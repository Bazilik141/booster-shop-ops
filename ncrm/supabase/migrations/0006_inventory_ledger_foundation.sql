-- NCRM-04
-- Additive inventory ledger, FIFO valuation, fee-allocation policy, and COGS quality.
-- 0001–0005 are intentionally immutable. No cloud migration is applied by this file.

-- A physical SKU attribute. NULL means the weight is not yet reliable enough for
-- automatic allocation; it must not silently fall back to quantity allocation.
alter table public.products
  add column weight_g numeric(12,3),
  add column is_outlet boolean not null default false,
  add constraint products_weight_g_positive_chk
    check (weight_g is null or weight_g > 0);

create index products_is_outlet_idx
  on public.products(is_outlet)
  where is_outlet;

-- The cost state is about COGS quality, not the sale status. Existing rows keep
-- their stored state; only newly inserted, not-yet-actual rows start as pending.
alter table public.sale_items
  drop constraint sale_items_cost_state_chk;

alter table public.sale_items
  alter column cost_state set default 'pending';

alter table public.sale_items
  add constraint sale_items_cost_state_chk
  check (cost_state in ('pending', 'provisional', 'estimated', 'actual'));

-- Signed corrections are their own audit layer. Do not turn them into synthetic
-- purchase_lots: purchase_lots require a real purchase_id and positive quantity.
create table public.inventory_adjustments (
  id uuid primary key default gen_random_uuid(),
  adjustment_no text not null unique,
  adjustment_date date not null,
  adjustment_kind text not null,
  source_ref text,
  note text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint inventory_adjustments_kind_chk check (
    adjustment_kind in ('operating_correction', 'opening_balance')
  )
  -- TODO(NCRM-04/OWNER): extend the business taxonomy only after it is approved.
);

create index inventory_adjustments_date_idx
  on public.inventory_adjustments(adjustment_date);

create trigger inventory_adjustments_set_updated_at
before update on public.inventory_adjustments
for each row execute function public.set_updated_at();

create table public.inventory_adjustment_items (
  id uuid primary key default gen_random_uuid(),
  adjustment_id uuid not null references public.inventory_adjustments(id),
  product_id uuid not null references public.products(id),
  qty_delta integer not null,
  prro_unit numeric(12,2) not null,
  mgmt_unit numeric(12,2) not null,
  cost_audit text not null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint inventory_adjustment_items_qty_delta_chk check (qty_delta <> 0),
  constraint inventory_adjustment_items_costs_nonnegative_chk check (
    prro_unit >= 0 and mgmt_unit >= 0
  )
);

create index inventory_adjustment_items_product_idx
  on public.inventory_adjustment_items(product_id, adjustment_id);

create trigger inventory_adjustment_items_set_updated_at
before update on public.inventory_adjustment_items
for each row execute function public.set_updated_at();

-- Opening balances remain inventory layers but are excluded from operating P&L.
-- This is deliberately not joined into v_pnl_monthly until NCRM-07.
create view public.v_inventory_adjustment_pnl as
select
  ia.adjustment_date,
  iai.product_id,
  ia.adjustment_kind,
  (ia.adjustment_kind = 'operating_correction') as is_operating_pnl,
  sum(iai.qty_delta)::numeric as qty_delta,
  sum(iai.qty_delta * iai.prro_unit)::numeric(14,2) as prro_variance_uah,
  sum(
    case
      when ia.adjustment_kind = 'operating_correction'
        then iai.qty_delta * iai.mgmt_unit
      else 0
    end
  )::numeric(14,2) as mgmt_variance_uah
from public.inventory_adjustments ia
join public.inventory_adjustment_items iai on iai.adjustment_id = ia.id
group by ia.adjustment_date, iai.product_id, ia.adjustment_kind;

-- Per-fee allocation records are the audit source for future manual purchases.
-- A purchase may still have legacy per-lot values without records; once a fee has
-- records, its full amount and one allocation method are enforced at commit time.
create table public.purchase_lot_fee_allocations (
  id uuid primary key default gen_random_uuid(),
  purchase_lot_id uuid not null references public.purchase_lots(id),
  fee_type text not null,
  allocation_method text not null,
  allocation_basis numeric(18,3),
  allocated_uah numeric(12,2) not null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (purchase_lot_id, fee_type),
  constraint purchase_lot_fee_allocations_fee_type_chk check (
    fee_type in ('forwarding_fee', 'intl_shipping', 'local_delivery')
  ),
  constraint purchase_lot_fee_allocations_method_chk check (
    allocation_method in ('weight', 'value', 'manual')
  ),
  constraint purchase_lot_fee_allocations_basis_chk check (
    allocation_basis is null or allocation_basis > 0
  ),
  constraint purchase_lot_fee_allocations_amount_chk check (allocated_uah >= 0)
);

create index purchase_lot_fee_allocations_lot_idx
  on public.purchase_lot_fee_allocations(purchase_lot_id);

create trigger purchase_lot_fee_allocations_set_updated_at
before update on public.purchase_lot_fee_allocations
for each row execute function public.set_updated_at();

create or replace function public.fn_assert_purchase_fee_allocation(
  p_purchase_id uuid,
  p_fee_type text
)
returns void
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_count integer;
  v_methods integer;
  v_allocated numeric(12,2);
  v_fee numeric(12,2);
begin
  select
    count(*),
    count(distinct a.allocation_method),
    coalesce(sum(a.allocated_uah), 0)::numeric(12,2)
  into v_count, v_methods, v_allocated
  from public.purchase_lot_fee_allocations a
  join public.purchase_lots pl on pl.id = a.purchase_lot_id
  where pl.purchase_id = p_purchase_id
    and a.fee_type = p_fee_type;

  -- Imported legacy lots predate this policy, so absence is valid. Partial or
  -- mixed-method records are never valid once allocation has started.
  if v_count = 0 then
    return;
  end if;

  select case p_fee_type
    when 'forwarding_fee' then p.forwarding_fee_uah
    when 'intl_shipping' then p.intl_shipping_uah
    when 'local_delivery' then p.local_delivery_uah
  end
  into v_fee
  from public.purchases p
  where p.id = p_purchase_id;

  if v_methods <> 1 then
    raise exception
      'Purchase % fee % has mixed allocation methods', p_purchase_id, p_fee_type;
  end if;

  if v_allocated <> v_fee then
    raise exception
      'Purchase % fee % allocation % must equal source fee %',
      p_purchase_id, p_fee_type, v_allocated, v_fee;
  end if;
end;
$$;

create or replace function public.fn_validate_purchase_lot_fee_allocation()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_purchase_id uuid;
  v_fee_type text;
begin
  if tg_op = 'DELETE' then
    select purchase_id into v_purchase_id
    from public.purchase_lots
    where id = old.purchase_lot_id;
    v_fee_type := old.fee_type;
  else
    select purchase_id into v_purchase_id
    from public.purchase_lots
    where id = new.purchase_lot_id;
    v_fee_type := new.fee_type;
  end if;

  perform public.fn_assert_purchase_fee_allocation(v_purchase_id, v_fee_type);
  if tg_op = 'UPDATE' and old.fee_type is distinct from new.fee_type then
    select purchase_id into v_purchase_id
    from public.purchase_lots
    where id = old.purchase_lot_id;
    perform public.fn_assert_purchase_fee_allocation(v_purchase_id, old.fee_type);
  end if;
  return null;
end;
$$;

create constraint trigger purchase_lot_fee_allocations_validate_total
after insert or update or delete on public.purchase_lot_fee_allocations
deferrable initially deferred
for each row execute function public.fn_validate_purchase_lot_fee_allocation();

create or replace function public.fn_allocate_purchase_shared_fee(
  p_purchase_id uuid,
  p_fee_type text,
  p_allocation_method text,
  p_manual_allocations jsonb default null
)
returns void
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_fee numeric(12,2);
  v_basis_total numeric;
  v_lot_count integer;
  v_manual_total numeric(12,2);
begin
  if p_fee_type not in ('forwarding_fee', 'intl_shipping', 'local_delivery') then
    raise exception 'Unsupported purchase fee type: %', p_fee_type;
  end if;
  if p_allocation_method not in ('weight', 'value', 'manual') then
    raise exception 'Unsupported allocation method: %', p_allocation_method;
  end if;

  select case p_fee_type
    when 'forwarding_fee' then p.forwarding_fee_uah
    when 'intl_shipping' then p.intl_shipping_uah
    when 'local_delivery' then p.local_delivery_uah
  end
  into v_fee
  from public.purchases p
  where p.id = p_purchase_id;

  if v_fee is null then
    raise exception 'Purchase not found: %', p_purchase_id;
  end if;

  select count(*) into v_lot_count
  from public.purchase_lots pl
  where pl.purchase_id = p_purchase_id;
  if v_lot_count = 0 then
    raise exception 'Purchase % has no lots to allocate', p_purchase_id;
  end if;

  delete from public.purchase_lot_fee_allocations a
  using public.purchase_lots pl
  where a.purchase_lot_id = pl.id
    and pl.purchase_id = p_purchase_id
    and a.fee_type = p_fee_type;

  if p_allocation_method = 'manual' then
    if jsonb_typeof(p_manual_allocations) <> 'object' then
      raise exception 'Manual allocation must be a JSON object keyed by purchase_lot_id';
    end if;

    if exists (
      select 1
      from public.purchase_lots pl
      where pl.purchase_id = p_purchase_id
        and (p_manual_allocations ->> pl.id::text) is null
    ) then
      raise exception 'Manual allocation must provide every lot of purchase %', p_purchase_id;
    end if;

    select coalesce(sum((p_manual_allocations ->> pl.id::text)::numeric), 0)::numeric(12,2)
    into v_manual_total
    from public.purchase_lots pl
    where pl.purchase_id = p_purchase_id;
    if v_manual_total <> v_fee then
      raise exception
        'Manual allocation % must equal source fee %', v_manual_total, v_fee;
    end if;

    insert into public.purchase_lot_fee_allocations (
      purchase_lot_id, fee_type, allocation_method, allocation_basis, allocated_uah
    )
    select
      pl.id,
      p_fee_type,
      'manual',
      null,
      (p_manual_allocations ->> pl.id::text)::numeric(12,2)
    from public.purchase_lots pl
    where pl.purchase_id = p_purchase_id;
  else
    select sum(
      case p_allocation_method
        when 'weight' then pl.qty * p.weight_g
        when 'value' then pl.goods_cost_uah
      end
    )
    into v_basis_total
    from public.purchase_lots pl
    join public.products p on p.id = pl.product_id
    where pl.purchase_id = p_purchase_id;

    if v_basis_total is null or v_basis_total <= 0 then
      raise exception
        'Purchase % cannot use % allocation without reliable positive bases',
        p_purchase_id, p_allocation_method;
    end if;

    if p_allocation_method = 'weight' and exists (
      select 1
      from public.purchase_lots pl
      join public.products p on p.id = pl.product_id
      where pl.purchase_id = p_purchase_id
        and p.weight_g is null
    ) then
      raise exception 'Weight allocation requires weight_g for every purchase lot';
    end if;

    with bases as (
      select
        pl.id as purchase_lot_id,
        pl.lot_code,
        case p_allocation_method
          when 'weight' then pl.qty * p.weight_g
          when 'value' then pl.goods_cost_uah
        end::numeric as allocation_basis
      from public.purchase_lots pl
      join public.products p on p.id = pl.product_id
      where pl.purchase_id = p_purchase_id
    ), rounded as (
      select
        b.*,
        row_number() over (order by b.lot_code, b.purchase_lot_id) as row_no,
        round(v_fee * b.allocation_basis / v_basis_total, 2) as proposed_uah
      from bases b
    ), allocated as (
      select
        r.*,
        case
          when r.row_no = 1 then r.proposed_uah
            + (v_fee - sum(r.proposed_uah) over ())
          else r.proposed_uah
        end::numeric(12,2) as allocated_uah
      from rounded r
    )
    insert into public.purchase_lot_fee_allocations (
      purchase_lot_id, fee_type, allocation_method, allocation_basis, allocated_uah
    )
    select
      purchase_lot_id,
      p_fee_type,
      p_allocation_method,
      allocation_basis,
      allocated_uah
    from allocated;
  end if;

  update public.purchase_lots pl
  set
    forwarding_fee_uah = case
      when p_fee_type = 'forwarding_fee' then a.allocated_uah
      else pl.forwarding_fee_uah
    end,
    intl_shipping_uah = case
      when p_fee_type = 'intl_shipping' then a.allocated_uah
      else pl.intl_shipping_uah
    end,
    local_delivery_uah = case
      when p_fee_type = 'local_delivery' then a.allocated_uah
      else pl.local_delivery_uah
    end
  from public.purchase_lot_fee_allocations a
  where a.purchase_lot_id = pl.id
    and a.fee_type = p_fee_type
    and pl.purchase_id = p_purchase_id;

  perform public.fn_assert_purchase_fee_allocation(p_purchase_id, p_fee_type);
end;
$$;

-- Canonical physical FIFO layers. cancelled is intentionally absent: it is a
-- legacy terminal/non-stock state. ordered and in_transit remain asset-only.
create view public.v_inventory_cost_layers as
select
  'purchase_lot'::text as source_kind,
  plc.id as source_id,
  plc.lot_code as layer_code,
  plc.product_id,
  coalesce(plc.delivery_date, p.ordered_at) as layer_date,
  plc.qty::numeric as qty,
  plc.prro_unit,
  plc.mgmt_unit,
  case
    when plc.status in ('in_stock', 'selling', 'sold') then 'warehouse'
    when plc.status in ('ordered', 'in_transit') then 'asset_only'
  end::text as layer_scope
from public.v_purchase_lot_costs plc
join public.purchases p on p.id = plc.purchase_id
where plc.status in ('ordered', 'in_transit', 'in_stock', 'selling', 'sold')
union all
select
  'inventory_adjustment'::text,
  iai.id,
  ia.adjustment_no,
  iai.product_id,
  ia.adjustment_date,
  iai.qty_delta::numeric,
  iai.prro_unit,
  iai.mgmt_unit,
  'warehouse'::text
from public.inventory_adjustments ia
join public.inventory_adjustment_items iai on iai.adjustment_id = ia.id
where iai.qty_delta > 0;

create view public.v_inventory_consumptions as
select
  'sale_item'::text as source_kind,
  si.id as source_id,
  si.product_id,
  s.sold_at as consumption_date,
  si.qty::numeric as qty
from public.sale_items si
join public.sales s on s.id = si.sale_id
where public.fn_is_actual_sale(si.sale_id)
union all
select
  'writeoff_item'::text,
  wi.id,
  wi.product_id,
  w.written_off_at,
  wi.qty::numeric
from public.writeoff_items wi
join public.writeoffs w on w.id = wi.writeoff_id
union all
select
  'inventory_adjustment'::text,
  iai.id,
  iai.product_id,
  ia.adjustment_date,
  abs(iai.qty_delta)::numeric
from public.inventory_adjustments ia
join public.inventory_adjustment_items iai on iai.adjustment_id = ia.id
where iai.qty_delta < 0;

-- Computes FIFO remainders in chronological order. A shortage before a later
-- receipt cannot consume that future layer; it remains visible for review rather
-- than being silently rewritten into history.
create or replace function public.fn_inventory_fifo_layers(
  p_as_of date default current_date
)
returns table (
  source_kind text,
  source_id uuid,
  layer_code text,
  product_id uuid,
  layer_date date,
  initial_qty numeric,
  remaining_qty numeric,
  prro_unit numeric,
  mgmt_unit numeric
)
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_event record;
  v_product_id uuid;
  v_idx integer;
  v_needed numeric;
  v_take numeric;
  v_queue_kind text[] := array[]::text[];
  v_queue_id uuid[] := array[]::uuid[];
  v_queue_code text[] := array[]::text[];
  v_queue_date date[] := array[]::date[];
  v_queue_initial numeric[] := array[]::numeric[];
  v_queue_remaining numeric[] := array[]::numeric[];
  v_queue_prro numeric[] := array[]::numeric[];
  v_queue_mgmt numeric[] := array[]::numeric[];
begin
  for v_event in
    select *
    from (
      select
        l.product_id,
        l.layer_date as event_date,
        0 as event_order,
        l.source_kind,
        l.source_id,
        l.layer_code,
        l.qty,
        l.prro_unit,
        l.mgmt_unit
      from public.v_inventory_cost_layers l
      where l.layer_scope = 'warehouse'
      union all
      select
        c.product_id,
        c.consumption_date,
        1,
        c.source_kind,
        c.source_id,
        null::text,
        c.qty,
        null::numeric,
        null::numeric
      from public.v_inventory_consumptions c
    ) events
    where event_date <= p_as_of
    order by product_id, event_date, event_order, source_kind, source_id
  loop
    if v_product_id is distinct from v_event.product_id then
      if v_product_id is not null then
        for v_idx in 1..coalesce(array_length(v_queue_id, 1), 0) loop
          source_kind := v_queue_kind[v_idx];
          source_id := v_queue_id[v_idx];
          layer_code := v_queue_code[v_idx];
          product_id := v_product_id;
          layer_date := v_queue_date[v_idx];
          initial_qty := v_queue_initial[v_idx];
          remaining_qty := v_queue_remaining[v_idx];
          prro_unit := v_queue_prro[v_idx];
          mgmt_unit := v_queue_mgmt[v_idx];
          return next;
        end loop;
      end if;

      v_product_id := v_event.product_id;
      v_queue_kind := array[]::text[];
      v_queue_id := array[]::uuid[];
      v_queue_code := array[]::text[];
      v_queue_date := array[]::date[];
      v_queue_initial := array[]::numeric[];
      v_queue_remaining := array[]::numeric[];
      v_queue_prro := array[]::numeric[];
      v_queue_mgmt := array[]::numeric[];
    end if;

    if v_event.event_order = 0 then
      v_queue_kind := array_append(v_queue_kind, v_event.source_kind);
      v_queue_id := array_append(v_queue_id, v_event.source_id);
      v_queue_code := array_append(v_queue_code, v_event.layer_code);
      v_queue_date := array_append(v_queue_date, v_event.event_date);
      v_queue_initial := array_append(v_queue_initial, v_event.qty);
      v_queue_remaining := array_append(v_queue_remaining, v_event.qty);
      v_queue_prro := array_append(v_queue_prro, v_event.prro_unit);
      v_queue_mgmt := array_append(v_queue_mgmt, v_event.mgmt_unit);
    else
      v_needed := v_event.qty;
      for v_idx in 1..coalesce(array_length(v_queue_id, 1), 0) loop
        exit when v_needed <= 0;
        if v_queue_remaining[v_idx] <= 0 then
          continue;
        end if;
        v_take := least(v_needed, v_queue_remaining[v_idx]);
        v_queue_remaining[v_idx] := v_queue_remaining[v_idx] - v_take;
        v_needed := v_needed - v_take;
      end loop;
    end if;
  end loop;

  if v_product_id is not null then
    for v_idx in 1..coalesce(array_length(v_queue_id, 1), 0) loop
      source_kind := v_queue_kind[v_idx];
      source_id := v_queue_id[v_idx];
      layer_code := v_queue_code[v_idx];
      product_id := v_product_id;
      layer_date := v_queue_date[v_idx];
      initial_qty := v_queue_initial[v_idx];
      remaining_qty := v_queue_remaining[v_idx];
      prro_unit := v_queue_prro[v_idx];
      mgmt_unit := v_queue_mgmt[v_idx];
      return next;
    end loop;
  end if;
end;
$$;

create view public.v_inventory_fifo_valuation as
with warehouse as (
  select
    l.product_id,
    sum(l.remaining_qty)::numeric as warehouse_qty,
    sum(l.remaining_qty * l.prro_unit)::numeric(14,2) as warehouse_prro_cost,
    sum(l.remaining_qty * l.mgmt_unit)::numeric(14,2) as warehouse_mgmt_cost
  from public.fn_inventory_fifo_layers(current_date) l
  group by l.product_id
), inbound as (
  select
    l.product_id,
    sum(l.qty)::numeric as inbound_qty,
    sum(l.qty * l.prro_unit)::numeric(14,2) as inbound_prro_cost,
    sum(l.qty * l.mgmt_unit)::numeric(14,2) as inbound_mgmt_cost
  from public.v_inventory_cost_layers l
  where l.layer_scope = 'asset_only'
  group by l.product_id
), products_in_scope as (
  select product_id from warehouse
  union
  select product_id from inbound
)
select
  p.id as product_id,
  p.sku,
  p.name,
  coalesce(w.warehouse_qty, 0)::numeric as warehouse_qty,
  coalesce(i.inbound_qty, 0)::numeric as inbound_qty,
  (coalesce(w.warehouse_qty, 0) + coalesce(i.inbound_qty, 0))::numeric as asset_qty,
  coalesce(w.warehouse_prro_cost, 0)::numeric(14,2) as warehouse_prro_cost,
  coalesce(w.warehouse_mgmt_cost, 0)::numeric(14,2) as warehouse_mgmt_cost,
  (
    coalesce(w.warehouse_prro_cost, 0) + coalesce(i.inbound_prro_cost, 0)
  )::numeric(14,2) as asset_prro_cost,
  (
    coalesce(w.warehouse_mgmt_cost, 0) + coalesce(i.inbound_mgmt_cost, 0)
  )::numeric(14,2) as asset_mgmt_cost
from products_in_scope s
join public.products p on p.id = s.product_id
left join warehouse w on w.product_id = p.id
left join inbound i on i.product_id = p.id;

-- Keep the established FIFO interface, but include signed adjustments as real
-- layers/consumption and expose fallback costing as estimated rather than actual.
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
  v_prior_adjustments numeric := 0;
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

  select coalesce(sum(wi.qty), 0)
  into v_prior_writeoffs
  from public.writeoff_items wi
  join public.writeoffs w on w.id = wi.writeoff_id
  where wi.product_id = p_product_id
    and w.written_off_at <= p_sale_date
    and wi.id is distinct from p_exclude_writeoff_item_id;

  select coalesce(sum(abs(iai.qty_delta)), 0)
  into v_prior_adjustments
  from public.inventory_adjustment_items iai
  join public.inventory_adjustments ia on ia.id = iai.adjustment_id
  where iai.product_id = p_product_id
    and iai.qty_delta < 0
    and ia.adjustment_date <= p_sale_date;

  v_skip := v_prior_sales + v_prior_writeoffs + v_prior_adjustments;

  for v_lot in
    select
      l.source_id,
      l.layer_code,
      l.qty,
      l.prro_unit,
      l.mgmt_unit,
      l.layer_date
    from public.v_inventory_cost_layers l
    where l.product_id = p_product_id
      and l.layer_scope = 'warehouse'
      and l.layer_date <= p_sale_date
    order by l.layer_date, l.layer_code, l.source_id
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
      format('%s × %s', v_lot.layer_code, v_take)
    );
    exit when v_needed <= 0;
  end loop;

  if v_needed > 0 then
    select l.prro_unit, l.mgmt_unit
    into v_fallback_prro, v_fallback_mgmt
    from public.v_inventory_cost_layers l
    where l.product_id = p_product_id
      and l.layer_scope = 'warehouse'
      and l.layer_date <= p_sale_date
    order by l.layer_date desc, l.layer_code desc, l.source_id desc
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
    '; prior_adjustments=', v_prior_adjustments,
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
    cost_state = case
      when v_cost.cost_method in ('FIFO+fallback', 'Fallback') then 'estimated'
      else 'actual'
    end,
    cost_audit = v_cost.cost_audit,
    cost_fixed_at = now()
  where id = v_item.id
  returning * into v_item;

  return v_item;
end;
$$;

-- 0003 treated the old default 'actual' as the signal to run FIFO. With the
-- corrected default 'pending', preserve that transition while leaving Mystery
-- 'provisional' rows exclusively to their existing refresh flow.
create or replace function public.fn_fix_new_sale_item()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
begin
  if new.cost_state in ('pending', 'actual')
    and new.cost_fixed_at is null
    and public.fn_is_actual_sale(new.sale_id)
  then
    perform public.fn_fix_sale_cogs(new.id);
  end if;
  return new;
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
        and cost_state in ('pending', 'actual')
        and cost_fixed_at is null
    loop
      perform public.fn_fix_sale_cogs(v_item_id);
    end loop;
  end if;
  return new;
end;
$$;

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
where si.cost_state = 'estimated';
