<?php
/**
 * Quick Fix Script - Minimal diagnostics
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 WAPOS Quick Fix</h1>";
echo "<p>Running minimal diagnostics...</p>";

// Test 1: Basic PHP
echo "<h2>1. PHP Test</h2>";
echo "<p>✅ PHP is working - Version: " . phpversion() . "</p>";

// Test 2: File access
echo "<h2>2. File Access Test</h2>";
$criticalFiles = [
    'config.php',
    'includes/Database.php',
    'includes/Auth.php',
    'includes/bootstrap.php'
];

foreach ($criticalFiles as $file) {
    if (file_exists($file)) {
        echo "<p>✅ $file exists</p>";
    } else {
        echo "<p>❌ $file missing</p>";
    }
}

// Test 3: Database connection (manual)
echo "<h2>3. Database Connection Test</h2>";
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=wapos;charset=utf8mb4', 'root', '');
    echo "<p>✅ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Test 4: Try loading bootstrap
echo "<h2>4. Bootstrap Test</h2>";
try {
    ob_start();
    require_once 'includes/bootstrap.php';
    $output = ob_get_clean();
    echo "<p>✅ Bootstrap loaded successfully</p>";
    
    if (isset($auth)) {
        echo "<p>✅ Auth object available</p>";
    } else {
        echo "<p>❌ Auth object not available</p>";
    }
    
    if (isset($db)) {
        echo "<p>✅ Database object available</p>";
    } else {
        echo "<p>❌ Database object not available</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Bootstrap failed: " . $e->getMessage() . "</p>";
} catch (Error $e) {
    echo "<p>❌ Bootstrap error: " . $e->getMessage() . "</p>";
}

// Test 5: Memory and performance
echo "<h2>5. System Resources</h2>";
echo "<p>Memory usage: " . number_format(memory_get_usage(true) / 1024 / 1024, 2) . " MB</p>";
echo "<p>Memory limit: " . ini_get('memory_limit') . "</p>";

// Quick fixes
echo "<h2>6. Quick Fixes</h2>";

// Fix 1: Ensure cache directory exists
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
    echo "<p>✅ Created cache directory</p>";
} else {
    echo "<p>✅ Cache directory exists</p>";
}

// Fix 2: Clear any problematic cache files
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "<p>✅ Cleared cache files</p>";
}

// Fix 3: Reset version
file_put_contents('version.txt', '2.1.1');
echo "<p>✅ Reset version to 2.1.1</p>";

echo "<hr>";
echo "<h2>🎯 Next Steps</h2>";
echo "<p>1. <a href='index.php'>Try Dashboard</a></p>";
echo "<p>2. <a href='pos.php'>Try POS System</a></p>";
echo "<p>3. <a href='login.php'>Try Login Page</a></p>";
echo "<p>4. <a href='reset-password.php'>Reset Password if needed</a></p>";

echo "<hr>";
echo "<p><strong>If pages are still blank, there may be a fatal error. Check your PHP error logs.</strong></p>";
?>
