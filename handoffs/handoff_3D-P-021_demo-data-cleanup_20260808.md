# Handoff — 3D-P-021: demo/test data cleanup in the live 3D-P Sheet

Date: 2026-08-08 | Parent: none | Blocks: 3D-P-015 (must complete before price-model rebuild starts)
Notion: `3b56bf20-bdb4-8125-8e81-eb68f946b69a`
Risky zone: **CRM** (direct production Google Sheet edit, no staging environment)

## Context

`diagnostics/3D-P_state-audit_20260807.md` (§3 items 5 & 7) confirms two live
contamination sources in the 3D-P workbook
(`docs.google.com/spreadsheets/d/1yp15H3YJGkqI4Rx89G4QZHkD9m67gnWh58TsTTi-jjo`):

1. **`ПРИКЛАД-001`** demo rows are still live in six tabs — `Номенклатура`,
   `Продажі`, `Виплати`, `Маркетингові_плюшки`, `Наявність`, `Аналітика` — and
   feed live totals (`Наявність` +2 units, `Виплати` +165 ₴ accrual as of the
   2026-08-07 audit). The Apps Script API filters them out of reads; the raw
   sheet formulas do not.
2. **`FIG-CHARM-001`** shows `Наявно зараз = 3`, which is not real inventory —
   it is the Addendum #2 positive-smoke artifact (`+1`, see
   `diagnostics/3D-P-008_addendum-2_report_20260802.md`) plus a later manual
   `тест` adjustment.

Owner decision, 2026-08-07 (`context-index.md` row `3D-P-021`): clean both,
**named Google Sheets version first as the rollback point**, and the
`FIG-CHARM-001` stock correction must go through the Addendum #2 ledger API
(`3dp_adjust_stock`) — **never a direct cell edit**. Do this before `3D-P-015`.

No API action exists for row deletion (`Code.gs` action list has
`3dp_nomenclature_archive/restore` and `3dp_adjust_stock` only — no delete).
So `ПРИКЛАД-001` removal is necessarily a manual Sheets-UI edit; there is no
code patch for that half of this task.

## Scope (what to change)

- Live 3D-P Google Sheet — named version created, then:
  - `FIG-CHARM-001` stock zeroed via `3dp_adjust_stock` (ledger-audited).
  - `ПРИКЛАД-001` rows deleted from all six tabs listed above.
- New file: `3d-print/apps-script-3dp-api/tests/live-3D-P-021-cleanup.ps1` —
  one-off runner for the `3dp_adjust_stock` call, adapted from the existing
  `tests/live-addendum2-positive-smoke.ps1` pattern (it already contains a
  `3dp_adjust_stock` call template at line 309).

## What NOT to touch

- `Code.gs` — no source change is required; the ledger API is already
  deployed and live (Addendum #2).
- Any other SKU's stock or archive state.
- The three `Аналітика` price scenarios (`3D-P-015` scope, not this task).

## Step-by-step procedure

**Step 0 — Refresh evidence (do not reuse 2026-08-07 numbers blind).**
Before touching anything, re-read the live sheet: confirm `ПРИКЛАД-001` is
still present in all six tabs, and read `FIG-CHARM-001`'s current
`Наявність!G` value via `3dp_skus` or `3dp_get_range`. The 2026-08-07 audit's
`3` may be stale by 2026-08-08. Use whatever value is read *now* as
`expected_current` in Step 2 — the API rejects a stale value with
`STALE_WRITE`.

**Step 1 — Named version (rollback point, owner, Sheets UI).**
File → Version history → Name current version → e.g.
`3D-P-021 pre-cleanup 2026-08-08`. Mandatory first step per the owner
decision. Do not proceed to Step 2 or 3 without it.

**Step 2 — Zero `FIG-CHARM-001` stock via the ledger API (never a direct cell
edit).**
Executor (see recommendation below) adapts
`tests/live-addendum2-positive-smoke.ps1` into a small runner that POSTs to
the deployed `/exec` endpoint with the owner token
(`BOOSTER_3DP_TOKEN` — never print or log it):

```json
{
  "action": "3dp_adjust_stock",
  "sku": "FIG-CHARM-001",
  "expected_current": "<value read fresh in Step 0>",
  "new_value": 0,
  "reason": "3D-P-021 demo/test stock cleanup"
}
```

Expected response: `old_value=<fresh value>`, `new_value=0`,
`delta=<negative>`, a `ledger_row` number. If `already_applied:true` comes
back, the exact same `reason` string was already logged for this SKU —
verify manually before treating it as a fresh success.

**Step 3 — Delete `ПРИКЛАД-001` rows (owner, manual, Sheets UI).**
No API covers this. Suggested order — dependent/leaf tabs first, master
lookup table last, so no tab shows a transient `#N/A` mid-cleanup:

1. `Виплати`
2. `Маркетингові_плюшки`
3. `Продажі`
4. `Наявність`
5. `Аналітика`
6. `Номенклатура` (master/lookup source — delete last)

Per tab: Ctrl+F `ПРИКЛАД-001` → confirm the row → right-click → **Delete
row** (not "clear contents" — a blank-but-present row can still be inside a
`SUM`/`COUNT` range).

**Step 4 — Verify (smoke test).**
- `Наявність` total no longer includes the phantom 2 units.
- `Виплати` total no longer includes the phantom 165 ₴ accrual.
- `3dp_overview` / `3dp_skus` shows no `ПРИКЛАД-001` row; `FIG-CHARM-001`
  stock reads `0`.
- `_Аудит_API` has the new stock-adjustment log entry; the
  `_Коригування_наявності` ledger has the new row with reason
  `3D-P-021 demo/test stock cleanup`.
- No `#REF!` / `#N/A` in `Наявність`, `Виплати`, or `Аналітика` totals after
  all six deletions.

**Step 5 — Rollback (if anything looks wrong).**
Restore the named version from Step 1 in full. Do not attempt a partial
manual undo of individual row deletions — restore, then re-diagnose before
retrying.

## Acceptance criteria

- [ ] Named Sheets version exists, timestamped before any Step 2/3 write.
- [ ] `FIG-CHARM-001` stock = 0, adjustment logged in
      `_Коригування_наявності` with reason `3D-P-021 demo/test stock cleanup`.
- [ ] `ПРИКЛАД-001` absent from all six tabs.
- [ ] `Наявність` and `Виплати` totals no longer include the phantom
      2 units / 165 ₴.
- [ ] No formula errors introduced in any of the six tabs.

## QA checklist (owner runs after execution)

- [ ] Open the live dashboard 3D-друк tab — totals match the reduced
      expectation (no phantom units/accrual).
- [ ] Spot-check `Наявність!G` for `FIG-CHARM-001` shows `0`, not a formula
      error.
- [ ] Confirm the named version from Step 1 is still present in Version
      history (rollback path stays available even after a successful run).

## Risks

- **No staging** — every write here lands directly on the production Sheet.
- **CRM risky zone** — treat with the same care as a DB change; rollback is
  the named Sheets version, not a per-cell undo.
- Token exposure: the Step 2 runner must never print, log, or commit
  `BOOSTER_3DP_TOKEN`.
- Manual row deletion (Step 3) is only reversible via a full named-version
  restore, not a per-row undo, once other edits stack on top of it.
- Blocks `3D-P-015` — closing this cleanly unblocks that track's sequencing.

## Executor, model and effort recommendation

Split by track:

- **Step 1, 3, 4, 5 (Sheets UI)** — no executor; manual owner action, no code
  involved.
- **Step 2 runner script** — mechanical, small, closely copy-adapted from an
  already-tested pattern (`live-addendum2-positive-smoke.ps1` line 309
  already has the exact `3dp_adjust_stock` call shape). Recommended:
  **Claude Code · Haiku · low** (equally valid: **Codex · Luna · low** — tie-break
  by remaining weekly quota, owner's call).

## Diagnostics / close-out

- After Step 4 passes, the executor of Step 2 files
  `diagnostics/3D-P-021_demo-data-cleanup_report_<YYYYMMDD>.md` using
  `templates/codex-report-template.md` (required — CRM is a risky zone).
- Claude (chat) sets Notion `3D-P-021` to `Done` and mirrors `ROADMAP_FLOW`
  only after the owner confirms the QA checklist above, per
  `ROADMAP_SOP.md` §3 lifecycle stage 6.
