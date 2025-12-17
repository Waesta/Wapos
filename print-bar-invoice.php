<?php
/**
 * Print Bar Tab Invoice
 * Generates a formal invoice for bar tabs - suitable for corporate/business customers
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

// Get customer details if linked
$customer = null;
if (!empty($tab['customer_id'])) {
    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$tab['customer_id']]);
}

$customerName = $customer['name'] ?? $tab['tab_name'] ?? 'Guest';
$customerPhone = $customer['phone'] ?? '';
$customerEmail = $customer['email'] ?? '';
$customerAddress = $customer['address'] ?? '';

// Generate invoice number
$invoiceNumber = 'INV-BAR-' . str_pad($tabId, 6, '0', STR_PAD_LEFT);

// Calculate totals
$subtotal = 0;
foreach ($tab['items'] ?? [] as $item) {
    if ($item['status'] !== 'voided') {
        $subtotal += $item['total_price'];
    }
}
$taxAmount = $tab['tax_amount'] ?? 0;
$discountAmount = $tab['discount_amount'] ?? 0;
$tipAmount = $tab['tip_amount'] ?? 0;
$totalAmount = $tab['total_amount'] ?? ($subtotal + $taxAmount - $discountAmount + $tipAmount);

// Payment status
$paidAmount = 0;
foreach ($tab['payments'] ?? [] as $payment) {
    $paidAmount += $payment['amount'];
}
$balanceDue = max(0, $totalAmount - $paidAmount);
$isPaid = $balanceDue <= 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?= htmlspecialchars($invoiceNumber) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            max-width: 210mm;
            margin: 0 auto;
            padding: 15mm;
            background: #fff;
        }
        .invoice-container {
            border: 1px solid #ddd;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .company-info {
            flex: 1;
        }
        .company-info h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
        }
        .company-info p {
            font-size: 11px;
            color: #666;
            margin: 2px 0;
        }
        .invoice-title {
            text-align: right;
        }
        .invoice-title h2 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .invoice-title .invoice-number {
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }
        .invoice-title .invoice-date {
            font-size: 11px;
            color: #666;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 12px;
            margin-top: 10px;
        }
        .status-paid {
            background: #28a745;
            color: white;
        }
        .status-unpaid {
            background: #dc3545;
            color: white;
        }
        .status-partial {
            background: #ffc107;
            color: #333;
        }
        .billing-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .bill-to, .tab-details {
            width: 48%;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 8px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
        }
        .bill-to p, .tab-details p {
            margin: 3px 0;
            font-size: 12px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background: #2c3e50;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        .items-table th:last-child,
        .items-table td:last-child {
            text-align: right;
        }
        .items-table th:nth-child(3),
        .items-table td:nth-child(3) {
            text-align: center;
        }
        .items-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #eee;
            font-size: 11px;
        }
        .items-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        .item-name {
            font-weight: 500;
        }
        .item-portion {
            font-size: 10px;
            color: #666;
        }
        .voided {
            text-decoration: line-through;
            color: #999;
        }
        .totals-section {
            display: flex;
            justify-content: flex-end;
        }
        .totals-table {
            width: 280px;
        }
        .totals-table tr td {
            padding: 5px 10px;
            font-size: 12px;
        }
        .totals-table tr td:last-child {
            text-align: right;
            font-weight: 500;
        }
        .totals-table .subtotal {
            border-top: 1px solid #ddd;
        }
        .totals-table .grand-total {
            background: #2c3e50;
            color: white;
            font-size: 14px;
            font-weight: bold;
        }
        .totals-table .grand-total td {
            padding: 10px;
        }
        .totals-table .balance-due {
            background: #dc3545;
            color: white;
            font-weight: bold;
        }
        .totals-table .fully-paid {
            background: #28a745;
            color: white;
            font-weight: bold;
        }
        .payments-section {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #ddd;
        }
        .payments-section h3 {
            font-size: 12px;
            margin-bottom: 10px;
            color: #333;
        }
        .payment-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 11px;
            border-bottom: 1px dotted #ddd;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .footer p {
            margin: 3px 0;
        }
        .terms {
            margin-top: 20px;
            padding: 10px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            font-size: 10px;
        }
        .terms h4 {
            font-size: 11px;
            margin-bottom: 5px;
        }
        .no-print {
            margin-top: 20px;
            text-align: center;
        }
        .no-print button {
            padding: 10px 25px;
            font-size: 14px;
            cursor: pointer;
            margin: 0 5px;
            border: none;
            border-radius: 4px;
        }
        .btn-print {
            background: #007bff;
            color: white;
        }
        .btn-close {
            background: #6c757d;
            color: white;
        }
        @media print {
            body {
                padding: 0;
                margin: 0;
            }
            .invoice-container {
                border: none;
            }
            .no-print {
                display: none;
            }
            @page {
                margin: 10mm;
                size: A4;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <div class="company-info">
                <?php if (!empty($settings['business_logo'])): ?>
                    <img src="<?= htmlspecialchars($settings['business_logo']) ?>" alt="Logo" style="max-height: 60px; margin-bottom: 10px;">
                <?php endif; ?>
                <h1><?= htmlspecialchars($settings['business_name'] ?? 'Business Name') ?></h1>
                <?php if (!empty($settings['business_address'])): ?>
                    <p><?= nl2br(htmlspecialchars($settings['business_address'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($settings['business_phone'])): ?>
                    <p>Tel: <?= htmlspecialchars($settings['business_phone']) ?></p>
                <?php endif; ?>
                <?php if (!empty($settings['business_email'])): ?>
                    <p>Email: <?= htmlspecialchars($settings['business_email']) ?></p>
                <?php endif; ?>
                <?php if (!empty($settings['vat_number'])): ?>
                    <p>VAT/PIN: <?= htmlspecialchars($settings['vat_number']) ?></p>
                <?php endif; ?>
            </div>
            <div class="invoice-title">
                <h2>INVOICE</h2>
                <div class="invoice-number"><?= htmlspecialchars($invoiceNumber) ?></div>
                <div class="invoice-date">Date: <?= date('d M Y') ?></div>
                <div class="invoice-date">Tab #: <?= htmlspecialchars($tab['tab_number']) ?></div>
                <?php if ($isPaid): ?>
                    <span class="status-badge status-paid">✓ PAID</span>
                <?php elseif ($paidAmount > 0): ?>
                    <span class="status-badge status-partial">PARTIAL</span>
                <?php else: ?>
                    <span class="status-badge status-unpaid">UNPAID</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="billing-section">
            <div class="bill-to">
                <div class="section-title">Bill To</div>
                <p><strong><?= htmlspecialchars($customerName) ?></strong></p>
                <?php if ($customerPhone): ?>
                    <p>Phone: <?= htmlspecialchars($customerPhone) ?></p>
                <?php endif; ?>
                <?php if ($customerEmail): ?>
                    <p>Email: <?= htmlspecialchars($customerEmail) ?></p>
                <?php endif; ?>
                <?php if ($customerAddress): ?>
                    <p><?= nl2br(htmlspecialchars($customerAddress)) ?></p>
                <?php endif; ?>
            </div>
            <div class="tab-details">
                <div class="section-title">Service Details</div>
                <p><strong>Server:</strong> <?= htmlspecialchars($serverName) ?></p>
                <?php if (!empty($tab['bar_station'])): ?>
                    <p><strong>Station:</strong> <?= htmlspecialchars($tab['bar_station']) ?></p>
                <?php endif; ?>
                <p><strong>Opened:</strong> <?= date('d M Y, H:i', strtotime($tab['created_at'])) ?></p>
                <?php if ($tab['status'] === 'closed' && !empty($tab['closed_at'])): ?>
                    <p><strong>Closed:</strong> <?= date('d M Y, H:i', strtotime($tab['closed_at'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($tab['guest_count'])): ?>
                    <p><strong>Guests:</strong> <?= (int)$tab['guest_count'] ?></p>
                <?php endif; ?>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:10%">#</th>
                    <th style="width:45%">Description</th>
                    <th style="width:10%">Qty</th>
                    <th style="width:15%">Unit Price</th>
                    <th style="width:20%">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php $itemNum = 0; ?>
                <?php foreach ($tab['items'] ?? [] as $item): ?>
                    <?php $itemNum++; ?>
                    <tr class="<?= $item['status'] === 'voided' ? 'voided' : '' ?>">
                        <td><?= $itemNum ?></td>
                        <td>
                            <span class="item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                            <?php if (!empty($item['portion_name'])): ?>
                                <br><span class="item-portion">(<?= htmlspecialchars($item['portion_name']) ?>)</span>
                            <?php endif; ?>
                            <?php if (!empty($item['special_instructions'])): ?>
                                <br><span class="item-portion">Note: <?= htmlspecialchars($item['special_instructions']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= $item['quantity'] ?></td>
                        <td><?= $currencySymbol ?> <?= number_format($item['unit_price'], 2) ?></td>
                        <td><?= $currencySymbol ?> <?= number_format($item['total_price'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($tab['items'])): ?>
                    <tr>
                        <td colspan="5" style="text-align:center;color:#999;padding:20px;">No items</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totals-section">
            <table class="totals-table">
                <tr class="subtotal">
                    <td>Subtotal:</td>
                    <td><?= $currencySymbol ?> <?= number_format($subtotal, 2) ?></td>
                </tr>
                <?php if ($taxAmount > 0): ?>
                <tr>
                    <td>Tax:</td>
                    <td><?= $currencySymbol ?> <?= number_format($taxAmount, 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($discountAmount > 0): ?>
                <tr>
                    <td>Discount:</td>
                    <td>-<?= $currencySymbol ?> <?= number_format($discountAmount, 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($tipAmount > 0): ?>
                <tr>
                    <td>Service/Tip:</td>
                    <td><?= $currencySymbol ?> <?= number_format($tipAmount, 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="grand-total">
                    <td>TOTAL:</td>
                    <td><?= $currencySymbol ?> <?= number_format($totalAmount, 2) ?></td>
                </tr>
                <?php if ($paidAmount > 0): ?>
                <tr>
                    <td>Paid:</td>
                    <td><?= $currencySymbol ?> <?= number_format($paidAmount, 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($balanceDue > 0): ?>
                <tr class="balance-due">
                    <td>BALANCE DUE:</td>
                    <td><?= $currencySymbol ?> <?= number_format($balanceDue, 2) ?></td>
                </tr>
                <?php else: ?>
                <tr class="fully-paid">
                    <td colspan="2" style="text-align:center;">✓ FULLY PAID</td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <?php if (!empty($tab['payments']) && count($tab['payments']) > 0): ?>
        <div class="payments-section">
            <h3>Payment History</h3>
            <?php foreach ($tab['payments'] as $payment): ?>
            <div class="payment-row">
                <span><?= ucfirst($payment['payment_method']) ?> - <?= date('d M Y H:i', strtotime($payment['created_at'])) ?></span>
                <span><?= $currencySymbol ?> <?= number_format($payment['amount'], 2) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($settings['invoice_terms'])): ?>
        <div class="terms">
            <h4>Terms & Conditions</h4>
            <p><?= nl2br(htmlspecialchars($settings['invoice_terms'])) ?></p>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p><strong>Thank you for your business!</strong></p>
            <?php if (!empty($settings['receipt_footer'])): ?>
                <p><?= nl2br(htmlspecialchars($settings['receipt_footer'])) ?></p>
            <?php endif; ?>
            <p style="margin-top:10px;font-size:9px;color:#999;">
                Generated: <?= date('d M Y H:i:s') ?> | Tab: <?= htmlspecialchars($tab['tab_number']) ?>
            </p>
        </div>
    </div>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Print Invoice</button>
        <button class="btn-close" onclick="window.close()">✕ Close</button>
    </div>
</body>
</html>
