<?php
/**
 * Google Maps API Endpoint
 * Handles Places API, Routes API, and geocoding requests
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

$auth->requireLogin();

use App\Services\GoogleMapsService;

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $googleMaps = new GoogleMapsService($db);

    switch ($action) {
        case 'autocomplete':
            // Address autocomplete using Places API
            $input = $_GET['input'] ?? '';
            
            if (empty($input)) {
                throw new Exception('Input is required');
            }

            $options = [];
            
            // Add location bias if provided
            if (!empty($_GET['lat']) && !empty($_GET['lng'])) {
                $options['location'] = [
                    'lat' => (float)$_GET['lat'],
                    'lng' => (float)$_GET['lng']
                ];
                $options['radius'] = (int)($_GET['radius'] ?? 50000);
            }

            // Country restriction
            if (!empty($_GET['country'])) {
                $options['country'] = $_GET['country'];
            }

            $suggestions = $googleMaps->autocompleteAddress($input, $options);

            echo json_encode([
                'success' => true,
                'suggestions' => $suggestions
            ]);
            break;

        case 'place_details':
            // Get place details by place_id
            $placeId = $_GET['place_id'] ?? '';
            
            if (empty($placeId)) {
                throw new Exception('Place ID is required');
            }

            $details = $googleMaps->getPlaceDetails($placeId);

            echo json_encode([
                'success' => true,
                'place' => $details
            ]);
            break;

        case 'geocode':
            // Geocode address to coordinates
            $address = $_GET['address'] ?? $_POST['address'] ?? '';
            
            if (empty($address)) {
                throw new Exception('Address is required');
            }

            $result = $googleMaps->geocodeAddress($address);

            echo json_encode([
                'success' => true,
                'result' => $result
            ]);
            break;

        case 'compute_route':
            // Compute single route
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            if (empty($data['origin']) || empty($data['destination'])) {
                throw new Exception('Origin and destination are required');
            }

            $origin = [
                'lat' => (float)$data['origin']['lat'],
                'lng' => (float)$data['origin']['lng']
            ];

            $destination = [
                'lat' => (float)$data['destination']['lat'],
                'lng' => (float)$data['destination']['lng']
            ];

            $options = [
                'travel_mode' => $data['travel_mode'] ?? 'DRIVE',
                'routing_preference' => $data['routing_preference'] ?? 'TRAFFIC_AWARE',
                'alternatives' => $data['alternatives'] ?? false,
                'avoid_tolls' => $data['avoid_tolls'] ?? false,
                'avoid_highways' => $data['avoid_highways'] ?? false,
                'avoid_ferries' => $data['avoid_ferries'] ?? false
            ];

            $route = $googleMaps->computeRoute($origin, $destination, $options);

            echo json_encode([
                'success' => true,
                'route' => $route
            ]);
            break;

        case 'optimize_route':
            // Optimize route for multiple deliveries
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            if (empty($data['origin']) || empty($data['deliveries'])) {
                throw new Exception('Origin and deliveries are required');
            }

            $origin = [
                'lat' => (float)$data['origin']['lat'],
                'lng' => (float)$data['origin']['lng']
            ];

            $deliveries = [];
            foreach ($data['deliveries'] as $delivery) {
                $deliveries[] = [
                    'lat' => (float)$delivery['lat'],
                    'lng' => (float)$delivery['lng'],
                    'id' => $delivery['id'] ?? null
                ];
            }

            $optimized = $googleMaps->optimizeMultipleDeliveries($origin, $deliveries);

            echo json_encode([
                'success' => true,
                'optimized_route' => $optimized
            ]);
            break;

        case 'distance_matrix':
            // Calculate distance matrix using Routes API
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            if (empty($data['origins']) || empty($data['destinations'])) {
                throw new Exception('Origins and destinations are required');
            }

            $options = [
                'travel_mode' => $data['travel_mode'] ?? 'DRIVE',
                'routing_preference' => $data['routing_preference'] ?? 'TRAFFIC_AWARE',
                'avoid_tolls' => $data['avoid_tolls'] ?? false,
                'avoid_highways' => $data['avoid_highways'] ?? false,
                'avoid_ferries' => $data['avoid_ferries'] ?? false
            ];

            $matrix = $googleMaps->calculateDistanceMatrix(
                $data['origins'],
                $data['destinations'],
                $options
            );

            echo json_encode([
                'success' => true,
                'matrix' => $matrix,
                'note' => 'Using modern Routes API instead of deprecated Distance Matrix API'
            ]);
            break;

        case 'validate_address':
            // Validate and standardize address
            $address = $_GET['address'] ?? $_POST['address'] ?? '';
            
            if (empty($address)) {
                throw new Exception('Address is required');
            }

            $validation = $googleMaps->validateAddress($address);

            echo json_encode([
                'success' => true,
                'validation' => $validation
            ]);
            break;

        case 'delivery_eta':
            // Get delivery ETA with traffic
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            if (empty($data['origin']) || empty($data['destination'])) {
                throw new Exception('Origin and destination are required');
            }

            $origin = [
                'lat' => (float)$data['origin']['lat'],
                'lng' => (float)$data['origin']['lng']
            ];

            $destination = [
                'lat' => (float)$data['destination']['lat'],
                'lng' => (float)$data['destination']['lng']
            ];

            $eta = $googleMaps->getDeliveryETA($origin, $destination);

            echo json_encode([
                'success' => true,
                'eta' => $eta
            ]);
            break;

        case 'config':
            // Get JavaScript configuration
            $config = $googleMaps->getJavaScriptConfig();

            echo json_encode([
                'success' => true,
                'config' => $config
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
