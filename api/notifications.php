<?php
/**
 * Notifications API
 * Handle email, SMS, and WhatsApp notifications
 */

require_once '../includes/bootstrap.php';

use App\Services\NotificationService;

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$notificationService = new NotificationService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }

    // Verify CSRF token
    if (!isset($data['csrf_token']) || !verifyCSRFToken($data['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $action = $data['action'] ?? '';
    $userId = $_SESSION['user_id'] ?? 0;

    try {
        switch ($action) {
            case 'send':
                $result = handleSendNotification($notificationService, $data, $db);
                break;

            case 'send_birthday_wishes':
                $result = handleBirthdayWishes($notificationService);
                break;

            case 'send_thank_you':
                $result = handleThankYou($notificationService, $data);
                break;

            case 'send_daily_summary':
                $result = handleDailySummary($notificationService);
                break;

            case 'send_low_stock_alert':
                $result = handleLowStockAlert($notificationService, $db);
                break;

            case 'send_receipt':
                $result = handleSendReceipt($notificationService, $data);
                break;

            case 'save_campaign_draft':
                $result = handleSaveCampaign($db, $data, $userId, 'draft');
                break;

            case 'launch_campaign':
                $result = handleLaunchCampaign($db, $notificationService, $data, $userId);
                break;

            case 'test_connection':
                $result = handleTestConnection($data);
                break;

            default:
                $result = ['success' => false, 'message' => 'Unknown action'];
        }

        echo json_encode($result);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'stats':
            $period = $_GET['period'] ?? 'today';
            echo json_encode(['success' => true, 'stats' => $notificationService->getStats($period)]);
            break;

        case 'logs':
            $limit = min((int)($_GET['limit'] ?? 50), 200);
            $offset = (int)($_GET['offset'] ?? 0);
            $channel = $_GET['channel'] ?? '';
            
            $sql = "SELECT nl.*, c.name as customer_name FROM notification_logs nl 
                    LEFT JOIN customers c ON nl.customer_id = c.id WHERE 1=1";
            $params = [];
            
            if ($channel) {
                $sql .= " AND nl.channel = ?";
                $params[] = $channel;
            }
            
            $sql .= " ORDER BY nl.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $logs = $db->fetchAll($sql, $params);
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;

        case 'templates':
            $type = $_GET['type'] ?? 'email';
            $table = $type === 'sms' ? 'sms_templates' : 'email_templates';
            $templates = $db->fetchAll("SELECT * FROM $table WHERE is_active = 1 ORDER BY name");
            echo json_encode(['success' => true, 'templates' => $templates]);
            break;

        case 'campaigns':
            $status = $_GET['status'] ?? '';
            $sql = "SELECT mc.*, u.full_name as creator_name FROM marketing_campaigns mc 
                    LEFT JOIN users u ON mc.created_by = u.id WHERE 1=1";
            $params = [];
            
            if ($status) {
                $sql .= " AND mc.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY mc.created_at DESC LIMIT 50";
            $campaigns = $db->fetchAll($sql, $params);
            echo json_encode(['success' => true, 'campaigns' => $campaigns]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    exit;
}

// ==========================================
// HANDLER FUNCTIONS
// ==========================================

function handleSendNotification(NotificationService $service, array $data, $db): array
{
    $channel = $data['channel'] ?? 'email';
    $recipientType = $data['recipient_type'] ?? 'single';
    $subject = $data['subject'] ?? '';
    $message = $data['message'] ?? '';

    if (empty($message)) {
        return ['success' => false, 'message' => 'Message is required'];
    }

    $results = ['sent' => 0, 'failed' => 0];

    if ($recipientType === 'single') {
        $recipient = $data['recipient'] ?? '';
        if (empty($recipient)) {
            return ['success' => false, 'message' => 'Recipient is required'];
        }
        
        $result = $service->send($channel, $recipient, $subject, $message, [
            'type' => $data['notification_type'] ?? 'transactional',
            'html' => $channel === 'email'
        ]);
        
        return $result;

    } elseif ($recipientType === 'customer') {
        $customerId = (int)($data['customer_id'] ?? 0);
        if (!$customerId) {
            return ['success' => false, 'message' => 'Customer is required'];
        }
        
        $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
        if (!$customer) {
            return ['success' => false, 'message' => 'Customer not found'];
        }
        
        $recipient = $channel === 'email' ? $customer['email'] : $customer['phone'];
        if (!$recipient) {
            return ['success' => false, 'message' => "Customer has no $channel contact"];
        }
        
        return $service->send($channel, $recipient, $subject, $message, [
            'type' => $data['notification_type'] ?? 'transactional',
            'customer_id' => $customerId,
            'html' => $channel === 'email'
        ]);

    } elseif ($recipientType === 'segment') {
        $segment = $data['segment'] ?? 'all';
        $customers = getCustomersBySegment($db, $segment, $channel);
        
        if (empty($customers)) {
            return ['success' => false, 'message' => 'No customers found in this segment'];
        }
        
        $customerIds = array_column($customers, 'id');
        return $service->sendPromotion($customerIds, $subject, $message, $channel);
    }

    return ['success' => false, 'message' => 'Invalid recipient type'];
}

function handleBirthdayWishes(NotificationService $service): array
{
    $birthdays = $service->getTodaysBirthdays();
    
    if (empty($birthdays)) {
        return ['success' => true, 'message' => 'No birthdays today'];
    }

    $sent = 0;
    $failed = 0;

    foreach ($birthdays as $customer) {
        $result = $service->sendBirthdayWishes($customer['id']);
        if ($result['success']) {
            $sent++;
        } else {
            $failed++;
        }
    }

    return [
        'success' => true,
        'message' => "Birthday wishes sent: $sent successful, $failed failed"
    ];
}

function handleThankYou(NotificationService $service, array $data): array
{
    $customerId = (int)($data['customer_id'] ?? 0);
    if (!$customerId) {
        return ['success' => false, 'message' => 'Customer ID is required'];
    }

    return $service->sendThankYou($customerId);
}

function handleDailySummary(NotificationService $service): array
{
    return $service->sendDailySummary();
}

function handleLowStockAlert(NotificationService $service, $db): array
{
    $lowStock = $db->fetchAll("
        SELECT name, stock_quantity, min_stock_level 
        FROM products 
        WHERE stock_quantity <= min_stock_level AND is_active = 1
        ORDER BY stock_quantity ASC
        LIMIT 20
    ");

    if (empty($lowStock)) {
        return ['success' => true, 'message' => 'No low stock items found'];
    }

    return $service->sendLowStockAlert($lowStock);
}

function handleSendReceipt(NotificationService $service, array $data): array
{
    $saleId = (int)($data['sale_id'] ?? 0);
    if (!$saleId) {
        return ['success' => false, 'message' => 'Sale ID is required'];
    }

    $channel = $data['channel'] ?? null;
    return $service->sendReceipt($saleId, $channel);
}

function handleSaveCampaign($db, array $data, int $userId, string $status): array
{
    $name = $data['name'] ?? '';
    $channel = $data['channel'] ?? 'email';
    $content = $data['content'] ?? '';

    if (empty($name) || empty($content)) {
        return ['success' => false, 'message' => 'Name and content are required'];
    }

    $stmt = $db->getConnection()->prepare("
        INSERT INTO marketing_campaigns 
        (name, description, channel, campaign_type, subject, content, target_segment, scheduled_at, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $name,
        $data['description'] ?? null,
        $channel,
        $data['campaign_type'] ?? 'promotional',
        $data['subject'] ?? null,
        $content,
        $data['target_segment'] ?? 'all',
        $data['scheduled_at'] ?: null,
        $status,
        $userId
    ]);

    return ['success' => true, 'message' => 'Campaign saved', 'campaign_id' => $db->getConnection()->lastInsertId()];
}

function handleLaunchCampaign($db, NotificationService $service, array $data, int $userId): array
{
    // First save the campaign
    $saveResult = handleSaveCampaign($db, $data, $userId, 'running');
    if (!$saveResult['success']) {
        return $saveResult;
    }

    $campaignId = $saveResult['campaign_id'];
    $channel = $data['channel'] ?? 'email';
    $segment = $data['target_segment'] ?? 'all';
    $subject = $data['subject'] ?? '';
    $content = $data['content'] ?? '';

    // Get target customers
    $customers = getCustomersBySegment($db, $segment, $channel);
    
    if (empty($customers)) {
        $db->getConnection()->exec("UPDATE marketing_campaigns SET status = 'completed', completed_at = NOW() WHERE id = $campaignId");
        return ['success' => true, 'message' => 'Campaign completed - no recipients found'];
    }

    // Update recipient count
    $db->getConnection()->exec("UPDATE marketing_campaigns SET total_recipients = " . count($customers) . ", started_at = NOW() WHERE id = $campaignId");

    // Send to all customers
    $sent = 0;
    $failed = 0;

    foreach ($customers as $customer) {
        $recipient = $channel === 'email' ? $customer['email'] : $customer['phone'];
        if (!$recipient) continue;

        $result = $service->send($channel, $recipient, $subject, $content, [
            'type' => 'marketing',
            'customer_id' => $customer['id'],
            'html' => $channel === 'email'
        ]);

        // Log recipient
        $db->getConnection()->prepare("
            INSERT INTO campaign_recipients (campaign_id, customer_id, recipient, channel, status, sent_at, error_message)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $campaignId,
            $customer['id'],
            $recipient,
            $channel,
            $result['success'] ? 'sent' : 'failed',
            $result['success'] ? date('Y-m-d H:i:s') : null,
            $result['success'] ? null : $result['message']
        ]);

        if ($result['success']) {
            $sent++;
        } else {
            $failed++;
        }

        usleep(100000); // 100ms delay between sends
    }

    // Update campaign stats
    $db->getConnection()->exec("
        UPDATE marketing_campaigns 
        SET sent_count = $sent, failed_count = $failed, status = 'completed', completed_at = NOW() 
        WHERE id = $campaignId
    ");

    return [
        'success' => true,
        'message' => "Campaign completed: $sent sent, $failed failed",
        'campaign_id' => $campaignId
    ];
}

function handleTestConnection(array $data): array
{
    $channel = $data['channel'] ?? 'email';
    $testRecipient = $data['test_recipient'] ?? '';

    if (empty($testRecipient)) {
        return ['success' => false, 'message' => 'Test recipient is required'];
    }

    // This would test the actual connection
    // For now, just validate the configuration exists
    $db = Database::getInstance();
    
    if ($channel === 'email') {
        $host = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'smtp_host'");
        if (empty($host['setting_value'])) {
            return ['success' => false, 'message' => 'SMTP not configured'];
        }
    } elseif ($channel === 'sms') {
        $apiKey = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'sms_api_key'");
        if (empty($apiKey['setting_value'])) {
            return ['success' => false, 'message' => 'SMS provider not configured'];
        }
    }

    return ['success' => true, 'message' => 'Configuration looks valid. Send a test message to verify.'];
}

function getCustomersBySegment($db, string $segment, string $channel): array
{
    $contactField = $channel === 'email' ? 'email' : 'phone';
    
    $baseQuery = "SELECT c.* FROM customers c 
                  LEFT JOIN customer_preferences cp ON c.id = cp.customer_id
                  WHERE c.is_active = 1 
                  AND c.$contactField IS NOT NULL 
                  AND c.$contactField != ''
                  AND (cp.id IS NULL OR cp.{$channel}_marketing = 1)";

    switch ($segment) {
        case 'active':
            $query = "$baseQuery AND c.id IN (
                SELECT DISTINCT customer_id FROM sales 
                WHERE customer_id IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            )";
            break;

        case 'inactive':
            $query = "$baseQuery AND c.id NOT IN (
                SELECT DISTINCT customer_id FROM sales 
                WHERE customer_id IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            )";
            break;

        case 'high_value':
            $query = "$baseQuery AND c.id IN (
                SELECT customer_id FROM sales 
                WHERE customer_id IS NOT NULL 
                GROUP BY customer_id 
                ORDER BY SUM(total_amount) DESC 
                LIMIT 100
            )";
            break;

        case 'new':
            $query = "$baseQuery AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;

        case 'birthday':
            $query = "$baseQuery AND DATE_FORMAT(c.date_of_birth, '%m') = DATE_FORMAT(NOW(), '%m')";
            break;

        default: // 'all'
            $query = $baseQuery;
    }

    $query .= " LIMIT 1000"; // Safety limit

    return $db->fetchAll($query) ?: [];
}
