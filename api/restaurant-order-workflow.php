<?php
require_once '../includes/bootstrap.php';
require_once '../includes/schema/orders.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function markOrderPicked($db, array $data): array
{
    $orderId = isset($data['order_id']) ? (int)$data['order_id'] : 0;
    if ($orderId <= 0) {
        throw new Exception('Order ID is required.');
    }

    $order = $db->fetchOne('SELECT id, status, order_number, order_type, table_id FROM orders WHERE id = ?', [$orderId]);
    if (!$order) {
        throw new Exception('Order not found.');
    }

    if (!in_array($order['status'], ['ready', 'ready_for_pickup'], true)) {
        throw new Exception('Only ready orders can be picked/served.');
    }

    $db->beginTransaction();

    try {
        $db->update(
            'orders',
            [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
            ],
            'id = :id',
            ['id' => $orderId]
        );

        $db->commit();

        return [
            'success' => true,
            'order_id' => $orderId,
            'message' => 'Order marked as picked up',
        ];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$db = Database::getInstance();
ensureOrdersCompletedAtColumn($db);
ensureOrderMetaTable($db);
$action = $data['action'] ?? '';

try {
    switch ($action) {
        case 'place_order':
            $result = placeOrder($db, $data, $auth);
            break;
        case 'process_payment':
            $result = processPayment($db, $data, $auth);
            break;
        case 'print_receipt':
            $result = printReceipt($db, $data, $auth);
            break;
        case 'reprint_receipt':
            $result = reprintReceipt($db, $data, $auth);
            break;
        case 'update_item_status':
            $result = updateKitchenItemStatus($db, $data);
            break;
        case 'mark_order_picked':
            $result = markOrderPicked($db, $data);
            break;
        default:
            throw new Exception('Invalid action');
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Restaurant workflow error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function placeOrder($db, $data, $auth) {
    $db->beginTransaction();
    
    try {
        $orderType = $data['order_type'] ?? 'dine-in';
        $customerName = isset($data['customer_name']) ? trim((string)$data['customer_name']) : '';
        $customerPhone = isset($data['customer_phone']) ? trim((string)$data['customer_phone']) : '';

        if ($orderType !== 'dine-in') {
            if ($customerName === '' || $customerPhone === '') {
                throw new Exception('Customer name and phone are required for takeout and delivery orders.');
            }
        } else {
            $customerName = $customerName !== '' ? $customerName : null;
            $customerPhone = $customerPhone !== '' ? $customerPhone : null;
        }
        $deliveryLat = (isset($data['delivery_latitude']) && is_numeric($data['delivery_latitude']))
            ? (float)$data['delivery_latitude']
            : null;
        $deliveryLng = (isset($data['delivery_longitude']) && is_numeric($data['delivery_longitude']))
            ? (float)$data['delivery_longitude']
            : null;

        $subtotal = (float)($data['subtotal'] ?? ($data['totals']['subtotal'] ?? 0));
        $taxAmount = (float)($data['tax_amount'] ?? ($data['totals']['tax_amount'] ?? 0));
        $promotionDiscount = isset($data['promotion_discount'])
            ? (float)$data['promotion_discount']
            : (float)($data['totals']['promotion_discount'] ?? 0);
        $loyaltyDiscount = isset($data['loyalty_discount'])
            ? (float)$data['loyalty_discount']
            : (float)($data['totals']['loyalty_discount'] ?? 0);
        if ($loyaltyDiscount < 0) {
            $loyaltyDiscount = 0;
        }
        $discountAmount = max(0.0, $promotionDiscount + $loyaltyDiscount);
        $manualDeliveryFee = array_key_exists('delivery_fee', $data) && $data['delivery_fee'] !== ''
            ? (float)$data['delivery_fee']
            : null;
        $deliveryFee = $manualDeliveryFee ?? 0.0;
        $deliveryPricing = [
            'distance_km' => null,
            'base_fee' => null,
            'calculated_fee' => null,
            'zone' => null,
        ];

        $pricingService = null;
        $auditRequestId = null;

        if ($orderType === 'delivery') {
            $pricingService = new \App\Services\DeliveryPricingService($db);
            $deliveryPricing = $pricingService->calculateFee($data, $deliveryLat, $deliveryLng);

            if ($deliveryPricing['calculated_fee'] !== null) {
                $deliveryFee = $manualDeliveryFee !== null ? $manualDeliveryFee : (float)$deliveryPricing['calculated_fee'];
            }

            $auditRequestId = $deliveryPricing['audit_request_id'] ?? null;
        }

        $totalAmount = max(0, round($subtotal + $taxAmount + $deliveryFee - $discountAmount, 2));

        $promotionSummary = [];
        if (!empty($data['promotion_summary']) && is_array($data['promotion_summary'])) {
            $promotionSummary = $data['promotion_summary'];
        }

        $loyaltyPayload = null;
        if (!empty($data['loyalty_payload']) && is_array($data['loyalty_payload'])) {
            $loyaltyPayload = $data['loyalty_payload'];
        }

        // Generate order number
        $orderNumber = generateOrderNumber();
        
        // Insert order record
        $orderId = $db->insert('orders', [
            'order_number' => $orderNumber,
            'order_type' => $orderType,
            'table_id' => $data['table_id'] ?? null,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'delivery_address' => $data['delivery_address'] ?? null,
            'delivery_instructions' => $data['delivery_instructions'] ?? null,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'delivery_fee' => $deliveryFee,
            'total_amount' => $totalAmount,
            'payment_method' => $data['payment_method'] ?? null,
            'payment_status' => 'pending',
            'user_id' => $auth->getUserId(),
            'notes' => $data['notes'] ?? null,
            'status' => 'pending'
        ]);

        if (!$orderId) {
            throw new Exception('Failed to create order record');
        }

        if (!empty($promotionSummary)) {
            $db->insert('order_meta', [
                'order_id' => $orderId,
                'meta_key' => 'promotion_summary',
                'meta_value' => json_encode($promotionSummary),
            ]);
        }

        if ($loyaltyPayload !== null) {
            $db->insert('order_meta', [
                'order_id' => $orderId,
                'meta_key' => 'loyalty_payload',
                'meta_value' => json_encode($loyaltyPayload),
            ]);
        }

        // Ensure added_by column exists for waiter tracking
        try {
            $cols = $db->fetchAll("SHOW COLUMNS FROM order_items LIKE 'added_by'");
            if (empty($cols)) {
                $db->query("ALTER TABLE order_items ADD COLUMN added_by INT UNSIGNED NULL AFTER special_instructions, ADD COLUMN added_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER added_by");
            }
        } catch (Exception $e) { /* ignore */ }
        
        // Insert order items with waiter tracking
        $waiterId = $data['user_id'] ?? null;
        foreach ($data['items'] as $item) {
            $db->insert('order_items', [
                'order_id' => $orderId,
                'product_id' => $item['id'],
                'product_name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['price'],
                'modifiers_data' => json_encode($item['modifiers'] ?? []),
                'special_instructions' => $item['instructions'] ?? null,
                'total_price' => $item['total'],
                'added_by' => $waiterId,
                'added_at' => date('Y-m-d H:i:s')
            ]);
        }

        if ($orderType === 'delivery') {
            ensureDeliveryRecord($db, $orderId, $data, $deliveryPricing, $deliveryLat, $deliveryLng, $deliveryFee);
            if ($pricingService && $auditRequestId) {
                $pricingService->attachAuditToOrder($auditRequestId, $orderId);
            }
        }
        
        // Update table status if dine-in
        if ($orderType === 'dine-in' && !empty($data['table_id'])) {
            $db->query("UPDATE restaurant_tables SET status = 'occupied' WHERE id = ?", [$data['table_id']]);
        }
        
        $db->commit();
        
        // Handle automatic printing based on settings
        $printResults = handleAutomaticPrinting($db, $orderId, 'order_placed');
        
        return [
            'success' => true,
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'message' => 'Order placed successfully',
            'print_results' => $printResults,
            'delivery_fee' => $deliveryFee,
            'total_amount' => $totalAmount,
            'delivery_pricing' => array_merge($deliveryPricing, [
                'audit_request_id' => $auditRequestId,
            ]),
        ];
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        throw $e;
    }
}

function updateKitchenItemStatus($db, array $data): array
{
    $itemId = isset($data['item_id']) ? (int)$data['item_id'] : 0;
    $status = isset($data['status']) ? strtolower((string)$data['status']) : '';
    $validStatuses = ['pending', 'preparing', 'ready'];

    if ($itemId <= 0 || !in_array($status, $validStatuses, true)) {
        throw new Exception('Invalid kitchen item payload.');
    }

    $db->beginTransaction();

    try {
        $updated = $db->update(
            'order_items',
            ['status' => $status],
            'id = :id',
            ['id' => $itemId]
        );

        if (!$updated) {
            throw new Exception('Failed to update item status');
        }

        $orderItem = $db->fetchOne('SELECT order_id FROM order_items WHERE id = ?', [$itemId]);
        if ($orderItem) {
            $orderStats = $db->fetchOne(
                'SELECT COUNT(*) AS total_items, SUM(CASE WHEN status = "ready" THEN 1 ELSE 0 END) AS ready_items FROM order_items WHERE order_id = ?',
                [$orderItem['order_id']]
            );

            $newOrderStatus = 'pending';
            if ($orderStats['ready_items'] > 0 && $orderStats['ready_items'] < $orderStats['total_items']) {
                $newOrderStatus = 'preparing';
            } elseif ((int)$orderStats['ready_items'] === (int)$orderStats['total_items']) {
                $newOrderStatus = 'ready';
            }

            $db->update(
                'orders',
                ['status' => $newOrderStatus],
                'id = :id',
                ['id' => $orderItem['order_id']]
            );
        }

        $db->commit();

        return [
            'success' => true,
            'message' => 'Item status updated successfully'
        ];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        throw $e;
    }
}

function processPayment($db, $data, $auth) {
    $orderId = isset($data['order_id']) ? (int)$data['order_id'] : 0;
    if ($orderId <= 0) {
        throw new Exception('Order ID is required.');
    }

    $order = $db->fetchOne('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$order) {
        throw new Exception('Order not found');
    }

    if ($order['payment_status'] === 'paid') {
        throw new Exception('Order already paid');
    }

    $items = $db->fetchAll('SELECT * FROM order_items WHERE order_id = ?', [$orderId]);
    if (!$items) {
        throw new Exception('Order has no items to charge');
    }

    $pdo = $db->getConnection();
    ensureOrdersPaymentTrackingColumns($db);
    $billingService = new \App\Services\RestaurantBillingService($pdo);
    $billingService->ensureSchema();

    $paymentsInput = $data['payments'] ?? null;
    $globalTip = array_key_exists('tip_amount', $data) ? (float)$data['tip_amount'] : null;

    if (!is_array($paymentsInput) || empty($paymentsInput)) {
        $legacyMethod = $data['payment_method'] ?? null;
        if (!$legacyMethod) {
            throw new Exception('Payment method is required.');
        }

        $legacyPayment = [
            'payment_method' => $legacyMethod,
            'amount' => (float)($data['amount_paid'] ?? 0),
            'tip_amount' => isset($data['tip_amount']) ? (float)$data['tip_amount'] : 0.0,
            'metadata' => []
        ];

        if ($legacyMethod === 'cash') {
            if (isset($data['amount_received'])) {
                $legacyPayment['metadata']['amount_received'] = (float)$data['amount_received'];
            }
            if (isset($data['change_amount'])) {
                $legacyPayment['metadata']['change_amount'] = (float)$data['change_amount'];
            }
        } elseif ($legacyMethod === 'card') {
            if (!empty($data['card_last_four'])) {
                $legacyPayment['metadata']['card_last_four'] = substr(preg_replace('/\D/', '', (string)$data['card_last_four']), -4);
            }
            if (!empty($data['card_type'])) {
                $legacyPayment['metadata']['card_type'] = $data['card_type'];
            }
            if (!empty($data['auth_code'])) {
                $legacyPayment['metadata']['auth_code'] = $data['auth_code'];
            }
        } elseif ($legacyMethod === 'mobile_money') {
            if (!empty($data['mobile_provider'])) {
                $legacyPayment['metadata']['mobile_provider'] = $data['mobile_provider'];
            }
            if (!empty($data['mobile_number'])) {
                $legacyPayment['metadata']['mobile_number'] = $data['mobile_number'];
            }
            if (!empty($data['transaction_id'])) {
                $legacyPayment['metadata']['transaction_id'] = $data['transaction_id'];
            }
        } elseif ($legacyMethod === 'bank_transfer') {
            if (!empty($data['bank_name'])) {
                $legacyPayment['metadata']['bank_name'] = $data['bank_name'];
            }
            if (!empty($data['reference_number'])) {
                $legacyPayment['metadata']['reference_number'] = $data['reference_number'];
            }
        }

        if (!empty($data['room_booking_id'])) {
            $legacyPayment['room_booking_id'] = (int)$data['room_booking_id'];
        }
        if (!empty($data['room_charge_description'])) {
            $legacyPayment['room_charge_description'] = $data['room_charge_description'];
        }

        $paymentsInput = [$legacyPayment];
        $globalTip = $legacyPayment['tip_amount'];
    }

    if ($globalTip !== null && $globalTip < 0) {
        throw new Exception('Tip amount cannot be negative.');
    }

    if ($globalTip !== null) {
        foreach ($paymentsInput as $index => &$entry) {
            $entry['tip_amount'] = $index === 0 ? (float)$globalTip : 0.0;
        }
        unset($entry);
    }

    $normalizedPayments = [];
    $totalAmount = 0.0;
    $totalTip = 0.0;
    $roomChargePayment = null;

    foreach ($paymentsInput as $index => $payment) {
        $method = strtolower(trim((string)($payment['payment_method'] ?? '')));
        if ($method === '') {
            throw new Exception('Payment method is required for entry #' . ($index + 1));
        }

        $amount = isset($payment['amount']) ? (float)$payment['amount'] : 0.0;
        if ($amount < 0) {
            throw new Exception('Payment amount cannot be negative (entry #' . ($index + 1) . ').');
        }

        $tipAmount = isset($payment['tip_amount']) ? (float)$payment['tip_amount'] : 0.0;
        if ($tipAmount < 0) {
            throw new Exception('Tip amount cannot be negative (entry #' . ($index + 1) . ').');
        }

        $metadata = $payment['metadata'] ?? [];
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
        } elseif (!is_array($metadata)) {
            $metadata = [];
        }

        $recordedBy = isset($payment['recorded_by_user_id']) ? (int)$payment['recorded_by_user_id'] : (int)$auth->getUserId();

        if ($method === 'room_charge') {
            $roomBookingId = $payment['room_booking_id'] ?? ($data['room_booking_id'] ?? null);
            $roomBookingId = $roomBookingId !== null ? (int)$roomBookingId : null;

            if (!$roomBookingId || $roomBookingId <= 0) {
                throw new Exception('Room selection is required for room charge payments.');
            }

            if ($tipAmount > 0) {
                throw new Exception('Tips cannot be applied to room charge payments. Record the tip with another payment method.');
            }

            if ($roomChargePayment !== null) {
                throw new Exception('Only one room charge payment can be applied per order.');
            }

            if (count($paymentsInput) > 1) {
                throw new Exception('Room charge payments cannot be combined with other payment methods for the same order.');
            }

            $description = trim((string)($payment['room_charge_description'] ?? $data['room_charge_description'] ?? ''));
            $roomChargePayment = [
                'room_booking_id' => $roomBookingId,
                'description' => $description,
                'amount' => $amount,
            ];

            $metadata['room_booking_id'] = $roomBookingId;
            if ($description !== '') {
                $metadata['room_charge_description'] = $description;
            }
        }

        if ($method === 'cash') {
            if (isset($payment['amount_received'])) {
                $metadata['amount_received'] = (float)$payment['amount_received'];
            }
            if (isset($payment['change_amount'])) {
                $metadata['change_amount'] = (float)$payment['change_amount'];
            }
        } elseif ($method === 'card') {
            if (!empty($payment['card_last_four'])) {
                $metadata['card_last_four'] = substr(preg_replace('/\D/', '', (string)$payment['card_last_four']), -4);
            }
            if (!empty($payment['card_type'])) {
                $metadata['card_type'] = $payment['card_type'];
            }
            if (!empty($payment['auth_code'])) {
                $metadata['auth_code'] = $payment['auth_code'];
            }
        } elseif ($method === 'mobile_money') {
            if (!empty($payment['mobile_provider'])) {
                $metadata['mobile_provider'] = $payment['mobile_provider'];
            }
            if (!empty($payment['mobile_number'])) {
                $metadata['mobile_number'] = $payment['mobile_number'];
            }
            if (!empty($payment['transaction_id'])) {
                $metadata['transaction_id'] = $payment['transaction_id'];
            }
        } elseif ($method === 'bank_transfer') {
            if (!empty($payment['bank_name'])) {
                $metadata['bank_name'] = $payment['bank_name'];
            }
            if (!empty($payment['reference_number'])) {
                $metadata['reference_number'] = $payment['reference_number'];
            }
        }

        $totalAmount += $amount;
        $totalTip += $tipAmount;

        $normalizedPayments[] = [
            'payment_method' => $method,
            'amount' => round($amount, 2),
            'tip_amount' => round($tipAmount, 2),
            'metadata' => !empty($metadata) ? $metadata : null,
            'recorded_by_user_id' => $recordedBy,
        ];
    }

    if (empty($normalizedPayments)) {
        throw new Exception('No payments supplied.');
    }

    $orderTotal = (float)$order['total_amount'];
    $amountRounded = round($totalAmount, 2);
    $tipRounded = round($totalTip, 2);

    if ($amountRounded < $orderTotal - 0.01) {
        throw new Exception('Payments do not cover the order total. Please adjust the split amounts.');
    }

    $paymentStatus = ($amountRounded + 0.009 >= $orderTotal) ? 'paid' : 'partial';
    $paymentMethodLabel = count($normalizedPayments) > 1 ? 'split' : $normalizedPayments[0]['payment_method'];

    $transactionActive = false;
    try {
        $db->beginTransaction();
        $transactionActive = true;

        if ($roomChargePayment !== null) {
            if (abs($roomChargePayment['amount'] - $orderTotal) > 0.01) {
                throw new Exception('Room charge must cover the full order total.');
            }

            $accountingService = new \App\Services\AccountingService($pdo);
            $salesService = new \App\Services\SalesService($pdo, $accountingService);

            $saleItems = [];
            foreach ($items as $item) {
                $saleItems[] = [
                    'product_id' => (int)$item['product_id'],
                    'qty' => (float)$item['quantity'],
                    'price' => (float)$item['unit_price'],
                    'tax_rate' => 0,
                    'discount' => 0,
                ];
            }

            $saleResult = $salesService->createSale([
                'user_id' => $auth->getUserId(),
                'customer_name' => $order['customer_name'] ?? null,
                'customer_phone' => $order['customer_phone'] ?? null,
                'items' => $saleItems,
                'totals' => [
                    'subtotal' => (float)$order['subtotal'],
                    'tax' => (float)$order['tax_amount'],
                    'discount' => (float)$order['discount_amount'],
                    'grand' => (float)$order['total_amount'],
                ],
                'amount_paid' => 0.0,
                'change_amount' => 0.0,
                'payment_method' => 'room_charge',
                'room_booking_id' => $roomChargePayment['room_booking_id'],
                'room_charge_description' => $roomChargePayment['description'] !== '' ? $roomChargePayment['description'] : 'Restaurant order ' . $order['order_number'],
                'notes' => $data['notes'] ?? null,
            ]);

            if (!($saleResult['success'] ?? false)) {
                throw new Exception($saleResult['message'] ?? 'Unable to create room charge sale.');
            }
        }

        $billingTotals = $billingService->replaceOrderPayments($orderId, $normalizedPayments, $paymentMethodLabel, $paymentStatus);

        if ($paymentStatus === 'paid') {
            $db->query('UPDATE orders SET status = "preparing" WHERE id = ?', [$orderId]);
        }

        $db->commit();
        $transactionActive = false;
    } catch (Exception $e) {
        if ($transactionActive && $db->inTransaction()) {
            $db->rollback();
        }
        throw $e;
    }

    $printResults = $paymentStatus === 'paid' ? handleAutomaticPrinting($db, $orderId, 'payment_completed') : [];

    $tenderedTotal = 0.0;
    foreach ($normalizedPayments as $payment) {
        $metadata = $payment['metadata'] ?? [];
        if ($payment['payment_method'] === 'cash' && is_array($metadata) && isset($metadata['amount_received'])) {
            $tenderedTotal += (float)$metadata['amount_received'];
        } else {
            $tenderedTotal += $payment['amount'] + $payment['tip_amount'];
        }
    }

    $changeDue = max(0, round($tenderedTotal - ($orderTotal + $tipRounded), 2));

    return [
        'success' => true,
        'order_id' => $orderId,
        'message' => 'Payment processed successfully',
        'payment_status' => $paymentStatus,
        'amount_paid' => $amountRounded,
        'tip_amount' => $tipRounded,
        'balance_remaining' => max(0, round($orderTotal - $amountRounded, 2)),
        'total_tendered' => round($tenderedTotal, 2),
        'change_due' => $changeDue,
        'print_results' => $printResults,
    ];
}

function printReceipt($db, $data, $auth) {
    $orderId = $data['order_id'];
    $receiptType = $data['receipt_type']; // 'kitchen', 'invoice', 'receipt'
    
    $order = $db->fetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    $printResult = printSpecificReceipt($db, $orderId, $receiptType);
    
    return [
        'success' => true,
        'message' => ucfirst($receiptType) . ' printed successfully',
        'print_result' => $printResult
    ];
}

function reprintReceipt($db, $data, $auth) {
    $orderId = $data['order_id'];
    $receiptType = $data['receipt_type'];
    
    // Log reprint action
    $db->insert('print_log', [
        'order_id' => $orderId,
        'receipt_type' => $receiptType,
        'action' => 'reprint',
        'user_id' => $auth->getUserId(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $printResult = printSpecificReceipt($db, $orderId, $receiptType);
    
    return [
        'success' => true,
        'message' => ucfirst($receiptType) . ' reprinted successfully',
        'print_result' => $printResult
    ];
}

function handleAutomaticPrinting($db, $orderId, $trigger) {
    $settings = getSettings($db);
    $results = [];
    
    switch ($trigger) {
        case 'order_placed':
            // Auto-print kitchen order if enabled
            if (($settings['kitchen_auto_print'] ?? '1') === '1') {
                $results['kitchen'] = printSpecificReceipt($db, $orderId, 'kitchen');
            }
            
            // Auto-print customer invoice if enabled
            if (($settings['customer_auto_print_invoice'] ?? '0') === '1') {
                $results['invoice'] = printSpecificReceipt($db, $orderId, 'invoice');
            }
            break;
            
        case 'payment_completed':
            // Auto-print customer receipt if enabled
            if (($settings['customer_auto_print_receipt'] ?? '1') === '1') {
                $results['receipt'] = printSpecificReceipt($db, $orderId, 'receipt');
            }
            break;
    }
    
    return $results;
}

function printSpecificReceipt($db, $orderId, $receiptType) {
    $settings = getSettings($db);
    
    try {
        error_log("Attempting to print $receiptType for order $orderId");
        
        switch ($receiptType) {
            case 'kitchen':
                $result = printKitchenReceipt($db, $orderId, $settings);
                break;
            case 'invoice':
                $result = printCustomerInvoice($db, $orderId, $settings);
                break;
            case 'receipt':
                $result = printCustomerReceipt($db, $orderId, $settings);
                break;
            default:
                throw new Exception('Invalid receipt type: ' . $receiptType);
        }
        
        error_log("Print result for $receiptType: " . json_encode($result));
        return $result;
        
    } catch (Exception $e) {
        // Log print error
        error_log("Print error for order $orderId, type $receiptType: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        // If fallback to screen is enabled, return screen display option
        if (($settings['fallback_to_screen'] ?? '1') === '1') {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'fallback_url' => getPrintUrl($receiptType, $orderId)
            ];
        }
        
        throw $e;
    }
}

function printKitchenReceipt($db, $orderId, $settings) {
    // Check if kitchen printer is enabled
    if (($settings['kitchen_printer_enabled'] ?? '1') !== '1') {
        return ['success' => false, 'message' => 'Kitchen printer disabled'];
    }
    
    // Get order items and check if any need kitchen printing
    $items = $db->fetchAll("
        SELECT oi.*, COALESCE(p.category_id, 1) as category_id 
        FROM order_items oi 
        LEFT JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
    ", [$orderId]);
    
    // If no kitchen zones configured, print all items
    $kitchenZones = $settings['kitchen_zones'] ?? '';
    if (empty($kitchenZones)) {
        $hasKitchenItems = count($items) > 0;
    } else {
        $kitchenZonesArray = array_filter(explode(',', $kitchenZones));
        $hasKitchenItems = false;
        
        foreach ($items as $item) {
            if (empty($kitchenZonesArray) || in_array($item['category_id'], $kitchenZonesArray)) {
                $hasKitchenItems = true;
                break;
            }
        }
    }
    
    if (!$hasKitchenItems) {
        return ['success' => false, 'message' => 'No kitchen items in order'];
    }
    
    // Simulate printing (in real implementation, you'd send to actual printer)
    $copies = intval($settings['kitchen_print_copies'] ?? 1);
    
    // Try to log print action (create table if it doesn't exist)
    try {
        // Check if print_log table exists, create if not
        $tableExists = $db->fetchOne("SHOW TABLES LIKE 'print_log'");
        if (!$tableExists) {
            $db->query("
                CREATE TABLE IF NOT EXISTS print_log (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    order_id INT UNSIGNED NOT NULL,
                    receipt_type ENUM('kitchen', 'invoice', 'receipt', 'bar') NOT NULL,
                    action ENUM('print', 'reprint', 'failed') NOT NULL,
                    copies INT DEFAULT 1,
                    printer_name VARCHAR(100),
                    error_message TEXT,
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
            ");
        }
        
        $db->insert('print_log', [
            'order_id' => $orderId,
            'receipt_type' => 'kitchen',
            'action' => 'print',
            'copies' => $copies,
            'printer_name' => $settings['kitchen_printer_name'] ?? 'Kitchen Printer',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        // Log creation failed, but continue with printing
        error_log('Failed to log print action: ' . $e->getMessage());
    }
    
    return [
        'success' => true,
        'message' => "Kitchen receipt printed ($copies copies)",
        'printer' => $settings['kitchen_printer_name'] ?? 'Kitchen Printer',
        'url' => getPrintUrl('kitchen', $orderId)
    ];
}

function printCustomerInvoice($db, $orderId, $settings) {
    if (($settings['customer_printer_enabled'] ?? '1') !== '1') {
        return ['success' => false, 'message' => 'Customer printer disabled'];
    }
    
    // Try to log print action
    try {
        $db->insert('print_log', [
            'order_id' => $orderId,
            'receipt_type' => 'invoice',
            'action' => 'print',
            'printer_name' => $settings['customer_printer_name'] ?? 'Customer Printer',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        // Log creation failed, but continue with printing
        error_log('Failed to log invoice print action: ' . $e->getMessage());
    }
    
    return [
        'success' => true,
        'message' => 'Customer invoice printed',
        'printer' => $settings['customer_printer_name'] ?? 'Customer Printer',
        'url' => getPrintUrl('invoice', $orderId)
    ];
}

function printCustomerReceipt($db, $orderId, $settings) {
    if (($settings['customer_printer_enabled'] ?? '1') !== '1') {
        return ['success' => false, 'message' => 'Customer printer disabled'];
    }
    
    // Check if order is paid
    $order = $db->fetchOne("SELECT payment_status FROM orders WHERE id = ?", [$orderId]);
    if (!$order || $order['payment_status'] !== 'paid') {
        return ['success' => false, 'message' => 'Order not paid yet'];
    }
    
    // Try to log print action
    try {
        $db->insert('print_log', [
            'order_id' => $orderId,
            'receipt_type' => 'receipt',
            'action' => 'print',
            'printer_name' => $settings['customer_printer_name'] ?? 'Customer Printer',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        // Log creation failed, but continue with printing
        error_log('Failed to log receipt print action: ' . $e->getMessage());
    }
    
    return [
        'success' => true,
        'message' => 'Customer receipt printed',
        'printer' => $settings['customer_printer_name'] ?? 'Customer Printer',
        'url' => getPrintUrl('receipt', $orderId)
    ];
}

function getPrintUrl($receiptType, $orderId) {
    switch ($receiptType) {
        case 'kitchen':
            return "print-kitchen-order.php?id=$orderId";
        case 'invoice':
            return "print-customer-invoice.php?id=$orderId";
        case 'receipt':
            return "print-customer-receipt.php?id=$orderId";
        default:
            return null;
    }
}

function getSettings($db) {
    $settingsRaw = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    foreach ($settingsRaw as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    return $settings;
}

function ensureDeliveryRecord($db, int $orderId, array $payload, array $pricing, ?float $lat, ?float $lng, float $deliveryFee): void
{
    if (!deliveryTableExists($db)) {
        return;
    }

    $record = $db->fetchOne("SELECT id FROM deliveries WHERE order_id = ?", [$orderId]);
    $data = [
        'delivery_address' => $payload['delivery_address'] ?? null,
        'delivery_instructions' => $payload['delivery_instructions'] ?? null,
        'status' => 'pending'
    ];

    if (deliveryColumnExists($db, 'delivery_fee')) {
        $data['delivery_fee'] = $deliveryFee;
    }

    if ($pricing['distance_km'] !== null && deliveryColumnExists($db, 'estimated_distance_km')) {
        $data['estimated_distance_km'] = $pricing['distance_km'];
    }

    if ($lat !== null && deliveryColumnExists($db, 'delivery_latitude')) {
        $data['delivery_latitude'] = $lat;
    }

    if ($lng !== null && deliveryColumnExists($db, 'delivery_longitude')) {
        $data['delivery_longitude'] = $lng;
    }

    if (!empty($pricing['zone']['id']) && deliveryColumnExists($db, 'delivery_zone_id')) {
        $data['delivery_zone_id'] = $pricing['zone']['id'];
    }

    if ($record) {
        if (deliveryColumnExists($db, 'updated_at')) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        $db->update('deliveries', $data, 'id = :id', ['id' => $record['id']]);
    } else {
        $data['order_id'] = $orderId;
        if (!isset($data['status'])) {
            $data['status'] = 'pending';
        }
        if (deliveryColumnExists($db, 'created_at')) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        $db->insert('deliveries', $data);
    }
}

function deliveryTableExists($db): bool
{
    static $cache;
    if ($cache !== null) {
        return $cache;
    }

    $result = $db->fetchOne("SHOW TABLES LIKE ?", ['deliveries']);
    $cache = (bool)$result;
    return $cache;
}

function deliveryColumnExists($db, string $column): bool
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    if (!deliveryTableExists($db)) {
        $cache[$column] = false;
        return false;
    }

    $result = $db->fetchOne("SHOW COLUMNS FROM deliveries LIKE ?", [$column]);
    $cache[$column] = (bool)$result;
    return $cache[$column];
}

function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}
?>
