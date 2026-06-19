# DASH-WRITE-004 — Edit recent accounting records

Date: 2026-06-19

## Apps Script mirror

Added GET actions:

- `recent_sales`
- `recent_purchases`
- `recent_writeoffs`

Added POST actions:

- `update_sale`
- `update_purchase`
- `update_writeoff`

The endpoints discover the real header row and columns, return the last 1-50
non-empty rows, and validate that `row_index` points below the header and still
contains a record.

Purchase supplier is parsed from and written back to the existing
`Постачальник:` prefix in `Примітка`. Purchase cost editing updates the source
columns used by `apiAddPurchase_` (`Вартість лоту` and Japan fees), preserving
the calculated cost formulas.

## Dashboard

Updated `dashboard/booster-dashboard.html`:

- added recent-record tabs below the accounting write forms;
- added lazy-loaded compact tables;
- added inline pre-filled edit forms;
- saving reloads the active table and invalidates overview/stock cache.

## Verification

- dashboard JavaScript syntax: passed;
- `git diff --check`: passed;
- browser smoke with local mock API:
  - all three tabs loaded;
  - each table rendered one row;
  - sale row opened with correct pre-filled values;
  - mocked save closed the form and showed success;
  - no console errors.
- Apps Script mirror readback confirmed routes and function blocks.

## Manual step

Copy the updated `Apps_Script_код` into the bound Apps Script project and
deploy a new web-app version. Then run controlled GET tests and one targeted
POST update on a disposable/test row before editing production records.
