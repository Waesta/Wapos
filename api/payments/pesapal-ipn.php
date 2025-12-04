<?php
/**
 * PesaPal IPN (Instant Payment Notification) Handler
 * Receives payment notifications from PesaPal API v3
 * 
 * Documentation: https://developer.pesapal.com/how-to-integrate/e-commerce/api-30-json/api-reference
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

require_once '../../includes/bootstrap.php';

use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentRequestStore;

// Log all incoming requests for debugging
$logFile = ROOT_PATH . '/storage/logs/pesapal-ipn.log';
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => function_exists('getallheaders') ? getallheaders() : [],
    'get' => $_GET,
    'post' => $_POST,
    'raw' => file_get_contents('php://input'),
];
file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);

header('Content-Type: application/json');

function respond(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

// PesaPal IPN can be GET or POST
$payload = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true) ?: $_POST;
} else {
    $payload = $_GET;
}

if (empty($payload)) {
    respond(400, ['success' => false, 'message' => 'Empty payload']);
}

try {
    $db = Database::getInstance();
    
    // PesaPal IPN sends: OrderTrackingId, OrderMerchantReference, OrderNotificationType
    $trackingId = $payload['OrderTrackingId'] ?? $payload['tracking_id'] ?? null;
    $merchantRef = $payload['OrderMerchantReference'] ?? $payload['merchant_reference'] ?? null;
    $notificationType = $payload['OrderNotificationType'] ?? $payload['notification_type'] ?? null;
    
    if (!$trackingId && !$merchantRef) {
        respond(400, ['success' => false, 'message' => 'Missing tracking ID or merchant reference']);
    }
    
    // Get transaction status from PesaPal API
    $consumerKey = settings('pesapal_consumer_key', '');
    $consumerSecret = settings('pesapal_consumer_secret', '');
    $environment = settings('pesapal_environment', 'sandbox');
    
    if (empty($consumerKey) || empty($consumerSecret)) {
        respond(500, ['success' => false, 'message' => 'PesaPal not configured']);
    }
    
    $baseUrl = $environment === 'live' 
        ? 'https://pay.pesapal.com/v3/api'
        : 'https://cybqa.pesapal.com/pesapalv3/api';
    
    // Get auth token
    $tokenResponse = httpPost($baseUrl . '/Auth/RequestToken', [
        'consumer_key' => $consumerKey,
        'consumer_secret' => $consumerSecret,
    ]);
    
    if (empty($tokenResponse['token'])) {
        respond(500, ['success' => false, 'message' => 'Failed to authenticate with PesaPal']);
    }
    
    $token = $tokenResponse['token'];
    
    // Get transaction status
    $statusResponse = httpGet(
        $baseUrl . '/Transactions/GetTransactionStatus?orderTrackingId=' . urlencode($trackingId),
        ['Authorization: Bearer ' . $token]
    );
    
    // Map PesaPal status codes
    // 0 = INVALID, 1 = COMPLETED, 2 = FAILED, 3 = REVERSED
    $statusCode = $statusResponse['payment_status_description'] ?? $statusResponse['status_code'] ?? null;
    $paymentMethod = $statusResponse['payment_method'] ?? null;
    $amount = $statusResponse['amount'] ?? 0;
    $currency = $statusResponse['currency'] ?? 'KES';
    $description = $statusResponse['description'] ?? null;
    
    $mappedStatus = match(strtolower((string)$statusCode)) {
        'completed', '1' => 'completed',
        'failed', '2' => 'failed',
        'reversed', '3' => 'refunded',
        'invalid', '0' => 'failed',
        default => 'pending'
    };
    
    // Update payment request
    $store = new PaymentRequestStore();
    $reference = $merchantRef ?: $trackingId;
    
    $updated = $store->updateStatus($reference, $mappedStatus, [
        'provider_reference' => $trackingId,
        'amount_paid' => $amount,
        'currency' => $currency,
        'payment_method' => $paymentMethod,
        'message' => $description,
        'raw_response' => $statusResponse,
        'completed_at' => $mappedStatus === 'completed' ? date('Y-m-d H:i:s') : null,
    ]);
    
    // If payment completed, update the related sale/order
    if ($mappedStatus === 'completed' && $updated) {
        $paymentRequest = $store->findByReference($reference);
        if ($paymentRequest) {
            $contextType = $paymentRequest['context_type'] ?? null;
            $contextId = $paymentRequest['context_id'] ?? null;
            
            if ($contextType === 'sale' && $contextId) {
                $db->query(
                    "UPDATE sales SET payment_status = 'paid', payment_reference = ?, payment_method = ?, updated_at = NOW() WHERE id = ?",
                    [$trackingId, $paymentMethod, $contextId]
                );
            } elseif ($contextType === 'order' && $contextId) {
                $db->query(
                    "UPDATE restaurant_orders SET payment_status = 'paid', payment_reference = ?, updated_at = NOW() WHERE id = ?",
                    [$trackingId, $contextId]
                );
            }
        }
    }
    
    // Log successful processing
    if (class_exists('SystemLogger')) {
        SystemLogger::getInstance()->info(
            "PesaPal IPN processed: {$reference} - {$mappedStatus}",
            'payment',
            ['reference' => $reference, 'status' => $mappedStatus, 'tracking_id' => $trackingId]
        );
    }
    
    // PesaPal expects specific response format
    respond(200, [
        'orderNotificationType' => $notificationType,
        'orderTrackingId' => $trackingId,
        'orderMerchantReference' => $merchantRef,
        'status' => 200,
    ]);
    
} catch (Throwable $e) {
    error_log("PesaPal IPN error: " . $e->getMessage());
    
    respond(500, [
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage(),
    ]);
}

/**
 * HTTP POST helper
 */
function httpPost(string $url, array $data, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
        ], $headers),
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true) ?: [];
}

/**
 * HTTP GET helper
 */
function httpGet(string $url, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge([
            'Accept: application/json',
        ], $headers),
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true) ?: [];
}
