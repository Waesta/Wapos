<?php
/**
 * Test Permissions System
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔐 Permissions System Test</h1>";

try {
    require_once 'includes/bootstrap.php';
    echo "<p style='color: green;'>✅ Bootstrap loaded successfully</p>";
    
    // Test database tables
    $tables = [
        'permission_modules' => 'Permission modules',
        'permission_actions' => 'Permission actions', 
        'user_permissions' => 'User permissions',
        'permission_audit_log' => 'Permission audit log'
    ];
    
    echo "<h2>Database Tables Check</h2>";
    foreach ($tables as $table => $description) {
        try {
            $count = $db->fetchOne("SELECT COUNT(*) as count FROM $table");
            echo "<p style='color: green;'>✅ $description ($table): " . $count['count'] . " records</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ $description ($table): " . $e->getMessage() . "</p>";
        }
    }
    
    // Test PermissionManager
    echo "<h2>PermissionManager Test</h2>";
    try {
        $permManager = new PermissionManager($auth->getUserId());
        echo "<p style='color: green;'>✅ PermissionManager created successfully</p>";
        
        // Test hasPermission method
        $hasPermission = $permManager->hasPermission('pos', 'create');
        echo "<p style='color: green;'>✅ hasPermission() method works: " . ($hasPermission ? 'true' : 'false') . "</p>";
        
        // Test getPermissionMatrix method
        $matrix = $permManager->getPermissionMatrix();
        echo "<p style='color: green;'>✅ getPermissionMatrix() method works: " . count($matrix) . " modules</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ PermissionManager error: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
    echo "<h2>🚀 Test Results</h2>";
    echo "<p><a href='permissions.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Permissions Page</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ System error: " . $e->getMessage() . "</p>";
}
?>
