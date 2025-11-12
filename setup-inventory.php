<?php
/**
 * Inventory System Setup
 * Creates required tables and sample data
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>📦 Inventory System Setup</h1>";

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=wapos;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $created = [];
    $errors = [];
    
    // 1. Create required tables
    echo "<h2>Creating Required Tables</h2>";
    
    $tables = [
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
                INDEX idx_product_id (product_id),
                INDEX idx_movement_type (movement_type),
                INDEX idx_created_at (created_at)
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
                INDEX idx_supplier_id (supplier_id),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at)
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
                INDEX idx_purchase_order_id (purchase_order_id),
                INDEX idx_product_id (product_id)
            )"
    ];
    
    foreach ($tables as $tableName => $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✅ Created/verified table: $tableName</p>";
            $created[] = $tableName;
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error creating $tableName: " . $e->getMessage() . "</p>";
            $errors[] = "$tableName: " . $e->getMessage();
        }
    }
    
    // 2. Ensure products table has required columns
    echo "<h2>Updating Products Table</h2>";
    
    $productColumns = [
        'stock_quantity' => "ALTER TABLE products ADD COLUMN stock_quantity DECIMAL(10,3) DEFAULT 0",
        'reorder_level' => "ALTER TABLE products ADD COLUMN reorder_level DECIMAL(10,3) DEFAULT 10",
        'reorder_quantity' => "ALTER TABLE products ADD COLUMN reorder_quantity DECIMAL(10,3) DEFAULT 50",
        'cost_price' => "ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0",
        'unit' => "ALTER TABLE products ADD COLUMN unit VARCHAR(20) DEFAULT 'pcs'"
    ];
    
    foreach ($productColumns as $column => $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✅ Added column: $column to products table</p>";
            $created[] = "products.$column";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "<p style='color: blue;'>ℹ️ Column $column already exists</p>";
            } else {
                echo "<p style='color: red;'>❌ Error adding column $column: " . $e->getMessage() . "</p>";
                $errors[] = "products.$column: " . $e->getMessage();
            }
        }
    }
    
    // 3. Insert sample data
    echo "<h2>Creating Sample Data</h2>";
    
    // Sample suppliers
    $sampleSuppliers = [
        ['ABC Wholesale', 'John Smith', 'john@abcwholesale.com', '+1234567890', '123 Business St', 'TAX123456', '30 days'],
        ['XYZ Distributors', 'Jane Doe', 'jane@xyzdist.com', '+0987654321', '456 Commerce Ave', 'TAX789012', '15 days'],
        ['Global Supply Co', 'Mike Johnson', 'mike@globalsupply.com', '+1122334455', '789 Trade Blvd', 'TAX345678', '45 days']
    ];
    
    foreach ($sampleSuppliers as $supplier) {
        try {
            $existing = $pdo->prepare("SELECT id FROM suppliers WHERE name = ?");
            $existing->execute([$supplier[0]]);
            if (!$existing->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, email, phone, address, tax_number, payment_terms) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute($supplier);
                echo "<p style='color: green;'>✅ Added supplier: {$supplier[0]}</p>";
                $created[] = "Supplier: {$supplier[0]}";
            } else {
                echo "<p style='color: blue;'>ℹ️ Supplier exists: {$supplier[0]}</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error adding supplier {$supplier[0]}: " . $e->getMessage() . "</p>";
        }
    }
    
    // Update existing products with inventory data
    try {
        $pdo->exec("UPDATE products SET stock_quantity = 100, reorder_level = 20, reorder_quantity = 50, cost_price = selling_price * 0.6, unit = 'pcs' WHERE stock_quantity = 0");
        echo "<p style='color: green;'>✅ Updated existing products with inventory data</p>";
        $created[] = "Product inventory data updated";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error updating products: " . $e->getMessage() . "</p>";
    }
    
    // Summary
    echo "<hr><h2>📋 Setup Summary</h2>";
    
    if (!empty($created)) {
        echo "<h3 style='color: green;'>✅ Successfully Created (" . count($created) . "):</h3>";
        echo "<ul>";
        foreach ($created as $item) {
            echo "<li style='color: green;'>$item</li>";
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
        echo "<h3>🎉 Inventory System Setup Complete!</h3>";
        echo "<p>All required tables and sample data have been created successfully.</p>";
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<h2>🚀 Test Inventory System</h2>";
    echo "<p><a href='inventory.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Inventory Management</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ Database Connection Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
