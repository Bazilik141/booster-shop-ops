# Codex Report — CRM-BUNDLE-COST-REPAIR: one-time legacy bundle cost correction

Date: 2026-08-27

## Scope

Prepare a separate temporary Apps Script file for the two legacy bundle rows only:

- `LOT-0100` / `PKM-EN-PORD-BBN`
- `LOT-0101` / `PKM-EN-CHRS-BBN`

The owner confirmed that each original order contained five booster bundles. The existing manually split pack lots already preserve the transferred proportion of purchase cost:

- `LOT-0118`: one Perfect Order bundle → six packs;
- `LOT-0117`: two Chaos Rising bundles → twelve packs.

The source bundle rows still hold only the remaining quantity (4 and 3) but retain the full five-bundle cost. The runner reduces only their manual `Закупки!I` (goods) and `K` (Ukraine delivery) inputs by the cost already recorded in the matching pack lot. It does not overwrite formulas, sales costs, stock quantities, statuses, or pack costs.

## Temporary artifact

```
crm/apps-script/CRM-BUNDLE-COST-REPAIR-ONCE_20260827.gs
crm/apps-script/tests/bundle-cost-repair-once.test.mjs
```

This is deliberately not part of `crm/apps-script/Code.gs` and is not a Web App endpoint or menu item.

## Expected correction

| Source lot | Inputs before | Inputs after | Correct management unit cost |
|---|---:|---:|---:|
| `LOT-0100` / Perfect Order bundle | I=5995.00, K=169.00 | I=4796.00, K=135.20 | 1306.77 |
| `LOT-0101` / Chaos Rising bundle | I=5995.00, K=169.00 | I=3597.00, K=101.40 | 1306.77 |

The runner then calls the existing `updateSkuCurrentCost_` and cache invalidator. Pack unit cost remains 217.79 in both cases.

## Safeguards

- verifies the exact CRM spreadsheet id, sheets, four lots, four SKU, row state, quantities, statuses, formulas, stock, sales and write-off counts before a write;
- aborts without writing if any check differs;
- accepts only an all-before or all-after state; a partial state fails closed;
- stores the two manual inputs for rollback if a post-write verification fails;
- checks cost conservation (PRRO 6164.00 and management 6533.84 per original order);
- runs CRM integrity check before and after a write;
- is idempotent after a verified successful run.

## Local verification

```
One-time legacy bundle-cost repair tests passed
Apps Script syntax parse passed
git diff --check passed
```

## Owner-run sequence

1. Create a new Apps Script file with the plus button shown in the owner screenshot, choose **Script**, and paste only `CRM-BUNDLE-COST-REPAIR-ONCE_20260827.gs`.
2. Run `previewLegacyBundleCostRepair`; it must return `state: "ready_to_apply"` and exactly two planned writes.
3. Run `applyLegacyBundleCostRepair`; it must return `ok: true`, `rows_written: 2`, and both source management costs as `1306.77`.
4. Delete the temporary file from the live Apps Script project. Then remove the matching local `.gs` and test artifact after reporting the result.

## Owner-run result and independent read-back

At 14:40 Kyiv on 2026-08-27, the owner ran `applyLegacyBundleCostRepair` in the live CRM. The runner reported `ok: true`, `already_applied: false`, `rows_written: 2`; FIFO current-cost refresh updated 37 SKUs; the integrity check was clean both before and after the write.

An independent bounded read-back confirmed the expected source costs and preserved pack costs:

| SKU | Remaining quantity | Management unit cost |
|---|---:|---:|
| `PKM-EN-PORD-BBN` | 4 | 1306.77 |
| `PKM-EN-PORD-BST` | 0 | 217.79 |
| `PKM-EN-CHRS-BBN` | 3 | 1306.77 |
| `PKM-EN-CHRS-BST` | 4 | 217.79 |

The two source lots now retain their respective residual purchase inputs: Perfect Order `I=4796.00`, `K=135.20`; Chaos Rising `I=3597.00`, `K=101.40`. Formula columns remain formulas.

## Cleanup

- The owner must delete only the temporary live Apps Script file created for this runner; do not delete `Code.gs`.
- After the independent read-back, Codex removed the matching local temporary `.gs` source and its test file. This diagnostic remains as the execution record.
