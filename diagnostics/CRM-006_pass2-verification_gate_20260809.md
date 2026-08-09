# CRM-006 — PASS 2 verification gate (2026-08-09)

## Scope

Record the evidence available after the owner-confirmed PASS 2 change, before
starting PASS 3 (`Майстер_Товарів!P2`).

## Live cell verification

Read at 2026-08-09 from the main CRM workbook (`РРЦ!E75:H75`):

| Cell | Observed value | Result |
|---|---|---|
| `РРЦ!E75` | `100` (`100,00 грн`) | PASS 2 price value is present |
| `РРЦ!F75` | `2026-08-08` | Preserved |
| `РРЦ!G75` | `Початкова РРЦ 2026-08-08: ACC-3D-DITTO-410; 3D аксесуар.` | Preserved |

`H75` remains a formula. No other cells were written by Codex.

## Required integrity-check result

The PASS 2 handoff requires recording the raw `integrity_check` result after
the edit. That raw JSON/output is not present in the repository diagnostics or
handoffs supplied to Codex. Therefore the following outcome is **not yet
verified**:

- total problems changed from `78` to expected `77`;
- `rrp_mismatch_3dp` disappeared;
- all other per-code counts remained unchanged.

The direct cell read proves the owner edit, but it is not a substitute for the
required API integrity output. PASS 3 is intentionally not started until the
raw output is attached or pasted and recorded.

## Next bounded action

Record the raw post-PASS-2 `integrity_check` output, then perform the PASS 3
preflight for `Майстер_Товарів!P2` (named Sheets version, current formula,
source header, and neighbouring formula check) before changing that one cell.

## Subsequent owner-provided PASS 2 integrity result

The owner subsequently confirmed in the active task instruction that the
post-PASS-2 check changed `78` to `77`, `rrp_mismatch_3dp` disappeared, and no
other problem-code count changed. This is the bounded pre-PASS-3 baseline.
The raw JSON payload was not attached, so its capped `problems` sample,
`truncated`, `coverage`, and `elapsed_ms` fields remain unrecorded.
