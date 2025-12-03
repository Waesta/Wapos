<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$pageTitle = 'Feedback';
include 'includes/header.php';
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1"><i class="bi bi-chat-square-text me-2 text-primary"></i>Share Your Feedback</h5>
                <p class="text-muted mb-0 small">Help us improve by sharing your experience and suggestions.</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Feedback Form -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Feedback Form</h6>
            </div>
            <div class="card-body">
                <div id="feedbackAlert" class="alert d-none" role="alert"></div>

                <form id="feedbackForm">
                    <!-- Rating -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">How would you rate your overall experience?</label>
                        <div class="d-flex gap-2 mt-2">
                            <?php 
                            $ratingLabels = ['Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                            for ($i = 1; $i <= 5; $i++): 
                            ?>
                                <div class="text-center">
                                    <input type="radio" name="rating" value="<?= $i ?>" class="btn-check" id="rating-<?= $i ?>" autocomplete="off">
                                    <label class="btn btn-outline-primary rounded-circle d-flex align-items-center justify-content-center" 
                                           for="rating-<?= $i ?>" style="width: 50px; height: 50px; font-weight: 600;">
                                        <?= $i ?>
                                    </label>
                                    <small class="d-block text-muted mt-1" style="font-size: 0.7rem;"><?= $ratingLabels[$i-1] ?></small>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Category -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Feedback Category</label>
                        <select class="form-select" name="category">
                            <option value="">Select a category (optional)</option>
                            <option value="bug">🐛 Bug Report</option>
                            <option value="feature">💡 Feature Request</option>
                            <option value="improvement">🔧 Improvement Suggestion</option>
                            <option value="usability">🎯 Usability Issue</option>
                            <option value="performance">⚡ Performance Issue</option>
                            <option value="other">📝 General Feedback</option>
                        </select>
                    </div>

                    <!-- Comments -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Your Feedback <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="comments" id="feedbackComments" rows="5" 
                                  placeholder="Please describe your experience, any issues encountered, or suggestions for improvement..." required></textarea>
                        <div class="form-text">Be as specific as possible. Include screen names, steps to reproduce issues, or feature details.</div>
                    </div>

                    <!-- Contact -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Contact Information <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" class="form-control" name="contact" placeholder="Email or phone number for follow-up">
                        <div class="form-text">Leave your contact if you'd like us to reach out regarding your feedback.</div>
                    </div>

                    <!-- Submit -->
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Submitted as: <strong><?= htmlspecialchars($auth->getUser()['full_name'] ?? $auth->getUser()['username'] ?? 'Anonymous') ?></strong>
                        </small>
                        <button type="submit" class="btn btn-primary px-4" id="feedbackSubmitBtn">
                            <i class="bi bi-send me-2"></i>Submit Feedback
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tips Sidebar -->
    <div class="col-lg-4">
        <!-- Tips Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h6 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Tips for Great Feedback</h6>
            </div>
            <div class="card-body">
                <div class="d-flex mb-3">
                    <div class="flex-shrink-0">
                        <span class="badge bg-primary rounded-circle p-2">
                            <i class="bi bi-bullseye"></i>
                        </span>
                    </div>
                    <div class="ms-3">
                        <strong class="d-block">Be Specific</strong>
                        <small class="text-muted">Mention the exact screen, button, or workflow.</small>
                    </div>
                </div>
                <div class="d-flex mb-3">
                    <div class="flex-shrink-0">
                        <span class="badge bg-danger rounded-circle p-2">
                            <i class="bi bi-bug"></i>
                        </span>
                    </div>
                    <div class="ms-3">
                        <strong class="d-block">Report Bugs Clearly</strong>
                        <small class="text-muted">Describe the steps that caused the issue.</small>
                    </div>
                </div>
                <div class="d-flex mb-3">
                    <div class="flex-shrink-0">
                        <span class="badge bg-success rounded-circle p-2">
                            <i class="bi bi-stars"></i>
                        </span>
                    </div>
                    <div class="ms-3">
                        <strong class="d-block">Share Ideas</strong>
                        <small class="text-muted">Tell us features that would help your work.</small>
                    </div>
                </div>
                <div class="d-flex">
                    <div class="flex-shrink-0">
                        <span class="badge bg-info rounded-circle p-2">
                            <i class="bi bi-telephone"></i>
                        </span>
                    </div>
                    <div class="ms-3">
                        <strong class="d-block">Stay Connected</strong>
                        <small class="text-muted">Leave contact info for follow-up discussions.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Process Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>What Happens Next?</h6>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-start mb-3">
                    <span class="badge bg-light text-dark rounded-circle me-3 p-2">1</span>
                    <div>
                        <strong class="d-block small">Review</strong>
                        <small class="text-muted">Our team reviews all submissions daily.</small>
                    </div>
                </div>
                <div class="d-flex align-items-start mb-3">
                    <span class="badge bg-light text-dark rounded-circle me-3 p-2">2</span>
                    <div>
                        <strong class="d-block small">Prioritize</strong>
                        <small class="text-muted">Issues are categorized and prioritized.</small>
                    </div>
                </div>
                <div class="d-flex align-items-start">
                    <span class="badge bg-light text-dark rounded-circle me-3 p-2">3</span>
                    <div>
                        <strong class="d-block small">Action</strong>
                        <small class="text-muted">We implement fixes and reach out if needed.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="feedbackContextValue" value="<?= htmlspecialchars($_SERVER['HTTP_REFERER'] ?? '/wapos/feedback.php') ?>">

<script>
(function() {
    const form = document.getElementById('feedbackForm');
    const alertBox = document.getElementById('feedbackAlert');
    const contextInput = document.getElementById('feedbackContextValue');
    const submitBtn = document.getElementById('feedbackSubmitBtn');

    const contextPage = contextInput.value || window.location.pathname;

    function showAlert(type, message, icon) {
        alertBox.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
        alertBox.classList.add(`alert-${type}`);
        alertBox.innerHTML = `<i class="bi ${icon} me-2"></i>${message}`;
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());
        payload.context_page = contextPage;

        try {
            const response = await fetch('api/submit-feedback.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message || 'Unable to submit feedback');
            }

            showAlert('success', 'Thank you! Your feedback has been submitted successfully.', 'bi-check-circle-fill');
            form.reset();
        } catch (error) {
            console.error(error);
            showAlert('danger', error.message || 'Something went wrong. Please try again.', 'bi-exclamation-triangle-fill');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-send me-2"></i>Submit Feedback';
        }
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
