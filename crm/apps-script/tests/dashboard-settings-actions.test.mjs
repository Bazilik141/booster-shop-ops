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
    if (code[index] === '}' && --depth === 0) return code.slice(start, index + 1);
  }
  throw new Error('Unclosed function: ' + name);
}

class Range {
  constructor(sheet, row, column, rows = 1, columns = 1) { Object.assign(this, { sheet, row, column, rows, columns }); }
  getValues() { return Array.from({ length: this.rows }, (_, r) => Array.from({ length: this.columns }, (_, c) => this.sheet.get(this.row + r, this.column + c))); }
  setValues(values) { values.forEach((line, r) => line.forEach((value, c) => this.sheet.set(this.row + r, this.column + c, value))); return this; }
}
class Sheet {
  constructor() { this.cells = new Map(); this.writeCount = 0; }
  key(row, column) { return row + ':' + column; }
  get(row, column) { return this.cells.get(this.key(row, column)) ?? ''; }
  set(row, column, value) { this.cells.set(this.key(row, column), value); this.writeCount += 1; }
  getRange(row, column, rows = 1, columns = 1) { return new Range(this, row, column, rows, columns); }
  getLastRow() { let last = 0; for (const key of this.cells.keys()) last = Math.max(last, Number(key.split(':')[0])); return last; }
}

const expenses = new Sheet();
let invalidations = 0;
const calls = [];
const context = vm.createContext({ JSON, Math, Number, String, Boolean, Array, Object, RegExp, Date, Error });
vm.runInContext([
  functionSource('apiCrmMaintenance_'),
  functionSource('apiAddExpense_'),
  `
  function num_(value){ return Number(value) || 0; }
  function round2_(value){ return Math.round(Number(value) * 100) / 100; }
  function apiNormalizeDateValue_(value){ return value || ''; }
  function crmNextAppendRow_(ss,name){ return Math.max(ss.getSheetByName(name).getLastRow() + 1, 3); }
  function invalidateDoGetCache_(){ globalThis.__invalidations += 1; }
  function apiIntegrityCheck_(){ return {clean:true,problems:[]}; }
  function crmEnsureCatalogOptionCapacity_(){ globalThis.__calls.push('catalog_options'); return {settings_rows_added:2,validation_fields:3}; }
  function crmAssertCapacityIntegrity_(){ return {before_clean:true,after_clean:true}; }
  function updateExpectedStockFormulas_(){ globalThis.__calls.push('expected_stock'); return {updated:4,purchase_last_row:9,stock_last_row:8}; }
  function updateSkuCurrentCost_(){ globalThis.__calls.push('current_cost'); return {updated:5}; }
  function initializeMissingPreorderCosts_(){ globalThis.__calls.push('preorder_cost'); return {checked:6,priced:1,rows:[7]}; }
  function setupCrmRowCapacityTrigger(){ globalThis.__calls.push('row_capacity'); return {ok:true}; }
  globalThis.SpreadsheetApp={flush:function(){}};
  globalThis.__test={apiCrmMaintenance_,apiAddExpense_};
  `
].join('\n'), context, { filename: 'Code.gs' });
context.__calls = calls;
context.__invalidations = invalidations;
const spreadsheet = { getSheetByName: (name) => name === 'Витрати' ? expenses : null };

const commands = ['catalog_options', 'expected_stock', 'current_cost', 'preorder_cost', 'row_capacity'];
commands.forEach((command) => assert.equal(context.__test.apiCrmMaintenance_(spreadsheet, { command }).ok, true));
assert.deepEqual(calls, commands, 'every allowlisted dashboard command reaches its intended CRM operation');
assert.throws(() => context.__test.apiCrmMaintenance_(spreadsheet, { command: 'openai_key' }), /unknown maintenance command/, 'OpenAI key setup stays unavailable');

const payload = {
  request_id: 'expense-request-0001', date: '2026-08-28', category: 'Пакування', description: 'Коробки', amount: '120',
  linked_to_sale: 'Ні', order_id: '', note: 'Партія 1', consumable_type: 'Коробка', consumable_qty: '12', consumable_status: 'На складі'
};
const first = context.__test.apiAddExpense_(spreadsheet, payload);
assert.equal(first.already_applied, false);
assert.equal(first.row_index, 3);
assert.equal(expenses.get(3, 11), 10);
assert.match(expenses.get(3, 7), /\[dashboard_request:expense-request-0001\]/);
const writesAfterFirst = expenses.writeCount;
const repeated = context.__test.apiAddExpense_(spreadsheet, payload);
assert.equal(repeated.already_applied, true);
assert.equal(repeated.row_index, 3);
assert.equal(expenses.writeCount, writesAfterFirst, 'a retry with the same request id does not append another financial row');
assert.throws(() => context.__test.apiAddExpense_(spreadsheet, { ...payload, request_id: 'short' }), /valid request_id required/);

console.log('Dashboard settings actions and expense idempotency tests passed');
