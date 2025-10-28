<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['order_id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$db = Database::getInstance();

try {
    $db->beginTransaction();
    
    // Get delivery record
    $delivery = $db->fetchOne("
        SELECT d.*, r.id as rider_id 
        FROM deliveries d 
        LEFT JOIN riders r ON d.rider_id = r.id 
        WHERE d.order_id = ?
    ", [$data['order_id']]);
    
    if (!$delivery) {
        throw new Exception('Delivery record not found');
    }
    
    // Update delivery status
    $updateData = ['status' => $data['status']];
    
    // Set timestamps based on status
    switch ($data['status']) {
        case 'picked-up':
            $updateData['picked_up_at'] = date('Y-m-d H:i:s');
            break;
        case 'in-transit':
            $updateData['in_transit_at'] = date('Y-m-d H:i:s');
            break;
        case 'delivered':
            $updateData['actual_delivery_time'] = date('Y-m-d H:i:s');
            // Mark rider as available again
            if ($delivery['rider_id']) {
                $db->update('riders',
                    ['status' => 'available'],
                    'id = :id',
                    ['id' => $delivery['rider_id']]
                );
            }
            // Mark order as completed
            $db->update('orders',
                ['status' => 'completed'],
                'id = :id',
                ['id' => $data['order_id']]
            );
            break;
        case 'failed':
            $updateData['failed_at'] = date('Y-m-d H:i:s');
            $updateData['failure_reason'] = $data['reason'] ?? 'Delivery failed';
            // Mark rider as available again
            if ($delivery['rider_id']) {
                $db->update('riders',
                    ['status' => 'available'],
                    'id = :id',
                    ['id' => $delivery['rider_id']]
                );
            }
            break;
    }
    
    $db->update('deliveries',
        $updateData,
        'order_id = :order_id',
        ['order_id' => $data['order_id']]
    );
    
    // Update rider's delivery count and rating if delivered
    if ($data['status'] === 'delivered' && $delivery['rider_id']) {
        $db->query("
            UPDATE riders 
            SET total_deliveries = total_deliveries + 1,
                last_delivery_at = NOW()
            WHERE id = ?
        ", [$delivery['rider_id']]);
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Delivery status updated successfully'
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
