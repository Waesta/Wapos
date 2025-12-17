# 🚨 URGENT: Database Migrations Not Run

## **Root Cause of All Errors**

The console shows **400 Bad Request** errors for all API endpoints because:

❌ **The database tables for Events, Security, and HR modules DO NOT EXIST yet**

The migrations have been created but **NOT executed** on your database.

---

## ✅ **IMMEDIATE FIX - Run These Commands**

### **Option 1: Via Command Line (Recommended)**

Open Command Prompt in `c:\xampp\htdocs\wapos` and run:

```bash
# Navigate to your project
cd c:\xampp\htdocs\wapos

# Run all migrations in order
mysql -u root -p wapos < database/migrations/020_events_banquet_management.sql
mysql -u root -p wapos < database/migrations/021_security_management.sql
mysql -u root -p wapos < database/migrations/022_enhanced_hr_employee.sql
mysql -u root -p wapos < database/migrations/023_add_new_user_roles.sql
mysql -u root -p wapos < database/migrations/024_accounting_integration_fix.sql
```

**Note:** If your MySQL root user has no password, omit the `-p` flag.

---

### **Option 2: Via phpMyAdmin (Alternative)**

1. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Select **`wapos`** database from left sidebar
3. Click **SQL** tab
4. For each migration file, copy its contents and execute:
   - `020_events_banquet_management.sql`
   - `021_security_management.sql`
   - `022_enhanced_hr_employee.sql`
   - `023_add_new_user_roles.sql`
   - `024_accounting_integration_fix.sql`

---

## 🔍 **Verify Tables Were Created**

After running migrations, verify by visiting:

```
http://localhost/wapos/check_tables.php
```

**Expected Output:**
```json
{
    "success": true,
    "total_tables": 23,
    "existing_tables": 23,
    "missing_tables": [],
    "message": "All tables exist!"
}
```

---

## 📋 **What Each Migration Does**

### **Migration 020: Events & Banquet Management**
Creates 10 tables:
- `event_venues` - Banquet halls, gardens, conference rooms
- `event_types` - Weddings, conferences, birthdays, etc.
- `event_bookings` - Customer bookings with dates and details
- `event_services` - Catering, decoration, AV equipment
- `event_booking_services` - Services added to bookings
- `event_payments` - Payment tracking with accounting integration
- `event_setup_requirements` - Setup tasks and assignments
- `event_feedback` - Customer reviews
- `event_activity_log` - Audit trail
- `event_menu_packages` - Food and beverage packages

### **Migration 021: Security Management**
Creates 11 tables:
- `security_personnel` - Guards and security staff
- `security_posts` - Guard posts and checkpoints
- `security_shifts` - Shift definitions (morning, evening, night)
- `security_schedules` - Staff scheduling
- `security_incidents` - Incident reports
- `security_patrols` - Patrol routes and logs
- `security_patrol_checkpoints` - Checkpoint verification
- `security_visitor_log` - Visitor entry/exit tracking
- `security_equipment` - Security equipment inventory
- `security_training` - Training records
- `security_activity_log` - Audit trail

### **Migration 022: Enhanced HR & Employee Management**
Creates 15 tables:
- `hr_departments` - Company departments
- `hr_positions` - Job positions
- `hr_employees` - Employee records
- `hr_employee_documents` - Document storage
- `hr_leave_types` - Leave categories
- `hr_leave_applications` - Leave requests
- `hr_payroll_runs` - Payroll processing
- `hr_payroll_details` - Individual payroll records
- `hr_attendance` - Time tracking
- `hr_performance_reviews` - Performance management
- `hr_training_programs` - Training courses
- `hr_employee_training` - Training assignments
- `hr_disciplinary_actions` - Disciplinary records
- `hr_benefits` - Employee benefits
- `hr_activity_log` - Audit trail

### **Migration 023: Add New User Roles**
Modifies `users` table to add roles:
- `security_manager` - Security module admin
- `security_staff` - Security guards
- `hr_manager` - HR module admin
- `hr_staff` - HR personnel
- `banquet_coordinator` - Events coordinator

### **Migration 024: Accounting Integration Fix**
Adds accounting integration:
- `transaction_id` column to `event_payments`
- `expense_transaction_id` to `hr_payroll_runs`
- Fixes `payment_date` column type
- Ensures all required columns exist

---

## 🐛 **Other Issues Fixed**

### **1. JavaScript Function Errors - FIXED ✅**

**Error:** `showPayrollModal is not defined`
- **Fixed:** Changed button to call `showCreatePayrollRunModal()` in `hr-employees.php`

**Error:** `showStartPatrolModal is not defined`
- **Fixed:** Added function to `security.php` (shows info notification)

---

## 🚀 **After Running Migrations**

### **1. Clear Browser Cache**
Press `Ctrl + F5` to hard refresh

### **2. Test Each Module**

**Events Module:**
```
http://localhost/wapos/events.php
```
- Should load dashboard stats
- Should show bookings table
- All modals should work

**Security Module:**
```
http://localhost/wapos/security.php
```
- Should load dashboard stats
- Should show schedule and incidents
- All modals should work

**HR Module:**
```
http://localhost/wapos/hr-employees.php
```
- Should load dashboard stats
- Should show employees table
- All modals should work

### **3. Verify API Endpoints**

Test in browser:
```
http://localhost/wapos/api/events-api.php?action=get_venues
http://localhost/wapos/api/security-api.php?action=get_personnel
http://localhost/wapos/api/hr-api.php?action=get_departments
```

**Expected:** JSON response with `"success": true`

---

## 📊 **Sample Data**

After migrations run successfully, the tables will have sample data:

**Events:**
- 3 venues (Grand Ballroom, Garden Pavilion, Conference Hall)
- 5 event types (Wedding, Conference, Birthday, etc.)
- 10+ services (Catering, Decoration, AV Equipment, etc.)

**Security:**
- 3 posts (Main Gate, Lobby, Parking)
- 3 shifts (Morning, Evening, Night)

**HR:**
- 5 departments (Front Office, Housekeeping, F&B, etc.)
- 10+ positions (Manager, Receptionist, Chef, etc.)
- 3 leave types (Annual, Sick, Emergency)

---

## ⚠️ **Common Issues**

### **Issue: "Access denied for user"**
**Solution:** Check MySQL credentials in `includes/config.php`

### **Issue: "Unknown database 'wapos'"**
**Solution:** Create database first:
```sql
CREATE DATABASE IF NOT EXISTS wapos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### **Issue: "Table already exists"**
**Solution:** Migrations use `CREATE TABLE IF NOT EXISTS`, so safe to re-run

### **Issue: Still getting 400 errors after migrations**
**Solutions:**
1. Check PHP error log: `c:\xampp\php\logs\php_error_log`
2. Check Apache error log: `c:\xampp\apache\logs\error.log`
3. Verify user has required role in database
4. Clear browser cache completely

---

## 📞 **Quick Diagnostic**

Run this SQL to check your setup:

```sql
-- Check if tables exist
SELECT COUNT(*) as event_tables 
FROM information_schema.tables 
WHERE table_schema = 'wapos' AND table_name LIKE 'event_%';

SELECT COUNT(*) as security_tables 
FROM information_schema.tables 
WHERE table_schema = 'wapos' AND table_name LIKE 'security_%';

SELECT COUNT(*) as hr_tables 
FROM information_schema.tables 
WHERE table_schema = 'wapos' AND table_name LIKE 'hr_%';

-- Check user roles
SELECT role FROM users WHERE id = 1;
```

**Expected Results:**
- event_tables: 10
- security_tables: 11
- hr_tables: 15
- role: should include 'admin' or relevant role

---

## ✅ **Summary**

**What's Wrong:**
- Database tables don't exist (migrations not run)
- 2 JavaScript functions were missing (now fixed)

**What to Do:**
1. ✅ Run 5 migration files via MySQL command line or phpMyAdmin
2. ✅ Visit `check_tables.php` to verify
3. ✅ Clear browser cache (Ctrl + F5)
4. ✅ Test each module
5. ✅ Verify APIs return data

**After Fix:**
- All 400 errors will disappear
- All modals will work
- Dashboard stats will load
- Tables will populate with data

---

**🎯 PRIORITY: Run the migrations NOW to fix all issues!**
