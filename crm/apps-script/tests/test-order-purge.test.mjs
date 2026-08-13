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
  getCell(row, column) { return new Range(this.sheet, this.row + row - 1, this.column + column - 1); }
  setValues(values) { values.forEach((row, rowIndex) => row.forEach((value, columnIndex) => { const cell = this.sheet.cell(this.row + rowIndex, this.column + columnIndex); cell.value = value; cell.formula = ""; })); return this; }
  setValue(value) { return this.setValues([[value]]); }
  setFormula(formula) { const cell = this.sheet.cell(this.row, this.column); cell.formula = formula; cell.value = ""; return this; }
  clearContent() { this.cells().flat().forEach(cell => { cell.value = ""; cell.formula = ""; }); return this; }
}

class Sheet {
  constructor(name) { this.name = name; this.cellsByAddress = new Map(); }
  cell(row, column) { const key = row + ":" + column; if (!this.cellsByAddress.has(key)) this.cellsByAddress.set(key, { value: "", formula: "" }); return this.cellsByAddress.get(key); }
  getRange(row, column, rows = 1, columns = 1) { return new Range(this, row, column, rows, columns); }
  getLastRow() { let last = 0; this.cellsByAddress.forEach((cell, key) => { if (cell.value !== "" || cell.formula) last = Math.max(last, Number(key.split(":")[0])); }); return last; }
}

class Spreadsheet {
  constructor(sheets) { this.sheets = new Map(sheets.map(sheet => [sheet.name, sheet])); }
  getSheetByName(name) { return this.sheets.get(name) || null; }
}

function row(width, values) { const result = Array(width).fill(""); Object.entries(values).forEach(([column, value]) => { result[Number(column) - 1] = value; }); return result; }

const sales = new Sheet("Продажі");
sales.getRange(3, 1, 3, 32).setValues([
  row(32, { 1: "MAN-FOP-0006", 6: "ACC-3D-DITTO-410", 8: 1, 27: "Тестове замовлення: універсальне очищення" }),
  row(32, { 1: "MAN-FOP-0006", 6: "PKM-JP-MBX-XL", 8: 1 }),
  row(32, { 1: "MAN-FOP-0006", 6: "OP-JP-MBX-ST", 8: 1 }),
]);
sales.getRange(3, 3).setFormula("=TODAY()");
sales.getRange(6, 1, 1, 32).setValues([row(32, { 1: "REAL-FOP-0001", 6: "REAL-SKU", 8: 1, 27: "звичайне замовлення" })]);
const components = new Sheet("Використання_компонентів");
components.getRange(2, 1, 1, 15).setValues([row(15, { 1: "CMP-001", 3: "MAN-FOP-0006", 13: "WRT-9999" })]);
const fixtures = new Sheet("Використання_фурнітури");
fixtures.getRange(2, 1, 1, 14).setValues([row(14, { 1: "FIX-001", 4: "MAN-FOP-0006" })]);
const accounting = new Sheet("3D_облік_замовлень");
accounting.getRange(2, 1, 1, 20).setValues([row(20, { 1: "3DP-001", 3: "MAN-FOP-0006" })]);
const writeoffs = new Sheet("Списання");
writeoffs.getRange(3, 1, 1, 12).setValues([row(12, { 1: "WRT-9999", 4: "PKM-JP-MBX-XL", 6: 1, 12: "Продаж MAN-FOP-0006" })]);
const expenses = new Sheet("Витрати");
expenses.getRange(3, 1, 1, 11).setValues([row(11, { 1: "2026-08-13", 4: 20, 5: "Так", 6: "MAN-FOP-0006", 7: "[3dp_marketing:273] derived" })]);
const purchases = new Sheet("Закупки");
purchases.getRange(3, 1, 2, 17).setValues([
  row(17, { 1: "LOT-0001", 5: "PKM-JP-MBX-XL", 8: 3, 17: "На складі" }),
  row(17, { 1: "LOT-0002", 5: "OP-JP-MBX-ST", 8: 3, 17: "На складі" }),
]);
const crm = new Spreadsheet([sales, components, fixtures, accounting, writeoffs, expenses, purchases]);
const context = vm.createContext({
  JSON, Math, Number, String, Boolean, Array, Object, RegExp, Date, Error, isFinite, console,
  Logger: { log() {} },
  SpreadsheetApp: { openById: () => crm, getActive: () => crm, flush() {} },
  PropertiesService: { getScriptProperties: () => ({ getProperty: () => "", setProperty() {} }) },
  Utilities: { formatDate: () => "2026-08-13" },
  Session: { getScriptTimeZone: () => "Europe/Kyiv" },
  ContentService: { MimeType: { JSON: "JSON" }, createTextOutput: () => ({ setMimeType() { return this; } }) },
});
vm.runInContext(code + "\nglobalThis.__test={apiTestOrderCleanup_,testOrderCleanupOrderIds_,testOrderCleanupPlan_};", context, { filename: "Code.gs" });

context._getCrmSs = () => crm;
context.crm3dpConfig_ = () => ({ url: "https://example.test/exec", token: "test-token" });
let remoteCleared = false;
context.crm3dpFetchJson_ = (_, options) => {
  const payload = JSON.parse(options.payload);
  const rows = remoteCleared ? [] : [8];
  const adjustments = remoteCleared ? [] : [4];
  const gifts = remoteCleared ? [] : [5];
  if (!payload.dry_run) remoteCleared = true;
  return { action: "3dp_test_order_cleanup", order: payload.order, sales_rows: rows, stock_adjustment_rows: adjustments, marketing_gift_rows: gifts, gift_request_marker_count: rows.length, rows_to_clear: rows.length + adjustments.length + gifts.length, rows_cleared: payload.dry_run ? 0 : rows.length + adjustments.length + gifts.length, already_applied: remoteCleared && !!payload.dry_run };
};
context.updateSkuCurrentCost_ = () => ({ updated: 2 });
context.invalidateDoGetCache_ = () => {};

assert.deepEqual(JSON.parse(JSON.stringify(context.__test.testOrderCleanupOrderIds_(crm))), ["MAN-FOP-0006"]);
assert.throws(() => context.__test.apiTestOrderCleanup_(crm, { confirm: "no" }), /confirmation/);
const applied = context.__test.apiTestOrderCleanup_(crm, { confirm: "CLEAN TEST ORDERS" });
assert.deepEqual(JSON.parse(JSON.stringify(applied.preflight[0].counts)), { sales: 3, components: 1, fixtures: 1, accounting: 1, writeoffs: 1, expenses: 1, three_dp_marketing_expenses: 1, remote_sales: 1, remote_stock_adjustments: 1, remote_marketing_gifts: 1, remote_gift_request_markers: 1 });
assert.equal(applied.preflight[0].fifo_evidence.mode, "recomputed_from_purchases_sales_and_writeoffs");
assert.equal(applied.preflight[0].fifo_evidence.safe_to_apply, true);
assert.equal(applied.rows_cleared, 11);
assert.equal(applied.verification.ok, true);
assert.equal(sales.getRange(3, 1).getValues()[0][0], "");
assert.equal(sales.getRange(3, 3).getFormulas()[0][0], "=TODAY()", "formula cells are retained");
assert.equal(writeoffs.getRange(3, 1).getValues()[0][0], "");
assert.equal(expenses.getRange(3, 7).getValues()[0][0], "");
assert.equal(sales.getRange(6, 1).getValues()[0][0], "REAL-FOP-0001", "an unmarked order is never selected");
assert.deepEqual(JSON.parse(JSON.stringify(context.__test.testOrderCleanupOrderIds_(crm))), []);
assert.equal(context.__test.apiTestOrderCleanup_(crm, { confirm: "CLEAN TEST ORDERS" }).status, "no_matching_test_orders");

console.log(JSON.stringify({ ok: true, order: "MAN-FOP-0006", surfaces: 11, marker_scan: true, formula_preservation: true, remote_gifts: true }));
