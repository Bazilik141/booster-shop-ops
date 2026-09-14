import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync(new URL('../Code.gs', import.meta.url), 'utf8');
const dashboard = fs.readFileSync(new URL('../../../dashboard/booster-dashboard.html', import.meta.url), 'utf8');

class MockSheet {
  constructor(rows) { this.rows = rows.map((row) => row.slice()); }
  getLastRow() { return this.rows.length; }
  getLastColumn() { return Math.max(1, ...this.rows.map((row) => row.length)); }
  setFrozenRows() {}
  appendRow(row) { this.rows.push(row.slice()); }
  getRange(row, column, numRows = 1, numColumns = 1) {
    const sheet = this;
    const read = () => Array.from({length:numRows}, (_, r) => Array.from({length:numColumns}, (_, c) => sheet.rows[row - 1 + r]?.[column - 1 + c] ?? ''));
    return {
      getValues: read,
      getDisplayValues: () => read().map((values) => values.map((value) => value == null ? '' : String(value))),
      getValue: () => read()[0][0],
      setValue(value) { while (sheet.rows.length < row) sheet.rows.push([]); sheet.rows[row - 1][column - 1] = value; return this; },
      setValues(values) { values.forEach((valuesRow, r) => valuesRow.forEach((value, c) => { while (sheet.rows.length < row + r) sheet.rows.push([]); sheet.rows[row - 1 + r][column - 1 + c] = value; })); return this; },
      isBlank: () => read()[0][0] === ''
    };
  }
}

function zenRuntime() {
  return new Function('Utilities', source + `
    resetMemoForMutation_ = function() {};
    invalidateDoGetCache_ = function() {};
    return {
      history: crm011ZenHistoricalRows_, requireSetup: crm011ZenRequireSetup_, topup: crm011ZenmarketTopup_, correct: crm011ZenmarketCorrectBalance_,
      ledgerHeaders: CRM011_ZEN_LEDGER_HEADERS_, lotHeaders: CRM011_ZEN_LOT_HEADERS_, topupHeaders: CRM011_ZEN_TOPUP_HEADERS_
    };
  `)({ getUuid: () => 'uuid-for-test' });
}

test('historical ZenMarket seed reconciles to the supplied current balance', () => {
  const helpers = new Function(source + '\nreturn { history: crm011ZenHistoricalRows_, expense: crm011ZenPurchaseExpense_ };')();
  const rows = helpers.history();
  assert.equal(rows[0][3], -48260);
  assert.equal(rows.at(-1)[3], -3685);
  assert.equal(rows.slice(1).reduce((sum, row) => sum + row[2], 0), 44575);
  assert.equal(rows.some((row) => row[2] === -165), false, 'ZenPoints must not change the money balance');
});

test('purchase expense contains goods, Japan fees, and delivery to Ukraine', () => {
  const helpers = new Function(source + '\nreturn { expense: crm011ZenPurchaseExpense_ };')();
  const row = Array(20).fill('');
  row[0] = 'LOT-0001'; row[1] = 'ORDER'; row[8] = 100; row[9] = 10; row[10] = 5;
  assert.deepEqual(helpers.expense(row, 3.2), {
    lot_id: 'LOT-0001', order_ref: 'ORDER', goods_jpy: 320, japan_jpy: 32, ukraine_jpy: 16, total_jpy: 368
  });
});

test('ordinary ZenMarket setup checks validate without changing sheets or headers', () => {
  const runtime = zenRuntime();
  const ledger = new MockSheet([runtime.ledgerHeaders, ...runtime.history()]);
  const lots = new MockSheet([runtime.lotHeaders]);
  const topups = new MockSheet([runtime.topupHeaders]);
  const sheets = {'ZenMarket_Рахунок':ledger, 'ZenMarket_Лоти':lots, 'ZenMarket_Поповнення':topups};
  const ss = {getSheetByName:(name) => sheets[name] || null};
  const before = JSON.stringify({ledger:ledger.rows, lots:lots.rows, topups:topups.rows});
  assert.equal(runtime.requireSetup(ss).ledger, ledger);
  assert.equal(JSON.stringify({ledger:ledger.rows, lots:lots.rows, topups:topups.rows}), before);
  topups.rows[0][9] = '';
  const missingHeaderBefore = JSON.stringify(topups.rows);
  assert.throws(() => runtime.requireSetup(ss), /ZENMARKET_SCHEMA_CONFLICT/);
  assert.equal(JSON.stringify(topups.rows), missingHeaderBefore, 'a read path must not restore a missing header');
});

test('top-up and correction write once and produce the expected balance', () => {
  const runtime = zenRuntime();
  const ledger = new MockSheet([runtime.ledgerHeaders, ...runtime.history()]);
  const lots = new MockSheet([runtime.lotHeaders]);
  const topups = new MockSheet([runtime.topupHeaders]);
  const sheets = {'ZenMarket_Рахунок':ledger, 'ZenMarket_Лоти':lots, 'ZenMarket_Поповнення':topups};
  const ss = {getSheetByName:(name) => sheets[name] || null};
  const payload = {request_id:'zen-topup-test-1', date:'2026-09-10', amount_jpy:10000, amount_uah:3125, note:'test'};
  const first = runtime.topup(ss, payload);
  assert.equal(first.ok, true); assert.equal(first.already_applied, false); assert.equal(first.balance_jpy, 6315);
  const ledgerCount = ledger.getLastRow(), topupCount = topups.getLastRow();
  const repeated = runtime.topup(ss, payload);
  assert.equal(repeated.already_applied, true); assert.equal(ledger.getLastRow(), ledgerCount); assert.equal(topups.getLastRow(), topupCount);
  const corrected = runtime.correct(ss, {request_id:'zen-correct-test-1', balance_jpy:7000, note:'owner check'});
  assert.equal(corrected.ok, true); assert.equal(corrected.difference_jpy, 685); assert.equal(corrected.balance_jpy, 7000);
  assert.match(String(ledger.rows.at(-1)[5]), /^коригування балансу зен - курсова різниця; owner check$/);
  const correctionCount = ledger.getLastRow();
  assert.equal(runtime.correct(ss, {request_id:'zen-correct-test-1', balance_jpy:7000, note:'owner check'}).already_applied, true);
  assert.equal(ledger.getLastRow(), correctionCount);
});

test('ZenMarket mutations are idempotent and isolated from P&L', () => {
  assert.match(source, /zenmarket_topup/);
  assert.match(source, /zenmarket_correct_balance/);
  assert.match(source, /crm011ZenFindRequestRow_\(setup\.ledger, 8, requestId\)/);
  assert.match(source, /crm011ZenFindRequestRow_\(setup\.topups, 9, requestId\)/);
  assert.match(source, /CRM011_ZEN_CORRECTION_NOTE_ = 'коригування балансу зен - курсова різниця'/);
  assert.match(source, /crm011ZenEnsurePurchaseBaselines_\(ss, zenMatches/);
  assert.match(source, /crm011ZenSyncPurchaseLots_\(ss, zenMatches/);
  assert.match(source, /const zenmarketAccount = crm011ZenBalanceSnapshot_\(ss\)/);
  assert.match(source, /zenmarket_account: zenmarketAccount/);
  assert.match(source, /balance_uah: round2_\(balanceJpy \/ jpyRate\)/);
  assert.match(source, /jpy_rate: jpyRate/);
  assert.doesNotMatch(source, /pnl: \{[^}]*zenmarket_account/s);
});

test('Finance UI exposes only the requested balance controls and responsive pairs', () => {
  assert.match(dashboard, /id="financeKpis" class="crm011-kpis finance-kpis"/);
  assert.equal((dashboard.match(/class="finance-pair"/g) || []).length, 2);
  assert.match(dashboard, /id="zenCurrentBalance" readonly/);
  assert.match(dashboard, /id="zenCorrectBalance"/);
  assert.match(dashboard, /id="zenTopupDate"/);
  assert.match(dashboard, /id="zenTopupJpy"/);
  assert.match(dashboard, /id="zenTopupUah"/);
  assert.match(dashboard, />Поповнити зен</);
  assert.match(dashboard, /'≈ '\+fmt\(zen\.balance_uah\)\+' за курсом CRM'/);
  assert.match(dashboard, /@media \(min-width: 801px\) and \(max-width: 1200px\)/);
  assert.match(dashboard, /@media \(max-width: 800px\)[\s\S]*\.finance-pair,.zen-form-grid \{ grid-template-columns:1fr; \}/);
  assert.doesNotMatch(dashboard, /ZenMarket[^\n]{0,120}(Комісія|Доставка по Японії|Товар)/);
});

test('dashboard script remains syntactically valid', () => {
  const scripts = [...dashboard.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g)].map((match) => match[1]).filter(Boolean);
  assert.ok(scripts.length > 0);
  scripts.forEach((script) => assert.doesNotThrow(() => new Function(script)));
});
