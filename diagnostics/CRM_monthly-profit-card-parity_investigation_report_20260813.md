# CRM diagnostic — monthly profit card parity

Date: 2026-08-13

## Question

Why can `Місяць на сьогодні` and the current bar in `Чистий прибуток — 6 міс` show different profit for the same month?

## Live evidence (read-only)

The CRM source sheet was inspected through narrow read-only ranges. No CRM or Apps Script data was changed.

| Source | Current-month profit | Inclusion rule |
| --- | ---: | --- |
| Automation `Звіт_Продажів!E6` | 5,090.02 UAH | paid rows, or rows with `Отримано` / `Відправлено`; excludes cancellations and returns |
| CRM row-based monthly aggregation | 4,967.41 UAH | `isActualSaleForCost_`; additionally excludes every `Передзамовлення`, even if paid |

The current live delta is 122.61 UAH. It is exactly the combined profit of two paid `Передзамовлення` sale rows dated 2026-08-07. The row-based calculation omits them; the automation report includes them because their payment status is `Оплачено`.

The screenshot showed a smaller historical delta (approximately 23 UAH). Its top card snapshot was 4,990 UAH, while the current live automation report is 5,090.02 UAH. The 122.61 UAH preorder effect does **not** prove that historical 23 UAH difference. The exact old state cannot be reconstructed from current sheet values alone.

## Code cause

- `apiSummary_()` reads `Поточний місяць` from the automation sheet's prepared `Звіт_Продажів` row.
- `apiMonthlySummary_()` reads CRM sale rows through `_getCrmSalesRows()`.
- `_getCrmSalesRows()` filters with `isActualSaleForCost_()`, which rejects `Передзамовлення` before monthly aggregation.
- The dashboard keeps `monthly` and `ordersAll` in browser memory until the owner uses its hard-refresh action. It has no automatic refresh after a CRM edit. This can retain an old display, but it is only a possible contributor to the historical 23 UAH snapshot, not proven evidence for that exact amount.

Therefore the two dashboard surfaces have different definitions of monthly profit.

## Formula and loss audit (current month, read-only)

The 32 August rows accepted by `isActualSaleForCost_()` were checked directly. Every `Валовий прибуток` and `Чистий прибуток` cell is still a formula; none contains a spreadsheet formula error. Recalculation of each row matched the live formulas exactly:

`net = sales amount - management cost - packaging - acquiring - Nova Pay - marketplace fee - shop-paid delivery`

Three rows are genuinely negative, not silently converted to a positive value:

| SKU | Net result, UAH | Evidence |
| --- | ---: | --- |
| `OP-JP-V7PR-BST` | -1.21 | unit price is below management cost after packaging and acquiring |
| `PKM-JP-OUTL-BST` | -248.75 | 10 units sold for 750.00; FIFO management cost is 984.00 plus costs |
| `OP-JP-OP15-BBX` | -374.28 | sale 3,655.00; FIFO management cost 3,860.45 plus packaging, acquiring, and 109.00 shop-paid delivery |

The sale-cost audit attached to each of these rows records its FIFO lot/fallback source. This is evidence of real loss-making sales under the recorded inputs, not evidence that the net-profit formula itself is broken.

## Integrity-check scope

The reported clean `integrity_check` is valid, but it does not test KPI parity or commercial price-versus-cost safety. It validates CRM schema/formulas, required sheet relationships, and the 3D RRP comparison; it cannot detect two valid aggregations that use different sale-status rules, nor flag a deliberately recorded loss-making sale.

## Authorised local implementation (not deployed)

The owner approved the following narrow implementation; it does not alter FIFO, pricing, or sale-writing logic.

1. `apiMonthlySummary_()` now returns current and previous month-to-date aggregates based on the same cost-confirmed sale rows as the six-month graph.
2. The `Місяць на сьогодні` card reads those aggregates, so its current revenue, profit, and order count have the same inclusion rule as the current graph bar.
3. Paid and unpaid preorders stay outside realised monthly profit. They appear only as a muted, compact subline in the existing `Активні замовл.` card, without adding a new dashboard card.
4. The CRM API active filter and the dashboard active-order filter both retain `Передзамовлення`, so such orders appear in the `Замовлення` tab and overview list.
5. The server cache keys for the changed monthly and active-order payloads are versioned forward, so a new publication cannot reuse an old payload shape or the old preorder filter during its cache TTL.

The confirmed loss-making sales are intentional promotion/coupon outcomes, so no loss guardrail was added in this scope.

## Local verification

- `node crm/apps-script/tests/monthly-profit-preorders.test.mjs` — passed.
- `node dashboard/tests/dashboard-contract.test.mjs` — passed.
- `node crm/apps-script/tests/integrity-check.test.mjs` — passed.
- `node crm/apps-script/tests/catalog-sku-create.test.mjs` — passed.

Live CRM and Apps Script data were not changed during this implementation. Owner publication and dashboard refresh remain required for production proof.
