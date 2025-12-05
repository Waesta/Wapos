<?php
/**
 * Location Performance Analytics
 * Compare performance across different business locations/branches
 */

require_once 'includes/bootstrap.php';
$auth->requireLogin();

// Check permissions
$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'manager', 'super_admin', 'developer', 'owner'])) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();
$currencySymbol = CurrencyManager::getInstance()->getCurrencySymbol();

// Get filter parameters
$locationId = isset($_GET['location_id']) ? (int)$_GET['location_id'] : 0;
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$compareMode = $_GET['compare'] ?? 'revenue'; // revenue, transactions, avg_ticket

// Get all locations
$locations = $db->fetchAll("SELECT * FROM locations WHERE is_active = 1 ORDER BY name");

// Get selected location details
$selectedLocation = null;
if ($locationId) {
    $selectedLocation = $db->fetchOne("SELECT * FROM locations WHERE id = ?", [$locationId]);
}

// Build location filter
$locationFilter = $locationId ? "AND s.location_id = ?" : "";
$locationParams = $locationId ? [$locationId] : [];

// Overall metrics across all/selected locations
$overallStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(AVG(total_amount), 0) as avg_ticket,
        COUNT(DISTINCT DATE(created_at)) as active_days,
        COUNT(DISTINCT user_id) as unique_staff,
        COUNT(DISTINCT location_id) as locations_count
    FROM sales s
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    $locationFilter
", array_merge([$dateFrom, $dateTo], $locationParams)) ?: [];

// Location comparison
$locationComparison = $db->fetchAll("
    SELECT 
        l.id,
        l.name as location_name,
        l.address,
        COUNT(s.id) as transactions,
        COALESCE(SUM(s.total_amount), 0) as revenue,
        COALESCE(AVG(s.total_amount), 0) as avg_ticket,
        COUNT(DISTINCT s.user_id) as staff_count,
        COUNT(DISTINCT DATE(s.created_at)) as active_days,
        MIN(s.created_at) as first_sale,
        MAX(s.created_at) as last_sale
    FROM locations l
    LEFT JOIN sales s ON s.location_id = l.id AND DATE(s.created_at) BETWEEN ? AND ?
    WHERE l.is_active = 1
    GROUP BY l.id
    ORDER BY revenue DESC
", [$dateFrom, $dateTo]) ?: [];

// Daily trend by location
$dailyByLocation = $db->fetchAll("
    SELECT 
        DATE(s.created_at) as sale_date,
        l.name as location_name,
        l.id as location_id,
        COUNT(*) as transactions,
        SUM(s.total_amount) as revenue
    FROM sales s
    JOIN locations l ON s.location_id = l.id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    $locationFilter
    GROUP BY DATE(s.created_at), l.id
    ORDER BY sale_date, l.name
", array_merge([$dateFrom, $dateTo], $locationParams)) ?: [];

// Payment methods by location
$paymentByLocation = $db->fetchAll("
    SELECT 
        l.name as location_name,
        s.payment_method,
        COUNT(*) as count,
        SUM(s.total_amount) as total
    FROM sales s
    JOIN locations l ON s.location_id = l.id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    $locationFilter
    GROUP BY l.id, s.payment_method
    ORDER BY l.name, total DESC
", array_merge([$dateFrom, $dateTo], $locationParams)) ?: [];

// Top products by location
$topProductsByLocation = $db->fetchAll("
    SELECT 
        l.name as location_name,
        COALESCE(p.name, si.product_name) as product_name,
        SUM(si.quantity) as qty_sold,
        SUM(si.total_price) as revenue
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN locations l ON s.location_id = l.id
    LEFT JOIN products p ON si.product_id = p.id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    $locationFilter
    GROUP BY l.id, COALESCE(p.name, si.product_name)
    ORDER BY l.name, revenue DESC
", array_merge([$dateFrom, $dateTo], $locationParams)) ?: [];

// Staff performance by location
$staffByLocation = $db->fetchAll("
    SELECT 
        l.name as location_name,
        l.id as location_id,
        u.full_name,
        COUNT(s.id) as transactions,
        SUM(s.total_amount) as revenue,
        AVG(s.total_amount) as avg_ticket
    FROM sales s
    JOIN locations l ON s.location_id = l.id
    JOIN users u ON s.user_id = u.id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    $locationFilter
    GROUP BY l.id, u.id
    ORDER BY l.name, revenue DESC
", array_merge([$dateFrom, $dateTo], $locationParams)) ?: [];

// Hourly comparison
$hourlyByLocation = $db->fetchAll("
    SELECT 
        l.name as location_name,
        HOUR(s.created_at) as hour,
        COUNT(*) as transactions,
        SUM(s.total_amount) as revenue
    FROM sales s
    JOIN locations l ON s.location_id = l.id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    $locationFilter
    GROUP BY l.id, HOUR(s.created_at)
    ORDER BY l.name, hour
", array_merge([$dateFrom, $dateTo], $locationParams)) ?: [];

// Register performance by location
$registersByLocation = $db->fetchAll("
    SELECT 
        l.name as location_name,
        r.name as register_name,
        r.register_number,
        COUNT(s.id) as transactions,
        COALESCE(SUM(s.total_amount), 0) as revenue
    FROM registers r
    JOIN locations l ON r.location_id = l.id
    LEFT JOIN sales s ON s.register_id = r.id AND DATE(s.created_at) BETWEEN ? AND ?
    WHERE l.is_active = 1
    $locationFilter
    GROUP BY r.id
    ORDER BY l.name, revenue DESC
", array_merge([$dateFrom, $dateTo], $locationParams)) ?: [];

// Month-over-month growth
$monthlyGrowth = $db->fetchAll("
    SELECT 
        l.name as location_name,
        DATE_FORMAT(s.created_at, '%Y-%m') as month,
        COUNT(*) as transactions,
        SUM(s.total_amount) as revenue
    FROM sales s
    JOIN locations l ON s.location_id = l.id
    WHERE s.created_at >= DATE_SUB(?, INTERVAL 6 MONTH)
    $locationFilter
    GROUP BY l.id, DATE_FORMAT(s.created_at, '%Y-%m')
    ORDER BY l.name, month
", array_merge([$dateTo], $locationParams)) ?: [];

// Group data for easier display
$paymentsByLoc = [];
foreach ($paymentByLocation as $row) {
    $paymentsByLoc[$row['location_name']][] = $row;
}

$productsByLoc = [];
foreach ($topProductsByLocation as $row) {
    if (!isset($productsByLoc[$row['location_name']]) || count($productsByLoc[$row['location_name']]) < 5) {
        $productsByLoc[$row['location_name']][] = $row;
    }
}

$staffByLoc = [];
foreach ($staffByLocation as $row) {
    $staffByLoc[$row['location_name']][] = $row;
}

$pageTitle = 'Location Analytics';
require_once 'includes/header.php';
?>

<style>
    .location-grid {
        display: grid;
        gap: var(--spacing-lg);
    }
    @media (min-width: 1200px) {
        .location-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .location-grid-3 {
            grid-template-columns: repeat(3, 1fr);
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
    .location-rank {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.875rem;
    }
    .rank-1 { background: #ffd700; color: #000; }
    .rank-2 { background: #c0c0c0; color: #000; }
    .rank-3 { background: #cd7f32; color: #fff; }
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
    .location-card {
        border-left: 4px solid var(--bs-primary);
    }
    .location-card.top-performer {
        border-left-color: var(--bs-success);
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1">
                <i class="bi bi-geo-alt text-primary me-2"></i>Location Analytics
            </h1>
            <p class="text-muted mb-0">
                <?php if ($selectedLocation): ?>
                    Analyzing: <strong><?= htmlspecialchars($selectedLocation['name']) ?></strong>
                <?php else: ?>
                    Compare performance across all <?= count($locations) ?> locations
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="registers.php" class="btn btn-outline-secondary">
                <i class="bi bi-cash-stack me-1"></i>Registers
            </a>
            <button class="btn btn-outline-success" onclick="exportReport()">
                <i class="bi bi-file-excel me-1"></i>Export
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Location</label>
                    <select class="form-select" name="location_id">
                        <option value="0">All Locations</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= $loc['id'] ?>" <?= $locationId == $loc['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="date_from" value="<?= $dateFrom ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="date_to" value="<?= $dateTo ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Compare By</label>
                    <select class="form-select" name="compare">
                        <option value="revenue" <?= $compareMode === 'revenue' ? 'selected' : '' ?>>Revenue</option>
                        <option value="transactions" <?= $compareMode === 'transactions' ? 'selected' : '' ?>>Transactions</option>
                        <option value="avg_ticket" <?= $compareMode === 'avg_ticket' ? 'selected' : '' ?>>Avg Ticket</option>
                    </select>
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
                <div class="metric-value text-primary"><?= $overallStats['locations_count'] ?? 0 ?></div>
                <div class="metric-label">Locations</div>
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
                <div class="metric-value"><?= number_format($overallStats['total_transactions'] ?? 0) ?></div>
                <div class="metric-label">Transactions</div>
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
                <div class="metric-value"><?= $overallStats['unique_staff'] ?? 0 ?></div>
                <div class="metric-label">Staff</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="metric-card text-center">
                <?php 
                $avgPerLocation = ($overallStats['locations_count'] ?? 0) > 0 
                    ? ($overallStats['total_revenue'] ?? 0) / $overallStats['locations_count'] 
                    : 0;
                ?>
                <div class="metric-value text-warning"><?= $currencySymbol ?><?= number_format($avgPerLocation, 0) ?></div>
                <div class="metric-label">Avg/Location</div>
            </div>
        </div>
    </div>

    <!-- Location Rankings -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Location Rankings</h5>
            <span class="badge bg-primary">Ranked by <?= ucfirst(str_replace('_', ' ', $compareMode)) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($locationComparison)): ?>
                <p class="text-muted p-4 mb-0">No location data available.</p>
            <?php else: ?>
                <?php 
                // Sort by selected compare mode
                usort($locationComparison, function($a, $b) use ($compareMode) {
                    return $b[$compareMode] <=> $a[$compareMode];
                });
                $maxValue = max(array_column($locationComparison, $compareMode)) ?: 1;
                $rank = 0;
                ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px">Rank</th>
                                <th>Location</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">Transactions</th>
                                <th class="text-end">Avg Ticket</th>
                                <th class="text-end">Staff</th>
                                <th style="width: 20%">Performance</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locationComparison as $loc): $rank++; ?>
                            <tr class="<?= $loc['id'] == $locationId ? 'table-primary' : '' ?>">
                                <td>
                                    <span class="location-rank <?= $rank <= 3 ? 'rank-' . $rank : 'bg-light' ?>">
                                        <?= $rank ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($loc['location_name']) ?></strong>
                                    <?php if ($loc['address']): ?>
                                        <small class="text-muted d-block"><?= htmlspecialchars($loc['address']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <strong class="text-success"><?= $currencySymbol ?><?= number_format($loc['revenue'], 0) ?></strong>
                                </td>
                                <td class="text-end"><?= number_format($loc['transactions']) ?></td>
                                <td class="text-end"><?= $currencySymbol ?><?= number_format($loc['avg_ticket'], 0) ?></td>
                                <td class="text-end"><?= $loc['staff_count'] ?></td>
                                <td>
                                    <div class="progress-bar-custom">
                                        <div class="progress-bar-fill bg-<?= $rank === 1 ? 'success' : ($rank <= 3 ? 'primary' : 'secondary') ?>" 
                                             style="width: <?= ($loc[$compareMode] / $maxValue) * 100 ?>%"></div>
                                    </div>
                                </td>
                                <td>
                                    <a href="?location_id=<?= $loc['id'] ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="location-grid mb-4">
        <!-- Payment Methods by Location -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-credit-card me-2"></i>Payment Methods by Location</h6>
            </div>
            <div class="card-body">
                <?php if (empty($paymentsByLoc)): ?>
                    <p class="text-muted mb-0">No payment data available.</p>
                <?php else: ?>
                    <?php foreach ($paymentsByLoc as $locName => $payments): ?>
                    <div class="mb-3 pb-3 border-bottom">
                        <strong class="d-block mb-2"><?= htmlspecialchars($locName) ?></strong>
                        <div class="row g-2">
                            <?php 
                            $locTotal = array_sum(array_column($payments, 'total')) ?: 1;
                            foreach ($payments as $payment): 
                            ?>
                            <div class="col-auto">
                                <span class="badge bg-<?= $payment['payment_method'] === 'cash' ? 'success' : ($payment['payment_method'] === 'card' ? 'primary' : 'warning') ?>">
                                    <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>: 
                                    <?= number_format(($payment['total'] / $locTotal) * 100, 0) ?>%
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Products by Location -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>Top Products by Location</h6>
            </div>
            <div class="card-body">
                <?php if (empty($productsByLoc)): ?>
                    <p class="text-muted mb-0">No product data available.</p>
                <?php else: ?>
                    <?php foreach ($productsByLoc as $locName => $products): ?>
                    <div class="mb-3 pb-3 border-bottom">
                        <strong class="d-block mb-2"><?= htmlspecialchars($locName) ?></strong>
                        <ol class="mb-0 ps-3">
                            <?php foreach (array_slice($products, 0, 3) as $product): ?>
                            <li class="small">
                                <?= htmlspecialchars($product['product_name']) ?>
                                <span class="text-muted">(<?= number_format($product['qty_sold']) ?> sold)</span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Staff Performance by Location -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-people me-2"></i>Staff Performance by Location</h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($staffByLoc)): ?>
                <p class="text-muted p-4 mb-0">No staff data available.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Location</th>
                                <th>Staff Member</th>
                                <th class="text-end">Transactions</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">Avg Ticket</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $currentLoc = '';
                            foreach ($staffByLocation as $staff): 
                                $showLoc = $staff['location_name'] !== $currentLoc;
                                $currentLoc = $staff['location_name'];
                            ?>
                            <tr>
                                <td>
                                    <?php if ($showLoc): ?>
                                        <strong><?= htmlspecialchars($staff['location_name']) ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($staff['full_name']) ?></td>
                                <td class="text-end"><?= number_format($staff['transactions']) ?></td>
                                <td class="text-end"><?= $currencySymbol ?><?= number_format($staff['revenue'], 0) ?></td>
                                <td class="text-end"><?= $currencySymbol ?><?= number_format($staff['avg_ticket'], 0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Registers by Location -->
    <?php if (!empty($registersByLocation)): ?>
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-cash-stack me-2"></i>Register Performance by Location</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Location</th>
                            <th>Register</th>
                            <th class="text-end">Transactions</th>
                            <th class="text-end">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $currentLoc = '';
                        foreach ($registersByLocation as $reg): 
                            $showLoc = $reg['location_name'] !== $currentLoc;
                            $currentLoc = $reg['location_name'];
                        ?>
                        <tr>
                            <td>
                                <?php if ($showLoc): ?>
                                    <strong><?= htmlspecialchars($reg['location_name']) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($reg['register_name']) ?>
                                <small class="text-muted">(<?= $reg['register_number'] ?>)</small>
                            </td>
                            <td class="text-end"><?= number_format($reg['transactions']) ?></td>
                            <td class="text-end"><?= $currencySymbol ?><?= number_format($reg['revenue'], 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function exportReport() {
    // Build CSV data
    const data = <?= json_encode($locationComparison) ?>;
    if (!data.length) {
        alert('No data to export');
        return;
    }
    
    let csv = 'Location,Revenue,Transactions,Avg Ticket,Staff,Active Days\n';
    data.forEach(row => {
        csv += `"${row.location_name}",${row.revenue},${row.transactions},${row.avg_ticket},${row.staff_count},${row.active_days}\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `location-analytics-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
}
</script>

<?php require_once 'includes/footer.php'; ?>
