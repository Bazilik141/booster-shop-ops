import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, "../Code.gs"), "utf8");

function columnToNumber(column) {
  return [...column.toUpperCase()].reduce((value, char) => value * 26 + char.charCodeAt(0) - 64, 0);
}

function parseA1(a1) {
  const match = String(a1).toUpperCase().match(/^([A-Z]+)(\d+)(?::([A-Z]+)(\d+))?$/);
  if (!match) throw new Error(`Unsupported mock range: ${a1}`);
  return {
    row: Number(match[2]),
    column: columnToNumber(match[1]),
    rows: Number(match[4] || match[2]) - Number(match[2]) + 1,
    columns: columnToNumber(match[3] || match[1]) - columnToNumber(match[1]) + 1,
  };
}

class MockRange {
  constructor(sheet, row, column, rows = 1, columns = 1) {
    this.sheet = sheet;
    this.row = row;
    this.column = column;
    this.rows = rows;
    this.columns = columns;
  }

  cells() {
    const result = [];
    for (let rowOffset = 0; rowOffset < this.rows; rowOffset += 1) {
      const row = [];
      for (let colOffset = 0; colOffset < this.columns; colOffset += 1) {
        row.push(this.sheet.cell(this.row + rowOffset, this.column + colOffset));
      }
      result.push(row);
    }
    return result;
  }

  getValue() { return this.cells()[0][0].value ?? ""; }
  getDisplayValue() { return display(this.getValue()); }
  getFormula() { return this.cells()[0][0].formula || ""; }
  getValues() { return this.cells().map((row) => row.map((cell) => cell.value ?? "")); }
  getDisplayValues() { return this.getValues().map((row) => row.map(display)); }
  getFormulas() { return this.cells().map((row) => row.map((cell) => cell.formula || "")); }
  getFontColors() { return this.cells().map((row) => row.map((cell) => cell.fontColor || "#000000")); }

  setValue(value) {
    const cell = this.cells()[0][0];
    cell.value = value;
    cell.formula = "";
    return this;
  }

  setValues(values) {
    assert.equal(values.length, this.rows);
    values.forEach((row, rowOffset) => {
      assert.equal(row.length, this.columns);
      row.forEach((value, colOffset) => {
        const cell = this.sheet.cell(this.row + rowOffset, this.column + colOffset);
        cell.value = value;
        cell.formula = "";
      });
    });
    return this;
  }

  setFormula(formula) {
    this.cells()[0][0].formula = formula;
    return this;
  }

  clearContent() {
    this.cells().flat().forEach((cell) => {
      cell.value = "";
      cell.formula = "";
    });
    return this;
  }

  setFontColor(color) {
    this.cells().flat().forEach((cell) => { cell.fontColor = color; });
    return this;
  }

  copyTo(target, pasteType) {
    const source = this.cells()[0][0];
    const destination = target.cells()[0][0];
    if (pasteType === "PASTE_FORMULA") destination.formula = source.formula || "";
    if (pasteType === "PASTE_FORMAT") destination.fontColor = source.fontColor || "#000000";
    return this;
  }
}

class MockSheet {
  constructor(name, rows = []) {
    this.name = name;
    this.grid = [];
    this.hidden = false;
    this.maxRows = 1000;
    this.maxColumns = 26;
    rows.forEach((row, rowIndex) => row.forEach((entry, columnIndex) => {
      const cell = this.cell(rowIndex + 1, columnIndex + 1);
      if (entry && typeof entry === "object" && !Array.isArray(entry) && ("value" in entry || "formula" in entry)) {
        Object.assign(cell, entry);
      } else {
        cell.value = entry ?? "";
      }
    }));
  }

  cell(row, column) {
    while (this.grid.length < row) this.grid.push([]);
    while (this.grid[row - 1].length < column) this.grid[row - 1].push({ value: "", formula: "", fontColor: "#000000" });
    return this.grid[row - 1][column - 1];
  }

  getName() { return this.name; }
  getMaxRows() { return this.maxRows; }
  getMaxColumns() { return this.maxColumns; }
  getLastRow() {
    for (let row = this.grid.length; row >= 1; row -= 1) {
      if (this.grid[row - 1].some((cell) => cell && (cell.value !== "" || cell.formula))) return row;
    }
    return 0;
  }
  getLastColumn() {
    let last = 0;
    this.grid.forEach((row) => row.forEach((cell, index) => {
      if (cell && (cell.value !== "" || cell.formula)) last = Math.max(last, index + 1);
    }));
    return last;
  }
  getRange(...args) {
    if (args.length === 1 && typeof args[0] === "string") {
      const parsed = parseA1(args[0]);
      return new MockRange(this, parsed.row, parsed.column, parsed.rows, parsed.columns);
    }
    return new MockRange(this, args[0], args[1], args[2] || 1, args[3] || 1);
  }
  appendRow(values) {
    this.getRange(Math.max(this.getLastRow() + 1, 1), 1, 1, values.length).setValues([values]);
  }
  setFrozenRows() { return this; }
  isSheetHidden() { return this.hidden; }
  hideSheet() { this.hidden = true; return this; }
}

class MockSpreadsheet {
  constructor(id, sheets) {
    this.id = id;
    this.sheets = Object.fromEntries(sheets.map((sheet) => [sheet.name, sheet]));
  }
  getId() { return this.id; }
  getSheetByName(name) { return this.sheets[name] || null; }
  insertSheet(name) {
    if (this.sheets[name]) throw new Error(`Sheet already exists: ${name}`);
    const sheet = new MockSheet(name);
    this.sheets[name] = sheet;
    return sheet;
  }
}

function display(value) {
  if (value instanceof Date) return value.toISOString().slice(0, 10);
  if (value === null || value === undefined) return "";
  return String(value);
}

const formula = (value, expression) => ({ value, formula: expression });
const nomenclatureHeaders = [
  "SKU", "Назва виробу", "Франшиза", "Тип", "Трек", "Статус", "Час друку, год", "Матеріал (пластик)",
  "Витрата матеріалу, г", "Ціна матеріалу, грн/кг", "Собівартість Сергія (матеріал+фурнітура), грн",
  "Дата оновлення", "Примітки", "Фурнітура (ланцюжок/карабін), грн/шт",
];
const printLogHeaders = [
  "Дата", "SKU", "Надруковано, шт", "Час друку факт, год", "Брак/відходи, шт",
  "Витрачено матеріалу, г (факт)", "Собівартість партії, грн", "Хто друкував", "Примітки",
];
const salesHeaders = [
  "Дата", "SKU", "Назва", "Кількість", "Фактична ціна за од., грн (після знижки)",
  "Собівартість Сергія за од., грн", "Витрати BoosterShop за од., грн", "% прибутку Сергію",
  "Маржинальний прибуток за од., грн", "Статус", "Нараховано Сергію, грн", "Дохід Booster Shop, грн",
  "Канал", "№ замовлення", "Примітки", "Тип знижки", "Параметр знижки",
  "Погоджено з Сергієм (Так/Ні)", "Період (авто, РРРР-ММ)",
];

const spreadsheet = new MockSpreadsheet("1yp15H3YJGkqI4Rx89G4QZHkD9m67gnWh58TsTTi-jjo", [
  new MockSheet("Легенда", Array.from({ length: 33 }, (_, index) => index === 31 ? ["Відомі відкриті питання", ""] : index === 32 ? ["Питання", "Відповідь"] : [])),
  new MockSheet("Номенклатура", [
    nomenclatureHeaders,
    ["ПРИКЛАД-001", "Приклад", "—", "Фігурка", "Продаж на сайті", "ПРИКЛАД — видалити", 4, "PLA", 120, 700, formula(84, "=I2*J2/1000+N2"), "2026-07-28", "приклад", 0],
    ["BR-CHARM-001", "Брелок Чармандер", "Pokemon", "Брелок", "Продаж на сайті", "Активний", 0.1032, "PLA", 2.45, 1549.98, formula(6.797451, "=I3*J3/1000+N3"), "2026-07-31", "реальний", 3],
  ]),
  new MockSheet("Друк-лог", [
    printLogHeaders,
    ["2026-07-20", "ПРИКЛАД-001", 5, 22, 1, 600, formula(420, "=C2*K2"), "Друг", "приклад — видалити"],
    ["2026-08-01", "BR-CHARM-001", 36, 3.72, 0, 88.24, formula(244.7, "=C3*K3"), "Сергій", "реальний друк"],
    ["", "", "", "", "", "", formula("", "=IF(B4=\"\",\"\",C4)"), "", ""],
  ]),
  new MockSheet("Продажі", [
    salesHeaders,
    ["2026-07-25", "ПРИКЛАД-001", formula("Приклад", "=X"), 1, 350, formula(84, "=X"), 20, 0.5, formula(246, "=X"), formula("ОК", "=X"), formula(207, "=X"), formula(123, "=X"), "Сайт", "—", "приклад — видалити", "", "", "", formula("2026-07", "=X")],
    ["2026-08-01", "BR-CHARM-001", formula("Брелок Чармандер", "=X"), 2, 62, formula(6.8, "=X"), 0, 0.5, formula(55.2, "=X"), formula("ОК", "=X"), formula(68, "=X"), formula(55.2, "=X"), "Сайт", "1001", "", "", "", "Так", formula("2026-08", "=X")],
  ]),
  new MockSheet("Виплати", [
    ["Період (РРРР-ММ)", "Нараховано Сергію за період, грн", "Термін перевірки Сергієм", "Дата фактичної виплати", "Статус", "Примітки"],
    ["2026-07", formula(207, "=X"), formula("2026-08-02", "=X"), "2026-08-02", "ПРИКЛАД — видалити", "приклад"],
    ["2026-08", formula(68, "=X"), formula("2026-09-02", "=X"), "", "Очікує", ""],
  ]),
  new MockSheet("Маркетингові_плюшки", [
    ["Дата", "SKU", "Закуплено в Друга, шт", "Ціна закупівлі за од., грн", "Сума закупівлі, грн", "Видано як бонус, шт", "До замовлення №", "Примітки"],
    ["Поріг замовлення для безкоштовної плюшки:", "", "", "", "", 2000, "", ""],
    ["2026-07-26", "ПРИКЛАД-001", 3, 60, formula(180, "=C3*D3"), 1, "—", "приклад — видалити"],
    ["2026-08-01", "BR-CHARM-001", 36, 7, formula(252, "=C4*D4"), 0, "", "реальна партія"],
  ]),
  new MockSheet("Наявність", [
    ["SKU", "Назва", "Надруковано всього, шт", "Брак всього, шт", "Продано на сайті, шт", "Видано як плюшка, шт", "Наявно зараз, шт"],
    [formula("ПРИКЛАД-001", "=A2"), formula("Приклад", "=B2"), formula(5, "=SUMIF(X)"), formula(1, "=SUMIF(X)"), formula(1, "=SUMIF(X)"), formula(1, "=SUMIF(X)"), formula(2, "=X")],
    [formula("BR-CHARM-001", "=A3"), formula("Брелок Чармандер", "=B3"), formula(36, "=SUMIF(X)"), formula(0, "=SUMIF(X)"), formula(2, "=SUMIF(X)"), formula(0, "=SUMIF(X)"), formula(34, "=X")],
  ]),
  new MockSheet("Аналітика", [["SKU", "Назва", "Собівартість Сергія, грн"]]),
]);

const properties = {
  BOOSTER_3DP_TOKEN: "owner-test-token",
  BOOSTER_3DP_SERHIY_TOKEN: "serhiy-test-token",
};
const context = {
  console,
  Date,
  Error,
  JSON,
  Math,
  Number,
  Object,
  String,
  Array,
  RegExp,
  isFinite,
  SpreadsheetApp: {
    getActiveSpreadsheet: () => spreadsheet,
    CopyPasteType: { PASTE_FORMULA: "PASTE_FORMULA", PASTE_FORMAT: "PASTE_FORMAT" },
  },
  PropertiesService: { getScriptProperties: () => ({ getProperty: (key) => properties[key] || null }) },
  Utilities: {
    formatDate: (date, _timezone, pattern) => {
      const iso = new Date(date).toISOString();
      if (pattern === "yyyy-MM") return iso.slice(0, 7);
      if (pattern === "yyyy-MM-dd HH:mm:ss") return `${iso.slice(0, 10)} ${iso.slice(11, 19)}`;
      return iso;
    },
  },
  LockService: { getScriptLock: () => ({ tryLock: () => true, releaseLock: () => {} }) },
  ContentService: {
    MimeType: { JSON: "application/json" },
    createTextOutput: (text) => ({ text, setMimeType() { return this; } }),
  },
};
vm.createContext(context);
vm.runInContext(code, context, { filename: "Code.gs" });

const owner = { role: "owner", identity: "dashboard" };
const serhiy = { role: "serhiy", identity: "serhiy" };
function expectCode(expectedCode, callback) {
  assert.throws(callback, (error) => error && error.code === expectedCode);
}

assert.equal(JSON.stringify(context.authenticate3dp_("owner-test-token")), JSON.stringify(owner));
assert.equal(JSON.stringify(context.authenticate3dp_("serhiy-test-token")), JSON.stringify(serhiy));
expectCode("UNAUTHORIZED", () => context.authenticate3dp_("wrong"));

const firstSetup = context.setup3dpApi();
assert.equal(firstSetup.ok, true);
assert.equal(firstSetup.already_applied, false);
assert.equal(spreadsheet.getSheetByName("Номенклатура").getRange("O1").getValue(), "Комбінована амортизація, грн/год");
assert.match(spreadsheet.getSheetByName("Номенклатура").getRange("K3").getFormula(), /\+O3\*G3$/);
assert.equal(spreadsheet.getSheetByName("Друк-лог").getRange("J3").getValue(), "Активний");
const archiveAwareFormula = spreadsheet.getSheetByName("Наявність").getRange("C3").getFormula();
assert.match(archiveAwareFormula, /<>Архів/);
assert.match(archiveAwareFormula, /;/);
assert.doesNotMatch(archiveAwareFormula, /,/);
assert.equal(spreadsheet.getSheetByName("_Аудит_API").isSheetHidden(), true);
const secondSetup = context.setup3dpApi();
assert.equal(secondSetup.already_applied, true);

spreadsheet.getSheetByName("Наявність").getRange("C3").setFormula('=IF(A3="","",SUMIFS(X,Y))');
const repairResult = context.repair3dpAvailabilityFormulas();
assert.equal(repairResult.already_applied, false);
assert.match(spreadsheet.getSheetByName("Наявність").getRange("C3").getFormula(), /;/);
assert.equal(context.repair3dpAvailabilityFormulas().already_applied, true);

assert.equal(context.overviewAction3dp_(spreadsheet).summary.sku_count, 1);
assert.equal(context.overviewAction3dp_(spreadsheet).summary.available, 34);
assert.equal(context.skusAction3dp_(spreadsheet).count, 1);
assert.equal(context.tableAction3dp_(spreadsheet, "Продажі", { requireHeader: "SKU" }).count, 1);
assert.equal(context.tableAction3dp_(spreadsheet, "Маркетингові_плюшки", { requireHeader: "SKU" }).count, 1);
assert.equal(context.tableAction3dp_(spreadsheet, "Виплати", { requireHeader: "Період (РРРР-ММ)" }).count, 1);
assert.equal(context.getRowAction3dp_(spreadsheet, { sheet: "Номенклатура", sku: "BR-CHARM-001" }).row.SKU, "BR-CHARM-001");
assert.equal(JSON.stringify(context.getRangeAction3dp_(spreadsheet, { sheet: "Легенда", range: "A32:B33" }, serhiy).values[1]), JSON.stringify(["Питання", "Відповідь"]));
expectCode("RANGE_NOT_ALLOWED", () => context.getRangeAction3dp_(spreadsheet, { sheet: "Легенда", range: "A1:B2" }, owner));
expectCode("RANGE_NOT_ALLOWED", () => context.getRangeAction3dp_(spreadsheet, { sheet: "Аналітика", range: "A18:N18" }, owner));
expectCode("SHEET_NOT_ALLOWED", () => context.getRangeAction3dp_(spreadsheet, { sheet: "_Аудит_API", range: "A1:B2" }, owner));

expectCode("FORMULA_CELL", () => context.writeAction3dp_(spreadsheet, {
  sheet: "Номенклатура", sku_or_row: "BR-CHARM-001", column: "K", value: 10, expected_current: 6.797451,
}, owner));
expectCode("COLUMN_NOT_ALLOWED", () => context.writeAction3dp_(spreadsheet, {
  sheet: "Номенклатура", sku_or_row: "BR-CHARM-001", column: "A", value: "RENAMED", expected_current: "BR-CHARM-001",
}, serhiy));
expectCode("STALE_WRITE", () => context.writeAction3dp_(spreadsheet, {
  sheet: "Номенклатура", sku_or_row: "BR-CHARM-001", column: "J", value: 1600, expected_current: 999,
}, owner));
expectCode("FORMULA_VALUE_NOT_ALLOWED", () => context.writeAction3dp_(spreadsheet, {
  sheet: "Номенклатура", sku_or_row: "BR-CHARM-001", column: "J", value: "=1+1", expected_current: 1549.98,
}, owner));

const writeResult = context.writeAction3dp_(spreadsheet, {
  sheet: "Номенклатура", sku_or_row: "BR-CHARM-001", column: "J", value: 1600, expected_current: 1549.98,
}, owner);
assert.equal(writeResult.new_value, 1600);
assert.equal(spreadsheet.getSheetByName("Номенклатура").getRange("J3").getValue(), 1600);

const editResult = context.updatePrintLogAction3dp_(spreadsheet, {
  row: 3,
  changes: { C: 37, I: "уточнено" },
  expected_current: { C: 36, I: "реальний друк" },
}, serhiy);
assert.equal(editResult.changes, 2);
assert.match(spreadsheet.getSheetByName("Друк-лог").getRange("K3").getValue(), /Надруковано, шт: 36 → 37/);

const archiveResult = context.setPrintLogArchiveAction3dp_(spreadsheet, { row: 3, expected_status: "Активний", reason: "дублікат" }, serhiy, true);
assert.equal(archiveResult.status, "Архів");
assert.equal(context.printLogAction3dp_(spreadsheet, {}).count, 0);
assert.equal(context.printLogAction3dp_(spreadsheet, { include_archived: "true" }).count, 1);
const restoreResult = context.setPrintLogArchiveAction3dp_(spreadsheet, { row: 3, expected_status: "Архів" }, serhiy, false);
assert.equal(restoreResult.status, "Активний");

const appendResult = context.appendRowAction3dp_(spreadsheet, {
  sheet: "Друк-лог",
  values: { A: "2026-08-02", B: "BR-CHARM-001", C: 10, D: 1.1, E: 1, F: 25, H: "Сергій", I: "друга партія" },
}, serhiy);
assert.equal(appendResult.row, 4);
assert.equal(spreadsheet.getSheetByName("Друк-лог").getRange("J4").getValue(), "Активний");
assert.match(spreadsheet.getSheetByName("Друк-лог").getRange("K4").getValue(), /Створено новий запис/);

const audit = spreadsheet.getSheetByName("_Аудит_API");
assert.ok(audit.getLastRow() >= 6);
assert.ok(audit.getRange(2, 2, audit.getLastRow() - 1, 1).getValues().flat().includes("serhiy"));

console.log(JSON.stringify({
  ok: true,
  setup_idempotent: true,
  read_actions_checked: 7,
  negative_write_tests: ["FORMULA_CELL", "COLUMN_NOT_ALLOWED", "STALE_WRITE"],
  extra_security_tests: ["FORMULA_VALUE_NOT_ALLOWED", "DOCUMENTATION_RANGE_BLOCKS"],
  print_log: ["append", "edit_with_history", "archive", "restore"],
  audit_rows: audit.getLastRow() - 1,
}));
