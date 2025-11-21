<?php
require_once 'includes/bootstrap.php';

$publicToken = getenv('MAINTENANCE_PUBLIC_TOKEN') ?: ($_ENV['MAINTENANCE_PUBLIC_TOKEN'] ?? $_SERVER['MAINTENANCE_PUBLIC_TOKEN'] ?? null);
if (!$publicToken) {
    http_response_code(503);
    echo '<h1>Maintenance reporting unavailable</h1><p>The guest reporting portal has not been configured. Please contact the hotel front desk.</p>';
    exit;
}

$pageTitle = 'Report a Room Issue';
include 'includes/public-header.php';
?>

<div class="container py-5" style="max-width: 720px;">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <h3 class="fw-semibold">Report a Room Issue</h3>
                <p class="text-muted mb-0">Let our team know what needs attention and we will update you once it is resolved.</p>
            </div>

            <form id="guestReportForm" class="row g-3">
                <div class="col-12">
                    <label class="form-label">Your Name</label>
                    <input type="text" class="form-control" name="reporter_name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact (Phone or Email)</label>
                    <input type="text" class="form-control" name="reporter_contact" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Room Number</label>
                    <input type="text" class="form-control" name="room_identifier" placeholder="e.g. 204" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Issue Title</label>
                    <input type="text" class="form-control" name="title" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Describe the Problem</label>
                    <textarea class="form-control" name="description" rows="4" required></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Priority</label>
                    <select class="form-select" name="priority">
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Booking Reference (optional)</label>
                    <input type="text" class="form-control" name="reference_code" placeholder="e.g. BK-1234">
                </div>
                <input type="hidden" name="public_key" value="<?= htmlspecialchars($publicToken) ?>">
                <input type="hidden" name="action" value="create">

                <div class="col-12 d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">Submit Issue</button>
                </div>
            </form>

            <div id="guestReportFeedback" class="alert mt-4 d-none" role="alert"></div>

            <div class="mt-4 small text-muted">
                <p class="mb-1">After submitting, you will receive a tracking code. Keep it handy if you need to contact the front desk for updates.</p>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('guestReportForm');
    const feedback = document.getElementById('guestReportFeedback');

    form.addEventListener('submit', function(event) {
        event.preventDefault();
        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;

        const formData = Object.fromEntries(new FormData(form).entries());
        formData.reporter_type = 'guest';

        fetch('api/maintenance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
            .then(resp => resp.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Unable to submit issue.');
                }
                form.reset();
                feedback.className = 'alert alert-success mt-4';
                const tracking = data.request && data.request.tracking_code ? data.request.tracking_code : 'pending';
                feedback.innerHTML = `Thank you! Your issue has been logged. <strong>Tracking Code: ${tracking}</strong>`;
                feedback.classList.remove('d-none');
            })
            .catch(err => {
                feedback.className = 'alert alert-danger mt-4';
                feedback.textContent = err.message;
                feedback.classList.remove('d-none');
            })
            .finally(() => {
                submitButton.disabled = false;
            });
    });
})();
</script>

<?php include 'includes/public-footer.php'; ?>
