import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, '../Code.gs'), 'utf8');

function functionSource(name) {
  const match = new RegExp('function ' + name + '\\(').exec(code);
  if (!match) throw new Error('Missing function: ' + name);
  const start = match.index;
  const open = code.indexOf('{', start);
  let depth = 0;
  for (let index = open; index < code.length; index += 1) {
    if (code[index] === '{') depth += 1;
    if (code[index] === '}') {
      depth -= 1;
      if (depth === 0) return code.slice(start, index + 1);
    }
  }
  throw new Error('Unclosed function: ' + name);
}

const headers = [
  'Номер замовлення / операції', 'SKU', 'Назва товару', 'Кількість', 'Ціна за одиницю', 'Знижка',
  'Сума продажу', 'Управлінська собівартість 1 од.', 'Управлінська собівартість продажу',
  'Пакування', 'Еквайринг', 'Нова Пей', 'Комісія маркетплейсу', 'Доставка за рахунок магазину', 'Чистий прибуток'
];

function row(values) { return headers.map((header) => values[header] ?? ''); }

const rows = [
  row({
    'Номер замовлення / операції': 'OC-FOP-0312', SKU: 'SKU-A', 'Назва товару': 'Name A from Товари', 'Кількість': 1,
    'Ціна за одиницю': 1400, 'Знижка': 0, 'Сума продажу': 1400, 'Управлінська собівартість 1 од.': 1284.44,
    'Управлінська собівартість продажу': 1284.44, Пакування: 4.73, Еквайринг: 21, 'Нова Пей': 0,
    'Комісія маркетплейсу': 0, 'Доставка за рахунок магазину': 34.43, 'Чистий прибуток': 55.40
  }),
  row({
    'Номер замовлення / операції': 'OC-FOP-0312', SKU: 'SKU-B', 'Назва товару': 'Name B from Товари', 'Кількість': 2,
    'Ціна за одиницю': 900, 'Знижка': 0, 'Сума продажу': 1800, 'Управлінська собівартість 1 од.': 1.05,
    'Управлінська собівартість продажу': 2.10, Пакування: 6.08, Еквайринг: 27, 'Нова Пей': 0,
    'Комісія маркетплейсу': 0, 'Доставка за рахунок магазину': 44.27, 'Чистий прибуток': 1720.55
  }),
  row({
    'Номер замовлення / операції': 'OC-FOP-0312', SKU: 'SKU-C', 'Назва товару': 'Name C from Товари', 'Кількість': 1,
    'Ціна за одиницю': 4200, 'Знижка': 0, 'Сума продажу': 4200, 'Управлінська собівартість 1 од.': 1955.70,
    'Управлінська собівартість продажу': 1955.70, Пакування: 14.19, Еквайринг: 63, 'Нова Пей': 0,
    'Комісія маркетплейсу': 0, 'Доставка за рахунок магазину': 103.30, 'Чистий прибуток': 2063.81
  }),
  row({
    'Номер замовлення / операції': 'OC-FOP-0313', SKU: 'SKU-ONE', 'Назва товару': 'One item from Товари', 'Кількість': 1,
    'Ціна за одиницю': 160, 'Знижка': 0, 'Сума продажу': 160, 'Управлінська собівартість 1 од.': 65.51,
    'Управлінська собівартість продажу': 65.51, Пакування: 3.60, Еквайринг: 0, 'Нова Пей': 0.80,
    'Комісія маркетплейсу': 0, 'Доставка за рахунок магазину': 0, 'Чистий прибуток': 90.09
  }),
  row({
    'Номер замовлення / операції': 'OC-FOP-0310', SKU: 'SKU-NEG', 'Назва товару': 'Negative item from Товари', 'Кількість': 10,
    'Ціна за одиницю': 75, 'Знижка': 0, 'Сума продажу': 750, 'Управлінська собівартість 1 од.': 98.40,
    'Управлінська собівартість продажу': 984, Пакування: 3.50, Еквайринг: 11.25, 'Нова Пей': 0,
    'Комісія маркетплейсу': 0, 'Доставка за рахунок магазину': 0, 'Чистий прибуток': -248.75
  })
];

const table = { headerRow: 2, headers, rows };
const context = vm.createContext({
  Array, Math, Number, Object, String,
  _getCrmSs: () => ({ getSheetByName: (name) => name === 'Продажі' ? {} : null }),
  apiRecentTable_: () => table,
  apiRecentCol_: (input, name) => {
    const index = input.indexOf(name);
    if (index === -1) throw new Error('column not found: ' + name);
    return index;
  },
  apiNum_: (value) => value === '' || value == null ? 0 : Number(String(value).replace(',', '.')) || 0,
  round2_: (value) => Math.round((Number(value) + Number.EPSILON) * 100) / 100
});
vm.runInContext(functionSource('apiOrderItems_') + '\nglobalThis.orderItems = apiOrderItems_;', context, { filename: 'Code.gs' });

const multi = JSON.parse(JSON.stringify(context.orderItems({ order_id: 'OC-FOP-0312' })));
assert.equal(multi.ok, true);
assert.equal(multi.count, 3);
assert.deepEqual(multi.totals, { amount: 7400, profit: 3839.76 });
assert.equal(multi.items[1].mgmt_cost_unit, 1.05);
assert.equal(multi.items[1].mgmt_cost_line, 2.10, 'line cost is returned directly and not reconstructed from quantity');
assert.equal(multi.items[0].payment_fees, 21);
assert.equal(multi.items[2].profit_pct, 49.14);
multi.items.forEach((item) => {
  const reconciled = item.amount - item.mgmt_cost_line - item.packaging - item.payment_fees - item.shop_delivery;
  assert.equal(Math.round(reconciled * 100) / 100, item.profit, 'line net profit reconciles including allocated payment fees');
  assert.equal(Object.hasOwn(item, 'customer_phone'), false);
  assert.equal(Object.hasOwn(item, 'customer_name'), false);
});

const single = JSON.parse(JSON.stringify(context.orderItems({ order_id: 'OC-FOP-0313' })));
assert.equal(single.count, 1);
assert.deepEqual(single.totals, { amount: 160, profit: 90.09 });
assert.equal(single.items[0].nova_pay, 0.8);

const negative = JSON.parse(JSON.stringify(context.orderItems({ order_id: 'OC-FOP-0310' })));
assert.equal(negative.items[0].profit, -248.75);
assert.equal(negative.items[0].profit_pct, -33.17);
assert.deepEqual(JSON.parse(JSON.stringify(context.orderItems({}))), { ok: false, error: 'order_id required' });

assert.match(code, /if \(action === 'order_items'\) return apiOrderItems_\(params\);/);
assert.doesNotMatch(code.match(/const CACHEABLE_ACTIONS = [^;]+;/)[0], /order_items/, 'order_items remains uncached server-side');
assert.doesNotMatch(functionSource('apiOrderItems_'), /setValue|setValues|appendRow|deleteRow/, 'order_items is read-only');

console.log('CRM-006-ORDER API tests passed');
