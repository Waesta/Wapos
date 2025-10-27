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

<script>
function assignRider(orderId) {
    // In a real implementation, this would show a modal to select a rider
    alert('Rider assignment feature - Select available rider for order #' + orderId);
}

function trackDelivery(orderId) {
    // In a real implementation, this would show a map with delivery tracking
    alert('Delivery tracking feature - Track order #' + orderId);
}
</script>

<?php include 'includes/footer.php'; ?>
