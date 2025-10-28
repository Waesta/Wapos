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

if (!$data || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$db = Database::getInstance();

try {
    $db->beginTransaction();
    
    // Generate sale number
    $saleNumber = generateSaleNumber();
    
    // Insert sale record
    $saleId = $db->insert('sales', [
        'sale_number' => $saleNumber,
        'user_id' => $auth->getUserId(),
        'customer_name' => $data['customer_name'] ?? null,
        'customer_phone' => $data['customer_phone'] ?? null,
        'subtotal' => $data['subtotal'],
        'tax_amount' => $data['tax_amount'],
        'discount_amount' => $data['discount_amount'] ?? 0,
        'total_amount' => $data['total_amount'],
        'amount_paid' => $data['amount_paid'],
        'change_amount' => $data['change_amount'] ?? 0,
        'payment_method' => $data['payment_method'],
        'notes' => $data['notes'] ?? null
    ]);
    
    if (!$saleId) {
        throw new Exception('Failed to create sale record');
    }
    
    // Insert sale items and update stock
    foreach ($data['items'] as $item) {
        // Insert sale item
        $db->insert('sale_items', [
            'sale_id' => $saleId,
            'product_id' => $item['id'],
            'product_name' => $item['name'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['price'],
            'tax_rate' => $item['tax_rate'] ?? 0,
            'discount_amount' => 0,
            'total_price' => $item['total']
        ]);
        
        // Update product stock
        $db->query(
            "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?",
            [$item['quantity'], $item['id']]
        );
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'sale_id' => $saleId,
        'sale_number' => $saleNumber,
        'message' => 'Sale completed successfully'
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    error_log("Sale completion error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
