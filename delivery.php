<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$db = Database::getInstance();

// Handle rider actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_rider') {
        $db->insert('riders', [
            'name' => sanitizeInput($_POST['name']),
            'phone' => sanitizeInput($_POST['phone']),
            'vehicle_type' => sanitizeInput($_POST['vehicle_type']),
            'vehicle_number' => sanitizeInput($_POST['vehicle_number']),
            'status' => 'available'
        ]);
        $_SESSION['success_message'] = 'Rider added successfully';
        redirect($_SERVER['PHP_SELF']);
    }
}

// Get riders
$riders = $db->fetchAll("SELECT * FROM riders WHERE is_active = 1 ORDER BY name");

// Get delivery orders
$deliveryOrders = $db->fetchAll("
    SELECT 
        o.*,
        d.status as delivery_status,
        d.rider_id,
        r.name as rider_name,
        r.phone as rider_phone
    FROM orders o
    LEFT JOIN deliveries d ON o.id = d.order_id
    LEFT JOIN riders r ON d.rider_id = r.id
    WHERE o.order_type = 'delivery'
    AND o.status NOT IN ('completed', 'cancelled')
    ORDER BY o.created_at DESC
");

// Get pending deliveries
$pendingDeliveries = $db->fetchAll("
    SELECT 
        d.*,
        o.order_number,
        o.total_amount,
        o.customer_name,
        o.customer_phone,
        o.created_at as order_time,
        r.name as rider_name
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    LEFT JOIN riders r ON d.rider_id = r.id
    WHERE d.status NOT IN ('delivered', 'failed')
    ORDER BY d.created_at DESC
");

$pageTitle = 'Delivery Management';
include 'includes/header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-truck me-2"></i>Delivery Management</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#riderModal">
        <i class="bi bi-plus-circle me-2"></i>Add Rider
    </button>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Active Riders</p>
                        <h3 class="mb-0 fw-bold">
                            <?= count(array_filter($riders, fn($r) => $r['status'] === 'available')) ?>
                        </h3>
                    </div>
                    <i class="bi bi-person-check text-success fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Pending Orders</p>
                        <h3 class="mb-0 fw-bold text-warning"><?= count($deliveryOrders) ?></h3>
                    </div>
                    <i class="bi bi-clock-history text-warning fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">In Transit</p>
                        <h3 class="mb-0 fw-bold text-info">
                            <?= count(array_filter($pendingDeliveries, fn($d) => $d['status'] === 'in-transit')) ?>
                        </h3>
                    </div>
                    <i class="bi bi-truck text-info fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Today Delivered</p>
                        <h3 class="mb-0 fw-bold text-success">
                            <?php 
                            $todayDelivered = $db->fetchOne("
                                SELECT COUNT(*) as count FROM deliveries 
                                WHERE status = 'delivered' 
                                AND DATE(actual_delivery_time) = CURDATE()
                            ");
                            echo $todayDelivered['count'] ?? 0;
                            ?>
                        </h3>
                    </div>
                    <i class="bi bi-check-circle text-success fs-1"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Riders List -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-people me-2"></i>Available Riders</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($riders as $rider): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?= htmlspecialchars($rider['name']) ?></h6>
                                <p class="mb-1 small text-muted">
                                    <i class="bi bi-phone me-1"></i><?= htmlspecialchars($rider['phone']) ?>
                                </p>
                                <p class="mb-0 small text-muted">
                                    <i class="bi bi-scooter me-1"></i><?= htmlspecialchars($rider['vehicle_type']) ?> 
                                    <?= htmlspecialchars($rider['vehicle_number']) ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-<?= $rider['status'] === 'available' ? 'success' : ($rider['status'] === 'busy' ? 'warning' : 'secondary') ?>">
                                    <?= ucfirst($rider['status']) ?>
                                </span>
                                <p class="mb-0 small text-muted mt-1">
                                    <?= $rider['total_deliveries'] ?> deliveries
                                </p>
                                <p class="mb-0 small">
                                    <i class="bi bi-star-fill text-warning"></i> <?= number_format($rider['rating'], 1) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Delivery Orders -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>Delivery Orders</h6>
            </div>
            <div class="card-body">
                <?php if (empty($deliveryOrders)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2">No delivery orders</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Rider</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deliveryOrders as $order): ?>
                                <tr>
                                    <td><?= htmlspecialchars($order['order_number']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($order['customer_phone'] ?? '') ?></small>
                                    </td>
                                    <td class="fw-bold">KES <?= formatMoney($order['total_amount']) ?></td>
                                    <td>
                                        <?php if ($order['rider_name']): ?>
                                            <?= htmlspecialchars($order['rider_name']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $order['delivery_status'] === 'delivered' ? 'success' : 
                                            ($order['delivery_status'] === 'in-transit' ? 'info' : 'warning') 
                                        ?>">
                                            <?= ucfirst(str_replace('-', ' ', $order['delivery_status'] ?? 'pending')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!$order['rider_id']): ?>
                                            <button class="btn btn-sm btn-primary" onclick="assignRider(<?= $order['id'] ?>)">
                                                <i class="bi bi-person-plus"></i> Assign
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-info" onclick="trackDelivery(<?= $order['id'] ?>)">
                                                <i class="bi bi-geo-alt"></i> Track
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Rider Modal -->
<div class="modal fade" id="riderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_rider">
                <div class="modal-header">
                    <h5 class="modal-title">Add Delivery Rider</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Rider Name *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number *</label>
                        <input type="tel" class="form-control" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vehicle Type *</label>
                        <select class="form-select" name="vehicle_type" required>
                            <option value="">Select...</option>
                            <option value="Motorcycle">Motorcycle</option>
                            <option value="Bicycle">Bicycle</option>
                            <option value="Car">Car</option>
                            <option value="Van">Van</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vehicle Number</label>
                        <input type="text" class="form-control" name="vehicle_number">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Rider</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Rider Modal -->
<div class="modal fade" id="assignRiderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Rider to Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Select Available Rider</label>
                    <select class="form-select" id="selectedRider">
                        <option value="">Choose rider...</option>
                        <?php foreach (array_filter($riders, fn($r) => $r['status'] === 'available') as $rider): ?>
                            <option value="<?= $rider['id'] ?>"><?= htmlspecialchars($rider['name']) ?> - <?= htmlspecialchars($rider['vehicle_type']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Estimated Delivery Time</label>
                    <input type="datetime-local" class="form-control" id="estimatedTime" 
                           value="<?= date('Y-m-d\TH:i', strtotime('+30 minutes')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Delivery Notes</label>
                    <textarea class="form-control" id="deliveryNotes" rows="2" placeholder="Special delivery instructions..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmAssignment()">Assign Rider</button>
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
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading tracking information...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentOrderId = null;

function assignRider(orderId) {
    currentOrderId = orderId;
    new bootstrap.Modal(document.getElementById('assignRiderModal')).show();
}

async function confirmAssignment() {
    const riderId = document.getElementById('selectedRider').value;
    const estimatedTime = document.getElementById('estimatedTime').value;
    const notes = document.getElementById('deliveryNotes').value;
    
    if (!riderId) {
        alert('Please select a rider');
        return;
    }
    
    try {
        const response = await fetch('api/assign-delivery-rider.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: currentOrderId,
                rider_id: riderId,
                estimated_time: estimatedTime,
                notes: notes
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('assignRiderModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function trackDelivery(orderId) {
    const modal = new bootstrap.Modal(document.getElementById('trackingModal'));
    modal.show();
    
    try {
        const response = await fetch(`api/get-delivery-tracking.php?order_id=${orderId}`);
        const result = await response.json();
        
        if (result.success) {
            const tracking = result.data;
            document.getElementById('trackingContent').innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Order Information</h6>
                        <p><strong>Order #:</strong> ${tracking.order_number}</p>
                        <p><strong>Customer:</strong> ${tracking.customer_name || 'N/A'}</p>
                        <p><strong>Phone:</strong> ${tracking.customer_phone || 'N/A'}</p>
                        <p><strong>Address:</strong> ${tracking.delivery_address || 'N/A'}</p>
                        <p><strong>Amount:</strong> KES ${parseFloat(tracking.total_amount).toFixed(2)}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Delivery Status</h6>
                        <p><strong>Rider:</strong> ${tracking.rider_name || 'Not assigned'}</p>
                        <p><strong>Status:</strong> <span class="badge bg-info">${tracking.status}</span></p>
                        <p><strong>Estimated Time:</strong> ${tracking.estimated_time || 'N/A'}</p>
                        <p><strong>Order Time:</strong> ${new Date(tracking.created_at).toLocaleString()}</p>
                    </div>
                </div>
                <div class="mt-3">
                    <h6>Delivery Timeline</h6>
                    <div class="timeline">
                        <div class="timeline-item ${tracking.status === 'pending' ? 'active' : 'completed'}">
                            <i class="bi bi-clock"></i> Order Placed
                        </div>
                        <div class="timeline-item ${tracking.status === 'assigned' ? 'active' : (tracking.status === 'picked-up' || tracking.status === 'in-transit' || tracking.status === 'delivered' ? 'completed' : '')}">
                            <i class="bi bi-person-check"></i> Rider Assigned
                        </div>
                        <div class="timeline-item ${tracking.status === 'picked-up' ? 'active' : (tracking.status === 'in-transit' || tracking.status === 'delivered' ? 'completed' : '')}">
                            <i class="bi bi-box"></i> Order Picked Up
                        </div>
                        <div class="timeline-item ${tracking.status === 'in-transit' ? 'active' : (tracking.status === 'delivered' ? 'completed' : '')}">
                            <i class="bi bi-truck"></i> In Transit
                        </div>
                        <div class="timeline-item ${tracking.status === 'delivered' ? 'completed' : ''}">
                            <i class="bi bi-check-circle"></i> Delivered
                        </div>
                    </div>
                </div>
                ${tracking.status !== 'delivered' && tracking.rider_name ? `
                <div class="mt-3 text-center">
                    <button class="btn btn-success me-2" onclick="updateDeliveryStatus(${orderId}, 'picked-up')">Mark as Picked Up</button>
                    <button class="btn btn-info me-2" onclick="updateDeliveryStatus(${orderId}, 'in-transit')">Mark as In Transit</button>
                    <button class="btn btn-primary" onclick="updateDeliveryStatus(${orderId}, 'delivered')">Mark as Delivered</button>
                </div>
                ` : ''}
            `;
        } else {
            document.getElementById('trackingContent').innerHTML = `
                <div class="alert alert-danger">
                    Error loading tracking information: ${result.message}
                </div>
            `;
        }
    } catch (error) {
        document.getElementById('trackingContent').innerHTML = `
            <div class="alert alert-danger">
                Error: ${error.message}
            </div>
        `;
    }
}

async function updateDeliveryStatus(orderId, status) {
    try {
        const response = await fetch('api/update-delivery-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: orderId,
                status: status
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('trackingModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

// Auto-refresh delivery status every 30 seconds
setInterval(() => {
    location.reload();
}, 30000);
</script>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    padding: 10px 0;
    border-left: 2px solid #dee2e6;
    padding-left: 20px;
    margin-bottom: 10px;
}

.timeline-item.completed {
    border-left-color: #28a745;
    color: #28a745;
}

.timeline-item.active {
    border-left-color: #007bff;
    color: #007bff;
    font-weight: bold;
}

.timeline-item i {
    position: absolute;
    left: -8px;
    background: white;
    border: 2px solid #dee2e6;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
}

.timeline-item.completed i {
    border-color: #28a745;
    color: #28a745;
}

.timeline-item.active i {
    border-color: #007bff;
    color: #007bff;
}
</style>

<?php include 'includes/footer.php'; ?>
