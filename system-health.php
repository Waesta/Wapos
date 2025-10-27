<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$pageTitle = 'System Health Check';
include 'includes/header.php';

// Simple health checks
$health = [
    'database' => false,
    'tables' => [],
    'permissions' => false,
    'files' => []
];

// Check database connection
try {
    $db->fetchOne("SELECT 1");
    $health['database'] = true;
} catch (Exception $e) {
    $health['database'] = false;
}

// Check essential tables
$essentialTables = ['users', 'products', 'sales', 'settings'];
foreach ($essentialTables as $table) {
    try {
        $result = $db->fetchOne("SHOW TABLES LIKE ?", [$table]);
        $health['tables'][$table] = !empty($result);
    } catch (Exception $e) {
        $health['tables'][$table] = false;
    }
}

// Check permissions system
try {
    $systemManager->getSystemModules();
    $systemManager->getSystemActions();
    $health['permissions'] = true;
} catch (Exception $e) {
    $health['permissions'] = false;
}

// Check essential files
$essentialFiles = [
    'includes/bootstrap.php',
    'includes/Database.php',
    'includes/Auth.php',
    'includes/SystemManager.php'
];

foreach ($essentialFiles as $file) {
    $health['files'][$file] = file_exists($file);
}

$overallHealth = $health['database'] && $health['permissions'] && 
                 !in_array(false, $health['tables']) && 
                 !in_array(false, $health['files']);
?>

<div class="row g-4">
    <!-- Overall Status -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-<?= $overallHealth ? 'success' : 'danger' ?> text-white">
                <h5 class="mb-0">
                    <i class="bi bi-<?= $overallHealth ? 'check-circle-fill' : 'x-circle-fill' ?> me-2"></i>
                    System Health: <?= $overallHealth ? 'HEALTHY' : 'ISSUES DETECTED' ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($overallHealth): ?>
                <div class="alert alert-success">
                    <h6><i class="bi bi-check-circle me-2"></i>All Systems Operational</h6>
                    <p class="mb-0">Your WAPOS system is running smoothly with no detected issues.</p>
                </div>
                <?php else: ?>
                <div class="alert alert-danger">
                    <h6><i class="bi bi-exclamation-triangle me-2"></i>System Issues Detected</h6>
                    <p class="mb-0">Some components need attention. Please review the details below.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Database Health -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-database me-2"></i>Database Health</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Connection</span>
                        <span class="badge bg-<?= $health['database'] ? 'success' : 'danger' ?>">
                            <?= $health['database'] ? 'Connected' : 'Failed' ?>
                        </span>
                    </div>
                </div>
                
                <h6>Essential Tables:</h6>
                <?php foreach ($health['tables'] as $table => $exists): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><?= htmlspecialchars($table) ?></span>
                    <span class="badge bg-<?= $exists ? 'success' : 'danger' ?>">
                        <?= $exists ? 'OK' : 'Missing' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- System Components -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-gear me-2"></i>System Components</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Permissions System</span>
                        <span class="badge bg-<?= $health['permissions'] ? 'success' : 'danger' ?>">
                            <?= $health['permissions'] ? 'Working' : 'Error' ?>
                        </span>
                    </div>
                </div>
                
                <h6>Core Files:</h6>
                <?php foreach ($health['files'] as $file => $exists): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small"><?= htmlspecialchars($file) ?></span>
                    <span class="badge bg-<?= $exists ? 'success' : 'danger' ?>">
                        <?= $exists ? 'OK' : 'Missing' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="bi bi-tools me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-3">
                        <a href="permissions.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-shield-lock me-2"></i>Check Permissions
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="users.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-people me-2"></i>Manage Users
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="settings.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-gear me-2"></i>System Settings
                        </a>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-warning w-100" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Refresh Check
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
