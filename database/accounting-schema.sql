-- Accounting schema for WAPOS
-- Tables: accounts, journal_entries, journal_lines, account_reconciliations

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
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_accounts_classification (classification),
  INDEX idx_accounts_section (statement_section),
  INDEX idx_accounts_parent (parent_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  INDEX idx_journal_status (status),
  INDEX idx_journal_entry_date (entry_date),
  INDEX idx_journal_period (period_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  CONSTRAINT fk_jl_acct FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_jl_account ON journal_lines(account_id);
CREATE INDEX idx_jl_entry ON journal_lines(journal_entry_id);

CREATE TABLE IF NOT EXISTS account_reconciliations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
  reconciliation_date DATE NOT NULL,
  statement_balance DECIMAL(12,2) NOT NULL,
  book_balance DECIMAL(12,2) NOT NULL,
  reconciled_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ar_acct FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
