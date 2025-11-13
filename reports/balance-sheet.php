<?php
require_once '../includes/bootstrap.php';

use App\Services\AccountingService;
use App\Services\LedgerDataService;

$auth->requireRole(['admin', 'manager']);

$db = Database::getInstance();
$pdo = $db->getConnection();
$accountingService = new AccountingService($pdo);
$ledgerDataService = new LedgerDataService($pdo, $accountingService);

$asOf = $_GET['as_of'] ?? date('Y-m-d');

$balanceSheet = $ledgerDataService->getBalanceSheet($asOf);

$assetsTotal = $balanceSheet['assets_total'];
$liabilitiesTotal = $balanceSheet['liabilities_total'];
$equityAccountsTotal = $balanceSheet['equity_accounts_total'];
$netIncome = $balanceSheet['net_income'];
$equityTotal = $balanceSheet['equity_total'];
$liabilitiesPlusEquity = $balanceSheet['liabilities_plus_equity'];

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
                <h3 class="mb-0 fw-bold text-success"><?= formatMoney($assetsTotal) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted mb-1 small">Liabilities</p>
                <h3 class="mb-0 fw-bold text-danger"><?= formatMoney($liabilitiesTotal) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted mb-1 small">Equity</p>
                <h3 class="mb-0 fw-bold text-info"><?= formatMoney($equityAccountsTotal) ?></h3>
                <div class="small text-muted">Net Income (to date): <?= formatMoney($netIncome) ?></div>
                <div class="small text-muted">Equity incl. Net Income: <?= formatMoney($equityTotal) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
        <div class="d-flex justify-content-between">
            <div><strong>Total Assets</strong></div>
            <div><?= formatMoney($assetsTotal) ?></div>
        </div>
        <div class="d-flex justify-content-between">
            <div><strong>Total Liabilities + Equity</strong></div>
            <div><?= formatMoney($liabilitiesPlusEquity) ?></div>
        </div>
    </div>
</div>

<div class="alert alert-light border-start border-4 border-primary mt-3">
    <div class="d-flex align-items-center">
        <i class="bi bi-info-circle me-2 text-primary"></i>
        <div>Balance sheet balances are calculated from posted journal entries as of <?= htmlspecialchars($asOf) ?>, following IFRS for SMEs classifications.</div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
