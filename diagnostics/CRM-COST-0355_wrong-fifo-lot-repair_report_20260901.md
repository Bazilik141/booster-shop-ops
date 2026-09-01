# CRM-COST-0355 — wrong FIFO lot repair and fresh-row guard

Date: 2026-09-01

## Outcome

Prepared a temporary, exact one-row repair for `OC-FOP-0355 / PKM-JP-BBLT-BST` and a permanent fresh-sale-row guard in the main CRM Apps Script mirror. Nothing was written to the live workbook or Apps Script project.

## Confirmed live evidence

- `Продажі!316` stores literal cost `2886.34 / 3074.02` for three `PKM-JP-BBLT-BST` units.
- Its audit names `LOT-0073`, but `Закупки!68` proves `LOT-0073` belongs to `PKM-JP-MZERO-BBX` at `2866.88 / 3038.89` per unit.
- The current canonical FIFO calculation for the target is expected to return `217.84 / 230.91` per unit from `LOT-0064`.
- The bad cost timestamp predates the CRM V159 stock repair publication/execution, so the stock-balance repair did not write this sale cost.

## Files

- `crm/apps-script/TEMP_CRM_COST_0355_repair_20260901.gs` — temporary preview/apply wrapper; exact order, SKU, date, quantity, price, bad lot, bad values, and expected FIFO are fail-closed.
- `crm/apps-script/Code.gs` — central fresh-row cleanup for `Продажі!L:M,AD:AF` before any new sale writer runs FIFO.
- `crm/apps-script/tests/crm-cost-0355-repair.test.mjs` — preview, apply, idempotency, exact write scope, and no-scan guard coverage.

## Runtime impact

The permanent guard adds one `RangeList.clearContent()` Sheets service operation per newly inserted order. It performs no additional sheet scan, no FIFO pass, and no `flush()`. Its cost is constant whether the order has one or ten product lines. Live latency has not been benchmarked; based on the operation shape, it is materially smaller than the existing FIFO reads/calculation.

The one-time repair intentionally runs pre/post integrity checks and one FIFO calculation. It may take noticeably longer once, but it does not affect later order imports.

## Owner-run gate

1. Create a fresh workbook copy.
2. Add `TEMP_CRM_COST_0355_repair_20260901.gs` as a separate Apps Script file beside the current `Code.gs`.
3. Run `previewCrmCost0355Repair`. It must report `dry_run:true`, `rows_written:0`, target row `316`, before `2886.34 / 3074.02`, and planned `217.84 / 230.91`.
4. Only if preview matches, run `repairCrmCost0355`. It must report `rows_written:1`, clean pre/post integrity, and after `217.84 / 230.91`.
5. Run `repairCrmCost0355` again. It must report `already_applied:true`, `rows_written:0`.
6. Read back `Продажі!L316:M316,AD316:AF316`, refresh the dashboard, and verify the order profit.
7. Delete only the temporary repair file from the live Apps Script project.
8. The permanent guard is a separate deployment: paste the reviewed complete `Code.gs`, publish a new Web App version, then import one controlled test order and verify its FIFO audit names only lots belonging to that SKU.

## Rollback

The repair snapshots `Продажі!L:M,AD:AF` and restores both ranges if its post-write read-back or integrity check fails. The fresh workbook copy is the external rollback if Apps Script execution is interrupted.

## V2 follow-up after the successful Black Bolt repair

The owner supplied a successful one-row apply transcript (`rows_written:1`, clean pre/post integrity) and reported publishing CRM V160 at 21:50 Kyiv. A fresh bounded read of `Продажі!300:318` then proved the remaining `OC-FOP-0355` state:

- row 315 `PKM-JP-MBRV-BST`: valid `LOT-0076`, six units;
- row 316 `PKM-JP-BBLT-BST`: repaired valid `LOT-0064`, three units; order-component projection is present;
- row 317 `PKM-JP-SVEX-BLR`: invalid `LOT-0119`, which belongs to `OP-JP-OP15-BST`; the audit also claims two lot units for a one-unit sale;
- row 318 `MTG-JP-AFRS-BST`: invalid `LOT-0075`, which belongs to `PKM-JP-MSYM-BST`; the audit claims one lot unit for a two-unit sale.

The two marketing/order-component entries are intentional and are not the defect. Their cost projection must survive the base FIFO repair.

`TEMP_CRM_COST_0355_order_repair_V2_20260901.gs` therefore refreshes all four order lines through `fixSaleCostForRow_(..., forceRecalculate:true)`, brackets that refresh with the existing component reset/reapply helpers, verifies unchanged component-ledger totals, runs pre/post integrity checks, performs exact preview/read-back comparison, and restores all four `L:M,AD:AF` rows on any failure.

## Live V2 result

Owner-run preview and apply completed on 2026-09-01 at 22:09–22:10 Kyiv:

- `rows_written: 4`;
- component totals preserved at `30.18 / 31.99`;
- `PKM-JP-MBRV-BST`: `91.55 / 97.24`, `LOT-0076`;
- `PKM-JP-BBLT-BST`: `220.13 / 233.34`, `LOT-0064`;
- `PKM-JP-SVEX-BLR`: `1532.67 / 1624.63`, `LOT-0098`;
- `MTG-JP-AFRS-BST`: `165.58 / 175.51`, `LOT-0059`;
- integrity before/after clean, zero introduced problems.

The owner refreshed the result and reported that the order is now correct. Live data repair is complete. The temporary V1/V2 Apps Script files should be removed from the live Apps Script project after retaining this diagnostic evidence; the permanent V160 fresh-row guard remains deployed.
