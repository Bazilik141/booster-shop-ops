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
    if (code[index] === '}') {
      depth -= 1;
      if (depth === 0) return code.slice(start, index + 1);
    }
  }
  throw new Error('Unclosed function: ' + name);
}

class Range {
  constructor(sheet, row, column, rows = 1, columns = 1) { this.sheet = sheet; this.row = row; this.column = column; this.rows = rows; this.columns = columns; }
  getValues() {
    return Array.from({ length: this.rows }, (_, rowOffset) => Array.from({ length: this.columns }, (_, columnOffset) => this.sheet.values.get((this.row + rowOffset) + ':' + (this.column + columnOffset)) || ''));
  }
  getFormulas() {
    return Array.from({ length: this.rows }, (_, rowOffset) => Array.from({ length: this.columns }, (_, columnOffset) => this.sheet.formulas.get((this.row + rowOffset) + ':' + (this.column + columnOffset)) || ''));
  }
  setFormulas(formulas) {
    formulas.forEach((row, rowOffset) => row.forEach((formula, columnOffset) => this.sheet.formulas.set((this.row + rowOffset) + ':' + (this.column + columnOffset), formula)));
    return this;
  }
}

class Sheet {
  constructor(name, maxRows) { this.name = name; this.maxRows = maxRows; this.values = new Map(); this.formulas = new Map(); }
  getMaxRows() { return this.maxRows; }
  getLastRow() { return this.maxRows; }
  getRange(row, column, rows = 1, columns = 1) { return new Range(this, row, column, rows, columns); }
}

const purchases = new Sheet('Закупки', 309);
const stock = new Sheet('Склад', 220);
const spreadsheet = { getSheetByName: (name) => ({ 'Закупки': purchases, 'Склад': stock }[name] || null) };
const context = vm.createContext({ Math, Number, String });
vm.runInContext([
  functionSource('crmCapacitySheetLastRow_'),
  functionSource('expectedStockFormula_'),
  functionSource('updateExpectedStockFormulas_'),
  'globalThis.__test = { expectedStockFormula_, updateExpectedStockFormulas_ };'
].join('\n'), context, { filename: 'Code.gs' });

const first = context.__test.updateExpectedStockFormulas_(spreadsheet);
assert.equal(first.updated, 218, 'first repair writes the formula through every stock row');
assert.equal(first.purchase_last_row, 309, 'formula ranges cover current purchase-sheet capacity');
const row3 = stock.getRange(3, 17).getFormulas()[0][0];
assert.match(row3, /'Закупки'!\$E\$3:\$E\$309=\$A3/);
assert.match(row3, /'Закупки'!\$Q\$3:\$Q\$309="Замовлено"/, 'confirmed orders are expected stock');
assert.match(row3, /"В дорозі"/);
assert.match(row3, /"На складі в Японії"/);
assert.match(row3, /"Виграно"/);
assert.equal(context.__test.updateExpectedStockFormulas_(spreadsheet).updated, 0, 'repeat repair is idempotent');
const unsafeStock = new Sheet('Склад', 3);
unsafeStock.values.set('3:17', 'ручне значення');
const unsafeSpreadsheet = { getSheetByName: (name) => ({ 'Закупки': purchases, 'Склад': unsafeStock }[name] || null) };
assert.throws(() => context.__test.updateExpectedStockFormulas_(unsafeSpreadsheet), /ручне значення/, 'the repair never replaces an unexpected manual value');
assert.match(code, /addItem\('Оновити очікуваний залишок', 'updateExpectedStockFormulaMenu'\)/, 'the repair is reachable from the public CRM menu');

console.log('CRM expected-stock formula tests passed');
