<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$pageTitle = 'Demo Feedback';
include 'includes/header.php';
?>

<style>
    .feedback-page .top-bar-title h5 {
        font-weight: 600;
    }
    .feedback-page .app-card {
        border-radius: 1rem;
        box-shadow: 0 0.35rem 1.2rem rgba(12, 68, 101, 0.08);
    }
    .feedback-page .rating-options .btn {
        min-width: 54px;
        border-radius: 999px;
        font-weight: 600;
    }
    .feedback-page .rating-options .btn-check:checked + span,
    .feedback-page .rating-options .btn-check:focus + span {
        color: #0d6efd;
    }
    .feedback-page .info-card .list-group-item {
        border: none;
        padding-left: 0;
        padding-right: 0;
    }
    @media (max-width: 991.98px) {
        .feedback-page .app-card {
            box-shadow: none;
        }
    }
</style>

<div class="main-content feedback-page">
    <div class="top-bar">
        <div class="top-bar-title">
            <h5 class="mb-0">Share Your Feedback</h5>
            <small class="text-muted">Focus group testers can leave comments, ratings, and optional contact information.</small>
        </div>
    </div>

    <div class="container-fluid py-4">
        <div class="row g-4 align-items-stretch">
            <div class="col-12 col-xl-7">
                <div class="app-card" data-elevation="md">
                    <div class="app-card-header d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-0"><i class="bi bi-chat-text me-2"></i>Feedback Form</h5>
                            <small class="text-muted">All fields except comments are optional, but more detail helps the product team.</small>
                        </div>
                    </div>

                    <div id="feedbackAlert" class="alert d-none" role="alert"></div>

                    <form id="feedbackForm" class="stack-md gap-4">
                        <div>
                            <label class="form-label">Overall Experience</label>
                            <div class="rating-options d-flex gap-2 flex-wrap">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <label class="btn btn-outline-primary d-flex align-items-center justify-content-center">
                                        <input type="radio" name="rating" value="<?= $i ?>" class="btn-check" autocomplete="off">
                                        <span><?= $i ?></span>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <small class="text-muted">1 = Needs a lot of work, 5 = Fantastic</small>
                        </div>

                        <div>
                            <label class="form-label">Comments *</label>
                            <textarea class="form-control" name="comments" id="feedbackComments" rows="5" placeholder="Tell us what worked well and what needs attention..." required></textarea>
                        </div>

                        <div>
                            <label class="form-label">Contact (optional)</label>
                            <input type="text" class="form-control" name="contact" placeholder="Email or phone if you'd like us to follow up">
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <small class="text-muted" id="feedbackContextNote">Context: <span id="feedbackContextValue"></span></small>
                            <button type="submit" class="btn btn-primary" id="feedbackSubmitBtn">
                                <i class="bi bi-send me-1"></i>Send Feedback
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="app-card info-card h-100">
                    <h6 class="text-uppercase text-muted small mb-3">Focus Group Tips</h6>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong>Be Specific:</strong>
                            <p class="mb-0 small text-muted">Mention the screen, workflow, or button that needs improvement.</p>
                        </li>
                        <li class="list-group-item">
                            <strong>Report Bugs:</strong>
                            <p class="mb-0 small text-muted">If something breaks, describe the exact steps that caused it.</p>
                        </li>
                        <li class="list-group-item">
                            <strong>Share Wishlist Ideas:</strong>
                            <p class="mb-0 small text-muted">Tell us about features that would make your daily tasks easier.</p>
                        </li>
                        <li class="list-group-item">
                            <strong>Optional Follow-up:</strong>
                            <p class="mb-0 small text-muted">Leave an email/phone if you'd like the team to reach out.</p>
                        </li>
                    </ul>
                </div>

                <div class="app-card mt-4">
                    <h6 class="text-uppercase text-muted small mb-3">What Happens Next?</h6>
                    <p class="mb-2">Every submission lands in the <code>demo_feedback</code> table. Product leads will triage ratings, tag themes, and respond when contact info is provided.</p>
                    <p class="mb-0 text-muted small">Need to escalate an issue urgently? Ping the facilitator on the focus group chat with a screenshot.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('feedbackForm');
    const alertBox = document.getElementById('feedbackAlert');
    const contextSpan = document.getElementById('feedbackContextValue');
    const submitBtn = document.getElementById('feedbackSubmitBtn');

    const contextPage = window.location.pathname;
    contextSpan.textContent = contextPage;

    function showAlert(type, message) {
        alertBox.classList.remove('d-none', 'alert-success', 'alert-danger');
        alertBox.classList.add(`alert-${type}`);
        alertBox.textContent = message;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';

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
                throw new Error(result.message || 'Unable to send feedback');
            }

            showAlert('success', result.message || 'Thanks for your input!');
            form.reset();
        } catch (error) {
            console.error(error);
            showAlert('danger', error.message || 'Something went wrong. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-send me-1"></i>Send Feedback';
        }
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
