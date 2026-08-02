# 3D-P Serhiy local server

A standalone local-only UI for Serhiy. It is an API consumer: the browser talks
only to this localhost process, and this process talks only to the deployed
3D-P Apps Script Web App. It has no Google Sheets/Drive client, main-CRM URL,
or `BOOSTER_CRM_TOKEN`.

## What it does

- Shows the bounded 3D-P overview, active SKU/availability, active print log,
  fixture list, payout-status rows, and only `Легенда!A32:A38` (known open
  questions).
- Uses the final batch model: session total weight and print time are divided by
  batch quantity before per-unit values are written to `Номенклатура!G:J`.
- Loads and saves the five raw batch values with Addendum #2's
  `3dp_batch_draft` / `3dp_batch_draft_save`, so a selected SKU's draft
  survives refreshes and re-selection:
  `quantity`, `total_weight_g`, `total_print_time_h`, `spool_weight_g`,
  `spool_price_uah`.
- Reads the three global settings from `Налаштування!A1:C4` and displays them
  read-only. Serhiy cannot edit those owner-only constants.
- Adds a print session through `3dp_append_row`, and changes `Брак, шт` only
  through the history-preserving `3dp_print_log_update` action.
- Assigns optional fixture price through the API. Fixture is a later,
  independent step; it is not part of the base formula and does not block a
  batch save.

There is no plastic-type field and no packaging-cost logic. Packaging is
3D-P-010, not this local server.

## Credential boundary

The process needs exactly these local environment variables:

- `BOOSTER_3DP_URL` — deployed Apps Script Web App URL ending in `/exec`.
- `BOOSTER_3DP_SERHIY_TOKEN` — a Serhiy-only credential provisioned in Apps
  Script separately from the owner/dashboard token.

Never set `BOOSTER_CRM_TOKEN` here. Do not reuse the owner/dashboard 3D-P
token, put a real token in `.env.example`, commit one, display one in the
browser, or send one in chat/screenshots. The server binds only to `127.0.0.1`;
it is not reachable from the LAN.

## Start on Serhiy's PC

Node.js 18+ is required. No `npm install` is needed: this package uses only
Node's built-in modules.

In PowerShell, from this directory, define the variables for this PowerShell
session and run the server:

```powershell
Set-Location 'C:\path\to\booster-shop-ops\3d-print\serhiy-local-server'
$env:BOOSTER_3DP_URL = 'https://script.google.com/macros/s/DEPLOYMENT_ID/exec'
$env:BOOSTER_3DP_SERHIY_TOKEN = 'Serhiy-only token from the owner'
npm start
```

Open `http://127.0.0.1:3107`. Stop it with `Ctrl+C`; session environment
variables disappear when that PowerShell window closes.

## Normal workflow

1. Select an active SKU. Its stored five-field batch draft loads automatically.
2. Enter or amend batch quantity, total product weight, total print time, spool
   weight, and spool price. Calculate and verify the per-unit values and three
   cost lines.
3. Click **Зберегти чернетку і per-unit у SKU**. The API saves the raw draft
   first, then writes per-unit values to `G:J`. A concurrent edit returns
   `STALE_WRITE`; refresh instead of trying to overwrite it.
4. Add the real print session through **Друк-лог: нова сесія**. Correct
   post-production defects with **Зберегти брак**, which preserves API history.
5. Select fixture only after production if it applies. It can be cleared later
   and does not change the base calculation.

## Local verification

```powershell
Set-Location 'C:\path\to\booster-shop-ops\3d-print\serhiy-local-server'
npm test
node --check .\server.mjs
node --check .\public\app.js
```

`npm test` uses a fake localhost 3D-P API. It verifies the local server sends
only the Serhiy credential, reads bounded payout/Legend views, round-trips the
five raw batch inputs, writes only computed per-unit `G:J`, and uses the
specialized print-log actions. It does not call the live endpoint or change
business data.

## Owner + Serhiy live QA

1. Start the server with the distinct Serhiy token and confirm the browser only
   calls `127.0.0.1`; real data must load from the local server, not a direct
   Sheets/Drive request.
2. On a non-production test SKU, save a known batch (for example 36 units,
   180 g and 18 h). Refresh/reselect the SKU and confirm all five raw values
   reload, while `G:J` contain `0.5 h`, `5 g`, `1000 g`, and `800 грн` per unit
   inputs where applicable — never 18 h or 180 g as per-unit data.
3. Create a test `Друк-лог` row and change `Брак, шт`; owner checks the live
   row history and `_Аудит_API` identity is `serhiy`.
4. Check fixture change/clear is independent. Confirm payout/open-question
   views show only the bounded data expected for this tool.

## Recovery and limits

Rollback is stopping the local process. It has no dashboard, CRM, or
Sheet-schema side effects. Approved API writes remain recoverable by the owner
from `_Аудит_API`.

The API's generic `3dp_write` action updates one cell at a time. The batch
save therefore has a known bounded risk: if a later per-unit cell becomes stale
after the raw draft has saved, the UI stops and reports the error; it never
retries blindly or falls back to direct Sheet access. An atomic batch action
would be a separate 3D-P-008 change.
