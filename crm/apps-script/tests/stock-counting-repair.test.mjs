import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, '../Code.gs'), 'utf8');
assert.match(code, /function diagnoseCrmStockCounting20260901\(\)/,
  'the repair has an owner-visible read-only preflight');
assert.match(code, /function repairCrmStockCounting20260901\(\)/,
  'the repair has an owner-visible apply function');
assert.match(code, /function diagnoseCrmStockCounting20260901\(\)[\s\S]*Logger\.log\(JSON\.stringify\(result\)\)/,
  'manual diagnostic runs write their result to the Apps Script execution log');
assert.match(code, /CRM_STOCK_COUNTING_REPAIR_MARKER_/,
  'the paired HWAK/MSYM correction has an idempotency marker');
assert.match(code, /const balanceVerification = crmStockCountingValidateBalances_\(ss\)/,
  'apply verifies calculated H values against ledger arithmetic, not only formula text');

const context = vm.createContext({
  JSON, Math, Number, String, Boolean, Array, Object, RegExp, Date, Error, Set, isFinite,
  Logger: { log() {} }, SpreadsheetApp: {}, Utilities: {}, Session: {}, console
});
vm.runInContext(code + '\nglobalThis.__stockRepairTest={inventoryMigrationStockBalanceFormula_,crmStockCountingWriteoffState_,crmStockCountingRuns_};', context, { filename: 'Code.gs' });

const formula = context.__stockRepairTest.inventoryMigrationStockBalanceFormula_(14);
assert.match(formula, /\$E14-\$F14-\$G14/,
  'the canonical balance subtracts the write-off aggregate once');
assert.match(formula, /Міграції_Складу/,
  'the canonical balance includes internal stock migrations');
assert.match(formula, /-N\(\$S14\)/,
  'the canonical balance subtracts the single active-reservation aggregate');
assert.doesNotMatch(formula, /SUMIFS\('Списання'/,
  'the canonical balance never subtracts the write-off ledger a second time');
assert.doesNotMatch(formula, /Передзамовлення/,
  'the canonical balance does not add a second preorder-only reservation path');

function duplicateRow() {
  const row = Array(12).fill('');
  row[0] = 'WRT-0226';
  row[1] = '2026-08-24';
  row[2] = 'Власне відкриття';
  row[3] = 'PKM-JP-INFX-BST';
  row[5] = 10;
  row[10] = 'Коригування складу';
  return row;
}

function writeoffSheet(rows) {
  return {
    getLastRow: () => rows.length + 2,
    getRange: (row, column, rowCount, columnCount) => ({
      getValues: () => rows.slice(row - 3, row - 3 + rowCount)
        .map((source) => source.slice(column - 1, column - 1 + columnCount))
    })
  };
}

const duplicated = Array.from({ length: 314 }, () => Array(12).fill(''));
duplicated[228 - 3] = duplicateRow();
for (let row = 238; row <= 316; row++) duplicated[row - 3] = duplicateRow();
const duplicatedState = context.__stockRepairTest.crmStockCountingWriteoffState_({
  getSheetByName: (name) => name === 'Списання' ? writeoffSheet(duplicated) : null
});
assert.equal(duplicatedState.retainedRecord.row, 228,
  'the earliest confirmed WRT-0226 record is retained');
assert.equal(duplicatedState.duplicateRecords.length, 79,
  'exactly 79 accidental copies are selected for clearing');
const duplicateRuns = context.__stockRepairTest.crmStockCountingRuns_(duplicatedState.duplicateRecords);
assert.equal(duplicateRuns.length, 1, 'contiguous duplicate rows are cleared as one bounded run');
assert.equal(duplicateRuns[0].start, 238);
assert.equal(duplicateRuns[0].records.length, 79);

const cleanedRows = Array.from({ length: 226 }, () => Array(12).fill(''));
cleanedRows[228 - 3] = duplicateRow();
const cleanState = context.__stockRepairTest.crmStockCountingWriteoffState_({
  getSheetByName: (name) => name === 'Списання' ? writeoffSheet(cleanedRows) : null
});
assert.equal(cleanState.duplicateRecords.length, 0,
  'the write-off cleanup is idempotent after one confirmed record remains');

const wrong = duplicated.map((row) => row.slice());
wrong[316 - 3][5] = 9;
assert.throws(() => context.__stockRepairTest.crmStockCountingWriteoffState_({
  getSheetByName: (name) => name === 'Списання' ? writeoffSheet(wrong) : null
}), /не збігається/,
  'the repair refuses a near-match instead of clearing unverified data');

console.log('stock-counting-repair.test.mjs: ok');
