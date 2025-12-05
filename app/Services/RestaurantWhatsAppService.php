<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * Restaurant WhatsApp Service
 * Handles WhatsApp ordering for restaurant takeout, delivery, and order status
 */
class RestaurantWhatsAppService
{
    private PDO $db;
    private ?string $accessToken;
    private ?string $phoneNumberId;

    private const STATE_IDLE = 'idle';
    private const STATE_MENU_CATEGORY = 'menu_category';
    private const STATE_ADDING_ITEMS = 'adding_items';
    private const STATE_CART_REVIEW = 'cart_review';
    private const STATE_ORDER_TYPE = 'order_type';
    private const STATE_DELIVERY_ADDRESS = 'delivery_address';
    private const STATE_CUSTOMER_NAME = 'customer_name';
    private const STATE_CONFIRM_ORDER = 'confirm_order';

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->loadSettings();
        $this->ensureStateTable();
    }

    private function loadSettings(): void
    {
        $settings = function_exists('settings_many')
            ? settings_many(['whatsapp_access_token', 'whatsapp_api_token', 'whatsapp_phone_number_id'])
            : [];
        $this->accessToken = $settings['whatsapp_access_token'] ?? $settings['whatsapp_api_token'] ?? null;
        $this->phoneNumberId = $settings['whatsapp_phone_number_id'] ?? null;
    }

    public function processMessage(string $phone, string $message): array
    {
        $phone = $this->formatPhone($phone);
        $lower = strtolower(trim($message));

        if (in_array($lower, ['cancel', 'stop', 'reset', 'start over'])) {
            $this->clearState($phone);
            return $this->getMainMenu();
        }

        $state = $this->getState($phone);
        if ($state['state'] !== self::STATE_IDLE) {
            return $this->continueFlow($phone, $message, $state);
        }

        return $this->detectIntent($phone, $lower, $message);
    }

    private function detectIntent(string $phone, string $lower, string $original): array
    {
        if ($this->hasKeyword($lower, ['menu', 'food', 'order', 'eat', 'hungry'])) {
            return $this->showCategories($phone);
        }
        if ($this->hasKeyword($lower, ['cart', 'basket', 'my order'])) {
            return $this->showCart($phone);
        }
        if ($this->hasKeyword($lower, ['status', 'track', 'where', 'my order'])) {
            return $this->getOrderStatus($phone);
        }
        if ($this->hasKeyword($lower, ['reorder', 'again', 'repeat', 'last order'])) {
            return $this->showReorderOptions($phone);
        }
        if ($this->hasKeyword($lower, ['hours', 'open', 'close', 'time'])) {
            return $this->getOperatingHours();
        }
        if ($this->hasKeyword($lower, ['location', 'address', 'where are you', 'directions'])) {
            return $this->getLocationInfo();
        }
        if ($this->hasKeyword($lower, ['hi', 'hello', 'hey'])) {
            return $this->getMainMenu();
        }
        return $this->getMainMenu(true);
    }

    private function getMainMenu(bool $showHelp = false): array
    {
        $name = $this->getBusinessName();
        $msg = $showHelp ? "I'm here to help! Here's what I can do:\n\n" : "Welcome to {$name}! 🍽️\n\n";
        $msg .= "🍔 *MENU* - Browse our menu & order\n";
        $msg .= "🛒 *CART* - View your cart\n";
        $msg .= "📋 *STATUS* - Track your order\n";
        $msg .= "🔄 *REORDER* - Order again\n";
        $msg .= "🕐 *HOURS* - Operating hours\n";
        $msg .= "📍 *LOCATION* - Find us\n\n";
        $msg .= "Type a keyword to get started!";
        return ['success' => true, 'response' => $msg, 'type' => 'menu'];
    }

    private function showCategories(string $phone): array
    {
        $categories = $this->db->query("
            SELECT c.id, c.name, COUNT(p.id) as item_count 
            FROM categories c 
            LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
            GROUP BY c.id 
            HAVING item_count > 0
            ORDER BY c.name
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($categories)) {
            return ['success' => true, 'response' => "Our menu is being updated. Please try again later or call us.", 'type' => 'error'];
        }

        $msg = "🍽️ *Our Menu Categories*\n\n";
        foreach ($categories as $i => $cat) {
            $msg .= ($i + 1) . ". *{$cat['name']}* ({$cat['item_count']} items)\n";
        }
        $msg .= "\nReply with a number to see items, or type *ALL* to see full menu.";

        $this->setState($phone, ['state' => self::STATE_MENU_CATEGORY, 'categories' => $categories, 'cart' => $this->getCart($phone)]);
        return ['success' => true, 'response' => $msg, 'type' => 'categories'];
    }

    private function continueFlow(string $phone, string $msg, array $state): array
    {
        switch ($state['state']) {
            case self::STATE_MENU_CATEGORY: return $this->handleCategorySelection($phone, $msg, $state);
            case self::STATE_ADDING_ITEMS: return $this->handleItemSelection($phone, $msg, $state);
            case self::STATE_CART_REVIEW: return $this->handleCartAction($phone, $msg, $state);
            case self::STATE_ORDER_TYPE: return $this->handleOrderType($phone, $msg, $state);
            case self::STATE_DELIVERY_ADDRESS: return $this->handleDeliveryAddress($phone, $msg, $state);
            case self::STATE_CUSTOMER_NAME: return $this->handleCustomerName($phone, $msg, $state);
            case self::STATE_CONFIRM_ORDER: return $this->handleOrderConfirmation($phone, $msg, $state);
            default: $this->clearState($phone); return $this->getMainMenu();
        }
    }

    private function handleCategorySelection(string $phone, string $msg, array $state): array
    {
        $lower = strtolower(trim($msg));
        $categories = $state['categories'];

        if ($lower === 'all') {
            return $this->showFullMenu($phone, $state);
        }

        $sel = (int)$msg;
        if ($sel < 1 || $sel > count($categories)) {
            return ['success' => true, 'response' => "Enter 1-" . count($categories) . " or *ALL*", 'type' => 'error'];
        }

        $category = $categories[$sel - 1];
        return $this->showCategoryItems($phone, $category, $state);
    }

    private function showCategoryItems(string $phone, array $category, array $state): array
    {
        $stmt = $this->db->prepare("SELECT id, name, price, description FROM products WHERE category_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$category['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $msg = "🍽️ *{$category['name']}*\n\n";
        foreach ($items as $i => $item) {
            $msg .= ($i + 1) . ". *{$item['name']}* - " . $this->formatCurrency($item['price']) . "\n";
            if ($item['description']) {
                $msg .= "   _{$item['description']}_\n";
            }
        }
        $msg .= "\n*To add to cart:* Reply with `number x quantity`\n";
        $msg .= "Example: `1 x 2` (2 of item 1)\n\n";
        $msg .= "Type *BACK* for categories, *CART* to view cart, or *CHECKOUT* to order.";

        $state['state'] = self::STATE_ADDING_ITEMS;
        $state['current_items'] = $items;
        $state['current_category'] = $category['name'];
        $this->setState($phone, $state);

        return ['success' => true, 'response' => $msg, 'type' => 'items'];
    }

    private function showFullMenu(string $phone, array $state): array
    {
        $items = $this->db->query("
            SELECT p.id, p.name, p.price, c.name as category 
            FROM products p 
            JOIN categories c ON p.category_id = c.id 
            WHERE p.is_active = 1 
            ORDER BY c.name, p.name
            LIMIT 30
        ")->fetchAll(PDO::FETCH_ASSOC);

        $msg = "🍽️ *Full Menu*\n\n";
        $currentCat = '';
        $itemNum = 0;
        foreach ($items as $item) {
            if ($currentCat !== $item['category']) {
                $currentCat = $item['category'];
                $msg .= "\n*" . strtoupper($currentCat) . "*\n";
            }
            $itemNum++;
            $msg .= "{$itemNum}. {$item['name']} - " . $this->formatCurrency($item['price']) . "\n";
        }
        $msg .= "\n*To add:* `number x quantity` (e.g., `5 x 2`)\n";
        $msg .= "Type *CART* to view cart or *CHECKOUT* to order.";

        $state['state'] = self::STATE_ADDING_ITEMS;
        $state['current_items'] = $items;
        $state['current_category'] = 'Full Menu';
        $this->setState($phone, $state);

        return ['success' => true, 'response' => $msg, 'type' => 'full_menu'];
    }

    private function handleItemSelection(string $phone, string $msg, array $state): array
    {
        $lower = strtolower(trim($msg));

        if ($lower === 'back') {
            return $this->showCategories($phone);
        }
        if ($lower === 'cart') {
            return $this->showCart($phone);
        }
        if ($lower === 'checkout' || $lower === 'order' || $lower === 'done') {
            return $this->startCheckout($phone, $state);
        }

        // Parse item selection: "1 x 2", "1x2", "1*2", "1"
        if (preg_match('/(\d+)\s*[x\*]?\s*(\d*)/', $msg, $match)) {
            $itemNum = (int)$match[1];
            $qty = !empty($match[2]) ? (int)$match[2] : 1;
            $items = $state['current_items'] ?? [];

            if ($itemNum < 1 || $itemNum > count($items)) {
                return ['success' => true, 'response' => "Invalid item number. Enter 1-" . count($items), 'type' => 'error'];
            }

            $item = $items[$itemNum - 1];
            $this->addToCart($phone, $item, $qty);

            $cart = $this->getCart($phone);
            $cartTotal = array_sum(array_column($cart, 'subtotal'));
            $cartCount = array_sum(array_column($cart, 'quantity'));

            $response = "✅ Added {$qty}x *{$item['name']}* to cart!\n\n";
            $response .= "🛒 Cart: {$cartCount} items - " . $this->formatCurrency($cartTotal) . "\n\n";
            $response .= "Add more items or type:\n";
            $response .= "• *CART* - View full cart\n";
            $response .= "• *CHECKOUT* - Place order\n";
            $response .= "• *BACK* - Browse categories";

            return ['success' => true, 'response' => $response, 'type' => 'item_added'];
        }

        return ['success' => true, 'response' => "Enter item number (e.g., `1` or `1 x 2`)", 'type' => 'error'];
    }

    private function showCart(string $phone): array
    {
        $cart = $this->getCart($phone);

        if (empty($cart)) {
            return ['success' => true, 'response' => "🛒 Your cart is empty!\n\nType *MENU* to browse our menu.", 'type' => 'empty_cart'];
        }

        $msg = "🛒 *Your Cart*\n━━━━━━━━━━━━━━━━━━\n\n";
        $total = 0;
        foreach ($cart as $i => $item) {
            $msg .= ($i + 1) . ". {$item['quantity']}x {$item['name']}\n";
            $msg .= "   " . $this->formatCurrency($item['subtotal']) . "\n";
            $total += $item['subtotal'];
        }
        $msg .= "\n━━━━━━━━━━━━━━━━━━\n";
        $msg .= "💰 *Total:* " . $this->formatCurrency($total) . "\n\n";
        $msg .= "Options:\n";
        $msg .= "• *CHECKOUT* - Place order\n";
        $msg .= "• *CLEAR* - Empty cart\n";
        $msg .= "• *MENU* - Add more items\n";
        $msg .= "• *REMOVE 1* - Remove item 1";

        $state = $this->getState($phone);
        $state['state'] = self::STATE_CART_REVIEW;
        $state['cart_total'] = $total;
        $this->setState($phone, $state);

        return ['success' => true, 'response' => $msg, 'type' => 'cart'];
    }

    private function handleCartAction(string $phone, string $msg, array $state): array
    {
        $lower = strtolower(trim($msg));

        if ($lower === 'checkout' || $lower === 'order') {
            return $this->startCheckout($phone, $state);
        }
        if ($lower === 'clear') {
            $this->clearCart($phone);
            $this->clearState($phone);
            return ['success' => true, 'response' => "🗑️ Cart cleared!\n\nType *MENU* to start fresh.", 'type' => 'cart_cleared'];
        }
        if ($lower === 'menu') {
            return $this->showCategories($phone);
        }
        if (preg_match('/remove\s*(\d+)/', $lower, $match)) {
            $this->removeFromCart($phone, (int)$match[1] - 1);
            return $this->showCart($phone);
        }

        return ['success' => true, 'response' => "Type *CHECKOUT*, *CLEAR*, *MENU*, or *REMOVE #*", 'type' => 'error'];
    }

    private function startCheckout(string $phone, array $state): array
    {
        $cart = $this->getCart($phone);
        if (empty($cart)) {
            return ['success' => true, 'response' => "Your cart is empty! Type *MENU* to add items.", 'type' => 'error'];
        }

        $msg = "📦 *Order Type*\n\n";
        $msg .= "How would you like to receive your order?\n\n";
        $msg .= "1. 🚗 *Pickup* - Collect from our location\n";
        $msg .= "2. 🚚 *Delivery* - We deliver to you\n\n";
        $msg .= "Reply with *1* or *2*";

        $state['state'] = self::STATE_ORDER_TYPE;
        $this->setState($phone, $state);

        return ['success' => true, 'response' => $msg, 'type' => 'order_type'];
    }

    private function handleOrderType(string $phone, string $msg, array $state): array
    {
        $sel = trim($msg);

        if ($sel === '1' || strtolower($sel) === 'pickup') {
            $state['order_type'] = 'pickup';
            $state['state'] = self::STATE_CUSTOMER_NAME;
            $this->setState($phone, $state);
            return ['success' => true, 'response' => "🚗 *Pickup Order*\n\nPlease enter your name:", 'type' => 'name'];
        }

        if ($sel === '2' || strtolower($sel) === 'delivery') {
            $state['order_type'] = 'delivery';
            $state['state'] = self::STATE_DELIVERY_ADDRESS;
            $this->setState($phone, $state);
            return ['success' => true, 'response' => "🚚 *Delivery Order*\n\nPlease enter your delivery address:", 'type' => 'address'];
        }

        return ['success' => true, 'response' => "Reply *1* for Pickup or *2* for Delivery", 'type' => 'error'];
    }

    private function handleDeliveryAddress(string $phone, string $msg, array $state): array
    {
        if (strlen(trim($msg)) < 10) {
            return ['success' => true, 'response' => "Please enter a complete delivery address.", 'type' => 'error'];
        }

        $state['delivery_address'] = trim($msg);
        $state['state'] = self::STATE_CUSTOMER_NAME;
        $this->setState($phone, $state);

        return ['success' => true, 'response' => "📍 Address saved!\n\nPlease enter your name:", 'type' => 'name'];
    }

    private function handleCustomerName(string $phone, string $msg, array $state): array
    {
        if (strlen(trim($msg)) < 2) {
            return ['success' => true, 'response' => "Please enter a valid name.", 'type' => 'error'];
        }

        $state['customer_name'] = trim($msg);
        $state['state'] = self::STATE_CONFIRM_ORDER;
        $this->setState($phone, $state);

        // Show order summary
        $cart = $this->getCart($phone);
        $total = array_sum(array_column($cart, 'subtotal'));

        $msg = "📋 *Order Summary*\n━━━━━━━━━━━━━━━━━━\n\n";
        foreach ($cart as $item) {
            $msg .= "• {$item['quantity']}x {$item['name']} - " . $this->formatCurrency($item['subtotal']) . "\n";
        }
        $msg .= "\n━━━━━━━━━━━━━━━━━━\n";
        $msg .= "💰 *Total:* " . $this->formatCurrency($total) . "\n\n";
        $msg .= "👤 *Name:* {$state['customer_name']}\n";
        $msg .= "📦 *Type:* " . ucfirst($state['order_type']) . "\n";
        if ($state['order_type'] === 'delivery') {
            $msg .= "📍 *Address:* {$state['delivery_address']}\n";
        }
        $msg .= "\n━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "Reply *YES* to confirm or *NO* to cancel.";

        return ['success' => true, 'response' => $msg, 'type' => 'confirm'];
    }

    private function handleOrderConfirmation(string $phone, string $msg, array $state): array
    {
        $lower = strtolower(trim($msg));

        if (in_array($lower, ['yes', 'y', 'confirm', 'ok'])) {
            try {
                $order = $this->createOrder($phone, $state);
                $this->clearCart($phone);
                $this->clearState($phone);

                $response = "🎉 *Order Confirmed!*\n\n";
                $response .= "📋 *Order #:* {$order['order_number']}\n";
                $response .= "💰 *Total:* " . $this->formatCurrency($order['total']) . "\n\n";

                if ($state['order_type'] === 'pickup') {
                    $response .= "🚗 *Pickup*\n";
                    $response .= "⏰ Ready in: 20-30 minutes\n";
                    $response .= "📍 " . $this->getBusinessAddress() . "\n";
                } else {
                    $response .= "🚚 *Delivery*\n";
                    $response .= "⏰ Estimated: 30-45 minutes\n";
                    $response .= "📍 {$state['delivery_address']}\n";
                }

                $response .= "\n💳 *Payment:* Pay on " . ($state['order_type'] === 'pickup' ? 'pickup' : 'delivery') . "\n\n";
                $response .= "Type *STATUS* to track your order.\n";
                $response .= "Thank you for ordering! 🙏";

                return ['success' => true, 'response' => $response, 'type' => 'order_complete', 'order_id' => $order['id']];

            } catch (Exception $e) {
                error_log("WhatsApp order error: " . $e->getMessage());
                return ['success' => false, 'response' => "Sorry, there was an error. Please try again or call us.", 'type' => 'error'];
            }
        }

        if (in_array($lower, ['no', 'n', 'cancel'])) {
            $this->clearState($phone);
            return ['success' => true, 'response' => "Order cancelled. Your cart is still saved.\n\nType *CART* to view or *MENU* to continue.", 'type' => 'cancelled'];
        }

        return ['success' => true, 'response' => "Reply *YES* to confirm or *NO* to cancel.", 'type' => 'error'];
    }

    private function getOrderStatus(string $phone): array
    {
        $stmt = $this->db->prepare("
            SELECT o.*, 
                   CASE WHEN d.id IS NOT NULL THEN d.status ELSE NULL END as delivery_status,
                   r.name as rider_name, r.phone as rider_phone
            FROM orders o
            LEFT JOIN deliveries d ON o.id = d.order_id
            LEFT JOIN riders r ON d.rider_id = r.id
            WHERE o.customer_phone = ? OR o.customer_phone = ?
            ORDER BY o.created_at DESC
            LIMIT 3
        ");
        $stmt->execute([$phone, '+' . ltrim($phone, '+')]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($orders)) {
            return ['success' => true, 'response' => "No recent orders found for this number.\n\nType *MENU* to place an order.", 'type' => 'not_found'];
        }

        $msg = "📋 *Your Recent Orders*\n\n";
        foreach ($orders as $order) {
            $msg .= "━━━━━━━━━━━━━━━━━━\n";
            $msg .= "🧾 *Order #{$order['order_number']}*\n";
            $msg .= "📅 " . date('M j, H:i', strtotime($order['created_at'])) . "\n";
            $msg .= "💰 " . $this->formatCurrency($order['total_amount']) . "\n";

            $statusEmoji = ['pending' => '⏳', 'confirmed' => '✅', 'preparing' => '👨‍🍳', 'ready' => '🎯', 'completed' => '✅', 'cancelled' => '❌'];
            $msg .= ($statusEmoji[$order['status']] ?? '📋') . " Status: " . ucfirst($order['status']) . "\n";

            if ($order['delivery_status']) {
                $deliveryEmoji = ['pending' => '⏳', 'assigned' => '👤', 'picked-up' => '📦', 'in-transit' => '🚚', 'delivered' => '✅'];
                $msg .= ($deliveryEmoji[$order['delivery_status']] ?? '🚚') . " Delivery: " . ucfirst($order['delivery_status']) . "\n";
                if ($order['rider_name']) {
                    $msg .= "🏍️ Rider: {$order['rider_name']}\n";
                }
            }
            $msg .= "\n";
        }

        return ['success' => true, 'response' => $msg, 'type' => 'order_status'];
    }

    private function showReorderOptions(string $phone): array
    {
        $stmt = $this->db->prepare("
            SELECT o.id, o.order_number, o.total_amount, o.created_at
            FROM orders o
            WHERE (o.customer_phone = ? OR o.customer_phone = ?)
            AND o.status = 'completed'
            ORDER BY o.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$phone, '+' . ltrim($phone, '+')]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($orders)) {
            return ['success' => true, 'response' => "No previous orders found.\n\nType *MENU* to place a new order.", 'type' => 'not_found'];
        }

        $msg = "🔄 *Reorder*\n\nYour recent orders:\n\n";
        foreach ($orders as $i => $order) {
            $msg .= ($i + 1) . ". Order #{$order['order_number']}\n";
            $msg .= "   " . date('M j', strtotime($order['created_at'])) . " - " . $this->formatCurrency($order['total_amount']) . "\n";
        }
        $msg .= "\nReply with a number to reorder, or type *MENU* for new order.";

        return ['success' => true, 'response' => $msg, 'type' => 'reorder'];
    }

    private function getOperatingHours(): array
    {
        $hours = settings('operating_hours') ?? "Monday - Sunday: 8:00 AM - 10:00 PM";
        $msg = "🕐 *Operating Hours*\n\n{$hours}\n\n";
        $msg .= "📞 Call us: " . ($this->getBusinessPhone() ?: 'See our website') . "\n";
        $msg .= "Type *MENU* to order!";
        return ['success' => true, 'response' => $msg, 'type' => 'hours'];
    }

    private function getLocationInfo(): array
    {
        $address = $this->getBusinessAddress();
        $msg = "📍 *Our Location*\n\n{$address}\n\n";
        $msg .= "Type *MENU* to order for pickup or delivery!";
        return ['success' => true, 'response' => $msg, 'type' => 'location'];
    }

    // Cart Management
    private function getCart(string $phone): array
    {
        $stmt = $this->db->prepare("SELECT * FROM whatsapp_carts WHERE phone = ?");
        $stmt->execute([$phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (json_decode($row['cart_data'], true) ?? []) : [];
    }

    private function addToCart(string $phone, array $item, int $qty): void
    {
        $cart = $this->getCart($phone);
        $found = false;
        foreach ($cart as &$cartItem) {
            if ($cartItem['id'] === $item['id']) {
                $cartItem['quantity'] += $qty;
                $cartItem['subtotal'] = $cartItem['quantity'] * $cartItem['price'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $cart[] = ['id' => $item['id'], 'name' => $item['name'], 'price' => $item['price'], 'quantity' => $qty, 'subtotal' => $item['price'] * $qty];
        }
        $this->saveCart($phone, $cart);
    }

    private function removeFromCart(string $phone, int $index): void
    {
        $cart = $this->getCart($phone);
        if (isset($cart[$index])) {
            array_splice($cart, $index, 1);
            $this->saveCart($phone, $cart);
        }
    }

    private function saveCart(string $phone, array $cart): void
    {
        $this->ensureCartTable();
        $stmt = $this->db->prepare("INSERT INTO whatsapp_carts (phone, cart_data, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE cart_data = VALUES(cart_data), updated_at = NOW()");
        $stmt->execute([$phone, json_encode($cart)]);
    }

    private function clearCart(string $phone): void
    {
        $stmt = $this->db->prepare("DELETE FROM whatsapp_carts WHERE phone = ?");
        $stmt->execute([$phone]);
    }

    private function ensureCartTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS whatsapp_carts (phone VARCHAR(20) PRIMARY KEY, cart_data JSON, updated_at DATETIME)");
    }

    // Order Creation
    private function createOrder(string $phone, array $state): array
    {
        $cart = $this->getCart($phone);
        $total = array_sum(array_column($cart, 'subtotal'));
        $orderNumber = 'WA' . date('ymdHis') . rand(10, 99);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO orders (order_number, customer_name, customer_phone, customer_address, order_type, subtotal, total_amount, status, order_source, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'whatsapp', 'WhatsApp Order', NOW())");
            $stmt->execute([$orderNumber, $state['customer_name'], $phone, $state['delivery_address'] ?? null, $state['order_type'], $total, $total]);
            $orderId = $this->db->lastInsertId();

            $itemStmt = $this->db->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($cart as $item) {
                $itemStmt->execute([$orderId, $item['id'], $item['name'], $item['quantity'], $item['price'], $item['subtotal']]);
            }

            $this->db->commit();
            return ['id' => $orderId, 'order_number' => $orderNumber, 'total' => $total];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // State Management
    private function getState(string $phone): array
    {
        $stmt = $this->db->prepare("SELECT state_data FROM whatsapp_conversation_states WHERE phone = ? AND expires_at > NOW()");
        $stmt->execute([$phone]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? (json_decode($r['state_data'], true) ?? ['state' => self::STATE_IDLE]) : ['state' => self::STATE_IDLE];
    }

    private function setState(string $phone, array $state): void
    {
        $stmt = $this->db->prepare("INSERT INTO whatsapp_conversation_states (phone, state_data, expires_at, updated_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW()) ON DUPLICATE KEY UPDATE state_data = VALUES(state_data), expires_at = VALUES(expires_at), updated_at = NOW()");
        $stmt->execute([$phone, json_encode($state)]);
    }

    private function clearState(string $phone): void
    {
        $this->db->prepare("DELETE FROM whatsapp_conversation_states WHERE phone = ?")->execute([$phone]);
    }

    private function ensureStateTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS whatsapp_conversation_states (phone VARCHAR(20) PRIMARY KEY, state_data JSON, expires_at DATETIME, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
    }

    // Utilities
    private function hasKeyword(string $text, array $keywords): bool
    {
        foreach ($keywords as $k) if (strpos($text, $k) !== false) return true;
        return false;
    }

    private function formatPhone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', $phone);
    }

    private function formatCurrency(float $amount): string
    {
        $s = function_exists('settings') ? (settings('currency_symbol') ?? 'KES') : 'KES';
        return $s . ' ' . number_format($amount, 2);
    }

    private function getBusinessName(): string
    {
        return function_exists('settings') ? (settings('business_name') ?? 'Our Restaurant') : 'Our Restaurant';
    }

    private function getBusinessPhone(): ?string
    {
        return function_exists('settings') ? settings('business_phone') : null;
    }

    private function getBusinessAddress(): string
    {
        return function_exists('settings') ? (settings('business_address') ?? 'Contact us for location') : 'Contact us for location';
    }

    public function sendMessage(string $to, string $message): array
    {
        if (!$this->accessToken || !$this->phoneNumberId) return ['success' => false, 'error' => 'Not configured'];
        $ch = curl_init("https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['messaging_product' => 'whatsapp', 'to' => $this->formatPhone($to), 'type' => 'text', 'text' => ['body' => $message]]), CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->accessToken, 'Content-Type: application/json']]);
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['success' => $code >= 200 && $code < 300, 'response' => json_decode($r, true)];
    }

    /**
     * Send order status update to customer
     */
    public function sendOrderUpdate(int $orderId, string $status): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order || empty($order['customer_phone'])) return false;

        $messages = [
            'confirmed' => "✅ *Order Confirmed!*\n\nYour order #{$order['order_number']} has been confirmed and is being prepared.\n\n⏰ Estimated time: 20-30 minutes",
            'preparing' => "👨‍🍳 *Preparing Your Order*\n\nOrder #{$order['order_number']} is now being prepared by our kitchen.",
            'ready' => "🎯 *Order Ready!*\n\nYour order #{$order['order_number']} is ready for pickup!\n\n📍 " . $this->getBusinessAddress(),
            'out_for_delivery' => "🚚 *Out for Delivery!*\n\nYour order #{$order['order_number']} is on its way!\n\nTrack: Type *STATUS*",
            'delivered' => "✅ *Delivered!*\n\nYour order #{$order['order_number']} has been delivered.\n\nThank you for ordering! 🙏\n\nType *REORDER* to order again.",
            'completed' => "✅ *Order Complete!*\n\nThank you for your order #{$order['order_number']}!\n\nWe hope you enjoyed your meal. 🙏\n\nType *REORDER* to order again."
        ];

        if (!isset($messages[$status])) return false;

        $result = $this->sendMessage($order['customer_phone'], $messages[$status]);
        return $result['success'] ?? false;
    }
}
