<?php
/**
 * Notification Settings
 * Configure email, SMS, and WhatsApp providers
 */

require_once 'includes/bootstrap.php';
$auth->requireLogin();

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'super_admin', 'developer'])) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    $settingsToSave = [
        // SMTP Settings
        'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 
        'smtp_from_email', 'smtp_from_name', 'smtp_encryption',
        // SMS Settings
        'sms_provider', 'sms_api_key', 'sms_api_secret', 'sms_sender_id',
        // WhatsApp Settings
        'whatsapp_access_token', 'whatsapp_phone_number_id', 'whatsapp_business_account_id',
        // General Settings
        'default_country_code', 'notification_admin_email',
        'auto_send_receipts', 'auto_birthday_wishes', 'auto_daily_summary'
    ];

    foreach ($settingsToSave as $key) {
        $value = $_POST[$key] ?? '';
        
        // Don't overwrite password if empty (keep existing)
        if (in_array($key, ['smtp_password', 'sms_api_secret', 'whatsapp_access_token']) && empty($value)) {
            continue;
        }
        
        $db->getConnection()->prepare("
            INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ")->execute([$key, $value]);
    }

    $_SESSION['success_message'] = 'Notification settings saved successfully!';
    header('Location: notification-settings.php');
    exit;
}

// Load current settings
$settings = [];
$rows = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%' OR setting_key LIKE 'sms_%' OR setting_key LIKE 'whatsapp_%' OR setting_key LIKE 'notification_%' OR setting_key LIKE 'auto_%' OR setting_key = 'default_country_code'");
foreach ($rows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$pageTitle = 'Notification Settings';
require_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1">
                <i class="bi bi-gear text-primary me-2"></i>Notification Settings
            </h1>
            <p class="text-muted mb-0">Configure email, SMS, and WhatsApp providers</p>
        </div>
        <div>
            <a href="notifications.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Notifications
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

        <div class="row g-4">
            <!-- Email/SMTP Settings -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-envelope me-2 text-primary"></i>Email (SMTP) Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">SMTP Host</label>
                                <input type="text" class="form-control" name="smtp_host" 
                                       value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>"
                                       placeholder="smtp.gmail.com">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Port</label>
                                <input type="number" class="form-control" name="smtp_port" 
                                       value="<?= htmlspecialchars($settings['smtp_port'] ?? '587') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="smtp_username" 
                                       value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>"
                                       placeholder="your-email@gmail.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="smtp_password" 
                                       placeholder="<?= !empty($settings['smtp_password']) ? '••••••••' : 'Enter password' ?>">
                                <small class="text-muted">Leave blank to keep existing</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">From Email</label>
                                <input type="email" class="form-control" name="smtp_from_email" 
                                       value="<?= htmlspecialchars($settings['smtp_from_email'] ?? '') ?>"
                                       placeholder="noreply@yourbusiness.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">From Name</label>
                                <input type="text" class="form-control" name="smtp_from_name" 
                                       value="<?= htmlspecialchars($settings['smtp_from_name'] ?? '') ?>"
                                       placeholder="Your Business Name">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Encryption</label>
                                <select class="form-select" name="smtp_encryption">
                                    <option value="tls" <?= ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (Recommended)</option>
                                    <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="none" <?= ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="testEmail()">
                                    <i class="bi bi-send me-1"></i>Send Test Email
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SMS Settings -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-chat-dots me-2 text-success"></i>SMS Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">SMS Provider</label>
                                <select class="form-select" name="sms_provider" id="smsProvider">
                                    <option value="" disabled>-- East Africa --</option>
                                    <option value="egosms" <?= ($settings['sms_provider'] ?? '') === 'egosms' ? 'selected' : '' ?>>EgoSMS (Uganda)</option>
                                    <option value="leopard" <?= ($settings['sms_provider'] ?? '') === 'leopard' ? 'selected' : '' ?>>SMSLeopard (Kenya)</option>
                                    <option value="africastalking" <?= ($settings['sms_provider'] ?? '') === 'africastalking' ? 'selected' : '' ?>>Africa's Talking (Pan-Africa)</option>
                                    <option value="" disabled>-- International --</option>
                                    <option value="twilio" <?= ($settings['sms_provider'] ?? '') === 'twilio' ? 'selected' : '' ?>>Twilio (Global)</option>
                                    <option value="vonage" <?= ($settings['sms_provider'] ?? '') === 'vonage' ? 'selected' : '' ?>>Vonage/Nexmo (Global)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" id="apiKeyLabel">API Key / Account SID</label>
                                <input type="text" class="form-control" name="sms_api_key" 
                                       value="<?= htmlspecialchars($settings['sms_api_key'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" id="apiSecretLabel">API Secret / Auth Token</label>
                                <input type="password" class="form-control" name="sms_api_secret" 
                                       placeholder="<?= !empty($settings['sms_api_secret']) ? '••••••••' : 'Enter secret' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sender ID / From Number</label>
                                <input type="text" class="form-control" name="sms_sender_id" 
                                       value="<?= htmlspecialchars($settings['sms_sender_id'] ?? '') ?>"
                                       placeholder="WAPOS or +1234567890">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Default Country Code</label>
                                <input type="text" class="form-control" name="default_country_code" 
                                       value="<?= htmlspecialchars($settings['default_country_code'] ?? '254') ?>"
                                       placeholder="254">
                                <small class="text-muted">Without + (e.g., 254 for Kenya)</small>
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="testSMS()">
                                    <i class="bi bi-send me-1"></i>Send Test SMS
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WhatsApp Settings -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-whatsapp me-2" style="color:#25d366"></i>WhatsApp Business API</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Access Token</label>
                                <input type="password" class="form-control" name="whatsapp_access_token" 
                                       placeholder="<?= !empty($settings['whatsapp_access_token']) ? '••••••••' : 'Enter access token' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number ID</label>
                                <input type="text" class="form-control" name="whatsapp_phone_number_id" 
                                       value="<?= htmlspecialchars($settings['whatsapp_phone_number_id'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Business Account ID</label>
                                <input type="text" class="form-control" name="whatsapp_business_account_id" 
                                       value="<?= htmlspecialchars($settings['whatsapp_business_account_id'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <div class="alert alert-info mb-0">
                                    <i class="bi bi-info-circle me-2"></i>
                                    WhatsApp Business API requires a Meta Business account. 
                                    <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started" target="_blank">Learn more</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Automation Settings -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-robot me-2 text-info"></i>Automation Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Admin Email (for alerts)</label>
                                <input type="email" class="form-control" name="notification_admin_email" 
                                       value="<?= htmlspecialchars($settings['notification_admin_email'] ?? '') ?>"
                                       placeholder="admin@yourbusiness.com">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_send_receipts" value="1"
                                           id="autoReceipts" <?= ($settings['auto_send_receipts'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="autoReceipts">
                                        Auto-send receipts to customers after purchase
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_birthday_wishes" value="1"
                                           id="autoBirthday" <?= ($settings['auto_birthday_wishes'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="autoBirthday">
                                        Auto-send birthday wishes to customers
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_daily_summary" value="1"
                                           id="autoDailySummary" <?= ($settings['auto_daily_summary'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="autoDailySummary">
                                        Auto-send daily sales summary to admin
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-lg me-2"></i>Save Settings
            </button>
        </div>
    </form>
</div>

<script>
function testEmail() {
    const email = prompt('Enter email address to send test:');
    if (!email) return;
    
    fetch('api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'send',
            channel: 'email',
            recipient_type: 'single',
            recipient: email,
            subject: 'Test Email from WAPOS',
            message: '<h2>Test Email</h2><p>This is a test email from your WAPOS notification system. If you received this, your email configuration is working correctly!</p>',
            csrf_token: '<?= generateCSRFToken() ?>'
        })
    })
    .then(r => r.json())
    .then(result => alert(result.success ? 'Test email sent!' : 'Error: ' + result.message));
}

function testSMS() {
    const phone = prompt('Enter phone number to send test SMS:');
    if (!phone) return;
    
    fetch('api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'send',
            channel: 'sms',
            recipient_type: 'single',
            recipient: phone,
            message: 'Test SMS from WAPOS. Your SMS configuration is working!',
            csrf_token: '<?= generateCSRFToken() ?>'
        })
    })
    .then(r => r.json())
    .then(result => alert(result.success ? 'Test SMS sent!' : 'Error: ' + result.message));
}

// Update labels based on provider
document.getElementById('smsProvider').addEventListener('change', function() {
    const provider = this.value;
    const keyLabel = document.getElementById('apiKeyLabel');
    const secretLabel = document.getElementById('apiSecretLabel');
    
    switch (provider) {
        case 'twilio':
            keyLabel.textContent = 'Account SID';
            secretLabel.textContent = 'Auth Token';
            break;
        case 'africastalking':
            keyLabel.textContent = 'API Key';
            secretLabel.textContent = 'Username';
            break;
        case 'egosms':
            keyLabel.textContent = 'Username';
            secretLabel.textContent = 'Password';
            break;
        case 'leopard':
            keyLabel.textContent = 'API Key';
            secretLabel.textContent = 'API Secret';
            break;
        default:
            keyLabel.textContent = 'API Key';
            secretLabel.textContent = 'API Secret';
    }
});

// Trigger on page load
document.getElementById('smsProvider').dispatchEvent(new Event('change'));
</script>

<?php require_once 'includes/footer.php'; ?>
