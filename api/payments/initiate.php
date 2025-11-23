<?php
require_once '../../includes/bootstrap.php';

use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentGatewayRequest;
use App\Services\Payments\PaymentRequestStore;

header('Content-Type: application/json');

$buffer = '';

function respond(int $status, array $payload, string $buffer = ''): void {
    if ($buffer !== '') {
        error_log('payments/initiate buffer: ' . $buffer);
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if (!$auth->isLoggedIn()) {
    respond(401, ['success' => false, 'message' => 'Unauthorized']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Invalid request method']);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    respond(400, ['success' => false, 'message' => 'Invalid JSON payload']);
}

$amount = isset($payload['amount']) ? (float)$payload['amount'] : 0;
if ($amount <= 0) {
    respond(422, ['success' => false, 'message' => 'Amount must be greater than zero']);
}

$contextType = $payload['context_type'] ?? 'pos_sale';
$contextId = isset($payload['context_id']) ? (int)$payload['context_id'] : 0;

try {
    $request = new PaymentGatewayRequest([
        'context_type' => $contextType,
        'context_id' => $contextId,
        'amount' => $amount,
        'currency' => $payload['currency'] ?? (settings('currency_code') ?? 'KES'),
        'customer_phone' => $payload['customer_phone'] ?? null,
        'customer_email' => $payload['customer_email'] ?? null,
        'customer_name' => $payload['customer_name'] ?? null,
        'metadata' => $payload['metadata'] ?? [],
    ]);

    $manager = new PaymentGatewayManager();
    $response = $manager->initiate($request);

    $store = new PaymentRequestStore();
    $providerKey = $manager->getActiveProviderKey();
    $store->create([
        'reference' => $response->reference,
        'provider' => $providerKey,
        'status' => $response->status,
        'context_type' => $request->contextType,
        'context_id' => $request->contextId,
        'amount' => $request->amount,
        'currency' => $request->currency,
        'customer_phone' => $request->customerPhone,
        'customer_email' => $request->customerEmail,
        'customer_name' => $request->customerName,
        'instructions' => $response->instructions,
        'provider_reference' => $response->providerReference,
        'meta' => $response->meta,
        'initiated_by_user_id' => $auth->getUserId(),
    ]);

    respond(200, [
        'success' => true,
        'provider' => $providerKey,
        'data' => [
            'status' => $response->status,
            'reference' => $response->reference,
            'provider_reference' => $response->providerReference,
            'redirect_url' => $response->redirectUrl,
            'instructions' => $response->instructions,
            'meta' => $response->meta,
        ],
    ]);
} catch (Throwable $e) {
    respond(500, [
        'success' => false,
        'message' => $e->getMessage(),
    ], $buffer);
}
