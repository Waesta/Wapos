<?php
require_once 'includes/bootstrap.php';

use App\Services\MaintenanceService;
use App\Services\HousekeepingService;

$auth->requireLogin();

$allowedRoles = ['super_admin', 'developer', 'admin', 'manager', 'accountant'];
$hasAccess = false;
foreach ($allowedRoles as $role) {
    if ($auth->hasRole($role)) {
        $hasAccess = true;
        break;
    }
}

if (!$hasAccess) {
    redirect('index.php?error=access_denied');
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$maintenanceService = new MaintenanceService($pdo);
$housekeepingService = new HousekeepingService($pdo);

$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('-6 days'));
$prevWeekStart = date('Y-m-d', strtotime('-13 days'));
$prevWeekEnd = date('Y-m-d', strtotime('-7 days'));

$salesCurrent = ['revenue' => 0, 'count' => 0];
$salesPrevious = ['revenue' => 0, 'count' => 0];
$dailySales = [];
$paymentMix = [];
$maintenanceSummary = [];
$housekeepingSummary = [];
$maintenanceHotlist = [];
$housekeepingDueToday = [];

try {
    $salesCurrent = $db->fetchOne(
        "SELECT SUM(total_amount) AS revenue, COUNT(*) AS count FROM sales WHERE DATE(created_at) BETWEEN ? AND ?",
        [$weekStart, $today]
    ) ?: $salesCurrent;

    $salesPrevious = $db->fetchOne(
        "SELECT SUM(total_amount) AS revenue, COUNT(*) AS count FROM sales WHERE DATE(created_at) BETWEEN ? AND ?",
        [$prevWeekStart, $prevWeekEnd]
    ) ?: $salesPrevious;

    $dailySalesRows = $db->fetchAll(
        "SELECT DATE(created_at) as sale_date, SUM(total_amount) as revenue
         FROM sales
         WHERE DATE(created_at) BETWEEN ? AND ?
         GROUP BY DATE(created_at)
         ORDER BY sale_date",
        [$weekStart, $today]
    ) ?: [];

    $dailySalesMap = [];
    foreach ($dailySalesRows as $row) {
        $dailySalesMap[$row['sale_date']] = (float) $row['revenue'];
    }

    $periodLabels = [];
    $periodValues = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $periodLabels[] = $date;
        $periodValues[] = $dailySalesMap[$date] ?? 0;
    }

    $dailySales = [
        'labels' => $periodLabels,
        'values' => $periodValues,
    ];

    $paymentMix = $db->fetchAll(
        "SELECT payment_method, SUM(total_amount) as total
         FROM sales
         WHERE DATE(created_at) BETWEEN ? AND ?
         GROUP BY payment_method
         ORDER BY total DESC",
        [$weekStart, $today]
    ) ?: [];
} catch (Throwable $e) {
    $paymentMix = [];
}

try {
    $maintenanceSummary = $maintenanceService->getSummary();
    $maintenanceHotlist = $pdo->fetchAll(
        "SELECT mr.tracking_code, mr.title, mr.priority, mr.status, rooms.room_number, mr.created_at
         FROM maintenance_requests mr
         LEFT JOIN rooms ON mr.room_id = rooms.id
         WHERE mr.status IN ('open','assigned','in_progress','on_hold')
         ORDER BY mr.priority = 'high' DESC, mr.due_date IS NULL, mr.due_date ASC, mr.created_at ASC
         LIMIT 5"
    ) ?: [];
} catch (Throwable $e) {
    $maintenanceSummary = [];
    $maintenanceHotlist = [];
}

try {
    $housekeepingSummary = $housekeepingService->getDashboardSummary();
    $housekeepingDueToday = $housekeepingService->getTasks([
        'scheduled_date' => $today,
        'status' => 'pending',
        'limit' => 5,
    ]);
} catch (Throwable $e) {
    $housekeepingSummary = [];
    $housekeepingDueToday = [];
}

$currentRevenue = (float) ($salesCurrent['revenue'] ?? 0);
$currentOrders = (int) ($salesCurrent['count'] ?? 0);
$averageDailyRevenue = count($dailySales['labels'] ?? []) > 0
    ? $currentRevenue / max(1, count($dailySales['labels']))
    : 0;
$previousRevenue = (float) ($salesPrevious['revenue'] ?? 0);
$revenueTrend = $previousRevenue > 0 ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100 : null;

$maintenanceOpen = ($maintenanceSummary['open'] ?? 0)
    + ($maintenanceSummary['assigned'] ?? 0)
    + ($maintenanceSummary['in_progress'] ?? 0)
    + ($maintenanceSummary['on_hold'] ?? 0);
$maintenanceResolved = ($maintenanceSummary['resolved'] ?? 0) + ($maintenanceSummary['closed'] ?? 0);
$housekeepingBacklog = ($housekeepingSummary['pending'] ?? 0) + ($housekeepingSummary['in_progress'] ?? 0);
$housekeepingCompletedToday = $housekeepingSummary['completed'] ?? 0;

$pageTitle = 'Executive Dashboard';
include 'includes/header.php';
?>

<div class="section-heading mt-2 mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-graph-up-arrow me-2"></i>Executive Command Center</h4>
        <p class="text-muted mb-0">Consolidated KPIs for sales, maintenance, and housekeeping</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Revenue (Last 7 Days)</p>
                <h3 class="fw-bold mb-1"><?= formatMoney($currentRevenue) ?></h3>
                <span class="badge bg-light text-dark"><?= $weekStart ?> → <?= $today ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Avg Daily Revenue</p>
                <h3 class="fw-bold mb-1"><?= formatMoney($averageDailyRevenue) ?></h3>
                <p class="text-muted small mb-0">Across <?= count($dailySales['labels']) ?> days</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Trend vs Prior Week</p>
                <?php if ($revenueTrend !== null): ?>
                    <?php $trendPositive = $revenueTrend >= 0; ?>
                    <h3 class="fw-bold mb-1 <?= $trendPositive ? 'text-success' : 'text-danger' ?>">
                        <?= ($trendPositive ? '+' : '') . number_format($revenueTrend, 1) ?>%
                    </h3>
                    <span class="badge bg-<?= $trendPositive ? 'success' : 'danger' ?>">
                        <?= $trendPositive ? 'Growth' : 'Decline' ?> vs <?= formatMoney($previousRevenue) ?>
                    </span>
                <?php else: ?>
                    <h3 class="fw-bold mb-1">—</h3>
                    <span class="badge bg-secondary">Not enough data</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Orders Logged</p>
                <h3 class="fw-bold mb-1"><?= number_format($currentOrders) ?></h3>
                <p class="text-muted small mb-0">Avg <?= number_format(max(0, $currentOrders / max(1, count($dailySales['labels']))), 1) ?>/day</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Maintenance Load</p>
                <h4 class="fw-bold mb-1"><?= $maintenanceOpen ?> open</h4>
                <p class="text-muted small mb-2"><?= $maintenanceResolved ?> resolved in lifecycle</p>
                <div class="progress" style="height: 8px;">
                    <?php
                    $totalMaint = $maintenanceOpen + $maintenanceResolved;
                    $resolvedPct = $totalMaint > 0 ? ($maintenanceResolved / $totalMaint) * 100 : 0;
                    ?>
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $resolvedPct ?>%" aria-valuenow="<?= $resolvedPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Housekeeping Backlog</p>
                <h4 class="fw-bold mb-1"><?= $housekeepingBacklog ?> tasks in queue</h4>
                <p class="text-muted small mb-0"><?= $housekeepingCompletedToday ?> completed today</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="text-muted small mb-1">Guest Experience Alerts</p>
                <h4 class="fw-bold mb-1">High Priority: <?= $maintenanceSummary['high_priority'] ?? 0 ?></h4>
                <p class="text-muted small mb-0">Overdue tickets: <?= $maintenanceSummary['overdue'] ?? 0 ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-activity me-2"></i>Sales Performance (7 days)</h6>
                <span class="badge bg-light text-muted">Live</span>
            </div>
            <div class="card-body">
                <canvas id="executiveSalesChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-credit-card me-2"></i>Payment Mix</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($paymentMix)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th class="text-end">Share</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $totalMix = array_sum(array_column($paymentMix, 'total'));
                                foreach ($paymentMix as $method):
                                    $share = $totalMix > 0 ? ($method['total'] / $totalMix) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><?= ucfirst(str_replace('_', ' ', $method['payment_method'])) ?></td>
                                        <td class="text-end">
                                            <span class="badge bg-primary-subtle text-primary fw-semibold">
                                                <?= number_format($share, 1) ?>%
                                            </span>
                                        </td>
                                        <td class="text-end fw-semibold"><?= formatMoney($method['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No sales recorded for the selected window.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-wrench-adjustable me-2"></i>Maintenance Watchlist</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($maintenanceHotlist)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($maintenanceHotlist as $ticket): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?= htmlspecialchars($ticket['title']) ?></strong>
                                        <p class="text-muted small mb-1">
                                            <?= htmlspecialchars($ticket['room_number'] ? 'Room ' . $ticket['room_number'] : 'Common Area') ?> ·
                                            <?= htmlspecialchars(strtoupper($ticket['tracking_code'])) ?>
                                        </p>
                                    </div>
                                    <span class="badge <?= $ticket['priority'] === 'high' ? 'bg-danger' : 'bg-secondary' ?>">
                                        <?= ucfirst($ticket['priority']) ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span class="text-capitalize text-muted">Status: <?= str_replace('_', ' ', $ticket['status']) ?></span>
                                    <span class="text-muted">Opened <?= date('M j, g:ia', strtotime($ticket['created_at'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No open maintenance alerts.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-broom me-2"></i>Housekeeping Today</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($housekeepingDueToday)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($housekeepingDueToday as $task): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?= htmlspecialchars($task['title'] ?? 'Task #' . $task['id']) ?></strong>
                                        <p class="text-muted small mb-1">
                                            <?= htmlspecialchars($task['room_number'] ?? 'Common Area') ?> · Due <?= date('g:ia', strtotime($task['due_at'] ?? $task['scheduled_date'] ?? $today)) ?>
                                        </p>
                                    </div>
                                    <span class="badge bg-<?= ($task['priority'] ?? 'normal') === 'high' ? 'danger' : 'info' ?>">
                                        <?= ucfirst($task['priority'] ?? 'normal') ?>
                                    </span>
                                </div>
                                <div class="text-muted small">
                                    <?= htmlspecialchars($task['notes'] ?? 'No notes provided.') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No pending housekeeping tasks for today.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const chartEl = document.getElementById('executiveSalesChart');
if (chartEl) {
    const salesChart = new Chart(chartEl, {
        type: 'line',
        data: {
            labels: <?= json_encode($dailySales['labels']) ?>,
            datasets: [{
                label: 'Revenue (<?= getCurrencyCode() ?>)',
                data: <?= json_encode($dailySales['values']) ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.12)',
                tension: 0.35,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat(undefined, { style: 'currency', currency: <?= json_encode(getCurrencyCode()) ?> }).format(value);
                        }
                    }
                }
            }
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
