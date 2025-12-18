# 📦 WAPOS DEPLOYMENT - FILES ORGANIZED BY TYPE
## New Files, Modified Files, and SQL Queries

**Date:** December 18, 2025  
**Purpose:** Clear separation of what's new vs. what's modified

---

## 🆕 NEW FILES (11 files to upload)

These files don't exist on your live server yet. Upload them to the specified locations.

### **1. Database Migrations** → Upload to: `/database/migrations/`

```
LOCAL PATH                                                    → UPLOAD TO SERVER
────────────────────────────────────────────────────────────────────────────────
c:\xampp\htdocs\wapos\database\migrations\020_events_banquet_management.sql
                                                              → /database/migrations/020_events_banquet_management.sql

c:\xampp\htdocs\wapos\database\migrations\021_security_management.sql
                                                              → /database/migrations/021_security_management.sql

c:\xampp\htdocs\wapos\database\migrations\022_enhanced_hr_employee.sql
                                                              → /database/migrations/022_enhanced_hr_employee.sql

c:\xampp\htdocs\wapos\database\migrations\023_add_new_user_roles.sql
                                                              → /database/migrations/023_add_new_user_roles.sql

c:\xampp\htdocs\wapos\database\migrations\024_accounting_integration_fix.sql
                                                              → /database/migrations/024_accounting_integration_fix.sql
```

### **2. Service Classes** → Upload to: `/app/Services/`

```
LOCAL PATH                                                    → UPLOAD TO SERVER
────────────────────────────────────────────────────────────────────────────────
c:\xampp\htdocs\wapos\app\Services\EventsService.php        → /app/Services/EventsService.php

c:\xampp\htdocs\wapos\app\Services\SecurityService.php      → /app/Services/SecurityService.php

c:\xampp\htdocs\wapos\app\Services\HRService.php            → /app/Services/HRService.php
```

### **3. API Endpoints** → Upload to: `/api/`

```
LOCAL PATH                                                    → UPLOAD TO SERVER
────────────────────────────────────────────────────────────────────────────────
c:\xampp\htdocs\wapos\api\events-api.php                    → /api/events-api.php

c:\xampp\htdocs\wapos\api\security-api.php                  → /api/security-api.php

c:\xampp\htdocs\wapos\api\hr-api.php                        → /api/hr-api.php
```

### **4. Frontend Pages** → Upload to: `/` (root)

```
LOCAL PATH                                                    → UPLOAD TO SERVER
────────────────────────────────────────────────────────────────────────────────
c:\xampp\htdocs\wapos\events.php                            → /events.php
```

**Total NEW files: 11**

---

## ✏️ MODIFIED FILES (4 files to replace)

These files already exist on your live server. **BACKUP FIRST**, then replace them.

### **Frontend Pages** → Upload to: `/` (root)

```
LOCAL PATH                                                    → REPLACE ON SERVER
────────────────────────────────────────────────────────────────────────────────
c:\xampp\htdocs\wapos\index.php                             → /index.php
                                                              ⚠️ BACKUP FIRST!
                                                              (Added 3 new module cards)

c:\xampp\htdocs\wapos\resources.php                         → /resources.php
                                                              ⚠️ BACKUP FIRST!
                                                              (Added Events, Security, HR sections)

c:\xampp\htdocs\wapos\test-data-guide.php                   → /test-data-guide.php
                                                              ⚠️ BACKUP FIRST!
                                                              (Added test scenarios for new modules)

c:\xampp\htdocs\wapos\security.php                          → /security.php
                                                              ⚠️ BACKUP FIRST!
                                                              (Added Start Patrol modal)

c:\xampp\htdocs\wapos\hr-employees.php                      → /hr-employees.php
                                                              ⚠️ BACKUP FIRST!
                                                              (Fixed payroll modal button)
```

### **Documentation** → Upload to: `/docs/`

```
LOCAL PATH                                                    → REPLACE ON SERVER
────────────────────────────────────────────────────────────────────────────────
c:\xampp\htdocs\wapos\docs\WAPOS_Training_Manual.md         → /docs/WAPOS_Training_Manual.md
                                                              ⚠️ BACKUP FIRST!
                                                              (Added chapters 10, 11, 12)
```

**Total MODIFIED files: 6**

---

## 💾 SQL QUERIES FOR DATABASE UPDATE

### **Option 1: Run Combined File (RECOMMENDED)**

```bash
# Single command to run all migrations at once
mysql -u your_username -p your_database_name < c:\xampp\htdocs\wapos\ALL_MIGRATIONS_COMBINED.sql
```

**File location:** `c:\xampp\htdocs\wapos\ALL_MIGRATIONS_COMBINED.sql`

---

### **Option 2: Run Individual Files (If you prefer step-by-step)**

Run these SQL files in **EXACT ORDER**:

```bash
# 1. Events & Banquet Management (10 tables)
mysql -u your_username -p your_database_name < c:\xampp\htdocs\wapos\database\migrations\020_events_banquet_management.sql

# 2. Security Management (11 tables)
mysql -u your_username -p your_database_name < c:\xampp\htdocs\wapos\database\migrations\021_security_management.sql

# 3. HR & Employee Management (15 tables)
mysql -u your_username -p your_database_name < c:\xampp\htdocs\wapos\database\migrations\022_enhanced_hr_employee.sql

# 4. Add New User Roles
mysql -u your_username -p your_database_name < c:\xampp\htdocs\wapos\database\migrations\023_add_new_user_roles.sql

# 5. Fix Accounting Integration
mysql -u your_username -p your_database_name < c:\xampp\htdocs\wapos\database\migrations\024_accounting_integration_fix.sql
```

---

### **Option 3: Via phpMyAdmin**

1. Login to phpMyAdmin
2. Select your database
3. Click **Import** tab
4. Choose file: `c:\xampp\htdocs\wapos\ALL_MIGRATIONS_COMBINED.sql`
5. Click **Go**
6. Wait for completion (should create 36 tables)

---

### **What These SQL Queries Do:**

| File | Tables Created | Sample Data |
|------|----------------|-------------|
| `020_events_banquet_management.sql` | 10 tables | 3 venues, 5 event types, 10+ services |
| `021_security_management.sql` | 11 tables | 6 shifts, 8 posts, 4 patrol routes |
| `022_enhanced_hr_employee.sql` | 15 tables | 5 departments, 10+ positions, 3 leave types |
| `023_add_new_user_roles.sql` | 0 tables | Updates `users` table with 5 new roles |
| `024_accounting_integration_fix.sql` | 0 tables | Adds transaction_id columns |

**Total: 36 new tables + role updates + accounting links**

---

## ✅ VERIFICATION AFTER SQL EXECUTION

Run this query to verify all tables were created:

```sql
-- Should return 36
SELECT COUNT(*) as total_new_tables 
FROM information_schema.tables 
WHERE table_schema = 'your_database_name' 
AND (table_name LIKE 'event_%' 
     OR table_name LIKE 'security_%' 
     OR table_name LIKE 'hr_%');
```

Check specific tables exist:

```sql
-- Events tables (should return 10 rows)
SHOW TABLES LIKE 'event_%';

-- Security tables (should return 11 rows)
SHOW TABLES LIKE 'security_%';

-- HR tables (should return 15 rows)
SHOW TABLES LIKE 'hr_%';
```

---

## 📊 SUMMARY

### **Files to Upload:**
- ✅ **11 NEW files** (don't exist on server yet)
- ✅ **6 MODIFIED files** (replace existing - backup first!)
- **Total: 17 files**

### **SQL Queries:**
- ✅ **1 combined file** (easiest option)
- OR
- ✅ **5 individual files** (run in order)

### **Database Changes:**
- ✅ **36 new tables**
- ✅ **5 new user roles**
- ✅ **Accounting integration**
- ✅ **Sample data included**

---

## 🚀 DEPLOYMENT SEQUENCE

### **Step 1: Backup**
```bash
# Backup database
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql

# Backup files (download from server)
- index.php
- resources.php
- test-data-guide.php
- security.php
- hr-employees.php
- docs/WAPOS_Training_Manual.md
```

### **Step 2: Upload NEW Files (11 files)**
Upload all files from "NEW FILES" section above

### **Step 3: Upload MODIFIED Files (6 files)**
Replace all files from "MODIFIED FILES" section above

### **Step 4: Run SQL Queries**
Execute `ALL_MIGRATIONS_COMBINED.sql` OR run 5 individual files in order

### **Step 5: Verify**
- Check 36 tables created
- Visit `/events.php` - should load
- Visit `/security.php` - should load
- Visit `/hr-employees.php` - should load
- No console errors

---

## 📁 QUICK REFERENCE - ALL FILES AT A GLANCE

```
NEW FILES (11):
├── database/migrations/020_events_banquet_management.sql
├── database/migrations/021_security_management.sql
├── database/migrations/022_enhanced_hr_employee.sql
├── database/migrations/023_add_new_user_roles.sql
├── database/migrations/024_accounting_integration_fix.sql
├── app/Services/EventsService.php
├── app/Services/SecurityService.php
├── app/Services/HRService.php
├── api/events-api.php
├── api/security-api.php
├── api/hr-api.php
└── events.php

MODIFIED FILES (6):
├── index.php
├── resources.php
├── test-data-guide.php
├── security.php
├── hr-employees.php
└── docs/WAPOS_Training_Manual.md

SQL QUERIES (1 combined OR 5 individual):
└── ALL_MIGRATIONS_COMBINED.sql (recommended)
    OR
    ├── 020_events_banquet_management.sql
    ├── 021_security_management.sql
    ├── 022_enhanced_hr_employee.sql
    ├── 023_add_new_user_roles.sql
    └── 024_accounting_integration_fix.sql
```

---

**END OF ORGANIZED FILE LIST**

*All files are located in: `c:\xampp\htdocs\wapos\`*  
*Upload to your live server's web root (e.g., `/public_html/wapos/`)*
