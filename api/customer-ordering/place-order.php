<?php
use App\Services\SalesService;
use App\Services\AccountingService;
use App\Services\DeliveryPricingService;

if (empty($payload['items']) || !is_array($payload['items'])) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty']);
    return;
}

if (empty($payload['csrf_token']) || !validateCSRFToken($payload['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    return;
}

$pdo = $db->getConnection();
$orderType = strtolower((string)($payload['order_type'] ?? 'delivery'));
$itemsInput = $payload['items'];

$normalizedItems = [];
$subtotal = 0.0;
$taxTotal = 0.0;

foreach ($itemsInput as $entry) {
    $productId = isset($entry['product_id']) ? (int)$entry['product_id'] : 0;
    $qty = isset($entry['qty']) ? (int)$entry['qty'] : 0;

    if ($productId <= 0 || $qty <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid cart item detected']);
        return;
    }

    $product = $db->fetchOne('SELECT id, name, price, tax_rate FROM products WHERE id = ? LIMIT 1', [$productId]);
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'A product in your cart is unavailable.']);
        return;
    }

    $unitPrice = isset($entry['price']) ? (float)$entry['price'] : (float)$product['price'];
    $lineSubtotal = $unitPrice * $qty;
    $lineTaxRate = isset($product['tax_rate']) ? (float)$product['tax_rate'] : 0.0;
    $lineTax = $lineSubtotal * ($lineTaxRate / 100);

    $normalizedItems[] = [
        'product_id' => (int)$product['id'],
        'name' => $product['name'],
        'qty' => $qty,
        'price' => round($unitPrice, 2),
        'tax_rate' => $lineTaxRate,
        'line_subtotal' => round($lineSubtotal, 2),
        'line_tax' => round($lineTax, 2),
    ];

    $subtotal += $lineSubtotal;
    $taxTotal += $lineTax;
}

$deliveryFee = 0.0;
$deliveryPricing = null;
$auditRequestId = null;
$pricingService = null;

if ($orderType === 'delivery') {
    $deliveryLat = isset($payload['delivery_latitude']) ? (float)$payload['delivery_latitude'] : null;
    $deliveryLng = isset($payload['delivery_longitude']) ? (float)$payload['delivery_longitude'] : null;
    $manualDeliveryFee = array_key_exists('delivery_fee', $payload) ? (float)$payload['delivery_fee'] : null;

    $pricingService = new DeliveryPricingService($db);
    $deliveryPricing = $pricingService->calculateFee([
        'order_type' => $orderType,
        'items' => $normalizedItems,
        'totals' => [
            'subtotal' => $subtotal,
            'tax' => $taxTotal,
        ],
        'delivery_fee' => $manualDeliveryFee,
    ], $deliveryLat, $deliveryLng);

    if (isset($deliveryPricing['calculated_fee']) && $deliveryPricing['calculated_fee'] !== null) {
        $deliveryFee = (float)$deliveryPricing['calculated_fee'];
    } elseif ($manualDeliveryFee !== null) {
        $deliveryFee = $manualDeliveryFee;
    }

    $auditRequestId = $deliveryPricing['audit_request_id'] ?? null;
}

$totalsPayload = $payload['totals'] ?? [];
$discountAmount = isset($totalsPayload['discount']) ? max(0.0, (float)$totalsPayload['discount']) : 0.0;
$grandTotal = isset($totalsPayload['total'])
    ? (float)$totalsPayload['total']
    : max(0.0, $subtotal + $taxTotal + $deliveryFee - $discountAmount);

$paymentMethod = $payload['payment_method'] ?? 'mobile_money';
$customerName = trim((string)($payload['customer_name'] ?? '')) ?: null;
$customerPhone = trim((string)($payload['customer_phone'] ?? '')) ?: null;
$customerEmail = trim((string)($payload['customer_email'] ?? '')) ?: null;
$deliveryAddress = trim((string)($payload['delivery_address'] ?? '')) ?: null;
$deliveryInstructions = trim((string)($payload['delivery_instructions'] ?? '')) ?: null;
$notes = trim((string)($payload['notes'] ?? '')) ?: null;

$userId = $auth->getUserId() ?? 1;

try {
    $pdo->beginTransaction();

    $accountingService = new AccountingService($pdo);
    $salesService = new SalesService($pdo, $accountingService);

    $saleResult = $salesService->createSale([
        'external_id' => $payload['external_id'] ?? null,
        'user_id' => $userId,
        'customer_name' => $customerName,
        'customer_phone' => $customerPhone,
        'items' => array_map(fn($item) => [
            'product_id' => $item['product_id'],
            'qty' => $item['qty'],
            'price' => $item['price'],
            'tax_rate' => $item['tax_rate'],
        ], $normalizedItems),
        'totals' => [
            'subtotal' => $subtotal,
            'tax' => $taxTotal,
            'discount' => $discountAmount,
            'grand' => $grandTotal,
        ],
        'amount_paid' => 0.0,
        'change_amount' => 0.0,
        'payment_method' => $paymentMethod,
        'notes' => $notes,
    ]);

    if (($saleResult['success'] ?? false) !== true) {
        throw new RuntimeException($saleResult['message'] ?? 'Failed to create sale');
    }

    $orderId = $db->insert('orders', [
        'order_number' => $saleResult['sale_number'],
        'order_type' => $orderType,
        'customer_name' => $customerName,
        'customer_phone' => $customerPhone,
        'customer_email' => $customerEmail,
        'delivery_address' => $deliveryAddress,
        'delivery_instructions' => $deliveryInstructions,
        'subtotal' => $subtotal,
        'tax_amount' => $taxTotal,
        'discount_amount' => $discountAmount,
        'delivery_fee' => $deliveryFee,
        'total_amount' => $grandTotal,
        'payment_method' => $paymentMethod,
        'payment_status' => 'pending',
        'user_id' => $userId,
        'notes' => $notes,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    if (!$orderId) {
        throw new RuntimeException('Failed to create order record');
    }

    foreach ($normalizedItems as $item) {
        $db->insert('order_items', [
            'order_id' => $orderId,
            'product_id' => $item['product_id'],
            'product_name' => $item['name'],
            'quantity' => $item['qty'],
            'unit_price' => $item['price'],
            'total_price' => $item['line_subtotal'],
            'tax_rate' => $item['tax_rate'],
            'special_instructions' => null,
        ]);
    }

    if ($orderType === 'delivery') {
        $deliveryId = $db->insert('deliveries', [
            'order_id' => $orderId,
            'status' => 'pending',
            'delivery_address' => $deliveryAddress,
            'delivery_instructions' => $deliveryInstructions,
            'estimated_time' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if ($auditRequestId && $deliveryId && $pricingService) {
            $pricingService->attachAuditToOrder($auditRequestId, $orderId);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'order_number' => $saleResult['sale_number'],
        'total_amount' => $grandTotal,
        'delivery_pricing' => $deliveryPricing,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Customer ordering place_order error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
