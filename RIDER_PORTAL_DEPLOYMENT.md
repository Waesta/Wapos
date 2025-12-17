# Rider Portal - Deployment Guide

## Overview
Complete web-based rider portal with GPS tracking, real-time location updates, and delivery management interface.

## Features Implemented

### 1. GPS Tracking
- **Real-time location tracking** using browser Geolocation API
- **Automatic updates** every 30 seconds to server
- **High accuracy mode** enabled for precise positioning
- **Speed and heading** tracking for better route visualization
- **Location history** stored in database for analytics

### 2. Delivery Management
- **Active deliveries list** with customer details
- **Status updates** (Assigned → Picked Up → In Transit → Delivered)
- **Proof of delivery** with photo upload
- **Delivery notes** for special instructions
- **Location capture** at each status change

### 3. Interactive Map
- **Google Maps integration** showing rider location
- **Delivery markers** for all active deliveries
- **Real-time position updates** as rider moves
- **Navigation support** - opens Google Maps for turn-by-turn directions

### 4. Performance Dashboard
- **Active deliveries count**
- **Completed deliveries today**
- **Current speed** in km/h
- **GPS status indicator**

## Files Created/Modified

### New Files
1. **`rider-portal.php`** - Main rider interface (22 KB)
   - GPS tracking UI
   - Delivery management
   - Interactive map
   - Status updates

### Modified Files
1. **`api/update-delivery-status.php`** - Enhanced with:
   - Photo upload support
   - Location tracking
   - Support for delivery_id parameter
   - Multipart form data handling

### Existing Files (Already Present)
1. **`api/update-rider-location.php`** - Location update endpoint
2. **`app/Services/DeliveryTrackingService.php`** - Tracking service

## Database Tables Used

### `riders` table
```sql
- id
- user_id
- name
- phone
- vehicle_type
- vehicle_number
- current_latitude
- current_longitude
- location_accuracy
- current_speed
- current_heading
- last_location_update
- status (available/busy/offline)
- total_deliveries
- last_delivery_at
```

### `rider_location_history` table
```sql
- id
- rider_id
- latitude
- longitude
- accuracy
- speed
- heading
- recorded_at
```

### `delivery_status_history` table
```sql
- id
- delivery_id
- status
- notes
- photo_url
- latitude
- longitude
- user_id
- created_at
```

### `deliveries` table
```sql
- id
- order_id
- rider_id
- status
- delivery_latitude
- delivery_longitude
- estimated_delivery_time
- picked_up_at
- in_transit_at
- actual_delivery_time
```

## Deployment Steps

### Step 1: Upload Files

Upload to production server:
```
rider-portal.php
api/update-delivery-status.php (replace existing)
```

### Step 2: Create Upload Directory

Create directory for delivery proof photos:
```bash
mkdir -p uploads/delivery-proofs
chmod 755 uploads/delivery-proofs
```

### Step 3: Configure Rider Accounts

For each rider, ensure they have:
1. User account with role = 'rider'
2. Entry in `riders` table linked to user_id
3. Active status

**SQL to create rider:**
```sql
-- Create user account
INSERT INTO users (username, password, full_name, email, role, active)
VALUES ('rider1', '$2y$10$...', 'John Rider', 'rider1@example.com', 'rider', 1);

-- Create rider profile
INSERT INTO riders (user_id, name, phone, vehicle_type, vehicle_number, status)
VALUES (LAST_INSERT_ID(), 'John Rider', '+254712345678', 'motorcycle', 'KAA 123A', 'available');
```

### Step 4: Test GPS Tracking

1. Login as rider
2. Go to `rider-portal.php`
3. Click "Start Tracking"
4. Grant location permissions
5. Verify location updates in database

**Check location updates:**
```sql
SELECT * FROM rider_location_history 
WHERE rider_id = ? 
ORDER BY recorded_at DESC 
LIMIT 10;
```

### Step 5: Test Delivery Workflow

1. **Assign delivery** to rider from admin panel
2. **Rider logs in** to portal
3. **Starts GPS tracking**
4. **Updates status**: Assigned → Picked Up → In Transit
5. **Marks delivered** with photo and notes
6. **Verify** delivery marked complete in orders

## How It Works

### GPS Tracking Flow

```
1. Rider clicks "Start Tracking"
   ↓
2. Browser requests location permission
   ↓
3. Geolocation API starts watching position
   ↓
4. Every position update:
   - Updates map marker
   - Shows current speed
   - Displays accuracy
   ↓
5. Every 30 seconds:
   - Sends location to server
   - Server updates riders table
   - Server logs to rider_location_history
   - Server recalculates ETAs for active deliveries
```

### Delivery Status Flow

```
ASSIGNED (new delivery)
   ↓
PICKED-UP (rider collects order)
   ↓
IN-TRANSIT (rider heading to customer)
   ↓
DELIVERED (completed with proof)
```

### Location Update API

**Endpoint:** `POST /api/update-rider-location.php`

**Request:**
```json
{
  "rider_id": 123,
  "latitude": -1.286389,
  "longitude": 36.817223,
  "accuracy": 10.5,
  "speed": 8.3,
  "heading": 180
}
```

**Response:**
```json
{
  "success": true,
  "message": "Location updated successfully",
  "updated_deliveries": 2,
  "deliveries": [
    {
      "delivery_id": 45,
      "order_id": 123,
      "estimated_delivery_time": "2024-12-17 11:30:00"
    }
  ]
}
```

### Delivery Status Update API

**Endpoint:** `POST /api/update-delivery-status.php`

**Request (JSON):**
```json
{
  "delivery_id": 45,
  "status": "picked-up",
  "latitude": -1.286389,
  "longitude": 36.817223
}
```

**Request (with photo - multipart/form-data):**
```
delivery_id: 45
status: delivered
notes: Left at front door
latitude: -1.292066
longitude: 36.821945
photo: [file]
```

**Response:**
```json
{
  "success": true,
  "message": "Delivery status updated successfully"
}
```

## Security Features

### 1. Authentication Required
- All API endpoints require login
- Riders can only update their own deliveries
- Session-based authentication

### 2. Location Privacy
- Location only shared with assigned deliveries
- Historical data archived after 30 days
- No public access to rider locations

### 3. Photo Upload Security
- File type validation (images only)
- Unique filenames to prevent overwriting
- Stored in protected directory
- File size limits enforced

### 4. GPS Accuracy
- High accuracy mode enabled
- Accuracy value recorded with each update
- Stale location data flagged

## Mobile Optimization

The portal is fully responsive and mobile-optimized:

- **Touch-friendly buttons** (min 44px tap targets)
- **Responsive layout** adapts to screen size
- **Optimized map** for mobile viewing
- **Camera integration** for proof photos
- **Minimal data usage** with 30s update interval
- **Works offline** (with limitations)

## Browser Requirements

### Supported Browsers
- ✅ Chrome 50+ (Android/Desktop)
- ✅ Safari 10+ (iOS/macOS)
- ✅ Firefox 55+
- ✅ Edge 79+

### Required Features
- Geolocation API
- File upload (camera access)
- LocalStorage
- Fetch API

## Troubleshooting

### Issue: "Location permission denied"
**Solution:** 
- User must grant location permission in browser
- Check browser settings → Site permissions
- On iOS: Settings → Safari → Location Services

### Issue: GPS not accurate
**Solution:**
- Ensure high accuracy mode enabled
- Check device GPS settings
- Move to area with clear sky view
- Wait for GPS to acquire satellites

### Issue: Location updates not saving
**Solution:**
- Check internet connection
- Verify rider_id is correct
- Check server error logs
- Ensure database tables exist

### Issue: Photo upload fails
**Solution:**
- Check uploads/delivery-proofs directory exists
- Verify directory permissions (755)
- Check file size limits in php.ini
- Ensure disk space available

### Issue: Map not loading
**Solution:**
- Verify Google Maps API key configured
- Check API key has Maps JavaScript API enabled
- Check browser console for errors
- Verify API key restrictions allow domain

## Performance Optimization

### Location Update Frequency
- **Default:** 30 seconds
- **Adjust in code:** Change `updateInterval` value
- **Recommendation:** 30-60s for battery life

### Data Usage
- **Per update:** ~500 bytes
- **Per hour:** ~60 KB (at 30s intervals)
- **Per day (8 hours):** ~480 KB

### Battery Impact
- **GPS tracking:** Moderate impact
- **Background updates:** Minimal impact
- **Recommendation:** Riders should have charger

## Admin Monitoring

Admins can monitor riders from:
1. **`enhanced-delivery-tracking.php`** - Real-time map view
2. **`delivery.php`** - Rider management
3. **Database queries** - Historical data

**View rider locations:**
```sql
SELECT 
    r.name,
    r.current_latitude,
    r.current_longitude,
    r.current_speed,
    r.last_location_update,
    r.status
FROM riders r
WHERE r.status = 'busy'
ORDER BY r.last_location_update DESC;
```

## Future Enhancements

Potential improvements:
1. **Offline mode** - Queue updates when offline
2. **Push notifications** - New delivery alerts
3. **Voice navigation** - Turn-by-turn audio
4. **Earnings tracker** - Daily/weekly earnings
5. **Route history** - Replay past deliveries
6. **Performance metrics** - Speed, efficiency scores
7. **Chat support** - In-app customer communication

## Testing Checklist

- [ ] Rider can login to portal
- [ ] GPS tracking starts successfully
- [ ] Location updates every 30 seconds
- [ ] Map shows rider location
- [ ] Delivery markers appear on map
- [ ] Status updates work (all transitions)
- [ ] Photo upload works
- [ ] Delivery notes save correctly
- [ ] Navigation opens Google Maps
- [ ] Completed count increments
- [ ] Admin sees rider on tracking dashboard
- [ ] Location history records properly

## Support

For issues:
1. Check browser console for JavaScript errors
2. Check server error logs: `logs/php_errors.log`
3. Verify database tables exist
4. Test API endpoints with Postman
5. Check Google Maps API quota

---

**Deployment Date:** _____________
**Deployed By:** _____________
**Tested By:** _____________
**Production URL:** https://yourdomain.com/rider-portal.php
