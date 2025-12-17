# Google Maps API Security Configuration

## Overview
WAPOS uses three separate Google Maps API keys for enhanced security and proper separation of concerns.

---

## API Keys Configuration

### 1. Client-Side Key (Frontend)
**Setting Key:** `google_maps_api_key`  
**Used For:**
- Maps JavaScript API (map rendering)
- Places Autocomplete (address suggestions in UI)
- Frontend map displays

**Security Restrictions Required:**
1. Go to [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Select this API key
3. Under "Application restrictions" → Choose **HTTP referrers**
4. Add your domains:
   ```
   https://yourdomain.com/*
   https://www.yourdomain.com/*
   http://localhost/* (for development)
   ```
5. Under "API restrictions" → Restrict to:
   - Maps JavaScript API
   - Places API (New)

---

### 2. Places API Key (Frontend)
**Setting Key:** `google_places_api_key`  
**Used For:**
- Places Autocomplete API (address validation)
- Place Details API (get coordinates from Place ID)

**Security Restrictions Required:**
1. Go to [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Select this API key
3. Under "Application restrictions" → Choose **HTTP referrers**
4. Add your domains:
   ```
   https://yourdomain.com/*
   https://www.yourdomain.com/*
   http://localhost/* (for development)
   ```
5. Under "API restrictions" → Restrict to:
   - Places API (New)
   - Geocoding API

---

### 3. Routes API Key (Backend/Server-Side)
**Setting Key:** `google_routes_api_key`  
**Used For:**
- Routes API (New) - route calculations
- Distance/duration calculations
- Traffic-aware routing
- Delivery dispatch optimization

**Security Restrictions Required:**
1. Go to [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Select this API key
3. Under "Application restrictions" → Choose **IP addresses**
4. Add your server IP addresses:
   ```
   XXX.XXX.XXX.XXX (Production server IP)
   YYY.YYY.YYY.YYY (Staging server IP - if applicable)
   127.0.0.1 (Local development)
   ```
5. Under "API restrictions" → Restrict to:
   - Routes API
   - Geocoding API

**CRITICAL:** This key should **NEVER** be exposed to frontend JavaScript!

---

## Implementation Checklist

### ✅ Current Implementation Status

- [x] Three separate API keys configured
- [x] GoogleMapsService.php uses Routes API (New)
- [x] Server-side route calculations
- [x] Client-side map rendering
- [x] Places Autocomplete integration
- [ ] HTTP Referrer restrictions applied
- [ ] Server IP restrictions applied
- [ ] API restrictions configured

### 🔒 Security Best Practices

1. **Never expose server-side keys to frontend**
   - Routes API key stays in PHP backend only
   - Only Maps JS and Places keys sent to browser

2. **Rotate keys periodically**
   - Change keys every 6-12 months
   - Update in settings table

3. **Monitor API usage**
   - Set up billing alerts in Google Cloud
   - Monitor for unusual spikes
   - Review API quotas regularly

4. **Use environment-specific keys**
   - Development keys for localhost
   - Production keys for live site
   - Staging keys for testing environment

---

## How to Apply Restrictions

### Step 1: Get Your Server IP
```bash
# On your production server, run:
curl ifconfig.me
# Or
curl icanhazip.com
```

### Step 2: Configure in Google Cloud Console

1. Visit: https://console.cloud.google.com/apis/credentials
2. Click on each API key
3. Apply restrictions as documented above
4. Click "Save"

### Step 3: Test After Applying Restrictions

```bash
# Test from your server (should work)
curl "https://routes.googleapis.com/directions/v2:computeRoutes" \
  -H "X-Goog-Api-Key: YOUR_ROUTES_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{...}'

# Test from unauthorized IP (should fail with 403)
```

---

## API Endpoints Used

### Routes API (Server-Side Only)
```
https://routes.googleapis.com/directions/v2:computeRoutes
```

### Places API (Client-Side)
```
https://places.googleapis.com/v1/places:autocomplete
https://places.googleapis.com/v1/places/{PLACE_ID}
```

### Geocoding API (Both)
```
https://maps.googleapis.com/maps/api/geocode/json
```

### Maps JavaScript API (Client-Side)
```
https://maps.googleapis.com/maps/api/js
```

---

## Cost Optimization

### Caching Strategy
- **Hard Cache:** 1440 minutes (24 hours)
- **Soft Cache:** 180 minutes (3 hours)
- Reduces API calls by ~80%

### Request Batching
- Multiple deliveries optimized in single request
- Waypoint optimization for multi-stop routes

### Fallback Mechanisms
- Haversine distance calculation when API unavailable
- Cached data used when fresh data fails

---

## Troubleshooting

### Error: "This API key is not authorized"
**Cause:** IP/Referrer restrictions blocking request  
**Solution:** Check restrictions match your server IP or domain

### Error: "API key not valid"
**Cause:** Key not enabled for the API  
**Solution:** Enable required APIs in Google Cloud Console

### Error: "Quota exceeded"
**Cause:** Daily/monthly quota limit reached  
**Solution:** Increase quota or optimize caching

---

## Production Deployment

### Before Going Live:

1. ✅ Apply all API key restrictions
2. ✅ Test from production server IP
3. ✅ Test from production domain
4. ✅ Set up billing alerts
5. ✅ Enable API quotas
6. ✅ Monitor first week closely

### Settings to Configure:

```sql
-- In settings table
UPDATE settings SET setting_value = 'YOUR_MAPS_JS_KEY' WHERE setting_key = 'google_maps_api_key';
UPDATE settings SET setting_value = 'YOUR_PLACES_KEY' WHERE setting_key = 'google_places_api_key';
UPDATE settings SET setting_value = 'YOUR_ROUTES_KEY' WHERE setting_key = 'google_routes_api_key';
```

---

## Support & Documentation

- [Google Maps Platform Documentation](https://developers.google.com/maps/documentation)
- [Routes API (New) Guide](https://developers.google.com/maps/documentation/routes)
- [Places API (New) Guide](https://developers.google.com/maps/documentation/places/web-service/overview)
- [API Key Best Practices](https://developers.google.com/maps/api-security-best-practices)

---

**Last Updated:** December 17, 2025  
**WAPOS Version:** 2.0+  
**Module:** Delivery & Logistics
