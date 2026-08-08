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
const currentCrmPath = path.join(repo, 'crm', 'apps-script', 'Code.gs');
const patch = fs.readFileSync(patchPath, 'utf8');
const helper = patch.match(/\/\/ BEGIN 3D-P-010 helper block[\s\S]*?\/\/ END 3D-P-010 helper block/)[0];
const currentCrmSource = fs.readFileSync(currentCrmPath, 'utf8');
const currentPredicateSource = currentCrmSource.match(/function is3dpPackagingSku_\(value\) \{[\s\S]*?\n\}/)?.[0];
const updateSaleStatusSource = currentCrmSource.slice(
  currentCrmSource.indexOf('function updateSaleStatus()'),
  currentCrmSource.indexOf('\nfunction updatePaymentStatus()')
);

assert.ok(currentPredicateSource, 'current Code.gs must expose the 3D-P SKU predicate');
assert.ok(updateSaleStatusSource, 'current Code.gs must expose updateSaleStatus');

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
  let currentStock = stock;
  const liveSaleRows = saleRows.map((row) => ({ ...row }));
  const liveLedgerRows = ledgerRows.map((row) => ({ ...row }));
  const fetch = (url, options = {}) => {
    const method = String(options.method || 'get').toLowerCase();
    const parsed = new URL(url);
    const action = parsed.searchParams.get('action');
    calls.push({ url, options, action });
    if (method === 'post') {
      const body = JSON.parse(options.payload);
      if (body.action === '3dp_append_row') {
        const row = appendRows[appendIndex++];
        liveSaleRows.push({
          row_number: row,
          '№ замовлення': body.values.N,
          'CRM row number': body.values.T,
          'Витрати BoosterShop за од., грн': 0,
        });
        return response(200, { ok: true, action: body.action, row });
      }
      if (body.action === '3dp_adjust_stock') {
        const newValue = currentStock + Number(body.delta || 0);
        currentStock = newValue;
        liveLedgerRows.push({ SKU: body.sku, 'Причина': body.reason });
        return response(200, {
          ok: true,
          action: body.action,
          old_value: currentStock - Number(body.delta || 0),
          new_value: newValue,
          delta: Number(body.delta || 0),
          warning: negativeStockWarning || newValue < 0 ? 'insufficient_stock' : null,
        });
      }
      if (body.action === '3dp_write') {
        liveSaleRows.forEach((row) => {
          if (Number(row.row_number) === Number(body.sku_or_row)) row['Витрати BoosterShop за од., грн'] = body.value;
        });
        return response(200, { ok: true, action: body.action });
      }
      throw new Error('unexpected POST action: ' + body.action);
    }
    if (action === '3dp_get_range') return response(200, { ok: true, values: [[header]], formulas: [['']] });
    if (action === '3dp_sales') return response(200, { ok: true, rows: liveSaleRows.slice() });
    if (action === '3dp_stock_adjustments') return response(200, { ok: true, rows: liveLedgerRows.slice() });
    if (action === '3dp_get_row') return response(200, { ok: true, row: { 'Наявно зараз, шт': currentStock } });
    throw new Error('unexpected GET action: ' + action);
  };
  return { calls, fetch };
}

function menuCrmRow(order, sku, packaging = 0, options = {}) {
  const row = Array(29).fill('');
  row[0] = order;
  row[1] = options.source || 'Оновити_продаж';
  row[2] = options.date || '2026-08-08';
  row[5] = sku;
  row[7] = options.qty || 1;
  row[8] = options.price || 90;
  row[9] = options.discount || 0;
  row[15] = packaging;
  row[28] = options.packagingType || '';
  return row;
}

function menuSales(rows) {
  return {
    getLastRow() { return Math.max(2, ...Object.keys(rows).map(Number)); },
    getRange(row, column, rowCount = 1, columnCount = 1) {
      return {
        getValues() {
          return Array.from({ length: rowCount }, (_, rowOffset) => {
            const values = rows[row + rowOffset] || Array(29).fill('');
            return Array.from({ length: columnCount }, (_, columnOffset) => values[column - 1 + columnOffset] ?? '');
          });
        },
        setValue(value) {
          if (!rows[row]) rows[row] = Array(29).fill('');
          rows[row][column - 1] = value;
          return this;
        },
      };
    },
  };
}

function menuHarness({ rows, form, fetch }) {
  const base = harness({ rows, fetch });
  const events = [];
  const crmSales = menuSales(rows);
  const ss = { getSheetByName: (name) => name === 'Продажі' ? crmSales : null };
  Object.assign(base.context, {
    SpreadsheetApp: {
      getActive: () => ss,
      getUi: () => ({ alert: (message) => events.push('alert:' + message) }),
    },
    resetMemoForMutation_: () => events.push('reset'),
    readForm_: () => form,
    resolveSaleUpdateOrder_: (_ss, value) => String(value || '').trim(),
    isBlank_: (value) => String(value == null ? '' : value).trim() === '',
    getPackagingCost_: () => 12,
    num_: (value) => Number(value || 0),
    orderRowWeights_: (_sales, rowNumbers) => rowNumbers.map(() => 1),
    allocateAmount_: (amount, weights) => weights.map(() => amount / weights.length),
    appendCellText_: () => {},
    fixSaleCostForRow_: (_ss, row) => events.push('fix:' + row),
    invalidateDoGetCache_: () => events.push('invalidate'),
    clearSaleUpdateForm: () => events.push('clear'),
  });
  vm.runInNewContext(currentPredicateSource, base.context, { filename: 'current 3D-P SKU predicate' });
  vm.runInNewContext(updateSaleStatusSource, base.context, { filename: 'current updateSaleStatus' });
  const sync = base.context.sync3dpPackagingCost_;
  base.context.sync3dpPackagingCost_ = function (...args) {
    events.push('sync');
    assert.deepEqual(args.slice(3), ['updateSaleStatus'], 'menu hook must identify its journal source');
    return sync(...args);
  };
  return { ...base, crmSales, events, rows };
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

test('current CRM predicate keeps legacy 3D SKUs and accepts canonical ACC-3D SKUs', () => {
  const context = vm.createContext({ String, RegExp });
  vm.runInContext(`${currentPredicateSource}\nglobalThis.matchSku = is3dpPackagingSku_;`, context);
  for (const sku of ['ACC-3D-DITTO-410', 'ACC-3D-PKM-130', 'ACC-3D-410', 'FIG-CHARM-001', 'BR-CHARM-100']) {
    assert.equal(context.matchSku(sku), true, sku + ' must match');
  }
  for (const sku of ['ACC-001', 'MBX-STD-001', 'ACC-3D-']) {
    assert.equal(context.matchSku(sku), false, sku + ' must not match');
  }
});

test('menu update creates a 3D-P sale after final CRM writes and writes packaging once', () => {
  const state = scenario({ stock: 10, appendRows: [40] });
  const rows = { 3: menuCrmRow('OC-MENU-100', 'ACC-3D-DITTO-410') };
  const { context, events } = menuHarness({
    rows,
    form: { 'ТТН / замовлення': 'OC-MENU-100', 'Паковання': 'Коробка' },
    fetch: state.fetch,
  });
  context.updateSaleStatus();
  assert.equal(rows[3][15], 12);
  assert.equal(postCalls(state.calls, '3dp_append_row').length, 1);
  assert.equal(postCalls(state.calls, '3dp_adjust_stock').length, 1);
  assert.equal(postCalls(state.calls, '3dp_write').length, 1);
  assert.ok(events.indexOf('fix:3') < events.indexOf('invalidate'));
  assert.ok(events.indexOf('invalidate') < events.indexOf('sync'));
  assert.ok(events.indexOf('sync') < events.indexOf('clear'));
  assert.ok(events.indexOf('clear') < events.findIndex((event) => event.startsWith('alert:')));
});

test('menu update reuses an existing 3D-P row and changes packaging only when needed', () => {
  const order = 'OC-MENU-200';
  const state = scenario({
    saleRows: [{ row_number: 41, '№ замовлення': order, 'CRM row number': 3, 'Витрати BoosterShop за од., грн': 5 }],
    ledgerRows: [{ SKU: 'ACC-3D-DITTO-410', 'Причина': 'auto: CRM order ' + order + ' row 3' }],
  });
  const { context } = menuHarness({
    rows: { 3: menuCrmRow(order, 'ACC-3D-DITTO-410') },
    form: { 'ТТН / замовлення': order, 'Паковання': 'Коробка' },
    fetch: state.fetch,
  });
  context.updateSaleStatus();
  assert.equal(postCalls(state.calls, '3dp_append_row').length, 0);
  assert.equal(postCalls(state.calls, '3dp_adjust_stock').length, 0);
  assert.equal(postCalls(state.calls, '3dp_write').length, 1);
  assert.equal(JSON.parse(postCalls(state.calls, '3dp_write')[0].options.payload).value, 12);
});

test('menu update with no 3D-P SKU makes no HTTP calls', () => {
  const state = { calls: [], fetch: () => { throw new Error('must not fetch'); } };
  const { context, events } = menuHarness({
    rows: { 3: menuCrmRow('OC-MENU-300', 'MTG-BOX-001') },
    form: { 'ТТН / замовлення': 'OC-MENU-300', 'Паковання': 'Коробка' },
    fetch: state.fetch,
  });
  context.updateSaleStatus();
  assert.equal(state.calls.length, 0);
  assert.ok(events.some((event) => event.startsWith('alert:Продаж оновлено')));
});

test('3D-P outage does not interrupt menu CRM writes or its success alert', () => {
  const state = { calls: [], fetch: () => response(503, { ok: false, code: 'UNAVAILABLE' }) };
  const rows = { 3: menuCrmRow('OC-MENU-400', 'ACC-3D-DITTO-410') };
  const { context, events } = menuHarness({
    rows,
    form: { 'ТТН / замовлення': 'OC-MENU-400', 'Паковання': 'Коробка' },
    fetch: state.fetch,
  });
  assert.doesNotThrow(() => context.updateSaleStatus());
  assert.equal(rows[3][15], 12);
  assert.ok(events.some((event) => event.startsWith('alert:Продаж оновлено')));
});

test('dashboard sync followed by a menu update has no duplicate append or stock decrement', () => {
  const order = 'OC-MENU-500';
  const state = scenario({ stock: 10, appendRows: [50] });
  const rows = { 3: menuCrmRow(order, 'ACC-3D-DITTO-410') };
  const { context, crmSales } = menuHarness({
    rows,
    form: { 'ТТН / замовлення': order, 'Паковання': 'Коробка' },
    fetch: state.fetch,
  });
  context.sync3dpSales_(crmSales, order, [3]);
  context.updateSaleStatus();
  assert.equal(postCalls(state.calls, '3dp_append_row').length, 1);
  assert.equal(postCalls(state.calls, '3dp_adjust_stock').length, 1);
  assert.equal(postCalls(state.calls, '3dp_write').length, 1);
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
