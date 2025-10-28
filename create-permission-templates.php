<?php
/**
 * Create Permission Templates
 * Creates predefined permission templates for common roles
 */

require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance();

// Handle template creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create_template') {
            $templateType = $_POST['template_type'];
            $userId = $_POST['user_id'];
            
            // Get user info
            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // Clear existing permissions for this user
            $db->execute("UPDATE user_permissions SET is_active = 0 WHERE user_id = ?", [$userId]);
            
            // Define template permissions
            $templates = [
                'cashier' => [
                    'pos' => ['create', 'read'],
                    'customers' => ['create', 'read', 'update'],
                    'products' => ['read'],
                    'sales' => ['create', 'read']
                ],
                'waiter' => [
                    'restaurant' => ['create', 'read', 'update'],
                    'pos' => ['create', 'read'],
                    'customers' => ['create', 'read', 'update'],
                    'products' => ['read'],
                    'rooms' => ['read', 'update']
                ],
                'inventory_manager' => [
                    'inventory' => ['create', 'read', 'update', 'delete', 'manage'],
                    'products' => ['create', 'read', 'update', 'delete'],
                    'reports' => ['read'],
                    'accounting' => ['read']
                ],
                'manager' => [
                    'pos' => ['create', 'read', 'update', 'delete'],
                    'restaurant' => ['create', 'read', 'update', 'delete'],
                    'inventory' => ['create', 'read', 'update', 'delete'],
                    'customers' => ['create', 'read', 'update', 'delete'],
                    'products' => ['create', 'read', 'update', 'delete'],
                    'sales' => ['create', 'read', 'update'],
                    'reports' => ['read'],
                    'rooms' => ['create', 'read', 'update', 'delete'],
                    'delivery' => ['create', 'read', 'update', 'delete']
                ]
            ];
            
            if (!isset($templates[$templateType])) {
                throw new Exception('Invalid template type');
            }
            
            $permissions = $templates[$templateType];
            $permissionsCreated = 0;
            
            // Create permissions
            foreach ($permissions as $moduleKey => $actions) {
                // Get module ID
                $module = $db->fetchOne("SELECT id FROM permission_modules WHERE module_key = ?", [$moduleKey]);
                if (!$module) continue;
                
                foreach ($actions as $actionKey) {
                    // Get action ID
                    $action = $db->fetchOne("SELECT id FROM permission_actions WHERE module_id = ? AND action_key = ?", [$module['id'], $actionKey]);
                    if (!$action) continue;
                    
                    // Create permission
                    $db->execute("
                        INSERT INTO user_permissions (user_id, module_id, action_id, granted_by, is_active) 
                        VALUES (?, ?, ?, ?, 1)
                    ", [$userId, $module['id'], $action['id'], $auth->getUserId()]);
                    
                    $permissionsCreated++;
                }
            }
            
            showAlert("Template '{$templateType}' applied to {$user['full_name']} - {$permissionsCreated} permissions created", 'success');
        }
        
    } catch (Exception $e) {
        showAlert('Error: ' . $e->getMessage(), 'error');
    }
    
    redirect($_SERVER['PHP_SELF']);
}

// Get data
try {
    $users = $db->fetchAll("SELECT id, username, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name") ?: [];
    $modules = $db->fetchAll("SELECT * FROM permission_modules WHERE is_active = 1 ORDER BY module_name") ?: [];
    $actions = $db->fetchAll("SELECT * FROM permission_actions WHERE is_active = 1 ORDER BY action_name") ?: [];
} catch (Exception $e) {
    $users = [];
    $modules = [];
    $actions = [];
}

$pageTitle = 'Create Permission Templates';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">
            <i class="bi bi-collection me-2"></i>Permission Templates
        </h4>
        <p class="text-muted mb-0">Apply predefined permission templates to users</p>
    </div>
    <div>
        <a href="permissions.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back to Permissions
        </a>
    </div>
</div>

<!-- Template Cards -->
<div class="row g-4 mb-4">
    <!-- Cashier Template -->
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0">
                    <i class="bi bi-cash-coin me-2"></i>Cashier Template
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Perfect for front-desk cashiers and sales staff</p>
                <div class="mb-3">
                    <strong>Permissions Include:</strong>
                    <ul class="small mt-2">
                        <li><span class="badge bg-primary me-1">POS</span> Create & View sales</li>
                        <li><span class="badge bg-info me-1">Customers</span> Manage customer info</li>
                        <li><span class="badge bg-secondary me-1">Products</span> View product catalog</li>
                        <li><span class="badge bg-warning me-1">Sales</span> Process transactions</li>
                    </ul>
                </div>
                <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#templateModal" 
                        onclick="setTemplate('cashier', 'Cashier')">
                    <i class="bi bi-person-plus me-2"></i>Apply Template
                </button>
            </div>
        </div>
    </div>
    
    <!-- Waiter Template -->
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0">
                    <i class="bi bi-cup-hot me-2"></i>Waiter Template
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Ideal for restaurant service staff and waiters</p>
                <div class="mb-3">
                    <strong>Permissions Include:</strong>
                    <ul class="small mt-2">
                        <li><span class="badge bg-primary me-1">Restaurant</span> Full table management</li>
                        <li><span class="badge bg-success me-1">POS</span> Process orders</li>
                        <li><span class="badge bg-info me-1">Customers</span> Manage guests</li>
                        <li><span class="badge bg-secondary me-1">Products</span> View menu items</li>
                        <li><span class="badge bg-dark me-1">Rooms</span> View & update rooms</li>
                    </ul>
                </div>
                <button class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#templateModal" 
                        onclick="setTemplate('waiter', 'Waiter')">
                    <i class="bi bi-person-plus me-2"></i>Apply Template
                </button>
            </div>
        </div>
    </div>
    
    <!-- Inventory Manager Template -->
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">
                    <i class="bi bi-boxes me-2"></i>Inventory Manager Template
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Complete inventory and stock management access</p>
                <div class="mb-3">
                    <strong>Permissions Include:</strong>
                    <ul class="small mt-2">
                        <li><span class="badge bg-info me-1">Inventory</span> Full stock control</li>
                        <li><span class="badge bg-secondary me-1">Products</span> Manage product catalog</li>
                        <li><span class="badge bg-primary me-1">Reports</span> View inventory reports</li>
                        <li><span class="badge bg-success me-1">Accounting</span> View financial data</li>
                    </ul>
                </div>
                <button class="btn btn-info w-100" data-bs-toggle="modal" data-bs-target="#templateModal" 
                        onclick="setTemplate('inventory_manager', 'Inventory Manager')">
                    <i class="bi bi-person-plus me-2"></i>Apply Template
                </button>
            </div>
        </div>
    </div>
    
    <!-- Manager Template -->
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">
                    <i class="bi bi-person-badge me-2"></i>Manager Template
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Comprehensive management access for supervisors</p>
                <div class="mb-3">
                    <strong>Permissions Include:</strong>
                    <ul class="small mt-2">
                        <li><span class="badge bg-primary me-1">POS</span> Full POS management</li>
                        <li><span class="badge bg-warning me-1">Restaurant</span> Complete F&B control</li>
                        <li><span class="badge bg-info me-1">Inventory</span> Stock management</li>
                        <li><span class="badge bg-success me-1">Reports</span> Business analytics</li>
                        <li><span class="badge bg-secondary me-1">And More...</span> 9 modules total</li>
                    </ul>
                </div>
                <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#templateModal" 
                        onclick="setTemplate('manager', 'Manager')">
                    <i class="bi bi-person-plus me-2"></i>Apply Template
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Current Users -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h6 class="mb-0">Current Users</h6>
    </div>
    <div class="card-body">
        <?php if (empty($users)): ?>
        <div class="text-center py-4">
            <i class="bi bi-people text-muted fs-1 mb-3"></i>
            <h5 class="text-muted">No Users Found</h5>
            <p class="text-muted">Create users first before applying permission templates.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Username</th>
                        <th>Current Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                        </td>
                        <td>
                            <code><?= htmlspecialchars($user['username']) ?></code>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?= htmlspecialchars($user['role']) ?></span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#templateModal" 
                                        onclick="setTemplate('cashier', 'Cashier', <?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')">
                                    Cashier
                                </button>
                                <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#templateModal" 
                                        onclick="setTemplate('waiter', 'Waiter', <?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')">
                                    Waiter
                                </button>
                                <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#templateModal" 
                                        onclick="setTemplate('inventory_manager', 'Inventory Manager', <?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')">
                                    Inventory
                                </button>
                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#templateModal" 
                                        onclick="setTemplate('manager', 'Manager', <?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')">
                                    Manager
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

<!-- Template Application Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Apply Permission Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_template">
                    <input type="hidden" name="template_type" id="modalTemplateType">
                    
                    <div class="mb-3">
                        <label class="form-label">Template</label>
                        <input type="text" class="form-control" id="modalTemplateName" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Apply to User</label>
                        <select name="user_id" class="form-select" id="modalUserId" required>
                            <option value="">Select User</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name']) ?> (<?= $user['username'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This will replace all existing permissions for the selected user.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setTemplate(templateType, templateName, userId = null, userName = null) {
    document.getElementById('modalTemplateType').value = templateType;
    document.getElementById('modalTemplateName').value = templateName;
    
    if (userId) {
        document.getElementById('modalUserId').value = userId;
    }
}
</script>

<?php include 'includes/footer.php'; ?>
