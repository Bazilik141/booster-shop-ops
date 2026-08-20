import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, '../Code.gs'), 'utf8');

function functionSource(name) {
  const match = new RegExp('function ' + name + '\\(').exec(code);
  if (!match) throw new Error('Missing function: ' + name);
  const start = match.index;
  const open = code.indexOf('{', start);
  let depth = 0;
  for (let index = open; index < code.length; index += 1) {
    if (code[index] === '{') depth += 1;
    if (code[index] === '}') {
      depth -= 1;
      if (depth === 0) return code.slice(start, index + 1);
    }
  }
  throw new Error('Unclosed function: ' + name);
}

class Range {
  constructor(sheet, row, column, rows = 1, columns = 1) { this.sheet = sheet; this.row = row; this.column = column; this.rows = rows; this.columns = columns; }
  getValues() {
    return Array.from({ length: this.rows }, (_, rowOffset) => Array.from({ length: this.columns }, (_, columnOffset) => this.sheet.valueAt(this.row + rowOffset, this.column + columnOffset)));
  }
  setValues(values) {
    values.forEach((row, rowOffset) => row.forEach((value, columnOffset) => this.sheet.setValue(this.row + rowOffset, this.column + columnOffset, value)));
    return this;
  }
}

class Sheet {
  constructor(rows) { this.rows = rows; this.writes = []; }
  getLastRow() { return this.rows.length + 2; }
  getRange(row, column, rows = 1, columns = 1) { return new Range(this, row, column, rows, columns); }
  valueAt(row, column) {
    if (row === 2 && column >= 30 && column <= 32) return ['Метод собівартості', 'Аудит собівартості', 'Дата фіксації собівартості'][column - 30];
    const source = this.rows[row - 3] || [];
    return source[column - 1] ?? '';
  }
  setValue(row, column, value) {
    const source = this.rows[row - 3];
    source[column - 1] = value;
    this.writes.push({ row, column, value });
  }
}

function sale({ order, sku = 'PKM-EN-Q2-MTIN-SAL', name = 'Mini Tin', prro = 689.88, mgmt = 731.27, payment = 'Не оплачено', status = 'В обробці' }) {
  const row = Array(32).fill('');
  row[0] = order; row[2] = '2026-08-20'; row[5] = sku; row[6] = name; row[7] = 1;
  row[11] = prro; row[12] = mgmt; row[22] = payment; row[23] = status; row[29] = 'FIFO'; row[30] = 'before=0';
  return row;
}

const sales = new Sheet([
  sale({ order: 'OC-FOP-0324' }),
  sale({ order: 'OC-FOP-UNCHANGED', prro: 551.9, mgmt: 585.01 }),
  sale({ order: 'OC-FOP-3DP', sku: 'FIG-CHARM-001' }),
  sale({ order: 'OC-FOP-MBX', name: 'Mystery Box Mini Tin' }),
  sale({ order: 'OC-FOP-CANCELLED', payment: 'Скасовано', status: 'Скасовано' })
]);
const spreadsheet = { getSheetByName: (name) => name === 'Продажі' ? sales : null };
const calls = { logs: [], flush: 0, invalidate: 0 };
const context = vm.createContext({
  Math, Number, String, Array, JSON, Date, Error,
  CRM011_FIFO_COST_DEFAULT_SKU_: 'PKM-EN-Q2-MTIN-SAL', CRM011_FIFO_COST_TOLERANCE_: 0.009, CRM011_FIFO_COST_MAX_ROWS_: 50, CRM011_FIFO_COST_ORDER_: 'OC-FOP-0324',
  num_: (value) => Number(value) || 0,
  round2_: (value) => Math.round((Number(value) || 0) * 100) / 100,
  apiDate_: (value) => String(value || ''),
  is3dpPackagingSku_: (sku) => String(sku).startsWith('FIG-'),
  isMysteryBoxSale_: (_, name) => String(name).toLowerCase().includes('mystery'),
  isActualSaleForCost_: (values) => values[22] !== 'Скасовано' && values[23] !== 'Скасовано',
  calculateFifoSaleCost_: (_, sku, __, row) => {
    if (sku === 'FIG-CHARM-001') return { prroUnit: 0, mgmtUnit: 0, method: '3D', audit: '' };
    if (row === 3 || row === 4 || row === 6) return { prroUnit: 551.9, mgmtUnit: 585.01, method: 'FIFO', audit: 'before=0; LOT-0113: 1 x 551.9/585.01' };
    return { prroUnit: 0, mgmtUnit: 0, method: 'FIFO', audit: '' };
  },
  trimCostAudit_: (value) => String(value),
  SpreadsheetApp: { getActiveSpreadsheet: () => spreadsheet, flush: () => { calls.flush += 1; } },
  Logger: { log: (value) => { calls.logs.push(value); } },
  invalidateDoGetCache_: () => { calls.invalidate += 1; }
});

vm.runInContext([
  functionSource('crm011FifoCostDiagnosticRow_'),
  functionSource('diagnoseCrm011FifoCostDrift_'),
  functionSource('diagnoseCrm011FifoCostDrift'),
  functionSource('crm011ResolveExactSaleRow_'),
  functionSource('crm011CostAuditHeadersReady_'),
  functionSource('repairCrm011FifoCostRows_'),
  functionSource('previewCrm011OcFop0324Repair'),
  functionSource('repairCrm011OcFop0324'),
  'globalThis.__test = { crm011FifoCostDiagnosticRow_, diagnoseCrm011FifoCostDrift_, diagnoseCrm011FifoCostDrift, repairCrm011FifoCostRows_, previewCrm011OcFop0324Repair, repairCrm011OcFop0324 };'
].join('\n'), context, { filename: 'Code.gs' });

const diagnostic = context.__test.diagnoseCrm011FifoCostDrift_(spreadsheet, 'PKM-EN-Q2-MTIN-SAL', 50);
assert.equal(diagnostic.total_rows, 4, 'diagnostic considers every matching SKU row, including excluded rows');
assert.equal(diagnostic.truncated, 0);
assert.equal(diagnostic.rows[0].would_change, true, 'drifted ordinary FIFO row is flagged');
assert.equal(diagnostic.rows[0].fifo_prro_unit, 551.9);
assert.equal(diagnostic.rows[1].would_change, false, 'matching frozen row stays unchanged');
assert.equal(diagnostic.rows[2].skip_reason, 'mystery_box', 'Mystery Box rows stay outside generic FIFO repair');
assert.equal(diagnostic.rows[3].skip_reason, 'not_actual', 'non-actual sale rows stay outside repair');
assert.equal(sales.writes.length, 0, 'Stage A diagnostic must write no cells');
assert.equal(calls.invalidate, 0, 'Stage A diagnostic never invalidates cache');
assert.doesNotMatch(functionSource('diagnoseCrm011FifoCostDrift_'), /setValue|setValues|invalidateDoGetCache_|updateSkuCurrentCost_/, 'Stage A source stays read-only');

const threeDp = context.__test.crm011FifoCostDiagnosticRow_(spreadsheet, 5, sales.rows[2]);
assert.equal(threeDp.skip_reason, '3dp_projection', '3D projection rows are hard-excluded');

const capped = context.__test.diagnoseCrm011FifoCostDrift_(spreadsheet, 'PKM-EN-Q2-MTIN-SAL', 1);
assert.equal(capped.returned_rows, 1);
assert.equal(capped.truncated, 3, 'diagnostic reports omitted rows instead of streaming beyond the cap');

const preview = context.__test.previewCrm011OcFop0324Repair();
assert.equal(preview.dry_run, true);
assert.equal(preview.rows_written, 0);
assert.equal(sales.writes.length, 0, 'preview must not alter frozen costs');

const repair = context.__test.repairCrm011OcFop0324();
assert.equal(repair.dry_run, false);
assert.equal(repair.rows_written, 1);
assert.equal(sales.rows[0][11], 551.9);
assert.equal(sales.rows[0][12], 585.01);
assert.equal(sales.rows[0][29], 'FIFO (CRM-011)');
assert.match(sales.rows[0][30], /crm011_refreeze=2026-08-20/);
assert.equal(calls.flush, 1);
assert.equal(calls.invalidate, 1, 'successful repair invalidates dashboard cache exactly once');

const repeat = context.__test.repairCrm011OcFop0324();
assert.equal(repeat.rows_written, 0, 'repeat repair is idempotent');
assert.equal(repeat.already_applied, true);
assert.equal(calls.invalidate, 1, 'idempotent repeat does not invalidate cache');

console.log('CRM-011 FIFO cost diagnostic and repair tests passed');
