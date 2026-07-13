-- NCRM-05
-- Additive Mystery fulfillment, reservations, and atomic shipment commit.
-- 0001–0006 remain immutable. This migration does not contact cloud Supabase.

-- `NULL` keeps component eligibility automatic. Only an explicit exclusion is
-- allowed; an allow-list would violate the approved Mystery Box contract.
alter table public.products
  add column is_sealed_pack boolean not null default false,
  add column mystery_eligibility_override text,
  add constraint products_mystery_eligibility_override_chk
    check (
      mystery_eligibility_override is null
      or mystery_eligibility_override = 'excluded'
    );

create index products_mystery_component_idx
  on public.products(game_code, language_code)
  where is_active
    and is_sealed_pack
    and not is_outlet
    and mystery_eligibility_override is null;

-- A fulfillment is intentionally one-to-one with the sold Mystery line, not
-- merely its parent sale: one order can contain several independently built boxes.
create table public.mystery_fulfillments (
  id uuid primary key default gen_random_uuid(),
  sale_item_id uuid not null unique references public.sale_items(id),
  state text not null default 'needs_assembly',
  reserved_at timestamptz,
  committed_at timestamptz,
  released_at timestamptz,
  reversed_at timestamptz,
  note text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint mystery_fulfillments_state_chk check (
    state in ('needs_assembly', 'reserved', 'committed', 'released', 'reversed')
  )
);

create trigger mystery_fulfillments_set_updated_at
before update on public.mystery_fulfillments
for each row execute function public.set_updated_at();

-- One reservation is one selected component SKU for one fulfillment. It reduces
-- Available quantity only while active; the MBOX writeoff remains the physical
-- inventory consumption at commit.
create table public.inventory_reservations (
  id uuid primary key default gen_random_uuid(),
  fulfillment_id uuid not null references public.mystery_fulfillments(id),
  product_id uuid not null references public.products(id),
  qty integer not null,
  state text not null default 'active',
  created_at timestamptz not null default now(),
  released_at timestamptz,
  committed_at timestamptz,
  constraint inventory_reservations_qty_positive_chk check (qty > 0),
  constraint inventory_reservations_state_chk check (
    state in ('active', 'released', 'committed')
  ),
  constraint inventory_reservations_fulfillment_product_uq
    unique (fulfillment_id, product_id)
);

create index inventory_reservations_active_product_idx
  on public.inventory_reservations(product_id)
  where state = 'active';

create table public.mystery_fulfillment_items (
  id uuid primary key default gen_random_uuid(),
  fulfillment_id uuid not null references public.mystery_fulfillments(id),
  reservation_id uuid not null unique references public.inventory_reservations(id),
  product_id uuid not null references public.products(id),
  qty integer not null,
  created_at timestamptz not null default now(),
  constraint mystery_fulfillment_items_qty_positive_chk check (qty > 0),
  constraint mystery_fulfillment_items_fulfillment_product_uq
    unique (fulfillment_id, product_id)
);

create index mystery_fulfillment_items_fulfillment_idx
  on public.mystery_fulfillment_items(fulfillment_id);

-- MBOX writes are explicitly tied to a fulfillment. NOT VALID preserves any
-- pre-NCRM-05 historical MBOX rows if this is ever applied to a populated DB,
-- while still enforcing the invariant for every later write.
alter table public.writeoffs
  add column mystery_fulfillment_id uuid
    references public.mystery_fulfillments(id),
  add constraint writeoffs_mbox_fulfillment_chk
    check (
      (type = 'MBOX'
        and mystery_sale_id is not null
        and mystery_fulfillment_id is not null)
      or (type <> 'MBOX' and mystery_fulfillment_id is null)
    ) not valid;

create unique index writeoffs_mbox_fulfillment_uq
  on public.writeoffs(mystery_fulfillment_id)
  where mystery_fulfillment_id is not null;

-- A reservation-aware operational balance. Existing stock/KPI views stay
-- unchanged; consumers that must prevent over-allocation use this new view.
create view public.v_inventory_available as
with reserved as (
  select
    product_id,
    sum(qty)::numeric as reserved_qty
  from public.inventory_reservations
  where state = 'active'
  group by product_id
)
select
  p.id as product_id,
  p.sku,
  p.name,
  coalesce(v.warehouse_qty, 0)::numeric as physical_qty,
  coalesce(r.reserved_qty, 0)::numeric as reserved_qty,
  (
    coalesce(v.warehouse_qty, 0) - coalesce(r.reserved_qty, 0)
  )::numeric as available_qty
from public.products p
left join public.v_inventory_fifo_valuation v on v.product_id = p.id
left join reserved r on r.product_id = p.id;

-- The only catalogue backfill required here is for immutable reference codes:
-- the NCRM-03 importer uses these exact codes too. There is deliberately no
-- backfill for is_sealed_pack; owner/catalogue confirmation is required first.
insert into public.games (code, name)
values
  ('pokemon', 'Pokémon'),
  ('one_piece', 'One Piece')
on conflict (code) do nothing;

insert into public.product_languages (code, name)
values ('jp', 'JP')
on conflict (code) do nothing;

-- New operational SKUs. The generic seeds remain as historical records but
-- cannot create new operational Mystery sale lines or stock-alert entries.
insert into public.products (sku, name, category_code, game_code, language_code)
values
  ('PKM-JP-MBX-ST', 'Pokémon — Mystery Box ST — JP', 'mystery_box', 'pokemon', 'jp'),
  ('OP-JP-MBX-ST', 'One Piece — Mystery Box ST — JP', 'mystery_box', 'one_piece', 'jp'),
  ('PKM-JP-MBX-XL', 'Pokémon — Mystery Box XL — JP', 'mystery_box', 'pokemon', 'jp'),
  ('OP-JP-MBX-XL', 'One Piece — Mystery Box XL — JP', 'mystery_box', 'one_piece', 'jp')
on conflict (sku) do nothing;

insert into public.product_prices (
  product_id,
  rrc,
  source,
  effective_from
)
select
  p.id,
  seed.rrc,
  'NCRM-05 approved Mystery SKU seed',
  date '2026-07-13'
from (
  values
    ('PKM-JP-MBX-ST'::text, 700::numeric),
    ('OP-JP-MBX-ST'::text, 700::numeric),
    ('PKM-JP-MBX-XL'::text, 950::numeric),
    ('OP-JP-MBX-XL'::text, 950::numeric)
) as seed(sku, rrc)
join public.products p on p.sku = seed.sku
where not exists (
  select 1
  from public.product_prices pp
  where pp.product_id = p.id
    and pp.source = 'NCRM-05 approved Mystery SKU seed'
);

insert into public.mystery_box_types (
  product_id,
  expected_pack_count,
  has_holo,
  holo_cost,
  provisional_unit_cost
)
select
  p.id,
  seed.expected_pack_count,
  seed.has_holo,
  seed.holo_cost,
  seed.provisional_unit_cost
from (
  values
    ('PKM-JP-MBX-ST'::text, 5, false, 0::numeric, 450::numeric),
    ('OP-JP-MBX-ST'::text, 5, false, 0::numeric, 450::numeric),
    ('PKM-JP-MBX-XL'::text, 7, true, 75::numeric, 700::numeric),
    ('OP-JP-MBX-XL'::text, 7, true, 75::numeric, 700::numeric)
) as seed(
  sku,
  expected_pack_count,
  has_holo,
  holo_cost,
  provisional_unit_cost
)
join public.products p on p.sku = seed.sku
on conflict (product_id) do nothing;

update public.products
set
  is_active = false,
  archived_at = coalesce(archived_at, now())
where sku in ('MBX', 'MBX-XL');

-- Virtual Mystery products are sold as a line but never consume a physical SKU;
-- their selected components are consumed by the linked MBOX writeoff instead.
create or replace view public.v_inventory_consumptions as
select
  'sale_item'::text as source_kind,
  si.id as source_id,
  si.product_id,
  s.sold_at as consumption_date,
  si.qty::numeric as qty
from public.sale_items si
join public.sales s on s.id = si.sale_id
where public.fn_is_actual_sale(si.sale_id)
  and not exists (
    select 1
    from public.mystery_box_types mbt
    where mbt.product_id = si.product_id
  )
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

-- Component pool: matching game, JP, explicitly sealed, active, non-Outlet,
-- no exclusion override, and physically available after active reservations.
create view public.v_mystery_eligible_components as
select
  mbt.product_id as mystery_product_id,
  mp.sku as mystery_sku,
  cp.id as component_product_id,
  cp.sku as component_sku,
  cp.name as component_name,
  a.physical_qty,
  a.reserved_qty,
  a.available_qty
from public.mystery_box_types mbt
join public.products mp on mp.id = mbt.product_id
join public.products cp
  on cp.game_code = mp.game_code
 and cp.language_code = 'jp'
join public.v_inventory_available a on a.product_id = cp.id
where mp.is_active
  and cp.is_active
  and cp.is_sealed_pack
  and not cp.is_outlet
  and cp.mystery_eligibility_override is null
  and a.available_qty > 0;

create or replace function public.fn_guard_mystery_sale_item()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
begin
  if exists (
    select 1
    from public.mystery_box_types mbt
    join public.products p on p.id = mbt.product_id
    where mbt.product_id = new.product_id
      and not p.is_active
  ) then
    raise exception
      'Archived Mystery SKU % cannot enter a new operational sale', new.product_id;
  end if;
  return new;
end;
$$;

create trigger sale_items_mystery_active_guard
before insert on public.sale_items
for each row execute function public.fn_guard_mystery_sale_item();

create or replace function public.fn_create_mystery_fulfillment()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
begin
  if exists (
    select 1
    from public.mystery_box_types
    where product_id = new.product_id
  ) then
    insert into public.mystery_fulfillments (sale_item_id)
    values (new.id);
  end if;
  return new;
end;
$$;

create trigger sale_items_create_mystery_fulfillment
after insert on public.sale_items
for each row execute function public.fn_create_mystery_fulfillment();

create or replace function public.fn_guard_mystery_fulfillment_item()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_reservation public.inventory_reservations%rowtype;
begin
  if current_setting('app.mystery_reserve', true) is distinct from 'on' then
    raise exception 'Mystery fulfillment items must be created by fn_reserve_mystery_fulfillment';
  end if;

  if tg_op = 'DELETE' then
    raise exception 'Mystery fulfillment items are immutable after reservation';
  end if;

  select * into strict v_reservation
  from public.inventory_reservations
  where id = new.reservation_id;

  if v_reservation.fulfillment_id <> new.fulfillment_id
    or v_reservation.product_id <> new.product_id
    or v_reservation.qty <> new.qty
  then
    raise exception 'Mystery fulfillment item must exactly match its reservation';
  end if;
  return new;
end;
$$;

create trigger mystery_fulfillment_items_guard
before insert or update or delete on public.mystery_fulfillment_items
for each row execute function public.fn_guard_mystery_fulfillment_item();

create or replace function public.fn_guard_inventory_reservation()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
begin
  if tg_op = 'INSERT' then
    if current_setting('app.mystery_reserve', true) is distinct from 'on' then
      raise exception 'Reservations must be created by fn_reserve_mystery_fulfillment';
    end if;
    return new;
  end if;

  if tg_op = 'DELETE' then
    raise exception 'Reservations are released or committed, never deleted';
  end if;

  if old.state = 'active' and new.state = 'released'
    and current_setting('app.mystery_release', true) = 'on'
  then
    return new;
  end if;

  if old.state = 'active' and new.state = 'committed'
    and current_setting('app.mystery_commit', true) = 'on'
  then
    return new;
  end if;

  raise exception 'Invalid reservation state transition % -> %', old.state, new.state;
end;
$$;

create trigger inventory_reservations_guard
before insert or update or delete on public.inventory_reservations
for each row execute function public.fn_guard_inventory_reservation();

create or replace function public.fn_guard_mystery_fulfillment_state()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_sale_item public.sale_items%rowtype;
  v_type public.mystery_box_types%rowtype;
  v_item_qty integer;
begin
  if old.state = new.state then
    return new;
  end if;

  select * into strict v_sale_item
  from public.sale_items
  where id = new.sale_item_id;

  select * into strict v_type
  from public.mystery_box_types
  where product_id = v_sale_item.product_id;

  if old.state = 'needs_assembly' and new.state = 'reserved' then
    if current_setting('app.mystery_reserve', true) is distinct from 'on' then
      raise exception 'Mystery fulfillment reservation must use fn_reserve_mystery_fulfillment';
    end if;

    select coalesce(sum(mfi.qty), 0) into v_item_qty
    from public.mystery_fulfillment_items mfi
    join public.inventory_reservations r on r.id = mfi.reservation_id
    where mfi.fulfillment_id = new.id
      and r.state = 'active';

    if v_item_qty <> v_type.expected_pack_count * v_sale_item.qty then
      raise exception
        'Mystery fulfillment % has % reserved packs; expected %',
        new.id,
        v_item_qty,
        v_type.expected_pack_count * v_sale_item.qty;
    end if;
    new.reserved_at := coalesce(new.reserved_at, now());
    return new;
  end if;

  if old.state = 'reserved' and new.state = 'released' then
    if current_setting('app.mystery_release', true) is distinct from 'on' then
      raise exception 'Reserved Mystery fulfillment must be released by the cancellation path';
    end if;
    new.released_at := coalesce(new.released_at, now());
    return new;
  end if;

  if old.state = 'reserved' and new.state = 'committed' then
    if current_setting('app.mystery_commit', true) is distinct from 'on'
      or new.committed_at is null
    then
      raise exception 'Mystery fulfillment commit must use fn_commit_mystery_fulfillment';
    end if;
    return new;
  end if;

  if old.state = 'committed' and new.state = 'reversed' then
    if current_setting('app.mystery_reverse', true) is distinct from 'on' then
      raise exception 'Mystery reversal belongs to the NCRM-06 return path';
    end if;
    new.reversed_at := coalesce(new.reversed_at, now());
    return new;
  end if;

  raise exception 'Invalid Mystery fulfillment state transition % -> %', old.state, new.state;
end;
$$;

create trigger mystery_fulfillments_state_guard
before update of state on public.mystery_fulfillments
for each row execute function public.fn_guard_mystery_fulfillment_state();

create or replace function public.fn_guard_mbox_writeoff()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_sale_id uuid;
begin
  if new.type <> 'MBOX' then
    return new;
  end if;

  if current_setting('app.mystery_commit', true) is distinct from 'on' then
    raise exception 'MBOX writeoffs must be created by fn_commit_mystery_fulfillment';
  end if;

  select si.sale_id into strict v_sale_id
  from public.mystery_fulfillments mf
  join public.sale_items si on si.id = mf.sale_item_id
  where mf.id = new.mystery_fulfillment_id;

  if new.mystery_sale_id is distinct from v_sale_id then
    raise exception 'MBOX writeoff sale must match its Mystery fulfillment';
  end if;
  return new;
end;
$$;

create trigger writeoffs_mbox_guard
before insert or update on public.writeoffs
for each row execute function public.fn_guard_mbox_writeoff();

create or replace function public.fn_guard_mbox_writeoff_item()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_type text;
begin
  select type into strict v_type
  from public.writeoffs
  where id = coalesce(new.writeoff_id, old.writeoff_id);

  if v_type = 'MBOX'
    and current_setting('app.mystery_commit', true) is distinct from 'on'
  then
    raise exception 'MBOX writeoff items must be created by fn_commit_mystery_fulfillment';
  end if;

  return case when tg_op = 'DELETE' then old else new end;
end;
$$;

create trigger writeoff_items_mbox_guard
before insert or update or delete on public.writeoff_items
for each row execute function public.fn_guard_mbox_writeoff_item();

create or replace function public.fn_guard_mystery_contents()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_fulfillment_id uuid;
  v_sale_item_id uuid;
begin
  if current_setting('app.mystery_commit', true) is distinct from 'on' then
    raise exception 'Mystery contents must be written by fn_commit_mystery_fulfillment';
  end if;

  if tg_op = 'DELETE' or new.source <> 'writeoff' then
    raise exception 'NCRM-05 Mystery contents must be immutable writeoff-backed rows';
  end if;

  select w.mystery_fulfillment_id, mf.sale_item_id
  into strict v_fulfillment_id, v_sale_item_id
  from public.writeoff_items wi
  join public.writeoffs w on w.id = wi.writeoff_id
  join public.mystery_fulfillments mf on mf.id = w.mystery_fulfillment_id
  where wi.id = new.writeoff_item_id
    and w.type = 'MBOX';

  if new.sale_item_id <> v_sale_item_id then
    raise exception 'Mystery contents must match the MBOX fulfillment sale item';
  end if;
  return new;
end;
$$;

create trigger mystery_contents_commit_gate
before insert or update or delete on public.mystery_contents
for each row execute function public.fn_guard_mystery_contents();

-- Avoid recalculating partial content within the atomic commit; COGS is refreshed
-- exactly once after all components and automatic consumables are present.
create or replace function public.fn_refresh_mystery_cogs_trigger()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
begin
  if current_setting('app.mystery_commit', true) = 'on' then
    return case when tg_op = 'DELETE' then old else new end;
  end if;

  perform public.fn_refresh_mystery_cogs(
    case when tg_op = 'DELETE' then old.sale_item_id else new.sale_item_id end
  );
  return case when tg_op = 'DELETE' then old else new end;
end;
$$;

create or replace function public.fn_reserve_mystery_fulfillment(
  p_sale_item_id uuid,
  p_components jsonb
)
returns public.mystery_fulfillments
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_sale_item public.sale_items%rowtype;
  v_fulfillment public.mystery_fulfillments%rowtype;
  v_type public.mystery_box_types%rowtype;
  v_status_code text;
  v_component record;
  v_selected_qty integer;
  v_available numeric;
begin
  if jsonb_typeof(p_components) <> 'array'
    or jsonb_array_length(p_components) = 0
  then
    raise exception 'Mystery components must be a non-empty JSON array';
  end if;

  select si.* into strict v_sale_item
  from public.sale_items si
  where si.id = p_sale_item_id
  for update;

  select * into strict v_fulfillment
  from public.mystery_fulfillments
  where sale_item_id = p_sale_item_id
  for update;

  select * into strict v_type
  from public.mystery_box_types
  where product_id = v_sale_item.product_id;

  select os.code into strict v_status_code
  from public.sales s
  join public.order_statuses os on os.id = s.order_status_id
  where s.id = v_sale_item.sale_id;

  if v_status_code in ('cancelled', 'refund') then
    raise exception 'Cannot reserve a cancelled or refunded Mystery sale item';
  end if;

  if v_fulfillment.state <> 'needs_assembly' then
    raise exception 'Mystery fulfillment % is already %', v_fulfillment.id, v_fulfillment.state;
  end if;

  select coalesce(sum((component.value ->> 'qty')::integer), 0)
  into v_selected_qty
  from jsonb_array_elements(p_components) as component(value);

  if v_selected_qty <> v_type.expected_pack_count * v_sale_item.qty then
    raise exception
      'Mystery reservation selects % packs; expected %',
      v_selected_qty,
      v_type.expected_pack_count * v_sale_item.qty;
  end if;

  for v_component in
    with requested as (
      select
        (component.value ->> 'product_id')::uuid as product_id,
        (component.value ->> 'qty')::integer as qty
      from jsonb_array_elements(p_components) as component(value)
    )
    select product_id, sum(qty)::integer as qty
    from requested
    group by product_id
    order by product_id
  loop
    if v_component.qty <= 0 then
      raise exception 'Mystery component quantity must be positive';
    end if;

    perform 1
    from public.products
    where id = v_component.product_id
    for update;
    if not found then
      raise exception 'Mystery component product % does not exist', v_component.product_id;
    end if;

    select a.available_qty into v_available
    from public.v_inventory_available a
    where a.product_id = v_component.product_id;

    if not exists (
      select 1
      from public.v_mystery_eligible_components e
      where e.mystery_product_id = v_sale_item.product_id
        and e.component_product_id = v_component.product_id
    ) then
      raise exception 'Component % is not eligible for Mystery SKU %',
        v_component.product_id, v_sale_item.product_id;
    end if;

    if coalesce(v_available, 0) < v_component.qty then
      raise exception 'Insufficient available stock for component %: available %, requested %',
        v_component.product_id, coalesce(v_available, 0), v_component.qty;
    end if;
  end loop;

  perform set_config('app.mystery_reserve', 'on', true);

  with requested as (
    select
      (component.value ->> 'product_id')::uuid as product_id,
      (component.value ->> 'qty')::integer as qty
    from jsonb_array_elements(p_components) as component(value)
  ), aggregated as (
    select product_id, sum(qty)::integer as qty
    from requested
    group by product_id
  )
  insert into public.inventory_reservations (fulfillment_id, product_id, qty)
  select v_fulfillment.id, product_id, qty
  from aggregated;

  insert into public.mystery_fulfillment_items (
    fulfillment_id,
    reservation_id,
    product_id,
    qty
  )
  select
    v_fulfillment.id,
    r.id,
    r.product_id,
    r.qty
  from public.inventory_reservations r
  where r.fulfillment_id = v_fulfillment.id;

  update public.mystery_fulfillments
  set state = 'reserved'
  where id = v_fulfillment.id
  returning * into v_fulfillment;

  return v_fulfillment;
end;
$$;

create or replace function public.fn_release_mystery_fulfillment(
  p_sale_item_id uuid
)
returns public.mystery_fulfillments
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_fulfillment public.mystery_fulfillments%rowtype;
  v_status_code text;
begin
  select mf.* into strict v_fulfillment
  from public.mystery_fulfillments mf
  where mf.sale_item_id = p_sale_item_id
  for update;

  if v_fulfillment.state <> 'reserved' then
    return v_fulfillment;
  end if;

  select os.code into strict v_status_code
  from public.sale_items si
  join public.sales s on s.id = si.sale_id
  join public.order_statuses os on os.id = s.order_status_id
  where si.id = p_sale_item_id;

  if v_status_code not in ('cancelled', 'refund') then
    raise exception 'Reserved Mystery fulfillment can be released only after cancellation/refund';
  end if;

  perform set_config('app.mystery_release', 'on', true);

  update public.inventory_reservations
  set state = 'released', released_at = now()
  where fulfillment_id = v_fulfillment.id
    and state = 'active';

  update public.mystery_fulfillments
  set state = 'released'
  where id = v_fulfillment.id
  returning * into v_fulfillment;

  return v_fulfillment;
end;
$$;

create or replace function public.fn_release_mystery_reservations_on_cancel()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_status_code text;
  v_sale_item_id uuid;
begin
  select code into strict v_status_code
  from public.order_statuses
  where id = new.order_status_id;

  if v_status_code not in ('cancelled', 'refund') then
    return new;
  end if;

  for v_sale_item_id in
    select mf.sale_item_id
    from public.mystery_fulfillments mf
    join public.sale_items si on si.id = mf.sale_item_id
    where si.sale_id = new.id
      and mf.state = 'reserved'
  loop
    perform public.fn_release_mystery_fulfillment(v_sale_item_id);
  end loop;

  return new;
end;
$$;

create trigger sales_release_mystery_reservations_on_cancel
after update of order_status_id on public.sales
for each row execute function public.fn_release_mystery_reservations_on_cancel();

create or replace function public.fn_commit_mystery_fulfillment(
  p_sale_item_id uuid
)
returns public.mystery_fulfillments
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_sale_item public.sale_items%rowtype;
  v_sale public.sales%rowtype;
  v_fulfillment public.mystery_fulfillments%rowtype;
  v_type public.mystery_box_types%rowtype;
  v_status_code text;
  v_game_code text;
  v_component record;
  v_writeoff_id uuid;
  v_writeoff_item_id uuid;
  v_reserved_qty integer;
  v_available numeric;
  v_writeoff_no text;
begin
  select si.* into strict v_sale_item
  from public.sale_items si
  where si.id = p_sale_item_id
  for update;

  select s.* into strict v_sale
  from public.sales s
  where s.id = v_sale_item.sale_id
  for update;

  select mf.* into strict v_fulfillment
  from public.mystery_fulfillments mf
  where mf.sale_item_id = p_sale_item_id
  for update;

  select mbt.* into strict v_type
  from public.mystery_box_types mbt
  where mbt.product_id = v_sale_item.product_id;

  select os.code, p.game_code into strict v_status_code, v_game_code
  from public.order_statuses os
  join public.products p on p.id = v_sale_item.product_id
  where os.id = v_sale.order_status_id;

  if v_status_code <> 'shipped' then
    raise exception 'Mystery fulfillment can commit only on shipped status, got %', v_status_code;
  end if;

  if v_fulfillment.state <> 'reserved' then
    raise exception 'Mystery fulfillment % is %, expected reserved',
      v_fulfillment.id, v_fulfillment.state;
  end if;

  select coalesce(sum(mfi.qty), 0) into v_reserved_qty
  from public.mystery_fulfillment_items mfi
  join public.inventory_reservations r on r.id = mfi.reservation_id
  where mfi.fulfillment_id = v_fulfillment.id
    and r.state = 'active';

  if v_reserved_qty <> v_type.expected_pack_count * v_sale_item.qty then
    raise exception 'Mystery fulfillment reservation is incomplete: got %, expected %',
      v_reserved_qty, v_type.expected_pack_count * v_sale_item.qty;
  end if;

  -- Every reserve/commit path locks component products in the same order.
  for v_component in
    select r.product_id, r.qty
    from public.inventory_reservations r
    where r.fulfillment_id = v_fulfillment.id
      and r.state = 'active'
    order by r.product_id
  loop
    perform 1
    from public.products
    where id = v_component.product_id
    for update;

    select available_qty into v_available
    from public.v_inventory_available
    where product_id = v_component.product_id;

    if coalesce(v_available, 0) < 0 then
      raise exception 'Reserved component % is no longer physically available', v_component.product_id;
    end if;
  end loop;

  perform set_config('app.mystery_commit', 'on', true);
  v_writeoff_no := format(
    'MBOX-%s-%s',
    to_char(clock_timestamp(), 'YYYYMMDDHH24MISSMS'),
    left(replace(v_fulfillment.id::text, '-', ''), 8)
  );

  insert into public.writeoffs (
    writeoff_no,
    type,
    reason,
    expected_qty,
    written_off_at,
    mystery_sale_id,
    mystery_fulfillment_id,
    note
  )
  values (
    v_writeoff_no,
    'MBOX',
    'NCRM-05 Mystery fulfillment commit',
    v_type.expected_pack_count * v_sale_item.qty,
    v_sale.sold_at,
    v_sale.id,
    v_fulfillment.id,
    concat('sale_item=', v_sale_item.id)
  )
  returning id into v_writeoff_id;

  for v_component in
    select r.product_id, r.qty
    from public.inventory_reservations r
    where r.fulfillment_id = v_fulfillment.id
      and r.state = 'active'
    order by r.product_id
  loop
    insert into public.writeoff_items (writeoff_id, product_id, qty, note)
    values (
      v_writeoff_id,
      v_component.product_id,
      v_component.qty,
      concat('mystery_fulfillment=', v_fulfillment.id)
    )
    returning id into v_writeoff_item_id;

    insert into public.mystery_contents (
      sale_item_id,
      component_product_id,
      qty,
      source,
      writeoff_item_id
    )
    values (
      v_sale_item.id,
      v_component.product_id,
      v_component.qty,
      'writeoff',
      v_writeoff_item_id
    );
  end loop;

  -- Existing rules are deliberately reused by semantic condition, not SKU text.
  insert into public.consumable_consumptions (
    consumable_id,
    qty,
    sale_id,
    source,
    reason,
    consumed_at
  )
  select
    acr.consumable_id,
    sum(acr.qty * v_sale_item.qty),
    v_sale.id,
    'auto',
    concat('NCRM-05 Mystery fulfillment ', v_fulfillment.id),
    v_sale.sold_at
  from public.auto_consumable_rules acr
  where acr.is_active
    and acr.condition in (
      'default',
      'mbox',
      case when v_type.has_holo then 'mbox_xl' else null end,
      case v_game_code
        when 'pokemon' then 'game_pokemon'
        when 'one_piece' then 'game_onepiece'
        else null
      end
    )
  group by acr.consumable_id;

  -- The contents trigger deferred this during the atomic block, so the final
  -- refresh sees every component, holo cost, and attributable consumable.
  perform public.fn_refresh_mystery_cogs(v_sale_item.id);

  update public.inventory_reservations
  set state = 'committed', committed_at = now()
  where fulfillment_id = v_fulfillment.id
    and state = 'active';

  update public.mystery_fulfillments
  set
    state = 'committed',
    committed_at = now()
  where id = v_fulfillment.id
  returning * into v_fulfillment;

  return v_fulfillment;
end;
$$;
