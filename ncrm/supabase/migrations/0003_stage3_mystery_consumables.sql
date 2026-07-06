-- NCRM-01 / Stage 3
-- Mystery boxes, consumables, and inventory write-offs.

create table public.consumables (
  id uuid primary key default gen_random_uuid(),
  name text not null unique,
  category text not null,
  unit_cost numeric(12,2) not null default 0,
  initial_stock numeric(12,3) not null default 0,
  initial_in_transit numeric(12,3) not null default 0,
  received_via_expenses numeric(12,3) not null default 0,
  in_transit_via_expenses numeric(12,3) not null default 0,
  used_in_sales numeric(12,3) not null default 0,
  stock_remaining numeric(12,3) generated always as (
    initial_stock + received_via_expenses - used_in_sales
  ) stored,
  activation_date date,
  is_packaging boolean not null default false,
  is_active boolean not null default true,
  archived_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint consumables_category_chk check (
    category in ('Упаковка', 'Маркетинг')
  ),
  constraint consumables_values_nonnegative_chk check (
    unit_cost >= 0
    and initial_stock >= 0
    and initial_in_transit >= 0
    and received_via_expenses >= 0
    and in_transit_via_expenses >= 0
    and used_in_sales >= 0
  )
);

create trigger consumables_set_updated_at
before update on public.consumables
for each row execute function public.set_updated_at();

alter table public.sales
add constraint sales_packaging_type_fk
foreign key (packaging_type_id) references public.consumables(id);

create table public.writeoffs (
  id uuid primary key default gen_random_uuid(),
  writeoff_no text not null unique,
  type text not null,
  reason text,
  expected_qty numeric(12,3),
  written_off_at date not null,
  mystery_sale_id uuid references public.sales(id),
  note text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint writeoffs_type_chk check (
    type in ('Власне відкриття', 'Маркетинг', 'MBOX', 'Інше')
  ),
  constraint writeoffs_expected_qty_positive_chk check (
    expected_qty is null or expected_qty > 0
  )
);

create index writeoffs_written_off_at_idx on public.writeoffs(written_off_at);

create trigger writeoffs_set_updated_at
before update on public.writeoffs
for each row execute function public.set_updated_at();

create table public.writeoff_items (
  id uuid primary key default gen_random_uuid(),
  writeoff_id uuid not null references public.writeoffs(id),
  product_id uuid not null references public.products(id),
  qty numeric(12,3) not null,
  note text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint writeoff_items_qty_positive_chk check (qty > 0)
);

create index writeoff_items_writeoff_id_idx
on public.writeoff_items(writeoff_id);

create index writeoff_items_product_id_idx
on public.writeoff_items(product_id);

create trigger writeoff_items_set_updated_at
before update on public.writeoff_items
for each row execute function public.set_updated_at();

create table public.mystery_box_types (
  id uuid primary key default gen_random_uuid(),
  product_id uuid not null unique references public.products(id),
  expected_pack_count integer not null,
  has_holo boolean not null default false,
  holo_cost numeric(12,2) not null default 0,
  provisional_unit_cost numeric(12,2) not null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint mystery_box_pack_count_positive_chk check (
    expected_pack_count > 0
  ),
  constraint mystery_box_costs_nonnegative_chk check (
    holo_cost >= 0 and provisional_unit_cost >= 0
  ),
  constraint mystery_box_holo_consistency_chk check (
    has_holo or holo_cost = 0
  )
);

create trigger mystery_box_types_set_updated_at
before update on public.mystery_box_types
for each row execute function public.set_updated_at();

create table public.mystery_contents (
  id uuid primary key default gen_random_uuid(),
  sale_item_id uuid not null references public.sale_items(id),
  component_product_id uuid not null references public.products(id),
  qty integer not null,
  source text not null,
  writeoff_item_id uuid unique references public.writeoff_items(id),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint mystery_contents_qty_positive_chk check (qty > 0),
  constraint mystery_contents_source_chk check (
    source in ('manual_entry', 'writeoff')
  ),
  constraint mystery_contents_writeoff_source_chk check (
    (source = 'writeoff' and writeoff_item_id is not null)
    or (source = 'manual_entry' and writeoff_item_id is null)
  )
);

create index mystery_contents_sale_item_id_idx
on public.mystery_contents(sale_item_id);

create trigger mystery_contents_set_updated_at
before update on public.mystery_contents
for each row execute function public.set_updated_at();

create table public.auto_consumable_rules (
  id uuid primary key default gen_random_uuid(),
  condition text not null,
  consumable_id uuid not null references public.consumables(id),
  qty numeric(12,3) not null,
  note text,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (condition, consumable_id),
  constraint auto_consumable_rules_condition_chk check (
    condition in (
      'game_pokemon',
      'game_onepiece',
      'mbox',
      'mbox_xl',
      'default'
    )
  ),
  constraint auto_consumable_rules_qty_positive_chk check (qty > 0)
);

create trigger auto_consumable_rules_set_updated_at
before update on public.auto_consumable_rules
for each row execute function public.set_updated_at();

create table public.consumable_consumptions (
  id uuid primary key default gen_random_uuid(),
  consumable_id uuid not null references public.consumables(id),
  qty numeric(12,3) not null,
  sale_id uuid references public.sales(id),
  source text not null,
  reason text,
  consumed_at date not null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint consumable_consumptions_qty_positive_chk check (qty > 0),
  constraint consumable_consumptions_source_chk check (
    source in ('auto', 'manual')
  )
);

create index consumable_consumptions_sale_id_idx
on public.consumable_consumptions(sale_id)
where sale_id is not null;

create trigger consumable_consumptions_set_updated_at
before update on public.consumable_consumptions
for each row execute function public.set_updated_at();

create or replace function public.fn_sync_consumable_usage()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
begin
  if tg_op in ('UPDATE', 'DELETE') then
    update public.consumables
    set used_in_sales = used_in_sales - old.qty
    where id = old.consumable_id;
  end if;

  if tg_op in ('INSERT', 'UPDATE') then
    update public.consumables
    set used_in_sales = used_in_sales + new.qty
    where id = new.consumable_id;
  end if;

  if tg_op = 'DELETE' then
    return old;
  end if;
  return new;
end;
$$;

create trigger consumable_consumptions_sync_usage
after insert or update or delete on public.consumable_consumptions
for each row execute function public.fn_sync_consumable_usage();

alter table public.sale_items
add column cost_state text not null default 'actual';

alter table public.sale_items
add constraint sale_items_cost_state_chk
check (cost_state in ('provisional', 'actual'));

alter table public.sale_items
drop constraint sale_items_cost_method_chk;

alter table public.sale_items
add constraint sale_items_cost_method_chk
check (
  cost_method in (
    'FIFO',
    'FIFO+fallback',
    'Fallback',
    'Відкладено',
    'Provisional'
  )
);

create or replace function public.fn_prepare_mystery_sale_item()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_type public.mystery_box_types%rowtype;
begin
  select * into v_type
  from public.mystery_box_types
  where product_id = new.product_id;

  if found then
    new.prro_unit := v_type.provisional_unit_cost;
    new.mgmt_unit := v_type.provisional_unit_cost;
    new.cost_method := 'Provisional';
    new.cost_state := 'provisional';
    new.cost_audit := 'mystery provisional unit cost';
    new.cost_fixed_at := now();
  end if;

  return new;
end;
$$;

create trigger sale_items_prepare_mystery
before insert on public.sale_items
for each row execute function public.fn_prepare_mystery_sale_item();

create or replace function public.fn_fix_new_sale_item()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
begin
  if new.cost_state = 'actual'
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
        and cost_state = 'actual'
        and cost_fixed_at is null
    loop
      perform public.fn_fix_sale_cogs(v_item_id);
    end loop;
  end if;
  return new;
end;
$$;

create or replace function public.fn_refresh_mystery_cogs(
  p_sale_item_id uuid
)
returns public.sale_items
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_item public.sale_items%rowtype;
  v_sale public.sales%rowtype;
  v_type public.mystery_box_types%rowtype;
  v_content record;
  v_cost record;
  v_content_qty integer;
  v_prro_total numeric := 0;
  v_mgmt_total numeric := 0;
  v_consumables_total numeric := 0;
  v_mystery_qty numeric := 0;
  v_methods text[] := '{}';
  v_audit text := '';
begin
  select * into strict v_item
  from public.sale_items
  where id = p_sale_item_id
  for update;

  select * into strict v_sale
  from public.sales
  where id = v_item.sale_id;

  select * into strict v_type
  from public.mystery_box_types
  where product_id = v_item.product_id;

  select coalesce(sum(qty), 0)::integer
  into v_content_qty
  from public.mystery_contents
  where sale_item_id = v_item.id;

  if v_content_qty < v_type.expected_pack_count * v_item.qty then
    update public.sale_items
    set
      prro_unit = v_type.provisional_unit_cost,
      mgmt_unit = v_type.provisional_unit_cost,
      cost_method = 'Provisional',
      cost_state = 'provisional',
      cost_audit = 'mystery provisional unit cost; content incomplete',
      cost_fixed_at = now()
    where id = v_item.id
    returning * into v_item;
    return v_item;
  end if;

  if v_content_qty > v_type.expected_pack_count * v_item.qty then
    raise exception
      'Mystery item % contains % packs; expected %',
      v_item.id,
      v_content_qty,
      v_type.expected_pack_count * v_item.qty;
  end if;

  for v_content in
    select
      mc.id,
      mc.component_product_id,
      mc.qty,
      mc.writeoff_item_id
    from public.mystery_contents mc
    where mc.sale_item_id = v_item.id
    order by mc.created_at, mc.id
  loop
    select * into strict v_cost
    from public.fn_fifo_cost_for_product(
      v_content.component_product_id,
      v_sale.sold_at,
      v_content.qty,
      null,
      v_content.writeoff_item_id
    );

    v_prro_total := v_prro_total + v_cost.prro_unit * v_content.qty;
    v_mgmt_total := v_mgmt_total + v_cost.mgmt_unit * v_content.qty;
    v_methods := array_append(v_methods, v_cost.cost_method);
    v_audit := concat_ws(
      '; ',
      nullif(v_audit, ''),
      format('content %s: %s', v_content.id, v_cost.cost_audit)
    );
  end loop;

  select coalesce(sum(cc.qty * c.unit_cost), 0)
  into v_consumables_total
  from public.consumable_consumptions cc
  join public.consumables c on c.id = cc.consumable_id
  where cc.sale_id = v_item.sale_id;

  select coalesce(sum(si.qty), 0)
  into v_mystery_qty
  from public.sale_items si
  join public.mystery_box_types mbt on mbt.product_id = si.product_id
  where si.sale_id = v_item.sale_id;

  if v_mystery_qty > 0 then
    v_consumables_total :=
      v_consumables_total * v_item.qty / v_mystery_qty;
  end if;

  v_prro_total := v_prro_total
    + v_type.holo_cost * v_item.qty
    + v_consumables_total;
  v_mgmt_total := v_mgmt_total
    + v_type.holo_cost * v_item.qty
    + v_consumables_total;

  update public.sale_items
  set
    prro_unit = round(v_prro_total / qty, 2),
    mgmt_unit = round(v_mgmt_total / qty, 2),
    cost_method = case
      when 'Fallback' = any(v_methods) then 'FIFO+fallback'
      when 'FIFO+fallback' = any(v_methods) then 'FIFO+fallback'
      else 'FIFO'
    end,
    cost_state = 'actual',
    cost_audit = concat(
      'mystery actual; ',
      v_audit,
      '; holo=', v_type.holo_cost * qty,
      '; consumables=', v_consumables_total
    ),
    cost_fixed_at = now()
  where id = v_item.id
  returning * into v_item;

  return v_item;
end;
$$;

create or replace function public.fn_refresh_mystery_cogs_trigger()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
begin
  perform public.fn_refresh_mystery_cogs(
    case when tg_op = 'DELETE' then old.sale_item_id else new.sale_item_id end
  );
  if tg_op = 'DELETE' then
    return old;
  end if;
  return new;
end;
$$;

create trigger mystery_contents_refresh_cogs
after insert or update or delete on public.mystery_contents
for each row execute function public.fn_refresh_mystery_cogs_trigger();

insert into public.product_categories (code, name)
values ('mystery_box', 'Mystery Box');

insert into public.products (sku, name, category_code)
values
  ('MBX', 'MBX', 'mystery_box'),
  ('MBX-XL', 'MBX-XL', 'mystery_box');

insert into public.product_prices (
  product_id,
  rrc,
  source,
  effective_from
)
select
  p.id,
  seed.rrc,
  'NCRM-01 confirmed seed',
  date '2026-07-05'
from (
  values
    ('MBX'::text, 700::numeric),
    ('MBX-XL'::text, 950::numeric)
) as seed(sku, rrc)
join public.products p on p.sku = seed.sku;

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
    ('MBX'::text, 5, false, 0::numeric, 450::numeric),
    ('MBX-XL'::text, 7, true, 75::numeric, 700::numeric)
) as seed(
  sku,
  expected_pack_count,
  has_holo,
  holo_cost,
  provisional_unit_cost
)
join public.products p on p.sku = seed.sku;

-- TODO(NCRM-01/OWNER): seed auto_consumable_rules only after the real
-- consumables inventory is supplied. Mixed Pokémon + One Piece behavior
-- also remains intentionally unset.
