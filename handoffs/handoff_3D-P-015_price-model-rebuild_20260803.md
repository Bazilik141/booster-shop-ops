# Codex Handoff — 3D-P-015: rebuild the price model around фактична РРЦ

Date: 2026-08-03 · **Revised 2026-08-08 (rev 2)** | Parent: 3D-P-000 · related: 3D-P-001,
3D-P-003, 3D-P-008, 3D-P-010, 3D-P-013, **3D-P-019**
Codex config: model=Sol · effort=xhigh
Sequenced **after** `3D-P-014` (owner decision 2026-08-03: failure visibility
first). **`3D-P-014` and `3D-P-021` both closed 2026-08-08 — this task is unblocked.**

---

## ⚠ REVISION 2026-08-08 (rev 2) — read this before anything below

Five things changed after rev 1 was written on 2026-08-03. Where this block and the rev-1 body
disagree, **this block wins**.

### R1. The `O`/`P` placement question is closed — do not re-open it

Rev 1 §Scope item 1 asks the executor to *propose* whether the new business columns go before or
after the technical `O`/`P` block. **The owner decided on 2026-08-07 (decision D1): append AFTER
`O`/`P`.** The three new `Номенклатура` columns become:

| Col | Header |
|---|---|
| `Q` | `РРЦ фактична, грн` |
| `R` | `Ціна під викуп, грн` (Track 2) |
| `S` | `Посилання на модель` |

No shift of `O`/`P`. No migration step. **No change to `API_3DP.nomenclatureStatusColumn` (`'O'`)
or `nomenclatureHistoryColumn` (`'P'`).** The owner accepted the cosmetic cost of business columns
sitting after technical ones in exchange for not touching the deployed archive/history write paths.

Recorded in: `diagnostics/3D-P_gap-register-and-work-plan_20260807.md` §5.1 D1; Notion `3D-P-015`
→ `Owner Decision`; `ROADMAP_SOP.md` §3D-P.

The **only** whitelist change is additive: extend
`OWNER_MANUAL_COLUMNS_3DP['Номенклатура']` from
`['A','B','C','D','E','F','G','H','I','J','L','M','N']` to include `'Q','R','S'`.
`SERHIY_MANUAL_COLUMNS_3DP['Номенклатура']` (`['G','H','I','J','L','M','N']`) is **not** extended —
Serhiy gains no pricing write access. Verified live in `3d-print/apps-script-3dp-api/Code.gs`
lines 78 and 87 on 2026-08-08.

### R2. Scope grew — the fixture *schema* half of `3D-P-019` ships inside this task

Owner decision F8, 2026-08-08. Full reasoning: `plans/3D-P-019_fixture-payer-model_20260808.md`.
Reason for folding it in: both tasks rewrite the same columns and the same sale-row write path.
**One migration instead of two.**

**The defect being fixed.** `Номенклатура!K` ("Собівартість Сергія (виробнича), грн") is a formula
that already folds the fixture price into Serhiy's production cost with **no payer dimension**.
Verified in the live-matching source, `Code.gs` line 1667 inside
`setupNomenclatureFinalCostSchema3dp_()`:

```
K = H/I*J  +  G*Налаштування!B2*Налаштування!B3  +  G*Налаштування!B4  +  N
    material          electricity                    amortization       fixture
```

Under Model B (reimburse Serhiy's cost, then split the remainder 50/50), a fixture the **owner**
bought is therefore booked as **Serhiy's** cost, reimbursed to him, and subtracted before the
split — so both the reimbursement and the split base are wrong. The owner has already bought
hangers for keychains. This is an active financial defect, dormant only because there are still
**0 real Track-1 sales**.

**What ships here:**

1. Remove the unconditional `+ N` from the `K` formula in
   `setupNomenclatureFinalCostSchema3dp_()` (line ~1663–1673) **and** from every existing
   `Номенклатура!K2:K<last>` cell the loop normalizes.
2. `Номенклатура!N` (`Фурнітура (ланцюжок/карабін), грн/шт`) **stays** as a
   reference/default price only. It is no longer a cost input. Update its header or the `Легенда`
   note so this is not re-fused later by someone reading the old meaning.
3. Add frozen fixture fields to the sale row alongside the frozen РРЦ — see R3.

**What does NOT ship here** (stays in `3D-P-019`): `Розхідники` lots with a payer column, the
owner's multi-line fixture entry in the order and write-off forms, Serhiy's purchase entry in his
server, and the owner-confirmed import step.

**`Фурнітура_довідник` being empty does not block this task.** That data gates the *operational*
half only. Do not stall on it.

### R3. `Продажі` frozen columns go after `T` → `U`, `V`, `W` (owner, 2026-08-08)

Live layout confirmed `A–T`, last column `T = CRM row number` (technical, `3D-P-010`;
`TECHNICAL_APPEND_COLUMNS_3DP['Продажі'] = ['T']`, `Code.gs` line 72). Same reasoning as D1:
appending after the technical column is the only option that does not shift a deployed write path.

| Col | Header | Written by |
|---|---|---|
| `U` | `РРЦ на момент продажу, грн` | `3D-P-010` hook, **numeric literal** |
| `V` | `Вартість фурнітури за од., грн (заморожена)` | `3D-P-010` hook, **numeric literal** |
| `W` | `Платник фурнітури` | `3D-P-010` hook, `власник` / `Сергій` |

`Продажі!F` (`Собівартість Сергія за од., грн`) also changes from a live formula to a frozen
numeric literal — it is currently in `FORMULA_COLUMNS_3DP['Продажі']`
(`['C','F','I','J','K','L','S']`, line 94), so that list must lose `'F'` and the new columns must
be registered as technical/hook-written, not as owner manual-input.

**Interim fixture rule until `3D-P-019` lands (owner decision, 2026-08-08):** the hook writes
`W = власник` by default and takes `V` from `Номенклатура!N`. The owner may change `W` to `Сергій`
manually on the sale row. Consequence, per §6 of the fixture plan: an owner-paid fixture is a
BoosterShop cost (`C_b`) that reduces profit before the split and is **not** reimbursed to Serhiy;
a Serhiy-paid fixture is reimbursed to him as a **separate accrual record**, never merged into the
print-cost figure. When `3D-P-019` builds the `Розхідники` payer rows, only the *source* of `V`/`W`
changes — the sale-row shape does not.

⚠ **Double-count check.** `Продажі!G` (`Витрати BoosterShop за од., грн`) is already fed with the
packaging cost by the `3D-P-010` hook. Whatever formula consumes the new owner-paid fixture cost
must not also be adding it through `G`. Prove this with a hand-computed example before shipping.

⚠ **The `Продажі` formulas `I`, `J`, `K`, `L`, `S` live in the spreadsheet, not in the repository.**
They are not in `Code.gs` and this handoff does not state them. **Read them live before changing
anything that feeds them**, and record what you read in `diagnostics/`.

### R4. `3D-P-021` is done — the demo-row caution is dropped, the capture requirement is not

`ПРИКЛАД-001` rows were removed from all six tabs on 2026-08-08 and the removal was independently
confirmed. Rev 1's warning about rebuilding `Аналітика` over demo rows no longer applies.

The rollback requirement below still stands, narrowed: **before deleting the three scenario columns,
capture the live `Аналітика` scenario values into `diagnostics/`.** Re-read the tab live first —
this handoff does not assert what `FIG-CHARM-001`'s scenario row currently contains.

### R5. The dashboard surrogates are replaced as part of this task

`dashboard/booster-dashboard.html` currently fakes all three fields because the columns do not
exist (`diagnostics/3D-P_gap-register-and-work-plan_20260807.md` §3.2, work item C2):

| Shown as | Actually reads | Replace with |
|---|---|---|
| РРЦ | `threeDpLatest(sales, sku, 'Фактична ціна за од., грн (після знижки)')` — the **last post-discount transaction price** | `Номенклатура!Q` |
| Ціна під викуп | last `Ціна закупівлі за од., грн` row in `Маркетингові_плюшки` | `Номенклатура!R` |
| Посилання на модель | `localStorage` key `threeDpModelLinks` in the owner's browser | `Номенклатура!S` |
| Рекомендована РРЦ | literal placeholder | **stays a placeholder** — see rev 1 §Scope 4 |

Touch points verified 2026-08-08: `threeDpModelLinks` / `saveThreeDpModel()` (lines ~744–764),
`threeDpMetrics()` (line ~849), `threeDpInfoRecord()` (line ~889). `threeDpMargin()` (line ~886)
is **correct arithmetic over a wrong input** — do not rewrite it, just feed it the real РРЦ.

A SKU with no sales currently shows «немає РРЦ» permanently; after this change it must show the
value entered in `Q`.

### R6. Blocker state as of 2026-08-08

- `3D-P-014` — **done**. Sync journal live in the main CRM, dashboard panel working.
- `3D-P-010` — **done**, all three CRM sale-write paths hooked and proven live on 2026-08-08.
  The hook that must write the frozen values exists and works.
- `3D-P-021` — **done**.
- `3D-P-022`, `3D-P-023` — done.
- **Still blocking nothing, but still unapproved:** the recommended-РРЦ formula. It ships as an
  explicit `pending` placeholder. **Do not invent it.** There are 0 real Track-1 sales to compute
  it from.

### R7. Added acceptance criteria (rev 1's list still applies in full)

- [ ] `Номенклатура` new columns are exactly `Q`, `R`, `S`; `O`/`P` and the two
      `API_3DP.nomenclature*Column` constants are unchanged.
- [ ] `SERHIY_MANUAL_COLUMNS_3DP` gains nothing; a Serhiy-token write to `Q`, `R` or `S` is
      rejected with `COLUMN_NOT_ALLOWED`, proven by test.
- [ ] `Номенклатура!K` no longer contains `+ N`, in the setup function **and** in every existing
      data row. A hand-computed cost for one real SKU matches the new formula.
- [ ] `Номенклатура!N` is documented as a reference price, not a cost input.
- [ ] `Продажі` gains `U`, `V`, `W`; `T` keeps its position and header `CRM row number`.
- [ ] `Продажі!F`, `U`, `V` are numeric literals in rows created by the `3D-P-010` hook; changing
      `Номенклатура` afterwards provably does not move them.
- [ ] `W` defaults to `власник` and is owner-editable.
- [ ] The owner-paid fixture cost is counted exactly once — not in `G` and again in `V`.
- [ ] The dashboard reads `Q`/`R`/`S`; `threeDpModelLinks` localStorage is removed, with any
      existing browser values surfaced to the owner for one-time migration rather than silently
      dropped.
- [ ] `_Аудит_API` records the schema migration.

### R8. Added owner QA (rev 1's list still applies in full)

6. Enter a model link for one SKU from the dashboard, hard-refresh (Ctrl+F5), and confirm it is
   still there — it must now come from the workbook, not the browser.
7. Confirm `Номенклатура!K` for a SKU with a non-zero `N` **dropped** by exactly the value of `N`.
8. Create a test sale, confirm `U`/`V`/`W` are filled, then change `Номенклатура!N` and `Q` and
   confirm the sale row does not move.

**Dashboard is a local file — after any change to it, hard-refresh (Ctrl+F5) before judging the
result.** A cached older version caused two false alarms on 2026-08-08.

### R9. Executor and mirrors

Recommended executor: **Codex** — it owns this file family (Notion `Current Owner: Codex`,
`Primary Tool: Codex`). `ROADMAP_FLOW` in `dashboard/booster-dashboard.html` already carries the
2026-08-08 scope-growth note for `3D-P-015`; no additional mirror write is needed to start.
Before touching either Apps Script, check `crm/apps-script/SOURCE_STATE.md` (rule OPS-CODEMIRROR).
State as of 2026-08-08:

- **3D-P project** — mirror verified byte-identical to the owner's live export (CRLF aside),
  deployed **V7, 2026-08-03 20:55**. Safe to plan against.
- **Main CRM project** — the mirror carries **local pending changes** (`3D-P-010` WP4 and
  `3D-P-023`) and has **not been re-exported since the post-V92 deploy**. `SOURCE_STATE.md` states
  plainly that this "is not deployment proof". This task changes the CRM-side hook that writes
  `U`/`V`/`W`, so **ask the owner for a fresh CRM `Code.gs` export and the current Web App version
  before patching the hook.** Do not assume the local mirror equals live.

---

## Why

Confirmed by direct live read (see
`diagnostics/3D-P_live-schema-audit_20260803.md`): the 3D-P workbook has **no
canonical product price**. `Номенклатура` has no РРЦ column. `Продажі!E` is the
per-transaction price after discount, not a product price. The only price
concept anywhere is the three speculative scenario columns in `Аналітика`
(«Ціна Консервативна/Середня/Оптимістична»), and every derived financial figure
descends from them — three margin columns, «Нараховано Сергію (Середня)»,
«Прибуток Сергію/год друку (Середня)».

Owner rejected this model outright on 2026-08-03: price scenarios are removed,
and all finance derives from a single **фактична РРЦ** per SKU, with a
**рекомендована (динамічна) РРЦ** shown alongside as advice only.

## Owner decisions (2026-08-03, confirmed)

1. **One фактична РРЦ per SKU**, plus a separate **ціна під викуп** for Track 2
   (marketing freebie — direct purchase from Serhiy, no 50/50). Price does not
   vary by channel.
2. **Аналітика is rebuilt, not deleted.** The three scenario columns and
   everything computed from them are removed and replaced by фактична РРЦ +
   рекомендована динамічна + margin derived from фактична. The market-reference
   research block is retained as input to the recommendation.
3. **Cost and price are frozen into the sale row at creation time**, as numeric
   values — a later change to filament price or РРЦ must not rewrite the
   economics of past sales or amounts already accrued to Serhiy.

## Scope

**1. New durable columns in `Номенклатура`.** Confirm the live layout against
the schema audit first; today the tab ends at `P` (`API_історія_змін`). Add, as
owner-only whitelisted manual-input columns:

- `РРЦ фактична, грн`
- `Ціна під викуп, грн` (Track 2)
- `Посилання на модель`

These three are exactly the fields `3D-P-008` Addendum #3 was written for and
`3D-P-013` had to ship as read-only placeholders. **This task supersedes
Addendum #3** — implement them here, once, rather than twice. Update Addendum #3
to point at this handoff instead of duplicating the work.

Do not append them blindly after `P`: `O`/`P` are technical Addendum #2 columns.
Decide and document whether the new business columns go before the technical
block (requiring a shift of `O`/`P` and an update to
`API_3DP.nomenclatureStatusColumn`/`nomenclatureHistoryColumn` plus the
whitelists) or after it. **A shift touches deployed write paths — if chosen, it
needs its own migration step, verified against the live sheet, with the audit
log preserved.** Propose, do not decide unilaterally.

**2. Freeze cost and price into `Продажі` at row creation.** `Продажі!F`
(Собівартість Сергія за од.) and a new `РРЦ на момент продажу, грн` column must
be written as **numeric literals** by the `3D-P-010` hook when it creates the
sale row, not left as live formulas. Existing formula-driven behaviour for rows
already in the sheet must not be retroactively rewritten — migrate deliberately
or leave historical rows as-is and document which.

**3. Rebuild the `Аналітика` calculator block.** Remove
`Ціна Консервативна/Середня/Оптимістична`, the three `Маржа BoosterShop *`
columns, `Нараховано Сергію (Середня)` and `Прибуток Сергію/год друку
(Середня)`. Replace with: `РРЦ фактична` (pulled from Номенклатура),
`РРЦ рекомендована` (computed, see below), `Маржа BoosterShop, грн` and
`Маржа, %` derived from фактична РРЦ, and `Прибуток Сергію/год друку` derived
from фактична РРЦ. Keep the market-reference research block below it untouched.

**4. Рекомендована РРЦ — formula is NOT yet approved.** `3D-P-013`'s handoff
carries a proposed mechanism (margin-class position weighted 75%, sales-interest
score 25%, with 100% margin-class weight until ≥5 real Track-1 sales exist) that
the owner has never confirmed. Until he does, render it as an explicit
`pending` placeholder exactly as `3D-P-013` already does. **Do not invent a
formula.** Ship everything else without it.

**5. Update every consumer.** `3d-print/apps-script-3dp-api/Code.gs`
(whitelists, read actions, overview aggregation), `dashboard/booster-dashboard.html`
(Вироби zone editable fields, Інформація zone margin grid and analytics tiles),
`3d-print/serhiy-local-server` (must not gain write access to price — Serhiy
sees cost inputs, not pricing), and `3D-P-010`'s sale-creation values map.

## What NOT to touch

- The 50/50 split formula itself, the two-track model, or the «ЗБИТКОВИЙ —
  рішення власника» rule. Only the price *input* changes.
- `_Аудит_API`, `_Коригування_наявності`, `_Чернетки_партій` semantics.
- The market-reference research data.
- Main CRM pricing, storefront prices, Merchant feed, Product schema. This task
  changes internal accounting only; pushing РРЦ to the storefront is a separate,
  unscoped decision.

## Acceptance criteria

- [ ] Live `Номенклатура` layout re-confirmed before any structural change.
- [ ] `РРЦ фактична`, `Ціна під викуп`, `Посилання на модель` exist as durable,
      owner-only writable columns; Serhiy token rejected for all three.
- [ ] Sale rows created by the `3D-P-010` hook carry frozen numeric cost and RRP;
      changing `Номенклатура` afterwards provably does not alter them.
- [ ] All three scenario price columns and their four derived columns are gone
      from `Аналітика`; nothing else in the workbook still references them.
- [ ] Margin and Serhiy-accrual figures reconcile against a hand-computed
      example for one real SKU.
- [ ] Рекомендована РРЦ remains a visible `pending` placeholder — no invented
      formula shipped.
- [ ] Dashboard and Serhiy's server both still work against the new schema; the
      Serhiy role gains no pricing write access.
- [ ] `ROADMAP_FLOW` entry for `3D-P-015` added; `3D-P-008` Addendum #3 updated
      to defer to this task.

## Owner QA

1. Set `РРЦ фактична` for one real SKU from the dashboard; confirm it persists
   and appears in Аналітика and the Інформація margin grid.
2. Confirm `Ціна під викуп` is independent of it and does not enter the 50/50
   calculation.
3. Create a test sale, then change the SKU's cost inputs, and confirm the
   completed sale's numbers do **not** move.
4. Confirm Serhiy's local server shows no price-editing controls.
5. Confirm the recommended-RRP cell still reads as pending, not a number.

## Rollback

Column additions are additive and reversible. The `Аналітика` rebuild destroys
the scenario columns — **capture the current values (including
`FIG-CHARM-001`'s 50/62/75 scenario row) into `diagnostics/` before removing
them**, so the removal is recoverable and auditable. Any `O`/`P` shift, if
chosen, needs its own documented rollback.

## Risks

- Touches the deployed write whitelist and the deployed CRM sync simultaneously.
  Prefer two sequenced patches (schema + API first, then consumers) over one.
- The `O`/`P` technical-column position question is the single highest-risk
  decision here — it can silently break archive/restore and stock adjustment,
  both of which are live and already QA'd.
- The recommended-RRP formula is the obvious scope-creep magnet. It is out of
  scope until the owner confirms it in writing.

## Recommended status

`Not started`, blocked by `3D-P-014`.
