-- NCRM-10 round 2: retain the OpenCart sale when one or more catalogue SKUs
-- are absent. The existing 0011 migration is immutable because it is already
-- an applied migration; this only replaces the RPC body.
--
-- Behaviour:
-- - matched items are inserted normally;
-- - unmatched items are skipped and appended to sales.note;
-- - an all-unmatched order receives a visible header-only sales record;
-- - the complete order discount is allocated over matched gross only.

create or replace function public.fn_ingest_opencart_order(
  p_payload jsonb
)
returns jsonb
language plpgsql
security invoker
set search_path = public
as $$
declare
  v_opencart_order_id text := nullif(trim(coalesce(p_payload ->> 'opencart_order_id', '')), '');
  v_order_no text := nullif(trim(coalesce(p_payload ->> 'order_no', '')), '');
  v_sold_at date;
  v_channel_id uuid;
  v_payment_type_id uuid;
  v_payment_status_id uuid;
  v_order_status_id uuid;
  v_post_method_id uuid;
  v_sale_id uuid;
  v_product_id uuid;
  v_item jsonb;
  v_item_sku text;
  v_item_qty integer;
  v_item_price numeric(12, 2);
  v_item_gross numeric(12, 2);
  v_item_discount numeric(12, 2);
  v_total_gross numeric(12, 2) := 0;
  v_matched_gross numeric(12, 2) := 0;
  v_discount_total numeric(12, 2) := greatest(coalesce((p_payload ->> 'discount_total')::numeric, 0), 0);
  v_discount_allocated numeric(12, 2) := 0;
  v_item_count integer := 0;
  v_matched_item_count integer := 0;
  v_matched_item_index integer := 0;
  v_items_skipped jsonb := '[]'::jsonb;
  v_missing_note text;
  v_sale_note text;
begin
  if v_opencart_order_id is null or v_opencart_order_id !~ '^[0-9]+$' then
    raise exception 'opencart_order_id must be a positive integer string';
  end if;

  if v_order_no is null or v_order_no !~ '^OC-FOP-[0-9]{4,}$' then
    raise exception 'order_no must use the OC-FOP-#### format';
  end if;

  if jsonb_typeof(p_payload -> 'items') <> 'array'
    or jsonb_array_length(p_payload -> 'items') = 0 then
    raise exception 'items must be a non-empty JSON array';
  end if;

  begin
    v_sold_at := (p_payload ->> 'sold_at')::date;
  exception when others then
    raise exception 'sold_at must be an ISO date';
  end;

  for v_item in
    select value
    from jsonb_array_elements(p_payload -> 'items')
  loop
    v_item_sku := nullif(trim(coalesce(v_item ->> 'sku', '')), '');

    if v_item_sku is null then
      raise exception 'each item must have a SKU';
    end if;

    begin
      v_item_qty := (v_item ->> 'qty')::integer;
      v_item_price := (v_item ->> 'unit_price')::numeric(12, 2);
    exception when others then
      raise exception 'item qty and unit_price must be numeric';
    end;

    if v_item_qty <= 0 or v_item_price < 0 then
      raise exception 'item qty must be positive and unit_price non-negative';
    end if;

    v_total_gross := v_total_gross + (v_item_qty * v_item_price);
    v_item_count := v_item_count + 1;
  end loop;

  if v_discount_total > v_total_gross then
    raise exception 'discount_total cannot exceed gross item value';
  end if;

  with payload_items as (
    select
      ordinality as item_index,
      nullif(trim(value ->> 'sku'), '') as sku,
      (value ->> 'qty')::integer as qty,
      (value ->> 'unit_price')::numeric(12, 2) as unit_price
    from jsonb_array_elements(p_payload -> 'items') with ordinality
  ),
  payload_skus as (
    select distinct sku
    from payload_items
  ),
  resolved_skus as (
    select payload_skus.sku, p.id as product_id
    from payload_skus
    left join public.products p on p.sku = payload_skus.sku
  )
  select
    coalesce(sum(payload_items.qty * payload_items.unit_price)
      filter (where resolved_skus.product_id is not null), 0),
    count(*) filter (where resolved_skus.product_id is not null),
    coalesce(
      jsonb_agg(
        jsonb_build_object(
          'sku', payload_items.sku,
          'qty', payload_items.qty,
          'unit_price', payload_items.unit_price
        )
        order by payload_items.item_index
      ) filter (where resolved_skus.product_id is null),
      '[]'::jsonb
    )
  into v_matched_gross, v_matched_item_count, v_items_skipped
  from payload_items
  left join resolved_skus using (sku);

  if jsonb_array_length(v_items_skipped) > 0 then
    select string_agg(
      'SKU не знайдено: ' || (item ->> 'sku') || ' (qty ' || (item ->> 'qty') || ')',
      '; '
      order by item_index
    )
    into v_missing_note
    from jsonb_array_elements(v_items_skipped) with ordinality as skipped(item, item_index);
  end if;

  v_sale_note := nullif(trim(coalesce(p_payload ->> 'note', '')), '');
  if v_missing_note is not null then
    v_sale_note := case
      when v_sale_note is null then v_missing_note
      else v_sale_note || E'\n' || v_missing_note
    end;
  end if;

  select id into strict v_channel_id
  from public.sale_channels
  where code = 'opencart';

  select id into strict v_payment_type_id
  from public.payment_types
  where code = coalesce(nullif(p_payload ->> 'payment_type_code', ''), 'acquiring');

  select id into strict v_payment_status_id
  from public.payment_statuses
  where code = coalesce(nullif(p_payload ->> 'payment_status_code', ''), 'unpaid');

  select id into strict v_order_status_id
  from public.order_statuses
  where code = coalesce(nullif(p_payload ->> 'order_status_code', ''), 'new');

  select id into v_post_method_id
  from public.post_methods
  where code = coalesce(nullif(p_payload ->> 'post_method_code', ''), 'other');

  insert into public.sales (
    order_no,
    opencart_order_id,
    channel_id,
    sold_at,
    customer_phone,
    customer_name,
    payment_type_id,
    payment_status_id,
    order_status_id,
    post_method_id,
    discount_total,
    packaging_cost,
    shop_delivery,
    note
  )
  values (
    v_order_no,
    v_opencart_order_id,
    v_channel_id,
    v_sold_at,
    nullif(trim(coalesce(p_payload ->> 'customer_phone', '')), ''),
    nullif(trim(coalesce(p_payload ->> 'customer_name', '')), ''),
    v_payment_type_id,
    v_payment_status_id,
    v_order_status_id,
    v_post_method_id,
    v_discount_total,
    greatest(coalesce((p_payload ->> 'packaging_cost')::numeric, 0), 0),
    greatest(coalesce((p_payload ->> 'shop_delivery')::numeric, 0), 0),
    v_sale_note
  )
  on conflict (opencart_order_id) do nothing
  returning id into v_sale_id;

  if v_sale_id is null then
    return jsonb_build_object('ok', true, 'duplicate', true);
  end if;

  for v_item in
    select value
    from jsonb_array_elements(p_payload -> 'items')
  loop
    v_item_sku := trim(v_item ->> 'sku');
    v_item_qty := (v_item ->> 'qty')::integer;
    v_item_price := (v_item ->> 'unit_price')::numeric(12, 2);

    select id into v_product_id
    from public.products
    where sku = v_item_sku;

    if v_product_id is null then
      continue;
    end if;

    v_matched_item_index := v_matched_item_index + 1;
    v_item_gross := v_item_qty * v_item_price;

    if v_matched_item_index = v_matched_item_count then
      v_item_discount := v_discount_total - v_discount_allocated;
    elsif v_matched_gross > 0 then
      v_item_discount := round(v_discount_total * v_item_gross / v_matched_gross, 2);
    else
      v_item_discount := 0;
    end if;

    v_discount_allocated := v_discount_allocated + v_item_discount;

    insert into public.sale_items (
      sale_id,
      product_id,
      qty,
      unit_price,
      discount_alloc,
      note
    )
    values (
      v_sale_id,
      v_product_id,
      v_item_qty,
      v_item_price,
      v_item_discount,
      nullif(trim(coalesce(v_item ->> 'note', '')), '')
    );
  end loop;

  return jsonb_build_object(
    'ok', true,
    'duplicate', false,
    'sale_id', v_sale_id,
    'items_inserted', v_matched_item_count,
    'items_skipped', v_items_skipped,
    'header_only', v_matched_item_count = 0
  );
end;
$$;

revoke all on function public.fn_ingest_opencart_order(jsonb) from public;
grant execute on function public.fn_ingest_opencart_order(jsonb) to service_role;
