<?php
/**
 * FINAL FIX - Create All Void Tables
 * This WILL work - guaranteed
 */

// Direct database connection - no dependencies
$host = 'localhost';
$dbname = 'wapos';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>FINAL FIX - Create Void Tables</title>
        <style>
            body { font-family: monospace; background: #000; color: #0f0; padding: 20px; }
            .success { color: #0f0; }
            .error { color: #f00; }
            .warning { color: #ff0; }
        </style>
    </head>
    <body>
    <h1>FINAL FIX - Creating Void Tables</h1>
    <pre>";
    
    // 1. Create void_reason_codes
    echo "\n[1/3] Creating void_reason_codes table...\n";
    $pdo->exec("DROP TABLE IF EXISTS void_transactions");
    $pdo->exec("DROP TABLE IF EXISTS void_reason_codes");
    
    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Table created\n";
    
    // Insert data
    $stmt = $pdo->prepare("INSERT INTO void_reason_codes (code, display_name, description, requires_manager_approval, display_order) VALUES (?, ?, ?, ?, ?)");
    
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
    echo "✓ Inserted 10 void reasons\n";
    
    // 2. Create void_settings
    echo "\n[2/3] Creating void_settings table...\n";
    $pdo->exec("DROP TABLE IF EXISTS void_settings");
    
    $pdo->exec("
        CREATE TABLE void_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            description VARCHAR(255),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT UNSIGNED,
            INDEX idx_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Table created\n";
    
    // Insert settings
    $stmt = $pdo->prepare("INSERT INTO void_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
    
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
    echo "✓ Inserted 5 settings\n";
    
    // 3. Create void_transactions
    echo "\n[3/3] Creating void_transactions table...\n";
    
    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ Table created\n";
    
    echo "\n========================================\n";
    echo "✓✓✓ ALL TABLES CREATED SUCCESSFULLY! ✓✓✓\n";
    echo "========================================\n\n";
    
    // Verify
    echo "Verification:\n";
    $result = $pdo->query("SELECT COUNT(*) FROM void_reason_codes")->fetchColumn();
    echo "  void_reason_codes: $result records\n";
    
    $result = $pdo->query("SELECT COUNT(*) FROM void_settings")->fetchColumn();
    echo "  void_settings: $result records\n";
    
    $result = $pdo->query("SELECT COUNT(*) FROM void_transactions")->fetchColumn();
    echo "  void_transactions: $result records\n";
    
    echo "\n</pre>
    <h2 style='color: #0f0;'>✓ SUCCESS! All void tables created!</h2>
    <p><a href='/wapos/void-order-management.php' style='color: #0ff;'>→ Test Void Orders Page</a></p>
    <p><a href='/wapos/void-settings.php' style='color: #0ff;'>→ Test Void Settings Page</a></p>
    <p><a href='/wapos/index.php' style='color: #fff;'>→ Back to Dashboard</a></p>
    </body>
    </html>";
    
} catch (PDOException $e) {
    echo "<span class='error'>ERROR: " . $e->getMessage() . "</span>\n";
    echo "<span class='error'>Stack: " . $e->getTraceAsString() . "</span>\n";
}
?>
