<?php
require_once '../includes/bootstrap.php';
$auth->requireRole(['admin', 'manager']);

$db = Database::getInstance();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Helper: sum movements for a set of account types
function sumByAccountType(Database $db, array $types, string $startDate, string $endDate): float {
    $placeholders = implode(',', array_fill(0, count($types), '?'));
    $params = array_merge([$startDate, $endDate], $types);
    $row = $db->fetchOne(
        "SELECT COALESCE(SUM(jl.credit_amount - jl.debit_amount), 0) as total
         FROM journal_lines jl
         JOIN accounts a ON a.id = jl.account_id
         WHERE DATE(jl.created_at) BETWEEN ? AND ?
           AND a.type IN ($placeholders)",
        $params
    );
    return (float)($row['total'] ?? 0);
}

// Helper: sum specific account code(s)
function sumByAccountCodes(Database $db, array $codes, string $startDate, string $endDate): float {
    if (empty($codes)) return 0.0;
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $params = array_merge([$startDate, $endDate], $codes);
    $row = $db->fetchOne(
        "SELECT COALESCE(SUM(jl.debit_amount - jl.credit_amount), 0) as total
         FROM journal_lines jl
         JOIN accounts a ON a.id = jl.account_id
         WHERE DATE(jl.created_at) BETWEEN ? AND ?
           AND a.code IN ($placeholders)",
        $params
    );
    return (float)($row['total'] ?? 0);
}

// Revenue: credits - debits on REVENUE
$revenue = sumByAccountType($db, ['REVENUE'], $startDate, $endDate);
// Contra revenue acts as a reduction to revenue (debits - credits)
$contraRevenue = 0.0;
$crRow = $db->fetchOne(
    "SELECT COALESCE(SUM(jl.debit_amount - jl.credit_amount), 0) as total
     FROM journal_lines jl
     JOIN accounts a ON a.id = jl.account_id
     WHERE DATE(jl.created_at) BETWEEN ? AND ? AND a.type = 'CONTRA_REVENUE'",
    [$startDate, $endDate]
);
$contraRevenue = (float)($crRow['total'] ?? 0);

// COGS: debit - credit on account code 5000
$cogs = sumByAccountCodes($db, ['5000'], $startDate, $endDate);

// Operating Expenses: all EXPENSE excluding 5000
$operatingExpensesRow = $db->fetchOne(
    "SELECT COALESCE(SUM(jl.debit_amount - jl.credit_amount), 0) as total
     FROM journal_lines jl
     JOIN accounts a ON a.id = jl.account_id
     WHERE DATE(jl.created_at) BETWEEN ? AND ?
       AND a.type = 'EXPENSE' AND a.code <> '5000'",
    [$startDate, $endDate]
);
$operatingExpenses = (float)($operatingExpensesRow['total'] ?? 0);

$netRevenue = $revenue - $contraRevenue;
$grossProfit = $netRevenue - $cogs;
$netProfit = $grossProfit - $operatingExpenses;

$pageTitle = 'Profit & Loss Report';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-journal-text me-2"></i>Profit & Loss</h4>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-2"></i>Filter
                </button>
                <a href="profit-and-loss.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted mb-1 small">Revenue</p>
                <h3 class="mb-0 fw-bold text-success"><?= formatMoney($revenue) ?></h3>
                <div class="small text-muted">Less: Contra Revenue <?= formatMoney($contraRevenue) ?></div>
                <hr>
                <p class="mb-1">Net Revenue</p>
                <h4 class="fw-bold"><?= formatMoney($netRevenue) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted mb-1 small">Cost of Goods Sold</p>
                <h3 class="mb-0 fw-bold text-danger"><?= formatMoney($cogs) ?></h3>
                <hr>
                <p class="mb-1">Gross Profit</p>
                <h4 class="fw-bold text-primary"><?= formatMoney($grossProfit) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted mb-1 small">Operating Expenses</p>
                <h3 class="mb-0 fw-bold text-warning"><?= formatMoney($operatingExpenses) ?></h3>
                <hr>
                <p class="mb-1">Net Profit</p>
                <h4 class="fw-bold text-info"><?= formatMoney($netProfit) ?></h4>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
