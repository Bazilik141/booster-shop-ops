import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, "../Code.gs"), "utf8");
const dashboard = fs.readFileSync(path.resolve(here, "../../../dashboard/booster-dashboard.html"), "utf8");

function dashboardFunctionSource(name) {
  const match = new RegExp('(?:async )?function ' + name + '\\(').exec(dashboard);
  if (!match) throw new Error('Missing dashboard function: ' + name);
  const start = match.index;
  const open = dashboard.indexOf('{', dashboard.indexOf(') {', start));
  let depth = 0;
  for (let index = open; index < dashboard.length; index += 1) {
    if (dashboard[index] === '{') depth += 1;
    if (dashboard[index] === '}') {
      depth -= 1;
      if (depth === 0) return dashboard.slice(start, index + 1);
    }
  }
  throw new Error('Unclosed dashboard function: ' + name);
}

const dashboardCallSource = dashboardFunctionSource('call');
async function throughDashboardTransport(response) {
  const context = vm.createContext({
    URLSearchParams, API: 'https://crm.example/exec', TOKEN: 'test-token',
    ensureCrmToken_: () => true,
    fetch: async () => ({ ok: true, status: 200, json: async () => response }),
  });
  vm.runInContext(dashboardCallSource + '\nglobalThis.__testCall = call;', context, { filename: 'dashboard/booster-dashboard.html' });
  return context.__testCall('integrity_check');
}

class MockRange {
  constructor(sheet, row, column, rows = 1, columns = 1) { this.sheet = sheet; this.row = row; this.column = column; this.rows = rows; this.columns = columns; }
  getValues() { return this.matrix((row, column) => this.sheet.value(row, column)); }
  getDisplayValues() { return this.matrix((row, column) => String(this.sheet.value(row, column) ?? "")); }
  getFormulas() { return this.matrix((row, column) => this.sheet.formula(row, column)); }
  getFormula() { return this.sheet.formula(this.row, this.column); }
  matrix(getter) { return Array.from({ length: this.rows }, (_, rowOffset) => Array.from({ length: this.columns }, (_, columnOffset) => getter(this.row + rowOffset, this.column + columnOffset))); }
}

class MockSheet {
  constructor(name, columns) { this.name = name; this.columns = columns; this.values = new Map(); this.formulas = new Map(); }
  key(row, column) { return row + ":" + column; }
  value(row, column) { return this.values.get(this.key(row, column)) ?? ""; }
  formula(row, column) { return this.formulas.get(this.key(row, column)) ?? ""; }
  setValue(row, column, value) { this.values.set(this.key(row, column), value); }
  setFormula(row, column, formula, effective = "") { this.formulas.set(this.key(row, column), formula); this.values.set(this.key(row, column), effective); }
  getRange(row, column, rows = 1, columns = 1) { return new MockRange(this, row, column, rows, columns); }
  getLastRow() { const rows = [...this.values.keys(), ...this.formulas.keys()].map((key) => Number(key.split(":")[0])); return rows.length ? Math.max(...rows) : 0; }
  getLastColumn() { return this.columns; }
  getName() { return this.name; }
}

class MockSpreadsheet {
  constructor(sheets) { this.sheets = new Map(sheets.map((sheet) => [sheet.name, sheet])); }
  getSheetByName(name) { return this.sheets.get(name) || null; }
}

function setHeaders(sheet, row, headers) { headers.forEach((header, index) => sheet.setValue(row, index + 1, header)); }
function setMasterFormulas(sheet, sku, active) {
  const columns = [...Array(18).keys(), 19, 20];
  columns.forEach((column) => sheet.setFormula(2, column + 1, "=ARRAYFORMULA(\"ok\")", ""));
  sheet.setValue(2, 1, sku); sheet.setValue(2, 16, active ? "Так" : "Ні");
}

function makeEnvironment({ productCount = 1, rrcEnabled = true, brokenRrcRows = [], missingMaster = false, masterActive = true, productActive = true, literalProductPrice = false, literalProductShortName = false, productSku = null, duplicateProductSku = false, consumableUsageName = null, literalConsumableUsage = false, remoteMode = 'none' } = {}) {
  const products = new MockSheet("Товари", 15);
  const rrc = new MockSheet("РРЦ", 8);
  const consumables = new MockSheet("Розхідники", 14);
  const master = new MockSheet("Майстер_Товарів", 21);
  setHeaders(products, 2, ["SKU", "Коротка назва", "Повна назва для сайту", "Бренд", "Мова", "Сет", "Формат", "Карт у бустері", "Бустерів у боксі", "Поточна ціна продажу", "Мінімальний залишок", "Активний товар", "Посилання на товар", "Примітка", "Фіксована собівартість"]);
  setHeaders(rrc, 2, ["SKU", "Назва товару", "Бренд", "Формат", "РРЦ, грн", "Дата оновлення", "Примітка", "Динамічна РРЦ"]);
  setHeaders(consumables, 3, ["Тип розхідника", "Категорія", "Собівартість 1 шт", "Початково на складі", "Початково їде", "Надійшло через витрати", "Їде через витрати", "Використано в продажах", "Залишок на складі", "Очікується", "Вартість залишку", "Примітка", "", "Dropdown для форми продажу"]);
  setHeaders(master, 1, ["SKU", "Назва", "Повна назва на сайті", "Бренд", "Мова", "Сет", "Формат", "Карт у бустері", "Бустерів у боксі", "Ціна CRM", "Ціна OpenCart", "Залишок", "Статус складу", "Очікується", "URL товару", "Активний", "Якість даних", "Статус автоматизації", "Нотатки", "Джерело даних", "Оновлено"]);
  [1, 2, 3, 4].forEach((column) => rrc.setFormula(3, column, "=ARRAYFORMULA(\"ok\")", ""));
  [6, 7, 8, 9, 11, 14].forEach((column) => consumables.setFormula(4, column, "=IF(TRUE;0;0)", 0));
  consumables.setValue(4, 1, consumableUsageName || "Пакет");
  if (literalConsumableUsage) {
    consumables.setValue(4, 8, 0);
    consumables.formulas.delete(consumables.key(4, 8));
  }

  for (let index = 0; index < productCount; index += 1) {
    const row = index + 3;
    const sku = duplicateProductSku && index === 1 ? "ACC-3D-TEST-001" : index === 0 && productSku ? productSku : "ACC-3D-TEST-" + String(index + 1).padStart(3, "0");
    products.setValue(row, 1, sku); products.setValue(row, 12, productActive ? "Так" : "Ні");
    if (literalProductShortName) products.setValue(row, 2, "Manual name"); else products.setFormula(row, 2, "=\"Назва\"", "Назва");
    if (literalProductPrice) products.setValue(row, 10, 100); else products.setFormula(row, 10, "=100", 100);
    if (rrcEnabled && (!duplicateProductSku || index === 0)) { rrc.setValue(row, 1, sku); rrc.setValue(row, 2, "Назва"); rrc.setValue(row, 5, 100); rrc.setValue(row, 6, "2026-08-09"); }
    if (index === 0) setMasterFormulas(master, missingMaster ? "ACC-3D-OTHER-999" : sku, masterActive);
    else if (!duplicateProductSku) { master.setValue(row, 1, sku); master.setValue(row, 16, masterActive ? "Так" : "Ні"); }
  }
  brokenRrcRows.forEach((row) => { rrc.setValue(row, 5, 100); rrc.setValue(row, 6, "2026-08-09"); rrc.setValue(row, 7, "manual price before SKU"); });

  const crm = new MockSpreadsheet([products, rrc, consumables]);
  const automation = new MockSpreadsheet([master]);
  const remoteProperties = remoteMode === 'none' ? {} : { BOOSTER_3DP_URL: 'https://3dp.example/exec', BOOSTER_3DP_SYNC_TOKEN: 'test-token' };
  const context = vm.createContext({
    JSON, Math, Number, String, Boolean, Array, Object, RegExp, Date, Error, isFinite,
    Logger: { log() {} }, Session: { getScriptTimeZone: () => "Europe/Kyiv" },
    Utilities: { formatDate: () => "2026-08-09 12:00:00" },
    PropertiesService: { getScriptProperties: () => ({ getProperty: (key) => remoteProperties[key] || "" }) },
    UrlFetchApp: { fetch() { if (remoteMode === 'deferred') throw new Error('REMOTE_DOWN'); return { getResponseCode: () => 200, getContentText: () => JSON.stringify({ ok: true, rows: remoteMode === 'mismatch' ? [{ SKU: 'ACC-3D-TEST-001', 'РРЦ фактична, грн': 90 }] : [] }) }; } },
    SpreadsheetApp: { openById: (id) => id === "1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg" ? crm : automation },
    ContentService: { MimeType: { JSON: "JSON" }, createTextOutput: (text) => ({ text, setMimeType() { return this; } }) },
  });
  vm.runInContext(`${code}\nglobalThis.__test = { apiIntegrityCheck_ };`, context, { filename: "Code.gs" });
  return context.__test.apiIntegrityCheck_;
}

{
  const result = makeEnvironment()();
  assert.equal(result.ok, true);
  assert.equal(result.clean, true);
  assert.deepEqual(JSON.parse(JSON.stringify(result.problems)), []);
  assert.equal(typeof result.elapsed_ms, 'number');
  assert.ok(result.elapsed_ms >= 0);
}

{
  const result = makeEnvironment({ rrcEnabled: false, brokenRrcRows: [71, 72, 73, 74, 75] })();
  const priceProblem = result.problems.find((problem) => problem.code === "price_without_sku");
  assert.equal(priceProblem.rows, "71-75");
  assert.ok(result.problems.some((problem) => problem.code === "active_sku_without_rrp"));
}

{
  const result = makeEnvironment({ productCount: 11, rrcEnabled: false })();
  assert.equal(result.problems.filter((problem) => problem.code === "active_sku_without_rrp").length, 10);
  assert.equal(result.truncated.active_sku_without_rrp, 1);
}

function assertOnlyProblem(result, code) {
  assert.equal(result.problems.length, 1);
  assert.equal(result.problems[0].code, code);
}

assertOnlyProblem(makeEnvironment({ missingMaster: true })(), 'missing_master_row');
assertOnlyProblem(makeEnvironment({ masterActive: false })(), 'master_row_inactive');
assertOnlyProblem(makeEnvironment({ literalProductPrice: true })(), 'formula_column_literal');
assertOnlyProblem(makeEnvironment({ productCount: 2, duplicateProductSku: true })(), 'duplicate_sku');

{
  const manualShortNameSkus = [
    'ACC-001', 'ACC-002', 'ACC-003', 'ACC-004', 'ACC-005', 'ACC-006', 'ACC-007-360', 'ACC-008', 'ACC-009',
    'PKM-JP-MBX-XL', 'OP-JP-MBX-XL', 'PKM-JP-MBX-ST', 'OP-JP-MBX-ST', 'ACC-3D-DITTO-410', 'PKM-EN-PBLK-BLR-SLP',
  ];
  manualShortNameSkus.forEach((sku) => {
    const result = makeEnvironment({ productSku: sku, literalProductShortName: true })();
    assert.deepEqual(JSON.parse(JSON.stringify(result.problems)), [], sku + ' must remain an allowed manual short name.');
  });
  const nonExempt = makeEnvironment({ productSku: 'PKM-EN-NOT-EXEMPT', literalProductShortName: true })();
  assertOnlyProblem(nonExempt, 'formula_column_literal');
  assert.match(nonExempt.problems[0].detail, /Коротка назва/);
  const literalPrice = makeEnvironment({ productSku: 'ACC-001', literalProductShortName: true, literalProductPrice: true })();
  assertOnlyProblem(literalPrice, 'formula_column_literal');
  assert.match(literalPrice.problems[0].detail, /Поточна ціна продажу/);
}

{
  const manualUsageNames = [
    'Аніме-брелок поліестер', 'Брошки TCG енергії', 'Фоторамка One Piece', 'Фоторамка Pokémon', 'Наліпка One Piece',
    'Нашивка', 'Фігурка краба', 'Піни One Piece', 'Фігурка Pokémon', 'FUR-BR-COLOR-MIX', 'FUR-BR-CARB',
  ];
  manualUsageNames.forEach((name) => {
    const result = makeEnvironment({ consumableUsageName: name, literalConsumableUsage: true })();
    assert.deepEqual(JSON.parse(JSON.stringify(result.problems)), [], name + ' must remain an allowed manual historic usage.');
  });
  const nonExempt = makeEnvironment({ consumableUsageName: 'Неузгоджений ручний розхідник', literalConsumableUsage: true })();
  assertOnlyProblem(nonExempt, 'formula_column_literal');
  assert.match(nonExempt.problems[0].detail, /Використано в продажах/);
}

{
  const result = makeEnvironment({ remoteMode: 'mismatch' })();
  assertOnlyProblem(result, 'rrp_mismatch_3dp');
  assert.equal(result.coverage.rrp_mismatch_3dp.compared, 1);
}

{
  const result = makeEnvironment({ rrcEnabled: false, productActive: false, masterActive: false, remoteMode: 'mismatch' })();
  assert.deepEqual(JSON.parse(JSON.stringify(result.problems)), []);
  assert.equal(result.coverage.rrp_mismatch_3dp.skipped_missing_crm_rrp, 1);
}

{
  const result = makeEnvironment({ remoteMode: 'deferred' })();
  assert.deepEqual(JSON.parse(JSON.stringify(result.problems)), []);
  assert.match(result.coverage.rrp_mismatch_3dp.deferred, /REMOTE_DOWN/);
}

{
  const dirty = makeEnvironment({ rrcEnabled: false, brokenRrcRows: [71, 72, 73, 74, 75] })();
  assert.equal(dirty.ok, true);
  assert.equal(dirty.clean, false);
  const transported = await throughDashboardTransport(dirty);
  assert.equal(transported.clean, false);
  assert.ok(transported.problems.some((problem) => problem.code === 'price_without_sku'));
}

{
  const clean = makeEnvironment()();
  const transported = await throughDashboardTransport(clean);
  assert.equal(transported.ok, true);
  assert.equal(transported.clean, true);
  assert.deepEqual(JSON.parse(JSON.stringify(transported.problems)), []);
}

await assert.rejects(() => throughDashboardTransport({ ok: false, error: 'bad token' }), /bad token/);

console.log("CRM integrity-check tests passed");
