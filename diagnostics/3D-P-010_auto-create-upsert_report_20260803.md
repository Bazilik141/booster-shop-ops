# 3D-P-010 auto-create sale upsert report

Date: 2026-08-03
Codex config: model=Sol · effort=xhigh
Status: local implementation prepared; deployment and live QA remain owner gates

## Outcome

The V87 update-only behavior is corrected locally. The CRM hook now creates a
3D-P `Продажі` row for each missing trigger-SKU line through `3dp_append_row`,
then keeps the existing update path for later CRM updates. Matching uses the
composite key `Продажі!N` (CRM order id) plus technical `Продажі!T` (CRM row
number), so multiple 3D-P lines in one order do not collide.

The 3D-P API source now has an owner-only bounded setup guard for column T. It
writes `T1=CRM row number` only when T is empty and returns `T_NOT_EMPTY` without
writing if any T data or formula already exists. T is integer-only, formula-free,
and rejected by generic `3dp_write`.

Automatic stock reservation uses the existing `3dp_adjust_stock` ledger with
reason `auto: CRM order <id> row <crm-row>`. The operation is idempotent by SKU
and exact reason. Insufficient stock is fail-open: the automatic ledger entry
may produce a negative resulting stock and returns a non-blocking warning;
manual stock adjustments retain the non-negative guard.

## Files changed

- `3d-print/apps-script-3dp-api/Code.gs` — T-column schema guard, technical
  append validation, filtered stock-ledger reads, automatic negative-stock
  warning behavior, and `3dp_setup_3dp010`.
- `3d-print/apps-script-3dp-api/tests/api.test.mjs` — setup, technical-column,
  idempotency, filtered-ledger, and insufficient-stock mock coverage.
- `3d-print/apps-script-3dp-api/README.md` — deployment and API contract notes.
- `patches/3D-P-010_crm-packaging-pull_20260802.js` — bounded T/header read,
  composite-key create/update path, packaging-cost write, and stock ledger call.
- `tests/3d-p-010-crm-packaging-pull.test.mjs` — CRM hook mock scenarios.

Existing owner changes in the dashboard, handoff, and other diagnostics files
were preserved and are outside this implementation scope.

## Local evidence

- `node --check patches/3D-P-010_crm-packaging-pull_20260802.js` — pass.
- `Code.gs` parsed through the Apps Script-compatible local syntax gate — pass.
- `node --test 3d-print/apps-script-3dp-api/tests/api.test.mjs` — pass.
- `node --test tests/3d-p-010-crm-packaging-pull.test.mjs` — 8 tests passed.
- `git diff --check` — required as the final repository check before handoff.

A bounded live Google Sheets read of `Продажі!A1:T2` in formula mode was also
completed after metadata resolution: the current A:S headers were returned and T1:T2
were blank, with no values or formulas. This confirms the physical T column is empty
for the owner setup gate, but does not prove a deployed Apps Script version, a live CRM
hook, or production behavior. The CRM hook still performs its own bounded `3dp_get_range`
T1 check immediately before any create-path write.

## Bounded owner deployment sequence

1. Review the updated `Code.gs` in the existing bound 3D-P Apps Script project.
2. Deploy the updated API source as a new web-app version, keeping the existing
   Script Properties and tokens unchanged.
3. Run the owner-only `setup3dp010()` (or its `3dp_setup_3dp010` route) after
   the bounded T check. If T is not empty, stop on `T_NOT_EMPTY`; do not guess
   or overwrite the column.
4. Confirm the API returns `T1=CRM row number`, then publish the API version
   used by the CRM hook.
5. Apply the PHP/Apps Script handoff hook to the current main CRM source only
   after its live/current anchors are confirmed. Configure the existing local
   `BOOSTER_3DP_URL` and `BOOSTER_3DP_SYNC_TOKEN` properties without exposing
   their values, then deploy the CRM source.
6. Run owner QA for one trigger line, multiple trigger lines in one order,
   repeated add/update calls, a non-trigger SKU, API outage, and insufficient
   stock. Confirm `_Аудит_API`, `Продажі!N/T`, the single packaging-cost write,
   and the stock-ledger warning.

## Rollback and boundaries

V87 remains deployed and needs no rollback; its update-only path is harmless and
continues to fail open when no row exists. If the new CRM hook must be disabled,
remove or gate the hook in the main CRM source and leave the 3D-P technical T
column intact for auditability. Do not manually edit T or create direct Sheet
rows outside the dedicated API. No live deployment, API setup write, CRM deployment, or production QA was
performed in this task; only the bounded read-only T emptiness confirmation was done.
