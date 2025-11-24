<?php
require_once 'includes/bootstrap.php';

use App\Services\AccountingService;
use App\Services\LedgerDataService;

$auth->requireLogin();

$db = Database::getInstance();
$pdo = $db->getConnection();
$accountingService = new AccountingService($pdo);
$ledgerDataService = new LedgerDataService($pdo, $accountingService);

// Get date range
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$selectedLocation = $_GET['location_id'] ?? '';
$selectedPaymentMethod = $_GET['payment_method'] ?? '';
$selectedOrderSource = $_GET['order_source'] ?? '';
$selectedUserId = $_GET['user_id'] ?? '';

try {
    $locations = $db->fetchAll("SELECT id, name FROM locations WHERE is_active = 1 ORDER BY name") ?: [];
} catch (Exception $e) {
    $locations = [];
}

try {
    $cashiers = $db->fetchAll("SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name, username") ?: [];
} catch (Exception $e) {
    $cashiers = [];
}

$paymentMethodsList = [
    'cash' => 'Cash',
    'card' => 'Card',
    'mobile_money' => 'Mobile Money',
    'bank_transfer' => 'Bank Transfer',
    'room_charge' => 'Room Charge'
];

$orderSources = [
    'pos' => 'POS',
    'online' => 'Online',
    'whatsapp' => 'WhatsApp',
    'phone' => 'Phone',
    'mobile_app' => 'Mobile App'
];

$filterClauses = [];
$filterParams = [];

if ($selectedLocation !== '' && $selectedLocation !== 'all') {
    $filterClauses[] = 's.location_id = ?';
    $filterParams[] = (int) $selectedLocation;
}

if ($selectedPaymentMethod !== '' && isset($paymentMethodsList[$selectedPaymentMethod])) {
    $filterClauses[] = 's.payment_method = ?';
    $filterParams[] = $selectedPaymentMethod;
}

if ($selectedOrderSource !== '' && isset($orderSources[$selectedOrderSource])) {
    $filterClauses[] = 's.order_source = ?';
    $filterParams[] = $selectedOrderSource;
}

if ($selectedUserId !== '' && $selectedUserId !== 'all') {
    $filterClauses[] = 's.user_id = ?';
    $filterParams[] = (int) $selectedUserId;
}

$filterSql = $filterClauses ? ' AND ' . implode(' AND ', $filterClauses) : '';

$exportType = $_GET['export'] ?? '';
if ($exportType === 'daily_csv') {
    $exportRows = $db->fetchAll("
        SELECT 
            DATE(s.created_at) as sale_date,
            COUNT(*) as sales_count,
            SUM(s.total_amount) as daily_revenue
        FROM sales s
        WHERE DATE(s.created_at) BETWEEN ? AND ? $filterSql
        GROUP BY DATE(s.created_at)
        ORDER BY sale_date
    ", array_merge([$dateFrom, $dateTo], $filterParams));

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="daily-sales-' . $dateFrom . '-to-' . $dateTo . '.csv"');
    $output = fopen('php://output', 'wb');
    fputcsv($output, ['Date', 'Sales Count', 'Revenue']);
    foreach ($exportRows as $row) {
        fputcsv($output, [$row['sale_date'], $row['sales_count'], $row['daily_revenue']]);
    }
    fclose($output);
    exit;
}

// Sales Summary
$salesSummary = $db->fetchOne("
    SELECT 
        COUNT(*) as total_sales,
        SUM(total_amount) as total_revenue,
        SUM(tax_amount) as total_tax,
        AVG(total_amount) as avg_sale
    FROM sales s
    WHERE DATE(s.created_at) BETWEEN ? AND ? $filterSql
", array_merge([$dateFrom, $dateTo], $filterParams));

// Top Selling Products
$topProducts = $db->fetchAll("
    SELECT 
        si.product_name,
        SUM(si.quantity) as total_quantity,
        SUM(si.total_price) as total_revenue
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    WHERE DATE(s.created_at) BETWEEN ? AND ? $filterSql
    GROUP BY si.product_id, si.product_name
    ORDER BY total_quantity DESC
    LIMIT 10
", array_merge([$dateFrom, $dateTo], $filterParams));

// Sales by Payment Method
$paymentMethods = $db->fetchAll("
    SELECT 
        payment_method,
        COUNT(*) as count,
        SUM(total_amount) as total
    FROM sales s
    WHERE DATE(s.created_at) BETWEEN ? AND ? $filterSql
    GROUP BY payment_method
", array_merge([$dateFrom, $dateTo], $filterParams));

// Daily Sales
$dailySales = $db->fetchAll("
    SELECT 
        DATE(created_at) as sale_date,
        COUNT(*) as sales_count,
        SUM(total_amount) as daily_revenue
    FROM sales s
    WHERE DATE(created_at) BETWEEN ? AND ? $filterSql
    GROUP BY DATE(created_at)
    ORDER BY sale_date
", array_merge([$dateFrom, $dateTo], $filterParams));

// Ledger-backed financial insights
$financialSummary = $ledgerDataService->getFinancialSummary($dateFrom, $dateTo);
$vatSummary = $ledgerDataService->getVatSummary($dateFrom, $dateTo);

$totalSalesCount = (int) ($salesSummary['total_sales'] ?? 0);
$postedRevenue = $financialSummary['revenue_total'];
$outputVat = $vatSummary['output_tax'] ?? 0;
$netVat = $vatSummary['net_tax'] ?? 0;
$averageSale = $totalSalesCount > 0 ? $postedRevenue / $totalSalesCount : 0;

$netVatClass = $netVat >= 0 ? 'text-danger' : 'text-success';

$recentWindow = array_slice(array_column($dailySales, 'daily_revenue'), -7);
$previousWindow = array_slice(array_column($dailySales, 'daily_revenue'), -14, 7);
$recentAverage = !empty($recentWindow) ? array_sum($recentWindow) / count($recentWindow) : 0;
$previousAverage = !empty($previousWindow) ? array_sum($previousWindow) / count($previousWindow) : 0;
$trendPercent = $previousAverage > 0 ? (($recentAverage - $previousAverage) / $previousAverage) * 100 : null;
$forecastNextWeek = $recentAverage * 7;

$dailyExportParams = $_GET;
$dailyExportParams['export'] = 'daily_csv';
$dailyExportUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query($dailyExportParams);

$pageTitle = 'Reports';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-graph-up me-2"></i>Sales Reports</h4>
</div>

<!-- Date Filter -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Date From</label>
                <input type="date" class="form-control" name="date_from" value="<?= $dateFrom ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Date To</label>
                <input type="date" class="form-control" name="date_to" value="<?= $dateTo ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Location</label>
                <select class="form-select" name="location_id">
                    <option value="">All locations</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= $location['id'] ?>" <?= ($selectedLocation !== '' && (int)$selectedLocation === (int)$location['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($location['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Cashier</label>
                <select class="form-select" name="user_id">
                    <option value="">All users</option>
                    <?php foreach ($cashiers as $cashier): ?>
                        <?php $displayName = $cashier['full_name'] ?: $cashier['username']; ?>
                        <option value="<?= $cashier['id'] ?>" <?= ($selectedUserId !== '' && (int)$selectedUserId === (int)$cashier['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($displayName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Payment Method</label>
                <select class="form-select" name="payment_method">
                    <option value="">All methods</option>
                    <?php foreach ($paymentMethodsList as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $selectedPaymentMethod === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Order Source</label>
                <select class="form-select" name="order_source">
                    <option value="">All channels</option>
                    <?php foreach ($orderSources as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $selectedOrderSource === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-2"></i>Generate Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-cart-check text-primary fs-1 mb-2"></i>
                <h3 class="mb-0"><?= $totalSalesCount ?></h3>
                <p class="text-muted small mb-0">Recorded Sales</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-cash-stack text-success fs-1 mb-2"></i>
                <h3 class="mb-0"><?= formatMoney($postedRevenue) ?></h3>
                <p class="text-muted small mb-0">Posted Revenue (Ledger)</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-receipt text-info fs-1 mb-2"></i>
                <h3 class="mb-0"><?= formatMoney($outputVat) ?></h3>
                <p class="text-muted small mb-0">Output VAT</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-balance-scale text-warning fs-1 mb-2"></i>
                <h3 class="mb-0 <?= $netVatClass ?>"><?= formatMoney($netVat) ?></h3>
                <p class="text-muted small mb-0">Net VAT <?= $netVat >= 0 ? 'Payable' : 'Recoverable' ?></p>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-light border-start border-4 border-primary mb-4">
    <div class="d-flex align-items-center">
        <i class="bi bi-info-circle me-2 text-primary"></i>
        <div>
            Ledger figures above reflect posted journal entries, ensuring IFRS-aligned revenue and tax reporting.
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-activity text-primary fs-1 mb-2"></i>
                <h3 class="mb-0"><?= formatMoney($forecastNextWeek) ?></h3>
                <p class="text-muted small mb-1">Forecasted Revenue (Next 7 Days)</p>
                <?php if ($trendPercent !== null): ?>
                    <span class="badge bg-<?= $trendPercent >= 0 ? 'success' : 'danger' ?>">
                        <?= ($trendPercent >= 0 ? '+' : '') . number_format($trendPercent, 1) ?>% vs prior week
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary">Not enough data</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <i class="bi bi-ticket text-info fs-1 mb-2"></i>
                <h3 class="mb-0"><?= formatMoney($averageSale) ?></h3>
                <p class="text-muted small mb-0">Average Ticket Size</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Top Selling Products -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Top Selling Products</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProducts as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['product_name']) ?></td>
                                <td class="text-end"><span class="badge bg-primary"><?= $product['total_quantity'] ?></span></td>
                                <td class="text-end fw-bold"><?= formatMoney($product['total_revenue']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales by Payment Method -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-credit-card me-2"></i>Sales by Payment Method</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Payment Method</th>
                                <th class="text-end">Count</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paymentMethods as $method): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?= $method['payment_method'] === 'cash' ? 'success' : 'info' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $method['payment_method'])) ?>
                                    </span>
                                </td>
                                <td class="text-end"><?= $method['count'] ?></td>
                                <td class="text-end fw-bold"><?= formatMoney($method['total']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Sales Chart -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Daily Sales Trend</h6>
                <a href="<?= htmlspecialchars($dailyExportUrl) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-download me-1"></i>Download CSV
                </a>
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="80"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('salesChart');
const currencyCode = <?= json_encode(getCurrencyCode()) ?>;
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($dailySales, 'sale_date')) ?>,
        datasets: [{
            label: 'Daily Revenue (' + currencyCode + ')',
            data: <?= json_encode(array_column($dailySales, 'daily_revenue')) ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
