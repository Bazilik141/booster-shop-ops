# CRM post-V152 — overview loading and OC-FOP-0336 repair

Date: 2026-08-28

## Outcome

Prepared a post-V152 correction for three connected defects. The owner later reported publishing it
as CRM V153 on 2026-08-28 at 09:46 Kyiv, confirmed the dashboard is coherent, repaired
`OC-FOP-0336`, and deleted the temporary live Apps Script file. This report records owner-reported
runtime results; no fresh V153 export or independent row read-back was supplied.

1. The dashboard now requests one critical overview payload and only then one secondary payload,
   instead of launching seven competing `doGet` executions at once.
2. Monthly revenue/profit excludes the whole mixed order while any line still has an unreconciled
   preorder cost. It no longer counts the in-stock line of a parcel that still waits for another SKU.
3. OpenCart `Очікування товару` now maps to CRM `Передзамовлення`; both legacy Abyss Eye SKUs are
   normalized to `ABYE`. Separate guarded runners repair the current OpenCart products and
   `OC-FOP-0336` without hand-writing FIFO cost values.

## Evidence and root cause

- Owner-reported deployment: CRM V152, 2026-08-28 08:37 Kyiv.
- Owner screenshot: seven `doGet` executions began at 08:44:03–08:44:04. Completed calls took
  2.734–13.960 s; another was still running at 21.963 s. The integrity request completed clean in
  26.889 s. `initializePreorderCostsMenu` took 56.393 s.
- Dashboard source started `summary`, active orders, all 500 orders, `channel_stats`,
  `monthly_summary`, `sku_list`, and `stock_alerts` simultaneously. Separate Apps Script executions
  cannot share the in-memory Sales read.
- The 500-order response was used only to filter active orders again for the overview preview.
- Actual reporting filtered preorder rows independently. In a mixed parcel that allowed an in-stock
  line to enter the month while another line remained forecasted.
- `mapOpenCartOrderStatus_()` recognized text containing `перед`/`pre`, but not the actual status
  `Очікування товару`, so the order silently became `Нове` and skipped preorder initialization.
- The 2026-08-24 OpenCart backup confirms product 93/94 use the wrong `product.model` values and
  product-code rows 979/980 repeat those values. No canonical `PKM-JP-ABYE-BST/BBX` collision was
  present in that backup.

## Changed files

- `crm/apps-script/Code.gs`
  - staged `overview_bootstrap` / `overview_secondary` actions;
  - shared the bounded Sales read with order aggregation;
  - order-level unreconciled-preorder accounting gate and fresh monthly cache key;
  - `Очікування товару` mapping plus both `ABYSS` → `ABYE` import aliases;
  - one shared max-buy/RRP lookup during bulk preorder initialization.
- `dashboard/booster-dashboard.html`
  - critical payload first, secondary payload after it; no seven-request fan-out or overview 500-order fetch.
- Temporary `CRM-OC-FOP-0336-ABYE-REPAIR-ONCE_20260828.gs`
  - changed only Sales SKU/status inputs, then invoked the established preorder/FIFO and stock-formula
    paths; removed from the live project and local workspace after the owner-reported successful run.
- A guarded OpenCart DB runner was prepared and linted for the four exact product/product-code rows,
  then removed locally after the owner confirmed the same articles had already been corrected manually.
  It was never uploaded or executed.

## Local verification

- `Code.gs` parse: passed.
- PHP lint: passed.
- 23/23 CRM Apps Script test files: passed.
- 2/2 dashboard test files: passed.
- `git diff --check`: passed for the scoped files.
- V153 publication, dashboard QA, and the order repair are owner-reported rather than independently
  reproduced in this workspace. The OpenCart PHP runner was not executed.

## Owner-reported completion

- CRM Web App V153 published: 2026-08-28 09:46 Kyiv.
- Dashboard: coherent.
- `OC-FOP-0336`: repaired; temporary live script deleted.
- OpenCart: the owner manually changed both articles before uploading any runner. The PHP runner is
  therefore obsolete for this incident and must not be uploaded merely to repeat the same change.

## Owner gates and acceptance

Closed by owner report: V153 publication, coherent dashboard, repaired order, and temporary-script
cleanup. No OpenCart runner is required after the manual article correction. A future live export is
still needed only if byte-for-byte V153 mirror proof becomes necessary.
