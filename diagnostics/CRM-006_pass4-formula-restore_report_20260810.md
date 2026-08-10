# Codex Report — CRM-006: pass 4a partial formula restore

Date: 2026-08-10

## Scope

Owner authorised CRM-006 pass 4a in `Товари!B` (`Коротка назва`): rows `38-39`, `49-67`, and `71-76`, plus the empty-coverage extension `B70` and `B77:B81`.

The live evidence proved that a single formula is unsafe for all 33 cells. With the owner's instruction to proceed, this pass applied the verified ordinary formula only to the 18 cells where it preserves the current result or an intended empty result. `Товари!J`, `Розхідники`, `Налаштування`, Apps Script, the dashboard, CRM-008, Notion, and the local roadmap were not changed.

## Named rollback point

Owner-created Google Sheets version before the write:

`10 серпня, 17:56 CRM-006-4a-before-20260810`

## Live reference formula

The three intact formulas `Товари!B3`, `B40`, and `B48` matched after row-reference normalization:

```gs
=IF(OR($D<row>="";$F<row>="";$E<row>="";$G<row>="");"";$D<row>&" — "&$F<row>&" — "&$E<row>&" — "&$G<row>)
```

There is no `ARRAYFORMULA` seed in `B2`.

## Applied Google Sheets write

One atomic `batchUpdate` sent 18 separate one-cell `updateCells` requests, each with the exact `userEnteredValue` field only. No fill-down, copy/paste, validation, formatting, or adjacent cell was changed.

| Range | Count | Pre-write result | Post-write result |
| --- | ---: | --- | --- |
| `B38:B39` | 2 | literal matched ordinary formula | formula; visible value unchanged |
| `B49:B51` | 3 | literal matched ordinary formula | formula; visible value unchanged |
| `B61:B67` | 7 | literal matched ordinary formula | formula; visible value unchanged |
| `B70` | 1 | empty row | formula; remains empty |
| `B77:B81` | 5 | empty rows | formulas; remain empty |

### Literal rollback record

| Cell | Literal before overwrite |
| --- | --- |
| `B38` | `Yu-Gi-Oh! — Blazing Dominion — JP — Booster` |
| `B39` | `Yu-Gi-Oh! — Blazing Dominion — JP — Booster Box` |
| `B49` | `Pokémon — Abyss Eye — JP — Booster` |
| `B50` | `Pokémon — Abyss Eye — JP — Booster Box` |
| `B51` | `Pokémon — Mega Brave — JP — Booster Box` |
| `B61` | `Pokémon — Perfect Order — EN — Booster Bundle` |
| `B62` | `Pokémon — Perfect Order — EN — Booster` |
| `B63` | `Pokémon — Chaos Rising — EN — Booster Bundle` |
| `B64` | `Pokémon — Chaos Rising — EN — Booster` |
| `B65` | `One Piece — OP-16 — JP — Booster Box` |
| `B66` | `One Piece — OP-16 — JP — Booster` |
| `B67` | `Pokémon — Mega Dream EX — JP — Booster Box` |

`B70` and `B77:B81` were empty before the write. The named Google Sheets version is the rollback for the whole partial pass.

## Direct post-write verification

- Formula readback: `18/18` target cells contain the exact row-specific ordinary formula.
- Formula result: all 12 overwritten visible values are byte-for-byte unchanged; `B70` and `B77:B81` still evaluate as empty.
- Formatting: the request field mask was `userEnteredValue`; the target cells retain their existing cell format metadata.
- Visual check: rendered `Товари!B38:B81` at 100% in Google Sheets. Header, row banding, width, and wrapping were unchanged; no new clipping or overlap was observed.

## Not changed — formula convention still unproven

`B52:B60` and `B71:B76` remain literal and intentionally untouched.

- For `B52:B60`, `E` and `F` are blank. The ordinary formula would return `""` and erase nine non-empty accessory names.
- `B71:B75` (Mystery/3D) do not follow the ordinary four-input output.
- `B76` needs the `Slowpoke` suffix, but no intact formula proves how to derive it.
- Existing `B68` and `B69` prove multiple formula conventions by adding the separate `Salamence` and `Gallade` suffixes.

## Historical-source evidence

With owner approval, a separate, temporary Google Sheets copy was created from the version-history entry `8 серпня, 22:54`. Its spreadsheet ID is distinct from the live CRM, and it was inspected read-only at `Товари!B52:G60` and `Товари!B71:G76`.

- Every cell in `B52:B60` has `userEnteredValue.stringValue`; none has `userEnteredValue.formulaValue`.
- Every cell in `B71:B76` has `userEnteredValue.stringValue`; none has `userEnteredValue.formulaValue`.
- The accessory rows have blank language/set inputs, and the Mystery/3D/Slowpoke rows use names that deliberately differ from the ordinary four-input formula.

This is direct historical evidence that the 15 remaining names were manually governed in the pre-fix version, not formulas that were overwritten during this task. The temporary copy and the live CRM were not edited during the inspection.

## Integrity-check status

The dashboard `integrity_check` was deferred before the partial write, so a pass-specific before result is unavailable. The owner then supplied the following official post-write result verbatim:

```json
{
  "ok": true,
  "action": "integrity_check",
  "checked": ["Товари", "РРЦ", "Розхідники", "Майстер_Товарів"],
  "problems": [
    {
      "sheet": "Товари",
      "rows": "52-60, 71-76",
      "code": "formula_column_literal",
      "detail": "Коротка назва contains a literal where a formula is required."
    },
    {
      "sheet": "Товари",
      "rows": "38-39",
      "code": "formula_column_literal",
      "detail": "Поточна ціна продажу contains a literal where a formula is required."
    },
    {
      "sheet": "Розхідники",
      "rows": "7-15, 17",
      "code": "formula_column_literal",
      "detail": "Надійшло через витрати contains a literal where a formula is required."
    },
    {
      "sheet": "Розхідники",
      "rows": "6, 8, 10-15, 17",
      "code": "formula_column_literal",
      "detail": "Їде через витрати contains a literal where a formula is required."
    },
    {
      "sheet": "Розхідники",
      "rows": "10-11, 13-15, 17-23",
      "code": "formula_column_literal",
      "detail": "Використано в продажах contains a literal where a formula is required."
    }
  ],
  "coverage": {
    "rrp_mismatch_3dp": {
      "compared": 1,
      "skipped_missing_crm_rrp": 0,
      "deferred": null
    }
  },
  "clean": false,
  "elapsed_ms": 7592
}
```

This confirms the expected reduction of the `Товари!B` finding from `38-39, 49-67, 71-76` to only `52-60, 71-76`. The other four known `formula_column_literal` findings are unchanged, and the supplied result contains no new problem code.

## Required follow-up

CRM-006 pass 4a is complete for the 18 cells with a proven formula convention. Do not write formulas into `B52:B60` or `B71:B76` merely to satisfy the current integrity rule: it would replace valid manual names with blank or incomplete values.

To remove the remaining `formula_column_literal` finding safely, the next scoped task must either:

1. change the integrity check to recognize these proven manual-name exceptions; or
2. define and approve a separate, complete formula convention for each exception class before any write.

## Files touched

```text
diagnostics/CRM-006_pass4-formula-restore_report_20260810.md — execution report
```

## Side effects / risks

The 18 applied cells are formula-driven with no current display-name change. The remaining 15 literals are a known incomplete portion of 4a; writing the ordinary formula there would be destructive.
