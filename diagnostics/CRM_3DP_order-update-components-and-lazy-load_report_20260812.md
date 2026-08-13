# CRM/3D-P order-line accounting and dashboard workflow — local report

Date: 2026-08-12

## Outcome

The local post-V105/V13 candidate implements owner-controlled per-line 3D accounting in the
canonical dashboard. An existing order can receive up to ten line-targeted fixtures and up to ten
line-targeted fulfillment components. Every 3D line has a manual `Продаж` checkbox; clearing it
selects `Маркетинг`. Price does not switch or reject the selected mode.

This is local source only. It has not been deployed and has not written to either live workbook.

## Verified starting evidence

- Owner-reported deployments: 3D-P V13 and CRM V105 at 12:07 Kyiv.
- `setupOrderComponentUsage()` completed with 18 consumable formulas updated.
- The post-deploy bounded CRM integrity check was clean and compared two 3D-P RRP rows.
- Owner QA proved that V105 still had a single fixture row, no save-progress feedback, no 3D COGS
  projection in Orders, and no Marketing column.
- The existing `MAN-FOP-0005` evidence contains one 3D line, so its legacy untargeted fixture row can
  be backfilled safely to that CRM row. Ambiguous multi-3D historical orders are not guessed.

## Accounting rules implemented

### Sale mode

- Serhiy payout = production cost + Serhiy-paid fixtures + 50% of the remaining margin after both
  fixture payer buckets and packaging are deducted.
- Owner-paid fixtures are deducted before the 50/50 split and then added to CRM management COGS.
- Current Sheets CRM PRRO COGS remains `0` for the 3D financial projection by owner decision.

### Marketing mode

- Serhiy payout = frozen Booster Shop buyout price + Serhiy-paid fixtures.
- CRM management COGS and Marketing amount = buyout price + owner-paid fixtures +
  Serhiy-paid fixtures.
- The linked `Витрати` projection is marked as derived and excluded from direct-order expense
  recalculation, preventing a second subtraction from profit.

This current-CRM PRRO decision must be revisited during NCRM migration because taxes are based on
income rather than profit.

## Implementation

### 3D-P Apps Script

- Adds frozen Sales columns X:AA for CRM mode, owner fixture/unit, Serhiy fixture/unit, and buyout/unit.
- Adds `preview3dpOrderLineAccounting()` and idempotent `setup3dpOrderLineAccounting()`.
- Migrates legacy V/W values without changing total stock: Sale rows remain sales; Marketing rows
  move into the existing marketing-stock formula path.
- Rebuilds per-line margin, Serhiy accrual, and Booster Shop income formulas from frozen values.
- Existing frozen-value updates report each column state separately, including partial writes.
- CRM line packaging is converted from the CRM line total to the per-unit value required by 3D-P
  Sales column G, avoiding a second multiplication by quantity in 3D-P formulas.

### CRM Apps Script

- Adds line targets to `Використання_фурнітури` and `Використання_компонентів`.
- Adds append-only `3D_облік_замовлень` snapshots and projects the latest snapshot into CRM COGS.
- Keeps mixed fixture payers separate per targeted 3D line.
- Applies general components after the 3D base-cost projection so a component targeted at a 3D line
  cannot be overwritten. Repeated application subtracts the prior audited overlay before adding the
  current ledger total.
- Restores the existing `Витрати!L3:M3` array formulas after clearing only verified matching literal
  blockers; unexpected literals stop setup before destructive repair.
- Adds Marketing totals to order summary and order-item API responses.
- Preserves the legacy sheet-form/order-level V/W path for compatibility and honest partial-write
  journal details.

### Dashboard

- Adds a repeatable `Додати фурнітуру` control, maximum ten rows, each tied to a selected 3D order line.
- Components are repeatable, maximum ten rows, and tied to a selected order line.
- Shows one manual Sale/Marketing checkbox per 3D line.
- Disables `Зберегти` immediately, shows `Зберігаю…`, ignores repeated clicks, and reports structured
  partial results instead of oscillating success/error messages.
- Adds Marketing to collapsed and expanded Orders tables.

## Local verification

All discovered test files passed in one run:

- CRM: Phase A, fixture usage/corrections, 3D sync journal, SKU creation, integrity check,
  order components, and order items.
- 3D-P API: setup idempotency, 11 read actions, guarded writes, archive/restore, and 23 audit rows.
- Dashboard: inline JavaScript parse, lazy-load/static sync contract, manual mode, multi-fixture,
  component targeting, busy-state, and Marketing contracts.
- `git diff --check` passed.

These tests are local/stubbed. They are not proof of Apps Script deployment, workbook migration,
or end-to-end live recalculation.

## V14 setup incident and correction

The owner published 3D-P V14 at 16:25 Kyiv. The first setup attempt stopped at 16:26 with a Google
Sheets out-of-bounds range error on the new Sales header write. The live grid had fewer than the 27
columns required for AA; the original migration validated the logical anchors but did not expand the
physical sheet grid.

The corrective local candidate now:

- reports current/required/missing column counts in the preview log;
- inserts only the missing trailing columns through AA;
- takes the migration snapshot after expansion;
- restores data and removes those inserted columns if any later setup step fails;
- reports `columns_added` in the bounded setup result;
- is regression-tested from a 26-column Sales grid and on an idempotent second run.

The failed V14 attempt occurred before row/formula migration. The corrected setup also accepts any
approved X:Z headers that might have survived the failed attempt and still refuses unexpected data.

V15 then completed the migration, but its second run rewrote only Availability E:F. A bounded live
read of `Наявність!A1:F3` proved Google had persisted the intended formulas while automatically
adding single quotes around the referenced sheet names. The post-V15 candidate canonicalizes those
quotes before comparison. Regression coverage now replaces the generated formulas with their exact
Google-persisted quoted shape before asserting that the repeated setup is a no-op.

The owner published V16 at 16:37 and ran the corrected setup at 16:38. It returned `ok=true`,
`already_applied=true`, `columns_added=0`, and `changes=[]`. The 3D-P migration gate is therefore
complete; CRM deployment/setup and end-to-end dashboard QA remain separate gates.

CRM V106 was published at 16:45. Its first setup created the accounting/component/fixture schemas,
backfilled one unambiguous fixture target, and removed two verified Expense L/M literals. The bounded
integrity check was clean with two remote RRP comparisons. The repeat incorrectly reported 52 more
blockers because Apps Script `getValues()` returns ARRAYFORMULA spill results even though those cells
have no user-entered content.

A bounded connector read confirmed the live state is healthy: L3/M3 retain the intended formulas,
and inspected lower L/M cells contain effective spill values with no `userEnteredValue`. The local
post-V106 correction returns immediately for a healthy spill. When anchors really show `#REF!`, it
temporarily clears only L3:M3, flushes the sheet so calculated spill values disappear, validates and
clears only surviving matching literals, and restores the formulas. Unexpected literals restore the
prior anchors and stop setup. Tests cover two real blockers followed by an idempotent healthy repeat.

The owner published CRM V107 at 16:54. At 16:55 its corrected setup returned every
schema/formula/backfill counter at zero and `already_applied=true`. The CRM deployment/setup gate is
therefore closed. The earlier clean post-migration integrity result remains applicable because V107
changed only setup spill detection and the V107 setup performed no workbook mutation.

## Owner deployment and QA gates

1. Publish 3D-P Apps Script after V13.
2. Run `preview3dpOrderLineAccounting()` and review the bounded JSON.
3. Run `setup3dpOrderLineAccounting()` and record its JSON. Repeat once; the second result must be
   `already_applied=true`.
4. Publish CRM Apps Script after V105.
5. Run `setup3dpOrderLineAccountingCRM()` and record its JSON. Repeat once; the second result must be
   `already_applied=true`.
6. Run the bounded CRM integrity check. Any new problem code blocks live use.
7. Hard-refresh the canonical dashboard.
8. Re-save `MAN-FOP-0005` once, explicitly choosing Sale or Marketing, and verify the CRM row cost,
   Marketing value, 3D accounting ledger, fixture targets, 3D-P frozen X:AA values, and sync journal.
9. Test a second disposable order containing two different 3D lines and two fixture rows to prove
   line isolation. Do not infer this from the single-line order.

## Rollback

- Republish the prior Apps Script versions and restore the prior dashboard file.
- Use Google Sheets version history to restore setup-added headers/formulas if the migration result
  or post-integrity check is not clean.
- Do not delete append-only ledger rows. Correct a bad live entry with a reviewed compensating entry.
- Existing Marketing expense projections can be neutralized by saving the same 3D line as Sale; the
  linked projection is retained with amount zero for auditability.
