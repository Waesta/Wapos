<?php
/**
 * Rider Portal - Web-based interface for delivery riders
 * Features: GPS tracking, delivery management, status updates
 */
require_once 'includes/bootstrap.php';

$auth->requireLogin();

$db = Database::getInstance();
$currentUser = $auth->getUser();

// Check if user is a rider
$isRider = in_array($currentUser['role'], ['rider', 'super_admin', 'admin', 'developer']);

if (!$isRider) {
    $_SESSION['error_message'] = 'Access denied. Rider role required.';
    redirect('index.php');
}

// Get rider information
$riderId = null;
$riderInfo = null;

if ($currentUser['role'] === 'rider') {
    $riderInfo = $db->fetchOne("SELECT * FROM riders WHERE user_id = ?", [$currentUser['id']]);
    if ($riderInfo) {
        $riderId = $riderInfo['id'];
    }
} else {
    // Admin/developer can select rider
    $riderId = $_GET['rider_id'] ?? null;
    if ($riderId) {
        $riderInfo = $db->fetchOne("SELECT * FROM riders WHERE id = ?", [$riderId]);
    }
}

// Get active deliveries for this rider
$activeDeliveries = [];
if ($riderId) {
    $activeDeliveries = $db->fetchAll("
        SELECT 
            d.*,
            o.order_number,
            o.customer_name,
            o.customer_phone,
            o.delivery_address,
            o.total_amount,
            o.payment_method,
            o.payment_status,
            TIMESTAMPDIFF(MINUTE, d.created_at, NOW()) as elapsed_minutes
        FROM deliveries d
        JOIN orders o ON d.order_id = o.id
        WHERE d.rider_id = ? 
        AND d.status IN ('assigned', 'picked-up', 'in-transit')
        ORDER BY d.created_at ASC
    ", [$riderId]);
}

// Get completed deliveries today
$completedToday = 0;
if ($riderId) {
    $result = $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM deliveries 
        WHERE rider_id = ? 
        AND status = 'delivered' 
        AND DATE(actual_delivery_time) = CURDATE()
    ", [$riderId]);
    $completedToday = $result['count'] ?? 0;
}

// Get Google Maps API key
$googleMapsApiKey = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'google_maps_api_key'");
$googleMapsApiKey = $googleMapsApiKey['setting_value'] ?? '';

// Get business location
$businessLat = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'business_latitude'");
$businessLng = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'business_longitude'");
$businessLat = $businessLat['setting_value'] ?? '-1.286389';
$businessLng = $businessLng['setting_value'] ?? '36.817223';

$pageTitle = 'Rider Portal';
include 'includes/header.php';
?>

<style>
.rider-portal {
    max-width: 100%;
    padding: 0;
}

.status-card {
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.status-card:hover {
    transform: translateY(-2px);
}

.delivery-card {
    border-left: 4px solid #0d6efd;
    transition: all 0.3s;
}

.delivery-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.delivery-card.urgent {
    border-left-color: #dc3545;
    background-color: #fff5f5;
}

.tracking-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
    animation: pulse 2s infinite;
}

.tracking-indicator.active {
    background-color: #198754;
}

.tracking-indicator.inactive {
    background-color: #6c757d;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

#riderMap {
    height: 400px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.location-status {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
    padding: 12px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    background: white;
}

.btn-action {
    min-width: 120px;
}

@media (max-width: 768px) {
    #riderMap {
        height: 300px;
    }
    
    .location-status {
        bottom: 10px;
        right: 10px;
        left: 10px;
        text-align: center;
    }
}
</style>

<div class="container-fluid rider-portal">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">
                        <i class="bi bi-bicycle me-2"></i>Rider Portal
                    </h4>
                    <?php if ($riderInfo): ?>
                        <p class="text-muted mb-0">
                            Welcome, <?= htmlspecialchars($riderInfo['name']) ?>
                            <?php if ($riderInfo['vehicle_type']): ?>
                                <span class="badge bg-secondary ms-2">
                                    <?= htmlspecialchars(ucfirst($riderInfo['vehicle_type'])) ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-primary" onclick="toggleTracking()" id="trackingToggle">
                        <i class="bi bi-geo-alt-fill me-1"></i>
                        <span id="trackingButtonText">Start Tracking</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$riderId): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            No rider profile found. Please contact administrator to set up your rider account.
        </div>
    <?php else: ?>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card status-card border-0">
                <div class="card-body text-center">
                    <i class="bi bi-truck text-primary fs-1 mb-2"></i>
                    <h3 class="mb-0" id="activeDeliveriesCount"><?= count($activeDeliveries) ?></h3>
                    <p class="text-muted mb-0 small">Active Deliveries</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card status-card border-0">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle text-success fs-1 mb-2"></i>
                    <h3 class="mb-0" id="completedTodayCount"><?= $completedToday ?></h3>
                    <p class="text-muted mb-0 small">Completed Today</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card status-card border-0">
                <div class="card-body text-center">
                    <i class="bi bi-speedometer2 text-info fs-1 mb-2"></i>
                    <h3 class="mb-0" id="currentSpeed">0</h3>
                    <p class="text-muted mb-0 small">Speed (km/h)</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card status-card border-0">
                <div class="card-body text-center">
                    <i class="bi bi-geo-alt text-warning fs-1 mb-2"></i>
                    <h3 class="mb-0">
                        <span class="tracking-indicator" id="trackingIndicator"></span>
                    </h3>
                    <p class="text-muted mb-0 small" id="trackingStatus">GPS Inactive</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Map and Deliveries -->
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">
                        <i class="bi bi-map me-2"></i>Live Map
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div id="riderMap"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0">
                        <i class="bi bi-list-check me-2"></i>Active Deliveries
                        <span class="badge bg-primary ms-2"><?= count($activeDeliveries) ?></span>
                    </h6>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($activeDeliveries)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-1 mb-3 d-block"></i>
                            <p>No active deliveries</p>
                            <small>New deliveries will appear here</small>
                        </div>
                    <?php else: ?>
                        <div id="deliveriesList">
                            <?php foreach ($activeDeliveries as $delivery): ?>
                                <?php 
                                $isUrgent = $delivery['elapsed_minutes'] > 30;
                                $statusColor = [
                                    'assigned' => 'warning',
                                    'picked-up' => 'info',
                                    'in-transit' => 'primary'
                                ][$delivery['status']] ?? 'secondary';
                                ?>
                                <div class="delivery-card card mb-3 <?= $isUrgent ? 'urgent' : '' ?>" data-delivery-id="<?= $delivery['id'] ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1">Order #<?= htmlspecialchars($delivery['order_number']) ?></h6>
                                                <small class="text-muted">
                                                    <i class="bi bi-clock me-1"></i><?= $delivery['elapsed_minutes'] ?> min ago
                                                </small>
                                            </div>
                                            <span class="badge bg-<?= $statusColor ?>">
                                                <?= ucfirst(str_replace('-', ' ', $delivery['status'])) ?>
                                            </span>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <small class="text-muted d-block">
                                                <i class="bi bi-person me-1"></i><?= htmlspecialchars($delivery['customer_name']) ?>
                                            </small>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($delivery['customer_phone']) ?>
                                            </small>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($delivery['delivery_address']) ?>
                                            </small>
                                        </div>

                                        <div class="d-flex gap-2 mt-3">
                                            <?php if ($delivery['status'] === 'assigned'): ?>
                                                <button class="btn btn-sm btn-primary btn-action" onclick="updateDeliveryStatus(<?= $delivery['id'] ?>, 'picked-up')">
                                                    <i class="bi bi-box-seam me-1"></i>Pick Up
                                                </button>
                                            <?php elseif ($delivery['status'] === 'picked-up'): ?>
                                                <button class="btn btn-sm btn-info btn-action" onclick="updateDeliveryStatus(<?= $delivery['id'] ?>, 'in-transit')">
                                                    <i class="bi bi-truck me-1"></i>In Transit
                                                </button>
                                            <?php elseif ($delivery['status'] === 'in-transit'): ?>
                                                <button class="btn btn-sm btn-success btn-action" onclick="showDeliveryProof(<?= $delivery['id'] ?>)">
                                                    <i class="bi bi-check-circle me-1"></i>Delivered
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-sm btn-outline-primary" onclick="navigateToDelivery(<?= $delivery['delivery_latitude'] ?>, <?= $delivery['delivery_longitude'] ?>)">
                                                <i class="bi bi-navigation me-1"></i>Navigate
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<!-- Delivery Proof Modal -->
<div class="modal fade" id="deliveryProofModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delivery</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="proofDeliveryId">
                
                <div class="mb-3">
                    <label class="form-label">Delivery Notes (Optional)</label>
                    <textarea class="form-control" id="deliveryNotes" rows="3" placeholder="Any special notes about the delivery..."></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Proof Photo (Optional)</label>
                    <input type="file" class="form-control" id="deliveryPhoto" accept="image/*" capture="environment">
                    <small class="text-muted">Take a photo of the delivered package</small>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Your current location will be recorded with this delivery.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="confirmDelivery()">
                    <i class="bi bi-check-circle me-1"></i>Confirm Delivery
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Location Status Indicator -->
<div class="location-status" id="locationStatus" style="display: none;">
    <div class="d-flex align-items-center">
        <div class="spinner-border spinner-border-sm text-success me-2" role="status"></div>
        <span class="small">
            <strong>GPS Active</strong><br>
            <span id="locationAccuracy" class="text-muted"></span>
        </span>
    </div>
</div>

<script>
const RIDER_ID = <?= json_encode($riderId) ?>;
const GOOGLE_MAPS_API_KEY = <?= json_encode($googleMapsApiKey) ?>;
const BUSINESS_LOCATION = {
    lat: <?= (float)$businessLat ?>,
    lng: <?= (float)$businessLng ?>
};

let map = null;
let riderMarker = null;
let deliveryMarkers = {};
let trackingActive = false;
let watchId = null;
let currentPosition = null;
let updateInterval = null;

// Initialize map
async function initMap() {
    if (!GOOGLE_MAPS_API_KEY) {
        console.warn('Google Maps API key not configured');
        document.getElementById('riderMap').innerHTML = '<div class="alert alert-warning m-3">Google Maps API key not configured</div>';
        return;
    }

    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${GOOGLE_MAPS_API_KEY}&callback=createMap`;
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
}

function createMap() {
    map = new google.maps.Map(document.getElementById('riderMap'), {
        center: BUSINESS_LOCATION,
        zoom: 13,
        mapTypeControl: false,
        streetViewControl: false
    });

    // Add rider marker
    riderMarker = new google.maps.Marker({
        map: map,
        position: BUSINESS_LOCATION,
        title: 'Your Location',
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 10,
            fillColor: '#4285F4',
            fillOpacity: 1,
            strokeColor: '#ffffff',
            strokeWeight: 3
        }
    });

    // Add delivery markers
    addDeliveryMarkers();
}

function addDeliveryMarkers() {
    const deliveries = <?= json_encode($activeDeliveries) ?>;
    
    deliveries.forEach(delivery => {
        if (delivery.delivery_latitude && delivery.delivery_longitude) {
            const marker = new google.maps.Marker({
                map: map,
                position: {
                    lat: parseFloat(delivery.delivery_latitude),
                    lng: parseFloat(delivery.delivery_longitude)
                },
                title: delivery.customer_name,
                icon: {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32">
                            <circle cx="16" cy="16" r="14" fill="#dc3545" stroke="#fff" stroke-width="2"/>
                            <text x="16" y="21" font-size="16" fill="#fff" text-anchor="middle" font-weight="bold">📦</text>
                        </svg>
                    `),
                    scaledSize: new google.maps.Size(32, 32)
                }
            });

            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div style="padding: 8px;">
                        <strong>${delivery.customer_name}</strong><br>
                        <small>${delivery.delivery_address}</small><br>
                        <span class="badge bg-${delivery.status === 'assigned' ? 'warning' : 'info'} mt-1">
                            ${delivery.status}
                        </span>
                    </div>
                `
            });

            marker.addListener('click', () => {
                infoWindow.open(map, marker);
            });

            deliveryMarkers[delivery.id] = marker;
        }
    });
}

// GPS Tracking
function toggleTracking() {
    if (trackingActive) {
        stopTracking();
    } else {
        startTracking();
    }
}

function startTracking() {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
    }

    const options = {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0
    };

    watchId = navigator.geolocation.watchPosition(
        handlePositionUpdate,
        handlePositionError,
        options
    );

    trackingActive = true;
    updateTrackingUI();

    // Send updates every 30 seconds
    updateInterval = setInterval(() => {
        if (currentPosition) {
            sendLocationUpdate(currentPosition);
        }
    }, 30000);

    document.getElementById('locationStatus').style.display = 'block';
}

function stopTracking() {
    if (watchId) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }

    if (updateInterval) {
        clearInterval(updateInterval);
        updateInterval = null;
    }

    trackingActive = false;
    updateTrackingUI();
    document.getElementById('locationStatus').style.display = 'none';
}

function handlePositionUpdate(position) {
    currentPosition = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy,
        speed: position.coords.speed ? position.coords.speed * 3.6 : 0, // Convert m/s to km/h
        heading: position.coords.heading
    };

    // Update UI
    document.getElementById('currentSpeed').textContent = Math.round(currentPosition.speed);
    document.getElementById('locationAccuracy').textContent = `Accuracy: ${Math.round(currentPosition.accuracy)}m`;

    // Update map
    if (map && riderMarker) {
        const newPos = {
            lat: currentPosition.latitude,
            lng: currentPosition.longitude
        };
        riderMarker.setPosition(newPos);
        map.panTo(newPos);
    }

    // Send to server
    sendLocationUpdate(currentPosition);
}

function handlePositionError(error) {
    console.error('Geolocation error:', error);
    let message = 'Unable to get location';
    
    switch(error.code) {
        case error.PERMISSION_DENIED:
            message = 'Location permission denied';
            break;
        case error.POSITION_UNAVAILABLE:
            message = 'Location unavailable';
            break;
        case error.TIMEOUT:
            message = 'Location request timeout';
            break;
    }

    document.getElementById('trackingStatus').textContent = message;
}

function sendLocationUpdate(position) {
    fetch('/api/update-rider-location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            rider_id: RIDER_ID,
            latitude: position.latitude,
            longitude: position.longitude,
            accuracy: position.accuracy,
            speed: position.speed / 3.6, // Convert back to m/s
            heading: position.heading
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Location updated:', data);
            // Refresh deliveries if ETAs changed
            if (data.updated_deliveries > 0) {
                refreshDeliveries();
            }
        }
    })
    .catch(error => console.error('Location update failed:', error));
}

function updateTrackingUI() {
    const button = document.getElementById('trackingToggle');
    const buttonText = document.getElementById('trackingButtonText');
    const indicator = document.getElementById('trackingIndicator');
    const status = document.getElementById('trackingStatus');

    if (trackingActive) {
        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-success');
        buttonText.textContent = 'Stop Tracking';
        indicator.classList.remove('inactive');
        indicator.classList.add('active');
        status.textContent = 'GPS Active';
    } else {
        button.classList.remove('btn-success');
        button.classList.add('btn-outline-primary');
        buttonText.textContent = 'Start Tracking';
        indicator.classList.remove('active');
        indicator.classList.add('inactive');
        status.textContent = 'GPS Inactive';
    }
}

// Delivery Management
function updateDeliveryStatus(deliveryId, newStatus) {
    if (!currentPosition) {
        alert('Please enable GPS tracking first');
        return;
    }

    fetch('/api/update-delivery-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            delivery_id: deliveryId,
            status: newStatus,
            latitude: currentPosition.latitude,
            longitude: currentPosition.longitude
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Status updated successfully', 'success');
            refreshDeliveries();
        } else {
            showToast(data.message || 'Failed to update status', 'danger');
        }
    })
    .catch(error => {
        console.error('Status update failed:', error);
        showToast('Failed to update status', 'danger');
    });
}

function showDeliveryProof(deliveryId) {
    document.getElementById('proofDeliveryId').value = deliveryId;
    document.getElementById('deliveryNotes').value = '';
    document.getElementById('deliveryPhoto').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('deliveryProofModal'));
    modal.show();
}

function confirmDelivery() {
    const deliveryId = document.getElementById('proofDeliveryId').value;
    const notes = document.getElementById('deliveryNotes').value;
    const photoInput = document.getElementById('deliveryPhoto');

    if (!currentPosition) {
        alert('Please enable GPS tracking first');
        return;
    }

    const formData = new FormData();
    formData.append('delivery_id', deliveryId);
    formData.append('status', 'delivered');
    formData.append('notes', notes);
    formData.append('latitude', currentPosition.latitude);
    formData.append('longitude', currentPosition.longitude);

    if (photoInput.files.length > 0) {
        formData.append('photo', photoInput.files[0]);
    }

    fetch('/api/update-delivery-status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('deliveryProofModal')).hide();
            showToast('Delivery confirmed successfully', 'success');
            refreshDeliveries();
            
            // Update completed count
            const completedCount = document.getElementById('completedTodayCount');
            completedCount.textContent = parseInt(completedCount.textContent) + 1;
        } else {
            showToast(data.message || 'Failed to confirm delivery', 'danger');
        }
    })
    .catch(error => {
        console.error('Delivery confirmation failed:', error);
        showToast('Failed to confirm delivery', 'danger');
    });
}

function navigateToDelivery(lat, lng) {
    if (!lat || !lng) {
        alert('Delivery location not available');
        return;
    }

    // Open in Google Maps app or browser
    const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
    window.open(url, '_blank');
}

function refreshDeliveries() {
    location.reload();
}

function showToast(message, type = 'info') {
    // Simple toast notification
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed top-0 start-50 translate-middle-x mt-3`;
    toast.style.zIndex = '9999';
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    
    // Auto-refresh deliveries every 60 seconds
    setInterval(refreshDeliveries, 60000);
});
</script>

<?php include 'includes/footer.php'; ?>
