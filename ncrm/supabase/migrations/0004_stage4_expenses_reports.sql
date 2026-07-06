-- NCRM-01 / Stage 4
-- Expenses ledger, true P&L, and reporting views.

create table public.expenses (
  id uuid primary key default gen_random_uuid(),
  category text not null,
  description text not null,
  amount numeric(12,2) not null,
  amount_currency text not null,
  amount_rate numeric(12,6) not null,
  amount_uah numeric(12,2) not null,
  spent_at date not null,
  treatment text not null,
  consumable_id uuid references public.consumables(id),
  consumable_qty numeric(12,3),
  linked_sale_id uuid references public.sales(id),
  note text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint expenses_category_chk check (
    category in (
      'Хостинг і сайт',
      'Маркетинг',
      'Сервіси та підписки',
      'Паковання та розхідники',
      'Інше'
    )
  ),
  constraint expenses_treatment_chk check (
    treatment in ('opex', 'capitalized')
  ),
  constraint expenses_amounts_chk check (
    amount > 0 and amount_rate > 0 and amount_uah > 0
  ),
  constraint expenses_currency_chk check (
    amount_currency ~ '^[A-Z]{3}$'
  ),
  constraint expenses_capitalized_consumable_chk check (
    (
      treatment = 'capitalized'
      and consumable_id is not null
      and consumable_qty is not null
      and consumable_qty > 0
    )
    or (
      treatment = 'opex'
      and consumable_id is null
      and consumable_qty is null
    )
  )
);

create index expenses_spent_at_idx on public.expenses(spent_at);
create index expenses_linked_sale_id_idx on public.expenses(linked_sale_id)
where linked_sale_id is not null;

create trigger expenses_set_updated_at
before update on public.expenses
for each row execute function public.set_updated_at();

create or replace function public.fn_sync_capitalized_consumable()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
begin
  if tg_op in ('UPDATE', 'DELETE')
    and old.treatment = 'capitalized'
  then
    update public.consumables
    set received_via_expenses = received_via_expenses - old.consumable_qty
    where id = old.consumable_id;
  end if;

  if tg_op in ('INSERT', 'UPDATE')
    and new.treatment = 'capitalized'
  then
    update public.consumables
    set received_via_expenses = received_via_expenses + new.consumable_qty
    where id = new.consumable_id;
  end if;

  if tg_op = 'DELETE' then
    return old;
  end if;
  return new;
end;
$$;

create trigger expenses_sync_capitalized_consumable
after insert or update or delete on public.expenses
for each row execute function public.fn_sync_capitalized_consumable();

create view public.v_pnl_monthly as
with sales_monthly as (
  select
    date_trunc('month', s.sold_at)::date as month,
    sum(f.revenue)::numeric(14,2) as revenue,
    sum(f.mgmt_cogs)::numeric(14,2) as cogs,
    sum(
      si.packaging_alloc
      + si.payment_fee
      + si.shop_delivery_alloc
    )::numeric(14,2) as direct_sale_costs,
    sum(f.net_profit)::numeric(14,2) as contribution_margin
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
    date_trunc('month', refunded_at)::date as month,
    sum(amount)::numeric(14,2) as refunds
  from public.refunds
  group by date_trunc('month', refunded_at)::date
),
months as (
  select month from sales_monthly
  union
  select month from opex_monthly
  union
  select month from refunds_monthly
)
select
  m.month,
  coalesce(s.revenue, 0)::numeric(14,2) as revenue,
  coalesce(s.cogs, 0)::numeric(14,2) as cogs,
  coalesce(s.direct_sale_costs, 0)::numeric(14,2)
    as direct_sale_costs,
  coalesce(s.contribution_margin, 0)::numeric(14,2)
    as contribution_margin,
  coalesce(o.operating_expenses, 0)::numeric(14,2)
    as operating_expenses,
  coalesce(r.refunds, 0)::numeric(14,2) as refunds,
  (
    coalesce(s.contribution_margin, 0)
    - coalesce(o.operating_expenses, 0)
    - coalesce(r.refunds, 0)
  )::numeric(14,2) as true_net_profit,
  case
    when coalesce(s.revenue, 0) = 0 then null
    else round(
      (
        coalesce(s.contribution_margin, 0)
        - coalesce(o.operating_expenses, 0)
        - coalesce(r.refunds, 0)
      ) / s.revenue * 100,
      2
    )
  end as margin_pct
from months m
left join sales_monthly s using (month)
left join opex_monthly o using (month)
left join refunds_monthly r using (month);

create view public.v_sales_report as
with periods(period_code, period_name, date_from, date_to) as (
  values
    (
      'last_7_days'::text,
      'Останні 7 днів'::text,
      current_date - 6,
      current_date
    ),
    (
      'current_month'::text,
      'Поточний місяць'::text,
      date_trunc('month', current_date)::date,
      current_date
    ),
    (
      'previous_month'::text,
      'Попередній місяць'::text,
      (date_trunc('month', current_date) - interval '1 month')::date,
      (date_trunc('month', current_date) - interval '1 day')::date
    )
),
sales_agg as (
  select
    p.period_code,
    count(distinct s.id) as orders,
    coalesce(sum(si.qty), 0) as units,
    coalesce(sum(f.revenue), 0)::numeric(14,2) as revenue,
    coalesce(sum(f.net_profit), 0)::numeric(14,2)
      as contribution_margin
  from periods p
  left join public.sales s
    on s.sold_at between p.date_from and p.date_to
    and public.fn_is_actual_sale(s.id)
  left join public.sale_items si on si.sale_id = s.id
  left join public.v_sale_item_financials f on f.id = si.id
  group by p.period_code
),
opex_agg as (
  select
    p.period_code,
    coalesce(sum(e.amount_uah), 0)::numeric(14,2)
      as operating_expenses
  from periods p
  left join public.expenses e
    on e.spent_at between p.date_from and p.date_to
    and e.treatment = 'opex'
  group by p.period_code
),
refund_agg as (
  select
    p.period_code,
    coalesce(sum(r.amount), 0)::numeric(14,2) as refunds
  from periods p
  left join public.refunds r
    on r.refunded_at between p.date_from and p.date_to
  group by p.period_code
)
select
  p.period_code,
  p.period_name,
  p.date_from,
  p.date_to,
  s.orders,
  s.units,
  s.revenue,
  (
    s.contribution_margin
    - o.operating_expenses
    - r.refunds
  )::numeric(14,2) as true_net_profit,
  case
    when s.revenue = 0 then null
    else round(
      (
        s.contribution_margin
        - o.operating_expenses
        - r.refunds
      ) / s.revenue * 100,
      2
    )
  end as margin_pct,
  case
    when s.orders = 0 then null
    else round(s.revenue / s.orders, 2)
  end as average_order_value
from periods p
join sales_agg s using (period_code)
join opex_agg o using (period_code)
join refund_agg r using (period_code);

create view public.v_channel_report as
select
  date_trunc('month', s.sold_at)::date as month,
  sc.code as channel_code,
  sc.name_uk as channel_name,
  count(distinct s.id) as orders,
  sum(si.qty) as units,
  sum(f.revenue)::numeric(14,2) as revenue,
  sum(f.net_profit)::numeric(14,2) as contribution_margin,
  case
    when sum(f.revenue) = 0 then null
    else round(sum(f.net_profit) / sum(f.revenue) * 100, 2)
  end as contribution_margin_pct,
  round(sum(f.revenue) / nullif(count(distinct s.id), 0), 2)
    as average_order_value
from public.sales s
join public.sale_channels sc on sc.id = s.channel_id
join public.sale_items si on si.sale_id = s.id
join public.v_sale_item_financials f on f.id = si.id
where public.fn_is_actual_sale(s.id)
group by
  date_trunc('month', s.sold_at)::date,
  sc.code,
  sc.name_uk;

create view public.v_top_skus as
select
  p.id as product_id,
  p.sku,
  p.name,
  sum(si.qty) as units,
  sum(f.revenue)::numeric(14,2) as revenue,
  sum(f.net_profit)::numeric(14,2) as contribution_margin
from public.sales s
join public.sale_items si on si.sale_id = s.id
join public.products p on p.id = si.product_id
join public.v_sale_item_financials f on f.id = si.id
where public.fn_is_actual_sale(s.id)
  and s.sold_at >= current_date - 29
group by p.id, p.sku, p.name;

create view public.v_stock_alerts as
with purchased as (
  select
    product_id,
    sum(qty)::numeric as received_qty
  from public.purchase_lots
  where status in ('in_stock', 'selling', 'sold')
  group by product_id
),
sold as (
  select
    si.product_id,
    sum(si.qty)::numeric as sold_qty,
    sum(si.qty) filter (
      where s.sold_at >= current_date - 29
    )::numeric as sold_qty_30d
  from public.sale_items si
  join public.sales s on s.id = si.sale_id
  where public.fn_is_actual_sale(s.id)
  group by si.product_id
),
written_off as (
  select
    wi.product_id,
    sum(wi.qty)::numeric as written_off_qty
  from public.writeoff_items wi
  group by wi.product_id
),
threshold as (
  select value_num as qty
  from public.v_current_app_config
  where key = 'stock_alert_qty'
),
stock as (
  select
    p.id as product_id,
    p.sku,
    p.name,
    (
      coalesce(pr.received_qty, 0)
      - coalesce(s.sold_qty, 0)
      - coalesce(w.written_off_qty, 0)
    )::numeric as stock_qty,
    coalesce(s.sold_qty_30d, 0)::numeric as sold_qty_30d
  from public.products p
  left join purchased pr on pr.product_id = p.id
  left join sold s on s.product_id = p.id
  left join written_off w on w.product_id = p.id
  where p.is_active
)
select
  st.product_id,
  st.sku,
  st.name,
  st.stock_qty,
  st.sold_qty_30d,
  case
    when st.sold_qty_30d = 0 then null
    else round(st.stock_qty / (st.sold_qty_30d / 30), 1)
  end as coverage_days,
  case
    when st.stock_qty <= 0 then 'Немає'
    when t.qty is not null and st.stock_qty <= t.qty then 'Мало'
    else null
  end as alert
from stock st
left join threshold t on true;

create view public.v_repeat_customers as
with normalized as (
  select
    s.id,
    s.sold_at,
    s.customer_name,
    regexp_replace(coalesce(s.customer_phone, ''), '[^0-9+]', '', 'g')
      as customer_phone,
    sum(f.revenue)::numeric(14,2) as revenue
  from public.sales s
  join public.sale_items si on si.sale_id = s.id
  join public.v_sale_item_financials f on f.id = si.id
  where public.fn_is_actual_sale(s.id)
  group by s.id, s.sold_at, s.customer_name, s.customer_phone
)
select
  customer_phone,
  max(customer_name) as customer_name,
  count(*) as order_count,
  min(sold_at) as first_order_at,
  max(sold_at) as last_order_at,
  sum(revenue)::numeric(14,2) as lifetime_revenue
from normalized
where customer_phone <> ''
group by customer_phone
having count(*) > 1;

create view public.v_data_quality as
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
  and public.fn_is_actual_sale(si.sale_id);

create view public.v_below_cost_alert as
select
  s.id as sale_id,
  s.order_no,
  s.sold_at,
  si.id as sale_item_id,
  p.id as product_id,
  p.sku,
  p.name,
  si.qty,
  f.revenue,
  f.mgmt_cogs,
  (f.revenue - f.mgmt_cogs)::numeric(12,2) as gap_uah
from public.sales s
join public.sale_items si on si.sale_id = s.id
join public.products p on p.id = si.product_id
join public.v_sale_item_financials f on f.id = si.id
where public.fn_is_actual_sale(s.id)
  and f.revenue < f.mgmt_cogs;
