import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync(new URL('../Code.gs', import.meta.url), 'utf8');
const dashboard = fs.readFileSync(new URL('../../../dashboard/booster-dashboard.html', import.meta.url), 'utf8');

function functionSource(name) {
  const start = source.indexOf(`function ${name}`);
  assert.notEqual(start, -1, `${name} declaration is missing`);
  const open = source.indexOf('{', start);
  let depth = 0;
  for (let index = open; index < source.length; index += 1) {
    if (source[index] === '{') depth += 1;
    if (source[index] === '}') { depth -= 1; if (depth === 0) return source.slice(start, index + 1); }
  }
  throw new Error(`${name} declaration is incomplete`);
}

test('CRM-012 Apps Script source compiles', () => {
  assert.doesNotThrow(() => new Function(source));
});

test('preorders recognise the later completion date and keep regular orders on sale date', () => {
  const runtime = new Function(`
    function dateSortValue_(value){return value instanceof Date ? value.getTime() : new Date(value).getTime();}
    ${functionSource('crm012WasPreorderRow_')}
    ${functionSource('crm012RecognitionDateForSalesRow_')}
    return crm012RecognitionDateForSalesRow_;
  `)();
  const c = { sale: 2, payment: 32, received: 34, cost_finalized: 31, status: 23, cost_method: 29 };
  const regular = Array(35).fill(''); regular[2] = new Date('2026-07-05'); regular[23] = 'Отримано';
  assert.equal(runtime(regular, c).source, 'sale_date');
  const preorder = Array(35).fill(''); preorder[2] = new Date('2026-07-05'); preorder[23] = 'Отримано'; preorder[29] = 'FIFO (передзамовлення, звірено)'; preorder[32] = new Date('2026-09-04'); preorder[34] = new Date('2026-09-06');
  assert.equal(new Date(runtime(preorder, c).ms).toISOString().slice(0, 10), '2026-09-06');
  const historical = preorder.slice(); historical[32] = ''; historical[34] = ''; historical[31] = new Date('2026-09-02');
  assert.equal(runtime(historical, c).source, 'cost_finalized_history_proxy');
  historical[31] = '';
  assert.equal(runtime(historical, c).source, 'sale_date_fallback');
});

test('ZenMarket missing-UAH counter reads only top-up ledger rows', () => {
  const counter = new Function(`
    function round2_(value){return Math.round((Number(value)+Number.EPSILON)*100)/100;}
    function num_(value){return Number(value)||0;}
    const CRM011_ZEN_LEDGER_HEADERS_ = Array(10).fill('');
    ${functionSource('crm012ZenMissingTopupUah_')}
    return crm012ZenMissingTopupUah_;
  `)();
  const rows = [Array(10).fill(''), ['ZEN-HIST-008','',70000,'','','','', '', '', 'historical_topup'], ['ZEN-EXP','','-800','','','','','','','historical_expense'], ['ZEN-MANUAL','',1000,'','','',500,'','','manual_topup']];
  const ledger = { getLastRow: () => rows.length, getRange: () => ({ getValues: () => rows.slice(1) }) };
  assert.deepEqual(counter(ledger), { count: 1, rows: [{ row: 2, operation_id: 'ZEN-HIST-008', amount_jpy: 70000 }] });
});

test('sheet-form purchases require an explicit supplier and cannot force ZenMarket', () => {
  const addPurchase = functionSource('addPurchase');
  assert.match(addPurchase, /Постачальник/);
  assert.match(addPurchase, /\['zenmarket_jp', 'supplier_ua', 'other'\]/);
  assert.doesNotMatch(source, /force_zenmarket|forceZenmarket/);
});

test('Cash Flow tells the owner when UAH top-ups are excluded', () => {
  assert.match(dashboard, /поповнення ZenMarket без суми UAH виключено з відтоку коштів/);
  assert.match(dashboard, /historical_topup_uah_missing/);
});

test('finance quality renders both CRM-012 recognition signals', () => {
  assert.match(dashboard, /recognition_fallback_orders/);
  assert.match(dashboard, /identity_conflict_orders/);
});

test('sale editor can change only to a canonical actual payment type', () => {
  const update = functionSource('apiUpdateSaleWithComponents_');
  assert.match(dashboard, /editPaymentType/);
  assert.match(dashboard, /Фактичний тип оплати/);
  assert.match(dashboard, /payload\.payment_type = editValue\('editPaymentType'\)/);
  assert.match(update, /const paymentTypeChanged = hasPaymentType/);
  assert.match(update, /CRM_PAYMENT_TYPES_\.indexOf\(paymentType\) === -1/);
  assert.match(update, /sales\.getRange\(row, 28\)\.setValue\(paymentType\)/);
});
