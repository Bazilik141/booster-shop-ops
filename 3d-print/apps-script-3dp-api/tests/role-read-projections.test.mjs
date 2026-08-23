import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, "../Code.gs"), "utf8");
const repositoryRoot = path.resolve(here, "../../..");

function resolveWp1ReadBaselinePath() {
  const matches = fs.readdirSync(repositoryRoot, { withFileTypes: true })
    .filter((entry) => entry.isFile() && /^Версія (?:25|23).*\.txt$/u.test(entry.name))
    .sort((left, right) => Number(/^Версія (\d+)/u.exec(right.name)?.[1] || 0) - Number(/^Версія (\d+)/u.exec(left.name)?.[1] || 0))
    .map((entry) => path.join(repositoryRoot, entry.name));
  assert.ok(matches.length >= 1, `Expected a V25 or V23 baseline export in ${repositoryRoot}; found none.`);
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
  clearContent() {
    for (let row = 0; row < this.rows; row += 1) {
      for (let column = 0; column < this.columns; column += 1) {
        this.sheet.setValueAt(this.startRow + row, this.startColumn + column, "");
        this.sheet.setFormulaAt(this.startRow + row, this.startColumn + column, "");
      }
    }
    return this;
  }
  getCell(row, column) { return new Range(this.sheet, this.startRow + row - 1, this.startColumn + column - 1); }
  offset(row, column, rows = this.rows, columns = this.columns) {
    return new Range(this.sheet, this.startRow + row, this.startColumn + column, rows, columns);
  }
  getFontColors() { return Array.from({ length: this.rows }, () => Array.from({ length: this.columns }, () => "#0000ff")); }
  getNotes() { return Array.from({ length: this.rows }, () => Array.from({ length: this.columns }, () => "")); }
  getNumberFormats() { return Array.from({ length: this.rows }, () => Array.from({ length: this.columns }, () => "0.##########")); }
  setNotes() { return this; }
  setFontColors() { return this; }
  setNumberFormats() { return this; }
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

  valueAt(row, column) {
    const formula = this.formulaAt(row, column);
    const directSkuReference = /^='Номенклатура'!A(\d+)$/.exec(formula);
    if (directSkuReference && this.workbook) {
      return this.workbook.getSheetByName("Номенклатура").valueAt(Number(directSkuReference[1]), 1);
    }
    return this.rows[row - 1]?.[column - 1] ?? "";
  }
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
  constructor(sheets) {
    this.sheets = new Map(Object.entries(sheets));
    this.sheets.forEach((sheet) => { sheet.workbook = this; });
  }
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
  let uuidCounter = 0;
  const context = {
    console,
    Utilities: {
      formatDate: () => "2026-08-16 12:00:00",
      getUuid: () => `00000000-0000-4000-8000-${String(++uuidCounter).padStart(12, "0")}`,
    },
    SpreadsheetApp: { getActiveSpreadsheet: () => workbook, flush() {} },
    PropertiesService: { getScriptProperties: () => ({ getProperty: () => "" }) },
    LockService: { getScriptLock: () => ({ tryLock: () => true, releaseLock() {} }) },
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
const repairApi = loadApi();
const repairNomenclature = repairApi.workbook.getSheetByName("Номенклатура");
repairNomenclature.setValueAt(3, 1, "BR-BULB-100");
repairNomenclature.setValueAt(3, 2, "Брелок Бульбазавр");
repairNomenclature.setValueAt(3, 15, "Активний");
const initialRepair = plain(repairApi.context.repair3dpActiveNomenclatureAnalytics());
assert.equal(initialRepair.initialized_skus.includes("BR-BULB-100"), true,
  "the public repair initializes an already-active SKU missing from Analytics");
assert.equal(repairApi.workbook.getSheetByName("Аналітика").getRange(5, 6).getValue(), 0.5,
  "the public repair applies the approved 50% share to that existing SKU");
assert.equal(call(repairApi, "3dp_get_row", owner, { sheet: "Номенклатура", sku: "BR-BULB-100" }).row["% прибутку Сергію"], 0.5,
  "the repaired active SKU no longer throws a missing-profit-share error");
const ownerCreateRollbackApi = loadApi();
ownerCreateRollbackApi.workbook.getSheetByName("Аналітика").setValueAt(3, 1, "unexpected analytics header");
codeOf(() => ownerCreateRollbackApi.context.createNomenclatureOwnerAction3dp_(ownerCreateRollbackApi.workbook, {
  sku: "BR-ROLL-100", values: { B: "Rollback test", D: "Брелок", Q: 200 },
}, owner), "ANALYTICS_SCHEMA_NOT_READY");
assert.equal(ownerCreateRollbackApi.workbook.getSheetByName("Номенклатура").getRange(3, 1).getDisplayValue(), "",
  "a failed owner quick-create must not leave a canonical SKU behind");
assert.equal(ownerCreateRollbackApi.workbook.getSheetByName("Номенклатура").getRange(3, 15).getDisplayValue(), "",
  "a failed owner quick-create must not leave an active status behind");
const ownerReadCases = [
  ["3dp_get_row", { sheet: "Номенклатура", sku: "FIG-001" }],
  ["3dp_get_range", { sheet: "Продажі", range: "A1:AA2" }],
  ["3dp_overview", {}], ["3dp_bootstrap", {}], ["3dp_information_bootstrap", {}],
  ["3dp_skus", {}], ["3dp_sales", {}], ["3dp_plyushky", {}], ["3dp_payouts", {}],
  ["3dp_print_log", {}], ["3dp_fixtures", {}], ["3dp_batch_draft", { sku: "FIG-001" }],
  ["3dp_stock_adjustments", {}],
];
const wp1BaselineSourcePath = resolveWp1ReadBaselinePath();
const wp1BaselineSource = fs.readFileSync(wp1BaselineSourcePath, "utf8");
const v23 = loadApi(wp1BaselineSource);
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
codeOf(() => call(api, "3dp_stock_adjustments", serhiy), "STOCK_ADJUSTMENT_SCHEMA_NOT_READY");

api.workbook.getSheetByName("Продажі").setValueAt(1, 21, "Змінена РРЦ");
codeOf(() => call(api, "3dp_sales", serhiy), "READ_PROJECTION_HEADER_MISSING");
api.workbook.getSheetByName("Продажі").setValueAt(1, 21, "РРЦ на момент продажу, грн");

plain(api.context.writeAction3dp_(api.workbook, { sheet: "Налаштування", column: "B", sku_or_row: 2, expected_current: 0.11, value: "0,12" }, serhiy));
assert.equal(api.workbook.getSheetByName("Налаштування").getRange("B2").getValue(), 0.12);
assert.deepEqual(api.workbook.getSheetByName("_Журнал_налаштувань_3DP").getRange("A2:F2").getValues()[0], ["2026-08-16 12:00:00", "serhiy", "Потужність принтера, кВт", "0.11", "0.12", ""]);
const journalLength = api.workbook.getSheetByName("_Журнал_налаштувань_3DP").getLastRow();
codeOf(() => api.context.writeAction3dp_(api.workbook, { sheet: "Налаштування", column: "B", sku_or_row: 1, value: 1 }, serhiy), "INVALID_ROW");
codeOf(() => api.context.writeAction3dp_(api.workbook, { sheet: "Налаштування", column: "B", sku_or_row: 2, value: 8 }, serhiy), "SETTINGS_VALUE_OUT_OF_BOUNDS");
codeOf(() => api.context.writeAction3dp_(api.workbook, { sheet: "Налаштування", column: "B", sku_or_row: 2, value: true }, serhiy), "SETTINGS_VALUE_INVALID");
assert.equal(api.workbook.getSheetByName("_Журнал_налаштувань_3DP").getLastRow(), journalLength);
assert.equal(call(api, "3dp_settings_journal", serhiy).rows.length, 1);
plain(api.context.writeAction3dp_(api.workbook, { sheet: "Номенклатура", column: "J", sku_or_row: "FIG-001", expected_current: 600, value: 700 }, serhiy));
assert.match(api.workbook.getSheetByName("Номенклатура").getRange("P2").getDisplayValue(), /\[serhiy\] Ціна котушки: 600 → 700/);

const nomenclatureJournal = api.workbook.getSheetByName("_Журнал_налаштувань_3DP");
const nomenclatureJournalBefore = nomenclatureJournal.getLastRow();
plain(api.context.writeAction3dp_(api.workbook, { sheet: "Номенклатура", column: "Q", sku_or_row: "FIG-001", expected_current: 350, value: "360,5" }, serhiy));
plain(api.context.writeAction3dp_(api.workbook, { sheet: "Номенклатура", column: "R", sku_or_row: "FIG-001", expected_current: 120, value: 130 }, owner));
plain(api.context.writeAction3dp_(api.workbook, { sheet: "Номенклатура", column: "S", sku_or_row: "FIG-001", expected_current: "https://private", value: "https://models.example/FIG-001" }, serhiy));
assert.deepEqual(nomenclatureJournal.getRange("A3:F5").getValues(), [
  ["2026-08-16 12:00:00", "serhiy", "РРЦ фактична, грн", "350", "360.5", "FIG-001"],
  ["2026-08-16 12:00:00", "owner", "Ціна під викуп, грн", "120", "130", "FIG-001"],
  ["2026-08-16 12:00:00", "serhiy", "Посилання на модель", "https://private", "https://models.example/FIG-001", "FIG-001"],
]);
codeOf(() => api.context.writeAction3dp_(api.workbook, { sheet: "Номенклатура", column: "Q", sku_or_row: "FIG-001", value: "not a number" }, serhiy), "NOMENCLATURE_PRICE_INVALID");
codeOf(() => api.context.writeAction3dp_(api.workbook, { sheet: "Номенклатура", column: "R", sku_or_row: "FIG-001", value: -1 }, serhiy), "NOMENCLATURE_PRICE_INVALID");
codeOf(() => api.context.writeAction3dp_(api.workbook, { sheet: "Номенклатура", column: "S", sku_or_row: "FIG-001", value: "ftp://models.example/FIG-001" }, serhiy), "MODEL_URL_INVALID");
assert.equal(nomenclatureJournal.getLastRow(), nomenclatureJournalBefore + 3);
codeOf(() => api.context.appendRowAction3dp_(api.workbook, {
  sheet: "Номенклатура",
  values: { A: "FIG-002", Q: 400, R: 150, S: "https://models.example/FIG-002" },
}, owner), "SPECIALIZED_ACTION_REQUIRED");
codeOf(() => api.context.createNomenclatureOwnerAction3dp_(api.workbook, {
  sku: "BR-FAIL-100", values: { B: "Неавторизований", D: "Брелок", Q: 400 },
}, serhiy), "FORBIDDEN");
codeOf(() => api.context.createNomenclatureOwnerAction3dp_(api.workbook, {
  sku: "BR-FAIL-100", values: { B: "Невідповідний тип", D: "Фігурка", Q: 400 },
}, owner), "SKU_TYPE_MISMATCH");
const ownerCreated = plain(api.context.handlePost3dp_({
  action: "3dp_nomenclature_owner_create", sku: "FIG-OWNR-002",
  values: { B: "Швидко створена фігурка", D: "Фігурка", M: "owner quick create", Q: 400, R: 150, S: "https://models.example/FIG-OWNR-002" },
}, owner));
assert.deepEqual(ownerCreated, {
  action: "3dp_nomenclature_owner_create", row: 3, sku: "FIG-OWNR-002", status: "Активний", analytics_initialized: true,
});
const ownerCreatedNomenclature = api.workbook.getSheetByName("Номенклатура");
assert.equal(ownerCreatedNomenclature.getRange(ownerCreated.row, 15).getDisplayValue(), "Активний");
assert.match(ownerCreatedNomenclature.getRange(ownerCreated.row, 16).getDisplayValue(), /Швидко створено власником/);
[7, 8, 9, 10, 11].forEach((column) => assert.equal(ownerCreatedNomenclature.getRange(ownerCreated.row, column).getValue(), "",
  "owner quick-create must not write production cost inputs or K"));
assert.equal(call(api, "3dp_get_row", owner, { sheet: "Номенклатура", sku: "FIG-OWNR-002" }).row["% прибутку Сергію"], 0.5,
  "owner quick-create provisions the Analytics share before the SKU can be read");
assert.equal(nomenclatureJournal.getLastRow(), nomenclatureJournalBefore + 3,
  "owner quick-create records initial data in the audit trail, not the Q/R/S change journal");
assert.equal(api.workbook.getSheetByName("_Аудит_API").getRange(api.workbook.getSheetByName("_Аудит_API").getLastRow(), 3).getDisplayValue(), "NOMENCLATURE_OWNER_CREATE");

plain(api.context.saveBatchDraftAction3dp_(api.workbook, { sku: "FIG-001", values: { quantity: 4 }, expected_current: { quantity: "" } }, serhiy));
assert.equal(call(api, "3dp_batch_draft", serhiy, { sku: "FIG-001" }).values.quantity, 4);
assert.equal(call(api, "3dp_batch_draft", owner, { sku: "FIG-001" }).values.quantity, 2);
assert.equal(api.workbook.getSheetByName("_Чернетки_партій").getRange("A3").getDisplayValue(), "serhiy::FIG-001");
const v23AfterSerhiyDraft = loadApi(wp1BaselineSource, api.workbook);
assert.equal(call(v23AfterSerhiyDraft, "3dp_batch_draft", owner, { sku: "FIG-001" }).values.quantity, 2);

const migration = plain(api.context.setup3dpWp1bSchema());
assert.equal(migration.already_applied, false);
assert.deepEqual(api.workbook.getSheetByName("Виплати").getRange("G1:H1").getValues()[0], [
  "Згода Сергія із сумою (Київ, роль)", "Кошти надійшли Сергію (Київ, роль)",
]);
assert.deepEqual(api.workbook.getSheetByName("_Коригування_наявності").getRange("A1:E2").getValues(), [[
  "SKU", "Зміна наявності, шт", "Причина", "Дата коригування (Київ)", "Роль",
], ["FIG-001", 1, "owner only", "2026-08-16", "owner"]]);
assert.equal(api.workbook.getSheetByName("_Коригування_наявності").isSheetHidden(), true);
assert.equal(plain(api.context.setup3dpWp1bSchema()).already_applied, true);

const manualColumns = JSON.parse(vm.runInContext("JSON.stringify(SERHIY_MANUAL_COLUMNS_3DP)", api.context));
const projections = JSON.parse(vm.runInContext("JSON.stringify(SERHIY_READ_PROJECTION_3DP)", api.context));
Object.entries(manualColumns).forEach(([sheetName, columns]) => {
  if (sheetName === "Налаштування") return;
  const headers = api.workbook.getSheetByName(sheetName).getRange(1, 1, 1, api.workbook.getSheetByName(sheetName).getLastColumn()).getDisplayValues()[0];
  columns.forEach((column) => {
    const header = headers[columnNumber(column) - 1];
    assert.ok(projections[sheetName].baseline.includes(header), `${sheetName}!${column} (${header}) must be in the Serhiy baseline projection`);
  });
});
assert.ok(projections["Виплати"].baseline.includes("Згода Сергія із сумою (Київ, роль)"));
assert.ok(projections["Виплати"].baseline.includes("Кошти надійшли Сергію (Київ, роль)"));
const serhiyPayout = call(api, "3dp_payouts", serhiy).rows[0];
assert.equal(serhiyPayout["Згода Сергія із сумою (Київ, роль)"], "");
assert.equal(serhiyPayout["Кошти надійшли Сергію (Київ, роль)"], "");

const availability = api.workbook.getSheetByName("Наявність");
availability.getRange("G2").setFormula("=SUMIF('_Коригування_наявності'!$A:$A;A2;'_Коригування_наявності'!$B:$B)");
const stockCorrection = plain(api.context.adjustStockAction3dp_(api.workbook, {
  sku: "FIG-001", expected_current: 0, new_value: 5, reason: "counted stock",
}, serhiy));
assert.deepEqual(stockCorrection, {
  action: "3dp_adjust_stock", sku: "FIG-001", row: 2, old_value: 0, new_value: 5, delta: 5,
  ledger_row: 3, warning: null,
});
assert.deepEqual(api.workbook.getSheetByName("_Коригування_наявності").getRange("A3:E3").getValues()[0], [
  "FIG-001", 5, "counted stock", "2026-08-16 12:00:00", "serhiy",
]);
assert.equal(call(api, "3dp_stock_adjustments", serhiy, { sku: "FIG-001" }).rows[0]["Роль"], "serhiy");
codeOf(() => api.context.adjustStockAction3dp_(api.workbook, {
  sku: "FIG-001", expected_current: 1, new_value: 5, reason: "stale count",
}, serhiy), "STALE_WRITE");

codeOf(() => api.context.createPayoutAction3dp_(api.workbook, { period: "2026-09" }, serhiy), "FORBIDDEN");
codeOf(() => api.context.markPayoutPaidAction3dp_(api.workbook, {
  row_number: 2, expected_period: "2026-08", paid_date: "2026-08-20",
}, serhiy), "FORBIDDEN");
const payoutSheet = api.workbook.getSheetByName("Виплати");
payoutSheet.getRange(3, 1, 1, 8).setValues([["2026-09", 100, "", "", "", "", "", ""]]);
codeOf(() => api.context.acknowledgePayoutAction3dp_(api.workbook, {
  row_number: 3, expected_period: "2026-09", acknowledgement: "amount_agreed",
}, serhiy, false), "PAYOUT_NOT_PUBLISHED");
const amountAcknowledgement = plain(api.context.acknowledgePayoutAction3dp_(api.workbook, {
  row_number: 2, expected_period: "2026-08", acknowledgement: "amount_agreed",
}, serhiy, false)).new_value;
assert.match(amountAcknowledgement, /^2026-08-16 12:00:00 · serhiy$/);
codeOf(() => api.context.acknowledgePayoutAction3dp_(api.workbook, {
  row_number: 2, expected_period: "2026-08", acknowledgement: "amount_agreed",
}, serhiy, false), "ACKNOWLEDGEMENT_ALREADY_SET");
codeOf(() => api.context.acknowledgePayoutAction3dp_(api.workbook, {
  row_number: 2, expected_period: "2026-08", acknowledgement: "money_received",
}, serhiy, false), "PAYOUT_NOT_PAID");
plain(api.context.markPayoutPaidAction3dp_(api.workbook, {
  row_number: 2, expected_period: "2026-08", paid_date: "2026-08-20", note: "paid",
}, owner));
const moneyAcknowledgement = plain(api.context.acknowledgePayoutAction3dp_(api.workbook, {
  row_number: 2, expected_period: "2026-08", acknowledgement: "money_received",
}, serhiy, false)).new_value;
assert.match(moneyAcknowledgement, /^2026-08-16 12:00:00 · serhiy$/);
codeOf(() => api.context.acknowledgePayoutAction3dp_(api.workbook, {
  row_number: 2, expected_period: "2026-08", acknowledgement: "amount_agreed",
}, owner, false), "FORBIDDEN");
plain(api.context.acknowledgePayoutAction3dp_(api.workbook, {
  row_number: 2, expected_period: "2026-08", acknowledgement: "amount_agreed",
  expected_current: amountAcknowledgement, reason: "explicit correction",
}, serhiy, true));
const payoutJournal = api.workbook.getSheetByName("_Журнал_підтверджень_виплат_3DP");
assert.equal(payoutJournal.isSheetHidden(), true);
assert.deepEqual(payoutJournal.getRange("A2:G4").getValues(), [
  ["2026-08-16 12:00:00", "serhiy", "2026-08", "amount agreement", "∅", amountAcknowledgement, ""],
  ["2026-08-16 12:00:00", "serhiy", "2026-08", "money received", "∅", moneyAcknowledgement, ""],
  ["2026-08-16 12:00:00", "serhiy", "2026-08", "amount agreement", amountAcknowledgement, amountAcknowledgement, "explicit correction"],
]);

const v23AfterWp1b = loadApi(wp1BaselineSource, api.workbook);
ownerReadCases.filter(([action]) => !["3dp_payouts", "3dp_stock_adjustments"].includes(action)).forEach(([action, params]) => {
  assert.deepEqual(call(api, action, owner, params), call(v23AfterWp1b, action, owner, params), `V23 owner response after WP1b: ${action}`);
});

const restrictedCode = code.replace("const SERHIY_FULL_ECONOMICS_VISIBLE_3DP = true;", "const SERHIY_FULL_ECONOMICS_VISIBLE_3DP = false;");
const restricted = loadApi(restrictedCode, api.workbook);
const restrictedSales = call(restricted, "3dp_sales", serhiy).rows[0];
assert.equal(Object.hasOwn(restrictedSales, "Витрати BoosterShop за од., грн"), false);
assert.equal(Object.hasOwn(restrictedSales, "Вартість фурнітури за од., грн (заморожена)"), false);
assert.equal(Object.hasOwn(restrictedSales, "Фурнітура власника за од., грн (заморожена)"), false);
assert.equal(Object.hasOwn(restrictedSales, "№ замовлення"), false);
assert.equal(Object.hasOwn(restrictedSales, "Примітки"), false);
assert.equal(Object.hasOwn(restrictedSales, "CRM row number"), false);
assert.equal(call(restricted, "3dp_stock_adjustments", serhiy, { sku: "FIG-001" }).rows[0]["Роль"], "serhiy");
assert.equal(call(restricted, "3dp_get_row", serhiy, { sheet: "Номенклатура", sku: "FIG-001" }).row["Ціна під викуп, грн"], 130);
assert.equal(call(restricted, "3dp_get_row", serhiy, { sheet: "Номенклатура", sku: "FIG-001" }).row["Посилання на модель"], "https://models.example/FIG-001");
assert.equal(call(restricted, "3dp_payouts", serhiy).rows[0]["Кошти надійшли Сергію (Київ, роль)"], moneyAcknowledgement);

// WP1c: a temporary DRAFT key is never a canonical article and every field the
// Serhiy-only creator can write is readable through the fail-closed baseline.
const wp1cManualColumns = JSON.parse(vm.runInContext("JSON.stringify(NOMENCLATURE_DRAFT_CREATE_COLUMNS_3DP)", api.context));
const wp1cNomenclatureHeaders = api.workbook.getSheetByName("Номенклатура").getRange(1, 1, 1, 19).getDisplayValues()[0];
wp1cManualColumns.forEach((column) => {
  const header = wp1cNomenclatureHeaders[columnNumber(column) - 1];
  assert.ok(projections["Номенклатура"].baseline.includes(header), `draft creator write ${column} (${header}) must be in the Serhiy baseline projection`);
});

const draftValues = {
  B: "Новий брелок", C: "Pokemon", D: "Брелок", E: "середній", F: "підготовка",
  G: 1.25, H: 12, I: 1000, J: 600, L: "2026-08-22", M: "чернетка", N: 0,
  Q: 350, R: 120, S: "https://models.example/draft",
};
const draft = plain(api.context.createNomenclatureDraftAction3dp_(api.workbook, { values: draftValues }, serhiy));
assert.equal(draft.action, "3dp_nomenclature_draft_create");
assert.match(draft.sku, /^DRAFT-[A-F0-9]{32}$/);
assert.equal(draft.status, "Чернетка");
assert.deepEqual(draft.sku_suggestion, { prefix: "BR", category_digits: "100", category_label: "Звичайний брелок-підвіска" });
assert.equal(Object.hasOwn(draft.sku_suggestion, "mnemonic"), false);
assert.equal(api.workbook.getSheetByName("Номенклатура").getRange(draft.row, 15).getDisplayValue(), "Чернетка");
assert.equal(/^(?:BR|FIG|ACC-3D)-[A-Z0-9][A-Z0-9-]*$/.test(draft.sku), false, "temporary draft key must never match the CRM 3D trigger");

assert.equal(call(api, "3dp_skus", owner).rows.some((row) => row.SKU === draft.sku), false, "default catalogue excludes drafts");
assert.equal(call(api, "3dp_skus", owner, { include_archived: "true" }).rows.some((row) => row.SKU === draft.sku), true, "explicit non-active catalogue includes drafts");
assert.equal(call(api, "3dp_overview", owner).summary.sku_count, 2, "overview counts active SKU only");
codeOf(() => call(api, "3dp_get_row", serhiy, { sheet: "Номенклатура", sku: draft.sku }), "ROW_FILTERED");
assert.equal(call(api, "3dp_get_row", serhiy, { sheet: "Номенклатура", sku: draft.sku, include_drafts: "true" }).row.SKU, draft.sku);
assert.equal(plain(api.context.nomenclatureStatusAtRow3dp_(api.workbook.getSheetByName("Номенклатура"), draft.row)), "Чернетка");
assert.doesNotThrow(() => api.context.validateNomenclatureArchiveState3dp_(api.workbook));

// All four pre-existing archive guards now reject a draft before any downstream write.
codeOf(() => api.context.appendOrderGiftsAction3dp_(api.workbook, {
  request_id: "draft-guard-001", order: "ORDER-DRAFT", date: "2026-08-22", items: [{ sku: draft.sku, qty: 1 }],
}, owner), "SKU_NOT_ACTIVE");
codeOf(() => api.context.manufactureBatchAction3dp_(api.workbook, { sku: draft.sku }, serhiy), "SKU_NOT_ACTIVE");
codeOf(() => api.context.saveBatchDraftAction3dp_(api.workbook, { sku: draft.sku }, serhiy), "SKU_NOT_ACTIVE");
codeOf(() => api.context.adjustStockAction3dp_(api.workbook, { sku: draft.sku }, serhiy), "SKU_NOT_ACTIVE");
codeOf(() => api.context.setNomenclatureArchiveAction3dp_(api.workbook, { row: draft.row }, owner, true), "DRAFT_ASSIGNMENT_REQUIRED");
codeOf(() => api.context.writeAction3dp_(api.workbook, {
  sheet: "Номенклатура", column: "A", sku_or_row: "FIG-001", value: "FIG-EDIT-100",
}, owner), "SPECIALIZED_ACTION_REQUIRED");
codeOf(() => api.context.writeAction3dp_(api.workbook, {
  sheet: "Номенклатура", column: "O", sku_or_row: draft.sku, value: "Активний",
}, serhiy), "COLUMN_NOT_ALLOWED");

const wp1cFormulaMigration = plain(api.context.setup3dpWp1cStatusSchema());
assert.equal(wp1cFormulaMigration.already_applied, false);
assert.match(api.workbook.getSheetByName("Наявність").getRange("C2").getFormula(), /Номенклатура/);
assert.match(api.workbook.getSheetByName("Наявність").getRange("C2").getFormula(), /Активний/);
assert.match(api.workbook.getSheetByName("Наявність").getRange("D2").getFormula(), /Номенклатура/);
assert.equal(plain(api.context.setup3dpWp1cStatusSchema()).already_applied, true);

codeOf(() => api.context.assignNomenclatureSkuAction3dp_(api.workbook, {
  draft_sku: draft.sku, sku: "BR-NEWD-100",
}, serhiy), "FORBIDDEN");
codeOf(() => api.context.assignNomenclatureSkuAction3dp_(api.workbook, {
  draft_sku: draft.sku, sku: "br-newd-100",
}, owner), "INVALID_SKU");
codeOf(() => api.context.assignNomenclatureSkuAction3dp_(api.workbook, {
  draft_sku: draft.sku, sku: "FIG-001",
}, owner), "INVALID_SKU");

// A row key with either print or sale history is a migration, not a safe rename.
api.workbook.getSheetByName("Друк-лог").setValueAt(3, 2, draft.sku);
codeOf(() => api.context.assignNomenclatureSkuAction3dp_(api.workbook, {
  draft_sku: draft.sku, sku: "BR-NEWD-100",
}, owner), "SKU_HISTORY_EXISTS");
api.workbook.getSheetByName("Друк-лог").setValueAt(3, 2, "");
api.workbook.getSheetByName("Продажі").setValueAt(3, 2, draft.sku);
codeOf(() => api.context.assignNomenclatureSkuAction3dp_(api.workbook, {
  draft_sku: draft.sku, sku: "BR-NEWD-100",
}, owner), "SKU_HISTORY_EXISTS");
api.workbook.getSheetByName("Продажі").setValueAt(3, 2, "");

const assigned = plain(api.context.assignNomenclatureSkuAction3dp_(api.workbook, {
  draft_sku: draft.sku, expected_draft_sku: draft.sku, sku: "BR-NEWD-100",
}, owner));
assert.deepEqual(assigned, {
  action: "3dp_nomenclature_assign_sku", row: draft.row, old_sku: draft.sku, sku: "BR-NEWD-100", status: "Активний",
  sku_suggestion: { prefix: "BR", category_digits: "100", category_label: "Звичайний брелок-підвіска" },
});
assert.equal(api.workbook.getSheetByName("Номенклатура").getRange(draft.row, 1).getDisplayValue(), "BR-NEWD-100");
const analytics = api.workbook.getSheetByName("Аналітика");
const analyticsRow = Array.from({ length: 14 }, (_, index) => index + 4).find((row) =>
  analytics.getRange(row, 1).getFormula() === `='Номенклатура'!A${draft.row}`);
assert.ok(analyticsRow, "canonical assignment must create an Analytics formula row");
assert.equal(analytics.getRange(analyticsRow, 6).getValue(), 0.5, "a newly active SKU receives the approved 50% default");
assert.equal(call(api, "3dp_get_row", owner, { sheet: "Номенклатура", sku: "BR-NEWD-100" }).row["% прибутку Сергію"], 0.5,
  "the assigned SKU is readable without a missing Analytics profit-share error");
analytics.getRange(4, 6).setValue(0.4);
plain(api.context.syncActiveNomenclatureAnalytics3dp_(api.workbook));
assert.equal(analytics.getRange(4, 6).getValue(), 0.4, "an explicit owner profit share survives a later sync");
const duplicateDraft = plain(api.context.createNomenclatureDraftAction3dp_(api.workbook, { values: draftValues }, serhiy));
const repair = plain(api.context.repair3dpActiveNomenclatureAnalytics());
assert.equal(repair.already_applied, true, "the public repair is idempotent after assignment synchronization");
assert.equal(Array.from({ length: 14 }, (_, index) => index + 4).some((row) =>
  analytics.getRange(row, 1).getFormula() === `='Номенклатура'!A${duplicateDraft.row}`), false,
  "a remaining draft must not consume an Analytics calculator row");
const blockedDraft = plain(api.context.createNomenclatureDraftAction3dp_(api.workbook, { values: draftValues }, serhiy));
analytics.getRange(3, 1).setValue("unexpected analytics header");
codeOf(() => api.context.assignNomenclatureSkuAction3dp_(api.workbook, {
  draft_sku: blockedDraft.sku, sku: "BR-BLOCK-102",
}, owner), "ANALYTICS_SCHEMA_NOT_READY");
assert.equal(api.workbook.getSheetByName("Номенклатура").getRange(blockedDraft.row, 1).getDisplayValue(), blockedDraft.sku,
  "an analytics schema failure rolls the canonical SKU assignment back to the draft key");
assert.equal(api.workbook.getSheetByName("Номенклатура").getRange(blockedDraft.row, 15).getDisplayValue(), "Чернетка",
  "an analytics schema failure does not activate the draft");
analytics.getRange(3, 1).setValue("SKU");
codeOf(() => api.context.assignNomenclatureSkuAction3dp_(api.workbook, {
  draft_sku: duplicateDraft.sku, sku: "BR-NEWD-100",
}, owner), "SKU_DUPLICATE");

console.log(JSON.stringify({ ok: true, owner_paths_preserved: 11, v23_owner_responses_compared: ownerReadCases.length, serhiy_projection_checks: 54, settings_journal_checks: 12, wp1b_write_checks: 23, payout_acknowledgement_checks: 17, wp1c_draft_status_checks: 31, analytics_provisioning_checks: 11, full_economics_checks: 10 }));
