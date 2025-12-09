<?php
/**
 * WAPOS - Branding & Logo Settings
 * Manage business logo for receipts, invoices, reports, and emails
 */
require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'developer', 'super_admin']);

$pageTitle = 'Branding Settings';

// Get current branding settings
$brandingSettings = SettingsStore::getByPrefix('branding_');
$businessLogo = $brandingSettings['branding_logo'] ?? '';
$businessLogoLight = $brandingSettings['branding_logo_light'] ?? '';
$businessName = $brandingSettings['branding_business_name'] ?? settings('business_name', 'WAPOS');
$businessTagline = $brandingSettings['branding_tagline'] ?? '';
$primaryColor = $brandingSettings['branding_primary_color'] ?? '#2563eb';
$secondaryColor = $brandingSettings['branding_secondary_color'] ?? '#1e293b';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle logo upload
        if (!empty($_FILES['business_logo']['name'])) {
            $uploadResult = uploadImage($_FILES['business_logo'], 'logos');
            if ($uploadResult['success']) {
                SettingsStore::persist('branding_logo', $uploadResult['path']);
                $businessLogo = $uploadResult['path'];
            } else {
                throw new Exception($uploadResult['error']);
            }
        }
        
        // Handle light logo upload
        if (!empty($_FILES['business_logo_light']['name'])) {
            $uploadResult = uploadImage($_FILES['business_logo_light'], 'logos');
            if ($uploadResult['success']) {
                SettingsStore::persist('branding_logo_light', $uploadResult['path']);
                $businessLogoLight = $uploadResult['path'];
            } else {
                throw new Exception($uploadResult['error']);
            }
        }
        
        // Save other settings
        SettingsStore::persist('branding_business_name', $_POST['business_name'] ?? $businessName);
        SettingsStore::persist('branding_tagline', $_POST['tagline'] ?? '');
        SettingsStore::persist('branding_primary_color', $_POST['primary_color'] ?? '#2563eb');
        SettingsStore::persist('branding_secondary_color', $_POST['secondary_color'] ?? '#1e293b');
        
        // Also update main business name setting
        SettingsStore::persist('business_name', $_POST['business_name'] ?? $businessName);
        
        $successMessage = 'Branding settings saved successfully!';
        
        // Refresh settings
        $brandingSettings = SettingsStore::getByPrefix('branding_');
        $businessLogo = $brandingSettings['branding_logo'] ?? '';
        $businessLogoLight = $brandingSettings['branding_logo_light'] ?? '';
        $businessName = $brandingSettings['branding_business_name'] ?? settings('business_name', 'WAPOS');
        $businessTagline = $brandingSettings['branding_tagline'] ?? '';
        $primaryColor = $brandingSettings['branding_primary_color'] ?? '#2563eb';
        $secondaryColor = $brandingSettings['branding_secondary_color'] ?? '#1e293b';
        
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

/**
 * Upload image helper function
 */
function uploadImage($file, $subfolder = 'uploads') {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed with error code: ' . $file['error']];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 5MB.'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP, SVG'];
    }
    
    // Create upload directory
    $uploadDir = __DIR__ . '/storage/uploads/' . $subfolder . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'path' => '/wapos/storage/uploads/' . $subfolder . '/' . $filename,
            'filename' => $filename
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to save file.'];
}

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-palette me-2 text-primary"></i>Branding Settings</h2>
            <p class="text-muted mb-0">Customize your business logo and branding for receipts, invoices, and reports</p>
        </div>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="row g-4">
            <!-- Logo Upload Section -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-image me-2"></i>Business Logo</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Upload your business logo. This will appear on receipts, invoices, reports, and emails.</p>
                        
                        <!-- Current Logo Preview -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Primary Logo (Dark Background)</label>
                            <div class="border rounded p-4 text-center bg-dark mb-2" style="min-height: 150px;">
                                <?php if ($businessLogo): ?>
                                    <img src="<?= htmlspecialchars($businessLogo) ?>" alt="Business Logo" 
                                         style="max-height: 120px; max-width: 100%;" class="img-fluid">
                                <?php else: ?>
                                    <div class="text-white-50">
                                        <i class="bi bi-image" style="font-size: 3rem;"></i>
                                        <p class="mb-0 mt-2">No logo uploaded</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <input type="file" class="form-control" name="business_logo" accept="image/*">
                            <div class="form-text">Recommended: PNG or SVG with transparent background, 400x150px or larger</div>
                        </div>

                        <!-- Light Logo -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Secondary Logo (Light Background)</label>
                            <div class="border rounded p-4 text-center bg-light mb-2" style="min-height: 150px;">
                                <?php if ($businessLogoLight): ?>
                                    <img src="<?= htmlspecialchars($businessLogoLight) ?>" alt="Business Logo Light" 
                                         style="max-height: 120px; max-width: 100%;" class="img-fluid">
                                <?php elseif ($businessLogo): ?>
                                    <img src="<?= htmlspecialchars($businessLogo) ?>" alt="Business Logo" 
                                         style="max-height: 120px; max-width: 100%;" class="img-fluid">
                                <?php else: ?>
                                    <div class="text-muted">
                                        <i class="bi bi-image" style="font-size: 3rem;"></i>
                                        <p class="mb-0 mt-2">No logo uploaded</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <input type="file" class="form-control" name="business_logo_light" accept="image/*">
                            <div class="form-text">Optional: Use if your primary logo doesn't work on light backgrounds</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Business Info & Colors -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-building me-2"></i>Business Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Business Name</label>
                            <input type="text" class="form-control" name="business_name" 
                                   value="<?= htmlspecialchars($businessName) ?>" required>
                            <div class="form-text">Appears on receipts and documents</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Tagline / Slogan</label>
                            <input type="text" class="form-control" name="tagline" 
                                   value="<?= htmlspecialchars($businessTagline) ?>" 
                                   placeholder="e.g., Quality Service, Always">
                            <div class="form-text">Optional tagline shown below logo</div>
                        </div>

                        <hr>

                        <h6 class="mb-3"><i class="bi bi-palette2 me-2"></i>Brand Colors</h6>
                        
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Primary Color</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" 
                                           name="primary_color" value="<?= htmlspecialchars($primaryColor) ?>">
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($primaryColor) ?>" 
                                           id="primaryColorText" readonly style="max-width: 100px;">
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Secondary Color</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" 
                                           name="secondary_color" value="<?= htmlspecialchars($secondaryColor) ?>">
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($secondaryColor) ?>" 
                                           id="secondaryColorText" readonly style="max-width: 100px;">
                                </div>
                            </div>
                        </div>
                        <div class="form-text">Colors used in reports and branded documents</div>
                    </div>
                </div>
            </div>

            <!-- Preview Section -->
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-eye me-2"></i>Preview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <!-- Receipt Preview -->
                            <div class="col-md-4">
                                <h6 class="text-muted mb-3">Receipt Preview</h6>
                                <div class="border rounded p-3 bg-white" style="max-width: 300px; font-family: monospace; font-size: 12px;">
                                    <div class="text-center mb-3">
                                        <?php if ($businessLogo): ?>
                                            <img src="<?= htmlspecialchars($businessLogo) ?>" alt="Logo" style="max-height: 50px; max-width: 150px;">
                                        <?php endif; ?>
                                        <div class="fw-bold mt-2"><?= htmlspecialchars($businessName) ?></div>
                                        <?php if ($businessTagline): ?>
                                            <div class="small text-muted"><?= htmlspecialchars($businessTagline) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <hr style="border-style: dashed;">
                                    <div class="small">
                                        <div>Receipt #: INV-001234</div>
                                        <div>Date: <?= date('d/m/Y H:i') ?></div>
                                    </div>
                                    <hr style="border-style: dashed;">
                                    <div class="small">
                                        <div class="d-flex justify-content-between">
                                            <span>Item 1 x2</span>
                                            <span>1,000</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Item 2 x1</span>
                                            <span>500</span>
                                        </div>
                                    </div>
                                    <hr style="border-style: dashed;">
                                    <div class="d-flex justify-content-between fw-bold">
                                        <span>TOTAL</span>
                                        <span>1,500</span>
                                    </div>
                                    <div class="text-center mt-3 small text-muted">
                                        Thank you for your business!
                                    </div>
                                </div>
                            </div>

                            <!-- Invoice Header Preview -->
                            <div class="col-md-4">
                                <h6 class="text-muted mb-3">Invoice Header Preview</h6>
                                <div class="border rounded p-4 bg-white">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <?php if ($businessLogo): ?>
                                                <img src="<?= htmlspecialchars($businessLogo) ?>" alt="Logo" style="max-height: 60px; max-width: 180px;">
                                            <?php else: ?>
                                                <h4 class="mb-0" style="color: <?= htmlspecialchars($primaryColor) ?>">
                                                    <?= htmlspecialchars($businessName) ?>
                                                </h4>
                                            <?php endif; ?>
                                            <?php if ($businessTagline): ?>
                                                <p class="text-muted small mb-0 mt-1"><?= htmlspecialchars($businessTagline) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <h5 class="mb-0" style="color: <?= htmlspecialchars($primaryColor) ?>">INVOICE</h5>
                                            <small class="text-muted">#INV-001234</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Report Header Preview -->
                            <div class="col-md-4">
                                <h6 class="text-muted mb-3">Report Header Preview</h6>
                                <div class="border rounded overflow-hidden">
                                    <div class="p-3 text-white" style="background: <?= htmlspecialchars($primaryColor) ?>">
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if ($businessLogo): ?>
                                                <img src="<?= htmlspecialchars($businessLogo) ?>" alt="Logo" style="max-height: 40px; max-width: 120px;">
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-0"><?= htmlspecialchars($businessName) ?></h6>
                                                <small class="opacity-75">Sales Report - <?= date('F Y') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3 bg-light small">
                                        <div class="row text-center">
                                            <div class="col">
                                                <div class="fw-bold">125,000</div>
                                                <div class="text-muted">Total Sales</div>
                                            </div>
                                            <div class="col">
                                                <div class="fw-bold">48</div>
                                                <div class="text-muted">Transactions</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Usage Guide -->
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Where Your Logo Appears</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-receipt text-primary fs-4"></i>
                                    <div>
                                        <strong>Receipts</strong>
                                        <p class="mb-0 small text-muted">POS thermal receipts</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-file-earmark-text text-success fs-4"></i>
                                    <div>
                                        <strong>Invoices</strong>
                                        <p class="mb-0 small text-muted">PDF invoices & bills</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-bar-chart text-info fs-4"></i>
                                    <div>
                                        <strong>Reports</strong>
                                        <p class="mb-0 small text-muted">Sales & financial reports</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-envelope text-warning fs-4"></i>
                                    <div>
                                        <strong>Emails</strong>
                                        <p class="mb-0 small text-muted">Customer notifications</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="col-12">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-lg me-2"></i>Save Branding Settings
                    </button>
                    <a href="settings.php" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-arrow-left me-2"></i>Back to Settings
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Update color text inputs when color picker changes
document.querySelectorAll('input[type="color"]').forEach(input => {
    input.addEventListener('input', function() {
        const textInput = this.parentElement.querySelector('input[type="text"]');
        if (textInput) {
            textInput.value = this.value;
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
