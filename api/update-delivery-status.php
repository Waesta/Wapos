<?php
require_once '../includes/bootstrap.php';

use App\Services\DeliveryTrackingService;

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Handle both JSON and multipart/form-data (for photo uploads)
$data = [];
if ($_SERVER['CONTENT_TYPE'] && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
    $data = $_POST;
} else {
    $data = json_decode(file_get_contents('php://input'), true);
}

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// Support both delivery_id and order_id
$deliveryId = $data['delivery_id'] ?? null;
$orderId = $data['order_id'] ?? null;
$newStatus = $data['status'] ?? null;

if (!$newStatus || (!$deliveryId && !$orderId)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$db = Database::getInstance();
$trackingService = new DeliveryTrackingService($db->getConnection());

try {
    $db->beginTransaction();
    
    // Get delivery record
    $whereClause = $deliveryId ? 'd.id = ?' : 'd.order_id = ?';
    $whereValue = $deliveryId ?: $orderId;
    
    $delivery = $db->fetchOne("
        SELECT d.*, r.id as rider_id 
        FROM deliveries d 
        LEFT JOIN riders r ON d.rider_id = r.id 
        WHERE {$whereClause}
    ", [$whereValue]);
    
    if (!$delivery) {
        throw new Exception('Delivery record not found');
    }
    
    // Handle photo upload
    $photoUrl = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/delivery-proofs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $filename = 'delivery_' . $delivery['id'] . '_' . time() . '.' . $extension;
        $uploadPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
            $photoUrl = '/uploads/delivery-proofs/' . $filename;
        }
    }
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
        'id = :id',
        ['id' => $delivery['id']]
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

    // Record timeline entry with location and photo
    try {
        $historyContext = [
            'notes' => $historyNotes,
            'user_id' => $auth->getUserId(),
        ];
        
        if (isset($data['latitude']) && isset($data['longitude'])) {
            $historyContext['latitude'] = (float)$data['latitude'];
            $historyContext['longitude'] = (float)$data['longitude'];
        }
        
        if ($photoUrl) {
            $historyContext['photo_url'] = $photoUrl;
        }
        
        $trackingService->recordStatusHistory((int)$delivery['id'], $newStatus, $historyContext);
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
