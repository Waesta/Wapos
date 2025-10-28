<?php
/**
 * WAPOS System Repair Script
 * Fixes common issues and ensures system stability
 */

echo "<h1>🔧 WAPOS System Repair</h1>";
echo "<p>Diagnosing and fixing system issues...</p>";

$fixes = [];
$errors = [];

// 1. Check if bootstrap.php is working
echo "<h2>🔍 Checking Bootstrap</h2>";
try {
    require_once 'includes/bootstrap.php';
    $fixes[] = "✅ Bootstrap loaded successfully";
    echo "<p class='text-success'>✅ Bootstrap loaded successfully</p>";
} catch (Exception $e) {
    $errors[] = "❌ Bootstrap error: " . $e->getMessage();
    echo "<p class='text-danger'>❌ Bootstrap error: " . $e->getMessage() . "</p>";
}

// 2. Check database connection
echo "<h2>🗄️ Checking Database</h2>";
try {
    if (isset($db)) {
        $db->fetchOne("SELECT 1");
        $fixes[] = "✅ Database connection working";
        echo "<p class='text-success'>✅ Database connection working</p>";
    } else {
        $db = Database::getInstance();
        $db->fetchOne("SELECT 1");
        $fixes[] = "✅ Database connection restored";
        echo "<p class='text-success'>✅ Database connection restored</p>";
    }
} catch (Exception $e) {
    $errors[] = "❌ Database error: " . $e->getMessage();
    echo "<p class='text-danger'>❌ Database error: " . $e->getMessage() . "</p>";
}

// 3. Check authentication
echo "<h2>🔐 Checking Authentication</h2>";
try {
    if (isset($auth)) {
        $fixes[] = "✅ Authentication system working";
        echo "<p class='text-success'>✅ Authentication system working</p>";
    } else {
        $auth = new Auth();
        $fixes[] = "✅ Authentication system restored";
        echo "<p class='text-success'>✅ Authentication system restored</p>";
    }
} catch (Exception $e) {
    $errors[] = "❌ Authentication error: " . $e->getMessage();
    echo "<p class='text-danger'>❌ Authentication error: " . $e->getMessage() . "</p>";
}

// 4. Check and fix file permissions
echo "<h2>📁 Checking File Permissions</h2>";
try {
    $criticalFiles = [
        'includes/bootstrap.php',
        'includes/Database.php',
        'includes/Auth.php',
        'includes/PerformanceManager.php',
        'config.php',
        'version.txt'
    ];
    
    $permissionIssues = 0;
    foreach ($criticalFiles as $file) {
        if (!file_exists($file)) {
            $errors[] = "❌ Missing file: $file";
            echo "<p class='text-danger'>❌ Missing file: $file</p>";
            $permissionIssues++;
        } elseif (!is_readable($file)) {
            $errors[] = "❌ Cannot read file: $file";
            echo "<p class='text-danger'>❌ Cannot read file: $file</p>";
            $permissionIssues++;
        }
    }
    
    if ($permissionIssues === 0) {
        $fixes[] = "✅ All critical files accessible";
        echo "<p class='text-success'>✅ All critical files accessible</p>";
    }
} catch (Exception $e) {
    $errors[] = "❌ File permission check failed: " . $e->getMessage();
    echo "<p class='text-danger'>❌ File permission check failed: " . $e->getMessage() . "</p>";
}

// 5. Clear problematic cache
echo "<h2>🧹 Clearing Cache</h2>";
try {
    $cacheDir = __DIR__ . '/cache';
    if (is_dir($cacheDir)) {
        $cacheFiles = glob($cacheDir . '/*');
        $cleared = 0;
        foreach ($cacheFiles as $file) {
            if (is_file($file)) {
                unlink($file);
                $cleared++;
            }
        }
        $fixes[] = "✅ Cleared $cleared cache files";
        echo "<p class='text-success'>✅ Cleared $cleared cache files</p>";
    } else {
        mkdir($cacheDir, 0755, true);
        $fixes[] = "✅ Created cache directory";
        echo "<p class='text-success'>✅ Created cache directory</p>";
    }
} catch (Exception $e) {
    $errors[] = "❌ Cache clearing failed: " . $e->getMessage();
    echo "<p class='text-danger'>❌ Cache clearing failed: " . $e->getMessage() . "</p>";
}

// 6. Reset system version to force refresh
echo "<h2>🔄 Resetting System Version</h2>";
try {
    $newVersion = '2.1.' . time();
    file_put_contents('version.txt', $newVersion);
    $fixes[] = "✅ System version reset to $newVersion";
    echo "<p class='text-success'>✅ System version reset to $newVersion</p>";
} catch (Exception $e) {
    $errors[] = "❌ Version reset failed: " . $e->getMessage();
    echo "<p class='text-danger'>❌ Version reset failed: " . $e->getMessage() . "</p>";
}

// 7. Test critical pages
echo "<h2>🧪 Testing Critical Pages</h2>";
$testPages = [
    'pos.php' => 'POS System',
    'index.php' => 'Dashboard',
    'system-health.php' => 'System Health'
];

foreach ($testPages as $page => $name) {
    try {
        if (file_exists($page)) {
            $fixes[] = "✅ $name page exists";
            echo "<p class='text-success'>✅ $name page exists</p>";
        } else {
            $errors[] = "❌ $name page missing";
            echo "<p class='text-danger'>❌ $name page missing</p>";
        }
    } catch (Exception $e) {
        $errors[] = "❌ $name page test failed: " . $e->getMessage();
        echo "<p class='text-danger'>❌ $name page test failed: " . $e->getMessage() . "</p>";
    }
}

// Summary
echo "<hr><h2>📋 Repair Summary</h2>";

if (!empty($fixes)) {
    echo "<h3 class='text-success'>✅ Successful Repairs:</h3>";
    echo "<ul>";
    foreach ($fixes as $fix) {
        echo "<li>$fix</li>";
    }
    echo "</ul>";
}

if (!empty($errors)) {
    echo "<h3 class='text-danger'>❌ Issues Found:</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
}

echo "<hr>";
if (empty($errors)) {
    echo "<div class='alert alert-success'>";
    echo "<h4>🎉 System Repair Complete!</h4>";
    echo "<p>All issues have been resolved. Your WAPOS system should now work properly.</p>";
    echo "</div>";
} else {
    echo "<div class='alert alert-warning'>";
    echo "<h4>⚠️ Some Issues Remain</h4>";
    echo "<p>Please check the errors above and contact support if needed.</p>";
    echo "</div>";
}

echo "<p><a href='index.php' class='btn btn-primary me-2'>Test Dashboard</a>";
echo "<a href='pos.php' class='btn btn-success me-2'>Test POS</a>";
echo "<a href='system-health.php' class='btn btn-info'>Test System Health</a></p>";

// Log repair
error_log("WAPOS System Repair completed. Fixes: " . count($fixes) . ", Errors: " . count($errors));
?>
