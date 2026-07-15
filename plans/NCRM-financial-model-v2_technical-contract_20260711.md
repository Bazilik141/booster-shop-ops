# NCRM Financial Model v2 — Technical Contract

Date: 2026-07-11  
Status: owner-approved business contract; implementation is a separate sequence of additive migrations/tasks.  
Scope: replaces legacy accounting behaviour going forward. Migrations `0001`–`0005` stay immutable.

## 1. Purpose

Build the new CRM on one explicit inventory and financial model rather than reproduce the stale averages, negative writeoffs, ambiguous “profit” label, and silent cost fallbacks from the legacy Sheet.

Legacy exports remain an audit source. They do not override canonical FIFO valuation or create new operational rules.

## 2. Canonical financial vocabulary

| Metric | Definition |
|---|---|
| Revenue | Accepted-sale line revenue after discounts, before refunds. |
| Net revenue | Revenue minus monetary refunds for the period. |
| PRRO gross profit | Revenue minus PRRO COGS. |
| Contribution margin | Net revenue minus management COGS, direct sale costs, and COGS reversals for restocked returns. |
| Operating expenses | OPEX ledger entries for the period. |
| Inventory adjustment impact | Signed gain/loss from counted inventory corrections on their event date. |
| True net P&L | Contribution margin minus OPEX plus inventory adjustment impact. |
| Warehouse cost | FIFO value of physical Ukraine inventory, including finalized stock corrections. |
| Asset cost | Warehouse cost plus ordered and in-transit inventory. |
| Available quantity | Physical stock minus active reservations. |
| Forecast margin | Expected net revenue from RRC less management inventory cost; not an actual-sales metric. |

The UI must not expose an unqualified `Прибуток` label. It must show either `Контрибуційна маржа` or `Чистий P&L`.

## 3. Cost and inventory rules

### 3.1 Lot cost

- PRRO unit cost is landed cost: goods, forwarding fee, international delivery, and local delivery.
- Management unit cost is PRRO cost plus the effective credit-servicing configuration valid at the purchase date.
- Future purchases store original amount, currency, rate used, and UAH amount for every monetary field.
- The historical CSV import keeps legacy UAH totals and explicitly records that original rate history is unavailable.

### 3.2 Shared purchase costs

For an order with several product lines, shared fees use this priority:

1. Weight allocation when reliable line weights are present.
2. Value allocation when weight is absent.
3. Manual allocation with a validated total as an explicit override.

Quantity allocation is not a default because packs, blisters, and boxes have materially different size and value.

### 3.3 FIFO and lot state

Canonical lot states: `ordered`, `in_transit`, `in_stock`, `selling`, `sold`.

- FIFO and warehouse valuation use `in_stock`, `selling`, and `sold` cost layers.
- `ordered` and `in_transit` contribute to asset cost but cannot be sold or reserved.
- A future lot may not enter `in_stock` without a received date.
- Sales, MBOX fulfillment, writeoffs, approved returns, and adjustments consume or create explicit cost layers.
- Sale COGS snapshots remain immutable after finalization; later corrections are new financial documents, not edits to old sales.

### 3.4 Inventory corrections

`inventory_adjustments` and `inventory_adjustment_items` are the canonical replacement for signed legacy writeoffs.

- Item quantity is signed (`qty_delta`).
- Each item carries PRRO and management cost snapshots plus source/audit references.
- A normal physical-count difference creates a signed `inventory_variance_pnl` on the correction date.
- Historical sales are never rewritten to force an inventory reconciliation.
- Migration-only opening balances remain separately marked and are excluded from operating P&L.

## 4. Mystery Box contract

### 4.1 New operational SKU

| SKU | Game | Contents | RRC | Provisional management COGS |
|---|---|---:|---:|---:|
| `PKM-JP-MBX-ST` | Pokémon | 5 JP packs | 700 | 450 |
| `OP-JP-MBX-ST` | One Piece | 5 JP packs | 700 | 450 |
| `PKM-JP-MBX-XL` | Pokémon | 7 JP packs + holo | 950 | 700 |
| `OP-JP-MBX-XL` | One Piece | 7 JP packs + holo | 950 | 700 |

`PKM-JP-MIX-MBX` and `OP-JP-MIX-MBX` are legacy-only archived virtual products. They remain available for historical audit but never enter new operational flows.

### 4.2 Component eligibility

A product is offered in the Mystery assembly UI automatically when all conditions hold:

- it is physically available after reservations;
- its game matches the selected Mystery SKU;
- language is `JP`;
- it is a sealed-pack product;
- it is not marked `is_outlet`;
- it has no explicit `mystery_eligibility_override = excluded`.

The default is automatic eligibility. The UI permits an exception-only exclusion toggle; newly added standard JP packs need no manual allow-list maintenance. Outlet is a catalogue attribute, never a SKU-text heuristic. Promo packs are eligible.

### 4.3 Assembly workflow

Proposed technical records: `mystery_fulfillments` and `mystery_fulfillment_items`.

| State | Inventory effect |
|---|---|
| `needs_assembly` | None; incoming site sale is provisional. |
| `reserved` | Components are selected and reduce available quantity only. |
| `committed` | On `Відправлено`, create final MBOX writeoff, mystery contents, actual COGS, and consumable consumption in one transaction. |
| `released` | Cancellation before shipment removes the reservation. |
| `reversed` | A post-commit reversal/return follows the return and inventory-adjustment rules. |

Rules:

- Assembly is linked to `sale_item_id`, not only to `sale_id`.
- ST validates exactly five packs per sold unit; XL validates exactly seven packs and adds the confirmed fixed holo cost of 75 UAH per sold unit.
- A generic MBOX writeoff cannot be created through a free-form writeoff form without the linked Mystery fulfillment.
- The database RPC commits all final writes atomically: availability check under lock, MBOX header/items, `mystery_contents`, cost refresh, consumable consumption, and fulfillment state.
- Before final content, the sale item remains `provisional`; after commit it receives actual FIFO COGS from components, holo, and attributable consumables.

## 5. Returns and COGS quality

### 5.1 Returns

Add `refund_items` linked to a refund and original `sale_item` with quantity and condition.

| Case | Revenue | COGS | Stock |
|---|---|---|---|
| Money-only refund | Decrease | Unchanged | Unchanged |
| Resellable returned item | Decrease | Reverse original COGS for returned quantity | Return layer at original cost |
| Damaged/non-resellable return | Decrease | Original COGS remains | No return layer |
| Unopened Mystery return | Decrease | Reverse component COGS | Restore confirmed components |

Direct sale costs remain unless a real fee reversal is separately recorded.

### 5.2 Cost confidence

Cost state must distinguish at least:

- `pending` — sale is not actual yet;
- `provisional` — Mystery sale awaiting final assembly;
- `estimated` — FIFO shortage used a documented fallback and needs review;
- `actual` — complete, auditable cost allocation.

No COGS calculation may silently write zero. KPI views report estimated-cost quantity and value separately from final figures.

## 6. Forecast and dashboard rules

### 6.1 Forecast

Manual RRC remains the canonical forecast price. Dynamic RRC is informational only.

```text
forecast_revenue_before_reserve = manual_RRC × available_qty
expected_discount_amount = forecast_revenue_before_reserve × expected_discount_pct
forecast_net_revenue = forecast_revenue_before_reserve − expected_discount_amount
forecast_margin = forecast_net_revenue − management_inventory_cost
```

`expected_discount_pct` starts at 5% and is an effective-dated configuration named `reserve under future discounts`. It is never applied to actual P&L.

Items without manual RRC are not treated as forecast profit `0`. They appear as a separate `unpriced inventory` count and value.

### 6.2 Dashboard guardrails

The dashboard must separately show:

- warehouse cost, asset cost, physical quantity, reserved quantity, and available quantity;
- revenue, net revenue, PRRO gross profit, contribution margin, and true net P&L;
- inventory adjustment impact for the selected period;
- provisional and estimated COGS exposure;
- unpriced inventory value;
- below-cost actual sales;
- forecast margin after the 5% discount reserve.

## 7. Proposed implementation sequence

The following are additive migrations/tasks. Exact sequence numbers are reserved for implementation planning; prior migrations are not edited.

1. **Inventory foundation** — adjustments ledger, inventory variance, product stock mode/outlet flag, reservations, cost allocation policy, and FIFO/warehouse views.
2. **Mystery fulfillment** — retire generic operational MBX seeds, seed four approved SKU, create reservation/commit workflow, automatic JP component pool, and virtual-bundle stock exclusion.
3. **Returns and cost quality** — `refund_items`, return layers, COGS reversal logic, `estimated` cost state, and data-quality checks.
4. **Reporting and forecast** — revised P&L/forecast views, effective-dated 5% discount reserve, unpriced-inventory reporting, and dashboard labels.
5. **NCRM-03 re-import/reconciliation** — import signed adjustments and legacy Mystery audit links under the new model; compare canonical FIFO/inventory values rather than stale Sheet averages.

## 8. Acceptance criteria for the implementation sequence

- `db reset` applies the existing migrations plus all new additive migrations cleanly.
- No normal sale, mystery fulfillment, correction, or return produces negative available stock without an explicit blocking error.
- A shared-cost purchase proves weight, value, and manual allocation sum exactly to the original fee.
- A signed inventory correction produces the expected stock delta and a dated inventory-variance P&L entry without modifying historical sale snapshots.
- Each of the four Mystery SKU validates game, JP language, Outlet exclusion, exact pack count, reservation release, and atomic shipment commit.
- A resellable return restores both the original cost layer and COGS; a non-resellable return does neither.
- No `actual` sales COGS is zero or fallback-derived; estimated and provisional exposure is visible in the dashboard.
- Forecast uses manual RRC, discounts expected revenue by the effective 5% configuration, and reports inventory without RRC separately.
- Legacy Mystery products cannot create new stock alerts or new operational Mystery sales.

## 9. Explicitly out of scope for this contract

- Editing migrations `0001`–`0005`.
- Writing back to the legacy Sheet or Apps Script.
- OpenCart production changes before a dedicated order-sync/CRM handoff.
- Reclassifying historical legacy dashboard averages as canonical FIFO values.

## 10. Access model / RLS foundation (addendum, owner-approved 2026-07-15)

Not part of the original 2026-07-11 contract above — added when NCRM-07b ("Enable RLS on public schema") turned out to need a real scoping decision instead of a bare RLS flip. Recorded here so it isn't only findable inside a dated plan file.

- **Roles:** `owner`, `admin`, `user_plus`, `user`. `owner` = the sole owner account, all access, no exceptions. `admin`/`user` exact permission grants: not yet defined (owner will assign later). `user_plus` = `user` baseline plus individually-granted extra permissions.
- **Posture:** RLS enabled deny-by-default on all public tables. No `anon`/`authenticated` policies exist. `service_role` (used exclusively by the app today) is unaffected — it always bypasses RLS.
- **Schema foundation:** `public.staff` (`auth.users` → role), `public.staff_permission_overrides` (per-staff point permissions, free-text key, no fixed taxonomy yet), and a `created_by` scaffold column on `sales`, `purchases`, `writeoffs`, `mystery_fulfillments`.
- **Where enforcement lives:** application layer (Next.js repository), not Postgres RLS-per-role policies — consistent with the existing rule that UI never queries Supabase directly (`ncrm/README.md`). RLS staying deny-by-default is defense-in-depth and Security Advisor compliance, not the access-control mechanism itself.
- **Explicitly deferred to NCRM-08/09:** admin/user permission grants, login UI, real per-role RLS policies, the application-layer permission-check helper.
- **Full rationale + options considered:** `plans/NCRM-07b_rls-multiuser-role-model_20260715.md`. **Implementation spec:** `handoffs/handoff_NCRM-07b_rls-multiuser-role-foundation_20260715.md`.
