<?php
require_once '../includes/bootstrap.php';
$auth->requireLogin();

header('Content-Type: application/json');

$db = Database::getInstance();
$poId = $_GET['po_id'] ?? 0;

try {
    $po = $db->fetchOne("SELECT supplier_id FROM purchase_orders WHERE id = ?", [$poId]);
    
    if (!$po) {
        echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
        exit;
    }
    
    $items = $db->fetchAll("
        SELECT poi.*, p.name as product_name, p.sku
        FROM purchase_order_items poi
        JOIN products p ON poi.product_id = p.id
        WHERE poi.purchase_order_id = ?
    ", [$poId]);
    
    echo json_encode([
        'success' => true,
        'supplier_id' => $po['supplier_id'],
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
