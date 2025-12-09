<?php
/**
 * Kitchen Order Ticket (KOT) Printer
 * Generates formal KOT tickets for kitchen display and printing
 */
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();
$orderId = $_GET['id'] ?? 0;
$autoPrint = ($_GET['auto_print'] ?? '1') === '1';
$kotNumber = $_GET['kot_number'] ?? null;

// Get order details
$order = $db->fetchOne("
    SELECT o.*, rt.table_number, rt.table_name, u.full_name as waiter_name,
           c.name as customer_name_from_customer
    FROM orders o
    LEFT JOIN restaurant_tables rt ON o.table_id = rt.id
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.id = ?
", [$orderId]);

// Generate KOT number if not provided
if (!$kotNumber) {
    $kotNumber = 'KOT-' . date('ymd') . '-' . str_pad($orderId, 4, '0', STR_PAD_LEFT);
}

if (!$order) {
    die('Order not found');
}

// Get order items
$items = $db->fetchAll("
    SELECT * FROM order_items WHERE order_id = ? ORDER BY id
", [$orderId]);

function productColumnExists(Database $db, string $column): bool {
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $row = $db->fetchOne(
        "SELECT COUNT(*) AS column_exists
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'products'
           AND COLUMN_NAME = ?",
        [$column]
    );

    $cache[$column] = !empty($row['column_exists']);
    return $cache[$column];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOT <?= htmlspecialchars($kotNumber) ?> - <?= htmlspecialchars($order['order_number']) ?></title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            width: 79.5mm; /* Kitchen printer optimized */
            margin: 0 auto;
            padding: 3mm;
            font-size: 11px;
            line-height: 1.1;
        }
        .header {
            text-align: center;
            border: 3px solid #000;
            padding: 8px;
            margin-bottom: 8px;
            background: #000;
            color: white;
        }
        .header h1 {
            margin: 2px 0;
            font-size: 20px;
            font-weight: bold;
        }
        .priority-high {
            background: #ff0000 !important;
            color: white !important;
            animation: blink 1s infinite;
        }
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }
        .order-info {
            font-size: 12px;
            margin-bottom: 10px;
            font-weight: bold;
            border: 2px solid #000;
            padding: 5px;
            background: #f0f0f0;
        }
        .order-info div {
            margin: 2px 0;
        }
        .urgent-info {
            background: #ffeb3b;
            padding: 3px;
            margin: 2px 0;
            border: 1px solid #000;
            text-align: center;
        }
        .items {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 10px 0;
            margin: 10px 0;
        }
        .item {
            margin: 8px 0;
            page-break-inside: avoid;
            border-bottom: 1px dotted #666;
            padding-bottom: 5px;
        }
        .item-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .item-qty {
            font-size: 16px;
            font-weight: bold;
            margin-right: 8px;
            background: #000;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .allergy-warning {
            background: #ff4444;
            color: white;
            padding: 3px;
            margin: 3px 0;
            font-weight: bold;
            text-align: center;
        }
        .prep-time {
            background: #4CAF50;
            color: white;
            padding: 2px 5px;
            font-size: 10px;
            border-radius: 3px;
            margin-left: 5px;
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
            margin-left: 15px;
            margin-top: 3px;
            font-style: italic;
            background: #ffffcc;
            padding: 3px;
            border-left: 3px solid #ff9800;
            font-weight: bold;
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
            /* Ensure high contrast for kitchen environment */
            * {
                color: #000 !important;
            }
            .header {
                background: #000 !important;
                color: white !important;
            }
        }
    </style>
</head>
<body>
    <?php 
        // Determine priority based on order type and time
        $orderTime = strtotime($order['created_at']);
        $timeDiff = time() - $orderTime;
        $isPriority = $timeDiff > 900 || $order['order_type'] === 'delivery'; // 15+ minutes or delivery
    ?>
    <div class="header <?= $isPriority ? 'priority-high' : '' ?>">
        <div style="font-size: 10px; margin-bottom: 2px;"><?= htmlspecialchars($kotNumber) ?></div>
        <h1><?= $isPriority ? '🔥 URGENT 🔥' : '' ?> KITCHEN ORDER TICKET</h1>
        <?php if ($isPriority): ?>
        <div style="font-size: 12px;">⚠️ PRIORITY ORDER ⚠️</div>
        <?php endif; ?>
    </div>

    <div class="order-info">
        <div style="font-size: 14px; text-align: center; margin-bottom: 5px;">
            <strong>ORDER #: <?= htmlspecialchars($order['order_number']) ?></strong>
        </div>
        <?php if ($order['table_number']): ?>
        <div class="urgent-info">🍽️ TABLE: <?= htmlspecialchars($order['table_number']) ?></div>
        <?php else: ?>
        <div class="urgent-info">📦 <?= strtoupper($order['order_type']) ?></div>
        <?php endif; ?>
        <?php if ($order['customer_name']): ?>
        <div>👤 Customer: <?= htmlspecialchars($order['customer_name']) ?></div>
        <?php endif; ?>
        <div class="time">⏰ Ordered: <?= formatDate($order['created_at'], 'H:i') ?> | Now: <?= date('H:i') ?></div>
        <div>👨‍💼 Waiter: <?= htmlspecialchars($order['waiter_name']) ?></div>
        <?php if ($timeDiff > 0): ?>
        <div style="background: <?= $timeDiff > 900 ? '#ff4444' : '#ffeb3b' ?>; padding: 2px; text-align: center; margin: 2px 0;">
            ⏱️ <?= floor($timeDiff / 60) ?> min <?= $timeDiff % 60 ?> sec ago
        </div>
        <?php endif; ?>
    </div>

    <div class="items">
        <?php 
            // Get product details for preparation time and allergens with schema awareness
            $hasPrepTime = productColumnExists($db, 'prep_time');
            $hasAllergens = productColumnExists($db, 'allergens');
            $hasCategory = productColumnExists($db, 'category_id');

            $selectColumns = ['id'];
            if ($hasPrepTime) {
                $selectColumns[] = 'prep_time';
            }
            if ($hasAllergens) {
                $selectColumns[] = 'allergens';
            }
            if ($hasCategory) {
                $selectColumns[] = 'category_id';
            }

            $columnList = implode(', ', $selectColumns);

            $productDetails = [];
            foreach ($items as $item) {
                $productDetails[$item['id']] = $db->fetchOne("SELECT {$columnList} FROM products WHERE id = ?", [$item['product_id']]) ?: [];
            }
        ?>
        <?php foreach ($items as $item): ?>
        <?php $product = $productDetails[$item['id']] ?? null; ?>
        <div class="item">
            <div>
                <span class="item-qty">x<?= $item['quantity'] ?></span>
                <span class="item-name"><?= strtoupper(htmlspecialchars($item['product_name'])) ?></span>
                <?php if ($hasPrepTime && !empty($product['prep_time'])): ?>
                <span class="prep-time">⏱️ <?= $product['prep_time'] ?>min</span>
                <?php endif; ?>
            </div>
            
            <?php if ($hasAllergens && !empty($product['allergens'])): ?>
            <div class="allergy-warning">
                ⚠️ ALLERGENS: <?= strtoupper(htmlspecialchars($product['allergens'])) ?> ⚠️
            </div>
            <?php endif; ?>
            
            <?php if ($item['modifiers_data']): ?>
                <?php $modifiers = json_decode($item['modifiers_data'], true); ?>
                <?php if ($modifiers && count($modifiers) > 0): ?>
                <div class="modifiers">
                    <?php foreach ($modifiers as $modifier): ?>
                    <div class="modifier">➕ <?= htmlspecialchars($modifier['name']) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($item['special_instructions']): ?>
            <div class="instructions">
                📝 SPECIAL: <?= strtoupper(htmlspecialchars($item['special_instructions'])) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($order['notes']): ?>
    <div style="margin: 8px 0; padding: 5px; background: #ff4444; color: white; border: 2px solid #000;">
        <div style="text-align: center; font-weight: bold; font-size: 12px;">🚨 ORDER NOTES 🚨</div>
        <div style="text-align: center; margin-top: 3px;"><?= strtoupper(nl2br(htmlspecialchars($order['notes']))) ?></div>
    </div>
    <?php endif; ?>
    
    <?php if ($order['delivery_instructions'] && $order['order_type'] === 'delivery'): ?>
    <div style="margin: 8px 0; padding: 5px; background: #2196F3; color: white; border: 2px solid #000;">
        <div style="text-align: center; font-weight: bold; font-size: 12px;">🚚 DELIVERY NOTES 🚚</div>
        <div style="text-align: center; margin-top: 3px;"><?= strtoupper(nl2br(htmlspecialchars($order['delivery_instructions']))) ?></div>
    </div>
    <?php endif; ?>

    <div class="footer">
        <div style="font-size: 14px; font-weight: bold;">📊 SUMMARY</div>
        <div>Total Items: <?= count($items) ?> | Total Qty: <?= array_sum(array_column($items, 'quantity')) ?></div>
        <?php 
            $maxPrepTime = 0;
            foreach ($items as $item) {
                $product = $productDetails[$item['id']] ?? null;
                if ($hasPrepTime && !empty($product['prep_time'])) {
                    $maxPrepTime = max($maxPrepTime, $product['prep_time']);
                }
            }
        ?>
        <?php if ($maxPrepTime > 0): ?>
        <div style="background: #4CAF50; color: white; padding: 3px; margin: 3px 0;">⏱️ Est. Prep Time: <?= $maxPrepTime ?> minutes</div>
        <?php endif; ?>
        <div style="margin-top: 8px; font-size: 10px;">Printed: <?= date('Y-m-d H:i:s') ?></div>
        <div style="margin-top: 5px;">━━━━━━━━━━━━━━━━━━━━━━━━━━</div>
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
        // Auto print when page loads (if enabled)
        <?php if ($autoPrint): ?>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
        <?php endif; ?>
    </script>
</body>
</html>
