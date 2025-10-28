<?php
/**
 * Verify System Health Page
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 System Health Verification</h1>";

try {
    require_once 'includes/bootstrap.php';
    
    echo "<h2>Testing System Health Components</h2>";
    
    // Test database connection
    try {
        $db->fetchOne("SELECT 1");
        echo "<p style='color: green;'>✅ Database connection working</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    }
    
    // Test permission system check
    try {
        $modules = $db->fetchOne("SELECT COUNT(*) as count FROM permission_modules WHERE is_active = 1");
        $actions = $db->fetchOne("SELECT COUNT(*) as count FROM permission_actions WHERE is_active = 1");
        $permissionsWorking = ($modules['count'] > 0 && $actions['count'] > 0);
        
        if ($permissionsWorking) {
            echo "<p style='color: green;'>✅ Permission system working ({$modules['count']} modules, {$actions['count']} actions)</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Permission system has no data ({$modules['count']} modules, {$actions['count']} actions)</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Permission system check failed: " . $e->getMessage() . "</p>";
    }
    
    // Test essential tables
    $essentialTables = [
        'users' => 'User Management',
        'products' => 'Product Catalog', 
        'sales' => 'Sales Transactions',
        'settings' => 'System Settings',
        'permission_modules' => 'Permission System',
        'stock_movements' => 'Inventory Management',
        'suppliers' => 'Supplier Management'
    ];
    
    echo "<h3>Essential Tables Check:</h3>";
    $allTablesOk = true;
    
    foreach ($essentialTables as $table => $description) {
        try {
            $result = $db->fetchOne("SHOW TABLES LIKE ?", [$table]);
            if (!empty($result)) {
                try {
                    $count = $db->fetchOne("SELECT COUNT(*) as count FROM $table");
                    echo "<p style='color: green;'>✅ $table ($description): {$count['count']} records</p>";
                } catch (Exception $e) {
                    echo "<p style='color: orange;'>⚠️ $table ($description): Table exists but query failed</p>";
                }
            } else {
                echo "<p style='color: red;'>❌ $table ($description): Missing</p>";
                $allTablesOk = false;
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ $table ($description): Error checking - " . $e->getMessage() . "</p>";
            $allTablesOk = false;
        }
    }
    
    // Test essential files
    echo "<h3>Essential Files Check:</h3>";
    $essentialFiles = [
        'includes/bootstrap.php' => 'System Bootstrap',
        'includes/Database.php' => 'Database Layer',
        'includes/Auth.php' => 'Authentication System',
        'includes/PermissionManager.php' => 'Permission Manager'
    ];
    
    $allFilesOk = true;
    foreach ($essentialFiles as $file => $description) {
        if (file_exists($file)) {
            echo "<p style='color: green;'>✅ $file ($description)</p>";
        } else {
            echo "<p style='color: red;'>❌ $file ($description): Missing</p>";
            $allFilesOk = false;
        }
    }
    
    // Overall status
    echo "<hr><h2>📋 Verification Summary</h2>";
    
    if ($allTablesOk && $allFilesOk) {
        echo "<div style='background: #d4edda; color: #155724; padding: 20px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>🎉 System Health Page Ready!</h3>";
        echo "<p>All components are working correctly. The system health page should load without errors.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #fff3cd; color: #856404; padding: 20px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>⚠️ Some Issues Found</h3>";
        echo "<p>Some tables or files are missing. The system health page will show these issues.</p>";
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<h2>🚀 Test System Health Page</h2>";
    echo "<p><a href='system-health.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Open System Health</a></p>";
    
    if (!$allTablesOk) {
        echo "<p><a href='update-database-schema.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Create Missing Tables</a>";
        echo "<a href='fix-permissions-page.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Setup Permissions</a></p>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ System Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
