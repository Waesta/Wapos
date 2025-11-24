<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

require_once 'includes/PermissionManager.php';
$permissionManager = new PermissionManager($auth->getUserId());

$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation for all POST actions on this page
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Invalid request. Please try again.';
        redirect($_SERVER['PHP_SELF']);
    }
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
            
            $_SESSION['success_message'] = 'Permission granted successfully';
        }
        
        elseif ($action === 'revoke_permission') {
            $userId = $_POST['user_id'];
            $moduleKey = $_POST['module_key'];
            $actionKey = $_POST['action_key'];
            $reason = $_POST['reason'] ?? '';
            
            $permissionManager->revokePermission($userId, $moduleKey, $actionKey, $auth->getUserId(), $reason);
            $_SESSION['success_message'] = 'Permission revoked successfully';
        }
        
        elseif ($action === 'add_to_group') {
            $userId = $_POST['user_id'];
            $groupId = $_POST['group_id'];
            $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
            
            $permissionManager->addUserToGroup($userId, $groupId, $auth->getUserId(), $expiresAt);
            $_SESSION['success_message'] = 'User added to group successfully';
        }
        
        elseif ($action === 'remove_from_group') {
            $userId = $_POST['user_id'];
            $groupId = $_POST['group_id'];
            $reason = $_POST['reason'] ?? '';
            
            $permissionManager->removeUserFromGroup($userId, $groupId, $auth->getUserId(), $reason);
            $_SESSION['success_message'] = 'User removed from group successfully';
        }
        
        elseif ($action === 'create_group') {
            $data = [
                'name' => sanitizeInput($_POST['name']),
                'description' => sanitizeInput($_POST['description']),
                'color' => $_POST['color'] ?? '#007bff',
                'created_by' => $auth->getUserId()
            ];
            
            $groupId = $db->insert('permission_groups', $data);
            
            // Grant permissions to the new group
            if (!empty($_POST['permissions'])) {
                foreach ($_POST['permissions'] as $permission) {
                    list($moduleKey, $actionKey) = explode(':', $permission);
                    $moduleId = $db->fetchOne("SELECT id FROM system_modules WHERE module_key = ?", [$moduleKey])['id'];
                    $actionId = $db->fetchOne("SELECT id FROM system_actions WHERE action_key = ?", [$actionKey])['id'];
                    
                    $db->insert('group_permissions', [
                        'group_id' => $groupId,
                        'module_id' => $moduleId,
                        'action_id' => $actionId,
                        'is_granted' => 1,
                        'granted_by' => $auth->getUserId()
                    ]);
                }
            }
            
            $_SESSION['success_message'] = 'Permission group created successfully';
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
    
    redirect($_SERVER['PHP_SELF']);
}

// Get data for display with error handling
try {
    $users = $db->fetchAll("SELECT id, username, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name") ?: [];
    
    // Load active system modules
    $modules = $db->fetchAll("
        SELECT id, module_key, display_name AS module_name, icon
        FROM system_modules 
        WHERE is_active = 1 
        ORDER BY display_name
    ") ?: [];
    
    // Map of actions per module via module_actions bridge
    $moduleActionsRaw = $db->fetchAll("
        SELECT 
            ma.module_id,
            sm.module_key,
            sm.display_name AS module_name,
            sm.icon,
            sa.action_key,
            sa.display_name AS action_name,
            sa.is_sensitive
        FROM module_actions ma
        JOIN system_modules sm ON ma.module_id = sm.id
        JOIN system_actions sa ON ma.action_id = sa.id
        WHERE sm.is_active = 1
        ORDER BY sm.display_name, sa.display_name
    ") ?: [];
    
    $moduleActionsMap = [];
    $actions = [];
    foreach ($moduleActionsRaw as $row) {
        $moduleActionsMap[$row['module_key']][$row['action_key']] = $row;
        if (!isset($actions[$row['action_key']])) {
            $actions[$row['action_key']] = [
                'action_key' => $row['action_key'],
                'action_name' => $row['action_name'],
                'is_sensitive' => $row['is_sensitive']
            ];
        }
    }
    $actions = array_values($actions);
    
    // Get user permissions
    $userPermissions = $db->fetchAll("
        SELECT up.*, sm.module_key, sa.action_key, u.username 
        FROM user_permissions up 
        JOIN system_modules sm ON up.module_id = sm.id 
        JOIN system_actions sa ON up.action_id = sa.id 
        JOIN users u ON up.user_id = u.id 
        WHERE up.is_active = 1 
        ORDER BY u.username, sm.display_name, sa.display_name
    ") ?: [];
    
    // Define permission groups (role-based templates)
    $groups = [
        [
            'id' => 1,
            'name' => 'Administrator',
            'description' => 'Full system access with all permissions',
            'color' => '#dc3545',
            'permissions' => ['*'] // All permissions
        ],
        [
            'id' => 2,
            'name' => 'Manager',
            'description' => 'Business operations and reporting access',
            'color' => '#0d6efd',
            'permissions' => ['pos.*', 'restaurant.*', 'inventory.*', 'customers.*', 'sales.*', 'reports.*']
        ],
        [
            'id' => 3,
            'name' => 'Cashier',
            'description' => 'Point of sale operations only',
            'color' => '#198754',
            'permissions' => ['pos.create', 'pos.read', 'customers.read', 'customers.create']
        ],
        [
            'id' => 4,
            'name' => 'Waiter',
            'description' => 'Restaurant service operations',
            'color' => '#fd7e14',
            'permissions' => ['restaurant.*', 'customers.read', 'customers.create']
        ],
        [
            'id' => 5,
            'name' => 'Inventory Manager',
            'description' => 'Stock and inventory management',
            'color' => '#6f42c1',
            'permissions' => ['inventory.*', 'products.*', 'reports.inventory']
        ],
        [
            'id' => 6,
            'name' => 'Accountant',
            'description' => 'Financial and accounting access',
            'color' => '#20c997',
            'permissions' => ['accounting.*', 'reports.financial', 'sales.read']
        ]
    ];
    
} catch (Exception $e) {
    error_log('Permissions data loading error: ' . $e->getMessage());
    $users = [];
    $modules = [];
    $actions = [];
    $userPermissions = [];
    $groups = [];
}

// Get selected user's permissions for matrix display
$selectedUserId = $_GET['user_id'] ?? ($users[0]['id'] ?? null);
$permissionMatrix = [];
if ($selectedUserId) {
    try {
        // Build permission matrix directly from database
        $userSpecificPermissions = $db->fetchAll("
            SELECT sm.module_key, sa.action_key
            FROM user_permissions up
            JOIN system_modules sm ON up.module_id = sm.id
            JOIN system_actions sa ON up.action_id = sa.id
            WHERE up.user_id = ? AND up.is_active = 1
        ", [$selectedUserId]) ?: [];

        $userPermissionLookup = [];
        foreach ($userSpecificPermissions as $perm) {
            $userPermissionLookup[$perm['module_key'] . ':' . $perm['action_key']] = true;
        }
        
        foreach ($modules as $module) {
            $moduleKey = $module['module_key'];
            $permissionMatrix[$moduleKey] = [
                'module_name' => $module['module_name'],
                'module_key' => $moduleKey,
                'icon' => $module['icon'] ?? 'bi bi-gear',
                'actions' => []
            ];

            $moduleActionSet = $moduleActionsMap[$moduleKey] ?? [];
            foreach ($moduleActionSet as $actionKey => $actionMeta) {
                $permissionMatrix[$moduleKey]['actions'][$actionKey] = [
                    'has_permission' => isset($userPermissionLookup[$moduleKey . ':' . $actionKey]),
                    'is_sensitive' => (bool)($actionMeta['is_sensitive'] ?? false)
                ];
            }
        }
    } catch (Exception $e) {
        $permissionMatrix = [];
    }
}

$pageTitle = 'User Permissions Management';
include 'includes/header.php';
?>

<style>
.permission-matrix {
    font-size: 0.85rem;
}
.permission-matrix th {
    writing-mode: vertical-lr;
    text-orientation: mixed;
    min-width: 40px;
    padding: 8px 4px;
}
.permission-matrix td {
    text-align: center;
    padding: 4px;
}
.permission-granted {
    background-color: #d4edda;
    color: #155724;
}
.permission-denied {
    background-color: #f8d7da;
    color: #721c24;
}
.sensitive-action {
    background-color: #fff3cd;
    border-left: 3px solid #ffc107;
}
.audit-log {
    max-height: 400px;
    overflow-y: auto;
}
</style>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-shield-lock me-2"></i>User Permissions Management</h4>
    <div class="btn-group">
        <a href="create-permission-templates.php" class="btn btn-success">
            <i class="bi bi-collection me-2"></i>Permission Templates
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
            <i class="bi bi-plus-circle me-2"></i>Create Group
        </button>
        <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#auditLogModal">
            <i class="bi bi-clock-history me-2"></i>Audit Log
        </button>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs mb-4" id="permissionTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="matrix-tab" data-bs-toggle="tab" data-bs-target="#matrix" type="button">
            <i class="bi bi-grid me-2"></i>Permission Matrix
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="groups-tab" data-bs-toggle="tab" data-bs-target="#groups" type="button">
            <i class="bi bi-people me-2"></i>Permission Groups
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="individual-tab" data-bs-toggle="tab" data-bs-target="#individual" type="button">
            <i class="bi bi-person-gear me-2"></i>Individual Permissions
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button">
            <i class="bi bi-file-earmark-code me-2"></i>Permission Templates
        </button>
    </li>
</ul>

<div class="tab-content" id="permissionTabContent">
    <!-- Permission Matrix Tab -->
    <div class="tab-pane fade show active" id="matrix" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h6 class="mb-0"><i class="bi bi-grid me-2"></i>Permission Matrix</h6>
                    </div>
                    <div class="col-md-6">
                        <select class="form-select" id="userSelect" onchange="location.href='?user_id='+this.value">
                            <option value="">Select User</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= $selectedUserId == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['username']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if ($selectedUserId && !empty($permissionMatrix)): ?>
                <div class="table-responsive">
                    <table class="table table-sm permission-matrix mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="writing-mode: horizontal-tb;">Module</th>
                                <?php foreach ($actions as $action): ?>
                                <th class="<?= ($action['is_sensitive'] ?? false) ? 'sensitive-action' : '' ?>">
                                    <?= htmlspecialchars($action['action_name'] ?? $action['action_key'] ?? 'Unknown') ?>
                                    <?php if ($action['is_sensitive'] ?? false): ?>
                                        <i class="bi bi-exclamation-triangle-fill text-warning ms-1"></i>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permissionMatrix as $moduleKey => $module): ?>
                            <tr>
                                <td class="text-start">
                                    <i class="<?= $module['icon'] ?> me-2"></i>
                                    <?= htmlspecialchars($module['module_name']) ?>
                                </td>
                                <?php foreach ($actions as $action): ?>
                                    <?php 
                                    $moduleActionSet = $moduleActionsMap[$moduleKey] ?? [];
                                    if (!isset($moduleActionSet[$action['action_key']])) {
                                        echo '<td class="text-muted">&mdash;</td>';
                                        continue;
                                    }
                                    $hasPermission = $module['actions'][$action['action_key']]['has_permission'] ?? false;
                                    $isSensitive = $module['actions'][$action['action_key']]['is_sensitive'] ?? ($moduleActionSet[$action['action_key']]['is_sensitive'] ?? false);
                                    ?>
                                    <td class="text-center <?= $hasPermission ? 'permission-granted' : 'permission-denied' ?> <?= $isSensitive ? 'sensitive-action' : '' ?>">
                                        <i class="bi bi-<?= $hasPermission ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?>"></i>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-person-x text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Select a user to view their permission matrix</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Permission Groups Tab -->
    <div class="tab-pane fade" id="groups" role="tabpanel">
        <div class="row g-3">
            <?php foreach ($groups as $group): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header" style="background-color: <?= $group['color'] ?>15; border-left: 4px solid <?= $group['color'] ?>;">
                        <h6 class="mb-0"><?= htmlspecialchars($group['name']) ?></h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3"><?= htmlspecialchars($group['description']) ?></p>
                        
                        <?php
                        $groupMembers = $db->fetchAll("
                            SELECT u.full_name, u.username 
                            FROM user_group_memberships ugm
                            JOIN users u ON ugm.user_id = u.id
                            WHERE ugm.group_id = ? AND ugm.is_active = 1
                            ORDER BY u.full_name
                        ", [$group['id']]);
                        ?>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block mb-2">Members (<?= count($groupMembers) ?>):</small>
                            <?php if (!empty($groupMembers)): ?>
                                <?php foreach (array_slice($groupMembers, 0, 3) as $member): ?>
                                    <span class="badge bg-light text-dark me-1 mb-1"><?= htmlspecialchars($member['full_name']) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($groupMembers) > 3): ?>
                                    <span class="badge bg-secondary">+<?= count($groupMembers) - 3 ?> more</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small">No members</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="manageGroupMembers(<?= $group['id'] ?>, '<?= htmlspecialchars($group['name']) ?>')">
                                <i class="bi bi-people me-1"></i>Manage Members
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="editGroupPermissions(<?= $group['id'] ?>, '<?= htmlspecialchars($group['name']) ?>')">
                                <i class="bi bi-shield-lock me-1"></i>Edit Permissions
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Individual Permissions Tab -->
    <div class="tab-pane fade" id="individual" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-person-gear me-2"></i>Individual Permission Overrides</h6>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="grant_permission">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                    
                    <div class="col-md-3">
                        <label class="form-label">User *</label>
                        <select class="form-select" name="user_id" required>
                            <option value="">Select User</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Module *</label>
                        <select class="form-select" name="module_key" required>
                            <option value="">Select Module</option>
                            <?php foreach ($modules as $module): ?>
                                <option value="<?= $module['module_key'] ?>"><?= htmlspecialchars($module['module_name'] ?? $module['module_key'] ?? 'Unknown') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Action *</label>
                        <select class="form-select" name="action_key" required>
                            <option value="">Select Action</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?= $action['action_key'] ?>" <?= ($action['is_sensitive'] ?? false) ? 'data-sensitive="true"' : '' ?>>
                                    <?= htmlspecialchars($action['action_name'] ?? $action['action_key'] ?? 'Unknown') ?>
                                    <?= ($action['is_sensitive'] ?? false) ? ' ⚠️' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Expires At</label>
                        <input type="datetime-local" class="form-control" name="expires_at">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Time Restrictions (Optional)</label>
                        <div class="row g-2">
                            <div class="col">
                                <input type="number" class="form-control" name="time_start" placeholder="Start Hour (0-23)" min="0" max="23">
                            </div>
                            <div class="col">
                                <input type="number" class="form-control" name="time_end" placeholder="End Hour (0-23)" min="0" max="23">
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Amount Limit</label>
                        <input type="number" step="0.01" class="form-control" name="amount_limit" placeholder="Max amount">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block">
                            <i class="bi bi-shield-plus me-2"></i>Grant Permission
                        </button>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Reason for Permission Change</label>
                        <textarea class="form-control" name="reason" rows="2" placeholder="Explain why this permission is being granted..."></textarea>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Permission Templates Tab -->
    <div class="tab-pane fade" id="templates" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-file-earmark-code me-2"></i>Permission Templates</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Permission templates allow you to save and reuse common permission sets. This feature helps maintain consistency and speeds up user setup.
                </div>
                
                <div class="text-center py-5">
                    <i class="bi bi-file-earmark-plus text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">Permission templates feature coming soon!</p>
                    <button class="btn btn-outline-primary" disabled>
                        <i class="bi bi-plus-circle me-2"></i>Create Template
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Group Modal -->
<div class="modal fade" id="createGroupModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create_group">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Create Permission Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Group Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Color</label>
                            <input type="color" class="form-control form-control-color" name="color" value="#007bff">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Initial Permissions</label>
                            <div class="row g-2">
                                <?php foreach ($modules as $module): ?>
                                <div class="col-md-6">
                                    <div class="card border">
                                        <div class="card-header py-2">
                                            <small class="fw-bold">
                                                <i class="<?= $module['icon'] ?? 'bi bi-gear' ?> me-1"></i>
                                                <?= htmlspecialchars($module['module_name'] ?? $module['module_key'] ?? 'Unknown') ?>
                                            </small>
                                        </div>
                                        <div class="card-body py-2">
                                            <?php $moduleActionSet = $moduleActionsMap[$module['module_key']] ?? []; ?>
                                            <?php if (!empty($moduleActionSet)): ?>
                                                <?php foreach ($moduleActionSet as $actionKey => $actionMeta): ?>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" name="permissions[]" 
                                                               value="<?= $module['module_key'] ?>:<?= $actionKey ?>" 
                                                               id="perm_<?= $module['module_key'] ?>_<?= $actionKey ?>">
                                                        <label class="form-check-label small" for="perm_<?= $module['module_key'] ?>_<?= $actionKey ?>">
                                                            <?= htmlspecialchars($actionMeta['action_name'] ?? ucfirst($actionKey)) ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <small class="text-muted">No actions configured for this module.</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Audit Log Modal -->
<div class="modal fade" id="auditLogModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Permission Audit Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="audit-log">
                    <?php
                    $auditLogs = $db->fetchAll("
                        SELECT 
                            pal.*,
                            u.full_name as user_name,
                            sm.display_name AS module_name,
                            sa.display_name AS action_name
                        FROM permission_audit_log pal
                        LEFT JOIN users u ON pal.user_id = u.id
                        LEFT JOIN system_modules sm ON pal.module_id = sm.id
                        LEFT JOIN system_actions sa ON pal.action_id = sa.id
                        ORDER BY pal.created_at DESC
                        LIMIT 100
                    ");
                    ?>
                    
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Module</th>
                                    <th>Details</th>
                                    <th>Risk</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($auditLogs as $log): ?>
                                <tr class="<?= $log['risk_level'] === 'high' || $log['risk_level'] === 'critical' ? 'table-warning' : '' ?>">
                                    <td class="small"><?= formatDate($log['created_at'], 'M j, H:i') ?></td>
                                    <td class="small"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $log['action_type'] === 'permission_denied' ? 'danger' : 
                                            ($log['action_type'] === 'permission_granted' ? 'success' : 'secondary') 
                                        ?>">
                                            <?= str_replace('_', ' ', $log['action_type']) ?>
                                        </span>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($log['module_name'] ?? 'N/A') ?></td>
                                    <td class="small">
                                        <?php 
                                        $details = $log['additional_data'] ? json_decode($log['additional_data'], true) : null;
                                        echo htmlspecialchars($details['details'] ?? 'N/A');
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $log['risk_level'] === 'critical' ? 'danger' : 
                                            ($log['risk_level'] === 'high' ? 'warning' : 
                                            ($log['risk_level'] === 'medium' ? 'info' : 'secondary'))
                                        ?>">
                                            <?= ucfirst($log['risk_level']) ?>
                                        </span>
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

<script>
function manageGroupMembers(groupId, groupName) {
    // Implementation for managing group members
    alert('Manage members for: ' + groupName);
}

function editGroupPermissions(groupId, groupName) {
    // Implementation for editing group permissions
    alert('Edit permissions for: ' + groupName);
}

// Auto-refresh audit log every 30 seconds when modal is open
let auditLogInterval;
$('#auditLogModal').on('shown.bs.modal', function() {
    auditLogInterval = setInterval(function() {
        // Refresh audit log content
        location.reload();
    }, 30000);
});

$('#auditLogModal').on('hidden.bs.modal', function() {
    if (auditLogInterval) {
        clearInterval(auditLogInterval);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
