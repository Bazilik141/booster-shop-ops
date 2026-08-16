import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, '../Code.gs'), 'utf8');

class Range {
  constructor(sheet, row, column, rows = 1, columns = 1) { this.sheet = sheet; this.row = row; this.column = column; this.rows = rows; this.columns = columns; }
  cells() { return Array.from({ length: this.rows }, (_, row) => Array.from({ length: this.columns }, (_, column) => this.sheet.cell(this.row + row, this.column + column))); }
  getValues() { return this.cells().map(row => row.map(cell => cell.value ?? '')); }
  getValue() { return this.sheet.cell(this.row, this.column).value ?? ''; }
  setValues(values) { values.forEach((row, rowIndex) => row.forEach((value, columnIndex) => { this.sheet.cell(this.row + rowIndex, this.column + columnIndex).value = value; })); return this; }
  setValue(value) { this.sheet.cell(this.row, this.column).value = value; return this; }
}

class Sheet {
  constructor() { this.cellsByKey = new Map(); this.maxRows = 452; }
  cell(row, column) { const key = row + ':' + column; if (!this.cellsByKey.has(key)) this.cellsByKey.set(key, {}); return this.cellsByKey.get(key); }
  getRange(row, column, rows, columns) { return new Range(this, row, column, rows, columns); }
  getLastRow() { return Math.max(0, ...Array.from(this.cellsByKey.keys(), key => Number(key.split(':')[0]))); }
  getMaxRows() { return this.maxRows; }
  getName() { return 'Продажі'; }
}

const sales = new Sheet();
const spreadsheet = { getSheetByName: name => name === 'Продажі' ? sales : null };
const context = vm.createContext({
  Array, Boolean, Date, Error, JSON, Math, Number, Object, RegExp, String, console, isFinite,
  Logger: { log() {} },
  SpreadsheetApp: { getActive: () => spreadsheet },
  PropertiesService: { getScriptProperties: () => ({ getProperty: () => '' }) },
  Utilities: { formatDate: () => '2026-08-14' },
  Session: { getScriptTimeZone: () => 'Europe/Kyiv' },
  ContentService: { MimeType: { JSON: 'JSON' }, createTextOutput: () => ({ setMimeType() { return this; } }) }
});
vm.runInContext(code + '\nglobalThis.__test = { upsertOpenCartOrder_ };', context, { filename: 'Code.gs' });
context.fixSaleCostForRow_ = () => {};
context.invalidateDoGetCache_ = () => {};

const payload = {
  order_id: 1003,
  telephone: '+380 50 000 0001',
  email: 'owner-test@example.invalid',
  customer_name: 'Owner Test Checkout',
  date_added: '2026-08-14 12:00:00',
  order_status: 'В обробці',
  products: [{ sku: 'TEST-SKU', quantity: 1, price: 100, total: 100 }]
};
const inserted = context.__test.upsertOpenCartOrder_(spreadsheet, payload);
assert.deepEqual(JSON.parse(JSON.stringify(inserted)), { action: 'inserted', order: 'OC-FOP-1003', rows: 1 }, 'test identity no longer blocks the import');
assert.equal(sales.getRange(3, 1).getValue(), 'OC-FOP-1003');
assert.equal(sales.getRange(3, 4).getValue(), '+380 50 000 0001');
assert.equal(sales.getRange(3, 5).getValue(), 'Owner Test Checkout');
assert.equal(context.__test.upsertOpenCartOrder_(spreadsheet, payload).action, 'ignored_existing_order', 'dedupe still prevents a repeated delivery from creating a second sale');
assert.doesNotMatch(code, /isIgnoredOpenCartOrder_|getOpenCartIgnoreRules_|ignored_test_order/);
console.log('OpenCart test-order import tests passed');
