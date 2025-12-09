<?php
/**
 * Digital Menu - Guest-Facing Menu Display
 * 
 * Can be accessed via QR code at tables for guests to browse the menu
 * Optionally allows self-ordering if enabled
 */

// This page is public - no auth required
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/currency-config.php';
require_once 'includes/branding-helpers.php';

$db = Database::getInstance();

// Get table info if provided
$tableId = isset($_GET['table']) ? (int)$_GET['table'] : null;
$tableInfo = null;
if ($tableId) {
    $tableInfo = $db->fetchOne("SELECT * FROM restaurant_tables WHERE id = ? AND is_active = 1", [$tableId]);
}

// Get business info
$businessName = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'business_name'")['setting_value'] ?? 'Restaurant';
$businessLogo = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'branding_logo'")['setting_value'] ?? '';
$primaryColor = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'branding_primary_color'")['setting_value'] ?? '#0d6efd';

// Check if self-ordering is enabled
$selfOrderingEnabled = ($db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'digital_menu_ordering'")['setting_value'] ?? '0') === '1';

// Get categories and products
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$products = $db->fetchAll("
    SELECT p.*, c.name as category_name,
           p.is_portioned, p.bottle_size_ml, p.default_portion_ml
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1 
    ORDER BY c.name, p.name
");

// Get portions for portioned products
$productPortions = [];
try {
    $portionsData = $db->fetchAll("
        SELECT pp.* FROM product_portions pp
        JOIN products p ON pp.product_id = p.id
        WHERE pp.is_active = 1 AND p.is_portioned = 1
        ORDER BY pp.product_id, pp.sort_order, pp.portion_size_ml
    ");
    foreach ($portionsData as $portion) {
        $productPortions[$portion['product_id']][] = $portion;
    }
} catch (Throwable $e) {
    $productPortions = [];
}

// Group products by category
$productsByCategory = [];
foreach ($products as $product) {
    $catName = $product['category_name'] ?? 'Other';
    $productsByCategory[$catName][] = $product;
}

$currencySymbol = CurrencyManager::getInstance()->getCurrencySymbol();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($businessName) ?> - Menu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($primaryColor) ?>;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding-bottom: 80px;
        }
        
        .menu-header {
            background: var(--primary-color);
            color: white;
            padding: 1.5rem 1rem;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .menu-header img {
            max-height: 60px;
            margin-bottom: 0.5rem;
        }
        
        .menu-header h1 {
            font-size: 1.5rem;
            margin: 0;
            font-weight: 700;
        }
        
        .table-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        .category-nav {
            background: white;
            padding: 0.75rem;
            overflow-x: auto;
            white-space: nowrap;
            position: sticky;
            top: 120px;
            z-index: 99;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .category-nav::-webkit-scrollbar {
            display: none;
        }
        
        .category-pill {
            display: inline-block;
            padding: 0.5rem 1rem;
            margin-right: 0.5rem;
            border-radius: 2rem;
            background: #f0f0f0;
            color: #333;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .category-pill:hover,
        .category-pill.active {
            background: var(--primary-color);
            color: white;
        }
        
        .menu-section {
            padding: 1rem;
        }
        
        .category-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #333;
            margin: 1.5rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .menu-item {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            gap: 1rem;
            transition: transform 0.2s;
        }
        
        .menu-item:hover {
            transform: translateY(-2px);
        }
        
        .menu-item-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            background: #f0f0f0;
            flex-shrink: 0;
        }
        
        .menu-item-image.placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            font-size: 2rem;
        }
        
        .menu-item-content {
            flex: 1;
            min-width: 0;
        }
        
        .menu-item-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.25rem;
            color: #333;
        }
        
        .menu-item-description {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .menu-item-price {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.1rem;
        }
        
        .menu-item-badge {
            display: inline-block;
            font-size: 0.65rem;
            padding: 0.15rem 0.4rem;
            border-radius: 0.25rem;
            margin-left: 0.25rem;
            vertical-align: middle;
        }
        
        .badge-bar {
            background: #17a2b8;
            color: white;
        }
        
        .badge-popular {
            background: #ffc107;
            color: #333;
        }
        
        .portion-options {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .portion-chip {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            background: #e9ecef;
            border-radius: 1rem;
            color: #495057;
        }
        
        .cart-fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .cart-fab .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            font-size: 0.75rem;
        }
        
        .search-bar {
            padding: 0.75rem 1rem;
            background: white;
        }
        
        .search-input {
            border-radius: 2rem;
            padding-left: 2.5rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23999' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 1rem center;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }
        
        @media (min-width: 768px) {
            .menu-section {
                max-width: 800px;
                margin: 0 auto;
            }
            
            .menu-items-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .menu-item {
                margin-bottom: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="menu-header">
        <?php if ($businessLogo): ?>
        <img src="<?= htmlspecialchars($businessLogo) ?>" alt="<?= htmlspecialchars($businessName) ?>">
        <?php endif; ?>
        <h1><?= htmlspecialchars($businessName) ?></h1>
        <?php if ($tableInfo): ?>
        <div class="table-badge">
            <i class="bi bi-geo-alt me-1"></i>
            Table <?= htmlspecialchars($tableInfo['table_number']) ?>
            <?php if ($tableInfo['table_name']): ?>
            - <?= htmlspecialchars($tableInfo['table_name']) ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </header>
    
    <!-- Search -->
    <div class="search-bar">
        <input type="text" class="form-control search-input" id="searchMenu" placeholder="Search menu...">
    </div>
    
    <!-- Category Navigation -->
    <nav class="category-nav">
        <a href="#all" class="category-pill active" data-category="all">All</a>
        <?php foreach (array_keys($productsByCategory) as $catName): ?>
        <a href="#<?= urlencode($catName) ?>" class="category-pill" data-category="<?= htmlspecialchars($catName) ?>">
            <?= htmlspecialchars($catName) ?>
        </a>
        <?php endforeach; ?>
    </nav>
    
    <!-- Menu Items -->
    <main class="menu-section">
        <?php if (empty($products)): ?>
        <div class="empty-state">
            <i class="bi bi-journal-x"></i>
            <h5>Menu Coming Soon</h5>
            <p>Our menu is being updated. Please check back later.</p>
        </div>
        <?php else: ?>
        
        <?php foreach ($productsByCategory as $categoryName => $categoryProducts): ?>
        <h2 class="category-title" id="cat-<?= urlencode($categoryName) ?>" data-category="<?= htmlspecialchars($categoryName) ?>">
            <?= htmlspecialchars($categoryName) ?>
        </h2>
        <div class="menu-items-grid">
            <?php foreach ($categoryProducts as $product): ?>
            <?php 
            $isPortioned = !empty($product['is_portioned']);
            $portions = $productPortions[$product['id']] ?? [];
            ?>
            <article class="menu-item" data-name="<?= strtolower(htmlspecialchars($product['name'])) ?>" data-category="<?= htmlspecialchars($categoryName) ?>">
                <?php if (!empty($product['image'])): ?>
                <img src="<?= htmlspecialchars($product['image']) ?>" alt="" class="menu-item-image">
                <?php else: ?>
                <div class="menu-item-image placeholder">
                    <i class="bi bi-image"></i>
                </div>
                <?php endif; ?>
                
                <div class="menu-item-content">
                    <div class="menu-item-name">
                        <?= htmlspecialchars($product['name']) ?>
                        <?php if ($isPortioned): ?>
                        <span class="menu-item-badge badge-bar">BAR</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($product['description'])): ?>
                    <div class="menu-item-description"><?= htmlspecialchars($product['description']) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($isPortioned && !empty($portions)): ?>
                    <div class="portion-options">
                        <?php foreach ($portions as $portion): ?>
                        <span class="portion-chip">
                            <?= htmlspecialchars($portion['portion_name']) ?>
                            <?php if ($portion['portion_size_ml']): ?>
                            (<?= $portion['portion_size_ml'] ?>ml)
                            <?php endif; ?>
                            - <?= $currencySymbol ?><?= number_format($portion['selling_price'], 2) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="menu-item-price">
                        <?= $currencySymbol ?><?= number_format($product['selling_price'], 2) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        
        <?php endif; ?>
    </main>
    
    <?php if ($selfOrderingEnabled): ?>
    <!-- Cart FAB -->
    <button class="cart-fab" id="cartBtn" style="display: none;">
        <i class="bi bi-cart3"></i>
        <span class="badge rounded-pill" id="cartCount">0</span>
    </button>
    <?php endif; ?>
    
    <script>
        // Search functionality
        document.getElementById('searchMenu').addEventListener('input', function() {
            const search = this.value.toLowerCase();
            document.querySelectorAll('.menu-item').forEach(item => {
                const name = item.dataset.name || '';
                item.style.display = name.includes(search) ? '' : 'none';
            });
            
            // Show/hide category titles based on visible items
            document.querySelectorAll('.category-title').forEach(title => {
                const category = title.dataset.category;
                const items = document.querySelectorAll(`.menu-item[data-category="${category}"]`);
                const hasVisible = Array.from(items).some(item => item.style.display !== 'none');
                title.style.display = hasVisible ? '' : 'none';
            });
        });
        
        // Category filter
        document.querySelectorAll('.category-pill').forEach(pill => {
            pill.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Update active state
                document.querySelectorAll('.category-pill').forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                
                const category = this.dataset.category;
                
                if (category === 'all') {
                    document.querySelectorAll('.menu-item').forEach(item => item.style.display = '');
                    document.querySelectorAll('.category-title').forEach(title => title.style.display = '');
                } else {
                    document.querySelectorAll('.menu-item').forEach(item => {
                        item.style.display = item.dataset.category === category ? '' : 'none';
                    });
                    document.querySelectorAll('.category-title').forEach(title => {
                        title.style.display = title.dataset.category === category ? '' : 'none';
                    });
                }
            });
        });
    </script>
</body>
</html>
