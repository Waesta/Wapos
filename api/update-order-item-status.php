<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['item_id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$db = Database::getInstance();

try {
    $db->beginTransaction();
    
    // Update item status
    $updated = $db->update('order_items',
        ['status' => $data['status']],
        'id = :id',
        ['id' => $data['item_id']]
    );
    
    if (!$updated) {
        throw new Exception('Failed to update item status');
    }
    
    // Get order ID for this item
    $orderItem = $db->fetchOne("SELECT order_id FROM order_items WHERE id = ?", [$data['item_id']]);
    
    if ($orderItem) {
        // Check if all items in the order are ready
        $orderStats = $db->fetchOne("
            SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready_items
            FROM order_items 
            WHERE order_id = ?
        ", [$orderItem['order_id']]);
        
        // Update order status based on item statuses
        $newOrderStatus = 'pending';
        if ($orderStats['ready_items'] > 0 && $orderStats['ready_items'] < $orderStats['total_items']) {
            $newOrderStatus = 'preparing';
        } elseif ($orderStats['ready_items'] == $orderStats['total_items']) {
            $newOrderStatus = 'ready';
        }
        
        $db->update('orders',
            ['status' => $newOrderStatus],
            'id = :id',
            ['id' => $orderItem['order_id']]
        );
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Item status updated successfully'
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
