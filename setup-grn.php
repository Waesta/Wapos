<?php
/**
 * Setup Goods Received Notes (GRN) Tables
 * Run this once to create the GRN tables for complete procurement flow
 */

require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'developer']);

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $sql = file_get_contents(__DIR__ . '/database/create_grn_tables.sql');
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $conn->exec($statement);
        }
    }
    
    echo '<div style="padding: 20px; font-family: Arial;">';
    echo '<h2 style="color: green;">✓ GRN System Setup Complete!</h2>';
    echo '<p>Goods Received Notes tables have been created successfully.</p>';
    echo '<h3>Procurement Flow:</h3>';
    echo '<ol>';
    echo '<li><strong>Create Purchase Order</strong> - Order goods from supplier</li>';
    echo '<li><strong>Receive Goods (GRN)</strong> - Record actual delivery</li>';
    echo '<li><strong>Stock Updated</strong> - Inventory automatically increased</li>';
    echo '</ol>';
    echo '<h3>Tables Created:</h3>';
    echo '<ul>';
    echo '<li>goods_received_notes - Main GRN records</li>';
    echo '<li>grn_items - Line items with batch/expiry tracking</li>';
    echo '</ul>';
    echo '<div style="margin-top: 20px;">';
    echo '<a href="goods-received.php" class="btn btn-primary" style="padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">Go to Goods Received</a> ';
    echo '<a href="inventory.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">Go to Inventory</a>';
    echo '</div>';
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<div style="padding: 20px; font-family: Arial;">';
    echo '<h2 style="color: red;">✗ Error</h2>';
    echo '<p>Failed to create tables: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>If tables already exist, you can safely ignore this error.</p>';
    echo '<p><a href="goods-received.php" style="color: blue;">Go to Goods Received</a></p>';
    echo '</div>';
}
?>
