import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync(new URL('../Code.gs', import.meta.url), 'utf8');
const start = source.indexOf('function crm016InventoryNumber_(');
const end = source.indexOf('function apiSkuCurrentCostMetrics_(', start);
assert.ok(start >= 0 && end > start);
const build = new Function('CRM_3DP_CRM_ROW_HEADER_', 'CRM_3DP_ORDER_HEADER_', 'round2_', 'num_', 'is3dpPackagingSku_',
  source.slice(start, end) + '\nreturn crm016InventoryFromSources_;')(
  'CRM row number', '№ замовлення', n => Math.round(n * 100) / 100,
  value => Number(value) || 0, sku => /^(BR|FIG|ACC-3D)-/.test(sku));

function sku(code, threeD = true) {
  return { sku: code, name: code, is_3dp: threeD, stock: threeD ? 0 : 4,
    stock_raw: threeD ? -1 : 4, physical_stock: threeD ? -1 : 4,
    preorder_reserved: 0, expected: 0, issues: ['мінусовий_залишок'] };
}
function remote(code, printed, sold, bonus = 0) {
  return { SKU: code, API_статус_запису: 'Активний', availability: {
    'Надруковано всього, шт': printed, 'Брак всього, шт': 0,
    'Продано на сайті, шт': sold, 'Видано як плюшка, шт': bonus,
    'Наявно зараз, шт': printed - sold - bonus,
  } };
}
function sale(rowNumber, order, code, qty, status) {
  const values = Array(30).fill('');
  values[0] = order; values[5] = code; values[7] = qty;
  values[22] = 'Не оплачено'; values[23] = status;
  return { rowNumber, values };
}
function committed(rowNumber, order, code, qty) {
  return { 'CRM row number': rowNumber, '№ замовлення': order, SKU: code, 'Кількість, шт': qty };
}
function run(skus, remotes, commits = [], sales = []) {
  return build(skus, remotes, commits, sales, ['Дата', 'SKU', 'Назва', 'Кількість, шт']);
}

const received = run([sku('ACC-3D-PKM-110'), sku('PKM-JP-TEST-BST', false)],
  [remote('ACC-3D-PKM-110', 4, 1, 1)],
  [committed(10, 'O-1', 'ACC-3D-PKM-110', 1)], [sale(10, 'O-1', 'ACC-3D-PKM-110', 1, 'Отримано')]);
assert.deepEqual([received.skus[0].stock, received.skus[0].physical_stock, received.skus[0].reserved_total, received.skus[0].projected_deficit], [2, 2, 0, 0]);
assert.equal(received.skus[0].issues.includes('мінусовий_залишок'), false);
assert.equal(received.skus[1].stock, 4, 'ordinary CRM stock remains on its own ledger');
assert.equal(received.skus[1].issues.includes('мінусовий_залишок'), false,
  'ordinary SKU with non-negative physical balance must not keep a stale negative-stock tag');

const preorderCovered = sku('OP-JP-EB03-BST', false);
preorderCovered.stock_raw = -5; preorderCovered.stock = 0;
preorderCovered.physical_stock = 1; preorderCovered.issues.push('мало_на_складі');
const preorderCoveredResult = run([preorderCovered], []);
assert.deepEqual(preorderCoveredResult.skus[0].issues, ['мало_на_складі'],
  'open preorder reserves must not be reported as negative physical stock');

const ordinaryShortage = sku('ACC-005', false);
ordinaryShortage.stock_raw = -8; ordinaryShortage.stock = 0; ordinaryShortage.physical_stock = -8;
const ordinaryResult = run([ordinaryShortage], []);
assert.equal(ordinaryResult.skus[0].physical_stock, null, 'negative accounting balance is not a physical count');
assert.equal(ordinaryResult.exceptions[0].code, 'crm_accounting_shortage');

const openCommitted = run([sku('BR-CHARM-100')], [remote('BR-CHARM-100', 5, 2)],
  [committed(11, 'O-2', 'BR-CHARM-100', 2)], [sale(11, 'O-2', 'BR-CHARM-100', 2, 'В обробці')]);
assert.deepEqual([openCommitted.skus[0].stock, openCommitted.skus[0].physical_stock, openCommitted.skus[0].reserved_total], [3, 5, 2]);

const openMissing = run([sku('BR-CHARM-100')], [remote('BR-CHARM-100', 5, 0)], [],
  [sale(11, 'O-2', 'BR-CHARM-100', 2, 'Нове')]);
assert.deepEqual([openMissing.skus[0].stock, openMissing.skus[0].physical_stock, openMissing.skus[0].reserved_total], [3, 5, 2]);
assert.equal(openMissing.skus[0].issues.includes('3dp_sale_sync_missing'), true);

const shippedMissing = run([sku('BR-DITTO-400')], [remote('BR-DITTO-400', 1, 0)], [],
  [sale(387, 'OC-FOP-0382', 'BR-DITTO-400', 1, 'Отримано')]);
assert.deepEqual([shippedMissing.skus[0].stock, shippedMissing.skus[0].physical_stock, shippedMissing.skus[0].reserved_total], [0, 0, 0]);
assert.equal(shippedMissing.exceptions[0].code, '3dp_sale_sync_missing');

const marketingProxy = run([sku('BR-DITTO-400')], [remote('BR-DITTO-400', 1, 1)], [],
  [sale(387, 'OC-FOP-0382', 'BR-DITTO-400', 1, 'Отримано')]);
assert.deepEqual([marketingProxy.skus[0].stock, marketingProxy.skus[0].physical_stock,
  marketingProxy.skus[0].projected_deficit], [null, null, null],
  'a separate 3D write-off plus unsynced sale must not assert a second physical deficit');
assert.equal(marketingProxy.skus[0].issues.includes('3dp_sale_sync_missing'), true);
assert.equal(marketingProxy.exceptions[0].missing_fulfilled, 1);
assert.deepEqual(marketingProxy.exceptions[0].missing_fulfilled_sources,
  [{ crm_row:387, order:'OC-FOP-0382', quantity:1 }], 'one-off alert dismissal must identify the exact sale');
const nextSale = run([sku('BR-DITTO-400')], [remote('BR-DITTO-400', 1, 1)], [], [
  sale(387, 'OC-FOP-0382', 'BR-DITTO-400', 1, 'Отримано'),
  sale(400, 'OC-FOP-0400', 'BR-DITTO-400', 1, 'Отримано'),
]);
assert.deepEqual(nextSale.exceptions[0].missing_fulfilled_sources,
  [{ crm_row:387, order:'OC-FOP-0382', quantity:1 }, { crm_row:400, order:'OC-FOP-0400', quantity:1 }],
  'a new unsynced sale must have a different incident identity');

const shortage = run([sku('FIG-ONIX-500')], [remote('FIG-ONIX-500', 0, 0)], [],
  [sale(15, 'O-3', 'FIG-ONIX-500', 1, 'Передзамовлення')]);
assert.deepEqual([shortage.skus[0].stock, shortage.skus[0].stock_raw, shortage.skus[0].physical_stock, shortage.skus[0].projected_deficit], [0, -1, 0, 1]);
assert.equal(shortage.skus[0].action, 'Друкувати');

const cancelled = run([sku('FIG-ONIX-500')], [remote('FIG-ONIX-500', 1, 0)], [],
  [sale(15, 'O-3', 'FIG-ONIX-500', 1, 'Скасовано')]);
assert.deepEqual([cancelled.skus[0].stock, cancelled.skus[0].reserved_total], [1, 0]);

const missing = run([sku('FIG-ONIX-500')], [{ SKU: 'FIG-ONIX-500', API_статус_запису: 'Активний', availability: null }]);
assert.equal(missing.skus[0].stock, null);
assert.equal(missing.skus[0].physical_stock, null);
assert.equal(missing.exceptions[0].code, '3dp_inventory_unavailable');

const mismatch = run([sku('BR-CHARM-100')], [remote('BR-CHARM-100', 5, 2)],
  [committed(11, 'O-2', 'BR-CHARM-100', 3)], [sale(11, 'O-2', 'BR-CHARM-100', 2, 'Нове')]);
assert.equal(mismatch.skus[0].stock, null, 'an overallocated remote sale must fail closed');
assert.equal(mismatch.exceptions[0].code, '3dp_sale_mismatch');

console.log('CRM-016 inventory: received/open/unsynced/shortage/missing/mismatch contract OK');
