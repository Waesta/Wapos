<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

// Check void permission - simplified for now
$user = $auth->getUser();
if (!$user || !in_array($user['role'], ['admin', 'manager', 'developer'])) {
    $_SESSION['error_message'] = 'You do not have permission to void orders. Manager or Admin role required.';
    redirect('index.php');
}

$db = Database::getInstance();

// Handle void order request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'void_order') {
            // CSRF validation
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid request. Please try again.');
            }
            $orderId = (int)$_POST['order_id'];
            $reasonCode = sanitizeInput($_POST['reason_code']);
            $reasonText = sanitizeInput($_POST['reason_text'] ?? '');
            $managerUserId = !empty($_POST['manager_user_id']) ? (int)$_POST['manager_user_id'] : null;
            
            // Validate inputs
            if (!$orderId || !$reasonCode) {
                throw new Exception('Order ID and reason code are required');
            }
            
            // Check if order exists and can be voided
            $order = $db->fetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
            if (!$order) {
                throw new Exception('Order not found');
            }
            
            if ($order['status'] === 'voided') {
                throw new Exception('Order is already voided');
            }
            
            if ($order['status'] === 'completed') {
                throw new Exception('Cannot void completed orders');
            }
            
            // Check if manager approval is required
            $reasonInfo = $db->fetchOne("SELECT * FROM void_reason_codes WHERE code = ?", [$reasonCode]);
            if ($reasonInfo['requires_manager_approval'] && !$managerUserId) {
                throw new Exception('Manager approval is required for this void reason');
            }
            
            // Check void time limit
            $voidTimeLimit = $db->fetchOne("SELECT setting_value FROM void_settings WHERE setting_key = 'void_time_limit_minutes'")['setting_value'] ?? 60;
            $orderAge = (time() - strtotime($order['created_at'])) / 60; // in minutes
            
            if ($orderAge > $voidTimeLimit && !$managerUserId) {
                throw new Exception("Orders older than {$voidTimeLimit} minutes require manager approval");
            }
            
            // Check daily void limit
            $dailyLimit = $db->fetchOne("SELECT setting_value FROM void_settings WHERE setting_key = 'void_daily_limit'")['setting_value'] ?? 10;
            $todayVoids = $db->fetchOne("
                SELECT COUNT(*) as count 
                FROM void_transactions 
                WHERE voided_by_user_id = ? AND DATE(void_timestamp) = CURDATE()
            ", [$auth->getUserId()])['count'] ?? 0;
            
            if ($todayVoids >= $dailyLimit && !$managerUserId) {
                throw new Exception("Daily void limit of {$dailyLimit} reached. Manager approval required.");
            }
            
            // Process the void
            $result = voidOrder($orderId, $reasonCode, $reasonText, $auth->getUserId(), $managerUserId, $db);
            
            if ($result['success']) {
                try {
                    $db->insert('permission_audit_log', [
                        'user_id' => $auth->getUserId(),
                        'action_type' => 'void_order',
                        'module_id' => null,
                        'action_id' => null,
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                        'session_id' => session_id(),
                        'risk_level' => 'medium',
                        'additional_data' => json_encode(['details' => "Voided order #{$order['order_number']} - Reason: {$reasonCode}"])
                    ]);
                } catch (Exception $e) {}
                
                $_SESSION['success_message'] = $result['message'];
                
                // Print void receipt if enabled
                $printVoidReceipt = $db->fetchOne("SELECT setting_value FROM void_settings WHERE setting_key = 'print_void_receipt'")['setting_value'] ?? '1';
                if ($printVoidReceipt === '1') {
                    $_SESSION['print_void_receipt'] = $orderId;
                }
            } else {
                throw new Exception($result['message']);
            }
            
            redirect($_SERVER['PHP_SELF']);
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
}

// Get recent orders that can be voided
$voidableOrders = $db->fetchAll("
    SELECT o.*, 
           COUNT(oi.id) as item_count,
           u.username as created_by_username,
           TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) as age_minutes
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.status IN ('pending', 'confirmed', 'preparing', 'ready')
    AND o.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 50
");

// Get void reason codes
$voidReasons = $db->fetchAll("
    SELECT * FROM void_reason_codes 
    WHERE is_active = 1 
    ORDER BY display_order, display_name
");

// Get void statistics
$voidStats = [
    'today_voids' => $db->fetchOne("SELECT COUNT(*) as count FROM void_transactions WHERE DATE(void_timestamp) = CURDATE()")['count'] ?? 0,
    'week_voids' => $db->fetchOne("SELECT COUNT(*) as count FROM void_transactions WHERE void_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['count'] ?? 0,
    'user_today_voids' => $db->fetchOne("SELECT COUNT(*) as count FROM void_transactions WHERE voided_by_user_id = ? AND DATE(void_timestamp) = CURDATE()", [$auth->getUserId()])['count'] ?? 0,
    'total_void_amount_today' => $db->fetchOne("SELECT SUM(original_total) as total FROM void_transactions WHERE DATE(void_timestamp) = CURDATE()")['total'] ?? 0
];

// Get recent void transactions
$recentVoids = $db->fetchAll("
    SELECT vt.*, vrc.display_name as reason_name, u.username
    FROM void_transactions vt
    JOIN void_reason_codes vrc ON vt.void_reason_code = vrc.code
    JOIN users u ON vt.voided_by_user_id = u.id
    ORDER BY vt.void_timestamp DESC
    LIMIT 20
");

$pageTitle = 'Void Order Management';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5><i class="bi bi-x-circle me-2"></i>Void Order Management</h5>
                    <small>Void orders with proper authorization and audit trail</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Void Statistics -->
    <div class="row g-3 mb-4 mt-2">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-x-circle text-danger fs-1 mb-2"></i>
                    <h3 class="text-danger"><?= $voidStats['today_voids'] ?></h3>
                    <p class="text-muted mb-0">Voids Today</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-calendar-week text-warning fs-1 mb-2"></i>
                    <h3 class="text-warning"><?= $voidStats['week_voids'] ?></h3>
                    <p class="text-muted mb-0">Voids This Week</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-person-x text-info fs-1 mb-2"></i>
                    <h3 class="text-info"><?= $voidStats['user_today_voids'] ?></h3>
                    <p class="text-muted mb-0">Your Voids Today</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-currency-dollar text-secondary fs-1 mb-2"></i>
                    <h3 class="text-secondary"><?= formatMoney($voidStats['total_void_amount_today'], false) ?></h3>
                    <p class="text-muted mb-0">Void Amount Today</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Voidable Orders -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-list-ul"></i> Orders Available for Void</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($voidableOrders)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-1 mb-3"></i>
                        <p>No orders available for void</p>
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
                                    <th>Age</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($voidableOrders as $order): ?>
                                <tr>
                                    <td>
                                        <strong>#<?= htmlspecialchars($order['order_number']) ?></strong>
                                        <?php if ($order['order_type'] === 'delivery'): ?>
                                        <br><small class="text-info"><i class="bi bi-truck"></i> Delivery</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($order['customer_name'] ?: 'Walk-in') ?></strong>
                                            <?php if ($order['customer_phone']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($order['customer_phone']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark"><?= $order['item_count'] ?> items</span>
                                    </td>
                                    <td>
                                        <strong><?= formatMoney($order['total_amount'], false) ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'pending' => 'warning',
                                            'confirmed' => 'info',
                                            'preparing' => 'primary',
                                            'ready' => 'success'
                                        ];
                                        $statusColor = $statusColors[$order['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($order['status']) ?></span>
                                    </td>
                                    <td>
                                        <small>
                                            <?= $order['age_minutes'] ?> min ago
                                            <?php if ($order['age_minutes'] > 60): ?>
                                            <br><span class="text-warning">⚠️ Requires approval</span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <button class="btn btn-danger btn-sm" onclick="voidOrder(<?= $order['id'] ?>, '<?= htmlspecialchars($order['order_number']) ?>', <?= $order['total_amount'] ?>, <?= $order['age_minutes'] ?>)">
                                            <i class="bi bi-x-circle"></i> Void
                                        </button>
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

        <!-- Recent Voids -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-clock-history"></i> Recent Void Transactions</h6>
                </div>
                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                    <?php if (empty($recentVoids)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-x-circle fs-1 mb-3"></i>
                        <p>No recent voids</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($recentVoids as $void): ?>
                    <div class="void-item mb-3 p-3 border rounded">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong>#<?= htmlspecialchars($void['order_number']) ?></strong>
                                <br>
                                <small class="text-muted"><?= formatMoney($void['original_total'], false) ?></small>
                            </div>
                            <small class="text-muted"><?= timeAgo($void['void_timestamp']) ?></small>
                        </div>
                        
                        <div class="mb-2">
                            <span class="badge bg-danger"><?= htmlspecialchars($void['reason_name']) ?></span>
                        </div>
                        
                        <div class="mb-1">
                            <small class="text-muted">
                                <i class="bi bi-person me-1"></i><?= htmlspecialchars($void['username']) ?>
                            </small>
                        </div>
                        
                        <?php if ($void['void_reason_text']): ?>
                        <div class="mb-1">
                            <small class="text-muted">"<?= htmlspecialchars(substr($void['void_reason_text'], 0, 50)) ?>..."</small>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Void Order Modal -->
<div class="modal fade" id="voidOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Void Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="void_order">
                    <input type="hidden" name="order_id" id="voidOrderId">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone. The order will be permanently voided.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><strong>Order Details:</strong></label>
                        <div id="orderDetails" class="p-2 bg-light rounded"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason_code" class="form-label">Void Reason <span class="text-danger">*</span></label>
                        <select class="form-select" name="reason_code" id="reason_code" required onchange="checkManagerApproval()">
                            <option value="">Select reason...</option>
                            <?php foreach ($voidReasons as $reason): ?>
                            <option value="<?= $reason['code'] ?>" data-requires-approval="<?= $reason['requires_manager_approval'] ? '1' : '0' ?>">
                                <?= htmlspecialchars($reason['display_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason_text" class="form-label">Additional Details</label>
                        <textarea class="form-control" name="reason_text" id="reason_text" rows="3" placeholder="Optional additional details about the void..."></textarea>
                    </div>
                    
                    <div id="managerApprovalSection" class="mb-3" style="display: none;">
                        <div class="alert alert-info">
                            <i class="bi bi-shield-check me-2"></i>
                            Manager approval is required for this void reason.
                        </div>
                        <label for="manager_user_id" class="form-label">Manager Authorization</label>
                        <select class="form-select" name="manager_user_id" id="manager_user_id">
                            <option value="">Select manager...</option>
                            <?php
                            $managers = $db->fetchAll("
                                SELECT u.id, u.username, u.full_name 
                                FROM users u 
                                JOIN user_roles ur ON u.id = ur.user_id 
                                JOIN roles r ON ur.role_id = r.id 
                                WHERE r.name IN ('Manager', 'Admin') AND u.is_active = 1
                            ");
                            foreach ($managers as $manager):
                            ?>
                            <option value="<?= $manager['id'] ?>"><?= htmlspecialchars($manager['full_name'] ?: $manager['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle me-2"></i>Void Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function voidOrder(orderId, orderNumber, totalAmount, ageMinutes) {
    document.getElementById('voidOrderId').value = orderId;
    document.getElementById('orderDetails').innerHTML = `
        <strong>Order #:</strong> ${orderNumber}<br>
        <strong>Amount:</strong> ${formatMoney(totalAmount)}<br>
        <strong>Age:</strong> ${ageMinutes} minutes
        ${ageMinutes > 60 ? '<br><span class="text-warning">⚠️ Requires manager approval due to age</span>' : ''}
    `;
    
    new bootstrap.Modal(document.getElementById('voidOrderModal')).show();
}

function checkManagerApproval() {
    const reasonSelect = document.getElementById('reason_code');
    const selectedOption = reasonSelect.options[reasonSelect.selectedIndex];
    const requiresApproval = selectedOption.getAttribute('data-requires-approval') === '1';
    const managerSection = document.getElementById('managerApprovalSection');
    const managerSelect = document.getElementById('manager_user_id');
    
    if (requiresApproval) {
        managerSection.style.display = 'block';
        managerSelect.required = true;
    } else {
        managerSection.style.display = 'none';
        managerSelect.required = false;
    }
}

function formatMoney(amount) {
    return parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
</script>

<?php 
function voidOrder($orderId, $reasonCode, $reasonText, $voidedByUserId, $managerUserId, $db) {
    try {
        // Call stored procedure
        $stmt = $db->prepare("CALL VoidOrder(?, ?, ?, ?, ?, @success, @message)");
        $stmt->execute([$orderId, $reasonCode, $reasonText, $voidedByUserId, $managerUserId]);
        
        // Get results
        $result = $db->fetchOne("SELECT @success as success, @message as message");
        
        return [
            'success' => (bool)$result['success'],
            'message' => $result['message']
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

include 'includes/footer.php'; 
?>
