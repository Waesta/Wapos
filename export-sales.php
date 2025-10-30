<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$paymentMethod = $_GET['payment_method'] ?? '';

$sql = "
    SELECT 
        s.id,
        s.sale_number,
        s.customer_name,
        s.total_amount,
        s.payment_method,
        s.created_at,
        u.full_name as cashier_name,
        (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
";
$params = [$dateFrom, $dateTo];

if (!empty($paymentMethod)) {
    $sql .= " AND s.payment_method = ?";
    $params[] = $paymentMethod;
}

$sql .= " ORDER BY s.created_at DESC";

$sales = $db->fetchAll($sql, $params);

$filename = 'sales_export_' . $dateFrom . '_to_' . $dateTo . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$fp = fopen('php://output', 'w');

// Header row
fputcsv($fp, [
    'Date',
    'Sale Number',
    'Customer',
    'Items',
    'Total Amount',
    'Payment Method',
    'Cashier'
]);

foreach ($sales as $row) {
    fputcsv($fp, [
        $row['created_at'],
        $row['sale_number'],
        $row['customer_name'] ?: 'Walk-in',
        $row['item_count'],
        number_format((float)$row['total_amount'], 2, '.', ''),
        ucfirst(str_replace('_', ' ', $row['payment_method'])),
        $row['cashier_name']
    ]);
}

fclose($fp);
exit;
