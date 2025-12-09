<?php
/**
 * Bar Variance Report
 * 
 * Track expected vs actual usage for cost control
 */

use App\Services\BarManagementService;

require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager', 'accountant']);

$db = Database::getInstance();
$pdo = $db->getConnection();
$barService = new BarManagementService($pdo);
$barService->ensureSchema();

$csrfToken = generateCSRFToken();
$currencySymbol = CurrencyManager::getInstance()->getCurrencySymbol();

// Date range
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$productId = !empty($_GET['product_id']) ? (int) $_GET['product_id'] : null;

// Get usage summary
$usageSummary = $barService->getUsageSummary($startDate, $endDate, $productId);

// Get portioned products for filter
$portionedProducts = $barService->getPortionedProducts();

// Calculate totals
$totalSalesMl = 0;
$totalWastageMl = 0;
$totalUsageMl = 0;
$totalPours = 0;

foreach ($usageSummary as $item) {
    $totalSalesMl += $item['sales_ml'] ?? 0;
    $totalWastageMl += ($item['wastage_ml'] ?? 0) + ($item['spillage_ml'] ?? 0);
    $totalUsageMl += $item['total_usage_ml'] ?? 0;
    $totalPours += $item['pour_count'] ?? 0;
}

$pageTitle = 'Bar Variance Report';
include 'includes/header.php';
?>

<style>
    .variance-positive { color: var(--bs-danger); }
    .variance-negative { color: var(--bs-success); }
    .stat-card {
        border-left: 4px solid var(--bs-primary);
    }
    .stat-card.warning { border-left-color: var(--bs-warning); }
    .stat-card.danger { border-left-color: var(--bs-danger); }
    .stat-card.success { border-left-color: var(--bs-success); }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-graph-up me-2"></i>Bar Variance Report</h4>
            <p class="text-muted mb-0">Track usage, wastage, and shrinkage for cost control</p>
        </div>
        <div class="d-flex gap-2">
            <a href="bar-management.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Bar Management
            </a>
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Print Report
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Product</label>
                    <select class="form-select" name="product_id">
                        <option value="">All Portioned Products</option>
                        <?php foreach ($portionedProducts as $product): ?>
                        <option value="<?= $product['id'] ?>" <?= $productId == $product['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($product['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm stat-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Total Sales Volume</h6>
                            <h3 class="mb-0"><?= number_format($totalSalesMl) ?> ml</h3>
                            <small class="text-muted"><?= number_format($totalSalesMl / 1000, 2) ?> L</small>
                        </div>
                        <div class="text-primary">
                            <i class="bi bi-droplet-fill fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm stat-card warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Wastage & Spillage</h6>
                            <h3 class="mb-0"><?= number_format($totalWastageMl) ?> ml</h3>
                            <small class="text-muted">
                                <?php 
                                $wastagePercent = $totalUsageMl > 0 ? ($totalWastageMl / $totalUsageMl) * 100 : 0;
                                echo number_format($wastagePercent, 1) . '% of total';
                                ?>
                            </small>
                        </div>
                        <div class="text-warning">
                            <i class="bi bi-exclamation-triangle fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm stat-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Total Pours</h6>
                            <h3 class="mb-0"><?= number_format($totalPours) ?></h3>
                            <small class="text-muted">Individual transactions</small>
                        </div>
                        <div class="text-info">
                            <i class="bi bi-cup-straw fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm stat-card success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Products Tracked</h6>
                            <h3 class="mb-0"><?= count($usageSummary) ?></h3>
                            <small class="text-muted">Portioned items</small>
                        </div>
                        <div class="text-success">
                            <i class="bi bi-box-seam fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Usage Details Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h6 class="mb-0">Usage Details by Product</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Bottle Size</th>
                            <th class="text-end">Expected Yield</th>
                            <th class="text-end">Sales (ml)</th>
                            <th class="text-end">Wastage (ml)</th>
                            <th class="text-end">Spillage (ml)</th>
                            <th class="text-end">Comp (ml)</th>
                            <th class="text-end">Staff (ml)</th>
                            <th class="text-end">Total Usage</th>
                            <th class="text-end">Pours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usageSummary)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="bi bi-info-circle me-2"></i>No usage data for the selected period.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($usageSummary as $item): ?>
                        <?php
                        $totalItemUsage = ($item['total_usage_ml'] ?? 0);
                        $itemWastage = ($item['wastage_ml'] ?? 0) + ($item['spillage_ml'] ?? 0);
                        $wastageRate = $totalItemUsage > 0 ? ($itemWastage / $totalItemUsage) * 100 : 0;
                        $allowedWastage = $item['wastage_allowance_percent'] ?? 2;
                        $isOverWastage = $wastageRate > $allowedWastage;
                        ?>
                        <tr class="<?= $isOverWastage ? 'table-warning' : '' ?>">
                            <td>
                                <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                <?php if ($isOverWastage): ?>
                                <span class="badge bg-danger ms-2">High Wastage</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($item['bottle_size_ml']): ?>
                                <?= number_format($item['bottle_size_ml']) ?> ml
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($item['expected_portions']): ?>
                                <?= $item['expected_portions'] ?> portions
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= number_format($item['sales_ml'] ?? 0) ?></td>
                            <td class="text-end <?= ($item['wastage_ml'] ?? 0) > 0 ? 'text-warning' : '' ?>">
                                <?= number_format($item['wastage_ml'] ?? 0) ?>
                            </td>
                            <td class="text-end <?= ($item['spillage_ml'] ?? 0) > 0 ? 'text-warning' : '' ?>">
                                <?= number_format($item['spillage_ml'] ?? 0) ?>
                            </td>
                            <td class="text-end"><?= number_format($item['comp_ml'] ?? 0) ?></td>
                            <td class="text-end"><?= number_format($item['staff_ml'] ?? 0) ?></td>
                            <td class="text-end fw-bold"><?= number_format($totalItemUsage) ?></td>
                            <td class="text-end"><?= number_format($item['pour_count'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($usageSummary)): ?>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td>TOTALS</td>
                            <td></td>
                            <td></td>
                            <td class="text-end"><?= number_format($totalSalesMl) ?></td>
                            <td class="text-end"><?= number_format(array_sum(array_column($usageSummary, 'wastage_ml'))) ?></td>
                            <td class="text-end"><?= number_format(array_sum(array_column($usageSummary, 'spillage_ml'))) ?></td>
                            <td class="text-end"><?= number_format(array_sum(array_column($usageSummary, 'comp_ml'))) ?></td>
                            <td class="text-end"><?= number_format(array_sum(array_column($usageSummary, 'staff_ml'))) ?></td>
                            <td class="text-end"><?= number_format($totalUsageMl) ?></td>
                            <td class="text-end"><?= number_format($totalPours) ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Wastage Analysis -->
    <?php if (!empty($usageSummary)): ?>
    <div class="row g-4 mt-2">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Usage Breakdown</h6>
                </div>
                <div class="card-body">
                    <?php
                    $categories = [
                        'Sales' => $totalSalesMl,
                        'Wastage' => array_sum(array_column($usageSummary, 'wastage_ml')),
                        'Spillage' => array_sum(array_column($usageSummary, 'spillage_ml')),
                        'Complimentary' => array_sum(array_column($usageSummary, 'comp_ml')),
                        'Staff' => array_sum(array_column($usageSummary, 'staff_ml')),
                    ];
                    $colors = ['primary', 'warning', 'danger', 'info', 'secondary'];
                    $i = 0;
                    ?>
                    <?php foreach ($categories as $label => $value): ?>
                    <?php if ($value > 0): ?>
                    <?php $percent = $totalUsageMl > 0 ? ($value / $totalUsageMl) * 100 : 0; ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span><?= $label ?></span>
                            <span><?= number_format($value) ?> ml (<?= number_format($percent, 1) ?>%)</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-<?= $colors[$i] ?>" style="width: <?= $percent ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php $i++; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>High Wastage Products</h6>
                </div>
                <div class="card-body">
                    <?php
                    $highWastage = array_filter($usageSummary, function($item) {
                        $total = $item['total_usage_ml'] ?? 0;
                        $wastage = ($item['wastage_ml'] ?? 0) + ($item['spillage_ml'] ?? 0);
                        $rate = $total > 0 ? ($wastage / $total) * 100 : 0;
                        return $rate > ($item['wastage_allowance_percent'] ?? 2);
                    });
                    ?>
                    <?php if (empty($highWastage)): ?>
                    <div class="text-center text-success py-3">
                        <i class="bi bi-check-circle fs-1 mb-2 d-block"></i>
                        <p class="mb-0">All products within acceptable wastage limits!</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($highWastage as $item): ?>
                        <?php
                        $total = $item['total_usage_ml'] ?? 0;
                        $wastage = ($item['wastage_ml'] ?? 0) + ($item['spillage_ml'] ?? 0);
                        $rate = $total > 0 ? ($wastage / $total) * 100 : 0;
                        ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                <br><small class="text-muted">Allowed: <?= $item['wastage_allowance_percent'] ?? 2 ?>%</small>
                            </div>
                            <span class="badge bg-danger"><?= number_format($rate, 1) ?>% wastage</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
