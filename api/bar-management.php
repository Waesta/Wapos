<?php
/**
 * Bar Management API
 * 
 * Handles portion-based products, recipes, and pour tracking
 */

use App\Services\BarManagementService;

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

$auth->requireRole(['admin', 'manager', 'bartender', 'cashier']);

$db = Database::getInstance()->getConnection();
$barService = new BarManagementService($db);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = $auth->getUserId();

try {
    switch ($action) {
        case 'get_measures':
            // Get standard tot/shot measures
            echo json_encode([
                'success' => true,
                'measures' => $barService->getMeasures(),
                'bottle_sizes' => $barService->getBottleSizes(),
            ]);
            break;

        case 'configure_product':
            // Configure a product for portion-based sales
            $auth->requireRole(['admin', 'manager']);
            
            $productId = (int) ($_POST['product_id'] ?? 0);
            if (!$productId) {
                throw new Exception('Product ID required');
            }

            $config = [
                'bottle_size_ml' => (int) ($_POST['bottle_size_ml'] ?? 750),
                'default_portion_ml' => (int) ($_POST['default_portion_ml'] ?? 25),
                'expected_portions' => (int) ($_POST['expected_portions'] ?? 0),
                'purchase_unit' => $_POST['purchase_unit'] ?? 'bottle',
                'wastage_percent' => (float) ($_POST['wastage_percent'] ?? 2),
            ];

            // Auto-calculate expected portions if not provided
            if ($config['expected_portions'] === 0 && $config['bottle_size_ml'] > 0 && $config['default_portion_ml'] > 0) {
                $config['expected_portions'] = floor($config['bottle_size_ml'] / $config['default_portion_ml']);
            }

            $result = $barService->configurePortionedProduct($productId, $config);
            echo json_encode($result);
            break;

        case 'add_portion':
            // Add a portion option to a product
            $auth->requireRole(['admin', 'manager']);
            
            $productId = (int) ($_POST['product_id'] ?? 0);
            if (!$productId) {
                throw new Exception('Product ID required');
            }

            $portionId = $barService->addPortion($productId, [
                'portion_name' => $_POST['portion_name'] ?? '',
                'portion_size_ml' => (float) ($_POST['portion_size_ml'] ?? 0),
                'portion_quantity' => (float) ($_POST['portion_quantity'] ?? 1),
                'selling_price' => (float) ($_POST['selling_price'] ?? 0),
                'cost_price' => (float) ($_POST['cost_price'] ?? 0),
                'is_default' => (int) ($_POST['is_default'] ?? 0),
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            ]);

            echo json_encode(['success' => true, 'portion_id' => $portionId]);
            break;

        case 'get_portions':
            // Get all portions for a product
            $productId = (int) ($_GET['product_id'] ?? 0);
            if (!$productId) {
                throw new Exception('Product ID required');
            }

            $portions = $barService->getProductPortions($productId);
            echo json_encode(['success' => true, 'portions' => $portions]);
            break;

        case 'get_portioned_products':
            // Get all products configured for portion sales
            $products = $barService->getPortionedProducts();
            echo json_encode(['success' => true, 'products' => $products]);
            break;

        case 'open_bottle':
            // Open a new bottle for tracking
            $productId = (int) ($_POST['product_id'] ?? 0);
            if (!$productId) {
                throw new Exception('Product ID required');
            }

            $locationId = !empty($_POST['location_id']) ? (int) $_POST['location_id'] : null;
            $notes = $_POST['notes'] ?? null;

            $openStockId = $barService->openBottle($productId, $userId, $locationId, $notes);
            echo json_encode(['success' => true, 'open_stock_id' => $openStockId]);
            break;

        case 'get_open_bottles':
            // Get open bottles for a product
            $productId = (int) ($_GET['product_id'] ?? 0);
            if (!$productId) {
                throw new Exception('Product ID required');
            }

            $locationId = !empty($_GET['location_id']) ? (int) $_GET['location_id'] : null;
            $bottles = $barService->getOpenBottles($productId, $locationId);
            echo json_encode(['success' => true, 'bottles' => $bottles]);
            break;

        case 'record_pour':
            // Record a pour (sale, wastage, etc.)
            $productId = (int) ($_POST['product_id'] ?? 0);
            $quantityMl = (float) ($_POST['quantity_ml'] ?? 0);
            
            if (!$productId || $quantityMl <= 0) {
                throw new Exception('Product ID and quantity required');
            }

            $pourId = $barService->recordPour([
                'product_id' => $productId,
                'open_stock_id' => !empty($_POST['open_stock_id']) ? (int) $_POST['open_stock_id'] : null,
                'sale_id' => !empty($_POST['sale_id']) ? (int) $_POST['sale_id'] : null,
                'sale_item_id' => !empty($_POST['sale_item_id']) ? (int) $_POST['sale_item_id'] : null,
                'pour_type' => $_POST['pour_type'] ?? 'sale',
                'quantity_ml' => $quantityMl,
                'quantity_portions' => (float) ($_POST['quantity_portions'] ?? 1),
                'portion_name' => $_POST['portion_name'] ?? null,
                'user_id' => $userId,
                'notes' => $_POST['notes'] ?? null,
            ]);

            echo json_encode(['success' => true, 'pour_id' => $pourId]);
            break;

        case 'record_wastage':
            // Record wastage/spillage
            $productId = (int) ($_POST['product_id'] ?? 0);
            $quantityMl = (float) ($_POST['quantity_ml'] ?? 0);
            $type = $_POST['type'] ?? 'wastage';
            
            if (!$productId || $quantityMl <= 0) {
                throw new Exception('Product ID and quantity required');
            }

            $pourId = $barService->recordWastage($productId, $quantityMl, $type, $userId, $_POST['notes'] ?? null);
            echo json_encode(['success' => true, 'pour_id' => $pourId]);
            break;

        case 'create_recipe':
            // Create a cocktail recipe
            $auth->requireRole(['admin', 'manager']);
            
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                throw new Exception('Recipe name required');
            }

            $ingredients = [];
            if (!empty($_POST['ingredients'])) {
                $ingredients = is_array($_POST['ingredients']) 
                    ? $_POST['ingredients'] 
                    : json_decode($_POST['ingredients'], true);
            }

            $recipeId = $barService->createRecipe([
                'name' => $name,
                'description' => $_POST['description'] ?? null,
                'category' => $_POST['category'] ?? 'Cocktail',
                'selling_price' => (float) ($_POST['selling_price'] ?? 0),
                'preparation_notes' => $_POST['preparation_notes'] ?? null,
                'image' => $_POST['image'] ?? null,
                'ingredients' => $ingredients,
            ]);

            echo json_encode(['success' => true, 'recipe_id' => $recipeId]);
            break;

        case 'get_recipe':
            // Get a recipe with ingredients
            $recipeId = (int) ($_GET['recipe_id'] ?? 0);
            if (!$recipeId) {
                throw new Exception('Recipe ID required');
            }

            $recipe = $barService->getRecipe($recipeId);
            if (!$recipe) {
                throw new Exception('Recipe not found');
            }

            echo json_encode(['success' => true, 'recipe' => $recipe]);
            break;

        case 'get_recipes':
            // Get all recipes
            $activeOnly = ($_GET['active_only'] ?? '1') === '1';
            $recipes = $barService->getRecipes($activeOnly);
            echo json_encode(['success' => true, 'recipes' => $recipes]);
            break;

        case 'process_recipe_sale':
            // Process a recipe sale (deduct ingredients)
            $recipeId = (int) ($_POST['recipe_id'] ?? 0);
            $saleId = (int) ($_POST['sale_id'] ?? 0);
            $quantity = (int) ($_POST['quantity'] ?? 1);
            
            if (!$recipeId) {
                throw new Exception('Recipe ID required');
            }

            $barService->processRecipeSale($recipeId, $saleId, $userId, $quantity);
            echo json_encode(['success' => true]);
            break;

        case 'get_usage_summary':
            // Get usage summary for variance reporting
            $auth->requireRole(['admin', 'manager', 'accountant']);
            
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            $productId = !empty($_GET['product_id']) ? (int) $_GET['product_id'] : null;

            $summary = $barService->getUsageSummary($startDate, $endDate, $productId);
            echo json_encode(['success' => true, 'summary' => $summary]);
            break;

        case 'calculate_yield':
            // Calculate expected yield from bottles
            $productId = (int) ($_GET['product_id'] ?? 0);
            $bottleCount = (int) ($_GET['bottle_count'] ?? 1);
            
            if (!$productId) {
                throw new Exception('Product ID required');
            }

            $yield = $barService->calculateExpectedYield($productId, $bottleCount);
            echo json_encode(['success' => true, 'yield' => $yield]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
