-- Simple Accounting Module Installation
-- Run this in phpMyAdmin or MySQL command line

USE wapos;

-- 1. Create accounts table
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

-- 2. Create journal_entries table
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
INSERT INTO accounts (code, name, type, classification, statement_section, reporting_order, parent_code, ifrs_reference, is_active) VALUES
('1000','Cash and Cash Equivalents','ASSET','CURRENT_ASSET','BALANCE_SHEET',100,NULL,'IFRS-SME 7.2',1),
('1010','Petty Cash','ASSET','CURRENT_ASSET','BALANCE_SHEET',110,'1000','IFRS-SME 7.2',1),
('1100','Bank Accounts','ASSET','CURRENT_ASSET','BALANCE_SHEET',120,NULL,'IFRS-SME 7.2',1),
('1200','Accounts Receivable','ASSET','CURRENT_ASSET','BALANCE_SHEET',200,NULL,'IFRS-SME 11.13',1),
('1300','Inventory','ASSET','CURRENT_ASSET','BALANCE_SHEET',210,NULL,'IFRS-SME 13.4',1),
('1400','Prepaid Expenses','ASSET','CURRENT_ASSET','BALANCE_SHEET',220,NULL,'IFRS-SME 11.14',1),
('1500','Property, Plant and Equipment','ASSET','NON_CURRENT_ASSET','BALANCE_SHEET',300,NULL,'IFRS-SME 17.10',1),
('1600','Accumulated Depreciation','CONTRA_REVENUE','CONTRA_ASSET','BALANCE_SHEET',305,'1500','IFRS-SME 17.23',1),
('2000','Accounts Payable','LIABILITY','CURRENT_LIABILITY','BALANCE_SHEET',400,NULL,'IFRS-SME 11.17',1),
('2100','Sales Tax Payable','LIABILITY','CURRENT_LIABILITY','BALANCE_SHEET',410,NULL,'IFRS-SME 29.12',1),
('2200','Accrued Expenses','LIABILITY','CURRENT_LIABILITY','BALANCE_SHEET',420,NULL,'IFRS-SME 11.17',1),
('2300','Short-term Loans','LIABILITY','CURRENT_LIABILITY','BALANCE_SHEET',430,NULL,'IFRS-SME 11.13',1),
('2400','Long-term Loans','LIABILITY','NON_CURRENT_LIABILITY','BALANCE_SHEET',500,NULL,'IFRS-SME 11.14',1),
('3000','Owner\'s Capital','EQUITY','EQUITY','EQUITY',600,NULL,'IFRS-SME 6.3',1),
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
