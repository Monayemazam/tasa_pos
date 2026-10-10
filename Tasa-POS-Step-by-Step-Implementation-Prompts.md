# TASA POS — Complete Step-by-Step AI Implementation Prompts
**Source of truth:** `Tasa-POS-PRD(1).md`, `Tasa-POS-Database-Schema(1).md`, and `Tasa-POS-Design-UX-Guideline.md` (all v1.0, dated 11 October 2026).

## How to use this prompt pack

1. Put all three source files in the AI coding tool's project context/repository if it supports project files.
2. Start with Prompt 0, then run prompts **in order**. Do not paste all prompts at once.
3. After each prompt, run the app, inspect the changed files, and complete the verification checklist before moving on.
4. If a prompt uncovers an earlier defect, fix it before continuing. Do not let later phases build on broken migrations, fake UI, placeholder data, or unverified business logic.
5. Keep a Git commit or backup after every accepted phase.

---

## GLOBAL RULES — include these in every implementation session

Treat the three Tasa documents as the specification. Preserve their terminology, schema, rules, and UX. Do not silently omit a feature, replace a requirement with a mockup, or invent a different business workflow. If the documents conflict or leave a decision open, use the PRD's stated default and record the decision.

- Stack: current stable Laravel using the official Vue + Inertia starter kit, PHP 8.3+, Vue 3, Inertia, Tailwind CSS v4, DaisyUI (the installed compatible version), MySQL 8.0.16+, Vite. Verify the actual versions and package compatibility before installing anything. Use Composer/npm packages only when needed and compatible.
- Use the database schema document as the authoritative table/column/relationship/migration-order specification. Keep money as `DECIMAL(12,2)`, never float; leather quantities as `DECIMAL(12,3)` sq ft; product/sale piece quantities as integers; accessory quantities as `DECIMAL(12,3)`. Use InnoDB, `utf8mb4`, real foreign keys, indexes, and the specified delete/soft-delete rules.
- Business timezone is `Asia/Dhaka`; currency is BDT (`৳`); dates display `DD MMM YYYY`; support Unicode/Bangla names and addresses.
- Never hard-delete sales, payments, stock ledgers, or audit logs. Sales must be cancelled or returned with a reason. Ledger corrections are new compensating records, not edits/deletes.
- No walk-in/anonymous customers. Every sale needs customer name, valid Bangladeshi mobile `^01[3-9]\d{8}$`, and delivery address. When Pathao is used, require city, zone, area, and weight.
- All sale/stock/payment/invoice-sequence writes must be transactional and concurrency-safe using row locks where specified. Protect against duplicate submission with an idempotency token enforced server-side.
- Server-side authorization is mandatory. Do not send sensitive cost, purchase price, payroll, or profit data to users who lack permission. Hiding a menu item is not authorization.
- Pathao calls must be queued; a courier outage must never prevent saving the sale/invoice. Use retries/backoff and idempotent merchant order IDs.
- All create/edit/cancel/return/stock/payroll/permission-changing actions must write audit entries as specified. Audit logs are append-only and read-only in the UI, even for Owner.
- No product images, no BOM, no tax/VAT, no wholesale tiers, no multi-branch, no loyalty points, no storefront, no double-entry ledger, no bank reconciliation, no balance sheet. Do not add these as “helpful extras.”
- UI: white/light only; page `#F4F6F6`, surfaces `#FFFFFF`, deep teal `#0B6E5E`; square corners; 1px borders; no gradients, glass, blur, decorative shadows, dark mode, or product image placeholders. Hanken Grotesk with Noto Sans Bengali fallback; Lineicons outline; WCAG 2.2 AA; status = colour + icon + text.
- One clear primary action per screen. Staff-facing wording must be plain language. Use skeletons, empty states, actionable errors, confirmation dialogs for destructive actions, and no optimistic UI for money or stock.
- Every prompt must end with actual verification: run tests, inspect database rows/query results, hard reload the UI, and report what was changed, what passed, what failed, and anything blocked. A toast or “implemented” claim is not proof.
- Do not rewrite unrelated modules or make broad destructive refactors. Inspect current code first; preserve working functionality.
- Do not commit secrets. Pathao credentials belong in `.env`; do not echo credentials into logs or UI. Do not execute PHP from a custom invoice template.
- Use policies/gates/form requests/services/jobs where appropriate. Keep controllers thin, business logic testable, and queries scoped/paginated.
- Never use fake success buttons or static mock data for completed features. If external credentials are not available, implement the real integration boundary and a clearly marked configuration/error state; do not pretend a live request succeeded.

---

# Prompt 0 — Repository Audit, Source Reconciliation, and Build Plan

You are the senior Laravel/Vue engineer responsible for delivering Tasa POS from the three supplied specification files.

Before changing code:
1. Inspect the repository structure, Git status, PHP/Composer/Node/npm versions, Laravel version, starter kit, installed packages, Vite/Tailwind/DaisyUI versions, database config, routes, migrations, models, controllers, Vue pages, layouts, tests, and environment setup.
2. Read all three source files end-to-end, not only their summaries:
   - Tasa POS PRD
   - Tasa POS Database Architecture
   - Tasa POS Design & UX Guideline
3. Build a requirements traceability checklist with every module, subfeature, business rule, table, permission, report formula, acceptance criterion, responsive requirement, and design QA item. Use the source document's section numbers.
4. Compare current implementation with the specification. Classify each item as missing, partial, implemented-but-unverified, or verified. Cite concrete files/routes/tables when reporting status.
5. Identify conflicts or ambiguities without silently changing them. Use the PRD's defaults for O-1 through O-7. Specifically default “Cost price includes labour” to No; support all worker wage bases; monthly invoice sequence `TASA-YYMM-0001`; Staff cannot cancel/return by default; Pathao delivery cost is recorded as an expense and customer delivery charge is optional.
6. Propose an ordered implementation plan matching the PRD delivery phases and the prompts in this pack. Identify risky package/version compatibility issues (especially Laravel 13 / Pathao package), but do not install an unverified package yet.
7. Do not modify code in this prompt. Save the traceability checklist in the repository, e.g. `docs/implementation-tracker.md`, including acceptance evidence columns and unchecked items.

Output: audit findings, architecture map, blockers, plan, and the path to the checklist. No unsupported “done” claims.

**Verify:** ensure the checklist covers all headings in the three source documents and each PRD acceptance criterion AC-1 through AC-14.

---

# Prompt 1 — Laravel Starter Kit, Dependencies, App Shell, and Design Tokens

Implement only the project foundation and global visual system.

1. If the repo is empty, create the current official Laravel Vue + Inertia starter kit project. If it already exists, preserve it and repair only what is necessary.
2. Confirm compatible versions of Laravel, PHP, Vue, Inertia, Tailwind v4, DaisyUI, and Vite. Check DaisyUI syntax against the installed major version; do not paste config written for another version.
3. Set application timezone to `Asia/Dhaka`; configure locale and date handling; configure MySQL connection for InnoDB/utf8mb4. Keep secrets in `.env`.
4. Install and self-host Hanken Grotesk and Noto Sans Bengali (including needed weights). Verify Bangla text and the `৳` glyph render correctly. Set `font-variant-numeric: tabular-nums` for money and quantity cells.
5. Implement exact design tokens from UX guideline §3 and §12:
   - Page `#F4F6F6`; surfaces `#FFFFFF`; sunken `#EBEFEE`.
   - Teal `#0B6E5E`, hover `#095A4C`, pressed `#074538`, tints `#E6F2EF` and `#F0F7F5`.
   - Ink and line colours exactly as specified.
   - Status colours for success, warning, danger, info, and advance/over-paid.
   - Square corners, 1px borders, no gradients, blur, glass, dark mode, or decorative card shadows.
6. Add `<meta name="color-scheme" content="light">` and `color-scheme: light` on `:root`. No dark-mode toggle or dark preference styling.
7. Create reusable layout primitives/components: application shell, page header, primary/secondary/ghost/danger buttons, labelled field, money field, BD phone field, status chip (icon + text), data table, mobile record card, empty state, skeleton, page error, confirmation dialog, drawer/full-screen mobile sheet, toast, date-range filter, search/filter bar, and pagination.
8. Create a central outline-icon mapping file using Lineicons. Icon-only actions need tooltip and `aria-label`.
9. Add skip-to-content, visible 2px teal focus ring, reduced-motion support, keyboard navigation, and accessible live regions.
10. Build responsive shell:
    - Desktop ≥1024: 248px sidebar, 56px top bar, global search, New Sale shortcut, user menu.
    - Tablet 640–1023: 72px icon rail and overlay drawer.
    - Mobile <640: 56px top bar and 64px bottom nav with Sale, Invoices, Inventory, Expenses, More; adapt visible items to permission.
11. Navigation groups and page-header pattern must follow the UX guideline. Keep content max-width 1440px.
12. Do not implement business modules yet; use only temporary shell placeholders clearly marked as not implemented.

**Verify:** run npm build and Laravel tests; inspect at 360, 390, 640, 768, 1024, 1280, 1440 widths; test keyboard focus, 200% zoom, Bangla, currency, and no horizontal page scroll at 360px. Update tracker.

---

# Prompt 2 — Full Database Schema, Migrations, Models, Seeders, and Integrity Tests

Implement the schema exactly from Database Architecture §1–§11. Do not simplify the schema into generic JSON tables.

1. Create migrations in the documented order:
   1) auth/access and Spatie permission tables;
   2) settings, audit_logs, import_logs, invoice_sequences;
   3) customers;
   4) product_categories, products, product_stock_movements;
   5) leather_items, leather_purchases, leather_consumptions;
   6) accessory_items, accessory_purchases, accessory_consumptions;
   7) stock_adjustments;
   8) expense_categories, recurring_expenses;
   9) sales, sale_items, sale_payments, sale_returns, sale_return_items;
   10) courier_orders, courier_status_logs;
   11) expenses;
   12) employees, salary_months, salary_payments, workers, worker_earnings, worker_payments;
   13) Laravel jobs/cache/session tables;
   14) Pathao package table only if required and compatible.
2. Preserve all columns, types, nullable rules, defaults, unique constraints, indexes, check constraints, foreign keys, `ON DELETE RESTRICT` defaults, and explicitly specified `SET NULL` actions in the schema document.
3. Keep all money columns `DECIMAL(12,2)`; use correct quantity types. Never use float/double for money.
4. Use soft deletes only on specified master data (users, customers, products/items, employees, workers and other masters as schema dictates). Never add soft deletes to sales, payments, ledgers, or audit logs.
5. Extend users with `is_active`, `last_login_at`, and starter-kit 2FA fields as required. Add role lock/description columns to Spatie roles. Enforce one role per user in application logic even if Spatie supports multiple.
6. Implement Eloquent models, relationships, casts, scopes, and factories. Avoid accidental mass assignment of protected fields.
7. Implement the `settings` key/group uniqueness, `invoice_sequences` monthly period row, append-only `audit_logs`, and `import_logs`.
8. Seed Owner and Staff roles, documented permissions, settings defaults, system expense categories and an initial Owner setup flow. Never ship a known default production password; require secure setup/rotation and document it.
9. Add a migration/schema consistency check and tests for FK behaviour, unique phone/SKU/invoice constraints, decimal precision, soft deletes, and ledger append-only rules.
10. Add database views only where specified/appropriate (e.g. leather stock); keep reports able to recompute from source rows.
11. Implement the schema verification SQL from §9 in a documented script or test suite. Do not “fix” a failed invariant by altering historical data silently.
12. Record any schema conflict in the tracker and choose the database architecture document unless the PRD explicitly overrides it.

**Verify:** fresh migrate, rollback/re-migrate in a disposable test DB, seed, inspect actual tables/columns/indexes/FKs, run tests, and execute relevant §9 verification queries. Do not reset a real database.

---

# Prompt 3 — Authentication, Users, Roles, Permissions, Settings, and Audit Log

Implement security and system administration end-to-end.

1. Use the official starter kit authentication. Remove public registration; only Owner-authorized user creation is allowed. Implement login rate limiting, secure session behaviour/timeouts, password reset/forced reset, active-user checks, and optional Owner 2FA if supported by the starter kit.
2. Create locked Owner role with full access. Owner cannot be renamed, edited, deleted, deactivated, or deprived of permissions. A Staff user cannot deactivate the Owner.
3. Create Users & Roles settings:
   - Create users (name, email/phone, password), assign exactly one role.
   - Activate/deactivate users without losing their historical records.
   - Force password reset.
   - Create custom roles and select section permissions in a clear checkbox matrix (View/Add/Edit/Delete plus the documented special permissions).
   - Owner role screen is read-only with “Owner always has full access.”
   - Unsaved permission changes show a sticky Save/Discard bar.
   - On mobile, use section accordions with large toggles.
4. Seed the permission names and defaults from Database Architecture §7.2 and PRD §12. Staff defaults: basic dashboard; POS; invoices/customers; courier; inventory; add/view expenses; quantity-only sales report. No payroll, P&L, role/settings management, cost/purchase prices, or sales profit columns by default. Cancel/return defaults to Owner only.
5. Enforce permissions through Laravel middleware, policies/gates and query/response shaping. Unauthorized requests return 403. Sensitive columns must not be sent to Staff at all. Check every read and write route, exports, dashboards, global search, and nested endpoints.
6. Build settings storage and UI for General, Invoice Branding, Pathao Courier, Categories & Units, Users & Roles. Implement general settings and the documented switches, including allow negative stock, cost price includes labour, price editing at sale, courier-risk hint, and other seed keys in schema §7.3.
7. Implement an append-only audit service and use it for every required mutation: create/edit/delete of masters, sales/payment changes, cancellation/returns, stock adjustments, payroll entries, settings and permission changes, login events where specified. Record user, event, model/type/id, old/new values, timestamp, IP, and user agent when available.
8. Audit log UI is searchable/filterable and read-only for everyone, including Owner. Never expose an update/delete route.
9. Ensure sensitive fields are excluded in Inertia props, API JSON, exports, validation errors, logs, and global search for unauthorized roles.
10. Add automated authorization tests for direct URL/API attempts and permission changes taking effect on the next request.

**Verify:** test as Owner, Staff, custom role, inactive user, and unauthenticated user. Prove Staff gets 403 on Payroll/P&L and cannot retrieve cost, purchase-rate, or profit fields by API or export. Confirm audit records are written in the database and cannot be edited/deleted.

---

# Prompt 4 — Finished Products, Categories, Production Entries, Stock Ledger, and Excel Import

Implement finished-goods inventory from PRD §8.1 and schema §4.4.

1. Products table: name, SKU (auto-generate `P-000123` when blank), category, manual cost price, selling price, stock quantity, minimum stock, active/inactive. No images, variants, BOM, or product thumbnails.
2. Validate non-negative prices. Show cost price only to users with `inventory.cost.view` (or the exact seeded permission equivalent); never include it in unauthorized responses.
3. Implement product/category CRUD, search, active/inactive filter, low-stock filter, pagination and clear empty/loading/error states. Deletion/archive must respect FK/history constraints; use soft-delete/archive when required rather than breaking historical invoice lines.
4. Implement “Add stock / production entry”: date, integer quantity, note. This writes a signed `product_stock_movements` row (`production` or `opening` as appropriate) and updates cached `products.stock_qty` in the same transaction.
5. Implement stock movement history per product with date, type, signed quantity, balance after, reference, note and creator. Movements are append-only.
6. Implement stock adjustments for finished products with signed quantity, date, reason (`damaged`, `lost`, `count_correction`, `other`), required note for `other`, and audit log. Write the adjustment and movement atomically.
7. Implement Excel import:
   - Downloadable template columns: Name, SKU, Category, Cost price, Selling price, Opening stock, Min stock.
   - Upload xlsx/csv as supported; validate file type/size and each row.
   - Preview parsed rows before committing; show row-level errors and duplicate SKU/name problems.
   - Import only confirmed valid rows; store `import_logs` with filename, total/success/failed counts and row errors.
   - Opening stock creates matching stock movement rows.
   - Avoid partial silent imports; use a clear transaction/batch strategy and report which rows succeeded.
8. Implement low-stock alerts based on current stock <= minimum stock. Negative stock is prohibited unless the setting explicitly allows it.
9. Add concurrency-safe stock updates using transactions and row locks. `stock_qty` must always equal the sum of product stock movements; add a verification command/test.
10. Build UI matching the UX spec: crisp tables on desktop, mobile cards, sticky table headers, right-aligned quantities and money, inline edit only for minimum-stock values, no optimistic updates.

**Verify:** create product, production entry, adjustment, import a valid and invalid workbook, sell/return test fixtures if sales module exists, hard reload, inspect DB movement rows and stock totals, test concurrent/negative-stock protection, and test Staff cost-price redaction.

---

# Prompt 5 — Leather Inventory and Purchases/Consumption

Implement raw leather stock exactly as PRD §8.2 and schema §4.5.

1. Leather item catalogue: name, optional colour/grade, minimum stock in sq ft, active status. Support Bangla/Unicode names.
2. Provide tabs: Stock, Purchases, Consumption. Use sq ft throughout; display quantity to 3 decimals.
3. Purchase form fields: date, optional supplier text, leather item, quantity in sq ft, rate per sq ft, auto-calculated total cost (`ROUND(qty × rate, 2)`), amount paid, payment method (`cash`, `bkash`, `bank_transfer`), note.
4. Validate quantity > 0, rate and paid amount >= 0, amount precision and date. Do not record the purchase as an expense; purchase cash flow is shown only in the P&L memo/cash summary.
5. Consumption form: date, leather item, quantity, note. Manual only; no BOM or automatic deduction from production. Block negative stock unless the explicit setting permits it. Consumption does not create a P&L expense.
6. Stock calculation must be `SUM(purchases.qty_sqft) - SUM(consumptions.qty_sqft) + SUM(adjustments.qty)`. Weighted average cost = total purchase cost / total purchased quantity. Stock value = current stock × weighted average cost.
7. Add date-range reporting for purchased and consumed quantities/costs; low-stock alerts; supplier and item filters; stock movement/history where appropriate.
8. Integrate stock adjustments for leather with signed quantities, reason/date/creator and audit. Verify adjustments affect current stock.
9. Hide purchase rates and purchase cost fields from Staff unless the documented special permission is enabled. Do not leak via summaries, exports, API or search.
10. Use transactions/locks around stock-affecting operations and ensure calculations are derived from source rows, not hand-edited totals.

**Verify:** test multiple purchase rates to prove weighted average, consumption, adjustments, low-stock boundary, negative-stock setting, date filters, Staff redaction, and SQL calculation versus UI. Confirm no expense row is created by purchase/consumption.

---

# Prompt 6 — Accessories Inventory and Stock Adjustments Across All Item Types

Implement accessories as the leather-equivalent stock system with per-item units, then finish shared stock adjustments.

1. Accessory catalogue: name, unit (pcs, set, meter, kg, etc.), minimum stock as `DECIMAL(12,3)`, active status. Name is unique per schema.
2. Tabs: Stock, Purchases, Consumption. Stock shows total purchased, total consumed, current stock, weighted average cost, stock value, and low-stock status.
3. Purchase fields: date, optional supplier, item, quantity, rate, computed total, amount paid, payment method, note. Validate quantity > 0, rate/paid >= 0. Use decimal-safe arithmetic.
4. Consumption fields: date, item, quantity, note. Manual only, no BOM. Enforce negative-stock setting. Consumption is stock tracking only, not a P&L expense.
5. Formula: current stock = purchases − consumptions ± adjustments; average cost = total purchase cost / total purchased quantity; stock value = current stock × average cost.
6. Complete shared Stock Adjustments for product/leather/accessory: item type, item, signed quantity, reason, note required for “other,” date, creator. Append-only; every adjustment audited. For products, write `product_stock_movements` in the same transaction.
7. Check item-type IDs safely because stock_adjustments uses a polymorphic item type/id rather than a direct FK; reject IDs that do not exist in the selected item table.
8. Staff can view/add/edit inventory as permitted, but purchase rates and cost data stay hidden by default. Delete/archive actions are Owner-only by default and must not invalidate ledgers.
9. Build low-stock widgets and global inventory search. Every status must have an icon and label as well as colour.

**Verify:** exercise all three item types, positive and negative adjustments, invalid item IDs, fractional quantities, unit labels, low-stock alerts, permissions, ledger consistency, and no duplicate expense creation.

---

# Prompt 7 — Customers and the Complete POS Sale Workflow

This is the most business-critical prompt. Implement real sales, not a static demo. Use a dedicated service layer and transactions.

## Customer
1. Customer profile fields: name, unique phone, optional alternate phone if present in schema, address, Pathao city/zone/area IDs and display names, notes. Customer phone is the unique key.
2. Search by phone first. Existing customers auto-fill name/address and last-used Pathao location; staff may correct details according to permissions. New customers can be created from the sale form.
3. Customer profile shows computed total orders, total spent, outstanding due and order history. Do not store these totals as mutable fields.
4. No anonymous/walk-in customer option anywhere.

## POS UI
5. Desktop: products on left; customer/cart/payment on right. Mobile: three guided steps (Customer → Products → Payment) and sticky footer. Text-only product list.
6. Customer required fields: name, Bangladeshi phone matching `^01[3-9]\d{8}$`, address at least 10 characters. If Pathao selected, City/Zone/Area and weight are required.
7. Product search by name/SKU; fast results; keyboard-friendly with focus + Enter so future barcode scanner input works. Adding a product adds a cart line. Show name, qty, unit price and line total. Quantity is whole pieces; stock checks happen server-side.
8. Selling price is prefilled from product. Editing the price requires the specific permission (Owner yes; Staff default yes but configurable as specified). Unit cost is snapshotted at sale time and hidden from unauthorized roles.
9. Support custom-made “Custom item” lines with free-text name, price and optional manual cost. They do not deduct product stock. Preserve snapshot cost for P&L; if cost is omitted, use the explicitly agreed safe/default handling and document it rather than guessing silently.
10. Totals: subtotal, flat or percentage discount, optional customer delivery charge, grand total. Validate discounts and prevent negative totals. Clearly show `৳` and tabular numbers.
11. Payment: paid amount supports partial payment; methods Cash, Cash on Delivery, bKash, Bank Transfer. Show balance due. For COD, Pathao collection amount is the remaining balance due. Support later Add Payment with date, amount, method and note.
12. Delivery mode: Send via Pathao (default) or No courier / hand-delivered. Pathao fields include weight (kg), Normal/On-demand, special instruction. Pathao city/zone/area are required only when sending via Pathao.
13. Save sale: server validates and uses a DB transaction to lock stock rows, reserve monthly invoice sequence, create sale, sale lines with price/cost snapshots, payments, stock movements, customer updates and audit events. Generate invoice `TASA-YYMM-0001`; sequence resets monthly, is concurrency-safe and never reuses cancelled numbers.
14. Stock deduction occurs only for catalogue products. If requested quantity exceeds stock, show clear warning; saving below zero is allowed only if `inventory.allow_negative_stock=1`. Re-check under lock on the server.
15. Protect double-click/retry using disabled loading button plus a server-side idempotency token with unique enforcement. A repeated token returns the existing result instead of making a second sale.
16. Do not wait for Pathao before committing sale. Dispatch a queued courier job after the sale transaction commits.
17. On success, show a clear confirmation with Print Invoice, Download PDF, New Sale. Refresh from server so the sale is visible in persisted records.
18. Add customer Pathao delivery success-rate hint only if enabled and available through the real package/service; show “unavailable” safely if it cannot be retrieved.

**Verify:** test missing customer fields, invalid phone, too-short address, empty cart, bad discount, partial payment, custom item, stock boundary, duplicate Save, simultaneous invoice creation, cost snapshot after product cost changes, customer auto-fill, hand-delivered sale, and DB rows/stock ledger. Test AC-1, AC-2 and AC-5.

---

# Prompt 8 — Invoice Documents, Printing, PDF, Branding, and Safe Template Editor

1. Build invoice view using the saved sale snapshot: shop logo/name/address/phone; invoice number/date; customer name/phone/address; item table (name, quantity, unit price, total); subtotal, discount, delivery charge, grand total, paid, due, payment method(s); Pathao consignment ID if any; footer note/terms.
2. Invoice number format `TASA-YYMM-0001`, with configurable prefix but monthly sequence by default. Cancelled invoice numbers are never reused.
3. Support A4, A5, and 80mm thermal-width layouts. Make print styles hide navigation, controls, and non-invoice UI. Ensure totals do not split awkwardly across pages and Bangla/currency render correctly.
4. Generate PDF using a compatible server-side PDF solution after checking the installed PHP/Laravel version. Do not fake Download PDF by opening print unless clearly offered as a separate Print action.
5. Owner-only Invoice Branding settings: logo upload, shop name/address/phone, footer, terms, selectable fields, paper size, and custom template editor.
6. Template editor allows sanitized HTML/CSS and only safe documented placeholders such as `{{invoice_no}}`, `{{customer_name}}`, `{{items_table}}`. Do not execute PHP, JavaScript, Blade, arbitrary server expressions, or unsanitized HTML. Escape customer-supplied values and sanitize allowed markup/CSS.
7. Provide a live A4/A5/thermal preview beside the form and a template-editor tab below. Save settings in the documented settings store and audit changes.
8. Print/download from invoice list and sale success page. Preserve the original sale-line unit price and cost snapshots even if product details later change.
9. Make invoice access permission-aware and avoid leaking internal cost/profit data on customer invoices.

**Verify:** compare printed/PDF totals against database values; test A4/A5/80mm; test long addresses and Bangla; test no Pathao order and Pathao order; test sanitized malicious template input; verify saved branding after hard reload.

---

# Prompt 9 — Invoices List, Payments, Cancellations, Returns, and Customer Balances

1. Build Sales/Invoices table with invoice number, date, customer, phone, total, paid, due, payment status (Paid/Partial/Unpaid), courier status, and actions.
2. Filters: date presets Today, Yesterday, Last 7 days, This month, Last month, Custom; payment status; courier status; payment method; search invoice number/customer name/phone. Keep filters in URL so refresh preserves them.
3. Actions: View, Print, Download PDF, Add Payment, Cancel Sale, Mark Returned (full or partial), Retry Pathao where applicable.
4. Add Payment records a new payment row with date, amount, method and note. Validate amount and payment status recalculation. No direct edits/deletes of payment history; corrections need an audited reversal/compensating record if the schema supports it.
5. Cancel sale: Owner-only by default; require reason; never hard-delete; mark cancelled; restore stock exactly once with `sale_cancel` movements; revenue excluded from reports; retain invoice number and full history; audit old/new state.
6. Return: record return date, reason, selected sale lines and quantities (full or partial), return amount and optional return courier cost. Prevent returning more than the unreturned sold quantity. Restore only returned catalogue quantities once. Record `sale_returns` and `sale_return_items`; revenue reduction is attributed to the return date's reporting period. Return courier cost creates a linked expense where specified.
7. If a courier parcel has already been dispatched, cancellation cannot pretend to cancel the Pathao parcel. Show warning and instructions/link to Pathao merchant panel; record local cancellation separately.
8. Block duplicate cancellation/return submissions and use transactions/locks to protect stock, payment, invoice and return balances.
9. Customer due totals are computed from active invoices/payments/returns according to the business rules. Make the calculation inspectable and consistent across customer profile, dashboard and reports.
10. Use clear confirmation dialogs with consequence text and required reason. No Delete action for sales/payroll history.

**Verify:** full and partial payment, payment after sale, cancel once and attempt twice, full/partial return, over-return prevention, return date period, courier return expense, customer due before/after, stock ledger, invoice-number retention and audit rows.

---

# Prompt 10 — Pathao Courier Integration, Queue Jobs, Status Polling, COD Settlement

Implement a real Pathao integration boundary with graceful failure.

1. First inspect the installed Laravel version and verify `enan/pathao-courier` (package name `enan/pathao-courier`) compatibility and its actual README/API. Do not assume package compatibility. If incompatible, implement an internal `PathaoService` calling the official Merchant API with the same interface so business code does not depend on the package.
2. Support `.env` keys `PATHAO_CLIENT_ID`, `PATHAO_CLIENT_SECRET`, `PATHAO_SECRET_TOKEN`. Secrets never appear in UI, logs, API responses or database plaintext. Implement token-expiry check and safe configuration status.
3. Owner-only Pathao settings: connection status, token days left (warn under 7 days), default store, sender name/phone, default delivery type, item type Parcel, default weight, test connection. List stores and support creating a store only if package/API supports it.
4. Load cities, zones, areas through the integration. Cache results (e.g. 24 hours); cascade City → Zone → Area; store numeric IDs and display names; prefill frequent customers' last-used location.
5. After a sale is committed, dispatch a queued job to create a Pathao order. Use invoice number as `merchant_order_id` for idempotency. Include store, sender, recipient, address, city/zone/area IDs, delivery type (48 Normal, 12 On-demand per PRD), item type (2 Parcel), quantity, weight, description, special instructions, and collection amount.
6. `amount_to_collect` is balance due for Cash on Delivery and 0 otherwise. For partial payment + COD, collect only the remaining balance.
7. On success save consignment ID, status, delivery/courier charge, response-safe metadata and status log. Create exactly one linked courier-charge expense (source `pathao_charge`) as specified. Prevent duplicate expenses on retries.
8. On failure preserve sale/invoice, set courier status Failed, store a safe error message, show red/danger status with icon + text, and expose Retry. Retry with backoff (e.g. 3 attempts) and idempotency protection.
9. Poll non-final orders on a scheduled job (e.g. every 30 minutes) using `VIEW_ORDER`; record status history. Map provider states into Pending, Picked up, In transit, Delivered, Returned, Failed as appropriate. Do not invent a status mapping—document and test the mapping from actual API responses.
10. Delivered COD orders remain in COD to receive until Owner-authorized user marks them Settled. Store settlement timestamp and actor if supported; audit the action. Settlement should not alter sales revenue or create a second sale/payment.
11. Courier Orders screen: invoice, customer, consignment ID, status, COD amount, courier charge, settlement state, last update; filters by status/date; actions Retry, Refresh status, Open in Pathao, Mark COD settled.
12. Editing recipient/address/amount after a courier order exists must be blocked or limited to non-courier fields, with the PRD message telling the user to update in Pathao panel first.
13. If Pathao is down or token expired, sale still saves and dashboard warns “Courier connection problem.” If package lacks cancel-order or webhooks, use the specified manual merchant-panel warning and scheduled polling; do not claim unsupported capabilities.
14. Configure Laravel queue worker and scheduler instructions for local and production hosting.

**Verify:** test mocked provider success/failure, real test credentials if available, retry without duplicate order/expense, token expiry state, cascading cached locations, COD/partial payment collection, status polling, COD settlement and sale persistence during provider failure. Explicitly report anything that cannot be live-tested without credentials.

---

# Prompt 11 — Expenses, Categories, Attachments, and Recurring Expense Reminders

1. Implement editable expense categories with `pl_group` mappings from schema: `marketing_facebook`, `marketing_other`, `courier`, `packaging`, `rent_utilities`, `other`. System categories cannot be deleted; inactive categories remain available for history.
2. Seed default categories: Facebook Ads, Other Marketing, Packaging, Courier Charges (system), Rent, Electricity, Internet, Gas/Water, Transport, Repair & Maintenance, Tools & Equipment, Miscellaneous. Map each to the appropriate P&L line.
3. Expense form: date (default today), category, positive amount, payment method (cash/bKash/bank transfer/other), optional vendor, note, optional bill photo/PDF attachment.
4. Store attachments on a private disk; validate MIME type and size; authorize downloads through a controller; never expose private paths publicly.
5. List/filter by date range, category and search; show totals by category and a chart/ranked list. Facebook Ads is first-class and feeds dashboard ROAS and P&L.
6. Staff can add and view expenses; edit/delete is Owner-only by default (configurable). Audit all mutations. Do not permit salary/wage payments or leather/accessory purchases to be recorded as ordinary expenses.
7. Courier charges generated by Pathao and return-courier costs are linked, source-tagged expenses. Prevent duplicates and keep them traceable to the courier order/sale/return.
8. Recurring expenses: create monthly templates. On the 1st, show a prompt “Add this month’s [expense]?” with one-click confirmation. Never silently post an expense. Ensure one instance per template/month and audit the confirmation.
9. Build empty/loading/error states and mobile card layout; date filters update URL.

**Verify:** category-to-P&L mapping, private attachment authorization, Staff permissions, recurring reminder and duplicate prevention, courier expense idempotency, no payroll/material purchase duplication, and actual DB rows after hard reload.

---

# Prompt 12 — Salaried Employees and Monthly Payroll Ledger

Implement PRD §10.1 and the matching schema tables.

1. Owner-only Salaried Employees module. Employee profile: name, phone, designation, joining date, monthly salary, active/inactive. Soft-delete/archive only as schema allows; retain history.
2. Create one `salary_months` row per employee/month with base salary, bonus/overtime, deductions, payable = base + bonus − deductions. Unique employee/month constraint; editable base for that month if salary changes.
3. Payment entry: any number of payments on any date, amount, method, note, and type Salary or Advance. Link payment to employee and relevant month where applicable; advances must carry forward rather than being treated as additional earned expense.
4. Monthly sheet shows base, bonus/overtime, deductions, payable, paid in month, month balance, carry-forward and net balance. Positive means Tasa owes employee; negative means employee was over-paid/owes Tasa and must be shown with distinct violet status.
5. Status badges: Fully paid, Partially paid, Over-paid. Use icon + label + status tint.
6. Correctly compute carry-forward across months including irregular payment dates, advance payments and over-payment offsets. Define boundary behaviour with tests so a payment is never counted twice in both an earlier and later month.
7. P&L salary expense is the payable earned for the period, not cash paid. Payroll payments affect cash summary and balances, not earned salary expense a second time.
8. Printable employee statement and date/month filters. Sensitive data only sent to authorized users with Payroll permission.
9. No hard delete of payroll history; corrections should be audited and use supported compensating entries or controlled edits with history.
10. Audit employee edits, monthly sheet changes and every payment.

**Verify:** employee with no payment, partial payment, multiple irregular payments, advance, overpayment and next-month carry-forward; check totals against hand calculations and DB rows. Confirm Staff cannot open routes or retrieve payroll data.

---

# Prompt 13 — Wage Workers, Earnings, Running Ledger, Weekly Payout

Implement PRD §10.2 and schema §4.11.

1. Owner-only worker profile: name, phone, role, wage basis (`per_day`, `per_piece`, `fixed_weekly`), rate and active/inactive status.
2. Earnings entry: date/period, quantity (days/pieces or a unit suitable for fixed weekly), rate defaulting from profile but editable, calculated earned amount, note. Use exact schema fields and validate the selected basis so irrelevant quantity fields are not misleading.
3. Payment entry: date, amount, method and note. Payments can occur once or multiple times per week; preserve history.
4. Ledger table: date, earned, paid, running balance. Balance = total earned − total paid. Positive = Tasa owes worker; negative = worker over-paid/owes Tasa. Over-paid is violet, not danger/success.
5. Worker summary: earned this week, earned this month, paid, current balance. Date-range filter on every ledger.
6. Weekly Payout helper lists all workers and current balance, lets Owner enter a payment for selected workers and records each payment correctly. Do not create payment records for blank/zero selections.
7. Printable statement per worker. All payroll routes and exports require Payroll permission.
8. P&L wage expense is earnings accrued in the selected period, not payments. If “Cost price includes labour” is Yes, exclude wage expense from P&L to prevent double counting; default is No.
9. Ensure worker payments/advances do not accidentally become expenses twice. Audit profile, earning and payment changes.
10. Support safe correction workflows without hard-deleting ledger history.

**Verify:** per-day, per-piece and fixed-weekly examples; repeated payments; overpayment; running balance; weekly payout with mixed selected/unselected workers; P&L accrual; setting toggle; permission denial and audit records.

---

# Prompt 14 — Sales Report, Exports, Drill-Down, and Dashboard

Implement sales reporting and role-appropriate dashboard widgets. Every total must be computed from source records.

## Sales report
1. Date presets and custom range; default current month. Summary cards: invoice count, gross sales, discounts, returns, net sales, amount received, amount due.
2. By-product table: product, quantity sold, revenue, COGS and gross profit. COGS uses the `unit_cost` snapshot on sale lines. Cost/profit columns only appear in server response for users with the relevant permission.
3. Additional views: by day/trend, payment method, courier status and top customers.
4. Export CSV, Excel and PDF with current filters. Exports must use the same query and authorization rules as the page; never include hidden cost/profit data for Staff.
5. Every relevant number/row supports drill-down to underlying records. Cancelled sales are excluded; returns reduce revenue in the return date's period.

## Dashboard for all users
6. Today's sales count/amount; this month's sales; pending/failed courier orders; low-stock alerts for products/leather/accessories; latest 10 invoices; quick actions New Sale, Add Expense, Add Leather Purchase.
7. Staff dashboard must not include any cost, profit, payroll or sensitive COD/customer due widgets unless separately permitted.

## Owner-only dashboard
8. Net profit this month and gross margin; total expenses; COD to receive; customer dues; payroll balances (owed and over-paid); six-month sales vs expenses trend; Facebook Ads spend vs sales and ROAS (`sales ÷ ad spend`).
9. Use actual P&L calculation services rather than a separate inconsistent formula. For zero ad spend, display ROAS as “—”/not applicable, not infinity.
10. Chart.js via vue-chartjs or ApexCharts; square bars, no gradients/shadows/3D, maximum four series, direct labels where practical, accessible legend, title/takeaway, and View as table toggle. Empty state instead of blank chart.
11. Dashboard filters/date ranges where specified. All money uses BDT formatting and tabular numerals. All widgets have loading/error states.

**Verify:** compare report values with direct SQL and hand calculations across active, cancelled, returned and partially paid sales; check all exports; verify Staff payloads exclude restricted fields; test no-ad-spend ROAS; check dashboard numbers after hard reload.

---

# Prompt 15 — Profit & Loss Statement, Memo Section, Cash Summary, and Accountant-Grade Reconciliation

This is an Owner-only report. Use the exact PRD §11 structure and formulas. Do not substitute a generic expense-minus-sales dashboard.

1. Controls: date range (This month default; Last month, This quarter, This year, Custom), compare with previous period toggle, PDF/Excel export.
2. Build lines:
   - A Gross sales = invoice subtotals for non-cancelled sales in the selected period.
   - B Discounts.
   - C Sales returns/cancellations according to documented rules; returns reduce revenue in the return-date period.
   - D Net sales = A − B − C.
   - E Delivery charges collected from customers.
   - F Total revenue = D + E.
   - G Cost of items sold = quantity × cost snapshot on each invoice line, adjusted consistently for partial returns/cancellations.
   - H Gross profit = F − G; gross margin = H ÷ F.
   - I1 Facebook Ads; I2 Other Marketing.
   - J Courier charges (Pathao + return costs).
   - K Salaries earned for period.
   - L Wages earned for period, excluded if “Cost price includes labour” is Yes.
   - M Packaging.
   - N Rent & utilities (rent, electricity, internet, gas/water).
   - O Other expenses.
   - P Total operating expenses = I1 + I2 + J + K + L + M + N + O.
   - Q Net profit/loss = H − P; net margin = Q ÷ F.
3. Explicitly document how cancellation and return amounts are attributed to periods so reports do not double count the original sale and return. Only non-cancelled sales count. Use snapshot unit cost; never current product cost for historical sales.
4. Map editable expense categories to the correct P&L groups. Material purchases are not P&L expenses. Leather/accessory consumption does not itself create an expense. Payroll payments are not the expense; earned/payable amounts are.
5. Add memo section, not included in profit calculation:
   - leather purchased (money and sq ft) and consumed (sq ft, value at average cost);
   - accessories purchased and consumed;
   - closing stock value for finished products, leather and accessories;
   - COD receivable from Pathao (delivered, not settled);
   - customer dues;
   - payroll payable/(advances);
   - cash summary: customer payments + COD settlements versus expenses + payroll payments + material purchase payments, with net cash movement.
6. Charts: monthly revenue vs expenses vs profit; expense breakdown; top 5 profitable products; KPI strip with net profit, gross margin %, ad spend as % sales and ROAS.
7. Compare with previous period. Every number and P&L line is clickable and opens source records filtered to the matching period/category. Recompute from source rows; do not rely on stale stored totals.
8. Export PDF/Excel; totals and drill-down filters must match on-screen values. This report and its props/routes/exports are protected by P&L permission.
9. Implement and test the PRD worked example: gross sales ৳200,000; discounts ৳5,000; returns ৳10,000; net sales ৳185,000; delivery charges ৳8,000; total revenue ৳193,000; COGS ৳80,000; gross profit ৳113,000 (~58.5%); expenses Facebook Ads ৳25,000, courier ৳14,000, salaries ৳30,000, wages ৳20,000, rent/utilities ৳12,000, packaging+other ৳6,000; total expenses ৳107,000; net profit **৳6,000 (~3.1%)**.
10. Add reconciliation tests for every P&L line, zero denominators, negative/overpaid balances, returns across period boundaries, cost-includes-labour toggle and material purchase cash summary.

**Verify:** use seeded fixtures for the worked example and assert exact expected net profit ৳6,000; inspect underlying records through every drill-down; compare exports and direct SQL; verify unauthorized access returns 403 and sends no report data.

---

# Prompt 16 — Global Search, Polish, Empty/Error States, and Cross-Module UX

1. Implement top-bar global search for invoice number, customer name/phone and product, with permission-aware results. Fast product search target <300ms. Do not expose cost/profit data in results.
2. Standardize filters and URL state for sales, expenses, reports and ledgers. Provide removable filter chips and Clear all. Mobile filters open a full-screen sheet.
3. Replace all placeholder pages and dead links with real workflows. Every button must perform its labelled action or be visibly disabled with an explanation. No fake data, TODO success messages or inert controls.
4. Standardize empty states, skeleton loading, page error with Try again, inline field validation, error summary with anchor links on long forms, save/loading button states and unsaved-change warnings.
5. Use clear microcopy such as “Save sale”, “Add payment”, “Retry order”, “Balance due”. No humour in errors. Error messages explain what happened and what the user can do next; never show stack traces/raw exception text.
6. Confirmation dialogs for destructive actions explain consequences and require reasons where specified. Never auto-focus the destructive button.
7. Drawers are 480px on desktop and full-screen sheets on mobile with sticky action bars. Keep floating layer shadows only where UX guideline allows.
8. Tables use semantic HTML, `<th scope>`, sticky headers, aligned numeric columns, pagination, and mobile cards when possible. P&L and ledger tables become label/amount rows on mobile.
9. Check WCAG 2.2 AA, keyboard-only operation, visible focus, live announcements, tooltips/aria-labels, reduced motion, contrast, 44px touch targets and 200% zoom.
10. Remove any accidental product imagery, rounded-card styles, gradients, dark theme CSS, excessive animation, glass effects or decorative shadows that conflict with the guideline.

**Verify:** click through every visible control; run accessibility checks; inspect console/network errors; test 360px and 200% zoom; confirm filters survive refresh and role-specific menus are absent for unauthorized users.

---

# Prompt 17 — Automated Test Suite, Acceptance Tests, and Data Integrity Audit

Create a durable automated test suite, not just manual notes.

1. Add unit tests for money/quantity calculations, discounts, invoice numbering, payroll balances, stock calculations, P&L formulas, payment statuses and courier amount-to-collect.
2. Add feature tests for auth, permissions, user management, product import, production/adjustment, leather/accessory purchase/consumption, POS, invoices, add payment, cancellation, partial/full returns, expenses, recurring reminders, payroll, reports, exports and settings.
3. Add integration tests for Pathao using a fake/mock HTTP client or package adapter. Cover provider success, provider error, timeout, expired token, retry/backoff, duplicate retry, status mapping, duplicate courier expense and COD settlement.
4. Test concurrency/idempotency: two simultaneous sales cannot allocate the same invoice number or oversell stock unless allowed; double-submit cannot create duplicate sales/payments/courier orders/expenses.
5. Test authorization by calling URLs and APIs directly, not only checking whether menu items are hidden. Assert restricted fields are absent from serialized payloads and exports.
6. Implement all database verification queries in schema §9 and run them in test/QA environments. Include stock ledger vs cached stock, stored totals vs source sums, orphan references, duplicate invoice sequence, invalid return quantities and expense/source relationships.
7. Test acceptance criteria AC-1 through AC-14 explicitly:
   - AC-1 customer required / no walk-in.
   - AC-2 unique sequential invoice / no duplicate Save.
   - AC-3 Pathao order + COD amount.
   - AC-4 courier failure leaves sale intact and Retry avoids duplicates.
   - AC-5 stock deduction/restoration and movement history.
   - AC-6 leather/accessory stock and weighted average.
   - AC-7 report values versus source rows.
   - AC-8 employee monthly carry-forward/overpayment.
   - AC-9 worker running balance/overpayment.
   - AC-10 P&L worked example nets ৳6,000 and drills down.
   - AC-11 Staff blocked from payroll/P&L and sensitive fields.
   - AC-12 role changes apply on next request.
   - AC-13 mobile widths.
   - AC-14 invoice A4/A5/thermal.
8. Use factories/seed fixtures that do not pollute production. Never run destructive test commands against production.
9. Produce a test report listing command, pass/fail, assertion, and remaining blocker. Fix failures rather than marking them “known” without explanation.

**Verify:** run the full suite and frontend build; inspect test DB; run schema verification queries; report actual pass/fail counts and unresolved issues.

---

# Prompt 18 — Performance, Backup/Restore, Security Review, and Production Deployment

Prepare the app for real shop use.

1. Performance targets: POS page <2 seconds, product search <300ms, reports for one year of data <3 seconds on representative data. Add indexes and eager loading based on query evidence; avoid N+1 queries and unbounded lists.
2. Paginate large tables; queue exports/PDF work if appropriate; cache Pathao city/zone/area data for 24h and invalidate safely.
3. Configure production environment with `APP_ENV=production`, `APP_DEBUG=false`, correct `APP_URL`, secure session/cookie settings, HTTPS, CSRF, rate-limited login, validated uploads, safe output, and least-privilege DB credentials. Never commit `.env`.
4. Configure production queue worker and scheduler. Document deployment-specific commands for migration, config/route/view cache, frontend build, queue restart and scheduler cron. Do not invent hosting-specific paths; inspect the actual host setup first.
5. Configure daily database backup with 14-day retention and secure storage outside public web root. Document restore steps and perform a restore drill into a separate test database.
6. Review file permissions and private attachments. Ensure uploaded expense files cannot execute and invoice HTML/CSS cannot execute PHP/JS.
7. Verify audit logs are append-only, business records retain history, FK constraints are intact, and database transactions are used for money/stock.
8. Test supported latest Chrome, Edge, Firefox, Safari, mobile Chrome and mobile Safari where available.
9. Create deployment documentation for local setup, production `.env` variables (names only, never values), queue worker, scheduler, backup, restore, storage links/private disk, Pathao credentials setup, health checks and rollback procedure.
10. Create a pre-launch checklist and safe rollback plan. Do not run migrations or destructive commands on production without explicit approval and a verified backup.

**Verify:** run production build, security review, backup/restore drill, performance tests on representative data, queue/scheduler health checks and final acceptance suite. Record evidence, not assumptions.

---

# Prompt 19 — Final End-to-End UAT With Tasa Data and Release Sign-Off

Act as QA lead and shop operator. Do not add new features during this prompt unless a defect blocks acceptance.

1. Use a copy of real Tasa product/customer data in a protected staging environment. Provide and validate the Excel product import template. Do not expose personal customer data in logs or fixtures.
2. Run realistic workflows:
   - Create/edit product and category; production entry; stock adjustment.
   - Buy/consume leather and accessories; compare current stock and weighted average.
   - Create customer; make a catalogue-product sale; make a custom-made sale; partial payment; add later payment.
   - Generate invoice, print A4/A5, download PDF, inspect thermal preview.
   - Create Pathao order; test provider failure/retry; poll status; settle delivered COD.
   - Cancel a sale; process partial and full return; verify stock/revenue/expenses/audit.
   - Record expenses and recurring reminder.
   - Run monthly salary sheet with irregular payments/advance/overpayment; run worker ledger and weekly payout.
   - Compare Sales Report, Dashboard, P&L and cash memo against source records.
   - Test Owner, Staff and custom role with direct URL/API attempts.
3. Complete design QA at 360, 390, 768, 1024, 1280 and 1440px; keyboard-only; 200% zoom; Bangla; `৳`; status chips; print layouts; empty/error/loading states.
4. Verify every requirement tracker item against the app and database. Mark an item “verified” only when there is reproducible evidence. Leave blocked items clearly marked with reason and owner.
5. Check logs and console for errors, failed requests, PHP warnings, migrations drift, failed jobs, queue failures, duplicate expenses, duplicate invoice numbers and audit gaps.
6. Deliver:
   - completed feature checklist linked to PRD/schema/UX sections;
   - test report with commands and real outcomes;
   - list of known issues and severity;
   - deployment/backup/restore guide;
   - Pathao credential/account prerequisites, if still outstanding;
   - go-live recommendation: Ready / Not Ready, with evidence.
7. Do not claim a feature is complete merely because a page exists. Confirm its data persists after reload and its side effects match the business rules.

**Release gate:** no unresolved critical/high severity security, stock, payment, invoice, payroll, P&L, or data-loss defects. AC-1 through AC-14 must pass or be explicitly blocked by external prerequisites.

---

## Final master checklist — every module that must exist

- [ ] Authentication; no public registration; login throttling; active/inactive users; optional Owner 2FA.
- [ ] Locked Owner, Staff, custom roles, per-action permission matrix, server-side policies, sensitive-field redaction.
- [ ] Settings, general toggles, categories/units, invoice branding, safe template editor, Pathao settings.
- [ ] Append-only audit log; import logs; monthly invoice sequence.
- [ ] Dashboard: shared widgets and Owner-only financial/payroll/COD/dues/ROAS widgets.
- [ ] POS with phone-first customer lookup, required customer info, product/SKU search, custom item, editable prices by permission, discounts, delivery charge, partial payments, methods, stock validation, idempotency.
- [ ] Invoices/sales list, filters, print/PDF, A4/A5/80mm, add payment, cancel reason, full/partial returns, stock restoration.
- [ ] Customer profiles, order history and computed balances; optional Pathao success-rate hint.
- [ ] Pathao location dropdowns/cache, queued order creation, retry/backoff, consignment ID, status polling, status logs, courier expense, COD receivable/settlement, provider failure handling.
- [ ] Products/categories, production entries, stock movement ledger, low stock, Excel import preview/row errors/logs.
- [ ] Leather catalogue, purchases, consumption, sq-ft stock, weighted average, stock value, adjustments.
- [ ] Accessories catalogue, unit-aware purchases/consumption, weighted average, stock value, adjustments.
- [ ] Shared stock adjustments with mandatory reason/note rules and audit.
- [ ] Expenses/categories, private attachments, Facebook Ads reporting, recurring expense reminders, courier/return costs.
- [ ] Salaried employees, monthly payable sheet, bonus/deductions, flexible payments/advances, carry-forward and overpayment.
- [ ] Wage workers, all three wage bases, earnings, payments, running ledger, weekly payout, printable statements.
- [ ] Sales report summaries, product/day/payment/courier/customer views, role-safe CSV/Excel/PDF exports.
- [ ] Accountant-style P&L, correct COGS snapshot, earned payroll, cost-includes-labour toggle, memo section, cash summary, charts and drill-down.
- [ ] Responsive dashboard and all modules, accessibility, keyboard support, no horizontal overflow.
- [ ] Automated tests, schema verification queries, performance, security, backups, restore drill, deployment guide and UAT evidence.

## Important implementation note

These prompts deliberately separate the work so a coding agent can finish and verify one layer before moving forward. If the agent has limited context, run one prompt at a time and keep the three source documents available. Do not skip prompts simply because a screen looks complete.
