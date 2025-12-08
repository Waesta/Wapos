<?php
/**
 * WAPOS - Full Deployment Manual (Printable)
 */
require_once 'includes/bootstrap.php';
$pageTitle = 'Full Deployment Manual - WAPOS';
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
            body { font-size: 10pt; }
            .container { max-width: 100%; }
            pre { font-size: 9pt; }
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f8fafc; color: #0f172a; line-height: 1.6; }
        .page-header { background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #fff; padding: 32px 0; margin-bottom: 32px; }
        .container { max-width: 900px; }
        .section-title { background: #1e293b; color: #fff; padding: 12px 20px; border-radius: 8px 8px 0 0; margin-bottom: 0; font-size: 1.1rem; }
        .section-content { background: #fff; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 8px 8px; padding: 20px; margin-bottom: 24px; }
        .data-table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 0.9em; }
        .data-table th, .data-table td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; }
        .data-table th { background: #f1f5f9; font-weight: 600; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.85em; }
        pre { background: #1e293b; color: #e2e8f0; padding: 12px; border-radius: 8px; overflow-x: auto; font-size: 0.85em; margin: 12px 0; }
        pre code { background: none; color: inherit; }
        .alert-box { padding: 12px 16px; border-radius: 8px; margin: 12px 0; font-size: 0.9em; }
        .alert-warning { background: #fef3c7; border-left: 4px solid #f59e0b; }
        .alert-info { background: #dbeafe; border-left: 4px solid #3b82f6; }
        .alert-success { background: #dcfce7; border-left: 4px solid #22c55e; }
        .alert-danger { background: #fee2e2; border-left: 4px solid #ef4444; }
        h3 { margin-top: 20px; padding-bottom: 6px; border-bottom: 2px solid #e2e8f0; font-size: 1.1rem; }
        h4 { margin-top: 16px; color: #475569; font-size: 1rem; }
        .toc { background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 24px; }
        .toc ul { columns: 2; column-gap: 30px; }
        .toc li { margin-bottom: 6px; }
        .toc a { color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
    <header class="page-header no-print">
        <div class="container">
            <a href="<?= APP_URL ?>" class="text-white text-decoration-none mb-2 d-inline-block"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
            <h1><i class="bi bi-book me-2"></i> WAPOS Full Deployment Manual</h1>
            <p>Comprehensive guide for cPanel & Cloud deployment</p>
            <button onclick="window.print()" class="btn btn-light mt-2"><i class="bi bi-printer me-2"></i>Print Manual</button>
            <a href="deployment-guide.php" class="btn btn-outline-light mt-2 ms-2"><i class="bi bi-lightning-charge me-2"></i>Quick Guide</a>
        </div>
    </header>

    <div class="container pb-5">
        <div class="d-none d-print-block text-center mb-4">
            <h1>WAPOS Full Deployment Manual</h1>
            <p>Version 2.0 | <?= date('F Y') ?></p>
            <hr>
        </div>

        <!-- Table of Contents -->
        <div class="toc no-print">
            <h4><i class="bi bi-list-ul me-2"></i>Table of Contents</h4>
            <ul>
                <li><a href="#credentials">Default Credentials</a></li>
                <li><a href="#requirements">System Requirements</a></li>
                <li><a href="#cpanel">cPanel Deployment</a></li>
                <li><a href="#cloud">Cloud Deployment</a></li>
                <li><a href="#database">Database Setup</a></li>
                <li><a href="#config">Configuration</a></li>
                <li><a href="#ssl">SSL/HTTPS Setup</a></li>
                <li><a href="#security">Security Hardening</a></li>
                <li><a href="#backup">Backup & Recovery</a></li>
                <li><a href="#troubleshooting">Troubleshooting</a></li>
                <li><a href="#maintenance">Maintenance</a></li>
            </ul>
        </div>

        <!-- Default Credentials -->
        <section id="credentials">
            <h2 class="section-title"><i class="bi bi-key me-2"></i>Default Credentials</h2>
            <div class="section-content">
                <table class="data-table">
                    <tr><th width="25%">URL</th><td><code>https://yourdomain.com</code></td></tr>
                    <tr><th>Username</th><td><code>superadmin</code></td></tr>
                    <tr><th>Password</th><td><code>Thepurpose@2025</code></td></tr>
                </table>
                <div class="alert-box alert-warning">
                    <strong>⚠️ Security Notice:</strong> Change the default password immediately after first login!
                </div>
            </div>
        </section>

        <!-- System Requirements -->
        <section id="requirements">
            <h2 class="section-title"><i class="bi bi-cpu me-2"></i>System Requirements</h2>
            <div class="section-content">
                <table class="data-table">
                    <thead><tr><th>Component</th><th>Minimum</th><th>Recommended</th></tr></thead>
                    <tbody>
                        <tr><td>PHP</td><td>8.0</td><td>8.1 or 8.2</td></tr>
                        <tr><td>MySQL/MariaDB</td><td>5.7</td><td>8.0 / MariaDB 10.6</td></tr>
                        <tr><td>Memory</td><td>512MB</td><td>1GB+</td></tr>
                        <tr><td>Storage</td><td>1GB</td><td>5GB+</td></tr>
                        <tr><td>SSL Certificate</td><td colspan="2">Required for production</td></tr>
                    </tbody>
                </table>

                <h4>Required PHP Extensions</h4>
                <table class="data-table">
                    <tr><td><code>pdo_mysql</code></td><td>Database connectivity</td></tr>
                    <tr><td><code>mbstring</code></td><td>Multi-byte string handling</td></tr>
                    <tr><td><code>json</code></td><td>JSON encoding/decoding</td></tr>
                    <tr><td><code>curl</code></td><td>HTTP requests (SMS, payments)</td></tr>
                    <tr><td><code>openssl</code></td><td>Encryption & SSL</td></tr>
                    <tr><td><code>gd</code></td><td>Image processing</td></tr>
                    <tr><td><code>zip</code></td><td>Backup compression</td></tr>
                    <tr><td><code>fileinfo</code></td><td>File type detection</td></tr>
                </table>
            </div>
        </section>

        <!-- cPanel Deployment -->
        <section id="cpanel" class="page-break">
            <h2 class="section-title"><i class="bi bi-hdd-rack me-2"></i>cPanel Deployment</h2>
            <div class="section-content">
                
                <h3>Step 1: Access cPanel</h3>
                <ol>
                    <li>Log into your hosting account</li>
                    <li>Navigate to cPanel: <code>yourdomain.com/cpanel</code> or <code>yourdomain.com:2083</code></li>
                </ol>

                <h3>Step 2: Create Database</h3>
                <ol>
                    <li>Go to <strong>MySQL® Databases</strong></li>
                    <li>Create database: <code>yourusername_wapos</code></li>
                    <li>Create user: <code>yourusername_waposuser</code></li>
                    <li>Set a strong password (save it!)</li>
                    <li>Add user to database with <strong>ALL PRIVILEGES</strong></li>
                </ol>

                <h3>Step 3: Upload Files</h3>
                <h4>Option A: File Manager</h4>
                <ol>
                    <li>Go to <strong>File Manager</strong> → <code>public_html</code></li>
                    <li>Click <strong>Upload</strong></li>
                    <li>Upload WAPOS zip file</li>
                    <li>Right-click → <strong>Extract</strong></li>
                </ol>

                <h4>Option B: FTP (Recommended)</h4>
                <pre><code>Host: ftp.yourdomain.com
Username: your_ftp_user
Password: your_ftp_password
Port: 21</code></pre>

                <h3>Step 4: Set File Permissions</h3>
                <pre><code># Directories
chmod 755 (all directories)

# Files
chmod 644 (all files)

# Writable directories
chmod 775 storage/
chmod 775 storage/logs/
chmod 775 storage/uploads/

# Config file (secure)
chmod 600 config.production.php</code></pre>

                <h3>Step 5: Configure PHP</h3>
                <ol>
                    <li>Go to <strong>Select PHP Version</strong></li>
                    <li>Select PHP 8.1 or 8.2</li>
                    <li>Enable extensions: pdo_mysql, mbstring, curl, gd, zip, fileinfo</li>
                </ol>

                <h3>Step 6: Import Database</h3>
                <ol>
                    <li>Go to <strong>phpMyAdmin</strong></li>
                    <li>Select your database</li>
                    <li>Click <strong>Import</strong></li>
                    <li>Import files in order:
                        <ul>
                            <li><code>database/schema.sql</code></li>
                            <li><code>database/migrations/001_initial_schema.sql</code></li>
                            <li><code>database/migrations/002_add_restaurant.sql</code></li>
                            <li><code>database/migrations/003_add_registers_tills.sql</code></li>
                            <li><code>database/migrations/004_add_notifications.sql</code></li>
                            <li><code>database/seeds/core_seed.sql</code></li>
                        </ul>
                    </li>
                </ol>

                <h3>Step 7: Create Production Config</h3>
                <p>Create <code>config.production.php</code> in root folder:</p>
                <pre><code>&lt;?php
// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'yourusername_wapos');
define('DB_USER', 'yourusername_waposuser');
define('DB_PASS', 'your_secure_password');

// Application
define('APP_URL', 'https://yourdomain.com');
define('APP_ENV', 'production');
define('APP_DEBUG', false);
define('APP_TIMEZONE', 'Africa/Nairobi');

// Security
define('SECURE_COOKIES', true);
define('SESSION_LIFETIME', 7200);</code></pre>

                <h3>Step 8: Enable SSL</h3>
                <ol>
                    <li>Go to <strong>SSL/TLS Status</strong></li>
                    <li>Click <strong>Run AutoSSL</strong></li>
                    <li>Wait for certificate installation</li>
                </ol>

                <h3>Step 9: Test Installation</h3>
                <ol>
                    <li>Visit <code>https://yourdomain.com</code></li>
                    <li>Login: <code>superadmin</code> / <code>Thepurpose@2025</code></li>
                    <li>Change password immediately</li>
                </ol>
            </div>
        </section>

        <!-- Cloud Deployment -->
        <section id="cloud" class="page-break">
            <h2 class="section-title"><i class="bi bi-cloud me-2"></i>Cloud Deployment (VPS)</h2>
            <div class="section-content">
                <div class="alert-box alert-info">
                    Works with: AWS EC2, DigitalOcean, Linode, Vultr, Hetzner, etc.
                </div>

                <h3>Step 1: Create Server</h3>
                <pre><code>Image: Ubuntu 22.04 LTS
Plan: 1GB RAM minimum ($5-6/month)
Region: Closest to your users
Firewall: Allow ports 22, 80, 443</code></pre>

                <h3>Step 2: Connect via SSH</h3>
                <pre><code>ssh root@your-server-ip
# or
ssh -i your-key.pem ubuntu@your-server-ip</code></pre>

                <h3>Step 3: Install LAMP Stack</h3>
                <pre><code># Update system
sudo apt update && sudo apt upgrade -y

# Install Apache
sudo apt install apache2 -y
sudo systemctl enable apache2

# Install PHP 8.2
sudo apt install software-properties-common -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install php8.2 php8.2-mysql php8.2-mbstring \
    php8.2-curl php8.2-gd php8.2-zip php8.2-xml -y

# Install MySQL
sudo apt install mysql-server -y
sudo mysql_secure_installation

# Enable Apache modules
sudo a2enmod rewrite headers expires deflate
sudo systemctl restart apache2</code></pre>

                <h3>Step 4: Configure MySQL</h3>
                <pre><code>sudo mysql

CREATE DATABASE wapos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'wapos_user'@'localhost' IDENTIFIED BY 'SecurePassword123!';
GRANT ALL PRIVILEGES ON wapos.* TO 'wapos_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;</code></pre>

                <h3>Step 5: Upload WAPOS Files</h3>
                <pre><code># Create directory
sudo mkdir -p /var/www/wapos

# Upload via SCP (from local machine)
scp -r /path/to/wapos/* user@server:/var/www/wapos/

# Set permissions
sudo chown -R www-data:www-data /var/www/wapos
sudo chmod -R 755 /var/www/wapos
sudo chmod -R 775 /var/www/wapos/storage</code></pre>

                <h3>Step 6: Configure Apache Virtual Host</h3>
                <pre><code>sudo nano /etc/apache2/sites-available/wapos.conf</code></pre>
                <pre><code>&lt;VirtualHost *:80&gt;
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/wapos
    
    &lt;Directory /var/www/wapos&gt;
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;
    
    ErrorLog ${APACHE_LOG_DIR}/wapos_error.log
    CustomLog ${APACHE_LOG_DIR}/wapos_access.log combined
&lt;/VirtualHost&gt;</code></pre>
                <pre><code># Enable site
sudo a2ensite wapos.conf
sudo a2dissite 000-default.conf
sudo systemctl reload apache2</code></pre>

                <h3>Step 7: Install SSL (Let's Encrypt)</h3>
                <pre><code>sudo apt install certbot python3-certbot-apache -y
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com

# Auto-renewal test
sudo certbot renew --dry-run</code></pre>

                <h3>Step 8: Configure Firewall</h3>
                <pre><code>sudo ufw enable
sudo ufw allow 22/tcp   # SSH
sudo ufw allow 80/tcp   # HTTP
sudo ufw allow 443/tcp  # HTTPS
sudo ufw status</code></pre>

                <h3>Step 9: Import Database</h3>
                <pre><code>mysql -u wapos_user -p wapos < /var/www/wapos/database/schema.sql
mysql -u wapos_user -p wapos < /var/www/wapos/database/seeds/core_seed.sql</code></pre>

                <h3>Step 10: Create Production Config</h3>
                <pre><code>sudo nano /var/www/wapos/config.production.php</code></pre>
                <p>Add the same config as cPanel Step 7</p>
            </div>
        </section>

        <!-- Security -->
        <section id="security" class="page-break">
            <h2 class="section-title"><i class="bi bi-shield-check me-2"></i>Security Hardening</h2>
            <div class="section-content">
                
                <h3>File Permissions Script</h3>
                <pre><code>#!/bin/bash
WAPOS_DIR="/var/www/wapos"

# Directories: 755
find $WAPOS_DIR -type d -exec chmod 755 {} \;

# Files: 644
find $WAPOS_DIR -type f -exec chmod 644 {} \;

# Writable: 775
chmod -R 775 $WAPOS_DIR/storage

# Config: 600
chmod 600 $WAPOS_DIR/config.production.php

# Ownership
chown -R www-data:www-data $WAPOS_DIR</code></pre>

                <h3>Install Fail2Ban (Cloud)</h3>
                <pre><code>sudo apt install fail2ban -y
sudo systemctl enable fail2ban</code></pre>

                <h3>Security Headers (.htaccess)</h3>
                <pre><code>&lt;IfModule mod_headers.c&gt;
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
&lt;/IfModule&gt;</code></pre>

                <h3>Security Checklist</h3>
                <table class="data-table">
                    <tr><td>☐</td><td>Change default password</td></tr>
                    <tr><td>☐</td><td>Set APP_DEBUG to false</td></tr>
                    <tr><td>☐</td><td>Enable HTTPS/SSL</td></tr>
                    <tr><td>☐</td><td>Secure config file (chmod 600)</td></tr>
                    <tr><td>☐</td><td>Configure firewall</td></tr>
                    <tr><td>☐</td><td>Enable Fail2Ban</td></tr>
                    <tr><td>☐</td><td>Disable directory listing</td></tr>
                </table>
            </div>
        </section>

        <!-- Backup -->
        <section id="backup" class="page-break">
            <h2 class="section-title"><i class="bi bi-hdd me-2"></i>Backup & Recovery</h2>
            <div class="section-content">
                
                <h3>Automated Backup Script</h3>
                <pre><code>#!/bin/bash
# backup-wapos.sh

BACKUP_DIR="/home/backups"
DB_NAME="wapos"
DB_USER="wapos_user"
DB_PASS="YourPassword"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Backup files
tar -czf $BACKUP_DIR/files_$DATE.tar.gz \
    --exclude='storage/logs/*' \
    -C /var/www wapos

# Remove backups older than 30 days
find $BACKUP_DIR -name "*.gz" -mtime +30 -delete

echo "Backup completed: $DATE"</code></pre>

                <h3>Schedule Daily Backup (Cron)</h3>
                <pre><code>crontab -e

# Add this line (runs at 2 AM daily)
0 2 * * * /home/backup-wapos.sh</code></pre>

                <h3>Restore from Backup</h3>
                <pre><code># Restore database
gunzip < /path/to/db_backup.sql.gz | mysql -u wapos_user -p wapos

# Restore files
tar -xzf /path/to/files_backup.tar.gz -C /var/www/</code></pre>
            </div>
        </section>

        <!-- Troubleshooting -->
        <section id="troubleshooting" class="page-break">
            <h2 class="section-title"><i class="bi bi-bug me-2"></i>Troubleshooting</h2>
            <div class="section-content">
                
                <h3>Blank Page / 500 Error</h3>
                <pre><code># Check Apache error log
sudo tail -f /var/log/apache2/error.log

# Check WAPOS logs
tail -f /var/www/wapos/storage/logs/error.log

# Enable debug temporarily
# In config.production.php:
define('APP_DEBUG', true);</code></pre>

                <h3>Database Connection Error</h3>
                <pre><code># Test MySQL connection
mysql -u wapos_user -p -h localhost wapos

# Check MySQL is running
sudo systemctl status mysql

# Verify credentials
cat /var/www/wapos/config.production.php | grep DB_</code></pre>

                <h3>Permission Denied</h3>
                <pre><code># Fix ownership
sudo chown -R www-data:www-data /var/www/wapos

# Fix permissions
sudo chmod -R 755 /var/www/wapos
sudo chmod -R 775 /var/www/wapos/storage</code></pre>

                <h3>SSL Certificate Issues</h3>
                <pre><code># Check certificate
sudo certbot certificates

# Renew certificate
sudo certbot renew

# Test Apache config
sudo apache2ctl -t</code></pre>
            </div>
        </section>

        <!-- Maintenance -->
        <section id="maintenance">
            <h2 class="section-title"><i class="bi bi-tools me-2"></i>Maintenance</h2>
            <div class="section-content">
                <table class="data-table">
                    <thead><tr><th>Task</th><th>Frequency</th><th>Command/Action</th></tr></thead>
                    <tbody>
                        <tr><td>System updates</td><td>Weekly</td><td><code>sudo apt update && sudo apt upgrade</code></td></tr>
                        <tr><td>Check disk space</td><td>Weekly</td><td><code>df -h</code></td></tr>
                        <tr><td>Review logs</td><td>Weekly</td><td>Check storage/logs/</td></tr>
                        <tr><td>Backup verification</td><td>Weekly</td><td>Test restore</td></tr>
                        <tr><td>SSL renewal</td><td>Auto</td><td>Certbot handles</td></tr>
                        <tr><td>Database optimization</td><td>Monthly</td><td>OPTIMIZE TABLE</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Quick Reference -->
        <section class="page-break">
            <h2 class="section-title"><i class="bi bi-bookmark me-2"></i>Quick Reference</h2>
            <div class="section-content">
                <h4>Important Paths</h4>
                <table class="data-table">
                    <thead><tr><th>Item</th><th>cPanel</th><th>Cloud/VPS</th></tr></thead>
                    <tbody>
                        <tr><td>Web Root</td><td><code>public_html/wapos</code></td><td><code>/var/www/wapos</code></td></tr>
                        <tr><td>Config</td><td><code>config.production.php</code></td><td><code>/var/www/wapos/config.production.php</code></td></tr>
                        <tr><td>Logs</td><td><code>storage/logs</code></td><td><code>/var/www/wapos/storage/logs</code></td></tr>
                        <tr><td>Apache Logs</td><td>Via cPanel</td><td><code>/var/log/apache2/</code></td></tr>
                    </tbody>
                </table>

                <h4>Useful Commands (Cloud)</h4>
                <table class="data-table">
                    <tr><td>Restart Apache</td><td><code>sudo systemctl restart apache2</code></td></tr>
                    <tr><td>Restart MySQL</td><td><code>sudo systemctl restart mysql</code></td></tr>
                    <tr><td>View Apache errors</td><td><code>sudo tail -f /var/log/apache2/error.log</code></td></tr>
                    <tr><td>Check disk space</td><td><code>df -h</code></td></tr>
                    <tr><td>Check memory</td><td><code>free -m</code></td></tr>
                </table>

                <div class="alert-box alert-success mt-4">
                    <strong>✅ Deployment Complete!</strong><br>
                    Your WAPOS system should now be live at your domain.
                </div>
            </div>
        </section>

        <div class="text-center text-muted mt-4 pt-4 border-top">
            <p>WAPOS Full Deployment Manual v2.0 | Generated <?= date('F j, Y') ?></p>
        </div>
    </div>
</body>
</html>
