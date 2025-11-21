<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

// Get active tables
$tables = $db->fetchAll("SELECT * FROM restaurant_tables WHERE is_active = 1 ORDER BY table_number");

// Get active orders by table
$activeOrders = $db->fetchAll("
    SELECT o.*, rt.table_number, rt.table_name,
           COUNT(oi.id) AS item_count,
           SUM(oi.quantity) AS total_items
    FROM orders o
    LEFT JOIN restaurant_tables rt ON o.table_id = rt.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.order_type IN ('dine-in', 'takeout')
      AND o.status NOT IN ('completed', 'cancelled')
    GROUP BY o.id
    ORDER BY o.created_at DESC
");

$stats = [
    'open' => 0,
    'preparing' => 0,
    'ready' => 0,
];

foreach ($activeOrders as $order) {
    $status = strtolower((string)($order['status'] ?? ''));
    if (in_array($status, ['pending', 'placed'], true)) {
        $stats['open']++;
    } elseif (in_array($status, ['in_progress', 'preparing'], true)) {
        $stats['preparing']++;
    } elseif (in_array($status, ['ready', 'ready_for_pickup'], true)) {
        $stats['ready']++;
    }
}

$totalActive = count($activeOrders);

$tablesAvailable = array_filter($tables, fn($table) => ($table['status'] ?? '') === 'available');
$tablesOccupied = array_filter($tables, fn($table) => ($table['status'] ?? '') === 'occupied');
$tablesReserved = array_filter($tables, fn($table) => ($table['status'] ?? '') === 'reserved');

$pageTitle = 'Restaurant Orders';
include 'includes/header.php';
?>

<style>
    .restaurant-shell {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-lg);
    }
    .restaurant-columns {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-lg);
    }
    @media (min-width: 992px) {
        .restaurant-columns {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
            gap: var(--spacing-lg);
            align-items: start;
        }
    }
    .restaurant-table-grid {
        display: grid;
        gap: var(--spacing-md);
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    }
    .table-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: var(--spacing-sm);
        transition: transform var(--transition-base), box-shadow var(--transition-base);
        cursor: pointer;
        min-height: 170px;
        text-align: center;
        width: 100%;
    }
    .table-card:hover,
    .table-card:focus-visible {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        outline: none;
    }
    .table-card .table-icon {
        font-size: 2.5rem;
    }
    .table-card[data-status="available"] { border-left: 4px solid var(--color-success); }
    .table-card[data-status="occupied"] { border-left: 4px solid var(--color-danger); }
    .table-card[data-status="reserved"] { border-left: 4px solid var(--color-warning); }
    .summary-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xs);
        height: 100%;
    }
    .summary-card-title {
        text-transform: uppercase;
        font-size: var(--text-xs);
        letter-spacing: 0.06em;
        color: var(--color-text-muted);
    }
    .summary-card-value {
        font-size: var(--text-xl);
        font-weight: 700;
    }
    .order-list {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
        max-height: 620px;
        overflow-y: auto;
        padding-right: var(--spacing-sm);
    }
    .order-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface);
        padding: var(--spacing-md);
        box-shadow: var(--shadow-sm);
        transition: transform var(--transition-base), box-shadow var(--transition-base);
    }
    .order-card[role="button"] {
        cursor: pointer;
    }
    .order-card:hover,
    .order-card:focus-visible {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        outline: none;
    }
    .order-card-actions {
        display: flex;
        gap: var(--spacing-xs);
        flex-wrap: wrap;
    }
    .order-empty {
        text-align: center;
        color: var(--color-text-muted);
        padding: var(--spacing-lg) 0;
    }
</style>

<div class="container-fluid py-4 restaurant-shell">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="stack-sm">
            <h1 class="mb-0"><i class="bi bi-shop me-2"></i>Restaurant Management</h1>
            <p class="text-muted mb-0">Monitor dining rooms and keep kitchen tickets flowing smoothly.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary btn-icon" onclick="newOrder('dine-in')">
                <i class="bi bi-plus-circle me-2"></i>New Dine-In
            </button>
            <button class="btn btn-success btn-icon" onclick="newOrder('takeout')">
                <i class="bi bi-bag-check me-2"></i>New Takeout
            </button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-6 col-xl-2">
            <div class="summary-card">
                <span class="summary-card-title">Active Orders</span>
                <span class="summary-card-value"><?= $totalActive ?></span>
                <span class="text-muted small">Open across all channels</span>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="summary-card">
                <span class="summary-card-title">Waiting</span>
                <span class="summary-card-value"><?= $stats['open'] ?></span>
                <span class="text-muted small">Awaiting kitchen acceptance</span>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="summary-card">
                <span class="summary-card-title">In Kitchen</span>
                <span class="summary-card-value"><?= $stats['preparing'] ?></span>
                <span class="text-muted small">Currently being prepared</span>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="summary-card">
                <span class="summary-card-title">Ready</span>
                <span class="summary-card-value"><?= $stats['ready'] ?></span>
                <span class="text-muted small">Ready to serve or pickup</span>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="summary-card">
                <span class="summary-card-title">Tables Free</span>
                <span class="summary-card-value"><?= count($tablesAvailable) ?></span>
                <span class="text-muted small">Available for seating</span>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="summary-card">
                <span class="summary-card-title">Tables Occupied</span>
                <span class="summary-card-value"><?= count($tablesOccupied) ?></span>
                <span class="text-muted small">Guests currently seated</span>
            </div>
        </div>
    </div>

    <div class="restaurant-columns">
        <div class="restaurant-column">
            <div class="app-card h-100">
                <div class="section-heading">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-grid me-2"></i>Restaurant Tables</h6>
                        <span class="text-muted small">Tap a table to start a new ticket</span>
                    </div>
                </div>
                <?php if (empty($tables)): ?>
                    <div class="order-empty">
                        <i class="bi bi-exclamation-circle fs-3"></i>
                        <p class="mt-2 mb-0">No tables configured yet.</p>
                        <small>Configure dining tables under Restaurant Settings.</small>
                    </div>
                <?php else: ?>
                    <div class="restaurant-table-grid">
                        <?php foreach ($tables as $table): ?>
                            <?php
                                $status = strtolower((string)($table['status'] ?? 'available'));
                                $statusColor = match ($status) {
                                    'available' => 'success',
                                    'occupied' => 'danger',
                                    'reserved' => 'warning',
                                    default => 'secondary'
                                };
                                $statusIcon = match ($status) {
                                    'occupied' => 'people-fill',
                                    'reserved' => 'calendar-event',
                                    default => 'check-circle'
                                };
                                $tableNumber = trim((string)($table['table_number'] ?? ''));
                                $tableLabel = $tableNumber !== '' ? $tableNumber : 'Table #' . (int)$table['id'];
                                $tableLabelEsc = htmlspecialchars($tableLabel, ENT_QUOTES, 'UTF-8');
                                $tableName = trim((string)($table['table_name'] ?? ''));
                                $tableNameEsc = htmlspecialchars($tableName, ENT_QUOTES, 'UTF-8');
                            ?>
                            <button type="button"
                                    class="table-card"
                                    data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
                                    onclick="selectTable(<?= (int)$table['id'] ?>, '<?= $tableLabelEsc ?>')">
                                <div class="table-icon text-<?= $statusColor ?>">
                                    <i class="bi bi-<?= $statusIcon ?>"></i>
                                </div>
                                <h5 class="mb-0"><?= $tableLabelEsc ?></h5>
                                <?php if ($tableName !== ''): ?>
                                    <div class="text-muted small"><?= $tableNameEsc ?></div>
                                <?php endif; ?>
                                <span class="app-status" data-color="<?= $statusColor ?>"><?= ucfirst($status) ?></span>
                                <div class="text-muted small">
                                    <i class="bi bi-people me-1"></i><?= (int)($table['capacity'] ?? 0) ?> seats
                                </div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="restaurant-column">
            <div class="app-card h-100 d-flex flex-column">
                <div class="section-heading">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-receipt me-2"></i>Active Orders</h6>
                        <span class="text-muted small"><?= $totalActive ?> in progress</span>
                    </div>
                </div>
                <?php if (empty($activeOrders)): ?>
                    <div class="order-empty">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2 mb-0">No active orders</p>
                        <small>New orders will appear here as soon as they are placed.</small>
                    </div>
                <?php else: ?>
                    <div class="order-list">
                        <?php foreach ($activeOrders as $order): ?>
                            <?php
                                $orderStatus = strtolower((string)($order['status'] ?? 'pending'));
                                $statusLabel = ucwords(str_replace('_', ' ', $orderStatus));
                                $statusColor = match ($orderStatus) {
                                    'pending', 'placed' => 'warning',
                                    'in_progress', 'preparing' => 'info',
                                    'ready', 'ready_for_pickup' => 'success',
                                    default => 'secondary'
                                };
                                $orderUrl = 'restaurant-order.php?id=' . (int)$order['id'];
                                $totalItems = (int)($order['total_items'] ?? 0);
                                $itemsLabel = $totalItems === 1 ? 'item' : 'items';
                                $orderIcon = $order['order_type'] === 'dine-in' ? 'table' : 'bag';
                            ?>
                            <div class="order-card" role="button" tabindex="0" data-order-url="<?= $orderUrl ?>">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div class="stack-sm">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-<?= $orderIcon ?>"></i>
                                            <h6 class="mb-0"><?= htmlspecialchars($order['order_number']) ?></h6>
                                        </div>
                                        <div class="text-muted small">
                                            <?php if ($order['order_type'] === 'dine-in'): ?>
                                                Table <?= htmlspecialchars($order['table_number'] ?? 'N/A') ?>
                                            <?php else: ?>
                                                Takeout • <?= formatDate($order['created_at'], 'd M H:i') ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <span class="app-status" data-color="<?= $statusColor ?>"><?= $statusLabel ?></span>
                                            <span class="badge-soft"><?= $totalItems ?> <?= $itemsLabel ?></span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <strong class="text-primary">KES <?= formatMoney($order['total_amount']) ?></strong>
                                        <div class="text-muted small"><?= formatDate($order['created_at'], 'H:i') ?></div>
                                    </div>
                                </div>
                                <div class="order-card-actions mt-3">
                                    <button type="button"
                                            class="btn btn-outline-secondary btn-sm"
                                            onclick="event.stopPropagation(); window.open('print-kitchen-order.php?id=<?= (int)$order['id'] ?>', '_blank', 'width=400,height=600');">
                                        <i class="bi bi-printer"></i> Kitchen Ticket
                                    </button>
                                    <button type="button"
                                            class="btn btn-outline-primary btn-sm"
                                            onclick="event.stopPropagation(); window.location.href = '<?= $orderUrl ?>';">
                                        <i class="bi bi-box-arrow-in-right"></i> Open
                                    </button>
                                </div>
                            </div>
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
