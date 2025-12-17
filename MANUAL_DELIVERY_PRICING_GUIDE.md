# Manual Delivery Pricing Mode

## Overview
Manual delivery pricing mode allows WAPOS to operate without Google Maps API subscription, enabling clients to set delivery fees manually based on their own pricing structure.

---

## When to Use Manual Mode

### ✅ **Use Manual Mode If:**
- Client doesn't want to pay for Google Maps API subscription
- Operating in areas with poor GPS/mapping coverage
- Simple flat-rate delivery pricing preferred
- Distance-based pricing calculated offline
- Budget constraints on API costs

### ❌ **Use API Mode If:**
- Need accurate traffic-aware ETAs
- Want automatic distance calculation
- Require route optimization
- Multiple delivery zones with complex pricing
- Real-time route tracking needed

---

## How It Works

### **Manual Mode Enabled**
1. Admin enters delivery fee manually during order creation
2. System skips Google Maps API calls
3. No distance/duration calculation
4. Fee is stored as entered
5. Rider assignment uses straight-line distance (Haversine)

### **API Mode (Default)**
1. System calculates distance using Google Routes API
2. Applies pricing rules based on distance
3. Considers traffic for accurate ETAs
4. Rider assignment uses traffic-aware routing
5. Full route tracking available

---

## Configuration

### **Enable Manual Pricing Mode**

**Option 1: Via Settings Table**
```sql
UPDATE settings 
SET setting_value = '1' 
WHERE setting_key = 'delivery_manual_pricing_mode';
```

**Option 2: Via Settings UI** (if available)
1. Go to Settings → Delivery & Logistics
2. Find "Manual Pricing Mode"
3. Toggle to "Enabled"
4. Save changes

---

## Usage Guide

### **For Order Entry Staff**

#### **When Manual Mode is ON:**
```
1. Customer places delivery order
2. Enter delivery address (for reference)
3. Manually enter delivery fee in "Delivery Fee" field
4. Complete order
```

**Example:**
- Customer in Zone A → Enter 200
- Customer in Zone B → Enter 350
- Customer in Zone C → Enter 500

#### **Pricing Reference Table** (Create Your Own)
```
Zone        Distance        Fee
Zone A      0-5 km         200
Zone B      5-10 km        350
Zone C      10-15 km       500
Zone D      15-20 km       700
Zone E      20+ km         1000
```

---

### **For Dispatchers**

#### **Rider Assignment in Manual Mode:**
- System uses straight-line distance (as the crow flies)
- No traffic consideration
- Estimated duration: distance ÷ 30 km/h average speed
- Still considers rider capacity and GPS location

**Auto-Assign Still Works:**
```javascript
// Auto-assign will use Haversine distance
fetch('/api/delivery-dispatch.php?action=auto_assign', {
    method: 'POST',
    body: JSON.stringify({
        delivery_id: 123,
        manual_mode: true  // Optional, auto-detected
    })
});
```

---

## Pricing Strategies

### **Strategy 1: Flat Rate**
**Best for:** Small delivery area, simple pricing
```
All deliveries: 300 flat rate
```

### **Strategy 2: Zone-Based**
**Best for:** City with defined zones
```
Zone 1 (CBD):           200
Zone 2 (Suburbs):       350
Zone 3 (Outskirts):     500
Zone 4 (Outside city):  700
```

### **Strategy 3: Distance Tiers**
**Best for:** Gradual pricing increase
```
0-3 km:     150
3-5 km:     250
5-10 km:    400
10-15 km:   600
15+ km:     800
```

### **Strategy 4: Hybrid**
**Best for:** Flexibility
```
Base fee:       100
Per km:         30
Minimum fee:    200
```

---

## API Behavior Comparison

| Feature | Manual Mode | API Mode |
|---------|-------------|----------|
| **Distance Calculation** | None (manual entry) | Google Routes API |
| **Duration Estimate** | Distance ÷ 30 km/h | Traffic-aware actual |
| **Delivery Fee** | Manually entered | Auto-calculated |
| **Rider Assignment** | Straight-line distance | Traffic-aware routing |
| **Route Polyline** | Not available | Full route path |
| **API Costs** | $0 | ~$0.005 per request |
| **Accuracy** | Approximate | Highly accurate |
| **Setup Time** | Immediate | Requires API setup |

---

## Cost Savings

### **Google Maps API Pricing** (as of 2025)
- Routes API: $0.005 per request
- Distance Matrix: $0.005 per element
- Geocoding: $0.005 per request

### **Example Monthly Costs:**

**Small Business** (100 deliveries/month)
- API Mode: ~$1.50/month
- Manual Mode: $0

**Medium Business** (500 deliveries/month)
- API Mode: ~$7.50/month
- Manual Mode: $0

**Large Business** (2000 deliveries/month)
- API Mode: ~$30/month
- Manual Mode: $0

**Note:** Costs are approximate. Actual costs depend on caching efficiency and API usage patterns.

---

## Switching Between Modes

### **From API Mode to Manual Mode:**
1. Enable manual pricing mode in settings
2. Train staff on manual fee entry
3. Create pricing reference guide
4. Test with sample orders
5. Monitor for consistency

### **From Manual Mode to API Mode:**
1. Set up Google Maps API keys (see `GOOGLE_MAPS_API_SECURITY.md`)
2. Configure API key restrictions
3. Disable manual pricing mode
4. Test automatic calculations
5. Monitor API costs

**No data migration needed** - existing orders retain their fees regardless of mode.

---

## Best Practices

### **For Manual Mode:**
1. ✅ Create clear pricing zones/tiers
2. ✅ Print reference guide for staff
3. ✅ Train all order entry staff
4. ✅ Review fees weekly for consistency
5. ✅ Update pricing as needed
6. ✅ Keep pricing simple and memorable

### **Quality Control:**
```sql
-- Check for unusual delivery fees
SELECT 
    id, 
    order_number, 
    delivery_address, 
    delivery_fee,
    created_at
FROM orders
WHERE delivery_fee > 1000 OR delivery_fee < 50
ORDER BY created_at DESC
LIMIT 50;
```

---

## Troubleshooting

### **Issue: Delivery fee not saving**
**Solution:** Ensure manual mode is enabled in settings

### **Issue: Auto-assign not working**
**Solution:** Manual mode still supports auto-assign, but uses straight-line distance

### **Issue: Staff entering inconsistent fees**
**Solution:** Create and distribute pricing reference guide

### **Issue: Want to switch back to API mode**
**Solution:** Simply disable manual mode and configure API keys

---

## Database Schema

### **Settings Table**
```sql
-- Manual pricing mode toggle
setting_key: 'delivery_manual_pricing_mode'
setting_value: '0' (disabled) or '1' (enabled)
setting_type: 'boolean'

-- Instructions for staff
setting_key: 'delivery_manual_pricing_instructions'
setting_value: 'Enter delivery fee manually based on distance or flat rate'
setting_type: 'text'
```

### **Orders Table**
```sql
-- Delivery fee column (existing)
delivery_fee DECIMAL(10,2) NULL
-- Stores manually entered or auto-calculated fee
```

---

## Migration Path

### **Phase 1: Pilot** (Week 1)
- Enable manual mode
- Train 2-3 staff members
- Test with 20-30 orders
- Gather feedback

### **Phase 2: Rollout** (Week 2)
- Train all staff
- Distribute pricing guide
- Monitor for 1 week
- Adjust pricing if needed

### **Phase 3: Optimize** (Week 3+)
- Review fee consistency
- Update pricing zones
- Refine staff training
- Document lessons learned

---

## Support

### **Common Questions**

**Q: Can I use both modes?**
A: No, only one mode active at a time. Choose based on your needs.

**Q: Will existing orders be affected?**
A: No, existing orders keep their original fees.

**Q: Can I change modes anytime?**
A: Yes, switch instantly via settings.

**Q: Does manual mode affect rider tracking?**
A: No, GPS tracking still works normally.

**Q: Can I still use auto-assign in manual mode?**
A: Yes, but it uses straight-line distance instead of traffic-aware routing.

---

## Conclusion

Manual delivery pricing mode provides a **zero-cost alternative** to Google Maps API for clients who:
- Prefer simple pricing structures
- Want to avoid API subscription costs
- Operate in areas with limited mapping coverage
- Need immediate deployment without API setup

**Trade-off:** Less accuracy in distance/duration calculations, but significant cost savings and operational simplicity.

---

**Version:** 1.0  
**Date:** December 17, 2025  
**Module:** Delivery & Logistics
