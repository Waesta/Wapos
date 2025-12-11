<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * Bar Tab Management Service
 * 
 * Handles:
 * - Tab opening/closing with pre-auth
 * - Item management
 * - Split payments
 * - Tab transfers
 * - BOT generation
 */
class BarTabService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Generate unique tab number
     */
    public function generateTabNumber(): string
    {
        $date = date('ymd');
        $prefix = "TAB-{$date}-";
        
        $stmt = $this->db->prepare("
            SELECT tab_number FROM bar_tabs 
            WHERE tab_number LIKE ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetchColumn();
        
        if ($last) {
            $lastNum = (int)substr($last, -4);
            $newNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNum = '0001';
        }
        
        return $prefix . $newNum;
    }

    /**
     * Generate BOT number
     */
    public function generateBotNumber(): string
    {
        $date = date('ymd');
        $prefix = "BOT-{$date}-";
        
        $stmt = $this->db->prepare("
            SELECT bot_number FROM bar_order_tickets 
            WHERE bot_number LIKE ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetchColumn();
        
        if ($last) {
            $lastNum = (int)substr($last, -4);
            $newNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNum = '0001';
        }
        
        return $prefix . $newNum;
    }

    /**
     * Open a new bar tab
     */
    public function openTab(array $data): array
    {
        $tabNumber = $this->generateTabNumber();
        
        $stmt = $this->db->prepare("
            INSERT INTO bar_tabs (
                tab_number, tab_name, tab_type, customer_id, room_booking_id,
                member_id, preauth_amount, preauth_reference, preauth_expires_at,
                card_last_four, card_type, location_id, bar_station, table_id,
                server_id, guest_count, notes
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");
        
        $stmt->execute([
            $tabNumber,
            $data['tab_name'],
            $data['tab_type'] ?? 'name',
            $data['customer_id'] ?? null,
            $data['room_booking_id'] ?? null,
            $data['member_id'] ?? null,
            $data['preauth_amount'] ?? null,
            $data['preauth_reference'] ?? null,
            $data['preauth_expires_at'] ?? null,
            $data['card_last_four'] ?? null,
            $data['card_type'] ?? null,
            $data['location_id'] ?? null,
            $data['bar_station'] ?? 'Main Bar',
            $data['table_id'] ?? null,
            $data['server_id'],
            $data['guest_count'] ?? 1,
            $data['notes'] ?? null
        ]);
        
        $tabId = $this->db->lastInsertId();
        
        return $this->getTab($tabId);
    }

    /**
     * Get tab by ID
     */
    public function getTab(int $tabId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   u.full_name as server_name,
                   c.name as customer_name,
                   c.phone as customer_phone
            FROM bar_tabs t
            LEFT JOIN users u ON t.server_id = u.id
            LEFT JOIN customers c ON t.customer_id = c.id
            WHERE t.id = ?
        ");
        $stmt->execute([$tabId]);
        $tab = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tab) {
            $tab['items'] = $this->getTabItems($tabId);
            $tab['payments'] = $this->getTabPayments($tabId);
        }
        
        return $tab ?: null;
    }

    /**
     * Get all open tabs
     */
    public function getOpenTabs(?int $locationId = null, ?string $station = null): array
    {
        $sql = "
            SELECT t.*, 
                   u.full_name as server_name,
                   c.name as customer_name,
                   (SELECT COUNT(*) FROM bar_tab_items WHERE tab_id = t.id AND status != 'voided') as item_count
            FROM bar_tabs t
            LEFT JOIN users u ON t.server_id = u.id
            LEFT JOIN customers c ON t.customer_id = c.id
            WHERE t.status = 'open'
        ";
        $params = [];
        
        if ($locationId) {
            $sql .= " AND t.location_id = ?";
            $params[] = $locationId;
        }
        
        if ($station) {
            $sql .= " AND t.bar_station = ?";
            $params[] = $station;
        }
        
        $sql .= " ORDER BY t.opened_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add item to tab
     */
    public function addItem(int $tabId, array $item): array
    {
        // Check if added_by column exists
        $hasAddedBy = $this->hasColumn('bar_tab_items', 'added_by');
        
        $totalPrice = $item['unit_price'] * $item['quantity'];
        
        if ($hasAddedBy) {
            $stmt = $this->db->prepare("
                INSERT INTO bar_tab_items (
                    tab_id, product_id, portion_id, recipe_id, item_name,
                    portion_name, quantity, unit_price, total_price,
                    modifiers, special_instructions, added_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $tabId,
                $item['product_id'],
                $item['portion_id'] ?? null,
                $item['recipe_id'] ?? null,
                $item['item_name'],
                $item['portion_name'] ?? null,
                $item['quantity'],
                $item['unit_price'],
                $totalPrice,
                isset($item['modifiers']) ? json_encode($item['modifiers']) : null,
                $item['special_instructions'] ?? null,
                $item['added_by'] ?? null
            ]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO bar_tab_items (
                    tab_id, product_id, portion_id, recipe_id, item_name,
                    portion_name, quantity, unit_price, total_price,
                    modifiers, special_instructions
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $tabId,
                $item['product_id'],
                $item['portion_id'] ?? null,
                $item['recipe_id'] ?? null,
                $item['item_name'],
                $item['portion_name'] ?? null,
                $item['quantity'],
                $item['unit_price'],
                $totalPrice,
                isset($item['modifiers']) ? json_encode($item['modifiers']) : null,
                $item['special_instructions'] ?? null
            ]);
        }
        
        $itemId = $this->db->lastInsertId();
        
        // Update tab totals
        $this->recalculateTab($tabId);
        
        // Generate BOT if needed
        if (!empty($item['send_to_bar'])) {
            $this->createBotForItem($tabId, $itemId);
        }
        
        return ['id' => $itemId, 'total_price' => $totalPrice];
    }

    /**
     * Get tab items
     */
    public function getTabItems(int $tabId): array
    {
        $hasAddedBy = $this->hasColumn('bar_tab_items', 'added_by');
        
        if ($hasAddedBy) {
            $stmt = $this->db->prepare("
                SELECT i.*, p.name as product_name, p.image as product_image,
                       u.full_name as added_by_name
                FROM bar_tab_items i
                LEFT JOIN products p ON i.product_id = p.id
                LEFT JOIN users u ON i.added_by = u.id
                WHERE i.tab_id = ?
                ORDER BY i.created_at ASC
            ");
        } else {
            $stmt = $this->db->prepare("
                SELECT i.*, p.name as product_name, p.image as product_image,
                       NULL as added_by_name
                FROM bar_tab_items i
                LEFT JOIN products p ON i.product_id = p.id
                WHERE i.tab_id = ?
                ORDER BY i.created_at ASC
            ");
        }
        $stmt->execute([$tabId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Void item from tab
     */
    public function voidItem(int $itemId, int $userId, string $reason): bool
    {
        $stmt = $this->db->prepare("
            UPDATE bar_tab_items 
            SET status = 'voided', voided_by = ?, voided_at = NOW(), void_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$userId, $reason, $itemId]);
        
        // Get tab ID and recalculate
        $stmt = $this->db->prepare("SELECT tab_id FROM bar_tab_items WHERE id = ?");
        $stmt->execute([$itemId]);
        $tabId = $stmt->fetchColumn();
        
        if ($tabId) {
            $this->recalculateTab($tabId);
        }
        
        return true;
    }

    /**
     * Recalculate tab totals
     */
    public function recalculateTab(int $tabId): void
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(total_price), 0) as subtotal
            FROM bar_tab_items 
            WHERE tab_id = ? AND status != 'voided'
        ");
        $stmt->execute([$tabId]);
        $subtotal = (float)$stmt->fetchColumn();
        
        // Get tax rate (could be from settings)
        $taxRate = 0.16; // 16% VAT
        $taxAmount = $subtotal * $taxRate;
        
        // Get existing discount and tip
        $stmt = $this->db->prepare("SELECT discount_amount, tip_amount FROM bar_tabs WHERE id = ?");
        $stmt->execute([$tabId]);
        $tab = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $discount = (float)($tab['discount_amount'] ?? 0);
        $tip = (float)($tab['tip_amount'] ?? 0);
        $total = $subtotal + $taxAmount - $discount + $tip;
        
        $stmt = $this->db->prepare("
            UPDATE bar_tabs 
            SET subtotal = ?, tax_amount = ?, total_amount = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$subtotal, $taxAmount, $total, $tabId]);
    }

    /**
     * Apply discount to tab
     */
    public function applyDiscount(int $tabId, float $amount, ?string $reason = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE bar_tabs SET discount_amount = ? WHERE id = ?
        ");
        $stmt->execute([$amount, $tabId]);
        $this->recalculateTab($tabId);
        return true;
    }

    /**
     * Add tip to tab
     */
    public function addTip(int $tabId, float $amount): bool
    {
        $stmt = $this->db->prepare("
            UPDATE bar_tabs SET tip_amount = ? WHERE id = ?
        ");
        $stmt->execute([$amount, $tabId]);
        $this->recalculateTab($tabId);
        return true;
    }

    /**
     * Process payment for tab
     */
    public function processPayment(int $tabId, array $payment): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO bar_tab_payments (
                tab_id, payment_method, amount, tip_amount, reference_number,
                card_last_four, phone_number, room_number, split_guest_name,
                split_portion_percent, processed_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $tabId,
            $payment['method'],
            $payment['amount'],
            $payment['tip_amount'] ?? 0,
            $payment['reference'] ?? null,
            $payment['card_last_four'] ?? null,
            $payment['phone_number'] ?? null,
            $payment['room_number'] ?? null,
            $payment['split_guest_name'] ?? null,
            $payment['split_portion_percent'] ?? null,
            $payment['processed_by']
        ]);
        
        $paymentId = $this->db->lastInsertId();
        
        // Check if tab is fully paid
        $this->checkTabPaid($tabId);
        
        return ['payment_id' => $paymentId];
    }

    /**
     * Get tab payments
     */
    public function getTabPayments(int $tabId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, u.full_name as processed_by_name
            FROM bar_tab_payments p
            LEFT JOIN users u ON p.processed_by = u.id
            WHERE p.tab_id = ?
            ORDER BY p.processed_at ASC
        ");
        $stmt->execute([$tabId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if tab is fully paid and close if so
     */
    private function checkTabPaid(int $tabId): void
    {
        $stmt = $this->db->prepare("SELECT total_amount FROM bar_tabs WHERE id = ?");
        $stmt->execute([$tabId]);
        $total = (float)$stmt->fetchColumn();
        
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount + tip_amount), 0) FROM bar_tab_payments WHERE tab_id = ?");
        $stmt->execute([$tabId]);
        $paid = (float)$stmt->fetchColumn();
        
        if ($paid >= $total) {
            $this->closeTab($tabId, 'paid');
        } else {
            $stmt = $this->db->prepare("UPDATE bar_tabs SET status = 'pending_payment' WHERE id = ?");
            $stmt->execute([$tabId]);
        }
    }

    /**
     * Close tab
     */
    public function closeTab(int $tabId, string $status = 'paid'): bool
    {
        $stmt = $this->db->prepare("
            UPDATE bar_tabs 
            SET status = ?, closed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $tabId]);
        return true;
    }

    /**
     * Transfer tab to another tab
     */
    public function transferToTab(int $fromTabId, int $toTabId, int $userId, ?array $itemIds = null): bool
    {
        $this->db->beginTransaction();
        
        try {
            if ($itemIds) {
                // Transfer specific items
                $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
                $stmt = $this->db->prepare("
                    UPDATE bar_tab_items SET tab_id = ? WHERE id IN ($placeholders) AND tab_id = ?
                ");
                $stmt->execute(array_merge([$toTabId], $itemIds, [$fromTabId]));
                
                $stmt = $this->db->prepare("
                    SELECT COALESCE(SUM(total_price), 0) FROM bar_tab_items WHERE id IN ($placeholders)
                ");
                $stmt->execute($itemIds);
                $amount = $stmt->fetchColumn();
            } else {
                // Transfer entire tab
                $stmt = $this->db->prepare("
                    UPDATE bar_tab_items SET tab_id = ? WHERE tab_id = ?
                ");
                $stmt->execute([$toTabId, $fromTabId]);
                
                $stmt = $this->db->prepare("SELECT total_amount FROM bar_tabs WHERE id = ?");
                $stmt->execute([$fromTabId]);
                $amount = $stmt->fetchColumn();
                
                // Close original tab
                $this->closeTab($fromTabId, 'transferred');
            }
            
            // Log transfer
            $stmt = $this->db->prepare("
                INSERT INTO bar_tab_transfers (
                    from_tab_id, to_tab_id, transfer_type, items_transferred, 
                    amount_transferred, transferred_by
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $fromTabId,
                $toTabId,
                $itemIds ? 'items_only' : 'tab_to_tab',
                $itemIds ? json_encode($itemIds) : null,
                $amount,
                $userId
            ]);
            
            // Recalculate both tabs
            $this->recalculateTab($fromTabId);
            $this->recalculateTab($toTabId);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Transfer tab to room folio
     */
    public function transferToRoom(int $tabId, int $roomBookingId, int $userId): bool
    {
        $tab = $this->getTab($tabId);
        if (!$tab) {
            throw new Exception('Tab not found');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Create folio charge (assuming room_folio_charges table exists)
            $stmt = $this->db->prepare("
                INSERT INTO room_folio_charges (
                    booking_id, charge_type, description, amount, 
                    reference_type, reference_id, created_by
                ) VALUES (?, 'bar', ?, ?, 'bar_tab', ?, ?)
            ");
            $stmt->execute([
                $roomBookingId,
                "Bar Tab #{$tab['tab_number']}",
                $tab['total_amount'],
                $tabId,
                $userId
            ]);
            
            // Log transfer
            $stmt = $this->db->prepare("
                INSERT INTO bar_tab_transfers (
                    from_tab_id, to_room_folio_id, transfer_type, 
                    amount_transferred, transferred_by
                ) VALUES (?, ?, 'tab_to_room', ?, ?)
            ");
            $stmt->execute([$tabId, $roomBookingId, $tab['total_amount'], $userId]);
            
            // Close tab
            $this->closeTab($tabId, 'charged_to_room');
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Create BOT for items
     */
    public function createBot(int $tabId, array $itemIds, string $station = 'Main Bar', int $userId = 0): array
    {
        $botNumber = $this->generateBotNumber();
        
        // Get items
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $stmt = $this->db->prepare("
            SELECT * FROM bar_tab_items WHERE id IN ($placeholders)
        ");
        $stmt->execute($itemIds);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $this->db->prepare("
            INSERT INTO bar_order_tickets (
                bot_number, source_type, tab_id, bar_station, items, 
                item_count, created_by
            ) VALUES (?, 'tab', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $botNumber,
            $tabId,
            $station,
            json_encode($items),
            count($items),
            $userId
        ]);
        
        $botId = $this->db->lastInsertId();
        
        // Update items status
        $stmt = $this->db->prepare("
            UPDATE bar_tab_items SET status = 'pending' WHERE id IN ($placeholders)
        ");
        $stmt->execute($itemIds);
        
        return [
            'bot_id' => $botId,
            'bot_number' => $botNumber
        ];
    }

    /**
     * Create BOT for single item
     */
    private function createBotForItem(int $tabId, int $itemId): void
    {
        $this->createBot($tabId, [$itemId], 'Main Bar', 0);
    }

    /**
     * Get pending BOTs for a station
     */
    public function getPendingBots(?string $station = null): array
    {
        $sql = "
            SELECT b.*, t.tab_name, t.tab_number as source_tab
            FROM bar_order_tickets b
            LEFT JOIN bar_tabs t ON b.tab_id = t.id
            WHERE b.status IN ('pending', 'acknowledged', 'preparing')
        ";
        $params = [];
        
        if ($station) {
            $sql .= " AND b.bar_station = ?";
            $params[] = $station;
        }
        
        $sql .= " ORDER BY b.priority DESC, b.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update BOT status
     */
    public function updateBotStatus(int $botId, string $status, int $userId): bool
    {
        $updates = ['status' => $status];
        
        switch ($status) {
            case 'acknowledged':
                $updates['acknowledged_by'] = $userId;
                $updates['acknowledged_at'] = date('Y-m-d H:i:s');
                break;
            case 'preparing':
                $updates['prepared_by'] = $userId;
                break;
            case 'ready':
                $updates['prepared_at'] = date('Y-m-d H:i:s');
                break;
            case 'picked_up':
                $updates['picked_up_at'] = date('Y-m-d H:i:s');
                break;
        }
        
        $setClauses = [];
        $params = [];
        foreach ($updates as $col => $val) {
            $setClauses[] = "$col = ?";
            $params[] = $val;
        }
        $params[] = $botId;
        
        $stmt = $this->db->prepare("
            UPDATE bar_order_tickets SET " . implode(', ', $setClauses) . " WHERE id = ?
        ");
        $stmt->execute($params);
        
        return true;
    }

    /**
     * Get bar stations
     */
    public function getStations(): array
    {
        $stmt = $this->db->query("
            SELECT * FROM bar_stations WHERE is_active = 1 ORDER BY display_order
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if a column exists in a table
     */
    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = "$table.$column";
        
        if (!isset($cache[$key])) {
            try {
                $stmt = $this->db->prepare("SHOW COLUMNS FROM $table LIKE ?");
                $stmt->execute([$column]);
                $cache[$key] = $stmt->rowCount() > 0;
            } catch (\Exception $e) {
                $cache[$key] = false;
            }
        }
        
        return $cache[$key];
    }

    /**
     * Transfer tab to another waiter (shift handoff)
     */
    public function transferTab(int $tabId, int $fromWaiterId, int $toWaiterId, ?string $reason = null): bool
    {
        // Update tab server
        $stmt = $this->db->prepare("UPDATE bar_tabs SET server_id = ? WHERE id = ?");
        $stmt->execute([$toWaiterId, $tabId]);
        
        // Log the transfer
        $this->logTransfer('bar_tab', $tabId, $fromWaiterId, $toWaiterId, $reason);
        
        return true;
    }

    /**
     * Log service transfer for audit trail
     */
    private function logTransfer(string $sourceType, int $sourceId, int $fromId, int $toId, ?string $reason): void
    {
        try {
            // Ensure table exists
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS service_transfers (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    source_type ENUM('bar_tab', 'restaurant_order') NOT NULL,
                    source_id INT UNSIGNED NOT NULL,
                    from_waiter_id INT UNSIGNED NOT NULL,
                    to_waiter_id INT UNSIGNED NOT NULL,
                    transfer_reason VARCHAR(255) NULL,
                    transferred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_source (source_type, source_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $stmt = $this->db->prepare("
                INSERT INTO service_transfers (source_type, source_id, from_waiter_id, to_waiter_id, transfer_reason)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$sourceType, $sourceId, $fromId, $toId, $reason]);
        } catch (\Exception $e) {
            // Non-critical, just log
            error_log("Failed to log transfer: " . $e->getMessage());
        }
    }

    /**
     * Get waiter performance for a date range
     */
    public function getWaiterPerformance(int $waiterId, ?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?? date('Y-m-d');
        $endDate = $endDate ?? date('Y-m-d');
        
        // Items added by this waiter
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT bti.tab_id) as tabs_served,
                COUNT(bti.id) as items_served,
                SUM(CASE WHEN bti.status != 'voided' THEN bti.total_price ELSE 0 END) as total_sales,
                SUM(CASE WHEN bti.status = 'voided' THEN bti.total_price ELSE 0 END) as voided_amount
            FROM bar_tab_items bti
            WHERE bti.added_by = ?
            AND DATE(bti.added_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$waiterId, $startDate, $endDate]);
        $itemStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Tips earned
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(tip_amount), 0) as total_tips
            FROM bar_tab_payments btp
            JOIN bar_tabs bt ON btp.tab_id = bt.id
            WHERE bt.server_id = ?
            AND DATE(btp.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$waiterId, $startDate, $endDate]);
        $tips = $stmt->fetchColumn();
        
        return [
            'waiter_id' => $waiterId,
            'period' => ['start' => $startDate, 'end' => $endDate],
            'tabs_served' => (int)($itemStats['tabs_served'] ?? 0),
            'items_served' => (int)($itemStats['items_served'] ?? 0),
            'total_sales' => (float)($itemStats['total_sales'] ?? 0),
            'voided_amount' => (float)($itemStats['voided_amount'] ?? 0),
            'tips_earned' => (float)$tips
        ];
    }
}
