<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

// Check admin permission - simplified for now
$user = $auth->getUser();
if (!$user || !in_array($user['role'], ['admin', 'developer', 'super_admin'])) {
    $_SESSION['error_message'] = 'You do not have permission to manage void settings. Admin role required.';
    redirectToDashboard($auth);
}

$db = Database::getInstance();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation for all POST actions on this page
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Invalid request. Please try again.';
        redirect($_SERVER['PHP_SELF']);
    }
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_void_settings') {
            $settings = [
                'void_time_limit_minutes' => (int)$_POST['void_time_limit_minutes'],
                'require_manager_approval_amount' => (float)$_POST['require_manager_approval_amount'],
                'auto_adjust_inventory' => isset($_POST['auto_adjust_inventory']) ? '1' : '0',
                'print_void_receipt' => isset($_POST['print_void_receipt']) ? '1' : '0',
                'allow_partial_void' => isset($_POST['allow_partial_void']) ? '1' : '0',
                'void_notification_email' => sanitizeInput($_POST['void_notification_email']),
                'void_daily_limit' => (int)$_POST['void_daily_limit'],
                'void_audit_retention_days' => (int)$_POST['void_audit_retention_days']
            ];
            
            foreach ($settings as $key => $value) {
                $db->query("
                    UPDATE void_settings 
                    SET setting_value = ? 
                    WHERE setting_key = ?
                ", [$value, $key]);
            }
            
            $_SESSION['success_message'] = 'Void settings updated successfully';
            redirect($_SERVER['PHP_SELF']);
        }
        
        if ($action === 'add_void_reason') {
            $code = strtoupper(sanitizeInput($_POST['code']));
            $displayName = sanitizeInput($_POST['display_name']);
            $description = sanitizeInput($_POST['description']);
            $requiresApproval = isset($_POST['requires_manager_approval']) ? 1 : 0;
            $affectsInventory = isset($_POST['affects_inventory']) ? 1 : 0;
            
            $db->insert('void_reason_codes', [
                'code' => $code,
                'display_name' => $displayName,
                'description' => $description,
                'requires_manager_approval' => $requiresApproval,
                'affects_inventory' => $affectsInventory,
                'is_active' => 1
            ]);
            
            $_SESSION['success_message'] = 'Void reason code added successfully';
            redirect($_SERVER['PHP_SELF']);
        }
        
        if ($action === 'update_void_reason') {
            $id = (int)$_POST['reason_id'];
            $displayName = sanitizeInput($_POST['display_name']);
            $description = sanitizeInput($_POST['description']);
            $requiresApproval = isset($_POST['requires_manager_approval']) ? 1 : 0;
            $affectsInventory = isset($_POST['affects_inventory']) ? 1 : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            $db->update('void_reason_codes', [
                'display_name' => $displayName,
                'description' => $description,
                'requires_manager_approval' => $requiresApproval,
                'affects_inventory' => $affectsInventory,
                'is_active' => $isActive
            ], 'id = :id', ['id' => $id]);
            
            $_SESSION['success_message'] = 'Void reason code updated successfully';
            redirect($_SERVER['PHP_SELF']);
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
}

// Get current settings
$voidSettings = [];
$settingsResult = $db->fetchAll("SELECT setting_key, setting_value FROM void_settings");
foreach ($settingsResult as $setting) {
    $voidSettings[$setting['setting_key']] = $setting['setting_value'];
}

// Get void reason codes
$voidReasons = $db->fetchAll("SELECT * FROM void_reason_codes ORDER BY display_order, display_name");

// Get void statistics
$voidStats = [
    'total_voids_month' => $db->fetchOne("SELECT COUNT(*) as count FROM void_transactions WHERE MONTH(void_timestamp) = MONTH(NOW())")['count'] ?? 0,
    'total_void_amount_month' => $db->fetchOne("SELECT SUM(original_total) as total FROM void_transactions WHERE MONTH(void_timestamp) = MONTH(NOW())")['total'] ?? 0,
    'most_common_reason' => $db->fetchOne("
        SELECT vrc.display_name, COUNT(*) as count 
        FROM void_transactions vt 
        JOIN void_reason_codes vrc ON vt.void_reason_code = vrc.code 
        WHERE MONTH(vt.void_timestamp) = MONTH(NOW()) 
        GROUP BY vt.void_reason_code 
        ORDER BY count DESC 
        LIMIT 1
    "),
    'avg_void_amount' => $db->fetchOne("SELECT AVG(original_total) as avg FROM void_transactions WHERE MONTH(void_timestamp) = MONTH(NOW())")['avg'] ?? 0
];

$pageTitle = 'Void Order Settings';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5><i class="bi bi-gear me-2"></i>Void Order Settings</h5>
                    <small>Configure void order policies and reason codes</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row g-3 mb-4 mt-2">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-x-circle text-danger fs-1 mb-2"></i>
                    <h3 class="text-danger"><?= $voidStats['total_voids_month'] ?></h3>
                    <p class="text-muted mb-0">Voids This Month</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-currency-dollar text-warning fs-1 mb-2"></i>
                    <h3 class="text-warning"><?= formatMoney($voidStats['total_void_amount_month'], false) ?></h3>
                    <p class="text-muted mb-0">Void Amount This Month</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-bar-chart text-info fs-1 mb-2"></i>
                    <h3 class="text-info"><?= formatMoney($voidStats['avg_void_amount'], false) ?></h3>
                    <p class="text-muted mb-0">Average Void Amount</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-exclamation-triangle text-secondary fs-1 mb-2"></i>
                    <div class="text-secondary">
                        <small><?= htmlspecialchars($voidStats['most_common_reason']['display_name'] ?? 'N/A') ?></small>
                    </div>
                    <p class="text-muted mb-0">Most Common Reason</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Void Settings -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-sliders"></i> Void Order Policies</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_void_settings">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="void_time_limit_minutes" class="form-label">Time Limit (Minutes)</label>
                                    <input type="number" class="form-control" name="void_time_limit_minutes" 
                                           value="<?= $voidSettings['void_time_limit_minutes'] ?? 60 ?>" min="1" max="1440">
                                    <small class="text-muted">Orders older than this require manager approval</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="require_manager_approval_amount" class="form-label">Manager Approval Amount</label>
                                    <input type="number" class="form-control" name="require_manager_approval_amount" 
                                           value="<?= $voidSettings['require_manager_approval_amount'] ?? 1000 ?>" step="0.01" min="0">
                                    <small class="text-muted">Orders above this amount require manager approval</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="void_daily_limit" class="form-label">Daily Void Limit</label>
                                    <input type="number" class="form-control" name="void_daily_limit" 
                                           value="<?= $voidSettings['void_daily_limit'] ?? 10 ?>" min="1" max="100">
                                    <small class="text-muted">Maximum voids per user per day</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="void_audit_retention_days" class="form-label">Audit Retention (Days)</label>
                                    <input type="number" class="form-control" name="void_audit_retention_days" 
                                           value="<?= $voidSettings['void_audit_retention_days'] ?? 365 ?>" min="30" max="3650">
                                    <small class="text-muted">How long to keep void transaction records</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="void_notification_email" class="form-label">Notification Email</label>
                            <input type="email" class="form-control" name="void_notification_email" 
                                   value="<?= $voidSettings['void_notification_email'] ?? '' ?>" placeholder="manager@restaurant.com">
                            <small class="text-muted">Email address to notify of void transactions</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="auto_adjust_inventory" 
                                           <?= ($voidSettings['auto_adjust_inventory'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label">
                                        Auto-adjust Inventory
                                    </label>
                                    <small class="d-block text-muted">Automatically return items to inventory when voided</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="print_void_receipt" 
                                           <?= ($voidSettings['print_void_receipt'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label">
                                        Print Void Receipt
                                    </label>
                                    <small class="d-block text-muted">Print confirmation receipt for voided orders</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="allow_partial_void" 
                                   <?= ($voidSettings['allow_partial_void'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label">
                                Allow Partial Voids
                            </label>
                            <small class="d-block text-muted">Allow voiding individual items from an order (Future feature)</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Update Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-lightning"></i> Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="void-order-management.php" class="btn btn-danger">
                            <i class="bi bi-x-circle me-2"></i>Void Orders
                        </a>
                        <a href="void-reports.php" class="btn btn-info">
                            <i class="bi bi-graph-up me-2"></i>Void Reports
                        </a>
                        <button class="btn btn-warning" onclick="exportVoidData()">
                            <i class="bi bi-download me-2"></i>Export Void Data
                        </button>
                        <button class="btn btn-secondary" onclick="cleanupOldVoids()">
                            <i class="bi bi-trash me-2"></i>Cleanup Old Records
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Void Reason Codes -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="bi bi-list-ul"></i> Void Reason Codes</h6>
                    <button class="btn btn-success btn-sm" onclick="addVoidReason()">
                        <i class="bi bi-plus-circle me-1"></i>Add Reason
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Display Name</th>
                                    <th>Description</th>
                                    <th>Manager Approval</th>
                                    <th>Affects Inventory</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($voidReasons as $reason): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($reason['code']) ?></code></td>
                                    <td><?= htmlspecialchars($reason['display_name']) ?></td>
                                    <td>
                                        <small><?= htmlspecialchars(substr($reason['description'], 0, 50)) ?><?= strlen($reason['description']) > 50 ? '...' : '' ?></small>
                                    </td>
                                    <td>
                                        <?php if ($reason['requires_manager_approval']): ?>
                                        <span class="badge bg-warning">Required</span>
                                        <?php else: ?>
                                        <span class="badge bg-success">Not Required</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($reason['affects_inventory']): ?>
                                        <span class="badge bg-info">Yes</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($reason['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-outline-primary btn-sm" onclick="editVoidReason(<?= $reason['id'] ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Void Reason Modal -->
<div class="modal fade" id="voidReasonModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="voidReasonModalTitle">Add Void Reason</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="voidReasonForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="voidReasonAction" value="add_void_reason">
                    <input type="hidden" name="reason_id" id="voidReasonId">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="code" id="voidReasonCode" required 
                               pattern="[A-Z_]+" title="Use uppercase letters and underscores only">
                        <small class="text-muted">Use uppercase letters and underscores (e.g., KITCHEN_ERROR)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="display_name" class="form-label">Display Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="display_name" id="voidReasonDisplayName" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="voidReasonDescription" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="requires_manager_approval" id="voidReasonManagerApproval">
                                <label class="form-check-label">
                                    Requires Manager Approval
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="affects_inventory" id="voidReasonAffectsInventory" checked>
                                <label class="form-check-label">
                                    Affects Inventory
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check mb-3" id="voidReasonActiveSection" style="display: none;">
                        <input class="form-check-input" type="checkbox" name="is_active" id="voidReasonActive" checked>
                        <label class="form-check-label">
                            Active
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="voidReasonSubmitBtn">
                        <i class="bi bi-check-circle me-2"></i>Add Reason
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addVoidReason() {
    document.getElementById('voidReasonModalTitle').textContent = 'Add Void Reason';
    document.getElementById('voidReasonAction').value = 'add_void_reason';
    document.getElementById('voidReasonSubmitBtn').innerHTML = '<i class="bi bi-check-circle me-2"></i>Add Reason';
    document.getElementById('voidReasonActiveSection').style.display = 'none';
    document.getElementById('voidReasonForm').reset();
    document.getElementById('voidReasonCode').readOnly = false;
    new bootstrap.Modal(document.getElementById('voidReasonModal')).show();
}

function editVoidReason(reasonId) {
    // This would typically fetch the reason data via AJAX
    // For now, we'll show the modal for editing
    document.getElementById('voidReasonModalTitle').textContent = 'Edit Void Reason';
    document.getElementById('voidReasonAction').value = 'update_void_reason';
    document.getElementById('voidReasonId').value = reasonId;
    document.getElementById('voidReasonSubmitBtn').innerHTML = '<i class="bi bi-check-circle me-2"></i>Update Reason';
    document.getElementById('voidReasonActiveSection').style.display = 'block';
    document.getElementById('voidReasonCode').readOnly = true;
    new bootstrap.Modal(document.getElementById('voidReasonModal')).show();
}

function exportVoidData() {
    if (confirm('Export void transaction data for the current month?')) {
        window.open('api/export-void-data.php?period=month', '_blank');
    }
}

function cleanupOldVoids() {
    if (confirm('This will permanently delete void records older than the retention period. Continue?')) {
        fetch('api/cleanup-void-records.php', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Cleanup completed: ' + data.message);
                } else {
                    alert('Cleanup failed: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
    }
}
</script>

<?php include 'includes/footer.php'; ?>
