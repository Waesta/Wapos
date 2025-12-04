<?php
/**
 * Relworx Payment Callback Handler
 * Receives payment notifications from Relworx API
 * 
 * Documentation: https://docs.relworx.com
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

require_once '../../includes/bootstrap.php';

use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentRequestStore;

// Log all incoming requests for debugging
$logFile = ROOT_PATH . '/storage/logs/relworx-callbacks.log';
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

// Relworx sends POST requests for callbacks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Method not allowed']);
}

// Parse the incoming payload
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    $payload = $_POST;
}

if (empty($payload)) {
    respond(400, ['success' => false, 'message' => 'Empty payload']);
}

try {
    $db = Database::getInstance();
    
    // Extract Relworx callback data
    // Relworx typically sends: reference, status, amount, currency, internal_reference, message
    $reference = $payload['reference'] ?? $payload['merchant_reference'] ?? null;
    $status = strtolower($payload['status'] ?? 'pending');
    $internalRef = $payload['internal_reference'] ?? $payload['transaction_id'] ?? null;
    $amount = $payload['amount'] ?? 0;
    $message = $payload['message'] ?? $payload['description'] ?? null;
    
    if (!$reference) {
        respond(400, ['success' => false, 'message' => 'Missing reference']);
    }
    
    // Map Relworx status to our internal status
    $mappedStatus = match($status) {
        'success', 'successful', 'paid', 'completed' => 'completed',
        'failed', 'error', 'declined', 'cancelled' => 'failed',
        'pending', 'processing' => 'pending',
        default => 'pending'
    };
    
    // Update payment request in database
    $store = new PaymentRequestStore();
    $updated = $store->updateStatus($reference, $mappedStatus, [
        'provider_reference' => $internalRef,
        'amount_paid' => $amount,
        'message' => $message,
        'raw_response' => $payload,
        'completed_at' => $mappedStatus === 'completed' ? date('Y-m-d H:i:s') : null,
    ]);
    
    if (!$updated) {
        // Try to find by provider reference
        $updated = $store->updateByProviderReference($internalRef, $mappedStatus, [
            'reference' => $reference,
            'amount_paid' => $amount,
            'message' => $message,
            'raw_response' => $payload,
            'completed_at' => $mappedStatus === 'completed' ? date('Y-m-d H:i:s') : null,
        ]);
    }
    
    // If payment completed, update the related sale/order
    if ($mappedStatus === 'completed' && $updated) {
        $paymentRequest = $store->findByReference($reference);
        if ($paymentRequest) {
            $contextType = $paymentRequest['context_type'] ?? null;
            $contextId = $paymentRequest['context_id'] ?? null;
            
            if ($contextType === 'sale' && $contextId) {
                $db->query(
                    "UPDATE sales SET payment_status = 'paid', payment_reference = ?, updated_at = NOW() WHERE id = ?",
                    [$internalRef, $contextId]
                );
            } elseif ($contextType === 'order' && $contextId) {
                $db->query(
                    "UPDATE restaurant_orders SET payment_status = 'paid', payment_reference = ?, updated_at = NOW() WHERE id = ?",
                    [$internalRef, $contextId]
                );
            }
        }
    }
    
    // Log successful processing
    if (class_exists('SystemLogger')) {
        SystemLogger::getInstance()->info(
            "Relworx callback processed: {$reference} - {$mappedStatus}",
            'payment',
            ['reference' => $reference, 'status' => $mappedStatus, 'provider_ref' => $internalRef]
        );
    }
    
    respond(200, [
        'success' => true,
        'message' => 'Callback processed successfully',
        'reference' => $reference,
        'status' => $mappedStatus,
    ]);
    
} catch (Throwable $e) {
    // Log error
    error_log("Relworx callback error: " . $e->getMessage());
    
    respond(500, [
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage(),
    ]);
}
