# Codex Handoff — NCRM-08: Read-екрани (summary/замовлення/склад/SKU/клієнти)

Date: 2026-07-16 | Parent: NCRM-04 (Done, commit `3c98253`), NCRM-02 (Done — repo-layer skeleton this task extends).

## 1. Task ID
NCRM-08 — Read-екрани (summary/замовлення/склад/SKU/клієнти). Blocker NCRM-04 is Done, task unblocked. Notion status: `Not started` → `In progress` (flipped this session, 2026-07-16).

## 2. Context
- Repo-layer convention is already established (NCRM-02, `ncrm/README.md`): UI (`ncrm/app/*`) never queries Supabase directly — only `ncrm/lib/repositories/*.repo.ts` may call the Supabase client (`ncrm/lib/supabase/client.ts`), returning typed domain objects (`ncrm/lib/domain/*.ts`).
- Today there is exactly one route, `ncrm/app/page.tsx`, explicitly labelled "NCRM-02 · Supabase read demo" — a technical skeleton, not the real dashboard. It uses `analytics.repo.ts`'s `getSummary()`/`getStock()`/`getSkuMetrics()`, each capped at 5 rows for the teaser.
- Schema moved a lot since NCRM-02 (`0006`–`0009`) and the repo layer has not caught up:
  - `v_pnl_monthly` (reworked in `0009`/NCRM-07) now exposes `net_revenue`, `prro_gross_profit`, `cogs_reversals`, `inventory_adjustment_impact` in addition to the original columns. `analytics.repo.ts`'s `mapPnlMonth` only reads the pre-`0009` columns — it doesn't error, but the new v2 KPI figures are invisible today.
  - New views with zero consumers anywhere in the app: `v_inventory_available`, `v_inventory_fifo_valuation` (`0006`/`0007`), `v_current_rrc` (replaced in `0009`, now filtered to `price_kind = 'manual'`), `v_cost_quality_exposure`, `v_unpriced_inventory`, `v_forecast_margin`, `v_inventory_dashboard_guardrails` (all `0009`).
- No per-order view exists. `sales`/`sale_items` are raw tables; `v_sale_item_financials` is per line-item, not per order. An orders screen needs a repo-layer query joining `sales` + `sale_items` + `v_sale_item_financials` + the reference tables (`order_statuses`, `payment_statuses`, `payment_types`, `post_methods`, `sale_channels`) — no schema change required, this is a new query, not a new view.
- There is no `customers` table — customer identity is only `sales.customer_phone`/`customer_name`. The one existing customer-shaped object, `v_repeat_customers` (`0004`), deliberately filters `having count(*) > 1` and excludes one-time buyers. A "клієнти" screen that only shows repeat customers would misrepresent the customer base — build the base list as a repo-layer aggregation over `sales` (group by normalized phone, same normalization `v_repeat_customers` uses: `regexp_replace(phone, '[^0-9+]', '', 'g')`), including count = 1 rows. **Flag, do not silently resolve:** whether this becomes a new SQL view or stays a repo-layer query is a real design choice — recommend repo-layer query to keep this task additive-only (no migration), but state the choice made in the report.
- `v_top_skus` inner-joins `sale_items` and filters `sold_at >= current_date - 29` — it only contains products sold in the trailing 30 days. **Do not build the SKU catalogue screen from `v_top_skus` alone** — it would silently hide every slow-moving or unsold SKU. Base the SKU list on `products` (or `v_stock_alerts`, which already left-joins from all `is_active` products), then left-join `v_current_rrc`, `v_inventory_fifo_valuation`, and `v_top_skus` (nullable) for performance data.
- **Open scope question — flag, do not silently resolve.** `plans/NCRM-financial-model-v2_technical-contract_20260711.md` §10 defers "admin/user permission grants, login UI, real per-role RLS policies, the application-layer permission-check helper" to "NCRM-08/09" without assigning which item belongs to which card. `staff`/`staff_permission_overrides` (NCRM-07b) are schema-only scaffolding — no Supabase Auth integration, no login page, no session handling exists anywhere in `ncrm/` today. This handoff does **not** resolve that split. Recommendation stated in Risks below: ship these read screens with no auth (matching today's `service_role`-only, RLS-deny-by-default posture) but do not deploy them anywhere network-reachable until the owner decides the split.

## 3. Goal
Replace the NCRM-02 tech-demo home page with real, non-demo PC-web read screens — summary/dashboard, orders, stock/warehouse, SKU catalogue, customers — reading exclusively through `ncrm/lib/repositories/*`, wired to the full post-`0009` schema. No new migration. No write/mutation paths anywhere (that's NCRM-09). No auth/login implementation — local-dev only (`npm run dev`) until the owner resolves the NCRM-08/09 auth-split question above.

## 4. What to change (scope)
- `ncrm/lib/domain/analytics.ts` — extend `PnlMonth` with `netRevenue`, `prroGrossProfit`, `cogsReversals`, `inventoryAdjustmentImpact` (map the net-new `v_pnl_monthly` columns from `0009`). Add types for `CostQualityExposure` (`v_cost_quality_exposure`), `UnpricedInventoryRow` (`v_unpriced_inventory`), `ForecastMarginRow` (`v_forecast_margin`), `DashboardGuardrails` (`v_inventory_dashboard_guardrails`).
- `ncrm/lib/repositories/analytics.repo.ts` — extend `mapPnlMonth` for the new columns; add `getCostQualityExposure()`, `getUnpricedInventory()`, `getForecastMargin()`, `getDashboardGuardrails()`, following the existing `getStock`/`getSkuMetrics` pattern (typed `Tables<"v_...">`, throw via `repositoryError` on failure).
- New `ncrm/lib/domain/orders.ts` + `ncrm/lib/repositories/orders.repo.ts` — `getOrders({ status?, limit?, offset? })` (join `sales`+`sale_items`+`v_sale_item_financials`+reference tables) and `getOrderById(id)` (line items + any `refunds`/`refund_items`).
- New `ncrm/lib/domain/customers.ts` + `ncrm/lib/repositories/customers.repo.ts` — `getCustomers({ limit?, offset? })`: aggregate `sales` by normalized `customer_phone`, return order_count/first_order_at/last_order_at/lifetime_revenue for **every** customer, not just repeat ones. May reuse `v_repeat_customers` purely to flag `isRepeat`, but the base list must not be limited to it.
- New SKU catalogue query (extend `analytics.ts`/`analytics.repo.ts` or add `sku.ts`) — `getSkuCatalog({ limit?, offset?, search? })` based on `products`/`v_stock_alerts` (full active catalogue), left-joined with `v_current_rrc`, `v_inventory_fifo_valuation`, and `v_top_skus` (nullable — many rows will have no 30-day sales).
- `ncrm/app/page.tsx` — replace demo content with the real summary screen (KPI cards from extended `getSummary()` + `getDashboardGuardrails()` + `getUnpricedInventory()` count/value + `getCostQualityExposure()`), keep `dynamic = "force-dynamic"` and the existing try/catch "Supabase not configured" fallback pattern.
- New route `ncrm/app/orders/page.tsx` (+ `ncrm/app/orders/[id]/page.tsx` if a detail view is included) — orders list.
- New route `ncrm/app/stock/page.tsx` — full warehouse/stock-alerts list (not the 5-row homepage teaser).
- New route `ncrm/app/sku/page.tsx` (+ `[id]` optional) — SKU catalogue.
- New route `ncrm/app/customers/page.tsx` — customers list.
- `ncrm/app/layout.tsx` — minimal shared nav linking the five screens. Styling stays at the existing tech-demo level; this is not a design task.

## 5. What NOT to touch
- `ncrm/supabase/migrations/0001`–`0010` — no schema changes. This task is app/repo-layer only. If a screen genuinely cannot be built without a new view or column, stop and flag it in the report rather than adding a migration silently.
- Auth/login/session implementation, `staff`/`staff_permission_overrides` wiring, any per-role RLS policy — explicitly out of scope (open owner decision, see §2/Risks). Do not add a login page as part of this task.
- Any write/mutation path — no create/edit/delete forms, no `insert`/`update`/`delete`/`upsert` calls anywhere in these screens or their repo functions. That is NCRM-09.
- `ncrm/scripts/import-history/*`, `ncrm/import/*` — NCRM-03 territory, untouched.
- Live Apps Script CRM, Google Sheet, OpenCart — separate codebase, never written to by this task.
- Standard protected zones (required minimum, not technically present in `ncrm/`): `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, Product schema — confirm no accidental cross-touch outside `ncrm/`.

## 6. Likely files/areas
- `ncrm/lib/domain/analytics.ts`, new `orders.ts`, new `customers.ts`, `sku.ts` or extended `analytics.ts`
- `ncrm/lib/repositories/analytics.repo.ts`, new `orders.repo.ts`, new `customers.repo.ts`
- `ncrm/app/page.tsx`, new `ncrm/app/orders/page.tsx`, new `ncrm/app/stock/page.tsx`, new `ncrm/app/sku/page.tsx`, new `ncrm/app/customers/page.tsx`, `ncrm/app/layout.tsx`
- `diagnostics/NCRM-08_read-screens_report_<date>.md` (new, Codex's own report — must state the customers/SKU-catalogue query design choice from §2 explicitly)
- No changes expected in `ncrm/supabase/migrations/*`, `ncrm/scripts/*`, `ncrm/import/*` — verify against actual project files before assuming otherwise.

## 7. Acceptance criteria
- [ ] `cd ncrm && npm run build` succeeds, 0 errors
- [ ] `git diff` on `ncrm/supabase/migrations/*` is empty
- [ ] `/` shows real summary/KPI data via the extended repo functions, not the NCRM-02 "read demo" copy
- [ ] `/orders` lists real `sales` rows with human-readable status/payment/channel labels (not raw UUIDs)
- [ ] `/stock` lists all active products from `v_stock_alerts` (not limited to 5)
- [ ] `/sku` lists **all** active products (count matches `products` table), including SKUs with zero sales in the trailing 30 days — verify at least one such SKU appears
- [ ] `/customers` includes at least one customer with exactly one order (not just repeat customers) — verify against a known one-time-buyer phone
- [ ] No file under `ncrm/app/` imports `@/lib/supabase/client` directly — only `ncrm/lib/repositories/*` do (grep-verifiable)
- [ ] No `insert`/`update`/`delete`/`upsert` call anywhere in the new/changed files
- [ ] Report explicitly states: whether the customers query and SKU catalogue query are views or repo-layer aggregations, and confirms no new migration was added

## 8. QA / smoke test (owner)
Not checkout/payment/site-schema in the OpenCart sense — `bs-checkout-smoke`/`bs-merchant-schema-qa` not applicable (separate local Next.js/Supabase app, nothing deployed to the live site). Real risk to name directly: these are the first screens ever built that expose full financial data (revenue, margin, COGS, RRC) end-to-end, and the app has **no authentication** — RLS deny-by-default doesn't help here because the app always connects via `service_role`, which bypasses RLS entirely. **Do not deploy this anywhere network-reachable** (Vercel, a public URL, a shared machine, etc.) until the owner has decided the NCRM-08/09 auth-split question flagged in §2. Keep it `npm run dev`/localhost-only for now.

- [ ] `cd ncrm && npx supabase start && npx supabase db reset`, then `npm run dev` — every new route loads with no runtime Supabase error
- [ ] Manually open `/orders`, `/stock`, `/sku`, `/customers` — spot-check 2-3 rows against known data (e.g. one order's total vs. the Sheet/legacy CRM for the same `order_no`)
- [ ] Confirm `/customers` shows a one-time buyer, not only repeat customers
- [ ] Confirm `/sku` shows a product with no recent sales, not silently dropped
- [ ] `git status` before commit — no `.env`/keys staged
- [ ] Explicitly confirm: no write to the real (cloud) Supabase, live CRM, Apps Script, or OpenCart; nothing deployed publicly

## 9. Rollback note
App/repo-layer only — no schema/migration changes, no live-system writes. Rollback = revert/remove the new `ncrm/app/{orders,stock,sku,customers}` routes and the new/extended repo+domain files; `ncrm/app/page.tsx` reverts to the NCRM-02 demo version via `git`. No downstream dependents yet (NCRM-09 write forms not started). No cloud Supabase involved.

## 10. Recommended status after execution
Stays `In progress` until: (a) owner reviews the report's stated design choice for the customers/SKU-catalogue queries, (b) owner explicitly decides the NCRM-08/09 auth/login split flagged in §2/§8 — this task should not be marked `Done` while the app remains permanently no-auth by default, (c) owner smoke-tests the four new screens locally per §8. Then → `Done`. Does not start NCRM-09 (write forms/FIFO-COGS UI) and does not resolve the `staff`/RLS auth model (separate owner decision, contract §10).
