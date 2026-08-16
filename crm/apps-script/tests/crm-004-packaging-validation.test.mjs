import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, '../Code.gs'), 'utf8');

class Rule {
  constructor(values, allowInvalid) { this.values = values; this.allowInvalid = allowInvalid; }
  getCriteriaType() { return 'VALUE_IN_LIST'; }
  getCriteriaValues() { return [this.values, true]; }
  getAllowInvalid() { return this.allowInvalid; }
}

class RuleBuilder {
  requireValueInList(values) { this.values = values.slice(); return this; }
  setAllowInvalid(value) { this.allowInvalid = value; return this; }
  build() { return new Rule(this.values, this.allowInvalid); }
}

class Range {
  constructor(sheet, row, column, rows = 1, columns = 1) { this.sheet = sheet; this.row = row; this.column = column; this.rows = rows; this.columns = columns; }
  cells() { return Array.from({ length: this.rows }, (_, row) => Array.from({ length: this.columns }, (_, column) => this.sheet.cell(this.row + row, this.column + column))); }
  getDataValidations() { return this.cells().map(row => row.map(cell => cell.validation || null)); }
  getDataValidation() { return this.sheet.cell(this.row, this.column).validation || null; }
  setDataValidation(rule) { this.cells().flat().forEach(cell => { cell.validation = rule; }); return this; }
}

class Sheet {
  constructor(name, maxRows = 501) { this.name = name; this.maxRows = maxRows; this.cellsByKey = new Map(); }
  cell(row, column) { const key = row + ':' + column; if (!this.cellsByKey.has(key)) this.cellsByKey.set(key, {}); return this.cellsByKey.get(key); }
  getMaxRows() { return this.maxRows; }
  getRange(row, column, rows, columns) {
    if (typeof row === 'string') {
      const match = /^([A-Z]+)(\d+)$/.exec(row);
      if (!match) throw new Error('Unsupported A1 range: ' + row);
      const col = Array.from(match[1]).reduce((total, char) => total * 26 + char.charCodeAt(0) - 64, 0);
      return new Range(this, Number(match[2]), col);
    }
    return new Range(this, row, column, rows, columns);
  }
}

class Spreadsheet {
  constructor(sheets) { this.sheets = new Map(sheets.map(sheet => [sheet.name, sheet])); }
  getSheetByName(name) { return this.sheets.get(name) || null; }
}

const sales = new Sheet('Продажі');
const updateForm = new Sheet('Оновити_продаж');
const spreadsheet = new Spreadsheet([sales, updateForm]);
const context = vm.createContext({
  Array, Boolean, Date, Error, JSON, Math, Number, Object, RegExp, String, console, isFinite,
  Logger: { log() {} },
  SpreadsheetApp: {
    DataValidationCriteria: { VALUE_IN_LIST: 'VALUE_IN_LIST' },
    getActive: () => spreadsheet,
    newDataValidation: () => new RuleBuilder()
  }
});
vm.runInContext(code + '\nglobalThis.__test = { canonicalCrmPackagingType_, crmPackagingComparisonKey_, getPackagingCost_, ensureCrmPackagingValidation_ };', context, { filename: 'Code.gs' });

assert.equal(context.__test.canonicalCrmPackagingType_("Середня м'яка 16х14 см"), "Середня м'яка 16x14 см");
assert.equal(context.__test.crmPackagingComparisonKey_('Мала м’яка 14х12 см'), context.__test.crmPackagingComparisonKey_("Мала м'яка 14x12 см"));

const first = context.__test.ensureCrmPackagingValidation_(spreadsheet);
assert.equal(first.sales_rule_changed, true);
assert.equal(first.update_form_rule_changed, true);
assert.equal(first.already_applied, false);
assert.equal(sales.getRange(274, 29).getDataValidation().getAllowInvalid(), false, 'AC274 receives the canonical strict rule');
assert.deepEqual(JSON.parse(JSON.stringify(sales.getRange(274, 29).getDataValidation().getCriteriaValues()[0])), ['', "Мала м'яка 14x12 см", "Середня м'яка 16x14 см", 'Велика пакет 17x30 см', 'Конверт Airpock 14x22 см', 'Інше']);

const repeat = context.__test.ensureCrmPackagingValidation_(spreadsheet);
assert.equal(repeat.already_applied, true, 'repeat only verifies the exact rule and does not change it');
assert.match(code, /const currentPackagingType = canonicalCrmPackagingType_\(current\[28\]\)/);
assert.match(code, /const packagingValidation = packagingChanged \? ensureCrmPackagingValidation_\(ss\) : null/);
console.log('CRM-004 packaging validation tests passed');
