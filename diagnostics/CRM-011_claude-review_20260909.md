# CRM-011 — Claude independent review

Date: 2026-09-09 | Reviewer: Claude (chat) | Author under review: Codex
Scope reviewed: tracked diff of `crm/apps-script/Code.gs` and
`dashboard/booster-dashboard.html` against `fa6619a`, plus the untracked
`crm/alerts-apps-script/`, `crm/apps-script/one-time/`, and the three new test
files. Reference: `handoffs/handoff_CRM-011_dashboard-finance-crm-upgrade_20260908.md`.

## Verdict

**RETURN FOR CHANGES.**

Nine blocking items were raised. **B9 and the whole "scope expansion" section were
withdrawn on the owner ruling of 2026-09-09 — see the amendment at the end of this
file.** Eight blocking items stand: B1-B8.

Original wording of the verdict, kept for the record: nine blocking items. Three are latent-failure defects in the CRM API that a
functional test cannot catch; six are handoff requirements shipped absent,
partial, or contradicted.

The parts that are good are genuinely good: the Orders filter architecture is a
view over untouched canonical arrays, client history is lazy and keyed
correctly, slow stock refuses to invent a zero, and the finance layer is honest
about approximation instead of printing a comfortable number. None of the
findings below are about that work.

---

## B1 — Three shadowing duplicates introduced in `Code.gs` (highest severity)

`crm/apps-script/Code.gs` now declares the same function twice in three places
that were single at `fa6619a`:

| Function | Lines | At HEAD | Now |
|---|---|---|---|
| `apiLtvReport_` | 6489, 9852 | 1 | 2 |
| `apiQualifiedClientsReport_` | 6518, 9862 | 1 | 2 |
| `crmGetOrders_` | 5869, 9873 | 1 | 2 |

(`apiAddSale_` at 3111/4176 and `getDirectOrderExpense_` at 4456/9461 were
already duplicated before this change and are out of scope here — but they are
the same pattern, and it is now spreading.)

The new implementations were appended at the end of the file instead of
replacing the originals. The code works only because a later `function`
declaration wins hoisting. Consequences:

- order listing and the entire client report depend on textual position in a
  9,900-line file;
- the next executor who opens `crmGetOrders_` at line 5869 — the one a search
  finds first — will edit dead code and observe no effect;
- any reordering, extraction, or partial paste into the bound editor silently
  reverts client segmentation and order enrichment to the pre-CRM-011 contract,
  with no error anywhere.

The handoff (WP2.4) said: *extend the existing action; do not create a
duplicate.*

**Fix:** delete the superseded bodies at 5869, 6489 and 6518 and keep one
definition per function. Re-run the parse check afterwards.

## B2 — Orders table sorting never takes effect

`renderOrdersTable` (line 2348) computes
`sortOrderRows_(filterOrderRows_(rawActive))`, then hands the result to
`renderOrderRows` (line 2310), whose first statement is `sortOrderRows(rows)`
— the legacy function at line 2208, which re-sorts by date **unconditionally**.

The table-sort result is discarded on every render. Clicking «Прибуток ↓»
updates the header arrow and changes nothing else, which is worse than the
feature being absent: the UI asserts a sort order the rows do not have.

**Fix, two edits:**

1. `sortOrderRows_`: when `orderState.tableSort.field` is empty, return
   `sortOrderRows(rows)` instead of `rows.slice()`.
2. `renderOrderRows`: iterate `rows` as given — remove the `sortOrderRows(...)`
   call.

## B3 — `finance_report.pnl` collapses the P&L to four lines

`Code.gs`:10033 returns
`pnl: { revenue, gross_profit, operating_expenses, operating_profit }`, and the
UI (2621) renders exactly those four rows.

The handoff's WP3 structure was Виручка → Собівартість → Валовий прибуток →
Пакування → Доставка магазину → Платіжні комісії → Інші операційні витрати →
Списання → Чистий прибуток.

As shipped, the block cannot answer the one question it exists for — *куди
пішли гроші між виручкою і прибутком*. This is a server-contract defect, not a
CSS problem: the per-period sums of `Продажі` O, P, Q, R, S, T are not in the
response at all. Those columns are already resolved by header name in
`apiOrderItems_` (3130 area), so the aggregation is available; it was simply
not built. The correction needs a new Apps Script publication, not only a
dashboard edit.

Rules that must hold in the expanded block, unchanged from the handoff: no
marketing line under order-level expenses; operating expenses select `Витрати`
rows by column L only; discount is never subtracted again.

## B4 — Finance comparison window is wrong for calendar-month periods

`loadFinance` (2613) always builds "an equal-length range immediately before the
current one". For «Поточний місяць» on 9 September that compares 1–9 Sep against
23–31 Aug.

The handoff specified MTD against the same day-span of the previous month —
1–9 Sep vs 1–8/9 Aug — precisely because a month-to-date figure compared to the
tail of the previous month is not a comparison of anything.

**Fix:** derive the comparison range from the selected preset, not from the
range length. Only `7д`, `30д`, `90д` and `Свій період` use the equal-length
rule; `Цей місяць` shifts the same day-span back one month; `Минулий місяць`
takes the full month before it.

## B5 — Three of six period presets are missing

`#financePreset` (line 681) offers `Поточний місяць`, `Останні 30 днів`,
`Свій період`. Absent: `7 днів`, `Минулий місяць`, `90 днів`.

The acceptance criterion "кожен preset повертає правильні boundaries" cannot be
evaluated for presets that do not exist, and `Минулий місяць` is the one an
owner actually reaches for when closing a month.

## B6 — Comparison renders no delta

`financeMetric_` (2609) emits only `було ₴X` beside the current value.

The handoff required an absolute delta and a percentage delta per metric, with
the percentage shown as `—` when the previous value is 0 (never `∞%`). As
shipped, the owner performs the subtraction that the comparison toggle exists to
perform.

## B7 — «Маржа < 15%» filter missing from Orders

`#ordersProfit` (line 620) offers `Прибуткові` / `Збиткові`.

`Прибуткові` was not requested. `Маржа < 15%` was — it is named in the handoff's
WP4 filter list and again in its acceptance list. The low-margin filter is the
one that surfaces orders that look fine and are not; a positive/negative split
does not.

Row margin is already computable client-side from `amount` and `profit`
(`orderTableSortValue_` does it for `net_pct`), so this is a frontend-only fix.

## B8 — New vs Repeat shipped as two counters

`customer_mix` returns `new_orders` and `repeat_orders`; the UI (2625) renders
those two numbers.

The handoff required a table of Замовлень / Виручка / Середній чек / Маржа
across New and Repeat, plus a note counting orders excluded from the split for
lack of a stable client identity while remaining inside the P&L totals.

Order counts alone do not support the question the block was specified to answer
— whether new or returning customers generate the result. Revenue and margin per
group are the half that carries the answer. Server-side; ships with B3.

## B9 — Overview was changed

The Overview «Потребують уваги» block was removed and replaced with
«Передзамовлення»; managed issues moved to a new lazy «Увага» page.

The handoff's *What NOT to touch* and owner decision D6 both state that Overview
keeps its current behaviour, navigation wiring only. The new arrangement may
well be an improvement — preorders on Overview are defensible and alerts do
deserve their own surface — but it was not authorised in this task, and the
owner has not seen a proposal for it.

**Owner decision required:** ratify the change and record it as an amendment, or
restore the previous Overview block and move the alerts page to its own task.

---

## Scope expansion beyond the handoff

Independent of the defects above, this round delivered a substantial amount of
unrequested work while requested work stayed unbuilt. Listed so the owner can
rule on each, not because any of it is badly implemented:

1. **Fiscal receipt gate.** New `Продажі!AH` column plus a server-side throw at
   `Code.gs`:9573 blocking any order update to `Оплачено` + `Отримано` without
   the flag. The implementation is correct — it raises before any write, reads
   and writes the whole order in one range each, and cannot be bypassed from the
   client. But it is a new hard gate on the owner's daily order-closing workflow
   that no one asked for in CRM-011.
2. **A second Apps Script project.** `crm/alerts-apps-script/` — 358 lines, its
   own Web App URL, its own token, a new `_Керування_Алертами` sheet, and
   Telegram alert suppression. That is a service, not a work package. It carries
   its own deployment, its own secret, and its own failure modes.
3. **New «Увага» page** in the sidebar, which now holds twelve entries.
4. **Arrival dates in slow stock** with expandable contributing lots.
5. **The one-time importer already ran against live data.** 108 ZenMarket
   payments were written into the production sheet on 2026-09-08. Owner decision
   D7 stated the historical import is a separate owner step and explicitly does
   not block WP2. The review handoff itself records that no verified Sheet
   rollback copy exists for that write.

The handoff also specified six independently deployable work packages, one per
change set. This arrived as one change set spanning two Apps Script projects and
the dashboard, which is why the missing pieces and the extra pieces can no
longer be shipped or reverted separately.

---

## Non-blocking, worth doing in the same pass

- **Missing KPI.** `Середній чек` was in the specified five tiles and is absent;
  `Валовий прибуток` was added in its place. `finance_report.totals` carries no
  `avg_order`, so this ships with B3.
- **Clients table is 15 columns.** «Клієнт» (the phone/display value) and
  «Імʼя» occupy two columns for one identity. Recommended main row: Клієнт ·
  Сегмент · Замовлень · LTV · Прибуток · Маржа · Остання покупка · Днів без
  покупки. Канал, Перша покупка, Інтервал, IP and phone belong in the expanded
  card, which already renders well.
- **Dead code in the dashboard.** Four functions are declared twice —
  `toggleClientDetail` (2509 / 2592), `clientSortValue_` (2552 / 2598),
  `renderClientsTable_` (2556 / 2599), `loadClients` (2568 / 2604). The later
  declaration wins, so the new Clients tab is live and roughly 85 lines of the
  previous renderer are unreachable — including a `colspan="10"` that would
  break the 15-column table the moment anyone reorders the file. Same
  append-instead-of-replace pattern as B1. Delete the old block.
  (`render` at 4295 / 4352 is pre-existing and out of scope.)
- **Empty IP column.** The server logic is correct: `crm011GameCode_` takes the
  SKU prefix from `row[5]` and matches `PKM` / `OP` / `MTG` / `YGO`, and
  `crm011ClientModel_` populates `games` per client. If the column renders empty
  against live data while `Сегмент` and `Перша покупка` populate, the cause is
  not this function — check first whether the response is being served from the
  pre-CRM-011 `apiLtvReport_` at line 6489 (see B1: the shadowing means a
  partial paste into the bound editor can silently serve the old contract), and
  only then whether the SKU prefixes in `Продажі` differ from the four codes.
  Do not "fix" `crm011GameCode_` before that is established.
- **Roadmap series derivation is correct** per owner decision D5:
  `/^(.*)-\d+$/` yields `RD`, `CRM`, `MKT-TG`, `3D-P` as intended. Note only
  that a non-numeric ID such as `CRM-006-ORDER` becomes its own dropdown entry.
  Acceptable; no action.
- `CLIENT_COLUMN_COUNT` fulfils the handoff's `CLIENT_TABLE_COLUMN_COUNT`
  requirement under a different name. No action.

---

## What this review could not establish

- **Live source identity.** `SOURCE_STATE.md` records V168 as owner-reported
  while the last byte-verified mirror is V164. Every statement in this review
  describes the local candidate, which is not proven to be what is deployed.
- **Server-side finance results against live data.** The contract, the column
  semantics and the double-count avoidance are verifiable by reading; the actual
  aggregates are not, without a published endpoint.
- **The alerts endpoint.** Reported as returning `doGet not found`; replacement
  source, token setup and publication are unconfirmed. Nothing about its runtime
  behaviour is verified here.
- **Segment distribution.** The handoff required reporting the real distribution
  after implementation so a threshold that swallows 80–90% of the base is
  caught. No distribution figures were supplied. `distribution` is present in
  the response (9858), so this is a matter of running it and reporting.


---

# Amendment — owner ruling, 2026-09-09

The owner states that the fiscal-receipt gate, the separate alerts Apps Script
project, the `Увага` page, the arrival dates in slow stock and the Overview
rearrangement were **all requested by him directly in the Codex chat**, after
the CRM-011 handoff was written.

Accordingly:

- **B9 (Overview was changed) is withdrawn.** It is authorised work, not an
  unapproved deviation. No restoration is required and no amendment note is owed
  by Codex.
- **The "Scope expansion beyond the handoff" section is withdrawn** as a finding.
  Items 1-4 are authorised. Item 5 (the one-time importer already run against
  live data) stands only as a data-safety note, and the owner has undertaken to
  take a verified copy of the `Продажі` sheet before round 2.
- The observation that this arrived as one change set rather than six separately
  deployable packages still holds as a fact about deployability, but it is a
  consequence of the same live authorisation and is not charged against the
  executor.

**Process cause, not a defect of anyone's work.** The authorisations were given
in a side channel and never reached the handoff, the Notion task, or the
repository. The reviewer therefore measured a correctly-executed change against
a specification that no longer described what the owner wanted, and reported
legitimate work as scope expansion. The cheap fix is a one-line addendum to the
handoff (or an append-only note on the Notion task) at the moment an
authorisation is given mid-flight — cheaper than a review round spent on
findings that dissolve on contact with the owner.

**Standing after this ruling:** B1-B8 remain blocking and are carried into
`handoffs/handoff_CRM-011-R2_defect-fixes_20260909.md`.
