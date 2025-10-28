<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();

try {
    // Get delivery statistics
    $stats = [
        'active_deliveries' => $db->fetchOne("SELECT COUNT(*) as count FROM deliveries WHERE status IN ('assigned', 'picked-up', 'in-transit')")['count'] ?? 0,
        'pending_deliveries' => $db->fetchOne("SELECT COUNT(*) as count FROM deliveries WHERE status = 'pending'")['count'] ?? 0,
        'completed_today' => $db->fetchOne("SELECT COUNT(*) as count FROM deliveries WHERE status = 'delivered' AND DATE(actual_delivery_time) = CURDATE()")['count'] ?? 0,
        'average_delivery_time' => round($db->fetchOne("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, actual_delivery_time)) as avg_time FROM deliveries WHERE status = 'delivered' AND DATE(actual_delivery_time) = CURDATE()")['avg_time'] ?? 0)
    ];

    // Get active deliveries
    $deliveries = $db->fetchAll("
        SELECT 
            d.*,
            o.order_number,
            o.customer_name,
            o.customer_phone,
            o.total_amount,
            r.name as rider_name,
            r.phone as rider_phone,
            r.vehicle_type,
            r.vehicle_number,
            r.current_latitude,
            r.current_longitude,
            TIMESTAMPDIFF(MINUTE, d.created_at, NOW()) as elapsed_minutes
        FROM deliveries d
        JOIN orders o ON d.order_id = o.id
        LEFT JOIN riders r ON d.rider_id = r.id
        WHERE d.status IN ('pending', 'assigned', 'picked-up', 'in-transit')
        ORDER BY d.created_at ASC
    ");

    // Get chart data for the last 7 days
    $chartData = $db->fetchAll("
        SELECT 
            DATE(actual_delivery_time) as delivery_date,
            COUNT(*) as deliveries_count,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, actual_delivery_time)) as avg_time
        FROM deliveries 
        WHERE status = 'delivered' 
        AND actual_delivery_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(actual_delivery_time)
        ORDER BY delivery_date ASC
    ");

    // Get rider performance data
    $riderPerformance = $db->fetchAll("
        SELECT 
            r.name,
            r.id,
            COUNT(d.id) as total_deliveries,
            AVG(d.customer_rating) as avg_customer_rating,
            AVG(TIMESTAMPDIFF(MINUTE, d.created_at, d.actual_delivery_time)) as avg_delivery_time,
            SUM(CASE WHEN d.status = 'delivered' THEN 1 ELSE 0 END) as successful_deliveries
        FROM riders r
        LEFT JOIN deliveries d ON r.id = d.rider_id AND DATE(d.created_at) = CURDATE()
        WHERE r.is_active = 1
        GROUP BY r.id, r.name
        ORDER BY successful_deliveries DESC, avg_customer_rating DESC
    ");

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'deliveries' => $deliveries,
        'chartData' => $chartData,
        'riderPerformance' => $riderPerformance
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
