# DASH-WRITE-002 - Apps Script Write API

Date: 2026-06-18
Status: ready for Apps Script copy/deploy and owner QA

## Implemented

- Added `doPost(e)` action routing:
  - `add_sale` -> `apiAddSale_`
  - `add_purchase` -> `apiAddPurchase_`
  - `add_writeoff` -> `apiAddWriteOff_`
- Preserved the existing Telegram branch, token check, script lock, and OpenCart fallback.
- Added payload validation and direct writes to:
  - `Продажі`
  - `Закупки`
  - `Списання`
- Dates are written as real `Date` values.
- Purchase items are fully validated before the first sheet write.
- Added row-capacity checks for sales, purchases, and write-offs.
- Preserved the existing `upsertOpenCartOrder_` implementation byte-for-byte in its functional lines.

## Mirror locations

- `doPost(e)`: `Apps_Script_код!A751`
- `apiNormalizeDateValue_`: `Apps_Script_код!A796`
- `apiAddSale_`: `Apps_Script_код!A804`
- `apiAddPurchase_`: `Apps_Script_код!A845`
- `apiAddWriteOff_`: `Apps_Script_код!A874`
- `upsertOpenCartOrder_`: `Apps_Script_код!A898`

## Verification

- Full Code.gs syntax parse: `ok`
- Total parsed code lines: `2308`
- Function occurrence checks: one each
- Mock smoke tests: `ok`
  - sale write
  - purchase write
  - write-off write
  - empty sale items validation
  - purchase validation before writes
  - bad-token response
  - all three action routes
  - OpenCart no-action fallback

## Remaining manual step

The spreadsheet mirror is updated. Copy the Code.gs block from `Apps_Script_код` into the bound Apps Script project, save it, deploy a new web-app version, then run one controlled POST per action.
