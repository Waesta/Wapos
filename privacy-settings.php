<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$pageTitle = 'Privacy & Data Settings';
include 'includes/header.php';

$user = $auth->getUser();
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1"><i class="bi bi-shield-lock me-2 text-primary"></i>Privacy & Data Settings</h5>
                <p class="text-muted mb-0 small">Manage your personal data and privacy preferences (GDPR Compliant)</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Data Export Section -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0"><i class="bi bi-download me-2"></i>Export Your Data</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Download a copy of all your personal data stored in our system. This includes your profile information, 
                    transaction history, and activity logs.
                </p>
                <div class="alert alert-info small">
                    <i class="bi bi-info-circle me-2"></i>
                    You can export your data up to 3 times per day. The export will be in JSON format.
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" id="btnExportData">
                        <i class="bi bi-file-earmark-arrow-down me-2"></i>Download My Data
                    </button>
                    <button class="btn btn-outline-secondary" id="btnViewData">
                        <i class="bi bi-eye me-2"></i>Preview Data
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Your Information -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0"><i class="bi bi-person me-2"></i>Your Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td class="text-muted">Username</td>
                        <td class="fw-semibold"><?= htmlspecialchars($user['username']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Full Name</td>
                        <td class="fw-semibold"><?= htmlspecialchars($user['full_name']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Email</td>
                        <td class="fw-semibold"><?= htmlspecialchars($user['email'] ?? 'Not set') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Phone</td>
                        <td class="fw-semibold"><?= htmlspecialchars($user['phone'] ?? 'Not set') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Account Created</td>
                        <td class="fw-semibold"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Data Retention Info -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Data Retention</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    We retain your data according to the following policies:
                </p>
                <ul class="list-unstyled small">
                    <li class="mb-2">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        <strong>Account Data:</strong> Retained while account is active
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        <strong>Transaction Records:</strong> 7 years (legal requirement)
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        <strong>Activity Logs:</strong> 90 days
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        <strong>Session Data:</strong> Until logout or expiry
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Account Deletion -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm border-danger">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 text-danger"><i class="bi bi-trash me-2"></i>Delete Account</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    You can request to delete your account and all associated personal data. 
                    This action is irreversible and will be processed within 30 days.
                </p>
                <div class="alert alert-warning small">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Some data may be retained for legal compliance (e.g., financial records).
                </div>
                <button class="btn btn-outline-danger" id="btnDeleteAccount">
                    <i class="bi bi-trash me-2"></i>Request Account Deletion
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Data Preview Modal -->
<div class="modal fade" id="dataPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-earmark-code me-2"></i>Your Data Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="dataPreviewContent" class="bg-light p-3 rounded" style="max-height: 500px; overflow: auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btnDownloadFromPreview">
                    <i class="bi bi-download me-2"></i>Download
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Confirm Account Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to request account deletion? This will:</p>
                <ul>
                    <li>Remove all your personal information</li>
                    <li>Anonymize your activity history</li>
                    <li>Revoke access to the system</li>
                </ul>
                <div class="mb-3">
                    <label class="form-label">Reason for leaving (optional)</label>
                    <textarea class="form-control" id="deleteReason" rows="2"></textarea>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="confirmDelete">
                    <label class="form-check-label" for="confirmDelete">
                        I understand this action cannot be undone
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btnConfirmDelete" disabled>
                    <i class="bi bi-trash me-2"></i>Delete My Account
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Export data
    document.getElementById('btnExportData').addEventListener('click', function() {
        window.location.href = 'api/gdpr-export.php?action=download';
    });
    
    // Preview data
    document.getElementById('btnViewData').addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
        
        try {
            const response = await fetch('api/gdpr-export.php?action=export');
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('dataPreviewContent').textContent = 
                    JSON.stringify(result.data, null, 2);
                new bootstrap.Modal(document.getElementById('dataPreviewModal')).show();
            } else {
                alert(result.message || 'Failed to load data');
            }
        } catch (error) {
            alert('An error occurred. Please try again.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-eye me-2"></i>Preview Data';
        }
    });
    
    // Download from preview
    document.getElementById('btnDownloadFromPreview').addEventListener('click', function() {
        window.location.href = 'api/gdpr-export.php?action=download';
    });
    
    // Delete account
    document.getElementById('btnDeleteAccount').addEventListener('click', function() {
        new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
    });
    
    // Enable delete button when checkbox is checked
    document.getElementById('confirmDelete').addEventListener('change', function() {
        document.getElementById('btnConfirmDelete').disabled = !this.checked;
    });
    
    // Confirm delete
    document.getElementById('btnConfirmDelete').addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
        
        try {
            const response = await fetch('api/gdpr-export.php?action=delete_request', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_request',
                    reason: document.getElementById('deleteReason').value
                })
            });
            const result = await response.json();
            
            if (result.success) {
                alert(result.message);
                bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
            } else {
                alert(result.message || 'Failed to submit request');
            }
        } catch (error) {
            alert('An error occurred. Please try again.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-trash me-2"></i>Delete My Account';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
