-- =====================================================
-- WAPOS Migration 002: Add Missing Tables and Columns
-- Purpose: Create missing tables and add missing columns
-- Date: 2025-10-31
-- Safe: Only creates what doesn't exist
-- =====================================================

-- 1. Create void_reason_codes table
CREATE TABLE IF NOT EXISTS void_reason_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    requires_approval TINYINT(1) DEFAULT 0,
    requires_manager_approval TINYINT(1) DEFAULT 0,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_code (code),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB;

-- Insert default void reason codes (IGNORE prevents duplicates)
INSERT IGNORE INTO void_reason_codes (code, display_name, description, requires_manager_approval, display_order) VALUES
('CUSTOMER_REQUEST', 'Customer Request', 'Customer requested cancellation', 0, 1),
('WRONG_ORDER', 'Wrong Order', 'Wrong order entered', 0, 2),
('PAYMENT_ISSUE', 'Payment Issue', 'Payment processing issue', 1, 3),
('DUPLICATE', 'Duplicate Order', 'Duplicate order', 0, 4),
('OUT_OF_STOCK', 'Out of Stock', 'Item out of stock', 0, 5),
('PRICING_ERROR', 'Pricing Error', 'Pricing error', 1, 6),
('KITCHEN_ERROR', 'Kitchen Error', 'Kitchen preparation error', 0, 7),
('CUSTOMER_NO_SHOW', 'Customer No-Show', 'Customer did not show up', 0, 8),
('MANAGER_OVERRIDE', 'Manager Override', 'Manager override', 1, 9),
('OTHER', 'Other Reason', 'Other reason', 1, 10);

-- 2. Create void_settings table
CREATE TABLE IF NOT EXISTS void_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB;

-- Insert default void settings (IGNORE prevents duplicates)
INSERT IGNORE INTO void_settings (setting_key, setting_value, description) VALUES
('require_manager_approval', '1', 'Require manager approval for voids'),
('max_void_amount', '1000', 'Maximum amount that can be voided without approval'),
('void_retention_days', '90', 'Number of days to retain void records'),
('allow_partial_void', '1', 'Allow partial order voids'),
('require_reason_code', '1', 'Require reason code for all voids');

-- 3. Create goods_received_notes table
CREATE TABLE IF NOT EXISTS goods_received_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grn_number VARCHAR(50) UNIQUE NOT NULL,
    purchase_order_id INT UNSIGNED,
    supplier_id INT UNSIGNED NOT NULL,
    received_date DATE NOT NULL,
    received_by INT UNSIGNED,
    total_amount DECIMAL(15,2) DEFAULT 0.00,
    notes TEXT,
    status ENUM('pending', 'partial', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_grn_number (grn_number),
    INDEX idx_supplier (supplier_id),
    INDEX idx_status (status),
    INDEX idx_received_date (received_date)
) ENGINE=InnoDB;

-- 4. Add missing columns to riders table (check if exists first)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'riders' 
     AND column_name = 'current_latitude') = 0,
    'ALTER TABLE riders ADD COLUMN current_latitude DECIMAL(10, 8) DEFAULT NULL AFTER phone',
    'SELECT "Column current_latitude already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'riders' 
     AND column_name = 'current_longitude') = 0,
    'ALTER TABLE riders ADD COLUMN current_longitude DECIMAL(11, 8) DEFAULT NULL AFTER current_latitude',
    'SELECT "Column current_longitude already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'riders' 
     AND column_name = 'last_location_update') = 0,
    'ALTER TABLE riders ADD COLUMN last_location_update TIMESTAMP NULL AFTER current_longitude',
    'SELECT "Column last_location_update already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Add order_source column to sales table (check if exists first)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'sales' 
     AND column_name = 'order_source') = 0,
    'ALTER TABLE sales ADD COLUMN order_source ENUM(''pos'', ''online'', ''whatsapp'', ''phone'', ''mobile_app'') DEFAULT ''pos'' AFTER payment_method',
    'SELECT "Column order_source already exists in sales"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Add order_source column to orders table (check if exists first)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'orders' 
     AND column_name = 'order_source') = 0,
    'ALTER TABLE orders ADD COLUMN order_source ENUM(''pos'', ''online'', ''whatsapp'', ''phone'', ''mobile_app'') DEFAULT ''pos'' AFTER status',
    'SELECT "Column order_source already exists in orders"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 7. Add indexes for order_source columns
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'sales' 
     AND index_name = 'idx_order_source') = 0,
    'ALTER TABLE sales ADD INDEX idx_order_source (order_source)',
    'SELECT "Index idx_order_source already exists on sales"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'orders' 
     AND index_name = 'idx_order_source') = 0,
    'ALTER TABLE orders ADD INDEX idx_order_source (order_source)',
    'SELECT "Index idx_order_source already exists on orders"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Record migration
INSERT IGNORE INTO migrations (migration_name) VALUES ('002_add_missing_tables_columns');
