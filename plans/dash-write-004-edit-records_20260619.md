# DASH-WRITE-004 - Accounting form parity

Date: 2026-06-19

## Dashboard write forms

`dashboard/booster-dashboard.html` now mirrors the operational spreadsheet
forms:

- sale: order/customer/payment/shipping fields, up to 10 item rows, discount,
  packaging, shop-paid delivery, and Mystery Box component write-offs;
- purchase: order reference, total lot cost, status, Japan fees in JPY, URL,
  note, and up to 3 SKU rows with automatic or manual line-cost allocation;
- write-off: date, type, reason, note, expected-quantity control, and up to 10
  SKU rows.

## Purchase updates

The purchases table now uses checkbox selection:

- select from 1 to 5 active lots;
- enter an individual Japan fee in JPY for each selected lot;
- apply shared tracking number, Ukraine delivery date, Ukraine delivery cost in
  JPY, status, and note;
- submit all selected lots in one `update_purchase` request.

## Display refinements

- sales show the last four TTN characters plus the delivery service; missing
  TTN is displayed as `NTTN`;
- purchase rows use Order Ref as the primary identifier and are sorted oldest
  to newest;
- Japan lot fees are displayed in JPY;
- lots with status `В дорозі` are rendered below other lots and grouped into
  parcel blocks by tracking number;
- SKU options display current warehouse stock;
- Mystery Box component rows show only in-stock Booster SKUs from the selected
  Mystery Box TCG, with stock greater than one.

## Mystery Box cost

- manual dashboard sales recalculate Mystery Box cost after component
  write-offs are created;
- OpenCart Mystery Boxes keep the existing initial fixed fallback cost;
- a later manual write-off whose general note contains the exact order number
  triggers recalculation for that Mystery Box order;
- PRRO cost uses the linked write-off PRRO totals;
- management cost uses linked write-off management totals, automatic
  consumables, and direct order expenses from `Витрати`;
- packaging, shipping, payment and marketplace commissions remain in their
  existing dedicated sales columns to avoid double counting.

## Apps Script mirror

Updated source-copy functions:

- `apiAddSale_`
- `apiAddPurchase_`
- `apiAddWriteOff_`
- `apiUpdatePurchase_`

The add endpoints now follow the same validation and allocation rules as the
spreadsheet forms. `apiUpdatePurchase_` accepts `lots` with a maximum of five
unique lot IDs and allocates shared Ukraine delivery proportionally by lot
cost.

The OpenCart/Telegram routing and `upsertOpenCartOrder_` remain in place.

## Verification

- dashboard JavaScript syntax: passed;
- Apps Script replacement block syntax: passed locally;
- exact source-copy readback confirmed the new block at row 810 and
  `upsertOpenCartOrder_` at row 1113;
- local browser smoke:
  - full sale/purchase/write-off fields rendered;
  - sale and write-off limits stopped at 10 rows;
  - purchase limit stopped at 3 rows;
  - purchase cost allocation produced 100/200/300 for quantities 1/2/3 and
    total 600;
  - two lots opened one batch-update editor with two individual JPY fee fields;
  - sale TTN rendered as `9999 · НП`, missing TTN as `NTTN · УП`;
  - One Piece Mystery Box components showed only the eligible One Piece Booster
    with stock above one;
  - transit lots sharing one tracking number rendered in one parcel block;
  - no console errors;
- no live write endpoint was called.

The isolated Mystery Box cost smoke produced PRRO `300` and management `355`
from component costs `300/330`, consumables `5`, and direct expense `20`.

## Manual step

`Apps_Script_код` is a source mirror. Copy the updated code into the bound Apps
Script project and deploy a new web-app version before production testing.
