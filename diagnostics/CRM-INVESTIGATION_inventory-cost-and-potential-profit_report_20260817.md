# Codex Report — CRM-INVESTIGATION: inventory cost and potential-profit reconciliation

Date: 2026-08-17

## Scope

Read-only investigation of the live CRM workbook and the local dashboard/API source path, followed by local repair preparation after the owner confirmed the intended meaning of `Замовлено`. The owner later reported publication of CRM V129, but the functional `Очікується` formula remains unverified and has not yet changed in the live workbook.

The calculation policy used for the reconciliation is the live `Налаштування!B8` rule: sales before `2026-04-01` do not reduce the current warehouse balance. Per owner clarification on 2026-08-17, potential profit intentionally applies an additional `1.05` factor to management cost as a realization-cost reserve. It applies only to potential warehouse/asset profit, never to completed-sale profit.

## Evidence

| Surface | Warehouse qty | Warehouse cost | Potential profit |
| --- | ---: | ---: | ---: |
| `Склад` now | 419 | UAH 71,994.69 | UAH 41,775.31 |
| Local `apiSummary_()` replay on current live rows | 373 | UAH 74,560.55 | UAH 26,911.43 |
| Policy-consistent target after the defects below are fixed | 404 | UAH 77,628.03 | UAH 29,620.57 |

The same policy-consistent target for all assets is **UAH 113,429.27 cost** and **UAH 60,119.28 potential profit**. The local `apiSummary_()` replay gives UAH 110,361.79 and UAH 57,410.13 respectively.

`apiSummary_()` is replayed from the repository mirror against live workbook rows. The workbook is live evidence; the currently published Web App V128 was owner-reported but not byte-compared with the mirror, so these dashboard numbers still require one authenticated `summary` response after the source is refreshed.

## Findings

1. **The API path consumes historical sales that the live warehouse rule deliberately excludes.**
   - `Налаштування!B8` says sales before 2026-04-01 do not reduce stock.
   - `Склад` implements that cutoff in the `Продано` formula.
   - `getSoldQtyBySkuForLotStatuses_()` and therefore `apiSummary_()` have no date cutoff.
   - 31 relevant units are before the cutoff: 15 `PKM-JP-MZERO-BST` and 16 `PKM-JP-INFX-BST`.

2. **`Склад` omits six current write-off rows.**
   - Its write-off formulas stop at `Списання!F197`, while the live IDs continue through row 203.
   - Omitted rows `WRT-0196`…`WRT-0201` total 15 units.
   - The static purchase and sales ranges are also bounded (`Закупки` to row 290 and `Продажі` to row 433). They do not yet omit a filled source row, but are the same capacity defect waiting to recur.
   - The 31 historical sales plus the 15 omitted write-offs explain the exact 46-unit discrepancy: `404 = 419 - 15 = 373 + 31`.

3. **`PKM-JP-ABYE-BBX` has stock but no stored cost in `Склад`.**
   - `Склад!A50` shows 2 units and UAH 9,600 potential profit, while `I50:J50` are blank and `K50:L50` are zero.
   - Live `Закупки!` row 89 (`LOT-0094`) holds the same 2 units at UAH 3,846.55 management cost each, UAH 7,693.11 total.
   - This makes the CRM stock tile understate cost by UAH 7,693.11 and overstate this SKU's potential profit by exactly UAH 7,693.11.

4. **Potential-profit surfaces intentionally use different meanings and must be labelled accordingly.**
   - Per owner decision, `apiSummary_()` correctly uses `(RRP - management unit cost * 1.05) * remaining FIFO quantity` for potential warehouse and asset profit.
   - `Склад` uses the unadjusted `potential revenue - management stock value`, so it is not a like-for-like comparator for the dashboard potential-profit cards.
   - The `1.05` multiplier is not present in completed-sale profit: the sales formula subtracts the recorded management sale cost and actual fulfilment/fee columns only.
   - On the policy-consistent remaining stock, the intentional reserve is UAH 3,881.40; for all assets it is UAH 5,671.46.

5. **`Замовлено` is counted as an asset but is invisible in `Очікується / Японія`.**
   - The current live purchase ledger has 13 `Замовлено` lots: 190 units, UAH 17,771.83 management cost, and UAH 19,289.58 adjusted potential profit.
   - `apiSummary_()` includes `Замовлено` in asset cost and adjusted asset potential profit; it deliberately excludes it from warehouse cost/profit until a warehouse FIFO status applies.
   - `Склад!Q:Очікується / Японія` includes only `В дорозі`, `На складі в Японії`, and `Виграно`; its formula excludes `Замовлено`. Of 190 ordered units, zero are therefore shown as expected because of their ordered status. The visible 24 `OP-JP-OP16-BST` expected units come from a separate non-ordered lot.
   - The dashboard asset-card sublabel currently names UA, in-transit, Japan, and won stock but omits ordered stock even though API asset totals include it.

## Dashboard rendering

`dashboard/booster-dashboard.html` does not recalculate these cards. `loadOverview()` displays the four fields returned by `summary`: `warehouse_cost`, `potential_profit_warehouse`, `asset_cost`, and `asset_potential_profit`. Thus the dashboard will faithfully display the API defect until the source calculation is aligned; changing only HTML would not fix the numbers.

No authenticated dashboard request was made during this investigation and the CRM token was not read or transmitted.

## Prepared repair — `Замовлено` is expected stock

Owner decision on 2026-08-17: `Замовлено` means a confirmed order and must be visible in expected stock as well as in asset calculations.

- Local `crm/apps-script/Code.gs` now contains public menu action `Оновити очікуваний залишок` backed by `updateExpectedStockFormulaMenu()`.
- It rewrites only formula column `Склад!Q3:Q<last row>` with the confirmed-status set: `Замовлено`, `В дорозі`, `На складі в Японії`, `Виграно`.
- Formula bounds use the existing CRM row-capacity helper, so the range follows current sheet capacity instead of retaining the legacy `Закупки!3:290` cutoff.
- Read-only preflight of the live workbook found formulas in every currently populated `Склад!Q3:Q201` cell and no manual values. The blank tail `Q202:Q220` will receive the same formula on the first run.
- `dashboard/booster-dashboard.html` now labels both asset cards `UA + замовлено + в дорозі + JP + виграно`.
- The `1.05` reserve and completed-sale calculations are deliberately untouched.

## V129 publication evidence and remaining functional step

The owner reported CRM Web App V129 at 2026-08-17 22:41 Kyiv and supplied a post-publish `integrity_check`: `clean:true`, no problems, 3 matched 3D RRP rows, no skipped CRM RRP rows, and elapsed time 9,698 ms. This is valid schema/formula-relation evidence, but that check does not inspect `Склад!Q`.

A direct read immediately after the report found the legacy formula in `Склад!Q3` and `Склад!Q201`: it still ends at purchase row 290 and only includes `В дорозі`, `На складі в Японії`, and `Виграно`. The 13 confirmed `Замовлено` lots remain absent from `Очікується` until the public menu action `Booster CRM → Оновити очікуваний залишок` is run after a spreadsheet refresh. The action is guarded: it stops rather than overwriting any unexpected manual value.

The source of V129 remains unexported, so the local mirror is not byte-compared with V129.

## Remaining repair order

1. Make API FIFO consumption use the same `Налаштування!B8` cutoff as `Склад`.
2. Replace hard-coded stock formula endpoints with capacity-safe ranges, including `Списання` rows beyond 197.
3. Run the public `updateSkuCurrentCostMenu()` wrapper and verify `Склад!I50:L50` for `PKM-JP-ABYE-BBX` after the source fix.
4. Keep the owner-approved `1.05` realization-cost reserve in dashboard potential warehouse/asset profit only. Label the `Склад` value as an unadjusted potential, or apply the same reserve there only if the owner wants the two displays to show the same metric.
5. Apply the prepared `Замовлено` expected-stock formula repair after a fresh V128 export is reconciled; then verify the 190 ordered units appear in `Склад!Q` while warehouse FIFO totals remain unchanged.
6. Before publication, obtain a fresh V128 Apps Script export, then compare the live `summary` response with the four cards after a hard refresh.

## Risk

High accounting-decision risk, low operational urgency: no transaction or historical source row should be edited to repair these totals. The safe repair is calculation/source alignment followed by bounded readback.
