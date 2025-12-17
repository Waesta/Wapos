# Delivery Dispatch System Upgrade

## Overview
Intelligent rider assignment system using Google Routes API for traffic-aware dispatch optimization.

---

## What's New

### 1. DeliveryDispatchService.php
**Location:** `app/Services/DeliveryDispatchService.php`

**Features:**
- ✅ **Intelligent Rider Selection** - Finds optimal rider based on traffic-aware duration
- ✅ **Capacity Management** - Considers rider workload (max 3 concurrent deliveries)
- ✅ **GPS Location Tracking** - Uses real-time rider positions when available
- ✅ **Scoring Algorithm** - Ranks riders by duration, capacity, GPS freshness
- ✅ **Auto-Assignment** - One-click automatic rider assignment
- ✅ **Address Validation** - Checks if delivery location is routable
- ✅ **Rider Availability** - Real-time capacity monitoring

**Key Methods:**
```php
// Find best rider for delivery
$result = $dispatchService->findOptimalRider($lat, $lng, $options);

// Auto-assign delivery
$result = $dispatchService->autoAssignDelivery($deliveryId);

// Check rider availability
$riders = $dispatchService->getRiderAvailability();

// Validate address is routable
$validation = $dispatchService->validateDeliveryAddress($lat, $lng);
```

---

### 2. Dispatch API Endpoint
**Location:** `api/delivery-dispatch.php`

**Endpoints:**
- `POST /api/delivery-dispatch.php?action=find_optimal_rider` - Find best rider
- `POST /api/delivery-dispatch.php?action=auto_assign` - Auto-assign delivery
- `GET /api/delivery-dispatch.php?action=rider_availability` - Get rider status
- `POST /api/delivery-dispatch.php?action=validate_address` - Check if routable
- `GET /api/delivery-dispatch.php?action=dispatch_analytics` - Performance metrics

**Example Usage:**
```javascript
// Find optimal rider
fetch('/api/delivery-dispatch.php?action=find_optimal_rider', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        delivery_lat: -1.2921,
        delivery_lng: 36.8219,
        priority: 'high'
    })
})
.then(r => r.json())
.then(data => {
    console.log('Optimal rider:', data.data.optimal_rider);
    console.log('Alternatives:', data.data.alternatives);
});

// Auto-assign delivery
fetch('/api/delivery-dispatch.php?action=auto_assign', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        delivery_id: 123,
        priority: 'normal'
    })
})
.then(r => r.json())
.then(data => {
    if (data.success) {
        alert('Assigned to: ' + data.assigned_rider.rider_name);
    }
});
```

---

### 3. Database Migration
**Location:** `database/migrations/018_delivery_dispatch_log.sql`

**Changes:**
- ✅ New table: `delivery_dispatch_log` - Tracks all dispatch decisions
- ✅ New column: `riders.max_active_deliveries` - Rider capacity setting
- ✅ Indexes for performance optimization
- ✅ Foreign keys for data integrity

**Run Migration:**
```sql
SOURCE database/migrations/018_delivery_dispatch_log.sql;
```

---

## How It Works

### Dispatch Algorithm

1. **Get Active Riders**
   - Query riders with `is_active = 1`
   - Filter by current capacity (< max_active_deliveries)
   - Order by workload (least busy first)

2. **Calculate Routes**
   - For each rider, compute route to delivery destination
   - Use rider's GPS location if available (< 5 min old)
   - Fallback to business location if no GPS
   - Use `TRAFFIC_AWARE_OPTIMAL` routing preference

3. **Score Riders**
   ```
   Score = Duration (minutes)
         + (Current Deliveries × 5 minutes penalty)
         + (No GPS location × 10 minutes penalty)
         + (Stale GPS > 5 min × 5 minutes penalty)
         - (High priority × 20% discount)
   ```

4. **Select Optimal**
   - Rider with lowest score wins
   - Return top 3 alternatives
   - Log decision for analytics

---

## Integration Guide

### Option 1: Add Auto-Assign Button to UI

**In `enhanced-delivery-tracking.php` or similar:**

```html
<!-- Add button to delivery card -->
<button class="btn btn-sm btn-success" 
        onclick="autoAssignDelivery(<?= $delivery['id'] ?>)">
    <i class="bi bi-lightning-fill"></i> Auto-Assign
</button>

<script>
async function autoAssignDelivery(deliveryId) {
    try {
        const response = await fetch('/api/delivery-dispatch.php?action=auto_assign', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ delivery_id: deliveryId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(`✅ Assigned to ${result.assigned_rider.rider_name}\n` +
                  `ETA: ${result.estimated_duration} minutes\n` +
                  `Distance: ${result.assigned_rider.distance_km} km`);
            location.reload();
        } else {
            alert('❌ ' + result.error);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}
</script>
```

### Option 2: Show Rider Suggestions Modal

```html
<!-- Modal to show rider options -->
<div class="modal fade" id="riderSuggestionsModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Suggested Riders</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="riderSuggestions">
                <!-- Populated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<script>
async function showRiderSuggestions(deliveryLat, deliveryLng) {
    const response = await fetch('/api/delivery-dispatch.php?action=find_optimal_rider', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            delivery_lat: deliveryLat,
            delivery_lng: deliveryLng
        })
    });
    
    const result = await response.json();
    const optimal = result.data.optimal_rider;
    const alternatives = result.data.alternatives;
    
    let html = `
        <div class="alert alert-success">
            <strong>🏆 Recommended:</strong> ${optimal.rider_name}<br>
            <small>
                ${optimal.duration_minutes} min · ${optimal.distance_km} km · 
                ${optimal.current_deliveries}/${optimal.max_capacity} active
            </small>
        </div>
    `;
    
    if (alternatives.length > 0) {
        html += '<h6>Alternatives:</h6><div class="list-group">';
        alternatives.forEach(rider => {
            html += `
                <div class="list-group-item">
                    <strong>${rider.rider_name}</strong><br>
                    <small>${rider.duration_minutes} min · ${rider.distance_km} km</small>
                </div>
            `;
        });
        html += '</div>';
    }
    
    document.getElementById('riderSuggestions').innerHTML = html;
    new bootstrap.Modal(document.getElementById('riderSuggestionsModal')).show();
}
</script>
```

---

## Configuration

### Rider Capacity Settings

**In `delivery.php` or rider management:**
```sql
-- Set individual rider capacity
UPDATE riders SET max_active_deliveries = 5 WHERE id = 1;

-- Set default for all riders
UPDATE riders SET max_active_deliveries = 3;
```

### Dispatch Options

```php
$options = [
    'priority' => 'high',              // 'normal' or 'high'
    'max_active_deliveries' => 3,      // Max concurrent deliveries per rider
    'max_distance_km' => 50            // Maximum service radius
];

$result = $dispatchService->findOptimalRider($lat, $lng, $options);
```

---

## Error Handling

### No Riders Available
```json
{
    "success": false,
    "error": "no_riders_available",
    "code": 503,
    "requires_manual_assignment": true
}
```

### Route Calculation Failed
```json
{
    "success": false,
    "error": "route_calculation_failed",
    "code": 502
}
```

### Un-routable Address
```json
{
    "routable": false,
    "error": "No route found",
    "requires_manual_review": true
}
```

---

## Analytics & Monitoring

### Dispatch Performance
```sql
-- View dispatch decisions
SELECT 
    d.order_number,
    r.name as rider_name,
    ddl.duration_seconds / 60 as duration_minutes,
    ddl.distance_meters / 1000 as distance_km,
    ddl.candidates_evaluated,
    ddl.selection_score,
    ddl.created_at
FROM delivery_dispatch_log ddl
JOIN deliveries d ON ddl.delivery_id = d.id
JOIN riders r ON ddl.selected_rider_id = r.id
ORDER BY ddl.created_at DESC
LIMIT 50;

-- Average dispatch metrics
SELECT 
    AVG(duration_seconds / 60) as avg_duration_min,
    AVG(distance_meters / 1000) as avg_distance_km,
    AVG(candidates_evaluated) as avg_candidates,
    COUNT(*) as total_dispatches
FROM delivery_dispatch_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);
```

---

## Testing Checklist

### Unit Tests
- [ ] Test with 0 active riders (should throw exception)
- [ ] Test with 1 rider (should select that rider)
- [ ] Test with multiple riders at different locations
- [ ] Test with riders at capacity (should exclude them)
- [ ] Test with invalid coordinates (should handle gracefully)
- [ ] Test with no GPS data (should use business location)

### Integration Tests
- [ ] Create test delivery with coordinates
- [ ] Call auto-assign API
- [ ] Verify rider assigned correctly
- [ ] Check dispatch_log entry created
- [ ] Verify estimated_delivery_time set
- [ ] Test with high priority flag

### Performance Tests
- [ ] Test with 10+ active riders
- [ ] Measure API response time (should be < 3 seconds)
- [ ] Verify caching reduces API calls
- [ ] Check database query performance

---

## Deployment Steps

1. **Upload Files**
   ```
   app/Services/DeliveryDispatchService.php
   api/delivery-dispatch.php
   database/migrations/018_delivery_dispatch_log.sql
   ```

2. **Run Migration**
   ```bash
   mysql -u username -p database_name < database/migrations/018_delivery_dispatch_log.sql
   ```

3. **Update Composer Autoload** (if needed)
   ```bash
   composer dump-autoload
   ```

4. **Test API Endpoint**
   ```bash
   curl -X GET "https://yourdomain.com/api/delivery-dispatch.php?action=rider_availability"
   ```

5. **Integrate into UI**
   - Add auto-assign buttons
   - Add rider suggestions modal
   - Update delivery assignment workflow

---

## Benefits

### For Dispatchers
- ✅ **Faster Assignment** - One-click auto-assign vs manual selection
- ✅ **Better Decisions** - Traffic-aware routing vs guesswork
- ✅ **Load Balancing** - Automatic capacity management
- ✅ **Transparency** - See why each rider was selected

### For Riders
- ✅ **Fair Distribution** - Workload evenly distributed
- ✅ **Optimal Routes** - Shortest duration assignments
- ✅ **Capacity Respect** - Won't be overloaded

### For Customers
- ✅ **Faster Delivery** - Optimal rider selection
- ✅ **Accurate ETAs** - Traffic-aware calculations
- ✅ **Better Service** - Right rider for the job

---

## Future Enhancements

### Phase 2 (Optional)
- [ ] Multi-delivery route optimization
- [ ] Rider skill/rating consideration
- [ ] Vehicle type matching (bike vs car)
- [ ] Time-of-day optimization
- [ ] Machine learning for better predictions
- [ ] Real-time traffic incident avoidance

---

**Status:** ✅ Ready for Production  
**Version:** 1.0  
**Date:** December 17, 2025  
**Module:** Delivery & Logistics
