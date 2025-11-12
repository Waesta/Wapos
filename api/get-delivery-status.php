<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();

try {
    // Get riders
    $riders = $db->fetchAll("
        SELECT 
            id,
            name,
            phone,
            vehicle_type,
            vehicle_number,
            status,
            COALESCE(total_deliveries, 0) AS total_deliveries,
            COALESCE(rating, 0) AS rating
        FROM riders
        WHERE is_active = 1
        ORDER BY name
    ");

    // Get active delivery orders
    $deliveryOrders = $db->fetchAll("
        SELECT 
            o.*,
            d.status as delivery_status,
            d.rider_id,
            r.name as rider_name,
            r.phone as rider_phone
        FROM orders o
        LEFT JOIN deliveries d ON o.id = d.order_id
        LEFT JOIN riders r ON d.rider_id = r.id
        WHERE o.order_type = 'delivery'
        AND o.status NOT IN ('completed', 'cancelled')
        ORDER BY o.created_at DESC
        LIMIT 50
    ");
    
    // Get delivery statistics
    $stats = [
        'pending' => 0,
        'assigned' => 0,
        'in_transit' => 0,
        'total' => count($deliveryOrders),
        'active_riders' => count(array_filter($riders, fn($r) => ($r['status'] ?? '') === 'available')),
        'today_delivered' => (int)($db->fetchOne("SELECT COUNT(*) as count FROM deliveries WHERE status = 'delivered' AND DATE(actual_delivery_time) = CURDATE()")['count'] ?? 0)
    ];
    
    foreach ($deliveryOrders as $order) {
        $status = $order['delivery_status'] ?? 'pending';
        if (isset($stats[$status])) {
            $stats[$status]++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $deliveryOrders,
        'riders' => $riders,
        'stats' => $stats,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
