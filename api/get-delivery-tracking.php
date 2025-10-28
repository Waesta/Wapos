<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$orderId = $_GET['order_id'] ?? null;

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

$db = Database::getInstance();

try {
    // Get order and delivery information
    $tracking = $db->fetchOne("
        SELECT 
            o.*,
            d.status as delivery_status,
            d.rider_id,
            d.estimated_delivery_time,
            d.actual_delivery_time,
            d.delivery_notes,
            d.delivery_address,
            r.name as rider_name,
            r.phone as rider_phone,
            r.vehicle_type,
            r.vehicle_number
        FROM orders o
        LEFT JOIN deliveries d ON o.id = d.order_id
        LEFT JOIN riders r ON d.rider_id = r.id
        WHERE o.id = ?
    ", [$orderId]);
    
    if (!$tracking) {
        throw new Exception('Order not found');
    }
    
    // Format the response
    $response = [
        'order_number' => $tracking['order_number'],
        'customer_name' => $tracking['customer_name'],
        'customer_phone' => $tracking['customer_phone'],
        'delivery_address' => $tracking['delivery_address'] ?? $tracking['customer_address'] ?? 'N/A',
        'total_amount' => $tracking['total_amount'],
        'created_at' => $tracking['created_at'],
        'status' => $tracking['delivery_status'] ?? 'pending',
        'rider_name' => $tracking['rider_name'],
        'rider_phone' => $tracking['rider_phone'],
        'vehicle_type' => $tracking['vehicle_type'],
        'vehicle_number' => $tracking['vehicle_number'],
        'estimated_time' => $tracking['estimated_delivery_time'],
        'actual_delivery_time' => $tracking['actual_delivery_time'],
        'delivery_notes' => $tracking['delivery_notes']
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $response
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
