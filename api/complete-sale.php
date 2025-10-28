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
    
    // Post accounting journals
    // Helpers
    $getAccountId = function(string $code) use ($db) {
        $acct = $db->fetchOne("SELECT id FROM accounts WHERE code = ?", [$code]);
        if ($acct && isset($acct['id'])) { return (int)$acct['id']; }
        // fallback: create minimal account if missing
        $db->insert('accounts', [
            'code' => $code,
            'name' => $code,
            'type' => in_array($code, ['1000','1100','1200','1300']) ? 'ASSET' : (in_array($code,['2000','2100']) ? 'LIABILITY' : (in_array($code,['4000','4100']) ? 'REVENUE' : 'EXPENSE')),
            'is_active' => 1
        ]);
        $acct = $db->fetchOne("SELECT id FROM accounts WHERE code = ?", [$code]);
        return (int)($acct['id'] ?? 0);
    };
    
    $netSales = ($data['subtotal'] ?? 0) - ($data['discount_amount'] ?? 0);
    $taxAmount = $data['tax_amount'] ?? 0;
    $totalAmount = $data['total_amount'] ?? 0;
    $change = $data['change_amount'] ?? 0;
    $amountPaid = $data['amount_paid'] ?? 0;
    $cashReceived = max(0, $amountPaid - $change);
    
    // Compute COGS from current product cost_price
    $cogs = 0.0;
    foreach ($data['items'] as $item) {
        $p = $db->fetchOne("SELECT cost_price FROM products WHERE id = ?", [$item['id']]);
        $cost = isset($p['cost_price']) ? (float)$p['cost_price'] : 0.0;
        $cogs += $cost * (float)$item['quantity'];
    }
    
    // Journal entry header
    $journalId = $db->insert('journal_entries', [
        'reference' => $saleNumber,
        'description' => 'POS Sale '
            . ($data['customer_name'] ? ('to ' . $data['customer_name']) : ''),
        'entry_date' => date('Y-m-d'),
        'total_amount' => $totalAmount,
        'created_by' => $auth->getUserId(),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Determine tender account
    $tender = strtolower($data['payment_method'] ?? 'cash');
    $cashAcct = ($tender === 'cash') ? '1000' : '1100';
    
    // Lines
    if ($cashReceived > 0) {
        $db->insert('journal_lines', [
            'journal_entry_id' => $journalId,
            'account_id' => $getAccountId($cashAcct),
            'debit_amount' => $cashReceived,
            'credit_amount' => 0,
            'description' => 'Tender received'
        ]);
    }
    if ($netSales > 0) {
        $db->insert('journal_lines', [
            'journal_entry_id' => $journalId,
            'account_id' => $getAccountId('4000'),
            'debit_amount' => 0,
            'credit_amount' => $netSales,
            'description' => 'Sales revenue'
        ]);
    }
    if ($taxAmount > 0) {
        $db->insert('journal_lines', [
            'journal_entry_id' => $journalId,
            'account_id' => $getAccountId('2100'),
            'debit_amount' => 0,
            'credit_amount' => $taxAmount,
            'description' => 'Sales tax payable'
        ]);
    }
    if ($cogs > 0) {
        $db->insert('journal_lines', [
            'journal_entry_id' => $journalId,
            'account_id' => $getAccountId('5000'),
            'debit_amount' => $cogs,
            'credit_amount' => 0,
            'description' => 'COGS'
        ]);
        $db->insert('journal_lines', [
            'journal_entry_id' => $journalId,
            'account_id' => $getAccountId('1300'),
            'debit_amount' => 0,
            'credit_amount' => $cogs,
            'description' => 'Inventory reduction'
        ]);
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
