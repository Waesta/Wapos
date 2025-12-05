<?php
/**
 * Notification Center
 * Manage email, SMS, and WhatsApp communications
 */

require_once 'includes/bootstrap.php';
$auth->requireLogin();

use App\Services\NotificationService;

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'manager', 'super_admin', 'developer'])) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$notificationService = new NotificationService($pdo);
$currencySymbol = CurrencyManager::getInstance()->getCurrencySymbol();

// Get statistics
$todayStats = $notificationService->getStats('today');
$weekStats = $notificationService->getStats('week');

// Get recent notifications
$recentNotifications = $db->fetchAll("
    SELECT nl.*, c.name as customer_name
    FROM notification_logs nl
    LEFT JOIN customers c ON nl.customer_id = c.id
    ORDER BY nl.created_at DESC
    LIMIT 20
");

// Get campaigns
$campaigns = $db->fetchAll("
    SELECT mc.*, u.full_name as creator_name
    FROM marketing_campaigns mc
    LEFT JOIN users u ON mc.created_by = u.id
    ORDER BY mc.created_at DESC
    LIMIT 10
");

// Get email templates
$emailTemplates = $db->fetchAll("SELECT * FROM email_templates WHERE is_active = 1 ORDER BY name");

// Get SMS templates
$smsTemplates = $db->fetchAll("SELECT * FROM sms_templates WHERE is_active = 1 ORDER BY name");

// Process stats into usable format
$statsMap = ['email' => ['sent' => 0, 'failed' => 0], 'sms' => ['sent' => 0, 'failed' => 0], 'whatsapp' => ['sent' => 0, 'failed' => 0]];
foreach ($todayStats as $stat) {
    if (isset($statsMap[$stat['channel']])) {
        $statsMap[$stat['channel']][$stat['status']] = $stat['count'];
    }
}

$pageTitle = 'Notification Center';
require_once 'includes/header.php';
?>

<style>
    .notification-grid {
        display: grid;
        gap: var(--spacing-lg);
    }
    @media (min-width: 1200px) {
        .notification-grid {
            grid-template-columns: 2fr 1fr;
        }
    }
    .channel-card {
        border-left: 4px solid;
        transition: transform 0.2s;
    }
    .channel-card:hover {
        transform: translateY(-2px);
    }
    .channel-email { border-left-color: #3b82f6; }
    .channel-sms { border-left-color: #10b981; }
    .channel-whatsapp { border-left-color: #25d366; }
    .status-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    .quick-action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface);
        transition: all 0.2s;
        text-decoration: none;
        color: inherit;
    }
    .quick-action-btn:hover {
        border-color: var(--bs-primary);
        background: var(--color-surface-subtle);
    }
    .quick-action-btn i {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1">
                <i class="bi bi-bell text-primary me-2"></i>Notification Center
            </h1>
            <p class="text-muted mb-0">Manage email, SMS, and WhatsApp communications</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendNotificationModal">
                <i class="bi bi-send me-1"></i>Send Notification
            </button>
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newCampaignModal">
                <i class="bi bi-megaphone me-1"></i>New Campaign
            </button>
            <a href="notification-settings.php" class="btn btn-outline-secondary">
                <i class="bi bi-gear me-1"></i>Settings
            </a>
        </div>
    </div>

    <!-- Channel Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card channel-card channel-email">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">Email</h6>
                            <h3 class="mb-0"><?= ($statsMap['email']['sent'] ?? 0) ?></h3>
                            <small class="text-muted">sent today</small>
                        </div>
                        <i class="bi bi-envelope-fill text-primary fs-2"></i>
                    </div>
                    <?php if (($statsMap['email']['failed'] ?? 0) > 0): ?>
                        <div class="mt-2">
                            <span class="badge bg-danger"><?= $statsMap['email']['failed'] ?> failed</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card channel-card channel-sms">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">SMS</h6>
                            <h3 class="mb-0"><?= ($statsMap['sms']['sent'] ?? 0) ?></h3>
                            <small class="text-muted">sent today</small>
                        </div>
                        <i class="bi bi-chat-dots-fill text-success fs-2"></i>
                    </div>
                    <?php if (($statsMap['sms']['failed'] ?? 0) > 0): ?>
                        <div class="mt-2">
                            <span class="badge bg-danger"><?= $statsMap['sms']['failed'] ?> failed</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card channel-card channel-whatsapp">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted mb-1">WhatsApp</h6>
                            <h3 class="mb-0"><?= ($statsMap['whatsapp']['sent'] ?? 0) ?></h3>
                            <small class="text-muted">sent today</small>
                        </div>
                        <i class="bi bi-whatsapp fs-2" style="color: #25d366;"></i>
                    </div>
                    <?php if (($statsMap['whatsapp']['failed'] ?? 0) > 0): ?>
                        <div class="mt-2">
                            <span class="badge bg-danger"><?= $statsMap['whatsapp']['failed'] ?> failed</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-md-2">
                    <a href="#" class="quick-action-btn" onclick="sendBirthdayWishes()">
                        <i class="bi bi-cake2 text-danger"></i>
                        <span class="small">Birthday Wishes</span>
                    </a>
                </div>
                <div class="col-6 col-md-2">
                    <a href="#" class="quick-action-btn" onclick="sendThankYou()">
                        <i class="bi bi-heart text-primary"></i>
                        <span class="small">Thank You</span>
                    </a>
                </div>
                <div class="col-6 col-md-2">
                    <a href="#" class="quick-action-btn" onclick="sendPromotion()">
                        <i class="bi bi-percent text-success"></i>
                        <span class="small">Promotion</span>
                    </a>
                </div>
                <div class="col-6 col-md-2">
                    <a href="#" class="quick-action-btn" onclick="sendDailySummary()">
                        <i class="bi bi-graph-up text-info"></i>
                        <span class="small">Daily Summary</span>
                    </a>
                </div>
                <div class="col-6 col-md-2">
                    <a href="#" class="quick-action-btn" onclick="checkLowStock()">
                        <i class="bi bi-exclamation-triangle text-warning"></i>
                        <span class="small">Low Stock Alert</span>
                    </a>
                </div>
                <div class="col-6 col-md-2">
                    <a href="marketing-campaigns.php" class="quick-action-btn">
                        <i class="bi bi-megaphone text-purple"></i>
                        <span class="small">Campaigns</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="notification-grid">
        <!-- Recent Notifications -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Notifications</h6>
                <a href="notification-logs.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentNotifications)): ?>
                    <p class="text-muted p-4 mb-0">No notifications sent yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Channel</th>
                                    <th>Recipient</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Sent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentNotifications as $notif): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $channelIcon = match($notif['channel']) {
                                            'email' => '<i class="bi bi-envelope text-primary"></i>',
                                            'sms' => '<i class="bi bi-chat-dots text-success"></i>',
                                            'whatsapp' => '<i class="bi bi-whatsapp" style="color:#25d366"></i>',
                                            default => '<i class="bi bi-bell"></i>'
                                        };
                                        echo $channelIcon;
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($notif['customer_name']): ?>
                                            <strong><?= htmlspecialchars($notif['customer_name']) ?></strong><br>
                                        <?php endif; ?>
                                        <small class="text-muted"><?= htmlspecialchars($notif['recipient']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= ucfirst($notif['notification_type']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = match($notif['status']) {
                                            'sent' => 'success',
                                            'failed' => 'danger',
                                            'pending' => 'warning',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>"><?= ucfirst($notif['status']) ?></span>
                                    </td>
                                    <td>
                                        <small><?= $notif['sent_at'] ? date('M j, g:i A', strtotime($notif['sent_at'])) : '—' ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Campaigns & Templates -->
        <div>
            <!-- Active Campaigns -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-megaphone me-2"></i>Recent Campaigns</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($campaigns)): ?>
                        <p class="text-muted mb-0">No campaigns yet.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($campaigns, 0, 5) as $campaign): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <div>
                                <strong><?= htmlspecialchars($campaign['name']) ?></strong>
                                <small class="text-muted d-block"><?= ucfirst($campaign['channel']) ?></small>
                            </div>
                            <span class="badge bg-<?= $campaign['status'] === 'completed' ? 'success' : ($campaign['status'] === 'running' ? 'primary' : 'secondary') ?>">
                                <?= ucfirst($campaign['status']) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Templates -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-file-text me-2"></i>Templates</h6>
                </div>
                <div class="card-body">
                    <h6 class="small text-muted mb-2">Email Templates</h6>
                    <?php foreach (array_slice($emailTemplates ?: [], 0, 4) as $template): ?>
                        <span class="badge bg-primary me-1 mb-1"><?= htmlspecialchars($template['name']) ?></span>
                    <?php endforeach; ?>
                    
                    <h6 class="small text-muted mb-2 mt-3">SMS Templates</h6>
                    <?php foreach (array_slice($smsTemplates ?: [], 0, 4) as $template): ?>
                        <span class="badge bg-success me-1 mb-1"><?= htmlspecialchars($template['name']) ?></span>
                    <?php endforeach; ?>
                    
                    <div class="mt-3">
                        <a href="notification-templates.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil me-1"></i>Manage Templates
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Send Notification Modal -->
<div class="modal fade" id="sendNotificationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-send me-2"></i>Send Notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="sendNotificationForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Channel</label>
                            <select class="form-select" name="channel" id="notifChannel" required>
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                                <option value="whatsapp">WhatsApp</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Recipient Type</label>
                            <select class="form-select" name="recipient_type" id="recipientType">
                                <option value="single">Single Recipient</option>
                                <option value="customer">Select Customer</option>
                                <option value="segment">Customer Segment</option>
                            </select>
                        </div>
                        <div class="col-12" id="singleRecipientDiv">
                            <label class="form-label">Recipient</label>
                            <input type="text" class="form-control" name="recipient" placeholder="Email or phone number">
                        </div>
                        <div class="col-12 d-none" id="customerSelectDiv">
                            <label class="form-label">Select Customer</label>
                            <select class="form-select" name="customer_id">
                                <option value="">Choose customer...</option>
                            </select>
                        </div>
                        <div class="col-12 d-none" id="segmentSelectDiv">
                            <label class="form-label">Customer Segment</label>
                            <select class="form-select" name="segment">
                                <option value="all">All Customers</option>
                                <option value="active">Active (purchased in 30 days)</option>
                                <option value="inactive">Inactive (no purchase in 60 days)</option>
                                <option value="high_value">High Value (top 20%)</option>
                                <option value="new">New (joined this month)</option>
                            </select>
                        </div>
                        <div class="col-12" id="subjectDiv">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="subject" placeholder="Email subject">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" rows="5" placeholder="Your message..." required></textarea>
                            <small class="text-muted">Use {{name}}, {{first_name}}, {{loyalty_points}} for personalization</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Template (Optional)</label>
                            <select class="form-select" name="template" id="templateSelect">
                                <option value="">No template - custom message</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="sendNotification()">
                    <i class="bi bi-send me-1"></i>Send
                </button>
            </div>
        </div>
    </div>
</div>

<!-- New Campaign Modal -->
<div class="modal fade" id="newCampaignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-megaphone me-2"></i>Create Campaign</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newCampaignForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Campaign Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Channel</label>
                            <select class="form-select" name="channel" required>
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                                <option value="whatsapp">WhatsApp</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Campaign Type</label>
                            <select class="form-select" name="campaign_type">
                                <option value="promotional">Promotional</option>
                                <option value="newsletter">Newsletter</option>
                                <option value="announcement">Announcement</option>
                                <option value="seasonal">Seasonal</option>
                                <option value="loyalty">Loyalty Program</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Target Segment</label>
                            <select class="form-select" name="target_segment">
                                <option value="all">All Customers</option>
                                <option value="active">Active Customers</option>
                                <option value="inactive">Inactive Customers</option>
                                <option value="high_value">High Value Customers</option>
                                <option value="new">New Customers</option>
                                <option value="birthday">Birthday This Month</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subject (for email)</label>
                            <input type="text" class="form-control" name="subject">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Content</label>
                            <textarea class="form-control" name="content" rows="5" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Schedule (Optional)</label>
                            <input type="datetime-local" class="form-control" name="scheduled_at">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary" onclick="saveCampaignDraft()">
                    <i class="bi bi-save me-1"></i>Save Draft
                </button>
                <button type="button" class="btn btn-primary" onclick="launchCampaign()">
                    <i class="bi bi-rocket me-1"></i>Launch
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= generateCSRFToken() ?>';

// Toggle recipient input based on type
document.getElementById('recipientType').addEventListener('change', function() {
    document.getElementById('singleRecipientDiv').classList.toggle('d-none', this.value !== 'single');
    document.getElementById('customerSelectDiv').classList.toggle('d-none', this.value !== 'customer');
    document.getElementById('segmentSelectDiv').classList.toggle('d-none', this.value !== 'segment');
    
    if (this.value === 'customer') {
        loadCustomers();
    }
});

// Toggle subject for SMS
document.getElementById('notifChannel').addEventListener('change', function() {
    document.getElementById('subjectDiv').classList.toggle('d-none', this.value !== 'email');
    loadTemplates(this.value);
});

function loadCustomers() {
    fetch('api/customers.php?action=list&limit=100')
        .then(r => r.json())
        .then(data => {
            const select = document.querySelector('[name="customer_id"]');
            select.innerHTML = '<option value="">Choose customer...</option>';
            (data.customers || []).forEach(c => {
                select.innerHTML += `<option value="${c.id}">${c.name} (${c.email || c.phone || 'No contact'})</option>`;
            });
        });
}

function loadTemplates(channel) {
    const select = document.getElementById('templateSelect');
    select.innerHTML = '<option value="">No template - custom message</option>';
    
    const templates = channel === 'email' 
        ? <?= json_encode($emailTemplates ?: []) ?>
        : <?= json_encode($smsTemplates ?: []) ?>;
    
    templates.forEach(t => {
        select.innerHTML += `<option value="${t.id}">${t.name}</option>`;
    });
}

function sendNotification() {
    const form = document.getElementById('sendNotificationForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    data.csrf_token = csrfToken;
    
    fetch('api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'send', ...data })
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert('Notification sent successfully!');
            bootstrap.Modal.getInstance(document.getElementById('sendNotificationModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(err => alert('Error: ' + err.message));
}

function sendBirthdayWishes() {
    if (!confirm('Send birthday wishes to all customers with birthdays today?')) return;
    
    fetch('api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'send_birthday_wishes', csrf_token: csrfToken })
    })
    .then(r => r.json())
    .then(result => {
        alert(result.message);
        if (result.success) location.reload();
    });
}

function sendThankYou() {
    const customerId = prompt('Enter customer ID to send thank you message:');
    if (!customerId) return;
    
    fetch('api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'send_thank_you', customer_id: customerId, csrf_token: csrfToken })
    })
    .then(r => r.json())
    .then(result => {
        alert(result.message);
        if (result.success) location.reload();
    });
}

function sendPromotion() {
    document.getElementById('newCampaignModal').querySelector('[name="campaign_type"]').value = 'promotional';
    new bootstrap.Modal(document.getElementById('newCampaignModal')).show();
}

function sendDailySummary() {
    if (!confirm('Send daily sales summary to admin email?')) return;
    
    fetch('api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'send_daily_summary', csrf_token: csrfToken })
    })
    .then(r => r.json())
    .then(result => alert(result.message));
}

function checkLowStock() {
    if (!confirm('Send low stock alert to admin?')) return;
    
    fetch('api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'send_low_stock_alert', csrf_token: csrfToken })
    })
    .then(r => r.json())
    .then(result => alert(result.message));
}

function saveCampaignDraft() {
    submitCampaign('draft');
}

function launchCampaign() {
    submitCampaign('launch');
}

function submitCampaign(action) {
    const form = document.getElementById('newCampaignForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    data.csrf_token = csrfToken;
    data.action = action === 'launch' ? 'launch_campaign' : 'save_campaign_draft';
    
    fetch('api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert(action === 'launch' ? 'Campaign launched!' : 'Campaign saved as draft');
            bootstrap.Modal.getInstance(document.getElementById('newCampaignModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    });
}

// Initialize
loadTemplates('email');
</script>

<?php require_once 'includes/footer.php'; ?>
