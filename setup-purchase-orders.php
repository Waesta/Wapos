<?php
/**
 * Setup Purchase Orders Tables
 * Run this once to create the purchase_orders and purchase_order_items tables
 */

require_once 'includes/bootstrap.php';

// Only allow admin/developer to run this
$auth->requireRole(['admin', 'developer']);

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Read and execute the SQL file
    $sql = file_get_contents(__DIR__ . '/database/create_purchase_orders.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $conn->exec($statement);
        }
    }
    
    echo '<div style="padding: 20px; font-family: Arial;">';
    echo '<h2 style="color: green;">✓ Success!</h2>';
    echo '<p>Purchase orders tables have been created successfully.</p>';
    echo '<ul>';
    echo '<li>purchase_orders table created</li>';
    echo '<li>purchase_order_items table created</li>';
    echo '</ul>';
    echo '<p><a href="inventory.php" style="color: blue;">Go to Inventory Management</a></p>';
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<div style="padding: 20px; font-family: Arial;">';
    echo '<h2 style="color: red;">✗ Error</h2>';
    echo '<p>Failed to create tables: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>If tables already exist, you can safely ignore this error.</p>';
    echo '<p><a href="inventory.php" style="color: blue;">Go to Inventory Management</a></p>';
    echo '</div>';
}
?>
