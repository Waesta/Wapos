<?php
require_once '../includes/bootstrap.php';
$auth->requireRole(['admin', 'manager']);

$db = Database::getInstance();

$asOf = $_GET['as_of'] ?? date('Y-m-d');

function sumAccountTypeAsOf(Database $db, string $type, string $asOf): float {
    $row = $db->fetchOne(
        "SELECT COALESCE(SUM(jl.debit_amount - jl.credit_amount), 0) AS total
         FROM journal_lines jl
         JOIN accounts a ON a.id = jl.account_id
         WHERE DATE(jl.created_at) <= ? AND a.type = ?",
        [$asOf, $type]
    );
    return (float)($row['total'] ?? 0);
}

function sumAccountCodesAsOf(Database $db, array $codes, string $asOf): float {
    if (empty($codes)) return 0.0;
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $params = array_merge([$asOf], $codes);
    $row = $db->fetchOne(
        "SELECT COALESCE(SUM(jl.debit_amount - jl.credit_amount), 0) AS total
         FROM journal_lines jl
         JOIN accounts a ON a.id = jl.account_id
         WHERE DATE(jl.created_at) <= ? AND a.code IN ($placeholders)",
        $params
    );
    return (float)($row['total'] ?? 0);
}

$assets = sumAccountTypeAsOf($db, 'ASSET', $asOf);
$liabilities = sumAccountTypeAsOf($db, 'LIABILITY', $asOf);
$equity = sumAccountTypeAsOf($db, 'EQUITY', $asOf);

// Retained earnings approximation = cumulative REVENUE - EXPENSE + CONTRA_REVENUE adjustments
$revenueToDate = $db->fetchOne(
    "SELECT COALESCE(SUM(jl.credit_amount - jl.debit_amount), 0) AS total
     FROM journal_lines jl JOIN accounts a ON a.id = jl.account_id
     WHERE DATE(jl.created_at) <= ? AND a.type = 'REVENUE'",
    [$asOf]
)['total'] ?? 0;
$contraToDate = $db->fetchOne(
    "SELECT COALESCE(SUM(jl.debit_amount - jl.credit_amount), 0) AS total
     FROM journal_lines jl JOIN accounts a ON a.id = jl.account_id
     WHERE DATE(jl.created_at) <= ? AND a.type = 'CONTRA_REVENUE'",
    [$asOf]
)['total'] ?? 0;
$expenseToDate = $db->fetchOne(
    "SELECT COALESCE(SUM(jl.debit_amount - jl.credit_amount), 0) AS total
     FROM journal_lines jl JOIN accounts a ON a.id = jl.account_id
     WHERE DATE(jl.created_at) <= ? AND a.type = 'EXPENSE'",
    [$asOf]
)['total'] ?? 0;
$retained = (float)$revenueToDate - (float)$contraToDate - (float)$expenseToDate;

$totalLiabEquity = $liabilities + $equity + $retained;

$pageTitle = 'Balance Sheet';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Balance Sheet</h4>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">As of</label>
                <input type="date" class="form-control" name="as_of" value="<?= htmlspecialchars($asOf) ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-2"></i>Apply</button>
                <a href="balance-sheet.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted mb-1 small">Assets</p>
                <h3 class="mb-0 fw-bold text-success"><?= formatMoney($assets) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted mb-1 small">Liabilities</p>
                <h3 class="mb-0 fw-bold text-danger"><?= formatMoney($liabilities) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted mb-1 small">Equity + Retained</p>
                <h3 class="mb-0 fw-bold text-info"><?= formatMoney($equity + $retained) ?></h3>
                <div class="small text-muted">Retained Earnings: <?= formatMoney($retained) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
        <div class="d-flex justify-content-between">
            <div><strong>Total Assets</strong></div>
            <div><?= formatMoney($assets) ?></div>
        </div>
        <div class="d-flex justify-content-between">
            <div><strong>Total Liabilities + Equity</strong></div>
            <div><?= formatMoney($totalLiabEquity) ?></div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
