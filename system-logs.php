<?php
/**
 * WAPOS - System Logs
 * Comprehensive logging and monitoring for Super Admin
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

require_once 'includes/bootstrap.php';

// Require super_admin or developer role
$auth->requireLogin();
$userRole = $auth->getRole();
if (!in_array($userRole, ['super_admin', 'admin', 'developer'], true)) {
    $_SESSION['error_message'] = 'Access denied. Super Admin privileges required.';
    redirect(APP_URL . '/index.php');
}

$db = Database::getInstance();
$pdo = $db->getConnection();

// Ensure audit_log table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED,
        action VARCHAR(100) NOT NULL,
        table_name VARCHAR(100),
        record_id INT UNSIGNED,
        old_values JSON,
        new_values JSON,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_table_record (table_name, record_id),
        INDEX idx_action (action),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB");
} catch (Exception $e) {
    // Table might already exist
}

// Ensure system_logs table exists for general logging
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        log_level ENUM('debug', 'info', 'warning', 'error', 'critical') DEFAULT 'info',
        category VARCHAR(50) NOT NULL DEFAULT 'general',
        message TEXT NOT NULL,
        context JSON,
        user_id INT UNSIGNED,
        ip_address VARCHAR(45),
        request_uri VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_level (log_level),
        INDEX idx_category (category),
        INDEX idx_created (created_at),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB");
} catch (Exception $e) {
    // Table might already exist
}

// Handle actions
$action = $_GET['action'] ?? '';
$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request token.';
        $messageType = 'danger';
    } else {
        $postAction = $_POST['action'] ?? '';
        
        if ($postAction === 'clear_old_logs') {
            $days = (int)($_POST['days'] ?? 30);
            try {
                $stmt = $pdo->prepare("DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->execute([$days]);
                $auditDeleted = $stmt->rowCount();
                
                $stmt = $pdo->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->execute([$days]);
                $systemDeleted = $stmt->rowCount();
                
                $message = "Cleared {$auditDeleted} audit logs and {$systemDeleted} system logs older than {$days} days.";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error clearing logs: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
        
        if ($postAction === 'export_logs') {
            $logType = $_POST['log_type'] ?? 'audit';
            $startDate = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $_POST['end_date'] ?? date('Y-m-d');
            
            try {
                if ($logType === 'audit') {
                    $stmt = $pdo->prepare("
                        SELECT al.*, u.username, u.full_name 
                        FROM audit_log al
                        LEFT JOIN users u ON al.user_id = u.id
                        WHERE DATE(al.created_at) BETWEEN ? AND ?
                        ORDER BY al.created_at DESC
                    ");
                } else {
                    $stmt = $pdo->prepare("
                        SELECT sl.*, u.username, u.full_name 
                        FROM system_logs sl
                        LEFT JOIN users u ON sl.user_id = u.id
                        WHERE DATE(sl.created_at) BETWEEN ? AND ?
                        ORDER BY sl.created_at DESC
                    ");
                }
                $stmt->execute([$startDate, $endDate]);
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Export as CSV
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $logType . '_logs_' . date('Y-m-d_His') . '.csv"');
                
                $output = fopen('php://output', 'w');
                if (!empty($logs)) {
                    fputcsv($output, array_keys($logs[0]));
                    foreach ($logs as $row) {
                        fputcsv($output, $row);
                    }
                }
                fclose($output);
                exit;
            } catch (Exception $e) {
                $message = 'Error exporting logs: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filters
$logType = $_GET['log_type'] ?? 'audit';
$filterAction = $_GET['filter_action'] ?? '';
$filterUser = $_GET['filter_user'] ?? '';
$filterLevel = $_GET['filter_level'] ?? '';
$filterDate = $_GET['filter_date'] ?? '';

// Get logs based on type
$logs = [];
$totalLogs = 0;

try {
    if ($logType === 'audit') {
        // Build query
        $where = [];
        $params = [];
        
        if ($filterAction) {
            $where[] = "al.action LIKE ?";
            $params[] = "%{$filterAction}%";
        }
        if ($filterUser) {
            $where[] = "(u.username LIKE ? OR u.full_name LIKE ?)";
            $params[] = "%{$filterUser}%";
            $params[] = "%{$filterUser}%";
        }
        if ($filterDate) {
            $where[] = "DATE(al.created_at) = ?";
            $params[] = $filterDate;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Count total
        $countSql = "SELECT COUNT(*) FROM audit_log al LEFT JOIN users u ON al.user_id = u.id {$whereClause}";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $totalLogs = (int)$stmt->fetchColumn();
        
        // Get logs
        $sql = "SELECT al.*, u.username, u.full_name 
                FROM audit_log al
                LEFT JOIN users u ON al.user_id = u.id
                {$whereClause}
                ORDER BY al.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // System logs
        $where = [];
        $params = [];
        
        if ($filterLevel) {
            $where[] = "sl.log_level = ?";
            $params[] = $filterLevel;
        }
        if ($filterUser) {
            $where[] = "(u.username LIKE ? OR u.full_name LIKE ?)";
            $params[] = "%{$filterUser}%";
            $params[] = "%{$filterUser}%";
        }
        if ($filterDate) {
            $where[] = "DATE(sl.created_at) = ?";
            $params[] = $filterDate;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Count total
        $countSql = "SELECT COUNT(*) FROM system_logs sl LEFT JOIN users u ON sl.user_id = u.id {$whereClause}";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $totalLogs = (int)$stmt->fetchColumn();
        
        // Get logs
        $sql = "SELECT sl.*, u.username, u.full_name 
                FROM system_logs sl
                LEFT JOIN users u ON sl.user_id = u.id
                {$whereClause}
                ORDER BY sl.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $message = 'Error loading logs: ' . $e->getMessage();
    $messageType = 'danger';
}

$totalPages = ceil($totalLogs / $perPage);

// Get statistics
$stats = [
    'audit_total' => 0,
    'audit_today' => 0,
    'system_total' => 0,
    'system_errors' => 0,
    'active_users_today' => 0,
    'login_attempts_today' => 0
];

try {
    $stats['audit_total'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
    $stats['audit_today'] = (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $stats['system_total'] = (int)$pdo->query("SELECT COUNT(*) FROM system_logs")->fetchColumn();
    $stats['system_errors'] = (int)$pdo->query("SELECT COUNT(*) FROM system_logs WHERE log_level IN ('error', 'critical') AND DATE(created_at) = CURDATE()")->fetchColumn();
    $stats['active_users_today'] = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM audit_log WHERE DATE(created_at) = CURDATE()")->fetchColumn();
} catch (Exception $e) {
    // Stats tables might not exist yet
}

// Get unique actions for filter dropdown
$uniqueActions = [];
try {
    $uniqueActions = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Get PHP error log content
$phpErrorLog = '';
$phpLogPath = LOG_PATH . '/php_errors.log';
if (file_exists($phpLogPath)) {
    $phpErrorLog = file_get_contents($phpLogPath);
    // Get last 100 lines
    $lines = explode("\n", $phpErrorLog);
    $phpErrorLog = implode("\n", array_slice($lines, -100));
}

// Get complete-sale log
$completeSaleLog = '';
$completeSaleLogPath = ROOT_PATH . '/storage/logs/complete-sale.log';
if (file_exists($completeSaleLogPath)) {
    $completeSaleLog = file_get_contents($completeSaleLogPath);
    $lines = explode("\n", $completeSaleLog);
    $completeSaleLog = implode("\n", array_slice($lines, -50));
}

$pageTitle = 'System Logs';
include 'includes/header.php';
?>

<style>
    .log-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .stat-card {
        background: var(--surface-card);
        border-radius: 12px;
        padding: 20px;
        border: 1px solid var(--border-color);
    }
    .stat-card h4 {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        margin: 0 0 8px;
    }
    .stat-card .value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-primary);
    }
    .stat-card.warning .value { color: #f59e0b; }
    .stat-card.danger .value { color: #ef4444; }
    .stat-card.success .value { color: #10b981; }
    
    .log-filters {
        background: var(--surface-card);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
        border: 1px solid var(--border-color);
    }
    
    .log-table {
        width: 100%;
        border-collapse: collapse;
    }
    .log-table th,
    .log-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }
    .log-table th {
        background: var(--surface-muted);
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .log-table tr:hover {
        background: var(--surface-muted);
    }
    
    .log-level {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .log-level.debug { background: #e0e7ff; color: #4338ca; }
    .log-level.info { background: #dbeafe; color: #1d4ed8; }
    .log-level.warning { background: #fef3c7; color: #d97706; }
    .log-level.error { background: #fee2e2; color: #dc2626; }
    .log-level.critical { background: #fecaca; color: #991b1b; }
    
    .action-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        background: var(--surface-muted);
    }
    .action-badge.void_sale { background: #fee2e2; color: #dc2626; }
    .action-badge.price_change { background: #fef3c7; color: #d97706; }
    .action-badge.role_change { background: #e0e7ff; color: #4338ca; }
    .action-badge.permission_change { background: #dbeafe; color: #1d4ed8; }
    .action-badge.login { background: #d1fae5; color: #059669; }
    .action-badge.logout { background: #f3f4f6; color: #6b7280; }
    
    .json-preview {
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-family: monospace;
        font-size: 0.8rem;
        color: var(--text-muted);
    }
    
    .log-file-viewer {
        background: #1e293b;
        color: #e2e8f0;
        border-radius: 8px;
        padding: 16px;
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 0.8rem;
        max-height: 400px;
        overflow: auto;
        white-space: pre-wrap;
        word-break: break-all;
    }
    
    .pagination {
        display: flex;
        gap: 4px;
        justify-content: center;
        margin-top: 24px;
    }
    .pagination a,
    .pagination span {
        padding: 8px 12px;
        border-radius: 6px;
        text-decoration: none;
        border: 1px solid var(--border-color);
    }
    .pagination a:hover {
        background: var(--surface-muted);
    }
    .pagination .active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }
    
    .tab-nav {
        display: flex;
        gap: 4px;
        margin-bottom: 24px;
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 0;
    }
    .tab-nav a {
        padding: 12px 20px;
        text-decoration: none;
        color: var(--text-muted);
        font-weight: 500;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        transition: all 0.2s;
    }
    .tab-nav a:hover {
        color: var(--text-primary);
    }
    .tab-nav a.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-journal-text me-2"></i>System Logs</h1>
            <p class="text-muted mb-0">Monitor system activity, audit trails, and error logs</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#exportModal">
                <i class="bi bi-download me-1"></i> Export
            </button>
            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
                <i class="bi bi-trash me-1"></i> Clear Old Logs
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="log-stats">
        <div class="stat-card">
            <h4>Audit Logs Total</h4>
            <div class="value"><?= number_format($stats['audit_total']) ?></div>
        </div>
        <div class="stat-card success">
            <h4>Today's Activity</h4>
            <div class="value"><?= number_format($stats['audit_today']) ?></div>
        </div>
        <div class="stat-card">
            <h4>System Logs</h4>
            <div class="value"><?= number_format($stats['system_total']) ?></div>
        </div>
        <div class="stat-card <?= $stats['system_errors'] > 0 ? 'danger' : '' ?>">
            <h4>Errors Today</h4>
            <div class="value"><?= number_format($stats['system_errors']) ?></div>
        </div>
        <div class="stat-card">
            <h4>Active Users Today</h4>
            <div class="value"><?= number_format($stats['active_users_today']) ?></div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-nav">
        <a href="?log_type=audit" class="<?= $logType === 'audit' ? 'active' : '' ?>">
            <i class="bi bi-shield-check me-1"></i> Audit Log
        </a>
        <a href="?log_type=system" class="<?= $logType === 'system' ? 'active' : '' ?>">
            <i class="bi bi-gear me-1"></i> System Log
        </a>
        <a href="?log_type=php_errors" class="<?= $logType === 'php_errors' ? 'active' : '' ?>">
            <i class="bi bi-bug me-1"></i> PHP Errors
        </a>
        <a href="?log_type=sales_log" class="<?= $logType === 'sales_log' ? 'active' : '' ?>">
            <i class="bi bi-cart me-1"></i> Sales Log
        </a>
    </div>

    <?php if ($logType === 'php_errors'): ?>
        <!-- PHP Error Log Viewer -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">PHP Error Log</h5>
                <small class="text-muted"><?= htmlspecialchars($phpLogPath) ?></small>
            </div>
            <div class="card-body p-0">
                <?php if ($phpErrorLog): ?>
                    <div class="log-file-viewer"><?= htmlspecialchars($phpErrorLog) ?></div>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-check-circle fs-1 text-success"></i>
                        <p class="mt-2 mb-0">No PHP errors logged.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($logType === 'sales_log'): ?>
        <!-- Sales Log Viewer -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Complete Sale Log</h5>
                <small class="text-muted"><?= htmlspecialchars($completeSaleLogPath) ?></small>
            </div>
            <div class="card-body p-0">
                <?php if ($completeSaleLog): ?>
                    <div class="log-file-viewer"><?= htmlspecialchars($completeSaleLog) ?></div>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-check-circle fs-1 text-success"></i>
                        <p class="mt-2 mb-0">No sale errors logged.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Filters -->
        <div class="log-filters">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="log_type" value="<?= htmlspecialchars($logType) ?>">
                
                <?php if ($logType === 'audit'): ?>
                    <div class="col-md-3">
                        <label class="form-label">Action</label>
                        <select name="filter_action" class="form-select">
                            <option value="">All Actions</option>
                            <?php foreach ($uniqueActions as $act): ?>
                                <option value="<?= htmlspecialchars($act) ?>" <?= $filterAction === $act ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $act))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="col-md-3">
                        <label class="form-label">Level</label>
                        <select name="filter_level" class="form-select">
                            <option value="">All Levels</option>
                            <option value="debug" <?= $filterLevel === 'debug' ? 'selected' : '' ?>>Debug</option>
                            <option value="info" <?= $filterLevel === 'info' ? 'selected' : '' ?>>Info</option>
                            <option value="warning" <?= $filterLevel === 'warning' ? 'selected' : '' ?>>Warning</option>
                            <option value="error" <?= $filterLevel === 'error' ? 'selected' : '' ?>>Error</option>
                            <option value="critical" <?= $filterLevel === 'critical' ? 'selected' : '' ?>>Critical</option>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="col-md-3">
                    <label class="form-label">User</label>
                    <input type="text" name="filter_user" class="form-control" placeholder="Username or name" value="<?= htmlspecialchars($filterUser) ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="filter_date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>
                    <a href="?log_type=<?= htmlspecialchars($logType) ?>" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <?php if ($logType === 'audit'): ?>
                                    <th>Action</th>
                                    <th>Table</th>
                                    <th>Record ID</th>
                                <?php else: ?>
                                    <th>Level</th>
                                    <th>Category</th>
                                    <th>Message</th>
                                <?php endif; ?>
                                <th>User</th>
                                <th>IP Address</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No logs found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <small><?= date('M j, Y', strtotime($log['created_at'])) ?></small><br>
                                            <small class="text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
                                        </td>
                                        <?php if ($logType === 'audit'): ?>
                                            <td>
                                                <span class="action-badge <?= htmlspecialchars($log['action']) ?>">
                                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $log['action']))) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($log['table_name'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($log['record_id'] ?? '-') ?></td>
                                        <?php else: ?>
                                            <td>
                                                <span class="log-level <?= htmlspecialchars($log['log_level']) ?>">
                                                    <?= htmlspecialchars($log['log_level']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($log['category'] ?? '-') ?></td>
                                            <td class="json-preview"><?= htmlspecialchars($log['message'] ?? '-') ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <?php if ($log['username']): ?>
                                                <strong><?= htmlspecialchars($log['username']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($log['full_name'] ?? '') ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">System</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?= htmlspecialchars($log['ip_address'] ?? '-') ?></code></td>
                                        <td>
                                            <?php if ($logType === 'audit' && ($log['old_values'] || $log['new_values'])): ?>
                                                <button class="btn btn-sm btn-outline-secondary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#detailModal"
                                                        data-old="<?= htmlspecialchars($log['old_values'] ?? '{}') ?>"
                                                        data-new="<?= htmlspecialchars($log['new_values'] ?? '{}') ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            <?php elseif ($logType === 'system' && $log['context']): ?>
                                                <button class="btn btn-sm btn-outline-secondary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#contextModal"
                                                        data-context="<?= htmlspecialchars($log['context']) ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?log_type=<?= htmlspecialchars($logType) ?>&page=<?= $page - 1 ?>&filter_action=<?= urlencode($filterAction) ?>&filter_user=<?= urlencode($filterUser) ?>&filter_date=<?= urlencode($filterDate) ?>&filter_level=<?= urlencode($filterLevel) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                if ($startPage > 1): ?>
                    <a href="?log_type=<?= htmlspecialchars($logType) ?>&page=1&filter_action=<?= urlencode($filterAction) ?>&filter_user=<?= urlencode($filterUser) ?>&filter_date=<?= urlencode($filterDate) ?>&filter_level=<?= urlencode($filterLevel) ?>">1</a>
                    <?php if ($startPage > 2): ?><span>...</span><?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?log_type=<?= htmlspecialchars($logType) ?>&page=<?= $i ?>&filter_action=<?= urlencode($filterAction) ?>&filter_user=<?= urlencode($filterUser) ?>&filter_date=<?= urlencode($filterDate) ?>&filter_level=<?= urlencode($filterLevel) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?><span>...</span><?php endif; ?>
                    <a href="?log_type=<?= htmlspecialchars($logType) ?>&page=<?= $totalPages ?>&filter_action=<?= urlencode($filterAction) ?>&filter_user=<?= urlencode($filterUser) ?>&filter_date=<?= urlencode($filterDate) ?>&filter_level=<?= urlencode($filterLevel) ?>"><?= $totalPages ?></a>
                <?php endif; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?log_type=<?= htmlspecialchars($logType) ?>&page=<?= $page + 1 ?>&filter_action=<?= urlencode($filterAction) ?>&filter_user=<?= urlencode($filterUser) ?>&filter_date=<?= urlencode($filterDate) ?>&filter_level=<?= urlencode($filterLevel) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="export_logs">
                <div class="modal-header">
                    <h5 class="modal-title">Export Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Log Type</label>
                        <select name="log_type" class="form-select">
                            <option value="audit">Audit Log</option>
                            <option value="system">System Log</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-download me-1"></i> Export CSV
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Clear Logs Modal -->
<div class="modal fade" id="clearLogsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="clear_old_logs">
                <div class="modal-header">
                    <h5 class="modal-title">Clear Old Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This action cannot be undone. Old logs will be permanently deleted.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Delete logs older than</label>
                        <select name="days" class="form-select">
                            <option value="7">7 days</option>
                            <option value="14">14 days</option>
                            <option value="30" selected>30 days</option>
                            <option value="60">60 days</option>
                            <option value="90">90 days</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Clear Logs
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Old Values</h6>
                        <pre id="oldValues" class="bg-light p-3 rounded" style="max-height: 300px; overflow: auto;"></pre>
                    </div>
                    <div class="col-md-6">
                        <h6>New Values</h6>
                        <pre id="newValues" class="bg-light p-3 rounded" style="max-height: 300px; overflow: auto;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Context Modal -->
<div class="modal fade" id="contextModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log Context</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="contextData" class="bg-light p-3 rounded" style="max-height: 400px; overflow: auto;"></pre>
            </div>
        </div>
    </div>
</div>

<script>
// Detail modal handler
document.getElementById('detailModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const oldValues = button.getAttribute('data-old');
    const newValues = button.getAttribute('data-new');
    
    try {
        document.getElementById('oldValues').textContent = JSON.stringify(JSON.parse(oldValues || '{}'), null, 2);
    } catch (e) {
        document.getElementById('oldValues').textContent = oldValues || 'N/A';
    }
    
    try {
        document.getElementById('newValues').textContent = JSON.stringify(JSON.parse(newValues || '{}'), null, 2);
    } catch (e) {
        document.getElementById('newValues').textContent = newValues || 'N/A';
    }
});

// Context modal handler
document.getElementById('contextModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const context = button.getAttribute('data-context');
    
    try {
        document.getElementById('contextData').textContent = JSON.stringify(JSON.parse(context || '{}'), null, 2);
    } catch (e) {
        document.getElementById('contextData').textContent = context || 'N/A';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
