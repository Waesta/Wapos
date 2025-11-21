<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

// Get pending and preparing orders
$orders = $db->fetchAll("
    SELECT o.*, rt.table_number, rt.table_name,
           COUNT(oi.id) as total_items,
           SUM(CASE WHEN oi.status = 'ready' THEN 1 ELSE 0 END) as ready_items
    FROM orders o
    LEFT JOIN restaurant_tables rt ON o.table_id = rt.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.status IN ('pending', 'preparing', 'ready') 
    AND o.order_type IN ('dine-in', 'takeout')
    GROUP BY o.id
    ORDER BY o.created_at ASC
");

// Get order items grouped by order
$orderItems = [];
if (!empty($orders)) {
    $orderIds = array_column($orders, 'id');
    $items = $db->fetchAll("
        SELECT oi.*, p.name as product_name
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id IN (" . implode(',', $orderIds) . ")
        ORDER BY oi.id ASC
    ");
    
    foreach ($items as $item) {
        $orderItems[$item['order_id']][] = $item;
    }
}

$pageTitle = 'Kitchen Display System';
include 'includes/header.php';
?>

<style>
    .kitchen-shell {
        min-height: 100vh;
        background: var(--color-surface-subtle);
        padding: var(--spacing-lg) 0 var(--spacing-xl);
    }
    .kitchen-toolbar {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: var(--spacing-md);
        align-items: center;
        margin-bottom: var(--spacing-xl);
    }
    .kitchen-toolbar h1 {
        font-size: var(--text-2xl);
        margin: 0;
    }
    .kitchen-toolbar p {
        margin: 0;
        color: var(--color-text-muted);
    }
    .kitchen-toolbar .btn-icon {
        display: inline-flex;
        align-items: center;
        gap: var(--spacing-xs);
    }
    .kitchen-stats-grid {
        display: grid;
        gap: var(--spacing-md);
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        margin-bottom: var(--spacing-xl);
    }
    .kitchen-stat-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
        padding: var(--spacing-md);
        text-align: center;
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xs);
    }
    .kitchen-stat-card h3 {
        margin: 0;
        font-size: var(--text-2xl);
        font-weight: 700;
    }
    .kitchen-orders-grid {
        display: grid;
        gap: var(--spacing-lg);
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    }
    .kitchen-order-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        background: var(--color-surface);
        box-shadow: var(--shadow-md);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: transform var(--transition-base), box-shadow var(--transition-base);
    }
    .kitchen-order-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    .kitchen-order-header {
        padding: var(--spacing-md);
        display: flex;
        justify-content: space-between;
        gap: var(--spacing-md);
        background: linear-gradient(135deg, var(--color-primary-500), var(--color-primary-700));
        color: var(--color-white);
    }
    .kitchen-order-header h2 {
        margin: 0;
        font-size: var(--text-lg);
        font-weight: 600;
    }
    .kitchen-order-meta {
        font-size: var(--text-sm);
        opacity: 0.85;
    }
    .kitchen-order-timer .timer-badge {
        font-family: 'JetBrains Mono', 'Courier New', monospace;
        font-weight: 700;
        font-size: var(--text-base);
        padding: var(--spacing-xs) var(--spacing-sm);
        border-radius: var(--radius-pill);
        background: rgba(255, 255, 255, 0.12);
        color: var(--color-white);
        display: inline-flex;
        align-items: center;
        gap: var(--spacing-xs);
    }
    .kitchen-order-body {
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-md);
    }
    .kitchen-item-card {
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--radius-md);
        padding: var(--spacing-sm) var(--spacing-md);
        display: flex;
        justify-content: space-between;
        gap: var(--spacing-md);
        background: var(--color-surface-subtle);
    }
    .kitchen-item-card[data-status="pending"] {
        border-left: 4px solid var(--color-warning-500);
    }
    .kitchen-item-card[data-status="preparing"] {
        border-left: 4px solid var(--color-info-500);
    }
    .kitchen-item-card[data-status="ready"] {
        border-left: 4px solid var(--color-success-500);
    }
    .kitchen-item-title {
        font-size: var(--text-md);
        font-weight: 600;
        margin-bottom: var(--spacing-xs);
    }
    .kitchen-item-modifiers {
        display: flex;
        flex-wrap: wrap;
        gap: var(--spacing-xs);
    }
    .kitchen-item-modifiers .badge {
        background: var(--color-surface);
        border: 1px solid var(--color-border-subtle);
        color: var(--color-text-muted);
        font-size: var(--text-xs);
        padding: 0.25rem 0.5rem;
    }
    .kitchen-item-actions {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xs);
    }
    .kitchen-progress {
        border-top: 1px solid var(--color-border-subtle);
        padding-top: var(--spacing-sm);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    .kitchen-progress small {
        color: var(--color-text-muted);
    }
    .kitchen-progress .progress {
        height: 6px;
        border-radius: var(--radius-pill);
    }
    .kitchen-empty {
        text-align: center;
        padding: var(--spacing-2xl) 0;
        color: var(--color-text-muted);
    }
</style>

<div class="kitchen-shell">
    <div class="container-fluid">
        <header class="kitchen-toolbar">
            <div class="stack-sm">
                <h1><i class="bi bi-fire text-danger"></i> Kitchen Display</h1>
                <p>Track tickets and handoff ready dishes without leaving the line.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary btn-icon" onclick="refreshOrders()">
                    <i class="bi bi-arrow-clockwise"></i>Refresh
                </button>
                <button class="btn btn-outline-secondary btn-icon" onclick="toggleSound()">
                    <i class="bi bi-volume-up" id="soundIcon"></i>Sound
                </button>
            </div>
        </header>

        <section class="kitchen-stats-grid">
            <article class="kitchen-stat-card">
                <i class="bi bi-clock-history text-warning fs-2"></i>
                <h3 id="pendingCount"><?= count(array_filter($orders, fn($o) => $o['status'] === 'pending')) ?></h3>
                <span class="text-muted">Waiting</span>
            </article>
            <article class="kitchen-stat-card">
                <i class="bi bi-fire text-info fs-2"></i>
                <h3 id="preparingCount"><?= count(array_filter($orders, fn($o) => $o['status'] === 'preparing')) ?></h3>
                <span class="text-muted">In Kitchen</span>
            </article>
            <article class="kitchen-stat-card">
                <i class="bi bi-check-circle text-success fs-2"></i>
                <h3 id="readyCount"><?= count(array_filter($orders, fn($o) => $o['status'] === 'ready')) ?></h3>
                <span class="text-muted">Ready to Serve</span>
            </article>
            <article class="kitchen-stat-card">
                <i class="bi bi-speedometer2 text-primary fs-2"></i>
                <h3 id="avgTime">--</h3>
                <span class="text-muted">Avg Prep Time</span>
            </article>
        </section>

        <section id="ordersGrid" class="kitchen-orders-grid">
            <?php if (empty($orders)): ?>
                <div class="kitchen-empty">
                    <i class="bi bi-inbox fs-1"></i>
                    <h4 class="mt-3 mb-0">No Active Orders</h4>
                    <p>Kitchen is all caught up!</p>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <article class="kitchen-order-card" data-order-id="<?= $order['id'] ?>" data-status="<?= htmlspecialchars($order['status']) ?>">
                        <div class="kitchen-order-header">
                            <div class="stack-xs">
                                <h2><?= htmlspecialchars($order['order_number']) ?></h2>
                                <div class="kitchen-order-meta">
                                    <?php if ($order['order_type'] === 'dine-in'): ?>
                                        <i class="bi bi-table"></i> Table <?= htmlspecialchars($order['table_number'] ?? 'N/A') ?>
                                    <?php else: ?>
                                        <i class="bi bi-bag-check"></i> Takeout<?= $order['customer_name'] ? ' • ' . htmlspecialchars($order['customer_name']) : '' ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="kitchen-order-timer text-end">
                                <span class="timer-badge" data-time="<?= $order['created_at'] ?>">
                                    <i class="bi bi-stopwatch"></i> 00:00
                                </span>
                                <div class="mt-2">
                                    <span class="app-status" data-color="<?= $order['status'] === 'pending' ? 'warning' : ($order['status'] === 'preparing' ? 'info' : 'success') ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="kitchen-order-body">
                            <?php if (isset($orderItems[$order['id']])): ?>
                                <?php foreach ($orderItems[$order['id']] as $item): ?>
                                    <div class="kitchen-item-card" data-status="<?= htmlspecialchars($item['status']) ?>">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-dark text-white"><?= (float)$item['quantity'] ?>x</span>
                                                <div class="kitchen-item-title mb-0"><?= htmlspecialchars($item['product_name']) ?></div>
                                            </div>
                                            <?php if ($item['modifiers_data']): ?>
                                                <?php $modifiers = json_decode($item['modifiers_data'], true); ?>
                                                <?php if ($modifiers): ?>
                                                    <div class="kitchen-item-modifiers mt-2">
                                                        <?php foreach ($modifiers as $mod): ?>
                                                            <span class="badge">+ <?= htmlspecialchars($mod['name']) ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($item['special_instructions']): ?>
                                                <div class="alert alert-warning py-1 px-2 mt-2 mb-0">
                                                    <small><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($item['special_instructions']) ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="kitchen-item-actions">
                                            <?php if ($item['status'] === 'pending'): ?>
                                                <button class="btn btn-info btn-sm" onclick="updateItemStatus(<?= $item['id'] ?>, 'preparing')">
                                                    <i class="bi bi-play-fill"></i> Start
                                                </button>
                                            <?php elseif ($item['status'] === 'preparing'): ?>
                                                <button class="btn btn-success btn-sm" onclick="updateItemStatus(<?= $item['id'] ?>, 'ready')">
                                                    <i class="bi bi-check"></i> Ready
                                                </button>
                                            <?php else: ?>
                                                <span class="app-status" data-color="success">Ready</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <div class="kitchen-progress">
                                <small>Progress: <?= $order['ready_items'] ?>/<?= $order['total_items'] ?> items ready</small>
                                <div class="progress">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $order['total_items'] > 0 ? ($order['ready_items'] / $order['total_items']) * 100 : 0 ?>%"></div>
                                </div>
                                <?php if ($order['ready_items'] == $order['total_items'] && $order['status'] !== 'ready'): ?>
                                    <button class="btn btn-success btn-sm align-self-end" onclick="completeOrder(<?= $order['id'] ?>)">
                                        <i class="bi bi-check-circle"></i> Complete Order
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</div>

<!-- Audio for notifications -->
<audio id="notificationSound" preload="auto">
    <source src="assets/sounds/notification.mp3" type="audio/mpeg">
    <source src="assets/sounds/notification.wav" type="audio/wav">
</audio>

<script>
let soundEnabled = true;
let lastOrderCount = <?= count($orders) ?>;

// Auto-refresh every 30 seconds
setInterval(refreshOrders, 30000);

// Update timers every second
setInterval(updateTimers, 1000);

function updateTimers() {
    document.querySelectorAll('.timer-badge').forEach(badge => {
        const orderTime = new Date(badge.dataset.time);
        const now = new Date();
        const diff = Math.floor((now - orderTime) / 1000);
        
        const minutes = Math.floor(diff / 60);
        const seconds = diff % 60;
        
        badge.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        // Change color based on time
        if (minutes >= 15) {
            badge.className = 'timer-badge badge bg-danger text-white';
        } else if (minutes >= 10) {
            badge.className = 'timer-badge badge bg-warning text-dark';
        } else {
            badge.className = 'timer-badge badge bg-light text-dark';
        }
    });
}

async function updateItemStatus(itemId, status) {
    try {
        const response = await fetch('api/update-order-item-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: itemId, status: status })
        });
        
        const result = await response.json();
        
        if (result.success) {
            refreshOrders();
            if (soundEnabled) {
                playNotificationSound();
            }
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function completeOrder(orderId) {
    try {
        const response = await fetch('api/complete-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            refreshOrders();
            if (soundEnabled) {
                playNotificationSound();
            }
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function refreshOrders() {
    try {
        const response = await fetch('api/get-kitchen-orders.php');
        const result = await response.json();
        
        if (result.success) {
            // Check for new orders
            if (result.data.orders.length > lastOrderCount) {
                if (soundEnabled) {
                    playNotificationSound();
                }
            }
            lastOrderCount = result.data.orders.length;
            
            // Update stats
            document.getElementById('pendingCount').textContent = result.data.stats.pending;
            document.getElementById('preparingCount').textContent = result.data.stats.preparing;
            document.getElementById('readyCount').textContent = result.data.stats.ready;
            document.getElementById('avgTime').textContent = result.data.stats.avg_time;
            
            // Update orders grid (simplified - in production, you'd update the DOM more efficiently)
            if (result.data.orders.length !== document.querySelectorAll('[data-order-id]').length) {
                location.reload(); // Simple refresh for now
            }
        }
    } catch (error) {
        console.error('Error refreshing orders:', error);
    }
}

function toggleSound() {
    soundEnabled = !soundEnabled;
    const icon = document.getElementById('soundIcon');
    icon.className = soundEnabled ? 'bi bi-volume-up me-2' : 'bi bi-volume-mute me-2';
}

function playNotificationSound() {
    const audio = document.getElementById('notificationSound');
    audio.play().catch(e => console.log('Could not play sound:', e));
}

// Initialize timers
updateTimers();
</script>

<?php include 'includes/footer.php'; ?>
