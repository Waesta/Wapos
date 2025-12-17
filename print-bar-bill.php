<?php
/**
 * Print Bar Bill / Check
 * Small thermal format for customers to review before paying
 * Waiter brings this to the table, customer reviews and pays
 */

require_once 'includes/bootstrap.php';
$auth->requireLogin();

use App\Services\BarTabService;

$db = Database::getInstance();
$tabService = new BarTabService($db->getConnection());

$tabId = (int)($_GET['tab_id'] ?? $_GET['id'] ?? 0);

if (!$tabId) {
    die('Tab ID required');
}

// Get tab details
$tab = $tabService->getTab($tabId);

if (!$tab) {
    die('Tab not found');
}

// Get settings
$settingsRaw = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($settingsRaw as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Currency formatting
require_once 'includes/currency-config.php';
$currency = CurrencyManager::getInstance();
$currencySymbol = $currency->getCurrencySymbol() ?: $currency->getCurrencyCode() ?: '';

// Get server/waiter name
$serverName = $tab['server_name'] ?? 'N/A';
$customerName = $tab['tab_name'] ?? 'Guest';

// Calculate totals
$subtotal = 0;
$itemCount = 0;
foreach ($tab['items'] ?? [] as $item) {
    if ($item['status'] !== 'voided') {
        $subtotal += $item['total_price'];
        $itemCount += $item['quantity'];
    }
}
$taxAmount = $tab['tax_amount'] ?? 0;
$discountAmount = $tab['discount_amount'] ?? 0;
$totalAmount = $tab['total_amount'] ?? ($subtotal + $taxAmount - $discountAmount);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill - <?= htmlspecialchars($tab['tab_number']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Courier New', monospace;
            width: 79.5mm;
            margin: 0 auto;
            padding: 5mm;
            font-size: 12px;
            line-height: 1.3;
            background: #fff;
        }
        .header {
            text-align: center;
            border-bottom: 2px double #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .header h1 {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .header .subtitle {
            font-size: 11px;
            color: #666;
        }
        .bill-title {
            text-align: center;
            margin: 10px 0;
            padding: 8px;
            background: #f0f0f0;
            border: 1px solid #000;
        }
        .bill-title h2 {
            font-size: 16px;
            letter-spacing: 2px;
        }
        .info-box {
            border: 1px dashed #000;
            padding: 8px;
            margin: 10px 0;
            font-size: 11px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }
        .items-section {
            margin: 10px 0;
        }
        .items-header {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
            margin-bottom: 5px;
        }
        .item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 4px 0;
            border-bottom: 1px dotted #ccc;
            font-size: 11px;
        }
        .item-left {
            flex: 1;
        }
        .item-name {
            font-weight: 500;
        }
        .item-portion {
            font-size: 9px;
            color: #666;
        }
        .item-qty {
            width: 30px;
            text-align: center;
        }
        .item-price {
            width: 70px;
            text-align: right;
            font-weight: 500;
        }
        .totals {
            margin-top: 10px;
            border-top: 2px solid #000;
            padding-top: 8px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
            font-size: 11px;
        }
        .grand-total {
            font-size: 16px;
            font-weight: bold;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 8px 0;
            margin-top: 8px;
            background: #000;
            color: #fff;
            padding-left: 5px;
            padding-right: 5px;
        }
        .message {
            text-align: center;
            margin: 15px 0;
            padding: 10px;
            border: 2px dashed #000;
            font-size: 11px;
        }
        .message strong {
            display: block;
            font-size: 12px;
            margin-bottom: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 10px;
            color: #666;
            border-top: 1px dashed #000;
            padding-top: 8px;
        }
        .waiter-sig {
            margin-top: 15px;
            border-top: 1px solid #000;
            padding-top: 10px;
        }
        .waiter-sig .label {
            font-size: 10px;
            color: #666;
        }
        .waiter-sig .name {
            font-size: 14px;
            font-weight: bold;
            margin-top: 3px;
        }
        .no-print {
            margin-top: 15px;
            text-align: center;
        }
        .no-print button {
            padding: 10px 20px;
            font-size: 14px;
            cursor: pointer;
            margin: 0 5px;
            border: 1px solid #333;
            background: #fff;
        }
        .no-print button:hover {
            background: #f0f0f0;
        }
        .btn-primary {
            background: #007bff !important;
            color: white !important;
            border-color: #007bff !important;
        }
        @media print {
            body {
                width: 100%;
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none;
            }
            @page {
                margin: 2mm;
                size: 80mm auto;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= htmlspecialchars($settings['business_name'] ?? 'BAR') ?></h1>
        <?php if (!empty($settings['business_phone'])): ?>
            <div class="subtitle">Tel: <?= htmlspecialchars($settings['business_phone']) ?></div>
        <?php endif; ?>
    </div>

    <div class="bill-title">
        <h2>★ YOUR BILL ★</h2>
    </div>

    <div class="info-box">
        <div class="info-row">
            <span>Tab:</span>
            <span><strong><?= htmlspecialchars($tab['tab_number']) ?></strong></span>
        </div>
        <div class="info-row">
            <span>Guest:</span>
            <span><?= htmlspecialchars($customerName) ?></span>
        </div>
        <div class="info-row">
            <span>Server:</span>
            <span><?= htmlspecialchars($serverName) ?></span>
        </div>
        <?php if (!empty($tab['bar_station'])): ?>
        <div class="info-row">
            <span>Station:</span>
            <span><?= htmlspecialchars($tab['bar_station']) ?></span>
        </div>
        <?php endif; ?>
        <div class="info-row">
            <span>Date:</span>
            <span><?= date('d/m/Y H:i') ?></span>
        </div>
    </div>

    <div class="items-section">
        <div class="items-header">
            <span style="flex:1">Item</span>
            <span style="width:30px;text-align:center">Qty</span>
            <span style="width:70px;text-align:right">Amount</span>
        </div>
        
        <?php foreach ($tab['items'] ?? [] as $item): ?>
            <?php if ($item['status'] === 'voided') continue; ?>
            <div class="item">
                <div class="item-left">
                    <div class="item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                    <?php if (!empty($item['portion_name'])): ?>
                        <div class="item-portion">(<?= htmlspecialchars($item['portion_name']) ?>)</div>
                    <?php endif; ?>
                </div>
                <div class="item-qty">x<?= $item['quantity'] ?></div>
                <div class="item-price"><?= number_format($item['total_price'], 2) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="totals">
        <div class="total-row">
            <span>Subtotal (<?= $itemCount ?> items):</span>
            <span><?= $currencySymbol ?> <?= number_format($subtotal, 2) ?></span>
        </div>
        <?php if ($taxAmount > 0): ?>
        <div class="total-row">
            <span>Tax:</span>
            <span><?= $currencySymbol ?> <?= number_format($taxAmount, 2) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($discountAmount > 0): ?>
        <div class="total-row">
            <span>Discount:</span>
            <span>-<?= $currencySymbol ?> <?= number_format($discountAmount, 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="total-row grand-total">
            <span>TOTAL DUE:</span>
            <span><?= $currencySymbol ?> <?= number_format($totalAmount, 2) ?></span>
        </div>
    </div>

    <div class="message">
        <strong>Thank you for visiting!</strong>
        Please settle your bill with your server.<br>
        We accept Cash, Card & Mobile Money.
    </div>

    <div class="waiter-sig">
        <div class="label">Your Server:</div>
        <div class="name"><?= htmlspecialchars($serverName) ?></div>
    </div>

    <div class="footer">
        <p><?= htmlspecialchars($settings['receipt_footer'] ?? 'Thank you for your patronage!') ?></p>
        <p style="margin-top:5px;font-size:9px;">Printed: <?= date('d/m/Y H:i:s') ?></p>
    </div>

    <div class="no-print">
        <button class="btn-primary" onclick="window.print()">🖨️ Print Bill</button>
        <button onclick="window.close()">✕ Close</button>
    </div>

    <script>
        // Auto-print if requested
        <?php if (isset($_GET['auto_print']) && $_GET['auto_print'] !== '0'): ?>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 300);
        };
        <?php endif; ?>
    </script>
</body>
</html>
