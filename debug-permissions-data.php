<?php
/**
 * Debug Permissions Data
 * Shows what data exists in the permission tables
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debug Permissions Data</h1>";

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=wapos;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Check permission_modules table
    echo "<h2>Permission Modules</h2>";
    try {
        $modules = $pdo->query("SELECT * FROM permission_modules ORDER BY module_name")->fetchAll();
        if (empty($modules)) {
            echo "<p style='color: orange;'>⚠️ No modules found in permission_modules table</p>";
        } else {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Module Key</th><th>Module Name</th><th>Description</th><th>Active</th></tr>";
            foreach ($modules as $module) {
                echo "<tr>";
                echo "<td>{$module['id']}</td>";
                echo "<td><strong>{$module['module_key']}</strong></td>";
                echo "<td>{$module['module_name']}</td>";
                echo "<td>{$module['description']}</td>";
                echo "<td>" . ($module['is_active'] ? '✅' : '❌') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error reading permission_modules: " . $e->getMessage() . "</p>";
    }
    
    // 2. Check permission_actions table
    echo "<h2>Permission Actions</h2>";
    try {
        $actions = $pdo->query("
            SELECT pa.*, pm.module_name 
            FROM permission_actions pa 
            JOIN permission_modules pm ON pa.module_id = pm.id 
            ORDER BY pm.module_name, pa.action_name
        ")->fetchAll();
        
        if (empty($actions)) {
            echo "<p style='color: orange;'>⚠️ No actions found in permission_actions table</p>";
        } else {
            echo "<p><strong>Found " . count($actions) . " actions</strong></p>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Module</th><th>Action Key</th><th>Action Name</th><th>Active</th></tr>";
            foreach ($actions as $action) {
                echo "<tr>";
                echo "<td>{$action['id']}</td>";
                echo "<td><span style='background: #007bff; color: white; padding: 2px 6px; border-radius: 3px;'>{$action['module_name']}</span></td>";
                echo "<td><code>{$action['action_key']}</code></td>";
                echo "<td>{$action['action_name']}</td>";
                echo "<td>" . ($action['is_active'] ? '✅' : '❌') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error reading permission_actions: " . $e->getMessage() . "</p>";
    }
    
    // 3. Check users table
    echo "<h2>Users</h2>";
    try {
        $users = $pdo->query("SELECT id, username, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
        if (empty($users)) {
            echo "<p style='color: orange;'>⚠️ No active users found</p>";
        } else {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>{$user['id']}</td>";
                echo "<td><strong>{$user['username']}</strong></td>";
                echo "<td>{$user['full_name']}</td>";
                echo "<td><span style='background: #28a745; color: white; padding: 2px 6px; border-radius: 3px;'>{$user['role']}</span></td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error reading users: " . $e->getMessage() . "</p>";
    }
    
    // 4. Check user_permissions table
    echo "<h2>User Permissions</h2>";
    try {
        $userPermissions = $pdo->query("
            SELECT up.*, pm.module_key, pa.action_key, u.username 
            FROM user_permissions up 
            JOIN permission_modules pm ON up.module_id = pm.id 
            JOIN permission_actions pa ON up.action_id = pa.id 
            JOIN users u ON up.user_id = u.id 
            WHERE up.is_active = 1 
            ORDER BY u.username, pm.module_name, pa.action_name
        ")->fetchAll();
        
        if (empty($userPermissions)) {
            echo "<p style='color: orange;'>⚠️ No user permissions assigned yet</p>";
            echo "<p>This is why the permission matrix shows 'Unknown' - there are no permissions to display.</p>";
        } else {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>User</th><th>Module</th><th>Action</th><th>Granted</th><th>Expires</th></tr>";
            foreach ($userPermissions as $perm) {
                echo "<tr>";
                echo "<td><strong>{$perm['username']}</strong></td>";
                echo "<td><span style='background: #007bff; color: white; padding: 2px 6px; border-radius: 3px;'>{$perm['module_key']}</span></td>";
                echo "<td><span style='background: #6c757d; color: white; padding: 2px 6px; border-radius: 3px;'>{$perm['action_key']}</span></td>";
                echo "<td>" . date('d/m/Y H:i', strtotime($perm['granted_at'])) . "</td>";
                echo "<td>" . ($perm['expires_at'] ? date('d/m/Y H:i', strtotime($perm['expires_at'])) : 'Never') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error reading user_permissions: " . $e->getMessage() . "</p>";
    }
    
    // 5. Summary and recommendations
    echo "<hr><h2>📋 Summary & Recommendations</h2>";
    
    $moduleCount = count($modules ?? []);
    $actionCount = count($actions ?? []);
    $userCount = count($users ?? []);
    $permissionCount = count($userPermissions ?? []);
    
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>Current Status:</h3>";
    echo "<ul>";
    echo "<li><strong>Modules:</strong> $moduleCount</li>";
    echo "<li><strong>Actions:</strong> $actionCount</li>";
    echo "<li><strong>Users:</strong> $userCount</li>";
    echo "<li><strong>Assigned Permissions:</strong> $permissionCount</li>";
    echo "</ul>";
    
    if ($moduleCount == 0 || $actionCount == 0) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>❌ Missing Core Data</h4>";
        echo "<p>The permission system needs modules and actions to work properly.</p>";
        echo "<p><strong>Solution:</strong> Run the table setup script to create default data.</p>";
        echo "</div>";
    } elseif ($permissionCount == 0) {
        echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>⚠️ No Permissions Assigned</h4>";
        echo "<p>The modules and actions exist, but no permissions have been assigned to users yet.</p>";
        echo "<p><strong>This is why you see 'Unknown' in the permission matrix.</strong></p>";
        echo "<p><strong>Solution:</strong> Use the grant permission form to assign permissions to users.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>✅ System Ready</h4>";
        echo "<p>The permission system has data and should be working correctly.</p>";
        echo "</div>";
    }
    echo "</div>";
    
    echo "<h3>Quick Actions:</h3>";
    echo "<p>";
    echo "<a href='quick-fix-missing-tables.php' style='background: #dc3545; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Setup Tables & Data</a>";
    echo "<a href='permissions.php' style='background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>View Permissions</a>";
    echo "<a href='test-permissions-fixed.php' style='background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Test Version</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ Database Connection Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
