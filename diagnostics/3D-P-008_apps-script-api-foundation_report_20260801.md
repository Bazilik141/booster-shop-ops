# Codex Report — 3D-P-008: Apps Script API foundation

Date: 2026-08-01

## Scope

Implemented the local source package for the separate Sheet-bound 3D-P Apps
Script API. The owner performed the first deployment and ran the schema setup on
2026-08-01; Codex did not deploy or initiate those live writes.

Owner-approved deviations from the original handoff:

- two independent Script Property tokens instead of one shared caller token;
- server-derived audit identity (`dashboard` / `serhiy`);
- the amortization schema prerequisite moved from 3D-P-006 into 3D-P-008;
- Serhiy receives print-log append, edit, reversible archive, and restore actions;
- every print-log edit also appends automatic `було → стало` history in the row;
- archived print-log rows stop contributing to `Наявність` formulas;
- Serhiy's later local UI will edit `Номенклатура!G:J,L:O` while formula K stays blocked.

## Files touched

```text
3d-print/apps-script-3dp-api/Code.gs
3d-print/apps-script-3dp-api/appsscript.json
3d-print/apps-script-3dp-api/README.md
3d-print/apps-script-3dp-api/tests/api.test.mjs
3d-print/apps-script-3dp-api/tests/live-negative-smoke.ps1
3d-print/apps-script-3dp-api/tests/live-positive-audit-smoke.ps1
diagnostics/3D-P-008_reconciliation_diff_20260801.md
diagnostics/3D-P-008_apps-script-api-foundation_report_20260801.md
dashboard/booster-dashboard.html
```

The dashboard change in this task is limited to the required `ROADMAP_FLOW`
entry. The pre-existing apostrophe-escaping change in the same file is preserved
and is not part of this implementation.

## Local checks

Syntax:

```text
Code.gs copied to a temporary .js path → node --check: PASS
tests/api.test.mjs → node --check: PASS
tests/live-negative-smoke.ps1 → PowerShell parser: PASS; owner executed successfully
tests/live-positive-audit-smoke.ps1 → PowerShell parser: PASS; owner executed successfully
```

Mock smoke test:

```json
{"ok":true,"setup_idempotent":true,"read_actions_checked":7,"negative_write_tests":["FORMULA_CELL","COLUMN_NOT_ALLOWED","STALE_WRITE"],"extra_security_tests":["FORMULA_VALUE_NOT_ALLOWED","DOCUMENTATION_RANGE_BLOCKS"],"print_log":["append","edit_with_history","archive","restore"],"audit_rows":5}
```

Covered locally:

- separate owner/Serhiy token identities;
- idempotent schema setup;
- bounded row/range reads and example-row filtering;
- overview/SKU/sales/plyushky/payouts reads;
- formula-cell rejection;
- role/non-whitelist rejection;
- stale optimistic-lock rejection;
- successful audited write;
- print-log append and automatic history;
- reversible archive and restore;
- audit sheet identity evidence.

These are local mocks. They are not Apps Script deployment or Google runtime proof.

## Live read-only evidence

- exact live spreadsheet ID and eight expected tabs confirmed;
- bounded header/formula/font-color ranges inspected;
- live Sheet title reports v6 but differs from the local v6 source;
- `Номенклатура!J3` is still blank live and `1549.98` locally;
- current Sheet timezone is `America/Los_Angeles`; the new Apps Script manifest
  and audit/current-month logic use `Europe/Kyiv` without changing Sheet timezone;
- during the Codex preflight, no live cells were written; the owner-run setup
  later applied the schema changes described below.

## First live deployment correction

A bounded post-setup read confirmed the hidden `_Аудит_API`, the new
`Номенклатура!O` header, amortization-aware K formulas, and print-log state/history
columns. It also found `#ERROR!` in `Наявність!C:D`. The live spreadsheet locale
is `uk_UA`; the first source version wrote comma-separated `IF`/`SUMIFS` arguments.
Existing valid formulas use semicolons.

The local source now emits semicolon-separated archive-aware formulas and no longer
canonicalizes commas and semicolons as equivalent, and adds the owner-run `repair3dpAvailabilityFormulas()` entrypoint, which
repairs only the currently invalid `Наявність!C:D` formulas. A regression assertion rejects comma-separated
output. The owner ran the targeted repair; bounded read `Наявність!C2:D15` now shows valid formulas and numeric results.
Local mock QA passes, including repair idempotency. The owner then published the
corrected deployment version and completed owner-token live API QA described below.

The published `/exec` URL was probed without a token and correctly returned
`UNAUTHORIZED`; no API action or business-data write was reached.

## Live API QA evidence

The owner-run negative smoke test returned `FORMULA_CELL`,
`COLUMN_NOT_ALLOWED`, and `STALE_WRITE`, with `live_cells_changed: 0`.

The owner then approved and ran the positive audit smoke on
`Номенклатура!O3`. Its result confirmed `blank → 0 → blank` and zero net cell
change. A bounded read of hidden `_Аудит_API!A1:H10` confirmed exactly two new
rows: identity `dashboard`, operation `WRITE`, target `O3`, values `∅ → 0` and
`0 → ∅`, at `2026-08-01 13:13:58` and `13:14:03` Kyiv.

## Idempotency

`setup3dpApi()` validates exact headers and occupied target columns. After the
first successful setup, a repeat returns `already_applied: true` when no schema,
formula, color, audit-sheet, or visibility change remains.

Archive/restore actions return `already_applied: true` when the row is already in
the requested state. Physical delete is not implemented.

## Rollback

Before setup, the owner creates a named Google Sheets version. Rollback options:

1. disable the new web-app deployment;
2. restore the named pre-setup Sheet version;
3. for later business-data writes, use `_Аудит_API` old values to reverse only
   the approved cells through a new guarded API write.

The main CRM Apps Script, OpenCart, database, and `BOOSTER_CRM_TOKEN` are not
touched.

## Owner deployment and QA gate

Owner-token deployment and live API QA are complete; see the evidence above.

Still required:

- verify the Serhiy token and real print-log actions during 3D-P-007;
- review and approve the reconciliation diff before any listed business-data write;
- apply only approved differences through `3dp_write` and spot-check live formulas.

## Side effects / risks

- Reconciliation and Serhiy print-log writes remain risky until their separate owner gates pass.
- Apps Script source validation cannot prove Google runtime behavior.
- The Google Sheet is actively edited; every downstream edit must send the last
  read value via `expected_current` or `expected_status`.
- The 3D-P-006 API deployment prerequisite is met. Its handoff still requires
  3D-P-008 Done in Notion or an explicit owner override while reconciliation is deferred.
- 3D-P-007 remains blocked until the Serhiy token and live print-log actions pass.
