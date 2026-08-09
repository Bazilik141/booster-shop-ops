# CRM Stock Adjustment — 2026-08-08

## Scope

Reconcile the physical balances requested for `ACC-003`, `ACC-009`, and `PKM-JP-OUTL-BST` without changing unrelated SKU records.

## Completed adjustments

| SKU | Resulting balance | Recorded movements |
| --- | ---: | --- |
| `ACC-003` | 5 | `WRT-0166`: 3 promotional units; `WRT-0167`: 1 unit transferred to `ACC-009` |
| `ACC-009` | 15 | `LOT-0129`: 25 units received from the `ACC-003` box; `WRT-0168`: 7 promotional units |

`LOT-0129` carries the source box cost through the internal conversion: 85.00 UAH purchase cost and 90.10 UAH landed cost for 25 individual units. The resulting per-unit costs for `ACC-009` are 3.40 UAH and 3.604 UAH respectively.

Formula verification after the movements:

- `ACC-003`: purchased 10, sold 1, written off 4, balance 5.
- `ACC-009`: purchased 35, sold 13, written off 7, balance 15.

## Outlet Mix evidence and completed correction

`PKM-JP-OUTL-BST` currently has a calculated balance of -5: purchased 278, sold 277, written off 6. The sales formula includes `OC-FOP-0310` for 10 units because it is paid and currently in processing. Before this order was entered, the calculated balance was 5.

Cancelled or returned Outlet orders are excluded by the warehouse formula and do not explain the discrepancy. Historical write-offs include a 5-unit gift movement (`WRT-0020`) and other documented corrections.

The owner confirmed that the 10 packs for `OC-FOP-0310` are included in the physical count. The following correction was therefore recorded:

- `WRT-0169`, dated 2026-08-08, type `Інше`, quantity -5. In the write-off ledger a negative quantity is the established inventory-restoration mechanism.
- The warehouse write-off total changed from 6 to 1 and the calculated available balance changed from -5 to 0.
- The 10 packs remain allocated to `OC-FOP-0310`; no inventory was added beyond the confirmed physical count.

## Validation boundary

Validated formulas, costs, and the three new write-off records in the CRM workbook. This report does not constitute production fulfillment or delivery-status verification for `OC-FOP-0310`.
