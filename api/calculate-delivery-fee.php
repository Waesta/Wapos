<?php

require_once '../includes/bootstrap.php';

use App\Services\DeliveryPricingService;

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$latRaw = $data['delivery_latitude'] ?? null;
$lngRaw = $data['delivery_longitude'] ?? null;

$latitude = is_numeric($latRaw) ? (float)$latRaw : null;
$longitude = is_numeric($lngRaw) ? (float)$lngRaw : null;

if ($latitude === null || $longitude === null) {
    echo json_encode(['success' => false, 'message' => 'Delivery latitude and longitude are required']);
    exit;
}

try {
    $db = Database::getInstance();
    $service = new DeliveryPricingService($db);

    $orderContext = [
        'delivery_fee' => $data['delivery_fee'] ?? null,
        'subtotal' => isset($data['subtotal']) && is_numeric($data['subtotal']) ? (float)$data['subtotal'] : 0,
        'tax_amount' => isset($data['tax_amount']) && is_numeric($data['tax_amount']) ? (float)$data['tax_amount'] : 0,
        'discount_amount' => isset($data['discount_amount']) && is_numeric($data['discount_amount']) ? (float)$data['discount_amount'] : 0,
    ];

    $pricing = $service->calculateFee($orderContext, $latitude, $longitude);

    echo json_encode([
        'success' => true,
        'data' => [
            'distance_km' => $pricing['distance_km'],
            'calculated_fee' => $pricing['calculated_fee'],
            'base_fee' => $pricing['base_fee'],
            'zone' => $pricing['zone'],
        ]
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
