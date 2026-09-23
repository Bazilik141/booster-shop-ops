# Codex Report — CRM-015: standalone 3D marketing writeoff and sales formula repair

Date: 2026-09-22

## Scope

Implemented the owner-approved accounting flow for 3D marketing writeoffs that
are not linked to an order, with optional active fixtures. Also diagnosed and
repaired the source defect that left six derived fields blank for the three
reported `Продажі` rows (`ACC-3D-PKM-110`, `BR-CHARM-100`,
`FIG-ONIX-500`).

At the time of the initial local report, no Apps Script source, Web App version,
dashboard, or workbook had been published or changed live. The subsequent
owner-reported actions are recorded below.

## Owner-reported live continuation (2026-09-22)

After the initial local report, the owner published the temporary 3D-P code in
the existing Web App as V35 (23:28 Kyiv). An owner-run preview matched only the
three expected date/SKU/order rows, sheet rows 2–4, with exactly 18 missing
formula cells and empty blocker/missing/ambiguous lists. The owner-run apply
returned `ok=true`, `rows_repaired=3`, `cells_repaired=18`; an immediate second
preview returned `cells_to_repair=0` and empty problem lists. These are supplied
API outputs, not an independent source export or dashboard screenshot. Manual
sales-tile QA later passed by owner report: all six fields display for all
three rows. The temporary route and HTML were then removed from the local
mirror, and the V35 paste artifact was removed locally as well. They remain
recoverable from candidate-branch history. The bound V35 project still had the
temporary route at that point; the clean republishing is recorded below.

On 2026-09-23 the owner reported replacing bound `Code.gs` with the prepared
`work/CRM-015_3dp_Code_final.gs` and deploying V36 at 11:05 Kyiv. The supplied
editor screenshot shows only `Код.gs` and `CatalogFifo.gs`; it is not a
byte-level source or active-version verification. No V36 API smoke or real
marketing writeoff has yet been reported. Main CRM publication and owner QA
for the new writeoff remain pending.

The owner then reported the dashboard CRM integrity check as `✓ OK` before
main-CRM publication. No raw API integrity payload was supplied. This is the
pre-publication gate only; a clean post-write check is still required after a
real marketing writeoff.

## Root cause

The published V34 export's `copyFormulaCells3dp_()` returned immediately for
row 2 and otherwise copied formulas only from the immediately preceding row.
The first real sale therefore received no formulas, and the next two rows copied
from an already blank predecessor. This exactly explains the missing
`Продажі!C/I/J/K/L/S` projections: product name, margin per unit, status,
Serhiy accrual, Booster Shop income, and period.

The fix generates the six formulas deterministically for the target row when
the current sales schema is present. The legacy pre-3D-P-015 schema retains its
previous copy behavior.

## Accounting contract

- One standalone marketing writeoff creates one `Продажі` row with mode
  `Маркетинг`; it is not duplicated in `Маркетингові_плюшки`.
- Manufactured stock is consumed oldest-first from immutable 3D FIFO batches.
- Serhiy accrual = quantity × (buyout price + Serhiy-paid fixture cost per unit).
- Booster Shop marketing expense = quantity × buyout price + all fixture cost.
- Margin per unit and Booster Shop income are zero for marketing rows.
- Optional fixtures must be active `Розхідники` records, use their stored payer,
  pass a current-stock guard, and receive an actual historical FIFO cost.
- The operation is not linked to an order. The expense is written as unlinked
  `Маркетинг`.
- An inventory difference is never auto-converted to marketing. Quantity and a
  reason must be explicitly entered. If the historical date is unknown, the UI
  tells the owner to use the inventory date and explain that choice in the note.

## Files touched

```text
3d-print/apps-script-3dp-api/Code.gs
3d-print/apps-script-3dp-api/CatalogFifo.gs
3d-print/apps-script-3dp-api/CRM-015.html
3d-print/apps-script-3dp-api/SOURCE_STATE.md
work/CRM-015_3dp_Code_from_V34.gs
work/CRM-015_3dp_Code_final.gs
crm/apps-script/Code.gs
crm/apps-script/SOURCE_STATE.md
dashboard/booster-dashboard.html
tests/crm-015-3dp-marketing-writeoff.test.mjs
diagnostics/CRM-015_3dp-marketing-writeoff-and-sales-formulas_report_20260922.md
```

The dirty worktree predates this task. No unrelated file was reset, removed, or
rewritten.

## Implementation and safety

- `3dp_marketing_writeoff` performs the 3D sale row and manufactured-batch FIFO
  allocation atomically under the 3D API lock, with snapshots and rollback.
- Stable `CRM015-...` request IDs make remote FIFO allocation, fixture ledger,
  and CRM expense retry-safe across partial cross-workbook failures.
- The dashboard deliberately preserves the request ID and entered fields after
  an error; a retry does not create a second writeoff.
- Clean bounded CRM integrity checks are required both before and after the CRM
  projection.
- Formula repair is owner-only, preview-first, SHA-256 fingerprint-gated, refuses
  manual-value conflicts or missing/duplicate target sales, verifies after apply,
  writes an audit entry, and restores complete row snapshots on failure. Targets
  must be exact date + SKU + order triples; an empty list is rejected.
- `CRM-015.html` and the `3dp_sales_formula_repair` route/action are temporary
  maintenance code. The UI is fixed to the three reported date/SKU/order triples, requires preview
  before apply, and stores neither URL nor token. Both temporary parts must be
  removed after verified live repair; the row-local formula seeding remains.
- Active component/fixture catalog reads now exclude `[ARCHIVED]` entries.
- No `!important`, `setTimeout`, `position:absolute/fixed`, or unexplained new
  magic-pixel override was added by this task.

## Source evidence

- Main CRM baseline: owner-supplied V185 export, normalized SHA-256
  `36aaa7218d1d13d1affe8593755a7fd813705b01701552240ac363f202c1231b`,
  byte-equal to the pre-task mirror after BOM/line-ending normalization.
- 3D-P baseline: owner-supplied V34 export, normalized SHA-256
  `ca72e8c6f09489fac47beb99a74161e3997e6897a4615a59746d8eaa165e11f6`.
  The pre-task mirror already had a preserved +33/-13 local divergence; CRM-015
  was built on top of it rather than discarding it.
- Deployment-safe 3D-P `Code.gs` input:
  `work/CRM-015_3dp_Code_from_V34.gs`, mechanically built from V34 with only
  CRM-015 additions (113 added / 1 removed line). The repository mirror also
  contains unpublished batch-draft work and must not be pasted wholesale for
  this task.
- Bounded credential-literal scans were clear. Secrets remain Script Properties.

## Verification

Focused command:

```powershell
node --test --test-isolation=none tests/crm-015-3dp-marketing-writeoff.test.mjs 3d-print/apps-script-3dp-api/tests/catalog-fifo.test.mjs dashboard/tests/crm-011-dashboard.test.mjs dashboard/tests/crm-011-r2-pass-a.test.mjs crm/apps-script/tests/crm-004-packaging-validation.test.mjs crm/apps-script/tests/packaging-002-dashboard-contract.test.mjs
```

Result: **30 tests passed, 0 failed**.

Continuation safety check (same command after exact-sale targeting): **32 tests
passed, 0 failed**. Both 3D-P paste candidates and the local 3D-P source also
passed JavaScript parsing. Codex performed no live write or publication.

Post-repair cleanup check: **31 focused tests passed, 0 failed**. The local
`Code.gs` and final paste candidate both omit the one-time repair action,
while retaining row-local formula seeding and marketing FIFO.

Additional gates passed:

- `new Function(...)` parse for both 3D-P `.gs` files and the main CRM
  `Code.gs`;
- `node --check` for the focused CRM-015 test;
- scoped `git diff --check`;
- temporary local visual-QA fixture confirmed absent.

Coverage includes the six formula columns, marketing financial rules, oldest
fixture FIFO layer calculation, insufficient-stock rejection, current-schema
and legacy-schema routing, full-row formula-repair rollback, request retry
markers, no inventory-difference autofill, dashboard JavaScript compilation,
and the temporary preview/apply tool.

Responsive source acceptance was checked for desktop (>1200 px), tablet
(801–1200 px), and mobile (<=800 px). The form uses the existing auto-fit grid;
fixture rows use the existing two-column writeoff layout and collapse to one
column on mobile. Long labels wrap, destructive fixture buttons have accessible
labels, and operation status is an `aria-live` region. A browser screenshot pass
was not produced because the managed in-app browser blocked the local `file://`
fixture; that policy was not bypassed.

Known unrelated baseline failures, not changed here:

- `dashboard/tests/dashboard-contract.test.mjs`: pre-existing missing category
  mapping for `Кейс / контейнер для зберігання`.
- legacy 3D API role tests expect older V23/V25/V29 export fixtures that are not
  present; the current owner-supplied evidence is V34.

## Idempotency

- Repeating the exact marketing request returns the existing FIFO allocation,
  fixture entries, and expense.
- Reusing its request ID with changed payload is rejected by the 3D allocation
  fingerprint and, when fixtures exist, by the CRM fixture fingerprint marker.
- Re-running formula repair after success previews zero cells and apply returns
  `already_applied: true`.

## Rollback

No live rollback is needed because nothing was deployed. During a live formula
repair failure the tool restores full row snapshots automatically. A deployed
marketing writeoff must be reversed through the existing explicit 3D FIFO
reversal/reconciliation path; rows must not be manually deleted.

## Owner deployment and QA gates

1. Run and save a clean main-CRM integrity check before publication.
2. Replace bound 3D-P `Code.gs` with
   `work/CRM-015_3dp_Code_from_V34.gs`; replace `CatalogFifo.gs` with the local
   `3d-print/apps-script-3dp-api/CatalogFifo.gs`. Publish a new 3D-P Web App
   version. Do not use the local 3D-P `Code.gs` mirror as the paste source.
3. Call `3dp_sales_formula_repair` through a direct owner-held API POST with
   `expected_sales` set to exactly `2026-09-06 / ACC-3D-PKM-110 / OC-FOP-0364`,
   `2026-09-19 / BR-CHARM-100 / OC-FOP-0389`, and
   `2026-09-22 / FIG-ONIX-500 / OC-FOP-0391`. Run preview and verify that only
   the intended rows/cells appear. Apply only with the returned fresh
   fingerprint, then preview again and require zero remaining cells. The
   `CRM-015.html` local-browser fetch has not been runtime proved, so use a
   direct HTTP client such as PowerShell for this step.
4. Confirm the dashboard `Продажі` table now shows all six derived fields for the
   three reported sales.
5. Replace bound 3D-P `Code.gs` with
   `work/CRM-015_3dp_Code_final.gs` after successful repair verification and
   republish the same 3D-P Web App deployment. This prepared candidate retains
   permanent formula seeding and omits the temporary repair route/action.
   Remove the temporary `CRM-015.html` locally.
6. Update the bound main CRM project with `Code.gs`; publish a new CRM Web App
   version.
7. Publish the canonical dashboard file. Test one real, owner-confirmed marketing
   writeoff using its actual quantity/date/reason and optional fixtures. Do not
   use an unexplained inventory difference as the quantity source.
8. Verify the new 3D `Продажі` row, manufactured FIFO allocation, fixture ledger,
   unlinked CRM `Маркетинг` expense, Serhiy accrual, and stock balance. Require a
   clean post-write CRM integrity check and clean `3dp_fifo_reconcile` result.

The temporary formula-repair route/action and `CRM-015.html` are versioned in
the interim `codex/crm-015-accounting-candidate` branch for traceability. They
must be removed from the bound project and repository in a follow-up change
after successful live repair; do not treat this branch as a permanent merged
state. Final publication, live writes, QA, and source-state version updates
remain owner-gated.
