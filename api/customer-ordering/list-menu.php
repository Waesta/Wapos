<?php
/** @var Database $db */
$categoryRows = $db->fetchAll('SELECT id, name, description FROM product_categories WHERE is_active = 1 ORDER BY display_order, name');
$productRows = $db->fetchAll('
    SELECT p.id, p.name, p.description, p.category_id, p.price, p.is_active
    FROM products p
    WHERE p.is_active = 1
    ORDER BY p.category_id, p.name
');

$modifierRows = $db->fetchAll('SELECT * FROM modifiers WHERE is_active = 1 ORDER BY category, name');
$modifiersByCategory = [];
foreach ($modifierRows as $modifier) {
    $category = $modifier['category'] ?? 'General';
    $modifiersByCategory[$category][] = $modifier;
}

echo json_encode([
    'success' => true,
    'categories' => $categoryRows,
    'products' => $productRows,
    'modifiers' => $modifiersByCategory,
]);
