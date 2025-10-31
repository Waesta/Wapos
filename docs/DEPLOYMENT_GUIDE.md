# WAPOS Deployment Guide for Shared Hosting

## Overview

This guide covers deploying WAPOS to shared hosting environments (cPanel, Plesk, etc.) with zero-downtime deployment and rollback capabilities.

---

## Pre-Deployment Checklist

- [ ] Backup current database
- [ ] Test in staging environment
- [ ] Review migration scripts
- [ ] Verify PHP version (7.4+)
- [ ] Check disk space (>1GB free)
- [ ] Notify users of maintenance window
- [ ] Prepare rollback plan

---

## Deployment Methods

### Method 1: Automated Deployment (Recommended)

**Via SSH:**

```bash
# Connect to server
ssh username@yourserver.com

# Navigate to project
cd public_html/wapos

# Pull latest code (if using Git)
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader

# Run deployment
php deploy.php 2.0.1
```

**What it does:**
1. ✅ Creates database backup
2. ✅ Runs migrations
3. ✅ Clears caches
4. ✅ Updates version file
5. ✅ Logs deployment

### Method 2: Manual Deployment (cPanel File Manager)

**Step 1: Backup**

1. Go to cPanel → phpMyAdmin
2. Select `wapos` database
3. Click **Export** → **Go**
4. Save SQL file locally

**Step 2: Upload Files**

1. Go to cPanel → File Manager
2. Navigate to `public_html/wapos`
3. Upload new files (overwrite existing)
4. Extract if uploaded as ZIP

**Step 3: Run Migrations**

1. Go to cPanel → phpMyAdmin
2. Select `wapos` database
3. Click **SQL** tab
4. Paste migration SQL:
   ```sql
   SOURCE /home/username/public_html/wapos/database/migrations/001_add_uniqueness_constraints.sql
   ```
5. Click **Go**

**Step 4: Clear Caches**

1. Delete all files in `cache/` directory
2. Delete all files in `cache/ratelimit/` directory

**Step 5: Verify**

1. Visit your site
2. Login as admin
3. Check System Health
4. Test a sale

---

## Zero-Downtime Deployment

### Using Symlinks (Advanced)

**Directory Structure:**

```
/home/username/public_html/
├── wapos/                  # Symlink to current release
├── releases/
│   ├── 2025-10-31-v2.0.0/
│   ├── 2025-10-31-v2.0.1/
│   └── 2025-10-31-v2.0.2/  # New release
├── shared/
│   ├── uploads/
│   ├── logs/
│   └── cache/
└── backups/
```

**Deployment Steps:**

```bash
# 1. Upload new release
mkdir -p releases/2025-10-31-v2.0.2
cd releases/2025-10-31-v2.0.2
# Upload files here

# 2. Link shared directories
ln -s ../../shared/uploads uploads
ln -s ../../shared/logs logs
ln -s ../../shared/cache cache

# 3. Install dependencies
composer install --no-dev

# 4. Run migrations
php deploy.php migrate

# 5. Switch symlink (atomic operation)
ln -sfn releases/2025-10-31-v2.0.2 wapos

# 6. Reload PHP-FPM (if available)
killall -USR2 php-fpm
```

**Rollback:**

```bash
# Switch back to previous release
ln -sfn releases/2025-10-31-v2.0.1 wapos
```

---

## Migration Execution

### Safe Migration Practices

1. **Always backup first**
   ```bash
   mysqldump -u username -p wapos > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Test in staging**
   - Run migration on copy of production database
   - Verify no errors
   - Check data integrity

3. **Run during low-traffic period**
   - Early morning (2-4 AM)
   - Notify users in advance

4. **Monitor execution**
   - Watch for errors
   - Check execution time
   - Verify completion

### Handling Migration Failures

**If migration fails:**

1. **Don't panic** - database is backed up
2. **Check error message**
3. **Common issues:**
   - Duplicate data → Clean before retry
   - Missing table → Check prerequisites
   - Timeout → Increase `max_execution_time`

4. **Rollback database:**
   ```bash
   mysql -u username -p wapos < backup_20251031_020000.sql
   ```

5. **Fix issue and retry**

---

## Environment-Specific Configuration

### Development

```php
// config.php
define('APP_ENV', 'development');
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Staging

```php
// config.php
define('APP_ENV', 'staging');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
```

### Production

```php
// config.php
define('APP_ENV', 'production');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
```

---

## Post-Deployment Verification

### Automated Checks

```bash
# Run health check
curl https://yoursite.com/wapos/admin/system-health-enhanced.php?format=json
```

### Manual Checks

- [ ] Login as admin
- [ ] Login as cashier
- [ ] Create test sale
- [ ] View reports
- [ ] Check inventory
- [ ] Test offline mode
- [ ] Verify WhatsApp (if enabled)
- [ ] Check accounting journals

### Smoke Test Script

```bash
#!/bin/bash
# smoke-test.sh

echo "Running WAPOS smoke tests..."

# Test database
php -r "require 'config.php'; new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);" && echo "✓ Database OK" || echo "✗ Database FAILED"

# Test file permissions
[ -w logs ] && echo "✓ Logs writable" || echo "✗ Logs not writable"
[ -w cache ] && echo "✓ Cache writable" || echo "✗ Cache not writable"

# Test PHP version
php -r "echo version_compare(PHP_VERSION, '7.4.0', '>=') ? '✓ PHP version OK' : '✗ PHP version too old';" && echo

# Test Composer autoload
php -r "require 'vendor/autoload.php'; echo '✓ Composer autoload OK';" && echo || echo "✗ Composer autoload FAILED"

echo "Smoke tests complete!"
```

---

## Rollback Procedures

### Automatic Rollback

```bash
php deploy.php rollback
```

This will:
1. Restore latest database backup
2. Revert code to previous version
3. Clear caches

### Manual Rollback

**Step 1: Stop Operations**
- Enable maintenance mode
- Notify users

**Step 2: Restore Database**

```bash
mysql -u username -p wapos < backups/db_2025-10-31_01-00-00.sql
```

**Step 3: Revert Code**

If using Git:
```bash
git checkout v2.0.0
composer install
```

If using releases:
```bash
ln -sfn releases/2025-10-31-v2.0.0 wapos
```

**Step 4: Verify**
- Test critical functions
- Check data integrity

**Step 5: Resume Operations**
- Disable maintenance mode
- Notify users

---

## Monitoring Post-Deployment

### First 24 Hours

Monitor:
- Error logs (`logs/app.log`)
- System health dashboard
- Database performance
- User feedback

### Week 1

- Review error trends
- Check performance metrics
- Gather user feedback
- Plan hotfixes if needed

---

## Troubleshooting Deployment Issues

### Issue: "Composer not found"

**Solution:**
```bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
php composer.phar install
```

### Issue: "Permission denied"

**Solution:**
```bash
chmod 755 deploy.php
chmod -R 755 logs cache
```

### Issue: "Migration timeout"

**Solution:**
```php
// Increase timeout in migration script
ini_set('max_execution_time', 300);
```

### Issue: "White screen after deployment"

**Solution:**
1. Check PHP error log
2. Verify file permissions
3. Check .htaccess syntax
4. Clear browser cache

---

## Maintenance Mode

### Enable Maintenance Mode

Create `maintenance.php` in root:

```php
<?php
http_response_code(503);
header('Retry-After: 3600');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Maintenance</title>
</head>
<body>
    <h1>System Maintenance</h1>
    <p>We're performing scheduled maintenance. We'll be back soon!</p>
    <p>Expected completion: <?= date('H:i', strtotime('+1 hour')) ?></p>
</body>
</html>
```

Add to `.htaccess`:

```apache
# Maintenance mode
RewriteCond %{REQUEST_URI} !^/maintenance\.php$
RewriteCond %{REMOTE_ADDR} !^123\.456\.789\.0$ # Your IP
RewriteRule ^(.*)$ /maintenance.php [R=503,L]
```

### Disable Maintenance Mode

Remove maintenance rules from `.htaccess`.

---

## Automated Deployment (CI/CD)

### GitHub Actions Example

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    tags:
      - 'v*'

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      
      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader
      
      - name: Deploy to server
        uses: easingthemes/ssh-deploy@main
        env:
          SSH_PRIVATE_KEY: ${{ secrets.SSH_PRIVATE_KEY }}
          REMOTE_HOST: ${{ secrets.REMOTE_HOST }}
          REMOTE_USER: ${{ secrets.REMOTE_USER }}
          TARGET: /home/username/public_html/wapos
      
      - name: Run migrations
        run: ssh ${{ secrets.REMOTE_USER }}@${{ secrets.REMOTE_HOST }} "cd /home/username/public_html/wapos && php deploy.php migrate"
```

---

## Best Practices

1. **Always backup before deployment**
2. **Test in staging first**
3. **Deploy during low-traffic periods**
4. **Have rollback plan ready**
5. **Monitor closely after deployment**
6. **Keep last 3 releases**
7. **Document all changes**
8. **Communicate with users**

---

**Version:** 2.0  
**Last Updated:** October 31, 2025
