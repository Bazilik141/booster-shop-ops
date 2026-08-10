# Codex Report — CRM-006: manual short-name integrity exceptions

Date: 2026-08-10

## Scope

The `integrity_check` rule treated every literal in `Товари → Коротка назва` as a defect. The owner-approved temporary copy of the `8 серпня, 22:54` version proved that 15 specified SKU rows were already manually governed before the formula-restoration work.

This change exempts only those 15 SKUs from the `Коротка назва` row-formula check. It does not exempt a literal in `Поточна ціна продажу`, any other product row, `Розхідники`, `РРЦ`, or `Майстер_Товарів`.

## Historical evidence

Read-only inspection of `Товари!A52:G60` and `Товари!A71:G76` in the temporary history copy found `userEnteredValue.stringValue`, not `formulaValue`, for these short names:

`ACC-001`, `ACC-002`, `ACC-003`, `ACC-004`, `ACC-005`, `ACC-006`, `ACC-007-360`, `ACC-008`, `ACC-009`, `PKM-JP-MBX-XL`, `OP-JP-MBX-XL`, `PKM-JP-MBX-ST`, `OP-JP-MBX-ST`, `ACC-3D-DITTO-410`, `PKM-EN-PBLK-BLR-SLP`.

## Files touched

```text
crm/apps-script/Code.gs                              — narrow SKU allow-list in integrity check
crm/apps-script/tests/integrity-check.test.mjs       — allowed, non-allowed, and price-regression tests
crm/apps-script/SOURCE_STATE.md                       — records local pending, undeployed mirror change
diagnostics/CRM-006_integrity-manual-short-names_report_20260810.md — this report
```

## Local verification

```text
node crm/apps-script/tests/integrity-check.test.mjs
CRM integrity-check tests passed

Get-Content crm/apps-script/Code.gs -Raw | node --input-type=commonjs --check
exit 0

git diff --check -- crm/apps-script/Code.gs crm/apps-script/tests/integrity-check.test.mjs
exit 0
```

The test covers all 15 allow-listed SKUs, a literal short name on a non-allow-listed SKU (must still report `formula_column_literal`), and a literal price on `ACC-001` (must still report `Поточна ціна продажу`).

## Live deployment evidence and rollback

The owner reported V99 published at 19:59 Kyiv on 2026-08-10 and supplied its live `integrity_check` result:

- `Товари → Коротка назва` rows `52-60, 71-76` are absent.
- `Товари → Поточна ціна продажу` rows `38-39` remain present.
- All three existing `Розхідники` findings remain present.
- No new problem code appeared; 3D-P RRP coverage remains `compared: 1`, `skipped_missing_crm_rrp: 0`, `deferred: null`.
- Runtime elapsed time: `6362` ms.

The owner deployed V99 by pasting the exact local `crm/apps-script/Code.gs` file, so this is both live behavior proof and direct mirror-to-V99 deployment provenance. A redundant post-deploy export is not required. Rollback remains owner-controlled: restore the previous deployed Apps Script version or republish the V98 source export.

## Side effects / risks

- The allow-list is intentionally explicit, so new manual SKU names remain detectable until separately justified.
- The mirror-to-V99 identity rests on the owner's direct deployment of this exact local file, rather than a second byte-comparison export.
