<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$phone = $data['phone'] ?? '';
$message = $data['message'] ?? '';

if (empty($phone) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Phone number and message are required']);
    exit;
}

$db = Database::getInstance();

// Get WhatsApp settings from cache
$settings = function_exists('settings_many')
    ? settings_many([
        'whatsapp_api_token',
        'whatsapp_phone_number_id',
        'whatsapp_access_token'
    ])
    : [];

try {
    $result = sendWhatsAppMessage($phone, $message, $db, $settings);
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function sendWhatsAppMessage($toPhone, $message, $db, $settings) {
    $apiToken = $settings['whatsapp_api_token'] ?? '';
    $phoneNumberId = $settings['whatsapp_phone_number_id'] ?? '';
    
    if (empty($apiToken)) {
        throw new Exception('WhatsApp API token not configured');
    }
    
    if (empty($phoneNumberId)) {
        throw new Exception('WhatsApp Phone Number ID not configured');
    }
    
    // Clean phone number
    $toPhone = preg_replace('/[^0-9+]/', '', $toPhone);
    if (!str_starts_with($toPhone, '+')) {
        $toPhone = '+' . $toPhone;
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('cURL error: ' . $error);
    }
    
    $success = $httpCode === 200;
    $responseData = json_decode($response, true);
    
    // Log the message attempt
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
        $errorMessage = $responseData['error']['message'] ?? 'Unknown error';
        throw new Exception('WhatsApp API error: ' . $errorMessage);
    }
    
    return [
        'success' => true,
        'message' => 'Message sent successfully',
        'whatsapp_message_id' => $responseData['messages'][0]['id'] ?? null,
        'response' => $responseData
    ];
}
?>
