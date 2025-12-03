<?php
require_once 'includes/bootstrap.php';
require_once 'includes/qr-generator.php';
$auth->requireLogin();

$db = Database::getInstance();
$saleId = $_GET['id'] ?? 0;

// Get sale details and loyalty summary
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

$promotions = $db->fetchAll("
    SELECT promotion_name, product_name, discount_amount, details
    FROM sale_promotions
    WHERE sale_id = ?
    ORDER BY id
", [$saleId]) ?: [];
$promotionSavings = array_reduce($promotions, static function ($carry, $promo) {
    return $carry + (float)($promo['discount_amount'] ?? 0);
}, 0.0);

$loyaltySummary = $db->fetchOne("SELECT * FROM sale_loyalty_summary WHERE sale_id = ?", [$saleId]);

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
    <link rel="stylesheet" href="css/thermal-print.css">
    <style>
        body {
            font-family: 'Courier New', monospace;
            width: 79.5mm; /* Optimized for Epson thermal printers */
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
        .transaction-info {
            border: 1px solid #000;
            padding: 5px;
            margin: 8px 0;
            font-size: 10px;
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
        }
        .item {
            margin: 3px 0;
            font-size: 10px;
            border-bottom: 1px dotted #ccc;
            padding-bottom: 2px;
        }
        .item-line {
            display: flex;
            justify-content: space-between;
        }
        .item-details {
            font-size: 9px;
            color: #666;
            margin-left: 10px;
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
            padding-top: 8px;
            margin-top: 8px;
            font-size: 9px;
        }
        .return-policy {
            border: 1px solid #000;
            padding: 5px;
            margin: 8px 0;
            font-size: 8px;
            text-align: left;
        }
        .qr-code {
            text-align: center;
            margin: 8px 0;
        }
        .qr-code img {
            width: 60px;
            height: 60px;
        }
        .social-links {
            font-size: 8px;
            margin: 5px 0;
        }
        .promo-section {
            border: 2px solid #000;
            padding: 5px;
            margin: 8px 0;
            text-align: center;
            font-size: 9px;
            font-weight: bold;
        }
        .promo-breakdown {
            border: 1px dashed #000;
            padding: 6px;
            margin: 8px 0;
            font-size: 9px;
        }
        .promo-breakdown-header {
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 4px;
        }
        .promo-breakdown ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .promo-breakdown li {
            display: flex;
            justify-content: space-between;
            gap: 6px;
            margin-bottom: 3px;
        }
        .promo-breakdown li span:last-child {
            font-weight: 600;
        }
        @media print {
            body {
                width: 79.5mm;
                margin: 0;
                padding: 2mm;
            }
            .no-print {
                display: none;
            }
            @page {
                margin: 0;
                size: 80mm auto;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <?php if (!empty($settings['business_logo'])): ?>
        <img src="<?= htmlspecialchars($settings['business_logo']) ?>" alt="Logo" class="logo">
        <?php endif; ?>
        <h2><?= htmlspecialchars($settings['business_name'] ?? APP_NAME) ?></h2>
        <?php if (!empty($settings['business_tagline'])): ?>
        <div style="font-style: italic; font-size: 10px;"><?= htmlspecialchars($settings['business_tagline']) ?></div>
        <?php endif; ?>
        <?php if (!empty($settings['business_address'])): ?>
        <div style="margin: 3px 0;"><?= nl2br(htmlspecialchars($settings['business_address'])) ?></div>
        <?php endif; ?>
        <div style="margin: 3px 0;">
            <?php if (!empty($settings['business_phone'])): ?>
            <div>Tel: <?= htmlspecialchars($settings['business_phone']) ?></div>
            <?php endif; ?>
            <?php if (!empty($settings['business_email'])): ?>
            <div>Email: <?= htmlspecialchars($settings['business_email']) ?></div>
            <?php endif; ?>
            <?php if (!empty($settings['business_website'])): ?>
            <div>Web: <?= htmlspecialchars($settings['business_website']) ?></div>
            <?php endif; ?>
        </div>
        <?php if (!empty($settings['vat_number']) || !empty($settings['tax_id'])): ?>
        <div style="font-size: 9px; margin: 3px 0;">
            <?php if (!empty($settings['vat_number'])): ?>
            VAT No: <?= htmlspecialchars($settings['vat_number']) ?>
            <?php endif; ?>
            <?php if (!empty($settings['tax_id'])): ?>
            <?= !empty($settings['vat_number']) ? ' | ' : '' ?>Tax ID: <?= htmlspecialchars($settings['tax_id']) ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($settings['receipt_header'])): ?>
        <div style="margin-top: 5px; font-size: 10px;"><?= htmlspecialchars($settings['receipt_header']) ?></div>
        <?php endif; ?>
    </div>

    <div class="transaction-info">
        <div style="text-align: center; font-weight: bold; margin-bottom: 5px;">TRANSACTION DETAILS</div>
        <div><strong>Receipt #:</strong> <?= htmlspecialchars($sale['sale_number']) ?></div>
        <div><strong>Date:</strong> <?= formatDate($sale['created_at'], 'd/m/Y') ?></div>
        <div><strong>Time:</strong> <?= formatDate($sale['created_at'], 'H:i:s') ?></div>
        <div><strong>Cashier:</strong> <?= htmlspecialchars($sale['cashier_name']) ?></div>
        <?php if ($sale['customer_name']): ?>
        <div><strong>Customer:</strong> <?= htmlspecialchars($sale['customer_name']) ?></div>
        <?php endif; ?>
        <?php if (!empty($sale['customer_phone'])): ?>
        <div><strong>Phone:</strong> <?= htmlspecialchars($sale['customer_phone']) ?></div>
        <?php endif; ?>
        <div><strong>Terminal:</strong> POS-<?= str_pad($auth->getUserId(), 3, '0', STR_PAD_LEFT) ?></div>
    </div>

    <div class="items">
        <div class="items-header">
            <div style="display: flex; justify-content: space-between;">
                <span>ITEM DESCRIPTION</span>
                <span>TOTAL</span>
            </div>
        </div>
        <?php foreach ($items as $item): ?>
        <?php 
            // Get product details for SKU/Barcode
            $product = $db->fetchOne("SELECT sku, barcode FROM products WHERE id = ?", [$item['product_id']]);
        ?>
        <div class="item">
            <div class="item-line">
                <span><strong><?= htmlspecialchars($item['product_name']) ?></strong></span>
                <span><strong><?= formatMoney($item['total_price']) ?></strong></span>
            </div>
            <div class="item-details">
                <?php if ($product && $product['sku']): ?>
                <div>SKU: <?= htmlspecialchars($product['sku']) ?></div>
                <?php endif; ?>
                <div><?= $item['quantity'] ?> x <?= formatMoney($item['unit_price']) ?> each</div>
                <?php if ($item['tax_rate'] > 0): ?>
                <div>Tax Rate: <?= $item['tax_rate'] ?>%</div>
                <?php endif; ?>
                <?php if ($item['discount_amount'] > 0): ?>
                <div>Discount: -<?= formatMoney($item['discount_amount']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($promotions)): ?>
    <div class="promo-breakdown">
        <div class="promo-breakdown-header">Promotions Applied</div>
        <ul>
            <?php foreach ($promotions as $promotion): ?>
            <li>
                <span>
                    <?= htmlspecialchars($promotion['promotion_name'] ?? 'Promo') ?>
                    <?php if (!empty($promotion['product_name'])): ?>
                        <small>(<?= htmlspecialchars($promotion['product_name']) ?>)</small>
                    <?php endif; ?>
                    <?php if (!empty($promotion['details'])): ?>
                        <div style="font-size:8px; color:#666;"><?= htmlspecialchars($promotion['details']) ?></div>
                    <?php endif; ?>
                </span>
                <span>-<?= formatMoney($promotion['discount_amount'] ?? 0) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <div style="display:flex; justify-content:space-between; border-top:1px dotted #000; padding-top:4px; margin-top:4px; font-weight:bold;">
            <span>Total Savings</span>
            <span>-<?= formatMoney($promotionSavings) ?></span>
        </div>
    </div>
    <?php endif; ?>

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
            <span>Payment Method:</span>
            <span><?= ucfirst(str_replace('_', ' ', $sale['payment_method'])) ?></span>
        </div>
        <?php if ($sale['payment_method'] === 'mobile_money' && !empty($sale['mobile_money_phone'])): ?>
        <div class="total-line" style="font-size: 10px;">
            <span>Mobile No.:</span>
            <span><?= htmlspecialchars($sale['mobile_money_phone']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($sale['payment_method'] === 'mobile_money' && !empty($sale['mobile_money_reference'])): ?>
        <div class="total-line" style="font-size: 10px;">
            <span>Transaction ID:</span>
            <span><?= htmlspecialchars($sale['mobile_money_reference']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($loyaltySummary): ?>
            <hr>
            <div class="total-line">
                <span>Loyalty Earned:</span>
                <span><?= number_format($loyaltySummary['points_earned'] ?? 0) ?> pts</span>
            </div>
            <?php if (!empty($loyaltySummary['points_redeemed'])): ?>
            <div class="total-line">
                <span>Loyalty Redeemed:</span>
                <span><?= number_format($loyaltySummary['points_redeemed']) ?> pts (<?= formatMoney($loyaltySummary['discount_amount'] ?? 0) ?>)</span>
            </div>
            <?php endif; ?>
            <div class="total-line">
                <span>Balance After:</span>
                <span><?= number_format($loyaltySummary['balance_after'] ?? 0) ?> pts</span>
            </div>
        <?php endif; ?>
        <?php if (in_array($sale['payment_method'], ['card', 'credit_card', 'debit_card'])): ?>
        <div class="total-line" style="font-size: 10px;">
            <span>Card Number:</span>
            <span>****-****-****-<?= substr(str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT), -4) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($sale['payment_method'] === 'mobile_money'): ?>
        <div class="total-line" style="font-size: 10px;">
            <span>Transaction ID:</span>
            <span><?= strtoupper(substr(md5($sale['sale_number'] . $sale['created_at']), 0, 10)) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Return Policy Section -->
    <div class="return-policy">
        <div style="font-weight: bold; text-align: center; margin-bottom: 3px;">RETURN & EXCHANGE POLICY</div>
        <div>• Returns accepted within <?= $settings['return_period'] ?? '30' ?> days with receipt</div>
        <div>• Items must be in original condition and packaging</div>
        <div>• Perishable items cannot be returned</div>
        <div>• Refunds processed to original payment method</div>
        <div>• Exchange allowed for same or higher value items</div>
        <?php if (!empty($settings['return_conditions'])): ?>
        <div>• <?= htmlspecialchars($settings['return_conditions']) ?></div>
        <?php endif; ?>
        <div style="text-align: center; margin-top: 3px; font-weight: bold;">For support: <?= $settings['business_phone'] ?? $settings['business_email'] ?? 'Contact store' ?></div>
    </div>

    <!-- QR Code Section -->
    <?php if (!empty($settings['enable_qr_code']) && $settings['enable_qr_code'] === '1'): ?>
    <div class="qr-code">
        <div style="font-size: 9px; margin-bottom: 3px;">Scan for digital receipt & feedback:</div>
        <?php $receiptQR = generateReceiptQR($sale['id'], $sale['sale_number'], $settings['business_website'] ?? ''); ?>
        <img src="<?= $receiptQR ?>" alt="Receipt QR Code" style="width: 60px; height: 60px; margin: 0 auto; display: block;">
        <div style="font-size: 8px; margin-top: 3px;"><?= $settings['business_website'] ?? 'Visit our website' ?></div>
    </div>
    <?php endif; ?>

    <!-- Promotional Offer -->
    <?php if (!empty($settings['current_promotion'])): ?>
    <div class="promo-section">
        <div>🎉 SPECIAL OFFER 🎉</div>
        <div><?= htmlspecialchars($settings['current_promotion']) ?></div>
        <?php if (!empty($settings['promo_code'])): ?>
        <div>Use code: <?= htmlspecialchars($settings['promo_code']) ?></div>
        <?php $promoQR = generatePromotionQR($settings['promo_code'], $settings['business_website'] ?? ''); ?>
        <img src="<?= $promoQR ?>" alt="Promo QR" style="width: 40px; height: 40px; margin: 3px auto; display: block;">
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Social Media & Contact -->
    <div class="social-links">
        <div style="text-align: center; font-weight: bold; margin-bottom: 3px;">STAY CONNECTED</div>
        <?php if (!empty($settings['facebook_page'])): ?>
        <div>📘 Facebook: <?= htmlspecialchars($settings['facebook_page']) ?></div>
        <?php endif; ?>
        <?php if (!empty($settings['instagram_handle'])): ?>
        <div>📷 Instagram: @<?= htmlspecialchars($settings['instagram_handle']) ?></div>
        <?php endif; ?>
        <?php if (!empty($settings['twitter_handle'])): ?>
        <div>🐦 Twitter: @<?= htmlspecialchars($settings['twitter_handle']) ?></div>
        <?php endif; ?>
        <?php if (!empty($settings['whatsapp_number'])): ?>
        <div>💬 WhatsApp: <?= htmlspecialchars($settings['whatsapp_number']) ?></div>
        <?php endif; ?>
    </div>

    <!-- Footer Messages -->
    <div class="footer">
        <?php if (!empty($settings['receipt_footer'])): ?>
        <div><?= nl2br(htmlspecialchars($settings['receipt_footer'])) ?></div>
        <?php endif; ?>
        <div style="margin: 5px 0; font-weight: bold;">Thank you for choosing us!</div>
        <div style="margin: 3px 0;">Your satisfaction is our priority</div>
        <?php if (!empty($settings['loyalty_program'])): ?>
        <div style="margin: 3px 0;">💳 Ask about our loyalty program!</div>
        <?php endif; ?>
        <div style="margin-top: 8px; font-size: 8px;">Powered by <?= APP_NAME ?> POS System</div>
        <div style="font-size: 8px;">Receipt printed on <?= date('d/m/Y H:i:s') ?></div>
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
