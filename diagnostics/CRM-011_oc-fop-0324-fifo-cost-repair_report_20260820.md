# Codex Report — CRM-011: OC-FOP-0324 FIFO cost repair

Date: 2026-08-20
Executor: Codex (owner reassigned CRM-011 in the active task)

## Outcome

The local candidate is ready for owner paste and execution. It contains a bounded, read-only diagnostic and an exact-order repair; it has **not** been pasted to the live Apps Script project and has not changed a live sale.

Bounded live Sheet reads established the repair set before implementation:

| Surface | Verified state |
| --- | --- |
| `Закупки!H108:P108` / `LOT-0113` | Quantity is `5`; formulas remain intact; unit cost is `551.90` PRRO and `585.01` management. |
| `Склад` / `PKM-EN-Q2-MTIN-SAL` | Four units remain; current costs are already `551.90 / 585.01`. |
| `Продажі!289` / `OC-FOP-0324` | Exactly one matching SKU sale; frozen costs remain `689.88 / 731.27`, method `FIFO`, audit names `LOT-0113`. |
| Full bounded SKU search over `Продажі!A1:AF452` | One matching sale row only; no wider row set was found. |

The required correction is therefore `Продажі!L289:M289` from `689.88 / 731.27` to `551.90 / 585.01`, re-frozen through the established FIFO algorithm rather than hand-edited.

## Scope

Implemented the CRM-011 Stage A diagnostic and the now owner-authorized exact repair for `OC-FOP-0324` only.

- `diagnoseCrm011FifoCostDrift()` is read-only, considers a capped maximum of 50 matching SKU rows, and logs one compact JSON result.
- `previewCrm011OcFop0324Repair()` is read-only and returns the exact before/after repair plan.
- `repairCrm011OcFop0324()` resolves exactly one live `OC-FOP-0324` + `PKM-EN-Q2-MTIN-SAL` row, then writes only the frozen cost and audit fields if they drift.
- 3D-P, Mystery Box, non-actual, invalid-quantity, wrong-SKU, missing, and duplicate target rows fail closed.

Not touched: `LOT-0113`, `Склад`, any ledger, any SKU other than the exact target, and all CRM-010 areas.

## Files touched

```
crm/apps-script/Code.gs                              — CRM-011 diagnostic, preview, and exact repair
crm/apps-script/tests/crm-011-fifo-cost-repair.test.mjs — focused regression coverage
crm/apps-script/SOURCE_STATE.md                      — mirror state record
diagnostics/CRM-011_oc-fop-0324-fifo-cost-repair_report_20260820.md — this report
```

## Local verification

```
Code.gs parse: OK
CRM-011 FIFO cost diagnostic and repair tests passed
CRM suite: 20/20 passed
git diff --check: clean
```

The focused test proves all of the following:

- Stage A writes no cells and never invalidates cache.
- Drifted, unchanged, 3D-P, Mystery Box, and non-actual rows are classified correctly.
- Diagnostic output is capped and reports the number truncated.
- Preview changes nothing.
- Apply changes one row from `689.88 / 731.27` to `551.90 / 585.01`, writes `FIFO (CRM-011)` plus `crm011_refreeze=2026-08-20`, flushes once, and invalidates cache once.
- A repeat is idempotent: `rows_written=0`, `already_applied=true`, no extra cache invalidation.

## Owner run sequence

This is an Apps Script source change, not a PHP/server patch. The owner deployment gate remains required.

1. In the CRM bound Apps Script project, paste the reviewed current `crm/apps-script/Code.gs` and save. No Web App publication is required for editor-run functions.
2. Run `diagnoseCrm011FifoCostDrift` from the function picker. It must complete and log one read-only JSON object.
3. Run `previewCrm011OcFop0324Repair`. It must report `dry_run: true`, `rows_written: 0`, and planned FIFO units `551.9 / 585.01`.
4. Make a workbook copy named `До CRM-011 2026-08-20`.
5. Run `repairCrm011OcFop0324`. It must report `rows_written: 1`.
6. Re-read `Продажі!L289:M289,AD289:AF289`: `551.90`, `585.01`, method `FIFO (CRM-011)`, and an audit marker `crm011_refreeze=2026-08-20`.
7. Run the dashboard read-only `integrity_check` and keep its bounded JSON with this task evidence.

## Rollback

Before the apply run, the named workbook copy is the primary rollback point. The repair response contains its before/after plan; to revert, restore the recorded values in `Продажі!L:M,AD:AF` for the one target row. Apps Script project history restores the source candidate if needed.

## Risks

- `Продажі!L:M` affects historical management reporting. This repair intentionally restates only the confirmed drifted sale.
- The function refuses generic application to 3D-P or Mystery Box sales, whose cost projections are owned by separate ledgers.
- A successful Apps Script save is not live proof. The required evidence is the preview/apply output, the exact cell read-back, and the read-only integrity result.
