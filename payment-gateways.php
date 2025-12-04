<?php
/**
 * WAPOS - Payment Gateway Settings
 * Easy integration for Relworx and PesaPal payment providers
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

require_once 'includes/bootstrap.php';

// Require super_admin or developer access
$auth->requireLogin();
$userRole = $auth->getRole();
if (!in_array($userRole, ['super_admin', 'developer'], true)) {
    header('Location: ' . APP_URL . '/access-denied.php');
    exit;
}

$db = Database::getInstance();
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request. Please try again.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'save_gateway') {
            $provider = $_POST['provider'] ?? '';
            
            $settings = [
                'payments_gateway_provider' => $provider,
                'payments_gateway_enabled' => isset($_POST['enabled']) ? '1' : '0',
            ];
            
            if ($provider === 'mpesa') {
                $settings['mpesa_consumer_key'] = trim($_POST['mpesa_consumer_key'] ?? '');
                $settings['mpesa_consumer_secret'] = trim($_POST['mpesa_consumer_secret'] ?? '');
                $settings['mpesa_passkey'] = trim($_POST['mpesa_passkey'] ?? '');
                $settings['mpesa_shortcode'] = trim($_POST['mpesa_shortcode'] ?? '');
                $settings['mpesa_shortcode_type'] = $_POST['mpesa_shortcode_type'] ?? 'paybill';
                $settings['mpesa_environment'] = $_POST['mpesa_environment'] ?? 'sandbox';
                $settings['mpesa_callback_url'] = trim($_POST['mpesa_callback_url'] ?? '');
                // B2C optional
                $settings['mpesa_b2c_shortcode'] = trim($_POST['mpesa_b2c_shortcode'] ?? '');
                $settings['mpesa_initiator_name'] = trim($_POST['mpesa_initiator_name'] ?? '');
                $settings['mpesa_security_credential'] = trim($_POST['mpesa_security_credential'] ?? '');
            } elseif ($provider === 'relworx') {
                $settings['relworx_api_key'] = trim($_POST['relworx_api_key'] ?? '');
                $settings['relworx_api_secret'] = trim($_POST['relworx_api_secret'] ?? '');
                $settings['relworx_merchant_id'] = trim($_POST['relworx_merchant_id'] ?? '');
                $settings['relworx_account_number'] = trim($_POST['relworx_account_number'] ?? '');
                $settings['relworx_environment'] = $_POST['relworx_environment'] ?? 'sandbox';
                $settings['relworx_callback_url'] = trim($_POST['relworx_callback_url'] ?? '');
                $settings['relworx_default_channel'] = $_POST['relworx_default_channel'] ?? 'mpesa_stk';
                $settings['relworx_default_country'] = $_POST['relworx_default_country'] ?? 'KE';
                $settings['relworx_default_currency'] = $_POST['relworx_default_currency'] ?? 'KES';
            } elseif ($provider === 'pesapal') {
                $settings['pesapal_consumer_key'] = trim($_POST['pesapal_consumer_key'] ?? '');
                $settings['pesapal_consumer_secret'] = trim($_POST['pesapal_consumer_secret'] ?? '');
                $settings['pesapal_environment'] = $_POST['pesapal_environment'] ?? 'sandbox';
                $settings['pesapal_ipn_url'] = trim($_POST['pesapal_ipn_url'] ?? '');
                $settings['pesapal_callback_url'] = trim($_POST['pesapal_callback_url'] ?? '');
            }
            
            // Save all settings
            foreach ($settings as $key => $value) {
                SettingsStore::persist($key, $value);
            }
            
            $message = 'Payment gateway settings saved successfully!';
            $messageType = 'success';
        }
        
        if ($action === 'test_connection') {
            $provider = $_POST['test_provider'] ?? '';
            
            if ($provider === 'mpesa') {
                $result = testMpesaConnection();
            } elseif ($provider === 'relworx') {
                $result = testRelworxConnection();
            } elseif ($provider === 'pesapal') {
                $result = testPesapalConnection();
            } else {
                $result = ['success' => false, 'message' => 'Invalid provider'];
            }
            
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
        }
    }
}

// Get current settings
$currentProvider = settings('payments_gateway_provider', '');
$gatewayEnabled = settings('payments_gateway_enabled', '0') === '1';

// M-Pesa Daraja settings (Direct Safaricom integration)
$mpesaSettings = [
    'consumer_key' => settings('mpesa_consumer_key', ''),
    'consumer_secret' => settings('mpesa_consumer_secret', ''),
    'passkey' => settings('mpesa_passkey', ''),
    'shortcode' => settings('mpesa_shortcode', ''),
    'shortcode_type' => settings('mpesa_shortcode_type', 'paybill'), // paybill or till
    'environment' => settings('mpesa_environment', 'sandbox'),
    'callback_url' => settings('mpesa_callback_url', APP_URL . '/api/payments/mpesa-callback.php'),
    // B2C settings (optional)
    'b2c_shortcode' => settings('mpesa_b2c_shortcode', ''),
    'initiator_name' => settings('mpesa_initiator_name', ''),
    'security_credential' => settings('mpesa_security_credential', ''),
];

// Relworx settings (Aggregator for multiple providers)
$relworxSettings = [
    'api_key' => settings('relworx_api_key', ''),
    'api_secret' => settings('relworx_api_secret', ''),
    'merchant_id' => settings('relworx_merchant_id', ''),
    'account_number' => settings('relworx_account_number', ''),
    'environment' => settings('relworx_environment', 'sandbox'),
    'callback_url' => settings('relworx_callback_url', APP_URL . '/api/payments/relworx-callback.php'),
    'default_channel' => settings('relworx_default_channel', 'mpesa_stk'),
    'default_country' => settings('relworx_default_country', 'KE'),
    'default_currency' => settings('relworx_default_currency', 'KES'),
];

// PesaPal settings
$pesapalSettings = [
    'consumer_key' => settings('pesapal_consumer_key', ''),
    'consumer_secret' => settings('pesapal_consumer_secret', ''),
    'environment' => settings('pesapal_environment', 'sandbox'),
    'ipn_url' => settings('pesapal_ipn_url', APP_URL . '/api/payments/pesapal-ipn.php'),
    'callback_url' => settings('pesapal_callback_url', APP_URL . '/api/payments/pesapal-callback.php'),
];

/**
 * Test M-Pesa Daraja API connection
 */
function testMpesaConnection() {
    $consumerKey = settings('mpesa_consumer_key', '');
    $consumerSecret = settings('mpesa_consumer_secret', '');
    $environment = settings('mpesa_environment', 'sandbox');
    
    if (empty($consumerKey) || empty($consumerSecret)) {
        return ['success' => false, 'message' => 'Consumer Key and Secret are required'];
    }
    
    $baseUrl = $environment === 'live' 
        ? 'https://api.safaricom.co.ke' 
        : 'https://sandbox.safaricom.co.ke';
    
    try {
        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $baseUrl . '/oauth/v1/generate?grant_type=client_credentials',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/json',
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (!empty($data['access_token'])) {
                return ['success' => true, 'message' => 'M-Pesa Daraja connection successful! Token expires in ' . ($data['expires_in'] ?? 3600) . ' seconds.'];
            }
        }
        
        $error = json_decode($response, true);
        $errorMsg = $error['errorMessage'] ?? $error['error_description'] ?? 'Unknown error';
        return ['success' => false, 'message' => 'M-Pesa connection failed: ' . $errorMsg];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Connection error: ' . $e->getMessage()];
    }
}

/**
 * Test Relworx API connection
 */
function testRelworxConnection() {
    $apiKey = settings('relworx_api_key', '');
    $apiSecret = settings('relworx_api_secret', '');
    $environment = settings('relworx_environment', 'sandbox');
    
    if (empty($apiKey) || empty($apiSecret)) {
        return ['success' => false, 'message' => 'API Key and Secret are required'];
    }
    
    $baseUrl = $environment === 'live' 
        ? 'https://api.relworx.com' 
        : 'https://sandbox.relworx.com';
    
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $baseUrl . '/v1/auth/test',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return ['success' => true, 'message' => 'Relworx connection successful! API credentials are valid.'];
        } else {
            return ['success' => false, 'message' => 'Relworx connection failed. HTTP Code: ' . $httpCode];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Connection error: ' . $e->getMessage()];
    }
}

/**
 * Test PesaPal API connection
 */
function testPesapalConnection() {
    $consumerKey = settings('pesapal_consumer_key', '');
    $consumerSecret = settings('pesapal_consumer_secret', '');
    $environment = settings('pesapal_environment', 'sandbox');
    
    if (empty($consumerKey) || empty($consumerSecret)) {
        return ['success' => false, 'message' => 'Consumer Key and Secret are required'];
    }
    
    $baseUrl = $environment === 'live' 
        ? 'https://pay.pesapal.com/v3' 
        : 'https://cybqa.pesapal.com/pesapalv3';
    
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $baseUrl . '/api/Auth/RequestToken',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'consumer_key' => $consumerKey,
                'consumer_secret' => $consumerSecret,
            ]),
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($httpCode === 200 && !empty($data['token'])) {
            return ['success' => true, 'message' => 'PesaPal connection successful! API credentials are valid.'];
        } else {
            $errorMsg = $data['error']['message'] ?? 'Unknown error';
            return ['success' => false, 'message' => 'PesaPal connection failed: ' . $errorMsg];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Connection error: ' . $e->getMessage()];
    }
}

$pageTitle = 'Payment Gateways';
include 'includes/header.php';
?>

<style>
    .gateway-card {
        background: var(--surface-card);
        border-radius: 12px;
        border: 2px solid var(--border-color);
        padding: 24px;
        margin-bottom: 24px;
        transition: all 0.2s;
    }
    .gateway-card.active {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    .gateway-header {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 20px;
    }
    .gateway-logo {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: 700;
    }
    .gateway-logo.relworx {
        background: linear-gradient(135deg, #00c853, #00e676);
        color: white;
    }
    .gateway-logo.pesapal {
        background: linear-gradient(135deg, #1976d2, #42a5f5);
        color: white;
    }
    .gateway-title h3 {
        margin: 0 0 4px;
        font-size: 1.25rem;
    }
    .gateway-title p {
        margin: 0;
        color: var(--text-muted);
        font-size: 0.9rem;
    }
    .env-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .env-badge.sandbox {
        background: #fff3cd;
        color: #856404;
    }
    .env-badge.live {
        background: #d4edda;
        color: #155724;
    }
    .form-section {
        background: var(--surface-muted);
        border-radius: 8px;
        padding: 20px;
        margin-top: 16px;
    }
    .form-section h5 {
        margin: 0 0 16px;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
    }
    .api-key-field {
        font-family: monospace;
        font-size: 0.9rem;
    }
    .help-text {
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-top: 4px;
    }
    .integration-steps {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin-top: 24px;
    }
    .integration-steps h5 {
        margin: 0 0 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .integration-steps ol {
        margin: 0;
        padding-left: 20px;
    }
    .integration-steps li {
        margin-bottom: 8px;
    }
    .integration-steps code {
        background: #e9ecef;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.85rem;
    }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-credit-card me-2"></i>Payment Gateways</h1>
            <p class="text-muted mb-0">Configure M-Pesa, Relworx, and PesaPal payment integrations</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
            <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- M-Pesa Daraja (Direct Safaricom) -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="gateway-card <?= $currentProvider === 'mpesa' ? 'active' : '' ?>">
                <div class="gateway-header">
                    <div class="gateway-logo" style="background: linear-gradient(135deg, #4CAF50, #8BC34A); color: white;">M</div>
                    <div class="gateway-title">
                        <h3>M-Pesa Daraja API</h3>
                        <p>Direct Safaricom Integration - Paybill, Till, Pochi La Biashara</p>
                    </div>
                    <?php if ($currentProvider === 'mpesa'): ?>
                        <span class="env-badge <?= $mpesaSettings['environment'] ?>"><?= $mpesaSettings['environment'] ?></span>
                    <?php endif; ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="save_gateway">
                    <input type="hidden" name="provider" value="mpesa">

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="mpesaEnabled" name="enabled" 
                               <?= $currentProvider === 'mpesa' && $gatewayEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="mpesaEnabled">Enable M-Pesa Direct Payments</label>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-section">
                                <h5><i class="bi bi-key me-2"></i>Daraja API Credentials</h5>
                                
                                <div class="mb-3">
                                    <label class="form-label">Consumer Key</label>
                                    <input type="text" name="mpesa_consumer_key" class="form-control api-key-field" 
                                           value="<?= htmlspecialchars($mpesaSettings['consumer_key']) ?>" 
                                           placeholder="From Safaricom Developer Portal">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Consumer Secret</label>
                                    <input type="password" name="mpesa_consumer_secret" class="form-control api-key-field" 
                                           value="<?= htmlspecialchars($mpesaSettings['consumer_secret']) ?>" 
                                           placeholder="From Safaricom Developer Portal">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Passkey (Lipa Na M-Pesa)</label>
                                    <input type="password" name="mpesa_passkey" class="form-control api-key-field" 
                                           value="<?= htmlspecialchars($mpesaSettings['passkey']) ?>" 
                                           placeholder="STK Push passkey">
                                </div>

                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label class="form-label">Shortcode</label>
                                        <input type="text" name="mpesa_shortcode" class="form-control" 
                                               value="<?= htmlspecialchars($mpesaSettings['shortcode']) ?>" 
                                               placeholder="Paybill or Till">
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label">Shortcode Type</label>
                                        <select name="mpesa_shortcode_type" class="form-select">
                                            <option value="paybill" <?= $mpesaSettings['shortcode_type'] === 'paybill' ? 'selected' : '' ?>>Paybill</option>
                                            <option value="till" <?= $mpesaSettings['shortcode_type'] === 'till' ? 'selected' : '' ?>>Till (Buy Goods)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Environment</label>
                                    <select name="mpesa_environment" class="form-select">
                                        <option value="sandbox" <?= $mpesaSettings['environment'] === 'sandbox' ? 'selected' : '' ?>>Sandbox (Testing)</option>
                                        <option value="live" <?= $mpesaSettings['environment'] === 'live' ? 'selected' : '' ?>>Live (Production)</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Callback URL</label>
                                    <input type="text" name="mpesa_callback_url" class="form-control api-key-field" 
                                           value="<?= htmlspecialchars($mpesaSettings['callback_url']) ?>">
                                    <div class="help-text">M-Pesa will send payment confirmations here</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-section">
                                <h5><i class="bi bi-arrow-left-right me-2"></i>B2C Settings (Optional)</h5>
                                <p class="text-muted small">For sending money to customers (refunds, disbursements)</p>
                                
                                <div class="mb-3">
                                    <label class="form-label">B2C Shortcode</label>
                                    <input type="text" name="mpesa_b2c_shortcode" class="form-control" 
                                           value="<?= htmlspecialchars($mpesaSettings['b2c_shortcode']) ?>" 
                                           placeholder="B2C Business Shortcode">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Initiator Name</label>
                                    <input type="text" name="mpesa_initiator_name" class="form-control" 
                                           value="<?= htmlspecialchars($mpesaSettings['initiator_name']) ?>" 
                                           placeholder="API Initiator username">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Security Credential</label>
                                    <input type="password" name="mpesa_security_credential" class="form-control api-key-field" 
                                           value="<?= htmlspecialchars($mpesaSettings['security_credential']) ?>" 
                                           placeholder="Encrypted credential">
                                    <div class="help-text">Generate from Daraja portal using your certificate</div>
                                </div>
                            </div>

                            <div class="integration-steps mt-3">
                                <h5><i class="bi bi-info-circle text-success"></i> Setup Instructions</h5>
                                <ol>
                                    <li>Register at <a href="https://developer.safaricom.co.ke" target="_blank">developer.safaricom.co.ke</a></li>
                                    <li>Create an app and get Consumer Key/Secret</li>
                                    <li>Go Live: Submit business documents for production access</li>
                                    <li>Set Callback: <code><?= APP_URL ?>/api/payments/mpesa-callback.php</code></li>
                                </ol>
                                <div class="mt-2 p-2 bg-light rounded small">
                                    <strong>Supports:</strong> STK Push, C2B (Paybill/Till), B2C Disbursements
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg me-1"></i> Save M-Pesa Settings
                        </button>
                        <button type="submit" name="action" value="test_connection" class="btn btn-outline-secondary">
                            <input type="hidden" name="test_provider" value="mpesa">
                            <i class="bi bi-wifi me-1"></i> Test Connection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Relworx -->
        <div class="col-lg-6">
            <div class="gateway-card <?= $currentProvider === 'relworx' ? 'active' : '' ?>">
                <div class="gateway-header">
                    <div class="gateway-logo relworx">RW</div>
                    <div class="gateway-title">
                        <h3>Relworx</h3>
                        <p>Airtel Money, MTN MoMo & Card Payments</p>
                    </div>
                    <?php if ($currentProvider === 'relworx'): ?>
                        <span class="env-badge <?= $relworxSettings['environment'] ?>"><?= $relworxSettings['environment'] ?></span>
                    <?php endif; ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="save_gateway">
                    <input type="hidden" name="provider" value="relworx">

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="relworxEnabled" name="enabled" 
                               <?= $currentProvider === 'relworx' && $gatewayEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="relworxEnabled">Enable Relworx Payments</label>
                    </div>

                    <div class="form-section">
                        <h5><i class="bi bi-key me-2"></i>API Credentials</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">API Key</label>
                            <input type="text" name="relworx_api_key" class="form-control api-key-field" 
                                   value="<?= htmlspecialchars($relworxSettings['api_key']) ?>" 
                                   placeholder="Enter your Relworx API Key">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">API Secret</label>
                            <input type="password" name="relworx_api_secret" class="form-control api-key-field" 
                                   value="<?= htmlspecialchars($relworxSettings['api_secret']) ?>" 
                                   placeholder="Enter your Relworx API Secret">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Merchant ID</label>
                                <input type="text" name="relworx_merchant_id" class="form-control" 
                                       value="<?= htmlspecialchars($relworxSettings['merchant_id']) ?>" 
                                       placeholder="Your Merchant ID">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Number</label>
                                <input type="text" name="relworx_account_number" class="form-control" 
                                       value="<?= htmlspecialchars($relworxSettings['account_number']) ?>" 
                                       placeholder="Your Account Number">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Environment</label>
                                <select name="relworx_environment" class="form-select">
                                    <option value="sandbox" <?= $relworxSettings['environment'] === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                                    <option value="live" <?= $relworxSettings['environment'] === 'live' ? 'selected' : '' ?>>Live</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Default Country</label>
                                <select name="relworx_default_country" class="form-select">
                                    <option value="KE" <?= $relworxSettings['default_country'] === 'KE' ? 'selected' : '' ?>>Kenya</option>
                                    <option value="UG" <?= $relworxSettings['default_country'] === 'UG' ? 'selected' : '' ?>>Uganda</option>
                                    <option value="RW" <?= $relworxSettings['default_country'] === 'RW' ? 'selected' : '' ?>>Rwanda</option>
                                    <option value="TZ" <?= $relworxSettings['default_country'] === 'TZ' ? 'selected' : '' ?>>Tanzania</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Default Currency</label>
                                <select name="relworx_default_currency" class="form-select">
                                    <option value="KES" <?= $relworxSettings['default_currency'] === 'KES' ? 'selected' : '' ?>>KES</option>
                                    <option value="UGX" <?= $relworxSettings['default_currency'] === 'UGX' ? 'selected' : '' ?>>UGX</option>
                                    <option value="RWF" <?= $relworxSettings['default_currency'] === 'RWF' ? 'selected' : '' ?>>RWF</option>
                                    <option value="TZS" <?= $relworxSettings['default_currency'] === 'TZS' ? 'selected' : '' ?>>TZS</option>
                                    <option value="USD" <?= $relworxSettings['default_currency'] === 'USD' ? 'selected' : '' ?>>USD</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Default Payment Channel</label>
                            <select name="relworx_default_channel" class="form-select">
                                <optgroup label="Airtel Money">
                                    <option value="airtel_ke" <?= $relworxSettings['default_channel'] === 'airtel_ke' ? 'selected' : '' ?>>Airtel Kenya</option>
                                    <option value="airtel_ug" <?= $relworxSettings['default_channel'] === 'airtel_ug' ? 'selected' : '' ?>>Airtel Uganda</option>
                                    <option value="airtel_rw" <?= $relworxSettings['default_channel'] === 'airtel_rw' ? 'selected' : '' ?>>Airtel Rwanda</option>
                                    <option value="airtel_tz" <?= $relworxSettings['default_channel'] === 'airtel_tz' ? 'selected' : '' ?>>Airtel Tanzania</option>
                                </optgroup>
                                <optgroup label="MTN Mobile Money">
                                    <option value="mtn_ug" <?= $relworxSettings['default_channel'] === 'mtn_ug' ? 'selected' : '' ?>>MTN Uganda</option>
                                    <option value="mtn_rw" <?= $relworxSettings['default_channel'] === 'mtn_rw' ? 'selected' : '' ?>>MTN Rwanda</option>
                                </optgroup>
                                <optgroup label="Card Payments">
                                    <option value="card" <?= $relworxSettings['default_channel'] === 'card' ? 'selected' : '' ?>>Visa / Mastercard</option>
                                </optgroup>
                            </select>
                            <div class="help-text">For M-Pesa, use the direct Daraja integration above</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Callback URL</label>
                            <input type="text" name="relworx_callback_url" class="form-control api-key-field" 
                                   value="<?= htmlspecialchars($relworxSettings['callback_url']) ?>">
                            <div class="help-text">Relworx will send payment notifications to this URL</div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Save Relworx Settings
                        </button>
                        <button type="submit" name="action" value="test_connection" class="btn btn-outline-secondary">
                            <input type="hidden" name="test_provider" value="relworx">
                            <i class="bi bi-wifi me-1"></i> Test Connection
                        </button>
                    </div>
                </form>

                <div class="integration-steps">
                    <h5><i class="bi bi-info-circle text-primary"></i> Setup Instructions</h5>
                    <ol>
                        <li>Sign up at <a href="https://relworx.com" target="_blank">relworx.com</a></li>
                        <li>Go to Dashboard → API Keys and copy credentials</li>
                        <li>Set Callback URL: <code><?= APP_URL ?>/api/payments/relworx-callback.php</code></li>
                        <li>Test in Sandbox first, then switch to Live</li>
                    </ol>
                    <div class="mt-3 p-2 bg-light rounded small">
                        <strong><i class="bi bi-check2-circle text-success"></i> Supported:</strong>
                        Airtel Money (KE, UG, RW, TZ), MTN MoMo (UG, RW), Card Payments
                    </div>
                </div>
            </div>
        </div>

        <!-- PesaPal -->
        <div class="col-lg-6">
            <div class="gateway-card <?= $currentProvider === 'pesapal' ? 'active' : '' ?>">
                <div class="gateway-header">
                    <div class="gateway-logo pesapal">PP</div>
                    <div class="gateway-title">
                        <h3>PesaPal</h3>
                        <p>M-Pesa, Cards, Bank & Mobile Money</p>
                    </div>
                    <?php if ($currentProvider === 'pesapal'): ?>
                        <span class="env-badge <?= $pesapalSettings['environment'] ?>"><?= $pesapalSettings['environment'] ?></span>
                    <?php endif; ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="save_gateway">
                    <input type="hidden" name="provider" value="pesapal">

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="pesapalEnabled" name="enabled"
                               <?= $currentProvider === 'pesapal' && $gatewayEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="pesapalEnabled">Enable PesaPal Payments</label>
                    </div>

                    <div class="form-section">
                        <h5><i class="bi bi-key me-2"></i>API Credentials</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Consumer Key</label>
                            <input type="text" name="pesapal_consumer_key" class="form-control api-key-field" 
                                   value="<?= htmlspecialchars($pesapalSettings['consumer_key']) ?>" 
                                   placeholder="Enter your PesaPal Consumer Key">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Consumer Secret</label>
                            <input type="password" name="pesapal_consumer_secret" class="form-control api-key-field" 
                                   value="<?= htmlspecialchars($pesapalSettings['consumer_secret']) ?>" 
                                   placeholder="Enter your PesaPal Consumer Secret">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Environment</label>
                            <select name="pesapal_environment" class="form-select">
                                <option value="sandbox" <?= $pesapalSettings['environment'] === 'sandbox' ? 'selected' : '' ?>>Sandbox (Testing)</option>
                                <option value="live" <?= $pesapalSettings['environment'] === 'live' ? 'selected' : '' ?>>Live (Production)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">IPN (Notification) URL</label>
                            <input type="text" name="pesapal_ipn_url" class="form-control api-key-field" 
                                   value="<?= htmlspecialchars($pesapalSettings['ipn_url']) ?>">
                            <div class="help-text">Instant Payment Notification URL</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Callback URL</label>
                            <input type="text" name="pesapal_callback_url" class="form-control api-key-field" 
                                   value="<?= htmlspecialchars($pesapalSettings['callback_url']) ?>">
                            <div class="help-text">User will be redirected here after payment</div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Save PesaPal Settings
                        </button>
                        <button type="submit" name="action" value="test_connection" class="btn btn-outline-secondary">
                            <input type="hidden" name="test_provider" value="pesapal">
                            <i class="bi bi-wifi me-1"></i> Test Connection
                        </button>
                    </div>
                </form>

                <div class="integration-steps">
                    <h5><i class="bi bi-info-circle text-primary"></i> Setup Instructions</h5>
                    <ol>
                        <li>Sign up at <a href="https://www.pesapal.com" target="_blank">pesapal.com</a></li>
                        <li>Go to Dashboard → API Credentials</li>
                        <li>Copy Consumer Key and Consumer Secret</li>
                        <li>Register IPN URL: <code><?= APP_URL ?>/api/payments/pesapal-ipn.php</code></li>
                        <li>Test in Sandbox, then apply for Live credentials</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Supported Payment Methods -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Supported Payment Methods</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-muted mb-3">Relworx</h6>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-check-circle text-success me-2"></i>M-Pesa (Kenya)</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Airtel Money</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>MTN Mobile Money</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Visa / Mastercard</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Bank Transfer</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted mb-3">PesaPal</h6>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-check-circle text-success me-2"></i>M-Pesa (Kenya, Tanzania)</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Airtel Money</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Visa / Mastercard / Amex</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Bank Transfer</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Equity Bank</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
