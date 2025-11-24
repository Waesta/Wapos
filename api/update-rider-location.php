<?php
/**
 * Rider location ingestion endpoint used by the mobile/PWA courier app.
 * Sample payload (JSON):
 * {
 *   "rider_id": 12,
 *   "latitude": -1.292066,
 *   "longitude": 36.821945,
 *   "accuracy": 4.2,
 *   "speed": 12.4,
 *   "heading": 85.0
 * }
 *
 * Notes:
 * - speed is meters/second (match the mobile GPS units)
 * - heading is degrees (0 = North, measured clockwise)
 * - accuracy is meters (optional)
 */
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
$speed = isset($data['speed']) && $data['speed'] !== '' ? (float)$data['speed'] : null;
$heading = isset($data['heading']) && $data['heading'] !== '' ? (float)$data['heading'] : null;

if ($riderId <= 0 || $latitude === null || $longitude === null) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$db = Database::getInstance();
$service = new DeliveryTrackingService($db->getConnection());

try {
    $updatedDeliveries = $service->updateRiderLocation($riderId, $latitude, $longitude, $accuracy, $speed, $heading);

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
