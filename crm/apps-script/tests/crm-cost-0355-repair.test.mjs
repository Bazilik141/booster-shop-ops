import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, '../Code.gs'), 'utf8');
const repairCode = fs.readFileSync(path.resolve(here, '../TEMP_CRM_COST_0355_repair_20260901.gs'), 'utf8');
const orderRepairV2Code = fs.readFileSync(path.resolve(here, '../TEMP_CRM_COST_0355_order_repair_V2_20260901.gs'), 'utf8');

function functionSource(source, name) {
  const match = new RegExp('function ' + name + '\\(').exec(source);
  if (!match) throw new Error('Missing function: ' + name);
  const start = match.index, open = source.indexOf('{', start);
  let depth = 0;
  for (let index = open; index < source.length; index += 1) {
    if (source[index] === '{') depth += 1;
    if (source[index] === '}' && --depth === 0) return source.slice(start, index + 1);
  }
  throw new Error('Unclosed function: ' + name);
}

class Range {
  constructor(sheet, row, column, rows = 1, columns = 1) { Object.assign(this, { sheet, row, column, rows, columns }); }
  getValues() { return Array.from({ length: this.rows }, (_, r) => Array.from({ length: this.columns }, (_, c) => this.sheet.valueAt(this.row + r, this.column + c))); }
  setValues(values) { values.forEach((valuesRow, r) => valuesRow.forEach((value, c) => this.sheet.setValue(this.row + r, this.column + c, value))); return this; }
}

class Sheet {
  constructor(name, rows) { this.name = name; this.rows = rows; }
  getLastRow() { return this.rows.length + 2; }
  getRange(row, column, rows = 1, columns = 1) { return new Range(this, row, column, rows, columns); }
  valueAt(row, column) { return (this.rows[row - 3] || [])[column - 1] ?? ''; }
  setValue(row, column, value) { this.rows[row - 3][column - 1] = value; }
}

const sale = Array(32).fill('');
sale[0] = 'OC-FOP-0355'; sale[2] = '2026-09-01'; sale[5] = 'PKM-JP-BBLT-BST'; sale[6] = 'Black Bolt'; sale[7] = 3; sale[8] = 350;
sale[11] = 2886.34; sale[12] = 3074.02; sale[22] = 'Оплачено'; sale[23] = 'В обробці'; sale[29] = 'FIFO + авторозхідники + компоненти замовлення'; sale[30] = 'before=3; LOT-0073: 1 x 2866.88/3038.89';
const lot = Array(18).fill('');
lot[0] = 'LOT-0073'; lot[4] = 'PKM-JP-MZERO-BBX'; lot[7] = 1; lot[12] = 2866.88; lot[15] = 3038.89; lot[16] = 'Продано';
const sales = new Sheet('Продажі', [sale]);
const purchases = new Sheet('Закупки', [lot]);
const spreadsheet = { getSheetByName: (name) => name === 'Продажі' ? sales : (name === 'Закупки' ? purchases : null) };
const calls = { flush: 0, invalidate: 0, lock: 0, clearRanges: [] };
const context = vm.createContext({
  Object, Math, Number, String, Array, JSON, Date, Error,
  num_: (value) => Number(value) || 0,
  round2_: (value) => Math.round((Number(value) || 0) * 100) / 100,
  apiDate_: (value) => String(value || ''),
  isActualSaleForCost_: () => true,
  isMysteryBoxSale_: () => false,
  is3dpPackagingSku_: () => false,
  calculateFifoSaleCost_: () => ({ prroUnit: 217.84, mgmtUnit: 230.91, method: 'FIFO', audit: 'before=26; LOT-0064: 3 x 217.84/230.91' }),
  trimCostAudit_: (value) => String(value),
  resetMemoForMutation_: () => {},
  apiIntegrityCheck_: () => ({ clean: true, problems: [] }),
  crmAssertCapacityIntegrity_: () => ({ before_clean: true, after_clean: true, introduced_problems: 0 }),
  invalidateDoGetCache_: () => { calls.invalidate += 1; },
  SpreadsheetApp: { getActiveSpreadsheet: () => spreadsheet, flush: () => { calls.flush += 1; } },
  LockService: { getDocumentLock: () => ({ tryLock: () => { calls.lock += 1; return true; }, releaseLock: () => {} }) },
  Logger: { log: () => {} }
});

vm.runInContext(repairCode + '\nglobalThis.__test={previewCrmCost0355Repair,repairCrmCost0355};', context, { filename: 'TEMP_CRM_COST_0355_repair_20260901.gs' });
const preview = context.__test.previewCrmCost0355Repair();
assert.equal(preview.dry_run, true);
assert.equal(preview.rows_written, 0);
assert.equal(sale[11], 2886.34, 'preview stays read-only');

const applied = context.__test.repairCrmCost0355();
assert.equal(applied.rows_written, 1);
assert.equal(sale[11], 217.84);
assert.equal(sale[12], 230.91);
assert.equal(sale[29], 'FIFO (CRM-COST-0355)');
assert.match(sale[30], /crm_cost_0355_refreeze=2026-09-01/);
assert.equal(calls.flush, 1);
assert.equal(calls.invalidate, 1);

const repeat = context.__test.repairCrmCost0355();
assert.equal(repeat.already_applied, true);
assert.equal(repeat.rows_written, 0);
assert.equal(calls.invalidate, 1, 'idempotent repeat does not invalidate again');

const clearContext = vm.createContext({ Math, Number, String, Array, Error });
const clearCalls = [];
const clearSheet = { getRangeList: (ranges) => ({ clearContent: () => { clearCalls.push(ranges); } }) };
const clearSs = { getSheetByName: (name) => name === 'Продажі' ? clearSheet : null };
vm.runInContext(functionSource(code, 'crmClearFreshSaleCostState_') + '\nglobalThis.clearFresh=crmClearFreshSaleCostState_;', clearContext);
const cleared = clearContext.clearFresh(clearSs, 316, 4);
assert.deepEqual(Array.from(cleared.ranges), ['L316:M319', 'AD316:AF319']);
assert.equal(clearCalls.length, 1, 'one RangeList clear per order');
assert.doesNotMatch(functionSource(code, 'crmClearFreshSaleCostState_'), /getValues|getDisplayValues|SpreadsheetApp\.flush/, 'guard adds no scan, read, or flush');
assert.match(functionSource(code, 'crmNextAppendRow_'), /sheetName === 'Продажі'[\s\S]*crmClearFreshSaleCostState_/, 'all fresh sale writers use the guard centrally');

const orderRepairAction = functionSource(orderRepairV2Code, 'crmCost0355OrderRepairV2Action_');
assert.match(orderRepairAction, /resetOrderComponentCostProjectionBeforeBaseRefresh_/, 'V2 removes only the prior component projection before FIFO');
assert.match(orderRepairAction, /fixSaleCostForRow_\([\s\S]*forceRecalculate:\s*true/, 'V2 uses the canonical forced FIFO writer');
assert.match(orderRepairAction, /reapplyOrderComponentCostAfterBaseRefresh_/, 'V2 restores component-ledger costs after FIFO');
assert.match(orderRepairAction, /apiIntegrityCheck_\(\)[\s\S]*apiIntegrityCheck_\(\)/, 'V2 runs integrity before and after');
assert.match(orderRepairAction, /setValues\(originalCosts\)[\s\S]*setValues\(originalAudit\)/, 'V2 rolls back all four rows on failure');
assert.doesNotMatch(functionSource(orderRepairV2Code, 'crmCost0355OrderRepairV2Plan_'), /setValue|setValues|clearContent/, 'V2 preview plan is read-only');

console.log('CRM-COST-0355 repair and fresh-row guard tests passed');
