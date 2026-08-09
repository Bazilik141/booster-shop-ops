# CRM-006-ORDER — expandable order lines

Date: 2026-08-09

## Scope

Implemented the additive, read-only `order_items` Apps Script action and the
dashboard order-row expansion described in
`handoffs/handoff_DASH-ORDERITEMS_expandable-order-lines_20260809.md`.

The existing `orders` contract, `crmGetOrders_`, 30-second orders cache,
summary/SKU/stock actions, all sheet formulas, and all order write paths were
not changed. CRM-006 Pass 4 (`formula_column_literal`) was not touched.

## Source and live-sheet preflight

- `crm/apps-script/SOURCE_STATE.md`: mirror V97, re-verified from the owner
  export on 2026-08-09 14:52. This implementation is local source only; it is
  not a published Apps Script version.
- Live sheet used for mapping: CRM spreadsheet, `Продажі`, 452 rows, headers in
  rows 1-2.
- `Продажі!G258:G264` uses an `INDEX`/`MATCH` formula against `Товари!A:B`.
  The product name is therefore formula-derived from `Товари`, not from
  `Майстер_Товарів`.

| Returned field | Live header | Column | Notes |
| --- | --- | --- | --- |
| order id | `Номер замовлення / операції` | A | Request filter only; not returned per item. |
| sku | `SKU` | F | Escaped in the dashboard. |
| name | `Назва товару` | G | Formula-derived from `Товари`. |
| qty | `Кількість` | H | Per line. |
| price | `Ціна за одиницю` | I | Per unit. |
| discount | `Знижка` | J | Allocated per line. |
| amount | `Сума продажу` | K | Per line. |
| mgmt_cost_unit | `Управлінська собівартість 1 од.` | M | Explicitly per unit. |
| mgmt_cost_line | `Управлінська собівартість продажу` | O | Per line; returned directly, not reconstructed. |
| packaging | `Пакування` | P | Allocated per line. |
| payment fees | `Еквайринг`, `Нова Пей`, `Комісія маркетплейсу` | Q, R, S | Returned separately and as `payment_fees`. |
| shop_delivery | `Доставка за рахунок магазину` | T | Allocated per line. |
| profit | `Чистий прибуток` | V | Per line. |

The API resolves every mapped field by its live header name through
`apiRecentTable_` and `apiRecentCol_`; it does not embed these column indices.

## Implementation

### Apps Script

- Registered `order_items` in `handleApiAction_`.
- Added `apiOrderItems_(params)`, which accepts an exact `order_id`, reads only
  `Продажі`, selects matching line rows, and returns line metrics plus order
  totals.
- The response contains no customer name or phone fields.
- `order_items` is deliberately absent from `CACHEABLE_ACTIONS`. The existing
  cache-key implementation omits request parameters for generic actions, so
  caching here would risk returning one order's lines for another order.
- No `setValue`, `setValues`, append, delete, or other write operation is in
  the new action.

### Reconciliation

The live `Чистий прибуток` formula deducts management line cost, packaging,
acquiring, Nova Pay, marketplace fee, and shop-funded delivery from the sale
amount. The expandable table therefore exposes the allocated packaging,
delivery, and payment-fee components rather than displaying an unexplained
residual. Discount is shown only where at least one line has a non-zero value;
it is already reflected in `Сума продажу`.

For the preflight multi-SKU evidence order, line totals are 7,400.00 UAH and
3,839.76 UAH profit; the latter rounds to the 3,840 UAH collapsed-order display.

### Dashboard

- Entire summary row is clickable and keyboard accessible (`role="button"`,
  `tabindex="0"`, Enter/Space, visible chevron, focus outline).
- Detail data is fetched only when a row is first expanded, with a spinner
  during loading, an in-place error after a failed request, and a session-only
  cache for collapse/re-expand.
- Detail rows and the summary row escape server-provided strings.
- Detail table has a totals row for amount and profit, and uses green/red profit
  coloring consistently for positive/negative values.
- `dashboard/booster-dashboard.html` remains the one canonical local `file://`
  dashboard file. It has no deployment step.

## Files touched

```text
crm/apps-script/Code.gs
dashboard/booster-dashboard.html
crm/apps-script/tests/order-items.test.mjs
tests/crm-006-order-items.test.mjs
```

## Local verification

```text
Code.gs syntax check through Node stdin: passed
CRM-006-ORDER API tests passed
CRM-006-ORDER dashboard tests passed
CRM integrity-check tests passed
CRM-005 integrity tile tests passed
git diff --check: passed
```

The API test covers:

- `OC-FOP-0312`: 3 items, amount 7,400.00 UAH, profit 3,839.76 UAH;
- `OC-FOP-0313`: one item, amount 160.00 UAH, profit 90.09 UAH;
- `OC-FOP-0310`: negative profit -248.75 UAH, -33.17 percent;
- exact `order_id` filtering, per-unit versus line management cost, fee-aware
  per-line reconciliation, and absence of customer PII;
- route registration, no server cache registration, and read-only source.

The dashboard test covers zero detail requests before expansion, one lazy
request, no request on collapse/re-expand, loading/error rendering, keyboard
guard, dynamic fee/discount columns, escaped name/SKU output, and preservation
of the UI2 integrity-tile ordering rule.

## Deployment and QA boundary

No commit, push, Apps Script publish, Sheet write, or production change was
performed.

This must be released separately from CRM-006 Pass 3 and UI2:

1. Owner creates a new named Apps Script Web App version for this change, for
   example `CRM-006-ORDER — order_items — 2026-08-09`, from the reviewed
   `crm/apps-script/Code.gs` content.
2. Owner refreshes the local Apps Script mirror and its source-state metadata in
   the same session after publishing.
3. Owner opens the canonical local dashboard file with Ctrl+F5 and verifies:
   `OC-FOP-0312` totals, `OC-FOP-0313` single line, `OC-FOP-0310` red negative
   profit, keyboard expand/collapse, and no detail request before a row click.

## Rollback

- Apps Script: restore the preceding named Web App version. The endpoint is
  additive and read-only, so removing it changes no order or sheet data.
- Dashboard: revert only the CRM-006-ORDER portion of the canonical dashboard
  file after preserving the separate UI2 and 3D-P-025 changes.

## Remaining unverified

Local source and unit tests are not proof of a published Apps Script Web App or
of the owner-browser UI. The named-version publish and manual QA above remain
the production acceptance gate.
