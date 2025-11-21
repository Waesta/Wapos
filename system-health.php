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
    'permission_counts' => [
        'modules' => 0,
        'actions' => 0
    ],
    'files' => [],
    'stats' => [
        'active_users' => null,
        'total_roles' => null,
        'total_tables' => count($allTables)
    ],
    'environment' => [
        'php_version' => PHP_VERSION,
        'server_name' => php_uname('n'),
        'os' => php_uname('s') . ' ' . php_uname('r'),
        'database_version' => null
    ]
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

if (!$health['database']) {
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
    $health['permission_counts']['modules'] = (int)($modules['count'] ?? 0);

    $stmt = $db->query("SELECT COUNT(*) as count FROM permission_actions WHERE is_active = 1");
    $actions = $stmt->fetch(PDO::FETCH_ASSOC);
    $health['permission_counts']['actions'] = (int)($actions['count'] ?? 0);

    $health['permissions'] = ($health['permission_counts']['modules'] > 0 && $health['permission_counts']['actions'] > 0);
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

// Additional statistics
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $health['stats']['active_users'] = (int)($result['count'] ?? 0);
} catch (Exception $e) {
    $health['stats']['active_users'] = null;
}

try {
    $stmt = $db->query("SELECT COUNT(DISTINCT role) as count FROM users WHERE role IS NOT NULL AND role <> ''");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $health['stats']['total_roles'] = (int)($result['count'] ?? 0);
} catch (Exception $e) {
    $health['stats']['total_roles'] = null;
}

try {
    $stmt = $db->query("SELECT VERSION() AS version");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $health['environment']['database_version'] = $result['version'] ?? null;
} catch (Exception $e) {
    $health['environment']['database_version'] = null;
}

$missingTables = array_keys(array_filter($health['tables'], fn($info) => !$info['exists']));
$missingFiles = array_keys(array_filter($health['files'], fn($info) => !$info['exists']));
?>

<div class="stack-lg">
    <section class="card border-0 shadow-sm">
        <div class="card-header bg-<?= $overallHealth ? 'success' : 'danger' ?> text-white d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1"><i class="bi bi-<?= $overallHealth ? 'check-circle-fill' : 'x-circle-fill' ?> me-2"></i>System Health</h5>
                <p class="mb-0 small">Status checked at <?= date('Y-m-d H:i:s') ?></p>
            </div>
            <div class="text-end">
                <span class="badge bg-dark">PHP <?= htmlspecialchars($health['environment']['php_version']) ?></span>
                <?php if (!empty($health['environment']['database_version'])): ?>
                    <span class="badge bg-dark ms-2">MySQL <?= htmlspecialchars($health['environment']['database_version']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?php if ($overallHealth): ?>
            <div class="alert alert-success d-flex align-items-start gap-3">
                <i class="bi bi-emoji-laughing fs-3"></i>
                <div>
                    <h6 class="mb-1">All systems operational</h6>
                    <p class="mb-0">Your WAPOS deployment is healthy. Keep monitoring regularly.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-danger d-flex align-items-start gap-3">
                <i class="bi bi-activity fs-3"></i>
                <div>
                    <h6 class="mb-1">Attention required</h6>
                    <p class="mb-0">We detected <?= count($missingTables) + count($missingFiles) ?> critical item(s) that need attention. Review the panels below to resolve them.</p>
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-3">
                    <div class="app-card" data-elevation="md">
                        <p class="text-muted small mb-1">Active Users</p>
                        <h4 class="fw-bold mb-0">
                            <?= $health['stats']['active_users'] !== null ? number_format($health['stats']['active_users']) : '<span class="text-muted">n/a</span>' ?>
                        </h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="app-card" data-elevation="md">
                        <p class="text-muted small mb-1">Distinct Roles</p>
                        <h4 class="fw-bold mb-0">
                            <?= $health['stats']['total_roles'] !== null ? number_format($health['stats']['total_roles']) : '<span class="text-muted">n/a</span>' ?>
                        </h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="app-card" data-elevation="md">
                        <p class="text-muted small mb-1">Tables Online</p>
                        <h4 class="fw-bold mb-0">
                            <?= number_format($health['stats']['total_tables']) ?>
                        </h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="app-card" data-elevation="md">
                        <p class="text-muted small mb-1">Permissions</p>
                        <h4 class="fw-bold mb-0">
                            <?= number_format($health['permission_counts']['modules']) ?> modules / <?= number_format($health['permission_counts']['actions']) ?> actions
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <section class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-database me-2"></i>Database Health</h5>
                    <span class="badge bg-<?= $health['database'] ? 'success' : 'danger' ?>"><?= $health['database'] ? 'Connected' : 'Offline' ?></span>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Server: <?= htmlspecialchars($health['environment']['server_name']) ?> • OS: <?= htmlspecialchars($health['environment']['os']) ?></p>
                    <h6 class="fw-semibold">Essential Tables</h6>
                    <div class="list-group list-group-flush">
                        <?php foreach ($health['tables'] as $table => $info): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-uppercase small text-muted"><?= htmlspecialchars($table) ?></div>
                                    <div><?= htmlspecialchars($info['description']) ?></div>
                                    <?php if ($info['exists'] && isset($info['count'])): ?>
                                        <div class="small text-muted">Records: <?= number_format((int)$info['count']) ?></div>
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
        </section>

        <section class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-gear me-2"></i>System Components</h5>
                    <span class="badge bg-<?= $health['permissions'] ? 'success' : 'danger' ?>">
                        <?= $health['permissions'] ? 'Permissions OK' : 'Permissions Issue' ?>
                    </span>
                </div>
                <div class="card-body">
                    <h6 class="fw-semibold">Core Files</h6>
                    <div class="list-group list-group-flush mb-3">
                        <?php foreach ($health['files'] as $file => $info): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold small text-uppercase text-muted"><?= htmlspecialchars($file) ?></div>
                                    <div><?= htmlspecialchars($info['description']) ?></div>
                                </div>
                                <span class="badge bg-<?= $info['exists'] ? 'success' : 'danger' ?>">
                                    <?= $info['exists'] ? 'Present' : 'Missing' ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!$overallHealth): ?>
                        <h6 class="fw-semibold">Issues Detected</h6>
                        <?php if (empty($missingTables) && empty($missingFiles)): ?>
                            <div class="alert alert-warning">No missing tables or files found. Review the permissions system or database connection.</div>
                        <?php else: ?>
                            <ul class="list-unstyled small">
                                <?php foreach ($missingTables as $table): ?>
                                    <li><i class="bi bi-exclamation-octagon text-danger me-2"></i>Table <strong><?= htmlspecialchars($table) ?></strong> is missing.</li>
                                <?php endforeach; ?>
                                <?php foreach ($missingFiles as $file): ?>
                                    <li><i class="bi bi-file-earmark-excel text-danger me-2"></i>File <strong><?= htmlspecialchars($file) ?></strong> is missing.</li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <section class="card border-0 shadow-sm">
        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-tools me-2"></i>Quick Actions</h5>
            <button class="btn btn-light btn-sm" onclick="location.reload()">
                <i class="bi bi-arrow-repeat me-1"></i>Refresh
            </button>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <a href="fix-system-health-issues.php" class="btn btn-success w-100">
                        <i class="bi bi-wrench-adjustable-circle me-2"></i>Fix Issues
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="permissions.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-shield-lock me-2"></i>Manage Permissions
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="users.php" class="btn btn-outline-success w-100">
                        <i class="bi bi-people me-2"></i>User Directory
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="settings.php" class="btn btn-outline-warning w-100">
                        <i class="bi bi-gear-wide-connected me-2"></i>System Settings
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
