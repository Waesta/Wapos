<?php
/**
 * Register Z-Report / X-Report
 * Print-friendly session report
 */

require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();
$currencySymbol = CurrencyManager::getInstance()->getCurrencySymbol();

$registerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$reportType = $_GET['type'] ?? 'z-report';
$sessionId = isset($_GET['session']) ? (int)$_GET['session'] : null;

if (!$registerId) {
    die('Register ID required');
}

// Get register info
$register = $db->fetchOne("
    SELECT r.*, l.name as location_name 
    FROM registers r 
    JOIN locations l ON r.location_id = l.id 
    WHERE r.id = ?
", [$registerId]);

if (!$register) {
    die('Register not found');
}

// Get session - either specific or current open session
if ($sessionId) {
    $session = $db->fetchOne("
        SELECT rs.*, u.full_name as cashier_name
        FROM register_sessions rs
        JOIN users u ON rs.user_id = u.id
        WHERE rs.id = ? AND rs.register_id = ?
    ", [$sessionId, $registerId]);
} else {
    // Get current or most recent session
    $session = $db->fetchOne("
        SELECT rs.*, u.full_name as cashier_name
        FROM register_sessions rs
        JOIN users u ON rs.user_id = u.id
        WHERE rs.register_id = ?
        ORDER BY rs.opened_at DESC
        LIMIT 1
    ", [$registerId]);
}

if (!$session) {
    die('No session found for this register');
}

// Get cash movements for this session
$movements = $db->fetchAll("
    SELECT rcm.*, u.full_name as created_by_name
    FROM register_cash_movements rcm
    LEFT JOIN users u ON rcm.created_by = u.id
    WHERE rcm.session_id = ?
    ORDER BY rcm.created_at
", [$session['id']]);

// Get sales breakdown by payment method
$salesBreakdown = $db->fetchAll("
    SELECT payment_method, COUNT(*) as count, SUM(total_amount) as total
    FROM sales
    WHERE session_id = ?
    GROUP BY payment_method
", [$session['id']]);

// Calculate expected balance
$cashIn = 0;
$cashOut = 0;
foreach ($movements as $m) {
    if (in_array($m['movement_type'], ['cash_in', 'float'])) {
        $cashIn += $m['amount'];
    } else {
        $cashOut += $m['amount'];
    }
}

$expectedBalance = $session['opening_balance'] + ($session['cash_sales'] ?? 0) + $cashIn - $cashOut;

// Business info
$businessName = settings('business_name') ?? 'Business';
$businessAddress = settings('business_address') ?? '';
$businessPhone = settings('business_phone') ?? '';

$reportTitle = $reportType === 'z-report' ? 'Z-REPORT (End of Day)' : 'X-REPORT (Mid-Shift)';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $reportTitle ?> - <?= htmlspecialchars($register['name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            padding: 20px;
            max-width: 400px;
            margin: 0 auto;
        }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 16px; margin-bottom: 5px; }
        .header h2 { font-size: 14px; font-weight: normal; }
        .divider { border-top: 1px dashed #000; margin: 10px 0; }
        .double-divider { border-top: 2px solid #000; margin: 10px 0; }
        .row { display: flex; justify-content: space-between; padding: 2px 0; }
        .row.indent { padding-left: 20px; }
        .row .label { flex: 1; }
        .row .value { text-align: right; min-width: 100px; }
        .section-title { font-weight: bold; margin: 15px 0 5px 0; text-transform: uppercase; }
        .total-row { font-weight: bold; font-size: 14px; }
        .variance-positive { color: green; }
        .variance-negative { color: red; }
        .footer { text-align: center; margin-top: 20px; font-size: 10px; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
        .print-btn {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">🖨️ Print</button>

    <div class="header">
        <h1><?= htmlspecialchars($businessName) ?></h1>
        <?php if ($businessAddress): ?>
            <div><?= htmlspecialchars($businessAddress) ?></div>
        <?php endif; ?>
        <?php if ($businessPhone): ?>
            <div>Tel: <?= htmlspecialchars($businessPhone) ?></div>
        <?php endif; ?>
        <div class="divider"></div>
        <h2><?= $reportTitle ?></h2>
    </div>

    <div class="section-title">Register Information</div>
    <div class="row">
        <span class="label">Register:</span>
        <span class="value"><?= htmlspecialchars($register['name']) ?></span>
    </div>
    <div class="row">
        <span class="label">Register #:</span>
        <span class="value"><?= htmlspecialchars($register['register_number']) ?></span>
    </div>
    <div class="row">
        <span class="label">Location:</span>
        <span class="value"><?= htmlspecialchars($register['location_name']) ?></span>
    </div>

    <div class="divider"></div>

    <div class="section-title">Session Details</div>
    <div class="row">
        <span class="label">Session #:</span>
        <span class="value"><?= htmlspecialchars($session['session_number']) ?></span>
    </div>
    <div class="row">
        <span class="label">Cashier:</span>
        <span class="value"><?= htmlspecialchars($session['cashier_name']) ?></span>
    </div>
    <div class="row">
        <span class="label">Opened:</span>
        <span class="value"><?= date('M j, Y g:i A', strtotime($session['opened_at'])) ?></span>
    </div>
    <?php if ($session['closed_at']): ?>
    <div class="row">
        <span class="label">Closed:</span>
        <span class="value"><?= date('M j, Y g:i A', strtotime($session['closed_at'])) ?></span>
    </div>
    <?php endif; ?>
    <div class="row">
        <span class="label">Status:</span>
        <span class="value"><?= strtoupper($session['status']) ?></span>
    </div>

    <div class="double-divider"></div>

    <div class="section-title">Sales Summary</div>
    <div class="row">
        <span class="label">Total Transactions:</span>
        <span class="value"><?= number_format($session['transaction_count'] ?? 0) ?></span>
    </div>
    <div class="divider"></div>
    
    <div class="row">
        <span class="label">Cash Sales:</span>
        <span class="value"><?= $currencySymbol ?> <?= number_format($session['cash_sales'] ?? 0, 2) ?></span>
    </div>
    <div class="row">
        <span class="label">Card Sales:</span>
        <span class="value"><?= $currencySymbol ?> <?= number_format($session['card_sales'] ?? 0, 2) ?></span>
    </div>
    <div class="row">
        <span class="label">Mobile Sales:</span>
        <span class="value"><?= $currencySymbol ?> <?= number_format($session['mobile_sales'] ?? 0, 2) ?></span>
    </div>
    <div class="divider"></div>
    <div class="row total-row">
        <span class="label">TOTAL SALES:</span>
        <span class="value"><?= $currencySymbol ?> <?= number_format($session['total_sales'] ?? 0, 2) ?></span>
    </div>

    <?php if (!empty($movements)): ?>
    <div class="double-divider"></div>
    <div class="section-title">Cash Movements</div>
    <?php foreach ($movements as $m): ?>
    <div class="row indent">
        <span class="label"><?= ucfirst(str_replace('_', ' ', $m['movement_type'])) ?>:</span>
        <span class="value"><?= in_array($m['movement_type'], ['cash_out', 'pickup', 'drop']) ? '-' : '+' ?><?= $currencySymbol ?> <?= number_format($m['amount'], 2) ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <div class="double-divider"></div>

    <div class="section-title">Cash Drawer</div>
    <div class="row">
        <span class="label">Opening Balance:</span>
        <span class="value"><?= $currencySymbol ?> <?= number_format($session['opening_balance'], 2) ?></span>
    </div>
    <div class="row">
        <span class="label">+ Cash Sales:</span>
        <span class="value"><?= $currencySymbol ?> <?= number_format($session['cash_sales'] ?? 0, 2) ?></span>
    </div>
    <?php if ($cashIn > 0): ?>
    <div class="row">
        <span class="label">+ Cash In:</span>
        <span class="value"><?= $currencySymbol ?> <?= number_format($cashIn, 2) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($cashOut > 0): ?>
    <div class="row">
        <span class="label">- Cash Out:</span>
        <span class="value"><?= $currencySymbol ?> <?= number_format($cashOut, 2) ?></span>
    </div>
    <?php endif; ?>
    <div class="divider"></div>
    <div class="row total-row">
        <span class="label">EXPECTED BALANCE:</span>
        <span class="value"><?= $currencySymbol ?> <?= number_format($expectedBalance, 2) ?></span>
    </div>

    <?php if ($session['closing_balance'] !== null): ?>
    <div class="row">
        <span class="label">Counted Balance:</span>
        <span class="value"><?= $currencySymbol ?> <?= number_format($session['closing_balance'], 2) ?></span>
    </div>
    <?php 
        $variance = $session['closing_balance'] - $expectedBalance;
        $varianceClass = $variance >= 0 ? 'variance-positive' : 'variance-negative';
    ?>
    <div class="row total-row <?= $varianceClass ?>">
        <span class="label">VARIANCE:</span>
        <span class="value"><?= $currencySymbol ?> <?= number_format($variance, 2) ?> <?= $variance > 0 ? '(OVER)' : ($variance < 0 ? '(SHORT)' : '') ?></span>
    </div>
    <?php endif; ?>

    <?php if ($session['closing_notes']): ?>
    <div class="divider"></div>
    <div class="section-title">Notes</div>
    <div><?= htmlspecialchars($session['closing_notes']) ?></div>
    <?php endif; ?>

    <div class="double-divider"></div>

    <div class="footer">
        <div>Report Generated: <?= date('M j, Y g:i:s A') ?></div>
        <div>Generated by: <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'System') ?></div>
        <div style="margin-top: 10px;">*** <?= $reportType === 'z-report' ? 'END OF DAY REPORT' : 'MID-SHIFT REPORT' ?> ***</div>
    </div>
</body>
</html>
