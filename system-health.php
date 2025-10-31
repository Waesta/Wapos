<?php
// Force no caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance()->getConnection();

$pageTitle = 'System Health Check';
include 'includes/header.php';

// Get ALL tables that actually exist in the database
$allTables = [];
$result = $db->query("SHOW TABLES");
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    $allTables[] = $row[0];
}

// System health checks
$health = [
    'database' => false,
    'tables' => [],
    'permissions' => false,
    'files' => []
];

// Check database connection
try {
    $db->query("SELECT 1");
    $health['database'] = true;
} catch (Exception $e) {
    $health['database'] = false;
}

// Check which essential tables exist
$essentialTables = [
    'users',
    'products',
    'sales',
    'settings',
    'suppliers',
    'accounts',
    'journal_entries'
];

$tableStatus = [];
$allGood = true;

foreach ($essentialTables as $table) {
    $exists = in_array($table, $allTables);
    $tableStatus[$table] = $exists;
    if (!$exists) {
        $allGood = false;
    }
}

// Check database connection
$dbConnected = true;
try {
    $db->query("SELECT 1");
} catch (Exception $e) {
    $dbConnected = false;
    $allGood = false;
}

// Check files
$files = [
    'includes/bootstrap.php',
    'includes/Database.php',
    'includes/Auth.php'
];

$fileStatus = [];
foreach ($files as $file) {
    $exists = file_exists($file);
    $fileStatus[$file] = $exists;
    if (!$exists) {
        $allGood = false;
    }
}

// Now check the actual tables with proper descriptions
$tableDescriptions = [
    'users' => 'User Management',
    'products' => 'Product Catalog',
    'sales' => 'Sales Transactions',
    'settings' => 'System Settings',
    'permission_modules' => 'Permission System',
    'stock_movements' => 'Inventory Management',
    'suppliers' => 'Supplier Management'
];

$health['tables'] = [];

foreach ($tableDescriptions as $table => $description) {
    $exists = in_array($table, $allTables);
    $health['tables'][$table] = [
        'exists' => $exists,
        'description' => $description
    ];
    
    // If table exists, check if it has data
    if ($exists) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            $health['tables'][$table]['count'] = $count['count'] ?? 0;
        } catch (Exception $e) {
            $health['tables'][$table]['count'] = 'Error';
        }
    } else {
        $health['tables'][$table]['count'] = 0;
    }
}

// Check permissions system properly
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM permission_modules WHERE is_active = 1");
    $modules = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $db->query("SELECT COUNT(*) as count FROM permission_actions WHERE is_active = 1");
    $actions = $stmt->fetch(PDO::FETCH_ASSOC);
    $health['permissions'] = (($modules['count'] ?? 0) > 0 && ($actions['count'] ?? 0) > 0);
} catch (Exception $e) {
    $health['permissions'] = false;
}

// Check essential files
$essentialFiles = [
    'includes/bootstrap.php' => 'System Bootstrap',
    'includes/Database.php' => 'Database Layer',
    'includes/Auth.php' => 'Authentication System',
    'includes/PermissionManager.php' => 'Permission Manager'
];

foreach ($essentialFiles as $file => $description) {
    $health['files'][$file] = [
        'exists' => file_exists($file),
        'description' => $description
    ];
}

// Calculate overall health
$tablesHealthy = true;
foreach ($health['tables'] as $table => $info) {
    if (!$info['exists']) {
        $tablesHealthy = false;
        break;
    }
}

$filesHealthy = true;
foreach ($health['files'] as $file => $info) {
    if (!$info['exists']) {
        $filesHealthy = false;
        break;
    }
}

$overallHealth = $health['database'] && $health['permissions'] && $tablesHealthy && $filesHealthy;
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
                <?php foreach ($health['tables'] as $table => $info): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <span class="fw-bold"><?= htmlspecialchars($table) ?></span><br>
                        <small class="text-muted"><?= htmlspecialchars($info['description']) ?></small>
                        <?php if ($info['exists'] && isset($info['count'])): ?>
                        <br><small class="text-info"><?= $info['count'] ?> records</small>
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-<?= $info['exists'] ? 'success' : 'danger' ?>">
                        <?= $info['exists'] ? 'OK' : 'Missing' ?>
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
                <?php foreach ($health['files'] as $file => $info): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <span class="small fw-bold"><?= htmlspecialchars($file) ?></span><br>
                        <small class="text-muted"><?= htmlspecialchars($info['description']) ?></small>
                    </div>
                    <span class="badge bg-<?= $info['exists'] ? 'success' : 'danger' ?>">
                        <?= $info['exists'] ? 'OK' : 'Missing' ?>
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
                    <div class="col-md-2">
                        <a href="fix-system-health-issues.php" class="btn btn-success w-100">
                            <i class="bi bi-wrench me-2"></i>Fix Issues
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="permissions.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-shield-lock me-2"></i>Permissions
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="users.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-people me-2"></i>Users
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="inventory.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-boxes me-2"></i>Inventory
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="settings.php" class="btn btn-outline-warning w-100">
                            <i class="bi bi-gear me-2"></i>Settings
                        </a>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
