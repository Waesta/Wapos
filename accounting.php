<?php
require_once 'includes/bootstrap.php';

use App\Services\AccountingService;
use App\Services\LedgerDataService;

$auth->requireRole(['admin', 'manager']);

$db = Database::getInstance();
$pdo = $db->getConnection();
$accountingService = new AccountingService($pdo);
$ledgerDataService = new LedgerDataService($pdo, $accountingService);

// Handle accounting actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation for all accounting POST actions
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        showAlert('Invalid request. Please try again.', 'error');
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'add_expense') {
                $db->beginTransaction();

                $expensePayload = [
                    'amount' => (float) $_POST['amount'],
                    'tax_amount' => isset($_POST['tax_amount']) ? (float) $_POST['tax_amount'] : 0,
                    'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
                    'description' => sanitizeInput($_POST['description'] ?? ''),
                    'expense_date' => $_POST['expense_date'] ?? date('Y-m-d'),
                    'payment_method' => $_POST['payment_method'] ?? 'cash',
                    'reference' => sanitizeInput($_POST['reference'] ?? ''),
                    'user_id' => $auth->getUserId(),
                    'location_id' => !empty($_POST['location_id']) ? (int) $_POST['location_id'] : null
                ];

                $expenseId = $db->insert('expenses', [
                    'user_id' => $expensePayload['user_id'],
                    'category_id' => $expensePayload['category_id'],
                    'description' => $expensePayload['description'],
                    'amount' => $expensePayload['amount'],
                    'payment_method' => $expensePayload['payment_method'],
                    'reference' => $expensePayload['reference'],
                    'expense_date' => $expensePayload['expense_date'],
                    'location_id' => $expensePayload['location_id'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                if (!$expenseId) {
                    throw new Exception('Failed to save expense');
                }

                $accountingService->postExpense((int) $expenseId, $expensePayload);

                $db->commit();
                showAlert('Expense added successfully', 'success');

            } elseif ($action === 'add_journal_entry') {
                $entries = json_decode($_POST['entries'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($entries) || empty($entries)) {
                    throw new Exception('No journal lines provided');
                }

                $description = sanitizeInput($_POST['description'] ?? 'Manual journal entry');
                $reference = sanitizeInput($_POST['reference'] ?? '');
                $entryDate = $_POST['entry_date'] ?? date('Y-m-d');

                if ($accountingService->isPeriodLocked($entryDate)) {
                    throw new Exception('Accounting period locked for ' . $entryDate);
                }

                $totalDebits = 0;
                $totalCredits = 0;

                foreach ($entries as $entry) {
                    $totalDebits += (float) ($entry['debit'] ?? 0);
                    $totalCredits += (float) ($entry['credit'] ?? 0);
                }

                if (abs($totalDebits - $totalCredits) > 0.01) {
                    throw new Exception('Journal entry must balance. Debits: ' . $totalDebits . ', Credits: ' . $totalCredits);
                }

                $db->beginTransaction();
                $journalId = $accountingService->createJournalEntry([
                    'entry_number' => $accountingService->generateEntryNumber(),
                    'source' => 'manual',
                    'source_id' => null,
                    'reference_no' => $reference ?: 'MANUAL-' . uniqid(),
                    'entry_date' => $entryDate,
                    'description' => $description,
                    'total_debit' => $totalDebits,
                    'total_credit' => $totalCredits,
                    'period_id' => $accountingService->resolvePeriod($entryDate),
                    'status' => 'draft'
                ]);

                $lines = [];
                foreach ($entries as $entry) {
                    $lines[] = [
                        'account_id' => (int) $entry['account_id'],
                        'debit' => (float) ($entry['debit'] ?? 0),
                        'credit' => (float) ($entry['credit'] ?? 0),
                        'description' => $entry['description'] ?? $description
                    ];
                }

                $accountingService->storeJournalLines($journalId, $lines);
                $accountingService->markAsPosted($journalId);

                $db->commit();
                showAlert('Journal entry created successfully', 'success');

            } elseif ($action === 'reconcile_account') {
                $accountId = (int) $_POST['account_id'];
                $statementBalance = (float) $_POST['statement_balance'];
                $reconciliationDate = $_POST['reconciliation_date'] ?? date('Y-m-d');

                $selectedTransactions = array_map('intval', $_POST['reconciled_transactions'] ?? []);

                $db->beginTransaction();

                if (!empty($selectedTransactions)) {
                    $placeholders = implode(',', array_fill(0, count($selectedTransactions), '?'));
                    $params = array_merge([$reconciliationDate], $selectedTransactions);
                    $db->query("UPDATE journal_entry_lines SET is_reconciled = 1, reconciled_date = ? WHERE id IN ({$placeholders})", $params);
                }

                $journalBalance = $accountingService->sumAsOfByAccountIds([$accountId], $reconciliationDate, false);

                $db->insert('account_reconciliations', [
                    'account_id' => $accountId,
                    'reconciliation_date' => $reconciliationDate,
                    'statement_balance' => $statementBalance,
                    'book_balance' => $journalBalance,
                    'reconciled_by' => $auth->getUserId(),
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                $db->commit();
                showAlert('Account reconciled successfully', 'success');
            }

        } catch (Exception $e) {
            if ($db->getConnection()->inTransaction()) {
                $db->rollback();
            }
            showAlert('Error: ' . $e->getMessage(), 'error');
        }
    }
}

// Date filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Ledger-backed financial summary
$financialSummary = $ledgerDataService->getFinancialSummary($startDate, $endDate);
$revenueTotal = $financialSummary['revenue_total'];
$cogsTotal = $financialSummary['cogs_total'];
$expenseTotal = $financialSummary['total_expense'];
$grossProfit = $financialSummary['gross_profit'];
$netProfit = $financialSummary['net_profit'];
$profitMargin = $financialSummary['profit_margin'];

// Expense breakdown and recent ledger movement
$expenseChart = $ledgerDataService->getExpenseChartData($startDate, $endDate);
$expensesByCategory = $expenseChart['raw'];
$recentExpenses = $ledgerDataService->getRecentExpenseEntries($startDate, $endDate);

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
                        <h3 class="mb-0 fw-bold text-success"><?= formatMoney($revenueTotal) ?></h3>
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
                        <h3 class="mb-0 fw-bold text-danger"><?= formatMoney($expenseTotal + $cogsTotal) ?></h3>
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
                        <h3 class="mb-0 fw-bold text-<?= $netProfit >= 0 ? 'primary' : 'danger' ?>"><?= formatMoney($netProfit) ?></h3>
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
                            <?= number_format($profitMargin, 1) ?>%
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
                    <span><?= htmlspecialchars("{$cat['account_code']} - {$cat['account_name']}") ?></span>
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
                        <th>Entry Date</th>
                        <th>Account</th>
                        <th>Description</th>
                        <th>Debit</th>
                        <th>Credit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentExpenses as $expense): ?>
                    <tr>
                        <td><?= formatDate($expense['entry_date'], 'd/m/Y') ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars("{$expense['account_code']} - {$expense['account_name']}") ?></span></td>
                        <td><?= htmlspecialchars($expense['description']) ?></td>
                        <td class="text-danger fw-semibold"><?= formatMoney($expense['debit_amount']) ?></td>
                        <td class="text-success fw-semibold"><?= formatMoney($expense['credit_amount']) ?></td>
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
                        <label class="form-label">Amount (Gross) *</label>
                        <input type="number" step="0.01" class="form-control" name="amount" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tax (Input VAT)</label>
                        <input type="number" step="0.01" class="form-control" name="tax_amount" placeholder="0.00">
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
        labels: <?= json_encode(array_map(function ($row) {
            return $row['account_code'] . ' - ' . $row['account_name'];
        }, $expensesByCategory)) ?>,
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
