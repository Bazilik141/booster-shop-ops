# Codex Handoff — 3D-P-006: Owner dashboard tab («3D-друк» section in booster-dashboard.html) + calculator

Date: 2026-07-31 (rewritten — see §"Revision history") | Parent: 3D-P-000 · related: 3D-P-001, 3D-P-005, 3D-P-007, 3D-P-008
Codex config: model=Sol · effort=xhigh

## Revision history

This handoff was first written before `3D-P-008` (the shared Apps Script API) existed, and originally had this
task talk to the Google Sheet directly. Owner decided 2026-07-31 to build the API first. **This version
supersedes the original — do not build against the Sheet directly, build against the `3D-P-008` API.** If
`3D-P-008` is not yet Done in Notion, stop and confirm with the owner before starting this task.

**2026-08-02: `3D-P-008` also has a schema-correction addendum (batch-based calculator, global settings
constants, defect field, fixture reference list — see that handoff's "Addendum" section at the top). That
addendum must ship before this task's calculator UI is built — check its status too, not just the base
`3D-P-008` task.**

## Context

New business line (3D-printed TCG merch with the owner's friend Serhiy). Owner's own view = a new tab inside
the *existing* `dashboard/booster-dashboard.html` (no new infrastructure — decided 2026-07-28 in 3D-P-005,
reconfirmed 2026-07-31). Serhiy's view is a separate task, 3D-P-007, consuming the same API — the two can now
be built in parallel.

Full target-state vision is `plans/3D-P_handoff-chatgpt_v1_20260728.md` (24 sections — a whole separate app).
This task is still deliberately a subset of that vision's §10 (owner interface) — no `Product`/`Variant`/
`CostVersion`/`Settlement` entities, no full production kanban, no market-comparables browser.

**Calculator scope — FINAL, 2026-08-02 (supersedes the 2026-08-01 and 2026-07-31 versions below):**

Owner got Serhiy's final, simplified cost model. This is now locked; do not build against either older version
in this section.

Per-session inputs (Serhiy picks a SKU from a dropdown, enters quantity produced in that print session/batch;
weight and time are session totals, evenly divided by quantity to get per-unit values before storage/display):

- `вага_виробу` (product weight, per unit after batch division)
- `час_друку` (print time, per unit after batch division)
- `вага_котушки` (spool weight, г)
- `ціна_котушки` (spool price, грн)

Fixed global constants, set once as editable settings (never re-entered per calculation, never hardcoded in
formulas — see `3D-P-008`'s 2026-08-02 addendum for where these live):

- Printer power: `170 Вт` (`0.17 кВт`)
- Electricity price: `4.32 грн/кВт·год`
- Printer amortization: `12 грн/год`

Formula:

- `Матеріал = (вага_виробу ÷ вага_котушки) × ціна_котушки`
- `Електроенергія = 0.17 × час_друку(год) × 4.32`
- `Амортизація = 12 × час_друку(год)`
- `Собівартість Сергія = Матеріал + Електроенергія + Амортизація` (+ фурнітура once assigned — separate,
  post-print step, see below; праця excluded, fully absorbed by the 50/50 split)

Plastic type: **not tracked at all** — do not add any plastic-type field or dropdown.

Defect/waste: not part of the cost formula. Add a `Брак, шт` field on `Друк-лог`, editable post-production by
owner or Serhiy (with the existing edit-history mechanism), separate from the calculator.

Fixture/hardware (фурнітура): a dropdown reading from a small reference list (`Фурнітура_довідник`: name,
price/шт — new in `3D-P-008`'s addendum). Assigned as an independent, later step after printing — must not
gate the SKU's initial calculator entry or its RRP scenarios.

Model link (replaces "photo"): if this task's UI surfaces a proof-of-existence field for a SKU (mirroring
`3D-P-002`'s Enable gate), it is a **link to the model**, not a photo upload — confirm exact link target with
owner before wiring it up (ambiguous which "site"/URL exactly, see `plans/3D-P-002_catalog-placement-admin-guide_20260731.md`
§2/§7, updated 2026-08-02).

Packaging cost: **not this task's scope.** Auto-pulling packaging cost from main-CRM order data into
`Продажі!G` is a separate, bounded follow-up — see `handoffs/handoff_3D-P-010_crm-packaging-cost-pull_20260802.md`.
Do not build packaging logic here.

**⚠ Hard dependency:** this calculator must be built against `3D-P-008`'s 2026-08-02 schema-correction
addendum, not the already-deployed single-`O`-column schema. If that addendum is not yet Done, stop and
confirm with the owner before starting this task's calculator UI.

*(Earlier 2026-08-01 and 2026-07-31 versions of this section, both superseded, kept below only for history —
do not build against either.)*

<details>
<summary>Superseded 2026-08-01 text</summary>

Serhiy asked, via owner, to keep amortization and electricity as two separate cost lines, not one combined
rate. `Собівартість Сергія = матеріал×ціна/1000 + фурнітура + амортизація×час_друку + електроенергія×час_друку`
(+ праця×час, only if labor needed its own line — resolved 2026-08-02: no, folded into the 50/50 split).

</details>

<details>
<summary>Superseded 2026-07-31 text</summary>

"Add a combined amortization rate input that stands in for printer depreciation, electricity, and labor
together — owner explicitly chose not to track those three separately right now."

</details>

## Scope (what to change)

- `dashboard/booster-dashboard.html`:
  - New sidebar nav item: `<div class="nav-item" onclick="showPage('3dprint')">🖨️ 3D-друк</div>`, inserted
    after the existing `Роадмап`/`Облік` items.
  - New `<div id="page-3dprint" class="page">` section, following the exact structural pattern of
    `page-overview`/`page-stock` (header, cards, section blocks with loading spinners) — see
    `dashboard/booster-dashboard.html` ~line 316-420 for the pattern to mirror.
  - New JS: a **separate** API client (`API_3DP` constant = the `3D-P-008` webapp URL, **separate**
    `TOKEN_3DP_STORAGE_KEY` localStorage key, separate token prompt) — do not extend or reuse the existing
    main-CRM `API`/`TOKEN`/`call()` (~line 519-555). Two independent credential paths, always.
  - Render, using `3D-P-008`'s bounded read actions (`3dp_overview`, `3dp_skus`, `3dp_sales`, `3dp_plyushky`,
    `3dp_payouts`, `3dp_get_row`, `3dp_get_range`) — never a full-document read:
    - Overview cards (SKU count, наявно, нараховано Сергію поточний місяць).
    - SKU table (Номенклатура + Наявність joined).
    - Sales log table (Продажі, incl. discount type/param columns P/Q/R).
    - Plyushky log table (Маркетингові_плюшки).
    - Payouts table (Виплати).
  - **New: interactive calculator panel.** Per the FINAL 2026-08-02 formula in Context above. Inputs: SKU
    (dropdown, existing or new), **кількість у партії/сесії** (batch quantity — new), **сумарна вага партії**
    and **сумарний час друку партії** (batch totals — the panel divides both by quantity to get per-unit
    values before anything is stored/displayed), вага котушки (г), ціна котушки (грн). The three global
    constants (170 Вт, 4.32 грн/кВт·год, 12 грн/год) are read from settings, not entered per calculation —
    display them read-only in the panel with an "edit settings" affordance, not a per-calculation input field.
    No plastic-type field. Live-computed:
    - `Матеріал = (вага_виробу_за_од ÷ вага_котушки) × ціна_котушки`
    - `Електроенергія = 0.17 × час_друку_за_од(год) × 4.32`
    - `Амортизація = 12 × час_друку_за_од(год)`
    - `Собівартість Сергія = Матеріал + Електроенергія + Амортизація` (+ фурнітура, once assigned via the
      dropdown below — shown as a separate addable line, not required to compute the base cost)
    - 3 RRP scenarios (Консервативна/Середня/Оптимістична) and resulting BoosterShop margin at each, mirroring
      the `Аналітика` tab's existing RRP/margin-tier shape (see local
      `3d-print/3D-P_nomenclature-tracker_v6_20260731.xlsx`, `Аналітика` tab) — the *scenario/margin structure*
      still applies, only the `Собівартість Сергія` input feeding it changed; do not assume the tab's older
      cost formula is still correct, recompute it per this section.
    - **Fixture dropdown**: populated from `Фурнітура_довідник` (new in `3D-P-008`'s addendum), selecting an
      option adds its price as a separate line, independent of and after the base cost calc.
    - **Model link field**: a plain URL input/display (not a photo upload) — exact semantics per
      `plans/3D-P-002_catalog-placement-admin-guide_20260731.md` §2/§7.
    - "Зберегти в таблицю" button — calls `3D-P-008`'s `3dp_write`/`3dp_append_row` to persist the **per-unit**
      values (never raw batch totals) back into `Номенклатура`, per the addendum's corrected column layout —
      coordinate exact column letters with whatever `3D-P-008`'s addendum actually produced, don't guess a
      stale layout.
    - No packaging-cost logic here — that is `3D-P-010`'s scope, not this task's.
  - `ROADMAP_FLOW` mirror: add/update the `3D-P-006` entry per `ROADMAP_SOP.md`.

## What NOT to touch

- The `3D-P-008` Apps Script project itself — this task is a pure API consumer. If a needed action doesn't
  exist yet, that's a gap in `3D-P-008`, flag it, don't add ad-hoc endpoints from this task.
- Main CRM Apps Script/webapp/token — unrelated, separate credential path (see Scope above).
- Existing dashboard pages and their JS/state — additive only.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant
  feed, Product schema.
- 3D-P-007 (Serhiy's local server) — separate task, no dashboard-specific coupling.

## Acceptance criteria

- [ ] New "3D-друк" nav item visible, `page-3dprint` renders without affecting other pages' state.
- [ ] Overview/SKU/sales/plyushky/payouts tables render real data via `3D-P-008` actions only (verify via
      network tab — no direct Sheets API/Drive calls from the dashboard).
- [ ] Calculator computes the FINAL 2026-08-02 formula (material via spool ratio, electricity via 170W×tariff,
      amortization via 12 грн/год) correctly for a batch entry (e.g. 36 units, session totals) — confirm the
      panel divides by quantity before showing/saving per-unit values, not before.
      RRP scenarios/margin tiers mirror `Аналітика`'s existing tier shape for a known SKU (use whatever SKU is
      actually in `Номенклатура` row 2 at test time — do not hardcode a SKU name).
- [ ] The three global constants render read-only from settings (not per-calculation inputs) and updating a
      setting changes the live calculation without a redeploy.
- [ ] Fixture dropdown populates from `Фурнітура_довідник` and adds its price as an independent line.
- [ ] No plastic-type field anywhere in the panel.
- [ ] "Зберегти в таблицю" round-trips: write via `3dp_write`, then a fresh `3dp_get_row` for that SKU shows
      the new per-unit value (not a batch total).
- [ ] `TOKEN_3DP` is fully independent of the main CRM token (test: clearing one doesn't affect the other).
- [ ] Existing main-CRM pages unaffected — no shared-code regression.
- [ ] `ROADMAP_FLOW` entry for `3D-P-006` added/updated.

## QA checklist (owner runs after deploy)

- [ ] Hard-refresh, confirm both token prompts are independent.
- [ ] Enter a test calculation in the calculator, save it, refresh the page, confirm it persisted.
- [ ] Spot-check the SKU table numbers against the live Sheet directly (open it, compare one row).
- [ ] Confirm one existing page (e.g. Огляд) still works — no regression.

## Rollback note

- `git revert` on `dashboard/booster-dashboard.html` — additive change, low blast radius.
- No changes to the `3D-P-008` script or the Sheet's formula structure from this task directly (writes go
  through `3D-P-008`'s already-guarded `3dp_write`, which has its own rollback path via `_Аудит_API`).

## Risks

- CRM risky zone — rollback above + QA checklist required before Done.
- Do not expand into more of §10 (виробництво kanban, ринкові аналоги browser) — open a new bounded task if
  the owner wants that next.

## Recommended status after execution

`In progress` until owner QA passes → then `Done`.
