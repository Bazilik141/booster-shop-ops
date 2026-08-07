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
- When the owner packs/ships an order containing a 3D-printed item, they manually record which fixture
  consumable(s) were used — in the **same** order-edit form where `packaging_type`/ТТН are already entered
  (`editPackagingType`, `editTtn` etc. in `dashboard/booster-dashboard.html`, ~lines 2953–2988).
- On save, each selected fixture line's cost pulls into the correct 3D-P `Продажі` row (matched via Addendum
  #2's order+CRM-row key below), alongside the packaging cost pull.
- **Full automation is explicitly deferred**: once the storefront gets product-options support (customer
  picks chain vs. carabiner on the product page), this manual order-pack-time step goes away. That work
  already exists as its own task — **`3D-P-011`** (PDP characteristic/variant selector for multi-variant
  3D-print items, discovery stage as of 2026-08-01, registered in `ROADMAP_SOP.md`'s 3D-P ID table — scoped
  originally for a size trigger, "Onyx 21cm/15cm", but the same mechanism would also serve a chain-vs-carabiner
  fixture selector). Do not build storefront product-options in this task — that is `3D-P-011`'s scope, only
  the interim manual-entry pull is in scope here.

### Fixture UI correction, 2026-08-03 — repeatable, not single-select

Owner live QA (`OC-FOP-0296`) found the order-edit form has no way to attach a consumable to an order at all,
and clarified the real need is broader than a single dropdown: **one order can consume more than one fixture
line, and the lines can be different types** (e.g. one chain + one carabiner on the same order, or two of the
same chain). A single `Фурнітура` dropdown cannot represent that.

Corrected UI: a repeatable fixture-line list in the order-edit form, not a single field.

- "Додати розхідник" (add line) button adds one row: `Розхідник` dropdown (populated from `Розхідники` rows
  where `Тип = Фурнітура`) + `Кількість` number input. Multiple rows allowed, duplicates allowed (e.g. qty 2 of
  the same chain, or two separate rows of qty 1 each — either is acceptable, do not force dedup).
- Each row removable before save (an "×" or "Прибрати" per row).
- Zero rows is valid (order has no fixture — most orders won't).
- On save, each row's `(fixture, qty)` pulls its cost (`ціна/шт × qty`, summed per fixture type if there are
  duplicate rows) into the matched 3D-P `Продажі` row — see Addendum #2's match key (order number + CRM row
  number), not a single order-level target.
- Each fixture line consumed must also decrement that `Розхідник`'s own stock in the main CRM (`Розхідники` —
  "на складі"), the same way any other consumable write-off already works there — confirm the existing
  write-off mechanism/action before inventing a new one.

**Phase 0 additions (on top of the packaging investigation already below):**

- Confirm whether `Розхідники`'s `Тип` field already accepts arbitrary/new values (i.e. whether adding
  `Тип = Фурнітура` rows requires any code change at all, or only new data entered through the existing
  Розхідники admin UI — this may mean zero backend schema change is needed for the consumable-tracking half of
  this feature, only the order-edit-form UI and the pull-into-3D-P-sheet parts are new code).
- Confirm the exact 3D-P sheet target for fixture cost (new column vs. reusing an existing one) against
  `3D-P-013`'s Вироби/Інформація field list — don't invent a column, check both handoffs' current state first.
- Confirm the existing consumable write-off mechanism in the main CRM (how a `Розхідники` row's "на складі" is
  normally decremented today) so fixture consumption reuses it instead of a new parallel mechanism.

**Acceptance criteria additions:**

- [ ] `Розхідники` supports `Тип = Фурнітура` rows (confirm existing support or add minimal support — Phase 0
      determines which).
- [ ] Order-edit form has a repeatable fixture-line list (add/remove rows, each a Розхідник + кількість),
      not a single dropdown. Zero, one, and multiple (including duplicate-type) rows all work.
- [ ] Saving an order with fixture lines pulls each line's cost into the matched 3D-P `Продажі` row (per
      Addendum #2's order+row match key) and decrements the corresponding `Розхідники` stock — verified
      end-to-end for one real/simulated order with two different fixture lines.
- [ ] No automatic storefront-facing product-options logic is added — confirmed out of scope.

---

## Addendum #2 — Auto-create sale (upsert), correcting the Phase 1 design, 2026-08-03

**Root cause of a real bug found in owner live QA (order `OC-FOP-0296`, SKU `FIG-CHARM-001`, after V87
deployed 2026-08-03): the deployed patch never fires, because it only knows how to UPDATE an existing 3D-P
`Продажі` row matched by order number. Nothing creates that row in the first place.** This was this handoff's
own design error, not a Codex implementation error — the "Phase 1, locked design" section below wrongly
assumed a 3D-P sales row already exists by the time an order is placed.

**Corrected model, owner-confirmed 2026-08-03:**

- Калькулятор (`3D-P-006`/`3D-P-013`) stays exactly as already built: production-batch entry only. It adds
  finished units to `Наявність` and computes per-unit cost. It has and must keep **no relationship to orders or
  sales** — do not tie a batch to an order number anywhere.
- The main CRM is the sole source of truth for orders. `apiAddSale_`, on detecting a 3D-P-trigger SKU
  (`BR-`/`FIG-`/`ACC-3D-0XX`) in an order line, must **create** the matching 3D-P `Продажі` row if none exists
  yet (via the already-existing `3dp_append_row` action — see `3D-P-008`'s scope section, "Used e.g. for ...
  logging a new Продажі ... entry"), not only update one.
- `apiUpdateSale_` keeps updating the same row for later changes (packaging, status, and fixture cost once
  `3D-P-010`'s fixture half ships).
- **Match key changes**: order number alone is not enough — one order can contain more than one 3D-P SKU line.
  Match/create per **order number + main-CRM row number** (the CRM row number is already available inside
  `apiAddSale_`/`apiUpdateSale_` as `row`/`rows`), not per order number alone. Revise the "Phase 1, locked
  design" section below accordingly: it currently says "the first matching row by row number" — that language
  is superseded by this addendum.
- **Stock decrement, owner-confirmed 2026-08-03**: creating a 3D-P sale row must decrement `Наявність`
  automatically, through the existing `3dp_adjust_stock` ledger (`3D-P-008` Addendum #2) with reason text like
  `auto: CRM order <order_id>` — never a raw overwrite of `Наявність!G`. If the sold quantity exceeds current
  available stock, do **not** block the write or the CRM order save — apply it (stock may go negative) and
  surface it as a visible warning (e.g. a flagged row in the existing adjustment ledger / a dashboard
  "Потребує уваги" entry), consistent with this task's fail-open principle: 3D-P bookkeeping issues must never
  block a CRM sale.

### Addendum #3 — third write path found, 2026-08-04

The hook covers `apiAddSale_` and `apiUpdateSale_` only. Live evidence
(`diagnostics/3D-P_live-schema-audit_20260803.md`, finding 9) shows the owner's
habitual update path is neither: the in-Sheet `Оновити_продаж` form runs
`updateSaleStatus()` (menu execution), which writes `Продажі` columns 16, 20,
23, 24, 26, 27 and 29 directly and never calls `apiUpdateSale_`.
`updatePaymentStatus()` aliases it.

Consequence: after V89 shipped the corrected create/upsert helper, order
`OC-FOP-0300` still did not sync, because the owner updated it through the menu
path and the hook was never invoked. This is a scope defect in this handoff, not
an implementation error.

Required: call the same `sync3dpSales_(sales, order, rows)` from
`updateSaleStatus()`, immediately before its `invalidateDoGetCache_()`, with the
identical fail-open contract. **Verify `UrlFetchApp` behaviour under the menu's
user-authorisation context before assuming parity with the Web App context** —
if outbound fetch is unavailable or slow there, record the intent in
`3D-P-014`'s sync journal instead of silently doing nothing, and say so.

Acceptance addition:

- [ ] A sale updated through the in-Sheet `Оновити_продаж` form syncs to 3D-P
      exactly as one updated through the dashboard, or fails visibly in the
      journal — never silently.

**Blocking gap — RESOLVED, 2026-08-03:** live `Продажі` headers confirmed as `A:S`. No existing column holds a
CRM row number, which the match key (order number + CRM row number) needs.

**Match-key storage decision, 2026-08-03:** do **not** embed the CRM row number as a marker inside `O —
Примітки` (Codex's proposed workaround). Rejected: `Примітки` is a human-edited free-text field — a marker
living inside it will eventually be overwritten, duplicated, or stripped by normal human editing, silently
breaking the match with no error. This also breaks the precedent already set in `3D-P-008` Addendum #2, where
a similar temptation (reuse legacy `Номенклатура!F` for archive status) was correctly rejected in favor of new
dedicated technical columns (`API_статус_запису`/`API_історія_змін`).

**Decision: add one new dedicated column**, `T` (first free column after confirmed `A:S` — verify it is
actually empty before writing to it), holding the CRM row number as a plain integer. This column is
system/technical, not a Sergiy/owner-facing editable field — do not surface it in the dashboard's sales table,
and add it to the same whitelist model as `3D-P-008`'s other technical columns (numeric only, no formula,
written only by `apiAddSale_`/`apiUpdateSale_`'s hook — not by `3dp_write`'s general caller surface unless
explicitly whitelisted for that purpose).

**Status of the currently deployed V87 patch:** inert, not harmful. It fail-open-skips every time (no matching
row exists yet), so no CRM order has been blocked or corrupted. No emergency rollback needed — it can stay
deployed while this correction is built; the correction is additive (create-if-missing, then the existing
update-if-found logic already deployed keeps working unchanged).

**New/updated acceptance criteria:**

- [ ] Live `Продажі` header/whitelist confirmed before implementation (see gap above).
- [ ] `apiAddSale_` creates a new 3D-P `Продажі` row for each order line carrying a 3D-P-trigger SKU that has
      no existing match (order number + CRM row number), via `3dp_append_row`, whitelisted columns only.
- [ ] `apiUpdateSale_` continues to find and update the correct row using the corrected order+row match key
      (not order number alone).
- [ ] Sale creation decrements `Наявність` via `3dp_adjust_stock` with an auto-generated reason referencing the
      order; insufficient stock produces a visible warning, never a blocked write.
- [ ] Re-running the same order event (e.g. a duplicate `apiUpdateSale_` call) never creates a duplicate 3D-P
      row — idempotent by order+row match key.
- [ ] A multi-line order with two different 3D-P SKUs creates two distinct 3D-P `Продажі` rows, not one merged
      row.
- [ ] Fail-open unchanged: a missing/unreachable 3D-P API never blocks main-CRM order save.

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

**Packaging — Phase 1, [corrected by Addendum #2, 2026-08-03 — update-only was wrong, see above]:**

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

`In progress` — packaging Phase 1's original update-only design is deployed (V87) but functionally incomplete
per Addendum #2; it needs the create-path correction before it does anything real. Fixture Phase 1 still waits
on its own Phase 0. → `Done` once Addendum #2's acceptance criteria and both QA checklists pass.
