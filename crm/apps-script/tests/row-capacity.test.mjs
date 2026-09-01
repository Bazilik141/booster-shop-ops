import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, "../Code.gs"), "utf8");

class Range {
  constructor(sheet, row, column, rows = 1, columns = 1) { this.sheet = sheet; this.row = row; this.column = column; this.rows = rows; this.columns = columns; }
  cells() { return Array.from({ length: this.rows }, (_, row) => Array.from({ length: this.columns }, (_, column) => this.sheet.cell(this.row + row, this.column + column))); }
  getValues() { return this.cells().map(row => row.map(cell => cell.value ?? "")); }
  getFormulas() { return this.cells().map(row => row.map(cell => cell.formula || "")); }
  getValue() { return this.sheet.cell(this.row, this.column).value ?? ""; }
  getFormula() { return this.sheet.cell(this.row, this.column).formula || ""; }
  setValue(value) { const cell = this.sheet.cell(this.row, this.column); cell.value = value; cell.formula = ""; return this; }
  setValues(values) { values.forEach((row, rowIndex) => row.forEach((value, columnIndex) => this.setCellValue_(rowIndex, columnIndex, value))); return this; }
  setCellValue_(rowIndex, columnIndex, value) { const cell = this.sheet.cell(this.row + rowIndex, this.column + columnIndex); cell.value = value; cell.formula = ""; }
  setFormula(formula) { const cell = this.sheet.cell(this.row, this.column); cell.formula = formula; return this; }
  setFormulas(formulas) { formulas.forEach((row, rowIndex) => row.forEach((formula, columnIndex) => { this.sheet.cell(this.row + rowIndex, this.column + columnIndex).formula = formula || ""; })); return this; }
  clearContent() { this.cells().forEach(row => row.forEach(cell => { cell.value = ""; cell.formula = ""; })); return this; }
  copyTo(destination) {
    destination.cells().forEach((row, rowIndex) => row.forEach((cell, columnIndex) => {
      const source = this.sheet.cell(this.row + (rowIndex % this.rows), this.column + (columnIndex % this.columns));
      cell.formula = source.formula || "";
    }));
    return destination;
  }
  autoFill(destination) {
    destination.cells().forEach(row => row.forEach(cell => { cell.formula = this.sheet.cell(this.row, this.column).formula || ""; }));
    return destination;
  }
}

class Sheet {
  constructor(name, maxRows = 220, maxColumns = 32) { this.name = name; this.maxRows = maxRows; this.maxColumns = maxColumns; this.map = new Map(); }
  key(row, column) { return `${row}:${column}`; }
  cell(row, column) { const key = this.key(row, column); if (!this.map.has(key)) this.map.set(key, { value: "", formula: "" }); return this.map.get(key); }
  getRange(row, column, rows = 1, columns = 1) { return new Range(this, row, column, rows, columns); }
  getRangeList(ranges) {
    const columnNumber = letters => [...letters].reduce((value, letter) => value * 26 + letter.charCodeAt(0) - 64, 0);
    const parsed = ranges.map(a1 => {
      const match = /^([A-Z]+)(\d+):([A-Z]+)(\d+)$/.exec(a1);
      if (!match) throw new Error(`Unsupported A1 range in fixture: ${a1}`);
      const firstColumn = columnNumber(match[1]), firstRow = Number(match[2]);
      const lastColumn = columnNumber(match[3]), lastRow = Number(match[4]);
      return this.getRange(firstRow, firstColumn, lastRow - firstRow + 1, lastColumn - firstColumn + 1);
    });
    return { clearContent: () => { parsed.forEach(range => range.clearContent()); } };
  }
  getMaxRows() { return this.maxRows; }
  getMaxColumns() { return this.maxColumns; }
  getLastColumn() { return this.maxColumns; }
  getLastRow() { let last = 1; for (const [key, cell] of this.map) if (cell.value !== "" || cell.formula) last = Math.max(last, Number(key.split(":")[0])); return last; }
  getName() { return this.name; }
  insertRowsAfter(row, count) { assert.equal(row, this.maxRows, `${this.name}: rows are appended only at the grid end`); this.maxRows += count; return this; }
  getRowHeight() { return 21; }
  setRowHeights() { return this; }
}

class Spreadsheet {
  constructor(sheets) { this.sheets = new Map(sheets.map(sheet => [sheet.name, sheet])); }
  getSheetByName(name) { return this.sheets.get(name) || null; }
}

function appendOnlySheet(name, rows = 220) { return new Sheet(name, rows); }
function makeSpreadsheet() {
  return new Spreadsheet([
    appendOnlySheet("Продажі", 452), appendOnlySheet("Закупки", 309), appendOnlySheet("Списання", 216),
    appendOnlySheet("Витрати", 218), appendOnlySheet("Розхідники", 80), appendOnlySheet("Використання_компонентів", 1000),
    appendOnlySheet("Використання_фурнітури", 1000), appendOnlySheet("3D_облік_замовлень", 1000), appendOnlySheet("Новини_кандидати", 981),
    appendOnlySheet("Товари", 220), appendOnlySheet("РРЦ", 930), appendOnlySheet("Склад", 220)
  ]);
}

const spreadsheet = makeSpreadsheet();
const triggers = [];
const scriptProperties = {};
const sales = spreadsheet.getSheetByName("Продажі");
sales.getRange(3, 1, 450, 1).setValues(Array.from({ length: 450 }, (_, index) => [`ORDER-${index + 1}`]));
sales.getRange(452, 11).setFormula("=SUM($A$3:$A$433)");

const context = vm.createContext({
  JSON, Math, Number, String, Boolean, Array, Object, RegExp, Date, Error, isFinite, console,
  SpreadsheetApp: { CopyPasteType: { PASTE_FORMAT: "format", PASTE_DATA_VALIDATION: "validation", PASTE_FORMULA: "formula" }, AutoFillSeries: { DEFAULT_SERIES: "default" }, getActive: () => spreadsheet, flush() {} },
  PropertiesService: { getScriptProperties: () => ({ getProperty: key => scriptProperties[key] || "", setProperty: (key, value) => { scriptProperties[key] = value; } }) },
  ScriptApp: {
    getProjectTriggers: () => triggers.slice(),
    deleteTrigger: trigger => { const index = triggers.indexOf(trigger); if (index >= 0) triggers.splice(index, 1); },
    newTrigger: handler => ({ timeBased: () => ({ everyDays: days => ({ atHour: hour => ({ create: () => { const trigger = { getHandlerFunction: () => handler, days, hour }; triggers.push(trigger); return trigger; } }) }) }) })
  },
  Logger: { log() {} }
});
vm.runInContext(code + "\nfunction apiIntegrityCheck_(){ return { clean:true, problems:[] }; }\nglobalThis.__test={crmNextAppendRow_,crmExpandSheetFormulaRanges_,crmExpandLocalFormulaRanges_,crmCapacitySheetLastRow_,setupCrmRowCapacityTrigger_ : setupCrmRowCapacityTrigger};", context, { filename: "Code.gs" });

assert.equal(context.__test.crmNextAppendRow_(spreadsheet, "Продажі", 1), 453, "a full sales grid returns the first new row");
assert.equal(sales.getMaxRows(), 552, "sales receives its configured 100-row refill");
assert.ok(sales.getRange(453, 11).getFormula(), "the first new sales row receives the copied formula structure");
assert.equal(sales.getRange(453, 1).getValue(), "", "capacity growth never clones the template row's literal order ID");
assert.equal(sales.getRange(452, 11).getFormula(), "=SUM($A$3:$A$552)", "local formula ranges are extended to the new grid end");
assert.equal(context.__test.crmExpandSheetFormulaRanges_("=SUM('Списання'!$A$3:$A$197)", { "Списання": 226 }), "=SUM('Списання'!$A$3:$A$226)");
assert.equal(context.__test.crmExpandLocalFormulaRanges_("=SUM($A$3:$A$199)", 3, 218), "=SUM($A$3:$A$218)");

const products = spreadsheet.getSheetByName("Товари");
products.getRange(3, 1, 218, 1).setValues(Array.from({ length: 218 }, (_, index) => [`SKU-${index + 1}`]));
products.getRange(220, 10).setFormula("=SUM('Продажі'!$A$3:$A$433)");
assert.equal(context.__test.crmNextAppendRow_(spreadsheet, "Товари", 1), 221, "catalog append uses the next shared product row");
assert.equal(products.getMaxRows(), 230, "catalog products receive 10 rows");
assert.equal(spreadsheet.getSheetByName("РРЦ").getMaxRows(), 930, "a longer catalog sheet is not truncated");
assert.equal(spreadsheet.getSheetByName("Склад").getMaxRows(), 230, "catalog stock stays aligned with products");
assert.equal(context.__test.crmCapacitySheetLastRow_(products, 3), 230);
const firstSetup = context.__test.setupCrmRowCapacityTrigger_();
assert.equal(firstSetup.created, true, "the daily trigger is installed once");
assert.equal(firstSetup.initial.deferred, true, "setup does not run the heavy formula refresh interactively");
assert.equal(triggers.length, 1);
assert.equal(triggers[0].days, 1);
assert.equal(triggers[0].hour, 4);
const repeatSetup = context.__test.setupCrmRowCapacityTrigger_();
assert.equal(repeatSetup.created, false, "a repeat does not create a duplicate trigger");
assert.equal(triggers.length, 1);
assert.equal(repeatSetup.schedule, "daily");
assert.equal(repeatSetup.scheduled_hour, 4);
const legacyTrigger = triggers[0];
delete scriptProperties.CRM_ROW_CAPACITY_TRIGGER_SCHEDULE;
const migratedSetup = context.__test.setupCrmRowCapacityTrigger_();
assert.equal(migratedSetup.created, true, "an unlabelled legacy trigger is replaced");
assert.equal(migratedSetup.replaced_schedule, true);
assert.equal(triggers.length, 1);
assert.notEqual(triggers[0], legacyTrigger);
assert.equal(triggers[0].hour, 4);

assert.match(code, /'Продажі': Object\.freeze\(\{ first_row: 3, key_column: 1, min_free_rows: 20, add_rows: 100 \}\)/);
assert.match(code, /'Закупки': Object\.freeze\(\{ first_row: 3, key_column: 1, min_free_rows: 20, add_rows: 50 \}\)/);
assert.match(code, /function setupCrmRowCapacityTrigger\(\)/);
assert.match(code, /everyDays\(1\)\.atHour\(CRM_ROW_CAPACITY_TRIGGER_HOUR_\)/);
assert.doesNotMatch(code.match(/function crmCopyRowStructure_\([\s\S]*?\n\}/)[0], /copyTypes\.PASTE_FORMULA/, "capacity growth never uses literal-cloning PASTE_FORMULA");
assert.match(code.match(/function crmCopyRowStructure_\([\s\S]*?\n\}/)[0], /autoFill/, "capacity growth uses native localized-formula autofill");
assert.doesNotMatch(code.match(/function maintainCrmRowCapacity_\([\s\S]*?\n\}/)[0], /apiIntegrityCheck_|SpreadsheetApp\.flush/, "nightly capacity maintenance cannot block for six minutes on the full integrity scan");
console.log("CRM row capacity tests passed");
