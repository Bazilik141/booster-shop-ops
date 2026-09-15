import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const html = fs.readFileSync(path.resolve(here, '../booster-dashboard.html'), 'utf8');

function functionSource(name) {
  const start = html.indexOf('function ' + name + '(');
  assert.notEqual(start, -1, name + ' is present');
  const bodyStart = html.indexOf('{', start);
  let depth = 0;
  for (let index = bodyStart; index < html.length; index += 1) {
    if (html[index] === '{') depth += 1;
    if (html[index] === '}') depth -= 1;
    if (depth === 0) return html.slice(start, index + 1);
  }
  throw new Error('Unclosed function: ' + name);
}

assert.match(html, /\['order_ref','Order Ref'\],\['sku','SKU'\],\['name','Назва'\],\['qty','К-сть'\],\['lot_value_uah','Сума лоту, грн'\]/);
assert.match(html, /title="Сортувати за номером замовлення"/);

const accountingState = {
  purchaseOrderSort: 'asc',
  recent: { purchases: [
    { order_ref: 'yskh329' },
    { order_ref: 'yskh307' },
    { order_ref: '1156931401' }
  ] }
};
let rerendered = false;
const helpers = new Function('accountingState', 'renderRecTable', 'recEsc',
  functionSource('purchaseOrderRefValue_') + '\n' +
  functionSource('sortPurchaseRows_') + '\n' +
  functionSource('togglePurchaseOrderSort_') + '\n' +
  'return { sortPurchaseRows_, togglePurchaseOrderSort_ };'
)(accountingState, () => { rerendered = true; }, String);

helpers.sortPurchaseRows_();
assert.deepEqual(accountingState.recent.purchases.map((row) => row.order_ref), ['yskh307', 'yskh329', '1156931401']);
helpers.togglePurchaseOrderSort_();
assert.equal(accountingState.purchaseOrderSort, 'desc');
assert.deepEqual(accountingState.recent.purchases.map((row) => row.order_ref), ['1156931401', 'yskh329', 'yskh307']);
assert.equal(rerendered, true);

console.log('Purchase update columns and ORDER REF sorting are wired');
