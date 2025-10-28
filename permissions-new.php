<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

require_once 'includes/PermissionManager.php';
$permissionManager = new PermissionManager($auth->getUserId());

$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'grant_permission') {
            $userId = $_POST['user_id'];
            $moduleKey = $_POST['module_key'];
            $actionKey = $_POST['action_key'];
            $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
            $reason = $_POST['reason'] ?? '';
            
            $conditions = [];
            if (!empty($_POST['time_start'])) {
                $conditions['time_restrictions']['start_hour'] = (int)$_POST['time_start'];
            }
            if (!empty($_POST['time_end'])) {
                $conditions['time_restrictions']['end_hour'] = (int)$_POST['time_end'];
            }
            if (!empty($_POST['amount_limit'])) {
                $conditions['amount_limit'] = (float)$_POST['amount_limit'];
            }
            
            $permissionManager->grantPermission(
                $userId, 
                $moduleKey, 
                $actionKey, 
                $auth->getUserId(),
                !empty($conditions) ? $conditions : null,
                $expiresAt,
                $reason
            );
            
            showAlert('Permission granted successfully', 'success');
        }
        
        elseif ($action === 'revoke_permission') {
            $userId = $_POST['user_id'];
            $moduleKey = $_POST['module_key'];
            $actionKey = $_POST['action_key'];
            $reason = $_POST['reason'] ?? '';
            
            $permissionManager->revokePermission($userId, $moduleKey, $actionKey, $reason);
            
            showAlert('Permission revoked successfully', 'success');
        }
        
    } catch (Exception $e) {
        showAlert('Error: ' . $e->getMessage(), 'error');
    }
    
    redirect($_SERVER['PHP_SELF']);
}

// Get data for display with error handling (NO SystemManager references)
try {
    $users = $db->fetchAll("SELECT id, username, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name") ?: [];
    
    // Get permission modules
    $modules = $db->fetchAll("SELECT * FROM permission_modules WHERE is_active = 1 ORDER BY module_name") ?: [];
    
    // Get permission actions
    $actions = $db->fetchAll("
        SELECT pa.*, pm.module_name 
        FROM permission_actions pa 
        JOIN permission_modules pm ON pa.module_id = pm.id 
        WHERE pa.is_active = 1 AND pm.is_active = 1 
        ORDER BY pm.module_name, pa.action_name
    ") ?: [];
    
    // Get user permissions
    $userPermissions = $db->fetchAll("
        SELECT up.*, pm.module_key, pa.action_key, u.username 
        FROM user_permissions up 
        JOIN permission_modules pm ON up.module_id = pm.id 
        JOIN permission_actions pa ON up.action_id = pa.id 
        JOIN users u ON up.user_id = u.id 
        WHERE up.is_active = 1 
        ORDER BY u.username, pm.module_name, pa.action_name
    ") ?: [];
    
} catch (Exception $e) {
    error_log('Permissions data loading error: ' . $e->getMessage());
    $users = [];
    $modules = [];
    $actions = [];
    $userPermissions = [];
}

// Get selected user's permissions for matrix display
$selectedUserId = $_GET['user_id'] ?? ($users[0]['id'] ?? null);
$permissionMatrix = [];
if ($selectedUserId) {
    try {
        // Build permission matrix directly from database
        $userSpecificPermissions = $db->fetchAll("
            SELECT pm.module_key, pm.module_name, pa.action_key, pa.action_name
            FROM user_permissions up
            JOIN permission_modules pm ON up.module_id = pm.id
            JOIN permission_actions pa ON up.action_id = pa.id
            WHERE up.user_id = ? AND up.is_active = 1
        ", [$selectedUserId]) ?: [];
        
        // Create matrix structure
        foreach ($modules as $module) {
            $moduleKey = $module['module_key'];
            $permissionMatrix[$moduleKey] = [
                'module_name' => $module['module_name'],
                'module_key' => $moduleKey,
                'icon' => 'bi bi-gear',
                'actions' => []
            ];
            
            // Check each action for this module
            foreach ($actions as $action) {
                if ($action['module_id'] == $module['id']) {
                    $hasPermission = false;
                    foreach ($userSpecificPermissions as $userPerm) {
                        if ($userPerm['module_key'] == $moduleKey && $userPerm['action_key'] == $action['action_key']) {
                            $hasPermission = true;
                            break;
                        }
                    }
                    
                    $permissionMatrix[$moduleKey]['actions'][$action['action_key']] = [
                        'has_permission' => $hasPermission,
                        'is_sensitive' => false
                    ];
                }
            }
        }
    } catch (Exception $e) {
        $permissionMatrix = [];
    }
}

$pageTitle = 'Permission Management';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">
            <i class="bi bi-shield-lock me-2"></i>Permission Management
        </h4>
        <p class="text-muted mb-0">Manage user permissions and access control</p>
    </div>
    <div class="btn-group">
        <a href="create-permission-templates.php" class="btn btn-success">
            <i class="bi bi-collection me-2"></i>Permission Templates
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#grantPermissionModal">
            <i class="bi bi-plus-circle me-2"></i>Grant Permission
        </button>
    </div>
</div>

<!-- Permission Overview Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-people text-primary fs-1 mb-2"></i>
                <h3 class="text-primary"><?= count($users) ?></h3>
                <p class="text-muted mb-0">Active Users</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-grid-3x3-gap text-success fs-1 mb-2"></i>
                <h3 class="text-success"><?= count($modules) ?></h3>
                <p class="text-muted mb-0">Permission Modules</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-lightning text-warning fs-1 mb-2"></i>
                <h3 class="text-warning"><?= count($actions) ?></h3>
                <p class="text-muted mb-0">Available Actions</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-check-circle text-info fs-1 mb-2"></i>
                <h3 class="text-info"><?= count($userPermissions) ?></h3>
                <p class="text-muted mb-0">Active Permissions</p>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#user-permissions">
            <i class="bi bi-person-check me-2"></i>User Permissions
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#permission-matrix">
            <i class="bi bi-grid me-2"></i>Permission Matrix
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- User Permissions Tab -->
    <div class="tab-pane fade show active" id="user-permissions">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">Current User Permissions</h6>
            </div>
            <div class="card-body">
                <?php if (empty($userPermissions)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-shield-x text-muted fs-1 mb-3"></i>
                    <h5 class="text-muted">No Permissions Assigned</h5>
                    <p class="text-muted">Start by granting permissions to users or apply permission templates.</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#grantPermissionModal">
                            <i class="bi bi-plus-circle me-2"></i>Grant Permission
                        </button>
                        <a href="create-permission-templates.php" class="btn btn-success">
                            <i class="bi bi-collection me-2"></i>Use Templates
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Module</th>
                                <th>Action</th>
                                <th>Granted</th>
                                <th>Expires</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userPermissions as $permission): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($permission['username'] ?? 'Unknown') ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= htmlspecialchars($permission['module_key'] ?? 'Unknown') ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($permission['action_key'] ?? 'Unknown') ?></span>
                                </td>
                                <td>
                                    <small class="text-muted"><?= $permission['granted_at'] ? date('d/m/Y H:i', strtotime($permission['granted_at'])) : 'Unknown' ?></small>
                                </td>
                                <td>
                                    <?php if ($permission['expires_at']): ?>
                                    <small class="text-warning"><?= date('d/m/Y H:i', strtotime($permission['expires_at'])) ?></small>
                                    <?php else: ?>
                                    <small class="text-success">Never</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger" onclick="revokePermission(<?= $permission['user_id'] ?>, '<?= $permission['module_key'] ?>', '<?= $permission['action_key'] ?>')">
                                        <i class="bi bi-x-circle"></i>
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
    
    <!-- Permission Matrix Tab -->
    <div class="tab-pane fade" id="permission-matrix">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Permission Matrix</h6>
                <select class="form-select w-auto" onchange="location.href='?user_id=' + this.value">
                    <option value="">Select User</option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?= $user['id'] ?>" <?= $selectedUserId == $user['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user['full_name']) ?> (<?= $user['username'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="card-body">
                <?php if ($selectedUserId && !empty($permissionMatrix)): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <?php foreach ($actions as $action): ?>
                                <th class="text-center">
                                    <?= htmlspecialchars($action['action_name'] ?? $action['action_key'] ?? 'Unknown') ?>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permissionMatrix as $moduleKey => $module): ?>
                            <tr>
                                <td>
                                    <i class="<?= $module['icon'] ?> me-2"></i>
                                    <?= htmlspecialchars($module['module_name']) ?>
                                </td>
                                <?php foreach ($actions as $action): ?>
                                <?php 
                                $hasPermission = $module['actions'][$action['action_key']]['has_permission'] ?? false;
                                ?>
                                <td class="text-center">
                                    <i class="bi bi-<?= $hasPermission ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?>"></i>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-grid text-muted fs-1 mb-3"></i>
                    <h5 class="text-muted">Select a User</h5>
                    <p class="text-muted">Choose a user from the dropdown to view their permission matrix.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Grant Permission Modal -->
<div class="modal fade" id="grantPermissionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Grant Permission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="grant_permission">
                    
                    <div class="mb-3">
                        <label class="form-label">User</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">Select User</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name']) ?> (<?= $user['username'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Module</label>
                        <select name="module_key" class="form-select" required>
                            <option value="">Select Module</option>
                            <?php foreach ($modules as $module): ?>
                            <option value="<?= $module['module_key'] ?>"><?= htmlspecialchars($module['module_name'] ?? $module['module_key'] ?? 'Unknown') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Action</label>
                        <select name="action_key" class="form-select" required>
                            <option value="">Select Action</option>
                            <?php foreach ($actions as $action): ?>
                            <option value="<?= $action['action_key'] ?>"><?= htmlspecialchars($action['action_name'] ?? $action['action_key'] ?? 'Unknown') ?> (<?= $action['module_name'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Expires At (Optional)</label>
                        <input type="datetime-local" name="expires_at" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Reason for granting this permission..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Grant Permission</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function revokePermission(userId, moduleKey, actionKey) {
    if (confirm('Are you sure you want to revoke this permission?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="revoke_permission">
            <input type="hidden" name="user_id" value="${userId}">
            <input type="hidden" name="module_key" value="${moduleKey}">
            <input type="hidden" name="action_key" value="${actionKey}">
            <input type="hidden" name="reason" value="Revoked via admin interface">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
