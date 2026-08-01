# 3D-P Apps Script API

This folder contains the source for a new Apps Script web app bound only to the
3D-P Google Sheet:

`https://docs.google.com/spreadsheets/d/1yp15H3YJGkqI4Rx89G4QZHkD9m67gnWh58TsTTi-jjo/edit`

It is separate from the Booster CRM Apps Script project and never uses
`BOOSTER_CRM_TOKEN`.

## Current gate

The owner completed deployment, formula repair, and owner-token live API QA on
2026-08-01. `Наявність!C2:D15` has valid semicolon formulas and numeric results.
The owner-token smoke test passed all three negative guards, then performed a
guarded `Номенклатура!O3` `blank → 0 → blank` write/restore; `_Аудит_API`
contains both records. Reconciliation writes remain an explicit owner gate.
Serhiy-token and print-log live proof belongs to 3D-P-007.

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

- `Номенклатура!O:O` — adds `Комбінована амортизація, грн/год` as a manual field;
- `Номенклатура!K:K` — updates the cost formula to
  `material + hardware + amortization rate × print hours`;
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

1. Open the approved live Sheet.
2. Open **Extensions → Apps Script**. Create a new bound script project; do not
   reuse the main CRM project.
3. Replace `Code.gs` with this folder's `Code.gs`.
4. Enable the manifest in Apps Script project settings and replace
   `appsscript.json` with this folder's manifest.
5. Run `preview3dpApiSetup()` and inspect the returned object/execution log.
6. Create a named Sheet version, then run `setup3dpApi()` and authorize the
   spreadsheet permissions.
7. Generate two independent 256-bit tokens locally. PowerShell command (run it
   twice and keep the outputs private):

   ```powershell
   [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLower()
   ```

8. In **Project settings → Script Properties**, add the two properties listed
   in the Security model section.
9. Choose **Deploy → New deployment → Web app**:
   - Execute as: **Me**
   - Who has access: **Anyone**
10. Copy the `/exec` URL. The URL is not a secret; the tokens are.

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

- `Номенклатура`: `G,H,I,J,L,M,N,O`
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
