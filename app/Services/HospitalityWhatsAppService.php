<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * Hospitality WhatsApp Service
 * Handles WhatsApp messaging for room bookings, room service, housekeeping, and guest communication
 */
class HospitalityWhatsAppService
{
    private PDO $db;
    private ?string $accessToken;
    private ?string $phoneNumberId;

    private const STATE_IDLE = 'idle';
    private const STATE_BOOKING_ROOM_TYPE = 'booking_room_type';
    private const STATE_BOOKING_DATES = 'booking_dates';
    private const STATE_BOOKING_GUESTS = 'booking_guests';
    private const STATE_BOOKING_NAME = 'booking_name';
    private const STATE_BOOKING_CONFIRM = 'booking_confirm';
    private const STATE_ROOM_SERVICE_MENU = 'room_service_menu';
    private const STATE_ROOM_SERVICE_CONFIRM = 'room_service_confirm';
    private const STATE_HOUSEKEEPING_TYPE = 'housekeeping_type';
    private const STATE_MAINTENANCE_DESCRIBE = 'maintenance_describe';

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->loadSettings();
        $this->ensureConversationStateTable();
    }

    private function loadSettings(): void
    {
        $settings = function_exists('settings_many')
            ? settings_many(['whatsapp_access_token', 'whatsapp_api_token', 'whatsapp_phone_number_id'])
            : [];
        $this->accessToken = $settings['whatsapp_access_token'] ?? $settings['whatsapp_api_token'] ?? null;
        $this->phoneNumberId = $settings['whatsapp_phone_number_id'] ?? null;
    }

    public function processMessage(string $phone, string $message, string $messageId): array
    {
        $phone = $this->formatPhone($phone);
        $messageLower = strtolower(trim($message));

        if (in_array($messageLower, ['cancel', 'stop', 'reset', 'menu', 'help'])) {
            $this->clearState($phone);
            return $this->getMainMenu();
        }

        $state = $this->getState($phone);
        if ($state['state'] !== self::STATE_IDLE) {
            return $this->continueFlow($phone, $message, $state);
        }

        return $this->detectIntent($phone, $messageLower, $message);
    }

    private function detectIntent(string $phone, string $lower, string $original): array
    {
        if ($this->hasKeyword($lower, ['book', 'reserve', 'room', 'stay', 'available'])) {
            return $this->startBooking($phone);
        }
        if ($this->hasKeyword($lower, ['room service', 'food', 'order food', 'breakfast', 'lunch', 'dinner'])) {
            return $this->startRoomService($phone);
        }
        if ($this->hasKeyword($lower, ['housekeeping', 'clean', 'towel', 'sheets'])) {
            return $this->startHousekeeping($phone);
        }
        if ($this->hasKeyword($lower, ['maintenance', 'repair', 'broken', 'fix', 'not working'])) {
            return $this->startMaintenance($phone);
        }
        if ($this->hasKeyword($lower, ['status', 'my booking', 'reservation'])) {
            return $this->getBookingStatus($phone);
        }
        if ($this->hasKeyword($lower, ['contact', 'reception', 'call'])) {
            return $this->getContactInfo();
        }
        if ($this->hasKeyword($lower, ['hi', 'hello', 'hey'])) {
            return $this->getMainMenu();
        }
        return $this->getMainMenu(true);
    }

    private function getMainMenu(bool $showHelp = false): array
    {
        $name = $this->getBusinessName();
        $msg = $showHelp ? "I didn't understand. Here's what I can help with:\n\n" : "Welcome to {$name}! 🏨\n\n";
        $msg .= "🛏️ *BOOK* - Reserve a room\n";
        $msg .= "🍽️ *ROOM SERVICE* - Order food\n";
        $msg .= "🧹 *HOUSEKEEPING* - Request cleaning\n";
        $msg .= "🔧 *MAINTENANCE* - Report issue\n";
        $msg .= "📋 *STATUS* - Check booking\n";
        $msg .= "📞 *CONTACT* - Reception\n\n";
        $msg .= "Type a keyword to get started!";
        return ['success' => true, 'response' => $msg, 'type' => 'menu'];
    }

    private function startBooking(string $phone): array
    {
        $types = $this->db->query("SELECT * FROM room_types WHERE is_active = 1 ORDER BY base_rate")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($types)) {
            return ['success' => true, 'response' => "No rooms available. Type *CONTACT* for assistance.", 'type' => 'error'];
        }

        $msg = "🛏️ *Room Reservation*\n\n*Available Room Types:*\n\n";
        foreach ($types as $i => $t) {
            $msg .= ($i + 1) . ". *{$t['name']}* - " . $this->formatCurrency($t['base_rate']) . "/night\n";
        }
        $msg .= "\nReply with the number of your choice.";

        $this->setState($phone, ['state' => self::STATE_BOOKING_ROOM_TYPE, 'room_types' => $types]);
        return ['success' => true, 'response' => $msg, 'type' => 'booking_start'];
    }

    private function continueFlow(string $phone, string $msg, array $state): array
    {
        switch ($state['state']) {
            case self::STATE_BOOKING_ROOM_TYPE: return $this->handleRoomType($phone, $msg, $state);
            case self::STATE_BOOKING_DATES: return $this->handleDates($phone, $msg, $state);
            case self::STATE_BOOKING_GUESTS: return $this->handleGuests($phone, $msg, $state);
            case self::STATE_BOOKING_NAME: return $this->handleName($phone, $msg, $state);
            case self::STATE_BOOKING_CONFIRM: return $this->handleConfirm($phone, $msg, $state);
            case self::STATE_ROOM_SERVICE_MENU: return $this->handleServiceOrder($phone, $msg, $state);
            case self::STATE_ROOM_SERVICE_CONFIRM: return $this->confirmServiceOrder($phone, $msg, $state);
            case self::STATE_HOUSEKEEPING_TYPE: return $this->handleHousekeeping($phone, $msg, $state);
            case self::STATE_MAINTENANCE_DESCRIBE: return $this->handleMaintenance($phone, $msg, $state);
            default: $this->clearState($phone); return $this->getMainMenu();
        }
    }

    private function handleRoomType(string $phone, string $msg, array $state): array
    {
        $sel = (int)$msg;
        $types = $state['room_types'];
        if ($sel < 1 || $sel > count($types)) {
            return ['success' => true, 'response' => "Enter 1-" . count($types) . " or *CANCEL*", 'type' => 'error'];
        }
        $state['room_type'] = $types[$sel - 1];
        $state['state'] = self::STATE_BOOKING_DATES;
        $this->setState($phone, $state);
        return ['success' => true, 'response' => "✅ *{$types[$sel-1]['name']}*\n\n📅 Enter dates:\n*Check-in - Check-out*\nExample: `15 Dec - 18 Dec`", 'type' => 'dates'];
    }

    private function handleDates(string $phone, string $msg, array $state): array
    {
        $dates = $this->parseDates($msg);
        if (!$dates) {
            return ['success' => true, 'response' => "Invalid dates. Try: `15 Dec - 18 Dec`", 'type' => 'error'];
        }
        $nights = (new \DateTime($dates['in']))->diff(new \DateTime($dates['out']))->days;
        $state['check_in'] = $dates['in'];
        $state['check_out'] = $dates['out'];
        $state['nights'] = $nights;
        $state['total'] = $state['room_type']['base_rate'] * $nights;
        $state['state'] = self::STATE_BOOKING_GUESTS;
        $this->setState($phone, $state);
        return ['success' => true, 'response' => "✅ {$nights} nights\n\n👥 How many guests?\nFormat: `Adults, Children`\nExample: `2, 1`", 'type' => 'guests'];
    }

    private function handleGuests(string $phone, string $msg, array $state): array
    {
        $parts = explode(',', $msg);
        $adults = max(1, (int)($parts[0] ?? 1));
        $children = max(0, (int)($parts[1] ?? 0));
        $state['adults'] = $adults;
        $state['children'] = $children;
        $state['state'] = self::STATE_BOOKING_NAME;
        $this->setState($phone, $state);
        return ['success' => true, 'response' => "✅ {$adults} adult(s), {$children} child(ren)\n\n👤 Guest name:", 'type' => 'name'];
    }

    private function handleName(string $phone, string $msg, array $state): array
    {
        if (strlen(trim($msg)) < 2) {
            return ['success' => true, 'response' => "Enter a valid name.", 'type' => 'error'];
        }
        $state['guest_name'] = trim($msg);
        $state['state'] = self::STATE_BOOKING_CONFIRM;
        $this->setState($phone, $state);

        $r = $state['room_type'];
        $summary = "📋 *Booking Summary*\n━━━━━━━━━━━━━━━━━━\n";
        $summary .= "🛏️ {$r['name']}\n👤 {$state['guest_name']}\n";
        $summary .= "📅 " . date('M j', strtotime($state['check_in'])) . " - " . date('M j, Y', strtotime($state['check_out'])) . "\n";
        $summary .= "🌙 {$state['nights']} nights\n👥 {$state['adults']} adults";
        if ($state['children'] > 0) $summary .= ", {$state['children']} children";
        $summary .= "\n💰 " . $this->formatCurrency($state['total']) . "\n━━━━━━━━━━━━━━━━━━\n\nReply *YES* or *NO*";
        return ['success' => true, 'response' => $summary, 'type' => 'confirm'];
    }

    private function handleConfirm(string $phone, string $msg, array $state): array
    {
        $r = strtolower(trim($msg));
        if (in_array($r, ['yes', 'y', 'ok'])) {
            try {
                $booking = $this->createBooking($phone, $state);
                $this->clearState($phone);
                return ['success' => true, 'response' => "🎉 *Confirmed!*\n\n📋 Booking: {$booking['number']}\n💰 Total: " . $this->formatCurrency($state['total']) . "\n\nWe look forward to your stay!", 'type' => 'complete'];
            } catch (Exception $e) {
                return ['success' => false, 'response' => "Error creating booking. Type *CONTACT* for help.", 'type' => 'error'];
            }
        }
        if (in_array($r, ['no', 'n', 'cancel'])) {
            $this->clearState($phone);
            return ['success' => true, 'response' => "Cancelled. Type *BOOK* to start again.", 'type' => 'cancelled'];
        }
        return ['success' => true, 'response' => "Reply *YES* or *NO*", 'type' => 'error'];
    }

    private function startRoomService(string $phone): array
    {
        $booking = $this->getActiveBooking($phone);
        if (!$booking) {
            return ['success' => true, 'response' => "Room service is for checked-in guests. Type *CONTACT* for help.", 'type' => 'error'];
        }
        $items = $this->db->query("SELECT id, name, price FROM products WHERE is_active = 1 LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($items)) {
            return ['success' => true, 'response' => "Menu unavailable. Type *CONTACT*.", 'type' => 'error'];
        }
        $msg = "🍽️ *Room Service*\nRoom: {$booking['room_number']}\n\n";
        foreach ($items as $i => $item) {
            $msg .= ($i + 1) . ". {$item['name']} - " . $this->formatCurrency($item['price']) . "\n";
        }
        $msg .= "\nOrder: `1x2, 3x1` (2 of #1, 1 of #3)";
        $this->setState($phone, ['state' => self::STATE_ROOM_SERVICE_MENU, 'booking' => $booking, 'items' => $items]);
        return ['success' => true, 'response' => $msg, 'type' => 'menu'];
    }

    private function handleServiceOrder(string $phone, string $msg, array $state): array
    {
        $items = $state['items'];
        $order = [];
        $total = 0;
        preg_match_all('/(\d+)\s*[x\*]?\s*(\d*)/', $msg, $m, PREG_SET_ORDER);
        foreach ($m as $match) {
            $num = (int)$match[1];
            $qty = !empty($match[2]) ? (int)$match[2] : 1;
            if ($num >= 1 && $num <= count($items)) {
                $item = $items[$num - 1];
                $order[] = ['item' => $item, 'qty' => $qty, 'sub' => $item['price'] * $qty];
                $total += $item['price'] * $qty;
            }
        }
        if (empty($order)) {
            return ['success' => true, 'response' => "Invalid order. Try: `1x2, 3`", 'type' => 'error'];
        }
        $state['order'] = $order;
        $state['total'] = $total;
        $state['state'] = self::STATE_ROOM_SERVICE_CONFIRM;
        $this->setState($phone, $state);
        $msg = "🍽️ *Order Summary*\n";
        foreach ($order as $o) $msg .= "• {$o['qty']}x {$o['item']['name']} - " . $this->formatCurrency($o['sub']) . "\n";
        $msg .= "\n💰 Total: " . $this->formatCurrency($total) . "\n\nReply *YES* or *NO*";
        return ['success' => true, 'response' => $msg, 'type' => 'confirm'];
    }

    private function confirmServiceOrder(string $phone, string $msg, array $state): array
    {
        if (in_array(strtolower($msg), ['yes', 'y'])) {
            foreach ($state['order'] as $o) {
                $this->addFolioEntry($state['booking']['id'], 'service', "Room Service: {$o['item']['name']}", $o['sub'], $o['qty']);
            }
            $this->clearState($phone);
            return ['success' => true, 'response' => "✅ Order placed! Delivery in 20-30 mins.", 'type' => 'complete'];
        }
        $this->clearState($phone);
        return ['success' => true, 'response' => "Cancelled. Type *ROOM SERVICE* to order again.", 'type' => 'cancelled'];
    }

    private function startHousekeeping(string $phone): array
    {
        $booking = $this->getActiveBooking($phone);
        if (!$booking) {
            return ['success' => true, 'response' => "Housekeeping is for guests. Type *CONTACT*.", 'type' => 'error'];
        }
        $msg = "🧹 *Housekeeping*\nRoom: {$booking['room_number']}\n\n1. Full cleaning\n2. Fresh towels\n3. Toiletries\n4. Bed linen\n5. Trash removal\n\nReply with number.";
        $this->setState($phone, ['state' => self::STATE_HOUSEKEEPING_TYPE, 'booking' => $booking]);
        return ['success' => true, 'response' => $msg, 'type' => 'menu'];
    }

    private function handleHousekeeping(string $phone, string $msg, array $state): array
    {
        $types = [1 => 'Full cleaning', 2 => 'Fresh towels', 3 => 'Toiletries', 4 => 'Bed linen', 5 => 'Trash removal'];
        $sel = (int)$msg;
        if (!isset($types[$sel])) {
            return ['success' => true, 'response' => "Enter 1-5.", 'type' => 'error'];
        }
        $this->createHousekeepingTask($state['booking'], $types[$sel]);
        $this->clearState($phone);
        return ['success' => true, 'response' => "✅ *{$types[$sel]}* requested!\nETA: 15-30 mins.", 'type' => 'complete'];
    }

    private function startMaintenance(string $phone): array
    {
        $booking = $this->getActiveBooking($phone);
        $msg = "🔧 *Report Issue*\n";
        if ($booking) $msg .= "Room: {$booking['room_number']}\n";
        $msg .= "\nDescribe the problem:";
        $this->setState($phone, ['state' => self::STATE_MAINTENANCE_DESCRIBE, 'booking' => $booking]);
        return ['success' => true, 'response' => $msg, 'type' => 'input'];
    }

    private function handleMaintenance(string $phone, string $msg, array $state): array
    {
        if (strlen($msg) < 5) {
            return ['success' => true, 'response' => "Please provide more details.", 'type' => 'error'];
        }
        $this->createMaintenanceRequest($phone, $state['booking'], $msg);
        $this->clearState($phone);
        return ['success' => true, 'response' => "✅ Issue reported! Our team will address it soon.", 'type' => 'complete'];
    }

    private function getBookingStatus(string $phone): array
    {
        $booking = $this->getActiveBooking($phone) ?? $this->getUpcomingBooking($phone);
        if (!$booking) {
            return ['success' => true, 'response' => "No bookings found. Type *BOOK* to reserve.", 'type' => 'not_found'];
        }
        $msg = "📋 *Booking Status*\n━━━━━━━━━━━━━━━━━━\n";
        $msg .= "📋 {$booking['booking_number']}\n";
        $msg .= "🛏️ " . ($booking['room_number'] ?? $booking['room_type_name']) . "\n";
        $msg .= "📅 " . date('M j', strtotime($booking['check_in_date'])) . " - " . date('M j', strtotime($booking['check_out_date'])) . "\n";
        $msg .= "✅ " . ucfirst(str_replace('_', ' ', $booking['status']));
        return ['success' => true, 'response' => $msg, 'type' => 'status'];
    }

    private function getContactInfo(): array
    {
        $name = $this->getBusinessName();
        $phone = function_exists('settings') ? settings('business_phone') : '';
        return ['success' => true, 'response' => "📞 *Contact*\n\n🏨 {$name}\n📱 {$phone}\n\n24-hour front desk", 'type' => 'contact'];
    }

    // Database helpers
    private function createBooking(string $phone, array $state): array
    {
        $room = $this->db->query("SELECT id FROM rooms WHERE room_type_id = {$state['room_type']['id']} AND status = 'available' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$room) throw new Exception('No room available');
        $num = 'WA' . date('ymd') . strtoupper(substr(uniqid(), -4));
        $stmt = $this->db->prepare("INSERT INTO room_bookings (booking_number, room_id, guest_name, guest_phone, check_in_date, check_out_date, adults, children, total_nights, rate_per_night, total_amount, status, payment_status, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'unpaid', 'WhatsApp booking', NOW(), NOW())");
        $stmt->execute([$num, $room['id'], $state['guest_name'], $phone, $state['check_in'], $state['check_out'], $state['adults'], $state['children'], $state['nights'], $state['room_type']['base_rate'], $state['total']]);
        $id = $this->db->lastInsertId();
        $this->addFolioEntry($id, 'room_charge', 'Room charge', $state['total'], $state['nights']);
        return ['id' => $id, 'number' => $num];
    }

    private function addFolioEntry(int $bookingId, string $type, string $desc, float $amount, int $qty = 1): void
    {
        $stmt = $this->db->prepare("INSERT INTO room_folios (booking_id, item_type, description, amount, quantity, date_charged, created_at) VALUES (?, ?, ?, ?, ?, CURDATE(), NOW())");
        $stmt->execute([$bookingId, $type, $desc, $amount, $qty]);
    }

    private function getActiveBooking(string $phone): ?array
    {
        $stmt = $this->db->prepare("SELECT b.*, r.room_number, rt.name as room_type_name FROM room_bookings b JOIN rooms r ON b.room_id = r.id JOIN room_types rt ON r.room_type_id = rt.id WHERE b.guest_phone = ? AND b.status = 'checked_in' LIMIT 1");
        $stmt->execute([$phone]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getUpcomingBooking(string $phone): ?array
    {
        $stmt = $this->db->prepare("SELECT b.*, r.room_number, rt.name as room_type_name FROM room_bookings b JOIN rooms r ON b.room_id = r.id JOIN room_types rt ON r.room_type_id = rt.id WHERE b.guest_phone = ? AND b.status IN ('pending', 'confirmed') AND b.check_in_date >= CURDATE() LIMIT 1");
        $stmt->execute([$phone]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function createHousekeepingTask(array $booking, string $title): void
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO housekeeping_tasks (room_id, booking_id, title, priority, status, notes, created_at) VALUES (?, ?, ?, 'normal', 'pending', 'WhatsApp request', NOW())");
            $stmt->execute([$booking['room_id'], $booking['id'], $title]);
        } catch (Exception $e) {
            error_log("Housekeeping: {$booking['room_number']} - {$title}");
        }
    }

    private function createMaintenanceRequest(string $phone, ?array $booking, string $desc): void
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO maintenance_requests (room_id, booking_id, title, description, priority, status, reporter_type, reporter_contact, created_at) VALUES (?, ?, ?, ?, 'normal', 'open', 'guest', ?, NOW())");
            $stmt->execute([$booking['room_id'] ?? null, $booking['id'] ?? null, substr($desc, 0, 100), $desc, $phone]);
        } catch (Exception $e) {
            error_log("Maintenance from {$phone}: {$desc}");
        }
    }

    // State management
    private function getState(string $phone): array
    {
        $stmt = $this->db->prepare("SELECT state_data FROM whatsapp_conversation_states WHERE phone = ? AND expires_at > NOW()");
        $stmt->execute([$phone]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? (json_decode($r['state_data'], true) ?? ['state' => self::STATE_IDLE]) : ['state' => self::STATE_IDLE];
    }

    private function setState(string $phone, array $state): void
    {
        $stmt = $this->db->prepare("INSERT INTO whatsapp_conversation_states (phone, state_data, expires_at, updated_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW()) ON DUPLICATE KEY UPDATE state_data = VALUES(state_data), expires_at = VALUES(expires_at), updated_at = NOW()");
        $stmt->execute([$phone, json_encode($state)]);
    }

    private function clearState(string $phone): void
    {
        $this->db->prepare("DELETE FROM whatsapp_conversation_states WHERE phone = ?")->execute([$phone]);
    }

    private function ensureConversationStateTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS whatsapp_conversation_states (phone VARCHAR(20) PRIMARY KEY, state_data JSON, expires_at DATETIME, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
    }

    // Utilities
    private function hasKeyword(string $text, array $keywords): bool
    {
        foreach ($keywords as $k) if (strpos($text, $k) !== false) return true;
        return false;
    }

    private function parseDates(string $input): ?array
    {
        $parts = preg_split('/\s*[-–to]+\s/', $input, 2);
        if (count($parts) !== 2) return null;
        $in = strtotime($parts[0]);
        $out = strtotime($parts[1]);
        if (!$in || !$out || $out <= $in) return null;
        return ['in' => date('Y-m-d', $in), 'out' => date('Y-m-d', $out)];
    }

    private function formatPhone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', $phone);
    }

    private function formatCurrency(float $amount): string
    {
        $s = function_exists('settings') ? (settings('currency_symbol') ?? 'KES') : 'KES';
        return $s . ' ' . number_format($amount, 2);
    }

    private function getBusinessName(): string
    {
        return function_exists('settings') ? (settings('business_name') ?? 'Our Hotel') : 'Our Hotel';
    }

    public function sendMessage(string $to, string $message): array
    {
        if (!$this->accessToken || !$this->phoneNumberId) return ['success' => false, 'error' => 'Not configured'];
        $ch = curl_init("https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['messaging_product' => 'whatsapp', 'to' => $this->formatPhone($to), 'type' => 'text', 'text' => ['body' => $message]]), CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->accessToken, 'Content-Type: application/json']]);
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['success' => $code >= 200 && $code < 300, 'response' => json_decode($r, true)];
    }
}
