<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();

try {
    $summary = [
        'pending' => 0,
        'assigned' => 0,
        'in_transit' => 0,
        'delivered_today' => 0,
        'total_active' => 0,
        'active_riders' => 0,
        'timestamp' => date(DATE_ATOM),
    ];

    $statsRow = $pdo->query(
        "SELECT
             SUM(CASE WHEN d.status = 'pending' THEN 1 ELSE 0 END) AS pending,
             SUM(CASE WHEN d.status = 'assigned' THEN 1 ELSE 0 END) AS assigned,
             SUM(CASE WHEN d.status = 'picked-up' OR d.status = 'in-transit' THEN 1 ELSE 0 END) AS in_transit
         FROM deliveries d
         JOIN orders o ON d.order_id = o.id
         WHERE o.order_type = 'delivery' AND o.status NOT IN ('completed','cancelled')"
    )->fetch(PDO::FETCH_ASSOC) ?: [];

    $summary['pending'] = (int)($statsRow['pending'] ?? 0);
    $summary['assigned'] = (int)($statsRow['assigned'] ?? 0);
    $summary['in_transit'] = (int)($statsRow['in_transit'] ?? 0);
    $summary['total_active'] = $summary['pending'] + $summary['assigned'] + $summary['in_transit'];

    $summary['delivered_today'] = (int)($pdo->fetchOne(
        "SELECT COUNT(*) AS c FROM deliveries WHERE status = 'delivered' AND DATE(actual_delivery_time) = CURDATE()"
    )['c'] ?? 0);

    $summary['active_riders'] = (int)($pdo->fetchOne(
        "SELECT COUNT(*) AS c FROM riders WHERE is_active = 1 AND status = 'available'"
    )['c'] ?? 0);

    $deliveries = $pdo->fetchAll(
        "SELECT
             o.id AS order_id,
             o.order_number,
             o.customer_name,
             o.customer_phone,
             o.total_amount,
             o.created_at,
             d.id AS delivery_id,
             d.status AS delivery_status,
             d.estimated_delivery_time,
             d.actual_delivery_time,
             d.delivery_address,
             r.name AS rider_name,
             r.phone AS rider_phone
         FROM orders o
         JOIN deliveries d ON o.id = d.order_id
         LEFT JOIN riders r ON d.rider_id = r.id
         WHERE o.order_type = 'delivery' AND o.status NOT IN ('completed','cancelled')
         ORDER BY d.updated_at DESC, o.created_at DESC
         LIMIT 25"
    ) ?: [];

    $payloadDeliveries = array_map(static function ($row) {
        return [
            'order_id' => (int)($row['order_id'] ?? 0),
            'order_number' => $row['order_number'] ?? null,
            'customer_name' => $row['customer_name'] ?? null,
            'customer_phone' => $row['customer_phone'] ?? null,
            'total_amount' => isset($row['total_amount']) ? (float)$row['total_amount'] : null,
            'delivery_status' => $row['delivery_status'] ?? null,
            'delivery_address' => $row['delivery_address'] ?? null,
            'estimated_delivery_time' => $row['estimated_delivery_time'] ?? null,
            'actual_delivery_time' => $row['actual_delivery_time'] ?? null,
            'rider_name' => $row['rider_name'] ?? null,
            'rider_phone' => $row['rider_phone'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }, $deliveries);

    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'deliveries' => $payloadDeliveries,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load delivery feed',
        'error' => $e->getMessage(),
    ]);
}
