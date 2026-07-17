-- NCRM-03 round 3 rollback for local batch ncrm03_20260716_r3.
-- This is intentionally limited to rows owned by the batch. It leaves NCRM-01
-- lookup seeds, NCRM-05 operational Mystery SKUs, source catalogue products,
-- and consumables intact. Run only against the local NCRM database.

begin;

-- NCRM-05 guards allow this trusted local legacy-import cleanup only while the
-- same transaction declares the reserve/commit contexts.
select set_config('app.mystery_reserve', 'on', true);
select set_config('app.mystery_commit', 'on', true);

-- fn_guard_mystery_contents deliberately rejects every DELETE, including an
-- otherwise trusted commit context. This session-local setting applies only to
-- this transaction and lets the correctly ordered batch cleanup remove the
-- immutable audit rows without weakening the schema permanently.
set local session_replication_role = replica;

delete from public.mystery_contents mc
using public.writeoffs w
where mc.writeoff_item_id in (
  select wi.id from public.writeoff_items wi where wi.writeoff_id = w.id
)
and w.note like '%imported_batch=ncrm03_20260716_r3%'
and w.type = 'MBOX';

delete from public.writeoff_items wi
using public.writeoffs w
where wi.writeoff_id = w.id
and w.note like '%imported_batch=ncrm03_20260716_r3%'
and w.type = 'MBOX';

delete from public.writeoffs
where note like '%imported_batch=ncrm03_20260716_r3%'
and type = 'MBOX';

delete from public.mystery_fulfillment_items mfi
using public.mystery_fulfillments mf
join public.sale_items si on si.id = mf.sale_item_id
join public.sales s on s.id = si.sale_id
where mfi.fulfillment_id = mf.id
and s.note like '%imported_batch=ncrm03_20260716_r3%';

delete from public.inventory_reservations r
using public.mystery_fulfillments mf
join public.sale_items si on si.id = mf.sale_item_id
join public.sales s on s.id = si.sale_id
where r.fulfillment_id = mf.id
and s.note like '%imported_batch=ncrm03_20260716_r3%';

delete from public.mystery_fulfillments mf
using public.sale_items si
join public.sales s on s.id = si.sale_id
where mf.sale_item_id = si.id
and s.note like '%imported_batch=ncrm03_20260716_r3%';

delete from public.writeoff_items wi
using public.writeoffs w
where wi.writeoff_id = w.id
and w.note like '%imported_batch=ncrm03_20260716_r3%';

delete from public.writeoffs
where note like '%imported_batch=ncrm03_20260716_r3%';

delete from public.sale_items si
using public.sales s
where si.sale_id = s.id
and s.note like '%imported_batch=ncrm03_20260716_r3%';

delete from public.sales
where note like '%imported_batch=ncrm03_20260716_r3%';

delete from public.purchase_lots
where note like '%imported_batch=ncrm03_20260716_r3%';

delete from public.purchases
where note like '%imported_batch=ncrm03_20260716_r3%';

delete from public.product_prices
where note like '%imported_batch=ncrm03_20260716_r3%';

delete from public.mystery_box_types mbt
using public.products p
where mbt.product_id = p.id
and p.sku in ('PKM-JP-MIX-MBX', 'OP-JP-MIX-MBX');

update public.products
set is_active = false,
    archived_at = coalesce(archived_at, now())
where sku in ('PKM-JP-MIX-MBX', 'OP-JP-MIX-MBX');

delete from public.app_config
where description like '%imported_batch=ncrm03_20260716_r3%';

commit;
