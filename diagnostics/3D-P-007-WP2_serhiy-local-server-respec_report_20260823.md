# Codex Report — 3D-P-007 WP2: Serhiy local-server re-spec

Date: 2026-08-24

## Outcome

Implemented the WP2 handoff entirely inside `3d-print/serhiy-local-server/**`.
The client now consumes the V29 Serhiy projection contract, exposes every
WP1b/WP1c grant assigned in the handoff, and presents three UI zones:
`Калькулятор`, `Вироби`, and `Інформація`.

No Apps Script mirror, owner dashboard, CRM file, Notion record, live workbook,
deployment, commit, or push was changed.

## Files touched

```text
3d-print/serhiy-local-server/server.mjs
3d-print/serhiy-local-server/lib/calculator.mjs
3d-print/serhiy-local-server/public/index.html
3d-print/serhiy-local-server/public/app.js
3d-print/serhiy-local-server/public/styles.css
3d-print/serhiy-local-server/tests/server-local.test.mjs
3d-print/serhiy-local-server/tests/calculator.test.mjs
3d-print/serhiy-local-server/README.md
diagnostics/3D-P-007-WP2_serhiy-local-server-respec_report_20260823.md
```

Local-server diff: 450 insertions, 669 deletions across eight package files.

## Projection-safe reads

- Bootstrap now uses `3dp_bootstrap?include_archived=true` and
  `3dp_information_bootstrap`, plus the active `3dp_print_log` read that is not
  included in either bundle.
- Settings reads request exactly `Налаштування!B2:B5`.
- Fixture validation refreshes the projected fixture bundle through
  `3dp_information_bootstrap`; the obsolete standalone fan-out was not retained.
- The obsolete open-questions constant, route, UI block, and every reference to
  the non-projected sheet were removed.
- Analytics is rendered from the projected matrix's returned header row. All
  object tables build their columns from the keys actually returned by the API;
  there is no client-side fixed projection list.

## Write routes

The local server now exposes bounded routes for:

- settings B2:B5 writes with `expected_current` and the Serhiy settings journal;
- nomenclature Q/R/S writes with `expected_current`;
- actual-count stock correction through `new_value` and mandatory
  `expected_current`;
- payout `amount_agreed` / `money_received` acknowledgement and explicit
  correction;
- nomenclature draft creation with only B,C,D,E,F,G,H,I,J,L,M,N,Q,R,S accepted;
- manufactured batches through `3dp_manufacture_batch`.

The manufacture form owns one request ID until the API confirms success. A
network failure leaves that ID on the form, so retrying reaches the API with the
same idempotency key. `printed_by` is fixed server-side to exactly `Сергій`.
Owner-only payout creation/closure and article assignment have no local route or
UI control.

API error `code` and `error` are returned unchanged by the local boundary. The
browser shows both instead of substituting a generic message.

## Calculator contract

`settingsFromRange()` now reads the projected four-row, one-column B2:B5 shape:

1. printer power;
2. electricity price;
3. amortization;
4. planned defect fraction.

`calculateBatchCost()` preserves `base_uah`, all three cost terms, both per-unit
divisions, spool values, and the established G/H/I/J write set. It adds
`defect_adjusted_uah = base_uah * (1 + planned_defect_fraction)` for the displayed
cost matching `Номенклатура!K`.

Worked test input: 36 units, 180 g, 18 h, 1000 g spool, 800 UAH spool price,
0.17 kW, 4.32 UAH/kWh, 12 UAH/h, defect fraction 0.08.

```text
per-unit weight:        5 g
per-unit print time:    0.5 h
base_uah:               10.3672
defect_adjusted_uah:    11.196576
sheet-equivalent K:     11.196576
```

## UI

### Калькулятор

Batch calculator, saved raw draft, unchanged per-unit writes, editable B2:B5
settings, and the Serhiy settings journal.

### Вироби

Active-SKU controls for Q/R/S, fixture price, actual-count stock correction,
idempotent manufacture logging, active print log, and draft creation. The draft
result displays the generated `DRAFT-` technical key plus prefix/category
suggestion only and explicitly states that the owner assigns the article.

### Інформація

Collapsible attention, analytics, all-products, sales, payouts, and marketing
gift blocks. Attention signals are built only from projected zero stock,
recorded defects, and missing print time. The shop CRM synchronization block is
absent. Existing payout acknowledgements render as recorded; the same append
button is not offered again, while explicit correction remains available.

Responsive rules cover desktop, tablet, and narrow layouts; keyboard focus is
visible on controls and disclosure summaries.

## Verification

The first sandboxed `npm test` attempt stopped before executing tests with the
known Windows Node runner error `spawn EPERM`. The identical command was rerun
outside the sandbox against the fake localhost API only.

```text
npm test
  tests: 6
  pass:  6
  fail:  0

node --check server.mjs             passed
node --check public/app.js          passed
node --check lib/calculator.mjs     passed
git diff --check (package scope)    passed
```

The server integration suite covers every new route, the two bundled reads,
exact B2:B5 range, `new_value` without `delta`, both payout acknowledgement
types, explicit correction, draft suggestion, stable manufacture retry ID, and
the absence of owner-only calls. Negative fake-API responses prove unchanged
propagation of:

```text
RANGE_NOT_PROJECTED
READ_PROJECTION_FORBIDDEN
STALE_WRITE
FORBIDDEN
```

A bounded package scan found no obsolete range, raw print-log append, real or
placeholder Apps Script URL, or reference to the removed non-projected source.
No real token is present; tests use a local-only sentinel identity.

## Idempotency and rollback

- Manufacture retry: stable `request_id`; the API returns `already_applied`
  instead of appending another row.
- Payout acknowledgement: existing value removes the append action; corrections
  require the expected existing value and a reason.
- Settings, Q/R/S, and stock writes use optimistic concurrency through
  `expected_current`.
- Local rollback is stopping the Node process and reverting the eight package
  files. This package performs no schema setup or deployment by itself.
- Accepted live API writes are outside local rollback and remain auditable in
  the workbook journals. None were executed during this task.

## Remaining owner gate

This is source and fake-API proof, not installation or live-workbook proof.
WP3/manual QA must use environment variables held in the shell and a designated
test SKU because every accepted write reaches the production workbook.

- [ ] Start locally and confirm bootstrap loads under the Serhiy credential.
- [ ] On a designated test SKU, save a batch draft and Q/R/S.
- [ ] Submit an actual counted stock value and one manufactured batch.
- [ ] Create one nomenclature draft and confirm it remains `Чернетка` with a
      `DRAFT-` key and no assigned article.
- [ ] Record both payout acknowledgements and confirm neither append action is
      offered twice.
- [ ] Confirm owner-only payout actions and reads outside B2:B5 remain refused.
- [ ] Confirm no order/customer identity is visible in any information table.

## Risks

- There is no staging workbook. Manual QA writes are production writes.
- A green local run does not prove installation on Serhiy's PC or the live
  production-identity boundary.
- Settings saves are one-cell operations. If a later changed cell becomes stale,
  earlier accepted settings writes remain journalled; the UI stops on the API
  error and does not retry blindly.
- The 17 draft type labels mirror the V29 contract because V29 does not expose a
  separate suggestion-list read action. The API remains authoritative for the
  returned prefix/category suggestion and validates the draft action.
