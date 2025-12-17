# 🚀 DEPLOY TO PRODUCTION - COMPLETE GUIDE

## 📦 PART 1: FILES TO UPLOAD TO CPANEL

Upload these files via cPanel File Manager or FTP:

### **New Files (Upload to root directory)**
```
1. rider-portal.php
2. rider-login.php
3. reset-rider-password.php
```

### **New Files (Upload to app/Services/)**
```
4. app/Services/GoogleMapsService.php
```

### **New Files (Upload to api/)**
```
5. api/google-maps.php
```

### **New Files (Upload to js/)**
```
6. js/google-maps-delivery.js
```

### **Modified Files (REPLACE existing files)**
```
7. settings.php
8. includes/bootstrap.php
9. api/update-delivery-status.php
10. index.php
```

### **Create Directory**
Via cPanel File Manager:
- Create folder: `uploads/delivery-proofs`
- Set permissions: 755

---

## 💾 PART 2: SQL QUERIES FOR PRODUCTION DATABASE

Run these in cPanel → phpMyAdmin → Your Database → SQL tab

### **Query 1: Add Google Maps API Key Settings**
```sql
-- Add new settings for Google Maps APIs
INSERT INTO settings (setting_key, setting_value, setting_type, setting_group) 
VALUES 
('google_places_api_key', '', 'string', 'delivery'),
('google_routes_api_key', '', 'string', 'delivery')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
```

### **Query 2: Verify Riders Table Exists**
```sql
-- Check if riders table exists (should return rows if exists)
SELECT COUNT(*) as table_exists 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'riders';
```

### **Query 3: Verify Rider Location History Table**
```sql
-- Check if rider_location_history exists
SELECT COUNT(*) as table_exists 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'rider_location_history';
```

### **Query 4: Verify Delivery Status History Table**
```sql
-- Check if delivery_status_history exists
SELECT COUNT(*) as table_exists 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'delivery_status_history';
```

### **Query 5: Check Existing Riders**
```sql
-- View all existing riders
SELECT 
    u.id,
    u.username,
    u.full_name,
    u.role,
    u.is_active,
    r.id as rider_id,
    r.phone,
    r.vehicle_type,
    r.status
FROM users u
LEFT JOIN riders r ON u.id = r.user_id
WHERE u.role = 'rider';
```

---

## ⚙️ PART 3: CONFIGURE GOOGLE MAPS

### **Step 1: Google Cloud Console**
1. Go to: https://console.cloud.google.com/
2. Enable these APIs:
   - Maps JavaScript API
   - Places API
   - Routes API
   - Geocoding API

3. Create API Key(s):
   - Can use 1 key for all APIs (simpler)
   - Or separate keys (more secure)

4. Set Restrictions:
   - Maps JavaScript API: HTTP referrer → `https://yourdomain.com/*`
   - Places/Routes API: IP address → Your server IP

### **Step 2: Configure in WAPOS**
1. Login as admin
2. Go to: Settings → Delivery & Logistics
3. Scroll to Google Maps section
4. Enter your API keys:
   - Google Maps JavaScript API Key
   - Google Places API Key (can be same)
   - Google Routes API Key (can be same)
5. Click Save

---

## 🧪 PART 4: TESTING ON PRODUCTION

### **Test 1: Home Page**
- Visit: `https://yourdomain.com/`
- Check "Riders" link appears in navigation
- Click it - should go to rider-login.php

### **Test 2: Rider Login**
- Visit: `https://yourdomain.com/rider-login.php`
- Should see mobile-optimized login page
- Try logging in with existing rider account

### **Test 3: Rider Portal**
- After login, should redirect to rider-portal.php
- Click "Start Tracking"
- Grant location permission
- Map should load
- GPS indicator should turn green

### **Test 4: Admin Password Reset**
- Login as admin
- Visit: `https://yourdomain.com/reset-rider-password.php`
- Should see list of riders
- Select a rider and reset password

### **Test 5: Google Maps**
- Go to: Settings → Delivery & Logistics
- Check if Google Maps fields are visible
- Enter API keys and save

---

## 📋 DEPLOYMENT CHECKLIST

- [ ] **Upload 10 files** to cPanel
- [ ] **Create uploads/delivery-proofs** folder (chmod 755)
- [ ] **Run SQL Query 1** (add settings)
- [ ] **Run SQL Queries 2-4** (verify tables exist)
- [ ] **Run SQL Query 5** (check existing riders)
- [ ] **Configure Google Cloud Console** (enable APIs, create keys)
- [ ] **Enter API keys** in WAPOS Settings
- [ ] **Test home page** (Riders link visible)
- [ ] **Test rider login** (mobile page loads)
- [ ] **Test rider portal** (GPS tracking works)
- [ ] **Test password reset** (admin tool works)
- [ ] **Share rider-login.php URL** with riders

---

## 🔧 IF TABLES DON'T EXIST

If SQL Queries 2-4 return 0 (tables don't exist), run these:

### **Create riders table:**
```sql
CREATE TABLE IF NOT EXISTS riders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    vehicle_type ENUM('motorcycle', 'car', 'bicycle', 'van') DEFAULT 'motorcycle',
    vehicle_number VARCHAR(20),
    vehicle_make VARCHAR(50),
    vehicle_color VARCHAR(30),
    license_number VARCHAR(50),
    vehicle_plate_photo_url VARCHAR(255),
    current_latitude DECIMAL(10, 8),
    current_longitude DECIMAL(11, 8),
    location_accuracy FLOAT,
    current_speed FLOAT,
    current_heading FLOAT,
    last_location_update DATETIME,
    status ENUM('available', 'busy', 'offline') DEFAULT 'available',
    total_deliveries INT DEFAULT 0,
    successful_deliveries INT DEFAULT 0,
    average_rating DECIMAL(3, 2) DEFAULT 0.00,
    last_delivery_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_location (current_latitude, current_longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### **Create rider_location_history table:**
```sql
CREATE TABLE IF NOT EXISTS rider_location_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rider_id INT UNSIGNED NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    accuracy FLOAT,
    speed FLOAT,
    heading FLOAT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
    INDEX idx_rider_time (rider_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### **Create delivery_status_history table:**
```sql
CREATE TABLE IF NOT EXISTS delivery_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delivery_id INT UNSIGNED NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT,
    photo_url VARCHAR(255),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    user_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_delivery (delivery_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🎯 SUMMARY

**What you're deploying:**
1. Google Maps Integration (Places, Routes, Maps JS API)
2. Rider Portal with GPS tracking
3. Rider Login page (mobile-optimized)
4. Admin password reset tool
5. Home page with Riders link

**What riders get:**
- Mobile-friendly login at: `https://yourdomain.com/rider-login.php`
- GPS tracking portal
- Delivery management
- Photo upload for proof of delivery
- Navigation to customers

**What admins get:**
- Google Maps integration for better routing
- Real-time rider tracking
- Password reset tool
- Enhanced delivery management

---

**Ready to deploy!** Upload files, run SQL, configure API keys, test, and go live! 🚀
