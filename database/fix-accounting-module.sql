-- ============================================
-- WAPOS Accounting Module Fix Script
-- Fixes all missing tables and seeds data
-- Run this to complete the accounting module
-- ============================================

-- 1. Create expense_categories table
CREATE TABLE IF NOT EXISTS expense_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Update expenses table structure
ALTER TABLE expenses 
ADD COLUMN IF NOT EXISTS category_id INT UNSIGNED AFTER user_id,
ADD COLUMN IF NOT EXISTS location_id INT UNSIGNED AFTER category_id,
ADD COLUMN IF NOT EXISTS reference VARCHAR(50) AFTER payment_method;

-- 3. Insert default expense categories
INSERT INTO expense_categories (name, description) VALUES
('Utilities', 'Electricity, Water, Internet'),
('Rent', 'Property rent and lease'),
('Salaries', 'Employee salaries and wages'),
('Supplies', 'Office and operational supplies'),
('Maintenance', 'Repairs and maintenance'),
('Marketing', 'Advertising and promotion'),
('Transportation', 'Fuel, vehicle maintenance'),
('Insurance', 'Business insurance premiums'),
('Professional Fees', 'Legal, accounting, consulting'),
('Other', 'Miscellaneous expenses')
ON DUPLICATE KEY UPDATE name=VALUES(name);

CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
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
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_accounts_classification (classification),
    INDEX idx_accounts_section (statement_section),
    INDEX idx_accounts_parent (parent_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE accounts
    MODIFY COLUMN name VARCHAR(150) NOT NULL,
    MODIFY COLUMN type ENUM('ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE','CONTRA_REVENUE','COST_OF_SALES','OTHER_INCOME','OTHER_EXPENSE') NOT NULL;

-- Ensure IFRS metadata columns exist on accounts table (MySQL 5.7 compatible)
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'accounts'
      AND COLUMN_NAME = 'classification'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE accounts ADD COLUMN classification ENUM(''CURRENT_ASSET'',''NON_CURRENT_ASSET'',''CONTRA_ASSET'',''CURRENT_LIABILITY'',''NON_CURRENT_LIABILITY'',''CONTRA_LIABILITY'',''EQUITY'',''CONTRA_EQUITY'',''REVENUE'',''CONTRA_REVENUE'',''OTHER_INCOME'',''COST_OF_SALES'',''OPERATING_EXPENSE'',''NON_OPERATING_EXPENSE'',''OTHER_EXPENSE'') NOT NULL DEFAULT ''CURRENT_ASSET'' AFTER type',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'accounts'
      AND COLUMN_NAME = 'statement_section'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE accounts ADD COLUMN statement_section ENUM(''BALANCE_SHEET'',''PROFIT_AND_LOSS'',''CASH_FLOW'',''EQUITY'') NOT NULL DEFAULT ''PROFIT_AND_LOSS'' AFTER classification',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'accounts'
      AND COLUMN_NAME = 'reporting_order'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE accounts ADD COLUMN reporting_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER statement_section',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'accounts'
      AND COLUMN_NAME = 'parent_code'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE accounts ADD COLUMN parent_code VARCHAR(20) NULL AFTER reporting_order',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'accounts'
      AND COLUMN_NAME = 'ifrs_reference'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE accounts ADD COLUMN ifrs_reference VARCHAR(50) NULL AFTER parent_code',
    'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO accounts (code, name, type, classification, statement_section, reporting_order, parent_code, ifrs_reference, is_active) VALUES
('1000','Cash and Cash Equivalents','ASSET','CURRENT_ASSET','BALANCE_SHEET',100,NULL,'IFRS-SME 7.2',1),
('1010','Petty Cash','ASSET','CURRENT_ASSET','BALANCE_SHEET',110,'1000','IFRS-SME 7.2',1),
('1100','Bank Accounts','ASSET','CURRENT_ASSET','BALANCE_SHEET',120,NULL,'IFRS-SME 7.2',1),
('1200','Accounts Receivable','ASSET','CURRENT_ASSET','BALANCE_SHEET',200,NULL,'IFRS-SME 11.13',1),
('1300','Inventory','ASSET','CURRENT_ASSET','BALANCE_SHEET',210,NULL,'IFRS-SME 13.4',1),
('1400','Prepaid Expenses','ASSET','CURRENT_ASSET','BALANCE_SHEET',220,NULL,'IFRS-SME 11.14',1),
('1500','Property, Plant and Equipment','ASSET','NON_CURRENT_ASSET','BALANCE_SHEET',300,NULL,'IFRS-SME 17.10',1),
('1600','Accumulated Depreciation','ASSET','CONTRA_ASSET','BALANCE_SHEET',305,'1500','IFRS-SME 17.23',1),
('2000','Accounts Payable','LIABILITY','CURRENT_LIABILITY','BALANCE_SHEET',400,NULL,'IFRS-SME 11.17',1),
('2100','Sales Tax Payable','LIABILITY','CURRENT_LIABILITY','BALANCE_SHEET',410,NULL,'IFRS-SME 29.12',1),
('2200','Accrued Expenses','LIABILITY','CURRENT_LIABILITY','BALANCE_SHEET',420,NULL,'IFRS-SME 11.17',1),
('2300','Short-term Loans','LIABILITY','CURRENT_LIABILITY','BALANCE_SHEET',430,NULL,'IFRS-SME 11.13',1),
('2400','Long-term Loans','LIABILITY','NON_CURRENT_LIABILITY','BALANCE_SHEET',500,NULL,'IFRS-SME 11.14',1),
('3000','Owner''s Capital','EQUITY','EQUITY','EQUITY',600,NULL,'IFRS-SME 6.3',1),
('3100','Retained Earnings','EQUITY','EQUITY','EQUITY',610,'3000','IFRS-SME 6.5',1),
('3200','Owner Drawings','EQUITY','CONTRA_EQUITY','EQUITY',620,'3000','IFRS-SME 6.7',1),
('4000','Sales Revenue','REVENUE','REVENUE','PROFIT_AND_LOSS',700,NULL,'IFRS-SME 23.30',1),
('4100','Service Revenue','REVENUE','REVENUE','PROFIT_AND_LOSS',710,'4000','IFRS-SME 23.30',1),
('4200','Other Operating Income','OTHER_INCOME','OTHER_INCOME','PROFIT_AND_LOSS',720,NULL,'IFRS-SME 23.30',1),
('4500','Sales Returns and Allowances','CONTRA_REVENUE','CONTRA_REVENUE','PROFIT_AND_LOSS',730,'4000','IFRS-SME 23.31',1),
('4510','Sales Discounts','CONTRA_REVENUE','CONTRA_REVENUE','PROFIT_AND_LOSS',735,'4000','IFRS-SME 23.31',1),
('5000','Cost of Goods Sold','EXPENSE','COST_OF_SALES','PROFIT_AND_LOSS',800,NULL,'IFRS-SME 13.19',1),
('5100','Direct Labour','EXPENSE','COST_OF_SALES','PROFIT_AND_LOSS',810,'5000','IFRS-SME 13.19',1),
('5200','Freight Inwards','EXPENSE','COST_OF_SALES','PROFIT_AND_LOSS',820,'5000','IFRS-SME 13.19',1),
('6000','Operating Expenses','EXPENSE','OPERATING_EXPENSE','PROFIT_AND_LOSS',900,NULL,'IFRS-SME 2.52',1),
('6100','Salaries and Wages','EXPENSE','OPERATING_EXPENSE','PROFIT_AND_LOSS',910,'6000','IFRS-SME 2.52',1),
('6200','Rent Expense','EXPENSE','OPERATING_EXPENSE','PROFIT_AND_LOSS',920,'6000','IFRS-SME 2.52',1),
('6300','Utilities Expense','EXPENSE','OPERATING_EXPENSE','PROFIT_AND_LOSS',930,'6000','IFRS-SME 2.52',1),
('6400','Marketing Expense','EXPENSE','OPERATING_EXPENSE','PROFIT_AND_LOSS',940,'6000','IFRS-SME 2.52',1),
('6500','Depreciation Expense','EXPENSE','OPERATING_EXPENSE','PROFIT_AND_LOSS',950,'6000','IFRS-SME 17.23',1),
('6600','Finance Costs','EXPENSE','NON_OPERATING_EXPENSE','PROFIT_AND_LOSS',960,NULL,'IFRS-SME 25.3',1),
('6700','Other Expenses','EXPENSE','OTHER_EXPENSE','PROFIT_AND_LOSS',970,NULL,'IFRS-SME 2.52',1)
ON DUPLICATE KEY UPDATE 
    name=VALUES(name),
    type=VALUES(type),
    classification=VALUES(classification),
    statement_section=VALUES(statement_section),
    reporting_order=VALUES(reporting_order),
    parent_code=VALUES(parent_code),
    ifrs_reference=VALUES(ifrs_reference),
    is_active=VALUES(is_active);

CREATE TABLE IF NOT EXISTS journal_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(50) NULL,
    description VARCHAR(255) NULL,
    entry_date DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('draft','posted','voided') NOT NULL DEFAULT 'draft',
    period_id INT UNSIGNED NULL,
    locked_at TIMESTAMP NULL,
    locked_by INT UNSIGNED NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entry_date (entry_date),
    INDEX idx_reference (reference),
    INDEX idx_status (status),
    INDEX idx_period (period_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Ensure journal_lines table exists
CREATE TABLE IF NOT EXISTS journal_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id INT NOT NULL,
    account_id INT NOT NULL,
    debit_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    credit_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    description VARCHAR(255) NULL,
    is_reconciled TINYINT(1) NOT NULL DEFAULT 0,
    reconciled_date DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_jl_je FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_jl_acct FOREIGN KEY (account_id) REFERENCES accounts(id),
    INDEX idx_jl_account (account_id),
    INDEX idx_jl_entry (journal_entry_id),
    INDEX idx_jl_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Create account_reconciliations table
CREATE TABLE IF NOT EXISTS account_reconciliations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    reconciliation_date DATE NOT NULL,
    statement_balance DECIMAL(12,2) NOT NULL,
    book_balance DECIMAL(12,2) NOT NULL,
    reconciled_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ar_acct FOREIGN KEY (account_id) REFERENCES accounts(id),
    INDEX idx_ar_account (account_id),
    INDEX idx_ar_date (reconciliation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Add setting_type column to settings table if missing
ALTER TABLE settings 
ADD COLUMN IF NOT EXISTS setting_type VARCHAR(50) DEFAULT 'string' AFTER setting_value;

-- 10. Update users table to include accountant role
ALTER TABLE users 
MODIFY COLUMN role ENUM('admin', 'manager', 'inventory_manager', 'cashier', 'waiter', 'rider', 'accountant', 'developer') DEFAULT 'cashier';

-- 11. Add foreign keys to expenses table (if not exists)
-- Check and add category_id foreign key
SET @fk_category_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'expenses' 
    AND CONSTRAINT_NAME = 'fk_expenses_category'
);

SET @sql_category = IF(@fk_category_exists = 0, 
    'ALTER TABLE expenses ADD CONSTRAINT fk_expenses_category FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL',
    'DO 0');

PREPARE stmt FROM @sql_category;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add location_id foreign key
SET @fk_location_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'expenses' 
    AND CONSTRAINT_NAME = 'fk_expenses_location'
);

SET @sql_location = IF(@fk_location_exists = 0, 
    'ALTER TABLE expenses ADD CONSTRAINT fk_expenses_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL',
    'DO 0');

PREPARE stmt FROM @sql_location;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
