# Codex Report — 3D-P-008: Addendum #2 API/schema extension

Date: 2026-08-02

## Outcome

The local Addendum #2 package is ready. The owner has run both its no-net-change
smoke and its controlled data-changing positive smoke successfully. It adds
owner-only settings writes, persistent raw batch drafts, reversible SKU
archive/restore, and a traceable stock-adjustment ledger without allowing any
API caller to overwrite an availability formula.

Codex did not deploy or write to the live Sheet. The owner-run positive evidence
below completes the Addendum #2 QA gate; reconciliation business writes remain
outside this addendum and require their separate approval.

## Runtime correction after owner preflight

The owner ran the prior local `preview3dpApiAddendum2()` and
`setup3dpApiAddendum2()` from the Apps Script editor. Both stopped before any
write with `Номенклатура!F3 has an unsupported SKU status`.

Root cause: the first local revision incorrectly treated existing column `F`
as the new archive state. The observed value proves it is a legacy business
status field. The corrected local revision leaves `F` untouched and creates
`O=API_статус_запису` plus `P=API_історія_змін`, mirroring `Друк-лог`'s system
pattern. Its mock fixture now contains a deliberately unsupported legacy `F3`
value and passes setup, archive/restore, and audit tests.

## Scope and design decisions

The handoff required four API/schema additions. The implementation preserves
legacy business data and adds only isolated technical schema:
catalogue columns:

- `_Чернетки_партій` is a hidden SKU-keyed internal table for the five raw
  calculator inputs. Owner and Serhiy can use only the specialized bounded
  read/save actions; every supplied field requires `expected_current`.
- Legacy `Номенклатура!F` remains untouched as the existing business-status field.
  Addendum #2 creates technical `O:P` archive state/history; generic writes to
  `O` are blocked, new SKU rows get technical `Активний`, and owner-only
  specialized actions set `Активний`/`Архів` with both row history and audit.
- `_Коригування_наявності` is a hidden append-only ledger. `3dp_adjust_stock`
  writes a delta plus mandatory reason there, while `Наявність!G` remains a
  formula and adds the ledger sum. It never receives a raw overwrite.
- `3dp_skus` and `3dp_overview` exclude archived SKUs by default. The owner may
  request `include_archived=true` only to choose a row to restore.

`preview3dpApiAddendum2()` and `setup3dpApiAddendum2()` reject the package until
Addendum #1's final-cost headers/settings/print-log archive anchors are present.
This prevents an out-of-order schema deployment.

## Files touched

```text
3d-print/apps-script-3dp-api/Code.gs
3d-print/apps-script-3dp-api/tests/api.test.mjs
3d-print/apps-script-3dp-api/tests/live-addendum2-smoke.ps1
3d-print/apps-script-3dp-api/README.md
dashboard/booster-dashboard.html
```

## Local validation

```text
Code.gs copied to a temporary .js file -> node --check: PASS
api.test.mjs -> PASS
PowerShell parser for live-addendum2-smoke.ps1: PASS
```

Mock smoke result:

```json
{"ok":true,"setup_idempotent":true,"read_actions_checked":9,"negative_write_tests":["FORMULA_CELL","COLUMN_NOT_ALLOWED","STALE_WRITE"],"extra_security_tests":["FORMULA_VALUE_NOT_ALLOWED","DOCUMENTATION_RANGE_BLOCKS"],"print_log":["append","edit_with_history","archive","restore"],"addendum_2":["owner_settings_only","batch_draft_round_trip","sku_archive_restore","stock_ledger"],"audit_rows":10}
```

The mock covers owner-versus-Serhiy settings access, settings range limiting,
batch-draft stale/formula guards and re-read, SKU archive/restore visibility,
stock stale/negative guards, ledger audit, and route dispatch. It is not Google
Apps Script runtime or live Sheet evidence.

## Owner live smoke evidence

The owner ran `live-addendum2-smoke.ps1` against the deployed `/exec` endpoint
and supplied this successful terminal result:

```json
{"ok":true,"sku":"FIG-CHARM-001","no_net_business_data_change":true,"audit_records_expected":1,"read_actions":["3dp_get_range settings","3dp_skus","3dp_batch_draft","3dp_stock_adjustments"],"guards":["Serhiy settings COLUMN_NOT_ALLOWED","generic SKU status COLUMN_NOT_ALLOWED","stock STALE_WRITE"]}
```

This is external runtime evidence that the live endpoint accepts both identities,
serves the Addendum #2 bounded reads, rejects the listed unauthorized/stale
writes, and made no business-data change. The only expected live write is one
same-value owner settings audit row. It does not exercise the controlled
positive business-data QA below.
## Owner API-only positive smoke evidence

The owner deployed the source containing owner-only `3dp_setup_addendum2` and
ran `tests/live-addendum2-positive-smoke.ps1` against the `/exec` endpoint for
`FIG-CHARM-001` with `StockDelta=+1`. The terminal result returned `ok:true`.

The four API-only controls passed:

1. `3dp_setup_addendum2` returned `already_applied:true` with no changes.
2. The previously absent batch draft saved and fresh-read all five values:
   quantity `2`, total weight `12.5`, total print time `0.5`, spool weight
   `1000`, spool price `750`.
3. The SKU transitioned `Активний → Архів → Активний`; it was absent from active
   `3dp_skus` during the archive and present with `include_archived=true`.
4. `Наявність!G` changed `0 → 1`; the bounded ledger read returned row `2` with
   SKU `FIG-CHARM-001`, delta `+1`, and reason
   `3D-P-008 Addendum #2 positive smoke 9aa8d57670b74810b5464f3826e5078b`.

The durable expected effects are the test draft, two archive history/audit
entries, one stock adjustment/audit entry, and the resulting inventory value
of `1`. No automatic counter-adjustment was run.
## Owner deployment gate

1. Confirm Addendum #1's live final-cost schema/smoke evidence is available.
2. In the existing bound 3D-P Apps Script project, replace only `Code.gs`.
3. Run `preview3dpApiAddendum2()`. Stop on any error; it must list the two
   hidden tables and the `Наявність!G` ledger formula change.
4. Create a named Google Sheets version, then run `setup3dpApiAddendum2()` once.
   A second run must report `already_applied: true`.
5. Publish a new version of the existing Web App. Do not alter Script Properties
   or expose either token.
6. From a plain PowerShell prompt, run:

```powershell
.\3d-print\apps-script-3dp-api\tests\live-addendum2-smoke.ps1
```

If the local variables are absent, the script asks for the `/exec` URL and both
tokens itself; token input is masked. The script makes one same-value owner
setting write (one audit row, no net business-data change) and proves the
owner/Serhiy/status/stale guardrails.

## Controlled positive QA after live smoke

- [x] `Code.gs` route deployed in a new Web App version; repeat setup returned
      `already_applied:true` with no changes.
- [x] API-only runner completed the batch-draft, archive/restore, and stock
      ledger checks for the owner-selected `FIG-CHARM-001` test SKU.
- [x] Terminal snapshots showed active/archive/active visibility and
      `Наявність!G: 0 → 1`, with the matching ledger reason/delta.
- [x] The runner output and audit/ledger state are preserved as owner-run live
      evidence. Any later stock counter-adjustment needs separate approval.

## Rollback

Before setup, the owner creates a named Google Sheets version. If the new schema
or formula behavior is wrong, disable the new Web App deployment and restore
that named Sheet version. Revert `Code.gs` to the prior deployed version only
when the Sheet version is restored as well; the old source does not understand
the two new internal tables. For a later confirmed business adjustment, use the
ledger/audit record to apply an explicitly approved counter-adjustment through
the guarded API.

## Risks and limits

- This package cannot establish live Google runtime behavior locally.
- `Наявність!G` changes its formula and must be spot-checked for the actual
  Ukrainian-locale separator behavior after deployment.
- The data-changing positive smoke has now created a test draft, archive/restore
  history, and a `+1` stock ledger entry for `FIG-CHARM-001`; it is a real
  business-data result, not a mock. Reversing the stock effect requires a
  separately approved counter-adjustment through the guarded API.
- Reconciliation remains outside this addendum and still needs separate owner
  approval.