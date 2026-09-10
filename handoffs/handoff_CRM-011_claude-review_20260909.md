# Claude Review Handoff — CRM-011: dashboard, finance, and CRM upgrade

Date: 2026-09-09 | Parent: CRM-011

Executor: Claude (review only) · model=Opus · thinking=high

CRM is a risky zone and this is a multi-file change affecting finance semantics,
order updates, fiscal-receipt enforcement, alerts, and dashboard behavior. Claude
should perform an independent diff review; Codex remains the sole patch author
for this round.

## Requested review outcome

Return one of these verdicts:

- `DEPLOY OK` — no blocking defect found; list residual risks and the bounded
  owner QA still required.
- `RETURN FOR CHANGES` — list each blocking defect with exact file and line,
  expected behavior, and the smallest safe correction.

This is review only. Do not edit files, deploy or publish Apps Script, change the
Google Sheet, update Notion or `ROADMAP_FLOW`, commit, or push.

## Scope under review

- `crm/apps-script/Code.gs` — main CRM API, finance/client contracts, fiscal
  receipt persistence and enforcement, arrival dates, sorting data, and
  performance changes.
- `crm/apps-script/SOURCE_STATE.md` — source/deployment evidence and open gates.
- `crm/apps-script/one-time/CRM-011_followup_data_import_20260908.gs` — guarded,
  idempotent one-time ZenMarket and arrival-date import.
- `crm/apps-script/tests/crm-011-finance-dashboard.test.mjs` — server contracts.
- `crm/apps-script/tests/crm-011-followup-data-and-alerts.test.mjs` — follow-up
  contracts.
- `crm/alerts-apps-script/Code.gs` — token-gated alert management and Telegram
  suppression.
- `crm/alerts-apps-script/SOURCE_STATE.md` — separate alerts deployment state.
- `dashboard/booster-dashboard.html` — Overview, Alerts, Finance, Orders,
  Clients, slow stock, and loading-response improvements.
- `dashboard/tests/crm-011-dashboard.test.mjs` — dashboard contracts.
- `diagnostics/CRM-011_dashboard-finance-crm-upgrade_report_20260908.md` — full
  implementation and verification evidence.

Do not treat unrelated dirty-worktree files or the pre-existing dashboard 3D
category mismatch as CRM-011 scope.

## Implemented behavior

1. Overview no longer loads the general attention block. It shows preorders
   using the existing orders payload. Managed issues moved to a separate lazy
   `Увага` page.
2. The alerts service uses a separate URL/token, stable full-signature alert
   IDs, and `_Керування_Алертами`. Dismissed issues are excluded from both daily
   Telegram alerts and the weekly summary; a materially changed issue receives
   a new signature and becomes active.
3. `Продажі!AH` stores `Фіскальний чек`. Both client and server block an order
   update to `Оплачено` + `Отримано` unless the flag is set. Multi-line orders
   use one range read and one range write.
4. `Повільний склад` is collapsed by default. Its primary row shows the oldest
   UA arrival date, while expanded details show the contributing lots.
5. Orders can sort both directions by amount, marketing, profit, and net profit
   percentage. Clients expose purchased game/IP labels derived from SKU prefixes
   `PKM`, `OP`, `MTG`, and `YGO`.
6. Finance count metrics no longer show a hryvnia symbol. Inventory assets are
   split into at-UA-warehouse and outside-UA-warehouse values. Cashflow labels,
   periods, calculation rules, and ZenMarket top-ups are explicit.
7. Performance work includes one memoized purchase model shared by arrivals and
   outside-stock assets, lazy page requests, removal of the dead Overview alert
   request, in-flight GET de-duplication, a 180 ms search debounce, analytical
   response caching, and cache-version invalidation after mutations.

## Finance rules to verify

- Revenue is `Продажі` column K and discount is not subtracted again.
- Profit is `Продажі` column V and order costs are not deducted twice.
- Operating expenses include only `Витрати` rows whose column L equals `Так`.
- `_getCrmSalesRows()` excludes orders with unreconciled preorder cost, and the
  UI discloses that exclusion.
- Paid cash receipts use `Дата оплати`; historical fallback dates are marked as
  approximations.
- ZenMarket UAH values are approximate at 3.2 JPY/UAH because the supplied CSV
  contains JPY only. Until the import exists, cash-out/net-cashflow must be
  unavailable, not a false zero.
- VIP is lifetime-profit top decile, at least two orders, with a 3,000 UAH
  minimum profit floor.

## Live setup evidence

- At 08:58 Kyiv, `setupCrm011FinanceColumns()` added only
  `Продажі!AH2 = Фіскальний чек`; zero payment dates were backfilled. Integrity
  was clean before (`20987 ms`) and after (`14380 ms`), with 66 RRP comparisons
  and 6 rows skipped for missing CRM RRP.
- At 11:43 Kyiv, the revised importer completed: 108/108 ZenMarket payments,
  17 matched tracks, and `arrival_cells_written=0` because all matched rows
  already contained the supplied dates. Integrity remained clean before
  (`13963 ms`) and after (`11929 ms`).
- The owner confirmed these four tracks do not exist in CRM and accepts them as
  intentionally unmapped: `LX316494995JP`, `LX316339015JP`, `LX315072863JP`,
  `LX314846403JP`.
- The 12:08 bounded audit scanned `Продажі!A253:AH752`: 500 rows, 60 distinct
  orders, 51 paid, 9 unpaid, and `missing_payment_date=0`.
- The same audit found one future date: `OLX-PHYS-0023`, row 347,
  `2026-09-10`. On 2026-09-09 the owner explicitly accepted the one-day-forward
  date and waived correction. It is not a blocker and must not be edited.

## Local verification evidence

- All 29 `crm/apps-script/tests/*.test.mjs` files pass sequentially.
- `dashboard/tests/crm-011-dashboard.test.mjs`: 6/6 pass.
- `dashboard/tests/3dp-sync-journal-static.test.mjs`: pass.
- `dashboard/tests/settings-workflows.test.mjs`: pass.
- `dashboard/tests/dashboard-contract.test.mjs`: one known pre-existing failure;
  the dashboard category list lacks `Кейс / контейнер для зберігання`. It is
  outside CRM-011 and must not be fixed in this review.
- Main CRM source, alerts source, one-time importer, and dashboard inline
  JavaScript all parse successfully via `new Function`.
- Scoped tracked `git diff --check`: pass.
- Local browser QA: Alerts setup state renders; Finance has no page-level
  horizontal overflow at 1440, 900, or 390 px; slow stock is collapsed by
  default.

## Source and deployment limits

- Main CRM Web App V168 is owner-reported. The last complete byte-verified
  mirror was V164, so exact live-source identity remains unproven.
- The guarded Sheet setup and data import above are live. A new main CRM Web App
  version containing the complete local candidate is not confirmed published.
- The separate alerts endpoint initially returned `doGet not found`; replacement
  source, token setup, and publication are not confirmed.
- The dashboard is local only. No commit, push, or deployment was performed.
- No live endpoint smoke, token-authenticated Alerts smoke, or final owner UI QA
  has been completed for this candidate.

## Mandatory review checks

- Compare the exact scoped diff with the original CRM-011 handoff and current
  `AGENTS.md`; flag any scope expansion or stale-source assumption.
- Trace all order-update paths and prove the fiscal blocker is server-enforced,
  persists for multi-line orders, and cannot be bypassed by dashboard state.
- Check every widened/fixed-width range for appended-column correctness and
  confirm no formula-bearing column can receive a literal.
- Trace alert dismissal through API persistence, daily Telegram output, weekly
  summary, token isolation, stable IDs, and changed-alert reactivation.
- Validate finance calculations for aggregation level, date basis, missing-data
  behavior, rounding, and double-count prevention.
- Validate performance changes: no duplicate broad Sheet reads, correct cache
  invalidation after mutations, bounded work, and no duplicate Overview request.
- Review long content, hover/focus/active behavior, and layouts at desktop,
  tablet, and mobile widths.
- Explicitly scan the diff for `!important`, `setTimeout`,
  `position:absolute/fixed`, and unexplained magic pixels. Current evidence:
  no new `!important` or absolute/fixed positioning; the new `setTimeout` is
  only the documented 180 ms debounce; 260 px search minimum and the existing
  800 px responsive threshold are documented in the diagnostic.

## Remaining owner gate after `DEPLOY OK`

1. Confirm the temporary importer is removed from the bound editor.
2. Publish a new main CRM Web App version and record its exact version.
3. Replace and publish the separate alerts source, set owner-held
   `BOOSTER_ALERTS_TOKEN`, and enter it in Dashboard → Увага → Налаштувати API.
4. Press Ctrl+F5 and QA fiscal blocking/saving, alert dismissal plus forced
   Telegram suppression, Finance reconciliation, order sorting, Clients IP,
   preorders, and collapsed/expanded slow stock.
5. Run post-publication integrity and bounded endpoint smoke; capture cold/warm
   Finance timing and update both `SOURCE_STATE.md` files.

## Rollback and stop conditions

- No verified Google Sheet copy/rollback artifact was reported in this thread.
  Do not claim one exists and do not delete or rewrite imported live data during
  review.
- Source rollback is publication of the prior owner-held Apps Script version;
  the precise prior live source must be confirmed before relying on it.
- Stop publication on a new integrity problem, a conflicting arrival date, a
  paid order missing its payment date, a fiscal-blocker bypass, an alert-token
  leak, or a finance reconciliation defect.
