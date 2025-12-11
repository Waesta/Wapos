<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$db = Database::getInstance();

try {
    $db->beginTransaction();
    
    // Generate order number
    $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Create order
    $orderId = $db->insert('orders', [
        'order_number' => $orderNumber,
        'order_type' => $data['order_type'],
        'table_id' => $data['table_id'] ?? null,
        'customer_name' => $data['customer_name'] ?? null,
        'customer_phone' => $data['customer_phone'] ?? null,
        'subtotal' => $data['subtotal'],
        'tax_amount' => $data['tax_amount'],
        'total_amount' => $data['total_amount'],
        'payment_method' => $data['payment_method'],
        'payment_status' => 'pending',
        'status' => 'pending',
        'user_id' => $auth->getUserId()
    ]);
    
    if (!$orderId) {
        throw new Exception('Failed to create order');
    }
    
    // Ensure added_by column exists
    try {
        $cols = $db->fetchAll("SHOW COLUMNS FROM order_items LIKE 'added_by'");
        if (empty($cols)) {
            $db->query("ALTER TABLE order_items ADD COLUMN added_by INT UNSIGNED NULL AFTER special_instructions, ADD COLUMN added_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER added_by");
        }
    } catch (Exception $e) { /* ignore */ }
    
    // Insert order items
    $waiterId = $auth->getUserId();
    foreach ($data['items'] as $item) {
        $db->insert('order_items', [
            'order_id' => $orderId,
            'product_id' => $item['id'],
            'product_name' => $item['name'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['price'],
            'modifiers_data' => json_encode($item['modifiers']),
            'special_instructions' => $item['instructions'] ?? null,
            'status' => 'pending',
            'total_price' => $item['total'],
            'added_by' => $waiterId,
            'added_at' => date('Y-m-d H:i:s')
        ]);
        
        // Update stock
        $db->query(
            "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?",
            [$item['quantity'], $item['id']]
        );
    }
    
    // Update table status if dine-in
    if ($data['table_id']) {
        $db->update('restaurant_tables',
            ['status' => 'occupied'],
            'id = :id',
            ['id' => $data['table_id']]
        );
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'message' => 'Order submitted successfully'
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
