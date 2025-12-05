<?php
/**
 * WAPOS - Guest Portal Settings
 * Manage guest access credentials and portal settings
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

require_once 'includes/bootstrap.php';
require_once 'app/Services/GuestAuthService.php';

use App\Services\GuestAuthService;

// Require admin access
$auth->requireLogin();
$userRole = $auth->getRole();
if (!in_array($userRole, ['super_admin', 'developer', 'admin'], true)) {
    header('Location: ' . APP_URL . '/access-denied.php');
    exit;
}

$settings = new SettingsStore($db);
$guestAuth = new GuestAuthService($db->getConnection());
$message = '';
$messageType = '';
$newCredentials = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request. Please try again.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? 'save_settings';
        
        if ($action === 'save_settings') {
            $guestPortalEnabled = isset($_POST['guest_portal_enabled']) ? '1' : '0';
            $settings->set('guest_portal_enabled', $guestPortalEnabled);
            $message = 'Settings saved successfully!';
            $messageType = 'success';
        }
        
        if ($action === 'create_guest_access') {
            try {
                $newCredentials = $guestAuth->createGuestAccess([
                    'guest_name' => $_POST['guest_name'] ?? '',
                    'room_number' => $_POST['room_number'] ?? '',
                    'check_in_date' => $_POST['check_in_date'] ?? date('Y-m-d'),
                    'check_out_date' => $_POST['check_out_date'] ?? date('Y-m-d', strtotime('+1 day')),
                    'email' => $_POST['guest_email'] ?? '',
                    'phone' => $_POST['guest_phone'] ?? '',
                    'booking_id' => $_POST['booking_id'] ?? null
                ]);
                $message = 'Guest access created successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
        
        if ($action === 'regenerate_credentials') {
            try {
                $guestAccessId = (int)($_POST['guest_access_id'] ?? 0);
                $newCredentials = $guestAuth->regenerateCredentials($guestAccessId);
                $message = 'Credentials regenerated successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
        
        if ($action === 'revoke_access') {
            try {
                $guestAccessId = (int)($_POST['guest_access_id'] ?? 0);
                $guestAuth->revokeAccess($guestAccessId, $auth->getUserId());
                $message = 'Guest access revoked.';
                $messageType = 'warning';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Get active guest accesses
$activeAccesses = $guestAuth->getActiveAccesses();

$guestPortalEnabled = $settings->get('guest_portal_enabled', '1') === '1';
$securePortalUrl = rtrim(APP_URL, '/') . '/guest-portal.php';
$trackingUrl = rtrim(APP_URL, '/') . '/guest-track.php';

$pageTitle = 'Guest Portal Management';
include 'includes/header.php';
?>

<style>
    .portal-card {
        background: var(--color-surface);
        border-radius: 12px;
        border: 1px solid var(--color-border);
        padding: 24px;
        margin-bottom: 20px;
    }
    
    .credentials-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 20px;
    }
    
    .credentials-box h5 {
        color: white;
        margin-bottom: 16px;
    }
    
    .credential-item {
        background: rgba(255,255,255,0.15);
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 12px;
    }
    
    .credential-item label {
        font-size: 0.75rem;
        text-transform: uppercase;
        opacity: 0.8;
        display: block;
        margin-bottom: 4px;
    }
    
    .credential-item .value {
        font-family: monospace;
        font-size: 1.1rem;
        font-weight: 600;
        word-break: break-all;
    }
    
    .url-display {
        background: var(--color-surface-alt);
        padding: 12px 16px;
        border-radius: 8px;
        font-family: monospace;
        font-size: 0.8rem;
        word-break: break-all;
        margin: 8px 0;
    }
    
    .copy-btn {
        cursor: pointer;
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid rgba(255,255,255,0.3);
        background: rgba(255,255,255,0.1);
        color: white;
        font-size: 0.85rem;
        transition: all 0.2s;
    }
    
    .copy-btn:hover {
        background: rgba(255,255,255,0.2);
    }
    
    .guest-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .guest-table th, .guest-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid var(--color-border);
    }
    
    .guest-table th {
        background: var(--color-surface-alt);
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
    }
    
    .guest-table tr:hover {
        background: var(--color-surface-alt);
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .status-badge.active {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-toggle {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px;
        background: var(--color-surface-alt);
        border-radius: 8px;
    }
    
    .toggle-switch {
        position: relative;
        width: 50px;
        height: 26px;
    }
    
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background-color: #ccc;
        transition: 0.3s;
        border-radius: 26px;
    }
    
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
    }
    
    input:checked + .toggle-slider { background-color: var(--color-success); }
    input:checked + .toggle-slider:before { transform: translateX(24px); }
    
    .security-info {
        background: #fef3c7;
        border: 1px solid #f59e0b;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 20px;
    }
    
    .security-info.success {
        background: #d1fae5;
        border-color: #10b981;
    }
    
    @media print {
        .no-print { display: none !important; }
        .credentials-box { background: #333 !important; -webkit-print-color-adjust: exact; }
    }
</style>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1"><i class="bi bi-shield-lock me-2"></i>Guest Portal Management</h1>
                    <p class="text-muted mb-0">Secure guest access with encrypted credentials</p>
                </div>
                <a href="<?= APP_URL ?>/guest-portal.php" target="_blank" class="btn btn-outline-primary no-print">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Preview Portal
                </a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show no-print">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- New Credentials Display -->
            <?php if ($newCredentials): ?>
                <div class="credentials-box">
                    <h5><i class="bi bi-key me-2"></i>Guest Credentials Generated</h5>
                    <div class="security-info success" style="background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.3);">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Save these credentials now. The password cannot be retrieved later.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="credential-item">
                                <label>Guest ID (Username)</label>
                                <div class="value"><?= htmlspecialchars($newCredentials['username']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="credential-item">
                                <label>Password</label>
                                <div class="value"><?= htmlspecialchars($newCredentials['password']) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="credential-item">
                        <label>Direct Access Link (One-Click Login)</label>
                        <div class="value" style="font-size: 0.85rem;"><?= htmlspecialchars($newCredentials['portal_url']) ?></div>
                    </div>
                    
                    <div class="d-flex gap-2 mt-3">
                        <button class="copy-btn" onclick="copyCredentials()">
                            <i class="bi bi-clipboard me-1"></i> Copy All
                        </button>
                        <button class="copy-btn" onclick="printCredentials()">
                            <i class="bi bi-printer me-1"></i> Print
                        </button>
                        <button class="copy-btn" onclick="shareViaWhatsApp()">
                            <i class="bi bi-whatsapp me-1"></i> WhatsApp
                        </button>
                        <button class="copy-btn" onclick="shareViaEmail()">
                            <i class="bi bi-envelope me-1"></i> Email
                        </button>
                    </div>
                </div>
                
                <script>
                    const guestCredentials = {
                        username: '<?= htmlspecialchars($newCredentials['username']) ?>',
                        password: '<?= htmlspecialchars($newCredentials['password']) ?>',
                        url: '<?= htmlspecialchars($newCredentials['portal_url']) ?>',
                        room: '<?= htmlspecialchars($newCredentials['room_number'] ?? '') ?>',
                        name: '<?= htmlspecialchars($newCredentials['guest_name'] ?? '') ?>'
                    };
                </script>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <!-- Create Guest Access -->
        <div class="col-lg-6">
            <div class="portal-card no-print">
                <h5 class="mb-3"><i class="bi bi-person-plus me-2"></i>Create Guest Access</h5>
                <p class="text-muted small mb-3">Generate secure login credentials for a guest at check-in</p>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="create_guest_access">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Guest Name <span class="text-danger">*</span></label>
                            <input type="text" name="guest_name" class="form-control" required placeholder="John Doe">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Room Number <span class="text-danger">*</span></label>
                            <input type="text" name="room_number" class="form-control" required placeholder="101">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Check-in Date</label>
                            <input type="date" name="check_in_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Check-out Date</label>
                            <input type="date" name="check_out_date" class="form-control" value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email (Optional)</label>
                            <input type="email" name="guest_email" class="form-control" placeholder="guest@email.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone (Optional)</label>
                            <input type="tel" name="guest_phone" class="form-control" placeholder="+254 7XX XXX XXX">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary mt-3">
                        <i class="bi bi-key me-1"></i> Generate Credentials
                    </button>
                </form>
            </div>
            
            <div class="portal-card no-print">
                <h5 class="mb-3"><i class="bi bi-gear me-2"></i>Portal Settings</h5>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <div class="status-toggle mb-3">
                        <label class="toggle-switch">
                            <input type="checkbox" name="guest_portal_enabled" <?= $guestPortalEnabled ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <div>
                            <strong>Guest Portal Enabled</strong>
                            <div class="text-muted small">Allow registered guests to access the portal</div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i> Save Settings
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Security Info -->
        <div class="col-lg-6">
            <div class="portal-card">
                <h5 class="mb-3"><i class="bi bi-shield-check me-2"></i>Security Features</h5>
                
                <div class="security-info success mb-3">
                    <strong><i class="bi bi-lock-fill me-2"></i>AES-256-GCM Encryption</strong>
                    <p class="mb-0 small mt-1">All sensitive guest data is encrypted at rest</p>
                </div>
                
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Argon2ID password hashing</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Secure session tokens (SHA-256)</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Rate limiting (5 failed attempts = 30min lockout)</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Auto-expiry on checkout</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>IP-based activity logging</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>One-time access links</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>HTTPOnly secure cookies</li>
                </ul>
                
                <hr>
                
                <h6 class="mb-2">Portal URL</h6>
                <div class="url-display"><?= htmlspecialchars($securePortalUrl) ?></div>
            </div>
        </div>
    </div>
    
    <!-- Active Guest Accesses -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="portal-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="bi bi-people me-2"></i>Active Guest Accesses</h5>
                    <span class="badge bg-primary"><?= count($activeAccesses) ?> active</span>
                </div>
                
                <?php if (empty($activeAccesses)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox display-4"></i>
                        <p class="mt-2 mb-0">No active guest accesses</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="guest-table">
                            <thead>
                                <tr>
                                    <th>Guest ID</th>
                                    <th>Guest Name</th>
                                    <th>Room</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Last Login</th>
                                    <th>Logins</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeAccesses as $access): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($access['username']) ?></code></td>
                                        <td><?= htmlspecialchars($access['guest_name']) ?></td>
                                        <td><strong><?= htmlspecialchars($access['room_number']) ?></strong></td>
                                        <td><?= date('M j', strtotime($access['check_in_date'])) ?></td>
                                        <td><?= date('M j', strtotime($access['check_out_date'])) ?></td>
                                        <td><?= $access['last_login_at'] ? date('M j, g:i A', strtotime($access['last_login_at'])) : '<span class="text-muted">Never</span>' ?></td>
                                        <td><?= $access['login_count'] ?></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="regenerate_credentials">
                                                <input type="hidden" name="guest_access_id" value="<?= $access['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Regenerate credentials">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="revoke_access">
                                                <input type="hidden" name="guest_access_id" value="<?= $access['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Revoke access" 
                                                        onclick="return confirm('Revoke access for <?= htmlspecialchars($access['guest_name']) ?>?')">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
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
    
    <!-- How It Works -->
    <div class="row mt-4 no-print">
        <div class="col-12">
            <div class="portal-card">
                <h5 class="mb-3"><i class="bi bi-info-circle me-2"></i>How Secure Guest Access Works</h5>
                
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="d-flex align-items-start">
                            <div class="bg-primary text-white rounded-circle p-2 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">1</div>
                            <div>
                                <strong>Check-in</strong>
                                <p class="text-muted small mb-0">Generate credentials when guest checks in</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="d-flex align-items-start">
                            <div class="bg-primary text-white rounded-circle p-2 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">2</div>
                            <div>
                                <strong>Share Link</strong>
                                <p class="text-muted small mb-0">Send secure link via WhatsApp, Email, or print</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="d-flex align-items-start">
                            <div class="bg-primary text-white rounded-circle p-2 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">3</div>
                            <div>
                                <strong>Guest Logs In</strong>
                                <p class="text-muted small mb-0">Guest uses link or credentials to access portal</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="d-flex align-items-start">
                            <div class="bg-primary text-white rounded-circle p-2 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">4</div>
                            <div>
                                <strong>Auto-Expire</strong>
                                <p class="text-muted small mb-0">Access automatically expires on checkout</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyCredentials() {
    if (typeof guestCredentials === 'undefined') return;
    
    const text = `Guest Portal Access\n\nGuest ID: ${guestCredentials.username}\nPassword: ${guestCredentials.password}\n\nOr use this direct link:\n${guestCredentials.url}`;
    
    navigator.clipboard.writeText(text).then(() => {
        alert('Credentials copied to clipboard!');
    });
}

function printCredentials() {
    window.print();
}

function shareViaWhatsApp() {
    if (typeof guestCredentials === 'undefined') return;
    
    const text = `Welcome to our property!\n\nYour Guest Portal Access:\n\nGuest ID: ${guestCredentials.username}\nPassword: ${guestCredentials.password}\n\nOr click this link:\n${guestCredentials.url}\n\nUse this to submit maintenance or housekeeping requests during your stay.`;
    
    window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
}

function shareViaEmail() {
    if (typeof guestCredentials === 'undefined') return;
    
    const subject = 'Your Guest Portal Access';
    const body = `Welcome!\n\nYour Guest Portal Access:\n\nGuest ID: ${guestCredentials.username}\nPassword: ${guestCredentials.password}\n\nOr click this link:\n${guestCredentials.url}\n\nUse this to submit maintenance or housekeeping requests during your stay.`;
    
    window.location.href = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
}
</script>

<?php include 'includes/footer.php'; ?>
