# Codex Handoff — NCRM-05: Mystery fulfillment

Date: 2026-07-12 | Parent: NCRM-04 (Done, commit `3c98253`).

## 1. Task ID
NCRM-05 — Mystery fulfillment.

## 2. Context
- Decision source: `plans/NCRM-financial-model-v2_technical-contract_20260711.md` §4 (Mystery Box contract), §7 item 2, §8, plus §2 vocabulary ("Available quantity").
- Blocker cleared: NCRM-04 is Done (commit `3c98253`, `ncrm/supabase/migrations/0006_inventory_ledger_foundation.sql`). Usable objects from it: `products.is_outlet`, `products.weight_g`, `sale_items.cost_state` widened to `pending`/`provisional`/`estimated`/`actual`, `inventory_adjustments`/`inventory_adjustment_items`, `v_inventory_cost_layers`, `v_inventory_consumptions`, `fn_inventory_fifo_layers`, `v_inventory_fifo_valuation`.
- **Gap found during context review, not previously flagged in Notion/dashboard:** contract §7 item 1 lists "reservations" under Inventory foundation scope, but NCRM-04's actual handoff (§4 a–g) and delivered `0006` migration do not implement any reservation mechanism — verified: zero occurrences of "reserv" anywhere in `0001`–`0006`. Contract §2 defines "Available quantity = Physical stock minus active reservations," and §4.3's `reserved` state requires "Components are selected and reduce available quantity only." NCRM-05 inherits this as a hard dependency — a reservation primitive has to be built here, it is not optional scope creep to defer.
- Current Mystery schema (`0003_stage3_mystery_consumables.sql`): `mystery_box_types` (1:1 `product_id` → `expected_pack_count`/`has_holo`/`holo_cost`/`provisional_unit_cost`), `mystery_contents` (already linked to `sale_item_id`, not only `sale_id` — satisfies §4.3's linkage requirement), `auto_consumable_rules`/`consumable_consumptions` (generic conditions `mbox`/`mbox_xl`/`game_pokemon`/`game_onepiece`, not SKU-specific — should keep working under the new SKUs unchanged). No `mystery_fulfillments`/`mystery_fulfillment_items` tables exist yet.
- Generic seeds to retire (contract §4.1): products `MBX` and `MBX-XL` (SKUs literally "MBX"/"MBX-XL", game-agnostic, no `game_code` set), category `mystery_box`, RRC 700/950, `mystery_box_types` rows 5-pack/no-holo/450 mgmt and 7-pack/holo-75/700 mgmt. Replace with 4 SKUs: `PKM-JP-MBX-ST`, `OP-JP-MBX-ST` (RRC 700, mgmt COGS 450, 5 packs, no holo), `PKM-JP-MBX-XL`, `OP-JP-MBX-XL` (RRC 950, mgmt COGS 700, 7 packs + 75 UAH holo).
- Nothing today gates `writeoffs.type = 'MBOX'` to require a linked Mystery fulfillment. Contract §4.3 requires this explicitly ("A generic MBOX writeoff cannot be created through a free-form writeoff form without the linked Mystery fulfillment"). The current path (insert `mystery_contents` referencing a pre-existing `writeoff_item_id`; trigger `fn_refresh_mystery_cogs` fires and flips cost to `actual` once quantity matches `expected_pack_count`) has no atomicity and no gate — it is manual and ungated today.
- No catalogue attribute exists for "sealed-pack product" (§4.2 eligibility condition). `products.category_code` is free text populated by the NCRM-03 import script, no fixed enum in any migration. Same problem class NCRM-04 solved for `is_outlet` ("Outlet is a catalogue attribute, never a SKU-text heuristic") — likely needs an analogous explicit column (e.g. `products.is_sealed_pack`). Unlike `is_outlet`, which only gated a brand-new unused query, this one is functionally required for eligibility to return anything on day one. Flag as an open decision; do not invent a backfill rule or SKU-text heuristic without documenting the choice.
- `products.game_code` / `products.language_code` already exist as FKs (`games`, `product_languages`) — eligibility can filter on those directly, but Codex must verify the actual seeded codes (e.g. `JP`) exist locally before relying on them; they come from the NCRM-03 import, not from any migration file.
- `mystery_eligibility_override` does not exist — new column needed, exclusion-only per §4.2 (default = included; explicit `excluded` value only, no allow-list).
- Legacy `MBX`/`MBX-XL` must not be deleted (contract: "remain available for historical audit") but must stop producing new stock alerts or entering new operational Mystery sales (§8 last bullet) — most likely `is_active = false` (+ `archived_at`), not a delete.

## 3. Goal
One additive migration (`ncrm/supabase/migrations/0007_mystery_fulfillment.sql`, split into `0007`+`0008` if cleaner) on top of `0001`–`0006` that delivers: a reservation primitive, the `mystery_fulfillments`/`mystery_fulfillment_items` state machine (`needs_assembly → reserved → committed`, plus `released`/`reversed`), the automatic JP component-eligibility query, `mystery_eligibility_override`, the 4 new Mystery SKU seeds, retirement of the generic `MBX`/`MBX-XL` seeds, and one atomic RPC for the `Відправлено` commit — without touching `0001`–`0006`, returns, KPI/dashboard views, or any live system.

## 4. What to change (scope)
New file `ncrm/supabase/migrations/0007_mystery_fulfillment.sql` (confirm no `0007+` file already exists before creating).

**a) Reservation primitive** — inherited gap from NCRM-04, in scope here because §4.3's `reserved` state depends on it:
- Minimal structure to represent "components selected, not yet committed, reduce available quantity." Open architectural decision — document the chosen approach in the diagnostic report, same pattern as NCRM-04's adjustment-layer decision.
- Expose a reservation-aware "available quantity" as a new view; do not rewrite `v_stock_alerts` or any existing KPI view.

**b) `mystery_fulfillments` + `mystery_fulfillment_items`:**
- States per §4.3: `needs_assembly`, `reserved`, `committed`, `released`, `reversed`.
- `mystery_fulfillments` keyed to `sale_item_id` (one fulfillment per Mystery `sale_item`, mirroring `mystery_contents.sale_item_id`).
- `mystery_fulfillment_items` — the reserved/committed component selection (`product_id` + `qty`), referencing the reservation rows from (a).
- Gate: a `writeoffs.type = 'MBOX'` row (and `mystery_contents`) can only be created through the commit RPC in (d), not free-form — needs an actual DB-level constraint/trigger, not just an app-layer rule.

**c) Component eligibility query (§4.2):**
- View/function returning eligible components per Mystery SKU: game match, `language_code = 'JP'`, sealed-pack attribute true, `is_outlet = false`, no `mystery_eligibility_override = 'excluded'`, and physically available after reservations from (a).
- Depends on the open sealed-pack decision above — pick the narrowest defensible default and document it; do not invent a SKU-text heuristic.

**d) Atomic commit RPC:**
- Single `security invoker` function tied to the `Відправлено` transition (existing `sales` trigger pattern: `order_status_id` update to the `shipped` code already fires `fn_fix_actual_sale_items` — decide whether to hook the same transition or expose an explicit RPC the app calls; document the choice).
- One transaction: re-check availability under row lock, create `writeoffs` (`type='MBOX'`) header + `writeoff_items`, `mystery_contents` rows (reuse the existing `source='writeoff'` path, already wired to `fn_refresh_mystery_cogs`), holo cost from `mystery_box_types.holo_cost`, `consumable_consumptions` (`source='auto'`, reuse `auto_consumable_rules`), and flip fulfillment state to `committed`.
- ST = exactly 5 packs, XL = exactly 7 packs + fixed 75 UAH holo — validate against `mystery_box_types.expected_pack_count`/`has_holo`/`holo_cost` (already exist); read from that table, do not hardcode 5/7/75 in the RPC.

**e) New SKU seeds + legacy retirement:**
- Insert 4 products: `PKM-JP-MBX-ST`, `OP-JP-MBX-ST` (RRC 700, `mystery_box_types` 5 packs / no holo / mgmt COGS 450), `PKM-JP-MBX-XL`, `OP-JP-MBX-XL` (RRC 950, 7 packs / holo 75 / mgmt COGS 700) — same seed pattern as `0003`'s `MBX`/`MBX-XL` insert, but set `game_code` correctly per SKU this time (the generic seeds never set one).
- `MBX`/`MBX-XL`: `is_active = false` (+ `archived_at = now()`); do not delete. Verify no FK/unique constraint blocks this before assuming it is a one-line update.
- Confirm `v_stock_alerts`'s existing `where p.is_active` filter (0004) already excludes archived legacy SKU from new alerts — do not add duplicate logic if it already holds.

**f) `mystery_eligibility_override`:**
- New column, most likely on `products` — contract §4.2 reads as a global-per-product exception, not per-Mystery-SKU. Confirm this reading before building a side table.

## 5. What NOT to touch
- `ncrm/supabase/migrations/0001`…`0006` — unedited.
- Returns, `refund_items`, COGS reversal — NCRM-06.
- `v_pnl_monthly`, `v_sales_report`, `v_channel_report`, `v_top_skus`, `v_repeat_customers`, `v_below_cost_alert`, `v_stock_alerts` (read-only reference only) — KPI/dashboard redesign is NCRM-07.
- `ncrm/scripts/import-history/*` (NCRM-03) — do not edit or re-run.
- `ncrm/app/*`, `ncrm/lib/repositories/*` — schema-only task, same boundary as NCRM-04, unless something genuinely fails to compile because of this change.
- Live Apps Script CRM, Google Sheet, OpenCart — untouched, separate codebase.
- Standard protected zones (required minimum, not technically relevant here): `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, Product schema.
- Real (cloud) Supabase project — stays a separate owner-driven step, not part of this task's DoD.

## 6. Likely files/areas
- `ncrm/supabase/migrations/0007_mystery_fulfillment.sql` (new; split into `0007`+`0008` if cleaner)
- `diagnostics/NCRM-05_mystery-fulfillment_report_<date>.md` (new, Codex's own report)
- No changes expected in `ncrm/app/`, `ncrm/lib/repositories/*`, or `0001`–`0006` — verify against actual project files before assuming otherwise.

## 7. Acceptance criteria
- [ ] `npx supabase db reset` (local) applies `0001`…`0007` (and `0008` if used) cleanly, exit code 0
- [ ] `git diff` on `0001`…`0006` is empty
- [ ] Each of the 4 Mystery SKU validates: game match, JP language, Outlet exclusion, exact pack count (5 or 7), reservation release on cancel, atomic commit on `Відправлено`
- [ ] No sale, mystery fulfillment, correction, or return produces negative available stock without an explicit blocking error
- [ ] A generic MBOX writeoff cannot be created outside a linked Mystery fulfillment (test: free-form `type='MBOX'` writeoff with no fulfillment must fail)
- [ ] Before commit the sale item stays `provisional`; after commit it carries actual FIFO COGS from components + holo + attributable consumables, `cost_state = 'actual'`
- [ ] Legacy `MBX`/`MBX-XL` create no new stock alerts and cannot enter new operational Mystery sales; both remain queryable for historical rows
- [ ] Cancelling a `reserved` fulfillment before shipment releases the reservation and restores available quantity

## 8. QA / smoke test (owner)
Not checkout/payment/site-schema in the OpenCart sense — `bs-checkout-smoke`/`bs-merchant-schema-qa` not applicable here. Naming the risk directly: this changes inventory-availability and COGS-finalization logic for every future Mystery sale, and retires two SKU currently used to sell real orders. If the `MBX`/`MBX-XL` retirement lands ahead of the front-end/CRM catching up, a live Mystery order could hit an archived SKU — confirm cutover sequencing with the owner before the real Supabase project or any OpenCart-facing piece is touched (both stay out of this task's DoD).

- [ ] `cd ncrm && npx supabase db reset` — 0 errors
- [ ] `supabase db diff --local` — empty
- [ ] Manually: reserve components for one ST and one XL fulfillment, cancel one before commit → reservation released, available quantity restored
- [ ] Manually: commit one ST and one XL fulfillment → MBOX writeoff + items + `mystery_contents` + `consumable_consumptions` created atomically, `cost_state` flips to `actual`, cost matches components + holo + consumables
- [ ] Manually: attempt a free-form `type='MBOX'` writeoff with no fulfillment link → rejected
- [ ] Manually: attempt to reserve more than available (post-reservation) quantity → blocked, no negative stock
- [ ] Confirm `MBX`/`MBX-XL` no longer appear in eligible-for-new-sale queries but remain in historical data
- [ ] `git status` before commit — no `.env`/keys staged
- [ ] Explicitly confirm: no write to the real (cloud) Supabase, live CRM, Apps Script, or OpenCart

## 9. Rollback note
Fully additive, schema-only, no live data involved. Rollback = remove `0007_mystery_fulfillment.sql` (and `0008` if used) + `cd ncrm && npx supabase db reset` — restores a clean state through `0006`. If already applied to the real (cloud) Supabase before a revert is needed: a dedicated reverse-DDL script dropping the new tables/views/functions and re-activating `MBX`/`MBX-XL` if they were archived, as its own script, matching the NCRM-01/03/04 rollback precedent. No UI/repo-layer consumers of the new objects exist yet, so rollback has no extra dependency chain.

## 10. Recommended status after execution
`In progress` until the owner confirms the local run (db reset + the manual test cases in §8), same convention as NCRM-04. Then → `Done`. Does not close NCRM-06/07 — returns and reporting/forecast stay separate, not-yet-started tasks.
