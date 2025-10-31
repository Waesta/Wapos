<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance()->getConnection();

// Get ALL tables
$result = $db->query("SHOW TABLES");
$allTables = [];
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    $allTables[] = $row[0];
}

sort($allTables);

header('Content-Type: text/plain');
echo "=== ALL TABLES IN YOUR DATABASE ===\n\n";
echo "Total: " . count($allTables) . " tables\n\n";

foreach ($allTables as $table) {
    echo "- $table\n";
}

echo "\n\n=== CHECKING SPECIFIC TABLES ===\n\n";

$checkTables = [
    'users',
    'products', 
    'sales',
    'settings',
    'permission_modules',
    'stock_movements',
    'suppliers'
];

foreach ($checkTables as $table) {
    $exists = in_array($table, $allTables) ? '✓ EXISTS' : '✗ MISSING';
    echo "$exists - $table\n";
}
?>
