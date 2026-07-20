# NCRM-07 — Reporting / forecast / KPI views

Date: 2026-07-15  
Scope: local Supabase schema only; no cloud Supabase, CRM, Apps Script, OpenCart, UI, or import work.

## Delivered scope

- New additive migration `ncrm/supabase/migrations/0009_reporting_forecast_kpi.sql`.
- Reworked `v_pnl_monthly` with actual-P&L fields: revenue, net revenue, PRRO gross profit, management COGS, direct sale costs, COGS reversals, contribution margin, OPEX, inventory-adjustment impact, and true net P&L.
- New views: `v_cost_quality_exposure`, `v_unpriced_inventory`, `v_forecast_margin`, and `v_inventory_dashboard_guardrails`.
- `v_data_quality` keeps every NCRM-06 branch and adds a warning for restockable returns whose COGS reversal is zero.

## Explicit decisions

### Return COGS sign

`contribution_margin = net_revenue - management_cogs - direct_sale_costs + cogs_reversals`.

`cogs_reversals` is the period sum of `refund_items.mgmt_reversal_uah`, dated by `refunds.refunded_at`. It is added back because a `resellable` or `mystery_unopened` return restores stock at the original frozen cost. A `money_only` or `damaged` return has zero reversal, so its original COGS remains an expense. Refunds themselves are deducted exactly once in `net_revenue`; they are not deducted again from true net P&L.

### Margin percentage

`margin_pct` uses `net_revenue` as denominator. This keeps the percentage aligned with the post-refund actual figure used by contribution margin and true net P&L. It is `NULL` when net revenue is zero.

### Forecast inventory-cost basis

The forecast uses only `available_qty`, not inbound or reserved stock. Its cost is warehouse management FIFO cost prorated from physical quantity to available quantity. Therefore asset-only/in-transit goods never produce forecast margin, and reservations do not cause the whole warehouse cost to be deducted from a smaller sellable quantity.

### Manual versus dynamic RRC

`product_prices.price_kind` now explicitly allows `manual` or `dynamic`; existing price rows default to `manual`. `v_current_rrc` filters to `manual`, so only manual RRC can feed `v_forecast_margin`. Dynamic RRC remains possible as an informational future price type but cannot silently change forecast margin.

### Forecast reserve

The 5% reserve is the effective-dated `app_config` value `forecast_discount_reserve_pct`, effective from `2026-07-11`. Forecast views read it through `v_current_app_config`; it is not hard-coded and is never joined into actual P&L.

## Guardrail sources

| Guardrail | Source |
|---|---|
| Warehouse/asset cost and physical/reserved/available quantity | `v_inventory_dashboard_guardrails` |
| Revenue through true net P&L and inventory adjustment impact | `v_pnl_monthly` |
| Provisional/estimated versus actual COGS exposure | `v_cost_quality_exposure` |
| Unpriced inventory | `v_unpriced_inventory` |
| Below-cost actual sales | Existing `v_below_cost_alert` |
| Forecast margin after reserve | `v_forecast_margin` |

## Validation

- `cd ncrm && npx supabase db reset` — passed, exit 0; applied `0001` through `0009`.
- `cd ncrm && npx supabase db diff --local` — passed: `No schema changes found`.
- Existing migrations `0001`–`0008` — no changes by this task; only new `0009` and this report were created.
- Focused SQL fixture ran inside `BEGIN` / `ROLLBACK`; no test rows persisted.
  - Two 1,000 UAH sales, each with 600 UAH management COGS and 50 UAH direct cost; one money-only and one resellable full refund: `revenue=2000`, `refunds=2000`, `cogs=1200`, `cogs_reversals=600`, `contribution_margin=-700`. This is the expected sum of `-650` money-only loss and `-50` resellable-return loss.
  - Two operating corrections of 200 UAH each plus one 300 UAH opening balance produced `inventory_adjustment_impact=400`; the opening balance added zero P&L impact.
  - A priced fixture with a newer dynamic price still returned manual RRC `1000` from `v_current_rrc`. With four available units, the forecast returned 5% reserve, `forecast_net_revenue=3800`, `management_inventory_cost=900`, and `forecast_margin=2900`.
  - An unpriced fixture with two available units appeared in `v_unpriced_inventory` with `warehouse_mgmt_cost=200` and had no forecast row.
- `v_cost_quality_exposure` returned the two actual fixture sale items separately with `units=2`, `management_cogs=1200`.

### Isolated refund assertions

Both tests were independent transactions and finished with `ROLLBACK`.

```text
money_only | revenue=1000.00 | refunds=1000.00 | net_revenue=0.00 |
cogs_reversals=0.00 | contribution_margin=-650.00 |
true_net_profit=-650.00 | old_true_net_profit=-650.00
```

The money-only case therefore preserves the old numeric result exactly: the
refund is now represented through `net_revenue` rather than through a second
post-margin subtraction.

```text
resellable | revenue=1000.00 | refunds=1000.00 | net_revenue=0.00 |
cogs_reversals=600.00 | contribution_margin=-50.00 |
true_net_profit=-50.00 | old_true_net_profit=-650.00
```

The resellable case differs by exactly the 600 UAH frozen management COGS
restored to inventory.

### Raw local command output

`cd ncrm && npx supabase db reset`

```text
Resetting local database...
Recreating database...
Initialising schema...
Seeding globals from roles.sql...
Applying migration 0001_stage1_core.sql...
NOTICE (42P06): schema "extensions" already exists, skipping
NOTICE (42710): extension "pgcrypto" already exists, skipping
Applying migration 0002_stage2_sales.sql...
Applying migration 0003_stage3_mystery_consumables.sql...
Applying migration 0004_stage4_expenses_reports.sql...
Applying migration 0005_grants_for_app_read.sql...
Applying migration 0006_inventory_ledger_foundation.sql...
Applying migration 0007_mystery_fulfillment.sql...
Applying migration 0008_returns_cost_quality.sql...
Applying migration 0009_reporting_forecast_kpi.sql...
Restarting containers...
Finished supabase db reset on branch master.
```

`cd ncrm && npx supabase db diff --local`

```text
{"diff":"","file":null,"schemas":[],"engine":"pg-delta","dropStatements":[],"message":"Diff complete."}
Creating shadow database...
Initialising schema...
Seeding globals from roles.sql...
Applying migration 0001_stage1_core.sql...
NOTICE (42P06): schema "extensions" already exists, skipping
NOTICE (42710): extension "pgcrypto" already exists, skipping
Applying migration 0002_stage2_sales.sql...
Applying migration 0003_stage3_mystery_consumables.sql...
Applying migration 0004_stage4_expenses_reports.sql...
Applying migration 0005_grants_for_app_read.sql...
Applying migration 0006_inventory_ledger_foundation.sql...
Applying migration 0007_mystery_fulfillment.sql...
Applying migration 0008_returns_cost_quality.sql...
Applying migration 0009_reporting_forecast_kpi.sql...
Diffing schemas...
Finished supabase db diff on branch master.

No schema changes found
```

## Rollback

For local development, remove `0009_reporting_forecast_kpi.sql` and run `cd ncrm && npx supabase db reset`. No real cloud environment is in scope. A later cloud rollback, if an owner applies the migration, requires reverse DDL for the new views, `product_prices.price_kind`, the manual-only `v_current_rrc`, and the config row.
