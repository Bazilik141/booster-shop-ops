# CRM-011 R2 — Pass A dashboard defect-fix report

Date: 2026-09-09

## Outcome

Pass A is implemented locally and is ready for owner Ctrl+F5 QA. This pass
changes only the dashboard frontend and its focused local test; it does not
change Apps Script, publish a Web App version, touch a Google Sheet, commit, or
push.

Pass B has not been started. The round-2 handoff explicitly requires owner QA of
Pass A before any Pass B implementation.

## Root causes and fixes

### A1 — Orders sorting

`renderOrdersTable()` passed table-sorted rows into `renderOrderRows()`, but
`renderOrderRows()` immediately applied the legacy date sort again. The visible
arrow changed while row order did not.

- `renderOrderRows()` now renders rows exactly as received.
- `sortOrderRows_()` owns the final order and falls back to the legacy date sort
  only when no table field is selected.
- Filtering, order mode changes, and expanded-detail rendering all continue to
  use the same ordering owner.

### A2 — Low-margin filter

- Added `Маржа < 15%` to `#ordersProfit`.
- The predicate remains AND-joined with every other order filter.
- `orderProfitPct()` is now the shared ratio helper for display, sorting, and
  filtering. Missing profit, missing amount, zero amount, and negative amount
  are unknown and do not match the low-margin filter.

### A3/A4 — Finance presets and comparison windows

- Added all six presets: 7 days, 30 days, current month, previous month, 90
  days, and custom.
- Calendar values are derived explicitly for `Europe/Kyiv` and serialized from
  UTC calendar containers, avoiding browser/UTC rollover at midnight.
- Manual date edits switch state to `custom`; the comparison logic uses stored
  preset state instead of guessing from the input values.
- Current month compares with the same day span of the previous month, clamped
  to that month's last day. Previous month compares with the complete calendar
  month before it. Rolling/custom ranges keep the equal-length prior range.

### A5 — KPI deltas

- Compared KPI tiles now show absolute and percentage change.
- A zero previous value shows the absolute change and `—` for percentage.
- Unavailable current or previous data shows `зміна: —`; it is never rendered
  as zero or infinity.
- Direction color supports explicit polarity. The current revenue/profit/margin/
  order tiles use higher-is-better; the helper also supports lower-is-better for
  a future expense tile without reversing all decreases globally.

### A6 — Dead Clients implementation

Removed the earlier unreachable declarations of `toggleClientDetail`,
`clientSortValue_`, `renderClientsTable_`, and `loadClients`, plus their unused
legacy renderer helper. The shared live sort controls were retained.

Duplicate-declaration check output after the edit:

```text
render=2
```

`render` is the one known pre-existing duplicate explicitly excluded by the
handoff. No other dashboard function name is declared twice.

### A7 — Slim Clients table

The main table now has exactly eight columns:

`Клієнт · Сегмент · Замовлень · LTV · Прибуток · Маржа · Остання покупка · Днів без покупки`

Channel, IP/game, first purchase, average interval, 60-day spend, and phone are
shown in the expanded card. The existing average-check value remains there.
`CLIENT_COLUMN_COUNT` is 8 and drives loading, success, and error detail rows.
Every field left in the header remains sortable.

## Verification

Focused Pass A test, run directly and sequentially:

```text
dashboard/tests/crm-011-r2-pass-a.test.mjs: 7/7 pass
```

It verifies script compilation, final order ownership, both sort directions,
default date order, AND-joined low-margin filtering, all preset boundaries,
month comparison and short-month clamping, zero/unavailable deltas, explicit
expense polarity, eight client columns, expanded-card fields, and duplicate
declarations.

The two added checks cover the owner QA explanations and A8: Assets are visibly
labelled as today's current balance, the period selector is declared irrelevant
to that block, and no snapshot/history placeholder is introduced.

Regression checks:

```text
dashboard/tests/crm-011-dashboard.test.mjs: 6/6 pass
crm/apps-script/tests/crm-011-finance-dashboard.test.mjs: 6/6 pass
crm/apps-script/tests/crm-011-followup-data-and-alerts.test.mjs: 4/4 pass
dashboard/tests/settings-workflows.test.mjs: pass
dashboard/tests/3dp-sync-journal-static.test.mjs: pass
git diff --check: pass
```

`dashboard/tests/dashboard-contract.test.mjs` still has the documented
pre-existing category mismatch: the dashboard list lacks
`Кейс / контейнер для зберігання`. CRM-011 R2 did not touch it.

The test files were run one at a time because the sandbox denied the test
runner's parallel child-process spawn with `EPERM`; direct sequential execution
completed normally.

## UI/CSS review

Pass A adds no CSS rule, `!important`, `position:absolute`, or
`position:fixed`. It adds no timer; the existing 180 ms order-search debounce
from round 1 remains unchanged. Desktop-only scope is preserved.

## Owner QA gate

### Owner feedback amendment

The first owner QA pass found five presentation/contract-visibility issues. The
dashboard now:

- uses `↑` for a positive change and `↓` for a negative change, so a red
  negative margin no longer starts with the upward-looking delta glyph;
- renames `Lifetime прибуток` to `Прибуток із замовлень` and states that it is
  the all-history order profit after direct order costs but before general
  business operating expenses; it is not presented as company net profit;
- shows counts on `Усі` and `Важливі` and displays the exact OR rule: 2+ orders,
  or 60-day spend above 1,500 UAH, or margin above 40%. If every client matches,
  it says so explicitly instead of presenting two apparently identical modes;
- places New/Repeat and Data Quality in one two-column row;
- detects an older `finance_report` response and explains that Assets and the
  full cash-out contract require the Pass B API publication, instead of showing
  a column of unexplained `Немає даних` values.
- renames the unclear ZenMarket sub-row to `Сума поповнень у єнах` and explains
  `Чистий рух грошей` as customer receipts minus ZenMarket top-ups and other
  actual payments for the selected period; it is explicitly not accounting
  profit.

The empty Assets/cash-out values were not replaced with zero and no backend was
published as part of Pass A.

The updated handoff resolves the later request for period comparison in Assets:
inventory assets are a current balance, not a period metric. Pass A therefore
moves the Assets block after the period-scoped Finance blocks, labels it
`Активи · станом на сьогодні`, and states that the selected period does not
change those figures. No daily snapshot sheet, trigger, historical fallback, or
`Історії ще немає` state is proposed or implemented. P&L and Cashflow comparison
remain Pass B work because they require the expanded server response.

Open the canonical local dashboard and press Ctrl+F5. Verify:

1. Orders reorder in both directions for Amount, Marketing, Profit, and Net
   profit %, then keep that order after changing a filter, changing order mode,
   and expanding one order.
2. `Prom + Repeat + Маржа < 15%` returns only the intersection.
3. All six Finance presets populate the expected dates. With comparison on,
   current month uses the same dates of the previous month and previous month
   uses the full month before it.
4. Compared KPI tiles show absolute and percentage changes; a zero baseline
   shows `—` instead of an infinite percentage.
5. Clients shows eight main columns without horizontal scrolling at the owner's
   desktop width, and the removed fields appear after expanding a client.

If any item fails, stop and send the visible result before Pass B. If all five
pass, owner confirmation authorizes implementation of Pass B, but not live
publication; publication retains its separate backup and integrity gates.
