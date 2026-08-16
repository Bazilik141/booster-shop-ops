# Codex Report — 3D-P-007 WP1: role-read projections and settings journal

Date: 2026-08-16

## Outcome

WP1 is implemented locally. The 3D-P API now returns header-name projections
for the `serhiy` role, keeps owner read responses V23-compatible, permits only
`Налаштування!B2:B5` writes by Serhiy within approved numeric bounds, and
records accepted settings changes in an append-only Kyiv-time journal.

No Apps Script version was published and no live Sheet cell was written.

## Scope and deliberate reconciliation

- Changed only the 3D-P Apps Script mirror, its tests, source-state evidence,
  the paste-ready Apps Script file, and this report.
- Did not change `crm/apps-script/Code.gs`, the dashboard, the Serhiy local
  server, or any `Продажі` column.
- `Продажі!H` (`% прибутку Сергію`) is included for Serhiy. The WP1 visibility
  sentence did not enumerate it, but the recorded owner decision list, item
  4.7, explicitly approves this field. It is Serhiy's split percentage, not
  a BoosterShop margin.

## Source baseline

- Owner-supplied V23 export: 2026-08-13 20:17, Europe/Kyiv.
- `3d-print/apps-script-3dp-api/Code.gs` matched that export after line-ending
  normalization; the difference was CRLF representation only.
- The evidence is recorded in `3d-print/apps-script-3dp-api/SOURCE_STATE.md`.

## Live bounded header evidence

Read-only Google Sheets connector reads were limited to header rows and
`Налаштування!A1:B5` in the 3D-P workbook. No orders, customers, or tokens were
read into this report.

### `Продажі` mapping

| Column | Live header | Serhiy |
|---|---|---|
| A | `Дата` | visible |
| B | `SKU` | visible |
| D | `Кількість` | visible |
| H | `% прибутку Сергію` | visible |
| K | `Нараховано Сергію, грн` | visible |
| U | `РРЦ на момент продажу, грн` | visible |
| W | `Платник фурнітури` | visible |
| X | `Режим CRM` | visible |
| Z | `Фурнітура Сергія за од., грн (заморожена)` | visible |
| AA | `Ціна викупу за од., грн (заморожена)` | visible |
| G | `Витрати BoosterShop за од., грн` | hidden |
| N | `№ замовлення` | hidden |
| T | `CRM row number` | hidden |
| V | `Вартість фурнітури за од., грн (заморожена)` | hidden |
| Y | `Фурнітура власника за од., грн (заморожена)` | hidden |

There is no separately named packaging-cost header in the live row. `G` is the
live owner-cost header and is omitted; the implementation does not infer a
separate packaging field.

### `Маркетингові_плюшки` mapping

Visible: `A Дата`, `B SKU`, `F Видано як бонус, шт`.

Hidden: purchase quantities/costs, `G До замовлення №`, and `H Примітки`.
The order link is therefore absent, not blanked.

### Current settings evidence

| Cell | Live value |
|---|---:|
| B2 printer power | 0.11 kW |
| B3 electricity price | 4.32 UAH/kWh |
| B4 amortisation | 12 UAH/h |
| B5 planned defect share | 0.08 |

Approved guards: B2 `0.01–5`, B3 `0.01–100`, B4 `0–1000`, B5 `0–0.50`.

## Implementation notes

- `SERHIY_READ_PROJECTION_3DP` is an explicit header-name allowlist. A missing
  approved header raises `READ_PROJECTION_HEADER_MISSING` with sheet and
  header name. A direct `3dp_get_range` request containing a hidden column
  raises `RANGE_NOT_PROJECTED`; it is never silently trimmed.
- `SERHIY_FULL_ECONOMICS_VISIBLE_3DP` is the only disclosure switch and is
  `false` by default. Flipping it to `true` bypasses the projections for reads.
- `3dp_settings_journal` is a new read action. The new hidden internal sheet
  `_Журнал_налаштувань_3DP` is created only by the first accepted settings
  write and appends: Kyiv timestamp, actor role, parameter, old value, new
  value. Default Serhiy reads are filtered to role `serhiy`; owner reads get
  all entries.
- Material-price history is appended to the existing per-SKU
  `Номенклатура!P` convention only for `Номенклатура!J` (spool price). It uses
  the established `timestamp [actor] old -> new` form.
- Serhiy batch drafts use the internal storage key `serhiy::<SKU>` plus a
  hidden role column. This prevents disclosure of owner drafts and avoids a
  duplicate plain SKU that would make a V23 rollback fail.

## Files

```text
3d-print/apps-script-3dp-api/Code.gs
3d-print/apps-script-3dp-api/SOURCE_STATE.md
3d-print/apps-script-3dp-api/tests/role-read-projections.test.mjs
patches/3D-P-007-WP1_role-read-projections_20260816.js
diagnostics/3D-P-007-WP1_role-read-projections_report_20260816.md
```

The patch file is a byte-identical, paste-ready copy of the updated `Code.gs`.
SHA-256: `BA115AF63F94D34912DB410CF49DA0D56B5B09941A1197A729BA8EEC429CF17D`.

## Local verification

```text
node 3d-print/apps-script-3dp-api/tests/role-read-projections.test.mjs "Версія 23, 13 серп. 2026 р., 2017.txt"
{"ok":true,"owner_paths_preserved":9,"v23_owner_responses_compared":13,"serhiy_projection_checks":18,"settings_journal_checks":8,"full_economics_checks":3}

node 3d-print/apps-script-3dp-api/tests/api.test.mjs
{"ok":true,"active_cleanup_route":true,"archived_setup_routes_removed":5,"preview3dpApiSetup_retained":true}

new Function(Code.gs)
Code.gs syntax ok

git diff --check
no output
```

The 13 V23 comparisons use the owner-provided V23 source against the same local
fake spreadsheet. This is local contract evidence, not proof of production
deployment.

## Owner deployment and QA

1. In the **3D-P bound Apps Script project only**, create a named rollback
   version of the current V23 code.
2. Replace `Code.gs` with the contents of
   `patches/3D-P-007-WP1_role-read-projections_20260816.js`; do not change
   Script Properties or tokens.
3. Save and publish a new version of the existing Web App. Do not publish the
   main CRM script.
4. Execute the WP1 QA checklist from the handoff. In particular verify owner
   payloads still render, hidden Serhiy fields are absent, B2:B5 writes create
   exactly one journal row, and rejected writes create none.
5. Run `integrity_check` and require `clean=true`, `problems=[]`.

The first accepted settings write creates the journal sheet. The first Serhiy
batch-draft save adds the private-draft role column; neither requires a schema
change to `Продажі`.

## Rollback

Republish the retained V23 `Code.gs` as a new Web App version, then hard-refresh
the dashboard. Preserve `_Журнал_налаштувань_3DP` and the batch-draft role
column; neither must be deleted for rollback. V23 ignores Serhiy's namespaced
draft keys instead of treating them as duplicate owner SKU drafts.

## Remaining gate

Owner publication and production QA remain required. WP2 must re-spec the
Serhiy local server against the projected response contract; it was not changed
in WP1.
