import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync(new URL('../Code.gs', import.meta.url), 'utf8');
const start = source.indexOf('function api3dpOrderShare_() {');
const end = source.indexOf('const CRM016_ORDER_REMOVE_STATUSES_', start);
assert.ok(start >= 0 && end > start, '3D order share action exists');
const body = source.slice(start, end);

function row(order, date, sku, status = 'Отримано', payment = 'Оплачено') {
  const values = Array(35).fill('');
  values[0] = order;
  values[2] = date;
  values[5] = sku;
  values[22] = payment;
  values[23] = status;
  return { values };
}

function run(entries) {
  const FixedDate = class extends Date {
    constructor(value) { super(value === undefined ? '2026-09-24T12:00:00Z' : value); }
  };
  const utilities = { formatDate(date) { return date.toISOString().slice(0, 7); } };
  const factory = new Function('Date', 'Utilities', '_getCrmSalesRowEntries', 'dateSortValue_', 'is3dpPackagingSku_', 'round2_', body + '\nreturn api3dpOrderShare_;');
  return factory(FixedDate, utilities, () => entries, value => Date.parse(value), sku => /^(BR|FIG|ACC-3D)-/.test(sku), value => Math.round(value * 100) / 100)();
}

const result = run([
  row('ORDER-1', '2026-09-03', 'FIG-ONIX-500'),
  row('ORDER-1', '2026-09-03', 'PKM-EN-AAA-BST'),
  row('ORDER-1', '2026-09-03', 'BR-CHARM-100'),
  row('ORDER-2', '2026-09-05', 'PKM-EN-BBB-BST'),
  row('ORDER-3', '2026-09-05', 'ACC-3D-PKM-110', 'Скасовано'),
  row('ORDER-4', '2026-08-31', 'BR-CHARM-100'),
  row('ORDER-5', '2026-09-07', 'FIG-ONIX-500', 'Отримано', 'Повернення')
]);
assert.deepEqual({ month: result.month, total: result.orders_total, with3dp: result.orders_with_3dp, pct: result.share_pct },
  { month: '2026-09', total: 2, with3dp: 1, pct: 50 });
assert.equal(run([]).share_pct, null, 'empty month has no misleading zero percent');
console.log('CRM-016 3D order share: unique orders and exclusions OK');
