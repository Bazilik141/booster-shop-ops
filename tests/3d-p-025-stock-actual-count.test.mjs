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

const stockCode = [
  functionSource('threeDpStockAdjustmentForActual'),
  functionSource('previewThreeDpStockAdjustment'),
  functionSource('saveThreeDpStock'),
].join('\n');

function createHarness({ actual = '97', reason = 'звірка', fresh = { row: { SKU: 'ACC-3D-TEST-001', 'Наявно зараз, шт': 195 } } } = {}) {
  const row = { SKU: 'ACC-3D-TEST-001', availability: { 'Наявно зараз, шт': 196 } };
  const elements = {
    threeDpStockActual: { value: actual },
    threeDpStockReason: { value: reason },
    threeDpStockPreview: { textContent: '' },
    threeDpStockSubmit: { disabled: true },
  };
  const gets = [];
  const posts = [];
  const state = { product: { sku: row.SKU, msg: {}, ledger: [] } };
  const context = vm.createContext({
    Number, String, Math, Object, Error, confirm: () => true,
    document: { getElementById: (id) => elements[id] || null },
    threeDpUi: state,
    threeDpSku: () => row,
    threeDpStatus: () => 'Активний',
    threeDpInput: (id) => elements[id] || null,
    threeDpMetrics: (item) => ({ availability: ((item || {}).availability || {})['Наявно зараз, шт'] }),
    threeDpNumber: (value) => { const number = Number(String(value == null ? '' : value).replace(',', '.').replace(/[^0-9.-]/g, '')); return Number.isFinite(number) ? number : 0; },
    renderThreeDpProducts: () => {}, renderThreeDpAll: () => {}, reloadThreeDpData: async () => {}, loadThreeDpLedger: async () => {},
    call3dp: async (action, payload) => { gets.push({ action, payload }); if (fresh instanceof Error) throw fresh; return fresh; },
    call3dpPost: async (payload) => { posts.push(payload); return { ledger_row: 9, old_value: payload.expected_current, new_value: Number(actual) }; },
  });
  vm.runInContext(stockCode + '\nglobalThis.stockTest = { previewThreeDpStockAdjustment, saveThreeDpStock };', context, { filename: 'dashboard/booster-dashboard.html' });
  return { elements, gets, posts, state, stockTest: context.stockTest };
}

{
  const test = createHarness({ actual: '' });
  test.stockTest.previewThreeDpStockAdjustment();
  assert.equal(test.elements.threeDpStockPreview.textContent, 'Вкажіть фактичну наявність.');
  assert.equal(test.elements.threeDpStockSubmit.disabled, true);
  await test.stockTest.saveThreeDpStock();
  assert.equal(test.gets.length, 0);
  assert.equal(test.posts.length, 0);
}

{
  const test = createHarness();
  await test.stockTest.saveThreeDpStock();
  assert.deepEqual(JSON.parse(JSON.stringify(test.gets)), [{ action: '3dp_get_row', payload: { sheet: 'Наявність', sku: 'ACC-3D-TEST-001' } }]);
  assert.deepEqual(JSON.parse(JSON.stringify(test.posts)), [{ action: '3dp_adjust_stock', sku: 'ACC-3D-TEST-001', expected_current: 195, delta: -98, reason: 'звірка' }]);
}

{
  const test = createHarness({ fresh: { row: { SKU: 'ACC-3D-TEST-001' } } });
  await test.stockTest.saveThreeDpStock();
  assert.equal(test.posts.length, 0);
  assert.match(test.state.product.msg.text, /Поточна наявність не повернулась/);
}

{
  const test = createHarness({ fresh: new Error('ROW_NOT_FOUND') });
  await test.stockTest.saveThreeDpStock();
  assert.equal(test.posts.length, 0);
  assert.match(test.state.product.msg.text, /ROW_NOT_FOUND/);
}

console.log('3D-P-025 stock actual-count tests passed');
