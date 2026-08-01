# Codex Handoff — 3D-P-010: Auto-pull packaging cost from main CRM into 3D-P sales log

Date: 2026-08-02 | Parent: 3D-P-000 · related: 3D-P-006, 3D-P-008, 3D-P-009 (NCRM chain, if overlapping)
Codex config: model=Sol · effort=xhigh

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

**What is NOT yet confirmed — this task starts with investigation, not implementation:**

- Where the resolved **cost in грн** for each non-`Інше` `PACKAGING_TYPES` entry actually lives (a static
  price table somewhere in the main CRM Apps Script? a lookup against `Розхідники`/consumables by name? a
  hardcoded map?). Nothing in the dashboard client code shown above stores a price for the four named types —
  only `custom_packaging_cost` is a number, and only for `Інше`.
- Which main-CRM Apps Script GET action (if any) already exposes a given order's `packaging_type`/resolved
  cost to an external caller. `AGENTS.md`'s `bs-crm-plan` convention lists `summary`, `orders`, `stock_alerts`,
  `sku_list` as the existing bounded read actions — `orders` is the most likely candidate, but its actual
  response shape must be confirmed by a live bounded call, not assumed.
- Whether the 3D-P sheet's `Продажі` tab currently has any order-identifying column to match a sale row back
  to a specific main-CRM order (needed to know *which* `Продажі` row a given order's packaging cost belongs
  to). Confirm via a bounded `3dp_get_range` read of `Продажі`'s header row — do not assume the column layout
  described in earlier 3D-P handoffs is still current.
- Whether main-CRM order creation/status-update already has any hook point this task can attach to, or whether
  one needs to be added. Per `AGENTS.md`'s CRM section, `doPost` on the main CRM script is reserved for order
  synchronization — any new logic here must respect that boundary, not add an unrelated POST action to it.

## Scope (what to change)

**Phase 0 — investigation (required before any code):** Codex reads the main CRM Apps Script source (owner
will provide the relevant backup/export if not already in the repo) and the current `Продажі` tab headers in
the live 3D-P sheet (bounded read), and reports back: (a) where packaging cost-by-type is resolved today, (b)
which existing action/hook is the right integration point, (c) whether `Продажі` needs a new order-reference
column. **Stop and report findings to the owner before writing any implementation** — this is a cross-system
risky-zone task (main CRM), not a self-contained 3D-P-sheet change like `3D-P-006`/`3D-P-007`/`3D-P-008`.

**Phase 1 — implementation (only after Phase 0 findings are reviewed and owner gives go-ahead):**

- A narrow, one-directional pull: main-CRM order data → 3D-P `Продажі!G`, never the reverse. This task does
  not write anything into the main CRM.
- Trigger point per owner (2026-08-02): "має підтягуватись під час створення або оновлення статусу
  замовлення, яке містить 3д товар" — i.e., at order creation or order status update, for any order containing
  a 3D-printed SKU (SKU prefix `BR-`/`FIG-`/`ACC-3D-0XX` per `plans/3D-P-002_catalog-placement-admin-guide_20260731.md`
  §8). Exact mechanism (main-CRM-side hook calling into the 3D-P API, vs. a polling job, vs. something else) is
  Codex's design call given Phase 0 findings — propose one, don't default silently if more than one reasonable
  option exists (see `AGENTS.md` UI/CSS-patch discipline §4 for the general pattern: offer options, don't pick
  the cheaper one unprompted, when it's a real architectural fork).
- The write into `Продажі!G` must go through `3D-P-008`'s already-guarded `3dp_write` (whitelist + formula
  check + optimistic lock + `_Аудит_API` logging) — never a direct Sheets write.
- If the resolved packaging cost for a standard type isn't found anywhere in the main CRM (Phase 0 gap), do
  not invent a number — leave the field for manual entry and flag the gap back to the owner, same principle as
  the original `3D-P-008` reconciliation gate.

## What NOT to touch

- Main CRM's `doPost` — do not repurpose it for this task; it's reserved for order sync per `AGENTS.md`.
- Any direct write into the main CRM's order/sales data from the 3D-P side.
- `Номенклатура`, `Друк-лог`, calculator logic — unrelated to this task, covered by `3D-P-006`/`3D-P-007`/
  `3D-P-008`'s addendum.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant
  feed, Product schema.

## Acceptance criteria

- [ ] Phase 0 report delivered and reviewed by owner before Phase 1 starts.
- [ ] Packaging cost for a real order containing a known 3D-P SKU lands in `Продажі!G` automatically, verified
      end-to-end (order event → `Продажі!G` value), not just unit-tested in isolation.
- [ ] No write path exists from the 3D-P side back into the main CRM.
- [ ] `Продажі!G` write goes through `3dp_write` only — confirmed via the same whitelist/formula/audit
      guarantees as every other 3D-P write.
- [ ] Missing/unresolvable packaging cost fails safe (flagged, not guessed) rather than writing a wrong number.
- [ ] `ROADMAP_FLOW` entry for `3D-P-010` added.

## QA checklist (owner runs after deploy)

- [ ] Place or simulate one real order containing a 3D-P SKU with a standard (non-`Інше`) packaging type,
      confirm the correct cost lands in `Продажі!G`.
- [ ] Repeat with `Інше`/custom packaging cost, confirm the custom value is used, not a default.
- [ ] Confirm updating an order's status after creation re-triggers the pull correctly (per owner's stated
      trigger: creation OR status update) without duplicating or overwriting an already-correct value.
- [ ] Confirm no unrelated main-CRM data changed as a side effect.

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

`In progress` until Phase 0 findings are owner-reviewed and Phase 1 passes QA → then `Done`.
