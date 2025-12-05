<?php
require_once 'includes/bootstrap.php';
$auth->requireRole('admin');

$db = Database::getInstance();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_room' || $action === 'edit_room') {
        $data = [
            'room_number' => sanitizeInput($_POST['room_number']),
            'room_type_id' => $_POST['room_type_id'],
            'floor' => sanitizeInput($_POST['floor']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        if ($action === 'add_room') {
            if ($db->insert('rooms', $data)) {
                $_SESSION['success_message'] = 'Room added successfully';
            }
        } else {
            $id = $_POST['id'];
            if ($db->update('rooms', $data, 'id = :id', ['id' => $id])) {
                $_SESSION['success_message'] = 'Room updated successfully';
            }
        }
        redirect($_SERVER['PHP_SELF']);
    }
    
    if ($action === 'add_type' || $action === 'edit_type') {
        $data = [
            'name' => sanitizeInput($_POST['name']),
            'description' => sanitizeInput($_POST['description']),
            'base_price' => $_POST['base_price'],
            'capacity' => $_POST['capacity'],
            'is_active' => isset($_POST['type_is_active']) ? 1 : 0
        ];
        
        if ($action === 'add_type') {
            if ($db->insert('room_types', $data)) {
                $_SESSION['success_message'] = 'Room type added successfully';
            }
        } else {
            $id = $_POST['type_id'];
            if ($db->update('room_types', $data, 'id = :id', ['id' => $id])) {
                $_SESSION['success_message'] = 'Room type updated successfully';
            }
        }
        redirect($_SERVER['PHP_SELF']);
    }
}

$rooms = $db->fetchAll("
    SELECT r.*, rt.name as type_name, rt.base_price 
    FROM rooms r 
    JOIN room_types rt ON r.room_type_id = rt.id 
    ORDER BY r.room_number
");

$roomTypes = $db->fetchAll("SELECT * FROM room_types ORDER BY name");

$pageTitle = 'Manage Rooms';
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-door-open me-2"></i>Manage Rooms</h1>
            <p class="text-muted mb-0">Add, edit, and configure rooms and room types</p>
        </div>
        <a href="<?= APP_URL ?>/rooms.php" class="btn btn-outline-primary">
            <i class="bi bi-calendar2-week me-1"></i> View Calendar
        </a>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#rooms" type="button">
            <i class="bi bi-door-open me-2"></i>Rooms
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#roomTypes" type="button">
            <i class="bi bi-grid-3x3 me-2"></i>Room Types
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- Rooms Tab -->
    <div class="tab-pane fade show active" id="rooms">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="bi bi-door-open me-2"></i>Manage Rooms</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roomModal" onclick="resetRoomForm()">
                <i class="bi bi-plus-circle me-2"></i>Add Room
            </button>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Room Number</th>
                                <th>Room Type</th>
                                <th>Floor</th>
                                <th>Price/Night</th>
                                <th>Status</th>
                                <th>Active</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $room): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($room['room_number']) ?></strong></td>
                                <td><?= htmlspecialchars($room['type_name']) ?></td>
                                <td><?= htmlspecialchars($room['floor']) ?></td>
                                <td class="fw-bold"><?= formatMoney($room['base_price']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $room['status'] === 'available' ? 'success' : ($room['status'] === 'occupied' ? 'danger' : 'warning') ?>">
                                        <?= ucfirst($room['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $room['is_active'] ? 'success' : 'secondary' ?>">
                                        <?= $room['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick='editRoom(<?= json_encode($room) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Room Types Tab -->
    <div class="tab-pane fade" id="roomTypes">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="bi bi-grid-3x3 me-2"></i>Room Types</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#typeModal" onclick="resetTypeForm()">
                <i class="bi bi-plus-circle me-2"></i>Add Room Type
            </button>
        </div>

        <div class="row g-3">
            <?php foreach ($roomTypes as $type): ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="mb-0"><?= htmlspecialchars($type['name']) ?></h5>
                            <span class="badge bg-<?= $type['is_active'] ? 'success' : 'secondary' ?>">
                                <?= $type['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <p class="text-muted small"><?= htmlspecialchars($type['description']) ?></p>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong class="text-primary"><?= formatMoney($type['base_price']) ?></strong>
                                <small class="text-muted">/night</small>
                            </div>
                            <div>
                                <i class="bi bi-people me-1"></i><?= $type['capacity'] ?> guests
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-primary w-100" onclick='editType(<?= json_encode($type) ?>)'>
                            <i class="bi bi-pencil me-1"></i>Edit
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Room Modal -->
<div class="modal fade" id="roomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="roomForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="roomModalTitle">Add Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="roomAction" value="add_room">
                    <input type="hidden" name="id" id="roomId">
                    
                    <div class="mb-3">
                        <label class="form-label">Room Number *</label>
                        <input type="text" class="form-control" name="room_number" id="room_number" required placeholder="e.g., 101, 102">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Room Type *</label>
                        <select class="form-select" name="room_type_id" id="room_type_id" required>
                            <option value="">Select Type</option>
                            <?php foreach ($roomTypes as $type): ?>
                                <?php if ($type['is_active']): ?>
                                <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?> - <?= formatMoney($type['base_price']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Floor *</label>
                        <input type="text" class="form-control" name="floor" id="room_floor" required placeholder="e.g., Ground Floor, First Floor">
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="room_is_active" checked>
                        <label class="form-check-label" for="room_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Room Type Modal -->
<div class="modal fade" id="typeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="typeForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="typeModalTitle">Add Room Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="typeAction" value="add_type">
                    <input type="hidden" name="type_id" id="typeId">
                    
                    <div class="mb-3">
                        <label class="form-label">Type Name *</label>
                        <input type="text" class="form-control" name="name" id="type_name" required placeholder="e.g., Standard, Deluxe, Suite">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="type_description" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Base Price (per night) *</label>
                        <input type="number" step="0.01" class="form-control" name="base_price" id="type_base_price" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Capacity (guests) *</label>
                        <input type="number" class="form-control" name="capacity" id="type_capacity" required value="2">
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="type_is_active" id="type_is_active" checked>
                        <label class="form-check-label" for="type_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetRoomForm() {
    document.getElementById('roomForm').reset();
    document.getElementById('roomAction').value = 'add_room';
    document.getElementById('roomModalTitle').textContent = 'Add Room';
}

function editRoom(room) {
    document.getElementById('roomAction').value = 'edit_room';
    document.getElementById('roomModalTitle').textContent = 'Edit Room';
    document.getElementById('roomId').value = room.id;
    document.getElementById('room_number').value = room.room_number;
    document.getElementById('room_type_id').value = room.room_type_id;
    document.getElementById('room_floor').value = room.floor || '';
    document.getElementById('room_is_active').checked = room.is_active == 1;
    
    new bootstrap.Modal(document.getElementById('roomModal')).show();
}

function resetTypeForm() {
    document.getElementById('typeForm').reset();
    document.getElementById('typeAction').value = 'add_type';
    document.getElementById('typeModalTitle').textContent = 'Add Room Type';
}

function editType(type) {
    document.getElementById('typeAction').value = 'edit_type';
    document.getElementById('typeModalTitle').textContent = 'Edit Room Type';
    document.getElementById('typeId').value = type.id;
    document.getElementById('type_name').value = type.name;
    document.getElementById('type_description').value = type.description || '';
    document.getElementById('type_base_price').value = type.base_price;
    document.getElementById('type_capacity').value = type.capacity;
    document.getElementById('type_is_active').checked = type.is_active == 1;
    
    new bootstrap.Modal(document.getElementById('typeModal')).show();
}
</script>

</div><!-- End container-fluid -->

<?php include 'includes/footer.php'; ?>
