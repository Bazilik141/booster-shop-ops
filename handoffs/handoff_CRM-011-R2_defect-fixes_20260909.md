# Codex Handoff — CRM-011 round 2: defect fixes

Date: 2026-09-09 | Parent: CRM-011
Executor: Codex — same executor as round 1, never swap mid-round (AGENTS.md).
Pass A: model=Terra · effort=medium. Pass B: model=Sol · effort=xhigh.

Source of findings: `diagnostics/CRM-011_claude-review_20260909.md`, including
its 2026-09-09 amendment. Read the amendment first — B9 and the entire scope
section were withdrawn on the owner ruling. The fiscal-receipt gate, the alerts
project, the `Увага` page, the arrival dates and the Overview rearrangement are
authorised work. Do not revert any of them, and do not treat them as debt.

Round 1 is not being rejected. The Orders filter architecture, lazy client
history, slow stock and the finance layer's honesty about missing data are all
kept as built. This round closes eight defects, nothing more.

## Two passes — do not merge them

The split exists so the owner gets working sorting and filters today without
waiting on an Apps Script publication.

- **Pass A is frontend only.** `dashboard/booster-dashboard.html` alone. No
  Apps Script change, no publication, no sheet touch. Delivery is Ctrl+F5.
- **Pass B is one Apps Script release** plus the dashboard rendering that
  consumes the new contract.

Ship Pass A first and let the owner run it before Pass B is written.

---

# PASS A — dashboard only, no publication

## A1 — Orders table sorting never takes effect (B2)

`renderOrdersTable` (line 2348) sorts with `sortOrderRows_`, then passes the
result to `renderOrderRows` (line 2310), whose first statement re-sorts every
batch with the legacy `sortOrderRows` (line 2208) — by date, unconditionally.
The table sort is discarded on every render while the header arrow claims it
was applied.

Two edits:

1. `sortOrderRows_`: when `orderState.tableSort.field` is empty, return
   `sortOrderRows(rows)` rather than `rows.slice()`, so date order stays the
   default.
2. `renderOrderRows`: iterate `rows` as received — delete the `sortOrderRows(…)`
   call. The caller owns ordering.

Sorting must survive a filter change, a mode change and an expanded row.

## A2 — «Маржа < 15%» filter is missing (B7)

`#ordersProfit` (line 620) offers `Прибуткові` / `Збиткові`. `Прибуткові` was
not requested and may stay. Add the third option that was:

```
<option value="low_margin">Маржа &lt; 15%</option>
```

Predicate in `filterOrderRows_`: `Number(row.amount) > 0` and
`Number(row.profit) / Number(row.amount) * 100 < 15`. An order with no amount or
no profit value is **not** matched — it is unknown, not low-margin.

`orderTableSortValue_` already computes this ratio for `net_pct`; reuse one
helper rather than writing the expression twice.

## A3 — Finance period presets, three of six missing (B5)

`#financePreset` (line 681) has `month`, `30d`, `custom`. Add `7d`,
`last_month`, `90d` so the full set is:

| value | label | from | to |
|---|---|---|---|
| `7d` | 7 днів | today − 6 | today |
| `30d` | 30 днів | today − 29 | today |
| `month` | Цей місяць | 1st of this month | today |
| `last_month` | Минулий місяць | 1st of previous month | last day of previous month |
| `90d` | 90 днів | today − 89 | today |
| `custom` | Свій період | user | user |

All boundaries in Europe/Kyiv, passed to the API as explicit `date_from` /
`date_to` strings — no UTC rollover may move a near-midnight sale.

## A4 — Comparison window is wrong for calendar-month presets (B4)

`loadFinance` (line 2613) always builds "an equal-length range immediately
before". On 9 September that compares 1–9 Sep against 23–31 Aug.

Derive the comparison range from the **selected preset**, not from the range
length:

| Preset | Comparison |
|---|---|
| `7d` / `30d` / `90d` / `custom` | equal-length range immediately before — the current behaviour, keep it |
| `month` | the same day-span of the previous month (1–9 Sep → 1–9 Aug; clamp to the last day of that month when it is shorter) |
| `last_month` | the full calendar month before the selected one |

Store the active preset in finance state; do not re-infer it from the date
inputs, because a user who edits a date manually has moved to `custom`.

## A5 — Comparison shows no delta (B6)

`financeMetric_` (line 2609) renders only `було ₴X`.

Show, per metric: the current value, the absolute delta, and the percentage
delta. Rules:

- previous = 0 or null → show the absolute delta, percentage renders `—`. Never
  `∞%`, never `100%` as a stand-in.
- current or previous unavailable → the whole delta renders `—`, not `0`.
- percentage rounded to one decimal.
- direction is coloured, but check the dashboard's existing convention before
  picking the tokens, and remember that a fall in an expense line is good while
  a fall in revenue is not — if one shared component cannot express that, pass
  the polarity in explicitly rather than colouring every decrease red.

## A6 — Delete the dead client block

Four functions are declared twice: `toggleClientDetail` (2509 / 2592),
`clientSortValue_` (2552 / 2598), `renderClientsTable_` (2556 / 2599),
`loadClients` (2568 / 2604). The later declarations win, so the Clients tab is
already the new one and roughly 85 lines of the previous renderer are
unreachable — including a `colspan="10"` that would break the 15-column table
the moment anything is reordered.

Delete the earlier block. Confirm afterwards that no declaration name appears
twice in the file except `render` (4295 / 4352), which is pre-existing and out
of scope.

## A7 — Slim the clients table

15 columns is a horizontal scroll, and «Клієнт» plus «Імʼя» are two columns for
one identity.

Main row: `Клієнт · Сегмент · Замовлень · LTV · Прибуток · Маржа · Остання
покупка · Днів без покупки`.

Move into the expanded card, which already renders well: `Канал`, `IP`,
`Перша покупка`, `Інтервал`, `Витрати 60д`, phone.

`CLIENT_COLUMN_COUNT` must follow the new count — it is used by the detail row
and by both error states. Sorting must keep working for every field that stays
in the header; a field moved into the card no longer needs a sort control.

## A8 — Inventory assets are a balance, not a period figure

Added 2026-09-09 after the owner asked whether daily asset snapshots should be
introduced. They should not — see the ruling below. This item is the whole
correction, and it is frontend only.

`finance_report.inventory_assets` is computed from current state: all
non-cancelled lots, split by whether they have a UA arrival date. It is not
bounded by `date_from` / `date_to` and must not be.

The actual defect is presentational. The assets block sits inside a
period-scoped tab, so a figure that describes today silently reads as if it
described the selected period. Selecting August and seeing today's capital is
wrong in exactly the way this project treats as a defect elsewhere: a number
that is not what the reader believes it is.

Fix:

- Move the assets block visually out of the period-scoped region, or mark it
  clearly enough that no reader ties it to the selector.
- Title or subtitle carries **«станом на сьогодні»**.
- Keep the existing exclusion note (lots outside UA, lots with no reliable
  valuation, excluded SKUs) — it is already correct.
- Change nothing on the server. `inventory_assets` stays unbounded.

**Do not implement daily asset snapshots, and do not render
`Історії ще немає` for past periods.** The owner ruled against that scheme on
2026-09-09 for three reasons, recorded here so it is not re-proposed:

1. A snapshot log only covers dates after it is switched on. Every historical
   period the owner actually opens — «Минулий місяць» above all — would show
   an empty state for months, and would still show it in November for August.
2. A daily snapshot needs a time-driven Apps Script trigger. Triggers fail
   silently and self-disable after repeated errors. If the snapshot is the only
   source, one broken trigger leaves a hole that cannot be backfilled.
3. A snapshot freezes what the system believed that day. This CRM edits history
   routinely (cost repairs, FIFO corrections). A frozen figure would drift away
   from the P&L permanently and invisibly.

If period-accurate assets are wanted later, the method is **reconstruction from
the dated data that already exists**, not a snapshot log:

```
assets at date D, per lot:
  arrived in UA on or before D   (Дата доставки в Україну <= D)
  minus units sold and written off up to D
  times unit cost
```

`getFifoCostBatches_` already performs exactly this kind of date-bounded
consumption (sales plus write-offs up to a given date), so the logic exists and
would be reused rather than invented. Reconstruction works retroactively from
day one, self-corrects when history is repaired, and adds no sheet and no
trigger — it is strictly less invasive than snapshots. Lots with no dates at all
would be surfaced as a counted, labelled exclusion, never folded into a total.

That work is out of scope for this round and belongs to its own roadmap row.

## Pass A acceptance

- Sorting by Сума, Маркетинг, Прибуток and Чистий прибуток % actually reorders
  rows, both directions, and survives filtering, mode switch and an expanded row.
- `Маржа < 15%` returns only orders below 15% and combines with the other
  filters through AND; `Prom + Repeat + маржа < 15%` returns the intersection.
- Each of the six presets produces the boundaries in the table above.
- With compare on: `Цей місяць` on 9 September compares against 1–9 August;
  `Минулий місяць` against the month before it.
- Each KPI shows an absolute and a percentage delta; a zero previous value shows
  the absolute delta with `—` for the percentage.
- No function is declared twice except the pre-existing `render`.
- The clients table fits without horizontal scroll at the owner's desktop width;
  the expanded card holds what was removed.
- The assets block reads as a present-day balance and cannot be mistaken for a
  figure belonging to the selected period; no snapshot sheet, trigger or
  `Історії ще немає` state was introduced.

---

# PASS B — one Apps Script release + its rendering

## B1 — Remove the three shadowing duplicates (review B1) — do this first

`crm/apps-script/Code.gs` declares three functions twice, all single at
`fa6619a`:

| Function | Superseded body | Live body |
|---|---|---|
| `crmGetOrders_` | 5869 | 9873 |
| `apiLtvReport_` | 6489 | 9852 |
| `apiQualifiedClientsReport_` | 6518 | 9862 |

The new implementations were appended instead of replacing the originals, so
order listing and the whole client report depend on textual position in a
9,900-line file. A search finds the dead `crmGetOrders_` first; a partial paste
into the bound editor silently restores the pre-CRM-011 contract with no error
anywhere.

Delete the superseded bodies at 5869, 6489 and 6518. Deleting a shadowed
declaration is behaviour-neutral by construction — the live behaviour is already
the later one — so this carries no functional risk, and it must land before
anything else edits those functions.

Do not touch `apiAddSale_` (3111 / 4176) or `getDirectOrderExpense_`
(4456 / 9461): both were already duplicated before CRM-011 and belong to their
own task. Note them in the report so they are not lost.

After the deletion, no function may be declared twice in `Code.gs`. Prove it in
the diagnostic with the check output, not with a claim.

## B2 — Expand `finance_report.pnl` (review B3)

`Code.gs`:10033 returns `pnl: { revenue, gross_profit, operating_expenses,
operating_profit }`. Four lines cannot answer *куди пішли гроші між виручкою і
прибутком*, which is the only reason the block exists.

Return the full breakdown, aggregated per period from `Продажі`:

```
revenue            K
cogs               O   management cost
gross_profit       revenue − cogs
packaging          P
delivery           T   shop-paid delivery
payment_fees       Q + R + S   (resolve by header name, see below)
operating_expenses Витрати rows with column L = Так
writeoffs          only where a reliable monetary cost valuation exists
net_profit
margin_pct
```

Non-negotiable rules, unchanged from the original handoff:

- Resolve every column **by header name**, never by letter. The June letters are
  stale: `apiOrderItems_` (3130 area) already reads `Нова Пей` and
  `Комісія маркетплейсу`, which the old R/S naming does not cover. Record the
  live `V` expansion you find in the diagnostic.
- No marketing line under order-level expenses. Order-level marketing is 3D-P
  gift marketing only and is already excluded from operating expenses
  (`Code.gs`:9436); ad spend is an ordinary `Витрати` row selected by column L.
- The discount is already inside K — never subtract it again.
- `revenue − cogs − packaging − delivery − payment_fees − operating_expenses
  − writeoffs` must equal `net_profit` for the period, and the sum of the
  order-level deductions must reconcile with the `V` column. If it does not,
  stop and report rather than adjusting a line to make it balance.
- A write-off with no reliable valuation goes to `data_quality`, not into the
  P&L as `0`.
- Missing stays `null`. Nothing in this block may render a fabricated zero.

Add `avg_order` to `totals` so the KPI row can show `Середній чек`.

## B3 — New vs Repeat as analysis, not two counters (review B8)

`customer_mix` currently returns `new_orders` and `repeat_orders`. Return per
group — new and repeat — `orders`, `revenue`, `avg_order`, `margin_pct`, plus a
top-level `unidentified_orders` count.

Classification is at **order level**, unchanged: an order is New when the client
had no prior identified order **at that moment**, Repeat when they had at least
one. A client whose first order is 3 Sep and second is 7 Sep contributes one New
and one Repeat to September. Do not classify by lifetime state.

Orders with no stable client identity are counted in `unidentified_orders`,
never silently added to New, and they stay inside the P&L totals.

## B4 — Rendering for the expanded contract

- P&L renders the full breakdown as a two-column table: muted label left,
  right-aligned figure right, subtotal rows separated by a top border rather
  than a new component. Give `Валовий прибуток` and `Чистий прибуток` more
  weight than the deduction lines, and colour the final result by sign. No
  waterfall chart, no graphs — the hierarchy is the readability fix.
- KPI row becomes the five specified tiles: `Виручка · Чистий прибуток ·
  Чиста маржа · Замовлень · Середній чек`. `Валовий прибуток` moves into the
  P&L block where it already belongs.
- New vs Repeat renders the table: rows `Замовлень / Виручка / Середній чек /
  Маржа`, columns `Нові / Повторні`, with the note
  `X замовлень не включено в New/Repeat через відсутність стабільного client
  identity` shown when `unidentified_orders > 0`.

## B5 — Report the segment distribution

The original handoff required it and round 1 did not supply it. `distribution`
is already in the `ltv_report` response (9858). Run it and put the real
percentages in the diagnostic. If one segment holds 80–90% of the base,
investigate the thresholds before declaring success.

## Pass B acceptance

- No duplicate function declarations remain in `Code.gs`; the check output is in
  the diagnostic.
- For one test period, 3–5 orders reconciled by hand prove
  `revenue − cogs − packaging − delivery − payment_fees − operating_expenses
  − writeoffs = net_profit`, with no line subtracted twice.
- The live `V` expansion is recorded by header name.
- A client with a first and a second order inside the same period appears once
  as New and once as Repeat.
- `unidentified_orders` is surfaced and those orders are still inside the P&L.
- `Середній чек` renders in the KPI row.
- Segment distribution reported with real figures.

---

## Mandatory for both passes

- **Pre-flight.** Confirm `SOURCE_STATE.md` describes the source you are
  editing. Round 1 left main CRM V168 owner-reported against a last
  byte-verified mirror of V164 — if that gap is still open, resolve it before
  Pass B rather than assuming.
- **OPS-CRMINTEGRITY.** Pass B changes no sheet structure, so the integrity
  check is not triggered by a column addition — but run it before and after the
  publication anyway and record both outputs, because the finance and client
  read paths are being rewritten.
- Never write a literal over a formula column. `Продажі` K and N–V and `Витрати`
  L–M are sheet formulas.
- Audit every fixed-width sheet read you touch; `_getCrmSalesRowEntries()` has a
  fixed column count and any new read must match it.
- Dashboard is PC-only. No mobile or tablet scope.

## What NOT to touch

- The fiscal-receipt gate, the alerts project, the `Увага` page, the arrival
  dates in slow stock and the current Overview arrangement — all owner-authorised
  in the Codex chat and ratified 2026-09-09.
- `apiAddSale_` and `getDirectOrderExpense_` duplicates — pre-existing, separate
  task.
- The `qualified` criterion — preserved as built.
- The Orders filter architecture, the lazy client history and the slow-stock
  block — correct as delivered.
- `dashboard/tests/dashboard-contract.test.mjs`'s known pre-existing failure
  (missing `Кейс / контейнер для зберігання` category) — out of scope.
- Secrets: `.env.review`, `scripts/.env`, `client_secret.json`. Apps Script
  secrets live in Script Properties and never enter a mirror.

## Owner gate before Pass B is published

The owner takes a verified copy of the `Продажі` sheet first. Round 1 wrote 108
ZenMarket payment rows into live data with no recorded rollback artifact; a
second pass must not land on top of that without a copy.

## Deliverables

One diagnostic per pass. Each states: what changed, what was verified and how,
the reconciliation evidence for Pass B, assumptions, and known limitations
stated plainly. Pass B additionally carries the duplicate-declaration check
output, the live `V` expansion, both integrity outputs, and the segment
distribution.
