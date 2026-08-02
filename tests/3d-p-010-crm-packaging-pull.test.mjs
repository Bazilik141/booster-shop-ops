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

function harness({ rows, fetch }) {
  const logs = [];
  const context = {
    Math, Number, String, Object, JSON, Array, RegExp, encodeURIComponent,
    PropertiesService: { getScriptProperties: () => ({ getProperty: (key) => ({ BOOSTER_3DP_URL: 'https://example.test/exec', BOOSTER_3DP_SYNC_TOKEN: 'owner-test-token' })[key] || '' }) },
    UrlFetchApp: { fetch },
    Logger: { log: (line) => logs.push(line) },
  };
  vm.runInNewContext(helper, context, { filename: '3D-P-010 helper' });
  return { context, logs, sales: sales(rows) };
}

function local(value) { return JSON.parse(JSON.stringify(value)); }

function crmRow(order, sku, packaging) {
  const row = Array(16).fill('');
  row[0] = order;
  row[5] = sku;
  row[15] = packaging;
  return row;
}

test('V86 anchors remain exact before this source patch is applied', () => {
  const source = fs.readFileSync(v86Path, 'utf8');
  for (const anchor of [
    'function apiAddSale_(ss, payload)',
    'const costRunState = {}; items.forEach(function(item, index) { const row = firstRow + index;',
    'updateSkuCurrentCost_(ss); invalidateDoGetCache_(); return { ok: true, rows_added: items.length, order_id: operation };',
    'function apiUpdateSale_(ss, payload)',
    'invalidateDoGetCache_(); return { ok: true, row_index: rowIndex, order_id: order, rows_updated: rows.length };',
  ]) assert.equal(source.includes(anchor), true, `missing V86 anchor: ${anchor}`);
});

test('writes multi-line packaging total once to the first matching 3D-P row', () => {
  const calls = [];
  const { context, sales: crm } = harness({
    rows: { 3: crmRow('OC-100', 'FIG-TEST-001', 2), 4: crmRow('OC-100', 'OTHER-1', 3) },
    fetch: (url, options = {}) => {
      calls.push({ url, options });
      if (options.method === 'post') return response(200, { ok: true, action: '3dp_write' });
      return response(200, { ok: true, rows: [
        { row_number: 12, '№ замовлення': 'OC-100', 'Витрати BoosterShop за од., грн': 0 },
        { row_number: 8, '№ замовлення': 'OC-100', 'Витрати BoosterShop за од., грн': 0 },
      ] });
    },
  });
  const result = context.sync3dpPackagingCost_(crm, 'OC-100', [3, 4]);
  assert.equal(result.ok, true);
  assert.equal(calls.length, 2);
  const body = JSON.parse(calls[1].options.payload);
  assert.deepEqual({ sheet: body.sheet, sku_or_row: body.sku_or_row, column: body.column, value: body.value, expected_current: body.expected_current }, { sheet: 'Продажі', sku_or_row: 8, column: 'G', value: 5, expected_current: 0 });
  assert.equal(body.token, 'owner-test-token');
});

test('does not call 3D-P for an order without a 3D-P SKU', () => {
  let calls = 0;
  const { context, sales: crm } = harness({ rows: { 3: crmRow('OC-200', 'MTG-BOX-001', 5) }, fetch: () => { calls += 1; throw new Error('must not fetch'); } });
  const result = context.sync3dpPackagingCost_(crm, 'OC-200', [3]);
  assert.deepEqual(local(result), { ok: true, skipped: 'no_3dp_sku' });
  assert.equal(calls, 0);
});

test('3D-P outage is fail-open and leaves the CRM caller with a skip', () => {
  const { context, logs, sales: crm } = harness({ rows: { 3: crmRow('OC-300', 'BR-TEST-001', 5) }, fetch: () => response(503, { ok: false, code: 'UNAVAILABLE' }) });
  const result = context.sync3dpPackagingCost_(crm, 'OC-300', [3]);
  assert.deepEqual(local(result), { ok: false, skipped: '3dp_unavailable' });
  assert.equal(logs.length, 1);
  assert.equal(logs[0].includes('owner-test-token'), false);
});

test('does not create a duplicate audit write when 3D-P G already equals the full order total', () => {
  let calls = 0;
  const { context, sales: crm } = harness({
    rows: { 3: crmRow('OC-400', 'ACC-3D-001', 5) },
    fetch: () => { calls += 1; return response(200, { ok: true, rows: [{ row_number: 5, '№ замовлення': 'OC-400', 'Витрати BoosterShop за од., грн': 5 }] }); },
  });
  const result = context.sync3dpPackagingCost_(crm, 'OC-400', [3]);
  assert.deepEqual(local(result), { ok: true, skipped: 'already_current', row: 5, value: 5 });
  assert.equal(calls, 1);
});