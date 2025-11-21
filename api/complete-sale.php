<?php
require_once '../includes/bootstrap.php';

use App\Services\AccountingService;
use App\Services\SalesService;
use Throwable;

header('Content-Type: application/json');
ob_start();

function respondJson(int $status, array $payload, string $buffer = ''): void {
    if ($buffer !== '') {
        error_log('complete-sale buffer: ' . $buffer);
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if (!$auth->isLoggedIn()) {
    respondJson(401, ['success' => false, 'message' => 'Unauthorized'], ob_get_clean());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(405, ['success' => false, 'message' => 'Invalid request method'], ob_get_clean());
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['items']) || !is_array($data['items'])) {
    respondJson(400, ['success' => false, 'message' => 'Invalid sale payload'], ob_get_clean());
}

if (empty($data['csrf_token']) || !validateCSRFToken($data['csrf_token'])) {
    respondJson(419, ['success' => false, 'message' => 'Invalid CSRF token'], ob_get_clean());
}

$items = [];
foreach ($data['items'] as $index => $item) {
    if (!isset($item['product_id'], $item['qty'], $item['price'])) {
        respondJson(400, [
            'success' => false,
            'message' => "Missing line item fields at index {$index}."
        ], ob_get_clean());
    }

    $qty = (float) $item['qty'];
    $price = (float) $item['price'];

    if ($qty <= 0 || $price < 0) {
        respondJson(400, [
            'success' => false,
            'message' => "Invalid quantity or price for item {$index}."
        ], ob_get_clean());
    }

    $items[] = [
        'product_id' => (int) $item['product_id'],
        'qty' => $qty,
        'price' => $price,
        'tax_rate' => isset($item['tax_rate']) ? (float) $item['tax_rate'] : 0.0,
        'discount' => isset($item['discount']) ? (float) $item['discount'] : 0.0,
    ];
}

if (empty($items)) {
    respondJson(400, ['success' => false, 'message' => 'No sale items provided'], ob_get_clean());
}

$totals = $data['totals'] ?? [];
$paymentMethod = $data['payment_method'] ?? 'cash';
$roomBookingId = isset($data['room_booking_id']) ? (int)$data['room_booking_id'] : null;

if ($paymentMethod === 'room_charge' && (!$roomBookingId || $roomBookingId <= 0)) {
    respondJson(422, ['success' => false, 'message' => 'room_booking_id is required when payment method is room_charge'], ob_get_clean());
}

$subtotal = isset($data['subtotal']) ? (float) $data['subtotal'] : ($totals['subtotal'] ?? 0.0);
if ($subtotal <= 0) {
    $subtotal = 0.0;
    foreach ($items as $item) {
        $subtotal += $item['qty'] * $item['price'];
    }
}

$taxAmount = isset($data['tax_amount']) ? (float) $data['tax_amount'] : ($totals['tax'] ?? 0.0);
$discountAmount = isset($data['discount_amount']) ? (float) $data['discount_amount'] : ($totals['discount'] ?? 0.0);
$grandTotal = isset($data['total_amount']) ? (float) $data['total_amount'] : ($totals['grand'] ?? ($subtotal + $taxAmount - $discountAmount));

$amountPaid = isset($data['amount_paid']) ? (float) $data['amount_paid'] : $grandTotal;
$changeAmount = isset($data['change_amount']) ? (float) $data['change_amount'] : max(0, $amountPaid - $grandTotal);
if ($paymentMethod === 'room_charge') {
    $amountPaid = 0.0;
    $changeAmount = 0.0;
}

$database = Database::getInstance();
$pdo = $database->getConnection();

$accountingService = new AccountingService($pdo);
$salesService = new SalesService($pdo, $accountingService);

try {
    $result = $salesService->createSale([
        'user_id' => $auth->getUserId(),
        'customer_name' => $data['customer_name'] ?? null,
        'customer_phone' => $data['customer_phone'] ?? null,
        'items' => $items,
        'totals' => [
            'subtotal' => $subtotal,
            'tax' => $taxAmount,
            'discount' => $discountAmount,
            'grand' => $grandTotal,
        ],
        'amount_paid' => $amountPaid,
        'change_amount' => $changeAmount,
        'payment_method' => $paymentMethod,
        'room_booking_id' => $roomBookingId,
        'room_charge_description' => $data['room_charge_description'] ?? null,
        'notes' => $data['notes'] ?? null,
    ]);

    $statusCode = $result['status_code'] ?? 200;
    unset($result['status_code']);

    $buffer = ob_get_clean();
    respondJson($statusCode, $result, $buffer);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $buffer = ob_get_clean();
    respondJson(500, [
        'success' => false,
        'message' => $e->getMessage(),
    ], $buffer);
}
