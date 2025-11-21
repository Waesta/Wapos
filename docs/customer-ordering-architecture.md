# Customer Ordering Portal Architecture

_Updated: 2025-11-16_

## Goals
- Provide a guest-facing web experience for browsing menus, building a cart, and placing delivery/pickup orders.
- Leverage existing POS and delivery services to avoid duplicating business logic.
- Offer real-time order status tracking using delivery timeline data.

## High-Level Components

1. **Frontend**
   - `customer-ordering.php`: SPA-style page served via PHP template, bootstraps Vue/Alpine or vanilla JS modules.
   - Sections: menu/product listing, cart summary, checkout form, order confirmation/tracking.
   - Re-uses existing product catalog APIs (`api/products.php`) and new ordering endpoints described below.

2. **API Endpoints**
   - `api/customer-ordering.php` (new controller) handling JSON requests via `action` parameter.
     - `action=list_menu` → returns categories, products, modifiers using existing services.
     - `action=quote` → accepts cart payload, returns totals/tax/delivery fee via `SalesService` + `DeliveryPricingService` (no persistence).
     - `action=place_order` → persists order by calling `SalesService::createSale` with `order_source = 'online'`, records delivery request, triggers notifications.
     - `action=status` → fetches latest delivery status history for a given order using `DeliveryTrackingService`.

3. **Services Re-use**
   - `SalesService` for order creation and payment posting (introduce `customer_order` payment method if needed).
   - `DeliveryPricingService` for dynamic delivery fee calculation.
   - `DeliveryTrackingService` for status/timeline retrieval.
   - `RestaurantReservationService` is not directly used but maintains schema for tables if dine-in bookings extend later.

4. **Data Flow**
   ```
   UI -> api/customer-ordering.php?action=list_menu -> products/categories
   UI -> api/customer-ordering.php?action=quote -> totals + delivery fee
   UI -> api/customer-ordering.php?action=place_order -> SalesService + Delivery request
   UI -> api/customer-ordering.php?action=status -> timeline updates
   ```

5. **Security & Validation**
   - All endpoints require CSRF token for POST (reuse `generateCSRFToken()` logic).
   - Rate limiting via basic session throttling.
   - Input validation for cart items, customer address, contact details.
   - Captcha or email verification optional for future iterations.

6. **Notifications**
   - Optional hooks to `WhatsAppService` / email for order confirmation and status changes.
   - Web UI polls `action=status` every 30 seconds for live updates.

7. **DB/Schema Impacts**
   - Ensure `orders.order_source` column (already present) accepts `online` value.
   - If needed, add `orders.customer_email`, `orders.delivery_lat/lng` via migration.
   - Reuse `delivery_distance_cache`, `delivery_pricing_audit`, `deliveries` tables.

8. **Next Steps**
   1. Scaffold `api/customer-ordering.php` with action router.
   2. Build `customer-ordering.php` UI (menu, cart, checkout).
   3. Integrate Delivery pricing and order placement flows.
   4. Add status tracking widget tied to delivery history.
