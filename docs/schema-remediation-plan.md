# Schema Remediation Plan

Generated: 2025-11-27 16:26 EAT  
Source Audit: `storage/schema-audits/schema-report-20251127_161216.json` (analysis summary `schema-analysis-20251127_141952.json`)

---

## 1. High-Risk Findings

| Category | Impact | Tables (representative) | Required Action |
| --- | --- | --- | --- |
| Zero-row critical core tables | Application cannot boot with usable data; modules crash on empty lookups | `users`, `locations`, `settings`, `products`, `categories`, `loyalty_programs`, `permission_groups`, `permission_templates`, `system_modules`, `sales`, `orders` | Prepare seed SQL aligning with canonical schema. Prioritize security (users, permissions), configuration (system/settings), commerce (products, categories), and transactional seeds (registers, loyalty). |
| Missing foreign keys (24 tables) | Data drift possible; cascade/cleanup logic fails | `categories`, `expense_categories`, `loyalty_programs`, `permission_actions`, `permission_groups`, `permission_modules`, `permission_templates`, `restaurant_tables`, `riders`, `room_types`, `scheduled_tasks`, `sync_queue`, `weather_data` | Compare each table against canonical definitions (see `database/` SQL files + service classes) and generate `ALTER TABLE` statements to add FKs. Document intentional exceptions if any. |
| Missing secondary indexes (6 tables) | Slow queries when filtering by name/status; may cause table scans under load | `demo_feedback`, `expense_categories`, `loyalty_programs`, `riders`, `room_types`, `suppliers` | Add indexes on frequently filtered columns (e.g., `name`, `is_active`, `status`) per module usage. |

---

## 2. Seed Data Roadmap

1. **Security & Access Control**
   - `users`: create super admin + developer accounts with hashed passwords matching PHP config expectations.
   - `permission_groups`, `permission_templates`, `user_group_memberships`: seed default role hierarchy (super-admin, manager, cashier, rider, housekeeping, maintenance).
   - `system_modules`, `module_actions`: enable/disable modules according to restored feature set.

2. **Configuration & Reference Data**
   - `settings`, `system_settings`: company profile, timezone, currency, POS defaults.
   - `locations`, `restaurant_tables`, `room_types`, `rooms`, `riders`, `suppliers`, `expense_categories`.
   - `loyalty_programs`, `void_reason_codes`, `delivery_zones`.

3. **Operational Seeds**
   - `products`, `categories`, `inventory_items` (via `InventoryService::ensureSchema()` to keep parity), `pos_registers`, `pos_register_sessions`, `pos_register_closures`.
   - `sales`, `sale_items`, `orders`, `order_items`, `void_transactions` — seed only if regression tests need sample transactions (otherwise rely on live data restore).

Deliver all seeds as explicit SQL scripts under `database/seeds/` for manual execution per user requirement.

---

## 3. Foreign-Key Remediation Checklist

| Table | Expected FK | Source Reference |
| --- | --- | --- |
| `categories` | `parent_id` → `categories(id)` (nullable cascade) | `DELIVERABLE_1_DATABASE_SCHEMA.sql` |
| `expense_categories` | `location_id` → `locations(id)` (if multi-location) | `database/phase2-schema.sql` |
| `loyalty_programs` | (none historically) – confirm design; optionally link `created_by` to `users(id)` | `app/Services/LoyaltyService.php` |
| `permission_actions`, `permission_modules`, `permission_templates` | Should link to `permission_groups`/`system_modules` | `database/permissions-schema.sql` |
| `restaurant_tables` | `location_id` → `locations(id)` | `database/phase2-schema.sql` |
| `riders` | `location_id` → `locations(id)` | `database/enhanced-delivery-schema.sql` |
| `room_types`, `rooms` | `location_id` → `locations(id)` | `database/phase2-schema.sql` |
| `scheduled_tasks` | `created_by` → `users(id)` (optional) | `app/Console/SystemScheduler.php` (if exists) |
| `sync_queue` | `source` tables vary – leave FK-less but enforce enumerated `entity_type` values | design decision |
| `weather_data` | reference `location_id` if stored | TBD |

Action: draft `ALTER TABLE` scripts per table, ensuring existing data (currently zero rows) doesn’t block FK creation.

---

## 4. Index Optimization Targets

1. `demo_feedback`: add index on (`company_name`, `created_at`).
2. `expense_categories`: add index on (`is_active`, `location_id`).
3. `loyalty_programs`: add index on (`is_active`), (`name`).
4. `riders`: add index on (`is_active`), (`location_id`).
5. `room_types`: add index on (`location_id`).
6. `suppliers`: add index on (`name`), (`is_active`).

Provide `ALTER TABLE ... ADD INDEX ...` statements bundled with the FK remediation SQL.

---

## 5. Next Execution Steps

1. **Prepare SQL scripts**
   - `database/seeds/core_seed.sql`: security + configuration seeds.
   - `database/seeds/module_seed_inventory.sql`, etc., for module-specific data.
   - `database/migrations/2025_11_schema_constraints.sql`: FK + index fixes.

2. **Validation**
   - Re-run `php scripts/schema_audit.php` after each batch to ensure structural parity.
   - Add targeted integration tests for Inventory/Delivery/RoomBooking services to confirm schema + default data load.

3. **Documentation**
   - Update `README.md` or `docs/restore-playbook.md` with “Schema audit & remediation” instructions for recurring recoveries.

---

This plan keeps us aligned with the user’s directive: automate verification, deliver SQL for manual execution, and progress toward fully functional modules with international-grade rigor.
