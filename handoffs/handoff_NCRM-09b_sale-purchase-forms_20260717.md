# Codex Handoff — NCRM-09b: Write-форми продаж + закупка

Date: 2026-07-17 | Parent: NCRM-09 ("Write-форми + FIFO-COGS + auth"), split into 09a (Done, owner-confirmed 2026-07-17) / 09b (this task) / 09c (списання/РРЦ/повернення/mystery box, пізніше). See `plans/NCRM-09_write-forms-auth-split_20260717.md`.

## 1. Task ID
NCRM-09b — Write-форми: продаж (sale) і закупка (purchase). Blocker NCRM-09a is Done — auth/session/`created_by` foundation exists and is owner-verified (login, logout, wrong-password handling, route-gate all confirmed working locally).

## 2. Context
- Repo-layer convention (`ncrm/README.md`, NCRM-02/08): UI (`ncrm/app/*`) never queries Supabase directly — only `ncrm/lib/repositories/*.repo.ts` may call the Supabase client.
- **The mutation functions this task needs already exist and already take `createdBy`** (added in NCRM-09a): `lib/repositories/sales.repo.ts` → `addSale(payload)`, `lib/repositories/purchases.repo.ts` → `addPurchase(payload)`. Get the current staff id via `getCurrentStaff()` (`lib/auth/session.ts`, from NCRM-09a) in the server action/route handler that calls these — do not re-derive identity another way.
- FIFO/COGS for **sale** items is fully automatic: triggers `fn_fix_new_sale_item`/`fn_fix_sale_cogs`/`fn_fifo_cost_for_product` (migration `0006`) fire on `sale_items` insert and compute cost layers. The sale form only needs to insert correct rows — verify this against the actual trigger definitions in `0006` before assuming, don't just trust this summary.
- **Purchase cost allocation is not automatic in the same way — read this carefully before building the purchase form.** `purchase_lots` has flat columns (`goods_cost_uah`, `forwarding_fee_uah`, `intl_shipping_uah`, `local_delivery_uah`) that are the canonical values FIFO valuation reads (via `v_purchase_lot_costs`/`v_inventory_cost_layers`). For a purchase with **one lot**, the fee columns are just the fee amounts directly — no allocation needed. For a purchase with **multiple lots sharing one fee** (one order, several SKUs, one forwarding fee for the whole shipment — the normal real-world case per the financial contract §3.2), the flat columns must be filled by calling the existing DB function `public.fn_allocate_purchase_shared_fee(p_purchase_id, p_fee_type, p_allocation_method, p_manual_allocations)` (`0006`, weight/value/manual per §3.2 of `plans/NCRM-financial-model-v2_technical-contract_20260711.md`) — **do not reimplement allocation math in JS/TS.** The DB function writes an audit row per lot into `purchase_lot_fee_allocations` and then updates the flat `purchase_lots` columns itself; call it once per shared fee type (`forwarding_fee`, `intl_shipping`, `local_delivery`) after the lots exist. `goods_cost_uah` is not a shared fee in this scheme — enter it per lot directly (it's already a per-SKU cost, not a shared shipment cost).
- No repo function exists yet to list reference/lookup tables for form dropdowns (`sale_channels`, `payment_types`, `payment_statuses`, `order_statuses`, `post_methods`, `supplier_regions`, `purchase_lot_statuses`) — today these are only ever joined into read queries (`orders.repo.ts`, `sales.repo.ts`), never listed standalone. Both forms need this.
- Currency rates are **manual entry only** — NCRM-11 ("Курси валют", auto-fetch) has not started (`status: 'todo'` on the roadmap). The purchase form's `goodsTotalRate`/`forwardingFeeRate`/etc. fields are plain number inputs the user types in; do not build or assume any rate auto-fetch.
- `in_stock` requiring a received date (`plans/NCRM-financial-model-v2_technical-contract_20260711.md §3.3`) is **not** a hard DB constraint today — it only surfaces as a data-quality flag (`stock_lot_missing_delivery_date`, feeding `v_data_quality`). The form should still nudge for a delivery date when status is `in_stock`/`selling`/`sold`, but this is a soft UX validation, not something the DB will reject — verify against the actual migration before assuming otherwise.
- Product/SKU selection for both forms: `lib/repositories/products.repo.ts` → `listSku()` already exists (from NCRM-08) and returns active products with current RRC — reuse it for the item/lot product picker, don't build a new product list query.
- Access: both forms are gated by NCRM-09a's route middleware already (`owner`/`admin` only, everything else redirects). No new access logic needed in this task.

## 3. Goal
Two working write forms — new sale (with line items) and new purchase (with lots, multi-currency, shared-fee allocation) — that call the existing `addSale`/`addPurchase` repo functions with a real `createdBy`, backed by new reference-lookup repo functions for the dropdown data both forms need. FIFO/COGS itself is not built here — it already exists at the DB layer.

## 4. What to change (scope)
- New `ncrm/lib/domain/reference.ts` + `ncrm/lib/repositories/reference.repo.ts` — read-only list functions for `sale_channels`, `payment_types`, `payment_statuses`, `order_statuses`, `post_methods`, `supplier_regions`, `purchase_lot_statuses` (verify exact column names — expect `id`/`code`/`name_uk` per the existing `mapLookup` pattern in `_utils.ts`, confirm against actual schema before coding). Both forms consume this.
- New `ncrm/app/orders/new/page.tsx` (or wherever fits the existing `/orders` route structure — Codex's call, but keep it discoverable from the orders list) — sale form: header fields (channel, payment type/status, order status, post method, customer name/phone, order no, sold-at date, discount/packaging/delivery) + a repeatable line-item block (product picker via `listSku()`, qty, unit price, per-line allocations). Submit calls `addSale` with `createdBy` from `getCurrentStaff()`.
- New `ncrm/app/purchases/` route (new — no purchase screen exists yet from NCRM-08, which only covered read screens for summary/orders/stock/sku/customers, not a purchases list). Minimum viable: a purchase form (region, supplier, order ref/url, ordered-at, three currency/amount/rate blocks for goods/forwarding/intl-shipping/local-delivery, repeatable lot block with product+qty+goods cost). On submit: `addPurchase` with lots initially carrying `goodsCostUah` per lot and `0` for the three shared-fee columns, then — if more than one lot — call `fn_allocate_purchase_shared_fee` via Supabase RPC once per fee type with the chosen allocation method (weight/value/manual); if exactly one lot, the whole fee can go directly on that lot without the RPC. State clearly in the report which path was implemented and how allocation method is chosen in the UI.
- `ncrm/app/layout.tsx` or the relevant list pages — add discoverable links/buttons to the new forms (e.g. "Новий продаж" on `/orders`, "Нова закупка" wherever the purchase list/form lives). No redesign — match existing tech-demo styling.
- `ncrm/lib/domain/sales.ts`/`purchases.ts` — extend only if the existing `AddSalePayload`/`AddPurchasePayload` shapes are missing something genuinely required by the form (verify first; NCRM-09a already added `createdBy`, don't duplicate).

## 5. What NOT to touch
- No writeoff/RRC/return/mystery-box forms or repo work — NCRM-09c, separate handoff.
- No changes to `addSale`/`addPurchase` beyond what's genuinely required — they already accept `createdBy` from 09a; if the shared-fee-allocation flow needs `addPurchase` itself to change (e.g. to skip inserting zero fee columns and let the RPC do it), make the smallest change and say so explicitly in the report — do not silently redesign the function.
- No changes to `fn_allocate_purchase_shared_fee`, `fn_fix_new_sale_item`, or any other trigger/function in `0006`/`0007`/`0008`/`0009`/`0010` — call them, don't modify them.
- No new migration expected. If the reference-lookup queries or the allocation RPC call genuinely require a schema change (e.g. a missing index, a missing RPC grant for the service role — unlikely since `service_role` bypasses RLS, but verify), stop and flag it rather than adding one silently.
- No currency-rate auto-fetch (NCRM-11, not started, separate task).
- Do not touch `ncrm/middleware.ts`, `ncrm/lib/auth/*`, `ncrm/app/login/*` — auth is done (09a), out of scope here.
- `ncrm/supabase/migrations/*`, `ncrm/scripts/import-history/*`, `ncrm/import/*` — untouched.
- Live Apps Script CRM, Google Sheet, OpenCart — separate codebase, never written to.
- Standard protected zones (not technically present in `ncrm/`, confirm no accidental cross-touch): `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, Product schema.

## 6. Likely files/areas
- New `ncrm/lib/domain/reference.ts`, `ncrm/lib/repositories/reference.repo.ts`
- New `ncrm/app/orders/new/page.tsx` (+ form component)
- New `ncrm/app/purchases/page.tsx` (list, if none exists) + `ncrm/app/purchases/new/page.tsx` (+ form component)
- `ncrm/app/layout.tsx` or list pages — links to new forms
- Possibly small, explicitly-justified changes to `ncrm/lib/repositories/purchases.repo.ts` for the allocation-RPC call path
- `diagnostics/NCRM-09b_sale-purchase-forms_report_<date>.md` (new, Codex's own report)
- No changes expected in `ncrm/supabase/migrations/*`, `ncrm/middleware.ts`, `ncrm/lib/auth/*`, `ncrm/app/login/*` — verify against actual project files before assuming otherwise.

## 7. Acceptance criteria
- [ ] `cd ncrm && npm run build` succeeds, 0 errors
- [ ] `git diff` on `ncrm/supabase/migrations/*` is empty (or Codex stopped and flagged why one was needed)
- [ ] A new sale with 2+ line items can be submitted through the UI; the resulting `sales`/`sale_items` rows have the correct `created_by` (the logged-in staff id) and each `sale_item` picks up a non-`pending`/non-zero cost state from the existing FIFO trigger (verify, don't assume it "just works")
- [ ] A new single-lot purchase can be submitted; `purchase_lots` fee columns match what was entered, `created_by` is set
- [ ] A new multi-lot purchase with a shared forwarding fee can be submitted; after the allocation step, `purchase_lot_fee_allocations` has one row per lot for that fee type and the lots' `forwarding_fee_uah` sum equals the entered total (this is exactly what `fn_assert_purchase_fee_allocation` already checks — a failed insert here means the flow is wired wrong, not that the check is wrong)
- [ ] Both forms are only reachable when logged in as `owner`/`admin` (inherits from NCRM-09a's middleware — spot-check, don't just assume)
- [ ] No file under `ncrm/app/` imports `@/lib/supabase/client` directly — only `ncrm/lib/repositories/*` do (grep-verifiable, same rule as NCRM-08/09a)
- [ ] Report explicitly states: how the multi-lot shared-fee allocation UI works (method chosen where, when the RPC fires), and confirms no allocation math was duplicated in TypeScript

## 8. QA / smoke test (owner)
Not checkout/payment/site-schema — `bs-checkout-smoke`/`bs-merchant-schema-qa`/`bs-seo-risk-gate` not applicable (separate local Next.js/Supabase app, nothing deployed). Real risk to name directly: this is the **first task that writes real inventory/financial rows** through the new CRM UI (09a only handled identity, no business data). A wrong allocation call could silently misstate warehouse cost for every SKU in a shared-fee purchase — that's exactly what `fn_assert_purchase_fee_allocation` is there to catch, but only if the flow actually calls it. **Do not deploy anywhere network-reachable**, same standing rule as NCRM-08/09a.

- [ ] `cd ncrm && npx supabase start && npx supabase db reset` (only if migrations actually changed — confirm first), then `npm run dev`
- [ ] Log in as owner, create a test sale with 1-2 items, confirm it appears on `/orders` with correct totals and a sane cost state (not silently zero)
- [ ] Create a single-lot test purchase, confirm it appears with correct UAH totals
- [ ] Create a multi-lot test purchase sharing one forwarding fee, confirm the allocation sums correctly across lots (spot-check against a hand calculation, e.g. weight-based split)
- [ ] Attempt a purchase with mismatched/incomplete allocation input (if the UI allows it) — confirm it's rejected with a clear error, not a silent partial write
- [ ] `git status` before commit — no `.env`/keys staged
- [ ] Confirm still local-only, nothing deployed publicly

## 9. Rollback note
App/repo-layer only, plus the new reference-lookup module — no schema/migration change expected, no live-system writes. Rollback = revert the new `ncrm/app/orders/new`, `ncrm/app/purchases/*` routes, the new `reference.ts`/`reference.repo.ts`, and any justified small change to `purchases.repo.ts`, via `git`. Any test sale/purchase rows created during QA should be identified and cleaned up manually (`db reset` wipes them along with everything else — confirm with owner before doing that if real imported data is present, per the standing NCRM-03 warning already on the dashboard about `db reset` wiping imported history).

## 10. Recommended status after execution
Stays `In progress` (parent NCRM-09) until: (a) Claude independently reviews the diff, especially the allocation-RPC wiring, (b) owner runs the §8 smoke test — single-lot purchase, multi-lot shared-fee purchase, sale with line items, (c) owner confirms the sums check out against a hand calculation for at least one shared-fee case. Only then does Claude write the NCRM-09c handoff (списання/РРЦ/повернення/mystery box).
