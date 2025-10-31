<?php
/**
 * Direct Accounting Module Installer
 * This will definitely work!
 */

require_once 'includes/bootstrap.php';
$auth->requireRole(['admin']);

echo "<!DOCTYPE html><html><head><title>Installing Accounting Module</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "</head><body class='bg-light'><div class='container py-5'><div class='card'><div class='card-body'>";
echo "<h3>Installing Accounting Module...</h3><hr>";

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Create accounts table
    echo "<p>Creating accounts table...</p>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        type ENUM('ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE','CONTRA_REVENUE') NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_code (code),
        INDEX idx_type (type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p class='text-success'>✓ accounts table created</p>";
    
    // Create journal_entries table
    echo "<p>Creating journal_entries table...</p>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS journal_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reference VARCHAR(50) NULL,
        description VARCHAR(255) NULL,
        entry_date DATE NOT NULL,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_entry_date (entry_date),
        INDEX idx_reference (reference)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p class='text-success'>✓ journal_entries table created</p>";
    
    // Create journal_lines table
    echo "<p>Creating journal_lines table...</p>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS journal_lines (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p class='text-success'>✓ journal_lines table created</p>";
    
    // Create expense_categories table
    echo "<p>Creating expense_categories table...</p>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS expense_categories (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p class='text-success'>✓ expense_categories table created</p>";
    
    // Create account_reconciliations table
    echo "<p>Creating account_reconciliations table...</p>";
    $pdo->exec("CREATE TABLE IF NOT EXISTS account_reconciliations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        reconciliation_date DATE NOT NULL,
        statement_balance DECIMAL(12,2) NOT NULL,
        book_balance DECIMAL(12,2) NOT NULL,
        reconciled_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ar_account (account_id),
        INDEX idx_ar_date (reconciliation_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p class='text-success'>✓ account_reconciliations table created</p>";
    
    // Insert accounts
    echo "<p>Inserting chart of accounts...</p>";
    $pdo->exec("INSERT IGNORE INTO accounts (code, name, type, is_active) VALUES
        ('1000', 'Cash', 'ASSET', 1),
        ('1100', 'Bank Account', 'ASSET', 1),
        ('1200', 'Accounts Receivable', 'ASSET', 1),
        ('1300', 'Inventory', 'ASSET', 1),
        ('1400', 'Prepaid Expenses', 'ASSET', 1),
        ('1500', 'Fixed Assets', 'ASSET', 1),
        ('2000', 'Accounts Payable', 'LIABILITY', 1),
        ('2100', 'Sales Tax Payable', 'LIABILITY', 1),
        ('2200', 'Accrued Expenses', 'LIABILITY', 1),
        ('2300', 'Short-term Loans', 'LIABILITY', 1),
        ('3000', 'Owner Equity', 'EQUITY', 1),
        ('3100', 'Retained Earnings', 'EQUITY', 1),
        ('3200', 'Drawings', 'EQUITY', 1),
        ('4000', 'Sales Revenue', 'REVENUE', 1),
        ('4100', 'Service Revenue', 'REVENUE', 1),
        ('4200', 'Other Income', 'REVENUE', 1),
        ('4500', 'Sales Returns', 'CONTRA_REVENUE', 1),
        ('4510', 'Sales Discounts', 'CONTRA_REVENUE', 1),
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
        ('6900', 'Depreciation Expense', 'EXPENSE', 1)");
    
    $accountCount = $pdo->query("SELECT COUNT(*) FROM accounts")->fetchColumn();
    echo "<p class='text-success'>✓ {$accountCount} accounts inserted</p>";
    
    // Insert expense categories
    echo "<p>Inserting expense categories...</p>";
    $pdo->exec("INSERT IGNORE INTO expense_categories (name, description) VALUES
        ('Utilities', 'Electricity, Water, Internet'),
        ('Rent', 'Property rent and lease'),
        ('Salaries', 'Employee salaries and wages'),
        ('Supplies', 'Office and operational supplies'),
        ('Maintenance', 'Repairs and maintenance'),
        ('Marketing', 'Advertising and promotion'),
        ('Transportation', 'Fuel, vehicle maintenance'),
        ('Insurance', 'Business insurance premiums'),
        ('Professional Fees', 'Legal, accounting, consulting'),
        ('Other', 'Miscellaneous expenses')");
    
    $categoryCount = $pdo->query("SELECT COUNT(*) FROM expense_categories")->fetchColumn();
    echo "<p class='text-success'>✓ {$categoryCount} expense categories inserted</p>";
    
    echo "<hr><div class='alert alert-success'>";
    echo "<h4>✅ Installation Complete!</h4>";
    echo "<p>All accounting tables have been created and seeded successfully.</p>";
    echo "<p><strong>You can now use:</strong></p>";
    echo "<ul>";
    echo "<li><a href='reports/profit-and-loss.php'>Profit & Loss Report</a></li>";
    echo "<li><a href='reports/balance-sheet.php'>Balance Sheet</a></li>";
    echo "<li><a href='accounting.php'>Accounting Module</a></li>";
    echo "</ul>";
    echo "</div>";
    echo "<a href='index.php' class='btn btn-primary'>Go to Dashboard</a>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h4>❌ Error</h4>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</div></div></div></body></html>";
?>
