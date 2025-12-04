<?php
/**
 * WAPOS - Privacy Policy
 * Legal privacy policy page
 * 
 * @copyright <?= date('Y') ?> Waesta Enterprises U Ltd. All rights reserved.
 */

require_once 'includes/bootstrap.php';

$pageTitle = 'Privacy Policy - WAPOS';
$lastUpdated = 'December 2024';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
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
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--surface-muted);
            color: var(--brand-deep);
            line-height: 1.8;
            margin: 0;
        }

        .page-nav {
            background: var(--surface);
            padding: 16px 0;
            border-bottom: 1px solid var(--border-soft);
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
        }

        .brand-name {
            font-weight: 700;
        }

        .nav-links a {
            padding: 8px 16px;
            text-decoration: none;
            color: var(--brand-muted);
            font-size: 0.9rem;
        }

        .nav-links a:hover {
            color: var(--brand-deep);
        }

        .legal-header {
            background: var(--brand-deep);
            color: #fff;
            padding: 48px 0;
        }

        .legal-header h1 {
            font-size: 2rem;
            margin: 0 0 8px;
        }

        .legal-header p {
            margin: 0;
            opacity: 0.8;
        }

        .legal-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 48px 20px;
        }

        .legal-card {
            background: var(--surface);
            border-radius: 12px;
            padding: 40px;
            border: 1px solid var(--border-soft);
        }

        .legal-card h2 {
            font-size: 1.3rem;
            margin: 32px 0 16px;
            color: var(--brand-deep);
        }

        .legal-card h2:first-child {
            margin-top: 0;
        }

        .legal-card h3 {
            font-size: 1.1rem;
            margin: 24px 0 12px;
        }

        .legal-card p {
            color: #475569;
            margin: 0 0 16px;
        }

        .legal-card ul {
            color: #475569;
            margin: 0 0 16px;
            padding-left: 24px;
        }

        .legal-card li {
            margin-bottom: 8px;
        }

        .highlight-box {
            background: var(--surface-muted);
            border-left: 4px solid var(--brand-primary);
            padding: 16px 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }

        .highlight-box p {
            margin: 0;
        }

        .footer {
            text-align: center;
            padding: 32px 0;
            color: var(--brand-muted);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <nav class="page-nav">
        <div class="container">
            <a href="<?= APP_URL ?>" class="brand-mark">
                <div class="brand-icon"><i class="bi bi-shop"></i></div>
                <span class="brand-name">WAPOS</span>
            </a>
            <div class="nav-links">
                <a href="<?= APP_URL ?>">Home</a>
                <a href="about.php">About</a>
                <a href="contact.php">Contact</a>
            </div>
        </div>
    </nav>

    <header class="legal-header">
        <div class="container">
            <h1>Privacy Policy</h1>
            <p>Last updated: <?= $lastUpdated ?></p>
        </div>
    </header>

    <div class="legal-content">
        <div class="legal-card">
            <h2>1. Introduction</h2>
            <p>Waesta Enterprises U Ltd ("we", "our", or "us") operates the WAPOS point of sale system. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our software.</p>
            
            <div class="highlight-box">
                <p><strong>Important:</strong> By using WAPOS, you agree to the collection and use of information in accordance with this policy.</p>
            </div>

            <h2>2. Information We Collect</h2>
            
            <h3>2.1 Information You Provide</h3>
            <p>We collect information that you voluntarily provide when using WAPOS, including:</p>
            <ul>
                <li><strong>Account Information:</strong> Name, email address, username, and password when creating user accounts</li>
                <li><strong>Business Information:</strong> Company name, address, phone number, and tax identification numbers</li>
                <li><strong>Transaction Data:</strong> Sales records, payment information, customer details, and inventory data</li>
                <li><strong>Communication Data:</strong> Messages sent through contact forms or support requests</li>
            </ul>

            <h3>2.2 Automatically Collected Information</h3>
            <p>When you use WAPOS, we may automatically collect:</p>
            <ul>
                <li>IP address and browser type</li>
                <li>Device information and operating system</li>
                <li>Usage patterns and feature interactions</li>
                <li>Error logs and system diagnostics</li>
            </ul>

            <h2>3. How We Use Your Information</h2>
            <p>We use the collected information for the following purposes:</p>
            <ul>
                <li><strong>Service Delivery:</strong> To provide, maintain, and improve WAPOS functionality</li>
                <li><strong>Account Management:</strong> To manage user accounts and provide customer support</li>
                <li><strong>Security:</strong> To detect, prevent, and address technical issues and security threats</li>
                <li><strong>Communication:</strong> To send important updates, security alerts, and support messages</li>
                <li><strong>Analytics:</strong> To understand usage patterns and improve our services</li>
                <li><strong>Legal Compliance:</strong> To comply with applicable laws and regulations</li>
            </ul>

            <h2>4. Data Storage and Security</h2>
            <p>We implement appropriate technical and organizational measures to protect your data:</p>
            <ul>
                <li>Encrypted data transmission using HTTPS/SSL</li>
                <li>Secure password hashing using industry-standard algorithms</li>
                <li>Role-based access control limiting data access</li>
                <li>Regular security audits and updates</li>
                <li>Audit logs tracking system access and changes</li>
            </ul>
            
            <div class="highlight-box">
                <p><strong>Data Location:</strong> Your data is stored on servers controlled by you or your hosting provider. Waesta Enterprises does not have direct access to your production data unless explicitly granted for support purposes.</p>
            </div>

            <h2>5. Data Sharing and Disclosure</h2>
            <p>We do not sell, trade, or rent your personal information. We may share data only in the following circumstances:</p>
            <ul>
                <li><strong>With Your Consent:</strong> When you explicitly authorize sharing</li>
                <li><strong>Service Providers:</strong> With trusted partners who assist in operating our services, bound by confidentiality agreements</li>
                <li><strong>Legal Requirements:</strong> When required by law, court order, or government request</li>
                <li><strong>Business Transfers:</strong> In connection with a merger, acquisition, or sale of assets</li>
            </ul>

            <h2>6. Your Rights (GDPR Compliance)</h2>
            <p>If you are in the European Economic Area (EEA), you have the following rights:</p>
            <ul>
                <li><strong>Access:</strong> Request a copy of your personal data</li>
                <li><strong>Rectification:</strong> Request correction of inaccurate data</li>
                <li><strong>Erasure:</strong> Request deletion of your data ("right to be forgotten")</li>
                <li><strong>Restriction:</strong> Request limitation of data processing</li>
                <li><strong>Portability:</strong> Request transfer of your data in a machine-readable format</li>
                <li><strong>Objection:</strong> Object to certain types of data processing</li>
            </ul>
            <p>To exercise these rights, contact us at <a href="mailto:privacy@waesta.com">privacy@waesta.com</a>.</p>

            <h2>7. Data Retention</h2>
            <p>We retain your data for as long as necessary to:</p>
            <ul>
                <li>Provide our services to you</li>
                <li>Comply with legal obligations (e.g., tax records)</li>
                <li>Resolve disputes and enforce agreements</li>
            </ul>
            <p>Transaction data may be retained for the period required by applicable tax and accounting laws in your jurisdiction.</p>

            <h2>8. Cookies and Tracking</h2>
            <p>WAPOS uses essential cookies for:</p>
            <ul>
                <li><strong>Session Management:</strong> To keep you logged in and maintain your session</li>
                <li><strong>Security:</strong> To prevent cross-site request forgery (CSRF) attacks</li>
                <li><strong>Preferences:</strong> To remember your settings and preferences</li>
            </ul>
            <p>We do not use third-party tracking or advertising cookies.</p>

            <h2>9. Children's Privacy</h2>
            <p>WAPOS is not intended for use by individuals under the age of 18. We do not knowingly collect personal information from children. If you believe we have collected information from a child, please contact us immediately.</p>

            <h2>10. International Data Transfers</h2>
            <p>If you access WAPOS from outside the country where your data is hosted, your information may be transferred across international borders. We ensure appropriate safeguards are in place for such transfers.</p>

            <h2>11. Changes to This Policy</h2>
            <p>We may update this Privacy Policy from time to time. We will notify you of any changes by:</p>
            <ul>
                <li>Posting the new policy on this page</li>
                <li>Updating the "Last updated" date</li>
                <li>Sending an email notification for significant changes</li>
            </ul>

            <h2>12. Contact Us</h2>
            <p>If you have questions about this Privacy Policy or our data practices, please contact us:</p>
            <ul>
                <li><strong>Company:</strong> Waesta Enterprises U Ltd</li>
                <li><strong>Email:</strong> <a href="mailto:privacy@waesta.com">privacy@waesta.com</a></li>
                <li><strong>Website:</strong> <a href="https://waesta.com">www.waesta.com</a></li>
            </ul>
        </div>
    </div>

    <footer class="footer">
        <p>&copy; <?= date('Y') ?> Waesta Enterprises U Ltd. All rights reserved.</p>
    </footer>
</body>
</html>
