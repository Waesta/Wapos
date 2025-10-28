<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'confirm_whatsapp_order') {
            $orderId = $_POST['order_id'];
            $totalAmount = $_POST['total_amount'];
            $deliveryFee = $_POST['delivery_fee'] ?? 0;
            $notes = sanitizeInput($_POST['notes'] ?? '');
            
            // Update order with confirmed details
            $db->update('orders', [
                'total_amount' => $totalAmount,
                'delivery_fee' => $deliveryFee,
                'status' => 'confirmed',
                'notes' => $notes,
                'confirmed_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $orderId]);
            
            // Send confirmation message to customer
            $order = $db->fetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
            if ($order) {
                sendOrderConfirmation($order, $db);
            }
            
            $_SESSION['success_message'] = 'WhatsApp order confirmed successfully';
            redirect($_SERVER['PHP_SELF']);
        }
        
        if ($action === 'reject_whatsapp_order') {
            $orderId = $_POST['order_id'];
            $reason = sanitizeInput($_POST['reason']);
            
            $db->update('orders', [
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $orderId]);
            
            // Send rejection message to customer
            $order = $db->fetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
            if ($order) {
                sendOrderRejection($order, $reason, $db);
            }
            
            $_SESSION['success_message'] = 'WhatsApp order rejected';
            redirect($_SERVER['PHP_SELF']);
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
}

// Get WhatsApp orders
$whatsappOrders = $db->fetchAll("
    SELECT o.*, 
           COUNT(oi.id) as item_count,
           GROUP_CONCAT(CONCAT(oi.quantity, 'x ', oi.product_name) SEPARATOR ', ') as items_summary
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.order_source = 'whatsapp'
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 50
");

// Get recent WhatsApp messages
$recentMessages = $db->fetchAll("
    SELECT wm.*, 
           o.order_number,
           ROW_NUMBER() OVER (PARTITION BY wm.customer_phone ORDER BY wm.created_at DESC) as rn
    FROM whatsapp_messages wm
    LEFT JOIN orders o ON wm.customer_phone = o.customer_phone AND o.order_source = 'whatsapp'
    WHERE wm.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOURS)
    ORDER BY wm.created_at DESC
    LIMIT 100
");

$pageTitle = 'WhatsApp Orders Management';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5><i class="bi bi-whatsapp me-2"></i>WhatsApp Orders Management</h5>
                    <small>Manage orders received via WhatsApp</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4 mt-2">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-clock text-warning fs-1 mb-2"></i>
                    <h3 class="text-warning">
                        <?= count(array_filter($whatsappOrders, fn($o) => $o['status'] === 'pending')) ?>
                    </h3>
                    <p class="text-muted mb-0">Pending Orders</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle text-success fs-1 mb-2"></i>
                    <h3 class="text-success">
                        <?= count(array_filter($whatsappOrders, fn($o) => $o['status'] === 'confirmed')) ?>
                    </h3>
                    <p class="text-muted mb-0">Confirmed Today</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-currency-dollar text-info fs-1 mb-2"></i>
                    <h3 class="text-info">
                        <?= formatMoney(array_sum(array_map(fn($o) => $o['total_amount'], array_filter($whatsappOrders, fn($o) => $o['status'] !== 'cancelled')))) ?>
                    </h3>
                    <p class="text-muted mb-0">Total Value</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-chat-dots text-primary fs-1 mb-2"></i>
                    <h3 class="text-primary"><?= count($recentMessages) ?></h3>
                    <p class="text-muted mb-0">Messages (24h)</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Orders List -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-list-ul"></i> WhatsApp Orders</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($whatsappOrders)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-1 mb-3"></i>
                        <p>No WhatsApp orders yet</p>
                        <small>Orders will appear here when customers place them via WhatsApp</small>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($whatsappOrders as $order): ?>
                                <tr>
                                    <td>
                                        <strong>#<?= htmlspecialchars($order['order_number']) ?></strong>
                                        <?php if ($order['order_type'] === 'delivery'): ?>
                                        <br><small class="text-info"><i class="bi bi-truck"></i> Delivery</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($order['customer_name'] ?: 'Unknown') ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <i class="bi bi-whatsapp text-success"></i>
                                                <?= htmlspecialchars($order['customer_phone']) ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($order['items_summary'] ?: 'No items') ?></small>
                                        <br>
                                        <span class="badge bg-light text-dark"><?= $order['item_count'] ?> items</span>
                                    </td>
                                    <td>
                                        <?php if ($order['total_amount'] > 0): ?>
                                        <strong><?= formatMoney($order['total_amount']) ?></strong>
                                        <?php else: ?>
                                        <span class="text-muted">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'pending' => 'warning',
                                            'confirmed' => 'success',
                                            'preparing' => 'info',
                                            'ready' => 'primary',
                                            'completed' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        $statusColor = $statusColors[$order['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($order['status']) ?></span>
                                    </td>
                                    <td>
                                        <small><?= timeAgo($order['created_at']) ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($order['status'] === 'pending'): ?>
                                            <button class="btn btn-outline-success" onclick="confirmOrder(<?= $order['id'] ?>)">
                                                <i class="bi bi-check"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="rejectOrder(<?= $order['id'] ?>)">
                                                <i class="bi bi-x"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button class="btn btn-outline-primary" onclick="viewOrderDetails(<?= $order['id'] ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="sendMessage('<?= $order['customer_phone'] ?>')">
                                                <i class="bi bi-whatsapp"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Messages -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-chat-dots"></i> Recent Messages</h6>
                </div>
                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                    <?php if (empty($recentMessages)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-chat-square fs-1 mb-3"></i>
                        <p>No recent messages</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($recentMessages as $message): ?>
                    <div class="message-item mb-3 p-2 border rounded">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <small class="text-muted">
                                <i class="bi bi-whatsapp text-success"></i>
                                <?= substr($message['customer_phone'], -4) ?>
                            </small>
                            <small class="text-muted"><?= timeAgo($message['created_at']) ?></small>
                        </div>
                        
                        <div class="message-content">
                            <?php if ($message['message_type'] === 'inbound'): ?>
                            <div class="alert alert-light py-2 mb-1">
                                <small><?= htmlspecialchars(substr($message['message_text'], 0, 100)) ?></small>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-success py-2 mb-1">
                                <small><i class="bi bi-arrow-right me-1"></i><?= htmlspecialchars(substr($message['message_text'], 0, 100)) ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($message['order_number']): ?>
                        <small class="text-info">Order: #<?= $message['order_number'] ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Order Confirmation Modal -->
<div class="modal fade" id="confirmOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm WhatsApp Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="confirm_whatsapp_order">
                    <input type="hidden" name="order_id" id="confirmOrderId">
                    
                    <div class="mb-3">
                        <label for="total_amount" class="form-label">Total Amount (KES)</label>
                        <input type="number" class="form-control" name="total_amount" id="total_amount" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="delivery_fee" class="form-label">Delivery Fee (KES)</label>
                        <input type="number" class="form-control" name="delivery_fee" id="delivery_fee" step="0.01" value="0">
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Order Rejection Modal -->
<div class="modal fade" id="rejectOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject WhatsApp Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject_whatsapp_order">
                    <input type="hidden" name="order_id" id="rejectOrderId">
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Rejection</label>
                        <select class="form-select" name="reason" id="reason" required>
                            <option value="">Select reason...</option>
                            <option value="Items not available">Items not available</option>
                            <option value="Outside delivery area">Outside delivery area</option>
                            <option value="Kitchen closed">Kitchen closed</option>
                            <option value="Invalid order">Invalid order</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmOrder(orderId) {
    document.getElementById('confirmOrderId').value = orderId;
    new bootstrap.Modal(document.getElementById('confirmOrderModal')).show();
}

function rejectOrder(orderId) {
    document.getElementById('rejectOrderId').value = orderId;
    new bootstrap.Modal(document.getElementById('rejectOrderModal')).show();
}

function viewOrderDetails(orderId) {
    window.open('order-details.php?id=' + orderId, '_blank');
}

function sendMessage(phone) {
    const message = prompt('Enter message to send:');
    if (!message) return;
    
    fetch('api/send-whatsapp-message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            phone: phone,
            message: message
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Message sent successfully!');
            location.reload();
        } else {
            alert('Failed to send message: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

// Auto-refresh every 30 seconds
setInterval(() => {
    location.reload();
}, 30000);
</script>

<?php 
function sendOrderConfirmation($order, $db) {
    $message = "✅ *Order Confirmed!*\n\n";
    $message .= "📋 Order #" . $order['order_number'] . "\n";
    $message .= "💰 Total: KES " . number_format($order['total_amount'], 2) . "\n";
    $message .= "⏰ Estimated time: 30-45 minutes\n\n";
    
    if ($order['order_type'] === 'delivery') {
        $message .= "🚚 Your order will be delivered to:\n";
        $message .= $order['customer_address'] . "\n\n";
    } else {
        $message .= "🏪 Ready for pickup at our location\n\n";
    }
    
    $message .= "We'll keep you updated on your order status!\n";
    $message .= "Thank you for choosing us! 🙏";
    
    // Send via WhatsApp API (implement sendWhatsAppMessage function)
    // sendWhatsAppMessage($order['customer_phone'], $message, $db);
}

function sendOrderRejection($order, $reason, $db) {
    $message = "❌ *Order Update*\n\n";
    $message .= "We're sorry, but we cannot process your order #" . $order['order_number'] . "\n\n";
    $message .= "📝 Reason: " . $reason . "\n\n";
    $message .= "Please feel free to place a new order or contact us for assistance.\n";
    $message .= "📞 Call us: [phone number]\n\n";
    $message .= "Thank you for understanding! 🙏";
    
    // Send via WhatsApp API
    // sendWhatsAppMessage($order['customer_phone'], $message, $db);
}

include 'includes/footer.php'; 
?>
