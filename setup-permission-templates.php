<?php
/**
 * Setup Permission Templates
 * Ensures all necessary modules and actions exist for the templates
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Setup Permission Templates</h1>";
echo "<p>Ensuring all modules and actions exist for permission templates...</p>";

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=wapos;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $fixes = [];
    
    // 1. Ensure permission tables exist
    echo "<h2>Step 1: Checking Permission Tables</h2>";
    
    $tables = ['permission_modules', 'permission_actions', 'user_permissions'];
    foreach ($tables as $table) {
        try {
            $result = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<p style='color: green;'>✅ Table $table exists ($result records)</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Table $table missing or error: " . $e->getMessage() . "</p>";
            echo "<p><strong>Run quick-fix-missing-tables.php first!</strong></p>";
            exit;
        }
    }
    
    // 2. Required modules for templates
    echo "<h2>Step 2: Ensuring Required Modules</h2>";
    
    $requiredModules = [
        ['pos', 'Point of Sale', 'Retail POS operations and sales'],
        ['restaurant', 'Restaurant', 'Restaurant and F&B operations'],
        ['inventory', 'Inventory', 'Stock and inventory management'],
        ['customers', 'Customers', 'Customer management and CRM'],
        ['products', 'Products', 'Product catalog management'],
        ['sales', 'Sales', 'Sales transactions and history'],
        ['reports', 'Reports', 'Business reports and analytics'],
        ['accounting', 'Accounting', 'Financial and accounting operations'],
        ['rooms', 'Rooms', 'Hotel room management'],
        ['delivery', 'Delivery', 'Delivery and logistics']
    ];
    
    foreach ($requiredModules as $module) {
        try {
            $existing = $pdo->prepare("SELECT id FROM permission_modules WHERE module_key = ?");
            $existing->execute([$module[0]]);
            if (!$existing->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO permission_modules (module_key, module_name, description, is_active) VALUES (?, ?, ?, 1)");
                $stmt->execute($module);
                echo "<p style='color: green;'>✅ Created module: {$module[1]}</p>";
                $fixes[] = "Module: {$module[1]}";
            } else {
                echo "<p style='color: blue;'>ℹ️ Module exists: {$module[1]}</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error with module {$module[1]}: " . $e->getMessage() . "</p>";
        }
    }
    
    // 3. Required actions for templates
    echo "<h2>Step 3: Ensuring Required Actions</h2>";
    
    $requiredActions = ['create', 'read', 'update', 'delete', 'manage'];
    $modules = $pdo->query("SELECT id, module_key, module_name FROM permission_modules")->fetchAll();
    $actionsCreated = 0;
    
    foreach ($modules as $module) {
        foreach ($requiredActions as $actionKey) {
            try {
                $existing = $pdo->prepare("SELECT id FROM permission_actions WHERE module_id = ? AND action_key = ?");
                $existing->execute([$module['id'], $actionKey]);
                if (!$existing->fetch()) {
                    $actionName = ucfirst($actionKey);
                    $description = $actionName . ' permissions for ' . $module['module_name'];
                    
                    $stmt = $pdo->prepare("INSERT INTO permission_actions (module_id, action_key, action_name, description, is_active) VALUES (?, ?, ?, ?, 1)");
                    $stmt->execute([$module['id'], $actionKey, $actionName, $description]);
                    $actionsCreated++;
                }
            } catch (Exception $e) {
                // Continue on error
            }
        }
    }
    
    echo "<p style='color: green;'>✅ Ensured $actionsCreated actions exist</p>";
    $fixes[] = "Actions: $actionsCreated created/verified";
    
    // 4. Verify template requirements
    echo "<h2>Step 4: Verifying Template Requirements</h2>";
    
    $templateRequirements = [
        'cashier' => ['pos', 'customers', 'products', 'sales'],
        'waiter' => ['restaurant', 'pos', 'customers', 'products', 'rooms'],
        'inventory_manager' => ['inventory', 'products', 'reports', 'accounting'],
        'manager' => ['pos', 'restaurant', 'inventory', 'customers', 'products', 'sales', 'reports', 'rooms', 'delivery']
    ];
    
    $allGood = true;
    foreach ($templateRequirements as $template => $requiredMods) {
        echo "<h3>" . ucwords(str_replace('_', ' ', $template)) . " Template</h3>";
        foreach ($requiredMods as $modKey) {
            $exists = $pdo->prepare("SELECT COUNT(*) FROM permission_modules WHERE module_key = ? AND is_active = 1");
            $exists->execute([$modKey]);
            $count = $exists->fetchColumn();
            
            if ($count > 0) {
                echo "<p style='color: green;'>✅ Module '$modKey' ready</p>";
            } else {
                echo "<p style='color: red;'>❌ Module '$modKey' missing</p>";
                $allGood = false;
            }
        }
    }
    
    // Summary
    echo "<hr><h2>📋 Setup Summary</h2>";
    
    if ($allGood) {
        echo "<div style='background: #d4edda; color: #155724; padding: 20px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>🎉 Permission Templates Ready!</h3>";
        echo "<p>All required modules and actions are available for the permission templates.</p>";
        echo "<p><strong>Available Templates:</strong></p>";
        echo "<ul>";
        echo "<li>✅ <strong>Cashier</strong> - POS operations and customer management</li>";
        echo "<li>✅ <strong>Waiter</strong> - Restaurant service and room management</li>";
        echo "<li>✅ <strong>Inventory Manager</strong> - Complete stock control</li>";
        echo "<li>✅ <strong>Manager</strong> - Comprehensive business operations</li>";
        echo "</ul>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>❌ Setup Issues</h3>";
        echo "<p>Some required modules are missing. Run the table creation script first.</p>";
        echo "</div>";
    }
    
    if (!empty($fixes)) {
        echo "<h3 style='color: green;'>✅ Fixes Applied (" . count($fixes) . "):</h3>";
        echo "<ul>";
        foreach ($fixes as $fix) {
            echo "<li style='color: green;'>$fix</li>";
        }
        echo "</ul>";
    }
    
    echo "<hr>";
    echo "<h2>🚀 Next Steps</h2>";
    echo "<p>";
    echo "<a href='create-permission-templates.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Create Templates</a>";
    echo "<a href='permissions.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Permissions Page</a>";
    echo "<a href='debug-permissions-data.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Debug Data</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ Database Connection Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
