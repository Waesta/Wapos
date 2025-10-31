<?php
/**
 * WAPOS - Accounting Module Installer
 * Run this once to install accounting tables
 */

require_once 'includes/bootstrap.php';

// Only admin can run this
$auth->requireRole(['admin']);

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        // Read the SQL file (use simple version without prepared statements)
        $sqlFile = __DIR__ . '/database/accounting-simple.sql';
        
        if (!file_exists($sqlFile)) {
            throw new Exception('SQL file not found: ' . $sqlFile);
        }
        
        $sql = file_get_contents($sqlFile);
        
        // Get PDO connection
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        // Enable buffered queries to avoid "unbuffered queries" error
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        
        // Split SQL into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) && 
                       !preg_match('/^--/', $stmt) && 
                       !preg_match('/^USE /', $stmt) &&
                       !preg_match('/^SET @/', $stmt) &&
                       !preg_match('/^PREPARE /', $stmt) &&
                       !preg_match('/^EXECUTE /', $stmt) &&
                       !preg_match('/^DEALLOCATE /', $stmt);
            }
        );
        
        // Don't use transaction for this - causes issues with prepared statements
        // $pdo->beginTransaction();
        
        $executed = 0;
        $errors = [];
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                    $executed++;
                } catch (PDOException $e) {
                    // Ignore "already exists" errors
                    if (strpos($e->getMessage(), 'already exists') === false &&
                        strpos($e->getMessage(), 'Duplicate') === false) {
                        $errors[] = $e->getMessage();
                    }
                }
            }
        }
        
        // $pdo->commit(); // No transaction used
        
        if (!empty($errors)) {
            $success = false;
            $message = "⚠️ Installation completed with errors:<br>" .
                       "✅ Executed {$executed} SQL statements<br>" .
                       "❌ Errors encountered:<br>" .
                       implode('<br>', array_map('htmlspecialchars', $errors));
        } else {
            $success = true;
            $message = "✅ Accounting module installed successfully!<br>" .
                       "📊 Executed {$executed} SQL statements<br>" .
                       "✅ All accounting tables created<br>" .
                       "✅ Chart of accounts seeded<br>" .
                       "✅ Expense categories added";
        }
        
    } catch (Exception $e) {
        $message = "❌ Error: " . htmlspecialchars($e->getMessage());
        $success = false;
    }
}

// Check if tables exist
$db = Database::getInstance();
$tablesExist = [];
$requiredTables = ['accounts', 'journal_entries', 'journal_lines', 'expense_categories'];

foreach ($requiredTables as $table) {
    try {
        $db->fetchOne("SELECT 1 FROM {$table} LIMIT 1");
        $tablesExist[$table] = true;
    } catch (Exception $e) {
        $tablesExist[$table] = false;
    }
}

$allTablesExist = !in_array(false, $tablesExist);

$pageTitle = 'Install Accounting Module';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-calculator me-2"></i><?= $pageTitle ?></h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $success ? 'success' : 'danger' ?> alert-dismissible fade show">
                                <?= $message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <h5 class="mb-3">📊 Accounting Module Status</h5>
                        
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Table Name</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tablesExist as $table => $exists): ?>
                                    <tr>
                                        <td><code><?= $table ?></code></td>
                                        <td>
                                            <?php if ($exists): ?>
                                                <span class="badge bg-success">✅ Installed</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">❌ Missing</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($allTablesExist): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>All accounting tables are installed!</strong>
                                <p class="mb-0 mt-2">You can now use:</p>
                                <ul class="mb-0">
                                    <li>Profit & Loss Report</li>
                                    <li>Balance Sheet</li>
                                    <li>Journal Entries</li>
                                    <li>Chart of Accounts</li>
                                </ul>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="accounting.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-calculator me-2"></i>Go to Accounting
                                </a>
                                <a href="reports/profit-and-loss.php" class="btn btn-outline-primary">
                                    <i class="bi bi-graph-up me-2"></i>View Profit & Loss
                                </a>
                                <a href="reports/balance-sheet.php" class="btn btn-outline-primary">
                                    <i class="bi bi-clipboard-data me-2"></i>View Balance Sheet
                                </a>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-house me-2"></i>Back to Dashboard
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Accounting tables are missing!</strong>
                                <p class="mb-0">Click the button below to install them.</p>
                            </div>

                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6>What will be installed:</h6>
                                    <ul class="mb-0">
                                        <li><strong>accounts</strong> - Chart of accounts (Assets, Liabilities, Equity, Revenue, Expenses)</li>
                                        <li><strong>journal_entries</strong> - Accounting journal entries</li>
                                        <li><strong>journal_lines</strong> - Journal entry line items (debits/credits)</li>
                                        <li><strong>expense_categories</strong> - Expense categorization</li>
                                        <li><strong>account_reconciliations</strong> - Bank reconciliation tracking</li>
                                    </ul>
                                </div>
                            </div>

                            <form method="POST" onsubmit="return confirm('Install accounting module? This will create new database tables.');">
                                <div class="d-grid gap-2">
                                    <button type="submit" name="install" class="btn btn-success btn-lg">
                                        <i class="bi bi-download me-2"></i>Install Accounting Module
                                    </button>
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle me-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>

                        <hr class="my-4">

                        <div class="alert alert-info mb-0">
                            <h6><i class="bi bi-info-circle me-2"></i>About the Accounting Module</h6>
                            <p class="mb-0">
                                The accounting module provides double-entry bookkeeping, financial reports, 
                                and comprehensive accounting features. It includes a complete chart of accounts, 
                                journal entries, and automated financial statement generation.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
