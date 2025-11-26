<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

try {
    $auth->requireRole(['admin', 'manager', 'inventory_manager']);

    $poId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($poId <= 0) {
        throw new Exception('Invalid purchase order identifier.');
    }

    $db = Database::getInstance();

    $order = $db->fetchOne(
        "SELECT po.*, s.name AS supplier_name, s.contact_person, s.phone AS supplier_phone,
                u.full_name AS created_by_name
         FROM purchase_orders po
         LEFT JOIN suppliers s ON po.supplier_id = s.id
         LEFT JOIN users u ON po.created_by = u.id
         WHERE po.id = ?",
        [$poId]
    );

    if (!$order) {
        throw new Exception('Purchase order not found.');
    }

    $items = $db->fetchAll(
        "SELECT poi.*, p.name AS product_name
         FROM purchase_order_items poi
         LEFT JOIN products p ON poi.product_id = p.id
         WHERE poi.purchase_order_id = ?
         ORDER BY poi.id",
        [$poId]
    );

    echo json_encode([
        'success' => true,
        'data' => [
            'order' => $order,
            'items' => $items ?: [],
        ],
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
