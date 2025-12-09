<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * Bar & Beverage Management Service
 * 
 * Handles:
 * - Portion-based sales (tots, shots, glasses)
 * - Yield tracking (bottles → portions)
 * - Recipe/cocktail management
 * - Variance and shrinkage reporting
 */
class BarManagementService
{
    private PDO $db;

    // Standard tot/shot measures in ml
    public const MEASURES = [
        'tot_25ml' => 25,
        'tot_35ml' => 35,
        'tot_50ml' => 50,
        'shot_30ml' => 30,
        'shot_44ml' => 44,
        'shot_60ml' => 60,
        'glass_125ml' => 125,
        'glass_175ml' => 175,
        'glass_250ml' => 250,
    ];

    // Common bottle sizes in ml
    public const BOTTLE_SIZES = [
        '350ml' => 350,
        '500ml' => 500,
        '700ml' => 700,
        '750ml' => 750,
        '1L' => 1000,
        '1.5L' => 1500,
        '3L' => 3000,
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Ensure all required database tables exist
     */
    public function ensureSchema(): void
    {
        // Product portions table - defines how products can be sold
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS product_portions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT UNSIGNED NOT NULL,
                portion_name VARCHAR(100) NOT NULL,
                portion_size_ml DECIMAL(10,2) NULL COMMENT 'Size in ml for liquids',
                portion_quantity DECIMAL(10,4) NOT NULL DEFAULT 1 COMMENT 'How much of base unit this portion uses',
                selling_price DECIMAL(15,2) NOT NULL,
                cost_price DECIMAL(15,2) DEFAULT 0,
                is_default TINYINT(1) DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_product_portion (product_id, portion_name),
                INDEX idx_product (product_id),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB
        ");

        // Product yield configuration - expected yields per purchase unit
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS product_yields (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT UNSIGNED NOT NULL,
                purchase_unit VARCHAR(50) NOT NULL COMMENT 'e.g., bottle, crate, case',
                purchase_size_ml INT NULL COMMENT 'Size in ml for liquids',
                expected_portions INT NOT NULL COMMENT 'Expected number of portions per unit',
                portion_size_ml DECIMAL(10,2) NULL COMMENT 'Standard portion size',
                wastage_allowance_percent DECIMAL(5,2) DEFAULT 2.00 COMMENT 'Acceptable wastage %',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_product_yield (product_id),
                INDEX idx_product (product_id)
            ) ENGINE=InnoDB
        ");

        // Recipes for cocktails and mixed drinks
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS bar_recipes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(200) NOT NULL,
                description TEXT,
                category VARCHAR(100) DEFAULT 'Cocktail',
                selling_price DECIMAL(15,2) NOT NULL,
                preparation_notes TEXT,
                image VARCHAR(255),
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_name (name),
                INDEX idx_category (category),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB
        ");

        // Recipe ingredients
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS bar_recipe_ingredients (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                recipe_id INT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NOT NULL,
                quantity_ml DECIMAL(10,2) NULL COMMENT 'Quantity in ml for liquids',
                quantity_units DECIMAL(10,4) NULL COMMENT 'Quantity in units for non-liquids',
                is_optional TINYINT(1) DEFAULT 0,
                notes VARCHAR(255),
                FOREIGN KEY (recipe_id) REFERENCES bar_recipes(id) ON DELETE CASCADE,
                INDEX idx_recipe (recipe_id),
                INDEX idx_product (product_id)
            ) ENGINE=InnoDB
        ");

        // Bar stock tracking - tracks opened bottles
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS bar_open_stock (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT UNSIGNED NOT NULL,
                location_id INT UNSIGNED NULL COMMENT 'Bar location if multiple bars',
                bottle_size_ml INT NOT NULL,
                remaining_ml DECIMAL(10,2) NOT NULL,
                opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                opened_by INT UNSIGNED NULL,
                status ENUM('open', 'empty', 'disposed') DEFAULT 'open',
                notes VARCHAR(255),
                closed_at TIMESTAMP NULL,
                INDEX idx_product (product_id),
                INDEX idx_status (status),
                INDEX idx_location (location_id)
            ) ENGINE=InnoDB
        ");

        // Bar pour/usage log - tracks every pour for variance analysis
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS bar_pour_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT UNSIGNED NOT NULL,
                open_stock_id INT UNSIGNED NULL,
                sale_id INT UNSIGNED NULL,
                sale_item_id INT UNSIGNED NULL,
                pour_type ENUM('sale', 'wastage', 'spillage', 'comp', 'staff', 'adjustment') DEFAULT 'sale',
                quantity_ml DECIMAL(10,2) NOT NULL,
                quantity_portions DECIMAL(10,4) DEFAULT 1,
                portion_name VARCHAR(100),
                user_id INT UNSIGNED NOT NULL,
                notes VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_product (product_id),
                INDEX idx_sale (sale_id),
                INDEX idx_type (pour_type),
                INDEX idx_date (created_at),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB
        ");

        // Bar variance reports
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS bar_variance_reports (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_date DATE NOT NULL,
                product_id INT UNSIGNED NOT NULL,
                opening_stock_ml DECIMAL(15,2) NOT NULL,
                purchases_ml DECIMAL(15,2) DEFAULT 0,
                expected_usage_ml DECIMAL(15,2) NOT NULL COMMENT 'Based on sales',
                actual_usage_ml DECIMAL(15,2) NOT NULL COMMENT 'Based on stock count',
                variance_ml DECIMAL(15,2) NOT NULL,
                variance_percent DECIMAL(5,2) NOT NULL,
                variance_value DECIMAL(15,2) NOT NULL COMMENT 'Cost of variance',
                notes TEXT,
                created_by INT UNSIGNED,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_date_product (report_date, product_id),
                INDEX idx_date (report_date),
                INDEX idx_product (product_id)
            ) ENGINE=InnoDB
        ");

        // Add columns to products table if not exist
        $this->addProductColumns();
    }

    /**
     * Add bar-specific columns to products table
     */
    private function addProductColumns(): void
    {
        $columns = [
            'is_portioned' => "ALTER TABLE products ADD COLUMN is_portioned TINYINT(1) DEFAULT 0 COMMENT 'Sold in portions (tots/shots/glasses)'",
            'bottle_size_ml' => "ALTER TABLE products ADD COLUMN bottle_size_ml INT NULL COMMENT 'Size in ml for liquor bottles'",
            'default_portion_ml' => "ALTER TABLE products ADD COLUMN default_portion_ml DECIMAL(10,2) NULL COMMENT 'Default pour size in ml'",
            'is_recipe' => "ALTER TABLE products ADD COLUMN is_recipe TINYINT(1) DEFAULT 0 COMMENT 'Is a cocktail/recipe'",
            'recipe_id' => "ALTER TABLE products ADD COLUMN recipe_id INT UNSIGNED NULL COMMENT 'Link to bar_recipes'",
        ];

        foreach ($columns as $column => $sql) {
            try {
                $check = $this->db->query("SHOW COLUMNS FROM products LIKE '$column'");
                if ($check->rowCount() === 0) {
                    $this->db->exec($sql);
                }
            } catch (\PDOException $e) {
                // Column might already exist
            }
        }
    }

    /**
     * Configure a product for portion-based sales
     */
    public function configurePortionedProduct(int $productId, array $config): array
    {
        $this->ensureSchema();

        // Update product as portioned
        $stmt = $this->db->prepare("
            UPDATE products SET 
                is_portioned = 1,
                bottle_size_ml = :bottle_size,
                default_portion_ml = :portion_size
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $productId,
            'bottle_size' => $config['bottle_size_ml'] ?? null,
            'portion_size' => $config['default_portion_ml'] ?? null,
        ]);

        // Set up yield configuration
        if (!empty($config['expected_portions'])) {
            $stmt = $this->db->prepare("
                INSERT INTO product_yields 
                    (product_id, purchase_unit, purchase_size_ml, expected_portions, portion_size_ml, wastage_allowance_percent)
                VALUES 
                    (:product_id, :purchase_unit, :purchase_size, :expected_portions, :portion_size, :wastage)
                ON DUPLICATE KEY UPDATE
                    purchase_unit = VALUES(purchase_unit),
                    purchase_size_ml = VALUES(purchase_size_ml),
                    expected_portions = VALUES(expected_portions),
                    portion_size_ml = VALUES(portion_size_ml),
                    wastage_allowance_percent = VALUES(wastage_allowance_percent)
            ");
            $stmt->execute([
                'product_id' => $productId,
                'purchase_unit' => $config['purchase_unit'] ?? 'bottle',
                'purchase_size' => $config['bottle_size_ml'] ?? 750,
                'expected_portions' => $config['expected_portions'],
                'portion_size' => $config['default_portion_ml'] ?? 25,
                'wastage' => $config['wastage_percent'] ?? 2.0,
            ]);
        }

        return ['success' => true, 'product_id' => $productId];
    }

    /**
     * Add a portion option for a product
     */
    public function addPortion(int $productId, array $data): int
    {
        $this->ensureSchema();

        $stmt = $this->db->prepare("
            INSERT INTO product_portions 
                (product_id, portion_name, portion_size_ml, portion_quantity, selling_price, cost_price, is_default, sort_order)
            VALUES 
                (:product_id, :name, :size_ml, :quantity, :selling_price, :cost_price, :is_default, :sort_order)
            ON DUPLICATE KEY UPDATE
                portion_size_ml = VALUES(portion_size_ml),
                portion_quantity = VALUES(portion_quantity),
                selling_price = VALUES(selling_price),
                cost_price = VALUES(cost_price),
                is_default = VALUES(is_default),
                sort_order = VALUES(sort_order)
        ");

        $stmt->execute([
            'product_id' => $productId,
            'name' => $data['portion_name'],
            'size_ml' => $data['portion_size_ml'] ?? null,
            'quantity' => $data['portion_quantity'] ?? 1,
            'selling_price' => $data['selling_price'],
            'cost_price' => $data['cost_price'] ?? 0,
            'is_default' => $data['is_default'] ?? 0,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Get all portions for a product
     */
    public function getProductPortions(int $productId): array
    {
        $stmt = $this->db->prepare("
            SELECT pp.*, p.name as product_name, p.bottle_size_ml
            FROM product_portions pp
            JOIN products p ON pp.product_id = p.id
            WHERE pp.product_id = :product_id AND pp.is_active = 1
            ORDER BY pp.sort_order, pp.portion_size_ml
        ");
        $stmt->execute(['product_id' => $productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all portioned products (for bar menu)
     */
    public function getPortionedProducts(): array
    {
        $stmt = $this->db->query("
            SELECT p.*, c.name as category_name,
                   py.expected_portions, py.purchase_size_ml,
                   (SELECT COUNT(*) FROM product_portions WHERE product_id = p.id AND is_active = 1) as portion_count
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN product_yields py ON p.id = py.product_id
            WHERE p.is_portioned = 1 AND p.is_active = 1
            ORDER BY c.name, p.name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record a pour/sale from a portioned product
     */
    public function recordPour(array $data): int
    {
        $this->ensureSchema();

        $stmt = $this->db->prepare("
            INSERT INTO bar_pour_log 
                (product_id, open_stock_id, sale_id, sale_item_id, pour_type, 
                 quantity_ml, quantity_portions, portion_name, user_id, notes)
            VALUES 
                (:product_id, :open_stock_id, :sale_id, :sale_item_id, :pour_type,
                 :quantity_ml, :quantity_portions, :portion_name, :user_id, :notes)
        ");

        $stmt->execute([
            'product_id' => $data['product_id'],
            'open_stock_id' => $data['open_stock_id'] ?? null,
            'sale_id' => $data['sale_id'] ?? null,
            'sale_item_id' => $data['sale_item_id'] ?? null,
            'pour_type' => $data['pour_type'] ?? 'sale',
            'quantity_ml' => $data['quantity_ml'],
            'quantity_portions' => $data['quantity_portions'] ?? 1,
            'portion_name' => $data['portion_name'] ?? null,
            'user_id' => $data['user_id'],
            'notes' => $data['notes'] ?? null,
        ]);

        // Update open stock if tracking individual bottles
        if (!empty($data['open_stock_id'])) {
            $this->updateOpenStock($data['open_stock_id'], -$data['quantity_ml']);
        }

        return (int) $this->db->lastInsertId();
    }

    /**
     * Open a new bottle for tracking
     */
    public function openBottle(int $productId, int $userId, ?int $locationId = null, ?string $notes = null): int
    {
        $this->ensureSchema();

        // Get bottle size from product
        $stmt = $this->db->prepare("SELECT bottle_size_ml FROM products WHERE id = :id");
        $stmt->execute(['id' => $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product || !$product['bottle_size_ml']) {
            throw new Exception('Product does not have a bottle size configured');
        }

        $stmt = $this->db->prepare("
            INSERT INTO bar_open_stock 
                (product_id, location_id, bottle_size_ml, remaining_ml, opened_by, notes)
            VALUES 
                (:product_id, :location_id, :bottle_size, :remaining, :user_id, :notes)
        ");

        $stmt->execute([
            'product_id' => $productId,
            'location_id' => $locationId,
            'bottle_size' => $product['bottle_size_ml'],
            'remaining' => $product['bottle_size_ml'],
            'user_id' => $userId,
            'notes' => $notes,
        ]);

        // Deduct one bottle from inventory
        $this->deductInventory($productId, 1);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update remaining ml in open bottle
     */
    private function updateOpenStock(int $openStockId, float $changeMl): void
    {
        $stmt = $this->db->prepare("
            UPDATE bar_open_stock 
            SET remaining_ml = remaining_ml + :change,
                status = CASE WHEN remaining_ml + :change2 <= 0 THEN 'empty' ELSE status END,
                closed_at = CASE WHEN remaining_ml + :change3 <= 0 THEN NOW() ELSE closed_at END
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $openStockId,
            'change' => $changeMl,
            'change2' => $changeMl,
            'change3' => $changeMl,
        ]);
    }

    /**
     * Deduct from product inventory
     */
    private function deductInventory(int $productId, float $quantity): void
    {
        $stmt = $this->db->prepare("
            UPDATE products 
            SET stock_quantity = stock_quantity - :qty 
            WHERE id = :id
        ");
        $stmt->execute(['id' => $productId, 'qty' => $quantity]);
    }

    /**
     * Get open bottles for a product
     */
    public function getOpenBottles(int $productId, ?int $locationId = null): array
    {
        $sql = "
            SELECT os.*, p.name as product_name, u.full_name as opened_by_name
            FROM bar_open_stock os
            JOIN products p ON os.product_id = p.id
            LEFT JOIN users u ON os.opened_by = u.id
            WHERE os.product_id = :product_id AND os.status = 'open'
        ";
        $params = ['product_id' => $productId];

        if ($locationId !== null) {
            $sql .= " AND os.location_id = :location_id";
            $params['location_id'] = $locationId;
        }

        $sql .= " ORDER BY os.opened_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a cocktail/recipe
     */
    public function createRecipe(array $data): int
    {
        $this->ensureSchema();

        $stmt = $this->db->prepare("
            INSERT INTO bar_recipes 
                (name, description, category, selling_price, preparation_notes, image)
            VALUES 
                (:name, :description, :category, :price, :notes, :image)
        ");

        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'Cocktail',
            'price' => $data['selling_price'],
            'notes' => $data['preparation_notes'] ?? null,
            'image' => $data['image'] ?? null,
        ]);

        $recipeId = (int) $this->db->lastInsertId();

        // Add ingredients
        if (!empty($data['ingredients'])) {
            foreach ($data['ingredients'] as $ingredient) {
                $this->addRecipeIngredient($recipeId, $ingredient);
            }
        }

        return $recipeId;
    }

    /**
     * Add ingredient to recipe
     */
    public function addRecipeIngredient(int $recipeId, array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO bar_recipe_ingredients 
                (recipe_id, product_id, quantity_ml, quantity_units, is_optional, notes)
            VALUES 
                (:recipe_id, :product_id, :qty_ml, :qty_units, :optional, :notes)
        ");

        $stmt->execute([
            'recipe_id' => $recipeId,
            'product_id' => $data['product_id'],
            'qty_ml' => $data['quantity_ml'] ?? null,
            'qty_units' => $data['quantity_units'] ?? null,
            'optional' => $data['is_optional'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Get recipe with ingredients
     */
    public function getRecipe(int $recipeId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM bar_recipes WHERE id = :id
        ");
        $stmt->execute(['id' => $recipeId]);
        $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$recipe) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT ri.*, p.name as product_name, p.bottle_size_ml, p.cost_price
            FROM bar_recipe_ingredients ri
            JOIN products p ON ri.product_id = p.id
            WHERE ri.recipe_id = :recipe_id
        ");
        $stmt->execute(['recipe_id' => $recipeId]);
        $recipe['ingredients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate cost
        $recipe['calculated_cost'] = $this->calculateRecipeCost($recipe['ingredients']);

        return $recipe;
    }

    /**
     * Calculate recipe cost based on ingredients
     */
    public function calculateRecipeCost(array $ingredients): float
    {
        $totalCost = 0;

        foreach ($ingredients as $ing) {
            if (!empty($ing['quantity_ml']) && !empty($ing['bottle_size_ml']) && $ing['bottle_size_ml'] > 0) {
                // Cost per ml * quantity
                $costPerMl = ($ing['cost_price'] ?? 0) / $ing['bottle_size_ml'];
                $totalCost += $costPerMl * $ing['quantity_ml'];
            } elseif (!empty($ing['quantity_units'])) {
                $totalCost += ($ing['cost_price'] ?? 0) * $ing['quantity_units'];
            }
        }

        return round($totalCost, 2);
    }

    /**
     * Get all recipes
     */
    public function getRecipes(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM bar_recipes";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY category, name";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Process a recipe sale - deduct all ingredients
     */
    public function processRecipeSale(int $recipeId, int $saleId, int $userId, int $quantity = 1): void
    {
        $recipe = $this->getRecipe($recipeId);
        if (!$recipe) {
            throw new Exception('Recipe not found');
        }

        foreach ($recipe['ingredients'] as $ingredient) {
            if ($ingredient['is_optional']) {
                continue;
            }

            $quantityMl = ($ingredient['quantity_ml'] ?? 0) * $quantity;
            $quantityUnits = ($ingredient['quantity_units'] ?? 0) * $quantity;

            if ($quantityMl > 0) {
                $this->recordPour([
                    'product_id' => $ingredient['product_id'],
                    'sale_id' => $saleId,
                    'pour_type' => 'sale',
                    'quantity_ml' => $quantityMl,
                    'quantity_portions' => $quantity,
                    'portion_name' => $recipe['name'],
                    'user_id' => $userId,
                ]);
            }

            if ($quantityUnits > 0) {
                $this->deductInventory($ingredient['product_id'], $quantityUnits);
            }
        }
    }

    /**
     * Get usage summary for variance reporting
     */
    public function getUsageSummary(string $startDate, string $endDate, ?int $productId = null): array
    {
        $sql = "
            SELECT 
                p.id as product_id,
                p.name as product_name,
                p.bottle_size_ml,
                py.expected_portions,
                py.wastage_allowance_percent,
                SUM(CASE WHEN bpl.pour_type = 'sale' THEN bpl.quantity_ml ELSE 0 END) as sales_ml,
                SUM(CASE WHEN bpl.pour_type = 'wastage' THEN bpl.quantity_ml ELSE 0 END) as wastage_ml,
                SUM(CASE WHEN bpl.pour_type = 'spillage' THEN bpl.quantity_ml ELSE 0 END) as spillage_ml,
                SUM(CASE WHEN bpl.pour_type = 'comp' THEN bpl.quantity_ml ELSE 0 END) as comp_ml,
                SUM(CASE WHEN bpl.pour_type = 'staff' THEN bpl.quantity_ml ELSE 0 END) as staff_ml,
                SUM(bpl.quantity_ml) as total_usage_ml,
                COUNT(DISTINCT bpl.id) as pour_count
            FROM products p
            LEFT JOIN product_yields py ON p.id = py.product_id
            LEFT JOIN bar_pour_log bpl ON p.id = bpl.product_id 
                AND bpl.created_at BETWEEN :start_date AND :end_date
            WHERE p.is_portioned = 1
        ";

        $params = [
            'start_date' => $startDate . ' 00:00:00',
            'end_date' => $endDate . ' 23:59:59',
        ];

        if ($productId) {
            $sql .= " AND p.id = :product_id";
            $params['product_id'] = $productId;
        }

        $sql .= " GROUP BY p.id ORDER BY p.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate expected yield from bottles
     */
    public function calculateExpectedYield(int $productId, int $bottleCount): array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, py.*
            FROM products p
            LEFT JOIN product_yields py ON p.id = py.product_id
            WHERE p.id = :id
        ");
        $stmt->execute(['id' => $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return [];
        }

        $bottleSizeMl = $product['bottle_size_ml'] ?? $product['purchase_size_ml'] ?? 750;
        $portionSizeMl = $product['default_portion_ml'] ?? $product['portion_size_ml'] ?? 25;
        $wastagePercent = $product['wastage_allowance_percent'] ?? 2;

        $totalMl = $bottleSizeMl * $bottleCount;
        $usableMl = $totalMl * (1 - ($wastagePercent / 100));
        $expectedPortions = floor($usableMl / $portionSizeMl);

        return [
            'product_id' => $productId,
            'product_name' => $product['name'],
            'bottle_count' => $bottleCount,
            'bottle_size_ml' => $bottleSizeMl,
            'total_ml' => $totalMl,
            'wastage_percent' => $wastagePercent,
            'wastage_ml' => $totalMl - $usableMl,
            'usable_ml' => $usableMl,
            'portion_size_ml' => $portionSizeMl,
            'expected_portions' => $expectedPortions,
        ];
    }

    /**
     * Record wastage/spillage
     */
    public function recordWastage(int $productId, float $quantityMl, string $type, int $userId, ?string $notes = null): int
    {
        if (!in_array($type, ['wastage', 'spillage', 'comp', 'staff'])) {
            $type = 'wastage';
        }

        return $this->recordPour([
            'product_id' => $productId,
            'pour_type' => $type,
            'quantity_ml' => $quantityMl,
            'user_id' => $userId,
            'notes' => $notes,
        ]);
    }

    /**
     * Get standard measures list
     */
    public function getMeasures(): array
    {
        return self::MEASURES;
    }

    /**
     * Get standard bottle sizes
     */
    public function getBottleSizes(): array
    {
        return self::BOTTLE_SIZES;
    }
}
