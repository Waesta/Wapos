<?php
/**
 * WhatsApp Inbox - Staff Dashboard for WhatsApp Messages
 * Allows front desk and staff to view, respond to, and manage WhatsApp conversations
 */
require_once 'includes/bootstrap.php';

use App\Services\HospitalityWhatsAppService;

$auth->requireLogin();

// Check permissions
$allowedRoles = ['admin', 'manager', 'cashier', 'developer', 'super_admin', 'receptionist'];
if (!in_array(strtolower($auth->getRole() ?? ''), $allowedRoles, true)) {
    $_SESSION['error_message'] = 'You do not have permission to access WhatsApp Inbox.';
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();

// Ensure whatsapp_messages table exists with proper structure
$pdo->exec("CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(255) UNIQUE,
    customer_phone VARCHAR(20) NOT NULL,
    customer_name VARCHAR(100),
    message_type ENUM('inbound', 'outbound') NOT NULL,
    content_type VARCHAR(50) DEFAULT 'text',
    message_text TEXT,
    media_url VARCHAR(500),
    template_name VARCHAR(100),
    status VARCHAR(50) DEFAULT 'received',
    is_read TINYINT(1) DEFAULT 0,
    replied_by INT UNSIGNED,
    replied_at DATETIME,
    booking_id INT UNSIGNED,
    order_id INT UNSIGNED,
    api_response TEXT,
    timestamp DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (customer_phone),
    INDEX idx_status (status),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB");

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_reply' && !empty($_POST['phone']) && !empty($_POST['message'])) {
        $phone = sanitizeInput($_POST['phone']);
        $message = sanitizeInput($_POST['message']);
        
        try {
            $hospitalityService = new HospitalityWhatsAppService($pdo);
            $result = $hospitalityService->sendMessage($phone, $message);
            
            if ($result['success']) {
                // Log outbound message
                $stmt = $pdo->prepare("INSERT INTO whatsapp_messages 
                    (customer_phone, message_type, message_text, status, replied_by, replied_at, created_at) 
                    VALUES (?, 'outbound', ?, 'sent', ?, NOW(), NOW())");
                $stmt->execute([$phone, $message, $auth->getUserId()]);
                
                $_SESSION['success_message'] = 'Message sent successfully!';
            } else {
                $_SESSION['error_message'] = 'Failed to send message: ' . ($result['error'] ?? 'Unknown error');
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?phone=' . urlencode($phone));
        exit;
    }
    
    if ($action === 'mark_read' && !empty($_POST['phone'])) {
        $phone = sanitizeInput($_POST['phone']);
        $stmt = $pdo->prepare("UPDATE whatsapp_messages SET is_read = 1 WHERE customer_phone = ? AND is_read = 0");
        $stmt->execute([$phone]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Get selected conversation
$selectedPhone = $_GET['phone'] ?? null;

// Get all conversations (grouped by phone)
$conversations = $pdo->query("
    SELECT 
        customer_phone,
        MAX(customer_name) as customer_name,
        MAX(created_at) as last_message_at,
        COUNT(*) as total_messages,
        SUM(CASE WHEN is_read = 0 AND message_type = 'inbound' THEN 1 ELSE 0 END) as unread_count,
        (SELECT message_text FROM whatsapp_messages m2 
         WHERE m2.customer_phone = whatsapp_messages.customer_phone 
         ORDER BY created_at DESC LIMIT 1) as last_message
    FROM whatsapp_messages
    GROUP BY customer_phone
    ORDER BY last_message_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get messages for selected conversation
$messages = [];
$customerInfo = null;
if ($selectedPhone) {
    $stmt = $pdo->prepare("SELECT * FROM whatsapp_messages WHERE customer_phone = ? ORDER BY created_at ASC");
    $stmt->execute([$selectedPhone]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mark as read
    $stmt = $pdo->prepare("UPDATE whatsapp_messages SET is_read = 1 WHERE customer_phone = ? AND is_read = 0");
    $stmt->execute([$selectedPhone]);
    
    // Get customer info if exists
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE phone = ? OR phone = ? LIMIT 1");
    $stmt->execute([$selectedPhone, '+' . ltrim($selectedPhone, '+')]);
    $customerInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get active booking if exists
    $stmt = $pdo->prepare("SELECT b.*, r.room_number FROM room_bookings b 
        JOIN rooms r ON b.room_id = r.id 
        WHERE b.guest_phone = ? AND b.status IN ('confirmed', 'checked_in') 
        ORDER BY b.check_in_date DESC LIMIT 1");
    $stmt->execute([$selectedPhone]);
    $activeBooking = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get unread count for badge
$unreadTotal = $pdo->query("SELECT COUNT(*) FROM whatsapp_messages WHERE is_read = 0 AND message_type = 'inbound'")->fetchColumn();

// Get WhatsApp business phone for display
$businessPhone = settings('whatsapp_business_phone') ?? settings('business_phone') ?? '';

$pageTitle = 'WhatsApp Inbox';
include 'includes/header.php';
?>

<style>
.inbox-container { display: flex; height: calc(100vh - 120px); }
.conversation-list { width: 350px; border-right: 1px solid #dee2e6; overflow-y: auto; background: #f8f9fa; }
.conversation-item { padding: 12px 15px; border-bottom: 1px solid #e9ecef; cursor: pointer; transition: background 0.2s; }
.conversation-item:hover, .conversation-item.active { background: #e3f2fd; }
.conversation-item.unread { background: #fff3cd; font-weight: 600; }
.chat-area { flex: 1; display: flex; flex-direction: column; }
.chat-header { padding: 15px; background: #25D366; color: white; }
.chat-messages { flex: 1; overflow-y: auto; padding: 20px; background: #e5ddd5; }
.message-bubble { max-width: 70%; padding: 10px 15px; border-radius: 10px; margin-bottom: 10px; position: relative; }
.message-inbound { background: white; margin-right: auto; border-bottom-left-radius: 0; }
.message-outbound { background: #dcf8c6; margin-left: auto; border-bottom-right-radius: 0; }
.message-time { font-size: 11px; color: #667781; margin-top: 5px; }
.chat-input { padding: 15px; background: #f0f0f0; border-top: 1px solid #dee2e6; }
.no-conversation { display: flex; align-items: center; justify-content: center; height: 100%; color: #6c757d; }
.customer-info-card { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
.quick-reply-btn { margin: 2px; font-size: 12px; }
</style>

<div class="container-fluid px-0">
    <div class="inbox-container">
        <!-- Conversation List -->
        <div class="conversation-list">
            <div class="p-3 bg-white border-bottom">
                <h5 class="mb-0">
                    <i class="bi bi-whatsapp text-success me-2"></i>Inbox
                    <?php if ($unreadTotal > 0): ?>
                        <span class="badge bg-danger"><?= $unreadTotal ?></span>
                    <?php endif; ?>
                </h5>
                <small class="text-muted">WhatsApp Business Messages</small>
            </div>
            
            <?php if (empty($conversations)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-chat-dots fs-1 mb-2 d-block"></i>
                    No conversations yet
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $conv): ?>
                    <a href="?phone=<?= urlencode($conv['customer_phone']) ?>" 
                       class="conversation-item d-block text-decoration-none text-dark <?= $selectedPhone === $conv['customer_phone'] ? 'active' : '' ?> <?= $conv['unread_count'] > 0 ? 'unread' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($conv['customer_name'] ?: $conv['customer_phone']) ?>
                                </div>
                                <small class="text-muted text-truncate d-block" style="max-width: 200px;">
                                    <?= htmlspecialchars(substr($conv['last_message'] ?? '', 0, 50)) ?>...
                                </small>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block"><?= date('H:i', strtotime($conv['last_message_at'])) ?></small>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <span class="badge bg-success rounded-pill"><?= $conv['unread_count'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Chat Area -->
        <div class="chat-area">
            <?php if ($selectedPhone): ?>
                <!-- Chat Header -->
                <div class="chat-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            <i class="bi bi-person-circle me-2"></i>
                            <?= htmlspecialchars($customerInfo['name'] ?? $selectedPhone) ?>
                        </h5>
                        <small><?= htmlspecialchars($selectedPhone) ?></small>
                    </div>
                    <div>
                        <?php if (isset($activeBooking)): ?>
                            <span class="badge bg-light text-dark">
                                <i class="bi bi-door-open me-1"></i>Room <?= $activeBooking['room_number'] ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Customer Info (if available) -->
                <?php if ($customerInfo || isset($activeBooking)): ?>
                    <div class="p-3 bg-light border-bottom">
                        <div class="row g-2">
                            <?php if ($customerInfo): ?>
                                <div class="col-auto">
                                    <small class="text-muted">Customer:</small>
                                    <strong><?= htmlspecialchars($customerInfo['name']) ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($activeBooking)): ?>
                                <div class="col-auto">
                                    <small class="text-muted">Booking:</small>
                                    <strong><?= $activeBooking['booking_number'] ?></strong>
                                </div>
                                <div class="col-auto">
                                    <small class="text-muted">Status:</small>
                                    <span class="badge bg-<?= $activeBooking['status'] === 'checked_in' ? 'success' : 'primary' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $activeBooking['status'])) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Messages -->
                <div class="chat-messages" id="chatMessages">
                    <?php foreach ($messages as $msg): ?>
                        <div class="message-bubble message-<?= $msg['message_type'] ?>">
                            <div><?= nl2br(htmlspecialchars($msg['message_text'])) ?></div>
                            <div class="message-time">
                                <?= date('M j, H:i', strtotime($msg['created_at'])) ?>
                                <?php if ($msg['message_type'] === 'outbound'): ?>
                                    <i class="bi bi-check2-all text-primary ms-1"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Quick Replies -->
                <div class="px-3 py-2 bg-white border-top">
                    <small class="text-muted">Quick replies:</small>
                    <button type="button" class="btn btn-outline-secondary btn-sm quick-reply-btn" onclick="setReply('Hello! How can I help you today?')">Greeting</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm quick-reply-btn" onclick="setReply('Your order is being prepared and will be ready shortly.')">Order Preparing</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm quick-reply-btn" onclick="setReply('Your order is ready for pickup!')">Order Ready</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm quick-reply-btn" onclick="setReply('Thank you for your message. Our team will assist you shortly.')">Will Assist</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm quick-reply-btn" onclick="setReply('Thank you for staying with us! We hope to see you again soon.')">Thank You</button>
                </div>
                
                <!-- Input Area -->
                <div class="chat-input">
                    <form method="POST" class="d-flex gap-2">
                        <input type="hidden" name="action" value="send_reply">
                        <input type="hidden" name="phone" value="<?= htmlspecialchars($selectedPhone) ?>">
                        <input type="text" name="message" id="messageInput" class="form-control" placeholder="Type a message..." required autofocus>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="no-conversation">
                    <div class="text-center">
                        <i class="bi bi-chat-square-text fs-1 mb-3 d-block"></i>
                        <h5>Select a conversation</h5>
                        <p>Choose a conversation from the list to view messages</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Scroll to bottom of messages
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});

function setReply(text) {
    document.getElementById('messageInput').value = text;
    document.getElementById('messageInput').focus();
}

// Auto-refresh for new messages (every 30 seconds)
setInterval(function() {
    // Could implement AJAX refresh here
}, 30000);
</script>

<?php include 'includes/footer.php'; ?>
