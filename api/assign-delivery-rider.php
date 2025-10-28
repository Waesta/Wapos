<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['order_id']) || !isset($data['rider_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$db = Database::getInstance();

try {
    $db->beginTransaction();
    
    // Check if order exists and is not already assigned
    $order = $db->fetchOne("SELECT * FROM orders WHERE id = ?", [$data['order_id']]);
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // Check if rider is available
    $rider = $db->fetchOne("SELECT * FROM riders WHERE id = ? AND status = 'available'", [$data['rider_id']]);
    if (!$rider) {
        throw new Exception('Rider not available');
    }
    
    // Create or update delivery record
    $existingDelivery = $db->fetchOne("SELECT id FROM deliveries WHERE order_id = ?", [$data['order_id']]);
    
    if ($existingDelivery) {
        // Update existing delivery
        $db->update('deliveries',
            [
                'rider_id' => $data['rider_id'],
                'status' => 'assigned',
                'estimated_delivery_time' => $data['estimated_time'] ?? null,
                'delivery_notes' => $data['notes'] ?? null,
                'assigned_at' => date('Y-m-d H:i:s')
            ],
            'id = :id',
            ['id' => $existingDelivery['id']]
        );
    } else {
        // Create new delivery record
        $db->insert('deliveries', [
            'order_id' => $data['order_id'],
            'rider_id' => $data['rider_id'],
            'status' => 'assigned',
            'estimated_delivery_time' => $data['estimated_time'] ?? null,
            'delivery_notes' => $data['notes'] ?? null,
            'assigned_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Update rider status to busy
    $db->update('riders',
        ['status' => 'busy'],
        'id = :id',
        ['id' => $data['rider_id']]
    );
    
    // Update order status
    $db->update('orders',
        ['status' => 'assigned'],
        'id = :id',
        ['id' => $data['order_id']]
    );
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Rider assigned successfully'
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
