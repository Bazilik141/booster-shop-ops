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

## Deployment steps (owner)

1. Open the existing approved bound 3D-P Apps Script project; do not use the main CRM project.
2. Replace only `Code.gs` with this folder's updated `Code.gs`.
3. Run `preview3dpApiSetup()` and confirm it reports legacy `O` removal, `Налаштування`, `Фурнітура_довідник`, legacy `H:J` clearing, and `Брак, шт`.
4. Create a named Google Sheets version, then run `setup3dpApi()` once. It is idempotent after the migration.
5. Publish a **new version of the existing web-app deployment**. Keep both existing Script Properties unchanged; do not regenerate or expose tokens.
6. Run the local negative smoke and the updated no-net-change positive audit smoke below, then manually confirm headers, settings values, the K formula, and `_Аудит_API`.

Every source change requires a new deployment version. Editing the script alone
does not update an already deployed web app.

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

Illustrative/example rows are removed from the table actions.

## Write actions (POST)

All POST bodies are JSON sent as `text/plain;charset=utf-8` to avoid a browser
CORS preflight.

- `3dp_write` — one whitelisted non-formula cell; use `expected_current`
- `3dp_append_row` — first business-empty prepared row; formula columns are copied
  from the preceding prepared row and cannot be supplied by the client
- `3dp_print_log_update` — edits an active print-log row; requires an
  `expected_current` entry for every changed field and appends automatic row history
- `3dp_print_log_archive` — reversible soft archive; accepts `expected_status`
  and an optional reason
- `3dp_print_log_restore` — restores an archived row

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
