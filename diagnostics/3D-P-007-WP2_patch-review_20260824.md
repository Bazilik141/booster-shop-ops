# Claude review — 3D-P-007 WP2: Serhiy local-server re-spec

Date: 2026-08-24 | Executor: Codex | Reviewer: Claude (chat)
Handoff: `handoffs/handoff_3D-P-007-WP2_serhiy-local-server-respec_20260823.md`
Codex report: `diagnostics/3D-P-007-WP2_serhiy-local-server-respec_report_20260823.md`

**Verdict: Deploy OK; є неблокуючі зауваження.**

The seven PHP patch conventions do not apply — this is a local Node package that
the owner runs, not a patch and not an Apps Script publication. Nothing about it
reaches production until someone starts the process with a real token.

## Scope

Only `3d-print/serhiy-local-server/**` changed, plus the report. Verified by file
mtimes: `3d-print/apps-script-3dp-api/Code.gs` (04:18), `crm/apps-script/Code.gs`
(12:21), `dashboard/booster-dashboard.html` (14:24 — this session's roadmap
mirror edit) and `3d-print/shared/print-time.js` (2026-08-08) are all untouched
by this round. The do-not-touch list held.

## Acceptance criteria — checked one by one

| Criterion | Result |
|---|---|
| `npm test` passes | **6/6 pass**, re-run here on a copy. Suites: two in `calculator.test.mjs` on the projected settings shape and the defect adjustment, plus per-route and error-propagation integration tests against a fake localhost API. |
| No call outside Serhiy's projection; `Легенда` nowhere | **Zero occurrences** of `Легенда` or `LEGEND` anywhere in the package. The old open-questions constant, route and UI block are gone. |
| `getSettings()` requests exactly `Налаштування!B2:B5` | Confirmed — `server.mjs:97`, the only `3dp_get_range` call in the package. |
| Displayed cost equals `Номенклатура!K`; `G/H/I/J` unchanged | `base_uah` is preserved and `defect_adjusted_uah = base_uah × (1 + planned_defect)` is added alongside it, exactly as the handoff specified. The worked example reproduces: 36 units / 180 g / 18 h / 1000 g / 800 UAH at 0.17 kW, 4.32, 12, 0.08 → per-unit 5 g and 0.5 h, base 10.3672, adjusted 11.196576. The four writes still carry `per_unit.time_hours`, `per_unit.weight_g`, `spool_weight_g`, `spool_price_uah` into `G/H/I/J` with the same normalisation and the same skip-if-unchanged guard. |
| Every WP1b/WP1c grant has a route and a UI entry point | Settings `B2:B5` + journal, `Q`/`R`/`S`, fixture price, stock correction, both payout acknowledgements plus correction, draft creation, manufactured batch, print-log defect edit. Fifteen distinct `3dp_*` actions, none of them owner-only. |
| Stock correction sends `new_value` | Confirmed. `new_value` appears twice; the string `delta:` appears nowhere in the package. |
| Print logging through `3dp_manufacture_batch` with a stable `request_id` | Confirmed, and `3dp_append_row` is gone from both `server.mjs` and `public/app.js`. The id is minted once onto the form's dataset, reused on retry, and deleted only after the API confirms. It is validated locally against the API's own pattern before the call, and `printed_by` is pinned server-side to exactly `Сергій`. |
| Three zones; Інформація blocks match; CRM-sync absent | Three zone buttons and three zone sections. Інформація carries exactly the six approved blocks — Потребує уваги, Зведення та аналітика, Всі вироби, Продажі, Виплати, Журнал маркетингових плюшок. **No CRM synchronisation block.** |
| The draft form never presents a generated article as assigned | The form carries a standing warning that no article is assigned here; the result renders the `DRAFT-` technical key, then the prefix and category **separately as a suggestion for the owner**, and repeats that the owner assigns the article. A complete SKU is never composed client-side. |
| No token, `/exec` URL or secret in any tracked file | Clean. `.env.example` holds empty keys and the literal placeholder `.../macros/s/DEPLOYMENT_ID`. Tests use a local sentinel identity. |
| API error codes reach the UI unchanged | `errorText()` renders `CODE: message` straight from the API payload; the local boundary re-emits `code` and `error` verbatim. Covered by its own passing test against fake `RANGE_NOT_PROJECTED`, `READ_PROJECTION_FORBIDDEN`, `STALE_WRITE` and `FORBIDDEN` responses. |

## Additional checks

**Tables are built from the payload, not from a hardcoded list.** `objectTable`
and `matrixTable` render whatever keys and header row the projected response
actually returns, so a renamed header surfaces rather than silently vanishing —
which is what the header-name-based projection is designed for.

**Attention signals** are exactly the three the handoff named, built from
Serhiy's own data: zero stock, missing per-unit print time, and defects recorded
in the print log.

**Payout append-once is honoured in the UI.** An existing acknowledgement renders
as a recorded value with only a «Виправити» action; the append button is not
offered again.

**The fixture list is refreshed before validation** via
`3dp_information_bootstrap` rather than trusting a cached bundle.

## Findings

| # | Severity | Where | Issue |
|---|---|---|---|
| 1 | non-blocking | `public/app.js:8` | The 17 draft type labels are mirrored client-side with **no contract test**. Compared by hand here against `NOMENCLATURE_DRAFT_SUGGESTIONS_3DP`: 17 on each side, identical values in identical order — correct today. But this is precisely the drift the dashboard's rewritten `dashboard-contract.test.mjs` now guards, and Serhiy's client has no equivalent. If the API's list changes, a stale label yields a null `sku_suggestion` and Serhiy sees «Для цього типу API не повернув підказку». Degraded, not corrupting — but silent. Cheap fix: mirror the dashboard's check, reading the constant out of `Code.gs`. |
| 2 | non-blocking, worth knowing | `public/app.js` manufacture form | The idempotency key is the right design, but it has one visible edge. If a submit fails **after** the API wrote the row (lost response), the key stays on the form; changing the numbers and resubmitting returns `already_applied` and the corrected numbers are silently discarded. The trade-off is correct — a lost edit beats a duplicate print-log row — but the message reads like success. After any failed manufacture submit, check the print log rather than trusting the second attempt's message. |
| 3 | deliberate change, record it | `lib/calculator.mjs` | `settingsFromRange` now accepts amortisation and planned defect equal to zero, where the previous version rejected anything not `> 0`. This matches the API's own bounds (`SETTINGS_VALUE_BOUNDS_3DP` gives both `min: 0`), so it is a fix, not a loosening — but the handoff said not to touch the calculator's terms, and this touched their validation. |
| 4 | beyond the handoff, harmless | `public/app.js` `payoutButtons` | The «Кошти отримано» acknowledgement is hidden until the payout row's status is `Виплачено`, showing «Після виплати» instead. Not requested; sensible, since confirming receipt before payment is meaningless. The API still governs what is accepted. |

Nothing blocking. No destructive operation, no secret, no unbounded loop, all
three entry files pass `node --check`.

## What this does and does not prove

Green here means the source is correct against a **fake** API. It is not
installation proof and not live-boundary proof. Both belong to WP3, and the
evidence WP3 must capture is unchanged: that `Q`/`R`/`S` writes are journalled
with an author, and that payout period creation and closure, `Налаштування`
outside `B2:B5`, and order/customer identity all stay closed under Serhiy's
token. That is the rewritten `3D-P-015` gate.

The package has never been installed or run by Serhiy. Every accepted write in
owner QA lands on the live workbook — there is no staging — so QA needs a
designated test SKU and the token in the shell environment only.

## Rollback

Stopping the Node process and reverting the eight package files. This package
performs no schema setup and no deployment of its own. Writes already accepted by
the API are outside local rollback and remain in the workbook journals.
