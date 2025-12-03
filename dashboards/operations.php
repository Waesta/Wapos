<?php
require_once '../includes/bootstrap.php';
$auth->requireLogin();

if (!$auth->hasRole(['admin', 'manager', 'super_admin', 'developer'])) {
    redirect('dashboards/cashier.php');
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$currencyConfig = CurrencyManager::getInstance()->getJavaScriptConfig();

function opsTableExists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    $cache[$table] = ((int)($stmt->fetchColumn() ?? 0)) > 0;
    return $cache[$table];
}

$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));

$posSalesToday = (float)($db->fetchOne('SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE DATE(created_at) = ?', [$today])['total'] ?? 0);
$restaurantOrdersToday = opsTableExists($pdo, 'orders')
    ? (int)($db->fetchOne('SELECT COUNT(*) AS count FROM orders WHERE DATE(created_at) = ?', [$today])['count'] ?? 0)
    : 0;
$deliveryActive = opsTableExists($pdo, 'deliveries')
    ? (int)($db->fetchOne("SELECT COUNT(*) AS count FROM deliveries WHERE status IN ('assigned','picked-up','in-transit')")['count'] ?? 0)
    : null;
$loyaltyRedemptionsWeek = opsTableExists($pdo, 'loyalty_transactions')
    ? (int)($db->fetchOne("SELECT COUNT(*) AS count FROM loyalty_transactions WHERE transaction_type = 'redeem' AND DATE(created_at) >= ?", [$weekStart])['count'] ?? 0)
    : 0;

$promotionStats = opsTableExists($pdo, 'promotions')
    ? $db->fetchOne('SELECT COUNT(*) AS total, SUM(is_active = 1) AS active FROM promotions')
    : ['total' => 0, 'active' => 0];
$loyaltyStats = opsTableExists($pdo, 'loyalty_programs')
    ? $db->fetchOne('SELECT COUNT(*) AS total, SUM(is_active = 1) AS active FROM loyalty_programs')
    : ['total' => 0, 'active' => 0];
$modifierStats = opsTableExists($pdo, 'modifiers')
    ? $db->fetchOne('SELECT COUNT(*) AS total, SUM(is_active = 1) AS active FROM modifiers')
    : ['total' => 0, 'active' => 0];

$pendingMobileMoney = (int)($db->fetchOne(
    "SELECT COUNT(*) AS count FROM sales WHERE payment_method = 'mobile_money' AND (mobile_money_reference IS NULL OR mobile_money_reference = '') AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
)['count'] ?? 0);

$deliverySupportsRiders = opsTableExists($pdo, 'riders');

$moduleStatus = [
    [
        'label' => 'Promotions',
        'value' => ($promotionStats['active'] ?? 0) . ' / ' . ($promotionStats['total'] ?? 0) . ' active',
        'status' => ($promotionStats['active'] ?? 0) > 0 ? 'success' : 'warning',
        'description' => 'Targeted offers across POS, restaurant, rooms and delivery.',
        'link' => APP_URL . '/manage-promotions.php',
    ],
    [
        'label' => 'Loyalty Programs',
        'value' => ($loyaltyStats['active'] ?? 0) > 0 ? 'Program live' : 'Inactive',
        'status' => ($loyaltyStats['active'] ?? 0) > 0 ? 'success' : 'warning',
        'description' => 'Configure earn / redeem rules and review top members.',
        'link' => APP_URL . '/loyalty-programs.php',
    ],
    [
        'label' => 'Modifier Library',
        'value' => ($modifierStats['total'] ?? 0) . ' options',
        'status' => ($modifierStats['total'] ?? 0) > 0 ? 'info' : 'warning',
        'description' => 'Guest additions surfaced on every restaurant order.',
        'link' => APP_URL . '/manage-modifiers.php',
    ],
    [
        'label' => 'Delivery Console',
        'value' => $deliveryActive === null ? 'Setup required' : $deliveryActive . ' active jobs',
        'status' => $deliveryActive === null ? 'danger' : ($deliveryActive > 0 ? 'primary' : 'success'),
        'description' => 'Track riders, pricing audits, and SLA performance.',
        'link' => APP_URL . '/delivery-dashboard.php',
    ],
    [
        'label' => 'Mobile Money Audit',
        'value' => $pendingMobileMoney > 0 ? $pendingMobileMoney . ' pending confirmations' : 'All reconciled',
        'status' => $pendingMobileMoney > 0 ? 'warning' : 'success',
        'description' => 'Review collected phone numbers & transaction IDs.',
        'link' => APP_URL . '/reports.php?tab=payments',
    ],
    [
        'label' => 'System Diagnostics',
        'value' => 'Health & schema guards',
        'status' => 'info',
        'description' => 'Monitor automatic schema repairs and background jobs.',
        'link' => APP_URL . '/status.php',
    ],
];

$quickLinks = [
    ['label' => 'POS', 'icon' => 'bi-cart', 'link' => APP_URL . '/pos.php'],
    ['label' => 'Restaurant', 'icon' => 'bi-egg-fried', 'link' => APP_URL . '/restaurant.php'],
    ['label' => 'Orders', 'icon' => 'bi-journal-text', 'link' => APP_URL . '/restaurant-order.php'],
    ['label' => 'Delivery Board', 'icon' => 'bi-truck', 'link' => APP_URL . '/delivery.php'],
    ['label' => 'Accounting', 'icon' => 'bi-calculator', 'link' => APP_URL . '/accounting.php'],
    ['label' => 'Reports', 'icon' => 'bi-graph-up', 'link' => APP_URL . '/reports.php'],
];

$actionCenter = [];
if ($pendingMobileMoney > 0) {
    $actionCenter[] = [
        'label' => 'Mobile money confirmations pending',
        'detail' => $pendingMobileMoney . ' sale(s) require supervisor review.',
        'link' => APP_URL . '/reports.php?tab=payments',
        'tone' => 'warning',
    ];
}
if (($promotionStats['total'] ?? 0) === 0) {
    $actionCenter[] = [
        'label' => 'Create your first promotion',
        'detail' => 'Configure an upsell to drive conversions today.',
        'link' => APP_URL . '/manage-promotions.php',
        'tone' => 'info',
    ];
}
if (!opsTableExists($pdo, 'deliveries')) {
    $actionCenter[] = [
        'label' => 'Delivery tables missing',
        'detail' => 'Run delivery setup scripts to enable live tracking.',
        'link' => APP_URL . '/delivery-pricing.php',
        'tone' => 'danger',
    ];
}

$launchpadBootstrap = [
    'metrics' => [
        'pos_sales_today' => $posSalesToday,
        'restaurant_orders_today' => $restaurantOrdersToday,
        'delivery_active' => $deliveryActive,
        'loyalty_redemptions_week' => $loyaltyRedemptionsWeek,
        'pending_mobile_money' => $pendingMobileMoney,
        'delivery_supports_riders' => $deliverySupportsRiders,
    ],
    'module_status' => $moduleStatus,
    'action_center' => $actionCenter,
    'quick_links' => $quickLinks,
    'generated_at' => date(DATE_ATOM),
];

$pageTitle = 'Operations Launchpad';
include '../includes/header.php';
?>

<style>
    .launchpad-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: var(--spacing-lg);
    }
    .launchpad-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--spacing-md);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
    }
    .launchpad-card footer {
        margin-top: auto;
    }
    .app-status {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.15rem 0.65rem;
        border-radius: 50rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        background: var(--color-border-subtle);
        color: var(--color-text-muted);
    }
    .app-status::before {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: currentColor;
    }
    .app-status[data-color="success"] {
        color: #198754;
        background: rgba(25, 135, 84, 0.12);
    }
    .app-status[data-color="warning"] {
        color: #fd7e14;
        background: rgba(253, 126, 20, 0.12);
    }
    .app-status[data-color="danger"] {
        color: #dc3545;
        background: rgba(220, 53, 69, 0.12);
    }
    .app-status[data-color="primary"] {
        color: #0d6efd;
        background: rgba(13, 110, 253, 0.12);
    }
    .app-status[data-color="info"] {
        color: #0dcaf0;
        background: rgba(13, 202, 240, 0.12);
    }
    .metric-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: var(--spacing-md);
    }
    .metric-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: var(--spacing-md);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
    }
    .action-center {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--spacing-md);
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
    }
    .action-item + .action-item {
        border-top: 1px solid var(--color-border-subtle);
        margin-top: var(--spacing-sm);
        padding-top: var(--spacing-sm);
    }
</style>

<div class="container-fluid py-4 stack-lg">
    <section class="section-heading">
        <div class="stack-sm">
            <h1 class="mb-0"><i class="bi bi-grid-1x2-fill text-primary me-2"></i>Operations Launchpad</h1>
            <p class="text-muted mb-0">Unified control center for promotions, loyalty, delivery, and mobile money.</p>
        </div>
        <div class="d-flex flex-column align-items-end gap-2">
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= APP_URL ?>/reports.php" class="btn btn-outline-primary btn-icon"><i class="bi bi-bar-chart"></i>Reports</a>
                <a href="<?= APP_URL ?>/status.php" class="btn btn-outline-secondary btn-icon"><i class="bi bi-activity"></i>Diagnostics</a>
            </div>
            <small class="text-muted" id="launchpadRefreshLabel">Synced <?= date('M d, Y h:i A') ?></small>
        </div>
    </section>

    <section class="metric-grid">
        <div class="metric-card" data-metric="pos_sales_today">
            <small class="text-muted text-uppercase">POS Sales Today</small>
            <h3 class="mb-0" id="launchpadPosSales"><?= formatMoney($posSalesToday) ?></h3>
            <span class="text-muted">Across all locations</span>
        </div>
        <div class="metric-card" data-metric="restaurant_orders_today">
            <small class="text-muted text-uppercase">Restaurant Orders Today</small>
            <h3 class="mb-0" id="launchpadRestaurantOrders"><?= number_format($restaurantOrdersToday) ?></h3>
            <span class="text-muted">Dine-in + takeout</span>
        </div>
        <div class="metric-card" data-metric="delivery_active">
            <small class="text-muted text-uppercase">Active Deliveries</small>
            <h3 class="mb-0" id="launchpadDeliveryActive"><?= $deliveryActive === null ? '—' : number_format($deliveryActive) ?></h3>
            <span class="text-muted" id="launchpadDeliverySubtext">Live tracking <?= $deliverySupportsRiders ? 'enabled' : 'requires rider data' ?></span>
        </div>
        <div class="metric-card" data-metric="loyalty_redemptions_week">
            <small class="text-muted text-uppercase">Loyalty Redemptions (Week)</small>
            <h3 class="mb-0" id="launchpadLoyaltyRedemptions"><?= number_format($loyaltyRedemptionsWeek) ?></h3>
            <span class="text-muted">Customer incentives processed</span>
        </div>
    </section>

    <section>
        <div class="section-heading">
            <h4 class="mb-0">Module Status</h4>
            <span class="text-muted">Shortcuts to every high-value workflow</span>
        </div>
        <div class="launchpad-grid" id="launchpadModuleGrid">
            <?php foreach ($moduleStatus as $module): ?>
                <article class="launchpad-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <h5 class="mb-1"><?= htmlspecialchars($module['label']) ?></h5>
                        <span class="app-status" data-color="<?= htmlspecialchars($module['status']) ?>"><?= htmlspecialchars($module['value']) ?></span>
                    </div>
                    <p class="text-muted mb-0"><?= htmlspecialchars($module['description']) ?></p>
                    <footer>
                        <a href="<?= htmlspecialchars($module['link']) ?>" class="btn btn-sm btn-outline-primary mt-2">
                            <i class="bi bi-arrow-up-right"></i> Open
                        </a>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="section-heading">
        <div>
            <h4 class="mb-0">Action Center</h4>
            <p class="text-muted mb-0">Outstanding tasks detected across modules.</p>
        </div>
    </section>
    <div class="action-center" id="launchpadActionCenter">
        <?php if (empty($actionCenter)): ?>
            <div class="text-muted">No pending actions. All monitored modules are nominal.</div>
        <?php else: ?>
            <?php foreach ($actionCenter as $index => $action): ?>
                <div class="action-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong><?= htmlspecialchars($action['label']) ?></strong>
                            <div class="text-muted"><?= htmlspecialchars($action['detail']) ?></div>
                        </div>
                        <span class="badge bg-<?= htmlspecialchars($action['tone']) ?> text-uppercase">Attention</span>
                    </div>
                    <a href="<?= htmlspecialchars($action['link']) ?>" class="btn btn-sm btn-outline-secondary mt-2">
                        <i class="bi bi-arrow-right"></i> Review
                    </a>
                </div>
                <?php if ($index < count($actionCenter) - 1): ?>
                    <hr>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <section>
        <div class="section-heading">
            <h4 class="mb-0">Quick Launch</h4>
            <span class="text-muted">Jump directly into frequently used modules.</span>
        </div>
        <div class="d-flex flex-wrap gap-2" id="launchpadQuickLinks">
            <?php foreach ($quickLinks as $quickLink): ?>
                <a href="<?= htmlspecialchars($quickLink['link']) ?>" class="btn btn-outline-dark btn-icon">
                    <i class="bi <?= htmlspecialchars($quickLink['icon']) ?>"></i><?= htmlspecialchars($quickLink['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<script>
    window.OPERATIONS_LAUNCHPAD_CONFIG = {
        endpoint: '../api/get-operations-launchpad-data.php',
        currency: <?= json_encode($currencyConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        initial: <?= json_encode($launchpadBootstrap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    };
</script>

<script>
(function() {
    const config = window.OPERATIONS_LAUNCHPAD_CONFIG || {};
    const currencyConfig = config.currency || { symbol: '', position: 'before', decimal_places: 2 };
    const endpoint = config.endpoint;
    const state = {
        timer: null,
        interval: 60000,
        fetching: false
    };

    const elements = {
        posSales: document.getElementById('launchpadPosSales'),
        restaurantOrders: document.getElementById('launchpadRestaurantOrders'),
        deliveryActive: document.getElementById('launchpadDeliveryActive'),
        deliverySubtext: document.getElementById('launchpadDeliverySubtext'),
        loyaltyRedemptions: document.getElementById('launchpadLoyaltyRedemptions'),
        refreshLabel: document.getElementById('launchpadRefreshLabel'),
        moduleGrid: document.getElementById('launchpadModuleGrid'),
        actionCenter: document.getElementById('launchpadActionCenter'),
        quickLinks: document.getElementById('launchpadQuickLinks')
    };

    function init() {
        if (config.initial) {
            renderAll(config.initial);
        }
        scheduleNextPoll();
        document.addEventListener('visibilitychange', handleVisibilityChange);
    }

    function handleVisibilityChange() {
        if (document.hidden) {
            clearTimeout(state.timer);
        } else {
            fetchLatest(true);
        }
    }

    function scheduleNextPoll() {
        clearTimeout(state.timer);
        state.timer = setTimeout(fetchLatest, state.interval);
    }

    function fetchLatest(force = false) {
        if (!endpoint || state.fetching) {
            if (!state.fetching) {
                scheduleNextPoll();
            }
            return;
        }
        if (document.hidden && !force) {
            return;
        }

        state.fetching = true;
        setRefreshLabel('Syncing latest metrics…');

        fetch(`${endpoint}?_=${Date.now()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to load launchpad data');
                }
                return response.json();
            })
            .then(payload => {
                if (!payload?.success || !payload.data) {
                    throw new Error(payload?.message || 'Launchpad data unavailable');
                }
                renderAll(payload.data);
                scheduleNextPoll();
            })
            .catch(error => {
                console.error(error);
                setRefreshLabel('Live sync unavailable. Retrying…');
                scheduleNextPoll();
            })
            .finally(() => {
                state.fetching = false;
            });
    }

    function renderAll(data) {
        if (!data) {
            return;
        }
        renderMetrics(data.metrics || {});
        renderModules(Array.isArray(data.module_status) ? data.module_status : []);
        renderActionCenter(Array.isArray(data.action_center) ? data.action_center : []);
        renderQuickLinks(Array.isArray(data.quick_links) ? data.quick_links : []);
        setRefreshLabel(data.generated_at || new Date().toISOString());
    }

    function renderMetrics(metrics) {
        if (elements.posSales && metrics.pos_sales_today !== undefined) {
            elements.posSales.textContent = formatCurrencyValue(metrics.pos_sales_today);
        }
        if (elements.restaurantOrders && metrics.restaurant_orders_today !== undefined) {
            elements.restaurantOrders.textContent = formatNumber(metrics.restaurant_orders_today);
        }
        if (elements.deliveryActive) {
            const value = metrics.delivery_active;
            elements.deliveryActive.textContent = value === null || value === undefined ? '—' : formatNumber(value);
        }
        if (elements.deliverySubtext) {
            const supportsRiders = !!metrics.delivery_supports_riders;
            elements.deliverySubtext.textContent = `Live tracking ${supportsRiders ? 'enabled' : 'requires rider data'}`;
        }
        if (elements.loyaltyRedemptions && metrics.loyalty_redemptions_week !== undefined) {
            elements.loyaltyRedemptions.textContent = formatNumber(metrics.loyalty_redemptions_week);
        }
    }

    function renderModules(modules) {
        if (!elements.moduleGrid) {
            return;
        }
        if (!modules.length) {
            elements.moduleGrid.innerHTML = '<div class="text-muted">No modules available.</div>';
            return;
        }
        elements.moduleGrid.innerHTML = modules.map(module => {
            const label = escapeHtml(module.label || 'Module');
            const value = escapeHtml(module.value || '—');
            const description = escapeHtml(module.description || '');
            const link = escapeAttribute(module.link || '#');
            const status = escapeAttribute(module.status || 'info');
            return `
                <article class="launchpad-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <h5 class="mb-1">${label}</h5>
                        <span class="app-status" data-color="${status}">${value}</span>
                    </div>
                    <p class="text-muted mb-0">${description}</p>
                    <footer>
                        <a href="${link}" class="btn btn-sm btn-outline-primary mt-2">
                            <i class="bi bi-arrow-up-right"></i> Open
                        </a>
                    </footer>
                </article>
            `;
        }).join('');
    }

    function renderActionCenter(actions) {
        if (!elements.actionCenter) {
            return;
        }
        if (!actions.length) {
            elements.actionCenter.innerHTML = '<div class="text-muted">No pending actions. All monitored modules are nominal.</div>';
            return;
        }
        elements.actionCenter.innerHTML = actions.map((action, index) => {
            const label = escapeHtml(action.label || 'Action Required');
            const detail = escapeHtml(action.detail || '');
            const link = escapeAttribute(action.link || '#');
            const tone = escapeAttribute(action.tone || 'secondary');
            const divider = index < actions.length - 1 ? '<hr>' : '';
            return `
                <div class="action-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>${label}</strong>
                            <div class="text-muted">${detail}</div>
                        </div>
                        <span class="badge bg-${tone} text-uppercase">Attention</span>
                    </div>
                    <a href="${link}" class="btn btn-sm btn-outline-secondary mt-2">
                        <i class="bi bi-arrow-right"></i> Review
                    </a>
                </div>
                ${divider}
            `;
        }).join('');
    }

    function renderQuickLinks(links) {
        if (!elements.quickLinks || !links.length) {
            return;
        }
        elements.quickLinks.innerHTML = links.map(link => {
            const label = escapeHtml(link.label || 'Open');
            const icon = escapeAttribute(link.icon || 'bi-arrow-up-right');
            const href = escapeAttribute(link.link || '#');
            return `
                <a href="${href}" class="btn btn-outline-dark btn-icon">
                    <i class="bi ${icon}"></i>${label}
                </a>
            `;
        }).join('');
    }

    function setRefreshLabel(timestamp) {
        if (!elements.refreshLabel) {
            return;
        }
        const date = timestamp ? new Date(timestamp) : new Date();
        if (Number.isNaN(date.getTime())) {
            elements.refreshLabel.textContent = 'Live sync enabled';
            return;
        }
        elements.refreshLabel.textContent = `Synced ${date.toLocaleString(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            month: 'short',
            day: 'numeric'
        })}`;
    }

    function formatCurrencyValue(amount) {
        const value = Number(amount || 0);
        const decimals = Number(currencyConfig.decimal_places ?? 2);
        const formatted = value.toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
        if (!currencyConfig.symbol) {
            return formatted;
        }
        return currencyConfig.position === 'after'
            ? `${formatted} ${currencyConfig.symbol}`
            : `${currencyConfig.symbol} ${formatted}`;
    }

    function formatNumber(value) {
        if (value === null || value === undefined) {
            return '—';
        }
        return Number(value).toLocaleString();
    }

    function escapeHtml(str) {
        if (typeof str !== 'string') {
            str = `${str ?? ''}`;
        }
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        };
        return str.replace(/[&<>"']/g, char => map[char]);
    }

    function escapeAttribute(str) {
        return escapeHtml(str).replace(/"/g, '&quot;');
    }

    init();
})();
</script>

<?php include '../includes/footer.php'; ?>
