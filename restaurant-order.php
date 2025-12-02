<?php
require_once 'includes/bootstrap.php';
require_once __DIR__ . '/includes/promotion-spotlight.php';

use App\Services\PromotionService;

$auth->requireLogin();

$db = Database::getInstance();

$pdo = $db->getConnection();
$activePromotions = loadActivePromotions($pdo);
$canManagePromotions = $auth->hasRole(['admin','manager']);

$promotionService = new PromotionService($pdo);
try {
    $restaurantAppliedPromotions = $promotionService->getActivePromotionsForModule('restaurant');
} catch (Throwable $e) {
    $restaurantAppliedPromotions = [];
}

// Get order type and table
$orderType = $_GET['type'] ?? 'dine-in';
$tableId = isset($_GET['table_id']) ? (int)$_GET['table_id'] : null;
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Get products and modifiers
$products = $db->fetchAll("SELECT * FROM products WHERE is_active = 1 ORDER BY name");
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");

// Fetch checked-in rooms for room charge payments
$checkedInRooms = [];
try {
    $tablesStmt = $pdo->query("SHOW TABLES LIKE 'room_bookings'");
    if ($tablesStmt && $tablesStmt->fetchColumn()) {
        $roomsStmt = $pdo->prepare(
            "SELECT b.id, b.booking_number, b.guest_name, r.room_number
             FROM room_bookings b
             JOIN rooms r ON b.room_id = r.id
             WHERE b.status = 'checked_in'
             ORDER BY r.room_number ASC, b.booking_number ASC"
        );
        $roomsStmt->execute();
        $rows = $roomsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $guestName = trim((string)($row['guest_name'] ?? ''));
            $roomNumber = trim((string)($row['room_number'] ?? ''));
            $bookingNumber = trim((string)($row['booking_number'] ?? ''));

            $labelParts = [];
            if ($roomNumber !== '') {
                $labelParts[] = 'Room ' . $roomNumber;
            }

            if ($guestName !== '') {
                $labelParts[] = $guestName;
            }

            $label = implode(' · ', $labelParts);
            if ($bookingNumber !== '') {
                $label .= ($label !== '' ? ' ' : '') . '(' . $bookingNumber . ')';
            }

            if ($label === '') {
                $label = 'Booking #' . (int)$row['id'];
            }

            $checkedInRooms[] = [
                'id' => (int)$row['id'],
                'label' => $label,
            ];
        }
    }
} catch (Throwable $e) {
    $checkedInRooms = [];
}
$modifiers = $db->fetchAll("SELECT * FROM modifiers WHERE is_active = 1 ORDER BY category, name");

$deliveryConfigKeys = [
    'google_maps_api_key',
    'business_latitude',
    'business_longitude',
    'delivery_cache_ttl_minutes',
    'delivery_cache_soft_ttl_minutes',
    'delivery_distance_fallback_provider'
];

$placeholders = implode(',', array_fill(0, count($deliveryConfigKeys), '?'));
$deliveryConfigRows = $db->fetchAll(
    "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)",
    $deliveryConfigKeys
);

$deliveryConfig = [];
foreach ($deliveryConfigRows as $row) {
    $deliveryConfig[$row['setting_key']] = $row['setting_value'];
}

$googleMapsApiKey = $deliveryConfig['google_maps_api_key'] ?? '';
$businessLat = isset($deliveryConfig['business_latitude']) ? (float)$deliveryConfig['business_latitude'] : null;
$businessLng = isset($deliveryConfig['business_longitude']) ? (float)$deliveryConfig['business_longitude'] : null;

// Get table info if dine-in
$tableInfo = null;
if ($tableId) {
    $tableInfo = $db->fetchOne("SELECT * FROM restaurant_tables WHERE id = ?", [$tableId]);
}

// Get existing order if editing
$existingOrder = null;
$existingItems = [];
if ($orderId) {
    $existingOrder = $db->fetchOne("SELECT o.*, rt.table_number, rt.table_name, u.full_name AS waiter_name
        FROM orders o
        LEFT JOIN restaurant_tables rt ON o.table_id = rt.id
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?", [$orderId]);

    $existingItems = $db->fetchAll("SELECT oi.*, p.name AS catalog_name
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id", [$orderId]);
}

$existingPromotionSummary = null;
$existingLoyaltyPayload = null;
if ($orderId) {
    $metaRows = $db->fetchAll("SELECT meta_key, meta_value FROM order_meta WHERE order_id = ?", [$orderId]);
    foreach ($metaRows as $metaRow) {
        $value = null;
        if (!empty($metaRow['meta_value'])) {
            $decoded = json_decode($metaRow['meta_value'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            }
        }

        if ($metaRow['meta_key'] === 'promotion_summary') {
            $existingPromotionSummary = $value;
        } elseif ($metaRow['meta_key'] === 'loyalty_payload') {
            $existingLoyaltyPayload = $value;
        }
    }
}

$existingOrderMeta = null;
$existingItemsPayload = [];

if ($existingOrder) {
    $existingOrderMeta = [
        'id' => (int)$existingOrder['id'],
        'order_number' => (string)$existingOrder['order_number'],
        'status' => (string)$existingOrder['status'],
        'payment_status' => (string)$existingOrder['payment_status'],
        'payment_method' => $existingOrder['payment_method'] ?? null,
        'table_number' => $existingOrder['table_number'] ?? null,
        'table_name' => $existingOrder['table_name'] ?? null,
        'waiter_name' => $existingOrder['waiter_name'] ?? null,
        'customer_name' => $existingOrder['customer_name'] ?? null,
        'created_at' => $existingOrder['created_at'] ?? null,
        'total_amount' => (float)$existingOrder['total_amount'],
        'promotion_summary' => $existingPromotionSummary,
        'loyalty_payload' => $existingLoyaltyPayload,
    ];
}

if (!empty($existingItems)) {
    foreach ($existingItems as $item) {
        $modifiers = [];
        if (!empty($item['modifiers_data'])) {
            $decoded = json_decode($item['modifiers_data'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $mod) {
                    $modifiers[] = [
                        'name' => $mod['name'] ?? '',
                        'price' => isset($mod['price']) ? (float)$mod['price'] : 0.0,
                    ];
                }
            }
        }

        $modifierTotal = array_reduce($modifiers, fn($carry, $mod) => $carry + (float)$mod['price'], 0.0);
        $unitPrice = (float)($item['unit_price'] ?? 0);
        $quantity = (float)($item['quantity'] ?? 1);
        $totalPrice = $item['total_price'] !== null ? (float)$item['total_price'] : $unitPrice * $quantity;
        $basePrice = max(0, $unitPrice - $modifierTotal);
        $itemName = $item['product_name'] ?? $item['catalog_name'] ?? ('Item #' . (int)$item['product_id']);

        $existingItemsPayload[] = [
            'id' => (int)$item['product_id'],
            'name' => $itemName,
            'quantity' => $quantity,
            'base_price' => $basePrice,
            'unit_price' => $unitPrice,
            'total' => $totalPrice,
            'modifiers' => $modifiers,
            'instructions' => $item['special_instructions'] ?? '',
        ];
    }
}

$currencySetting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'currency'");
$currencySymbol = $currencySetting['setting_value'] ?? '$';

$pageTitle = $orderType === 'dine-in' ? 'Dine-In Order' : 'Takeout Order';
include 'includes/header.php';
?>

<script>
window.EXISTING_ORDER_META = <?= json_encode($existingOrderMeta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.EXISTING_ORDER_ITEMS = <?= json_encode($existingItemsPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.ACTIVE_RESTAURANT_PROMOTIONS = <?= json_encode($restaurantAppliedPromotions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.EXISTING_PROMOTION_SUMMARY = <?= json_encode($existingPromotionSummary, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.EXISTING_LOYALTY_PAYLOAD = <?= json_encode($existingLoyaltyPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<style>
    .order-shell {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-lg);
        min-height: calc(100vh - 120px);
    }
    .order-grid {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-lg);
    }
    @media (min-width: 992px) {
        .order-grid {
            display: grid;
            grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
            gap: var(--spacing-lg);
            align-items: start;
        }
    }
    .order-products .product-grid {
        display: grid;
        gap: var(--spacing-md);
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        padding-bottom: var(--spacing-md);
    }
    .product-item {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background-color: var(--color-surface);
        box-shadow: var(--shadow-sm);
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
        transition: transform var(--transition-base), box-shadow var(--transition-base);
    }
    .product-item:hover,
    .product-item:focus-within {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    .product-item h6 {
        font-size: var(--text-md);
        font-weight: 600;
    }
    .product-item .product-price {
        font-size: var(--text-lg);
        font-weight: 700;
        color: var(--color-primary-600);
    }
    .product-item .stock-pill {
        font-size: var(--text-sm);
        color: var(--color-text-muted);
    }
    .product-item .add-to-cart-btn {
        width: 100%;
    }
    .order-cart-card {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: var(--spacing-lg);
    }
    @media (min-width: 992px) {
        .order-cart-card {
            position: sticky;
            top: var(--spacing-lg);
        }
    }
    .cart-items {
        max-height: 340px;
        overflow-y: auto;
        border: 1px dashed var(--color-border-subtle);
        border-radius: var(--radius-md);
        padding: var(--spacing-sm);
        background: var(--color-surface-subtle);
    }
    .cart-items .list-group {
        gap: var(--spacing-sm);
        display: flex;
        flex-direction: column;
    }
    .cart-items .list-group-item {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-sm);
        box-shadow: none;
    }
    .cart-empty {
        text-align: center;
        color: var(--color-text-muted);
        padding: var(--spacing-xl) var(--spacing-md);
    }
    .cart-totals {
        border-top: 1px solid var(--color-border);
        padding-top: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    .cart-total-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: var(--text-md);
    }
    .cart-total-row strong {
        font-size: var(--text-lg);
    }
    .delivery-map-wrapper {
        position: relative;
    }
    .delivery-map-container {
        position: relative;
        height: 260px;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        overflow: hidden;
        background: var(--color-surface-subtle);
    }
    #deliveryMap {
        height: 100%;
        width: 100%;
    }
    .map-search-box {
        position: absolute;
        top: 12px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 5;
        width: calc(100% - 32px);
        max-width: 360px;
        box-shadow: var(--shadow-md);
    }
    .order-actions {
        display: grid;
        gap: var(--spacing-sm);
    }
    .order-actions .order-action {
        width: 100%;
    }
    .order-actions .order-action--full {
        grid-column: 1 / -1;
    }
    .order-actions-split {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: var(--spacing-sm);
    }
    @media (min-width: 576px) {
        .order-actions {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
        .order-actions .order-action--full {
            grid-column: 1 / -1;
        }
    }
</style>

<div class="order-shell container-fluid py-4">
    <div class="order-header d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div class="stack-sm">
            <h1 class="mb-0"><i class="bi bi-<?= $orderType === 'dine-in' ? 'table' : 'bag-check' ?> me-2"></i><?= ucfirst($orderType) ?> Order</h1>
            <?php if ($tableInfo): ?>
                <p class="text-muted mb-0">Table <?= htmlspecialchars($tableInfo['table_number']) ?> · Seats <?= (int)($tableInfo['capacity'] ?? 0) ?></p>
            <?php else: ?>
                <p class="text-muted mb-0">Build the order and send to the kitchen in one place.</p>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="restaurant.php" class="btn btn-outline-secondary btn-icon">
                <i class="bi bi-arrow-left me-2"></i>Back to Floor
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12">
            <?php
            renderPromotionSpotlight(
                $activePromotions,
                [
                    'title' => 'Restaurant Promotions',
                    'description' => 'Live offers on combos, happy-hour bundles, and chef specials.',
                    'icon' => 'bi-egg-fried',
                    'context' => 'restaurant',
                    'max_items' => 4,
                    'show_manage_link' => $canManagePromotions,
                ]
            );
            ?>
        </div>
    </div>

    <div class="order-grid">
        <section class="order-products app-card h-100">
            <div class="section-heading">
                <div>
                    <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>Menu Browser</h6>
                    <span class="text-muted small">Tap to add dishes. Filters refine search instantly.</span>
                </div>
            </div>

            <div class="row g-2 align-items-center mb-3">
                <div class="col-md-8">
                    <label for="searchProduct" class="form-label mb-1">Search Menu</label>
                    <input type="text" id="searchProduct" class="form-control" placeholder="Search menu items...">
                </div>
                <div class="col-md-4">
                    <label for="filterCategory" class="form-label mb-1">Category</label>
                    <select id="filterCategory" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="productsList" class="product-grid">
                <?php foreach ($products as $product): ?>
                    <?php
                    $productPayload = json_encode([
                        'id' => (int)$product['id'],
                        'name' => $product['name'],
                        'selling_price' => (float)$product['selling_price'],
                        'stock_quantity' => (float)$product['stock_quantity'],
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                    ?>
                    <article class="product-item" data-category="<?= $product['category_id'] ?>" data-name="<?= strtolower($product['name']) ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="stack-xs">
                                <h6 class="mb-0"><?= htmlspecialchars($product['name']) ?></h6>
                                <span class="stock-pill"><i class="bi bi-archive me-1"></i>Stock <?= (float)$product['stock_quantity'] ?></span>
                            </div>
                            <span class="product-price"><?= formatMoney($product['selling_price'], false) ?></span>
                        </div>
                        <button
                            type="button"
                            class="btn btn-primary btn-icon add-to-cart-btn"
                            data-product-id="<?= (int)$product['id'] ?>"
                            data-product-name="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-product-price="<?= (float)$product['selling_price'] ?>"
                            data-product-stock="<?= (float)$product['stock_quantity'] ?>"
                            data-product-json="<?= htmlspecialchars($productPayload, ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <i class="bi bi-cart-plus me-2"></i>Add to Order
                        </button>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="order-cart">
            <div class="app-card order-cart-card">
                <div class="section-heading">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-receipt me-2"></i>Order Summary</h6>
                        <span class="text-muted small">Review lines, capture customer details, and take payment.</span>
                    </div>
                </div>

                <?php if ($existingOrderMeta): ?>
                    <div class="alert alert-info d-flex flex-column gap-1">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div>
                                <strong>Order <?= htmlspecialchars($existingOrderMeta['order_number']) ?></strong>
                                <div class="small text-muted">
                                    Status: <?= ucfirst(htmlspecialchars($existingOrderMeta['status'])) ?> · Payment: <?= ucfirst(htmlspecialchars($existingOrderMeta['payment_status'])) ?>
                                </div>
                                <?php if (!empty($existingOrderMeta['waiter_name'])): ?>
                                    <div class="small text-muted">Waiter: <?= htmlspecialchars($existingOrderMeta['waiter_name']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($existingOrderMeta['table_number'])): ?>
                                    <div class="small text-muted">Table: <?= htmlspecialchars($existingOrderMeta['table_number']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <div class="fw-semibold">Total <?= formatMoney($existingOrderMeta['total_amount']) ?></div>
                                <div class="small text-muted"><?= $existingOrderMeta['created_at'] ? formatDate($existingOrderMeta['created_at'], 'd M Y H:i') : '' ?></div>
                            </div>
                        </div>
                        <?php if ($existingOrderMeta['payment_status'] !== 'paid'): ?>
                            <div>
                                <a href="restaurant-payment.php?order_id=<?= (int)$existingOrderMeta['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-credit-card"></i> Open Payment Screen
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($orderType !== 'dine-in'): ?>
                    <div class="stack-sm">
                        <h6 class="text-uppercase text-muted fw-semibold small mb-2">Customer Details</h6>
                        <?php if ($orderType === 'takeout'): ?>
                            <div class="alert alert-info small mb-2">
                                <div class="fw-semibold mb-1">On-premise pickup?</div>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="form-check form-switch m-0">
                                        <?php $existingDelivery = !empty($existingOrderMeta['delivery_address']); ?>
                                        <input class="form-check-input" type="checkbox" role="switch" id="requireDeliveryDetails" <?= $existingDelivery ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="requireDeliveryDetails">Require delivery drop-off details</label>
                                    </div>
                                </div>
                                <div class="text-muted mt-1">Leave off for guests already at the counter. Turn on when dispatching to a customer off-site.</div>
                            </div>
                        <?php endif; ?>
                        <input type="text" id="customerName" class="form-control" placeholder="Customer name" autocomplete="off">
                        <input type="tel" id="customerPhone" class="form-control" placeholder="Customer phone" autocomplete="off">
                        <div id="deliveryDetailFields">
                            <textarea id="deliveryAddress" class="form-control" rows="2" placeholder="Delivery address"></textarea>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" step="0.000001" id="deliveryLatitude" class="form-control" placeholder="Latitude">
                                </div>
                                <div class="col-6">
                                    <input type="number" step="0.000001" id="deliveryLongitude" class="form-control" placeholder="Longitude">
                                </div>
                            </div>
                            <div class="form-text">Coordinates help auto-calculate delivery fees. Use decimal degrees.</div>
                            <?php if (!empty($googleMapsApiKey)): ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="toggleDeliveryMap()">
                                        <i class="bi bi-geo-alt"></i> Pick on Map
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="useCurrentLocation()">
                                        <i class="bi bi-crosshair"></i> Use My Location
                                    </button>
                                </div>
                                <div id="deliveryMapWrapper" class="delivery-map-wrapper mt-2" style="display:none;">
                                    <input id="deliveryMapSearch" type="text" class="form-control map-search-box" placeholder="Search address or place">
                                    <div class="delivery-map-container">
                                        <div id="deliveryMap"></div>
                                    </div>
                                    <small class="text-muted d-block mt-2">Tap the map to refine the drop-off.</small>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning small mb-0">
                                    Configure a Google Maps API key in Settings to enable map-based location selection.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="text-danger small d-none" id="customerIdentityHelper">Name and phone are required for takeout & delivery orders.</div>
                        <div class="text-danger small d-none" id="deliveryDetailsHelper">Delivery address is required when serving off-site guests.</div>
                    </div>
                <?php endif; ?>

                <div class="stack-sm">
                    <h6 class="text-uppercase text-muted fw-semibold small mb-2">Cart Items</h6>
                    <div class="cart-items" id="cartItems">
                        <div class="cart-empty">
                            <i class="bi bi-cart-x fs-1"></i>
                            <p class="mt-2 mb-0">Cart is empty</p>
                            <small>Add items from the menu to get started.</small>
                        </div>
                    </div>
                </div>

                <div class="cart-totals">
                    <div class="cart-total-row">
                        <span>Subtotal</span>
                        <span id="subtotal"><?= formatMoney(0, false) ?></span>
                    </div>
                    <div class="cart-total-row text-success d-none" id="promotionDiscountRow">
                        <span>Promotion Savings</span>
                        <span id="promotionDiscountValue">-<?= formatMoney(0, false) ?></span>
                    </div>
                    <div class="cart-total-row">
                        <span>Tax (16%)</span>
                        <span id="taxAmount"><?= formatMoney(0, false) ?></span>
                    </div>
                    <?php if ($orderType !== 'dine-in'): ?>
                        <div class="cart-total-row">
                            <span>Delivery Fee</span>
                            <div class="d-flex align-items-center gap-2">
                                <span id="deliveryFeeDisplay">0.00</span>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleDeliveryFeeEdit()" id="editDeliveryFeeBtn">Edit</button>
                            </div>
                        </div>
                        <div id="deliveryFeeEdit" class="stack-xs" style="display: none;">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Fee</span>
                                <input type="number" step="0.01" class="form-control" id="deliveryFeeInput" placeholder="0.00">
                                <button class="btn btn-outline-primary" type="button" onclick="applyManualDeliveryFee()">Apply</button>
                            </div>
                            <div class="form-text">Leave blank to use the calculated fee.</div>
                        </div>
                        <div class="stack-xs">
                            <button type="button" class="btn btn-outline-primary btn-sm align-self-start" onclick="calculateDeliveryFee()" id="recalculateDeliveryBtn">Calculate Delivery Fee</button>
                            <div class="small text-muted" id="deliveryFeeMeta"></div>
                        </div>
                    <?php endif; ?>
                    <div class="cart-total-row">
                        <span>Order Total</span>
                        <span id="total"><?= formatMoney(0, false) ?></span>
                    </div>
                    <div class="cart-total-row d-none" id="tipRow">
                        <span>Tip / Gratuity</span>
                        <span id="tipAmountDisplay">0.00</span>
                    </div>
                    <div class="cart-total-row grand-total-row">
                        <strong>Total Due (incl. tip)</strong>
                        <strong class="text-primary" id="grandTotalAmount"><?= formatMoney(0, false) ?></strong>
                    </div>
                </div>

                <div class="stack-sm">
                    <label for="paymentMethod" class="form-label mb-1">Payment Method</label>
                    <select id="paymentMethod" class="form-select">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="room_charge">Charge to Room</option>
                    </select>
                </div>

                <div class="stack-sm">
                    <label for="tipAmountInput" class="form-label mb-1">Tip / Gratuity (optional)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-gift"></i></span>
                        <input type="number" class="form-control" id="tipAmountInput" min="0" step="0.01" placeholder="0.00" autocomplete="off">
                        <button type="button" class="btn btn-outline-secondary" onclick="clearTipAmount()">Clear</button>
                    </div>
                    <div class="form-text">Record gratuities for payout audits. Leave blank if none.</div>
                </div>

                <div id="cashPaymentSection" class="stack-sm" style="display: none;">
                    <label for="cashAmountReceived" class="form-label mb-1">Amount Received</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-cash-stack"></i></span>
                        <input type="number"
                               class="form-control"
                               id="cashAmountReceived"
                               placeholder="0.00"
                               step="0.01"
                               min="0"
                               autocomplete="off">
                        <button type="button" class="btn btn-outline-secondary" id="cashExactTenderBtn" onclick="fillExactTender()">
                            Exact Total
                        </button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="text-muted small">Change Due</span>
                        <strong id="cashChangeDisplay">0.00</strong>
                    </div>
                    <div class="text-danger small d-none" id="cashTenderHelper">Amount received must be at least the total due.</div>
                </div>

                <div id="roomChargeSection" class="stack-sm" style="display: none;">
                    <?php if (!empty($checkedInRooms)): ?>
                        <label for="roomBookingSelect" class="form-label">Select Checked-in Room</label>
                        <select id="roomBookingSelect" class="form-select">
                            <option value="">Select a checked-in room...</option>
                            <?php foreach ($checkedInRooms as $room): ?>
                                <option value="<?= (int)$room['id'] ?>" data-label="<?= htmlspecialchars($room['label'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($room['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="roomChargeDescription" class="form-label">Folio Note (optional)</label>
                        <input type="text" id="roomChargeDescription" class="form-control" maxlength="120" placeholder="e.g. Dinner at restaurant">
                        <small class="text-muted">Charges will be posted to the guest folio.</small>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0">
                            <div class="small mb-1"><i class="bi bi-exclamation-triangle me-1"></i>No checked-in rooms available.</div>
                            <div class="small">Check in a guest before charging orders to a room.</div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="order-actions">
                    <button class="btn btn-info order-action order-action--full" onclick="printInvoice()" id="invoiceBtn" disabled>
                        <i class="bi bi-receipt me-2"></i>Print Invoice
                    </button>
                    <button class="btn btn-primary btn-lg order-action order-action--full" onclick="processPayment()" id="paymentBtn" disabled>
                        <i class="bi bi-credit-card me-2"></i>Process Payment
                    </button>
                    <div class="order-actions-split order-action order-action--full" role="group" aria-label="Print options">
                        <button class="btn btn-outline-success" onclick="printKitchenOrder()" id="kitchenBtn" disabled>
                            <i class="bi bi-printer me-1"></i>Kitchen
                        </button>
                        <button class="btn btn-outline-info" onclick="printCustomerReceipt()" id="receiptBtn" disabled>
                            <i class="bi bi-receipt-cutoff me-1"></i>Receipt
                        </button>
                    </div>
                    <button class="btn btn-outline-danger order-action order-action--full" onclick="clearCart()">
                        <i class="bi bi-trash me-2"></i>Clear Cart
                    </button>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Modifier Modal -->
<div class="modal fade" id="modifierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Customize Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 id="itemName"></h6>
                <p class="text-muted" id="itemPrice"></p>
                
                <h6 class="mt-3">Add Modifiers:</h6>
                <div id="modifiersList">
                    <?php 
                    $modsByCategory = [];
                    foreach ($modifiers as $mod) {
                        $modsByCategory[$mod['category']][] = $mod;
                    }
                    ?>
                    <?php foreach ($modsByCategory as $category => $mods): ?>
                        <h6 class="mt-3 mb-2"><?= htmlspecialchars($category) ?></h6>
                        <?php foreach ($mods as $mod): ?>
                        <div class="form-check">
                            <input class="form-check-input modifier-check" type="checkbox" 
                                   data-id="<?= $mod['id'] ?>"
                                   data-name="<?= htmlspecialchars($mod['name']) ?>"
                                   data-price="<?= $mod['price'] ?>"
                                   id="mod_<?= $mod['id'] ?>">
                            <label class="form-check-label" for="mod_<?= $mod['id'] ?>">
                                <?= htmlspecialchars($mod['name']) ?>
                                <?php if ($mod['price'] > 0): ?>
                                    (+<?= formatMoney($mod['price'], false) ?>)
                                <?php endif; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
                
                <h6 class="mt-3">Special Instructions:</h6>
                <textarea class="form-control" id="specialInstructions" rows="2" placeholder="e.g., No onions, extra spicy..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmModifiers()">Add to Order</button>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];
let currentItem = null;
let orderFinancials = {
    grossSubtotal: 0,
    promotionDiscount: 0,
    netSubtotal: 0,
    subtotal: 0,
    tax: 0,
    deliveryFee: 0,
    total: 0,
    tip: 0,
    grandTotal: 0,
};
const TAX_RATE = 16;
const currencySymbol = <?= json_encode($currencySymbol) ?>;
const orderType = '<?= $orderType ?>';
const googleMapsApiKey = <?= json_encode($googleMapsApiKey) ?>;
const businessCoordinates = {
    lat: <?= $businessLat !== null ? json_encode($businessLat) : 'null' ?>,
    lng: <?= $businessLng !== null ? json_encode($businessLng) : 'null' ?>
};
const EXISTING_ORDER_META = window.EXISTING_ORDER_META || null;
const EXISTING_ORDER_ITEMS = Array.isArray(window.EXISTING_ORDER_ITEMS) ? window.EXISTING_ORDER_ITEMS : [];
const modifierModalEl = document.getElementById('modifierModal');
const hasModifierOptions = modifierModalEl && modifierModalEl.querySelectorAll('.modifier-check').length > 0;
let modifierModalInstance = null;
let deliveryPricingState = {
    distance: null,
    fee: 0,
    baseFee: null,
    zone: null,
    manual: false,
    lastCalculatedAt: null,
    calculatedFee: null,
    rule: null,
    provider: null,
    cacheHit: false,
    fallbackUsed: false,
    auditRequestId: null,
    durationMinutes: null
};
const DELIVERY_FEE_RECALC_DELAY = 900;
let deliveryFeeRecalcTimer = null;
let deliveryMap = null;
let deliveryMarker = null;
let googleMapsLoadingPromise = null;
let deliveryMapInitialized = false;

const RESTAURANT_PROMOTIONS = Array.isArray(window.ACTIVE_RESTAURANT_PROMOTIONS)
    ? window.ACTIVE_RESTAURANT_PROMOTIONS.map((promotion) => ({
        ...promotion,
        id: Number(promotion.id),
        product_id: Number(promotion.product_id),
        min_quantity: Math.max(1, Number(promotion.min_quantity ?? 1)),
        bundle_price: promotion.bundle_price !== null ? parseFloat(promotion.bundle_price) : null,
        discount_value: promotion.discount_value !== null ? parseFloat(promotion.discount_value) : null,
        promotion_type: (promotion.promotion_type || 'bundle_price').toLowerCase(),
    }))
    : [];
const CURRENCY_DECIMALS = 2;
let currentPromotionSummary = { perLineDiscounts: [], totalDiscount: 0, applied: [] };

function roundCurrency(value) {
    const factor = Math.pow(10, CURRENCY_DECIMALS);
    return Math.round((Number(value) || 0) * factor) / factor;
}

function formatAmount(value) {
    return roundCurrency(value).toFixed(CURRENCY_DECIMALS);
}

function evaluatePromotionOption(promotion, unitPrice, quantity) {
    const minQty = Math.max(1, Number(promotion.min_quantity ?? 1));
    if (quantity < minQty || unitPrice <= 0) {
        return null;
    }

    let discount = 0;
    let discountUnits = 0;
    let perUnitDiscount = 0;
    let details = '';
    const type = promotion.promotion_type;

    if (type === 'bundle_price') {
        const bundlePrice = promotion.bundle_price !== null ? Number(promotion.bundle_price) : null;
        if (bundlePrice === null) {
            return null;
        }
        const groups = Math.floor(quantity / minQty);
        if (groups <= 0) {
            return null;
        }
        const regular = unitPrice * minQty;
        const savingsPerBundle = Math.max(0, regular - bundlePrice);
        if (savingsPerBundle <= 0) {
            return null;
        }
        discount = savingsPerBundle * groups;
        discountUnits = groups * minQty;
        perUnitDiscount = savingsPerBundle / minQty;
        details = `${groups} bundle${groups > 1 ? 's' : ''}`;
    } else if (type === 'percent') {
        const percent = Math.max(0, Math.min(100, Number(promotion.discount_value ?? 0)));
        if (percent <= 0) {
            return null;
        }
        perUnitDiscount = unitPrice * (percent / 100);
        discountUnits = quantity;
        discount = perUnitDiscount * discountUnits;
        details = `${percent.toFixed(1).replace(/\.0$/, '')}% off`;
    } else {
        const value = Math.max(0, Number(promotion.discount_value ?? 0));
        if (value <= 0) {
            return null;
        }
        perUnitDiscount = Math.min(value, unitPrice);
        discountUnits = quantity;
        discount = perUnitDiscount * discountUnits;
        details = `${perUnitDiscount.toFixed(2)} off each`;
    }

    perUnitDiscount = Math.min(perUnitDiscount, unitPrice);
    discount = roundCurrency(discount);

    if (discount <= 0 || discountUnits <= 0 || perUnitDiscount <= 0) {
        return null;
    }

    return {
        discount,
        discount_units: discountUnits,
        per_unit_discount: perUnitDiscount,
        promotion,
        details,
    };
}

function calculateCartPromotions(cartItems) {
    if (!Array.isArray(cartItems) || cartItems.length === 0 || RESTAURANT_PROMOTIONS.length === 0) {
        return {
            perLineDiscounts: new Array(cartItems.length).fill(0),
            totalDiscount: 0,
            applied: [],
        };
    }

    const promotionsByProduct = {};
    RESTAURANT_PROMOTIONS.forEach((promotion) => {
        const productId = Number(promotion.product_id);
        if (!productId) {
            return;
        }
        if (!promotionsByProduct[productId]) {
            promotionsByProduct[productId] = [];
        }
        promotionsByProduct[productId].push(promotion);
    });

    const productMap = new Map();
    cartItems.forEach((item, index) => {
        const productId = Number(item.id);
        const qty = Number(item.quantity);
        if (!productId || qty <= 0) {
            return;
        }
        if (!productMap.has(productId)) {
            productMap.set(productId, {
                quantity: 0,
                unitPrice: Number(item.price) || 0,
                name: item.name || null,
                lines: [],
            });
        }
        const entry = productMap.get(productId);
        entry.quantity += qty;
        entry.unitPrice = Number(item.price) || entry.unitPrice;
        if (!entry.name && item.name) {
            entry.name = item.name;
        }
        entry.lines.push({ index, quantity: qty });
    });

    const perLineDiscounts = new Array(cartItems.length).fill(0);
    const applied = [];
    let totalDiscount = 0;

    productMap.forEach((entry, productId) => {
        const promotionList = promotionsByProduct[productId];
        if (!promotionList || promotionList.length === 0) {
            return;
        }

        let bestOption = null;
        promotionList.forEach((promotion) => {
            const result = evaluatePromotionOption(promotion, entry.unitPrice, entry.quantity);
            if (result && (!bestOption || result.discount > bestOption.discount)) {
                bestOption = result;
            }
        });

        if (!bestOption) {
            return;
        }

        let remainingUnits = bestOption.discount_units;
        let allocatedDiscount = 0;

        entry.lines.forEach((line) => {
            if (remainingUnits <= 0) {
                return;
            }
            const qty = Math.min(line.quantity, remainingUnits);
            if (qty <= 0) {
                return;
            }
            const lineDiscount = roundCurrency(qty * bestOption.per_unit_discount);
            perLineDiscounts[line.index] += lineDiscount;
            remainingUnits -= qty;
            allocatedDiscount += lineDiscount;
            totalDiscount += lineDiscount;
        });

        if (allocatedDiscount > 0) {
            applied.push({
                promotion_id: bestOption.promotion.id,
                promotion_name: bestOption.promotion.name ?? null,
                product_id: productId,
                product_name: entry.name ?? null,
                discount: roundCurrency(allocatedDiscount),
                details: bestOption.details,
            });
        }
    });

    return {
        perLineDiscounts,
        totalDiscount: roundCurrency(totalDiscount),
        applied,
    };
}

function getPromotionSummaryPayload() {
    const summary = currentPromotionSummary || {};
    const applied = Array.isArray(summary.applied)
        ? summary.applied.map((entry) => ({
            promotion_id: entry.promotion_id ?? null,
            promotion_name: entry.promotion_name ?? null,
            product_id: entry.product_id ?? null,
            product_name: entry.product_name ?? null,
            discount: roundCurrency(entry.discount ?? 0),
            details: entry.details ?? null,
        }))
        : [];

    return {
        total_discount: roundCurrency(summary.totalDiscount ?? 0),
        applied,
    };
}

function getDeliveryFeeValue() {
    if (orderType === 'dine-in') {
        return 0;
    }

    let fee = deliveryPricingState.fee;
    if (typeof fee !== 'number') {
        fee = parseFloat(fee);
    }

    if (Number.isNaN(fee) || !Number.isFinite(fee)) {
        return 0;
    }

    return Math.max(0, fee);
}

function refreshDeliveryFeeMeta(customMessage) {
    if (orderType === 'dine-in') {
        return;
    }

    const metaEl = document.getElementById('deliveryFeeMeta');
    if (!metaEl) {
        return;
    }

    if (typeof customMessage === 'string') {
        metaEl.textContent = customMessage;
        return;
    }

    const segments = [];

    if (deliveryPricingState.manual) {
        segments.push('Manual fee applied');
    } else if (deliveryPricingState.calculatedFee !== null && !Number.isNaN(deliveryPricingState.calculatedFee)) {
        segments.push('Calculated automatically');
    }

    if (deliveryPricingState.distance !== null && !Number.isNaN(deliveryPricingState.distance)) {
        segments.push('Distance: ' + deliveryPricingState.distance.toFixed(2) + ' km');
    }

    if (deliveryPricingState.zone && deliveryPricingState.zone.name) {
        segments.push('Zone: ' + deliveryPricingState.zone.name);
    }

    if (deliveryPricingState.provider) {
        segments.push('Provider: ' + deliveryPricingState.provider.replace(/_/g, ' '));
    }

    if (deliveryPricingState.cacheHit) {
        segments.push('Cache hit');
    }

    if (deliveryPricingState.durationMinutes !== null) {
        segments.push('ETA: ~' + deliveryPricingState.durationMinutes + ' min');
    }

    if (deliveryPricingState.lastCalculatedAt instanceof Date) {
        segments.push('Updated ' + deliveryPricingState.lastCalculatedAt.toLocaleTimeString());
    }

    metaEl.textContent = segments.join(' • ');
}

function toggleDeliveryFeeEdit(forceOpen) {
    if (orderType === 'dine-in') {
        return;
    }

    const editEl = document.getElementById('deliveryFeeEdit');
    const button = document.getElementById('editDeliveryFeeBtn');
    if (!editEl) {
        return;
    }

    let shouldShow;
    if (typeof forceOpen === 'boolean') {
        shouldShow = forceOpen;
    } else {
        shouldShow = editEl.style.display === 'none' || editEl.style.display === '';
    }

    editEl.style.display = shouldShow ? 'block' : 'none';

    if (button) {
        button.textContent = shouldShow ? 'Close' : 'Edit';
    }

    if (shouldShow) {
        const input = document.getElementById('deliveryFeeInput');
        if (input) {
            if (deliveryPricingState.manual) {
                input.value = deliveryPricingState.fee;
            } else {
                input.value = '';
            }
            input.focus();
            input.select();
        }
    }
}

function toggleDeliveryMap() {
    const wrapper = document.getElementById('deliveryMapWrapper');
    if (!wrapper) return;

    const shouldShow = wrapper.style.display === 'none' || wrapper.style.display === '';
    wrapper.style.display = shouldShow ? 'block' : 'none';

    if (shouldShow && !deliveryMapInitialized) {
        initDeliveryMap();
    }
}

async function initDeliveryMap() {
    if (!googleMapsApiKey) {
        console.warn('Google Maps API key missing');
        return;
    }

    try {
        const googleMaps = await loadGoogleMaps();
        const mapElement = document.getElementById('deliveryMap');
        if (!mapElement) return;

        const defaultCenter = {
            lat: (typeof deliveryPricingState.lat === 'number' ? deliveryPricingState.lat : (businessCoordinates.lat || -1.2921)),
            lng: (typeof deliveryPricingState.lng === 'number' ? deliveryPricingState.lng : (businessCoordinates.lng || 36.8219))
        };

        deliveryMap = new googleMaps.Map(mapElement, {
            center: defaultCenter,
            zoom: 13,
            disableDefaultUI: false,
        });

        deliveryMarker = new googleMaps.Marker({
            map: deliveryMap,
            draggable: true,
            position: defaultCenter,
        });

        deliveryMarker.addListener('dragend', () => {
            const pos = deliveryMarker.getPosition();
            updateDeliveryCoordinates(pos.lat(), pos.lng(), true);
        });

        deliveryMap.addListener('click', (event) => {
            if (!event.latLng) return;
            updateDeliveryCoordinates(event.latLng.lat(), event.latLng.lng(), true);
            if (deliveryMarker) {
                deliveryMarker.setPosition(event.latLng);
            }
        });

        initAutocomplete(googleMaps);

        const currentLat = getCoordinateValue('deliveryLatitude');
        const currentLng = getCoordinateValue('deliveryLongitude');
        if (currentLat !== null && currentLng !== null) {
            const position = { lat: currentLat, lng: currentLng };
            deliveryMap.setCenter(position);
            deliveryMarker.setPosition(position);
        }

        deliveryMapInitialized = true;
    } catch (error) {
        console.error('Failed to load Google Maps', error);
    }
}

function initAutocomplete(googleMaps) {
    const input = document.getElementById('deliveryMapSearch');
    if (!input) {
        return;
    }

    const autocomplete = new googleMaps.places.Autocomplete(input, {
        fields: ['geometry', 'formatted_address'],
        types: ['geocode']
    });

    autocomplete.addListener('place_changed', () => {
        const place = autocomplete.getPlace();
        if (!place || !place.geometry || !place.geometry.location) {
            return;
        }

        const location = place.geometry.location;
        updateDeliveryCoordinates(location.lat(), location.lng(), true);
        if (deliveryMap) {
            deliveryMap.panTo(location);
        }
        if (deliveryMarker) {
            deliveryMarker.setPosition(location);
        }

        if (place.formatted_address) {
            const addressField = document.getElementById('deliveryAddress');
            if (addressField) {
                addressField.value = place.formatted_address;
            }
        }
    });
}

function updateDeliveryCoordinates(lat, lng, updateInputs = false) {
    if (updateInputs) {
        const latField = document.getElementById('deliveryLatitude');
        const lngField = document.getElementById('deliveryLongitude');
        if (latField) latField.value = lat.toFixed(6);
        if (lngField) lngField.value = lng.toFixed(6);
    }
    deliveryPricingState.lat = lat;
    deliveryPricingState.lng = lng;
    scheduleDeliveryFeeRecalc();
}

function loadGoogleMaps() {
    if (googleMapsLoadingPromise) {
        return googleMapsLoadingPromise;
    }

    googleMapsLoadingPromise = new Promise((resolve, reject) => {
        if (window.google && window.google.maps) {
            resolve(window.google.maps);
            return;
        }

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(googleMapsApiKey)}&libraries=places`;
        script.async = true;
        script.defer = true;
        script.onload = () => {
            if (window.google && window.google.maps) {
                resolve(window.google.maps);
            } else {
                reject(new Error('Google Maps failed to load.'));
            }
        };
        script.onerror = () => {
            reject(new Error('Unable to load Google Maps script.'));
        };

        document.head.appendChild(script);
    });

    return googleMapsLoadingPromise;
}

function useCurrentLocation() {
    if (!navigator.geolocation) {
        refreshDeliveryFeeMeta('Geolocation not supported in this browser.');
        return;
    }

    navigator.geolocation.getCurrentPosition((position) => {
        const { latitude, longitude } = position.coords;
        updateDeliveryCoordinates(latitude, longitude, true);
        if (deliveryMap) {
            deliveryMap.panTo({ lat: latitude, lng: longitude });
        }
        if (deliveryMarker) {
            deliveryMarker.setPosition({ lat: latitude, lng: longitude });
        } else if (deliveryMapInitialized) {
            const googleMaps = window.google && window.google.maps;
            if (googleMaps) {
                deliveryMarker = new googleMaps.Marker({
                    map: deliveryMap,
                    position: { lat: latitude, lng: longitude },
                    draggable: true,
                });
            }
        }
        refreshDeliveryFeeMeta('Location captured from device GPS.');
    }, () => {
        refreshDeliveryFeeMeta('Unable to retrieve current location.');
    });
}

function applyManualDeliveryFee() {
    if (orderType === 'dine-in') {
        return;
    }
    const input = document.getElementById('deliveryFeeInput');
    if (!input) {
        return;
    }

    const rawValue = input.value ? input.value.trim() : '';

    if (rawValue === '') {
        deliveryPricingState.manual = false;
        if (deliveryPricingState.calculatedFee !== null && !Number.isNaN(deliveryPricingState.calculatedFee)) {
            deliveryPricingState.fee = Math.max(0, deliveryPricingState.calculatedFee);
        } else {
            deliveryPricingState.fee = 0;
        }
        deliveryPricingState.lastCalculatedAt = new Date();
        refreshDeliveryFeeMeta('Manual fee cleared. Using calculated fee.');
        toggleDeliveryFeeEdit(false);
        updateCart();
        scheduleDeliveryFeeRecalc();
        return;
    }

    const manualValue = parseFloat(rawValue);
    if (Number.isNaN(manualValue)) {
        refreshDeliveryFeeMeta('Enter a valid number for the delivery fee.');
        input.focus();
        input.select();
        return;
    }

    deliveryPricingState.manual = true;
    deliveryPricingState.fee = Math.max(0, manualValue);
    deliveryPricingState.lastCalculatedAt = new Date();
    refreshDeliveryFeeMeta();
    toggleDeliveryFeeEdit(false);
    updateCart();
}

function scheduleDeliveryFeeRecalc() {
    if (orderType === 'dine-in') {
        return;
    }
    if (deliveryFeeRecalcTimer) {
        clearTimeout(deliveryFeeRecalcTimer);
    }
    deliveryFeeRecalcTimer = setTimeout(() => {
        deliveryFeeRecalcTimer = null;
        calculateDeliveryFee();
    }, DELIVERY_FEE_RECALC_DELAY);
}

async function calculateDeliveryFee() {
    if (orderType === 'dine-in') {
        return;
    }

    const latitude = getCoordinateValue('deliveryLatitude');
    const longitude = getCoordinateValue('deliveryLongitude');
    const button = document.getElementById('recalculateDeliveryBtn');

    if (latitude === null || longitude === null) {
        refreshDeliveryFeeMeta('Enter latitude and longitude to calculate delivery fee.');
        return;
    }

    if (button) {
        button.disabled = true;
        button.textContent = 'Calculating…';
    }

    refreshDeliveryFeeMeta('Calculating delivery fee…');

    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const taxAmount = subtotal * (TAX_RATE / 100);

    const payload = {
        delivery_latitude: latitude,
        delivery_longitude: longitude,
        subtotal: subtotal,
        tax_amount: taxAmount,
    };

    if (deliveryPricingState.manual) {
        payload.delivery_fee = deliveryPricingState.fee;
    }

    try {
        const response = await fetch('api/calculate-delivery-fee.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        const result = await response.json();

        if (!result.success || !result.data) {
            throw new Error(result.message || 'Delivery fee calculation failed');
        }

        const data = result.data;

        deliveryPricingState.manual = false;
        deliveryPricingState.distance = typeof data.distance_km === 'number' ? data.distance_km : null;
        deliveryPricingState.baseFee = typeof data.base_fee === 'number' ? data.base_fee : null;
        deliveryPricingState.calculatedFee = typeof data.calculated_fee === 'number' ? Math.max(0, data.calculated_fee) : null;
        deliveryPricingState.fee = deliveryPricingState.calculatedFee !== null ? deliveryPricingState.calculatedFee : 0;
        deliveryPricingState.zone = data.zone || null;
        deliveryPricingState.provider = data.provider || null;
        deliveryPricingState.cacheHit = Boolean(data.cache_hit);
        deliveryPricingState.fallbackUsed = Boolean(data.fallback_used);
        deliveryPricingState.durationMinutes = typeof data.duration_minutes === 'number' ? data.duration_minutes : null;
        deliveryPricingState.auditRequestId = data.audit_request_id || null;
        deliveryPricingState.lastCalculatedAt = new Date();

        refreshDeliveryFeeMeta();
        toggleDeliveryFeeEdit(false);
        updateCart();
    } catch (error) {
        console.error('Delivery fee calculation failed', error);
        refreshDeliveryFeeMeta('Delivery fee error: ' + (error && error.message ? error.message : 'Unexpected error'));
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = 'Calculate Delivery Fee';
        }
    }
}

function filterProducts() {
    const searchTerm = document.getElementById('searchProduct').value.toLowerCase();
    const categoryFilter = document.getElementById('filterCategory').value;
    const products = document.querySelectorAll('.product-item');
    
    products.forEach(product => {
        const name = product.dataset.name;
        const category = product.dataset.category;
        
        const matchesSearch = name.includes(searchTerm);
        const matchesCategory = !categoryFilter || category === categoryFilter;
        
        product.style.display = matchesSearch && matchesCategory ? '' : 'none';
    });
}

function buildProductFromDataset(dataset) {
    if (dataset.productJson) {
        try {
            return JSON.parse(dataset.productJson);
        } catch (error) {
            console.error('Failed to parse product JSON dataset', error, dataset.productJson);
        }
    }

    return {
        id: Number.parseInt(dataset.productId, 10),
        name: dataset.productName,
        selling_price: parseFloat(dataset.productPrice),
        stock_quantity: parseFloat(dataset.productStock)
    };
}

function addToCart(product) {
    if (!product || !product.name || Number.isNaN(product.id) || Number.isNaN(product.selling_price)) {
        console.error('Invalid product payload', product);
        return;
    }

    currentItem = {
        id: product.id,
        name: product.name,
        price: parseFloat(product.selling_price),
        quantity: 1,
        modifiers: [],
        instructions: '',
        base_price: parseFloat(product.selling_price)
    };
    
    if (!hasModifierOptions) {
        currentItem.total = currentItem.price * currentItem.quantity;
        cart.push(Object.assign({}, currentItem));
        updateCart();
        currentItem = null;
        return;
    }

    // Show modifier modal
    document.getElementById('itemName').textContent = product.name;
    document.getElementById('itemPrice').textContent = parseFloat(product.selling_price).toFixed(2);
    document.querySelectorAll('.modifier-check').forEach(cb => cb.checked = false);
    document.getElementById('specialInstructions').value = '';
    
    if (!modifierModalInstance) {
        modifierModalInstance = new bootstrap.Modal(modifierModalEl);
    }
    modifierModalInstance.show();
}

function confirmModifiers() {
    // Get selected modifiers
    const modifiers = [];
    let modifierTotal = 0;
    
    document.querySelectorAll('.modifier-check:checked').forEach(cb => {
        const mod = {
            id: cb.dataset.id,
            name: cb.dataset.name,
            price: parseFloat(cb.dataset.price)
        };
        modifiers.push(mod);
        modifierTotal += mod.price;
    });
    
    currentItem.modifiers = modifiers;
    currentItem.instructions = document.getElementById('specialInstructions').value;
    currentItem.price = currentItem.base_price + modifierTotal;
    currentItem.total = currentItem.price * currentItem.quantity;
    
    cart.push(currentItem);
    updateCart();
    
    if (modifierModalInstance) {
        modifierModalInstance.hide();
    }
}

function updateCart() {
    const cartDiv = document.getElementById('cartItems');
    
    const submitBtn = document.getElementById('submitBtn');
    const promotionSummaryResult = calculateCartPromotions(cart);
    currentPromotionSummary = promotionSummaryResult;

    if (cart.length === 0) {
        cartDiv.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-cart-x fs-1"></i>
                <p class="mt-2">Cart is empty</p>
            </div>
        `;
        if (submitBtn) {
            submitBtn.disabled = true;
        }
    } else {
        let html = '<div class="list-group">';
        cart.forEach((item, index) => {
            const lineSubtotal = roundCurrency(Number(item.price) * Number(item.quantity));
            const lineDiscount = Math.min(lineSubtotal, promotionSummaryResult.perLineDiscounts[index] ?? 0);
            const netTotal = roundCurrency(lineSubtotal - lineDiscount);

            cart[index].line_subtotal = lineSubtotal;
            cart[index].promotion_discount = roundCurrency(lineDiscount);
            cart[index].total = netTotal;

            html += `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-0">${item.name}</h6>
                            ${item.modifiers.map(m => `<small class="text-muted d-block">+ ${m.name}</small>`).join('')}
                            ${item.instructions ? `<small class="text-muted fst-italic d-block">${item.instructions}</small>` : ''}
                            ${cart[index].promotion_discount > 0 ? `<small class="text-success d-block">-${formatAmount(cart[index].promotion_discount)} promo</small>` : ''}
                        </div>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${index})">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary" onclick="updateQuantity(${index}, -1)">-</button>
                            <button class="btn btn-outline-secondary" disabled>${item.quantity}</button>
                            <button class="btn btn-outline-secondary" onclick="updateQuantity(${index}, 1)">+</button>
                        </div>
                        <strong>${formatAmount(cart[index].total)}</strong>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        cartDiv.innerHTML = html;
        if (submitBtn) {
            submitBtn.disabled = false;
        }
    }
    
    // Calculate totals
    const grossSubtotal = cart.reduce((sum, item) => sum + (item.line_subtotal ?? (item.price * item.quantity)), 0);
    const promotionDiscount = Math.min(grossSubtotal, promotionSummaryResult.totalDiscount || 0);
    const netSubtotal = Math.max(0, grossSubtotal - promotionDiscount);
    const taxAmount = roundCurrency(netSubtotal * (TAX_RATE / 100));
    const deliveryFee = getDeliveryFeeValue();
    const total = roundCurrency(netSubtotal + taxAmount + deliveryFee);
    const tipAmount = getTipAmount();
    const grandTotal = roundCurrency(total + tipAmount);

    orderFinancials = {
        grossSubtotal: roundCurrency(grossSubtotal),
        promotionDiscount: roundCurrency(promotionDiscount),
        netSubtotal: roundCurrency(netSubtotal),
        subtotal: roundCurrency(netSubtotal),
        tax: taxAmount,
        deliveryFee,
        total,
        tip: tipAmount,
        grandTotal,
    };
    
    const subtotalEl = document.getElementById('subtotal');
    const taxEl = document.getElementById('taxAmount');
    const deliveryFeeEl = document.getElementById('deliveryFeeDisplay');
    const totalEl = document.getElementById('total');
    const tipRow = document.getElementById('tipRow');
    const tipDisplay = document.getElementById('tipAmountDisplay');
    const grandTotalEl = document.getElementById('grandTotalAmount');

    if (subtotalEl) subtotalEl.textContent = formatAmount(netSubtotal);
    if (taxEl) taxEl.textContent = formatAmount(taxAmount);
    if (deliveryFeeEl) deliveryFeeEl.textContent = formatAmount(deliveryFee);
    if (totalEl) totalEl.textContent = formatAmount(total);
    if (tipRow) tipRow.classList.toggle('d-none', tipAmount <= 0);
    if (tipDisplay) tipDisplay.textContent = formatAmount(tipAmount);
    if (grandTotalEl) grandTotalEl.textContent = formatAmount(grandTotal);

    const promoRow = document.getElementById('promotionDiscountRow');
    const promoValue = document.getElementById('promotionDiscountValue');
    if (promoRow && promoValue) {
        if (promotionDiscount > 0) {
            promoRow.classList.remove('d-none');
            promoValue.textContent = '-' + formatAmount(promotionDiscount);
        } else {
            promoRow.classList.add('d-none');
            promoValue.textContent = '-' + formatAmount(0);
        }
    }

    // Update button states
    updateButtonStates();
    refreshCashTenderUI();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    updateCart();
}

function updateQuantity(index, change) {
    const item = cart[index];
    const newQty = item.quantity + change;
    
    if (newQty <= 0) {
        removeFromCart(index);
        return;
    }
    
    item.quantity = newQty;
    item.total = item.price * item.quantity;
    updateCart();
}

function clearCart() {
    if (cart.length > 0 && confirm('Clear all items?')) {
        cart = [];
        updateCart();
    }
}

let currentOrderId = null;
let orderStatus = 'draft'; // draft, placed, paid

function hydrateExistingOrder() {
    if (!EXISTING_ORDER_META || !Array.isArray(EXISTING_ORDER_ITEMS) || EXISTING_ORDER_ITEMS.length === 0) {
        return false;
    }

    currentOrderId = EXISTING_ORDER_META.id || null;
    orderStatus = EXISTING_ORDER_META.payment_status === 'paid' ? 'paid' : 'placed';

    cart = EXISTING_ORDER_ITEMS.map((item) => {
        const qty = Number(item.quantity) || 1;
        const unitPrice = typeof item.unit_price === 'number' ? item.unit_price : (Number(item.total) / qty);
        return {
            id: item.id,
            name: item.name,
            base_price: item.base_price ?? unitPrice,
            price: unitPrice,
            quantity: qty,
            modifiers: Array.isArray(item.modifiers) ? item.modifiers : [],
            instructions: item.instructions || '',
            total: item.total ?? unitPrice * qty,
        };
    });

    const paymentMethodSelect = document.getElementById('paymentMethod');
    if (paymentMethodSelect && EXISTING_ORDER_META.payment_method) {
        paymentMethodSelect.value = EXISTING_ORDER_META.payment_method;
    }

    if (typeof EXISTING_ORDER_META.tip_amount === 'number') {
        setTipAmount(EXISTING_ORDER_META.tip_amount, false);
    }

    updateCart();
    return true;
}

async function submitOrder() {
    if (cart.length === 0) return;
    if (!requireCustomerIdentity(true)) {
        alert('Customer name and phone are required for takeout and delivery orders.');
        return;
    }
    if (!requireDeliveryDetailsValid(true)) {
        alert('Please capture a delivery address for off-site orders.');
        return;
    }
    const paymentMethodInput = document.getElementById('paymentMethod');
    const paymentMethod = paymentMethodInput ? paymentMethodInput.value : 'cash';
    if (!requireDeliveryDetailsValid(true)) {
        return;
    }
    
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const taxAmount = subtotal * (TAX_RATE / 100);
    const deliveryFee = getDeliveryFeeValue();
    const total = subtotal + taxAmount + deliveryFee;
    const tipAmount = getTipAmount();
    const grandTotal = total + tipAmount;

    const orderData = {
        action: 'place_order',
        order_type: '<?= $orderType ?>',
        table_id: <?= $tableId ?? 'null' ?>,
        customer_name: getInputValue('customerName') || null,
        customer_phone: getInputValue('customerPhone') || null,
        payment_method: paymentMethod,
        subtotal: subtotal,
        tax_amount: taxAmount,
        delivery_fee: deliveryFee,
        total_amount: total,
        tip_amount: tipAmount,
        delivery_address: getInputValue('deliveryAddress') || null,
        delivery_latitude: getCoordinateValue('deliveryLatitude'),
        delivery_longitude: getCoordinateValue('deliveryLongitude'),
        delivery_pricing_request_id: deliveryPricingState.auditRequestId || null,
        items: cart,
        promotion_discount: currentPromotionSummary?.totalDiscount || 0,
        promotion_summary: getPromotionSummaryPayload(),
        totals: {
            subtotal: orderFinancials.grossSubtotal,
            promotion_discount: currentPromotionSummary?.totalDiscount || 0,
            net_subtotal: orderFinancials.netSubtotal,
            tax_amount: taxAmount,
            delivery_fee: deliveryFee,
            tip_amount: tipAmount,
            total_amount: total,
            grand_total: grandTotal,
        }
    };
    if (paymentMethod === 'cash') {
        const cashPayload = getCashTenderPayload();
        orderData.amount_received = cashPayload.amount_received;
        orderData.change_amount = cashPayload.change_amount;
    }
    
    try {
        const response = await fetch('api/restaurant-order-workflow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(orderData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            currentOrderId = result.order_id;
            orderStatus = 'placed';
            
            alert('Order placed successfully! Order #: ' + result.order_number);
            
            // Update button states
            updateButtonStates();
            
            // Show print results if any
            if (result.print_results) {
                showPrintResults(result.print_results);
            }
            
        } else {
            alert('Error: ' + (result.message || 'Failed to place order'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function printInvoice() {
    console.log('printInvoice called, currentOrderId:', currentOrderId);
    
    if (!currentOrderId) {
        await submitOrder();
        if (!currentOrderId) return;
    }
    
    try {
        const response = await fetch('api/restaurant-order-workflow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'print_receipt',
                order_id: currentOrderId,
                receipt_type: 'invoice'
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('Invoice print result:', result);
        
        if (result.success) {
            // Open invoice in new window
            window.open('print-customer-invoice.php?id=' + currentOrderId, '_blank', 'width=400,height=600');
        } else {
            alert('Error printing invoice: ' + result.message);
        }
    } catch (error) {
        console.error('Invoice print error:', error);
        alert('Error: ' + error.message);
    }
}

async function processPayment() {
    if (!currentOrderId) {
        await submitOrder();
        if (!currentOrderId) return;
    }
    
    const paymentMethodInput = document.getElementById('paymentMethod');
    const paymentMethod = paymentMethodInput ? paymentMethodInput.value : 'cash';
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const taxAmount = subtotal * (TAX_RATE / 100);
    const deliveryFee = getDeliveryFeeValue();
    const total = subtotal + taxAmount + deliveryFee;
    const tipAmount = getTipAmount();
    const totalDue = total + tipAmount;

    if (paymentMethod === 'cash' && !cashTenderValid(true)) {
        alert('Enter the amount received before processing payment.');
        return;
    }

    if (!confirm(`Process payment of ${currencySymbol} ${totalDue.toFixed(2)} via ${paymentMethod}?`)) {
        return;
    }
    
    try {
        const payload = {
            action: 'process_payment',
            order_id: currentOrderId,
            payment_method: paymentMethod,
            amount_paid: total,
            tip_amount: tipAmount
        };

        if (paymentMethod === 'cash') {
            const cashPayload = getCashTenderPayload();
            payload.amount_received = cashPayload.amount_received;
            payload.change_amount = cashPayload.change_amount;
        }

        if (paymentMethod === 'room_charge') {
            const roomSelect = document.getElementById('roomBookingSelect');
            if (!roomSelect || roomSelect.options.length <= 1) {
                alert('There are no checked-in rooms available to charge.');
                return;
            }

            if (!roomSelect.value) {
                alert('Please select a checked-in room to charge this order to.');
                roomSelect.focus();
                return;
            }

            const roomBookingId = parseInt(roomSelect.value, 10);
            if (!Number.isFinite(roomBookingId) || roomBookingId <= 0) {
                alert('Invalid room selection.');
                return;
            }

            const descriptionField = document.getElementById('roomChargeDescription');
            payload.room_booking_id = roomBookingId;
            payload.amount_paid = 0;
            payload.change_amount = 0;
            if (descriptionField && descriptionField.value.trim() !== '') {
                payload.room_charge_description = descriptionField.value.trim();
            }
        }

        const response = await fetch('api/restaurant-order-workflow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        
        if (result.success) {
            orderStatus = 'paid';
            alert('Payment processed successfully!');
            
            // Update button states
            updateButtonStates();
            
            // Show print results if any
            if (result.print_results) {
                showPrintResults(result.print_results);
            }
            
            // Ask if want to print receipt
            if (confirm('Print customer receipt?')) {
                printCustomerReceipt();
            }
            
        } else {
            alert('Error processing payment: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function printKitchenOrder() {
    console.log('printKitchenOrder called, currentOrderId:', currentOrderId);
    
    if (!currentOrderId) {
        alert('Please place the order first');
        return;
    }
    
    try {
        const response = await fetch('api/restaurant-order-workflow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'print_receipt',
                order_id: currentOrderId,
                receipt_type: 'kitchen'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Open kitchen order in new window
            window.open('print-kitchen-order.php?id=' + currentOrderId, '_blank', 'width=400,height=600');
        } else {
            alert('Error printing kitchen order: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function printCustomerReceipt() {
    console.log('printCustomerReceipt called, currentOrderId:', currentOrderId, 'orderStatus:', orderStatus);
    
    if (!currentOrderId) {
        alert('Please place the order first');
        return;
    }
    
    if (orderStatus !== 'paid') {
        alert('Payment must be completed before printing receipt');
        return;
    }
    
    try {
        const response = await fetch('api/restaurant-order-workflow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'print_receipt',
                order_id: currentOrderId,
                receipt_type: 'receipt'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Open customer receipt in new window
            window.open('print-customer-receipt.php?id=' + currentOrderId, '_blank', 'width=400,height=600');
        } else {
            alert('Error printing customer receipt: ' + result.message);
        }
    } catch (error) {
        console.error('Customer receipt print error:', error);
        alert('Error: ' + error.message);
    }
}

function roomChargeSelectionValid() {
    const paymentMethodInput = document.getElementById('paymentMethod');
    if (!paymentMethodInput || paymentMethodInput.value !== 'room_charge') {
        return true;
    }

    const select = document.getElementById('roomBookingSelect');
    if (!select) {
        return false;
    }

    return select.value !== '';
}

function handlePaymentMethodChange() {
    const methodSelect = document.getElementById('paymentMethod');
    const roomChargeSection = document.getElementById('roomChargeSection');
    if (!methodSelect) {
        return;
    }

    if (roomChargeSection) {
        roomChargeSection.style.display = methodSelect.value === 'room_charge' ? 'block' : 'none';
    }

    refreshCashTenderUI(false);
    updateButtonStates();
}

function updateButtonStates() {
    const hasItems = cart.length > 0;
    const hasOrder = currentOrderId !== null;
    const isPaid = orderStatus === 'paid';
    const paymentMethodInput = document.getElementById('paymentMethod');
    const paymentMethod = paymentMethodInput ? paymentMethodInput.value : 'cash';
    const roomChargeReady = roomChargeSelectionValid();
    const identityReady = requireCustomerIdentity(false);
    const deliveryReady = requireDeliveryDetailsValid(false);
    const cashReady = paymentMethod !== 'cash' ? true : cashTenderValid(false);
    
    // Enable/disable buttons based on state with error handling
    try {
        const invoiceBtn = document.getElementById('invoiceBtn');
        const paymentBtn = document.getElementById('paymentBtn');
        const kitchenBtn = document.getElementById('kitchenBtn');
        const receiptBtn = document.getElementById('receiptBtn');
        
        if (invoiceBtn) invoiceBtn.disabled = !hasItems || !identityReady || !deliveryReady;
        if (paymentBtn) paymentBtn.disabled = !hasItems || !identityReady || !deliveryReady || (paymentMethod === 'room_charge' && !roomChargeReady) || (paymentMethod === 'cash' && !cashReady);
        if (kitchenBtn) kitchenBtn.disabled = !hasOrder;
        if (receiptBtn) receiptBtn.disabled = !isPaid;
    } catch (error) {
        console.error('Error updating button states:', error);
    }
}

function customerIdentityValid() {
    if (!customerIdentityRequired()) {
        return true;
    }

    const nameInput = document.getElementById('customerName');
    const phoneInput = document.getElementById('customerPhone');
    const name = (nameInput?.value || '').trim();
    const phone = (phoneInput?.value || '').trim();

    return name !== '' && phone !== '';
}

function requireCustomerIdentity(showFeedback = true) {
    const helper = document.getElementById('customerIdentityHelper');

    if (!customerIdentityRequired()) {
        if (helper) helper.classList.add('d-none');
        return true;
    }

    const nameInput = document.getElementById('customerName');
    const phoneInput = document.getElementById('customerPhone');

    const name = (nameInput?.value || '').trim();
    const phone = (phoneInput?.value || '').trim();
    const valid = name !== '' && phone !== '';

    if (helper) {
        if (valid) {
            helper.classList.add('d-none');
        } else if (showFeedback) {
            helper.classList.remove('d-none');
        } else {
            helper.classList.add('d-none');
        }
    }

    if (nameInput) {
        if (!valid && showFeedback && name === '') {
            nameInput.classList.add('is-invalid');
        } else {
            nameInput.classList.remove('is-invalid');
        }
    }

    if (phoneInput) {
        if (!valid && showFeedback && phone === '') {
            phoneInput.classList.add('is-invalid');
        } else {
            phoneInput.classList.remove('is-invalid');
        }
    }

    if (!valid && showFeedback) {
        if (name === '' && nameInput) {
            nameInput.focus();
        } else if (phoneInput) {
            phoneInput.focus();
        }
    }

    return valid;
}

function customerIdentityRequired() {
    if (orderType === 'delivery') {
        return true;
    }

    if (orderType === 'takeout') {
        return deliveryDetailsRequired();
    }

    return false;
}

function deliveryDetailsRequired() {
    if (orderType === 'delivery') {
        return true;
    }

    if (orderType === 'takeout') {
        const toggle = document.getElementById('requireDeliveryDetails');
        return toggle ? toggle.checked : false;
    }

    return false;
}

function refreshDeliveryDetailsUI() {
    const required = deliveryDetailsRequired();
    const section = document.getElementById('deliveryDetailFields');
    const helper = document.getElementById('deliveryDetailsHelper');

    if (section) {
        section.style.display = required ? 'block' : 'none';
    }

    if (!required && helper) {
        helper.classList.add('d-none');
    }
}

function requireDeliveryDetailsValid(showFeedback = true) {
    if (!deliveryDetailsRequired()) {
        const helper = document.getElementById('deliveryDetailsHelper');
        if (helper) helper.classList.add('d-none');
        return true;
    }

    const addressInput = document.getElementById('deliveryAddress');
    const helper = document.getElementById('deliveryDetailsHelper');
    const address = (addressInput?.value || '').trim();
    const valid = address !== '';

    if (helper) {
        helper.classList.toggle('d-none', valid || !showFeedback);
    }
    if (addressInput) {
        addressInput.classList.toggle('is-invalid', !valid && showFeedback);
    }

    if (!valid && showFeedback && addressInput) {
        addressInput.focus();
    }

    return valid;
}

function getTipInput() {
    return document.getElementById('tipAmountInput');
}

function sanitizeTipValue(value) {
    if (value === '' || value === null || value === undefined) {
        return 0;
    }
    const parsed = parseFloat(value);
    if (Number.isNaN(parsed) || parsed < 0) {
        return 0;
    }
    return parsed;
}

function getTipAmount() {
    const input = getTipInput();
    if (!input) {
        return 0;
    }
    return sanitizeTipValue(input.value);
}

function setTipAmount(value, triggerUpdate = true) {
    const input = getTipInput();
    if (!input) {
        return;
    }
    const cleanValue = sanitizeTipValue(value);
    input.value = cleanValue > 0 ? cleanValue.toFixed(2) : '';
    if (triggerUpdate) {
        updateCart();
    }
}

function clearTipAmount() {
    const input = getTipInput();
    if (!input) {
        return;
    }
    input.value = '';
    updateCart();
}

function refreshCashTenderUI(showFeedback = false) {
    const methodSelect = document.getElementById('paymentMethod');
    const section = document.getElementById('cashPaymentSection');
    const input = document.getElementById('cashAmountReceived');
    const changeDisplay = document.getElementById('cashChangeDisplay');

    if (!methodSelect || !section) {
        return;
    }

    const isCash = methodSelect.value === 'cash';
    section.style.display = isCash ? 'block' : 'none';

    if (!isCash) {
        clearCashTenderAlerts();
        return;
    }

    if (input && changeDisplay) {
        const tendered = parseFloat(input.value || '0');
        const totalDue = orderFinancials.grandTotal || orderFinancials.total || 0;
        changeDisplay.textContent = Math.max(0, tendered - totalDue).toFixed(2);
    }

    cashTenderValid(showFeedback);
}

function clearCashTenderAlerts() {
    const helper = document.getElementById('cashTenderHelper');
    const input = document.getElementById('cashAmountReceived');
    if (helper) helper.classList.add('d-none');
    if (input) input.classList.remove('is-invalid');
}

function cashTenderValid(showFeedback = true) {
    const methodSelect = document.getElementById('paymentMethod');
    const input = document.getElementById('cashAmountReceived');
    const helper = document.getElementById('cashTenderHelper');

    if (!methodSelect || methodSelect.value !== 'cash') {
        clearCashTenderAlerts();
        return true;
    }

    if (!input) {
        return false;
    }

    const tendered = parseFloat(input.value || '0');
    const totalDue = orderFinancials.grandTotal || orderFinancials.total || 0;
    const valid = tendered >= totalDue && tendered > 0;

    if (helper) {
        helper.classList.toggle('d-none', valid || !showFeedback);
    }
    input.classList.toggle('is-invalid', !valid && showFeedback);

    if (!valid && showFeedback) {
        input.focus();
    }

    const changeDisplay = document.getElementById('cashChangeDisplay');
    if (changeDisplay) {
        changeDisplay.textContent = Math.max(0, tendered - totalDue).toFixed(2);
    }

    return valid;
}

function fillExactTender() {
    const input = document.getElementById('cashAmountReceived');
    if (!input) {
        return;
    }
    const totalDue = orderFinancials.grandTotal || orderFinancials.total || 0;
    input.value = totalDue.toFixed(2);
    refreshCashTenderUI(false);
    updateButtonStates();
}

function getCashTenderPayload() {
    const input = document.getElementById('cashAmountReceived');
    if (!input) {
        return { amount_received: null, change_amount: null };
    }

    const tendered = parseFloat(input.value || '0');
    const total = orderFinancials.grandTotal || orderFinancials.total || 0;
    const change = Math.max(0, tendered - total);
    return {
        amount_received: tendered,
        change_amount: change,
    };
}

function showPrintResults(printResults) {
    let message = 'Print Status:\n';
    
    if (printResults.kitchen) {
        message += `Kitchen: ${printResults.kitchen.success ? '✓ Printed' : '✗ Failed'}\n`;
    }
    if (printResults.invoice) {
        message += `Invoice: ${printResults.invoice.success ? '✓ Printed' : '✗ Failed'}\n`;
    }
    if (printResults.receipt) {
        message += `Receipt: ${printResults.receipt.success ? '✓ Printed' : '✗ Failed'}\n`;
    }
    
    if (message !== 'Print Status:\n') {
        alert(message);
    }
}

// Test function to verify buttons work
function testButtons() {
    console.log('Testing buttons...');
}

function getInputValue(elementId) {
    const el = document.getElementById(elementId);
    return el ? el.value : null;
}

function getCoordinateValue(elementId) {
    const raw = getInputValue(elementId);
    if (raw === null || raw === undefined || raw === '') {
        return null;
    }

    const parsed = parseFloat(raw);
    if (Number.isNaN(parsed)) {
        return null;
    }

    return parsed;
}

document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded, initializing restaurant order page...');

    const hydrated = hydrateExistingOrder();

    const searchInput = document.getElementById('searchProduct');
    const categorySelect = document.getElementById('filterCategory');
    if (searchInput) {
        searchInput.addEventListener('input', filterProducts);
    }
    if (categorySelect) {
        categorySelect.addEventListener('change', filterProducts);
    }

    // Initialize button states
    updateButtonStates();

    const productsList = document.getElementById('productsList');
    if (productsList) {
        productsList.addEventListener('click', (event) => {
            const button = event.target.closest('.add-to-cart-btn');
            if (!button) {
                return;
            }
            event.preventDefault();
            const product = buildProductFromDataset(button.dataset);
            addToCart(product);
        });

        productsList.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            const button = event.target.closest('.add-to-cart-btn');
            if (button) {
                event.preventDefault();
                const product = buildProductFromDataset(button.dataset);
                addToCart(product);
                return;
            }

            const item = event.target.closest('.product-item');
            if (!item) {
                return;
            }

            const primaryButton = item.querySelector('.add-to-cart-btn');
            if (primaryButton) {
                event.preventDefault();
                primaryButton.focus();
                const product = buildProductFromDataset(primaryButton.dataset);
                addToCart(product);
            }
        });
    }
    
    const paymentMethodSelect = document.getElementById('paymentMethod');
    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', handlePaymentMethodChange);
        handlePaymentMethodChange();
    }

    const identityInputs = ['customerName', 'customerPhone'];
    identityInputs.forEach((id) => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', () => {
                requireCustomerIdentity(false);
                updateButtonStates();
            });
        }
    });

    const cashInput = document.getElementById('cashAmountReceived');
    if (cashInput) {
        cashInput.addEventListener('input', () => {
            refreshCashTenderUI(false);
            updateButtonStates();
        });
    }

    const tipInput = document.getElementById('tipAmountInput');
    if (tipInput) {
        tipInput.addEventListener('input', () => {
            updateCart();
        });
    }

    const deliveryToggle = document.getElementById('requireDeliveryDetails');
    if (deliveryToggle) {
        deliveryToggle.addEventListener('change', () => {
            refreshDeliveryDetailsUI();
            updateCart();
        });
    }

    refreshDeliveryDetailsUI();

    const roomSelect = document.getElementById('roomBookingSelect');
    if (roomSelect) {
        roomSelect.addEventListener('change', refreshPaymentUIState);
    }

    if (orderType !== 'dine-in') {
        const latField = document.getElementById('deliveryLatitude');
        const lngField = document.getElementById('deliveryLongitude');
        const addressField = document.getElementById('deliveryAddress');
        latField?.addEventListener('input', scheduleDeliveryFeeRecalc);
        lngField?.addEventListener('input', scheduleDeliveryFeeRecalc);
        addressField?.addEventListener('blur', scheduleDeliveryFeeRecalc);
    }

    if (!hydrated) {
        updateCart();
    }

    // Add debug logging for button clicks
    console.log('Page initialized. You can run testButtons() in console to debug.');
});
</script>

<?php include 'includes/footer.php'; ?>
