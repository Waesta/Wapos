<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

// Initialize default values
$todaySales = ['total_sales' => 0, 'total_revenue' => 0];
$lowStock = [];
$recentSales = [];
$monthStats = ['total_orders' => 0, 'total_revenue' => 0, 'avg_order' => 0];

try {
    // Get today's stats
    $today = date('Y-m-d');
    $result = $db->fetchOne("
        SELECT 
            COUNT(*) as total_sales,
            COALESCE(SUM(total_amount), 0) as total_revenue
        FROM sales 
        WHERE DATE(created_at) = ?
    ", [$today]);
    
    if ($result) {
        $todaySales = $result;
    }
} catch (Exception $e) {
    error_log('Dashboard sales query error: ' . $e->getMessage());
}

try {
    // Get low stock products
    $lowStock = $db->fetchAll("
        SELECT id, name, stock_quantity, min_stock_level 
        FROM products 
        WHERE stock_quantity <= min_stock_level AND is_active = 1
        ORDER BY stock_quantity ASC
        LIMIT 10
    ") ?: [];
} catch (Exception $e) {
    error_log('Dashboard low stock query error: ' . $e->getMessage());
}

try {
    // Get recent sales
    $recentSales = $db->fetchAll("
        SELECT 
            s.*,
            u.full_name as cashier_name
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.id
        ORDER BY s.created_at DESC
        LIMIT 10
    ") ?: [];
} catch (Exception $e) {
    error_log('Dashboard recent sales query error: ' . $e->getMessage());
}

try {
    // Get this month's stats
    $monthStart = date('Y-m-01');
    $result = $db->fetchOne("
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(AVG(total_amount), 0) as avg_order
        FROM sales 
        WHERE DATE(created_at) >= ?
    ", [$monthStart]);
    
    if ($result) {
        $monthStats = $result;
    }
} catch (Exception $e) {
    error_log('Dashboard month stats query error: ' . $e->getMessage());
}

$pageTitle = 'Dashboard';
include 'includes/header.php';
?>

<div class="row g-3 mb-4">
    <!-- Today's Sales Card -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Today's Sales</p>
                        <h3 class="mb-0 fw-bold"><?= $todaySales['total_sales'] ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                        <i class="bi bi-cart-check text-primary fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Revenue Card -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Today's Revenue</p>
                        <h3 class="mb-0 fw-bold"><?= formatMoney($todaySales['total_revenue']) ?></h3>
                    </div>
                    <div class="bg-success bg-opacity-10 p-3 rounded">
                        <i class="bi bi-cash-stack text-success fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Month's Orders Card -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">This Month</p>
                        <h3 class="mb-0 fw-bold"><?= $monthStats['total_orders'] ?></h3>
                    </div>
                    <div class="bg-info bg-opacity-10 p-3 rounded">
                        <i class="bi bi-graph-up text-info fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert Card -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Low Stock Items</p>
                        <h3 class="mb-0 fw-bold text-warning"><?= count($lowStock) ?></h3>
                    </div>
                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                        <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Quick Actions -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-lightning-fill text-warning me-2"></i>Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="pos.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-cart-plus me-2"></i>New Sale
                    </a>
                    <a href="products.php?action=add" class="btn btn-outline-success">
                        <i class="bi bi-plus-circle me-2"></i>Add Product
                    </a>
                    <a href="reports.php" class="btn btn-outline-info">
                        <i class="bi bi-graph-up me-2"></i>View Reports
                    </a>
                </div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <?php if (count($lowStock) > 0): ?>
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Low Stock Alert</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($lowStock, 0, 5) as $product): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0"><?= htmlspecialchars($product['name']) ?></h6>
                                <small class="text-muted">Stock: <?= $product['stock_quantity'] ?> (Min: <?= $product['min_stock_level'] ?>)</small>
                            </div>
                            <span class="badge bg-warning">Low</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($lowStock) > 5): ?>
                <div class="card-footer text-center">
                    <a href="products.php?filter=low_stock" class="btn btn-sm btn-link">View All (<?= count($lowStock) ?>)</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Sales -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Sales</h6>
                    <a href="sales.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Sale #</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Cashier</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentSales)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    No sales yet. <a href="pos.php">Make your first sale!</a>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($recentSales as $sale): ?>
                                <tr>
                                    <td><a href="sale-details.php?id=<?= $sale['id'] ?>"><?= htmlspecialchars($sale['sale_number']) ?></a></td>
                                    <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                    <td>
                                        <?php 
                                        $itemCount = $db->fetchOne("SELECT COUNT(*) as count FROM sale_items WHERE sale_id = ?", [$sale['id']]);
                                        echo $itemCount['count'];
                                        ?>
                                    </td>
                                    <td class="fw-bold"><?= formatMoney($sale['total_amount']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $sale['payment_method'] === 'cash' ? 'success' : 'info' ?>">
                                            <?= ucfirst($sale['payment_method']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($sale['cashier_name']) ?></td>
                                    <td><small class="text-muted"><?= formatDate($sale['created_at'], 'H:i') ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
