# Rider Tracking API Guide

This guide explains how rider/mobile clients should send live telemetry to WAPOS so the delivery dashboard can show Google Maps markers, ETAs, and routes in real time.

## Endpoint Overview

- **URL:** `POST /wapos/api/update-rider-location.php`
- **Auth:** same PHP session cookie that the rider portal uses (`PHPSESSID=...`). Obtain it via normal login and persist it while the rider is online.
- **Expected cadence:** send updates every **30–60 seconds** while the rider status is `assigned`, `picked-up`, or `in-transit`. Pause while idle/offline to save bandwidth.

## Payload Schema

Send JSON in the request body. Fields:

| Field       | Type   | Required | Description |
|-------------|--------|----------|-------------|
| `rider_id`  | int    | ✅        | Internal rider ID (from WAPOS admin panel / API). |
| `latitude`  | float  | ✅        | Decimal degrees (between `-90` and `90`). |
| `longitude` | float  | ✅        | Decimal degrees (between `-180` and `180`). |
| `accuracy`  | float  | optional | GPS accuracy in meters. |
| `speed`     | float  | optional | Rider speed in **meters/second** (directly from GPS). |
| `heading`   | float  | optional | Compass heading in degrees (`0` = North, measured clockwise). |

### Sample JSON Payload

```json
{
  "rider_id": 12,
  "latitude": -1.292066,
  "longitude": 36.821945,
  "accuracy": 4.2,
  "speed": 12.4,
  "heading": 85.0
}
```

### Sample cURL Request

```bash
curl -X POST https://your-domain.com/wapos/api/update-rider-location.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=YOUR_SESSION_ID" \
  -d '{
        "rider_id": 12,
        "latitude": -1.292066,
        "longitude": 36.821945,
        "accuracy": 4.2,
        "speed": 12.4,
        "heading": 85.0
      }'
```

Replace `YOUR_SESSION_ID` with the rider's authenticated session cookie. When using HTTPS behind a reverse proxy, ensure the domain matches your deployment (e.g., `https://pos.example.com/wapos/...`).

## Implementation Tips

1. **Throttle updates:** avoid sending changes if the GPS position hasn't moved more than ~10 meters since the last payload.
2. **Retry logic:** if the request fails due to temporary network issues, retry with exponential backoff. Re-authenticate on HTTP 401.
3. **Battery considerations:** leverage platform-specific background location APIs (e.g., Android FusedLocationProvider) to balance accuracy with battery life.
4. **Data precision:** keep 6–7 decimal places for latitude/longitude (≈11 cm precision) but avoid unnecessary string length.
5. **Security:** always use HTTPS in production to protect rider location data in transit.

## Response Payload

On success the API returns:

```json
{
  "success": true,
  "message": "Location updated successfully",
  "updated_deliveries": 2,
  "deliveries": [
    {
      "delivery_id": 57,
      "order_id": 1432,
      "estimated_delivery_time": "2025-11-24 13:40:12"
    }
  ]
}
```

`updated_deliveries` tells the client how many active orders were recalculated (useful for debugging). On error it returns `success: false` plus a `message` string.

## FAQ

**Q: Do riders need API keys?**  
No. They authenticate with the same credentials/session used to log into the rider dashboard. If you expose the API to native apps, consider issuing short-lived JWTs that proxy to this endpoint.

**Q: Can we batch multiple updates?**  
Currently the endpoint accepts a single location per request. Keep updates frequent instead of batching to minimize latency on the dashboard.

**Q: What happens if speed/heading are missing?**  
They are optional. The server simply skips those fields; markers will still move but without direction/velocity metadata.

---

Need more details? Contact the WAPOS engineering team or open an issue in the repo.
