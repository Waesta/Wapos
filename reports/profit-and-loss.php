<?php
require_once '../includes/bootstrap.php';

use App\Services\AccountingService;
use App\Services\LedgerDataService;

$auth->requireRole(['admin', 'manager']);

$db = Database::getInstance();
$pdo = $db->getConnection();
$accountingService = new AccountingService($pdo);
$ledgerDataService = new LedgerDataService($pdo, $accountingService);

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$statement = $ledgerDataService->getProfitAndLoss($startDate, $endDate);

$revenue = $statement['revenue'];
$contraRevenue = $statement['contra_revenue'];
$netRevenue = $statement['net_revenue'];
$cogs = $statement['cogs'];
$grossProfit = $statement['gross_profit'];
$operatingExpenses = $statement['operating_expenses'];
$nonOperatingExpenses = $statement['non_operating_expenses'];
$totalExpenses = $statement['total_expenses'];
$netProfit = $statement['net_profit'];

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
                <h4 class="mb-0 fw-bold text-warning"><?= formatMoney($operatingExpenses) ?></h4>
                <div class="small text-muted">Non-operating & Other Expenses <?= formatMoney($nonOperatingExpenses) ?></div>
                <div class="small text-muted">Total Expenses <?= formatMoney($totalExpenses) ?></div>
                <hr>
                <p class="mb-1">Net Profit</p>
                <h4 class="fw-bold text-info"><?= formatMoney($netProfit) ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-light border-start border-4 border-primary mt-3">
    <div class="d-flex align-items-center">
        <i class="bi bi-info-circle me-2 text-primary"></i>
        <div>Figures above are sourced from posted journal entries for the selected period, in line with IFRS for SMEs presentation.</div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
