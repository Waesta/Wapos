# WAPOS Deployment Manual

## Complete Guide for cPanel & Cloud Deployment

**Version:** 2.0  
**Last Updated:** December 2025

---

## Table of Contents

1. [Pre-Deployment Checklist](#1-pre-deployment-checklist)
2. [cPanel Deployment](#2-cpanel-deployment)
3. [Cloud Deployment (AWS/DigitalOcean/Linode)](#3-cloud-deployment)
4. [Database Setup](#4-database-setup)
5. [Configuration](#5-configuration)
6. [SSL/HTTPS Setup](#6-sslhttps-setup)
7. [Domain & DNS Configuration](#7-domain--dns-configuration)
8. [Performance Optimization](#8-performance-optimization)
9. [Security Hardening](#9-security-hardening)
10. [Backup & Recovery](#10-backup--recovery)
11. [Troubleshooting](#11-troubleshooting)
12. [Maintenance](#12-maintenance)

---

## 1. Pre-Deployment Checklist

### System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| PHP | 8.0 | 8.1 or 8.2 |
| MySQL/MariaDB | 5.7 | 8.0 / MariaDB 10.6 |
| Memory | 512MB | 1GB+ |
| Storage | 1GB | 5GB+ |
| SSL Certificate | Required | Required |

### Required PHP Extensions

```
✓ pdo_mysql
✓ mbstring
✓ json
✓ curl
✓ openssl
✓ gd (for image processing)
✓ zip (for backups)
✓ fileinfo
```

### Files to Prepare

```
wapos/
├── api/                 # API endpoints
├── assets/              # CSS, JS, images
├── classes/             # PHP classes
├── config/              # Configuration files
├── dashboards/          # Role-based dashboards
├── database/            # Migrations & seeds
├── includes/            # Core includes
├── storage/             # Logs & uploads (writable)
├── config.php           # Main configuration
├── config.production.php # Production config (create this)
├── .htaccess            # Apache configuration
└── index.php            # Entry point
```

---

## 2. cPanel Deployment

### Step 1: Access cPanel

1. Log into your hosting account
2. Navigate to cPanel (usually `yourdomain.com/cpanel` or `yourdomain.com:2083`)
3. Enter your username and password

### Step 2: Create Database

1. Go to **MySQL® Databases**
2. Create a new database:
   ```
   Database name: yourusername_wapos
   ```
3. Create a database user:
   ```
   Username: yourusername_waposuser
   Password: [strong password - save this!]
   ```
4. Add user to database with **ALL PRIVILEGES**

### Step 3: Upload Files

#### Option A: File Manager (Small files)

1. Go to **File Manager**
2. Navigate to `public_html` (or subdomain folder)
3. Click **Upload**
4. Upload the WAPOS zip file
5. Extract the zip file
6. Move contents to desired location

#### Option B: FTP (Recommended for large files)

1. Go to **FTP Accounts** in cPanel
2. Create FTP account or use main account
3. Connect using FileZilla or similar:
   ```
   Host: ftp.yourdomain.com
   Username: your_ftp_user
   Password: your_ftp_password
   Port: 21
   ```
4. Upload all WAPOS files to `public_html/` or subdirectory

#### Option C: Git (Advanced)

1. Go to **Terminal** in cPanel (if available)
2. Navigate to public_html:
   ```bash
   cd ~/public_html
   ```
3. Clone repository:
   ```bash
   git clone https://github.com/your-repo/wapos.git .
   ```

### Step 4: Set File Permissions

In File Manager or via SSH:

```bash
# Directories: 755
find /home/username/public_html/wapos -type d -exec chmod 755 {} \;

# Files: 644
find /home/username/public_html/wapos -type f -exec chmod 644 {} \;

# Writable directories: 775
chmod -R 775 /home/username/public_html/wapos/storage
chmod -R 775 /home/username/public_html/wapos/storage/logs
chmod -R 775 /home/username/public_html/wapos/storage/uploads
chmod -R 775 /home/username/public_html/wapos/storage/backups

# Config file: 600 (secure)
chmod 600 /home/username/public_html/wapos/config.production.php
```

### Step 5: Configure PHP Version

1. Go to **Select PHP Version** or **MultiPHP Manager**
2. Select PHP 8.1 or 8.2
3. Enable required extensions:
   - pdo_mysql
   - mbstring
   - curl
   - gd
   - zip
   - fileinfo
   - openssl

### Step 6: Import Database

1. Go to **phpMyAdmin**
2. Select your database
3. Click **Import**
4. Upload `database/schema.sql`
5. Click **Go**
6. Then import `database/migrations/*.sql` files in order
7. Finally import `database/seeds/core_seed.sql`

### Step 7: Create Production Config

Create `config.production.php`:

```php
<?php
/**
 * WAPOS Production Configuration
 * This file overrides config.php settings for production
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'yourusername_wapos');
define('DB_USER', 'yourusername_waposuser');
define('DB_PASS', 'your_secure_password');

// Application URL (no trailing slash)
define('APP_URL', 'https://yourdomain.com');
// Or for subdirectory: define('APP_URL', 'https://yourdomain.com/wapos');

// Environment
define('APP_ENV', 'production');
define('APP_DEBUG', false);

// Security
define('SECURE_COOKIES', true);
define('SESSION_LIFETIME', 7200); // 2 hours

// Email Configuration (SMTP)
define('MAIL_HOST', 'mail.yourdomain.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'noreply@yourdomain.com');
define('MAIL_PASSWORD', 'your_email_password');
define('MAIL_FROM_ADDRESS', 'noreply@yourdomain.com');
define('MAIL_FROM_NAME', 'WAPOS System');
define('MAIL_ENCRYPTION', 'tls');

// Timezone
define('APP_TIMEZONE', 'Africa/Nairobi'); // Change to your timezone
```

### Step 8: Update .htaccess

Ensure `.htaccess` exists in root:

```apache
# WAPOS Production .htaccess

# Enable Rewrite Engine
RewriteEngine On

# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Remove www (optional - choose one)
# RewriteCond %{HTTP_HOST} ^www\.(.*)$ [NC]
# RewriteRule ^(.*)$ https://%1/$1 [R=301,L]

# Protect sensitive files
<FilesMatch "^(config\.php|config\.production\.php|\.env|composer\.json|composer\.lock)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Protect directories
<IfModule mod_autoindex.c>
    Options -Indexes
</IfModule>

# PHP Settings
<IfModule mod_php.c>
    php_value upload_max_filesize 64M
    php_value post_max_size 64M
    php_value max_execution_time 300
    php_value memory_limit 256M
</IfModule>

# Cache static assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType application/x-javascript "access plus 1 month"
</IfModule>

# Gzip Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css
    AddOutputFilterByType DEFLATE application/javascript application/x-javascript
    AddOutputFilterByType DEFLATE application/json
</IfModule>

# Security Headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```

### Step 9: Test Installation

1. Visit `https://yourdomain.com`
2. You should see the WAPOS login page
3. Default login:
   ```
   Username: superadmin
   Password: admin123
   ```
4. **IMMEDIATELY change the password!**

---

## 3. Cloud Deployment

### AWS (Amazon Web Services)

#### Option A: EC2 Instance

1. **Launch EC2 Instance**
   ```
   AMI: Ubuntu 22.04 LTS
   Instance Type: t3.small (minimum) or t3.medium
   Storage: 20GB SSD
   Security Group: Allow HTTP (80), HTTPS (443), SSH (22)
   ```

2. **Connect via SSH**
   ```bash
   ssh -i your-key.pem ubuntu@your-ec2-ip
   ```

3. **Install LAMP Stack**
   ```bash
   # Update system
   sudo apt update && sudo apt upgrade -y
   
   # Install Apache
   sudo apt install apache2 -y
   sudo systemctl enable apache2
   
   # Install PHP 8.2
   sudo apt install software-properties-common -y
   sudo add-apt-repository ppa:ondrej/php -y
   sudo apt update
   sudo apt install php8.2 php8.2-mysql php8.2-mbstring php8.2-curl php8.2-gd php8.2-zip php8.2-xml -y
   
   # Install MySQL
   sudo apt install mysql-server -y
   sudo mysql_secure_installation
   
   # Enable Apache modules
   sudo a2enmod rewrite headers expires deflate
   sudo systemctl restart apache2
   ```

4. **Configure MySQL**
   ```bash
   sudo mysql
   ```
   ```sql
   CREATE DATABASE wapos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'wapos_user'@'localhost' IDENTIFIED BY 'SecurePassword123!';
   GRANT ALL PRIVILEGES ON wapos.* TO 'wapos_user'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```

5. **Upload WAPOS Files**
   ```bash
   # Create directory
   sudo mkdir -p /var/www/wapos
   
   # Upload via SCP from your local machine
   scp -i your-key.pem -r /path/to/wapos/* ubuntu@your-ec2-ip:/tmp/wapos/
   
   # Move files
   sudo mv /tmp/wapos/* /var/www/wapos/
   
   # Set permissions
   sudo chown -R www-data:www-data /var/www/wapos
   sudo chmod -R 755 /var/www/wapos
   sudo chmod -R 775 /var/www/wapos/storage
   ```

6. **Configure Apache Virtual Host**
   ```bash
   sudo nano /etc/apache2/sites-available/wapos.conf
   ```
   ```apache
   <VirtualHost *:80>
       ServerName yourdomain.com
       ServerAlias www.yourdomain.com
       DocumentRoot /var/www/wapos
       
       <Directory /var/www/wapos>
           AllowOverride All
           Require all granted
       </Directory>
       
       ErrorLog ${APACHE_LOG_DIR}/wapos_error.log
       CustomLog ${APACHE_LOG_DIR}/wapos_access.log combined
   </VirtualHost>
   ```
   ```bash
   sudo a2ensite wapos.conf
   sudo a2dissite 000-default.conf
   sudo systemctl reload apache2
   ```

#### Option B: AWS Lightsail (Easier)

1. Go to AWS Lightsail Console
2. Create Instance → Linux → LAMP (PHP 8)
3. Choose $5/month plan (1GB RAM)
4. Connect via browser SSH
5. Follow steps 4-6 above

### DigitalOcean

1. **Create Droplet**
   ```
   Image: LAMP on Ubuntu 22.04
   Plan: Basic $6/month (1GB RAM)
   Region: Choose closest to users
   ```

2. **Connect via SSH**
   ```bash
   ssh root@your-droplet-ip
   ```

3. **Follow AWS EC2 steps 3-6** (same process)

### Linode

1. **Create Linode**
   ```
   Image: Ubuntu 22.04 LTS
   Plan: Shared CPU 1GB ($5/month)
   ```

2. **Use StackScript** or follow manual setup (same as AWS EC2)

---

## 4. Database Setup

### Import Schema

```bash
# Via command line
mysql -u wapos_user -p wapos < /var/www/wapos/database/schema.sql

# Import migrations in order
mysql -u wapos_user -p wapos < /var/www/wapos/database/migrations/001_initial_schema.sql
mysql -u wapos_user -p wapos < /var/www/wapos/database/migrations/002_add_restaurant.sql
mysql -u wapos_user -p wapos < /var/www/wapos/database/migrations/003_add_registers_tills.sql
mysql -u wapos_user -p wapos < /var/www/wapos/database/migrations/004_add_notifications.sql

# Import seed data
mysql -u wapos_user -p wapos < /var/www/wapos/database/seeds/core_seed.sql
```

### Database Optimization

```sql
-- Add to MySQL config (/etc/mysql/mysql.conf.d/mysqld.cnf)
[mysqld]
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
innodb_flush_log_at_trx_commit = 2
query_cache_type = 1
query_cache_size = 32M
max_connections = 100
```

---

## 5. Configuration

### Production Config Template

Create `/var/www/wapos/config.production.php`:

```php
<?php
/**
 * WAPOS Production Configuration
 */

// ===========================================
// DATABASE
// ===========================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'wapos');
define('DB_USER', 'wapos_user');
define('DB_PASS', 'YourSecurePassword123!');
define('DB_CHARSET', 'utf8mb4');

// ===========================================
// APPLICATION
// ===========================================
define('APP_URL', 'https://pos.yourdomain.com');
define('APP_NAME', 'WAPOS');
define('APP_ENV', 'production');
define('APP_DEBUG', false);
define('APP_TIMEZONE', 'Africa/Nairobi');

// ===========================================
// SECURITY
// ===========================================
define('SECURE_COOKIES', true);
define('SESSION_LIFETIME', 7200);
define('CSRF_TOKEN_LIFETIME', 3600);
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

// ===========================================
// EMAIL (SMTP)
// ===========================================
define('MAIL_ENABLED', true);
define('MAIL_HOST', 'smtp.gmail.com'); // Or your SMTP server
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your-email@gmail.com');
define('MAIL_PASSWORD', 'your-app-password');
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_ADDRESS', 'noreply@yourdomain.com');
define('MAIL_FROM_NAME', 'WAPOS');

// ===========================================
// SMS (Optional)
// ===========================================
define('SMS_ENABLED', false);
define('SMS_PROVIDER', 'africastalking'); // or 'twilio', 'vonage'
define('SMS_API_KEY', '');
define('SMS_USERNAME', '');
define('SMS_SENDER_ID', 'WAPOS');

// ===========================================
// WHATSAPP (Optional)
// ===========================================
define('WHATSAPP_ENABLED', false);
define('WHATSAPP_PROVIDER', 'meta'); // or 'aisensy'
define('WHATSAPP_ACCESS_TOKEN', '');
define('WHATSAPP_PHONE_NUMBER_ID', '');

// ===========================================
// FILE UPLOADS
// ===========================================
define('UPLOAD_MAX_SIZE', 10485760); // 10MB
define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx');

// ===========================================
// LOGGING
// ===========================================
define('LOG_LEVEL', 'error'); // debug, info, warning, error
define('LOG_PATH', __DIR__ . '/storage/logs');

// ===========================================
// CACHE
// ===========================================
define('CACHE_ENABLED', true);
define('CACHE_DRIVER', 'file'); // file, redis, memcached
define('CACHE_PATH', __DIR__ . '/storage/cache');
define('CACHE_TTL', 3600);
```

### Environment-Specific Loading

Update `config.php` to load production config:

```php
<?php
// At the end of config.php, add:

// Load production config if exists
if (file_exists(__DIR__ . '/config.production.php')) {
    require_once __DIR__ . '/config.production.php';
}
```

---

## 6. SSL/HTTPS Setup

### cPanel (AutoSSL)

1. Go to **SSL/TLS Status**
2. Click **Run AutoSSL**
3. Wait for certificate installation
4. Verify HTTPS works

### Let's Encrypt (Cloud/VPS)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache -y

# Get certificate
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com

# Auto-renewal (already configured, but verify)
sudo certbot renew --dry-run

# Add to crontab for auto-renewal
sudo crontab -e
# Add: 0 0 1 * * /usr/bin/certbot renew --quiet
```

### Force HTTPS in Apache

```apache
# In /etc/apache2/sites-available/wapos.conf
<VirtualHost *:80>
    ServerName yourdomain.com
    Redirect permanent / https://yourdomain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot /var/www/wapos
    
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.com/privkey.pem
    
    <Directory /var/www/wapos>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

---

## 7. Domain & DNS Configuration

### DNS Records

| Type | Name | Value | TTL |
|------|------|-------|-----|
| A | @ | Your-Server-IP | 3600 |
| A | www | Your-Server-IP | 3600 |
| CNAME | pos | yourdomain.com | 3600 |
| MX | @ | mail.yourdomain.com | 3600 |
| TXT | @ | v=spf1 include:_spf.google.com ~all | 3600 |

### Subdomain Setup (pos.yourdomain.com)

**cPanel:**
1. Go to **Subdomains**
2. Create subdomain: `pos`
3. Document root: `public_html/wapos`

**Cloud/VPS:**
```bash
# Create virtual host for subdomain
sudo nano /etc/apache2/sites-available/pos.yourdomain.com.conf
```

---

## 8. Performance Optimization

### PHP OpCache

```ini
# /etc/php/8.2/apache2/conf.d/10-opcache.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

### MySQL Tuning

```ini
# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
innodb_buffer_pool_size = 512M
innodb_log_file_size = 128M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
query_cache_type = 1
query_cache_size = 64M
tmp_table_size = 64M
max_heap_table_size = 64M
```

### Apache MPM

```bash
# Use event MPM for better performance
sudo a2dismod mpm_prefork
sudo a2enmod mpm_event
sudo systemctl restart apache2
```

### Enable Gzip Compression

Already included in `.htaccess`, but verify:

```bash
# Test compression
curl -H "Accept-Encoding: gzip" -I https://yourdomain.com
# Should see: Content-Encoding: gzip
```

### CDN (Optional)

Consider using Cloudflare:
1. Sign up at cloudflare.com
2. Add your domain
3. Update nameservers at your registrar
4. Enable caching and optimization

---

## 9. Security Hardening

### File Permissions

```bash
# Secure permissions script
#!/bin/bash
WAPOS_DIR="/var/www/wapos"

# Directories: 755
find $WAPOS_DIR -type d -exec chmod 755 {} \;

# Files: 644
find $WAPOS_DIR -type f -exec chmod 644 {} \;

# Writable directories: 775
chmod -R 775 $WAPOS_DIR/storage

# Config files: 600
chmod 600 $WAPOS_DIR/config.php
chmod 600 $WAPOS_DIR/config.production.php

# Set ownership
chown -R www-data:www-data $WAPOS_DIR
```

### Firewall (UFW)

```bash
# Enable firewall
sudo ufw enable

# Allow necessary ports
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS

# Deny everything else
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Check status
sudo ufw status
```

### Fail2Ban

```bash
# Install
sudo apt install fail2ban -y

# Configure
sudo nano /etc/fail2ban/jail.local
```

```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true

[apache-auth]
enabled = true

[apache-badbots]
enabled = true
```

### Security Headers

Add to Apache config:

```apache
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net;"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
</IfModule>
```

---

## 10. Backup & Recovery

### Automated Backup Script

Create `/home/username/backup-wapos.sh`:

```bash
#!/bin/bash

# Configuration
BACKUP_DIR="/home/username/backups"
WAPOS_DIR="/var/www/wapos"
DB_NAME="wapos"
DB_USER="wapos_user"
DB_PASS="YourPassword"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Backup files (excluding logs and cache)
tar -czf $BACKUP_DIR/files_$DATE.tar.gz \
    --exclude='storage/logs/*' \
    --exclude='storage/cache/*' \
    -C $(dirname $WAPOS_DIR) $(basename $WAPOS_DIR)

# Remove old backups
find $BACKUP_DIR -name "*.gz" -mtime +$RETENTION_DAYS -delete

# Log
echo "Backup completed: $DATE" >> $BACKUP_DIR/backup.log
```

```bash
# Make executable
chmod +x /home/username/backup-wapos.sh

# Add to crontab (daily at 2 AM)
crontab -e
# Add: 0 2 * * * /home/username/backup-wapos.sh
```

### Restore from Backup

```bash
# Restore database
gunzip < /path/to/db_backup.sql.gz | mysql -u wapos_user -p wapos

# Restore files
tar -xzf /path/to/files_backup.tar.gz -C /var/www/

# Fix permissions
chown -R www-data:www-data /var/www/wapos
```

### Off-site Backup (Recommended)

```bash
# Sync to remote server
rsync -avz /home/username/backups/ user@backup-server:/backups/wapos/

# Or use AWS S3
aws s3 sync /home/username/backups/ s3://your-bucket/wapos-backups/
```

---

## 11. Troubleshooting

### Common Issues

#### 1. Blank Page / 500 Error

```bash
# Check Apache error log
sudo tail -f /var/log/apache2/error.log

# Check PHP errors
sudo tail -f /var/www/wapos/storage/logs/error.log

# Enable debug temporarily
# In config.production.php:
define('APP_DEBUG', true);
```

#### 2. Database Connection Error

```bash
# Test MySQL connection
mysql -u wapos_user -p -h localhost wapos

# Check MySQL is running
sudo systemctl status mysql

# Check credentials in config
cat /var/www/wapos/config.production.php | grep DB_
```

#### 3. Permission Denied

```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/wapos

# Fix permissions
sudo chmod -R 755 /var/www/wapos
sudo chmod -R 775 /var/www/wapos/storage
```

#### 4. Session Issues

```bash
# Check PHP session path
php -i | grep session.save_path

# Ensure directory is writable
sudo chmod 777 /var/lib/php/sessions
```

#### 5. SSL Certificate Issues

```bash
# Check certificate status
sudo certbot certificates

# Renew certificate
sudo certbot renew

# Check Apache SSL config
sudo apache2ctl -t
```

#### 6. Slow Performance

```bash
# Check server load
top
htop

# Check MySQL slow queries
sudo tail -f /var/log/mysql/slow.log

# Check PHP-FPM status (if using)
sudo systemctl status php8.2-fpm
```

### Debug Mode

Temporarily enable debug mode:

```php
// In config.production.php
define('APP_DEBUG', true);
define('LOG_LEVEL', 'debug');
```

**Remember to disable after debugging!**

---

## 12. Maintenance

### Regular Tasks

| Task | Frequency | Command/Action |
|------|-----------|----------------|
| Update PHP | Monthly | `sudo apt update && sudo apt upgrade php*` |
| Update MySQL | Monthly | `sudo apt update && sudo apt upgrade mysql*` |
| Check disk space | Weekly | `df -h` |
| Review logs | Weekly | Check `/var/www/wapos/storage/logs/` |
| Backup verification | Weekly | Test restore from backup |
| SSL renewal | Auto | Certbot handles this |
| Security updates | Weekly | `sudo apt update && sudo apt upgrade` |

### Update WAPOS

```bash
# Backup first!
/home/username/backup-wapos.sh

# Pull latest code (if using Git)
cd /var/www/wapos
git pull origin main

# Or upload new files via FTP/SCP

# Run migrations
mysql -u wapos_user -p wapos < database/migrations/latest.sql

# Clear cache
rm -rf storage/cache/*

# Fix permissions
sudo chown -R www-data:www-data /var/www/wapos
```

### Monitor Uptime

Use free monitoring services:
- UptimeRobot (uptimerobot.com)
- Freshping (freshping.io)
- StatusCake (statuscake.com)

---

## Quick Reference

### Important Paths

| Item | cPanel | Cloud/VPS |
|------|--------|-----------|
| Web Root | `/home/user/public_html/wapos` | `/var/www/wapos` |
| Config | `public_html/wapos/config.production.php` | `/var/www/wapos/config.production.php` |
| Logs | `public_html/wapos/storage/logs` | `/var/www/wapos/storage/logs` |
| Apache Logs | Via cPanel | `/var/log/apache2/` |
| MySQL | Via phpMyAdmin | `mysql -u user -p` |

### Default Credentials

```
URL: https://yourdomain.com
Username: superadmin
Password: admin123

⚠️ CHANGE IMMEDIATELY AFTER FIRST LOGIN!
```

### Support Contacts

- Technical Issues: [your-support-email]
- Documentation: [your-docs-url]
- GitHub Issues: [your-github-repo]

---

## Checklist

### Pre-Launch

- [ ] Database imported and tested
- [ ] Config file created with correct credentials
- [ ] SSL certificate installed
- [ ] File permissions set correctly
- [ ] Default password changed
- [ ] Email/SMS settings configured (if needed)
- [ ] Backup system configured
- [ ] Firewall configured (cloud only)
- [ ] Debug mode disabled
- [ ] Error logging enabled

### Post-Launch

- [ ] Test all major features
- [ ] Test offline functionality
- [ ] Verify email sending
- [ ] Test receipt printing
- [ ] Create additional user accounts
- [ ] Set up monitoring
- [ ] Document any customizations

---

**Deployment Complete! 🎉**

Your WAPOS system should now be live and accessible at your domain.
