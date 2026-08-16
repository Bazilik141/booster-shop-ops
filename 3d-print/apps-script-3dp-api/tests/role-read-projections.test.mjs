import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, "../Code.gs"), "utf8");
const repositoryRoot = path.resolve(here, "../../..");

function resolveV23ExportPath() {
  const matches = fs.readdirSync(repositoryRoot, { withFileTypes: true })
    .filter((entry) => entry.isFile() && /^Версія 23.*\.txt$/u.test(entry.name))
    .map((entry) => path.join(repositoryRoot, entry.name));
  assert.equal(matches.length, 1, `Expected exactly one V23 export matching "Версія 23*.txt" in ${repositoryRoot}; found ${matches.length}.`);
  return matches[0];
}

function columnNumber(column) {
  return [...column.toUpperCase()].reduce((value, char) => value * 26 + char.charCodeAt(0) - 64, 0);
}

function parseA1(a1) {
  const match = /^([A-Z]+)(\d+)(?::([A-Z]+)(\d+))?$/i.exec(a1);
  if (!match) throw new Error(`unsupported A1 range: ${a1}`);
  return {
    startColumn: columnNumber(match[1]),
    startRow: Number(match[2]),
    endColumn: columnNumber(match[3] || match[1]),
    endRow: Number(match[4] || match[2]),
  };
}

class Range {
  constructor(sheet, startRow, startColumn, rows = 1, columns = 1) {
    this.sheet = sheet;
    this.startRow = startRow;
    this.startColumn = startColumn;
    this.rows = rows;
    this.columns = columns;
  }

  getValues() {
    return Array.from({ length: this.rows }, (_, row) => Array.from({ length: this.columns }, (_, column) =>
      this.sheet.valueAt(this.startRow + row, this.startColumn + column)));
  }

  getDisplayValues() {
    return this.getValues().map((row) => row.map((value) => value === null || typeof value === "undefined" ? "" : String(value)));
  }

  getFormulas() {
    return Array.from({ length: this.rows }, (_, row) => Array.from({ length: this.columns }, (_, column) =>
      this.sheet.formulaAt(this.startRow + row, this.startColumn + column)));
  }

  getValue() { return this.getValues()[0][0]; }
  getDisplayValue() { return this.getDisplayValues()[0][0]; }
  getFormula() { return this.getFormulas()[0][0]; }
  getNumberFormat() { return "0.##########"; }
  setNumberFormat() { return this; }
  setFontColor() { return this; }
  getFontColors() { return Array.from({ length: this.rows }, () => Array.from({ length: this.columns }, () => "#0000ff")); }
  setValue(value) { this.sheet.setValueAt(this.startRow, this.startColumn, value); return this; }
  setValues(values) {
    values.forEach((row, rowIndex) => row.forEach((value, columnIndex) =>
      this.sheet.setValueAt(this.startRow + rowIndex, this.startColumn + columnIndex, value)));
    return this;
  }
  setFormula(value) { this.sheet.setFormulaAt(this.startRow, this.startColumn, value); return this; }
}

class Sheet {
  constructor(name, rows = [[]]) {
    this.name = name;
    this.rows = rows.map((row) => [...row]);
    this.formulas = [];
    this.hidden = false;
  }

  valueAt(row, column) { return this.rows[row - 1]?.[column - 1] ?? ""; }
  formulaAt(row, column) { return this.formulas[row - 1]?.[column - 1] ?? ""; }
  setValueAt(row, column, value) {
    while (this.rows.length < row) this.rows.push([]);
    while (this.rows[row - 1].length < column) this.rows[row - 1].push("");
    this.rows[row - 1][column - 1] = value;
  }
  setFormulaAt(row, column, value) {
    while (this.formulas.length < row) this.formulas.push([]);
    while (this.formulas[row - 1].length < column) this.formulas[row - 1].push("");
    this.formulas[row - 1][column - 1] = value;
  }
  getName() { return this.name; }
  getLastRow() {
    for (let row = this.rows.length; row >= 1; row -= 1) if (this.rows[row - 1].some((value) => value !== "")) return row;
    return 0;
  }
  getLastColumn() { return Math.max(1, ...this.rows.map((row) => row.length)); }
  getMaxRows() { return 1000; }
  getMaxColumns() { return 30; }
  getRange(...args) {
    if (typeof args[0] === "string") {
      const parsed = parseA1(args[0]);
      return new Range(this, parsed.startRow, parsed.startColumn, parsed.endRow - parsed.startRow + 1, parsed.endColumn - parsed.startColumn + 1);
    }
    return new Range(this, args[0], args[1], args[2] || 1, args[3] || 1);
  }
  appendRow(row) { this.rows.push([...row]); }
  setFrozenRows() {}
  hideSheet() { this.hidden = true; }
  isSheetHidden() { return this.hidden; }
}

class Spreadsheet {
  constructor(sheets) { this.sheets = new Map(Object.entries(sheets)); }
  getId() { return "1yp15H3YJGkqI4Rx89G4QZHkD9m67gnWh58TsTTi-jjo"; }
  getSheetByName(name) { return this.sheets.get(name) || null; }
  insertSheet(name) { const sheet = new Sheet(name); this.sheets.set(name, sheet); return sheet; }
}

const salesHeaders = [
  "Дата", "SKU", "Назва", "Кількість", "Фактична ціна за од., грн (після знижки)", "Собівартість Сергія за од., грн",
  "Витрати BoosterShop за од., грн", "% прибутку Сергію", "Маржинальний прибуток за од., грн", "Статус",
  "Нараховано Сергію, грн", "Дохід Booster Shop, грн", "Канал", "№ замовлення", "Примітки", "Тип знижки",
  "Параметр знижки", "Погоджено з Сергієм (Так/Ні)", "Період (авто, РРРР-ММ)", "CRM row number",
  "РРЦ на момент продажу, грн", "Вартість фурнітури за од., грн (заморожена)", "Платник фурнітури", "Режим CRM",
  "Фурнітура власника за од., грн (заморожена)", "Фурнітура Сергія за од., грн (заморожена)", "Ціна викупу за од., грн (заморожена)",
];

const salesRow = [
  "2026-08-16", "FIG-001", "Тестовий виріб", 2, 300, 100, 20, 0.5, 80, "Проведено", 200, 400, "site", "ORDER-SECRET",
  "клієнт: приватні дані", "нема", "", "Так", "2026-08", 321, 350, 25, "Сергій", "Продаж", 10, 15, 120,
];

function makeSpreadsheet() {
  return new Spreadsheet({
    "Номенклатура": new Sheet("Номенклатура", [[
      "SKU", "Назва виробу", "Франшиза", "Тип", "Трек", "Статус", "Час друку за од., год", "Вага виробу за од., г",
      "Вага котушки, г", "Ціна котушки, грн", "Собівартість Сергія (виробнича), грн", "Дата оновлення", "Примітки",
      "Фурнітура (ціна-довідка), грн/шт", "API_статус_запису", "API_історія_змін", "РРЦ фактична, грн", "Ціна під викуп, грн", "Посилання на модель",
    ], ["FIG-001", "Тестовий виріб", "Pokemon", "Брелок", "Track-2", "Активний", 1.5, 10, 1000, 600, 50, "2026-08-16", "private", 0, "Активний", "owner history", 350, 120, "https://private"]]),
    "Друк-лог": new Sheet("Друк-лог", [[
      "Дата", "SKU", "Надруковано, шт", "Час друку факт, год", "Брак, шт", "Витрачено матеріалу, г (факт)", "Собівартість партії, грн", "Хто друкував", "Примітки", "API_статус_запису", "API_історія_змін",
    ], ["2026-08-16", "FIG-001", 3, 4, 1, 30, 180, "Сергій", "ok", "Активний", "history"]]),
    "Продажі": new Sheet("Продажі", [salesHeaders, salesRow]),
    "Виплати": new Sheet("Виплати", [["Період (РРРР-ММ)", "Нараховано Сергію за період, грн", "Термін перевірки Сергієм", "Дата фактичної виплати", "Статус", "Примітки"], ["2026-08", 200, "2026-08-20", "", "Очікує перевірки", "private owner note"]]),
    "Маркетингові_плюшки": new Sheet("Маркетингові_плюшки", [["Дата", "SKU", "Закуплено в Друга, шт", "Ціна закупівлі за од., грн", "Сума закупівлі, грн", "Видано як бонус, шт", "До замовлення №", "Примітки"], ["2026-08-16", "FIG-001", 0, 0, 0, 1, "ORDER-SECRET", "customer secret"]]),
    "Наявність": new Sheet("Наявність", [["SKU", "Назва", "Надруковано всього, шт", "Брак всього, шт", "Продано на сайті, шт", "Видано як плюшка, шт", "Наявно зараз, шт"], ["FIG-001", "Тестовий виріб", 3, 1, 2, 0, 0]]),
    "Аналітика": new Sheet("Аналітика", [["Маржа-калькулятор"], [], ["SKU", "Назва", "Собівартість Сергія, грн", "Витрати BoosterShop (фурнітура), грн", "Час друку, год", "% прибутку Сергію", "РРЦ фактична", "РРЦ рекомендована", "Маржа BoosterShop, грн", "Маржа BoosterShop, %", "Нараховано Сергію, грн", "Прибуток Сергію/год друку, грн"], ["FIG-001", "Тестовий виріб", 50, 10, 1.5, 0.5, 350, 400, 80, 0.23, 200, 133.33]]),
    "Налаштування": new Sheet("Налаштування", [["Глобальні константи 3D-друку", "", ""], ["Потужність принтера, кВт", 0.11, "кВт"], ["Ціна електроенергії, грн/кВт·год", 4.32, "грн/кВт·год"], ["Амортизація принтера, грн/год", 12, "грн/год"], ["Планований брак, частка", 0.08, "частка"]]),
    "Фурнітура_довідник": new Sheet("Фурнітура_довідник", [["Назва фурнітури", "Ціна, грн/шт"], ["Кільце", 5]]),
    "_Чернетки_партій": new Sheet("_Чернетки_партій", [["SKU", "Кількість у партії, шт", "Сумарна вага партії, г", "Сумарний час партії, год", "Вага котушки, г", "Ціна котушки, грн"], ["FIG-001", 2, 20, 3, 1000, 600]]),
    "_Коригування_наявності": new Sheet("_Коригування_наявності", [["SKU", "Зміна наявності, шт", "Причина", "Дата коригування (Київ)"], ["FIG-001", 1, "owner only", "2026-08-16"]]),
    "_Аудит_API": new Sheet("_Аудит_API", [["timestamp_kyiv", "identity", "operation", "sheet", "target", "old_value", "new_value", "details"]]),
  });
}

function loadApi(source = code, workbook = makeSpreadsheet()) {
  const context = {
    console,
    Utilities: { formatDate: () => "2026-08-16 12:00:00" },
    SpreadsheetApp: { getActiveSpreadsheet: () => workbook, flush() {} },
    PropertiesService: { getScriptProperties: () => ({ getProperty: () => "" }) },
  };
  vm.createContext(context);
  vm.runInContext(source, context, { filename: "Code.gs" });
  return { context, workbook };
}

function plain(value) { return JSON.parse(JSON.stringify(value)); }
function call(api, action, actor, params = {}) { return plain(api.context.handleGet3dp_({ action, ...params }, actor)); }
function codeOf(fn, code) { assert.throws(fn, (error) => error && error.code === code); }

const owner = { role: "owner", identity: "dashboard" };
const serhiy = { role: "serhiy", identity: "serhiy" };
const api = loadApi();
const ownerReadCases = [
  ["3dp_get_row", { sheet: "Номенклатура", sku: "FIG-001" }],
  ["3dp_get_range", { sheet: "Продажі", range: "A1:AA2" }],
  ["3dp_overview", {}], ["3dp_bootstrap", {}], ["3dp_information_bootstrap", {}],
  ["3dp_skus", {}], ["3dp_sales", {}], ["3dp_plyushky", {}], ["3dp_payouts", {}],
  ["3dp_print_log", {}], ["3dp_fixtures", {}], ["3dp_batch_draft", { sku: "FIG-001" }],
  ["3dp_stock_adjustments", {}],
];
const v23SourcePath = resolveV23ExportPath();
const v23Source = fs.readFileSync(v23SourcePath, "utf8");
const v23 = loadApi(v23Source);
ownerReadCases.forEach(([action, params]) => {
  assert.deepEqual(call(api, action, owner, params), call(v23, action, owner, params), `V23 owner response: ${action}`);
});

const ownerSales = call(api, "3dp_sales", owner);
assert.deepEqual(ownerSales.rows[0], Object.fromEntries([["row_number", 2], ...salesHeaders.map((header, index) => [header, salesRow[index]])]));
assert.equal(ownerSales.rows[0]["№ замовлення"], "ORDER-SECRET");
assert.equal(call(api, "3dp_get_range", owner, { sheet: "Налаштування", range: "A1:C5" }).range, "A1:C5");
assert.equal(call(api, "3dp_bootstrap", owner).analytics.range, "A3:N17");
assert.equal(call(api, "3dp_information_bootstrap", owner).sales.rows[0]["CRM row number"], 321);
assert.equal(call(api, "3dp_skus", owner).rows[0].availability["Наявно зараз, шт"], 0);
assert.equal(call(api, "3dp_print_log", owner).rows[0]["API_історія_змін"], "history");
assert.equal(call(api, "3dp_batch_draft", owner, { sku: "FIG-001" }).values.spool_price_uah, 600);
assert.equal(call(api, "3dp_stock_adjustments", owner).rows[0]["Причина"], "owner only");

const serhiySales = call(api, "3dp_sales", serhiy);
const serhiySalesHeaders = salesHeaders.filter((header) => !["№ замовлення", "Примітки", "CRM row number"].includes(header));
assert.deepEqual(Object.keys(serhiySales.rows[0]), ["row_number", ...serhiySalesHeaders]);
assert.equal(Object.hasOwn(serhiySales.rows[0], "№ замовлення"), false);
assert.equal(Object.hasOwn(serhiySales.rows[0], "Примітки"), false);
assert.equal(Object.hasOwn(serhiySales.rows[0], "CRM row number"), false);
assert.equal(serhiySales.rows[0]["Витрати BoosterShop за од., грн"], 20);
assert.equal(serhiySales.rows[0]["Вартість фурнітури за од., грн (заморожена)"], 25);
assert.equal(serhiySales.rows[0]["Фурнітура власника за од., грн (заморожена)"], 10);
assert.equal(serhiySales.rows[0]["Маржинальний прибуток за од., грн"], 80);
const serhiyPlyushky = call(api, "3dp_plyushky", serhiy).rows[0];
assert.deepEqual(Object.keys(serhiyPlyushky), ["row_number", "Дата", "SKU", "Закуплено в Друга, шт", "Ціна закупівлі за од., грн", "Сума закупівлі, грн", "Видано як бонус, шт"]);
assert.equal(Object.hasOwn(serhiyPlyushky, "До замовлення №"), false);
assert.equal(Object.hasOwn(serhiyPlyushky, "Примітки"), false);
assert.equal(serhiyPlyushky["Сума закупівлі, грн"], 0);
assert.equal(call(api, "3dp_get_row", serhiy, { sheet: "Номенклатура", sku: "FIG-001" }).row["API_історія_змін"], "owner history");
assert.equal(call(api, "3dp_print_log", serhiy).rows[0]["API_історія_змін"], "history");
assert.equal(call(api, "3dp_batch_draft", serhiy, { sku: "FIG-001" }).found, false);
assert.equal(call(api, "3dp_bootstrap", serhiy).settings.range, "B2:B5");
const serhiyAnalytics = call(api, "3dp_bootstrap", serhiy).analytics;
assert.equal(serhiyAnalytics.values[0].length, 11);
assert.deepEqual(serhiyAnalytics.values[0], ["SKU", "Назва", "Собівартість Сергія, грн", "Витрати BoosterShop (фурнітура), грн", "Час друку, год", "% прибутку Сергію", "РРЦ фактична", "Маржа BoosterShop, грн", "Маржа BoosterShop, %", "Нараховано Сергію, грн", "Прибуток Сергію/год друку, грн"]);
assert.equal(serhiyAnalytics.values[1][3], 10);
assert.equal(serhiyAnalytics.values[1][7], 80);
assert.equal(serhiyAnalytics.values[1][8], 0.23);
assert.equal(call(api, "3dp_get_range", serhiy, { sheet: "Продажі", range: "A1:B2" }).values[1][1], "FIG-001");
codeOf(() => call(api, "3dp_get_range", serhiy, { sheet: "Продажі", range: "N1:N2" }), "RANGE_NOT_PROJECTED");
codeOf(() => call(api, "3dp_get_range", serhiy, { sheet: "Продажі", range: "O1:O2" }), "RANGE_NOT_PROJECTED");
codeOf(() => call(api, "3dp_get_range", serhiy, { sheet: "Продажі", range: "T1:T2" }), "RANGE_NOT_PROJECTED");
assert.equal(call(api, "3dp_get_range", serhiy, { sheet: "Продажі", range: "U1:AA2" }).values[1][1], 25);
assert.equal(call(api, "3dp_get_range", serhiy, { sheet: "Маркетингові_плюшки", range: "A1:F2" }).values[1][4], 0);
codeOf(() => call(api, "3dp_get_range", serhiy, { sheet: "Маркетингові_плюшки", range: "G1:G2" }), "RANGE_NOT_PROJECTED");
codeOf(() => call(api, "3dp_get_range", serhiy, { sheet: "Маркетингові_плюшки", range: "H1:H2" }), "RANGE_NOT_PROJECTED");
codeOf(() => call(api, "3dp_get_range", serhiy, { sheet: "Аналітика", range: "H3:H4" }), "RANGE_NOT_PROJECTED");
codeOf(() => call(api, "3dp_get_range", serhiy, { sheet: "Налаштування", range: "A1:B5" }), "RANGE_NOT_PROJECTED");
codeOf(() => call(api, "3dp_stock_adjustments", serhiy), "READ_PROJECTION_FORBIDDEN");

api.workbook.getSheetByName("Продажі").setValueAt(1, 21, "Змінена РРЦ");
codeOf(() => call(api, "3dp_sales", serhiy), "READ_PROJECTION_HEADER_MISSING");
api.workbook.getSheetByName("Продажі").setValueAt(1, 21, "РРЦ на момент продажу, грн");

plain(api.context.writeAction3dp_(api.workbook, { sheet: "Налаштування", column: "B", sku_or_row: 2, expected_current: 0.11, value: "0,12" }, serhiy));
assert.equal(api.workbook.getSheetByName("Налаштування").getRange("B2").getValue(), 0.12);
assert.deepEqual(api.workbook.getSheetByName("_Журнал_налаштувань_3DP").getRange("A2:E2").getValues()[0], ["2026-08-16 12:00:00", "serhiy", "Потужність принтера, кВт", "0.11", "0.12"]);
const journalLength = api.workbook.getSheetByName("_Журнал_налаштувань_3DP").getLastRow();
codeOf(() => api.context.writeAction3dp_(api.workbook, { sheet: "Налаштування", column: "B", sku_or_row: 1, value: 1 }, serhiy), "INVALID_ROW");
codeOf(() => api.context.writeAction3dp_(api.workbook, { sheet: "Налаштування", column: "B", sku_or_row: 2, value: 8 }, serhiy), "SETTINGS_VALUE_OUT_OF_BOUNDS");
codeOf(() => api.context.writeAction3dp_(api.workbook, { sheet: "Налаштування", column: "B", sku_or_row: 2, value: true }, serhiy), "SETTINGS_VALUE_INVALID");
assert.equal(api.workbook.getSheetByName("_Журнал_налаштувань_3DP").getLastRow(), journalLength);
assert.equal(call(api, "3dp_settings_journal", serhiy).rows.length, 1);
plain(api.context.writeAction3dp_(api.workbook, { sheet: "Номенклатура", column: "J", sku_or_row: "FIG-001", expected_current: 600, value: 700 }, serhiy));
assert.match(api.workbook.getSheetByName("Номенклатура").getRange("P2").getDisplayValue(), /\[serhiy\] Ціна котушки: 600 → 700/);

plain(api.context.saveBatchDraftAction3dp_(api.workbook, { sku: "FIG-001", values: { quantity: 4 }, expected_current: { quantity: "" } }, serhiy));
assert.equal(call(api, "3dp_batch_draft", serhiy, { sku: "FIG-001" }).values.quantity, 4);
assert.equal(call(api, "3dp_batch_draft", owner, { sku: "FIG-001" }).values.quantity, 2);
assert.equal(api.workbook.getSheetByName("_Чернетки_партій").getRange("A3").getDisplayValue(), "serhiy::FIG-001");
const v23AfterSerhiyDraft = loadApi(v23Source, api.workbook);
assert.equal(call(v23AfterSerhiyDraft, "3dp_batch_draft", owner, { sku: "FIG-001" }).values.quantity, 2);

const restrictedCode = code.replace("const SERHIY_FULL_ECONOMICS_VISIBLE_3DP = true;", "const SERHIY_FULL_ECONOMICS_VISIBLE_3DP = false;");
const restricted = loadApi(restrictedCode);
const restrictedSales = call(restricted, "3dp_sales", serhiy).rows[0];
assert.equal(Object.hasOwn(restrictedSales, "Витрати BoosterShop за од., грн"), false);
assert.equal(Object.hasOwn(restrictedSales, "Вартість фурнітури за од., грн (заморожена)"), false);
assert.equal(Object.hasOwn(restrictedSales, "Фурнітура власника за од., грн (заморожена)"), false);
assert.equal(Object.hasOwn(restrictedSales, "№ замовлення"), false);
assert.equal(Object.hasOwn(restrictedSales, "Примітки"), false);
assert.equal(Object.hasOwn(restrictedSales, "CRM row number"), false);
codeOf(() => call(restricted, "3dp_stock_adjustments", serhiy), "READ_PROJECTION_FORBIDDEN");

console.log(JSON.stringify({ ok: true, owner_paths_preserved: 9, v23_owner_responses_compared: ownerReadCases.length, serhiy_projection_checks: 34, settings_journal_checks: 8, full_economics_checks: 7 }));
