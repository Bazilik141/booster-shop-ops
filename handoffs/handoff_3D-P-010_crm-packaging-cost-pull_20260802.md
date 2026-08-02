# Codex Handoff — 3D-P-010: Auto-pull packaging + fixture cost from main CRM into 3D-P sales log

Date: 2026-08-02 | Parent: 3D-P-000 · related: 3D-P-006, 3D-P-008, 3D-P-009 (NCRM chain, if overlapping), 3D-P-013
Codex config: model=Sol · effort=xhigh

## Addendum — Fixture (фурнітура) pull added, 2026-08-02 (same task, expanded scope)

Owner confirmed the same order-pack-time entry point should also carry fixture consumption, not just
packaging. Read this before Phase 0 below — it changes what Phase 0 needs to investigate.

**Owner-confirmed model (semi-automatic, interim — full automation deferred to NCRM):**

- Each fixture variant (e.g. "кольоровий ланцюжок", "металевий карабін") is tracked as its **own** consumable
  ("розхідник") in the main CRM's existing `Розхідники` system — own stock level, own price/batch or price/шт,
  same shape as any other consumable already there (per `dashboard/booster-dashboard.html`'s existing
  Розхідники table: назва, тип, ціна/шт, на складі, в дорозі, витрата 30д, вистачить).
- When the owner packs/ships an order containing a 3D-printed item, they manually select which fixture
  consumable was used — in the **same** order-edit form where `packaging_type`/ТТН are already entered
  (`editPackagingType`, `editTtn` etc. in `dashboard/booster-dashboard.html`, ~lines 2953–2988). Add a
  `Фурнітура` field to that same form, a dropdown populated from `Розхідники` rows where `Тип = Фурнітура`.
- On save, the selected fixture's cost pulls into the 3D-P sheet **alongside** the packaging cost pull
  (same `Продажі!G`-style write via `3dp_write`, or an adjacent column — confirm exact target column against
  `3D-P-011`'s Zone C table needs and `3D-P-008`'s current schema).
- **Full automation is explicitly deferred**: once the storefront gets product-options support (customer
  picks chain vs. carabiner on the product page), this manual order-pack-time step goes away. That work
  already exists as its own task — **`3D-P-011`** (PDP characteristic/variant selector for multi-variant
  3D-print items, discovery stage as of 2026-08-01, registered in `ROADMAP_SOP.md`'s 3D-P ID table — scoped
  originally for a size trigger, "Onyx 21cm/15cm", but the same mechanism would also serve a chain-vs-carabiner
  fixture selector). Do not build storefront product-options in this task — that is `3D-P-011`'s scope, only
  the interim manual-entry pull is in scope here.

**Phase 0 additions (on top of the packaging investigation already below):**

- Confirm whether `Розхідники`'s `Тип` field already accepts arbitrary/new values (i.e. whether adding
  `Тип = Фурнітура` rows requires any code change at all, or only new data entered through the existing
  Розхідники admin UI — this may mean zero backend schema change is needed for the consumable-tracking half of
  this feature, only the order-edit-form dropdown and the pull-into-3D-P-sheet parts are new code).
- Confirm the exact 3D-P sheet target for fixture cost (new column vs. reusing an existing one) against
  `3D-P-011`'s Вироби/Інформація field list — don't invent a column, check both handoffs' current state first.

**Acceptance criteria additions:**

- [ ] `Розхідники` supports `Тип = Фурнітура` rows (confirm existing support or add minimal support — Phase 0
      determines which).
- [ ] Order-edit form has a `Фурнітура` dropdown alongside `Паковання`, populated from Фурнітура-type
      consumables.
- [ ] Selecting a fixture on order save pulls its cost into the 3D-P sheet the same way packaging does —
      verified end-to-end for one real/simulated order.
- [ ] No automatic storefront-facing product-options logic is added — confirmed out of scope.

---

## Context

Owner reversed an earlier decision (2026-07-31: "packaging not added as a separate cost line for 3D-P items,
absorbed into margin"). New decision, 2026-08-02: packaging type/cost should be **pulled automatically from
the main Booster Shop CRM's order data** into the 3D-P sheet's `Продажі!G` ("Витрати BoosterShop за од.")
whenever a sold item is a 3D-printed SKU.

Confirmed evidence this data already exists in the main CRM: `dashboard/booster-dashboard.html` (main-CRM
sales log, not the 3D-P sheet) already carries `packaging_type` (a fixed list, `PACKAGING_TYPES` at
`booster-dashboard.html:2488`: `Мала м'яка 14х12 см`, `Середня м'яка 16х14 см`, `Велика пакет 17х30 см`,
`Конверт Airpock 14х22 см`, `Інше`) and an optional `custom_packaging_cost` override (used only when type is
`Інше`) on both `add_sale` and `update_sale` payloads (`booster-dashboard.html:2521-2529`, `2717-2720`,
`2961-2988`).

**Packaging Phase 0 — RESOLVED, 2026-08-02** (see `diagnostics/3D-P-010_phase0_report_20260802.md`'s
addendum for the full evidence trail). Confirmed via a live look at the main CRM Google Sheet:

- `Продажі!P` (main CRM) already holds the resolved packaging cost in грн, `Продажі!AC` holds the type. For
  multi-line orders the order's total is **split across line rows** (e.g. order `OC-FOP-0285`: six rows sum to
  exactly 5.00 грн) — a single row's `P` is a fraction, not the order total.
- The 3D-P sheet already has everything needed to receive it: `Продажі!G` (Витрати BoosterShop за од.) is the
  write target, `Продажі!N` (№ замовлення) already allows matching back to a CRM `order_id`. No new column
  needed on the 3D-P side.
- `action=orders` does not return packaging fields — confirmed dead end, do not use it.
- `action=recent_sales` does return aggregated `packaging_cost`/`packaging_type`, but is **not** the
  integration path — see the locked design below, which is event-driven instead.

**Fixture Phase 0 — still open** (see the Addendum section above): whether `Розхідники`'s `Тип` field already
accepts new values, and the new order-edit-form UI field, are not resolved by the packaging evidence above.

## Scope (what to change)

**Packaging — Phase 1, locked design (Codex-proposed 2026-08-02, evidence-based, ready to build):**

- A narrow, one-directional pull: main-CRM order data → 3D-P `Продажі!G`, never the reverse. This task does
  not write anything into the main CRM.
- Hook into the main CRM's existing `apiAddSale_()` and `apiUpdateSale_()` functions. On each call, determine
  whether the order contains a 3D-P SKU (prefix `BR-`/`FIG-`/`ACC-3D-0XX` per
  `plans/3D-P-002_catalog-placement-admin-guide_20260731.md` §8).
- Compute the order's full packaging sum: use an already-computed total if the CRM has one, otherwise sum
  `Продажі!P` across all main-CRM rows sharing that `order_id`. Do not assume a single row's `P` is the total —
  it isn't for multi-line orders.
- Match to the 3D-P `Продажі` row(s) via `N` (order number). Write the full sum into **exactly one** 3D-P row
  — the first matching row by row number. Never duplicate the sum across multiple 3D-P rows from the same
  order (an order can contain more than one 3D-P line).
- Write only through `3D-P-008`'s already-guarded `3dp_write` with `expected_current` — never a direct Sheets
  write.
- **Fail safe, always**: if no matching 3D-P row exists yet, or the 3D-P API is unreachable, the main-CRM order
  flow must not break — log/record the skip and continue normally. This must never block or fail order
  creation/update — that function's primary job (order sync) takes priority over this pull.
- Do not use `action=recent_sales` or any polling approach — the event hook already has the exact `order_id`
  and sum at the moment of the event, no scanning needed.

**Fixture — still Phase 0** (see Addendum above) — do not start fixture implementation until its own Phase 0
questions (Розхідники `Тип` flexibility, new UI field) are answered. Packaging and fixture can ship as two
separate patches within this same task if fixture's Phase 0 takes longer — do not hold packaging back waiting
for fixture.

## What NOT to touch

- Main CRM's `doPost` — do not repurpose it for this task; it's reserved for order sync per `AGENTS.md`.
- Any direct write into the main CRM's order/sales data from the 3D-P side.
- `Номенклатура`, `Друк-лог`, calculator logic — unrelated to this task, covered by `3D-P-006`/`3D-P-007`/
  `3D-P-008`'s addendum.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant
  feed, Product schema.

## Acceptance criteria

- [ ] Packaging cost for a real order containing a known 3D-P SKU lands in `Продажі!G` automatically, verified
      end-to-end (order event → `Продажі!G` value), not just unit-tested in isolation.
- [ ] A multi-line order (like `OC-FOP-0285`) correctly sums `P` across its rows and writes the total once, to
      the first matching 3D-P row only — no duplication, no under-count.
- [ ] No write path exists from the 3D-P side back into the main CRM.
- [ ] `Продажі!G` write goes through `3dp_write` only — confirmed via the same whitelist/formula/audit
      guarantees as every other 3D-P write.
- [ ] A missing 3D-P row or unreachable 3D-P API never blocks or fails main-CRM order creation/update — confirm
      with a deliberate negative test (simulate the API being down).
- [ ] Fixture's own Phase 0 questions answered before fixture implementation starts (separate from packaging).
- [ ] `ROADMAP_FLOW` entry for `3D-P-010` added.

## QA checklist (owner runs after deploy)

- [ ] Place or simulate one real order containing a 3D-P SKU with a standard (non-`Інше`) packaging type,
      confirm the correct cost lands in `Продажі!G`.
- [ ] Repeat with `Інше`/custom packaging cost, confirm the custom value is used, not a default.
- [ ] Repeat with a multi-line order, confirm the summed total (not a single row's fraction) lands correctly.
- [ ] Confirm updating an order's status after creation re-triggers the pull correctly (per owner's stated
      trigger: creation OR status update) without duplicating or overwriting an already-correct value.
- [ ] Confirm no unrelated main-CRM data changed as a side effect, and that a simulated 3D-P-API outage does
      not affect normal order processing.

## Rollback note

- This task only ever writes into the 3D-P sheet via the already-guarded `3dp_write` — any bad write is
  recoverable via `_Аудит_API`'s old-value log, same as any other 3D-P write.
- If the trigger mechanism itself needs disabling, it must be revertible independently of `3D-P-008`'s core
  API (do not couple this task's hook so tightly that disabling it risks the base API).
- No OpenCart/database changes anticipated: flag immediately if Phase 0 findings show otherwise, before Phase 1.

## Risks

- **CRM risky zone**, per `AGENTS.md` — this task touches main-CRM order data, a higher-blast-radius surface
  than the isolated 3D-P sheet every other 3D-P task has stayed within. Extra care, explicit Phase 0 gate,
  rollback plan, and focused smoke test are not optional here.
- Real risk of scope creep into "sync the whole order into the 3D-P sheet" — this task is packaging cost only,
  not a general order-sync feature. If Phase 0 findings suggest the clean solution is broader, stop and ask
  the owner rather than silently expanding scope.
- Depends on `3D-P-008`'s 2026-08-02 addendum for `Продажі` column stability — confirm that addendum's status
  before starting Phase 1.

## Recommended status after execution

`In progress` — packaging Phase 1 can start now; fixture Phase 1 waits on its own Phase 0 → `Done` once both
QA checklists pass.
