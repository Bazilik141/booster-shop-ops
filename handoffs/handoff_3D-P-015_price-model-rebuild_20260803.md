# Codex Handoff — 3D-P-015: rebuild the price model around фактична РРЦ

Date: 2026-08-03 | Parent: 3D-P-000 · related: 3D-P-001, 3D-P-003, 3D-P-008,
3D-P-010, 3D-P-013
Codex config: model=Sol · effort=xhigh
Sequenced **after** `3D-P-014` (owner decision 2026-08-03: failure visibility
first).

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
