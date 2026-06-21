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
  - no console errors;
- no live write endpoint was called.

## Manual step

`Apps_Script_код` is a source mirror. Copy the updated code into the bound Apps
Script project and deploy a new web-app version before production testing.
