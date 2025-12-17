# Rider Login System - Quick Reference

## Overview
Complete login system for delivery riders with both standard and mobile-optimized interfaces.

## Login Options

### Option 1: Mobile-Optimized Rider Login (Recommended)
**URL:** `https://yourdomain.com/rider-login.php`

**Features:**
- ✅ Mobile-first design
- ✅ Large touch-friendly buttons
- ✅ Rider-specific branding
- ✅ PWA installable (add to home screen)
- ✅ Auto-redirects to rider portal
- ✅ Validates rider role only

**Perfect for:** Riders accessing from smartphones

### Option 2: Standard Login
**URL:** `https://yourdomain.com/login.php`

**Features:**
- ✅ Works for all user roles
- ✅ Auto-redirects based on role
- ✅ Standard WAPOS interface

**Perfect for:** Desktop access or multi-role users

## Login Flow

### For Riders Using Mobile Login
```
1. Visit: rider-login.php
   ↓
2. Enter username & password
   ↓
3. System validates credentials
   ↓
4. Checks user has 'rider' role
   ↓
5. Auto-redirects to: rider-portal.php
   ↓
6. Rider can start GPS tracking
```

### For Riders Using Standard Login
```
1. Visit: login.php
   ↓
2. Enter username & password
   ↓
3. System validates credentials
   ↓
4. Detects 'rider' role
   ↓
5. Auto-redirects to: rider-portal.php
```

## Files Modified/Created

### New Files
1. **`rider-login.php`** - Mobile-optimized rider login page
   - Rider-specific branding
   - Large touch targets
   - PWA support
   - Role validation

### Modified Files
1. **`includes/bootstrap.php`** - Added rider redirect
   - Line 91: `'rider' => '/rider-portal.php'`
   - Riders now auto-redirect after login

## Setup Instructions

### 1. Upload Files
```
rider-login.php
includes/bootstrap.php (replace existing)
```

### 2. Share Login URL with Riders
Give riders this URL:
```
https://yourdomain.com/rider-login.php
```

Or create a QR code for easy mobile access:
```
https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=https://yourdomain.com/rider-login.php
```

### 3. Create Rider Accounts
Each rider needs:
- Username (e.g., `rider1`, `john_rider`)
- Password (secure, at least 8 characters)
- Role set to `rider`
- Entry in `riders` table

**SQL Example:**
```sql
-- Create user account
INSERT INTO users (username, password, full_name, email, role, active)
VALUES (
    'rider1',
    '$2y$10$...', -- Use password_hash() in PHP
    'John Rider',
    'john@example.com',
    'rider',
    1
);

-- Create rider profile
INSERT INTO riders (user_id, name, phone, vehicle_type, vehicle_number, status)
VALUES (
    LAST_INSERT_ID(),
    'John Rider',
    '+254712345678',
    'motorcycle',
    'KAA 123A',
    'available'
);
```

**PHP Script to Create Rider:**
```php
<?php
require_once 'includes/bootstrap.php';

$username = 'rider1';
$password = 'SecurePass123!';
$fullName = 'John Rider';
$phone = '+254712345678';

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$db = Database::getInstance();
$db->query("
    INSERT INTO users (username, password, full_name, role, active)
    VALUES (?, ?, ?, 'rider', 1)
", [$username, $hashedPassword, $fullName]);

$userId = $db->getConnection()->lastInsertId();

// Insert rider profile
$db->query("
    INSERT INTO riders (user_id, name, phone, vehicle_type, status)
    VALUES (?, ?, ?, 'motorcycle', 'available')
", [$userId, $fullName, $phone]);

echo "Rider created successfully!\n";
echo "Username: $username\n";
echo "Password: $password\n";
?>
```

## Mobile App Experience

### Add to Home Screen (PWA)

**iOS (Safari):**
1. Open `rider-login.php` in Safari
2. Tap Share button
3. Tap "Add to Home Screen"
4. Tap "Add"
5. App icon appears on home screen

**Android (Chrome):**
1. Open `rider-login.php` in Chrome
2. Tap menu (3 dots)
3. Tap "Add to Home Screen"
4. Tap "Add"
5. App icon appears on home screen

**Benefits:**
- Quick access from home screen
- Looks like native app
- No app store required
- Instant updates

## Security Features

### Rate Limiting
- **5 attempts** per 15 minutes per IP
- Prevents brute force attacks
- Shows remaining attempts
- Auto-resets on success

### Role Validation
- Rider login page only allows 'rider' role
- Other roles redirected with error
- Prevents unauthorized access

### CSRF Protection
- All forms include CSRF tokens
- Validates on submission
- Prevents cross-site attacks

### Session Security
- Secure session handling
- Auto-logout on inactivity
- Session hijacking prevention

## Troubleshooting

### Issue: "This login is for riders only"
**Cause:** User account doesn't have 'rider' role
**Solution:** 
```sql
UPDATE users SET role = 'rider' WHERE username = 'rider1';
```

### Issue: Rider redirects to wrong page
**Cause:** `bootstrap.php` not updated
**Solution:** Verify line 91 has: `'rider' => '/rider-portal.php'`

### Issue: Can't login - "Invalid username or password"
**Cause:** Incorrect credentials or inactive account
**Solution:**
```sql
-- Check user exists and is active
SELECT username, role, active FROM users WHERE username = 'rider1';

-- Activate account if needed
UPDATE users SET active = 1 WHERE username = 'rider1';

-- Reset password
UPDATE users 
SET password = '$2y$10$...' -- Use password_hash() 
WHERE username = 'rider1';
```

### Issue: Rate limited
**Cause:** Too many failed login attempts
**Solution:** Wait 15 minutes or clear rate limit:
```php
<?php
require_once 'includes/bootstrap.php';
require_once 'includes/RateLimiter.php';

$rateLimiter = new RateLimiter(5, 15);
$rateLimiter->clear('rider_login:' . $_SERVER['REMOTE_ADDR']);
echo "Rate limit cleared";
?>
```

## User Experience Flow

### First Time Login
```
1. Rider receives credentials from admin
2. Opens rider-login.php on phone
3. Enters username & password
4. Clicks "Sign In"
5. Redirected to rider-portal.php
6. Prompted to enable GPS
7. Starts receiving deliveries
```

### Daily Login
```
1. Tap app icon on home screen (if installed)
2. Auto-opens to login page
3. Enter credentials (may be saved)
4. Instant access to portal
5. One-tap to start tracking
```

## Admin Tasks

### View All Riders
```sql
SELECT 
    u.username,
    u.full_name,
    u.active,
    r.phone,
    r.vehicle_type,
    r.status,
    r.total_deliveries
FROM users u
JOIN riders r ON u.id = r.user_id
WHERE u.role = 'rider'
ORDER BY u.full_name;
```

### Reset Rider Password
```sql
-- Generate new hash in PHP:
-- password_hash('NewPassword123', PASSWORD_DEFAULT)

UPDATE users 
SET password = '$2y$10$...' 
WHERE username = 'rider1';
```

### Deactivate Rider
```sql
UPDATE users SET active = 0 WHERE username = 'rider1';
UPDATE riders SET status = 'offline' WHERE user_id = (
    SELECT id FROM users WHERE username = 'rider1'
);
```

## Testing Checklist

- [ ] Rider can access `rider-login.php`
- [ ] Login form displays correctly on mobile
- [ ] Valid credentials allow login
- [ ] Invalid credentials show error
- [ ] Rate limiting works after 5 failed attempts
- [ ] Rider redirects to `rider-portal.php` after login
- [ ] Non-rider roles get error message
- [ ] Standard login also works for riders
- [ ] PWA install prompt appears (mobile)
- [ ] Add to home screen works
- [ ] Session persists across page loads
- [ ] Logout works correctly

## URLs Summary

| Purpose | URL | Users |
|---------|-----|-------|
| Rider Login (Mobile) | `/rider-login.php` | Riders only |
| Standard Login | `/login.php` | All users |
| Rider Portal | `/rider-portal.php` | Logged-in riders |
| Admin Tracking | `/enhanced-delivery-tracking.php` | Admins |

## QR Code for Riders

Generate a QR code for easy mobile access:

**Online Generator:**
```
https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=https://yourdomain.com/rider-login.php
```

**Print and Display:**
- At dispatch area
- In rider break room
- On rider information board
- In onboarding materials

---

**Quick Start:** Share `rider-login.php` URL with riders → They login → Auto-redirect to portal → Start tracking! 🚴
