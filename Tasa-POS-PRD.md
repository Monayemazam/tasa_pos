# Tasa POS — Product Requirements Document (PRD)

| | |
|---|---|
| **Product** | Tasa POS — simple sales, inventory, payroll and profit/loss system for a leather-goods maker and online seller |
| **Version** | 1.0 |
| **Date** | 11 October 2026 |
| **Prepared for** | Monayem (developer) / Tasa (client) |
| **Document position** | PRD — follows Requirements Analysis and Design Guideline; precedes Database Architecture and Implementation Prompts. Sections of earlier documents are referenced, not duplicated. |
| **Stack** | Laravel (latest, via official Starter Kit), Vue 3, Inertia.js, PHP, Tailwind CSS, DaisyUI, MySQL |

---

## 1. Product Overview

### 1.1 What Tasa does
Tasa **makes and sells leather products**. The shop is **online** (orders come through Facebook/social channels), and every order is **shipped by courier** (Pathao). Because every sale is shipped, every sale needs a customer name, phone number and address.

### 1.2 What this system is
A **simple, text-only POS web app** that lets Tasa:

1. Record a sale in a few clicks and **auto-generate an invoice**.
2. **Auto-create the Pathao courier order** from the same entry — no double typing.
3. Track **finished-product stock**, **raw leather stock** and **accessories stock** (purchases and consumption).
4. Record **all expenses** (bills, Facebook ads, packaging, etc.).
5. Track **salary** (monthly employees) and **wages** (workers paid weekly / twice weekly), including advances, over-payments and what is still owed.
6. See an accurate **Profit & Loss** statement built like a senior accountant would build it.
7. Control **who sees what** through simple role management.

### 1.3 Guiding principle
> **Keep it simple.** A shop assistant with no training should be able to make a sale in under a minute. Every screen has one clear job. No jargon, no cluttered screens.

### 1.4 Out of scope (explicitly not building)
- Product images in POS (text-only product list).
- Bill of Materials (BOM). Product cost and selling price are typed in manually. Material consumption is logged manually.
- Walk-in / anonymous customers (a sale cannot be saved without name + phone + address).
- Tax / VAT, wholesale price tiers, multi-branch, loyalty points, customer-facing storefront.
- Accounting features such as double-entry ledgers, bank reconciliation, balance sheet.

---

## 2. Users and Roles

Two built-in roles plus the ability to create more.

| Role | Who | Access |
|---|---|---|
| **Owner** | Business owner | Everything. Cannot be restricted or deleted. |
| **Staff** | Shop / order-handling person | POS (sales, invoices, customers), all Inventory, can add Expenses. **No** Salary/Wages, **no** Profit & Loss, **no** Role management, **no** Settings. |
| **Custom roles** | Optional | Owner picks exactly which sections each role can open (see §11). |

---

## 3. Core Business Rules (read this first)

These rules prevent the most common POS/accounting mistakes. Everything in later sections follows them.

| # | Rule |
|---|---|
| BR-1 | Currency is **BDT (৳)**. Timezone **Asia/Dhaka**. Dates shown as `DD MMM YYYY`. |
| BR-2 | **No sale without** customer name, phone, and delivery address. Phone validated as a Bangladeshi mobile number (`01XXXXXXXXX`, 11 digits). |
| BR-3 | A saved sale **always** produces an invoice with a unique sequential number. |
| BR-4 | Sales are **never hard-deleted**. They are **Cancelled** (with a reason) or **Returned**, so history and stock stay accurate. |
| BR-5 | **Raw leather and accessories purchases are inventory, not expenses.** They become a cost only through product cost price when the product is sold. This avoids counting the same money twice. |
| BR-6 | **Cost of Goods Sold (COGS) = quantity sold × the product's cost price at the time of sale.** The cost price is snapshotted on the invoice line so later edits to product cost do not change past profit. |
| BR-7 | Leather/accessory **consumption is stock tracking only**; it does not create a P&L expense by itself (see BR-5/6). |
| BR-8 | **Courier charge** per order is recorded as an expense automatically (editable). |
| BR-9 | Payroll is recognised in P&L when **earned/due for the period**, not when paid. Advances and over-payments are tracked as balances (see §9). |
| BR-10 | **Staff users never see P&L, salary/wages, or (by default) cost price**, and this is enforced on the **server**, not just hidden in the UI. |
| BR-11 | Money fields use `DECIMAL(12,2)`. Leather quantity uses `DECIMAL(12,3)` in **square feet (sq ft)**. |
| BR-12 | Every create/edit/cancel/delete is written to an **audit log** (who, what, when, old → new value). |

> **Decision needed — O-1 (default chosen):** Product *cost price* is assumed to be **materials only** (leather + accessories + packaging if you want). Worker wages are then a separate operating expense, so they are not counted twice. If Tasa instead types an *all-in* cost price (materials + labour), then worker wages must be excluded from P&L. The system will have a Settings toggle: **"Cost price includes labour: Yes/No"** (default **No**).

---

## 4. Module Map and Navigation

```
Dashboard
Sales
  ├─ New Sale (POS)
  ├─ Invoices / Sales List
  ├─ Customers
  └─ Courier Orders (Pathao status)
Inventory
  ├─ Products (finished goods)
  ├─ Leather  (Stock · Purchases · Consumption)
  ├─ Accessories (Stock · Purchases · Consumption)
  └─ Stock Adjustments
Expenses
Payroll
  ├─ Salaried Employees
  └─ Wage Workers
Reports
  ├─ Sales Report (date range, by product)
  └─ Profit & Loss
Settings
  ├─ Users & Roles
  ├─ Invoice Branding
  ├─ Pathao Courier
  └─ General (shop info, categories, units)
```

Staff sees only the sections allowed for them; hidden sections do not appear in the menu at all.

---

## 5. Dashboard

**Purpose:** one-glance health check. Different widgets for Owner and Staff.

### 5.1 Widgets for all users
- Today's sales (count and amount)
- This month's sales
- Pending courier orders / failed Pathao orders (needs attention)
- Low-stock alerts (products, leather, accessories below their minimum level)
- Latest 10 invoices
- Quick buttons: **New Sale**, **Add Expense**, **Add Leather Purchase**

### 5.2 Extra widgets for Owner only
- Net profit (this month) and gross margin %
- Total expenses (this month)
- **COD to receive** (courier-collected cash not yet marked as received)
- **Customer dues** (unpaid balances on invoices)
- **Payroll balances** (total owed to staff, total over-paid)
- Sales vs expenses trend chart (last 6 months)
- Facebook ad spend vs sales (simple ROAS = sales ÷ ad spend)

---

## 6. Sales Management

### 6.1 New Sale (POS screen)
Single-page, text-only, no product images. Layout: **left = products, right = customer + cart + payment** on desktop; stacked sections on tablet/mobile.

**Step flow**
1. **Customer** — type phone number first. If the customer exists, name and address auto-fill; otherwise enter new details. Fields: Name*, Phone*, Address*, and Pathao **City / Zone / Area*** (cascading dropdowns, see §7).
2. **Add products** — search by name or SKU; click a row to add. Cart lines show: name, qty, unit price, line total. Unit price is prefilled with the product's selling price; editing the price requires the permission *"Edit price at sale"* (Owner yes, Staff default yes — configurable).
3. **Totals** — Subtotal, Discount (flat ৳ or %), Delivery charge (charged to customer, optional), **Grand total**.
4. **Payment** — Paid amount (supports **partial payment**), payment method: **Cash · Cash on Delivery · bKash · Bank Transfer**. Balance due is calculated automatically. For *Cash on Delivery* the "Amount to collect" sent to Pathao equals the balance due.
5. **Delivery** — choose **Send via Pathao** (default) or **No courier (hand-delivered)**. If Pathao: weight (kg), delivery type (Normal / On-demand), special instruction.
6. **Save** → invoice is generated → Pathao order is created automatically → success screen with **Print Invoice**, **Download PDF**, **New Sale**.

**Rules**
- Cannot save with empty cart, missing customer details, or (for Pathao) missing city/zone/area/weight.
- Stock is deducted on save. If requested qty > available stock, show a clear warning; saving anyway is controlled by a setting *"Allow selling below zero stock"* (default **No**).
- **Custom-made products:** a cart line can be added as **"Custom item"** (free-text name, price, optional manual cost) for made-to-order pieces that are not in the product list. It does not deduct product stock.
- **Duplicate protection:** double-clicking Save must not create two sales (button disables + server-side idempotency token).
- Barcode scanning is **not** required now, but the product search box must work with a keyboard-style scanner later (focus + Enter), so no redesign is needed when hardware is bought.

### 6.2 Invoices / Sales List
- Table: Invoice no., date, customer, phone, total, paid, due, payment status (Paid / Partial / Unpaid), courier status, actions.
- **Filters:** date range (presets: Today, Yesterday, Last 7 days, This month, Last month, Custom), payment status, courier status, payment method, search by invoice no./name/phone.
- **Actions:** View, Print, Download PDF, **Add payment** (for dues), **Cancel sale** (reason required), **Mark returned** (full or partial lines), Retry Pathao.
- **Cancel / Return effects:** stock is restored, revenue is reduced in reports, an audit entry is written. A return after courier dispatch also lets the user record the **return courier cost** as an expense.

### 6.3 Invoice
- Auto-number format: **`TASA-YYMM-0001`** (resets sequence each month; configurable prefix). Numbers are never reused, even if a sale is cancelled.
- Contents: shop logo/name/address/phone, invoice no., date, customer name/phone/address, item table (name, qty, unit price, total), subtotal, discount, delivery charge, grand total, paid, due, payment method(s), courier consignment ID (if any), footer note/terms.
- **Formats:** A4 and A5 (PDF/print), plus an **80 mm thermal-width** layout ready for a future receipt printer.
- **Invoice Branding settings (Owner only):** logo, shop details, footer text, terms, which fields to show, paper size, and an **optional custom template editor** where the Owner can edit the invoice HTML/CSS using safe placeholders such as `{{invoice_no}}`, `{{customer_name}}`, `{{items_table}}`.

> **Security note — custom code:** raw **PHP** will **not** be executable from the Settings screen. Letting anyone run PHP from a form is a remote-code-execution risk. The custom template supports **HTML + CSS + placeholders** only (sanitised). If deeper customisation is needed later, it should be done by the developer in the codebase.

### 6.4 Customers
- Auto-created from sales (phone is the unique key).
- Profile: name, phone, addresses, total orders, total spent, outstanding due, order history.
- **Optional courier-risk hint:** on the sale screen, show the customer's **Pathao delivery success rate** (from the package's success-rate lookup) so staff can spot risky COD orders. Can be turned off in settings.

### 6.5 Sales Report (date range and product-wise)
Located under **Reports → Sales Report**.

- **Date range** picker with presets; default = this month.
- **Summary cards:** invoices, gross sales, discounts, returns, **net sales**, amount received, amount due.
- **By product table:** product, **quantity sold**, revenue, COGS, gross profit (profit/cost columns visible only to users with *View cost & profit* permission).
- Additional views: by day (trend), by payment method, by courier status, by customer (top buyers).
- **Export:** CSV / Excel / PDF.

---

## 7. Pathao Courier Integration

### 7.1 Package
Use **`enuenan/pathao-courier`** (Composer package name: `enan/pathao-courier`, MIT licence). Verified capabilities from its README:

| Capability | Package method |
|---|---|
| Token expiry check | `GET_ACCESS_TOKEN_EXPIRY_DAYS_LEFT()` |
| Cities / Zones / Areas | `GET_CITIES()`, `GET_ZONES($city_id)`, `GET_AREAS($zone_id)` |
| Stores | `GET_STORES()`, `CREATE_STORE($request)` |
| **Create order** | `CREATE_ORDER($request)` |
| Price calculation | `GET_PRICE_CALCULATION($request)` |
| Order details/status | `VIEW_ORDER($consignment_id)` |
| Customer success rate | `GET_USER_SUCCESS_RATE($request)` |

**Package limitations to design around:**
- **No cancel-order method** → cancelling a dispatched parcel must be done in the Pathao merchant panel; the POS shows a clear warning and a link.
- **No webhook support** → delivery status is updated by a **scheduled job that polls `VIEW_ORDER`** (e.g., every 30 minutes for non-final orders).
- **Laravel 13 compatibility must be verified** before install (`composer require` and a smoke test). If incompatible, fall back to a thin internal `PathaoService` class calling the same Pathao Merchant API directly, keeping the same interface so the rest of the app does not change.

### 7.2 Setup (Settings → Pathao Courier, Owner only)
1. `composer require enan/pathao-courier`, publish config, run migration.
2. `.env` keys: `PATHAO_CLIENT_ID`, `PATHAO_CLIENT_SECRET`, `PATHAO_SECRET_TOKEN` (token from the package's one-time `php artisan set:pathao-courier`).
3. Settings page shows: connection status, **token days left** (warn when < 7 days), default **Pathao Store**, default sender name/phone, default delivery type, default item type (Parcel), default weight.
4. Credentials live in `.env` only — never shown or stored in plain text in the UI.

### 7.3 Address capture
Pathao requires numeric **city, zone and area IDs**, so the sale form uses three cascading dropdowns (City → Zone → Area) loaded from the package and **cached** (e.g., 24 h) to keep the POS fast. The free-text address must be **at least 10 characters** (Pathao validation). Frequent customers' last used city/zone/area are prefilled.

### 7.4 Auto-create flow
1. User saves the sale (with *Send via Pathao* on).
2. Sale + invoice are saved first, **always** — a courier problem must never lose a sale.
3. A **queued job** calls `CREATE_ORDER` with:
   - `store_id` (default store), `merchant_order_id` = **invoice number** (also used to prevent duplicates)
   - sender name/phone (from settings), recipient name/phone/address
   - `recipient_city / zone / area`
   - `delivery_type` (48 = Normal, 12 = On-demand), `item_type` (2 = Parcel)
   - `item_quantity`, `item_weight`, `item_description` (short product list), `special_instruction`
   - `amount_to_collect` = balance due if Cash on Delivery, otherwise **0**
4. On success: store **consignment ID**, courier status, and the delivery charge → auto-create the **Courier charge expense** (BR-8).
5. On failure: status = **Failed**, error message saved, a red badge appears on the dashboard and the sale, and a **Retry** button is available. The job retries automatically with back-off (e.g., 3 attempts).
6. **Status polling:** scheduled job updates statuses (Pending → Picked up → In transit → Delivered / Returned). Delivered COD orders appear under **COD to receive** until the Owner marks them **Settled** (cash received from Pathao).

### 7.5 Courier Orders screen
Table of all Pathao orders: invoice, customer, consignment ID, status, COD amount, courier charge, settlement status, last updated. Filters by status/date. Actions: Retry, Refresh status, Open in Pathao, Mark COD settled.

### 7.6 Edge cases
- Editing a sale **after** the Pathao order exists: customer/address/amount edits are **blocked** with a message ("Update this in Pathao panel, then adjust here"), or allowed only for non-courier fields.
- Pathao API down / token expired: sale still saves; dashboard shows "Courier connection problem".
- Partial payment + COD: `amount_to_collect` = remaining balance only.

---

## 8. Inventory Management

Three separate sections, all simple tables with search and a clear "current stock" column.

### 8.1 Products (finished goods — what Tasa sells)
**Fields:** Name*, SKU/code (auto if empty), Category, **Cost price*** (manual), **Selling price*** (manual), Current stock, Minimum stock (alert level), Active/Inactive.
- No images. No BOM. No variants required (a colour/size can simply be part of the product name, e.g., "Bifold Wallet – Tan").
- **Stock increases** via **"Add stock / production entry"** (date, qty, note) when new finished pieces are made; **decreases** automatically on sale; **adjusted** via Stock Adjustments (damage, correction) with a mandatory reason.
- **Excel import:** downloadable template (Name, SKU, Category, Cost price, Selling price, Opening stock, Min stock); validation preview before import; errors listed per row. Also useful for loading the existing product-name list.
- Stock movement history per product (sale, production, return, adjustment).

### 8.2 Leather (raw material)
Unit: **square feet (sq ft)**.

**Leather items catalogue:** name (e.g., "Cow Nappa"), colour/grade (optional), minimum stock.

**Purchase entry:** date, supplier (optional text), leather item, quantity (sq ft), **rate per sq ft**, total cost (auto), amount paid, note.
**Consumption entry:** date, leather item, quantity (sq ft), note (e.g., "Wallet batch — 30 pcs"). Entered **manually**; blocked if it would make stock negative (unless allowed by setting).

**Screen:** tabs — **Stock** | **Purchases** | **Consumption**.
**Stock tab columns:** item, total purchased, total consumed, **current stock**, average cost per sq ft, stock value, low-stock flag.

> **Formulas**
> `Current stock = Σ purchased − Σ consumed ± adjustments`
> `Average cost = Σ purchase cost ÷ Σ purchased quantity` (weighted average)
> `Stock value = current stock × average cost`

Filter by date range to see "how much leather was bought and used this month".

### 8.3 Accessories (raw material)
Same structure as Leather but with a **unit per item** (pcs, set, meter, kg…): zippers, buckles, snaps, thread, lining, packaging, etc.
Tabs: **Stock | Purchases | Consumption**. Identical formulas and low-stock alerts.

### 8.4 Stock Adjustments
Used for corrections on any of the three inventory types: item, +/− quantity, reason (damaged, lost, count correction), date, who. Always audited.

### 8.5 Inventory permissions
Staff can view/add/edit all inventory. **Cost price and purchase rates** are hidden from Staff unless the permission *"View cost & purchase prices"* is on (default **off** for Staff). Delete is Owner-only by default.

---

## 9. Expenses

**Purpose:** record every business cost so profit is real.

**Entry fields:** date, **category***, amount*, payment method, paid to / vendor (optional), note, attachment (bill photo/PDF, optional).

**Default categories (editable by Owner):**
Facebook Ads · Other Marketing · Packaging · Courier Charges *(auto from Pathao)* · Rent · Electricity · Internet · Gas/Water · Transport · Repair & Maintenance · Tools & Equipment · Miscellaneous.

> Salary and wage payments are **not** entered here — they are recorded under **Payroll** and flow into P&L automatically (prevents duplicates).
> Leather and accessories purchases are **not** expenses (BR-5).

**Features**
- List with date-range filter, category filter, search; totals by category (chart).
- **Facebook Ads** is a first-class category so ad spend is visible on the dashboard and P&L (marketing section).
- **Recurring expenses:** mark an expense as monthly (e.g., rent, internet) → the system prompts "Add this month's rent?" on the 1st — a one-click confirm, not silent auto-posting.
- Staff can **add and view** expenses; edit/delete is Owner-only by default (configurable).

---

## 10. Payroll — Salary and Wages

Two separate ledgers, because the two groups are paid differently.

### 10.1 Salaried Employees (monthly)
**Employee profile:** name, phone, designation, joining date, **monthly salary**, status.

**Monthly salary sheet (per employee per month):**

| Line | Meaning |
|---|---|
| Base salary | Monthly salary (editable per month if it changes) |
| + Bonus / Overtime | Optional |
| − Deductions | Optional (e.g., absence) |
| **= Payable for month** | Base + bonus − deductions |
| − Paid in month | Sum of all payments recorded |
| **= Month balance** | Payable − Paid |
| **Carry-forward** | Previous unpaid or over-paid amount |
| **Net balance** | Carry-forward + month balance |

- **Payments are flexible:** record any number of payments on any date (start of month, mid-month, end of month, advances). Each payment: date, amount, method, note, type (*Salary* / *Advance*).
- **Net balance reading:** positive = **Tasa still owes the employee**; negative = **employee has been over-paid (employee owes Tasa / advance to be adjusted)**.
- Status badges: *Fully paid · Partially paid · Over-paid*.

### 10.2 Wage Workers (weekly / twice-weekly)
**Worker profile:** name, phone, role, **wage basis**, rate, status.
Wage basis options (pick per worker): **Per day**, **Per piece**, or **Fixed per week**.

**Earnings entry:** date (or period), days worked or pieces made, rate (defaults from profile, editable), **amount earned** (auto), note.
**Payment entry:** date, amount, method, note — recorded as often as needed (once or twice a week).

**Worker ledger (running statement):**

| Date | Earned | Paid | Running balance |
|---|---|---|---|

> `Balance = Total earned − Total paid`
> Positive → **Tasa owes the worker**. Negative → **worker was over-paid and owes Tasa** (shown in a distinct colour).

- Summary at top of each worker: earned this week / this month, paid, **current balance**.
- A **"Weekly payout"** helper screen lists all workers with their current balance so the Owner can pay and record in one go.
- Date-range filter on every ledger; printable statement per worker/employee.

### 10.3 Payroll in Profit & Loss
- **Salary expense** = Σ *payable for month* of all salaried employees (accrual).
- **Wage expense** = Σ *earned* by workers in the period.
- Over-paid amounts (advances) are **not** extra expense; they sit as a balance and reduce future payables. The P&L page shows a memo line: *"Payroll payable / (advance) as of period end."*
- Visible **only** to users with the *Payroll* and *Profit & Loss* permissions (Owner by default).

---

## 11. Profit & Loss (senior-accountant view)

**Location:** Reports → Profit & Loss. **Access:** Owner only by default.

**Controls:** date range (This month default; presets for Last month, This quarter, This year, Custom), **compare with previous period** toggle, Export PDF/Excel.

### 11.1 Statement layout

```
REVENUE
  Gross sales (invoice subtotals)                        A
  − Discounts                                            B
  − Sales returns / cancellations                        C
  = NET SALES                                  D = A − B − C
  + Delivery charges collected from customers            E
  = TOTAL REVENUE                              F = D + E

COST OF GOODS SOLD
  Cost of items sold (qty × cost price)                  G
  = GROSS PROFIT                               H = F − G
  Gross margin %                               H ÷ F

OPERATING EXPENSES
  Marketing
     Facebook Ads                                        I1
     Other marketing                                     I2
  Courier charges (Pathao + return costs)                J
  Salaries (earned for period)                           K
  Wages (earned for period)                              L
  Packaging                                              M
  Rent & utilities (rent, electricity, internet, gas)    N
  Other expenses                                         O
  = TOTAL OPERATING EXPENSES      P = I1+I2+J+K+L+M+N+O

NET PROFIT / (LOSS)                            Q = H − P
Net margin %                                   Q ÷ F
```

> If *"Cost price includes labour"* = Yes (see O-1), line **L (Wages)** is excluded from P to avoid double counting.

### 11.2 Memo section ("for information, not in the profit calculation")
- Leather purchased in period (৳, sq ft) and consumed (sq ft, value at average cost)
- Accessories purchased and consumed
- Closing stock value: finished products, leather, accessories
- **COD receivable from Pathao** (delivered, not yet settled)
- **Customer dues** (unpaid invoice balances)
- **Payroll payable / (advances)**
- **Cash summary:** cash received (customer payments + COD settlements) vs cash paid out (expenses + payroll payments + material purchases) → net cash movement. This answers "where did my money go even though P&L shows profit?"

### 11.3 Worked example (to be used as a test case)
| Item | ৳ |
|---|---|
| Gross sales | 200,000 |
| Discounts | 5,000 |
| Returns | 10,000 |
| **Net sales** | **185,000** |
| Delivery charges collected | 8,000 |
| **Total revenue** | **193,000** |
| COGS | 80,000 |
| **Gross profit** | **113,000** (58.5%) |
| Facebook Ads | 25,000 |
| Courier charges | 14,000 |
| Salaries | 30,000 |
| Wages | 20,000 |
| Rent & utilities | 12,000 |
| Packaging + other | 6,000 |
| **Total operating expenses** | **107,000** |
| **Net profit** | **6,000** (3.1%) |

### 11.4 Visuals
- Revenue vs expenses vs profit bar chart by month
- Expense breakdown (donut or ranked bars)
- Top 5 profitable products
- KPI strip: Net profit, Gross margin %, Ad spend as % of sales, ROAS

### 11.5 Accuracy rules
- Only **non-cancelled** sales count; returns reduce revenue in the **return date's** period.
- COGS uses the **snapshot cost** on each invoice line.
- Every number on the page is **clickable** → opens the underlying records (drill-down) so the Owner can verify.
- Reports recompute from source records (no stored totals that can drift).

---

## 12. Role Management (Settings → Users & Roles)

### 12.1 Users
Create user (name, email/phone, password), assign **one role**, activate/deactivate, force password reset. Owner cannot be deactivated by others. Deactivated users keep their history.

### 12.2 Permission matrix
Owner chooses, per role, which sections are available and what actions are allowed. Presented as a **simple checkbox grid** (rows = sections, columns = View / Add / Edit / Delete).

| Section | Owner | Staff (default) |
|---|---|---|
| Dashboard (basic widgets) | ✔ | ✔ |
| Dashboard (profit/payroll widgets) | ✔ | ✘ |
| POS / New Sale | ✔ | ✔ |
| Invoices & Customers | ✔ | ✔ (cancel: Owner only by default) |
| Courier Orders | ✔ | ✔ |
| Inventory — Products | ✔ | ✔ |
| Inventory — Leather | ✔ | ✔ |
| Inventory — Accessories | ✔ | ✔ |
| View cost & purchase prices | ✔ | ✘ |
| Expenses | ✔ | Add + View |
| **Payroll (Salary & Wages)** | ✔ | ✘ |
| **Profit & Loss** | ✔ | ✘ |
| Sales Report (quantities) | ✔ | ✔ |
| Sales Report (cost/profit columns) | ✔ | ✘ |
| Settings, Users & Roles | ✔ | ✘ |

### 12.3 Enforcement
- Permissions are checked **on the server** (policies/gates + route middleware) and in API responses — hidden fields are not sent to unauthorised users.
- The Owner role is locked to full access.
- Recommended implementation: **spatie/laravel-permission** with seeded permissions per module/action.
- Login security: hashed passwords, login rate-limiting, optional 2FA for Owner (from the Starter Kit), session timeout.

---

## 13. UI / UX Requirements

### 13.1 Design direction (carried over from the approved mockup — see Design Guideline)
- **Light theme only**, **white base**; no dark-mode leakage anywhere.
- Single solid accent colour: **deep teal `#0B6E5E`**; no gradients, no glass/blur effects.
- **Square corners** everywhere (no rounded buttons, cards or inputs); **1 px borders**.
- Font: **Hanken Grotesk**. Icons: **Lineicons (outline)**.
- Built with Tailwind + DaisyUI themed to the above.

### 13.2 Simplicity rules
- Plain labels ("Paid amount", "Balance due"), no accounting jargon on staff screens.
- One primary button per screen; destructive actions need a confirm dialog.
- Defaults everywhere (today's date, default payment method, default Pathao store).
- Clear empty states ("No leather purchases yet — Add the first purchase").

### 13.3 Responsiveness
| Device | Behaviour |
|---|---|
| **Mobile (< 640 px)** | Bottom or collapsible navigation; POS as stacked steps (Customer → Products → Payment); tables become **cards**; large touch targets (≥ 44 px) |
| **Tablet (640–1024 px)** | Collapsible sidebar; two-column POS where space allows |
| **Desktop (> 1024 px)** | Fixed sidebar; two-pane POS; full data tables |

Every module — including P&L and ledgers — must be usable on a phone.

---

## 14. Data Model (high level — full detail goes in the Database Architecture document)

| Table | Key columns |
|---|---|
| `users` | name, email/phone, password, role, is_active |
| `roles`, `permissions`, pivot tables | (spatie/laravel-permission) |
| `customers` | name, phone (unique), default address, city/zone/area ids |
| `products` | name, sku, category_id, cost_price, selling_price, stock_qty, min_stock, is_active |
| `product_stock_movements` | product_id, type (sale/production/return/adjustment), qty, ref, note, user |
| `sales` | invoice_no, customer_id, date, subtotal, discount, delivery_charge, grand_total, paid_total, due, status (active/cancelled/returned), courier_mode, notes, created_by |
| `sale_items` | sale_id, product_id (nullable for custom), name, qty, unit_price, **unit_cost (snapshot)**, line_total |
| `sale_payments` | sale_id, date, amount, method, note |
| `sale_returns` (+ items) | sale_id, date, reason, amount, return_courier_cost |
| `courier_orders` | sale_id, consignment_id, status, cod_amount, courier_charge, error, last_synced_at, cod_settled_at |
| `leather_items` | name, colour/grade, min_stock |
| `leather_purchases` | date, item_id, supplier, qty_sqft, rate, total, paid, note |
| `leather_consumptions` | date, item_id, qty_sqft, note |
| `accessory_items` | name, unit, min_stock |
| `accessory_purchases` / `accessory_consumptions` | same shape as leather |
| `stock_adjustments` | type (product/leather/accessory), item_id, qty (±), reason, date, user |
| `expense_categories` | name, is_system |
| `expenses` | date, category_id, amount, method, vendor, note, attachment, source (manual/courier/…), recurring flag |
| `employees` | name, phone, designation, monthly_salary, joined_at, status |
| `salary_months` | employee_id, month, base, bonus, deduction, payable |
| `salary_payments` | employee_id, month, date, amount, type (salary/advance), method, note |
| `workers` | name, phone, wage_basis, rate, status |
| `worker_earnings` | worker_id, date, qty (days/pieces), rate, amount, note |
| `worker_payments` | worker_id, date, amount, method, note |
| `settings` | key/value (shop info, invoice template, pathao defaults, toggles) |
| `audit_logs` | user_id, action, entity, entity_id, old/new json, timestamp |

Conventions: soft deletes on masters; no hard delete on sales; indexes on `sales.date`, `sales.invoice_no`, `customers.phone`, foreign keys; all money `DECIMAL(12,2)`.

---

## 15. Non-Functional Requirements

| Area | Requirement |
|---|---|
| Performance | POS screen loads < 2 s; product search results < 300 ms; reports for 1 year of data < 3 s |
| Security | CSRF, XSS-safe output, server-side authorisation, encrypted secrets in `.env`, HTTPS only, rate-limited login |
| Reliability | Pathao calls run in **queues** with retries; sale save never depends on courier response |
| Data integrity | DB transactions around sale + stock + payment writes; idempotent sale submission |
| Backup | Automated daily DB backup, 14-day retention, tested restore |
| Auditability | Audit log for all writes; read-only for everyone including Owner |
| Browser support | Latest Chrome, Edge, Safari, Firefox; mobile Chrome/Safari |
| Localisation | English UI; Unicode (Bangla) accepted in names/addresses; ৳ currency formatting |
| Deployment | Standard Laravel hosting (PHP 8.3+, MySQL 8, Node build step); `.env`-based config; queue worker + scheduler (cron) configured |

---

## 16. Delivery Plan (suggested phases)

| Phase | Scope | Outcome |
|---|---|---|
| 0 | Project setup from Laravel Starter Kit (Vue + Inertia), Tailwind/DaisyUI theme, layout, seeders | Skeleton with login and navigation |
| 1 | Users, roles & permission matrix, settings, audit log | Access control working |
| 2 | Inventory: products, Excel import, leather, accessories, adjustments | Stock tracking live |
| 3 | Customers, POS sale screen, payments, invoices (print/PDF), returns/cancel | End-to-end sale |
| 4 | Pathao integration: setup, address dropdowns, queued create-order, status polling, courier orders screen | Auto courier orders |
| 5 | Expenses + Payroll (salary and wages ledgers) | All costs captured |
| 6 | Sales report, Profit & Loss, dashboard widgets, exports | Decision-ready numbers |
| 7 | Responsive polish, UAT with Tasa data, performance, backups, go-live | Production release |

---

## 17. Acceptance Criteria and Definition of Done

**Standard for every feature:** every create / update / delete / cancel must be **verified against the actual database** (query or hard reload) — a success toast or agent self-report is **not** proof. Each implementation prompt embeds this rule.

**Key acceptance tests**

| ID | Test |
|---|---|
| AC-1 | A sale cannot be saved without name, valid BD phone and address; no "walk-in" option exists anywhere. |
| AC-2 | Saving a sale creates exactly one invoice with the next sequential number; double-click does not duplicate. |
| AC-3 | Saving a sale with *Send via Pathao* creates a Pathao order; `consignment_id` is stored; `amount_to_collect` equals balance due for COD and 0 otherwise. |
| AC-4 | If Pathao fails, the sale and invoice still exist, status = Failed, Retry works, and no duplicate order is created on retry. |
| AC-5 | Sale deducts product stock; cancel/return restores it; stock movement history matches. |
| AC-6 | Leather: purchases − consumption = current stock (sq ft); average cost and stock value are correct. Same for accessories. |
| AC-7 | Sales report by date range and by product returns correct quantities and totals versus raw records. |
| AC-8 | Salaried employee with irregular payments shows correct month balance, carry-forward and over-payment flag. |
| AC-9 | Wage worker with weekly/twice-weekly payments shows a correct running balance; over-payment is displayed as negative/"owes Tasa". |
| AC-10 | P&L for the worked example in §11.3 produces net profit ৳6,000; every line drills down to records. |
| AC-11 | A Staff user cannot open Payroll or P&L by URL or API (403), and cost price/profit fields are absent from their responses. |
| AC-12 | Role matrix changes take effect on the user's next request. |
| AC-13 | All screens are usable at 375 px, 768 px and 1280 px widths. |
| AC-14 | Invoice prints correctly in A4/A5 and the thermal layout previews correctly. |

---

## 18. Assumptions and Open Questions

**Assumptions (change if wrong)**
1. Single shop / single location.
2. Payment methods: Cash, Cash on Delivery, bKash, Bank Transfer. No tax/VAT. No wholesale pricing.
3. Leather measured in **sq ft**; accessories in their own units.
4. Staff may set the unit price in POS (configurable).
5. Product list currently exists only as names in Excel → import template provided.
6. No printer or barcode scanner yet; layout and input handling are future-ready.

**Open questions**
| ID | Question | Default if unanswered |
|---|---|---|
| O-1 | Does product cost price include labour (wages)? | **No** — materials only; wages are a separate expense |
| O-2 | Wage workers: paid **per day**, **per piece**, or **fixed weekly**? | All three supported; chosen per worker |
| O-3 | Does Pathao's delivery fee get **charged to the customer**, **absorbed by Tasa**, or **deducted from COD**? | Recorded as an expense; "Delivery charge" line on the invoice is optional |
| O-4 | Should Staff be allowed to **cancel/return** sales? | **No** (Owner only) |
| O-5 | Should invoice number reset **monthly** or run continuously? | Monthly reset (`TASA-YYMM-0001`) |
| O-6 | Is the Pathao merchant account and **API credentials** already available? | Needed before Phase 4 |
| O-7 | Is the Pathao **store** already created in the merchant panel? | Pull store list; create via package if none |

---

## 19. Glossary
- **COGS** — Cost of Goods Sold: what the sold items cost to make.
- **Gross profit** — Revenue minus COGS.
- **Net profit** — Gross profit minus all operating expenses.
- **COD** — Cash on Delivery; courier collects money from the customer.
- **Accrual (earned) basis** — Costs/revenue are counted when they are earned/incurred, not when cash moves.
- **Sq ft** — Square foot; unit used to measure leather.
