<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

ob_start();

const COMPLETE_SALE_LOG = __DIR__ . '/../storage/logs/complete-sale.log';

if (!is_dir(dirname(COMPLETE_SALE_LOG))) {
    @mkdir(dirname(COMPLETE_SALE_LOG), 0775, true);
}

function logCompleteSaleError(string $message, array $context = []): void
{
    $line = '[' . date('Y-m-d H:i:s') . "] complete-sale | {$message}";
    if (!empty($context)) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $line .= PHP_EOL;
    @file_put_contents(COMPLETE_SALE_LOG, $line, FILE_APPEND);
}

$completeSaleResponded = false;

$fatalHandler = function () use (&$completeSaleResponded) {
    if ($completeSaleResponded) {
        return;
    }

    $error = error_get_last();
    $buffer = ob_get_clean();

    if ($buffer !== '' && stripos($buffer, '<br') !== false) {
        logCompleteSaleError('Buffer contained HTML output', ['buffer' => strip_tags($buffer)]);
    }

    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        logCompleteSaleError('Fatal error', $error);
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'A fatal error occurred while completing the sale. Check server logs for details.',
        ]);
    }
};

register_shutdown_function($fatalHandler);

require_once '../includes/bootstrap.php';
require_once __DIR__ . '/api-middleware.php';

use App\Services\AccountingService;
use App\Services\LoyaltyService;
use App\Services\SalesService;

header('Content-Type: application/json');

function respondJson(int $status, array $payload, string $buffer = ''): void {
    global $completeSaleResponded;
    $completeSaleResponded = true;
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
$moduleScope = isset($data['module_scope']) ? trim((string)$data['module_scope']) : 'retail';

$loyaltyPayload = $data['loyalty'] ?? null;
$loyaltyDiscount = 0.0;

if ($loyaltyPayload !== null) {
    if (empty($loyaltyPayload['customer_id'])) {
        respondJson(422, ['success' => false, 'message' => 'customer_id is required when loyalty data is provided'], ob_get_clean());
    }
    $loyaltyDiscount = max(0.0, (float)($loyaltyPayload['discount_amount'] ?? 0.0));
}

$promotions = [];
if (!empty($data['promotions']) && is_array($data['promotions'])) {
    foreach ($data['promotions'] as $promo) {
        if (!is_array($promo)) {
            continue;
        }
        $discount = isset($promo['discount']) ? (float)$promo['discount'] : 0.0;
        if ($discount <= 0) {
            continue;
        }
        $promotions[] = [
            'promotion_id' => isset($promo['promotion_id']) && (int)$promo['promotion_id'] > 0 ? (int)$promo['promotion_id'] : null,
            'product_id' => isset($promo['product_id']) && (int)$promo['product_id'] > 0 ? (int)$promo['product_id'] : null,
            'discount' => $discount,
            'details' => isset($promo['details']) ? trim((string)$promo['details']) : null,
        ];
    }
}

if ($paymentMethod === 'room_charge' && (!$roomBookingId || $roomBookingId <= 0)) {
    respondJson(422, ['success' => false, 'message' => 'room_booking_id is required when payment method is room_charge'], ob_get_clean());
}

$grossSubtotal = isset($data['subtotal']) ? (float) $data['subtotal'] : (float)($totals['subtotal'] ?? 0.0);
if ($grossSubtotal <= 0) {
    $grossSubtotal = 0.0;
    foreach ($items as $item) {
        $grossSubtotal += $item['qty'] * $item['price'];
    }
}

$promotionDiscount = isset($data['promotion_discount'])
    ? (float) $data['promotion_discount']
    : (float) ($totals['promotion_discount'] ?? 0.0);
$promotionDiscount = max(0.0, min($promotionDiscount, $grossSubtotal));

$netSubtotal = isset($data['net_subtotal'])
    ? (float) $data['net_subtotal']
    : max(0.0, $grossSubtotal - $promotionDiscount);

$taxAmount = isset($data['tax_amount']) ? (float) $data['tax_amount'] : (float) ($totals['tax'] ?? 0.0);
$loyaltyDiscountField = isset($data['loyalty_discount'])
    ? (float) $data['loyalty_discount']
    : (float) ($totals['loyalty_discount'] ?? 0.0);
if ($loyaltyPayload !== null && $loyaltyDiscount <= 0 && $loyaltyDiscountField > 0) {
    $loyaltyDiscount = max(0.0, $loyaltyDiscountField);
} elseif ($loyaltyPayload === null) {
    $loyaltyDiscount = max(0.0, $loyaltyDiscountField);
}

$discountAmount = max(0.0, $promotionDiscount + $loyaltyDiscount);
if ($discountAmount <= 0) {
    $discountAmount = isset($data['discount_amount'])
        ? (float) $data['discount_amount']
        : (float) ($totals['discount'] ?? 0.0);
}

$computedGrand = max(0.0, $netSubtotal + $taxAmount - $loyaltyDiscount);
$providedGrand = isset($data['total_amount']) ? (float) $data['total_amount'] : (float) ($totals['grand'] ?? 0.0);
$grandTotal = $providedGrand > 0 ? $providedGrand : $computedGrand;

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
$loyaltyService = null;
if ($loyaltyPayload !== null) {
    $loyaltyService = new LoyaltyService($pdo);
}

try {
    $result = $salesService->createSale([
        'user_id' => $auth->getUserId(),
        'customer_name' => $data['customer_name'] ?? null,
        'customer_phone' => $data['customer_phone'] ?? null,
        'mobile_money_phone' => $data['mobile_money_phone'] ?? null,
        'mobile_money_reference' => $data['mobile_money_reference'] ?? ($data['gateway_reference'] ?? null),
        'items' => $items,
        'promotions' => $promotions,
        'module_scope' => $moduleScope,
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

    if (!empty($result['accounting_warning'])) {
        error_log('complete-sale accounting warning: ' . $result['accounting_warning']);
    }

    if ($loyaltyService !== null && !empty($result['sale_id'])) {
        try {
            $loyaltyResult = $loyaltyService->applySaleLoyalty([
                'customer_id' => (int) $loyaltyPayload['customer_id'],
                'program_id' => isset($loyaltyPayload['program_id']) ? (int) $loyaltyPayload['program_id'] : null,
                'points_to_redeem' => isset($loyaltyPayload['points_to_redeem']) ? (int) $loyaltyPayload['points_to_redeem'] : 0,
                'discount_amount' => $loyaltyDiscount,
                'sale_id' => (int) $result['sale_id'],
                'sale_total' => $grandTotal,
            ]);
            $result['loyalty'] = $loyaltyResult;
        } catch (Throwable $loyaltyError) {
            error_log('loyalty application failed: ' . $loyaltyError->getMessage());
            $result['loyalty_warning'] = $loyaltyError->getMessage();
        }
    }

    $buffer = ob_get_clean();
    respondJson($statusCode, $result, $buffer);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logCompleteSaleError('Unhandled exception during sale completion', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'request' => [
            'payment_method' => $paymentMethod ?? null,
            'room_booking_id' => $roomBookingId ?? null,
            'amount_paid' => $amountPaid ?? null,
            'change_amount' => $changeAmount ?? null,
            'item_count' => isset($data['items']) && is_array($data['items']) ? count($data['items']) : 0,
        ],
    ]);
    $buffer = ob_get_clean();
    respondJson(500, [
        'success' => false,
        'message' => $e->getMessage(),
    ], $buffer);
}
