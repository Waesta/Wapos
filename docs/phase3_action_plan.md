# WAPOS Phase 2 Completion & Phase 3 Execution Plan

_Updated: 2025-11-16_

## Overview
This document captures the concrete implementation backlog required to:
1. Close outstanding Phase 2 deliverables.
2. Deliver the Phase 3 roadmap items requested by stakeholders.

Items are grouped by module with explicit tasks, dependencies, and suggested sequencing.

---

## Phase 2 Completion Tasks

### 1. Restaurant Reservations & Split Billing
- [ ] E2E QA script covering reservation creation, overlap validation, and status transitions.
- [ ] Split-billing regression: ensure `order_payments` is populated from POS and restaurant payment flows.
- [ ] Add reservation activity feed (append internal notes) to UI for traceability.

### 2. Room Booking Module
- [ ] Build calendar view for `room_bookings` with drag/drop or modal interactions.
- [ ] Connect check-in/check-out buttons to `RoomBookingService::updateStatus` with inline folio updates.
- [ ] Surface invoice download CTA in booking detail drawer.

### 3. Delivery Operations Dashboard
- [ ] Create `delivery-dashboard.php` page summarizing live deliveries, rider locations, and status timelines.
- [ ] Wire dashboard to `DeliveryTrackingService::getTimelineForOrder` and rider location endpoints.
- [ ] Add settings UI hints for Distance Matrix API key and origin coordinates.

### 4. Multi-location Admin Console
- [ ] Build `multi-location-dashboard.php` with cards for sales, inventory, deliveries per site.
- [ ] Implement inventory transfer request workflow (create, approve, fulfil).

### 5. Reporting & Accounting (IFRS alignment)
- [ ] Validate `LedgerDataService` outputs against IFRS templates; add automated test fixture.
- [ ] Update `accounting.php`, `reports.php`, `profit-and-loss.php`, `balance-sheet.php`, `sales-tax-report.php` to use the new ledger service consistently.
- [ ] Provide export (CSV/PDF) buttons for management reporting.

### 6. Housekeeping & Maintenance Polishing
- [ ] Extend housekeeping board with filtered views per role (admin/manager/housekeeper).
- [ ] Integrate maintenance tickets with housekeeping to block rooms when flagged.

---

## Phase 3 Feature Tracks

### Track A: Advanced Delivery & Customer Ordering
- [ ] Customer-facing ordering portal (responsive web) with menu, cart, and checkout.
- [ ] Real-time order tracking using delivery status history + push updates.
- [ ] Route optimization UI leveraging `delivery_routes`/`route_waypoints` tables.

### Track B: Offline/PWA Enablement
- [ ] Service worker for POS/restaurant/ordering surfaces with asset caching.
- [ ] IndexedDB layer for carts, orders, delivery updates, reservations.
- [ ] Sync queue reconciliation UI for admins; conflict resolution strategies.

### Track C: Payment & Integration Enhancements
- [ ] M-Pesa STK push integration for POS and delivery payments.
- [ ] Stripe/PayPal integration for online checkout.
- [ ] WhatsApp notification templates for reservations, deliveries, and housekeeping alerts.

### Track D: Analytics & Multi-location Intelligence
- [ ] Unified analytics dashboard (sales, occupancy, delivery KPIs) per branch.
- [ ] Forecasting module (inventory, sales) using historical data.
- [ ] Scheduled email reports (daily, weekly, quarterly) aligned with IFRS reporting cadence.

### Track E: Security & Compliance
- [ ] Implement 2FA for administrative roles.
- [ ] Encrypt sensitive configuration (API keys, tokens) at rest.
- [ ] Automated backup scheduling with retention policies and restoration playbooks.

---

## Sequencing Recommendation
1. Close Phase 2 items in the order listed to stabilize existing modules.
2. Kick off Track A and Track C in parallel once delivery dashboard is live (customer value + revenue impact).
3. Start Track B (offline/PWA) after core workflows are stabilized to avoid rework.
4. Track D (analytics) and Track E (security/compliance) proceed once the foundation modules are production-ready.

---

## Next Steps
- Review and sign off on backlog scope.
- Create sprint plan assigning owners & estimates per task.
- Begin implementation with reservation QA automation and room calendar experience.
