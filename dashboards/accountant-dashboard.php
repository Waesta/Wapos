<?php
/**
 * Accountant Dashboard
 * Financial overview and accounting access
 */

require_once '../includes/bootstrap.php';
$auth->requireRole('accountant');

$db = Database::getInstance();

$pageTitle = 'Accountant Dashboard';
include '../includes/header.php';

// Get financial summary
$today = date('Y-m-d');
$thisMonth = date('Y-m-01');

// Today's sales
$todaySales = $db->fetchOne("
    SELECT 
        COUNT(*) as count,
        COALESCE(SUM(total_amount), 0) as total
    FROM sales 
    WHERE DATE(created_at) = ?
", [$today]) ?: ['count' => 0, 'total' => 0];

// This month's sales
$monthSales = $db->fetchOne("
    SELECT 
        COUNT(*) as count,
        COALESCE(SUM(total_amount), 0) as total
    FROM sales 
    WHERE DATE(created_at) >= ?
", [$thisMonth]) ?: ['count' => 0, 'total' => 0];

// Pending payments (where amount_paid is less than total_amount)
$pendingPayments = $db->fetchOne("
    SELECT 
        COUNT(*) as count,
        COALESCE(SUM(total_amount - amount_paid), 0) as total
    FROM sales 
    WHERE amount_paid < total_amount
") ?: ['count' => 0, 'total' => 0];

// Recent transactions
$recentTransactions = $db->fetchAll("
    SELECT 
        s.*,
        u.full_name as cashier_name
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    ORDER BY s.created_at DESC
    LIMIT 10
") ?: [];

// Get expense summary (if accounting tables exist)
$expenses = ['count' => 0, 'total' => 0];
try {
    $expenses = $db->fetchOne("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(amount), 0) as total
        FROM expenses 
        WHERE DATE(expense_date) >= ?
    ", [$thisMonth]);
} catch (Exception $e) {
    // Expenses table doesn't exist yet
}
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-calculator me-2"></i>Accountant Dashboard</h2>
            <p class="text-muted mb-0">Financial overview and accounting management</p>
        </div>
        <div>
            <span class="badge bg-primary fs-6">
                <i class="bi bi-calendar3 me-1"></i><?= date('F d, Y') ?>
            </span>
        </div>
    </div>

    <!-- Financial Summary Cards -->
    <div class="row g-3 mb-4">
        <!-- Today's Revenue -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted mb-1 small">Today's Revenue</p>
                            <h3 class="mb-0"><?= formatMoney($todaySales['total']) ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="bi bi-cash-coin text-success fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-receipt me-1"></i><?= $todaySales['count'] ?> transactions
                    </small>
                </div>
            </div>
        </div>

        <!-- Monthly Revenue -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted mb-1 small">Monthly Revenue</p>
                            <h3 class="mb-0"><?= formatMoney($monthSales['total']) ?></h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="bi bi-graph-up text-primary fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-receipt me-1"></i><?= $monthSales['count'] ?> transactions
                    </small>
                </div>
            </div>
        </div>

        <!-- Pending Payments -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted mb-1 small">Pending Payments</p>
                            <h3 class="mb-0"><?= formatMoney($pendingPayments['total']) ?></h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="bi bi-clock-history text-warning fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-exclamation-circle me-1"></i><?= $pendingPayments['count'] ?> pending
                    </small>
                </div>
            </div>
        </div>

        <!-- Monthly Expenses -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted mb-1 small">Monthly Expenses</p>
                            <h3 class="mb-0"><?= formatMoney($expenses['total']) ?></h3>
                        </div>
                        <div class="bg-danger bg-opacity-10 p-3 rounded">
                            <i class="bi bi-arrow-down-circle text-danger fs-4"></i>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-receipt me-1"></i><?= $expenses['count'] ?> expenses
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h5>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <a href="../accounting.php" class="btn btn-outline-primary w-100">
                                <i class="bi bi-journal-text me-2"></i>Accounting
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="../reports.php?type=financial" class="btn btn-outline-success w-100">
                                <i class="bi bi-file-earmark-bar-graph me-2"></i>Financial Reports
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="../reports.php?type=sales" class="btn btn-outline-info w-100">
                                <i class="bi bi-graph-up me-2"></i>Sales Reports
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="../sales.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-receipt me-2"></i>View All Sales
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Transactions</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Receipt #</th>
                                    <th>Date & Time</th>
                                    <th>Cashier</th>
                                    <th>Amount</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentTransactions)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                            No transactions found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentTransactions as $sale): ?>
                                        <tr>
                                            <td><strong>#<?= str_pad($sale['id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                                            <td><?= date('M d, Y h:i A', strtotime($sale['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($sale['cashier_name'] ?? 'N/A') ?></td>
                                            <td><strong><?= formatMoney($sale['total_amount']) ?></strong></td>
                                            <td><?= ucfirst($sale['payment_method'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php
                                                // Calculate payment status based on amount_paid
                                                if ($sale['amount_paid'] >= $sale['total_amount']) {
                                                    $paymentStatus = 'completed';
                                                    $class = 'success';
                                                } elseif ($sale['amount_paid'] > 0) {
                                                    $paymentStatus = 'partial';
                                                    $class = 'info';
                                                } else {
                                                    $paymentStatus = 'pending';
                                                    $class = 'warning';
                                                }
                                                ?>
                                                <span class="badge bg-<?= $class ?>">
                                                    <?= ucfirst($paymentStatus) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="../print-receipt.php?id=<?= $sale['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   target="_blank">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <a href="../sales.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-arrow-right me-1"></i>View All Transactions
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
