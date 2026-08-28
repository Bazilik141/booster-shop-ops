# Codex Report — CRM-PREORDER: mixed orders, cost forecast, and stock deficit

Date: 2026-08-27

## Scope

Implemented the owner-approved variant 2 locally in the main CRM Apps Script mirror and the
canonical dashboard. No live Google Sheet, Apps Script deployment, Web App publication, roadmap
status, commit, or push was performed.

## Files touched

```text
crm/apps-script/Code.gs
crm/apps-script/SOURCE_STATE.md
crm/apps-script/tests/preorder-cost-and-stock.test.mjs
crm/apps-script/tests/inventory-migration.test.mjs
crm/apps-script/tests/order-items.test.mjs
crm/apps-script/tests/purchase-batch-limit.test.mjs
crm/apps-script/tests/sku-current-cost-menu.test.mjs
dashboard/booster-dashboard.html
dashboard/tests/dashboard-contract.test.mjs
diagnostics/CRM-PREORDER_mixed-orders-cost-and-deficit_report_20260827.md
```

## Implemented behaviour

- One mixed order remains one parcel and uses the order-wide `Передзамовлення` status.
- Preorder cost is frozen per line in this order: landed FIFO; active incoming lots with statuses
  `Виграно`, `Замовлено`, `В дорозі`, `На складі в Японії`; dynamic max-buy price; 75% of RRP.
- The quantity supplied by each source is included in the cost audit. The 75% RRP fallback is a
  numeric unit cost and is multiplied by the uncovered quantity.
- A landed-lot update reconciles affected preorder SKUs. Changing an order from preorder to a
  live status, including `Відправлено`, repeats FIFO reconciliation.
- The owner-visible CRM menu action `Заповнити собівартість передзамовлень` preflights and fills
  only active preorder rows without an existing frozen preorder method. This covers orders that
  already existed before deployment and is idempotent on repeat.
- If actual FIFO is still insufficient, the frozen forecast remains and the dashboard warns with
  the uncovered quantity. Forecast rows remain excluded from actual sales/monthly reporting.
- Dashboard accounting has separate ordinary-order and preorder tabs. Forecast costs are labelled.
- Stock output exposes numeric `physical_stock`, `reserved_total`, `regular_reserved`,
  `preorder_reserved`, `stock_raw`, and `preorder_deficit`. Displayed ordinary availability is
  `max(0, stock_raw)` and never appears negative.

## Local validation

```text
Node VM syntax: Code.gs OK; dashboard inline script OK
CRM Apps Script tests: 22/22 passed
Dashboard tests: 2/2 passed
```

Regression coverage includes FIFO cost repair, inventory migration, Mystery Box costs, OpenCart
identity/quantity sync, order components, current-cost refresh, monthly preorder exclusion, recent
purchases, and the new mixed preorder/75%-RRP/deficit/order-kind scenarios.

## Live gate and rollback

The repository mirror is not a deployment target. Before live use, the owner must take a fresh CRM
workbook copy, record the bounded CRM integrity result, paste the reviewed `Code.gs`, publish a new
Web App version, refresh the local dashboard, repeat the same integrity check, and run bounded QA.
Rollback is the owner-created pre-change workbook copy plus republishing the prior Web App version.

## Post-deploy QA checklist

- [ ] Create a two-SKU preorder: one SKU covered by landed stock, one covered by an incoming lot.
- [ ] Run `Booster CRM → Заповнити собівартість передзамовлень` once for pre-deployment orders; repeat returns zero newly priced rows.
- [ ] Confirm the order appears only in `Передзамовлення` and both lines show their expected cost source.
- [ ] Confirm ordinary availability never shows a negative number; physical, preorder reserve, and deficit reconcile.
- [ ] Mark the incoming lot `На складі UA` and confirm its preorder line changes from forecast to FIFO.
- [ ] Change the full order to `Відправлено` and confirm the repeated FIFO check succeeds.
- [ ] Confirm actual monthly totals exclude the order before final FIFO reconciliation and include it afterwards.
- [ ] Confirm an uncovered test SKU with no max-buy price uses exactly 75% of RRP times quantity.

## Side effects / risks

- CRM, order status, stock, and FIFO are risky zones; live proof is still absent.
- The current mirror already contains a separate pending post-V148 3D catalogue rename candidate.
  Deployment review must treat the whole mirror as a combined unpublished candidate, not as only
  this preorder change.
- Full-sheet exports were not used. Local fixtures and bounded code paths were sufficient.
