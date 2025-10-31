<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance()->getConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Create void_transactions Table - WAPOS</title>
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
    <h1>Create void_transactions Table</h1>
    <div class='log'>";

try {
    // Check if table exists
    $result = $db->query("SHOW TABLES LIKE 'void_transactions'")->fetch();
    
    if ($result) {
        echo "<div class='warning'>⚠ Table void_transactions already exists</div>";
        echo "<div class='success'>Showing current structure...</div><br>";
    } else {
        echo "<div class='warning'>Creating void_transactions table...</div>";
        
        $sql = "
        CREATE TABLE void_transactions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            order_type ENUM('sale', 'restaurant_order', 'delivery') DEFAULT 'sale',
            void_reason_code VARCHAR(50) NOT NULL,
            void_reason_notes TEXT,
            original_total DECIMAL(15,2) NOT NULL,
            voided_by_user_id INT UNSIGNED NOT NULL,
            manager_user_id INT UNSIGNED,
            void_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_order_id (order_id),
            INDEX idx_void_timestamp (void_timestamp),
            INDEX idx_voided_by (voided_by_user_id),
            INDEX idx_reason_code (void_reason_code),
            FOREIGN KEY (voided_by_user_id) REFERENCES users(id),
            FOREIGN KEY (void_reason_code) REFERENCES void_reason_codes(code)
        ) ENGINE=InnoDB;
        ";
        
        $db->exec($sql);
        echo "<div class='success'>✓ Table created successfully!</div><br>";
    }
    
    // Show table structure
    echo "<div class='warning'>Table structure:</div>";
    $result = $db->query("SHOW COLUMNS FROM void_transactions");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "<div class='success'>  ✓ " . $row['Field'] . " (" . $row['Type'] . ")</div>";
    }
    
    echo "<br>";
    echo "<div class='success'>========================================</div>";
    echo "<div class='success'>✓ void_transactions table ready!</div>";
    echo "<div class='success'>========================================</div>";
    
    // Check record count
    $result = $db->query("SELECT COUNT(*) as count FROM void_transactions");
    $count = $result->fetch(PDO::FETCH_ASSOC);
    echo "<br><div class='success'>Current void transactions: " . $count['count'] . "</div>";
    
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
