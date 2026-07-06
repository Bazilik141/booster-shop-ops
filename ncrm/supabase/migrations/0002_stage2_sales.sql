-- NCRM-01 / Stage 2
-- Sales, payments, refunds, and frozen FIFO COGS.

create table public.sale_channels (
  id uuid primary key default gen_random_uuid(),
  code text not null unique,
  name_uk text not null unique,
  is_online boolean,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger sale_channels_set_updated_at
before update on public.sale_channels
for each row execute function public.set_updated_at();

create table public.payment_types (
  id uuid primary key default gen_random_uuid(),
  code text not null unique,
  name_uk text not null unique,
  fee_pct_config_key text,
  fee_fixed_config_key text,
  fee_min_config_key text,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger payment_types_set_updated_at
before update on public.payment_types
for each row execute function public.set_updated_at();

create table public.post_methods (
  id uuid primary key default gen_random_uuid(),
  code text not null unique,
  name_uk text not null unique,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger post_methods_set_updated_at
before update on public.post_methods
for each row execute function public.set_updated_at();

create table public.order_statuses (
  id uuid primary key default gen_random_uuid(),
  code text not null unique,
  name_uk text not null unique,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger order_statuses_set_updated_at
before update on public.order_statuses
for each row execute function public.set_updated_at();

create table public.payment_statuses (
  id uuid primary key default gen_random_uuid(),
  code text not null unique,
  name_uk text not null unique,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create trigger payment_statuses_set_updated_at
before update on public.payment_statuses
for each row execute function public.set_updated_at();

create table public.sales (
  id uuid primary key default gen_random_uuid(),
  order_no text not null unique,
  opencart_order_id text unique,
  channel_id uuid not null references public.sale_channels(id),
  sold_at date not null,
  customer_phone text,
  customer_name text,
  payment_type_id uuid not null references public.payment_types(id),
  payment_status_id uuid not null references public.payment_statuses(id),
  order_status_id uuid not null references public.order_statuses(id),
  post_method_id uuid references public.post_methods(id),
  ttn text,
  discount_total numeric(12,2) not null default 0,
  packaging_type_id uuid,
  packaging_cost numeric(12,2) not null default 0,
  shop_delivery numeric(12,2) not null default 0,
  note text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint sales_amounts_nonnegative_chk check (
    discount_total >= 0
    and packaging_cost >= 0
    and shop_delivery >= 0
  )
);

create index sales_sold_at_idx on public.sales(sold_at);
create index sales_customer_phone_idx on public.sales(customer_phone)
where customer_phone is not null;

create trigger sales_set_updated_at
before update on public.sales
for each row execute function public.set_updated_at();

create table public.sale_items (
  id uuid primary key default gen_random_uuid(),
  sale_id uuid not null references public.sales(id),
  product_id uuid not null references public.products(id),
  qty integer not null,
  unit_price numeric(12,2) not null,
  discount_alloc numeric(12,2) not null default 0,
  packaging_alloc numeric(12,2) not null default 0,
  shop_delivery_alloc numeric(12,2) not null default 0,
  prro_unit numeric(12,2),
  mgmt_unit numeric(12,2),
  payment_fee numeric(12,2) not null default 0,
  cost_method text not null default 'Відкладено',
  cost_audit text,
  cost_fixed_at timestamptz,
  note text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint sale_items_qty_positive_chk check (qty > 0),
  constraint sale_items_amounts_nonnegative_chk check (
    unit_price >= 0
    and discount_alloc >= 0
    and packaging_alloc >= 0
    and shop_delivery_alloc >= 0
    and payment_fee >= 0
    and (prro_unit is null or prro_unit >= 0)
    and (mgmt_unit is null or mgmt_unit >= 0)
  ),
  constraint sale_items_cost_method_chk check (
    cost_method in ('FIFO', 'FIFO+fallback', 'Fallback', 'Відкладено')
  )
);

create index sale_items_sale_id_idx on public.sale_items(sale_id);
create index sale_items_product_id_idx on public.sale_items(product_id);

create trigger sale_items_set_updated_at
before update on public.sale_items
for each row execute function public.set_updated_at();

create table public.refunds (
  id uuid primary key default gen_random_uuid(),
  sale_id uuid references public.sales(id),
  refund_type text not null,
  amount numeric(12,2) not null,
  reason text,
  refunded_at date not null,
  restock boolean not null default false,
  note text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint refunds_type_chk check (
    refund_type in (
      'partial_money_no_return',
      'partial_return',
      'full_return'
    )
  ),
  constraint refunds_amount_positive_chk check (amount > 0),
  constraint refunds_restock_chk check (
    refund_type <> 'partial_money_no_return' or not restock
  )
);

create index refunds_refunded_at_idx on public.refunds(refunded_at);

create trigger refunds_set_updated_at
before update on public.refunds
for each row execute function public.set_updated_at();

create view public.v_sale_item_financials as
select
  si.id,
  si.sale_id,
  si.product_id,
  si.qty,
  si.unit_price,
  si.discount_alloc,
  si.packaging_alloc,
  si.shop_delivery_alloc,
  si.prro_unit,
  si.mgmt_unit,
  si.payment_fee,
  si.cost_method,
  si.cost_audit,
  si.cost_fixed_at,
  (si.qty * si.unit_price - si.discount_alloc)::numeric(12,2) as revenue,
  (coalesce(si.prro_unit, 0) * si.qty)::numeric(12,2) as prro_cogs,
  (coalesce(si.mgmt_unit, 0) * si.qty)::numeric(12,2) as mgmt_cogs,
  (
    si.qty * si.unit_price
    - si.discount_alloc
    - coalesce(si.prro_unit, 0) * si.qty
  )::numeric(12,2) as gross_profit,
  (
    si.qty * si.unit_price
    - si.discount_alloc
    - coalesce(si.mgmt_unit, 0) * si.qty
    - si.packaging_alloc
    - si.payment_fee
    - si.shop_delivery_alloc
  )::numeric(12,2) as net_profit
from public.sale_items si;

create or replace function public.fn_is_actual_sale(p_sale_id uuid)
returns boolean
language sql
stable
security invoker
set search_path = public
as $$
  select coalesce((
    select
      (
        ps.code = 'paid'
        or os.code in ('shipped', 'received')
      )
      and os.code not in ('cancelled', 'refund')
      and ps.code not in ('cancelled', 'refund')
    from public.sales s
    join public.payment_statuses ps on ps.id = s.payment_status_id
    join public.order_statuses os on os.id = s.order_status_id
    where s.id = p_sale_id
  ), false);
$$;

create or replace function public.fn_fifo_cost_for_product(
  p_product_id uuid,
  p_sale_date date,
  p_qty numeric,
  p_exclude_sale_item_id uuid default null,
  p_exclude_writeoff_item_id uuid default null
)
returns table (
  prro_unit numeric,
  mgmt_unit numeric,
  cost_method text,
  cost_audit text
)
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_needed numeric := p_qty;
  v_prior_sales numeric := 0;
  v_prior_writeoffs numeric := 0;
  v_skip numeric := 0;
  v_available numeric;
  v_take numeric;
  v_prro_total numeric := 0;
  v_mgmt_total numeric := 0;
  v_fifo_qty numeric := 0;
  v_fallback_qty numeric := 0;
  v_fallback_prro numeric;
  v_fallback_mgmt numeric;
  v_audit text := '';
  v_lot record;
begin
  if p_qty <= 0 then
    raise exception 'FIFO quantity must be positive';
  end if;

  select coalesce(sum(si.qty), 0)
  into v_prior_sales
  from public.sale_items si
  join public.sales s on s.id = si.sale_id
  where si.product_id = p_product_id
    and si.id is distinct from p_exclude_sale_item_id
    and public.fn_is_actual_sale(si.sale_id)
    and (
      s.sold_at < p_sale_date
      or (
        s.sold_at = p_sale_date
        and p_exclude_sale_item_id is not null
        and si.id < p_exclude_sale_item_id
      )
    );

  if to_regclass('public.writeoff_items') is not null then
    execute $sql$
      select coalesce(sum(wi.qty), 0)
      from public.writeoff_items wi
      join public.writeoffs w on w.id = wi.writeoff_id
      where wi.product_id = $1
        and w.written_off_at <= $2
        and wi.id is distinct from $3
    $sql$
    into v_prior_writeoffs
    using p_product_id, p_sale_date, p_exclude_writeoff_item_id;
  end if;

  v_skip := v_prior_sales + v_prior_writeoffs;

  for v_lot in
    select
      plc.id,
      plc.lot_code,
      plc.qty::numeric as qty,
      plc.prro_unit,
      plc.mgmt_unit,
      plc.delivery_date
    from public.v_purchase_lot_costs plc
    where plc.product_id = p_product_id
      and plc.status in ('in_stock', 'selling', 'sold')
      and (plc.delivery_date is null or plc.delivery_date <= p_sale_date)
    order by
      plc.delivery_date nulls first,
      plc.lot_code,
      plc.id
  loop
    if v_skip >= v_lot.qty then
      v_skip := v_skip - v_lot.qty;
      continue;
    end if;

    v_available := v_lot.qty - v_skip;
    v_skip := 0;
    v_take := least(v_needed, v_available);
    v_prro_total := v_prro_total + v_take * v_lot.prro_unit;
    v_mgmt_total := v_mgmt_total + v_take * v_lot.mgmt_unit;
    v_fifo_qty := v_fifo_qty + v_take;
    v_needed := v_needed - v_take;
    v_audit := concat_ws(
      '; ',
      nullif(v_audit, ''),
      format('%s × %s', v_lot.lot_code, v_take)
    );

    exit when v_needed <= 0;
  end loop;

  if v_needed > 0 then
    select plc.prro_unit, plc.mgmt_unit
    into v_fallback_prro, v_fallback_mgmt
    from public.v_purchase_lot_costs plc
    where plc.product_id = p_product_id
      and (plc.delivery_date is null or plc.delivery_date <= p_sale_date)
    order by plc.delivery_date desc nulls last, plc.lot_code desc
    limit 1;

    if v_fallback_prro is null or v_fallback_mgmt is null then
      raise exception
        'FIFO has no cost source for product %, date %, shortage %',
        p_product_id,
        p_sale_date,
        v_needed;
    end if;

    v_prro_total := v_prro_total + v_needed * v_fallback_prro;
    v_mgmt_total := v_mgmt_total + v_needed * v_fallback_mgmt;
    v_fallback_qty := v_needed;
    v_audit := concat_ws(
      '; ',
      nullif(v_audit, ''),
      format('fallback × %s', v_needed)
    );
    v_needed := 0;
  end if;

  prro_unit := round(v_prro_total / p_qty, 2);
  mgmt_unit := round(v_mgmt_total / p_qty, 2);
  cost_method := case
    when v_fifo_qty > 0 and v_fallback_qty = 0 then 'FIFO'
    when v_fifo_qty > 0 and v_fallback_qty > 0 then 'FIFO+fallback'
    else 'Fallback'
  end;
  cost_audit := concat(
    'prior_sales=', v_prior_sales,
    '; prior_writeoffs=', v_prior_writeoffs,
    '; ', v_audit
  );
  return next;
end;
$$;

create or replace function public.fn_fix_sale_cogs(p_sale_item_id uuid)
returns public.sale_items
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_item public.sale_items%rowtype;
  v_sale public.sales%rowtype;
  v_cost record;
begin
  select * into strict v_item
  from public.sale_items
  where id = p_sale_item_id
  for update;

  select * into strict v_sale
  from public.sales
  where id = v_item.sale_id;

  if not public.fn_is_actual_sale(v_item.sale_id) then
    return v_item;
  end if;

  select * into strict v_cost
  from public.fn_fifo_cost_for_product(
    v_item.product_id,
    v_sale.sold_at,
    v_item.qty,
    v_item.id
  );

  update public.sale_items
  set
    prro_unit = v_cost.prro_unit,
    mgmt_unit = v_cost.mgmt_unit,
    cost_method = v_cost.cost_method,
    cost_audit = v_cost.cost_audit,
    cost_fixed_at = now()
  where id = v_item.id
  returning * into v_item;

  return v_item;
end;
$$;

create or replace function public.fn_fix_actual_sale_items()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_item_id uuid;
begin
  if public.fn_is_actual_sale(new.id) then
    for v_item_id in
      select id
      from public.sale_items
      where sale_id = new.id
        and cost_fixed_at is null
    loop
      perform public.fn_fix_sale_cogs(v_item_id);
    end loop;
  end if;
  return new;
end;
$$;

create trigger sales_fix_actual_cogs
after insert or update of payment_status_id, order_status_id, sold_at
on public.sales
for each row execute function public.fn_fix_actual_sale_items();

create or replace function public.fn_fix_new_sale_item()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
begin
  if new.cost_fixed_at is null and public.fn_is_actual_sale(new.sale_id) then
    perform public.fn_fix_sale_cogs(new.id);
  end if;
  return new;
end;
$$;

create trigger sale_items_fix_actual_cogs
after insert on public.sale_items
for each row execute function public.fn_fix_new_sale_item();

insert into public.sale_channels (code, name_uk, is_online)
values
  ('opencart', 'OpenCart', true),
  ('telegram', 'Telegram', true),
  ('olx', 'OLX', true),
  ('monobazar', 'Monobazar', true),
  ('other', 'Інше', null);

insert into public.payment_types (
  code,
  name_uk,
  fee_pct_config_key,
  fee_fixed_config_key,
  fee_min_config_key
)
values
  (
    'fop_control',
    'Контроль оплати ФОП',
    'fop_control_pct',
    null,
    'fop_control_min'
  ),
  (
    'cod_personal',
    'Післяплата фіз',
    'payback_fiz_pct',
    'payback_fiz_fix',
    null
  ),
  ('bank_details', 'За реквізитами', null, null, null),
  ('acquiring', 'Еквайринг', 'acquiring_pct', null, null);

insert into public.post_methods (code, name_uk)
values
  ('nova_poshta', 'НП'),
  ('ukrposhta', 'УП'),
  ('meest', 'Meest'),
  ('pickup', 'Самовивіз'),
  ('other', 'Інше');

insert into public.order_statuses (code, name_uk)
values
  ('new', 'Нове'),
  ('processing', 'В обробці'),
  ('shipped', 'Відправлено'),
  ('received', 'Отримано'),
  ('cancelled', 'Скасовано'),
  ('refund', 'Повернення'),
  ('preorder', 'Передзамовлення');

insert into public.payment_statuses (code, name_uk)
values
  ('unpaid', 'Не оплачено'),
  ('paid', 'Оплачено'),
  ('refund', 'Повернення'),
  ('cancelled', 'Скасовано');
