<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

// Handle WhatsApp webhook setup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'save_whatsapp_config') {
            // Save WhatsApp Business API configuration
            $settings = [
                'whatsapp_business_phone' => sanitizeInput($_POST['business_phone']),
                'whatsapp_api_token' => sanitizeInput($_POST['api_token']),
                'whatsapp_webhook_url' => sanitizeInput($_POST['webhook_url']),
                'whatsapp_verify_token' => sanitizeInput($_POST['verify_token']),
                'whatsapp_enabled' => isset($_POST['whatsapp_enabled']) ? '1' : '0',
                'whatsapp_auto_replies' => isset($_POST['auto_replies']) ? '1' : '0',
                'whatsapp_order_notifications' => isset($_POST['order_notifications']) ? '1' : '0',
                'whatsapp_delivery_tracking' => isset($_POST['delivery_tracking']) ? '1' : '0'
            ];
            
            foreach ($settings as $key => $value) {
                $db->query("
                    INSERT INTO settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ", [$key, $value]);
            }
            
            $_SESSION['success_message'] = 'WhatsApp configuration saved successfully';
            redirect($_SERVER['PHP_SELF']);
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    }
}

// Get current WhatsApp settings
$whatsappSettings = [];
$settingsResult = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'whatsapp_%'");
foreach ($settingsResult as $setting) {
    $whatsappSettings[$setting['setting_key']] = $setting['setting_value'];
}

// Get WhatsApp order statistics
$whatsappStats = [
    'total_orders' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE order_source = 'whatsapp'")['count'] ?? 0,
    'orders_today' => $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE order_source = 'whatsapp' AND DATE(created_at) = CURDATE()")['count'] ?? 0,
    'messages_sent' => $db->fetchOne("SELECT COUNT(*) as count FROM whatsapp_messages WHERE message_type = 'outbound' AND DATE(created_at) = CURDATE()")['count'] ?? 0,
    'active_chats' => $db->fetchOne("SELECT COUNT(DISTINCT customer_phone) as count FROM whatsapp_messages WHERE DATE(created_at) = CURDATE()")['count'] ?? 0
];

$pageTitle = 'WhatsApp Integration';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5><i class="bi bi-whatsapp me-2"></i>WhatsApp Business Integration</h5>
                    <small>Integrate WhatsApp for orders, tracking, and customer communication</small>
                </div>
            </div>
        </div>
    </div>

    <!-- WhatsApp Statistics -->
    <div class="row g-3 mb-4 mt-2">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-cart-check text-success fs-1 mb-2"></i>
                    <h3 class="text-success"><?= $whatsappStats['total_orders'] ?></h3>
                    <p class="text-muted mb-0">Total WhatsApp Orders</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-calendar-day text-primary fs-1 mb-2"></i>
                    <h3 class="text-primary"><?= $whatsappStats['orders_today'] ?></h3>
                    <p class="text-muted mb-0">Orders Today</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-chat-dots text-info fs-1 mb-2"></i>
                    <h3 class="text-info"><?= $whatsappStats['messages_sent'] ?></h3>
                    <p class="text-muted mb-0">Messages Sent Today</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-people text-warning fs-1 mb-2"></i>
                    <h3 class="text-warning"><?= $whatsappStats['active_chats'] ?></h3>
                    <p class="text-muted mb-0">Active Chats Today</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Configuration Panel -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-gear"></i> WhatsApp Configuration</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_whatsapp_config">
                        
                        <div class="mb-3">
                            <label for="business_phone" class="form-label">Business Phone Number</label>
                            <input type="tel" class="form-control" id="business_phone" name="business_phone" 
                                   value="<?= htmlspecialchars($whatsappSettings['whatsapp_business_phone'] ?? '') ?>"
                                   placeholder="+254700000000">
                            <small class="text-muted">Your WhatsApp Business phone number</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="api_token" class="form-label">WhatsApp API Token</label>
                            <input type="password" class="form-control" id="api_token" name="api_token" 
                                   value="<?= htmlspecialchars($whatsappSettings['whatsapp_api_token'] ?? '') ?>"
                                   placeholder="Your WhatsApp Business API token">
                            <small class="text-muted">Get this from Facebook Developer Console</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="webhook_url" class="form-label">Webhook URL</label>
                            <input type="url" class="form-control" id="webhook_url" name="webhook_url" 
                                   value="<?= htmlspecialchars($whatsappSettings['whatsapp_webhook_url'] ?? 'https://yourdomain.com/wapos/api/whatsapp-webhook.php') ?>"
                                   readonly>
                            <small class="text-muted">Configure this URL in your WhatsApp Business settings</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="verify_token" class="form-label">Webhook Verify Token</label>
                            <input type="text" class="form-control" id="verify_token" name="verify_token" 
                                   value="<?= htmlspecialchars($whatsappSettings['whatsapp_verify_token'] ?? '') ?>"
                                   placeholder="webhook_verify_token_123">
                            <small class="text-muted">Token for webhook verification</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="whatsapp_enabled" name="whatsapp_enabled" 
                                       <?= ($whatsappSettings['whatsapp_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="whatsapp_enabled">
                                    Enable WhatsApp Integration
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="auto_replies" name="auto_replies" 
                                       <?= ($whatsappSettings['whatsapp_auto_replies'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="auto_replies">
                                    Enable Auto-replies
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="order_notifications" name="order_notifications" 
                                       <?= ($whatsappSettings['whatsapp_order_notifications'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="order_notifications">
                                    Send Order Notifications
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="delivery_tracking" name="delivery_tracking" 
                                       <?= ($whatsappSettings['whatsapp_delivery_tracking'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="delivery_tracking">
                                    Enable Delivery Tracking via WhatsApp
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-2"></i>Save Configuration
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Features Overview -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-list-check"></i> WhatsApp Features</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <h6 class="text-success">📱 Order Management</h6>
                            <ul class="list-unstyled mb-3">
                                <li>✅ Receive orders via WhatsApp messages</li>
                                <li>✅ Menu sharing with images and prices</li>
                                <li>✅ Order confirmation and receipts</li>
                                <li>✅ Payment link generation</li>
                            </ul>
                            
                            <h6 class="text-info">🚚 Delivery Tracking</h6>
                            <ul class="list-unstyled mb-3">
                                <li>✅ Real-time delivery status updates</li>
                                <li>✅ Live location sharing</li>
                                <li>✅ ETA notifications</li>
                                <li>✅ Delivery confirmation with photos</li>
                            </ul>
                            
                            <h6 class="text-warning">🤖 Automated Responses</h6>
                            <ul class="list-unstyled mb-3">
                                <li>✅ Welcome messages for new customers</li>
                                <li>✅ Menu requests and catalog sharing</li>
                                <li>✅ Order status inquiries</li>
                                <li>✅ FAQ responses</li>
                            </ul>
                            
                            <h6 class="text-danger">📊 Analytics & Reports</h6>
                            <ul class="list-unstyled">
                                <li>✅ WhatsApp order analytics</li>
                                <li>✅ Customer engagement metrics</li>
                                <li>✅ Message delivery reports</li>
                                <li>✅ Conversion tracking</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-lightning"></i> Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <button class="btn btn-outline-success w-100 mb-2" onclick="testWhatsAppConnection()">
                                <i class="bi bi-wifi me-2"></i>Test Connection
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-primary w-100 mb-2" onclick="sendTestMessage()">
                                <i class="bi bi-chat-dots me-2"></i>Send Test Message
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-info w-100 mb-2" onclick="shareMenu()">
                                <i class="bi bi-menu-button-wide me-2"></i>Share Menu
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-warning w-100 mb-2" onclick="viewAnalytics()">
                                <i class="bi bi-graph-up me-2"></i>View Analytics
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Setup Instructions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-info-circle"></i> Setup Instructions</h6>
                </div>
                <div class="card-body">
                    <div class="accordion" id="setupAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                    Step 1: WhatsApp Business API Setup
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#setupAccordion">
                                <div class="accordion-body">
                                    <ol>
                                        <li>Create a <strong>Facebook Developer Account</strong> at developers.facebook.com</li>
                                        <li>Create a new app and add <strong>WhatsApp Business API</strong></li>
                                        <li>Get your <strong>Phone Number ID</strong> and <strong>Access Token</strong></li>
                                        <li>Configure webhook URL: <code>https://yourdomain.com/wapos/api/whatsapp-webhook.php</code></li>
                                        <li>Set webhook events: <code>messages</code>, <code>message_deliveries</code>, <code>message_reads</code></li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                    Step 2: Configure Webhook
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#setupAccordion">
                                <div class="accordion-body">
                                    <ol>
                                        <li>Copy the webhook URL from the configuration above</li>
                                        <li>In Facebook Developer Console, paste the webhook URL</li>
                                        <li>Enter the verify token you set in the configuration</li>
                                        <li>Subscribe to webhook events</li>
                                        <li>Test the webhook connection</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                    Step 3: Test Integration
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#setupAccordion">
                                <div class="accordion-body">
                                    <ol>
                                        <li>Use the "Test Connection" button above</li>
                                        <li>Send a test message to verify API connectivity</li>
                                        <li>Send a WhatsApp message to your business number</li>
                                        <li>Check if the webhook receives the message</li>
                                        <li>Verify auto-replies are working</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testWhatsAppConnection() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Testing...';
    btn.disabled = true;
    
    fetch('api/test-whatsapp-connection.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ WhatsApp connection successful!\n\n' + data.message);
            } else {
                alert('❌ Connection failed:\n\n' + data.message);
            }
        })
        .catch(error => {
            alert('❌ Error testing connection:\n\n' + error.message);
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

function sendTestMessage() {
    const phone = prompt('Enter phone number to send test message (with country code):');
    if (!phone) return;
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
    btn.disabled = true;
    
    fetch('api/send-whatsapp-message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            phone: phone,
            message: 'Hello! This is a test message from your WAPOS system. WhatsApp integration is working correctly! 🎉'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Test message sent successfully!');
        } else {
            alert('❌ Failed to send message:\n\n' + data.message);
        }
    })
    .catch(error => {
        alert('❌ Error sending message:\n\n' + error.message);
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function shareMenu() {
    window.open('whatsapp-menu-catalog.php', '_blank');
}

function viewAnalytics() {
    window.open('whatsapp-analytics.php', '_blank');
}
</script>

<?php include 'includes/footer.php'; ?>
