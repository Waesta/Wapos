<?php
require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager', 'accountant']);

$db = Database::getInstance();

// Helpers
function getAccountBalance(Database $db, $accountId, $asOfDate) {
    try {
        $row = $db->fetchOne(
            "SELECT COALESCE(SUM(debit_amount - credit_amount), 0) AS balance
             FROM journal_lines
             WHERE account_id = ? AND DATE(created_at) <= ?",
            [$accountId, $asOfDate]
        );
        return $row['balance'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }

function getAccountIdByCode(Database $db, string $code): int {
    $acct = $db->fetchOne("SELECT id FROM accounts WHERE code = ?", [$code]);
    if ($acct && isset($acct['id'])) { return (int)$acct['id']; }
    $db->insert('accounts', [
        'code' => $code,
        'name' => $code,
        'type' => in_array($code, ['1000','1100','1200','1300']) ? 'ASSET' : (in_array($code,['2000','2100']) ? 'LIABILITY' : (in_array($code,['4000','4100']) ? 'REVENUE' : 'EXPENSE')),
        'is_active' => 1
    ]);
    $acct = $db->fetchOne("SELECT id FROM accounts WHERE code = ?", [$code]);
    return (int)($acct['id'] ?? 0);
}
}

// Handle accounting actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation for all accounting POST actions
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        showAlert('Invalid request. Please try again.', 'error');
    } else {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add_expense') {
            $data = [
                'amount' => $_POST['amount'],
                'category_id' => $_POST['category_id'],
                'description' => sanitizeInput($_POST['description']),
                'expense_date' => $_POST['expense_date'],
                'payment_method' => $_POST['payment_method'],
                'reference' => sanitizeInput($_POST['reference'] ?? ''),
                'user_id' => $auth->getUserId(),
                'location_id' => $_POST['location_id'] ?: null
            ];
            
            if ($db->insert('expenses', $data)) {
                // Post journal: Dr Operating Expense (6000), Cr Cash/Bank
                $journalId = $db->insert('journal_entries', [
                    'reference' => $data['reference'] ?? null,
                    'description' => 'Expense: ' . $data['description'],
                    'entry_date' => $data['expense_date'],
                    'total_amount' => $data['amount'],
                    'created_by' => $auth->getUserId(),
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                $tender = strtolower($data['payment_method'] ?? 'cash');
                $cashAcct = ($tender === 'cash') ? '1000' : '1100';

                // Dr Expense
                $db->insert('journal_lines', [
                    'journal_entry_id' => $journalId,
                    'account_id' => getAccountIdByCode($db, '6000'),
                    'debit_amount' => $data['amount'],
                    'credit_amount' => 0,
                    'description' => 'Operating expense'
                ]);
                // Cr Cash/Bank
                $db->insert('journal_lines', [
                    'journal_entry_id' => $journalId,
                    'account_id' => getAccountIdByCode($db, $cashAcct),
                    'debit_amount' => 0,
                    'credit_amount' => $data['amount'],
                    'description' => 'Payment of expense'
                ]);

                showAlert('Expense added successfully', 'success');
            }
            
        } elseif ($action === 'add_journal_entry') {
            $entries = json_decode($_POST['entries'], true);
            $description = sanitizeInput($_POST['description']);
            $reference = sanitizeInput($_POST['reference']);
            
            $totalDebits = 0;
            $totalCredits = 0;
            
            // Validate entries balance
            foreach ($entries as $entry) {
                $totalDebits += $entry['debit'] ?? 0;
                $totalCredits += $entry['credit'] ?? 0;
            }
            
            if (abs($totalDebits - $totalCredits) > 0.01) {
                throw new Exception('Journal entry must balance. Debits: ' . $totalDebits . ', Credits: ' . $totalCredits);
            }
            
            // Create journal entry
            $journalId = $db->insert('journal_entries', [
                'reference' => $reference,
                'description' => $description,
                'entry_date' => $_POST['entry_date'],
                'total_amount' => $totalDebits,
                'created_by' => $auth->getUserId(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Add journal lines
            foreach ($entries as $entry) {
                if (($entry['debit'] ?? 0) > 0 || ($entry['credit'] ?? 0) > 0) {
                    $db->insert('journal_lines', [
                        'journal_entry_id' => $journalId,
                        'account_id' => $entry['account_id'],
                        'debit_amount' => $entry['debit'] ?? 0,
                        'credit_amount' => $entry['credit'] ?? 0,
                        'description' => $entry['description'] ?? $description
                    ]);
                }
            }
            
            showAlert('Journal entry created successfully', 'success');
            
        } elseif ($action === 'reconcile_account') {
            $accountId = $_POST['account_id'];
            $statementBalance = $_POST['statement_balance'];
            $reconciliationDate = $_POST['reconciliation_date'];
            
            // Mark transactions as reconciled
            $selectedTransactions = $_POST['reconciled_transactions'] ?? [];
            if (!empty($selectedTransactions)) {
                $placeholders = str_repeat('?,', count($selectedTransactions) - 1) . '?';
                $db->query("UPDATE journal_lines SET is_reconciled = 1, reconciled_date = ? WHERE id IN ($placeholders)", 
                    array_merge([$reconciliationDate], $selectedTransactions));
            }
            
            // Create reconciliation record
            $db->insert('account_reconciliations', [
                'account_id' => $accountId,
                'reconciliation_date' => $reconciliationDate,
                'statement_balance' => $statementBalance,
                'book_balance' => getAccountBalance($db, $accountId, $reconciliationDate),
                'reconciled_by' => $auth->getUserId(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            showAlert('Account reconciled successfully', 'success');
        }
        
    } catch (Exception $e) {
        showAlert('Error: ' . $e->getMessage(), 'error');
    }
    }
}

// Date filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Get financial summary
$revenue = $db->fetchOne("
    SELECT COALESCE(SUM(total_amount), 0) as total 
    FROM sales 
    WHERE DATE(created_at) BETWEEN ? AND ?
", [$startDate, $endDate]);

$expenses = $db->fetchOne("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM expenses 
    WHERE DATE(expense_date) BETWEEN ? AND ?
", [$startDate, $endDate]);

$profit = $revenue['total'] - $expenses['total'];

// Get expense breakdown by category
$expensesByCategory = $db->fetchAll("
    SELECT 
        ec.name as category_name,
        COALESCE(SUM(e.amount), 0) as total
    FROM expense_categories ec
    LEFT JOIN expenses e ON ec.id = e.category_id 
        AND DATE(e.expense_date) BETWEEN ? AND ?
    WHERE ec.is_active = 1
    GROUP BY ec.id, ec.name
    ORDER BY total DESC
", [$startDate, $endDate]);

// Get recent expenses
$recentExpenses = $db->fetchAll("
    SELECT e.*, ec.name as category_name, u.full_name as added_by, l.name as location_name
    FROM expenses e
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
    LEFT JOIN users u ON e.user_id = u.id
    LEFT JOIN locations l ON e.location_id = l.id
    WHERE DATE(e.expense_date) BETWEEN ? AND ?
    ORDER BY e.expense_date DESC, e.created_at DESC
    LIMIT 50
", [$startDate, $endDate]);

// Get categories and locations for form
$categories = $db->fetchAll("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name");
$locations = $db->fetchAll("SELECT id, name FROM locations WHERE is_active = 1 ORDER BY name");

$pageTitle = 'Accounting & Expenses';
include 'includes/header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-calculator me-2"></i>Accounting & Financial Management</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#expenseModal">
        <i class="bi bi-plus-circle me-2"></i>Add Expense
    </button>
</div>

<!-- Date Filter -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?= $startDate ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?= $endDate ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-2"></i>Filter
                </button>
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Financial Summary Cards -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Total Revenue</p>
                        <h3 class="mb-0 fw-bold text-success"><?= formatMoney($revenue['total']) ?></h3>
                    </div>
                    <i class="bi bi-arrow-up-circle text-success fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Total Expenses</p>
                        <h3 class="mb-0 fw-bold text-danger"><?= formatMoney($expenses['total']) ?></h3>
                    </div>
                    <i class="bi bi-arrow-down-circle text-danger fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Net Profit</p>
                        <h3 class="mb-0 fw-bold text-<?= $profit >= 0 ? 'primary' : 'danger' ?>"><?= formatMoney($profit) ?></h3>
                    </div>
                    <i class="bi bi-graph-up text-primary fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Profit Margin</p>
                        <h3 class="mb-0 fw-bold text-info">
                            <?php 
                            $margin = $revenue['total'] > 0 ? ($profit / $revenue['total']) * 100 : 0;
                            echo number_format($margin, 1) . '%';
                            ?>
                        </h3>
                    </div>
                    <i class="bi bi-percent text-info fs-1"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Expense Breakdown -->
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Expenses by Category</h6>
            </div>
            <div class="card-body">
                <canvas id="expenseChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>Category Breakdown</h6>
            </div>
            <div class="card-body">
                <?php foreach ($expensesByCategory as $cat): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><?= htmlspecialchars($cat['category_name']) ?></span>
                    <strong><?= formatMoney($cat['total']) ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Expenses -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Expenses</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Payment Method</th>
                        <th>Location</th>
                        <th>Amount</th>
                        <th>Added By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentExpenses as $expense): ?>
                    <tr>
                        <td><?= formatDate($expense['expense_date'], 'd/m/Y') ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($expense['category_name']) ?></span></td>
                        <td><?= htmlspecialchars($expense['description']) ?></td>
                        <td><?= ucfirst(str_replace('_', ' ', $expense['payment_method'])) ?></td>
                        <td><?= htmlspecialchars($expense['location_name'] ?? 'All') ?></td>
                        <td class="fw-bold text-danger"><?= formatMoney($expense['amount']) ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars($expense['added_by']) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_expense">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Add Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category *</label>
                        <select class="form-select" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Amount *</label>
                        <input type="number" step="0.01" class="form-control" name="amount" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <textarea class="form-control" name="description" rows="2" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Expense Date *</label>
                        <input type="date" class="form-control" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Method *</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="card">Card</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reference/Invoice #</label>
                        <input type="text" class="form-control" name="reference">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <select class="form-select" name="location_id">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Expense Pie Chart
const ctx = document.getElementById('expenseChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($expensesByCategory, 'category_name')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($expensesByCategory, 'total')) ?>,
            backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
