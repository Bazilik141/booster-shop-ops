# CRM Mystery Box write-off inventory audit

Date: 2026-08-24  
Scope: 2026-07-01 through 2026-08-23 inclusive  
Mode: read-only CSV reconciliation; no CRM, spreadsheet, or source-code changes were made.

## Decision summary

Every Mystery Box sale in the supplied export has associated write-off ledger rows. The warehouse stock totals do not, however, include the valid write-offs entered after the current `Склад!Списано` formula boundary. Before expanding that formula, the duplicate `WRT-0206` rows must be repaired; otherwise stock would be reduced by an additional invalid 32 units.

## Evidence base

- `Booster Shop CRM — облік товарів - Продажі.csv`: 301 sale records.
- `Booster Shop CRM — облік товарів - Списання.csv`: 234 write-off records.
- `Booster Shop CRM — облік товарів - Склад.csv`: 94 stock records.
- Dates were normalized because the exports contain both `YYYY-MM-DD` and `DD.MM.YYYY` formats.
- Customer-facing note content was not copied into this report.

## Mystery Box linkage

- 18 Mystery Box sale rows represent 17 orders and 23 boxes.
- All 17 orders / 23 boxes link to one or more write-off ledger rows through the operation code retained in the write-off record.
- Valid Mystery Box components written after the formula boundary total **44 units**. They exist in `Списання` but do not currently reduce `Склад`.

## Formula-boundary reconciliation

An independent SKU-by-SKU reconciliation of all 94 stock records found an exact match only when `Склад.Списано` is compared with `Списання` data rows 1–195 (Google Sheets rows 3–197, `WRT-0001` through `WRT-0195`). The residual is zero for every SKU at this boundary.

The export contains 39 later write-off rows (`WRT-0196` onward) totaling 79 units. None is included in the current stock write-off total. After removing the known duplicate excess, 47 of those units are legitimate ledger consumption:

| Category | Units ignored by `Склад` |
| --- | ---: |
| Mystery Box components | 44 |
| Other write-offs | 3 |
| Total valid ledger consumption | **47** |

## Duplicate ledger rows

`WRT-0206` appears 17 times as an identical record (same SKU, date, quantity, and fields). One record is consistent with the intended operation; the other 16 copies add **32 invalid units**. This is the only duplicate write-off ID found in the scoped period.

The current formula boundary happens to exclude these duplicates together with the valid late rows. Extending the formula first would incorrectly deduct all 79 units, including the invalid 32.

## Other checks

- No blank derived-name or cost fields were found in scoped write-off records.
- The 3 legitimate non-Mystery-Box post-boundary units are also absent from `Склад` totals.
- This audit proves the supplied export's ledger-to-stock discrepancy. It does not prove physical stock, FIFO cost correctness, or the current published Apps Script version.

## Safe correction order (requires owner authorization)

1. Take a fresh spreadsheet copy/backup and record a bounded preflight of the affected rows.
2. Verify and remove the 16 surplus `WRT-0206` ledger duplicates, retaining exactly one intended row; read back the result.
3. Repair or extend the `Склад!Списано` formula so it includes valid future write-off rows without a fixed row cap; then reconcile all affected SKUs again.
4. Only then perform the physical count and create new manual write-offs for the remaining difference. Do not re-enter the 44 Mystery Box units: they are already present in the ledger and would be double-counted once the formula is repaired.

## Current disposition

The first recovery candidate stopped in its preflight before writing at 12:53 Kyiv because it
incorrectly targeted `Склад!H`. A direct live read confirmed that `Склад!G3`, not `H3`, contains
the hard-capped write-off formula. The corrected candidate then completed at 12:59 Kyiv:
`duplicate_rows_cleared:16`, `stock_formula_rows_updated:94`, and CRM integrity was clean before
and after. A direct live read confirmed exactly one `WRT-0206` remains and all 94 `Склад!G`
formulas now reference the full current `Списання` range through row 236.

The temporary recovery menu/helper/test has been removed from the local mirror. The owner later
reported that the replacement script was pasted, all menu actions (including the existing
`Оновити собівартість складу`) were run, and CRM Web App V148 was published at 13:06 Kyiv. That
V148/source state is owner-reported: no fresh complete Apps Script export or separate current-cost
return payload was supplied for an independent byte comparison or full FIFO reconciliation. A
bounded direct read found numeric `Склад!I:J` values without formula errors for sampled affected
SKUs. Local cleanup evidence: `Code.gs` parsed and the remaining 20 Apps Script tests passed.
