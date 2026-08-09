# CRM-006 — PASS 3 P2 index repair (2026-08-09)

## Scope and authority

One formula-only change in the automation workbook:

- Spreadsheet: `Booster Shop — Майстер-дашборд автоматизацій`
- Sheet / range: `Майстер_Товарів!P2`
- Change: `VLOOKUP` index `13` to `12` only

The owner directed this pass after confirming PASS 2 as closed. No Apps Script,
dashboard source, other sheet cell, or formula range was changed.

## PASS 2 / PASS 3 pre-check baseline

Owner-provided bounded result immediately before PASS 3:

- total problems: `78 -> 77` after PASS 2;
- `rrp_mismatch_3dp`: cleared;
- all other problem-code counts: unchanged.

The raw JSON was not attached, therefore the capped problem sample,
`truncated`, `coverage`, and `elapsed_ms` are not independently recorded here.

## Preflight evidence

### Named version

Created in Google Sheets before the write:

`CRM-006 PASS 3 — before P2 index fix — 2026-08-09`

This is the rollback point for this pass only.

### Original formula and rollback

`Майстер_Товарів!P2` before the write, verbatim:

```gs
=ARRAYFORMULA(IF(A2:A="";"";IFERROR(VLOOKUP(A2:A;Source_CRM_Products!A7:O;13;FALSE);"")))
```

### Live source mapping

`Source_CRM_Products!A6:O6` is the source sheet's imported header row; the
lookup data begins at `A7`.

| VLOOKUP index | Live header |
| --- | --- |
| 12 | `Активний товар` |
| 13 | `Посилання на товар` |

Thus index 13 produced product links (or blank links) in the `Активний`
column, while index 12 is the required source field.

### Neighbouring formula scan

Reviewed `Майстер_Товарів!A2:U2` for formulas indexing
`Source_CRM_Products!A7:O`.

- `B:J` use the matching indexes `2:10`; no shift found.
- `P2` alone used index `13` into `A7:O`; this was the defect.
- `O2` has a fallback lookup into the shorter `Source_CRM_Products!A7:M`
  range at index `13`, correctly returning the URL field; it is not the same
  `A7:O` lookup and was not changed.
- No other formula in the reviewed master row uses the same `A7:O` index with
  an off-by-one mapping.

## Applied change

`Майстер_Товарів!P2` now contains, verbatim:

```gs
=ARRAYFORMULA(IF(A2:A="";"";IFERROR(VLOOKUP(A2:A;Source_CRM_Products!A7:O;12;FALSE);"")))
```

The Google Sheets batch contained exactly one `updateCells` request for one
cell (`P2`) and the `userEnteredValue` field only.

## Direct post-write verification

- `P2` evaluates to `Так`.
- The spill in `P2:P20` evaluates to `Так` values instead of the former blank
  link-derived output.
- The target cell retains its existing plain-text column formatting; no
  dimension, validation, neighbouring value, or formula changed.

## Remaining required runtime evidence

The full post-write `integrity_check` and hard-refreshed dashboard verification
could not be invoked from the available automation surface: the check requires
the owner-held dashboard session token, and the protected browser cannot open
the local dashboard file. Those outcomes are therefore **not claimed**:

- `master_row_inactive` reduction and any remaining SKU list;
- dashboard SKU list and stock views repopulating;
- potential-profit and asset-profit values no longer reading `₴0`;
- profit plausibility against warehouse cost `₴84,077`.

## Owner QA / rollback

Run `integrity_check` in the dashboard, copy its full JSON result, then hard
refresh and record the three dashboard values above. If the result is not as
expected, restore the named version above or restore the original `P2` formula
from this report.
