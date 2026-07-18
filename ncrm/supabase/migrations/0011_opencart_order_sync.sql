-- NCRM-10: atomic OpenCart order ingestion for the trusted Edge Function.
--
-- The function is intentionally service_role-only. The Edge Function validates
-- its own shared secret before calling this RPC; this database function then
-- keeps the sales header and all sale_items in one PostgreSQL transaction.

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
  v_discount_total numeric(12, 2) := greatest(coalesce((p_payload ->> 'discount_total')::numeric, 0), 0);
  v_discount_allocated numeric(12, 2) := 0;
  v_item_count integer := 0;
  v_item_index integer := 0;
  v_missing_skus text[];
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

  with payload_skus as (
    select distinct nullif(trim(value ->> 'sku'), '') as sku
    from jsonb_array_elements(p_payload -> 'items')
  )
  select array_agg(payload_skus.sku order by payload_skus.sku)
  into v_missing_skus
  from payload_skus
  left join public.products p on p.sku = payload_skus.sku
  where payload_skus.sku is null or p.id is null;

  if v_missing_skus is not null then
    raise exception 'NCRM product SKU not found: %', array_to_string(v_missing_skus, ', ');
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
    nullif(trim(coalesce(p_payload ->> 'note', '')), '')
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
    v_item_index := v_item_index + 1;
    v_item_sku := trim(v_item ->> 'sku');
    v_item_qty := (v_item ->> 'qty')::integer;
    v_item_price := (v_item ->> 'unit_price')::numeric(12, 2);
    v_item_gross := v_item_qty * v_item_price;

    if v_item_index = v_item_count then
      v_item_discount := v_discount_total - v_discount_allocated;
    elsif v_total_gross > 0 then
      v_item_discount := round(v_discount_total * v_item_gross / v_total_gross, 2);
    else
      v_item_discount := 0;
    end if;

    v_discount_allocated := v_discount_allocated + v_item_discount;

    select id into strict v_product_id
    from public.products
    where sku = v_item_sku;

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
    'items_inserted', v_item_count
  );
end;
$$;

revoke all on function public.fn_ingest_opencart_order(jsonb) from public;
grant execute on function public.fn_ingest_opencart_order(jsonb) to service_role;
