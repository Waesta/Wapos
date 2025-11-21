<?php
require_once __DIR__ . '/includes/bootstrap.php';

$auth->requireLogin();
$user = $auth->getUser();
$role = strtolower($user['role'] ?? '');

if (!in_array($role, ['admin', 'developer'], true)) {
    redirect('index.php');
}

$pageTitle = 'Delivery Pricing Management';
$csrfToken = generateCSRFToken();

$db = Database::getInstance();
$currencySymbol = getCurrencySymbol();

$settingsKeys = [
    'delivery_base_fee',
    'delivery_per_km_rate',
    'delivery_cache_ttl_minutes',
    'delivery_cache_soft_ttl_minutes',
    'delivery_distance_fallback_provider',
    'business_latitude',
    'business_longitude',
];
$placeholders = implode(',', array_fill(0, count($settingsKeys), '?'));
$settingsRows = $db->fetchAll(
    "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)",
    $settingsKeys
);
$pricingSettings = [];
foreach ($settingsRows as $row) {
    $pricingSettings[$row['setting_key']] = $row['setting_value'];
}

$defaultBaseFee = isset($pricingSettings['delivery_base_fee']) ? (float)$pricingSettings['delivery_base_fee'] : null;
$defaultPerKm = isset($pricingSettings['delivery_per_km_rate']) ? (float)$pricingSettings['delivery_per_km_rate'] : null;
$cacheTtl = isset($pricingSettings['delivery_cache_ttl_minutes']) ? (int)$pricingSettings['delivery_cache_ttl_minutes'] : null;
$cacheSoftTtl = isset($pricingSettings['delivery_cache_soft_ttl_minutes']) ? (int)$pricingSettings['delivery_cache_soft_ttl_minutes'] : null;
$fallbackProvider = $pricingSettings['delivery_distance_fallback_provider'] ?? 'haversine';

$originLat = $pricingSettings['business_latitude'] ?? null;
$originLng = $pricingSettings['business_longitude'] ?? null;
if (!$originLat || !$originLng) {
    $locationFallback = $db->fetchOne('SELECT latitude, longitude FROM locations WHERE is_active = 1 ORDER BY id LIMIT 1');
    if ($locationFallback && !empty($locationFallback['latitude']) && !empty($locationFallback['longitude'])) {
        $originLat = $originLat ?: $locationFallback['latitude'];
        $originLng = $originLng ?: $locationFallback['longitude'];
    }
}

$pricingConfig = [
    'defaults' => [
        'base_fee' => $defaultBaseFee,
        'per_km_fee' => $defaultPerKm,
    ],
    'cache' => [
        'ttl' => $cacheTtl,
        'soft_ttl' => $cacheSoftTtl,
        'fallback_provider' => $fallbackProvider,
    ],
    'origin' => [
        'lat' => $originLat,
        'lng' => $originLng,
    ],
];

$defaultBaseFeeLabel = $defaultBaseFee !== null
    ? $currencySymbol . number_format($defaultBaseFee, 2)
    : 'Not configured';
$defaultPerKmLabel = $defaultPerKm !== null
    ? $currencySymbol . number_format($defaultPerKm, 2) . ' / km'
    : 'Not configured';
$cacheTtlLabel = $cacheTtl !== null
    ? number_format($cacheTtl) . ' min'
    : 'Default (1440 min)';
$cacheSoftTtlLabel = $cacheSoftTtl !== null
    ? number_format($cacheSoftTtl) . ' min'
    : 'Default (180 min)';
$fallbackProviderLabel = ucwords(str_replace('_', ' ', $fallbackProvider));
$originLabel = ($originLat && $originLng)
    ? sprintf('%.5f, %.5f', (float)$originLat, (float)$originLng)
    : 'Not configured';
$originSource = ($pricingSettings['business_latitude'] ?? null) && ($pricingSettings['business_longitude'] ?? null)
    ? 'Business settings'
    : ($originLat && $originLng ? 'Active location fallback' : 'Not set');

include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">Delivery Pricing Management</h2>
            <p class="text-muted mb-0">Configure dynamic pricing rules, monitor API usage, and manage cache.</p>
        </div>
        <div class="btn-group">
            <button id="refreshAllBtn" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-repeat me-1"></i>Refresh
            </button>
            <button id="purgeCacheBtn" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash3 me-1"></i>Purge Cache
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-3">Default Pricing</h6>
                    <p class="mb-1"><strong>Base fee:</strong> <?= htmlspecialchars($defaultBaseFeeLabel) ?></p>
                    <p class="mb-1"><strong>Per km:</strong> <?= htmlspecialchars($defaultPerKmLabel) ?></p>
                    <p class="mb-0 text-muted small">These values prefill new pricing rules and act as fallbacks when no distance band matches.</p>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-3">Cache Strategy</h6>
                    <p class="mb-1"><strong>Hard TTL:</strong> <?= htmlspecialchars($cacheTtlLabel) ?></p>
                    <p class="mb-1"><strong>Soft TTL:</strong> <?= htmlspecialchars($cacheSoftTtlLabel) ?></p>
                    <p class="mb-0"><strong>Fallback:</strong> <span class="badge bg-light text-dark"><?= htmlspecialchars($fallbackProviderLabel) ?></span></p>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-12">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-3">Origin Coordinates</h6>
                    <p class="mb-1"><strong>Lat / Lng:</strong> <?= htmlspecialchars($originLabel) ?></p>
                    <p class="mb-0 text-muted small">Source: <?= htmlspecialchars($originSource) ?> · Used when resolving live distance metrics.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">Create / Update Rule</h5>
                    <form id="ruleForm" class="row g-3">
                        <input type="hidden" id="ruleId">
                        <div class="col-12">
                            <label for="ruleName" class="form-label">Rule Name</label>
                            <input type="text" class="form-control" id="ruleName" placeholder="e.g. 0 - 5 km Core City" required>
                        </div>
                        <div class="col-6">
                            <label for="priority" class="form-label">Priority</label>
                            <input type="number" min="1" class="form-control" id="priority" value="1" required>
                            <div class="form-text">Lower runs first.</div>
                        </div>
                        <div class="col-6">
                            <label for="isActive" class="form-label">Status</label>
                            <select id="isActive" class="form-select">
                                <option value="1" selected>Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="distanceMin" class="form-label">Min Distance (km)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="distanceMin" value="0.00" required>
                        </div>
                        <div class="col-6">
                            <label for="distanceMax" class="form-label">Max Distance (km)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="distanceMax" placeholder="Leave blank for no limit">
                        </div>
                        <div class="col-6">
                            <label for="baseFee" class="form-label">Base Fee</label>
                            <input type="number" step="0.01" min="0" inputmode="decimal" class="form-control" id="baseFee" value="<?= $defaultBaseFee !== null ? htmlspecialchars(number_format($defaultBaseFee, 2, '.', '')) : '0.00' ?>" placeholder="<?= htmlspecialchars(($currencySymbol ?: '') . ' e.g. 80.00') ?>" required>
                            <?php if ($defaultBaseFee !== null): ?>
                            <div class="form-text">System default: <?= htmlspecialchars($defaultBaseFeeLabel) ?></div>
                            <?php else: ?>
                            <div class="form-text text-muted">No default configured; falls back to 0.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-6">
                            <label for="perKmFee" class="form-label">Per Km Fee</label>
                            <input type="number" step="0.01" min="0" inputmode="decimal" class="form-control" id="perKmFee" value="<?= $defaultPerKm !== null ? htmlspecialchars(number_format($defaultPerKm, 2, '.', '')) : '0.00' ?>" placeholder="<?= htmlspecialchars(($currencySymbol ?: '') . ' e.g. 15.00') ?>" required>
                            <?php if ($defaultPerKm !== null): ?>
                            <div class="form-text">System default: <?= htmlspecialchars($defaultPerKmLabel) ?></div>
                            <?php else: ?>
                            <div class="form-text text-muted">No default configured; falls back to 0 per km.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label for="surchargePercent" class="form-label">Surcharge (%)</label>
                            <input type="number" step="0.1" min="0" inputmode="decimal" class="form-control" id="surchargePercent" value="0.00">
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea id="notes" class="form-control" rows="2" placeholder="Optional context"></textarea>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1" id="saveRuleBtn">
                                <i class="bi bi-save me-1"></i><span id="saveRuleBtnText">Save Rule</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="resetFormBtn">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-danger d-none" id="ruleFormError"></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">Pricing Rules</h5>
                        <span class="badge bg-light text-dark" id="rulesCount">0 rules</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm align-middle mb-0" id="rulesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th class="text-center">Priority</th>
                                    <th class="text-center">Distance Range</th>
                                    <th class="text-end">Base</th>
                                    <th class="text-end">Per Km</th>
                                    <th class="text-end">Surcharge</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Loading rules…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">Usage &amp; Metrics</h5>
                        <small class="text-muted" id="metricsTimestamp"></small>
                    </div>
                    <div class="row g-3" id="metricsCards">
                        <div class="col-md-4">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <h6 class="text-muted">Total Requests</h6>
                                    <p class="fs-4 fw-semibold" id="metricTotalRequests">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 h-100" style="background: #e7f5ff;">
                                <div class="card-body">
                                    <h6 class="text-muted">Cache Hit Rate</h6>
                                    <p class="fs-4 fw-semibold" id="metricCacheRate">0%</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <h6 class="text-muted">Fallback Calls</h6>
                                    <p class="fs-4 fw-semibold" id="metricFallbackCalls">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <h6 class="text-muted">Average Distance</h6>
                                    <p class="fs-4 fw-semibold" id="metricAvgDistance">0 km</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 h-100" style="background: #f8f0ff;">
                                <div class="card-body">
                                    <h6 class="text-muted">Average Fee</h6>
                                    <p class="fs-4 fw-semibold" id="metricAvgFee">0.00</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <h6 class="text-muted">Cache Entries</h6>
                                    <p class="fs-4 fw-semibold" id="metricCacheEntries">0</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <h6>Top Rule Usage</h6>
                            <ul class="list-group list-group-flush" id="ruleUsageList">
                                <li class="list-group-item text-muted">Loading…</li>
                            </ul>
                        </div>
                        <div class="col-lg-6">
                            <h6>Recent Requests</h6>
                            <div class="table-responsive" style="max-height: 240px;">
                                <table class="table table-sm table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Provider</th>
                                            <th class="text-center">Cache</th>
                                            <th class="text-center">Fallback</th>
                                            <th class="text-end">Fee</th>
                                            <th class="text-end">Distance</th>
                                            <th>When</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recentRequestsBody">
                                        <tr><td colspan="6" class="text-muted text-center py-3">Loading…</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const pricingConfig = <?= json_encode($pricingConfig) ?>;
const csrfToken = <?= json_encode($csrfToken) ?>;
let rules = [];
let isSavingRule = false;

const apiEndpoint = 'api/delivery-pricing.php';

const selectors = {
    rulesTableBody: () => document.querySelector('#rulesTable tbody'),
    rulesCount: () => document.getElementById('rulesCount'),
    metricsTimestamp: () => document.getElementById('metricsTimestamp'),
    ruleForm: () => document.getElementById('ruleForm'),
    saveRuleBtn: () => document.getElementById('saveRuleBtn'),
    saveRuleBtnText: () => document.getElementById('saveRuleBtnText'),
    resetFormBtn: () => document.getElementById('resetFormBtn'),
    ruleFormError: () => document.getElementById('ruleFormError'),
    metricTotalRequests: () => document.getElementById('metricTotalRequests'),
    metricCacheRate: () => document.getElementById('metricCacheRate'),
    metricFallbackCalls: () => document.getElementById('metricFallbackCalls'),
    metricAvgDistance: () => document.getElementById('metricAvgDistance'),
    metricAvgFee: () => document.getElementById('metricAvgFee'),
    metricCacheEntries: () => document.getElementById('metricCacheEntries'),
    ruleUsageList: () => document.getElementById('ruleUsageList'),
    recentRequestsBody: () => document.getElementById('recentRequestsBody'),
    refreshAllBtn: () => document.getElementById('refreshAllBtn'),
    purgeCacheBtn: () => document.getElementById('purgeCacheBtn'),
};

document.addEventListener('DOMContentLoaded', () => {
    const form = selectors.ruleForm();
    form.addEventListener('submit', handleSaveRule);

    selectors.resetFormBtn().addEventListener('click', resetRuleForm);
    selectors.refreshAllBtn().addEventListener('click', () => {
        loadRules();
        loadMetrics();
    });
    selectors.purgeCacheBtn().addEventListener('click', handlePurgeCache);

    resetRuleForm();
    loadRules();
    loadMetrics();
});

function handleSaveRule(event) {
    event.preventDefault();
    if (isSavingRule) return;

    const form = selectors.ruleForm();
    clearFormError();

    const payload = {
        action: 'save_rule',
        csrf_token: csrfToken,
        id: valueOrNull('ruleId'),
        rule_name: form.ruleName.value.trim(),
        priority: form.priority.value,
        distance_min_km: form.distanceMin.value,
        distance_max_km: form.distanceMax.value,
        base_fee: form.baseFee.value,
        per_km_fee: form.perKmFee.value,
        surcharge_percent: form.surchargePercent.value,
        notes: form.notes.value,
        is_active: form.isActive.value,
    };

    if (!payload.rule_name) {
        showFormError('Rule name is required.');
        return;
    }

    const overlapError = validateRangeOverlap(payload);
    if (overlapError) {
        showFormError(overlapError);
        return;
    }

    toggleSaveState(true);

    fetch(apiEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    })
        .then(checkResponse)
        .then((result) => {
            if (!result.success) {
                throw new Error(result.message || 'Failed to save rule');
            }
            loadRules().then(() => {
                resetRuleForm();
                showToast('Rule saved successfully.', 'success');
            });
        })
        .catch((error) => {
            console.error('Save rule error:', error);
            showFormError(error.message || 'Unable to save rule.');
        })
        .finally(() => toggleSaveState(false));
}

function loadRules() {
    const tbody = selectors.rulesTableBody();
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading rules…</td></tr>';

    return fetch(apiEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'list_rules', csrf_token: csrfToken }),
    })
        .then(checkResponse)
        .then((result) => {
            if (!result.success) {
                throw new Error(result.message || 'Failed to fetch rules');
            }
            rules = result.rules || [];
            renderRules();
        })
        .catch((error) => {
            console.error('Load rules error:', error);
            tbody.innerHTML = `<tr><td colspan="8" class="text-danger text-center py-4">${escapeHtml(error.message || 'Unable to load rules.')}</td></tr>`;
        });
}

function renderRules() {
    const tbody = selectors.rulesTableBody();
    selectors.rulesCount().textContent = `${rules.length} ${rules.length === 1 ? 'rule' : 'rules'}`;

    if (!rules.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No pricing rules configured.</td></tr>';
        return;
    }

    tbody.innerHTML = rules.map((rule) => {
        const distanceMax = rule.distance_max_km !== null && rule.distance_max_km !== ''
            ? Number(rule.distance_max_km).toFixed(2)
            : '∞';
        const statusBadge = rule.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';

        return `
            <tr>
                <td>
                    <strong>${escapeHtml(rule.rule_name)}</strong>
                    ${rule.notes ? `<br><small class="text-muted">${escapeHtml(rule.notes)}</small>` : ''}
                </td>
                <td class="text-center">${Number(rule.priority)}</td>
                <td class="text-center">${Number(rule.distance_min_km).toFixed(2)} – ${distanceMax} km</td>
                <td class="text-end">${Number(rule.base_fee).toFixed(2)}</td>
                <td class="text-end">${Number(rule.per_km_fee).toFixed(2)}</td>
                <td class="text-end">${Number(rule.surcharge_percent).toFixed(2)}%</td>
                <td>${statusBadge}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editRule(${rule.id})"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteRule(${rule.id})"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
        `;
    }).join('');
}

function editRule(id) {
    const rule = rules.find((r) => Number(r.id) === Number(id));
    if (!rule) return;

    const form = selectors.ruleForm();
    form.ruleId.value = rule.id;
    form.ruleName.value = rule.rule_name;
    form.priority.value = rule.priority;
    form.isActive.value = rule.is_active;
    form.distanceMin.value = Number(rule.distance_min_km).toFixed(2);
    form.distanceMax.value = rule.distance_max_km !== null ? Number(rule.distance_max_km).toFixed(2) : '';
    form.baseFee.value = Number(rule.base_fee).toFixed(2);
    form.perKmFee.value = Number(rule.per_km_fee).toFixed(2);
    form.surchargePercent.value = Number(rule.surcharge_percent).toFixed(2);
    form.notes.value = rule.notes || '';
    selectors.saveRuleBtnText().textContent = 'Update Rule';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function deleteRule(id) {
    if (!confirm('Delete this pricing rule? This cannot be undone.')) {
        return;
    }

    fetch(apiEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'delete_rule', csrf_token: csrfToken, id }),
    })
        .then(checkResponse)
        .then((result) => {
            if (!result.success) {
                throw new Error(result.message || 'Failed to delete rule');
            }
            loadRules();
        })
        .catch((error) => {
            console.error('Delete rule error:', error);
            alert(error.message || 'Unable to delete rule.');
        });
}

function resetRuleForm() {
    const form = selectors.ruleForm();
    form.reset();
    form.ruleId.value = '';
    form.priority.value = '1';
    form.isActive.value = '1';
    form.distanceMin.value = formatMoneyInput(0);
    form.distanceMax.value = '';
    form.baseFee.value = formatMoneyInput(pricingConfig?.defaults?.base_fee ?? null);
    form.perKmFee.value = formatMoneyInput(pricingConfig?.defaults?.per_km_fee ?? null);
    form.surchargePercent.value = formatMoneyInput(0);
    form.notes.value = '';
    selectors.saveRuleBtnText().textContent = 'Save Rule';
    clearFormError();
}

function loadMetrics() {
    selectors.metricsTimestamp().textContent = 'Refreshing…';

    fetch(apiEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'get_metrics', csrf_token: csrfToken }),
    })
        .then(checkResponse)
        .then((result) => {
            if (!result.success) {
                throw new Error(result.message || 'Failed to fetch metrics');
            }
            renderMetrics(result.metrics || {});
        })
        .catch((error) => {
            console.error('Metrics error:', error);
            selectors.metricsTimestamp().textContent = 'Unable to load metrics';
            selectors.ruleUsageList().innerHTML = `<li class="list-group-item text-danger">${escapeHtml(error.message || 'Error loading metrics.')}</li>`;
        });
}

function renderMetrics(metrics) {
    const totalRequests = Number(metrics.total_requests || 0);
    const cacheHits = Number(metrics.cache_hits || 0);
    const cacheRate = totalRequests > 0 ? Math.round((cacheHits / totalRequests) * 100) : 0;

    selectors.metricTotalRequests().textContent = totalRequests.toLocaleString();
    selectors.metricCacheRate().textContent = cacheRate + '%';
    selectors.metricFallbackCalls().textContent = Number(metrics.fallback_calls || 0).toLocaleString();
    selectors.metricAvgDistance().textContent = (Number(metrics.avg_distance_km || 0).toFixed(2)) + ' km';
    selectors.metricAvgFee().textContent = Number(metrics.avg_fee || 0).toFixed(2);
    selectors.metricCacheEntries().textContent = Number(metrics.cache_entries || 0).toLocaleString();

    selectors.metricsTimestamp().textContent = metrics.last_request_at
        ? 'Last request: ' + new Date(metrics.last_request_at).toLocaleString()
        : 'No audit records yet';

    const usageList = selectors.ruleUsageList();
    const ruleUsage = metrics.rule_usage || [];
    if (ruleUsage.length) {
        usageList.innerHTML = ruleUsage.map((row) => `
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>${escapeHtml(row.rule_name || 'Unassigned')}</span>
                <span class="badge bg-primary rounded-pill">${Number(row.usage_count || 0)}</span>
            </li>
        `).join('');
    } else {
        usageList.innerHTML = '<li class="list-group-item text-muted">No usage recorded.</li>';
    }

    const recentBody = selectors.recentRequestsBody();
    const recent = metrics.recent_requests || [];
    if (recent.length) {
        recentBody.innerHTML = recent.map((row) => `
            <tr>
                <td>${escapeHtml(row.provider || 'unknown')}</td>
                <td class="text-center">${row.cache_hit ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'}</td>
                <td class="text-center">${row.fallback_used ? '<span class="badge bg-warning text-dark">Yes</span>' : '<span class="badge bg-secondary">No</span>'}</td>
                <td class="text-end">${row.fee_applied !== null ? Number(row.fee_applied).toFixed(2) : '—'}</td>
                <td class="text-end">${row.distance_km !== null ? Number(row.distance_km).toFixed(2) + ' km' : '—'}</td>
                <td>${row.created_at ? new Date(row.created_at).toLocaleString() : '—'}</td>
            </tr>
        `).join('');
    } else {
        recentBody.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-3">No recent requests.</td></tr>';
    }
}

function handlePurgeCache() {
    if (!confirm('Purge all cached distance entries? This will force fresh API calls.')) {
        return;
    }

    fetch(apiEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'purge_cache', csrf_token: csrfToken }),
    })
        .then(checkResponse)
        .then((result) => {
            if (!result.success) {
                throw new Error(result.message || 'Failed to purge cache');
            }
            alert('Distance cache cleared.');
            loadMetrics();
        })
        .catch((error) => {
            console.error('Cache purge error:', error);
            alert(error.message || 'Unable to purge cache.');
        });
}

function toggleSaveState(isSaving) {
    isSavingRule = isSaving;
    selectors.saveRuleBtn().disabled = isSaving;
    selectors.saveRuleBtnText().textContent = isSaving ? 'Saving…' : (selectors.ruleForm().ruleId.value ? 'Update Rule' : 'Save Rule');
}

function valueOrNull(id) {
    const value = document.getElementById(id).value;
    if (value === null || value === undefined || value === '') {
        return null;
    }
    return value;
}

function checkResponse(response) {
    return response.text().then((text) => {
        let data = null;
        if (text) {
            try {
                data = JSON.parse(text);
            } catch (parseError) {
                console.warn('Non-JSON API response:', text);
            }
        }

        if (response.ok) {
            return data !== null ? data : {};
        }

        const message = data && data.message
            ? data.message
            : (text || 'HTTP ' + response.status);

        throw new Error(message);
    });
}

function escapeHtml(value) {
    if (typeof value !== 'string') {
        value = value === null || value === undefined ? '' : String(value);
    }
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatMoneyInput(value, fallback = '0.00') {
    const num = Number(value);
    if (Number.isFinite(num)) {
        return num.toFixed(2);
    }
    return fallback;
}

function showFormError(message) {
    const alertEl = selectors.ruleFormError();
    if (!alertEl) return;
    alertEl.textContent = message;
    alertEl.classList.remove('d-none');
}

function clearFormError() {
    const alertEl = selectors.ruleFormError();
    if (!alertEl) return;
    alertEl.textContent = '';
    alertEl.classList.add('d-none');
}

function showToast(message, type = 'info') {
    if (!window.bootstrap || !bootstrap.Toast) {
        console.log(`[${type}]`, message);
        return;
    }
    let container = document.getElementById('globalToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'globalToastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(container);
    }
    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-bg-${type} border-0`;
    toastEl.role = 'alert';
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${escapeHtml(message)}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    container.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

function validateRangeOverlap(payload) {
    const min = Number(payload.distance_min_km || 0);
    const max = payload.distance_max_km === '' || payload.distance_max_km === null ? null : Number(payload.distance_max_km);

    if (max !== null && max <= min) {
        return 'Max distance must be greater than min distance.';
    }

    for (const rule of rules) {
        if (payload.id && Number(payload.id) === Number(rule.id)) {
            continue;
        }
        const otherMin = Number(rule.distance_min_km);
        const otherMax = rule.distance_max_km !== null ? Number(rule.distance_max_km) : Infinity;
        const currentMax = max !== null ? max : Infinity;
        if (min <= otherMax && otherMin <= currentMax) {
            return `Distance range overlaps with existing rule: ${rule.rule_name || 'Rule #' + rule.id}`;
        }
    }

    return null;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
