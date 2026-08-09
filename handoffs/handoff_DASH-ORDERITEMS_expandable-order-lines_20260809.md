# CRM-006-ORDER — expandable order rows with per-line detail

Date: 2026-08-09 · Executor: **Codex** · Author: Claude (chat)
Roadmap ID: `CRM-006-ORDER`, created 2026-08-09 on owner instruction as a sub-item of `CRM-006`.
Thematically it is a dashboard feature rather than integrity repair; it is tracked there by owner
choice for convenience.

Owner request: click an order in the Замовлення tab and expand it to show, per item — product name,
SKU, quantity, management cost, net profit in UAH and in %.

## 1. Feasibility — read this before planning

**The data exists per line. The API does not expose it.** This is therefore **not** a dashboard-only
change: it needs an Apps Script change and a new published Web App version.

`Продажі` is already one row per line item. `crmGetOrders_` groups rows by order id, aggregates, and
then explicitly discards the detail:

```js
const result = orders.slice(0, cleanLimit).map(function(order) {
  delete order.sort; delete order.rows; return order;
});
```

Column positions read from V97 source (0-based indices as used in `crmGetOrders_`):

| Field | Index | Column | Evidence |
|---|---|---|---|
| Order id | 0 | A | `crmGetOrders_` grouping key |
| SKU | 5 | F | `apiAddSale_` writes the `1..6` block |
| Product name | 6 | G | not written by `apiAddSale_`; passed as `values[6]` beside the SKU in `isMysteryBoxSale_` — most likely formula-derived |
| Quantity | 7 | H | `order.items_count += num_(row[7])`; written by `apiAddSale_` at `getRange(row, 8, 1, 3)` |
| Unit price | 8 | I | same write block |
| Discount (allocated) | 9 | J | same write block — `round2_(discount * weight)` |
| Sale amount | 10 | K | `order.amount += num_(row[10])` |
| PRRO unit cost | 11 | L | `getRange(row, 12, 1, 2)` writes `[[prroUnit, mgmtUnit]]` |
| **Management unit cost** | 12 | **M** | second cell of that same write |
| Packaging (allocated) | 15 | P | `getRange(row, 16).setValue(round2_(packaging * weight))` |
| Shop delivery (allocated) | 19 | T | `getRange(row, 20).setValue(round2_(shopDelivery * weight))` |
| Profit | 21 | V | `order.profit += num_(row[21])` |

**Verify every one of these against the live header row before use.** These are read from code, not
from the sheet, and `AGENTS.md` treats historical constants as evidence to check rather than
permission to assume. `apiRecentSales_` already resolves `Сума продажу`, `Пакування` and
`Доставка за рахунок магазину` by header name — reuse that approach rather than hardcoding indices.

Two things to confirm explicitly:

- **Is column M per unit or per line?** The write path names the variable `mgmtUnit`, and `Закупки`
  uses the header `Управлінська собівартість 1 од.`, so per-unit is the strong expectation — but read
  the `Продажі` header text and confirm. Getting this wrong silently multiplies or divides the whole
  column by quantity.
- **Where does the product name come from?** If `G` is not a formula-derived name and the name is
  instead resolved through `Майстер_Товарів`, **stop and report**: that path is broken by the `P2`
  defect and the feature would ship with blank names.

## 2. Reconciliation — the owner raised this, and he is right

Owner: *"суми можуть не співпадати, бо є ж окремі витрати типу оплата паковання чи доставки."*

The correct position, from the source:

- **Order totals already reconcile.** Packaging and shop delivery are *allocated across the lines at
  write time* (`packaging * weight`, `shopDelivery * weight`), and the per-line profit in column `V`
  is stored net of them. `crmGetOrders_` builds the order profit by summing that same column, so line
  profits sum to the order profit by construction.
- **What does *not* reconcile is the arithmetic inside a line.** With only quantity, management cost
  and profit on screen, `amount − cost × qty` will not equal `profit`, because the allocated
  packaging, delivery and discount are missing from the view. The owner would see numbers that look
  wrong while being right.

**Therefore show the allocated costs.** They are per-line values already, not order-level extras that
need inventing — no new allocation logic, no new arithmetic, just columns that already exist.

## 3. Design

**Add a new read-only action, do not extend `orders`.**

`orders` returns up to 500 orders. Attaching every line to every order inflates a payload that is
mostly never expanded, and changes a contract already consumed by `crmFindOrder_` and by the
`pending` reduction in `apiSummary_`. Both are risk with no upside.

Instead: `order_items` taking `order_id`, returning that order's lines only, fetched lazily on
expand. This mirrors the existing lazy pattern used for the 3D-P ledger.

- Read-only. No writes, ever.
- Register in `handleApiAction_` alongside the others.
- **Caching trap.** `apiDoGetCacheKey_` returns a key without params for every action except
  `sku_list`. Adding `order_items` to `CACHEABLE_ACTIONS` without extending that function would serve
  one order's lines for every order. Either extend the key correctly to include `order_id`, or leave
  the action uncached.
- Per line return: `sku`, `name`, `qty`, `price`, `discount`, `amount`, `mgmt_cost_unit`,
  `mgmt_cost_line`, `packaging`, `shop_delivery`, `profit`, `profit_pct`.
  Compute `profit_pct` as `profit / amount * 100`, the same way the collapsed row does, and return
  `null` rather than `0` when `amount` is `0`.
- **Never return customer phone or name.** They sit in `Продажі` columns `D` and `E` and have no
  business in a line-item response.

## 4. Dashboard

- The whole order row is the expand toggle: `cursor:pointer`, `role="button"`, `tabindex="0"`,
  Enter/Space handled, plus a visible chevron. A row that is clickable without looking clickable will
  not be discovered.
- Expand fetches once and caches in memory for the session; collapsing does not refetch.
- Spinner while loading; the error text in place on failure — never an empty panel.
- Columns: Назва · SKU · К-сть · Собівартість (упр.) · Пакування · Доставка · Сума · Прибуток ₴ · %.
  Discount only if it is non-zero somewhere in the order — an always-zero column is noise.
- **A totals row at the bottom of the panel**, summing amount and profit. That row must equal the
  collapsed order row above it. This is not decoration: it is the owner's own reconciliation check,
  visible without a calculator.
- Reuse the existing profit colouring — green positive, red negative — so `OC-FOP-0310` (`−33.2 %`)
  reads consistently at both levels.
- Follow the existing table styling. Do not introduce a new visual language for one panel.
- Escape every server string with the existing helper. SKUs and names are user-entered.

## 5. Do not touch

- `crmGetOrders_` return shape, its 30 s cache, and `crmOrderMatchesStatus_`.
- `apiSummary_`, `apiSkuList_`, `apiStockAlerts_`.
- Anything in the `CRM-006` Sheets passes. Different file, independent work.
- Any order write path. This feature is read-only end to end.

## 6. Acceptance criteria

- [ ] Column mapping confirmed against the live `Продажі` header row, resolved by header name where
      `apiRecentSales_` already does so, and recorded in the diagnostic.
- [ ] Whether column M is per-unit or per-line is stated explicitly, with the header text quoted.
- [ ] The product-name source is confirmed and is **not** `Майстер_Товарів`.
- [ ] **The panel totals row equals the collapsed order row** — amount and profit, to the kopiyka, on
      a multi-SKU order. `OC-FOP-0312` (3 SKUs, ₴7 400, ₴3 840) is the test case.
- [ ] **Per line, `amount − mgmt_cost_line − packaging − shop_delivery` reconciles to `profit`**, or
      the residual is explained in the diagnostic and named on screen. If a component is still
      missing, find it before shipping rather than hiding the difference.
- [ ] A single-SKU order expands correctly — `OC-FOP-0313`.
- [ ] A negative-profit order displays correctly — `OC-FOP-0310`, `−33.2 %`.
- [ ] No customer personal data in the `order_items` response.
- [ ] If cached, the cache key includes `order_id`, proven by a test.
- [ ] Zero extra requests until a row is actually expanded.
- [ ] Keyboard expand and collapse work.

## 7. Rollback

Two parts, two rollbacks. Apps Script: restore the previous named version — the action is additive
and read-only, so removing it changes nothing operationally. Dashboard: revert the file, noting the
standing coupling with `3D-P-025` and the integrity tile.

## 8. Sequencing

Independent of `CRM-006`, but it **deploys** with an Apps Script publish, so it is sequenced after the
`P2` fix rather than interleaved — one named version per logical change, not one version carrying two
unrelated things.

Order: `CRM-006` pass 3 (`P2`) → confirm the dashboard symptoms clear → then this feature together
with `handoffs/handoff_CRM-005-UI2_tile-position-and-copy_20260809.md`.
