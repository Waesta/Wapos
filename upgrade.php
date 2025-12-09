<?php
/**
 * WAPOS System Upgrade & Database Migration
 * Comprehensive upgrade tool for all system modules
 * 
 * Current Version: 3.0 (Full Enterprise Suite)
 */

require_once 'config.php';

$message = '';
$success = false;
$upgradeSummary = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $scripts = [
            [
                'label' => 'Phase 2 core upgrade',
                'path' => __DIR__ . '/database/phase2-schema.sql',
                'optional' => false,
            ],
            [
                'label' => 'Complete system features',
                'path' => __DIR__ . '/database/complete-system.sql',
                'optional' => true,
            ],
            [
                'label' => 'Accounting core schema',
                'path' => __DIR__ . '/database/accounting-schema.sql',
                'optional' => false,
            ],
            [
                'label' => 'Accounting base data',
                'path' => __DIR__ . '/database/accounting-simple.sql',
                'optional' => false,
            ],
            [
                'label' => 'Accounting IFRS seed data',
                'path' => __DIR__ . '/database/accounting-seed.sql',
                'optional' => false,
            ],
            [
                'label' => 'Accounting IFRS upgrade',
                'path' => __DIR__ . '/database/fix-accounting-module.sql',
                'optional' => false,
            ],
            [
                'label' => 'Granular permissions schema',
                'path' => __DIR__ . '/database/permissions-schema.sql',
                'optional' => false,
            ],
            [
                'label' => 'Dynamic delivery pricing schema',
                'path' => __DIR__ . '/database/migrations/003_delivery_dynamic_pricing.sql',
                'optional' => false,
            ],
            [
                'label' => 'Restaurant billing enhancements',
                'path' => __DIR__ . '/database/migrations/004_restaurant_billing.sql',
                'optional' => false,
            ],
            [
                'label' => 'Notification system tables',
                'path' => __DIR__ . '/database/migrations/004_add_notifications.sql',
                'optional' => false,
            ],
            [
                'label' => 'Bar management and portion tracking',
                'path' => __DIR__ . '/database/migrations/010_bar_management.sql',
                'optional' => false,
            ],
            [
                'label' => 'Housekeeping inventory management',
                'path' => __DIR__ . '/database/migrations/011_housekeeping_inventory.sql',
                'optional' => false,
            ],
        ];

        $totalExecuted = 0;
        $totalSkipped = 0;
        $errors = [];

        foreach ($scripts as $script) {
            if (!file_exists($script['path'])) {
                if (!empty($script['optional'])) {
                    continue;
                }
                throw new Exception('Required upgrade file missing: ' . basename($script['path']));
            }

            if (basename($script['path']) === 'permissions-schema.sql') {
                ensurePermissionPrerequisites($pdo, $errors, $totalExecuted, $totalSkipped);
            }

            $sql = file_get_contents($script['path']);
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function ($stmt) {
                    if ($stmt === '') {
                        return false;
                    }
                    $stripped = ltrim($stmt);
                    return !str_starts_with($stripped, '--') && !str_starts_with($stripped, '/*');
                }
            );

            $executed = 0;
            $skipped = 0;

            foreach ($statements as $statement) {
                if ($statement === '') {
                    continue;
                }

                try {
                    $pdo->exec($statement);
                    $executed++;
                    $totalExecuted++;
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'already exists') !== false ||
                        strpos($e->getMessage(), 'Duplicate') !== false ||
                        strpos($e->getMessage(), 'Unknown column') !== false) {
                        $skipped++;
                        $totalSkipped++;
                        continue;
                    }

                    $errors[] = $script['label'] . ': ' . $e->getMessage();
                }
            }

            $upgradeSummary[] = [
                'label' => $script['label'],
                'executed' => $executed,
                'skipped' => $skipped,
            ];
        }

        ensureDynamicPricingTables($pdo, $upgradeSummary, $errors, $totalExecuted, $totalSkipped);
        ensureEnhancedRolesAndPermissions($pdo, $upgradeSummary, $errors, $totalExecuted, $totalSkipped);

        $success = empty($errors);
        $message = $success
            ? "Upgrade completed! Executed {$totalExecuted} statements (skipped {$totalSkipped})."
            : "Upgrade completed with warnings. Executed {$totalExecuted} statements (skipped {$totalSkipped}).";

        if (!empty($errors)) {
            $message .= ' Review the following issues: ' . implode(' | ', array_slice($errors, 0, 3));
        }

    } catch (Throwable $e) {
        $success = false;
        $message = 'Upgrade failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 50px 0;
        }
        .upgrade-card {
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card upgrade-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-gear-wide-connected text-primary" style="font-size: 4rem;"></i>
                            <h2 class="mt-3 fw-bold"><?= APP_NAME ?> System Upgrade</h2>
                            <p class="text-muted">Database Migration & Module Installation Tool</p>
                            <span class="badge bg-success">Version 3.0 - Enterprise Suite</span>
                        </div>

                        <?php if ($message): ?>
                            <div class="alert alert-<?= $success ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                                <i class="bi bi-<?= $success ? 'check-circle' : 'exclamation-triangle' ?>-fill me-2"></i>
                                <?= htmlspecialchars($message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-check-circle text-success me-2"></i>System Fully Upgraded!</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-primary mt-2">Core Modules</h6>
                                            <ul class="small mb-0">
                                                <li>✅ Point of Sale (POS)</li>
                                                <li>✅ Inventory Management</li>
                                                <li>✅ Customer & Loyalty</li>
                                                <li>✅ IFRS Accounting</li>
                                                <li>✅ Reports & Analytics</li>
                                            </ul>
                                            <h6 class="text-primary mt-3">Hospitality</h6>
                                            <ul class="small mb-0">
                                                <li>✅ Restaurant Orders & KDS</li>
                                                <li>✅ Bar & Beverage (Portions)</li>
                                                <li>✅ Digital Menu & QR Codes</li>
                                                <li>✅ Room Booking & Folios</li>
                                                <li>✅ Housekeeping Tasks</li>
                                                <li>✅ Housekeeping Inventory</li>
                                                <li>✅ Maintenance Requests</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-primary mt-2">Operations</h6>
                                            <ul class="small mb-0">
                                                <li>✅ Delivery & Tracking</li>
                                                <li>✅ Dynamic Delivery Pricing</li>
                                                <li>✅ Multi-location Support</li>
                                                <li>✅ Supplier Management</li>
                                                <li>✅ Purchase Orders & GRN</li>
                                            </ul>
                                            <h6 class="text-primary mt-3">Communication</h6>
                                            <ul class="small mb-0">
                                                <li>✅ Email Notifications</li>
                                                <li>✅ SMS Marketing</li>
                                                <li>✅ WhatsApp Integration</li>
                                                <li>✅ Marketing Campaigns</li>
                                            </ul>
                                            <h6 class="text-primary mt-3">Security</h6>
                                            <ul class="small mb-0">
                                                <li>✅ Role-based Access</li>
                                                <li>✅ Granular Permissions</li>
                                                <li>✅ Audit Logging</li>
                                                <li>✅ Backup & Restore</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($upgradeSummary)): ?>
                                <div class="card border-success mb-4">
                                    <div class="card-body">
                                        <h6 class="card-title text-success"><i class="bi bi-journal-check me-2"></i>What was applied</h6>
                                        <ul class="mb-0 small">
                                            <?php foreach ($upgradeSummary as $item): ?>
                                                <li>
                                                    <strong><?= htmlspecialchars($item['label']) ?>:</strong>
                                                    <?= (int) $item['executed'] ?> executed,
                                                    <?= (int) $item['skipped'] ?> skipped duplicates
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="d-grid gap-2">
                                <a href="index.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-house-door me-2"></i>Go to Dashboard
                                </a>
                                <a href="restaurant.php" class="btn btn-outline-success">
                                    <i class="bi bi-shop me-2"></i>Try Restaurant Module
                                </a>
                                <a href="rooms.php" class="btn btn-outline-info">
                                    <i class="bi bi-building me-2"></i>Try Room Booking
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-database-gear text-info me-2"></i>Database Migrations</h5>
                                    <p class="small text-muted mb-2">This will apply all pending database migrations:</p>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <ul class="small mb-0">
                                                <li>Core system schema</li>
                                                <li>IFRS accounting tables</li>
                                                <li>Granular permissions</li>
                                                <li>Restaurant & modifiers</li>
                                                <li>Room booking system</li>
                                                <li>Delivery & pricing</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <ul class="small mb-0">
                                                <li>Notification system</li>
                                                <li>Marketing campaigns</li>
                                                <li>Bar & beverage tracking</li>
                                                <li>Housekeeping inventory</li>
                                                <li>Audit & security logs</li>
                                                <li>130+ database tables</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-warning mb-4">
                                <div class="card-body">
                                    <h6 class="card-title text-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>Before Running:</h6>
                                    <ol class="mb-0 small">
                                        <li>Backup your database first</li>
                                        <li>Ensure MySQL is running</li>
                                        <li>Existing data will be preserved</li>
                                        <li>Safe to run multiple times (idempotent)</li>
                                    </ol>
                                </div>
                            </div>

                            <form method="POST">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-database-gear me-2"></i>Run Migrations
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-center mt-3 text-white">
                    <small>&copy; <?= date('Y') ?> <?= APP_NAME ?> - Enterprise Suite v3.0</small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php

function ensureDynamicPricingTables(PDO $pdo, array &$upgradeSummary, array &$errors, int &$totalExecuted, int &$totalSkipped): void
{
    $dynamicExecuted = 0;
    $dynamicSkipped = 0;

    $tables = [
        'delivery_pricing_rules' => <<<SQL
CREATE TABLE IF NOT EXISTS delivery_pricing_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(100) NOT NULL,
    priority INT UNSIGNED NOT NULL DEFAULT 1,
    distance_min_km DECIMAL(8,2) NOT NULL DEFAULT 0,
    distance_max_km DECIMAL(8,2) NULL,
    base_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    per_km_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    surcharge_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    notes TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_distance_range (distance_min_km, distance_max_km),
    KEY idx_delivery_pricing_priority (priority),
    KEY idx_delivery_pricing_active (is_active)
) ENGINE=InnoDB
SQL,
        'delivery_distance_cache' => <<<SQL
CREATE TABLE IF NOT EXISTS delivery_distance_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    origin_hash CHAR(64) NOT NULL,
    destination_hash CHAR(64) NOT NULL,
    origin_lat DECIMAL(10,8) NOT NULL,
    origin_lng DECIMAL(11,8) NOT NULL,
    destination_lat DECIMAL(10,8) NOT NULL,
    destination_lng DECIMAL(11,8) NOT NULL,
    provider VARCHAR(50) NOT NULL,
    distance_m INT UNSIGNED NOT NULL,
    duration_s INT UNSIGNED DEFAULT NULL,
    response_payload JSON,
    cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    KEY idx_origin_destination_hash (origin_hash, destination_hash),
    KEY idx_expires_at (expires_at)
) ENGINE=InnoDB
SQL,
        'delivery_pricing_audit' => <<<SQL
CREATE TABLE IF NOT EXISTS delivery_pricing_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED DEFAULT NULL,
    request_id CHAR(36) NOT NULL,
    provider VARCHAR(50) NOT NULL,
    rule_id INT UNSIGNED DEFAULT NULL,
    distance_m INT UNSIGNED DEFAULT NULL,
    duration_s INT UNSIGNED DEFAULT NULL,
    fee_applied DECIMAL(10,2) DEFAULT NULL,
    api_calls INT UNSIGNED DEFAULT 0,
    cache_hit TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    INDEX idx_created_at (created_at),
    INDEX idx_rule_id (rule_id)
) ENGINE=InnoDB
SQL,
    ];

    foreach ($tables as $name => $createSql) {
        if (!tableExists($pdo, $name)) {
            try {
                $pdo->exec($createSql);
                $dynamicExecuted++;
                $totalExecuted++;
            } catch (Throwable $e) {
                $errors[] = 'Dynamic pricing verification: ' . $e->getMessage();
            }
        } else {
            $dynamicSkipped++;
            $totalSkipped++;
        }
    }

    // Ensure required columns exist even if tables predated this upgrade
    $requiredColumns = [
        'delivery_pricing_audit' => [
            'cache_hit' => 'ALTER TABLE delivery_pricing_audit ADD COLUMN cache_hit TINYINT(1) DEFAULT 0 AFTER api_calls',
            'fallback_used' => 'ALTER TABLE delivery_pricing_audit ADD COLUMN fallback_used TINYINT(1) DEFAULT 0 AFTER cache_hit',
        ],
    ];

    foreach ($requiredColumns as $table => $columns) {
        foreach ($columns as $column => $alterStatement) {
            if (!columnExists($pdo, $table, $column)) {
                try {
                    $pdo->exec($alterStatement);
                    $dynamicExecuted++;
                    $totalExecuted++;
                } catch (Throwable $e) {
                    $errors[] = sprintf('Dynamic pricing column %s.%s: %s', $table, $column, $e->getMessage());
                }
            } else {
                $dynamicSkipped++;
                $totalSkipped++;
            }
        }
    }

    // Seed default rule
    try {
        $insertSql = "INSERT INTO delivery_pricing_rules (rule_name, priority, distance_min_km, distance_max_km, base_fee, per_km_fee, surcharge_percent, notes)
            SELECT 'Default 0-5km', 1, 0.00, 5.00, 50.00, 0.00, 0.00, 'Initial default bracket'
            WHERE NOT EXISTS (SELECT 1 FROM delivery_pricing_rules)";
        $affected = $pdo->exec($insertSql);
        if ($affected !== false && $affected > 0) {
            $dynamicExecuted++;
            $totalExecuted++;
        }
    } catch (Throwable $e) {
        $errors[] = 'Dynamic pricing default rule: ' . $e->getMessage();
    }

    $upgradeSummary[] = [
        'label' => 'Dynamic delivery pricing verification',
        'executed' => $dynamicExecuted,
        'skipped' => $dynamicSkipped,
    ];
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return !empty($row['total']);
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return !empty($row['total']);
}

function ensureEnhancedRolesAndPermissions(PDO $pdo, array &$upgradeSummary, array &$errors, int &$totalExecuted, int &$totalSkipped): void
{
    $executed = 0;
    $skipped = 0;

    $expectedRoles = [
        'admin',
        'manager',
        'accountant',
        'cashier',
        'waiter',
        'inventory_manager',
        'rider',
        'frontdesk',
        'housekeeping_manager',
        'housekeeping_staff',
        'maintenance_manager',
        'maintenance_staff',
        'technician',
        'engineer',
        'developer'
    ];

    $enumList = "'" . implode("','", array_map(fn($role) => addslashes($role), $expectedRoles)) . "'";
    try {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM($enumList) NOT NULL DEFAULT 'cashier'");
        $executed++;
        $totalExecuted++;
    } catch (Throwable $e) {
        $errors[] = 'Role enum update: ' . $e->getMessage();
    }

    if (!tableExists($pdo, 'permission_modules') || !tableExists($pdo, 'permission_actions')) {
        $errors[] = 'Permission tables are missing. Ensure permissions-schema.sql executed successfully.';
        $upgradeSummary[] = [
            'label' => 'Enhanced role & permission provisioning',
            'executed' => $executed,
            'skipped' => ++$skipped,
        ];
        $totalSkipped++;
        return;
    }

    $defaultModules = [
        ['pos', 'Point of Sale', 'POS transactions and sales'],
        ['inventory', 'Inventory Management', 'Stock control and purchasing'],
        ['accounting', 'Accounting & Finance', 'Financial management and reporting'],
        ['users', 'User Management', 'User accounts and permissions'],
        ['customers', 'Customer Management', 'Customer data and CRM'],
        ['reports', 'Reports & Analytics', 'Business intelligence and reporting'],
        ['settings', 'System Settings', 'System configuration and preferences'],
        ['rooms', 'Room Management', 'Hotel room bookings and management'],
        ['restaurant', 'Restaurant Operations', 'Table service and kitchen management'],
        ['delivery', 'Delivery Management', 'Order delivery and logistics'],
        ['housekeeping', 'Housekeeping', 'Scheduling, room status, and task execution'],
        ['maintenance', 'Maintenance', 'Issue tracking, technician dispatch, and resolution'],
        ['frontdesk', 'Front Desk', 'Guest services, check-ins, and concierge workflows']
    ];

    $insertModuleStmt = $pdo->prepare(
        "INSERT INTO permission_modules (module_key, module_name, description, is_active)
         VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE module_name = VALUES(module_name), description = VALUES(description), is_active = VALUES(is_active)"
    );

    foreach ($defaultModules as $module) {
        try {
            $insertModuleStmt->execute($module);
            $executed++;
            $totalExecuted++;
        } catch (Throwable $e) {
            $errors[] = 'Permission module ' . $module[0] . ': ' . $e->getMessage();
        }
    }

    $moduleActionDefinitions = [
        'pos' => [
            ['view', 'View POS', 'View POS transactions and reports'],
            ['create', 'Create Sale', 'Create new POS transactions'],
            ['update', 'Update Sale', 'Modify existing POS transactions'],
            ['refund', 'Issue Refunds', 'Process POS refunds'],
            ['void', 'Void Sale', 'Void POS transactions']
        ],
        'inventory' => [
            ['view', 'View Inventory', 'View stock levels and movements'],
            ['create', 'Create Inventory Entry', 'Add new inventory records'],
            ['update', 'Update Inventory', 'Modify inventory records'],
            ['adjust_inventory', 'Adjust Inventory', 'Execute stock adjustments']
        ],
        'housekeeping' => [
            ['view', 'View Tasks', 'Access housekeeping dashboards and tasks'],
            ['create', 'Create Task', 'Create new housekeeping tasks'],
            ['update', 'Update Task', 'Update housekeeping tasks'],
            ['assign', 'Assign Task', 'Assign or reassign housekeeping tasks'],
            ['complete', 'Complete Task', 'Mark housekeeping tasks complete']
        ],
        'maintenance' => [
            ['view', 'View Requests', 'Access maintenance requests'],
            ['create', 'Create Request', 'Log new maintenance issues'],
            ['update', 'Update Request', 'Update maintenance request details'],
            ['assign', 'Assign Request', 'Assign maintenance requests to staff'],
            ['resolve', 'Resolve Request', 'Mark maintenance requests resolved']
        ],
        'frontdesk' => [
            ['view', 'View Front Desk', 'Access front desk dashboards'],
            ['create', 'Create Record', 'Create guest or stay records'],
            ['update', 'Update Record', 'Update guest or stay records']
        ],
        'users' => [
            ['view', 'View Users', 'View user directory'],
            ['create', 'Create User', 'Create new user accounts'],
            ['update', 'Update User', 'Update existing user accounts'],
            ['change_permissions', 'Change Permissions', 'Adjust user/group permissions']
        ]
    ];

    $getModuleIdStmt = $pdo->prepare("SELECT id FROM permission_modules WHERE module_key = ?");
    $insertActionStmt = $pdo->prepare(
        "INSERT INTO permission_actions (module_id, action_key, action_name, description, is_active)
         VALUES (?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE action_name = VALUES(action_name), description = VALUES(description), is_active = VALUES(is_active)"
    );

    foreach ($moduleActionDefinitions as $moduleKey => $actions) {
        if (!$getModuleIdStmt->execute([$moduleKey])) {
            $errors[] = 'Permission module lookup failed for ' . $moduleKey;
            continue;
        }

        $moduleId = $getModuleIdStmt->fetchColumn();
        if (!$moduleId) {
            $errors[] = 'Permission module missing for key ' . $moduleKey;
            continue;
        }

        foreach ($actions as $action) {
            try {
                [$actionKey, $actionName, $description] = $action;
                $insertActionStmt->execute([$moduleId, $actionKey, $actionName, $description]);
                $executed++;
                $totalExecuted++;
            } catch (Throwable $e) {
                $errors[] = sprintf('Permission action %s:%s - %s', $moduleKey, $action[0], $e->getMessage());
            }
        }
    }

    $upgradeSummary[] = [
        'label' => 'Enhanced role & permission provisioning',
        'executed' => $executed,
        'skipped' => $skipped,
    ];
}

function ensurePermissionPrerequisites(PDO $pdo, array &$errors, int &$totalExecuted, int &$totalSkipped): void
{
    $table = 'user_sessions';
    if (tableExists($pdo, $table)) {
        $totalSkipped++;
        return;
    }

    $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
        id VARCHAR(128) PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        location_id INT UNSIGNED,
        device_fingerprint VARCHAR(255),
        two_factor_verified TINYINT(1) DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
    ) ENGINE=InnoDB";

    try {
        $pdo->exec($sql);
        $totalExecuted++;
    } catch (Throwable $e) {
        $errors[] = 'Permission prerequisite user_sessions: ' . $e->getMessage();
    }
}

