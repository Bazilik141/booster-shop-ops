# CRM / 3D-P stock, components, payouts, and test cleanup report

Date: 2026-08-13

## Outcome

Prepared an owner-deployable CRM V112 / 3D-P V20 candidate plus the canonical
dashboard changes for the 2026-08-13 QA findings. No live deployment or Sheet
write was performed by Codex.

## Bounded live evidence

- Main CRM Web App: owner-reported V111, published 2026-08-13 14:07 Kyiv.
- Pre-change CRM integrity check: clean, no problems, three 3D RRP comparisons.
- CRM `Продажі` has `MAN-FOP-0005` on rows 268-269 and the new
  `MAN-FOP-0006` on rows 272-274.
- `MAN-FOP-0006` allocation totals were inconsistent by one cent:
  discount 100.01, packaging 79.99, delivery 120.01, yielding the displayed
  1699.99 total although the order form correctly holds 1700.
- Bounded 3D-P stock showed live availability that differed from the CRM
  inventory formula. The sale form was displaying the CRM value and therefore
  did not use the 3D-P inventory source of truth.
- Existing 3D Sales rows had blank `% прибутку Сергію`, so downstream formulas
  could not honestly reproduce the frozen profit split.
- The Payouts sheet already had formula-owned amount/deadline columns but no
  dashboard write workflow for opening a period or recording actual payment.

## Root causes and changes

### 1. 3D stock in the sale form

The Accounting catalog used only the CRM inventory projection. The dashboard
now loads bounded `3dp_skus` data and overlays stock for exact active 3D SKUs.
Until that request succeeds, 3D stock is displayed as unavailable rather than
as a misleading CRM value. The 3D API GET helper retries transient HTTP 404
responses with bounded backoff.

### 2. Sale total and submit latency

The sale writer rounded discount, packaging, and delivery independently on
every row. The replacement allocator assigns the rounding remainder to the last
eligible row, so each distributed total equals the submitted total to the cent.
The manual-sale request no longer performs the full `updateSkuCurrentCost_`
rebuild; that maintenance remains deferred to the existing inventory refresh
path. The dashboard disables the submit button and shows elapsed time while the
request is active.

An exact dry-run/apply repair is provided for `MAN-FOP-0006`:

- `previewManFop0006AllocationRepair()`
- `repairManFop0006Allocations()`

It verifies the three expected rows/SKUs and submitted totals before changing
only the allocation cells. Repeat execution is idempotent.

### 3. Optional component target

`Використання_компонентів` already had target columns N/O. The dashboard now
offers an optional order-line selector:

- blank target: marketing gift, distributed across all sale rows;
- exact target: fulfillment/Mystery Box COGS assigned only to that row and
  excluded from the Marketing projection.

The API validates both row number and SKU against the selected order. Ledger
writes, Marketing projection, and COGS calculation all use the same N/O target.

### 4. 3D product filters and Sales table

The product cost filter now preserves the actual selected state, including
returning to `Усі`. The 3D Sales table has a persistent per-column picker and a
formatted `% прибутку Сергію` column.

The 3D-P candidate freezes the SKU-specific share into Sales column H for new
rows. Existing blank values are repaired by:

- `preview3dpSalesProfitShareBackfill()`
- `setup3dpSalesProfitShareBackfill()`

The setup writes only blank H cells, logs every change, rolls back on audit
failure, and is idempotent.

### 5. Payout workflow

The dashboard can now create a unique `YYYY-MM` payout period and mark an exact
period row as paid on a chosen date. The 3D-P API actions are owner-only,
optimistically locked, audit-logged, rollback-protected, and do not overwrite
formula-owned amount/deadline cells:

- `3dp_payout_create`
- `3dp_payout_mark_paid`

Creating a period does not mark it paid. The owner should use the payment action
only after money is actually transferred.

### 6. Exact `MAN-FOP-0005` cleanup

The owner-approved test-order cleanup is available through:

- `previewManFop0005TestOrderPurge()`
- `purgeManFop0005TestOrder()`

CRM first requires a successful bounded 3D-P preview/apply for the exact order,
then clears only manual cells for matching CRM sales, component usage, fixture
usage, 3D accounting, writeoffs, and direct expenses. Formula columns and audit/
sync journals are preserved. Local CRM cells are snapshotted and restored if a
local write fails. Repeat execution is idempotent.

## Local verification

All 12 local test files passed on 2026-08-13:

- 3D-P API regression, including payout permissions/idempotency, exact test
  purge, profit-share backfill, formula protection, stale-write protection, and
  audit rollback behavior;
- CRM fixture Phase A/B, 3D sync journal, catalog SKU, integrity check, Mystery
  Box cost repair, order components/targets, order API, and qualified clients;
- dashboard syntax/contract and retry-only journal contract.

No live Web App, live Sheet mutation, or browser QA is claimed by these tests.

## Owner deployment and migration order

1. Publish the updated 3D-P `Code.gs` as Web App V20.
2. Publish the updated CRM `Code.gs` as Web App V112.
3. In CRM, run `previewManFop0005TestOrderPurge()`. Confirm it lists only the
   approved test order, then run `purgeManFop0005TestOrder()` and repeat once;
   the repeat must report `already_applied:true`.
4. In 3D-P, run `preview3dpSalesProfitShareBackfill()`, then
   `setup3dpSalesProfitShareBackfill()`, then repeat the setup; the repeat must
   be idempotent.
5. In CRM, run `previewManFop0006AllocationRepair()`, then
   `repairManFop0006Allocations()`, then repeat; the repeat must be idempotent.
6. Run the CRM integrity check and require `problems:[]`, `clean:true`, with no
   new deferred coverage.
7. Reload the canonical dashboard with Ctrl+F5 and complete manual QA below.

## Manual QA

- Sale form: compare every 3D SKU stock label with current `3D-друк → Вироби`;
  do not accept a CRM fallback when 3D-P is unavailable.
- Create one disposable sale and verify the button remains disabled with visible
  elapsed time until completion; the order sum must equal the entered total to
  the cent.
- `MAN-FOP-0006`: confirm the list and editor both display 1700.00 after repair.
- Add one blank-target gift and confirm Marketing increases without changing
  client payment; add one exact-target Mystery Box component and confirm only
  that row's COGS increases while Marketing does not.
- 3D products: choose cost `до 10`, then return to `Усі`; all applicable rows
  must return.
- 3D Sales: hide and restore individual columns; confirm Serhiy profit percent
  is populated for repaired/new rows.
- Payouts: create the current test period only if appropriate. Do not press
  `mark paid` unless a real payment occurred.

## Risk and rollback

Risk: high (CRM, cross-workbook accounting, stock, and payout writes).

- Apps Script rollback: republish CRM V111 and 3D-P V19.
- Sheet rollback: named Google Sheets versions remain the operational rollback
  for migrations. API writes also retain audit records and action-level rollback.
- The exact test purge is intentionally destructive for business rows but
  preserves formulas and journals. Run its preview and verify the exact order
  before apply.

## Live deployment evidence

Owner-reported deployment and migration results on 2026-08-13:

- CRM V112 published at 15:41 Kyiv; 3D-P V20 published at 15:42 Kyiv.
- `MAN-FOP-0005` preview matched 2 Sales, 6 component, 6 fixture, 2 accounting,
  and 5 writeoff rows in CRM, plus one Sales and two stock-adjustment rows in
  3D-P. Apply cleared all 21 CRM and 3 remote rows. Repeat returned zero counts
  and `already_applied:true`.
- 3D Sales profit-share preview matched only row 3, SKU `ACC-3D-DITTO-410`, at
  `0.5`. Apply updated one row; repeat returned zero updates and
  `already_applied:true`.
- `MAN-FOP-0006` repair changed only row 274 from discount/packaging/delivery
  `38.89 / 31.11 / 46.67` to `38.88 / 31.12 / 46.66`. Resulting exact totals
  are 100 / 80 / 120. Repeat returned `already_applied:true`.
- Post-change CRM integrity and dashboard manual QA are not yet claimed.
