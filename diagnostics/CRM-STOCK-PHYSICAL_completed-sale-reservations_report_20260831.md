# CRM-STOCK-PHYSICAL — completed sales excluded from physical stock

Date: 2026-08-31

## Outcome

The `Склад` dashboard no longer restores completed Outlet Mix sales into
`Фізично`. The API now adds back only quantities still physically reserving
stock: `Нове`, `В обробці`, and `Передзамовлення`.

`Відправлено`, `Отримано`, and a row that is only `Оплачено` are excluded from
the physical reconstruction. FIFO and sales-reporting behavior is unchanged.

The dashboard `Резерв` cell now displays the full current reservation total,
not only its preorder subset.

## Root cause

`apiDecoratePreorderStock_()` used `isStockReservationSale_()` to calculate
`physical_stock`. That shared helper correctly retains completed orders for
FIFO and sales reporting, but it also returned true for `Відправлено`,
`Отримано`, and `Оплачено`. Consequently old completed sales were added back
to the raw warehouse balance.

## Files changed

- `crm/apps-script/Code.gs`
- `crm/apps-script/tests/preorder-cost-and-stock.test.mjs`
- `dashboard/booster-dashboard.html`
- `dashboard/tests/dashboard-contract.test.mjs`

## Local validation

- Apps Script source syntax: passed.
- Targeted preorder/stock regression: passed.
- Full local Apps Script and dashboard suite: 26/26 passed.
- `git diff --check` for the four scoped files: passed.

The regression fixture includes 277 units with status `Отримано`; they do not
enter `reserved_total` or `physical_stock`.

## Deployment and owner QA gate

This is local mirror/dashboard source only. It has not changed the live Web
App or the live dashboard response. The local mirror also contains the separate
unpublished V157 candidate, so **do not paste the whole local `Code.gs` into
the live editor**.

The owner should make only these two V156-matched edits in the live Apps Script
editor, then publish a new Web App version:

1. Add `isPhysicalStockReservationSale_()` immediately after
   `isStockReservationSale_()`.
2. In `apiDecoratePreorderStock_()`, replace
   `isStockReservationSale_(row)` with
   `isPhysicalStockReservationSale_(row)`.

Those anchors were verified against the owner-supplied complete V156 export.

After publication:

3. Refresh the local dashboard file, which already displays the full active
   reservation in `Резерв`.
4. Run the read-only CRM integrity check and retain its bounded result.

Expected owner QA:

5. Confirm `PKM-JP-OUTL-BST` shows
   `Доступно: 0`, `Фізично: 0`, `Очікується: 80`, `Резерв: 0` when there are no
   active Outlet reservations.
