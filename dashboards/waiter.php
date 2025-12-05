<?php
/**
 * Waiter Dashboard
 * Restaurant-focused dashboard for waiters
 */

require_once '../includes/bootstrap.php';
$auth->requireRole('waiter');

$db = Database::getInstance();

// Generate personalized greeting
$hour = (int)date('H');
$userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Waiter';
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

<style>
    .waiter-shell {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xl);
    }
    .waiter-toolbar {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: var(--spacing-md);
    }
    .waiter-toolbar h1 {
        margin: 0;
        font-size: var(--text-2xl);
    }
    .waiter-toolbar p {
        margin: 0;
        color: var(--color-text-muted);
    }
    .waiter-metrics {
        display: grid;
        gap: var(--spacing-md);
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    }
    .waiter-metric-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    .waiter-metric-card h3 {
        font-size: var(--text-2xl);
        margin: 0;
    }
    .waiter-metric-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-pill);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: var(--text-lg);
    }
    .waiter-quick-actions {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-md);
    }
    .waiter-quick-actions .action-grid {
        display: grid;
        gap: var(--spacing-sm);
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }
    .waiter-layout {
        display: grid;
        gap: var(--spacing-lg);
    }
    @media (min-width: 1200px) {
        .waiter-layout {
            grid-template-columns: minmax(0, 7fr) minmax(0, 4fr);
        }
    }
    .waiter-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
    }
    .waiter-card header {
        padding: var(--spacing-md);
        border-bottom: 1px solid var(--color-border-subtle);
    }
    .waiter-card header h5,
    .waiter-card header h6 {
        margin: 0;
    }
    .waiter-card .card-body {
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-md);
    }
    .waiter-active-grid {
        display: grid;
        gap: var(--spacing-md);
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    }
    .waiter-active-card {
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--radius-md);
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
        background: var(--color-surface-subtle);
    }
    .waiter-active-card[data-status="ready"] {
        border-left: 4px solid var(--color-success-500);
    }
    .waiter-active-card[data-status="preparing"] {
        border-left: 4px solid var(--color-info-500);
    }
    .waiter-active-card[data-status="pending"] {
        border-left: 4px solid var(--color-warning-500);
    }
    .waiter-list {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    .waiter-list-item {
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--radius-md);
        padding: var(--spacing-sm) var(--spacing-md);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .waiter-tables {
        display: flex;
        flex-wrap: wrap;
        gap: var(--spacing-sm);
    }
    .waiter-tables .btn {
        display: inline-flex;
        align-items: center;
        gap: var(--spacing-xs);
    }
</style>

<div class="waiter-shell container-fluid py-4">
    <!-- Personalized Greeting -->
    <div class="alert alert-light border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-warning bg-opacity-25 p-3">
                <i class="bi <?= $greetingIcon ?> text-warning fs-4"></i>
            </div>
            <div>
                <h4 class="mb-1"><?= $greeting ?>, <?= htmlspecialchars($firstName) ?>! 👋</h4>
                <p class="mb-0 text-muted"><?= $welcomeMessage ?>! Today is <?= date('l, F j, Y') ?>. Ready to serve!</p>
            </div>
        </div>
    </div>

    <section class="waiter-toolbar">
        <div class="stack-sm">
            <h1><i class="bi bi-person-badge me-2"></i>Waiter Dashboard</h1>
            <p>Your shift insights and active orders</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="../restaurant.php" class="btn btn-primary btn-icon btn-lg">
                <i class="bi bi-plus-circle"></i>New Order
            </a>
            <a href="../restaurant-reservations.php" class="btn btn-outline-secondary btn-icon">
                <i class="bi bi-calendar-event"></i>Reservations
            </a>
        </div>
    </section>

    <section class="waiter-metrics">
        <article class="waiter-metric-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="stack-xs">
                    <span class="text-muted text-uppercase small">Today's Orders</span>
                    <h3><?= (int)($todayOrders['count'] ?? 0) ?></h3>
                </div>
                <span class="waiter-metric-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-receipt"></i>
                </span>
            </div>
            <span class="text-muted small"><i class="bi bi-cash-coin me-1"></i><?= formatMoney($todayOrders['total'] ?? 0) ?></span>
        </article>
        <article class="waiter-metric-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="stack-xs">
                    <span class="text-muted text-uppercase small">Active Orders</span>
                    <h3><?= count($activeOrders) ?></h3>
                </div>
                <span class="waiter-metric-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-hourglass-split"></i>
                </span>
            </div>
            <span class="text-muted small"><i class="bi bi-alarm me-1"></i>Needs attention</span>
        </article>
        <article class="waiter-metric-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="stack-xs">
                    <span class="text-muted text-uppercase small">Shift (8h)</span>
                    <h3><?= (int)($shiftOrders['count'] ?? 0) ?></h3>
                </div>
                <span class="waiter-metric-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-clock"></i>
                </span>
            </div>
            <span class="text-muted small"><i class="bi bi-cash me-1"></i><?= formatMoney($shiftOrders['total'] ?? 0) ?></span>
        </article>
        <article class="waiter-metric-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="stack-xs">
                    <span class="text-muted text-uppercase small">Tables Free</span>
                    <h3><?= count($availableTables) ?></h3>
                </div>
                <span class="waiter-metric-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-table"></i>
                </span>
            </div>
            <span class="text-muted small"><i class="bi bi-check-circle me-1"></i>Ready to seat</span>
        </article>
    </section>

    <section class="waiter-quick-actions">
        <header class="stack-xs">
            <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h5>
            <span class="text-muted small">Jump straight into the tools you use every shift.</span>
        </header>
        <div class="action-grid">
            <a href="../restaurant.php" class="btn btn-primary btn-lg">
                <i class="bi bi-plus-circle me-2"></i>New Order
            </a>
            <a href="../manage-tables.php" class="btn btn-outline-success btn-lg">
                <i class="bi bi-table me-2"></i>Manage Tables
            </a>
            <a href="../kitchen-display.php" class="btn btn-outline-warning btn-lg">
                <i class="bi bi-fire me-2"></i>Kitchen Display
            </a>
            <a href="../customers.php" class="btn btn-outline-info btn-lg">
                <i class="bi bi-people me-2"></i>Customers
            </a>
        </div>
    </section>

    <section class="waiter-layout">
        <article class="waiter-card">
            <header>
                <h5 class="mb-0"><i class="bi bi-hourglass-split me-2"></i>Active Orders</h5>
            </header>
            <div class="card-body">
                <?php if (empty($activeOrders)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-check-circle fs-1 text-success"></i>
                        <p class="mt-3 mb-0">All tickets cleared — nice work!</p>
                    </div>
                <?php else: ?>
                    <div class="waiter-active-grid">
                        <?php foreach ($activeOrders as $order): ?>
                            <?php
                                $statusColors = [
                                    'pending' => 'warning',
                                    'preparing' => 'info',
                                    'ready' => 'success'
                                ];
                                $statusColor = $statusColors[$order['status']] ?? 'secondary';
                                $orderLabel = sprintf('#%04d', $order['id']);
                            ?>
                            <div class="waiter-active-card" data-status="<?= htmlspecialchars($order['status']) ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="stack-xs">
                                        <span class="fw-semibold">Order <?= $orderLabel ?></span>
                                        <small class="text-muted">
                                            <?php if (!empty($order['table_number'])): ?>
                                                <i class="bi bi-table me-1"></i>Table <?= htmlspecialchars($order['table_number']) ?>
                                            <?php elseif (!empty($order['room_name'])): ?>
                                                <i class="bi bi-door-open me-1"></i><?= htmlspecialchars($order['room_name']) ?>
                                            <?php else: ?>
                                                Takeout
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <span class="app-status" data-color="<?= $statusColor ?>"><?= ucfirst($order['status']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><?= formatMoney($order['total_amount'] ?? 0) ?></strong>
                                    <small class="text-muted"><i class="bi bi-clock me-1"></i><?= date('h:i A', strtotime($order['created_at'])) ?></small>
                                </div>
                                <?php if ($order['status'] === 'ready'): ?>
                                    <a href="../restaurant-payment.php?order_id=<?= $order['id'] ?>" class="btn btn-success btn-sm w-100">
                                        <i class="bi bi-cash me-1"></i>Process Payment
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>

        <div class="stack-lg">
            <article class="waiter-card">
                <header>
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Orders</h6>
                </header>
                <div class="card-body">
                    <?php if (empty($recentOrders)): ?>
                        <div class="text-center text-muted py-3">
                            <p class="mb-0">No recent orders yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="waiter-list">
                            <?php foreach ($recentOrders as $order): ?>
                                <?php $orderLabel = sprintf('#%04d', $order['id']); ?>
                                <div class="waiter-list-item">
                                    <div class="stack-xs">
                                        <span class="fw-semibold"><?= $orderLabel ?></span>
                                        <small class="text-muted"><?= date('h:i A', strtotime($order['created_at'])) ?></small>
                                    </div>
                                    <div class="text-end">
                                        <strong><?= formatMoney($order['total_amount'] ?? 0) ?></strong>
                                        <span class="app-status ms-2" data-color="<?= $order['status'] === 'completed' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>

            <article class="waiter-card">
                <header>
                    <h6 class="mb-0"><i class="bi bi-table me-2"></i>Free Tables</h6>
                </header>
                <div class="card-body">
                    <?php if (empty($availableTables)): ?>
                        <div class="text-center text-muted py-3">
                            <p class="mb-0">All tables are currently seated.</p>
                        </div>
                    <?php else: ?>
                        <div class="waiter-tables">
                            <?php foreach ($availableTables as $table): ?>
                                <a href="../restaurant.php?table_id=<?= $table['id'] ?>" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-person-plus"></i>Table <?= htmlspecialchars($table['table_number']) ?>
                                    <span class="text-muted">(<?= (int)$table['capacity'] ?>)</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>
