<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();

try {
    // Get database stats
    $dbStats = $db->getStats();
    
    // Get system stats
    $memoryUsage = memory_get_peak_usage(true);
    $memoryUsageMB = number_format($memoryUsage / 1024 / 1024, 1);
    
    // Check system health
    $health = [
        'database' => false,
        'cache' => false,
        'storage' => false,
        'performance' => false
    ];
    
    // Database health
    try {
        $db->fetchOne("SELECT 1");
        $health['database'] = true;
    } catch (Exception $e) {
        $health['database'] = false;
    }
    
    // Cache health
    $cacheDir = ROOT_PATH . '/cache';
    $health['cache'] = is_dir($cacheDir) && is_writable($cacheDir);
    
    // Storage health
    $freeSpace = disk_free_space(ROOT_PATH);
    $totalSpace = disk_total_space(ROOT_PATH);
    $usagePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
    $health['storage'] = $usagePercent < 90;
    
    // Performance health
    $health['performance'] = $memoryUsage < 128 * 1024 * 1024; // 128MB
    
    // Get recent performance metrics
    $recentSales = $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM sales 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    
    $recentOrders = $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    
    $stats = [
        'query_count' => $dbStats['query_count'],
        'cache_size' => $dbStats['cache_size'],
        'cache_enabled' => $dbStats['cache_enabled'],
        'memory_usage' => $memoryUsageMB,
        'memory_usage_bytes' => $memoryUsage,
        'recent_sales' => $recentSales['count'] ?? 0,
        'recent_orders' => $recentOrders['count'] ?? 0,
        'storage_usage_percent' => round($usagePercent, 1),
        'free_space_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
        'timestamp' => time()
    ];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'health' => $health
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
