# CRM Mystery Box write-off recovery — Claude handoff

Date: 2026-08-24  
Status: recovery complete; live data read-back verified.  
Deployment note: CRM Web App **V148**, published at 13:06 Kyiv, is owner-reported. No post-V148 source export was byte-compared with the local mirror.

## Outcome

The inventory understatement caused by the capped write-off formula and the duplicated Mystery Box ledger record was repaired without creating any new write-offs.

- `WRT-0206`: retained one intended row and cleared 16 identical surplus rows.
- `Склад!G`: 94 write-off formulas now include `Списання` through row 236.
- The original CSV audit found 47 valid write-off units omitted by the former row-197 formula: 44 Mystery Box component units and 3 other write-off units. The 32 units from the surplus copies of `WRT-0206` were excluded before expanding the formula.

## Direct live evidence

The following was read directly from the live Google Sheet after the successful recovery; no write was made during this verification.

| Check | Result |
|---|---|
| Recovery execution | Owner-run result at 12:59 Kyiv: `kept_writeoff_row:208`, `duplicate_rows_cleared:16`, `stock_formula_rows_updated:94`, `writeoff_formula_last_row:236`; bounded CRM integrity was clean before and after. |
| `Списання!A206:A236` | IDs continue from `WRT-0204` through `WRT-0218`; exactly one `WRT-0206` remains. |
| `Склад!G3:G5` | Each formula is a `SUMIF` against `Списання!$D$3:$D$236` / `$F$3:$F$236`, replacing the former row-197 cap. |
| Sample affected warehouse rows | Read-back showed numeric stock and current-cost values without formula errors: `OP-JP-OP15-BST` G/H/I/J = 27 / 11 / 151.70 / 160.80; `OP-JP-OP08-BST` = 25 / 5 / 82.67 / 87.63. This is a bounded sanity check, not a complete FIFO-cost reconciliation. |

The first run at 12:53 Kyiv made no writes: its preflight correctly stopped because it looked for the write-off formula in `Склад!H3`. Direct inspection established that the relevant formula is in `Склад!G3`; the corrected run then completed successfully.

## Source and cleanup state

The local mirror removes the one-off recovery menu item, helper functions, and recovery test after the confirmed success. The permanent capacity-maintenance wrapper remains. Local verification previously completed: `Code.gs` parses, the remaining Apps Script suite passed 20/20, and a caller search for the one-off recovery returned zero hits.

The owner reports that the script was replaced, all recovery actions were run, and V148 was published. Treat this as owner-reported deployment evidence until a fresh complete Apps Script export is compared to `crm/apps-script/Code.gs`.

## Boundaries / not proven by this recovery

- It does not prove the physical stock count; new manual write-offs should be entered only for the remaining physical difference.
- It does not perform a complete FIFO/current-cost reconciliation. The reported current-cost action and the sampled numeric `I:J` cells show no immediate formula error, but not correctness of every SKU cost.
- It does not prove byte-for-byte identity of V148 with the local mirror.

## Requested Claude review

Review this recovery as complete for the duplicate ledger and write-off-formula scope. Do not reopen or rerun the one-off recovery. If a follow-up is needed, scope it separately to a physical-count reconciliation or an explicit FIFO/current-cost audit; do not alter Notion properties or task status from this handoff.

## Related artifacts

- `diagnostics/CRM-MYSTERY-WRITEOFFS_inventory-audit_report_20260824.md` — original CSV audit and evidence.
- `handoffs/handoff_CRM-MYSTERY-WRITEOFFS_inventory-recovery_20260824.md` — historical execution runbook.
- `crm/apps-script/SOURCE_STATE.md` — source/provenance notes.
