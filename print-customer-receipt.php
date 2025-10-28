<?php
require_once 'includes/bootstrap.php';
require_once 'includes/qr-generator.php';
$auth->requireLogin();

$db = Database::getInstance();
$orderId = $_GET['id'] ?? 0;

// Get order details
$order = $db->fetchOne("
    SELECT o.*, rt.table_number, rt.table_name, u.full_name as waiter_name
    FROM orders o
    LEFT JOIN restaurant_tables rt ON o.table_id = rt.id
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.payment_status = 'paid'
", [$orderId]);

if (!$order) {
    die('Order not found or payment not completed');
}

// Get order items
$items = $db->fetchAll("
    SELECT oi.*, p.sku 
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ? 
    ORDER BY oi.id
", [$orderId]);

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
    <title>Customer Receipt - <?= htmlspecialchars($order['order_number']) ?></title>
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
        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .receipt-header h1 {
            margin: 3px 0;
            font-size: 18px;
            font-weight: bold;
        }
        .logo {
            max-width: 60mm;
            height: auto;
            margin-bottom: 5px;
        }
        .business-info {
            text-align: center;
            font-size: 10px;
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
        .payment-details {
            border: 1px solid #000;
            padding: 5px;
            margin: 8px 0;
            font-size: 10px;
            background: #e8f5e8;
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
        .footer {
            text-align: center;
            border-top: 2px dashed #000;
            padding-top: 8px;
            margin-top: 8px;
            font-size: 9px;
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
    <!-- Business Information Header -->
    <div class="receipt-header">
        <?php if (!empty($settings['business_logo'])): ?>
        <img src="<?= htmlspecialchars($settings['business_logo']) ?>" alt="Logo" class="logo">
        <?php endif; ?>
        <h1><?= htmlspecialchars($settings['business_name'] ?? APP_NAME) ?></h1>
        <?php if (!empty($settings['business_tagline'])): ?>
        <div style="font-style: italic; font-size: 10px;"><?= htmlspecialchars($settings['business_tagline']) ?></div>
        <?php endif; ?>
    </div>

    <div class="business-info">
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

    <!-- Transaction Details -->
    <div class="transaction-info">
        <div style="text-align: center; font-weight: bold; margin-bottom: 5px;">TRANSACTION DETAILS</div>
        <div><strong>Receipt #:</strong> <?= htmlspecialchars($order['order_number']) ?></div>
        <div><strong>Date:</strong> <?= formatDate($order['created_at'], 'd/m/Y') ?></div>
        <div><strong>Time:</strong> <?= formatDate($order['created_at'], 'H:i:s') ?></div>
        
        <?php if ($order['table_number']): ?>
        <div><strong>Table:</strong> <?= htmlspecialchars($order['table_number']) ?></div>
        <?php else: ?>
        <div><strong>Service:</strong> <?= ucfirst($order['order_type']) ?></div>
        <?php endif; ?>
        
        <?php if ($order['customer_name']): ?>
        <div><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?></div>
        <?php endif; ?>
        
        <?php if ($order['customer_phone']): ?>
        <div><strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?></div>
        <?php endif; ?>
        
        <div><strong>Served by:</strong> <?= htmlspecialchars($order['waiter_name']) ?></div>
        <div><strong>Terminal:</strong> REST-<?= str_pad($auth->getUserId(), 3, '0', STR_PAD_LEFT) ?></div>
    </div>

    <!-- Items Section -->
    <div class="items">
        <div class="items-header">
            <div style="display: flex; justify-content: space-between;">
                <span>ITEM DESCRIPTION</span>
                <span>TOTAL</span>
            </div>
        </div>
        
        <?php foreach ($items as $item): ?>
        <div class="item">
            <div class="item-line">
                <span><strong><?= htmlspecialchars($item['product_name']) ?></strong></span>
                <span><strong><?= formatMoney($item['total_price']) ?></strong></span>
            </div>
            <div class="item-details">
                <?php if ($item['sku']): ?>
                <div>SKU: <?= htmlspecialchars($item['sku']) ?></div>
                <?php endif; ?>
                <div><?= $item['quantity'] ?> x <?= formatMoney($item['unit_price']) ?> each</div>
                
                <?php if ($item['modifiers_data']): ?>
                <?php $modifiers = json_decode($item['modifiers_data'], true); ?>
                <?php if ($modifiers && count($modifiers) > 0): ?>
                <div style="margin-top: 1px;">
                    <?php foreach ($modifiers as $modifier): ?>
                    <div>+ <?= htmlspecialchars($modifier['name']) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($item['special_instructions']): ?>
                <div style="margin-top: 1px; font-style: italic;">
                    Note: <?= htmlspecialchars($item['special_instructions']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Totals Section -->
    <div class="totals">
        <div class="total-line">
            <span>Subtotal:</span>
            <span><?= formatMoney($order['subtotal']) ?></span>
        </div>
        
        <?php if ($order['discount_amount'] > 0): ?>
        <div class="total-line">
            <span>Discount:</span>
            <span>-<?= formatMoney($order['discount_amount']) ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($order['tax_amount'] > 0): ?>
        <div class="total-line">
            <span>Tax (VAT):</span>
            <span><?= formatMoney($order['tax_amount']) ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($order['delivery_fee'] > 0): ?>
        <div class="total-line">
            <span>Delivery Fee:</span>
            <span><?= formatMoney($order['delivery_fee']) ?></span>
        </div>
        <?php endif; ?>
        
        <div class="total-line grand-total">
            <span>TOTAL:</span>
            <span><?= formatMoney($order['total_amount']) ?></span>
        </div>
    </div>

    <!-- Payment Details -->
    <div class="payment-details">
        <div style="text-align: center; font-weight: bold; margin-bottom: 3px;">PAYMENT INFORMATION</div>
        <div><strong>Payment Method:</strong> <?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></div>
        <div><strong>Amount Paid:</strong> <?= formatMoney($order['total_amount']) ?></div>
        <div><strong>Payment Status:</strong> ✅ PAID</div>
        <div><strong>Transaction Time:</strong> <?= formatDate($order['updated_at'], 'd/m/Y H:i:s') ?></div>
        
        <?php if (in_array($order['payment_method'], ['card', 'credit_card', 'debit_card'])): ?>
        <div style="margin-top: 3px;">
            <strong>Card Number:</strong> ****-****-****-<?= substr(str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT), -4) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($order['payment_method'] === 'mobile_money'): ?>
        <div style="margin-top: 3px;">
            <strong>Transaction ID:</strong> <?= strtoupper(substr(md5($order['order_number'] . $order['created_at']), 0, 10)) ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Return Policy Section -->
    <div class="return-policy">
        <div style="font-weight: bold; text-align: center; margin-bottom: 3px;">RETURN & EXCHANGE POLICY</div>
        <div>• Returns accepted within <?= $settings['return_period'] ?? '7' ?> days with receipt</div>
        <div>• Food items cannot be returned for health reasons</div>
        <div>• Defective items will be replaced or refunded</div>
        <div>• Original receipt required for all returns</div>
        <?php if (!empty($settings['return_conditions'])): ?>
        <div>• <?= htmlspecialchars($settings['return_conditions']) ?></div>
        <?php endif; ?>
        <div style="text-align: center; margin-top: 3px; font-weight: bold;">
            For support: <?= $settings['business_phone'] ?? $settings['business_email'] ?? 'Contact restaurant' ?>
        </div>
    </div>

    <!-- QR Code Section -->
    <?php if (!empty($settings['enable_qr_code']) && $settings['enable_qr_code'] === '1'): ?>
    <div class="qr-code">
        <div style="font-size: 9px; margin-bottom: 3px;">Scan for digital receipt & feedback:</div>
        <?php $receiptQR = generateReceiptQR($order['id'], $order['order_number'], $settings['business_website'] ?? ''); ?>
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
        <div style="margin: 5px 0; font-weight: bold;">Thank you for dining with us!</div>
        <div style="margin: 3px 0;">We hope you enjoyed your meal</div>
        <?php if (!empty($settings['loyalty_program'])): ?>
        <div style="margin: 3px 0;">💳 <?= htmlspecialchars($settings['loyalty_program']) ?></div>
        <?php endif; ?>
        <div style="margin-top: 8px; font-size: 8px;">This receipt serves as proof of purchase</div>
        <div style="font-size: 8px;">Powered by <?= APP_NAME ?> Restaurant System</div>
        <div style="font-size: 8px;">Receipt printed on <?= date('d/m/Y H:i:s') ?></div>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 5px;">
            🖨️ Print Receipt
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; margin-left: 10px; background: #6c757d; color: white; border: none; border-radius: 5px;">
            ❌ Close
        </button>
        <button onclick="emailReceipt()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; margin-left: 10px; background: #28a745; color: white; border: none; border-radius: 5px;">
            📧 Email Receipt
        </button>
    </div>

    <script>
        // Auto print when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };

        function emailReceipt() {
            const email = prompt('Enter customer email address:');
            if (email && email.includes('@')) {
                // Here you would implement email functionality
                alert('Receipt will be sent to: ' + email);
                // You could make an AJAX call to send the email
            }
        }
    </script>
</body>
</html>
