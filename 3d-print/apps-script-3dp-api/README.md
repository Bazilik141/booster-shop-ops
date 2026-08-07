# 3D-P Apps Script API

This folder contains the source for a new Apps Script web app bound only to the
3D-P Google Sheet:

`https://docs.google.com/spreadsheets/d/1yp15H3YJGkqI4Rx89G4QZHkD9m67gnWh58TsTTi-jjo/edit`

It is separate from the Booster CRM Apps Script project and never uses
`BOOSTER_CRM_TOKEN`.

## Current gate

The owner completed the original deployment, formula repair, and owner-token live
API QA on 2026-08-01. The 2026-08-02 schema-correction source is prepared
locally but is **not deployed** yet: it removes `Номенклатура!O`, resets legacy
plastic/price-per-kg values, and adds the final spool-based cost model. Do not
run 3D-P-006 or 3D-P-007 calculator UI against the live Sheet until the owner
runs the deployment gate below. Reconciliation writes remain a separate explicit
owner gate; Serhiy-token and print-log live proof belong to 3D-P-007.

Do not paste generated token values into Git, this README, a client file, a
screenshot, or a chat message.

## Security model

Two independent Script Properties are required:

- `BOOSTER_3DP_TOKEN` — owner dashboard identity (`dashboard` in the audit log)
- `BOOSTER_3DP_SERHIY_TOKEN` — Serhiy local-server identity (`serhiy` in the audit log)

The caller identity is derived server-side from the matched property. A caller
cannot choose or spoof the audit identity in a request.

Write protection is layered:

1. role-specific hardcoded manual-column whitelist;
2. a live formula-cell check before every cell write;
3. optimistic locking through `expected_current` / `expected_status`;
4. a script-wide lock around every POST action;
5. `_Аудит_API` logging with old/new values;
6. automatic rollback of changed cells if the audit append fails.

`_Аудит_API` is hidden and cannot be read through any API action.

## One-time schema setup

Run `preview3dpApiSetup()` first. It performs anchor checks and returns the plan
without changing the Sheet.

After reviewing the preview, run `setup3dpApi()`. It is idempotent and performs
only the approved changes:

- `Номенклатура!O:O` — removes the legacy `Комбінована амортизація, грн/год` field;
- `Номенклатура!G:J` — renames inputs to per-unit print time/weight and spool weight/price, then clears the legacy plastic/price-per-kg values (owner-approved for the current SKU);
- `Налаштування!B2:B4` — creates editable global constants: `0.17` kW, `4.32` UAH/kWh, `12` UAH/h;
- `Номенклатура!K:K` — uses material from `weight per unit ÷ spool weight × spool price`, plus electricity, amortization, and independently editable fixture price;
- `Друк-лог!E:E` — renames the existing editable defect field to `Брак, шт`; its existing post-production history path is retained;
- `Фурнітура_довідник!A:B` — creates the name/price reference list consumed by calculator dropdowns;
- `Друк-лог!J:J` — adds system state `Активний` / `Архів`;
- `Друк-лог!K:K` — adds the automatic per-row `було → стало` history;
- `Наявність!C:D` — stops archived print-log rows from affecting stock totals;
- normalizes approved manual-input columns to blue font on prepared non-example rows;
- creates and hides `_Аудит_API`.

Before running setup, create a named Google Sheets version such as
`Before 3D-P-008 setup 2026-08-01`. If the first setup produced formula errors in
`Наявність!C:D`, paste the corrected source and run only
`repair3dpAvailabilityFormulas()`; it is idempotent and touches only those formulas.

## Addendum #2: dashboard product and calculator API extension

This local source package also contains Addendum #2. It is **not deployment or
live-QA proof**. It must be deployed only after the final-cost schema correction
(Addendum #1) is live and verified.

Run `preview3dpApiAddendum2()` first. It makes no writes and refuses to proceed
unless all Addendum #1 anchors are present: final `Номенклатура!G:J` and `K1`,
the approved `Налаштування!B2:B4` block, and `Друк-лог!J:K` archive/history
columns. A mismatch returns `ADDENDUM_1_REQUIRED` or
`SETUP_ANCHOR_MISMATCH` without changing the workbook.

After a named Sheet version is created, run `setup3dpApiAddendum2()`. It is
idempotent and only:

- enables owner-only guarded `3dp_write` access to `Налаштування!B2:B4`;
- preserves legacy `Номенклатура!F` business status untouched and adds technical
  `O:P` archive state/history (`Активний` / `Архів`) with `_Аудит_API` trail;
- creates and hides `_Чернетки_партій` with a SKU key plus the five raw batch
  calculator inputs;
- creates and hides `_Коригування_наявності`, an append-only SKU/delta/reason/
  Kyiv-time stock ledger;
- changes only `Наявність!G` formulas so they include that ledger. The stock
  API never overwrites an availability formula cell.

The two internal sheets are intentionally unavailable through `3dp_get_range`.
They are exposed only through bounded specialized actions below.

## Deployment steps (owner)

1. Open the existing approved bound 3D-P Apps Script project; do not use the main CRM project.
2. Replace only `Code.gs` with this folder's updated `Code.gs`.
3. Run `preview3dpApiSetup()` and confirm it reports legacy `O` removal, `Налаштування`, `Фурнітура_довідник`, legacy `H:J` clearing, and `Брак, шт`.
4. Create a named Google Sheets version, then run `setup3dpApi()` once. It is idempotent after the migration.
5. Publish a **new version of the existing web-app deployment**. Keep both existing Script Properties unchanged; do not regenerate or expose tokens.
6. Run the owner-only `setup3dp010()` only after the bounded T emptiness check succeeds; publish a new Web App version containing it.
7. Run the local negative smoke and the updated no-net-change positive audit smoke below, then manually confirm headers, settings values, the K formula, `Продажі!T1`, and `_Аудит_API`.

Every source change requires a new deployment version. Editing the script alone
does not update an already deployed web app.

## 3D-P-010 CRM auto-sale match key

After Addendum #2 is live, run the owner-only `setup3dp010()` editor function
once. It checks that `Продажі!T:T` is empty before writing `T1=CRM row number`;
it returns `T_NOT_EMPTY` and makes no write if any existing T data or formula is
found. The new T column is technical, integer-only, formula-free, and is not
available through generic `3dp_write`. Sales-row creation through
`3dp_append_row` requires the CRM row number in T. The CRM hook matches on the
composite key `Продажі!N + Продажі!T`.

For automatic sale stock adjustments, use reason `auto: CRM order <id> row
<crm-row>`. The adjustment is idempotent by SKU plus exact reason. If stock is
insufficient, the ledger may go negative and the API returns a warning; manual
adjustments still reject negative resulting stock.

## Read actions (GET)

- `3dp_get_row&sheet=<Номенклатура|Наявність>&sku=<SKU>`
- `3dp_get_range&sheet=<sheet>&range=<bounded A1 range>` — maximum 500 cells;
  `_Аудит_API` is denied; Serhiy can read only the bounded open-question block
  in `Легенда`
- `3dp_overview`
- `3dp_skus`
- `3dp_sales`
- `3dp_plyushky`
- `3dp_payouts`
- `3dp_print_log&include_archived=<true|false>`
- `3dp_fixtures` — bounded fixture name/price list for both calculator dropdowns
- `3dp_batch_draft&sku=<SKU>` — owner or Serhiy gets one SKU's five raw
  calculator inputs, or `found:false` with blanks
- `3dp_stock_adjustments&sku=<optional SKU>&reason=<exact reason>&limit=<1..100>` — owner-only,
  bounded latest-first adjustment history; SKU and reason filters are optional

`3dp_skus` hides archived SKUs by default. Pass `include_archived=true` only
for the owner restore view. `3dp_overview` excludes archived SKUs and their
availability totals from active dashboard calculations.

Illustrative/example rows are removed from the table actions.

## Write actions (POST)

All POST bodies are JSON sent as `text/plain;charset=utf-8` to avoid a browser
CORS preflight.

- `3dp_write` — one whitelisted non-formula cell; use `expected_current`
- `3dp_append_row` — first business-empty prepared row; formula columns are copied
  from the preceding prepared row and cannot be supplied by the client; a `Продажі`
  row also requires technical column T with an integer CRM row number
- `3dp_print_log_update` — edits an active print-log row; requires an
  `expected_current` entry for every changed field and appends automatic row history
- `3dp_print_log_archive` — reversible soft archive; accepts `expected_status`
  and an optional reason
- `3dp_print_log_restore` — restores an archived row
- `3dp_batch_draft_save` — owner or Serhiy saves one or more of `quantity`,
  `total_weight_g`, `total_print_time_h`, `spool_weight_g`,
  `spool_price_uah`; every supplied field needs its last-read value in
  `expected_current`
- `3dp_nomenclature_archive` / `3dp_nomenclature_restore` — owner-only,
  reversible SKU status action with `row`, optional `expected_status`, and
  optional reason; direct `Номенклатура!O` system-status writes are blocked
- `3dp_adjust_stock` — owner-only ledger append, requiring `sku`, a short
  `reason`, `expected_current`, and exactly one of integer `delta` or
  non-negative integer `new_value`; manual adjustments reject negative stock,
  while an automatic `auto: CRM order <id> row <crm-row>` adjustment may go
  negative and returns a non-blocking `insufficient_stock` warning
- `3dp_setup_addendum2` — owner-only invocation of the strict, idempotent
  Addendum #2 setup. It returns `already_applied:true` only when it made no
  schema change; the live positive smoke uses it as a preflight before writes.
- `3dp_setup_3dp010` — owner-only, bounded setup for `Продажі!T1:T:lastRow`;
  it writes `CRM row number` only when T is empty and otherwise returns `T_NOT_EMPTY`.

`3dp_write` permits the owner only at `Налаштування!B2:B4`; the Serhiy token is
rejected with `COLUMN_NOT_ALLOWED`. New SKU rows receive technical
`Номенклатура!O=Активний`; archive state is never a generic writable column, while
legacy business field `F` remains unchanged.

There is intentionally no physical-delete action.

### Serhiy write scope

- `Номенклатура`: `G,H,I,J,L,M,N` (no plastic type or per-SKU combined-rate field)
- `Друк-лог`: `A,B,C,D,E,F,H,I`, using append/update/archive/restore actions

Formula columns `Номенклатура!K` and `Друк-лог!G` are always rejected.

## Owner live smoke tests

Keep the URL and token only in local environment variables:

```powershell
$env:BOOSTER_3DP_URL='https://script.google.com/macros/s/.../exec'
$env:BOOSTER_3DP_TOKEN='paste-owner-token-locally'
Invoke-RestMethod "$env:BOOSTER_3DP_URL?action=3dp_overview&token=$env:BOOSTER_3DP_TOKEN"
```

For Addendum #2, run this separate no-net-change live smoke after publishing a
new Web App version:

```powershell
.\3d-print\apps-script-3dp-api\tests\live-addendum2-smoke.ps1
```

It prompts for the `/exec` URL and both tokens when their local environment
variables are absent. Token input is hidden; no secret-filled PowerShell command
or persistent environment variable is required. It performs one same-value owner
settings write (one audit row, zero net business-data change), and proves Serhiy
settings denial, generic SKU-status denial, bounded draft/stock-history reads,
and the stock stale-write guard. It intentionally does not create a test draft,
archive a real SKU, or alter stock.

For controlled API-only positive QA, first deploy the source containing
`3dp_setup_addendum2`, then run against an owner-selected test SKU:

```powershell
.\3d-print\apps-script-3dp-api\tests\live-addendum2-positive-smoke.ps1 `
  -TestSku 'YOUR-TEST-SKU' `
  -StockDelta 1 `
  -ConfirmLiveWrites
```

The runner prompts for the `/exec` URL and owner token if absent locally. It
prints JSON snapshots before and after every control point, and asserts all four:

1. `3dp_setup_addendum2` returns `already_applied:true` before data writes;
2. all five batch-draft raw values save and fresh-read through the API;
3. the selected active SKU is archived, absent from active `3dp_skus`, visible
   through `include_archived=true`, then restored to active;
4. `Наявність!G` changes by the supplied non-zero delta and a bounded ledger
   read returns the exact generated reason and delta.

This is intentionally data-changing. It saves the supplied batch-draft values,
creates archive and restore audit/history entries, and appends one permanent
stock-ledger adjustment. It never picks a SKU automatically and does not create
an automatic counter-adjustment; select a dedicated test SKU and a deliberate
small delta.

The required negative tests after deployment are:

1. formula cell `Номенклатура!K3` → `FORMULA_CELL`;
2. a non-whitelisted column for the caller → `COLUMN_NOT_ALLOWED`;
3. an incorrect `expected_current` → `STALE_WRITE`.

Run all three without changing a live cell:

```powershell
.\3d-print\apps-script-3dp-api\tests\live-negative-smoke.ps1
```

The script discovers a real SKU, uses only the owner token from the current
process environment, asserts the three error codes, and reports
`live_cells_changed=0`. It never prints the token.

The positive audit smoke reads one real SKU and re-writes its existing fixture value
to the same cell using `expected_current`; it creates one audit row but causes zero net
business-data change. It no longer uses the removed `O` column.

Do not perform reconciliation writes until the owner approves the separate diff
report. Use the API, not a direct Sheet edit, for every approved reconciliation
cell so `_Аудит_API` proves the first real write path.

## Local validation

Run with the bundled or a standard Node.js runtime:

```powershell
node .\3d-print\apps-script-3dp-api\tests\api.test.mjs
```

The test is a local Apps Script mock. Passing it proves source behavior, not an
Apps Script deployment or live Google integration.