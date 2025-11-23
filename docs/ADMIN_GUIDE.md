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

Daily/weekly/hourly automation is now controlled from **Settings → Data Protection & Backups**.

1. Choose frequency (hourly/daily/weekly), run time, weekly day (if applicable), retention window, and storage path.
2. Click **Save Changes** to persist settings. This automatically updates the `scheduled_tasks` table and next-run timestamp.
3. Configure your OS scheduler to call the bundled runner:

```bash
# Example cron (Linux) – runs every hour
0 * * * * cd /path/to/wapos && /usr/bin/php scripts/run-scheduled-tasks.php >> storage/logs/scheduler.log 2>&1
```

On Windows Task Scheduler use:
```
Program/script: php.exe
Arguments: c:\xampp\htdocs\wapos\scripts\run-scheduled-tasks.php
Start in: c:\xampp\htdocs\wapos
```

Every time the runner executes, it checks `scheduled_tasks` for due jobs, performs backups via `SystemBackupService`, and auto-purges expired archives per retention policy.

**Manual Backup (UI):**

1. Navigate to **Settings → Data Protection & Backups**.
2. Click **Run Backup Now**. Progress + result appear inline and the page refreshes to show the new entry.

**Manual Backup (CLI fallback):**

```bash
php scripts/run-scheduled-tasks.php  # Executes any due job immediately
```

### Restore Procedure

1. **Stop all operations** (maintenance mode or take site offline).
2. Locate the `.zip` backup from `/backups` (default) or your custom storage directory.
3. Extract the archive to obtain the `.sql` file.
4. Import database dump:
   ```bash
   mysql -u DB_USER -p DB_NAME < path/to/backup.sql
   ```
5. **Verify data integrity:**
   - Check recent sales
   - Verify inventory counts
   - Test user login
6. **Exit maintenance mode** once validation passes.

### Backup Retention & Verification

- Retention is enforced automatically using the configured day count. Old files and log rows are deleted after each successful run.
- For additional resilience, mirror `/backups` onto off-site storage (S3, Google Drive, etc.).
- Monthly test: download the latest backup, restore to a staging database, and spot-check the dashboard to ensure dumps remain valid.

### Scheduled Backup Testing Checklist

1. Adjust the schedule to run within the next 5 minutes (e.g., hourly, current minute +1).
2. Wait for the cron/Task Scheduler window or trigger `php scripts/run-scheduled-tasks.php` manually.
3. Confirm the new row appears under **Manual Backups & History** with `Status = Success`.
4. Download the archive via the download icon and verify it opens and contains the `.sql` dump.
5. Confirm expired backups past retention are no longer listed.

---

## Data Import & Export

### Supported Entities

- Products (SKU, pricing, stock metadata)
- Customers (contact & address basics)
- Suppliers (contact, tax details)
- Users (role assignments, status)

The full column definitions and keys are shown in **Settings → Data Protection & Backups → Data Import & Export**.

### Templates

1. Go to the Data Import & Export card.
2. Pick an entity from the dropdown.
3. Click **Template** to download headers-only CSV.
4. Populate the file using UTF-8 encoding. Required columns are marked in the UI.

### Exporting Live Data

1. Select the entity.
2. Click **Export** to download the current dataset as CSV (includes headers + data).
3. Use filters/transformations externally if needed; re-import uses the same column order.

### Import Workflow

1. Select entity.
2. Choose **Validate Only** to check data without changes, or **Validate & Import** to perform inserts/updates.
3. Upload the CSV produced from template/export.
4. Click **Process File**.
5. Status banner shows success counts or validation errors (including row numbers and column names).

### Matching & Updates

- Products: matched by SKU
- Customers: matched by email, then phone if email blank
- Suppliers: matched by name
- Users: matched by username

When a match is found, the row updates the existing record. Otherwise, a new record is inserted. Passwords are required only when creating new users (hashing handled server-side).

### Import Safety Tips

1. **Always validate first** when working with new templates or external data.
2. Keep backups before mass changes; use **Run Backup Now** if uncertain.
3. For large files (>5k rows), split into smaller batches to avoid PHP execution limits.
4. Numeric columns accept plain numbers (no commas). Boolean columns accept 1/0 or Yes/No.

### Troubleshooting Imports

- **“Missing required columns”** → ensure headers exactly match the template (case insensitive).
- **“Invalid value for Role”** → role must be one of the allowed options displayed on the form.
- **“Unable to open uploaded file”** → confirm PHP upload limits (`upload_max_filesize`, `post_max_size`).
- **Partial updates** → results panel indicates how many rows inserted/updated; rerun export to confirm.

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
