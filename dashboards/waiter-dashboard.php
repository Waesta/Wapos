<?php
/**
 * Waiter Dashboard
 * Restaurant-focused dashboard for waiters
 */

require_once '../includes/bootstrap.php';
$auth->requireRole('waiter');

$db = Database::getInstance();

$pageTitle = 'Waiter Dashboard';
include '../includes/header.php';

// Get waiter's stats
$userId = $auth->getUserId();
$today = date('Y-m-d');

// Today's orders by this waiter
$todayOrders = $db->fetchOne("
    SELECT 
        COUNT(*) as count,
        COALESCE(SUM(total_amount), 0) as total
    FROM restaurant_orders 
    WHERE waiter_id = ? AND DATE(created_at) = ?
", [$userId, $today]) ?: ['count' => 0, 'total' => 0];

// Active orders (pending/preparing)
$activeOrders = $db->fetchAll("
    SELECT ro.*, rt.table_number, rr.room_name
    FROM restaurant_orders ro
    LEFT JOIN restaurant_tables rt ON ro.table_id = rt.id
    LEFT JOIN rooms rr ON ro.room_id = rr.id
    WHERE ro.waiter_id = ? 
    AND ro.status IN ('pending', 'preparing', 'ready')
    ORDER BY ro.created_at ASC
", [$userId]) ?: [];

// Recent completed orders
$recentOrders = $db->fetchAll("
    SELECT ro.*, rt.table_number, rr.room_name
    FROM restaurant_orders ro
    LEFT JOIN restaurant_tables rt ON ro.table_id = rt.id
    LEFT JOIN rooms rr ON ro.room_id = rr.id
    WHERE ro.waiter_id = ?
    ORDER BY ro.created_at DESC
    LIMIT 5
", [$userId]) ?: [];

// Available tables
$availableTables = $db->fetchAll("
    SELECT * FROM restaurant_tables 
    WHERE status = 'available' 
    ORDER BY table_number ASC
") ?: [];

// Get this shift's stats (last 8 hours)
$shiftStart = date('Y-m-d H:i:s', strtotime('-8 hours'));
$shiftOrders = $db->fetchOne("
    SELECT 
        COUNT(*) as count,
        COALESCE(SUM(total_amount), 0) as total
    FROM restaurant_orders 
    WHERE waiter_id = ? AND created_at >= ?
", [$userId, $shiftStart]) ?: ['count' => 0, 'total' => 0];
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-person-badge me-2"></i>Waiter Dashboard</h2>
            <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($auth->getUser()['full_name'] ?? 'User') ?>!</p>
        </div>
        <div>
            <a href="../restaurant.php" class="btn btn-primary btn-lg">
                <i class="bi bi-plus-circle me-2"></i>New Order
            </a>
        </div>
    </div>

    <!-- Stats Summary Cards -->
    <div class="row g-3 mb-4">
        <!-- Today's Orders -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted mb-1 small">Today's Orders</p>
                            <h3 class="mb-0"><?= $todayOrders['count'] ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="bi bi-receipt text-success fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-cash me-1"></i><?= formatMoney($todayOrders['total']) ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Active Orders -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted mb-1 small">Active Orders</p>
                            <h3 class="mb-0"><?= count($activeOrders) ?></h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="bi bi-hourglass-split text-warning fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-clock me-1"></i>Needs attention
                    </small>
                </div>
            </div>
        </div>

        <!-- This Shift -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted mb-1 small">This Shift (8h)</p>
                            <h3 class="mb-0"><?= $shiftOrders['count'] ?></h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="bi bi-clock text-primary fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-cash me-1"></i><?= formatMoney($shiftOrders['total']) ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Available Tables -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted mb-1 small">Available Tables</p>
                            <h3 class="mb-0"><?= count($availableTables) ?></h3>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="bi bi-table text-info fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-check-circle me-1"></i>Ready to serve
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
                            <a href="../restaurant.php" class="btn btn-primary w-100 btn-lg">
                                <i class="bi bi-plus-circle me-2"></i>New Order
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="../manage-tables.php" class="btn btn-outline-success w-100">
                                <i class="bi bi-table me-2"></i>Manage Tables
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="../kitchen-display.php" class="btn btn-outline-warning w-100">
                                <i class="bi bi-fire me-2"></i>Kitchen Display
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="../customers.php" class="btn btn-outline-info w-100">
                                <i class="bi bi-people me-2"></i>Customers
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Active Orders -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="bi bi-hourglass-split me-2"></i>Active Orders</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($activeOrders)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle fs-1 d-block mb-3 text-success"></i>
                            <h5>No Active Orders</h5>
                            <p class="mb-0">All orders are completed. Great job!</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($activeOrders as $order): ?>
                                <div class="col-md-6">
                                    <div class="card border-start border-4 <?= $order['status'] === 'ready' ? 'border-success' : 'border-warning' ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="mb-1">Order #<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?></h6>
                                                    <small class="text-muted">
                                                        <?php if ($order['table_number']): ?>
                                                            <i class="bi bi-table me-1"></i>Table <?= $order['table_number'] ?>
                                                        <?php elseif ($order['room_name']): ?>
                                                            <i class="bi bi-door-open me-1"></i><?= htmlspecialchars($order['room_name']) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <?php
                                                $statusColors = [
                                                    'pending' => 'warning',
                                                    'preparing' => 'info',
                                                    'ready' => 'success'
                                                ];
                                                $color = $statusColors[$order['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?= $color ?>">
                                                    <?= ucfirst($order['status']) ?>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong><?= formatMoney($order['total_amount']) ?></strong>
                                                <small class="text-muted">
                                                    <?= date('h:i A', strtotime($order['created_at'])) ?>
                                                </small>
                                            </div>
                                            <?php if ($order['status'] === 'ready'): ?>
                                                <div class="mt-2">
                                                    <a href="../restaurant-payment.php?order_id=<?= $order['id'] ?>" 
                                                       class="btn btn-sm btn-success w-100">
                                                        <i class="bi bi-cash me-1"></i>Process Payment
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Orders & Available Tables -->
        <div class="col-lg-4">
            <!-- Recent Orders -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Orders</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recentOrders)): ?>
                        <div class="text-center py-3 text-muted">
                            <p class="mb-0 small">No recent orders</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentOrders as $order): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">#<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?></h6>
                                            <small class="text-muted">
                                                <?= date('h:i A', strtotime($order['created_at'])) ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <strong class="d-block"><?= formatMoney($order['total_amount']) ?></strong>
                                            <span class="badge bg-<?= $order['status'] === 'completed' ? 'success' : 'secondary' ?> badge-sm">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Available Tables -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0"><i class="bi bi-table me-2"></i>Available Tables</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($availableTables)): ?>
                        <div class="text-center py-3 text-muted">
                            <p class="mb-0 small">All tables are occupied</p>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($availableTables as $table): ?>
                                <a href="../restaurant.php?table_id=<?= $table['id'] ?>" 
                                   class="btn btn-outline-success btn-sm">
                                    Table <?= $table['table_number'] ?>
                                    <small class="text-muted">(<?= $table['capacity'] ?>)</small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
