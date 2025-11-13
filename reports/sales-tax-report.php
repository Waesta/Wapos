<?php
require_once '../includes/bootstrap.php';

use App\Services\AccountingService;
use App\Services\LedgerDataService;

$auth->requireRole(['admin', 'manager', 'accountant']);

$db = Database::getInstance();
$pdo = $db->getConnection();
$accountingService = new AccountingService($pdo);
$ledgerDataService = new LedgerDataService($pdo, $accountingService);

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$vatSummary = $ledgerDataService->getVatSummary($startDate, $endDate);

$outputTax = $vatSummary['output_tax'] ?? 0;
$inputTax = $vatSummary['input_tax'] ?? 0;
$netTax = $vatSummary['net_tax'] ?? 0;
$netClass = $netTax >= 0 ? 'text-danger' : 'text-success';

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
        <div class="d-flex justify-content-between mb-2">
            <div><strong>Output Tax (Sales Tax Payable)</strong></div>
            <div><?= formatMoney($outputTax) ?></div>
        </div>
        <div class="d-flex justify-content-between mb-2">
            <div><strong>Input Tax (VAT Recoverable)</strong></div>
            <div><?= formatMoney($inputTax) ?></div>
        </div>
        <hr>
        <div class="d-flex justify-content-between">
            <div><strong>Net VAT <?= $netTax >= 0 ? 'Payable' : 'Recoverable' ?></strong></div>
            <div class="fw-bold <?= $netClass ?>"><?= formatMoney($netTax) ?></div>
        </div>
    </div>
</div>

<div class="alert alert-light border-start border-4 border-primary mt-3">
    <div class="d-flex align-items-center">
        <i class="bi bi-info-circle me-2 text-primary"></i>
        <div>VAT figures are derived from posted ledger entries between <?= htmlspecialchars($startDate) ?> and <?= htmlspecialchars($endDate) ?>, separating output and input tax per IFRS guidance.</div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
