# Codex Report — 3D-P-019: Phase B fixture usage ledger

Date: 2026-08-11

## Status

Implemented and locally validated only. The owner confirmed that the current `Code.gs` revision has
**not** been pasted or published; CRM Apps Script V102 remains the live baseline.

The owner explicitly approved this dedicated **usage** ledger on 2026-08-11. It supersedes the older
handoff's no-ledger implementation choice only for durable consumption records; F9 remains unchanged:
one operation may still contain fixtures of one payer only, and `W` never becomes `змішано`.

## Implemented contract

- Owner-run `setup3dp019FixtureUsagePhaseB()` creates the append-only
  `Використання_фурнітури` ledger with ID, date, source, reference, fixture, payer, quantity, frozen
  unit cost, frozen total, note, and creation time.
- Its hidden helper column produces the fixture dropdown as `code | payer` directly from
  `Розхідники`, so the payer is visible and never parsed from the fixture code.
- The setup replaces `Розхідники!H` only on fixture rows with a `SUMIFS` formula over the new ledger,
  matching both fixture code and payer. It refuses a non-zero historical literal in H instead of erasing
  un-reconciled usage.
- Repeatable fixture rows are added to the sale, sale-update, and write-off forms. A selected value
  must be an exact current fixture/payer pair and quantity must be positive.
- F9 is enforced in the UI and before every ledger write. The message identifies the already selected
  fixture/payer and the rejected fixture/payer. One sale or write-off therefore has one payer only.
- F6 is preserved: insufficient fixture stock produces a visible warning but still saves the operation.
- Manual sale creation and sale update append fixture usage before the CRM→3D-P sync. For a 3D-P sale,
  frozen V is `all fixture ledger totals ÷ all 3D-P units in that order`; W is the one actual payer. No
  fixture produces `V=0`, `W` blank. The old `Номенклатура!N` reference is no longer used as a cost input.
- The write-off form appends the same ledger rows with its `WRT-*` reference, without treating normal
  sale consumption as a write-off in the existing `Списання` table.

## Setup safety

Before the first write, setup requires the verified `Розхідники` A/B/H/O headers, at least one valid
fixture row, zero-or-formula fixture H values, and empty/recognized target form areas. It does not
overwrite an unknown form section or a mismatched existing ledger schema.

The three target areas were read narrowly from the live CRM before implementation:

- `Внести_продаж!A47:C55`;
- `Оновити_продаж!A20:C28`;
- `Внести_списання!A23:C30`.

No customer or sales data was read or changed.

## Local validation

```text
node --input-type=module -e "import { readFileSync } from 'node:fs'; new Function(readFileSync('crm/apps-script/Code.gs', 'utf8')); console.log('Code.gs syntax parse passed');"
node crm/apps-script/tests/3d-p-019-phase-a.test.mjs
node crm/apps-script/tests/3d-p-019-fixture-usage.test.mjs
node crm/apps-script/tests/integrity-check.test.mjs
git diff --check
```

All passed. The new fixture test proves same-payer aggregation, F6 warning without rejection,
mixed-payer rejection naming both sides, invalid dropdown text rejection, and V/W allocation from a
ledger total.

## Deployment / QA gate

This is a CRM financial and sheet-structure change. Before production:

1. Create a named Google Sheets version.
2. Run the bounded `integrity_check` and retain its output as the Phase-B baseline.
3. Paste/publish the complete current `Code.gs`.
4. Run `setup3dp019FixturePayerPhaseA()` once to apply the strict O-column safeguard.
5. Run `setup3dp019FixtureUsagePhaseB()` once.
6. Run `integrity_check` again. Any new code is a defect of this change.
7. Use a reversible test sale and test write-off, then verify the ledger rows, `Розхідники!H/I`, the
   F9 message, an F6 warning-save, and frozen V/W in the 3D-P sale row.

## Deliberately not implemented here

Serhiy's pending-purchase queue and owner-confirmed import (F3/F5) remain a separate 3D-P workbook/API
change. The main CRM API routes do not yet accept fixture-line payloads; their callers provide no such
schema, so they continue with no fixture usage and blank W rather than inventing a payer.

## Rollback

Do not delete ledger rows. To undo a test, append an equal and opposite correction only after the owner
approves the accounting correction; restore the two fixture H formulas only if the ledger is retained.
For a code rollback before any real fixture usage, revert the current source revision and leave the
new empty ledger/form areas in place until the owner decides whether to remove them manually.
