import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const dashboard = fs.readFileSync(path.resolve(here, '../dashboard/booster-dashboard.html'), 'utf8');

function functionSource(name) {
  const match = new RegExp('(?:async )?function ' + name + '\\(').exec(dashboard);
  if (!match) throw new Error('Missing dashboard function: ' + name);
  const start = match.index;
  const open = dashboard.indexOf('{', start);
  let depth = 0;
  for (let index = open; index < dashboard.length; index += 1) {
    if (dashboard[index] === '{') depth += 1;
    if (dashboard[index] === '}') {
      depth -= 1;
      if (depth === 0) return dashboard.slice(start, index + 1);
    }
  }
  throw new Error('Unclosed dashboard function: ' + name);
}

let requests = 0;
let renders = 0;
let requestedAction = '';
let requestedOrderId = '';
const itemResult = {
  ok: true,
  order_id: 'OC-FOP-0312',
  items: [{
    name: '<img src=x onerror=alert(1)>', sku: 'SKU-<A>', qty: 2, mgmt_cost_unit: 1.05, mgmt_cost_line: 2.10,
    packaging: 6.08, shop_delivery: 44.27, acquiring: 27, nova_pay: 0, marketplace_fee: 0,
    payment_fees: 27, discount: 5, amount: 1800, profit: 1720.55, profit_pct: 95.59
  }],
  totals: { amount: 1800, profit: 1720.55 }
};
const source = [
  dashboard.match(/const orderItemsState = [^;]+;/)[0],
  functionSource('clearOrderItemsState'),
  functionSource('orderItemsKeydown'),
  functionSource('orderItemsPaymentFeesHtml'),
  functionSource('renderOrderItemsPanel'),
  functionSource('toggleOrderItems'),
  'globalThis.orderTest = { orderItemsState, clearOrderItemsState, orderItemsKeydown, orderItemsPaymentFeesHtml, renderOrderItemsPanel, toggleOrderItems };'
].join('\n');
const context = vm.createContext({
  Array, Number, Object, String, Error,
  call: async (action, params) => {
    requests += 1;
    requestedAction = action;
    requestedOrderId = String(params && params.order_id || '');
    if (requestedOrderId === 'FAIL') throw new Error('offline');
    return itemResult;
  },
  renderOrdersTable: () => { renders += 1; },
  spin: () => '<div class="spinner"></div>',
  fmt: (value) => '₴' + Number(value || 0).toFixed(2),
  fmtP: (value) => value == null ? '—' : Number(value).toFixed(1) + '%',
  threeDpEsc: (value) => String(value == null ? '—' : value).replace(/[&<>'"]/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' })[char])
});
vm.runInContext(source, context, { filename: 'dashboard/booster-dashboard.html' });

assert.equal(requests, 0, 'there is no detail request on initial render');
await context.orderTest.toggleOrderItems('OC-FOP-0312');
assert.equal(requests, 1);
assert.equal(requestedAction, 'order_items');
assert.equal(requestedOrderId, 'OC-FOP-0312');
assert.equal(context.orderTest.orderItemsState.expanded['OC-FOP-0312'], true);
assert.equal(context.orderTest.orderItemsState.cache['OC-FOP-0312'], itemResult);
assert.ok(renders >= 2, 'loading and resolved states re-render');

await context.orderTest.toggleOrderItems('OC-FOP-0312');
assert.equal(requests, 1, 'collapse does not refetch');
await context.orderTest.toggleOrderItems('OC-FOP-0312');
assert.equal(requests, 1, 're-expand reuses the session cache');

const panel = context.orderTest.renderOrderItemsPanel({ order_id: 'OC-FOP-0312' });
assert.match(panel, /Платіжні комісії/);
assert.match(panel, /Знижка/);
assert.match(panel, /Разом/);
assert.match(panel, /&lt;img src=x onerror=alert\(1\)&gt;/, 'server-provided name is escaped');
assert.match(panel, /SKU-&lt;A&gt;/, 'server-provided SKU is escaped');

let enterPrevented = false;
context.orderTest.orderItemsKeydown({ key: 'Enter', preventDefault() { enterPrevented = true; } }, { dataset: { orderId: 'KEYBOARD' } });
await new Promise((resolve) => setImmediate(resolve));
assert.equal(enterPrevented, true, 'Enter expands a focused row');
assert.equal(requests, 2, 'keyboard expansion makes the same one lazy request');

context.orderTest.orderItemsState.expanded.NEGATIVE = true;
context.orderTest.orderItemsState.cache.NEGATIVE = {
  items: [{ ...itemResult.items[0], profit: -248.75, profit_pct: -33.17, amount: 750, discount: 0, payment_fees: 11.25 }]
};
assert.match(context.orderTest.renderOrderItemsPanel({ order_id: 'NEGATIVE' }), /color:var\(--red\)/, 'negative line and total use the red profit color');

await context.orderTest.toggleOrderItems('FAIL');
assert.equal(context.orderTest.orderItemsState.errors.FAIL, 'offline');
assert.match(context.orderTest.renderOrderItemsPanel({ order_id: 'FAIL' }), /Не вдалося завантажити позиції: offline/);

let prevented = false;
context.orderTest.orderItemsKeydown({ key: 'x', preventDefault() { prevented = true; } }, { dataset: { orderId: 'OC-FOP-0312' } });
assert.equal(prevented, false, 'unrelated keys do not toggle');
assert.match(functionSource('orderItemsKeydown'), /event\.key !== 'Enter' && event\.key !== ' '/);
assert.match(dashboard, /class="order-summary-row"[\s\S]*role="button" tabindex="0" aria-expanded=/);
assert.equal((dashboard.match(/call\('order_items'/g) || []).length, 1, 'only the expand handler calls order_items');
assert.match(dashboard, /#crmIntegrityCard\s*\{\s*order:\s*999;/, 'UI2 tile ordering remains intact');

console.log('CRM-006-ORDER dashboard tests passed');
