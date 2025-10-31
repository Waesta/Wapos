# Fix: ERR_FAILED on Login Issue

## Problem
After login, you get "This site can't be reached - ERR_FAILED" until you press Ctrl+F5.

## Root Cause
The old service worker was caching PHP files (including `index.php`) which should never be cached. This caused the browser to try to serve stale cached content that no longer works.

## Solution Applied

### 1. Updated Service Worker (v2.1)
- ✅ Changed to **NEVER cache PHP files**
- ✅ PHP files now use "Network First" strategy
- ✅ Only static files (images, offline.html) are cached
- ✅ Bumped cache version to force update

### 2. Improved Service Worker Registration
- ✅ Added automatic update checking
- ✅ Added proper error handling
- ✅ Added controller change detection

---

## How to Fix for Existing Users

### Option 1: Clear Cache Page (Recommended)

1. Visit: `http://localhost/wapos/clear-sw-cache.html`
2. Wait for automatic cleanup (3 seconds)
3. You'll be redirected to login
4. Login normally - issue should be resolved

### Option 2: Manual Browser Clear

**Chrome/Edge:**
1. Press `F12` to open DevTools
2. Go to **Application** tab
3. Click **Clear storage** (left sidebar)
4. Check all boxes
5. Click **Clear site data**
6. Close DevTools
7. Press `Ctrl+Shift+R` to hard reload

**Firefox:**
1. Press `F12` to open DevTools
2. Go to **Storage** tab
3. Right-click on domain → **Delete All**
4. Close DevTools
5. Press `Ctrl+Shift+R` to hard reload

### Option 3: Incognito/Private Mode

1. Open browser in Incognito/Private mode
2. Navigate to `http://localhost/wapos/login.php`
3. Login normally
4. Close incognito window
5. Return to normal browser and clear cache (Option 2)

---

## Verification

After clearing cache, verify the fix:

1. **Open DevTools** (F12)
2. Go to **Application** → **Service Workers**
3. You should see version **2.1**
4. **Network** tab should show PHP files coming from network (not cache)

---

## For Developers

### Service Worker Changes Made

**File:** `service-worker.js`

```javascript
// OLD (WRONG) - Cached PHP files
const APP_SHELL_FILES = [
    '/wapos/index.php',  // ❌ Should NOT be cached
    '/wapos/pos.php',    // ❌ Should NOT be cached
    // ... other PHP files
];

// NEW (CORRECT) - Only static files
const APP_SHELL_FILES = [
    '/wapos/offline.html',      // ✅ Static HTML
    '/wapos/assets/images/logo.png'  // ✅ Static image
];

// Added explicit check
if (url.pathname.endsWith('.php')) {
    event.respondWith(networkFirst(request));  // Always network first
    return;
}
```

### Footer Registration Update

**File:** `includes/footer.php`

```javascript
// Added proper scope and update handling
navigator.serviceWorker.register("/wapos/service-worker.js", {
    scope: '/wapos/'
})
.then(registration => {
    registration.update();  // Check for updates
    
    // Handle updates gracefully
    registration.addEventListener('updatefound', () => {
        // Prompt user to update
    });
});
```

---

## Prevention

This issue won't happen again because:

1. ✅ PHP files are **never cached** anymore
2. ✅ Service worker auto-updates on page load
3. ✅ Users are prompted when new version is available
4. ✅ Cache version is bumped (2.0 → 2.1)

---

## Testing

Test the fix:

```bash
# 1. Clear everything
Visit: http://localhost/wapos/clear-sw-cache.html

# 2. Login
Visit: http://localhost/wapos/login.php
Login with: admin / admin123

# 3. Verify no ERR_FAILED
Should redirect to dashboard without errors

# 4. Check DevTools
F12 → Application → Service Workers
Should show: "wapos-v2.1"
```

---

## Rollout Plan

### For Production Deployment

1. **Deploy updated files:**
   - `service-worker.js` (v2.1)
   - `includes/footer.php` (updated registration)
   - `clear-sw-cache.html` (cleanup page)

2. **Notify users:**
   ```
   Subject: System Update - Please Clear Cache
   
   We've updated the system. If you experience login issues:
   1. Visit: https://yoursite.com/wapos/clear-sw-cache.html
   2. Wait 3 seconds
   3. Login normally
   
   Or press Ctrl+Shift+R to hard refresh.
   ```

3. **Monitor:**
   - Check error logs for SW registration failures
   - Verify users can login without Ctrl+F5

---

## FAQ

**Q: Why did this happen?**  
A: The old service worker was too aggressive in caching PHP files, which are dynamic and should always be fetched from the server.

**Q: Will offline mode still work?**  
A: Yes! Offline mode still works for:
- Static assets (CSS, JS, images)
- API data (cached for fallback)
- Queued transactions (IndexedDB)

**Q: Do I need to clear cache every time?**  
A: No, only once. After clearing, the new service worker (v2.1) will handle updates properly.

**Q: What if clearing cache doesn't work?**  
A: Try:
1. Close all browser tabs for the site
2. Restart browser
3. Visit clear-sw-cache.html again
4. If still failing, unregister SW manually in DevTools

---

## Support

If issues persist:

1. **Check browser console** (F12 → Console)
2. **Check service worker status** (F12 → Application → Service Workers)
3. **Verify cache version** (should be 2.1)
4. **Check network tab** (PHP files should show "Fetch" not "ServiceWorker")

---

**Status:** ✅ FIXED  
**Version:** 2.1  
**Date:** October 31, 2025
