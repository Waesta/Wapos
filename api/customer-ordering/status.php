<?php
use App\Services\DeliveryTrackingService;

$orderId = isset($payload['order_id']) ? (int)$payload['order_id'] : 0;

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'order_id is required']);
    return;
}

$order = $db->fetchOne('
    SELECT id, order_number, order_type, status, total_amount, payment_status, created_at, updated_at
    FROM orders
    WHERE id = ?
    LIMIT 1
', [$orderId]);

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    return;
}

$delivery = $db->fetchOne('
    SELECT id, status, rider_id, estimated_time, actual_delivery_time, created_at, updated_at
    FROM deliveries
    WHERE order_id = ?
    ORDER BY id DESC
    LIMIT 1
', [$orderId]);

$trackingService = new DeliveryTrackingService($pdo);
$timelineRows = $delivery ? $trackingService->getTimelineForOrder($orderId) : [];

$timeline = array_map(function (array $row) {
    return [
        'id' => isset($row['id']) ? (int)$row['id'] : null,
        'status' => $row['status'] ?? null,
        'notes' => $row['notes'] ?? null,
        'photo_url' => $row['photo_url'] ?? null,
        'latitude' => $row['latitude'] ?? null,
        'longitude' => $row['longitude'] ?? null,
        'recorded_by' => $row['recorded_by'] ?? null,
        'created_at' => $row['created_at'] ?? null,
    ];
}, $timelineRows);

$latestStatus = $delivery['status'] ?? $order['status'];
$latestTimestamp = $delivery['updated_at'] ?? $order['updated_at'] ?? $order['created_at'];

$response = [
    'success' => true,
    'order' => $order,
    'delivery' => $delivery,
    'timeline' => $timeline,
    'latest_status' => $latestStatus,
    'latest_timestamp' => $latestTimestamp,
];

echo json_encode($response);
