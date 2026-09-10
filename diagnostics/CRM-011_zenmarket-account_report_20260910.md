# Codex Report — CRM-011: ZenMarket reference account

Date: 2026-09-10

## Outcome

Implemented the ZenMarket reference-balance layer and the owner-requested
compact Finance UI. The balance KPI now also shows a smaller approximate UAH
equivalent calculated with the current CRM JPY rate. The implementation keeps
ZenMarket balance tracking separate from FIFO, RRP, inventory valuation, P&L,
and canonical UAH purchase costs.

## Scope

- Add a sixth Finance KPI for the current ZenMarket JPY balance.
- Show `approximately UAH ... at the CRM rate` below that JPY value.
- Place P&L and cashflow side by side on desktop.
- Place Assets and ZenMarket account controls side by side on desktop.
- Expose only two owner actions: balance correction and account top-up.
- Maintain one current ZenMarket expense per CRM LOT-ID. Purchase edits apply
  only the expense delta, preventing duplicate balance deductions.
- Preserve the existing `ZenMarket_Поповнення` sheet as the UAH cashflow source.
- Seed the reference balance from the two supplied HTML histories.

No live spreadsheet mutation or Apps Script publication was performed by
Codex. Repository delivery is separate from the owner-gated live publication.

## Data reconciliation

The two supplied HTML histories contain 12 distinct visible rows: 11 money
balance movements and one ZenPoints movement. The seed intentionally excludes
the ZenPoints row.

```text
Opening balance before the earliest visible movement: JPY -48,260
Money-balance movement total:                         JPY +44,575
Latest supplied balance:                              JPY  -3,685
Reconciliation:                                       PASS
```

The historical JPY 70,000 top-up has no UAH amount in the supplied HTML. It is
included in the JPY reference balance but is not fabricated in the UAH
cashflow sheet. The owner must provide the actual charged UAH amount if that
historical top-up should also appear in cashflow.

## Files touched

```text
crm/apps-script/Code.gs
crm/apps-script/SOURCE_STATE.md
crm/apps-script/tests/crm-011-r2-pass-b.test.mjs
crm/apps-script/tests/crm-011-zenmarket-account.test.mjs
dashboard/booster-dashboard.html
diagnostics/CRM-011_zenmarket-account_report_20260910.md
```

## Backend design

`setupCrm011ZenMarketAccount()` creates or validates:

- `ZenMarket_Рахунок`: internal balance-change journal;
- `ZenMarket_Лоти`: one current JPY expense per LOT-ID;
- `ZenMarket_Поповнення`: existing sheet, extended only by appended note,
  request-ID, and creation-timestamp headers.

New top-ups store actual JPY and UAH values. Balance corrections calculate the
difference from the current recorded balance and use the canonical comment
`коригування балансу зен - курсова різниця`. Request IDs make both owner actions
safe against repeated dashboard submission.

For a ZenMarket purchase, the reference JPY expense is:

```text
goods cost + Japan delivery/fees + delivery to Ukraine
```

The purchase's existing UAH values are converted with the JPY rate already used
by the CRM at the time of the save/update. A later edit updates the same LOT-ID
index and applies only the delta. Periodic owner corrections absorb small
exchange-rate or external-account drift without rewriting purchase costs.

## UI root cause and override review

The Finance blocks were full width because P&L, cashflow, and Assets were three
independent `.section` elements. The KPI strip was explicitly fixed at five
columns by `.crm011-kpis`. No prior patch in `patches/` or diagnostic override
targeted the new Finance layout selectors. The fix groups the requested pairs in
a Finance-specific grid and adds a Finance-specific six-column KPI class; it
does not change the shared section component. The new rules add no `!important`,
`setTimeout`, fixed/absolute positioning, or unexplained overlay.

## Performance impact

- No additional full `Закупки` read is added to `finance_report`.
- Finance reads only the small ZenMarket sheet headers and the last balance.
- Purchase synchronization reads `Закупки` once per relevant mutation and only
  after the existing purchase write path has identified the affected LOT-IDs.
- Finance GET caching remains 180 seconds; its contract key was bumped to v4
  after adding the UAH balance projection, preventing an older cached payload
  from hiding the new line.
- After a ZenMarket mutation, only the Finance report is refreshed in the UI.

## Performance review and proposed next pass

### Current loading model confirmed from source

- Overview already uses a staged critical path: `overview_bootstrap`, then
  `overview_secondary`, then `overview_assets`.
- Other pages already load only on their first visit through `loaded[name]`.
- Stock reuses `sku_list`, then adds stock alerts, and starts the remote 3D-P
  stock overlay after the main CRM table can render.
- Order line details and the order edit component catalogue are already lazy.
- The client and Finance reports are cached GET actions, while their detail
  requests are deferred until the owner expands a row or opens a page.

The proposed idle work must therefore warm data, not invoke every full page
loader. Calling hidden page loaders directly would mutate hidden DOM, mark pages
as loaded before they render visibly, and could trigger remote or
mutation-capable UI dependencies that the owner never requested.

### Recommended Pass C1 — measure first, low risk

1. Add client timing for every GET action: queue time, network time, render
   time, cache hit/miss, payload size, and the active page.
2. Return a bounded `elapsed_ms` and cache-hit flag from the heavy server read
   actions. Do not log customer rows, tokens, or full payloads.
3. Establish p50/p95 for cold load, warm load, and first page open before
   changing request order.

Acceptance target: no regression in initial Overview time; identify the three
largest cold actions with measured evidence. This is the prerequisite for
deleting or combining code safely.

### Recommended Pass C2 — serialized idle prefetch, medium risk

After 30 seconds from load/F5, and only while the page is visible and the owner
is idle, use `requestIdleCallback` with a `setTimeout` fallback to warm one
read-only API result at a time. Cancel or postpone when the owner interacts,
the tab is hidden, `navigator.connection.saveData` is enabled, or a foreground
request starts.

Suggested first allow-list:

1. `sku_list` — reused by Stock, Products, Accounting, and 3D-P CRM checks;
2. `ltv_report` — Clients;
3. the default current-period `finance_report` — Finance;
4. the default Orders list only after its cache contract is verified.

Prefetch should populate the existing response cache but must not set
`loaded[page]`, render hidden DOM, call external alerts/3D-P APIs, or run
Settings, Accounting, Updates, detail, integrity, or mutation actions.
Foreground navigation always has priority and should reuse an in-flight
prefetch through the existing request de-duplication map.

Acceptance target: each prefetched page opens from warm data, only one
background request exists at a time, Overview remains interactive, and no
request is made after the tab becomes hidden.

### Recommended Pass C3 — remove duplicated work, medium risk

- Decouple `loadUpdates()` from `loadAccounting()`. The current Updates loader
  sets `loaded.accounting=true`, builds the entire Accounting page, starts its
  SKU/3D-P/recent-order work, and then moves two sections into Updates. Give
  Updates its own small loader and shared render helpers instead.
- Replace `apiRecentTable_()` full-table reads with bounded tail reads where the
  contract needs only recent rows. Preserve whole-order grouping at the range
  boundary.
- Extend request-local memoization so Finance/Overview helpers share the same
  `Продажі`, `Закупки`, and header projections inside one invocation rather
  than re-reading equivalent ranges.
- Review cache invalidation by data domain. A sale should invalidate sales,
  clients, Finance, and relevant stock caches; a Zen balance correction should
  invalidate only Finance/Zen data.

Acceptance target: fewer Sheet reads and API calls with byte-equivalent visible
results and all mutation/integrity tests unchanged.

### Recommended Pass C4 — dead-code removal, evidence-gated

Do not delete functions based on name or age. First generate a static call map
for HTML handlers, inline `onclick` references, Apps Script dispatch routes,
triggers, setup/repair entry points, and tests. Cross-check it with bounded
runtime action telemetry for an agreed observation period. Classify candidates
as active, owner-run maintenance, superseded one-time code, or unreachable.
Delete only an explicitly reviewed candidate list in a separate commit so the
rollback is clean.

### Risks and non-goals

- Parallel background loading would increase Apps Script contention and can
  make the foreground slower; the proposal is deliberately serialized.
- Prefetch is never allowed to perform Sheet writes, integrity checks, alert
  mutations, or external 3D-P calls.
- A larger cache TTL can show stale operational data. Use current TTLs first
  and measure before changing freshness contracts.
- Minifying the single HTML file may reduce transfer size but will not fix
  Sheet-read latency and would make owner/Claude review harder; it is not a
  priority.
- No performance optimization from this proposal is implemented in the current
  ZenMarket tile round. Claude review and owner approval are the next gate.

## Validation

```text
All Apps Script test files: 31 passed
Focused ZenMarket tests:    6 passed
git diff --check:           passed
Apps Script source syntax:  passed
Dashboard script syntax:    passed
```

Focused coverage includes the historical balance equation, ZenPoints exclusion,
three-component purchase expense, request-ID contracts, P&L isolation, the
six-KPI layout, the UAH projection/rate contract, both two-column Finance rows,
mobile stacking, and dashboard JS syntax.

The in-app browser rejected the local `file://` dashboard URL under its browser
security policy. No bypass was attempted. DOM/CSS breakpoint contracts were
validated at desktop, 801–1200 px, and <=800 px; final visual inspection remains
an owner QA gate.

## Idempotency

- Re-running setup does not duplicate the historical seed.
- Repeating the same top-up or correction request ID does not create a second
  balance movement.
- Repeating an unchanged purchase update produces a zero ZenMarket delta.
- A pre-existing lot first edited after setup is baselined before the edit so
  its historical cost is not deducted again.

## Rollback

Before running setup, make a spreadsheet copy or backup. To roll back before
publication, discard only the scoped local diff. After publication, publish the
previous Apps Script version and restore the previous dashboard file. The two
new internal sheets can remain unused; deleting them is not required for code
rollback and should be done only after a separate owner decision and backup.

## Owner installation and QA gate

1. Run the existing CRM integrity check and confirm `clean=true`.
2. Back up the spreadsheet.
3. Paste the candidate `Code.gs` into the bound Apps Script project, save it,
   and run `setupCrm011ZenMarketAccount()` once.
4. Confirm setup returns `current_balance_jpy=-3685`, `history_seeded=true` on
   the first run, and clean integrity before and after.
5. Publish a new Web App version.
6. Open the local dashboard and press Ctrl+F5.
7. Check the Finance layout and confirm the ZenMarket KPI and readonly current
   balance show JPY -3,685. Under the KPI, confirm a smaller approximate UAH
   value appears with the text `за курсом CRM`.
8. Run `crm011ZenMarketVerificationForOwner()` and confirm `ok=true` with an
   empty `problems` list.
9. Enter future top-ups only through the compact Finance form. Use correction
   only when the real ZenMarket balance materially diverges from CRM.

## Residual risk

- The historical JPY 70,000 top-up is absent from UAH cashflow until its actual
  UAH charge is supplied.
- Apps Script has no multi-sheet database transaction. Request-ID recovery
  prevents duplicate top-ups, but a rare interruption between the two sheet
  appends may require repeating the same unchanged form submission.
- Live publication, sheet setup output, and visual QA remain owner-gated.
