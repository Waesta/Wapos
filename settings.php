<?php

use App\Services\SystemBackupService;
use App\Services\ScheduledTaskService;
use App\Services\DataPortService;
use App\Services\LoyaltyService;

require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'developer', 'accountant', 'super_admin']);

$db = Database::getInstance();
$pdo = $db->getConnection();
$backupService = new SystemBackupService($pdo);
$scheduledTaskService = new ScheduledTaskService($pdo);
$dataPortService = new DataPortService($pdo);
$loyaltyService = new LoyaltyService($pdo);
$activeLoyaltyProgram = $loyaltyService->getActiveProgram();
if (!$activeLoyaltyProgram) {
    $activeLoyaltyProgram = $loyaltyService->ensureDefaultProgram();
}
$loyaltyStats = $activeLoyaltyProgram ? $loyaltyService->getProgramStats((int)$activeLoyaltyProgram['id']) : null;
$currencyManager = CurrencyManager::getInstance();
$backupConfig = $backupService->getConfig();
$backupLogs = $backupService->listBackups(25);
$dataPortEntities = $dataPortService->getEntities();
$defaultDataPortEntity = array_key_first($dataPortEntities) ?? '';
$userRole = strtolower($auth->getRole() ?? '');
$csrfToken = generateCSRFToken();
$systemManager = SystemManager::getInstance();
$canManageModules = in_array($userRole, ['super_admin', 'developer'], true);

$moduleCatalog = [
    'dashboard' => [
        'label' => 'Executive Dashboard',
        'description' => 'KPIs, revenue pulse, and operational alerts for leadership.',
        'icon' => 'bi-graph-up-arrow',
        'category' => 'Core Platform Essentials',
        'locked' => true,
    ],
    'pos' => [
        'label' => 'Retail POS',
        'description' => 'Counter sales, quick tenders, discounts, and drawer controls.',
        'icon' => 'bi-cart-plus',
        'category' => 'Sales Counters',
    ],
    'sales' => [
        'label' => 'Sales History & Registers',
        'description' => 'Register audit, receipt settings, promotions, and void workflows.',
        'icon' => 'bi-journal-check',
        'category' => 'Sales Counters',
    ],
    'customers' => [
        'label' => 'Customers & Loyalty',
        'description' => 'CRM tools, loyalty balances, and communication history.',
        'icon' => 'bi-people',
        'category' => 'Sales Counters',
    ],
    'inventory' => [
        'label' => 'Inventory & Catalog',
        'description' => 'Products, stock counts, GRNs, and multi-location transfers.',
        'icon' => 'bi-boxes',
        'category' => 'Back Office & Governance',
    ],
    'restaurant' => [
        'label' => 'Restaurant Suite',
        'description' => 'Dine-in orders, reservations, table management, and KDS.',
        'icon' => 'bi-cup-hot',
        'category' => 'Hospitality & Guest Ops',
    ],
    'rooms' => [
        'label' => 'Rooms & Accommodation',
        'description' => 'Room inventory, folios, invoices, and stay extensions.',
        'icon' => 'bi-building',
        'category' => 'Hospitality & Guest Ops',
    ],
    'housekeeping' => [
        'label' => 'Housekeeping Board',
        'description' => 'Room turns, inspections, and task dispatch for staff.',
        'icon' => 'bi-broom',
        'category' => 'Hospitality & Guest Ops',
    ],
    'maintenance' => [
        'label' => 'Maintenance Desk',
        'description' => 'Issue intake, technician routing, and resolution tracking.',
        'icon' => 'bi-tools',
        'category' => 'Hospitality & Guest Ops',
    ],
    'delivery' => [
        'label' => 'Delivery & Dispatch',
        'description' => 'Rider assignments, live tracking, and pricing rules.',
        'icon' => 'bi-truck',
        'category' => 'Logistics & Field Ops',
    ],
    'reports' => [
        'label' => 'Business Reports',
        'description' => 'Operational analytics, exports, and scheduled reports.',
        'icon' => 'bi-file-earmark-bar-graph',
        'category' => 'Back Office & Governance',
    ],
    'accounting' => [
        'label' => 'Accounting & Finance',
        'description' => 'Ledger-ready exports, balance sheet, P&L, and tax packs.',
        'icon' => 'bi-calculator',
        'category' => 'Back Office & Governance',
    ],
    'locations' => [
        'label' => 'Locations & Branches',
        'description' => 'Geo-aware branches, stock routing, and cash controls per site.',
        'icon' => 'bi-geo-alt',
        'category' => 'Back Office & Governance',
    ],
    'users' => [
        'label' => 'User & Access Control',
        'description' => 'Role assignments, permissions, and compliance logs.',
        'icon' => 'bi-person-gear',
        'category' => 'Admin & Compliance',
    ],
    'settings' => [
        'label' => 'System Configuration',
        'description' => 'Branding, fiscal parameters, integrations, and toggles.',
        'icon' => 'bi-gear',
        'category' => 'Admin & Compliance',
        'locked' => true,
    ],
];

foreach ($moduleCatalog as $moduleKey => $moduleMeta) {
    $isEnabled = $systemManager->isModuleEnabled($moduleKey);
    if (!empty($moduleMeta['locked']) && !$isEnabled) {
        $systemManager->setModuleEnabled($moduleKey, true);
        $isEnabled = true;
    }
    $moduleCatalog[$moduleKey]['enabled'] = $isEnabled;
}

// Registered setting fields with data types
$fieldDefinitions = [
    'business_name' => ['type' => 'string'],
    'business_address' => ['type' => 'multiline'],
    'business_phone' => ['type' => 'string'],
    'business_email' => ['type' => 'string'],
    'business_website' => ['type' => 'string'],
    'tax_rate' => ['type' => 'float'],
    'currency_code' => ['type' => 'string'],
    'currency_symbol' => ['type' => 'string'],
    'currency_position' => ['type' => 'string'],
    'decimal_places' => ['type' => 'int'],
    'decimal_separator' => ['type' => 'string'],
    'thousands_separator' => ['type' => 'string'],
    'receipt_header' => ['type' => 'string'],
    'receipt_footer' => ['type' => 'multiline'],
    'receipt_show_logo' => ['type' => 'bool'],
    'receipt_show_qr' => ['type' => 'bool'],
    'business_latitude' => ['type' => 'float'],
    'business_longitude' => ['type' => 'float'],
    'delivery_base_fee' => ['type' => 'float'],
    'delivery_per_km_rate' => ['type' => 'float'],
    'delivery_max_active_jobs' => ['type' => 'int'],
    'delivery_sla_pending_limit' => ['type' => 'int'],
    'delivery_sla_assigned_limit' => ['type' => 'int'],
    'delivery_sla_delivery_limit' => ['type' => 'int'],
    'delivery_sla_slack_minutes' => ['type' => 'int'],
    'google_maps_api_key' => ['type' => 'string'],
    'google_distance_matrix_endpoint' => ['type' => 'string'],
    'google_distance_matrix_timeout' => ['type' => 'int'],
    'delivery_cache_ttl_minutes' => ['type' => 'int'],
    'delivery_cache_soft_ttl_minutes' => ['type' => 'int'],
    'delivery_distance_fallback_provider' => ['type' => 'string'],
    'accounting_auto_lock_days' => ['type' => 'int'],
    'accounting_alert_email' => ['type' => 'string'],
    'enable_whatsapp_notifications' => ['type' => 'bool'],
    'whatsapp_access_token' => ['type' => 'string'],
    'whatsapp_phone_number_id' => ['type' => 'string'],
    'whatsapp_business_account_id' => ['type' => 'string'],
    'notification_reply_to_email' => ['type' => 'string'],
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_settings') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error_message'] = 'Invalid request. Please try again.';
            redirect($_SERVER['PHP_SELF']);
        }

        $moduleStateChanged = false;
        if ($canManageModules) {
            $enabledModulesInput = array_keys($_POST['modules'] ?? []);
            foreach ($moduleCatalog as $moduleKey => &$moduleMeta) {
                $targetEnabled = !empty($moduleMeta['locked']) ? true : in_array($moduleKey, $enabledModulesInput, true);
                if ($targetEnabled !== $moduleMeta['enabled']) {
                    $systemManager->setModuleEnabled($moduleKey, $targetEnabled);
                    $moduleMeta['enabled'] = $targetEnabled;
                    $moduleStateChanged = true;
                }
            }
            unset($moduleMeta);
        }

        $settingsToUpdate = [];
        foreach ($fieldDefinitions as $key => $meta) {
            $type = $meta['type'];

            if ($type === 'bool') {
                $settingsToUpdate[$key] = !empty($_POST[$key]) ? '1' : '0';
                continue;
            }

            if (!array_key_exists($key, $_POST)) {
                continue;
            }

            $rawValue = $_POST[$key];
            switch ($type) {
                case 'int':
                    $value = trim((string) $rawValue);
                    if ($value === '') {
                        $settingsToUpdate[$key] = '';
                        break;
                    }
                    $value = (string) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
                    $settingsToUpdate[$key] = $value;
                    break;

                case 'float':
                    $value = trim((string) $rawValue);
                    if ($value === '') {
                        $settingsToUpdate[$key] = '';
                        break;
                    }
                    $normalized = str_replace(',', '', $value);
                    if (!is_numeric($normalized)) {
                        $normalized = '0';
                    }
                    $settingsToUpdate[$key] = (string) $normalized;
                    break;

                case 'multiline':
                    $settingsToUpdate[$key] = htmlspecialchars(trim((string) $rawValue), ENT_QUOTES, 'UTF-8');
                    break;

                default:
                    $settingsToUpdate[$key] = sanitizeInput($rawValue ?? '');
                    break;
            }
        }

        if (!empty($settingsToUpdate)) {
            foreach ($settingsToUpdate as $key => $value) {
                SettingsStore::persist($key, $value);
            }
        }

        if (isset($_POST['loyalty_program_id'])) {
            try {
                $loyaltyProgramId = (int) ($_POST['loyalty_program_id'] ?? 0);
                $loyaltyPayload = [
                    'name' => trim($_POST['loyalty_name'] ?? ''),
                    'description' => trim($_POST['loyalty_description'] ?? ''),
                    'points_per_dollar' => (float) ($_POST['loyalty_points_per_currency'] ?? ($activeLoyaltyProgram['points_per_dollar'] ?? 1)),
                    'redemption_rate' => (float) ($_POST['loyalty_redemption_rate'] ?? ($activeLoyaltyProgram['redemption_rate'] ?? 0.01)),
                    'min_points_redemption' => (int) ($_POST['loyalty_min_points'] ?? ($activeLoyaltyProgram['min_points_redemption'] ?? 100)),
                    'is_active' => 1,
                ];

                if ($loyaltyProgramId > 0) {
                    $loyaltyService->saveProgram($loyaltyProgramId, $loyaltyPayload);
                } else {
                    $activeLoyaltyProgram = $loyaltyService->createProgram($loyaltyPayload);
                    $loyaltyProgramId = (int) ($activeLoyaltyProgram['id'] ?? 0);
                }

                $activeLoyaltyProgram = $loyaltyService->setActiveProgram($loyaltyProgramId);
                $loyaltyStats = $loyaltyService->getProgramStats($loyaltyProgramId);
            } catch (Throwable $loyaltyException) {
                $_SESSION['error_message'] = 'Failed to update loyalty settings: ' . $loyaltyException->getMessage();
            }
        }

        $backupConfigPosted = [
            'frequency' => sanitizeInput($_POST['backup_frequency'] ?? ''),
            'time' => sanitizeInput($_POST['backup_time_of_day'] ?? ''),
            'weekday' => sanitizeInput($_POST['backup_weekday'] ?? ''),
            'retention_days' => $_POST['backup_retention_days'] ?? '',
            'storage_path' => trim((string)($_POST['backup_storage_path'] ?? '')),
        ];

        $backupFieldsPresent = array_filter($backupConfigPosted, static function ($value, $key) {
            if ($key === 'retention_days') {
                return $value !== '';
            }
            return $value !== '';
        }, ARRAY_FILTER_USE_BOTH);

        if (!empty($backupFieldsPresent)) {
            $normalizedConfig = [
                'frequency' => in_array($backupConfigPosted['frequency'], ['hourly', 'daily', 'weekly'], true) ? $backupConfigPosted['frequency'] : ($backupConfig['frequency'] ?? 'daily'),
                'time' => preg_match('/^\d{2}:\d{2}$/', $backupConfigPosted['time']) ? $backupConfigPosted['time'] : ($backupConfig['time'] ?? '02:00'),
                'weekday' => in_array(strtolower($backupConfigPosted['weekday']), ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'], true)
                    ? strtolower($backupConfigPosted['weekday'])
                    : ($backupConfig['weekday'] ?? 'monday'),
                'retention_days' => max(1, (int)$backupConfigPosted['retention_days'] ?: (int)($backupConfig['retention_days'] ?? 30)),
                'storage_path' => $backupConfigPosted['storage_path'] !== '' ? $backupConfigPosted['storage_path'] : ($backupConfig['storage_path'] ?? ROOT_PATH . '/backups'),
            ];

            $backupService->persistConfig($normalizedConfig);
            $scheduledTaskService->upsertBackupTask($normalizedConfig);
            $backupConfig = $normalizedConfig;
            $backupLogs = $backupService->listBackups(25);
        }

        if ($moduleStateChanged) {
            $systemManager->forceRefresh();
        }

        SettingsStore::refresh();
        $_SESSION['success_message'] = 'Settings updated successfully';
        redirect($_SERVER['PHP_SELF']);
    }
}

// Get current settings (after currency manager neutralization)
$settings = settings();
$settings['currency_code'] = $currencyManager->getCurrencyCode();
$settings['currency_symbol'] = $currencyManager->getCurrencySymbol();
$settings['currency_name'] = $currencyManager->getCurrencyName();

$pageTitle = 'System Settings';
include 'includes/header.php';

$moduleGroups = [];
foreach ($moduleCatalog as $key => $moduleMeta) {
    $category = $moduleMeta['category'] ?? 'Other Modules';
    if (!isset($moduleGroups[$category])) {
        $moduleGroups[$category] = [];
    }
    $moduleGroups[$category][$key] = $moduleMeta;
}

$sections = [
    'client_modules' => [
        'title' => 'Client Modules',
        'icon' => 'bi-toggle2-on',
        'description' => 'Switch modules on/off per deployment without breaking other workflows.',
        'roles' => ['super_admin', 'developer'],
    ],
    'business_profile' => [
        'title' => 'Business Profile',
        'icon' => 'bi-shop',
        'description' => 'Core company details used across receipts, invoices, and notifications.',
        'roles' => ['admin', 'developer'],
    ],
    'accounting_tax' => [
        'title' => 'Accounting & Taxes',
        'icon' => 'bi-calculator',
        'description' => 'Financial configuration, tax percentages, and ledger preferences.',
        'roles' => ['admin', 'developer', 'accountant'],
    ],
    'receipts_branding' => [
        'title' => 'Receipts & Printing',
        'icon' => 'bi-journal-text',
        'description' => 'Customize receipt headers, footers, and printing preferences.',
        'roles' => ['admin', 'developer', 'accountant'],
    ],
    'delivery_logistics' => [
        'title' => 'Delivery & Logistics',
        'icon' => 'bi-truck',
        'description' => 'Control delivery origins, pricing defaults, and Distance Matrix behaviour.',
        'roles' => ['admin', 'developer'],
    ],
    'integrations_notifications' => [
        'title' => 'Integrations & Notifications',
        'icon' => 'bi-plug',
        'description' => 'Manage WhatsApp alerts and system-wide notification defaults.',
        'roles' => ['admin', 'developer'],
    ],
    'data_protection' => [
        'title' => 'Data Protection & Backups',
        'icon' => 'bi-shield-lock',
        'description' => 'Automate backups, manage retention, and handle data import/export workflows.',
        'roles' => ['super_admin', 'developer', 'admin', 'accountant'],
    ],
    'loyalty_rewards' => [
        'title' => 'Loyalty & Rewards',
        'icon' => 'bi-stars',
        'description' => 'Tune earn and redeem rules shared by Retail, Restaurant, and Rooms.',
        'roles' => ['admin', 'manager', 'super_admin'],
    ],
];

$visibleSections = array_filter($sections, function ($section) use ($userRole) {
    if ($userRole === 'super_admin') {
        return true;
    }
    return in_array($userRole, $section['roles'], true);
});
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-gear me-2"></i>System Settings</h4>
        <p class="text-muted mb-0">Smooth operations are our passion—fine-tune each module from one guided console.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="coa-management.php" class="btn btn-outline-secondary">
            <i class="bi bi-journal-richtext me-1"></i>Chart of Accounts
        </a>
        <button form="settingsForm" type="submit" class="btn btn-primary">
            <i class="bi bi-check2-circle me-1"></i>Save Changes
        </button>
    </div>
</div>

<div class="row">
    <div class="col-lg-3 mb-4 mb-lg-0">
        <div class="list-group shadow-sm sticky-lg-top" style="top: 6rem;">
            <?php foreach ($visibleSections as $key => $section): ?>
                <button type="button" class="list-group-item list-group-item-action settings-nav-item" data-section="<?= $key ?>">
                    <i class="bi <?= $section['icon'] ?> me-2"></i><?= $section['title'] ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-lg-9">
        <?php if (empty($visibleSections)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-lock me-2"></i>No settings are available for your role. Contact an administrator if you need access.
            </div>
        <?php else: ?>
        <form method="POST" id="settingsForm">
            <input type="hidden" name="action" value="update_settings">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <?php foreach ($visibleSections as $key => $section): ?>
            <section class="card border-0 shadow-sm mb-4 settings-section" id="<?= $key ?>">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <h5 class="card-title mb-1"><i class="bi <?= $section['icon'] ?> me-2"></i><?= $section['title'] ?></h5>
                            <p class="text-muted small mb-0"><?= $section['description'] ?></p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-section="<?= $key ?>">
                            <i class="bi bi-arrow-up"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($key === 'client_modules'): ?>
                        <?php if (empty($moduleCatalog)): ?>
                            <p class="text-muted">Module catalog unavailable.</p>
                        <?php else: ?>
                            <?php foreach ($moduleGroups as $groupName => $groupModules): ?>
                                <div class="mb-4">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="text-uppercase text-muted small fw-semibold"><?= htmlspecialchars($groupName) ?></span>
                                        <div class="ms-2 flex-grow-1 border-top border-dashed"></div>
                                    </div>
                                    <div class="row g-3">
                                        <?php foreach ($groupModules as $moduleKey => $module): ?>
                                            <div class="col-md-6">
                                                <div class="card border-0 shadow-sm h-100">
                                                    <div class="card-body d-flex align-items-start justify-content-between gap-3">
                                                        <div>
                                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                                <i class="bi <?= htmlspecialchars($module['icon'] ?? 'bi-grid') ?> text-primary"></i>
                                                                <span class="fw-semibold"><?= htmlspecialchars($module['label']) ?></span>
                                                                <?php if (!empty($module['locked'])): ?>
                                                                    <span class="badge bg-secondary">Required</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <p class="text-muted small mb-0"><?= htmlspecialchars($module['description']) ?></p>
                                                        </div>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" role="switch" name="modules[<?= htmlspecialchars($moduleKey) ?>]" value="1" id="module-<?= htmlspecialchars($moduleKey) ?>" <?= !empty($module['enabled']) ? 'checked' : '' ?> <?= !empty($module['locked']) ? 'disabled' : '' ?>>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-shield-check me-2"></i>Disabled modules fully hide their menus and routes but keep historical data safe.
                            </div>
                        <?php endif; ?>
                    <?php elseif ($key === 'business_profile'): ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Business Name *</label>
                                <input type="text" class="form-control" name="business_name" value="<?= htmlspecialchars($settings['business_name'] ?? '') ?>" required>
                                <div class="form-text">Displayed on receipts, invoices, and PDF exports.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Email</label>
                                <input type="email" class="form-control" name="business_email" value="<?= htmlspecialchars($settings['business_email'] ?? '') ?>" placeholder="support@yourbusiness.com">
                                <div class="form-text">Customers will reply here when emailing documents.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="business_phone" value="<?= htmlspecialchars($settings['business_phone'] ?? '') ?>" placeholder="+254 700 000000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Website</label>
                                <input type="url" class="form-control" name="business_website" value="<?= htmlspecialchars($settings['business_website'] ?? '') ?>" placeholder="https://example.com">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Registered Address</label>
                                <textarea class="form-control" name="business_address" rows="3" placeholder="Street, City, Country"><?= htmlspecialchars($settings['business_address'] ?? '') ?></textarea>
                                <div class="form-text">Appears on receipts and delivery notes.</div>
                            </div>
                        </div>
                    <?php elseif ($key === 'accounting_tax'): ?>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Tax Rate (%)</label>
                                <input type="number" class="form-control" step="0.01" min="0" name="tax_rate" value="<?= htmlspecialchars($settings['tax_rate'] ?? '16') ?>">
                                <div class="form-text">Default VAT applied to taxable items.</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Currency Code</label>
                                <input type="text" class="form-control" name="currency_code" value="<?= htmlspecialchars($settings['currency_code'] ?? '') ?>" placeholder="e.g., USD" maxlength="6">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Currency Symbol</label>
                                <input type="text" class="form-control" name="currency_symbol" value="<?= htmlspecialchars($settings['currency_symbol'] ?? '') ?>" placeholder="e.g., $">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Symbol Position</label>
                                <?php $currencyPosition = $settings['currency_position'] ?? 'before'; ?>
                                <select class="form-select" name="currency_position">
                                    <option value="before" <?= $currencyPosition === 'before' ? 'selected' : '' ?>>Symbol before amount (¤ 1,000.00)</option>
                                    <option value="after" <?= $currencyPosition === 'after' ? 'selected' : '' ?>>Symbol after amount (1,000.00 ¤)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Decimal Places</label>
                                <input type="number" class="form-control" name="decimal_places" min="0" max="4" value="<?= htmlspecialchars($settings['decimal_places'] ?? '2') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Decimal Separator</label>
                                <input type="text" class="form-control" name="decimal_separator" maxlength="1" value="<?= htmlspecialchars($settings['decimal_separator'] ?? '.') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Thousands Separator</label>
                                <input type="text" class="form-control" name="thousands_separator" maxlength="1" value="<?= htmlspecialchars($settings['thousands_separator'] ?? ',') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Auto-lock Period (days)</label>
                                <input type="number" class="form-control" min="0" name="accounting_auto_lock_days" value="<?= htmlspecialchars($settings['accounting_auto_lock_days'] ?? '0') ?>">
                                <div class="form-text">Automatically lock journals after the given number of days. Use 0 to disable.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Accounting Alerts Email</label>
                                <input type="email" class="form-control" name="accounting_alert_email" value="<?= htmlspecialchars($settings['accounting_alert_email'] ?? '') ?>" placeholder="finance@yourbusiness.com">
                                <div class="form-text">Period close summaries and exception alerts will be sent here.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reply-to Email for Notifications</label>
                                <input type="email" class="form-control" name="notification_reply_to_email" value="<?= htmlspecialchars($settings['notification_reply_to_email'] ?? '') ?>">
                                <div class="form-text">Used as the default reply-to address for automated mailers.</div>
                            </div>
                        </div>
                    <?php elseif ($key === 'receipts_branding'): ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Receipt Header</label>
                                <input type="text" class="form-control" name="receipt_header" value="<?= htmlspecialchars($settings['receipt_header'] ?? '') ?>" placeholder="Thank you for your business!">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Receipt Footer</label>
                                <textarea class="form-control" name="receipt_footer" rows="2" placeholder="Return policy or contact info"><?= htmlspecialchars($settings['receipt_footer'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <?php $showLogo = !empty($settings['receipt_show_logo']); ?>
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" role="switch" id="receiptLogoToggle" name="receipt_show_logo" <?= $showLogo ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="receiptLogoToggle">Show company logo on printed receipts</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <?php $showQr = !empty($settings['receipt_show_qr']); ?>
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" role="switch" id="receiptQRToggle" name="receipt_show_qr" <?= $showQr ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="receiptQRToggle">Include payment QR code / M-Pesa paybill</label>
                                </div>
                                <div class="form-text">Configure QR code content in the integrations section.</div>
                            </div>
                        </div>
                    <?php elseif ($key === 'delivery_logistics'): ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Business Latitude</label>
                                <input type="number" step="0.000001" class="form-control" name="business_latitude" value="<?= htmlspecialchars($settings['business_latitude'] ?? '') ?>" placeholder="-1.292066">
                                <div class="form-text">Origin latitude used for routing and distance estimates.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Business Longitude</label>
                                <input type="number" step="0.000001" class="form-control" name="business_longitude" value="<?= htmlspecialchars($settings['business_longitude'] ?? '') ?>" placeholder="36.821946">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Max Active Jobs per Rider</label>
                                <input type="number" min="1" class="form-control" name="delivery_max_active_jobs" value="<?= htmlspecialchars($settings['delivery_max_active_jobs'] ?? '3') ?>">
                                <div class="form-text">Dispatch will avoid assigning riders beyond this active delivery count.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Default Base Delivery Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?= htmlspecialchars($settings['currency_symbol'] ?? CurrencyManager::getInstance()->getCurrencySymbol()) ?></span>
                                    <input type="number" class="form-control" name="delivery_base_fee" step="0.01" min="0" value="<?= htmlspecialchars($settings['delivery_base_fee'] ?? '') ?>" placeholder="50">
                                </div>
                                <div class="form-text">Applies when no specific pricing rule is matched.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Per-Kilometer Rate</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?= htmlspecialchars($settings['currency_symbol'] ?? CurrencyManager::getInstance()->getCurrencySymbol()) ?></span>
                                    <input type="number" class="form-control" name="delivery_per_km_rate" step="0.01" min="0" value="<?= htmlspecialchars($settings['delivery_per_km_rate'] ?? '') ?>" placeholder="10">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">SLA Targets (minutes)</label>
                                <div class="row g-2">
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="input-group">
                                            <span class="input-group-text">Pending</span>
                                            <input type="number" min="1" class="form-control" name="delivery_sla_pending_limit" value="<?= htmlspecialchars($settings['delivery_sla_pending_limit'] ?? '15') ?>">
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="input-group">
                                            <span class="input-group-text">Assigned</span>
                                            <input type="number" min="1" class="form-control" name="delivery_sla_assigned_limit" value="<?= htmlspecialchars($settings['delivery_sla_assigned_limit'] ?? '10') ?>">
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="input-group">
                                            <span class="input-group-text">Delivery</span>
                                            <input type="number" min="1" class="form-control" name="delivery_sla_delivery_limit" value="<?= htmlspecialchars($settings['delivery_sla_delivery_limit'] ?? '45') ?>">
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="input-group">
                                            <span class="input-group-text">Slack</span>
                                            <input type="number" min="0" class="form-control" name="delivery_sla_slack_minutes" value="<?= htmlspecialchars($settings['delivery_sla_slack_minutes'] ?? '5') ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-text">Configure SLA thresholds for monitoring and risk alerts. Slack adds tolerance before triggering an alert.</div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Google Distance Matrix API Key</label>
                                <input type="text" class="form-control" name="google_maps_api_key" value="<?= htmlspecialchars($settings['google_maps_api_key'] ?? '') ?>" placeholder="AIza...">
                                <div class="form-text">Secure the key via HTTP referrer restrictions or a proxy server.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Distance Matrix Endpoint</label>
                                <input type="text" class="form-control" name="google_distance_matrix_endpoint" value="<?= htmlspecialchars($settings['google_distance_matrix_endpoint'] ?? 'https://maps.googleapis.com/maps/api/distancematrix/json') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">HTTP Timeout (s)</label>
                                <input type="number" class="form-control" name="google_distance_matrix_timeout" value="<?= htmlspecialchars($settings['google_distance_matrix_timeout'] ?? '10') ?>" min="5">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fallback Provider</label>
                                <?php $fallbackProvider = $settings['delivery_distance_fallback_provider'] ?? 'haversine'; ?>
                                <select class="form-select" name="delivery_distance_fallback_provider">
                                    <option value="haversine" <?= $fallbackProvider === 'haversine' ? 'selected' : '' ?>>Haversine Estimate</option>
                                    <option value="manual" <?= $fallbackProvider === 'manual' ? 'selected' : '' ?>>Manual / Fixed Fee</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Cache TTL (minutes)</label>
                                <input type="number" class="form-control" name="delivery_cache_ttl_minutes" min="5" value="<?= htmlspecialchars($settings['delivery_cache_ttl_minutes'] ?? '1440') ?>">
                                <div class="form-text">Distance results will automatically refresh after this time.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Soft Refresh Interval (minutes)</label>
                                <input type="number" class="form-control" name="delivery_cache_soft_ttl_minutes" min="5" value="<?= htmlspecialchars($settings['delivery_cache_soft_ttl_minutes'] ?? '180') ?>">
                            </div>
                        </div>
                    <?php elseif ($key === 'integrations_notifications'): ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <?php $enableWhatsApp = !empty($settings['enable_whatsapp_notifications']); ?>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="enableWhatsapp" name="enable_whatsapp_notifications" <?= $enableWhatsApp ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="enableWhatsapp">Enable WhatsApp notifications</label>
                                </div>
                                <div class="form-text">Requires Meta Business API credentials.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number ID</label>
                                <input type="text" class="form-control" name="whatsapp_phone_number_id" value="<?= htmlspecialchars($settings['whatsapp_phone_number_id'] ?? '') ?>" placeholder="1234567890">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Access Token</label>
                                <input type="text" class="form-control" name="whatsapp_access_token" value="<?= htmlspecialchars($settings['whatsapp_access_token'] ?? '') ?>" placeholder="EAA...">
                                <div class="form-text">Store securely—tokens expire periodically.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Business Account ID</label>
                                <input type="text" class="form-control" name="whatsapp_business_account_id" value="<?= htmlspecialchars($settings['whatsapp_business_account_id'] ?? '') ?>" placeholder="123456">
                            </div>
                        </div>
                    <?php elseif ($key === 'data_protection'): ?>
                        <div class="row g-4">
                            <div class="col-lg-5">
                                <div class="border rounded-3 p-3 h-100">
                                    <h6 class="fw-semibold mb-3">Automated Backup Schedule</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Frequency</label>
                                        <?php $frequency = $backupConfig['frequency'] ?? 'daily'; ?>
                                        <select class="form-select" name="backup_frequency" id="backupFrequency">
                                            <option value="hourly" <?= $frequency === 'hourly' ? 'selected' : '' ?>>Hourly</option>
                                            <option value="daily" <?= $frequency === 'daily' ? 'selected' : '' ?>>Daily</option>
                                            <option value="weekly" <?= $frequency === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Run Time</label>
                                        <input type="time" class="form-control" name="backup_time_of_day" value="<?= htmlspecialchars($backupConfig['time'] ?? '02:00') ?>" required>
                                        <div class="form-text">24-hour format, server time.</div>
                                    </div>
                                    <div class="mb-3" id="backupWeekdayWrapper">
                                        <label class="form-label">Weekly Run Day</label>
                                        <?php $weekday = $backupConfig['weekday'] ?? 'monday'; ?>
                                        <select class="form-select" name="backup_weekday">
                                            <?php foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day): ?>
                                                <option value="<?= $day ?>" <?= $day === $weekday ? 'selected' : '' ?>><?= ucfirst($day) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Used when frequency is set to weekly.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Retention (days)</label>
                                        <input type="number" class="form-control" name="backup_retention_days" min="1" value="<?= (int)($backupConfig['retention_days'] ?? 30) ?>">
                                        <div class="form-text">Older backups beyond this window will be auto-deleted.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Storage Path</label>
                                        <input type="text" class="form-control" name="backup_storage_path" value="<?= htmlspecialchars($backupConfig['storage_path'] ?? ROOT_PATH . '/backups') ?>" placeholder="D:/WAPOS/backups">
                                        <div class="form-text">Use an absolute path to an accessible drive or mounted network share.</div>
                                    </div>
                                    <div class="alert alert-warning mb-0 small">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Ensure the web server user has permission to write to the selected directory.
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="border rounded-3 p-3 h-100 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                                        <div>
                                            <h6 class="fw-semibold mb-1">Manual Backups & History</h6>
                                            <p class="text-muted small mb-0">Generate on-demand backups or download previous archives.</p>
                                        </div>
                                        <button class="btn btn-primary" type="button" id="runBackupBtn">
                                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                                            <i class="bi bi-hdd-stack me-1"></i>Run Backup Now
                                        </button>
                                    </div>
                                    <div id="backupStatusAlert" class="alert d-none" role="alert"></div>
                                    <div class="table-responsive flex-grow-1">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th scope="col">Created</th>
                                                    <th scope="col">Type</th>
                                                    <th scope="col">Size</th>
                                                    <th scope="col">Status</th>
                                                    <th scope="col">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($backupLogs)): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-4">No backups have been recorded yet.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($backupLogs as $log): ?>
                                                        <?php
                                                            $badgeClass = [
                                                                'success' => 'success',
                                                                'failed' => 'danger',
                                                                'running' => 'warning'
                                                            ][$log['status']] ?? 'secondary';
                                                            $sizeMb = $log['backup_size'] ? round($log['backup_size'] / (1024 * 1024), 2) : 0;
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <div class="fw-semibold"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($log['created_at'] ?? ''))) ?></div>
                                                                <div class="text-muted small">Retention: <?= (int)$log['retention_days'] ?> days</div>
                                                            </td>
                                                            <td class="text-capitalize"><?= htmlspecialchars($log['backup_type'] ?? 'manual') ?></td>
                                                            <td><?= $sizeMb > 0 ? $sizeMb . ' MB' : '—' ?></td>
                                                            <td>
                                                                <span class="badge bg-<?= $badgeClass ?>"><?= ucfirst($log['status']) ?></span>
                                                                <?php if (!empty($log['message'])): ?>
                                                                    <div class="small text-muted"><?= htmlspecialchars($log['message']) ?></div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-nowrap">
                                                                <a class="btn btn-outline-secondary btn-sm" href="api/system-backup.php?action=download&id=<?= (int)$log['id'] ?>" title="Download">
                                                                    <i class="bi bi-download"></i>
                                                                </a>
                                                                <button type="button" class="btn btn-outline-danger btn-sm ms-1 backup-delete-btn" data-backup-id="<?= (int)$log['id'] ?>" title="Delete">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="border rounded-3 p-3">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                                        <div>
                                            <h6 class="fw-semibold mb-1">Data Import & Export</h6>
                                            <p class="text-muted small mb-0">Download templates, export live data, or import bulk updates for supported entities.</p>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="bi bi-shield-lock me-1"></i>Access limited to admin, accountant, developer, super admin.
                                        </div>
                                    </div>
                                    <div class="row g-4 align-items-center">
                                        <div class="col-lg-4">
                                            <div class="bg-light border rounded-3 p-3 h-100">
                                                <h6 class="fw-semibold mb-2">Entity Overview</h6>
                                                <ul class="list-unstyled small mb-0">
                                                    <?php foreach ($dataPortEntities as $entityKey => $entityMeta): ?>
                                                        <li class="mb-1">
                                                            <strong><?= htmlspecialchars($entityMeta['label']) ?></strong>
                                                            <br>
                                                            <span class="text-muted">Columns: <?= count($entityMeta['columns']) ?> &bull; Key: <?= htmlspecialchars(strtoupper(implode('/', (array)($entityMeta['match_on'] ?? [])))) ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="col-lg-8">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Data Entity</label>
                                                    <select class="form-select" id="dataPortEntity">
                                                        <?php foreach ($dataPortEntities as $entityKey => $entityMeta): ?>
                                                            <option value="<?= htmlspecialchars($entityKey) ?>" <?= $entityKey === $defaultDataPortEntity ? 'selected' : '' ?>><?= htmlspecialchars($entityMeta['label']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 d-flex justify-content-md-end align-items-end gap-2">
                                                    <button type="button" class="btn btn-outline-secondary w-100 w-md-auto" id="downloadTemplateBtn">
                                                        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Template
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary w-100 w-md-auto" id="exportDataBtn">
                                                        <i class="bi bi-download me-1"></i>Export
                                                    </button>
                                                </div>
                                                <div class="col-12">
                                                    <form id="dataImportForm" class="row g-3" enctype="multipart/form-data" novalidate>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Upload CSV</label>
                                                            <input type="file" class="form-control" id="dataImportFile" accept=".csv,text/csv">
                                                            <div class="form-text">Use UTF-8 CSV with headers from the template.</div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">Mode</label>
                                                            <select class="form-select" id="dataImportMode">
                                                                <option value="validate">Validate Only</option>
                                                                <option value="import">Validate &amp; Import</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-3 d-flex align-items-end">
                                                            <button type="submit" class="btn btn-success w-100" id="dataImportSubmit">
                                                                <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                                                                <i class="bi bi-upload me-1"></i>Process File
                                                            </button>
                                                        </div>
                                                    </form>
                                                    <div id="dataImportStatus" class="alert d-none mt-3" role="alert"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($key === 'loyalty_rewards'): ?>
                        <?php if (!$activeLoyaltyProgram): ?>
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>No loyalty program found. Save the form to create the default program automatically.
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="loyalty_program_id" value="<?= (int) $activeLoyaltyProgram['id'] ?>">
                            <div class="row g-3 align-items-stretch">
                                <div class="col-md-7">
                                    <div class="mb-3">
                                        <label class="form-label">Program Name</label>
                                        <input type="text" class="form-control" name="loyalty_name" value="<?= htmlspecialchars($activeLoyaltyProgram['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Program Description</label>
                                        <textarea class="form-control" name="loyalty_description" rows="2" placeholder="Visible to staff when linking loyalty customers."><?= htmlspecialchars($activeLoyaltyProgram['description'] ?? '') ?></textarea>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Points per <?= htmlspecialchars($settings['currency_code'] ?? 'unit') ?></label>
                                            <input type="number" step="0.01" min="0" class="form-control" name="loyalty_points_per_currency" value="<?= htmlspecialchars($activeLoyaltyProgram['points_per_dollar'] ?? 1) ?>" required>
                                            <div class="form-text">How many points a customer earns per currency spent.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Redemption Rate (value per point)</label>
                                            <input type="number" step="0.001" min="0" class="form-control" name="loyalty_redemption_rate" value="<?= htmlspecialchars($activeLoyaltyProgram['redemption_rate'] ?? 0.01) ?>" required>
                                            <div class="form-text">Monetary value granted when redeeming one point.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Minimum Points to Redeem</label>
                                            <input type="number" min="0" step="1" class="form-control" name="loyalty_min_points" value="<?= htmlspecialchars($activeLoyaltyProgram['min_points_redemption'] ?? 100) ?>" required>
                                            <div class="form-text">Prevents redeeming very small balances.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body">
                                            <h6 class="mb-3">Program Snapshot</h6>
                                            <?php if ($loyaltyStats): ?>
                                                <div class="d-flex flex-column gap-3">
                                                    <div>
                                                        <span class="text-muted small text-uppercase">Members</span>
                                                        <div class="fs-4 fw-semibold"><?= number_format($loyaltyStats['members'] ?? 0) ?></div>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <span class="text-muted small">Points Earned</span>
                                                            <div class="fw-semibold text-primary"><?= number_format($loyaltyStats['points_earned'] ?? 0) ?></div>
                                                        </div>
                                                        <div>
                                                            <span class="text-muted small">Redeemed</span>
                                                            <div class="fw-semibold text-success"><?= number_format($loyaltyStats['points_redeemed'] ?? 0) ?></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-muted mb-0">Loyalty usage data will appear after the first transaction.</p>
                                            <?php endif; ?>
                                            <hr>
                                            <a href="loyalty-programs.php" class="btn btn-outline-primary w-100">
                                                <i class="bi bi-box-arrow-up-right me-1"></i>Open Loyalty Console
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>
            <?php endforeach; ?>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2-circle me-1"></i>Save Changes
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.settings-nav-item').forEach((navButton) => {
        navButton.addEventListener('click', () => {
            const targetId = navButton.getAttribute('data-section');
            const section = document.getElementById(targetId);
            if (!section) {
                return;
            }
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    const sectionElements = Array.from(document.querySelectorAll('.settings-section'));
    const navItems = Array.from(document.querySelectorAll('.settings-nav-item'));

    function updateActiveNav() {
        const scrollPosition = window.scrollY + 120;
        let activeId = null;
        sectionElements.forEach((section) => {
            if (section.offsetTop <= scrollPosition) {
                activeId = section.id;
            }
        });

        navItems.forEach((item) => {
            if (item.getAttribute('data-section') === activeId) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    }

    updateActiveNav();
    window.addEventListener('scroll', updateActiveNav);

    const backupFrequencySelect = document.getElementById('backupFrequency');
    const backupWeekdayWrapper = document.getElementById('backupWeekdayWrapper');
    if (backupFrequencySelect && backupWeekdayWrapper) {
        const toggleWeekdayVisibility = () => {
            if (backupFrequencySelect.value === 'weekly') {
                backupWeekdayWrapper.classList.remove('d-none');
            } else {
                backupWeekdayWrapper.classList.add('d-none');
            }
        };
        toggleWeekdayVisibility();
        backupFrequencySelect.addEventListener('change', toggleWeekdayVisibility);
    }

    const csrfToken = '<?= htmlspecialchars($csrfToken) ?>';
    const runBackupBtn = document.getElementById('runBackupBtn');
    const backupStatusAlert = document.getElementById('backupStatusAlert');
    const backupDeleteButtons = document.querySelectorAll('.backup-delete-btn');
    const dataPortEntitySelect = document.getElementById('dataPortEntity');
    const downloadTemplateBtn = document.getElementById('downloadTemplateBtn');
    const exportDataBtn = document.getElementById('exportDataBtn');
    const dataImportForm = document.getElementById('dataImportForm');
    const dataImportFile = document.getElementById('dataImportFile');
    const dataImportMode = document.getElementById('dataImportMode');
    const dataImportSubmit = document.getElementById('dataImportSubmit');
    const dataImportStatus = document.getElementById('dataImportStatus');

    function setAlert(message, variant = 'success') {
        if (!backupStatusAlert) {
            return;
        }
        backupStatusAlert.className = `alert alert-${variant}`;
        backupStatusAlert.textContent = message;
        backupStatusAlert.classList.remove('d-none');
    }

    async function runManualBackup() {
        if (!runBackupBtn) {
            return;
        }

        const spinner = runBackupBtn.querySelector('.spinner-border');
        if (spinner) {
            spinner.classList.remove('d-none');
        }
        runBackupBtn.disabled = true;
        backupStatusAlert?.classList.add('d-none');

        try {
            const response = await fetch('api/system-backup.php?action=run', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                }),
            });

            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Backup failed');
            }

            setAlert(payload.message || 'Backup completed successfully');
            setTimeout(() => window.location.reload(), 1500);
        } catch (error) {
            setAlert(error.message || 'Unable to complete backup', 'danger');
        } finally {
            if (spinner) {
                spinner.classList.add('d-none');
            }
            runBackupBtn.disabled = false;
        }
    }

    async function deleteBackup(id, button) {
        if (!id || !confirm('Delete this backup permanently?')) {
            return;
        }

        button.disabled = true;
        try {
            const response = await fetch('api/system-backup.php?action=delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id,
                    csrf_token: csrfToken,
                }),
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Failed to delete backup');
            }
            setAlert(payload.message || 'Backup deleted');
            setTimeout(() => window.location.reload(), 1000);
        } catch (error) {
            setAlert(error.message || 'Unable to delete backup', 'danger');
            button.disabled = false;
        }
    }

    runBackupBtn?.addEventListener('click', runManualBackup);
    backupDeleteButtons.forEach((btn) => {
        btn.addEventListener('click', () => deleteBackup(parseInt(btn.dataset.backupId || '0', 10), btn));
    });

    const getSelectedEntity = () => dataPortEntitySelect?.value || '';

    downloadTemplateBtn?.addEventListener('click', () => {
        const entity = getSelectedEntity();
        if (!entity) {
            alert('Select an entity first.');
            return;
        }
        window.open(`api/data-port.php?action=template&entity=${encodeURIComponent(entity)}`);
    });

    exportDataBtn?.addEventListener('click', () => {
        const entity = getSelectedEntity();
        if (!entity) {
            alert('Select an entity first.');
            return;
        }
        window.open(`api/data-port.php?action=export&entity=${encodeURIComponent(entity)}`);
    });

    const setImportAlert = (message, variant = 'success') => {
        if (!dataImportStatus) {
            return;
        }
        dataImportStatus.className = `alert alert-${variant}`;
        dataImportStatus.textContent = message;
        dataImportStatus.classList.remove('d-none');
    };

    dataImportForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const entity = getSelectedEntity();
        if (!entity) {
            setImportAlert('Select an entity to process.', 'danger');
            return;
        }
        if (!dataImportFile?.files?.length) {
            setImportAlert('Choose a CSV file to upload.', 'danger');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'import');
        formData.append('entity', entity);
        formData.append('mode', dataImportMode?.value || 'validate');
        formData.append('csrf_token', csrfToken);
        formData.append('file', dataImportFile.files[0]);

        const spinner = dataImportSubmit?.querySelector('.spinner-border');
        spinner?.classList.remove('d-none');
        if (dataImportSubmit) {
            dataImportSubmit.disabled = true;
        }
        dataImportStatus?.classList.add('d-none');

        try {
            const response = await fetch('api/data-port.php', {
                method: 'POST',
                body: formData,
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Import failed');
            }
            const summary = payload.message || `Completed. Inserted ${payload.inserted || 0}, updated ${payload.updated || 0}.`;
            setImportAlert(summary, 'success');
        } catch (error) {
            setImportAlert(error.message || 'Unable to process file', 'danger');
        } finally {
            spinner?.classList.add('d-none');
            if (dataImportSubmit) {
                dataImportSubmit.disabled = false;
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
