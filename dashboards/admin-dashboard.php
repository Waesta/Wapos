<?php
/**
 * Admin/Developer Dashboard - Complete System Overview
 * Full administrative control and system monitoring
 */

$db = Database::getInstance();

// Admin-specific metrics
$systemStats = [
    'users' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'],
    'total_sales' => $db->fetchOne("SELECT COUNT(*) as count FROM sales")['count'],
    'products' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE is_active = 1")['count'],
    'locations' => $db->fetchOne("SELECT COUNT(*) as count FROM locations WHERE is_active = 1")['count']
];

// Financial overview
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$yearStart = date('Y-01-01');

$financials = [
    'today' => $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE DATE(created_at) = ?", [$today])['revenue'],
    'month' => $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE DATE(created_at) >= ?", [$monthStart])['revenue'],
    'year' => $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE DATE(created_at) >= ?", [$yearStart])['revenue']
];

// System health metrics
$systemHealth = $systemManager->getSystemStatus();

// Recent admin activities
$recentActivities = $db->fetchAll("
    SELECT 
        pal.action_type,
        pal.created_at,
        u.full_name as user_name,
        sm.display_name as module_name,
        pal.risk_level
    FROM permission_audit_log pal
    LEFT JOIN users u ON pal.user_id = u.id
    LEFT JOIN system_modules sm ON pal.module_id = sm.id
    WHERE pal.action_type IN ('permission_changed', 'sensitive_action', 'policy_violation')
    ORDER BY pal.created_at DESC
    LIMIT 10
");

// Low stock alerts
$lowStock = $db->fetchAll("
    SELECT name, stock_quantity, min_stock_level 
    FROM products 
    WHERE stock_quantity <= min_stock_level AND is_active = 1
    ORDER BY stock_quantity ASC
    LIMIT 5
");

// User activity summary
$userActivity = $db->fetchAll("
    SELECT 
        u.full_name,
        u.role,
        COUNT(s.id) as sales_count,
        COALESCE(SUM(s.total_amount), 0) as sales_total,
        MAX(s.created_at) as last_sale
    FROM users u
    LEFT JOIN sales s ON u.id = s.user_id AND DATE(s.created_at) = ?
    WHERE u.is_active = 1
    GROUP BY u.id
    ORDER BY sales_count DESC
    LIMIT 8
", [$today]);

$pageTitle = 'Admin Dashboard';
include '../includes/header.php';
?>

<style>
.metric-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}
.metric-card .metric-value {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}
.health-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 8px;
}
.health-good { background-color: #28a745; }
.health-warning { background-color: #ffc107; }
.health-danger { background-color: #dc3545; }
</style>

<!-- Admin Dashboard Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">
            <i class="bi bi-shield-lock-fill text-primary me-2"></i>
            Administrative Dashboard
        </h2>
        <p class="text-muted mb-0">Complete system overview and control</p>
    </div>
    <div class="btn-group">
        <a href="system-status.php" class="btn btn-outline-primary">
            <i class="bi bi-cpu me-1"></i>System Status
        </a>
        <a href="permissions.php" class="btn btn-outline-success">
            <i class="bi bi-shield-check me-1"></i>Permissions
        </a>
        <a href="users.php" class="btn btn-outline-info">
            <i class="bi bi-people me-1"></i>Users
        </a>
    </div>
</div>

<!-- System Overview Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="metric-card text-center">
            <div class="metric-value"><?= number_format($systemStats['users']) ?></div>
            <div class="metric-label">Active Users</div>
            <small class="opacity-75">System accounts</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card text-center">
            <div class="metric-value"><?= number_format($systemStats['total_sales']) ?></div>
            <div class="metric-label">Total Sales</div>
            <small class="opacity-75">All transactions</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card text-center">
            <div class="metric-value"><?= number_format($systemStats['products']) ?></div>
            <div class="metric-label">Products</div>
            <small class="opacity-75">Active inventory</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card text-center">
            <div class="metric-value"><?= number_format($systemStats['locations']) ?></div>
            <div class="metric-label">Locations</div>
            <small class="opacity-75">Store locations</small>
        </div>
    </div>
</div>

<!-- Financial Overview -->
<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Financial Overview</h5>
            </div>
            <div class="card-body">
                <div class="row text-center g-3">
                    <div class="col-md-4">
                        <div class="border-end">
                            <div class="h3 text-success mb-1"><?= formatMoney($financials['today']) ?></div>
                            <div class="text-muted small">Today's Revenue</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border-end">
                            <div class="h3 text-primary mb-1"><?= formatMoney($financials['month']) ?></div>
                            <div class="text-muted small">This Month</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="h3 text-info mb-1"><?= formatMoney($financials['year']) ?></div>
                        <div class="text-muted small">This Year</div>
                    </div>
                </div>
                
                <hr>
                
                <div class="row g-2">
                    <div class="col-md-6">
                        <a href="reports.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-bar-chart me-2"></i>Detailed Reports
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="accounting.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-calculator me-2"></i>Accounting
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-heart-pulse me-2"></i>System Health</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>System Status</span>
                        <span class="health-indicator <?= $systemHealth['initialized'] ? 'health-good' : 'health-danger' ?>"></span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Modules</span>
                        <span class="badge bg-primary"><?= $systemHealth['modules_count'] ?></span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Actions</span>
                        <span class="badge bg-success"><?= $systemHealth['actions_count'] ?></span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Relationships</span>
                        <span class="badge bg-info"><?= $systemHealth['relationships_count'] ?></span>
                    </div>
                </div>
                
                <a href="system-status.php" class="btn btn-outline-info w-100">
                    <i class="bi bi-gear me-2"></i>Full Diagnostics
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Management Sections -->
<div class="row g-4 mb-4">
    <!-- Recent Admin Activities -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-shield-exclamation me-2"></i>Security Activities</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recentActivities)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentActivities as $activity): ?>
                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="fw-bold small">
                                    <?= ucwords(str_replace('_', ' ', $activity['action_type'])) ?>
                                </div>
                                <div class="text-muted small">
                                    <?= htmlspecialchars($activity['user_name'] ?? 'System') ?>
                                    <?php if ($activity['module_name']): ?>
                                        → <?= htmlspecialchars($activity['module_name']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small">
                                    <?= formatDate($activity['created_at'], 'M j, H:i') ?>
                                </div>
                            </div>
                            <span class="badge bg-<?= 
                                $activity['risk_level'] === 'critical' ? 'danger' : 
                                ($activity['risk_level'] === 'high' ? 'warning' : 'secondary') 
                            ?>">
                                <?= ucfirst($activity['risk_level']) ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-3">
                    <i class="bi bi-shield-check fs-1"></i>
                    <p class="mt-2">No recent security activities</p>
                </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="permissions.php" class="btn btn-outline-warning w-100">
                        <i class="bi bi-eye me-2"></i>View Audit Log
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- User Activity -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>User Activity (Today)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($userActivity)): ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Sales</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userActivity as $user): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold small"><?= htmlspecialchars($user['full_name']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary small"><?= ucfirst($user['role']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?= $user['sales_count'] ?></span>
                                </td>
                                <td class="text-end">
                                    <small><?= formatMoney($user['sales_total']) ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-3">
                    <i class="bi bi-person-x fs-1"></i>
                    <p class="mt-2">No user activity today</p>
                </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="users.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-people me-2"></i>Manage Users
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Inventory Alerts -->
<?php if (!empty($lowStock)): ?>
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm border-start border-danger border-4">
            <div class="card-header bg-light">
                <h5 class="mb-0 text-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Inventory Alerts (<?= count($lowStock) ?> items)
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <?php foreach ($lowStock as $item): ?>
                    <div class="col-md-2">
                        <div class="alert alert-warning mb-0 text-center">
                            <div class="fw-bold small"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="text-danger">
                                <strong><?= $item['stock_quantity'] ?></strong> / <?= $item['min_stock_level'] ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <a href="products.php" class="btn btn-outline-danger">
                        <i class="bi bi-box-seam me-2"></i>Manage Inventory
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Administrative Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-2">
                        <a href="users.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-person-plus d-block fs-4 mb-2"></i>
                            <small>Add User</small>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="permissions.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-shield-plus d-block fs-4 mb-2"></i>
                            <small>Permissions</small>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="products.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-plus-square d-block fs-4 mb-2"></i>
                            <small>Add Product</small>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="locations.php" class="btn btn-outline-warning w-100">
                            <i class="bi bi-geo-alt-fill d-block fs-4 mb-2"></i>
                            <small>Locations</small>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="settings.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-gear-fill d-block fs-4 mb-2"></i>
                            <small>Settings</small>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="system-status.php" class="btn btn-outline-danger w-100">
                            <i class="bi bi-cpu-fill d-block fs-4 mb-2"></i>
                            <small>System</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
