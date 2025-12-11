<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * NotificationService
 * Handles Email, SMS, and WhatsApp notifications for marketing, 
 * communication, and customer appreciation.
 */
class NotificationService
{
    private PDO $db;
    private array $settings = [];
    
    // Notification channels
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_SMS = 'sms';
    const CHANNEL_WHATSAPP = 'whatsapp';
    
    // Notification types
    const TYPE_MARKETING = 'marketing';
    const TYPE_TRANSACTIONAL = 'transactional';
    const TYPE_APPRECIATION = 'appreciation';
    const TYPE_REMINDER = 'reminder';
    const TYPE_ALERT = 'alert';

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->loadSettings();
    }

    /**
     * Load notification settings from database
     */
    private function loadSettings(): void
    {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'notification_%' OR setting_key LIKE 'smtp_%' OR setting_key LIKE 'sms_%'");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    /**
     * Send notification via specified channel
     */
    public function send(string $channel, string $recipient, string $subject, string $message, array $options = []): array
    {
        $result = ['success' => false, 'message' => '', 'notification_id' => null];

        try {
            // Log the notification attempt
            $notificationId = $this->logNotification($channel, $recipient, $subject, $message, $options);
            $result['notification_id'] = $notificationId;

            switch ($channel) {
                case self::CHANNEL_EMAIL:
                    $result = $this->sendEmail($recipient, $subject, $message, $options);
                    break;
                case self::CHANNEL_SMS:
                    $result = $this->sendSMS($recipient, $message, $options);
                    break;
                case self::CHANNEL_WHATSAPP:
                    $result = $this->sendWhatsApp($recipient, $message, $options);
                    break;
                default:
                    throw new Exception("Unknown notification channel: $channel");
            }

            // Update log with result
            $this->updateNotificationLog($notificationId, $result['success'], $result['message']);
            $result['notification_id'] = $notificationId;

        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            if (isset($notificationId)) {
                $this->updateNotificationLog($notificationId, false, $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Send Email using SMTP
     */
    private function sendEmail(string $to, string $subject, string $body, array $options = []): array
    {
        $smtpHost = $this->settings['smtp_host'] ?? (defined('MAIL_HOST') ? MAIL_HOST : '');
        $smtpPort = $this->settings['smtp_port'] ?? (defined('MAIL_PORT') ? MAIL_PORT : 587);
        $smtpUser = $this->settings['smtp_username'] ?? (defined('MAIL_USERNAME') ? MAIL_USERNAME : '');
        $smtpPass = $this->settings['smtp_password'] ?? (defined('MAIL_PASSWORD') ? MAIL_PASSWORD : '');
        $fromEmail = $this->settings['smtp_from_email'] ?? (defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : '');
        $fromName = $this->settings['smtp_from_name'] ?? (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'WAPOS');

        if (empty($smtpHost) || empty($smtpUser)) {
            return ['success' => false, 'message' => 'SMTP not configured'];
        }

        // Check if PHPMailer is available
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return $this->sendWithPHPMailer($to, $subject, $body, $options, [
                'host' => $smtpHost,
                'port' => $smtpPort,
                'username' => $smtpUser,
                'password' => $smtpPass,
                'from_email' => $fromEmail,
                'from_name' => $fromName
            ]);
        }

        // Fallback to native mail() function
        $headers = [
            'From' => "$fromName <$fromEmail>",
            'Reply-To' => $fromEmail,
            'MIME-Version' => '1.0',
            'Content-Type' => ($options['html'] ?? true) ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8',
            'X-Mailer' => 'WAPOS Notification System'
        ];

        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= "$key: $value\r\n";
        }

        $sent = @mail($to, $subject, $body, $headerString);
        
        return [
            'success' => $sent,
            'message' => $sent ? 'Email sent successfully' : 'Failed to send email'
        ];
    }

    /**
     * Send email using PHPMailer
     */
    private function sendWithPHPMailer(string $to, string $subject, string $body, array $options, array $smtp): array
    {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host = $smtp['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtp['username'];
            $mail->Password = $smtp['password'];
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtp['port'];

            $mail->setFrom($smtp['from_email'], $smtp['from_name']);
            $mail->addAddress($to, $options['recipient_name'] ?? '');

            if (!empty($options['reply_to'])) {
                $mail->addReplyTo($options['reply_to']);
            }

            if (!empty($options['cc'])) {
                foreach ((array)$options['cc'] as $cc) {
                    $mail->addCC($cc);
                }
            }

            if (!empty($options['attachments'])) {
                foreach ($options['attachments'] as $attachment) {
                    $mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
                }
            }

            $mail->isHTML($options['html'] ?? true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            if (($options['html'] ?? true) && !empty($options['alt_body'])) {
                $mail->AltBody = $options['alt_body'];
            }

            $mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];

        } catch (\PHPMailer\PHPMailer\Exception $e) {
            return ['success' => false, 'message' => 'PHPMailer Error: ' . $e->getMessage()];
        }
    }

    /**
     * Send SMS via configured provider
     */
    private function sendSMS(string $phone, string $message, array $options = []): array
    {
        $provider = $this->settings['sms_provider'] ?? 'africastalking';
        $apiKey = $this->settings['sms_api_key'] ?? '';
        $apiSecret = $this->settings['sms_api_secret'] ?? '';
        $senderId = $this->settings['sms_sender_id'] ?? 'WAPOS';

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'SMS provider not configured'];
        }

        // Normalize phone number
        $phone = $this->normalizePhoneNumber($phone);

        switch ($provider) {
            case 'africastalking':
                return $this->sendAfricasTalkingSMS($phone, $message, $apiKey, $apiSecret, $senderId);
            case 'twilio':
                return $this->sendTwilioSMS($phone, $message, $apiKey, $apiSecret, $senderId);
            case 'nexmo':
            case 'vonage':
                return $this->sendVonageSMS($phone, $message, $apiKey, $apiSecret, $senderId);
            case 'egosms':
                return $this->sendEgoSMS($phone, $message, $apiKey, $apiSecret, $senderId);
            case 'leopard':
                return $this->sendLeopardSMS($phone, $message, $apiKey, $apiSecret, $senderId);
            default:
                return ['success' => false, 'message' => "Unknown SMS provider: $provider"];
        }
    }

    /**
     * Send SMS via Africa's Talking
     */
    private function sendAfricasTalkingSMS(string $phone, string $message, string $apiKey, string $username, string $senderId): array
    {
        $url = 'https://api.africastalking.com/version1/messaging';
        
        $data = [
            'username' => $username,
            'to' => $phone,
            'message' => $message,
            'from' => $senderId
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apiKey: ' . $apiKey,
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 201) {
            $result = json_decode($response, true);
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'provider_response' => $result
            ];
        }

        return ['success' => false, 'message' => "SMS failed: HTTP $httpCode - $response"];
    }

    /**
     * Send SMS via Twilio
     */
    private function sendTwilioSMS(string $phone, string $message, string $accountSid, string $authToken, string $fromNumber): array
    {
        $url = "https://api.twilio.com/2010-04-01/Accounts/$accountSid/Messages.json";
        
        $data = [
            'To' => $phone,
            'From' => $fromNumber,
            'Body' => $message
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "$accountSid:$authToken",
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 201) {
            return ['success' => true, 'message' => 'SMS sent via Twilio'];
        }

        return ['success' => false, 'message' => "Twilio SMS failed: $response"];
    }

    /**
     * Send SMS via Vonage (Nexmo)
     */
    private function sendVonageSMS(string $phone, string $message, string $apiKey, string $apiSecret, string $senderId): array
    {
        $url = 'https://rest.nexmo.com/sms/json';
        
        $data = [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'to' => $phone,
            'from' => $senderId,
            'text' => $message
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        if (isset($result['messages'][0]['status']) && $result['messages'][0]['status'] === '0') {
            return ['success' => true, 'message' => 'SMS sent via Vonage'];
        }

        return ['success' => false, 'message' => 'Vonage SMS failed: ' . ($result['messages'][0]['error-text'] ?? 'Unknown error')];
    }

    /**
     * Send SMS via EgoSMS (Uganda)
     * https://www.egosms.co/
     */
    private function sendEgoSMS(string $phone, string $message, string $username, string $password, string $senderId): array
    {
        $url = 'https://www.egosms.co/api/v1/json/';
        
        // EgoSMS expects phone without + prefix
        $phone = ltrim($phone, '+');
        
        $data = [
            'method' => 'SendSms',
            'userdata' => [
                'username' => $username,
                'password' => $password
            ],
            'msgdata' => [
                [
                    'number' => $phone,
                    'message' => $message,
                    'senderid' => $senderId
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        
        if (isset($result['Status']) && strtolower($result['Status']) === 'ok') {
            return ['success' => true, 'message' => 'SMS sent via EgoSMS', 'provider_response' => $result];
        }

        return ['success' => false, 'message' => 'EgoSMS failed: ' . ($result['Message'] ?? $response)];
    }

    /**
     * Send SMS via SMSLeopard (Kenya)
     * https://smsleopard.com/
     * API Docs: https://developers.smsleopard.com/
     */
    private function sendLeopardSMS(string $phone, string $message, string $apiKey, string $apiSecret, string $senderId): array
    {
        $url = 'https://api.smsleopard.com/v1/sms/send';
        
        // SMSLeopard expects phone with country code (can include +)
        $phone = $this->normalizePhoneNumber($phone, true);
        
        $data = [
            'source' => $senderId,
            'message' => $message,
            'destination' => [
                ['number' => $phone]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "$apiKey:$apiSecret", // Basic Auth
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'message' => "SMSLeopard connection error: $curlError"];
        }

        $result = json_decode($response, true);
        
        // SMSLeopard returns 200 on success with recipients array
        if ($httpCode === 200 && isset($result['recipients'])) {
            $recipient = $result['recipients'][0] ?? [];
            if (isset($recipient['status']) && $recipient['status'] === 'Success') {
                return ['success' => true, 'message' => 'SMS sent via SMSLeopard', 'provider_response' => $result];
            }
        }

        $errorMsg = $result['error'] ?? $result['message'] ?? $response;
        return ['success' => false, 'message' => "SMSLeopard failed: $errorMsg"];
    }

    /**
     * Send WhatsApp message
     */
    private function sendWhatsApp(string $phone, string $message, array $options = []): array
    {
        $provider = $this->settings['whatsapp_provider'] ?? 'meta';
        
        switch ($provider) {
            case 'aisensy':
                return $this->sendAiSensyWhatsApp($phone, $message, $options);
            case 'meta':
            default:
                return $this->sendMetaWhatsApp($phone, $message, $options);
        }
    }

    /**
     * Send WhatsApp via Meta Business API (Direct)
     */
    private function sendMetaWhatsApp(string $phone, string $message, array $options = []): array
    {
        $accessToken = $this->settings['whatsapp_access_token'] ?? '';
        $phoneNumberId = $this->settings['whatsapp_phone_number_id'] ?? '';

        if (empty($accessToken) || empty($phoneNumberId)) {
            return ['success' => false, 'message' => 'Meta WhatsApp not configured'];
        }

        $phone = $this->normalizePhoneNumber($phone, false);
        $url = "https://graph.facebook.com/v17.0/$phoneNumberId/messages";

        // Check if using template or text message
        if (!empty($options['template'])) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'template' => [
                    'name' => $options['template'],
                    'language' => ['code' => $options['language'] ?? 'en'],
                    'components' => $options['template_components'] ?? []
                ]
            ];
        } else {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['body' => $message]
            ];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return ['success' => true, 'message' => 'WhatsApp message sent via Meta'];
        }

        return ['success' => false, 'message' => "Meta WhatsApp failed: $response"];
    }

    /**
     * Send WhatsApp via AiSensy
     * https://aisensy.com/
     * API Docs: https://wiki.aisensy.com/en/articles/11501889-api-reference-docs
     */
    private function sendAiSensyWhatsApp(string $phone, string $message, array $options = []): array
    {
        $apiKey = $this->settings['aisensy_api_key'] ?? '';
        $campaignName = $options['campaign'] ?? ($this->settings['aisensy_default_campaign'] ?? '');

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'AiSensy API key not configured'];
        }

        if (empty($campaignName)) {
            return ['success' => false, 'message' => 'AiSensy campaign name not configured'];
        }

        $phone = $this->normalizePhoneNumber($phone, true);
        $url = 'https://backend.aisensy.com/campaign/t1/api/v2';

        $payload = [
            'apiKey' => $apiKey,
            'campaignName' => $campaignName,
            'destination' => $phone,
            'userName' => $options['user_name'] ?? ($options['recipient_name'] ?? 'Customer'),
            'source' => $options['source'] ?? 'WAPOS'
        ];

        // Add template parameters if provided
        if (!empty($options['template_params'])) {
            $payload['templateParams'] = (array)$options['template_params'];
        }

        // Add media if provided
        if (!empty($options['media_url'])) {
            $payload['media'] = [
                'url' => $options['media_url'],
                'filename' => $options['media_filename'] ?? 'file'
            ];
        }

        // Add tags if provided
        if (!empty($options['tags'])) {
            $payload['tags'] = (array)$options['tags'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'message' => "AiSensy connection error: $curlError"];
        }

        $result = json_decode($response, true);

        // AiSensy returns success status
        if ($httpCode === 200 && isset($result['status']) && strtolower($result['status']) === 'success') {
            return ['success' => true, 'message' => 'WhatsApp sent via AiSensy', 'provider_response' => $result];
        }

        $errorMsg = $result['message'] ?? $result['error'] ?? $response;
        return ['success' => false, 'message' => "AiSensy failed: $errorMsg"];
    }

    /**
     * Normalize phone number to international format
     */
    private function normalizePhoneNumber(string $phone, bool $includePlus = true): string
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // If starts with 0, assume local number - add country code
        if (str_starts_with($phone, '0')) {
            $countryCode = $this->settings['default_country_code'] ?? '254'; // Kenya default
            $phone = $countryCode . substr($phone, 1);
        }
        
        // Remove leading + if present
        $phone = ltrim($phone, '+');
        
        return $includePlus ? '+' . $phone : $phone;
    }

    /**
     * Log notification to database
     */
    private function logNotification(string $channel, string $recipient, string $subject, string $message, array $options): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO notification_logs 
            (channel, recipient, subject, message, notification_type, customer_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            $channel,
            $recipient,
            $subject,
            $message,
            $options['type'] ?? self::TYPE_TRANSACTIONAL,
            $options['customer_id'] ?? null
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update notification log with result
     */
    private function updateNotificationLog(int $id, bool $success, string $response): void
    {
        $stmt = $this->db->prepare("
            UPDATE notification_logs 
            SET status = ?, response = ?, sent_at = IF(? = 1, NOW(), NULL)
            WHERE id = ?
        ");
        $stmt->execute([
            $success ? 'sent' : 'failed',
            $response,
            $success ? 1 : 0,
            $id
        ]);
    }

    // ==========================================
    // CONVENIENCE METHODS FOR COMMON USE CASES
    // ==========================================

    /**
     * Send sale receipt to customer
     */
    public function sendReceipt(int $saleId, ?string $channel = null): array
    {
        $sale = $this->getSaleWithCustomer($saleId);
        if (!$sale) {
            return ['success' => false, 'message' => 'Sale not found'];
        }

        if (!$sale['customer_email'] && !$sale['customer_phone']) {
            return ['success' => false, 'message' => 'Customer has no contact info'];
        }

        $channel = $channel ?? ($sale['customer_email'] ? self::CHANNEL_EMAIL : self::CHANNEL_SMS);
        $recipient = $channel === self::CHANNEL_EMAIL ? $sale['customer_email'] : $sale['customer_phone'];

        $subject = "Receipt for your purchase - #{$sale['sale_number']}";
        $message = $this->buildReceiptMessage($sale, $channel);

        return $this->send($channel, $recipient, $subject, $message, [
            'type' => self::TYPE_TRANSACTIONAL,
            'customer_id' => $sale['customer_id'],
            'html' => $channel === self::CHANNEL_EMAIL
        ]);
    }

    /**
     * Send thank you / appreciation message
     */
    public function sendThankYou(int $customerId, ?string $channel = null): array
    {
        $customer = $this->getCustomer($customerId);
        if (!$customer) {
            return ['success' => false, 'message' => 'Customer not found'];
        }

        $channel = $channel ?? ($customer['email'] ? self::CHANNEL_EMAIL : self::CHANNEL_SMS);
        $recipient = $channel === self::CHANNEL_EMAIL ? $customer['email'] : $customer['phone'];

        if (!$recipient) {
            return ['success' => false, 'message' => 'Customer has no contact info for this channel'];
        }

        $businessName = $this->settings['business_name'] ?? 'Our Store';
        $subject = "Thank you for shopping with $businessName!";
        $message = $this->buildThankYouMessage($customer, $channel);

        return $this->send($channel, $recipient, $subject, $message, [
            'type' => self::TYPE_APPRECIATION,
            'customer_id' => $customerId,
            'html' => $channel === self::CHANNEL_EMAIL
        ]);
    }

    /**
     * Send promotional message to customer segment
     */
    public function sendPromotion(array $customerIds, string $subject, string $message, string $channel = self::CHANNEL_EMAIL): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];

        foreach ($customerIds as $customerId) {
            $customer = $this->getCustomer($customerId);
            if (!$customer) continue;

            $recipient = $channel === self::CHANNEL_EMAIL ? $customer['email'] : $customer['phone'];
            if (!$recipient) continue;

            // Personalize message
            $personalizedMessage = $this->personalizeMessage($message, $customer);

            $result = $this->send($channel, $recipient, $subject, $personalizedMessage, [
                'type' => self::TYPE_MARKETING,
                'customer_id' => $customerId,
                'html' => $channel === self::CHANNEL_EMAIL
            ]);

            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = ['customer_id' => $customerId, 'error' => $result['message']];
            }

            // Rate limiting - small delay between sends
            usleep(100000); // 100ms
        }

        return $results;
    }

    /**
     * Send birthday wishes
     */
    public function sendBirthdayWishes(int $customerId): array
    {
        $customer = $this->getCustomer($customerId);
        if (!$customer) {
            return ['success' => false, 'message' => 'Customer not found'];
        }

        $channel = $customer['email'] ? self::CHANNEL_EMAIL : self::CHANNEL_SMS;
        $recipient = $customer['email'] ?: $customer['phone'];

        if (!$recipient) {
            return ['success' => false, 'message' => 'Customer has no contact info'];
        }

        $businessName = $this->settings['business_name'] ?? 'Our Store';
        $subject = "Happy Birthday, {$customer['name']}! 🎂";
        $message = $this->buildBirthdayMessage($customer, $channel);

        return $this->send($channel, $recipient, $subject, $message, [
            'type' => self::TYPE_APPRECIATION,
            'customer_id' => $customerId,
            'html' => $channel === self::CHANNEL_EMAIL
        ]);
    }

    /**
     * Send low stock alert to admin
     */
    public function sendLowStockAlert(array $products): array
    {
        $adminEmail = $this->settings['notification_admin_email'] ?? '';
        if (!$adminEmail) {
            return ['success' => false, 'message' => 'Admin email not configured'];
        }

        $subject = 'Low Stock Alert - Action Required';
        $message = $this->buildLowStockMessage($products);

        return $this->send(self::CHANNEL_EMAIL, $adminEmail, $subject, $message, [
            'type' => self::TYPE_ALERT,
            'html' => true
        ]);
    }

    /**
     * Send daily sales summary
     */
    public function sendDailySummary(?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        $adminEmail = $this->settings['notification_admin_email'] ?? '';
        
        if (!$adminEmail) {
            return ['success' => false, 'message' => 'Admin email not configured'];
        }

        $summary = $this->getDailySummary($date);
        $subject = "Daily Sales Summary - $date";
        $message = $this->buildDailySummaryMessage($summary, $date);

        return $this->send(self::CHANNEL_EMAIL, $adminEmail, $subject, $message, [
            'type' => self::TYPE_ALERT,
            'html' => true
        ]);
    }

    // ==========================================
    // MESSAGE BUILDERS
    // ==========================================

    private function buildReceiptMessage(array $sale, string $channel): string
    {
        $businessName = $this->settings['business_name'] ?? 'WAPOS';
        $currencySymbol = $this->settings['currency_symbol'] ?? '';

        if ($channel === self::CHANNEL_EMAIL) {
            return $this->getEmailTemplate('receipt', [
                'business_name' => $businessName,
                'sale' => $sale,
                'currency' => $currencySymbol
            ]);
        }

        // SMS format
        return "Thank you for your purchase at $businessName! " .
               "Receipt #{$sale['sale_number']} - Total: $currencySymbol" . number_format($sale['total_amount'], 2);
    }

    private function buildThankYouMessage(array $customer, string $channel): string
    {
        $businessName = $this->settings['business_name'] ?? 'Our Store';
        $name = $customer['name'] ?? 'Valued Customer';

        if ($channel === self::CHANNEL_EMAIL) {
            return $this->getEmailTemplate('thank_you', [
                'business_name' => $businessName,
                'customer_name' => $name
            ]);
        }

        return "Dear $name, thank you for choosing $businessName! We appreciate your business and look forward to serving you again.";
    }

    private function buildBirthdayMessage(array $customer, string $channel): string
    {
        $businessName = $this->settings['business_name'] ?? 'Our Store';
        $name = $customer['name'] ?? 'Valued Customer';

        if ($channel === self::CHANNEL_EMAIL) {
            return $this->getEmailTemplate('birthday', [
                'business_name' => $businessName,
                'customer_name' => $name
            ]);
        }

        return "Happy Birthday, $name! 🎂 Wishing you a wonderful day from all of us at $businessName. Visit us today for a special birthday treat!";
    }

    private function buildLowStockMessage(array $products): string
    {
        $html = '<h2>Low Stock Alert</h2>';
        $html .= '<p>The following products are running low on stock:</p>';
        $html .= '<table border="1" cellpadding="8" style="border-collapse: collapse;">';
        $html .= '<tr><th>Product</th><th>Current Stock</th><th>Min Level</th></tr>';
        
        foreach ($products as $product) {
            $html .= "<tr><td>{$product['name']}</td><td>{$product['stock_quantity']}</td><td>{$product['min_stock_level']}</td></tr>";
        }
        
        $html .= '</table>';
        $html .= '<p>Please reorder these items soon.</p>';
        
        return $html;
    }

    private function buildDailySummaryMessage(array $summary, string $date): string
    {
        $currencySymbol = $this->settings['currency_symbol'] ?? '';
        
        return $this->getEmailTemplate('daily_summary', [
            'date' => $date,
            'summary' => $summary,
            'currency' => $currencySymbol
        ]);
    }

    private function personalizeMessage(string $message, array $customer): string
    {
        $replacements = [
            '{{name}}' => $customer['name'] ?? 'Valued Customer',
            '{{first_name}}' => explode(' ', $customer['name'] ?? '')[0] ?? 'Customer',
            '{{email}}' => $customer['email'] ?? '',
            '{{phone}}' => $customer['phone'] ?? '',
            '{{loyalty_points}}' => $customer['loyalty_points'] ?? 0
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    private function getEmailTemplate(string $template, array $data): string
    {
        $businessName = $data['business_name'] ?? ($this->settings['business_name'] ?? 'WAPOS');
        $currencySymbol = $data['currency'] ?? ($this->settings['currency_symbol'] ?? '');

        $header = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #2563eb; color: white; padding: 20px; text-align: center;'>
                <h1 style='margin: 0;'>$businessName</h1>
            </div>
            <div style='padding: 20px; background: #f9fafb;'>
        ";

        $footer = "
            </div>
            <div style='background: #374151; color: #9ca3af; padding: 15px; text-align: center; font-size: 12px;'>
                <p style='margin: 0;'>© " . date('Y') . " $businessName. All rights reserved.</p>
                <p style='margin: 5px 0 0;'>This is an automated message. Please do not reply directly.</p>
            </div>
        </div>
        ";

        switch ($template) {
            case 'receipt':
                $sale = $data['sale'];
                $content = "
                    <h2>Thank you for your purchase!</h2>
                    <p>Receipt #: <strong>{$sale['sale_number']}</strong></p>
                    <p>Date: " . date('M j, Y g:i A', strtotime($sale['created_at'])) . "</p>
                    <hr>
                    <p><strong>Total: $currencySymbol" . number_format($sale['total_amount'], 2) . "</strong></p>
                    <p>Payment: " . ucfirst($sale['payment_method']) . "</p>
                    <hr>
                    <p>Thank you for shopping with us!</p>
                ";
                break;

            case 'thank_you':
                $content = "
                    <h2>Thank You, {$data['customer_name']}!</h2>
                    <p>We truly appreciate your business and trust in us.</p>
                    <p>Your satisfaction is our top priority, and we're committed to providing you with the best products and service.</p>
                    <p>We look forward to serving you again soon!</p>
                    <p style='margin-top: 20px;'>Warm regards,<br><strong>The $businessName Team</strong></p>
                ";
                break;

            case 'birthday':
                $content = "
                    <h2 style='color: #dc2626;'>🎂 Happy Birthday, {$data['customer_name']}! 🎉</h2>
                    <p>Wishing you a fantastic birthday filled with joy and happiness!</p>
                    <p>As a token of our appreciation, we'd love to offer you a special birthday treat. Visit us today!</p>
                    <p style='margin-top: 20px;'>Best wishes,<br><strong>The $businessName Team</strong></p>
                ";
                break;

            case 'daily_summary':
                $s = $data['summary'];
                $content = "
                    <h2>Daily Sales Summary - {$data['date']}</h2>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #e5e7eb;'>Total Sales</td>
                            <td style='padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: right;'><strong>{$s['transaction_count']}</strong></td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #e5e7eb;'>Revenue</td>
                            <td style='padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: right;'><strong>$currencySymbol" . number_format($s['total_revenue'], 2) . "</strong></td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #e5e7eb;'>Avg Ticket</td>
                            <td style='padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: right;'>$currencySymbol" . number_format($s['avg_ticket'], 2) . "</td></tr>
                        <tr><td style='padding: 8px;'>Top Payment</td>
                            <td style='padding: 8px; text-align: right;'>" . ucfirst($s['top_payment_method'] ?? 'N/A') . "</td></tr>
                    </table>
                ";
                break;

            default:
                $content = "<p>" . ($data['message'] ?? '') . "</p>";
        }

        return $header . $content . $footer;
    }

    // ==========================================
    // DATA HELPERS
    // ==========================================

    private function getSaleWithCustomer(int $saleId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.id = ?
        ");
        $stmt->execute([$saleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getCustomer(int $customerId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getDailySummary(string $date): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as transaction_count,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(AVG(total_amount), 0) as avg_ticket,
                (SELECT payment_method FROM sales WHERE DATE(created_at) = ? GROUP BY payment_method ORDER BY COUNT(*) DESC LIMIT 1) as top_payment_method
            FROM sales
            WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$date, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get customers with birthdays today
     */
    public function getTodaysBirthdays(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM customers 
            WHERE DATE_FORMAT(date_of_birth, '%m-%d') = DATE_FORMAT(NOW(), '%m-%d')
            AND is_active = 1
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get notification statistics
     */
    public function getStats(string $period = 'today'): array
    {
        $dateFilter = match($period) {
            'today' => "DATE(created_at) = CURDATE()",
            'week' => "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => "1=1"
        };

        $stmt = $this->db->query("
            SELECT 
                channel,
                status,
                COUNT(*) as count
            FROM notification_logs
            WHERE $dateFilter
            GROUP BY channel, status
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
