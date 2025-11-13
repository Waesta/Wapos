<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

// Get order type and table
$orderType = $_GET['type'] ?? 'dine-in';
$tableId = $_GET['table_id'] ?? null;
$orderId = $_GET['id'] ?? null;

// Get products and modifiers
$products = $db->fetchAll("SELECT * FROM products WHERE is_active = 1 ORDER BY name");
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$modifiers = $db->fetchAll("SELECT * FROM modifiers WHERE is_active = 1 ORDER BY category, name");

// Get table info if dine-in
$tableInfo = null;
if ($tableId) {
    $tableInfo = $db->fetchOne("SELECT * FROM restaurant_tables WHERE id = ?", [$tableId]);
}

// Get existing order if editing
$existingOrder = null;
if ($orderId) {
    $existingOrder = $db->fetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
    $existingItems = $db->fetchAll("SELECT * FROM order_items WHERE order_id = ?", [$orderId]);
}

$currencySetting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'currency'");
$currencySymbol = $currencySetting['setting_value'] ?? '$';

$pageTitle = $orderType === 'dine-in' ? 'Dine-In Order' : 'Takeout Order';
include 'includes/header.php';
?>

<style>
    .pos-container {
        height: calc(100vh - 220px);
        overflow: hidden;
        margin-bottom: 60px;
    }
    .product-card {
        cursor: pointer;
        transition: all 0.2s;
        height: 120px;
    }
    .product-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .cart-section {
        border-left: 2px solid #dee2e6;
        height: 100%;
        display: flex;
        flex-direction: column;
        padding-bottom: 20px;
        overflow-y: auto;
    }
    .cart-items {
        flex: 0 0 auto;
        overflow-y: auto;
        max-height: 30vh;
        margin-bottom: 10px;
    }
    
    /* Custom scrollbar styling */
    .cart-section::-webkit-scrollbar {
        width: 8px;
    }
    
    .cart-section::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .cart-section::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }
    
    .cart-section::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
    
    .cart-items::-webkit-scrollbar {
        width: 6px;
    }
    
    .cart-items::-webkit-scrollbar-track {
        background: #f8f9fa;
        border-radius: 3px;
    }
    
    .cart-items::-webkit-scrollbar-thumb {
        background: #dee2e6;
        border-radius: 3px;
    }
    
    .cart-items::-webkit-scrollbar-thumb:hover {
        background: #c1c1c1;
    }
    
    /* Compact form elements */
    .form-label {
        margin-bottom: 4px;
        font-size: 0.9rem;
    }
    
    .form-control, .form-select {
        padding: 6px 12px;
        font-size: 0.9rem;
    }
    
    .btn-sm {
        padding: 4px 8px;
        font-size: 0.8rem;
    }
    
    /* Responsive adjustments for scrollable layout */
    @media (max-height: 800px) {
        .pos-container {
            height: calc(100vh - 180px);
            margin-bottom: 40px;
        }
        .cart-items {
            max-height: 25vh;
        }
    }
    
    @media (max-height: 700px) {
        .pos-container {
            height: calc(100vh - 160px);
            margin-bottom: 40px;
        }
        .cart-items {
            max-height: 20vh;
        }
    }
    
    @media (max-height: 600px) {
        .pos-container {
            height: calc(100vh - 140px);
            margin-bottom: 40px;
        }
        .cart-items {
            max-height: 18vh;
        }
    }
</style>

<div class="pos-container">
    <div class="row g-0 h-100">
        <!-- Products Section -->
        <div class="col-md-7 p-3" style="overflow-y: auto;">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0">
                        <i class="bi bi-<?= $orderType === 'dine-in' ? 'table' : 'bag-check' ?> me-2"></i>
                        <?= ucfirst($orderType) ?> Order
                    </h5>
                    <?php if ($tableInfo): ?>
                        <small class="text-muted">Table: <?= htmlspecialchars($tableInfo['table_number']) ?></small>
                    <?php endif; ?>
                </div>
                <a href="restaurant.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-2"></i>Back
                </a>
            </div>

            <!-- Search and Filter -->
            <div class="mb-3">
                <div class="row g-2">
                    <div class="col-md-8">
                        <input type="text" id="searchProduct" class="form-control" placeholder="Search menu items...">
                    </div>
                    <div class="col-md-4">
                        <select id="filterCategory" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Products List -->
            <div id="productsList" class="list-group product-list">
                <?php foreach ($products as $product): ?>
                <?php
                $productPayload = json_encode([
                    'id' => (int)$product['id'],
                    'name' => $product['name'],
                    'selling_price' => (float)$product['selling_price'],
                    'stock_quantity' => (float)$product['stock_quantity'],
                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                ?>
                <div class="list-group-item product-item d-flex flex-column flex-md-row align-items-md-center justify-product between gap-2"
                     data-category="<?= $product['category_id'] ?>"
                     data-name="<?= strtolower($product['name']) ?>">
                    <div class="product-info">
                        <h6 class="mb-1"><?= htmlspecialchars($product['name']) ?></h6>
                        <div class="text-muted small">Stock: <?= $product['stock_quantity'] ?></div>
                    </div>
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
                        <div class="text-primary fw-bold"><?= formatMoney($product['selling_price'], false) ?></div>
                        <button 
                            type="button"
                            class="btn btn-sm btn-primary add-to-cart-btn"
                            data-product-id="<?= (int)$product['id'] ?>"
                            data-product-name="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-product-price="<?= (float)$product['selling_price'] ?>"
                            data-product-stock="<?= (float)$product['stock_quantity'] ?>"
                            data-product-json="<?= htmlspecialchars($productPayload, ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <i class="bi bi-cart-plus me-1"></i>Add
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Cart Section -->
        <div class="col-md-5 cart-section">
            <div class="p-3">
                <h5 class="mb-3"><i class="bi bi-receipt me-2"></i>Order Items</h5>
                
                <!-- Customer Info -->
                <?php if ($orderType !== 'dine-in'): ?>
                <div class="mb-3">
                    <input type="text" id="customerName" class="form-control form-control-sm mb-2" placeholder="Customer Name (Optional)">
                    <input type="tel" id="customerPhone" class="form-control form-control-sm mb-2" placeholder="Customer Phone (Optional)">
                    <textarea id="deliveryAddress" class="form-control form-control-sm mb-2" rows="2" placeholder="Delivery Address"></textarea>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="number" step="0.000001" id="deliveryLatitude" class="form-control form-control-sm" placeholder="Latitude">
                        </div>
                        <div class="col-6">
                            <input type="number" step="0.000001" id="deliveryLongitude" class="form-control form-control-sm" placeholder="Longitude">
                        </div>
                    </div>
                    <div class="form-text">Provide coordinates for accurate delivery fee calculation. Use decimal degrees.</div>
                </div>
                <?php endif; ?>

                <!-- Cart Items -->
                <div class="cart-items" id="cartItems">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-cart-x fs-1"></i>
                        <p class="mt-2">Cart is empty</p>
                    </div>
                </div>

                <!-- Cart Total -->
                <div class="border-top pt-3 mt-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span id="subtotal"><?= formatMoney(0, false) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax (16%):</span>
                        <span id="taxAmount"><?= formatMoney(0, false) ?></span>
                    </div>
                    <?php if ($orderType !== 'dine-in'): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Delivery Fee:</span>
                        <div class="d-flex align-items-center gap-2">
                            <span id="deliveryFeeDisplay">0.00</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleDeliveryFeeEdit()" id="editDeliveryFeeBtn">Edit</button>
                        </div>
                    </div>
                    <div class="mb-2" id="deliveryFeeEdit" style="display: none;">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Fee</span>
                            <input type="number" step="0.01" class="form-control" id="deliveryFeeInput" placeholder="0.00">
                            <button class="btn btn-outline-primary" type="button" onclick="applyManualDeliveryFee()">Apply</button>
                        </div>
                        <div class="form-text">Leave blank to use the calculated fee.</div>
                    </div>
                    <div class="mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="calculateDeliveryFee()" id="recalculateDeliveryBtn">Calculate Delivery Fee</button>
                        <div class="small text-muted" id="deliveryFeeMeta"></div>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between mb-3">
                        <strong>Total:</strong>
                        <strong class="text-primary fs-4" id="total"><?= formatMoney(0, false) ?></strong>
                    </div>

                    <div class="mb-3">
                        <select id="paymentMethod" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>

                    <div class="d-grid gap-2">
                        <div class="d-grid gap-2">
                            <button class="btn btn-info" onclick="printInvoice()" id="invoiceBtn" disabled>
                                <i class="bi bi-receipt me-2"></i>Print Invoice
                            </button>
                            <button class="btn btn-primary btn-lg" onclick="processPayment()" id="paymentBtn" disabled>
                                <i class="bi bi-credit-card me-2"></i>Process Payment
                            </button>
                        </div>
                        <div class="d-grid gap-2 mt-2">
                            <div class="btn-group">
                                <button class="btn btn-outline-success btn-sm" onclick="printKitchenOrder()" id="kitchenBtn" disabled>
                                    <i class="bi bi-printer me-1"></i>Kitchen
                                </button>
                                <button class="btn btn-outline-info btn-sm" onclick="printCustomerReceipt()" id="receiptBtn" disabled>
                                    <i class="bi bi-receipt-cutoff me-1"></i>Receipt
                                </button>
                            </div>
                            <button class="btn btn-outline-danger" onclick="clearCart()">
                                <i class="bi bi-trash me-2"></i>Clear Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
const TAX_RATE = 16;
const currencySymbol = <?= json_encode($currencySymbol) ?>;
const orderType = '<?= $orderType ?>';
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
    calculatedFee: null
};

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
            html += `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-0">${item.name}</h6>
                            ${item.modifiers.map(m => `<small class="text-muted d-block">+ ${m.name}</small>`).join('')}
                            ${item.instructions ? `<small class="text-muted fst-italic d-block">${item.instructions}</small>` : ''}
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
                        <strong>${item.total.toFixed(2)}</strong>
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
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const taxAmount = subtotal * (TAX_RATE / 100);
    const deliveryFee = getDeliveryFeeValue();
    const total = subtotal + taxAmount + deliveryFee;
    
    const subtotalEl = document.getElementById('subtotal');
    const taxEl = document.getElementById('taxAmount');
    const deliveryFeeEl = document.getElementById('deliveryFeeDisplay');
    const totalEl = document.getElementById('total');

    if (subtotalEl) subtotalEl.textContent = subtotal.toFixed(2);
    if (taxEl) taxEl.textContent = taxAmount.toFixed(2);
    if (deliveryFeeEl) deliveryFeeEl.textContent = deliveryFee.toFixed(2);
    if (totalEl) totalEl.textContent = total.toFixed(2);
    
    // Update button states
    updateButtonStates();
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

async function submitOrder() {
    if (cart.length === 0) return;
    
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const taxAmount = subtotal * (TAX_RATE / 100);
    const deliveryFee = getDeliveryFeeValue();
    const total = subtotal + taxAmount + deliveryFee;
    
    const orderData = {
        action: 'place_order',
        order_type: '<?= $orderType ?>',
        table_id: <?= $tableId ?? 'null' ?>,
        customer_name: getInputValue('customerName') || null,
        customer_phone: getInputValue('customerPhone') || null,
        payment_method: getInputValue('paymentMethod') || 'cash',
        subtotal: subtotal,
        tax_amount: taxAmount,
        delivery_fee: deliveryFee,
        total_amount: total,
        delivery_address: getInputValue('deliveryAddress') || null,
        delivery_latitude: getCoordinateValue('deliveryLatitude'),
        delivery_longitude: getCoordinateValue('deliveryLongitude'),
        items: cart
    };
    
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
    
    if (!confirm(`Process payment of ${currencySymbol} ${total.toFixed(2)} via ${paymentMethod}?`)) {
        return;
    }
    
    try {
        const response = await fetch('api/restaurant-order-workflow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'process_payment',
                order_id: currentOrderId,
                payment_method: paymentMethod,
                amount_paid: total
            })
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
        alert('Error: ' + error.message);
    }
}

function updateButtonStates() {
    const hasItems = cart.length > 0;
    const hasOrder = currentOrderId !== null;
    const isPaid = orderStatus === 'paid';
    
    // Enable/disable buttons based on state with error handling
    try {
        const invoiceBtn = document.getElementById('invoiceBtn');
        const paymentBtn = document.getElementById('paymentBtn');
        const kitchenBtn = document.getElementById('kitchenBtn');
        const receiptBtn = document.getElementById('receiptBtn');
        
        if (invoiceBtn) invoiceBtn.disabled = !hasItems;
        if (paymentBtn) paymentBtn.disabled = !hasItems;
        if (kitchenBtn) kitchenBtn.disabled = !hasOrder;
        if (receiptBtn) receiptBtn.disabled = !isPaid;
    } catch (error) {
        console.error('Error updating button states:', error);
    }
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
    
    // Add debug logging for button clicks
    console.log('Page initialized. You can run testButtons() in console to debug.');
});
</script>

<?php include 'includes/footer.php'; ?>
