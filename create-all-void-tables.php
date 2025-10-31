<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance()->getConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Create All Void Tables - WAPOS</title>
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
    <h1>Create All Void Management Tables</h1>
    <div class='log'>";

try {
    // 1. Create void_reason_codes table
    echo "<div class='warning'>1. Creating void_reason_codes table...</div>";
    $result = $db->query("SHOW TABLES LIKE 'void_reason_codes'")->fetch();
    
    if (!$result) {
        $sql = "
        CREATE TABLE void_reason_codes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL UNIQUE,
            display_name VARCHAR(100) NOT NULL,
            description VARCHAR(255) NOT NULL,
            requires_approval TINYINT(1) DEFAULT 0,
            requires_manager_approval TINYINT(1) DEFAULT 0,
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (is_active),
            INDEX idx_code (code),
            INDEX idx_display_order (display_order)
        ) ENGINE=InnoDB;
        ";
        $db->exec($sql);
        echo "<div class='success'>✓ Created void_reason_codes table</div>";
        
        // Insert default data
        $stmt = $db->prepare("
            INSERT INTO void_reason_codes (code, display_name, description, requires_manager_approval, display_order) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $reasons = [
            ['CUSTOMER_REQUEST', 'Customer Request', 'Customer requested cancellation', 0, 1],
            ['WRONG_ORDER', 'Wrong Order', 'Wrong order entered', 0, 2],
            ['PAYMENT_ISSUE', 'Payment Issue', 'Payment processing issue', 1, 3],
            ['DUPLICATE', 'Duplicate Order', 'Duplicate order', 0, 4],
            ['OUT_OF_STOCK', 'Out of Stock', 'Item out of stock', 0, 5],
            ['PRICING_ERROR', 'Pricing Error', 'Pricing error', 1, 6],
            ['KITCHEN_ERROR', 'Kitchen Error', 'Kitchen preparation error', 0, 7],
            ['CUSTOMER_NO_SHOW', 'Customer No-Show', 'Customer did not show up', 0, 8],
            ['MANAGER_OVERRIDE', 'Manager Override', 'Manager override', 1, 9],
            ['OTHER', 'Other Reason', 'Other reason', 1, 10]
        ];
        
        foreach ($reasons as $reason) {
            $stmt->execute($reason);
        }
        echo "<div class='success'>✓ Inserted 10 default void reasons</div>";
    } else {
        echo "<div class='success'>✓ void_reason_codes already exists</div>";
    }
    
    echo "<br>";
    
    // 2. Create void_settings table
    echo "<div class='warning'>2. Creating void_settings table...</div>";
    $result = $db->query("SHOW TABLES LIKE 'void_settings'")->fetch();
    
    if (!$result) {
        $sql = "
        CREATE TABLE void_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            description VARCHAR(255),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT UNSIGNED,
            INDEX idx_key (setting_key)
        ) ENGINE=InnoDB;
        ";
        $db->exec($sql);
        echo "<div class='success'>✓ Created void_settings table</div>";
        
        // Insert default settings
        $stmt = $db->prepare("
            INSERT INTO void_settings (setting_key, setting_value, description) 
            VALUES (?, ?, ?)
        ");
        
        $settings = [
            ['require_manager_approval', '1', 'Require manager approval for voids'],
            ['max_void_amount', '1000', 'Maximum amount that can be voided without approval'],
            ['void_retention_days', '90', 'Number of days to retain void records'],
            ['allow_partial_void', '1', 'Allow partial order voids'],
            ['require_reason_code', '1', 'Require reason code for all voids']
        ];
        
        foreach ($settings as $setting) {
            $stmt->execute($setting);
        }
        echo "<div class='success'>✓ Inserted 5 default settings</div>";
    } else {
        echo "<div class='success'>✓ void_settings already exists</div>";
    }
    
    echo "<br>";
    
    // 3. Create void_transactions table
    echo "<div class='warning'>3. Creating void_transactions table...</div>";
    $result = $db->query("SHOW TABLES LIKE 'void_transactions'")->fetch();
    
    if (!$result) {
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
            INDEX idx_reason_code (void_reason_code)
        ) ENGINE=InnoDB;
        ";
        $db->exec($sql);
        echo "<div class='success'>✓ Created void_transactions table</div>";
    } else {
        echo "<div class='success'>✓ void_transactions already exists</div>";
    }
    
    echo "<br>";
    echo "<div class='success'>========================================</div>";
    echo "<div class='success'>✓ All void management tables created!</div>";
    echo "<div class='success'>========================================</div>";
    
    // Verify all tables
    echo "<br><div class='warning'>Verifying tables...</div>";
    $tables = ['void_reason_codes', 'void_settings', 'void_transactions'];
    foreach ($tables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'")->fetch();
        if ($result) {
            $count = $db->query("SELECT COUNT(*) as count FROM $table")->fetch(PDO::FETCH_ASSOC);
            echo "<div class='success'>✓ $table ({$count['count']} records)</div>";
        } else {
            echo "<div class='error'>✗ $table (missing)</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'>✗ ERROR: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='error'>Stack: " . htmlspecialchars($e->getTraceAsString()) . "</div>";
}

echo "</div>
    <div class='mt-4'>
        <a href='void-order-management.php' class='btn btn-success btn-lg'>✓ Open Void Orders Page</a>
        <a href='check-actual-tables.php' class='btn btn-info'>Check All Tables</a>
        <a href='index.php' class='btn btn-secondary'>Back to Dashboard</a>
    </div>
</div>
</body>
</html>";
?>
