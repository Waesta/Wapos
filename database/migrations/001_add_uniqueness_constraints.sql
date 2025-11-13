-- =====================================================
-- WAPOS Migration 001: Add Uniqueness Constraints & Idempotency
-- Purpose: Prevent duplicate data and enable idempotent operations
-- Date: 2025-10-31
-- =====================================================

-- Create migrations tracking table
CREATE TABLE IF NOT EXISTS migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_migration_name (migration_name)
) ENGINE=InnoDB;

-- =====================================================
-- PRODUCTS & CATALOG
-- =====================================================

-- Add unique constraints to products (if not exists)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'products' 
     AND index_name = 'ux_products_sku') = 0,
    'ALTER TABLE products ADD UNIQUE KEY ux_products_sku (sku)',
    'SELECT "Index ux_products_sku already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'products' 
     AND index_name = 'ux_products_barcode') = 0,
    'ALTER TABLE products ADD UNIQUE KEY ux_products_barcode (barcode)',
    'SELECT "Index ux_products_barcode already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add location_id to products if not exists
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'products' 
     AND column_name = 'location_id') = 0,
    'ALTER TABLE products ADD COLUMN location_id INT UNSIGNED DEFAULT 1 AFTER id',
    'SELECT "Column location_id already exists in products"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Categories uniqueness (location + name)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'categories' 
     AND column_name = 'location_id') = 0,
    'ALTER TABLE categories ADD COLUMN location_id INT UNSIGNED DEFAULT 1 AFTER id',
    'SELECT "Column location_id already exists in categories"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'categories' 
     AND index_name = 'ux_categories_location_name') = 0,
    'ALTER TABLE categories ADD UNIQUE KEY ux_categories_location_name (location_id, name)',
    'SELECT "Index ux_categories_location_name already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- CUSTOMERS & SUPPLIERS
-- =====================================================

-- Customers unique phone and email
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'customers' 
     AND index_name = 'ux_customers_phone') = 0,
    'ALTER TABLE customers ADD UNIQUE KEY ux_customers_phone (phone)',
    'SELECT "Index ux_customers_phone already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'customers' 
     AND index_name = 'ux_customers_email') = 0,
    'ALTER TABLE customers ADD UNIQUE KEY ux_customers_email (email)',
    'SELECT "Index ux_customers_email already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create suppliers table if not exists
CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_code VARCHAR(50) UNIQUE,
    name VARCHAR(200) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    tax_id VARCHAR(50),
    payment_terms VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY ux_suppliers_phone (phone),
    UNIQUE KEY ux_suppliers_email (email),
    INDEX idx_name (name)
) ENGINE=InnoDB;

-- =====================================================
-- ROOMS & RESTAURANT
-- =====================================================

-- Rooms unique constraint (location + room_number)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'rooms' 
     AND index_name = 'ux_rooms_location_number') = 0,
    'ALTER TABLE rooms ADD UNIQUE KEY ux_rooms_location_number (location_id, room_number)',
    'SELECT "Index ux_rooms_location_number already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Restaurant tables unique constraint (location + table_name)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'restaurant_tables' 
     AND index_name = 'ux_restaurant_tables_location_name') = 0,
    'ALTER TABLE restaurant_tables ADD UNIQUE KEY ux_restaurant_tables_location_name (location_id, table_name)',
    'SELECT "Index ux_restaurant_tables_location_name already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- SALES & LINE ITEMS
-- =====================================================

-- Add external_id to sales for idempotency
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'sales' 
     AND column_name = 'external_id') = 0,
    'ALTER TABLE sales ADD COLUMN external_id VARCHAR(100) UNIQUE AFTER id',
    'SELECT "Column external_id already exists in sales"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add receipt_no unique constraint
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'sales' 
     AND index_name = 'ux_sales_receipt_no') = 0,
    'ALTER TABLE sales ADD UNIQUE KEY ux_sales_receipt_no (sale_number)',
    'SELECT "Index ux_sales_receipt_no already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add external_id unique constraint
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'sales' 
     AND index_name = 'ux_sales_external_id') = 0,
    'ALTER TABLE sales ADD UNIQUE KEY ux_sales_external_id (external_id)',
    'SELECT "Index ux_sales_external_id already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Sale items unique constraint (sale_id + product_id)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'sale_items' 
     AND index_name = 'ux_sale_items_unique') = 0,
    'ALTER TABLE sale_items ADD UNIQUE KEY ux_sale_items_unique (sale_id, product_id)',
    'SELECT "Index ux_sale_items_unique already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- PURCHASING
-- =====================================================

-- Create purchase_orders table if not exists
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) NOT NULL,
    supplier_id INT UNSIGNED,
    location_id INT UNSIGNED DEFAULT 1,
    order_date DATE NOT NULL,
    expected_date DATE,
    status ENUM('draft', 'sent', 'partial', 'received', 'cancelled') DEFAULT 'draft',
    subtotal DECIMAL(15,2) DEFAULT 0,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    total_amount DECIMAL(15,2) DEFAULT 0,
    notes TEXT,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY ux_po_number (po_number),
    INDEX idx_supplier (supplier_id),
    INDEX idx_status (status),
    INDEX idx_date (order_date)
) ENGINE=InnoDB;

-- Create grn (goods received notes) table if not exists
CREATE TABLE IF NOT EXISTS grn (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grn_number VARCHAR(50) NOT NULL,
    po_id INT UNSIGNED,
    supplier_id INT UNSIGNED,
    location_id INT UNSIGNED DEFAULT 1,
    received_date DATE NOT NULL,
    status ENUM('draft', 'completed', 'cancelled') DEFAULT 'draft',
    total_amount DECIMAL(15,2) DEFAULT 0,
    notes TEXT,
    received_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY ux_grn_number (grn_number),
    INDEX idx_po (po_id),
    INDEX idx_supplier (supplier_id),
    INDEX idx_date (received_date)
) ENGINE=InnoDB;

-- =====================================================
-- DELIVERY
-- =====================================================

-- Add order_id to deliveries if exists
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
     WHERE table_schema = DATABASE() 
     AND table_name = 'deliveries') > 0,
    (SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
         WHERE table_schema = DATABASE() 
         AND table_name = 'deliveries' 
         AND index_name = 'ux_deliveries_order') = 0,
        'ALTER TABLE deliveries ADD UNIQUE KEY ux_deliveries_order (order_id)',
        'SELECT "Index ux_deliveries_order already exists"'
    )),
    'SELECT "Table deliveries does not exist"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- ACCOUNTING
-- =====================================================

CREATE TABLE IF NOT EXISTS accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(150) NOT NULL,
    type ENUM('ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE','CONTRA_REVENUE','COST_OF_SALES','OTHER_INCOME','OTHER_EXPENSE') NOT NULL,
    classification ENUM(
        'CURRENT_ASSET','NON_CURRENT_ASSET','CONTRA_ASSET',
        'CURRENT_LIABILITY','NON_CURRENT_LIABILITY','CONTRA_LIABILITY',
        'EQUITY','CONTRA_EQUITY',
        'REVENUE','CONTRA_REVENUE','OTHER_INCOME',
        'COST_OF_SALES','OPERATING_EXPENSE','NON_OPERATING_EXPENSE','OTHER_EXPENSE'
    ) NOT NULL,
    statement_section ENUM('BALANCE_SHEET','PROFIT_AND_LOSS','CASH_FLOW','EQUITY') NOT NULL DEFAULT 'PROFIT_AND_LOSS',
    reporting_order INT UNSIGNED NOT NULL DEFAULT 0,
    parent_code VARCHAR(20) NULL,
    ifrs_reference VARCHAR(50) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY ux_accounts_code (code),
    INDEX idx_accounts_classification (classification),
    INDEX idx_accounts_section (statement_section),
    INDEX idx_accounts_parent (parent_code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS journal_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_number VARCHAR(50) NOT NULL,
    source VARCHAR(50) NOT NULL COMMENT 'sale, purchase, adjustment, etc',
    source_id INT UNSIGNED COMMENT 'ID of source transaction',
    reference_no VARCHAR(100),
    entry_date DATE NOT NULL,
    description TEXT,
    total_debit DECIMAL(15,2) DEFAULT 0,
    total_credit DECIMAL(15,2) DEFAULT 0,
    status ENUM('draft','posted','voided') DEFAULT 'draft',
    period_id INT UNSIGNED NULL,
    posted_by INT UNSIGNED,
    posted_at TIMESTAMP NULL,
    locked_at TIMESTAMP NULL,
    locked_by INT UNSIGNED NULL,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY ux_journal_source_ref (source, source_id, reference_no),
    INDEX idx_entry_number (entry_number),
    INDEX idx_source (source, source_id),
    INDEX idx_date (entry_date),
    INDEX idx_status (status),
    INDEX idx_period (period_id)
) ENGINE=InnoDB;

-- Create journal_entry_lines table if not exists
CREATE TABLE IF NOT EXISTS journal_entry_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    debit_amount DECIMAL(15,2) DEFAULT 0,
    credit_amount DECIMAL(15,2) DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    INDEX idx_journal (journal_entry_id),
    INDEX idx_account (account_id)
) ENGINE=InnoDB;

-- Create accounting_periods table for period close
CREATE TABLE IF NOT EXISTS accounting_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_name VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('open', 'closed', 'locked') DEFAULT 'open',
    closed_by INT UNSIGNED,
    closed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY ux_period_dates (start_date, end_date),
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB;

-- =====================================================
-- PERFORMANCE INDEXES
-- =====================================================

-- Add updated_at indexes for delta polling
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'products' 
     AND column_name = 'updated_at') > 0,
    (SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
         WHERE table_schema = DATABASE() 
         AND table_name = 'products' 
         AND index_name = 'idx_location_updated') = 0,
        'ALTER TABLE products ADD INDEX idx_location_updated (location_id, updated_at)',
        'SELECT "Index idx_location_updated already exists on products"'
    )),
    'SELECT "Column updated_at does not exist in products"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add device_id and created_at_iso to sales for offline sync
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'sales' 
     AND column_name = 'device_id') = 0,
    'ALTER TABLE sales ADD COLUMN device_id VARCHAR(100) AFTER external_id',
    'SELECT "Column device_id already exists in sales"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Record migration
INSERT IGNORE INTO migrations (migration_name) VALUES ('001_add_uniqueness_constraints');

-- =====================================================
-- END OF MIGRATION
-- =====================================================
