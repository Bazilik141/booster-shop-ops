# Codex Report — 3D-P-007: Serhiy local server

Date: 2026-08-02

## Scope

Implemented a standalone local-only Node.js server that is a pure consumer of
the deployed 3D-P Apps Script API. It has the required batch calculator,
fixture workflow, print-log creation, and post-production defect edit. It does
not edit `Code.gs`, the dashboard, main CRM, or the Google Sheet directly.

The final cost model is applied to per-unit values only:

- material = `(weight per unit / spool weight) * spool price`;
- electricity = `0.17 * time per unit * electricity price`;
- amortization = `amortization rate * time per unit`.

The three constants are read from the API settings block and are never editable
in the local UI. Fixture is resolved by name from `3dp_fixtures`, then its
reference price alone is written to `Номенклатура!N` as an independent later
action. Defect count is deliberately outside the cost formula.

## Files touched

```
3d-print/serhiy-local-server/package.json
3d-print/serhiy-local-server/.env.example
3d-print/serhiy-local-server/server.mjs
3d-print/serhiy-local-server/lib/calculator.mjs
3d-print/serhiy-local-server/public/index.html
3d-print/serhiy-local-server/public/app.js
3d-print/serhiy-local-server/public/styles.css
3d-print/serhiy-local-server/tests/calculator.test.mjs
3d-print/serhiy-local-server/README.md
diagnostics/3D-P-007_serhiy-local-server_report_20260802.md
```

## Local validation

```
npm test
3 passed, 0 failed

node --check .\server.mjs
node --check .\public\app.js
git diff --check
```

The calculation test uses a 36-unit session and proves 180 g / 18 h become
5 g / 0.5 h per unit before material, electricity, and amortization are
computed. A separate localhost-only smoke started the server with a fictitious
API URL/token and confirmed `GET /` returned `200 text/html`; it did not call
the remote API.

## Credential and network boundary

The server requires only local process variables:

- `BOOSTER_3DP_URL`
- `BOOSTER_3DP_SERHIY_TOKEN`

It binds only to `127.0.0.1`. The browser only calls this local server; the
server is the sole caller of the 3D-P web app. No file or endpoint accepts,
stores, logs, or displays a CRM token or dashboard token.

## Known API boundary

The deployed API supplies only one `3dp_write` cell per request. Therefore a
batch SKU save is four separately audited, optimistic-lock writes to `G:J`.
If a concurrent change produces `STALE_WRITE`, the UI stops and asks the user
to refresh; it never retries blindly or falls back to a direct Sheet write.
An atomic multi-cell API action would be a separate 3D-P-008 change and is out
of scope here.

## Owner + Serhiy live QA

- [ ] Set the distinct Serhiy API token locally, start the server, and verify real SKU/availability/fixture/print-log data load.
- [ ] Calculate a test batch and confirm UI values and saved `Номенклатура!G:J` are per unit, not batch totals.
- [ ] Add a non-production `Друк-лог` session through the UI; confirm it appears live.
- [ ] Change its `Брак, шт`; confirm the `було → стало` history and the `serhiy` audit identity.
- [ ] Assign and clear a fixture; confirm only `Номенклатура!N` changes and that base cost calculation still works without it.

## Rollback

Stop the local process with `Ctrl+C`. The server has no dashboard, CRM, or
Sheet-schema side effect. Individual live writes are recoverable from
`_Аудит_API` by the owner.
## Explicitly omitted pending owner authorization

Payout status and the bounded open-question block in `Легенда` are intentionally
not requested from or displayed by this server: they can expose financial or
internal planning information to Serhiy beyond the production workflow. The
server currently shows only SKU/availability, the final calculator, fixtures,
and active `Друк-лог` data. Adding either omitted view requires separate,
explicit owner authorization and is not implied by the separate Serhiy API
token.

