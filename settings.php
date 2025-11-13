<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        // CSRF validation
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error_message'] = 'Invalid request. Please try again.';
            redirect($_SERVER['PHP_SELF']);
        }
        $settings = [
            'business_name' => sanitizeInput($_POST['business_name']),
            'business_address' => sanitizeInput($_POST['business_address']),
            'business_phone' => sanitizeInput($_POST['business_phone']),
            'business_email' => sanitizeInput($_POST['business_email']),
            'tax_rate' => $_POST['tax_rate'],
            'currency' => sanitizeInput($_POST['currency']),
            'receipt_header' => sanitizeInput($_POST['receipt_header']),
            'receipt_footer' => sanitizeInput($_POST['receipt_footer']),
            'business_latitude' => sanitizeInput($_POST['business_latitude'] ?? ''),
            'business_longitude' => sanitizeInput($_POST['business_longitude'] ?? ''),
            'delivery_base_fee' => sanitizeInput($_POST['delivery_base_fee'] ?? ''),
            'delivery_per_km_rate' => sanitizeInput($_POST['delivery_per_km_rate'] ?? '')
        ];
        
        foreach ($settings as $key => $value) {
            $db->query(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE setting_value = ?",
                [$key, $value, $value]
            );
        }
        
        $_SESSION['success_message'] = 'Settings updated successfully';
        redirect($_SERVER['PHP_SELF']);
    }
}

// Get current settings
$settingsRaw = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($settingsRaw as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

$pageTitle = 'Settings';
include 'includes/header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>

<h4 class="mb-3"><i class="bi bi-gear me-2"></i>System Settings</h4>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-building me-2"></i>Business Information</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Business Name *</label>
                        <input type="text" class="form-control" name="business_name" 
                               value="<?= htmlspecialchars($settings['business_name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Business Address</label>
                        <textarea class="form-control" name="business_address" rows="2"><?= htmlspecialchars($settings['business_address'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" class="form-control" name="business_phone" 
                                   value="<?= htmlspecialchars($settings['business_phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="business_email" 
                                   value="<?= htmlspecialchars($settings['business_email'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Default Tax Rate (%)</label>
                            <input type="number" step="0.01" class="form-control" name="tax_rate" 
                                   value="<?= htmlspecialchars($settings['tax_rate'] ?? '16') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Currency Symbol</label>
                            <input type="text" class="form-control" name="currency" 
                                   value="<?= htmlspecialchars($settings['currency'] ?? '$') ?>"
                                   placeholder="e.g. $, €, £, ¥, KES">
                            <small class="text-muted">Examples: $ € £ ₹ KES USD</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Receipt Header Text</label>
                        <input type="text" class="form-control" name="receipt_header" 
                               value="<?= htmlspecialchars($settings['receipt_header'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Receipt Footer Text</label>
                        <input type="text" class="form-control" name="receipt_footer" 
                               value="<?= htmlspecialchars($settings['receipt_footer'] ?? '') ?>">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Business Latitude</label>
                            <input type="number" step="0.000001" class="form-control" name="business_latitude"
                                   value="<?= htmlspecialchars($settings['business_latitude'] ?? '') ?>"
                                   placeholder="e.g. -1.292066">
                            <small class="text-muted">Origin latitude used for delivery distance calculations.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Business Longitude</label>
                            <input type="number" step="0.000001" class="form-control" name="business_longitude"
                                   value="<?= htmlspecialchars($settings['business_longitude'] ?? '') ?>"
                                   placeholder="e.g. 36.821946">
                            <small class="text-muted">Origin longitude used for delivery distance calculations.</small>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Default Base Delivery Fee</label>
                            <input type="number" step="0.01" class="form-control" name="delivery_base_fee"
                                   value="<?= htmlspecialchars($settings['delivery_base_fee'] ?? '') ?>"
                                   placeholder="e.g. 50">
                            <small class="text-muted">Applied before distance when no zone configuration exists.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Default Per-Kilometer Rate</label>
                            <input type="number" step="0.01" class="form-control" name="delivery_per_km_rate"
                                   value="<?= htmlspecialchars($settings['delivery_per_km_rate'] ?? '') ?>"
                                   placeholder="e.g. 10">
                            <small class="text-muted">Rate per kilometer outside the base distance.</small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Save Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>System Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <td><strong>Version:</strong></td>
                        <td>1.0.0</td>
                    </tr>
                    <tr>
                        <td><strong>PHP Version:</strong></td>
                        <td><?= phpversion() ?></td>
                    </tr>
                    <tr>
                        <td><strong>Database:</strong></td>
                        <td><?= DB_NAME ?></td>
                    </tr>
                    <tr>
                        <td><strong>Installation:</strong></td>
                        <td><?= date('Y-m-d') ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-shield-check me-2"></i>Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="backup.php" class="btn btn-outline-primary">
                        <i class="bi bi-download me-2"></i>Backup Database
                    </a>
                    <a href="users.php" class="btn btn-outline-success">
                        <i class="bi bi-people me-2"></i>Manage Users
                    </a>
                    <a href="?clear_cache=1" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise me-2"></i>Clear Cache
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
