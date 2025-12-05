<?php
/**
 * Manager Dashboard - Operational Overview
 * Focus on daily operations, sales, and team management
 */

require_once '../includes/bootstrap.php';
$auth->requireRole('manager');

$db = Database::getInstance();

// Generate personalized greeting
$hour = (int)date('H');
$userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Manager';
$firstName = explode(' ', $userName)[0];

if ($hour >= 5 && $hour < 12) {
    $greeting = "Good morning";
    $greetingIcon = "bi-sunrise";
} elseif ($hour >= 12 && $hour < 17) {
    $greeting = "Good afternoon";
    $greetingIcon = "bi-sun";
} else {
    $greeting = "Good evening";
    $greetingIcon = "bi-moon-stars";
}

$lastLogin = $_SESSION['last_login'] ?? null;
$welcomeMessage = $lastLogin ? "Welcome back" : "Welcome";

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
    .manager-shell {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xl);
    }
    .manager-toolbar {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: var(--spacing-md);
    }
    .manager-toolbar h1 {
        margin: 0;
        font-size: var(--text-2xl);
    }
    .manager-toolbar p {
        margin: 0;
        color: var(--color-text-muted);
    }
    .manager-metrics {
        display: grid;
        gap: var(--spacing-md);
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
    .manager-metric-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    .manager-metric-card h3 {
        font-size: var(--text-2xl);
        margin: 0;
    }
    .manager-metric-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: var(--radius-pill);
        font-size: var(--text-lg);
    }
    .manager-layout {
        display: grid;
        gap: var(--spacing-lg);
    }
    @media (min-width: 1200px) {
        .manager-layout {
            grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
        }
    }
    .manager-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
    }
    .manager-card header {
        padding: var(--spacing-md);
        border-bottom: 1px solid var(--color-border-subtle);
    }
    .manager-card header h5,
    .manager-card header h6 {
        margin: 0;
    }
    .manager-card .card-body {
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-md);
    }
    .manager-table-wrapper {
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--radius-md);
        overflow: hidden;
    }
    .manager-list {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    .manager-list-item {
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--radius-md);
        padding: var(--spacing-sm) var(--spacing-md);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--color-surface-subtle);
    }
    .manager-grid {
        display: grid;
        gap: var(--spacing-sm);
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
    .manager-grid .card {
        border-radius: var(--radius-md);
        border: 1px solid var(--color-border-subtle);
        box-shadow: none;
    }
    .manager-alerts {
        display: grid;
        gap: var(--spacing-sm);
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    .manager-alerts .alert {
        margin: 0;
        border-radius: var(--radius-md);
    }
    .manager-actions {
        display: grid;
        gap: var(--spacing-sm);
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    }
</style>

<div class="manager-shell container-fluid py-4">
    <!-- Personalized Greeting -->
    <div class="alert alert-light border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-success bg-opacity-10 p-3">
                <i class="bi <?= $greetingIcon ?> text-success fs-4"></i>
            </div>
            <div>
                <h4 class="mb-1"><?= $greeting ?>, <?= htmlspecialchars($firstName) ?>! 👋</h4>
                <p class="mb-0 text-muted"><?= $welcomeMessage ?> to your dashboard. Today is <?= date('l, F j, Y') ?>.</p>
            </div>
        </div>
    </div>

    <section class="manager-toolbar">
        <div class="stack-sm">
            <h1><i class="bi bi-briefcase-fill text-success me-2"></i>Manager Dashboard</h1>
            <p>Track daily operations, team output, and revenue health at a glance.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="reports.php" class="btn btn-outline-primary btn-icon">
                <i class="bi bi-graph-up"></i>Reports
            </a>
            <a href="accounting.php" class="btn btn-outline-success btn-icon">
                <i class="bi bi-calculator"></i>Accounting
            </a>
            <a href="users.php" class="btn btn-outline-info btn-icon">
                <i class="bi bi-people"></i>Team
            </a>
        </div>
    </section>

    <section class="manager-metrics">
        <article class="manager-metric-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="stack-xs">
                    <span class="text-muted text-uppercase small">Sales Today</span>
                    <h3><?= number_format($dailyStats['sales_count'] ?? 0) ?></h3>
                </div>
                <span class="manager-metric-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-cart-check"></i>
                </span>
            </div>
            <span class="text-muted small">Transactions captured so far.</span>
        </article>
        <article class="manager-metric-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="stack-xs">
                    <span class="text-muted text-uppercase small">Revenue Today</span>
                    <h3><?= formatMoney($dailyStats['sales_revenue'] ?? 0, false) ?></h3>
                </div>
                <span class="manager-metric-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-cash-coin"></i>
                </span>
            </div>
            <span class="text-muted small">Gross takings from all channels.</span>
        </article>
        <article class="manager-metric-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="stack-xs">
                    <span class="text-muted text-uppercase small">Customers Served</span>
                    <h3><?= number_format($dailyStats['customers_served'] ?? 0) ?></h3>
                </div>
                <span class="manager-metric-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-people"></i>
                </span>
            </div>
            <span class="text-muted small">Unique customers handled today.</span>
        </article>
        <article class="manager-metric-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="stack-xs">
                    <span class="text-muted text-uppercase small">Average Order</span>
                    <h3><?= formatMoney($dailyStats['avg_order'] ?? 0, false) ?></h3>
                </div>
                <span class="manager-metric-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-speedometer2"></i>
                </span>
            </div>
            <span class="text-muted small">Ticket size across today’s sales.</span>
        </article>
    </section>

    <section class="manager-layout">
        <article class="manager-card">
            <header>
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Team Performance (Today)</h5>
            </header>
            <div class="card-body">
                <?php if (!empty($teamPerformance)): ?>
                    <div class="manager-table-wrapper">
                        <table class="table table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff</th>
                                    <th>Role</th>
                                    <th class="text-center">Sales</th>
                                    <th class="text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teamPerformance as $staff): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($staff['full_name']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $staff['role'] === 'cashier' ? 'info' : 'success' ?>">
                                                <?= ucfirst($staff['role']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?= (int)$staff['sales_count'] ?></span>
                                        </td>
                                        <td class="text-end fw-semibold"><?= formatMoney($staff['sales_total'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-person-workspace fs-1"></i>
                        <p class="mt-3 mb-0">No team activity logged yet today.</p>
                    </div>
                <?php endif; ?>
                <a href="users.php" class="btn btn-outline-primary btn-icon align-self-start">
                    <i class="bi bi-people"></i>Manage Team
                </a>
            </div>
        </article>

        <div class="stack-lg">
            <article class="manager-card">
                <header>
                    <h6 class="mb-0"><i class="bi bi-receipt me-2"></i>Recent Orders</h6>
                </header>
                <div class="card-body">
                    <?php if (!empty($recentOrders)): ?>
                        <div class="manager-list">
                            <?php foreach (array_slice($recentOrders, 0, 6) as $order): ?>
                                <div class="manager-list-item">
                                    <div class="stack-xs">
                                        <span class="fw-semibold"><?= htmlspecialchars($order['sale_number']) ?></span>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($order['cashier_name'] ?? 'Unknown') ?> • <?= formatDate($order['created_at'], 'H:i') ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <strong><?= formatMoney($order['total_amount'] ?? 0) ?></strong>
                                        <span class="badge bg-light text-muted border ms-2"><?= ucfirst($order['payment_method']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <p class="mb-0">No orders recorded yet.</p>
                        </div>
                    <?php endif; ?>
                    <a href="sales.php" class="btn btn-outline-success btn-icon align-self-start">
                        <i class="bi bi-list-ul"></i>View All Sales
                    </a>
                </div>
            </article>

            <article class="manager-card">
                <header>
                    <h6 class="mb-0"><i class="bi bi-calendar-month me-2"></i>Monthly Overview</h6>
                </header>
                <div class="card-body">
                    <div class="manager-grid">
                        <div class="card bg-light">
                            <div class="card-body">
                                <small class="text-muted text-uppercase">Total Sales</small>
                                <h4 class="mb-0 text-primary"><?= number_format($monthlyStats['sales'] ?? 0) ?></h4>
                            </div>
                        </div>
                        <div class="card bg-light">
                            <div class="card-body">
                                <small class="text-muted text-uppercase">Revenue</small>
                                <h4 class="mb-0 text-success"><?= formatMoney($monthlyStats['revenue'] ?? 0) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="manager-actions">
                        <a href="reports.php" class="btn btn-outline-primary btn-icon">
                            <i class="bi bi-bar-chart"></i>Detailed Reports
                        </a>
                        <a href="accounting.php" class="btn btn-outline-success btn-icon">
                            <i class="bi bi-journal-text"></i>Financial Summary
                        </a>
                        <a href="customers.php" class="btn btn-outline-info btn-icon">
                            <i class="bi bi-people"></i>Customer Analysis
                        </a>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <section class="manager-grid">
        <article class="manager-card">
            <header>
                <h6 class="mb-0"><i class="bi bi-trophy me-2"></i>Top Products (Today)</h6>
            </header>
            <div class="card-body">
                <?php if (!empty($topProducts)): ?>
                    <div class="manager-grid">
                        <?php foreach ($topProducts as $product): ?>
                            <div class="card bg-light">
                                <div class="card-body py-3">
                                    <div class="fw-semibold small text-truncate"><?= htmlspecialchars($product['product_name']) ?></div>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">Sold: <?= (int)$product['total_sold'] ?></small>
                                        <small class="text-success"><?= formatMoney($product['total_revenue'] ?? 0) ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <p class="mb-0">No product movement yet today.</p>
                    </div>
                <?php endif; ?>
                <a href="products.php" class="btn btn-outline-info btn-icon align-self-start">
                    <i class="bi bi-box-seam"></i>Manage Products
                </a>
            </div>
        </article>

        <article class="manager-card">
            <header>
                <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Inventory Alerts</h6>
            </header>
            <div class="card-body">
                <?php if (!empty($lowStock)): ?>
                    <div class="manager-alerts">
                        <?php foreach ($lowStock as $item): ?>
                            <div class="alert alert-warning d-flex flex-column">
                                <span class="fw-semibold small"><?= htmlspecialchars($item['name']) ?></span>
                                <small class="text-danger">Stock <?= (int)$item['stock_quantity'] ?> • Min <?= (int)$item['min_stock_level'] ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <p class="mb-0">All inventory levels look healthy.</p>
                    </div>
                <?php endif; ?>
                <a href="products.php" class="btn btn-outline-warning btn-icon align-self-start">
                    <i class="bi bi-boxes"></i>Check Inventory
                </a>
            </div>
        </article>
    </section>

    <section class="manager-card">
        <header>
            <h6 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Manager Actions</h6>
        </header>
        <div class="card-body">
            <div class="manager-actions">
                <a href="pos.php" class="btn btn-outline-primary btn-icon">
                    <i class="bi bi-cart-plus"></i>New Sale
                </a>
                <a href="restaurant.php" class="btn btn-outline-success btn-icon">
                    <i class="bi bi-shop"></i>Restaurant Ops
                </a>
                <a href="products.php" class="btn btn-outline-info btn-icon">
                    <i class="bi bi-box"></i>Inventory
                </a>
                <a href="customers.php" class="btn btn-outline-warning btn-icon">
                    <i class="bi bi-people"></i>Customers
                </a>
                <a href="delivery.php" class="btn btn-outline-danger btn-icon">
                    <i class="bi bi-truck"></i>Delivery
                </a>
                <a href="reports.php" class="btn btn-outline-secondary btn-icon">
                    <i class="bi bi-graph-up"></i>Reports
                </a>
            </div>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>
