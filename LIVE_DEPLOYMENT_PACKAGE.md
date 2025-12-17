# WAPOS Live System Deployment Package
## Events, Security & HR Modules - Production Deployment

**Date:** December 17, 2025
**Version:** 1.0.0
**Modules:** Events & Banquet Management, Security Management, HR & Employee Management

---

## 📋 DEPLOYMENT CHECKLIST

### Pre-Deployment
- [ ] Backup live database
- [ ] Backup live files
- [ ] Verify PHP version >= 7.4
- [ ] Verify MySQL version >= 5.7
- [ ] Test database connection
- [ ] Review current user roles

### Deployment
- [ ] Upload new files (see File List below)
- [ ] Run SQL migrations in order
- [ ] Verify table creation
- [ ] Test API endpoints
- [ ] Clear browser cache

### Post-Deployment
- [ ] Test each module functionality
- [ ] Verify accounting integration
- [ ] Check user permissions
- [ ] Monitor error logs

---

## 🗄️ SQL MIGRATIONS (Run in Order)

### Step 1: Backup Command
```bash
# Run this FIRST on live server
mysqldump -u [username] -p [database_name] > wapos_backup_$(date +%Y%m%d_%H%M%S).sql
```

### Step 2: Run Migrations

**Migration 1: Events & Banquet Management**
```bash
mysql -u [username] -p [database_name] < 020_events_banquet_management.sql
```

**Migration 2: Security Management**
```bash
mysql -u [username] -p [database_name] < 021_security_management.sql
```

**Migration 3: HR & Employee Management**
```bash
mysql -u [username] -p [database_name] < 022_enhanced_hr_employee.sql
```

**Migration 4: User Roles**
```bash
mysql -u [username] -p [database_name] < 023_add_new_user_roles.sql
```

**Migration 5: Accounting Integration**
```bash
mysql -u [username] -p [database_name] < 024_accounting_integration_fix.sql
```

### Step 3: Verify Tables Created
```sql
-- Run this to verify all tables were created
SELECT COUNT(*) as event_tables 
FROM information_schema.tables 
WHERE table_schema = '[database_name]' 
AND table_name LIKE 'event_%';
-- Should return: 10

SELECT COUNT(*) as security_tables 
FROM information_schema.tables 
WHERE table_schema = '[database_name]' 
AND table_name LIKE 'security_%';
-- Should return: 11

SELECT COUNT(*) as hr_tables 
FROM information_schema.tables 
WHERE table_schema = '[database_name]' 
AND table_name LIKE 'hr_%';
-- Should return: 15
```

---

## 📁 FILES TO UPLOAD

### Database Migrations (Upload to: `/database/migrations/`)
1. `020_events_banquet_management.sql` - **MODIFIED** (Fixed foreign keys)
2. `021_security_management.sql` - **MODIFIED** (Fixed foreign keys)
3. `022_enhanced_hr_employee.sql` - **NEW**
4. `023_add_new_user_roles.sql` - **NEW**
5. `024_accounting_integration_fix.sql` - **NEW**

### Service Classes (Upload to: `/app/Services/`)
1. `EventsService.php` - **NEW** (Full events management logic)
2. `SecurityService.php` - **NEW** (Full security management logic)
3. `HRService.php` - **NEW** (Full HR management logic)

### API Endpoints (Upload to: `/api/`)
1. `events-api.php` - **MODIFIED** (Added service details fetch)
2. `security-api.php` - **NEW** (Full security API)
3. `hr-api.php` - **NEW** (Full HR API)

### Frontend Pages (Upload to: `/`)
1. `events.php` - **NEW** (Complete events UI)
2. `security.php` - **MODIFIED** (Added patrol modal, removed placeholder)
3. `hr-employees.php` - **MODIFIED** (Fixed payroll button)

### Configuration Files (Upload to: `/includes/`)
1. `module-catalog.php` - **VERIFY** (Should already have new modules)
2. `header.php` - **VERIFY** (Should already have navigation)

---

## 🔧 CONFIGURATION CHANGES

### User Roles Added
The following roles are now available:
- `security_manager`
- `security_staff`
- `hr_manager`
- `hr_staff`
- `banquet_coordinator`

### Module Permissions
```php
// Events Module
'allowed_roles' => ['admin', 'manager', 'frontdesk', 'banquet_coordinator']

// Security Module
'allowed_roles' => ['admin', 'manager', 'security_manager', 'security_staff']

// HR Module
'allowed_roles' => ['admin', 'manager', 'hr_manager', 'hr_staff']
```

---

## 🧪 POST-DEPLOYMENT TESTING

### Test Events Module
1. Navigate to: `https://yourdomain.com/wapos/events.php`
2. Verify dashboard loads with stats
3. Test "New Booking" button
4. Test "Manage Venues" button
5. Create a test booking
6. Add a service to booking
7. Record a payment
8. Verify payment appears in accounting transactions

### Test Security Module
1. Navigate to: `https://yourdomain.com/wapos/security.php`
2. Verify dashboard loads with stats
3. Test "Add Personnel" button
4. Test "Schedule Shift" button
5. Test "Report Incident" button
6. Test "Start Patrol" button (NEW - no longer placeholder)
7. Test "Log Visitor" button

### Test HR Module
1. Navigate to: `https://yourdomain.com/wapos/hr-employees.php`
2. Verify dashboard loads with stats
3. Test "Add Employee" button
4. Test "Process Payroll" button
5. Create a test payroll run
6. Verify payroll creates accounting expense

---

## 🔍 VERIFICATION QUERIES

### Check Sample Data Loaded
```sql
-- Events sample data
SELECT COUNT(*) FROM event_venues; -- Should be 3
SELECT COUNT(*) FROM event_types; -- Should be 5
SELECT COUNT(*) FROM event_services; -- Should be 10+

-- Security sample data
SELECT COUNT(*) FROM security_shifts; -- Should be 6
SELECT COUNT(*) FROM security_posts; -- Should be 8
SELECT COUNT(*) FROM security_patrol_routes; -- Should be 4

-- HR sample data
SELECT COUNT(*) FROM hr_departments; -- Should be 5
SELECT COUNT(*) FROM hr_positions; -- Should be 10+
SELECT COUNT(*) FROM hr_leave_types; -- Should be 3
```

### Check Accounting Integration
```sql
-- Verify transaction_id column exists in event_payments
DESCRIBE event_payments;

-- Check if any payments have been linked to accounting
SELECT ep.payment_number, ep.amount, t.transaction_date, t.description
FROM event_payments ep
LEFT JOIN transactions t ON ep.transaction_id = t.id
LIMIT 5;
```

---

## ⚠️ IMPORTANT NOTES

### Foreign Key Data Types
All foreign keys referencing `users` and `customers` tables have been corrected to `INT UNSIGNED` to match the existing schema. This was the main fix that allowed migrations to run successfully.

### Accounting Integration
- Event payments automatically create income transactions
- HR payroll runs can create expense transactions
- All linked via `transaction_id` columns

### Sample Data
All migrations include sample data:
- **Events:** 3 venues, 5 event types, 10+ services
- **Security:** 6 shifts, 8 posts, 4 patrol routes
- **HR:** 5 departments, 10+ positions, 3 leave types

### No Placeholders
All features are fully implemented with no "coming soon" placeholders.

---

## 🆘 ROLLBACK PROCEDURE

If deployment fails:

```bash
# Restore from backup
mysql -u [username] -p [database_name] < wapos_backup_[timestamp].sql

# Remove uploaded files
rm events.php security.php hr-employees.php
rm api/events-api.php api/security-api.php api/hr-api.php
rm app/Services/EventsService.php app/Services/SecurityService.php app/Services/HRService.php
```

---

## 📞 SUPPORT

If you encounter issues:
1. Check PHP error logs: `/var/log/php_errors.log`
2. Check MySQL error logs: `/var/log/mysql/error.log`
3. Check browser console for JavaScript errors
4. Verify all files uploaded correctly
5. Verify database migrations completed

---

## ✅ SUCCESS INDICATORS

Deployment is successful when:
- ✅ All 36 new tables created
- ✅ No 400 errors in browser console
- ✅ Dashboard stats load on all three modules
- ✅ All modals open and function correctly
- ✅ Sample data visible in dropdowns
- ✅ API endpoints return success responses
- ✅ Accounting integration creates transactions

---

**End of Deployment Package**
