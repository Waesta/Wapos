<?php
/**
 * Bartender Performance Dashboard
 * 
 * Detailed metrics per bartender:
 * - Sales performance
 * - Pour accuracy
 * - Speed metrics
 * - Variance tracking
 * - Tips earned
 */

require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager', 'bartender']);

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$userRole = strtolower($auth->getRole() ?? '');
$isManager = in_array($userRole, ['admin', 'manager']);

// Date range
$startDate = $_GET['start'] ?? date('Y-m-01');
$endDate = $_GET['end'] ?? date('Y-m-d');

// Get bartenders
$bartenders = $db->fetchAll("
    SELECT id, username, full_name 
    FROM users 
    WHERE role IN ('bartender', 'waiter', 'cashier') AND is_active = 1
    ORDER BY full_name
");

// Selected bartender (managers can view all, bartenders see only their own)
$selectedBartender = $_GET['bartender'] ?? ($isManager ? 'all' : $userId);
if (!$isManager && $selectedBartender != $userId) {
    $selectedBartender = $userId;
}

// Build query conditions
$userCondition = $selectedBartender === 'all' ? '' : 'AND pl.user_id = ' . (int)$selectedBartender;

// Get pour statistics
$pourStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_pours,
        SUM(CASE WHEN pour_type = 'sale' THEN 1 ELSE 0 END) as sales_pours,
        SUM(CASE WHEN pour_type = 'wastage' THEN 1 ELSE 0 END) as wastage_pours,
        SUM(CASE WHEN pour_type = 'spillage' THEN 1 ELSE 0 END) as spillage_pours,
        SUM(CASE WHEN pour_type = 'comp' THEN 1 ELSE 0 END) as comp_pours,
        SUM(quantity_ml) as total_ml,
        SUM(CASE WHEN pour_type = 'sale' THEN quantity_ml ELSE 0 END) as sales_ml,
        SUM(CASE WHEN pour_type IN ('wastage', 'spillage') THEN quantity_ml ELSE 0 END) as waste_ml
    FROM bar_pour_log pl
    WHERE DATE(pl.created_at) BETWEEN ? AND ?
    $userCondition
", [$startDate, $endDate]);

// Get tab statistics
$tabStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_tabs,
        SUM(subtotal) as total_sales,
        SUM(tip_amount) as total_tips,
        AVG(total_amount) as avg_tab_value,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as completed_tabs,
        SUM(CASE WHEN status = 'voided' THEN 1 ELSE 0 END) as voided_tabs
    FROM bar_tabs
    WHERE DATE(opened_at) BETWEEN ? AND ?
    " . ($selectedBartender === 'all' ? '' : 'AND server_id = ' . (int)$selectedBartender), 
    [$startDate, $endDate]
);

// Get BOT performance (speed)
$botStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_bots,
        AVG(actual_time_minutes) as avg_prep_time,
        SUM(CASE WHEN status = 'ready' OR status = 'picked_up' THEN 1 ELSE 0 END) as completed_bots,
        SUM(CASE WHEN actual_time_minutes <= estimated_time_minutes THEN 1 ELSE 0 END) as on_time_bots
    FROM bar_order_tickets
    WHERE DATE(created_at) BETWEEN ? AND ?
    " . ($selectedBartender === 'all' ? '' : 'AND prepared_by = ' . (int)$selectedBartender),
    [$startDate, $endDate]
);

// Get hourly sales distribution
$hourlySales = $db->fetchAll("
    SELECT 
        HOUR(opened_at) as hour,
        COUNT(*) as tab_count,
        SUM(total_amount) as revenue
    FROM bar_tabs
    WHERE DATE(opened_at) BETWEEN ? AND ?
    " . ($selectedBartender === 'all' ? '' : 'AND server_id = ' . (int)$selectedBartender) . "
    GROUP BY HOUR(opened_at)
    ORDER BY hour
", [$startDate, $endDate]);

// Get top products sold
$topProducts = $db->fetchAll("
    SELECT 
        ti.item_name,
        SUM(ti.quantity) as qty_sold,
        SUM(ti.total_price) as revenue
    FROM bar_tab_items ti
    JOIN bar_tabs t ON ti.tab_id = t.id
    WHERE DATE(t.opened_at) BETWEEN ? AND ?
    AND ti.status != 'voided'
    " . ($selectedBartender === 'all' ? '' : 'AND t.server_id = ' . (int)$selectedBartender) . "
    GROUP BY ti.item_name
    ORDER BY qty_sold DESC
    LIMIT 10
", [$startDate, $endDate]);

// Get bartender leaderboard (managers only)
$leaderboard = [];
if ($isManager) {
    $leaderboard = $db->fetchAll("
        SELECT 
            u.id,
            u.full_name,
            COUNT(DISTINCT t.id) as tabs_served,
            COALESCE(SUM(t.total_amount), 0) as total_sales,
            COALESCE(SUM(t.tip_amount), 0) as total_tips,
            COALESCE(AVG(t.total_amount), 0) as avg_tab
        FROM users u
        LEFT JOIN bar_tabs t ON u.id = t.server_id AND DATE(t.opened_at) BETWEEN ? AND ?
        WHERE u.role IN ('bartender', 'waiter') AND u.is_active = 1
        GROUP BY u.id
        ORDER BY total_sales DESC
    ", [$startDate, $endDate]);
}

// Calculate metrics
$wastePercent = $pourStats['total_ml'] > 0 
    ? round(($pourStats['waste_ml'] / $pourStats['total_ml']) * 100, 2) 
    : 0;

$onTimePercent = $botStats['total_bots'] > 0 
    ? round(($botStats['on_time_bots'] / $botStats['total_bots']) * 100, 1) 
    : 0;

$pageTitle = 'Bartender Dashboard';
include 'includes/header.php';
?>

<style>
    .metric-card {
        border-radius: 1rem;
        padding: 1.5rem;
        height: 100%;
    }
    
    .metric-value {
        font-size: 2.5rem;
        font-weight: 700;
        line-height: 1;
    }
    
    .metric-label {
        font-size: 0.9rem;
        color: var(--bs-secondary);
        margin-top: 0.5rem;
    }
    
    .metric-change {
        font-size: 0.8rem;
        margin-top: 0.25rem;
    }
    
    .leaderboard-item {
        display: flex;
        align-items: center;
        padding: 0.75rem;
        border-bottom: 1px solid var(--bs-border-color);
    }
    
    .leaderboard-item:last-child {
        border-bottom: none;
    }
    
    .leaderboard-rank {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        margin-right: 1rem;
    }
    
    .leaderboard-rank.gold { background: #ffd700; color: #000; }
    .leaderboard-rank.silver { background: #c0c0c0; color: #000; }
    .leaderboard-rank.bronze { background: #cd7f32; color: #fff; }
    .leaderboard-rank.other { background: var(--bs-secondary-bg); }
    
    .progress-ring {
        width: 120px;
        height: 120px;
    }
    
    .chart-container {
        height: 250px;
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h4><i class="bi bi-speedometer2 me-2"></i>Bartender Performance</h4>
            <p class="text-muted mb-0">Track sales, speed, and accuracy metrics</p>
        </div>
        <div class="col-md-6">
            <form class="row g-2 justify-content-end">
                <?php if ($isManager): ?>
                <div class="col-auto">
                    <select name="bartender" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $selectedBartender === 'all' ? 'selected' : '' ?>>All Bartenders</option>
                        <?php foreach ($bartenders as $bt): ?>
                            <option value="<?= $bt['id'] ?>" <?= $selectedBartender == $bt['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($bt['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-auto">
                    <input type="date" name="start" class="form-control" value="<?= $startDate ?>">
                </div>
                <div class="col-auto">
                    <input type="date" name="end" class="form-control" value="<?= $endDate ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Key Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="metric-card bg-primary bg-opacity-10">
                <div class="metric-value text-primary"><?= formatCurrency($tabStats['total_sales'] ?? 0) ?></div>
                <div class="metric-label">Total Sales</div>
                <div class="metric-change text-success">
                    <i class="bi bi-graph-up"></i> <?= number_format($tabStats['total_tabs'] ?? 0) ?> tabs
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="metric-card bg-success bg-opacity-10">
                <div class="metric-value text-success"><?= formatCurrency($tabStats['total_tips'] ?? 0) ?></div>
                <div class="metric-label">Tips Earned</div>
                <div class="metric-change">
                    Avg: <?= formatCurrency(($tabStats['total_tabs'] ?? 0) > 0 ? ($tabStats['total_tips'] ?? 0) / $tabStats['total_tabs'] : 0) ?>/tab
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="metric-card bg-info bg-opacity-10">
                <div class="metric-value text-info"><?= number_format($botStats['avg_prep_time'] ?? 0, 1) ?>m</div>
                <div class="metric-label">Avg Prep Time</div>
                <div class="metric-change <?= $onTimePercent >= 80 ? 'text-success' : 'text-warning' ?>">
                    <i class="bi bi-clock"></i> <?= $onTimePercent ?>% on-time
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="metric-card bg-warning bg-opacity-10">
                <div class="metric-value text-warning"><?= $wastePercent ?>%</div>
                <div class="metric-label">Waste Rate</div>
                <div class="metric-change <?= $wastePercent <= 3 ? 'text-success' : 'text-danger' ?>">
                    Target: ≤3%
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Pour Breakdown -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-droplet me-2"></i>Pour Breakdown</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Sales Pours</span>
                            <strong><?= number_format($pourStats['sales_pours'] ?? 0) ?></strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: <?= ($pourStats['total_pours'] ?? 0) > 0 ? (($pourStats['sales_pours'] ?? 0) / $pourStats['total_pours'] * 100) : 0 ?>%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Wastage</span>
                            <strong><?= number_format($pourStats['wastage_pours'] ?? 0) ?></strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-danger" style="width: <?= ($pourStats['total_pours'] ?? 0) > 0 ? (($pourStats['wastage_pours'] ?? 0) / $pourStats['total_pours'] * 100) : 0 ?>%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Spillage</span>
                            <strong><?= number_format($pourStats['spillage_pours'] ?? 0) ?></strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-warning" style="width: <?= ($pourStats['total_pours'] ?? 0) > 0 ? (($pourStats['spillage_pours'] ?? 0) / $pourStats['total_pours'] * 100) : 0 ?>%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Comps</span>
                            <strong><?= number_format($pourStats['comp_pours'] ?? 0) ?></strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-info" style="width: <?= ($pourStats['total_pours'] ?? 0) > 0 ? (($pourStats['comp_pours'] ?? 0) / $pourStats['total_pours'] * 100) : 0 ?>%"></div>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <div class="fs-4 fw-bold"><?= number_format(($pourStats['total_ml'] ?? 0) / 1000, 1) ?>L</div>
                        <div class="text-muted">Total Volume Poured</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Products -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Top Sellers</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($topProducts as $i => $product): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-secondary me-2"><?= $i + 1 ?></span>
                                    <?= htmlspecialchars($product['item_name']) ?>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?= number_format($product['qty_sold']) ?></div>
                                    <small class="text-muted"><?= formatCurrency($product['revenue']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($topProducts)): ?>
                            <div class="list-group-item text-center text-muted">No sales data</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Hourly Distribution -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-clock me-2"></i>Peak Hours</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="hourlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($isManager && !empty($leaderboard)): ?>
    <!-- Leaderboard -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-award me-2"></i>Team Leaderboard</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Bartender</th>
                                    <th class="text-end">Tabs</th>
                                    <th class="text-end">Sales</th>
                                    <th class="text-end">Tips</th>
                                    <th class="text-end">Avg Tab</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaderboard as $i => $staff): ?>
                                    <tr>
                                        <td>
                                            <span class="leaderboard-rank <?= $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : 'other')) ?>">
                                                <?= $i + 1 ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($staff['full_name']) ?></strong>
                                        </td>
                                        <td class="text-end"><?= number_format($staff['tabs_served']) ?></td>
                                        <td class="text-end fw-bold text-success"><?= formatCurrency($staff['total_sales']) ?></td>
                                        <td class="text-end"><?= formatCurrency($staff['total_tips']) ?></td>
                                        <td class="text-end"><?= formatCurrency($staff['avg_tab']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Hourly chart
const hourlyData = <?= json_encode($hourlySales) ?>;
const hours = Array.from({length: 24}, (_, i) => i);
const hourLabels = hours.map(h => h.toString().padStart(2, '0') + ':00');

const hourlyValues = hours.map(h => {
    const found = hourlyData.find(d => d.hour == h);
    return found ? parseFloat(found.revenue) : 0;
});

new Chart(document.getElementById('hourlyChart'), {
    type: 'bar',
    data: {
        labels: hourLabels,
        datasets: [{
            label: 'Revenue',
            data: hourlyValues,
            backgroundColor: 'rgba(23, 162, 184, 0.7)',
            borderColor: 'rgba(23, 162, 184, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            x: {
                ticks: {
                    maxRotation: 45,
                    minRotation: 45,
                    callback: function(val, index) {
                        return index % 3 === 0 ? this.getLabelForValue(val) : '';
                    }
                }
            },
            y: {
                beginAtZero: true,
                ticks: {
                    callback: value => '<?= CURRENCY_SYMBOL ?>' + value.toLocaleString()
                }
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
