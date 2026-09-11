# CRM-012 — Claude review

Date: 2026-09-11 | Reviewer: Claude (chat) | Author under review: Codex
Report under review: `diagnostics/CRM-012_zenmarket-integrity-and-recognition_report_20260910.md`
Handoff: `handoffs/handoff_CRM-012_zenmarket-integrity-and-recognition_20260910.md`
Sources inspected: `crm/apps-script/Code.gs`, `dashboard/booster-dashboard.html`,
`crm/apps-script/SOURCE_STATE.md` as staged 2026-09-11.

## Verdict

**Review OK; owner QA required.** One defect returned, one fragility recorded.

All six work packages are implemented, and WP4 is implemented more carefully
than the handoff asked. Nothing in this round needs to be redone.

## Verified against the source, not the report

- **No new duplicate declarations.** `Code.gs` still carries only the
  pre-existing `apiAddSale_` and `getDirectOrderExpense_`; the dashboard only
  the pre-existing `render`. Both files parse.
- **WP0.** `SOURCE_STATE.md` records V171 as the byte-verified live baseline and
  closes the V168-reported / V164-verified gap, with the CSV caveat noted.
- **WP1.** `crm012ZenMissingTopupUah_` counts from `ZenMarket_Рахунок`, selecting
  `historical_topup` and `manual_topup` rows with an empty UAH cell. All four
  hardcoded `1` literals are gone.
- **WP2.** `crm012ZenRecordHistoricalTopupUahForOwner` is fixed-target and
  strongly guarded: exactly one `ZEN-HIST-008`, stored JPY must equal 70,000,
  source must be `historical_topup`, an existing UAH value must match or it
  throws, and a duplicate top-up row is rejected. It cannot be aimed at another
  row and it derives neither amount. The Cash Flow notice is wired to the
  dynamic counter (`zenMissingTopups > 0`), so it clears itself.
- **WP3.** `force_zenmarket` and `forceZenmarket` are absent from the code. The
  two `force_zenmarket` strings in the dashboard are Claude's own `ROADMAP_TASKS`
  description text, not executable code. The supplier field is validated to
  `zenmarket_jp` / `supplier_ua` / `other`, and the `LOT-0181` repair is gated
  behind an exact confirmation token.
- **WP4.** Better than specified. `crm012SalesRecognitionColumns_` resolves
  `Дата оплати` and `Дата отримання` **by header name** with fallbacks rather
  than by hardcoded index. `setupCrm012RevenueRecognition()` appends the column
  at `getLastColumn() + 1` only when absent, never inserts, and gates on
  integrity. The stamp goes through `crm011StampRowsOnce_`, which writes only
  into a blank cell, from all four paths — menu `addSale` / `updateSaleStatus`
  and dashboard `crm011ApiAddSale_` / `crm011ApiUpdateSale_`.
  One recognition function feeds five consumers: the client model, channel
  stats, `apiMonthlySummary_`, `apiAggregateSalesRows_` and the finance report.
  The "one rule in one place" requirement is met.
  The former-preorder detection chain holds end to end: an open preorder is
  excluded by status, a reserved one by `FIFO (резерв передзамовлення)`, and a
  reconciled one carries `FIFO (передзамовлення, звірено)`, which the detector
  matches, while a regular order carries `FIFO (CRM-011)` and recognises by sale
  date.
- **WP5.** Read-only. No rename path is enabled and no canonical name is
  invented; live-card evidence is deliberately `null` pending human
  verification.

## Returned — `recognition_fallback_orders` is never rendered

`finance_report.data_quality` carries `recognition_fallback_orders` and
`identity_conflict_orders`. The dashboard's finance quality line enumerates a
**fixed** set of keys — `writeoff_missing_value_rows`,
`linked_order_writeoffs_excluded`, `reconciliation_rows_checked`,
`reconciliation_mismatches` — and neither new key is among them. Grep confirms
zero occurrences of either name in the dashboard.

This is the same defect class returned in the previous round as Z2: a
data-quality signal that exists in the API and nowhere on screen. It matters
more here than it did there. WP4 changes **which month money lands in**, and
`recognition_fallback_orders` is the only indicator of how many orders were
placed in a month by a fallback date rather than by a recorded event. Shipping
the recognition change without that counter visible means a silent estimate
looks exactly like a measured fact.

**Fix:** add both keys to the same finance quality line. Frontend only; no
publication required.

## Recorded, not blocking — the preorder marker is a substring match

`crm012WasPreorderRow_` decides "this was a preorder" by testing the
human-readable cost-method label against `/передзамовлення/i`. It is correct
today for every method in use.

The fragility: the marker is a word inside a display string. Rename that label,
translate it, or drop the word in a future refactor, and every historical
preorder silently reverts to sale-date recognition — no error, no warning, and
the months quietly move back. A stable boolean or an explicit method code would
not have that failure mode. Not worth changing in this round; worth knowing
before anyone edits those labels.

## Owner gate

The owner-run order in the Codex report is correct and properly gated; follow it
as written. Two emphases:

- The pre-WP2 spreadsheet copy exists (`10 вересня, 22:42`). Run the
  `LOT-0181` repair **only after** reviewing the mislabel-scan output, and do
  not extend it to other lots. The report is right that current-sheet data
  cannot establish the provenance of older rows — do not infer suppliers from it.
- After `crm012ZenRecordHistoricalTopupUahForOwner()`, confirm the Cash Flow
  notice disappears by itself. It is bound to the dynamic counter, so if it
  stays, the counter is not seeing the recorded value — that is a signal, not
  cosmetics.

Publication, both integrity outputs, the exact JPY compensation, the resulting
balance, the before/after month comparison and the full 3D divergence table all
remain owner-gated runtime evidence and are not verified here.
