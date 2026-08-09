# Codex Report — CRM-006 bounded live diagnosis

Date: 2026-08-09

## Scope and method

Read-only diagnosis for CRM-006. Exact metadata and bounded `get_cells` reads were used against
the main CRM and the automation workbook; no live cell, formula, format, validation, or Apps Script
source was changed.

## Root causes proved

### 1. RRP cascade: the spill blocker is `РРЦ!A76:D76`

`РРЦ!A3:D3` are the intended ARRAYFORMULA seeds and currently return `#REF!`: each reports that the
array result would overwrite data in row 76. `РРЦ!A76:D76` contains literal values, not formulas.
They exactly duplicate the keys derived from `Товари!A76`, `B76`, `D76`, and `G76`:
`PKM-EN-PBLK-BLR-SLP`, its short name, `Pokémon`, and `Blister`.

Rows `71:75` contain manual RRP/date/note cells in `E:G`, but their `A:D` cells are blank because
the spill is blocked. Their notes identify SKUs that match `Товари!A71:A75`, including
`ACC-3D-DITTO-410` at row 75. Therefore clearing `A76:D76` (and only those four duplicate literals)
is the minimum action that permits the existing seeds to repopulate keys for rows `3:76`. Clearing
rows `71:75` alone would **not** restore the spill.

`РРЦ!E75` remains a separate, known business correction: its live value is `90`, while the owner
has confirmed `100` for `ACC-3D-DITTO-410`.

### 2. `master_row_inactive`: wrong VLOOKUP index in an existing formula

`Майстер_Товарів!P2` is an ARRAYFORMULA that uses
`VLOOKUP(A2:A;Source_CRM_Products!A7:O;13;FALSE)`. In the imported source, column 12 is
`Активний товар`; column 13 is `Посилання на товар`. Consequently `P2:P` is blank for the sampled
active SKUs, and CRM-005 correctly classifies them as inactive. This is a formula defect, not 72
independent catalogue-row omissions.

### 3. `formula_column_literal`: genuine overwritten derived formulas

- `Товари!B` and `J` have row formulas at row 3, but every reported sample row has a literal
  string/number instead. The short-name values can match the visible formula result, but are still
  literals rather than the required formula.
- `Розхідники!F:H` are derived from `Витрати` and `Продажі`. Row 4 and selected valid rows have
  formulas; the reported rows contain numeric literals in those same calculated columns. These are
  not manual-input columns by their headers or their surrounding formula pattern.

## Required owner action before the first write

The live diagnosis proved that the minimal spill repair must clear **`РРЦ!A76:D76`**, not blank
rows `71:75`. This four-cell scope extension and the separate `E75` price correction are approved
by `handoffs/handoff_CRM-006-PASS1_rrp-spill-repair_20260809.md`; the handoff assigns their execution
to the owner. Codex remains read-only unless the owner explicitly reassigns the write.

## PASS1 pre-write rollback material — 2026-08-09

Fresh bounded read of `РРЦ!A76:D76` recorded these literal values verbatim:

| Cell | Current value |
| --- | --- |
| `A76` | `PKM-EN-PBLK-BLR-SLP` |
| `B76` | `Pokémon — Pitch Black — EN — Blister — Slowpoke` |
| `C76` | `Pokémon` |
| `D76` | `Blister` |

These values are the exact rollback material. The required owner action is **Clear contents** for
`РРЦ!A76:D76` — never delete cells or shift cells left. `E76:H76` must remain untouched.

If approved, the safe first pass is:

1. Owner creates a named Google Sheets version and saves the raw pre-write `integrity_check` JSON.
2. Re-read `РРЦ!A71:H76`; clear **only** `A76:D76`, preserving `E:H` and every other row.
3. Re-read `РРЦ!A3:H76` enough to confirm the spill has recalculated, then run and record the
   integrity check. This must make the previously missing SKU-keyed RRP rows visible.
4. Confirm the new `rrp_mismatch_3dp` result for `ACC-3D-DITTO-410` (`CRM 90` vs 3D-P `100`).
5. In a second explicit micro-pass, set **`РРЦ!E75` to `100`**, re-read it, and run the check again.

The `Майстер_Товарів!P2` formula repair and all `Товари` / `Розхідники` formula restoration remain
separate stages after this before/after pair. No bulk fill-down is authorised.

## Rollback and verification

The named Sheets version is the rollback point. For the first pass, the exact inverse is restoring
the four original literals in `РРЦ!A76:D76`; it should be needed only if the spill does not rebuild
as expected. The focused smoke test is: `A3:D3` no longer show `#REF!`, rows `71:76` have SKU keys,
and the integrity output no longer reports the RRP cascade as unkeyed rows.

`diagnostics/CRM-005_first-live-baseline_20260809.md` remains until a clean post-repair baseline is
recorded; it was not deleted.
