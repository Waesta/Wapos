<?php
/**
 * WAPOS - Terms of Service
 * Legal terms and conditions
 * 
 * @copyright <?= date('Y') ?> Waesta Enterprises U Ltd. All rights reserved.
 */

require_once 'includes/bootstrap.php';

$pageTitle = 'Terms of Service - WAPOS';
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

        .legal-card ul, .legal-card ol {
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

        .highlight-box.warning {
            border-left-color: #f59e0b;
            background: #fffbeb;
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
            <h1>Terms of Service</h1>
            <p>Last updated: <?= $lastUpdated ?></p>
        </div>
    </header>

    <div class="legal-content">
        <div class="legal-card">
            <h2>1. Agreement to Terms</h2>
            <p>These Terms of Service ("Terms") constitute a legally binding agreement between you ("User", "you", or "your") and Waesta Enterprises U Ltd ("Company", "we", "our", or "us") governing your use of the WAPOS point of sale system ("Software", "Service", or "WAPOS").</p>
            
            <div class="highlight-box">
                <p><strong>By accessing or using WAPOS, you agree to be bound by these Terms.</strong> If you do not agree to these Terms, you may not access or use the Software.</p>
            </div>

            <h2>2. Description of Service</h2>
            <p>WAPOS is a comprehensive business management software system that includes:</p>
            <ul>
                <li>Point of Sale (POS) functionality</li>
                <li>Restaurant and hospitality management</li>
                <li>Inventory management</li>
                <li>Delivery tracking and management</li>
                <li>Housekeeping and maintenance modules</li>
                <li>Accounting and financial reporting</li>
                <li>User and role management</li>
            </ul>

            <h2>3. License Grant</h2>
            <h3>3.1 License</h3>
            <p>Subject to your compliance with these Terms and payment of applicable fees, we grant you a limited, non-exclusive, non-transferable, revocable license to:</p>
            <ul>
                <li>Install and use WAPOS for your internal business operations</li>
                <li>Access and use the documentation provided with the Software</li>
                <li>Create user accounts for your authorized employees</li>
            </ul>

            <h3>3.2 Restrictions</h3>
            <p>You may NOT:</p>
            <ul>
                <li>Copy, modify, or distribute the Software without authorization</li>
                <li>Reverse engineer, decompile, or disassemble the Software</li>
                <li>Rent, lease, or lend the Software to third parties</li>
                <li>Remove or alter any proprietary notices or labels</li>
                <li>Use the Software for any unlawful purpose</li>
                <li>Sublicense or resell the Software without written consent</li>
            </ul>

            <h2>4. User Accounts</h2>
            <h3>4.1 Account Creation</h3>
            <p>To use WAPOS, you must create user accounts. You agree to:</p>
            <ul>
                <li>Provide accurate and complete information</li>
                <li>Maintain the security of account credentials</li>
                <li>Promptly update any changes to account information</li>
                <li>Accept responsibility for all activities under your accounts</li>
            </ul>

            <h3>4.2 Account Security</h3>
            <p>You are responsible for:</p>
            <ul>
                <li>Keeping passwords confidential</li>
                <li>Restricting access to authorized personnel only</li>
                <li>Notifying us immediately of any unauthorized access</li>
                <li>Implementing appropriate access controls within your organization</li>
            </ul>

            <h2>5. Payment Terms</h2>
            <h3>5.1 Fees</h3>
            <p>Use of WAPOS may be subject to license fees, subscription fees, or other charges as agreed in your purchase agreement or invoice. All fees are:</p>
            <ul>
                <li>Due as specified in your agreement</li>
                <li>Non-refundable unless otherwise stated</li>
                <li>Subject to applicable taxes</li>
            </ul>

            <h3>5.2 Late Payment</h3>
            <p>Failure to pay fees when due may result in:</p>
            <ul>
                <li>Suspension of access to the Software</li>
                <li>Late payment charges</li>
                <li>Termination of your license</li>
            </ul>

            <h2>6. Data Ownership and Responsibility</h2>
            <h3>6.1 Your Data</h3>
            <p>You retain all ownership rights to the data you enter into WAPOS ("Your Data"). This includes:</p>
            <ul>
                <li>Customer information</li>
                <li>Transaction records</li>
                <li>Inventory data</li>
                <li>Financial records</li>
                <li>Employee information</li>
            </ul>

            <h3>6.2 Data Responsibility</h3>
            <p>You are solely responsible for:</p>
            <ul>
                <li>The accuracy and legality of Your Data</li>
                <li>Obtaining necessary consents for data collection</li>
                <li>Compliance with data protection laws</li>
                <li>Regular backups of Your Data</li>
            </ul>

            <div class="highlight-box warning">
                <p><strong>Important:</strong> We strongly recommend maintaining regular backups of your data. While we implement security measures, you are ultimately responsible for protecting your business data.</p>
            </div>

            <h2>7. Acceptable Use</h2>
            <p>You agree to use WAPOS only for lawful purposes. You shall NOT:</p>
            <ul>
                <li>Use the Software for fraudulent or illegal activities</li>
                <li>Attempt to gain unauthorized access to systems or data</li>
                <li>Interfere with or disrupt the Software's operation</li>
                <li>Upload malicious code or harmful content</li>
                <li>Violate any applicable laws or regulations</li>
                <li>Infringe on intellectual property rights</li>
            </ul>

            <h2>8. Intellectual Property</h2>
            <h3>8.1 Ownership</h3>
            <p>WAPOS and all related intellectual property rights are owned by Waesta Enterprises U Ltd. This includes:</p>
            <ul>
                <li>Software code and architecture</li>
                <li>User interface designs</li>
                <li>Documentation and manuals</li>
                <li>Trademarks and logos</li>
                <li>Trade secrets and know-how</li>
            </ul>

            <h3>8.2 Feedback</h3>
            <p>If you provide suggestions, ideas, or feedback about WAPOS, you grant us a perpetual, royalty-free license to use such feedback for any purpose without compensation to you.</p>

            <h2>9. Support and Maintenance</h2>
            <h3>9.1 Support Services</h3>
            <p>Support services may be provided as part of your license agreement and may include:</p>
            <ul>
                <li>Email and phone support during business hours</li>
                <li>Bug fixes and security patches</li>
                <li>Access to documentation and user manuals</li>
                <li>Software updates and upgrades</li>
            </ul>

            <h3>9.2 Support Limitations</h3>
            <p>Support does not include:</p>
            <ul>
                <li>Issues caused by unauthorized modifications</li>
                <li>Problems due to third-party software or hardware</li>
                <li>Training beyond initial setup</li>
                <li>Custom development or modifications</li>
            </ul>

            <h2>10. Warranty Disclaimer</h2>
            <div class="highlight-box warning">
                <p><strong>WAPOS IS PROVIDED "AS IS" AND "AS AVAILABLE" WITHOUT WARRANTIES OF ANY KIND.</strong> We disclaim all warranties, express or implied, including but not limited to merchantability, fitness for a particular purpose, and non-infringement.</p>
            </div>
            <p>We do not warrant that:</p>
            <ul>
                <li>The Software will be uninterrupted or error-free</li>
                <li>Defects will be corrected</li>
                <li>The Software will meet your specific requirements</li>
                <li>The Software is free from viruses or harmful components</li>
            </ul>

            <h2>11. Limitation of Liability</h2>
            <p>TO THE MAXIMUM EXTENT PERMITTED BY LAW:</p>
            <ul>
                <li>We shall not be liable for any indirect, incidental, special, consequential, or punitive damages</li>
                <li>Our total liability shall not exceed the fees paid by you in the twelve (12) months preceding the claim</li>
                <li>We are not liable for loss of data, profits, revenue, or business opportunities</li>
            </ul>

            <h2>12. Indemnification</h2>
            <p>You agree to indemnify and hold harmless Waesta Enterprises U Ltd, its officers, directors, employees, and agents from any claims, damages, losses, or expenses arising from:</p>
            <ul>
                <li>Your use of the Software</li>
                <li>Your violation of these Terms</li>
                <li>Your violation of any third-party rights</li>
                <li>Your Data or content</li>
            </ul>

            <h2>13. Term and Termination</h2>
            <h3>13.1 Term</h3>
            <p>These Terms remain in effect until terminated by either party.</p>

            <h3>13.2 Termination by You</h3>
            <p>You may terminate by discontinuing use of the Software and notifying us in writing.</p>

            <h3>13.3 Termination by Us</h3>
            <p>We may terminate or suspend your access immediately if you:</p>
            <ul>
                <li>Breach these Terms</li>
                <li>Fail to pay applicable fees</li>
                <li>Engage in fraudulent or illegal activity</li>
                <li>Become insolvent or bankrupt</li>
            </ul>

            <h3>13.4 Effect of Termination</h3>
            <p>Upon termination:</p>
            <ul>
                <li>Your license to use WAPOS ends immediately</li>
                <li>You must cease all use of the Software</li>
                <li>You should export Your Data before termination</li>
                <li>Provisions that should survive termination will remain in effect</li>
            </ul>

            <h2>14. Governing Law</h2>
            <p>These Terms shall be governed by and construed in accordance with the laws of the jurisdiction where Waesta Enterprises U Ltd is registered, without regard to conflict of law principles.</p>

            <h2>15. Dispute Resolution</h2>
            <p>Any disputes arising from these Terms shall be resolved through:</p>
            <ol>
                <li><strong>Negotiation:</strong> Good faith discussions between parties</li>
                <li><strong>Mediation:</strong> If negotiation fails, through a mutually agreed mediator</li>
                <li><strong>Arbitration:</strong> Binding arbitration as a final resort</li>
            </ol>

            <h2>16. Changes to Terms</h2>
            <p>We reserve the right to modify these Terms at any time. We will provide notice of material changes by:</p>
            <ul>
                <li>Posting updated Terms on our website</li>
                <li>Updating the "Last updated" date</li>
                <li>Sending email notification for significant changes</li>
            </ul>
            <p>Continued use of WAPOS after changes constitutes acceptance of the modified Terms.</p>

            <h2>17. General Provisions</h2>
            <ul>
                <li><strong>Entire Agreement:</strong> These Terms constitute the entire agreement between you and us regarding WAPOS</li>
                <li><strong>Severability:</strong> If any provision is found unenforceable, the remaining provisions remain in effect</li>
                <li><strong>Waiver:</strong> Failure to enforce any right does not constitute a waiver</li>
                <li><strong>Assignment:</strong> You may not assign these Terms without our written consent</li>
            </ul>

            <h2>18. Contact Information</h2>
            <p>For questions about these Terms, please contact us:</p>
            <ul>
                <li><strong>Company:</strong> Waesta Enterprises U Ltd</li>
                <li><strong>Email:</strong> <a href="mailto:legal@waesta.com">legal@waesta.com</a></li>
                <li><strong>Website:</strong> <a href="https://waesta.com">www.waesta.com</a></li>
            </ul>
        </div>
    </div>

    <footer class="footer">
        <p>&copy; <?= date('Y') ?> Waesta Enterprises U Ltd. All rights reserved.</p>
    </footer>
</body>
</html>
