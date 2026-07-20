-- NCRM-06
-- Additive returns, immutable COGS reversal snapshots, and Mystery-return stock.
-- 0001–0007 stay immutable. This migration is local-only until an owner-approved
-- cloud cutover exists.

-- Existing Mystery commits did not persist a component-level cost. Keep their
-- rows valid but leave the snapshot NULL: reversal safely refuses such legacy
-- fulfillments instead of reconstructing historical FIFO from mutable history.
alter table public.mystery_contents
  add column prro_unit_snapshot numeric(12,2),
  add column mgmt_unit_snapshot numeric(12,2),
  add column cost_snapshot_at timestamptz,
  add constraint mystery_contents_cost_snapshot_chk check (
    (prro_unit_snapshot is null and mgmt_unit_snapshot is null and cost_snapshot_at is null)
    or (
      prro_unit_snapshot >= 0
      and mgmt_unit_snapshot >= 0
      and cost_snapshot_at is not null
    )
  );

create table public.refund_items (
  id uuid primary key default gen_random_uuid(),
  refund_id uuid not null references public.refunds(id),
  sale_item_id uuid not null references public.sale_items(id),
  qty integer not null,
  condition text not null,
  prro_reversal_uah numeric(14,2) not null default 0,
  mgmt_reversal_uah numeric(14,2) not null default 0,
  note text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint refund_items_qty_positive_chk check (qty > 0),
  constraint refund_items_condition_chk check (
    condition in ('money_only', 'resellable', 'damaged', 'mystery_unopened')
  ),
  constraint refund_items_reversal_nonnegative_chk check (
    prro_reversal_uah >= 0 and mgmt_reversal_uah >= 0
  )
);

create index refund_items_refund_id_idx on public.refund_items(refund_id);
create index refund_items_sale_item_id_idx on public.refund_items(sale_item_id);
create index refund_items_stock_return_idx
  on public.refund_items(condition)
  where condition in ('resellable', 'mystery_unopened');

create trigger refund_items_set_updated_at
before update on public.refund_items
for each row execute function public.set_updated_at();

-- A Mystery-box return needs one parent refund item, but each physical component
-- is restored through its own immutable supply row. Holo and consumables remain
-- excluded: neither has a separately tracked product/layer in the current model.
create table public.mystery_return_components (
  id uuid primary key default gen_random_uuid(),
  refund_item_id uuid not null references public.refund_items(id),
  mystery_content_id uuid not null unique references public.mystery_contents(id),
  product_id uuid not null references public.products(id),
  qty integer not null,
  prro_unit numeric(12,2) not null,
  mgmt_unit numeric(12,2) not null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint mystery_return_components_qty_positive_chk check (qty > 0),
  constraint mystery_return_components_costs_nonnegative_chk check (
    prro_unit >= 0 and mgmt_unit >= 0
  )
);

create index mystery_return_components_refund_item_id_idx
  on public.mystery_return_components(refund_item_id);
create index mystery_return_components_product_id_idx
  on public.mystery_return_components(product_id, created_at);

create trigger mystery_return_components_set_updated_at
before update on public.mystery_return_components
for each row execute function public.set_updated_at();

create or replace function public.fn_prepare_refund_item()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_refund public.refunds%rowtype;
  v_sale_item public.sale_items%rowtype;
begin
  select * into strict v_refund
  from public.refunds
  where id = new.refund_id
  for update;

  select * into strict v_sale_item
  from public.sale_items
  where id = new.sale_item_id
  for update;

  if v_refund.sale_id is null or v_refund.sale_id <> v_sale_item.sale_id then
    raise exception 'Refund % must reference the same sale as sale_item %',
      new.refund_id, new.sale_item_id;
  end if;

  if new.condition = 'resellable' then
    if v_sale_item.prro_unit is null
      or v_sale_item.mgmt_unit is null
      or v_sale_item.cost_fixed_at is null
    then
      raise exception 'Resellable return requires fixed original COGS for sale_item %',
        new.sale_item_id;
    end if;

    new.prro_reversal_uah := round(v_sale_item.prro_unit * new.qty, 2);
    new.mgmt_reversal_uah := round(v_sale_item.mgmt_unit * new.qty, 2);
  elsif new.condition in ('money_only', 'damaged') then
    new.prro_reversal_uah := 0;
    new.mgmt_reversal_uah := 0;
  elsif current_setting('app.mystery_reverse', true) is distinct from 'on' then
    raise exception 'Mystery unopened return must use fn_reverse_mystery_fulfillment';
  end if;

  return new;
end;
$$;

create trigger refund_items_prepare
before insert or update of refund_id, sale_item_id, qty, condition
on public.refund_items
for each row execute function public.fn_prepare_refund_item();

create or replace function public.fn_assert_refund_item_qty(p_sale_item_id uuid)
returns void
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_sale_qty integer;
  v_refunded_qty integer;
begin
  select qty into strict v_sale_qty
  from public.sale_items
  where id = p_sale_item_id;

  select coalesce(sum(qty), 0)::integer into v_refunded_qty
  from public.refund_items
  where sale_item_id = p_sale_item_id;

  if v_refunded_qty > v_sale_qty then
    raise exception 'Refund quantity % exceeds sold quantity % for sale_item %',
      v_refunded_qty, v_sale_qty, p_sale_item_id;
  end if;
end;
$$;

create or replace function public.fn_validate_refund_item_qty()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
begin
  perform public.fn_assert_refund_item_qty(
    case when tg_op = 'DELETE' then old.sale_item_id else new.sale_item_id end
  );

  if tg_op = 'UPDATE' and old.sale_item_id is distinct from new.sale_item_id then
    perform public.fn_assert_refund_item_qty(old.sale_item_id);
  end if;
  return null;
end;
$$;

create constraint trigger refund_items_validate_qty
after insert or update or delete on public.refund_items
deferrable initially deferred
for each row execute function public.fn_validate_refund_item_qty();

create or replace function public.fn_guard_mystery_return_component()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
begin
  if tg_op = 'INSERT'
    and current_setting('app.mystery_reverse', true) = 'on'
  then
    return new;
  end if;

  raise exception 'Mystery return components must be created by fn_reverse_mystery_fulfillment';
end;
$$;

create trigger mystery_return_components_guard
before insert or update or delete on public.mystery_return_components
for each row execute function public.fn_guard_mystery_return_component();

-- The existing FIFO stream already orders supplies before consumptions on the
-- same day. Return layers are supplies, so no synthetic consumption or
-- prior-refund skip is needed in fn_fifo_cost_for_product.
create or replace view public.v_inventory_cost_layers as
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
where iai.qty_delta > 0
union all
select
  'refund_item'::text,
  ri.id,
  concat('REF-', left(replace(r.id::text, '-', ''), 12)),
  si.product_id,
  r.refunded_at,
  ri.qty::numeric,
  round(ri.prro_reversal_uah / ri.qty, 2)::numeric(12,2),
  round(ri.mgmt_reversal_uah / ri.qty, 2)::numeric(12,2),
  'warehouse'::text
from public.refund_items ri
join public.refunds r on r.id = ri.refund_id
join public.sale_items si on si.id = ri.sale_item_id
where ri.condition = 'resellable'
union all
select
  'mystery_return_component'::text,
  mrc.id,
  concat('MRET-', left(replace(ri.id::text, '-', ''), 12)),
  mrc.product_id,
  r.refunded_at,
  mrc.qty::numeric,
  mrc.prro_unit,
  mrc.mgmt_unit,
  'warehouse'::text
from public.mystery_return_components mrc
join public.refund_items ri on ri.id = mrc.refund_item_id
join public.refunds r on r.id = ri.refund_id;

-- New commits freeze each component cost before the immutable writeoff/content
-- record is finalized. This replaces only the NCRM-05 RPC, not its migration.
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
  v_cost record;
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
    writeoff_no, type, reason, expected_qty, written_off_at,
    mystery_sale_id, mystery_fulfillment_id, note
  ) values (
    v_writeoff_no, 'MBOX', 'NCRM-05 Mystery fulfillment commit',
    v_type.expected_pack_count * v_sale_item.qty, v_sale.sold_at,
    v_sale.id, v_fulfillment.id, concat('sale_item=', v_sale_item.id)
  ) returning id into v_writeoff_id;

  for v_component in
    select r.product_id, r.qty
    from public.inventory_reservations r
    where r.fulfillment_id = v_fulfillment.id
      and r.state = 'active'
    order by r.product_id
  loop
    insert into public.writeoff_items (writeoff_id, product_id, qty, note)
    values (
      v_writeoff_id, v_component.product_id, v_component.qty,
      concat('mystery_fulfillment=', v_fulfillment.id)
    ) returning id into v_writeoff_item_id;

    select * into strict v_cost
    from public.fn_fifo_cost_for_product(
      v_component.product_id,
      v_sale.sold_at,
      v_component.qty,
      null,
      v_writeoff_item_id
    );

    insert into public.mystery_contents (
      sale_item_id, component_product_id, qty, source, writeoff_item_id,
      prro_unit_snapshot, mgmt_unit_snapshot, cost_snapshot_at
    ) values (
      v_sale_item.id, v_component.product_id, v_component.qty, 'writeoff',
      v_writeoff_item_id, v_cost.prro_unit, v_cost.mgmt_unit, now()
    );
  end loop;

  insert into public.consumable_consumptions (
    consumable_id, qty, sale_id, source, reason, consumed_at
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

  perform public.fn_refresh_mystery_cogs(v_sale_item.id);

  update public.inventory_reservations
  set state = 'committed', committed_at = now()
  where fulfillment_id = v_fulfillment.id
    and state = 'active';

  update public.mystery_fulfillments
  set state = 'committed', committed_at = now()
  where id = v_fulfillment.id
  returning * into v_fulfillment;

  return v_fulfillment;
end;
$$;

create or replace function public.fn_reverse_mystery_fulfillment(
  p_sale_item_id uuid,
  p_refund_id uuid
)
returns public.refund_items
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_sale_item public.sale_items%rowtype;
  v_refund public.refunds%rowtype;
  v_fulfillment public.mystery_fulfillments%rowtype;
  v_status_code text;
  v_component record;
  v_prro_reversal numeric(14,2);
  v_mgmt_reversal numeric(14,2);
  v_refund_item public.refund_items%rowtype;
begin
  select si.* into strict v_sale_item
  from public.sale_items si
  where si.id = p_sale_item_id
  for update;

  select r.* into strict v_refund
  from public.refunds r
  where r.id = p_refund_id
  for update;

  if v_refund.sale_id is null or v_refund.sale_id <> v_sale_item.sale_id then
    raise exception 'Refund % does not belong to Mystery sale_item %',
      p_refund_id, p_sale_item_id;
  end if;

  if v_refund.refund_type = 'partial_money_no_return' then
    raise exception 'Money-only refund cannot reverse a Mystery fulfillment';
  end if;

  select mf.* into strict v_fulfillment
  from public.mystery_fulfillments mf
  where mf.sale_item_id = p_sale_item_id
  for update;

  if v_fulfillment.state <> 'committed' then
    raise exception 'Mystery fulfillment % is %, expected committed',
      v_fulfillment.id, v_fulfillment.state;
  end if;

  select os.code into strict v_status_code
  from public.sales s
  join public.order_statuses os on os.id = s.order_status_id
  where s.id = v_sale_item.sale_id;

  if v_status_code not in ('shipped', 'refund') then
    raise exception 'Mystery reversal requires shipped/refund sale status, got %', v_status_code;
  end if;

  if exists (
    select 1
    from public.refund_items ri
    where ri.sale_item_id = p_sale_item_id
      and ri.condition = 'mystery_unopened'
  ) then
    raise exception 'Mystery sale_item % already has an unopened-return record', p_sale_item_id;
  end if;

  if exists (
    select 1
    from public.mystery_contents mc
    where mc.sale_item_id = p_sale_item_id
      and (
        mc.prro_unit_snapshot is null
        or mc.mgmt_unit_snapshot is null
        or mc.cost_snapshot_at is null
      )
  ) then
    raise exception
      'Mystery fulfillment % predates NCRM-06 component cost snapshots; reversal is intentionally blocked',
      v_fulfillment.id;
  end if;

  select
    round(coalesce(sum(mc.qty * mc.prro_unit_snapshot), 0), 2),
    round(coalesce(sum(mc.qty * mc.mgmt_unit_snapshot), 0), 2)
  into v_prro_reversal, v_mgmt_reversal
  from public.mystery_contents mc
  where mc.sale_item_id = p_sale_item_id;

  if v_prro_reversal = 0 and v_mgmt_reversal = 0 then
    raise exception 'Mystery fulfillment % has no restorable component cost snapshot',
      v_fulfillment.id;
  end if;

  perform set_config('app.mystery_reverse', 'on', true);

  insert into public.refund_items (
    refund_id, sale_item_id, qty, condition,
    prro_reversal_uah, mgmt_reversal_uah,
    note
  ) values (
    p_refund_id, p_sale_item_id, v_sale_item.qty, 'mystery_unopened',
    v_prro_reversal, v_mgmt_reversal,
    concat('NCRM-06 unopened Mystery reversal; fulfillment=', v_fulfillment.id)
  ) returning * into v_refund_item;

  for v_component in
    select
      mc.id,
      mc.component_product_id,
      mc.qty,
      mc.prro_unit_snapshot,
      mc.mgmt_unit_snapshot
    from public.mystery_contents mc
    where mc.sale_item_id = p_sale_item_id
    order by mc.component_product_id, mc.id
  loop
    insert into public.mystery_return_components (
      refund_item_id, mystery_content_id, product_id, qty, prro_unit, mgmt_unit
    ) values (
      v_refund_item.id, v_component.id, v_component.component_product_id,
      v_component.qty, v_component.prro_unit_snapshot, v_component.mgmt_unit_snapshot
    );
  end loop;

  update public.mystery_fulfillments
  set state = 'reversed'
  where id = v_fulfillment.id;

  return v_refund_item;
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
  );
