<?php
use App\Services\DeliveryPricingService;

if (empty($payload['items']) || !is_array($payload['items'])) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty']);
    return;
}

$orderType = $payload['order_type'] ?? 'delivery';
$itemsInput = $payload['items'];

$normalizedItems = [];
$subtotal = 0.0;
$taxTotal = 0.0;

foreach ($itemsInput as $entry) {
    $productId = isset($entry['product_id']) ? (int)$entry['product_id'] : 0;
    $qty = isset($entry['qty']) ? (int)$entry['qty'] : 0;
    if ($productId <= 0 || $qty <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid cart item']);
        return;
    }

    $product = $db->fetchOne('SELECT id, name, price, tax_rate FROM products WHERE id = ? LIMIT 1', [$productId]);
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'One of the products is no longer available.']);
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

if ($orderType === 'delivery') {
    $deliveryLat = isset($payload['delivery_latitude']) ? (float)$payload['delivery_latitude'] : null;
    $deliveryLng = isset($payload['delivery_longitude']) ? (float)$payload['delivery_longitude'] : null;

    $pricingService = new DeliveryPricingService($db);
    $deliveryPricing = $pricingService->calculateFee([
        'order_type' => $orderType,
        'items' => $normalizedItems,
        'totals' => [
            'subtotal' => $subtotal,
            'tax' => $taxTotal,
        ],
        'delivery_fee' => $payload['delivery_fee'] ?? null,
    ], $deliveryLat, $deliveryLng);

    if (isset($deliveryPricing['calculated_fee'])) {
        $deliveryFee = (float)$deliveryPricing['calculated_fee'];
    }
}

$discountAmount = isset($payload['discount']) ? max(0.0, (float)$payload['discount']) : 0.0;
$grandTotal = max(0.0, $subtotal + $taxTotal + $deliveryFee - $discountAmount);

$totals = [
    'subtotal' => round($subtotal, 2),
    'tax' => round($taxTotal, 2),
    'delivery_fee' => round($deliveryFee, 2),
    'discount' => round($discountAmount, 2),
    'total' => round($grandTotal, 2),
];

echo json_encode([
    'success' => true,
    'items' => $normalizedItems,
    'totals' => $totals,
    'delivery_pricing' => $deliveryPricing,
]);
