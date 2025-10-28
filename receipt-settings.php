<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'business_name' => $_POST['business_name'] ?? '',
        'business_tagline' => $_POST['business_tagline'] ?? '',
        'business_address' => $_POST['business_address'] ?? '',
        'business_phone' => $_POST['business_phone'] ?? '',
        'business_email' => $_POST['business_email'] ?? '',
        'business_website' => $_POST['business_website'] ?? '',
        'vat_number' => $_POST['vat_number'] ?? '',
        'tax_id' => $_POST['tax_id'] ?? '',
        'receipt_header' => $_POST['receipt_header'] ?? '',
        'receipt_footer' => $_POST['receipt_footer'] ?? '',
        'return_period' => $_POST['return_period'] ?? '30',
        'return_conditions' => $_POST['return_conditions'] ?? '',
        'enable_qr_code' => isset($_POST['enable_qr_code']) ? '1' : '0',
        'current_promotion' => $_POST['current_promotion'] ?? '',
        'promo_code' => $_POST['promo_code'] ?? '',
        'facebook_page' => $_POST['facebook_page'] ?? '',
        'instagram_handle' => $_POST['instagram_handle'] ?? '',
        'twitter_handle' => $_POST['twitter_handle'] ?? '',
        'whatsapp_number' => $_POST['whatsapp_number'] ?? '',
        'loyalty_program' => $_POST['loyalty_program'] ?? '',
        'printer_type' => $_POST['printer_type'] ?? 'epson_tm_t88',
        'receipt_width' => $_POST['receipt_width'] ?? '80',
        'print_density' => $_POST['print_density'] ?? 'normal'
    ];
    
    foreach ($settings as $key => $value) {
        $existing = $db->fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
        if ($existing) {
            $db->query("UPDATE settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
        } else {
            $db->insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
    }
    
    $success = "Receipt settings updated successfully!";
}

// Get current settings
$settingsRaw = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($settingsRaw as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

$pageTitle = 'Receipt Settings';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-receipt"></i> Receipt Configuration</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="row">
                            <!-- Business Information -->
                            <div class="col-md-6">
                                <h6 class="text-primary">Business Information</h6>
                                <div class="mb-3">
                                    <label class="form-label">Business Name</label>
                                    <input type="text" name="business_name" class="form-control" value="<?= htmlspecialchars($settings['business_name'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Business Tagline</label>
                                    <input type="text" name="business_tagline" class="form-control" value="<?= htmlspecialchars($settings['business_tagline'] ?? '') ?>" placeholder="e.g., Your trusted partner">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="business_address" class="form-control" rows="3"><?= htmlspecialchars($settings['business_address'] ?? '') ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="business_phone" class="form-control" value="<?= htmlspecialchars($settings['business_phone'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="business_email" class="form-control" value="<?= htmlspecialchars($settings['business_email'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Website</label>
                                    <input type="url" name="business_website" class="form-control" value="<?= htmlspecialchars($settings['business_website'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <!-- Tax & Legal -->
                            <div class="col-md-6">
                                <h6 class="text-primary">Tax & Legal Information</h6>
                                <div class="mb-3">
                                    <label class="form-label">VAT Number</label>
                                    <input type="text" name="vat_number" class="form-control" value="<?= htmlspecialchars($settings['vat_number'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Tax ID</label>
                                    <input type="text" name="tax_id" class="form-control" value="<?= htmlspecialchars($settings['tax_id'] ?? '') ?>">
                                </div>
                                
                                <h6 class="text-primary mt-4">Receipt Messages</h6>
                                <div class="mb-3">
                                    <label class="form-label">Header Message</label>
                                    <textarea name="receipt_header" class="form-control" rows="2" placeholder="Welcome message or special notice"><?= htmlspecialchars($settings['receipt_header'] ?? '') ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Footer Message</label>
                                    <textarea name="receipt_footer" class="form-control" rows="2" placeholder="Thank you message or additional info"><?= htmlspecialchars($settings['receipt_footer'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <!-- Return Policy -->
                            <div class="col-md-6">
                                <h6 class="text-primary">Return & Exchange Policy</h6>
                                <div class="mb-3">
                                    <label class="form-label">Return Period (days)</label>
                                    <input type="number" name="return_period" class="form-control" value="<?= htmlspecialchars($settings['return_period'] ?? '30') ?>" min="1" max="365">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Additional Conditions</label>
                                    <textarea name="return_conditions" class="form-control" rows="2" placeholder="Special conditions or restrictions"><?= htmlspecialchars($settings['return_conditions'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Digital Features -->
                            <div class="col-md-6">
                                <h6 class="text-primary">Digital Features</h6>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="enable_qr_code" class="form-check-input" <?= ($settings['enable_qr_code'] ?? '0') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">Enable QR Code on Receipts</label>
                                    </div>
                                    <small class="text-muted">Allows customers to access digital receipt and provide feedback</small>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <!-- Promotions -->
                            <div class="col-md-6">
                                <h6 class="text-primary">Current Promotions</h6>
                                <div class="mb-3">
                                    <label class="form-label">Promotion Text</label>
                                    <input type="text" name="current_promotion" class="form-control" value="<?= htmlspecialchars($settings['current_promotion'] ?? '') ?>" placeholder="e.g., 10% off next purchase">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Promo Code</label>
                                    <input type="text" name="promo_code" class="form-control" value="<?= htmlspecialchars($settings['promo_code'] ?? '') ?>" placeholder="e.g., SAVE10">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Loyalty Program</label>
                                    <input type="text" name="loyalty_program" class="form-control" value="<?= htmlspecialchars($settings['loyalty_program'] ?? '') ?>" placeholder="e.g., Earn points with every purchase">
                                </div>
                            </div>
                            
                            <!-- Social Media -->
                            <div class="col-md-6">
                                <h6 class="text-primary">Social Media Links</h6>
                                <div class="mb-3">
                                    <label class="form-label">Facebook Page</label>
                                    <input type="text" name="facebook_page" class="form-control" value="<?= htmlspecialchars($settings['facebook_page'] ?? '') ?>" placeholder="facebook.com/yourpage">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Instagram Handle</label>
                                    <input type="text" name="instagram_handle" class="form-control" value="<?= htmlspecialchars($settings['instagram_handle'] ?? '') ?>" placeholder="yourbusiness">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Twitter Handle</label>
                                    <input type="text" name="twitter_handle" class="form-control" value="<?= htmlspecialchars($settings['twitter_handle'] ?? '') ?>" placeholder="yourbusiness">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">WhatsApp Number</label>
                                    <input type="text" name="whatsapp_number" class="form-control" value="<?= htmlspecialchars($settings['whatsapp_number'] ?? '') ?>" placeholder="+1234567890">
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <!-- Printer Settings -->
                            <div class="col-md-6">
                                <h6 class="text-primary">Thermal Printer Settings</h6>
                                <div class="mb-3">
                                    <label class="form-label">Printer Type</label>
                                    <select name="printer_type" class="form-select">
                                        <option value="epson_tm_t88" <?= ($settings['printer_type'] ?? '') === 'epson_tm_t88' ? 'selected' : '' ?>>Epson TM-T88 Series</option>
                                        <option value="epson_tm_t20" <?= ($settings['printer_type'] ?? '') === 'epson_tm_t20' ? 'selected' : '' ?>>Epson TM-T20 Series</option>
                                        <option value="generic_80mm" <?= ($settings['printer_type'] ?? '') === 'generic_80mm' ? 'selected' : '' ?>>Generic 80mm Thermal</option>
                                        <option value="generic_58mm" <?= ($settings['printer_type'] ?? '') === 'generic_58mm' ? 'selected' : '' ?>>Generic 58mm Thermal</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Receipt Width (mm)</label>
                                    <select name="receipt_width" class="form-select">
                                        <option value="80" <?= ($settings['receipt_width'] ?? '80') === '80' ? 'selected' : '' ?>>80mm (Standard)</option>
                                        <option value="58" <?= ($settings['receipt_width'] ?? '80') === '58' ? 'selected' : '' ?>>58mm (Compact)</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Print Density</label>
                                    <select name="print_density" class="form-select">
                                        <option value="high" <?= ($settings['print_density'] ?? 'normal') === 'high' ? 'selected' : '' ?>>High (More text per line)</option>
                                        <option value="normal" <?= ($settings['print_density'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>Normal (Recommended)</option>
                                        <option value="low" <?= ($settings['print_density'] ?? 'normal') === 'low' ? 'selected' : '' ?>>Low (Larger text)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2"></i> Save Settings
                            </button>
                            <a href="print-receipt.php?id=1" target="_blank" class="btn btn-outline-secondary">
                                <i class="bi bi-eye"></i> Preview Receipt
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-info-circle"></i> Receipt Elements Guide</h6>
                </div>
                <div class="card-body">
                    <h6 class="text-success">✅ Essential Elements Included:</h6>
                    <ul class="list-unstyled small">
                        <li>✓ Business name, logo, and contact details</li>
                        <li>✓ Transaction date, time, and receipt number</li>
                        <li>✓ Cashier name and terminal ID</li>
                        <li>✓ Itemized list with SKU, quantity, and prices</li>
                        <li>✓ Subtotal, taxes, discounts, and total</li>
                        <li>✓ Payment method and card details (masked)</li>
                        <li>✓ Return and exchange policy</li>
                        <li>✓ QR codes for digital receipt access</li>
                        <li>✓ Promotional offers and loyalty program</li>
                        <li>✓ Social media links and contact info</li>
                    </ul>
                    
                    <h6 class="text-primary mt-3">📱 Digital Features:</h6>
                    <ul class="list-unstyled small">
                        <li>• QR code links to digital receipt</li>
                        <li>• Customer feedback system</li>
                        <li>• Social sharing options</li>
                        <li>• Mobile-optimized viewing</li>
                    </ul>
                    
                    <h6 class="text-info mt-3">🖨️ Printer Optimization:</h6>
                    <ul class="list-unstyled small">
                        <li>• Optimized for 80mm thermal printers</li>
                        <li>• Epson TM-T20/TM-T88 compatibility</li>
                        <li>• Proper spacing and font sizing</li>
                        <li>• High contrast for clear printing</li>
                    </ul>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h6><i class="bi bi-printer"></i> Thermal Printer Specs</h6>
                </div>
                <div class="card-body small">
                    <strong>Standard Dimensions:</strong><br>
                    • Width: 79.5mm ± 0.5mm<br>
                    • Roll Diameter: Up to 83mm<br>
                    • Paper Thickness: 0.06-0.08mm<br><br>
                    
                    <strong>Compatible Models:</strong><br>
                    • Epson TM-T88 Series<br>
                    • Epson TM-T20 Series<br>
                    • Most 80mm thermal printers<br>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
