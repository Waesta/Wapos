<?php
/**
 * Fix Role ENUM - Add Missing Roles
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Fix Role ENUM</title>";
echo "<style>body{font-family:Arial;max-width:800px;margin:50px auto;padding:20px;}";
echo ".success{background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".error{background:#f8d7da;color:#721c24;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".info{background:#d1ecf1;color:#0c5460;padding:15px;border-radius:5px;margin:10px 0;}";
echo "pre{background:#f5f5f5;padding:15px;border-radius:5px;overflow:auto;}";
echo "</style></head><body>";

echo "<h1>🔧 Fix Role ENUM</h1>";

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=wapos;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>✅ Database connected</div>";
    
    // Check current ENUM values
    echo "<h2>Current Role Column Definition:</h2>";
    $result = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($result, true) . "</pre>";
    
    echo "<h2>Updating Role ENUM...</h2>";
    
    // Alter the role column to include all roles
    $sql = "ALTER TABLE users MODIFY COLUMN role ENUM(
        'admin',
        'manager',
        'accountant',
        'cashier',
        'waiter',
        'inventory_manager',
        'rider'
    ) NOT NULL DEFAULT 'cashier'";
    
    $pdo->exec($sql);
    
    echo "<div class='success'>";
    echo "<h3>✅ SUCCESS!</h3>";
    echo "<p>Role ENUM updated successfully!</p>";
    echo "</div>";
    
    // Verify the change
    echo "<h2>Updated Role Column Definition:</h2>";
    $result = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($result, true) . "</pre>";
    
    echo "<div class='info'>";
    echo "<h3>✅ Available Roles:</h3>";
    echo "<ul>";
    echo "<li><strong>admin</strong> - Full system access</li>";
    echo "<li><strong>manager</strong> - Business operations</li>";
    echo "<li><strong>accountant</strong> - Financial management</li>";
    echo "<li><strong>cashier</strong> - POS operations</li>";
    echo "<li><strong>waiter</strong> - Restaurant service</li>";
    echo "<li><strong>inventory_manager</strong> - Inventory control</li>";
    echo "<li><strong>rider</strong> - Delivery operations</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<hr>";
    echo "<h2>🧪 Test User Creation</h2>";
    echo "<p><a href='test-add-user.php' style='display:inline-block;padding:10px 20px;background:#28a745;color:white;text-decoration:none;border-radius:5px;'>Test Add User Again</a></p>";
    echo "<p><a href='users.php' style='display:inline-block;padding:10px 20px;background:#007bff;color:white;text-decoration:none;border-radius:5px;'>Go to Users Page</a></p>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Error</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Code:</strong> " . $e->getCode() . "</p>";
    echo "</div>";
    
    echo "<h3>💡 Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Make sure MySQL is running</li>";
    echo "<li>Check database credentials</li>";
    echo "<li>Verify 'wapos' database exists</li>";
    echo "<li>Ensure you have ALTER privileges</li>";
    echo "</ul>";
}

echo "</body></html>";
?>
