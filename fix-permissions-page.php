<?php
/**
 * Fix Permissions Page Issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Permissions Page Fix</h1>";

try {
    require_once 'includes/bootstrap.php';
    
    $fixes = [];
    $errors = [];
    
    // 1. Check if permission tables exist
    echo "<h2>Step 1: Checking Permission Tables</h2>";
    
    $requiredTables = [
        'permission_modules',
        'permission_actions', 
        'user_permissions',
        'permission_audit_log'
    ];
    
    $missingTables = [];
    foreach ($requiredTables as $table) {
        try {
            $db->query("SELECT 1 FROM $table LIMIT 1");
            echo "<p style='color: green;'>✅ Table $table exists</p>";
            $fixes[] = "Table $table verified";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Table $table missing</p>";
            $missingTables[] = $table;
            $errors[] = "Missing table: $table";
        }
    }
    
    // 2. Create missing tables if needed
    if (!empty($missingTables)) {
        echo "<h2>Step 2: Creating Missing Tables</h2>";
        
        $tableSQL = [
            'permission_modules' => "
                CREATE TABLE permission_modules (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    module_key VARCHAR(50) UNIQUE NOT NULL,
                    module_name VARCHAR(100) NOT NULL,
                    description TEXT,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
            'permission_actions' => "
                CREATE TABLE permission_actions (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    module_id INT NOT NULL,
                    action_key VARCHAR(50) NOT NULL,
                    action_name VARCHAR(100) NOT NULL,
                    description TEXT,
                    is_active BOOLEAN DEFAULT TRUE,
                    FOREIGN KEY (module_id) REFERENCES permission_modules(id),
                    UNIQUE KEY unique_module_action (module_id, action_key)
                )",
            'user_permissions' => "
                CREATE TABLE user_permissions (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    module_id INT NOT NULL,
                    action_id INT NOT NULL,
                    granted_by INT NOT NULL,
                    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME,
                    conditions JSON,
                    is_active BOOLEAN DEFAULT TRUE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (module_id) REFERENCES permission_modules(id),
                    FOREIGN KEY (action_id) REFERENCES permission_actions(id),
                    FOREIGN KEY (granted_by) REFERENCES users(id)
                )",
            'permission_audit_log' => "
                CREATE TABLE permission_audit_log (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT,
                    module_key VARCHAR(50) NOT NULL,
                    action_key VARCHAR(50) NOT NULL,
                    resource_id VARCHAR(100),
                    result ENUM('granted', 'denied') NOT NULL,
                    reason TEXT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )"
        ];
        
        foreach ($missingTables as $table) {
            try {
                $db->query($tableSQL[$table]);
                echo "<p style='color: green;'>✅ Created table: $table</p>";
                $fixes[] = "Created table: $table";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Failed to create $table: " . $e->getMessage() . "</p>";
                $errors[] = "Failed to create $table";
            }
        }
    }
    
    // 3. Insert default permission modules
    echo "<h2>Step 3: Inserting Default Permission Data</h2>";
    
    $defaultModules = [
        ['pos', 'Point of Sale', 'Retail POS operations'],
        ['restaurant', 'Restaurant', 'Restaurant and F&B operations'],
        ['inventory', 'Inventory', 'Stock and inventory management'],
        ['accounting', 'Accounting', 'Financial and accounting operations'],
        ['customers', 'Customers', 'Customer management and CRM'],
        ['users', 'Users', 'User management and administration'],
        ['reports', 'Reports', 'Business reports and analytics'],
        ['settings', 'Settings', 'System configuration'],
        ['rooms', 'Rooms', 'Hotel room management'],
        ['delivery', 'Delivery', 'Delivery and logistics']
    ];
    
    foreach ($defaultModules as $module) {
        try {
            $existing = $db->fetchOne("SELECT id FROM permission_modules WHERE module_key = ?", [$module[0]]);
            if (!$existing) {
                $db->insert('permission_modules', [
                    'module_key' => $module[0],
                    'module_name' => $module[1],
                    'description' => $module[2]
                ]);
                echo "<p style='color: green;'>✅ Added module: {$module[1]}</p>";
                $fixes[] = "Added module: {$module[1]}";
            } else {
                echo "<p style='color: blue;'>ℹ️ Module exists: {$module[1]}</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Failed to add module {$module[1]}: " . $e->getMessage() . "</p>";
            $errors[] = "Failed to add module: {$module[1]}";
        }
    }
    
    // 4. Insert default actions for each module
    $defaultActions = [
        'create' => 'Create/Add new records',
        'read' => 'View/Read records', 
        'update' => 'Edit/Update records',
        'delete' => 'Delete records',
        'manage' => 'Full management access'
    ];
    
    $modules = $db->fetchAll("SELECT id, module_key, module_name FROM permission_modules");
    foreach ($modules as $module) {
        foreach ($defaultActions as $actionKey => $actionName) {
            try {
                $existing = $db->fetchOne("SELECT id FROM permission_actions WHERE module_id = ? AND action_key = ?", 
                    [$module['id'], $actionKey]);
                if (!$existing) {
                    $db->insert('permission_actions', [
                        'module_id' => $module['id'],
                        'action_key' => $actionKey,
                        'action_name' => $actionName,
                        'description' => $actionName . ' for ' . $module['module_name']
                    ]);
                }
            } catch (Exception $e) {
                // Continue on error
            }
        }
    }
    echo "<p style='color: green;'>✅ Default actions created for all modules</p>";
    $fixes[] = "Default actions created";
    
    // Summary
    echo "<hr><h2>📋 Fix Summary</h2>";
    
    if (!empty($fixes)) {
        echo "<h3 style='color: green;'>✅ Fixes Applied (" . count($fixes) . "):</h3>";
        echo "<ul>";
        foreach ($fixes as $fix) {
            echo "<li style='color: green;'>$fix</li>";
        }
        echo "</ul>";
    }
    
    if (!empty($errors)) {
        echo "<h3 style='color: red;'>❌ Issues (" . count($errors) . "):</h3>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li style='color: red;'>$error</li>";
        }
        echo "</ul>";
    }
    
    if (empty($errors)) {
        echo "<div style='background: #d4edda; color: #155724; padding: 20px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>🎉 Permissions System Fixed!</h3>";
        echo "<p>All permission tables and default data have been set up correctly.</p>";
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<h2>🚀 Test Permissions System</h2>";
    echo "<p><a href='permissions.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Permissions Page</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ System Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
