<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

// Get filter parameters
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$paymentMethod = $_GET['payment_method'] ?? '';

// Build query
$sql = "
    SELECT 
        s.*,
        u.full_name as cashier_name,
        (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
";

$params = [$dateFrom, $dateTo];

if ($paymentMethod) {
    $sql .= " AND s.payment_method = ?";
    $params[] = $paymentMethod;
}

$sql .= " ORDER BY s.created_at DESC";

$sales = $db->fetchAll($sql, $params);

// Calculate totals
$totalSales = count($sales);
$totalRevenue = array_sum(array_column($sales, 'total_amount'));
$averageSale = $totalSales > 0 ? $totalRevenue / $totalSales : 0;

$salesByPayment = $db->fetchAll(
    "SELECT payment_method, COUNT(*) as count, SUM(total_amount) as amount
     FROM sales
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY payment_method",
    [$dateFrom, $dateTo]
) ?: [];

$topCashiers = $db->fetchAll(
    "SELECT u.full_name, COUNT(s.id) AS sales_count, SUM(s.total_amount) AS total_amount
     FROM sales s
     LEFT JOIN users u ON s.user_id = u.id
     WHERE DATE(s.created_at) BETWEEN ? AND ?
     GROUP BY s.user_id
     ORDER BY total_amount DESC
     LIMIT 5",
    [$dateFrom, $dateTo]
) ?: [];

$topProducts = $db->fetchAll(
    "SELECT 
        COALESCE(p.name, si.product_name) AS product_name,
        SUM(si.quantity) AS total_quantity,
        SUM(si.total_price) AS total_amount
     FROM sale_items si
     JOIN sales s ON si.sale_id = s.id
     LEFT JOIN products p ON si.product_id = p.id
     WHERE DATE(s.created_at) BETWEEN ? AND ?
     GROUP BY COALESCE(p.name, si.product_name)
     ORDER BY total_amount DESC
     LIMIT 6",
    [$dateFrom, $dateTo]
) ?: [];

$pageTitle = 'Sales History';
include 'includes/header.php';
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h4 class="mb-0"><i class="bi bi-receipt me-2"></i>Sales History</h4>
        <small class="text-muted">Review transactions, payment mix, and top performers</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-secondary" onclick="window.open('customers.php', '_self')">
            <i class="bi bi-people me-2"></i>Customers
        </button>
        <button class="btn btn-success" onclick="exportToExcel()">
            <i class="bi bi-file-excel me-2"></i>Export to Excel
        </button>
    </div>
</div>

<!-- Filter Card -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small">Date From</label>
                <input type="date" class="form-control" name="date_from" value="<?= $dateFrom ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Date To</label>
                <input type="date" class="form-control" name="date_to" value="<?= $dateTo ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Payment Method</label>
                <select class="form-select" name="payment_method">
                    <option value="">All Methods</option>
                    <option value="cash" <?= $paymentMethod === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="card" <?= $paymentMethod === 'card' ? 'selected' : '' ?>>Card</option>
                    <option value="mobile_money" <?= $paymentMethod === 'mobile_money' ? 'selected' : '' ?>>Mobile Money</option>
                    <option value="bank_transfer" <?= $paymentMethod === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-2"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <small class="text-muted text-uppercase">Total Sales</small>
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0 fw-bold"><?= $totalSales ?></h3>
                    <i class="bi bi-cart-check text-primary fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <small class="text-muted text-uppercase">Total Revenue</small>
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0 fw-bold text-success"><?= formatMoney($totalRevenue) ?></h3>
                    <i class="bi bi-currency-dollar text-success fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <small class="text-muted text-uppercase">Average Ticket</small>
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0 fw-bold text-info"><?= formatMoney($averageSale) ?></h3>
                    <i class="bi bi-graph-up text-info fs-1"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Mix & Top Performers -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">Payment Mix</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($salesByPayment)): ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th class="text-end">Count</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salesByPayment as $row): ?>
                            <tr>
                                <td><?= ucfirst(str_replace('_', ' ', $row['payment_method'])) ?></td>
                                <td class="text-end"><?= (int)$row['count'] ?></td>
                                <td class="text-end"><?= formatMoney($row['amount']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No payment data for the selected range.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">Top Cashiers</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($topCashiers)): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($topCashiers as $cashier): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= htmlspecialchars($cashier['full_name'] ?? '—') ?></strong>
                            <div class="small text-muted"><?= (int)$cashier['sales_count'] ?> sales</div>
                        </div>
                        <span class="badge bg-primary-subtle text-primary"><?= formatMoney($cashier['total_amount']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                    <p class="text-muted mb-0">No cashier data in this range.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">Top Products</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($topProducts)): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($topProducts as $product): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= htmlspecialchars($product['product_name'] ?? 'Unnamed') ?></strong>
                            <div class="small text-muted"><?= (float)$product['total_quantity'] ?> units</div>
                        </div>
                        <span class="badge bg-success-subtle text-success"><?= formatMoney($product['total_amount']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                    <p class="text-muted mb-0">No product sales captured in this period.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Sales Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <h5 class="mb-0">Sales Register</h5>
        <div class="d-flex flex-wrap gap-2">
            <div class="input-group input-group-sm" style="max-width:220px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="salesSearch" placeholder="Search sale # or customer">
            </div>
            <select class="form-select form-select-sm" id="salesPaymentFilter" style="max-width:180px;">
                <option value="">All Methods</option>
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="mobile_money">Mobile Money</option>
                <option value="bank_transfer">Bank Transfer</option>
            </select>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="salesTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Sale #</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Cashier</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No sales found for the selected period
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($sales as $sale): ?>
                        <?php
                            $searchIndex = strtolower(trim(($sale['sale_number'] ?? '') . ' ' . ($sale['customer_name'] ?? 'walk-in')));
                        ?>
                        <tr data-payment="<?= htmlspecialchars($sale['payment_method']) ?>" data-search="<?= htmlspecialchars($searchIndex) ?>">
                            <td><?= formatDate($sale['created_at'], 'd/m/Y H:i') ?></td>
                            <td>
                                <a href="sale-details.php?id=<?= $sale['id'] ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($sale['sale_number']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                            <td><span class="badge bg-secondary"><?= $sale['item_count'] ?> items</span></td>
                            <td class="fw-bold"><?= formatMoney($sale['total_amount']) ?></td>
                            <td>
                                <span class="badge bg-<?= $sale['payment_method'] === 'cash' ? 'success' : 'info' ?>">
                                    <?= ucfirst(str_replace('_', ' ', $sale['payment_method'])) ?>
                                </span>
                            </td>
                            <td><small><?= htmlspecialchars($sale['cashier_name']) ?></small></td>
                            <td>
                                <a href="sale-details.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="print-receipt.php?id=<?= $sale['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div id="salesEmptyState" class="alert alert-info text-center" style="display: <?= empty($sales) ? '' : 'none' ?>;">
            No sales match the current filters.
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    window.location.href = 'export-sales.php?date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&payment_method=<?= $paymentMethod ?>';
}

const salesSearchInput = document.getElementById('salesSearch');
const salesPaymentFilter = document.getElementById('salesPaymentFilter');
const salesRows = Array.from(document.querySelectorAll('#salesTable tbody tr'));
const salesEmptyState = document.getElementById('salesEmptyState');

document.addEventListener('DOMContentLoaded', () => {
    if (salesSearchInput) {
        salesSearchInput.addEventListener('input', debounce(filterSales, 150));
    }
    if (salesPaymentFilter) {
        salesPaymentFilter.addEventListener('change', filterSales);
    }
    filterSales();
});

function filterSales() {
    const term = (salesSearchInput?.value || '').trim().toLowerCase();
    const payment = salesPaymentFilter?.value || '';
    let visible = 0;

    salesRows.forEach(row => {
        const matchesSearch = !term || (row.dataset.search || '').includes(term);
        const matchesPayment = !payment || (row.dataset.payment || '') === payment;

        if (matchesSearch && matchesPayment) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    if (salesEmptyState) {
        salesEmptyState.style.display = visible === 0 ? '' : 'none';
    }
}

function debounce(fn, delay) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn.apply(this, args), delay);
    };
}
</script>

<?php include 'includes/footer.php'; ?>
