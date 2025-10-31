<?php
/**
 * Manager Dashboard - Operational Overview
 * Focus on daily operations, sales, and team management
 */

require_once '../includes/bootstrap.php';
$auth->requireRole('manager');

$db = Database::getInstance();

// Manager-specific metrics
$today = date('Y-m-d');
$monthStart = date('Y-m-01');

// Daily operations
$dailyStats = [
    'sales_count' => $db->fetchOne("SELECT COUNT(*) as count FROM sales WHERE DATE(created_at) = ?", [$today])['count'],
    'sales_revenue' => $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE DATE(created_at) = ?", [$today])['revenue'],
    'customers_served' => $db->fetchOne("SELECT COUNT(DISTINCT customer_name) as count FROM sales WHERE DATE(created_at) = ? AND customer_name IS NOT NULL", [$today])['count'],
    'avg_order' => $db->fetchOne("SELECT COALESCE(AVG(total_amount), 0) as avg FROM sales WHERE DATE(created_at) = ?", [$today])['avg']
];

// Monthly comparison
$monthlyStats = [
    'sales' => $db->fetchOne("SELECT COUNT(*) as count FROM sales WHERE DATE(created_at) >= ?", [$monthStart])['count'],
    'revenue' => $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE DATE(created_at) >= ?", [$monthStart])['revenue']
];

// Team performance
$teamPerformance = $db->fetchAll("
    SELECT 
        u.full_name,
        u.role,
        COUNT(s.id) as sales_count,
        COALESCE(SUM(s.total_amount), 0) as sales_total
    FROM users u
    LEFT JOIN sales s ON u.id = s.user_id AND DATE(s.created_at) = ?
    WHERE u.is_active = 1 AND u.role IN ('cashier', 'waiter')
    GROUP BY u.id
    ORDER BY sales_count DESC
    LIMIT 10
", [$today]);

// Recent orders
$recentOrders = $db->fetchAll("
    SELECT 
        s.*,
        u.full_name as cashier_name
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    ORDER BY s.created_at DESC
    LIMIT 8
");

// Low stock items (manager needs to know)
$lowStock = $db->fetchAll("
    SELECT name, stock_quantity, min_stock_level 
    FROM products 
    WHERE stock_quantity <= min_stock_level AND is_active = 1
    ORDER BY stock_quantity ASC
    LIMIT 8
");

// Top selling products today
$topProducts = $db->fetchAll("
    SELECT 
        si.product_name,
        SUM(si.quantity) as total_sold,
        SUM(si.total_price) as total_revenue
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    WHERE DATE(s.created_at) = ?
    GROUP BY si.product_name
    ORDER BY total_sold DESC
    LIMIT 6
", [$today]);

$pageTitle = 'Manager Dashboard';
include '../includes/header.php';
?>

<style>
.manager-metric {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border-radius: 12px;
    padding: 1.25rem;
}
.performance-card {
    transition: transform 0.2s;
}
.performance-card:hover {
    transform: translateY(-2px);
}
</style>

<!-- Manager Dashboard Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">
            <i class="bi bi-briefcase-fill text-success me-2"></i>
            Manager Dashboard
        </h2>
        <p class="text-muted mb-0">Daily operations and team oversight</p>
    </div>
    <div class="btn-group">
        <a href="reports.php" class="btn btn-outline-primary">
            <i class="bi bi-graph-up me-1"></i>Reports
        </a>
        <a href="accounting.php" class="btn btn-outline-success">
            <i class="bi bi-calculator me-1"></i>Accounting
        </a>
        <a href="users.php" class="btn btn-outline-info">
            <i class="bi bi-people me-1"></i>Team
        </a>
    </div>
</div>

<!-- Daily Performance Metrics -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="manager-metric text-center">
            <div class="h2 mb-1"><?= number_format($dailyStats['sales_count']) ?></div>
            <div class="fw-bold">Sales Today</div>
            <small class="opacity-75">Transactions</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="manager-metric text-center">
            <div class="h2 mb-1"><?= formatMoney($dailyStats['sales_revenue'], false) ?></div>
            <div class="fw-bold">Revenue Today</div>
            <small class="opacity-75">Total earnings</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="manager-metric text-center">
            <div class="h2 mb-1"><?= number_format($dailyStats['customers_served']) ?></div>
            <div class="fw-bold">Customers</div>
            <small class="opacity-75">Served today</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="manager-metric text-center">
            <div class="h2 mb-1"><?= formatMoney($dailyStats['avg_order'], false) ?></div>
            <div class="fw-bold">Avg Order</div>
            <small class="opacity-75">Per transaction</small>
        </div>
    </div>
</div>

<!-- Main Content Row -->
<div class="row g-4 mb-4">
    <!-- Team Performance -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm performance-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Team Performance (Today)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($teamPerformance)): ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Staff Member</th>
                                <th>Role</th>
                                <th class="text-center">Sales</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teamPerformance as $staff): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($staff['full_name']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $staff['role'] === 'cashier' ? 'info' : 'success' ?>">
                                        <?= ucfirst($staff['role']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?= $staff['sales_count'] ?></span>
                                </td>
                                <td class="text-end">
                                    <strong><?= formatMoney($staff['sales_total']) ?></strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-3">
                    <i class="bi bi-person-workspace fs-1"></i>
                    <p class="mt-2">No team activity today</p>
                </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="users.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-people me-2"></i>Manage Team
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Orders -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm performance-card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Recent Orders</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recentOrders)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($recentOrders, 0, 6) as $order): ?>
                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($order['sale_number']) ?></div>
                                <small class="text-muted">
                                    <?= htmlspecialchars($order['cashier_name'] ?? 'Unknown') ?>
                                    • <?= formatDate($order['created_at'], 'H:i') ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success"><?= formatMoney($order['total_amount']) ?></div>
                                <small class="text-muted"><?= ucfirst($order['payment_method']) ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-3">
                    <i class="bi bi-receipt fs-1"></i>
                    <p class="mt-2">No recent orders</p>
                </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="sales.php" class="btn btn-outline-success w-100">
                        <i class="bi bi-list-ul me-2"></i>View All Sales
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Content Row -->
<div class="row g-4 mb-4">
    <!-- Top Products -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Top Products (Today)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($topProducts)): ?>
                <div class="row g-2">
                    <?php foreach ($topProducts as $product): ?>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <div class="fw-bold small"><?= htmlspecialchars($product['product_name']) ?></div>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">Sold: <?= $product['total_sold'] ?></small>
                                    <small class="text-success"><?= formatMoney($product['total_revenue']) ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-3">
                    <i class="bi bi-box fs-1"></i>
                    <p class="mt-2">No sales data today</p>
                </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="products.php" class="btn btn-outline-info w-100">
                        <i class="bi bi-box-seam me-2"></i>Manage Products
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Inventory Alerts -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Inventory Alerts</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($lowStock)): ?>
                <div class="row g-2">
                    <?php foreach ($lowStock as $item): ?>
                    <div class="col-md-6">
                        <div class="alert alert-warning mb-2 py-2">
                            <div class="fw-bold small"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="text-danger small">
                                Stock: <strong><?= $item['stock_quantity'] ?></strong> 
                                (Min: <?= $item['min_stock_level'] ?>)
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-3">
                    <i class="bi bi-check-circle fs-1 text-success"></i>
                    <p class="mt-2">All inventory levels good</p>
                </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="products.php" class="btn btn-outline-warning w-100">
                        <i class="bi bi-boxes me-2"></i>Check Inventory
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Overview -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-calendar-month me-2"></i>Monthly Performance</h5>
            </div>
            <div class="card-body">
                <div class="row text-center g-3">
                    <div class="col-md-6">
                        <div class="border-end">
                            <div class="h3 text-primary mb-1"><?= number_format($monthlyStats['sales']) ?></div>
                            <div class="text-muted">Total Sales This Month</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="h3 text-success mb-1"><?= formatMoney($monthlyStats['revenue']) ?></div>
                        <div class="text-muted">Monthly Revenue</div>
                    </div>
                </div>
                
                <div class="row g-2 mt-3">
                    <div class="col-md-4">
                        <a href="reports.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-graph-up me-2"></i>Detailed Reports
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="accounting.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-calculator me-2"></i>Financial Summary
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="customers.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-people me-2"></i>Customer Analysis
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Manager Actions -->
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Manager Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-2">
                        <a href="pos.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-cart-plus d-block fs-4 mb-2"></i>
                            <small>New Sale</small>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="restaurant.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-shop d-block fs-4 mb-2"></i>
                            <small>Restaurant</small>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="products.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-box-seam d-block fs-4 mb-2"></i>
                            <small>Inventory</small>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="customers.php" class="btn btn-outline-warning w-100">
                            <i class="bi bi-people d-block fs-4 mb-2"></i>
                            <small>Customers</small>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="delivery.php" class="btn btn-outline-danger w-100">
                            <i class="bi bi-truck d-block fs-4 mb-2"></i>
                            <small>Delivery</small>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="reports.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-graph-up d-block fs-4 mb-2"></i>
                            <small>Reports</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
