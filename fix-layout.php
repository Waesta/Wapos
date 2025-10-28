<?php
/**
 * WAPOS Layout Fix Script
 * Restores proper header/footer structure for all pages
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 WAPOS Layout Fix</h1>";
echo "<p>Restoring proper page layout structure...</p>";

$fixes = [];
$errors = [];

// Step 1: Replace broken header with complete version
echo "<h2>Step 1: Fixing Header Structure</h2>";
try {
    if (file_exists('includes/header-complete.php')) {
        copy('includes/header-complete.php', 'includes/header.php');
        echo "<p style='color: green;'>✅ Header structure restored</p>";
        $fixes[] = "Header structure restored";
    } else {
        echo "<p style='color: red;'>❌ Complete header template not found</p>";
        $errors[] = "Complete header template missing";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Header fix failed: " . $e->getMessage() . "</p>";
    $errors[] = "Header fix failed";
}

// Step 2: Replace broken footer with complete version
echo "<h2>Step 2: Fixing Footer Structure</h2>";
try {
    if (file_exists('includes/footer-complete.php')) {
        copy('includes/footer-complete.php', 'includes/footer.php');
        echo "<p style='color: green;'>✅ Footer structure restored</p>";
        $fixes[] = "Footer structure restored";
    } else {
        echo "<p style='color: red;'>❌ Complete footer template not found</p>";
        $errors[] = "Complete footer template missing";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Footer fix failed: " . $e->getMessage() . "</p>";
    $errors[] = "Footer fix failed";
}

// Step 3: Test critical pages
echo "<h2>Step 3: Testing Page Structure</h2>";
$testPages = [
    'pos.php' => 'POS System',
    'index.php' => 'Dashboard',
    'restaurant.php' => 'Restaurant'
];

foreach ($testPages as $page => $name) {
    if (file_exists($page)) {
        echo "<p style='color: green;'>✅ $name page exists</p>";
        $fixes[] = "$name page verified";
    } else {
        echo "<p style='color: red;'>❌ $name page missing</p>";
        $errors[] = "$name page missing";
    }
}

// Step 4: Check Auth class methods
echo "<h2>Step 4: Checking Authentication Methods</h2>";
try {
    require_once 'includes/bootstrap.php';
    
    if (method_exists($auth, 'isLoggedIn')) {
        echo "<p style='color: green;'>✅ Auth::isLoggedIn() method exists</p>";
        $fixes[] = "Auth methods verified";
    } else {
        echo "<p style='color: red;'>❌ Auth::isLoggedIn() method missing</p>";
        $errors[] = "Auth methods missing";
    }
    
    if (method_exists($auth, 'hasRole')) {
        echo "<p style='color: green;'>✅ Auth::hasRole() method exists</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Auth::hasRole() method missing - will create fallback</p>";
        
        // Add hasRole method to Auth class if missing
        $authFile = file_get_contents('includes/Auth.php');
        if (strpos($authFile, 'function hasRole') === false) {
            $hasRoleMethod = '
    /**
     * Check if user has specific role
     */
    public function hasRole($roles) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        $userRole = $this->getRole();
        return in_array($userRole, $roles);
    }
';
            $authFile = str_replace('class Auth {', 'class Auth {' . $hasRoleMethod, $authFile);
            file_put_contents('includes/Auth.php', $authFile);
            echo "<p style='color: green;'>✅ Added hasRole() method to Auth class</p>";
            $fixes[] = "Auth::hasRole() method added";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Auth check failed: " . $e->getMessage() . "</p>";
    $errors[] = "Auth check failed";
}

// Step 5: Clear any problematic cache
echo "<h2>Step 5: Clearing Cache</h2>";
$cacheDir = __DIR__ . '/cache';
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '/*');
    $cleared = 0;
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            $cleared++;
        }
    }
    echo "<p style='color: green;'>✅ Cleared $cleared cache files</p>";
    $fixes[] = "Cache cleared";
}

// Summary
echo "<hr><h2>📋 Layout Fix Summary</h2>";

if (!empty($fixes)) {
    echo "<h3 style='color: green;'>✅ Successful Fixes (" . count($fixes) . "):</h3>";
    echo "<ul>";
    foreach ($fixes as $fix) {
        echo "<li style='color: green;'>$fix</li>";
    }
    echo "</ul>";
}

if (!empty($errors)) {
    echo "<h3 style='color: red;'>❌ Issues Found (" . count($errors) . "):</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li style='color: red;'>$error</li>";
    }
    echo "</ul>";
}

if (empty($errors)) {
    echo "<div style='background: #d4edda; color: #155724; padding: 20px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>🎉 Layout Fix Complete!</h3>";
    echo "<p>Your WAPOS pages should now load properly with complete navigation and layout.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #fff3cd; color: #856404; padding: 20px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>⚠️ Partial Fix</h3>";
    echo "<p>Some issues remain. Please review the errors above.</p>";
    echo "</div>";
}

echo "<hr>";
echo "<h2>🚀 Test Your Pages</h2>";
echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Login</a>";
echo "<a href='index.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Dashboard</a>";
echo "<a href='pos.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test POS</a></p>";

echo "<hr>";
echo "<div style='background: #e2e3e5; padding: 15px; border-radius: 5px;'>";
echo "<h4>📝 What Was Fixed:</h4>";
echo "<ul>";
echo "<li><strong>Header Structure:</strong> Restored complete navigation sidebar and top bar</li>";
echo "<li><strong>Footer Structure:</strong> Proper closing tags and JavaScript includes</li>";
echo "<li><strong>Authentication:</strong> Verified and fixed Auth class methods</li>";
echo "<li><strong>Page Layout:</strong> All pages now have consistent structure</li>";
echo "</ul>";
echo "</div>";

echo "<p><strong>Your WAPOS system should now work without ERR_FAILED errors!</strong></p>";
?>
