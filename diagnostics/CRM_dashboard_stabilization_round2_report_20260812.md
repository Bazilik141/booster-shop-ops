# CRM/dashboard stabilization round 2 — diagnostic report

Date: 2026-08-12  
Executor: Codex · model=Sol · effort=xhigh  
Status: local candidate ready; not deployed; no live writes performed

## Scope

- Restore and protect Mystery Box COGS for `OC-FOP-0309`, `OC-FOP-0312`, and `OLX-FOP-0050`.
- Replace the Clients top-10 view with the owner-approved OR criteria and sortable metrics.
- Make 3D information blocks independently collapsible and lazy-load heavy tables.
- Make the calculator draft state visually explicit and reduce draft read calls.
- Allow non-fixture components to remain order-level while fixtures stay line-targeted.
- Retry exactly one confirmed HTTP 404 from the 3D-P Apps Script route.
- Overlay the Warehouse 3D rows with 3D-P nomenclature names and current availability.

## Live read-only evidence

Source: bounded Google Sheets reads against the main CRM workbook. No full workbook export and no write were used.

| Order | CRM sale row(s) | Current MBX P/RRO unit | Current MBX management unit | Linked writeoff P/RRO total | Linked writeoff management total | Expected repaired management unit |
|---|---:|---:|---:|---:|---:|---:|
| OC-FOP-0309 | 257 | 0.00 | 3.26 | 647.35 | 686.18 | 689.44 |
| OC-FOP-0312 | 262 (qty 2) | 0.00 | 1.05 | 1,474.99 | 1,563.48 | 782.79 |
| OLX-FOP-0050 | 267 | 0.00 | 2.09 | 734.48 | 778.54 | 780.63 |

The current values contain only the auto-consumables. The booster writeoff rows still exist, so the defect is a projection regression, not missing inventory history.
Bounded searches in `Витрати!A:M` found no direct expense linked to any of the three orders, so the expected repaired values above contain no hidden order-expense addition.

## Root causes and corrections

### Mystery Box cost

`apiUpdateSaleWithComponents_()` calls `fixSaleCostForRow_()` during ordinary order edits. A Mystery Box SKU has no FIFO stock lot, so the fallback recalculation replaced the linked-writeoff cost with zero plus auto-consumables. The correction makes linked Mystery Box writeoffs the frozen source of truth and adds:

- `previewMysteryBoxCostRegressionRepair()` — read-only preview;
- `repairMysteryBoxCostRegression()` — locked, exact three-order repair;
- repeat-run idempotency evidence in the returned report.

### Components

The ledger and allocation engine already supported an unassigned bucket and distributed it using order row weights. Only dashboard/API validation incorrectly required a row target for every component. The target is now optional for general components and remains mandatory for fixture lines.
Existing component-ledger amounts are reapplied after every base-cost refresh, so a later status or TTN edit cannot silently remove a previously recorded component cost.

### 3D-P HTTP 404

The observed error is an invalid JSON response with HTTP 404. That status proves the Web App action was not executed. The CRM bridge retries that exact condition once after 300 ms. It does not retry invalid JSON with a success code or other uncertain mutation outcomes.

### Clients

The API now returns all clients matching any approved criterion:

- orders > 1;
- spend in the last 60 days > UAH 1,500;
- all-time aggregate margin > 40%.

Default order is 60-day spend descending. The dashboard supports sorting by name, channel, orders, units, 60-day spend, all-time spend, profit, and margin.

### 3D information and calculator

The previous information action read sales, payouts, marketing freebies, and fixtures serially before rendering. The dashboard now renders seven collapsed blocks immediately and fetches only the opened heavy table. The draft endpoint now reads five stored values in one bounded range call, and the dashboard caches the result per SKU for the current session.

### Warehouse

The CRM catalog placeholder name and CRM stock formula are not authoritative for 3D inventory. Warehouse renders CRM rows immediately, then overlays matching 3D-P SKU name, type, and `Наявно зараз, шт` from the existing `3dp_skus` endpoint. Failure remains fail-open and leaves the CRM view visible.

## Validation

- Full local test pass: 12/12 Node test files.
- Dashboard inline JavaScript parses in a VM.
- `git diff --check`: clean.
- Responsive browser QA: 1440x900, 1024x768, 390x844; no page-level horizontal overflow.
- Seven 3D information blocks render collapsed and toggle independently.
- Startup failure found during browser QA was fixed: CRM token prompting is now lazy and cannot leave state declarations in the temporal dead zone.
- No new `!important`, `position:absolute/fixed`, or `setTimeout` workaround was introduced by this round.

## Deployment and owner QA gates

1. Publish 3D-P candidate as V17.
2. Publish CRM candidate as V108.
3. Run and record `previewMysteryBoxCostRegressionRepair()`.
4. If the preview contains the three expected orders/values, run `repairMysteryBoxCostRegression()` once.
5. Repeat `repairMysteryBoxCostRegression()`; expected: `orders_changed=0`, `already_applied=true`.
6. Run the CRM integrity check and record its bounded output.
7. Hard-refresh the canonical dashboard and manually verify the six owner-reported scenarios.

Rollback: restore the previous Apps Script deployments (3D-P V16 and CRM V107). The repair writes only the documented COGS/audit cells for the three exact Mystery Box rows; if code rollback is required after repair, their restored values remain data-correct because they are derived from existing writeoffs.
