# WAPOS Administrator Guide

## Table of Contents

1. [System Setup](#system-setup)
2. [User & Role Management](#user--role-management)
3. [Location Configuration](#location-configuration)
4. [Tax & Currency Settings](#tax--currency-settings)
5. [WhatsApp Integration](#whatsapp-integration)
6. [Backup & Restore](#backup--restore)
7. [Month Close Process](#month-close-process)
8. [System Monitoring](#system-monitoring)
9. [Troubleshooting](#troubleshooting)

---

## System Setup

### Initial Installation

1. **Install Composer Dependencies**
   ```bash
   cd c:\xampp\htdocs\wapos
   composer install
   ```

2. **Run Database Migration**
   ```bash
   php deploy.php 2.0.0
   ```
   
   Or manually via phpMyAdmin:
   ```sql
   SOURCE database/migrations/001_add_uniqueness_constraints.sql
   ```

3. **Set Permissions**
   - Ensure `logs/` is writable (755)
   - Ensure `cache/` is writable (755)
   - Ensure `uploads/` is writable (755)

4. **Configure Environment**
   - Edit `config.php` with your database credentials
   - Set `APP_URL` to your domain
   - Configure timezone

### Production Checklist

- [ ] Set `display_errors = 0` in config.php
- [ ] Enable HTTPS and uncomment HSTS header in .htaccess
- [ ] Configure email alerts for errors
- [ ] Set up automated backups (cron)
- [ ] Test rollback procedure
- [ ] Configure WhatsApp (if using)

---

## User & Role Management

### Creating Users

1. Navigate to **Settings → Users**
2. Click **Add New User**
3. Fill in details:
   - Username (unique)
   - Full Name
   - Email
   - Phone
   - Role (admin/manager/cashier)
   - Location assignment
4. Click **Save**

Default password will be sent to user's email.

### Available Roles

| Role | Permissions |
|------|-------------|
| **Admin** | Full system access, can manage users, settings, accounting |
| **Manager** | Sales, reports, inventory, customers (no system settings) |
| **Cashier** | POS access only, create sales, view customers |
| **Waiter** | Restaurant orders, table management |
| **Housekeeper** | Room status updates, cleaning logs |

### Password Reset

**Admin Reset:**
```bash
php reset-admin-password.php
```

**User Self-Service:**
Users can reset via login page → "Forgot Password"

### Audit Trail

View user actions:
1. Navigate to **Settings → Audit Log**
2. Filter by user, action, or date range
3. Export to CSV for compliance

---

## Location Configuration

### Adding Locations

1. Navigate to **Settings → Locations**
2. Click **Add Location**
3. Configure:
   - Location Code (unique, e.g., "MAIN", "BRANCH1")
   - Name
   - Address
   - Phone/Email
   - Timezone
   - Currency Code
   - Tax Rate
   - Service Charge Rate

### Multi-Location Features

- Each transaction is scoped to a location
- Users can be assigned to specific locations
- Cross-location transfers require admin approval
- Reports can be filtered by location

---

## Tax & Currency Settings

### Tax Configuration

1. Navigate to **Settings → Tax Settings**
2. Set default tax rate (e.g., 16% = 0.16)
3. Configure tax-exempt categories
4. Set tax display preferences

### Currency Settings

Edit `config.php`:

```php
define('CURRENCY_CODE', 'USD');
define('CURRENCY_SYMBOL', '$');
define('CURRENCY_POSITION', 'before'); // or 'after'
define('DECIMAL_SEPARATOR', '.');
define('THOUSANDS_SEPARATOR', ',');
```

Supported formats:
- USD: $1,234.56
- EUR: 1.234,56 €
- KES: KSh 1,234.56

---

## WhatsApp Integration

### Setup Requirements

1. **Meta Business Account**
   - Create at business.facebook.com
   - Verify business
   - Add phone number

2. **Get Credentials**
   - Access Token
   - Phone Number ID
   - Business Account ID
   - App Secret (for webhook verification)

### Configuration

1. Navigate to **Settings → WhatsApp**
2. Enter credentials:
   - Access Token
   - Phone Number ID
   - Business Account ID
   - App Secret
3. Click **Save & Test**

### Template Setup

Create message templates in Meta Business Manager:

**order_confirmation**
```
Hello {{1}}, your order #{{2}} has been confirmed. 
Total: {{3}}. Estimated delivery: {{4}}.
```

**order_out_for_delivery**
```
Your order #{{1}} is out for delivery!
```

**order_delivered**
```
Your order #{{1}} has been delivered. Thank you!
```

### Webhook Configuration

1. In Meta Business Manager, set webhook URL:
   ```
   https://yourdomain.com/wapos/api/whatsapp/webhook
   ```

2. Set verify token (same as in settings)

3. Subscribe to:
   - messages
   - message_status

### Testing

Send test message:
1. Navigate to **WhatsApp → Test**
2. Enter phone number
3. Select template
4. Click **Send**

---

## Backup & Restore

### Automated Backups

**Setup Cron Job (cPanel):**

```bash
# Daily backup at 2 AM
0 2 * * * cd /home/username/public_html/wapos && php deploy.php backup
```

**Manual Backup:**

```bash
php deploy.php backup
```

Backups stored in `/backups/` directory.

### Restore Procedure

1. **Stop all operations** (maintenance mode)

2. **Restore database:**
   ```bash
   php deploy.php restore backups/db_2025-10-31_02-00-00.sql
   ```

3. **Verify data integrity:**
   - Check recent sales
   - Verify inventory counts
   - Test user login

4. **Exit maintenance mode**

### Backup Retention

- Keep last 7 daily backups
- Keep last 4 weekly backups
- Keep last 12 monthly backups
- Store off-site (Google Drive, Dropbox)

---

## Month Close Process

### Pre-Close Checklist

- [ ] All sales entered
- [ ] All purchases recorded
- [ ] Inventory count completed
- [ ] Bank reconciliation done
- [ ] All adjustments posted

### Closing Steps

1. Navigate to **Accounting → Period Close**
2. Select period (e.g., October 2025)
3. Review trial balance
4. Verify:
   - Total debits = Total credits
   - AR aging matches GL
   - Inventory valuation matches GL
5. Click **Close Period**

### Post-Close

- Period is marked "Closed"
- No edits allowed to closed period
- Admin can reopen if needed (audit logged)

### Locking Period

For permanent lock:
1. Navigate to closed period
2. Click **Lock Period**
3. Confirm

**Warning:** Locked periods cannot be reopened without database access.

---

## System Monitoring

### Health Dashboard

Access: **Admin → System Health**

Monitors:
- Database connectivity
- Disk space
- PHP version
- Queue size (offline sync)
- Recent errors
- Query performance
- Memory usage

### Log Viewer

Access: **Admin → Logs**

View and search:
- Application logs (`logs/app.log`)
- Error logs
- Audit logs

### Performance Metrics

Monitor:
- Average sale processing time
- API response times
- Database query times
- Offline queue size

### Alerts

Configure email alerts for:
- Disk space > 90%
- Database errors
- Failed sync attempts > 100
- Critical errors in logs

---

## Troubleshooting

### Common Issues

**Issue:** "CSRF token invalid"  
**Solution:** Clear browser cache, ensure cookies enabled

**Issue:** "Database connection failed"  
**Solution:** Check config.php credentials, verify MySQL running

**Issue:** "Offline sales not syncing"  
**Solution:** 
1. Check network connectivity
2. View outbox: Developer Tools → Application → IndexedDB
3. Manual flush: Click "Sync Now" in POS

**Issue:** "Duplicate SKU error"  
**Solution:** Clean duplicate data before migration:
```sql
SELECT sku, COUNT(*) FROM products GROUP BY sku HAVING COUNT(*) > 1;
```

**Issue:** "Trial balance doesn't match"  
**Solution:**
1. Run accounting report
2. Check for unposted entries
3. Verify journal entry totals

### Support Contacts

- **Technical Support:** support@wapos.com
- **Emergency:** +1-xxx-xxx-xxxx
- **Documentation:** https://docs.wapos.com

### System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- 256MB memory limit
- 1GB disk space minimum
- HTTPS recommended

---

## Appendix

### Keyboard Shortcuts

| Action | Shortcut |
|--------|----------|
| New Sale | Ctrl+N |
| Search Product | Ctrl+F |
| Complete Sale | Ctrl+Enter |
| Void Sale | Ctrl+D |
| Print Receipt | Ctrl+P |

### API Endpoints

For integrations:
- `POST /api/sales` - Create sale
- `GET /api/sales?since=` - Get sales (delta)
- `GET /api/products` - Get products
- `POST /api/whatsapp/webhook` - WhatsApp webhook

### Database Schema

See `DELIVERABLE_1_DATABASE_SCHEMA.sql` for complete schema.

---

**Version:** 2.0  
**Last Updated:** October 31, 2025
