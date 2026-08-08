# Codex Report — 3D-P-024: safe human print-time entry

Date: 2026-08-08

## Scope and deployment state

Implemented the handoff scope locally. **No Apps Script was pasted, Web App was published, workbook
cell was changed, or owner dashboard/local server was deployed in this session.**

The 3D-P mirror pull date is recorded as 2026-08-08 in `crm/apps-script/SOURCE_STATE.md`; its
recorded deployed version is still V7 (2026-08-03). That version record conflicts with the later
3D-P-015/FIX2 rollout history, so the owner must record the replacement version after deployment.

The live conversion factor remains an owner QA gate: confirm on the freshly versioned Sheet that a
Google-entered `1:39` is normalized to decimal `1.65`. The code supports the Sheets time serial
case through the displayed clock value and then restores a plain number format, but local mocks are
not proof of the bound Sheet's locale behaviour.

## Root cause

- `Номенклатура!G` and `Друк-лог!D` had no `onEdit` normalization, unit note, or plausibility
  warning. Google therefore accepted a clock value as a fraction of a day, while downstream cost
  formulas treated it as decimal hours.
- The owner dashboard used a `type="number"` time input. Its generic `threeDpNumber()` strips
  punctuation, so a bypassed `1:39` would become `139`, not 1.65; the input boundary needed its own
  parser.
- Serhiy's server accepted only raw numeric hours, with no shared natural-language parsing.

## Implemented changes

- Added `3d-print/shared/print-time.js`, the canonical browser/server parser and formatter:
  `1:39`, `1 год 39 хв` / `1год39хв` / `1h39m`, and `1,65` / `1.65` all resolve to decimal hours.
  It preserves up to 10 decimal places internally and formats read-only values as
  `1,65 год (1 год 39 хв)`.
- Added a matching, vector-tested Apps Script mirror because a bound Apps Script cannot import a
  repository file at trigger runtime. The API test compares every accepted/rejected vector against
  the canonical parser.
- Added a tightly scoped simple `onEdit(e)` for only `Номенклатура!G2:G` and `Друк-лог!D2:D`:
  blanks remain blank; normal values become plain decimal numbers; input outside 0.02–100 h gets a
  cell-note warning without blocking the edit. Script writes do not recurse into a simple trigger.
- Added owner-only idempotent `3dp_setup_3dp024`. It writes the two header notes, fills only blank
  approved `Номенклатура!K` and `Продажі!I/K/L` formulas, corrects `Аналітика!A1`, never touches
  `Аналітика` row 18 or below, and appends `SETUP_3DP024` to `_Аудит_API` only when it changes
  something.
- Updated the existing 3D-P-015 validator to accept a blank formula cell but still reject a
  nonblank, unapproved literal/formula. The corrected audit text now describes the actual
  defect-rate uplift instead of claiming a fixture-only change.
- Dashboard batch input and both Serhiy inputs now accept the shared human forms, show live parsed
  hints, submit decimal hours, show both forms for read-only values, and show the same non-blocking
  implausible-time warning. `calculator.mjs` arithmetic is unchanged.
- Added `live-3D-P-024-setup.ps1`: it validates the 3D-P endpoint via `3dp_overview` before any
  write, rejects a main-CRM URL/token, requires explicit `-ConfirmLiveWrite`, and saves redacted
  evidence under `diagnostics/`.

## Files touched

```text
3d-print/shared/print-time.js
3d-print/apps-script-3dp-api/Code.gs
3d-print/apps-script-3dp-api/tests/api.test.mjs
3d-print/apps-script-3dp-api/tests/live-3D-P-024-setup.ps1
3d-print/serhiy-local-server/server.mjs
3d-print/serhiy-local-server/public/index.html
3d-print/serhiy-local-server/public/app.js
3d-print/serhiy-local-server/tests/server-local.test.mjs
dashboard/booster-dashboard.html
dashboard/tests/3dp-sync-journal-static.test.mjs
```

## Local verification

```text
node 3d-print/apps-script-3dp-api/tests/api.test.mjs
  PASS — shared/App Script parser vectors; onEdit scope, clock/text/decimal/>24 h,
  fail-open warning, blank and unrelated-cell cases; blank formula regression; 3dp_setup_3dp024
  idempotency and owner-only guard

node dashboard/tests/3dp-sync-journal-static.test.mjs
  PASS — shared parser load, text time input, parsed hint, formatted read-only values and warning

npm --prefix 3d-print/serhiy-local-server test
  PASS — 4/4; local server serves the shared parser and stores 18:00 / 1 год 39 хв as decimal hours

node --check 3d-print/serhiy-local-server/server.mjs
  PASS
```

No live Sheet, Web App, browser visual session, or owner QA was performed.

## Idempotency and rollback

- A repeat `3dp_setup_3dp024` returns `already_applied: true` and writes no extra audit row when
  notes, formulas and title already match.
- `onEdit` is additive; removing it stops future normalization and leaves stored decimal values
  unchanged.
- For a full rollback, restore the fresh named Google Sheets version made immediately before this
  setup. The source rollback is owner-controlled through the immediately preceding 3D-P Web App
  version.

## Owner deployment command

After creating a fresh named Google Sheets version, pasting `Code.gs` into the bound 3D-P project,
and publishing a new Web App version:

```powershell
.\3d-print\apps-script-3dp-api\tests\live-3D-P-024-setup.ps1 -ConfirmLiveWrite -SaveEvidence
```

Update the local dashboard and Serhiy server from this worktree; restart the server and hard-refresh
the dashboard. Do not change Script Properties.

## Post-deploy owner QA

- [ ] In `Номенклатура!G` enter `1:39`; confirm numeric `1.65`, plain number format, and correct
      downstream cost. Repeat with `1,65`, `1 год 39 хв`, and a value above 24 h.
- [ ] Repeat in `Друк-лог!D`; enter `0,001` and confirm a visible warning appears but the edit is
      not blocked.
- [ ] For `ACC-3D-DITTO-410` at `1.65`, confirm cost `50.32 грн`, margin `24.84`, Serhiy accrual
      `75.16`, and hourly result `45.55`.
- [ ] Confirm a blank `Номенклатура!K` and blank `Продажі!I/K/L` are filled by setup; verify the
      audit says `SETUP_3DP024` and `Аналітика` market references remain at row 18 or lower.
- [ ] In dashboard batch calculator and both Serhiy forms enter `2:30`; confirm the hint is
      `2 год 30 хв` and the stored request is decimal `2.5`.
- [ ] Check dashboard at desktop, tablet, and mobile widths, plus hover/focus and long hints.

## Side effects and risks

This remains a financial-model change: print time feeds production cost, reimbursement and the 50/50
base. Cell-note warnings are deliberately fail-open. The onEdit assumption is valid only while the
owner remains the sole direct workbook editor; Serhiy continues to use the constrained local server.
