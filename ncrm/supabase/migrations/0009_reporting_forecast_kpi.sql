-- NCRM-07
-- Reporting, forecast, and KPI views. Local schema only.

-- Forecasts must use the manual RRC. The new explicit kind preserves every
-- existing price as manual while allowing a future informational dynamic price.
alter table public.product_prices
  add column price_kind text not null default 'manual';

alter table public.product_prices
  add constraint product_prices_price_kind_chk
  check (price_kind in ('manual', 'dynamic'));

create or replace view public.v_current_rrc as
select distinct on (pp.product_id)
  pp.id,
  pp.product_id,
  pp.rrc,
  pp.source,
  pp.note,
  pp.effective_from,
  pp.price_kind
from public.product_prices pp
where pp.effective_from <= current_date
  and pp.price_kind = 'manual'
order by pp.product_id, pp.effective_from desc, pp.created_at desc;

insert into public.app_config (
  key,
  value_num,
  unit,
  description,
  effective_from,
  is_active
)
values (
  'forecast_discount_reserve_pct',
  5,
  '%',
  'NCRM financial-model v2 section 6.1: reserve under future discounts',
  date '2026-07-11',
  true
)
on conflict (key, effective_from) do nothing;

-- Refunds affect net revenue on their own event date. A resellable or unopened
-- Mystery return restores frozen management COGS through cogs_reversals.
create or replace view public.v_pnl_monthly as
with sales_monthly as (
  select
    date_trunc('month', s.sold_at)::date as month,
    sum(f.revenue)::numeric(14,2) as revenue,
    sum(f.gross_profit)::numeric(14,2) as prro_gross_profit,
    sum(f.mgmt_cogs)::numeric(14,2) as cogs,
    sum(
      si.packaging_alloc
      + si.payment_fee
      + si.shop_delivery_alloc
    )::numeric(14,2) as direct_sale_costs
  from public.sales s
  join public.sale_items si on si.sale_id = s.id
  join public.v_sale_item_financials f on f.id = si.id
  where public.fn_is_actual_sale(s.id)
  group by date_trunc('month', s.sold_at)::date
),
opex_monthly as (
  select
    date_trunc('month', spent_at)::date as month,
    sum(amount_uah)::numeric(14,2) as operating_expenses
  from public.expenses
  where treatment = 'opex'
  group by date_trunc('month', spent_at)::date
),
refunds_monthly as (
  select
    date_trunc('month', r.refunded_at)::date as month,
    sum(r.amount)::numeric(14,2) as refunds,
    coalesce(sum(ri.mgmt_reversal_uah), 0)::numeric(14,2) as cogs_reversals
  from public.refunds r
  left join public.refund_items ri on ri.refund_id = r.id
  group by date_trunc('month', r.refunded_at)::date
),
adjustments_monthly as (
  select
    date_trunc('month', adjustment_date)::date as month,
    sum(mgmt_variance_uah)::numeric(14,2) as inventory_adjustment_impact
  from public.v_inventory_adjustment_pnl
  where is_operating_pnl
  group by date_trunc('month', adjustment_date)::date
),
months as (
  select month from sales_monthly
  union
  select month from opex_monthly
  union
  select month from refunds_monthly
  union
  select month from adjustments_monthly
),
monthly as (
  select
    m.month,
    coalesce(s.revenue, 0)::numeric(14,2) as revenue,
    coalesce(s.prro_gross_profit, 0)::numeric(14,2) as prro_gross_profit,
    coalesce(s.cogs, 0)::numeric(14,2) as cogs,
    coalesce(s.direct_sale_costs, 0)::numeric(14,2) as direct_sale_costs,
    coalesce(o.operating_expenses, 0)::numeric(14,2) as operating_expenses,
    coalesce(r.refunds, 0)::numeric(14,2) as refunds,
    coalesce(r.cogs_reversals, 0)::numeric(14,2) as cogs_reversals,
    coalesce(a.inventory_adjustment_impact, 0)::numeric(14,2)
      as inventory_adjustment_impact
  from months m
  left join sales_monthly s using (month)
  left join opex_monthly o using (month)
  left join refunds_monthly r using (month)
  left join adjustments_monthly a using (month)
)
select
  month,
  revenue,
  cogs,
  direct_sale_costs,
  (
    revenue - refunds - cogs - direct_sale_costs + cogs_reversals
  )::numeric(14,2) as contribution_margin,
  operating_expenses,
  refunds,
  (
    revenue - refunds - cogs - direct_sale_costs + cogs_reversals
    - operating_expenses + inventory_adjustment_impact
  )::numeric(14,2) as true_net_profit,
  case
    when revenue - refunds = 0 then null
    else round(
      (
        revenue - refunds - cogs - direct_sale_costs + cogs_reversals
        - operating_expenses + inventory_adjustment_impact
      ) / (revenue - refunds) * 100,
      2
    )
  end as margin_pct,
  (revenue - refunds)::numeric(14,2) as net_revenue,
  prro_gross_profit,
  cogs_reversals,
  inventory_adjustment_impact
from monthly;

create view public.v_cost_quality_exposure as
select
  date_trunc('month', s.sold_at)::date as month,
  si.cost_state,
  count(*) as sale_item_count,
  sum(si.qty)::numeric as units,
  sum(f.revenue)::numeric(14,2) as revenue,
  sum(f.mgmt_cogs)::numeric(14,2) as management_cogs
from public.sales s
join public.sale_items si on si.sale_id = s.id
join public.v_sale_item_financials f on f.id = si.id
where public.fn_is_actual_sale(s.id)
  and si.cost_state in ('provisional', 'estimated', 'actual')
group by date_trunc('month', s.sold_at)::date, si.cost_state;

create view public.v_unpriced_inventory as
select
  a.product_id,
  a.sku,
  a.name,
  a.physical_qty,
  a.reserved_qty,
  a.available_qty,
  coalesce(v.warehouse_mgmt_cost, 0)::numeric(14,2) as warehouse_mgmt_cost,
  coalesce(v.asset_mgmt_cost, 0)::numeric(14,2) as asset_mgmt_cost
from public.v_inventory_available a
left join public.v_inventory_fifo_valuation v on v.product_id = a.product_id
left join public.v_current_rrc rrc on rrc.product_id = a.product_id
where a.physical_qty > 0
  and rrc.product_id is null;

-- Forecast is limited to sellable warehouse stock. Its management inventory
-- cost is the FIFO warehouse value prorated to available (not reserved) units.
create view public.v_forecast_margin as
with reserve as (
  select (value_num / 100)::numeric as expected_discount_pct
  from public.v_current_app_config
  where key = 'forecast_discount_reserve_pct'
)
select
  a.product_id,
  a.sku,
  a.name,
  rrc.rrc as manual_rrc,
  a.physical_qty,
  a.reserved_qty,
  a.available_qty,
  reserve.expected_discount_pct,
  (rrc.rrc * a.available_qty)::numeric(14,2)
    as forecast_revenue_before_reserve,
  (rrc.rrc * a.available_qty * reserve.expected_discount_pct)::numeric(14,2)
    as expected_discount_amount,
  (
    rrc.rrc * a.available_qty * (1 - reserve.expected_discount_pct)
  )::numeric(14,2) as forecast_net_revenue,
  (
    coalesce(v.warehouse_mgmt_cost, 0)
    * a.available_qty / nullif(v.warehouse_qty, 0)
  )::numeric(14,2) as management_inventory_cost,
  (
    rrc.rrc * a.available_qty * (1 - reserve.expected_discount_pct)
    - coalesce(v.warehouse_mgmt_cost, 0)
      * a.available_qty / nullif(v.warehouse_qty, 0)
  )::numeric(14,2) as forecast_margin
from public.v_inventory_available a
join public.v_current_rrc rrc on rrc.product_id = a.product_id
join reserve on true
left join public.v_inventory_fifo_valuation v on v.product_id = a.product_id
where a.available_qty > 0;

create view public.v_inventory_dashboard_guardrails as
select
  coalesce(sum(a.physical_qty), 0)::numeric as physical_qty,
  coalesce(sum(a.reserved_qty), 0)::numeric as reserved_qty,
  coalesce(sum(a.available_qty), 0)::numeric as available_qty,
  coalesce(sum(v.warehouse_prro_cost), 0)::numeric(14,2) as warehouse_prro_cost,
  coalesce(sum(v.warehouse_mgmt_cost), 0)::numeric(14,2) as warehouse_mgmt_cost,
  coalesce(sum(v.asset_prro_cost), 0)::numeric(14,2) as asset_prro_cost,
  coalesce(sum(v.asset_mgmt_cost), 0)::numeric(14,2) as asset_mgmt_cost
from public.v_inventory_available a
left join public.v_inventory_fifo_valuation v on v.product_id = a.product_id;

create or replace view public.v_data_quality as
select
  'actual_sale_missing_cogs'::text as check_name,
  'error'::text as severity,
  si.id::text as record_key,
  concat('sale_item cost is not fixed; sale_id=', si.sale_id) as details
from public.sale_items si
where public.fn_is_actual_sale(si.sale_id)
  and (
    si.cost_fixed_at is null
    or si.prro_unit is null
    or si.mgmt_unit is null
  )
union all
select
  'stock_lot_missing_delivery_date',
  'warning',
  pl.id::text,
  concat('lot_code=', pl.lot_code)
from public.purchase_lots pl
where pl.status in ('in_stock', 'selling', 'sold')
  and pl.delivery_date is null
union all
select
  'negative_stock',
  'error',
  sa.product_id::text,
  concat('sku=', sa.sku, '; stock_qty=', sa.stock_qty)
from public.v_stock_alerts sa
where sa.stock_qty < 0
union all
select
  'mystery_cogs_provisional',
  'warning',
  si.id::text,
  concat('sale_id=', si.sale_id)
from public.sale_items si
where si.cost_state = 'provisional'
  and public.fn_is_actual_sale(si.sale_id)
union all
select
  'sale_cogs_estimated',
  'warning',
  si.id::text,
  concat('sale_id=', si.sale_id, '; method=', si.cost_method, '; ', coalesce(si.cost_audit, ''))
from public.sale_items si
where si.cost_state = 'estimated'
union all
select
  'refund_header_without_items',
  'warning',
  r.id::text,
  concat('sale_id=', r.sale_id, '; refunded_at=', r.refunded_at)
from public.refunds r
where not exists (
  select 1 from public.refund_items ri where ri.refund_id = r.id
)
union all
select
  'refund_item_qty_exceeds_sale_item',
  'error',
  ri.sale_item_id::text,
  concat('refunded_qty=', sum(ri.qty), '; sold_qty=', si.qty)
from public.refund_items ri
join public.sale_items si on si.id = ri.sale_item_id
group by ri.sale_item_id, si.qty
having sum(ri.qty) > si.qty
union all
select
  'mystery_unopened_not_reversed',
  'error',
  ri.id::text,
  concat('sale_item=', ri.sale_item_id)
from public.refund_items ri
left join public.mystery_fulfillments mf on mf.sale_item_id = ri.sale_item_id
where ri.condition = 'mystery_unopened'
  and coalesce(mf.state, '') <> 'reversed'
union all
select
  'refund_restock_condition_mismatch',
  'warning',
  r.id::text,
  concat('restock=', r.restock, '; stock_return_items=', coalesce(x.stock_return_items, 0))
from public.refunds r
left join lateral (
  select count(*)::integer as stock_return_items
  from public.refund_items ri
  where ri.refund_id = r.id
    and ri.condition in ('resellable', 'mystery_unopened')
) x on true
where r.restock is distinct from (coalesce(x.stock_return_items, 0) > 0)
union all
select
  'committed_mystery_missing_component_snapshot',
  'warning',
  mf.id::text,
  concat('sale_item=', mf.sale_item_id)
from public.mystery_fulfillments mf
where mf.state = 'committed'
  and exists (
    select 1
    from public.mystery_contents mc
    where mc.sale_item_id = mf.sale_item_id
      and (
        mc.prro_unit_snapshot is null
        or mc.mgmt_unit_snapshot is null
        or mc.cost_snapshot_at is null
      )
  )
union all
select
  'restock_refund_missing_cogs_reversal',
  'warning',
  ri.id::text,
  concat('refund_id=', ri.refund_id, '; condition=', ri.condition)
from public.refund_items ri
where ri.condition in ('resellable', 'mystery_unopened')
  and ri.mgmt_reversal_uah = 0;
