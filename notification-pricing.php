<?php
/**
 * Notification Pricing Configuration
 * Set per-message costs for billing clients
 */

require_once 'includes/bootstrap.php';
$auth->requireLogin();

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['super_admin', 'developer'])) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();
$currencySymbol = CurrencyManager::getInstance()->getCurrencySymbol();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    $pricingSettings = [
        'sms_cost_per_message',
        'whatsapp_cost_per_message', 
        'email_cost_per_message',
        'sms_provider_cost',
        'whatsapp_provider_cost',
        'email_provider_cost',
        'billing_currency',
        'billing_enabled'
    ];
    
    foreach ($pricingSettings as $key) {
        $value = $_POST[$key] ?? '';
        $db->getConnection()->prepare("
            INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ")->execute([$key, $value]);
    }
    
    $_SESSION['success_message'] = 'Pricing settings saved successfully!';
    header('Location: notification-pricing.php');
    exit;
}

// Get current settings
$settings = [];
$settingsResult = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE '%cost%' OR setting_key LIKE 'billing%'");
foreach ($settingsResult as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default values
$smsCost = $settings['sms_cost_per_message'] ?? '0.50';
$whatsappCost = $settings['whatsapp_cost_per_message'] ?? '0.30';
$emailCost = $settings['email_cost_per_message'] ?? '0.05';
$smsProviderCost = $settings['sms_provider_cost'] ?? '0.30';
$whatsappProviderCost = $settings['whatsapp_provider_cost'] ?? '0.15';
$emailProviderCost = $settings['email_provider_cost'] ?? '0.01';
$billingEnabled = $settings['billing_enabled'] ?? '1';

// Calculate margins
$smsMargin = (float)$smsCost - (float)$smsProviderCost;
$whatsappMargin = (float)$whatsappCost - (float)$whatsappProviderCost;
$emailMargin = (float)$emailCost - (float)$emailProviderCost;

$pageTitle = 'Notification Pricing';
require_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h1 class="h4 mb-1">
                        <i class="bi bi-currency-dollar text-success me-2"></i>Notification Pricing
                    </h1>
                    <p class="text-muted mb-0">Configure per-message costs for client billing</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="notification-usage.php" class="btn btn-outline-primary">
                        <i class="bi bi-bar-chart me-1"></i>View Usage
                    </a>
                    <a href="notification-settings.php" class="btn btn-outline-secondary">
                        <i class="bi bi-gear me-1"></i>Provider Settings
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i><?= $_SESSION['success_message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
                <!-- Billing Toggle -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="billing_enabled" value="1" 
                                   id="billingEnabled" <?= $billingEnabled === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="billingEnabled">
                                <strong>Enable Notification Billing</strong>
                                <br><small class="text-muted">Track and charge clients for SMS, WhatsApp, and Email usage</small>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- SMS Pricing -->
                    <div class="col-md-4">
                        <div class="card h-100 border-success">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-chat-dots me-2"></i>SMS Pricing</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Your Cost (Provider)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?= $currencySymbol ?></span>
                                        <input type="number" step="0.01" min="0" class="form-control" 
                                               name="sms_provider_cost" value="<?= $smsProviderCost ?>"
                                               id="smsProviderCost" onchange="updateMargin('sms')">
                                    </div>
                                    <small class="text-muted">What you pay per SMS</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Client Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?= $currencySymbol ?></span>
                                        <input type="number" step="0.01" min="0" class="form-control" 
                                               name="sms_cost_per_message" value="<?= $smsCost ?>"
                                               id="smsCost" onchange="updateMargin('sms')">
                                    </div>
                                    <small class="text-muted">What you charge clients</small>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Your Margin:</span>
                                    <strong class="text-success fs-5" id="smsMargin"><?= $currencySymbol ?><?= number_format($smsMargin, 2) ?></strong>
                                </div>
                                <div class="text-muted small" id="smsMarginPercent">
                                    <?= $smsProviderCost > 0 ? number_format(($smsMargin / $smsProviderCost) * 100, 0) : 0 ?>% markup
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- WhatsApp Pricing -->
                    <div class="col-md-4">
                        <div class="card h-100" style="border-color: #25d366">
                            <div class="card-header text-white" style="background-color: #25d366">
                                <h5 class="mb-0"><i class="bi bi-whatsapp me-2"></i>WhatsApp Pricing</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Your Cost (Provider)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?= $currencySymbol ?></span>
                                        <input type="number" step="0.01" min="0" class="form-control" 
                                               name="whatsapp_provider_cost" value="<?= $whatsappProviderCost ?>"
                                               id="whatsappProviderCost" onchange="updateMargin('whatsapp')">
                                    </div>
                                    <small class="text-muted">What you pay per message</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Client Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?= $currencySymbol ?></span>
                                        <input type="number" step="0.01" min="0" class="form-control" 
                                               name="whatsapp_cost_per_message" value="<?= $whatsappCost ?>"
                                               id="whatsappCost" onchange="updateMargin('whatsapp')">
                                    </div>
                                    <small class="text-muted">What you charge clients</small>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Your Margin:</span>
                                    <strong class="fs-5" style="color: #25d366" id="whatsappMargin"><?= $currencySymbol ?><?= number_format($whatsappMargin, 2) ?></strong>
                                </div>
                                <div class="text-muted small" id="whatsappMarginPercent">
                                    <?= $whatsappProviderCost > 0 ? number_format(($whatsappMargin / $whatsappProviderCost) * 100, 0) : 0 ?>% markup
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Pricing -->
                    <div class="col-md-4">
                        <div class="card h-100 border-primary">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-envelope me-2"></i>Email Pricing</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Your Cost (Provider)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?= $currencySymbol ?></span>
                                        <input type="number" step="0.001" min="0" class="form-control" 
                                               name="email_provider_cost" value="<?= $emailProviderCost ?>"
                                               id="emailProviderCost" onchange="updateMargin('email')">
                                    </div>
                                    <small class="text-muted">What you pay per email</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Client Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?= $currencySymbol ?></span>
                                        <input type="number" step="0.001" min="0" class="form-control" 
                                               name="email_cost_per_message" value="<?= $emailCost ?>"
                                               id="emailCost" onchange="updateMargin('email')">
                                    </div>
                                    <small class="text-muted">What you charge clients</small>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Your Margin:</span>
                                    <strong class="text-primary fs-5" id="emailMargin"><?= $currencySymbol ?><?= number_format($emailMargin, 3) ?></strong>
                                </div>
                                <div class="text-muted small" id="emailMarginPercent">
                                    <?= $emailProviderCost > 0 ? number_format(($emailMargin / $emailProviderCost) * 100, 0) : 0 ?>% markup
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profit Calculator -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-calculator me-2"></i>Profit Calculator</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Expected Monthly SMS</label>
                                <input type="number" class="form-control" id="calcSms" value="1000" onchange="calculateProfit()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Expected Monthly WhatsApp</label>
                                <input type="number" class="form-control" id="calcWhatsapp" value="500" onchange="calculateProfit()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Expected Monthly Emails</label>
                                <input type="number" class="form-control" id="calcEmail" value="2000" onchange="calculateProfit()">
                            </div>
                            <div class="col-md-3">
                                <div class="bg-success text-white rounded p-3 text-center">
                                    <small>Estimated Monthly Profit</small>
                                    <div class="fs-4 fw-bold" id="monthlyProfit"><?= $currencySymbol ?>0.00</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pricing Tips -->
                <div class="card mt-4 border-info">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Pricing Tips</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h6><i class="bi bi-chat-dots text-success me-2"></i>SMS</h6>
                                <ul class="small mb-0">
                                    <li>EgoSMS (Uganda): ~UGX 25-35/SMS</li>
                                    <li>SMSLeopard (Kenya): ~KES 0.80-1.50/SMS</li>
                                    <li>Africa's Talking: ~$0.02-0.05/SMS</li>
                                    <li>Typical markup: 50-100%</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6><i class="bi bi-whatsapp me-2" style="color:#25d366"></i>WhatsApp</h6>
                                <ul class="small mb-0">
                                    <li>AiSensy: ~$0.005-0.02/message</li>
                                    <li>Meta Direct: ~$0.005-0.08/message</li>
                                    <li>Varies by conversation type</li>
                                    <li>Typical markup: 100-200%</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6><i class="bi bi-envelope text-primary me-2"></i>Email</h6>
                                <ul class="small mb-0">
                                    <li>SendGrid: ~$0.001/email</li>
                                    <li>Mailgun: ~$0.0008/email</li>
                                    <li>Self-hosted SMTP: Near free</li>
                                    <li>Typical markup: 200-500%</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="notification-usage.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-lg me-2"></i>Save Pricing
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const currencySymbol = '<?= $currencySymbol ?>';

function updateMargin(channel) {
    const providerCost = parseFloat(document.getElementById(channel + 'ProviderCost').value) || 0;
    const clientCost = parseFloat(document.getElementById(channel + 'Cost').value) || 0;
    const margin = clientCost - providerCost;
    const markup = providerCost > 0 ? ((margin / providerCost) * 100) : 0;
    
    document.getElementById(channel + 'Margin').textContent = currencySymbol + margin.toFixed(channel === 'email' ? 3 : 2);
    document.getElementById(channel + 'MarginPercent').textContent = markup.toFixed(0) + '% markup';
    
    calculateProfit();
}

function calculateProfit() {
    const smsCount = parseInt(document.getElementById('calcSms').value) || 0;
    const whatsappCount = parseInt(document.getElementById('calcWhatsapp').value) || 0;
    const emailCount = parseInt(document.getElementById('calcEmail').value) || 0;
    
    const smsMargin = (parseFloat(document.getElementById('smsCost').value) || 0) - 
                      (parseFloat(document.getElementById('smsProviderCost').value) || 0);
    const whatsappMargin = (parseFloat(document.getElementById('whatsappCost').value) || 0) - 
                           (parseFloat(document.getElementById('whatsappProviderCost').value) || 0);
    const emailMargin = (parseFloat(document.getElementById('emailCost').value) || 0) - 
                        (parseFloat(document.getElementById('emailProviderCost').value) || 0);
    
    const profit = (smsCount * smsMargin) + (whatsappCount * whatsappMargin) + (emailCount * emailMargin);
    document.getElementById('monthlyProfit').textContent = currencySymbol + profit.toFixed(2);
}

// Initial calculation
calculateProfit();
</script>

<?php require_once 'includes/footer.php'; ?>
