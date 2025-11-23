<?php
require_once '../../includes/bootstrap.php';

use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentRequestStore;

header('Content-Type: application/json');

function respondWebhook(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondWebhook(405, ['success' => false, 'message' => 'Invalid request method']);
}

$provider = isset($_GET['provider']) ? strtolower(trim($_GET['provider'])) : null;

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}
if (!is_array($payload)) {
    $payload = [];
}

$headers = [];
if (function_exists('getallheaders')) {
    $headers = getallheaders();
}

try {
    $managerOptions = [];
    if ($provider) {
        $managerOptions['provider'] = $provider;
    }

    $manager = new PaymentGatewayManager($managerOptions ?: null);
    $result = $manager->handleWebhook($provider, $payload, $headers);

    $store = new PaymentRequestStore();
    $updated = $store->updateStatus($result->reference, $result->status, [
        'provider_reference' => $result->providerReference,
        'meta' => $result->meta,
    ]);

    if (!$updated) {
        respondWebhook(404, [
            'success' => false,
            'message' => 'Payment request not found for reference',
            'reference' => $result->reference,
        ]);
    }

    respondWebhook(200, [
        'success' => true,
        'reference' => $result->reference,
        'status' => $result->status,
    ]);
} catch (Throwable $e) {
    respondWebhook(500, [
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
