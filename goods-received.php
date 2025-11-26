<?php
require_once 'includes/bootstrap.php';

use App\Services\Inventory\InventoryService;

$auth->requireRole(['admin', 'manager', 'inventory_manager']);

$db = Database::getInstance();
$inventoryService = new InventoryService($db->getConnection());

// Ensure GRN tables exist (auto-migration for legacy installs)
try {
    $hasGrnTable = $db->fetchOne("SHOW TABLES LIKE 'goods_received_notes'");
    if (!$hasGrnTable) {
        $db->query("
            CREATE TABLE goods_received_notes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                grn_number VARCHAR(50) UNIQUE NOT NULL,
                purchase_order_id INT UNSIGNED,
                supplier_id INT UNSIGNED,
                received_date DATE NOT NULL,
                invoice_number VARCHAR(100),
                total_amount DECIMAL(15,2) DEFAULT 0,
                notes TEXT,
                status ENUM('draft', 'completed', 'cancelled') DEFAULT 'draft',
                received_by INT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_grn_number (grn_number),
                INDEX idx_po (purchase_order_id),
                INDEX idx_status (status),
                INDEX idx_received_date (received_date),
                CONSTRAINT fk_grn_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
                CONSTRAINT fk_grn_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
                CONSTRAINT fk_grn_user FOREIGN KEY (received_by) REFERENCES users(id)
            ) ENGINE=InnoDB
        ");
    }

    $hasGrnItemsTable = $db->fetchOne("SHOW TABLES LIKE 'grn_items'");
    if (!$hasGrnItemsTable) {
        $db->query("
            CREATE TABLE grn_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                grn_id INT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NOT NULL,
                ordered_quantity DECIMAL(10,2) DEFAULT 0,
                received_quantity DECIMAL(10,2) NOT NULL,
                unit_cost DECIMAL(15,2) NOT NULL,
                subtotal DECIMAL(15,2) NOT NULL,
                expiry_date DATE,
                batch_number VARCHAR(50),
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_grn (grn_id),
                INDEX idx_product (product_id),
                CONSTRAINT fk_grn_items_grn FOREIGN KEY (grn_id) REFERENCES goods_received_notes(id) ON DELETE CASCADE,
                CONSTRAINT fk_grn_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");
    }
} catch (Exception $schemaError) {
    error_log('Failed to verify or create GRN tables: ' . $schemaError->getMessage());
}

// Ensure schema has invoice_number column to prevent insert failures
try {
    $invoiceColumn = $db->fetchOne("SHOW COLUMNS FROM goods_received_notes LIKE 'invoice_number'");
    if (!$invoiceColumn) {
        $db->query("ALTER TABLE goods_received_notes ADD COLUMN invoice_number VARCHAR(100) NULL AFTER received_date");
    }
} catch (Exception $schemaError) {
    error_log('Failed to verify or alter goods_received_notes schema: ' . $schemaError->getMessage());
}

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

                    // Update latest cost
                    $db->update('products', [
                        'cost_price' => $unitCost
                    ], 'id = :id', ['id' => $item['product_id']]);

                    // Record inbound movement via unified inventory service
                    $inventoryService->recordInboundMovement((int) $item['product_id'], $receivedQty, [
                        'movement_type' => 'grn',
                        'reference_type' => 'grn',
                        'reference_id' => $grnId,
                        'reference_number' => $grnNumber,
                        'notes' => 'Invoice: ' . $invoiceNumber,
                        'user_id' => $auth->getUserId(),
                        'source_module' => 'inventory',
                        'unit_cost' => $unitCost
                    ]);

                    // Legacy stock movement log for backward compatibility
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

$pendingPoItems = [];
if (!empty($pendingPOs)) {
    $poIds = array_column($pendingPOs, 'id');
    if (!empty($poIds)) {
        $placeholders = implode(',', array_fill(0, count($poIds), '?'));
        $poItems = $db->fetchAll("
            SELECT poi.purchase_order_id, poi.product_id, poi.quantity, poi.unit_cost, poi.subtotal,
                   p.name AS product_name, p.sku
            FROM purchase_order_items poi
            JOIN products p ON poi.product_id = p.id
            WHERE poi.purchase_order_id IN ($placeholders)
            ORDER BY poi.id
        ", $poIds);

        foreach ($poItems as $item) {
            $purchaseOrderId = (int)$item['purchase_order_id'];
            if (!isset($pendingPoItems[$purchaseOrderId])) {
                $pendingPoItems[$purchaseOrderId] = [];
            }

            $pendingPoItems[$purchaseOrderId][] = [
                'product_id' => (int)$item['product_id'],
                'product_name' => $item['product_name'],
                'sku' => $item['sku'],
                'ordered_quantity' => (float)$item['quantity'],
                'unit_cost' => (float)$item['unit_cost']
            ];
        }
    }
}

$suppliers = $db->fetchAll("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name");
$products = $db->fetchAll("SELECT id, name, sku, unit FROM products WHERE is_active = 1 ORDER BY name");
$currencyJsConfig = CurrencyManager::getInstance()->getJavaScriptConfig();

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

<!-- GRN Overview -->
<?php
$grnMetrics = [
    'today' => $db->fetchOne("SELECT COUNT(*) as total, SUM(total_amount) as amount FROM goods_received_notes WHERE DATE(received_date) = CURDATE()") ?: ['total' => 0, 'amount' => 0],
    'week' => $db->fetchOne("SELECT COUNT(*) as total, SUM(total_amount) as amount FROM goods_received_notes WHERE received_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)") ?: ['total' => 0, 'amount' => 0],
    'month' => $db->fetchOne("SELECT COUNT(*) as total, SUM(total_amount) as amount FROM goods_received_notes WHERE received_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)") ?: ['total' => 0, 'amount' => 0],
];

$grnPolicyHighlights = [
    [
        'label' => 'Pending POs',
        'value' => count($pendingPOs),
        'icon' => 'hourglass-split',
        'variant' => count($pendingPOs) > 0 ? 'warning' : 'success',
        'description' => count($pendingPOs) > 0 ? 'Awaiting receiving' : 'No pending purchase orders.'
    ],
    [
        'label' => 'Suppliers',
        'value' => count($suppliers),
        'icon' => 'building',
        'variant' => 'info',
        'description' => 'Active suppliers enabled for receiving.'
    ],
    [
        'label' => 'GRNs this Month',
        'value' => $grnMetrics['month']['total'],
        'icon' => 'truck',
        'variant' => 'primary',
        'description' => 'Completed goods receipts in the last 30 days.'
    ],
    [
        'label' => 'Value Received',
        'value' => formatMoney($grnMetrics['month']['amount']),
        'icon' => 'currency-dollar',
        'variant' => 'success',
        'description' => 'Total stock value received this month.'
    ],
];
?>

<div class="row g-3 mb-4">
    <?php foreach ($grnPolicyHighlights as $highlight): ?>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-<?= $highlight['variant'] ?>-subtle text-<?= $highlight['variant'] ?> rounded-circle p-2">
                        <i class="bi bi-<?= htmlspecialchars($highlight['icon']) ?>"></i>
                    </span>
                    <div>
                        <small class="text-muted text-uppercase"><?= htmlspecialchars($highlight['label']) ?></small>
                        <h5 class="mb-1"><?= htmlspecialchars($highlight['value']) ?></h5>
                        <span class="text-muted small"><?= htmlspecialchars($highlight['description']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
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
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#grn-insights">
            <i class="bi bi-bar-chart"></i> Insights
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- GRN List Tab -->
    <div class="tab-pane fade show active" id="grn-list">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <h6 class="mb-0">Recent Goods Received Notes</h6>
                <div class="d-flex flex-wrap gap-2">
                    <div class="input-group input-group-sm" style="max-width:220px;">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="grnSearch" placeholder="GRN number or supplier">
                    </div>
                    <input type="date" class="form-control form-control-sm" id="grnDateFilter" style="max-width:170px;">
                    <select class="form-select form-select-sm" id="grnStatusFilter" style="max-width:160px;">
                        <option value="">All Status</option>
                        <option value="draft">Draft</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <button class="btn btn-outline-secondary btn-sm" id="grnExportBtn"><i class="bi bi-download"></i> Export CSV</button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="grnTable">
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
                            <?php
                                $grnNumber    = $grn['grn_number'] ?? '';
                                $supplierName = $grn['supplier_name'] ?? '';
                                $invoiceNo    = $grn['invoice_number'] ?? '';
                                $status       = $grn['status'] ?? '';
                                $receivedDate = $grn['received_date'] ?? '';
                                $receivedBy   = $grn['received_by_name'] ?? '';
                                $poNumber     = $grn['po_number'] ?? '-';
                                $searchBlob = strtolower(trim($grnNumber . ' ' . $supplierName . ' ' . $invoiceNo));
                            ?>
                            <tr data-search="<?= htmlspecialchars($searchBlob) ?>" data-date="<?= htmlspecialchars($receivedDate) ?>" data-status="<?= htmlspecialchars($status) ?>">
                                <td><strong><?= htmlspecialchars($grnNumber) ?></strong></td>
                                <td><?= $receivedDate ? formatDate($receivedDate, 'd/m/Y') : '-' ?></td>
                                <td><?= htmlspecialchars($supplierName) ?></td>
                                <td><?= htmlspecialchars($poNumber !== '' ? $poNumber : '-') ?></td>
                                <td><?= htmlspecialchars($invoiceNo !== '' ? $invoiceNo : '-') ?></td>
                                <td><?= formatMoney($grn['total_amount']) ?></td>
                                <td><?= htmlspecialchars($receivedBy) ?></td>
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
                    <div id="grnEmptyState" class="alert alert-info text-center" style="display:none;">
                        No GRNs match the current filters.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pending POs Tab -->
    <div class="tab-pane fade" id="pending-pos">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <h6 class="mb-0">Purchase Orders Awaiting Receipt</h6>
                <div class="d-flex flex-wrap gap-2">
                    <select class="form-select form-select-sm" id="poSupplierFilter" style="max-width:200px;">
                        <option value="">All Suppliers</option>
                        <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= htmlspecialchars($supplier['name']) ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="pendingPoTable">
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
                        <tr data-supplier="<?= htmlspecialchars($po['supplier_name'] ?? '') ?>">
                            <td><strong><?= htmlspecialchars($po['po_number'] ?? '') ?></strong></td>
                            <td><?= formatDate($po['created_at'], 'd/m/Y') ?></td>
                            <td><?= htmlspecialchars($po['supplier_name'] ?? '') ?></td>
                            <td><?= formatMoney($po['total_amount']) ?></td>
                            <td>
                                <span class="badge bg-warning"><?= ucfirst($po['status']) ?></span>
                            </td>
                            <td>
                                <?php
                                $poItemPayload = $pendingPoItems[$po['id']] ?? [];
                                ?>
                                <button class="btn btn-sm btn-success" data-po-id="<?= $po['id'] ?>" data-po-number="<?= htmlspecialchars($po['po_number'] ?? '') ?>" data-supplier-id="<?= (int)($po['supplier_id'] ?? 0) ?>" data-po-items="<?= htmlspecialchars(json_encode($poItemPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>" onclick="receiveFromPO(this)">
                                    <i class="bi bi-check-circle me-1"></i>Receive Goods
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="pendingPoEmptyState" class="alert alert-info text-center" style="display:none;">
                        No pending purchase orders for the selected supplier.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Insights Tab -->
    <div class="tab-pane fade" id="grn-insights">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">Top Suppliers (30 days)</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $topSuppliers = $db->fetchAll("
                            SELECT s.name, COUNT(g.id) AS grn_count, SUM(g.total_amount) AS total_amount
                            FROM goods_received_notes g
                            JOIN suppliers s ON g.supplier_id = s.id
                            WHERE g.received_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                            GROUP BY s.id
                            ORDER BY total_amount DESC
                            LIMIT 5
                        ") ?: [];
                        ?>
                        <?php if (!empty($topSuppliers)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($topSuppliers as $supplier): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($supplier['name']) ?></strong>
                                        <div class="small text-muted"><?= $supplier['grn_count'] ?> GRNs</div>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary"><?= formatMoney($supplier['total_amount']) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted mb-0">No supplier activity in the last 30 days.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">Receiving Timeline</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $grnTimeline = $db->fetchAll("
                            SELECT DATE(received_date) as date, COUNT(*) as total, SUM(total_amount) as amount
                            FROM goods_received_notes
                            WHERE received_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                            GROUP BY DATE(received_date)
                            ORDER BY date DESC
                            LIMIT 14
                        ") ?: [];
                        ?>
                        <?php if (!empty($grnTimeline)): ?>
                            <div class="table-responsive" style="max-height:240px;">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th class="text-end">GRNs</th>
                                            <th class="text-end">Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grnTimeline as $row): ?>
                                        <tr>
                                            <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                                            <td class="text-end"><?= $row['total'] ?></td>
                                            <td class="text-end"><?= formatMoney($row['amount']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No GRNs recorded in the last two weeks.</p>
                        <?php endif; ?>
                    </div>
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
const currencyConfig = <?= json_encode($currencyJsConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function formatCurrencyValue(amount) {
    const cfg = currencyConfig || {};
    const decimalsRaw = typeof cfg.decimal_places === 'number' ? cfg.decimal_places : parseInt(cfg.decimal_places ?? 2, 10);
    const decimals = Number.isFinite(decimalsRaw) ? Math.max(0, decimalsRaw) : 2;
    const decimalSeparator = cfg.decimal_separator ?? '.';
    const thousandsSeparator = cfg.thousands_separator ?? ',';
    const numericAmount = Number(amount) || 0;
    const fixed = numericAmount.toFixed(decimals);
    let [intPart, fracPart = ''] = fixed.split('.');
    intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSeparator);
    return decimals > 0 ? `${intPart}${decimalSeparator}${fracPart}` : intPart;
}

function formatCurrencyWithSymbol(amount) {
    const formatted = formatCurrencyValue(amount);
    const symbol = currencyConfig?.symbol || '';
    if (!symbol) {
        return formatted;
    }
    return (currencyConfig.position === 'after') ? `${formatted} ${symbol}` : `${symbol} ${formatted}`;
}

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
        
        const unitCostDisplay = formatCurrencyWithSymbol(item.unit_cost);
        const subtotalDisplay = formatCurrencyWithSymbol(subtotal);
        html += `
            <div class="card mb-2">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>${item.product_name}</strong><br>
                            <small class="text-muted">
                                Qty: ${item.received_quantity} × ${unitCostDisplay} = ${subtotalDisplay}
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
    
    html += `<div class="alert alert-primary"><h5 class="mb-0">Total: ${formatCurrencyWithSymbol(total)}</h5></div>`;
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

function receiveFromPO(buttonEl) {
    if (!buttonEl) {
        return;
    }

    const poId = buttonEl.getAttribute('data-po-id');
    const poNumber = buttonEl.getAttribute('data-po-number') || '';
    const supplierId = buttonEl.getAttribute('data-supplier-id');
    let payloadItems = [];

    const itemsAttr = buttonEl.getAttribute('data-po-items');
    if (itemsAttr) {
        try {
            payloadItems = JSON.parse(itemsAttr);
        } catch (error) {
            console.warn('Failed to parse embedded PO items, falling back to API', error);
        }
    }

    const handleItems = (poItems, supplierIdValue) => {
        document.getElementById('poId').value = poId;
        if (supplierIdValue) {
            document.getElementById('supplierId').value = supplierIdValue;
        }

        grnItems = poItems.map(item => ({
            product_id: item.product_id,
            product_name: item.product_name,
            received_quantity: item.ordered_quantity ?? item.quantity ?? item.received_quantity ?? 0,
            unit_cost: item.unit_cost,
            ordered_quantity: item.ordered_quantity ?? item.quantity ?? 0,
            expiry_date: item.expiry_date || '',
            batch_number: item.batch_number || ''
        }));

        updateGRNDisplay();

        const modalEl = document.getElementById('grnModal');
        if (modalEl) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        } else {
            console.error('Modal element not found!');
        }
    };

    if (payloadItems.length > 0) {
        console.debug('Using embedded PO items for PO', poId, payloadItems);
        handleItems(payloadItems, supplierId);
        return;
    }

    // Fallback to API fetch if no embedded payload
    console.debug('Fetching PO items for PO', poId, poNumber);
    fetch('api/get-po-items.php?po_id=' + encodeURIComponent(poId))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                handleItems(data.items, data.supplier_id);
            } else {
                console.error('API returned error:', data.message);
                alert('Error loading PO items: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Error loading PO items. Check console for details.');
        });
}

function viewGRN(grnId) {
    window.location.href = 'grn-details.php?id=' + grnId;
}

// Initialize
const grnModalElement = document.getElementById('grnModal');
grnModalElement?.addEventListener('show.bs.modal', function () {
    if (!document.getElementById('poId').value) {
        grnItems = [];
        updateGRNDisplay();
    }
});

// Filtering controls for GRN list and pending PO tables
const grnSearchInput = document.getElementById('grnSearch');
const grnDateFilter = document.getElementById('grnDateFilter');
const grnStatusFilter = document.getElementById('grnStatusFilter');
const grnRows = Array.from(document.querySelectorAll('#grnTable tbody tr'));
const grnEmptyState = document.getElementById('grnEmptyState');

const poSupplierFilter = document.getElementById('poSupplierFilter');
const pendingPoRows = Array.from(document.querySelectorAll('#pendingPoTable tbody tr'));
const pendingPoEmptyState = document.getElementById('pendingPoEmptyState');

document.addEventListener('DOMContentLoaded', () => {
    grnSearchInput?.addEventListener('input', debounce(filterGrns, 150));
    grnDateFilter?.addEventListener('change', filterGrns);
    grnStatusFilter?.addEventListener('change', filterGrns);
    poSupplierFilter?.addEventListener('change', filterPendingPOs);

    filterGrns();
    filterPendingPOs();
});

function filterGrns() {
    const term = (grnSearchInput?.value || '').trim().toLowerCase();
    const date = grnDateFilter?.value || '';
    const status = grnStatusFilter?.value || '';

    let visible = 0;

    grnRows.forEach(row => {
        const matchesTerm = !term || (row.dataset.search || '').includes(term);
        const matchesDate = !date || (row.dataset.date || '') === date;
        const matchesStatus = !status || (row.dataset.status || '') === status;

        if (matchesTerm && matchesDate && matchesStatus) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    if (grnEmptyState) {
        grnEmptyState.style.display = visible === 0 ? '' : 'none';
    }
}

function filterPendingPOs() {
    const supplier = (poSupplierFilter?.value || '').toLowerCase();
    let visible = 0;

    pendingPoRows.forEach(row => {
        const matchesSupplier = !supplier || (row.dataset.supplier || '').toLowerCase() === supplier;
        if (matchesSupplier) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    if (pendingPoEmptyState) {
        pendingPoEmptyState.style.display = visible === 0 ? '' : 'none';
    }
}

function debounce(fn, delay) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn.apply(this, args), delay);
    };
}
</script>

<?php include 'includes/footer.php'; ?>
