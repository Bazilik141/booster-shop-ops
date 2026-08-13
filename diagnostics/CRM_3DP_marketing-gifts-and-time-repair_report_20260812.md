# Codex Report — CRM/3D-P QA: marketing gifts, print time, and duplicate repair

Date: 2026-08-12

## Scope

Investigated the owner QA after 3D-P V18 / CRM V109 and implemented the bounded corrections:

- component rows added from the order-update form are always marketing gifts;
- their management cost is shown in the Orders `Marketing` column, distributed over all order lines, while customer payment is unchanged and profit cost is not counted twice;
- print-time values written as decimal hours cannot retain a duration number format, and the dashboard can decode the already observed legacy Date serialization;
- long order updates disable repeat submission, show elapsed time, retain the same request ID across a timeout/reload, and expose the 3D-P-only retry only after an actual partial result;
- the repeated packaging note is no longer appended on an unchanged save;
- an append-only dry-run/apply repair is prepared for the verified `MAN-FOP-0005` duplicates.

## Live evidence used

Bounded Google Sheets reads, not full-workbook exports, established:

- `Номенклатура` row for `BR-CHARM-100`: stored/displayed print time was `3:10:00`, while the calculator draft is 4.75 hours for 36 units (0.131944... decimal hours per unit).
- `Використання_компонентів`: two positive `ACC-002` rows for `MAN-FOP-0005`, quantity 1 each.
- `Використання_фурнітури`: four positive rows targeting CRM row 268, total quantity 5 for owner-paid `FUR-BR-COLOR-MIX`.
- `Продажі` rows 268–269: component cost is already inside management COGS/profit, while the dashboard Marketing projection remained zero; row 268 also had the same packaging note repeated.

The owner-reported arrival repair changed exactly 16 rows, its repeat returned `already_applied=true`, and the post-change integrity check returned `clean=true`, `problems=[]`, `compared=2`.

## Files touched

```text
3d-print/apps-script-3dp-api/Code.gs
3d-print/apps-script-3dp-api/tests/api.test.mjs
crm/apps-script/Code.gs
crm/apps-script/SOURCE_STATE.md
crm/apps-script/tests/order-components.test.mjs
dashboard/booster-dashboard.html
dashboard/tests/dashboard-contract.test.mjs
diagnostics/CRM_3DP_marketing-gifts-and-time-repair_report_20260812.md
```

## Duplicate repair contract

Public functions:

```javascript
previewManFop0005UsageDuplicateRepair()
repairManFop0005UsageDuplicates()
```

The preview must be run first. Against the verified live state it should report:

```text
before.component_qty = 2
before.fixture_qty = 5
desired.component_qty = 1
desired.fixture_qty = 1
adjustments.component_qty = -1
adjustments.fixture_qty = -4
dry_run = true
would_change = true
```

The repair does not delete ledger history. It appends compensating negative usage/write-off rows,
recalculates the linked order, restores stock through the existing formulas, and is idempotent from
net quantities. Its explicit assumption is: keep the older blind-packet gift, one `ACC-002`, and one
owner-paid `FUR-BR-COLOR-MIX` targeted to CRM row 268.

## Local verification

```text
node --test 3d-print/apps-script-3dp-api/tests/api.test.mjs crm/apps-script/tests/*.test.mjs dashboard/tests/*.test.mjs
tests: 12
pass: 12
fail: 0
```

`git diff --check` passed for all scoped source and test files.

UI signature scan found no newly added `!important` or `position:absolute/fixed`. The existing
candidate contains one bounded 400 ms `setTimeout` for the previously approved transient 3D-P 404
retry; the new save progress uses `setInterval` only while the request is pending and clears it before
success/partial rendering.

## Deployment and owner QA

1. Publish 3D-P candidate as V19.
2. Run `setup3dp024()` once. It should normalize `Номенклатура!G` and `Друк-лог!D` to decimal-hour number format; repeat must be `already_applied=true`.
3. Publish CRM candidate as V110 and reopen the canonical local dashboard.
4. Run only `previewManFop0005UsageDuplicateRepair()` and compare its bounded output with the contract above.
5. After owner confirms the one-ACC/one-fixture assumption, run `repairManFop0005UsageDuplicates()` once, repeat it for idempotency, then run the CRM integrity check.
6. Reopen `MAN-FOP-0005`: Marketing should include the retained gift costs, customer payment must remain 173 UAH, and the print-time warning for `BR-CHARM-100` must disappear.

## Risks and rollback

- Deployment and live repair remain owner actions; this repository edit is not deployment proof.
- The repair intentionally requires owner confirmation because the desired fixture quantity is a business fact inferred from the QA action, not from the ledger itself.
- Append-only compensations are recoverable with opposite compensating entries; no ledger row deletion is used.
- Stable request IDs prevent duplicate component/fixture writes only for the same unchanged form payload. A materially changed payload correctly receives a new request ID.

## Live result — 21:11–21:18 Kyiv

- 3D-P V19 and CRM V110 were owner-published.
- Duplicate preview matched the contract exactly: component `2→1`, fixture `5→1`, note rows 2.
- Apply appended one compensating component/write-off (`CMP-USE-00004`, `WRT-0184`) and one
  fixture correction totaling `-11.83`; remote accounting synchronized one row with no failures.
- Integrity returned `clean=true`, `problems=[]`, and two successful 3D-P RRP comparisons.

## Follow-up QA — one stale frozen field

The next deliberate test added another `ACC-002` gift and `FUR-BR-CARB` fixture. The CRM writers
completed once, then remote synchronization reported the honest partial detail:

```text
V updated; W current; X current; Y failed; cause: STALE_WRITE
```

The dashboard's retry-only path healed it without re-appending either ledger. Bounded live reads now
show 3D-P row 2 / CRM row 268 as `V=16.03`, `W=власник`, `X=Продаж`, `Y=16.03`, `Z=0`, `AA=20`.
Orders shows Marketing 141 UAH, consistent with two intentional `ACC-002` gifts plus the older small
gift. The undeployed CRM V111 candidate refreshes the exact remote row and retries one still-different
frozen field once when it receives `STALE_WRITE`, so the user should not need the visible recovery
button for this transient snapshot race.
