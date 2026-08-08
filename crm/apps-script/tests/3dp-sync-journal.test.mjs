import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const codePath = path.resolve(here, "../Code.gs");
const code = fs.readFileSync(codePath, "utf8");

class MockRange {
  constructor(sheet, row, column, rows, columns) { this.sheet = sheet; this.row = row; this.column = column; this.rows = rows; this.columns = columns; }
  getValues() { return Array.from({ length: this.rows }, (_, rowOffset) => Array.from({ length: this.columns }, (_, columnOffset) => this.sheet.value(this.row + rowOffset, this.column + columnOffset))); }
  getDisplayValues() { return this.getValues().map((row) => row.map((value) => String(value ?? ""))); }
  setValues(values) {
    if (this.sheet.failJournalAppend && this.row >= 2) throw new Error("journal write failed");
    values.forEach((valuesRow, rowOffset) => valuesRow.forEach((value, columnOffset) => this.sheet.set(this.row + rowOffset, this.column + columnOffset, value)));
    return this;
  }
}

class MockSheet {
  constructor(name) { this.name = name; this.rows = []; this.hidden = false; this.frozenRows = 0; this.failJournalAppend = false; }
  value(row, column) { return (this.rows[row - 1] || [])[column - 1] ?? ""; }
  set(row, column, value) { if (!this.rows[row - 1]) this.rows[row - 1] = []; this.rows[row - 1][column - 1] = value; }
  getRange(row, column, rows = 1, columns = 1) { return new MockRange(this, row, column, rows, columns); }
  getLastRow() { for (let index = this.rows.length - 1; index >= 0; index -= 1) if ((this.rows[index] || []).some((value) => value !== "" && value !== undefined)) return index + 1; return 0; }
  setFrozenRows(value) { this.frozenRows = value; }
  hideSheet() { this.hidden = true; }
  isSheetHidden() { return this.hidden; }
  deleteRows(start, count) { this.rows.splice(start - 1, count); }
}

class MockSpreadsheet {
  constructor() { this.sheets = new Map(); }
  getSheetByName(name) { return this.sheets.get(name) || null; }
  insertSheet(name) { const sheet = new MockSheet(name); this.sheets.set(name, sheet); return sheet; }
}

class MockSales {
  constructor(spreadsheet, rows) { this.spreadsheet = spreadsheet; this.rows = rows; }
  getParent() { return this.spreadsheet; }
  getRange(row) { return { getValues: () => [this.rows.get(row) || Array(16).fill("")] }; }
}

function response(payload, status = 200) {
  return { getResponseCode: () => status, getContentText: () => JSON.stringify(payload) };
}

function saleRow(sku, quantity = 1, packaging = 10) {
  const row = Array(16).fill("");
  row[5] = sku;
  row[7] = quantity;
  row[15] = packaging;
  return row;
}

function journalRows(spreadsheet) {
  const sheet = spreadsheet.getSheetByName("_Журнал_3DP_синхронізації");
  if (!sheet || sheet.getLastRow() < 2) return [];
  return sheet.getRange(2, 1, sheet.getLastRow() - 1, 7).getValues();
}

function makeEnvironment(options = {}) {
  const spreadsheet = new MockSpreadsheet();
  const properties = {
    BOOSTER_CRM_TOKEN: "owner-token",
    BOOSTER_3DP_URL: options.configured === false ? "" : "https://3dp.example/exec",
    BOOSTER_3DP_SYNC_TOKEN: options.configured === false ? "" : "3dp-sync-token",
  };
  const remote = {
    outage: !!options.outage,
    schemaReady: options.schemaReady !== false,
    existingRows: options.existingRows || [],
    nomenclatureRows: options.nomenclatureRows || {},
    nomenclatureFailures: options.nomenclatureFailures || {},
    stockAlreadyApplied: !!options.stockAlreadyApplied,
    negativeStock: !!options.negativeStock,
    appendPayloads: [],
    writePayloads: [],
  };
  const logs = [];
  const context = vm.createContext({
    JSON, Math, Number, String, Boolean, Array, Object, RegExp, Date, Error, isFinite,
    Logger: { log: (line) => logs.push(String(line)) },
    Session: { getScriptTimeZone: () => "Europe/Kyiv" },
    Utilities: { formatDate: (value) => value && typeof value.getTime === "function" && value.getTime() === Date.parse("2026-08-08T12:47:22.000Z") ? "2026-08-08 15:47:22" : "2026-08-08 12:00:00" },
    PropertiesService: { getScriptProperties: () => ({ getProperty: (key) => properties[key] || "" }) },
    SpreadsheetApp: { openById: () => spreadsheet },
    ContentService: { MimeType: { JSON: "JSON" }, createTextOutput: (text) => ({ text, setMimeType() { return this; } }) },
    UrlFetchApp: {
      fetch: (url, request = {}) => {
        if (remote.outage) throw new Error("Request failed for https://3dp.example/exec?token=3dp-sync-token");
        const body = request.method === "post" ? JSON.parse(request.payload) : null;
        const action = body ? body.action : new URL(url).searchParams.get("action");
        if (action === "3dp_get_range") return response({ ok: true, values: [remote.schemaReady ? ["CRM row number", "РРЦ на момент продажу, грн", "Вартість фурнітури за од., грн (заморожена)", "Платник фурнітури"] : ["wrong header"]] });
        if (action === "3dp_sales") return response({ ok: true, rows: remote.existingRows });
        if (action === "3dp_stock_adjustments") return response({ ok: true, rows: remote.stockAlreadyApplied ? [{ "Причина": new URL(url).searchParams.get("reason") }] : [] });
        if (action === "3dp_get_row") {
          const params = new URL(url).searchParams;
          if (params.get("sheet") === "Номенклатура") {
            const sku = params.get("sku");
            if (remote.nomenclatureFailures[sku]) return response({ ok: false, code: remote.nomenclatureFailures[sku] });
            return response({ ok: true, row: remote.nomenclatureRows[sku] || { "Собівартість Сергія (виробнича), грн": 12.5, "РРЦ фактична, грн": 99, "Фурнітура (ціна-довідка), грн/шт": 4 } });
          }
          return response({ ok: true, row: { "Наявно зараз, шт": 10 } });
        }
        if (action === "3dp_append_row") { remote.appendPayloads.push(body); return response({ ok: true, row: 20 }); }
        if (action === "3dp_write") { remote.writePayloads.push(body); return response({ ok: true }); }
        if (action === "3dp_adjust_stock") return response({ ok: true, new_value: remote.negativeStock ? -1 : 9, warning: remote.negativeStock ? "insufficient_stock" : null });
        throw new Error("unexpected 3D-P action " + action);
      },
    },
  });
  vm.runInContext(`${code}\nglobalThis.__test = { sync3dpSales_, sync3dpPackagingCost_, is3dpPackagingSku_, crm3dpAppendJournal_, crm3dpSanitizeJournalDetail_, apiSyncJournal_, doGet };`, context, { filename: codePath });
  return { context, spreadsheet, properties, remote, logs };
}

assert.match(code, /sync3dpPackagingCost_\(sales, operation, addedRows, 'apiAddSale_'\)/);
assert.match(code, /sync3dpPackagingCost_\(sales, order, rows, 'apiUpdateSale_'\)/);
assert.match(code, /function sync3dpPackagingCost_\(sales, orderId, rowNumbers\)[\s\S]*arguments\[3\]/);
assert.match(code, /ROW_NOT_FOUND\|ROW_FILTERED/);
const journalFunction = code.slice(code.indexOf("function crm3dpJournalEntry_"), code.indexOf("function crm3dpAppendJournal_"));
assert.doesNotMatch(journalFunction, /SpreadsheetApp\.getActive/);
assert.doesNotMatch(fs.readFileSync(path.resolve(here, "../../../3d-print/apps-script-3dp-api/Code.gs"), "utf8"), /sync_journal/);

{
  const env = makeEnvironment();
  for (const sku of ['ACC-3D-DITTO-410', 'ACC-3D-PKM-130', 'ACC-3D-410', 'FIG-CHARM-001', 'BR-CHARM-100']) {
    assert.equal(env.context.__test.is3dpPackagingSku_(sku), true, sku + ' must trigger 3D-P sync');
  }
  for (const sku of ['ACC-001', 'MBX-STD-001', 'ACC-3D-']) {
    assert.equal(env.context.__test.is3dpPackagingSku_(sku), false, sku + ' must not trigger 3D-P sync');
  }
}

{
  const env = makeEnvironment();
  const sales = new MockSales(env.spreadsheet, new Map([[3, saleRow('ACC-3D-DITTO-410')]]));
  const result = env.context.__test.sync3dpSales_(sales, 'OC-FOP-ACC-001', [3], 'apiAddSale_');
  assert.equal(result.ok, true);
  assert.equal(result.created, 1);
  assert.equal(journalRows(env.spreadsheet)[0][5], 'created');
  assert.equal(env.remote.appendPayloads[0].values.F, 12.5);
  assert.equal(env.remote.appendPayloads[0].values.U, 99);
  assert.equal(env.remote.appendPayloads[0].values.V, 4);
  assert.equal(env.remote.appendPayloads[0].values.W, 'власник');
}

{
  const env = makeEnvironment({ nomenclatureRows: {
    'FIG-CHARM-001': { "Собівартість Сергія (виробнича), грн": "", "РРЦ фактична, грн": "", "Фурнітура (ціна-довідка), грн/шт": 4 },
  } });
  const sales = new MockSales(env.spreadsheet, new Map([[3, saleRow('FIG-CHARM-001')]]));
  const result = env.context.__test.sync3dpSales_(sales, 'OC-FOP-MISSING-FROZEN', [3], 'apiAddSale_');
  assert.equal(result.ok, true);
  assert.equal(result.skipped, 'missing_frozen_inputs');
  assert.equal(env.remote.appendPayloads.length, 0);
  assert.equal(journalRows(env.spreadsheet)[0][5], 'skipped_missing_cost_or_rrp');
  assert.match(journalRows(env.spreadsheet)[0][6], /CRM sale remains saved/);
}

{
  const env = makeEnvironment({ nomenclatureFailures: { 'FIG-CHARM-001': 'ROW_NOT_FOUND' } });
  const sales = new MockSales(env.spreadsheet, new Map([
    [3, saleRow('FIG-CHARM-001')],
    [4, saleRow('ACC-3D-DITTO-410')],
  ]));
  const result = env.context.__test.sync3dpSales_(sales, 'OC-FOP-MISSING-NOMENCLATURE', [3, 4], 'apiAddSale_');
  assert.equal(result.ok, true);
  assert.equal(result.created, 1);
  assert.deepEqual(journalRows(env.spreadsheet).map((row) => row[5]), ['skipped_sku_not_in_nomenclature', 'created']);
  assert.match(journalRows(env.spreadsheet)[0][6], /FIG-CHARM-001.*Номенклатура/);
  assert.equal(env.remote.appendPayloads.length, 1);
  assert.equal(env.remote.writePayloads.filter((payload) => payload.column === 'G').length, 1);
}

{
  const env = makeEnvironment({ nomenclatureFailures: { 'FIG-CHARM-001': 'ROW_FILTERED' } });
  const sales = new MockSales(env.spreadsheet, new Map([[3, saleRow('FIG-CHARM-001')]]));
  const result = env.context.__test.sync3dpSales_(sales, 'OC-FOP-FILTERED-NOMENCLATURE', [3], 'apiAddSale_');
  assert.equal(result.ok, true);
  assert.equal(result.skipped, 'missing_frozen_inputs');
  assert.equal(journalRows(env.spreadsheet)[0][5], 'skipped_sku_not_in_nomenclature');
}

{
  const env = makeEnvironment({ nomenclatureFailures: { 'FIG-CHARM-001': 'UNAUTHORIZED' } });
  const sales = new MockSales(env.spreadsheet, new Map([[3, saleRow('FIG-CHARM-001')]]));
  const result = env.context.__test.sync3dpSales_(sales, 'OC-FOP-NOMENCLATURE-AUTH', [3], 'apiAddSale_');
  assert.equal(result.ok, false);
  assert.equal(journalRows(env.spreadsheet)[0][5], 'skipped_api_error');
}

{
  const env = makeEnvironment();
  const sales = new MockSales(env.spreadsheet, new Map([[3, saleRow('ACC-3D-')]]));
  const result = env.context.__test.sync3dpSales_(sales, 'OC-FOP-SHAPE-001', [3], 'apiAddSale_');
  assert.equal(result.skipped, 'sku_shape');
  assert.equal(journalRows(env.spreadsheet)[0][4], 'ACC-3D-');
  assert.equal(journalRows(env.spreadsheet)[0][5], 'skipped_sku_shape');
  assert.match(journalRows(env.spreadsheet)[0][6], /ACC-3D-/);
}

{
  const env = makeEnvironment();
  const sales = new MockSales(env.spreadsheet, new Map([
    [3, saleRow('ACC-3D-')],
    [4, saleRow('ACC-3D-DITTO-410')],
  ]));
  const result = env.context.__test.sync3dpSales_(sales, 'OC-FOP-MIXED-001', [3, 4], 'apiAddSale_');
  assert.equal(result.created, 1);
  assert.deepEqual(journalRows(env.spreadsheet).map((row) => row[5]), ['skipped_sku_shape', 'created']);
}

{
  const env = makeEnvironment();
  const row = saleRow("FIG-CHARM-001");
  row[3] = "+380501234567";
  row[4] = "Ірина Тест";
  const sales = new MockSales(env.spreadsheet, new Map([[3, row]]));
  const result = env.context.__test.sync3dpSales_(sales, "OC-FOP-0300", [3], "apiAddSale_");
  assert.equal(result.ok, true);
  assert.deepEqual(journalRows(env.spreadsheet).map((row) => row[5]), ["created"]);
  assert.equal(journalRows(env.spreadsheet)[0][1], "apiAddSale_");
  assert.doesNotMatch(JSON.stringify(journalRows(env.spreadsheet)), /380501234567|Ірина Тест/);
  assert.equal(env.spreadsheet.getSheetByName("_Журнал_3DP_синхронізації").isSheetHidden(), true);
}

{
  const env = makeEnvironment({ configured: false });
  const sales = new MockSales(env.spreadsheet, new Map([[3, saleRow("FIG-CHARM-001")]]));
  const result = env.context.__test.sync3dpSales_(sales, "OC-FOP-0301", [3], "apiUpdateSale_");
  assert.equal(result.skipped, "not_configured");
  assert.equal(journalRows(env.spreadsheet)[0][5], "skipped_not_configured");
  assert.equal(journalRows(env.spreadsheet)[0][1], "apiUpdateSale_");
}

{
  const env = makeEnvironment({ outage: true });
  const sales = new MockSales(env.spreadsheet, new Map([[3, saleRow("FIG-CHARM-001")]]));
  const result = env.context.__test.sync3dpSales_(sales, "OC-FOP-0302", [3]);
  assert.equal(result.ok, false);
  const row = journalRows(env.spreadsheet)[0];
  assert.equal(row[1], "unknown");
  assert.equal(row[5], "skipped_api_error");
  assert.doesNotMatch(JSON.stringify(row), /3dp-sync-token|https:\/\//);
  assert.match(row[6], /\[URL redacted\]/);
  assert.doesNotMatch(env.logs.join("\n"), /3dp-sync-token|https:\/\//);
}

{
  const env = makeEnvironment({ schemaReady: false });
  const sales = new MockSales(env.spreadsheet, new Map([[3, saleRow("FIG-CHARM-001")]]));
  env.context.__test.sync3dpSales_(sales, "OC-FOP-0303", [3], "apiAddSale_");
  assert.equal(journalRows(env.spreadsheet)[0][5], "skipped_schema");
}

{
  const env = makeEnvironment();
  const sales = new MockSales(env.spreadsheet, new Map([[3, saleRow("CARD-001")]]));
  const result = env.context.__test.sync3dpPackagingCost_(sales, "OC-FOP-0304", [3]);
  assert.equal(result.skipped, "no_3dp_sku");
  assert.equal(journalRows(env.spreadsheet)[0][5], "skipped_no_3dp_sku");
  assert.equal(journalRows(env.spreadsheet)[0][1], "unknown");
}

{
  const env = makeEnvironment({ existingRows: [{ row_number: 20, "№ замовлення": "OC-FOP-0305", "CRM row number": 3, "Витрати BoosterShop за од., грн": 10 }], stockAlreadyApplied: true });
  const sales = new MockSales(env.spreadsheet, new Map([[3, saleRow("FIG-CHARM-001")]]));
  const result = env.context.__test.sync3dpSales_(sales, "OC-FOP-0305", [3], "apiUpdateSale_");
  assert.equal(result.ok, true);
  assert.deepEqual(journalRows(env.spreadsheet).map((row) => row[5]), ["noop"]);
}

{
  const env = makeEnvironment({ existingRows: [{ row_number: 20, "№ замовлення": "OC-FOP-0305B", "CRM row number": 3, "Витрати BoosterShop за од., грн": 0 }], stockAlreadyApplied: true });
  const sales = new MockSales(env.spreadsheet, new Map([[3, saleRow("FIG-CHARM-001")]]));
  env.context.__test.sync3dpSales_(sales, "OC-FOP-0305B", [3], "apiUpdateSale_");
  assert.deepEqual(journalRows(env.spreadsheet).map((row) => row[5]), ["updated"]);
  assert.match(journalRows(env.spreadsheet)[0][6], /already applied/);
}

{
  const env = makeEnvironment({ negativeStock: true });
  const sales = new MockSales(env.spreadsheet, new Map([[3, saleRow("FIG-CHARM-001")]]));
  env.context.__test.sync3dpSales_(sales, "OC-FOP-0306", [3], "apiAddSale_");
  assert.deepEqual(journalRows(env.spreadsheet).map((row) => row[5]), ["warning_negative_stock"]);
  assert.match(journalRows(env.spreadsheet)[0][6], /sale row was created/);
}

{
  const env = makeEnvironment();
  const sales = new MockSales(env.spreadsheet, new Map([[3, saleRow("FIG-CHARM-001", 1.5)]]));
  env.context.__test.sync3dpSales_(sales, "OC-FOP-0307", [3], "apiAddSale_");
  assert.equal(journalRows(env.spreadsheet)[0][5], "skipped_invalid_qty");
  assert.match(journalRows(env.spreadsheet)[0][6], /sale row was created/);
}

{
  const env = makeEnvironment({ existingRows: [
    { row_number: 20, "№ замовлення": "OC-FOP-0307B", "CRM row number": 3, "Витрати BoosterShop за од., грн": 10 },
    { row_number: 21, "№ замовлення": "OC-FOP-0307B", "CRM row number": 3, "Витрати BoosterShop за од., грн": 10 },
  ], stockAlreadyApplied: true });
  const sales = new MockSales(env.spreadsheet, new Map([[3, saleRow("FIG-CHARM-001")]]));
  env.context.__test.sync3dpSales_(sales, "OC-FOP-0307B", [3], "apiUpdateSale_");
  assert.deepEqual(journalRows(env.spreadsheet).map((row) => row[5]), ["warning_duplicate_key"]);
}

{
  const env = makeEnvironment();
  const detail = env.context.__test.crm3dpSanitizeJournalDetail_(
    "request https://example.test/exec?token=secret Bearer bearer-secret +380 50 123 45 67 test@example.com token=another-secret",
    "fallback"
  );
  assert.match(detail, /\[URL redacted\]|\[phone redacted\]|\[email redacted\]|\[redacted\]/);
  assert.doesNotMatch(detail, /secret|380 50 123|test@example\.com/);
  assert.ok(detail.length <= 240);
  const sales = new MockSales(env.spreadsheet, new Map());
  env.context.__test.crm3dpAppendJournal_(sales, "unknown", "OC-FOP-ERROR", null, "error", "Future CRM sync error: retry later.");
  assert.equal(journalRows(env.spreadsheet)[0][6], "Future CRM sync error: retry later.");
}

{
  const env = makeEnvironment();
  const originalInsert = env.spreadsheet.insertSheet.bind(env.spreadsheet);
  env.spreadsheet.insertSheet = (name) => { const sheet = originalInsert(name); sheet.failJournalAppend = true; return sheet; };
  const sales = new MockSales(env.spreadsheet, new Map([[3, saleRow("FIG-CHARM-001")]]));
  const result = env.context.__test.sync3dpSales_(sales, "OC-FOP-0308", [3], "apiAddSale_");
  assert.equal(result.ok, true);
  assert.equal(journalRows(env.spreadsheet).length, 0);
}

{
  const env = makeEnvironment();
  const sales = new MockSales(env.spreadsheet, new Map());
  env.context.__test.sync3dpSales_(sales, "", [], "unknown");
  const sheet = env.spreadsheet.getSheetByName("_Журнал_3DP_синхронізації");
  const storedKyivDate = vm.runInContext('new Date("2026-08-08T12:47:22.000Z")', env.context);
  sheet.rows = [["timestamp_kyiv", "source", "order_id", "crm_row", "sku", "outcome", "detail"], ["2026-08-08 10:00:00", "apiAddSale_", "OC-1", 3, "FIG-1", "created", "created"], [storedKyivDate, "apiUpdateSale_", "OC-1", 3, "FIG-1", "warning_negative_stock", "warning"]];
  const read = env.context.__test.apiSyncJournal_({ limit: 1 });
  assert.equal(read.count, 1);
  assert.equal(read.rows[0].outcome, "warning_negative_stock");
  assert.equal(read.rows[0].timestamp_kyiv, "2026-08-08 15:47:22");
  assert.equal(JSON.parse(env.context.__test.doGet({ parameter: { action: "sync_journal", token: "wrong" } }).text).ok, false);
  assert.equal(JSON.parse(env.context.__test.doGet({ parameter: { action: "sync_journal", token: "owner-token", limit: "1" } }).text).rows[0].outcome, "warning_negative_stock");
}

{
  const env = makeEnvironment();
  const sales = new MockSales(env.spreadsheet, new Map());
  for (let index = 0; index < 1001; index += 1) {
    env.context.__test.crm3dpAppendJournal_(sales, "unknown", "OC-" + index, { row: index + 3, values: saleRow("FIG-CHARM-001") }, "noop");
  }
  const rows = journalRows(env.spreadsheet);
  assert.equal(rows.length, 1000);
  assert.equal(rows[0][2], "OC-1");
  assert.equal(rows.at(-1)[2], "OC-1000");
}

console.log("3dp-sync-journal tests passed");
