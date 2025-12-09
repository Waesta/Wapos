-- Bar Management Schema Migration
-- Adds bartender role and bar management tables

-- Add bartender role to users table
ALTER TABLE users MODIFY COLUMN role ENUM(
    'admin',
    'manager',
    'accountant',
    'cashier',
    'waiter',
    'bartender',
    'inventory_manager',
    'rider',
    'frontdesk',
    'housekeeping_manager',
    'housekeeping_staff',
    'maintenance_manager',
    'maintenance_staff',
    'technician',
    'engineer',
    'developer',
    'front_office_manager',
    'guest_relations_manager',
    'concierge',
    'spa_manager',
    'spa_staff',
    'events_manager',
    'banquet_supervisor',
    'room_service_manager',
    'room_service_staff',
    'security_manager',
    'security_staff',
    'hr_manager',
    'hr_staff',
    'revenue_manager',
    'sales_manager',
    'sales_executive',
    'super_admin'
) DEFAULT 'cashier';

-- Add bar-specific columns to products table
ALTER TABLE products ADD COLUMN IF NOT EXISTS is_portioned TINYINT(1) DEFAULT 0 COMMENT 'Sold in portions (tots/shots/glasses)';
ALTER TABLE products ADD COLUMN IF NOT EXISTS bottle_size_ml INT NULL COMMENT 'Size in ml for liquor bottles';
ALTER TABLE products ADD COLUMN IF NOT EXISTS default_portion_ml DECIMAL(10,2) NULL COMMENT 'Default pour size in ml';
ALTER TABLE products ADD COLUMN IF NOT EXISTS is_recipe TINYINT(1) DEFAULT 0 COMMENT 'Is a cocktail/recipe';
ALTER TABLE products ADD COLUMN IF NOT EXISTS recipe_id INT UNSIGNED NULL COMMENT 'Link to bar_recipes';

-- Product portions table - defines how products can be sold
CREATE TABLE IF NOT EXISTS product_portions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    portion_name VARCHAR(100) NOT NULL,
    portion_size_ml DECIMAL(10,2) NULL COMMENT 'Size in ml for liquids',
    portion_quantity DECIMAL(10,4) NOT NULL DEFAULT 1 COMMENT 'How much of base unit this portion uses',
    selling_price DECIMAL(15,2) NOT NULL,
    cost_price DECIMAL(15,2) DEFAULT 0,
    is_default TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_product_portion (product_id, portion_name),
    INDEX idx_product (product_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Product yield configuration - expected yields per purchase unit
CREATE TABLE IF NOT EXISTS product_yields (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    purchase_unit VARCHAR(50) NOT NULL COMMENT 'e.g., bottle, crate, case',
    purchase_size_ml INT NULL COMMENT 'Size in ml for liquids',
    expected_portions INT NOT NULL COMMENT 'Expected number of portions per unit',
    portion_size_ml DECIMAL(10,2) NULL COMMENT 'Standard portion size',
    wastage_allowance_percent DECIMAL(5,2) DEFAULT 2.00 COMMENT 'Acceptable wastage %',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_product_yield (product_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

-- Recipes for cocktails and mixed drinks
CREATE TABLE IF NOT EXISTS bar_recipes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(100) DEFAULT 'Cocktail',
    selling_price DECIMAL(15,2) NOT NULL,
    preparation_notes TEXT,
    image VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Recipe ingredients
CREATE TABLE IF NOT EXISTS bar_recipe_ingredients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity_ml DECIMAL(10,2) NULL COMMENT 'Quantity in ml for liquids',
    quantity_units DECIMAL(10,4) NULL COMMENT 'Quantity in units for non-liquids',
    is_optional TINYINT(1) DEFAULT 0,
    notes VARCHAR(255),
    FOREIGN KEY (recipe_id) REFERENCES bar_recipes(id) ON DELETE CASCADE,
    INDEX idx_recipe (recipe_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

-- Bar stock tracking - tracks opened bottles
CREATE TABLE IF NOT EXISTS bar_open_stock (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    location_id INT UNSIGNED NULL COMMENT 'Bar location if multiple bars',
    bottle_size_ml INT NOT NULL,
    remaining_ml DECIMAL(10,2) NOT NULL,
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    opened_by INT UNSIGNED NULL,
    status ENUM('open', 'empty', 'disposed') DEFAULT 'open',
    notes VARCHAR(255),
    closed_at TIMESTAMP NULL,
    INDEX idx_product (product_id),
    INDEX idx_status (status),
    INDEX idx_location (location_id)
) ENGINE=InnoDB;

-- Bar pour/usage log - tracks every pour for variance analysis
CREATE TABLE IF NOT EXISTS bar_pour_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    open_stock_id INT UNSIGNED NULL,
    sale_id INT UNSIGNED NULL,
    sale_item_id INT UNSIGNED NULL,
    pour_type ENUM('sale', 'wastage', 'spillage', 'comp', 'staff', 'adjustment') DEFAULT 'sale',
    quantity_ml DECIMAL(10,2) NOT NULL,
    quantity_portions DECIMAL(10,4) DEFAULT 1,
    portion_name VARCHAR(100),
    user_id INT UNSIGNED NOT NULL,
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product (product_id),
    INDEX idx_sale (sale_id),
    INDEX idx_type (pour_type),
    INDEX idx_date (created_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- Bar variance reports
CREATE TABLE IF NOT EXISTS bar_variance_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_date DATE NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    opening_stock_ml DECIMAL(15,2) NOT NULL,
    purchases_ml DECIMAL(15,2) DEFAULT 0,
    expected_usage_ml DECIMAL(15,2) NOT NULL COMMENT 'Based on sales',
    actual_usage_ml DECIMAL(15,2) NOT NULL COMMENT 'Based on stock count',
    variance_ml DECIMAL(15,2) NOT NULL,
    variance_percent DECIMAL(5,2) NOT NULL,
    variance_value DECIMAL(15,2) NOT NULL COMMENT 'Cost of variance',
    notes TEXT,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_date_product (report_date, product_id),
    INDEX idx_date (report_date),
    INDEX idx_product (product_id)
) ENGINE=InnoDB;
