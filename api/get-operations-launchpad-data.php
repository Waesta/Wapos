<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$allowedRoles = ['admin', 'manager', 'super_admin', 'developer'];
$authorized = false;
foreach ($allowedRoles as $role) {
    if ($auth->hasRole($role)) {
        $authorized = true;
        break;
    }
}

if (!$authorized) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();

function opsTableExists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    $cache[$table] = ((int)($stmt->fetchColumn() ?? 0)) > 0;
    return $cache[$table];
}

$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));

$posSalesToday = (float)($db->fetchOne('SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE DATE(created_at) = ?', [$today])['total'] ?? 0);
$restaurantOrdersToday = opsTableExists($pdo, 'orders')
    ? (int)($db->fetchOne('SELECT COUNT(*) AS count FROM orders WHERE DATE(created_at) = ?', [$today])['count'] ?? 0)
    : 0;
$deliveryActive = opsTableExists($pdo, 'deliveries')
    ? (int)($db->fetchOne("SELECT COUNT(*) AS count FROM deliveries WHERE status IN ('assigned','picked-up','in-transit')")['count'] ?? 0)
    : null;
$loyaltyRedemptionsWeek = opsTableExists($pdo, 'loyalty_transactions')
    ? (int)($db->fetchOne("SELECT COUNT(*) AS count FROM loyalty_transactions WHERE transaction_type = 'redeem' AND DATE(created_at) >= ?", [$weekStart])['count'] ?? 0)
    : 0;

$promotionStats = opsTableExists($pdo, 'promotions')
    ? $db->fetchOne('SELECT COUNT(*) AS total, SUM(is_active = 1) AS active FROM promotions')
    : ['total' => 0, 'active' => 0];
$loyaltyStats = opsTableExists($pdo, 'loyalty_programs')
    ? $db->fetchOne('SELECT COUNT(*) AS total, SUM(is_active = 1) AS active FROM loyalty_programs')
    : ['total' => 0, 'active' => 0];
$modifierStats = opsTableExists($pdo, 'modifiers')
    ? $db->fetchOne('SELECT COUNT(*) AS total, SUM(is_active = 1) AS active FROM modifiers')
    : ['total' => 0, 'active' => 0];

$pendingMobileMoney = (int)($db->fetchOne(
    "SELECT COUNT(*) AS count FROM sales WHERE payment_method = 'mobile_money' AND (mobile_money_reference IS NULL OR mobile_money_reference = '') AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
)['count'] ?? 0);

$deliverySupportsRiders = opsTableExists($pdo, 'riders');

$moduleStatus = [
    [
        'label' => 'Promotions',
        'value' => ($promotionStats['active'] ?? 0) . ' / ' . ($promotionStats['total'] ?? 0) . ' active',
        'status' => ($promotionStats['active'] ?? 0) > 0 ? 'success' : 'warning',
        'description' => 'Targeted offers across POS, restaurant, rooms and delivery.',
        'link' => APP_URL . '/manage-promotions.php',
    ],
    [
        'label' => 'Loyalty Programs',
        'value' => ($loyaltyStats['active'] ?? 0) > 0 ? 'Program live' : 'Inactive',
        'status' => ($loyaltyStats['active'] ?? 0) > 0 ? 'success' : 'warning',
        'description' => 'Configure earn / redeem rules and review top members.',
        'link' => APP_URL . '/loyalty-programs.php',
    ],
    [
        'label' => 'Modifier Library',
        'value' => ($modifierStats['total'] ?? 0) . ' options',
        'status' => ($modifierStats['total'] ?? 0) > 0 ? 'info' : 'warning',
        'description' => 'Guest additions surfaced on every restaurant order.',
        'link' => APP_URL . '/manage-modifiers.php',
    ],
    [
        'label' => 'Delivery Console',
        'value' => $deliveryActive === null ? 'Setup required' : $deliveryActive . ' active jobs',
        'status' => $deliveryActive === null ? 'danger' : ($deliveryActive > 0 ? 'primary' : 'success'),
        'description' => 'Track riders, pricing audits, and SLA performance.',
        'link' => APP_URL . '/delivery-dashboard.php',
    ],
    [
        'label' => 'Mobile Money Audit',
        'value' => $pendingMobileMoney > 0 ? $pendingMobileMoney . ' pending confirmations' : 'All reconciled',
        'status' => $pendingMobileMoney > 0 ? 'warning' : 'success',
        'description' => 'Review collected phone numbers & transaction IDs.',
        'link' => APP_URL . '/reports.php?tab=payments',
    ],
    [
        'label' => 'System Diagnostics',
        'value' => 'Health & schema guards',
        'status' => 'info',
        'description' => 'Monitor automatic schema repairs and background jobs.',
        'link' => APP_URL . '/status.php',
    ],
];

$actionCenter = [];
if ($pendingMobileMoney > 0) {
    $actionCenter[] = [
        'label' => 'Mobile money confirmations pending',
        'detail' => $pendingMobileMoney . ' sale(s) require supervisor review.',
        'link' => APP_URL . '/reports.php?tab=payments',
        'tone' => 'warning',
    ];
}
if (($promotionStats['total'] ?? 0) === 0) {
    $actionCenter[] = [
        'label' => 'Create your first promotion',
        'detail' => 'Configure an upsell to drive conversions today.',
        'link' => APP_URL . '/manage-promotions.php',
        'tone' => 'info',
    ];
}
if (!opsTableExists($pdo, 'deliveries')) {
    $actionCenter[] = [
        'label' => 'Delivery tables missing',
        'detail' => 'Run delivery setup scripts to enable live tracking.',
        'link' => APP_URL . '/delivery-pricing.php',
        'tone' => 'danger',
    ];
}

$quickLinks = [
    ['label' => 'POS', 'icon' => 'bi-cart', 'link' => APP_URL . '/pos.php'],
    ['label' => 'Restaurant', 'icon' => 'bi-egg-fried', 'link' => APP_URL . '/restaurant.php'],
    ['label' => 'Orders', 'icon' => 'bi-journal-text', 'link' => APP_URL . '/restaurant-order.php'],
    ['label' => 'Delivery Board', 'icon' => 'bi-truck', 'link' => APP_URL . '/delivery.php'],
    ['label' => 'Accounting', 'icon' => 'bi-calculator', 'link' => APP_URL . '/accounting.php'],
    ['label' => 'Reports', 'icon' => 'bi-graph-up', 'link' => APP_URL . '/reports.php'],
];

$metrics = [
    'pos_sales_today' => $posSalesToday,
    'restaurant_orders_today' => $restaurantOrdersToday,
    'delivery_active' => $deliveryActive,
    'loyalty_redemptions_week' => $loyaltyRedemptionsWeek,
    'pending_mobile_money' => $pendingMobileMoney,
    'delivery_supports_riders' => $deliverySupportsRiders,
];

$response = [
    'success' => true,
    'data' => [
        'metrics' => $metrics,
        'module_status' => $moduleStatus,
        'action_center' => $actionCenter,
        'quick_links' => $quickLinks,
        'generated_at' => date(DATE_ATOM),
    ],
];

echo json_encode($response);
