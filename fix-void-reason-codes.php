<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance()->getConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix void_reason_codes Table - WAPOS</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { padding: 20px; background: #f8f9fa; }
        .log { background: #000; color: #0f0; padding: 20px; border-radius: 5px; font-family: monospace; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
    </style>
</head>
<body>
<div class='container'>
    <h1>Fix void_reason_codes Table</h1>
    <div class='log'>";

try {
    // Check current columns
    echo "<div class='warning'>Checking current columns...</div>";
    $result = $db->query("SHOW COLUMNS FROM void_reason_codes");
    $columns = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
        echo "<div class='success'>  ✓ " . $row['Field'] . "</div>";
    }
    
    echo "<br>";
    
    // Add missing columns
    $missingColumns = [];
    
    if (!in_array('display_name', $columns)) {
        echo "<div class='warning'>Adding display_name column...</div>";
        $db->exec("ALTER TABLE void_reason_codes ADD COLUMN display_name VARCHAR(100) NOT NULL AFTER code");
        echo "<div class='success'>✓ Added display_name</div>";
        $missingColumns[] = 'display_name';
    } else {
        echo "<div class='success'>✓ display_name already exists</div>";
    }
    
    if (!in_array('display_order', $columns)) {
        echo "<div class='warning'>Adding display_order column...</div>";
        $db->exec("ALTER TABLE void_reason_codes ADD COLUMN display_order INT DEFAULT 0 AFTER requires_approval");
        echo "<div class='success'>✓ Added display_order</div>";
        $missingColumns[] = 'display_order';
    } else {
        echo "<div class='success'>✓ display_order already exists</div>";
    }
    
    if (!in_array('requires_manager_approval', $columns)) {
        echo "<div class='warning'>Adding requires_manager_approval column...</div>";
        $db->exec("ALTER TABLE void_reason_codes ADD COLUMN requires_manager_approval TINYINT(1) DEFAULT 0 AFTER requires_approval");
        echo "<div class='success'>✓ Added requires_manager_approval</div>";
        $missingColumns[] = 'requires_manager_approval';
    } else {
        echo "<div class='success'>✓ requires_manager_approval already exists</div>";
    }
    
    echo "<br>";
    
    // Update existing records if columns were added
    if (count($missingColumns) > 0) {
        echo "<div class='warning'>Updating existing records...</div>";
        
        $updates = [
            ['CUSTOMER_REQUEST', 'Customer Request', 0, 1],
            ['WRONG_ORDER', 'Wrong Order', 0, 2],
            ['PAYMENT_ISSUE', 'Payment Issue', 1, 3],
            ['DUPLICATE', 'Duplicate Order', 0, 4],
            ['OUT_OF_STOCK', 'Out of Stock', 0, 5],
            ['PRICING_ERROR', 'Pricing Error', 1, 6],
            ['KITCHEN_ERROR', 'Kitchen Error', 0, 7],
            ['CUSTOMER_NO_SHOW', 'Customer No-Show', 0, 8],
            ['MANAGER_OVERRIDE', 'Manager Override', 1, 9],
            ['OTHER', 'Other Reason', 1, 10]
        ];
        
        foreach ($updates as $update) {
            $stmt = $db->prepare("
                UPDATE void_reason_codes 
                SET display_name = ?, 
                    requires_manager_approval = ?, 
                    display_order = ? 
                WHERE code = ?
            ");
            $stmt->execute([$update[1], $update[2], $update[3], $update[0]]);
            echo "<div class='success'>  ✓ Updated: {$update[0]}</div>";
        }
    }
    
    echo "<br>";
    echo "<div class='success'>========================================</div>";
    echo "<div class='success'>✓ void_reason_codes table fixed!</div>";
    echo "<div class='success'>========================================</div>";
    
    // Verify final structure
    echo "<br><div class='warning'>Final table structure:</div>";
    $result = $db->query("SHOW COLUMNS FROM void_reason_codes");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "<div class='success'>  ✓ " . $row['Field'] . " (" . $row['Type'] . ")</div>";
    }
    
    // Show sample data
    echo "<br><div class='warning'>Sample data:</div>";
    $result = $db->query("SELECT code, display_name, display_order FROM void_reason_codes ORDER BY display_order LIMIT 5");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "<div class='success'>  {$row['display_order']}. {$row['code']} - {$row['display_name']}</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>✗ ERROR: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>
    <div class='mt-4'>
        <a href='void-order-management.php' class='btn btn-success btn-lg'>✓ Test Void Orders Page</a>
        <a href='index.php' class='btn btn-secondary'>Back to Dashboard</a>
    </div>
</div>
</body>
</html>";
?>
