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
        height: calc(100vh - 120px);
        overflow: hidden;
    }
    .products-section {
        height: 100%;
        overflow-y: auto;
    }
    .cart-section {
        height: 100%;
        border-left: 2px solid #dee2e6;
        display: flex;
        flex-direction: column;
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
        flex: 1;
        overflow-y: auto;
        max-height: 50vh;
    }
    .cart-total {
        border-top: 2px solid #dee2e6;
        background: #f8f9fa;
    }
</style>

<div class="pos-container">
    <div class="row g-0 h-100">
        <!-- Products Section -->
        <div class="col-md-7 products-section p-3">
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

            <!-- Products Grid -->
            <div id="productsGrid" class="row g-2">
                <?php foreach ($products as $product): ?>
                <div class="col-md-4 col-lg-3 product-item" 
                     data-category="<?= $product['category_id'] ?>"
                     data-name="<?= strtolower($product['name']) ?>"
                     data-sku="<?= strtolower($product['sku']) ?>"
                     data-barcode="<?= strtolower($product['barcode']) ?>">
                    <div class="card product-card" onclick='addToCart(<?= json_encode($product) ?>)'>
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0 small"><?= htmlspecialchars($product['name']) ?></h6>
                                <?php if ($product['stock_quantity'] <= $product['min_stock_level']): ?>
                                    <span class="badge bg-warning text-dark">Low</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-muted mb-1 small">SKU: <?= htmlspecialchars($product['sku']) ?></p>
                            <div class="d-flex justify-content-between align-items-end">
                                <div>
                                    <p class="mb-0 fw-bold text-primary">KES <?= formatMoney($product['selling_price']) ?></p>
                                    <small class="text-muted">Stock: <?= $product['stock_quantity'] ?></small>
                                </div>
                                <i class="bi bi-plus-circle fs-4 text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Cart Section -->
        <div class="col-md-5 cart-section">
            <div class="p-3">
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
                <div class="cart-total p-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span id="subtotal">KES 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax (<span id="taxRate">0</span>%):</span>
                        <span id="taxAmount">KES 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <strong>Total:</strong>
                        <strong class="text-primary fs-4" id="total">KES 0.00</strong>
                    </div>

                    <div class="mb-3">
                        <select id="paymentMethod" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>

                    <div class="d-grid gap-2">
                        <button class="btn btn-primary btn-lg" onclick="completeSale()" id="checkoutBtn" disabled>
                            <i class="bi bi-check2-circle me-2"></i>Complete Sale
                        </button>
                        <button class="btn btn-outline-danger" onclick="clearCart()">
                            <i class="bi bi-trash me-2"></i>Clear Cart
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];
const TAX_RATE = 16; // Default tax rate percentage

// Search products
document.getElementById('searchProduct').addEventListener('input', filterProducts);
document.getElementById('filterCategory').addEventListener('change', filterProducts);

function filterProducts() {
    const searchTerm = document.getElementById('searchProduct').value.toLowerCase();
    const categoryFilter = document.getElementById('filterCategory').value;
    const products = document.querySelectorAll('.product-item');
    
    products.forEach(product => {
        const name = product.dataset.name;
        const sku = product.dataset.sku;
        const barcode = product.dataset.barcode;
        const category = product.dataset.category;
        
        const matchesSearch = name.includes(searchTerm) || sku.includes(searchTerm) || barcode.includes(searchTerm);
        const matchesCategory = !categoryFilter || category === categoryFilter;
        
        product.style.display = matchesSearch && matchesCategory ? '' : 'none';
    });
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
        document.getElementById('checkoutBtn').disabled = true;
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
                            <div>KES ${item.price.toFixed(2)} × ${item.quantity}</div>
                            <strong>KES ${item.total.toFixed(2)}</strong>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        cartDiv.innerHTML = html;
        document.getElementById('checkoutBtn').disabled = false;
    }
    
    // Calculate totals
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const taxAmount = subtotal * (TAX_RATE / 100);
    const total = subtotal + taxAmount;
    
    document.getElementById('subtotal').textContent = 'KES ' + subtotal.toFixed(2);
    document.getElementById('taxRate').textContent = TAX_RATE;
    document.getElementById('taxAmount').textContent = 'KES ' + taxAmount.toFixed(2);
    document.getElementById('total').textContent = 'KES ' + total.toFixed(2);
}

function clearCart() {
    if (cart.length > 0 && confirm('Clear all items from cart?')) {
        cart = [];
        updateCart();
    }
}

async function completeSale() {
    if (cart.length === 0) {
        alert('Cart is empty');
        return;
    }
    
    const customerName = document.getElementById('customerName').value || null;
    const paymentMethod = document.getElementById('paymentMethod').value;
    const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
    const taxAmount = subtotal * (TAX_RATE / 100);
    const total = subtotal + taxAmount;
    
    const saleData = {
        customer_name: customerName,
        payment_method: paymentMethod,
        subtotal: subtotal,
        tax_amount: taxAmount,
        total_amount: total,
        amount_paid: total,
        items: cart
    };
    
    try {
        const response = await fetch('api/complete-sale.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(saleData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Sale completed successfully! Sale #: ' + result.sale_number);
            cart = [];
            updateCart();
            document.getElementById('customerName').value = '';
            
            // Ask if want to print receipt
            if (confirm('Print receipt?')) {
                window.open('print-receipt.php?id=' + result.sale_id, '_blank');
            }
        } else {
            alert('Error: ' + (result.message || 'Failed to complete sale'));
        }
    } catch (error) {
        alert('Error completing sale: ' + error.message);
    }
}

// Barcode scanner support
document.addEventListener('keypress', function(e) {
    if (e.target.tagName !== 'INPUT') {
        document.getElementById('searchProduct').focus();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
