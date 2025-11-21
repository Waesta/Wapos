<?php
require_once 'includes/bootstrap.php';

$pageTitle = 'Order Online';
include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
    .menu-section-title {
        border-left: 4px solid var(--bs-primary);
        padding-left: 0.75rem;
    }
    .product-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border-radius: 12px;
        overflow: hidden;
        height: 100%;
    }
    .product-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
    }
    .cart-sidebar {
        position: sticky;
        top: 6rem;
        max-height: calc(100vh - 7rem);
        overflow-y: auto;
    }
    .badge-pill {
        border-radius: 999px;
        padding: 0.35rem 0.75rem;
        font-size: 0.75rem;
    }
    .floating-cart-button {
        position: fixed;
        bottom: 1.5rem;
        right: 1.5rem;
        z-index: 1030;
        box-shadow: 0 10px 20px rgba(13, 110, 253, 0.25);
    }
    .modifier-chip {
        border-radius: 20px;
        border: 1px solid rgba(13, 110, 253, 0.3);
        padding: 0.25rem 0.75rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .modifier-chip.active {
        background: rgba(13, 110, 253, 0.1);
        border-color: rgba(13, 110, 253, 0.6);
    }
    .status-timeline {
        border-left: 2px solid rgba(13, 110, 253, 0.3);
        padding-left: 1rem;
    }
    .status-timeline .timeline-item {
        position: relative;
        margin-bottom: 1.25rem;
    }
    .status-timeline .timeline-item::before {
        content: '';
        position: absolute;
        left: -1.1rem;
        top: 0.2rem;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--bs-primary);
        box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.15);
    }
    .status-timeline .timeline-item:last-child {
        margin-bottom: 0;
    }
    .status-timeline .timeline-item .timestamp {
        font-size: 0.75rem;
        color: var(--bs-gray-600);
    }
</style>

<div class="container py-4" id="orderingApp">
    <div class="row g-4">
        <div class="col-xxl-8">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1"><i class="bi bi-basket2 me-2 text-primary"></i>Place Your Order</h1>
                    <p class="text-muted mb-0">Browse our menu, customize your meal, and check out in minutes.</p>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="orderType" id="typeDelivery" value="delivery" autocomplete="off" checked>
                        <label class="btn btn-outline-primary" for="typeDelivery"><i class="bi bi-truck me-1"></i>Delivery</label>
                        <input type="radio" class="btn-check" name="orderType" id="typePickup" value="pickup" autocomplete="off">
                        <label class="btn btn-outline-primary" for="typePickup"><i class="bi bi-bag me-1"></i>Pickup</label>
                    </div>
                </div>
            </div>

            <div id="menuContainer">
                <div class="text-center py-5 text-muted" id="menuLoading">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3">Loading menu...</p>
                </div>
            </div>
        </div>
        <div class="col-xxl-4">
            <div class="card shadow-sm cart-sidebar" id="cartCard">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-cart3 me-2"></i>Your Cart</h5>
                    <span class="badge bg-primary" id="cartCount">0 items</span>
                </div>
                <div class="card-body p-0">
                    <div id="cartEmpty" class="text-center text-muted py-5">
                        <i class="bi bi-basket fs-1"></i>
                        <p class="mt-2">Your cart is empty. Browse the menu to get started.</p>
                    </div>
                    <div id="cartItems" class="list-group list-group-flush d-none"></div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between small text-muted mb-2">
                        <span>Subtotal</span>
                        <span id="subtotalDisplay">KES 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mb-2">
                        <span>Tax</span>
                        <span id="taxDisplay">KES 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mb-2 delivery-only">
                        <span>Delivery</span>
                        <span id="deliveryDisplay">KES 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between fw-semibold">
                        <span>Total</span>
                        <span id="totalDisplay">KES 0.00</span>
                    </div>
                    <button class="btn btn-primary w-100 mt-3" id="checkoutBtn" disabled>
                        <i class="bi bi-credit-card me-2"></i>Proceed to Checkout
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Checkout Modal -->
    <div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Checkout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="checkoutForm" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="customer_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone *</label>
                            <input type="tel" class="form-control" name="customer_phone" required>
                        </div>
                        <div class="col-md-12 delivery-only">
                            <label class="form-label">Delivery Address *</label>
                            <textarea class="form-control" name="delivery_address" rows="2" placeholder="Building, street, landmarks" required></textarea>
                        </div>
                        <div class="col-md-12 delivery-only">
                            <label class="form-label">Delivery Instructions</label>
                            <textarea class="form-control" name="delivery_instructions" rows="2" placeholder="Gate code, contact person, etc."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email (optional)</label>
                            <input type="email" class="form-control" name="customer_email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method *</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="card">Card on Delivery</option>
                                <option value="cash">Cash on Delivery</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Order Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    </form>
                    <div id="quoteSummary" class="border rounded p-3 bg-light mt-3">
                        <h6 class="fw-semibold mb-3">Order Summary</h6>
                        <div class="d-flex justify-content-between small text-muted mb-2">
                            <span>Subtotal</span>
                            <span id="quoteSubtotal">KES 0.00</span>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mb-2">
                            <span>Tax</span>
                            <span id="quoteTax">KES 0.00</span>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mb-2 delivery-only">
                            <span>Delivery</span>
                            <span id="quoteDelivery">KES 0.00</span>
                        </div>
                        <div class="d-flex justify-content-between fw-semibold">
                            <span>Total</span>
                            <span id="quoteTotal">KES 0.00</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmOrderBtn">
                        <i class="bi bi-check-circle me-2"></i>Place Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Confirmation Modal -->
    <div class="modal fade" id="orderConfirmationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="text-success mb-3">
                        <i class="bi bi-check-circle-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="fw-bold">Order Confirmed!</h5>
                    <p class="text-muted mb-3">Thank you for your order. We’re preparing it now.</p>
                    <p class="small text-muted" id="confirmationDetails"></p>
                    <button class="btn btn-primary w-100 mt-3" id="trackOrderBtn">
                        <i class="bi bi-map me-2"></i>Track Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Status Modal -->
    <div class="modal fade" id="orderStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-geo-alt me-2"></i>Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="statusMeta" class="mb-3">
                        <h6 class="fw-semibold mb-1" id="statusOrderLabel">Order</h6>
                        <div class="small text-muted" id="statusSummary"></div>
                    </div>
                    <div id="statusTimelineContainer" class="status-timeline">
                        <div class="timeline-item text-muted" id="statusTimelinePlaceholder">
                            <div class="fw-semibold">No updates yet</div>
                            <div class="timestamp">We will refresh when new events arrive.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="refreshStatusBtn">
                        <i class="bi bi-arrow-repeat me-1"></i>Refresh
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<button class="btn btn-primary rounded-pill floating-cart-button d-none" id="floatingCartBtn">
    <i class="bi bi-cart3 me-2"></i><span id="floatingCartCount">0</span>
</button>

<script>
const state = {
    categories: [],
    products: [],
    cart: [],
    orderType: 'delivery',
    quote: null,
    quoteHash: null,
    lastQuoteTimestamp: null,
    orderId: null,
    orderNumber: null,
};

const menuContainer = document.getElementById('menuContainer');
const menuLoading = document.getElementById('menuLoading');
const cartCard = document.getElementById('cartCard');
const cartEmpty = document.getElementById('cartEmpty');
const cartItems = document.getElementById('cartItems');
const cartCount = document.getElementById('cartCount');
const checkoutBtn = document.getElementById('checkoutBtn');
const floatingCartBtn = document.getElementById('floatingCartBtn');
const floatingCartCount = document.getElementById('floatingCartCount');

const subtotalDisplay = document.getElementById('subtotalDisplay');
const taxDisplay = document.getElementById('taxDisplay');
const deliveryDisplay = document.getElementById('deliveryDisplay');
const totalDisplay = document.getElementById('totalDisplay');

const checkoutModal = new bootstrap.Modal(document.getElementById('checkoutModal'));
const orderConfirmationModal = new bootstrap.Modal(document.getElementById('orderConfirmationModal'));
const orderStatusModal = new bootstrap.Modal(document.getElementById('orderStatusModal'));
const confirmationDetails = document.getElementById('confirmationDetails');
const checkoutForm = document.getElementById('checkoutForm');
const confirmOrderBtn = document.getElementById('confirmOrderBtn');
const refreshStatusBtn = document.getElementById('refreshStatusBtn');
const statusOrderLabel = document.getElementById('statusOrderLabel');
const statusSummary = document.getElementById('statusSummary');
const statusTimelineContainer = document.getElementById('statusTimelineContainer');
const statusTimelinePlaceholder = document.getElementById('statusTimelinePlaceholder');

const quoteSubtotal = document.getElementById('quoteSubtotal');
const quoteTax = document.getElementById('quoteTax');
const quoteDelivery = document.getElementById('quoteDelivery');
const quoteTotal = document.getElementById('quoteTotal');

function formatCurrency(value) {
    return 'KES ' + (value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function fetchMenu() {
    menuLoading.classList.remove('d-none');
    try {
        const response = await fetch('api/customer-ordering.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'list_menu' })
        });
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Failed to load menu');
        }
        state.categories = result.categories || [];
        state.products = result.products || [];
        renderMenu();
    } catch (error) {
        menuContainer.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
    } finally {
        menuLoading.classList.add('d-none');
    }
}

function renderMenu() {
    if (!state.categories.length) {
        menuContainer.innerHTML = `<div class="alert alert-info">Menu will be published soon. Please check back later.</div>`;
        return;
    }

    const html = state.categories.map(category => {
        const items = state.products.filter(product => product.category_id === category.id && product.is_active !== false);
        if (!items.length) {
            return '';
        }
        const cards = items.map(product => `
            <div class="col-md-6">
                <div class="card product-card shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="card-title mb-1">${product.name}</h5>
                                <p class="card-text text-muted small mb-0">${product.description || ''}</p>
                            </div>
                            <span class="badge bg-light text-dark fw-semibold">${formatCurrency(product.price)}</span>
                        </div>
                        <div class="mt-auto">
                            <button class="btn btn-outline-primary w-100" onclick='openProduct(${JSON.stringify(product)})'>
                                <i class="bi bi-plus-circle me-1"></i>Add to Order
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');

        return `
            <section class="mb-4">
                <h4 class="menu-section-title mb-3">${category.name}</h4>
                <div class="row g-3">
                    ${cards}
                </div>
            </section>
        `;
    }).join('');

    menuContainer.innerHTML = html;
}

function openProduct(product) {
    const quantity = prompt(`Enter quantity for ${product.name}:`, '1');
    if (!quantity) return;
    const qty = Math.max(1, parseInt(quantity, 10));
    addToCart({
        product_id: product.id,
        name: product.name,
        price: parseFloat(product.price),
        qty,
    });
}

function addToCart(item) {
    const existing = state.cart.find(line => line.product_id === item.product_id);
    if (existing) {
        existing.qty += item.qty;
    } else {
        state.cart.push({ ...item });
    }
    updateCartUI();
}

function removeFromCart(productId) {
    state.cart = state.cart.filter(line => line.product_id !== productId);
    updateCartUI();
}

function changeQuantity(productId, delta) {
    const line = state.cart.find(item => item.product_id === productId);
    if (!line) return;
    line.qty = Math.max(1, line.qty + delta);
    updateCartUI();
}

function updateCartUI() {
    const itemCount = state.cart.reduce((sum, item) => sum + item.qty, 0);
    cartCount.textContent = `${itemCount} item${itemCount === 1 ? '' : 's'}`;
    floatingCartCount.textContent = itemCount;
    floatingCartBtn.classList.toggle('d-none', itemCount === 0);

    if (state.cart.length === 0) {
        cartEmpty.classList.remove('d-none');
        cartItems.classList.add('d-none');
        checkoutBtn.disabled = true;
        state.quote = null;
        state.quoteHash = null;
        updateTotalsFromQuote();
        return;
    } else {
        cartEmpty.classList.add('d-none');
        cartItems.classList.remove('d-none');
        checkoutBtn.disabled = false;
        cartItems.innerHTML = state.cart.map(item => `
            <div class="list-group-item d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-semibold">${item.name}</div>
                    <div class="text-muted small">${formatCurrency(item.price)} × ${item.qty}</div>
                </div>
                <div class="text-end">
                    <div class="fw-semibold">${formatCurrency(item.price * item.qty)}</div>
                    <div class="btn-group btn-group-sm mt-1" role="group">
                        <button class="btn btn-outline-secondary" onclick="changeQuantity(${item.product_id}, -1)">-</button>
                        <button class="btn btn-outline-secondary" onclick="changeQuantity(${item.product_id}, 1)">+</button>
                        <button class="btn btn-outline-danger" onclick="removeFromCart(${item.product_id})"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>
        `).join('');
    }

    requestQuote();
}

async function requestQuote() {
    if (!state.cart.length) {
        state.quote = null;
        updateTotalsFromQuote();
        return;
    }

    const payload = {
        action: 'quote',
        order_type: state.orderType,
        items: state.cart.map(item => ({
            product_id: item.product_id,
            qty: item.qty,
            price: item.price,
        })),
    };

    const hashSource = JSON.stringify(payload);
    const hash = await digestMessage(hashSource);
    if (hash === state.quoteHash && state.quote) {
        updateTotalsFromQuote();
        return;
    }

    state.quoteHash = hash;
    try {
        const response = await fetch('api/customer-ordering.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Quote failed');
        }
        state.quote = result;
        state.lastQuoteTimestamp = Date.now();
        updateTotalsFromQuote();
    } catch (error) {
        console.error(error);
        showToast('danger', error.message);
    }
}

function updateTotalsFromQuote() {
    if (!state.quote) {
        subtotalDisplay.textContent = formatCurrency(0);
        taxDisplay.textContent = formatCurrency(0);
        deliveryDisplay.textContent = state.orderType === 'delivery' ? formatCurrency(0) : 'KES 0.00';
        totalDisplay.textContent = formatCurrency(0);
        return;
    }

    const totals = state.quote.totals;
    subtotalDisplay.textContent = formatCurrency(totals.subtotal);
    taxDisplay.textContent = formatCurrency(totals.tax);
    deliveryDisplay.textContent = state.orderType === 'delivery' ? formatCurrency(totals.delivery_fee) : 'KES 0.00';
    totalDisplay.textContent = formatCurrency(totals.total);
}

function openCheckout() {
    if (!state.cart.length) {
        showToast('warning', 'Add items to your cart first.');
        return;
    }
    const form = checkoutForm;
    form.reset();
    toggleDeliveryControls();
    updateQuoteSummary();
    checkoutModal.show();
}

function toggleDeliveryControls() {
    const controls = document.querySelectorAll('.delivery-only');
    controls.forEach(ctrl => ctrl.classList.toggle('d-none', state.orderType !== 'delivery'));
}

function updateQuoteSummary() {
    updateTotalsFromQuote();
    const totals = state.quote?.totals ?? { subtotal: 0, tax: 0, delivery_fee: 0, total: 0 };
    quoteSubtotal.textContent = formatCurrency(totals.subtotal ?? 0);
    quoteTax.textContent = formatCurrency(totals.tax ?? 0);
    quoteDelivery.textContent = state.orderType === 'delivery' ? formatCurrency(totals.delivery_fee ?? 0) : 'KES 0.00';
    quoteTotal.textContent = formatCurrency(totals.total ?? 0);
}

async function placeOrder() {
    if (!checkoutForm.reportValidity()) {
        return;
    }

    if (!state.quote) {
        await requestQuote();
        if (!state.quote) {
            showToast('danger', 'Unable to calculate totals for this order.');
            return;
        }
    }

    confirmOrderBtn.disabled = true;
    confirmOrderBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Placing order...';

    const formData = new FormData(checkoutForm);
    const csrfToken = formData.get('csrf_token');
    const payload = {
        action: 'place_order',
        order_type: state.orderType,
        items: state.cart.map(item => ({
            product_id: item.product_id,
            qty: item.qty,
            price: item.price,
        })),
        totals: state.quote?.totals ?? null,
        customer_name: formData.get('customer_name'),
        customer_phone: formData.get('customer_phone'),
        customer_email: formData.get('customer_email') || null,
        delivery_address: state.orderType === 'delivery' ? formData.get('delivery_address') : null,
        delivery_instructions: state.orderType === 'delivery' ? formData.get('delivery_instructions') : null,
        payment_method: formData.get('payment_method'),
        notes: formData.get('notes') || null,
        csrf_token: csrfToken,
    };

    try {
        const response = await fetch('api/customer-ordering.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Unable to place order');
        }

        state.orderId = result.order_id;
        state.orderNumber = result.order_number;
        confirmationDetails.textContent = `Order #${result.order_number}. Total ${formatCurrency(result.total_amount)}.`;
        checkoutModal.hide();
        orderConfirmationModal.show();
        state.cart = [];
        updateCartUI();
        updateTotalsFromQuote();
    } catch (error) {
        showToast('danger', error.message);
    } finally {
        confirmOrderBtn.disabled = false;
        confirmOrderBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Place Order';
    }
}

async function loadOrderStatus() {
    if (!state.orderId) {
        showToast('warning', 'No order to track yet.');
        return;
    }

    try {
        const response = await fetch('api/customer-ordering.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'status', order_id: state.orderId })
        });
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Could not fetch status');
        }
        renderStatusModal(result);
        orderStatusModal.show();
    } catch (error) {
        showToast('danger', error.message);
    }
}

function renderStatusModal(payload) {
    statusOrderLabel.textContent = `Order #${payload.order?.order_number || state.orderNumber || ''}`;
    const status = payload.latest_status ? payload.latest_status.replace(/_/g, ' ') : 'Unknown';
    const timestamp = payload.latest_timestamp ? new Date(payload.latest_timestamp).toLocaleString() : '—';
    statusSummary.textContent = `${status} • Updated ${timestamp}`;

    const timeline = payload.timeline || [];
    statusTimelineContainer.innerHTML = '';

    if (!timeline.length) {
        statusTimelineContainer.appendChild(statusTimelinePlaceholder);
        statusTimelinePlaceholder.classList.remove('d-none');
        return;
    }

    statusTimelinePlaceholder.classList.add('d-none');

    timeline.forEach(entry => {
        const item = document.createElement('div');
        item.className = 'timeline-item';
        const statusLabel = entry.status ? entry.status.replace(/_/g, ' ') : 'Update';
        const timestamp = entry.created_at ? new Date(entry.created_at).toLocaleString() : '—';
        const notes = entry.notes ? `<div class="text-muted small">${escapeHtml(entry.notes)}</div>` : '';
        item.innerHTML = `
            <div class="fw-semibold">${escapeHtml(statusLabel)}</div>
            <div class="timestamp">${timestamp}</div>
            ${notes}
        `;
        statusTimelineContainer.appendChild(item);
    });
}

async function digestMessage(message) {
    if (window.crypto && window.crypto.subtle) {
        const encoder = new TextEncoder();
        const data = encoder.encode(message);
        const hashBuffer = await window.crypto.subtle.digest('SHA-1', data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }
    return message;
}

function showToast(type, message) {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${type} border-0 position-fixed top-0 end-0 m-3`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, { delay: 4000 });
    bsToast.show();
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

document.getElementById('typeDelivery').addEventListener('change', () => {
    state.orderType = 'delivery';
    toggleDeliveryControls();
    requestQuote();
});

document.getElementById('typePickup').addEventListener('change', () => {
    state.orderType = 'pickup';
    toggleDeliveryControls();
    requestQuote();
});

checkoutBtn.addEventListener('click', openCheckout);
confirmOrderBtn.addEventListener('click', placeOrder);
floatingCartBtn.addEventListener('click', () => {
    cartCard.scrollIntoView({ behavior: 'smooth' });
});
document.getElementById('trackOrderBtn').addEventListener('click', loadOrderStatus);
refreshStatusBtn.addEventListener('click', loadOrderStatus);

fetchMenu().then(() => requestQuote());
</script>
