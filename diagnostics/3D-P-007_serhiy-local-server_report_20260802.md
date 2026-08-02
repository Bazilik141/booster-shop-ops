# Codex Report — 3D-P-007: Serhiy local server

Date: 2026-08-02

## Outcome

Prepared an isolated, local-only Node.js server in
`3d-print/serhiy-local-server/`. It consumes the deployed 3D-P Apps Script API
and does not edit `Code.gs`, the dashboard, `ROADMAP_FLOW`, Notion, the Google
Sheet directly, CRM, or any deployment.

The package now targets the deployed Addendum #2 contract rather than the
pre-addendum G:J-only workflow:

- selected SKU loads its raw batch draft with `3dp_batch_draft`;
- save persists all five raw values atomically for that draft through
  `3dp_batch_draft_save`, then writes the derived per-unit values to
  `Номенклатура!G:J` using guarded `3dp_write` calls;
- the final formula divides session totals by quantity before material,
  electricity, and amortization are calculated;
- global settings remain read-only and are loaded from `Налаштування!A1:C4`;
- the local view reads active SKUs/availability, active print log, fixtures,
  payouts, and only bounded `Легенда!A32:A38` questions;
- print creation uses `3dp_append_row`; defect edits use the specialized,
  history-preserving `3dp_print_log_update` route.

No plastic-type field, packaging-cost calculation, direct Sheet access,
owner-only settings edit, archive/restore, or stock adjustment was added.
Those are outside Serhiy's role or belong to other tasks.

## Files

```
3d-print/serhiy-local-server/package.json
3d-print/serhiy-local-server/.env.example
3d-print/serhiy-local-server/server.mjs
3d-print/serhiy-local-server/lib/calculator.mjs
3d-print/serhiy-local-server/public/index.html
3d-print/serhiy-local-server/public/app.js
3d-print/serhiy-local-server/public/styles.css
3d-print/serhiy-local-server/tests/calculator.test.mjs
3d-print/serhiy-local-server/tests/server-local.test.mjs
3d-print/serhiy-local-server/README.md
diagnostics/3D-P-007_serhiy-local-server_report_20260802.md
```

## Local verification

Executed successfully:

```text
npm --prefix .\3d-print\serhiy-local-server test
4 passed, 0 failed

node --check .\3d-print\serhiy-local-server\server.mjs
node --check .\3d-print\serhiy-local-server\public\app.js
node --check .\3d-print\serhiy-local-server\lib\calculator.mjs
node --check .\3d-print\serhiy-local-server\tests\server-local.test.mjs
```

The integration test starts both a fake 3D-P API and the local server on
loopback-only ephemeral ports. It verifies the client uses only the distinct
Serhiy test credential, calls `3dp_payouts` and bounded
`Легенда!A32:A38`, round-trips the five raw Addendum #2 values, writes derived
`G:J` values, and uses both print-log write paths. No live endpoint, token, or
business data is used by that test.

## Credential and network boundary

The server needs exactly local `BOOSTER_3DP_URL` and
`BOOSTER_3DP_SERHIY_TOKEN` variables. The token never reaches browser code,
files, logs, or the UI. It listens only on `127.0.0.1`; the browser talks only
to localhost, while the local process is the sole caller of the 3D-P web app.
`BOOSTER_CRM_TOKEN` and the owner/dashboard 3D-P token are neither read nor
accepted.

## Remaining live gate

Not verified in this task:

- an owner-provisioned, distinct live Serhiy token;
- real endpoint response under that token;
- a deliberately selected test-SKU batch draft/save/reload;
- one real test print-log append plus post-production defect update, confirmed
  by the owner in `_Аудит_API`.

These steps write only through existing API controls and require the owner and
Serhiy together. They are not implied by the local test pass.

## Recovery

Stop the local process with `Ctrl+C`. The package has no deployment, dashboard,
CRM, or Sheet-schema side effects. Individual API writes are traceable and
recoverable by the owner through `_Аудит_API`.
