<?php
/**
 * Housekeeping Inventory Management
 * 
 * Manage laundry, linen, supplies, and minibar inventory
 */

use App\Services\HousekeepingInventoryService;

require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager', 'housekeeping_manager', 'housekeeping_staff']);

$db = Database::getInstance();
$pdo = $db->getConnection();
$hkInventory = new HousekeepingInventoryService($pdo);
$hkInventory->ensureSchema();

$csrfToken = generateCSRFToken();
$userRole = strtolower($auth->getRole() ?? '');
$canManage = in_array($userRole, ['admin', 'manager', 'housekeeping_manager']);

// Get current section from URL
$currentSection = $_GET['section'] ?? 'all';
$sections = $hkInventory->getSections();

// Get inventory data
$inventory = $hkInventory->getAllInventory();
$lowStockItems = $hkInventory->getLowStockItems();
$linenSummary = $hkInventory->getLinenSummary();
$dashboardSummary = $hkInventory->getDashboardSummary();

// Filter by section if specified
if ($currentSection !== 'all' && isset($sections[$currentSection])) {
    $inventory = array_filter($inventory, fn($item) => $item['section'] === $currentSection);
}

$pageTitle = 'Housekeeping Inventory';
include 'includes/header.php';
?>

<style>
    .section-card {
        border: 1px solid var(--bs-border-color);
        border-radius: 0.5rem;
        padding: 1rem;
        text-align: center;
        transition: all 0.2s;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        display: block;
    }
    .section-card:hover, .section-card.active {
        border-color: var(--bs-primary);
        background: var(--bs-primary-bg-subtle);
    }
    .section-card i {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        display: block;
    }
    .section-card.active i {
        color: var(--bs-primary);
    }
    .linen-status-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-clean { background: #d4edda; color: #155724; }
    .status-in_use { background: #cce5ff; color: #004085; }
    .status-dirty { background: #f8d7da; color: #721c24; }
    .status-washing { background: #fff3cd; color: #856404; }
    .status-damaged { background: #f5c6cb; color: #721c24; }
    .low-stock-indicator {
        color: var(--bs-danger);
        font-weight: bold;
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-box-seam me-2"></i>Housekeeping Inventory</h4>
            <p class="text-muted mb-0">Manage laundry, linen, supplies, and minibar stock</p>
        </div>
        <?php if ($canManage): ?>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                <i class="bi bi-plus-lg me-1"></i>Add Item
            </button>
            <a href="housekeeping-laundry.php" class="btn btn-outline-secondary">
                <i class="bi bi-basket3 me-1"></i>Laundry Batches
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Dashboard Summary -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                                <i class="bi bi-box-seam text-primary fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h3 class="mb-0"><?= count($inventory) ?></h3>
                            <small class="text-muted">Total Items</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 <?= count($lowStockItems) > 0 ? 'border-danger' : '' ?>">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-danger bg-opacity-10 rounded-3 p-3">
                                <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h3 class="mb-0"><?= count($lowStockItems) ?></h3>
                            <small class="text-muted">Low Stock Items</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                                <i class="bi bi-basket3 text-warning fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h3 class="mb-0"><?= $dashboardSummary['pending_laundry'] ?></h3>
                            <small class="text-muted">Pending Laundry</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success bg-opacity-10 rounded-3 p-3">
                                <i class="bi bi-cup-straw text-success fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h3 class="mb-0"><?= formatMoney($dashboardSummary['today_minibar_revenue']) ?></h3>
                            <small class="text-muted">Today's Minibar</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Linen Status Summary -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-stack me-2"></i>Linen Status Overview</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($linenSummary as $status => $data): ?>
                <div class="col-md-2 col-4">
                    <div class="text-center">
                        <div class="linen-status-badge status-<?= $status ?> mb-2" style="font-size: 1.5rem; padding: 0.5rem 1rem;">
                            <?= $data['count'] ?>
                        </div>
                        <div class="small text-muted"><?= $data['label'] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Section Navigation -->
    <div class="row g-3 mb-4">
        <div class="col-md-2 col-4">
            <a href="?section=all" class="section-card <?= $currentSection === 'all' ? 'active' : '' ?>">
                <i class="bi bi-grid-3x3-gap"></i>
                <div class="fw-semibold">All Sections</div>
            </a>
        </div>
        <?php foreach ($sections as $key => $section): ?>
        <div class="col-md-2 col-4">
            <a href="?section=<?= $key ?>" class="section-card <?= $currentSection === $key ? 'active' : '' ?>">
                <i class="<?= $section['icon'] ?>"></i>
                <div class="fw-semibold"><?= htmlspecialchars($section['label']) ?></div>
                <small class="text-muted"><?= $dashboardSummary['sections'][$key]['item_count'] ?? 0 ?> items</small>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Inventory Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                <?php if ($currentSection !== 'all' && isset($sections[$currentSection])): ?>
                <?= htmlspecialchars($sections[$currentSection]['label']) ?> Inventory
                <?php else: ?>
                All Inventory Items
                <?php endif; ?>
            </h6>
            <input type="text" class="form-control form-control-sm" style="max-width: 250px;" 
                   placeholder="Search items..." id="searchInventory">
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="inventoryTable">
                    <thead class="table-light">
                        <tr>
                            <th>Item</th>
                            <th>Section</th>
                            <th>Code</th>
                            <th class="text-end">On Hand</th>
                            <th class="text-end">Reorder Level</th>
                            <th class="text-end">Cost</th>
                            <th>Location</th>
                            <?php if ($canManage): ?>
                            <th class="text-end">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventory)): ?>
                        <tr>
                            <td colspan="<?= $canManage ? 8 : 7 ?>" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No inventory items found.
                                <?php if ($canManage): ?>
                                <br><a href="#" data-bs-toggle="modal" data-bs-target="#addItemModal">Add your first item</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($inventory as $item): ?>
                        <?php $isLowStock = $item['quantity_on_hand'] <= $item['reorder_level']; ?>
                        <tr class="<?= $isLowStock ? 'table-warning' : '' ?>" 
                            data-search="<?= strtolower($item['item_name'] . ' ' . ($item['item_code'] ?? '') . ' ' . ($item['section'] ?? '')) ?>">
                            <td>
                                <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                <?php if ($isLowStock): ?>
                                <span class="badge bg-danger ms-1">Low Stock</span>
                                <?php endif; ?>
                                <?php if (!empty($item['linen_count'])): ?>
                                <br><small class="text-muted"><?= $item['linen_count'] ?> tracked items</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (isset($sections[$item['section']])): ?>
                                <span class="badge bg-secondary">
                                    <i class="<?= $sections[$item['section']]['icon'] ?> me-1"></i>
                                    <?= $sections[$item['section']]['label'] ?>
                                </span>
                                <?php else: ?>
                                <?= htmlspecialchars($item['section']) ?>
                                <?php endif; ?>
                            </td>
                            <td><code><?= htmlspecialchars($item['item_code'] ?? '-') ?></code></td>
                            <td class="text-end <?= $isLowStock ? 'low-stock-indicator' : '' ?>">
                                <?= number_format($item['quantity_on_hand'], 0) ?> <?= htmlspecialchars($item['unit']) ?>
                            </td>
                            <td class="text-end"><?= number_format($item['reorder_level'], 0) ?></td>
                            <td class="text-end"><?= formatMoney($item['cost_price']) ?></td>
                            <td><?= htmlspecialchars($item['location'] ?? '-') ?></td>
                            <?php if ($canManage): ?>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-success" onclick="adjustStock(<?= $item['id'] ?>, '<?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>', 'receipt')" title="Receive Stock">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                    <button class="btn btn-outline-warning" onclick="adjustStock(<?= $item['id'] ?>, '<?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>', 'issue')" title="Issue Stock">
                                        <i class="bi bi-dash-lg"></i>
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="editItem(<?= $item['id'] ?>)" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Add Inventory Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addItemForm" method="POST" action="api/housekeeping-inventory.php">
                <input type="hidden" name="action" value="add_item">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Section</label>
                        <select class="form-select" name="section" required>
                            <?php foreach ($sections as $key => $section): ?>
                            <option value="<?= $key ?>"><?= htmlspecialchars($section['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Item Name</label>
                        <input type="text" class="form-control" name="item_name" required placeholder="e.g., Bath Towel, Shampoo 50ml">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Item Code</label>
                            <input type="text" class="form-control" name="item_code" placeholder="e.g., BT-001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unit</label>
                            <select class="form-select" name="unit">
                                <option value="pcs">Pieces</option>
                                <option value="sets">Sets</option>
                                <option value="pairs">Pairs</option>
                                <option value="bottles">Bottles</option>
                                <option value="boxes">Boxes</option>
                                <option value="kg">Kilograms</option>
                                <option value="liters">Liters</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Initial Quantity</label>
                            <input type="number" class="form-control" name="quantity_on_hand" value="0" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reorder Level</label>
                            <input type="number" class="form-control" name="reorder_level" value="10" min="0">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Cost Price</label>
                            <input type="number" class="form-control" name="cost_price" value="0" min="0" step="0.01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Storage Location</label>
                            <input type="text" class="form-control" name="location" placeholder="e.g., Store Room A">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Supplier</label>
                        <input type="text" class="form-control" name="supplier" placeholder="Supplier name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Optional description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>Add Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Adjust Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="adjustStockForm" method="POST" action="api/housekeeping-inventory.php">
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="inventory_id" id="adjustInventoryId">
                <input type="hidden" name="type" id="adjustType">
                <div class="modal-body">
                    <p class="mb-3"><strong id="adjustItemName"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" name="quantity" min="1" value="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <input type="text" class="form-control" name="notes" placeholder="Reason for adjustment">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="adjustSubmitBtn">
                        <i class="bi bi-check-lg me-1"></i>Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= $csrfToken ?>';

// Search functionality
document.getElementById('searchInventory')?.addEventListener('input', function() {
    const search = this.value.toLowerCase();
    document.querySelectorAll('#inventoryTable tbody tr').forEach(row => {
        const text = row.dataset.search || '';
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

// Stock adjustment
function adjustStock(id, name, type) {
    document.getElementById('adjustInventoryId').value = id;
    document.getElementById('adjustType').value = type;
    document.getElementById('adjustItemName').textContent = name;
    
    const btn = document.getElementById('adjustSubmitBtn');
    if (type === 'receipt') {
        btn.className = 'btn btn-success';
        btn.innerHTML = '<i class="bi bi-plus-lg me-1"></i>Receive Stock';
    } else {
        btn.className = 'btn btn-warning';
        btn.innerHTML = '<i class="bi bi-dash-lg me-1"></i>Issue Stock';
    }
    
    new bootstrap.Modal(document.getElementById('adjustStockModal')).show();
}

// Form submissions
document.getElementById('addItemForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch(this.action, { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.success) {
            alert('Item added successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to add item'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
});

document.getElementById('adjustStockForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch(this.action, { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.success) {
            alert('Stock adjusted successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to adjust stock'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
