<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance()->getConnection();

header('Content-Type: text/plain');
echo "=== CHECKING FOR MISSING TABLES AND COLUMNS ===\n\n";

// Get all existing tables
$result = $db->query("SHOW TABLES");
$existingTables = [];
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    $existingTables[] = $row[0];
}

// Tables that are being referenced in errors
$requiredTables = [
    'void_reason_codes',
    'void_settings',
    'goods_received_notes',
    'journal_lines'
];

echo "MISSING TABLES:\n";
echo "---------------\n";
$missingTables = [];
foreach ($requiredTables as $table) {
    if (!in_array($table, $existingTables)) {
        echo "✗ MISSING: $table\n";
        $missingTables[] = $table;
    } else {
        echo "✓ EXISTS: $table\n";
    }
}

echo "\n\nCHECKING COLUMNS:\n";
echo "-----------------\n";

// Check riders table for location columns
if (in_array('riders', $existingTables)) {
    echo "\nriders table:\n";
    $result = $db->query("SHOW COLUMNS FROM riders");
    $columns = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    
    $requiredColumns = ['current_latitude', 'current_longitude', 'last_location_update'];
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $columns)) {
            echo "  ✗ MISSING: $col\n";
        } else {
            echo "  ✓ EXISTS: $col\n";
        }
    }
} else {
    echo "\n✗ riders table doesn't exist\n";
}

// Check sales table for order_source column
if (in_array('sales', $existingTables)) {
    echo "\nsales table:\n";
    $result = $db->query("SHOW COLUMNS FROM sales");
    $columns = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    
    if (!in_array('order_source', $columns)) {
        echo "  ✗ MISSING: order_source\n";
    } else {
        echo "  ✓ EXISTS: order_source\n";
    }
} else {
    echo "\n✗ sales table doesn't exist\n";
}

// Check orders table for order_source column
if (in_array('orders', $existingTables)) {
    echo "\norders table:\n";
    $result = $db->query("SHOW COLUMNS FROM orders");
    $columns = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    
    if (!in_array('order_source', $columns)) {
        echo "  ✗ MISSING: order_source\n";
    } else {
        echo "  ✓ EXISTS: order_source\n";
    }
}

echo "\n\n=== SUMMARY ===\n";
if (count($missingTables) > 0) {
    echo "Missing " . count($missingTables) . " tables\n";
    echo "Action needed: Create migration to add missing components\n";
} else {
    echo "All required tables exist!\n";
    echo "Only column additions may be needed.\n";
}
?>
