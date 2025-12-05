<?php
/**
 * Register Performance Analytics
 * Analyze individual register performance, cashier efficiency, and trends
 */

require_once 'includes/bootstrap.php';
$auth->requireLogin();

// Check permissions
$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'manager', 'super_admin', 'developer'])) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();
$currencySymbol = CurrencyManager::getInstance()->getCurrencySymbol();

// Get filter parameters
$registerId = isset($_GET['register_id']) ? (int)$_GET['register_id'] : 0;
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Get all registers for filter
$registers = $db->fetchAll("
    SELECT r.*, l.name as location_name 
    FROM registers r 
    JOIN locations l ON r.location_id = l.id 
    ORDER BY l.name, r.name
");

// Get selected register details
$selectedRegister = null;
if ($registerId) {
    $selectedRegister = $db->fetchOne("
        SELECT r.*, l.name as location_name 
        FROM registers r 
        JOIN locations l ON r.location_id = l.id 
        WHERE r.id = ?
    ", [$registerId]);
}

// Build register filter for queries
$registerFilter = $registerId ? "AND s.register_id = ?" : "";
$registerParams = $registerId ? [$registerId] : [];

// Overall performance metrics
$overallStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(AVG(total_amount), 0) as avg_ticket,
        COUNT(DISTINCT DATE(created_at)) as active_days,
        COUNT(DISTINCT user_id) as unique_cashiers
    FROM sales s
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    $registerFilter
", array_merge([$dateFrom, $dateTo], $registerParams)) ?: [];

// Daily sales trend
$dailyTrend = $db->fetchAll("
    SELECT 
        DATE(s.created_at) as sale_date,
        COUNT(*) as transactions,
        SUM(total_amount) as revenue
    FROM sales s
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    $registerFilter
    GROUP BY DATE(s.created_at)
    ORDER BY sale_date
", array_merge([$dateFrom, $dateTo], $registerParams)) ?: [];

// Hourly distribution
$hourlyDistribution = $db->fetchAll("
    SELECT 
        HOUR(s.created_at) as hour,
        COUNT(*) as transactions,
        SUM(total_amount) as revenue
    FROM sales s
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    $registerFilter
    GROUP BY HOUR(s.created_at)
    ORDER BY hour
", array_merge([$dateFrom, $dateTo], $registerParams)) ?: [];

// Payment method breakdown
$paymentBreakdown = $db->fetchAll("
    SELECT 
        payment_method,
        COUNT(*) as count,
        SUM(total_amount) as total
    FROM sales s
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    $registerFilter
    GROUP BY payment_method
    ORDER BY total DESC
", array_merge([$dateFrom, $dateTo], $registerParams)) ?: [];

// Cashier performance (for this register)
$cashierPerformance = $db->fetchAll("
    SELECT 
        u.id as user_id,
        u.full_name,
        COUNT(s.id) as transactions,
        SUM(s.total_amount) as revenue,
        AVG(s.total_amount) as avg_ticket,
        MIN(s.created_at) as first_sale,
        MAX(s.created_at) as last_sale
    FROM sales s
    JOIN users u ON s.user_id = u.id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    $registerFilter
    GROUP BY u.id
    ORDER BY revenue DESC
", array_merge([$dateFrom, $dateTo], $registerParams)) ?: [];

// Register comparison (all registers)
$registerComparison = $db->fetchAll("
    SELECT 
        r.id,
        r.name as register_name,
        r.register_number,
        l.name as location_name,
        COUNT(s.id) as transactions,
        COALESCE(SUM(s.total_amount), 0) as revenue,
        COALESCE(AVG(s.total_amount), 0) as avg_ticket
    FROM registers r
    JOIN locations l ON r.location_id = l.id
    LEFT JOIN sales s ON s.register_id = r.id AND DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY r.id
    ORDER BY revenue DESC
", [$dateFrom, $dateTo]) ?: [];

// Session history for selected register
$sessionHistory = [];
if ($registerId) {
    $sessionHistory = $db->fetchAll("
        SELECT 
            rs.*,
            u.full_name as cashier_name,
            (SELECT COUNT(*) FROM sales WHERE session_id = rs.id) as sale_count,
            (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE session_id = rs.id) as session_revenue
        FROM register_sessions rs
        JOIN users u ON rs.user_id = u.id
        WHERE rs.register_id = ?
        AND DATE(rs.opened_at) BETWEEN ? AND ?
        ORDER BY rs.opened_at DESC
        LIMIT 20
    ", [$registerId, $dateFrom, $dateTo]) ?: [];
}

// Variance analysis
$varianceStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_sessions,
        SUM(CASE WHEN variance > 0 THEN 1 ELSE 0 END) as over_count,
        SUM(CASE WHEN variance < 0 THEN 1 ELSE 0 END) as short_count,
        SUM(CASE WHEN variance = 0 THEN 1 ELSE 0 END) as exact_count,
        COALESCE(SUM(variance), 0) as total_variance,
        COALESCE(AVG(ABS(variance)), 0) as avg_variance
    FROM register_sessions rs
    WHERE rs.status = 'closed'
    AND DATE(rs.opened_at) BETWEEN ? AND ?
    " . ($registerId ? "AND rs.register_id = ?" : ""),
    $registerId ? [$dateFrom, $dateTo, $registerId] : [$dateFrom, $dateTo]
) ?: [];

$pageTitle = 'Register Analytics';
require_once 'includes/header.php';
?>

<style>
    .analytics-grid {
        display: grid;
        gap: var(--spacing-lg);
    }
    @media (min-width: 1200px) {
        .analytics-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    .metric-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--spacing-md);
    }
    .metric-value {
        font-size: 1.75rem;
        font-weight: 700;
    }
    .metric-label {
        color: var(--color-text-muted);
        font-size: 0.875rem;
    }
    .chart-container {
        height: 250px;
        position: relative;
    }
    .progress-bar-custom {
        height: 8px;
        border-radius: 4px;
        background: var(--color-border);
        overflow: hidden;
    }
    .progress-bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.3s ease;
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1">
                <i class="bi bi-graph-up-arrow text-primary me-2"></i>Register Analytics
            </h1>
            <p class="text-muted mb-0">
                <?php if ($selectedRegister): ?>
                    Analyzing: <strong><?= htmlspecialchars($selectedRegister['name']) ?></strong> 
                    (<?= htmlspecialchars($selectedRegister['location_name']) ?>)
                <?php else: ?>
                    All registers performance overview
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="location-analytics.php" class="btn btn-outline-info">
                <i class="bi bi-geo-alt me-1"></i>Location Analytics
            </a>
            <a href="registers.php" class="btn btn-outline-secondary">
                <i class="bi bi-cash-stack me-1"></i>Manage Registers
            </a>
            <a href="register-reports.php" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-1"></i>X/Y/Z Reports
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Register</label>
                    <select class="form-select" name="register_id">
                        <option value="0">All Registers</option>
                        <?php foreach ($registers as $reg): ?>
                            <option value="<?= $reg['id'] ?>" <?= $registerId == $reg['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($reg['name']) ?> (<?= htmlspecialchars($reg['location_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="date_from" value="<?= $dateFrom ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="date_to" value="<?= $dateTo ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i>Apply Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="metric-card text-center">
                <div class="metric-value text-primary"><?= number_format($overallStats['total_transactions'] ?? 0) ?></div>
                <div class="metric-label">Transactions</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="metric-card text-center">
                <div class="metric-value text-success"><?= $currencySymbol ?><?= number_format($overallStats['total_revenue'] ?? 0, 0) ?></div>
                <div class="metric-label">Total Revenue</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="metric-card text-center">
                <div class="metric-value text-info"><?= $currencySymbol ?><?= number_format($overallStats['avg_ticket'] ?? 0, 0) ?></div>
                <div class="metric-label">Avg Ticket</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="metric-card text-center">
                <div class="metric-value"><?= $overallStats['active_days'] ?? 0 ?></div>
                <div class="metric-label">Active Days</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="metric-card text-center">
                <div class="metric-value"><?= $overallStats['unique_cashiers'] ?? 0 ?></div>
                <div class="metric-label">Cashiers</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="metric-card text-center">
                <?php 
                $avgPerDay = ($overallStats['active_days'] ?? 0) > 0 
                    ? ($overallStats['total_revenue'] ?? 0) / $overallStats['active_days'] 
                    : 0;
                ?>
                <div class="metric-value text-warning"><?= $currencySymbol ?><?= number_format($avgPerDay, 0) ?></div>
                <div class="metric-label">Avg/Day</div>
            </div>
        </div>
    </div>

    <div class="analytics-grid mb-4">
        <!-- Register Comparison -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Register Comparison</h6>
            </div>
            <div class="card-body">
                <?php if (empty($registerComparison)): ?>
                    <p class="text-muted mb-0">No register data available.</p>
                <?php else: ?>
                    <?php 
                    $maxRevenue = max(array_column($registerComparison, 'revenue')) ?: 1;
                    ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Register</th>
                                    <th class="text-end">Transactions</th>
                                    <th class="text-end">Revenue</th>
                                    <th style="width: 30%">Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registerComparison as $reg): ?>
                                <tr class="<?= $reg['id'] == $registerId ? 'table-primary' : '' ?>">
                                    <td>
                                        <a href="?register_id=<?= $reg['id'] ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>">
                                            <?= htmlspecialchars($reg['register_name']) ?>
                                        </a>
                                        <small class="text-muted d-block"><?= htmlspecialchars($reg['location_name']) ?></small>
                                    </td>
                                    <td class="text-end"><?= number_format($reg['transactions']) ?></td>
                                    <td class="text-end"><?= $currencySymbol ?><?= number_format($reg['revenue'], 0) ?></td>
                                    <td>
                                        <div class="progress-bar-custom">
                                            <div class="progress-bar-fill bg-primary" style="width: <?= ($reg['revenue'] / $maxRevenue) * 100 ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-credit-card me-2"></i>Payment Methods</h6>
            </div>
            <div class="card-body">
                <?php if (empty($paymentBreakdown)): ?>
                    <p class="text-muted mb-0">No payment data available.</p>
                <?php else: ?>
                    <?php 
                    $totalPayments = array_sum(array_column($paymentBreakdown, 'total')) ?: 1;
                    $colors = ['cash' => 'success', 'card' => 'primary', 'mobile_money' => 'warning', 'mpesa' => 'info'];
                    ?>
                    <?php foreach ($paymentBreakdown as $payment): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span><?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?></span>
                            <span>
                                <strong><?= $currencySymbol ?><?= number_format($payment['total'], 0) ?></strong>
                                <small class="text-muted">(<?= number_format(($payment['total'] / $totalPayments) * 100, 1) ?>%)</small>
                            </span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-bar-fill bg-<?= $colors[$payment['payment_method']] ?? 'secondary' ?>" 
                                 style="width: <?= ($payment['total'] / $totalPayments) * 100 ?>%"></div>
                        </div>
                        <small class="text-muted"><?= number_format($payment['count']) ?> transactions</small>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cashier Performance -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-people me-2"></i>Cashier Performance</h6>
            </div>
            <div class="card-body">
                <?php if (empty($cashierPerformance)): ?>
                    <p class="text-muted mb-0">No cashier data available.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Cashier</th>
                                    <th class="text-end">Sales</th>
                                    <th class="text-end">Revenue</th>
                                    <th class="text-end">Avg Ticket</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cashierPerformance as $cashier): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cashier['full_name']) ?></td>
                                    <td class="text-end"><?= number_format($cashier['transactions']) ?></td>
                                    <td class="text-end"><?= $currencySymbol ?><?= number_format($cashier['revenue'], 0) ?></td>
                                    <td class="text-end"><?= $currencySymbol ?><?= number_format($cashier['avg_ticket'], 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Variance Analysis -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-calculator me-2"></i>Cash Variance Analysis</h6>
            </div>
            <div class="card-body">
                <?php if (($varianceStats['total_sessions'] ?? 0) == 0): ?>
                    <p class="text-muted mb-0">No closed sessions to analyze.</p>
                <?php else: ?>
                    <div class="row g-3 text-center mb-3">
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="h5 mb-0 text-success"><?= $varianceStats['exact_count'] ?? 0 ?></div>
                                <small class="text-muted">Exact</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="h5 mb-0 text-primary"><?= $varianceStats['over_count'] ?? 0 ?></div>
                                <small class="text-muted">Over</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <div class="h5 mb-0 text-danger"><?= $varianceStats['short_count'] ?? 0 ?></div>
                                <small class="text-muted">Short</small>
                            </div>
                        </div>
                    </div>
                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Variance:</span>
                            <strong class="<?= ($varianceStats['total_variance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $currencySymbol ?><?= number_format($varianceStats['total_variance'] ?? 0, 2) ?>
                            </strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Avg Variance (abs):</span>
                            <strong><?= $currencySymbol ?><?= number_format($varianceStats['avg_variance'] ?? 0, 2) ?></strong>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Hourly Distribution -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-clock me-2"></i>Sales by Hour</h6>
        </div>
        <div class="card-body">
            <?php if (empty($hourlyDistribution)): ?>
                <p class="text-muted mb-0">No hourly data available.</p>
            <?php else: ?>
                <?php 
                $maxHourly = max(array_column($hourlyDistribution, 'revenue')) ?: 1;
                $hourlyMap = array_column($hourlyDistribution, null, 'hour');
                ?>
                <div class="d-flex align-items-end justify-content-between" style="height: 150px;">
                    <?php for ($h = 6; $h <= 23; $h++): ?>
                        <?php 
                        $hourData = $hourlyMap[$h] ?? ['revenue' => 0, 'transactions' => 0];
                        $height = ($hourData['revenue'] / $maxHourly) * 100;
                        ?>
                        <div class="text-center flex-fill px-1" title="<?= $h ?>:00 - <?= $currencySymbol ?><?= number_format($hourData['revenue'], 0) ?> (<?= $hourData['transactions'] ?> sales)">
                            <div class="bg-primary rounded-top mx-auto" style="width: 80%; height: <?= max($height, 2) ?>px;"></div>
                            <small class="text-muted"><?= $h ?></small>
                        </div>
                    <?php endfor; ?>
                </div>
                <div class="text-center mt-2">
                    <small class="text-muted">Hour of Day (6 AM - 11 PM)</small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($registerId && !empty($sessionHistory)): ?>
    <!-- Session History -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-list-check me-2"></i>Recent Sessions</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Session</th>
                            <th>Cashier</th>
                            <th>Opened</th>
                            <th>Closed</th>
                            <th class="text-end">Sales</th>
                            <th class="text-end">Revenue</th>
                            <th class="text-end">Variance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessionHistory as $session): ?>
                        <tr>
                            <td>
                                <span class="badge bg-<?= $session['status'] === 'open' ? 'success' : 'secondary' ?>">
                                    <?= htmlspecialchars($session['session_number']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($session['cashier_name']) ?></td>
                            <td><?= date('M j, g:i A', strtotime($session['opened_at'])) ?></td>
                            <td><?= $session['closed_at'] ? date('M j, g:i A', strtotime($session['closed_at'])) : '—' ?></td>
                            <td class="text-end"><?= number_format($session['sale_count']) ?></td>
                            <td class="text-end"><?= $currencySymbol ?><?= number_format($session['session_revenue'], 0) ?></td>
                            <td class="text-end">
                                <?php if ($session['variance'] !== null): ?>
                                    <span class="<?= $session['variance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $session['variance'] >= 0 ? '+' : '' ?><?= $currencySymbol ?><?= number_format($session['variance'], 2) ?>
                                    </span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
