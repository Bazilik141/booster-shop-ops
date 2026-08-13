# Codex Report — 3D-P-019: phase C fixture corrections and journal honesty

Date: 2026-08-11

## Scope

Implemented the authorised phase-C delta on the un-deployed phase-B working copy:

- WP1: fixture allocation faults now journal as `skipped_fixture_allocation`, while the CRM sale remains saved.
- WP2: `Оновити_продаж` can append signed `Коригування` ledger rows. The original row is not changed; corrections keep the original ledger unit cost, enforce one payer, and prevent a net quantity below zero.
- WP2: a correction recalculates the existing 3D-P sale row's `V/W` via optimistic `3dp_write` calls and journals a non-blocking warning if that remote write fails.
- WP3: separate preview and clear actions identify only uncovered historical 3D-P rows, then set `V=0` and `W=''`; reruns are idempotent.

The owner explicitly approved the minimal scope extension into the 3D-P API after contract discovery: its `3dp_write` now permits only owner-token writes to `Продажі!V/W` on CRM-linked rows, with schema checks, optimistic locking, and existing audit logging. No other 3D-P fields became writable.

`setup3dp019FixturePayerPhaseA()` was restored to the exact V102 content (after CRLF/LF normalization). The strict `Розхідники!O` validation previously staged inside it now runs from `setup3dp019FixtureUsagePhaseB()`, preserving the required validation without modifying the live Phase-A function.

## Files touched

```text
crm/apps-script/Code.gs
crm/apps-script/tests/3d-p-019-phase-a.test.mjs
crm/apps-script/tests/3d-p-019-fixture-usage.test.mjs
crm/apps-script/tests/3dp-sync-journal.test.mjs
3d-print/apps-script-3dp-api/Code.gs
3d-print/apps-script-3dp-api/tests/api.test.mjs
diagnostics/3D-P-019_phase-c_report_20260811.md
```

`crm/apps-script/SOURCE_STATE.md` was deliberately not changed: neither Apps Script project has been pasted, published, or verified live during this work.

## Local verification

```text
3D-P-019 Phase A setup tests passed
3D-P-019 fixture usage tests passed
3dp-sync-journal tests passed
CRM integrity-check tests passed
3D-P API test suite: ok=true, setup_idempotent=true, audit_rows=21
Apps Script syntax parse passed
git diff --check: passed
```

The journal tests prove mixed/missing payer and zero-unit fixture allocation faults produce `skipped_fixture_allocation`; they also prove WP3 preview makes no write, clear changes only uncovered rows, and a repeat reports `already_applied`. A forced partial remote write (`V` succeeds, `W` fails) now journals `V updated; W update failed`, rather than misleadingly reporting a total failure.

The fixture tests prove frozen-price corrections, two consecutive corrections, payer rejection, net-zero rejection, full reversal (`V=0`, `W=''`), and ledger-covered historical-row protection. The 3D-P API tests prove the narrow `V/W` write policy rejects Serhiy, non-CRM-linked rows, invalid payer values, and negative frozen values.

## Owner deployment and QA

No deployment, commit, push, Sheet write, or post-change live integrity check was performed.

1. In the 3D-P bound Apps Script project, create a named version, paste `3d-print/apps-script-3dp-api/Code.gs`, and publish the new Web App version.
2. In the CRM bound Apps Script project, create a named version, run `integrity_check` and retain the baseline result, paste `crm/apps-script/Code.gs`, and publish the new Web App version.
3. Run `setup3dp019FixturePayerPhaseA()`; expected result: `already_applied: true`.
4. Run `setup3dp019FixtureUsagePhaseB()` once. It creates/validates the ledger and applies the O-column list validation.
5. Run `preview3dp019HistoricalFixtureFrozenValues()` and inspect its counts. Only then run `clear3dp019HistoricalFixtureFrozenValues()`.
6. Run `integrity_check` again. Any new problem code is a deployment defect.
7. Create one reversible 3D-P sale with fixture usage, then submit a negative fixture correction through `Оновити_продаж`. Verify an appended `Коригування` row, net `Розхідники!H`, and updated 3D-P `Продажі!V/W`.

## Rollback

- Restore either Apps Script project to the named pre-deploy version and republish it.
- Do not delete ledger rows. Reverse a bad correction with a further approved correction.
- WP3 changes pre-cutover values. Keep the preview output and named Sheet version before running the write action; that is the recovery point for cleared `V/W` values.

## Known limitation

`V` remains the accepted order-level average fixture cost per 3D-P unit, not a per-SKU actual allocation. Phase C does not change that model.

## Remaining proof

Local tests do not prove the two published Web Apps, real Sheets validation, remote API permissions, or post-deploy integrity. Those require the owner-run steps above.
