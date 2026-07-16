-- NCRM-03 rollback for batch ncrm03_20260710.
-- Run only against the NCRM database, after reviewing the affected counts.
-- Dependency order is intentional. Products and consumables have no note/import
-- marker in schema v1; their source-owned SKU/name lists are guarded below.

begin;

delete from public.app_config
where description like '%imported_batch=ncrm03_20260710%';

delete from public.writeoff_items
where writeoff_id in (
  select id from public.writeoffs
  where note like '%imported_batch=ncrm03_20260710%'
);
delete from public.writeoffs
where note like '%imported_batch=ncrm03_20260710%';

delete from public.sale_items
where sale_id in (
  select id from public.sales
  where note like '%imported_batch=ncrm03_20260710%'
);
delete from public.sales
where note like '%imported_batch=ncrm03_20260710%';

delete from public.purchase_lots
where note like '%imported_batch=ncrm03_20260710%';
delete from public.purchases
where note like '%imported_batch=ncrm03_20260710%';

delete from public.product_prices
where note like '%imported_batch=ncrm03_20260710%';

delete from public.products
where sku in (
  'PKM-JP-MZERO-BLR', 'PKM-JP-MDEX-BST', 'PKM-JP-SCEX-BST', 'PKM-JP-MZERO-BST',
  'PKM-JP-MSYM-BST', 'PKM-JP-MZERO-BBX', 'OP-JP-V7PR-BST', 'OP-JP-OP10-BST',
  'PKM-JP-MBRV-BST', 'OP-JP-OP07-BST', 'OP-JP-OP11-BST', 'OP-JP-EB03-BST',
  'PKM-JP-SPIN-BST', 'PKM-KR-HWAK-BBX', 'PKM-KR-SEBK-BBX', 'PKM-JP-PRTB-SET',
  'OP-JP-OP12-BST', 'OP-JP-OP14-BST', 'OP-JP-OP15-BST', 'PKM-JP-INFX-BST',
  'PKM-JP-OUTL-BST', 'YGO-JP-QCAC-BST', 'OP-JP-OP08-BST', 'OP-JP-OP08-BBX',
  'PKM-JP-MIX-MBX', 'OP-JP-MIX-MBX', 'OP-JP-PRB01-BST', 'OP-JP-PRB01-BBX',
  'OP-JP-OP07-BBX', 'OP-JP-OP10-BBX', 'OP-JP-OP11-BBX', 'OP-JP-EB03-BBX',
  'OP-JP-OP12-BBX', 'OP-JP-OP14-BBX', 'OP-JP-OP15-BBX', 'YGO-JP-BDOM-BST',
  'YGO-JP-BDOM-BBX', 'PKM-JP-MSYM-BBX', 'PKM-JP-BBLT-BST', 'PKM-JP-SHTR-BST',
  'MTG-JP-AFRS-BST', 'PKM-JP-WFLR-BST', 'PKM-JP-SVEX-BLR', 'YGO-JP-WPP5-BST',
  'YGO-JP-WPP5-BBX', 'PKM-KR-HWAK-BST', 'PKM-JP-ABYE-BST', 'PKM-JP-ABYE-BBX',
  'PKM-JP-MBRV-BBX', 'ACC-001', 'ACC-002', 'ACC-003', 'ACC-004', 'ACC-005',
  'ACC-006', 'ACC-007', 'ACC-008', 'ACC-009', 'PKM-EN-PORD-BBN', 'PKM-EN-PORD-BST',
  'PKM-EN-CHRS-BBN', 'PKM-EN-CHRS-BST', 'OP-JP-OP16-BBX', 'OP-JP-OP16-BST'
)
and not exists (select 1 from public.purchase_lots where product_id = products.id)
and not exists (select 1 from public.sale_items where product_id = products.id)
and not exists (select 1 from public.writeoff_items where product_id = products.id);

-- Products/consumables are source-owned in this empty NCRM cutover. Do not run
-- this block after new NCRM production records reference these SKUs/names.
delete from public.consumables
where name in (
  'Мала м''яка 14х12 см',
  'Середня м''яка 16х14 см',
  'Велика пакет 17х30 см',
  'Конверт Airpock 14х22 см',
  'Стікер лого+QR',
  'Блайнд-пакет для картки',
  'Аніме-брелок поліестер',
  'Брошки TCG енергії',
  'Брелок солом''яний капелюх',
  'Міні-альбом для карт',
  'Протектор для картки',
  'Пакет zip-lock',
  'Наліпка Mystery Box'
)
and not exists (select 1 from public.sales where packaging_type_id = consumables.id);

commit;
