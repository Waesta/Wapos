<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();

// Get WhatsApp settings
$settings = [];
$settingsResult = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'whatsapp_%'");
foreach ($settingsResult as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }
    
    $action = $data['action'] ?? '';
    
    try {
        switch ($action) {
            case 'send_order_status_update':
                $result = sendOrderStatusUpdate($data, $db, $settings);
                break;
                
            case 'send_delivery_update':
                $result = sendDeliveryUpdate($data, $db, $settings);
                break;
                
            case 'send_delivery_eta':
                $result = sendDeliveryETA($data, $db, $settings);
                break;
                
            case 'send_delivery_confirmation':
                $result = sendDeliveryConfirmation($data, $db, $settings);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function sendOrderStatusUpdate($data, $db, $settings) {
    $orderId = $data['order_id'] ?? null;
    $status = $data['status'] ?? null;
    
    if (!$orderId || !$status) {
        throw new Exception('Missing order ID or status');
    }
    
    // Get order details
    $order = $db->fetchOne("
        SELECT o.*, COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.id = ?
        GROUP BY o.id
    ", [$orderId]);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    $message = generateOrderStatusMessage($order, $status);
    
    return sendWhatsAppMessage($order['customer_phone'], $message, $db, $settings);
}

function sendDeliveryUpdate($data, $db, $settings) {
    $deliveryId = $data['delivery_id'] ?? null;
    $status = $data['status'] ?? null;
    
    if (!$deliveryId || !$status) {
        throw new Exception('Missing delivery ID or status');
    }
    
    // Get delivery details
    $delivery = $db->fetchOne("
        SELECT d.*, o.order_number, o.customer_phone, o.customer_name,
               r.name as rider_name, r.phone as rider_phone, r.vehicle_type
        FROM deliveries d
        JOIN orders o ON d.order_id = o.id
        LEFT JOIN riders r ON d.rider_id = r.id
        WHERE d.id = ?
    ", [$deliveryId]);
    
    if (!$delivery) {
        throw new Exception('Delivery not found');
    }
    
    $message = generateDeliveryStatusMessage($delivery, $status);
    
    return sendWhatsAppMessage($delivery['customer_phone'], $message, $db, $settings);
}

function sendDeliveryETA($data, $db, $settings) {
    $deliveryId = $data['delivery_id'] ?? null;
    $eta = $data['eta'] ?? null;
    
    if (!$deliveryId || !$eta) {
        throw new Exception('Missing delivery ID or ETA');
    }
    
    $delivery = $db->fetchOne("
        SELECT d.*, o.order_number, o.customer_phone, o.customer_name,
               r.name as rider_name, r.phone as rider_phone
        FROM deliveries d
        JOIN orders o ON d.order_id = o.id
        LEFT JOIN riders r ON d.rider_id = r.id
        WHERE d.id = ?
    ", [$deliveryId]);
    
    if (!$delivery) {
        throw new Exception('Delivery not found');
    }
    
    $message = "🚚 *Delivery Update*\n\n";
    $message .= "📦 Order #" . $delivery['order_number'] . "\n";
    $message .= "⏰ *Estimated arrival:* " . date('H:i', strtotime($eta)) . "\n\n";
    
    if ($delivery['rider_name']) {
        $message .= "🏍️ Rider: " . $delivery['rider_name'] . "\n";
        if ($delivery['rider_phone']) {
            $message .= "📞 Contact: " . $delivery['rider_phone'] . "\n";
        }
    }
    
    $message .= "\n📍 We're on our way to you!\n";
    $message .= "Please be available to receive your order.";
    
    return sendWhatsAppMessage($delivery['customer_phone'], $message, $db, $settings);
}

function sendDeliveryConfirmation($data, $db, $settings) {
    $deliveryId = $data['delivery_id'] ?? null;
    $photoUrl = $data['photo_url'] ?? null;
    
    if (!$deliveryId) {
        throw new Exception('Missing delivery ID');
    }
    
    $delivery = $db->fetchOne("
        SELECT d.*, o.order_number, o.customer_phone, o.customer_name, o.total_amount
        FROM deliveries d
        JOIN orders o ON d.order_id = o.id
        WHERE d.id = ?
    ", [$deliveryId]);
    
    if (!$delivery) {
        throw new Exception('Delivery not found');
    }
    
    $message = "✅ *Order Delivered Successfully!*\n\n";
    $message .= "📦 Order #" . $delivery['order_number'] . "\n";
    $message .= "💰 Total: KES " . number_format($delivery['total_amount'], 2) . "\n";
    $message .= "📅 Delivered: " . date('M j, Y H:i') . "\n\n";
    
    $message .= "Thank you for choosing us! 🙏\n\n";
    $message .= "⭐ *Rate your experience:*\n";
    $message .= "Reply with a number 1-5:\n";
    $message .= "5 = Excellent 😍\n";
    $message .= "4 = Good 😊\n";
    $message .= "3 = Average 😐\n";
    $message .= "2 = Poor 😞\n";
    $message .= "1 = Terrible 😡\n\n";
    
    $message .= "💬 We'd love to hear your feedback!";
    
    // If photo provided, send it first, then the message
    if ($photoUrl) {
        sendWhatsAppImage($delivery['customer_phone'], $photoUrl, 'Your order has been delivered!', $db, $settings);
    }
    
    return sendWhatsAppMessage($delivery['customer_phone'], $message, $db, $settings);
}

function generateOrderStatusMessage($order, $status) {
    $statusMessages = [
        'confirmed' => [
            'emoji' => '✅',
            'title' => 'Order Confirmed',
            'message' => 'Your order has been confirmed and we\'re preparing it now!'
        ],
        'preparing' => [
            'emoji' => '👨‍🍳',
            'title' => 'Order Being Prepared',
            'message' => 'Our kitchen team is preparing your delicious order!'
        ],
        'ready' => [
            'emoji' => '🎯',
            'title' => 'Order Ready',
            'message' => $order['order_type'] === 'delivery' ? 'Your order is ready and will be picked up for delivery soon!' : 'Your order is ready for pickup!'
        ],
        'out_for_delivery' => [
            'emoji' => '🚚',
            'title' => 'Out for Delivery',
            'message' => 'Your order is on its way to you!'
        ],
        'completed' => [
            'emoji' => '✅',
            'title' => 'Order Completed',
            'message' => 'Your order has been completed. Thank you!'
        ]
    ];
    
    $statusInfo = $statusMessages[$status] ?? [
        'emoji' => '📋',
        'title' => 'Order Update',
        'message' => 'Your order status has been updated.'
    ];
    
    $message = $statusInfo['emoji'] . " *" . $statusInfo['title'] . "*\n\n";
    $message .= "📋 Order #" . $order['order_number'] . "\n";
    $message .= "👤 " . ($order['customer_name'] ?: 'Customer') . "\n";
    $message .= "📦 " . $order['item_count'] . " items\n";
    $message .= "💰 KES " . number_format($order['total_amount'], 2) . "\n\n";
    
    $message .= $statusInfo['message'] . "\n\n";
    
    if ($status === 'ready' && $order['order_type'] !== 'delivery') {
        $message .= "📍 *Pickup Location:*\n";
        $message .= "[Your restaurant address]\n\n";
        $message .= "🕒 *Pickup Hours:*\n";
        $message .= "Monday - Sunday: 8:00 AM - 10:00 PM\n\n";
    }
    
    $message .= "💬 Reply to this message if you have any questions!";
    
    return $message;
}

function generateDeliveryStatusMessage($delivery, $status) {
    $statusMessages = [
        'assigned' => [
            'emoji' => '👤',
            'title' => 'Rider Assigned',
            'message' => 'A delivery rider has been assigned to your order!'
        ],
        'picked-up' => [
            'emoji' => '📦',
            'title' => 'Order Picked Up',
            'message' => 'Your order has been picked up and is on the way!'
        ],
        'in-transit' => [
            'emoji' => '🚚',
            'title' => 'On the Way',
            'message' => 'Your order is being delivered to you now!'
        ],
        'nearby' => [
            'emoji' => '📍',
            'title' => 'Rider Nearby',
            'message' => 'Your delivery rider is nearby! Please be ready to receive your order.'
        ],
        'delivered' => [
            'emoji' => '✅',
            'title' => 'Delivered',
            'message' => 'Your order has been successfully delivered!'
        ]
    ];
    
    $statusInfo = $statusMessages[$status] ?? [
        'emoji' => '🚚',
        'title' => 'Delivery Update',
        'message' => 'Your delivery status has been updated.'
    ];
    
    $message = $statusInfo['emoji'] . " *" . $statusInfo['title'] . "*\n\n";
    $message .= "📦 Order #" . $delivery['order_number'] . "\n";
    
    if ($delivery['rider_name']) {
        $message .= "🏍️ Rider: " . $delivery['rider_name'] . "\n";
        if ($delivery['rider_phone']) {
            $message .= "📞 Contact: " . $delivery['rider_phone'] . "\n";
        }
        if ($delivery['vehicle_type']) {
            $message .= "🚗 Vehicle: " . ucfirst($delivery['vehicle_type']) . "\n";
        }
    }
    
    $message .= "\n" . $statusInfo['message'] . "\n\n";
    
    if ($status === 'nearby') {
        $message .= "⏰ *Estimated arrival:* 5-10 minutes\n";
        $message .= "📱 Please keep your phone nearby\n\n";
    }
    
    $message .= "📍 Track your order status anytime by typing *track*";
    
    return $message;
}

function sendWhatsAppMessage($toPhone, $message, $db, $settings) {
    $apiToken = $settings['whatsapp_api_token'] ?? '';
    $phoneNumberId = $settings['whatsapp_phone_number_id'] ?? '';
    
    if (empty($apiToken) || empty($phoneNumberId)) {
        throw new Exception('WhatsApp API credentials not configured');
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('cURL error: ' . $error);
    }
    
    $success = $httpCode === 200;
    
    // Log the message
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
    
    if (!$success) {
        $responseData = json_decode($response, true);
        $errorMessage = $responseData['error']['message'] ?? 'Unknown error';
        throw new Exception('WhatsApp API error: ' . $errorMessage);
    }
    
    return [
        'success' => true,
        'message' => 'Message sent successfully',
        'response' => json_decode($response, true)
    ];
}

function sendWhatsAppImage($toPhone, $imageUrl, $caption, $db, $settings) {
    $apiToken = $settings['whatsapp_api_token'] ?? '';
    $phoneNumberId = $settings['whatsapp_phone_number_id'] ?? '';
    
    if (empty($apiToken) || empty($phoneNumberId)) {
        throw new Exception('WhatsApp API credentials not configured');
    }
    
    $url = "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages";
    
    $data = [
        'messaging_product' => 'whatsapp',
        'to' => $toPhone,
        'type' => 'image',
        'image' => [
            'link' => $imageUrl,
            'caption' => $caption
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $success = $httpCode === 200;
    
    // Log the message
    try {
        $db->insert('whatsapp_messages', [
            'customer_phone' => $toPhone,
            'message_type' => 'outbound',
            'content_type' => 'image',
            'message_text' => $caption,
            'media_url' => $imageUrl,
            'status' => $success ? 'sent' : 'failed',
            'api_response' => $response,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        error_log('Failed to log WhatsApp image: ' . $e->getMessage());
    }
    
    return $success;
}
?>
