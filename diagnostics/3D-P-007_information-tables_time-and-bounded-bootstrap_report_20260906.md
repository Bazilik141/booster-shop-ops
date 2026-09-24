# Codex Report — 3D-P-007: information tables, human print time, and owner bootstrap limit

Date: 2026-09-06

## Outcome

The Serhiy dashboard now gives every data table inside `Інформація` its own sortable headers and column-visibility picker. Tables whose rows can be resolved to a SKU also expose a product-type filter. Print-time output uses only `X год XX хв`. The owner dashboard's `3dp_bootstrap` candidate no longer routes its fixed analytics projection through the unrelated 500-cell public range-request limit.

## Root causes

- `public/app.js` rendered `Інформація` through static `objectTable` / `matrixTable` HTML. Headers were plain text, every discovered column was always rendered, and no SKU-to-type join existed for sales, analytics, or bonus rows.
- The shared print-time helper returned decimal hours plus a human form, so table values such as `0.216666667` remained visible.
- `bootstrapAction3dp_` built `Аналітика_SKU!A1:N<row>` and called the public `getRangeAction3dp_`. At row 36, 14 columns exceed `API_3DP.maxRangeCells = 500`, making the whole owner dashboard fail. This was a mismatch between a fixed audited internal projection and a guard for caller-controlled ranges.
- The local table CSS forced `white-space: nowrap` on data cells. Browser QA showed a long product name overlapping adjacent columns at the medium breakpoint.

## Implementation

- Added `public/information-tables.js` with deterministic flattening, SKU-type resolution, per-table type filtering, numeric/text sorting, column visibility, and analytics matrix conversion.
- Preferences are stored separately per table in browser `localStorage`.
- Payouts receive sorting and column selection; they do not show a type filter because payout rows contain no SKU/product relation.
- Time headers matching `Час друку ... год` render through the shared human formatter. Minutes are zero-padded.
- Updated the owner dashboard to use the same human-only time formatter.
- Added `dashboardAnalyticsAction3dp_`, limited to the existing fixed 100-row by 14-column owner projection. The public `3dp_get_range` limit remains exactly 500 cells.
- Updated the EXE builder to include `shared/print-time.js`; prior updater payloads did not replace that shared file.
- Changed the existing source cell rule instead of stacking a CSS override. No `!important`, fixed/absolute positioning, or delayed UI workaround was added.

## Verification

- Serhiy local tests: 22/22 passed.
- 3D-P API focused suite: 11/11 passed, including a 40-row owner bootstrap and a matching external `A1:N40` rejection with `RANGE_TOO_LARGE`.
- Browser interaction QA with isolated fake data:
  - type `Фігурка` hid the `Брелок` row;
  - quantity sorted ascending and descending;
  - hiding `РРЦ фактична` removed only that displayed column;
  - `0.216666667` rendered as `0 год 13 хв` and `1.016666667` as `1 год 01 хв`;
  - 360 px, 900 px, and 1440 px layouts remained usable with horizontal table scrolling;
  - long names wrap inside their cell rather than overlap neighbouring columns.
- New EXE end-to-end QA passed against a previous package: old application file was backed up, portable Node hash stayed unchanged, new table module and shared formatter were installed, and the updated local server returned HTTP 200.
- Rebuilt full portable ZIP passed the existing encoding/runtime/reference checks and served the new table module with HTTP 200 after clean extraction.

## Broader test-suite note

The repository-wide dashboard sweep is not currently green: 11 existing tests fail on missing retired fixtures or expectations already out of sync with the dirty workspace (for example the removed 2026-07 CRM CSV and older stock-adjustment/UI anchors). The focused tests for every changed contract above pass. Those unrelated legacy failures were not edited in this scope.

## Artifacts

- `3d-print/serhiy-local-server/dist/Booster-3DP-Оновлення_202609063.exe`
  - SHA-256: `70be39585b5e87a599f47e1ed4f71cd46e0adf9b334a7862a69bd1245f358738`
- `3d-print/serhiy-local-server/dist/Booster-3DP-Serhiy_Node-v24.19.0_20260906.zip`
  - SHA-256: `b403b9ae839275a52bb810d7abc906349bcf6def32bf5d19ed4ab8c525a7933f`
- `3d-print/serhiy-local-server/dist/ПЕРЕДАТИ-СЕРГІЮ_оновлення-таблиць_20260906.zip`
  - SHA-256: `5e5b18898b7aad89e3536966f93a32c0798e14e244d0281120675c0b1826a00e`

## Deployment boundary

The Serhiy EXE is ready for owner delivery. `Code.gs` and the owner dashboard file are local candidates only. No Apps Script publication, Sheet write, live API call, commit, or push was performed. The owner's live bounded-read error remains until the updated `Code.gs` is copied to the bound project and published as a new Web App version.

## Owner QA

1. Update Serhiy with the new EXE and verify the five `Інформація` data tables.
2. Publish the local `3d-print/apps-script-3dp-api/Code.gs` candidate as a new Web App version.
3. Reload the local owner dashboard and confirm `3D-P API тимчасово недоступний` is gone.
4. If the error remains, stop and capture the full error; do not increase `maxRangeCells` as a workaround.
