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

$pageTitle = 'Products';
include 'includes/header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-box-seam me-2"></i>Products Management</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetForm()">
        <i class="bi bi-plus-circle me-2"></i>Add Product
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>SKU</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Cost</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= htmlspecialchars($product['sku']) ?></td>
                        <td><strong><?= htmlspecialchars($product['name']) ?></strong></td>
                        <td><?= htmlspecialchars($product['category_name'] ?? '-') ?></td>
                        <td>KES <?= formatMoney($product['cost_price']) ?></td>
                        <td class="fw-bold">KES <?= formatMoney($product['selling_price']) ?></td>
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
</script>

<?php include 'includes/footer.php'; ?>
