<?php
require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager', 'inventory_manager']);

$db = Database::getInstance();

// Handle GRN actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = 'Invalid request. Please try again.';
        redirect($_SERVER['PHP_SELF']);
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create_grn') {
            $poId = $_POST['po_id'] ?? null;
            $supplierId = $_POST['supplier_id'];
            $receivedDate = $_POST['received_date'];
            $invoiceNumber = sanitizeInput($_POST['invoice_number'] ?? '');
            $notes = sanitizeInput($_POST['notes'] ?? '');
            $items = json_decode($_POST['items'], true);
            
            if (empty($items) || !is_array($items)) {
                throw new Exception('Please add at least one item to the GRN');
            }
            
            // Generate GRN number
            $grnNumber = 'GRN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Create GRN
            $grnId = $db->insert('goods_received_notes', [
                'grn_number' => $grnNumber,
                'purchase_order_id' => $poId ?: null,
                'supplier_id' => $supplierId,
                'received_date' => $receivedDate,
                'invoice_number' => $invoiceNumber,
                'total_amount' => 0,
                'notes' => $notes,
                'status' => 'completed',
                'received_by' => $auth->getUserId(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$grnId) {
                throw new Exception('Failed to create GRN');
            }
            
            $totalAmount = 0;
            
            // Process each item
            foreach ($items as $item) {
                $receivedQty = (float)$item['received_quantity'];
                $unitCost = (float)$item['unit_cost'];
                $subtotal = $receivedQty * $unitCost;
                $totalAmount += $subtotal;
                
                // Insert GRN item
                $db->insert('grn_items', [
                    'grn_id' => $grnId,
                    'product_id' => $item['product_id'],
                    'ordered_quantity' => $item['ordered_quantity'] ?? 0,
                    'received_quantity' => $receivedQty,
                    'unit_cost' => $unitCost,
                    'subtotal' => $subtotal,
                    'expiry_date' => !empty($item['expiry_date']) ? $item['expiry_date'] : null,
                    'batch_number' => sanitizeInput($item['batch_number'] ?? ''),
                    'notes' => sanitizeInput($item['notes'] ?? '')
                ]);
                
                // Update product stock
                $product = $db->fetchOne("SELECT stock_quantity, cost_price FROM products WHERE id = ?", [$item['product_id']]);
                if ($product) {
                    $oldQty = (float)$product['stock_quantity'];
                    $newQty = $oldQty + $receivedQty;
                    
                    // Update stock and cost price
                    $db->update('products', [
                        'stock_quantity' => $newQty,
                        'cost_price' => $unitCost
                    ], 'id = :id', ['id' => $item['product_id']]);
                    
                    // Log stock movement
                    $db->insert('stock_movements', [
                        'product_id' => $item['product_id'],
                        'movement_type' => 'in',
                        'quantity' => $receivedQty,
                        'old_quantity' => $oldQty,
                        'new_quantity' => $newQty,
                        'reason' => 'Goods Received - ' . $grnNumber,
                        'notes' => 'Invoice: ' . $invoiceNumber,
                        'user_id' => $auth->getUserId(),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            // Update GRN total
            $db->update('goods_received_notes', 
                ['total_amount' => $totalAmount], 
                'id = :id', 
                ['id' => $grnId]
            );
            
            // Update PO status if linked
            if ($poId) {
                $db->update('purchase_orders', 
                    ['status' => 'received'], 
                    'id = :id', 
                    ['id' => $poId]
                );
            }
            
            $_SESSION['success_message'] = "GRN $grnNumber created successfully. Stock updated.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
    redirect($_SERVER['PHP_SELF']);
}

// Get GRNs
$grns = $db->fetchAll("
    SELECT g.*, s.name as supplier_name, u.full_name as received_by_name,
           po.po_number
    FROM goods_received_notes g
    LEFT JOIN suppliers s ON g.supplier_id = s.id
    LEFT JOIN users u ON g.received_by = u.id
    LEFT JOIN purchase_orders po ON g.purchase_order_id = po.id
    ORDER BY g.created_at DESC
    LIMIT 50
");

// Get pending purchase orders
$pendingPOs = $db->fetchAll("
    SELECT po.*, s.name as supplier_name
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    WHERE po.status IN ('pending', 'approved', 'ordered')
    ORDER BY po.created_at DESC
");

$suppliers = $db->fetchAll("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name");
$products = $db->fetchAll("SELECT id, name, sku, unit FROM products WHERE is_active = 1 ORDER BY name");

$pageTitle = 'Goods Received Notes';
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
            <i class="bi bi-truck me-2"></i>Goods Received Notes (GRN)
        </h4>
        <p class="text-muted mb-0">Receive goods and update inventory</p>
    </div>
    <div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#grnModal">
            <i class="bi bi-plus-circle me-2"></i>New GRN
        </button>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#grn-list">
            <i class="bi bi-list-ul me-2"></i>GRN List
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pending-pos">
            <i class="bi bi-hourglass-split me-2"></i>Pending POs (<?= count($pendingPOs) ?>)
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- GRN List Tab -->
    <div class="tab-pane fade show active" id="grn-list">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>GRN Number</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th>PO Number</th>
                                <th>Invoice</th>
                                <th>Total Amount</th>
                                <th>Received By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grns as $grn): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($grn['grn_number']) ?></strong></td>
                                <td><?= formatDate($grn['received_date'], 'd/m/Y') ?></td>
                                <td><?= htmlspecialchars($grn['supplier_name']) ?></td>
                                <td><?= htmlspecialchars($grn['po_number'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($grn['invoice_number'] ?: '-') ?></td>
                                <td><?= formatMoney($grn['total_amount']) ?></td>
                                <td><?= htmlspecialchars($grn['received_by_name']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $grn['status'] === 'completed' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($grn['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewGRN(<?= $grn['id'] ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <a href="print-grn.php?id=<?= $grn['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pending POs Tab -->
    <div class="tab-pane fade" id="pending-pos">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>PO Number</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingPOs as $po): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($po['po_number']) ?></strong></td>
                                <td><?= formatDate($po['created_at'], 'd/m/Y') ?></td>
                                <td><?= htmlspecialchars($po['supplier_name']) ?></td>
                                <td><?= formatMoney($po['total_amount']) ?></td>
                                <td>
                                    <span class="badge bg-warning"><?= ucfirst($po['status']) ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-success" onclick="receiveFromPO(<?= $po['id'] ?>, '<?= htmlspecialchars($po['po_number']) ?>')">
                                        <i class="bi bi-check-circle me-1"></i>Receive Goods
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

<!-- GRN Modal -->
<div class="modal fade" id="grnModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Goods Received Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="grnForm" onsubmit="return validateGRNForm()">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_grn">
                    <input type="hidden" name="po_id" id="poId">
                    <input type="hidden" name="items" id="grnItems" value="[]">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Supplier *</label>
                            <select name="supplier_id" id="supplierId" class="form-select" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Received Date *</label>
                            <input type="date" name="received_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Invoice Number</label>
                            <input type="text" name="invoice_number" class="form-control" placeholder="INV-12345">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <h6>Items</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <select class="form-select grn-product">
                                <option value="">Select Product</option>
                                <?php foreach ($products as $product): ?>
                                <option value="<?= $product['id'] ?>" data-unit="<?= htmlspecialchars($product['unit']) ?>">
                                    <?= htmlspecialchars($product['name']) ?> (<?= htmlspecialchars($product['sku']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="number" class="form-control grn-quantity" placeholder="Qty" step="0.01" min="0.01">
                        </div>
                        <div class="col-md-2">
                            <input type="number" class="form-control grn-cost" placeholder="Unit Cost" step="0.01" min="0.01">
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control grn-expiry" placeholder="Expiry (optional)">
                        </div>
                        <div class="col-md-2">
                            <input type="text" class="form-control grn-batch" placeholder="Batch #">
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-success w-100" onclick="addGRNItem()">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div id="grnItemsList"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create GRN & Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let grnItems = [];

function addGRNItem() {
    const productSelect = document.querySelector('.grn-product');
    const quantityInput = document.querySelector('.grn-quantity');
    const costInput = document.querySelector('.grn-cost');
    const expiryInput = document.querySelector('.grn-expiry');
    const batchInput = document.querySelector('.grn-batch');
    
    if (!productSelect.value) {
        alert('Please select a product');
        return;
    }
    if (!quantityInput.value || parseFloat(quantityInput.value) <= 0) {
        alert('Please enter a valid quantity');
        return;
    }
    if (!costInput.value || parseFloat(costInput.value) <= 0) {
        alert('Please enter a valid unit cost');
        return;
    }
    
    const item = {
        product_id: productSelect.value,
        product_name: productSelect.options[productSelect.selectedIndex].text,
        received_quantity: parseFloat(quantityInput.value),
        unit_cost: parseFloat(costInput.value),
        expiry_date: expiryInput.value || '',
        batch_number: batchInput.value || '',
        ordered_quantity: 0
    };
    
    grnItems.push(item);
    updateGRNDisplay();
    
    // Clear inputs
    productSelect.value = '';
    quantityInput.value = '';
    costInput.value = '';
    expiryInput.value = '';
    batchInput.value = '';
    productSelect.focus();
}

function updateGRNDisplay() {
    const container = document.getElementById('grnItemsList');
    
    if (grnItems.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No items added yet.</div>';
        document.getElementById('grnItems').value = '[]';
        return;
    }
    
    let html = '<div class="alert alert-success"><strong>Items to Receive (' + grnItems.length + '):</strong></div>';
    let total = 0;
    
    grnItems.forEach((item, index) => {
        const subtotal = item.received_quantity * item.unit_cost;
        total += subtotal;
        
        html += `
            <div class="card mb-2">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>${item.product_name}</strong><br>
                            <small class="text-muted">
                                Qty: ${item.received_quantity} × KES ${item.unit_cost.toFixed(2)} = KES ${subtotal.toFixed(2)}
                                ${item.expiry_date ? ' | Expiry: ' + item.expiry_date : ''}
                                ${item.batch_number ? ' | Batch: ' + item.batch_number : ''}
                            </small>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeGRNItem(${index})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += `<div class="alert alert-primary"><h5 class="mb-0">Total: KES ${total.toFixed(2)}</h5></div>`;
    container.innerHTML = html;
    
    document.getElementById('grnItems').value = JSON.stringify(grnItems);
}

function removeGRNItem(index) {
    grnItems.splice(index, 1);
    updateGRNDisplay();
}

function validateGRNForm() {
    if (grnItems.length === 0) {
        alert('Please add at least one item to the GRN');
        return false;
    }
    return true;
}

function receiveFromPO(poId, poNumber) {
    // Load PO items and populate GRN form
    fetch('api/get-po-items.php?po_id=' + poId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('poId').value = poId;
                document.getElementById('supplierId').value = data.supplier_id;
                grnItems = data.items.map(item => ({
                    product_id: item.product_id,
                    product_name: item.product_name,
                    received_quantity: item.quantity,
                    unit_cost: item.unit_cost,
                    ordered_quantity: item.quantity,
                    expiry_date: '',
                    batch_number: ''
                }));
                updateGRNDisplay();
                new bootstrap.Modal(document.getElementById('grnModal')).show();
            }
        });
}

function viewGRN(grnId) {
    window.location.href = 'grn-details.php?id=' + grnId;
}

// Initialize
document.getElementById('grnModal').addEventListener('show.bs.modal', function () {
    if (!document.getElementById('poId').value) {
        grnItems = [];
        updateGRNDisplay();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
