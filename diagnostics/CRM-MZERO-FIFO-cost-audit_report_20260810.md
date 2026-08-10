# CRM audit — PKM-JP-MZERO-BLR FIFO cost

> **OP-15 calculation withdrawn (2026-08-10).** Any OP-15 value in this report that stems from **₴14,150.69** is invalid: that number was obtained by summing already-corrupted post-transfer rows. Do not use the OP-15 section. The MZERO-specific FIFO observations remain separate from this withdrawal.

Date: 2026-08-10  
Scope: read-only diagnosis; no live Sheet, Apps Script, orders, or dashboard values were changed.

## Outcome

The current warehouse valuation and the two newest MZERO sales are correct. The defect is confined to two older OLX sales: both still calculate their historical cost from the **current** `Склад` value, so their profit changes whenever the remaining stock cost changes.

| Area | Result |
| --- | --- |
| Current physical stock | Correct: 2 units from `LOT-0080` |
| Current stock cost | Correct: 927.41 UAH PRRO / 983.05 UAH management per unit |
| `OC-FOP-0304` and `OC-FOP-0312` | Correct FIFO: both use `LOT-0072` plus the 1.17 UAH sticker in management cost |
| `MAN-FOP-0002` | Correct FIFO: `LOT-0047` plus the 1.17 UAH sticker |
| `OLX-FOP-0017` and `OLX-FOP-0021` | Incorrect historical cost: formulas look up current `Склад!I:J` |

## Lot chain and current stock

The delivered lots total seven units; five were sold and none were written off. FIFO leaves the two units in `LOT-0080` (delivery 2026-07-19) as current stock. Its formula-derived unit cost is exactly the current `Склад` value:

| Lot | Delivery | Qty | Management unit cost, UAH | Status |
| --- | --- | ---: | ---: | --- |
| `LOT-0001` | 2026-04-01 | 1 | 1,330.71 | Sold |
| `LOT-0002` | 2026-04-01 | 1 | 1,182.31 | Sold |
| `LOT-0047` | 2026-06-15 | 1 | 815.78 | Sold |
| `LOT-0072` | 2026-07-05 | 2 | 1,283.27 | Sold |
| `LOT-0080` | 2026-07-19 | 2 | 983.05 | In UA warehouse |

`Склад` therefore correctly shows quantity 2, management cost 983.05 UAH, and management stock value 1,966.10 UAH.

## Sale-cost reconciliation

| Sale | Sale date | Saved management cost, UAH | FIFO management cost, UAH | Finding |
| --- | --- | ---: | ---: | --- |
| `OLX-FOP-0017` | 2026-04-05 | 983.05 | 1,330.71 | Incorrect; understated by 347.66 |
| `OLX-FOP-0021` | 2026-04-19 | 983.05 | 1,182.31 | Incorrect; understated by 199.26 |
| `MAN-FOP-0002` | 2026-06-16 | 816.95 | 816.95 | Correct |
| `OC-FOP-0304` | 2026-08-06 | 1,284.44 | 1,284.44 | Correct |
| `OC-FOP-0312` | 2026-08-09 | 1,284.44 | 1,284.44 | Correct |

The two OLX rows use formulas that fetch `Склад!I:J` instead of saved FIFO values. Their combined management cost is understated by 546.92 UAH, and their historic profit is overstated by the same amount. All later checked rows already contain fixed FIFO values and must not be changed.

## Pending purchase

`LOT-0097` contains two MZERO units with status `Замовлено`, no delivery date, and no Ukraine-delivery cost yet. They are ordered, but not part of physical stock or the current-cost calculation. `Склад!R3` deliberately counts only `В дорозі`, `На складі в Японії`, and `Виграно`, so its displayed zero does not mean the two ordered units are absent from the purchase ledger.

## Safe repair boundary

The MZERO repair is independent of the OP-15 correction. It should replace only the two legacy OLX sale-cost formulas with their proven historical FIFO values, preserve all later sales, and record an audit trail. The CRM integrity check must run before and after any authorised write.
