# 3D-P Serhiy local server

Local-only UI for Serhiy. The browser communicates only with the Node process on
`127.0.0.1`; the process consumes the Serhiy-projected 3D-P Apps Script API. It
has no Google Sheets client, Drive client, main-CRM URL, or CRM credential.

## Three zones

### Calculator

- Loads the projected bootstrap and the four `Налаштування!B2:B5` values.
- Saves the five raw batch-draft inputs and writes only the established per-unit
  values to `Номенклатура!G:J`.
- Displays both the unchanged base cost and the defect-adjusted cost used by
  `Номенклатура!K`: `base * (1 + planned defect fraction)`.
- Allows edits to the four projected settings with `expected_current`; bounds
  remain authoritative in Apps Script. The Serhiy-only settings journal is
  available under the settings toggle.

### Products

- Lists projected products and availability; write controls include active SKUs
  only.
- Edits actual RRP, buyout price, and model URL through the journalled Q/R/S API
  grant.
- Saves fixture price, records an actual counted stock value through
  `new_value`, and logs manufactured batches through the active-SKU-aware,
  idempotent manufacture action.
- Creates nomenclature drafts. A draft receives a `DRAFT-` key. The returned
  prefix/category is shown only as an owner suggestion; the UI never presents a
  complete article as assigned.
- Shows the active projected print log.

### Information

- Builds attention signals from zero stock, recorded defects, and missing print
  time.
- Renders projected analytics, all products, sales, payouts, and marketing gifts
  using the headers returned by the API.
- Payout rows expose Serhiy's two append-once acknowledgements and the explicit
  correction route. An existing acknowledgement is displayed as recorded
  instead of offering the same acknowledgement action again.

The UI contains no shop CRM synchronization controls and no owner-only payout or
article-assignment actions.

## Distribution and credentials

The Serhiy distribution is assembled with portable Node.js 24.19.0 LTS for
Windows x64. Node is copied only into the generated zip; it must not be placed
in this repository. The package uses Node built-ins only.

`distribution/Запустити.bat` delegates to `distribution/launcher.ps1`. On first
run the launcher asks once for the Apps Script URL and a masked Serhiy token,
persists them as Windows user environment variables through `setx`, and also
sets them for the current process. Later launches reuse those values. A rotated
token is entered through `distribution/Змінити токен.bat`.

```powershell
.\scripts\build-serhiy-3dp-package.ps1 `
  -NodePath 'C:\Downloads\node-v24.19.0-win-x64.zip' `
  -OutputDirectory 'C:\Downloads'
```

The launcher preflights the credential, refuses a non-Serhiy projection, checks
that fixed port 3107 is free, starts the server, and opens
`http://127.0.0.1:3107`. Closing its console window stops the local process.
Never reuse the owner credential, add a real credential to a file, display it,
or copy it into a screenshot or report. `.env.example` is documentation only;
the runtime intentionally does not load `.env` files.

## Local verification

```powershell
npm test
node --check .\server.mjs
node --check .\public\app.js
```

The test suite uses a fake localhost API. It covers the two projected bootstrap
actions, exact `B2:B5` settings reads, all WP1b/WP1c routes, stable manufacture
request IDs, `new_value` stock semantics, the adjusted K-cost example, and
unchanged propagation of `RANGE_NOT_PROJECTED`, `READ_PROJECTION_FORBIDDEN`,
`STALE_WRITE`, and `FORBIDDEN`. It never calls the live workbook.

## Live QA boundary

Every write in a manual QA session reaches the production 3D-P workbook. Use a
designated test SKU and a named workbook copy before testing settings, Q/R/S,
stock correction, manufacture logging, draft creation, or payout
acknowledgements. Local green tests prove the client contract only; installation
and production-identity evidence belong to WP3.

Stopping the Node process rolls back the local UI only. Already accepted API
writes remain in the workbook and are recoverable through the API audit trails.
