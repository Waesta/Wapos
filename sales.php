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

$pageTitle = 'Sales History';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-receipt me-2"></i>Sales History</h4>
    <button class="btn btn-success" onclick="exportToExcel()">
        <i class="bi bi-file-excel me-2"></i>Export to Excel
    </button>
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
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Total Sales</p>
                        <h3 class="mb-0 fw-bold"><?= $totalSales ?></h3>
                    </div>
                    <i class="bi bi-cart-check text-primary fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Total Revenue</p>
                        <h3 class="mb-0 fw-bold text-success">KES <?= formatMoney($totalRevenue) ?></h3>
                    </div>
                    <i class="bi bi-currency-dollar text-success fs-1"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sales Table -->
<div class="card border-0 shadow-sm">
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
                        <tr>
                            <td><?= formatDate($sale['created_at'], 'd/m/Y H:i') ?></td>
                            <td>
                                <a href="sale-details.php?id=<?= $sale['id'] ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($sale['sale_number']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                            <td><span class="badge bg-secondary"><?= $sale['item_count'] ?> items</span></td>
                            <td class="fw-bold">KES <?= formatMoney($sale['total_amount']) ?></td>
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
    </div>
</div>

<script>
function exportToExcel() {
    window.location.href = 'export-sales.php?date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&payment_method=<?= $paymentMethod ?>';
}
</script>

<?php include 'includes/footer.php'; ?>
