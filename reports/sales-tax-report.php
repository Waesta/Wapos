<?php
require_once '../includes/bootstrap.php';
$auth->requireRole(['admin', 'manager', 'accountant']);

$db = Database::getInstance();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Output tax: credit postings to Sales Tax Payable (2100)
$row = $db->fetchOne(
    "SELECT COALESCE(SUM(jl.credit_amount - jl.debit_amount), 0) AS output_tax
     FROM journal_lines jl
     JOIN accounts a ON a.id = jl.account_id
     WHERE DATE(jl.created_at) BETWEEN ? AND ? AND a.code = '2100'",
    [$startDate, $endDate]
);
$outputTax = (float)($row['output_tax'] ?? 0);

$pageTitle = 'Sales Tax (VAT) Report';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-file-spreadsheet me-2"></i>Sales Tax (VAT)</h4>
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
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-2"></i>Filter</button>
                <a href="sales-tax-report.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between">
            <div><strong>Output Tax (Sales Tax Payable)</strong></div>
            <div><?= formatMoney($outputTax) ?></div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
