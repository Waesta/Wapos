<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();
$currencyManager = CurrencyManager::getInstance();

// Get active categories and products
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$products = $db->fetchAll("SELECT * FROM products WHERE is_active = 1 ORDER BY name");

$pdo = $db->getConnection();
$checkedInRooms = [];

try {
    $tablesStmt = $pdo->query("SHOW TABLES LIKE 'room_bookings'");
    if ($tablesStmt && $tablesStmt->fetchColumn()) {
        $roomsStmt = $pdo->prepare(
            "SELECT b.id, b.booking_number, b.guest_name, b.guest_phone, r.room_number
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
                $label = 'Booking #' . (int) $row['id'];
            }

            $checkedInRooms[] = [
                'id' => (int) $row['id'],
                'label' => $label,
            ];
        }
    }
} catch (Throwable $e) {
    $checkedInRooms = [];
}

$gatewayProvider = strtolower((string)(settings('payments_gateway_provider') ?? ''));
$isGatewayEnabled = in_array($gatewayProvider, ['relworx', 'pesapal'], true);
$currencyCode = $currencyManager->getCurrencyCode();

$latestSaleSnapshot = $db->fetchOne("SELECT id, sale_number, total_amount, payment_method, created_at FROM sales ORDER BY id DESC LIMIT 1");
$latestSaleSnapshot = $latestSaleSnapshot ? [
    'id' => (int)($latestSaleSnapshot['id'] ?? 0),
    'sale_number' => $latestSaleSnapshot['sale_number'] ?? null,
    'total_amount' => isset($latestSaleSnapshot['total_amount']) ? (float)$latestSaleSnapshot['total_amount'] : null,
    'payment_method' => $latestSaleSnapshot['payment_method'] ?? null,
    'created_at' => $latestSaleSnapshot['created_at'] ?? null,
] : [
    'id' => 0,
    'sale_number' => null,
    'total_amount' => null,
    'payment_method' => null,
    'created_at' => null,
];

$todaySalesStats = $db->fetchOne("SELECT COUNT(*) AS sale_count, COALESCE(SUM(total_amount), 0) AS total_amount FROM sales WHERE DATE(created_at) = CURDATE()");
$liveSalesConfig = [
    'latest_id' => $latestSaleSnapshot['id'],
    'latest_sale_number' => $latestSaleSnapshot['sale_number'],
    'latest_total' => $latestSaleSnapshot['total_amount'],
    'latest_payment_method' => $latestSaleSnapshot['payment_method'],
    'latest_created_at' => $latestSaleSnapshot['created_at'],
    'today_count' => (int)($todaySalesStats['sale_count'] ?? 0),
    'today_total' => (float)($todaySalesStats['total_amount'] ?? 0),
];

$pageTitle = 'Point of Sale';
include 'includes/header.php';
?>

<style>
    .live-sales-card {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
        height: 100%;
    }
    .live-sales-metric {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
    }
    .live-sales-feed {
        max-height: 140px;
        overflow: hidden;
        padding-left: 1.25rem;
        margin-bottom: 0;
    }
    .live-sales-feed li {
        list-style: none;
        padding: 0.35rem 0;
        border-bottom: 1px dashed rgba(0,0,0,0.06);
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        font-size: 0.92rem;
    }
    .live-sales-feed li:last-child {
        border-bottom: none;
    }
    .live-sales-pill {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .live-sales-pill[data-method="cash"] {
        color: #0f5132;
        background: rgba(25,135,84,.15);
        border: 1px solid rgba(25,135,84,.2);
        border-radius: 999px;
        padding: 0.15rem 0.6rem;
    }
    .live-sales-pill[data-method="card"],
    .live-sales-pill[data-method="mobile_money"],
    .live-sales-pill[data-method="bank_transfer"] {
        color: #084298;
        background: rgba(13,110,253,.12);
        border: 1px solid rgba(13,110,253,.15);
        border-radius: 999px;
        padding: 0.15rem 0.6rem;
    }
    .live-sales-pill[data-method="room_charge"] {
        color: #664d03;
        background: rgba(255,193,7,.15);
        border: 1px solid rgba(255,193,7,.25);
        border-radius: 999px;
        padding: 0.15rem 0.6rem;
    }
    .live-sales-controls {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }
    .live-sales-status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #20c997;
        display: inline-block;
    }
    .live-sales-status-dot[data-state="paused"] {
        background: #dc3545;
    }
    .pos-shell {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-lg);
    }
    .pos-layout {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-lg);
        min-height: calc(100vh - 180px);
    }
    @media (min-width: 992px) {
        .pos-layout {
            flex-direction: row;
            align-items: stretch;
        }
    }
    .pos-column {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: var(--spacing-lg);
    }
    .pos-panel {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: var(--spacing-md);
    }
    .pos-panel .panel-body {
        flex: 1;
        overflow-y: auto;
    }
    .product-filters {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    @media (min-width: 576px) {
        .product-filters {
            flex-direction: row;
            align-items: center;
        }
        .product-filters > * {
            flex: 1;
        }
    }
    #productsList {
        display: grid;
        gap: var(--spacing-md);
    }
    .product-item {
        cursor: pointer;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .product-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
    }
    .product-meta {
        color: var(--color-text-muted);
        font-size: 0.85rem;
    }
    .product-actions {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xs);
    }
    @media (min-width: 576px) {
        .product-actions {
            flex-direction: row;
            align-items: center;
            justify-content: flex-end;
            gap: var(--spacing-sm);
        }
    }
    .cart-card {
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .cart-items {
        flex: 1;
        overflow-y: auto;
        border: 1px dashed var(--color-border);
        border-radius: var(--radius-md);
        padding: var(--spacing-md);
        background: rgba(13, 110, 253, 0.03);
    }
    .cart-empty {
        text-align: center;
        color: var(--color-text-muted);
        padding: var(--spacing-lg) 0;
    }
    .cart-summary {
        margin-top: var(--spacing-md);
        border-top: 1px solid var(--color-border);
        padding-top: var(--spacing-md);
    }
    .cart-line {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: var(--spacing-sm) var(--spacing-md);
        background: var(--color-surface);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    @media (min-width: 576px) {
        .cart-line {
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
        }
    }
    .cart-line-actions .btn {
        min-width: 36px;
    }
    .payment-section {
        border-top: 1px solid var(--color-border);
        margin-top: var(--spacing-md);
        padding-top: var(--spacing-md);
    }
    .payment-buttons .btn {
        font-weight: 600;
    }
    .held-order-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: var(--spacing-md);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
    }
    .scan-feedback {
        position: fixed;
        top: 1.5rem;
        right: 1.5rem;
        z-index: 1080;
        display: none;
        align-items: center;
        gap: var(--spacing-sm);
        padding: var(--spacing-sm) var(--spacing-md);
        border-radius: var(--radius-md);
        background: var(--color-surface);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--color-border);
        font-weight: 600;
    }
    .scan-feedback.show { display: inline-flex; }
    .scan-feedback[data-state="success"] { border-color: var(--color-success); color: var(--color-success); }
    .scan-feedback[data-state="error"] { border-color: var(--color-danger); color: var(--color-danger); }
    .loyalty-card {
        border: 1px dashed var(--color-border);
        background: var(--color-surface-alt);
        border-radius: var(--radius-md);
        padding: var(--spacing-md);
    }
</style>

<div class="scan-feedback" id="scanFeedback" role="status" aria-live="assertive">
    <i class="bi bi-upc-scan"></i>
    <span id="scanFeedbackText"></span>
</div>
<div class="container-fluid py-4 pos-shell">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="stack-sm">
            <h1 class="mb-0"><i class="bi bi-cart4 me-2"></i>Point of Sale</h1>
            <p class="text-muted mb-0">Search products, build a sale, and complete payments faster.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-outline-secondary btn-icon" onclick="showHeldOrders()">
                <i class="bi bi-list"></i><span>Held Orders</span>
            </button>
            <button class="btn btn-outline-danger btn-icon" onclick="clearCart()">
                <i class="bi bi-trash"></i><span>Clear Cart</span>
            </button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="app-card live-sales-card" aria-live="polite">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Today's Sales</p>
                        <div class="live-sales-metric">
                            <span class="fs-4 fw-semibold" id="liveSalesCount">0</span>
                            <span class="text-muted small">transactions</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <p class="text-muted mb-1 small">Value</p>
                        <div class="fs-5 fw-semibold" id="liveSalesValue">0.00</div>
                    </div>
                </div>
                <div class="bg-light p-3 rounded">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">Last ticket</small>
                            <div class="fw-semibold" id="liveSalesLastRef">—</div>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">Time</small>
                            <div class="fw-semibold small" id="liveSalesLastTime">—</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="app-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="live-sales-status-dot" id="liveSalesStatusDot"></div>
                        <span class="fw-semibold">Live Activity</span>
                    </div>
                    <div class="live-sales-controls">
                        <button class="btn btn-sm btn-outline-secondary" id="liveSalesToggleBtn" type="button">Pause</button>
                        <button class="btn btn-sm btn-outline-primary" id="liveSalesRefreshBtn" type="button">Refresh</button>
                    </div>
                </div>
                <ul class="live-sales-feed" id="liveSalesList">
                    <li class="text-muted justify-content-center">Waiting for updates…</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="pos-layout">
        <div class="pos-column">
            <div class="app-card pos-panel">
                <div class="section-heading">
                    <div>
                        <h5 class="mb-1">Product Catalog</h5>
                        <p class="text-muted small mb-0">Tap to add items to the cart. Filters update instantly.</p>
                    </div>
                </div>
                <div class="product-filters">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="searchProduct" class="form-control" placeholder="Search by name, SKU, or barcode">
                    </div>
                    <select id="filterCategory" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="panel-body" id="productsPanel">
                    <div id="productsList">
                        <?php foreach ($products as $product): ?>
                        <?php
                        $productPayload = json_encode([
                            'id' => (int)$product['id'],
                            'name' => $product['name'],
                            'selling_price' => (float)$product['selling_price'],
                            'stock_quantity' => (float)$product['stock_quantity'],
                            'tax_rate' => $product['tax_rate'] !== null ? (float)$product['tax_rate'] : null,
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                        <div class="product-item"
                             data-category="<?= (int)$product['category_id'] ?>"
                             data-name="<?= htmlspecialchars(strtolower($product['name']), ENT_QUOTES, 'UTF-8') ?>"
                             data-sku="<?= htmlspecialchars(strtolower($product['sku'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                             data-barcode="<?= htmlspecialchars(strtolower($product['barcode'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="mb-0">
                                            <?= htmlspecialchars($product['name']) ?>
                                        </h6>
                                        <?php if ($product['stock_quantity'] <= $product['min_stock_level']): ?>
                                            <span class="badge bg-warning text-dark">Low</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-meta">SKU: <?= htmlspecialchars($product['sku']) ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-semibold text-primary small">
                                        <?= formatMoney($product['selling_price']) ?>
                                    </div>
                                    <small class="text-muted">Stock: <?= $product['stock_quantity'] ?></small>
                                </div>
                            </div>
                            <div class="product-actions">
                                <button 
                                    type="button"
                                    class="btn btn-primary btn-sm add-to-cart-btn"
                                    data-product-id="<?= (int)$product['id'] ?>"
                                    data-product-name="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-product-price="<?= (float)$product['selling_price'] ?>"
                                    data-product-stock="<?= (float)$product['stock_quantity'] ?>"
                                    data-product-tax="<?= $product['tax_rate'] !== null ? (float)$product['tax_rate'] : '' ?>"
                                    data-product-json="<?= htmlspecialchars($productPayload, ENT_QUOTES, 'UTF-8') ?>"
                                    onclick="addToCartFromElement(this)"
                                >
                                    <i class="bi bi-cart-plus me-1"></i>Add to Cart
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="pos-column">
            <div class="app-card cart-card">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <h5 class="mb-1"><i class="bi bi-cart3 me-2"></i>Current Sale</h5>
                        <p class="text-muted small mb-0">Customer summary, cart contents, and payment controls.</p>
                    </div>
                    <span class="app-status" data-color="info" id="cartStatus">Ready</span>
                </div>

                <div class="row g-2">
                    <div class="col-lg-8">
                        <label for="customerName" class="form-label mb-1">Customer Name</label>
                        <input type="text" id="customerName" class="form-control form-control-sm" placeholder="Optional">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label mb-1">Items in Cart</label>
                        <div class="form-control form-control-sm bg-light" id="cartItemCount">0</div>
                    </div>
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-lg-8">
                        <div class="loyalty-card">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-stars text-warning"></i>
                                    <span class="fw-semibold">Loyalty</span>
                                </div>
                                <span class="badge-soft text-muted" id="loyaltyStatus">Not linked</span>
                            </div>
                            <label for="loyaltyCardNumber" class="form-label small text-muted mb-1">Card / Phone</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="loyaltyCardNumber" placeholder="Scan or type number" autocomplete="off">
                                <button class="btn btn-outline-primary" type="button" id="linkLoyaltyBtn" title="Link Loyalty">
                                    <i class="bi bi-link-45deg"></i>
                                </button>
                                <button class="btn btn-outline-secondary d-none" type="button" id="loyaltyClearBtn" title="Clear Loyalty">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                            <div class="d-flex flex-column gap-2 mt-2">
                                <div class="small" id="loyaltyBalance" aria-live="polite"></div>
                                <div class="d-flex flex-column gap-2">
                                    <label class="form-label small text-muted mb-1" for="loyaltyRedeemPoints">Redeem Points</label>
                                    <div class="d-flex gap-2">
                                        <input type="number" class="form-control form-control-sm" id="loyaltyRedeemPoints" placeholder="0" min="0" step="1" disabled>
                                        <button class="btn btn-outline-success btn-sm" type="button" id="loyaltyRedeemBtn" disabled>Apply</button>
                                        <button class="btn btn-outline-secondary btn-sm" type="button" id="loyaltyRedeemClearBtn" disabled>Clear</button>
                                    </div>
                                </div>
                                <div class="small text-muted" id="loyaltyDetails">Customer rewards will appear here.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="stack-sm">
                            <label class="form-label mb-1" for="customerPhone">Phone</label>
                            <input type="tel" id="customerPhone" class="form-control form-control-sm" placeholder="Optional">
                        </div>
                    </div>
                </div>

                <div class="cart-items" id="cartItems">
                    <div class="cart-empty">
                        <i class="bi bi-cart-x fs-1"></i>
                        <p class="mt-2 mb-0">Cart is empty</p>
                        <small>Click on products to add</small>
                    </div>
                </div>

                <div class="cart-summary">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <strong id="subtotal">0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Tax (<span id="taxRate">0</span>%)</span>
                        <strong id="taxAmount">0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2 d-none" id="loyaltyDiscountRow">
                        <span class="text-muted">Loyalty Discount</span>
                        <strong class="text-success" id="loyaltyDiscountAmount">-0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-top border-bottom my-2">
                        <span class="fw-semibold">Total Due</span>
                        <span class="h4 mb-0 text-primary" id="total">0.00</span>
                    </div>

                    <div class="payment-section">
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-6">
                                <label for="paymentMethod" class="form-label">Payment Method</label>
                                <select id="paymentMethod" class="form-select form-select-sm" onchange="handlePaymentMethodChange()">
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="mobile_money">Mobile Money</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="room_charge">Charge to Room</option>
                                </select>
                            </div>
                            <div class="col-lg-6" id="cashPaymentWrap">
                                <label for="amountTendered" class="form-label">Amount Tendered</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="bi bi-cash"></i></span>
                                    <input type="number"
                                           class="form-control"
                                           id="amountTendered"
                                           placeholder="0.00"
                                           step="0.01"
                                           min="0"
                                           oninput="calculateChange()"
                                           onkeypress="handleTenderedKeypress(event)">
                                </div>
                                <div class="mt-2" id="cashAlerts"></div>
                                <div class="mt-3 d-flex flex-wrap gap-2" id="quickTenderButtons">
                                    <button type="button" class="btn btn-outline-primary btn-sm quick-tender-btn" data-amount="100"><?= formatMoney(100); ?></button>
                                    <button type="button" class="btn btn-outline-primary btn-sm quick-tender-btn" data-amount="200"><?= formatMoney(200); ?></button>
                                    <button type="button" class="btn btn-outline-primary btn-sm quick-tender-btn" data-amount="500"><?= formatMoney(500); ?></button>
                                    <button type="button" class="btn btn-outline-primary btn-sm quick-tender-btn" data-amount="1000"><?= formatMoney(1000); ?></button>
                                </div>
                                <small class="text-muted">Tip: use Alt + number keys (1-4) for quick tender amounts.</small>
                            </div>
                            <div class="col-lg-6 d-none" id="mobileMoneyInputWrap">
                                <label for="mobileMoneyPhone" class="form-label">Customer Mobile Number</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                    <input type="tel"
                                           class="form-control"
                                           id="mobileMoneyPhone"
                                           placeholder="e.g. +2567..."
                                           autocomplete="tel"
                                           inputmode="tel">
                                </div>
                                <small class="text-muted">Use international format. The prompt will be sent to this number.</small>
                            </div>
                        </div>

                        <div class="row g-2 mt-2 d-none" id="mobileMoneyStatusWrap">
                            <div class="col-12">
                                <div id="mobileMoneyStatus" class="alert alert-info small mb-0"></div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1" id="roomChargeSection" style="display:none;">
                            <div class="col-12">
                                <?php if (!empty($checkedInRooms)): ?>
                                    <label for="roomBookingSelect" class="form-label">Select Checked-in Room</label>
                                    <select id="roomBookingSelect" class="form-select form-select-sm">
                                        <option value="">Select a checked-in room...</option>
                                        <?php foreach ($checkedInRooms as $room): ?>
                                            <option value="<?= (int) $room['id'] ?>"
                                                    data-label="<?= htmlspecialchars($room['label'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($room['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Charges will be posted to the selected room folio.</small>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-0">
                                        <div class="small mb-1"><i class="bi bi-exclamation-triangle me-1"></i>No checked-in rooms are available.</div>
                                        <div class="small mb-0">Check in a guest before charging orders to a room.</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12">
                                <label for="roomChargeDescription" class="form-label">Folio Note (optional)</label>
                                <input type="text" class="form-control form-control-sm" id="roomChargeDescription" maxlength="120" placeholder="e.g. Room service dinner">
                            </div>
                        </div>

                        <div class="payment-buttons mt-3">
                            <div class="d-grid gap-2">
                                <button class="btn btn-success btn-lg" onclick="processPayment()" id="processPaymentBtn" disabled>
                                    <i class="bi bi-credit-card me-2"></i>Process Payment
                                </button>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <button class="btn btn-outline-warning w-100" onclick="holdOrder()" id="holdOrderBtn" disabled>
                                            <i class="bi bi-pause-circle me-1"></i>Hold Order
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-outline-info w-100" onclick="showHeldOrders()" id="recallOrderBtn">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Recall Order
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Confirmation Modal -->
<div class="modal fade" id="paymentConfirmationModal" tabindex="-1" aria-labelledby="paymentConfirmationLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title" id="paymentConfirmationLabel"><i class="bi bi-receipt me-2 text-primary"></i>Payment Confirmation</h5>
                    <p class="text-muted small mb-0">Review the transaction details before completing the sale.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="app-card border-0 shadow-sm h-100">
                            <h6 class="text-muted text-uppercase small">Transaction Summary</h6>
                            <div class="d-flex flex-column gap-2 mt-3 small">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Subtotal</span>
                                    <span id="confirmSubtotal">0.00</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Tax</span>
                                    <span id="confirmTax">0.00</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center py-2 border-top">
                                    <strong>Total</strong>
                                    <strong id="confirmTotal" class="text-primary">0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Payment Method</span>
                                    <span id="confirmPaymentMethod">Cash</span>
                                </div>
                                <div class="d-flex justify-content-between d-none" id="confirmLoyaltyDiscountRow">
                                    <span class="text-muted">Loyalty Discount</span>
                                    <span id="confirmLoyaltyDiscount" class="text-success">-0.00</span>
                                </div>
                                <div class="d-flex justify-content-between d-none" id="confirmRoomChargeRow">
                                    <span class="text-muted">Charged To</span>
                                    <span id="confirmRoomCharge"></span>
                                </div>
                                <div class="d-flex justify-content-between" id="confirmAmountPaidRow">
                                    <span class="text-muted">Amount Paid</span>
                                    <span id="confirmAmountPaid">0.00</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center d-none" id="confirmChangeRow">
                                    <strong>Change Due</strong>
                                    <strong id="confirmChange" class="text-success">0.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="d-flex flex-column gap-3">
                            <div id="cashInstructions" class="app-card border-0 shadow-sm d-none">
                                <h6 class="text-muted text-uppercase small">Cash Handling</h6>
                                <ol class="mb-0 small mt-2 ps-3">
                                    <li>Collect payment from customer</li>
                                    <li>Place cash in drawer</li>
                                    <li>Count change carefully</li>
                                    <li>Give change to customer</li>
                                    <li>Provide receipt</li>
                                </ol>
                                <div id="changeBreakdown" class="mt-3 d-none">
                                    <h6 class="small text-muted text-uppercase mb-2">Change Breakdown</h6>
                                    <div id="changeDetails" class="small"></div>
                                </div>
                            </div>
                            <div id="cardInstructions" class="app-card border-0 shadow-sm d-none">
                                <h6 class="text-muted text-uppercase small"><i class="bi bi-credit-card me-2"></i>Card Payment</h6>
                                <p class="small mb-0">Process the card via your terminal and confirm the transaction before completing the sale.</p>
                            </div>
                            <div id="roomChargeInstructions" class="app-card border-0 shadow-sm d-none">
                                <h6 class="text-muted text-uppercase small"><i class="bi bi-door-open me-2"></i>Room Charge</h6>
                                <p class="small mb-0">Verify guest authorization and ensure folio notes reflect the purchase accurately.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="finalizePayment()">
                    <i class="bi bi-check-circle me-2"></i>Complete Transaction
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hold Order Modal -->
<div class="modal fade" id="holdOrderModal" tabindex="-1" aria-labelledby="holdOrderLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title" id="holdOrderLabel"><i class="bi bi-pause-circle me-2 text-warning"></i>Hold Current Order</h5>
                    <p class="small text-muted mb-0">Save this cart to resume later. Provide a reference to find it quickly.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="holdCustomerName" class="form-label">Customer Name or Reference<span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="holdCustomerName" placeholder="e.g. John Doe or Table 4">
                    <small class="text-muted">Required so the order can be recalled by staff.</small>
                </div>
                <div class="mb-3">
                    <label for="holdNotes" class="form-label">Notes (Optional)</label>
                    <textarea class="form-control" id="holdNotes" rows="2" placeholder="Mention dietary notes, pickup time, etc."></textarea>
                </div>
                <div class="app-card border-info-subtle">
                    <h6 class="text-muted text-uppercase small">Order Summary</h6>
                    <div id="holdOrderSummary" class="small mt-2"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="confirmHoldOrder()">
                    <i class="bi bi-pause-circle me-2"></i>Hold Order
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Held Orders Modal -->
<div class="modal fade" id="heldOrdersModal" tabindex="-1" aria-labelledby="heldOrdersLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title" id="heldOrdersLabel"><i class="bi bi-list-stars me-2 text-info"></i>Held Orders</h5>
                    <p class="small text-muted mb-0">Recall, process, or clear held carts from this list.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="heldOrdersList" class="d-flex flex-column gap-3">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 mb-0">Loading held orders...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
window.LIVE_SALES_CONFIG = <?= json_encode($liveSalesConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<script>
const CSRF_TOKEN = '<?= generateCSRFToken(); ?>';
let cart = [];
const TAX_RATE = 16; // Default tax rate percentage

// Currency configuration from PHP
const CURRENCY_CONFIG = <?= json_encode(CurrencyManager::getInstance()->getJavaScriptConfig()) ?>;
const CURRENCY_CODE = '<?= addslashes($currencyCode) ?>';
const MOBILE_MONEY_GATEWAY_ENABLED = <?= $isGatewayEnabled ? 'true' : 'false' ?>;
const MOBILE_MONEY_GATEWAY_PROVIDER = '<?= addslashes($gatewayProvider) ?>';
let mobileMoneyPollTimeout = null;
let mobileMoneyCurrentReference = null;
let mobileMoneyPollAttempts = 0;
const MOBILE_MONEY_POLL_INTERVAL = 4000;
const MOBILE_MONEY_POLL_MAX_ATTEMPTS = 30;
const FINALIZE_BUTTON_DEFAULT_HTML = '<i class="bi bi-check-circle me-2"></i>Complete Transaction';
const MOBILE_MONEY_SUCCESS_STATUSES = ['success', 'completed', 'paid', 'approved'];
const MOBILE_MONEY_FAILURE_STATUSES = ['failed', 'declined', 'cancelled', 'expired', 'error'];
const LIVE_SALES_CONFIG = window.LIVE_SALES_CONFIG || { latest_id: 0, today_count: 0, today_total: 0 };
const liveSalesElements = {
    count: null,
    value: null,
    lastRef: null,
    lastTime: null,
    list: null,
    statusDot: null,
    toggleBtn: null,
};
const liveSalesState = {
    latestId: LIVE_SALES_CONFIG.latest_id || 0,
    todayCount: LIVE_SALES_CONFIG.today_count || 0,
    todayTotal: LIVE_SALES_CONFIG.today_total || 0,
    paused: false,
    timer: null,
    channel: null,
};

const loyaltyState = {
    linked: false,
    customerId: null,
    customerName: null,
    programId: null,
    program: null,
    balance: 0,
    minRedemption: 0,
    pointsToRedeem: 0,
    discountAmount: 0,
    effectiveDiscount: 0,
    detailMessage: 'Customer rewards will appear here.'
};

function requestLoyalty(payload) {
    return fetch('api/loyalty.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    }).then(async response => {
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to process loyalty request.');
        }
        return data;
    });
}

function resetLoyaltyState(clearCardInput = false) {
    loyaltyState.linked = false;
    loyaltyState.customerId = null;
    loyaltyState.customerName = null;
    loyaltyState.programId = null;
    loyaltyState.program = null;
    loyaltyState.balance = 0;
    loyaltyState.minRedemption = 0;
    loyaltyState.pointsToRedeem = 0;
    loyaltyState.discountAmount = 0;
    loyaltyState.effectiveDiscount = 0;
    loyaltyState.detailMessage = 'Customer rewards will appear here.';
    if (clearCardInput) {
        const cardInput = document.getElementById('loyaltyCardNumber');
        if (cardInput) {
            cardInput.value = '';
        }
    }
    const redeemInput = document.getElementById('loyaltyRedeemPoints');
    if (redeemInput) {
        redeemInput.value = '';
    }
    updateLoyaltyUI();
    updateCart();
}

function updateLoyaltyUI(message = null, tone = 'muted') {
    const statusEl = document.getElementById('loyaltyStatus');
    if (statusEl) {
        statusEl.textContent = loyaltyState.linked ? 'Linked' : 'Not linked';
        statusEl.classList.toggle('text-success', loyaltyState.linked);
        statusEl.classList.toggle('text-muted', !loyaltyState.linked);
    }

    const balanceEl = document.getElementById('loyaltyBalance');
    if (balanceEl) {
        if (loyaltyState.linked && loyaltyState.program) {
            const approx = loyaltyState.balance * parseFloat(loyaltyState.program.redemption_rate || 0);
            balanceEl.textContent = `Balance: ${loyaltyState.balance} pts (~${formatCurrency(approx)})`;
        } else {
            balanceEl.textContent = 'Link a customer to view loyalty balance.';
        }
    }

    const detailsEl = document.getElementById('loyaltyDetails');
    if (detailsEl) {
        const text = message ?? loyaltyState.detailMessage;
        detailsEl.textContent = text;
        detailsEl.classList.remove('text-danger', 'text-success');
        detailsEl.classList.add(`text-${tone}`);
    }

    const clearBtn = document.getElementById('loyaltyClearBtn');
    if (clearBtn) {
        clearBtn.classList.toggle('d-none', !loyaltyState.linked);
        clearBtn.disabled = !loyaltyState.linked;
    }

    const redeemInput = document.getElementById('loyaltyRedeemPoints');
    const redeemBtn = document.getElementById('loyaltyRedeemBtn');
    const redeemClearBtn = document.getElementById('loyaltyRedeemClearBtn');

    if (redeemInput) {
        redeemInput.disabled = !loyaltyState.linked;
        redeemInput.placeholder = loyaltyState.linked && loyaltyState.minRedemption > 0
            ? `Min ${loyaltyState.minRedemption}`
            : '0';
    }
    if (redeemBtn) {
        const currentValue = redeemInput ? parseInt(redeemInput.value || '0', 10) : 0;
        redeemBtn.disabled = !loyaltyState.linked || !Number.isFinite(currentValue) || currentValue <= 0;
    }
    if (redeemClearBtn) {
        redeemClearBtn.disabled = loyaltyState.pointsToRedeem === 0;
    }
}

function setLoyaltyLoading(isLoading) {
    const linkBtn = document.getElementById('linkLoyaltyBtn');
    if (linkBtn) {
        linkBtn.disabled = isLoading;
        linkBtn.innerHTML = isLoading ? '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>' : '<i class="bi bi-link-45deg"></i>';
    }
    const redeemBtn = document.getElementById('loyaltyRedeemBtn');
    if (redeemBtn && isLoading) {
        redeemBtn.disabled = true;
    }
}

function getLoyaltyDiscountValue(subtotal, taxAmount) {
    if (!loyaltyState.linked || loyaltyState.discountAmount <= 0) {
        loyaltyState.effectiveDiscount = 0;
        return 0;
    }
    const maxDiscount = Math.max(0, subtotal + taxAmount);
    const discount = Math.min(loyaltyState.discountAmount, maxDiscount);
    loyaltyState.effectiveDiscount = discount;
    return discount;
}

async function handleLoyaltyLink() {
    const identifierInput = document.getElementById('loyaltyCardNumber');
    if (!identifierInput) {
        return;
    }
    const identifier = identifierInput.value.trim();
    if (!identifier) {
        alert('Enter a card or phone number to link.');
        return;
    }
    setLoyaltyLoading(true);
    try {
        const data = await requestLoyalty({ action: 'link', identifier });
        loyaltyState.linked = true;
        loyaltyState.customerId = parseInt(data.customer?.id ?? 0, 10) || null;
        loyaltyState.customerName = data.customer?.name || null;
        loyaltyState.programId = parseInt(data.program?.id ?? 0, 10) || null;
        loyaltyState.program = data.program || null;
        loyaltyState.balance = parseInt(data.enrollment?.points_balance ?? 0, 10) || 0;
        loyaltyState.minRedemption = parseInt(data.program?.min_points_redemption ?? 0, 10) || 0;
        loyaltyState.pointsToRedeem = 0;
        loyaltyState.discountAmount = 0;
        loyaltyState.effectiveDiscount = 0;
        const lastActivity = (data.history || [])[0];
        if (lastActivity) {
            const activityDate = new Date(lastActivity.created_at);
            loyaltyState.detailMessage = `${lastActivity.transaction_type} ${lastActivity.points} pts on ${activityDate.toLocaleString()}`;
        } else {
            loyaltyState.detailMessage = 'Linked customer has no loyalty transactions yet.';
        }
        updateLoyaltyUI();
        updateCart();
    } catch (error) {
        console.error('Loyalty link error:', error);
        updateLoyaltyUI(error.message || 'Unable to link loyalty.', 'danger');
        alert(error.message || 'Failed to link loyalty customer.');
    } finally {
        setLoyaltyLoading(false);
    }
}

function clearLoyaltyLink() {
    resetLoyaltyState(false);
}

async function applyLoyaltyRedemption() {
    if (!loyaltyState.linked || !loyaltyState.customerId) {
        alert('Link a loyalty customer first.');
        return;
    }
    const input = document.getElementById('loyaltyRedeemPoints');
    if (!input) {
        return;
    }
    const points = parseInt(input.value || '0', 10);
    if (!Number.isFinite(points) || points <= 0) {
        alert('Enter the number of points to redeem.');
        return;
    }
    if (points > loyaltyState.balance) {
        alert('Customer does not have enough points.');
        return;
    }
    if (loyaltyState.minRedemption > 0 && points < loyaltyState.minRedemption) {
        alert(`Minimum redemption is ${loyaltyState.minRedemption} points.`);
        return;
    }
    try {
        setLoyaltyLoading(true);
        const data = await requestLoyalty({
            action: 'preview',
            customer_id: loyaltyState.customerId,
            program_id: loyaltyState.programId,
            points
        });
        const preview = data.preview || {};
        loyaltyState.pointsToRedeem = points;
        loyaltyState.discountAmount = parseFloat(preview.value ?? 0) || 0;
        loyaltyState.detailMessage = `Redeeming ${points} pts for ${formatCurrency(loyaltyState.discountAmount)} discount.`;
        updateLoyaltyUI('Redemption applied.', 'success');
        updateCart();
    } catch (error) {
        console.error('Loyalty preview error:', error);
        updateLoyaltyUI(error.message || 'Unable to preview redemption.', 'danger');
        alert(error.message || 'Failed to preview redemption.');
    } finally {
        setLoyaltyLoading(false);
    }
}

function clearLoyaltyRedemption() {
    loyaltyState.pointsToRedeem = 0;
    loyaltyState.discountAmount = 0;
    loyaltyState.effectiveDiscount = 0;
    const input = document.getElementById('loyaltyRedeemPoints');
    if (input) {
        input.value = '';
    }
    updateLoyaltyUI('Redemption cleared.', 'muted');
    updateCart();
}

// Currency formatting function
function formatCurrency(amount) {
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: CURRENCY_CONFIG.decimal_places,
        maximumFractionDigits: CURRENCY_CONFIG.decimal_places
    }).format(amount);
    
    return formatted; // Return amount only, no currency symbol
}

function formatPaymentMethodLabel(method) {
    switch (method) {
        case 'cash':
            return 'Cash';
        case 'card':
            return 'Card';
        case 'mobile_money':
            return 'Mobile Money';
        case 'bank_transfer':
            return 'Bank Transfer';
        case 'room_charge':
            return 'Room Charge';
        default:
            return method.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
    }
}

function refreshPaymentUIState() {
    const paymentMethod = document.getElementById('paymentMethod').value;
    const processBtn = document.getElementById('processPaymentBtn');
    const holdBtn = document.getElementById('holdOrderBtn');
    const paymentModal = document.getElementById('paymentConfirmationModal');
    if (paymentModal) {
        paymentModal.addEventListener('hide.bs.modal', () => {
            const activeElement = document.activeElement;
            if (activeElement && paymentModal.contains(activeElement)) {
                activeElement.blur();
            }
            setTimeout(() => {
                document.getElementById('customerName')?.focus();
            }, 0);
        });
    }

    const roomSelect = document.getElementById('roomBookingSelect');
    const cartHasItems = cart.length > 0;

    if (holdBtn) {
        holdBtn.disabled = !cartHasItems;
    }

    if (!processBtn) {
        return;
    }

    if (paymentMethod === 'cash') {
        calculateChange();
        return;
    }

    if (paymentMethod === 'room_charge') {
        const hasRooms = roomSelect && roomSelect.options.length > 1;
        const hasSelection = roomSelect && roomSelect.value !== '';
        processBtn.disabled = !cartHasItems || !hasRooms || !hasSelection;
        return;
    }

    processBtn.disabled = !cartHasItems;
}

// Search products
document.getElementById('searchProduct').addEventListener('input', filterProducts);
document.getElementById('filterCategory').addEventListener('change', filterProducts);

function filterProducts() {
    const searchTerm = document.getElementById('searchProduct').value.toLowerCase();
    const categoryFilter = document.getElementById('filterCategory').value;
    const products = document.querySelectorAll('#productsList .product-item');

    products.forEach(product => {
        const name = product.dataset.name;
        const sku = product.dataset.sku ?? '';
        const barcode = product.dataset.barcode ?? '';
        const category = product.dataset.category;

        const matchesSearch = name.includes(searchTerm) || sku.includes(searchTerm) || barcode.includes(searchTerm);
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

    const taxAttr = dataset.productTax ?? '';
    return {
        id: Number.parseInt(dataset.productId, 10),
        name: dataset.productName,
        selling_price: parseFloat(dataset.productPrice),
        stock_quantity: parseFloat(dataset.productStock),
        tax_rate: taxAttr === '' ? null : parseFloat(dataset.productTax)
    };
}

function addToCartFromElement(element) {
    if (!element || !element.dataset) {
        return;
    }

    let product = buildProductFromDataset(element.dataset);

    if ((!product || !product.name) && element.getAttribute) {
        const payload = element.getAttribute('data-product-json');
        if (payload) {
            try {
                product = JSON.parse(payload);
            } catch (error) {
                console.error('Failed to parse product payload attribute', error, payload);
            }
        }
    }

    if (!product.name || Number.isNaN(product.id) || Number.isNaN(product.selling_price)) {
        console.error('Invalid product dataset', element.dataset);
        return;
    }

    console.debug('Adding product to cart', product);
    addToCart(product);
}

function addToCart(product) {
    const existing = cart.find(item => item.id === product.id);
    
    if (existing) {
        if (existing.quantity < product.stock_quantity) {
            existing.quantity++;
            existing.total = existing.quantity * existing.price;
        } else {
            alert('Not enough stock available');
            return;
        }
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: parseFloat(product.selling_price),
            quantity: 1,
            stock: product.stock_quantity,
            total: parseFloat(product.selling_price),
            tax_rate: parseFloat(product.tax_rate) || 0
        });
    }
    
    updateCart();
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
    
    if (newQty > item.stock) {
        alert('Not enough stock available');
        return;
    }
    
    item.quantity = newQty;
    item.total = item.quantity * item.price;
    updateCart();
}

function updateCart() {
    const cartDiv = document.getElementById('cartItems');
    const cartCountEl = document.getElementById('cartItemCount');
    const cartStatusEl = document.getElementById('cartStatus');

    if (!cartDiv) {
        return;
    }

    if (cart.length === 0) {
        cartDiv.innerHTML = `
            <div class="cart-empty">
                <i class="bi bi-cart-x fs-1"></i>
                <p class="mt-2 mb-0">Cart is empty</p>
                <small>Click on products to add</small>
            </div>
        `;
        if (cartStatusEl) {
            cartStatusEl.textContent = 'Ready';
            cartStatusEl.setAttribute('data-color', 'info');
        }
    } else {
        let html = '<div class="d-flex flex-column gap-2">';
        cart.forEach((item, index) => {
            html += `
                <div class="cart-line">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold">${item.name}</div>
                            <div class="text-muted small">${formatCurrency(item.price)} × ${item.quantity}</div>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${index})" aria-label="Remove ${item.name}">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center gap-3">
                        <div class="btn-group btn-group-sm cart-line-actions" role="group" aria-label="Adjust quantity for ${item.name}">
                            <button class="btn btn-outline-secondary" onclick="updateQuantity(${index}, -1)">-</button>
                            <button class="btn btn-outline-secondary" disabled>${item.quantity}</button>
                            <button class="btn btn-outline-secondary" onclick="updateQuantity(${index}, 1)">+</button>
                        </div>
                        <strong>${formatCurrency(item.total)}</strong>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        cartDiv.innerHTML = html;
        if (cartStatusEl) {
            cartStatusEl.textContent = 'Building Order';
            cartStatusEl.setAttribute('data-color', 'primary');
        }
    }

    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    if (cartCountEl) {
        cartCountEl.textContent = totalItems;
    }

    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const taxAmount = subtotal * (TAX_RATE / 100);
    const total = subtotal + taxAmount;

    document.getElementById('subtotal').textContent = formatCurrency(subtotal);
    document.getElementById('taxRate').textContent = TAX_RATE;
    document.getElementById('taxAmount').textContent = formatCurrency(taxAmount);
    document.getElementById('total').textContent = formatCurrency(total);

    refreshPaymentUIState();
    updateHoldOrderButton();
}

function clearCart() {
    if (cart.length > 0 && confirm('Clear all items from cart?')) {
        cart = [];
        updateCart();
    }
}

// Payment method change handler
function isGatewayMobileMoneyEnabled() {
    return MOBILE_MONEY_GATEWAY_ENABLED === true;
}

function setMobileMoneyStatus(message, type = 'info') {
    const wrap = document.getElementById('mobileMoneyStatusWrap');
    const statusEl = document.getElementById('mobileMoneyStatus');
    if (!wrap || !statusEl) {
        return;
    }
    wrap.classList.remove('d-none');
    wrap.style.display = 'block';
    statusEl.className = `alert alert-${type} small mb-0`;
    statusEl.textContent = message;
}

function clearMobileMoneyStatus() {
    const wrap = document.getElementById('mobileMoneyStatusWrap');
    const statusEl = document.getElementById('mobileMoneyStatus');
    if (wrap) {
        wrap.classList.add('d-none');
        wrap.style.display = 'none';
    }
    if (statusEl) {
        statusEl.textContent = '';
    }
    if (mobileMoneyPollTimeout) {
        clearTimeout(mobileMoneyPollTimeout);
        mobileMoneyPollTimeout = null;
    }
    mobileMoneyCurrentReference = null;
    mobileMoneyPollAttempts = 0;
}

function getFinalizeButton() {
    return document.querySelector('#paymentConfirmationModal .btn-success');
}

function setFinalizeButton(label, disabled = false) {
    const btn = getFinalizeButton();
    if (!btn) {
        return null;
    }
    if (label) {
        btn.innerHTML = label;
    }
    btn.disabled = disabled;
    return btn;
}

function resetFinalizeButton() {
    setFinalizeButton(FINALIZE_BUTTON_DEFAULT_HTML, false);
}

function normalizeGatewayStatus(status) {
    return (status || '').toString().trim().toLowerCase();
}

function isMobileMoneySuccess(status) {
    return MOBILE_MONEY_SUCCESS_STATUSES.includes(normalizeGatewayStatus(status));
}

function isMobileMoneyFailure(status) {
    return MOBILE_MONEY_FAILURE_STATUSES.includes(normalizeGatewayStatus(status));
}

async function initiateMobileMoneyPayment(payment) {
    if (!payment.mobileMoneyPhone) {
        alert('Customer phone number is required for mobile money payments.');
        resetFinalizeButton();
        return;
    }

    try {
        setMobileMoneyStatus('Sending mobile money prompt to customer...', 'info');
        setFinalizeButton('<i class="bi bi-phone me-2"></i>Waiting for customer approval', true);

        const contextId = payment.contextId || Date.now();
        payment.contextId = contextId;

        const payload = {
            amount: payment.total,
            currency: CURRENCY_CODE,
            customer_phone: payment.mobileMoneyPhone,
            customer_name: payment.customerName || null,
            customer_email: payment.customerEmail || null,
            metadata: {
                source: 'pos',
                customer_contact_phone: payment.customerContactPhone || null,
                cart_items: cart.map(item => ({
                    product_id: item.id,
                    name: item.name,
                    qty: item.quantity,
                    price: item.price,
                    total: item.total
                }))
            },
            context_type: 'pos_sale',
            context_id: contextId
        };

        const response = await fetch('api/payments/initiate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Unable to initiate payment');
        }

        const data = result.data || {};
        payment.gatewayReference = data.reference;
        mobileMoneyCurrentReference = data.reference;
        mobileMoneyPollAttempts = 0;

        if (data.instructions) {
            setMobileMoneyStatus(data.instructions, 'info');
        } else {
            setMobileMoneyStatus('Prompt sent. Waiting for customer confirmation...', 'info');
        }

        const initialStatus = normalizeGatewayStatus(data.status);
        if (isMobileMoneySuccess(initialStatus)) {
            await finalizePayment(true);
            return;
        }

        pollMobileMoneyStatus();
    } catch (error) {
        console.error('Mobile money initiation error:', error);
        setMobileMoneyStatus(`Failed to initiate payment: ${error.message}`, 'danger');
        resetFinalizeButton();
    }
}

async function pollMobileMoneyStatus() {
    if (!mobileMoneyCurrentReference) {
        return;
    }

    mobileMoneyPollAttempts += 1;
    if (mobileMoneyPollAttempts > MOBILE_MONEY_POLL_MAX_ATTEMPTS) {
        setMobileMoneyStatus('Payment is still pending. Please confirm with the customer or try again.', 'warning');
        resetFinalizeButton();
        return;
    }

    try {
        const response = await fetch(`api/payments/status.php?reference=${encodeURIComponent(mobileMoneyCurrentReference)}`);
        const result = await response.json();

        if (!result.success || !result.data) {
            throw new Error(result.message || 'Unable to fetch payment status');
        }

        const record = result.data;
        const status = normalizeGatewayStatus(record.status);

        if (isMobileMoneySuccess(status)) {
            setMobileMoneyStatus('Payment confirmed! Completing sale...', 'success');
            await finalizePayment(true);
            return;
        }

        if (isMobileMoneyFailure(status)) {
            setMobileMoneyStatus(`Payment failed: ${record.status}`, 'danger');
            resetFinalizeButton();
            return;
        }

        if (record.instructions) {
            setMobileMoneyStatus(record.instructions, 'info');
        } else {
            setMobileMoneyStatus('Awaiting customer confirmation...', 'info');
        }

        mobileMoneyPollTimeout = setTimeout(() => {
            pollMobileMoneyStatus();
        }, MOBILE_MONEY_POLL_INTERVAL);
    } catch (error) {
        console.error('Mobile money status error:', error);
        setMobileMoneyStatus(`Error checking payment status: ${error.message}`, 'danger');
        resetFinalizeButton();
    }
}

function handlePaymentMethodChange() {
    const paymentMethod = document.getElementById('paymentMethod').value;
    const cashWrap = document.getElementById('cashPaymentWrap');
    const cashAlerts = document.getElementById('cashAlerts');
    const roomChargeSection = document.getElementById('roomChargeSection');
    const mobileMoneyInputWrap = document.getElementById('mobileMoneyInputWrap');
    const mobileMoneyStatusWrap = document.getElementById('mobileMoneyStatusWrap');

    if (paymentMethod === 'cash') {
        if (cashWrap) {
            cashWrap.style.display = '';
        }
        if (roomChargeSection) {
            roomChargeSection.style.display = 'none';
        }
        if (mobileMoneyInputWrap) {
            mobileMoneyInputWrap.style.display = 'none';
        }
        if (mobileMoneyStatusWrap) {
            mobileMoneyStatusWrap.style.display = 'none';
        }
        const processBtn = document.getElementById('processPaymentBtn');
        if (processBtn) {
            processBtn.innerHTML = '<i class="bi bi-cash me-2"></i>Process Cash Payment';
        }
    } else {
        if (cashWrap) {
            cashWrap.style.display = 'none';
        }
        if (cashAlerts) {
            cashAlerts.innerHTML = '';
            cashAlerts.style.display = 'none';
        }

        const mobileMoneyGatewayActive = paymentMethod === 'mobile_money' && isGatewayMobileMoneyEnabled();
        if (paymentMethod === 'mobile_money') {
            mobileMoneyInputWrap?.classList.remove('d-none');
            mobileMoneyInputWrap && (mobileMoneyInputWrap.style.display = 'block');
            if (mobileMoneyStatusWrap) {
                mobileMoneyStatusWrap.classList.toggle('d-none', !mobileMoneyGatewayActive);
                mobileMoneyStatusWrap.style.display = mobileMoneyGatewayActive ? 'block' : 'none';
                const infoEl = document.getElementById('mobileMoneyStatus');
                if (infoEl) {
                    infoEl.textContent = mobileMoneyGatewayActive
                        ? 'A mobile money prompt will be sent to the customer phone. Await confirmation.'
                        : 'Record the transaction reference after the customer confirms payment.';
                }
            }
            if (!mobileMoneyGatewayActive) {
                clearMobileMoneyStatus();
            }
        } else {
            mobileMoneyInputWrap?.classList.add('d-none');
            if (mobileMoneyInputWrap) mobileMoneyInputWrap.style.display = 'none';
            mobileMoneyStatusWrap?.classList.add('d-none');
            if (mobileMoneyStatusWrap) mobileMoneyStatusWrap.style.display = 'none';
            clearMobileMoneyStatus();
        }

        if (paymentMethod === 'room_charge') {
            if (roomChargeSection) {
                roomChargeSection.style.display = 'flex';
            }
            const processBtn = document.getElementById('processPaymentBtn');
            if (processBtn) {
                processBtn.innerHTML = '<i class="bi bi-door-open me-2"></i>Charge to Room';
            }
        } else {
            if (roomChargeSection) {
                roomChargeSection.style.display = 'none';
            }
            const processBtn = document.getElementById('processPaymentBtn');
            if (processBtn) {
                const label = formatPaymentMethodLabel(paymentMethod);
                processBtn.innerHTML = '<i class="bi bi-credit-card me-2"></i>Process ' + label + ' Payment';
            }
        }
    }

    refreshPaymentUIState();
}

// Calculate change and update display
function calculateChange() {
    const paymentMethod = document.getElementById('paymentMethod').value;
    if (paymentMethod !== 'cash') {
        return;
    }

    const amountField = document.getElementById('amountTendered');
    const alerts = document.getElementById('cashAlerts');
    const processBtn = document.getElementById('processPaymentBtn');

    if (!amountField || !alerts || !processBtn) {
        return;
    }

    const total = cart.reduce((sum, item) => sum + item.total, 0) * (1 + TAX_RATE / 100);
    const tendered = parseFloat(amountField.value) || 0;
    const change = tendered - total;

    alerts.innerHTML = '';
    alerts.style.display = 'none';

    if (tendered === 0) {
        processBtn.disabled = true;
        return;
    }

    if (change >= 0) {
        const alertClass = change === 0 ? 'alert-info' : 'alert-success';
        const title = change === 0 ? 'Exact Amount Received' : 'Change Due';
        const value = change === 0 ? '' : `<strong>${formatCurrency(change)}</strong>`;

        alerts.innerHTML = `
            <div class="alert ${alertClass} mb-0">
                <div class="d-flex justify-content-between align-items-center gap-3">
                    <strong>${title}</strong>
                    ${value}
                </div>
            </div>
        `;
        alerts.style.display = 'block';
        processBtn.disabled = false;
    } else {
        alerts.innerHTML = `
            <div class="alert alert-danger mb-0">
                <div class="d-flex justify-content-between align-items-center gap-3">
                    <small>Insufficient amount tendered</small>
                    <small class="fw-semibold">Needs ${formatCurrency(total)}</small>
                </div>
            </div>
        `;
        alerts.style.display = 'block';
        processBtn.disabled = true;
    }
}

function applyQuickTender(amount) {
    const paymentMethodSelect = document.getElementById('paymentMethod');
    if (paymentMethodSelect && paymentMethodSelect.value !== 'cash') {
        paymentMethodSelect.value = 'cash';
        handlePaymentMethodChange();
    }

    const tenderField = document.getElementById('amountTendered');
    if (!tenderField) {
        return;
    }

    tenderField.value = Number(amount).toFixed(2);
    calculateChange();
    tenderField.focus();
    tenderField.select();
}

// Handle Enter key in amount tendered field
function handleTenderedKeypress(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        const processBtn = document.getElementById('processPaymentBtn');
        if (!processBtn.disabled) {
            processPayment();
        }
    }
}

// Process payment with proper validation
function processPayment() {
    if (cart.length === 0) {
        alert('Cart is empty');
        return;
    }
    
    const paymentMethod = document.getElementById('paymentMethod').value;
    const customerNameInput = document.getElementById('customerName');
    const customerNameValue = customerNameInput ? customerNameInput.value.trim() : '';
    const contactPhoneInput = document.getElementById('customerPhone');
    const contactPhoneValue = contactPhoneInput ? contactPhoneInput.value.trim() : '';
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const taxAmount = subtotal * (TAX_RATE / 100);
    const total = subtotal + taxAmount;
    
    let amountPaid = total;
    let changeAmount = 0;
    let roomBookingId = null;
    let roomChargeDescription = null;
    let roomBookingLabel = null;
    
    // Handle cash payments with tendered amount
    if (paymentMethod === 'cash') {
        const tendered = parseFloat(document.getElementById('amountTendered').value) || 0;
        
        if (tendered < total) {
            alert('Insufficient amount tendered. Please enter at least ' + formatCurrency(total));
            document.getElementById('amountTendered').focus();
            return;
        }
        
        amountPaid = tendered;
        changeAmount = tendered - total;
    } else if (paymentMethod === 'room_charge') {
        const roomSelect = document.getElementById('roomBookingSelect');
        if (!roomSelect || roomSelect.options.length <= 1) {
            alert('There are no checked-in rooms available to charge.');
            return;
        }

        const selectedRoom = roomSelect.value;
        if (!selectedRoom) {
            alert('Please select a checked-in room to charge this order to.');
            return;
        }

        roomBookingId = parseInt(selectedRoom, 10);
        if (!Number.isFinite(roomBookingId) || roomBookingId <= 0) {
            alert('Invalid room selection.');
            return;
        }

        const selectedOption = roomSelect.options[roomSelect.selectedIndex];
        roomBookingLabel = selectedOption ? (selectedOption.dataset.label || selectedOption.textContent.trim()) : 'Selected room';
        const descriptionField = document.getElementById('roomChargeDescription');
        roomChargeDescription = descriptionField ? descriptionField.value.trim() : '';

        amountPaid = 0;
        changeAmount = 0;
    }
    
    // Show payment confirmation modal
    const extras = {
        customerName: customerNameValue || null,
        customerContactPhone: contactPhoneValue || null,
    };
    if (paymentMethod === 'room_charge') {
        extras.roomBookingId = roomBookingId;
        extras.roomChargeDescription = roomChargeDescription;
        extras.roomBookingLabel = roomBookingLabel;
    }
    if (paymentMethod === 'mobile_money') {
        const phoneInput = document.getElementById('mobileMoneyPhone');
        const phoneValue = phoneInput ? phoneInput.value.trim() : '';
        if (phoneValue.length < 7) {
            alert('Please provide the customer phone number to send the payment prompt.');
            phoneInput?.focus();
            return;
        }
        extras.mobileMoneyPhone = phoneValue;
        if (isGatewayMobileMoneyEnabled()) {
            amountPaid = 0;
            changeAmount = 0;
        }
    }

    showPaymentConfirmation(subtotal, taxAmount, total, paymentMethod, amountPaid, changeAmount, extras);
}

// Show payment confirmation modal
function showPaymentConfirmation(subtotal, taxAmount, total, paymentMethod, amountPaid, changeAmount, extras = {}) {
    // Update modal content
    document.getElementById('confirmSubtotal').textContent = formatCurrency(subtotal);
    document.getElementById('confirmTax').textContent = formatCurrency(taxAmount);
    document.getElementById('confirmTotal').textContent = formatCurrency(total);
    document.getElementById('confirmPaymentMethod').textContent = formatPaymentMethodLabel(paymentMethod);
    document.getElementById('confirmAmountPaid').textContent = formatCurrency(amountPaid);

    // Handle change display
    const changeRow = document.getElementById('confirmChangeRow');
    if (changeAmount > 0) {
        changeRow.style.display = 'table-row';
        document.getElementById('confirmChange').textContent = formatCurrency(changeAmount);
        
        // Show change breakdown for cash payments
        if (paymentMethod === 'cash') {
            showChangeBreakdown(changeAmount);
        }
    } else {
        changeRow.style.display = 'none';
        hideChangeBreakdown();
    }

    // Show appropriate instructions
    const cashInstructions = document.getElementById('cashInstructions');
    const cardInstructions = document.getElementById('cardInstructions');
    const roomChargeInstructions = document.getElementById('roomChargeInstructions');
    const roomChargeRow = document.getElementById('confirmRoomChargeRow');
    const amountPaidRow = document.getElementById('confirmAmountPaidRow');

    const showElement = el => {
        if (!el) return;
        el.classList.remove('d-none');
        el.style.display = '';
    };
    const hideElement = el => {
        if (!el) return;
        el.classList.add('d-none');
        el.style.display = 'none';
    };

    if (paymentMethod === 'cash') {
        showElement(cashInstructions);
        hideElement(cardInstructions);
        hideElement(roomChargeInstructions);
        roomChargeRow.classList.add('d-none');
        roomChargeRow.style.display = 'none';
        amountPaidRow.classList.remove('d-none');
        amountPaidRow.style.display = '';
        showChangeBreakdown(changeAmount);
    } else if (paymentMethod === 'room_charge') {
        hideElement(cashInstructions);
        hideElement(cardInstructions);
        showElement(roomChargeInstructions);
        if (extras.roomBookingLabel) {
            document.getElementById('confirmRoomCharge').textContent = extras.roomBookingLabel;
            roomChargeRow.classList.remove('d-none');
            roomChargeRow.style.display = '';
        } else {
            roomChargeRow.classList.add('d-none');
            roomChargeRow.style.display = 'none';
        }
        amountPaidRow.classList.add('d-none');
        amountPaidRow.style.display = 'none';
    } else {
        hideElement(cashInstructions);
        showElement(cardInstructions);
        hideElement(roomChargeInstructions);
        roomChargeRow.classList.add('d-none');
        roomChargeRow.style.display = 'none';
        amountPaidRow.classList.remove('d-none');
        amountPaidRow.style.display = '';
    }

    window.pendingPayment = {
        subtotal,
        taxAmount,
        total,
        paymentMethod,
        amountPaid,
        changeAmount,
        ...extras
    };

    // Show modal
    new bootstrap.Modal(document.getElementById('paymentConfirmationModal')).show();
}

// Show change breakdown
function showChangeBreakdown(changeAmount) {
    const denominations = [1000, 500, 200, 100, 50, 20, 10, 5, 1];
    let remaining = Math.round(changeAmount);
    let breakdown = [];
    
    for (let denom of denominations) {
        if (remaining >= denom) {
            const count = Math.floor(remaining / denom);
            breakdown.push(`${count} × ${formatCurrency(denom)}`);
            remaining -= count * denom;
        }
    }
    
    if (breakdown.length > 0) {
        document.getElementById('changeBreakdown').style.display = 'block';
        document.getElementById('changeDetails').innerHTML = breakdown.join('<br>');
    }
}

function hideChangeBreakdown() {
    const breakdown = document.getElementById('changeBreakdown');
    if (breakdown) {
        breakdown.style.display = 'none';
        const details = document.getElementById('changeDetails');
        if (details) {
            details.innerHTML = '';
        }
    }
}

// Finalize payment
async function finalizePayment() {
    const payment = window.pendingPayment;
    if (!payment) {
        alert('No pending payment found');
        return;
    }
    
    const customerName = document.getElementById('customerName').value || null;
    
    const saleData = {
        csrf_token: CSRF_TOKEN,
        customer_name: customerName,
        payment_method: payment.paymentMethod,
        subtotal: payment.subtotal,
        tax_amount: payment.taxAmount,
        total_amount: payment.total,
        amount_paid: payment.amountPaid,
        change_amount: payment.changeAmount,
        items: cart.map(item => ({
            product_id: item.id,
            qty: item.quantity,
            price: item.price,
            tax_rate: item.tax_rate,
            discount: item.discount ?? 0
        })),
        totals: {
            subtotal: payment.subtotal,
            tax: payment.taxAmount,
            discount: 0,
            grand: payment.total
        }
    };

    if (payment.paymentMethod === 'room_charge') {
        saleData.room_booking_id = payment.roomBookingId;
        if (payment.roomChargeDescription) {
            saleData.room_charge_description = payment.roomChargeDescription;
        }
    }
    if (payment.paymentMethod === 'mobile_money' && payment.gatewayReference) {
        saleData.gateway_reference = payment.gatewayReference;
    }
    
    // Disable button to prevent double-clicking
    const finalizeBtn = getFinalizeButton();
    const originalText = finalizeBtn ? finalizeBtn.innerHTML : FINALIZE_BUTTON_DEFAULT_HTML;
    setFinalizeButton('<i class="bi bi-hourglass-split me-2"></i>Processing...', true);
    
    try {
        const response = await fetch('api/complete-sale.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(saleData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('paymentConfirmationModal')).hide();
            
            // Show success message with change info for cash payments
            let successMessage = `✅ Sale completed successfully!\n\nSale #: ${result.sale_number}`;
            if (payment.paymentMethod === 'cash' && payment.changeAmount > 0) {
                successMessage += `\n\n💰 Change Due: ${formatCurrency(payment.changeAmount)}`;
                successMessage += `\n\nPlease give the customer their change and receipt.`;
            }
            
            alert(successMessage);
            
            // Reset form
            cart = [];
            updateCart();
            document.getElementById('customerName').value = '';
            document.getElementById('amountTendered').value = '';
            calculateChange();
            clearMobileMoneyStatus();
            window.pendingPayment = null;
            
            // Ask if want to print receipt
            if (confirm('Print receipt?')) {
                window.open('print-receipt.php?id=' + result.sale_id, '_blank');
            }
        } else {
            alert('Error: ' + (result.message || 'Failed to complete sale'));
        }
    } catch (error) {
        alert('Error completing sale: ' + error.message);
    } finally {
        // Re-enable button
        setFinalizeButton(originalText, false);
    }
}

// Initialize payment interface
document.addEventListener('DOMContentLoaded', function() {
    // Initialize totals with currency formatting
    ['subtotal', 'taxAmount', 'total', 'changeAmount'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = formatCurrency(0);
        }
    });

    const productsList = document.getElementById('productsList');
    const addButtons = document.querySelectorAll('.add-to-cart-btn');

    if (addButtons.length) {
        addButtons.forEach(button => {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                addToCartFromElement(this);
            });
        });
    }

    if (productsList) {
        productsList.addEventListener('click', (event) => {
            const button = event.target.closest('.add-to-cart-btn');
            if (!button) {
                return;
            }
            event.preventDefault();
            addToCartFromElement(button);
        });

        productsList.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            const button = event.target.closest('.add-to-cart-btn');
            if (button) {
                event.preventDefault();
                addToCartFromElement(button);
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
                addToCartFromElement(primaryButton);
            }
        });
    }

    handlePaymentMethodChange(); // Set initial payment method state
    updateHoldOrderButton(); // Initialize hold order button state
    const roomSelect = document.getElementById('roomBookingSelect');
    if (roomSelect) {
        roomSelect.addEventListener('change', refreshPaymentUIState);
    }

    document.querySelectorAll('.quick-tender-btn').forEach(button => {
        button.addEventListener('click', () => {
            const amount = parseFloat(button.dataset.amount);
            if (!Number.isFinite(amount)) {
                return;
            }
            applyQuickTender(amount);
        });
    });

    refreshPaymentUIState();
});

// Hold Order Functions
function holdOrder() {
    if (cart.length === 0) {
        alert('Cannot hold an empty order');
        return;
    }
    
    // Populate hold order summary
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const tax = subtotal * (TAX_RATE / 100);
    const total = subtotal + tax;

    const itemsHtml = cart.map(item => `
        <li class="d-flex justify-content-between align-items-center gap-2">
            <span>${item.name} × ${item.quantity}</span>
            <span class="fw-semibold">${formatCurrency(item.total)}</span>
        </li>
    `).join('');

    document.getElementById('holdOrderSummary').innerHTML = `
        <ul class="list-unstyled d-flex flex-column gap-2 mb-3">
            ${itemsHtml}
        </ul>
        <div class="d-flex flex-column gap-1">
            <div class="d-flex justify-content-between text-muted">
                <span>Subtotal</span>
                <span>${formatCurrency(subtotal)}</span>
            </div>
            <div class="d-flex justify-content-between text-muted">
                <span>Tax</span>
                <span>${formatCurrency(tax)}</span>
            </div>
            <div class="d-flex justify-content-between align-items-center fw-semibold border-top pt-2">
                <span>Total</span>
                <span class="text-primary">${formatCurrency(total)}</span>
            </div>
        </div>
    `;
    
    // Clear previous values
    document.getElementById('holdCustomerName').value = '';
    document.getElementById('holdNotes').value = '';
    
    // Show modal
    new bootstrap.Modal(document.getElementById('holdOrderModal')).show();
}

function confirmHoldOrder() {
    const customerName = document.getElementById('holdCustomerName').value.trim();
    const notes = document.getElementById('holdNotes').value.trim();
    
    if (!customerName) {
        alert('Please enter a customer name or reference');
        return;
    }
    
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const tax = subtotal * (TAX_RATE / 100);
    const total = subtotal + tax;
    
    const heldOrder = {
        id: Date.now(), // Simple ID generation
        customerName: customerName,
        notes: notes,
        items: [...cart], // Copy cart items
        subtotal: subtotal,
        tax: tax,
        total: total,
        timestamp: new Date().toISOString(),
        cashier: 'Current User' // You can get this from PHP session
    };
    
    // Save to localStorage (in production, this would be saved to database)
    let heldOrders = JSON.parse(localStorage.getItem('heldOrders') || '[]');
    heldOrders.push(heldOrder);
    localStorage.setItem('heldOrders', JSON.stringify(heldOrders));
    
    // Clear current cart
    cart = [];
    updateCart();
    
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('holdOrderModal')).hide();
    
    // Show success message
    alert(`Order held successfully for ${customerName}`);
}

function showHeldOrders() {
    const modal = new bootstrap.Modal(document.getElementById('heldOrdersModal'));
    modal.show();
    
    // Load held orders
    loadHeldOrders();
}

function loadHeldOrders() {
    const heldOrders = JSON.parse(localStorage.getItem('heldOrders') || '[]');
    const container = document.getElementById('heldOrdersList');
    
    if (heldOrders.length === 0) {
        container.innerHTML = `
            <div class="app-card held-order-card text-center">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="text-muted mt-2 mb-1">No held orders</p>
                <small class="text-muted">Hold an order from the cart to see it listed here.</small>
            </div>
        `;
        return;
    }
    
    let html = '';
    heldOrders.forEach((order, index) => {
        const timeAgo = getTimeAgo(new Date(order.timestamp));
        html += `
            <div class="held-order-card">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                    <div>
                        <h6 class="mb-1">${order.customerName}</h6>
                        <small class="text-muted">Held ${timeAgo}</small>
                    </div>
                    <div class="text-end">
                        <div class="fw-semibold text-primary">${formatCurrency(order.total)}</div>
                        <small class="text-muted">${order.items.length} item${order.items.length !== 1 ? 's' : ''}</small>
                    </div>
                </div>
                ${order.notes ? `<div class="small text-info mb-2"><i class="bi bi-sticky"></i> ${order.notes}</div>` : ''}
                <div class="border rounded-3 px-3 py-2 bg-light">
                    <small class="text-muted text-uppercase">Items</small>
                    <ul class="list-unstyled small mb-0 mt-2 d-flex flex-column gap-1">
                        ${order.items.map(item => `
                            <li class="d-flex justify-content-between">
                                <span>${item.name}</span>
                                <span>× ${item.quantity}</span>
                            </li>
                        `).join('')}
                    </ul>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-success btn-sm" onclick="recallOrder(${index})">
                        <i class="bi bi-arrow-clockwise me-1"></i>Recall
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="deleteHeldOrder(${index})">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function recallOrder(index) {
    const heldOrders = JSON.parse(localStorage.getItem('heldOrders') || '[]');
    const order = heldOrders[index];
    
    if (!order) {
        alert('Order not found');
        return;
    }
    
    // Check if current cart has items
    if (cart.length > 0) {
        if (!confirm('Current cart has items. Recalling this order will replace the current cart. Continue?')) {
            return;
        }
    }
    
    // Load order into cart
    cart = [...order.items];
    updateCart();
    
    // Update customer name field
    document.getElementById('customerName').value = order.customerName;
    
    // Remove from held orders
    heldOrders.splice(index, 1);
    localStorage.setItem('heldOrders', JSON.stringify(heldOrders));
    
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('heldOrdersModal')).hide();
    
    // Show success message
    alert(`Order recalled for ${order.customerName}. You can now add more items or process payment.`);
}

function deleteHeldOrder(index) {
    if (!confirm('Are you sure you want to delete this held order?')) {
        return;
    }
    
    const heldOrders = JSON.parse(localStorage.getItem('heldOrders') || '[]');
    heldOrders.splice(index, 1);
    localStorage.setItem('heldOrders', JSON.stringify(heldOrders));
    
    // Reload the list
    loadHeldOrders();
}

function updateHoldOrderButton() {
    const holdBtn = document.getElementById('holdOrderBtn');
    if (!holdBtn) {
        return;
    }
    holdBtn.disabled = cart.length === 0;
}


function getTimeAgo(date) {
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} min ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    return date.toLocaleDateString();
}

// Barcode scanner support
document.addEventListener('keypress', function(e) {
    if (e.target.tagName !== 'INPUT') {
        document.getElementById('searchProduct').focus();
    }
});

// Quick tender keyboard shortcuts (Alt + 1..4)
document.addEventListener('keydown', function(event) {
    if (!event.altKey) {
        return;
    }

    const mapping = {
        '1': 100,
        '2': 200,
        '3': 500,
        '4': 1000
    };

    if (!Object.prototype.hasOwnProperty.call(mapping, event.key)) {
        return;
    }

    event.preventDefault();
    applyQuickTender(mapping[event.key]);
});
</script>

<?php include 'includes/footer.php'; ?>
