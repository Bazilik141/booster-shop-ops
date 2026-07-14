# Codex Handoff — NCRM-06: Returns + cost quality

Date: 2026-07-14 | Parent: NCRM-04 (Done, commit `3c98253`), NCRM-05 (Done, commit `cb964cb`).

## 1. Task ID
NCRM-06 — Returns and cost quality.

## 2. Context
- Decision source: `plans/NCRM-financial-model-v2_technical-contract_20260711.md` §5 (Returns and COGS quality), §7 item 3, §8, plus §2 vocabulary ("Contribution margin = Net revenue minus management COGS, direct sale costs, and COGS reversals for restocked returns").
- Both parent handoffs explicitly excluded this scope ("`refunds`, `refund_items`, COGS reversal — NCRM-06"), so nothing beyond the placeholder below is pre-built.
- Current `refunds` table (`0002_stage2_sales.sql`) is header-only: `sale_id`, `refund_type` (`partial_money_no_return`/`partial_return`/`full_return`), `amount`, `reason`, `refunded_at`, `restock` (boolean), `note`. No `refund_items`, no link to `sale_item_id`, no per-line condition. It already feeds `v_pnl_monthly` (`refunds_monthly` CTE, `sum(amount)` by month) and `v_sales_report` (`refund_agg`) as a flat monthly subtraction from contribution margin — this crude behavior is exactly what contract §2 is meant to replace, but wiring new data into those views is explicitly **NCRM-07** (KPI/dashboard redesign), not this task.
- Real gap: contract §5.1 needs four distinct per-line outcomes (money-only / resellable / damaged-non-resellable / unopened Mystery), but today's `refunds.restock` is one boolean per refund header — it cannot represent a `partial_return` that mixes a resellable line and a damaged line in the same refund. `refund_items` must carry its own condition, independent of the header.
- `sale_items` already carries frozen cost snapshots (`prro_unit`, `mgmt_unit`, `cost_state`, `cost_method`, `cost_fixed_at`). Contract §3.3: sale COGS snapshots stay immutable after finalization; a return's COGS reversal must be a new record referencing the frozen snapshot, never an edit to the original `sale_item` row.
- A resellable return's "return layer at original cost" (contract §5.1 table) is architecturally the same shape NCRM-04 already built for positive `inventory_adjustment_items`: an additive supply-side row. `v_inventory_cost_layers` / `fn_inventory_fifo_layers` already fold any `layer_scope='warehouse'` source generically (today: `purchase_lot`, `inventory_adjustment`). `refund_items` is very likely a third `source_kind` in that same view, reusing existing FIFO/valuation machinery instead of a parallel one.
- Mystery unopened returns already have a placeholder. `ncrm/supabase/migrations/0007_mystery_fulfillment.sql`'s `fn_guard_mystery_fulfillment_state()` allows `committed → reversed` only when `current_setting('app.mystery_reverse') = 'on'`, with the code comment "Mystery reversal belongs to the NCRM-06 return path." No function sets that flag yet — this task must add the mirror of NCRM-05's `fn_commit_mystery_fulfillment`.
- **Gap found during this context review, not previously flagged:** neither `mystery_contents` nor `writeoff_items` stores a per-component unit cost. At commit time, `fn_refresh_mystery_cogs` (`0003`, unchanged since) calls `fn_fifo_cost_for_product` per component and folds the result straight into the parent `sale_item`'s blended `prro_unit`/`mgmt_unit` (plus holo + consumables) — the individual component's cost is never persisted anywhere after that. "Reverse component COGS" and "restore confirmed components" at original cost (contract §5.1) has no source of truth to read without either (a) adding a cost snapshot to `mystery_contents` at commit time, or (b) recomputing `fn_fifo_cost_for_product` for the historical commit date with the same exclusions. **Open decision — do not invent silently, document the choice in the diagnostic report.** Recommendation: (a), matching the "freeze cost at the moment of the event" principle already used for `sale_items.cost_fixed_at` and NCRM-04's adjustment-item cost snapshots — but this changes `mystery_contents`' shape (additive column via `alter table`, `0007` stays unedited), so confirm before building.
- Contract §5.1 does not say whether holo cost and attributable consumables are reversed on an unopened Mystery return, only "component COGS." **Open decision, flag explicitly for owner sign-off** — do not assume. Narrowest defensible default to propose: components only (holo card physically ships back inside the unopened box, arguably still a component; consumables like packaging were spent regardless of the return and are not typically recoverable).
- `refunds.refund_type` (money-flow classification) and the new `refund_items.condition` (physical/COGS classification) are not the same axis and may not align 1:1 — a `partial_return` can contain one resellable and one damaged line. Keep both fields; do not derive one from the other without documenting the mapping.

## 3. Goal
One additive migration (`ncrm/supabase/migrations/0008_returns_cost_quality.sql`, split into `0008`+`0009` if cleaner) on top of `0001`–`0007` delivering: `refund_items` linked to `refunds` and the original `sale_item`, a per-line condition (`money_only`/`resellable`/`damaged`/`mystery_unopened` at minimum), a resellable-return stock layer at original cost feeding the existing FIFO/valuation views, non-destructive COGS-reversal bookkeeping that never edits a finalized `sale_item`, the Mystery `committed → reversed` reversal path, and additive `v_data_quality` checks — without touching `0001`–`0007`, KPI/dashboard views, or any live system.

## 4. What to change (scope)
New file `ncrm/supabase/migrations/0008_returns_cost_quality.sql` (confirm no `0008+` file already exists before creating).

**a) `refund_items` table:**
- `id`, `refund_id` (FK `refunds`), `sale_item_id` (FK `sale_items` — the contract's "original sale_item" link), `qty` (integer, `> 0`), `condition` (text, check in `('money_only', 'resellable', 'damaged', 'mystery_unopened')` — the minimum distinction required by §5.1; do not extend the taxonomy without flagging), `note`, standard `created_at`/`updated_at` trigger.
- Constraint: the sum of `refund_items.qty` for a given `sale_item_id`, across all its refunds, must never exceed that `sale_items.qty` — a bound, not an exact-sum match (unlike NCRM-04's fee-allocation total). A constraint trigger following the `purchase_lot_fee_allocations_validate_total` pattern (`0006`) is the closest precedent.
- Open decision to document, not invent: should `refunds.restock` stay independent, or become validated against "at least one linked `refund_items.condition = 'resellable'`"? Either is defensible; document the choice.

**b) COGS reversal bookkeeping (resellable + mystery_unopened only):**
- `condition = 'resellable'`: snapshot-reverse the *original* `sale_item`'s own frozen `prro_unit`/`mgmt_unit` × returned qty onto the `refund_items` row (e.g. `prro_reversal_uah`/`mgmt_reversal_uah`, naming at Codex's discretion) — a read-only copy computed once; the `sale_items` row itself is never updated.
- `condition = 'damaged'` / `'money_only'`: no reversal fields populated (or explicitly zero) — original COGS stands, per the contract table.
- `condition = 'mystery_unopened'`: the reversal amount comes from the RPC in (d), not a simple snapshot multiply — the box's frozen `mgmt_unit`/`prro_unit` is already a blend of components + holo + consumables.

**c) Resellable return stock layer (original cost):**
- Extend `v_inventory_cost_layers` / `v_inventory_consumptions` / `fn_inventory_fifo_layers` (`create or replace`, following the `0003`/`0006`/`0007` precedent — `0001`–`0007` stay unedited) with a new `source_kind = 'refund_item'`: a positive warehouse-scope layer, `layer_date = refunds.refunded_at`, quantity = `refund_items.qty`, cost = the frozen original `sale_item.prro_unit`/`mgmt_unit` (not recomputed FIFO) — this is contract §5.1's "Return layer at original cost" literally.
- Only `condition = 'resellable'` produces this layer. `damaged`/`money_only` do not; `mystery_unopened` restoration is a separate mechanism (see (d)), not this layer.
- Verify `fn_fifo_cost_for_product`'s existing prior-consumption skip-forward logic (prior sales/writeoffs/adjustments) still holds once a fourth event source exists; add a prior-refund-layer term analogous to `v_prior_adjustments` if the ordering requires it — do not silently break FIFO sequencing.

**d) Mystery unopened-return path:**
- New function (name at Codex's discretion, e.g. `fn_reverse_mystery_fulfillment(p_sale_item_id uuid, p_refund_id uuid)`), mirroring `fn_commit_mystery_fulfillment`'s transaction shape:
  - Verify `mystery_fulfillments.state = 'committed'` for the sale item, and that the sale/order is in a state consistent with the existing cancel/refund guards (`fn_release_mystery_fulfillment` precedent).
  - `perform set_config('app.mystery_reverse', 'on', true);` then `update mystery_fulfillments set state = 'reversed', reversed_at = now()` — the `0007` guard trigger already expects exactly this flag and transition.
  - For each `mystery_contents` row of the sale item: create a new stock-supply record restoring the component (reuse the `source_kind = 'refund_item'` layer mechanism from (c), or a dedicated `source_kind` if a distinct label is clearer — document the choice), at the cost basis resolved per the open decision in Context (either the new `mystery_contents` cost snapshot, or a recompute).
  - Create the `refund_items` row for this reversal (`condition = 'mystery_unopened'`), linked to the box's own `sale_item_id`.
  - Do not touch `writeoff_items`/`mystery_contents` rows themselves — they stay immutable audit records, same principle as NCRM-05's insert-only guard on `mystery_contents`.
  - Holo cost / consumables reversal: implement per the narrowest default flagged in Context, and call it out explicitly in the diagnostic report as an open question for owner sign-off.

**e) `v_data_quality` (additive branch only, `create or replace`, `0004`/`0006` precedent):**
- At minimum: a `refund` with zero linked `refund_items` (orphan header); `refund_items.qty` summed per `sale_item_id` exceeding the original `sale_items.qty`; a `mystery_unopened` `refund_items` row whose fulfillment never reached `state = 'reversed'`.
- Append only — do not rewrite the five existing checks.

**f) Standard scaffolding:**
- `created_at`/`updated_at` + `set_updated_at` trigger on every new table, matching every existing table in this schema.

## 5. What NOT to touch
- `ncrm/supabase/migrations/0001`…`0007` — unedited (new objects/`alter`/`create or replace` go in the new file, per the `0003`/`0006`/`0007` precedent).
- `v_pnl_monthly`, `v_sales_report`, `v_channel_report`, `v_top_skus`, `v_repeat_customers`, `v_below_cost_alert`, `v_stock_alerts` — no redesign; wiring reversed COGS into reporting is **NCRM-07**.
- `ncrm/scripts/import-history/*` (NCRM-03) — do not edit or re-run.
- `ncrm/app/*`, `ncrm/lib/repositories/*` (NCRM-02) — schema-only task, same boundary as NCRM-04/05, unless something genuinely fails to compile because of this change.
- `mystery_fulfillments`/`mystery_fulfillment_items`/`inventory_reservations` table DDL from `0007` — extend behavior only via new triggers/functions in the new file; if a column must be added, use `alter table` in the new file only.
- Live Apps Script CRM, Google Sheet, OpenCart — untouched, separate codebase.
- Standard protected zones (required minimum, not technically relevant here): `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, Product schema.
- The real (cloud) Supabase project — applying this migration there stays a separate owner-driven step, not part of this task's Definition of Done.

## 6. Likely files/areas
- `ncrm/supabase/migrations/0008_returns_cost_quality.sql` (new; split into `0008`+`0009` if cleaner)
- `diagnostics/NCRM-06_returns-cost-quality_report_<date>.md` (new, Codex's own report — must explicitly document every open decision flagged in Context: mystery cost-snapshot mechanism, holo/consumables reversal scope, `restock` vs. `condition` consistency)
- No changes expected in `ncrm/app/`, `ncrm/lib/repositories/*`, or `ncrm/supabase/migrations/0001`–`0007` — verify against actual project files before assuming otherwise.

## 7. Acceptance criteria
- [ ] `npx supabase db reset` (local) applies `0001`…`0008` (and `0009` if used) cleanly, exit code 0
- [ ] `git diff` on `0001`…`0007` is empty
- [ ] `refund_items` exists, FK'd to `refunds` and `sale_items`, `condition` restricted to the four values, `qty > 0`
- [ ] Sum of `refund_items.qty` per `sale_item_id` never exceeds the original `sale_items.qty` — blocking error on violation
- [ ] A `resellable` refund item creates a new warehouse-scope stock layer at the sale item's original frozen cost; a `damaged` or `money_only` refund item creates none
- [ ] No refund path ever updates an existing `sale_items` row's `prro_unit`/`mgmt_unit`/`cost_state`/`cost_fixed_at`
- [ ] A `committed` Mystery fulfillment transitions to `reversed` only through the new path; each of its `mystery_contents` rows produces a restored stock layer
- [ ] `v_data_quality` gains the new checks additively; the five existing checks are unchanged
- [ ] `0001`–`0007` files are byte-identical (`git diff` empty)
- [ ] Not a completion criterion for this task: rewiring `v_pnl_monthly`/`v_sales_report` to actually subtract reversed COGS — that is NCRM-07

## 8. QA / smoke test (owner)
Not checkout/payment/site-schema in the OpenCart sense — `bs-checkout-smoke`/`bs-merchant-schema-qa` not applicable (schema-only Supabase task, no live system touched). Naming the risk directly: this is the first NCRM task that lets a finalized sale be partially reversed after the fact. A wrong reversal amount or a resellable layer created at the wrong cost silently corrupts every downstream FIFO valuation and P&L number from that date forward, and a Mystery reversal that fails to restore its components silently loses stock. Given the open cost-snapshot decision in Context, review the diagnostic report before this is ever run against real data.

- [ ] `cd ncrm && npx supabase db reset` — 0 errors
- [ ] `supabase db diff --local` — empty
- [ ] Manually: one money-only refund → no stock layer, no reversal record, `sale_item` untouched
- [ ] Manually: one resellable refund on a non-Mystery `sale_item` → new stock layer at original cost, reversal recorded, `sale_item` untouched, warehouse valuation increases by exactly returned qty × original unit cost
- [ ] Manually: one damaged refund → no stock layer, no reversal, `sale_item` untouched
- [ ] Manually: one `committed` Mystery fulfillment reversed unopened → fulfillment `state = 'reversed'`, every `mystery_contents` component restored as a stock layer, the sale item's own frozen cost fields unchanged
- [ ] Manually: attempt to over-refund a `sale_item` beyond its original qty → blocked
- [ ] `git status` before commit — no `.env`/keys staged
- [ ] Explicitly confirm: no write to the real (cloud) Supabase, live CRM, Apps Script, or OpenCart

## 9. Rollback note
Fully additive, schema-only, no live data involved. Rollback = remove `0008_returns_cost_quality.sql` (and `0009` if used) + `cd ncrm && npx supabase db reset` — restores a clean state through `0007`. If already applied to the real (cloud) Supabase before a revert is needed: a dedicated reverse-DDL script dropping the new tables/views/functions, matching the rollback precedent from NCRM-01/03/04/05. No UI/repo-layer consumers of the new objects exist yet, so rollback has no extra dependency chain.

## 10. Recommended status after execution
`In progress` until the owner confirms the local run (`db reset` + the manual test cases in §8) and signs off on the two open decisions flagged in Context (Mystery cost-snapshot mechanism; holo/consumables reversal scope). Then → `Done`. Does not close **NCRM-07** (reporting/forecast + KPI views still separate, not-yet-started) nor re-open **NCRM-03**'s reconciliation.
