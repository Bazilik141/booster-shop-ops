# CRM unified SKU creation and dashboard performance — local implementation report

Date: 2026-08-12

Executor: Codex · model=Sol · effort=xhigh

## Outcome

A local, not-deployed candidate now provides one guarded CRM SKU-creation action used by both the
general owner form and the 3D product form. The same candidate removes the main proven causes of
the intermittent 3D dashboard failure and one duplicated expensive CRM summary calculation.

## Grounded live evidence

- Canonical UI source: `dashboard/booster-dashboard.html`.
- 3D workbook `Номенклатура!A1:Y4`: `BR-CHARM-100` exists at row 4, active, with actual RRP 25.
- Main CRM searches found no `BR-CHARM-100` in `Товари!A1:O220` or `РРЦ!A1:H930`.
- Automation search found no `BR-CHARM-100` in `Майстер_Товарів!A1:Z1000`.
- `ACC-3D-DITTO-410` proves the intended projection: CRM `Товари` row 75 and `РРЦ` row 75 feed
  `Source_CRM_Products`, then formula-driven `Майстер_Товарів`.
- Live cell metadata confirms `Товари!J`, `РРЦ!A:D`, `РРЦ!H`, and `Майстер_Товарів` are formula
  surfaces. They must not receive literal catalogue writes.

## Root causes

1. `saveThreeDpProduct()` appended only to the 3D-P `Номенклатура` table. It never called CRM.
2. `reloadThreeDpData()` issued eight independent Apps Script GETs through one `Promise.all`.
   One transient 404 or non-JSON response rejected the whole load and the UI mislabeled the state
   as “3D-P not connected”.
3. CRM overview starts seven concurrent API requests. `summary` then called the full `apiSkuList_`
   internally while the same page independently requested `sku_list`, duplicating sales, stock,
   RRP and catalogue work.

## Local changes

### CRM Apps Script

- Added read-only `catalog_options`.
- Added owner-token POST action `add_sku`.
- `add_sku` preflights SKU, required catalogue fields, RRP, available rows, formula anchors and
  option-list capacity before writes.
- Writes only manual product fields plus the required short-name formula; it leaves `Товари!J`,
  `РРЦ!A:D`, `РРЦ!H`, and all `Майстер_Товарів` cells formula-driven.
- Supports idempotent retry and rejects an existing SKU with conflicting CRM fields or RRP.
- Clears its own product, RRP and newly added option cells if post-write verification fails.
- Replaced the full `apiSkuList_` call inside `summary` with a narrow active-SKU/RRP snapshot.

### 3D-P Apps Script

- Added owner/authorized read action `3dp_bootstrap` that returns overview, SKU, sales, marketing,
  payouts, fixtures, settings and analytics data in one response.

### Canonical dashboard

- The 3D page now uses one bootstrap request instead of eight failure-coupled requests.
- Read-only 3D GET calls retry once after a short delay for a transient 404/429/5xx/network or
  non-JSON response. POST writes are deliberately not auto-retried.
- Connection errors no longer claim that credentials are missing when the configured API fails.
- Added an owner “New SKU in main CRM” form inside the existing `Товари` page.
- New 3D product creation now also calls the same CRM `add_sku` action.
- Existing 3D products have an idempotent “Check / add to CRM” action for repairing older gaps such
  as `BR-CHARM-100`.
- Cross-API partial success is reported honestly: if 3D succeeds and CRM fails, the UI names the
  completed side and offers the safe CRM retry path.

## Verification

- `3d-print/apps-script-3dp-api/tests/api.test.mjs`: pass, including `3dp_bootstrap`.
- `crm/apps-script/tests/catalog-sku-create.test.mjs`: pass, including create, formula preservation,
  idempotent retry, conflict rejection and missing-formula stop.
- `dashboard/tests/dashboard-contract.test.mjs`: pass, including inline JavaScript parse and the
  new transport/partial-result contracts.
- Existing integrity, order-item, Phase A, fixture-usage and journal regression suites: pass.
- Headless Chrome UI smoke at 1440×900, 1024×768 and 768×720: form visible and focusable; long
  name/note values did not cause horizontal overflow.

## Not proven / deployment gates

- No live Sheet write was performed in this implementation session.
- CRM V103 and 3D-P V11 do not contain this candidate.
- A fresh pre-deploy `integrity_check` is required.
- Deployment order must be 3D-P API first, then CRM API, then reload the local dashboard.
- Live QA should first use the idempotent CRM-sync button on existing `BR-CHARM-100`, then verify
  `Товари`, `РРЦ`, `Майстер_Товарів`, `sku_list`, and a post-change clean integrity result.
- OpenCart product-page creation is not included. The new flow creates the internal operational SKU
  and preserves an optional storefront URL; publishing product content remains a separate workflow.

## Rollback

- Re-publish the prior Apps Script deployments (CRM V103 and 3D-P V11) if runtime QA fails.
- Revert only the scoped local changes listed above for the dashboard rollback.
- The CRM writer has no delete path. Any live test SKU must be a deliberate real SKU or use a
  separately approved cleanup procedure; do not create disposable live catalogue rows.
