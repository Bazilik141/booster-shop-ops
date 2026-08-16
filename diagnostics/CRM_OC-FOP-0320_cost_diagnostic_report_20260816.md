# Codex Report — CRM: OC-FOP-0320 cost diagnosis

Date: 2026-08-16

## Scope

Read-only investigation of the cost calculation for `OC-FOP-0320`. No spreadsheet cells, Apps Script source, dashboard code, deployment, or status were changed.

## Evidence inspected

- Live CRM spreadsheet `Продажі!A280:AF281`.
- Live CRM spreadsheet `Використання_компонентів` rows 15-24, filtered by `OC-FOP-0320`.
- Live CRM spreadsheet `Списання` rows 194-201, filtered by `OC-FOP-0320`.
- `crm/apps-script/SOURCE_STATE.md`: CRM V118 was owner-reported published on 2026-08-13.
- The current working-tree diff does not modify the cost paths examined: `recalculateMysteryBoxOrderCost_`, `fixSaleCostForRow_`, `orderComponentTotals_`, `applyOrderComponentCost_`, or `appendOrderComponents_`.

## Result

The Mystery Box management cost is overstated because the second order update executes two overlapping accounting paths:

1. `fixSaleCostForRow_()` invokes `recalculateMysteryBoxOrderCost_()`, which uses the order-linked `Списання` entries as Mystery Box composition cost.
2. `applyOrderComponentCost_()` then adds all component-ledger entries for the same order, including the six entries already targeted to CRM row 281.

The initial Mystery Box content was saved with request marker `dashboard-1786872481497-ecmqiu1k`; the later update is recorded separately as `dashboard-1786873628560-9ed5tgnf`. The second update therefore re-added cost already present through the first save's linked writeoffs.

## Amount reconciliation

| Item | PRRO, UAH | Management, UAH |
| --- | ---: | ---: |
| Current Mystery Box cost in `Продажі!N281:O281` | 2,418.38 | 2,591.38 |
| Targeted Mystery Box content, frozen in component ledger | 1,388.33 | 1,471.60 |
| Order-level second-save components allocated to Mystery Box by revenue (2,000 / 2,700) | 27.58 | 53.96 |
| Auto Mystery Box consumables | 0.00 | 3.26 |
| Expected Mystery Box total after counting each entered component once | about 1,415.92 | 1,528.82 |
| Current overstatement | about 1,002.46 | 1,062.56 |

The expected management cost is about `764.41 UAH` per Mystery Box instead of `1,295.69 UAH`. With the same packaging, payment, and delivery values, this changes the Mystery Box management profit from `-681.01 UAH` to about `+381.55 UAH`; total order profit becomes about `+812.48 UAH` (about `30.1%`).

## Related inconsistency

`Списання` rows WRT-0196 and WRT-0197 contain the intended SKU and quantity but have blank calculated name/cost cells. Their frozen component-ledger costs are present (196.56 and 230.91 UAH management cost). This is why the Mystery Box recomputation sees only four priced writeoffs, while the generic component ledger sees all six. It must be reconciled as part of the durable repair; overwriting formula/cost cells manually would violate the CRM derived-data rules.

## Recommended bounded repair

1. Patch the Apps Script order-update path so a targeted Mystery Box component is counted once only; preserve order-level/unassigned component allocation.
2. Repair the source/formula path that leaves WRT-0196 and WRT-0197 without calculated costs, using the approved source of truth rather than literals.
3. Apply a narrowly scoped, idempotent repair for OC-FOP-0320 after an owner-approved preflight, then re-read only the affected `Продажі`, component-ledger, and writeoff rows.
4. Run the read-only CRM integrity check before and after. It is schema/formula evidence only and does not replace the above cost reconciliation.

## Prepared local repair

Implemented locally, not published:

- `recalculateMysteryBoxOrderCost_()` now uses a component-ledger entry targeted to a Mystery Box as its frozen composition cost and excludes its linked writeoff from a second calculation. Legacy unlinked Mystery Box writeoffs retain their fallback path.
- `applyOrderComponentCost_()` no longer adds a targeted Mystery Box component after the Mystery Box recomputation. Order-level/unassigned components are still allocated across order lines by revenue.
- New component writeoffs receive the canonical `Списання` formulas for name and cost columns. The order-scoped recovery restores those formulas only where the linked cells are fully blank, and stops on any unexpected formula shape.
- `repairOCFOP0320MysteryBoxCost()` is an exact, idempotent owner-run recovery. It is limited to the order's sale cost cells and its linked blank writeoff-formula cells.

## Local verification

- `Code.gs` parsed with Node `new Function(...)`.
- All 13 local CRM Apps Script tests passed, including the new first-save / second-edit regression case.
- The regression proves the expected Mystery Box units: PRRO `707.96 UAH`; management `764.41 UAH`; repeat recovery reports `already_applied=true`.

## Publication gate

No live action has been taken. The current local `Code.gs` also has unrelated working-tree changes, whose live status was not re-proven in this investigation. Do not paste the complete file into Apps Script as a bundled deployment without first isolating/reviewing the scoped cost-repair hunks. After the owner publishes the scoped source, run `repairOCFOP0320MysteryBoxCost()` once, then run it once more to confirm `already_applied=true` and capture bounded reads of the affected rows.

## Risk and rollback

CRM cost mutation is a risky-zone change. No mutation was made in this investigation. A later repair needs a reversible, order-scoped plan and must not alter purchase-cost, stock, or unrelated sale rows.
