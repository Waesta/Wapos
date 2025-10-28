<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$db = Database::getInstance();

try {
    $db->beginTransaction();
    
    // Get order details
    $order = $db->fetchOne("SELECT * FROM orders WHERE id = ?", [$data['order_id']]);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // Update order status to completed
    $updated = $db->update('orders',
        [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s')
        ],
        'id = :id',
        ['id' => $data['order_id']]
    );
    
    if (!$updated) {
        throw new Exception('Failed to complete order');
    }
    
    // Update all order items to ready if not already
    $db->query("
        UPDATE order_items 
        SET status = 'ready' 
        WHERE order_id = ? AND status != 'ready'
    ", [$data['order_id']]);
    
    // If it's a dine-in order, we might want to keep the table occupied
    // until the customer leaves (handled separately)
    
    // Create a notification/alert for the waiter (if needed)
    if ($order['order_type'] === 'dine-in' && $order['table_id']) {
        // You could add a notifications system here
        // For now, we'll just log it
        error_log("Order {$order['order_number']} completed for table {$order['table_id']}");
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order completed successfully',
        'order_number' => $order['order_number']
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
