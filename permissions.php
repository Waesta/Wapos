<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

require_once 'includes/PermissionManager.php';
$permissionManager = new PermissionManager($auth->getUserId());

$db = Database::getInstance();

require_once __DIR__ . '/includes/HospitalityPermissionSeeder.php';
HospitalityPermissionSeeder::sync($db);

if (!function_exists('buildPermissionsLink')) {
    function buildPermissionsLink(array $overrides = []): string
    {
        $query = $_GET;
        unset($query['action'], $query['csrf_token']);
        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }

        $basePath = $_SERVER['PHP_SELF'] ?? '/permissions.php';
        $queryString = http_build_query($query);
        return $queryString ? $basePath . '?' . $queryString : $basePath;
    }
}

$validTabs = ['matrix', 'groups', 'individual', 'templates'];
$activeTab = $_GET['tab'] ?? 'matrix';
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'matrix';
}

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
                'group_name' => sanitizeInput($_POST['name']),
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

        elseif ($action === 'update_group_permissions') {
            $groupId = (int)($_POST['group_id'] ?? 0);
            $selectedPermissions = $_POST['permissions'] ?? [];
            if ($groupId <= 0) {
                throw new Exception('Invalid group selected');
            }

            $moduleIdCache = [];
            $actionIdCache = [];

            $db->beginTransaction();
            try {
                $db->query('DELETE FROM group_permissions WHERE group_id = ?', [$groupId]);

                foreach ($selectedPermissions as $permission) {
                    if (strpos($permission, ':') === false) {
                        continue;
                    }

                    [$moduleKey, $actionKey] = array_map('trim', explode(':', $permission, 2));
                    if ($moduleKey === '' || $actionKey === '') {
                        continue;
                    }

                    if (!isset($moduleIdCache[$moduleKey])) {
                        $moduleRow = $db->fetchOne('SELECT id FROM system_modules WHERE module_key = ?', [$moduleKey]);
                        if (!$moduleRow) {
                            continue;
                        }
                        $moduleIdCache[$moduleKey] = (int)$moduleRow['id'];
                    }

                    if (!isset($actionIdCache[$actionKey])) {
                        $actionRow = $db->fetchOne('SELECT id FROM system_actions WHERE action_key = ?', [$actionKey]);
                        if (!$actionRow) {
                            continue;
                        }
                        $actionIdCache[$actionKey] = (int)$actionRow['id'];
                    }

                    $db->insert('group_permissions', [
                        'group_id' => $groupId,
                        'module_id' => $moduleIdCache[$moduleKey],
                        'action_id' => $actionIdCache[$actionKey],
                        'is_granted' => 1,
                        'granted_by' => $auth->getUserId()
                    ]);
                }

                $db->commit();
                $_SESSION['success_message'] = 'Group permissions updated successfully';
            } catch (Exception $updateException) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $updateException;
            }
        }

        elseif ($action === 'create_template') {
            $templateName = sanitizeInput($_POST['template_name'] ?? '');
            $templateDescription = sanitizeInput($_POST['template_description'] ?? '');
            $templatePermissions = $_POST['template_permissions'] ?? [];

            if (empty($templateName)) {
                throw new Exception('Template name is required');
            }

            $templateData = json_encode([
                'permissions' => $templatePermissions,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $db->insert('permission_templates', [
                'name' => $templateName,
                'description' => $templateDescription,
                'template_data' => $templateData,
                'created_by' => $auth->getUserId()
            ]);

            $_SESSION['success_message'] = 'Permission template created successfully';
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
    
    redirect($_SERVER['PHP_SELF']);
}

// Get data for display with error handling
$moduleActionsMap = [];
try {
    $users = $db->fetchAll("
        SELECT id, username, full_name, role, is_active
        FROM users
        ORDER BY is_active DESC, full_name
    ") ?: [];
    
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
        WHERE up.is_granted = 1 
        ORDER BY u.username, sm.display_name, sa.display_name
    ") ?: [];
    
    // Load permission groups
    $groups = $db->fetchAll("
        SELECT id, group_name AS name, description, color
        FROM permission_groups
        WHERE is_active = 1
        ORDER BY group_name
    ") ?: [];

    $groupMembersMap = [];
    $groupPermissionsMap = [];

    if (!empty($groups)) {
        $groupMembersRows = $db->fetchAll("
            SELECT 
                ugm.group_id,
                ugm.user_id,
                ugm.expires_at,
                u.full_name,
                u.username
            FROM user_group_memberships ugm
            JOIN users u ON ugm.user_id = u.id
            WHERE ugm.is_active = 1
            ORDER BY u.full_name
        ") ?: [];

        foreach ($groupMembersRows as $memberRow) {
            $groupId = (int)$memberRow['group_id'];
            $groupMembersMap[$groupId][] = [
                'user_id' => (int)$memberRow['user_id'],
                'full_name' => $memberRow['full_name'],
                'username' => $memberRow['username'],
                'expires_at' => $memberRow['expires_at']
            ];
        }

        $groupPermissionsRows = $db->fetchAll("
            SELECT 
                gp.group_id,
                sm.module_key,
                sa.action_key
            FROM group_permissions gp
            JOIN system_modules sm ON gp.module_id = sm.id
            JOIN system_actions sa ON gp.action_id = sa.id
            WHERE gp.is_granted = 1
        ") ?: [];

        foreach ($groupPermissionsRows as $permRow) {
            $groupId = (int)$permRow['group_id'];
            $groupPermissionsMap[$groupId][] = $permRow['module_key'] . ':' . $permRow['action_key'];
        }
    }

    $groupDataForJs = [];
    foreach ($groups as $group) {
        $groupId = (int)$group['id'];
        $groupDataForJs[$groupId] = [
            'id' => $groupId,
            'name' => $group['name'],
            'description' => $group['description'],
            'color' => $group['color'],
            'members' => $groupMembersMap[$groupId] ?? [],
            'permissions' => $groupPermissionsMap[$groupId] ?? []
        ];
    }

} catch (Exception $e) {
    error_log('Permissions data loading error: ' . $e->getMessage());
    $users = [];
    $modules = [];
    $actions = [];
    $userPermissions = [];
    $groups = [];
    $groupMembersMap = [];
    $groupPermissionsMap = [];
    $groupDataForJs = [];
}

$usersForJs = array_map(static function ($user) {
    $fullName = trim((string)($user['full_name'] ?? ''));
    $username = (string)($user['username'] ?? '');
    return [
        'id' => (int)($user['id'] ?? 0),
        'full_name' => $fullName !== '' ? $fullName : $username,
        'username' => $username,
        'role' => (string)($user['role'] ?? '')
    ];
}, $users);

$groupDataJson = json_encode((object)$groupDataForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}';
$usersDataJson = json_encode($usersForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';
$moduleActionsJson = json_encode($moduleActionsMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}';
$modulesJson = json_encode($modules, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';

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
.permissions-shell {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xl, 2rem);
}
.page-toolbar {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    border: 1px solid var(--color-border, #e9ecef);
    border-radius: var(--radius-lg, 1rem);
    padding: 1.25rem;
    background: var(--color-surface, #fff);
    box-shadow: var(--shadow-sm, 0 1px 2px rgba(15, 23, 42, 0.08));
}
.page-toolbar .toolbar-info h4 {
    margin-bottom: 0.25rem;
}
.page-toolbar .toolbar-info p {
    margin: 0;
    color: var(--color-text-muted, #6c757d);
}
.page-toolbar .toolbar-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}
.page-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    border: none;
}
.page-tabs .nav-link {
    border-radius: 999px;
    border: 1px solid var(--color-border, #e0e0e0);
    background: #fff;
    color: var(--color-text, #0f172a);
    padding: 0.45rem 1.15rem;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.page-tabs .nav-link.active {
    background: var(--bs-primary, #0d6efd);
    color: #fff;
    border-color: var(--bs-primary, #0d6efd);
    box-shadow: 0 0.35rem 1rem rgba(13, 110, 253, 0.15);
}
.tab-pane {
    transition: opacity 0.2s ease;
}
@media (max-width: 767px) {
    .page-toolbar {
        flex-direction: column;
        align-items: flex-start;
    }
    .page-tabs {
        width: 100%;
    }
    .page-tabs .nav-link {
        flex: 1 1 calc(50% - 0.5rem);
        justify-content: center;
    }
}
</style>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

<div class="permissions-shell">
    <section class="page-toolbar">
        <div class="toolbar-info">
            <h4 class="mb-1"><i class="bi bi-shield-lock me-2"></i>User Permissions</h4>
            <p class="mb-0">Audit, grant, and monitor granular access across every module.</p>
        </div>
        <div class="toolbar-actions">
            <a href="create-permission-templates.php" class="btn btn-light">
                <i class="bi bi-collection me-2"></i>Templates
            </a>
            <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#auditLogModal" type="button">
                <i class="bi bi-clock-history me-2"></i>Audit Log
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal" type="button">
                <i class="bi bi-plus-circle me-2"></i>Create Group
            </button>
        </div>
    </section>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills page-tabs mb-4" id="permissionTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $activeTab === 'matrix' ? 'active' : '' ?>" id="matrix-tab" href="<?= buildPermissionsLink(['tab' => 'matrix']) ?>">
                <i class="bi bi-grid"></i>
                <span>Matrix</span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $activeTab === 'groups' ? 'active' : '' ?>" id="groups-tab" href="<?= buildPermissionsLink(['tab' => 'groups']) ?>">
                <i class="bi bi-people"></i>
                <span>Groups</span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $activeTab === 'individual' ? 'active' : '' ?>" id="individual-tab" href="<?= buildPermissionsLink(['tab' => 'individual']) ?>">
                <i class="bi bi-person-gear"></i>
                <span>Individual</span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $activeTab === 'templates' ? 'active' : '' ?>" id="templates-tab" href="<?= buildPermissionsLink(['tab' => 'templates']) ?>">
                <i class="bi bi-file-earmark-code"></i>
                <span>Templates</span>
            </a>
        </li>
    </ul>

    <div class="tab-content" id="permissionTabContent">
    <!-- Permission Matrix Tab -->
    <div class="tab-pane fade <?= $activeTab === 'matrix' ? 'show active' : '' ?>" id="matrix" role="tabpanel" aria-labelledby="matrix-tab">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h6 class="mb-0"><i class="bi bi-grid me-2"></i>Permission Matrix</h6>
                    </div>
                    <div class="col-md-6">
                        <form method="GET" class="d-flex align-items-center gap-2" id="userSelectForm">
                            <?php
                            $preservedQuery = $_GET;
                            unset($preservedQuery['user_id'], $preservedQuery['action'], $preservedQuery['csrf_token']);
                            foreach ($preservedQuery as $key => $value):
                            ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                            <?php endforeach; ?>
                            <select class="form-select" id="userSelect" name="user_id">
                                <option value="">Select User</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= $selectedUserId == $user['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['username']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-outline-secondary">Go</button>
                        </form>
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
    <div class="tab-pane fade <?= $activeTab === 'groups' ? 'show active' : '' ?>" id="groups" role="tabpanel" aria-labelledby="groups-tab">
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
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary manage-group-members-btn"
                                data-group-id="<?= (int)$group['id'] ?>"
                                data-group-name="<?= htmlspecialchars($group['name'], ENT_QUOTES) ?>"
                            >
                                <i class="bi bi-people me-1"></i>Manage Members
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary edit-group-permissions-btn"
                                data-group-id="<?= (int)$group['id'] ?>"
                                data-group-name="<?= htmlspecialchars($group['name'], ENT_QUOTES) ?>"
                            >
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
    <div class="tab-pane fade <?= $activeTab === 'individual' ? 'show active' : '' ?>" id="individual" role="tabpanel" aria-labelledby="individual-tab">
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
    <div class="tab-pane fade <?= $activeTab === 'templates' ? 'show active' : '' ?>" id="templates" role="tabpanel" aria-labelledby="templates-tab">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-file-earmark-code me-2"></i>Permission Templates</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Permission templates allow you to save and reuse common permission sets. This feature helps maintain consistency and speeds up user setup.
                </div>
                
                <?php
                $templates = $db->fetchAll("SELECT id, name, description, created_at FROM permission_templates ORDER BY name") ?: [];
                if (!empty($templates)):
                ?>
                <div class="row g-3">
                    <?php foreach ($templates as $template): ?>
                    <div class="col-md-4">
                        <div class="card border">
                            <div class="card-body">
                                <h6><?= htmlspecialchars($template['name']) ?></h6>
                                <p class="small text-muted mb-2"><?= htmlspecialchars($template['description'] ?? '') ?></p>
                                <button class="btn btn-sm btn-outline-primary" type="button">
                                    <i class="bi bi-copy me-1"></i>Apply
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-file-earmark-plus text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">No permission templates created yet.</p>
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createTemplateModal" type="button">
                        <i class="bi bi-plus-circle me-2"></i>Create Template
                    </button>
                </div>
                <?php endif; ?>
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

<!-- Manage Group Members Modal -->
<div class="modal fade" id="manageGroupMembersModal" tabindex="-1" aria-labelledby="manageGroupMembersModalLabel">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center flex-wrap gap-2" id="manageGroupMembersModalLabel">
                    <span><i class="bi bi-people me-2"></i>Manage Group Members</span>
                    <small class="text-muted" id="manageGroupMembersModalName"></small>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <form method="POST" id="addGroupMemberForm" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="add_to_group">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                        <input type="hidden" name="group_id" id="addMemberGroupId">
                        <div class="col-md-6">
                            <label for="addMemberUserSelect" class="form-label">User *</label>
                            <select class="form-select" id="addMemberUserSelect" name="user_id" required>
                                <option value="">Select user</option>
                            </select>
                            <small class="text-muted" id="addMemberHelper"></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expires At</label>
                            <input type="datetime-local" class="form-control" name="expires_at">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-plus me-1"></i>Add
                            </button>
                        </div>
                    </form>
                </div>

                <div class="table-responsive border rounded">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Username</th>
                                <th>Expires</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="groupMembersList">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    Select a group to manage its members.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Group Permissions Modal -->
<div class="modal fade" id="editGroupPermissionsModal" tabindex="-1" aria-labelledby="editGroupPermissionsModalLabel">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" id="editGroupPermissionsForm">
                <input type="hidden" name="action" value="update_group_permissions">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                <input type="hidden" name="group_id" id="editPermissionsGroupId">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center flex-wrap gap-2" id="editGroupPermissionsModalLabel">
                        <span><i class="bi bi-shield-lock me-2"></i>Edit Group Permissions</span>
                        <small class="text-muted" id="editGroupPermissionsModalName"></small>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-info-circle text-primary"></i>
                        <div class="small mb-0">
                            Select the actions this group should have access to. Changes take effect immediately for all members.
                        </div>
                    </div>
                    <div id="groupPermissionsChecklist" class="row g-3">
                        <div class="col-12 text-center text-muted py-4">
                            Select a group to load its permission map.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Save Permissions
                    </button>
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

<!-- Create Template Modal -->
<div class="modal fade" id="createTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create_template">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-plus me-2"></i>Create Permission Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Template Name *</label>
                            <input type="text" class="form-control" name="template_name" required placeholder="e.g., Basic Cashier, Senior Manager">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="template_description" rows="2" placeholder="Describe what this template is for..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Permissions</label>
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
                                                        <input class="form-check-input" type="checkbox" name="template_permissions[]" 
                                                               value="<?= $module['module_key'] ?>:<?= $actionKey ?>" 
                                                               id="tpl_<?= $module['module_key'] ?>_<?= $actionKey ?>">
                                                        <label class="form-check-label small" for="tpl_<?= $module['module_key'] ?>_<?= $actionKey ?>">
                                                            <?= htmlspecialchars($actionMeta['action_name'] ?? ucfirst($actionKey)) ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <small class="text-muted">No actions</small>
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
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Save Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const GROUP_DATA = <?= $groupDataJson ?>;
const USERS_DATA = <?= $usersDataJson ?>;
const MODULE_ACTIONS_MAP = <?= $moduleActionsJson ?>;
const MODULES_DATA = <?= $modulesJson ?>;
const CSRF_TOKEN = '<?= generateCSRFToken(); ?>';

function manageGroupMembers(groupId, groupName) {
    const modalEl = document.getElementById('manageGroupMembersModal');
    if (!modalEl) {
        return;
    }

    document.getElementById('manageGroupMembersModalName').textContent = groupName || '';
    document.getElementById('addMemberGroupId').value = groupId;
    populateAddMemberSelect(groupId);
    renderGroupMembersTable(groupId);
    showBootstrapModal(modalEl);
}

function editGroupPermissions(groupId, groupName) {
    const modalEl = document.getElementById('editGroupPermissionsModal');
    if (!modalEl) {
        return;
    }

    document.getElementById('editPermissionsGroupId').value = groupId;
    document.getElementById('editGroupPermissionsModalName').textContent = groupName || '';
    buildGroupPermissionChecklist(groupId);
    showBootstrapModal(modalEl);
}

function populateAddMemberSelect(groupId) {
    const selectEl = document.getElementById('addMemberUserSelect');
    if (!selectEl) {
        return;
    }

    selectEl.innerHTML = '<option value="">Select user</option>';
    const group = GROUP_DATA[groupId] || {};
    const memberIds = new Set((group.members || []).map(member => member.user_id));

    USERS_DATA.forEach(user => {
        if (memberIds.has(user.id)) {
            return;
        }
        const option = document.createElement('option');
        option.value = user.id;
        option.textContent = `${user.full_name} (${user.username})`;
        selectEl.appendChild(option);
    });

    const helper = document.getElementById('addMemberHelper');
    if (helper) {
        const count = memberIds.size;
        helper.textContent = count
            ? `${count} member${count === 1 ? '' : 's'} currently in this group`
            : 'No members currently in this group';
    }
}

function renderGroupMembersTable(groupId) {
    const tbody = document.getElementById('groupMembersList');
    if (!tbody) {
        return;
    }

    tbody.innerHTML = '';
    const group = GROUP_DATA[groupId];
    const members = group?.members || [];

    if (!members.length) {
        const emptyRow = document.createElement('tr');
        const emptyCell = document.createElement('td');
        emptyCell.colSpan = 4;
        emptyCell.className = 'text-center text-muted py-4';
        emptyCell.textContent = 'No members in this group yet.';
        emptyRow.appendChild(emptyCell);
        tbody.appendChild(emptyRow);
        return;
    }

    members.forEach(member => {
        const row = document.createElement('tr');

        const nameCell = document.createElement('td');
        nameCell.textContent = member.full_name || member.username || 'User';
        row.appendChild(nameCell);

        const usernameCell = document.createElement('td');
        usernameCell.textContent = member.username || '—';
        row.appendChild(usernameCell);

        const expiresCell = document.createElement('td');
        expiresCell.textContent = formatDateLabel(member.expires_at);
        row.appendChild(expiresCell);

        const actionsCell = document.createElement('td');
        actionsCell.className = 'text-end';

        const removeForm = document.createElement('form');
        removeForm.method = 'POST';
        removeForm.className = 'remove-group-member-form d-inline';
        removeForm.dataset.memberName = member.full_name || member.username || 'this member';

        removeForm.appendChild(createHiddenInput('action', 'remove_from_group'));
        removeForm.appendChild(createHiddenInput('csrf_token', CSRF_TOKEN));
        removeForm.appendChild(createHiddenInput('group_id', groupId));
        removeForm.appendChild(createHiddenInput('user_id', member.user_id));
        removeForm.appendChild(createHiddenInput('reason', ''));

        const removeBtn = document.createElement('button');
        removeBtn.type = 'submit';
        removeBtn.className = 'btn btn-outline-danger btn-sm';
        removeBtn.innerHTML = '<i class="bi bi-person-dash"></i>';
        removeBtn.title = 'Remove from group';
        removeForm.appendChild(removeBtn);

        actionsCell.appendChild(removeForm);
        row.appendChild(actionsCell);
        tbody.appendChild(row);
    });
}

function buildGroupPermissionChecklist(groupId) {
    const container = document.getElementById('groupPermissionsChecklist');
    if (!container) {
        return;
    }

    container.innerHTML = '';
    const group = GROUP_DATA[groupId];
    if (!group) {
        container.innerHTML = '<div class="col-12 text-center text-muted py-4">Unable to load group permissions.</div>';
        return;
    }

    const selectedPermissions = new Set(group.permissions || []);
    const modulesWithActions = MODULES_DATA.filter(module => {
        const actionSet = MODULE_ACTIONS_MAP[module.module_key] || {};
        return Object.keys(actionSet).length > 0;
    });

    if (!modulesWithActions.length) {
        container.innerHTML = '<div class="col-12 text-center text-muted py-4">No modules have actions configured yet.</div>';
        return;
    }

    const fragment = document.createDocumentFragment();

    modulesWithActions.forEach(module => {
        const actionSet = MODULE_ACTIONS_MAP[module.module_key] || {};
        const col = document.createElement('div');
        col.className = 'col-md-6 col-lg-4';

        const card = document.createElement('div');
        card.className = 'card border';

        const header = document.createElement('div');
        header.className = 'card-header py-2';
        header.innerHTML = `<small class="fw-bold"><i class="${module.icon || 'bi bi-gear'} me-1"></i>${module.module_name || module.module_key}</small>`;
        card.appendChild(header);

        const body = document.createElement('div');
        body.className = 'card-body py-2';

        if (!Object.keys(actionSet).length) {
            const emptyText = document.createElement('small');
            emptyText.className = 'text-muted';
            emptyText.textContent = 'No actions configured for this module.';
            body.appendChild(emptyText);
        } else {
            Object.entries(actionSet).forEach(([actionKey, actionMeta]) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'form-check form-check-inline';

                const input = document.createElement('input');
                input.type = 'checkbox';
                input.className = 'form-check-input';
                input.name = 'permissions[]';
                input.value = `${module.module_key}:${actionKey}`;
                input.id = `editPerm_${module.module_key}_${actionKey}`;
                input.checked = selectedPermissions.has(input.value);

                const label = document.createElement('label');
                label.className = 'form-check-label small';
                label.setAttribute('for', input.id);
                label.textContent = actionMeta.action_name || actionKey;

                wrapper.appendChild(input);
                wrapper.appendChild(label);
                body.appendChild(wrapper);
            });
        }

        card.appendChild(body);
        col.appendChild(card);
        fragment.appendChild(col);
    });

    container.appendChild(fragment);
}

function createHiddenInput(name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    return input;
}

function formatDateLabel(value) {
    if (!value) {
        return 'No expiry';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    return date.toLocaleString();
}

function showBootstrapModal(modalEl) {
    if (window.bootstrap && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } else {
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        document.body.classList.add('modal-open');
    }
}

function hideBootstrapModal(modalEl) {
    if (window.bootstrap && bootstrap.Modal) {
        const instance = bootstrap.Modal.getInstance(modalEl);
        if (instance) {
            instance.hide();
        }
    } else {
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
}

// Auto-refresh audit log every 30 seconds when modal is open
let auditLogInterval;
const auditLogModal = document.getElementById('auditLogModal');
if (auditLogModal) {
    auditLogModal.addEventListener('shown.bs.modal', () => {
        auditLogInterval = setInterval(() => {
            window.location.reload();
        }, 30000);
    });

    auditLogModal.addEventListener('hidden.bs.modal', () => {
        if (auditLogInterval) {
            clearInterval(auditLogInterval);
            auditLogInterval = null;
        }
    });
}
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Auto-submit user select dropdown on change
    const userSelect = document.getElementById('userSelect');
    if (userSelect) {
        userSelect.addEventListener('change', () => {
            if (userSelect.value) {
                userSelect.closest('form').submit();
            }
        });
    }

    // Tabs use URL navigation - no JS needed for tab switching

    const modalToggleButtons = document.querySelectorAll('[data-bs-toggle="modal"][data-bs-target]');
    modalToggleButtons.forEach(button => {
        button.addEventListener('click', event => {
            const targetSelector = button.getAttribute('data-bs-target');
            const targetModal = targetSelector ? document.querySelector(targetSelector) : null;
            if (!targetModal) {
                return;
            }
            if (window.bootstrap && bootstrap.Modal) {
                return;
            }
            event.preventDefault();
            showBootstrapModal(targetModal);
        });
    });

    const modalDismissButtons = document.querySelectorAll('[data-bs-dismiss="modal"]');
    modalDismissButtons.forEach(button => {
        button.addEventListener('click', event => {
            if (window.bootstrap && bootstrap.Modal) {
                return;
            }
            event.preventDefault();
            const modalEl = button.closest('.modal');
            if (modalEl) {
                hideBootstrapModal(modalEl);
            }
        });
    });

    const manageMembersButtons = document.querySelectorAll('.manage-group-members-btn');
    manageMembersButtons.forEach(button => {
        button.addEventListener('click', () => {
            const groupId = parseInt(button.dataset.groupId, 10);
            if (!groupId) {
                return;
            }
            const groupName = button.dataset.groupName || '';
            manageGroupMembers(groupId, groupName);
        });
    });

    const editGroupButtons = document.querySelectorAll('.edit-group-permissions-btn');
    editGroupButtons.forEach(button => {
        button.addEventListener('click', () => {
            const groupId = parseInt(button.dataset.groupId, 10);
            if (!groupId) {
                return;
            }
            const groupName = button.dataset.groupName || '';
            editGroupPermissions(groupId, groupName);
        });
    });

    // Confirm before removing group members
    document.addEventListener('submit', event => {
        const form = event.target;
        if (form.classList.contains('remove-group-member-form')) {
            const memberName = form.dataset.memberName || 'this member';
            if (!confirm(`Remove ${memberName} from this group?`)) {
                event.preventDefault();
            }
        }
    });

    // Handle form select dropdowns - populate dependent selects
    const moduleSelect = document.querySelector('select[name="module_key"]');
    const actionSelect = document.querySelector('select[name="action_key"]');
    if (moduleSelect && actionSelect) {
        moduleSelect.addEventListener('change', () => {
            const selectedModule = moduleSelect.value;
            const moduleActions = MODULE_ACTIONS_MAP[selectedModule] || {};
            
            // Clear and repopulate action select
            actionSelect.innerHTML = '<option value="">Select Action</option>';
            Object.entries(moduleActions).forEach(([actionKey, actionMeta]) => {
                const option = document.createElement('option');
                option.value = actionKey;
                option.textContent = (actionMeta.action_name || actionKey) + (actionMeta.is_sensitive ? ' ⚠️' : '');
                if (actionMeta.is_sensitive) {
                    option.dataset.sensitive = 'true';
                }
                actionSelect.appendChild(option);
            });
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
