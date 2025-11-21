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

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h4 class="mb-0">
            <i class="bi bi-boxes me-2"></i>Inventory Management
        </h4>
        <small class="text-muted">Stock control, movements, and purchase orders</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-secondary" onclick="location.href='products.php'">
            <i class="bi bi-box-seam me-2"></i>Catalog
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#stockAdjustmentModal">
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
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <small class="text-muted text-uppercase">Low Stock Items</small>
                <h3 class="mb-0 text-warning"><?= count($lowStockProducts) ?></h3>
                <span class="badge bg-warning-subtle text-warning mt-2">Watchlist</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <small class="text-muted text-uppercase">Stock In Today</small>
                <h3 class="mb-0 text-success">
                    <?php
                    try {
                        $inMovements = $db->fetchOne("SELECT COUNT(*) as count FROM stock_movements WHERE movement_type = 'in' AND DATE(created_at) = CURDATE()");
                        echo ($inMovements && isset($inMovements['count'])) ? $inMovements['count'] : 0;
                    } catch (Exception $e) {
                        echo '0';
                    }
                    ?>
                </h3>
                <span class="badge bg-success-subtle text-success mt-2">Receipts</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <small class="text-muted text-uppercase">Stock Out Today</small>
                <h3 class="mb-0 text-danger">
                    <?php
                    try {
                        $outMovements = $db->fetchOne("SELECT COUNT(*) as count FROM stock_movements WHERE movement_type IN ('out', 'damaged') AND DATE(created_at) = CURDATE()");
                        echo ($outMovements && isset($outMovements['count'])) ? $outMovements['count'] : 0;
                    } catch (Exception $e) {
                        echo '0';
                    }
                    ?>
                </h3>
                <span class="badge bg-danger-subtle text-danger mt-2">Issues</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <small class="text-muted text-uppercase">Total Stock Value</small>
                <h3 class="mb-0 text-info">
                    <?php
                    try {
                        $totalValue = $db->fetchOne("SELECT SUM(stock_quantity * cost_price) as total FROM products WHERE is_active = 1");
                        echo formatMoney(($totalValue && isset($totalValue['total'])) ? $totalValue['total'] : 0);
                    } catch (Exception $e) {
                        echo formatMoney(0);
                    }
                    ?>
                </h3>
                <span class="badge bg-info-subtle text-info mt-2">On Hand</span>
            </div>
        </div>
    </div>

    <!-- Insights Tab -->
    <div class="tab-pane fade" id="insights">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">Top Movers (7 days)</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $topMovers = $db->fetchAll("
                            SELECT p.name, p.sku,
                                   SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END) AS total_in,
                                   SUM(CASE WHEN sm.movement_type IN ('out','damaged') THEN sm.quantity ELSE 0 END) AS total_out
                            FROM stock_movements sm
                            JOIN products p ON sm.product_id = p.id
                            WHERE sm.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                            GROUP BY sm.product_id
                            ORDER BY total_out DESC
                            LIMIT 5
                        ") ?: [];
                        ?>
                        <?php if (!empty($topMovers)): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($topMovers as $mover): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($mover['name']) ?></strong>
                                    <div class="small text-muted"><?= htmlspecialchars($mover['sku']) ?></div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success-subtle text-success">In: <?= (float)$mover['total_in'] ?></span>
                                    <span class="badge bg-danger-subtle text-danger ms-2">Out: <?= (float)$mover['total_out'] ?></span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                            <p class="text-muted mb-0">Not enough movement data this week.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">Reorder Recommendations</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($lowStockProducts)): ?>
                        <div class="table-responsive" style="max-height:240px;">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Current</th>
                                        <th>Reorder</th>
                                        <th>Suggested Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($lowStockProducts, 0, 10) as $product): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['name']) ?></td>
                                        <td><?= $product['stock_quantity'] ?></td>
                                        <td><?= $product['reorder_level'] ?></td>
                                        <td><?= $product['reorder_quantity'] ?: max(1, $product['reorder_level'] * 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">All products are above reorder levels.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Low Stock Alert -->
<?php if (!empty($lowStockProducts)): ?>
<div class="alert alert-warning border-0 shadow-sm">
    <div class="d-flex align-items-start gap-3">
        <i class="bi bi-exclamation-triangle-fill fs-3"></i>
        <div>
            <h5 class="mb-1">Low Stock Alert</h5>
            <p class="mb-2">The following items are running low and may need reordering:</p>
            <div class="row g-2">
                <?php foreach (array_slice($lowStockProducts, 0, 6) as $product): ?>
                <div class="col-md-4">
                    <strong><?= htmlspecialchars($product['name']) ?></strong><br>
                    <small class="text-muted">Current: <?= $product['stock_quantity'] ?> | Reorder at: <?= $product['reorder_level'] ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
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
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#insights">
            <i class="bi bi-bar-chart-line me-2"></i>Insights
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- Stock Levels Tab -->
    <div class="tab-pane fade show active" id="stock-levels">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <h6 class="mb-0">Current Stock Levels</h6>
                <div class="d-flex flex-wrap gap-2">
                    <div class="input-group input-group-sm" style="max-width:220px;">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="inventorySearch" placeholder="Search product or SKU">
                    </div>
                    <select class="form-select form-select-sm" id="inventoryCategoryFilter" style="max-width:180px;">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category['name']) ?>"><?= htmlspecialchars($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm" id="inventoryStatusFilter" style="max-width:160px;">
                        <option value="">All Status</option>
                        <option value="normal">Normal</option>
                        <option value="low stock">Low Stock</option>
                        <option value="out of stock">Out of Stock</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="inventoryTable">
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
                            <tr data-search="<?= htmlspecialchars(strtolower($product['name'] . ' ' . $product['sku'])) ?>" data-category="<?= htmlspecialchars($product['category_name'] ?? '') ?>" data-status="<?= $status ?>">
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
                    <div id="inventoryEmptyState" class="alert alert-info text-center" style="display:none;">
                        No products match the selected filters.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stock Movements Tab -->
    <div class="tab-pane fade" id="movements">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <h6 class="mb-0">Recent Stock Movements</h6>
                <button class="btn btn-sm btn-outline-secondary" onclick="window.open('export-stock-movements.php', '_blank')">
                    <i class="bi bi-download"></i> Export CSV
                </button>
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
                                    <select class="form-select po-product">
                                        <option value="">Select Product</option>
                                        <?php foreach ($allProducts as $product): ?>
                                        <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control po-quantity" placeholder="Qty" step="0.01" min="0.01">
                                </div>
                                <div class="col-md-3">
                                    <input type="number" class="form-control po-cost" placeholder="Unit Cost" step="0.01" min="0.01">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-success w-100" onclick="addPOItem()">
                                        <i class="bi bi-plus"></i> Add
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

function editSupplier(id) {
    window.location.href = 'supplier-management.php?id=' + id;
}

const inventorySearchInput = document.getElementById('inventorySearch');
const inventoryCategoryFilter = document.getElementById('inventoryCategoryFilter');
const inventoryStatusFilter = document.getElementById('inventoryStatusFilter');
const inventoryRows = Array.from(document.querySelectorAll('#inventoryTable tbody tr'));
const inventoryEmptyState = document.getElementById('inventoryEmptyState');

function filterInventory() {
    const searchTerm = (inventorySearchInput?.value || '').trim().toLowerCase();
    const category = (inventoryCategoryFilter?.value || '').toLowerCase();
    const status = (inventoryStatusFilter?.value || '');

    let visible = 0;

    inventoryRows.forEach(row => {
        const matchesSearch = !searchTerm || (row.dataset.search || '').includes(searchTerm);
        const matchesCategory = !category || (row.dataset.category || '').toLowerCase() === category;
        const matchesStatus = !status || (row.dataset.status || '') === status;

        if (matchesSearch && matchesCategory && matchesStatus) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    if (inventoryEmptyState) {
        inventoryEmptyState.style.display = visible === 0 ? '' : 'none';
    }
}

function debounce(fn, delay) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn.apply(this, args), delay);
    };
}

document.addEventListener('DOMContentLoaded', () => {
    inventorySearchInput?.addEventListener('input', debounce(filterInventory, 150));
    inventoryCategoryFilter?.addEventListener('change', filterInventory);
    inventoryStatusFilter?.addEventListener('change', filterInventory);
    filterInventory();
});

function addPOItem() {
    const productSelect = document.querySelector('.po-product');
    const quantityInput = document.querySelector('.po-quantity');
    const costInput = document.querySelector('.po-cost');
    
    // Validate inputs
    if (!productSelect.value) {
        alert('Please select a product');
        productSelect.focus();
        return;
    }
    if (!quantityInput.value || parseFloat(quantityInput.value) <= 0) {
        alert('Please enter a valid quantity');
        quantityInput.focus();
        return;
    }
    if (!costInput.value || parseFloat(costInput.value) <= 0) {
        alert('Please enter a valid unit cost');
        costInput.focus();
        return;
    }
    
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
    productSelect.focus();
}

function updatePOItemsDisplay() {
    const container = document.getElementById('selectedItems');
    
    if (poItems.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No items added yet. Use the form above to add items.</div>';
        document.getElementById('poItems').value = '[]';
        return;
    }
    
    let html = '<div class="alert alert-success"><strong>Selected Items (' + poItems.length + '):</strong></div>';
    let total = 0;
    
    poItems.forEach((item, index) => {
        const subtotal = item.quantity * item.unit_cost;
        total += subtotal;
        
        html += `
            <div class="d-flex justify-content-between align-items-center mb-2 p-3 border rounded bg-light">
                <div>
                    <strong>${item.product_name}</strong><br>
                    <small class="text-muted">Qty: ${item.quantity} × KES ${formatMoney(item.unit_cost)} = KES ${formatMoney(subtotal)}</small>
                </div>
                <button type="button" class="btn btn-sm btn-danger" onclick="removePOItem(${index})">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </div>
        `;
    });
    
    html += `<div class="text-end mt-3 p-3 bg-primary text-white rounded"><h5 class="mb-0">Total: KES ${formatMoney(total)}</h5></div>`;
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

// Initialize the display when modal opens
document.getElementById('purchaseOrderModal').addEventListener('show.bs.modal', function () {
    poItems = [];
    updatePOItemsDisplay();
});
</script>

<?php include 'includes/footer.php'; ?>
