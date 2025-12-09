<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

// Check permission
$userRole = $auth->getRole();
if (!$userRole || !in_array($userRole, ['admin', 'manager', 'accountant', 'developer', 'super_admin'])) {
    $_SESSION['error_message'] = 'You do not have permission to view void reports.';
    redirectToDashboard($auth);
}

$db = Database::getInstance();

// Get date range from request or default to last 30 days
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Normalize date inputs (swap if out of order)
if (strtotime($startDate) > strtotime($endDate)) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

// Fetch settings to surface current policy context
$voidSettingsRows = $db->fetchAll("SELECT setting_key, setting_value FROM void_settings");
$voidSettings = [];
foreach ($voidSettingsRows as $row) {
    $voidSettings[$row['setting_key']] = $row['setting_value'];
}

$voidTimeLimit = (int)($voidSettings['void_time_limit_minutes'] ?? 0);
$managerApprovalThreshold = (float)($voidSettings['require_manager_approval_amount'] ?? 0);
$dailyLimit = (int)($voidSettings['void_daily_limit'] ?? 0);
$autoAdjustInventory = ($voidSettings['auto_adjust_inventory'] ?? '1') === '1';
$notificationEmail = $voidSettings['void_notification_email'] ?? '';

// Get void statistics
$voidStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_voids,
        SUM(original_total) as total_amount,
        COUNT(DISTINCT voided_by_user_id) as unique_users,
        COUNT(CASE WHEN manager_user_id IS NOT NULL THEN 1 END) as manager_approved
    FROM void_transactions
    WHERE DATE(void_timestamp) BETWEEN ? AND ?
", [$startDate, $endDate]) ?: ['total_voids' => 0, 'total_amount' => 0, 'unique_users' => 0, 'manager_approved' => 0];

$averageVoidAmount = $voidStats['total_voids'] > 0 ? ($voidStats['total_amount'] / $voidStats['total_voids']) : 0;
$managerApprovalRate = $voidStats['total_voids'] > 0 ? round(($voidStats['manager_approved'] / $voidStats['total_voids']) * 100, 1) : 0;

// Track void distribution by order type for mix analysis
$voidsByOrderType = $db->fetchAll("
    SELECT 
        vt.order_type,
        COUNT(*) as count,
        SUM(vt.original_total) as total_amount
    FROM void_transactions vt
    WHERE DATE(vt.void_timestamp) BETWEEN ? AND ?
    GROUP BY vt.order_type
    ORDER BY count DESC
", [$startDate, $endDate]);

// Identify outlier voids for audit attention
$highValueVoids = $db->fetchAll("
    SELECT 
        vt.order_id,
        vt.order_type,
        vt.original_total,
        vt.void_timestamp,
        vt.void_reason_code,
        vrc.display_name as reason_name,
        u.username as voided_by,
        m.username as manager_name
    FROM void_transactions vt
    LEFT JOIN void_reason_codes vrc ON vt.void_reason_code = vrc.code
    LEFT JOIN users u ON vt.voided_by_user_id = u.id
    LEFT JOIN users m ON vt.manager_user_id = m.id
    WHERE DATE(vt.void_timestamp) BETWEEN ? AND ?
    ORDER BY vt.original_total DESC
    LIMIT 8
", [$startDate, $endDate]);

// Get voids by reason
$voidsByReason = $db->fetchAll("
    SELECT 
        vt.void_reason_code,
        vrc.display_name,
        COUNT(*) as count,
        SUM(vt.original_total) as total_amount
    FROM void_transactions vt
    LEFT JOIN void_reason_codes vrc ON vt.void_reason_code = vrc.code
    WHERE DATE(vt.void_timestamp) BETWEEN ? AND ?
    GROUP BY vt.void_reason_code, vrc.display_name
    ORDER BY count DESC
", [$startDate, $endDate]);

// Get voids by user
$voidsByUser = $db->fetchAll("
    SELECT 
        u.username,
        u.role,
        COUNT(*) as count,
        SUM(vt.original_total) as total_amount
    FROM void_transactions vt
    LEFT JOIN users u ON vt.voided_by_user_id = u.id
    WHERE DATE(vt.void_timestamp) BETWEEN ? AND ?
    GROUP BY u.id, u.username, u.role
    ORDER BY count DESC
    LIMIT 10
", [$startDate, $endDate]);

// Get recent void transactions
$recentVoids = $db->fetchAll("
    SELECT 
        vt.*,
        vrc.display_name as reason_name,
        u.username as voided_by,
        m.username as manager_name
    FROM void_transactions vt
    LEFT JOIN void_reason_codes vrc ON vt.void_reason_code = vrc.code
    LEFT JOIN users u ON vt.voided_by_user_id = u.id
    LEFT JOIN users m ON vt.manager_user_id = m.id
    WHERE DATE(vt.void_timestamp) BETWEEN ? AND ?
    ORDER BY vt.void_timestamp DESC
    LIMIT 50
", [$startDate, $endDate]);

// Get daily void trend
$dailyTrend = $db->fetchAll("
    SELECT 
        DATE(void_timestamp) as date,
        COUNT(*) as count,
        SUM(original_total) as total_amount
    FROM void_transactions
    WHERE DATE(void_timestamp) BETWEEN ? AND ?
    GROUP BY DATE(void_timestamp)
    ORDER BY date ASC
", [$startDate, $endDate]);

$dailyTrendCounts = array_sum(array_column($dailyTrend, 'count'));
$dailyTrendAmounts = array_sum(array_map(fn($row) => $row['total_amount'] ?? 0, $dailyTrend));

$policyHighlights = [
    [
        'label' => 'Time Limit',
        'value' => $voidTimeLimit > 0 ? $voidTimeLimit . ' min' : 'Not enforced',
        'icon' => 'hourglass-split',
        'variant' => $voidTimeLimit > 0 ? 'warning' : 'secondary',
        'description' => $voidTimeLimit > 0 ? 'Orders older than this require approval.' : 'No age-based constraint currently active.'
    ],
    [
        'label' => 'Approval Threshold',
        'value' => $managerApprovalThreshold > 0 ? number_format($managerApprovalThreshold, 2) : 'Not set',
        'icon' => 'shield-check',
        'variant' => $managerApprovalThreshold > 0 ? 'info' : 'secondary',
        'description' => 'Voids above this total must be escalated.'
    ],
    [
        'label' => 'Daily Limit',
        'value' => $dailyLimit > 0 ? $dailyLimit . ' per user' : 'No limit',
        'icon' => 'calendar-check',
        'variant' => $dailyLimit > 0 ? 'primary' : 'secondary',
        'description' => 'Operator void allowance each day.'
    ],
    [
        'label' => 'Inventory Sync',
        'value' => $autoAdjustInventory ? 'Enabled' : 'Disabled',
        'icon' => $autoAdjustInventory ? 'arrow-repeat' : 'slash-circle',
        'variant' => $autoAdjustInventory ? 'success' : 'secondary',
        'description' => $autoAdjustInventory ? 'Stock levels auto-adjust on void.' : 'Manual stock adjustments required.'
    ],
];

$policyAlerts = [];
if ($managerApprovalThreshold > 0 && $managerApprovalRate < 30) {
    $policyAlerts[] = 'Manager approvals are low relative to threshold; audit whether high-value voids are being escalated.';
}
if (!$autoAdjustInventory) {
    $policyAlerts[] = 'Inventory auto-adjustment is disabled. Ensure reconciliation tasks are tracked in operations logs.';
}
if ($notificationEmail) {
    $policyAlerts[] = 'Notification emails route to ' . htmlspecialchars($notificationEmail) . '. Verify distribution list quarterly.';
}

$pageUrlBase = 'void-reports.php';

$pageTitle = 'Void Reports';
include 'includes/header.php';
?>

<style>
.stat-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #2c3e50;
}
.stat-label {
    color: #7f8c8d;
    font-size: 0.9rem;
}
.chart-container {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Policy Overview & Export -->
<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Policy Snapshot</h5>
                    <small class="text-muted">Current enforcement levers impacting void activity</small>
                </div>
                <div class="btn-group">
                    <a href="<?= $pageUrlBase ?>?start_date=<?= urlencode(date('Y-m-01')) ?>&end_date=<?= urlencode(date('Y-m-t')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar3"></i> Month-to-date</a>
                    <a href="<?= $pageUrlBase ?>?start_date=<?= urlencode(date('Y-m-d', strtotime('-90 days'))) ?>&end_date=<?= urlencode(date('Y-m-d')) ?>" class="btn btn-outline-secondary btn-sm">Last 90 days</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($policyHighlights as $highlight): ?>
                        <div class="col-md-3 col-sm-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <span class="badge bg-<?= $highlight['variant'] ?>-subtle text-<?= $highlight['variant'] ?> rounded-circle p-2">
                                        <i class="bi bi-<?= htmlspecialchars($highlight['icon']) ?>"></i>
                                    </span>
                                    <div>
                                        <small class="text-muted text-uppercase"><?= htmlspecialchars($highlight['label']) ?></small>
                                        <h6 class="mb-0"><?= htmlspecialchars($highlight['value']) ?></h6>
                                    </div>
                                </div>
                                <p class="text-muted small mb-0"><?= htmlspecialchars($highlight['description']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Exports & Actions</h5>
            </div>
            <div class="card-body d-flex flex-column gap-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="mb-0">Download CSV</h6>
                        <small class="text-muted">Full detail for selected range</small>
                    </div>
                    <a href="api/export-void-data.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-download"></i> Export
                    </a>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="mb-0">Schedule Digest</h6>
                        <small class="text-muted">Send policy report to stakeholders</small>
                    </div>
                    <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleDigestModal">
                        <i class="bi bi-envelope"></i> Configure
                    </button>
                </div>
                <div class="border-top pt-3">
                    <h6>Policy Alerts</h6>
                    <?php if (empty($policyAlerts)): ?>
                        <p class="text-muted small mb-0">No policy concerns detected in this window.</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0 small text-warning">
                            <?php foreach ($policyAlerts as $alert): ?>
                                <li class="d-flex gap-2"><i class="bi bi-exclamation-triangle"></i><span><?= htmlspecialchars($alert) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Key Metrics Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-danger"><?= number_format($voidStats['total_voids']) ?></div>
            <div class="stat-label">Total Voids</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-warning"><?= formatCurrency($voidStats['total_amount'] ?? 0) ?></div>
            <div class="stat-label">Total Amount Voided</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-info"><?= number_format($voidStats['unique_users']) ?></div>
            <div class="stat-label">Users Who Voided</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-success"><?= number_format($voidStats['manager_approved']) ?></div>
            <div class="stat-label">Manager Approved</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-primary"><?= formatCurrency($averageVoidAmount) ?></div>
            <div class="stat-label">Average Void Amount</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-success"><?= $managerApprovalRate ?>%</div>
            <div class="stat-label">Approval Rate</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-secondary"><?= number_format($dailyTrendCounts) ?></div>
            <div class="stat-label">Voids in Range</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-warning"><?= formatCurrency($dailyTrendAmounts) ?></div>
            <div class="stat-label">Total Loss in Range</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Voids by Reason -->
    <div class="col-md-6">
        <div class="chart-container">
            <h5 class="mb-3">Voids by Reason</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Reason</th>
                            <th class="text-end">Count</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($voidsByReason as $reason): ?>
                        <tr>
                            <td><?= htmlspecialchars($reason['display_name'] ?? $reason['void_reason_code']) ?></td>
                            <td class="text-end"><?= number_format($reason['count']) ?></td>
                            <td class="text-end"><?= number_format($reason['total_amount'] ?? 0, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Voids by User -->
    <div class="col-md-6">
        <div class="chart-container">
            <h5 class="mb-3">Top Users (Voids)</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th class="text-end">Count</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($voidsByUser as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($user['role']) ?></span></td>
                            <td class="text-end"><?= number_format($user['count']) ?></td>
                            <td class="text-end"><?= number_format($user['total_amount'] ?? 0, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="chart-container">
            <h5 class="mb-3">Voids by Order Type</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Order Type</th>
                            <th class="text-end">Count</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($voidsByOrderType as $type): ?>
                        <tr>
                            <td><?= htmlspecialchars(ucfirst($type['order_type'] ?? 'Unknown')) ?></td>
                            <td class="text-end"><?= number_format($type['count']) ?></td>
                            <td class="text-end"><?= formatCurrency($type['total_amount'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($voidsByOrderType)): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">No voids for this period.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="chart-container">
            <h5 class="mb-3">Process Insights</h5>
            <ul class="list-unstyled mb-0">
                <li class="d-flex gap-3 align-items-start mb-3">
                    <span class="badge bg-primary-subtle text-primary rounded-pill"><i class="bi bi-activity"></i></span>
                    <div>
                        <strong>Approval rate</strong>
                        <p class="mb-0 text-muted small">Managers approved <?= $managerApprovalRate ?>% of voids in this window. Target 40%+ for thresholds set at <?= formatCurrency($managerApprovalThreshold) ?>.</p>
                    </div>
                </li>
                <li class="d-flex gap-3 align-items-start mb-3">
                    <span class="badge bg-info-subtle text-info rounded-pill"><i class="bi bi-stopwatch"></i></span>
                    <div>
                        <strong>Policy age checks</strong>
                        <p class="mb-0 text-muted small">Enforce the <?= $voidTimeLimit > 0 ? $voidTimeLimit . ' minute' : 'current' ?> time limit by validating order timestamps before approval.</p>
                    </div>
                </li>
                <li class="d-flex gap-3 align-items-start">
                    <span class="badge bg-warning-subtle text-warning rounded-pill"><i class="bi bi-archive"></i></span>
                    <div>
                        <strong>Inventory posture</strong>
                        <p class="mb-0 text-muted small">Inventory sync is <?= $autoAdjustInventory ? 'active; monitor for discrepancies via nightly stock counts.' : 'disabled; ensure manual reconciliations are logged.' ?></p>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- Daily Trend -->
<div class="row mb-4">
    <div class="col-12">
        <div class="chart-container">
            <h5 class="mb-3">Daily Void Trend</h5>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Voids</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dailyTrend as $day): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($day['date'])) ?></td>
                            <td class="text-end"><?= number_format($day['count']) ?></td>
                            <td class="text-end"><?= number_format($day['total_amount'] ?? 0, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Recent Void Transactions -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Void Transactions</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Order ID</th>
                                <th>Type</th>
                                <th>Reason</th>
                                <th>Amount</th>
                                <th>Voided By</th>
                                <th>Manager</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentVoids as $void): ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($void['void_timestamp'])) ?></td>
                                <td>#<?= $void['order_id'] ?></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($void['order_type']) ?></span></td>
                                <td><?= htmlspecialchars($void['reason_name'] ?? $void['void_reason_code']) ?></td>
                                <td><?= number_format($void['original_total'] ?? 0, 2) ?></td>
                                <td><?= htmlspecialchars($void['voided_by']) ?></td>
                                <td><?= $void['manager_name'] ? htmlspecialchars($void['manager_name']) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- High Value Voids -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">High Value Voids</h5>
                <small class="text-muted">Top transactions by amount for this window</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Reason</th>
                                <th>Voided By</th>
                                <th>Manager</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($highValueVoids as $void): ?>
                            <tr>
                                <td>#<?= $void['order_id'] ?></td>
                                <td><span class="badge bg-info-subtle text-info"><?= htmlspecialchars($void['order_type']) ?></span></td>
                                <td><?= formatCurrency($void['original_total']) ?></td>
                                <td><?= htmlspecialchars($void['reason_name'] ?? $void['void_reason_code']) ?></td>
                                <td><?= htmlspecialchars($void['voided_by']) ?></td>
                                <td><?= $void['manager_name'] ? htmlspecialchars($void['manager_name']) : '-' ?></td>
                                <td><?= date('M d, Y H:i', strtotime($void['void_timestamp'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($highValueVoids)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No high value voids in this range.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Digest Modal -->
<div class="modal fade" id="scheduleDigestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Void Digest</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Configure a weekly or monthly email summarizing void performance and policy adherence metrics.</p>
                <div class="mb-3">
                    <label class="form-label">Recipients</label>
                    <input type="text" class="form-control" placeholder="Comma separated email addresses" value="<?= htmlspecialchars($notificationEmail) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Frequency</label>
                    <select class="form-select">
                        <option value="weekly">Weekly (Mondays)</option>
                        <option value="monthly">Monthly (1st business day)</option>
                    </select>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="includeHighValue" checked>
                    <label class="form-check-label" for="includeHighValue">Include high-value void detail</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary"><i class="bi bi-send"></i> Save Schedule</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
