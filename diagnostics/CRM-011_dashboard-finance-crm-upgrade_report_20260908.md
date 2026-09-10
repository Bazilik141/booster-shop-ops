# CRM-011 — dashboard finance/CRM upgrade report

Date: 2026-09-08

## Outcome

Local implementation is ready for independent review, owner-gated Apps Script
publication, and live QA. No Web App version was published and nothing was
committed or pushed. The owner did run the guarded live setup/import: the fiscal
header was appended and 108 ZenMarket payment rows were imported, with clean
integrity checks before and after.

The 2026-09-08 follow-up is included: the supplied ZenMarket history, managed
alerts, Overview preorders, fiscal-receipt blocking, purchase arrival dates,
sortable financial order columns, client IP/game labels, split assets, and a
plain-language cashflow block.

## Source and preflight

- Handoff: `handoffs/handoff_CRM-011_dashboard-finance-crm-upgrade_20260908.md`.
- Apps Script baseline: local candidate after owner-reported V168 publication;
  the last byte-verified complete export remains V164. Existing unrelated local
  Telegram shipment-queue changes were preserved.
- Owner-provided pre-change read-only integrity result:

```json
{"ok":true,"action":"integrity_check","problems":[],"coverage":{"rrp_mismatch_3dp":{"compared":66,"skipped_missing_crm_rrp":6,"deferred":null}},"clean":true,"elapsed_ms":15228}
```

Owner-provided post-V168 read-only result was also clean with the same coverage
and `elapsed_ms=16254`.

## Implemented scope

- WP1: Stock now shows a top-5 slow-stock block ranked by physical stock times
  current cost, with 60-day coverage and documented exclusions.
- WP2: append-only setup for `Закупки → Дата створення`, `Продажі → Дата
  оплати`, and `Курси → Дата курсу`; JPY fallback is 3.2; finance, client,
  client-order, and enriched order contracts were added.
- WP3: lazy Finance page with period/comparison controls, KPI, P&L, inventory
  assets, customer mix, cashflow, and data-quality sections.
- WP4: AND-joined order search/filters, normalized phone search, client identity,
  NEW/repeat marker, result count, and one `ORDER_SUMMARY_COLUMN_COUNT` constant.
- WP5: All/Important client modes, global KPI, seven segments, real first/last
  dates and average interval, and lazy order history.
- WP6: generated roadmap series plus stage filters joined with AND.
- Performance cluster: in-flight GET de-duplication, 180 ms search debounce,
  lazy Finance/client-history requests, cache TTL for analytical reports, and
  client-visible finance request timing.
- Follow-up: Overview reuses the existing orders payload for preorders and no
  longer requests stock alerts; alerts are a separate lazy page. Purchase
  arrivals and outside-stock assets share one memoized purchase pass. Fiscal
  receipt values for a multi-line order use one range read and one range write.
- The one-time importer embeds all 108 unique ZenMarket payments and all 21
  owner-confirmed tracking dates. Missing tracks are reported and skipped;
  conflicting existing dates remain fatal. The payment import is idempotent by
  Payment ID.
- The separate alerts source adds token-gated management. `dismissed` issues
  are omitted from daily Telegram and weekly summaries; a changed issue gets a
  new stable signature and becomes active again.

## Finance semantics and explicit gaps

- Revenue is `Продажі` K and is not reduced by discount again.
- Order profit is `Продажі` V and is not recomputed or reduced by order costs a
  second time.
- Operating expenses include only `Витрати` rows whose column L is `Так`.
- Finance uses `_getCrmSalesRows()`, therefore orders with unreconciled preorder
  cost are excluded and the UI says so.
- Paid cash receipts use `Дата оплати`; missing historical values use the owner
  rule (sale date, or sale date + 3 days for cash on delivery) and are counted as
  approximations.
- `ZenMarket_Поповнення` becomes the canonical top-up source after the guarded
  one-time import. Historical UAH values are explicitly approximate at 3.2 JPY
  per UAH because the source file contains JPY only. Until import, cash out and
  net cashflow remain `null`/`Немає даних`, never a false zero.
- VIP uses lifetime profit top decile, at least two orders, and a 3,000 UAH
  minimum profit floor.

## Fixed-width read audit

- `_getCrmSalesRowEntries()` was widened from 32 to exactly 33 columns because
  `Дата оплати` is the new last column.
- Test-order cleanup snapshots/clears 34 columns so it cannot leave an orphaned
  payment date or fiscal-receipt flag.
- Existing reads of 29/32 columns remain intentionally bounded where they use
  only the original schema and do not read, copy, or clear the new date field.
- Row-capacity formula lists remain at the original formula-bearing width; the
  appended date fields are values, not formula columns.

## Optimization review

- No broad framework or dashboard rewrite was made.
- No new `!important`, `position:absolute`, or `position:fixed` was added.
- `setTimeout` is used only for the 180 ms search debounce, avoiding a full table
  render on every keystroke.
- The 260 px search minimum keeps the multi-field query usable on desktop while
  `flex:1` allows it to grow; the existing 800 px responsive rule remains intact.
- Analytical GET responses use three-minute Apps Script cache entries; mutations
  already invalidate the shared cache version.

## Verification

- `Code.gs` syntax via `new Function`: pass.
- Dashboard inline JavaScript syntax: pass.
- New CRM-011 server contracts: 6/6 pass.
- Follow-up importer/alerts contracts: 2/2 pass.
- New CRM-011 dashboard contracts: 6/6 pass.
- All existing `crm/apps-script/tests/*.test.mjs`: pass.
- Existing dashboard settings and sync-journal tests: pass.
- Local browser QA at `http://127.0.0.1:8765/dashboard/booster-dashboard.html`:
  Finance layout renders, order controls render, and Roadmap `CRM + todo` returns
  exactly `CRM-011`, proving the two filters are AND-joined.
- Follow-up browser QA: the Alerts page renders its setup state, Finance has no
  horizontal page overflow at 1440, 900, or 390 px, and `Повільний склад` is a
  closed `details` section by default.
- Live API data was not loaded because no token was entered into the QA browser.
- Known pre-existing failure remains in `dashboard-contract.test.mjs`: the
  dashboard 3D category list lacks `Кейс / контейнер для зберігання`. This was
  present before CRM-011 and was not changed outside scope.
- Known pre-existing `tests/3d-p-013-dashboard-ui-regression.test.mjs` failures
  remain outside CRM-011 (three legacy 3D-P contract expectations).

## Owner-gated publication and QA

### Live setup progress — 2026-09-09

- `setupCrm011FinanceColumns()` succeeded at 08:58 Kyiv. Only the append-only
  `Фіскальний чек` header was added at column 34; payment-date backfill was zero.
- Integrity stayed clean before and after with 66 RRP comparisons and 6 rows
  skipped for missing CRM RRP.
- The first importer execution stopped before any import writes. Four exact
  normalized tracks were absent from `Закупки`: `LX316494995JP`,
  `LX316339015JP`, `LX315072863JP`, `LX314846403JP`.
- The revised importer treats those absent tracks as a reported warning and
  continues with payments and exact matched rows. Existing conflicting dates
  remain a fatal stop.
- Owner rerun at 11:43 Kyiv succeeded: 108/108 top-ups imported, 17 tracks
  matched, and zero arrival cells required changes because their stored dates
  already matched. Integrity remained clean (`13963 ms` before, `11929 ms`
  after). The owner confirmed the four unmatched tracks do not exist in CRM.
- The first date audit returned zero orders from 250 rows. Because an empty
  sample cannot prove date coverage, the local follow-up audit now aggregates
  multi-row orders across up to 500 rows and returns status counts plus the
  number of paid orders found.
- The 11:57 rerun found 60 distinct orders in 500 rows: 51 paid and 9 unpaid.
  Apps Script truncated the log before `missing_payment_date`, so the gate was
  still open at that point. One visible order (`OLX-PHYS-0023`, row 347) was dated
  `2026-09-10`, one day after the run date. The wrapper now logs only a compact
  summary and explicitly lists future payment dates.
- The compact 12:08 rerun closed missing-date coverage for the bounded sample:
  51 paid orders found and `missing_payment_date=0`. The same single future date
  remains on `OLX-PHYS-0023` row 347. The owner explicitly accepted it as a
  known one-day-forward value and waived correction; it is not a blocker and
  this task must not change that order.

1. Obtain independent Claude review using
   `handoffs/handoff_CRM-011_claude-review_20260909.md`. Do not publish on a
   `RETURN FOR CHANGES` verdict.
2. Confirm that a recoverable Google Sheet copy exists before any further live
   write. No copy was verified in the available execution evidence.
3. Confirm the complete local `crm/apps-script/Code.gs` is present in the bound
   editor and delete the temporary importer from the live editor. Do not rerun
   the already completed import.
4. Run the read-only `integrity_check` and bounded `payment_date_audit` again.
   Stop on a new problem or a paid order without a payment date. The explicitly
   accepted `OLX-PHYS-0023` date is not a blocker.
5. Publish a new main CRM Web App version and record the exact version in
   `crm/apps-script/SOURCE_STATE.md`.
6. Replace the source of the separate alerts Apps Script with
   `crm/alerts-apps-script/Code.gs`, set a new `BOOSTER_ALERTS_TOKEN` in Script
   Properties, and publish a new version of its existing Web App deployment.
   Do not send the token in chat. In Dashboard → Увага → Налаштувати API, enter
   that token once.
7. In the dashboard, press Ctrl+F5 and test Stock, Finance, Orders, Clients,
   Alerts, and Roadmap. Confirm the fiscal blocker prevents `Оплачено` +
   `Отримано` without the checkbox, then saves with it. Dismiss one test alert
   and confirm a forced daily alert does not include it.
8. Compare Finance totals with bounded source rows for one short period and send
   the post-setup JSON, post-integrity JSON, Web App version, segment distribution,
   and cold/warm Finance timing for final closure.

## Rollback

No verified Google Sheet copy/rollback artifact was reported in this thread.
Before further live writes, create or confirm a recoverable copy. If a
post-integrity check fails, stop before publication and use that confirmed copy
only after identifying the affected range. For source rollback, publish the
prior owner-held Apps Script version after confirming its exact version; do not
assume V166 because V168 is owner-reported. The local dashboard rollback is the
CRM-011 diff in `dashboard/booster-dashboard.html`.
