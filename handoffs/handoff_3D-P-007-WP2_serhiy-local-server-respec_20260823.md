# Codex Handoff — 3D-P-007 WP2: Serhiy local-server re-spec

Date: 2026-08-23 | Parent: `3D-P-007` (Serhiy local server)
Executor: Codex · model=Sol · effort=xhigh — multi-file re-spec of a client
against a contract it has never spoken, with two verified live-breaking calls
and a cost-display divergence; not a mechanical edit. Owner assigned Codex on
2026-08-23.

## Verified state — 2026-08-23, ~20:20 Kyiv

| Surface | State | How it was established |
|---|---|---|
| 3D-P Apps Script | **V29**, published 2026-08-23 16:00 Kyiv. Mirror `3d-print/apps-script-3dp-api/Code.gs` is byte-identical to the owner's labelled export (LF-normalised MD5 `d2f8256c5e21acf14ec442cf4533fff4`, 3718 lines). | Direct diff of export against mirror. Recorded in that folder's `SOURCE_STATE.md`. |
| `3d-print/serhiy-local-server/` | Unchanged since 2026-08-08. Speaks the pre-projection contract. | File mtimes. |
| Main CRM Apps Script | **V144**, owner-reported publication 2026-08-23 20:11 Kyiv, after the `CRM-012` cleanup removed the `OC-FOP-0326` and `OC-FOP-0320` recovery helpers. **No V144 export exists**, so the mirror is not byte-verified against live. | `diagnostics/CRM-012_oc-fop-0326-abyss-sku_report_20260823.md`, `crm/apps-script/SOURCE_STATE.md`. |
| `dashboard/booster-dashboard.html` | Unchanged since 2026-08-23 16:51 Kyiv: 574 000 bytes, 4372 lines, 104 rows matching `^ *id: '…', title:`. | Direct read. |

The CRM row is context only — this work package does not touch CRM. It is here
because that project was being edited during the same window and is **not**
byte-verified; do not treat its mirror as an authority for anything.

## Context

The API half of `3D-P-007` is finished and live: 3D-P Apps Script **V29**. The
repository mirror is byte-identical to the owner's export, so the repository copy
is an accurate contract source for this work.

`3d-print/serhiy-local-server/` was specified before WP1 rev 2 / WP1b / WP1c and
still speaks the pre-projection contract. Two of its calls now fail outright
under the Serhiy token, and the whole write grant Serhiy was given in WP1b/WP1c
has no interface.

**The governing data boundary** is the 2026-08-16 revision in
`plans/3D-P-007_serhiy-data-scope-decision-list_20260816.md` (section
«⚠ РЕВІЗІЯ 2026-08-16»): everything inside the 3D line is open to Serhiy;
everything outside it is closed. `SERHIY_FULL_ECONOMICS_VISIBLE_3DP = true`.
Older documents describe an abandoned margin-hiding model — do not re-derive the
boundary from them.

## Verified defects in the current client

These are read out of V29 source, not inferred:

1. **`server.mjs` `bootstrap()` reads `Легенда!A32:A38`.** `Легенда` has no key in
   `SERHIY_READ_PROJECTION_3DP`, so `3dp_get_range` raises
   `READ_PROJECTION_FORBIDDEN` for the Serhiy token. The «Відкриті питання»
   panel cannot work and bootstrap fails as a whole.
2. **`server.mjs` `getSettings()` reads `Налаштування!A1:C4`.**
   `assertSerhiyRangeReadAllowed3dp_` permits Serhiy only `Налаштування!B2:B5`;
   anything else returns `RANGE_NOT_PROJECTED`.
3. **`lib/calculator.mjs` `settingsFromRange()` expects the A1:C4 grid** and
   reads column index 1. Under the projected range the payload is 4 rows × 1
   column, so the adapter must change with (2).
4. **Displayed cost diverges from the sheet.** `Номенклатура!K` is
   `(H/I*J + G*B2*B3 + G*B4) * (1 + Налаштування!B5)` — see
   `nomenclatureFinalCostFormula3dp_`, called with `includeFixture=false` and the
   defect factor active. `calculateBatchCost` returns
   `base_uah = material + electricity + amortization` with **no** `(1+B5)`
   factor. The 2026-08-07 audit predates `B5`, which arrived with WP1 rev 2 on
   2026-08-16, so "the audit confirmed the formula" no longer covers the defect
   multiplier.
   ⚠ This affects the **displayed** number only. The per-unit divisions and the
   four values written to `G/H/I/J` are correct and must not change.
5. **No interface exists for the WP1b/WP1c grant**: `Q`/`R`/`S`, stock
   corrections, payout acknowledgement, draft creation, settings editing.
6. `appendPrintLog()` uses raw `3dp_append_row` on `Друк-лог`. That path performs
   no `Номенклатура` status check and has no idempotency key, so a print can be
   logged against a `Чернетка` or an archived SKU and a retry double-appends.
   `3dp_manufacture_batch` enforces the active-SKU check and is idempotent by
   `request_id`.

## Scope (what to change)

### `3d-print/serhiy-local-server/server.mjs`

- Replace the seven-call `bootstrap()` fan-out with `3dp_bootstrap`
  (returns `overview`, `skus`, `settings` = `Налаштування!B2:B5`, `analytics`
  projected) plus `3dp_information_bootstrap` (returns `sales`, `plyushky`,
  `payouts`, `fixtures`, all projected). Keep `3dp_print_log` and
  `3dp_fixtures` only where the bootstrap payloads do not already cover them.
- Delete `LEGEND_OPEN_QUESTIONS` and its route. Legend is not projected; there is
  no replacement source for that block.
- `getSettings()` reads `Налаштування!B2:B5` only.
- Replace `appendPrintLog()`'s `3dp_append_row` with `3dp_manufacture_batch`
  (`sku`, `quantity`, `defects`, `total_print_time_h`, `total_weight_g`,
  `printed_by`, `request_id`, `note`). `printed_by` must be exactly `Сергій`.
  Generate a stable `request_id` per submission (`^[A-Za-z0-9_-]{8,80}$`) so a
  retry is idempotent rather than a second row.
- Add routes for the WP1b/WP1c grant, each forwarding the API's own error code
  and message to the UI unchanged:
  - settings edit — `3dp_write` on `Налаштування` `B2:B5` with
    `expected_current`; bounds are enforced server-side
    (`SETTINGS_VALUE_BOUNDS_3DP`), do not duplicate them as client rules that can
    drift;
  - settings journal — `3dp_settings_journal` (read);
  - price/model fields — `3dp_write` on `Номенклатура` `Q` (РРЦ фактична),
    `R` (Ціна під викуп), `S` (посилання на модель) with `expected_current`;
  - stock correction — `3dp_adjust_stock` sending **`new_value`**, the actual
    counted quantity, never `delta`. This is 3D-P-025 semantics and is
    regression-covered; sending a delta silently means something different to
    the user. `expected_current` is mandatory;
  - payout acknowledgement — `3dp_payout_acknowledge`
    (`amount_agreed` / `money_received`) and
    `3dp_payout_acknowledgement_correct`. Append-once: the UI must show an
    existing acknowledgement as recorded rather than offering the button again;
  - draft creation — `3dp_nomenclature_draft_create`. Required fields are `B`
    (Назва виробу) and `D` (Тип); allowed fields are
    `B,C,D,E,F,G,H,I,J,L,M,N,Q,R,S`. The response carries `sku` (the generated
    `DRAFT-…` key) and `sku_suggestion`.

### `3d-print/serhiy-local-server/lib/calculator.mjs`

- Re-shape `settingsFromRange()` for the `B2:B5` payload and expose the planned
  defect fraction (row 4 of that range) alongside the three existing constants.
- Add a defect-adjusted total to the returned `costs` so the displayed figure
  matches `Номенклатура!K`. Keep `base_uah` as-is and add the adjusted value as a
  separate field; do not redefine an existing one.
- **Do not touch** the per-unit divisions, the material/electricity/amortization
  terms, or the four values written to `G/H/I/J`.

### `3d-print/serhiy-local-server/public/index.html` + `public/app.js`

Restructure the current flat eight-section page into **three zones**, mirroring
the owner dashboard's zone model in `dashboard/booster-dashboard.html`
(`threeDpZone-calculator` / `-products` / `-information`, lines ~641–660):

1. **Калькулятор** — the batch calculator plus a `⚙ Налаштування` toggle holding
   the four `B2:B5` constants (editable) and the change journal. Mirror the
   owner's `toggleThreeDpSettings()` pattern; do not copy owner-only controls.
2. **Вироби** — SKU list with availability, the fixture selector, `Q`/`R`/`S`
   editing, the stock-correction form, the print/manufacture form and the active
   print log, and the **draft-creation form**. The draft form's type dropdown
   feeds `sku_suggestion`; display the returned prefix and category **as a
   suggestion only**, and state on the form that the article is assigned by the
   owner. Never render a complete SKU as if it were assigned.
3. **Інформація** — collapsible blocks matching the owner's set minus what does
   not cross:
   - `Синхронізація з CRM` — **omit entirely**, not Serhiy's zone;
   - `Потребує уваги` — rebuild from Serhiy's own signals: zero stock, recorded
     defects, SKUs missing print time;
   - `Зведення та аналітика`, `Всі вироби`, `Продажі`, `Виплати` (with the two
     acknowledgement actions), `Журнал маркетингових плюшок` — render from the
     projected payloads.

Render whatever headers the projected payload actually contains. Do not hardcode
a column list: the projection is header-name based by design, so a renamed
header must surface as a visible failure, not a silently missing column.

### `3d-print/serhiy-local-server/tests/`

- Extend `server-local.test.mjs` with a mocked-API case per new route, including
  the negative paths: `RANGE_NOT_PROJECTED`, `READ_PROJECTION_FORBIDDEN`,
  `STALE_WRITE`, `FORBIDDEN` on an owner-only action.
- Add a `calculator.test.mjs` case asserting the defect-adjusted total equals the
  `K` formula for a worked example, and that per-unit outputs are unchanged from
  the current expected values.
- `npm test` must pass locally before delivery.

### `3d-print/serhiy-local-server/README.md`

Update the run instructions and the described feature set. Environment variables
only — no token value in any file, example, comment, test fixture or commit.

## What NOT to touch

- `3d-print/apps-script-3dp-api/**` — the API is deployed and QA'd at V29. If
  this work appears to need an API change, stop and report; do not patch it.
- `calculateBatchCost` per-unit math and the `G/H/I/J` write set.
- `3d-print/shared/print-time.js` — shared with the owner dashboard.
- `dashboard/booster-dashboard.html` — WP2b touches it; two writers on that file
  in one round is the failure mode that already occurred twice this month.
- `crm/apps-script/**`.
- `.env`, `.env.review`, `scripts/.env`, `client_secret.json`.

## Acceptance criteria

- [ ] `npm test` passes in `3d-print/serhiy-local-server/`.
- [ ] No call in the package targets a sheet or range outside Serhiy's
      projection; `Легенда` appears nowhere.
- [ ] `getSettings()` requests exactly `Налаштування!B2:B5`.
- [ ] The calculator's displayed cost equals `Номенклатура!K` for a worked
      example, and the values written to `G/H/I/J` are unchanged.
- [ ] Every WP1b/WP1c grant listed above has a working route and a UI entry
      point; stock correction sends `new_value`.
- [ ] Print logging goes through `3dp_manufacture_batch` with a stable
      `request_id`.
- [ ] Three zones exist and the Інформація blocks match the list above, with
      CRM-sync absent.
- [ ] The draft form never presents a generated article as assigned.
- [ ] No token, `/exec` URL or secret appears in any tracked file.
- [ ] API error codes reach the UI unchanged rather than being replaced by a
      generic local message.

## QA checklist (owner runs)

This package is not deployed — it is run locally with Node. There is no staging
for the 3D-P Sheet either: every write below lands on the live workbook.

- [ ] `node --run test` (or `npm test`) in `3d-print/serhiy-local-server/`.
- [ ] Start the server with `BOOSTER_3DP_URL` and `BOOSTER_3DP_SERHIY_TOKEN` set
      in the shell only, open `http://127.0.0.1:3107`, confirm bootstrap loads
      with no error banner.
- [ ] On a **designated test SKU**, not a live one: save a batch draft, write
      `Q`, `R`, `S`, submit a stock correction with the actual count, log one
      manufactured batch, create one draft product.
- [ ] Confirm in the Sheet: `Q`/`R`/`S` changes appear in the change journal with
      role `serhiy`; the stock ledger row records role `serhiy`; the draft row
      carries a `DRAFT-` key and status `Чернетка` with no article.
- [ ] Confirm the closed boundary still holds under the Serhiy token: creating or
      closing a payout period is refused, `Налаштування` outside `B2:B5` is
      refused, and no order or customer identity is visible anywhere in the UI.
- [ ] Confirm the two acknowledgement buttons record a Kyiv timestamp and role,
      and cannot be pressed twice.

The last two items are the live evidence the rewritten `3D-P-015` gate needs;
capture them for WP3.

## Risks

- **Live workbook, no staging.** Use a designated test SKU for every write in QA.
- **Serhiy's token is production-identity.** It stays in the environment. It must
  never enter a file, a test fixture, a log line, a screenshot or a report.
- **The package has never been installed or run by Serhiy.** `npm test` passed
  4/4 on 2026-08-02 and nothing since. Treat a green local run as source
  evidence, not installation proof — installation is WP3.
- **`Чернетка` polarity.** Any new guard must ask "is this row active?", never
  "is this row archived?". The archived-status inversion was the bulk of WP1c's
  real work and any missed site lets an article-less draft reach sale or accrual.
- **Article assignment is never automatic.** Prefix and category are
  deterministic and may be suggested; the mnemonic
  (`JIGGLYPUFF → JIGGL`, `POKEBALL → PKBL`) is the owner's readability judgement.
  Canon: `plans/3D-P_sku-naming-convention_20260807.md`.
- Deliver the report as `diagnostics/3D-P-007-WP2_serhiy-local-server-respec_report_20260823.md`.
  Do not commit, push, or write Notion.
