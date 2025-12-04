<?php
/**
 * Initiate M-Pesa STK Push Payment
 * 
 * Sends USSD prompt to customer's phone for payment authorization
 * 
 * POST Parameters:
 * - phone: Customer phone number (required)
 * - amount: Amount to charge (required)
 * - reference: Payment reference/invoice number (optional)
 * - description: Payment description (optional)
 * - sale_id: Related sale ID (optional)
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

require_once '../../includes/bootstrap.php';

use App\Services\Payments\Providers\MpesaGateway;
use App\Services\Payments\PaymentGatewayRequest;
use App\Services\Payments\PaymentRequestStore;

header('Content-Type: application/json');

function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Require authentication
$auth = Auth::getInstance();
if (!$auth->isLoggedIn()) {
    respond(401, ['success' => false, 'message' => 'Authentication required']);
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Method not allowed']);
}

// Get input
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$phone = trim($input['phone'] ?? '');
$amount = floatval($input['amount'] ?? 0);
$reference = trim($input['reference'] ?? '');
$description = trim($input['description'] ?? 'Payment');
$saleId = intval($input['sale_id'] ?? 0);

// Validate
if (empty($phone)) {
    respond(400, ['success' => false, 'message' => 'Phone number is required']);
}

if ($amount <= 0) {
    respond(400, ['success' => false, 'message' => 'Amount must be greater than 0']);
}

// Check if M-Pesa is configured
$consumerKey = settings('mpesa_consumer_key', '');
$consumerSecret = settings('mpesa_consumer_secret', '');
$passkey = settings('mpesa_passkey', '');
$shortcode = settings('mpesa_shortcode', '');

if (empty($consumerKey) || empty($consumerSecret) || empty($passkey) || empty($shortcode)) {
    respond(500, ['success' => false, 'message' => 'M-Pesa is not configured. Please set up credentials in Payment Gateways settings.']);
}

try {
    // Initialize gateway
    $gateway = new MpesaGateway([
        'consumer_key' => $consumerKey,
        'consumer_secret' => $consumerSecret,
        'passkey' => $passkey,
        'shortcode' => $shortcode,
        'shortcode_type' => settings('mpesa_shortcode_type', 'paybill'),
        'environment' => settings('mpesa_environment', 'sandbox'),
        'callback_url' => settings('mpesa_callback_url', APP_URL . '/api/payments/mpesa-callback.php'),
    ]);

    // Create payment request
    $request = new PaymentGatewayRequest([
        'amount' => $amount,
        'currency' => 'KES',
        'customerPhone' => $phone,
        'contextType' => $saleId ? 'sale' : 'pos',
        'contextId' => $saleId,
        'metadata' => [
            'reference' => $reference,
            'description' => $description,
            'account_reference' => $reference ?: ('INV-' . time()),
        ],
    ]);

    // Initiate STK Push
    $response = $gateway->stkPush($request);

    // Store payment request for tracking
    $store = new PaymentRequestStore();
    $store->create([
        'reference' => $response->reference,
        'provider' => 'mpesa',
        'status' => 'pending',
        'context_type' => $saleId ? 'sale' : 'pos',
        'context_id' => $saleId,
        'amount' => $amount,
        'currency' => 'KES',
        'customer_phone' => $phone,
        'provider_reference' => $response->providerReference,
        'instructions' => $response->instructions,
        'meta' => $response->meta,
        'initiated_by_user_id' => $auth->getUserId(),
    ]);

    // Also store by checkout request ID for callback matching
    if (!empty($response->meta['checkout_request_id'])) {
        $store->create([
            'reference' => $response->meta['checkout_request_id'],
            'provider' => 'mpesa',
            'status' => 'pending',
            'context_type' => $saleId ? 'sale' : 'pos',
            'context_id' => $saleId,
            'amount' => $amount,
            'currency' => 'KES',
            'customer_phone' => $phone,
            'provider_reference' => $response->providerReference,
            'meta' => ['original_reference' => $response->reference],
            'initiated_by_user_id' => $auth->getUserId(),
        ]);
    }

    respond(200, [
        'success' => true,
        'message' => $response->instructions,
        'reference' => $response->reference,
        'checkout_request_id' => $response->meta['checkout_request_id'] ?? null,
        'merchant_request_id' => $response->meta['merchant_request_id'] ?? null,
    ]);

} catch (Throwable $e) {
    error_log("STK Push error: " . $e->getMessage());
    
    respond(500, [
        'success' => false,
        'message' => 'Failed to initiate payment: ' . $e->getMessage(),
    ]);
}
