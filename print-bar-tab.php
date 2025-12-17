<?php
/**
 * Print Bar Tab Receipt
 * Generates a thermal printer-friendly receipt for bar tabs
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/bootstrap.php';

// QR generator is optional
if (file_exists(__DIR__ . '/includes/qr-generator.php')) {
    require_once 'includes/qr-generator.php';
}

use App\Services\BarTabService;

$auth->requireLogin();

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

// Get customer name if linked
$customerName = $tab['customer_name'] ?? $tab['guest_name'] ?? $tab['tab_name'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bar Tab - <?= htmlspecialchars($tab['tab_number']) ?></title>
    <link rel="stylesheet" href="css/thermal-print.css">
    <style>
        body {
            font-family: 'Courier New', monospace;
            width: 79.5mm;
            margin: 0 auto;
            padding: 5mm;
            font-size: 12px;
            line-height: 1.2;
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .header h2 {
            margin: 3px 0;
            font-size: 18px;
            font-weight: bold;
        }
        .logo {
            max-width: 60mm;
            height: auto;
            margin-bottom: 5px;
        }
        .info {
            font-size: 11px;
            margin-bottom: 8px;
        }
        .tab-info {
            border: 1px solid #000;
            padding: 5px;
            margin: 8px 0;
            font-size: 10px;
        }
        .tab-info div {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
        }
        .items {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 8px 0;
            margin: 8px 0;
        }
        .items-header {
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
            margin-bottom: 5px;
            font-size: 10px;
            display: flex;
            justify-content: space-between;
        }
        .item {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
            font-size: 11px;
        }
        .item-name {
            flex: 1;
            max-width: 70%;
        }
        .item-qty {
            width: 15%;
            text-align: center;
        }
        .item-price {
            width: 25%;
            text-align: right;
        }
        .item-portion {
            font-size: 9px;
            color: #666;
            margin-left: 10px;
        }
        .totals {
            margin-top: 10px;
            border-top: 1px dashed #000;
            padding-top: 8px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
            font-size: 11px;
        }
        .grand-total {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 10px;
            border-top: 1px dashed #000;
            padding-top: 8px;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border: 1px solid #000;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .voided {
            text-decoration: line-through;
            color: #999;
        }
        @media print {
            body {
                width: 100%;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <?php if (!empty($settings['receipt_logo'])): ?>
            <img src="<?= htmlspecialchars($settings['receipt_logo']) ?>" alt="Logo" class="logo">
        <?php endif; ?>
        <h2><?= htmlspecialchars($settings['business_name'] ?? 'BAR') ?></h2>
        <?php if (!empty($settings['business_address'])): ?>
            <div class="info"><?= nl2br(htmlspecialchars($settings['business_address'])) ?></div>
        <?php endif; ?>
        <?php if (!empty($settings['business_phone'])): ?>
            <div class="info">Tel: <?= htmlspecialchars($settings['business_phone']) ?></div>
        <?php endif; ?>
    </div>

    <div style="text-align: center; margin: 10px 0;">
        <?php $isPaid = isset($_GET['paid']) && $_GET['paid'] == '1'; ?>
        <strong style="font-size: 14px;"><?= $isPaid ? 'RECEIPT' : 'BAR TAB' ?></strong>
        <?php if ($isPaid): ?>
            <span class="status-badge" style="background:#000;color:#fff;">✓ PAID</span>
        <?php else: ?>
            <span class="status-badge"><?= strtoupper($tab['status']) ?></span>
        <?php endif; ?>
    </div>

    <div class="tab-info">
        <div><span>Tab #:</span><span><?= htmlspecialchars($tab['tab_number']) ?></span></div>
        <div><span>Guest:</span><span><?= htmlspecialchars($customerName) ?></span></div>
        <div><span>Server:</span><span><?= htmlspecialchars($serverName) ?></span></div>
        <?php if (!empty($tab['bar_station'])): ?>
        <div><span>Station:</span><span><?= htmlspecialchars($tab['bar_station']) ?></span></div>
        <?php endif; ?>
        <div><span>Opened:</span><span><?= date('d/m/Y H:i', strtotime($tab['created_at'])) ?></span></div>
        <?php if ($tab['status'] === 'closed' && !empty($tab['closed_at'])): ?>
        <div><span>Closed:</span><span><?= date('d/m/Y H:i', strtotime($tab['closed_at'])) ?></span></div>
        <?php endif; ?>
    </div>

    <div class="items">
        <div class="items-header">
            <span style="flex:1">Item</span>
            <span style="width:15%;text-align:center">Qty</span>
            <span style="width:25%;text-align:right">Amount</span>
        </div>
        
        <?php if (!empty($tab['items'])): ?>
            <?php foreach ($tab['items'] as $item): ?>
                <div class="item <?= $item['status'] === 'voided' ? 'voided' : '' ?>">
                    <div class="item-name">
                        <?= htmlspecialchars($item['item_name']) ?>
                        <?php if (!empty($item['portion_name'])): ?>
                            <span class="item-portion">(<?= htmlspecialchars($item['portion_name']) ?>)</span>
                        <?php endif; ?>
                    </div>
                    <div class="item-qty">x<?= $item['quantity'] ?></div>
                    <div class="item-price"><?= $currencySymbol ?> <?= number_format($item['total_price'], 2) ?></div>
                </div>
                <?php if (!empty($item['special_instructions'])): ?>
                    <div style="font-size:9px;color:#666;margin-left:10px;margin-bottom:3px;">
                        → <?= htmlspecialchars($item['special_instructions']) ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align:center;color:#666;padding:10px;">No items</div>
        <?php endif; ?>
    </div>

    <div class="totals">
        <div class="total-row">
            <span>Subtotal:</span>
            <span><?= $currencySymbol ?> <?= number_format($tab['subtotal'] ?? 0, 2) ?></span>
        </div>
        <?php if (($tab['tax_amount'] ?? 0) > 0): ?>
        <div class="total-row">
            <span>Tax:</span>
            <span><?= $currencySymbol ?> <?= number_format($tab['tax_amount'], 2) ?></span>
        </div>
        <?php endif; ?>
        <?php if (($tab['discount_amount'] ?? 0) > 0): ?>
        <div class="total-row">
            <span>Discount:</span>
            <span>-<?= $currencySymbol ?> <?= number_format($tab['discount_amount'], 2) ?></span>
        </div>
        <?php endif; ?>
        <?php if (($tab['tip_amount'] ?? 0) > 0): ?>
        <div class="total-row">
            <span>Tip:</span>
            <span><?= $currencySymbol ?> <?= number_format($tab['tip_amount'], 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="total-row grand-total">
            <span>TOTAL:</span>
            <span><?= $currencySymbol ?> <?= number_format($tab['total_amount'] ?? 0, 2) ?></span>
        </div>
    </div>

    <?php if (!empty($tab['payments']) && count($tab['payments']) > 0): ?>
    <div style="margin-top:10px;border-top:1px dashed #000;padding-top:8px;">
        <div style="font-weight:bold;font-size:11px;margin-bottom:5px;">PAYMENTS</div>
        <?php foreach ($tab['payments'] as $payment): ?>
        <div class="total-row">
            <span><?= ucfirst($payment['payment_method']) ?>:</span>
            <span><?= $currencySymbol ?> <?= number_format($payment['amount'], 2) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="footer">
        <?php if (!empty($settings['receipt_footer'])): ?>
            <p><?= nl2br(htmlspecialchars($settings['receipt_footer'])) ?></p>
        <?php else: ?>
            <p>Thank you for your visit!</p>
        <?php endif; ?>
        <p style="font-size:9px;color:#666;">Printed: <?= date('d/m/Y H:i:s') ?></p>
    </div>

    <div class="no-print" style="text-align:center;margin-top:20px;">
        <button onclick="window.print()" style="padding:10px 30px;font-size:14px;cursor:pointer;">
            🖨️ Print
        </button>
        <button onclick="window.close()" style="padding:10px 30px;font-size:14px;cursor:pointer;margin-left:10px;">
            ✕ Close
        </button>
    </div>

    <script>
        // Auto-print if requested
        <?php if (isset($_GET['auto_print']) && $_GET['auto_print'] !== '0'): ?>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
        <?php endif; ?>
    </script>
</body>
</html>
