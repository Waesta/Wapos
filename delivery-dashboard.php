<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

if (!$auth->hasRole('admin') && !$auth->hasRole('manager')) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Delivery Operations';
include 'includes/header.php';
?>

<style>
    .metric-card {
        border-left: 4px solid var(--bs-primary);
        transition: transform 0.2s ease;
    }
    .metric-card:hover {
        transform: translateY(-2px);
    }
    .rider-status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 0.35rem;
    }
    .dashboard-section {
        min-height: 260px;
    }
    .active-badge {
        font-size: 0.65rem;
        padding: 0.2rem 0.5rem;
        border-radius: 2rem;
    }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-truck me-2 text-primary"></i>Delivery Operations Dashboard</h4>
        <p class="text-muted mb-0">Live view of delivery workload, rider activity, and SLA risks.</p>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <button id="refreshBtn" class="btn btn-outline-primary">
            <i class="bi bi-arrow-repeat me-1"></i>Refresh
        </button>
        <a href="enhanced-delivery-tracking.php" class="btn btn-primary">
            <i class="bi bi-map me-1"></i>Open Tracking Console
        </a>
    </div>
</div>

<div id="alertContainer"></div>

<div class="row g-3 mb-3" id="metricRow">
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm metric-card">
            <div class="card-body">
                <p class="text-muted mb-1 small">Active Deliveries</p>
                <h3 class="fw-bold mb-0" data-metric="active_deliveries">--</h3>
                <small class="text-muted" data-metric="active_change"></small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm metric-card border-primary">
            <div class="card-body">
                <p class="text-muted mb-1 small">On-Time Rate (24h)</p>
                <h3 class="fw-bold mb-0" data-metric="on_time_rate">--</h3>
                <small class="text-muted">Completed in SLA</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm metric-card border-success">
            <div class="card-body">
                <p class="text-muted mb-1 small">Avg. Delivery Duration</p>
                <h3 class="fw-bold mb-0" data-metric="avg_duration">--</h3>
                <small class="text-muted">Rolling 24 hours</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm metric-card border-warning">
            <div class="card-body">
                <p class="text-muted mb-1 small">Orders At Risk</p>
                <h3 class="fw-bold mb-0" data-metric="at_risk">--</h3>
                <small class="text-muted">ETA beyond SLA</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xxl-7">
        <div class="card shadow-sm dashboard-section" id="activeDeliveriesCard">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>Active Deliveries</h6>
                <span class="badge bg-primary" id="activeDeliveriesCount">0</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="activeDeliveriesTable">
                        <thead class="table-light">
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th>Rider</th>
                                <th class="text-end">ETA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <div class="spinner-border text-primary" role="status"></div>
                                    <div class="small mt-2">Loading active deliveries…</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xxl-5">
        <div class="card shadow-sm dashboard-section mb-3" id="riderStatusCard">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-people me-2"></i>Rider Status</h6>
                <span class="badge bg-secondary" id="activeRidersCount">0 Riders</span>
            </div>
            <div class="card-body">
                <div id="riderStatusList" class="list-group list-group-flush">
                    <div class="text-center py-3 text-muted">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <div class="small mt-2">Fetching rider positions…</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card shadow-sm dashboard-section" id="slaRiskCard">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Potential SLA Breaches</h6>
                <span class="badge bg-warning text-dark" id="slaRiskCount">0</span>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush" id="slaRiskList">
                    <li class="list-group-item text-muted text-center small">No orders at risk</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
const alertContainer = document.getElementById('alertContainer');
const refreshBtn = document.getElementById('refreshBtn');

const metricElements = {
    active_deliveries: document.querySelector('[data-metric="active_deliveries"]'),
    active_change: document.querySelector('[data-metric="active_change"]'),
    on_time_rate: document.querySelector('[data-metric="on_time_rate"]'),
    avg_duration: document.querySelector('[data-metric="avg_duration"]'),
    at_risk: document.querySelector('[data-metric="at_risk"]')
};

const tables = {
    activeDeliveries: document.querySelector('#activeDeliveriesTable tbody'),
    riderStatus: document.getElementById('riderStatusList'),
    slaRisk: document.getElementById('slaRiskList')
};

const badges = {
    activeDeliveries: document.getElementById('activeDeliveriesCount'),
    activeRiders: document.getElementById('activeRidersCount'),
    slaRisk: document.getElementById('slaRiskCount')
};

function showAlert(type, message) {
    const wrapper = document.createElement('div');
    wrapper.className = `alert alert-${type} alert-dismissible fade show`;
    wrapper.innerHTML = `
        <div>${message}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    alertContainer.append(wrapper);
    setTimeout(() => wrapper.remove(), 6000);
}

function formatDuration(minutes) {
    if (!minutes && minutes !== 0) return '--';
    const hrs = Math.floor(minutes / 60);
    const mins = Math.round(minutes % 60);
    if (hrs <= 0) return `${mins} min`;
    return `${hrs}h ${mins}m`;
}

function formatEta(eta) {
    if (!eta) return '--';
    try {
        const date = new Date(eta);
        if (Number.isNaN(date.getTime())) return eta;
        return date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    } catch (err) {
        return eta;
    }
}

function statusVariant(status) {
    const map = {
        pending: 'secondary',
        assigned: 'info',
        'picked-up': 'primary',
        'in-transit': 'primary',
        delivered: 'success',
        failed: 'danger',
        cancelled: 'dark'
    };
    return map[status] || 'secondary';
}

async function loadDashboardData() {
    try {
        refreshBtn.disabled = true;
        const response = await fetch('api/get-delivery-dashboard-data.php');
        if (!response.ok) {
            throw new Error('Failed to fetch dashboard data');
        }
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Delivery dashboard data unavailable');
        }
        const stats = result.stats || result.summary || {};
        renderMetrics(stats);
        renderActiveDeliveries((result.deliveries || result.active_deliveries || []).map(delivery => ({
            ...delivery,
            sla: delivery.sla || null
        })));
        renderRiderStatus(result.riders || []);
        renderSlaRisk(result.sla_risk || []);
    } catch (error) {
        showAlert('danger', error.message);
    } finally {
        refreshBtn.disabled = false;
    }
}

function renderMetrics(summary) {
    metricElements.active_deliveries.textContent = summary.active_deliveries ?? summary.active_deliveries_current ?? '--';
    metricElements.on_time_rate.textContent = summary.on_time_rate ? `${summary.on_time_rate}%` : '--';
    metricElements.avg_duration.textContent = summary.average_delivery_time ? formatDuration(summary.average_delivery_time) : (summary.avg_delivery_time ? formatDuration(summary.avg_delivery_time) : '--');
    metricElements.at_risk.textContent = summary.orders_at_risk ?? summary.pending_at_risk ?? '--';

    const change = summary.active_change ?? 0;
    const changeLabel = change > 0 ? `▲ ${change}` : change < 0 ? `▼ ${Math.abs(change)}` : 'No change';
    metricElements.active_change.textContent = changeLabel;
}

function renderActiveDeliveries(deliveries) {
    badges.activeDeliveries.textContent = deliveries.length;
    if (!deliveries.length) {
        tables.activeDeliveries.innerHTML = `
            <tr>
                <td colspan="6" class="py-4 text-center text-muted">
                    <i class="bi bi-inbox mt-2 mb-2 fs-2"></i>
                    <div>No active deliveries</div>
                </td>
            </tr>
        `;
        return;
    }

    tables.activeDeliveries.innerHTML = deliveries.map(delivery => {
        const status = delivery.status ?? 'pending';
        const badge = statusVariant(status);
        const sla = delivery.sla || {};
        const eta = formatEta(delivery.estimated_delivery_time || sla.promised_time);
        const rider = delivery.rider_name || 'Unassigned';
        const customer = delivery.customer_name || delivery.recipient_name || 'Customer';
        const orderLabel = delivery.order_number ? `#${delivery.order_number}` : (delivery.order_id ?? 'Order');
        const address = delivery.delivery_address || '—';
        const badgeExtras = [];
        if (sla.is_at_risk) {
            badgeExtras.push('<span class="badge bg-danger active-badge ms-1">SLA risk</span>');
        } else if (sla.is_late) {
            badgeExtras.push('<span class="badge bg-warning text-dark active-badge ms-1">Late</span>');
        }
        const delayText = sla.delay_minutes ? `<div class="small text-danger">+${sla.delay_minutes} min</div>` : '';
        return `
            <tr>
                <td class="fw-semibold">${orderLabel}</td>
                <td>${customer}</td>
                <td class="text-truncate" style="max-width: 220px;" title="${address}">${address}</td>
                <td><span class="badge bg-${badge} active-badge">${status.replace(/_/g, ' ')}</span>${badgeExtras.join('')}</td>
                <td>${rider}</td>
                <td class="text-end">
                    ${eta}
                    ${delayText}
                </td>
            </tr>
        `;
    }).join('');
}

function renderRiderStatus(riders) {
    const palette = {
        available: 'var(--bs-success)',
        busy: 'var(--bs-warning)',
        offline: 'var(--bs-secondary)'
    };

    if (!riders.length) {
        badges.activeRiders.textContent = '0 Riders';
        tables.riderStatus.innerHTML = `
            <div class="text-center py-4 text-muted">
                <i class="bi bi-person-dash fs-2"></i>
                <div class="mt-2">No rider data yet</div>
            </div>
        `;
        return;
    }

    badges.activeRiders.textContent = `${riders.length} Riders`;
    tables.riderStatus.innerHTML = riders.map(rider => {
        const status = rider.status || 'offline';
        const dotColor = palette[status] || palette.offline;
        const deliveries = rider.active_deliveries ?? 0;
        const subtitle = rider.last_location_update ? new Date(rider.last_location_update).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) : 'No recent update';
        return `
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="javascript:void(0)">
                <div>
                    <div class="fw-semibold">${rider.name || 'Rider'}</div>
                    <small class="text-muted">${subtitle}</small>
                </div>
                <div class="text-end">
                    <span class="rider-status-dot" style="background:${dotColor}"></span>
                    <span class="badge bg-light text-dark">${status.replace(/_/g, ' ')}</span>
                    ${deliveries ? `<div class="small text-muted">${deliveries} active</div>` : ''}
                </div>
            </a>
        `;
    }).join('');
}

function renderSlaRisk(atRisk) {
    badges.slaRisk.textContent = atRisk.length;
    if (!atRisk.length) {
        tables.slaRisk.innerHTML = '<li class="list-group-item text-center text-muted small">No orders at risk</li>';
        return;
    }

    tables.slaRisk.innerHTML = atRisk.map(entry => {
        const eta = formatEta(entry.estimated_delivery_time || entry.promised_time);
        const delay = entry.delay_minutes ? `${entry.delay_minutes} min late` : 'ETA exceeded';
        return `
            <li class="list-group-item d-flex justify-content-between align-items-start">
                <div>
                    <div class="fw-semibold">#${entry.order_number || entry.order_id}</div>
                    <small class="text-muted">${entry.customer_name || 'Customer'} · ${entry.delivery_address || 'Address unavailable'}</small>
                </div>
                <div class="text-end">
                    <span class="badge bg-danger active-badge">${delay}</span>
                    <div class="small text-muted">ETA ${eta}</div>
                </div>
            </li>
        `;
    }).join('');
}

refreshBtn.addEventListener('click', loadDashboardData);

loadDashboardData();
setInterval(loadDashboardData, 60000);
</script>

<?php include 'includes/footer.php'; ?>
