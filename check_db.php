<?php
require_once 'includes/bootstrap.php';

try {
    // Test database connection
    $db = Database::getInstance();
    echo "✅ Database connection successful!<br>";
    
    // Check if products table exists
    $tables = $db->fetchAll("SHOW TABLES LIKE 'products'");
    
    if (empty($tables)) {
        echo "❌ Products table does not exist. Creating it now...<br>";
        
        // Create products table if it doesn't exist
        $sql = "
        CREATE TABLE IF NOT EXISTS products (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_id INT UNSIGNED,
            name VARCHAR(200) NOT NULL,
            description TEXT,
            sku VARCHAR(50) UNIQUE,
            barcode VARCHAR(50) UNIQUE,
            cost_price DECIMAL(15,2) DEFAULT 0,
            selling_price DECIMAL(15,2) NOT NULL,
            stock_quantity INT DEFAULT 0,
            min_stock_level INT DEFAULT 10,
            unit VARCHAR(20) DEFAULT 'pcs',
            tax_rate DECIMAL(5,2) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            image VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            supplier_id INT UNSIGNED,
            has_expiry TINYINT(1) DEFAULT 0,
            alert_before_days INT DEFAULT 30,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $db->query($sql);
        echo "✅ Products table created successfully!<br>";
    } else {
        echo "✅ Products table exists.<br>";
    }
    
    // Show products table structure
    echo "<h3>Products Table Structure:</h3>";
    $columns = $db->fetchAll("DESCRIBE products");
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Check your database configuration in config.php<br>";
    echo "DB_HOST: " . DB_HOST . "<br>";
    echo "DB_NAME: " . DB_NAME . "<br>";
    echo "DB_USER: " . DB_USER . "<br>";
}
?>
