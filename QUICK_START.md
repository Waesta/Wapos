# WAPOS 2.0 - Quick Start Guide

## 🚀 Getting Started in 5 Minutes

### Step 1: Install Dependencies

```bash
cd c:\xampp\htdocs\wapos
composer install
```

### Step 2: Run Database Migration

**Option A: Automated (Recommended)**
```bash
php deploy.php 2.0.0
```

**Option B: Manual (phpMyAdmin)**
1. Open phpMyAdmin
2. Select `wapos` database
3. Click **Import**
4. Choose `database/migrations/001_add_uniqueness_constraints.sql`
5. Click **Go**

### Step 3: Verify Installation

Visit: `http://localhost/wapos/admin/system-health-enhanced.php`

All checks should show ✓ (green).

### Step 4: Login

**URL:** `http://localhost/wapos/login.php`

**Default Credentials:**
- Username: `admin`
- Password: `admin123`

**⚠️ Change password immediately after first login!**

---

## 📋 What's New in 2.0

### Architecture
- ✅ PSR-4 autoloading with Composer
- ✅ Layered architecture (Controllers/Services/Repositories)
- ✅ Domain-driven design with value objects

### Security
- ✅ CSRF protection on all forms
- ✅ Rate limiting (5 login attempts per 15 min)
- ✅ Enhanced security headers (CSP, XFO, HSTS-ready)
- ✅ Audit logging for sensitive actions
- ✅ Argon2ID password hashing

### Offline-First PWA
- ✅ Enhanced Service Worker with IndexedDB
- ✅ Outbox queue for offline sales
- ✅ Auto-sync when online
- ✅ Background Sync API support

### Idempotent APIs
- ✅ Duplicate prevention with `external_id`
- ✅ Safe retries (no duplicate sales)
- ✅ 201 Created vs 200 OK responses

### Real-Time Updates
- ✅ Delta polling with `?since=` parameter
- ✅ ETag and Last-Modified headers
- ✅ 304 Not Modified support

### Accounting
- ✅ Double-entry bookkeeping
- ✅ Idempotent journal posting
- ✅ Period close and lock
- ✅ Trial balance validation

### WhatsApp Integration
- ✅ Meta Business Cloud API
- ✅ Order confirmations
- ✅ Delivery status updates
- ✅ Inbound message handling

### Database
- ✅ Unique constraints prevent duplicates
- ✅ Idempotent migrations
- ✅ Performance indexes
- ✅ Multi-location support

---

## 🔧 Configuration

### Basic Settings

Edit `config.php`:

```php
// Database
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'wapos');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application
define('APP_URL', 'http://localhost/wapos');
define('TIMEZONE', 'Africa/Nairobi');

// Currency
define('CURRENCY_CODE', 'USD');
define('CURRENCY_SYMBOL', '$');
```

### WhatsApp (Optional)

Add to database `settings` table:

```sql
INSERT INTO settings (setting_key, setting_value) VALUES
('whatsapp_access_token', 'your-access-token'),
('whatsapp_phone_number_id', 'your-phone-id'),
('whatsapp_business_account_id', 'your-business-id'),
('whatsapp_app_secret', 'your-app-secret');
```

---

## 📱 Testing Offline Mode

### Desktop (Chrome DevTools)

1. Open POS: `http://localhost/wapos/pos.php`
2. Press `F12` → **Network** tab
3. Check **Offline**
4. Create a sale
5. Uncheck **Offline**
6. Sale auto-syncs

### Mobile

1. Enable airplane mode
2. Create sale in POS
3. Disable airplane mode
4. Sale syncs automatically

---

## 🧪 Running Tests

### Unit Tests

```bash
vendor/bin/phpunit tests/Unit
```

### Integration Tests

```bash
vendor/bin/phpunit tests/Integration
```

### E2E Tests (Playwright)

```bash
# Install Playwright
npm install
npx playwright install

# Run tests
npx playwright test
```

---

## 📊 System Health Check

Visit: `/admin/system-health-enhanced.php`

Monitors:
- ✅ Database connectivity
- ✅ Disk space
- ✅ PHP version
- ✅ Queue size
- ✅ Error logs
- ✅ Performance
- ✅ Memory usage

---

## 🔐 Security Checklist

### Before Going Live

- [ ] Change default admin password
- [ ] Set `display_errors = 0` in config.php
- [ ] Enable HTTPS
- [ ] Uncomment HSTS header in .htaccess
- [ ] Configure backup cron job
- [ ] Test rollback procedure
- [ ] Review user permissions
- [ ] Enable error email alerts

---

## 📚 Documentation

- **Admin Guide:** `docs/ADMIN_GUIDE.md`
- **Deployment Guide:** `docs/DEPLOYMENT_GUIDE.md`
- **Implementation Summary:** `IMPLEMENTATION_SUMMARY.md`
- **API Documentation:** `DELIVERABLE_2_API_CONTRACT.md`
- **Database Schema:** `DELIVERABLE_1_DATABASE_SCHEMA.sql`

---

## 🆘 Troubleshooting

### "CSRF token invalid"
**Solution:** Clear browser cache, ensure cookies enabled

### "Composer not found"
**Solution:**
```bash
curl -sS https://getcomposer.org/installer | php
php composer.phar install
```

### "Migration failed: Duplicate entry"
**Solution:** Clean duplicate data first:
```sql
-- Find duplicates
SELECT sku, COUNT(*) FROM products GROUP BY sku HAVING COUNT(*) > 1;

-- Remove duplicates (keep first)
DELETE p1 FROM products p1
INNER JOIN products p2 
WHERE p1.id > p2.id AND p1.sku = p2.sku;
```

### "Offline sales not syncing"
**Solution:**
1. Check browser console for errors
2. Open DevTools → Application → IndexedDB → wapos_offline → outbox
3. Check status of queued items
4. Click "Sync Now" in POS

---

## 🎯 Next Steps

1. **Customize branding**
   - Upload logo
   - Configure receipt header/footer
   - Set business details

2. **Add products**
   - Import via CSV
   - Or add manually

3. **Create users**
   - Add cashiers
   - Assign roles
   - Set permissions

4. **Configure locations**
   - Add branches
   - Set tax rates
   - Configure currencies

5. **Test workflows**
   - Create test sale
   - Process refund
   - Generate reports
   - Test offline mode

6. **Setup backups**
   - Configure cron job
   - Test restore
   - Store off-site

7. **Go live!**
   - Enable HTTPS
   - Train staff
   - Monitor system

---

## 📞 Support

- **Documentation:** `docs/` folder
- **Issues:** Check `IMPLEMENTATION_SUMMARY.md`
- **Email:** support@wapos.com

---

## 🎉 Success!

You're now running WAPOS 2.0 with:
- ✅ Enterprise-grade architecture
- ✅ Offline-first capabilities
- ✅ Idempotent operations
- ✅ Comprehensive security
- ✅ Accounting compliance
- ✅ WhatsApp integration

**Happy selling! 🛒**

---

**Version:** 2.0  
**Last Updated:** October 31, 2025
