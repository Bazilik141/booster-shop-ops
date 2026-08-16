# CRM-004 — Packaging validation and resumable order update

Date: 2026-08-14

## Outcome

The `Продажі!AC274` failure is fixed in the local CRM/dashboard source. The apparent same packaging size could contain either Latin `x` or Cyrillic `х`; Google Sheets treats them as different values. The dashboard therefore tried to rewrite AC, whose old validation source rejected the other spelling, after some order fields had already been written.

The new source normalizes both legacy variants before it decides that packaging changed. It preserves the existing stable request ID across the dashboard spelling migration, so resubmitting an interrupted order continues only unfinished 3D-gift, component, or fixture writers.

## Scope

- Included: `CRM-004` packaging validation defect affecting `Продажі!AC` and the `Оновити_продаж!B12` dropdown, plus the retry identity needed for the current partial order update.
- Excluded: the separate new-SKU validation issue, sales values, formulas, stock, ledger rows, 3D-P source, deployment, and roadmap/status changes.

## Files touched

```text
crm/apps-script/Code.gs
dashboard/booster-dashboard.html
crm/apps-script/tests/crm-004-packaging-validation.test.mjs
tests/crm-004-dashboard-packaging-retry.test.mjs
```

## Implementation

- Declares one canonical packaging list using ASCII `x` and recognizes legacy `х`, curly apostrophes, and whitespace variants as the same value.
- Compares canonical packaging keys before writing `Продажі!AC`; a visually identical legacy value is no longer rewritten.
- Before an actual packaging change, repairs the two known validation targets before any sale, gift, component, or fixture write:
  - `Продажі!AC3:AC501`
  - `Оновити_продаж!B12`
- Adds `setupCrm004PackagingValidation()` for a one-time explicit repair. It changes validation rules only and returns `already_applied=true` on a repeat.
- Retains a pending browser request ID when its prior signature used the old Cyrillic-`х` dashboard value.

## Local verification

```text
Code.gs syntax parse: passed
CRM-004 packaging validation tests: passed
CRM-004 dashboard packaging retry tests: passed

CRM Apps Script suite: 11/11 passed
- 3D-P-019 fixture usage
- 3D-P sync journal
- SKU creation
- CRM-004 packaging validation
- CRM integrity check
- monthly profit/preorders
- mystery-box cost repair
- order components
- order items API
- qualified clients
- test-order purge

git diff --check: passed
```

The unrelated `tests/3d-p-013-dashboard-ui-regression.test.mjs` currently has two stale static expectations in the pre-existing dashboard baseline. Its inline script did compile; the failed assertions concern old 3D-P rendering and refresh-count patterns, not packaging or retry code.

## Deployment and live QA (owner)

1. Paste the updated `crm/apps-script/Code.gs` into the main CRM Apps Script project and publish a new Web App version.
2. In the Apps Script editor run `setupCrm004PackagingValidation()` once. Save the bounded JSON result; run it once more and expect `already_applied: true`.
3. Hard-refresh the local `dashboard/booster-dashboard.html`.
4. Reopen `OC-FOP-0318` without changing its form and press **Зберегти**. The retained request ID resumes only unfinished writers; it must not duplicate an existing 3D gift, component, or fixture.
5. Verify `Продажі!AC274` accepts the selected packaging and check the relevant component/gift/fixture ledgers contain one entry per intended form row.
6. Run the read-only `integrity_check`; any new problem code blocks acceptance.

## Rollback

- Republish the previous CRM Apps Script version and restore the prior dashboard file.
- The setup changes validation rules only; restore the previous validation configuration from Google Sheets version history if necessary.
- Do not delete any existing append-only ledger row. If live QA finds an unintended writer, investigate and use a reviewed compensating entry.

## Remaining evidence gap

All evidence above is local. The updated Apps Script has not been published, `setupCrm004PackagingValidation()` has not run in the live workbook, and the resumed `OC-FOP-0318` write has not been live-verified.
