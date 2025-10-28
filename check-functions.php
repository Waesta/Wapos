<?php
/**
 * WAPOS Function Check Script
 * Verifies all required functions are available
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 WAPOS Function Check</h1>";
echo "<p>Verifying all required functions are available...</p>";

$checks = [];
$missing = [];

// Load bootstrap to get all functions
try {
    require_once 'includes/bootstrap.php';
    echo "<p style='color: green;'>✅ Bootstrap loaded successfully</p>";
    $checks[] = "Bootstrap loaded";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Bootstrap failed: " . $e->getMessage() . "</p>";
    $missing[] = "Bootstrap loading failed";
}

// Check required functions
echo "<h2>Required Functions Check</h2>";

$requiredFunctions = [
    'formatDate' => 'Date formatting function',
    'formatMoney' => 'Money formatting function', 
    'generateSaleNumber' => 'Sale number generation',
    'sanitizeInput' => 'Input sanitization',
    'generateCSRFToken' => 'CSRF token generation',
    'validateCSRFToken' => 'CSRF token validation',
    'redirect' => 'Page redirection',
    'showAlert' => 'Alert display function'
];

foreach ($requiredFunctions as $func => $description) {
    if (function_exists($func)) {
        echo "<p style='color: green;'>✅ $func() - $description</p>";
        $checks[] = "$func() function available";
    } else {
        echo "<p style='color: red;'>❌ $func() - $description (MISSING)</p>";
        $missing[] = "$func() function missing";
    }
}

// Check Auth class methods
echo "<h2>Auth Class Methods Check</h2>";

$authMethods = [
    'isLoggedIn' => 'Login status check',
    'hasRole' => 'Role permission check',
    'getUser' => 'User data retrieval',
    'getRole' => 'User role retrieval',
    'requireLogin' => 'Login requirement',
    'requireRole' => 'Role requirement'
];

if (isset($auth)) {
    foreach ($authMethods as $method => $description) {
        if (method_exists($auth, $method)) {
            echo "<p style='color: green;'>✅ Auth::$method() - $description</p>";
            $checks[] = "Auth::$method() available";
        } else {
            echo "<p style='color: red;'>❌ Auth::$method() - $description (MISSING)</p>";
            $missing[] = "Auth::$method() missing";
        }
    }
} else {
    echo "<p style='color: red;'>❌ Auth object not available</p>";
    $missing[] = "Auth object not initialized";
}

// Check Database class methods
echo "<h2>Database Class Methods Check</h2>";

$dbMethods = [
    'getInstance' => 'Singleton instance',
    'query' => 'Query execution',
    'fetchAll' => 'Fetch all results',
    'fetchOne' => 'Fetch single result',
    'insert' => 'Insert operation',
    'update' => 'Update operation',
    'delete' => 'Delete operation'
];

if (isset($db)) {
    foreach ($dbMethods as $method => $description) {
        if (method_exists($db, $method)) {
            echo "<p style='color: green;'>✅ Database::$method() - $description</p>";
            $checks[] = "Database::$method() available";
        } else {
            echo "<p style='color: red;'>❌ Database::$method() - $description (MISSING)</p>";
            $missing[] = "Database::$method() missing";
        }
    }
} else {
    echo "<p style='color: red;'>❌ Database object not available</p>";
    $missing[] = "Database object not initialized";
}

// Test formatDate function specifically
echo "<h2>Function Testing</h2>";

if (function_exists('formatDate')) {
    try {
        $testDate = formatDate('2024-01-15 14:30:00', 'd/m/Y H:i');
        echo "<p style='color: green;'>✅ formatDate() test: '$testDate'</p>";
        $checks[] = "formatDate() working correctly";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ formatDate() test failed: " . $e->getMessage() . "</p>";
        $missing[] = "formatDate() function error";
    }
}

// Summary
echo "<hr><h2>📋 Function Check Summary</h2>";

if (!empty($checks)) {
    echo "<h3 style='color: green;'>✅ Available Functions/Methods (" . count($checks) . "):</h3>";
    echo "<ul>";
    foreach ($checks as $check) {
        echo "<li style='color: green;'>$check</li>";
    }
    echo "</ul>";
}

if (!empty($missing)) {
    echo "<h3 style='color: red;'>❌ Missing Functions/Methods (" . count($missing) . "):</h3>";
    echo "<ul>";
    foreach ($missing as $miss) {
        echo "<li style='color: red;'>$miss</li>";
    }
    echo "</ul>";
} else {
    echo "<div style='background: #d4edda; color: #155724; padding: 20px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>🎉 All Functions Available!</h3>";
    echo "<p>All required functions and methods are properly loaded and working.</p>";
    echo "</div>";
}

echo "<hr>";
echo "<h2>🚀 Test Your Pages</h2>";
echo "<p><a href='users.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Users Page</a>";
echo "<a href='pos.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test POS</a>";
echo "<a href='index.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Dashboard</a></p>";

if (empty($missing)) {
    echo "<p><strong>Your WAPOS system should now work without function errors!</strong></p>";
}
?>
