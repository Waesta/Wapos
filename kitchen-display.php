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
    .kitchen-container {
        background: #f8f9fa;
        min-height: 100vh;
        padding: 20px 0;
    }
    .order-card {
        transition: all 0.3s ease;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .order-pending { border-left: 5px solid #ffc107; }
    .order-preparing { border-left: 5px solid #17a2b8; }
    .order-ready { border-left: 5px solid #28a745; }
    
    .item-card {
        background: white;
        border-radius: 8px;
        margin-bottom: 10px;
        padding: 12px;
        border-left: 4px solid #dee2e6;
        transition: all 0.2s ease;
    }
    .item-pending { border-left-color: #ffc107; }
    .item-preparing { border-left-color: #17a2b8; }
    .item-ready { border-left-color: #28a745; }
    
    .timer-badge {
        font-family: 'Courier New', monospace;
        font-weight: bold;
    }
    
    .order-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px 12px 0 0;
        padding: 15px 20px;
    }
    
    .status-buttons .btn {
        margin: 2px;
        border-radius: 20px;
        font-size: 12px;
        padding: 4px 12px;
    }
    
    .progress-ring {
        width: 60px;
        height: 60px;
    }
    
    .kitchen-stats {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    @media (max-width: 768px) {
        .order-card {
            margin-bottom: 15px;
        }
        .kitchen-container {
            padding: 10px;
        }
    }
</style>

<div class="kitchen-container">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">
                    <i class="bi bi-fire text-danger me-2"></i>Kitchen Display System
                </h2>
                <p class="text-muted mb-0">Real-time order management</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="refreshOrders()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                </button>
                <button class="btn btn-outline-secondary" onclick="toggleSound()">
                    <i class="bi bi-volume-up me-2" id="soundIcon"></i>Sound
                </button>
            </div>
        </div>

        <!-- Kitchen Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="kitchen-stats text-center">
                    <i class="bi bi-clock-history text-warning fs-1"></i>
                    <h3 class="mt-2 mb-0" id="pendingCount"><?= count(array_filter($orders, fn($o) => $o['status'] === 'pending')) ?></h3>
                    <p class="text-muted mb-0">Pending</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kitchen-stats text-center">
                    <i class="bi bi-fire text-info fs-1"></i>
                    <h3 class="mt-2 mb-0" id="preparingCount"><?= count(array_filter($orders, fn($o) => $o['status'] === 'preparing')) ?></h3>
                    <p class="text-muted mb-0">Preparing</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kitchen-stats text-center">
                    <i class="bi bi-check-circle text-success fs-1"></i>
                    <h3 class="mt-2 mb-0" id="readyCount"><?= count(array_filter($orders, fn($o) => $o['status'] === 'ready')) ?></h3>
                    <p class="text-muted mb-0">Ready</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="kitchen-stats text-center">
                    <i class="bi bi-speedometer2 text-primary fs-1"></i>
                    <h3 class="mt-2 mb-0" id="avgTime">--</h3>
                    <p class="text-muted mb-0">Avg Time</p>
                </div>
            </div>
        </div>

        <!-- Orders Grid -->
        <div class="row" id="ordersGrid">
            <?php if (empty($orders)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <h4 class="mt-3 text-muted">No Active Orders</h4>
                        <p class="text-muted">Kitchen is all caught up!</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                <div class="col-lg-4 col-md-6" data-order-id="<?= $order['id'] ?>">
                    <div class="order-card order-<?= $order['status'] ?>">
                        <!-- Order Header -->
                        <div class="order-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-1"><?= htmlspecialchars($order['order_number']) ?></h5>
                                    <p class="mb-0 opacity-75">
                                        <?php if ($order['order_type'] === 'dine-in'): ?>
                                            <i class="bi bi-table me-1"></i>Table <?= htmlspecialchars($order['table_number'] ?? 'N/A') ?>
                                        <?php else: ?>
                                            <i class="bi bi-bag-check me-1"></i>Takeout
                                            <?php if ($order['customer_name']): ?>
                                                - <?= htmlspecialchars($order['customer_name']) ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="text-end">
                                    <div class="timer-badge badge bg-light text-dark" data-time="<?= $order['created_at'] ?>">
                                        00:00
                                    </div>
                                    <div class="mt-1">
                                        <span class="badge bg-<?= $order['status'] === 'pending' ? 'warning' : ($order['status'] === 'preparing' ? 'info' : 'success') ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="p-3">
                            <?php if (isset($orderItems[$order['id']])): ?>
                                <?php foreach ($orderItems[$order['id']] as $item): ?>
                                <div class="item-card item-<?= $item['status'] ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <span class="badge bg-secondary me-2"><?= $item['quantity'] ?>x</span>
                                                <?= htmlspecialchars($item['product_name']) ?>
                                            </h6>
                                            
                                            <?php if ($item['modifiers_data']): ?>
                                                <?php $modifiers = json_decode($item['modifiers_data'], true); ?>
                                                <?php if ($modifiers): ?>
                                                    <div class="mb-2">
                                                        <?php foreach ($modifiers as $mod): ?>
                                                            <small class="badge bg-light text-dark me-1">+ <?= htmlspecialchars($mod['name']) ?></small>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <?php if ($item['special_instructions']): ?>
                                                <div class="alert alert-warning py-1 px-2 mb-2">
                                                    <small><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($item['special_instructions']) ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="status-buttons">
                                            <?php if ($item['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-info" onclick="updateItemStatus(<?= $item['id'] ?>, 'preparing')">
                                                    <i class="bi bi-play-fill"></i> Start
                                                </button>
                                            <?php elseif ($item['status'] === 'preparing'): ?>
                                                <button class="btn btn-sm btn-success" onclick="updateItemStatus(<?= $item['id'] ?>, 'ready')">
                                                    <i class="bi bi-check"></i> Ready
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle"></i> Ready
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <!-- Order Actions -->
                            <div class="mt-3 pt-3 border-top">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted">
                                            Progress: <?= $order['ready_items'] ?>/<?= $order['total_items'] ?> items ready
                                        </small>
                                        <div class="progress mt-1" style="height: 4px;">
                                            <div class="progress-bar bg-success" style="width: <?= $order['total_items'] > 0 ? ($order['ready_items'] / $order['total_items']) * 100 : 0 ?>%"></div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($order['ready_items'] == $order['total_items'] && $order['status'] !== 'ready'): ?>
                                        <button class="btn btn-success btn-sm" onclick="completeOrder(<?= $order['id'] ?>)">
                                            <i class="bi bi-check-circle me-1"></i>Complete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
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
