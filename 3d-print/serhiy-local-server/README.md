# 3D-P Serhiy local server

Small local-only UI for Serhiy. It talks **only** to the deployed 3D-P Apps
Script web app; it does not have a Google Sheets/Drive client, main CRM URL, or
`BOOSTER_CRM_TOKEN`.

## What it does

- Shows the 3D-P overview, SKUs/availability, active print log, and fixture list.
- Calculates the final batch model. Batch total weight and time are divided by
  quantity before `Номенклатура!G:H` per-unit values are saved.
- Reads the three global settings from `Налаштування!A1:C4`; the local UI cannot
  edit them.
- Writes only through the deployed API as the `serhiy` identity:
  `Номенклатура!G,H,I,J,N` and approved `Друк-лог` actions.
- Adds a print session and changes `Брак, шт` through the API's specialized
  history-preserving action.

There is no plastic-type field. Fixture is a later, independent assignment:
the UI resolves its selected name from `Фурнітура_довідник` and saves the
reference price into `Номенклатура!N`.

## Prerequisites

- Node.js 18 or newer (`node --version`). No `npm install` is needed.
- The 3D-P web app deployed after the 3D-P-008 schema correction.
- A **separate** Apps Script property `BOOSTER_3DP_SERHIY_TOKEN`. Do not reuse
  the dashboard token and do not paste either token in this repository, chat,
  screenshots, or browser fields.

## Run on Serhiy's PC

In PowerShell, from this folder, set the two values only for the current local
process. The actual token must never be copied into this README or `.env.example`.

```powershell
Set-Location 'C:\path\to\booster-shop-ops\3d-print\serhiy-local-server'
$env:BOOSTER_3DP_URL='https://script.google.com/macros/s/.../exec'
$env:BOOSTER_3DP_SERHIY_TOKEN='Serhiy-only token from the owner'
npm start
```

Open `http://127.0.0.1:3107`. The server binds to `127.0.0.1` only, so it is
not exposed to other devices on the network. Stop it with `Ctrl+C`.

## Normal workflow

1. Pick SKU, enter session quantity, total product weight, total print time,
   spool weight, and spool price. Click **Розрахувати**.
2. Check per-unit values and the three cost lines. Click **Зберегти per-unit у
   SKU** only when they are correct.
3. Add the actual session to **Друк-лог**. Defect quantity is kept separate from
   the cost formula.
4. After production, choose a fixture if one is needed. It is optional and can
   be changed or cleared later.
5. To correct a defect count, use its row's **Зберегти брак** button. A stale
   value is rejected; refresh and retry instead of overwriting someone else's
   change.

## Checks before owner/Serhiy QA

```powershell
Set-Location 'C:\path\to\booster-shop-ops\3d-print\serhiy-local-server'
npm test
node --check .\server.mjs
node --check .\public\app.js
```

## Live QA (owner + Serhiy)

1. With the Serhiy token, open the local UI and confirm real SKU, availability,
   fixture list, and active log load. The browser must only call `127.0.0.1`;
   the server is the sole API caller.
2. Use a non-production test SKU/session: calculate a batch (for example,
   36 units) and verify the displayed per-unit weight/time are totals divided
   by 36. Save it and refresh; confirm `Номенклатура!G:J` match those per-unit
   values, not batch totals.
3. Add one test `Друк-лог` row, then modify `Брак, шт`. Confirm the row updates
   and its API history contains `було → стало`.
4. Select a fixture and confirm only `Номенклатура!N` changes; clear it and
   confirm it is independently reversible.
5. Check `_Аудит_API` as owner: successful writes should identify `serhiy`.

## Limits and recovery

The existing API permits one `3dp_write` cell per request, so a per-unit SKU
save is four audited, optimistic-lock writes. If another editor changes a SKU
between those writes, the UI stops with `STALE_WRITE` and tells Serhiy to refresh.
Already-applied cells remain individually visible in `_Аудит_API`; no silent
retry or direct-Sheet fallback exists. A future atomic batch-write action would
be a separate 3D-P-008 scope change, not part of this server.

Rollback is simply stopping this local server. It has no dashboard, CRM, or
Sheet-schema changes. Any approved data correction is recoverable from
`_Аудит_API` by the owner.
