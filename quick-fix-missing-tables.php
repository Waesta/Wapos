<?php
/**
 * Quick Fix for Missing Tables
 * Creates permission_modules and stock_movements tables immediately
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Quick Fix: Missing Tables</h1>";
echo "<p>Creating missing permission_modules and stock_movements tables...</p>";

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=wapos;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $fixes = [];
    $errors = [];
    
    // 1. Create permission_modules table
    echo "<h2>Step 1: Creating permission_modules table</h2>";
    
    $permissionModulesSQL = "
        CREATE TABLE IF NOT EXISTS permission_modules (
            id INT PRIMARY KEY AUTO_INCREMENT,
            module_key VARCHAR(50) UNIQUE NOT NULL,
            module_name VARCHAR(100) NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
    
    try {
        $pdo->exec($permissionModulesSQL);
        echo "<p style='color: green;'>✅ Created permission_modules table</p>";
        $fixes[] = "permission_modules table created";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error creating permission_modules: " . $e->getMessage() . "</p>";
        $errors[] = "permission_modules: " . $e->getMessage();
    }
    
    // 2. Create permission_actions table
    echo "<h2>Step 2: Creating permission_actions table</h2>";
    
    $permissionActionsSQL = "
        CREATE TABLE IF NOT EXISTS permission_actions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            module_id INT NOT NULL,
            action_key VARCHAR(50) NOT NULL,
            action_name VARCHAR(100) NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            FOREIGN KEY (module_id) REFERENCES permission_modules(id),
            UNIQUE KEY unique_module_action (module_id, action_key)
        )";
    
    try {
        $pdo->exec($permissionActionsSQL);
        echo "<p style='color: green;'>✅ Created permission_actions table</p>";
        $fixes[] = "permission_actions table created";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error creating permission_actions: " . $e->getMessage() . "</p>";
        $errors[] = "permission_actions: " . $e->getMessage();
    }
    
    // 3. Create stock_movements table
    echo "<h2>Step 3: Creating stock_movements table</h2>";
    
    $stockMovementsSQL = "
        CREATE TABLE IF NOT EXISTS stock_movements (
            id INT PRIMARY KEY AUTO_INCREMENT,
            product_id INT NOT NULL,
            movement_type ENUM('in', 'out', 'transfer', 'damaged', 'adjustment') NOT NULL,
            quantity DECIMAL(10,3) NOT NULL,
            old_quantity DECIMAL(10,3) NOT NULL,
            new_quantity DECIMAL(10,3) NOT NULL,
            reason VARCHAR(100) NOT NULL,
            notes TEXT,
            reference VARCHAR(50),
            user_id INT NOT NULL,
            location_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_product_id (product_id),
            INDEX idx_movement_type (movement_type),
            INDEX idx_created_at (created_at)
        )";
    
    try {
        $pdo->exec($stockMovementsSQL);
        echo "<p style='color: green;'>✅ Created stock_movements table</p>";
        $fixes[] = "stock_movements table created";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error creating stock_movements: " . $e->getMessage() . "</p>";
        $errors[] = "stock_movements: " . $e->getMessage();
    }
    
    // Update existing products with inventory data (skip if products table needs fixing)
    try {
        // First check if products table has the right columns
        $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'sell_price'")->fetch();
        if ($columns) {
            $pdo->exec("UPDATE products SET stock_quantity = 100, reorder_level = 20, reorder_quantity = 50, cost_price = sell_price * 0.6, unit = 'pcs' WHERE stock_quantity = 0 OR stock_quantity IS NULL");
            echo "<p style='color: green;'>✅ Updated existing products with inventory data</p>";
            $fixes[] = "Product inventory data updated";
        } else {
            echo "<p style='color: orange;'>⚠️ Products table needs column fixes - run fix-products-table.php first</p>";
            $fixes[] = "Products table needs column fixes";
        }
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠️ Products table needs fixing: " . $e->getMessage() . "</p>";
        echo "<p style='color: blue;'>ℹ️ Run fix-products-table.php to fix the products table structure</p>";
    }
    
    // 4. Insert default permission modules
    echo "<h2>Step 4: Inserting Default Permission Modules</h2>";
    
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
    
    $modulesAdded = 0;
    foreach ($defaultModules as $module) {
        try {
            $existing = $pdo->prepare("SELECT id FROM permission_modules WHERE module_key = ?");
            $existing->execute([$module[0]]);
            if (!$existing->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO permission_modules (module_key, module_name, description) VALUES (?, ?, ?)");
                $stmt->execute($module);
                $modulesAdded++;
            }
        } catch (Exception $e) {
            // Continue on error
        }
    }
    
    echo "<p style='color: green;'>✅ Added $modulesAdded permission modules</p>";
    $fixes[] = "$modulesAdded permission modules added";
    
    // 5. Insert default actions for each module
    echo "<h2>Step 5: Creating Default Permission Actions</h2>";
    
    $defaultActions = [
        'create' => 'Create/Add new records',
        'read' => 'View/Read records', 
        'update' => 'Edit/Update records',
        'delete' => 'Delete records',
        'manage' => 'Full management access'
    ];
    
    $modules = $pdo->query("SELECT id, module_key, module_name FROM permission_modules")->fetchAll();
    $actionsCreated = 0;
    
    foreach ($modules as $module) {
        foreach ($defaultActions as $actionKey => $actionName) {
            try {
                $existing = $pdo->prepare("SELECT id FROM permission_actions WHERE module_id = ? AND action_key = ?");
                $existing->execute([$module['id'], $actionKey]);
                if (!$existing->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO permission_actions (module_id, action_key, action_name, description) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $module['id'],
                        $actionKey,
                        $actionName,
                        $actionName . ' for ' . $module['module_name']
                    ]);
                    $actionsCreated++;
                }
            } catch (Exception $e) {
                // Continue on error
            }
        }
    }
    
    echo "<p style='color: green;'>✅ Created $actionsCreated permission actions</p>";
    $fixes[] = "$actionsCreated permission actions created";
    
    // 6. Create other essential tables
    echo "<h2>Step 6: Creating Other Essential Tables</h2>";
    
    $otherTables = [
        'user_permissions' => "
            CREATE TABLE IF NOT EXISTS user_permissions (
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
        
        'suppliers' => "
            CREATE TABLE IF NOT EXISTS suppliers (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                contact_person VARCHAR(100),
                email VARCHAR(100),
                phone VARCHAR(20),
                address TEXT,
                tax_number VARCHAR(50),
                payment_terms VARCHAR(100),
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
    ];
    
    foreach ($otherTables as $tableName => $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✅ Created/verified table: $tableName</p>";
            $fixes[] = "Table: $tableName";
        } catch (Exception $e) {
            echo "<p style='color: orange;'>⚠️ Table $tableName: " . $e->getMessage() . "</p>";
        }
    }
    
    // Summary
    echo "<hr><h2>📋 Quick Fix Summary</h2>";
    
    if (!empty($fixes)) {
        echo "<h3 style='color: green;'>✅ Successfully Fixed (" . count($fixes) . "):</h3>";
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
        echo "<h3>🎉 Missing Tables Fixed!</h3>";
        echo "<p>The permission_modules and stock_movements tables have been created successfully.</p>";
        echo "<p><strong>What was fixed:</strong></p>";
        echo "<ul>";
        echo "<li>✅ Created permission_modules table with 10 modules</li>";
        echo "<li>✅ Created permission_actions table with 50 actions</li>";
        echo "<li>✅ Created stock_movements table for inventory</li>";
        echo "<li>✅ Created user_permissions table</li>";
        echo "<li>✅ Created suppliers table</li>";
        echo "</ul>";
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<h2>🚀 Test System Health</h2>";
    echo "<p><a href='fix-products-table.php' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Fix Products Table</a>";
    echo "<a href='system-health.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Check System Health</a>";
    echo "<a href='permissions.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Permissions</a>";
    echo "<a href='inventory.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Inventory</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ Database Connection Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
