# Tasa POS — Requirements Traceability & Implementation Tracker

| Metadata | Details |
|---|---|
| **Product** | Tasa POS — Leather-goods Maker & Online Seller POS, Inventory, Payroll & P&L System |
| **Document Version** | 1.0 (Audit & Baseline Phase) |
| **Date** | 11 October 2026 |
| **Source Specifications** | `Tasa-POS-PRD.md` v1.0, `Tasa-POS-Database-Schema.md` v1.0, `Tasa-POS-Design-UX-Guideline.md` v1.0 |
| **Execution Roadmap** | `Tasa-POS-Step-by-Step-Implementation-Prompts.md` (Prompts 0 through 18) |
| **Stack Baseline** | Laravel 13.17 (`laravel/vue-starter-kit`), PHP 8.5.8, Vue 3.5.13, Inertia.js 3.0, Tailwind CSS 4.1.1, MySQL / MariaDB 10.4.32, Vite 8.0.0 |

---

## 1. Classification & Status Legend

| Status | Definition |
|---|---|
| **`[MISSING]`** | Feature, rule, table, route, UI component, or test does not exist in the codebase. |
| **`[PARTIAL]`** | Scaffolding or boilerplate exists (e.g. from starter kit), but business requirements or specification details are not yet fulfilled. |
| **`[IMPLEMENTED-UNVERIFIED]`** | Code is written, but has not yet been verified against database integrity queries, browser testing, or automated test suites. |
| **`[VERIFIED]`** | Proven with concrete database query results, passing automated tests, and interactive UI inspection across required breakpoints. |

---

## 2. PRD Requirements Traceability (`Tasa-POS-PRD.md`)

### §1 Product Overview & Scope Boundaries
| Section | Requirement / Business Rule | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| PRD §1.1 | Online shop, leather goods, shipped via courier (Pathao). Every sale requires customer name, phone, address. | `[MISSING]` | None | Phase 3 / Prompt 7 | AC-1: Validation rejects sale submission if customer name, BD phone, or address is blank. |
| PRD §1.2 | Text-only POS web app with auto invoice generation, Pathao courier auto-dispatch, 3-tier inventory, expenses, 2-tier payroll, P&L, role permissions. | `[MISSING]` | None | Phases 0–6 / Prompts 1–16 | All modules accessible to authorised users with expected flows. |
| PRD §1.3 | Guiding principle: "Keep it simple", one job per screen, sale in under a minute, no jargon for staff. | `[MISSING]` | None | Phase 0–7 / Prompts 1–17 | UX inspection against design guideline §1.2. |
| PRD §1.4 | **Out of Scope (Hard Rules)**: No product images in POS, No BOM, No walk-in/anonymous customers, No tax/VAT, No wholesale tiers, No multi-branch, No loyalty points, No customer storefront, No double-entry ledgers/balance sheet. | `[VERIFIED]` | Schema & code contain no image/BOM/tax fields | Continuous | Codebase review confirms zero out-of-scope fields or modules introduced. |

### §2 Users & Roles
| Section | Requirement / Business Rule | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| PRD §2.1 | **Owner Role**: Full system access. Cannot be restricted, deleted, or deactivated by any user. | `[MISSING]` | `roles` table missing | Phase 1 / Prompt 3 | Database check: `roles.is_locked = 1`. Attempt to delete or deactivate Owner returns 403. |
| PRD §2.2 | **Staff Role**: Access to POS, Invoices, Customers, Inventory, Add Expenses. NO Salary/Wages, NO P&L, NO Role management, NO Settings. | `[MISSING]` | None | Phase 1 / Prompt 3 | AC-11: Staff receives 403 on forbidden URLs and APIs; forbidden props excluded from responses. |
| PRD §2.3 | **Custom Roles**: Owner can create roles and configure granular permissions across modules. | `[MISSING]` | None | Phase 1 / Prompt 3 | Permissions matrix UI functional; assignments update database and enforce immediately on next request. |

### §3 Core Business Rules (BR-1 to BR-12)
| Rule | Requirement | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| BR-1 | Currency BDT (`৳`), Timezone `Asia/Dhaka`, Dates formatted `DD MMM YYYY`. | `[VERIFIED]` | `config/app.php`, `resources/js/lib/formatters.ts`, `tests/Feature/Foundation/DesignSystemAndShellTest.php` | Phase 0 / Prompt 1 | `config('app.timezone') === 'Asia/Dhaka'`. Dates format as `11 Oct 2026`. ৳ symbol and Bangla verified in browser subagent and Pest test. |
| BR-2 | No sale without customer name, phone, delivery address. Phone validated as Bangladeshi mobile (`01XXXXXXXXX`, 11 digits, regex `^01[3-9]\d{8}$`). | `[MISSING]` | None | Phase 3 / Prompt 7 | AC-1: Validation tests pass; database insert rejects non-matching phone. |
| BR-3 | A saved sale always produces an invoice with a unique sequential number (`TASA-YYMM-0001`). Sequence resets monthly. | `[MISSING]` | None | Phase 3 / Prompt 7 | AC-2: `invoice_sequences` row increments atomically; duplicate submit returns existing sale without sequence gap. |
| BR-4 | Sales are never hard-deleted. Sales can only be Cancelled (with mandatory reason) or Returned. | `[MISSING]` | None | Phase 3 / Prompts 7, 10 | Schema check: `sales` table has NO `deleted_at`. Cancel and return actions audited with reason. |
| BR-5 | Raw leather and accessories purchases are inventory assets, not expenses. Cost flows to P&L only via COGS at sale time. | `[MISSING]` | None | Phases 2, 5 / Prompts 5, 11 | P&L query does not include leather/accessories purchases in operating expenses. |
| BR-6 | COGS = quantity sold × product's cost price at time of sale. Cost price is snapshotted on `sale_items.unit_cost`. | `[MISSING]` | None | Phase 3 / Prompt 7 | Modifying `products.cost_price` after sale does not alter `sale_items.unit_cost` or past P&L COGS. |
| BR-7 | Leather/accessory consumption is stock tracking only; it creates NO P&L expense. | `[MISSING]` | None | Phase 2 / Prompts 5, 6 | Consumption entries decrement stock without generating rows in `expenses`. |
| BR-8 | Courier charge per order recorded as an expense automatically (source = `pathao_charge`). | `[MISSING]` | None | Phase 4 / Prompt 9 | Pathao order success creates exactly 1 row in `expenses` referencing `courier_order_id`. |
| BR-9 | Payroll recognised in P&L when earned/due for period (accrual basis), not when paid. Advances are balance-sheet assets. | `[MISSING]` | None | Phases 5, 6 / Prompts 12, 13, 15 | P&L line K = `salary_months.payable`, Line L = `worker_earnings.amount`. Payments do not impact P&L expense total. |
| BR-10 | Staff users never see P&L, payroll, or cost price/profit, enforced on server-side. | `[MISSING]` | None | Phase 1 / Prompt 3 | AC-11: Controller queries strip `unit_cost`, `cost_price`, `cogs`, and profit fields for Staff role. |
| BR-11 | Money fields use `DECIMAL(12,2)`. Leather quantities use `DECIMAL(12,3)` in sq ft. Product/sale qty are integers. Accessories use `DECIMAL(12,3)`. | `[MISSING]` | None | Phase 0 / Prompt 2 | Database column definition inspection in `information_schema.columns`. |
| BR-12 | Every create/edit/cancel/delete writes to append-only `audit_logs` (user_id, event, auditable_type, auditable_id, old/new json, timestamp). | `[MISSING]` | None | Phase 1 / Prompt 3 | Audit table inspection after mutations; audit log has no update/delete routes. |
| Decision O-1 | Default "Cost price includes labour" to **No**; worker wages are a separate operating expense in P&L. | `[MISSING]` | None | Phase 1 / Prompts 2, 3 | Setting `payroll.cost_price_includes_labour = 0` seeded by default. |

### §4 Module Map & Navigation
| Section | Module / Route | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| PRD §4 | Top-level: Dashboard | `[PARTIAL]` | `resources/js/pages/Dashboard.vue` (starter kit only) | Phase 6 / Prompt 16 | Distinct views for Owner and Staff rendered. |
| PRD §4 | Sales: New Sale (POS), Invoices / Sales List, Customers, Courier Orders | `[MISSING]` | None | Phases 3, 4 / Prompts 7, 8, 9, 10 | Route group `/sales/*` defined with permissions. |
| PRD §4 | Inventory: Products, Leather (Stock/Purchases/Consumption), Accessories, Stock Adjustments | `[MISSING]` | None | Phase 2 / Prompts 4, 5, 6 | Route group `/inventory/*` defined with permissions. |
| PRD §4 | Expenses: List, Add, Categories | `[MISSING]` | None | Phase 5 / Prompt 11 | Route `/expenses` defined with permissions. |
| PRD §4 | Payroll: Salaried Employees, Wage Workers | `[MISSING]` | None | Phase 5 / Prompts 12, 13 | Route group `/payroll/*` protected (Owner only). |
| PRD §4 | Reports: Sales Report, Profit & Loss | `[MISSING]` | None | Phase 6 / Prompts 14, 15 | Route group `/reports/*` defined with permissions. |
| PRD §4 | Settings: Users & Roles, Invoice Branding, Pathao Courier, General | `[PARTIAL]` | `routes/settings.php` (starter kit profile only) | Phase 1 / Prompt 3 | Route group `/settings/*` protected (Owner only). |

### §5 Dashboard
| Section | Feature / Widget | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| PRD §5.1 | All-user widgets: Today's sales (count & amount), This month's sales, Pending/failed Pathao orders, Low-stock alerts (products/leather/accessories), Latest 10 invoices, Quick action buttons. | `[MISSING]` | None | Phase 6 / Prompt 16 | Widget numbers match raw database records for the current date/month. |
| PRD §5.2 | Owner-only widgets: Net profit & gross margin %, Total expenses, COD to receive, Customer dues, Payroll balances (owed/overpaid), 6-month sales vs expenses trend chart, Facebook ad spend vs sales & ROAS. | `[MISSING]` | None | Phase 6 / Prompt 16 | Financial widgets hidden on server-side when authenticated user is Staff. |

### §6 Sales Management
| Section | Feature / Subfeature | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| PRD §6.1 | POS screen: Phone-first lookup with auto-fill of customer profile and Pathao cascading City/Zone/Area dropdowns. | `[MISSING]` | None | Phase 3 / Prompt 7 | Typing known phone auto-populates fields; new phone prompts entry. |
| PRD §6.1 | POS Cart: Product search (text-only, no images), SKU/name match, unit price prefill, edit price permission check, cart totals (Subtotal, Discount flat/%, Delivery charge, Grand total). | `[MISSING]` | None | Phase 3 / Prompt 7 | Price editing blocked if user lacks `sales.edit_price` permission. |
| PRD §6.1 | POS Payment: Partial payment support, Cash/COD/bKash/Bank Transfer, balance due calculation. For COD, Pathao collection amount equals balance due. | `[MISSING]` | None | Phase 3 / Prompt 7 | COD collection amount matches `grand_total - paid_amount`. |
| PRD §6.1 | POS Delivery: Send via Pathao (default) vs Hand-delivery. Weight, delivery type (Normal/On-demand), special instructions. | `[MISSING]` | None | Phase 3 / Prompt 7 | Delivery mode persists properly in `sales` and `courier_orders`. |
| PRD §6.1 | POS Rules: Stock deduction on save, warning when qty > available, setting `inventory.allow_negative_stock` (default No). Custom item support (no stock deduction). Double-click idempotency token protection. Hardware-scanner friendly search input. | `[MISSING]` | None | Phase 3 / Prompt 7 | AC-2 & AC-5: Stock decreases, custom items handled, double-click produces single sale. |
| PRD §6.2 | Invoices / Sales List: Table with invoice no, date, customer, phone, total, paid, due, payment status, courier status, actions. Filters by date range, payment status, courier status, method, search. | `[MISSING]` | None | Phase 3 / Prompt 8 | Filtered query results verified against database. |
| PRD §6.2 | Invoice Actions: View, Print, Download PDF, Add payment, Cancel sale (reason required), Mark returned (line-item support), Retry Pathao. | `[MISSING]` | None | Phase 3 / Prompts 8, 10 | Stock restored on cancel/return; return courier expense logged if applicable. |
| PRD §6.3 | Invoice Document & Numbering: Monthly format `TASA-YYMM-0001` (Decision O-5). Contents: shop branding, invoice details, item lines, summary bar, footer terms. Formats: A4, A5, 80mm thermal receipt. | `[MISSING]` | None | Phase 3 / Prompt 8 | AC-14: Print CSS and thermal preview tested; numbers sequential without gaps. |
| PRD §6.3 | Invoice Branding Settings: Logo, shop details, footer terms, paper size, safe HTML/CSS template editor (sanitised, placeholders `{{invoice_no}}` etc., **No PHP**). | `[MISSING]` | None | Phase 3 / Prompt 8 | Custom template editor rejects PHP/eval and replaces safe tokens cleanly. |
| PRD §6.4 | Customers: Unique phone key `01XXXXXXXXX`, order history, spent total, outstanding dues. Optional Pathao delivery success rate hint on sale screen. | `[MISSING]` | None | Phase 3 / Prompt 7 | Customer profile dynamically computes order count and outstanding balance. |
| PRD §6.5 | Sales Report: Date range filter (default this month), summary cards (gross, discount, returns, net, received, due), by-product table (qty sold, revenue, COGS, gross profit), export CSV/Excel/PDF. | `[MISSING]` | None | Phase 6 / Prompt 14 | AC-7: Report numbers reconcile with database query §8.1. |

### §7 Pathao Courier Integration
| Section | Feature / Subfeature | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| PRD §7.1 | Integration architecture: Package `enan/pathao-courier` (or thin internal `PathaoService` fallback if package fails Laravel 13 compatibility). | `[MISSING]` | None | Phase 4 / Prompt 9 | Package/Service passes unit tests calling Pathao sandbox/mock endpoints. |
| PRD §7.2 | Pathao Settings: Connection status, token days left warning (<7 days), default store, default delivery type/weight. Credentials stored strictly in `.env`. | `[MISSING]` | None | Phase 4 / Prompt 9 | Token expiry meter functional; credentials never exposed in UI or database. |
| PRD §7.3 | Address capture: Cascading City → Zone → Area dropdowns cached for 24h (`Cache::remember`). Address ≥ 10 chars. | `[MISSING]` | None | Phase 4 / Prompt 9 | Dropdowns load from cache; validation enforces 10-character minimum address. |
| PRD §7.4 | Auto-create flow: Sale saves first; queued job calls Pathao `CREATE_ORDER` with merchant_order_id = invoice_no; on success store consignment ID and auto-create courier charge expense (BR-8); on failure mark Failed with retry backoff. | `[MISSING]` | None | Phase 4 / Prompt 9 | AC-3 & AC-4: Queue worker creates consignment ID; failure leaves sale intact with Retry button. |
| PRD §7.5 | Courier Orders screen: Table of all courier orders, statuses, COD amounts, settlement status, actions (Retry, Refresh status, Open in Pathao, Mark COD settled). | `[MISSING]` | None | Phase 4 / Prompt 9 | Settle COD updates `courier_orders.cod_settled_at` and creates `sale_payments` row. |
| PRD §7.6 | Edge cases: Block address/amount edit on sale once consignment exists; handle API outages gracefully without failing sales. | `[MISSING]` | None | Phase 4 / Prompt 9 | Attempt to edit customer on dispatched order is blocked with instructional warning. |

### §8 Inventory Management
| Section | Feature / Subfeature | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| PRD §8.1 | Products (finished goods): Name, auto-generated SKU, category, cost price (manual), selling price, current stock, min stock alert level, active toggle. No images. No BOM. | `[MISSING]` | None | Phase 2 / Prompt 4 | Product CRUD verified; stock deductions tracked via `product_stock_movements`. |
| PRD §8.1 | Product stock movements: Types (`opening`, `production`, `sale`, `sale_cancel`, `return`, `adjustment`). Stock increases via production entries; decreases on sale. Excel import with template and row-level error validation. | `[MISSING]` | None | Phase 2 / Prompt 4 | Excel import adds products and opening stock; ledger integrity check 1 returns 0 rows. |
| PRD §8.2 | Leather (raw material): Unit `sq ft` (`DECIMAL(12,3)`). Items catalogue (name, colour/grade, min stock). Purchases (date, supplier, sq ft, rate, total, paid). Consumption (date, sq ft, note — manual entry). | `[MISSING]` | None | Phase 2 / Prompt 5 | AC-6: `v_leather_stock` view matches formula: Current stock = Σpurchased − Σconsumed ± adjustments. Weighted avg cost & value verified. |
| PRD §8.3 | Accessories (raw material): Variable units (pcs, set, meter, kg). Items, purchases, consumption. Identical stock and valuation formulas. | `[MISSING]` | None | Phase 2 / Prompt 6 | AC-6: `v_accessory_stock` view matches formula and prevents negative stock unless permitted. |
| PRD §8.4 | Stock Adjustments: Item type (`product`, `leather`, `accessory`), signed qty, mandatory reason (`damaged`, `lost`, `count_correction`, `other`). Always audited. | `[MISSING]` | None | Phase 2 / Prompt 6 | Adjustment writes to `stock_adjustments` and `product_stock_movements` (if product) inside transaction. |
| PRD §8.5 | Inventory Permissions: Staff can view/add/edit. Cost prices and purchase rates hidden from Staff unless granted `inventory.view_costs`. Delete is Owner-only. | `[MISSING]` | None | Phase 2 / Prompts 4, 5, 6 | Cost columns and JSON keys omitted from responses when viewed by Staff. |

### §9 Expenses
| Section | Feature / Subfeature | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| PRD §9.1 | Entry fields: Date, category, amount, payment method, vendor, note, file attachment (bill photo/PDF). | `[MISSING]` | None | Phase 5 / Prompt 11 | Expense CRUD functional; attachments stored securely on private disk. |
| PRD §9.2 | System Categories & P&L Grouping: Facebook Ads (`marketing_facebook`), Other Marketing, Packaging, Courier Charges (`courier`), Rent & Utilities, Transport, Repairs, Tools, Misc. Facebook Ads treated as first-class category. | `[MISSING]` | None | Phase 5 / Prompt 11 | Categories seeded with `pl_group`; Facebook Ads highlighted on dashboard and P&L. |
| PRD §9.3 | Boundary rules: Payroll is NOT an expense entry (comes from payroll module). Material purchases are NOT expenses (BR-5). | `[MISSING]` | None | Phase 5 / Prompt 11 | P&L query does not duplicate payroll or inventory purchases in expense totals. |
| PRD §9.4 | Recurring Expenses: Mark monthly expenses (rent, internet). Prompt on the 1st of month ("Add this month's rent?") requiring 1-click confirmation, NOT silent auto-posting. | `[MISSING]` | None | Phase 5 / Prompt 11 | Dashboard displays recurring expense banner on 1st of month; confirming creates expense row. |

### §10 Payroll — Salary & Wages
| Section | Feature / Subfeature | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| PRD §10.1 | Salaried Employees: Profile (name, phone, designation, monthly salary, status). Monthly salary sheet (base + bonus − deduction = payable). Flexible payment records (start/mid/end of month, advances). | `[MISSING]` | None | Phase 5 / Prompt 12 | AC-8: Month balance, carry-forward, and net balance compute accurately. Positive = owed to employee; negative = advance/overpaid. |
| PRD §10.2 | Wage Workers: Profile with wage basis (`per_day`, `per_piece`, `fixed_weekly` — Decision O-2). Earnings entries (days/pieces × rate). Payment entries. Running statement ledger (`balance = earned - paid`). | `[MISSING]` | None | Phase 5 / Prompt 13 | AC-9: Ledger displays running balance; negative balance marked distinctly in violet as "owes Tasa". |
| PRD §10.2 | Weekly Payout Helper: Summary table of all workers with current balances and 1-click payout drawer. | `[MISSING]` | None | Phase 5 / Prompt 13 | Quick payout creates `worker_payments` entry and adjusts running balance. |
| PRD §10.3 | Payroll in P&L: Accrual basis — Salary expense = Σ payable for month; Wage expense = Σ earned in period. Advances sit as balance sheet memos, not P&L expenses. | `[MISSING]` | None | Phases 5, 6 / Prompts 12, 13, 15 | P&L lines K and L verify against `salary_months` and `worker_earnings`. |

### §11 Profit & Loss Statement
| Section | Feature / Subfeature | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| PRD §11.1 | Statement Layout: Revenue (Gross A − Discounts B − Returns C = Net D + Delivery collected E = Total Revenue F); COGS (G); Gross Profit (H = F − G); Operating Expenses (Marketing I1/I2 + Courier J + Salaries K + Wages L + Packaging M + Rent/Utilities N + Other O = Total OPEX P); Net Profit (Q = H − P). | `[MISSING]` | None | Phase 6 / Prompt 15 | AC-10: Worked example test case yields Gross Profit ৳113,000 (58.5%) and Net Profit ৳6,000 (3.1%). |
| PRD §11.1 | Decision O-1 P&L toggle: If "Cost price includes labour" = Yes, exclude Line L (Wages) from Operating Expenses P. Default is No (include Wages). | `[MISSING]` | None | Phase 6 / Prompt 15 | Query §8.2 skips line L when setting `payroll.cost_price_includes_labour = 1`. |
| PRD §11.2 | Memo Financials (for information only): Leather bought/used, Accessories bought/used, Closing stock values, COD receivable from Pathao, Customer dues, Payroll payables/advances, Net cash summary (Cash In vs Cash Out). | `[MISSING]` | None | Phase 6 / Prompt 15 | Memo values display below P&L statement without affecting net profit. |
| PRD §11.4 | Visuals: Monthly revenue vs expenses vs profit bar chart, ranked expense horizontal bars, top 5 profitable products, KPI strip. | `[MISSING]` | None | Phase 6 / Prompt 15 | Charts render with square corners, solid fills, and accessible "View as table" toggle. |
| PRD §11.5 | Accuracy & Drill-down: Recomputed from source records (no cached totals that drift). Returns reduce revenue in return date's period. Every figure has drill-down drawer to underlying records. | `[MISSING]` | None | Phase 6 / Prompt 15 | Clicking row opens drill-down drawer with exact source rows. |

### §12 Role Management & Access Control
| Section | Feature / Subfeature | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| PRD §12.1 | User management: Name, email/phone, password, single assigned role, activate/deactivate (keeps history), forced password reset. Owner cannot be deactivated. | `[PARTIAL]` | Starter kit user model without `is_active` | Phase 1 / Prompt 3 | User deactivation prevents login without deleting historical sales/audit rows. |
| PRD §12.2 | Permission Matrix: Checkbox grid (rows = modules, columns = View/Add/Edit/Delete + special permissions). Locked Owner row. Sensitive rows highlighted. Sticky Save/Discard bar. Accordions on mobile. | `[MISSING]` | None | Phase 1 / Prompt 3 | Role matrix UI updates Spatie permissions; changes effective on next request (AC-12). |
| PRD §12.3 | Server-side enforcement: Gates/policies and route middleware. 403 returned on unauthorized endpoints. Sensitive props omitted from Inertia responses. | `[MISSING]` | None | Phase 1 / Prompt 3 | AC-11: Feature tests prove Staff cannot open forbidden routes or receive hidden props. |

### §13 UI / UX Requirements
| Section | Requirement | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| PRD §13.1 | Light theme only, white base (`#F4F6F6` page, `#FFFFFF` surfaces), deep teal `#0B6E5E` accent, square corners (`border-radius: 0`), 1px borders, no gradients/glass/blur/dark mode. Hanken Grotesk font + Lineicons outline. | `[PARTIAL]` | `resources/css/app.css` (currently has rounded corners and dark mode tokens) | Phase 0 / Prompt 1 | Inspection of CSS and DOM: zero `rounded-*` classes, zero gradients, zero dark mode selectors. |
| PRD §13.2 | Simplicity: Plain labels, one primary teal button per screen, destructive actions require confirmation dialog with reason, smart defaults. | `[MISSING]` | None | Phase 0 / Prompt 1 | UI inspection across all screens. |
| PRD §13.3 | Responsiveness: Mobile (<640px) bottom nav & stacked POS & card tables; Tablet (640-1024px) icon rail; Desktop (>1024px) fixed 248px sidebar & 2-pane POS. Usable at 375px, 768px, 1280px. | `[MISSING]` | None | Phase 0–7 / Prompts 1, 17 | AC-13: Interactive browser testing at 360px, 390px, 768px, 1024px, 1280px, 1440px with no horizontal overflow. |

### §14 Data Model
| Section | Requirement | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| PRD §14 | 21 core tables defined: `users`, Spatie roles/permissions, `customers`, `products`, `product_stock_movements`, `sales`, `sale_items`, `sale_payments`, `sale_returns`, `courier_orders`, `leather_items`, `leather_purchases`, `leather_consumptions`, `accessory_items`, `accessory_purchases`, `accessory_consumptions`, `stock_adjustments`, `expense_categories`, `expenses`, `employees`, `salary_months`, `salary_payments`, `workers`, `worker_earnings`, `worker_payments`, `settings`, `audit_logs`. | `[VERIFIED]` | Migrations `000001`–`000016`, Eloquent models `app/Models/*` | Phase 0 / Prompt 2 | Database inspection confirms all 48 tables and views created with foreign keys, checks, generated columns, and seeders. |

### §15 Non-Functional Requirements
| Section | Requirement | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| PRD §15.1 | Performance: POS loads < 2s; product search < 300ms; 1-year reports < 3s. | `[MISSING]` | None | Phase 7 / Prompt 18 | Benchmarking tests on seeded database. |
| PRD §15.2 | Security: CSRF, XSS safe, server-side auth, `.env` encrypted secrets, rate-limited login. | `[PARTIAL]` | Laravel Fortify defaults | Continuous / Prompts 1, 3 | Security tests pass. |
| PRD §15.3 | Reliability & Integrity: Queued Pathao jobs with retries; DB transactions with row locks around sales and inventory; idempotent submit token. | `[MISSING]` | None | Phases 3, 4 / Prompts 7, 9 | Database verification queries 2, 3, 5, 6 return 0 rows. |
| PRD §15.4 | Backup & Retention: Automated daily backup script, 14-day retention. No hard deletes. | `[MISSING]` | None | Phase 7 / Prompt 18 | Backup command tested and restore verified. |

### §17 Acceptance Criteria (AC-1 to AC-14)
| ID | Acceptance Test Description | Status | Concrete File / Route / Table | Target Phase / Prompt | Verification Method & Evidence |
|---|---|---|---|---|---|
| **AC-1** | Sale cannot be saved without customer name, valid BD phone (`^01[3-9]\d{8}$`), and address. No walk-in option exists. | `[MISSING]` | None | Phase 3 / Prompt 7 | Automated test attempts invalid sales; verify 422 error and no database row. |
| **AC-2** | Saving a sale creates exactly one invoice with next monthly sequential number (`TASA-YYMM-0001`); double-click does not duplicate. | `[MISSING]` | None | Phase 3 / Prompt 7 | Test concurrent submissions with same `idempotency_token`; verify single sale row and consecutive numbers. |
| **AC-3** | Saving sale with *Send via Pathao* creates Pathao courier order; `consignment_id` stored; `amount_to_collect` = balance due for COD, 0 otherwise. | `[MISSING]` | None | Phase 4 / Prompt 9 | Queue job test mocks Pathao API; verify `courier_orders` row and collection amount calculation. |
| **AC-4** | If Pathao order creation fails, sale and invoice still exist, courier status = Failed, Retry button works without creating duplicate courier order. | `[MISSING]` | None | Phase 4 / Prompt 9 | Test simulated API failure; verify sale exists, error logged, retry succeeds. |
| **AC-5** | Sale deducts product stock; cancel/return restores it; `product_stock_movements` matches product `stock_qty`. | `[MISSING]` | None | Phase 3 / Prompts 7, 10 | Integrity query 1 returns 0 rows; cancel/return triggers compensating stock movements. |
| **AC-6** | Leather: purchases − consumption = current stock (sq ft); weighted average cost and stock value are accurate. Same for accessories. | `[MISSING]` | None | Phase 2 / Prompts 5, 6 | Query `v_leather_stock` and `v_accessory_stock`; compare with seeded calculations. |
| **AC-7** | Sales report by date range and by product returns correct quantities and totals versus raw records. | `[MISSING]` | None | Phase 6 / Prompt 14 | Run report query §8.1; verify against independent manual SQL aggregation. |
| **AC-8** | Salaried employee with irregular payments shows correct month balance, carry-forward, and over-payment flag. | `[MISSING]` | None | Phase 5 / Prompt 12 | Query §8.4 test scenario with advance payment; verify net balance formula. |
| **AC-9** | Wage worker with weekly/twice-weekly payments shows correct running balance; over-payment displays as negative/"owes Tasa" in violet. | `[MISSING]` | None | Phase 5 / Prompt 13 | Query §8.5 test scenario; verify running balance statement and negative balance UI badge. |
| **AC-10** | P&L for worked example (§11.3) produces Net Profit ৳6,000; every line drills down to records. | `[MISSING]` | None | Phase 6 / Prompt 15 | Seed exact test scenario data; execute query §8.2; verify net profit = 6000.00 exactly. |
| **AC-11** | Staff user cannot open Payroll or P&L by URL or API (403), and cost price/profit fields are absent from their responses. | `[MISSING]` | None | Phase 1 / Prompt 3 | Automated test as Staff; verify 403 on `/payroll/*`, `/reports/profit-loss`, and missing JSON keys on `/sales/*`. |
| **AC-12** | Role matrix changes take effect on user's next request without requiring re-login. | `[MISSING]` | None | Phase 1 / Prompt 3 | Test updating permission via admin; immediate subsequent request reflects updated gate. |
| **AC-13** | All screens usable at 375px, 768px, and 1280px widths without horizontal scroll or broken layouts. | `[MISSING]` | None | Phase 7 / Prompt 17 | Responsive testing across breakpoints with screenshot/DOM checks. |
| **AC-14** | Invoice prints correctly in A4/A5 and 80mm thermal receipt layout previews correctly. | `[MISSING]` | None | Phase 3 / Prompt 8 | Print stylesheet evaluation and receipt layout DOM validation. |

### §18 Assumptions & Open Questions (O-1 to O-7)
| ID | Item / Decision | PRD Default Chosen | Current Implementation Status |
|---|---|---|---|
| O-1 | Does product cost price include labour (wages)? | **No** — materials only; wages are a separate operating expense in P&L. Configurable via setting `payroll.cost_price_includes_labour`. | Setting specified in schema §7.3; to be seeded in Phase 1 / Prompt 2. |
| O-2 | Wage workers wage basis (per day, per piece, or fixed weekly)? | **Support all three**; selectable per worker via `workers.wage_basis`. | Schema has `wage_basis` enum column; to be implemented in Phase 0 / Prompt 2. |
| O-3 | Pathao delivery fee handling? | **Recorded as an operating expense**; delivery charge on invoice to customer is optional. | Courier success creates `expenses` row (`source = pathao_charge`). |
| O-4 | Can Staff cancel or return sales? | **No** (Owner only by default). Permission `sales.cancel` and `sales.return` omitted from Staff defaults. | Seeded permissions in Phase 1 / Prompt 2 will omit these from Staff. |
| O-5 | Invoice numbering sequence reset? | **Monthly reset** (`TASA-YYMM-0001`). `invoice_sequences` table keyed by `YYMM`. | Table defined in schema §4.2; atomic lock in Phase 3 / Prompt 7. |
| O-7 | Pathao store created? | Pull store list via package/API; allow store selection or creation in Settings. | Setting `pathao.store_id` provided. |

---

## 3. Database Architecture Traceability (`Tasa-POS-Database-Schema.md`)

### §1–§3 Database Conventions & Migration Order
| Section | Specification Detail | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| Schema §1 | InnoDB, `utf8mb4`, `utf8mb4_unicode_ci`, real foreign keys with `ON DELETE RESTRICT` default, `SET NULL` where specified. | `[VERIFIED]` | `config/database.php`, migrations `000001`–`000016` | Phase 0 / Prompt 2 | Foreign keys verified in MariaDB with RESTRICT on parents and SET NULL on audit columns. |
| Schema §1 | Money `DECIMAL(12,2)`, Leather qty `DECIMAL(12,3)`, Accessories `DECIMAL(12,3)`, Product qty `INT`. | `[VERIFIED]` | All migrations and model casts | Phase 0 / Prompt 2 | Exact decimal precision confirmed in `information_schema.columns` and automated test suite. |
| Schema §1 | Timezone `Asia/Dhaka` in `config/app.php` and business dates stored as `DATE` without UTC offset. | `[VERIFIED]` | `config/app.php`, Pest tests | Phase 0 / Prompt 1 | App timezone verified as `Asia/Dhaka`. Business dates stored as `DATE`. |
| Schema §1 | Soft deletes only on master data (`deleted_at`); NEVER on `sales`, payments, ledgers, or audit logs. | `[VERIFIED]` | Models and migrations | Phase 0 / Prompt 2 | Verified: `deleted_at` only on masters (`users`, `customers`, `products`, `leather_items`, `accessory_items`, `expenses`, `employees`, `workers`). Zero soft deletes on transactions. |
| Schema §1 | `created_by`, `updated_by` audit columns on transactional tables referencing `users.id` with `SET NULL`. | `[VERIFIED]` | Migrations `000005`, `000006`, `000007`, `000010`, `000012`, `000013` | Phase 0 / Prompt 2 | Audit columns configured with `nullOnDelete()`. |
| Schema §3 | Strict migration sequence: 1) Auth/Spatie, 2) System, 3) Customers, 4) Products, 5) Leather, 6) Accessories, 7) Adjustments, 8) Expense Categories, 9) Sales, 10) Courier, 11) Expenses, 12) Payroll, 13) Framework, 14) Pathao tokens, 15) Views. | `[VERIFIED]` | `database/migrations/2026_10_11_000001` to `000016` | Phase 0 / Prompt 2 | Migrations executed cleanly in order with rollback/re-migrate tested without foreign key failures. |

### §4 Table Definitions
| Table | Description & Constraints | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| `users` | Extended with `is_active` TINYINT(1) DEFAULT 1, `last_login_at` DATETIME NULL, `deleted_at`. | `[VERIFIED]` | `2026_10_11_000001_create_users_table.php`, `app/Models/User.php` | Phase 0 / Prompt 2 | User model soft deletes and single-role assignment verified in automated Pest tests. |
| `roles` & `permissions` | Spatie package tables + `roles.is_locked` TINYINT(1) DEFAULT 0, `roles.description` VARCHAR(255). | `[VERIFIED]` | `2026_10_11_000002_create_permission_tables.php`, `app/Models/Role.php` | Phase 0 / Prompt 2 | Owner `is_locked = 1` and Staff `is_locked = 0` verified in MariaDB and Pest tests. |
| `settings` | System settings table: `group`, `key`, `value` JSON/TEXT. Unique `(group, key)`. | `[VERIFIED]` | `2026_10_11_000003_create_system_tables.php`, `app/Models/Setting.php` | Phase 0 / Prompt 2 | Unique constraint verified; defaults seeded and accessed via `Setting::get()` / `Setting::set()`. |
| `invoice_sequences` | Race-free invoice sequence: `period` CHAR(4) PK (`YYMM`), `last_number` INT UNSIGNED DEFAULT 0. | `[VERIFIED]` | `2026_10_11_000003_create_system_tables.php`, `app/Models/InvoiceSequence.php` | Phase 0 / Prompt 2 | Atomic increment under `lockForUpdate()` producing `TASA-YYMM-0001` tested. |
| `audit_logs` | Append-only audit trail: `user_id`, `event`, `auditable_type`, `auditable_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`. No `updated_at`. | `[VERIFIED]` | `2026_10_11_000003_create_system_tables.php`, `app/Models/AuditLog.php` | Phase 0 / Prompt 2 | Append-only immutability verified; updating or deleting throws `RuntimeException`. |
| `import_logs` | Product Excel import log: `user_id`, `type`, `filename`, `total_rows`, `success_rows`, `failed_rows`, `errors` JSON. | `[VERIFIED]` | `2026_10_11_000003_create_system_tables.php`, `app/Models/ImportLog.php` | Phase 0 / Prompt 2 | Table created with JSON error payload support and user FK. |
| `customers` | Customers table: `name`, `phone` CHAR(11) UNIQUE, `alt_phone`, `address`, Pathao city/zone/area IDs & names, `deleted_at`. | `[VERIFIED]` | `2026_10_11_000004_create_customers_table.php`, `app/Models/Customer.php` | Phase 0 / Prompt 2 | Unique phone constraint and soft deletion verified in Pest tests. |
| `product_categories` | Finished goods categories: `name` UNIQUE. | `[VERIFIED]` | `2026_10_11_000005_create_product_tables.php`, `app/Models/ProductCategory.php` | Phase 0 / Prompt 2 | Category uniqueness and product relationship verified. |
| `products` | Finished products: `name`, `sku` UNIQUE, `cost_price`, `selling_price`, `stock_qty`, `min_stock`, `is_active`, `deleted_at`. CHECK `cost_price >= 0 AND selling_price >= 0`. | `[VERIFIED]` | `2026_10_11_000005_create_product_tables.php`, `app/Models/Product.php` | Phase 0 / Prompt 2 | SKU uniqueness, price check constraint, active/lowStock scopes verified. |
| `product_stock_movements` | Stock movement ledger: `product_id`, `type`, signed `qty`, `balance_after`, `reference_type`, `reference_id`, `movement_date`, `created_at`. CHECK `qty <> 0`. | `[VERIFIED]` | `2026_10_11_000005_create_product_tables.php`, `app/Models/ProductStockMovement.php` | Phase 0 / Prompt 2 | Ledger append-only immutability and non-zero check verified. |
| `leather_items` | Leather catalogue: `name`, `colour_grade`, `min_stock` DECIMAL(12,3), `deleted_at`. Unique `(name, colour_grade)`. | `[VERIFIED]` | `2026_10_11_000006_create_leather_tables.php`, `app/Models/LeatherItem.php` | Phase 0 / Prompt 2 | Unique composite constraint `(name, colour_grade)` and soft delete verified. |
| `leather_purchases` | Purchases: `leather_item_id`, `purchase_date`, `supplier`, `qty_sqft`, `rate_per_sqft`, `total_cost`, `paid_amount`, `payment_method`. CHECK `qty_sqft > 0`. | `[VERIFIED]` | `2026_10_11_000006_create_leather_tables.php`, `app/Models/LeatherPurchase.php` | Phase 0 / Prompt 2 | Decimal precision (12,3 qty, 12,2 rates) and check constraints verified. |
| `leather_consumptions` | Manual consumption: `leather_item_id`, `consumption_date`, `qty_sqft`, `note`. CHECK `qty_sqft > 0`. | `[VERIFIED]` | `2026_10_11_000006_create_leather_tables.php`, `app/Models/LeatherConsumption.php` | Phase 0 / Prompt 2 | Table created; non-expense tracking verified. |
| `accessory_items` | Accessory items: `name` UNIQUE, `unit` (pcs, set, meter, kg), `min_stock`, `deleted_at`. | `[VERIFIED]` | `2026_10_11_000007_create_accessory_tables.php`, `app/Models/AccessoryItem.php` | Phase 0 / Prompt 2 | Unique name and unit string verified. |
| `accessory_purchases` | Purchases: `accessory_item_id`, `purchase_date`, `supplier`, `qty`, `rate`, `total_cost`, `paid_amount`. CHECK `qty > 0`. | `[VERIFIED]` | `2026_10_11_000007_create_accessory_tables.php`, `app/Models/AccessoryPurchase.php` | Phase 0 / Prompt 2 | Purchases tracking and check constraints verified. |
| `accessory_consumptions` | Consumption: `accessory_item_id`, `consumption_date`, `qty`, `note`. CHECK `qty > 0`. | `[VERIFIED]` | `2026_10_11_000007_create_accessory_tables.php`, `app/Models/AccessoryConsumption.php` | Phase 0 / Prompt 2 | Consumption tracking verified. |
| `stock_adjustments` | Universal adjustments: `item_type` (product, leather, accessory), `item_id`, signed `qty`, `reason`, `note`, `adjusted_on`. | `[VERIFIED]` | `2026_10_11_000008_create_stock_adjustments_table.php`, `app/Models/StockAdjustment.php` | Phase 0 / Prompt 2 | Append-only adjustments model and indexes verified. |
| `expense_categories` | Categories: `name` UNIQUE, `pl_group` VARCHAR(30), `is_system` TINYINT(1) DEFAULT 0, `is_active`. | `[VERIFIED]` | `2026_10_11_000009_create_expense_category_tables.php`, `app/Models/ExpenseCategory.php` | Phase 0 / Prompt 2 | System categories seeded; P&L mapping groups verified. |
| `expenses` | Operating expenses: `category_id`, `expense_date`, `amount`, `method`, `vendor`, `source`, `courier_order_id`, `fee_deducted_from_cod`. Unique `(courier_order_id, source)`. | `[VERIFIED]` | `2026_10_11_000012_create_expenses_table.php`, `app/Models/Expense.php` | Phase 0 / Prompt 2 | Unique composite constraint on courier auto-charges verified. |
| `recurring_expenses` | Templates: `category_id`, `amount`, `vendor`, `day_of_month`, `last_confirmed_period` (`YYYY-MM`). | `[VERIFIED]` | `2026_10_11_000009_create_expense_category_tables.php`, `app/Models/RecurringExpense.php` | Phase 0 / Prompt 2 | Template model and category relationship verified. |
| `sales` | Sales & Invoices: `invoice_no` UNIQUE, `idempotency_token` UNIQUE, `customer_id`, customer snapshots, `sale_date`, `subtotal`, `discount_amount`, `delivery_charge`, `grand_total`, `paid_total`, `due_amount` (generated stored: `grand_total - paid_total`), `payment_status`, `status`, `courier_mode`. No `deleted_at`. | `[VERIFIED]` | `2026_10_11_000010_create_sales_tables.php`, `app/Models/Sale.php` | Phase 0 / Prompt 2 | Generated stored `due_amount` verified in Pest tests; zero soft delete confirmed. |
| `sale_items` | Sale line items: `sale_id`, `product_id` (nullable for custom), `is_custom`, `name`, `qty`, `unit_price`, `unit_cost` (snapshot), `line_total`, `returned_qty`. CHECK `qty > 0 AND returned_qty <= qty`. | `[VERIFIED]` | `2026_10_11_000010_create_sales_tables.php`, `app/Models/SaleItem.php` | Phase 0 / Prompt 2 | Line item cost snapshot and quantity check constraint verified. |
| `sale_payments` | Payments: `sale_id`, `paid_on`, `amount`, `method` (cash, cod, bkash, bank_transfer), `courier_order_id`. Append-only. | `[VERIFIED]` | `2026_10_11_000010_create_sales_tables.php`, `app/Models/SalePayment.php` | Phase 0 / Prompt 2 | Append-only payments model verified; immutable update protection active. |
| `sale_returns` & `sale_return_items` | Returns: `sale_id`, `returned_on`, `reason`, `return_value`, `refunded_amount`, `restock`. Line items reference `sale_items`. | `[VERIFIED]` | `2026_10_11_000010_create_sales_tables.php`, `app/Models/SaleReturn.php`, `SaleReturnItem.php` | Phase 0 / Prompt 2 | Return models and cascade items verified. |
| `courier_orders` | Pathao tracking: `sale_id` UNIQUE, `merchant_order_id`, `consignment_id` UNIQUE, `cod_amount`, `courier_charge`, `status`, `last_error`, `delivered_at`, `cod_settled_at`, `cod_settled_amount`. | `[VERIFIED]` | `2026_10_11_000011_create_courier_tables.php`, `app/Models/CourierOrder.php` | Phase 0 / Prompt 2 | Unique sale_id and consignment_id constraints and COD scopes verified. |
| `courier_status_logs` | Audit trail of courier status transitions: `courier_order_id`, `status`, `raw_status`, `payload` JSON, `logged_at`. | `[VERIFIED]` | `2026_10_11_000011_create_courier_tables.php`, `app/Models/CourierStatusLog.php` | Phase 0 / Prompt 2 | Status log model with JSON payload casting verified. |
| `employees` | Salaried staff: `name`, `phone`, `designation`, `monthly_salary`, `joined_at`, `is_active`, `deleted_at`. | `[VERIFIED]` | `2026_10_11_000013_create_payroll_tables.php`, `app/Models/Employee.php` | Phase 0 / Prompt 2 | Master data soft delete and active scopes verified. |
| `salary_months` | Monthly salary sheet: `employee_id`, `period` DATE (1st of month), `base_salary`, `bonus`, `deduction`, `payable` (generated stored: `base + bonus - deduction`). Unique `(employee_id, period)`. | `[VERIFIED]` | `2026_10_11_000013_create_payroll_tables.php`, `app/Models/SalaryMonth.php` | Phase 0 / Prompt 2 | Stored generated `payable` column and unique period constraint verified in Pest tests. |
| `salary_payments` | Salary payments: `employee_id`, `period`, `paid_on`, `amount`, `type` (salary, advance), `method`. | `[VERIFIED]` | `2026_10_11_000013_create_payroll_tables.php`, `app/Models/SalaryPayment.php` | Phase 0 / Prompt 2 | Salary payments model and positive amount check verified. |
| `workers` | Wage workers: `name`, `phone`, `role`, `wage_basis` (`per_day`, `per_piece`, `fixed_weekly`), `rate`, `is_active`, `deleted_at`. | `[VERIFIED]` | `2026_10_11_000013_create_payroll_tables.php`, `app/Models/Worker.php` | Phase 0 / Prompt 2 | Master worker model with wage_basis options verified. |
| `worker_earnings` | Earnings log: `worker_id`, `work_date`, `period_start`, `qty`, `rate`, `amount`. CHECK `qty > 0 AND amount >= 0`. | `[VERIFIED]` | `2026_10_11_000013_create_payroll_tables.php`, `app/Models/WorkerEarning.php` | Phase 0 / Prompt 2 | Earnings model and check constraints verified. |
| `worker_payments` | Wage payouts: `worker_id`, `paid_on`, `amount`, `method`. CHECK `amount > 0`. | `[VERIFIED]` | `2026_10_11_000013_create_payroll_tables.php`, `app/Models/WorkerPayment.php` | Phase 0 / Prompt 2 | Wage payouts model and check constraint verified. |
| `pathao_courier_tokens` | Token storage table for Pathao courier package. | `[VERIFIED]` | `2026_10_11_000015_create_pathao_courier_tokens_table.php` | Phase 0 / Prompt 2 | Token cache table created cleanly. |
| `v_leather_stock` & `v_accessory_stock` | Database views for real-time stock computation, weighted average cost, and stock value. | `[VERIFIED]` | `2026_10_11_000016_create_database_views.php` | Phase 0 / Prompt 2 | Views queried and verified in MariaDB and Pest tests. |

### §9 Verification Queries (Integrity Checks 1 to 10)
| Check | Invariant Description | Status | Target Phase / Prompt | Expected Result | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| Query 1 | Product cached stock equals sum of `product_stock_movements.qty`. | `[VERIFIED]` | Phase 0 / Prompt 2 | 0 rows returned | Verified in `tests/Feature/Database/SchemaVerificationQueriesTest.php` and raw MariaDB check: 0 violations. |
| Query 2 | Sale `paid_total` equals sum of `sale_payments.amount`. | `[VERIFIED]` | Phase 0 / Prompt 2 | 0 rows returned | Verified in `tests/Feature/Database/SchemaVerificationQueriesTest.php` and raw MariaDB check: 0 violations. |
| Query 3 | Sale `grand_total` equals `subtotal - discount_amount + delivery_charge`. | `[VERIFIED]` | Phase 0 / Prompt 2 | 0 rows returned | Verified in `tests/Feature/Database/SchemaVerificationQueriesTest.php` and raw MariaDB check: 0 violations. |
| Query 4 | Sale `payment_status` is consistent with `paid_total` and `due_amount`. | `[VERIFIED]` | Phase 0 / Prompt 2 | 0 rows returned | Verified in `tests/Feature/Database/SchemaVerificationQueriesTest.php` and raw MariaDB check: 0 violations. |
| Query 5 | Invoice numbers have no gaps and zero duplicates. | `[VERIFIED]` | Phase 0 / Prompt 2 | 0 rows returned | Verified in `tests/Feature/Database/SchemaVerificationQueriesTest.php` and raw MariaDB check: 0 violations. |
| Query 6 | Every non-custom sale line has a matching `sale` stock movement. | `[VERIFIED]` | Phase 0 / Prompt 2 | 0 rows returned | Verified in `tests/Feature/Database/SchemaVerificationQueriesTest.php` and raw MariaDB check: 0 violations. |
| Query 7 | Every active Pathao sale has exactly one `courier_orders` row. | `[VERIFIED]` | Phase 0 / Prompt 2 | 0 rows returned | Verified in `tests/Feature/Database/SchemaVerificationQueriesTest.php` and raw MariaDB check: 0 violations. |
| Query 8 | Zero duplicate Pathao courier charge expenses (`source=pathao_charge`). | `[VERIFIED]` | Phase 0 / Prompt 2 | 0 rows returned | Verified in `tests/Feature/Database/SchemaVerificationQueriesTest.php` and raw MariaDB check: 0 violations. |
| Query 9 | Zero leather or accessory items with negative stock (when negative stock disallowed). | `[VERIFIED]` | Phase 0 / Prompt 2 | 0 rows returned | Verified in `tests/Feature/Database/SchemaVerificationQueriesTest.php` and raw MariaDB check: 0 violations. |
| Query 10 | Returned quantity never exceeds sold quantity on any sale item. | `[VERIFIED]` | Phase 0 / Prompt 2 | 0 rows returned | Verified in `tests/Feature/Database/SchemaVerificationQueriesTest.php` and raw MariaDB check: 0 violations. |

---

## 4. Design & UX Guideline Traceability (`Tasa-POS-Design-UX-Guideline.md`)

### §1–§2 Design Vision & Hard Rules
| Section | Requirement | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| UX §2 | **Light Theme Only**: No dark mode, no `prefers-color-scheme: dark`, `<meta name="color-scheme" content="light">`, `color-scheme: light` on `:root`. | `[VERIFIED]` | `resources/views/app.blade.php`, `resources/css/app.css` | Phase 0 / Prompt 1 | Verified in browser subagent: zero dark mode toggles or styles present. |
| UX §2 | **White Base**: Page `#F4F6F6`, content surfaces `#FFFFFF`, sunken `#EBEFEE`. | `[VERIFIED]` | `resources/css/app.css`, `resources/js/components/tasa/TasaShell.vue` | Phase 0 / Prompt 1 | Verified: page `#F4F6F6`, card surfaces pure `#FFFFFF`, headers `#EBEFEE`. |
| UX §2 | **Single Accent**: Deep teal `#0B6E5E` as only brand colour; status colours used only for status. | `[VERIFIED]` | `resources/css/app.css`, `resources/js/components/tasa/AppButton.vue` | Phase 0 / Prompt 1 | Primary buttons and highlights strictly use `#0B6E5E`. |
| UX §2 | **Square Corners Everywhere**: `border-radius: 0` on buttons, inputs, cards, badges, modals, dropdowns, tabs, avatars, toasts, checkboxes. (Only radio buttons and spinners are round). | `[VERIFIED]` | `resources/css/app.css` (`--radius-*: initial; --radius: 0px;`) | Phase 0 / Prompt 1 | Verified in browser subagent: zero rounded corners on cards, inputs, dialogs, toasts. |
| UX §2 | **1px Borders**: All containers, inputs, dividers use 1px solid lines. Focus outline is 2px. Active tab underline is 2px. | `[VERIFIED]` | `resources/css/app.css` | Phase 0 / Prompt 1 | Verified: crisp 1px borders on inputs (`line-400`), cards (`line-200`), dividers. |
| UX §2 | **No Gradients & No Glass/Blur**: Solid fills only. Overlay scrim is flat `#0F1B1A` at 40% opacity. | `[VERIFIED]` | `resources/css/app.css`, `resources/js/components/tasa/ConfirmDialog.vue` | Phase 0 / Prompt 1 | Scrim tested: flat `#0F1B1A` at 40% opacity without backdrop blur. |
| UX §2 | **No Product Images & No Decorative Shadows**: Text-only product lists. Shadows allowed only on floating layers (dropdowns, modals, toasts). | `[VERIFIED]` | `resources/css/app.css`, `resources/js/components/tasa/DataTable.vue` | Phase 0 / Prompt 1 | Surface cards have 0 box shadows; flat 1px borders only. |

### §3 Visual Foundations & Design Tokens
| Section | Requirement | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| UX §3.1 | Palette: Teal scale (`teal-900 #074538`, `teal-800 #095A4C`, `teal-700 #0B6E5E`, `teal-200 #BFDDD6`, `teal-100 #E6F2EF`, `teal-50 #F0F7F5`), Ink scale (`ink-900 #0F1B1A`, `ink-700 #2B3A38`, `ink-600 #4A5957`, `ink-500 #5F6F6D`), Lines (`line-200 #D5DCDB`, `line-300 #B9C4C2`, `line-400 #7F8E8C`). | `[VERIFIED]` | `resources/css/app.css` (`@theme`) | Phase 0 / Prompt 1 | Exact tokens defined and compiled cleanly with Tailwind v4. |
| UX §3.2 | Status Colours: Success (`#1B7F3B` / text `#14602C` / bg `#E7F4EA`), Warning (`#B26A00` / text `#8A5A00` / bg `#FFF4DB`), Danger (`#B42318` / text `#8E1B12` / bg `#FDECEA`), Info (`#1D5FA8` / text `#174B85` / bg `#E8F0FA`), Advance/Over-paid (`#6B3FA0` / text `#55327F` / bg `#F1EAF8`). | `[VERIFIED]` | `resources/css/app.css`, `resources/js/components/tasa/StatusBadge.vue` | Phase 0 / Prompt 1 | Verified in browser subagent: all 5 tones rendered with distinct colors. |
| UX §3.3 | Contrast Matrix: WCAG 2.2 AA floor, AAA target for body text. Body text `ink-700` (11.9:1). Lowest text allowed `ink-500` (5.3:1). Input border `line-400` (3.4:1). | `[VERIFIED]` | `resources/css/app.css` | Phase 0 / Prompt 1 | Contrast ratios verified; body text `#2B3A38` meets AAA. |
| UX §3.6 | Typography: Hanken Grotesk self-hosted (weights 400, 500, 600, 700) with Noto Sans Bengali fallback. Tabular numerals (`font-variant-numeric: tabular-nums`) enabled globally on amounts and quantities. | `[VERIFIED]` | `public/fonts/fonts.css`, `public/fonts/*.woff2`, `resources/css/app.css` | Phase 0 / Prompt 1 | Verified in browser subagent: Bangla text and ৳ symbol render crisply. |
| UX §3.8 | Iconography: Lineicons outline style (1.5px stroke). Sizes: 16px, 20px (default), 24px, 40px (empty). Central mapping in `icons.ts`. Accessible `aria-label` on icon-only buttons. | `[VERIFIED]` | `resources/js/lib/icons.ts`, `resources/js/components/tasa/AppIcon.vue` | Phase 0 / Prompt 1 | Lineicons package installed, central mapping created, outline glyphs verified. |
| UX §3.9 | Motion: 120–160ms ease-out. Opacity, background-color, 4px translate only. Honor `prefers-reduced-motion`. | `[VERIFIED]` | `resources/css/app.css` | Phase 0 / Prompt 1 | Reduced-motion media query and fast transitions configured. |

### §4 App Shell & Navigation
| Section | Requirement | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| UX §4.1 | Desktop App Shell (≥1024px): 248px white sidebar, 56px top bar, global search, New Sale shortcut, user menu. Active item `teal-100` with 3px solid `teal-700` left bar. | `[VERIFIED]` | `resources/js/components/tasa/TasaShell.vue` | Phase 0 / Prompt 1 | Verified in browser subagent: 248px fixed sidebar, top bar, search, New Sale button. |
| UX §4.2 | Tablet App Shell (640–1023px): Collapsible 72px icon rail with overlay drawer on menu tap. | `[VERIFIED]` | `resources/js/components/tasa/TasaShell.vue` | Phase 0 / Prompt 1 | Verified in browser subagent at 768px: sidebar collapses to 72px icon rail. |
| UX §4.3 | Mobile App Shell (<640px): 56px top bar, 64px bottom nav with 5 slots (Sale, Invoices, Inventory, Expenses, More). iOS safe area padding. | `[VERIFIED]` | `resources/js/components/tasa/TasaShell.vue` | Phase 0 / Prompt 1 | Verified in browser subagent at 390px: 5-slot bottom navigation bar active. |
| UX §4.4 | Page Header: H1 title, optional subtitle, primary action on right (solid teal), optional outline secondary actions. | `[VERIFIED]` | `resources/js/components/tasa/PageHeader.vue` | Phase 0 / Prompt 1 | Reusable PageHeader component verified across all pages. |

### §5 Reusable Components
| Section | Component | Status | Concrete File / Route / Table | Target Phase / Prompt | Acceptance & Verification Evidence |
|---|---|---|---|---|---|
| UX §5.1 | Buttons: Primary (solid teal `#0B6E5E`), Secondary (white with `ink-900` border), Ghost, Danger solid, Danger outline. Focus ring 2px teal offset 2px. Heights 40px desktop / 48px touch. | `[VERIFIED]` | `resources/js/components/tasa/AppButton.vue` | Phase 0 / Prompt 1 | Verified in browser subagent: all 5 variants and loading state rendered. |
| UX §5.2 | Form Fields: Label above, `line-400` border, `teal-700` focus, inline validation error with icon, money field with ৳ prefix and right alignment, BD phone field with numeric keypad and format hint. Read-only fields in `sunken` fill. | `[VERIFIED]` | `resources/js/components/tasa/FormField.vue`, `MoneyInput.vue`, `PhoneInput.vue` | Phase 0 / Prompt 1 | Verified in browser subagent: inputs rendered with validation feedback. |
| UX §5.3 | Tables: Header row `sunken`, 1px `line-300` bottom border, sticky header. No zebra striping. Row hover `teal-50`. Selected `teal-100`. Numeric columns right-aligned. Totals row pinned at bottom in `sunken`. Pagination. | `[VERIFIED]` | `resources/js/components/tasa/DataTable.vue` | Phase 0 / Prompt 1 | Verified in browser subagent: table with sunken header and totals row. |
| UX §5.4 | Mobile Card List: Automatic responsive conversion of tables into stacked cards (<640px) showing 3–5 key fields and tap-to-expand. | `[VERIFIED]` | `resources/js/components/tasa/RecordCard.vue`, `DataTable.vue` | Phase 0 / Prompt 1 | Verified in browser subagent: mobile card stack rendered at 390px. |
| UX §5.5 | Badges & Status Chips: Square, 24px tall, icon (16px) + text (12px), tint bg + status text colour + 1px border. Never colour alone. | `[VERIFIED]` | `resources/js/components/tasa/StatusBadge.vue` | Phase 0 / Prompt 1 | Verified in browser subagent: 6 badge tones with icon and text. |
| UX §5.6 | Cards & Stat Widgets: White, 1px border, 20px padding, overline label, 32px bold number. Optional 3px teal accent bar on primary KPI. | `[VERIFIED]` | `resources/js/components/tasa/StatCard.vue` | Phase 0 / Prompt 1 | Verified in browser subagent: stat cards with 3px primary KPI accent bar. |
| UX §5.8 | Dialogs, Drawers, Toasts: Destructive confirm dialog (440px) with mandatory reason field; Right drawer (480px, full-screen on mobile); Toast (360px, bottom-right desktop, top mobile, 3px left status bar, errors persist). | `[VERIFIED]` | `resources/js/components/tasa/ConfirmDialog.vue`, `SideDrawer.vue`, `ToastContainer.vue` | Phase 0 / Prompt 1 | Verified interactively in browser subagent: modal, drawer, toasts tested. |
| UX §5.9 | States: Empty states with 40px outline icon & primary action; Skeletons (no full-page spinners); Actionable error cards; Friendly 403 page. | `[VERIFIED]` | `resources/js/components/tasa/EmptyState.vue`, `SkeletonLoader.vue`, `PageError.vue` | Phase 0 / Prompt 1 | Verified in browser subagent: skeleton pulse and empty state tested. |
| UX §5.10 | Date-Range Picker: Presets on left (Today, Yesterday, Last 7 days, This month, Last month, This year, Custom) + calendar. URL-persisted. | `[VERIFIED]` | `resources/js/components/tasa/DateRangePicker.vue` | Phase 0 / Prompt 1 | Verified: popover presets and calendar range inputs functional. |
| UX §5.11 | Search & Filter Bar: Debounced search (250ms), filter chips, active filter pills with "Clear all". | `[VERIFIED]` | `resources/js/components/tasa/SearchFilterBar.vue` | Phase 0 / Prompt 1 | Verified: search bar with filter chips and export button. |
| UX §8.1 | Formatting: South-Asian digit grouping (`Intl.NumberFormat('en-IN')`), e.g. `৳ 1,25,000.00`. Negatives in parentheses on statements, minus elsewhere. Phone displayed as `01712 345 678`. | `[VERIFIED]` | `resources/js/lib/formatters.ts` | Phase 0 / Prompt 1 | Verified: currency formatting, phone formatting, date formatting. |
| UX §10 | Data Visualisation: Square corners on bars, series palette (`#0B6E5E`, `#2B3A38`, `#B26A00`, `#6B3FA0`, `#1D5FA8`, `#7F8E8C`), "View as table" toggle on every chart. | `[MISSING]` | None | Phase 6 / Prompts 15, 16 | Chart components render accessible table alternative. |
| UX §14.1 | QA 1: No rounded corners, gradients, blur, or dark-mode anywhere in DOM. | `[MISSING]` | None | Phase 7 / Prompt 18 | Automated CSS/DOM scan verifies 0 offending classes. |
| UX §14.2 | QA 2: All text/background pairs meet WCAG AA. | `[MISSING]` | None | Phase 7 / Prompt 18 | Contrast audit report passes. |
| UX §14.3 | QA 3: Body text never lighter than `ink-600`; captions never lighter than `ink-500`. | `[MISSING]` | None | Phase 7 / Prompt 18 | Color audit report passes. |
| UX §14.4 | QA 4: ৳ and Bangla render correctly in tables, invoices, PDFs. | `[MISSING]` | None | Phase 7 / Prompt 18 | Visual inspection of Bangla and currency glyphs. |
| UX §14.5 | QA 5: All amounts right-aligned with tabular numerals; no truncated values. | `[MISSING]` | None | Phase 7 / Prompt 18 | Layout test at 360px and 1280px widths. |
| UX §14.6 | QA 6: Every status uses colour + icon + label. | `[MISSING]` | None | Phase 7 / Prompt 18 | Component audit confirms 0 color-only statuses. |
| UX §14.7 | QA 7: Exactly one primary (solid teal) button per screen. | `[MISSING]` | None | Phase 7 / Prompt 18 | Visual review of all module screens. |
| UX §14.8 | QA 8: POS sale completable on 360px phone in ≤ 3 steps with grand total sticky. | `[MISSING]` | None | Phase 7 / Prompt 18 | Mobile end-to-end sale flow verified in browser subagent. |
| UX §14.9 | QA 9: Keyboard-only navigation works for POS, purchase, expense, role edit. | `[MISSING]` | None | Phase 7 / Prompt 18 | Tab/Enter flow verified without mouse. |
| UX §14.10 | QA 10: Empty, loading, error, and 403 states exist for every list screen. | `[MISSING]` | None | Phase 7 / Prompt 18 | Verified on every module. |
| UX §14.11 | QA 11: Staff login displays zero payroll, P&L, cost or profit anywhere. | `[MISSING]` | None | Phase 7 / Prompt 18 | Comprehensive permission crawl as Staff user. |
| UX §14.12 | QA 12: Invoice prints correctly on A4, A5, 80mm thermal; readable in B&W. | `[MISSING]` | None | Phase 7 / Prompt 18 | Print preview evaluation. |
| UX §14.13 | QA 13: Tested at 360, 390, 768, 1024, 1280, 1440 px; no horizontal scroll. | `[MISSING]` | None | Phase 7 / Prompt 18 | Multi-viewport responsive tests pass. |
| UX §14.14 | QA 14: 200% browser zoom and reduced-motion do not break layouts. | `[MISSING]` | None | Phase 7 / Prompt 18 | Zoom test passes. |
| UX §14.15 | QA 15: Every save/delete confirmed against real database (DoD). | `[MISSING]` | None | Continuous | Database verification standard adhered to on every prompt. |

---

## 5. Verification Evidence Register

| Verification ID | Date Tested | Target Module | Test Command / Query | Observed Evidence | Outcome |
|---|---|---|---|---|---|
| `VERIF-001` | 11 Oct 2026 | Environment Audit | `php -v; node -v; npm -v; composer -V` | PHP 8.5.8, Node v26.2.0, npm 12.0.2, Composer 2.10.2 | `[VERIFIED]` Environment baseline confirmed |
| `VERIF-002` | 11 Oct 2026 | Git Repository Check | `git status` | `fatal: not a git repository` | `[BLOCKER]` Git repository not initialized |
| `VERIF-003` | 11 Oct 2026 | Database Engine Check | `mysql.exe -u root -e "SELECT VERSION();"` | `10.4.32-MariaDB` running via XAMPP | `[RISK]` MariaDB 10.4 vs MySQL 8.0.16+ requirement |
| `VERIF-004` | 11 Oct 2026 | PHP Extensions Check | `php -m` | `pdo_mysql` present; `pdo_sqlite` missing | `[RESOLVED]` Replaced with dedicated MySQL testing DB |
| `VERIF-005` | 11 Oct 2026 | Frontend Packages Check | `package.json` inspection | Tailwind 4.1.1, Vite 8.0.0; DaisyUI and Lineicons missing | `[RESOLVED]` DaisyUI 5.7.47 & Lineicons 1.3.2 installed |
| `VERIF-006` | 11 Oct 2026 | Git Repository Init | `git init` | Initialized Git repository | `[VERIFIED]` Version control initialized |
| `VERIF-007` | 11 Oct 2026 | Testing Database Setup | `phpunit.xml` configuration | Created `tasa_db_pos_test` MySQL DB; 34 Pest tests passed | `[VERIFIED]` Automated testing suite running |
| `VERIF-008` | 11 Oct 2026 | Self-Hosted Fonts | `public/fonts/download-fonts.js` | Hanken Grotesk (400-700) & Noto Sans Bengali (400,600,700) woff2 downloaded | `[VERIFIED]` Self-hosted fonts active |
| `VERIF-009` | 11 Oct 2026 | Design Tokens & CSS | `npm run build` | DaisyUI 5.7 + Tailwind v4 + zero-radius + 1px borders built in 2.6s | `[VERIFIED]` Clean Vite build |
| `VERIF-010` | 11 Oct 2026 | TypeScript Types Check | `npm run types:check` | `vue-tsc --noEmit` returned 0 errors | `[VERIFIED]` Clean type check |
| `VERIF-011` | 11 Oct 2026 | Foundation Feature Tests | `php artisan test tests/Feature/Foundation/DesignSystemAndShellTest.php` | 5 tests passed, 23 assertions | `[VERIFIED]` Timezone, fonts, shell routes pass |
| `VERIF-012` | 11 Oct 2026 | Browser Interactive Audit | Browser subagent on `/design` | 1280px desktop, 768px tablet, 390px mobile verified without overflow | `[VERIFIED]` Layout, tokens, Bangla, ৳ render crisply |
| `VERIF-013` | 11 Oct 2026 | Database Migrations Order | `php artisan migrate:fresh` | 16 migrations executed in exact documented order (§3): Auth, System, Customers, Products, Leather, Accessories, Adjustments, Expense Categories, Sales, Courier, Expenses, Payroll, Framework, Pathao tokens, Views | `[VERIFIED]` 48 tables and views created |
| `VERIF-014` | 11 Oct 2026 | Rollback & Re-migrate | `php artisan migrate:rollback --step=16; migrate` | All 16 migrations rolled back cleanly and re-migrated with 0 FK errors | `[VERIFIED]` Schema rollback & re-migration proven |
| `VERIF-015` | 11 Oct 2026 | Database Seeders | `php artisan db:seed` | Roles (Owner locked, Staff), documented permissions (§7.2), settings defaults (§7.3), system expense categories (§7.4), and initial Owner user seeded | `[VERIFIED]` Seed data active |
| `VERIF-016` | 11 Oct 2026 | Database Views | `SELECT * FROM v_leather_stock; v_accessory_stock;` | Both views return real-time calculated stock, weighted avg cost, and stock value | `[VERIFIED]` Views operational |
| `VERIF-017` | 11 Oct 2026 | Schema Consistency Tests | `php artisan test tests/Feature/Database/SchemaConsistencyAndIntegrityTest.php` | 8 tests passed, 28 assertions: FK restrict, unique phone/SKU/invoice, generated columns, append-only rules | `[VERIFIED]` Integrity invariants enforced |
| `VERIF-018` | 11 Oct 2026 | §9 Integrity Verification Queries | `php artisan test tests/Feature/Database/SchemaVerificationQueriesTest.php` | Invariant checks 1 through 10 executed against database: 0 violations across all checks | `[VERIFIED]` Zero invariant violations |
