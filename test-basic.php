<?php
// Basic system test - no includes
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>WAPOS Basic Test</title></head><body>";
echo "<h1>🧪 WAPOS Basic System Test</h1>";

// Test 1: PHP
echo "<h2>1. PHP Status</h2>";
echo "<p>✅ PHP Version: " . phpversion() . "</p>";
echo "<p>✅ Memory Limit: " . ini_get('memory_limit') . "</p>";

// Test 2: Database (direct connection)
echo "<h2>2. Database Test</h2>";
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=wapos;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $result = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch();
    echo "<p>✅ Database connected successfully</p>";
    echo "<p>✅ Found " . $result['count'] . " users in database</p>";
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test 3: File system
echo "<h2>3. File System Test</h2>";
$testFiles = ['config.php', 'includes/Database.php', 'includes/Auth.php'];
foreach ($testFiles as $file) {
    if (file_exists($file)) {
        echo "<p>✅ $file exists</p>";
    } else {
        echo "<p>❌ $file missing</p>";
    }
}

// Test 4: Try minimal bootstrap
echo "<h2>4. Minimal Bootstrap Test</h2>";
try {
    // Load config first
    require_once 'config.php';
    echo "<p>✅ Config loaded</p>";
    
    // Load Database class
    require_once 'includes/Database.php';
    echo "<p>✅ Database class loaded</p>";
    
    // Test Database instance
    $db = Database::getInstance();
    echo "<p>✅ Database instance created</p>";
    
    // Test query
    $result = $db->fetchOne("SELECT 1 as test");
    echo "<p>✅ Database query successful</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Bootstrap test failed: " . $e->getMessage() . "</p>";
} catch (Error $e) {
    echo "<p>❌ Bootstrap error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>🎯 Quick Actions</h2>";
echo "<p><a href='quick-fix.php'>Run Quick Fix</a></p>";
echo "<p><a href='login.php'>Try Login Page</a></p>";
echo "<p><a href='index.php'>Try Dashboard</a></p>";

echo "</body></html>";
?>
