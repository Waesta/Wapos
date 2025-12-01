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
$currencyConfig = CurrencyManager::getInstance()->getJavaScriptConfig();
$accountingLiveConfig = [
    'summary' => $ledgerDataService->getLiveFinancialSnapshot(),
    'recent_entries' => $ledgerDataService->getRecentLedgerEntries(12),
    'filters' => [
        'start_date' => $startDate,
        'end_date' => $endDate,
    ],
];

$pageTitle = 'Accounting & Expenses';
include 'includes/header.php';
?>

<script>
    window.ACCOUNTING_LIVE_CONFIG = <?= json_encode($accountingLiveConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.ACCOUNTING_LIVE_ENDPOINT = 'api/live-accounting-feed.php';
    window.CURRENCY_CONFIG = <?= json_encode($currencyConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<style>
    #accountingLiveWidgets .live-metric-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 0.75rem;
    }
    #accountingLiveWidgets .live-metric {
        padding: 0.75rem;
        border: 1px solid var(--color-border, #eef2f7);
        border-radius: 0.75rem;
        background: #f8fafc;
    }
    #accountingLiveWidgets .live-metric h5 {
        margin: 0;
        font-weight: 600;
    }
    .live-sync-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #20c997;
    }
    .live-sync-dot[data-state="syncing"] {
        background: #0d6efd;
        box-shadow: 0 0 0 4px rgba(13,110,253,.2);
    }
    .live-sync-dot[data-state="error"] {
        background: #dc3545;
    }
    #accountingLiveFeed {
        max-height: 260px;
        overflow-y: auto;
    }
</style>

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

<!-- Live Financial Pulse -->
<div class="row g-3 mb-4" id="accountingLiveWidgets">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex flex-column gap-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Live Financial Pulse</p>
                        <h3 class="mb-0" id="accountingLiveTodayNet">—</h3>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">Updated</small>
                        <div class="fw-semibold small" id="accountingLiveUpdated">—</div>
                    </div>
                </div>
                <div class="live-metric-grid">
                    <div class="live-metric">
                        <p class="text-muted small mb-1">Today Revenue</p>
                        <h5 id="accountingLiveTodayRevenue">—</h5>
                    </div>
                    <div class="live-metric">
                        <p class="text-muted small mb-1">Today Expenses</p>
                        <h5 id="accountingLiveTodayExpense">—</h5>
                    </div>
                    <div class="live-metric">
                        <p class="text-muted small mb-1">Receivables</p>
                        <h5 id="accountingLiveReceivablesTotal">—</h5>
                        <small class="text-muted" id="accountingLiveReceivablesCount">0 open</small>
                    </div>
                </div>
                <div class="live-metric-grid">
                    <div class="live-metric">
                        <p class="text-muted small mb-1">Monthly Revenue</p>
                        <h5 id="accountingLiveMonthRevenue">—</h5>
                    </div>
                    <div class="live-metric">
                        <p class="text-muted small mb-1">Monthly Expenses</p>
                        <h5 id="accountingLiveMonthExpense">—</h5>
                    </div>
                    <div class="live-metric">
                        <p class="text-muted small mb-1">Profit Margin</p>
                        <h5 id="accountingLiveMargin">—</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="live-sync-dot" id="accountingLiveDot"></div>
                        <span class="fw-semibold">Live Ledger Feed</span>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" id="accountingLiveToggle">Pause</button>
                        <button class="btn btn-outline-primary" id="accountingLiveRefresh">Refresh</button>
                    </div>
                </div>
                <div class="small text-muted mb-2" id="accountingLiveLabel">Live updates on</div>
                <ul class="list-group list-group-flush flex-grow-1" id="accountingLiveFeed">
                    <li class="list-group-item text-muted text-center">Waiting for ledger activity…</li>
                </ul>
            </div>
        </div>
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

<script>
(function() {
    const ACCOUNTING_LIVE_ENDPOINT = window.ACCOUNTING_LIVE_ENDPOINT || 'api/live-accounting-feed.php';
    const ACCOUNTING_LIVE_CONFIG = window.ACCOUNTING_LIVE_CONFIG || { summary: {}, recent_entries: [] };
    const currencyConfig = window.CURRENCY_CONFIG || { symbol: '', position: 'before', decimal_places: 2 };
    const ACCOUNTING_LIVE_POLL_INTERVAL = 15000;

    const accountingLiveElements = {};
    const accountingLiveState = {
        paused: false,
        timer: null,
        channel: null,
    };

    function initAccountingLiveWidgets() {
        accountingLiveElements.todayRevenue = document.getElementById('accountingLiveTodayRevenue');
        accountingLiveElements.todayExpense = document.getElementById('accountingLiveTodayExpense');
        accountingLiveElements.todayNet = document.getElementById('accountingLiveTodayNet');
        accountingLiveElements.monthRevenue = document.getElementById('accountingLiveMonthRevenue');
        accountingLiveElements.monthExpense = document.getElementById('accountingLiveMonthExpense');
        accountingLiveElements.margin = document.getElementById('accountingLiveMargin');
        accountingLiveElements.receivablesTotal = document.getElementById('accountingLiveReceivablesTotal');
        accountingLiveElements.receivablesCount = document.getElementById('accountingLiveReceivablesCount');
        accountingLiveElements.updated = document.getElementById('accountingLiveUpdated');
        accountingLiveElements.feed = document.getElementById('accountingLiveFeed');
        accountingLiveElements.dot = document.getElementById('accountingLiveDot');
        accountingLiveElements.label = document.getElementById('accountingLiveLabel');
        accountingLiveElements.toggleBtn = document.getElementById('accountingLiveToggle');
        accountingLiveElements.refreshBtn = document.getElementById('accountingLiveRefresh');

        renderAccountingLiveSummary(ACCOUNTING_LIVE_CONFIG.summary || {});
        renderAccountingLiveFeed(Array.isArray(ACCOUNTING_LIVE_CONFIG.recent_entries) ? ACCOUNTING_LIVE_CONFIG.recent_entries : []);

        accountingLiveElements.toggleBtn?.addEventListener('click', () => {
            accountingLiveState.paused = !accountingLiveState.paused;
            accountingLiveElements.toggleBtn.textContent = accountingLiveState.paused ? 'Resume' : 'Pause';
            accountingLiveElements.label.textContent = accountingLiveState.paused ? 'Live updates paused' : 'Live updates on';
            if (accountingLiveState.paused) {
                setDotState('idle');
                clearTimeout(accountingLiveState.timer);
            } else {
                fetchAccountingLiveData(true);
            }
        });

        accountingLiveElements.refreshBtn?.addEventListener('click', () => fetchAccountingLiveData(true));

        if ('BroadcastChannel' in window) {
            accountingLiveState.channel = new BroadcastChannel('accounting-live-feed');
            accountingLiveState.channel.onmessage = event => {
                if (event?.data?.type === 'accounting-live-sync' && !accountingLiveState.paused) {
                    const payload = event.data.payload || {};
                    if (payload.summary) {
                        renderAccountingLiveSummary(payload.summary);
                    }
                    if (Array.isArray(payload.entries)) {
                        renderAccountingLiveFeed(payload.entries);
                    }
                }
            };
        }

        fetchAccountingLiveData();
    }

    function setDotState(state) {
        if (!accountingLiveElements.dot) return;
        const validStates = ['live', 'syncing', 'error', 'idle'];
        if (!validStates.includes(state)) {
            state = 'idle';
        }
        accountingLiveElements.dot.setAttribute('data-state', state === 'live' ? 'live' : state);
    }

    function scheduleNextTick() {
        clearTimeout(accountingLiveState.timer);
        accountingLiveState.timer = setTimeout(fetchAccountingLiveData, ACCOUNTING_LIVE_POLL_INTERVAL);
    }

    function fetchAccountingLiveData(force = false) {
        if (accountingLiveState.paused && !force) {
            return;
        }

        setDotState('syncing');
        accountingLiveElements.label.textContent = 'Syncing latest ledger data…';

        fetch(`${ACCOUNTING_LIVE_ENDPOINT}?_=${Date.now()}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to load live feed');
                }
                return response.json();
            })
            .then(data => {
                if (!data?.success) {
                    throw new Error(data?.message || 'Live feed unavailable');
                }

                const summary = data.summary || {};
                const entries = Array.isArray(data.recent_entries) ? data.recent_entries : [];
                renderAccountingLiveSummary(summary);
                renderAccountingLiveFeed(entries);
                accountingLiveElements.label.textContent = 'Live updates on';
                setDotState('live');

                if (accountingLiveState.channel) {
                    accountingLiveState.channel.postMessage({
                        type: 'accounting-live-sync',
                        payload: { summary, entries }
                    });
                }

                scheduleNextTick();
            })
            .catch(error => {
                console.error(error);
                accountingLiveElements.label.textContent = 'Unable to refresh live feed';
                setDotState('error');
                scheduleNextTick();
            });
    }

    function renderAccountingLiveSummary(summary) {
        accountingLiveElements.todayRevenue && (accountingLiveElements.todayRevenue.textContent = formatCurrencyValue(summary.today_revenue));
        accountingLiveElements.todayExpense && (accountingLiveElements.todayExpense.textContent = formatCurrencyValue(summary.today_expense));
        accountingLiveElements.todayNet && (accountingLiveElements.todayNet.textContent = formatCurrencyValue(summary.today_net));
        accountingLiveElements.monthRevenue && (accountingLiveElements.monthRevenue.textContent = formatCurrencyValue(summary.month_revenue));
        accountingLiveElements.monthExpense && (accountingLiveElements.monthExpense.textContent = formatCurrencyValue(summary.month_expense));
        accountingLiveElements.margin && (accountingLiveElements.margin.textContent = typeof summary.profit_margin === 'number'
            ? `${summary.profit_margin.toFixed(1)}%`
            : '—');
        accountingLiveElements.receivablesTotal && (accountingLiveElements.receivablesTotal.textContent = formatCurrencyValue(summary.pending_receivables_total));
        accountingLiveElements.receivablesCount && (accountingLiveElements.receivablesCount.textContent = `${summary.pending_receivables_count ?? 0} open`);
        accountingLiveElements.updated && (accountingLiveElements.updated.textContent = formatTimestamp(summary.timestamp));
    }

    function renderAccountingLiveFeed(entries) {
        if (!accountingLiveElements.feed) {
            return;
        }

        if (!entries.length) {
            accountingLiveElements.feed.innerHTML = '<li class="list-group-item text-center text-muted">No recent ledger entries</li>';
            return;
        }

        accountingLiveElements.feed.innerHTML = entries.map(entry => {
            const label = escapeHtml(entry.entry_number || `JE-${entry.id}`);
            const description = escapeHtml(entry.description || 'No description');
            const timestamp = formatTimestamp(entry.created_at || entry.entry_date);
            const lines = Array.isArray(entry.lines) ? entry.lines : [];
            const lineHtml = lines.map(line => {
                const accountLabel = escapeHtml(`${line.account_code || ''} ${line.account_name || ''}`.trim());
                const debit = Number(line.debit || 0);
                const credit = Number(line.credit || 0);
                const amountLabel = debit > 0
                    ? `<span class="text-danger">D ${formatCurrencyValue(debit)}</span>`
                    : `<span class="text-success">C ${formatCurrencyValue(credit)}</span>`;
                return `<div class="d-flex justify-content-between small text-muted"><span>${accountLabel}</span>${amountLabel}</div>`;
            }).join('');

            return `
                <li class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>${label}</strong>
                            <div class="text-muted small">${description}</div>
                        </div>
                        <div class="text-end small text-muted">${timestamp}</div>
                    </div>
                    <div class="mt-2">
                        ${lineHtml || '<div class="text-muted small">No lines available</div>'}
                    </div>
                </li>
            `;
        }).join('');
    }

    function formatTimestamp(value) {
        if (!value) {
            return '—';
        }
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value;
        }
        return date.toLocaleString(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            month: 'short',
            day: 'numeric'
        });
    }

    function formatCurrencyValue(amount) {
        const value = Number(amount || 0);
        const decimals = Number(currencyConfig.decimal_places ?? 2);
        const formatted = value.toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
        if (!currencyConfig.symbol) {
            return formatted;
        }
        return currencyConfig.position === 'after'
            ? `${formatted} ${currencyConfig.symbol}`
            : `${currencyConfig.symbol} ${formatted}`;
    }

    function escapeHtml(str) {
        if (typeof str !== 'string') {
            return '';
        }
        return str.replace(/[&<>'"]/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[char]);
    }

    document.addEventListener('DOMContentLoaded', initAccountingLiveWidgets);
})();
</script>

<?php include 'includes/footer.php'; ?>
