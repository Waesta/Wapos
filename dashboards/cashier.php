<?php
/**
 * Cashier Dashboard
 * POS-focused dashboard for cashiers
 */

require_once '../includes/bootstrap.php';
$auth->requireRole('cashier');

$db = Database::getInstance();

// Generate personalized greeting
$hour = (int)date('H');
$userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Cashier';
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

$pageTitle = 'Cashier Dashboard';
include '../includes/header.php';

// Get cashier's stats
$userId = $auth->getUserId();
$today = date('Y-m-d');

// Today's sales by this cashier
$todaySales = $db->fetchOne("
    SELECT 
        COUNT(*) as count,
        COALESCE(SUM(total_amount), 0) as total
    FROM sales 
    WHERE user_id = ? AND DATE(created_at) = ?
", [$userId, $today]) ?: ['count' => 0, 'total' => 0];

// This shift's sales (last 8 hours)
$shiftStart = date('Y-m-d H:i:s', strtotime('-8 hours'));
$shiftSales = $db->fetchOne("
    SELECT 
        COUNT(*) as count,
        COALESCE(SUM(total_amount), 0) as total
    FROM sales 
    WHERE user_id = ? AND created_at >= ?
", [$userId, $shiftStart]) ?: ['count' => 0, 'total' => 0];

// Recent sales by this cashier
$recentSales = $db->fetchAll("
    SELECT *
    FROM sales 
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
", [$userId]) ?: [];

// Get low stock products
$lowStock = $db->fetchAll("
    SELECT p.*, 
           COALESCE(SUM(sm.quantity), 0) as current_stock
    FROM products p
    LEFT JOIN stock_movements sm ON p.id = sm.product_id
    WHERE p.is_active = 1
    GROUP BY p.id
    HAVING current_stock <= p.reorder_level
    ORDER BY current_stock ASC
    LIMIT 5
");
?>

<div class="container-fluid py-4">
    <!-- Personalized Greeting -->
    <div class="alert alert-light border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                <i class="bi <?= $greetingIcon ?> text-primary fs-4"></i>
            </div>
            <div>
                <h4 class="mb-1"><?= $greeting ?>, <?= htmlspecialchars($firstName) ?>! 👋</h4>
                <p class="mb-0 text-muted"><?= $welcomeMessage ?>! Today is <?= date('l, F j, Y') ?>. Have a productive shift!</p>
            </div>
        </div>
    </div>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-cash-register me-2"></i>Cashier Dashboard</h2>
            <p class="text-muted mb-0">Your sales summary and quick actions</p>
        </div>
        <div>
            <a href="../pos.php" class="btn btn-primary btn-lg">
                <i class="bi bi-cart-plus me-2"></i>Open POS
            </a>
        </div>
    </div>

    <!-- Sales Summary Cards -->
    <div class="row g-3 mb-4">
        <!-- Today's Sales -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted mb-1 small">Today's Sales</p>
                            <h3 class="mb-0"><?= formatMoney($todaySales['total']) ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="bi bi-calendar-check text-success fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-receipt me-1"></i><?= $todaySales['count'] ?> transactions
                    </small>
                </div>
            </div>
        </div>

        <!-- This Shift -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted mb-1 small">This Shift (8h)</p>
                            <h3 class="mb-0"><?= formatMoney($shiftSales['total']) ?></h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="bi bi-clock text-primary fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-receipt me-1"></i><?= $shiftSales['count'] ?> transactions
                    </small>
                </div>
            </div>
        </div>

        <!-- Average Transaction -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted mb-1 small">Average Transaction</p>
                            <h3 class="mb-0">
                                <?php 
                                $avg = $todaySales['count'] > 0 ? $todaySales['total'] / $todaySales['count'] : 0;
                                echo formatMoney($avg);
                                ?>
                            </h3>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="bi bi-graph-up text-info fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-calculator me-1"></i>Per sale average
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h5>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <a href="../pos.php" class="btn btn-primary w-100 btn-lg">
                                <i class="bi bi-cart-plus me-2"></i>New Sale
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="../customers.php" class="btn btn-outline-success w-100">
                                <i class="bi bi-people me-2"></i>Customers
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="../products.php" class="btn btn-outline-info w-100">
                                <i class="bi bi-box-seam me-2"></i>Products
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="../sales.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-receipt me-2"></i>My Sales
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Recent Sales -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>My Recent Sales</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Receipt #</th>
                                    <th>Time</th>
                                    <th>Amount</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentSales)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                            No sales yet. Start selling!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentSales as $sale): ?>
                                        <tr>
                                            <td><strong>#<?= str_pad($sale['id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                                            <td><?= date('h:i A', strtotime($sale['created_at'])) ?></td>
                                            <td><strong><?= formatMoney($sale['total_amount']) ?></strong></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= ucfirst($sale['payment_method'] ?? 'Cash') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                // Calculate payment status based on amount_paid
                                                if ($sale['amount_paid'] >= $sale['total_amount']) {
                                                    $paymentStatus = 'completed';
                                                    $class = 'success';
                                                } elseif ($sale['amount_paid'] > 0) {
                                                    $paymentStatus = 'partial';
                                                    $class = 'info';
                                                } else {
                                                    $paymentStatus = 'pending';
                                                    $class = 'warning';
                                                }
                                                ?>
                                                <span class="badge bg-<?= $class ?>">
                                                    <?= ucfirst($paymentStatus) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="../print-receipt.php?id=<?= $sale['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   target="_blank">
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
        </div>

        <!-- Low Stock Alert -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Low Stock Alert</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($lowStock)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
                            <p class="mb-0">All products are well stocked!</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($lowStock as $product): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?= htmlspecialchars($product['name']) ?></h6>
                                            <small class="text-muted">SKU: <?= htmlspecialchars($product['sku']) ?></small>
                                        </div>
                                        <span class="badge bg-danger">
                                            <?= (int)$product['current_stock'] ?> left
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
