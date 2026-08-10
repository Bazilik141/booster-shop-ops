# WITHDRAWN — CRM audit — OC-FOP-0314 / OP-15 box-to-pack split

> **Calculation withdrawn (2026-08-10).** This report incorrectly summed two already-corrupted post-transfer rows and treated **₴14,150.69** as the original two-box management cost. That number, and every figure derived from it in this file, are invalid and must not be used. The source listing price of approximately **₴6,300** covers the two equal boxes together. The only valid basis for a repair is the single original management total: original lot price plus its actual original fees and delivery.

Date: 2026-08-10  
Scope: read-only diagnosis; no Google Sheet, Apps Script, dashboard, or order values were changed.

## Outcome

`OC-FOP-0314` did **not** receive the cost of both boxes. Its management cost is the entire value currently stored on the one remaining box lot, plus the automatic sticker consumable:

`7718.56 (LOT-0063 box) + 1.17 (sticker) = 7719.73 UAH`

That is why the order has a negative management profit. The calculation chain is working as designed, but its input lot allocation is not reliably correct for the reported box-to-pack operation.

The current records show an uneven manual allocation of the original two-box purchase value, not a duplicated total. The owner confirmed the source listing was one price for two identical boxes, each containing 24 packs; equal half-allocation is therefore the required accounting rule:

| Record | Qty | PRRO unit cost, UAH | Management unit cost, UAH | Management total, UAH |
| --- | ---: | ---: | ---: | ---: |
| `LOT-0063` / `OP-JP-OP15-BBX` retained box | 1 box | 7,281.66 | 7,718.56 | 7,718.56 |
| `LOT-0119` / `OP-JP-OP15-BST` split packs | 20 packs | 303.40 | 321.61 | 6,432.13 |
| Combined current source allocation | — | — | — | 14,150.69 |

The combined 14,150.69 UAH equals the two row totals, so the full cost was not copied onto both rows. However, 54.5% of the recorded management cost remains on one box and 45.5% is assigned to the pack lot. With the now-confirmed identical boxes, each source box must carry 7,075.35 UAH management cost (6,674.85 UAH PRRO); the converted lot must begin with 24 packs at 294.81 UAH management / 278.12 UAH PRRO per pack.

## Confirmed facts

1. `Продажі!265` (`OC-FOP-0314`, 2026-08-10) sold one `OP-JP-OP15-BBX` for 3,655 UAH. The saved sale audit says `LOT-0063: 1 x 7281.66/7718.56; auto consumables: Стікер лого+QR=1.17`.
2. `Закупки!58` (`LOT-0063`) now contains one box and a formula-derived management cost of 7,718.56 UAH. Its raw cost inputs and formula columns are internally consistent.
3. `Закупки!123` (`LOT-0119`) is a manual split row: `yskh243 (split)`, 20 packs, management total 6,432.13 UAH, unit cost 321.61 UAH. Its note states that one `LOT-0063` box was converted into 20 packs.
4. `Товари` defines both relevant active SKUs and sets `OP-JP-OP15-BBX` to **24 packs per box**. Therefore the split record’s 20 packs conflicts with the canonical product configuration.
5. `Списання!162` (`WRT-0160`, 2026-08-03) also describes the conversion as `1 × OP-JP-OP15-BBX (20 packs) → OP-JP-OP15-BST; 4 packs written off`. This is a second source of the same 20-versus-24 conflict.
6. `Склад` reports 24 packs on hand at 282.53 UAH management unit cost. This is exactly the script’s SKU-level FIFO result: 4 packs remaining from `LOT-0036` at 87.14 UAH plus all 20 packs from `LOT-0119` at 321.61 UAH. It is not a separate formula error in `Склад`.

## Why the pack cost looks wrong

The owner clarified the intended rule: the four later opened packs remain an ordinary SKU-level FIFO write-off, so they consume the oldest available packs first. That is exactly how `updateSkuCurrentCost_` works, and `WRT-0160` must not be tied directly to the newly converted lot.

The defect is solely the source split: it created 20 packs instead of 24 and gave both the retained box and the converted packs the wrong half-allocation. After the correction, the current pack stock must be 28 units: 4 remaining older `LOT-0036` packs plus 24 packs from the converted box. The expected stock cost is 250.13 UAH PRRO / 265.14 UAH management per pack, with 7,423.92 UAH management value in total. These values follow the already-existing SKU FIFO logic; no new lot-link rule is needed.

## Apps Script trace

The current local mirror is V98, live-confirmed on 2026-08-09.

- `fixSaleCostForRow_` calculates the sale from FIFO batches and then adds the automatic sticker cost. This explains the saved 7,719.73 UAH on `OC-FOP-0314`.
- `getFifoCostBatches_` reads batches only by SKU and purchase-row order.
- `updateSkuCurrentCost_` subtracts aggregate sales and write-offs by SKU from oldest lots; it has no `sourceLotId` input.
- No automatic box-to-pack transfer/allocation function is present in the current CRM script. The split row is therefore a manual accounting operation, not a calculated Apps Script transfer.

## Integrity-check context

The supplied integrity check is not clean because of existing literals in formula columns on other `Товари` and `Розхідники` rows. The two `OP-15` product-master rows are not in the listed affected ranges. Those warnings must be recorded before and after any future repair, but they do not explain the `LOT-0063` / `LOT-0119` allocation.

## Safe next action (requires owner approval)

The allocation evidence is now sufficient. Do not change only `Склад`: that would refresh a display value while leaving the box sale with a wrong fixed historical cost.

An authorised backed-up repair must:

1. Reallocate `LOT-0063` and `LOT-0119` to equal halves of the two-box cost.
2. Change the split lot from 20 to 24 packs and set its per-pack cost from the second half.
3. Recalculate `OC-FOP-0314` from the corrected retained-box cost; its management cost becomes 7,076.52 UAH including the 1.17 UAH sticker.
4. Run the existing FIFO stock-cost refresh so `Склад` reflects 28 packs and the corrected remaining-lot mix.

`WRT-0160` requires no direct lot reassignment: it stays in normal oldest-lot FIFO. The integrity check must run immediately before and after the authorised change.
