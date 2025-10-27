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
        height: calc(100vh - 120px);
        overflow: hidden;
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
    }
    .cart-items {
        flex: 1;
        overflow-y: auto;
        max-height: 50vh;
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
                            <p class="text-primary mb-0 fw-bold">KES <?= formatMoney($product['selling_price']) ?></p>
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
                        <span id="subtotal"><?= formatMoney(0) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax (16%):</span>
                        <span id="taxAmount"><?= formatMoney(0) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <strong>Total:</strong>
                        <strong class="text-primary fs-4" id="total"><?= formatMoney(0) ?></strong>
                    </div>

                    <div class="mb-3">
                        <select id="paymentMethod" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>

                    <div class="d-grid gap-2">
                        <button class="btn btn-success btn-lg" onclick="submitOrder()" id="submitBtn" disabled>
                            <i class="bi bi-check-circle me-2"></i>Submit Order
                        </button>
                        <button class="btn btn-outline-danger" onclick="clearCart()">
                            <i class="bi bi-trash me-2"></i>Clear All
                        </button>
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
                                    (+KES <?= formatMoney($mod['price']) ?>)
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
    document.getElementById('itemPrice').textContent = currencySymbol + ' ' + parseFloat(product.selling_price).toFixed(2);
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
                        <strong>${currencySymbol} ${item.total.toFixed(2)}</strong>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        cartDiv.innerHTML = html;
        document.getElementById('submitBtn').disabled = false;
    }
    
    // Calculate totals
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const taxAmount = subtotal * (TAX_RATE / 100);
    const total = subtotal + taxAmount;
    
    document.getElementById('subtotal').textContent = currencySymbol + ' ' + subtotal.toFixed(2);
    document.getElementById('taxAmount').textContent = currencySymbol + ' ' + taxAmount.toFixed(2);
    document.getElementById('total').textContent = currencySymbol + ' ' + total.toFixed(2);
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

async function submitOrder() {
    if (cart.length === 0) return;
    
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const taxAmount = subtotal * (TAX_RATE / 100);
    const total = subtotal + taxAmount;
    
    const orderData = {
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
        const response = await fetch('api/create-restaurant-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(orderData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Order submitted successfully!');
            window.location.href = 'restaurant.php';
        } else {
            alert('Error: ' + (result.message || 'Failed to submit order'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
