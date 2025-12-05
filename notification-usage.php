<?php
/**
 * Notification Usage & Billing Monitor
 * Track SMS, WhatsApp, and Email usage for billing purposes
 */

require_once 'includes/bootstrap.php';
$auth->requireLogin();

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['super_admin', 'developer'])) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();
$currencySymbol = CurrencyManager::getInstance()->getCurrencySymbol();

// Get filter parameters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // First of current month
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$channel = $_GET['channel'] ?? '';
$status = $_GET['status'] ?? '';

// Build filters
$filters = "WHERE DATE(nl.created_at) BETWEEN ? AND ?";
$params = [$dateFrom, $dateTo];

if ($channel) {
    $filters .= " AND nl.channel = ?";
    $params[] = $channel;
}
if ($status) {
    $filters .= " AND nl.status = ?";
    $params[] = $status;
}

// Get usage summary by channel
$usageSummary = $db->fetchAll("
    SELECT 
        channel,
        status,
        COUNT(*) as count
    FROM notification_logs nl
    $filters
    GROUP BY channel, status
    ORDER BY channel, status
", $params);

// Aggregate by channel
$channelStats = [];
foreach ($usageSummary as $row) {
    if (!isset($channelStats[$row['channel']])) {
        $channelStats[$row['channel']] = ['sent' => 0, 'failed' => 0, 'pending' => 0, 'total' => 0];
    }
    $channelStats[$row['channel']][$row['status']] = $row['count'];
    $channelStats[$row['channel']]['total'] += $row['count'];
}

// Get pricing from settings
$smsCost = (float)($db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'sms_cost_per_message'")['setting_value'] ?? 0.50);
$whatsappCost = (float)($db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'whatsapp_cost_per_message'")['setting_value'] ?? 0.30);
$emailCost = (float)($db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'email_cost_per_message'")['setting_value'] ?? 0.05);

// Calculate costs
$costs = [
    'sms' => ($channelStats['sms']['sent'] ?? 0) * $smsCost,
    'whatsapp' => ($channelStats['whatsapp']['sent'] ?? 0) * $whatsappCost,
    'email' => ($channelStats['email']['sent'] ?? 0) * $emailCost,
];
$totalCost = array_sum($costs);

// Daily usage trend
$dailyUsage = $db->fetchAll("
    SELECT 
        DATE(created_at) as date,
        channel,
        COUNT(*) as count,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM notification_logs nl
    $filters
    GROUP BY DATE(created_at), channel
    ORDER BY date DESC
", $params);

// Monthly summary (last 6 months)
$monthlySummary = $db->fetchAll("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        channel,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent
    FROM notification_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m'), channel
    ORDER BY month DESC, channel
");

// Top notification types
$topTypes = $db->fetchAll("
    SELECT 
        notification_type,
        channel,
        COUNT(*) as count
    FROM notification_logs nl
    $filters
    GROUP BY notification_type, channel
    ORDER BY count DESC
    LIMIT 10
", $params);

// Recent notifications
$recentLogs = $db->fetchAll("
    SELECT nl.*, c.name as customer_name
    FROM notification_logs nl
    LEFT JOIN customers c ON nl.customer_id = c.id
    $filters
    ORDER BY nl.created_at DESC
    LIMIT 50
", $params);

// Failed notifications for review
$failedNotifications = $db->fetchAll("
    SELECT nl.*, c.name as customer_name
    FROM notification_logs nl
    LEFT JOIN customers c ON nl.customer_id = c.id
    WHERE nl.status = 'failed'
    AND DATE(nl.created_at) BETWEEN ? AND ?
    ORDER BY nl.created_at DESC
    LIMIT 20
", [$dateFrom, $dateTo]);

$pageTitle = 'Notification Usage & Billing';
require_once 'includes/header.php';
?>

<style>
    .usage-card {
        border-left: 4px solid;
        transition: transform 0.2s;
    }
    .usage-card:hover { transform: translateY(-2px); }
    .usage-sms { border-left-color: #10b981; }
    .usage-whatsapp { border-left-color: #25d366; }
    .usage-email { border-left-color: #3b82f6; }
    .cost-badge {
        font-size: 1.25rem;
        font-weight: 700;
    }
    .billing-summary {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        color: white;
        border-radius: var(--radius-lg);
        padding: 1.5rem;
    }
    .stat-mini {
        text-align: center;
        padding: 0.5rem;
    }
    .stat-mini .value {
        font-size: 1.5rem;
        font-weight: 700;
    }
    .stat-mini .label {
        font-size: 0.75rem;
        opacity: 0.8;
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1">
                <i class="bi bi-bar-chart-line text-primary me-2"></i>Notification Usage & Billing
            </h1>
            <p class="text-muted mb-0">Monitor SMS, WhatsApp, and Email usage for client billing</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success" onclick="exportUsage()">
                <i class="bi bi-file-excel me-1"></i>Export Report
            </button>
            <a href="notification-pricing.php" class="btn btn-outline-primary">
                <i class="bi bi-currency-dollar me-1"></i>Set Pricing
            </a>
            <a href="notifications.php" class="btn btn-outline-secondary">
                <i class="bi bi-bell me-1"></i>Notifications
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="date_from" value="<?= $dateFrom ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="date_to" value="<?= $dateTo ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Channel</label>
                    <select class="form-select" name="channel">
                        <option value="">All Channels</option>
                        <option value="sms" <?= $channel === 'sms' ? 'selected' : '' ?>>SMS</option>
                        <option value="whatsapp" <?= $channel === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
                        <option value="email" <?= $channel === 'email' ? 'selected' : '' ?>>Email</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent</option>
                        <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary w-100">
                        This Month
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Billing Summary -->
    <div class="billing-summary mb-4">
        <div class="row align-items-center">
            <div class="col-md-4">
                <h5 class="mb-1"><i class="bi bi-receipt me-2"></i>Billing Summary</h5>
                <p class="mb-0 opacity-75"><?= date('M j', strtotime($dateFrom)) ?> - <?= date('M j, Y', strtotime($dateTo)) ?></p>
            </div>
            <div class="col-md-8">
                <div class="row">
                    <div class="col stat-mini">
                        <div class="value text-success"><?= number_format($channelStats['sms']['sent'] ?? 0) ?></div>
                        <div class="label">SMS Sent</div>
                        <div class="small"><?= $currencySymbol ?><?= number_format($costs['sms'], 2) ?></div>
                    </div>
                    <div class="col stat-mini">
                        <div class="value" style="color: #25d366"><?= number_format($channelStats['whatsapp']['sent'] ?? 0) ?></div>
                        <div class="label">WhatsApp Sent</div>
                        <div class="small"><?= $currencySymbol ?><?= number_format($costs['whatsapp'], 2) ?></div>
                    </div>
                    <div class="col stat-mini">
                        <div class="value text-info"><?= number_format($channelStats['email']['sent'] ?? 0) ?></div>
                        <div class="label">Emails Sent</div>
                        <div class="small"><?= $currencySymbol ?><?= number_format($costs['email'], 2) ?></div>
                    </div>
                    <div class="col stat-mini border-start">
                        <div class="value text-warning"><?= $currencySymbol ?><?= number_format($totalCost, 2) ?></div>
                        <div class="label">Total Billable</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Channel Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card usage-card usage-sms h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">SMS</h6>
                            <h3 class="mb-0"><?= number_format($channelStats['sms']['total'] ?? 0) ?></h3>
                            <small class="text-muted">total messages</small>
                        </div>
                        <i class="bi bi-chat-dots-fill text-success fs-2"></i>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col">
                            <div class="text-success fw-bold"><?= $channelStats['sms']['sent'] ?? 0 ?></div>
                            <small class="text-muted">Sent</small>
                        </div>
                        <div class="col">
                            <div class="text-danger fw-bold"><?= $channelStats['sms']['failed'] ?? 0 ?></div>
                            <small class="text-muted">Failed</small>
                        </div>
                        <div class="col">
                            <div class="text-warning fw-bold"><?= $channelStats['sms']['pending'] ?? 0 ?></div>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span>Cost per SMS:</span>
                        <strong><?= $currencySymbol ?><?= number_format($smsCost, 2) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Total Cost:</span>
                        <strong class="text-success"><?= $currencySymbol ?><?= number_format($costs['sms'], 2) ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card usage-card usage-whatsapp h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">WhatsApp</h6>
                            <h3 class="mb-0"><?= number_format($channelStats['whatsapp']['total'] ?? 0) ?></h3>
                            <small class="text-muted">total messages</small>
                        </div>
                        <i class="bi bi-whatsapp fs-2" style="color: #25d366"></i>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col">
                            <div class="text-success fw-bold"><?= $channelStats['whatsapp']['sent'] ?? 0 ?></div>
                            <small class="text-muted">Sent</small>
                        </div>
                        <div class="col">
                            <div class="text-danger fw-bold"><?= $channelStats['whatsapp']['failed'] ?? 0 ?></div>
                            <small class="text-muted">Failed</small>
                        </div>
                        <div class="col">
                            <div class="text-warning fw-bold"><?= $channelStats['whatsapp']['pending'] ?? 0 ?></div>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span>Cost per Message:</span>
                        <strong><?= $currencySymbol ?><?= number_format($whatsappCost, 2) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Total Cost:</span>
                        <strong style="color: #25d366"><?= $currencySymbol ?><?= number_format($costs['whatsapp'], 2) ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card usage-card usage-email h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">Email</h6>
                            <h3 class="mb-0"><?= number_format($channelStats['email']['total'] ?? 0) ?></h3>
                            <small class="text-muted">total emails</small>
                        </div>
                        <i class="bi bi-envelope-fill text-primary fs-2"></i>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col">
                            <div class="text-success fw-bold"><?= $channelStats['email']['sent'] ?? 0 ?></div>
                            <small class="text-muted">Sent</small>
                        </div>
                        <div class="col">
                            <div class="text-danger fw-bold"><?= $channelStats['email']['failed'] ?? 0 ?></div>
                            <small class="text-muted">Failed</small>
                        </div>
                        <div class="col">
                            <div class="text-warning fw-bold"><?= $channelStats['email']['pending'] ?? 0 ?></div>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span>Cost per Email:</span>
                        <strong><?= $currencySymbol ?><?= number_format($emailCost, 2) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Total Cost:</span>
                        <strong class="text-primary"><?= $currencySymbol ?><?= number_format($costs['email'], 2) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Monthly Trend -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Monthly Usage Trend</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Month</th>
                                    <th class="text-end">SMS</th>
                                    <th class="text-end">WhatsApp</th>
                                    <th class="text-end">Email</th>
                                    <th class="text-end">Est. Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $monthlyData = [];
                                foreach ($monthlySummary as $row) {
                                    if (!isset($monthlyData[$row['month']])) {
                                        $monthlyData[$row['month']] = ['sms' => 0, 'whatsapp' => 0, 'email' => 0];
                                    }
                                    $monthlyData[$row['month']][$row['channel']] = $row['sent'];
                                }
                                foreach ($monthlyData as $month => $data):
                                    $monthCost = ($data['sms'] * $smsCost) + ($data['whatsapp'] * $whatsappCost) + ($data['email'] * $emailCost);
                                ?>
                                <tr>
                                    <td><strong><?= date('M Y', strtotime($month . '-01')) ?></strong></td>
                                    <td class="text-end"><?= number_format($data['sms']) ?></td>
                                    <td class="text-end"><?= number_format($data['whatsapp']) ?></td>
                                    <td class="text-end"><?= number_format($data['email']) ?></td>
                                    <td class="text-end"><strong><?= $currencySymbol ?><?= number_format($monthCost, 2) ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage by Type -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Usage by Type</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($topTypes)): ?>
                        <p class="text-muted mb-0">No data available.</p>
                    <?php else: ?>
                        <?php foreach ($topTypes as $type): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <span class="badge bg-<?= $type['channel'] === 'sms' ? 'success' : ($type['channel'] === 'whatsapp' ? 'success' : 'primary') ?> me-2">
                                    <?= strtoupper($type['channel']) ?>
                                </span>
                                <?= ucfirst($type['notification_type']) ?>
                            </div>
                            <strong><?= number_format($type['count']) ?></strong>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Failed Notifications -->
    <?php if (!empty($failedNotifications)): ?>
    <div class="card mt-4">
        <div class="card-header bg-danger text-white">
            <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Failed Notifications (Review Required)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Channel</th>
                            <th>Recipient</th>
                            <th>Type</th>
                            <th>Error</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($failedNotifications as $notif): ?>
                        <tr>
                            <td><small><?= date('M j, g:i A', strtotime($notif['created_at'])) ?></small></td>
                            <td>
                                <span class="badge bg-<?= $notif['channel'] === 'sms' ? 'success' : ($notif['channel'] === 'whatsapp' ? 'success' : 'primary') ?>">
                                    <?= strtoupper($notif['channel']) ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($notif['customer_name'] ?? $notif['recipient']) ?>
                            </td>
                            <td><?= ucfirst($notif['notification_type']) ?></td>
                            <td><small class="text-danger"><?= htmlspecialchars(substr($notif['response'] ?? 'Unknown error', 0, 50)) ?>...</small></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="retryNotification(<?= $notif['id'] ?>)">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Activity -->
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Activity</h6>
            <span class="badge bg-secondary"><?= count($recentLogs) ?> records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Time</th>
                            <th>Channel</th>
                            <th>Recipient</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLogs as $log): 
                            $cost = match($log['channel']) {
                                'sms' => $log['status'] === 'sent' ? $smsCost : 0,
                                'whatsapp' => $log['status'] === 'sent' ? $whatsappCost : 0,
                                'email' => $log['status'] === 'sent' ? $emailCost : 0,
                                default => 0
                            };
                        ?>
                        <tr>
                            <td><small><?= date('M j, g:i A', strtotime($log['created_at'])) ?></small></td>
                            <td>
                                <?php
                                $icon = match($log['channel']) {
                                    'sms' => '<i class="bi bi-chat-dots text-success"></i>',
                                    'whatsapp' => '<i class="bi bi-whatsapp" style="color:#25d366"></i>',
                                    'email' => '<i class="bi bi-envelope text-primary"></i>',
                                    default => '<i class="bi bi-bell"></i>'
                                };
                                echo $icon;
                                ?>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($log['customer_name'] ?? $log['recipient']) ?></small>
                            </td>
                            <td><small><?= ucfirst($log['notification_type']) ?></small></td>
                            <td>
                                <span class="badge bg-<?= $log['status'] === 'sent' ? 'success' : ($log['status'] === 'failed' ? 'danger' : 'warning') ?>">
                                    <?= ucfirst($log['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($cost > 0): ?>
                                    <small class="text-success"><?= $currencySymbol ?><?= number_format($cost, 2) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function exportUsage() {
    const data = {
        period: '<?= $dateFrom ?> to <?= $dateTo ?>',
        sms: { sent: <?= $channelStats['sms']['sent'] ?? 0 ?>, cost: <?= $costs['sms'] ?> },
        whatsapp: { sent: <?= $channelStats['whatsapp']['sent'] ?? 0 ?>, cost: <?= $costs['whatsapp'] ?> },
        email: { sent: <?= $channelStats['email']['sent'] ?? 0 ?>, cost: <?= $costs['email'] ?> },
        total: <?= $totalCost ?>
    };
    
    let csv = 'Notification Usage Report\n';
    csv += 'Period,' + data.period + '\n\n';
    csv += 'Channel,Messages Sent,Cost\n';
    csv += 'SMS,' + data.sms.sent + ',' + data.sms.cost.toFixed(2) + '\n';
    csv += 'WhatsApp,' + data.whatsapp.sent + ',' + data.whatsapp.cost.toFixed(2) + '\n';
    csv += 'Email,' + data.email.sent + ',' + data.email.cost.toFixed(2) + '\n';
    csv += '\nTotal Billable,,' + data.total.toFixed(2) + '\n';
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'notification-usage-<?= $dateFrom ?>-to-<?= $dateTo ?>.csv';
    a.click();
}

function retryNotification(id) {
    if (!confirm('Retry sending this notification?')) return;
    
    fetch('api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'retry', notification_id: id, csrf_token: '<?= generateCSRFToken() ?>' })
    })
    .then(r => r.json())
    .then(result => {
        alert(result.message);
        if (result.success) location.reload();
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
