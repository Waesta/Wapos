<?php
/**
 * Admin/Developer Dashboard - Complete System Overview
 * Full administrative control and system monitoring
 */

require_once '../includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance();

// Admin-specific metrics
$systemStats = [
    'users' => ($db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1") ?: ['count' => 0])['count'],
    'total_sales' => ($db->fetchOne("SELECT COUNT(*) as count FROM sales") ?: ['count' => 0])['count'],
    'products' => ($db->fetchOne("SELECT COUNT(*) as count FROM products WHERE is_active = 1") ?: ['count' => 0])['count'],
    'locations' => ($db->fetchOne("SELECT COUNT(*) as count FROM locations WHERE is_active = 1") ?: ['count' => 0])['count']
];

// Financial overview
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$yearStart = date('Y-01-01');

$financials = [
    'today' => ($db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE DATE(created_at) = ?", [$today]) ?: ['revenue' => 0])['revenue'],
    'month' => ($db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE DATE(created_at) >= ?", [$monthStart]) ?: ['revenue' => 0])['revenue'],
    'year' => ($db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE DATE(created_at) >= ?", [$yearStart]) ?: ['revenue' => 0])['revenue']
];

// System health metrics (simplified)
$systemHealth = [
    'database' => true,  // If we got here, database is working
    'status' => 'operational',
    'initialized' => true,
    'modules_count' => ($db->fetchOne("SELECT COUNT(*) as count FROM permission_modules") ?: ['count' => 0])['count'],
    'actions_count' => ($db->fetchOne("SELECT COUNT(*) as count FROM permission_actions") ?: ['count' => 0])['count'],
    'relationships_count' => ($db->fetchOne("SELECT COUNT(*) as count FROM user_permissions") ?: ['count' => 0])['count']
];

// Recent admin activities
$recentActivities = $db->fetchAll("
    SELECT 
        pal.action_type,
        COALESCE(sm.display_name, sm.name, 'General') AS module_name,
        pal.created_at,
        u.full_name as user_name,
        pal.risk_level
    FROM permission_audit_log pal
    LEFT JOIN users u ON pal.user_id = u.id
    LEFT JOIN system_modules sm ON pal.module_id = sm.id
    WHERE pal.action_type IN ('permission_changed', 'sensitive_action', 'policy_violation')
    ORDER BY pal.created_at DESC
    LIMIT 10
") ?: [];

// Low stock alerts
$lowStock = $db->fetchAll("
    SELECT name, stock_quantity, min_stock_level 
    FROM products 
    WHERE stock_quantity <= min_stock_level AND is_active = 1
    ORDER BY stock_quantity ASC
    LIMIT 5
") ?: [];

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
    .admin-shell {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xl);
    }
    .admin-toolbar {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: var(--spacing-md);
    }
    .admin-toolbar h1 {
        margin: 0;
        font-size: var(--text-2xl);
    }
    .admin-toolbar p {
        margin: 0;
        color: var(--color-text-muted);
    }
    .admin-metrics {
        display: grid;
        gap: var(--spacing-md);
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
    .admin-metric-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    .admin-metric-card h3 {
        font-size: var(--text-2xl);
        margin: 0;
    }
    .admin-metric-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: var(--radius-pill);
        font-size: var(--text-lg);
    }
    .admin-layout {
        display: grid;
        gap: var(--spacing-lg);
    }
    @media (min-width: 1200px) {
        .admin-layout {
            grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
        }
    }
    .admin-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
    }
    .admin-card header {
        padding: var(--spacing-md);
        border-bottom: 1px solid var(--color-border-subtle);
    }
    .admin-card header h5,
    .admin-card header h6 {
        margin: 0;
    }
    .admin-card .card-body {
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-md);
    }
    .admin-financial-grid {
        display: grid;
        gap: var(--spacing-md);
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }
    .admin-financial-cell {
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--radius-md);
        background: var(--color-surface-subtle);
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xs);
    }
    .admin-actions {
        display: grid;
        gap: var(--spacing-sm);
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
    .admin-health-list {
        display: grid;
        gap: var(--spacing-sm);
    }
    .admin-health-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--spacing-sm) var(--spacing-md);
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--radius-md);
        background: var(--color-surface-subtle);
    }
    .admin-health-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
    }
    .admin-health-dot--good { background-color: var(--color-success); }
    .admin-health-dot--warn { background-color: var(--color-warning); }
    .admin-health-dot--bad { background-color: var(--color-danger); }
    .admin-activity-list {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    .admin-activity-item {
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--radius-md);
        padding: var(--spacing-sm) var(--spacing-md);
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: var(--spacing-md);
        background: var(--color-surface-subtle);
    }
    .admin-activity-meta {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xxs);
    }
    .admin-grid {
        display: grid;
        gap: var(--spacing-lg);
    }
    @media (min-width: 992px) {
        .admin-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    .admin-inventory-grid {
        display: grid;
        gap: var(--spacing-sm);
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    .admin-quick-actions {
        display: grid;
        gap: var(--spacing-sm);
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    }
</style>

<div class="admin-shell container-fluid py-4">
    <section class="admin-toolbar">
        <div class="stack-sm">
            <h1><i class="bi bi-shield-lock-fill text-primary me-2"></i>Administrative Dashboard</h1>
            <p>Complete system overview and control center for admins and developers.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= APP_URL ?>/status.php" class="btn btn-outline-primary btn-icon">
                <i class="bi bi-cpu"></i>System Status
            </a>
            <a href="<?= APP_URL ?>/permissions.php" class="btn btn-outline-success btn-icon">
                <i class="bi bi-shield-check"></i>Permissions
            </a>
            <a href="<?= APP_URL ?>/users.php" class="btn btn-outline-info btn-icon">
                <i class="bi bi-people"></i>Users
            </a>
            <a href="<?= APP_URL ?>/products.php" class="btn btn-outline-secondary btn-icon">
                <i class="bi bi-box"></i>Products
            </a>
            <a href="<?= APP_URL ?>/locations.php" class="btn btn-outline-warning btn-icon">
                <i class="bi bi-geo-alt"></i>Locations
            </a>
            <a href="<?= APP_URL ?>/settings.php" class="btn btn-outline-dark btn-icon">
                <i class="bi bi-gear"></i>Settings
            </a>
        </div>
    </section>

    <section class="admin-metrics">
        <article class="admin-metric-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="stack-xs">
                    <span class="text-muted text-uppercase small">Active Users</span>
                    <h3><?= number_format($systemStats['users'] ?? 0) ?></h3>
                </div>
                <span class="admin-metric-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-people"></i>
                </span>
            </div>
            <span class="text-muted small">Accounts with access today.</span>
        </article>
        <article class="admin-metric-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="stack-xs">
                    <span class="text-muted text-uppercase small">Total Sales</span>
                    <h3><?= number_format($systemStats['total_sales'] ?? 0) ?></h3>
                </div>
                <span class="admin-metric-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-receipt"></i>
                </span>
            </div>
            <span class="text-muted small">All-time transactions recorded.</span>
        </article>
        <article class="admin-metric-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="stack-xs">
                    <span class="text-muted text-uppercase small">Products</span>
                    <h3><?= number_format($systemStats['products'] ?? 0) ?></h3>
                </div>
                <span class="admin-metric-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-box-seam"></i>
                </span>
            </div>
            <span class="text-muted small">Active inventory items.</span>
        </article>
        <article class="admin-metric-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="stack-xs">
                    <span class="text-muted text-uppercase small">Locations</span>
                    <h3><?= number_format($systemStats['locations'] ?? 0) ?></h3>
                </div>
                <span class="admin-metric-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-geo"></i>
                </span>
            </div>
            <span class="text-muted small">Operational venues online.</span>
        </article>
    </section>

    <section class="admin-layout">
        <article class="admin-card">
            <header>
                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Financial Overview</h5>
            </header>
            <div class="card-body">
                <div class="admin-financial-grid">
                    <div class="admin-financial-cell">
                        <small class="text-muted text-uppercase">Today's Revenue</small>
                        <h4 class="text-success mb-0"><?= formatMoney($financials['today'] ?? 0) ?></h4>
                    </div>
                    <div class="admin-financial-cell">
                        <small class="text-muted text-uppercase">Month to Date</small>
                        <h4 class="text-primary mb-0"><?= formatMoney($financials['month'] ?? 0) ?></h4>
                    </div>
                    <div class="admin-financial-cell">
                        <small class="text-muted text-uppercase">Year to Date</small>
                        <h4 class="text-info mb-0"><?= formatMoney($financials['year'] ?? 0) ?></h4>
                    </div>
                </div>
                <div class="admin-actions">
                    <a href="<?= APP_URL ?>/reports.php" class="btn btn-outline-primary btn-icon">
                        <i class="bi bi-bar-chart"></i>Detailed Reports
                    </a>
                    <a href="<?= APP_URL ?>/accounting.php" class="btn btn-outline-success btn-icon">
                        <i class="bi bi-calculator"></i>Accounting Suite
                    </a>
                </div>
            </div>
        </article>

        <div class="stack-lg">
            <article class="admin-card">
                <header>
                    <h6 class="mb-0"><i class="bi bi-heart-pulse me-2"></i>System Health</h6>
                </header>
                <div class="card-body">
                    <div class="admin-health-list">
                        <div class="admin-health-item">
                            <span>Initialization</span>
                            <span class="admin-health-dot <?= $systemHealth['initialized'] ? 'admin-health-dot--good' : 'admin-health-dot--bad' ?>"></span>
                        </div>
                        <div class="admin-health-item">
                            <span>Modules</span>
                            <span class="badge bg-primary"><?= number_format($systemHealth['modules_count'] ?? 0) ?></span>
                        </div>
                        <div class="admin-health-item">
                            <span>Actions</span>
                            <span class="badge bg-success"><?= number_format($systemHealth['actions_count'] ?? 0) ?></span>
                        </div>
                        <div class="admin-health-item">
                            <span>Relationships</span>
                            <span class="badge bg-info"><?= number_format($systemHealth['relationships_count'] ?? 0) ?></span>
                        </div>
                    </div>
                    <a href="<?= APP_URL ?>/status.php" class="btn btn-outline-info btn-icon align-self-start">
                        <i class="bi bi-gear"></i>Full Diagnostics
                    </a>
                </div>
            </article>

            <article class="admin-card">
                <header>
                    <h6 class="mb-0"><i class="bi bi-shield-exclamation me-2"></i>Security Activities</h6>
                </header>
                <div class="card-body">
                    <?php if (!empty($recentActivities)): ?>
                        <div class="admin-activity-list">
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="admin-activity-item">
                                    <div class="admin-activity-meta">
                                        <span class="fw-semibold small"><?= ucwords(str_replace('_', ' ', $activity['action_type'])) ?></span>
                                        <small class="text-muted"><?= htmlspecialchars($activity['user_name'] ?? 'System') ?><?php if (!empty($activity['module_name'])): ?> → <?= htmlspecialchars($activity['module_name']) ?><?php endif; ?></small>
                                        <small class="text-muted"><?= formatDate($activity['created_at'], 'M j, H:i') ?></small>
                                    </div>
                                    <?php
                                        $riskLevel = $activity['risk_level'] ?? 'normal';
                                        $riskClass = match ($riskLevel) {
                                            'critical' => 'danger',
                                            'high' => 'warning',
                                            'medium' => 'info',
                                            default => 'secondary',
                                        };
                                    ?>
                                    <span class="badge bg-<?= $riskClass ?>"><?= ucfirst($riskLevel) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-shield-check fs-1"></i>
                            <p class="mt-3 mb-0">No security escalations logged.</p>
                        </div>
                    <?php endif; ?>
                    <a href="<?= APP_URL ?>/permissions.php" class="btn btn-outline-warning btn-icon align-self-start">
                        <i class="bi bi-eye"></i>View Audit Log
                    </a>
                </div>
            </article>
        </div>
    </section>

    <section class="admin-grid">
        <article class="admin-card">
            <header>
                <h6 class="mb-0"><i class="bi bi-people me-2"></i>User Activity (Today)</h6>
            </header>
            <div class="card-body">
                <?php if (!empty($userActivity)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th class="text-center">Sales</th>
                                    <th class="text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userActivity as $user): ?>
                                    <tr>
                                        <td class="fw-semibold small"><?= htmlspecialchars($user['full_name']) ?></td>
                                        <td><span class="badge bg-secondary small"><?= ucfirst($user['role']) ?></span></td>
                                        <td class="text-center"><span class="badge bg-info"><?= (int)$user['sales_count'] ?></span></td>
                                        <td class="text-end"><small><?= formatMoney($user['sales_total'] ?? 0) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-person-x fs-1"></i>
                        <p class="mt-3 mb-0">No user activity captured yet today.</p>
                    </div>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/users.php" class="btn btn-outline-primary btn-icon align-self-start">
                    <i class="bi bi-people"></i>Manage Users
                </a>
            </div>
        </article>

        <article class="admin-card">
            <header>
                <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Inventory Alerts</h6>
            </header>
            <div class="card-body">
                <?php if (!empty($lowStock)): ?>
                    <div class="admin-inventory-grid">
                        <?php foreach ($lowStock as $item): ?>
                            <div class="alert alert-warning mb-0">
                                <div class="fw-semibold small text-truncate"><?= htmlspecialchars($item['name']) ?></div>
                                <small class="text-danger">Stock <?= (int)$item['stock_quantity'] ?> • Min <?= (int)$item['min_stock_level'] ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-check-circle fs-1 text-success"></i>
                        <p class="mt-3 mb-0">All tracked items are above minimum levels.</p>
                    </div>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/products.php" class="btn btn-outline-danger btn-icon align-self-start">
                    <i class="bi bi-box-seam"></i>Manage Inventory
                </a>
            </div>
        </article>
    </section>

    <section class="admin-card">
        <header>
            <h6 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Administrative Actions</h6>
        </header>
        <div class="card-body">
            <div class="admin-quick-actions">
                <a href="<?= APP_URL ?>/users.php" class="btn btn-outline-primary btn-icon">
                    <i class="bi bi-person-plus"></i>Add User
                </a>
                <a href="<?= APP_URL ?>/permissions.php" class="btn btn-outline-success btn-icon">
                    <i class="bi bi-shield-plus"></i>Permissions
                </a>
                <a href="<?= APP_URL ?>/products.php" class="btn btn-outline-info btn-icon">
                    <i class="bi bi-plus-square"></i>Add Product
                </a>
                <a href="<?= APP_URL ?>/locations.php" class="btn btn-outline-warning btn-icon">
                    <i class="bi bi-geo-alt"></i>Locations
                </a>
                <a href="<?= APP_URL ?>/settings.php" class="btn btn-outline-secondary btn-icon">
                    <i class="bi bi-gear"></i>Settings
                </a>
                <a href="<?= APP_URL ?>/status.php" class="btn btn-outline-danger btn-icon">
                    <i class="bi bi-cpu"></i>System
                </a>
            </div>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>
