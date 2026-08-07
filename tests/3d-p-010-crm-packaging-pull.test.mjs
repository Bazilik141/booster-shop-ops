import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const repo = path.resolve(here, '..');
const patchPath = path.join(repo, 'patches', '3D-P-010_crm-packaging-pull_20260802.js');
const v86Path = path.join(repo, 'Booster Shop CRM - Apps_Script_код 29.07.2026.csv');
const patch = fs.readFileSync(patchPath, 'utf8');
const helper = patch.match(/\/\/ BEGIN 3D-P-010 helper block[\s\S]*?\/\/ END 3D-P-010 helper block/)[0];

function response(status, payload) {
  return { getResponseCode: () => status, getContentText: () => JSON.stringify(payload) };
}

function sales(rows) {
  return {
    getRange(row) {
      return { getValues: () => [rows[row]] };
    },
  };
}

function crmRow(order, sku, packaging, { source = 'Сайт', date = '2026-08-03', qty = 2, price = 62, discount = 0 } = {}) {
  const row = Array(16).fill('');
  row[0] = order;
  row[1] = source;
  row[2] = date;
  row[5] = sku;
  row[7] = qty;
  row[8] = price;
  row[9] = discount;
  row[15] = packaging;
  return row;
}

function scenario({ saleRows = [], stock = 10, ledgerRows = [], header = 'CRM row number', appendRows = [20, 21], negativeStockWarning = false } = {}) {
  const calls = [];
  let appendIndex = 0;
  const fetch = (url, options = {}) => {
    const method = String(options.method || 'get').toLowerCase();
    const parsed = new URL(url);
    const action = parsed.searchParams.get('action');
    calls.push({ url, options, action });
    if (method === 'post') {
      const body = JSON.parse(options.payload);
      if (body.action === '3dp_append_row') {
        return response(200, { ok: true, action: body.action, row: appendRows[appendIndex++] });
      }
      if (body.action === '3dp_adjust_stock') {
        const newValue = stock + Number(body.delta || 0);
        return response(200, {
          ok: true,
          action: body.action,
          old_value: stock,
          new_value: newValue,
          delta: Number(body.delta || 0),
          warning: negativeStockWarning || newValue < 0 ? 'insufficient_stock' : null,
        });
      }
      if (body.action === '3dp_write') return response(200, { ok: true, action: body.action });
      throw new Error('unexpected POST action: ' + body.action);
    }
    if (action === '3dp_get_range') return response(200, { ok: true, values: [[header]], formulas: [['']] });
    if (action === '3dp_sales') return response(200, { ok: true, rows: saleRows.slice() });
    if (action === '3dp_stock_adjustments') return response(200, { ok: true, rows: ledgerRows.slice() });
    if (action === '3dp_get_row') return response(200, { ok: true, row: { 'Наявно зараз, шт': stock } });
    throw new Error('unexpected GET action: ' + action);
  };
  return { calls, fetch };
}

function harness({ rows, fetch }) {
  const logs = [];
  const context = {
    Math, Number, String, Object, JSON, Array, RegExp, Date, encodeURIComponent,
    PropertiesService: {
      getScriptProperties: () => ({
        getProperty: (key) => ({
          BOOSTER_3DP_URL: 'https://example.test/exec',
          BOOSTER_3DP_SYNC_TOKEN: 'owner-test-token',
        })[key] || '',
      }),
    },
    UrlFetchApp: { fetch },
    Logger: { log: (line) => logs.push(line) },
  };
  vm.runInNewContext(helper, context, { filename: '3D-P-010 helper' });
  return { context, logs, sales: sales(rows) };
}

function postCalls(calls, action) {
  return calls.filter((call) => call.options.method === 'post' && JSON.parse(call.options.payload).action === action);
}

test('V86 anchors remain exact before this source patch is applied', () => {
  const source = fs.readFileSync(v86Path, 'utf8');
  for (const anchor of [
    'function apiAddSale_(ss, payload)',
    'const costRunState = {}; items.forEach(function(item, index) { const row = firstRow + index;',
    'updateSkuCurrentCost_(ss); invalidateDoGetCache_(); return { ok: true, rows_added: items.length, order_id: operation };',
    'function apiUpdateSale_(ss, payload)',
    'invalidateDoGetCache_(); return { ok: true, row_index: rowIndex, order_id: order, rows_updated: rows.length };',
  ]) assert.equal(source.includes(anchor), true, 'missing V86 anchor: ' + anchor);
});

test('creates one 3D-P sale, writes the full packaging total, and decrements stock', () => {
  const state = scenario({ saleRows: [], stock: 10, appendRows: [20] });
  const { context, logs, sales: crm } = harness({
    rows: { 3: crmRow('OC-100', 'FIG-TEST-001', 5) },
    fetch: state.fetch,
  });
  const result = context.sync3dpSales_(crm, 'OC-100', [3]);
  assert.equal(JSON.stringify(result), JSON.stringify({ ok: true, order: 'OC-100', created: 1, matched: 1, packaging: 5 }));
  const append = JSON.parse(postCalls(state.calls, '3dp_append_row')[0].options.payload);
  assert.deepEqual(append.values, {
    A: '2026-08-03', B: 'FIG-TEST-001', D: 2, E: 62, G: 0, M: 'Сайт', N: 'OC-100', T: 3,
  });
  const adjustment = JSON.parse(postCalls(state.calls, '3dp_adjust_stock')[0].options.payload);
  assert.equal(adjustment.sku, 'FIG-TEST-001');
  assert.equal(adjustment.expected_current, 10);
  assert.equal(adjustment.delta, -2);
  assert.equal(adjustment.reason, 'auto: CRM order OC-100 row 3');
  const write = JSON.parse(postCalls(state.calls, '3dp_write')[0].options.payload);
  assert.deepEqual({
    sku_or_row: write.sku_or_row, column: write.column, value: write.value, expected_current: write.expected_current,
  }, { sku_or_row: 20, column: 'G', value: 5, expected_current: 0 });
  assert.equal(logs.length, 0);
});

test('creates two distinct composite-key rows but writes packaging once', () => {
  const state = scenario({ saleRows: [], stock: 20, appendRows: [20, 21] });
  const { context, sales: crm } = harness({
    rows: {
      3: crmRow('OC-200', 'FIG-ONE-001', 2, { qty: 1 }),
      4: crmRow('OC-200', 'OTHER-001', 3, { qty: 1 }),
      5: crmRow('OC-200', 'BR-TWO-001', 2, { qty: 1 }),
    },
    fetch: state.fetch,
  });
  const result = context.sync3dpSales_(crm, 'OC-200', [3, 4, 5]);
  assert.equal(result.created, 2);
  assert.equal(result.matched, 2);
  assert.equal(result.packaging, 7);
  assert.equal(postCalls(state.calls, '3dp_append_row').length, 2);
  assert.equal(postCalls(state.calls, '3dp_adjust_stock').length, 2);
  const writes = postCalls(state.calls, '3dp_write');
  assert.equal(writes.length, 1);
  assert.equal(JSON.parse(writes[0].options.payload).value, 7);
  const appendRows = postCalls(state.calls, '3dp_append_row').map((call) => JSON.parse(call.options.payload).values.T);
  assert.deepEqual(appendRows, [3, 5]);
});

test('duplicate update is idempotent by order plus CRM row and stock reason', () => {
  const reason = 'auto: CRM order OC-300 row 3';
  const state = scenario({
    saleRows: [{ row_number: 8, '№ замовлення': 'OC-300', 'CRM row number': 3, 'Витрати BoosterShop за од., грн': 5 }],
    stock: 10,
    ledgerRows: [{ SKU: 'FIG-TEST-001', 'Причина': reason }],
  });
  const { context, sales: crm } = harness({
    rows: { 3: crmRow('OC-300', 'FIG-TEST-001', 5) },
    fetch: state.fetch,
  });
  const result = context.sync3dpSales_(crm, 'OC-300', [3]);
  assert.equal(result.created, 0);
  assert.equal(result.matched, 1);
  assert.equal(postCalls(state.calls, '3dp_append_row').length, 0);
  assert.equal(postCalls(state.calls, '3dp_adjust_stock').length, 0);
  assert.equal(postCalls(state.calls, '3dp_write').length, 0);
});

test('missing T schema remains fail-open', () => {
  const state = scenario({ header: '' });
  const { context, logs, sales: crm } = harness({
    rows: { 3: crmRow('OC-400', 'BR-TEST-001', 5) },
    fetch: state.fetch,
  });
  const result = context.sync3dpSales_(crm, 'OC-400', [3]);
  assert.equal(JSON.stringify(result), JSON.stringify({ ok: false, skipped: '3dp_unavailable' }));
  assert.equal(logs.length, 1);
});

test('3D-P outage is fail-open', () => {
  const state = {
    calls: [],
    fetch: () => response(503, { ok: false, code: 'UNAVAILABLE' }),
  };
  const { context, logs, sales: crm } = harness({
    rows: { 3: crmRow('OC-500', 'BR-TEST-001', 5) },
    fetch: state.fetch,
  });
  const result = context.sync3dpSales_(crm, 'OC-500', [3]);
  assert.equal(JSON.stringify(result), JSON.stringify({ ok: false, skipped: '3dp_unavailable' }));
  assert.equal(logs.length, 1);
  assert.equal(logs[0].includes('owner-test-token'), false);
});

test('non-3D order does not call the API', () => {
  let calls = 0;
  const { context, sales: crm } = harness({
    rows: { 3: crmRow('OC-600', 'MTG-BOX-001', 5) },
    fetch: () => { calls += 1; throw new Error('must not fetch'); },
  });
  const result = context.sync3dpSales_(crm, 'OC-600', [3]);
  assert.equal(JSON.stringify(result), JSON.stringify({ ok: true, skipped: 'no_3dp_sku' }));
  assert.equal(calls, 0);
});

test('insufficient stock returns a visible warning without blocking sale sync', () => {
  const state = scenario({ saleRows: [], stock: 1, appendRows: [30], negativeStockWarning: true });
  const { context, logs, sales: crm } = harness({
    rows: { 3: crmRow('OC-700', 'FIG-TEST-001', 0, { qty: 3 }) },
    fetch: state.fetch,
  });
  const result = context.sync3dpSales_(crm, 'OC-700', [3]);
  assert.equal(result.created, 1);
  assert.equal(postCalls(state.calls, '3dp_append_row').length, 1);
  assert.equal(postCalls(state.calls, '3dp_adjust_stock').length, 1);
  assert.equal(logs.some((line) => line.includes('WARNING') && line.includes('insufficient stock')), true);
});
