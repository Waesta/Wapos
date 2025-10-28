<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$riderId = $data['rider_id'] ?? null;
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;
$accuracy = $data['accuracy'] ?? null;

if (!$riderId || !$latitude || !$longitude) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$db = Database::getInstance();

try {
    // Update rider location
    $db->update('riders', [
        'current_latitude' => $latitude,
        'current_longitude' => $longitude,
        'location_accuracy' => $accuracy,
        'last_location_update' => date('Y-m-d H:i:s')
    ], 'id = :id', ['id' => $riderId]);

    // Log location history
    $db->insert('rider_location_history', [
        'rider_id' => $riderId,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'accuracy' => $accuracy,
        'recorded_at' => date('Y-m-d H:i:s')
    ]);

    // Update delivery ETAs for active deliveries
    $activeDeliveries = $db->fetchAll("
        SELECT d.*, o.delivery_address 
        FROM deliveries d
        JOIN orders o ON d.order_id = o.id
        WHERE d.rider_id = ? AND d.status IN ('assigned', 'picked-up', 'in-transit')
    ", [$riderId]);

    foreach ($activeDeliveries as $delivery) {
        // Calculate estimated delivery time based on distance and traffic
        $estimatedTime = calculateDeliveryETA($latitude, $longitude, $delivery['delivery_address']);
        
        $db->update('deliveries', [
            'estimated_delivery_time' => $estimatedTime
        ], 'id = :id', ['id' => $delivery['id']]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Location updated successfully',
        'updated_deliveries' => count($activeDeliveries)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function calculateDeliveryETA($riderLat, $riderLng, $deliveryAddress) {
    // This is a simplified ETA calculation
    // In a real implementation, you would use Google Maps Distance Matrix API
    // or similar service to get accurate travel time
    
    // For now, return a basic estimate (15-45 minutes)
    $baseTime = 15; // Base delivery time in minutes
    $randomFactor = rand(0, 30); // Add 0-30 minutes based on distance/traffic
    
    return date('Y-m-d H:i:s', strtotime("+{$baseTime} minutes +{$randomFactor} minutes"));
}
?>
