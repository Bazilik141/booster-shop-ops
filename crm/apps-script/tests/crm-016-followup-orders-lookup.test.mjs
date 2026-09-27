import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync(new URL('../Code.gs', import.meta.url), 'utf8');
const start = source.indexOf('function crmGetOrders_(');
const end = source.indexOf('\nfunction crm011DateBounds_', start);
assert.ok(start >= 0 && end > start);
const fn = source.slice(start, end);
const row = Array(30).fill('');
row[0] = 'ORDER-1'; row[2] = '2026-09-22'; row[3] = '+380501112233'; row[4] = 'Buyer';
row[5] = 'SKU-A'; row[7] = 1; row[10] = 100; row[21] = 20; row[23] = 'Нове';
const globals = {
  num_: value => Number(value) || 0,
  CacheService: { getScriptCache: () => ({ get: () => null, put: () => {} }) },
  crmOrdersCacheVersion_: () => 'fixture',
  crm011ClientModel_: () => ({ clients: [
    { key:'tel:380501112233', segment:'Перший', orders:1 },
    { key:'tel:380501112233', segment:'Другий', orders:9 }
  ] }),
  _getCrmSalesRowEntries: () => [{ values:row }],
  apiCustomerKey_: () => 'tel:380501112233',
  apiDate_: value => String(value),
  onlyDigits_: value => String(value || '').replace(/\D/g, ''),
  dateSortValue_: () => new Date('2026-09-22').getTime(),
  round2_: value => Math.round(value * 100) / 100,
  isUnfinalizedPreorderCostMethod_: () => false,
  crm3dpMarketingByOrder_: () => ({ byOrder:{} }),
  _getCrmSs: () => ({}),
  crmOrderMatchesStatus_: () => true,
  Logger: { log: () => {} }
};
const getOrders = new Function(...Object.keys(globals), fn + '\nreturn crmGetOrders_;')(...Object.values(globals));
const orders = getOrders('active', 20, { skip_marketing:true });
assert.equal(orders.length, 1);
assert.equal(orders[0].client_type, 'Перший', 'lookup preserves first-match behavior');
assert.equal(orders[0].client_orders, 1);
assert.equal(orders[0].amount, 100);
console.log('CRM-016 order lookup: first client match and order totals preserved');
