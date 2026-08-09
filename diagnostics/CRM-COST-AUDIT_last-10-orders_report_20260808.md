# Codex Report — CRM-COST-AUDIT: last 10 order cost review

Date: 2026-08-08

## Scope

Read-only audit of the live Google Sheet `Booster Shop CRM — облік товарів`:

- `Продажі!A236:AF257` — the last 10 unique order IDs by populated row order
- `Закупки!A2:Q309` — purchase lots and unit costs
- `Склад!A2:K220` — current quantities and current weighted costs
- `Списання!A2:L216` — write-offs linked to the audited orders

Audited orders: `OC-FOP-0309`, `OC-FOP-0308`, `OC-FOP-0307`, `OC-FOP-0304`, `OC-FOP-0302`, `OLX-FOP-0049`, `OC-FOP-0301`, `OC-FOP-0297`, `OC-FOP-0294`, `OC-FOP-0287`.

## Dry-run result

- 19 sale lines were inspected. All 17 finalized lines match the purchase-lot or component basis recorded in the cost audit, including rounding to the displayed unit cost.
- `OC-FOP-0287` is correctly mixed: 5 units from `LOT-0102` at `92.72 / 98.28`, 5 units from the recorded fallback stock basis at `75.08 / 79.58`, plus `1.17` consumables. This produces `83.90 / 89.05` per unit.
- `OC-FOP-0309` is correctly based on the five linked write-offs: PRRO total `639.86`, management total `681.51` including `3.26` consumables.
- Historical FIFO costs can differ from the current `Склад` average because later lots remain in stock. Examples: `ACC-003` sold at `118.80` while current stock averages `85.00`; `PKM-JP-MZERO-BLR` sold at `1210.63` while current stock averages `1021.82`; `OP-JP-OP15-BST` sold at `82.21` while current stock averages `266.54`. The linked purchase lots confirm these are FIFO effects, not arithmetic errors.

## Exceptions / risks

1. `OC-FOP-0302`, line `Продажі!249`, `ACC-009`: the cost basis `2.66 / 2.81` matches the only purchase lot (`LOT-0091`), but `Склад!60` is currently `-3` units (`10` purchased, `13` sold). This is an inventory-balance deficit, not a cost-formula mismatch.
2. `OC-FOP-0308`, lines `Продажі!255:256`: both rows are `Оплачено / Передзамовлення` and explicitly marked `Відкладено` / `Не зафіксовано`. Their displayed costs are provisional lookup values and should not be treated as final COGS until the preorder is released.

## Idempotency

N/A — read-only audit; no spreadsheet cells were changed.

## Rollback

N/A — no writes or deployment performed.

## Post-deploy QA checklist

- [x] Verified all audited lot references against `Закупки` unit costs.
- [x] Reconciled finalized line costs, fallback allocations, and linked write-offs.
- [x] Compared historical FIFO costs with current `Склад` balances and isolated the two non-error exceptions above.
- [x] No edits, formula changes, or status changes made.

## Side effects / risks

No live data was modified. The `ACC-009` negative balance remains unresolved and should be corrected through a separately authorized inventory reconciliation; `OC-FOP-0308` remains intentionally deferred as a preorder.
