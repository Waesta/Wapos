<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
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
        // Generate order number
        $orderNumber = generateOrderNumber();
        
        // Insert order record
        $orderId = $db->insert('orders', [
            'order_number' => $orderNumber,
            'order_type' => $data['order_type'] ?? 'dine-in',
            'table_id' => $data['table_id'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'delivery_address' => $data['delivery_address'] ?? null,
            'delivery_instructions' => $data['delivery_instructions'] ?? null,
            'subtotal' => $data['subtotal'],
            'tax_amount' => $data['tax_amount'],
            'discount_amount' => $data['discount_amount'] ?? 0,
            'delivery_fee' => $data['delivery_fee'] ?? 0,
            'total_amount' => $data['total_amount'],
            'payment_method' => $data['payment_method'] ?? null,
            'payment_status' => 'pending',
            'user_id' => $auth->getUserId(),
            'notes' => $data['notes'] ?? null,
            'status' => 'pending'
        ]);
        
        if (!$orderId) {
            throw new Exception('Failed to create order record');
        }
        
        // Insert order items
        foreach ($data['items'] as $item) {
            $db->insert('order_items', [
                'order_id' => $orderId,
                'product_id' => $item['id'],
                'product_name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['price'],
                'modifiers_data' => json_encode($item['modifiers'] ?? []),
                'special_instructions' => $item['instructions'] ?? null,
                'total_price' => $item['total']
            ]);
        }
        
        // Update table status if dine-in
        if ($data['order_type'] === 'dine-in' && !empty($data['table_id'])) {
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
            'print_results' => $printResults
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function processPayment($db, $data, $auth) {
    $orderId = $data['order_id'];
    $paymentMethod = $data['payment_method'];
    $amountPaid = $data['amount_paid'];
    
    // Get order details
    $order = $db->fetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    if ($order['payment_status'] === 'paid') {
        throw new Exception('Order already paid');
    }
    
    // Update order with payment information
    $db->query("
        UPDATE orders 
        SET payment_method = ?, 
            payment_status = 'paid', 
            status = 'preparing',
            updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ", [$paymentMethod, $orderId]);
    
    // Handle automatic printing for payment completion
    $printResults = handleAutomaticPrinting($db, $orderId, 'payment_completed');
    
    return [
        'success' => true,
        'order_id' => $orderId,
        'message' => 'Payment processed successfully',
        'print_results' => $printResults
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

function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}
?>
