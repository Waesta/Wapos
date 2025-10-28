<?php
/**
 * Test Permissions Page - Simple Version
 * Tests the permissions system without complex features that might cause errors
 */

require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance();

$pageTitle = 'Permission Management (Test)';
include 'includes/header.php';

// Get data with proper error handling
try {
    $users = $db->fetchAll("SELECT id, username, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name") ?: [];
    $modules = $db->fetchAll("SELECT * FROM permission_modules WHERE is_active = 1 ORDER BY module_name") ?: [];
    $actions = $db->fetchAll("
        SELECT pa.*, pm.module_name 
        FROM permission_actions pa 
        JOIN permission_modules pm ON pa.module_id = pm.id 
        WHERE pa.is_active = 1 AND pm.is_active = 1 
        ORDER BY pm.module_name, pa.action_name
    ") ?: [];
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
    $users = [];
    $modules = [];
    $actions = [];
    $userPermissions = [];
    $error = $e->getMessage();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">
            <i class="bi bi-shield-lock me-2"></i>Permission Management (Test)
        </h4>
        <p class="text-muted mb-0">Testing permissions system functionality</p>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger">
    <h6><i class="bi bi-exclamation-triangle me-2"></i>Database Error</h6>
    <p class="mb-0"><?= htmlspecialchars($error) ?></p>
</div>
<?php endif; ?>

<!-- System Status Cards -->
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

<!-- Current Permissions -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h6 class="mb-0">Current User Permissions</h6>
    </div>
    <div class="card-body">
        <?php if (empty($userPermissions)): ?>
        <div class="text-center py-4">
            <i class="bi bi-shield-x text-muted fs-1 mb-3"></i>
            <h5 class="text-muted">No Permissions Assigned</h5>
            <p class="text-muted">Run the table creation scripts to set up the permission system.</p>
            <a href="quick-fix-missing-tables.php" class="btn btn-primary">
                <i class="bi bi-wrench me-2"></i>Create Permission Tables
            </a>
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
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Available Modules -->
<?php if (!empty($modules)): ?>
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white">
        <h6 class="mb-0">Available Permission Modules</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($modules as $module): ?>
            <div class="col-md-4">
                <div class="card border">
                    <div class="card-body">
                        <h6 class="card-title"><?= htmlspecialchars($module['module_name'] ?? $module['module_key'] ?? 'Unknown') ?></h6>
                        <p class="card-text small text-muted"><?= htmlspecialchars($module['description'] ?? 'No description') ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">Key: <?= htmlspecialchars($module['module_key'] ?? 'Unknown') ?></small>
                            <span class="badge bg-<?= ($module['is_active'] ?? false) ? 'success' : 'secondary' ?>">
                                <?= ($module['is_active'] ?? false) ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Available Actions -->
<?php if (!empty($actions)): ?>
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white">
        <h6 class="mb-0">Available Permission Actions</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Action Key</th>
                        <th>Action Name</th>
                        <th>Description</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($actions as $action): ?>
                    <tr>
                        <td><span class="badge bg-primary"><?= htmlspecialchars($action['module_name'] ?? 'Unknown') ?></span></td>
                        <td><code><?= htmlspecialchars($action['action_key'] ?? 'Unknown') ?></code></td>
                        <td><?= htmlspecialchars($action['action_name'] ?? 'Unknown') ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars($action['description'] ?? 'No description') ?></small></td>
                        <td>
                            <span class="badge bg-<?= ($action['is_active'] ?? false) ? 'success' : 'secondary' ?>">
                                <?= ($action['is_active'] ?? false) ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="mt-4">
    <h6>Quick Actions</h6>
    <div class="d-flex gap-2">
        <a href="quick-fix-missing-tables.php" class="btn btn-success">
            <i class="bi bi-wrench me-2"></i>Setup Tables
        </a>
        <a href="permissions.php" class="btn btn-primary">
            <i class="bi bi-shield-lock me-2"></i>Full Permissions Page
        </a>
        <a href="system-health.php" class="btn btn-info">
            <i class="bi bi-heart-pulse me-2"></i>System Health
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
