# Codex Report — CRM/3D-P universal test-order cleanup and one-off archive

Date: 2026-08-13

## Status and deployment boundary

The owner reported publication of **CRM V115 at 20:16 Kyiv** and **3D-P V23 at 20:17 Kyiv**. The
immediate bounded `integrity_check` was clean: `problems=[]`, three 3D-P RRP comparisons,
`deferred=null`, `elapsed_ms=5982`.

This proves publication and bounded CRM integrity. It does **not** yet prove a live universal
dashboard-cleanup run. The earlier exact `MAN-FOP-0006` cleanup remains separately owner-verified
at CRM V114 / 3D-P V22. No commit, push, Notion write, or deployment action was performed by Codex.

## Universal dashboard action

The fixed `0005/0006` allow-list and its `DELETE <order>` confirmation are replaced by the owner
dashboard action `test_order_cleanup`.

1. The CRM scans `Продажі!AA` (the `Примітка` column) case-insensitively for either explicit marker:
   `тестове замовлення` or `тест замовлення`.
2. One marked sale line selects its complete order ID, so a mixed ordinary/3D test order is handled
   as one unit.
3. Before the first write it preflights every selected order across CRM Sales, component ledger,
   fixture ledger, 3D accounting, write-offs, Expenses/3D Marketing projection, and the remote
   3D-P Sales, stock-adjustment and marketing-gift ledgers.
4. On a successful preflight it clears only manual cells, preserving formulas, row numbers, audit
   history and journals; CRM current cost is recalculated after its matching write-offs are cleared.
5. It then rescans CRM and 3D-P. The dashboard shows the complete JSON report, keeps it in browser
   `localStorage`, and provides a **Copy report** button.

The action requires the existing CRM token, an in-page irreversible-action confirmation, and the
exact payload confirmation `CLEAN TEST ORDERS`. The remote 3D-P endpoint is owner-only and requires
`CLEAN TEST ORDER <order>` from CRM.

CRM and 3D-P are separate Apps Script projects, not one transaction. If an external timeout occurs
after one side writes, the response records the order/stage and a later dashboard run is idempotent;
it resumes rather than duplicating or deleting rows twice.

## Archive extraction

Archive files are repository-only and must never be pasted into Apps Script:

- `crm/apps-script/archive/one-off-migrations_20260813.gs`
- `3d-print/apps-script-3dp-api/archive/one-off-migrations_20260813.gs`

The moved function bodies were copied from the live candidates without rewriting them. Each archive
header states its source version (CRM V114 / 3D-P V22) and requires a fresh schema review before any
restoration.

CRM archive:

- 3D-P-019 Phase-A fixture-payer setup and historical frozen-fixture cleanup;
- one-off Mystery Box cost repair, MAN-FOP-0006 allocation repair, consumable-arrival repair and
  MAN-FOP-0005 usage-duplicate repair;
- `apiLtvReportLegacy_`: obsolete simple top-client LTV report, superseded by the active richer
  `ltv_report` route.

3D-P archive:

- completed 010, 015, 024, order-line-accounting and sales-profit-share setup/backfill paths,
  including their setup-only preflights and formula/analytics helpers;
- Addendum #2 one-time setup and the targeted availability-formula repair.

Five retired owner setup API routes were removed: `3dp_setup_3dp010`, `3dp_setup_3dp015`,
`3dp_setup_3dp024`, `3dp_setup_order_line_accounting`, and `3dp_setup_addendum2`.
`preview3dpApiSetup` and the active `setup3dpApi` remain. Active `salesOrderLine…` formula helpers
also remain because current order processing uses them.

The owner-supplied Trigger panels were considered before extraction: CRM has only
`runNightlyInventoryMaintenance`, `keepWarm`, and `runNewsPruneOnce`; 3D-P has no triggers. None
of those functions or the protected Telegram/news subsystem was changed.

## Size and local validation

| Live file | Before archive | After archive | Archive functions |
|---|---:|---:|---:|
| CRM `Code.gs` | 7,000 lines | 6,643 lines | 18 |
| 3D-P `Code.gs` | 3,051 lines | 2,384 lines | 36 |

- CRM cleanup mock: marked mixed order is selected, unmarked order is untouched, formulas survive,
  all 11 local/remote surfaces are counted, repeated execution is a no-op.
- 3D-P source VM parse and active cleanup-route contract pass; all five retired setup routes are
  absent, while `preview3dpApiSetup` remains.
- Dashboard inline syntax and cleanup contract pass.
- 18 other local test files pass.

Two pre-existing broad regression tests do not pass against the already-dirty dashboard/legacy test
harness and were not changed to hide that fact:

1. `tests/3d-p-013-dashboard-ui-regression.test.mjs` expects an older 3D information renderer and
   exactly two product reload calls; the current dashboard contains the owner’s newer collapsible
   information UI and a third reload path.
2. `tests/3d-p-010-crm-packaging-pull.test.mjs` evaluates `updateSaleStatus()` in isolation but does
   not inject its current `read3dp019FixtureFormLines_` dependency.

Tests solely for archived one-off entry points were removed from the active suite. Active fixture,
sync, order-component, integrity and dashboard contract coverage remains.

## Remaining owner QA

1. V115/V23 are published and their immediate `integrity_check` is clean. Before the first actual
   cleanup, create named Sheet versions for data recovery and hard-refresh the dashboard.
2. Add the marker in `Примітка` to a disposable test order, open **Тестові замовлення**, and run the
   dashboard action. Read/copy the report before creating another test.
3. Run `integrity_check` after cleanup. Required result: `clean=true`, `problems=[]`,
   `deferred=null`.
4. Confirm one ordinary order save/update and one `/digest` → `/post` news flow; the latter is a
   regression check for the protected subsystem.

## Rollback

Republish the currently deployed CRM V115 and 3D-P V23 source, or restore the named Sheet version
if a test cleanup itself needs data recovery. The archive extraction is code-only; its rollback is a
republish of the prior script version.
