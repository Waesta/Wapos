<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

// Check permission
$user = $auth->getUser();
if (!$user || !in_array($user['role'], ['admin', 'manager', 'accountant'])) {
    $_SESSION['error_message'] = 'You do not have permission to view void reports.';
    redirect('index.php');
}

$db = Database::getInstance();

// Get date range from request or default to last 30 days
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Get void statistics
$voidStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_voids,
        SUM(original_total) as total_amount,
        COUNT(DISTINCT voided_by_user_id) as unique_users,
        COUNT(CASE WHEN manager_user_id IS NOT NULL THEN 1 END) as manager_approved
    FROM void_transactions
    WHERE DATE(void_timestamp) BETWEEN ? AND ?
", [$startDate, $endDate]) ?: ['total_voids' => 0, 'total_amount' => 0, 'unique_users' => 0, 'manager_approved' => 0];

// Get voids by reason
$voidsByReason = $db->fetchAll("
    SELECT 
        vt.void_reason_code,
        vrc.display_name,
        COUNT(*) as count,
        SUM(vt.original_total) as total_amount
    FROM void_transactions vt
    LEFT JOIN void_reason_codes vrc ON vt.void_reason_code = vrc.code
    WHERE DATE(vt.void_timestamp) BETWEEN ? AND ?
    GROUP BY vt.void_reason_code, vrc.display_name
    ORDER BY count DESC
", [$startDate, $endDate]);

// Get voids by user
$voidsByUser = $db->fetchAll("
    SELECT 
        u.username,
        u.role,
        COUNT(*) as count,
        SUM(vt.original_total) as total_amount
    FROM void_transactions vt
    LEFT JOIN users u ON vt.voided_by_user_id = u.id
    WHERE DATE(vt.void_timestamp) BETWEEN ? AND ?
    GROUP BY u.id, u.username, u.role
    ORDER BY count DESC
    LIMIT 10
", [$startDate, $endDate]);

// Get recent void transactions
$recentVoids = $db->fetchAll("
    SELECT 
        vt.*,
        vrc.display_name as reason_name,
        u.username as voided_by,
        m.username as manager_name
    FROM void_transactions vt
    LEFT JOIN void_reason_codes vrc ON vt.void_reason_code = vrc.code
    LEFT JOIN users u ON vt.voided_by_user_id = u.id
    LEFT JOIN users m ON vt.manager_user_id = m.id
    WHERE DATE(vt.void_timestamp) BETWEEN ? AND ?
    ORDER BY vt.void_timestamp DESC
    LIMIT 50
", [$startDate, $endDate]);

// Get daily void trend
$dailyTrend = $db->fetchAll("
    SELECT 
        DATE(void_timestamp) as date,
        COUNT(*) as count,
        SUM(original_total) as total_amount
    FROM void_transactions
    WHERE DATE(void_timestamp) BETWEEN ? AND ?
    GROUP BY DATE(void_timestamp)
    ORDER BY date ASC
", [$startDate, $endDate]);

$pageTitle = 'Void Reports';
include 'includes/header.php';
?>

<style>
.stat-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #2c3e50;
}
.stat-label {
    color: #7f8c8d;
    font-size: 0.9rem;
}
.chart-container {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-danger"><?= number_format($voidStats['total_voids']) ?></div>
            <div class="stat-label">Total Voids</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-warning"><?= number_format($voidStats['total_amount'] ?? 0, 2) ?></div>
            <div class="stat-label">Total Amount Voided</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-info"><?= number_format($voidStats['unique_users']) ?></div>
            <div class="stat-label">Users Who Voided</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-success"><?= number_format($voidStats['manager_approved']) ?></div>
            <div class="stat-label">Manager Approved</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Voids by Reason -->
    <div class="col-md-6">
        <div class="chart-container">
            <h5 class="mb-3">Voids by Reason</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Reason</th>
                            <th class="text-end">Count</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($voidsByReason as $reason): ?>
                        <tr>
                            <td><?= htmlspecialchars($reason['display_name'] ?? $reason['void_reason_code']) ?></td>
                            <td class="text-end"><?= number_format($reason['count']) ?></td>
                            <td class="text-end"><?= number_format($reason['total_amount'] ?? 0, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Voids by User -->
    <div class="col-md-6">
        <div class="chart-container">
            <h5 class="mb-3">Top Users (Voids)</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th class="text-end">Count</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($voidsByUser as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($user['role']) ?></span></td>
                            <td class="text-end"><?= number_format($user['count']) ?></td>
                            <td class="text-end"><?= number_format($user['total_amount'] ?? 0, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Daily Trend -->
<div class="row mb-4">
    <div class="col-12">
        <div class="chart-container">
            <h5 class="mb-3">Daily Void Trend</h5>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Voids</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dailyTrend as $day): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($day['date'])) ?></td>
                            <td class="text-end"><?= number_format($day['count']) ?></td>
                            <td class="text-end"><?= number_format($day['total_amount'] ?? 0, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Recent Void Transactions -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Void Transactions</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Order ID</th>
                                <th>Type</th>
                                <th>Reason</th>
                                <th>Amount</th>
                                <th>Voided By</th>
                                <th>Manager</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentVoids as $void): ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($void['void_timestamp'])) ?></td>
                                <td>#<?= $void['order_id'] ?></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($void['order_type']) ?></span></td>
                                <td><?= htmlspecialchars($void['reason_name'] ?? $void['void_reason_code']) ?></td>
                                <td><?= number_format($void['original_total'] ?? 0, 2) ?></td>
                                <td><?= htmlspecialchars($void['voided_by']) ?></td>
                                <td><?= $void['manager_name'] ? htmlspecialchars($void['manager_name']) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
