<?php
/**
 * WAPOS - Contact Page
 * Contact form and support information
 * 
 * @copyright <?= date('Y') ?> Waesta Enterprises U Ltd. All rights reserved.
 */

require_once 'includes/bootstrap.php';

$pageTitle = 'Contact Us - WAPOS';
$pageDescription = 'Get in touch with Waesta Enterprises for WAPOS support, sales inquiries, or partnership opportunities.';

$messageSent = false;
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validate
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Store in database or send email
        try {
            $db = Database::getInstance();
            
            // Create contact_messages table if not exists
            $db->query("CREATE TABLE IF NOT EXISTS contact_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                company VARCHAR(255),
                subject VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                status ENUM('new', 'read', 'replied', 'closed') DEFAULT 'new',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            $db->query(
                "INSERT INTO contact_messages (name, email, company, subject, message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$name, $email, $company, $subject, $message, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
            );
            
            $messageSent = true;
        } catch (Exception $e) {
            $error = 'Sorry, there was an error sending your message. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="robots" content="index, follow">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --brand-deep: #0f172a;
            --brand-primary: #2563eb;
            --brand-muted: #64748b;
            --surface: #ffffff;
            --surface-muted: #f8fafc;
            --border-soft: #e2e8f0;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--surface-muted);
            color: var(--brand-deep);
            line-height: 1.7;
            margin: 0;
        }

        .page-nav {
            background: var(--surface);
            padding: 16px 0;
            border-bottom: 1px solid var(--border-soft);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .page-nav .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand-mark {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--brand-deep);
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--brand-primary), #1d4ed8);
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 1.2rem;
        }

        .brand-name {
            font-weight: 700;
            font-size: 1.1rem;
        }

        .nav-links {
            display: flex;
            gap: 8px;
        }

        .nav-links a {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--brand-muted);
            transition: all 0.2s;
        }

        .nav-links a:hover {
            background: var(--surface-muted);
            color: var(--brand-deep);
        }

        .nav-links a.btn-primary {
            background: var(--brand-primary);
            color: #fff;
        }

        .hero-section {
            background: linear-gradient(135deg, var(--brand-primary), #1d4ed8);
            color: #fff;
            padding: 64px 0;
            text-align: center;
        }

        .hero-section h1 {
            font-size: 2.2rem;
            margin: 0 0 12px;
        }

        .hero-section p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
        }

        .contact-section {
            padding: 64px 0;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 48px;
        }

        @media (max-width: 768px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }
        }

        .contact-info h2 {
            font-size: 1.5rem;
            margin: 0 0 24px;
        }

        .contact-item {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }

        .contact-item i {
            width: 48px;
            height: 48px;
            background: var(--surface);
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            display: grid;
            place-items: center;
            color: var(--brand-primary);
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .contact-item h4 {
            margin: 0 0 4px;
            font-size: 1rem;
        }

        .contact-item p {
            margin: 0;
            color: var(--brand-muted);
            font-size: 0.95rem;
        }

        .contact-item a {
            color: var(--brand-primary);
            text-decoration: none;
        }

        .contact-item a:hover {
            text-decoration: underline;
        }

        .contact-form-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 32px;
            border: 1px solid var(--border-soft);
        }

        .contact-form-card h2 {
            font-size: 1.3rem;
            margin: 0 0 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }

        .form-group label .required {
            color: #dc2626;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-soft);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .btn-submit {
            width: 100%;
            padding: 14px 24px;
            background: var(--brand-primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: #1d4ed8;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #10b981;
            color: #065f46;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #ef4444;
            color: #991b1b;
        }

        .faq-section {
            padding: 64px 0;
            background: var(--surface);
        }

        .faq-section h2 {
            text-align: center;
            font-size: 1.5rem;
            margin: 0 0 32px;
        }

        .faq-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            max-width: 900px;
            margin: 0 auto;
        }

        .faq-item {
            padding: 24px;
            background: var(--surface-muted);
            border-radius: 12px;
        }

        .faq-item h4 {
            margin: 0 0 8px;
            font-size: 1rem;
        }

        .faq-item p {
            margin: 0;
            color: var(--brand-muted);
            font-size: 0.9rem;
        }

        .footer {
            background: var(--brand-deep);
            color: #fff;
            padding: 32px 0;
            text-align: center;
        }

        .footer p {
            margin: 0;
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="page-nav">
        <div class="container">
            <a href="<?= APP_URL ?>" class="brand-mark">
                <div class="brand-icon"><i class="bi bi-shop"></i></div>
                <span class="brand-name">WAPOS</span>
            </a>
            <div class="nav-links">
                <a href="<?= APP_URL ?>">Home</a>
                <a href="about.php">About</a>
                <a href="resources.php">User Manual</a>
                <a href="contact.php">Contact</a>
                <a href="login.php" class="btn-primary">Sign In</a>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero-section">
        <div class="container">
            <h1>Contact Us</h1>
            <p>Have questions about WAPOS? We're here to help.</p>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="container">
            <div class="contact-grid">
                <!-- Contact Info -->
                <div class="contact-info">
                    <h2>Get in Touch</h2>
                    
                    <div class="contact-item">
                        <i class="bi bi-envelope"></i>
                        <div>
                            <h4>Email</h4>
                            <p><a href="mailto:info@waesta.com">info@waesta.com</a></p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <i class="bi bi-globe"></i>
                        <div>
                            <h4>Website</h4>
                            <p><a href="https://waesta.com" target="_blank">www.waesta.com</a></p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <i class="bi bi-clock"></i>
                        <div>
                            <h4>Support Hours</h4>
                            <p>Monday - Friday: 8:00 AM - 6:00 PM<br>Saturday: 9:00 AM - 1:00 PM</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <i class="bi bi-building"></i>
                        <div>
                            <h4>Company</h4>
                            <p>Waesta Enterprises U Ltd</p>
                        </div>
                    </div>
                </div>

                <!-- Contact Form -->
                <div class="contact-form-card">
                    <h2>Send us a Message</h2>
                    
                    <?php if ($messageSent): ?>
                        <div class="alert alert-success">
                            <strong>Thank you!</strong> Your message has been sent successfully. We'll get back to you within 24-48 hours.
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$messageSent): ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Your Name <span class="required">*</span></label>
                            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Email Address <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Company / Organization</label>
                            <input type="text" name="company" class="form-control" value="<?= htmlspecialchars($_POST['company'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Subject <span class="required">*</span></label>
                            <select name="subject" class="form-control" required>
                                <option value="">Select a subject...</option>
                                <option value="Sales Inquiry" <?= ($_POST['subject'] ?? '') === 'Sales Inquiry' ? 'selected' : '' ?>>Sales Inquiry</option>
                                <option value="Technical Support" <?= ($_POST['subject'] ?? '') === 'Technical Support' ? 'selected' : '' ?>>Technical Support</option>
                                <option value="Feature Request" <?= ($_POST['subject'] ?? '') === 'Feature Request' ? 'selected' : '' ?>>Feature Request</option>
                                <option value="Partnership" <?= ($_POST['subject'] ?? '') === 'Partnership' ? 'selected' : '' ?>>Partnership Opportunity</option>
                                <option value="General Question" <?= ($_POST['subject'] ?? '') === 'General Question' ? 'selected' : '' ?>>General Question</option>
                                <option value="Other" <?= ($_POST['subject'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Message <span class="required">*</span></label>
                            <textarea name="message" class="form-control" required placeholder="How can we help you?"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn-submit">
                            <i class="bi bi-send me-2"></i> Send Message
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <h2>Frequently Asked Questions</h2>
            <div class="faq-grid">
                <div class="faq-item">
                    <h4>How do I get WAPOS for my business?</h4>
                    <p>Contact us through this form or email us at info@waesta.com. We'll discuss your needs and provide a customized solution.</p>
                </div>
                <div class="faq-item">
                    <h4>Is training included?</h4>
                    <p>Yes, we provide comprehensive training for your team as part of the setup process, plus access to our detailed user manual.</p>
                </div>
                <div class="faq-item">
                    <h4>Can WAPOS be customized?</h4>
                    <p>Absolutely. WAPOS is highly configurable and can be customized to match your specific business requirements.</p>
                </div>
                <div class="faq-item">
                    <h4>What support do you offer?</h4>
                    <p>We offer email support, remote assistance, and on-site support depending on your service plan.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Waesta Enterprises U Ltd. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
