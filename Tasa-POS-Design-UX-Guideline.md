# Tasa POS — Design & UX Guideline

| | |
|---|---|
| **Document** | UI / UX Design Guideline |
| **Version** | 1.0 |
| **Date** | 11 October 2026 |
| **Works with** | `Tasa-POS-PRD.md` (PRD §13 gives the short summary; this document is the full spec) and `Tasa-POS-Database-Schema.md` |
| **Stack** | Vue 3 · Inertia.js · Tailwind CSS (v4, Starter Kit) · DaisyUI (themed, see §13) · Lineicons (outline) · Hanken Grotesk |
| **Design direction** | **Modern, sharp, high-contrast.** White base, one solid deep-teal accent, square corners, 1 px borders, no gradients, no glass, no dark mode. |

---

## 1. Design Vision

### 1.1 One sentence
> *A calm, sharp, white workspace where numbers are impossible to misread and the next action is always obvious.*

### 1.2 Design principles

| # | Principle | What it means in practice |
|---|---|---|
| P1 | **Legibility first** | Dark ink on white. Every text/background pair meets WCAG AA at minimum (§3.3). No light-grey-on-white text anywhere. |
| P2 | **Sharp, not harsh** | Square corners and crisp 1 px lines give precision; generous white space and a single accent colour keep it friendly. |
| P3 | **One job per screen** | Each screen has one primary action, shown as the only solid teal button. |
| P4 | **Numbers are the hero** | Money and quantities are large, right-aligned, tabular, and never truncated. |
| P5 | **Plain words** | Staff see "Balance due", not "Accounts receivable". Accounting terms appear only on Owner-only screens. |
| P6 | **Fast by default** | Smart defaults (today's date, default payment method, default Pathao store), keyboard-friendly, no unnecessary modals. |
| P7 | **Colour never works alone** | Every status uses colour **plus** an icon **plus** a text label. |
| P8 | **Same on every device** | Mobile, tablet and desktop get the same features; only the layout adapts (§9). |

### 1.3 Look and feel keywords
Crisp · Clean · Confident · Precise · Quiet. **Not:** playful, glossy, rounded, gradient-heavy, decorative.

---

## 2. Hard Rules (non-negotiable)

These come from the approved design direction and the light-theme-only standard.

| Rule | Detail |
|---|---|
| **Light theme only** | No dark mode, no `prefers-color-scheme: dark` styles, no theme toggle. Add `<meta name="color-scheme" content="light">` and set `color-scheme: light` on `:root` so the browser never auto-darkens form controls or scrollbars. |
| **White base** | Page background `#F4F6F6`, all content surfaces pure `#FFFFFF`. |
| **Single accent** | Deep teal `#0B6E5E` is the only brand colour. Status colours (§3.2) are used only for status. |
| **Square corners** | `border-radius: 0` on buttons, inputs, cards, badges, modals, dropdowns, tabs, avatars, toasts, checkboxes. The only exceptions are **radio buttons** (circle, universal convention) and the **loading spinner**. |
| **1 px borders** | All containers, inputs and dividers use 1 px solid lines. Never 2 px borders, except the 2 px focus outline and the 2 px active-tab underline. |
| **No gradients** | Solid fills only. |
| **No glass / blur / transparency effects** | Overlay scrim is a flat colour (see §3.4). |
| **No product images** | POS and inventory are text-only (PRD §1.4). No image placeholders or empty thumbnail boxes. |
| **No decorative shadows** | Elevation is expressed with borders. A shadow is allowed **only** on floating layers (dropdowns, modals, toasts) — see §3.5. |

---

## 3. Visual Foundations

### 3.1 Colour palette

**Brand teal**

| Token | Hex | Use |
|---|---|---|
| `teal-900` | `#074538` | Pressed state |
| `teal-800` | `#095A4C` | Hover state of primary button |
| **`teal-700`** | **`#0B6E5E`** | **Primary: buttons, links, active nav, focus ring, key figures** |
| `teal-200` | `#BFDDD6` | Tint borders |
| `teal-100` | `#E6F2EF` | Selected row, active nav background, tint badges |
| `teal-50` | `#F0F7F5` | Row hover, subtle highlight |

**Ink (text) and lines**

| Token | Hex | Use |
|---|---|---|
| `ink-900` | `#0F1B1A` | Headings, primary text, numbers |
| `ink-700` | `#2B3A38` | Body text, table text, labels |
| `ink-600` | `#4A5957` | Secondary text, helper text |
| `ink-500` | `#5F6F6D` | Captions, placeholders (lowest allowed text colour) |
| `line-200` | `#D5DCDB` | Card borders, dividers (decorative) |
| `line-300` | `#B9C4C2` | Stronger dividers, table header bottom |
| `line-400` | `#7F8E8C` | **Input, select and checkbox borders** (meets 3:1 for UI components) |

**Surfaces**

| Token | Hex | Use |
|---|---|---|
| `surface` | `#FFFFFF` | Cards, tables, inputs, sidebar, top bar, modals |
| `page` | `#F4F6F6` | App background behind cards |
| `sunken` | `#EBEFEE` | Table header, read-only fields, disabled fill |

### 3.2 Status colours (text colour / tint background)

| Meaning | Solid | Text on tint | Tint bg | Icon | Used for |
|---|---|---|---|---|---|
| **Success** | `#1B7F3B` | `#14602C` | `#E7F4EA` | check-circle | Paid, Delivered, Settled, In stock |
| **Warning** | `#B26A00` | `#8A5A00` | `#FFF4DB` | alert-triangle | Partial payment, Low stock, Token expiring |
| **Danger** | `#B42318` | `#8E1B12` | `#FDECEA` | alert-circle | Unpaid, Failed, Cancelled, Out of stock, errors |
| **Info** | `#1D5FA8` | `#174B85` | `#E8F0FA` | info-circle | In transit, Picked up, neutral notices |
| **Advance / Over-paid** | `#6B3FA0` | `#55327F` | `#F1EAF8` | arrow-up-circle | Worker/employee over-paid (they owe Tasa) |

> "Over-paid" gets its own violet so it can never be confused with "Danger" or "Success" on payroll screens.

### 3.3 Contrast matrix (WCAG 2.2 AA is the floor; target AAA for body text)

Ratios were calculated with the WCAG relative-luminance formula; re-check with a contrast tool during build.

| Foreground → Background | Ratio | Result |
|---|---|---|
| `ink-900` on `surface` | ≈ 17.6 : 1 | AAA |
| `ink-900` on `page` | ≈ 16.2 : 1 | AAA |
| `ink-700` on `surface` | ≈ 11.9 : 1 | AAA |
| `ink-700` on `sunken` (table header) | ≈ 10.3 : 1 | AAA |
| `ink-600` on `surface` | ≈ 7.4 : 1 | AAA |
| `ink-500` on `surface` | ≈ 5.3 : 1 | AA |
| `ink-500` on `page` | ≈ 4.9 : 1 | AA |
| `teal-700` on `surface` (links, figures) | ≈ 6.2 : 1 | AA |
| `surface` (white) on `teal-700` (button text) | ≈ 6.2 : 1 | AA |
| `teal-700` on `teal-100` (active nav, tint badge) | ≈ 5.4 : 1 | AA |
| white on danger `#B42318` | ≈ 6.6 : 1 | AA |
| white on success `#1B7F3B` | ≈ 5.1 : 1 | AA |
| status text on its tint (all five) | 5.4 – 7.9 : 1 | AA / AAA |
| `line-400` on `surface` (input border) | ≈ 3.4 : 1 | Passes 3:1 UI-component rule |

**Rules that follow from this**
- Body text is `ink-700` (never lighter). Secondary text is `ink-600`. `ink-500` is the lightest text allowed, only for captions and placeholders.
- Never put teal text on `page` for small text below 14 px.
- Disabled controls use `sunken` fill + `ink-500` text and a "not-allowed" cursor (disabled controls are exempt from contrast rules but must still be readable).

### 3.4 Overlay
Modal scrim: flat `#0F1B1A` at **40 %** opacity (a flat dim only — no blur).

### 3.5 Elevation

| Level | Style | Used for |
|---|---|---|
| 0 | 1 px `line-200` border, no shadow | Cards, tables, sidebar, top bar |
| 1 | 1 px `line-300` border + `0 6px 16px rgba(15,27,26,0.10)` | Dropdowns, popovers, date-range picker |
| 2 | 1 px `line-300` border + `0 12px 32px rgba(15,27,26,0.16)` | Modals, drawers, toasts |

### 3.6 Typography

**Font:** **Hanken Grotesk** (Google Fonts; weights 400, 500, 600, 700), self-hosted via `@font-face` with `font-display: swap`.

```css
font-family: "Hanken Grotesk", "Noto Sans Bengali", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
```

> **Bangla and the ৳ sign:** Hanken Grotesk has no Bengali glyphs. Customer names/addresses in Bangla and the **৳** symbol (U+09F3) must fall back to **Noto Sans Bengali** (self-hosted, weights 400/600/700). Test that ৳ renders next to numbers at every size and weight before sign-off.

**Numbers:** enable `font-variant-numeric: tabular-nums;` on every table, ledger and total so digits align in columns. If the font build lacks tabular figures, use a tabular fallback for amount cells only.

**Type scale** (desktop / mobile)

| Token | Size / line height | Weight | Use |
|---|---|---|---|
| `display` | 32 / 40 (mobile 28 / 36) | 700 | Hero numbers: grand total on POS, net profit on P&L |
| `h1` | 24 / 32 (mobile 22 / 30) | 700 | Page title |
| `h2` | 20 / 28 | 600 | Section title |
| `h3` | 16 / 24 | 600 | Card title, modal title |
| `body` | 14 / 22 (mobile **16** / 24) | 400 | Default text, table cells, inputs |
| `body-strong` | 14 / 22 | 600 | Labels, table header, emphasised values |
| `caption` | 12 / 18 | 500 | Helper text, timestamps, badge text |
| `overline` | 12 / 16, letter-spacing +0.04em, uppercase | 600 | Table section labels, widget labels |

Rules: minimum text size **12 px** (captions only); **form inputs are 16 px on mobile** (prevents iOS zoom); never use font-weight 300; titles use sentence case; no all-caps for anything longer than three words.

### 3.7 Spacing and layout grid
- **Base unit 4 px.** Allowed steps: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64.
- Card padding 20 px (mobile 16 px). Gap between cards 16 px (mobile 12 px). Page gutter 24 px desktop, 16 px mobile.
- Content max-width **1440 px**, centred. Forms max-width **720 px** unless two-pane.
- 12-column grid on desktop, 8 on tablet, 4 on mobile.

### 3.8 Iconography
- **Lineicons, outline style only**, consistent 1.5 px visual stroke. Sizes: 16 (inline), **20 (default)**, 24 (nav on mobile / empty states 40).
- Icon colour matches adjacent text; icons inside primary buttons are white.
- Icon-only buttons **must** have a visible tooltip and an `aria-label`.
- Pick icons by concept: dashboard, cart/receipt, users, box, leather/layers, tag/accessory, wallet, bank-note, user-group (payroll), bar-chart/pie-chart (reports), settings, shield (roles), truck (courier), printer, download, search, plus, edit, trash, check, close, alert, info, calendar, filter, phone, location. Choose the matching Lineicons outline glyph for each and keep the mapping in one `icons.ts` file.

### 3.9 Motion
- Duration **120–160 ms**, `ease-out`. Only opacity, background-colour and 4 px translate. No bounce, no parallax, no animated gradients.
- Skeleton loading uses a flat `sunken` block with a slow opacity pulse (1.2 s).
- Respect `prefers-reduced-motion: reduce` → remove all transitions except colour.

---

## 4. App Shell and Navigation

### 4.1 Desktop (≥ 1024 px)

```
┌──────────────┬──────────────────────────────────────────────────────────┐
│  TASA        │  Page title                       🔍 Search   ＋ New sale   ◯ MA ▾ │  ← top bar 56 px
│              ├──────────────────────────────────────────────────────────┤
│ ▌Dashboard   │                                                          │
│  Sales    ▾  │   Content area  (page bg #F4F6F6, cards #FFFFFF)         │
│  Inventory▾  │                                                          │
│  Expenses    │                                                          │
│  Payroll     │                                                          │
│  Reports  ▾  │                                                          │
│  Settings    │                                                          │
│              │                                                          │
│  ◯ Monayem   │                                                          │
│  Owner  ⎋    │                                                          │
└──────────────┴──────────────────────────────────────────────────────────┘
   sidebar 248 px, white, 1 px right border
```

- **Sidebar:** white, 1 px `line-200` right border. Item height 44 px, icon 20 px + label 14 px/500 `ink-700`.
- **Active item:** `teal-100` fill, 3 px solid `teal-700` bar on the left edge, label `teal-700` weight 600. **Hover:** `teal-50`.
- **Groups** (Sales, Inventory, Reports) expand inline; sub-items are indented 32 px.
- **Top bar:** white, 1 px bottom border. Page title left; global search (invoice no., customer name/phone, product); one primary shortcut **＋ New sale**; user menu (name, role, Log out).
- Sections the user cannot access are **removed** from the menu, not greyed out (PRD §4, §12).

### 4.2 Tablet (640–1023 px)
Sidebar collapses to a **72 px icon rail** (tooltip on hover/long-press); tapping the menu icon opens the full sidebar as an overlay drawer with scrim.

### 4.3 Mobile (< 640 px)
- Top bar 56 px: menu icon · page title · search icon.
- **Bottom navigation (64 px, 5 slots):** **Sale · Invoices · Inventory · Expenses · More**. "More" opens a sheet with Dashboard, Payroll, Reports, Settings (as allowed). Active slot: `teal-700` icon + label with a 2 px top bar.
- Safe-area padding for iOS (`env(safe-area-inset-bottom)`).

### 4.4 Page header pattern
Every page: **Title (h1)** · one-line description in `ink-600` (optional) · **primary action** (right, solid teal) · optional secondary actions (outline). Breadcrumbs only for depth ≥ 3.

---

## 5. Components

All components are square-cornered. Heights: **40 px** desktop controls, **48 px** on touch devices (mobile/tablet).

### 5.1 Buttons

| Type | Style | Use |
|---|---|---|
| **Primary** | Solid `teal-700`, white text 14/600, hover `teal-800`, pressed `teal-900` | One per screen: Save sale, Save, Add |
| **Secondary** | White fill, 1 px `ink-900` border, `ink-900` text, hover `teal-50` | Cancel, Print, Export |
| **Ghost** | No border, `teal-700` text, hover `teal-50` | Low-emphasis actions, table row actions |
| **Danger** | Solid `#B42318`, white text | Cancel sale, Delete (only inside a confirm dialog) |
| **Danger outline** | 1 px `#B42318` border and text | Opening a destructive flow |

States: **Focus** = 2 px solid `teal-700` outline with 2 px offset. **Disabled** = `sunken` fill, `ink-500` text. **Loading** = spinner replaces the icon, label stays, button disabled (prevents double-submit — PRD §6.1).
Padding 0 16 px; icon gap 8 px. Full-width on mobile for the main form action.

### 5.2 Form fields
- **Label above** the field, 14/600 `ink-700`. Required fields marked with a `*` in `#B42318` **and** the word "Required" in assistive text.
- **Input:** white fill, 1 px `line-400` border, text `ink-900` 14 px (16 px mobile), height 40/48, padding 0 12 px. **Placeholder** `ink-500`, used only for examples, never as the label.
- **Focus:** border becomes `teal-700` and a 2 px `teal-700` outline appears.
- **Error:** 1 px `#B42318` border, message below with an alert icon in `#8E1B12` 12/500. Never use red text alone.
- **Helper text:** 12/500 `ink-600` below the field.
- **Read-only/computed** (e.g., "Balance due", "Total cost"): `sunken` fill, `ink-900` bold, no focus ring.
- **Money input:** right-aligned, **৳** prefix inside the field, tabular numerals, input mode `decimal`.
- **Phone input:** numeric keypad (`inputmode="numeric"`), live format check `01XXXXXXXXX`, inline message "Enter an 11-digit mobile number".
- **Select:** native look on mobile; on desktop a custom listbox with search when options > 8 (customers, leather items, Pathao city/zone/area).
- **Date:** default today; calendar popover; text typing allowed.
- **Checkbox/Toggle:** 20 px square box (toggle = rectangular track, square knob), `line-400` border, checked = solid `teal-700` with white tick.
- **Validation timing:** validate on blur and on submit; after the first error, re-validate while typing. Error summary at the top of long forms with anchor links.

### 5.3 Tables (desktop/tablet)
- White card, 1 px `line-200` border. **Header row** `sunken`, 12/600 overline in `ink-700`, 1 px `line-300` bottom border, sticky on scroll.
- Row height 48 px (compact option 40 px), 14/400 `ink-700`; **primary column** (invoice no., product name) 14/600 `ink-900`.
- **No zebra striping.** Rows separated by 1 px `line-200`. Hover = `teal-50`. Selected = `teal-100`.
- **Numeric columns** right-aligned, tabular numerals, header also right-aligned. **Totals row** pinned at the bottom: `sunken` fill, 600 weight, 1 px top `ink-900` border.
- Actions column: ghost icon buttons (view, print, more) with tooltips; the "more" menu holds destructive actions.
- Pagination: 25 rows default; footer shows "Showing 1–25 of 312" and Prev/Next + page-size select. Remember filters and page in the URL.
- Empty table: see §5.9.

### 5.4 Mobile card list (< 640 px)
Tables become stacked cards (white, 1 px border, 12 px gap):

```
┌──────────────────────────────────────┐
│ TASA-2610-0042              ● Paid   │  ← title row: invoice no. + status badge
│ Rahim Uddin · 01712 345 678          │
│ 11 Oct 2026                          │
│ ─────────────────────────────────────│
│ Total                      ৳ 4,500   │
│ Due                        ৳ 0       │
│ Courier: In transit        ⋯         │
└──────────────────────────────────────┘
```
Show 3–5 key fields; the rest open on tap. Whole card is the tap target (min 56 px tall).

### 5.5 Badges and status chips
Square, 24 px tall, 8 px horizontal padding, **icon (16) + label (12/600)**, tint background + status text colour from §3.2, 1 px border in the status solid colour at 30 % — drawn as a flat colour, not transparency effects (use the pre-computed hex tint border).

| Domain | Values → colour |
|---|---|
| **Payment** | Paid → Success · Partial → Warning · Unpaid → Danger |
| **Sale** | Active → neutral (ink) · Cancelled → Danger · Returned / Partly returned → Warning |
| **Courier (Pathao)** | Queued → neutral · Failed → Danger · Created → Info · Picked up → Info · In transit → Info · Delivered → Success · Returned → Warning · On hold → Warning · Cancelled → Danger |
| **COD** | To receive → Warning · Settled → Success |
| **Stock** | In stock → Success · Low stock → Warning · Out of stock → Danger |
| **Payroll balance** | We owe them → Warning · Settled → Success · Over-paid → Advance (violet) |

### 5.6 Cards and stat widgets
- Card: white, 1 px `line-200`, padding 20 px, title h3 + optional action link top-right.
- **Stat widget:** overline label (`ink-600`) → **display number** (`ink-900`, 32/700, tabular) → delta/secondary line (12/500). An optional 3 px left accent bar in `teal-700` marks the primary KPI (use on at most one widget per row).
- Negative profit shows in `#B42318` with a minus sign and a "Loss" label; positive profit in `ink-900` (not green) to keep teal/green distinct.

### 5.7 Tabs
Underline tabs: 44 px tall, label 14/600; inactive `ink-600`, hover `ink-900`, **active `teal-700` with a 2 px bottom border**; 1 px `line-200` baseline across the full width. On mobile, tabs scroll horizontally with a visible edge cut-off cue.

### 5.8 Dialogs, drawers, toasts
- **Confirm dialog** (destructive): width 440, title (what will happen), 1–2 lines of consequence, **required reason field** where the PRD demands it (cancel sale, adjustments), Danger button on the right, Secondary "Keep it" on the left. Never auto-focus the destructive button.
- **Drawer** (right, 480 px) for quick-add forms (add customer, add leather purchase, add payment). On mobile, drawers become **full-screen sheets** with a sticky action bar.
- **Toast:** bottom-right (mobile: top), 360 px, white, 1 px border, 3 px left bar in the status colour, icon + message + optional action. Success auto-dismisses at 4 s; **errors stay until closed**.
- A toast is **not** proof of saving (project DB-verification standard): after a save, the UI also refreshes from the server so the row visibly appears.

### 5.9 Empty, loading and error states
- **Empty:** 40 px outline icon (`ink-500`), h3 headline, one sentence in `ink-600`, one primary button. Example: *"No leather purchases yet — Record the first purchase to start tracking stock."*
- **Loading:** skeleton blocks matching the final layout (never a full-page spinner after first paint).
- **Error (page):** alert card with plain cause + **Try again** button; never show stack traces or raw codes to users.
- **Permission denied (403):** friendly page "You don't have access to this section" with a Back button.

### 5.10 Date-range picker
Used on Sales, Reports, Expenses, P&L.
- Button shows the current range ("1 Oct – 11 Oct 2026").
- Popover with **presets on the left** — Today, Yesterday, Last 7 days, This month, Last month, This year, Custom — and a two-month calendar on the right. On mobile: full-screen sheet, presets as a horizontal chip row, then the calendar.
- Applying a range updates the URL, so refresh and sharing keep the filter.

### 5.11 Search and filter bar
One row above tables: search field (left), filter chips/selects, date-range, **Export** (secondary). Applied filters appear as removable chips with a "Clear all" ghost button. On mobile, filters collapse into a **Filters** button opening a sheet.

---

## 6. Core Screens

Wireframes are structural, not pixel-final. Hex values and sizes come from §3.

### 6.1 Login
Centered 400 px white card on `page` background, 1 px border. Shop logo/name, email, password, **Sign in** (primary, full-width), "Forgot password". No decorative imagery. Owner 2FA step uses six separate 48 px square digit boxes.

### 6.2 Dashboard

**Owner**
```
Dashboard                                               [ This month ▾ ]
┌──────────────┬──────────────┬──────────────┬──────────────┐
│ TODAY'S SALES│ THIS MONTH   │ NET PROFIT   │ COD TO RECEIVE│
│ ৳ 18,400     │ ৳ 3,42,000   │ ▌৳ 41,500    │ ৳ 22,300     │
│ 6 invoices   │ 58 invoices  │ 12.1% margin │ 9 orders     │
└──────────────┴──────────────┴──────────────┴──────────────┘
┌─────────────────────────────────────┬──────────────────────────┐
│ Sales vs expenses (6 months)        │ Needs attention          │
│ [ square bars, direct labels ]      │ ⚠ 2 failed Pathao orders │
│                                     │ ⚠ 3 items low in stock   │
│                                     │ ⚠ Token expires in 5 days│
├─────────────────────────────────────┼──────────────────────────┤
│ Latest invoices (table, 10 rows)    │ Customer dues · Payroll  │
│                                     │ balance · Ad spend / ROAS│
└─────────────────────────────────────┴──────────────────────────┘
```
**Staff:** same layout **without** profit, payroll, dues-value and ROAS widgets; the Quick actions card (New sale, Add expense, Add leather purchase) takes the free space.
**"Needs attention"** is always the second-most prominent block; each line is a link straight to the fix (Retry, Open stock).

### 6.3 New Sale (POS) — the most important screen

**Desktop / large tablet (two panes)**
```
New sale                                                     Invoice will be: TASA-2610-0043
┌─────────────────────────────────────────┬───────────────────────────────────────┐
│ 1  CUSTOMER                             │ 3  CART                               │
│ Phone*  [ 01712345678      ] ✓ Found    │ Item              Qty   Price  Total  │
│ Name*   [ Rahim Uddin           ]       │ Bifold wallet – Tan  1  1,800  1,800  │
│ Address*[ House 12, Road 4, ...  ]      │ Card holder – Black  2    900  1,800  │
│ City* [Dhaka ▾] Zone*[Mirpur ▾] Area*[▾]│ ─────────────────────────────────────│
│ ○ Courier success rate: 92%             │ Subtotal                      3,600   │
├─────────────────────────────────────────│ Discount  [ ৳ ▾ ][  100 ]      −100   │
│ 2  PRODUCTS                             │ Delivery charge   [ 60 ]         60   │
│ 🔍 [ Search name or code…        ]      │ ─────────────────────────────────────│
│ ┌─────────────────────────────────────┐ │ GRAND TOTAL            ৳ 3,560        │ ← display size
│ │ Bifold wallet – Tan      ৳1,800  12 │ │                                       │
│ │ Card holder – Black      ৳  900   0 │ │ 4  PAYMENT                            │
│ │ Belt – Brown 38          ৳1,500   5 │ │ Paid now [ 500 ]  Method [bKash ▾]    │
│ │ ＋ Add custom item                  │ │ Balance due (COD)       ৳ 3,060       │
│ └─────────────────────────────────────┘ │ 5  DELIVERY                           │
│  (text rows, stock shown at right,      │ (●) Send via Pathao  ( ) Hand delivery│
│   no images)                            │ Weight [0.5] kg  Type [Normal ▾]      │
│                                         │ Note [                         ]      │
│                                         │ ┌───────────────────────────────────┐ │
│                                         │ │      SAVE SALE & CREATE ORDER     │ │ ← only solid teal
│                                         │ └───────────────────────────────────┘ │
└─────────────────────────────────────────┴───────────────────────────────────────┘
```

**Behaviour and UX details**
- **Phone first.** Typing a known number auto-fills name, address and last used city/zone/area and shows a green "Found" tag; unknown number shows "New customer".
- **Product rows are plain text lines** (name · price · stock). Tap/click or press Enter on a row to add; adding the same product increases the quantity. Stock column colour follows §5.5 (Low = amber text + icon).
- **Cart quantity** uses a −/+ stepper with a typed field in the middle; swipe-left (mobile) or the trash icon removes a line.
- **Grand total** uses the `display` style in `ink-900`; it is always visible (sticky on mobile).
- **Balance due** is read-only; when the method is *Cash on Delivery* a helper line states: "Pathao will collect ৳ 3,060 from the customer."
- **Save button** shows a spinner and is disabled after the first click. On success: a success screen with **Print invoice · Download PDF · New sale**, plus the courier result line ("Pathao order created · Consignment 12345" or a warning chip "Courier failed — Retry").
- **Keyboard (desktop):** `/` focuses product search · `Enter` adds highlighted product · `Ctrl+Enter` saves · `Esc` clears search.
- **Validation:** the Save button is never hidden; clicking it with missing data scrolls to and highlights the first error.

**Mobile (< 640 px): three-step flow with a sticky footer**
```
┌──────────────────────────────┐
│ ① Customer ─ ② Items ─ ③ Pay │  ← progress, step names visible
├──────────────────────────────┤
│  (current step content,      │
│   16 px inputs, 48 px rows)  │
├──────────────────────────────┤
│ Total ৳ 3,560     [ Next → ] │  ← sticky bar; last step: [ Save sale ]
└──────────────────────────────┘
```
Cart is a bottom-sheet summary opened from the sticky bar ("2 items"). Courier fields appear on step ③.

### 6.4 Invoices / Sales list
Search + filter bar (date range, payment status, courier status, method) → table (§5.3) with columns: Invoice no. · Date · Customer · Phone · Total · Paid · Due · Payment · Courier · Actions. Row click opens the **invoice detail** page: left = invoice preview, right = timeline (created, payments, courier status changes, cancellations — from `audit_logs` and `courier_status_logs`), top-right actions **Print · PDF · Add payment · ⋯ (Cancel / Return / Retry Pathao)**.

### 6.5 Invoice document (print design)
Uses the same system but is **print-first: black text on white, teal used only for the shop name rule and the totals bar** so it still looks right on a monochrome printer.

```
┌──────────────────────────────────────────────────────────┐
│ [LOGO]  TASA                                  INVOICE    │
│ Address · Phone                         TASA-2610-0043   │
│ ──────────────────────────────── (2 pt teal rule)        │
│ Billed to                       Date        11 Oct 2026  │
│ Rahim Uddin                     Payment     bKash + COD  │
│ 01712 345 678                   Courier     Pathao #12345│
│ House 12, Road 4, Mirpur, Dhaka                          │
│ ──────────────────────────────────────────────────────── │
│ #  Item                    Qty     Price       Amount    │
│ 1  Bifold wallet – Tan      1     1,800.00    1,800.00   │
│ 2  Card holder – Black      2       900.00    1,800.00   │
│ ──────────────────────────────────────────────────────── │
│                          Subtotal            3,600.00    │
│                          Discount             −100.00    │
│                          Delivery               60.00    │
│                          ┌───────────────────────────┐   │
│                          │ TOTAL         ৳ 3,560.00  │   │ ← solid teal bar, white text
│                          └───────────────────────────┘   │
│                          Paid                  500.00    │
│                          Balance due         3,060.00    │
│ Footer note / terms                                      │
└──────────────────────────────────────────────────────────┘
```
- **A4:** 20 mm margins, 11 pt body, 9 pt footer. **A5:** 12 mm margins, 10 pt body. **Thermal 80 mm:** single column, 10 pt, dashed separators, no colour (all black), totals bold.
- Table numbers right-aligned and tabular. Currency printed as `৳` with two decimals.
- Print CSS: `@page` size per setting, hide app chrome, avoid page breaks inside rows.
- The custom template editor (Owner) shows a **live preview** beside the HTML field and a "Reset to default" button (PRD §6.3).

### 6.6 Inventory

**Products:** search + category filter + stock filter (All / Low / Out). Table columns: Name · Code · Category · Cost* · Price · **Stock** · Status · Actions (*cost visible only with permission). Primary action **＋ Add product**; secondary **Import Excel** (opens a drawer: download template → upload → preview table with per-row errors → confirm).
**Product form:** single column, 720 px: Name, Code (auto), Category, Cost price, Selling price, Minimum stock, Active toggle. No image field.

**Leather and Accessories (same pattern)**
```
Leather                                    [ ＋ Purchase ]  [ − Consumption ]
[ Stock ]  Purchases   Consumption                          [ 1 Oct–11 Oct ▾ ]
┌──────────────────────────────────────────────────────────────────────────────┐
│ Item            Bought     Used      CURRENT STOCK   Avg cost    Value       │
│ Cow Nappa Tan   120.0 sqft  84.5 sqft   ▌ 35.5 sqft   ৳ 310.00   ৳ 11,005    │
│ Goat Black       60.0 sqft  58.0 sqft   ⚠  2.0 sqft   ৳ 280.00   ৳ 560       │
└──────────────────────────────────────────────────────────────────────────────┘
```
- **Current stock** is the visually strongest column (600 weight, `ink-900`); low stock gets the warning badge plus amber text.
- Purchase and Consumption forms open in a **right drawer**; the unit (**sq ft** or item unit) is shown inside the field suffix; the form shows live "Total cost = qty × rate" in a read-only field and, for consumption, "Stock after this entry: 28.5 sq ft" (turns red with an alert if it would go below zero).
- Date-range filter answers "how much was bought/used this month".

### 6.7 Expenses
Top: date range · category filter · **＋ Add expense** (primary). A **summary strip** above the table shows total and top 3 categories as plain horizontal bars with direct labels (**Facebook Ads** always listed first when present). Table columns: Date · Category · Paid to · Note · Method · Amount · Actions. Courier-generated rows carry an "Auto" badge and are read-only except for amount adjustments by the Owner.
The add form is a drawer with large amount field first, then category, date, method, vendor, note, attachment drop-zone (square dashed 1 px border). Recurring prompt: a banner on the 1st — *"Add this month's rent — ৳ 25,000?  [Add now] [Not now]"*.

### 6.8 Payroll (Owner only)

**Salaried employees — month sheet**
```
Salary · October 2026                                   [ ‹ Sep ] [ Nov › ]
┌───────────────────────────────────────────────────────────────────────────┐
│ Employee   Salary  Bonus  Deduct  Payable   Paid      Balance               │
│ Karim      20,000    0       0    20,000   10,000    ● We owe him  10,000   │
│ Salma      18,000  1,000     0    19,000   21,000    ● Over-paid    2,000   │
└───────────────────────────────────────────────────────────────────────────┘
Row action: [ ＋ Add payment ]  → drawer: Date · Amount · Type (Salary/Advance) · Method · Note
```
Balance labels use plain words: **"We owe him/her"** (warning), **"Paid in full"** (success), **"Over-paid — adjusts next month"** (violet). The word "payable" is allowed; "accrual" is not.

**Wage workers — ledger**
```
Rafiq · Stitcher · ৳ 600 / day                      [ ＋ Work entry ] [ ＋ Payment ]
┌────────────────────────────────────────────────────────────────────┐
│ EARNED THIS WEEK  ৳ 3,600   PAID  ৳ 4,000   BALANCE  ● Over-paid ৳ 400 │
└────────────────────────────────────────────────────────────────────┘
Date        Description          Earned     Paid      Running balance
04 Oct      6 days @ 600          3,600        —          3,600 owed
05 Oct      Payment (cash)           —     2,000          1,600 owed
09 Oct      Payment (bKash)          —     2,000           400 over-paid
```
Running balance text switches colour/label (Warning "owed" ↔ Violet "over-paid"). The **Weekly payout** helper is a table of all workers with balance and a one-tap **Pay** button per row.

### 6.9 Reports

**Sales report:** date range → summary strip (Invoices · Gross sales · Discounts · Returns · **Net sales** · Received · Due) → tabs **By product · By day · By payment method · By customer**. By-product table sorts by quantity sold by default; profit columns appear only with permission. Export button group (CSV · Excel · PDF).

**Profit & Loss (Owner)** — an accountant's statement, laid out as a clean ledger:
```
Profit & Loss          [ 1 Oct – 31 Oct 2026 ▾ ]   [ ☐ Compare with previous ]  [ Export ▾ ]
┌────────────────────────────────────────────────────────────────────────┐
│ NET PROFIT        ▌ ৳ 6,000         Net margin 3.1%    Gross margin 58.5% │
└────────────────────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────────────────────┐
│ REVENUE                                                                  │
│   Gross sales                                              2,00,000.00 › │
│   − Discounts                                                (5,000.00)› │
│   − Returns                                                 (10,000.00)› │
│ NET SALES                                                  1,85,000.00   │  ← subtotal row: bold, top border
│   + Delivery charges collected                                8,000.00 › │
│ TOTAL REVENUE                                              1,93,000.00   │
│ COST OF GOODS SOLD                                           (80,000.00)›│
│ GROSS PROFIT                                               1,13,000.00   │  ← bold, sunken fill
│ OPERATING EXPENSES                                                       │
│   Facebook Ads                                              (25,000.00)› │
│   Courier charges                                           (14,000.00)› │
│   Salaries · Wages · Packaging · Rent & utilities · Other     …          │
│ TOTAL OPERATING EXPENSES                                   (1,07,000.00) │
│ NET PROFIT                                                     6,000.00  │  ← heavy 2 px top & bottom ink border
└────────────────────────────────────────────────────────────────────────┘
Memo (not in profit): COD to receive · Customer dues · Payroll payable · Closing stock · Cash summary
```
- Columns: **Label (left) · Amount (right, tabular)**; with comparison on, add *Previous* and *Change* columns.
- Section headers = overline style; subtotal rows = 600 weight with a 1 px `ink-900` top border; **grand lines (Gross profit, Net profit)** use `sunken` fill, 700 weight, and Net profit gets a 2 px top and bottom border.
- Deductions show in **parentheses** (accounting style) in `ink-900`; a loss is `#B42318` with the word "Loss".
- Every amount with a **›** chevron is a link that opens the underlying records in a right drawer (PRD §11.5 drill-down).
- A small info line under the date range states the **basis**: *"Sales counted by invoice date · Salaries counted by month · Wages by work date."*
- Charts (below the statement): revenue vs expenses vs profit by month (square bars), expense breakdown (ranked horizontal bars, not a donut), top 5 profitable products.

### 6.10 Courier orders
Table with Pathao status badge, COD amount, courier charge, settlement badge. Failed rows are tinted `#FDECEA` and show the short error under the invoice number with a **Retry** button. Row actions: Refresh status · Open in Pathao · Mark COD settled (drawer asks for the amount actually received).

### 6.11 Users and roles (Owner)
**Users:** simple table (Name · Email · Role · Status · Last login · Actions).
**Role editor — permission matrix**
```
Role: Staff                                              [ Save changes ]
┌──────────────────────────┬───────┬───────┬───────┬────────┐
│ Section                  │ View  │ Add   │ Edit  │ Delete │
├──────────────────────────┼───────┼───────┼───────┼────────┤
│ POS / New sale           │  ☑    │  ☑    │  ☐    │   —    │
│ Invoices & customers     │  ☑    │   —   │  ☑    │   ☐    │
│ Inventory · Products     │  ☑    │  ☑    │  ☑    │   ☐    │
│ Inventory · Leather      │  ☑    │  ☑    │  ☑    │   ☐    │
│ Inventory · Accessories  │  ☑    │  ☑    │  ☑    │   ☐    │
│ Expenses                 │  ☑    │  ☑    │  ☐    │   ☐    │
│ Payroll (Salary & wages) │  ☐    │  ☐    │  ☐    │   ☐    │
│ Profit & Loss            │  ☐    │   —   │   —   │    —   │
├──────────────────────────┴───────┴───────┴───────┴────────┤
│ Special permissions                                          │
│ ☐ See cost & purchase prices   ☐ Cancel / return sales       │
│ ☐ See profit in sales report   ☐ Mark COD as settled         │
└──────────────────────────────────────────────────────────────┘
```
Checkbox cells are 44 px targets. Sensitive rows (**Payroll, Profit & Loss**) use the warning tint `#FFF4DB` as the row background plus a lock icon to signal "sensitive". The **Owner** role page is read-only with a banner "Owner always has full access." Unsaved changes show a sticky bar: *"You have unsaved changes — [Save] [Discard]"*. On mobile the matrix becomes one accordion per section with toggles.

### 6.12 Settings
Left sub-navigation (desktop) / list (mobile): General · Invoice branding · Pathao Courier · Categories & units · Users & roles.
- **Invoice branding:** form on the left, **live A4/A5/thermal preview** on the right, template editor below in a tab.
- **Pathao Courier:** connection status chip (Connected / Problem), **token days-left** meter (warning < 7 days), default store select, defaults for delivery type/weight, **Test connection** button.

---

## 7. Responsive Behaviour

| Breakpoint | Layout |
|---|---|
| **< 640 px (mobile)** | Bottom nav, single column, cards instead of tables, 16 px inputs, 48 px controls, full-screen drawers, POS in 3 steps with sticky footer |
| **640–1023 px (tablet)** | Icon-rail sidebar, two-column POS where width ≥ 768, tables with horizontal scroll hints, 48 px controls |
| **1024–1279 px (small desktop)** | Full sidebar, two-pane POS, tables |
| **≥ 1280 px (desktop)** | Full sidebar, content up to 1440 px, optional compact tables |

Rules: no horizontal page scroll at 360 px width; touch targets ≥ 44 × 44 px; tables that cannot become cards (P&L, ledgers) stay as tables but switch to a two-column **label / amount** layout with larger row padding on mobile; modals and drawers never exceed the viewport (internal scroll with sticky action bar); test at 360, 390, 768, 1024, 1280 and 1440 px.

---

## 8. Content, Formatting and Microcopy

### 8.1 Formats
| Item | Format | Example |
|---|---|---|
| Currency | `৳` + amount, **South-Asian digit grouping** (lakh/crore) via `Intl.NumberFormat('en-IN')`; 2 decimals in tables and statements, 0 decimals on dashboard headline cards | `৳ 1,25,000.00` · `৳ 1,25,000` |
| Negative / deduction | Parentheses on statements; minus sign elsewhere | `(5,000.00)` · `−৳ 100` |
| Dates | `DD Mon YYYY` (table), `11 Oct 2026, 2:30 PM` (timestamps) | `11 Oct 2026` |
| Quantity | Pieces as integers; leather with up to 2 decimals + `sq ft` | `35.5 sq ft` |
| Phone | Show as `01712 345 678`, store as `01712345678` | |
| Percent | One decimal | `58.5%` |

### 8.2 Voice
Short, direct, friendly. Verbs on buttons (**Save sale**, **Add payment**, **Retry order**). Say what happened and what to do next. No exclamation marks, no humour in errors.

### 8.3 Sample copy
| Situation | Copy |
|---|---|
| Missing phone | "Enter the customer's 11-digit mobile number. We need it to send the parcel." |
| Invalid address | "Please enter a full address (at least 10 characters) so the courier can find it." |
| Saved sale | "Sale saved. Invoice TASA-2610-0043 is ready." |
| Pathao failed | "Sale saved, but the courier order failed: *reason*. [Retry order]" |
| Not enough stock | "Only 2 left in stock. Add anyway?" (only if negative stock is allowed) otherwise "Only 2 left in stock." |
| Cancel sale dialog | "Cancel invoice TASA-2610-0043? Stock will be returned and the sale will be removed from reports. The invoice number cannot be reused." + required reason |
| Over-paid worker | "Rafiq has been paid ৳ 400 more than he has earned. This will be adjusted against his next earnings." |
| No access | "You don't have access to this section. Ask the owner if you need it." |
| Low stock | "Goat Black leather is running low: 2.0 sq ft left (minimum 10)." |

---

## 9. Accessibility (WCAG 2.2 AA)

| Area | Requirement |
|---|---|
| Contrast | Meet §3.3 for all text and UI components |
| Focus | Always visible: 2 px `teal-700` outline, 2 px offset; never removed. Logical tab order; skip-to-content link |
| Keyboard | Every action reachable without a mouse; dialogs trap focus and return it on close; `Esc` closes overlays |
| Labels | Every input has a visible label and `aria-describedby` for help/errors; icon buttons have `aria-label` and tooltip |
| Status | Never colour alone — icon + text on every badge and chart series |
| Tables | Real `<table>`, `<th scope>`, captions where useful; sortable columns announce sort direction |
| Live regions | Toasts and save results announced via `aria-live="polite"` (errors `assertive`) |
| Targets | ≥ 44 × 44 px on touch; ≥ 24 × 24 px spacing-adjusted minimum on desktop |
| Motion | Honour `prefers-reduced-motion` |
| Language | `lang="en"`, and `lang="bn"` on Bangla text spans |
| Zoom | Layout works at 200 % browser zoom and with 16 px base font size changes |

---

## 10. Charts and Data Visualisation

- Library: **Chart.js** (via `vue-chartjs`) or ApexCharts; configure with square bar corners and no gradients, shadows or 3D.
- **Series colours (in order):** `teal-700 #0B6E5E` · `ink-700 #2B3A38` · `#B26A00` · `#6B3FA0` · `#1D5FA8` · `#7F8E8C`. Maximum 4 series per chart.
- **Direct labels** on bars/lines where space allows; otherwise a legend with square swatches. Axis text 12 px `ink-600`, grid lines 1 px `line-200`, no vertical grid.
- Every chart has a title, a one-line takeaway sentence below it, and a **"View as table"** toggle (accessibility + exact numbers).
- Prefer **bars and ranked lists** over pie/donut charts. Money axes use the same ৳ South-Asian formatting, abbreviated (`1L`, `10K`) only on axis ticks.
- Empty chart: show the empty-state pattern, not a blank grid.

---

## 11. Interaction Patterns Worth Standardising

| Pattern | Spec |
|---|---|
| **Quick add** | Anything the shop adds often (customer, leather purchase, payment, expense) opens in a right drawer; the list refreshes behind it after save. |
| **Inline edit** | Only for quantity in the cart and for minimum-stock values; everything else uses a form. |
| **Destructive actions** | Always a confirm dialog with consequence text; sales and payroll never offer "Delete", only Cancel / Reverse with a reason. |
| **Unsaved changes** | Prompt before leaving a dirty form (browser + in-app). |
| **Optimistic UI** | Not used for money or stock. Show a spinner and wait for the server. |
| **Search** | Debounce 250 ms; highlight matching text; show "No results for '…'" with a clear-search button. |
| **Sorting** | Click header; arrow icon shows direction; default sorts documented per table (sales by date desc, products by name asc). |
| **Persistence** | Filters, date range and page live in the URL; the last-used date range is remembered per user. |
| **Offline/slow network** | Clear banner "Connection lost — changes are not saved" with automatic retry; the Save sale button is disabled while offline. |

---

## 12. Design Tokens (implementation reference)

### 12.1 Tailwind v4 theme tokens (`resources/css/app.css`)
```css
@import "tailwindcss";

@theme {
  --font-sans: "Hanken Grotesk", "Noto Sans Bengali", system-ui, sans-serif;

  --color-teal-50:  #F0F7F5;
  --color-teal-100: #E6F2EF;
  --color-teal-200: #BFDDD6;
  --color-teal-700: #0B6E5E;
  --color-teal-800: #095A4C;
  --color-teal-900: #074538;

  --color-ink-900: #0F1B1A;
  --color-ink-700: #2B3A38;
  --color-ink-600: #4A5957;
  --color-ink-500: #5F6F6D;

  --color-line-200: #D5DCDB;
  --color-line-300: #B9C4C2;
  --color-line-400: #7F8E8C;

  --color-page:   #F4F6F6;
  --color-sunken: #EBEFEE;

  --color-success: #1B7F3B;  --color-success-text: #14602C;  --color-success-bg: #E7F4EA;
  --color-warning: #B26A00;  --color-warning-text: #8A5A00;  --color-warning-bg: #FFF4DB;
  --color-danger:  #B42318;  --color-danger-text:  #8E1B12;  --color-danger-bg:  #FDECEA;
  --color-info:    #1D5FA8;  --color-info-text:    #174B85;  --color-info-bg:    #E8F0FA;
  --color-advance: #6B3FA0;  --color-advance-text: #55327F;  --color-advance-bg: #F1EAF8;

  --radius-*: initial;       /* remove every rounded utility */
  --radius-none: 0px;
}

:root { color-scheme: light; }
body  { background: var(--color-page); color: var(--color-ink-700);
        font-variant-numeric: tabular-nums; }
```

### 12.2 DaisyUI theme (DaisyUI 5 syntax — verify against the installed version)
```css
@plugin "daisyui" { themes: tasa --default; }

@plugin "daisyui/theme" {
  name: "tasa";
  default: true;
  prefersdark: false;
  color-scheme: light;

  --color-base-100: #FFFFFF;
  --color-base-200: #F4F6F6;
  --color-base-300: #D5DCDB;
  --color-base-content: #0F1B1A;

  --color-primary: #0B6E5E;
  --color-primary-content: #FFFFFF;
  --color-secondary: #2B3A38;
  --color-secondary-content: #FFFFFF;
  --color-accent: #0B6E5E;
  --color-accent-content: #FFFFFF;
  --color-neutral: #0F1B1A;
  --color-neutral-content: #FFFFFF;

  --color-info: #1D5FA8;     --color-info-content: #FFFFFF;
  --color-success: #1B7F3B;  --color-success-content: #FFFFFF;
  --color-warning: #B26A00;  --color-warning-content: #FFFFFF;
  --color-error: #B42318;    --color-error-content: #FFFFFF;

  --radius-selector: 0rem;
  --radius-field: 0rem;
  --radius-box: 0rem;
  --size-selector: 0.25rem;
  --size-field: 0.25rem;
  --border: 1px;
  --depth: 0;
  --noise: 0;
}
```
> If the installed DaisyUI is v4, express the same values in `tailwind.config.js → daisyui.themes` using `primary`, `base-100`… keys and set `--rounded-box`, `--rounded-btn`, `--rounded-badge`, `--tab-radius` to `0`.

### 12.3 Component sizing tokens
| Token | Value |
|---|---|
| `control-h` | 40 px desktop · 48 px touch |
| `row-h` | 48 px (compact 40 px) |
| `nav-item-h` | 44 px |
| `sidebar-w` | 248 px (rail 72 px) |
| `topbar-h` | 56 px |
| `bottom-nav-h` | 64 px |
| `drawer-w` | 480 px (full screen on mobile) |
| `dialog-w` | 440 px |
| `toast-w` | 360 px |

---

## 13. Implementation Notes for the Developer

1. Build the **theme and a `/design` preview route** first (Owner-only, dev environment): buttons, inputs, badges, table, card, dialog, toast, empty state, each in every state. Sign off the look before building modules.
2. Create shared Vue components once and reuse everywhere: `AppButton`, `FormField`, `MoneyInput`, `PhoneInput`, `StatusBadge`, `DataTable` (+ mobile card renderer), `StatCard`, `DateRangePicker`, `ConfirmDialog`, `SideDrawer`, `EmptyState`, `PageHeader`, `Money` (display formatter).
3. Format money in **one** helper (`formatMoney`) so the South-Asian grouping and ৳ fallback font are consistent everywhere.
4. Never hard-code a hex value in a component — tokens only.
5. Ban `rounded-*`, `shadow-*` (except overlays), `bg-gradient-*`, `backdrop-blur`, `dark:` utilities through a lint rule or a CI grep.
6. Permission-based UI hiding is cosmetic; the server enforces access (PRD BR-10).
7. Icons come from one registry; do not mix other icon sets.

---

## 14. Design QA Checklist (sign-off before release)

| # | Check | Pass |
|---|---|---|
| 1 | No rounded corners, gradients, blur or dark-mode styles anywhere (including browser form controls and scrollbars) | ☐ |
| 2 | Every text/background pair meets §3.3 (automated contrast scan + manual spot-check of badges and disabled states) | ☐ |
| 3 | Body text is never lighter than `ink-600`; captions never lighter than `ink-500` | ☐ |
| 4 | ৳ and Bangla text render correctly (fallback font loaded) in tables, invoices and PDFs | ☐ |
| 5 | All amounts right-aligned with tabular numerals; no truncated money values at any breakpoint | ☐ |
| 6 | Every status uses colour + icon + label | ☐ |
| 7 | One primary (solid teal) button per screen | ☐ |
| 8 | POS: a sale can be completed on a 360 px phone in ≤ 3 steps, with the total always visible | ☐ |
| 9 | Keyboard-only run-through of POS, a purchase entry, an expense and a role edit works | ☐ |
| 10 | Empty, loading, error and 403 states exist for every list screen | ☐ |
| 11 | Staff login shows no payroll, P&L, cost or profit anywhere, including dashboard widgets, exports and print views | ☐ |
| 12 | Invoice prints correctly on A4, A5 and the 80 mm layout, and is readable in black-and-white | ☐ |
| 13 | Tested at 360, 390, 768, 1024, 1280, 1440 px; no horizontal page scroll | ☐ |
| 14 | 200 % zoom and reduced-motion settings do not break layouts | ☐ |
| 15 | Every save/delete was confirmed against the database (project Definition of Done), not by the toast | ☐ |
