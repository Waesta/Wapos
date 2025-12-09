<?php
/**
 * Happy Hour Management
 * 
 * Time-based pricing rules:
 * - Schedule happy hours by day/time
 * - Product/category discounts
 * - Buy-one-get-one deals
 * - Automatic price adjustments
 */

require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager']);

$db = Database::getInstance();

// Get existing happy hour rules
$happyHours = $db->fetchAll("
    SELECT hh.*, 
           GROUP_CONCAT(DISTINCT c.name) as category_names,
           GROUP_CONCAT(DISTINCT p.name) as product_names
    FROM happy_hour_rules hh
    LEFT JOIN happy_hour_categories hhc ON hh.id = hhc.happy_hour_id
    LEFT JOIN categories c ON hhc.category_id = c.id
    LEFT JOIN happy_hour_products hhp ON hh.id = hhp.happy_hour_id
    LEFT JOIN products p ON hhp.product_id = p.id
    GROUP BY hh.id
    ORDER BY hh.start_time
");

// Get categories and products for selection
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$products = $db->fetchAll("SELECT id, name, selling_price FROM products WHERE is_active = 1 ORDER BY name");

// Check current happy hour status
$now = date('H:i:s');
$today = strtolower(date('l'));
$activeHappyHour = $db->fetchOne("
    SELECT * FROM happy_hour_rules 
    WHERE is_active = 1 
    AND start_time <= ? 
    AND end_time >= ?
    AND (days_of_week LIKE ? OR days_of_week = 'all')
", [$now, $now, '%' . $today . '%']);

$csrfToken = generateCSRFToken();
$pageTitle = 'Happy Hour Management';
include 'includes/header.php';
?>

<style>
    .happy-hour-card {
        border-radius: 1rem;
        overflow: hidden;
        transition: all 0.3s;
    }
    
    .happy-hour-card.active {
        border: 2px solid #ffc107;
        box-shadow: 0 0 20px rgba(255, 193, 7, 0.3);
    }
    
    .happy-hour-header {
        padding: 1rem;
        background: linear-gradient(135deg, #ff6b6b, #feca57);
        color: white;
    }
    
    .happy-hour-header.active {
        background: linear-gradient(135deg, #ffc107, #ff9800);
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.8; }
    }
    
    .time-badge {
        font-size: 1.5rem;
        font-weight: 700;
        font-family: monospace;
    }
    
    .discount-badge {
        font-size: 2rem;
        font-weight: 700;
    }
    
    .day-pill {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 1rem;
        font-size: 0.7rem;
        font-weight: 600;
        margin: 0.1rem;
        background: var(--bs-secondary-bg);
    }
    
    .day-pill.active {
        background: var(--bs-success);
        color: white;
    }
    
    .current-status {
        padding: 1.5rem;
        border-radius: 1rem;
        text-align: center;
    }
    
    .current-status.active {
        background: linear-gradient(135deg, rgba(255, 193, 7, 0.2), rgba(255, 152, 0, 0.2));
        border: 2px solid #ffc107;
    }
    
    .current-status.inactive {
        background: var(--bs-secondary-bg);
    }
    
    .countdown {
        font-size: 2rem;
        font-weight: 700;
        font-family: monospace;
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h4><i class="bi bi-clock-history me-2"></i>Happy Hour Management</h4>
            <p class="text-muted">Configure time-based pricing and promotions</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHappyHourModal">
                <i class="bi bi-plus-lg me-1"></i>Add Happy Hour
            </button>
        </div>
    </div>
    
    <!-- Current Status -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="current-status <?= $activeHappyHour ? 'active' : 'inactive' ?>">
                <?php if ($activeHappyHour): ?>
                    <div class="d-flex align-items-center justify-content-center gap-4">
                        <div>
                            <i class="bi bi-stars text-warning" style="font-size: 3rem;"></i>
                        </div>
                        <div class="text-start">
                            <h3 class="mb-1 text-warning">🍻 Happy Hour is ACTIVE!</h3>
                            <p class="mb-0 fs-5"><?= htmlspecialchars($activeHappyHour['name']) ?></p>
                            <p class="mb-0">
                                <span class="discount-badge text-success"><?= $activeHappyHour['discount_percent'] ?>% OFF</span>
                                until <span class="time-badge"><?= date('g:i A', strtotime($activeHappyHour['end_time'])) ?></span>
                            </p>
                        </div>
                        <div class="ms-auto">
                            <div class="text-muted">Time Remaining</div>
                            <div class="countdown" id="countdown" data-end="<?= $activeHappyHour['end_time'] ?>">--:--:--</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center gap-3">
                        <i class="bi bi-moon-stars text-muted" style="font-size: 2rem;"></i>
                        <div>
                            <h5 class="mb-0">No Active Happy Hour</h5>
                            <p class="text-muted mb-0">Current time: <?= date('g:i A') ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Happy Hour Rules -->
    <div class="row g-4">
        <?php foreach ($happyHours as $hh): ?>
            <?php 
                $isActive = $activeHappyHour && $activeHappyHour['id'] == $hh['id'];
                $days = explode(',', $hh['days_of_week']);
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card happy-hour-card <?= $isActive ? 'active' : '' ?>">
                    <div class="happy-hour-header <?= $isActive ? 'active' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="mb-1"><?= htmlspecialchars($hh['name']) ?></h5>
                                <div class="time-badge">
                                    <?= date('g:i A', strtotime($hh['start_time'])) ?> - <?= date('g:i A', strtotime($hh['end_time'])) ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="discount-badge"><?= $hh['discount_percent'] ?>%</div>
                                <small>OFF</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Days:</small><br>
                            <?php foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day): ?>
                                <span class="day-pill <?= in_array($day, $days) || $hh['days_of_week'] === 'all' ? 'active' : '' ?>">
                                    <?= ucfirst(substr($day, 0, 3)) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($hh['discount_type'] === 'bogo'): ?>
                            <div class="alert alert-info py-2 mb-2">
                                <i class="bi bi-gift me-1"></i>Buy One Get One Free
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($hh['category_names']): ?>
                            <div class="mb-2">
                                <small class="text-muted">Categories:</small><br>
                                <span class="badge bg-primary"><?= htmlspecialchars($hh['category_names']) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($hh['product_names']): ?>
                            <div class="mb-2">
                                <small class="text-muted">Products:</small><br>
                                <span class="badge bg-secondary"><?= htmlspecialchars(substr($hh['product_names'], 0, 50)) ?>...</span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$hh['category_names'] && !$hh['product_names']): ?>
                            <div class="text-muted small">
                                <i class="bi bi-check-circle me-1"></i>Applies to all bar items
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       <?= $hh['is_active'] ? 'checked' : '' ?>
                                       onchange="toggleHappyHour(<?= $hh['id'] ?>, this.checked)">
                                <label class="form-check-label"><?= $hh['is_active'] ? 'Active' : 'Inactive' ?></label>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-primary" onclick="editHappyHour(<?= $hh['id'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteHappyHour(<?= $hh['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($happyHours)): ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="bi bi-clock-history text-muted" style="font-size: 4rem;"></i>
                    <h5 class="mt-3">No Happy Hours Configured</h5>
                    <p class="text-muted">Create your first happy hour promotion to get started</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHappyHourModal">
                        <i class="bi bi-plus-lg me-1"></i>Add Happy Hour
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Happy Hour Modal -->
<div class="modal fade" id="addHappyHourModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-clock me-2"></i>Add Happy Hour</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="happyHourForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" required placeholder="e.g., Evening Happy Hour">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Discount Type</label>
                            <select class="form-select" name="discount_type" id="discountType">
                                <option value="percent">Percentage Off</option>
                                <option value="fixed">Fixed Amount Off</option>
                                <option value="bogo">Buy One Get One</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-control" name="start_time" required value="16:00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" name="end_time" required value="19:00">
                        </div>
                        <div class="col-md-4" id="discountValueGroup">
                            <label class="form-label">Discount (%)</label>
                            <input type="number" class="form-control" name="discount_percent" value="20" min="1" max="100">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Days of Week</label>
                            <div class="btn-group w-100" role="group">
                                <?php foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day): ?>
                                    <input type="checkbox" class="btn-check" name="days[]" value="<?= $day ?>" id="day_<?= $day ?>" checked>
                                    <label class="btn btn-outline-primary" for="day_<?= $day ?>"><?= ucfirst(substr($day, 0, 3)) ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apply to Categories (Optional)</label>
                            <select class="form-select" name="categories[]" multiple size="5">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Leave empty for all categories</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apply to Products (Optional)</label>
                            <select class="form-select" name="products[]" multiple size="5">
                                <?php foreach ($products as $prod): ?>
                                    <option value="<?= $prod['id'] ?>"><?= htmlspecialchars($prod['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Leave empty for all products</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description (Optional)</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="Promotional message to display"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-plus-lg me-1"></i>Create Happy Hour
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= $csrfToken ?>';

// Countdown timer
function updateCountdown() {
    const el = document.getElementById('countdown');
    if (!el) return;
    
    const endTime = el.dataset.end;
    const now = new Date();
    const end = new Date(now.toDateString() + ' ' + endTime);
    
    if (end < now) {
        el.textContent = '00:00:00';
        return;
    }
    
    const diff = Math.floor((end - now) / 1000);
    const hours = Math.floor(diff / 3600);
    const minutes = Math.floor((diff % 3600) / 60);
    const seconds = diff % 60;
    
    el.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

setInterval(updateCountdown, 1000);
updateCountdown();

// Discount type toggle
document.getElementById('discountType')?.addEventListener('change', function() {
    const group = document.getElementById('discountValueGroup');
    if (this.value === 'bogo') {
        group.style.display = 'none';
    } else {
        group.style.display = 'block';
        group.querySelector('label').textContent = this.value === 'percent' ? 'Discount (%)' : 'Discount Amount';
    }
});

// Form submission
document.getElementById('happyHourForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {
        action: 'create_happy_hour',
        name: formData.get('name'),
        discount_type: formData.get('discount_type'),
        discount_percent: formData.get('discount_percent'),
        start_time: formData.get('start_time'),
        end_time: formData.get('end_time'),
        days: formData.getAll('days[]'),
        categories: formData.getAll('categories[]'),
        products: formData.getAll('products[]'),
        description: formData.get('description'),
        csrf_token: CSRF_TOKEN
    };
    
    try {
        const response = await fetch('api/happy-hour.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert(result.message || 'Failed to create happy hour');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to create happy hour');
    }
});

async function toggleHappyHour(id, active) {
    try {
        const response = await fetch('api/happy-hour.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'toggle',
                id: id,
                active: active,
                csrf_token: CSRF_TOKEN
            })
        });
        const result = await response.json();
        
        if (!result.success) {
            alert(result.message || 'Failed to update');
            location.reload();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

async function deleteHappyHour(id) {
    if (!confirm('Delete this happy hour rule?')) return;
    
    try {
        const response = await fetch('api/happy-hour.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'delete',
                id: id,
                csrf_token: CSRF_TOKEN
            })
        });
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert(result.message || 'Failed to delete');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
