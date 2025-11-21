<?php
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

$auth->requireLogin();
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

enforceCsrf($payload['csrf_token'] ?? '');

enforceVoidPermission($auth);

enforceJsonAction($payload);

$action = $payload['action'];

try {
    switch ($action) {
        case 'fetch_void_context':
            $response = fetchVoidContext($db, $payload, $auth);
            break;
        case 'get_void_lists':
            $lists = fetchVoidLists($db, $auth);
            $response = array_merge(['success' => true], $lists);
            break;
        case 'void_order':
            $response = processVoidRequest($db, $payload, $auth);
            break;
        default:
            throw new Exception('Unsupported action');
    }

    http_response_code(200);
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function enforceCsrf(string $token): void
{
    if (!validateCSRFToken($token)) {
        throw new Exception('Invalid CSRF token');
    }
}

function enforceVoidPermission(Auth $auth): void
{
    $user = $auth->getUser();
    $role = strtolower($user['role'] ?? '');

    if (!in_array($role, ['admin', 'manager', 'developer'], true)) {
        throw new Exception('You do not have permission to void orders');
    }
}

function enforceJsonAction(array $payload): void
{
    if (empty($payload['action'])) {
        throw new Exception('Missing action');
    }
}

function fetchVoidContext(Database $db, array $payload, Auth $auth): array
{
    $orderId = (int)($payload['order_id'] ?? 0);
    if ($orderId <= 0) {
        throw new Exception('Invalid order identifier');
    }

    $order = $db->fetchOne(
        "SELECT o.*, TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) AS age_minutes,
                u.username AS created_by_username, u.full_name AS created_by_full_name
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         WHERE o.id = ?",
        [$orderId]
    );

    if (!$order) {
        throw new Exception('Order not found');
    }

    if ($order['status'] === 'voided') {
        throw new Exception('Order already voided');
    }

    if ($order['status'] === 'completed') {
        throw new Exception('Completed orders cannot be voided');
    }

    $items = $db->fetchAll(
        "SELECT oi.*, p.sku, p.name AS product_name
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?
         ORDER BY oi.id",
        [$orderId]
    );

    try {
        $payments = $db->fetchAll(
            "SELECT op.*, u.username AS recorded_by
             FROM order_payments op
             LEFT JOIN users u ON u.id = op.recorded_by_user_id
             WHERE op.order_id = ?
             ORDER BY op.created_at DESC",
            [$orderId]
        );
    } catch (Exception $e) {
        $payments = [];
    }

    $reasonCodes = $db->fetchAll(
        "SELECT id, code, display_name, description, requires_manager_approval, affects_inventory
         FROM void_reason_codes
         WHERE is_active = 1
         ORDER BY display_order, display_name"
    );

    $voidSettings = fetchVoidSettings($db);
    $policy = evaluatePolicies($order, $voidSettings, $db, $auth);

    return [
        'success' => true,
        'data' => [
            'order' => $order,
            'items' => $items,
            'payments' => $payments,
            'reason_codes' => $reasonCodes,
            'policy' => $policy,
        ],
    ];
}

function processVoidRequest(Database $db, array $payload, Auth $auth): array
{
    $orderId = (int)($payload['order_id'] ?? 0);
    $reasonCode = sanitizeInput($payload['reason_code'] ?? '');
    $reasonText = sanitizeInput($payload['reason_text'] ?? '');
    $managerUserId = isset($payload['manager_user_id']) ? (int)$payload['manager_user_id'] : null;
    $managerPin = sanitizeInput($payload['manager_pin'] ?? '');

    if ($orderId <= 0 || !$reasonCode) {
        throw new Exception('Order ID and reason code are required');
    }

    $order = $db->fetchOne('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$order) {
        throw new Exception('Order not found');
    }

    if ($order['status'] === 'voided') {
        throw new Exception('Order already voided');
    }

    if ($order['status'] === 'completed') {
        throw new Exception('Completed orders cannot be voided');
    }

    $settings = fetchVoidSettings($db);
    $policy = evaluatePolicies($order, $settings, $db, $auth);

    $reason = $db->fetchOne('SELECT * FROM void_reason_codes WHERE code = ?', [$reasonCode]);
    if (!$reason) {
        throw new Exception('Unknown reason code');
    }

    $needsManager = $policy['requires_manager'] || ($reason['requires_manager_approval'] ?? false);

    if ($needsManager) {
        if (!$managerUserId || !$managerPin) {
            throw new Exception('Manager approval required');
        }

        $manager = $db->fetchOne('SELECT id, role, void_pin FROM users WHERE id = ? AND is_active = 1', [$managerUserId]);
        if (!$manager || !in_array(strtolower($manager['role']), ['manager', 'admin', 'developer'], true)) {
            throw new Exception('Invalid manager selected');
        }

        if (!$manager['void_pin'] || !password_verify($managerPin, $manager['void_pin'])) {
            throw new Exception('Invalid manager PIN');
        }
    } else {
        $managerUserId = null;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('CALL VoidOrder(?, ?, ?, ?, ?, @success, @message)');
        $stmt->execute([$orderId, $reasonCode, $reasonText, $auth->getUserId(), $managerUserId]);

        $result = $db->fetchOne('SELECT @success AS success, @message AS message');
        if (empty($result['success'])) {
            throw new Exception($result['message'] ?? 'Void failed');
        }

        $db->query(
            'UPDATE void_transactions
             SET notes = ?, receipt_printed = 0
             WHERE order_id = ?
             ORDER BY id DESC LIMIT 1',
            [json_encode(['policy' => $policy]), $orderId]
        );

        if (($settings['print_void_receipt'] ?? '1') === '1') {
            $_SESSION['print_void_receipt'] = $orderId;
        }

        if (!empty($settings['void_notification_email'])) {
            queueVoidNotification(
                $db,
                $settings['void_notification_email'],
                $orderId,
                $reasonCode,
                $reasonText,
                $auth->getUserId(),
                $managerUserId
            );
        }

        $db->commit();

        $lists = fetchVoidLists($db, $auth);

        return array_merge(
            ['success' => true, 'message' => $result['message'] ?? 'Order voided successfully'],
            $lists
        );
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        throw $e;
    }
}

function fetchVoidLists(Database $db, Auth $auth): array
{
    $settings = fetchVoidSettings($db);
    $voidTimeLimit = (int)($settings['void_time_limit_minutes'] ?? 60);
    $amountThreshold = (float)($settings['require_manager_approval_amount'] ?? 0);
    $dailyLimit = (int)($settings['void_daily_limit'] ?? 10);

    $userTodayVoids = (int)($db->fetchOne(
        'SELECT COUNT(*) AS total FROM void_transactions WHERE voided_by_user_id = ? AND DATE(void_timestamp) = CURDATE()',
        [$auth->getUserId()]
    )['total'] ?? 0);

    $nearDailyLimit = $dailyLimit > 0 && $userTodayVoids >= max(0, $dailyLimit - 1);

    $orders = $db->fetchAll(
        "SELECT o.*, COUNT(oi.id) AS item_count, u.username AS created_by_username,
                TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) AS age_minutes
         FROM orders o
         LEFT JOIN order_items oi ON oi.order_id = o.id
         LEFT JOIN users u ON u.id = o.user_id
         WHERE o.status IN ('pending', 'confirmed', 'preparing', 'ready')
           AND o.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
         GROUP BY o.id
         ORDER BY o.created_at DESC
         LIMIT 100"
    );

    foreach ($orders as &$order) {
        $order['total_amount'] = (float)($order['total_amount'] ?? 0);
        $order['age_minutes'] = (int)($order['age_minutes'] ?? 0);
        $order['over_time_limit'] = $voidTimeLimit > 0 && $order['age_minutes'] > $voidTimeLimit;
        $order['over_amount_threshold'] = $amountThreshold > 0 && $order['total_amount'] > $amountThreshold;
        $order['near_daily_limit'] = $nearDailyLimit;
    }
    unset($order);

    $recentVoids = $db->fetchAll(
        "SELECT vt.*, vrc.display_name AS reason_name,
                vu.username AS voided_by_username,
                mu.username AS manager_username
         FROM void_transactions vt
         JOIN void_reason_codes vrc ON vrc.code = vt.void_reason_code
         LEFT JOIN users vu ON vu.id = vt.voided_by_user_id
         LEFT JOIN users mu ON mu.id = vt.manager_approval_user_id
         ORDER BY vt.void_timestamp DESC
         LIMIT 30"
    );

    $voidStats = [
        'today_voids' => (int)($db->fetchOne('SELECT COUNT(*) AS total FROM void_transactions WHERE DATE(void_timestamp) = CURDATE()')['total'] ?? 0),
        'week_voids' => (int)($db->fetchOne('SELECT COUNT(*) AS total FROM void_transactions WHERE void_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)')['total'] ?? 0),
        'user_today_voids' => $userTodayVoids,
        'total_void_amount_today' => (float)($db->fetchOne('SELECT SUM(original_total) AS total FROM void_transactions WHERE DATE(void_timestamp) = CURDATE()')['total'] ?? 0.0),
    ];

    return [
        'voidable_orders' => $orders,
        'recent_voids' => $recentVoids,
        'void_stats' => $voidStats,
        'void_settings' => $settings,
    ];
}

function fetchVoidSettings(Database $db): array
{
    $rows = $db->fetchAll('SELECT setting_key, setting_value FROM void_settings');
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function evaluatePolicies(array $order, array $settings, Database $db, Auth $auth): array
{
    $ageMinutes = (int)($db->fetchOne('SELECT TIMESTAMPDIFF(MINUTE, ?, NOW()) AS age', [$order['created_at']])['age'] ?? 0);
    $voidTimeLimit = (int)($settings['void_time_limit_minutes'] ?? 60);
    $amountThreshold = (float)($settings['require_manager_approval_amount'] ?? 0);
    $dailyLimit = (int)($settings['void_daily_limit'] ?? 10);

    $requiresManager = false;
    $flags = [];

    if ($ageMinutes > $voidTimeLimit) {
        $requiresManager = true;
        $flags[] = ['type' => 'time_limit', 'message' => "Order age exceeds {$voidTimeLimit} minutes"];
    }

    if ($amountThreshold > 0 && (float)$order['total_amount'] > $amountThreshold) {
        $requiresManager = true;
        $flags[] = ['type' => 'amount_threshold', 'message' => 'Order amount exceeds manager threshold'];
    }

    $todaysVoids = (int)($db->fetchOne(
        'SELECT COUNT(*) AS total FROM void_transactions WHERE voided_by_user_id = ? AND DATE(void_timestamp) = CURDATE()',
        [$auth->getUserId()]
    )['total'] ?? 0);

    if ($todaysVoids >= $dailyLimit) {
        $requiresManager = true;
        $flags[] = ['type' => 'daily_limit', 'message' => 'Daily void limit reached'];
    }

    return [
        'requires_manager' => $requiresManager,
        'flags' => $flags,
        'age_minutes' => $ageMinutes,
        'daily_voids' => $todaysVoids,
        'daily_limit' => $dailyLimit,
    ];
}

function queueVoidNotification(Database $db, string $email, int $orderId, string $reasonCode, string $reasonText, int $voidedByUserId, ?int $managerUserId): void
{
    try {
        $db->insert('email_queue', [
            'recipient' => $email,
            'subject' => 'Order voided: #' . $orderId,
            'body' => json_encode([
                'order_id' => $orderId,
                'reason_code' => $reasonCode,
                'reason_text' => $reasonText,
                'voided_by' => $voidedByUserId,
                'manager_user_id' => $managerUserId,
                'queued_at' => date('Y-m-d H:i:s'),
            ], JSON_PRETTY_PRINT),
            'created_at' => date('Y-m-d H:i:s'),
            'status' => 'pending',
        ]);
    } catch (Exception $e) {
        // Silently ignore if email queue table not present or insert fails
    }
}
