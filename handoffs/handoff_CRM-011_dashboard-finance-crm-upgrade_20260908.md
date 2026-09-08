# Codex Handoff — CRM-011: Dashboard finance, orders, clients, slow stock, roadmap filters

Date: 2026-09-08 | Parent: —
Executor: Codex · model=Sol · effort=xhigh — multi-file work spanning the main-CRM Apps Script
backend and the 651 KB dashboard, touching financial semantics and formula-backed sheet columns.
WP1 and WP6 in isolation would be Terra/medium, but they ship inside the same task and the same
review round; never swap executor mid-round.

## Context

`dashboard/booster-dashboard.html` already works as the operational CRM: overview, stock,
consumables, orders, SKUs, clients, accounting, 3D-P, service tabs. It has no financial analysis
layer, orders cannot be searched or filtered, the Clients tab silently shows only *qualified*
clients, and nothing shows capital frozen in slow-moving stock. This task adds those in six
independently deployable work packages.

---

## Pre-flight — mandatory before any code

1. **Mirror freshness.** Read `crm/apps-script/SOURCE_STATE.md` and confirm the recorded source
   identity still matches `crm/apps-script/Code.gs`. If the mirror is older than the change being
   planned, stop and request a fresh owner export (AGENTS.md § Apps Script mirrors, rule 1).
2. **CRM integrity check.** WP2 adds columns to `Закупки` and `Продажі` — a sheet-structure change
   under OPS-CRMINTEGRITY. Run the read-only dashboard integrity check **before** the change,
   record its bounded output, run it again after, and include both in the diagnostic. Any new
   problem code is a defect of this change, not pre-existing noise.
3. **Never write a literal over a formula column.** `Продажі` K and N–V, and `Витрати` L–M, are
   sheet formulas. Read them; do not overwrite them.
4. **Column additions append, never insert.** Inserting a column mid-sheet shifts every
   letter-based sheet formula and silently breaks profit. See WP2 for the exact rule.

---

## Established facts — do not re-derive, do not contradict

Verified 2026-09-08 against `Code.gs` (V166 mirror) and `plans/crm-financial-model_2026-06-26.md`.
This section exists because an executor who does not know it will rebuild it wrong.

### Sales economics

Sheet formulas in `Продажі` (not in code):

```
K = H×I − J                       revenue, ALREADY net of discount
U = K − N                         gross profit on ПРРО (landed) cost
V = K − O − P − Q − R − S − T     ← the field the dashboard exposes as `profit`
```

`V` already subtracts management COGS (landed × 1.06), packaging, payment fees and shop-paid
delivery. It does **not** subtract marketing and does **not** subtract operating expenses.

Consequences for the P&L: never subtract the discount again (it is inside K); never subtract
packaging, payment fees or shop delivery a second time when the block is derived from `V`.

**The June column letters are stale.** That audit names R = Контроль ФОП and S = Післяплата, but
`apiOrderItems_` currently reads `Нова Пей` and `Комісія маркетплейсу`. Re-verify the live `V`
formula **by header name, not by letter**, and record the current expansion in the diagnostic.

### Operating-expense deduplication already exists in the data

`Витрати` carries derived columns L and M (`Code.gs` ~9309, ARRAYFORMULA):

- **L** = `Так` / `Ні` — "counts as an operating expense"
- **M** = reason — `Розхідник: не в операційці` / `Пряма витрата продажу: не в операційці` /
  `Рахується в операційці`

The encoded rule: a row is NOT operating when column H (consumable type) is filled, OR category
= `Пакування`, OR column E (`Пряма витрата продажу`) = `Так`.

**Column L is the authoritative filter for the P&L "other operating expenses" block.** Read it.
Do not design a parallel deduplication rule.

### Marketing is not an order-level P&L line

Order-level `marketing` in this CRM is exclusively 3D-P gift marketing
(`crm3dpMarketingByOrder_`). Those rows land in `Витрати` carrying the marker
`excluded from operating expenses and order direct-expense recalc` (`Code.gs`:9436). Real ad
spend (Google Ads and similar) is a standalone `Витрати` row and is therefore an **operating**
expense, already selected by column L.

Therefore the P&L has **no marketing line under order-level expenses**. Marketing appears in
operating expenses only. Placing it in both double-counts 3D-P.

### Two sales populations — pick one, and say which

- `_getCrmSalesRowEntries()` — raw, every row. Used by `crmGetOrders_`.
- `_getCrmSalesRows()` — drops **every row of any order** containing an unreconciled preorder
  line. Used by `ltv_report` and the SKU metrics.

**Finance uses the filtered population** (`_getCrmSalesRows`); otherwise profit is fiction for
unreconciled preorders. The unavoidable consequence: Finance revenue for a period will be lower
than the sum of the same period in the Orders tab. That gap must be visible and explained, never
silent — see WP3 acceptance.

### Client identity

`apiCustomerKey_(row)` returns `tel:<digits>` when the phone has ≥ 7 digits, otherwise
`name:<lowercased name>`. `apiCustomerDisplay_` currently masks both.

Known defect of the `name:` fallback: it merges two different people sharing a name, and splits
one person who ordered from two phones. **Do not attempt fuzzy matching.** Expose the weakness
instead — see WP2 `identity_source`.

### Stock

`sku_list` already returns `stock`, `expected`, `incoming_stock`, `incoming_after_preorder`,
`current_cost`, `sold_30d`, `sold_60d`, `margin_pct`, `is_3dp`, and the Склад tab already loads
it. `current_cost` can be `null`.

### JPY rate

`getCurrencyRate_(currency)` reads sheet `Курси` (row 4+, column A = currency, column B = rate)
with a hardcoded `3.5` fallback in two places. The rate is **JPY per 1 UAH**.

The owner set `Курси` JPY to **3.2** on 2026-09-08, derived from the ZenMarket top-up page:
¥7 699 = 2 397 UAH → 3.212. The ZenMarket product page shows 3.329; that is informational and
carries ZenMarket's payment-page markup — it is not what the card is charged. Always use the
top-up figure.

Note the coupling: this rate is also applied at purchase entry (`apiAddPurchase_` and two other
sites) to convert Japanese fees and delivery into UAH, where the result is stored as a literal.
Changing the rate affects **future** landed cost only; already-written rows keep their stored
UAH values and stay historically correct.

---

## Owner decisions locked 2026-09-08

| # | Decision |
|---|---|
| D1 | Customer name and phone are **unmasked** in the dashboard. Owner authorised this explicitly. |
| D2 | Cash-in date: card / prepaid sales = `Дата продажу`; `Післяплата` = order date + 3 days. The second is an approximation and must be labelled as such in the UI. |
| D3 | Real cash-out for inventory is the **ZenMarket balance top-up**, not the lot payment. Lot payments, ZenMarket commissions, Japan delivery and refunds move the ZenMarket balance, not the owner's money. |
| D4 | Historical purchase cash-out converts JPY→UAH at the `Курси` rate, flagged approximate. Where an export shows an actual UAH figure, prefer it; the rate is fallback only. Always keep the JPY figure alongside so the conversion can be corrected later without re-parsing. |
| D5 | Roadmap series prefix is cut at the **last hyphen before the trailing number** (`3D-P-007` → `3D-P`, `MKT-TG-005` → `MKT-TG`, `RD-13` → `RD`), derived from the data at runtime, never hardcoded. |
| D6 | The Overview tab keeps its current behaviour. Navigation wiring only. |
| D7 | The full ZenMarket export does not block WP2. The backend ships the columns and starts writing dates forward; the historical import is a separate owner step. |

---

## Deployment reality — read before planning delivery

The two halves of this task deploy completely differently. This is the most common source of
confusion on CRM work.

- **WP2 is backend** — `crm/apps-script/Code.gs`. That file is a **mirror**, not the running
  system. Delivery = updated mirror + a `SOURCE_STATE.md` entry + one copy-paste-ready owner
  instruction block. The owner pastes into the bound Apps Script project and publishes a new
  version. There is no patch file and no PHP runner. Source is not deployment.
- **WP1 and WP3–WP6 are frontend** — `dashboard/booster-dashboard.html`. There is no deploy step
  at all: the owner opens the repository file over `file://` and presses Ctrl+F5.

The dashboard is **PC-only**. Do not spend scope on mobile or tablet layout, and do not ask the
owner for mobile QA.

---

## Work packages

Six independently deployable packages, in this order. **One work package per change set.** Do
not bundle. Each package must be usable on its own.

### WP1 — Slow stock / frozen capital (frontend, ship first)

Zero backend. Chosen first because it delivers value immediately and validates the approach
before anything risky.

Placement: top of the **Склад** tab, above the main table, as a compact card. Not on Overview.

- Title `Повільний залишок`, subtitle
  `SKU, у яких найбільше грошей заморожено у слабко рухомому залишку`.
- Candidate qualification: physical stock > 0 **and** (`sold_60d = 0` **or** stock coverage
  ≥ 60 days).
- Ranking: `stock_value = physical_stock × current_cost`, descending, top 5.
- Columns: SKU · товар · фізичний залишок · собівартість/од. · грошей у залишку · продано 30д ·
  продано 60д · днів запасу · поточна margin.
- Every row states **why it is listed** — `0 продажів за 60д` or `запасу на N днів`.
- Capital uses **physical** stock only. Never `expected`, `incoming_stock`,
  `incoming_after_preorder` or any projected availability.
- `current_cost === null` renders `—` and the row is excluded from ranking. Never `₴0`.
- Exclusions: reuse existing helpers rather than duplicating lists. `is_3dp` already exists in
  `sku_list`. Mystery Box detection is server-side (`isMysteryBoxSale_`); if no client-side
  signal exists, WP2 adds an `is_mystery` flag to `sku_list` and WP1 consumes it — this is the
  only backend dependency in WP1 and it may ship in WP2 without blocking the rest of WP1.
- Also exclude assembled-on-demand, Outlet and preorder-only SKUs where an unusual inventory
  model is normal business logic and `stock = 0`.

Acceptance: for each of the top 5, `stock value = authoritative physical stock × current cost`
recomputed by hand and matching; no incoming quantity contributes to capital; each row's reason
is displayed.

### WP2 — Backend, single pass (Apps Script)

One Code.gs change, one owner publication. Everything the frontend packages need lands here.

**2.1 — New date columns.**

Append at the first free column **after the current last used column** of each sheet. Never
insert mid-sheet.

- `Закупки` → `Дата створення`, written automatically at insert by `apiAddPurchase_` and by the
  form path at `Code.gs`:125. `apiUpdatePurchase_` currently reads `getRange(3,1,n,18)`;
  appending beyond that is safe.
- `Продажі` → `Дата оплати`, written **once**, at the transition of `Статус оплати` to
  `Оплачено` (`apiUpdateSale_`, and `apiAddSale_` when a sale is created already paid). Never
  overwrite an existing value.
- `_getCrmSalesRowEntries()` reads a fixed width of 32 columns
  (`sales.getRange(3, 1, lastRow - 2, 32)`). Widen it to include the new column. Audit every
  other fixed-width read of `Продажі` and `Закупки` in the same pass and list them in the
  diagnostic.

Both columns are empty for existing rows and that is expected — D2 and D4 cover history.

**2.2 — JPY rate fallback.** Change the hardcoded `3.5` fallback to `3.2` in both places inside
`getCurrencyRate_`. Add a `Дата курсу` note beside the JPY row in `Курси` recording the date the
rate was last verified, so a future reader can tell whether it is stale. Do not add a second
rate source.

**2.3 — `finance_report` action.** One aggregated read-only endpoint. All finance arithmetic is
server-side; the UI renders an already-aggregated report and never pulls the sales ledger into
the browser to `.reduce()` it.

Parameters: `date_from`, `date_to`, `compare_from`, `compare_to` (all `YYYY-MM-DD`).

Response shape — adapt to real CRM architecture, but keep the semantics:

```
period, comparison
summary   revenue, cogs, gross_profit, order_expenses, operating_expenses,
          net_profit, margin_pct, orders, avg_order,
          cash_in, cash_out, net_cashflow
pnl       revenue, cogs, packaging, delivery, payment_fees,
          operating_expenses, writeoffs, net_profit
cashflow  customer_payments, inventory_topups, consumables, marketing,
          operating, other, net, zenmarket_liability
customers new, repeat, unidentified
assets    ua, incoming, jp, total
data_quality
```

Rules that must hold:

- `pnl` has **no marketing key under order-level expenses** (see Established facts).
- `operating_expenses` selects `Витрати` rows by column **L = Так** only.
- An inventory purchase is never a P&L expense. It affects cash flow; it enters the P&L as COGS
  at the moment of sale.
- Write-offs enter the P&L only where a reliable monetary cost valuation exists; otherwise they
  are reported in `data_quality`, not as `0`.
- Every boundary is computed in **Europe/Kyiv**. The API receives explicit `date_from` /
  `date_to`; no UTC rollover may move a near-midnight sale into the wrong day.
- Missing / unavailable / not-computable are **distinct from a real zero** in the response.
  Return `null`, never a fabricated `0`.
- `data_quality` reports at minimum: sales carrying forecast cost, sales with missing COGS,
  unidentified customers, expenses without category, and the count and value of orders excluded
  by the preorder filter.
- Cash-in dating follows D2. Cash-out for inventory follows D3 — top-ups only. The
  outstanding negative ZenMarket balance is reported as `zenmarket_liability`, a liability, never
  a period expense.
- While any cash date is an approximation, the report labels it. The UI must be able to render
  `Обліковий рух коштів`; nothing may be presented as factual Cash Flow while it is derived.
- Cache per period key; `hardRefresh()` clears it.

**2.4 — `orders` enrichment.** Extend the existing action; do not create a duplicate. Do not
change existing field names or semantics.

New fields per order: `client_key`, `customer_name`, `customer_phone`, `client_orders_total`,
`client_is_new`, `identity_source` (`phone` | `name`).

`crmGetOrders_` already reads the whole ledger in one pass, so these aggregate in the same pass.
There is no N+1 problem to solve here.

Per D1 the name and phone are returned unmasked. `identity_source: 'name'` marks a client whose
identity rests on the weak name fallback.

**2.5 — `ltv_report` extension.** The `qualified=true` branch (`apiQualifiedClientsReport_`)
already exists; its criteria are **OR**-joined — `orders > 1` OR `spend_60d > 1500` OR
`margin > 40`. Preserve that behaviour exactly; the owner has not authorised a criteria change.
The default branch is already "all clients" but capped at limit 50 — raise the cap for the
all-clients mode.

Add to every list item: `client_key`, `identity_source`, `first_order_date`, `last_order_date`,
`days_since_last`, `segment`, `avg_interval_days`, `primary_channel`, alongside the existing
`orders`, `orders_30d` / `orders_60d`, `units`, `spend_60d`, `ltv`, `profit`, `margin_pct`.

**2.6 — `client_orders` action.** Parameters `client_key`, optional `limit` (default 10).
Returns that client's recent orders: date, order ID, amount, profit, margin, SKUs. Never called
during `loadClients()` — expand-only, following the existing `order_items` lazy pattern.

**2.7 — Segmentation constants, server-side.** One configuration object, no magic numbers spread
through the renderer:

```
REGULAR_MIN_ORDERS   = 4
DORMANT_DAYS         = 60
LOST_DAYS            = 120
LOW_MARGIN_PCT       = 15
LOW_MARGIN_MIN_LTV   = 5000
```

VIP is not a hardcoded LTV threshold: **top 10% of identified customers by lifetime profit, AND
`orders >= 2`, AND lifetime profit above a floor** — the floor exists because on a small base
"top 10%" degenerates into two or three people and the segment becomes noise. Set the floor
explicitly in the same config object and state the value in the report.

Deterministic priority, applied in this order: Lost → Dormant → Low-margin → VIP → Regular →
Repeat → New. One segment per client. No tag system in this version.

**2.8 — `sku_list`: `is_mystery` flag** if WP1 has no client-side signal.

Acceptance for WP2: server-side helpers/tests added; integrity check clean before and after;
`SOURCE_STATE.md` updated with the pull date and the published version; the owner instruction
block is complete and copy-paste ready.

### WP3 — Finance tab (frontend)

Navigation: a new sidebar item `💰 Фінанси`, placed after `Клієнти`. Finance is analysis;
`Облік` is data entry; they stay separate.

Technical integration: `page-finance`, `loadFinance()`, `financeState`, loader entry
`finance: loadFinance`, and a finance cache/state reset inside `hardRefresh()`. Finance is
**lazy-loaded on first open** and must not be fetched during `loadOverview()`. `loadOverview()`
logic is not modified (D6).

**Period selector — inside Finance only.** It must not affect Overview, Orders, Clients or Stock.

Presets: `7 днів`, `30 днів`, `Цей місяць`, `Минулий місяць`, `90 днів`, `Свій період` (date
from / date to).

Compare toggle `Порівняти з попереднім періодом`:

| Preset | Current | Previous |
|---|---|---|
| 7 / 30 / 90 днів | last N calendar days | the N calendar days immediately before |
| Цей місяць | MTD | same day-span of the previous month (1–8 Sep vs 1–8 Aug, **not** all of August) |
| Минулий місяць | full previous calendar month | the month before it |
| Свій період | the chosen range | an equal-length range immediately before |

All boundaries in Europe/Kyiv.

**KPI row — five tiles**, not six: Виручка · Чистий прибуток · Чиста маржа · Замовлень ·
Середній чек. Cash Flow gets the header of its own block instead; six tiles overflow the current
card width, and cash flow is derived rather than measured.

With compare on, each metric shows an absolute delta and a percentage delta. When previous = 0,
show the absolute delta and leave the percentage as `—`. Never render `∞%`.

**P&L block.** A two-column table: muted label left, right-aligned number right; subtotal rows
separated by a top border, not by a new component. No waterfall chart, no BI mosaic. Structure:

```
Виручка
− Собівартість
= Валовий прибуток
− Пакування
− Доставка магазину
− Платіжні комісії
− Інші операційні витрати
− Списання            (only with a reliable cost valuation)
= Чистий прибуток
  Маржа %
```

**Cash Flow block**, stacked **below** P&L, never beside it — side-by-side invites the reader to
add two different kinds of money. Inflow: customer payments. Outflow: ZenMarket top-ups,
consumables, marketing, operating expenses, other real cash expenses. Result: net cash flow.
Below the result, separately: inventory capital (UA, ordered, in transit, JP, total cost of
assets) and the ZenMarket liability.

`overview_assets` logic or its backend helper may be reused, but Finance must not depend on
Overview's DOM or state.

While cash dating is approximate, the block is titled `Обліковий рух коштів` and carries a note
naming the approximations in force (D2, D4).

**New vs repeat clients block.** Table: Замовлень / Виручка / Середній чек / Маржа × Нові /
Повторні.

Classification is at **order level, not lifetime state**. An order is New when the client had no
prior identified order at that moment, Repeat when they had at least one. A client whose first
order is 3 Sep and second is 7 Sep contributes one New and one Repeat to September.

Orders that cannot be tied to a client are **not** counted as New. Show a small note —
`X замовлень не включено в New/Repeat через відсутність стабільного client identity` — while the
main P&L still includes those orders.

**Error and partial data.** Finance never renders a fabricated `0`. Distinguish real zero, null,
unavailable and not-computable; show `—` or `Немає даних`. When `data_quality` is non-empty,
show a compact warning such as `⚠ 3 продажі містять прогнозну собівартість`. Do not build a
separate integrity subsystem.

**Preorder exclusion must be visible.** Under the P&L, state the excluded orders and their value,
e.g. `2 замовлення на ₴14 300 не входять: незакрите передзамовлення`. Without this the Orders
tab and Finance disagree for no visible reason.

Acceptance: each preset returns correct boundaries; custom range works; comparison works; Kyiv
timezone respected; for one test period, 3–5 orders reconciled by hand proving
`Revenue − COGS − included expenses = Net Profit` with no double subtraction; cash-in and
cash-out sources are shown and approximations admitted; the new/repeat case above classifies
correctly; Overview behaviour unchanged.

### WP4 — Orders toolbar, client column, colspan refactor (frontend)

Existing modes (Active + 60 days, Active, Completed 60 days, date sort) stay. Add a toolbar
above or beside them.

**Search** — one input, case-insensitive, matching at minimum order ID, customer, phone, TTN and
SKU. Phone matching normalises spaces, `+`, `(`, `)` and `-` on both sides.

**Filters**, combining with AND: Канал (All + actual source values) · Статус замовлення (All +
actual values) · Оплата (All / Оплачено / Не оплачено / other actual values) · Тип клієнта (All /
New / Repeat) · Передзамовлення (All / Так / Ні) · Прибутковість (All / Збиткові / Маржа < 15%).
Plus `Скинути фільтри` and a visible `Знайдено: N`.

`Prom + Repeat + margin < 15%` must return only the intersection.

**Filter architecture.** Never mutate the canonical fetched arrays destructively.
`orderItemsState.activeRows` and `orderItemsState.completedRows` stay the source; a
`getFilteredOrders_()` returns a view. Expanded state and the order-item cache survive filtering,
and no API request fires on a filter click.

**Client column.** One cell: name plus badge — `Іван Петренко ×4`, or `Іван Петренко NEW` for a
first lifetime order. No CRM segment badges here; the row answers only "who is this and did they
buy before". Where `identity_source = 'name'`, mark the cell discreetly so a merged-identity
count is not read as fact.

The table is already wide at 11 columns and is PC-only. Flag to the owner in the report if a
12th column degrades the layout, and name the existing column you would merge or drop — do not
decide that unilaterally.

**Colspan refactor.** `colspan="11"` is currently hardcoded in six places:
`booster-dashboard.html` lines 2266, 2267, 2270, 2282, 2345, 2347, 2351. Introduce
`ORDER_SUMMARY_COLUMN_COUNT` and use it for the expanded loading row, expanded error row,
expanded item row, regular active divider, preorder divider and completed divider. The
order-items internal table has its own column count and must **not** depend on the summary count.

Acceptance: every search field and every filter works individually and combined; reset works;
result count correct; an expanded order still works after filtering; order items are not
re-fetched unnecessarily; table geometry intact.

### WP5 — Clients rework (frontend)

**Two modes**, toolbar `Усі клієнти | Важливі`.

`Важливі` preserves the established backend `qualified` semantics exactly (WP2.5) — do not change
the criterion silently. Synchronise the current subtitle with what the backend actually
qualifies, since the OR-joined criteria are looser than the label implies.

`Усі клієнти` shows every identified client for whom a customer aggregate can be built — no
`spend_60d`, margin or repeat restriction.

**KPI cards** above the table, computed over the **whole customer base** regardless of mode, so
the CRM stays readable: Усього клієнтів · Нових за 30 днів · Повторних за 30 днів · Repeat rate ·
Середній LTV.

`Repeat rate` is fixed as `clients with ≥ 2 lifetime orders / all identified clients`, lifetime,
stated in a tooltip. No ambiguous percentage.

**New columns**: Перша покупка (`first_order_date`), Остання покупка (`last_order_date`), Днів
без покупки (`days_since_last`), Сегмент. All four sortable.

**Segments** (WP2.7 taxonomy): Новий (1 order) · Повторний (2–3) · Постійний (≥ 4) · VIP ·
Засинає · Втрачений · Низькомаржинальний.

Visually: a **neutral badge for every segment**, coloured only for `Втрачений` and
`Низькомаржинальний` — the two that demand action. Seven coloured badges turn the table into
confetti.

**Distribution QA.** After implementation, report the real segment distribution. If one category
takes 80–90% of the base, investigate thresholds and logic rather than declaring success.

**Client detail.** Keep the current metrics (середній чек, profit/order, margin, frequency,
units) and extend into a real client card: lifetime orders, LTV, lifetime profit, margin, average
check, average profit per order, first purchase, last purchase, days inactive, segment,
frequency, most frequent channel.

Average interval between orders is computed from **real order dates** for clients with ≥ 2
orders. The current `orders_60d / 2` (line ~2505) is not a long-term frequency metric and is
replaced.

Order history: last 5–10 orders — дата, order ID, сума, profit, margin, SKU — loaded lazily on
expand via `client_orders`, keyed by `client_key`, cached per client and cleared by
`hardRefresh()`. Clicking an order ID to jump into Orders with that ID pre-searched is optional.

`Найчастіше купує`: top 3 SKUs where SKU history is reliable. Do not invent a category mapping
where no authoritative category field exists; `Top SKU` beats a heuristic.

**Colspan.** The client detail renderer hardcodes `colspan="10"` (line 2514). Introduce
`CLIENT_TABLE_COLUMN_COUNT` or derive the colspan from the schema.

Acceptance: All shows a wider base than Important; Important preserves current qualified
behaviour; KPI cards stay global while the mode changes only the table; first/last order and
days-since-last correct; segments deterministic; sorting works; expand works; history is lazy and
matches the expanded client's key; average interval derived from real dates.

### WP6 — Roadmap tab filters (frontend)

`ROADMAP_TASKS` in `booster-dashboard.html` already holds `id` and `status` and is already in
memory. No backend.

Add filtering by **series prefix** and by **stage**, combining with AND.

Series list is derived from the data at runtime per D5 — cut at the last hyphen before the
trailing number, so `RD-13` → `RD`, `CRM-001` → `CRM`, `MKT-TG-005` → `MKT-TG`,
`3D-P-007` → `3D-P`. The dropdown offers only the series actually present today. Never hardcode
the list: a hardcoded list means every new series needs a code change, and it will be forgotten.

Handle IDs that do not end in a number without throwing — bucket them under their full prefix
rather than dropping them.

Stage filter uses the statuses actually present in `ROADMAP_TASKS` (`active`, `todo`, `done`).

Acceptance: every series present in the data appears in the dropdown; a new ID in a new series
appears without a code change; prefix + stage combine with AND; a `Скинути` control restores the
default view; the existing roadmap rendering, blocked-by resolution and priority sort are
unchanged.

---

## What NOT to touch

- **Overview** — no new KPI, no average check, no extra margin, no profit/order, no new/repeat,
  no bottom SKU, no global period selector, no "what changed" narrative. Only the navigation
  wiring for the new tab, without altering `loadOverview()` logic.
- **The existing order status model** — no age/time-in-status, no refund workflow, no new returns
  system, no status changes.
- **`order_items` cache and render flow** — must keep working exactly as now.
- **Existing `orders` field names and semantics** — extend, never rename or redefine.
- **The `qualified` criterion** — preserved as-is; a change needs separate owner authorisation.
- **Visual language** — reuse `.card`, `.section`, `.badge`, `.pill`, `.tbl-wrap`, existing button
  styles, existing colours, the current dark theme. New screens must look native. Check the
  dashboard's own convention before colouring comparison deltas.
- **Secrets** — `.env.review`, `scripts/.env`, `client_secret.json` are never read, printed or
  referenced. Apps Script secrets live in Script Properties and must never enter the mirror.

---

## Regression checklist (owner runs, desktop)

1. Overview · 2. Active orders · 3. Completed 60d · 4. Preorders · 5. Expand order ·
6. Order item totals · 7. Stock · 8. SKU list · 9. Clients · 10. Accounting sale form ·
11. Purchase form · 12. Expense form · 13. Consumables · 14. 3D-P · 15. Roadmap ·
16. Hard refresh.

---

## Risks

- **Sheet-structure change (WP2.1)** — appending columns to `Закупки` and `Продажі` touches
  formula-backed sheets. Integrity check before and after is mandatory; a column inserted rather
  than appended silently corrupts every profit figure.
- **Fixed-width sheet reads** — `_getCrmSalesRowEntries()` reads exactly 32 columns. Any missed
  fixed-width read will return stale or truncated rows without erroring.
- **Rate change coupling** — `Курси` JPY feeds purchase landed cost, not only the finance
  conversion. Future lots cost more in UAH after 3.5 → 3.2; historical rows are unaffected.
- **Double counting** — the three specific traps are discount (already in K), order-level
  expenses (already in V), and marketing (3D-P only, already excluded from operating).
- **Privacy change (D1)** — unmasking is an owner decision already taken; do not extend it beyond
  the dashboard, and do not export customer data anywhere else.
- Not a risky zone under AGENTS.md: no checkout, payment, schema or feed code is touched, so no
  checkout smoke test is required.

---

## Deliverables to the owner

1. **Data audit** — the live `V` formula as verified today, sources used for P&L and cash flow,
   how double counting is avoided, how client identity is determined.
2. **Changes** — frontend functions, API actions, backend helpers, modified data contracts.
3. **Assumptions** — payment date, purchase creation date, expense semantics, unidentified
   clients, the ZenMarket top-up model.
4. **QA evidence** — what was actually tested, including both integrity-check outputs and the
   segment distribution.
5. **Known limitations** — approximations stated plainly, not hidden.
6. **Owner instruction block for WP2** — complete, copy-paste ready: what to paste, where, what
   to publish, what to record in `SOURCE_STATE.md`.
