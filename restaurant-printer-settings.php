<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        // Kitchen Printer Settings
        'kitchen_printer_enabled' => isset($_POST['kitchen_printer_enabled']) ? '1' : '0',
        'kitchen_printer_ip' => $_POST['kitchen_printer_ip'] ?? '',
        'kitchen_printer_name' => $_POST['kitchen_printer_name'] ?? 'Kitchen Printer',
        'kitchen_auto_print' => isset($_POST['kitchen_auto_print']) ? '1' : '0',
        'kitchen_print_copies' => $_POST['kitchen_print_copies'] ?? '1',
        
        // Customer Printer Settings
        'customer_printer_enabled' => isset($_POST['customer_printer_enabled']) ? '1' : '0',
        'customer_printer_ip' => $_POST['customer_printer_ip'] ?? '',
        'customer_printer_name' => $_POST['customer_printer_name'] ?? 'Customer Printer',
        'customer_auto_print_invoice' => isset($_POST['customer_auto_print_invoice']) ? '1' : '0',
        'customer_auto_print_receipt' => isset($_POST['customer_auto_print_receipt']) ? '1' : '0',
        
        // Bar Printer Settings
        'bar_printer_enabled' => isset($_POST['bar_printer_enabled']) ? '1' : '0',
        'bar_printer_ip' => $_POST['bar_printer_ip'] ?? '',
        'bar_printer_name' => $_POST['bar_printer_name'] ?? 'Bar Printer',
        'bar_auto_print' => isset($_POST['bar_auto_print']) ? '1' : '0',
        
        // Receipt Type Settings
        'kitchen_receipt_format' => $_POST['kitchen_receipt_format'] ?? 'standard',
        'customer_receipt_format' => $_POST['customer_receipt_format'] ?? 'detailed',
        'print_modifiers_kitchen' => isset($_POST['print_modifiers_kitchen']) ? '1' : '0',
        'print_allergens_kitchen' => isset($_POST['print_allergens_kitchen']) ? '1' : '0',
        'print_prep_time_kitchen' => isset($_POST['print_prep_time_kitchen']) ? '1' : '0',
        
        // Error Handling
        'printer_retry_attempts' => $_POST['printer_retry_attempts'] ?? '3',
        'printer_timeout' => $_POST['printer_timeout'] ?? '30',
        'fallback_to_screen' => isset($_POST['fallback_to_screen']) ? '1' : '0',
        
        // Zone Management
        'kitchen_zones' => $_POST['kitchen_zones'] ?? '',
        'bar_categories' => $_POST['bar_categories'] ?? '',
    ];
    
    foreach ($settings as $key => $value) {
        $existing = $db->fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
        if ($existing) {
            $db->query("UPDATE settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
        } else {
            $db->insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
    }
    
    $success = "Printer settings updated successfully!";
}

// Get current settings
$settingsRaw = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($settingsRaw as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Get categories for zone assignment
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");

$pageTitle = 'Restaurant Printer Settings';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-printer"></i> Restaurant Printer Configuration</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <!-- Kitchen Printer Settings -->
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">🍳 Kitchen Printer Settings</h6>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="kitchen_printer_enabled" class="form-check-input" <?= ($settings['kitchen_printer_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">Enable Kitchen Printer</label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Printer Name</label>
                                    <input type="text" name="kitchen_printer_name" class="form-control" value="<?= htmlspecialchars($settings['kitchen_printer_name'] ?? 'Kitchen Printer') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Printer IP Address</label>
                                    <input type="text" name="kitchen_printer_ip" class="form-control" value="<?= htmlspecialchars($settings['kitchen_printer_ip'] ?? '') ?>" placeholder="192.168.1.100">
                                    <small class="text-muted">Leave empty for local/USB printer</small>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="kitchen_auto_print" class="form-check-input" <?= ($settings['kitchen_auto_print'] ?? '1') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">Auto-print on order placement</label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Number of Copies</label>
                                    <select name="kitchen_print_copies" class="form-select">
                                        <option value="1" <?= ($settings['kitchen_print_copies'] ?? '1') === '1' ? 'selected' : '' ?>>1 Copy</option>
                                        <option value="2" <?= ($settings['kitchen_print_copies'] ?? '1') === '2' ? 'selected' : '' ?>>2 Copies</option>
                                        <option value="3" <?= ($settings['kitchen_print_copies'] ?? '1') === '3' ? 'selected' : '' ?>>3 Copies</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Customer Printer Settings -->
                            <div class="col-md-6">
                                <h6 class="text-primary">👥 Customer Printer Settings</h6>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="customer_printer_enabled" class="form-check-input" <?= ($settings['customer_printer_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">Enable Customer Printer</label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Printer Name</label>
                                    <input type="text" name="customer_printer_name" class="form-control" value="<?= htmlspecialchars($settings['customer_printer_name'] ?? 'Customer Printer') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Printer IP Address</label>
                                    <input type="text" name="customer_printer_ip" class="form-control" value="<?= htmlspecialchars($settings['customer_printer_ip'] ?? '') ?>" placeholder="192.168.1.101">
                                    <small class="text-muted">Leave empty for local/USB printer</small>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="customer_auto_print_invoice" class="form-check-input" <?= ($settings['customer_auto_print_invoice'] ?? '0') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">Auto-print invoice (pre-payment)</label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="customer_auto_print_receipt" class="form-check-input" <?= ($settings['customer_auto_print_receipt'] ?? '1') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">Auto-print receipt (post-payment)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- Bar Printer Settings -->
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">🍹 Bar Printer Settings</h6>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="bar_printer_enabled" class="form-check-input" <?= ($settings['bar_printer_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">Enable Bar Printer</label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Printer Name</label>
                                    <input type="text" name="bar_printer_name" class="form-control" value="<?= htmlspecialchars($settings['bar_printer_name'] ?? 'Bar Printer') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Printer IP Address</label>
                                    <input type="text" name="bar_printer_ip" class="form-control" value="<?= htmlspecialchars($settings['bar_printer_ip'] ?? '') ?>" placeholder="192.168.1.102">
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="bar_auto_print" class="form-check-input" <?= ($settings['bar_auto_print'] ?? '1') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">Auto-print bar orders</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Receipt Format Settings -->
                            <div class="col-md-6">
                                <h6 class="text-primary">📄 Receipt Format Settings</h6>
                                <div class="mb-3">
                                    <label class="form-label">Kitchen Receipt Format</label>
                                    <select name="kitchen_receipt_format" class="form-select">
                                        <option value="compact" <?= ($settings['kitchen_receipt_format'] ?? 'standard') === 'compact' ? 'selected' : '' ?>>Compact (minimal info)</option>
                                        <option value="standard" <?= ($settings['kitchen_receipt_format'] ?? 'standard') === 'standard' ? 'selected' : '' ?>>Standard (recommended)</option>
                                        <option value="detailed" <?= ($settings['kitchen_receipt_format'] ?? 'standard') === 'detailed' ? 'selected' : '' ?>>Detailed (all info)</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Customer Receipt Format</label>
                                    <select name="customer_receipt_format" class="form-select">
                                        <option value="simple" <?= ($settings['customer_receipt_format'] ?? 'detailed') === 'simple' ? 'selected' : '' ?>>Simple</option>
                                        <option value="detailed" <?= ($settings['customer_receipt_format'] ?? 'detailed') === 'detailed' ? 'selected' : '' ?>>Detailed (recommended)</option>
                                        <option value="premium" <?= ($settings['customer_receipt_format'] ?? 'detailed') === 'premium' ? 'selected' : '' ?>>Premium (with QR codes)</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="print_modifiers_kitchen" class="form-check-input" <?= ($settings['print_modifiers_kitchen'] ?? '1') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">Print modifiers on kitchen receipt</label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="print_allergens_kitchen" class="form-check-input" <?= ($settings['print_allergens_kitchen'] ?? '1') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">Print allergen warnings</label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="print_prep_time_kitchen" class="form-check-input" <?= ($settings['print_prep_time_kitchen'] ?? '1') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">Print preparation times</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- Zone Management -->
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">🏢 Kitchen Zones</h6>
                                <div class="mb-3">
                                    <label class="form-label">Kitchen Categories</label>
                                    <select name="kitchen_zones[]" class="form-select" multiple size="6">
                                        <?php 
                                        $kitchenZones = explode(',', $settings['kitchen_zones'] ?? '');
                                        foreach ($categories as $cat): 
                                        ?>
                                        <option value="<?= $cat['id'] ?>" <?= in_array($cat['id'], $kitchenZones) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Hold Ctrl to select multiple categories for kitchen printing</small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="text-primary">🍺 Bar Categories</h6>
                                <div class="mb-3">
                                    <label class="form-label">Bar Categories</label>
                                    <select name="bar_categories[]" class="form-select" multiple size="6">
                                        <?php 
                                        $barCategories = explode(',', $settings['bar_categories'] ?? '');
                                        foreach ($categories as $cat): 
                                        ?>
                                        <option value="<?= $cat['id'] ?>" <?= in_array($cat['id'], $barCategories) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Hold Ctrl to select multiple categories for bar printing</small>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- Error Handling -->
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">⚠️ Error Handling</h6>
                                <div class="mb-3">
                                    <label class="form-label">Retry Attempts</label>
                                    <select name="printer_retry_attempts" class="form-select">
                                        <option value="1" <?= ($settings['printer_retry_attempts'] ?? '3') === '1' ? 'selected' : '' ?>>1 Attempt</option>
                                        <option value="3" <?= ($settings['printer_retry_attempts'] ?? '3') === '3' ? 'selected' : '' ?>>3 Attempts</option>
                                        <option value="5" <?= ($settings['printer_retry_attempts'] ?? '3') === '5' ? 'selected' : '' ?>>5 Attempts</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Timeout (seconds)</label>
                                    <input type="number" name="printer_timeout" class="form-control" value="<?= htmlspecialchars($settings['printer_timeout'] ?? '30') ?>" min="10" max="120">
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="fallback_to_screen" class="form-check-input" <?= ($settings['fallback_to_screen'] ?? '1') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">Show on screen if printing fails</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2"></i> Save Settings
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="testPrinters()">
                                <i class="bi bi-printer"></i> Test Printers
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-info-circle"></i> Receipt Types Guide</h6>
                </div>
                <div class="card-body">
                    <h6 class="text-success">🍳 Kitchen Order Receipt</h6>
                    <ul class="list-unstyled small">
                        <li>✓ Auto-prints when order is placed</li>
                        <li>✓ Shows table number and order details</li>
                        <li>✓ Includes special instructions and allergies</li>
                        <li>✓ Preparation times and modifiers</li>
                        <li>✓ Heat and moisture resistant format</li>
                    </ul>
                    
                    <h6 class="text-info mt-3">📋 Customer Invoice</h6>
                    <ul class="list-unstyled small">
                        <li>• Generated after ordering (pre-payment)</li>
                        <li>• Shows itemized bill for transparency</li>
                        <li>• Includes tax breakdown and totals</li>
                        <li>• Payment terms and conditions</li>
                        <li>• NOT a proof of payment</li>
                    </ul>
                    
                    <h6 class="text-primary mt-3">🧾 Customer Receipt</h6>
                    <ul class="list-unstyled small">
                        <li>• Issued only after payment confirmation</li>
                        <li>• Legal proof of purchase</li>
                        <li>• Complete transaction details</li>
                        <li>• Return policy and QR codes</li>
                        <li>• Marketing and social media links</li>
                    </ul>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h6><i class="bi bi-gear"></i> Printer Setup Tips</h6>
                </div>
                <div class="card-body small">
                    <strong>Network Printers:</strong><br>
                    • Use static IP addresses<br>
                    • Ensure printers are on same network<br>
                    • Test connectivity before setup<br><br>
                    
                    <strong>USB Printers:</strong><br>
                    • Leave IP address field empty<br>
                    • Install printer drivers on server<br>
                    • Use printer's system name<br><br>
                    
                    <strong>Zone Assignment:</strong><br>
                    • Kitchen: Food preparation items<br>
                    • Bar: Beverages and cocktails<br>
                    • Customer: All receipt types<br>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testPrinters() {
    if (confirm('Test all enabled printers?')) {
        // Here you would implement printer testing
        alert('Printer test initiated. Check each printer for test receipts.');
        
        // You could make AJAX calls to test each printer
        fetch('api/test-printers.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ test: true })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Printer test completed successfully!');
            } else {
                alert('Printer test failed: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error testing printers');
        });
    }
}

// Handle multiple select for zones
document.addEventListener('DOMContentLoaded', function() {
    const kitchenZones = document.querySelector('select[name="kitchen_zones[]"]');
    const barCategories = document.querySelector('select[name="bar_categories[]"]');
    
    // Convert multi-select values to comma-separated string on form submit
    document.querySelector('form').addEventListener('submit', function() {
        if (kitchenZones) {
            const selected = Array.from(kitchenZones.selectedOptions).map(option => option.value);
            kitchenZones.insertAdjacentHTML('afterend', 
                '<input type="hidden" name="kitchen_zones" value="' + selected.join(',') + '">');
        }
        
        if (barCategories) {
            const selected = Array.from(barCategories.selectedOptions).map(option => option.value);
            barCategories.insertAdjacentHTML('afterend', 
                '<input type="hidden" name="bar_categories" value="' + selected.join(',') + '">');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
