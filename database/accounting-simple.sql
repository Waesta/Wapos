-- Simple Accounting Module Installation
-- Run this in phpMyAdmin or MySQL command line

USE wapos;

-- 1. Create accounts table
CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    type ENUM('ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE','CONTRA_REVENUE') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Create journal_entries table
CREATE TABLE IF NOT EXISTS journal_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(50) NULL,
    description VARCHAR(255) NULL,
    entry_date DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entry_date (entry_date),
    INDEX idx_reference (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create journal_lines table
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
    INDEX idx_jl_account (account_id),
    INDEX idx_jl_entry (journal_entry_id),
    INDEX idx_jl_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create expense_categories table
CREATE TABLE IF NOT EXISTS expense_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Create account_reconciliations table
CREATE TABLE IF NOT EXISTS account_reconciliations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    reconciliation_date DATE NOT NULL,
    statement_balance DECIMAL(12,2) NOT NULL,
    book_balance DECIMAL(12,2) NOT NULL,
    reconciled_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ar_account (account_id),
    INDEX idx_ar_date (reconciliation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Insert Chart of Accounts
INSERT INTO accounts (code, name, type, is_active) VALUES
-- Assets
('1000', 'Cash', 'ASSET', 1),
('1100', 'Bank Account', 'ASSET', 1),
('1200', 'Accounts Receivable', 'ASSET', 1),
('1300', 'Inventory', 'ASSET', 1),
('1400', 'Prepaid Expenses', 'ASSET', 1),
('1500', 'Fixed Assets', 'ASSET', 1),
-- Liabilities
('2000', 'Accounts Payable', 'LIABILITY', 1),
('2100', 'Sales Tax Payable', 'LIABILITY', 1),
('2200', 'Accrued Expenses', 'LIABILITY', 1),
('2300', 'Short-term Loans', 'LIABILITY', 1),
-- Equity
('3000', 'Owner Equity', 'EQUITY', 1),
('3100', 'Retained Earnings', 'EQUITY', 1),
('3200', 'Drawings', 'EQUITY', 1),
-- Revenue
('4000', 'Sales Revenue', 'REVENUE', 1),
('4100', 'Service Revenue', 'REVENUE', 1),
('4200', 'Other Income', 'REVENUE', 1),
-- Contra Revenue
('4500', 'Sales Returns', 'CONTRA_REVENUE', 1),
('4510', 'Sales Discounts', 'CONTRA_REVENUE', 1),
-- Expenses
('5000', 'Cost of Goods Sold', 'EXPENSE', 1),
('6000', 'Operating Expenses', 'EXPENSE', 1),
('6100', 'Salaries and Wages', 'EXPENSE', 1),
('6200', 'Rent Expense', 'EXPENSE', 1),
('6300', 'Utilities Expense', 'EXPENSE', 1),
('6400', 'Marketing Expense', 'EXPENSE', 1),
('6500', 'Supplies Expense', 'EXPENSE', 1),
('6600', 'Maintenance Expense', 'EXPENSE', 1),
('6700', 'Insurance Expense', 'EXPENSE', 1),
('6800', 'Professional Fees', 'EXPENSE', 1),
('6900', 'Depreciation Expense', 'EXPENSE', 1)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- 7. Insert Expense Categories
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

-- Done!
SELECT 'Accounting module installed successfully!' AS Status;
