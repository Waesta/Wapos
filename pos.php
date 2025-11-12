<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

// Get active categories and products
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$products = $db->fetchAll("SELECT * FROM products WHERE is_active = 1 ORDER BY name");

$pageTitle = 'Point of Sale';
include 'includes/header.php';
?>

<style>
    .pos-container {
        height: calc(100vh - 220px);
        overflow: hidden;
        margin-bottom: 60px;
    }
    .products-section {
        height: 100%;
        overflow-y: auto;
        padding: 15px;
    }
    .cart-section {
        height: 100%;
        border-left: 2px solid #dee2e6;
        display: flex;
        flex-direction: column;
        padding: 10px;
        padding-bottom: 20px;
        overflow-y: auto;
    }
    .product-card {
        cursor: pointer;
        transition: all 0.2s;
        height: 140px;
    }
    .product-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .cart-items {
        flex: 0 0 auto;
        overflow-y: auto;
        max-height: 30vh;
        margin-bottom: 10px;
    }
    .cart-total {
        border-top: 2px solid #dee2e6;
        background: #f8f9fa;
        padding: 8px;
        margin-top: 10px;
        flex-shrink: 0;
    }
    .payment-section {
        margin-top: 5px;
        margin-bottom: 5px;
    }
    .payment-buttons {
        margin-top: 5px;
        margin-bottom: 30px;
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
    
    .alert {
        padding: 8px 12px;
        font-size: 0.85rem;
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
        <div class="col-md-7 products-section">
            <!-- Search and Filter -->
            <div class="mb-3">
                <div class="row g-2">
                    <div class="col-md-8">
                        <input type="text" id="searchProduct" class="form-control" placeholder="Search products by name, SKU, or barcode...">
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
                    'tax_rate' => $product['tax_rate'] !== null ? (float)$product['tax_rate'] : null,
                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                ?>
                <div class="list-group-item product-item d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2"
                     data-category="<?= (int)$product['category_id'] ?>"
                     data-name="<?= htmlspecialchars(strtolower($product['name']), ENT_QUOTES, 'UTF-8') ?>"
                     data-sku="<?= htmlspecialchars(strtolower($product['sku'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                     data-barcode="<?= htmlspecialchars(strtolower($product['barcode'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="product-info">
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="mb-0"><?= htmlspecialchars($product['name']) ?></h6>
                            <?php if ($product['stock_quantity'] <= $product['min_stock_level']): ?>
                                <span class="badge bg-warning text-dark">Low</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted small">SKU: <?= htmlspecialchars($product['sku']) ?></div>
                    </div>
                    <div class="text-md-end d-flex flex-column flex-sm-row align-items-sm-center gap-2">
                        <div class="pe-sm-2 text-muted small">
                            <div class="fw-bold text-primary"><?= formatMoney($product['selling_price']) ?></div>
                            <div>Stock: <?= $product['stock_quantity'] ?></div>
                        </div>
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
                            <i class="bi bi-cart-plus me-1"></i>Add
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Cart Section -->
        <div class="col-md-5 cart-section">
            <div class="h-100 d-flex flex-column">
                <h5 class="mb-3"><i class="bi bi-cart3 me-2"></i>Current Sale</h5>
                
                <!-- Customer Info -->
                <div class="mb-3">
                    <input type="text" id="customerName" class="form-control form-control-sm" placeholder="Customer Name (Optional)">
                </div>

                <!-- Cart Items -->
                <div class="cart-items" id="cartItems">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-cart-x fs-1"></i>
                        <p class="mt-2">Cart is empty<br><small>Click on products to add</small></p>
                    </div>
                </div>

                <!-- Cart Total -->
                <div class="cart-total">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span id="subtotal">0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax (<span id="taxRate">0</span>%):</span>
                        <span id="taxAmount">0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <strong>Total:</strong>
                        <strong class="text-primary fs-4" id="total">0.00</strong>
                    </div>

                    <div class="payment-section">
                        <div class="mb-1">
                            <label for="paymentMethod" class="form-label">Payment Method</label>
                            <select id="paymentMethod" class="form-select form-select-sm" onchange="handlePaymentMethodChange()">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>

                        <!-- Cash Payment Section -->
                        <div id="cashPaymentSection" class="mb-1">
                            <label for="amountTendered" class="form-label">Amount Tendered</label>
                            <input type="number" class="form-control form-control-sm" id="amountTendered" 
                                   placeholder="0.00" step="0.01" min="0" 
                                   oninput="calculateChange()" onkeypress="handleTenderedKeypress(event)">
                            <div id="changeDisplay" class="mt-1" style="display: none;">
                                <div class="alert alert-success mb-0 py-1">
                                    <div class="d-flex justify-content-between">
                                        <strong>Change Due:</strong>
                                        <strong id="changeAmount">0.00</strong>
                                    </div>
                                </div>
                            </div>
                            <div id="insufficientFunds" class="mt-1" style="display: none;">
                                <div class="alert alert-danger mb-0 py-1">
                                    <small>Insufficient amount tendered</small>
                                </div>
                            </div>
                        </div>


                        <div class="payment-buttons">
                            <div class="d-grid gap-2">
                                <button class="btn btn-success btn-lg" onclick="processPayment()" id="processPaymentBtn" disabled>
                                    <i class="bi bi-credit-card me-2"></i>Process Payment
                                </button>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <button class="btn btn-warning w-100" onclick="holdOrder()" id="holdOrderBtn" disabled>
                                            <i class="bi bi-pause-circle me-1"></i>Hold Order
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-info w-100" onclick="showHeldOrders()" id="recallOrderBtn">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Recall Order
                                        </button>
                                    </div>
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
</div>

<!-- Payment Confirmation Modal -->
<div class="modal fade" id="paymentConfirmationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Payment Confirmation</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Transaction Summary</h6>
                        <table class="table table-sm">
                            <tr><td>Subtotal:</td><td id="confirmSubtotal">0.00</td></tr>
                            <tr><td>Tax:</td><td id="confirmTax">0.00</td></tr>
                            <tr><td><strong>Total:</strong></td><td><strong id="confirmTotal">0.00</strong></td></tr>
                            <tr><td>Payment Method:</td><td id="confirmPaymentMethod">Cash</td></tr>
                            <tr id="confirmAmountPaidRow"><td>Amount Paid:</td><td id="confirmAmountPaid">0.00</td></tr>
                            <tr id="confirmChangeRow" style="display: none;"><td><strong>Change Due:</strong></td><td><strong id="confirmChange" class="text-success">0.00</strong></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Cash Drawer Instructions</h6>
                        <div id="cashInstructions" style="display: none;">
                            <div class="alert alert-info">
                                <h6><i class="bi bi-cash-stack me-2"></i>Cash Handling</h6>
                                <ol class="mb-0">
                                    <li>Collect payment from customer</li>
                                    <li>Place cash in drawer</li>
                                    <li>Count change carefully</li>
                                    <li>Give change to customer</li>
                                    <li>Provide receipt</li>
                                </ol>
                            </div>
                            <div id="changeBreakdown" style="display: none;">
                                <h6>Change Breakdown</h6>
                                <div id="changeDetails" class="small"></div>
                            </div>
                        </div>
                        <div id="cardInstructions" style="display: none;">
                            <div class="alert alert-primary">
                                <h6><i class="bi bi-credit-card me-2"></i>Card Payment</h6>
                                <p class="mb-0">Process card payment through your terminal and confirm transaction completion.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="finalizePayment()">
                    <i class="bi bi-check-circle me-2"></i>Complete Transaction
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hold Order Modal -->
<div class="modal fade" id="holdOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pause-circle me-2"></i>Hold Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="holdCustomerName" class="form-label">Customer Name/Reference</label>
                    <input type="text" class="form-control" id="holdCustomerName" placeholder="Enter customer name or reference">
                    <small class="text-muted">This helps identify the order when recalling</small>
                </div>
                <div class="mb-3">
                    <label for="holdNotes" class="form-label">Notes (Optional)</label>
                    <textarea class="form-control" id="holdNotes" rows="2" placeholder="Any special notes about this order"></textarea>
                </div>
                <div class="alert alert-info">
                    <h6>Order Summary:</h6>
                    <div id="holdOrderSummary"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="confirmHoldOrder()">
                    <i class="bi bi-pause-circle me-2"></i>Hold Order
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Held Orders Modal -->
<div class="modal fade" id="heldOrdersModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-list me-2"></i>Held Orders</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="heldOrdersList">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading held orders...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];
const TAX_RATE = 16; // Default tax rate percentage

// Currency configuration from PHP
const CURRENCY_CONFIG = <?= json_encode(CurrencyManager::getInstance()->getJavaScriptConfig()) ?>;

// Currency formatting function
function formatCurrency(amount) {
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: CURRENCY_CONFIG.decimal_places,
        maximumFractionDigits: CURRENCY_CONFIG.decimal_places
    }).format(amount);
    
    return formatted; // Return amount only, no currency symbol
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
    
    if (cart.length === 0) {
        cartDiv.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-cart-x fs-1"></i>
                <p class="mt-2">Cart is empty<br><small>Click on products to add</small></p>
            </div>
        `;
        document.getElementById('processPaymentBtn').disabled = true;
        document.getElementById('holdOrderBtn').disabled = true;
    } else {
        let html = '<div class="list-group">';
        cart.forEach((item, index) => {
            html += `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">${item.name}</h6>
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
                        <div class="text-end">
                            <div>${formatCurrency(item.price)} × ${item.quantity}</div>
                            <strong>${formatCurrency(item.total)}</strong>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        cartDiv.innerHTML = html;
        // Enable payment button based on payment method
        const paymentMethod = document.getElementById('paymentMethod').value;
        if (paymentMethod === 'cash') {
            calculateChange(); // This will set the correct button state
        } else {
            document.getElementById('processPaymentBtn').disabled = false;
        }
        document.getElementById('holdOrderBtn').disabled = false;
    }
    
    // Calculate totals
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const taxAmount = subtotal * (TAX_RATE / 100);
    const total = subtotal + taxAmount;
    
    document.getElementById('subtotal').textContent = formatCurrency(subtotal);
    document.getElementById('taxRate').textContent = TAX_RATE;
    document.getElementById('taxAmount').textContent = formatCurrency(taxAmount);
    document.getElementById('total').textContent = formatCurrency(total);
}

function clearCart() {
    if (cart.length > 0 && confirm('Clear all items from cart?')) {
        cart = [];
        updateCart();
    }
}

// Payment method change handler
function handlePaymentMethodChange() {
    const paymentMethod = document.getElementById('paymentMethod').value;
    const cashSection = document.getElementById('cashPaymentSection');
    const processBtn = document.getElementById('processPaymentBtn');
    
    if (paymentMethod === 'cash') {
        cashSection.style.display = 'block';
        processBtn.innerHTML = '<i class="bi bi-cash me-2"></i>Process Cash Payment';
        calculateChange(); // Recalculate to update button state
    } else {
        cashSection.style.display = 'none';
        processBtn.innerHTML = '<i class="bi bi-credit-card me-2"></i>Process ' + paymentMethod.replace('_', ' ').toUpperCase() + ' Payment';
        processBtn.disabled = cart.length === 0; // Enable for non-cash payments
    }
}

// Calculate change and update display
function calculateChange() {
    const paymentMethod = document.getElementById('paymentMethod').value;
    if (paymentMethod !== 'cash') return;
    
    const total = cart.reduce((sum, item) => sum + item.total, 0) * (1 + TAX_RATE / 100);
    const tendered = parseFloat(document.getElementById('amountTendered').value) || 0;
    const change = tendered - total;
    
    const changeDisplay = document.getElementById('changeDisplay');
    const insufficientFunds = document.getElementById('insufficientFunds');
    const processBtn = document.getElementById('processPaymentBtn');
    
    if (tendered === 0) {
        // No amount entered
        changeDisplay.style.display = 'none';
        insufficientFunds.style.display = 'none';
        processBtn.disabled = true;
    } else if (change >= 0) {
        // Sufficient funds
        changeDisplay.style.display = 'block';
        insufficientFunds.style.display = 'none';
        document.getElementById('changeAmount').textContent = 'KES ' + change.toFixed(2);
        processBtn.disabled = false;
        
        // Highlight if exact change
        if (change === 0) {
            changeDisplay.className = 'mt-2';
            changeDisplay.innerHTML = `
                <div class="alert alert-info mb-0">
                    <div class="d-flex justify-content-between">
                        <strong>Exact Amount</strong>
                        <strong><i class="bi bi-check-circle text-success"></i></strong>
                    </div>
                </div>
            `;
        } else {
            changeDisplay.className = 'mt-2';
            changeDisplay.innerHTML = `
                <div class="alert alert-success mb-0">
                    <div class="d-flex justify-content-between">
                        <strong>Change Due:</strong>
                        <strong id="changeAmount">${formatCurrency(change)}</strong>
                    </div>
                </div>
            `;
        }
    } else {
        // Insufficient funds
        changeDisplay.style.display = 'none';
        insufficientFunds.style.display = 'block';
        processBtn.disabled = true;
    }
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
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const taxAmount = subtotal * (TAX_RATE / 100);
    const total = subtotal + taxAmount;
    
    let amountPaid = total;
    let changeAmount = 0;
    
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
    }
    
    // Show payment confirmation modal
    showPaymentConfirmation(subtotal, taxAmount, total, paymentMethod, amountPaid, changeAmount);
}

// Show payment confirmation modal
function showPaymentConfirmation(subtotal, taxAmount, total, paymentMethod, amountPaid, changeAmount) {
    // Update modal content
    document.getElementById('confirmSubtotal').textContent = formatCurrency(subtotal);
    document.getElementById('confirmTax').textContent = formatCurrency(taxAmount);
    document.getElementById('confirmTotal').textContent = formatCurrency(total);
    document.getElementById('confirmPaymentMethod').textContent = paymentMethod.replace('_', ' ').toUpperCase();
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
    }
    
    // Show appropriate instructions
    const cashInstructions = document.getElementById('cashInstructions');
    const cardInstructions = document.getElementById('cardInstructions');
    
    if (paymentMethod === 'cash') {
        cashInstructions.style.display = 'block';
        cardInstructions.style.display = 'none';
    } else {
        cashInstructions.style.display = 'none';
        cardInstructions.style.display = 'block';
    }
    
    // Store payment data for finalization
    window.pendingPayment = {
        subtotal, taxAmount, total, paymentMethod, amountPaid, changeAmount
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

// Finalize payment
async function finalizePayment() {
    const payment = window.pendingPayment;
    if (!payment) {
        alert('No pending payment found');
        return;
    }
    
    const customerName = document.getElementById('customerName').value || null;
    
    const saleData = {
        customer_name: customerName,
        payment_method: payment.paymentMethod,
        subtotal: payment.subtotal,
        tax_amount: payment.taxAmount,
        total_amount: payment.total,
        amount_paid: payment.amountPaid,
        change_amount: payment.changeAmount,
        items: cart
    };
    
    // Disable button to prevent double-clicking
    const finalizeBtn = document.querySelector('#paymentConfirmationModal .btn-success');
    const originalText = finalizeBtn.innerHTML;
    finalizeBtn.disabled = true;
    finalizeBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Processing...';
    
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
        finalizeBtn.disabled = false;
        finalizeBtn.innerHTML = originalText;
    }
}

// Initialize payment interface
document.addEventListener('DOMContentLoaded', function() {
    // Initialize totals with currency formatting
    document.getElementById('subtotal').textContent = formatCurrency(0);
    document.getElementById('taxAmount').textContent = formatCurrency(0);
    document.getElementById('total').textContent = formatCurrency(0);
    document.getElementById('changeAmount').textContent = formatCurrency(0);

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
});

// Hold Order Functions
function holdOrder() {
    if (cart.length === 0) {
        alert('Cannot hold an empty order');
        return;
    }
    
    // Populate hold order summary
    const summary = cart.map(item => 
        `${item.name} x${item.quantity} = ${formatCurrency(item.total)}`
    ).join('<br>');
    
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const tax = subtotal * (TAX_RATE / 100);
    const total = subtotal + tax;
    
    document.getElementById('holdOrderSummary').innerHTML = `
        ${summary}<br>
        <hr>
        <strong>Subtotal: ${formatCurrency(subtotal)}</strong><br>
        <strong>Tax: ${formatCurrency(tax)}</strong><br>
        <strong>Total: ${formatCurrency(total)}</strong>
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
            <div class="text-center py-4">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="text-muted mt-2">No held orders</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    heldOrders.forEach((order, index) => {
        const timeAgo = getTimeAgo(new Date(order.timestamp));
        html += `
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-1">${order.customerName}</h6>
                            <small class="text-muted">${timeAgo}</small>
                        </div>
                        <div class="text-end">
                            <strong>${formatCurrency(order.total)}</strong>
                            <br>
                            <small class="text-muted">${order.items.length} items</small>
                        </div>
                    </div>
                    
                    ${order.notes ? `<div class="mb-2"><small class="text-info"><i class="bi bi-sticky"></i> ${order.notes}</small></div>` : ''}
                    
                    <div class="mb-2">
                        <small class="text-muted">Items:</small><br>
                        <small>${order.items.map(item => `${item.name} x${item.quantity}`).join(', ')}</small>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button class="btn btn-success btn-sm" onclick="recallOrder(${index})">
                            <i class="bi bi-arrow-clockwise me-1"></i>Recall
                        </button>
                        <button class="btn btn-outline-danger btn-sm" onclick="deleteHeldOrder(${index})">
                            <i class="bi bi-trash me-1"></i>Delete
                        </button>
                    </div>
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
</script>

<?php include 'includes/footer.php'; ?>
