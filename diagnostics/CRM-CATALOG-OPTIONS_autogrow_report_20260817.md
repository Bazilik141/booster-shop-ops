# CRM-CATALOG-OPTIONS — auto-growing reference lists implementation report

Date: 2026-08-17

## Outcome

Implemented a separate catalog-reference capacity layer. It complements the
existing row-capacity feature but does not merge unrelated responsibilities:

- row capacity remains responsible for append-only data rows;
- catalog-option capacity owns `Налаштування` option columns and their
  dependent `Товари` data validations.

No Google Sheet, Apps Script deployment, Web App version, trigger, SKU, or
production CRM value was changed locally.

## Files changed

- `crm/apps-script/Code.gs`
- `crm/apps-script/tests/catalog-sku-create.test.mjs`
- `crm/apps-script/tests/integrity-check.test.mjs`
- `crm/apps-script/SOURCE_STATE.md`

## Implemented behavior

`CRM_CATALOG_OPTION_CONFIG_` is the only registry for the four controlled
fields: brand, language, set, and format. It defines each Settings column, its
matching `Товари` column, and the existing strictness behavior.

When `add_sku` receives a new option:

1. It uses all existing blank cells in that Settings column. For the current
   CRM, this includes rows below the old fixed limits through row 60.
2. If that whole Settings grid is full, it appends 50 rows at the bottom and
   copies only reference-list format/validation structure.
3. It repairs all four `Товари` validation columns to point at the full current
   Settings grid before writing the new SKU. The Set validation remains strict;
   the existing behavior of the other three columns is preserved.
4. It writes the new option and SKU/RRP through the existing locked,
   idempotent `add_sku` transaction. A failure after planning still clears any
   newly written option value and SKU/RRP values; preallocated blank rows are
   harmless capacity.

The public Sheet menu now includes **Booster CRM → Оновити довідники SKU**.
This one-time migration repairs the current validation rules, runs the bounded
integrity check before and after, and is idempotent on repeats. Completion is a
non-blocking spreadsheet toast, not a modal dialog that can leave the execution
shown as running until the owner acknowledges it.

`integrity_check` now detects duplicate option text, a missing managed option
column, and validation-range drift between `Налаштування` and `Товари`. Its
fast path verifies rule type across every managed cell and its exact Settings
source at the first, middle, and final rows of each column; this avoids hundreds
of expensive Range-metadata lookups after a successful migration.

## Regression coverage

- New Set after the legacy `AD4:AD44` limit, using existing blank Settings rows.
- New Brand, Language, and Format after each former fixed limit.
- A physically full Settings grid that must append 50 rows before accepting a
  new Set, including rebuilt validation source range.
- Idempotent menu migration of all four validation columns.
- Integrity detection for a drifted validation range and duplicate option text.
- A clean validation grid performs 12 exact source checks (three per field),
  instead of one per cell across `Товари!D:G`; a drift at the middle probe is
  detected.

## Local verification

- `new Function(Code.gs)` syntax parse: passed.
- All 14 CRM Apps Script test files: passed.
- `git diff --check` for the scoped source, tests, and source-state note: passed.

## Live evidence and follow-up candidate

The initial deployment repaired the validation drift: the owner's post-migration
`integrity_check` returned `clean=true` with no problems. It took 172,581 ms.
The root cause was identified in the new validation check: after a successful
migration every one of the 872 cells in `Товари!D:G` matched, so the code
resolved a Range object for every validation rule. Before migration, `every()`
stopped at the first drifted rule, hiding this cost.

The current local follow-up reduces those exact Range-source resolutions to
12, while retaining a full-column check of validation type and strictness. It
also replaces the completion alert with a non-blocking toast. This candidate is
not yet published; its live timing must be measured after owner publication.

## Deployment and QA gate

The owner must review the scoped diff, paste it into the live bound Apps Script
project, and publish a new CRM Web App version. Source change is not deployment.

After publication:

1. Reload the CRM spreadsheet and run **Booster CRM → Оновити довідники SKU**.
   Record its before/after bounded integrity result; the first run should repair
   the four old closed validation ranges, and a repeat should be idempotent.
2. In the dashboard, create `YGO-JP-BETB-BST` with Set `BEYOND THE BRAVE`,
   Language `JP`, RRP 75, cards per booster 5, and no opening stock write.
3. Run **Перевірити CRM** after the SKU creation. Confirm no new problem code,
   the SKU appears once in `Товари` and `РРЦ`, and the automation master obtains
   the matching source row.
4. Repeat the same SKU submission once; it must return the existing row rather
   than create an extra option or catalog row.

## Rollback

Before publication, discard this local candidate. After publication, the owner
can paste and republish the fresh pre-change Apps Script export captured for
this task. The one-time validation migration changes only the validation source
ranges; it does not alter formulas or existing catalog values. Do not delete a
successfully created SKU or option as rollback for this code change.
