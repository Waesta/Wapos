# Google Maps API Integration - Deployment Guide

## Overview
This deployment adds comprehensive Google Maps integration to the WAPOS delivery module using three modern Google APIs:
- **Places API**: Address autocomplete and validation
- **Routes API**: Optimized routing, traffic awareness, and distance calculations (replaces deprecated Distance Matrix API)
- **Maps JavaScript API**: Interactive maps and geocoding

## Files Modified/Created

### New Files
1. `app/Services/GoogleMapsService.php` - Core service for all Google Maps operations
2. `api/google-maps.php` - API endpoint for frontend requests
3. `js/google-maps-delivery.js` - Frontend JavaScript library
4. `GOOGLE_MAPS_INTEGRATION_DEPLOYMENT.md` - This deployment guide

### Modified Files
1. `settings.php` - Added new API key fields (lines 71-72, 711-725)

## Deployment Steps

### Step 1: Upload Files to Production

Upload these files to your production server:

```
app/Services/GoogleMapsService.php
api/google-maps.php
js/google-maps-delivery.js
settings.php (replace existing)
```

### Step 2: Configure Google Cloud Console

1. **Go to Google Cloud Console**: https://console.cloud.google.com/

2. **Enable Required APIs**:
   - Maps JavaScript API
   - Places API (New)
   - Routes API (New - replaces Distance Matrix API)
   - Geocoding API

3. **Create/Update API Keys**:

   **Option A: Single API Key (Recommended for simplicity)**
   - Create one API key with all APIs enabled
   - Use the same key for all three settings

   **Option B: Separate API Keys (Recommended for security)**
   - Create separate keys for better quota management
   - Maps JavaScript API key: Restrict to your domain
   - Places API key: Restrict to your server IP
   - Routes API key: Restrict to your server IP

4. **Set API Restrictions**:
   - **HTTP Referrer** (for Maps JavaScript API): `https://yourdomain.com/*`
   - **IP Address** (for Places/Routes API): Your server's IP address

### Step 3: Configure WAPOS Settings

1. Log into WAPOS as admin
2. Go to **Settings** → **Delivery & Logistics**
3. Scroll to **Google Maps Configuration**
4. Enter your API keys:
   - **Google Maps JavaScript API Key**: Your Maps API key
   - **Google Places API Key**: Your Places API key (can be same as Maps)
   - **Google Routes API Key**: Your Routes API key (can be same as Maps)
5. Click **Save Settings**

### Step 4: Test Integration

#### Test 1: Address Autocomplete
1. Go to **Restaurant** → **New Order** → **Delivery**
2. Start typing an address in the delivery address field
3. Verify autocomplete suggestions appear

#### Test 2: Route Calculation
1. Go to **Delivery** → **Enhanced Tracking**
2. Verify map loads with delivery locations
3. Check that routes are displayed between riders and destinations

#### Test 3: Route Optimization
1. Assign multiple deliveries to one rider
2. System should calculate optimal route order
3. Verify ETA updates based on traffic

## Features Implemented

### 1. Address Autocomplete (Places API)
- Real-time address suggestions as user types
- Validates and standardizes addresses
- Returns precise coordinates for delivery locations

**Usage in Code**:
```javascript
const gmaps = new GoogleMapsDelivery({ mapsApiKey: 'YOUR_KEY' });
const suggestions = await gmaps.autocompleteAddress('123 Main St');
```

### 2. Route Optimization (Routes API)
- Calculates optimal delivery sequence for multiple stops
- Traffic-aware routing for accurate ETAs
- Supports up to 25 waypoints per route

**Usage in Code**:
```php
$googleMaps = new GoogleMapsService($db);
$optimized = $googleMaps->optimizeMultipleDeliveries($origin, $deliveries);
```

### 3. Interactive Maps (Maps JavaScript API)
- Real-time rider location tracking
- Visual route display on map
- Drag-and-drop location selection

**Usage in Code**:
```javascript
await gmaps.initializeMap('mapElement');
gmaps.addMarker('rider1', { lat: -1.286, lng: 36.817 });
```

## API Endpoints

### Frontend API Calls

All API calls go through `api/google-maps.php`:

```javascript
// Address autocomplete
fetch('api/google-maps.php?action=autocomplete&input=123+Main+St')

// Get place details
fetch('api/google-maps.php?action=place_details&place_id=ChIJ...')

// Compute route
fetch('api/google-maps.php?action=compute_route', {
    method: 'POST',
    body: JSON.stringify({
        origin: { lat: -1.286, lng: 36.817 },
        destination: { lat: -1.292, lng: 36.822 }
    })
})

// Optimize multiple deliveries
fetch('api/google-maps.php?action=optimize_route', {
    method: 'POST',
    body: JSON.stringify({
        origin: { lat: -1.286, lng: 36.817 },
        deliveries: [
            { lat: -1.292, lng: 36.822, id: 1 },
            { lat: -1.295, lng: 36.825, id: 2 }
        ]
    })
})
```

## Cost Management

### API Pricing (as of 2024)
- **Maps JavaScript API**: $7 per 1,000 loads
- **Places API Autocomplete**: $2.83 per 1,000 requests
- **Routes API**: $5 per 1,000 requests (replaces Distance Matrix API at $5 per 1,000)
- **Geocoding API**: $5 per 1,000 requests

**Note**: Routes API provides the same functionality as Distance Matrix API but with additional features like traffic-aware routing and route optimization.

### Cost Optimization Features
1. **Caching**: Distance calculations cached for 24 hours
2. **Fallback**: Haversine distance calculation when API unavailable
3. **Debouncing**: Autocomplete requests throttled
4. **Batch Processing**: Multiple deliveries optimized in single request

### Monthly Cost Estimate (Example)
- 1,000 deliveries/month
- 2,000 address lookups
- 1,000 route calculations

**Estimated Cost**: $20-30/month

## Troubleshooting

### Issue: "API key not configured"
**Solution**: Verify API keys are entered in Settings → Delivery & Logistics

### Issue: "This API project is not authorized to use this API"
**Solution**: Enable the required API in Google Cloud Console

### Issue: "The provided API key is invalid"
**Solution**: 
1. Check for typos in API key
2. Verify API key restrictions allow your domain/IP
3. Ensure API key has required APIs enabled

### Issue: Map not loading
**Solution**:
1. Check browser console for errors
2. Verify Maps JavaScript API is enabled
3. Check HTTP referrer restrictions

### Issue: Routes not displaying
**Solution**:
1. Verify Routes API is enabled
2. Check that coordinates are valid
3. Ensure API key has Routes API access

## Security Best Practices

1. **Never expose API keys in client-side code** (except Maps JavaScript API)
2. **Use HTTP referrer restrictions** for Maps JavaScript API
3. **Use IP restrictions** for server-side APIs (Places, Routes)
4. **Monitor API usage** in Google Cloud Console
5. **Set up billing alerts** to avoid unexpected charges
6. **Rotate API keys** periodically

## Rollback Plan

If issues occur after deployment:

1. **Immediate**: System continues to work with fallback Haversine distance calculations
2. **Rollback**: Replace `settings.php` with backup
3. **Remove**: Delete new files if needed (system will use fallback calculations)

## Support

For issues or questions:
1. Check Google Maps Platform documentation: https://developers.google.com/maps
2. Review API usage in Google Cloud Console
3. Check WAPOS error logs in `logs/php_errors.log`

## Next Steps

After successful deployment:
1. Monitor API usage for first week
2. Adjust caching settings if needed
3. Train staff on new address autocomplete features
4. Review delivery route optimization results

---

**Deployment Date**: _____________
**Deployed By**: _____________
**Production URL**: _____________
**API Key Created**: _____________
