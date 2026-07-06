-- NCRM-01 / Stage 1
-- Core reference data, products, purchases, and FIFO lots.

create schema if not exists extensions;
create extension if not exists pgcrypto with schema extensions;

create or replace function public.set_updated_at()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

create table public.app_config (
  key text not null,
  value_num numeric,
  value_text text,
  value_date date,
  unit text,
  description text,
  effective_from date not null,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  primary key (key, effective_from),
  constraint app_config_exactly_one_value_chk check (
    num_nonnulls(value_num, value_text, value_date) = 1
  )
);

create trigger app_config_set_updated_at
before update on public.app_config
for each row execute function public.set_updated_at();

create view public.v_current_app_config as
with current_config as (
  select distinct on (key)
    key,
    value_num,
    value_text,
    value_date,
    unit,
    description,
    effective_from,
    is_active
  from public.app_config
  where is_active
    and effective_from <= current_date
  order by key, effective_from desc
),
computed_credit as (
  select
    'credit_servicing_pct'::text as key,
    rate.value_num * months.value_num as value_num,
    null::text as value_text,
    null::date as value_date,
    'ratio'::text as unit,
    'credit_rate_monthly × credit_months'::text as description,
    greatest(rate.effective_from, months.effective_from) as effective_from,
    true as is_active
  from current_config rate
  join current_config months on months.key = 'credit_months'
  where rate.key = 'credit_rate_monthly'
)
select * from current_config
where key <> 'credit_servicing_pct'
union all
select * from computed_credit;

create table public.currency_rates (
  id uuid primary key default gen_random_uuid(),
  currency text not null,
  rate_to_uah numeric(12,6) not null,
  as_of date not null,
  source text not null,
  note text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (currency, as_of),
  constraint currency_rates_currency_chk check (currency ~ '^[A-Z]{3}$'),
  constraint currency_rates_positive_chk check (rate_to_uah > 0)
);

create trigger currency_rates_set_updated_at
before update on public.currency_rates
for each row execute function public.set_updated_at();

create table public.product_brands (
  code text primary key,
  name text not null unique,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger product_brands_set_updated_at
before update on public.product_brands
for each row execute function public.set_updated_at();

create table public.product_categories (
  code text primary key,
  name text not null unique,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger product_categories_set_updated_at
before update on public.product_categories
for each row execute function public.set_updated_at();

create table public.games (
  code text primary key,
  name text not null unique,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger games_set_updated_at
before update on public.games
for each row execute function public.set_updated_at();

create table public.product_languages (
  code text primary key,
  name text not null unique,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger product_languages_set_updated_at
before update on public.product_languages
for each row execute function public.set_updated_at();

create table public.products (
  id uuid primary key default gen_random_uuid(),
  sku text not null unique,
  name text,
  full_name text,
  brand_code text references public.product_brands(code),
  category_code text references public.product_categories(code),
  game_code text references public.games(code),
  language_code text references public.product_languages(code),
  gtin text,
  legacy_sku text,
  is_active boolean not null default true,
  archived_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create index products_legacy_sku_idx on public.products(legacy_sku)
where legacy_sku is not null;

create trigger products_set_updated_at
before update on public.products
for each row execute function public.set_updated_at();

create table public.product_prices (
  id uuid primary key default gen_random_uuid(),
  product_id uuid not null references public.products(id),
  rrc numeric(12,2) not null,
  source text not null,
  note text,
  effective_from date not null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (product_id, effective_from),
  constraint product_prices_rrc_nonnegative_chk check (rrc >= 0)
);

create trigger product_prices_set_updated_at
before update on public.product_prices
for each row execute function public.set_updated_at();

create view public.v_current_rrc as
select distinct on (pp.product_id)
  pp.id,
  pp.product_id,
  pp.rrc,
  pp.source,
  pp.note,
  pp.effective_from
from public.product_prices pp
where pp.effective_from <= current_date
order by pp.product_id, pp.effective_from desc, pp.created_at desc;

create table public.supplier_regions (
  id uuid primary key default gen_random_uuid(),
  code text not null unique,
  name_uk text not null unique,
  has_intermediary boolean not null default false,
  intermediary_name text,
  default_goods_currency text not null,
  default_forwarding_currency text not null,
  default_intl_shipping_currency text not null,
  default_local_currency text not null,
  applicable_charges text[] not null default '{}',
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint supplier_regions_intermediary_chk check (
    has_intermediary or intermediary_name is null
  ),
  constraint supplier_regions_goods_currency_chk check (
    default_goods_currency ~ '^[A-Z]{3}$'
  ),
  constraint supplier_regions_forwarding_currency_chk check (
    default_forwarding_currency ~ '^[A-Z]{3}$'
  ),
  constraint supplier_regions_intl_currency_chk check (
    default_intl_shipping_currency ~ '^[A-Z]{3}$'
  ),
  constraint supplier_regions_local_currency_chk check (
    default_local_currency ~ '^[A-Z]{3}$'
  )
);

create trigger supplier_regions_set_updated_at
before update on public.supplier_regions
for each row execute function public.set_updated_at();

create table public.purchases (
  id uuid primary key default gen_random_uuid(),
  region_id uuid not null references public.supplier_regions(id),
  supplier_name text,
  order_ref text,
  order_url text not null,
  ordered_at date not null,
  goods_total_amount numeric(12,2) not null,
  goods_total_currency text not null,
  goods_total_rate numeric(12,6) not null,
  goods_total_uah numeric(12,2) not null,
  forwarding_fee_amount numeric(12,2) not null,
  forwarding_fee_currency text not null,
  forwarding_fee_rate numeric(12,6) not null,
  forwarding_fee_uah numeric(12,2) not null,
  intl_shipping_amount numeric(12,2) not null,
  intl_shipping_currency text not null,
  intl_shipping_rate numeric(12,6) not null,
  intl_shipping_uah numeric(12,2) not null,
  local_delivery_amount numeric(12,2) not null,
  local_delivery_currency text not null,
  local_delivery_rate numeric(12,6) not null,
  local_delivery_uah numeric(12,2) not null,
  note text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint purchases_amounts_nonnegative_chk check (
    goods_total_amount >= 0
    and goods_total_uah >= 0
    and forwarding_fee_amount >= 0
    and forwarding_fee_uah >= 0
    and intl_shipping_amount >= 0
    and intl_shipping_uah >= 0
    and local_delivery_amount >= 0
    and local_delivery_uah >= 0
  ),
  constraint purchases_rates_positive_chk check (
    goods_total_rate > 0
    and forwarding_fee_rate > 0
    and intl_shipping_rate > 0
    and local_delivery_rate > 0
  ),
  constraint purchases_currencies_chk check (
    goods_total_currency ~ '^[A-Z]{3}$'
    and forwarding_fee_currency ~ '^[A-Z]{3}$'
    and intl_shipping_currency ~ '^[A-Z]{3}$'
    and local_delivery_currency ~ '^[A-Z]{3}$'
  )
);

create index purchases_ordered_at_idx on public.purchases(ordered_at);
create index purchases_order_ref_idx on public.purchases(order_ref)
where order_ref is not null;

create trigger purchases_set_updated_at
before update on public.purchases
for each row execute function public.set_updated_at();

create table public.purchase_lot_statuses (
  code text primary key,
  name_uk text not null unique,
  is_stock boolean not null,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger purchase_lot_statuses_set_updated_at
before update on public.purchase_lot_statuses
for each row execute function public.set_updated_at();

create table public.purchase_lots (
  id uuid primary key default gen_random_uuid(),
  lot_code text not null unique,
  purchase_id uuid not null references public.purchases(id),
  product_id uuid not null references public.products(id),
  qty integer not null,
  goods_cost_uah numeric(12,2) not null,
  forwarding_fee_uah numeric(12,2) not null,
  intl_shipping_uah numeric(12,2) not null,
  local_delivery_uah numeric(12,2) not null,
  manual_unit_cost numeric(12,2),
  delivery_date date,
  track_number text,
  status text not null references public.purchase_lot_statuses(code),
  legacy_status text,
  note text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint purchase_lots_qty_positive_chk check (qty > 0),
  constraint purchase_lots_costs_nonnegative_chk check (
    goods_cost_uah >= 0
    and forwarding_fee_uah >= 0
    and intl_shipping_uah >= 0
    and local_delivery_uah >= 0
    and (manual_unit_cost is null or manual_unit_cost >= 0)
  )
);

create index purchase_lots_fifo_idx
on public.purchase_lots(product_id, delivery_date, created_at)
where status in ('in_stock', 'selling', 'sold');

create trigger purchase_lots_set_updated_at
before update on public.purchase_lots
for each row execute function public.set_updated_at();

create view public.v_purchase_lot_costs as
with config as (
  select coalesce((
    select value_num
    from public.v_current_app_config
    where key = 'credit_servicing_pct'
  ), 0::numeric) as credit_servicing_pct
)
select
  pl.id,
  pl.lot_code,
  pl.purchase_id,
  pl.product_id,
  pl.qty,
  pl.delivery_date,
  pl.status,
  (
    pl.goods_cost_uah
    + pl.forwarding_fee_uah
    + pl.intl_shipping_uah
    + pl.local_delivery_uah
  )::numeric(12,2) as prro_total,
  coalesce(
    pl.manual_unit_cost,
    (
      pl.goods_cost_uah
      + pl.forwarding_fee_uah
      + pl.intl_shipping_uah
      + pl.local_delivery_uah
    ) / nullif(pl.qty, 0)
  )::numeric(12,2) as prro_unit,
  (
    (
      pl.goods_cost_uah
      + pl.forwarding_fee_uah
      + pl.intl_shipping_uah
      + pl.local_delivery_uah
    ) * (1 + config.credit_servicing_pct)
  )::numeric(12,2) as mgmt_total,
  (
    coalesce(
      pl.manual_unit_cost,
      (
        pl.goods_cost_uah
        + pl.forwarding_fee_uah
        + pl.intl_shipping_uah
        + pl.local_delivery_uah
      ) / nullif(pl.qty, 0)
    ) * (1 + config.credit_servicing_pct)
  )::numeric(12,2) as mgmt_unit
from public.purchase_lots pl
cross join config;

insert into public.currency_rates (
  currency,
  rate_to_uah,
  as_of,
  source,
  note
)
values (
  'UAH',
  1,
  date '2026-07-05',
  'ручне',
  'NCRM-01 стартовий курс'
);

insert into public.supplier_regions (
  code,
  name_uk,
  has_intermediary,
  intermediary_name,
  default_goods_currency,
  default_forwarding_currency,
  default_intl_shipping_currency,
  default_local_currency,
  applicable_charges
)
values
  (
    'japan',
    'Японія (ZenMarket)',
    true,
    'ZenMarket',
    'UAH',
    'JPY',
    'JPY',
    'UAH',
    array['goods_total', 'forwarding_fee', 'intl_shipping', 'local_delivery']
  ),
  (
    'ukraine',
    'Україна',
    false,
    null,
    'UAH',
    'UAH',
    'UAH',
    'UAH',
    array['goods_total', 'local_delivery']
  ),
  (
    'europe',
    'Європа',
    true,
    null,
    'EUR',
    'EUR',
    'EUR',
    'UAH',
    array['goods_total', 'forwarding_fee', 'intl_shipping', 'local_delivery']
  ),
  (
    'usa',
    'США',
    true,
    null,
    'USD',
    'USD',
    'USD',
    'UAH',
    array['goods_total', 'forwarding_fee', 'intl_shipping', 'local_delivery']
  );

-- TODO(NCRM-01/OWNER): specify Europe/USA forwarders, customs, and form fields.

insert into public.purchase_lot_statuses (code, name_uk, is_stock)
values
  ('ordered', 'Замовлено', false),
  ('in_transit', 'В дорозі', false),
  ('in_stock', 'На складі', true),
  ('selling', 'В реалізації', true),
  ('sold', 'Продано', true),
  ('cancelled', 'Скасовано', false);
