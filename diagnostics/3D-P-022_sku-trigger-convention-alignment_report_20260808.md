# Codex Report — 3D-P-022: SKU trigger convention alignment

Date: 2026-08-08

## Scope

Implemented the handoff without changing the 3D-P workbook, existing SKU data, sale/stock control
flow, journal schema, or any credential. The CRM trigger is now permissive enough to recognise the
canonical `ACC-3D-<MNEMONIC>-<XYZ>` form and still accepts legacy 3D SKU shapes. The dashboard
create form is strict about the canonical grammar.

## Root cause and fix

- The old CRM predicate required three digits immediately after `ACC-3D-`, so
  `ACC-3D-DITTO-410` did not trigger CRM-to-3D-P sync.
- The old dashboard form likewise rejected canonical `ACC-3D-` SKUs.
- The CRM predicate is now `^(?:BR|FIG|ACC-3D)-[A-Z0-9][A-Z0-9-]*$` to prevent silent sales misses.
- The dashboard creation validator is now `^(BR|FIG|ACC-3D)-[A-Z0-9]{2,5}-\\d{3}$` and shows examples
  when rejecting a malformed new SKU.
- A prefixed but malformed SKU is now journalled as `skipped_sku_shape`, including its SKU in the
  existing sanitised `detail`; a non-3D line still records `skipped_no_3dp_sku`.

The literal `ACC-3D-` branch cannot match the non-3D `ACC-0XX` family; the tests also prove
`ACC-001` does not trigger.

## Files touched

```
crm/apps-script/Code.gs
crm/apps-script/SOURCE_STATE.md
crm/apps-script/tests/3dp-sync-journal.test.mjs
dashboard/booster-dashboard.html
dashboard/tests/3dp-sync-journal-static.test.mjs
tests/3d-p-010-crm-packaging-pull.test.mjs
patches/3D-P-022_sku-trigger-convention-alignment_20260808.js
```

## Local validation

```text
crm/apps-script/tests/3dp-sync-journal.test.mjs: passed
dashboard/tests/3dp-sync-journal-static.test.mjs: passed
tests/3d-p-010-crm-packaging-pull.test.mjs: 9/9 passed
git diff --check: passed
```

Covered values: canonical `ACC-3D-DITTO-410` and `ACC-3D-PKM-130`; legacy
`ACC-3D-410`, `FIG-CHARM-001`, `BR-CHARM-100`; rejected `ACC-001`, `MBX-STD-001`, and
`ACC-3D-`. The journal test also proves that one malformed line is recorded even when another
valid 3D SKU in the same order continues to sync.

## Deployment status

Prepared locally only. The main CRM Apps Script mirror was last exported on 2026-08-08; source is
not deployment proof. The owner must paste the CRM patch, publish a new Web App version, then export
the deployed `Code.gs` and record its version in `crm/apps-script/SOURCE_STATE.md`.

The dashboard file is a direct repository edit and becomes effective from this local file; no 3D-P
workbook write is involved.

## Rollback

Restore the prior CRM predicate/no-trigger block and the prior dashboard validator, remove the new
journal-outcome map entry, then publish a new CRM Web App version. No spreadsheet rows are created,
edited, or deleted by 3D-P-022.

## Owner QA

- [ ] Create a named CRM Apps Script version before paste/publish.
- [ ] Apply [3D-P-022_sku-trigger-convention-alignment_20260808.js](../patches/3D-P-022_sku-trigger-convention-alignment_20260808.js).
- [ ] In `3D-друк → Вироби`, create `ACC-3D-DITTO-410` as `Функціональний аксесуар`.
- [ ] Verify the SKU appears in 3D-P and then resume the 3D-P-014 QA cases.
- [ ] Re-export the deployed CRM source and update the mirror state.
