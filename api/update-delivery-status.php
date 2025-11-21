<?php
require_once '../includes/bootstrap.php';

use App\Services\DeliveryTrackingService;

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
$trackingService = new DeliveryTrackingService($db->getConnection());

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
    
    $newStatus = $data['status'];
    // Update delivery status
    $updateData = ['status' => $newStatus];
    
    // Set timestamps based on status
    switch ($newStatus) {
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
    if ($newStatus === 'delivered' && $delivery['rider_id']) {
        $db->query("
            UPDATE riders 
            SET total_deliveries = total_deliveries + 1,
                last_delivery_at = NOW()
            WHERE id = ?
        ", [$delivery['rider_id']]);
    }

    $db->commit();

    $historyNotes = $data['notes'] ?? null;
    if ($newStatus === 'failed' && !$historyNotes) {
        $historyNotes = $data['reason'] ?? 'Marked as failed';
    }

    // Record timeline entry
    try {
        $trackingService->recordStatusHistory((int)$delivery['id'], $newStatus, [
            'notes' => $historyNotes,
            'user_id' => $auth->getUserId(),
        ]);
    } catch (Exception $inner) {
        // Timeline recording failure should not break response
        error_log('Failed to record delivery status history: ' . $inner->getMessage());
    }
    
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
