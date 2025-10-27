<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

// Get active tables
$tables = $db->fetchAll("SELECT * FROM restaurant_tables WHERE is_active = 1 ORDER BY table_number");

// Get active orders by table
$activeOrders = $db->fetchAll("
    SELECT o.*, rt.table_number, rt.table_name, 
           COUNT(oi.id) as item_count,
           SUM(oi.quantity) as total_items
    FROM orders o
    LEFT JOIN restaurant_tables rt ON o.table_id = rt.id
    LEFT JOIN order_items oi ON o.id = oi.id
    WHERE o.order_type IN ('dine-in', 'takeout') 
    AND o.status NOT IN ('completed', 'cancelled')
    GROUP BY o.id
    ORDER BY o.created_at DESC
");

$pageTitle = 'Restaurant Orders';
include 'includes/header.php';
?>

<style>
    .table-card {
        cursor: pointer;
        transition: all 0.3s;
        height: 150px;
    }
    .table-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.2);
    }
    .table-available { border-left: 4px solid #28a745; }
    .table-occupied { border-left: 4px solid #dc3545; }
    .table-reserved { border-left: 4px solid #ffc107; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-shop me-2"></i>Restaurant Management</h4>
    <div class="btn-group">
        <button class="btn btn-primary" onclick="newOrder('dine-in')">
            <i class="bi bi-plus-circle me-2"></i>New Dine-In
        </button>
        <button class="btn btn-success" onclick="newOrder('takeout')">
            <i class="bi bi-bag-check me-2"></i>New Takeout
        </button>
    </div>
</div>

<div class="row g-3">
    <!-- Tables Section -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-grid me-2"></i>Restaurant Tables</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($tables as $table): ?>
                    <div class="col-md-3">
                        <div class="card table-card table-<?= $table['status'] ?>" 
                             onclick="selectTable(<?= $table['id'] ?>, '<?= htmlspecialchars($table['table_number']) ?>')">
                            <div class="card-body text-center">
                                <i class="bi bi-<?= $table['status'] === 'available' ? 'check-circle text-success' : 'x-circle text-danger' ?> fs-1 mb-2"></i>
                                <h5 class="mb-1"><?= htmlspecialchars($table['table_number']) ?></h5>
                                <p class="text-muted small mb-0"><?= htmlspecialchars($table['table_name'] ?? '') ?></p>
                                <small class="badge bg-<?= $table['status'] === 'available' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($table['status']) ?>
                                </small>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="bi bi-people me-1"></i><?= $table['capacity'] ?> seats
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Orders -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-receipt me-2"></i>Active Orders (<?= count($activeOrders) ?>)</h6>
            </div>
            <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                <?php if (empty($activeOrders)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2">No active orders</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($activeOrders as $order): ?>
                        <a href="restaurant-order.php?id=<?= $order['id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($order['order_number']) ?></h6>
                                    <p class="mb-1 small text-muted">
                                        <?php if ($order['order_type'] === 'dine-in'): ?>
                                            <i class="bi bi-table me-1"></i><?= htmlspecialchars($order['table_number'] ?? 'No table') ?>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-info" onclick="window.open('print-kitchen-order.php?id=<?= $order['id'] ?>', '_blank', 'width=400,height=600')">
                                                <i class="bi bi-printer"></i> Kitchen
                                            </button>
                                        <?php endif; ?>
                                    </p>
                                    <p class="mb-0 small">
                                        <span class="badge bg-<?= $order['status'] === 'pending' ? 'warning' : 'info' ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                        <span class="text-muted ms-2"><?= $order['total_items'] ?? 0 ?> items</span>
                                    </p>
                                </div>
                                <div class="text-end">
                                    <strong class="text-primary">KES <?= formatMoney($order['total_amount']) ?></strong>
                                    <br><small class="text-muted"><?= formatDate($order['created_at'], 'H:i') ?></small>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function selectTable(tableId, tableName) {
    if (confirm(`Create new order for ${tableName}?`)) {
        window.location.href = `restaurant-order.php?new=1&table_id=${tableId}`;
    }
}

function newOrder(type) {
    window.location.href = `restaurant-order.php?new=1&type=${type}`;
}
</script>

<?php include 'includes/footer.php'; ?>
