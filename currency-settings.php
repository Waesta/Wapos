<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();
$currencyManager = CurrencyManager::getInstance();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF validation
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error_message'] = 'Invalid request. Please try again.';
            redirect($_SERVER['PHP_SELF']);
        }
        $settings = [
            'currency_code' => sanitizeInput($_POST['currency_code']),
            'currency_symbol' => sanitizeInput($_POST['currency_symbol']),
            'currency_name' => sanitizeInput($_POST['currency_name']),
            'currency_position' => sanitizeInput($_POST['currency_position']),
            'decimal_places' => (int)$_POST['decimal_places'],
            'decimal_separator' => sanitizeInput($_POST['decimal_separator']),
            'thousands_separator' => sanitizeInput($_POST['thousands_separator'])
        ];
        
        $currencyManager->updateCurrencySettings($settings);
        $_SESSION['success_message'] = 'Currency settings updated successfully';
        redirect($_SERVER['PHP_SELF']);
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error updating settings: ' . $e->getMessage();
    }
}

// Get current settings
$currentSettings = [
    'currency_code' => $currencyManager->getCurrencyCode(),
    'currency_symbol' => $currencyManager->getCurrencySymbol(),
    'currency_name' => $currencyManager->getCurrencyName()
];

// Get all settings for form
$allSettings = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'currency_%'");
$settingsArray = [];
foreach ($allSettings as $setting) {
    $settingsArray[$setting['setting_key']] = $setting['setting_value'];
}

// Common currencies
$commonCurrencies = [
    ['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar'],
    ['code' => 'EUR', 'symbol' => '€', 'name' => 'Euro'],
    ['code' => 'GBP', 'symbol' => '£', 'name' => 'British Pound'],
    ['code' => 'KES', 'symbol' => 'KSh', 'name' => 'Kenyan Shilling'],
    ['code' => 'NGN', 'symbol' => '₦', 'name' => 'Nigerian Naira'],
    ['code' => 'ZAR', 'symbol' => 'R', 'name' => 'South African Rand'],
    ['code' => 'CAD', 'symbol' => 'C$', 'name' => 'Canadian Dollar'],
    ['code' => 'AUD', 'symbol' => 'A$', 'name' => 'Australian Dollar'],
    ['code' => 'JPY', 'symbol' => '¥', 'name' => 'Japanese Yen'],
    ['code' => 'CNY', 'symbol' => '¥', 'name' => 'Chinese Yuan'],
    ['code' => 'INR', 'symbol' => '₹', 'name' => 'Indian Rupee']
];

$pageTitle = 'Currency Settings';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5><i class="bi bi-currency-exchange me-2"></i>Currency Settings</h5>
                    <small>Configure your preferred currency and formatting options</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-gear"></i> Currency Configuration</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="currency_code" class="form-label">Currency Code</label>
                                    <select class="form-select" name="currency_code" id="currency_code" onchange="updateCurrencyInfo()">
                                        <?php foreach ($commonCurrencies as $currency): ?>
                                        <option value="<?= $currency['code'] ?>" 
                                                data-symbol="<?= htmlspecialchars($currency['symbol']) ?>"
                                                data-name="<?= htmlspecialchars($currency['name']) ?>"
                                                <?= $currentSettings['currency_code'] === $currency['code'] ? 'selected' : '' ?>>
                                            <?= $currency['code'] ?> - <?= htmlspecialchars($currency['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Select your primary currency</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="currency_symbol" class="form-label">Currency Symbol</label>
                                    <input type="text" class="form-control" name="currency_symbol" id="currency_symbol" 
                                           value="<?= htmlspecialchars($currentSettings['currency_symbol']) ?>" required>
                                    <small class="text-muted">Symbol to display (e.g., $, €, £)</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="currency_name" class="form-label">Currency Name</label>
                            <input type="text" class="form-control" name="currency_name" id="currency_name" 
                                   value="<?= htmlspecialchars($currentSettings['currency_name']) ?>" required>
                            <small class="text-muted">Full name of the currency</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="currency_position" class="form-label">Symbol Position</label>
                                    <select class="form-select" name="currency_position" id="currency_position">
                                        <option value="before" <?= ($settingsArray['currency_position'] ?? 'before') === 'before' ? 'selected' : '' ?>>
                                            Before amount ($100.00)
                                        </option>
                                        <option value="after" <?= ($settingsArray['currency_position'] ?? 'before') === 'after' ? 'selected' : '' ?>>
                                            After amount (100.00 $)
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="decimal_places" class="form-label">Decimal Places</label>
                                    <select class="form-select" name="decimal_places" id="decimal_places">
                                        <option value="0" <?= ($settingsArray['decimal_places'] ?? '2') === '0' ? 'selected' : '' ?>>0 (100)</option>
                                        <option value="2" <?= ($settingsArray['decimal_places'] ?? '2') === '2' ? 'selected' : '' ?>>2 (100.00)</option>
                                        <option value="3" <?= ($settingsArray['decimal_places'] ?? '2') === '3' ? 'selected' : '' ?>>3 (100.000)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="decimal_separator" class="form-label">Decimal Separator</label>
                                    <select class="form-select" name="decimal_separator" id="decimal_separator">
                                        <option value="." <?= ($settingsArray['decimal_separator'] ?? '.') === '.' ? 'selected' : '' ?>>Period (.)</option>
                                        <option value="," <?= ($settingsArray['decimal_separator'] ?? '.') === ',' ? 'selected' : '' ?>>Comma (,)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="thousands_separator" class="form-label">Thousands Separator</label>
                                    <select class="form-select" name="thousands_separator" id="thousands_separator">
                                        <option value="," <?= ($settingsArray['thousands_separator'] ?? ',') === ',' ? 'selected' : '' ?>>Comma (,)</option>
                                        <option value="." <?= ($settingsArray['thousands_separator'] ?? ',') === '.' ? 'selected' : '' ?>>Period (.)</option>
                                        <option value=" " <?= ($settingsArray['thousands_separator'] ?? ',') === ' ' ? 'selected' : '' ?>>Space ( )</option>
                                        <option value="" <?= ($settingsArray['thousands_separator'] ?? ',') === '' ? 'selected' : '' ?>>None</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Preview</label>
                            <div class="alert alert-info">
                                <h6>Sample Amount: <span id="previewAmount">$1,234.56</span></h6>
                                <small class="text-muted">This is how amounts will appear in your system</small>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Update Currency Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-info-circle"></i> Current Settings</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Currency:</strong></td>
                            <td><?= htmlspecialchars($currentSettings['currency_code']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Symbol:</strong></td>
                            <td><?= htmlspecialchars($currentSettings['currency_symbol']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Name:</strong></td>
                            <td><?= htmlspecialchars($currentSettings['currency_name']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Sample:</strong></td>
                            <td><?= formatCurrency(1234.56) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6><i class="bi bi-exclamation-triangle"></i> Important Notes</h6>
                </div>
                <div class="card-body">
                    <ul class="small">
                        <li>Currency changes affect all prices and amounts system-wide</li>
                        <li>Existing transaction records maintain their original currency</li>
                        <li>Reports will show amounts in the current currency format</li>
                        <li>POS interface will update immediately after saving</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateCurrencyInfo() {
    const select = document.getElementById('currency_code');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption) {
        document.getElementById('currency_symbol').value = selectedOption.dataset.symbol || '';
        document.getElementById('currency_name').value = selectedOption.dataset.name || '';
        updatePreview();
    }
}

function updatePreview() {
    const symbol = document.getElementById('currency_symbol').value || '$';
    const position = document.getElementById('currency_position').value;
    const decimalPlaces = parseInt(document.getElementById('decimal_places').value) || 2;
    const decimalSeparator = document.getElementById('decimal_separator').value || '.';
    const thousandsSeparator = document.getElementById('thousands_separator').value || ',';
    
    let amount = 1234.56;
    let formattedAmount = amount.toFixed(decimalPlaces);
    
    // Replace decimal separator
    if (decimalSeparator !== '.') {
        formattedAmount = formattedAmount.replace('.', decimalSeparator);
    }
    
    // Add thousands separator
    if (thousandsSeparator && formattedAmount.length > 4) {
        const parts = formattedAmount.split(decimalSeparator);
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSeparator);
        formattedAmount = parts.join(decimalSeparator);
    }
    
    // Add currency symbol
    let preview;
    if (position === 'after') {
        preview = formattedAmount + ' ' + symbol;
    } else {
        preview = symbol + ' ' + formattedAmount;
    }
    
    document.getElementById('previewAmount').textContent = preview;
}

// Update preview when any field changes
document.addEventListener('DOMContentLoaded', function() {
    updatePreview();
    
    ['currency_symbol', 'currency_position', 'decimal_places', 'decimal_separator', 'thousands_separator'].forEach(id => {
        document.getElementById(id).addEventListener('change', updatePreview);
    });
});
</script>

<?php include 'includes/footer.php'; ?>
