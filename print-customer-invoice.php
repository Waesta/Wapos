<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();
$orderId = $_GET['id'] ?? 0;

// Get order details
$order = $db->fetchOne("
    SELECT o.*, rt.table_number, rt.table_name, u.full_name as waiter_name
    FROM orders o
    LEFT JOIN restaurant_tables rt ON o.table_id = rt.id
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
", [$orderId]);

if (!$order) {
    die('Order not found');
}

// Get order items
$items = $db->fetchAll("
    SELECT oi.*, p.sku 
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ? 
    ORDER BY oi.id
", [$orderId]);

$promotionSummary = null;
$loyaltyPayload = null;
$metaRows = $db->fetchAll('SELECT meta_key, meta_value FROM order_meta WHERE order_id = ?', [$orderId]);
foreach ($metaRows as $metaRow) {
    if (!isset($metaRow['meta_key'])) {
        continue;
    }
    $decoded = null;
    if (!empty($metaRow['meta_value'])) {
        $decoded = json_decode($metaRow['meta_value'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = null;
        }
    }

    if ($metaRow['meta_key'] === 'promotion_summary') {
        $promotionSummary = is_array($decoded) ? $decoded : null;
    } elseif ($metaRow['meta_key'] === 'loyalty_payload') {
        $loyaltyPayload = is_array($decoded) ? $decoded : null;
    }
}

$promotionDiscount = isset($promotionSummary['total_discount']) ? (float)$promotionSummary['total_discount'] : 0.0;
$appliedPromotions = isset($promotionSummary['applied']) && is_array($promotionSummary['applied'])
    ? array_filter($promotionSummary['applied'], static fn($entry) => !empty($entry['discount']))
    : [];
$loyaltyDiscount = 0.0;
if ($loyaltyPayload) {
    $loyaltyDiscount = isset($loyaltyPayload['discount_amount']) ? (float)$loyaltyPayload['discount_amount'] : 0.0;
}
$otherDiscount = max(0.0, (float)$order['discount_amount'] - $promotionDiscount - $loyaltyDiscount);

// Get settings from cache
$settings = function_exists('settings_many')
    ? settings_many([
        'business_logo',
        'business_name',
        'business_address',
        'business_phone',
        'business_email',
        'vat_number',
        'invoice_terms'
    ])
    : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Invoice - <?= htmlspecialchars($order['order_number']) ?></title>
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
        .invoice-header {
            text-align: center;
            border: 2px solid #000;
            padding: 8px;
            margin-bottom: 8px;
            background: #f8f9fa;
        }
        .invoice-header h1 {
            margin: 2px 0;
            font-size: 18px;
            font-weight: bold;
        }
        .business-info {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
            font-size: 10px;
        }
        .order-details {
            border: 1px solid #000;
            padding: 5px;
            margin: 8px 0;
            font-size: 11px;
            background: #f0f0f0;
        }
        .items-section {
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
            margin: 3px 0;
            font-size: 10px;
            border-bottom: 1px dotted #ccc;
            padding-bottom: 2px;
        }
        .item-line {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .item-details {
            font-size: 9px;
            color: #666;
            margin-left: 10px;
        }
        .totals-section {
            margin: 8px 0;
            font-size: 11px;
        }
        .promo-summary {
            border: 1px solid #198754;
            padding: 6px;
            margin: 8px 0;
            background: #f1fff6;
        }
        .promo-summary h3 {
            margin: 0 0 4px;
            font-size: 11px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .promo-line {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            margin: 2px 0;
        }
        .total-line {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
            padding: 2px 0;
        }
        .grand-total {
            font-size: 14px;
            font-weight: bold;
            border-top: 2px solid #000;
            padding-top: 5px;
            margin-top: 5px;
            background: #e8f5e8;
        }
        .payment-info {
            border: 2px solid #000;
            padding: 5px;
            margin: 8px 0;
            text-align: center;
            background: #fff3cd;
        }
        .invoice-footer {
            text-align: center;
            border-top: 2px dashed #000;
            padding-top: 8px;
            margin-top: 8px;
            font-size: 9px;
        }
        .due-amount {
            background: #dc3545;
            color: white;
            padding: 5px;
            margin: 5px 0;
            text-align: center;
            font-weight: bold;
            font-size: 16px;
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
    <div class="business-info">
        <?php if (!empty($settings['business_logo'])): ?>
        <img src="<?= htmlspecialchars($settings['business_logo']) ?>" alt="Logo" style="max-width: 40mm; height: auto; margin-bottom: 3px;">
        <?php endif; ?>
        <div style="font-size: 14px; font-weight: bold;"><?= htmlspecialchars($settings['business_name'] ?? APP_NAME) ?></div>
        <?php if (!empty($settings['business_address'])): ?>
        <div><?= nl2br(htmlspecialchars($settings['business_address'])) ?></div>
        <?php endif; ?>
        <div>
            <?php if (!empty($settings['business_phone'])): ?>
            Tel: <?= htmlspecialchars($settings['business_phone']) ?>
            <?php endif; ?>
            <?php if (!empty($settings['business_email'])): ?>
            | Email: <?= htmlspecialchars($settings['business_email']) ?>
            <?php endif; ?>
        </div>
        <?php if (!empty($settings['vat_number'])): ?>
        <div style="font-size: 9px;">VAT No: <?= htmlspecialchars($settings['vat_number']) ?></div>
        <?php endif; ?>
    </div>

    <!-- Invoice Header -->
    <div class="invoice-header">
        <h1>📋 CUSTOMER INVOICE</h1>
        <div style="font-size: 12px; margin-top: 3px;">PRE-PAYMENT BILL</div>
    </div>

    <!-- Order Details -->
    <div class="order-details">
        <div style="text-align: center; font-weight: bold; margin-bottom: 5px;">
            ORDER DETAILS
        </div>
        <div><strong>Invoice #:</strong> INV-<?= htmlspecialchars($order['order_number']) ?></div>
        <div><strong>Order #:</strong> <?= htmlspecialchars($order['order_number']) ?></div>
        <div><strong>Date:</strong> <?= formatDate($order['created_at'], 'd/m/Y') ?></div>
        <div><strong>Time:</strong> <?= formatDate($order['created_at'], 'H:i:s') ?></div>
        
        <?php if ($order['table_number']): ?>
        <div><strong>Table:</strong> <?= htmlspecialchars($order['table_number']) ?></div>
        <?php else: ?>
        <div><strong>Order Type:</strong> <?= ucfirst($order['order_type']) ?></div>
        <?php endif; ?>
        
        <?php if ($order['customer_name']): ?>
        <div><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?></div>
        <?php endif; ?>
        
        <?php if ($order['customer_phone']): ?>
        <div><strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?></div>
        <?php endif; ?>
        
        <div><strong>Served by:</strong> <?= htmlspecialchars($order['waiter_name']) ?></div>
        <div><strong>Status:</strong> <?= ucfirst($order['status']) ?></div>
    </div>

    <!-- Items Section -->
    <div class="items-section">
        <div class="items-header">
            <span>ITEM DESCRIPTION</span>
            <span>AMOUNT</span>
        </div>
        
        <?php foreach ($items as $item): ?>
        <div class="item">
            <div class="item-line">
                <div style="flex: 1;">
                    <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                </div>
                <div><strong><?= formatMoney($item['total_price']) ?></strong></div>
            </div>
            <div class="item-details">
                <?php if ($item['sku']): ?>
                <div>SKU: <?= htmlspecialchars($item['sku']) ?></div>
                <?php endif; ?>
                <div><?= $item['quantity'] ?> x <?= formatMoney($item['unit_price']) ?> each</div>
                
                <?php if ($item['modifiers_data']): ?>
                <?php $modifiers = json_decode($item['modifiers_data'], true); ?>
                <?php if ($modifiers && count($modifiers) > 0): ?>
                <div style="margin-top: 2px;">
                    <strong>Modifications:</strong>
                    <?php foreach ($modifiers as $modifier): ?>
                    <div style="margin-left: 10px;">• <?= htmlspecialchars($modifier['name']) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($item['special_instructions']): ?>
                <div style="margin-top: 2px; font-style: italic;">
                    <strong>Special:</strong> <?= htmlspecialchars($item['special_instructions']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($appliedPromotions)): ?>
    <div class="promo-summary">
        <h3>Promotion Savings</h3>
        <?php foreach ($appliedPromotions as $promo): ?>
            <div class="promo-line">
                <span>
                    <?= htmlspecialchars($promo['promotion_name'] ?? ($promo['details'] ?? 'Promotion')) ?>
                    <?php if (!empty($promo['product_name'])): ?>
                        <small>(<?= htmlspecialchars($promo['product_name']) ?>)</small>
                    <?php endif; ?>
                </span>
                <span>-<?= formatMoney($promo['discount'] ?? 0) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Totals Section -->
    <div class="totals-section">
        <div class="total-line">
            <span>Subtotal:</span>
            <span><?= formatMoney($order['subtotal']) ?></span>
        </div>

        <?php if ($promotionDiscount > 0): ?>
        <div class="total-line">
            <span>Promotion Savings:</span>
            <span>-<?= formatMoney($promotionDiscount) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($loyaltyDiscount > 0): ?>
        <div class="total-line">
            <span>Loyalty Redemption:</span>
            <span>-<?= formatMoney($loyaltyDiscount) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($otherDiscount > 0): ?>
        <div class="total-line">
            <span>Other Discounts:</span>
            <span>-<?= formatMoney($otherDiscount) ?></span>
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
            <span>TOTAL AMOUNT:</span>
            <span><?= formatMoney($order['total_amount']) ?></span>
        </div>
    </div>

    <!-- Payment Information -->
    <div class="payment-info">
        <div style="font-weight: bold; font-size: 12px;">💳 PAYMENT REQUIRED</div>
        <div class="due-amount">
            AMOUNT DUE: <?= formatMoney($order['total_amount']) ?>
        </div>
        <div style="font-size: 10px; margin-top: 3px;">
            Payment Status: <strong><?= ucfirst($order['payment_status']) ?></strong>
        </div>
        <?php if ($order['payment_status'] === 'pending'): ?>
        <div style="font-size: 9px; margin-top: 3px; color: #dc3545;">
            ⚠️ This invoice is for review only. Payment is required to complete the order.
        </div>
        <?php endif; ?>
    </div>

    <!-- Payment Methods -->
    <div style="border: 1px solid #000; padding: 5px; margin: 8px 0; font-size: 10px;">
        <div style="text-align: center; font-weight: bold; margin-bottom: 3px;">ACCEPTED PAYMENT METHODS</div>
        <div style="text-align: center;">
            💵 Cash | 💳 Card | 📱 Mobile Money | 🏦 Bank Transfer
        </div>
    </div>

    <!-- Terms and Conditions -->
    <div style="border: 1px dotted #000; padding: 3px; margin: 8px 0; font-size: 8px;">
        <div style="font-weight: bold; text-align: center; margin-bottom: 2px;">TERMS & CONDITIONS</div>
        <div>• Prices include applicable taxes</div>
        <div>• Payment is due upon receipt of this invoice</div>
        <div>• Orders may be cancelled before payment</div>
        <div>• Food preparation begins after payment confirmation</div>
        <?php if ($order['order_type'] === 'dine-in'): ?>
        <div>• Table will be held for 15 minutes after order placement</div>
        <?php endif; ?>
        <?php if (!empty($settings['invoice_terms'])): ?>
        <div>• <?= htmlspecialchars($settings['invoice_terms']) ?></div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="invoice-footer">
        <div style="font-weight: bold; margin-bottom: 3px;">Thank you for choosing us!</div>
        <div>This is a pre-payment invoice and not a receipt</div>
        <div style="margin-top: 5px; font-size: 8px;">
            Invoice generated on <?= date('d/m/Y H:i:s') ?>
        </div>
        <div style="margin-top: 3px; font-size: 8px;">
            Powered by <?= APP_NAME ?> Restaurant System
        </div>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 5px;">
            🖨️ Print Invoice
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; margin-left: 10px; background: #6c757d; color: white; border: none; border-radius: 5px;">
            ❌ Close
        </button>
        <button onclick="processPayment()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; margin-left: 10px; background: #28a745; color: white; border: none; border-radius: 5px;">
            💳 Process Payment
        </button>
    </div>

    <script>
        // Auto print when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };

        function processPayment() {
            if (confirm('Proceed to payment processing?')) {
                window.location.href = 'restaurant-payment.php?order_id=<?= $order['id'] ?>';
            }
        }
    </script>
</body>
</html>
