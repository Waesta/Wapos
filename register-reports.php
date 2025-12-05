<?php
require_once 'includes/bootstrap.php';

use App\Services\RegisterReportService;

$allowedRoles = ['admin', 'manager', 'cashier', 'developer', 'super_admin'];
if (!$auth->isLoggedIn()) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}
if (!in_array(strtolower($auth->getRole() ?? ''), $allowedRoles, true)) {
    $_SESSION['error_message'] = 'You do not have permission to access Register Reports.';
    header('Location: ' . APP_URL . '/dashboards/admin.php');
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$reportService = new RegisterReportService($pdo);
$reportService->ensureSchema();

$activeSession = $reportService->getActiveSession();
$recentSessions = $reportService->listSessions(null, null, 10);
$recentClosures = $reportService->getRecentClosures(15);

$csrfToken = generateCSRFToken();
$pageTitle = 'Register Reports';
include 'includes/header.php';
?>

<style>
    .register-shell {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xl);
    }
    .register-grid {
        display: grid;
        gap: var(--spacing-lg);
    }
    @media (min-width: 1200px) {
        .register-grid {
            grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
        }
    }
    .register-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
    }
    .register-card header {
        padding: var(--spacing-md);
        border-bottom: 1px solid var(--color-border-subtle);
    }
    .register-card .card-body {
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-md);
    }
    .register-actions {
        display: grid;
        gap: var(--spacing-sm);
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }
    .register-highlight {
        border: 1px dashed var(--color-border);
        border-radius: var(--radius-md);
        padding: var(--spacing-md);
        background: var(--color-surface-subtle);
    }
    .register-table-wrapper {
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--radius-md);
        overflow: hidden;
    }
    .register-report-output {
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--radius-md);
        padding: var(--spacing-md);
        background: var(--color-surface-subtle);
        display: none;
        white-space: pre-wrap;
        font-family: var(--font-family-monospace, 'JetBrains Mono', monospace);
        font-size: 0.9rem;
        max-height: 400px;
        overflow: auto;
    }
    .badge-live {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
    .stack-sm {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    .stack-xs {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xs);
    }
    .report-section {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
        padding: var(--spacing-md);
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--radius-md);
        background: var(--color-surface);
    }
    .report-section + .report-section {
        margin-top: var(--spacing-md);
    }
    .section-title {
        font-size: var(--text-base);
        font-weight: 600;
    }
    .report-meta-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: var(--spacing-sm);
        margin: 0;
    }
    .report-meta-list div {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    .report-meta-list dt {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: var(--color-text-muted);
        margin: 0;
    }
    .report-meta-list dd {
        margin: 0;
        font-weight: 600;
    }
    .report-metrics-grid {
        display: grid;
        gap: var(--spacing-sm);
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    }
    .report-metric-card {
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--radius-md);
        background: var(--color-surface-subtle);
        padding: var(--spacing-sm) var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }
    .report-metric-card .label {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: var(--color-text-muted);
    }
    .report-metric-card .value {
        font-size: 1.25rem;
        font-weight: 700;
    }
    .report-metric-card small {
        color: var(--color-text-muted);
    }
    .report-empty {
        padding: var(--spacing-sm);
        border-radius: var(--radius-sm);
        background: var(--color-surface-subtle);
        color: var(--color-text-muted);
        font-size: 0.9rem;
    }
    .report-table-wrapper {
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--radius-md);
        overflow: hidden;
    }
    .report-table thead {
        background: var(--color-surface-subtle);
    }
    .report-table th,
    .report-table td {
        font-size: 0.9rem;
    }
    .report-raw {
        margin-top: var(--spacing-md);
    }
    .report-raw pre {
        background: var(--color-surface-subtle);
        border-radius: var(--radius-md);
        padding: var(--spacing-md);
        max-height: 300px;
        overflow: auto;
        font-family: var(--font-family-monospace, 'JetBrains Mono', monospace);
        font-size: 0.85rem;
    }
</style>

<div class="container-fluid py-4 register-shell">
    <section class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="stack-sm">
            <h1 class="mb-0"><i class="bi bi-printer-fill text-primary me-2"></i>Register Reports</h1>
            <p class="text-muted mb-0">Generate X, Y, and Z register snapshots, manage cash sessions, and review closures.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-outline-primary" data-action="generate-x">
                <i class="bi bi-speedometer2"></i>Generate X Report
            </button>
            <button class="btn btn-outline-success" data-action="generate-y">
                <i class="bi bi-clock-history"></i>Generate Y Report
            </button>
            <button class="btn btn-outline-danger" data-action="generate-z">
                <i class="bi bi-calendar-check"></i>Generate Z Report
            </button>
        </div>
    </section>

    <section class="register-grid">
        <article class="register-card">
            <header class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-0"><i class="bi bi-cash-stack me-2"></i>Register Session</h5>
                    <small class="text-muted">Track the current drawer opening and closing.</small>
                </div>
                <span class="badge bg-<?= $activeSession ? 'success' : 'secondary' ?> badge-live" id="sessionStatusBadge">
                    <span class="spinner-grow spinner-grow-sm<?= $activeSession ? '' : ' d-none' ?>" id="sessionStatusSpinner" role="status"></span>
                    <span id="sessionStatusLabel"><?= $activeSession ? 'Session Active' : 'No Active Session' ?></span>
                </span>
            </header>
            <div class="card-body">
                <div class="register-highlight" id="sessionSummary">
                    <?php if ($activeSession): ?>
                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                            <div class="stack-xs">
                                <strong>Opened:</strong>
                                <span><?= date('M j, g:i a', strtotime($activeSession['opened_at'])) ?></span>
                                <small class="text-muted">Opening Float: <?= formatMoney($activeSession['opening_amount']) ?></small>
                            </div>
                            <div class="stack-xs">
                                <strong>Operator:</strong>
                                <span><?= htmlspecialchars($auth->getUser()['full_name'] ?? $auth->getUsername()) ?></span>
                            </div>
                            <div class="stack-xs">
                                <strong>Session ID:</strong>
                                <span>#<?= $activeSession['id'] ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="mb-0 text-muted">No session is currently open. Start of day? Open a drawer session to log cash handling.</p>
                    <?php endif; ?>
                </div>

                <div class="register-actions">
                    <button class="btn btn-success" id="openSessionBtn" <?= $activeSession ? 'disabled' : '' ?>>
                        <i class="bi bi-play-circle me-1"></i>Open Session
                    </button>
                    <button class="btn btn-outline-danger" id="closeSessionBtn" <?= $activeSession ? '' : 'disabled' ?>>
                        <i class="bi bi-stop-circle me-1"></i>Close Session
                    </button>
                    <button class="btn btn-outline-secondary" id="refreshSessionsBtn">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh Sessions
                    </button>
                </div>

                <div>
                    <h6 class="mb-2">Recent Sessions</h6>
                    <div class="register-table-wrapper">
                        <table class="table table-sm mb-0 align-middle" id="sessionTable">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Status</th>
                                    <th>Opened</th>
                                    <th>Closed</th>
                                    <th>Opening</th>
                                    <th>Closing</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentSessions)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-3 text-muted">No sessions recorded yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentSessions as $session): ?>
                                        <tr>
                                            <td>#<?= (int)$session['id'] ?></td>
                                            <td>
                                                <span class="badge bg-<?= $session['status'] === 'open' ? 'success' : 'secondary' ?>">
                                                    <?= ucfirst($session['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($session['opened_at']) ?></td>
                                            <td><?= $session['closed_at'] ? htmlspecialchars($session['closed_at']) : '—' ?></td>
                                            <td><?= formatMoney($session['opening_amount']) ?></td>
                                            <td><?= $session['closing_amount'] !== null ? formatMoney($session['closing_amount']) : '—' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </article>

        <article class="register-card">
            <header>
                <h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Recent Closures</h5>
            </header>
            <div class="card-body">
                <div class="register-table-wrapper">
                    <table class="table table-sm mb-0 align-middle" id="closureTable">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Generated</th>
                                <th>Range</th>
                                <th>Reset</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentClosures)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-3 text-muted">No closures generated yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentClosures as $closure): ?>
                                    <tr>
                                        <td>#<?= (int)$closure['id'] ?></td>
                                        <td><span class="badge bg-primary"><?= htmlspecialchars($closure['closure_type']) ?></span></td>
                                        <td><?= htmlspecialchars($closure['generated_at']) ?></td>
                                        <td>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($closure['range_start']) ?><br>
                                                <?= htmlspecialchars($closure['range_end']) ?>
                                            </small>
                                        </td>
                                        <td><?= !empty($closure['reset_applied']) ? '<i class="bi bi-check-circle text-success"></i>' : '—' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <small class="text-muted">Closures log the state of the register at each snapshot. Z reports reset the baseline for subsequent X reports.</small>
            </div>
        </article>
    </section>

    <section class="register-card">
        <header class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0"><i class="bi bi-journal-check me-2"></i>Report Output</h5>
                <small class="text-muted">Generated X/Y/Z reports will appear here. Download or print as needed.</small>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="copyReportBtn" disabled><i class="bi bi-clipboard me-1"></i>Copy JSON</button>
                <button class="btn btn-outline-primary btn-sm" id="printReportBtn" disabled><i class="bi bi-printer me-1"></i>Print</button>
            </div>
        </header>
        <div class="card-body">
            <div class="register-report-output" id="reportOutput"></div>
            <p class="text-muted small mb-0">Tip: Z reports should be run once daily at close of business. X reports provide mid-shift visibility without clearing totals.</p>
        </div>
    </section>
</div>

<!-- Open Session Modal -->
<div class="modal fade" id="openSessionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-play-circle me-2 text-success"></i>Open Register Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Opening Float</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-cash"></i></span>
                        <input type="number" min="0" class="form-control" id="openingAmount" value="0" step="0.01">
                    </div>
                    <small class="text-muted">Amount currently in the drawer.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Note (optional)</label>
                    <textarea class="form-control" id="openingNote" rows="2" placeholder="e.g. Morning shift float"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmOpenSession">Open Session</button>
            </div>
        </div>
    </div>
</div>

<!-- Close Session Modal -->
<div class="modal fade" id="closeSessionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-stop-circle me-2 text-danger"></i>Close Register Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Counted Cash</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-cash-coin"></i></span>
                        <input type="number" min="0" class="form-control" id="closingAmount" step="0.01">
                    </div>
                    <small class="text-muted">Actual cash counted when closing the drawer.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Closing Note (optional)</label>
                    <textarea class="form-control" id="closingNote" rows="2" placeholder="e.g. End of day balance"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmCloseSession">Close Session</button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const registerState = {
        activeSession: <?= json_encode($activeSession ?? null) ?>,
        sessions: <?= json_encode($recentSessions) ?>,
        closures: <?= json_encode($recentClosures) ?>
    };

    const apiUrl = '<?= APP_URL ?>/api/register-reports.php';
    const reportOutput = document.getElementById('reportOutput');
    const copyReportBtn = document.getElementById('copyReportBtn');
    const printReportBtn = document.getElementById('printReportBtn');

    function updateButtons() {
        const hasReport = reportOutput.dataset.report ? true : false;
        if (hasReport) {
            copyReportBtn.removeAttribute('disabled');
            printReportBtn.removeAttribute('disabled');
        } else {
            copyReportBtn.setAttribute('disabled', 'true');
            printReportBtn.setAttribute('disabled', 'true');
        }
    }

    function buildReportMarkup(report, rawJson) {
        const type = escapeHtml((report.type || '').toUpperCase());
        const generated = escapeHtml(formatDateTime(report.generated_at));
        const range = report.range || {};
        const totals = report.totals || {};
        const sales = totals.sales || {};
        const voids = totals.voids || {};
        const drawer = totals.drawer || {};
        const payments = Array.isArray(totals.payments) ? totals.payments : [];

        const paymentTable = payments.length > 0
            ? `
                <div class="report-table-wrapper">
                    <table class="table table-sm mb-0 report-table">
                        <thead>
                            <tr>
                                <th>Payment Method</th>
                                <th class="text-center">Count</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${payments.map((row) => `
                                <tr>
                                    <td>${escapeHtml(capitalize(row.method ?? 'unknown'))}</td>
                                    <td class="text-center">${formatNumber(row.count ?? 0)}</td>
                                    <td class="text-end">${formatMoney(row.total_amount ?? 0)}</td>
                                    <td class="text-end">${formatMoney(row.paid_amount ?? 0)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `
            : '<div class="report-empty">No payment activity recorded for this range.</div>';

        const rawBlock = `<div class="report-raw"><details><summary class="small text-muted">Show raw JSON</summary><pre>${escapeHtml(rawJson)}</pre></details></div>`;

        return `
            <article class="report-section">
                <h4 class="section-title">${type} Report Summary</h4>
                <div class="report-meta-list">
                    <div>
                        <dt>Generated</dt>
                        <dd>${generated}</dd>
                    </div>
                    <div>
                        <dt>Range Start</dt>
                        <dd>${escapeHtml(formatDateTime(range.start ?? range.start_iso))}</dd>
                    </div>
                    <div>
                        <dt>Range End</dt>
                        <dd>${escapeHtml(formatDateTime(range.end ?? range.end_iso))}</dd>
                    </div>
                    ${report.closure_id ? `<div><dt>Closure ID</dt><dd>#${escapeHtml(report.closure_id)}</dd></div>` : ''}
                </div>
            </article>

            <article class="report-section">
                <h5 class="section-title">Sales Summary</h5>
                <div class="report-metrics-grid">
                    <div class="report-metric-card">
                        <span class="label">Transactions</span>
                        <span class="value">${formatNumber(sales.count ?? 0)}</span>
                    </div>
                    <div class="report-metric-card">
                        <span class="label">Gross Sales</span>
                        <span class="value">${formatMoney(sales.total ?? 0)}</span>
                        <small>Subtotal ${formatMoney(sales.subtotal ?? 0)} · Tax ${formatMoney(sales.tax ?? 0)}</small>
                    </div>
                    <div class="report-metric-card">
                        <span class="label">Discounts</span>
                        <span class="value text-danger">${formatMoney(sales.discount ?? 0)}</span>
                    </div>
                    <div class="report-metric-card">
                        <span class="label">Net Collected</span>
                        <span class="value">${formatMoney(sales.amount_paid ?? 0)}</span>
                        <small>Change issued ${formatMoney(sales.change_given ?? 0)}</small>
                    </div>
                </div>
            </article>

            <article class="report-section">
                <h5 class="section-title">Payment Breakdown</h5>
                ${paymentTable}
            </article>

            <article class="report-section">
                <h5 class="section-title">Voids & Refunds</h5>
                <div class="report-metrics-grid">
                    <div class="report-metric-card">
                        <span class="label">Void Count</span>
                        <span class="value">${formatNumber(voids.count ?? 0)}</span>
                    </div>
                    <div class="report-metric-card">
                        <span class="label">Void Amount</span>
                        <span class="value">${formatMoney(voids.total ?? 0)}</span>
                    </div>
                </div>
            </article>

            <article class="report-section">
                <h5 class="section-title">Expected Cash Drawer</h5>
                <div class="report-metrics-grid">
                    <div class="report-metric-card">
                        <span class="label">Cash Received</span>
                        <span class="value">${formatMoney(drawer.cash_received ?? 0)}</span>
                    </div>
                    <div class="report-metric-card">
                        <span class="label">Change Given</span>
                        <span class="value">${formatMoney(drawer.change_given ?? 0)}</span>
                    </div>
                    <div class="report-metric-card">
                        <span class="label">Expected Drawer Cash</span>
                        <span class="value">${formatMoney(drawer.expected_drawer_cash ?? 0)}</span>
                    </div>
                </div>
            </article>

            ${rawBlock}
        `;
    }

    function buildPrintMarkup(report, rawJson) {
        const payments = Array.isArray(report?.totals?.payments) ? report.totals.payments : [];
        const range = report.range || {};
        const sales = report.totals?.sales || {};
        const voids = report.totals?.voids || {};
        const drawer = report.totals?.drawer || {};

        const paymentRows = payments.length
            ? payments.map((row) => `
                <tr>
                    <td>${escapeHtml(capitalize(row.method ?? 'unknown'))}</td>
                    <td>${formatNumber(row.count ?? 0)}</td>
                    <td>${formatMoney(row.total_amount ?? 0)}</td>
                    <td>${formatMoney(row.paid_amount ?? 0)}</td>
                </tr>
            `).join('')
            : `<tr><td colspan="4">No payment activity recorded.</td></tr>`;

        return `
<h1>${escapeHtml((report.type || '').toUpperCase())} Report</h1>
<ul class="meta">
    <li><strong>Generated:</strong> ${escapeHtml(formatDateTime(report.generated_at))}</li>
    <li><strong>Range Start:</strong> ${escapeHtml(formatDateTime(range.start ?? range.start_iso))}</li>
    <li><strong>Range End:</strong> ${escapeHtml(formatDateTime(range.end ?? range.end_iso))}</li>
    ${report.closure_id ? `<li><strong>Closure ID:</strong> #${escapeHtml(report.closure_id)}</li>` : ''}
</ul>

<h2>Sales Summary</h2>
<div class="grid">
    <div class="card"><div class="label">Transactions</div><div class="value">${formatNumber(sales.count ?? 0)}</div></div>
    <div class="card"><div class="label">Gross Sales</div><div class="value">${formatMoney(sales.total ?? 0)}</div><div class="label">Subtotal ${formatMoney(sales.subtotal ?? 0)} · Tax ${formatMoney(sales.tax ?? 0)}</div></div>
    <div class="card"><div class="label">Discounts</div><div class="value">${formatMoney(sales.discount ?? 0)}</div></div>
    <div class="card"><div class="label">Net Collected</div><div class="value">${formatMoney(sales.amount_paid ?? 0)}</div><div class="label">Change ${formatMoney(sales.change_given ?? 0)}</div></div>
</div>

<h2>Payment Breakdown</h2>
<table>
    <thead>
        <tr><th>Method</th><th>Count</th><th>Total</th><th>Paid</th></tr>
    </thead>
    <tbody>${paymentRows}</tbody>
</table>

<h2>Voids & Refunds</h2>
<div class="grid">
    <div class="card"><div class="label">Void Count</div><div class="value">${formatNumber(voids.count ?? 0)}</div></div>
    <div class="card"><div class="label">Void Amount</div><div class="value">${formatMoney(voids.total ?? 0)}</div></div>
</div>

<h2>Expected Drawer</h2>
<div class="grid">
    <div class="card"><div class="label">Cash Received</div><div class="value">${formatMoney(drawer.cash_received ?? 0)}</div></div>
    <div class="card"><div class="label">Change Given</div><div class="value">${formatMoney(drawer.change_given ?? 0)}</div></div>
    <div class="card"><div class="label">Expected Drawer Cash</div><div class="value">${formatMoney(drawer.expected_drawer_cash ?? 0)}</div></div>
</div>

<div class="raw"><h2>Raw JSON</h2><pre>${escapeHtml(rawJson)}</pre></div>
        `;
    }

    function renderSessionSummary(session) {
        const summary = document.getElementById('sessionSummary');
        if (!session) {
            summary.innerHTML = '<p class="mb-0 text-muted">No session is currently open. Start of day? Open a drawer session to log cash handling.</p>';
            document.getElementById('openSessionBtn').removeAttribute('disabled');
            document.getElementById('closeSessionBtn').setAttribute('disabled', 'true');
            return;
        }

        summary.innerHTML = `
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div class="stack-xs">
                    <strong>Opened:</strong>
                    <span>${escapeHtml(formatDateTime(session.opened_at))}</span>
                    <small class="text-muted">Opening Float: ${formatMoney(session.opening_amount)}</small>
                </div>
                <div class="stack-xs">
                    <strong>Operator:</strong>
                    <span><?= htmlspecialchars($auth->getUser()['full_name'] ?? $auth->getUsername()) ?></span>
                </div>
                <div class="stack-xs">
                    <strong>Session ID:</strong>
                    <span>#${escapeHtml(session.id)}</span>
                </div>
            </div>
        `;

        document.getElementById('openSessionBtn').setAttribute('disabled', 'true');
        document.getElementById('closeSessionBtn').removeAttribute('disabled');
    }

    function renderSessionsTable(sessions) {
        const tbody = document.querySelector('#sessionTable tbody');
        if (!sessions || sessions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">No sessions recorded yet.</td></tr>';
            return;
        }

        tbody.innerHTML = sessions.map((session) => `
            <tr>
                <td>#${session.id}</td>
                <td><span class="badge bg-${session.status === 'open' ? 'success' : 'secondary'}">${capitalize(session.status)}</span></td>
                <td>${escapeHtml(formatDateTime(session.opened_at))}</td>
                <td>${session.closed_at ? escapeHtml(formatDateTime(session.closed_at)) : '—'}</td>
                <td>${formatMoney(session.opening_amount ?? 0)}</td>
                <td>${session.closing_amount !== null ? formatMoney(session.closing_amount) : '—'}</td>
            </tr>
        `).join('');
    }

    function renderClosuresTable(closures) {
        const tbody = document.querySelector('#closureTable tbody');
        if (!closures || closures.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">No closures generated yet.</td></tr>';
            return;
        }

        tbody.innerHTML = closures.map((closure) => `
            <tr>
                <td>#${closure.id}</td>
                <td><span class="badge bg-primary">${escapeHtml(closure.closure_type)}</span></td>
                <td>${escapeHtml(formatDateTime(closure.generated_at))}</td>
                <td><small class="text-muted">${escapeHtml(formatDateTime(closure.range_start))}<br>${escapeHtml(formatDateTime(closure.range_end))}</small></td>
                <td>${closure.reset_applied ? '<i class="bi bi-check-circle text-success"></i>' : '—'}</td>
            </tr>
        `).join('');
    }

    function callApi(action, payload = {}) {
        const body = Object.assign({ action, csrf_token: csrfToken }, payload);
        return fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(async (res) => {
            const data = await res.json().catch(() => ({ success: false, message: 'Invalid server response' }));
            if (!res.ok || data.success === false) {
                throw new Error(data.message || 'Request failed');
            }
            return data;
        });
    }

    function showReport(report) {
        if (!report) {
            reportOutput.style.display = 'none';
            reportOutput.innerHTML = '';
            delete reportOutput.dataset.report;
            updateButtons();
            return;
        }

        const json = JSON.stringify(report, null, 2);
        reportOutput.innerHTML = buildReportMarkup(report, json);
        reportOutput.dataset.report = json;
        reportOutput.style.display = 'block';
        updateButtons();
        reportOutput.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function handleError(error) {
        const message = error?.message || 'Something went wrong. Please try again.';
        const toast = document.createElement('div');
        toast.className = 'alert alert-danger alert-dismissible fade show position-fixed top-0 end-0 m-3';
        toast.style.zIndex = '1080';
        toast.innerHTML = `
            <strong>Error:</strong> ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.body.appendChild(toast);
        setTimeout(() => bootstrap.Alert.getOrCreateInstance(toast).close(), 6000);
    }

    function escapeHtml(value) {
        return (value ?? '').toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatMoney(amount) {
        const numeric = parseFloat(amount ?? 0) || 0;
        return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(numeric);
    }

    function formatNumber(value) {
        return new Intl.NumberFormat('en-US').format(value ?? 0);
    }

    function formatDateTime(value) {
        if (!value) return '—';
        const date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) {
            return value;
        }
        return date.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    function capitalize(value) {
        if (!value) return '';
        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    function updateSessionBadge(session) {
        const badge = document.getElementById('sessionStatusBadge');
        const spinner = document.getElementById('sessionStatusSpinner');
        const label = document.getElementById('sessionStatusLabel');
        if (!badge || !spinner || !label) return;

        if (session) {
            badge.classList.remove('bg-secondary');
            badge.classList.add('bg-success');
            spinner.classList.remove('d-none');
            label.textContent = 'Session Active';
        } else {
            badge.classList.remove('bg-success');
            badge.classList.add('bg-secondary');
            spinner.classList.add('d-none');
            label.textContent = 'No Active Session';
        }
    }

    // Event bindings
    document.querySelector('[data-action="generate-x"]').addEventListener('click', () => {
        callApi('generate_x')
            .then(({ report }) => showReport(report))
            .catch(handleError);
    });

    document.querySelector('[data-action="generate-y"]').addEventListener('click', () => {
        const sessionId = registerState.activeSession?.id || 0;
        if (!sessionId) {
            handleError(new Error('No active session. Open or select a session to generate a Y report.'));
            return;
        }
        callApi('generate_y', { session_id: sessionId })
            .then(({ report }) => showReport(report))
            .catch(handleError);
    });

    document.querySelector('[data-action="generate-z"]').addEventListener('click', () => {
        const finalize = confirm('Generate end-of-day Z report? This will reset register totals.');
        callApi('generate_z', { finalize })
            .then(({ report }) => {
                showReport(report);
                if (report.closure_id) {
                    refreshClosures();
                }
            })
            .catch(handleError);
    });

    document.getElementById('openSessionBtn').addEventListener('click', () => {
        new bootstrap.Modal(document.getElementById('openSessionModal')).show();
    });

    document.getElementById('closeSessionBtn').addEventListener('click', () => {
        if (!registerState.activeSession) {
            handleError(new Error('No active session to close.'));
            return;
        }
        new bootstrap.Modal(document.getElementById('closeSessionModal')).show();
    });

    document.getElementById('refreshSessionsBtn').addEventListener('click', refreshSessions);

    document.getElementById('confirmOpenSession').addEventListener('click', () => {
        const openingAmount = parseFloat(document.getElementById('openingAmount').value || '0');
        const note = document.getElementById('openingNote').value || null;

        callApi('open_session', { opening_amount: openingAmount, note })
            .then(({ session }) => {
                registerState.activeSession = session;
                refreshSessions();
                renderSessionSummary(session);
                bootstrap.Modal.getInstance(document.getElementById('openSessionModal')).hide();
                updateSessionBadge(session);
            })
            .catch(handleError);
    });

    document.getElementById('confirmCloseSession').addEventListener('click', () => {
        if (!registerState.activeSession) {
            handleError(new Error('No active session to close.'));
            return;
        }
        const closingAmountValue = document.getElementById('closingAmount').value;
        const closingAmount = closingAmountValue === '' ? null : parseFloat(closingAmountValue);
        const note = document.getElementById('closingNote').value || null;

        callApi('close_session', {
            session_id: registerState.activeSession.id,
            closing_amount: closingAmount,
            note,
        })
            .then(({ session }) => {
                registerState.activeSession = null;
                refreshSessions();
                renderSessionSummary(null);
                bootstrap.Modal.getInstance(document.getElementById('closeSessionModal')).hide();
                updateSessionBadge(null);
            })
            .catch(handleError);
    });

    copyReportBtn.addEventListener('click', () => {
        const report = reportOutput.dataset.report;
        if (!report) return;
        navigator.clipboard.writeText(report).then(() => {
            copyReportBtn.innerHTML = '<i class="bi bi-clipboard-check"></i>Copied!';
            setTimeout(() => {
                copyReportBtn.innerHTML = '<i class="bi bi-clipboard"></i>Copy JSON';
            }, 2000);
        });
    });

    printReportBtn.addEventListener('click', () => {
        const reportJson = reportOutput.dataset.report;
        if (!reportJson) return;

        let report;
        try {
            report = JSON.parse(reportJson);
        } catch (e) {
            handleError(new Error('Unable to parse report for printing.'));
            return;
        }

        const html = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Register Report</title>
<style>
body { font-family: Arial, sans-serif; color: #222; margin: 24px; }
h1 { font-size: 20px; margin-bottom: 4px; }
h2 { font-size: 16px; margin: 24px 0 8px; }
table { width: 100%; border-collapse: collapse; margin-top: 8px; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
th { background: #f5f5f5; }
.grid { display: flex; flex-wrap: wrap; gap: 12px; }
.card { border: 1px solid #ddd; border-radius: 6px; padding: 12px; min-width: 160px; }
.label { font-size: 11px; text-transform: uppercase; color: #666; }
.value { font-size: 18px; font-weight: bold; margin-top: 4px; }
.meta { margin: 0; padding: 0; list-style: none; display: flex; gap: 16px; font-size: 12px; }
.meta li { margin: 0; }
.raw { margin-top: 24px; font-size: 11px; white-space: pre-wrap; word-break: break-word; }
</style>
</head>
<body>
${buildPrintMarkup(report, reportJson)}
</body>
</html>`;

        const w = window.open('', '_blank');
        if (!w) return;
        w.document.write(html);
        w.document.close();
        w.focus();
        setTimeout(() => w.print(), 200);
    });

    function refreshSessions() {
        callApi('list_sessions', { limit: 10 })
            .then(({ sessions }) => {
                registerState.sessions = sessions;
                renderSessionsTable(sessions);
                registerState.activeSession = sessions.find((session) => session.status === 'open') || registerState.activeSession;
                renderSessionSummary(registerState.activeSession);
                updateSessionBadge(registerState.activeSession);
            })
            .catch(handleError);
    }

    function refreshClosures() {
        callApi('list_closures', { limit: 15 })
            .then(({ closures }) => {
                registerState.closures = closures;
                renderClosuresTable(closures);
            })
            .catch(handleError);
    }

    // Initial render
    renderSessionSummary(registerState.activeSession);
    renderSessionsTable(registerState.sessions);
    renderClosuresTable(registerState.closures);
    updateButtons();
    updateSessionBadge(registerState.activeSession);
})();
</script>

<?php include 'includes/footer.php'; ?>
