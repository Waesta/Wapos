<?php

namespace App\Services;

use PDO;

/**
 * WhatsApp Business Cloud API Service
 * Handles WhatsApp messaging integration
 */
class WhatsAppService
{
    private PDO $db;
    private ?string $accessToken;
    private ?string $phoneNumberId;
    private ?string $businessAccountId;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->loadSettings();
    }

    /**
     * Load WhatsApp settings from database
     */
    private function loadSettings(): void
    {
        $settings = function_exists('settings_many')
            ? settings_many([
                'whatsapp_access_token',
                'whatsapp_phone_number_id',
                'whatsapp_business_account_id'
            ])
            : [];

        $this->accessToken = $settings['whatsapp_access_token'] ?? null;
        $this->phoneNumberId = $settings['whatsapp_phone_number_id'] ?? null;
        $this->businessAccountId = $settings['whatsapp_business_account_id'] ?? null;
    }

    /**
     * Send template message
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        array $parameters = [],
        ?string $languageCode = 'en'
    ): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'WhatsApp not configured'
            ];
        }

        $url = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->formatPhoneNumber($to),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode
                ]
            ]
        ];

        if (!empty($parameters)) {
            $payload['template']['components'] = [
                [
                    'type' => 'body',
                    'parameters' => array_map(function($param) {
                        return ['type' => 'text', 'text' => $param];
                    }, $parameters)
                ]
            ];
        }

        $response = $this->sendRequest($url, $payload);

        // Log message
        $this->logMessage([
            'phone' => $to,
            'direction' => 'out',
            'template_name' => $templateName,
            'status' => $response['success'] ? 'sent' : 'failed',
            'waba_msg_id' => $response['data']['messages'][0]['id'] ?? null,
            'error' => $response['error'] ?? null
        ]);

        return $response;
    }

    /**
     * Send order confirmation
     */
    public function sendOrderConfirmation(int $orderId, string $customerPhone): bool
    {
        $order = $this->getOrder($orderId);
        
        if (!$order) {
            return false;
        }

        $result = $this->sendTemplate(
            $customerPhone,
            'order_confirmation',
            [
                $order['order_number'],
                $order['total_amount'],
                $order['estimated_delivery']
            ]
        );

        if ($result['success']) {
            $this->updateOrderSource($orderId, 'whatsapp');
        }

        return $result['success'];
    }

    /**
     * Send delivery status update
     */
    public function sendDeliveryUpdate(int $orderId, string $status): bool
    {
        $order = $this->getOrder($orderId);
        
        if (!$order || !$order['customer_phone']) {
            return false;
        }

        $templateMap = [
            'out_for_delivery' => 'order_out_for_delivery',
            'delivered' => 'order_delivered'
        ];

        $templateName = $templateMap[$status] ?? null;
        
        if (!$templateName) {
            return false;
        }

        $result = $this->sendTemplate(
            $order['customer_phone'],
            $templateName,
            [$order['order_number']]
        );

        return $result['success'];
    }

    /**
     * Handle incoming webhook
     */
    public function handleWebhook(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if ($change['field'] === 'messages') {
                    $this->processMessage($change['value']);
                }
            }
        }
    }

    /**
     * Process incoming message
     */
    private function processMessage(array $value): void
    {
        foreach ($value['messages'] ?? [] as $message) {
            $from = $message['from'];
            $messageId = $message['id'];
            $type = $message['type'];
            $text = $message['text']['body'] ?? '';

            // Log incoming message
            $this->logMessage([
                'phone' => $from,
                'direction' => 'in',
                'waba_msg_id' => $messageId,
                'status' => 'received',
                'message_text' => $text
            ]);

            // Parse intent and create draft order
            $this->parseAndCreateOrder($from, $text);
        }

        // Mark as read
        foreach ($value['statuses'] ?? [] as $status) {
            $this->updateMessageStatus($status['id'], $status['status']);
        }
    }

    /**
     * Parse message and create draft order
     */
    private function parseAndCreateOrder(string $phone, string $text): void
    {
        // Simple intent parsing (can be enhanced with NLP)
        $keywords = ['order', 'buy', 'purchase', 'want'];
        
        $hasIntent = false;
        foreach ($keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $hasIntent = true;
                break;
            }
        }

        if (!$hasIntent) {
            return;
        }

        // Find or create customer
        $customer = $this->findCustomerByPhone($phone);
        
        if (!$customer) {
            $customerId = $this->createCustomer($phone);
        } else {
            $customerId = $customer['id'];
        }

        // Create draft order
        $sql = "INSERT INTO orders 
                (customer_id, order_source, status, notes, created_at) 
                VALUES (?, 'whatsapp', 'draft', ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$customerId, "WhatsApp message: {$text}"]);
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $signature, string $payload): bool
    {
        $appSecret = $this->getAppSecret();
        
        if (!$appSecret) {
            return false;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Send HTTP request to WhatsApp API
     */
    private function sendRequest(string $url, array $payload): array
    {
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => $error
            ];
        }

        $data = json_decode($response, true);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => $data,
            'error' => $data['error']['message'] ?? null
        ];
    }

    /**
     * Log WhatsApp message
     */
    private function logMessage(array $data): void
    {
        $this->ensureTableExists();

        $sql = "INSERT INTO whatsapp_messages 
                (customer_id, order_id, direction, template_name, message_text, 
                 status, waba_msg_id, error, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['customer_id'] ?? null,
            $data['order_id'] ?? null,
            $data['direction'],
            $data['template_name'] ?? null,
            $data['message_text'] ?? null,
            $data['status'],
            $data['waba_msg_id'] ?? null,
            $data['error'] ?? null
        ]);
    }

    /**
     * Update message status
     */
    private function updateMessageStatus(string $wabaMessageId, string $status): void
    {
        $sql = "UPDATE whatsapp_messages SET status = ? WHERE waba_msg_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $wabaMessageId]);
    }

    /**
     * Utility methods
     */
    private function isConfigured(): bool
    {
        return !empty($this->accessToken) && !empty($this->phoneNumberId);
    }

    private function formatPhoneNumber(string $phone): string
    {
        // Remove non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if missing (assuming default)
        if (strlen($phone) < 10) {
            return $phone;
        }
        
        return $phone;
    }

    private function getOrder(int $orderId): ?array
    {
        $sql = "SELECT * FROM orders WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orderId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    private function updateOrderSource(int $orderId, string $source): void
    {
        $sql = "UPDATE orders SET order_source = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$source, $orderId]);
    }

    private function findCustomerByPhone(string $phone): ?array
    {
        $sql = "SELECT * FROM customers WHERE phone = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->formatPhoneNumber($phone)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    private function createCustomer(string $phone): int
    {
        $sql = "INSERT INTO customers (phone, name, created_at) VALUES (?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->formatPhoneNumber($phone), 'WhatsApp Customer']);
        return (int) $this->db->lastInsertId();
    }

    private function getAppSecret(): ?string
    {
        return function_exists('settings')
            ? settings('whatsapp_app_secret')
            : null;
    }

    private function ensureTableExists(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS whatsapp_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED,
            order_id INT UNSIGNED,
            direction ENUM('in', 'out') NOT NULL,
            template_name VARCHAR(100),
            message_text TEXT,
            status VARCHAR(50),
            waba_msg_id VARCHAR(255),
            error TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer (customer_id),
            INDEX idx_order (order_id),
            INDEX idx_waba_msg (waba_msg_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB";

        $this->db->exec($sql);
    }
}
