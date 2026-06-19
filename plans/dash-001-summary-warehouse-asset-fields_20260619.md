# DASH-001 — Summary warehouse and asset fields

Date: 2026-06-19

## Corrected business definition

- `warehouse_cost`: FIFO cost of every remaining unit physically in UA.
- `asset_cost`: cost of every unsold unit: UA warehouse + transit + Japan + won.
- `potential_profit_warehouse`: potential profit of the same UA FIFO remainder.
- `asset_potential_profit`: potential profit of every unsold unit.

Profit uses `(RRC - management_cost * 1.05) * remaining_qty` and includes
negative positions. Lots without RRC cannot contribute to potential profit.

## Implementation

Updated only `apiSummary_()` in the CRM spreadsheet mirror.

- FIFO still excludes transit/Japan/won lots from warehouse consumption.
- Remaining quantities in old rows marked `Продано` are no longer dropped from
  warehouse totals.
- Non-UA asset cost is added to the FIFO warehouse cost for total asset cost.
- Warehouse and total asset profit now use the same remaining quantities and
  cost basis.

## Expected values from the current data

- `warehouse_cost`: `26386.27`
- non-UA asset cost: `44647.52`
- `asset_cost`: `71033.79`
- `potential_profit_warehouse`: `25304.42`
- `asset_potential_profit`: `60724.53`, after RRC `2700` for
  `YGO-JP-WPP5-BBX` is actually present in `РРЦ!E47`

The owner's manual full-lot sum `78736.45` is higher than `asset_cost` by
`7702.66`, which is the cost of units already consumed from partially sold
lots.

## Verification

- Spreadsheet readback confirmed the corrected block and preserved
  `apiOrders_()`.
- The live web app is unchanged until the mirror is copied into Apps Script and
  a new web-app version is deployed.
- At verification time `РРЦ!E47` was still blank, so the live API could not yet
  use the stated RRC `2700`.
