import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, "../Code.gs"), "utf8");

class Range {
  constructor(sheet, row, col, rows = 1, cols = 1) { this.sheet = sheet; this.row = row; this.col = col; this.rows = rows; this.cols = cols; }
  cells() { return Array.from({ length: this.rows }, (_, r) => Array.from({ length: this.cols }, (_, c) => this.sheet.cell(this.row + r, this.col + c))); }
  getValues() { return this.cells().map(row => row.map(cell => cell.value ?? "")); }
  getDisplayValues() { return this.getValues().map(row => row.map(value => String(value ?? ""))); }
  getValue() { return this.sheet.cell(this.row, this.col).value ?? ""; }
  getFormula() { return this.sheet.cell(this.row, this.col).formula || ""; }
  getFormulas() { return this.cells().map(row => row.map(cell => cell.formula || "")); }
  setValue(value) { this.sheet.cell(this.row, this.col).value = value; return this; }
  setValues(values) { values.forEach((row, r) => row.forEach((value, c) => { this.sheet.cell(this.row + r, this.col + c).value = value; })); return this; }
  setFormula(formula) { this.sheet.cell(this.row, this.col).formula = formula; return this; }
  clearContent() { this.cells().flat().forEach(cell => { cell.value = ""; cell.formula = ""; }); return this; }
  clearDataValidations() { return this; }
}

class Sheet {
  constructor(name) { this.name = name; this.map = new Map(); this.maxRows = 500; this.maxColumns = 32; }
  key(row, col) { return `${row}:${col}`; }
  cell(row, col) { const key = this.key(row, col); if (!this.map.has(key)) this.map.set(key, { value: "", formula: "" }); return this.map.get(key); }
  getRange(row, col, rows = 1, cols = 1) { return new Range(this, row, col, rows, cols); }
  getLastRow() { let last = 0; for (const [key, cell] of this.map) if (cell.value !== "" || cell.formula) last = Math.max(last, Number(key.split(":")[0])); return last; }
  getLastColumn() { let last = 0; for (const [key, cell] of this.map) if (cell.value !== "" || cell.formula) last = Math.max(last, Number(key.split(":")[1])); return last; }
  getMaxRows() { return this.maxRows; }
  getMaxColumns() { return this.maxColumns; }
  insertColumnsAfter(_, count) { this.maxColumns += count; }
}

class Spreadsheet {
  constructor(sheets) { this.sheets = new Map(sheets.map(sheet => [sheet.name, sheet])); }
  getSheetByName(name) { return this.sheets.get(name) || null; }
}

const sales = new Sheet("Продажі"), writeoffs = new Sheet("Списання"), consumables = new Sheet("Розхідники"), ledger = new Sheet("Використання_компонентів");
sales.getRange(2, 30, 1, 3).setValues([["Метод собівартості", "Аудит собівартості", "Дата фіксації собівартості"]]);
const regular = Array(32).fill("");
regular[0] = "OC-FOP-TEST-MBX"; regular[2] = new Date("2026-08-16T00:00:00Z"); regular[5] = "PKM-JP-MZERO-BST"; regular[6] = "Regular booster"; regular[7] = 5; regular[10] = 700; regular[11] = 43; regular[12] = 47.54;
const mystery = Array(32).fill("");
mystery[0] = "OC-FOP-TEST-MBX"; mystery[2] = new Date("2026-08-16T00:00:00Z"); mystery[5] = "PKM-JP-MBX-XL"; mystery[6] = "Містері бокс Pokémon TCG"; mystery[7] = 2; mystery[10] = 2000; mystery[11] = 1209.19; mystery[12] = 1295.69;
sales.getRange(3, 1, 2, 32).setValues([regular, mystery]);

const componentHeaders = ["ID", "Дата", "Замовлення", "Тип", "Код / назва", "Кількість", "Собівартість 1 шт / ПРРО", "Управлінська собівартість 1 шт", "Вартість / ПРРО", "Управлінська вартість", "Примітка", "Створено", "ID списання", "CRM row number", "SKU цілі"];
ledger.getRange(1, 1, 1, componentHeaders.length).setValues([componentHeaders]);
const targetEntries = [
  ["INFX", 354.56, 375.84], ["MZERO", 123.21, 130.59], ["MSYM", 326.28, 345.84],
  ["MBRV", 181, 191.86], ["SPIN", 185.44, 196.56], ["BBLT", 217.84, 230.91]
];
const ledgerRows = targetEntries.map((entry, index) => ["CMP-T" + index, new Date(), "OC-FOP-TEST-MBX", "SKU", entry[0], 1, entry[1], entry[2], entry[1], entry[2], "", new Date(), "WRT-T" + index, 4, "PKM-JP-MBX-XL"]);
ledgerRows.push(
  ["CMP-U1", new Date(), "OC-FOP-TEST-MBX", "Розхідник", "Брошки", 1, 0, 18.98, 0, 18.98, "", new Date(), "", "", ""],
  ["CMP-U2", new Date(), "OC-FOP-TEST-MBX", "Розхідник", "Мала м'яка", 4, 0, 3.6, 0, 14.4, "", new Date(), "", "", ""],
  ["CMP-U3", new Date(), "OC-FOP-TEST-MBX", "SKU", "ACC-008", 1, 7.83, 8.3, 7.83, 8.3, "", new Date(), "WRT-U1", "", ""],
  ["CMP-U4", new Date(), "OC-FOP-TEST-MBX", "SKU", "ACC-001", 1, 29.4, 31.16, 29.4, 31.16, "", new Date(), "WRT-U2", "", ""]
);
ledger.getRange(2, 1, ledgerRows.length, 15).setValues(ledgerRows);

const writeoffRows = targetEntries.map((entry, index) => ["WRT-T" + index, new Date(), "Інше", entry[0], "", 1, "", "", index < 4 ? entry[1] : "", index < 4 ? entry[2] : "", "", "Продаж OC-FOP-TEST-MBX"]);
writeoffRows.push(
  ["WRT-U1", new Date(), "Інше", "ACC-008", "", 1, "", "", "", "", "", "Продаж OC-FOP-TEST-MBX"],
  ["WRT-U2", new Date(), "Інше", "ACC-001", "", 1, "", "", "", "", "", "Продаж OC-FOP-TEST-MBX"]
);
writeoffs.getRange(3, 1, writeoffRows.length, 12).setValues(writeoffRows);
consumables.getRange(4, 1, 3, 9).setValues([
  ["Стікер лого+QR", "", 1.17, 100, "", 0, "", 0, 100],
  ["Блайнд-пакет для картки", "", 1.32, 100, "", 0, "", 0, 100],
  ["Наліпка Mystery Box", "", 0.77, 100, "", 0, "", 0, 100]
]);

const spreadsheet = new Spreadsheet([sales, writeoffs, consumables, ledger]);
const context = vm.createContext({ JSON, Math, Number, String, Boolean, Array, Object, RegExp, Date, Error, Set, isFinite, console, Logger: { log() {} }, SpreadsheetApp: { getActive: () => spreadsheet, openById: () => spreadsheet, flush() {} }, PropertiesService: { getScriptProperties: () => ({ getProperty: () => "" }) }, Utilities: { formatDate: () => "2026-08-16", sleep() {} }, Session: { getScriptTimeZone: () => "Europe/Kyiv" }, ContentService: { MimeType: { JSON: "JSON" }, createTextOutput: () => ({ setMimeType() { return this; } }) } });
vm.runInContext(code + "\nglobalThis.__test={repairMysteryBoxOrderComponentCost_,componentWriteoffFormulaSet_};", context, { filename: "Code.gs" });

const repaired = context.__test.repairMysteryBoxOrderComponentCost_(spreadsheet, "OC-FOP-TEST-MBX");
assert.equal(repaired.ok, true);
assert.equal(repaired.writeoff_formula_rows_repaired, 8, "all linked writeoffs receive the canonical formulas");
assert.equal(sales.getRange(4, 12).getValue(), 707.96, "targeted Mystery Box PRRO components are counted once");
assert.equal(sales.getRange(4, 13).getValue(), 764.41, "targeted Mystery Box management components are counted once");
assert.equal(sales.getRange(3, 12).getValue(), 44.93, "order-level PRRO components remain allocated by revenue");
assert.equal(sales.getRange(3, 13).getValue(), 51.32, "order-level management components remain allocated by revenue");
assert.equal(writeoffs.getRange(7, 5).getFormula(), context.__test.componentWriteoffFormulaSet_(7)[0], "previously blank component writeoff gets the canonical name formula");

const repeat = context.__test.repairMysteryBoxOrderComponentCost_(spreadsheet, "OC-FOP-TEST-MBX");
assert.equal(repeat.already_applied, true, "repeat repair must be idempotent");
assert.equal(repeat.writeoff_formula_rows_repaired, 0);
assert.equal(sales.getRange(4, 12).getValue(), 707.96);
assert.equal(sales.getRange(4, 13).getValue(), 764.41);
console.log("Mystery Box order-component repair tests passed");
