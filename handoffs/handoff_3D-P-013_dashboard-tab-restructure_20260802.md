# Codex Handoff — 3D-P-013: «3D-друк» dashboard tab restructure (multi-zone UI)

Date: 2026-08-02 | Parent: 3D-P-006 · related: 3D-P-008, 3D-P-010
Codex config: model=Sol · effort=xhigh

**Renumbering note:** this task was originally drafted as `3D-P-011` earlier the same day. That number was
already registered (a different session, 2026-08-01) for the PDP variant-selector task — see
`ROADMAP_SOP.md`'s 3D-P ID table. Renumbered to `3D-P-013` to avoid an ID collision; no other content changed.
The old file `handoffs/handoff_3D-P-011_dashboard-tab-restructure_20260802.md` is a stub pointing here.

## Context

`3D-P-006` shipped a first, working version of the "3D-друк" dashboard section (`threeDpCards`,
`threeDpCalculator`, `threeDpSkuTable`, `threeDpSalesTable`, `threeDpPlyushkyTable`, `threeDpPayoutsTable` —
all currently rendered as one flat page). Owner used it and wants a UX restructure: split into distinct zones
by function, add a proper SKU CRUD surface, and add a recommended-RRP pricing engine. This task supersedes the
single-page layout from `3D-P-006` — it does not touch `3D-P-008`'s API contract (same read/write actions,
just reorganized consumption in the UI) or `3D-P-007` (Serhiy's server is unaffected, no dashboard-specific
coupling, same as before).

**One sub-feature in this handoff (recommended-RRP generation formula, §Zone C) is marked TBD below — do not
build it until the owner confirms the exact mechanism.**

**Phase split, 2026-08-02 (Codex review finding, correct — this handoff is NOT pure UI as originally framed):**
four requirements below need new API/schema surface that `3D-P-008` does not currently expose: editable
`Налаштування`, raw batch-draft persistence, `Номенклатура` archive/restore, and traceable наявність
adjustment. These are scoped in `3D-P-008`'s "Addendum #2" section, a separate, narrow follow-up to the API.
Each item below is tagged **[Phase A]** (buildable now, against the existing deployed API) or **[Phase B]**
(blocked on `3D-P-008` Addendum #2). Build all Phase A work now; do not start Phase B items until that
addendum is Done.

## Scope — three zones inside the existing "3D-друк" nav section

Keep it as one nav item / one `page-3dprint` container (per `3D-P-006`'s existing pattern), with an internal
sub-tab switcher for the three zones below — do not add three separate top-level sidebar nav items, this stays
inside the single "🖨️ 3D-друк" entry.

### Zone A — Калькулятор (revise existing `threeDpCalculator`)

- **[Phase A]** Remove the current read-only constants text line ("Константи read-only з Налаштування: 0.17
  кВт · 4.32 грн/кВт·год · 12 грн/год. Де редагувати...").
- **[Phase A]** Add a small "⚙" icon in this zone's header. In Phase A it holds only the relocated "Налаштувати
  3D-P API" button (currently inline next to the "Калькулятор партії" section header — the
  `configure3dpAccess()` button) and a **read-only** display of the three constants (already readable via the
  existing settings read action). **[Phase B]** once `3D-P-008` Addendum #2 ships, the same panel gains actual
  edit capability for the three constants (owner-only write).
- **[Phase B]** Persist raw batch-draft inputs per SKU (Кількість у партії, шт; Сумарна вага партії, г;
  Сумарний час партії, год; Вага котушки, г; Ціна котушки, грн) so reselecting a SKU repopulates them. Until
  this ships, the calculator still computes and saves correctly (per-unit values to `Номенклатура` as today)
  — it just won't remember the raw batch inputs across a reselect. Acceptable Phase A gap, not a blocker.
  "Посилання на модель (чернетка)" and "РРЦ" are **not** yet writable at all — see the Zone B correction below
  (`3D-P-008` Addendum #3) — do not build draft persistence for fields that have no write path yet.
- **[Phase A]** Remove the fixture selection/calculation section entirely: no "Фурнітура" input, no "Разом із
  фурнітурою" output line. Fixture cost is handled entirely outside the calculator (post-print assignment, and
  per `3D-P-010`'s 2026-08-02 addendum, pulled from order-pack-time entry in the main CRM — not calculated
  here).
- **[Phase A]** Remove the 3-tier RRP scenario block (Консервативна/Середня/Оптимістична) and its "(пакування
  не входить: 3D-P-010)" note entirely from the calculator output. RRP display moves to Zone C/B (see below) —
  the calculator's job is only "Собівартість Сергія", nothing about pricing.

### Zone B — Вироби (new: SKU CRUD)

Add/edit form per SKU, replacing any ad-hoc SKU creation flow:

- **[Phase A]** Fields: SKU (immutable after creation), назва, категорія/тип (брелок/фігурка/аксесуар, per the
  SKU-prefix convention in `plans/3D-P-002_catalog-placement-admin-guide_20260731.md` §8), примітки. Статус and
  наявність adjustment are Phase B, see below.
- **[Phase A]** Вага виробу / час друку / собівартість Сергія: **read-only display here**, sourced from the
  calculator's last save. Do not make these independently editable on this screen — one source of truth, avoid
  the two screens drifting.
- **[Phase C — corrected 2026-08-02]** Посилання на модель, РРЦ (фактична), and Ціна під викуп (Track-2 buyout
  price, with the "?" tooltip: "Це ціна, яку заплатить Booster Shop за придбання 1 шт виробу") are **not**
  Phase A after all — Claude's original claim that these "already fit the existing whitelist" was wrong. Codex
  correctly found no durable `Номенклатура` column/action exists for any of the three and shipped them as
  read-only/placeholder rather than inventing a destination. They become editable once `3D-P-008` Addendum #3
  ships (owner-only write path). Both РРЦ and Ціна під викуп must surface in Zone C's table regardless of
  phase — read-only there is fine until Addendum #3 lands.
- **[Phase B]** Статус (**only** Активний / Архів — no Draft/Чернетка state). Until this ships, do not fake a
  status field client-side — leave it out of the Phase A form rather than build something that has to be
  reworked.
- **[Phase B]** Наявність adjustment: an explicit action to record a correction/write-off (not just overwrite
  the number silently) — requires the new `3dp_adjust_stock`-style action from `3D-P-008` Addendum #2. Until
  it ships, наявність is read-only in this zone (edits happen the existing way, outside this new form).
- **[Phase B]** Delete → **Archive** (reversible status change), not a hard delete. Mirror `Друк-лог`'s
  existing archive/restore mechanism from `3D-P-008` (same UX: archived SKUs stop appearing in active
  views/availability calculations but remain restorable, same audit trail via `_Аудит_API`) — needs Addendum
  #2's `Номенклатура` archive/restore action. Until it ships, do not add a delete/archive button that has
  nowhere to write.

### Zone C — Інформація (read-only table + logs + analytics)

**[Phase A]** — this whole zone is display/reorganization over data the API already exposes; no Addendum #2
dependency. The two exceptions are individually tagged below (recommended RRP stays TBD regardless of phase;
the 3D-order-% tile depends on `3D-P-010`, not on Addendum #2).

**Main table** — all SKUs, all characteristics, one row per SKU:

- Sortable by any column, filterable/groupable by parameters (e.g. filter by категорія, статус, cost bracket),
  columns individually hideable/showable (persist the owner's column visibility choice — a dashboard-local
  preference is fine, does not need to round-trip through the 3D-P API).
- Must include at minimum: SKU, назва, статус, наявність, собівартість Сергія, час виготовлення (per unit),
  РРЦ (фактична), **РРЦ (рекомендована)** — see TBD below, **Ціна викупу Booster Shop** (renamed from the old
  "Маркетингові плюшки" tile — same underlying `Ціна під викуп` field from Zone B).
- Margin classification/coloring for the РРЦ (фактична) column, per the grid in §Margin grid below — colors
  should reuse the dashboard's existing semantic palette (`--green`/`--yellow`/`--orange`/`--red`, defined in
  `dashboard/booster-dashboard.html`'s `:root`) rather than introducing new hex values. There are 5 tiers and
  4 semantic colors — Codex's call on how to differentiate the top two tiers (e.g. `--green` at two opacity
  levels), stay within the existing palette.
- Search box by назва/SKU, separate from the column filters (a simple text filter, not a replacement for
  column filtering).

**"Потребує уваги" block** — SKUs missing РРЦ, missing посилання на модель, or with naявність (Track 1)
approaching zero.

**Consolidated tiles** (this + previous month, mirror the whole-shop dashboard's existing tile patterns —
reuse the rendering approach already used for `topSkusList` (~`dashboard/booster-dashboard.html:1210`) and the
"Виручка — 6 місяців" section (~line 389) rather than inventing a new tile style):

- Собівартість складу 3D-виробів (поточна наявність × поточна собівартість per SKU, summed).
- Потенційний прибуток складу (поточна наявність × (РРЦ факт. − собівартість), summed).
- % замовлень із 3D-товарами відносно всіх замовлень на сайті — this needs the same "which orders contain a
  3D-P SKU" detection that `3D-P-010`'s Phase 0 investigation is already scoped to figure out (see that
  handoff). Do not build a second, separate detection mechanism — reuse whatever Phase 0 lands on.
- Топ-5 SKU за прибутком (30 днів).
- Виручка та Чистий прибуток (6 місяців) — same shape as the main dashboard's existing "Виручка — 6 місяців"
  section, scoped to 3D-P sales only.

**Moved into this zone** (previously separate tiles/tables in the flat layout):

- Продажі table (`threeDpSalesTable`, incl. discount columns).
- Виплати table (`threeDpPayoutsTable`).
- Маркетингові_плюшки **journal** (`threeDpPlyushkyTable`) — owner wants to keep the full log, placed at the
  bottom of this zone. Its price also surfaces as the "Ціна викупу Booster Shop" column in the main table
  above — the journal is the transaction history, the column is the current reference price, both stay.

### Margin grid (owner-confirmed, 2026-08-02)

`Маржа = (РРЦ − Собівартість) ÷ РРЦ` (percentage of the sale price, not a markup-on-cost ratio). Example:
cost 10 грн, РРЦ 40 грн → margin 75%.

| Собівартість | Найбажаніша | Оптимальна | Прийнятна | Низька | Критична |
|---|---|---|---|---|---|
| до 10 грн | ≥90% | 75–90% | 60–75% | 40–60% | <40% |
| 10.01–25 грн | ≥80% | 70–80% | 55–70% | 40–55% | <40% |
| 25.01–60 грн | ≥80% | 65–80% | 50–65% | 35–50% | <35% |
| 60.01–99 грн | ≥80% | 65–80% | 50–65% | 30–50% | <30% |
| вище 99 грн | ≥80% | 70–80% | 55–70% | 40–55% | <40% |

This grid classifies/colors an **existing** РРЦ. It is not by itself a formula for generating a new suggested
number — see TBD below for that.

### TBD — recommended/dynamic RRP generation formula (do NOT build until owner confirms)

Owner wants a computed "рекомендована РРЦ" using: 75% weight = margin-class position (from the grid above),
25% weight = sales-interest over a lookback window (90 days, extending to 120 days once ≥5 real Track-1 sales
exist — Track-2/`Маркетингові_плюшки` rows are excluded, they already live in a separate tab so no extra
filtering logic is needed). Before ≥5 real sales exist, weight is 100% margin-class, 0% sales-interest.

The margin-grid table only *classifies* an existing price — turning it into a *generator* of a suggested РРЦ
number requires a target-margin-per-bracket definition and a way to blend in the sales-interest score. Claude
proposed one concrete mechanism to the owner on 2026-08-02 (target margin = position between the bottom of
"Прийнятна" and the top of "Найбажаніша" for that cost bracket, shifted by a sales-interest score computed as
`min(1, units_sold_in_window / N)` for an owner-set reference `N`) — **this is still pending owner
confirmation**. Do not implement the generation formula until that confirmation lands; everything else in this
handoff does not depend on it (the РРЦ факт./рекомендована columns can ship with "рекомендована" showing
"—"/pending until the formula is confirmed, then filled in via a follow-up patch).

## What NOT to touch

- `3D-P-008`'s Apps Script API — this task is a pure UI consumer/reorganizer, same rule as `3D-P-006`. If a
  needed read/write action doesn't exist, that's a gap to flag back to `3D-P-008`, not something to patch
  around here.
- `3D-P-007` (Serhiy's local server) — unaffected.
- `3D-P-010`'s packaging/fixture pull mechanism — this task only displays/consumes whatever lands in the
  Sheet, does not build the pull itself.
- Main CRM pages/tables in the same dashboard file — additive only, no shared-state regression.
- The recommended-RRP generation formula (see TBD above) — explicitly out of scope until confirmed.

## Acceptance criteria

**Phase A:**

- [ ] Three zones (Калькулятор / Вироби / Інформація) render inside the single "3D-друк" nav section, switch
      cleanly, no cross-zone state leakage.
- [ ] Calculator: constants text removed, "⚙" panel holds the relocated API-config button and a read-only
      constants display, fixture rows and RRP-scenario block removed. Batch calc still computes/saves per-unit
      values correctly (raw-draft persistence is Phase B, not tested here).
- [ ] Вироби (Phase A fields only): SKU/назва/категорія/примітки CRUD works. Посилання-на-модель/РРЦ/Ціна-під-
      викуп render read-only with the buyout-price tooltip still visible — confirm they are NOT editable yet
      (Phase C, blocked on Addendum #3) and no code silently writes a guessed column for them. No статус field,
      no archive button, наявність is read-only — confirm none of these Phase B affordances are half-built.
- [ ] Інформація: table sort/filter/column-hide all work; search box filters by назва/SKU; margin coloring on
      РРЦ факт. matches the grid table above exactly for at least 3 test cases across different cost brackets;
      alerts block correctly flags a SKU missing РРЦ and one missing a model link; Продажі/Виплати/Плюшки
      journal all render in this zone, not elsewhere.
- [ ] РРЦ (рекомендована) column exists and shows a clear pending/placeholder state, not a guessed number.
- [ ] `ROADMAP_FLOW` entry for `3D-P-013` added, noting Phase A done / Phase B and Phase C pending.

**Phase B (after `3D-P-008` Addendum #2 ships — Done, 2026-08-02):**

- [ ] "⚙" panel gains working constants edit (owner token only).
- [ ] Raw batch-draft values persist and repopulate on SKU reselect (test: enter values, save, switch to
      another SKU, switch back — original raw values return, still editable).
- [ ] Вироби gains статус (Активний/Архів), наявність adjustment with traceable reason, and Archive/restore —
      all confirmed via `_Аудит_API` or equivalent.

**Phase C (after `3D-P-008` Addendum #3 ships — not started):**

- [ ] Посилання на модель, РРЦ (фактична), and Ціна під викуп become editable, pre-filled with current value,
      save correctly through the new whitelisted columns/action.

## QA checklist (owner runs after deploy)

**Phase A:**
- [ ] Walk all three zones, confirm no main-CRM page regressed.
- [ ] Add one test SKU end-to-end via Вироби (Phase A fields), confirm it appears correctly in Інформація and
      is selectable in the Калькулятор dropdown.
- [ ] Confirm the margin color on a known SKU's РРЦ matches the grid table by hand-checking the math.

**Phase B:**
- [ ] Archive a test SKU, confirm it disappears from active views but is restorable.
- [ ] Adjust наявність with a reason, confirm the reason is visible afterward.
- [ ] Reselect a SKU in the calculator after a batch save, confirm raw inputs return.

## Rollback note

- Additive/reorganizing change to `dashboard/booster-dashboard.html` — `git revert` is low blast radius, same
  as `3D-P-006`.
- No changes to `3D-P-008`'s Apps Script or Sheet schema from this task directly.

## Risks

- CRM/ops-tooling risky zone per `AGENTS.md` (dashboard file) — standard rollback + QA checklist applies, no
  elevated risk beyond `3D-P-006`'s (pure UI reorganization, same write paths).
- Scope-creep risk: keep the recommended-RRP generator out until confirmed — do not guess target margins.

## Recommended status after execution

`In progress` — Phase A can ship and pass its own QA independently → `Done` only once Phase B (blocked on
`3D-P-008` Addendum #2) also passes QA. The RRP-generation sub-item stays open even after both phases are
Done, tracked as a follow-up patch once the formula is confirmed.
