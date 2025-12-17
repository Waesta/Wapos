<?php
/**
 * Delivery Dispatch API - Intelligent rider assignment endpoints
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

$auth->requireLogin();
$role = $auth->getRole();

// Only admin, manager, super_admin, developer can access dispatch functions
if (!in_array($role, ['super_admin', 'admin', 'manager', 'developer'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$db = Database::getInstance()->getConnection();
$mapsService = new App\Services\GoogleMapsService($db);
$dispatchService = new App\Services\DeliveryDispatchService($db, $mapsService);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'find_optimal_rider':
            // Find best rider for a delivery location
            $deliveryLat = (float)($_POST['delivery_lat'] ?? 0);
            $deliveryLng = (float)($_POST['delivery_lng'] ?? 0);
            
            if (!$deliveryLat || !$deliveryLng) {
                throw new Exception('Delivery coordinates required');
            }

            $options = [
                'priority' => $_POST['priority'] ?? 'normal',
                'max_active_deliveries' => (int)($_POST['max_active_deliveries'] ?? 3),
                'max_distance_km' => (int)($_POST['max_distance_km'] ?? 50)
            ];

            $result = $dispatchService->findOptimalRider($deliveryLat, $deliveryLng, $options);
            
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;

        case 'auto_assign':
            // Automatically assign delivery to optimal rider
            $deliveryId = (int)($_POST['delivery_id'] ?? 0);
            
            if (!$deliveryId) {
                throw new Exception('Delivery ID required');
            }

            $options = [
                'priority' => $_POST['priority'] ?? 'normal'
            ];

            $result = $dispatchService->autoAssignDelivery($deliveryId, $options);
            
            echo json_encode($result);
            break;

        case 'rider_availability':
            // Get all riders with their current availability status
            $riders = $dispatchService->getRiderAvailability();
            
            echo json_encode([
                'success' => true,
                'riders' => $riders,
                'total' => count($riders)
            ]);
            break;

        case 'validate_address':
            // Check if delivery address is routable
            $lat = (float)($_POST['lat'] ?? 0);
            $lng = (float)($_POST['lng'] ?? 0);
            
            if (!$lat || !$lng) {
                throw new Exception('Coordinates required');
            }

            $validation = $dispatchService->validateDeliveryAddress($lat, $lng);
            
            echo json_encode([
                'success' => true,
                'validation' => $validation
            ]);
            break;

        case 'dispatch_analytics':
            // Get dispatch performance analytics
            $days = (int)($_GET['days'] ?? 7);
            
            $stmt = $db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_dispatches,
                    AVG(duration_seconds) as avg_duration_seconds,
                    AVG(distance_meters) as avg_distance_meters,
                    AVG(candidates_evaluated) as avg_candidates,
                    AVG(selection_score) as avg_score
                FROM delivery_dispatch_log
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            $stmt->execute([$days]);
            $analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'analytics' => $analytics,
                'period_days' => $days
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    $errorCode = 500;
    
    // Map specific errors to appropriate HTTP codes
    if ($e->getMessage() === 'no_riders_available') {
        $errorCode = 503; // Service Unavailable
    } elseif ($e->getMessage() === 'route_calculation_failed') {
        $errorCode = 502; // Bad Gateway
    } elseif (strpos($e->getMessage(), 'not found') !== false) {
        $errorCode = 404;
    }
    
    http_response_code($errorCode);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $errorCode
    ]);
}
