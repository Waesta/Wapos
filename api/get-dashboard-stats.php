<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();

try {
    $today = date('Y-m-d');
    
    // Today's sales stats
    $todayStats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_sales,
            COALESCE(SUM(total_amount), 0) as total_revenue
        FROM sales 
        WHERE DATE(created_at) = ?
    ", [$today]);
    
    // Pending orders count
    $pendingOrders = $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE status IN ('pending', 'preparing')
    ");
    
    // Low stock items
    $lowStock = $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM products 
        WHERE stock_quantity <= reorder_level AND is_active = 1
    ");
    
    // Active deliveries
    $activeDeliveries = $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM deliveries 
        WHERE status IN ('assigned', 'picked-up', 'in-transit')
    ");
    
    $stats = [
        'today_sales' => $todayStats['total_sales'] ?? 0,
        'today_revenue' => $todayStats['total_revenue'] ?? 0,
        'pending_orders' => $pendingOrders['count'] ?? 0,
        'low_stock' => $lowStock['count'] ?? 0,
        'active_deliveries' => $activeDeliveries['count'] ?? 0,
        'timestamp' => time()
    ];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
