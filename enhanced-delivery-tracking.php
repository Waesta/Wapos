<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();
$currencySymbol = getCurrencySymbol();

$deliverySettingKeys = [
    'delivery_max_active_jobs',
    'delivery_sla_pending_limit',
    'delivery_sla_assigned_limit',
    'delivery_sla_delivery_limit',
    'delivery_sla_slack_minutes',
    'google_maps_api_key',
    'business_latitude',
    'business_longitude',
];
$placeholders = implode(',', array_fill(0, count($deliverySettingKeys), '?'));
$deliverySettingsRows = $db->fetchAll(
    "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)",
    $deliverySettingKeys
);
$deliverySettings = [];
foreach ($deliverySettingsRows as $row) {
    $deliverySettings[$row['setting_key']] = $row['setting_value'];
}

$googleMapsApiKey = (string)($deliverySettings['google_maps_api_key'] ?? '');
$businessLat = isset($deliverySettings['business_latitude']) ? (float)$deliverySettings['business_latitude'] : null;
$businessLng = isset($deliverySettings['business_longitude']) ? (float)$deliverySettings['business_longitude'] : null;

$currentUser = method_exists($auth, 'getUser') ? $auth->getUser() : null;
$currentRole = $currentUser['role'] ?? 'staff';
$maskRiderPhones = !in_array($currentRole, ['super_admin', 'admin', 'manager', 'developer'], true);

$deliveryConfig = [
    'max_active_jobs' => max(1, (int)($deliverySettings['delivery_max_active_jobs'] ?? 3)),
    'sla' => [
        'pending' => max(1, (int)($deliverySettings['delivery_sla_pending_limit'] ?? 15)),
        'assigned' => max(1, (int)($deliverySettings['delivery_sla_assigned_limit'] ?? 10)),
        'delivery' => max(1, (int)($deliverySettings['delivery_sla_delivery_limit'] ?? 45)),
        'slack' => max(0, (int)($deliverySettings['delivery_sla_slack_minutes'] ?? 5)),
    ],
    'google_maps_api_key' => $googleMapsApiKey,
    'business_latitude' => $businessLat,
    'business_longitude' => $businessLng,
];

$slaTargetsText = sprintf(
    'SLA Targets · Pending %d min · Assigned %d min · Delivery %d min (+%d min slack)',
    $deliveryConfig['sla']['pending'],
    $deliveryConfig['sla']['assigned'],
    $deliveryConfig['sla']['delivery'],
    $deliveryConfig['sla']['slack']
);

// Get delivery statistics
$stats = [
    'active_deliveries' => $db->fetchOne("SELECT COUNT(*) as count FROM deliveries WHERE status IN ('assigned', 'picked-up', 'in-transit')")['count'] ?? 0,
    'pending_deliveries' => $db->fetchOne("SELECT COUNT(*) as count FROM deliveries WHERE status = 'pending'")['count'] ?? 0,
    'completed_today' => $db->fetchOne("SELECT COUNT(*) as count FROM deliveries WHERE status = 'delivered' AND DATE(actual_delivery_time) = CURDATE()")['count'] ?? 0,
    'average_delivery_time' => $db->fetchOne("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, actual_delivery_time)) as avg_time FROM deliveries WHERE status = 'delivered' AND DATE(actual_delivery_time) = CURDATE()")['avg_time'] ?? 0
];

// Get active deliveries with real-time tracking
$activeDeliveries = $db->fetchAll("
    SELECT 
        d.*,
        o.order_number,
        o.customer_name,
        o.customer_phone,
        o.total_amount,
        r.name as rider_name,
        r.phone as rider_phone,
        r.vehicle_type,
        r.vehicle_number,
        r.current_latitude,
        r.current_longitude,
        TIMESTAMPDIFF(MINUTE, d.created_at, NOW()) as elapsed_minutes,
        CASE 
            WHEN d.status = 'pending' THEN 'Waiting for rider assignment'
            WHEN d.status = 'assigned' THEN 'Rider assigned, preparing for pickup'
            WHEN d.status = 'picked-up' THEN 'Order picked up, heading to customer'
            WHEN d.status = 'in-transit' THEN 'On the way to delivery'
            WHEN d.status = 'delivered' THEN 'Successfully delivered'
            WHEN d.status = 'failed' THEN 'Delivery failed'
        END as status_description
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    LEFT JOIN riders r ON d.rider_id = r.id
    WHERE d.status IN ('pending', 'assigned', 'picked-up', 'in-transit')
    ORDER BY d.created_at ASC
");

$pageTitle = 'Enhanced Delivery Tracking';
include 'includes/header.php';
?>

<div class="container-fluid">
    <!-- Real-time Delivery Dashboard -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5><i class="bi bi-truck me-2"></i>Real-time Delivery Dashboard</h5>
                    <small>Auto-refreshes every 30 seconds</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Dynamic Pricing Metrics -->
    <div class="row g-3 mb-2" id="pricingMetricsRow" style="display:none;">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-cash-coin text-success fs-1 mb-2"></i>
                    <h3 class="text-success" id="pricingTrackedOrders">0</h3>
                    <p class="text-muted mb-0">Tracked Deliveries</p>
                    <small class="text-muted" id="pricingTotalRequests">&nbsp;</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-graph-up text-info fs-1 mb-2"></i>
                    <h3 class="text-info" id="pricingAvgFee">0.00</h3>
                    <p class="text-muted mb-0">Average Fee</p>
                    <small class="text-muted" id="pricingAvgDistance">&nbsp;</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-lightning-charge text-primary fs-1 mb-2"></i>
                    <h3 class="text-primary" id="pricingCacheRate">0%</h3>
                    <p class="text-muted mb-0">Cache Hit Rate</p>
                    <small class="text-muted" id="pricingCacheHits">&nbsp;</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-exclamation-triangle text-warning fs-1 mb-2"></i>
                    <h3 class="text-warning" id="pricingFallbackRate">0%</h3>
                    <p class="text-muted mb-0">Fallback Usage</p>
                    <small class="text-muted" id="pricingFallbackCalls">&nbsp;</small>
                </div>
            </div>
        </div>
    </div>
    <div class="mb-4 text-muted small" id="pricingLastUpdatedText" style="display:none;"></div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-truck text-primary fs-1 mb-2"></i>
                    <h3 class="text-primary" id="activeDeliveriesCount"><?= $stats['active_deliveries'] ?></h3>
                    <p class="text-muted mb-0">Active Deliveries</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-clock text-warning fs-1 mb-2"></i>
                    <h3 class="text-warning" id="pendingDeliveriesCount"><?= $stats['pending_deliveries'] ?></h3>
                    <p class="text-muted mb-0">Pending Assignment</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle text-success fs-1 mb-2"></i>
                    <h3 class="text-success" id="completedTodayCount"><?= $stats['completed_today'] ?></h3>
                    <p class="text-muted mb-0">Completed Today</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-speedometer text-info fs-1 mb-2"></i>
                    <h3 class="text-info" id="avgDeliveryTime"><?= round($stats['average_delivery_time']) ?> min</h3>
                    <p class="text-muted mb-0">Avg Delivery Time</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Operational Thresholds -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-3">Operational Thresholds</h6>
                    <p class="mb-2" id="slaTargetsLabel"><?= htmlspecialchars($slaTargetsText) ?></p>
                    <div class="d-flex flex-wrap gap-2 small">
                        <span class="badge bg-primary-subtle text-primary" id="maxActiveJobsBadge">Max active: <?= $deliveryConfig['max_active_jobs'] ?></span>
                        <span class="badge bg-secondary-subtle text-secondary" id="slaSlackBadge">Slack: <?= $deliveryConfig['sla']['slack'] ?> min</span>
                        <span class="badge bg-info-subtle text-info" id="slaPendingBadge">Pending: <?= $deliveryConfig['sla']['pending'] ?> min</span>
                        <span class="badge bg-warning-subtle text-warning" id="slaAssignedBadge">Assigned: <?= $deliveryConfig['sla']['assigned'] ?> min</span>
                        <span class="badge bg-success-subtle text-success" id="slaDeliveryBadge">Delivery: <?= $deliveryConfig['sla']['delivery'] ?> min</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-3">Rider Workload Snapshot</h6>
                    <div class="d-flex flex-wrap gap-2 small align-items-center">
                        <span class="badge bg-success-subtle text-success" id="ridersAvailableBadge">Available: --</span>
                        <span class="badge bg-warning-subtle text-warning" id="ridersBusyBadge">Busy: --</span>
                        <span class="badge bg-secondary-subtle text-secondary" id="ridersOfflineBadge">Offline: --</span>
                        <span class="badge bg-info-subtle text-info" id="avgIdleBadge">Avg idle: --</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Live Delivery Map -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-geo-alt"></i> Live Delivery Map</h6>
                </div>
                <div class="card-body">
                    <div id="deliveryMap" style="height: 400px;"></div>
                </div>
            </div>
        </div>

        <!-- Active Deliveries List -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-list-check"></i> Active Deliveries</h6>
                </div>
                <div class="card-body" id="activeDeliveriesContainer" style="max-height: 400px; overflow-y: auto;">
                    <div class="text-center text-muted py-4">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <p class="mb-0">Loading active deliveries…</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delivery Analytics -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-graph-up"></i> Delivery Performance</h6>
                </div>
                <div class="card-body">
                    <canvas id="deliveryChart" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-star"></i> Rider Performance</h6>
                </div>
                <div class="card-body">
                    <div id="riderPerformance" class="d-grid gap-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delivery Tracking Modal -->
<div class="modal fade" id="trackingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delivery Tracking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="trackingContent">
                    <!-- Tracking details will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="delivery-dispatch-ui.js"></script>
let deliveryChart;
let googleMap;
let directionsService;
let mapInfoWindow;
let mapHasFitBounds = false;
let googleMapsLoader = null;
let googleMapsErrored = false;
const mapMarkers = new Map();
const routeRenderers = new Map();
const routeRequestCache = new Map();
const ROUTE_REFRESH_MS = 60000;
const currencySymbol = <?= json_encode($currencySymbol) ?>;
let deliveryConfig = <?= json_encode($deliveryConfig) ?>;
const googleMapsConfig = {
    apiKey: deliveryConfig.google_maps_api_key || '',
    businessLat: deliveryConfig.business_latitude,
    businessLng: deliveryConfig.business_longitude
};
const maskRiderPhones = <?= $maskRiderPhones ? 'true' : 'false' ?>;
const defaultMapCenter = (googleMapsConfig.businessLat !== null && googleMapsConfig.businessLat !== undefined &&
    googleMapsConfig.businessLng !== null && googleMapsConfig.businessLng !== undefined)
    ? { lat: Number(googleMapsConfig.businessLat), lng: Number(googleMapsConfig.businessLng) }
    : { lat: -1.2921, lng: 36.8219 };
let MAX_ACTIVE_JOBS = deliveryConfig.max_active_jobs ?? 3;
let latestDeliverySnapshot = {
    deliveries: <?= json_encode($activeDeliveries) ?>,
    supportsPricingAudit: false,
    pricingMetrics: null
};
const initialStats = <?= json_encode($stats) ?>;

// Auto-refresh every 30 seconds
setInterval(refreshDeliveryData, 30000);

async function refreshDeliveryData() {
    try {
        const response = await fetch('api/get-delivery-dashboard-data.php');
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Failed to load delivery data');
        }
        if (data.config) {
            if (typeof data.config.max_active_jobs !== 'undefined') {
                const parsedMax = Number(data.config.max_active_jobs);
                MAX_ACTIVE_JOBS = Number.isFinite(parsedMax) && parsedMax > 0 ? parsedMax : MAX_ACTIVE_JOBS;
                deliveryConfig.max_active_jobs = MAX_ACTIVE_JOBS;
            }
            if (data.config.sla) {
                deliveryConfig.sla = {
                    pending: Number(data.config.sla.pending_minutes_limit) || deliveryConfig.sla.pending,
                    assigned: Number(data.config.sla.assigned_minutes_limit) || deliveryConfig.sla.assigned,
                    delivery: Number(data.config.sla.delivery_minutes_limit) || deliveryConfig.sla.delivery,
                    slack: Number(data.config.sla.slack_minutes) >= 0 ? Number(data.config.sla.slack_minutes) : deliveryConfig.sla.slack,
                };
            }
            updateThresholdUi();
        }

        latestDeliverySnapshot = {
            deliveries: data.deliveries || [],
            supportsPricingAudit: Boolean(data.supportsPricingAudit),
            pricingMetrics: data.pricingMetrics || null
        };

        updateDeliveryStats(data.stats || {}, latestDeliverySnapshot.pricingMetrics, latestDeliverySnapshot.supportsPricingAudit);
        updateActiveDeliveries(latestDeliverySnapshot.deliveries, latestDeliverySnapshot.supportsPricingAudit);
        updateDeliveryChart(data.chartData);
        updateRiderPerformance(data.riderPerformance);
        await updateMapMarkers(latestDeliverySnapshot.deliveries, latestDeliverySnapshot.supportsPricingAudit);
    } catch (error) {
        console.error('Error refreshing delivery data:', error);
    }
}

function updateDeliveryStats(stats, pricingMetrics, supportsPricingAudit) {
    if (!stats) {
        return;
    }

    document.getElementById('activeDeliveriesCount').textContent = Number(stats.active_deliveries || 0);
    document.getElementById('pendingDeliveriesCount').textContent = Number(stats.pending_deliveries || 0);
    document.getElementById('completedTodayCount').textContent = Number(stats.completed_today || 0);
    document.getElementById('avgDeliveryTime').textContent = formatMinutesLabel(stats.average_delivery_time);

    const ridersAvailable = document.getElementById('ridersAvailableBadge');
    const ridersBusy = document.getElementById('ridersBusyBadge');
    const ridersOffline = document.getElementById('ridersOfflineBadge');
    const avgIdleBadge = document.getElementById('avgIdleBadge');

    if (ridersAvailable) {
        ridersAvailable.textContent = `Available: ${Number(stats.riders_available ?? stats.active_riders ?? 0)}`;
    }
    if (ridersBusy) {
        ridersBusy.textContent = `Busy: ${Number(stats.riders_busy ?? 0)}`;
    }
    if (ridersOffline) {
        ridersOffline.textContent = `Offline: ${Number(stats.riders_offline ?? 0)}`;
    }
    if (avgIdleBadge) {
        if (typeof stats.avg_idle_minutes !== 'undefined' && stats.avg_idle_minutes !== null) {
            avgIdleBadge.textContent = `Avg idle: ${formatMinutesLabel(stats.avg_idle_minutes)}`;
        } else {
            avgIdleBadge.textContent = 'Avg idle: --';
        }
    }

    const pricingRow = document.getElementById('pricingMetricsRow');
    const pricingLastUpdated = document.getElementById('pricingLastUpdatedText');

    if (supportsPricingAudit && pricingMetrics) {
        pricingRow.style.display = '';
        const summary = pricingMetrics.summary || {};
        const trackedOrders = summary.tracked_orders || 0;
        document.getElementById('pricingTrackedOrders').textContent = trackedOrders.toLocaleString();
        document.getElementById('pricingTotalRequests').textContent = `Total requests: ${(pricingMetrics.total_requests || 0).toLocaleString()}`;

        const avgFee = summary.avg_fee !== null && summary.avg_fee !== undefined ? formatCurrency(summary.avg_fee) : '—';
        document.getElementById('pricingAvgFee').textContent = avgFee;

        const avgDistance = summary.avg_distance_km !== null && summary.avg_distance_km !== undefined
            ? `${Number(summary.avg_distance_km).toFixed(2)} km`
            : 'Distance N/A';
        document.getElementById('pricingAvgDistance').textContent = avgDistance;

        const totalRequests = pricingMetrics.total_requests || 0;
        const cacheHits = pricingMetrics.cache_hits || 0;
        const fallbackCalls = pricingMetrics.fallback_calls || 0;

        const cacheRate = totalRequests ? Math.round((cacheHits / totalRequests) * 100) : 0;
        const fallbackRate = totalRequests ? Math.round((fallbackCalls / totalRequests) * 100) : 0;

        document.getElementById('pricingCacheRate').textContent = `${cacheRate}%`;
        document.getElementById('pricingCacheHits').textContent = `${cacheHits.toLocaleString()} cache hits`;

        document.getElementById('pricingFallbackRate').textContent = `${fallbackRate}%`;
        document.getElementById('pricingFallbackCalls').textContent = `${fallbackCalls.toLocaleString()} fallback calls`;

        if (pricingMetrics.last_request_at) {
            pricingLastUpdated.textContent = `Last pricing request: ${formatDateTime(pricingMetrics.last_request_at)}`;
        } else {
            pricingLastUpdated.textContent = 'No pricing requests captured yet.';
        }
        pricingLastUpdated.style.display = '';
    } else {
        pricingRow.style.display = 'none';
        pricingLastUpdated.style.display = 'none';
    }
}

function updateActiveDeliveries(deliveries, supportsPricingAudit) {
    const container = document.getElementById('activeDeliveriesContainer');
    if (!deliveries.length) {
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="bi bi-truck fs-1 mb-3"></i>
                <p>No active deliveries</p>
            </div>
        `;
        return;
    }

    container.innerHTML = deliveries.map(delivery => {
        const sla = delivery.sla || {};
        const slaBadge = renderSlaBadge(sla);
        const slaMeta = renderSlaMeta(sla);
        const statusHint = delivery.status_description ? `<small class="text-muted">${escapeHtml(delivery.status_description)}</small>` : '';
        return `
            <div class="delivery-item mb-3 p-3 border rounded ${sla.is_at_risk ? 'border-danger' : ''}" data-delivery-id="${delivery.id}" data-order-id="${delivery.order_id}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <strong>#${escapeHtml(delivery.order_number)}</strong><br>
                        <small class="text-muted">${escapeHtml(delivery.customer_name || 'N/A')}</small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-${getStatusColor(delivery.status)}">${capitalize(delivery.status)}</span>
                        ${slaBadge}
                    </div>
                </div>
                <div class="mb-2">
                    <small class="text-muted">
                        <i class="bi bi-person me-1"></i>${escapeHtml(delivery.rider_name || 'Not assigned')}
                    </small>
                    ${delivery.rider_phone ? `<br><small class="text-muted"><i class="bi bi-telephone me-1"></i>${escapeHtml(formatRiderPhone(delivery.rider_phone))}</small>` : ''}
                </div>
                <div class="mb-2">
                    ${statusHint}
                    <small class="text-muted d-block">Started ${Number(delivery.elapsed_minutes || 0)} min ago</small>
                    ${slaMeta}
                </div>
                ${supportsPricingAudit ? renderPricingMeta(delivery) : ''}
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <small class="text-muted">${formatCurrency(delivery.total_amount)}</small>
                    <div class="btn-group btn-group-sm">
                        ${delivery.status === 'pending' && delivery.delivery_latitude && delivery.delivery_longitude ? 
                            `<button class="btn btn-success" onclick="autoAssignDelivery(${delivery.id})" title="Auto-assign optimal rider">
                                <i class="bi bi-lightning-fill"></i>
                            </button>
                            <button class="btn btn-outline-secondary" onclick="showRiderSuggestions(${delivery.delivery_latitude}, ${delivery.delivery_longitude}, ${delivery.id})" title="View rider suggestions">
                                <i class="bi bi-people"></i>
                            </button>` : 
                            ''
                        }
                        <button class="btn btn-outline-primary" onclick="trackDelivery(${delivery.order_id})" title="Track delivery">
                            <i class="bi bi-geo-alt"></i>
                        </button>
                        <button class="btn btn-outline-info" onclick="contactCustomer('${delivery.customer_phone ? escapeHtml(delivery.customer_phone) : ''}')" title="Contact customer">
                            <i class="bi bi-telephone"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function updateDeliveryChart(chartData) {
    const labels = chartData.map(item => item.delivery_date ? new Date(item.delivery_date).toLocaleDateString() : '');
    const deliveriesCompleted = chartData.map(item => Number(item.deliveries_count || 0));
    const averageTimes = chartData.map(item => Number(parseFloat(item.avg_time || 0).toFixed(2)));

    if (!deliveryChart) {
        const ctx = document.getElementById('deliveryChart').getContext('2d');
        deliveryChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Deliveries Completed',
                    data: deliveriesCompleted,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.3,
                    fill: true
                }, {
                    label: 'Average Delivery Time (min)',
                    data: averageTimes,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    } else {
        deliveryChart.data.labels = labels;
        deliveryChart.data.datasets[0].data = deliveriesCompleted;
        deliveryChart.data.datasets[1].data = averageTimes;
        deliveryChart.update();
    }
}

function updateRiderPerformance(riders) {
    const container = document.getElementById('riderPerformance');
    if (!riders.length) {
        container.innerHTML = '<div class="text-muted">No rider performance metrics available today.</div>';
        return;
    }
    container.innerHTML = riders.map(rider => `
        <div class="border rounded p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>${rider.name}</strong>
                <span class="badge bg-success">${Number(rider.successful_deliveries)} delivered</span>
            </div>
            <div class="row text-muted small">
                <div class="col-6">Total Deliveries: ${Number(rider.total_deliveries)}</div>
                <div class="col-6">Avg Time: ${formatMinutes(rider.avg_delivery_time)}</div>
                <div class="col-6">Customer Rating: ${formatRating(rider.avg_customer_rating)}</div>
                <div class="col-6">On-time: ${calculateOnTimeRate(rider)}</div>
            </div>
        </div>
    `).join('');
}

async function updateMapMarkers(deliveries, supportsPricingAudit) {
    const googleLib = await ensureGoogleMapsLoaded();
    if (!googleLib) {
        return;
    }

    if (!googleMap) {
        initGoogleMap();
    }

    const activeIds = new Set();
    const bounds = new google.maps.LatLngBounds();
    let hasPoints = false;

    deliveries.forEach(delivery => {
        const lat = Number(delivery.current_latitude);
        const lng = Number(delivery.current_longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
        }

        hasPoints = true;
        bounds.extend({ lat, lng });
        activeIds.add(String(delivery.id));

        const markerKey = String(delivery.id);
        const existing = mapMarkers.get(markerKey);
        const iconConfig = markerIcon(getMarkerColor(delivery));

        if (existing) {
            existing.marker.setPosition({ lat, lng });
            existing.marker.setIcon(iconConfig);
            existing.delivery = delivery;
        } else {
            const marker = new google.maps.Marker({
                position: { lat, lng },
                map: googleMap,
                icon: iconConfig,
                title: delivery.rider_name || `Delivery #${delivery.order_number}`
            });

            marker.addListener('click', () => {
                mapInfoWindow.setContent(buildMarkerInfo(delivery, supportsPricingAudit));
                mapInfoWindow.open(googleMap, marker);
            });

            mapMarkers.set(markerKey, { marker, delivery });
        }

        updateRouteForDelivery(delivery);
    });

    // Remove markers for deliveries no longer active
    for (const [key, entry] of mapMarkers.entries()) {
        if (!activeIds.has(key)) {
            entry.marker.setMap(null);
            mapMarkers.delete(key);
        }
    }

    cleanupOrphanRoutes(activeIds);

    if (hasPoints && !mapHasFitBounds) {
        googleMap.fitBounds(bounds, { top: 50, bottom: 50, left: 50, right: 50 });
        mapHasFitBounds = true;
    } else if (!hasPoints && !mapHasFitBounds) {
        googleMap.setCenter(defaultMapCenter);
        googleMap.setZoom(12);
    }
}

async function trackDelivery(orderId) {
    try {
        const response = await fetch(`api/get-delivery-tracking.php?order_id=${orderId}`);
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Unable to fetch delivery tracking');
        }
        const currentDelivery = latestDeliverySnapshot.deliveries.find(item => Number(item.order_id) === Number(orderId));
        showTrackingModal(data.data, currentDelivery, latestDeliverySnapshot.supportsPricingAudit);
    } catch (error) {
        console.error('Error tracking delivery:', error);
        alert('Unable to load delivery tracking details.');
    }
}

function showTrackingModal(trackingData, deliverySnapshot, supportsPricingAudit) {
    const timelineHtml = buildTimeline(trackingData.timeline || []);
    const pricingHtml = supportsPricingAudit ? buildPricingModalSection(deliverySnapshot) : '';
    const sla = (deliverySnapshot && deliverySnapshot.sla) || null;
    const slaHtml = sla ? `
        <div class="mt-2">
            <span class="badge ${sla.is_at_risk ? 'bg-danger' : (sla.is_late ? 'bg-warning text-dark' : 'bg-success-subtle text-success')}">
                ${sla.is_at_risk ? 'SLA risk' : (sla.is_late ? 'SLA late' : 'SLA on track')}
            </span>
            <small class="text-muted d-block mt-1">
                Phase: ${capitalize(sla.phase || trackingData.status || 'pending')} · Wait ${formatMinutesLabel(sla.wait_minutes)} · Delay ${formatMinutesLabel(sla.delay_minutes)}
            </small>
            ${sla.promised_time ? `<small class="text-muted">Promised by ${formatDateTime(sla.promised_time)}</small>` : ''}
        </div>
    ` : '';
    const content = `
        <div class="row">
            <div class="col-md-6">
                <h6>Order Information</h6>
                <p class="mb-1"><strong>Order #:</strong> ${trackingData.order_number}</p>
                <p class="mb-1"><strong>Customer:</strong> ${trackingData.customer_name}</p>
                <p class="mb-1"><strong>Phone:</strong> ${trackingData.customer_phone || 'N/A'}</p>
                <p class="mb-0"><strong>Address:</strong> ${trackingData.delivery_address || 'N/A'}</p>
            </div>
            <div class="col-md-6">
                <h6>Delivery Status</h6>
                <p class="mb-1"><strong>Status:</strong> <span class="badge bg-${getStatusColor(trackingData.status)}">${capitalize(trackingData.status)}</span></p>
                <p class="mb-1"><strong>Rider:</strong> ${trackingData.rider_name || 'Not assigned'}</p>
                <p class="mb-1"><strong>Vehicle:</strong> ${trackingData.vehicle_type || 'N/A'} ${trackingData.vehicle_number ? '(' + trackingData.vehicle_number + ')' : ''}</p>
                <p class="mb-0"><strong>Estimated Time:</strong> ${formatDateTime(trackingData.estimated_time) || 'N/A'}</p>
                ${slaHtml}
            </div>
        </div>
        ${pricingHtml}
        <div class="row mt-3">
            <div class="col-12">
                <h6>Delivery Timeline</h6>
                ${timelineHtml}
            </div>
        </div>
    `;
    
    document.getElementById('trackingContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('trackingModal')).show();
}

function buildTimeline(entries) {
    if (!entries.length) {
        return '<p class="text-muted">No timeline data available.</p>';
    }
    return `
        <ul class="list-group">
            ${entries.map(entry => `
                <li class="list-group-item d-flex justify-content-between align-items-start">
                    <div class="ms-2 me-auto">
                        <div class="fw-semibold">${capitalize(entry.status)}</div>
                        <small class="text-muted">${entry.notes || 'No notes provided.'}</small>
                    </div>
                    <span class="badge bg-secondary rounded-pill">${formatDateTime(entry.created_at)}</span>
                </li>
            `).join('')}
        </ul>
    `;
}

function contactCustomer(phone) {
    if (phone) {
        window.open(`tel:${phone}`);
    }
}

function renderSlaBadge(sla) {
    if (!sla || (!sla.is_at_risk && !sla.is_late && !sla.phase)) {
        return '';
    }
    if (sla.is_at_risk) {
        return '<span class="badge bg-danger">SLA risk</span>';
    }
    if (sla.is_late) {
        return '<span class="badge bg-warning text-dark">SLA late</span>';
    }
    return '<span class="badge bg-success-subtle text-success">SLA on track</span>';
}

function renderSlaMeta(sla) {
    if (!sla) {
        return '';
    }
    const fragments = [];
    if (sla.phase) {
        fragments.push(`Phase: ${capitalize(sla.phase)}`);
    }
    if (sla.delay_minutes !== null && sla.delay_minutes !== undefined) {
        fragments.push(`Delay ${formatMinutesLabel(sla.delay_minutes)}`);
    }
    if (sla.promised_time) {
        fragments.push(`Due ${formatDateTime(sla.promised_time)}`);
    }
    if (!fragments.length) {
        return '';
    }
    return `<small class="text-muted d-block">${fragments.join(' · ')}</small>`;
}

function renderPricingMeta(delivery) {
    if (!hasPricingData(delivery)) {
        return '';
    }

    const provider = formatProvider(delivery.pricing_provider);
    const fee = formatCurrency(delivery.pricing_fee_applied);
    const distance = delivery.pricing_distance_m ? `${(Number(delivery.pricing_distance_m) / 1000).toFixed(2)} km` : 'Distance N/A';
    const rule = delivery.pricing_rule_name || 'Unnamed rule';
    const cacheBadge = delivery.pricing_cache_hit ? '<span class="badge bg-success me-1">Cache</span>' : '';
    const fallbackBadge = delivery.pricing_fallback_used ? '<span class="badge bg-warning text-dark me-1">Fallback</span>' : '';
    const providerBadge = provider ? `<span class="badge bg-secondary me-1">${provider}</span>` : '';
    const calculated = delivery.pricing_calculated_at ? `<small class="text-muted d-block">Calculated ${formatRelativeTime(delivery.pricing_calculated_at)}</small>` : '';

    return `
        <div class="mb-2">
            <div class="mb-1">
                ${providerBadge}${cacheBadge}${fallbackBadge}
            </div>
            <small class="text-muted d-block">Fee: <strong>${fee}</strong></small>
            <small class="text-muted d-block">Distance: ${distance}</small>
            <small class="text-muted d-block">Rule: ${rule}</small>
            ${calculated}
        </div>
    `;
}

function buildPricingPopup(delivery) {
    if (!hasPricingData(delivery)) {
        return '';
    }

    const provider = formatProvider(delivery.pricing_provider);
    const fee = formatCurrency(delivery.pricing_fee_applied);
    const distance = delivery.pricing_distance_m ? `${(Number(delivery.pricing_distance_m) / 1000).toFixed(2)} km` : 'N/A';
    const details = [`Fee ${fee}`, `Distance ${distance}`];
    if (provider) {
        details.unshift(provider);
    }
    if (delivery.pricing_cache_hit) {
        details.push('Cache hit');
    }
    if (delivery.pricing_fallback_used) {
        details.push('Fallback');
    }
    return details.join(' • ');
}

function buildPricingModalSection(delivery) {
    if (!hasPricingData(delivery)) {
        return '';
    }

    const provider = formatProvider(delivery.pricing_provider) || 'Unknown';
    const fee = formatCurrency(delivery.pricing_fee_applied);
    const distance = delivery.pricing_distance_m ? `${(Number(delivery.pricing_distance_m) / 1000).toFixed(2)} km` : 'N/A';
    const rule = delivery.pricing_rule_name || 'Unnamed rule';
    const cache = delivery.pricing_cache_hit ? 'Yes' : 'No';
    const fallback = delivery.pricing_fallback_used ? 'Yes' : 'No';
    const calculatedAt = delivery.pricing_calculated_at ? formatDateTime(delivery.pricing_calculated_at) : 'N/A';
    const requestId = delivery.pricing_request_id || 'N/A';

    return `
        <div class="row mt-3">
            <div class="col-12">
                <h6>Pricing Details</h6>
                <div class="row small text-muted">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Fee:</strong> ${fee}</p>
                        <p class="mb-1"><strong>Distance:</strong> ${distance}</p>
                        <p class="mb-1"><strong>Rule:</strong> ${rule}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Provider:</strong> ${provider}</p>
                        <p class="mb-1"><strong>Cache Hit:</strong> ${cache}</p>
                        <p class="mb-1"><strong>Fallback:</strong> ${fallback}</p>
                        <p class="mb-1"><strong>Calculated:</strong> ${calculatedAt}</p>
                        <p class="mb-0"><strong>Request ID:</strong> <span class="text-break">${requestId}</span></p>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function hasPricingData(delivery) {
    return Boolean(delivery && delivery.pricing_fee_applied !== null && delivery.pricing_fee_applied !== undefined);
}

function formatCurrency(value) {
    if (value === null || value === undefined || value === '') {
        return `${currencySymbol} 0.00`;
    }
    const number = Number(value) || 0;
    return `${currencySymbol} ${number.toFixed(2)}`;
}

function formatProvider(provider) {
    if (!provider) {
        return '';
    }
    return provider.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
}

function formatRiderPhone(phone) {
    if (!phone) {
        return 'N/A';
    }
    if (!maskRiderPhones) {
        return phone;
    }
    return maskPhoneNumber(phone);
}

function maskPhoneNumber(phone) {
    const digits = phone.replace(/[^0-9]/g, '');
    if (digits.length <= 4) {
        return '*'.repeat(Math.max(0, digits.length - 1)) + digits.slice(-1);
    }
    const lastFour = digits.slice(-4);
    return `${'*'.repeat(Math.max(0, digits.length - 4))}${lastFour}`;
}

function markerIcon(color) {
    if (typeof google === 'undefined') {
        return null;
    }
    return {
        path: google.maps.SymbolPath.CIRCLE,
        scale: 8,
        fillColor: color,
        fillOpacity: 0.9,
        strokeColor: '#ffffff',
        strokeWeight: 2
    };
}

function getMarkerColor(delivery) {
    const sla = delivery.sla || {};
    if (sla.is_at_risk) {
        return '#dc3545';
    }
    if (sla.is_late) {
        return '#fd7e14';
    }
    switch ((delivery.status || '').toLowerCase()) {
        case 'pending':
            return '#6c757d';
        case 'assigned':
            return '#ffc107';
        case 'picked-up':
            return '#0dcaf0';
        case 'in-transit':
            return '#0d6efd';
        case 'delivered':
            return '#198754';
        default:
            return '#6c757d';
    }
}

function buildMarkerInfo(delivery, supportsPricingAudit) {
    const slaBadge = renderSlaBadge(delivery.sla || {}) || '';
    const pricingDetails = supportsPricingAudit ? buildPricingPopup(delivery) : '';
    const riderPhone = delivery.rider_phone ? formatRiderPhone(delivery.rider_phone) : 'N/A';
    const vehicleDetails = [delivery.vehicle_make, delivery.vehicle_color, delivery.vehicle_type]
        .filter(Boolean)
        .join(' · ');
    const plateLine = delivery.vehicle_number ? `Plate: ${escapeHtml(delivery.vehicle_number)}` : '';
    const licenseLine = delivery.license_number ? `License: ${escapeHtml(delivery.license_number)}` : '';
    const platePhotoLink = delivery.vehicle_plate_photo_url
        ? `<br><a href="${escapeHtml(delivery.vehicle_plate_photo_url)}" target="_blank" rel="noopener">Plate photo</a>`
        : '';

    return `
        <div class="map-info">
            <strong>${escapeHtml(delivery.rider_name || 'Unassigned')}</strong><br>
            <small>Phone: ${escapeHtml(riderPhone)}</small><br>
            ${vehicleDetails ? `<small>${escapeHtml(vehicleDetails)}</small><br>` : ''}
            ${plateLine ? `<small>${plateLine}</small><br>` : ''}
            ${licenseLine ? `<small>${licenseLine}</small><br>` : ''}
            ${platePhotoLink}
            <hr class="my-2">
            <small>Order #${escapeHtml(delivery.order_number)}</small><br>
            <small>Status: ${escapeHtml(capitalize(delivery.status || 'pending'))}</small>
            ${slaBadge ? `<div class="mt-1">${slaBadge}</div>` : ''}
            <small class="d-block text-muted">Started ${Number(delivery.elapsed_minutes || 0)} min ago</small>
            ${pricingDetails ? `<div class="mt-2 text-muted small">${pricingDetails}</div>` : ''}
        </div>
    `;
}

function updateRouteForDelivery(delivery) {
    if (!googleMap || !directionsService) {
        return;
    }

    const markerKey = String(delivery.id);
    const originLat = Number(delivery.current_latitude);
    const originLng = Number(delivery.current_longitude);
    const destLat = Number(delivery.delivery_latitude);
    const destLng = Number(delivery.delivery_longitude);

    if (!Number.isFinite(originLat) || !Number.isFinite(originLng) || !Number.isFinite(destLat) || !Number.isFinite(destLng)) {
        removeRoute(markerKey);
        return;
    }

    const lastRequested = routeRequestCache.get(markerKey) || 0;
    if (Date.now() - lastRequested < ROUTE_REFRESH_MS) {
        return;
    }
    routeRequestCache.set(markerKey, Date.now());

    const request = {
        origin: { lat: originLat, lng: originLng },
        destination: { lat: destLat, lng: destLng },
        travelMode: google.maps.TravelMode.DRIVING,
    };

    directionsService.route(request, (result, status) => {
        if (status !== google.maps.DirectionsStatus.OK || !result) {
            return;
        }

        let rendererEntry = routeRenderers.get(markerKey);
        if (!rendererEntry) {
            const renderer = new google.maps.DirectionsRenderer({
                map: googleMap,
                suppressMarkers: true,
                preserveViewport: true,
                polylineOptions: {
                    strokeColor: getMarkerColor(delivery),
                    strokeOpacity: 0.7,
                    strokeWeight: 4
                }
            });
            rendererEntry = { renderer };
            routeRenderers.set(markerKey, rendererEntry);
        }

        rendererEntry.renderer.setDirections(result);
    });
}

function cleanupOrphanRoutes(activeIds) {
    for (const [key, entry] of routeRenderers.entries()) {
        if (!activeIds.has(key)) {
            entry.renderer.setMap(null);
            routeRenderers.delete(key);
            routeRequestCache.delete(key);
        }
    }
}

function removeRoute(key) {
    const entry = routeRenderers.get(key);
    if (entry) {
        entry.renderer.setMap(null);
        routeRenderers.delete(key);
    }
    routeRequestCache.delete(key);
}

async function ensureGoogleMapsLoaded() {
    if (typeof window === 'undefined') {
        return null;
    }
    if (window.google && window.google.maps) {
        return window.google;
    }
    if (!googleMapsConfig.apiKey) {
        if (!googleMapsErrored) {
            console.warn('Google Maps API key missing; map features disabled.');
            googleMapsErrored = true;
        }
        return null;
    }
    if (googleMapsLoader) {
        return googleMapsLoader;
    }

    googleMapsLoader = new Promise((resolve, reject) => {
        const scriptId = 'google-maps-sdk';
        if (document.getElementById(scriptId)) {
            document.getElementById(scriptId).addEventListener('load', () => resolve(window.google));
            document.getElementById(scriptId).addEventListener('error', () => reject(new Error('Google Maps failed to load')));
            return;
        }

        const script = document.createElement('script');
        script.id = scriptId;
        script.async = true;
        script.defer = true;
        const encodedKey = encodeURIComponent(googleMapsConfig.apiKey);
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodedKey}&libraries=geometry`;
        script.onload = () => resolve(window.google);
        script.onerror = () => {
            googleMapsErrored = true;
            reject(new Error('Google Maps failed to load'));
        };

        document.head.appendChild(script);
    }).catch(error => {
        console.error(error);
        return null;
    });

    return googleMapsLoader;
}

function initGoogleMap() {
    if (!document.getElementById('deliveryMap')) {
        return;
    }
    googleMap = new google.maps.Map(document.getElementById('deliveryMap'), {
        center: defaultMapCenter,
        zoom: 12,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false,
    });
    directionsService = new google.maps.DirectionsService();
    mapInfoWindow = new google.maps.InfoWindow();
}

// Trigger initial map load eagerly so the first refresh paints faster
ensureGoogleMapsLoaded().then(lib => {
    if (lib && !googleMap) {
        initGoogleMap();
        updateMapMarkers(latestDeliverySnapshot.deliveries || [], latestDeliverySnapshot.supportsPricingAudit);
    }
});

function formatRelativeTime(value) {
    if (!value) {
        return '';
    }
    const date = new Date(value);
    const diff = Date.now() - date.getTime();
    const minutes = Math.round(diff / 60000);
    if (minutes < 1) {
        return 'just now';
    }
    if (minutes < 60) {
        return `${minutes} min ago`;
    }
    const hours = Math.round(minutes / 60);
    if (hours < 24) {
        return `${hours} hr ago`;
    }
    const days = Math.round(hours / 24);
    return `${days} day${days !== 1 ? 's' : ''} ago`;
}

function formatMinutes(value) {
    const minutes = parseFloat(value || 0);
    return minutes ? `${minutes.toFixed(1)} min` : 'N/A';
}

function formatMinutesLabel(value) {
    const minutes = Number(value);
    if (!Number.isFinite(minutes)) {
        return '--';
    }
    return `${Math.round(minutes)} min`;
}

function formatRating(value) {
    const rating = parseFloat(value || 0);
    return rating ? `${rating.toFixed(1)} / 5` : 'N/A';
}

function calculateOnTimeRate(rider) {
    if (!rider.total_deliveries) {
        return 'N/A';
    }
    const rate = (Number(rider.successful_deliveries || 0) / Number(rider.total_deliveries)) * 100;
    return `${rate.toFixed(0)}% on-time`;
}

function formatDateTime(value) {
    if (!value) return null;
    return new Date(value).toLocaleString();
}

function capitalize(value) {
    if (!value) return '';
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function getStatusColor(status) {
    switch ((status || '').toLowerCase()) {
        case 'pending':
            return 'warning';
        case 'assigned':
        case 'picked-up':
        case 'in-transit':
            return 'primary';
        case 'delivered':
            return 'success';
        case 'failed':
            return 'danger';
        default:
            return 'secondary';
    }
}

function escapeHtml(value) {
    if (value === null || value === undefined) {
        return '';
    }
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function updateThresholdUi() {
    const slaText = `SLA Targets · Pending ${deliveryConfig.sla.pending} min · Assigned ${deliveryConfig.sla.assigned} min · Delivery ${deliveryConfig.sla.delivery} min (+${deliveryConfig.sla.slack} min slack)`;
    const slaTargetsLabel = document.getElementById('slaTargetsLabel');
    if (slaTargetsLabel) {
        slaTargetsLabel.textContent = slaText;
    }
    const maxActiveBadge = document.getElementById('maxActiveJobsBadge');
    if (maxActiveBadge) {
        maxActiveBadge.textContent = `Max active: ${MAX_ACTIVE_JOBS}`;
    }
    const slackBadge = document.getElementById('slaSlackBadge');
    if (slackBadge) {
        slackBadge.textContent = `Slack: ${deliveryConfig.sla.slack} min`;
    }
    const pendingBadge = document.getElementById('slaPendingBadge');
    if (pendingBadge) {
        pendingBadge.textContent = `Pending: ${deliveryConfig.sla.pending} min`;
    }
    const assignedBadge = document.getElementById('slaAssignedBadge');
    if (assignedBadge) {
        assignedBadge.textContent = `Assigned: ${deliveryConfig.sla.assigned} min`;
    }
    const deliveryBadge = document.getElementById('slaDeliveryBadge');
    if (deliveryBadge) {
        deliveryBadge.textContent = `Delivery: ${deliveryConfig.sla.delivery} min`;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', async function() {
    updateThresholdUi();
    updateDeliveryStats(initialStats, null, false);
    updateActiveDeliveries(latestDeliverySnapshot.deliveries, latestDeliverySnapshot.supportsPricingAudit);
    updateMapMarkers(latestDeliverySnapshot.deliveries, latestDeliverySnapshot.supportsPricingAudit);
    refreshDeliveryData();
});
</script>

<?php 
include 'includes/footer.php'; 
?>
