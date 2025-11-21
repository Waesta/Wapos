<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!$data || !is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

if (empty($data['csrf_token']) || !validateCSRFToken($data['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$orderId = filter_var($data['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$riderId = filter_var($data['rider_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$orderId || !$riderId) {
    echo json_encode(['success' => false, 'message' => 'Invalid order or rider id']);
    exit;
}

$estimatedTime = $data['estimated_time'] ?? null;
if ($estimatedTime) {
    $timestamp = strtotime($estimatedTime);
    if ($timestamp === false || $timestamp < time() - 3600) {
        echo json_encode(['success' => false, 'message' => 'Estimated time must be a valid future datetime']);
        exit;
    }
    $estimatedTime = date('Y-m-d H:i:s', $timestamp);
}

$notes = isset($data['notes']) ? trim((string)$data['notes']) : null;
if ($notes !== null && strlen($notes) > 500) {
    echo json_encode(['success' => false, 'message' => 'Delivery notes may not exceed 500 characters']);
    exit;
}

$db = Database::getInstance();

try {
    $db->beginTransaction();
    
    // Check if order exists and is not already assigned
    $order = $db->fetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
    if (!$order) {
        throw new Exception('Order not found');
    }

    if (in_array($order['status'], ['delivered', 'cancelled'], true)) {
        throw new Exception('Order is already closed');
    }

    $existingDelivery = $db->fetchOne("SELECT id, status, rider_id FROM deliveries WHERE order_id = ?", [$orderId]);
    if ($existingDelivery && $existingDelivery['status'] !== 'cancelled') {
        throw new Exception('Order already has an active rider assignment');
    }

    // Check if rider is available and workload threshold
    $workload = $db->fetchOne("SELECT r.*, (
            SELECT COUNT(*) FROM deliveries d 
            WHERE d.rider_id = r.id AND d.status IN ('assigned', 'picked-up', 'in-transit')
        ) AS active_jobs
        FROM riders r
        WHERE r.id = ? AND r.is_active = 1", [$riderId]);

    $maxActiveJobs = 3;
    if (!$workload) {
        throw new Exception('Rider not found');
    }

    $activeJobs = (int)($workload['active_jobs'] ?? 0);
    $currentStatus = $workload['status'] ?? null;

    if ($currentStatus !== 'available' && $activeJobs >= $maxActiveJobs) {
        throw new Exception('Rider workload is at capacity');
    }

    if ($currentStatus !== 'busy') {
        $db->execute("UPDATE riders SET status = 'busy' WHERE id = ?", [$riderId]);
    }

    // Create or update delivery record
    if ($existingDelivery) {
        $db->update('deliveries',
            [
                'rider_id' => $riderId,
                'status' => 'assigned',
                'estimated_delivery_time' => $estimatedTime,
                'delivery_notes' => $notes,
                'assigned_at' => date('Y-m-d H:i:s')
            ],
            'id = :id',
            ['id' => $existingDelivery['id']]
        );
    } else {
        $db->insert('deliveries', [
            'order_id' => $orderId,
            'rider_id' => $riderId,
            'status' => 'assigned',
            'estimated_delivery_time' => $estimatedTime,
            'delivery_notes' => $notes,
            'assigned_at' => date('Y-m-d H:i:s')
        ]);
    }

    $db->update('orders',
        ['status' => 'assigned'],
        'id = :id',
        ['id' => $orderId]
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
