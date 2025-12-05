<?php

namespace App\Helpers;

/**
 * WhatsApp Helper
 * Generates click-to-chat links, QR codes, and share buttons
 */
class WhatsAppHelper
{
    /**
     * Generate WhatsApp click-to-chat URL
     */
    public static function getChatUrl(?string $message = null): string
    {
        $phone = self::getBusinessPhone();
        if (!$phone) {
            return '#';
        }
        
        // Format phone number (remove spaces, dashes, ensure country code)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        $url = "https://wa.me/{$phone}";
        if ($message) {
            $url .= "?text=" . urlencode($message);
        }
        
        return $url;
    }

    /**
     * Generate WhatsApp button HTML
     */
    public static function getButton(string $text = 'Chat on WhatsApp', ?string $message = null, string $class = 'btn btn-success'): string
    {
        $url = self::getChatUrl($message);
        return '<a href="' . htmlspecialchars($url) . '" target="_blank" class="' . htmlspecialchars($class) . '">
            <i class="bi bi-whatsapp me-1"></i>' . htmlspecialchars($text) . '
        </a>';
    }

    /**
     * Generate floating WhatsApp button HTML
     */
    public static function getFloatingButton(?string $message = null): string
    {
        $url = self::getChatUrl($message);
        return '
        <a href="' . htmlspecialchars($url) . '" target="_blank" 
           class="whatsapp-float" 
           style="position:fixed;bottom:20px;right:20px;width:60px;height:60px;background:#25D366;color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:30px;box-shadow:2px 2px 10px rgba(0,0,0,0.3);z-index:1000;text-decoration:none;">
            <i class="bi bi-whatsapp"></i>
        </a>';
    }

    /**
     * Generate QR code URL for WhatsApp chat
     * Uses Google Charts API for QR generation
     */
    public static function getQRCodeUrl(int $size = 200, ?string $message = null): string
    {
        $chatUrl = self::getChatUrl($message);
        return "https://chart.googleapis.com/chart?cht=qr&chs={$size}x{$size}&chl=" . urlencode($chatUrl);
    }

    /**
     * Generate QR code image tag
     */
    public static function getQRCodeImage(int $size = 200, ?string $message = null, string $alt = 'Scan to chat on WhatsApp'): string
    {
        $url = self::getQRCodeUrl($size, $message);
        return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($alt) . '" width="' . $size . '" height="' . $size . '">';
    }

    /**
     * Generate WhatsApp share URL for a message
     */
    public static function getShareUrl(string $message): string
    {
        return "https://wa.me/?text=" . urlencode($message);
    }

    /**
     * Generate booking confirmation message for WhatsApp
     */
    public static function getBookingMessage(array $booking): string
    {
        $businessName = self::getBusinessName();
        $msg = "Hi! I have a booking at {$businessName}.\n\n";
        $msg .= "Booking #: {$booking['booking_number']}\n";
        $msg .= "Name: {$booking['guest_name']}\n";
        $msg .= "Check-in: " . date('M j, Y', strtotime($booking['check_in_date'])) . "\n";
        $msg .= "Check-out: " . date('M j, Y', strtotime($booking['check_out_date'])) . "\n";
        return $msg;
    }

    /**
     * Generate order inquiry message for WhatsApp
     */
    public static function getOrderMessage(array $order): string
    {
        $msg = "Hi! I'd like to check on my order.\n\n";
        $msg .= "Order #: {$order['order_number']}\n";
        return $msg;
    }

    /**
     * Generate room service pre-filled message
     */
    public static function getRoomServiceMessage(string $roomNumber): string
    {
        return "Hi! I'm in Room {$roomNumber} and would like to order room service.";
    }

    /**
     * Generate housekeeping request pre-filled message
     */
    public static function getHousekeepingMessage(string $roomNumber): string
    {
        return "Hi! I'm in Room {$roomNumber} and need housekeeping assistance.";
    }

    /**
     * Generate maintenance request pre-filled message
     */
    public static function getMaintenanceMessage(string $roomNumber): string
    {
        return "Hi! I'm in Room {$roomNumber} and need to report a maintenance issue.";
    }

    /**
     * Generate menu order pre-filled message
     */
    public static function getMenuMessage(): string
    {
        return "Hi! I'd like to see your menu and place an order.";
    }

    /**
     * Get business WhatsApp phone number
     */
    private static function getBusinessPhone(): ?string
    {
        if (function_exists('settings')) {
            return settings('whatsapp_business_phone') ?? settings('business_phone');
        }
        return null;
    }

    /**
     * Get business name
     */
    private static function getBusinessName(): string
    {
        if (function_exists('settings')) {
            return settings('business_name') ?? 'Our Business';
        }
        return 'Our Business';
    }

    /**
     * Generate printable QR code card for rooms/tables
     */
    public static function getPrintableCard(string $location, string $type = 'room'): string
    {
        $businessName = self::getBusinessName();
        
        $messages = [
            'room' => self::getRoomServiceMessage($location),
            'table' => "Hi! I'm at Table {$location} and would like to order.",
            'general' => self::getMenuMessage()
        ];
        
        $message = $messages[$type] ?? $messages['general'];
        $qrUrl = self::getQRCodeUrl(150, $message);
        
        return '
        <div style="border:2px solid #25D366;border-radius:10px;padding:20px;text-align:center;max-width:200px;font-family:Arial,sans-serif;">
            <div style="color:#25D366;font-size:24px;margin-bottom:10px;">
                <i class="bi bi-whatsapp"></i>
            </div>
            <div style="font-weight:bold;margin-bottom:5px;">' . htmlspecialchars($businessName) . '</div>
            <div style="font-size:12px;color:#666;margin-bottom:10px;">' . ucfirst($type) . ' ' . htmlspecialchars($location) . '</div>
            <img src="' . htmlspecialchars($qrUrl) . '" alt="Scan to order" width="150" height="150">
            <div style="font-size:11px;color:#666;margin-top:10px;">Scan to order via WhatsApp</div>
        </div>';
    }
}
