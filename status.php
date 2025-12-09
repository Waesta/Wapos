<?php
/**
 * WAPOS System Status Dashboard
 * Shows current system state, installed modules, and database statistics
 * 
 * Version: 3.0 Enterprise Suite
 */
require_once __DIR__ . '/includes/bootstrap.php';

// System status is restricted to admin roles
if (!$auth->isLoggedIn() || !in_array($auth->getRole(), ['developer', 'super_admin', 'admin'])) {
    $_SESSION['error_message'] = 'Access denied. Admin privileges required.';
    redirectToDashboard($auth);
}

$db = Database::getInstance();

// Count total tables
$tableCount = (int)($db->fetchOne("SELECT COUNT(*) as cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")['cnt'] ?? 0);

// Get key module table counts
$moduleTables = [
    'Core' => ['users', 'settings', 'products', 'categories', 'sales', 'customers'],
    'Restaurant' => ['orders', 'order_items', 'restaurant_tables', 'modifiers', 'table_reservations'],
    'Rooms' => ['rooms', 'room_types', 'room_bookings', 'room_folios'],
    'Delivery' => ['deliveries', 'riders', 'delivery_pricing_rules', 'delivery_zones'],
    'Inventory' => ['inventory_items', 'stock_movements', 'purchase_orders', 'goods_received_notes'],
    'Accounting' => ['accounts', 'journal_entries', 'journal_entry_lines', 'expense_categories'],
    'Notifications' => ['notification_logs', 'email_templates', 'sms_templates', 'marketing_campaigns'],
    'Bar' => ['bar_recipes', 'bar_open_stock', 'bar_pour_log', 'product_portions'],
    'Housekeeping' => ['housekeeping_tasks', 'housekeeping_inventory', 'housekeeping_linen', 'housekeeping_laundry_batches'],
];

$moduleStatus = [];
foreach ($moduleTables as $module => $tables) {
    $installed = 0;
    foreach ($tables as $table) {
        $exists = $db->fetchOne("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$table]);
        if ($exists) $installed++;
    }
    $moduleStatus[$module] = [
        'installed' => $installed,
        'total' => count($tables),
        'complete' => $installed === count($tables)
    ];
}

// Get record counts for key tables
$stats = [
    'users' => (int)($db->fetchOne("SELECT COUNT(*) as cnt FROM users")['cnt'] ?? 0),
    'products' => (int)($db->fetchOne("SELECT COUNT(*) as cnt FROM products")['cnt'] ?? 0),
    'sales' => (int)($db->fetchOne("SELECT COUNT(*) as cnt FROM sales")['cnt'] ?? 0),
    'customers' => (int)($db->fetchOne("SELECT COUNT(*) as cnt FROM customers")['cnt'] ?? 0),
    'orders' => (int)($db->fetchOne("SELECT COUNT(*) as cnt FROM orders")['cnt'] ?? 0),
];

// Check for housekeeping inventory tables
$hkInventoryExists = $db->fetchOne("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'housekeeping_inventory'");

$pageTitle = 'System Status';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - System Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 30px 0;
        }
        .status-card {
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .module-badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
        }
        .stat-card {
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card status-card">
                    <div class="card-body p-4">
                        <!-- Header -->
                        <div class="text-center mb-4">
                            <i class="bi bi-shield-check text-success" style="font-size: 4rem;"></i>
                            <h1 class="mt-3 fw-bold"><?= APP_NAME ?> System Status</h1>
                            <p class="text-muted mb-2">Enterprise Suite v3.0</p>
                            <span class="badge bg-success fs-6">
                                <i class="bi bi-database me-1"></i><?= number_format($tableCount) ?> Database Tables
                            </span>
                        </div>

                        <!-- Quick Stats -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-2 col-4">
                                <div class="card stat-card bg-primary text-white text-center p-3">
                                    <div class="fs-3 fw-bold"><?= number_format($stats['users']) ?></div>
                                    <small>Users</small>
                                </div>
                            </div>
                            <div class="col-md-2 col-4">
                                <div class="card stat-card bg-success text-white text-center p-3">
                                    <div class="fs-3 fw-bold"><?= number_format($stats['products']) ?></div>
                                    <small>Products</small>
                                </div>
                            </div>
                            <div class="col-md-2 col-4">
                                <div class="card stat-card bg-info text-white text-center p-3">
                                    <div class="fs-3 fw-bold"><?= number_format($stats['customers']) ?></div>
                                    <small>Customers</small>
                                </div>
                            </div>
                            <div class="col-md-2 col-4">
                                <div class="card stat-card bg-warning text-dark text-center p-3">
                                    <div class="fs-3 fw-bold"><?= number_format($stats['sales']) ?></div>
                                    <small>Sales</small>
                                </div>
                            </div>
                            <div class="col-md-2 col-4">
                                <div class="card stat-card bg-danger text-white text-center p-3">
                                    <div class="fs-3 fw-bold"><?= number_format($stats['orders']) ?></div>
                                    <small>Orders</small>
                                </div>
                            </div>
                            <div class="col-md-2 col-4">
                                <div class="card stat-card bg-dark text-white text-center p-3">
                                    <div class="fs-3 fw-bold"><?= number_format($tableCount) ?></div>
                                    <small>Tables</small>
                                </div>
                            </div>
                        </div>

                        <!-- Module Status -->
                        <div class="card bg-light mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Module Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <?php foreach ($moduleStatus as $module => $status): ?>
                                    <div class="col-md-4 col-6">
                                        <div class="d-flex align-items-center justify-content-between p-2 border rounded <?= $status['complete'] ? 'border-success bg-success bg-opacity-10' : 'border-warning bg-warning bg-opacity-10' ?>">
                                            <span class="fw-semibold"><?= htmlspecialchars($module) ?></span>
                                            <span class="badge <?= $status['complete'] ? 'bg-success' : 'bg-warning text-dark' ?> module-badge">
                                                <?= $status['installed'] ?>/<?= $status['total'] ?>
                                                <?= $status['complete'] ? '✓' : '' ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- System Features -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><i class="bi bi-check2-all me-2"></i>Core Features</h6>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled mb-0">
                                            <li>✅ Point of Sale (POS)</li>
                                            <li>✅ Inventory Management</li>
                                            <li>✅ Customer & Loyalty Programs</li>
                                            <li>✅ IFRS Accounting</li>
                                            <li>✅ Multi-location Support</li>
                                            <li>✅ Role-based Access Control</li>
                                            <li>✅ Audit Logging</li>
                                            <li>✅ Backup & Restore</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0"><i class="bi bi-building me-2"></i>Hospitality Suite</h6>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled mb-0">
                                            <li>✅ Restaurant Orders & KDS</li>
                                            <li>✅ Bar & Beverage (Portions/Tots)</li>
                                            <li>✅ Bar Tabs & BOT System</li>
                                            <li>✅ Bar KDS & Floor Plan</li>
                                            <li>✅ Happy Hour Automation</li>
                                            <li>✅ Digital Menu & QR Codes</li>
                                            <li>✅ Room Booking & Folios</li>
                                            <li>✅ Housekeeping Inventory</li>
                                            <li>✅ Employee Time Clock</li>
                                            <li>✅ Delivery & Tracking</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Links -->
                        <div class="card bg-light mb-4">
                            <div class="card-body">
                                <h6 class="card-title"><i class="bi bi-link-45deg me-2"></i>Quick Access</h6>
                                <div class="row g-2">
                                    <div class="col-md-3 col-6">
                                        <a href="upgrade.php" class="btn btn-primary w-100">
                                            <i class="bi bi-database-gear me-1"></i> Migrations
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <a href="system-health.php" class="btn btn-info w-100">
                                            <i class="bi bi-heart-pulse me-1"></i> Health Check
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <a href="settings.php" class="btn btn-secondary w-100">
                                            <i class="bi bi-gear me-1"></i> Settings
                                        </a>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <a href="dashboards/admin.php" class="btn btn-success w-100">
                                            <i class="bi bi-house me-1"></i> Dashboard
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Server Info -->
                        <div class="card border-secondary">
                            <div class="card-body">
                                <h6 class="card-title"><i class="bi bi-server me-2"></i>Server Information</h6>
                                <div class="row small">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>PHP Version:</strong> <?= phpversion() ?></p>
                                        <p class="mb-1"><strong>Server:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Database:</strong> <?= DB_NAME ?></p>
                                        <p class="mb-1"><strong>Timezone:</strong> <?= date_default_timezone_get() ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
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
