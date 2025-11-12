<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

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
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($activeDeliveries)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-truck fs-1 mb-3"></i>
                        <p>No active deliveries</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($activeDeliveries as $delivery): ?>
                    <div class="delivery-item mb-3 p-3 border rounded" data-delivery-id="<?= $delivery['id'] ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong>#<?= htmlspecialchars($delivery['order_number']) ?></strong>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($delivery['customer_name']) ?></small>
                            </div>
                            <span class="badge bg-<?= getStatusColor($delivery['status']) ?>"><?= ucfirst($delivery['status']) ?></span>
                        </div>
                        
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="bi bi-person me-1"></i><?= htmlspecialchars($delivery['rider_name'] ?? 'Not assigned') ?>
                            </small>
                            <?php if ($delivery['rider_phone']): ?>
                            <br>
                            <small class="text-muted">
                                <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($delivery['rider_phone']) ?>
                            </small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-2">
                            <small class="text-muted"><?= htmlspecialchars($delivery['status_description']) ?></small>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i><?= $delivery['elapsed_minutes'] ?> min ago
                            </small>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="trackDelivery(<?= $delivery['id'] ?>)">
                                    <i class="bi bi-geo-alt"></i>
                                </button>
                                <button class="btn btn-outline-info" onclick="contactCustomer('<?= $delivery['customer_phone'] ?>')">
                                    <i class="bi bi-telephone"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
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

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let deliveryChart;
let deliveryMap;
let riderLayerGroup;

// Auto-refresh every 30 seconds
setInterval(refreshDeliveryData, 30000);

async function refreshDeliveryData() {
    try {
        const response = await fetch('api/get-delivery-dashboard-data.php');
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Failed to load delivery data');
        }
        updateDeliveryStats(data.stats);
        updateActiveDeliveries(data.deliveries);
        updateDeliveryChart(data.chartData);
        updateRiderPerformance(data.riderPerformance);
        updateMapMarkers(data.deliveries);
    } catch (error) {
        console.error('Error refreshing delivery data:', error);
    }
}

function updateDeliveryStats(stats) {
    document.getElementById('activeDeliveriesCount').textContent = stats.active_deliveries;
    document.getElementById('pendingDeliveriesCount').textContent = stats.pending_deliveries;
    document.getElementById('completedTodayCount').textContent = stats.completed_today;
    document.getElementById('avgDeliveryTime').textContent = `${stats.average_delivery_time} min`;
}

function updateActiveDeliveries(deliveries) {
    const container = document.querySelector('.col-md-4 .card-body');
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
        return `
            <div class="delivery-item mb-3 p-3 border rounded" data-delivery-id="${delivery.id}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <strong>#${delivery.order_number}</strong><br>
                        <small class="text-muted">${delivery.customer_name}</small>
                    </div>
                    <span class="badge bg-${getStatusColor(delivery.status)}">${capitalize(delivery.status)}</span>
                </div>
                <div class="mb-2">
                    <small class="text-muted">
                        <i class="bi bi-person me-1"></i>${delivery.rider_name || 'Not assigned'}
                    </small>
                    ${delivery.rider_phone ? `<br><small class="text-muted"><i class="bi bi-telephone me-1"></i>${delivery.rider_phone}</small>` : ''}
                </div>
                <div class="mb-2">
                    <small class="text-muted">Started ${delivery.elapsed_minutes} min ago</small>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">KES ${Number(delivery.total_amount).toFixed(2)}</small>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="trackDelivery(${delivery.order_id})">
                            <i class="bi bi-geo-alt"></i>
                        </button>
                        <button class="btn btn-outline-info" onclick="contactCustomer('${delivery.customer_phone || ''}')">
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

function updateMapMarkers(deliveries) {
    if (!deliveryMap) {
        deliveryMap = L.map('deliveryMap').setView([ -1.2921, 36.8219 ], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(deliveryMap);
        riderLayerGroup = L.layerGroup().addTo(deliveryMap);
    }

    riderLayerGroup.clearLayers();
    deliveries.filter(delivery => delivery.current_latitude && delivery.current_longitude).forEach(delivery => {
        const marker = L.marker([delivery.current_latitude, delivery.current_longitude]);
        marker.bindPopup(`
            <strong>${delivery.rider_name || 'Unassigned'}</strong><br>
            Order: #${delivery.order_number}<br>
            Status: ${capitalize(delivery.status)}<br>
            Started: ${delivery.elapsed_minutes} min ago
        `);
        riderLayerGroup.addLayer(marker);
    });
}

async function trackDelivery(orderId) {
    try {
        const response = await fetch(`api/get-delivery-tracking.php?order_id=${orderId}`);
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Unable to fetch delivery tracking');
        }
        showTrackingModal(data.data);
    } catch (error) {
        console.error('Error tracking delivery:', error);
        alert('Unable to load delivery tracking details.');
    }
}

function showTrackingModal(trackingData) {
    const timelineHtml = buildTimeline(trackingData.timeline || []);
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
            </div>
        </div>
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

function formatMinutes(value) {
    const minutes = parseFloat(value || 0);
    return minutes ? `${minutes.toFixed(1)} min` : 'N/A';
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

// Initialize on page load
document.addEventListener('DOMContentLoaded', async function() {
    refreshDeliveryData();
});
</script>

<?php 
function getStatusColor($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'assigned': return 'info';
        case 'picked-up': return 'primary';
        case 'in-transit': return 'primary';
        case 'delivered': return 'success';
        case 'failed': return 'danger';
        default: return 'secondary';
    }
}

include 'includes/footer.php'; 
?>
