<?php
require_once '../includes/bootstrap.php';

// Set content type
header('Content-Type: application/json');

// Database + cached settings
$db = Database::getInstance();
$settings = function_exists('settings_many')
    ? settings_many([
        'whatsapp_verify_token',
        'whatsapp_auto_replies',
        'whatsapp_api_token',
        'whatsapp_phone_number_id'
    ])
    : [];

// Verify webhook (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    
    $verifyToken = $settings['whatsapp_verify_token'] ?? '';
    
    if ($mode === 'subscribe' && $token === $verifyToken) {
        echo $challenge;
        exit;
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

// Handle webhook events (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    
    try {
        // Log incoming webhook data
        error_log('WhatsApp Webhook: ' . $input);
        
        // Process webhook data
        if (isset($data['entry'])) {
            foreach ($data['entry'] as $entry) {
                if (isset($entry['changes'])) {
                    foreach ($entry['changes'] as $change) {
                        if ($change['field'] === 'messages') {
                            processWhatsAppMessage($change['value'], $db, $settings);
                        }
                    }
                }
            }
        }
        
        echo json_encode(['status' => 'success']);
        
    } catch (Exception $e) {
        error_log('WhatsApp Webhook Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    exit;
}

function processWhatsAppMessage($messageData, $db, $settings) {
    if (!isset($messageData['messages'])) {
        return;
    }
    
    foreach ($messageData['messages'] as $message) {
        $messageId = $message['id'];
        $fromPhone = $message['from'];
        $timestamp = $message['timestamp'];
        $messageType = $message['type'];
        
        // Check if message already processed
        $existing = $db->fetchOne("SELECT id FROM whatsapp_messages WHERE message_id = ?", [$messageId]);
        if ($existing) {
            continue;
        }
        
        // Extract message content
        $messageText = '';
        $mediaUrl = null;
        
        switch ($messageType) {
            case 'text':
                $messageText = $message['text']['body'] ?? '';
                break;
            case 'image':
                $messageText = $message['image']['caption'] ?? '';
                $mediaUrl = $message['image']['id'] ?? null;
                break;
            case 'document':
                $messageText = $message['document']['caption'] ?? '';
                $mediaUrl = $message['document']['id'] ?? null;
                break;
        }
        
        // Store incoming message
        $db->insert('whatsapp_messages', [
            'message_id' => $messageId,
            'customer_phone' => $fromPhone,
            'message_type' => 'inbound',
            'content_type' => $messageType,
            'message_text' => $messageText,
            'media_url' => $mediaUrl,
            'timestamp' => date('Y-m-d H:i:s', $timestamp),
            'status' => 'received',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Process message content
        $response = processMessageContent($messageText, $fromPhone, $db, $settings);
        
        // Send auto-reply if enabled and response generated
        if ($response && ($settings['whatsapp_auto_replies'] ?? '0') === '1') {
            sendWhatsAppMessage($fromPhone, $response, $db, $settings);
        }
    }
}

function processMessageContent($messageText, $customerPhone, $db, $settings) {
    $messageTextLower = strtolower(trim($messageText));
    $pdo = $db->getConnection();
    $phone = preg_replace('/[^0-9+]/', '', $customerPhone);
    
    // Check if user has active conversation state
    $stateCheck = $pdo->prepare("SELECT state_data FROM whatsapp_conversation_states WHERE phone = ? AND expires_at > NOW()");
    $stateCheck->execute([$phone]);
    $stateRow = $stateCheck->fetch(PDO::FETCH_ASSOC);
    $hasActiveState = (bool)$stateRow;
    $stateData = $stateRow ? json_decode($stateRow['state_data'], true) : null;
    
    // Determine which service to use based on state or keywords
    $hospitalityKeywords = ['book', 'reserve', 'room', 'stay', 'accommodation', 'housekeeping', 
        'clean', 'towel', 'maintenance', 'repair', 'broken', 'check in', 'check out', 
        'checkout', 'checkin', 'room service'];
    
    $restaurantKeywords = ['menu', 'food', 'order', 'eat', 'hungry', 'cart', 'basket', 
        'reorder', 'pickup', 'delivery', 'hours', 'location'];
    
    $commonKeywords = ['cancel', 'stop', 'reset', 'help', 'yes', 'no', 'hi', 'hello', 'hey', 'status'];
    
    // Check if in active conversation - route to appropriate service
    if ($hasActiveState && $stateData) {
        $currentState = $stateData['state'] ?? 'idle';
        
        // Hospitality states start with 'booking_', 'room_service_', 'housekeeping_', 'maintenance_'
        if (strpos($currentState, 'booking_') === 0 || strpos($currentState, 'room_') === 0 || 
            strpos($currentState, 'housekeeping') === 0 || strpos($currentState, 'maintenance') === 0) {
            try {
                $hospitalityService = new \App\Services\HospitalityWhatsAppService($pdo);
                $result = $hospitalityService->processMessage($customerPhone, $messageText, '');
                return $result['response'] ?? null;
            } catch (Exception $e) {
                error_log('Hospitality WhatsApp error: ' . $e->getMessage());
            }
        }
        
        // Restaurant states: menu_category, adding_items, cart_review, order_type, etc.
        if (strpos($currentState, 'menu') === 0 || strpos($currentState, 'adding') === 0 || 
            strpos($currentState, 'cart') === 0 || strpos($currentState, 'order') === 0 ||
            strpos($currentState, 'delivery') === 0 || strpos($currentState, 'customer') === 0 ||
            strpos($currentState, 'confirm') === 0) {
            try {
                $restaurantService = new \App\Services\RestaurantWhatsAppService($pdo);
                $result = $restaurantService->processMessage($customerPhone, $messageText);
                return $result['response'] ?? null;
            } catch (Exception $e) {
                error_log('Restaurant WhatsApp error: ' . $e->getMessage());
            }
        }
    }
    
    // No active state - detect intent from keywords
    $isHospitality = false;
    $isRestaurant = false;
    
    foreach ($hospitalityKeywords as $keyword) {
        if (strpos($messageTextLower, $keyword) !== false) {
            $isHospitality = true;
            break;
        }
    }
    
    foreach ($restaurantKeywords as $keyword) {
        if (strpos($messageTextLower, $keyword) !== false) {
            $isRestaurant = true;
            break;
        }
    }
    
    // Route to appropriate service
    if ($isHospitality && !$isRestaurant) {
        try {
            $hospitalityService = new \App\Services\HospitalityWhatsAppService($pdo);
            $result = $hospitalityService->processMessage($customerPhone, $messageText, '');
            return $result['response'] ?? null;
        } catch (Exception $e) {
            error_log('Hospitality WhatsApp error: ' . $e->getMessage());
        }
    }
    
    if ($isRestaurant) {
        try {
            $restaurantService = new \App\Services\RestaurantWhatsAppService($pdo);
            $result = $restaurantService->processMessage($customerPhone, $messageText);
            return $result['response'] ?? null;
        } catch (Exception $e) {
            error_log('Restaurant WhatsApp error: ' . $e->getMessage());
        }
    }
    
    // Default: Show combined menu for greetings
    if (in_array($messageTextLower, ['hi', 'hello', 'hey', 'help'])) {
        return getWelcomeMenu($db);
    }
    
    // Legacy menu keywords - route to restaurant
    if (in_array($messageTextLower, ['menu', 'catalog', 'food', 'order'])) {
        try {
            $restaurantService = new \App\Services\RestaurantWhatsAppService($pdo);
            $result = $restaurantService->processMessage($customerPhone, $messageText);
            return $result['response'] ?? null;
        } catch (Exception $e) {
            error_log('Restaurant WhatsApp error: ' . $e->getMessage());
        }
    }
    
    // Order status inquiry
    if (strpos($messageText, 'status') !== false || strpos($messageText, 'order') !== false) {
        return getOrderStatus($customerPhone, $db);
    }
    
    // Location/delivery inquiry
    if (strpos($messageText, 'location') !== false || strpos($messageText, 'delivery') !== false || strpos($messageText, 'track') !== false) {
        return getDeliveryTracking($customerPhone, $db);
    }
    
    // Contact/support inquiry
    if (strpos($messageText, 'contact') !== false || strpos($messageText, 'help') !== false || strpos($messageText, 'support') !== false) {
        return getContactInfo($db);
    }
    
    // Try to parse as order
    if (strpos($messageText, 'order:') === 0 || preg_match('/\d+\s*(x|\*)\s*\w+/', $messageText)) {
        return processOrderMessage($messageText, $customerPhone, $db);
    }
    
    // Default response
    return "Hello! 👋\n\nWelcome to our restaurant! Here's how I can help:\n\n" .
           "🍽️ Type *menu* to see our food catalog\n" .
           "📋 Type *order: [items]* to place an order\n" .
           "📍 Type *status* to check your order status\n" .
           "🚚 Type *track* to track your delivery\n" .
           "📞 Type *contact* for support\n\n" .
           "Example order: *order: 2x Burger, 1x Fries, 1x Coke*";
}

function generateMenuResponse($db) {
    // Get popular menu items
    $menuItems = $db->fetchAll("
        SELECT p.name, p.price, p.description, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 AND p.stock_quantity > 0
        ORDER BY c.name, p.name
        LIMIT 15
    ");
    
    if (empty($menuItems)) {
        return "Sorry, our menu is currently being updated. Please call us for available items.";
    }
    
    $response = "🍽️ *Our Menu* 🍽️\n\n";
    $currentCategory = '';
    
    foreach ($menuItems as $item) {
        if ($currentCategory !== $item['category_name']) {
            $currentCategory = $item['category_name'];
            $response .= "\n*" . strtoupper($currentCategory) . "*\n";
        }
        
        $response .= "• " . $item['name'] . " - KES " . number_format($item['price'], 2) . "\n";
        if ($item['description']) {
            $response .= "  _" . substr($item['description'], 0, 50) . "_\n";
        }
    }
    
    $response .= "\n📱 To order, type:\n*order: 2x Burger, 1x Fries*\n\n";
    $response .= "💬 Need help? Type *contact*";
    
    return $response;
}

function getOrderStatus($customerPhone, $db) {
    // Find recent orders for this customer
    $orders = $db->fetchAll("
        SELECT o.*, d.status as delivery_status, r.name as rider_name
        FROM orders o
        LEFT JOIN deliveries d ON o.id = d.order_id
        LEFT JOIN riders r ON d.rider_id = r.id
        WHERE o.customer_phone = ? OR o.customer_phone = ?
        ORDER BY o.created_at DESC
        LIMIT 3
    ", [$customerPhone, '+' . ltrim($customerPhone, '+')]);
    
    if (empty($orders)) {
        return "I couldn't find any recent orders for this number. 🤔\n\n" .
               "If you've placed an order, please make sure you're messaging from the same number used for the order.";
    }
    
    $response = "📋 *Your Recent Orders:*\n\n";
    
    foreach ($orders as $order) {
        $response .= "🧾 *Order #" . $order['order_number'] . "*\n";
        $response .= "📅 " . date('M j, Y H:i', strtotime($order['created_at'])) . "\n";
        $response .= "💰 KES " . number_format($order['total_amount'], 2) . "\n";
        
        // Order status
        $status = $order['status'];
        $statusEmoji = [
            'pending' => '⏳',
            'confirmed' => '✅',
            'preparing' => '👨‍🍳',
            'ready' => '🎯',
            'completed' => '✅',
            'cancelled' => '❌'
        ];
        
        $response .= ($statusEmoji[$status] ?? '📋') . " Status: " . ucfirst($status) . "\n";
        
        // Delivery status if applicable
        if ($order['order_type'] === 'delivery' && $order['delivery_status']) {
            $deliveryEmoji = [
                'pending' => '⏳',
                'assigned' => '👤',
                'picked-up' => '📦',
                'in-transit' => '🚚',
                'delivered' => '✅',
                'failed' => '❌'
            ];
            
            $response .= ($deliveryEmoji[$order['delivery_status']] ?? '🚚') . " Delivery: " . ucfirst($order['delivery_status']) . "\n";
            
            if ($order['rider_name']) {
                $response .= "🏍️ Rider: " . $order['rider_name'] . "\n";
            }
        }
        
        $response .= "\n";
    }
    
    $response .= "💬 Need more help? Type *contact*";
    
    return $response;
}

function getDeliveryTracking($customerPhone, $db) {
    // Find active delivery for this customer
    $delivery = $db->fetchOne("
        SELECT d.*, o.order_number, o.customer_name, r.name as rider_name, r.phone as rider_phone
        FROM deliveries d
        JOIN orders o ON d.order_id = o.id
        LEFT JOIN riders r ON d.rider_id = r.id
        WHERE (o.customer_phone = ? OR o.customer_phone = ?)
        AND d.status IN ('assigned', 'picked-up', 'in-transit')
        ORDER BY d.created_at DESC
        LIMIT 1
    ", [$customerPhone, '+' . ltrim($customerPhone, '+')]);
    
    if (!$delivery) {
        return "🚚 No active deliveries found for this number.\n\n" .
               "If you have a recent order, it might be:\n" .
               "• Still being prepared\n" .
               "• Ready for pickup (if dine-in/takeout)\n" .
               "• Already delivered\n\n" .
               "Type *status* to check your order status.";
    }
    
    $response = "🚚 *Delivery Tracking*\n\n";
    $response .= "📦 Order #" . $delivery['order_number'] . "\n";
    
    $statusMessages = [
        'assigned' => '👤 Rider assigned - Preparing for pickup',
        'picked-up' => '📦 Order picked up - On the way!',
        'in-transit' => '🚚 Out for delivery - Almost there!'
    ];
    
    $response .= ($statusMessages[$delivery['status']] ?? 'In progress') . "\n\n";
    
    if ($delivery['rider_name']) {
        $response .= "🏍️ *Rider:* " . $delivery['rider_name'] . "\n";
        if ($delivery['rider_phone']) {
            $response .= "📞 *Contact:* " . $delivery['rider_phone'] . "\n";
        }
    }
    
    if ($delivery['estimated_delivery_time']) {
        $eta = date('H:i', strtotime($delivery['estimated_delivery_time']));
        $response .= "⏰ *ETA:* " . $eta . "\n";
    }
    
    $response .= "\n📍 We'll notify you when your order is delivered!";
    
    return $response;
}

function getContactInfo($db) {
    // Get business contact information
    $businessInfo = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('business_name', 'business_phone', 'business_email', 'business_address')");
    $info = [];
    foreach ($businessInfo as $setting) {
        $info[$setting['setting_key']] = $setting['setting_value'];
    }
    
    $response = "📞 *Contact Information*\n\n";
    $response .= "🏪 " . ($info['business_name'] ?? 'Our Restaurant') . "\n\n";
    
    if (!empty($info['business_phone'])) {
        $response .= "📱 Phone: " . $info['business_phone'] . "\n";
    }
    
    if (!empty($info['business_email'])) {
        $response .= "📧 Email: " . $info['business_email'] . "\n";
    }
    
    if (!empty($info['business_address'])) {
        $response .= "📍 Address: " . $info['business_address'] . "\n";
    }
    
    $response .= "\n🕒 *Operating Hours:*\n";
    $response .= "Monday - Sunday: 8:00 AM - 10:00 PM\n\n";
    $response .= "💬 You can also order directly through this WhatsApp chat!\n";
    $response .= "Type *menu* to see our offerings.";
    
    return $response;
}

function processOrderMessage($messageText, $customerPhone, $db) {
    // This is a simplified order processing
    // In a real implementation, you'd want more sophisticated parsing
    
    $response = "🛒 *Order Received!*\n\n";
    $response .= "Thank you for your order! We're processing it now.\n\n";
    $response .= "📝 *Your order:*\n" . $messageText . "\n\n";
    $response .= "⏰ *Estimated time:* 30-45 minutes\n";
    $response .= "💰 *Total:* We'll confirm the amount shortly\n\n";
    $response .= "✅ You'll receive updates on your order status.\n";
    $response .= "📞 Call us if you need to make changes: [phone]\n\n";
    $response .= "Thank you for choosing us! 🙏";
    
    // Here you would typically:
    // 1. Parse the order items
    // 2. Calculate total amount
    // 3. Create order in database
    // 4. Send confirmation with payment link
    
    return $response;
}

function getWelcomeMenu($db) {
    $businessInfo = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key = 'business_name'");
    $businessName = $businessInfo[0]['setting_value'] ?? 'Our Business';
    
    $response = "Welcome to {$businessName}! 👋\n\n";
    $response .= "How can we help you today?\n\n";
    $response .= "🍽️ *MENU* - Browse & order food\n";
    $response .= "🛒 *CART* - View your cart\n";
    $response .= "📋 *STATUS* - Track your order\n\n";
    $response .= "🏨 *BOOK* - Reserve a room\n";
    $response .= "🧹 *HOUSEKEEPING* - Request service\n";
    $response .= "🔧 *MAINTENANCE* - Report an issue\n\n";
    $response .= "📞 *CONTACT* - Speak to us\n";
    $response .= "📍 *LOCATION* - Find us\n\n";
    $response .= "Type a keyword to get started!";
    
    return $response;
}

function sendWhatsAppMessage($toPhone, $message, $db, $settings) {
    $apiToken = $settings['whatsapp_api_token'] ?? '';
    $phoneNumberId = $settings['whatsapp_phone_number_id'] ?? '';
    
    if (empty($apiToken) || empty($phoneNumberId)) {
        error_log('WhatsApp API credentials not configured');
        return false;
    }
    
    $url = "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages";
    
    $data = [
        'messaging_product' => 'whatsapp',
        'to' => $toPhone,
        'type' => 'text',
        'text' => [
            'body' => $message
        ]
    ];
    
    $headers = [
        'Authorization: Bearer ' . $apiToken,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $success = $httpCode === 200;
    
    // Log outbound message
    try {
        $db->insert('whatsapp_messages', [
            'customer_phone' => $toPhone,
            'message_type' => 'outbound',
            'content_type' => 'text',
            'message_text' => $message,
            'status' => $success ? 'sent' : 'failed',
            'api_response' => $response,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        error_log('Failed to log WhatsApp message: ' . $e->getMessage());
    }
    
    return $success;
}
?>
