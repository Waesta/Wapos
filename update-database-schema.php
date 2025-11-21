<?php
/**
 * WAPOS Database Schema Update
 * Creates all missing tables according to specifications
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🗄️ WAPOS Database Schema Update</h1>";
echo "<p>Creating missing tables according to WAPOS specifications...</p>";

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=wapos;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $created = [];
    $errors = [];

    // Ensure users.role enum includes all operational roles
    $roleValues = [
        'admin',
        'manager',
        'accountant',
        'cashier',
        'waiter',
        'inventory_manager',
        'rider',
        'frontdesk',
        'housekeeping_manager',
        'housekeeping_staff',
        'maintenance_manager',
        'maintenance_staff',
        'technician',
        'engineer',
        'developer'
    ];

    $enumList = "'" . implode("','", array_map(fn($role) => addslashes($role), $roleValues)) . "'";
    try {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM($enumList) NOT NULL DEFAULT 'cashier'");
        echo "<p style='color: green;'>✅ users.role enum synchronized</p>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠️ Unable to alter users.role enum: " . htmlspecialchars($e->getMessage()) . "</p>";
        $errors[] = "users.role enum: " . $e->getMessage();
    }
    
    // 1. Stock Management Tables
    echo "<h2>📦 Stock Management Tables</h2>";
    
    $stockTables = [
        'stock_movements' => "
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
                FOREIGN KEY (product_id) REFERENCES products(id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (location_id) REFERENCES locations(id)
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
            )",
        
        'purchase_orders' => "
            CREATE TABLE IF NOT EXISTS purchase_orders (
                id INT PRIMARY KEY AUTO_INCREMENT,
                po_number VARCHAR(50) UNIQUE NOT NULL,
                supplier_id INT NOT NULL,
                status ENUM('pending', 'approved', 'ordered', 'received', 'cancelled') DEFAULT 'pending',
                order_date DATE,
                expected_date DATE,
                received_date DATE,
                total_amount DECIMAL(10,2) DEFAULT 0,
                notes TEXT,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
                FOREIGN KEY (created_by) REFERENCES users(id)
            )",
        
        'purchase_order_items' => "
            CREATE TABLE IF NOT EXISTS purchase_order_items (
                id INT PRIMARY KEY AUTO_INCREMENT,
                purchase_order_id INT NOT NULL,
                product_id INT NOT NULL,
                quantity DECIMAL(10,3) NOT NULL,
                unit_cost DECIMAL(10,2) NOT NULL,
                received_quantity DECIMAL(10,3) DEFAULT 0,
                subtotal DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id)
            )"
    ];
    
    foreach ($stockTables as $tableName => $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✅ Created/verified table: $tableName</p>";
            $created[] = $tableName;
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error creating $tableName: " . $e->getMessage() . "</p>";
            $errors[] = "$tableName: " . $e->getMessage();
        }
    }
    
    // 2. Accounting & GL Tables
    echo "<h2>💰 Accounting & General Ledger Tables</h2>";
    
    $accountingTables = [
        'chart_of_accounts' => "
            CREATE TABLE IF NOT EXISTS chart_of_accounts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                account_code VARCHAR(20) UNIQUE NOT NULL,
                account_name VARCHAR(100) NOT NULL,
                account_type ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL,
                parent_id INT,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (parent_id) REFERENCES chart_of_accounts(id)
            )",
        
        'journal_entries' => "
            CREATE TABLE IF NOT EXISTS journal_entries (
                id INT PRIMARY KEY AUTO_INCREMENT,
                reference VARCHAR(50) NOT NULL,
                description TEXT NOT NULL,
                entry_date DATE NOT NULL,
                total_amount DECIMAL(15,2) NOT NULL,
                status ENUM('draft', 'posted', 'reversed') DEFAULT 'draft',
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            )",
        
        'journal_lines' => "
            CREATE TABLE IF NOT EXISTS journal_lines (
                id INT PRIMARY KEY AUTO_INCREMENT,
                journal_entry_id INT NOT NULL,
                account_id INT NOT NULL,
                debit_amount DECIMAL(15,2) DEFAULT 0,
                credit_amount DECIMAL(15,2) DEFAULT 0,
                description TEXT,
                is_reconciled BOOLEAN DEFAULT FALSE,
                reconciled_date DATE,
                FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
                FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id)
            )",
        
        'account_reconciliations' => "
            CREATE TABLE IF NOT EXISTS account_reconciliations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                account_id INT NOT NULL,
                reconciliation_date DATE NOT NULL,
                statement_balance DECIMAL(15,2) NOT NULL,
                book_balance DECIMAL(15,2) NOT NULL,
                difference DECIMAL(15,2) GENERATED ALWAYS AS (statement_balance - book_balance) STORED,
                reconciled_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id),
                FOREIGN KEY (reconciled_by) REFERENCES users(id)
            )",
        
        'expense_categories' => "
            CREATE TABLE IF NOT EXISTS expense_categories (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                account_id INT,
                is_active BOOLEAN DEFAULT TRUE,
                FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id)
            )"
    ];
    
    foreach ($accountingTables as $tableName => $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✅ Created/verified table: $tableName</p>";
            $created[] = $tableName;
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error creating $tableName: " . $e->getMessage() . "</p>";
            $errors[] = "$tableName: " . $e->getMessage();
        }
    }
    
    // 3. Room Management Tables
    echo "<h2>🏨 Room Management Tables</h2>";
    
    $roomTables = [
        'room_types' => "
            CREATE TABLE IF NOT EXISTS room_types (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                base_rate DECIMAL(10,2) NOT NULL,
                max_occupancy INT DEFAULT 2,
                amenities JSON,
                is_active BOOLEAN DEFAULT TRUE
            )",
        
        'rooms' => "
            CREATE TABLE IF NOT EXISTS rooms (
                id INT PRIMARY KEY AUTO_INCREMENT,
                room_number VARCHAR(20) UNIQUE NOT NULL,
                room_type_id INT NOT NULL,
                floor VARCHAR(10),
                status ENUM('available', 'occupied', 'maintenance', 'cleaning') DEFAULT 'available',
                is_active BOOLEAN DEFAULT TRUE,
                FOREIGN KEY (room_type_id) REFERENCES room_types(id)
            )",
        
        'room_bookings' => "
            CREATE TABLE IF NOT EXISTS room_bookings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                booking_number VARCHAR(50) UNIQUE NOT NULL,
                room_id INT NOT NULL,
                customer_id INT NOT NULL,
                check_in_date DATE NOT NULL,
                check_out_date DATE NOT NULL,
                actual_check_in DATETIME,
                actual_check_out DATETIME,
                adults INT DEFAULT 1,
                children INT DEFAULT 0,
                rate_per_night DECIMAL(10,2) NOT NULL,
                total_amount DECIMAL(10,2) NOT NULL,
                status ENUM('confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'confirmed',
                notes TEXT,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (room_id) REFERENCES rooms(id),
                FOREIGN KEY (customer_id) REFERENCES customers(id),
                FOREIGN KEY (created_by) REFERENCES users(id)
            )",
        
        'room_folios' => "
            CREATE TABLE IF NOT EXISTS room_folios (
                id INT PRIMARY KEY AUTO_INCREMENT,
                booking_id INT NOT NULL,
                item_type ENUM('room_charge', 'service', 'tax', 'deposit', 'payment') NOT NULL,
                description VARCHAR(200) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                quantity DECIMAL(10,2) DEFAULT 1,
                date_charged DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (booking_id) REFERENCES room_bookings(id) ON DELETE CASCADE
            )"
    ];
    
    foreach ($roomTables as $tableName => $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✅ Created/verified table: $tableName</p>";
            $created[] = $tableName;
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error creating $tableName: " . $e->getMessage() . "</p>";
            $errors[] = "$tableName: " . $e->getMessage();
        }
    }
    
    // 4. Enhanced Permission Tables
    echo "<h2>🔐 Enhanced Permission Management Tables</h2>";
    
    $permissionTables = [
        'permission_modules' => "
            CREATE TABLE IF NOT EXISTS permission_modules (
                id INT PRIMARY KEY AUTO_INCREMENT,
                module_key VARCHAR(50) UNIQUE NOT NULL,
                module_name VARCHAR(100) NOT NULL,
                description TEXT,
                is_active BOOLEAN DEFAULT TRUE
            )",
        
        'permission_actions' => "
            CREATE TABLE IF NOT EXISTS permission_actions (
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
        
        'permission_audit_log' => "
            CREATE TABLE IF NOT EXISTS permission_audit_log (
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
    
    foreach ($permissionTables as $tableName => $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✅ Created/verified table: $tableName</p>";
            $created[] = $tableName;
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error creating $tableName: " . $e->getMessage() . "</p>";
            $errors[] = "$tableName: " . $e->getMessage();
        }
    }
    
    // 5. CRM & Loyalty Tables
    echo "<h2>👥 CRM & Loyalty Program Tables</h2>";
    
    $crmTables = [
        'customer_groups' => "
            CREATE TABLE IF NOT EXISTS customer_groups (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                discount_percentage DECIMAL(5,2) DEFAULT 0,
                is_active BOOLEAN DEFAULT TRUE
            )",
        
        'loyalty_programs' => "
            CREATE TABLE IF NOT EXISTS loyalty_programs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                points_per_dollar DECIMAL(5,2) DEFAULT 1,
                redemption_rate DECIMAL(5,2) DEFAULT 0.01,
                min_points_redemption INT DEFAULT 100,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        
        'customer_loyalty_points' => "
            CREATE TABLE IF NOT EXISTS customer_loyalty_points (
                id INT PRIMARY KEY AUTO_INCREMENT,
                customer_id INT NOT NULL,
                program_id INT NOT NULL,
                points_earned INT DEFAULT 0,
                points_redeemed INT DEFAULT 0,
                points_balance INT GENERATED ALWAYS AS (points_earned - points_redeemed) STORED,
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                FOREIGN KEY (program_id) REFERENCES loyalty_programs(id),
                UNIQUE KEY unique_customer_program (customer_id, program_id)
            )",
        
        'loyalty_transactions' => "
            CREATE TABLE IF NOT EXISTS loyalty_transactions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                customer_id INT NOT NULL,
                program_id INT NOT NULL,
                transaction_type ENUM('earn', 'redeem', 'adjust', 'expire') NOT NULL,
                points INT NOT NULL,
                sale_id INT,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id),
                FOREIGN KEY (program_id) REFERENCES loyalty_programs(id),
                FOREIGN KEY (sale_id) REFERENCES sales(id)
            )"
    ];
    
    foreach ($crmTables as $tableName => $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✅ Created/verified table: $tableName</p>";
            $created[] = $tableName;
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error creating $tableName: " . $e->getMessage() . "</p>";
            $errors[] = "$tableName: " . $e->getMessage();
        }
    }
    
    // 6. Insert Default Data
    echo "<h2>📊 Inserting Default Data</h2>";
    
    // Default Chart of Accounts
    $defaultAccounts = [
        ['1000', 'Cash', 'asset'],
        ['1100', 'Accounts Receivable', 'asset'],
        ['1200', 'Inventory', 'asset'],
        ['1500', 'Equipment', 'asset'],
        ['2000', 'Accounts Payable', 'liability'],
        ['2100', 'Sales Tax Payable', 'liability'],
        ['3000', 'Owner Equity', 'equity'],
        ['4000', 'Sales Revenue', 'revenue'],
        ['4100', 'Service Revenue', 'revenue'],
        ['5000', 'Cost of Goods Sold', 'expense'],
        ['6000', 'Operating Expenses', 'expense'],
        ['6100', 'Rent Expense', 'expense'],
        ['6200', 'Utilities Expense', 'expense']
    ];
    
    foreach ($defaultAccounts as $account) {
        try {
            $pdo->prepare("INSERT IGNORE INTO chart_of_accounts (account_code, account_name, account_type) VALUES (?, ?, ?)")
                ->execute($account);
        } catch (Exception $e) {
            // Ignore duplicate entries
        }
    }
    echo "<p style='color: green;'>✅ Default chart of accounts inserted</p>";
    
    // Default Permission Modules
    $defaultModules = [
        ['pos', 'Point of Sale', 'POS transactions and sales'],
        ['inventory', 'Inventory Management', 'Stock control and purchasing'],
        ['accounting', 'Accounting & Finance', 'Financial management and reporting'],
        ['users', 'User Management', 'User accounts and permissions'],
        ['customers', 'Customer Management', 'Customer data and CRM'],
        ['reports', 'Reports & Analytics', 'Business intelligence and reporting'],
        ['settings', 'System Settings', 'System configuration and preferences'],
        ['rooms', 'Room Management', 'Hotel room bookings and management'],
        ['restaurant', 'Restaurant Operations', 'Table service and kitchen management'],
        ['delivery', 'Delivery Management', 'Order delivery and logistics'],
        ['housekeeping', 'Housekeeping', 'Scheduling, room status, and task execution'],
        ['maintenance', 'Maintenance', 'Issue tracking, technician dispatch, and resolution'],
        ['frontdesk', 'Front Desk', 'Guest services, check-ins, and concierge workflows']
    ];
    
    foreach ($defaultModules as $module) {
        try {
            $pdo->prepare("INSERT IGNORE INTO permission_modules (module_key, module_name, description) VALUES (?, ?, ?)")
                ->execute($module);
        } catch (Exception $e) {
            // Ignore duplicate entries
        }
    }
    echo "<p style='color: green;'>✅ Default permission modules inserted</p>";

    // Default Permission Actions per module
    $moduleActionDefinitions = [
        'pos' => [
            ['view', 'View POS', 'View POS transactions and reports'],
            ['create', 'Create Sale', 'Create new POS transactions'],
            ['update', 'Update Sale', 'Modify existing POS transactions'],
            ['refund', 'Issue Refunds', 'Process POS refunds'],
            ['void', 'Void Sale', 'Void POS transactions']
        ],
        'inventory' => [
            ['view', 'View Inventory', 'View stock levels and movements'],
            ['create', 'Create Inventory Entry', 'Add new inventory records'],
            ['update', 'Update Inventory', 'Modify inventory records'],
            ['adjust_inventory', 'Adjust Inventory', 'Execute stock adjustments']
        ],
        'housekeeping' => [
            ['view', 'View Tasks', 'Access housekeeping dashboards and tasks'],
            ['create', 'Create Task', 'Create new housekeeping tasks'],
            ['update', 'Update Task', 'Update housekeeping tasks'],
            ['assign', 'Assign Task', 'Assign or reassign housekeeping tasks'],
            ['complete', 'Complete Task', 'Mark housekeeping tasks complete']
        ],
        'maintenance' => [
            ['view', 'View Requests', 'Access maintenance requests'],
            ['create', 'Create Request', 'Log new maintenance issues'],
            ['update', 'Update Request', 'Update maintenance request details'],
            ['assign', 'Assign Request', 'Assign maintenance requests to staff'],
            ['resolve', 'Resolve Request', 'Mark maintenance requests resolved']
        ],
        'frontdesk' => [
            ['view', 'View Front Desk', 'Access front desk dashboards'],
            ['create', 'Create Record', 'Create guest or stay records'],
            ['update', 'Update Record', 'Update guest or stay records']
        ],
        'users' => [
            ['view', 'View Users', 'View user directory'],
            ['create', 'Create User', 'Create new user accounts'],
            ['update', 'Update User', 'Update existing user accounts'],
            ['change_permissions', 'Change Permissions', 'Adjust user/group permissions']
        ]
    ];

    $getModuleIdStmt = $pdo->prepare("SELECT id FROM permission_modules WHERE module_key = ?");
    $insertActionStmt = $pdo->prepare("INSERT INTO permission_actions (module_id, action_key, action_name, description, is_active) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE action_name = VALUES(action_name), description = VALUES(description), is_active = VALUES(is_active)");

    foreach ($moduleActionDefinitions as $moduleKey => $actions) {
        if (!$getModuleIdStmt->execute([$moduleKey])) {
            continue;
        }
        $moduleId = $getModuleIdStmt->fetchColumn();
        if (!$moduleId) {
            continue;
        }
        foreach ($actions as $action) {
            [$actionKey, $actionName, $description] = $action;
            try {
                $insertActionStmt->execute([$moduleId, $actionKey, $actionName, $description]);
            } catch (Exception $e) {
                $errors[] = "permission_actions ({$moduleKey}:{$actionKey}) " . $e->getMessage();
            }
        }
    }
    
    // Default Room Types
    $defaultRoomTypes = [
        ['Standard Single', 'Standard room with single bed', 75.00, 1],
        ['Standard Double', 'Standard room with double bed', 95.00, 2],
        ['Deluxe Suite', 'Luxury suite with amenities', 150.00, 4],
        ['Family Room', 'Large room for families', 120.00, 6]
    ];
    
    foreach ($defaultRoomTypes as $roomType) {
        try {
            $pdo->prepare("INSERT IGNORE INTO room_types (name, description, base_rate, max_occupancy) VALUES (?, ?, ?, ?)")
                ->execute($roomType);
        } catch (Exception $e) {
            // Ignore duplicate entries
        }
    }
    echo "<p style='color: green;'>✅ Default room types inserted</p>";
    
    // Summary
    echo "<hr><h2>📋 Schema Update Summary</h2>";
    
    if (!empty($created)) {
        echo "<h3 style='color: green;'>✅ Tables Created/Verified (" . count($created) . "):</h3>";
        echo "<ul>";
        foreach ($created as $table) {
            echo "<li style='color: green;'>$table</li>";
        }
        echo "</ul>";
    }
    
    if (!empty($errors)) {
        echo "<h3 style='color: red;'>❌ Errors (" . count($errors) . "):</h3>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li style='color: red;'>$error</li>";
        }
        echo "</ul>";
    }
    
    if (empty($errors)) {
        echo "<div style='background: #d4edda; color: #155724; padding: 20px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>🎉 Database Schema Update Complete!</h3>";
        echo "<p>All required tables have been created according to WAPOS specifications.</p>";
        echo "<p><strong>Your system now supports:</strong></p>";
        echo "<ul>";
        echo "<li>✅ Complete Inventory Management with Stock Control</li>";
        echo "<li>✅ Full Accounting & General Ledger Integration</li>";
        echo "<li>✅ Room Management for Hotel Operations</li>";
        echo "<li>✅ Granular Permission Management System</li>";
        echo "<li>✅ CRM & Loyalty Program Management</li>";
        echo "<li>✅ Multi-location Support</li>";
        echo "</ul>";
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<h2>🚀 Next Steps</h2>";
    echo "<p><a href='inventory.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Inventory</a>";
    echo "<a href='accounting.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Accounting</a>";
    echo "<a href='manage-rooms.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Room Management</a>";
    echo "<a href='permissions.php' style='background: #6f42c1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Permissions</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ Database Connection Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
