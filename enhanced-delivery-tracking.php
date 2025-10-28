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
                    <h3 class="text-primary"><?= $stats['active_deliveries'] ?></h3>
                    <p class="text-muted mb-0">Active Deliveries</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-clock text-warning fs-1 mb-2"></i>
                    <h3 class="text-warning"><?= $stats['pending_deliveries'] ?></h3>
                    <p class="text-muted mb-0">Pending Assignment</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle text-success fs-1 mb-2"></i>
                    <h3 class="text-success"><?= $stats['completed_today'] ?></h3>
                    <p class="text-muted mb-0">Completed Today</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-speedometer text-info fs-1 mb-2"></i>
                    <h3 class="text-info"><?= round($stats['average_delivery_time']) ?> min</h3>
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
                    <div id="deliveryMap" style="height: 400px; background: #f8f9fa; border-radius: 8px; position: relative;">
                        <div class="d-flex align-items-center justify-content-center h-100">
                            <div class="text-center">
                                <i class="bi bi-map fs-1 text-muted mb-3"></i>
                                <h5 class="text-muted">Interactive Delivery Map</h5>
                                <p class="text-muted">Real-time rider locations and delivery routes</p>
                                <button class="btn btn-primary" onclick="initializeMap()">
                                    <i class="bi bi-geo-alt me-2"></i>Load Map
                                </button>
                            </div>
                        </div>
                    </div>
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
                    <div id="riderPerformance">
                        <!-- Rider performance metrics will be loaded here -->
                    </div>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let deliveryChart;
let mapInitialized = false;

// Auto-refresh every 30 seconds
setInterval(refreshDeliveryData, 30000);

function refreshDeliveryData() {
    fetch('api/get-delivery-dashboard-data.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDeliveryStats(data.stats);
                updateActiveDeliveries(data.deliveries);
                updateDeliveryChart(data.chartData);
            }
        })
        .catch(error => console.error('Error refreshing delivery data:', error));
}

function updateDeliveryStats(stats) {
    // Update statistics cards with new data
    document.querySelector('.text-primary h3').textContent = stats.active_deliveries;
    document.querySelector('.text-warning h3').textContent = stats.pending_deliveries;
    document.querySelector('.text-success h3').textContent = stats.completed_today;
    document.querySelector('.text-info h3').textContent = stats.average_delivery_time + ' min';
}

function trackDelivery(deliveryId) {
    fetch(`api/get-delivery-tracking.php?delivery_id=${deliveryId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showTrackingModal(data.data);
            }
        })
        .catch(error => console.error('Error tracking delivery:', error));
}

function showTrackingModal(trackingData) {
    const content = `
        <div class="row">
            <div class="col-md-6">
                <h6>Order Information</h6>
                <p><strong>Order #:</strong> ${trackingData.order_number}</p>
                <p><strong>Customer:</strong> ${trackingData.customer_name}</p>
                <p><strong>Phone:</strong> ${trackingData.customer_phone}</p>
                <p><strong>Address:</strong> ${trackingData.delivery_address}</p>
            </div>
            <div class="col-md-6">
                <h6>Delivery Status</h6>
                <p><strong>Status:</strong> <span class="badge bg-primary">${trackingData.status}</span></p>
                <p><strong>Rider:</strong> ${trackingData.rider_name || 'Not assigned'}</p>
                <p><strong>Vehicle:</strong> ${trackingData.vehicle_type || 'N/A'}</p>
                <p><strong>Estimated Time:</strong> ${trackingData.estimated_time || 'N/A'}</p>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <h6>Delivery Timeline</h6>
                <div class="delivery-timeline">
                    <!-- Timeline will be rendered here -->
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('trackingContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('trackingModal')).show();
}

function contactCustomer(phone) {
    if (phone) {
        window.open(`tel:${phone}`);
    }
}

function initializeMap() {
    // Initialize delivery map with rider locations
    // This would integrate with Google Maps or similar service
    document.getElementById('deliveryMap').innerHTML = `
        <div class="d-flex align-items-center justify-content-center h-100">
            <div class="text-center">
                <div class="spinner-border text-primary mb-3"></div>
                <p>Loading map with rider locations...</p>
            </div>
        </div>
    `;
    
    // Simulate map loading
    setTimeout(() => {
        document.getElementById('deliveryMap').innerHTML = `
            <div class="p-3">
                <h6>Interactive Map Features:</h6>
                <ul class="list-unstyled">
                    <li><i class="bi bi-geo-alt text-primary me-2"></i>Real-time rider locations</li>
                    <li><i class="bi bi-arrow-right text-success me-2"></i>Optimized delivery routes</li>
                    <li><i class="bi bi-clock text-warning me-2"></i>ETA calculations</li>
                    <li><i class="bi bi-telephone text-info me-2"></i>Direct communication</li>
                </ul>
                <div class="alert alert-info">
                    <strong>Note:</strong> Map integration requires Google Maps API or similar service.
                </div>
            </div>
        `;
    }, 2000);
}

// Initialize delivery performance chart
function initDeliveryChart() {
    const ctx = document.getElementById('deliveryChart').getContext('2d');
    deliveryChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Deliveries Completed',
                data: [12, 19, 8, 15, 22, 18, 25],
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }, {
                label: 'Average Delivery Time (min)',
                data: [25, 22, 28, 20, 18, 24, 19],
                borderColor: 'rgb(255, 99, 132)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initDeliveryChart();
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
