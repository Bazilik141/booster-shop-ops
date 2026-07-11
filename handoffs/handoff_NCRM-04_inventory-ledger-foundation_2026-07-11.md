# Codex Handoff — NCRM-04: Inventory ledger foundation

Date: 2026-07-11 | Parent: NCRM-01 (Done), NCRM-02 (Done). Closes the owner-decision gate that left NCRM-03 (In progress) blocked.

## 1. Task ID
NCRM-04 — Inventory ledger foundation. Notion: verify before starting — the existing NCRM-04 card (if any) may still carry the old title "Read screens" from the pre-contract sequence. See Context for the renumbering.

## 2. Context
- Decision source: `plans/NCRM-financial-model-v2_technical-contract_20260711.md` (owner-approved, §3–5, §7 item 1, §8, §9) + `diagnostics/NCRM-03_import-history-kpi-reconciliation_report_20260710.md`.
- **Renumbering note:** `context-index.md` and (likely) Notion still map NCRM-04…09 to the original 2026-06-26 architecture plan (NCRM-04 = read screens, NCRM-05 = write forms, NCRM-06 = expenses/P&L, NCRM-07 = order pipeline). The v2 technical contract §7 redefines the sequence: NCRM-04 = Inventory foundation (this task), NCRM-05 = Mystery fulfillment, NCRM-06 = Returns/cost quality, NCRM-07 = Reporting/forecast — matching the owner's own framing of this task. `context-index.md` is updated in the same commit as this handoff. **Owner action needed:** relabel the NCRM-04…07 Notion cards accordingly (page_id lookup — `ROADMAP_SOP.md §5` — not done here). NCRM-08/09 are not reconciled against v2 yet.
- Current schema: `ncrm/supabase/migrations/0001`…`0005` — 28 tables, 12 views, 11 functions (Stage 1–4 + grants), verified local + remote per `diagnostics/NCRM-01_supabase-project-sql-migrations_report_20260705.md`. These 5 files are immutable per the contract's scope line and the owner's instruction.
- NCRM-03 is blocked on exactly what this task adds: `writeoff_items.qty > 0` cannot represent six negative correction rows (`WRT-0051`, `WRT-0052`, `WRT-0094`–`WRT-0097`; reported total `-29` units; per-SKU gaps `MSYM -11 / MDEX -1 / OUTL -6 / MZERO -7`). The resulting reconciliation gaps: warehouse PRRO `+8,061.67`, warehouse management `+8,545.06`, stock+in-transit management `+8,545.05` UAH — the exact shortfall a signed adjustment layer is meant to close.
- NCRM-03's second blocker (legacy average-cost `Склад` values vs. FIFO for `PKM-JP-MBRV-BBX` / `PKM-JP-SPIN-BST`) gets a comparison tool from canonical FIFO valuation here, but closing that gap is a future re-import/reconciliation task, not numbered yet — do not confuse it with NCRM-05/06/07.
- **This task does not re-run or fix the NCRM-03 import.** It only adds the model NCRM-03 was missing. Re-reconciling NCRM-03's history under the new model is separate future work.
- `purchase_lot_statuses` today: `ordered`, `in_transit`, `in_stock`, `selling`, `sold`, `cancelled` (0001) — the 5 canonical states from contract §3.3 plus one legacy terminal/non-stock status. No new codes are needed.
- `sale_items.cost_state` (added in 0003) currently allows only `provisional`/`actual`, defaulting to `actual`. This contradicts contract §5.2 (`pending` / `provisional` / `estimated` / `actual`) and is a real gap: a freshly inserted `sale_item` on a not-yet-actual sale already carries `cost_state = 'actual'` by default, before any real cost is computed.
- `fn_fifo_cost_for_product` (0002) already distinguishes `cost_method` (`FIFO` / `FIFO+fallback` / `Fallback`) with an audit trail, but `fn_fix_sale_cogs` always writes `cost_state='actual'`, even when part or all of the cost is a fallback (last-lot unit cost stretched over a shortage). `v_data_quality` (0004) does not currently surface fallback-derived rows separately — only `mystery_cogs_provisional`.
- **Precedent already in this repo for "0001–0005 stays immutable" not meaning "the resulting schema is frozen":** `0003` already redefines objects created in `0002` via `create or replace function` (`fn_fix_new_sale_item`, `fn_fix_actual_sale_items`) and via `alter table ... drop constraint ... add constraint` (`sale_items_cost_method_chk`) — without touching the `0002` file. `0006` should use the same pattern for `sale_items_cost_state_chk`, `fn_fifo_cost_for_product`, `fn_fix_sale_cogs`.
- `products` has no weight column and no `is_outlet` flag today. The legacy "outlet" concept is a distinct SKU (`PKM-JP-OUTL-BST`, see `diagnostics/CRM-LOT-0058_outlet-transfer-writeoff_report_20260706.md`), not a catalogue attribute — exactly what contract §4.2 says must change ("Outlet is a catalogue attribute, never a SKU-text heuristic").
- `purchases` holds order-level totals; `purchase_lots` already stores final per-lot amounts directly (`goods_cost_uah` etc.) with no allocation formula anywhere in the schema — NCRM-03's import bypassed allocation because the source already had lot-level costs. The §3.2 allocation policy for future manual purchases is genuinely missing functionality, not a formality.

## 3. Goal
One additive migration (`ncrm/supabase/migrations/0006_*`, split into `0007_*` too if that's cleaner) on top of `0001`–`0005` that delivers: a signed adjustment ledger, canonical FIFO warehouse/asset valuation, a shared-cost allocation policy, an explicit `estimated` cost state, and an outlet flag — without touching the existing 5 files, Mystery, returns, KPI/dashboard views, or any live system.

## 4. What to change (scope)
New file `ncrm/supabase/migrations/0006_inventory_ledger_foundation.sql` (task-descriptive name, following `0005`'s departure from the `stageN` pattern — confirm no `0006+` file already exists before creating).

**a) `inventory_adjustments` + `inventory_adjustment_items` — signed replacement for correction writeoffs:**
- `inventory_adjustments`: human-readable unique number (same convention as `writeoff_no`/`order_no`), the adjustment/correction date (the same date used for the variance P&L entry — contract §3.4), source/audit note, standard `created_at`/`updated_at` trigger. Contract doesn't fix a complete adjustment-type taxonomy — do not invent the final business list. Minimum required distinction (§3.4 last line): a normal correction that generates P&L impact vs. a migration-only opening balance that's excluded from operating P&L. Mark anything else undecided as `-- TODO(NCRM-04/OWNER)`, matching the `0001`/`0003` convention.
- `inventory_adjustment_items`: signed `qty_delta` (`check (qty_delta <> 0)`), `product_id` FK, PRRO and management unit-cost snapshots (name them like `sale_items.prro_unit`/`mgmt_unit` for consistency), and a cost source/audit text field (like `sale_items.cost_audit`) documenting where a positive correction's cost comes from or which layer a negative correction consumes.
- **Open architectural decision — document the chosen approach in the PR/diagnostic report**, same as NCRM-03 §4's "flag as open decision" pattern: should adjustment items live entirely alongside `purchase_lots` as their own signed layer (positive `qty_delta` = new cost layer with its own snapshot; negative = consumes an existing layer — mirroring how `writeoff_items` today are netted inside `fn_fifo_cost_for_product`'s `v_skip` counter without ever becoming rows in `purchase_lots`), or some other structure. Constraint to respect: `purchase_lots.qty` requires `> 0` and `purchase_id` is a `not null` FK to `purchases` — a "synthetic lot with no purchase" implies loosening those constraints on an existing (0001) table, which is allowed additively but must be explicitly justified if chosen.

**b) Inventory variance on the correction date:**
- A signed view/aggregate (e.g. `v_inventory_adjustment_pnl`, exact name at Codex's discretion) by product and correction date, queryable for future KPI work (NCRM-07). **Do not wire this into `v_pnl_monthly`/`v_sales_report`** — that's the KPI/dashboard redesign the owner excluded.
- Guarantee: no write path here touches already-fixed `sale_items` (`cost_fixed_at`, `prro_unit`, `mgmt_unit` of existing rows stay untouched) — contract §3.3, sale COGS snapshots are immutable after finalization.

**c) Canonical FIFO warehouse/asset valuation:**
- A queryable view/function computing "Warehouse cost" and "Asset cost" exactly per the contract §2 vocabulary (FIFO remainder of `in_stock`+`selling`+`sold` layers, net of sales/writeoffs/**new adjustments**; asset = warehouse + `ordered`+`in_transit`). This doesn't exist as a reusable object today — NCRM-03 computed these numbers ad hoc in its reconcile script. If it reduces duplication, consider factoring the per-lot FIFO-remaining logic out of `fn_fifo_cost_for_product` into something both costing and valuation can reuse — optional, at Codex's discretion.

**d) Unified lot statuses:**
- No new `purchase_lot_statuses` codes. New FIFO/valuation logic uses exactly the 5 canonical states already seeded in `0001`; `cancelled` stays non-stock and outside FIFO/valuation. Document this in a migration comment; don't touch `0001`'s seed data.

**e) Shared-cost allocation (weight → value → manual override):**
- The §3.2 policy doesn't exist as functionality today (lots already carry final allocated amounts with no formula behind them). Needed: (1) a way to record which method allocated an order's shared costs (weight/value/manual) — per-purchase or per-fee-type granularity is Codex's call, document the choice; (2) a minimal weight field — likely on `products` (a physical attribute of the SKU, not lot-specific) — **verify whether weight is tracked anywhere already before adding a column**; (3) the hard invariant, and the measurable acceptance criterion from contract §8: allocated shares across a purchase's lots must sum exactly to the original order-level fee, for each of the three methods.

**f) Outlet/catalogue flag:**
- `products.is_outlet boolean not null default false` (+ index if useful for the future Mystery eligibility query). Column only — **no backfill of existing SKUs** (e.g. `PKM-JP-OUTL-BST` stays as-is; reclassifying it is a data task, not this schema task).

**g) Explicit `estimated`/needs-review COGS:**
- Widen `sale_items_cost_state_chk` (currently `provisional`/`actual`) to add at least `estimated`; consider `pending` too, for the not-yet-`actual` case that today incorrectly defaults to `actual` (§5.2's full list is `pending`/`provisional`/`estimated`/`actual`). Use `drop constraint`/`add constraint`, following the `0003` precedent.
- `fn_fifo_cost_for_product`/`fn_fix_sale_cogs` (`create or replace`, `0003` precedent): whenever `cost_method` includes a fallback component (`Fallback`/`FIFO+fallback`), write `cost_state = 'estimated'`, not `'actual'`. No silent zero/fallback disguised as `actual`.
- **Do not touch Mystery cost functions** (`fn_refresh_mystery_cogs` etc.) — they already only write `provisional`/`actual`, both of which remain valid after the constraint widens.
- Make fallback-derived rows separately queryable (a new branch in `v_data_quality`, or a small dedicated view) — visibility only, not a KPI/dashboard redesign.

## 5. What NOT to touch
- `ncrm/supabase/migrations/0001`…`0005` — files stay unedited (new objects/ALTERs go in the new file, per the `0003` precedent).
- New Mystery workflow/SKU, `mystery_fulfillments`/`mystery_fulfillment_items` — NCRM-05.
- `refund_items`, return layers, refund COGS reversal — NCRM-06.
- `v_pnl_monthly`, `v_sales_report`, `v_channel_report`, `v_top_skus`, `v_repeat_customers`, `v_below_cost_alert` — no redesign (KPI/dashboard explicitly excluded). `v_data_quality` may only gain one additive check branch, not a rewrite.
- `ncrm/scripts/import-history/*` (NCRM-03) — do not edit or re-run against the new model in this task.
- `ncrm/app/*`, `ncrm/lib/repositories/*` (NCRM-02) — schema-only task; leave the UI/repo layer alone unless it genuinely fails to compile because of this change.
- Live Apps Script CRM, Google Sheet, OpenCart — untouched (separate codebase, read-only if needed for context).
- Standard site-side protected zones (not technically relevant here, listed as the required minimum): `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, Product schema.
- The real (cloud) Supabase project — applying this migration there stays a separate owner-driven step (same split as NCRM-01/02), not part of this task's Definition of Done.

## 6. Likely files/areas
- `ncrm/supabase/migrations/0006_inventory_ledger_foundation.sql` (new; Codex may split into `0006`+`0007` if cleaner — keep ordering correct either way)
- `diagnostics/NCRM-04_inventory-ledger-foundation_report_<date>.md` (new, Codex's own report)
- No changes expected in `ncrm/app/`, `ncrm/lib/repositories/*`, `ncrm/scripts/import-history/*`, or `ncrm/supabase/migrations/0001-0005` — Codex should verify against actual project files before assuming otherwise.

## 7. Acceptance criteria
- [ ] `npx supabase db reset` (local) applies `0001`…`0006` (and `0007` if used) cleanly, exit code 0
- [ ] `git diff` on `0001`…`0005` is empty
- [ ] `inventory_adjustments`/`inventory_adjustment_items` exist; inserting one positive and one negative test `qty_delta` produces the expected stock change and a dated variance record, with zero writes to any existing `sale_items` row
- [ ] A test multi-SKU purchase with one shared fee — weight, value, and manual allocation each sum exactly to the original fee, to the kopeck, for all three methods
- [ ] Warehouse cost / Asset cost are queryable via a view/function and match the contract §2 definitions on test data (does not need to match the real NCRM-03 import)
- [ ] `purchase_lot_statuses` gets no new rows; FIFO/valuation excludes `cancelled` and non-stock states from warehouse cost, includes `ordered`+`in_transit` only in asset cost
- [ ] A new `sale_item` on a not-yet-actual sale no longer defaults to `cost_state='actual'`
- [ ] A test sale that hits a FIFO shortage (fallback) produces `cost_state='estimated'`, not `'actual'`, and is distinguishable from clean-FIFO rows
- [ ] `products.is_outlet` exists, defaults `false`, zero existing rows flip to `true` right after the migration
- [ ] NCRM-03's reconciliation numbers (`diagnostics/NCRM-03_*_20260710.md`) are **not** a completion criterion for this task — that's separate future work

## 8. QA / smoke test (owner)
Not checkout/payment/site-schema — `bs-checkout-smoke`/`bs-merchant-schema-qa` not applicable. Naming the risk directly: this changes cost-calculation logic (`fn_fifo_cost_for_product`, `cost_state`) for every future sale in this schema, including the rows NCRM-03 already imported locally.

- [ ] `cd ncrm && npx supabase db reset` — 0 errors
- [ ] `supabase db diff` — empty
- [ ] `\dt+`/`\dv+` (or an equivalent SQL check) — new tables/views present, `0001`-`0005` objects unchanged
- [ ] Manually: one positive and one negative test adjustment → expected stock change, dated variance, no existing `sale_item` touched
- [ ] Manually: one FIFO-shortage scenario → `cost_state='estimated'`, never a silent `actual`/zero
- [ ] If the local NCRM-03 import batch (`ncrm03_20260710`) is still in the DB — row counts and `cost_fixed_at` on existing `sale_items` are unchanged after `db reset` + the new migration
- [ ] `git status` before commit — no `.env`/keys staged
- [ ] Explicitly confirm: no write to the real (cloud) Supabase, live CRM, Apps Script, or OpenCart

## 9. Rollback note
Fully additive, schema-only, no live data involved. Rollback = remove `0006` (and `0007` if used) + `npx supabase db reset` — restores a clean state through `0005`. If already applied to the real (cloud) Supabase before a revert is needed: explicit reverse DDL (drop the new tables/views/functions, restore `sale_items_cost_state_chk` to `provisional`/`actual`) as its own script, matching the rollback files from NCRM-01/03. No external consumers of the new objects exist yet (UI/repo layer untouched), so rollback has no extra dependencies.

## 10. Recommended status after execution
`In progress` until the owner confirms the local run (db reset + the manual test cases in §8). Then → `Done` for NCRM-04. **Does not close NCRM-03** — its DoD (reconciliation within kopeck tolerance) waits on a separate, not-yet-numbered task to re-run the import/reconciliation under this new model.
