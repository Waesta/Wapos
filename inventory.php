<?php
require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager', 'inventory_manager']);

$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation for all inventory POST actions
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        showAlert('Invalid request. Please try again.', 'error');
    } else {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'adjust_stock') {
            $productId = $_POST['product_id'];
            $adjustmentType = $_POST['adjustment_type']; // 'in', 'out', 'transfer', 'damaged'
            $quantity = (float)$_POST['quantity'];
            $reason = sanitizeInput($_POST['reason']);
            $notes = sanitizeInput($_POST['notes'] ?? '');
            
            // Get current stock
            $product = $db->fetchOne("SELECT * FROM products WHERE id = ?", [$productId]);
            if (!$product) {
                throw new Exception('Product not found');
            }
            
            $oldQuantity = $product['stock_quantity'];
            $newQuantity = $oldQuantity;
            
            switch ($adjustmentType) {
                case 'in':
                    $newQuantity = $oldQuantity + $quantity;
                    break;
                case 'out':
                case 'damaged':
                    $newQuantity = $oldQuantity - $quantity;
                    break;
                case 'set':
                    $newQuantity = $quantity;
                    break;
            }
            
            // Update product stock
            $db->update('products', 
                ['stock_quantity' => $newQuantity], 
                'id = :id', 
                ['id' => $productId]
            );
            
            // Log stock movement
            $db->insert('stock_movements', [
                'product_id' => $productId,
                'movement_type' => $adjustmentType,
                'quantity' => $quantity,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'reason' => $reason,
                'notes' => $notes,
                'user_id' => $auth->getUserId(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $_SESSION['success_message'] = 'Stock adjusted successfully';
            
        } elseif ($action === 'set_reorder_level') {
            $productId = $_POST['product_id'];
            $reorderLevel = (float)$_POST['reorder_level'];
            $reorderQuantity = (float)$_POST['reorder_quantity'];
            
            $db->update('products', [
                'reorder_level' => $reorderLevel,
                'reorder_quantity' => $reorderQuantity
            ], 'id = :id', ['id' => $productId]);
            
            $_SESSION['success_message'] = 'Reorder levels updated successfully';
            
        } elseif ($action === 'create_purchase_order') {
            $supplierId = $_POST['supplier_id'];
            $items = json_decode($_POST['items'], true);
            
            // Validate items
            if (empty($items) || !is_array($items)) {
                throw new Exception('Please add at least one item to the purchase order');
            }
            
            // Create purchase order
            $poNumber = 'PO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            $poId = $db->insert('purchase_orders', [
                'po_number' => $poNumber,
                'supplier_id' => $supplierId,
                'status' => 'pending',
                'total_amount' => 0,
                'created_by' => $auth->getUserId(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$poId) {
                throw new Exception('Failed to create purchase order');
            }
            
            $totalAmount = 0;
            foreach ($items as $item) {
                $subtotal = $item['quantity'] * $item['unit_cost'];
                $totalAmount += $subtotal;
                
                $db->insert('purchase_order_items', [
                    'purchase_order_id' => $poId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'subtotal' => $subtotal
                ]);
            }
            
            // Update total amount
            $db->update('purchase_orders', 
                ['total_amount' => $totalAmount], 
                'id = :id', 
                ['id' => $poId]
            );
            
            $_SESSION['success_message'] = "Purchase Order $poNumber created successfully";
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
    redirect($_SERVER['PHP_SELF']);
    }
}

// Get inventory data with error handling
try {
    $lowStockProducts = $db->fetchAll("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.stock_quantity <= p.reorder_level 
        ORDER BY p.stock_quantity ASC
    ");
    $lowStockProducts = $lowStockProducts ?: [];
} catch (Exception $e) {
    $lowStockProducts = [];
}

try {
    $recentMovements = $db->fetchAll("
        SELECT sm.*, p.name as product_name, u.username 
        FROM stock_movements sm 
        JOIN products p ON sm.product_id = p.id 
        JOIN users u ON sm.user_id = u.id 
        ORDER BY sm.created_at DESC 
        LIMIT 20
    ") ?: [];
} catch (Exception $e) {
    $recentMovements = [];
}

try {
    $suppliers = $db->fetchAll("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name") ?: [];
} catch (Exception $e) {
    $suppliers = [];
}

try {
    $categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name") ?: [];
} catch (Exception $e) {
    $categories = [];
}

$pageTitle = 'Inventory Management';
include 'includes/header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">
            <i class="bi bi-boxes me-2"></i>Inventory Management
        </h4>
        <p class="text-muted mb-0">Stock control, movements, and purchase orders</p>
    </div>
    <div>
        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#stockAdjustmentModal">
            <i class="bi bi-plus-circle me-2"></i>Stock Adjustment
        </button>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#purchaseOrderModal">
            <i class="bi bi-cart-plus me-2"></i>Create Purchase Order
        </button>
    </div>
</div>

<!-- Inventory Overview Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-exclamation-triangle text-warning fs-1 mb-2"></i>
                <h3 class="text-warning"><?= count($lowStockProducts) ?></h3>
                <p class="text-muted mb-0">Low Stock Items</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-arrow-up-circle text-success fs-1 mb-2"></i>
                <h3 class="text-success">
                    <?php
                    try {
                        $inMovements = $db->fetchOne("SELECT COUNT(*) as count FROM stock_movements WHERE movement_type = 'in' AND DATE(created_at) = CURDATE()");
                        echo ($inMovements && isset($inMovements['count'])) ? $inMovements['count'] : 0;
                    } catch (Exception $e) {
                        echo '0';
                    }
                    ?>
                </h3>
                <p class="text-muted mb-0">Stock In Today</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-arrow-down-circle text-danger fs-1 mb-2"></i>
                <h3 class="text-danger">
                    <?php
                    try {
                        $outMovements = $db->fetchOne("SELECT COUNT(*) as count FROM stock_movements WHERE movement_type IN ('out', 'damaged') AND DATE(created_at) = CURDATE()");
                        echo ($outMovements && isset($outMovements['count'])) ? $outMovements['count'] : 0;
                    } catch (Exception $e) {
                        echo '0';
                    }
                    ?>
                </h3>
                <p class="text-muted mb-0">Stock Out Today</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-currency-dollar text-info fs-1 mb-2"></i>
                <h3 class="text-info">
                    <?php
                    try {
                        $totalValue = $db->fetchOne("SELECT SUM(stock_quantity * cost_price) as total FROM products WHERE is_active = 1");
                        echo formatMoney(($totalValue && isset($totalValue['total'])) ? $totalValue['total'] : 0);
                    } catch (Exception $e) {
                        echo formatMoney(0);
                    }
                    ?>
                </h3>
                <p class="text-muted mb-0">Total Stock Value</p>
            </div>
        </div>
    </div>
</div>

<!-- Low Stock Alert -->
<?php if (!empty($lowStockProducts)): ?>
<div class="alert alert-warning">
    <h5><i class="bi bi-exclamation-triangle me-2"></i>Low Stock Alert</h5>
    <p>The following items are running low and may need reordering:</p>
    <div class="row">
        <?php foreach (array_slice($lowStockProducts, 0, 6) as $product): ?>
        <div class="col-md-4 mb-2">
            <strong><?= htmlspecialchars($product['name']) ?></strong><br>
            <small>Current: <?= $product['stock_quantity'] ?> | Reorder at: <?= $product['reorder_level'] ?></small>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#stock-levels">
            <i class="bi bi-boxes me-2"></i>Stock Levels
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#movements">
            <i class="bi bi-arrow-left-right me-2"></i>Stock Movements
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#purchase-orders">
            <i class="bi bi-cart me-2"></i>Purchase Orders
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#suppliers">
            <i class="bi bi-building me-2"></i>Suppliers
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- Stock Levels Tab -->
    <div class="tab-pane fade show active" id="stock-levels">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">Current Stock Levels</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Reorder Level</th>
                                <th>Unit Cost</th>
                                <th>Stock Value</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $products = $db->fetchAll("
                                SELECT p.*, c.name as category_name 
                                FROM products p 
                                LEFT JOIN categories c ON p.category_id = c.id 
                                WHERE p.is_active = 1 
                                ORDER BY p.name
                            ");
                            
                            foreach ($products as $product):
                                $stockValue = $product['stock_quantity'] * $product['cost_price'];
                                $status = 'normal';
                                $statusClass = 'success';
                                
                                if ($product['stock_quantity'] <= 0) {
                                    $status = 'out of stock';
                                    $statusClass = 'danger';
                                } elseif ($product['stock_quantity'] <= $product['reorder_level']) {
                                    $status = 'low stock';
                                    $statusClass = 'warning';
                                }
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($product['name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($product['sku']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></td>
                                <td>
                                    <span class="badge bg-<?= $statusClass ?>"><?= $product['stock_quantity'] ?></span>
                                    <?= $product['unit'] ?>
                                </td>
                                <td><?= $product['reorder_level'] ?></td>
                                <td><?= formatMoney($product['cost_price']) ?></td>
                                <td><?= formatMoney($stockValue) ?></td>
                                <td>
                                    <span class="badge bg-<?= $statusClass ?>"><?= $status ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="adjustStock(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>')">
                                        <i class="bi bi-plus-minus"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="setReorderLevel(<?= $product['id'] ?>, <?= $product['reorder_level'] ?>, <?= $product['reorder_quantity'] ?>)">
                                        <i class="bi bi-gear"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stock Movements Tab -->
    <div class="tab-pane fade" id="movements">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">Recent Stock Movements</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Product</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Before</th>
                                <th>After</th>
                                <th>Reason</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentMovements as $movement): ?>
                            <tr>
                                <td><?= formatDate($movement['created_at'], 'd/m/Y H:i') ?></td>
                                <td><?= htmlspecialchars($movement['product_name']) ?></td>
                                <td>
                                    <?php
                                    $typeClass = [
                                        'in' => 'success',
                                        'out' => 'primary',
                                        'damaged' => 'danger',
                                        'transfer' => 'info'
                                    ];
                                    $class = $typeClass[$movement['movement_type']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $class ?>"><?= ucfirst($movement['movement_type']) ?></span>
                                </td>
                                <td>
                                    <?= $movement['movement_type'] === 'out' || $movement['movement_type'] === 'damaged' ? '-' : '+' ?>
                                    <?= $movement['quantity'] ?>
                                </td>
                                <td><?= $movement['old_quantity'] ?></td>
                                <td><?= $movement['new_quantity'] ?></td>
                                <td><?= htmlspecialchars($movement['reason']) ?></td>
                                <td><?= htmlspecialchars($movement['username']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Purchase Orders Tab -->
    <div class="tab-pane fade" id="purchase-orders">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">Purchase Orders</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>PO Number</th>
                                <th>Supplier</th>
                                <th>Date</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $purchaseOrders = $db->fetchAll("
                                SELECT po.*, s.name as supplier_name 
                                FROM purchase_orders po 
                                LEFT JOIN suppliers s ON po.supplier_id = s.id 
                                ORDER BY po.created_at DESC 
                                LIMIT 20
                            ");
                            
                            foreach ($purchaseOrders as $po):
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($po['po_number']) ?></td>
                                <td><?= htmlspecialchars($po['supplier_name']) ?></td>
                                <td><?= formatDate($po['created_at'], 'd/m/Y') ?></td>
                                <td><?= formatMoney($po['total_amount']) ?></td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'pending' => 'warning',
                                        'approved' => 'info',
                                        'ordered' => 'primary',
                                        'received' => 'success',
                                        'cancelled' => 'danger'
                                    ];
                                    $class = $statusClass[$po['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $class ?>"><?= ucfirst($po['status']) ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewPurchaseOrder(<?= $po['id'] ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Suppliers Tab -->
    <div class="tab-pane fade" id="suppliers">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Suppliers</h6>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal">
                    <i class="bi bi-plus me-2"></i>Add Supplier
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $supplier): ?>
                            <tr>
                                <td><?= htmlspecialchars($supplier['name']) ?></td>
                                <td><?= htmlspecialchars($supplier['contact_person']) ?></td>
                                <td><?= htmlspecialchars($supplier['email']) ?></td>
                                <td><?= htmlspecialchars($supplier['phone']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $supplier['is_active'] ? 'success' : 'secondary' ?>">
                                        <?= $supplier['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editSupplier(<?= $supplier['id'] ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal fade" id="stockAdjustmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Stock Adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select Product</option>
                            <?php
                            $allProducts = $db->fetchAll("SELECT id, name, sku FROM products WHERE is_active = 1 ORDER BY name");
                            foreach ($allProducts as $product):
                            ?>
                            <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?> (<?= $product['sku'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Adjustment Type</label>
                        <select name="adjustment_type" class="form-select" required>
                            <option value="in">Stock In</option>
                            <option value="out">Stock Out</option>
                            <option value="damaged">Damaged/Loss</option>
                            <option value="set">Set Quantity</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" class="form-control" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <select name="reason" class="form-select" required>
                            <option value="purchase">Purchase/Delivery</option>
                            <option value="sale">Sale</option>
                            <option value="return">Return</option>
                            <option value="damaged">Damaged</option>
                            <option value="expired">Expired</option>
                            <option value="theft">Theft/Loss</option>
                            <option value="transfer">Transfer</option>
                            <option value="adjustment">Manual Adjustment</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Adjust Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Purchase Order Modal -->
<div class="modal fade" id="purchaseOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="purchaseOrderForm" onsubmit="return validatePOForm()">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_purchase_order">
                    <input type="hidden" name="items" id="poItems" value="[]">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Items</h6>
                        <div id="poItemsList">
                            <div class="row g-2 mb-2">
                                <div class="col-md-5">
                                    <select class="form-select po-product" required>
                                        <option value="">Select Product</option>
                                        <?php foreach ($allProducts as $product): ?>
                                        <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control po-quantity" placeholder="Qty" step="0.01" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" class="form-control po-cost" placeholder="Unit Cost" step="0.01" required>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-success" onclick="addPOItem()">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div id="selectedItems"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Purchase Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let poItems = [];

function addPOItem() {
    const productSelect = document.querySelector('.po-product');
    const quantityInput = document.querySelector('.po-quantity');
    const costInput = document.querySelector('.po-cost');
    
    if (productSelect.value && quantityInput.value && costInput.value) {
        const item = {
            product_id: productSelect.value,
            product_name: productSelect.options[productSelect.selectedIndex].text,
            quantity: parseFloat(quantityInput.value),
            unit_cost: parseFloat(costInput.value)
        };
        
        poItems.push(item);
        updatePOItemsDisplay();
        
        // Clear inputs
        productSelect.value = '';
        quantityInput.value = '';
        costInput.value = '';
    }
}

function updatePOItemsDisplay() {
    const container = document.getElementById('selectedItems');
    let html = '<h6>Selected Items:</h6>';
    let total = 0;
    
    poItems.forEach((item, index) => {
        const subtotal = item.quantity * item.unit_cost;
        total += subtotal;
        
        html += `
            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                <div>
                    <strong>${item.product_name}</strong><br>
                    <small>Qty: ${item.quantity} × ${formatMoney(item.unit_cost)} = ${formatMoney(subtotal)}</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePOItem(${index})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
    });
    
    html += `<div class="text-end"><strong>Total: ${formatMoney(total)}</strong></div>`;
    container.innerHTML = html;
    
    document.getElementById('poItems').value = JSON.stringify(poItems);
}

function removePOItem(index) {
    poItems.splice(index, 1);
    updatePOItemsDisplay();
}

function formatMoney(amount) {
    return parseFloat(amount).toFixed(2);
}

function adjustStock(productId, productName) {
    const modal = new bootstrap.Modal(document.getElementById('stockAdjustmentModal'));
    document.querySelector('[name="product_id"]').value = productId;
    modal.show();
}

function setReorderLevel(productId, currentLevel, currentQuantity) {
    // Implementation for setting reorder levels
    console.log('Set reorder level for product', productId);
}

function validatePOForm() {
    if (poItems.length === 0) {
        alert('Please add at least one item to the purchase order');
        return false;
    }
    return true;
}
</script>

<?php include 'includes/footer.php'; ?>
