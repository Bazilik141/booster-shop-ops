# 3D-P-007 follow-up: missing Snorlax availability and draft print time

Date: 2026-09-29 (updated after owner-supplied 3D-P source export)

## Outcome and scope

Implemented and packaged the Serhiy product-draft time-entry fix, a permanent
3D-P availability-provisioning fix, and a guarded one-time Snorlax repair.
Bounded live API reads confirmed the incident; the owner then supplied both
3D-P Apps Script source files. On 2026-09-29 the owner ran the repair and
reported V39 publication. No live write, publication, commit, push, Notion
change, or owner-dashboard edit was performed by Codex.

## Owner-provided live result (2026-09-29)

`preview3dp007Repair` at 12:23:22 listed exactly `Наявність!A76:G76` and
`Номенклатура!G76:H76`: `46205` -> `2.1166666667` hours, text `50.99` ->
numeric `50.99`, expected availability 1. `apply3dp007Repair` at 12:23:43
returned the same fingerprint, `available: 1`, and backup property
`TASK_3D_P_007_BACKUP`. Owner screenshots show 1 unit in Serhiy's UI and in
the owner dashboard's All Products table. The owner reports Web App V39 at
12:24 and subsequently confirmed that temporary files were removed and the
permanent code installed. This proves the owner-provided repair output and
visible stock; a separate byte-level deployment check has not been performed.
Serhiy has not yet installed the packaged local-server updater.

## Live evidence

Read using the configured Serhiy API identity, without exposing credentials:
`3dp_skus`, `3dp_print_log` for `FIG-SNRLX-500`, and narrow `3dp_get_range`
requests for `Наявність!A68:G80`, `Наявність!A73:G76`, and
`Номенклатура!G76:K76`. Raw bounded responses are local-only under
`work/3dp-snorlax-20260928/`; do not stage them.

- `Номенклатура` row 76 is active `FIG-SNRLX-500`, Snorlax. Its API result has
  `availability: null` and print time `46205`.
- `Друк-лог` row 36 records one unit, zero defects, `2.1166666667` hours
  (2 h 07 min), material 50.99 g, and batch cost 72.29684 UAH. The record has
  an actual FIFO batch marker. Do not create another batch to fix display.
- `Наявність` row 73 has all seven formulas. Rows 74–76 have neither values
  nor formulas. The missing source projection is confirmed; it is not a
  dashboard filter or a lost print-log entry.
- The owner dashboard joins the API availability object and turns an absent
  quantity into zero through `threeDpMetrics` / `threeDpInfoRecord`.
- Another active product at Nomenclature row 74 (`ACC-3D-TCG-600`) also lacks
  availability. Row 75 is archived; any repair must preserve status rules.
- Nomenclature G76 is the numeric value 46205, with no formula. Its provenance
  is not established by these reads. A spreadsheet date conversion is possible,
  but is not a proven account of the original input. H76 is text `50.99`;
  K76 has a formula but currently returns blank. Do not equate blank cost with
  a zero cost or rewrite historical FIFO cost.

## Confirmed code causes

- `public/index.html` product draft field G used `type="number"`; the browser
  consequently could not accept a colon.
- `public/app.js` sent draft G as raw form text. `server.mjs:createDraft` passed
  it through unchanged. Unlike the calculator, this path did not use the shared
  print-time parser. The inspected local Apps Script draft normalizer also
  passes G through; deployed source identity is not independently confirmed.
- The owner-supplied `Code.gs` canonical-SKU assignment and direct owner create
  provision analytics but not availability. The missing live formulas are
  consistent with that source behavior. The supplied source has no independently
  verified deployed version identity.

## Implemented files

- `3d-print/serhiy-local-server/public/index.html`: G is text, with colon-capable
  keyboard, examples, a human-readable preview, and an inline validation error.
- `3d-print/serhiy-local-server/public/app.js`: validates and converts G to a
  numeric hour value before submission; preserves readable typed time on blur;
  clears preview on successful form reset. Existing calculator behavior stays.
- `3d-print/serhiy-local-server/server.mjs`: independently parses optional G;
  omits blank input; forwards numeric hours; rejects malformed/negative time.
- `3d-print/serhiy-local-server/tests/server-local.test.mjs`: HTTP contract
  matrix proves clock, decimal-comma, decimal-dot, numeric, words, blank, and
  zero handling, and proves invalid values never reach the upstream fake API.
- `3d-print/apps-script-3dp-api/Code.gs`: narrow permanent mirror change. Draft
  numeric fields are normalized; active product create/SKU assignment now fill
  missing availability formulas from the established row-73 semantics. Existing
  complete projections are preserved. Unexpected formulas, manual values,
  duplicate SKUs, inactive products, schema drift, or insufficient rows block.
- `patches/3D-P-007_snorlax-stock_20260928/Code_final.gs`: deployment candidate
  derived from the owner-supplied bound-project source, not the repository
  mirror. `Code_with_repair.gs` has only the two temporary editor wrappers in
  addition to the final source. The companion `CatalogFifo.gs` exactly matches
  the supplied file. Source hashes and the source/deployment distinction are
  recorded in `3d-print/apps-script-3dp-api/SOURCE_STATE.md`.
- `patches/3D-P-007_snorlax-stock_20260928/3D-P-007.html`: task-named temporary
  editor-only preview/apply repair; it rechecks the exact SKU, FIFO batch,
  print-log entry, row, inputs, and preview fingerprint. It changes only
  `Наявність!A76:G76` formulas and `Номенклатура!G76:H76` numeric inputs,
  verifies the formula balance equals one, writes an audit event, and retains
  a bounded pre-change backup in Script Properties. It never creates a batch.
  The temporary local mirror was removed after owner-provided application and
  UI verification; the package copy remains for audit.

No CSS override, `!important`, new timer, absolute/fixed positioning, or magic
pixel value was added. Shared CSS was not edited. The two pre-existing
`TECH-045` dashboard status/date edits were preserved without modification.

## Verification

- Node syntax checks: passed for app.js and server.mjs.
- Local suite: 27 passed, 0 failed. The sandbox blocked child processes with
  `spawn EPERM`; the same suite passed outside that sandbox. An initial new
  test used a product name/type inconsistent with the existing fake API's
  assertions; test inputs were aligned and the complete suite rerun.
- Isolated browser fixture on port 3118, no live API: colon input accepted;
  `2:07` preview is `2 год 07 хв`; `2:75` shows an error; a submitted test draft
  reaches the fixture as numeric `2.1166666667`. Successful submit resets the
  form. After the final blur adjustment, `2:07` remains readable in the input.
- Visual review at 360, 900 and 1440 px: hint/preview readable, no overlap,
  long product name and existing table remain usable. Focus, invalid and
  pending/disabled submit states exercised. CSS hover rules were unchanged.
- Updater compiled with 22 payload files. Quiet install into a disposable
  fixture returned exit 0; the three changed runtime files match the source,
  previous server file was backed up, runtime sentinel remained unchanged,
  and the installation path was not remembered.
- PHP lint: not applicable. No actual installation on Serhiy's machine was
  performed; local tests do not prove that the updater has reached him.
- The owner-supplied 3D-P source and unchanged FIFO parse with the permanent
  fix. An 8-case fake-Sheet suite passed, including the actual editor wrappers,
  preview/apply, idempotency, changed-source rejection, formula conflict,
  create/activation rollback, and failed-apply restoration. The test runner
  required the same out-of-sandbox context due to `spawn EPERM`.
- Candidate diff against the supplied `Code.gs` contains only the intended
  create/activation hooks, draft numeric normalization, and new availability
  helper. `CatalogFifo.gs` hash is unchanged. No live execution is claimed.

## Artifact and rollback

`3d-print/serhiy-local-server/dist/ПЕРЕДАТИ СЕРГІЮ 2026-09-28 — час чернетки/Booster-3DP-Оновлення_20260928-draft-time.exe`

SHA-256: `b10eb4976c7fc3f5c60d56dc60895da3708bb5b8e2c4b4c355c1ee6fbf613b03`

The updater creates `_update_backups/update-<timestamp>/` in the selected
installation. Restore the replaced files from that backup with the local
server stopped if rollback is needed. It does not modify tokens or portable
Node. Owner delivery: close the Serhiy dashboard; run the updater and choose
the existing folder containing `Запустити.bat`, `app`, and `runtime`; restart.
If it reports the server is still running, wait for its existing idle shutdown.

## Remaining gate

After Serhiy installs the updater, confirm product draft `2:07` through his
updated local server. This is a separate installation/QA gate; do not claim
the new input behavior is live on Serhiy's machine yet.
Also examine active `ACC-3D-TCG-600` on row 74 in a separate bounded repair;
this Snorlax repair intentionally left it untouched. The retained Script
Property `TASK_3D_P_007_BACKUP` supports recovery; do not hand-edit the live
formula projection. The owner authorized a scoped Git commit and push on
2026-09-29; unrelated working-tree files remain outside this task.
