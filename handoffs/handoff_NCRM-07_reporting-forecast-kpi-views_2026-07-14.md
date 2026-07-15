# Codex Handoff — NCRM-07: Reporting/forecast + KPI views (incl. former NCRM-06)

Date: 2026-07-14 | Parent: NCRM-04 (Done, commit `3c98253`), NCRM-05 (Done, commit `cb964cb`), NCRM-06 (Done, commits `0cd78bd` + `4e4a0e6`).

## 1. Task ID
NCRM-07 — Reporting/forecast + KPI views. All three Notion blockers (NCRM-04, NCRM-05, NCRM-06) are now Done, so this task is unblocked and ready to start (Notion status: Not started → In progress as of this handoff).

## 2. Context
- Decision source: `plans/NCRM-financial-model-v2_technical-contract_20260711.md` §2 (vocabulary), §6 (forecast + dashboard rules), §7 item 4, §8. Also folds in the merged-away NCRM-06 scope note: expenses-ledger opex/capitalized split (already built, `0004`), reworked `v_pnl_monthly`/KPI views, forecast margin, dashboard guardrails.
- Every consumer this task needs already exists as of `0008`:
  - `v_sale_item_financials` (`0002`) — per-line `revenue`, `prro_cogs`, `mgmt_cogs`, `gross_profit` (= revenue − prro_cogs, i.e. **already** contract's "PRRO gross profit" shape), `net_profit` (= revenue − mgmt_cogs − direct costs, the *old* contribution-margin shape, pre-return-reversal).
  - `refund_items.prro_reversal_uah` / `mgmt_reversal_uah` (`0008`) — per-line frozen COGS reversal amount, `0` for `money_only`/`damaged`, populated for `resellable`/`mystery_unopened`. This is the literal source for contract §2's "COGS reversals for restocked returns."
  - `v_inventory_adjustment_pnl` (`0006`) — signed `mgmt_variance_uah` per adjustment date/product, **already restricted** to `adjustment_kind = 'operating_correction'` (`opening_balance` rows excluded) and its own comment says *"deliberately not joined into v_pnl_monthly until NCRM-07."* This is the exact "inventory adjustment impact" line.
  - `v_inventory_available` (`0007`) — `physical_qty`, `reserved_qty`, `available_qty` per product.
  - `v_current_rrc` (`0001`) — latest `product_prices` row per product where `effective_from <= current_date`.
  - `app_config` / `v_current_app_config` (`0001`) — the existing effective-dated key/value pattern (already used for `stock_alert_qty`); reuse this for the 5% forecast discount reserve instead of a new table.
  - `sale_items.cost_state` (`pending`/`provisional`/`estimated`/`actual`, `0006`) — source for the provisional/estimated exposure split.
- **Gap found in this context review — flag, do not silently resolve.** `product_prices.source` is free text (`0001`), not a constrained enum, and current seed rows use values like `'NCRM-01 confirmed seed'`. There is no `dynamic_rrc` field anywhere in the Supabase schema. `plans/crm-financial-model_2026-06-26.md` §P2 confirms the *legacy* Sheet kept manual RRC and `dynamic_rrc` as two separate fields and always computed profit off manual RRC only — contract §6.1 restates the same rule ("Manual RRC remains the canonical forecast price. Dynamic RRC is informational only."). Today `product_prices` only ever receives what is functionally the manual RRC (no dynamic-RRC import path exists), so `v_current_rrc` is safe to use as-is *for now*. But nothing in the schema stops a future insert from mixing a dynamic value into the same table under a different `source` string and silently corrupting the forecast. Recommendation: add a `price_kind` (or reuse `source` with an enforced check) in this migration if forecast correctness depends on it, or explicitly document that `product_prices` is manual-only by contract and defer the constraint. Do not invent a dynamic-RRC import — none exists yet.
- **Open arithmetic question — flag, do not silently resolve.** Contract §2 defines contribution margin as *"Net revenue minus management COGS, direct sale costs, and COGS reversals for restocked returns."* Read literally that subtracts the reversal amount a third time, which is economically backward — a resellable return that restores stock and reverses COGS should *increase* contribution margin in the return's period, not decrease it further. Recommended interpretation (state explicitly in the report, do not assume silently): `contribution_margin = net_revenue − management_cogs − direct_sale_costs + cogs_reversals`, where `cogs_reversals` is the period sum of `refund_items.mgmt_reversal_uah` for refunds dated in that period. This matches contract §3.3 (reversal is a new financial document dated at the return event, original sale snapshot untouched) and §5.1's return table (resellable "COGS: Reverse original COGS for returned quantity").
- Current `v_pnl_monthly` (`0004`) subtracts `refunds.amount` a second time, after already computing `contribution_margin` from `v_sale_item_financials.net_profit` — under the new model that double-subtraction moves earlier: `refunds.amount` becomes part of **net revenue** (`revenue − monetary refunds`), not a second deduction after contribution margin. Verify the net numeric result changes correctly for a test month with a `money_only` refund (should net out to the same total as before) versus a `resellable` refund (should now differ, because COGS reversal is new).
- Old `v_pnl_monthly`/`v_sales_report`/`v_channel_report`/`v_top_skus`/`v_below_cost_alert` all key off `v_sale_item_financials` and `fn_is_actual_sale`; none of them currently reference `inventory_adjustments`, `refund_items`, or `v_inventory_available`. This task is exactly wiring those three in, per the code comments left by NCRM-04/06 pointing here.
- No UI exists yet (`NCRM-08` "Read screens" is Not started) — contract §2's "UI must not expose an unqualified `Прибуток` label" is not directly actionable in this schema-only task. Name new/reworked view columns unambiguously (`contribution_margin`, `true_net_profit`, never a bare `profit`) so NCRM-08 inherits the correct labels for free; do not build UI here.

## 3. Goal
One additive migration (`ncrm/supabase/migrations/0009_reporting_forecast_kpi.sql`, split further only if genuinely cleaner) on top of `0001`–`0008` delivering: a reworked `v_pnl_monthly` (net revenue, PRRO gross profit, contribution margin incl. return COGS reversals, operating expenses, inventory adjustment impact, true net P&L), a cost-quality exposure view (provisional/estimated COGS, quantity and value, separate from actual), an unpriced-inventory view (products with no current manual RRC — never treated as forecast profit `0`), a forecast-margin view (manual RRC × available_qty − effective-dated 5% discount reserve, per contract §6.1's exact formula), and the dashboard-guardrail figures from contract §6.2 — without touching `0001`–`0008`, without any UI/app work, and without any cloud Supabase / live CRM / Apps Script / OpenCart write.

## 4. What to change (scope)
New file `ncrm/supabase/migrations/0009_reporting_forecast_kpi.sql` (confirm no `0009+` file already exists before creating).

**a) Effective-dated forecast config:**
- Insert one `app_config` row: `key = 'forecast_discount_reserve_pct'`, `value_num = 5`, `unit = '%'`, `effective_from` = a fixed past/seed date (not `current_date`, to keep the migration idempotent across `db reset` runs — follow the `0004` `stock_alert_qty` precedent for exact date choice), `description` referencing contract §6.1 "reserve under future discounts," `is_active = true`.
- Every forecast view must read this key via `v_current_app_config`, never hardcode `5`/`0.05` inline.

**b) `v_pnl_monthly` rework (`create or replace view`, `0004`'s shape as the base):**
- Add `net_revenue` = `revenue − refunds.amount` (monthly sum), replacing the current post-margin refund subtraction.
- Add `prro_gross_profit` = monthly sum of `v_sale_item_financials.gross_profit` (already the right formula — just not currently exposed at this grain).
- Add `cogs_reversals` = monthly sum of `refund_items.mgmt_reversal_uah` joined through `refunds.refunded_at` for the period (per the recommended interpretation in Context — implement explicitly, do not guess a different sign silently).
- Recompute `contribution_margin` = `net_revenue − mgmt_cogs − direct_sale_costs + cogs_reversals`.
- Add `inventory_adjustment_impact` = monthly sum of `v_inventory_adjustment_pnl.mgmt_variance_uah` **filtered to `is_operating_pnl = true`** (opening-balance rows must stay excluded, matching the `0006` comment).
- Recompute `true_net_profit` = `contribution_margin − operating_expenses + inventory_adjustment_impact` (no second refund subtraction — refunds already left the model via `net_revenue`).
- Decide and document the `margin_pct` denominator (`revenue` vs `net_revenue`) — either is defensible, state the choice in the report.
- Keep the existing `revenue`, `cogs`, `direct_sale_costs`, `operating_expenses` columns; add rather than remove where the contract doesn't explicitly require removal, so nothing already consuming this view breaks silently (nothing does yet — no UI — but keep it additive on principle, matching `0006`/`0008` precedent).

**c) Cost-quality exposure (new view, name at Codex's discretion, e.g. `v_cost_quality_exposure`):**
- Per month (or per current snapshot — pick one, document it): quantity and revenue/COGS value of `sale_items` where `cost_state in ('provisional', 'estimated')`, broken out separately from `actual`. Source: `sale_items.cost_state` + `v_sale_item_financials`. Contract §5.2 / §6.2: "No `actual` sales COGS is zero or fallback-derived; estimated and provisional exposure is visible in the dashboard."

**d) Unpriced inventory (new view, e.g. `v_unpriced_inventory`):**
- Products present in `v_inventory_available` (`available_qty > 0` or `asset_qty > 0` — pick the base, document it) with **no** matching row in `v_current_rrc`. Report count and asset/warehouse cost value (from `v_inventory_fifo_valuation`) for this set. Contract §6.1: "Items without manual RRC are not treated as forecast profit `0`. They appear as a separate `unpriced inventory` count and value."

**e) Forecast margin (new view, e.g. `v_forecast_margin`):**
- Implement contract §6.1's formula literally, per product (join `v_inventory_available.available_qty` × `v_current_rrc.rrc`), reading the 5% reserve from `v_current_app_config` (per (a)):
  ```
  forecast_revenue_before_reserve = manual_RRC * available_qty
  expected_discount_amount = forecast_revenue_before_reserve * expected_discount_pct
  forecast_net_revenue = forecast_revenue_before_reserve - expected_discount_amount
  forecast_margin = forecast_net_revenue - management_inventory_cost
  ```
  `management_inventory_cost` = the product's `mgmt` cost from `v_inventory_fifo_valuation` (warehouse or asset scope — pick one and document; contract §2 defines "Asset cost" as including ordered/in-transit, "Warehouse cost" as physical only — forecast should almost certainly use warehouse/available stock, not in-transit goods that aren't sellable yet; confirm this reading in the report). Only include products present in `v_current_rrc` — unpriced products are (d)'s responsibility, not this view's.
- This view is forecast-only. It must never be summed into `v_pnl_monthly`'s actual figures.

**f) Dashboard guardrail figures (contract §6.2) — confirm each is queryable from existing or new objects, do not skip any:**
  - warehouse cost, asset cost, physical/reserved/available qty → `v_inventory_fifo_valuation` + `v_inventory_available` (exist, no change needed — expose via a consolidated view if that's cleaner for a future dashboard consumer, Codex's discretion).
  - revenue, net revenue, PRRO gross profit, contribution margin, true net P&L → `v_pnl_monthly` (b).
  - inventory adjustment impact for the selected period → `v_pnl_monthly` (b) or `v_inventory_adjustment_pnl` directly.
  - provisional and estimated COGS exposure → (c).
  - unpriced inventory value → (d).
  - below-cost actual sales → `v_below_cost_alert` (`0004`) already exists; verify it still reads correctly against current `v_sale_item_financials`, no rework expected.
  - forecast margin after the 5% discount reserve → (e).

**g) `v_data_quality` (additive branch only, `create or replace`, `0004`/`0006`/`0008` precedent):**
- Consider adding: an `actual`-state sale item whose COGS reversal math would make period contribution margin negative in a way that signals a data error (optional, only if cheap); a `resellable`/`mystery_unopened` `refund_item` whose reversal is `0` (should not normally happen once (b) exists, may indicate a legacy pre-`0008` row). Keep additive — do not rewrite the existing checks.

## 5. What NOT to touch
- `ncrm/supabase/migrations/0001`…`0008` — unedited (new objects/`create or replace` go in the new file, per the `0003`/`0006`/`0007`/`0008` precedent).
- `ncrm/app/*`, `ncrm/lib/repositories/*` — no UI, no app code; NCRM-08 (read screens) is a separate, not-yet-started task that will consume these views later.
- `ncrm/scripts/import-history/*`, anything under `ncrm/import/` — NCRM-03 (re-import/reconciliation) is explicitly sequenced *after* this task (contract §7 item 5) and untouched here.
- `refund_items`, `mystery_return_components`, `inventory_reservations`, `inventory_adjustments` table DDL — read-only consumers here; if a genuinely new column is needed, it goes via `alter table` in the new file only, and must be flagged as a deviation from "reporting-only" scope before assuming it's fine.
- Live Apps Script CRM, Google Sheet, OpenCart — untouched, separate codebase.
- Standard protected zones (required minimum, not technically relevant here): `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, Product schema.
- The real (cloud) Supabase project — applying this migration there stays a separate owner-driven step, not part of this task's Definition of Done.

## 6. Likely files/areas
- `ncrm/supabase/migrations/0009_reporting_forecast_kpi.sql` (new)
- `diagnostics/NCRM-07_reporting-forecast-kpi_report_<date>.md` (new, Codex's own report — must explicitly document: the contribution-margin sign-convention decision, the `margin_pct` denominator choice, the forecast's warehouse-vs-asset cost basis choice, and the `product_prices.source`/dynamic-RRC gap)
- No changes expected in `ncrm/app/`, `ncrm/lib/repositories/*`, or `ncrm/supabase/migrations/0001`–`0008` — verify against actual project files before assuming otherwise.

## 7. Acceptance criteria
- [ ] `npx supabase db reset` (local) applies `0001`…`0009` cleanly, exit code 0
- [ ] `git diff` on `0001`…`0008` is empty
- [ ] `v_pnl_monthly` exposes `net_revenue`, `prro_gross_profit`, `contribution_margin`, `operating_expenses`, `inventory_adjustment_impact`, `true_net_profit` — no column named bare `profit`
- [ ] For a test month with only a `money_only` refund, `true_net_profit` matches the pre-migration value (net revenue absorbs the same subtraction the old code did in one step instead of two)
- [ ] For a test month with a `resellable` refund, `contribution_margin` visibly reflects the `mgmt_reversal_uah` add-back (documented sign, not accidental)
- [ ] `inventory_adjustment_impact` in `v_pnl_monthly` only reflects `adjustment_kind = 'operating_correction'` rows; an `opening_balance` adjustment produces no change to any `v_pnl_monthly` row
- [ ] A product with `available_qty > 0` and a current `v_current_rrc` row produces a non-null `forecast_margin`; a product with `available_qty > 0` and **no** `v_current_rrc` row appears in the unpriced-inventory view instead of showing forecast margin `0`
- [ ] `forecast_discount_reserve_pct` is read from `app_config`/`v_current_app_config`, not hardcoded, and is confirmed **not** to affect any `v_pnl_monthly` actual figure
- [ ] Cost-quality exposure view separates `provisional`/`estimated` quantity and value from `actual`
- [ ] `v_data_quality` gains any new checks additively; existing checks unchanged
- [ ] `0001`–`0008` files are byte-identical (`git diff` empty)
- [ ] Report explicitly states the resolution for every "flag, do not silently resolve" item in §2 of this handoff

## 8. QA / smoke test (owner)
Not checkout/payment/site-schema in the OpenCart sense — `bs-checkout-smoke`/`bs-merchant-schema-qa` not applicable (schema-only Supabase task, no live system touched, nothing deployed to a real site). Naming the risk directly: this view feeds every profit number the owner will eventually read on a dashboard. A wrong sign on the COGS-reversal add-back silently overstates or understates contribution margin for every month with a resellable return; a forecast view that accidentally mixes a future dynamic-RRC row into `v_current_rrc` would silently misprice forecast margin. Review the report's documented decisions (§7 last item) before trusting any number from this migration against real data.

- [ ] `cd ncrm && npx supabase db reset` — 0 errors
- [ ] `supabase db diff --local` — empty
- [ ] Manually (via `psql`): seed one resellable return in a test month, confirm `v_pnl_monthly.contribution_margin` for that month changes by exactly the documented sign/amount versus a run without the return
- [ ] Manually: seed one `operating_correction` inventory adjustment and one `opening_balance` adjustment in the same month; confirm only the `operating_correction` one moves `v_pnl_monthly.inventory_adjustment_impact`
- [ ] Manually: pick one product with stock and no `product_prices` row; confirm it appears in the unpriced-inventory view with a non-zero value, and does **not** appear with `forecast_margin = 0` anywhere
- [ ] Manually: hand-compute `forecast_margin` for one priced product and compare against the view's output
- [ ] `git status` before commit — no `.env`/keys staged
- [ ] Explicitly confirm: no write to the real (cloud) Supabase, live CRM, Apps Script, or OpenCart

## 9. Rollback note
Fully additive, schema-only, no live data involved. Rollback = remove `0009_reporting_forecast_kpi.sql` + `cd ncrm && npx supabase db reset` — restores a clean state through `0008`. If already applied to the real (cloud) Supabase before a revert is needed: a dedicated reverse-DDL script dropping/reverting the new/replaced views and the `app_config` row, matching the rollback precedent from NCRM-01/03/04/05/06. No app/repo-layer consumers of these views exist yet (NCRM-08 not started), so rollback has no downstream dependency chain.

## 10. Recommended status after execution
`In progress` until the owner confirms the local run (`db reset` + the manual test cases in §8) and signs off on the open decisions flagged in Context and required by §7's last acceptance item (contribution-margin sign convention, `margin_pct` denominator, forecast cost basis, `product_prices.source`/dynamic-RRC handling). Then → `Done`. Closes contract §7 sequence item 4. Does **not** start **NCRM-03** re-import/reconciliation (sequence item 5, separate task) and does **not** build any UI (**NCRM-08** read screens, separate task, currently Not started).
