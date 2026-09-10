# Codex Handoff — CRM-012: ZenMarket integrity, revenue recognition, 3D names

Date: 2026-09-10 | Parent: CRM-011
Executor: Codex — same executor as CRM-011, never swap mid-round (AGENTS.md).
model=Sol · effort=xhigh — CRM is a risky zone, this round changes money
attribution, repairs live data and touches the revenue-recognition rule.

Findings and evidence: `diagnostics/CRM-011_round2-review_20260910.md`. Read it
first; this file is the work order and does not repeat the analysis.

CRM-011 itself is not reopened. Its Pass B is verified landed and its remaining
gate is owner QA plus publication of anything not yet live.

## Preconditions

- **Mirror gate is closed at V171.** The owner export
  `Версія 171, 10 вер. 2026 р., 1828` is byte-identical to
  `crm/apps-script/Code.gs` apart from one regex line mangled by the CSV export
  format itself. Record V171 in `SOURCE_STATE.md` as byte-verified, with the
  export filename and normalised hash, and close the V168-reported /
  V164-verified gap. Do this in WP0 before anything else, so the rest of the
  round starts from a proven baseline.
- **The ZenMarket layer and the migration expansion are already live** in V171,
  despite both reports saying no publication was performed. Every fix below
  changes live behaviour and needs its own publication.
- **Owner takes a verified copy of the spreadsheet before WP2 is applied.**
  WP2 repairs live money data.
- `OPS-CRMINTEGRITY`: run the integrity check before and after any structural
  or repair step and record both bounded outputs.
- Never write a literal over a formula column. `Продажі` K and N–V and `Витрати`
  L–M are sheet formulas.

## WP0 — Close the mirror gate

Update `crm/apps-script/SOURCE_STATE.md`: V171 byte-verified against the owner
export, method recorded (CSV parsed as CSV, CRLF normalised), and a one-line
note that the `.csv` export is lossy for lines containing double quotes so
future verifications should request `.gs`/`.txt`.

No code change. This is documentation of a proven fact, and it unblocks the rest.

## WP1 — ZenMarket data-quality counter must count

`historical_topup_uah_missing` is a hardcoded `1` in three places and
`zenmarket_historical_topup_uah_missing` is a hardcoded `1` in the
`finance_report` `data_quality` block.

Replace all four with a value computed at call time: the number of top-up rows
whose UAH amount is empty. Read it from the same ledger the balance is read
from; do not introduce a second source of truth and do not cache it separately
from the report that carries it.

Acceptance: with the ¥70,000 top-up still missing its UAH amount the value is 1;
after WP2 records ₴22,000 it becomes 0 without any code change; if a second
top-up is entered without a UAH amount it becomes 2.

## WP2 — Record the missing UAH amount and surface the gap

**Data.** `ZEN-HIST-008` (2026-09-10 05:28:58 ZenMarket clock, `+¥70,000`) has
no UAH amount. The owner supplies **₴22,000**. Record it against that top-up
through the existing top-up/ledger path — not by hand-editing a seeded row, and
not by deriving it from the rate. Both amounts are facts: ¥70,000 and ₴22,000.

For context only, do not encode: 70,000 ÷ 22,000 = 3.18, which confirms the
existing `Курси` JPY rate of 3.2 to within 0.6%. **The rate does not change.**

**UI.** The dashboard currently contains zero references to `uah_missing`. Cash
Flow must show the gap while it exists — a single line naming the count and
stating that those top-ups are excluded from cash-out, rendered only when the
WP1 count is above zero. Missing is not zero; this is the one place where that
rule was not applied.

Acceptance: before the fix, Cash Flow states that one top-up is excluded; after
₴22,000 is recorded, the line disappears on its own and cash-out rises by that
amount.

## WP3 — Non-ZenMarket purchases must not touch the ZenMarket balance

Three parts, in this order.

**3.1 Code — stop the mislabelling.** `addPurchase()` writes `'zenmarket_jp'`
into the supplier column unconditionally (`purchases.getRange(row, 20)`). Either
add a supplier field to that form, or stop writing the column when the supplier
is not ZenMarket. Do not guess a supplier from the SKU or the order reference.

**3.2 Code — remove the override.** Delete `force_zenmarket: true` from all
three call sites — the `crm011ZenSyncPurchaseLots_` calls inside `addPurchase()`
and `updatePurchase()`, and the `crm011ZenEnsurePurchaseBaselines_` call beside
the second. The supplier column is the only authority for whether a lot is a
ZenMarket lot. Leave the `forceZenmarket` parameter of
`crm011ZenIsPurchaseRow_` in place only if some caller still legitimately needs
it; if none does, remove the parameter so it cannot come back.

3.1 without 3.2 is insufficient: the flag makes the sync fire regardless of a
correct supplier column.

**3.3 Data — repair the known instance.** `LOT-0181`, order `1158736408`,
purchased on OLX on 2026-09-10, entered through the sheet form.

- correct its supplier column;
- remove it from the `ZenMarket_Лоти` index so no future delta references it;
- post a compensating movement through the existing balance-correction action
  for the JPY amount that was wrongly charged, with a note naming the lot.

Do not delete rows from `ZenMarket_Рахунок`. It is append-only and that property
is correct — a correction is a new entry, never an erasure.

Before repairing, scan for other lots that entered the same way and report them
to the owner. Do not repair anything beyond `LOT-0181` without owner
confirmation of the list.

Acceptance: the JPY amount removed by the compensation equals the amount the
sync originally charged for that lot; the balance after repair matches the
owner's real ZenMarket balance; a new non-ZenMarket purchase entered through the
sheet form leaves the Zen balance unchanged; a new ZenMarket purchase still
charges it.

## WP4 — Revenue recognition month for preorders

**Current behaviour, verified.** While an order is a preorder it is excluded
from `_getCrmSalesRows()` entirely, so it belongs to no month. When it is
completed it enters, bucketed by `row[2]` = `Дата продажу`, the date the order
was created. A preorder placed in July and completed in September therefore
lands in **July** — a closed month changes retroactively, months after the fact.

**Owner decision 2026-09-10:** an order that was ever a preorder is recognised
in the month it became complete, that is when both `Отримано` and `Оплачено`
are in place.

**Scope boundary — do not change the basis for everything.** Regular orders keep
`Дата продажу`: for them sale, payment and shipment fall within days, the month
is the same either way, and repricing every historical month would create the
same empty-history trap that daily asset snapshots were rejected for.

**Implementation.**

1. Add `Дата отримання` to `Продажі`, appended after the last used column, never
   inserted. Write it once, on the transition of `Статус замовлення` to
   `Отримано`, in the same way `Дата оплати` is written. Never overwrite an
   existing value. Widen every fixed-width read of `Продажі` accordingly and
   list them in the diagnostic.
2. Implement one recognition-date function, used by every consumer:
   - the order was never a preorder → `Дата продажу`;
   - the order was a preorder → the later of `Дата оплати` and `Дата отримання`;
   - preorder with those dates absent (history) → `Дата фіксації собівартості`,
     column 32, which is already written whenever a preorder's cost is finalised
     into real FIFO and is currently read by nothing;
   - none of the above available → `Дата продажу`, and the order is counted in
     `data_quality` as recognised on a fallback date.
3. Route `apiAggregateSalesRows_`, `apiMonthlySummary_` and the Finance period
   bounds through that one function. One rule in one place: the Overview tile,
   the six-month charts and Finance must not be able to disagree about a month.

**Known limit of the historical proxy, state it in the diagnostic:**
`Дата фіксації собівартості` is overwritten by any later cost recalculation
(mystery-box recomputation, migration-driven refresh), so for a preorder that
was recalculated after completion it drifts forward. Good enough for history,
not a substitute for the real stamp going forward.

Acceptance: a preorder created in month A and completed in month B counts in B,
in both the order count and the money, on the Overview tile, in the six-month
charts and in Finance; a regular order's month is unchanged; historical months
computed before and after the change are reported side by side so the owner sees
exactly what moved.

## WP5 — 3D product names

Two symptoms, one cause: nothing keeps the name in the main CRM aligned with the
3D-P catalogue. `apiSync3dpCatalogRrp_` synchronises RRP only.

- In `Товари` / `Майстер_Товарів` a number of `BR-*` SKUs carry the placeholder
  name `3D-друк`, which is what the Products tab renders.
- In the 3D-P catalogue a number of entries still carry draft working names
  rather than canonical ones.

**Do not invent or auto-pick names.** Canonical naming is governed by
`plans/3D-P_sku-naming-convention_20260807.md` and the `bs-3dp-card-qa` rules,
and live product cards are evidence.

1. **Report first.** Produce a divergence table for every 3D SKU: SKU, name in
   `Товари`/`Майстер_Товарів`, name in the 3D-P catalogue, name on the live site
   where one exists, and a flag for placeholder names (`3D-друк` and similar) and
   for names that fail the convention. No writes in this step.
2. **Owner approves** the canonical name per SKU from that table.
3. **Bounded write** of the approved names, and a name synchronisation on the
   same path that already synchronises RRP, so the two catalogues cannot diverge
   again. Renames must go through the existing rename/collision handling, not a
   blind overwrite.

Acceptance: the divergence table is complete and matches the live sheets; after
the approved write no 3D SKU renders a placeholder name in the Products tab; the
3D tab and the Products tab show the same name for the same SKU; a subsequent
RRP sync carries the name too.

## What NOT to touch

- **The ZenMarket top-up form.** It already takes both amounts as entered facts
  with no conversion, which is exactly what the owner asked for. Verified
  2026-09-10.
- **The `Курси` JPY rate.** 3.2 is confirmed by the owner's real top-up.
- **The side-by-side P&L / Cash Flow layout.** Owner-ratified.
- **Equal cost split in `container_to_units`.** Owner deferred it until sets are
  actually split; not in scope, not a defect to fix here.
- **`apiAddSale_` and `getDirectOrderExpense_` duplicates.** Pre-existing,
  separate task.
- **The recognition basis for regular orders.** WP4 is scoped to preorders.
- Secrets: `.env.review`, `scripts/.env`, `client_secret.json`. Apps Script
  secrets live in Script Properties and never enter a mirror.

## Deliverables

One diagnostic covering the round: what changed, both integrity outputs, the
before/after month comparison for WP4, the JPY compensation figure and resulting
balance for WP3.3, the 3D divergence table for WP5.1, assumptions, and known
limitations stated plainly rather than omitted.

Publication remains owner-gated: spreadsheet copy first, then paste and publish,
then post-publication integrity plus the owner QA above.
