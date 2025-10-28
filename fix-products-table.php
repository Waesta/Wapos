<?php
/**
 * Fix Products Table Structure
 * Checks and fixes the products table for inventory management
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Fix Products Table Structure</h1>";

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=wapos;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $fixes = [];
    $errors = [];
    
    // 1. Check current products table structure
    echo "<h2>Step 1: Checking Products Table Structure</h2>";
    
    try {
        $columns = $pdo->query("DESCRIBE products")->fetchAll();
        echo "<p style='color: blue;'>ℹ️ Current products table columns:</p>";
        echo "<ul>";
        foreach ($columns as $column) {
            echo "<li><strong>{$column['Field']}</strong> - {$column['Type']}</li>";
        }
        echo "</ul>";
        
        // Check if price column exists
        $priceExists = false;
        $sellPriceExists = false;
        foreach ($columns as $column) {
            if ($column['Field'] === 'price') {
                $priceExists = true;
            }
            if ($column['Field'] === 'sell_price') {
                $sellPriceExists = true;
            }
        }
        
        echo "<p>Price column exists: " . ($priceExists ? "✅ Yes" : "❌ No") . "</p>";
        echo "<p>Sell_price column exists: " . ($sellPriceExists ? "✅ Yes" : "❌ No") . "</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error checking table structure: " . $e->getMessage() . "</p>";
        $errors[] = "Table structure check: " . $e->getMessage();
    }
    
    // 2. Add missing columns to products table
    echo "<h2>Step 2: Adding Missing Columns</h2>";
    
    $requiredColumns = [
        'sell_price' => "ALTER TABLE products ADD COLUMN sell_price DECIMAL(10,2) DEFAULT 0",
        'cost_price' => "ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0",
        'stock_quantity' => "ALTER TABLE products ADD COLUMN stock_quantity DECIMAL(10,3) DEFAULT 0",
        'reorder_level' => "ALTER TABLE products ADD COLUMN reorder_level DECIMAL(10,3) DEFAULT 10",
        'reorder_quantity' => "ALTER TABLE products ADD COLUMN reorder_quantity DECIMAL(10,3) DEFAULT 50",
        'unit' => "ALTER TABLE products ADD COLUMN unit VARCHAR(20) DEFAULT 'pcs'",
        'barcode' => "ALTER TABLE products ADD COLUMN barcode VARCHAR(50)",
        'sku' => "ALTER TABLE products ADD COLUMN sku VARCHAR(50)",
        'is_active' => "ALTER TABLE products ADD COLUMN is_active BOOLEAN DEFAULT TRUE"
    ];
    
    foreach ($requiredColumns as $column => $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✅ Added column: $column</p>";
            $fixes[] = "Added column: $column";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "<p style='color: blue;'>ℹ️ Column $column already exists</p>";
            } else {
                echo "<p style='color: red;'>❌ Error adding column $column: " . $e->getMessage() . "</p>";
                $errors[] = "Column $column: " . $e->getMessage();
            }
        }
    }
    
    // 3. Check if products table has any data
    echo "<h2>Step 3: Checking Products Data</h2>";
    
    try {
        $productCount = $pdo->query("SELECT COUNT(*) as count FROM products")->fetch();
        echo "<p>Current products in table: <strong>{$productCount['count']}</strong></p>";
        
        if ($productCount['count'] == 0) {
            echo "<h3>Adding Sample Products</h3>";
            
            $sampleProducts = [
                ['Coffee', 'Hot brewed coffee', 'beverages', 2.50, 1.00, 100, 20, 50, 'cup'],
                ['Sandwich', 'Ham and cheese sandwich', 'food', 5.99, 2.50, 50, 10, 25, 'pcs'],
                ['Water Bottle', 'Bottled water 500ml', 'beverages', 1.50, 0.50, 200, 50, 100, 'bottle'],
                ['Burger', 'Classic beef burger', 'food', 8.99, 4.00, 30, 5, 15, 'pcs'],
                ['Fries', 'French fries portion', 'food', 3.99, 1.50, 75, 15, 30, 'portion']
            ];
            
            foreach ($sampleProducts as $product) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO products (name, description, category, sell_price, cost_price, stock_quantity, reorder_level, reorder_quantity, unit, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute($product);
                    echo "<p style='color: green;'>✅ Added sample product: {$product[0]}</p>";
                    $fixes[] = "Sample product: {$product[0]}";
                } catch (Exception $e) {
                    echo "<p style='color: red;'>❌ Error adding product {$product[0]}: " . $e->getMessage() . "</p>";
                }
            }
        } else {
            echo "<h3>Updating Existing Products</h3>";
            
            // Update existing products with default inventory values
            try {
                $updateSQL = "
                    UPDATE products 
                    SET 
                        sell_price = COALESCE(sell_price, 5.00),
                        cost_price = COALESCE(cost_price, sell_price * 0.6, 3.00),
                        stock_quantity = COALESCE(stock_quantity, 100),
                        reorder_level = COALESCE(reorder_level, 20),
                        reorder_quantity = COALESCE(reorder_quantity, 50),
                        unit = COALESCE(unit, 'pcs'),
                        is_active = COALESCE(is_active, 1)
                    WHERE sell_price IS NULL OR sell_price = 0 OR cost_price IS NULL OR cost_price = 0
                ";
                
                $result = $pdo->exec($updateSQL);
                echo "<p style='color: green;'>✅ Updated $result existing products with inventory data</p>";
                $fixes[] = "Updated $result existing products";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Error updating existing products: " . $e->getMessage() . "</p>";
                $errors[] = "Update existing products: " . $e->getMessage();
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error checking products data: " . $e->getMessage() . "</p>";
        $errors[] = "Products data check: " . $e->getMessage();
    }
    
    // 4. Create categories table if it doesn't exist
    echo "<h2>Step 4: Creating Categories Table</h2>";
    
    $categoriesSQL = "
        CREATE TABLE IF NOT EXISTS categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
    
    try {
        $pdo->exec($categoriesSQL);
        echo "<p style='color: green;'>✅ Created/verified categories table</p>";
        $fixes[] = "Categories table created";
        
        // Add sample categories
        $sampleCategories = [
            ['food', 'Food items and meals'],
            ['beverages', 'Drinks and beverages'],
            ['retail', 'Retail products'],
            ['services', 'Service items']
        ];
        
        foreach ($sampleCategories as $category) {
            try {
                $existing = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                $existing->execute([$category[0]]);
                if (!$existing->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                    $stmt->execute($category);
                    echo "<p style='color: green;'>✅ Added category: {$category[0]}</p>";
                    $fixes[] = "Category: {$category[0]}";
                }
            } catch (Exception $e) {
                // Continue on error
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error creating categories table: " . $e->getMessage() . "</p>";
        $errors[] = "Categories table: " . $e->getMessage();
    }
    
    // 5. Final verification
    echo "<h2>Step 5: Final Verification</h2>";
    
    try {
        $finalCheck = $pdo->query("
            SELECT 
                COUNT(*) as total_products,
                SUM(CASE WHEN sell_price > 0 THEN 1 ELSE 0 END) as products_with_price,
                SUM(CASE WHEN stock_quantity > 0 THEN 1 ELSE 0 END) as products_with_stock
            FROM products
        ")->fetch();
        
        echo "<p><strong>Final Status:</strong></p>";
        echo "<ul>";
        echo "<li>Total products: {$finalCheck['total_products']}</li>";
        echo "<li>Products with prices: {$finalCheck['products_with_price']}</li>";
        echo "<li>Products with stock: {$finalCheck['products_with_stock']}</li>";
        echo "</ul>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error in final verification: " . $e->getMessage() . "</p>";
    }
    
    // Summary
    echo "<hr><h2>📋 Products Table Fix Summary</h2>";
    
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
        echo "<h3>🎉 Products Table Fixed!</h3>";
        echo "<p>The products table now has all required columns for inventory management.</p>";
        echo "<p><strong>What was fixed:</strong></p>";
        echo "<ul>";
        echo "<li>✅ Added sell_price column (replaces missing 'price' column)</li>";
        echo "<li>✅ Added cost_price column for inventory costing</li>";
        echo "<li>✅ Added stock_quantity, reorder_level, reorder_quantity</li>";
        echo "<li>✅ Added unit, barcode, sku columns</li>";
        echo "<li>✅ Added is_active column</li>";
        echo "<li>✅ Created categories table</li>";
        echo "<li>✅ Added sample data if table was empty</li>";
        echo "</ul>";
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<h2>🚀 Test Fixed System</h2>";
    echo "<p><a href='quick-fix-missing-tables.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Re-run Table Fix</a>";
    echo "<a href='inventory.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Inventory</a>";
    echo "<a href='system-health.php' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Check System Health</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ Database Connection Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
