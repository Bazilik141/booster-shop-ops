# CRM-FORMULA-COVERAGE-001 — formula range coverage repair

Date: 2026-09-03

## Scope

Repair formula references that stopped before the current prepared grid rows in
the main CRM. The repair touches formulas only; it does not add, delete, or
rewrite sale, purchase, write-off, FIFO, or migration ledger values.

The local `Code.gs` mirror is changed. It is not a live deployment.

## Live evidence before repair

Google Sheets metadata and formula-only reads of the live main CRM found these
current grid capacities:

| Source sheet | Current last grid row | Old formula upper bound found |
| --- | ---: | ---: |
| `Продажі` | 752 | 433 and 452 |
| `Закупки` | 309 | 290 |
| `Списання` | 636 | 201 / 220 |
| `Витрати` | 218 | 199 |
| `Розхідники` | 80 | 50 |
| `Товари` / `Склад` | 220 | 201 |
| `РРЦ` | 930 | 300 |

Examples of affected live calculations:

- `Склад!E:F:R` ignored purchases after `Закупки!290` and sales after
  `Продажі!433`.
- `Склад!S:T` used the old 201-row catalog bound.
- `Розхідники` usage formulas ignored sales after row 433 and expenses after
  row 199.
- Older `Продажі` rows used 433-row fee allocation ranges, so their order
  charges could exclude newer lines in the same order.

The initial scan missed compact metric cards on `Дашборд`. The post-run live
check found `Дашборд!B9:B12`, `I9:I16` still referenced `Закупки…$290` and
`Склад…$201`; that directly understated stock units, stock values, and frozen
capital for catalog rows 202–220. The repair now includes those two narrow
dashboard metric columns. `Дашборд_ФОП`, `Налаштування`, and
`Міграції_Складу` have no identified capacity-bound formulas in this scope.

`PKM-JP-MZERO-BBX` currently remains at `1` because its ledger contains five
received boxes, three recorded sales, and one recorded `MIG-0002` transfer to
28 packs. The missing recent box sale is not present under that SKU in
`Продажі`; expanding ranges cannot invent that ledger row.

## Implementation

- The former one-pass repair exceeded Apps Script's 360-second execution cap
  and stopped partway through. The code now processes at most 100 rows per
  invocation and stores the exact scope and next row in Script Properties.
- Each batch snapshots only its changed formulas and restores that batch if a
  write error occurs. The next minute's one-time trigger resumes the same CRM
  spreadsheet by its saved ID; it does not depend on an active browser tab.
- A bounded `apiIntegrityCheck_` runs before the first batch and after the
  final batch. On a failed final check the code stops, retains the state for
  diagnosis, and the owner restores the Google Sheets version/copy made before
  the repair.
- `Booster CRM → Налаштувати автооновлення формул CRM` starts or resumes the
  batch sequence and ensures the daily capacity trigger exists. At least 30
  short executions may be required for the current prepared rows.
- `Booster CRM → Оновити діапазони дашборду` is a separate fast repair for
  only `Дашборд!B9:B17` and `I9:I17`. It is safe to run after the completed
  background repair and does not restart that long sequence.
- The normal nightly maintenance remains non-blocking and does not run the
  full integrity check.

## Files touched

```text
crm/apps-script/Code.gs
crm/apps-script/tests/row-capacity.test.mjs
diagnostics/CRM-FORMULA-COVERAGE-001_formula-range-repair_report_20260903.md
```

## Local verification

```text
node crm/apps-script/tests/row-capacity.test.mjs
CRM row capacity tests passed

node crm/apps-script/tests/expected-stock-formula.test.mjs
CRM expected-stock formula tests passed

node crm/apps-script/tests/sku-current-cost-menu.test.mjs
CRM-010 SKU current-cost menu tests passed

node --input-type=module -e "... new vm.Script(Code.gs) ..."
Code.gs syntax parse passed

git diff --check -- crm/apps-script/Code.gs crm/apps-script/tests/row-capacity.test.mjs
exit 0
```

The capacity regression test also proves that a failed post-check restores the
original stale formula instead of leaving a partial formula rewrite.

## Owner rollout and QA

1. Make a Google Sheets copy before changing formulas.
2. Paste the changed complete `crm/apps-script/Code.gs` into the bound Apps
   Script project, save it, and publish a new named Web App version.
3. Reload the CRM spreadsheet. Select `Booster CRM → Налаштувати автооновлення
   формул CRM` once. The first dialog should report `batch_scheduled`; do not
   run the function manually from the Apps Script editor.
4. Wait for the one-time background batches (about one per minute). The final
   execution log must contain `status: complete` and no integrity error.
5. After a completed run, select `Booster CRM → Оновити діапазони дашборду`
   once. The dialog must report the number of changed formulas, then reload
   the dashboard.
6. Add the omitted Munics Zero box sale through the normal sale flow with SKU
   `PKM-JP-MZERO-BBX`; do not type over `Склад!H8`. Its balance should then be
   `0` and its asset value should be `0.00`.
7. Re-run the dashboard CRM integrity check and retain its bounded result.

## Rollback

If a batch write fails, that batch restores its own formula changes before
throwing. If the final integrity check fails or a business outcome is wrong,
restore the Google Sheets copy/version made before step 2 and republish the
prior Apps Script version.

## Risks

Medium: the first owner run changes many formulas because the workbook has
several historical capacity boundaries. The operation preserves all input and
ledger cells, performs a bounded integrity pre/post check, and is owner-gated.
`apiIntegrityCheck_` does not validate every FIFO or warehouse outcome, so the
post-run Munics Zero and new-purchase dashboard checks remain required.
