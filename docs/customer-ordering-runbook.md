# Customer Ordering Portal – Runbook

_Last updated: 2025-11-16_

## Overview
Guest-facing ordering experience located at `/customer-ordering.php`. Supports delivery and pickup orders, calculates totals via existing services (`SalesService`, `DeliveryPricingService`), and exposes order status via `DeliveryTrackingService` timeline.

## 1. Deployment Steps
1. Ensure Phase 2 upgrade executed (`php upgrade.php`).
2. Deploy new files:
   - `customer-ordering.php`
   - `api/customer-ordering.php`
   - `api/customer-ordering/list-menu.php`
   - `api/customer-ordering/quote.php`
   - `api/customer-ordering/place-order.php`
   - `api/customer-ordering/status.php`
3. Clear PHP opcode caches if applicable.
4. Verify menu accessibility from browser.

## 2. Configuration
- Menu data: Use existing `products` & `product_categories`. Only `is_active` items appear.
- Delivery pricing: configure Distance Matrix settings under “Delivery & Logistics” in Settings (`settings.php`). Without API key, system falls back to Haversine/manual fee.
- Payment methods: portal currently assumes COD/Mobile Money/Card (on delivery). Payments remain pending until processed in back office.

## 3. Daily Monitoring
- Check `delivery-dashboard.php` for pending deliveries created from online orders.
- Monitor `logs/app.log` (if configured) for `Customer ordering` entries.
- Review `orders` table and ensure payment status transitions.

## 4. Incident Response
### Symptom: Guests cannot load menu
1. Check `/api/customer-ordering.php` network response.
2. Ensure menu categories/products are active.
3. Review web server logs for PHP errors.

### Symptom: Quote returns error
1. Review request payload in browser dev tools.
2. Confirm `DeliveryPricingService` dependencies (API key, database tables) are in place.
3. Check for missing products/invalid prices.

### Symptom: Orders not appearing in back office
1. Inspect `orders` and `sales` tables sorted by `created_at`.
2. Ensure `place-order.php` executed via network trace.
3. Confirm CSRF token validation not failing (response 403/419).

## 5. Future Enhancements (Backlog)
- Payment gateway integration (e.g., M-Pesa STK push, Stripe).
- Full timeline UI for status updates (map, rider location overlay).
- Offline caching / PWA support for repeat customers.
- Automated email/SMS notifications on status changes.
- Automated test suite (Cypress + PHPUnit).

Refer also to `docs/customer-ordering-test-plan.md` for detailed testing instructions.
