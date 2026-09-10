# CRM-011 — Claude review of the ZenMarket account and migration expansion

Date: 2026-09-10 | Reviewer: Claude (chat) | Author under review: Codex
Reports reviewed:
`diagnostics/CRM-011_zenmarket-account_report_20260910.md`,
`diagnostics/CRM-011_internal-migration-expansion_report_20260910.md`.
Source under review: owner export `Версія 171, 10 вер. 2026 р., 1828` and the
repository mirror `crm/apps-script/Code.gs`.

## Verdict

**Review OK; two defects returned, one new defect found outside both reports.**

Round-2 Pass B is verified landed on the way through: the three shadowing
duplicates are gone (only the pre-existing `apiAddSale_` and
`getDirectOrderExpense_` remain, as the handoff required), `pnl` now carries
`cogs / packaging / delivery / payment_fees / operating_expenses / writeoffs /
net_profit / margin_pct`, `customer_mix` carries per-group orders, revenue,
`avg_order`, `margin_pct` plus `unidentified_orders` with `null` rather than
zero for missing values, and `totals.avg_order` exists. The five agreed KPI
tiles survived the addition of the sixth.

## Mirror gate — CLOSED at V171

The owner export and the repository mirror are byte-identical after CRLF
normalisation. The single differing line is a regex literal whose escaped
quotes the CSV export itself consumed:

```
mirror : body.match(/\["wrb\.fr","Fbv4je","((?:\\.|[^"])*)"/)
export : body.match(/\["wrb\.fr",Fbv4je,((?:\\.|[^])*)"/)
```

That is an export artefact, not a source difference. `SOURCE_STATE.md` should
record V171 as byte-verified and close the V168-reported / V164-verified gap.

**Consequence the reports do not state:** both reports say "no publication was
performed", which was true when they were written — but the live V171 source
already contains `ZenMarket_Рахунок`, `crm011ZenmarketTopup_` and
`container_to_units`. The owner published after the reports. Every defect below
is therefore **live**, not pre-publication.

**Process note for future verifications:** the `.csv` export is a CSV file, not
plain text. Every source line is a CSV record, so a line containing a comma is
split across columns; reading only column 0 fabricates a "hundreds of lines
differ" result. Rejoin the columns, and prefer requesting a `.gs`/`.txt` export
when the comparison matters.

## Z1 — the ZenMarket data-quality counter is a literal

`historical_topup_uah_missing: 1` appears as a hardcoded `1` in three places,
and `zenmarket_historical_topup_uah_missing: 1` in the `finance_report`
`data_quality` block. Nothing counts anything.

Two consequences, both in the future rather than today:

- once the owner supplies the UAH amount for the ¥70,000 top-up, the flag still
  reports 1 — permanently;
- if a second top-up ever lands without a UAH amount, the flag still reports 1.

A counter that can neither clear nor grow trains the reader to ignore it, and
it will be ignored exactly when it finally matters. Severity is low today and
rises the moment Z2 puts it on screen.

## Z2 — the gap is real, and invisible

`ZEN-HIST-008`, 2026-09-10 05:28:58 by the ZenMarket clock, `+¥70,000`, the
movement that first took the balance positive (−65,019 → +4,981). Its seed row
carries the note `UAH сума відсутня у HTML`.

Nothing was lost: the ZenMarket balance history is denominated in JPY only. The
UAH figure exists on the payment page at the moment of payment and in the card
statement, neither of which is in the supplied HTML. Codex was right not to
fabricate it.

**Owner-supplied value: ₴22,000.** Implied rate 70,000 ÷ 22,000 = 3.18, which
confirms the `Курси` JPY rate of 3.2 to within 0.6% — no rate change is needed.

Until that amount is recorded, cash-out is understated by roughly ₴21,900, and
the dashboard contains zero references to `uah_missing`. The one rule this
project applies everywhere — missing is not zero — is unapplied in the single
place where money is actually missing.

## New defect — non-ZenMarket purchases charge the ZenMarket balance

Not raised in either report. Found while tracing the owner's observation that a
non-ZenMarket purchase had moved the Zen balance.

The spreadsheet-form path `addPurchase()` (menu «Booster CRM → Додати закупку»):

1. writes `purchases.getRange(row, 20).setValue('zenmarket_jp')` unconditionally
   — the form has no supplier field at all, so every purchase entered there is
   stamped as a ZenMarket purchase;
2. then calls `crm011ZenSyncPurchaseLots_(ss, lotIds, { force_zenmarket: true })`.

`crm011ZenIsPurchaseRow_(row, forceZenmarket)` opens with
`if (forceZenmarket) return true;` — so the supplier column is not even read.
The same `force_zenmarket: true` appears in `updatePurchase()` and in the
`crm011ZenEnsurePurchaseBaselines_` call beside it.

The dashboard path is correct: `crm011ApiAddPurchase_` derives `isZenmarket`
from `supplier_channel` and syncs only when true; `apiUpdatePurchaseBatch10_`
filters through `crm011ZenIsPurchaseRow_` without the flag.

So the rule differs by entry point: **a purchase entered through the sheet form
always debits the ZenMarket balance; the same purchase entered through the
dashboard does not.**

Two effects, the second worse than the first:

- the Zen balance is understated by that purchase converted at the JPY rate;
- the supplier column now reads `zenmarket_jp` for a non-ZenMarket lot, and that
  column is what marks a lot as ZenMarket everywhere else — so the error
  reproduces on every later edit of that lot.

Confirmed instance: `LOT-0181`, order `1158736408`, purchased on OLX on
2026-09-10.

## Migration expansion — accepted

Guards are server-side and complete: the source must be a recognised container,
the target a single pack SKU or `ACC-009`, `ACC-003` splits only into exactly
25 × `ACC-009`, `ACC-009` is unavailable as a target for anything else, and
source and target may not coincide. Cost is transferred in full with the last
FIFO entry absorbing the rounding remainder, so no value is lost.
`inventoryMigrationStockSnapshot_` accepts both `На складі` and `На складі UA`,
which side-steps the F2 status-vocabulary trap from the 2026-06-26 audit — keep
it inclusive.

One property the owner should know rather than fix: cost is split **equally**
across target units. Exact for a box of identical packs; an approximation for a
bundle or set whose contents differ in value, after which per-SKU margin is
wrong in both directions with nothing recording why. The owner reviewed this on
2026-09-10 and deferred it until sets are actually split.

## Layout — owner ruling

P&L and Cash Flow now sit side by side, which reverses the deliberate stacking
in the R2 handoff (two different kinds of money next to each other invite the
reader to add them). The owner ratified the compact layout on 2026-09-10. No
change required; recorded so it is not re-raised as a regression.

## Verified correct — do not "fix"

The ZenMarket top-up form takes **both** amounts as entered facts. `submitZenTopup`
requires `amount_jpy` and `amount_uah` to be present and positive and performs no
conversion; the backend requires both as well. The 3.2 rate is not involved. The
owner asked on 2026-09-10 that neither amount be derived from the other — that is
already the behaviour.

Where the 3.2 rate genuinely is an estimate: the `≈ ₴… за курсом CRM` line under
the balance KPI, and the conversion of a ZenMarket purchase's UAH cost into a JPY
balance charge. The second is the designed source of balance drift, and the
balance-correction action is its designed absorber.
