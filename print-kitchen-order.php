<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();
$orderId = $_GET['id'] ?? 0;

// Get order details
$order = $db->fetchOne("
    SELECT o.*, rt.table_number, u.full_name as waiter_name
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
    SELECT * FROM order_items WHERE order_id = ? ORDER BY id
", [$orderId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Order - <?= htmlspecialchars($order['order_number']) ?></title>
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
        .header h1 {
            margin: 5px 0;
            font-size: 24px;
        }
        .order-info {
            font-size: 14px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .order-info div {
            margin: 3px 0;
        }
        .items {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 10px 0;
            margin: 10px 0;
        }
        .item {
            margin: 15px 0;
            page-break-inside: avoid;
        }
        .item-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .item-qty {
            font-size: 20px;
            font-weight: bold;
            margin-right: 10px;
        }
        .modifiers {
            margin-left: 30px;
            margin-top: 5px;
        }
        .modifier {
            font-size: 14px;
            margin: 3px 0;
        }
        .instructions {
            margin-left: 30px;
            margin-top: 5px;
            font-style: italic;
            background: #f0f0f0;
            padding: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #000;
            font-size: 12px;
        }
        .time {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
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
        <h1>KITCHEN ORDER</h1>
    </div>

    <div class="order-info">
        <div>Order #: <?= htmlspecialchars($order['order_number']) ?></div>
        <?php if ($order['table_number']): ?>
        <div>TABLE: <?= htmlspecialchars($order['table_number']) ?></div>
        <?php else: ?>
        <div>TYPE: <?= strtoupper($order['order_type']) ?></div>
        <?php endif; ?>
        <?php if ($order['customer_name']): ?>
        <div>Customer: <?= htmlspecialchars($order['customer_name']) ?></div>
        <?php endif; ?>
        <div class="time">Time: <?= date('H:i') ?></div>
        <div>Waiter: <?= htmlspecialchars($order['waiter_name']) ?></div>
    </div>

    <div class="items">
        <?php foreach ($items as $item): ?>
        <div class="item">
            <div>
                <span class="item-qty">x<?= $item['quantity'] ?></span>
                <span class="item-name"><?= strtoupper(htmlspecialchars($item['product_name'])) ?></span>
            </div>
            
            <?php if ($item['modifiers_data']): ?>
                <?php $modifiers = json_decode($item['modifiers_data'], true); ?>
                <?php if ($modifiers && count($modifiers) > 0): ?>
                <div class="modifiers">
                    <?php foreach ($modifiers as $modifier): ?>
                    <div class="modifier">+ <?= htmlspecialchars($modifier['name']) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($item['special_instructions']): ?>
            <div class="instructions">
                NOTE: <?= htmlspecialchars($item['special_instructions']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($order['notes']): ?>
    <div style="margin: 10px 0; padding: 10px; background: #ffeb3b;">
        <strong>ORDER NOTES:</strong><br>
        <?= nl2br(htmlspecialchars($order['notes'])) ?>
    </div>
    <?php endif; ?>

    <div class="footer">
        <div>Total Items: <?= count($items) ?></div>
        <div style="margin-top: 10px;">━━━━━━━━━━━━━━━━━━━━━━━━━━</div>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer;">
            Print Kitchen Order
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
