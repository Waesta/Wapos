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

            <!-- Products Grid -->
            <div id="productsGrid" class="row g-2">
                <?php foreach ($products as $product): ?>
                <div class="col-md-4 product-item" 
                     data-category="<?= $product['category_id'] ?>"
                     data-name="<?= strtolower($product['name']) ?>">
                    <div class="card product-card" onclick='addToCart(<?= json_encode($product) ?>)'>
                        <div class="card-body p-2">
                            <h6 class="mb-1"><?= htmlspecialchars($product['name']) ?></h6>
                            <p class="text-primary mb-0 fw-bold"><?= formatMoney($product['selling_price'], false) ?></p>
                            <small class="text-muted">Stock: <?= $product['stock_quantity'] ?></small>
                        </div>
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
                    <input type="tel" id="customerPhone" class="form-control form-control-sm" placeholder="Customer Phone (Optional)">
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
const currencySymbol = '<?= $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'currency'")['setting_value'] ?? '$' ?>';

// Search and filter
document.getElementById('searchProduct').addEventListener('input', filterProducts);
document.getElementById('filterCategory').addEventListener('change', filterProducts);

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

function addToCart(product) {
    currentItem = {
        id: product.id,
        name: product.name,
        price: parseFloat(product.selling_price),
        quantity: 1,
        modifiers: [],
        instructions: '',
        base_price: parseFloat(product.selling_price)
    };
    
    // Show modifier modal
    document.getElementById('itemName').textContent = product.name;
    document.getElementById('itemPrice').textContent = parseFloat(product.selling_price).toFixed(2);
    document.querySelectorAll('.modifier-check').forEach(cb => cb.checked = false);
    document.getElementById('specialInstructions').value = '';
    
    new bootstrap.Modal(document.getElementById('modifierModal')).show();
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
    
    bootstrap.Modal.getInstance(document.getElementById('modifierModal')).hide();
}

function updateCart() {
    const cartDiv = document.getElementById('cartItems');
    
    if (cart.length === 0) {
        cartDiv.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-cart-x fs-1"></i>
                <p class="mt-2">Cart is empty</p>
            </div>
        `;
        document.getElementById('submitBtn').disabled = true;
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
    }
    
    // Calculate totals
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const taxAmount = subtotal * (TAX_RATE / 100);
    const total = subtotal + taxAmount;
    
    document.getElementById('subtotal').textContent = subtotal.toFixed(2);
    document.getElementById('taxAmount').textContent = taxAmount.toFixed(2);
    document.getElementById('total').textContent = total.toFixed(2);
    
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
    const total = subtotal + taxAmount;
    
    const orderData = {
        action: 'place_order',
        order_type: '<?= $orderType ?>',
        table_id: <?= $tableId ?? 'null' ?>,
        customer_name: document.getElementById('customerName')?.value || null,
        customer_phone: document.getElementById('customerPhone')?.value || null,
        payment_method: document.getElementById('paymentMethod').value,
        subtotal: subtotal,
        tax_amount: taxAmount,
        total_amount: total,
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
    
    const paymentMethod = document.getElementById('paymentMethod').value;
    const total = cart.reduce((sum, item) => sum + item.total, 0) * (1 + TAX_RATE / 100);
    
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
    console.log('Cart length:', cart.length);
    console.log('Current order ID:', currentOrderId);
    console.log('Order status:', orderStatus);
    
    const buttons = ['invoiceBtn', 'paymentBtn', 'kitchenBtn', 'receiptBtn'];
    buttons.forEach(btnId => {
        const btn = document.getElementById(btnId);
        console.log(`${btnId}:`, btn ? `exists, disabled: ${btn.disabled}` : 'NOT FOUND');
    });
}

// Initialize the page when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing restaurant order page...');
    
    // Initialize button states
    updateButtonStates();
    
    // Add debug logging for button clicks
    const buttons = ['invoiceBtn', 'paymentBtn', 'kitchenBtn', 'receiptBtn'];
    buttons.forEach(btnId => {
        const btn = document.getElementById(btnId);
        if (btn) {
            console.log(`Found button: ${btnId}`);
            btn.addEventListener('click', function(e) {
                console.log(`Button ${btnId} clicked, disabled: ${btn.disabled}`);
                if (btn.disabled) {
                    e.preventDefault();
                    console.log('Button click prevented - button is disabled');
                }
            });
        } else {
            console.error(`Button not found: ${btnId}`);
        }
    });
    
    // Add test button for debugging
    console.log('Page initialized. You can run testButtons() in console to debug.');
});
</script>

<?php include 'includes/footer.php'; ?>
