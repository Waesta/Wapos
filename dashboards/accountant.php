<?php
/**
 * Accountant Dashboard
 * Financial overview and accounting access
 */

require_once '../includes/bootstrap.php';

use App\Services\AccountingService;
use App\Services\LedgerDataService;

$auth->requireRole('accountant');

$db = Database::getInstance();
$pdo = $db->getConnection();
$accountingService = new AccountingService($pdo);
$ledgerDataService = new LedgerDataService($pdo, $accountingService);

$pageTitle = 'Accountant Dashboard';
include '../includes/header.php';

// Get financial summary
$today = date('Y-m-d');
$thisMonth = date('Y-m-01');

// Today's sales
$todaySales = $db->fetchOne("
    SELECT 
        COUNT(*) as count,
        COALESCE(SUM(total_amount), 0) as total
    FROM sales 
    WHERE DATE(created_at) = ?
", [$today]) ?: ['count' => 0, 'total' => 0];

// This month's sales
$monthSales = $db->fetchOne("
    SELECT 
        COUNT(*) as count,
        COALESCE(SUM(total_amount), 0) as total
    FROM sales 
    WHERE DATE(created_at) >= ?
", [$thisMonth]) ?: ['count' => 0, 'total' => 0];

// Pending payments (where amount_paid is less than total_amount)
$pendingPayments = $db->fetchOne("
    SELECT 
        COUNT(*) as count,
        COALESCE(SUM(total_amount - amount_paid), 0) as total
    FROM sales 
    WHERE amount_paid < total_amount
") ?: ['count' => 0, 'total' => 0];

// Recent transactions
$recentTransactions = $db->fetchAll("
    SELECT 
        s.*,
        u.full_name as cashier_name
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    ORDER BY s.created_at DESC
    LIMIT 10
") ?: [];

// Get expense summary (if accounting tables exist)
$expenses = ['count' => 0, 'total' => 0];
try {
    $expenses = $db->fetchOne("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(amount), 0) as total
        FROM expenses 
        WHERE DATE(expense_date) >= ?
    ", [$thisMonth]);
} catch (Exception $e) {
    // Expenses table doesn't exist yet
}

$currencyConfig = CurrencyManager::getInstance()->getJavaScriptConfig();
$accountingLiveConfig = [
    'summary' => $ledgerDataService->getLiveFinancialSnapshot(),
    'recent_entries' => $ledgerDataService->getRecentLedgerEntries(8),
];
?>

<script>
    window.ACCOUNTING_LIVE_CONFIG = <?= json_encode($accountingLiveConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.ACCOUNTING_LIVE_ENDPOINT = '../api/live-accounting-feed.php';
    window.CURRENCY_CONFIG = <?= json_encode($currencyConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<style>
    #accountingLiveWidgets .live-metric-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.75rem;
    }
    #accountingLiveWidgets .live-metric {
        padding: 0.85rem;
        border: 1px solid var(--color-border, #e9ecef);
        border-radius: 0.85rem;
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

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-calculator me-2"></i>Accountant Dashboard</h2>
            <p class="text-muted mb-0">Financial overview and accounting management</p>
        </div>
        <div>
            <span class="badge bg-primary fs-6">
                <i class="bi bi-calendar3 me-1"></i><?= date('F d, Y') ?>
            </span>
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
    <div class="row g-3 mb-4">
        <!-- Today's Revenue -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted mb-1 small">Today's Revenue</p>
                            <h3 class="mb-0 fw-bold text-success" id="cardTodayRevenueAmount"><?= formatMoney($todaySales['total']) ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="bi bi-cash-coin text-success fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-receipt me-1"></i><?= $todaySales['count'] ?> transactions
                    </small>
                </div>
            </div>
        </div>

        <!-- Monthly Revenue -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted mb-1 small">Monthly Revenue</p>
                            <h3 class="mb-0 fw-bold text-success" id="cardMonthRevenueAmount"><?= formatMoney($monthSales['total']) ?></h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="bi bi-graph-up text-primary fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-receipt me-1"></i><?= $monthSales['count'] ?> transactions
                    </small>
                </div>
            </div>
        </div>

        <!-- Pending Payments -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted mb-1 small">Pending Payments</p>
                            <h3 class="mb-0 fw-bold text-warning" id="cardPendingPaymentsAmount"><?= formatMoney($pendingPayments['total']) ?></h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="bi bi-clock-history text-warning fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-exclamation-circle me-1"></i><span id="cardPendingPaymentsCount"><?= $pendingPayments['count'] ?></span> pending
                    </small>
                </div>
            </div>
        </div>

        <!-- Monthly Expenses -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted mb-1 small">Monthly Expenses</p>
                            <h3 class="mb-0 fw-bold text-danger" id="cardMonthExpensesAmount"><?= formatMoney($expenses['total']) ?></h3>
                        </div>
                        <div class="bg-danger bg-opacity-10 p-3 rounded">
                            <i class="bi bi-arrow-down-circle text-danger fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-receipt me-1"></i><?= $expenses['count'] ?> expenses
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h5>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <a href="../accounting.php" class="btn btn-outline-primary w-100">
                                <i class="bi bi-journal-text me-2"></i>Accounting
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="../reports.php?type=financial" class="btn btn-outline-success w-100">
                                <i class="bi bi-file-earmark-bar-graph me-2"></i>Financial Reports
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="../reports.php?type=sales" class="btn btn-outline-info w-100">
                                <i class="bi bi-graph-up me-2"></i>Sales Reports
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="../sales.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-receipt me-2"></i>View All Sales
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Transactions</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Receipt #</th>
                                    <th>Date & Time</th>
                                    <th>Cashier</th>
                                    <th>Amount</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentTransactions)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                            No transactions found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentTransactions as $sale): ?>
                                        <tr>
                                            <td><strong>#<?= str_pad($sale['id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                                            <td><?= date('M d, Y h:i A', strtotime($sale['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($sale['cashier_name'] ?? 'N/A') ?></td>
                                            <td><strong><?= formatMoney($sale['total_amount']) ?></strong></td>
                                            <td><?= ucfirst($sale['payment_method'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php
                                                // Calculate payment status based on amount_paid
                                                if ($sale['amount_paid'] >= $sale['total_amount']) {
                                                    $paymentStatus = 'completed';
                                                    $class = 'success';
                                                } elseif ($sale['amount_paid'] > 0) {
                                                    $paymentStatus = 'partial';
                                                    $class = 'info';
                                                } else {
                                                    $paymentStatus = 'pending';
                                                    $class = 'warning';
                                                }
                                                ?>
                                                <span class="badge bg-<?= $class ?>">
                                                    <?= ucfirst($paymentStatus) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="../print-receipt.php?id=<?= $sale['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   target="_blank">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <a href="../sales.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-arrow-right me-1"></i>View All Transactions
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const ACCOUNTING_LIVE_ENDPOINT = window.ACCOUNTING_LIVE_ENDPOINT || '../api/live-accounting-feed.php';
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

        fetch(`${ACCOUNTING_LIVE_ENDPOINT}?limit=8&_=${Date.now()}`, {
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

        const summaryCards = {
            todayRevenue: document.getElementById('cardTodayRevenueAmount'),
            monthRevenue: document.getElementById('cardMonthRevenueAmount'),
            pendingAmount: document.getElementById('cardPendingPaymentsAmount'),
            pendingCount: document.getElementById('cardPendingPaymentsCount'),
            monthExpenses: document.getElementById('cardMonthExpensesAmount'),
        };

        summaryCards.todayRevenue && (summaryCards.todayRevenue.textContent = formatCurrencyValue(summary.today_revenue));
        summaryCards.monthRevenue && (summaryCards.monthRevenue.textContent = formatCurrencyValue(summary.month_revenue));
        summaryCards.pendingAmount && (summaryCards.pendingAmount.textContent = formatCurrencyValue(summary.pending_receivables_total));
        summaryCards.pendingCount && (summaryCards.pendingCount.textContent = summary.pending_receivables_count ?? 0);
        summaryCards.monthExpenses && (summaryCards.monthExpenses.textContent = formatCurrencyValue(summary.month_expense));
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

<?php include '../includes/footer.php'; ?>
