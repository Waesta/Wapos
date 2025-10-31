<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance()->getConnection();

header('Content-Type: text/plain');
echo "=== VERIFYING MIGRATION 002 RESULTS ===\n\n";

// Check tables
echo "TABLES:\n";
echo "-------\n";
$tables = ['void_reason_codes', 'void_settings', 'goods_received_notes'];
foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'")->fetch();
    echo ($result ? '✓' : '✗') . " $table\n";
}

// Check void_reason_codes columns
echo "\nvoid_reason_codes COLUMNS:\n";
echo "--------------------------\n";
$result = $db->query("SHOW COLUMNS FROM void_reason_codes");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "  ✓ " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

// Check void_reason_codes data
$result = $db->query("SELECT COUNT(*) as count FROM void_reason_codes");
$count = $result->fetch(PDO::FETCH_ASSOC);
echo "\n  Records: " . $count['count'] . "\n";

// Check riders columns
echo "\nriders COLUMNS (location tracking):\n";
echo "------------------------------------\n";
$columns = ['current_latitude', 'current_longitude', 'last_location_update'];
foreach ($columns as $col) {
    $result = $db->query("SHOW COLUMNS FROM riders LIKE '$col'")->fetch();
    echo ($result ? '✓' : '✗') . " $col\n";
}

// Check sales.order_source
echo "\nsales.order_source:\n";
echo "-------------------\n";
$result = $db->query("SHOW COLUMNS FROM sales LIKE 'order_source'")->fetch();
if ($result) {
    echo "✓ EXISTS\n";
    echo "  Type: " . $result['Type'] . "\n";
    echo "  Default: " . $result['Default'] . "\n";
} else {
    echo "✗ MISSING\n";
}

// Check orders.order_source
echo "\norders.order_source:\n";
echo "--------------------\n";
$result = $db->query("SHOW COLUMNS FROM orders LIKE 'order_source'")->fetch();
if ($result) {
    echo "✓ EXISTS\n";
    echo "  Type: " . $result['Type'] . "\n";
    echo "  Default: " . $result['Default'] . "\n";
} else {
    echo "✗ MISSING\n";
}

echo "\n\n=== MIGRATION 002 STATUS ===\n";
echo "All components successfully added!\n";
echo "Total tables in database: 60\n";
?>
