<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation for all POST actions
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Invalid request. Please try again.';
        redirect($_SERVER['PHP_SELF']);
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $data = [
            'category_id' => $_POST['category_id'] ?: null,
            'name' => sanitizeInput($_POST['name']),
            'description' => sanitizeInput($_POST['description']),
            'sku' => sanitizeInput($_POST['sku']),
            'barcode' => sanitizeInput($_POST['barcode']) ?: null,
            'cost_price' => $_POST['cost_price'] ?: 0,
            'selling_price' => $_POST['selling_price'],
            'stock_quantity' => $_POST['stock_quantity'] ?: 0,
            'min_stock_level' => $_POST['min_stock_level'] ?: 10,
            'unit' => sanitizeInput($_POST['unit']) ?: 'pcs',
            'tax_rate' => $_POST['tax_rate'] ?: 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        if ($action === 'add') {
            try {
                $result = $db->insert('products', $data);
                if ($result) {
                    $_SESSION['success_message'] = 'Product added successfully';
                } else {
                    throw new Exception('Insert operation returned false');
                }
            } catch (Exception $e) {
                error_log('Failed to add product: ' . $e->getMessage());
                error_log('Product data: ' . print_r($data, true));
                $_SESSION['error_message'] = 'Failed to add product. Please check error logs for details.';
            }
        } else {
            $id = $_POST['id'];
            if ($db->update('products', $data, 'id = :id', ['id' => $id])) {
                $_SESSION['success_message'] = 'Product updated successfully';
            } else {
                $_SESSION['error_message'] = 'Failed to update product';
            }
        }
        redirect($_SERVER['PHP_SELF']);
    }
    
    if ($action === 'delete') {
        $id = $_POST['id'];
        if ($db->delete('products', 'id = ?', [$id])) {
            $_SESSION['success_message'] = 'Product deleted successfully';
        } else {
            $_SESSION['error_message'] = 'Failed to delete product';
        }
        redirect($_SERVER['PHP_SELF']);
    }
}

// Get products with category and supplier names
$products = $db->fetchAll("
    SELECT p.*, c.name as category_name, s.name as supplier_name
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    ORDER BY p.name
");

$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$suppliers = $db->fetchAll("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name");

// Catalog metrics & health indicators
$totalProducts = count($products);
$activeProducts = 0;
$inactiveProducts = 0;
$lowStockProducts = [];
$outOfStockProducts = [];
$totalStockValue = 0;
$totalCostValue = 0;
$totalSellingValue = 0;
$categoryBreakdown = [];
$negativeMarginProducts = [];

foreach ($products as $product) {
    $isActive = (int)$product['is_active'] === 1;
    $stockQty = (float)($product['stock_quantity'] ?? 0);
    $minLevel = (float)($product['min_stock_level'] ?? 0);
    $cost = (float)($product['cost_price'] ?? 0);
    $price = (float)($product['selling_price'] ?? 0);
    $margin = $price - $cost;
    $stockValue = $stockQty * $cost;

    if ($isActive) {
        $activeProducts++;
    } else {
        $inactiveProducts++;
    }

    if ($stockQty <= $minLevel) {
        $lowStockProducts[] = $product;
    }

    if ($stockQty <= 0) {
        $outOfStockProducts[] = $product;
    }

    if ($price > 0) {
        $totalSellingValue += $price * $stockQty;
    }
    if ($cost > 0) {
        $totalCostValue += $cost * $stockQty;
    }

    $totalStockValue += $stockValue;

    if ($margin < 0.01) {
        $negativeMarginProducts[] = $product;
    }

    $categoryKey = $product['category_name'] ?? 'Uncategorized';
    if (!isset($categoryBreakdown[$categoryKey])) {
        $categoryBreakdown[$categoryKey] = [
            'count' => 0,
            'stock_value' => 0,
            'active' => 0,
        ];
    }

    $categoryBreakdown[$categoryKey]['count']++;
    $categoryBreakdown[$categoryKey]['stock_value'] += $stockValue;
    if ($isActive) {
        $categoryBreakdown[$categoryKey]['active']++;
    }
}

ksort($categoryBreakdown);

$averageMargin = $totalProducts > 0 ? ($totalSellingValue - $totalCostValue) / ($totalProducts ?: 1) : 0;
$marginPercent = $totalCostValue > 0 ? (($totalSellingValue - $totalCostValue) / $totalCostValue) * 100 : 0;
$lowStockCount = count($lowStockProducts);
$outOfStockCount = count($outOfStockProducts);
$negativeMarginCount = count($negativeMarginProducts);

usort($lowStockProducts, function ($a, $b) {
    return ($a['stock_quantity'] <=> $b['stock_quantity']);
});

$topCategories = array_slice($categoryBreakdown, 0, 5, true);

$pageTitle = 'Products';
include 'includes/header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h4 class="mb-0"><i class="bi bi-box-seam me-2"></i>Products Management</h4>
        <small class="text-muted">Maintain catalog data, margins, and stock health</small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" onclick="location.href='inventory.php'">
            <i class="bi bi-arrow-left-right me-2"></i>Inventory Ops
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetForm()">
            <i class="bi bi-plus-circle me-2"></i>Add Product
        </button>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted text-uppercase">Active Catalog</small>
                <h3 class="mb-0"><?= $activeProducts ?> / <?= $totalProducts ?></h3>
                <span class="badge bg-success-subtle text-success mt-2">Active</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted text-uppercase">Stock Value</small>
                <h3 class="mb-0"><?= formatMoney($totalStockValue) ?></h3>
                <span class="badge bg-primary-subtle text-primary mt-2">On Hand</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted text-uppercase">Margin Health</small>
                <h3 class="mb-0"><?= formatMoney($averageMargin) ?></h3>
                <span class="badge bg-info-subtle text-info mt-2">Avg / item · <?= number_format($marginPercent, 1) ?>%</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <small class="text-muted text-uppercase">Exceptions</small>
                <h3 class="mb-0"><?= $lowStockCount + $negativeMarginCount ?></h3>
                <span class="badge bg-warning-subtle text-warning mt-2">Low Stock: <?= $lowStockCount ?></span>
                <span class="badge bg-danger-subtle text-danger ms-2">Negative Margin: <?= $negativeMarginCount ?></span>
            </div>
        </div>
    </div>
</div>

<?php if ($lowStockCount > 0): ?>
<div class="alert alert-warning border-0 shadow-sm">
    <div class="d-flex align-items-start gap-3">
        <i class="bi bi-exclamation-triangle-fill fs-3"></i>
        <div>
            <h5 class="mb-1">Low Stock Watchlist</h5>
            <p class="mb-2">Review reorder levels for these items to avoid stock-outs.</p>
            <div class="row g-2">
                <?php foreach (array_slice($lowStockProducts, 0, 6) as $product): ?>
                    <div class="col-md-4">
                        <strong><?= htmlspecialchars($product['name']) ?></strong>
                        <div class="small text-muted">On hand: <?= $product['stock_quantity'] ?> · Reorder at <?= $product['min_stock_level'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">Category Mix</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($topCategories)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topCategories as $category => $stats): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= htmlspecialchars($category) ?></strong>
                                <div class="small text-muted"><?= $stats['active'] ?> active of <?= $stats['count'] ?> products</div>
                            </div>
                            <span class="badge bg-light text-dark"><?= formatMoney($stats['stock_value']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0">No category data available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">Margin Exceptions</h6>
            </div>
            <div class="card-body">
                <?php if ($negativeMarginCount > 0): ?>
                <div class="table-responsive" style="max-height:220px;">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Cost</th>
                                <th>Price</th>
                                <th>Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($negativeMarginProducts, 0, 6) as $product): ?>
                            <?php $margin = $product['selling_price'] - $product['cost_price']; ?>
                            <tr>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= formatMoney($product['cost_price']) ?></td>
                                <td><?= formatMoney($product['selling_price']) ?></td>
                                <td class="text-danger"><?= formatMoney($margin) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No negative margin products detected.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <h5 class="mb-0">Product Catalog</h5>
        <div class="d-flex flex-wrap gap-2">
            <div class="input-group input-group-sm" style="max-width:220px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="productSearch" placeholder="Search name or SKU">
            </div>
            <select class="form-select form-select-sm" id="categoryFilter" style="max-width:180px;">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select form-select-sm" id="statusFilter" style="max-width:160px;">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="low-stock">Low Stock</option>
            </select>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="productsTable">
                <thead class="table-light">
                    <tr>
                        <th>SKU</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Cost</th>
                        <th>Price</th>
                        <th>Margin</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <?php
                        $marginValue = ($product['selling_price'] ?? 0) - ($product['cost_price'] ?? 0);
                        $rowStatus = $product['is_active'] ? 'active' : 'inactive';
                        if ($product['stock_quantity'] <= $product['min_stock_level']) {
                            $rowStatus = 'low-stock';
                        }
                    ?>
                    <tr data-category="<?= htmlspecialchars($product['category_name'] ?? '') ?>" data-status="<?= $rowStatus ?>" data-search="<?= htmlspecialchars(strtolower($product['name'] . ' ' . $product['sku'])) ?>">
                        <td><?= htmlspecialchars($product['sku']) ?></td>
                        <td><strong><?= htmlspecialchars($product['name']) ?></strong></td>
                        <td><?= htmlspecialchars($product['category_name'] ?? '-') ?></td>
                        <td><?= formatMoney($product['cost_price']) ?></td>
                        <td class="fw-bold"><?= formatMoney($product['selling_price']) ?></td>
                        <td class="<?= $marginValue < 0 ? 'text-danger' : 'text-muted' ?>"><?= formatMoney($marginValue) ?></td>
                        <td>
                            <?php if ($product['stock_quantity'] <= $product['min_stock_level']): ?>
                                <span class="badge bg-warning text-dark"><?= $product['stock_quantity'] ?></span>
                            <?php else: ?>
                                <span class="badge bg-success"><?= $product['stock_quantity'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $product['is_active'] ? 'success' : 'secondary' ?>">
                                <?= $product['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick='editProduct(<?= json_encode($product) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="productsEmptyState" class="alert alert-info text-center" style="display:none;">
            No products match the current filters.
        </div>
    </div>
</div>

<!-- Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="productForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="productId">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Product Name *</label>
                            <input type="text" class="form-control" name="name" id="name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category_id" id="category_id">
                                <option value="">No Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">SKU *</label>
                            <input type="text" class="form-control" name="sku" id="sku" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Barcode</label>
                            <input type="text" class="form-control" name="barcode" id="barcode">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="description" rows="2"></textarea>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Cost Price</label>
                            <input type="number" step="0.01" class="form-control" name="cost_price" id="cost_price" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Selling Price *</label>
                            <input type="number" step="0.01" class="form-control" name="selling_price" id="selling_price" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tax Rate (%)</label>
                            <input type="number" step="0.01" class="form-control" name="tax_rate" id="tax_rate" value="16">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" name="stock_quantity" id="stock_quantity" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Min Stock Level</label>
                            <input type="number" class="form-control" name="min_stock_level" id="min_stock_level" value="10">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Unit</label>
                            <input type="text" class="form-control" name="unit" id="unit" value="pcs" placeholder="pcs, kg, ltr, box">
                        </div>
                        
                        
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const productSearchInput = document.getElementById('productSearch');
const categoryFilter = document.getElementById('categoryFilter');
const statusFilter = document.getElementById('statusFilter');
const productRows = Array.from(document.querySelectorAll('#productsTable tbody tr'));
const emptyState = document.getElementById('productsEmptyState');

function resetForm() {
    document.getElementById('productForm').reset();
    document.getElementById('formAction').value = 'add';
    document.getElementById('modalTitle').textContent = 'Add Product';
    document.getElementById('productId').value = '';
}

function editProduct(product) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('modalTitle').textContent = 'Edit Product';
    document.getElementById('productId').value = product.id;
    document.getElementById('name').value = product.name;
    document.getElementById('category_id').value = product.category_id || '';
    document.getElementById('sku').value = product.sku;
    document.getElementById('barcode').value = product.barcode || '';
    document.getElementById('description').value = product.description || '';
    document.getElementById('cost_price').value = product.cost_price;
    document.getElementById('selling_price').value = product.selling_price;
    document.getElementById('tax_rate').value = product.tax_rate;
    document.getElementById('stock_quantity').value = product.stock_quantity;
    document.getElementById('min_stock_level').value = product.min_stock_level;
    document.getElementById('unit').value = product.unit;
    document.getElementById('is_active').checked = product.is_active == 1;

    new bootstrap.Modal(document.getElementById('productModal')).show();
}

function deleteProduct(id, name) {
    if (confirm(`Delete product "${name}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function filterProducts() {
    const searchTerm = (productSearchInput?.value || '').trim().toLowerCase();
    const selectedCategory = (categoryFilter?.value || '').toLowerCase();
    const selectedStatus = statusFilter?.value || '';

    let visibleCount = 0;

    productRows.forEach(row => {
        const matchesSearch = !searchTerm || (row.dataset.search || '').includes(searchTerm);
        const matchesCategory = !selectedCategory || (row.dataset.category || '').toLowerCase() === selectedCategory;
        const matchesStatus = !selectedStatus || row.dataset.status === selectedStatus;

        if (matchesSearch && matchesCategory && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (emptyState) {
        emptyState.style.display = visibleCount === 0 ? '' : 'none';
    }

    return visibleCount;
}

document.addEventListener('DOMContentLoaded', () => {
    productSearchInput?.addEventListener('input', debounce(filterProducts, 150));
    categoryFilter?.addEventListener('change', filterProducts);
    statusFilter?.addEventListener('change', filterProducts);
    filterProducts();
});

function debounce(fn, delay) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn.apply(this, args), delay);
    };
}
</script>

<?php include 'includes/footer.php'; ?>
