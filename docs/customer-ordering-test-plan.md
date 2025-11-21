# Customer Ordering Portal – Integration Test Plan

_Last updated: 2025-11-16_

## 1. Prerequisites
- Database upgraded through `upgrade.php` (Phase 2 scripts applied).
- `products`, `product_categories`, and optionally `modifiers` populated with at least one active item per category.
- Web server running (e.g., Apache in XAMPP) and base URL reachable at `http://localhost/wapos/`.
- Test account with `admin` or `manager` role for verifying order creation in back office.

## 2. Manual Test Matrix
| Scenario | Steps | Expected Outcome |
| --- | --- | --- |
| Browse Menu | Open `/customer-ordering.php` | Categories and items render; loading spinner disappears. |
| Add to Cart | Click “Add to Order” on 2 different products; adjust quantities | Cart badge updates, totals recalculated via quote endpoint, floating cart button visible. |
| Remove Item | Remove one item, decrement quantity to 0 | Item removed, totals refresh, cart empty state shown when last item removed. |
| Delivery Checkout | With items in cart, leave order type as Delivery → Checkout → submit address/contact info | Modal shows quote totals; order placed; confirmation modal displays order number and total; /orders table has new record with `order_type=delivery`. |
| Pickup Checkout | Switch order type to Pickup, place order | Delivery fields hidden, quote excludes delivery fee, order stored with `order_type=pickup`. |
| Status Tracking | From confirmation modal click “Track Order” | Status modal shows timeline (empty initially), refresh button fetches latest status without error. |
| Menu API | `POST api/customer-ordering.php {"action":"list_menu"}` | JSON payload with categories, products, modifiers. |
| Quote API | `POST api/customer-ordering.php {"action":"quote", ...}` with sample cart | Totals returned, delivery fee included when `order_type=delivery`. |
| Place Order API | `POST api/customer-ordering.php {"action":"place_order", ...}` with valid cart & `csrf_token` | Response success with `order_id` and `order_number`, DB entries created in `sales`, `orders`, `order_items`, (`deliveries` for delivery). |
| Status API | `POST api/customer-ordering.php {"action":"status", "order_id": <id>}` | JSON with latest status, timeline array based on `delivery_status_history`. |

## 3. Data Validation
After a successful order placement:
- `sales` table should contain a new row with `order_source = 'online'` and totals matching UI.
- `orders` table entry links to the sale (order number matches sale number) and stores delivery/pickup metadata.
- `order_items` contains each line, quantities, and pricing.
- For delivery orders:
  - `deliveries` table has `status = 'pending'`.
  - `delivery_pricing_audit` row (if Distance Matrix configured) linked via `attachAuditToOrder`.

## 4. Negative Tests
- Submit `place_order` without CSRF token → expect `success=false`, error message.
- Provide invalid product ID → expect “unavailable product” response.
- Omit required delivery address when `order_type=delivery` → UI validation prevents submission.
- Force `status` action with invalid `order_id` → expect “Order not found”.

## 5. Regression Checks
- Existing POS flows (`pos.php`, `restaurant-order` workflows) should continue to operate; verify `SalesService` totals unaffected by new order source.
- Delivery dashboard (`delivery-dashboard.php`) should include newly created deliveries in pending list.

## 6. Post-Test Cleanup
- Optional: delete test orders using admin interface or SQL (`DELETE FROM order_items WHERE order_id IN (...); DELETE FROM orders ...; DELETE FROM sales ...; DELETE FROM deliveries ...;`).

_Add additional automated tests (PHPUnit or Cypress) as the project introduces a dedicated testing harness._
