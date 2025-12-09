<?php
/**
 * Bar POS - Professional Bar Point of Sale
 * 
 * Features:
 * - Tab management (open/close/transfer)
 * - Pre-authorization support
 * - Portion-based ordering (tots, shots, glasses)
 * - BOT generation to bar stations
 * - Split payments
 * - Room charge integration
 */

use App\Services\BarTabService;
use App\Services\BarManagementService;

require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager', 'bartender', 'waiter', 'cashier']);

$db = Database::getInstance();
$pdo = $db->getConnection();

// Initialize services
$tabService = new BarTabService($pdo);
$barService = new BarManagementService($pdo);

// Ensure schema exists
$barService->ensureSchema();

$csrfToken = generateCSRFToken();
$userId = $_SESSION['user_id'];
$userRole = strtolower($auth->getRole() ?? '');
$locationId = $_SESSION['location_id'] ?? null;

// Get bar stations
$stations = $tabService->getStations();

// Get open tabs
$openTabs = $tabService->getOpenTabs($locationId);

// Get products with portions
$products = $db->fetchAll("
    SELECT p.*, c.name as category_name,
           (SELECT COUNT(*) FROM product_portions WHERE product_id = p.id AND is_active = 1) as portion_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1
    ORDER BY c.name, p.name
");

// Get categories
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");

// Get recipes
$recipes = $barService->getRecipes();

// Get customers for tab linking
$customers = $db->fetchAll("SELECT id, name, phone, email FROM customers WHERE is_active = 1 ORDER BY name LIMIT 500");

// Get open room bookings for room charge
$roomBookings = $db->fetchAll("
    SELECT rb.id, rb.booking_number, r.room_number, rb.guest_name
    FROM room_bookings rb
    JOIN rooms r ON rb.room_id = r.id
    WHERE rb.status IN ('checked_in', 'confirmed')
    ORDER BY r.room_number
");

$pageTitle = 'Bar POS';
include 'includes/header.php';
?>

<style>
    .bar-pos-container {
        display: grid;
        grid-template-columns: 320px 1fr 380px;
        gap: 1rem;
        height: calc(100vh - 120px);
        padding: 1rem;
    }
    
    /* Tabs Panel */
    .tabs-panel {
        background: var(--bs-body-bg);
        border-radius: 0.5rem;
        border: 1px solid var(--bs-border-color);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .tabs-header {
        padding: 1rem;
        border-bottom: 1px solid var(--bs-border-color);
        background: var(--bs-tertiary-bg);
    }
    
    .tabs-list {
        flex: 1;
        overflow-y: auto;
        padding: 0.5rem;
    }
    
    .tab-card {
        background: var(--bs-body-bg);
        border: 2px solid var(--bs-border-color);
        border-radius: 0.5rem;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .tab-card:hover {
        border-color: var(--bs-primary);
    }
    
    .tab-card.active {
        border-color: var(--bs-primary);
        background: rgba(var(--bs-primary-rgb), 0.1);
    }
    
    .tab-card .tab-name {
        font-weight: 600;
        font-size: 1rem;
    }
    
    .tab-card .tab-meta {
        font-size: 0.8rem;
        color: var(--bs-secondary);
    }
    
    .tab-card .tab-total {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--bs-success);
    }
    
    .tab-type-badge {
        font-size: 0.65rem;
        padding: 0.15rem 0.4rem;
        border-radius: 0.25rem;
    }
    
    /* Products Panel */
    .products-panel {
        background: var(--bs-body-bg);
        border-radius: 0.5rem;
        border: 1px solid var(--bs-border-color);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .products-header {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--bs-border-color);
        background: var(--bs-tertiary-bg);
    }
    
    .category-tabs {
        display: flex;
        gap: 0.5rem;
        overflow-x: auto;
        padding: 0.5rem 1rem;
        border-bottom: 1px solid var(--bs-border-color);
    }
    
    .category-tab {
        padding: 0.5rem 1rem;
        border-radius: 2rem;
        border: 1px solid var(--bs-border-color);
        background: var(--bs-body-bg);
        white-space: nowrap;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    .category-tab.active {
        background: var(--bs-primary);
        color: white;
        border-color: var(--bs-primary);
    }
    
    .products-grid {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 0.75rem;
        align-content: start;
    }
    
    .product-card {
        background: var(--bs-body-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: 0.5rem;
        padding: 0.75rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .product-card:hover {
        border-color: var(--bs-primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .product-card .product-name {
        font-weight: 600;
        font-size: 0.85rem;
        margin-bottom: 0.25rem;
    }
    
    .product-card .product-price {
        color: var(--bs-success);
        font-weight: 700;
    }
    
    .product-card .portion-badge {
        font-size: 0.65rem;
        background: var(--bs-info-bg-subtle);
        color: var(--bs-info);
        padding: 0.1rem 0.3rem;
        border-radius: 0.25rem;
    }
    
    /* Order Panel */
    .order-panel {
        background: var(--bs-body-bg);
        border-radius: 0.5rem;
        border: 1px solid var(--bs-border-color);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .order-header {
        padding: 1rem;
        border-bottom: 1px solid var(--bs-border-color);
        background: var(--bs-tertiary-bg);
    }
    
    .order-items {
        flex: 1;
        overflow-y: auto;
        padding: 0.5rem;
    }
    
    .order-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem;
        border-bottom: 1px solid var(--bs-border-color);
    }
    
    .order-item:last-child {
        border-bottom: none;
    }
    
    .order-item .item-details {
        flex: 1;
    }
    
    .order-item .item-name {
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .order-item .item-portion {
        font-size: 0.75rem;
        color: var(--bs-secondary);
    }
    
    .order-item .item-price {
        font-weight: 600;
    }
    
    .order-totals {
        padding: 1rem;
        border-top: 1px solid var(--bs-border-color);
        background: var(--bs-tertiary-bg);
    }
    
    .total-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.25rem;
    }
    
    .total-row.grand-total {
        font-size: 1.2rem;
        font-weight: 700;
        border-top: 2px solid var(--bs-border-color);
        padding-top: 0.5rem;
        margin-top: 0.5rem;
    }
    
    .order-actions {
        padding: 0.75rem;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }
    
    .order-actions .btn {
        padding: 0.75rem;
        font-weight: 600;
    }
    
    /* Quick Actions */
    .quick-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .quick-action-btn {
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    /* Portion Modal */
    .portion-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
    }
    
    .portion-option {
        padding: 1rem;
        border: 2px solid var(--bs-border-color);
        border-radius: 0.5rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .portion-option:hover {
        border-color: var(--bs-primary);
    }
    
    .portion-option.selected {
        border-color: var(--bs-primary);
        background: rgba(var(--bs-primary-rgb), 0.1);
    }
    
    .portion-option .portion-name {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    
    .portion-option .portion-size {
        font-size: 0.8rem;
        color: var(--bs-secondary);
    }
    
    .portion-option .portion-price {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--bs-success);
    }
    
    /* Station indicator */
    .station-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    @media (max-width: 1200px) {
        .bar-pos-container {
            grid-template-columns: 1fr;
            height: auto;
        }
        
        .tabs-panel, .products-panel, .order-panel {
            min-height: 400px;
        }
    }
</style>

<div class="bar-pos-container">
    <!-- Tabs Panel -->
    <div class="tabs-panel">
        <div class="tabs-header">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Open Tabs</h6>
                <button class="btn btn-primary btn-sm" onclick="showNewTabModal()">
                    <i class="bi bi-plus-lg"></i> New Tab
                </button>
            </div>
            <div class="quick-actions">
                <button class="quick-action-btn btn btn-outline-secondary btn-sm" onclick="showQuickSale()">
                    <i class="bi bi-lightning"></i> Quick Sale
                </button>
                <button class="quick-action-btn btn btn-outline-info btn-sm" onclick="showTransferModal()">
                    <i class="bi bi-arrow-left-right"></i> Transfer
                </button>
            </div>
        </div>
        <div class="tabs-list" id="tabsList">
            <?php if (empty($openTabs)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-wallet2 fs-1 d-block mb-2"></i>
                    <p>No open tabs</p>
                    <button class="btn btn-primary btn-sm" onclick="showNewTabModal()">Open First Tab</button>
                </div>
            <?php else: ?>
                <?php foreach ($openTabs as $tab): ?>
                    <div class="tab-card" data-tab-id="<?= $tab['id'] ?>" onclick="selectTab(<?= $tab['id'] ?>)">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="tab-name"><?= htmlspecialchars($tab['tab_name']) ?></div>
                                <div class="tab-meta">
                                    <span class="tab-type-badge bg-<?= $tab['tab_type'] === 'card' ? 'warning' : ($tab['tab_type'] === 'room' ? 'info' : 'secondary') ?>">
                                        <?= ucfirst($tab['tab_type']) ?>
                                    </span>
                                    <?= $tab['item_count'] ?> items • <?= $tab['server_name'] ?>
                                </div>
                            </div>
                            <div class="tab-total"><?= formatCurrency($tab['total_amount']) ?></div>
                        </div>
                        <div class="tab-meta mt-1">
                            <small><i class="bi bi-clock"></i> <?= date('g:i A', strtotime($tab['opened_at'])) ?></small>
                            <?php if ($tab['bar_station']): ?>
                                <span class="station-indicator bg-info-subtle text-info ms-2">
                                    <i class="bi bi-cup-straw"></i> <?= htmlspecialchars($tab['bar_station']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Products Panel -->
    <div class="products-panel">
        <div class="products-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-grid me-2"></i>Menu</h6>
                <div class="input-group" style="width: 200px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control form-control-sm" id="productSearch" placeholder="Search...">
                </div>
            </div>
        </div>
        <div class="category-tabs" id="categoryTabs">
            <div class="category-tab active" data-category="all">All</div>
            <div class="category-tab" data-category="cocktails">🍹 Cocktails</div>
            <div class="category-tab" data-category="spirits">🥃 Spirits</div>
            <div class="category-tab" data-category="wine">🍷 Wine</div>
            <div class="category-tab" data-category="beer">🍺 Beer</div>
            <div class="category-tab" data-category="soft">🥤 Soft Drinks</div>
            <?php foreach ($categories as $cat): ?>
                <div class="category-tab" data-category="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></div>
            <?php endforeach; ?>
        </div>
        <div class="products-grid" id="productsGrid">
            <?php foreach ($products as $product): ?>
                <div class="product-card" 
                     data-product-id="<?= $product['id'] ?>"
                     data-product-name="<?= htmlspecialchars($product['name']) ?>"
                     data-product-price="<?= $product['selling_price'] ?>"
                     data-has-portions="<?= $product['portion_count'] > 0 ? '1' : '0' ?>"
                     data-category="<?= $product['category_id'] ?>"
                     onclick="addProduct(<?= $product['id'] ?>)">
                    <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                    <div class="product-price"><?= formatCurrency($product['selling_price']) ?></div>
                    <?php if ($product['portion_count'] > 0): ?>
                        <div class="portion-badge"><?= $product['portion_count'] ?> portions</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <!-- Recipes/Cocktails -->
            <?php foreach ($recipes as $recipe): ?>
                <div class="product-card" 
                     data-recipe-id="<?= $recipe['id'] ?>"
                     data-product-name="<?= htmlspecialchars($recipe['name']) ?>"
                     data-product-price="<?= $recipe['selling_price'] ?>"
                     data-is-recipe="1"
                     data-category="cocktails"
                     onclick="addRecipe(<?= $recipe['id'] ?>)">
                    <div class="product-name">🍹 <?= htmlspecialchars($recipe['name']) ?></div>
                    <div class="product-price"><?= formatCurrency($recipe['selling_price']) ?></div>
                    <div class="portion-badge">Cocktail</div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Order Panel -->
    <div class="order-panel">
        <div class="order-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0" id="currentTabName">No Tab Selected</h6>
                    <small class="text-muted" id="currentTabMeta"></small>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="printTab()"><i class="bi bi-printer me-2"></i>Print Tab</a></li>
                        <li><a class="dropdown-item" href="#" onclick="sendBot()"><i class="bi bi-send me-2"></i>Send to Bar (BOT)</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="showTransferModal()"><i class="bi bi-arrow-left-right me-2"></i>Transfer Tab</a></li>
                        <li><a class="dropdown-item" href="#" onclick="chargeToRoom()"><i class="bi bi-door-open me-2"></i>Charge to Room</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="voidTab()"><i class="bi bi-x-circle me-2"></i>Void Tab</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="order-items" id="orderItems">
            <div class="text-center text-muted py-5">
                <i class="bi bi-cup-straw fs-1 d-block mb-2"></i>
                <p>Select a tab or start a new one</p>
            </div>
        </div>
        
        <div class="order-totals">
            <div class="total-row">
                <span>Subtotal</span>
                <span id="subtotal"><?= formatCurrency(0) ?></span>
            </div>
            <div class="total-row">
                <span>Tax (16%)</span>
                <span id="taxAmount"><?= formatCurrency(0) ?></span>
            </div>
            <div class="total-row">
                <span>Discount</span>
                <span id="discountAmount">-<?= formatCurrency(0) ?></span>
            </div>
            <div class="total-row grand-total">
                <span>Total</span>
                <span id="grandTotal"><?= formatCurrency(0) ?></span>
            </div>
        </div>
        
        <div class="order-actions">
            <button class="btn btn-outline-secondary" onclick="showDiscountModal()" id="btnDiscount" disabled>
                <i class="bi bi-percent"></i> Discount
            </button>
            <button class="btn btn-outline-info" onclick="showTipModal()" id="btnTip" disabled>
                <i class="bi bi-heart"></i> Add Tip
            </button>
            <button class="btn btn-warning" onclick="splitPayment()" id="btnSplit" disabled>
                <i class="bi bi-pie-chart"></i> Split
            </button>
            <button class="btn btn-success" onclick="showPaymentModal()" id="btnPay" disabled>
                <i class="bi bi-credit-card"></i> Pay
            </button>
        </div>
    </div>
</div>

<!-- New Tab Modal -->
<div class="modal fade" id="newTabModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-wallet2 me-2"></i>Open New Tab</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Tab Type</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="tabType" id="tabTypeName" value="name" checked>
                        <label class="btn btn-outline-primary" for="tabTypeName"><i class="bi bi-person"></i> Name</label>
                        
                        <input type="radio" class="btn-check" name="tabType" id="tabTypeCard" value="card">
                        <label class="btn btn-outline-primary" for="tabTypeCard"><i class="bi bi-credit-card"></i> Card</label>
                        
                        <input type="radio" class="btn-check" name="tabType" id="tabTypeRoom" value="room">
                        <label class="btn btn-outline-primary" for="tabTypeRoom"><i class="bi bi-door-open"></i> Room</label>
                    </div>
                </div>
                
                <div class="mb-3" id="tabNameGroup">
                    <label class="form-label">Guest Name</label>
                    <input type="text" class="form-control" id="newTabName" placeholder="Enter guest name">
                </div>
                
                <div class="mb-3 d-none" id="tabCardGroup">
                    <label class="form-label">Card Last 4 Digits</label>
                    <input type="text" class="form-control" id="cardLastFour" maxlength="4" placeholder="1234">
                    <div class="mt-2">
                        <label class="form-label">Pre-Auth Amount</label>
                        <input type="number" class="form-control" id="preauthAmount" placeholder="0.00">
                    </div>
                </div>
                
                <div class="mb-3 d-none" id="tabRoomGroup">
                    <label class="form-label">Select Room</label>
                    <select class="form-select" id="roomBookingSelect">
                        <option value="">-- Select Room --</option>
                        <?php foreach ($roomBookings as $booking): ?>
                            <option value="<?= $booking['id'] ?>">
                                Room <?= htmlspecialchars($booking['room_number']) ?> - <?= htmlspecialchars($booking['guest_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Bar Station</label>
                    <select class="form-select" id="barStationSelect">
                        <?php foreach ($stations as $station): ?>
                            <option value="<?= htmlspecialchars($station['name']) ?>"><?= htmlspecialchars($station['name']) ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($stations)): ?>
                            <option value="Main Bar">Main Bar</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Guest Count</label>
                    <input type="number" class="form-control" id="guestCount" value="1" min="1">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Link to Customer (Optional)</label>
                    <select class="form-select" id="customerSelect">
                        <option value="">-- Walk-in Guest --</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?> (<?= $customer['phone'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="createTab()">
                    <i class="bi bi-plus-lg me-1"></i>Open Tab
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Portion Selection Modal -->
<div class="modal fade" id="portionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="portionModalTitle">Select Portion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="portion-grid" id="portionOptions">
                    <!-- Populated dynamically -->
                </div>
                <div class="mt-3">
                    <label class="form-label">Quantity</label>
                    <div class="input-group">
                        <button class="btn btn-outline-secondary" type="button" onclick="adjustPortionQty(-1)">-</button>
                        <input type="number" class="form-control text-center" id="portionQty" value="1" min="1">
                        <button class="btn btn-outline-secondary" type="button" onclick="adjustPortionQty(1)">+</button>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label">Special Instructions</label>
                    <input type="text" class="form-control" id="portionInstructions" placeholder="e.g., No ice, extra lime">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmPortion()">
                    <i class="bi bi-plus-lg me-1"></i>Add to Tab
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-credit-card me-2"></i>Process Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Payment Method</h6>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-success payment-method-btn" data-method="cash">
                                <i class="bi bi-cash-stack me-2"></i>Cash
                            </button>
                            <button class="btn btn-outline-success payment-method-btn" data-method="card">
                                <i class="bi bi-credit-card me-2"></i>Card
                            </button>
                            <button class="btn btn-outline-success payment-method-btn" data-method="mpesa">
                                <i class="bi bi-phone me-2"></i>M-Pesa
                            </button>
                            <button class="btn btn-outline-success payment-method-btn" data-method="room_charge">
                                <i class="bi bi-door-open me-2"></i>Charge to Room
                            </button>
                            <button class="btn btn-outline-warning payment-method-btn" data-method="comp">
                                <i class="bi bi-gift me-2"></i>Complimentary
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Amount</h6>
                        <div class="mb-3">
                            <label class="form-label">Tab Total</label>
                            <input type="text" class="form-control form-control-lg" id="paymentTotal" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tip Amount</label>
                            <input type="number" class="form-control" id="paymentTip" value="0" step="0.01">
                        </div>
                        <div class="mb-3" id="cashReceivedGroup">
                            <label class="form-label">Cash Received</label>
                            <input type="number" class="form-control" id="cashReceived" step="0.01">
                            <div class="mt-2">
                                <strong>Change: <span id="changeAmount"><?= formatCurrency(0) ?></span></strong>
                            </div>
                        </div>
                        <div class="mb-3 d-none" id="mpesaGroup">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="mpesaPhone" placeholder="0712345678">
                        </div>
                        <div class="mb-3 d-none" id="roomChargeGroup">
                            <label class="form-label">Select Room</label>
                            <select class="form-select" id="paymentRoomSelect">
                                <?php foreach ($roomBookings as $booking): ?>
                                    <option value="<?= $booking['id'] ?>">
                                        Room <?= htmlspecialchars($booking['room_number']) ?> - <?= htmlspecialchars($booking['guest_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-lg" onclick="processPayment()">
                    <i class="bi bi-check-lg me-1"></i>Complete Payment
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= $csrfToken ?>';
const CURRENCY_SYMBOL = '<?= CURRENCY_SYMBOL ?>';
let currentTabId = null;
let currentTab = null;
let selectedProduct = null;
let selectedPortion = null;
let selectedPaymentMethod = 'cash';

// Tab type toggle
document.querySelectorAll('input[name="tabType"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('tabNameGroup').classList.toggle('d-none', this.value !== 'name');
        document.getElementById('tabCardGroup').classList.toggle('d-none', this.value !== 'card');
        document.getElementById('tabRoomGroup').classList.toggle('d-none', this.value !== 'room');
    });
});

// Payment method selection
document.querySelectorAll('.payment-method-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.payment-method-btn').forEach(b => b.classList.remove('active', 'btn-success'));
        this.classList.add('active', 'btn-success');
        this.classList.remove('btn-outline-success');
        selectedPaymentMethod = this.dataset.method;
        
        document.getElementById('cashReceivedGroup').classList.toggle('d-none', selectedPaymentMethod !== 'cash');
        document.getElementById('mpesaGroup').classList.toggle('d-none', selectedPaymentMethod !== 'mpesa');
        document.getElementById('roomChargeGroup').classList.toggle('d-none', selectedPaymentMethod !== 'room_charge');
    });
});

// Cash change calculation
document.getElementById('cashReceived')?.addEventListener('input', function() {
    const total = parseFloat(document.getElementById('paymentTotal').value.replace(/[^0-9.]/g, '')) || 0;
    const tip = parseFloat(document.getElementById('paymentTip').value) || 0;
    const received = parseFloat(this.value) || 0;
    const change = received - (total + tip);
    document.getElementById('changeAmount').textContent = formatCurrency(Math.max(0, change));
});

function formatCurrency(amount) {
    return CURRENCY_SYMBOL + ' ' + parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function showNewTabModal() {
    new bootstrap.Modal(document.getElementById('newTabModal')).show();
}

async function createTab() {
    const tabType = document.querySelector('input[name="tabType"]:checked').value;
    let tabName = '';
    let data = {
        tab_type: tabType,
        bar_station: document.getElementById('barStationSelect').value,
        guest_count: document.getElementById('guestCount').value,
        customer_id: document.getElementById('customerSelect').value || null
    };
    
    if (tabType === 'name') {
        tabName = document.getElementById('newTabName').value.trim();
        if (!tabName) {
            alert('Please enter a guest name');
            return;
        }
        data.tab_name = tabName;
    } else if (tabType === 'card') {
        const cardLast4 = document.getElementById('cardLastFour').value.trim();
        if (!cardLast4 || cardLast4.length !== 4) {
            alert('Please enter last 4 digits of card');
            return;
        }
        data.tab_name = 'Card ****' + cardLast4;
        data.card_last_four = cardLast4;
        data.preauth_amount = document.getElementById('preauthAmount').value || null;
    } else if (tabType === 'room') {
        const roomId = document.getElementById('roomBookingSelect').value;
        if (!roomId) {
            alert('Please select a room');
            return;
        }
        const roomOption = document.getElementById('roomBookingSelect').selectedOptions[0];
        data.tab_name = roomOption.textContent.trim();
        data.room_booking_id = roomId;
    }
    
    try {
        const response = await fetch('api/bar-tabs.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'open_tab', ...data, csrf_token: CSRF_TOKEN})
        });
        const result = await response.json();
        
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('newTabModal')).hide();
            location.reload(); // Refresh to show new tab
        } else {
            alert(result.message || 'Failed to create tab');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to create tab');
    }
}

async function selectTab(tabId) {
    currentTabId = tabId;
    
    // Update UI
    document.querySelectorAll('.tab-card').forEach(card => {
        card.classList.toggle('active', card.dataset.tabId == tabId);
    });
    
    // Enable buttons
    document.getElementById('btnDiscount').disabled = false;
    document.getElementById('btnTip').disabled = false;
    document.getElementById('btnSplit').disabled = false;
    document.getElementById('btnPay').disabled = false;
    
    // Load tab details
    try {
        const response = await fetch(`api/bar-tabs.php?action=get_tab&tab_id=${tabId}`);
        const result = await response.json();
        
        if (result.success) {
            currentTab = result.tab;
            renderTabDetails(currentTab);
        }
    } catch (error) {
        console.error('Error loading tab:', error);
    }
}

function renderTabDetails(tab) {
    document.getElementById('currentTabName').textContent = tab.tab_name;
    document.getElementById('currentTabMeta').textContent = `${tab.tab_number} • ${tab.bar_station || 'Main Bar'}`;
    
    // Render items
    const itemsContainer = document.getElementById('orderItems');
    if (!tab.items || tab.items.length === 0) {
        itemsContainer.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="bi bi-cup fs-3 d-block mb-2"></i>
                <p>No items yet. Add drinks from the menu.</p>
            </div>
        `;
    } else {
        itemsContainer.innerHTML = tab.items.map(item => `
            <div class="order-item ${item.status === 'voided' ? 'text-decoration-line-through text-muted' : ''}">
                <div class="item-details">
                    <div class="item-name">${item.item_name}</div>
                    <div class="item-portion">${item.portion_name || ''} ${item.special_instructions ? '• ' + item.special_instructions : ''}</div>
                </div>
                <div class="item-qty">x${item.quantity}</div>
                <div class="item-price">${formatCurrency(item.total_price)}</div>
                ${item.status !== 'voided' ? `
                    <button class="btn btn-sm btn-outline-danger" onclick="voidItem(${item.id})">
                        <i class="bi bi-x"></i>
                    </button>
                ` : ''}
            </div>
        `).join('');
    }
    
    // Update totals
    document.getElementById('subtotal').textContent = formatCurrency(tab.subtotal);
    document.getElementById('taxAmount').textContent = formatCurrency(tab.tax_amount);
    document.getElementById('discountAmount').textContent = '-' + formatCurrency(tab.discount_amount);
    document.getElementById('grandTotal').textContent = formatCurrency(tab.total_amount);
}

async function addProduct(productId) {
    if (!currentTabId) {
        alert('Please select or open a tab first');
        return;
    }
    
    const productCard = document.querySelector(`[data-product-id="${productId}"]`);
    selectedProduct = {
        id: productId,
        name: productCard.dataset.productName,
        price: parseFloat(productCard.dataset.productPrice),
        hasPortions: productCard.dataset.hasPortions === '1'
    };
    
    if (selectedProduct.hasPortions) {
        // Load portions and show modal
        await loadPortions(productId);
        new bootstrap.Modal(document.getElementById('portionModal')).show();
    } else {
        // Add directly
        await addItemToTab({
            product_id: productId,
            item_name: selectedProduct.name,
            unit_price: selectedProduct.price,
            quantity: 1
        });
    }
}

async function loadPortions(productId) {
    try {
        const response = await fetch(`api/bar-management.php?action=get_portions&product_id=${productId}`);
        const result = await response.json();
        
        document.getElementById('portionModalTitle').textContent = selectedProduct.name;
        
        const container = document.getElementById('portionOptions');
        if (result.success && result.portions.length > 0) {
            container.innerHTML = result.portions.map((p, i) => `
                <div class="portion-option ${i === 0 ? 'selected' : ''}" 
                     data-portion-id="${p.id}"
                     data-portion-name="${p.portion_name}"
                     data-portion-price="${p.selling_price}"
                     onclick="selectPortion(this)">
                    <div class="portion-name">${p.portion_name}</div>
                    <div class="portion-size">${p.portion_size_ml ? p.portion_size_ml + 'ml' : ''}</div>
                    <div class="portion-price">${formatCurrency(p.selling_price)}</div>
                </div>
            `).join('');
            
            selectedPortion = result.portions[0];
        } else {
            container.innerHTML = `
                <div class="portion-option selected" 
                     data-portion-id=""
                     data-portion-name="Standard"
                     data-portion-price="${selectedProduct.price}"
                     onclick="selectPortion(this)">
                    <div class="portion-name">Standard</div>
                    <div class="portion-price">${formatCurrency(selectedProduct.price)}</div>
                </div>
            `;
            selectedPortion = {id: null, portion_name: 'Standard', selling_price: selectedProduct.price};
        }
    } catch (error) {
        console.error('Error loading portions:', error);
    }
}

function selectPortion(element) {
    document.querySelectorAll('.portion-option').forEach(p => p.classList.remove('selected'));
    element.classList.add('selected');
    selectedPortion = {
        id: element.dataset.portionId,
        portion_name: element.dataset.portionName,
        selling_price: parseFloat(element.dataset.portionPrice)
    };
}

function adjustPortionQty(delta) {
    const input = document.getElementById('portionQty');
    input.value = Math.max(1, parseInt(input.value) + delta);
}

async function confirmPortion() {
    const qty = parseInt(document.getElementById('portionQty').value);
    const instructions = document.getElementById('portionInstructions').value;
    
    await addItemToTab({
        product_id: selectedProduct.id,
        portion_id: selectedPortion.id || null,
        item_name: selectedProduct.name,
        portion_name: selectedPortion.portion_name,
        unit_price: selectedPortion.selling_price,
        quantity: qty,
        special_instructions: instructions,
        send_to_bar: true
    });
    
    bootstrap.Modal.getInstance(document.getElementById('portionModal')).hide();
    document.getElementById('portionQty').value = 1;
    document.getElementById('portionInstructions').value = '';
}

async function addItemToTab(item) {
    try {
        const response = await fetch('api/bar-tabs.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'add_item',
                tab_id: currentTabId,
                ...item,
                csrf_token: CSRF_TOKEN
            })
        });
        const result = await response.json();
        
        if (result.success) {
            selectTab(currentTabId); // Refresh tab
        } else {
            alert(result.message || 'Failed to add item');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to add item');
    }
}

async function addRecipe(recipeId) {
    if (!currentTabId) {
        alert('Please select or open a tab first');
        return;
    }
    
    const recipeCard = document.querySelector(`[data-recipe-id="${recipeId}"]`);
    
    await addItemToTab({
        recipe_id: recipeId,
        item_name: recipeCard.dataset.productName,
        unit_price: parseFloat(recipeCard.dataset.productPrice),
        quantity: 1,
        send_to_bar: true
    });
}

async function voidItem(itemId) {
    const reason = prompt('Reason for voiding this item:');
    if (!reason) return;
    
    try {
        const response = await fetch('api/bar-tabs.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'void_item',
                item_id: itemId,
                reason: reason,
                csrf_token: CSRF_TOKEN
            })
        });
        const result = await response.json();
        
        if (result.success) {
            selectTab(currentTabId);
        } else {
            alert(result.message || 'Failed to void item');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

function showPaymentModal() {
    if (!currentTab) return;
    
    document.getElementById('paymentTotal').value = formatCurrency(currentTab.total_amount);
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

async function processPayment() {
    const tip = parseFloat(document.getElementById('paymentTip').value) || 0;
    let paymentData = {
        action: 'process_payment',
        tab_id: currentTabId,
        method: selectedPaymentMethod,
        amount: currentTab.total_amount,
        tip_amount: tip,
        csrf_token: CSRF_TOKEN
    };
    
    if (selectedPaymentMethod === 'mpesa') {
        paymentData.phone_number = document.getElementById('mpesaPhone').value;
    } else if (selectedPaymentMethod === 'room_charge') {
        paymentData.room_booking_id = document.getElementById('paymentRoomSelect').value;
    }
    
    try {
        const response = await fetch('api/bar-tabs.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(paymentData)
        });
        const result = await response.json();
        
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
            alert('Payment successful!');
            location.reload();
        } else {
            alert(result.message || 'Payment failed');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Payment failed');
    }
}

// Category filtering
document.querySelectorAll('.category-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const category = this.dataset.category;
        document.querySelectorAll('.product-card').forEach(card => {
            if (category === 'all') {
                card.style.display = '';
            } else if (category === 'cocktails') {
                card.style.display = card.dataset.isRecipe === '1' ? '' : 'none';
            } else {
                card.style.display = card.dataset.category === category ? '' : 'none';
            }
        });
    });
});

// Product search
document.getElementById('productSearch').addEventListener('input', function() {
    const search = this.value.toLowerCase();
    document.querySelectorAll('.product-card').forEach(card => {
        const name = card.dataset.productName.toLowerCase();
        card.style.display = name.includes(search) ? '' : 'none';
    });
});

async function sendBot() {
    if (!currentTabId) return;
    
    // Get pending items
    const pendingItems = currentTab.items.filter(i => i.status === 'pending');
    if (pendingItems.length === 0) {
        alert('No pending items to send to bar');
        return;
    }
    
    try {
        const response = await fetch('api/bar-tabs.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'create_bot',
                tab_id: currentTabId,
                item_ids: pendingItems.map(i => i.id),
                csrf_token: CSRF_TOKEN
            })
        });
        const result = await response.json();
        
        if (result.success) {
            alert(`BOT #${result.bot_number} sent to bar!`);
            selectTab(currentTabId);
        } else {
            alert(result.message || 'Failed to send BOT');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

function printTab() {
    if (!currentTabId) return;
    window.open(`print-bar-tab.php?tab_id=${currentTabId}`, '_blank', 'width=400,height=600');
}
</script>

<?php include 'includes/footer.php'; ?>
