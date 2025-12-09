<?php
/**
 * Bar & Beverage Management
 * 
 * Manage portion-based products, recipes, and track pours
 */

use App\Services\BarManagementService;

require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager', 'bartender']);

$db = Database::getInstance();
$pdo = $db->getConnection();
$barService = new BarManagementService($pdo);
$barService->ensureSchema();

$csrfToken = generateCSRFToken();
$userRole = strtolower($auth->getRole() ?? '');
$canManage = in_array($userRole, ['admin', 'manager']);

// Get all products that could be portioned (liquor, wine, etc.)
$allProducts = $db->fetchAll("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.is_active = 1 
    ORDER BY c.name, p.name
");

// Get portioned products
$portionedProducts = $barService->getPortionedProducts();

// Get recipes
$recipes = $barService->getRecipes();

// Get categories for filtering
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");

// Get standard measures
$measures = $barService->getMeasures();
$bottleSizes = $barService->getBottleSizes();

$pageTitle = 'Bar & Beverage Management';
include 'includes/header.php';
?>

<style>
    .measure-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        background: var(--bs-primary-bg-subtle);
        color: var(--bs-primary);
    }
    .portion-card {
        border: 1px solid var(--bs-border-color);
        border-radius: 0.5rem;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        background: var(--bs-body-bg);
    }
    .portion-card:hover {
        border-color: var(--bs-primary);
    }
    .bottle-indicator {
        width: 40px;
        height: 80px;
        border: 2px solid var(--bs-border-color);
        border-radius: 0 0 8px 8px;
        position: relative;
        overflow: hidden;
        background: #f8f9fa;
    }
    .bottle-fill {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(to top, #8b4513, #d2691e);
        transition: height 0.3s ease;
    }
    .recipe-ingredient {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem;
        border-bottom: 1px solid var(--bs-border-color);
    }
    .recipe-ingredient:last-child {
        border-bottom: none;
    }
    .nav-tabs .nav-link {
        font-weight: 500;
    }
    .nav-tabs .nav-link.active {
        background: var(--bs-primary);
        color: white;
        border-color: var(--bs-primary);
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-cup-straw me-2"></i>Bar & Beverage Management</h4>
            <p class="text-muted mb-0">Manage tots, shots, portions, and cocktail recipes</p>
        </div>
        <?php if ($canManage): ?>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#configureProductModal">
                <i class="bi bi-gear me-1"></i>Configure Product
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#recipeModal">
                <i class="bi bi-plus-lg me-1"></i>New Recipe
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                                <i class="bi bi-droplet-half text-primary fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h3 class="mb-0"><?= count($portionedProducts) ?></h3>
                            <small class="text-muted">Portioned Products</small>
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
                                <i class="bi bi-cup-hot text-success fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h3 class="mb-0"><?= count($recipes) ?></h3>
                            <small class="text-muted">Cocktail Recipes</small>
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
                                <i class="bi bi-rulers text-warning fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h3 class="mb-0"><?= count($measures) ?></h3>
                            <small class="text-muted">Standard Measures</small>
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
                            <div class="bg-info bg-opacity-10 rounded-3 p-3">
                                <i class="bi bi-graph-up text-info fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <a href="bar-variance-report.php" class="text-decoration-none">
                                <span class="text-dark">Variance Report</span>
                                <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                            <br><small class="text-muted">Track usage & shrinkage</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#portionedTab">
                <i class="bi bi-droplet me-1"></i>Portioned Products
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#recipesTab">
                <i class="bi bi-cup-hot me-1"></i>Cocktail Recipes
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#measuresTab">
                <i class="bi bi-rulers me-1"></i>Standard Measures
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#openBottlesTab">
                <i class="bi bi-box-seam me-1"></i>Open Bottles
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Portioned Products Tab -->
        <div class="tab-pane fade show active" id="portionedTab">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Products Sold by Portion (Tots/Shots/Glasses)</h6>
                    <input type="text" class="form-control form-control-sm" style="max-width: 250px;" 
                           placeholder="Search products..." id="searchPortioned">
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="portionedTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Bottle Size</th>
                                    <th>Default Portion</th>
                                    <th>Expected Yield</th>
                                    <th>Portions</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($portionedProducts)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="bi bi-info-circle me-2"></i>No portioned products configured yet.
                                        <?php if ($canManage): ?>
                                        <br><a href="#" data-bs-toggle="modal" data-bs-target="#configureProductModal">Configure a product</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($portionedProducts as $product): ?>
                                <tr data-search="<?= htmlspecialchars(strtolower($product['name'] . ' ' . ($product['category_name'] ?? ''))) ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($product['name']) ?></strong>
                                        <?php if ($product['sku']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($product['sku']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($product['category_name'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($product['bottle_size_ml']): ?>
                                        <span class="measure-badge"><?= $product['bottle_size_ml'] ?>ml</span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($product['default_portion_ml']): ?>
                                        <span class="measure-badge"><?= $product['default_portion_ml'] ?>ml</span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($product['expected_portions']): ?>
                                        <span class="badge bg-success"><?= $product['expected_portions'] ?> portions</span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= $product['portion_count'] ?? 0 ?> options</span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewPortions(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($canManage): ?>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="editPortionedProduct(<?= $product['id'] ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recipes Tab -->
        <div class="tab-pane fade" id="recipesTab">
            <div class="row g-3">
                <?php if (empty($recipes)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>No cocktail recipes created yet.
                        <?php if ($canManage): ?>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#recipeModal">Create your first recipe</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($recipes as $recipe): ?>
                <div class="col-md-4 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <?php if ($recipe['image']): ?>
                        <img src="<?= htmlspecialchars($recipe['image']) ?>" class="card-img-top" alt="" style="height: 150px; object-fit: cover;">
                        <?php else: ?>
                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                            <i class="bi bi-cup-hot text-muted" style="font-size: 3rem;"></i>
                        </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h6 class="card-title mb-1"><?= htmlspecialchars($recipe['name']) ?></h6>
                            <span class="badge bg-secondary mb-2"><?= htmlspecialchars($recipe['category']) ?></span>
                            <p class="card-text small text-muted mb-2">
                                <?= htmlspecialchars(substr($recipe['description'] ?? 'No description', 0, 60)) ?>...
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="text-primary"><?= formatMoney($recipe['selling_price']) ?></strong>
                                <button class="btn btn-sm btn-outline-primary" onclick="viewRecipe(<?= $recipe['id'] ?>)">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Standard Measures Tab -->
        <div class="tab-pane fade" id="measuresTab">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-droplet me-2"></i>Tot & Shot Measures</h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">Standard measures used for spirits and liquor</p>
                            <div class="row g-2">
                                <?php foreach ($measures as $name => $ml): ?>
                                <div class="col-6 col-lg-4">
                                    <div class="portion-card text-center">
                                        <div class="measure-badge mb-2"><?= $ml ?>ml</div>
                                        <div class="small text-muted"><?= ucwords(str_replace('_', ' ', $name)) ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-box me-2"></i>Standard Bottle Sizes</h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">Common bottle sizes for inventory tracking</p>
                            <div class="row g-2">
                                <?php foreach ($bottleSizes as $name => $ml): ?>
                                <div class="col-6 col-lg-4">
                                    <div class="portion-card text-center">
                                        <div class="measure-badge mb-2"><?= $ml ?>ml</div>
                                        <div class="small text-muted"><?= $name ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-calculator me-2"></i>Yield Calculator</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Bottle Size (ml)</label>
                                    <select class="form-select" id="calcBottleSize">
                                        <?php foreach ($bottleSizes as $name => $ml): ?>
                                        <option value="<?= $ml ?>" <?= $ml === 750 ? 'selected' : '' ?>><?= $name ?> (<?= $ml ?>ml)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Portion Size (ml)</label>
                                    <select class="form-select" id="calcPortionSize">
                                        <?php foreach ($measures as $name => $ml): ?>
                                        <option value="<?= $ml ?>" <?= $ml === 25 ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $name)) ?> (<?= $ml ?>ml)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Wastage %</label>
                                    <input type="number" class="form-control" id="calcWastage" value="2" min="0" max="10" step="0.5">
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-primary w-100" onclick="calculateYield()">
                                        <i class="bi bi-calculator me-1"></i>Calculate
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <div class="bg-success bg-opacity-10 rounded p-2 text-center">
                                        <div class="small text-muted">Expected Yield</div>
                                        <strong class="text-success fs-4" id="calcResult">30</strong>
                                        <div class="small text-muted">portions</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Open Bottles Tab -->
        <div class="tab-pane fade" id="openBottlesTab">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Currently Open Bottles</h6>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#openBottleModal">
                        <i class="bi bi-plus-lg me-1"></i>Open New Bottle
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-3" id="openBottlesContainer">
                        <div class="col-12 text-center text-muted py-4">
                            <i class="bi bi-box-seam fs-1 mb-2 d-block"></i>
                            <p>No open bottles being tracked</p>
                            <small>Open bottles are tracked for variance reporting</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Configure Product Modal -->
<div class="modal fade" id="configureProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-gear me-2"></i>Configure Portioned Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="configureProductForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Select Product</label>
                            <select class="form-select" name="product_id" id="configProductSelect" required>
                                <option value="">-- Select a product --</option>
                                <?php foreach ($allProducts as $product): ?>
                                <option value="<?= $product['id'] ?>" 
                                        data-bottle="<?= $product['bottle_size_ml'] ?? '' ?>"
                                        data-portion="<?= $product['default_portion_ml'] ?? '' ?>">
                                    <?= htmlspecialchars($product['name']) ?> 
                                    (<?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bottle/Purchase Size</label>
                            <div class="input-group">
                                <select class="form-select" name="bottle_size_ml" id="configBottleSize">
                                    <?php foreach ($bottleSizes as $name => $ml): ?>
                                    <option value="<?= $ml ?>" <?= $ml === 750 ? 'selected' : '' ?>><?= $name ?></option>
                                    <?php endforeach; ?>
                                    <option value="custom">Custom...</option>
                                </select>
                                <input type="number" class="form-control d-none" id="configBottleSizeCustom" placeholder="ml">
                                <span class="input-group-text">ml</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Default Portion Size</label>
                            <div class="input-group">
                                <select class="form-select" name="default_portion_ml" id="configPortionSize">
                                    <?php foreach ($measures as $name => $ml): ?>
                                    <option value="<?= $ml ?>" <?= $ml === 25 ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $name)) ?></option>
                                    <?php endforeach; ?>
                                    <option value="custom">Custom...</option>
                                </select>
                                <input type="number" class="form-control d-none" id="configPortionSizeCustom" placeholder="ml">
                                <span class="input-group-text">ml</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expected Portions per Bottle</label>
                            <input type="number" class="form-control" name="expected_portions" id="configExpectedPortions" min="1">
                            <div class="form-text">Auto-calculated based on sizes</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Wastage Allowance</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="wastage_percent" value="2" min="0" max="10" step="0.5">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Acceptable spillage/wastage percentage</div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3"><i class="bi bi-list-ul me-2"></i>Portion Options (Pricing)</h6>
                    <div id="portionOptionsContainer">
                        <!-- Portion options will be added here -->
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addPortionOption()">
                        <i class="bi bi-plus me-1"></i>Add Portion Option
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Recipe Modal -->
<div class="modal fade" id="recipeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cup-hot me-2"></i>Create Cocktail Recipe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="recipeForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Recipe Name</label>
                            <input type="text" class="form-control" name="name" required placeholder="e.g., Mojito, Margarita">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category">
                                <option value="Cocktail">Cocktail</option>
                                <option value="Mocktail">Mocktail</option>
                                <option value="Shot">Shot</option>
                                <option value="Long Drink">Long Drink</option>
                                <option value="Hot Drink">Hot Drink</option>
                                <option value="Specialty">Specialty</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Selling Price</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= htmlspecialchars(CurrencyManager::getInstance()->getCurrencySymbol()) ?></span>
                                <input type="number" class="form-control" name="selling_price" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Calculated Cost</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= htmlspecialchars(CurrencyManager::getInstance()->getCurrencySymbol()) ?></span>
                                <input type="text" class="form-control" id="recipeCost" readonly value="0.00">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="Brief description of the drink"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Preparation Notes</label>
                            <textarea class="form-control" name="preparation_notes" rows="2" placeholder="How to prepare this drink"></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3"><i class="bi bi-list-check me-2"></i>Ingredients</h6>
                    <div id="recipeIngredientsContainer">
                        <!-- Ingredients will be added here -->
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addRecipeIngredient()">
                        <i class="bi bi-plus me-1"></i>Add Ingredient
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save Recipe
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Portions Modal -->
<div class="modal fade" id="viewPortionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-droplet me-2"></i><span id="viewPortionsTitle">Portions</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="viewPortionsContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Recipe Modal -->
<div class="modal fade" id="viewRecipeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cup-hot me-2"></i><span id="viewRecipeTitle">Recipe</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="viewRecipeContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Open Bottle Modal -->
<div class="modal fade" id="openBottleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>Open New Bottle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="openBottleForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Product</label>
                        <select class="form-select" name="product_id" required>
                            <option value="">-- Select a portioned product --</option>
                            <?php foreach ($portionedProducts as $product): ?>
                            <option value="<?= $product['id'] ?>">
                                <?= htmlspecialchars($product['name']) ?>
                                <?php if ($product['bottle_size_ml']): ?>
                                (<?= $product['bottle_size_ml'] ?>ml)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <input type="text" class="form-control" name="notes" placeholder="e.g., Batch number, expiry date">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-box-seam me-1"></i>Open Bottle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= $csrfToken ?>';
const currencySymbol = '<?= htmlspecialchars(CurrencyManager::getInstance()->getCurrencySymbol()) ?>';
const allProducts = <?= json_encode($allProducts) ?>;

let portionOptionIndex = 0;
let ingredientIndex = 0;

// Search functionality
document.getElementById('searchPortioned')?.addEventListener('input', function() {
    const search = this.value.toLowerCase();
    document.querySelectorAll('#portionedTable tbody tr').forEach(row => {
        const text = row.dataset.search || '';
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

// Yield calculator
function calculateYield() {
    const bottleSize = parseInt(document.getElementById('calcBottleSize').value);
    const portionSize = parseInt(document.getElementById('calcPortionSize').value);
    const wastage = parseFloat(document.getElementById('calcWastage').value) || 0;
    
    const usableMl = bottleSize * (1 - wastage / 100);
    const portions = Math.floor(usableMl / portionSize);
    
    document.getElementById('calcResult').textContent = portions;
}

// Auto-calculate expected portions in config modal
function updateExpectedPortions() {
    const bottleSize = document.getElementById('configBottleSize').value === 'custom' 
        ? parseInt(document.getElementById('configBottleSizeCustom').value) || 0
        : parseInt(document.getElementById('configBottleSize').value);
    const portionSize = document.getElementById('configPortionSize').value === 'custom'
        ? parseInt(document.getElementById('configPortionSizeCustom').value) || 0
        : parseInt(document.getElementById('configPortionSize').value);
    
    if (bottleSize > 0 && portionSize > 0) {
        const wastage = parseFloat(document.querySelector('[name="wastage_percent"]').value) || 2;
        const usableMl = bottleSize * (1 - wastage / 100);
        document.getElementById('configExpectedPortions').value = Math.floor(usableMl / portionSize);
    }
}

document.getElementById('configBottleSize')?.addEventListener('change', function() {
    document.getElementById('configBottleSizeCustom').classList.toggle('d-none', this.value !== 'custom');
    updateExpectedPortions();
});

document.getElementById('configPortionSize')?.addEventListener('change', function() {
    document.getElementById('configPortionSizeCustom').classList.toggle('d-none', this.value !== 'custom');
    updateExpectedPortions();
});

document.getElementById('configBottleSizeCustom')?.addEventListener('input', updateExpectedPortions);
document.getElementById('configPortionSizeCustom')?.addEventListener('input', updateExpectedPortions);
document.querySelector('[name="wastage_percent"]')?.addEventListener('input', updateExpectedPortions);

// Add portion option
function addPortionOption(data = {}) {
    const container = document.getElementById('portionOptionsContainer');
    const index = portionOptionIndex++;
    
    const html = `
        <div class="row g-2 mb-2 portion-option" data-index="${index}">
            <div class="col-md-3">
                <input type="text" class="form-control form-control-sm" name="portions[${index}][name]" 
                       placeholder="e.g., Single Tot" value="${data.name || ''}" required>
            </div>
            <div class="col-md-2">
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control" name="portions[${index}][size_ml]" 
                           placeholder="25" value="${data.size_ml || ''}" min="1">
                    <span class="input-group-text">ml</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">${currencySymbol}</span>
                    <input type="number" class="form-control" name="portions[${index}][price]" 
                           placeholder="0.00" value="${data.price || ''}" step="0.01" min="0" required>
                </div>
            </div>
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Cost</span>
                    <input type="number" class="form-control" name="portions[${index}][cost]" 
                           placeholder="0.00" value="${data.cost || ''}" step="0.01" min="0">
                </div>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.portion-option').remove()">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}

// Add recipe ingredient
function addRecipeIngredient(data = {}) {
    const container = document.getElementById('recipeIngredientsContainer');
    const index = ingredientIndex++;
    
    let productOptions = '<option value="">Select ingredient...</option>';
    allProducts.forEach(p => {
        productOptions += `<option value="${p.id}" data-cost="${p.cost_price}" data-bottle="${p.bottle_size_ml || ''}">${p.name}</option>`;
    });
    
    const html = `
        <div class="row g-2 mb-2 recipe-ingredient-row" data-index="${index}">
            <div class="col-md-5">
                <select class="form-select form-select-sm" name="ingredients[${index}][product_id]" 
                        onchange="updateIngredientCost(this)" required>
                    ${productOptions}
                </select>
            </div>
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control ingredient-qty" name="ingredients[${index}][quantity_ml]" 
                           placeholder="30" value="${data.quantity_ml || ''}" min="0" step="0.1"
                           onchange="updateRecipeCost()">
                    <span class="input-group-text">ml</span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check form-check-inline mt-2">
                    <input class="form-check-input" type="checkbox" name="ingredients[${index}][optional]" value="1">
                    <label class="form-check-label small">Optional</label>
                </div>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.recipe-ingredient-row').remove(); updateRecipeCost();">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}

function updateIngredientCost(select) {
    updateRecipeCost();
}

function updateRecipeCost() {
    let totalCost = 0;
    document.querySelectorAll('.recipe-ingredient-row').forEach(row => {
        const select = row.querySelector('select');
        const qtyInput = row.querySelector('.ingredient-qty');
        const option = select.options[select.selectedIndex];
        
        if (option && option.value) {
            const costPrice = parseFloat(option.dataset.cost) || 0;
            const bottleSize = parseFloat(option.dataset.bottle) || 750;
            const qty = parseFloat(qtyInput.value) || 0;
            
            if (bottleSize > 0 && qty > 0) {
                totalCost += (costPrice / bottleSize) * qty;
            }
        }
    });
    
    document.getElementById('recipeCost').value = totalCost.toFixed(2);
}

// View portions for a product
async function viewPortions(productId, productName) {
    document.getElementById('viewPortionsTitle').textContent = productName + ' - Portions';
    const modal = new bootstrap.Modal(document.getElementById('viewPortionsModal'));
    modal.show();
    
    const content = document.getElementById('viewPortionsContent');
    content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    
    try {
        const response = await fetch(`/wapos/api/bar-management.php?action=get_portions&product_id=${productId}`);
        const data = await response.json();
        
        if (data.success && data.portions.length > 0) {
            let html = '<div class="list-group">';
            data.portions.forEach(p => {
                html += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${p.portion_name}</strong>
                            ${p.portion_size_ml ? `<span class="measure-badge ms-2">${p.portion_size_ml}ml</span>` : ''}
                            ${p.is_default ? '<span class="badge bg-primary ms-2">Default</span>' : ''}
                        </div>
                        <strong class="text-primary">${currencySymbol}${parseFloat(p.selling_price).toFixed(2)}</strong>
                    </div>
                `;
            });
            html += '</div>';
            content.innerHTML = html;
        } else {
            content.innerHTML = '<div class="alert alert-info mb-0">No portions configured for this product.</div>';
        }
    } catch (error) {
        content.innerHTML = '<div class="alert alert-danger mb-0">Failed to load portions.</div>';
    }
}

// View recipe details
async function viewRecipe(recipeId) {
    const modal = new bootstrap.Modal(document.getElementById('viewRecipeModal'));
    modal.show();
    
    const content = document.getElementById('viewRecipeContent');
    content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    
    try {
        const response = await fetch(`/wapos/api/bar-management.php?action=get_recipe&recipe_id=${recipeId}`);
        const data = await response.json();
        
        if (data.success && data.recipe) {
            const r = data.recipe;
            document.getElementById('viewRecipeTitle').textContent = r.name;
            
            let ingredientsHtml = '';
            if (r.ingredients && r.ingredients.length > 0) {
                r.ingredients.forEach(ing => {
                    ingredientsHtml += `
                        <div class="recipe-ingredient">
                            <span class="measure-badge">${ing.quantity_ml || ing.quantity_units || '?'}${ing.quantity_ml ? 'ml' : ''}</span>
                            <span>${ing.product_name}</span>
                            ${ing.is_optional ? '<span class="badge bg-secondary">Optional</span>' : ''}
                        </div>
                    `;
                });
            }
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <p class="text-muted">${r.description || 'No description'}</p>
                        <div class="d-flex gap-3 mb-3">
                            <div>
                                <small class="text-muted d-block">Selling Price</small>
                                <strong class="text-primary fs-5">${currencySymbol}${parseFloat(r.selling_price).toFixed(2)}</strong>
                            </div>
                            <div>
                                <small class="text-muted d-block">Cost</small>
                                <strong class="text-danger fs-5">${currencySymbol}${parseFloat(r.calculated_cost).toFixed(2)}</strong>
                            </div>
                            <div>
                                <small class="text-muted d-block">Margin</small>
                                <strong class="text-success fs-5">${currencySymbol}${(r.selling_price - r.calculated_cost).toFixed(2)}</strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-2">Ingredients</h6>
                        <div class="border rounded">
                            ${ingredientsHtml || '<div class="p-3 text-muted">No ingredients</div>'}
                        </div>
                    </div>
                </div>
                ${r.preparation_notes ? `
                <hr>
                <h6>Preparation</h6>
                <p class="text-muted mb-0">${r.preparation_notes}</p>
                ` : ''}
            `;
        } else {
            content.innerHTML = '<div class="alert alert-danger mb-0">Recipe not found.</div>';
        }
    } catch (error) {
        content.innerHTML = '<div class="alert alert-danger mb-0">Failed to load recipe.</div>';
    }
}

// Configure product form submission
document.getElementById('configureProductForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'configure_product');
    formData.append('csrf_token', csrfToken);
    
    // Handle custom sizes
    if (formData.get('bottle_size_ml') === 'custom') {
        formData.set('bottle_size_ml', document.getElementById('configBottleSizeCustom').value);
    }
    if (formData.get('default_portion_ml') === 'custom') {
        formData.set('default_portion_ml', document.getElementById('configPortionSizeCustom').value);
    }
    
    try {
        const response = await fetch('/wapos/api/bar-management.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            // Now add portions
            const portions = [];
            document.querySelectorAll('.portion-option').forEach(row => {
                const index = row.dataset.index;
                portions.push({
                    name: formData.get(`portions[${index}][name]`),
                    size_ml: formData.get(`portions[${index}][size_ml]`),
                    price: formData.get(`portions[${index}][price]`),
                    cost: formData.get(`portions[${index}][cost]`)
                });
            });
            
            for (const portion of portions) {
                if (portion.name && portion.price) {
                    await fetch('/wapos/api/bar-management.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'add_portion',
                            product_id: data.product_id,
                            portion_name: portion.name,
                            portion_size_ml: portion.size_ml || '',
                            selling_price: portion.price,
                            cost_price: portion.cost || '0',
                            csrf_token: csrfToken
                        })
                    });
                }
            }
            
            alert('Product configured successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to configure product'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
});

// Recipe form submission
document.getElementById('recipeForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // Collect ingredients
    const ingredients = [];
    document.querySelectorAll('.recipe-ingredient-row').forEach(row => {
        const index = row.dataset.index;
        const productId = formData.get(`ingredients[${index}][product_id]`);
        if (productId) {
            ingredients.push({
                product_id: productId,
                quantity_ml: formData.get(`ingredients[${index}][quantity_ml]`) || null,
                is_optional: formData.get(`ingredients[${index}][optional]`) ? 1 : 0
            });
        }
    });
    
    const payload = {
        action: 'create_recipe',
        name: formData.get('name'),
        description: formData.get('description'),
        category: formData.get('category'),
        selling_price: formData.get('selling_price'),
        preparation_notes: formData.get('preparation_notes'),
        ingredients: JSON.stringify(ingredients),
        csrf_token: csrfToken
    };
    
    try {
        const response = await fetch('/wapos/api/bar-management.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(payload)
        });
        const data = await response.json();
        
        if (data.success) {
            alert('Recipe created successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to create recipe'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
});

// Open bottle form
document.getElementById('openBottleForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'open_bottle');
    formData.append('csrf_token', csrfToken);
    
    try {
        const response = await fetch('/wapos/api/bar-management.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            alert('Bottle opened and tracked!');
            bootstrap.Modal.getInstance(document.getElementById('openBottleModal')).hide();
            // Refresh open bottles tab
        } else {
            alert('Error: ' + (data.error || 'Failed to open bottle'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    calculateYield();
    
    // Add default portion options when modal opens
    document.getElementById('configureProductModal')?.addEventListener('show.bs.modal', function() {
        const container = document.getElementById('portionOptionsContainer');
        if (container.children.length === 0) {
            addPortionOption({ name: 'Single Tot', size_ml: 25 });
            addPortionOption({ name: 'Double Tot', size_ml: 50 });
        }
    });
    
    // Add default ingredient when recipe modal opens
    document.getElementById('recipeModal')?.addEventListener('show.bs.modal', function() {
        const container = document.getElementById('recipeIngredientsContainer');
        if (container.children.length === 0) {
            addRecipeIngredient();
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
