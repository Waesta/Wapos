<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();
$saleId = $_GET['id'] ?? 0;

// Get sale details
$sale = $db->fetchOne("
    SELECT s.*, u.full_name as cashier_name
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
", [$saleId]);

if (!$sale) {
    die('Sale not found');
}

// Get sale items
$items = $db->fetchAll("
    SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id
", [$saleId]);

// Get settings
$settingsRaw = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($settingsRaw as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?= htmlspecialchars($sale['sale_number']) ?></title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            width: 80mm;
            margin: 0 auto;
            padding: 10px;
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .header h2 {
            margin: 5px 0;
            font-size: 20px;
        }
        .info {
            font-size: 12px;
            margin-bottom: 10px;
        }
        .items {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 10px 0;
            margin: 10px 0;
        }
        .item {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 12px;
        }
        .totals {
            margin: 10px 0;
            font-size: 13px;
        }
        .total-line {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }
        .grand-total {
            font-size: 16px;
            font-weight: bold;
            border-top: 2px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }
        .footer {
            text-align: center;
            border-top: 2px dashed #000;
            padding-top: 10px;
            margin-top: 10px;
            font-size: 11px;
        }
        @media print {
            body {
                width: 80mm;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2><?= htmlspecialchars($settings['business_name'] ?? APP_NAME) ?></h2>
        <?php if (!empty($settings['business_address'])): ?>
        <div><?= nl2br(htmlspecialchars($settings['business_address'])) ?></div>
        <?php endif; ?>
        <?php if (!empty($settings['business_phone'])): ?>
        <div>Tel: <?= htmlspecialchars($settings['business_phone']) ?></div>
        <?php endif; ?>
        <?php if (!empty($settings['receipt_header'])): ?>
        <div style="margin-top: 10px;"><?= htmlspecialchars($settings['receipt_header']) ?></div>
        <?php endif; ?>
    </div>

    <div class="info">
        <div><strong>Receipt #:</strong> <?= htmlspecialchars($sale['sale_number']) ?></div>
        <div><strong>Date:</strong> <?= formatDate($sale['created_at'], 'd/m/Y H:i') ?></div>
        <div><strong>Cashier:</strong> <?= htmlspecialchars($sale['cashier_name']) ?></div>
        <?php if ($sale['customer_name']): ?>
        <div><strong>Customer:</strong> <?= htmlspecialchars($sale['customer_name']) ?></div>
        <?php endif; ?>
    </div>

    <div class="items">
        <?php foreach ($items as $item): ?>
        <div class="item">
            <div style="flex: 1;">
                <div><strong><?= htmlspecialchars($item['product_name']) ?></strong></div>
                <div><?= $item['quantity'] ?> x <?= formatMoney($item['unit_price']) ?></div>
            </div>
            <div><strong><?= formatMoney($item['total_price']) ?></strong></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="totals">
        <div class="total-line">
            <span>Subtotal:</span>
            <span><?= formatMoney($sale['subtotal']) ?></span>
        </div>
        <?php if ($sale['tax_amount'] > 0): ?>
        <div class="total-line">
            <span>Tax:</span>
            <span><?= formatMoney($sale['tax_amount']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($sale['discount_amount'] > 0): ?>
        <div class="total-line">
            <span>Discount:</span>
            <span>-<?= formatMoney($sale['discount_amount']) ?></span>
        </div>
        <?php endif; ?>
        <div class="total-line grand-total">
            <span>TOTAL:</span>
            <span><?= formatMoney($sale['total_amount']) ?></span>
        </div>
        <div class="total-line">
            <span>Paid:</span>
            <span><?= formatMoney($sale['amount_paid']) ?></span>
        </div>
        <?php if ($sale['change_amount'] > 0): ?>
        <div class="total-line">
            <span>Change:</span>
            <span><?= formatMoney($sale['change_amount']) ?></span>
        </div>
        <?php endif; ?>
        <div class="total-line">
            <span>Payment:</span>
            <span><?= ucfirst(str_replace('_', ' ', $sale['payment_method'])) ?></span>
        </div>
    </div>

    <div class="footer">
        <?php if (!empty($settings['receipt_footer'])): ?>
        <div><?= nl2br(htmlspecialchars($settings['receipt_footer'])) ?></div>
        <?php endif; ?>
        <div style="margin-top: 10px;">Thank you for your business!</div>
        <div>Powered by <?= APP_NAME ?></div>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer;">
            Print Receipt
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; margin-left: 10px;">
            Close
        </button>
    </div>

    <script>
        // Auto print when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
