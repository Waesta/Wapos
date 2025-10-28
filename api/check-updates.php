<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$clientVersion = $data['version'] ?? '';
$clientTimestamp = $data['timestamp'] ?? 0;
$currentPage = $data['page'] ?? '';

try {
    // Get current system version
    $currentVersion = trim(file_get_contents(ROOT_PATH . '/version.txt'));
    $currentTimestamp = time();
    
    $response = [
        'success' => true,
        'needsUpdate' => false,
        'forceReload' => false,
        'softUpdate' => false,
        'updates' => []
    ];
    
    // Check if version has changed (major update)
    if ($clientVersion !== $currentVersion) {
        $response['needsUpdate'] = true;
        $response['forceReload'] = true;
        $response['message'] = 'System updated to version ' . $currentVersion;
    }
    // Check for data updates based on page
    else {
        $dataUpdated = false;
        $updates = [];
        
        // Page-specific update checks
        switch (true) {
            case strpos($currentPage, 'kitchen-display') !== false:
                // Check for new orders or status changes
                $lastOrderUpdate = $db->fetchOne("
                    SELECT MAX(UNIX_TIMESTAMP(GREATEST(created_at, IFNULL(updated_at, created_at)))) as last_update 
                    FROM orders 
                    WHERE status IN ('pending', 'preparing', 'ready')
                ");
                
                if ($lastOrderUpdate && $lastOrderUpdate['last_update'] > $clientTimestamp) {
                    $dataUpdated = true;
                    $updates[] = [
                        'type' => 'reload_component',
                        'selector' => '#ordersGrid',
                        'url' => APP_URL . '/api/get-kitchen-orders.php'
                    ];
                }
                break;
                
            case strpos($currentPage, 'delivery') !== false:
                // Check for delivery status changes
                $lastDeliveryUpdate = $db->fetchOne("
                    SELECT MAX(UNIX_TIMESTAMP(GREATEST(created_at, IFNULL(updated_at, created_at)))) as last_update 
                    FROM deliveries
                ");
                
                if ($lastDeliveryUpdate && $lastDeliveryUpdate['last_update'] > $clientTimestamp) {
                    $dataUpdated = true;
                    $updates[] = [
                        'type' => 'reload_component',
                        'selector' => '.delivery-orders',
                        'url' => APP_URL . '/api/get-delivery-status.php'
                    ];
                }
                break;
                
            case strpos($currentPage, 'index') !== false || strpos($currentPage, 'dashboard') !== false:
                // Check for new sales or significant changes
                $lastSaleUpdate = $db->fetchOne("
                    SELECT MAX(UNIX_TIMESTAMP(created_at)) as last_update 
                    FROM sales 
                    WHERE DATE(created_at) = CURDATE()
                ");
                
                if ($lastSaleUpdate && $lastSaleUpdate['last_update'] > $clientTimestamp) {
                    $dataUpdated = true;
                    $updates[] = [
                        'type' => 'reload_component',
                        'selector' => '.stats-cards',
                        'url' => APP_URL . '/api/get-dashboard-stats.php'
                    ];
                }
                break;
        }
        
        if ($dataUpdated) {
            $response['needsUpdate'] = true;
            $response['softUpdate'] = true;
            $response['updates'] = $updates;
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
