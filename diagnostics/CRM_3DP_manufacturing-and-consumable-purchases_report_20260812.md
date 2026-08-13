# Codex Report — CRM/3D-P: manufacturing, formula-safe sync, and consumable purchases

Date: 2026-08-12

## Scope

Implemented the owner-requested follow-up to the V17/V108 QA round:

- add a calculator action that records a manufactured 3D batch in the append-only print log;
- fix the row-268 `FORMULA_CELL` failure without weakening the general formula-cell guard;
- add a safe retry that runs only the CRM-to-3D-P sync and never repeats component or fixture writers;
- add dashboard creation and status management for consumable/fixture purchases, including new catalog entries;
- add an exact preview/apply repair for the owner-confirmed arrived consumables;
- preserve the already-live Mystery Box COGS repair and its idempotency protection.

## Verified live baseline

- 3D-P Web App V17, owner-reported published at 18:07 Kyiv.
- CRM Web App V108, owner-reported published at 18:08 Kyiv.
- The Mystery Box repair preview/apply/repeat succeeded for `OC-FOP-0309`, `OC-FOP-0312`, and `OLX-FOP-0050`.
- The repeat returned `orders_changed=0` and `already_applied=true`.
- The post-repair CRM integrity check returned `clean=true`, `problems=[]`, and two completed 3D-P RRP comparisons.

## Root causes

### Row 268: `FORMULA_CELL`

The 3D-P `Продажі` sheet prepares new rows with a formula in column F. CRM intentionally sends a frozen production cost in F when creating the corresponding sale row. The API rejected the prepared target before the first write because its generic formula guard treated the canonical prepared F formula like an arbitrary protected formula.

The correction permits replacement only when all conditions are true:

1. target sheet is `Продажі`;
2. target column is F;
3. the existing formula is exactly the canonical row-local production-cost formula.

All other formula cells still return `FORMULA_CELL`. If a later column write fails, rollback restores the original F formula rather than a literal value.

### Consumables shown as still travelling

The dashboard's former purchase editor writes `Закупки` (merchandise lots). Consumables and fixtures are sourced from `Витрати`, where `Розхідники!F:G` count statuses `На складі` and `Їде`. The owner-updated merchandise form therefore could not change these consumable rows.

A bounded live read found 16 `Витрати` rows still marked `Їде`: rows 11, 18, 19, 20, 21, 22, 34, 39, 40, 41, 42, 43, 44, 47, 49, and 50. The repair is restricted to the 13 exact owner-confirmed names and changes only J from `Їде` to `На складі`.

## Files touched

```text
3d-print/apps-script-3dp-api/Code.gs
3d-print/apps-script-3dp-api/tests/api.test.mjs
crm/apps-script/Code.gs
crm/apps-script/SOURCE_STATE.md
crm/apps-script/tests/order-components.test.mjs
dashboard/booster-dashboard.html
dashboard/tests/dashboard-contract.test.mjs
diagnostics/CRM_3DP_manufacturing-and-consumable-purchases_report_20260812.md
```

## Implementation details

### Manufacture batch

`3dp_manufacture_batch` validates an active SKU, positive integer quantity, defect count, material usage, print time, operator, and stable dashboard request ID. It appends to `Друк-лог`; the existing `Наявність` formulas calculate stock from that ledger. A repeat with the same request ID is idempotent.

### Consumable and fixture purchases

The CRM API now supports:

- `add_consumable_purchase` for an existing or new catalog entry;
- `update_consumable_purchase` with optimistic status checking;
- statuses `Замовлено`, `Їде`, `На складі`, and `Скасовано`;
- fixture payer validation (`власник` or `Сергій`);
- formula-backed stock and cost fields without literal writes over formula columns;
- pre/post integrity checks for new `Розхідники` catalog rows;
- cleanup of newly created expense/catalog rows if the operation or integrity gate fails.

The dashboard shows open consumable purchases, lets the owner confirm arrival, and exposes a new-purchase form for existing or new names.

### Safe 3D-P retry

The order editor now has `Повторити тільки 3D-P`. It reads the selected CRM order and reruns only `sync3dpPackagingCost_`. It does not call component or fixture appenders. Accounting snapshots remain idempotent by their existing fingerprint.

## Local verification

All 12 local tests passed:

```text
3D-P-019 fixture usage tests passed
3D-P-019 Phase A setup tests passed
3dp-sync-journal tests passed
CRM catalog SKU create tests passed
CRM integrity-check tests passed
Mystery Box cost repair tests passed
CRM order component tests passed
CRM-006-ORDER API tests passed
Qualified clients report tests passed
3D-P API tests passed (formula guard, manufacturing, idempotency)
3dp-sync-journal dashboard static tests passed
Dashboard syntax and contract tests passed
```

`git diff --check` passed. Browser visual QA of the local `file://` dashboard was not completed because the in-app browser security policy blocks local file URLs. The static inline-JavaScript parser and dashboard contract suite passed; the owner still needs the normal manual dashboard QA after deployment.

## Idempotency

- Manufacturing: stable request ID in the print-log note prevents duplicate batches.
- 3D-P-only retry: existing remote sale matching plus accounting fingerprint prevents duplicate accounting snapshots.
- Arrival repair: repeat finds zero `Їде` rows in the exact allowlist and returns `already_applied=true`.
- Purchase status update: same status returns `already_applied=true`; stale expected status fails closed.

## Rollback

No live write or deployment was performed by Codex. To roll back a candidate before owner deployment, restore the listed mirror/dashboard files from Git. To roll back after deployment, publish the prior owner-held V17 3D-P source and V108 CRM source as new Web App versions; the dashboard is the canonical local HTML file.

The arrival repair changes existing sheet data. Before running it, retain its preview output. A data rollback is to set only the reported `Витрати!J` rows back from `На складі` to `Їде`; do not apply this rollback to any row whose status was changed again after the repair.

## Owner deployment and post-deploy QA

1. Publish the 3D-P candidate as Web App V18.
2. Publish the CRM candidate as Web App V109.
3. In CRM Apps Script, run `previewConsumableArrivalStatusRepair()` and confirm `rows_to_update=16` with only the expected names/rows.
4. Run `repairConsumableArrivalStatus()` once; repeat it and confirm `rows_updated=0`, `already_applied=true`.
5. Run the dashboard CRM integrity check and require `problems=[]` with no new problem code.
6. Reopen `MAN-FOP-0005` and click `Повторити тільки 3D-P`. Do not add the protectors or fixture again. Confirm row 268 syncs and the success message explicitly says the component/fixture writers were not repeated.
7. In the 3D calculator, use a small real batch and confirm the print-log entry, stock delta of `quantity - defects`, and idempotent retry behavior.
8. In `Розхідники`, create or select a test purchase, move it through `Замовлено` -> `Їде` -> `На складі`, and confirm the `Розхідники` quantities follow the existing formulas.

## Side effects and risks

- CRM and Google Sheets logic are risky-zone changes; production proof remains owner QA.
- Adding a brand-new consumable invokes two bounded integrity checks and can take tens of seconds. The dashboard disables the save button and shows progress to prevent click spam.
- `Розхідники` still uses the existing current-unit-cost model, not FIFO lots. A new purchase updates the current weighted formula behavior already defined by the sheet; this change does not introduce a new lot-costing model.
- The arrival repair is intentionally exact and one-time; it does not infer arrival from dates, tracking, or other names.
