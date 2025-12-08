<?php
/**
 * WAPOS - Deployment Guide (Printable)
 */
require_once 'includes/bootstrap.php';
$pageTitle = 'Deployment Guide - WAPOS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
            body { font-size: 11pt; }
            .container { max-width: 100%; }
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f8fafc; color: #0f172a; line-height: 1.6; }
        .page-header { background: linear-gradient(135deg, #059669, #047857); color: #fff; padding: 32px 0; margin-bottom: 32px; }
        .container { max-width: 900px; }
        .section-title { background: #1e293b; color: #fff; padding: 12px 20px; border-radius: 8px 8px 0 0; margin-bottom: 0; font-size: 1.2rem; }
        .section-content { background: #fff; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 8px 8px; padding: 20px; margin-bottom: 24px; }
        .data-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .data-table th, .data-table td { border: 1px solid #e2e8f0; padding: 10px 12px; text-align: left; }
        .data-table th { background: #f1f5f9; font-weight: 600; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }
        pre { background: #1e293b; color: #e2e8f0; padding: 16px; border-radius: 8px; overflow-x: auto; }
        pre code { background: none; color: inherit; }
        .alert-box { padding: 16px; border-radius: 8px; margin: 16px 0; }
        .alert-warning { background: #fef3c7; border-left: 4px solid #f59e0b; }
        .alert-info { background: #dbeafe; border-left: 4px solid #3b82f6; }
        .alert-success { background: #dcfce7; border-left: 4px solid #22c55e; }
        .step-number { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: #2563eb; color: #fff; border-radius: 50%; font-weight: bold; margin-right: 10px; }
        h3 { margin-top: 24px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; }
        .checklist { list-style: none; padding: 0; }
        .checklist li { padding: 8px 0; border-bottom: 1px dashed #e2e8f0; }
        .checklist li:before { content: "☐ "; font-size: 1.2em; }
    </style>
</head>
<body>
    <header class="page-header no-print">
        <div class="container">
            <a href="<?= APP_URL ?>" class="text-white text-decoration-none mb-2 d-inline-block"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
            <h1><i class="bi bi-cloud-upload me-2"></i> WAPOS Deployment Guide</h1>
            <p>Quick reference for deploying WAPOS on cPanel and Cloud platforms</p>
            <button onclick="window.print()" class="btn btn-light mt-2"><i class="bi bi-printer me-2"></i>Print Guide</button>
            <a href="deployment-manual.php" class="btn btn-outline-light mt-2 ms-2"><i class="bi bi-book me-2"></i>Full Manual</a>
        </div>
    </header>

    <div class="container pb-5">
        <div class="d-none d-print-block text-center mb-4">
            <h1>WAPOS Deployment Guide</h1>
            <p>Quick Reference | <?= date('F Y') ?></p>
            <hr>
        </div>

        <!-- Quick Start -->
        <section>
            <h2 class="section-title"><i class="bi bi-lightning-charge me-2"></i>Quick Start - Default Credentials</h2>
            <div class="section-content">
                <div class="alert-box alert-warning">
                    <strong>⚠️ Important:</strong> Change these credentials immediately after first login!
                </div>
                <table class="data-table">
                    <tr><th width="30%">URL</th><td><code>https://yourdomain.com</code></td></tr>
                    <tr><th>Username</th><td><code>superadmin</code></td></tr>
                    <tr><th>Password</th><td><code>Thepurpose@2025</code></td></tr>
                </table>
            </div>
        </section>

        <!-- System Requirements -->
        <section>
            <h2 class="section-title"><i class="bi bi-gear me-2"></i>System Requirements</h2>
            <div class="section-content">
                <table class="data-table">
                    <thead><tr><th>Component</th><th>Minimum</th><th>Recommended</th></tr></thead>
                    <tbody>
                        <tr><td>PHP</td><td>8.0</td><td>8.1 or 8.2</td></tr>
                        <tr><td>MySQL/MariaDB</td><td>5.7</td><td>8.0 / MariaDB 10.6</td></tr>
                        <tr><td>Memory</td><td>512MB</td><td>1GB+</td></tr>
                        <tr><td>Storage</td><td>1GB</td><td>5GB+</td></tr>
                    </tbody>
                </table>

                <h4 class="mt-4">Required PHP Extensions</h4>
                <div class="row">
                    <div class="col-md-6">
                        <ul class="checklist">
                            <li>pdo_mysql</li>
                            <li>mbstring</li>
                            <li>json</li>
                            <li>curl</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="checklist">
                            <li>openssl</li>
                            <li>gd</li>
                            <li>zip</li>
                            <li>fileinfo</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- cPanel Deployment -->
        <section class="page-break">
            <h2 class="section-title"><i class="bi bi-hdd-rack me-2"></i>cPanel Deployment (Shared Hosting)</h2>
            <div class="section-content">
                <h3><span class="step-number">1</span>Create Database</h3>
                <ol>
                    <li>Go to <strong>MySQL® Databases</strong> in cPanel</li>
                    <li>Create database: <code>yourusername_wapos</code></li>
                    <li>Create user: <code>yourusername_waposuser</code></li>
                    <li>Add user to database with <strong>ALL PRIVILEGES</strong></li>
                </ol>

                <h3><span class="step-number">2</span>Upload Files</h3>
                <ol>
                    <li>Go to <strong>File Manager</strong> → <code>public_html</code></li>
                    <li>Upload WAPOS zip file</li>
                    <li>Extract the zip file</li>
                    <li>Delete the zip file after extraction</li>
                </ol>

                <h3><span class="step-number">3</span>Import Database</h3>
                <ol>
                    <li>Go to <strong>phpMyAdmin</strong></li>
                    <li>Select your database</li>
                    <li>Click <strong>Import</strong></li>
                    <li>Upload <code>database/schema.sql</code></li>
                    <li>Import migration files in order (001, 002, 003, 004)</li>
                    <li>Import <code>database/seeds/core_seed.sql</code></li>
                </ol>

                <h3><span class="step-number">4</span>Create Production Config</h3>
                <p>Create file: <code>config.production.php</code></p>
                <pre><code>&lt;?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'yourusername_wapos');
define('DB_USER', 'yourusername_waposuser');
define('DB_PASS', 'your_secure_password');

define('APP_URL', 'https://yourdomain.com');
define('APP_ENV', 'production');
define('APP_DEBUG', false);</code></pre>

                <h3><span class="step-number">5</span>Set Permissions</h3>
                <pre><code>Directories: 755
Files: 644
storage/ folder: 775
config.production.php: 600</code></pre>

                <h3><span class="step-number">6</span>Enable SSL</h3>
                <ol>
                    <li>Go to <strong>SSL/TLS Status</strong></li>
                    <li>Click <strong>Run AutoSSL</strong></li>
                    <li>Wait for certificate installation</li>
                </ol>

                <h3><span class="step-number">7</span>Test & Login</h3>
                <ol>
                    <li>Visit <code>https://yourdomain.com</code></li>
                    <li>Login with default credentials</li>
                    <li><strong>Change password immediately!</strong></li>
                </ol>
            </div>
        </section>

        <!-- Cloud Deployment -->
        <section class="page-break">
            <h2 class="section-title"><i class="bi bi-cloud me-2"></i>Cloud Deployment (VPS)</h2>
            <div class="section-content">
                <div class="alert-box alert-info">
                    <strong>Supported Platforms:</strong> AWS EC2, DigitalOcean, Linode, Vultr, etc.
                </div>

                <h3><span class="step-number">1</span>Create Server</h3>
                <pre><code>OS: Ubuntu 22.04 LTS
RAM: 1GB minimum (2GB recommended)
Storage: 20GB SSD
Ports: 22 (SSH), 80 (HTTP), 443 (HTTPS)</code></pre>

                <h3><span class="step-number">2</span>Install LAMP Stack</h3>
                <pre><code># Update system
sudo apt update && sudo apt upgrade -y

# Install Apache
sudo apt install apache2 -y

# Install PHP 8.2
sudo apt install php8.2 php8.2-mysql php8.2-mbstring php8.2-curl php8.2-gd php8.2-zip -y

# Install MySQL
sudo apt install mysql-server -y

# Enable Apache modules
sudo a2enmod rewrite headers
sudo systemctl restart apache2</code></pre>

                <h3><span class="step-number">3</span>Configure MySQL</h3>
                <pre><code>sudo mysql

CREATE DATABASE wapos;
CREATE USER 'wapos_user'@'localhost' IDENTIFIED BY 'SecurePassword123!';
GRANT ALL PRIVILEGES ON wapos.* TO 'wapos_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;</code></pre>

                <h3><span class="step-number">4</span>Upload Files</h3>
                <pre><code># Create directory
sudo mkdir -p /var/www/wapos

# Upload via SCP
scp -r /local/wapos/* user@server:/var/www/wapos/

# Set permissions
sudo chown -R www-data:www-data /var/www/wapos
sudo chmod -R 755 /var/www/wapos
sudo chmod -R 775 /var/www/wapos/storage</code></pre>

                <h3><span class="step-number">5</span>Configure Apache</h3>
                <pre><code># Create virtual host
sudo nano /etc/apache2/sites-available/wapos.conf

&lt;VirtualHost *:80&gt;
    ServerName yourdomain.com
    DocumentRoot /var/www/wapos
    
    &lt;Directory /var/www/wapos&gt;
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;
&lt;/VirtualHost&gt;

# Enable site
sudo a2ensite wapos.conf
sudo systemctl reload apache2</code></pre>

                <h3><span class="step-number">6</span>Install SSL (Let's Encrypt)</h3>
                <pre><code>sudo apt install certbot python3-certbot-apache -y
sudo certbot --apache -d yourdomain.com</code></pre>

                <h3><span class="step-number">7</span>Configure Firewall</h3>
                <pre><code>sudo ufw enable
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp</code></pre>
            </div>
        </section>

        <!-- Post-Deployment Checklist -->
        <section class="page-break">
            <h2 class="section-title"><i class="bi bi-clipboard-check me-2"></i>Post-Deployment Checklist</h2>
            <div class="section-content">
                <h4>Security</h4>
                <ul class="checklist">
                    <li>Change default superadmin password</li>
                    <li>Set APP_DEBUG to false</li>
                    <li>Verify SSL certificate is active</li>
                    <li>Set config.production.php permissions to 600</li>
                    <li>Configure firewall (cloud only)</li>
                </ul>

                <h4>Functionality</h4>
                <ul class="checklist">
                    <li>Test login with all user roles</li>
                    <li>Test POS sale (cash payment)</li>
                    <li>Test receipt printing</li>
                    <li>Test email sending (if configured)</li>
                    <li>Verify offline mode works</li>
                </ul>

                <h4>Backup</h4>
                <ul class="checklist">
                    <li>Set up automated database backup</li>
                    <li>Set up file backup</li>
                    <li>Test restore procedure</li>
                </ul>

                <h4>Monitoring</h4>
                <ul class="checklist">
                    <li>Set up uptime monitoring</li>
                    <li>Configure error logging</li>
                    <li>Set up email alerts for errors</li>
                </ul>
            </div>
        </section>

        <!-- Important Paths -->
        <section>
            <h2 class="section-title"><i class="bi bi-folder me-2"></i>Important File Paths</h2>
            <div class="section-content">
                <table class="data-table">
                    <thead><tr><th>Item</th><th>cPanel</th><th>Cloud/VPS</th></tr></thead>
                    <tbody>
                        <tr><td>Web Root</td><td><code>public_html/wapos</code></td><td><code>/var/www/wapos</code></td></tr>
                        <tr><td>Config</td><td><code>wapos/config.production.php</code></td><td><code>/var/www/wapos/config.production.php</code></td></tr>
                        <tr><td>Logs</td><td><code>wapos/storage/logs</code></td><td><code>/var/www/wapos/storage/logs</code></td></tr>
                        <tr><td>Uploads</td><td><code>wapos/storage/uploads</code></td><td><code>/var/www/wapos/storage/uploads</code></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Support -->
        <section>
            <h2 class="section-title"><i class="bi bi-life-preserver me-2"></i>Support & Resources</h2>
            <div class="section-content">
                <table class="data-table">
                    <tr><td><strong>Full Deployment Manual</strong></td><td><a href="deployment-manual.php">deployment-manual.php</a></td></tr>
                    <tr><td><strong>Test Data Guide</strong></td><td><a href="test-data-guide.php">test-data-guide.php</a></td></tr>
                    <tr><td><strong>System Status</strong></td><td><a href="status.php">status.php</a></td></tr>
                </table>
                
                <div class="alert-box alert-success mt-4">
                    <strong>✅ Deployment Complete!</strong><br>
                    Your WAPOS system should now be live and accessible at your domain.
                </div>
            </div>
        </section>

        <div class="text-center text-muted mt-4 pt-4 border-top">
            <p>WAPOS Deployment Guide | Generated <?= date('F j, Y') ?></p>
        </div>
    </div>
</body>
</html>
