import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, "../Code.gs"), "utf8");

class Range {
  constructor(sheet, row, column, rows = 1, columns = 1) { this.sheet = sheet; this.row = row; this.column = column; this.rows = rows; this.columns = columns; }
  getValues() { return Array.from({ length: this.rows }, (_, y) => Array.from({ length: this.columns }, (_, x) => this.sheet.value(this.row + y, this.column + x))); }
  setValues(values) { values.forEach((row, y) => row.forEach((value, x) => this.sheet.set(this.row + y, this.column + x, value))); return this; }
  getRow() { return this.row; }
  getColumn() { return this.column; }
  getNumRows() { return this.rows; }
  getNumColumns() { return this.columns; }
  clearContent() { for (let y = 0; y < this.rows; y += 1) for (let x = 0; x < this.columns; x += 1) this.sheet.set(this.row + y, this.column + x, ""); return this; }
}

class Sheet {
  constructor(name, parent) { this.name = name; this.parent = parent; this.values = new Map(); }
  key(row, column) { return `${row}:${column}`; }
  value(row, column) { return this.values.get(this.key(row, column)) ?? ""; }
  set(row, column, value) { this.values.set(this.key(row, column), value); }
  getRange(row, column, rows = 1, columns = 1) { return new Range(this, row, column, rows, columns); }
  getLastRow() { return Math.max(1, ...[...this.values.keys()].map((key) => Number(key.split(":")[0]))); }
  getName() { return this.name; }
  getParent() { return this.parent; }
  toast(message) { this.lastToast = message; }
}

class Spreadsheet {
  constructor() { this.sheets = new Map(); }
  add(name) { const sheet = new Sheet(name, this); this.sheets.set(name, sheet); return sheet; }
  getSheetByName(name) { return this.sheets.get(name) ?? null; }
}

function makeFixtureRow(sheet, row, name, payer, cost, stock) {
  sheet.set(row, 1, name); sheet.set(row, 2, "Фурнітура"); sheet.set(row, 3, cost); sheet.set(row, 9, stock); sheet.set(row, 15, payer);
}

const context = vm.createContext({ JSON, String, Number, Math, RegExp, Date, Error, console });
vm.runInContext(`${code}\nglobalThis.__test = {
  build3dp019FixtureUsagePlan_, crm3dpFixtureFrozenForOrder_, apply3dp019FixturePayerGuardOnEdit_,
  crm3dpSyncErrorOutcome_,
  orderHeader: CRM_3DP_ORDER_HEADER_, costHeader: CRM_3DP_FIXTURE_COST_HEADER_, payerHeader: CRM_3DP_FIXTURE_PAYER_HEADER_
};`, context, { filename: "Code.gs" });

function makeSpreadsheet() {
  const ss = new Spreadsheet();
  const consumables = ss.add("Розхідники");
  const ledger = ss.add("Використання_фурнітури");
  for (let index = 0; index < 11; index += 1) ledger.set(1, index + 1, `H${index}`);
  makeFixtureRow(consumables, 4, "FUR-CHAIN", "власник", 2.5, 1);
  makeFixtureRow(consumables, 5, "FUR-CARB", "власник", 1.25, 10);
  makeFixtureRow(consumables, 6, "FUR-S-CHAIN", "Сергій", 3, 10);
  return ss;
}

function addLedgerRow(ledger, row, { source = "Продаж", reference, name = "FUR-CHAIN", payer = "власник", qty, unitCost }) {
  ledger.set(row, 3, source); ledger.set(row, 4, reference); ledger.set(row, 5, name); ledger.set(row, 6, payer);
  ledger.set(row, 7, qty); ledger.set(row, 8, unitCost); ledger.set(row, 9, qty * unitCost);
}

{
  const ss = makeSpreadsheet();
  const result = context.__test.build3dp019FixtureUsagePlan_(ss, [
    { selection: "FUR-CHAIN | власник", qty: 2, row: 49 },
    { selection: "FUR-CARB | власник", qty: 1, row: 50 },
  ], "Продаж", "MAN-FOP-0001");
  assert.equal(result.ok, true);
  assert.equal(result.payer, "власник");
  assert.equal(result.total, 6.25);
  assert.match(result.warning, /FUR-CHAIN: запит 2, на складі 1/);
}

{
  const ss = makeSpreadsheet();
  const result = context.__test.build3dp019FixtureUsagePlan_(ss, [
    { selection: "FUR-CHAIN | власник", qty: 1, row: 49 },
    { selection: "FUR-S-CHAIN | Сергій", qty: 1, row: 50 },
  ], "Продаж", "MAN-FOP-0002");
  assert.equal(result.ok, false);
  assert.match(result.error, /FUR-CHAIN \(власник\).*FUR-S-CHAIN \(Сергій\)/);
}

{
  const ss = makeSpreadsheet();
  const result = context.__test.build3dp019FixtureUsagePlan_(ss, [{ selection: "FUR-CHAIN", qty: 1, row: 49 }], "Продаж", "MAN-FOP-0003");
  assert.equal(result.ok, false);
  assert.match(result.error, /некоректний формат/);
}

{
  const ss = makeSpreadsheet();
  const ledger = ss.getSheetByName("Використання_фурнітури");
  addLedgerRow(ledger, 2, { reference: "MAN-FOP-0004", payer: "Сергій", qty: 3, unitCost: 3 });
  const sales = ss.add("Продажі");
  const frozen = context.__test.crm3dpFixtureFrozenForOrder_(sales, "MAN-FOP-0004", [{ values: ["", "", "", "", "", "FIG-TEST", "", 3] }]);
  assert.deepEqual(JSON.parse(JSON.stringify(frozen)), { cost_per_unit: 3, payer: "Сергій", has_ledger: true });
}

{
  const ss = makeSpreadsheet();
  const ledger = ss.getSheetByName("Використання_фурнітури");
  addLedgerRow(ledger, 2, { reference: "MAN-FOP-0005", qty: 2, unitCost: 2.5 });
  ss.getSheetByName("Розхідники").set(4, 3, 99);
  const firstCorrection = context.__test.build3dp019FixtureUsagePlan_(ss, [
    { selection: "FUR-CHAIN | власник", qty: -1, row: 22 },
  ], "Продаж", "MAN-FOP-0005", { allow_correction: true });
  assert.equal(firstCorrection.ok, true);
  assert.equal(firstCorrection.ledger_source, "Коригування");
  assert.equal(firstCorrection.entries[0].unitCost, 2.5, "correction must keep the original frozen cost");
  addLedgerRow(ledger, 3, { source: "Коригування", reference: "MAN-FOP-0005", qty: -1, unitCost: 2.5 });
  const secondCorrection = context.__test.build3dp019FixtureUsagePlan_(ss, [
    { selection: "FUR-CHAIN | власник", qty: 1, row: 22 },
  ], "Продаж", "MAN-FOP-0005", { allow_correction: true });
  assert.equal(secondCorrection.ok, true, "a second correction for the same sale must remain append-only");
  assert.equal(secondCorrection.entries[0].unitCost, 2.5);
}

{
  const ss = makeSpreadsheet();
  const ledger = ss.getSheetByName("Використання_фурнітури");
  addLedgerRow(ledger, 2, { reference: "MAN-FOP-0006", qty: 2, unitCost: 2.5 });
  addLedgerRow(ledger, 3, { source: "Коригування", reference: "MAN-FOP-0006", qty: -1, unitCost: 2.5 });
  const belowZero = context.__test.build3dp019FixtureUsagePlan_(ss, [
    { selection: "FUR-CHAIN | власник", qty: -2, row: 22 },
  ], "Продаж", "MAN-FOP-0006", { allow_correction: true });
  assert.equal(belowZero.ok, false);
  assert.match(belowZero.error, /нижче нуля/);
  const combinedBelowZero = context.__test.build3dp019FixtureUsagePlan_(ss, [
    { selection: "FUR-CHAIN | власник", qty: -1, row: 22 },
    { selection: "FUR-CHAIN | власник", qty: -1, row: 23 },
  ], "Продаж", "MAN-FOP-0006", { allow_correction: true });
  assert.equal(combinedBelowZero.ok, false, "all correction rows in one form must be netted together");
  const wrongPayer = context.__test.build3dp019FixtureUsagePlan_(ss, [
    { selection: "FUR-S-CHAIN | Сергій", qty: 1, row: 22 },
  ], "Продаж", "MAN-FOP-0006", { allow_correction: true });
  assert.equal(wrongPayer.ok, false);
  assert.match(wrongPayer.error, /Не можна змінювати платника/);
}

{
  const ss = makeSpreadsheet();
  const ledger = ss.getSheetByName("Використання_фурнітури");
  addLedgerRow(ledger, 2, { reference: "MAN-FOP-0007", qty: 2, unitCost: 2.5 });
  addLedgerRow(ledger, 3, { source: "Коригування", reference: "MAN-FOP-0007", qty: -1, unitCost: 2.5 });
  const sales = ss.add("Продажі");
  const trigger = [{ values: ["", "", "", "", "", "FIG-TEST", "", 3] }];
  const corrected = context.__test.crm3dpFixtureFrozenForOrder_(sales, "MAN-FOP-0007", trigger);
  assert.deepEqual(JSON.parse(JSON.stringify(corrected)), { cost_per_unit: 0.83, payer: "власник", has_ledger: true });
  addLedgerRow(ledger, 4, { source: "Коригування", reference: "MAN-FOP-0007", qty: -1, unitCost: 2.5 });
  const reversed = context.__test.crm3dpFixtureFrozenForOrder_(sales, "MAN-FOP-0007", trigger);
  assert.deepEqual(JSON.parse(JSON.stringify(reversed)), { cost_per_unit: 0, payer: "", has_ledger: true });
}

{
  const ss = makeSpreadsheet();
  const ledger = ss.getSheetByName("Використання_фурнітури");
  addLedgerRow(ledger, 2, { reference: "MAN-FOP-0008", payer: "", qty: 1, unitCost: 2.5 });
  const sales = ss.add("Продажі");
  assert.throws(() => context.__test.crm3dpFixtureFrozenForOrder_(sales, "MAN-FOP-0008", [{ values: ["", "", "", "", "", "FIG-TEST", "", 1] }]), /CRM_3DP_FIXTURE_ALLOCATION:PAYER/);
  assert.equal(context.__test.crm3dpSyncErrorOutcome_("CRM_3DP_FIXTURE_ALLOCATION:ZERO_UNITS; test"), "skipped_fixture_allocation");
  assert.equal(context.__test.crm3dpSyncErrorOutcome_("other API problem"), "skipped_api_error");
}

console.log("3D-P-019 fixture usage tests passed");
