<?php
require_once '../includes/bootstrap.php';

use App\Services\DeliveryTrackingService;

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

$riderId = isset($data['rider_id']) ? (int)$data['rider_id'] : 0;
$latitude = isset($data['latitude']) ? (float)$data['latitude'] : null;
$longitude = isset($data['longitude']) ? (float)$data['longitude'] : null;
$accuracy = isset($data['accuracy']) && $data['accuracy'] !== '' ? (float)$data['accuracy'] : null;

if ($riderId <= 0 || $latitude === null || $longitude === null) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$db = Database::getInstance();
$service = new DeliveryTrackingService($db->getConnection());

try {
    $updatedDeliveries = $service->updateRiderLocation($riderId, $latitude, $longitude, $accuracy);

    echo json_encode([
        'success' => true,
        'message' => 'Location updated successfully',
        'updated_deliveries' => count($updatedDeliveries),
        'deliveries' => $updatedDeliveries
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
