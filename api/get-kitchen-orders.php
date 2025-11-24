<?php
require_once '../includes/bootstrap.php';
require_once '../includes/schema/orders.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
ensureOrdersCompletedAtColumn($db);

try {
    // Get active orders
    $orders = $db->fetchAll("
        SELECT o.*, rt.table_number, rt.table_name,
               COUNT(oi.id) as total_items,
               SUM(CASE WHEN oi.status = 'ready' THEN 1 ELSE 0 END) as ready_items
        FROM orders o
        LEFT JOIN restaurant_tables rt ON o.table_id = rt.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.status IN ('pending', 'preparing', 'ready') 
        AND o.order_type IN ('dine-in', 'takeout')
        GROUP BY o.id
        ORDER BY o.created_at ASC
    ");
    
    // Calculate statistics
    $stats = [
        'pending' => 0,
        'preparing' => 0,
        'ready' => 0,
        'avg_time' => '--'
    ];
    
    $totalTime = 0;
    $completedToday = 0;
    
    foreach ($orders as $order) {
        $stats[$order['status']]++;
    }
    
    // Get average completion time for today
    $avgTimeResult = $db->fetchOne("
        SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at)) as avg_minutes
        FROM orders 
        WHERE status = 'completed' 
        AND completed_at IS NOT NULL
        AND DATE(completed_at) = CURDATE()
    ");
    
    if ($avgTimeResult && $avgTimeResult['avg_minutes']) {
        $stats['avg_time'] = round($avgTimeResult['avg_minutes']) . 'm';
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'orders' => $orders,
            'stats' => $stats
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
