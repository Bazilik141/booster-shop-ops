# CRM OC-FOP-0321 — writeoff capacity diagnostic

Date: 2026-08-16

## Outcome

Prepared a local Apps Script correction for the partial order update. It uses
the existing blank rows of `Списання` through the sheet's actual grid limit;
it does not insert rows or alter any existing record.

## Live bounded evidence

- `Продажі` row 282 (`OC-FOP-0321`) already contains the submitted status,
  TTN, and packaging changes, but has no submitted component cost.
- Searches of `Використання_компонентів!A1:O1000` and
  `Списання!A1:L216` found zero records for this order. The failure occurred
  before the component ledger or writeoff writer began, so retrying does not
  need a data cleanup.
- `Списання` has 216 grid rows. IDs occupy rows 197–201 (`WRT-0195` through
  `WRT-0199`), while rows 202–216 are blank.
- The active local writer checked only rows through 201. For this form's two
  SKU components it selected row 202, then rejected `202 + 2 - 1 > 201`.

## Local correction

- Added `writeoffLastWritableRow_()` and `nextWriteoffRow_()` in
  `crm/apps-script/Code.gs`. They take the real sheet grid (`getMaxRows()`) as
  the writable limit, with 201 retained as the legacy minimum.
- `appendOrderComponents_()` now uses that bounded helper. Its existing
  `ensureComponentWriteoffFormulaRows_()` still fills the standard writeoff
  formulas for the two new rows.
- Added regression coverage in `crm/apps-script/tests/order-components.test.mjs`:
  two records fit at rows 202–203 of a 216-row grid; a genuinely full grid is
  still rejected.

## Expected retry after publication

Save the unchanged `OC-FOP-0321` form once, retaining its existing request ID.
Because no component, fixture, 3D gift, or writeoff record was created by the
failed call, the retry should append exactly four component-ledger entries and
two SKU writeoffs (rows 202–203). It will not duplicate a 3D gift, fixture, or
previous component; this order has no 3D gift in the submitted form.

## Verification

- `new Function(Code.gs)` syntax parse: passed.
- Focused `order-components.test.mjs`: passed.
- Full CRM Apps Script suite: all 13 test files passed.
- `git diff --check` for the scoped source and test files: passed.

## Superseded capacity note

The originally scoped correction used the 15 existing blank grid rows only.
The owner subsequently authorized automatic CRM row capacity. Its broader
replacement is documented in `CRM_row-capacity_autogrow_report_20260816.md`;
after publication, `Списання` receives a 10-row background refill before the
remaining capacity becomes low and an emergency writer fallback if needed.

## Boundaries and remaining gate

- This is a local mirror change only; it has not been pasted or published to
  the live Apps Script Web App.
- The mirror has unrelated current worktree changes. Merge only this narrow
  helper and `appendOrderComponents_()` callsite into the live V118 source;
  do not replace live `Code.gs` wholesale.
- The source mirror change is still local only; no row, trigger, or Web App has
  been changed live in this session.
