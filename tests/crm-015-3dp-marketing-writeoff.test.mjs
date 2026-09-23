import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..');
const api = fs.readFileSync(path.join(root, '3d-print/apps-script-3dp-api/Code.gs'), 'utf8');
const fifo = fs.readFileSync(path.join(root, '3d-print/apps-script-3dp-api/CatalogFifo.gs'), 'utf8');
const crm = fs.readFileSync(path.join(root, 'crm/apps-script/Code.gs'), 'utf8');
const dashboard = fs.readFileSync(path.join(root, 'dashboard/booster-dashboard.html'), 'utf8');
const finalPaste = fs.readFileSync(path.join(root, 'work/CRM-015_3dp_Code_final.gs'), 'utf8');

function functionSource(source, name) {
  const match = new RegExp('function\\s+' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*\\(').exec(source);
  if (!match) throw new Error('Missing function: ' + name);
  const open = source.indexOf('{', match.index);
  let depth = 0;
  for (let index = open; index < source.length; index += 1) {
    if (source[index] === '{') depth += 1;
    if (source[index] === '}' && --depth === 0) return source.slice(match.index, index + 1);
  }
  throw new Error('Unclosed function: ' + name);
}

test('sales append rejects incomplete technical rows across schema versions', () => {
  for (const [orderLineReady, legacyReady, expectedCode] of [
    [false, false, 'CRM_ROW_REQUIRED'],
    [false, true, 'CRM_ROW_REQUIRED'],
    [true, true, 'FROZEN_VALUES_REQUIRED'],
  ]) {
    const context = vm.createContext({
      SHEETS_3DP: { sales: 'Продажі', nomenclature: 'Номенклатура' },
      APPENDABLE_SHEETS_3DP: ['Продажі'],
      INTERNAL_FIFO_APPEND_MARKER_3DP: 'internal',
      SALES_FROZEN_COLUMNS_3DP: [],
      SALES_ORDER_LINE_REQUIRED_COLUMNS_3DP: ['A', 'T', 'U'],
      SALES_3DP015_REQUIRED_COLUMNS_3DP: ['A', 'T'],
      getSheet3dp_: () => ({}),
      resolveColumn3dp_: (_, column) => column,
      assertCellWriteAllowed3dp_: () => {},
      assertManualValue3dp_: () => {},
      is3dpOrderLineAccountingSchemaReady3dp_: () => orderLineReady,
      is3dp015SalesSchemaReady3dp_: () => legacyReady,
      apiError3dp_: code => Object.assign(new Error(code), { code }),
      findFirstBusinessEmptyRow3dp_: () => { throw new Error('row mutation reached'); },
    });
    vm.runInContext(functionSource(api, 'appendRowAction3dp_') + '\nglobalThis.append=appendRowAction3dp_;', context);
    assert.throws(
      () => context.append({}, { sheet: 'Продажі', internal_fifo_marker: 'internal', values: { A: '2026-09-22' } }, {}),
      error => error.code === expectedCode,
      `schema orderLineReady=${orderLineReady}, legacyReady=${legacyReady}`,
    );
  }
});

test('one-time formula repair is absent from the final 3D sources', () => {
  assert.doesNotMatch(api, /3dp_sales_formula_repair|salesFormulaRepairPlan3dp_|salesFormulaRepairAction3dp_/);
  assert.doesNotMatch(finalPaste, /3dp_sales_formula_repair|salesFormulaRepairPlan3dp_|salesFormulaRepairAction3dp_/);
  assert.equal(fs.existsSync(path.join(root, '3d-print/apps-script-3dp-api/CRM-015.html')), false);
  assert.equal(fs.existsSync(path.join(root, 'work/CRM-015_3dp_Code_from_V34.gs')), false);
});

test('sales derived formulas are row-local and include all six missing dashboard fields', () => {
  const names = ['salesProductNameFormula3dp_', 'salesOrderLineMarginFormula3dp_', 'salesStatusFormula3dp_',
    'salesOrderLineSerhiyAccrualFormula3dp_', 'salesOrderLineBoosterIncomeFormula3dp_', 'salesPeriodFormula3dp_',
    'salesDerivedFormulaMap3dp_'];
  const context = vm.createContext({});
  vm.runInContext(names.map(name => functionSource(api, name)).join('\n') + '\nglobalThis.formulas=salesDerivedFormulaMap3dp_(2);', context);
  const formulas = JSON.parse(JSON.stringify(context.formulas));
  assert.deepEqual(Object.keys(formulas), ['C', 'I', 'J', 'K', 'L', 'S']);
  assert.match(formulas.C, /Номенклатура.*B2/);
  assert.match(formulas.I, /X2="Маркетинг";0/);
  assert.match(formulas.K, /D2\*\(AA2\+Z2\)/);
  assert.match(formulas.L, /X2="Маркетинг";0/);
  assert.match(formulas.S, /LEFT\(A2;7\)/);
  const copySource = functionSource(api, 'copyFormulaCells3dp_');
  assert.match(copySource, /is3dp015SalesSchemaReady3dp_\(sheet\)/);
  assert.match(copySource, /ensureSalesDerivedFormulas3dp_\(sheet, row, true\)/);
  assert.match(copySource, /LEGACY_PRE_3DP015_SALES_FORMULA_COLUMNS_3DP/);
});

test('3D API routes permanent marketing FIFO', () => {
  assert.match(api, /case '3dp_marketing_writeoff':\s*return fifo3dpMarketingWriteoffAction_/);
  assert.match(fifo, /'marketing_writeoff'/);
  assert.match(fifo, /FIFO_MARKETING_WRITEOFF_COMMITTED/);
  assert.match(fifo, /marketing_expense: fifo3dpRound_\(quantity \* \(buyout \+ ownerFixturePerUnit \+ serhiyFixturePerUnit\), 2\)/);
  assert.match(fifo, /serhiy_accrual: fifo3dpRound_\(quantity \* \(buyout \+ serhiyFixturePerUnit\), 2\)/);
});

test('CRM orchestrator uses clean integrity gates, fixture FIFO, and an unlinked Marketing expense', () => {
  assert.match(crm, /action === 'add_3dp_marketing_writeoff'/);
  const source = functionSource(crm, 'apiAdd3dpMarketingWriteoff_');
  assert.match(source, /apiIntegrityCheck_\(\)/);
  assert.match(source, /crm015FixturePlan_/);
  assert.match(source, /action: '3dp_marketing_writeoff'/);
  assert.match(source, /category: 'Маркетинг'/);
  assert.match(source, /linked_to_sale: 'Ні'/);
  assert.match(functionSource(crm, 'crm015FixtureFifoCost_'), /FIFO_розхідники/);
  assert.match(functionSource(crm, 'crm015FixturePlan_'), /\[ARCHIVED\]/);
  assert.match(functionSource(crm, 'crm015FixturePlan_'), /Недостатньо фурнітури/);
});

test('fixture FIFO consumes older priced layers and excludes the retried request', () => {
  const fifoRows = [
    ['LOT-1', '2026-09-01', 'Кільце', 2, 10, 'opening', ''],
    ['LOT-2', '2026-09-10', 'Кільце', 3, 20, 'expense', ''],
  ];
  const ledgerRows = [
    ['FUR-1', '2026-09-05', 'Продаж', 'ORDER-1', 'Кільце', 'власник', 1, 10, 10, '', ''],
    ['FUR-2', '2026-09-11', 'CRM-015 Маркетинг', 'CRM015-RETRY1234', 'Кільце', 'власник', 3, 16.67, 50, '', ''],
  ];
  const makeSheet = rows => ({
    getLastRow: () => rows.length + 1,
    getRange: (row, column, count) => ({ getValues: () => rows.slice(row - 2, row - 2 + count) }),
  });
  const ss = { getSheetByName: name => name === 'FIFO_розхідники' ? makeSheet(fifoRows) : name === 'Використання_фурнітури' ? makeSheet(ledgerRows) : null };
  const context = vm.createContext({
    CRM_CONSUMABLE_FIFO_SHEET_: 'FIFO_розхідники', CRM_3DP019_FIXTURE_USAGE_SHEET_: 'Використання_фурнітури',
    num_: value => Number(value) || 0, round2_: value => Math.round(Number(value) * 100) / 100,
    dateSortValue_: value => new Date(value).getTime(), Error,
  });
  vm.runInContext(functionSource(crm, 'crm015FixtureFifoCost_') + '\nglobalThis.cost=crm015FixtureFifoCost_;', context);
  assert.equal(context.cost(ss, 'Кільце', 3, '2026-09-12', 'CRM015-RETRY1234'), 16.67);
  assert.throws(() => context.cost(ss, 'Кільце', 5, '2026-09-12', 'CRM015-NEW12345'), /не покриває/);
});

test('dashboard exposes standalone marketing writeoff without inventory-difference autofill', () => {
  assert.match(dashboard, /Маркетингове списання · CRM-015/);
  assert.match(dashboard, /action:'add_3dp_marketing_writeoff'/);
  assert.match(dashboard, /Для повтору не змінюй форму/);
  assert.doesNotMatch(functionSource(dashboard, 'saveThreeDpMarketingWriteoff_'), /expected_current|delta/);
  assert.match(functionSource(dashboard, 'saveThreeDpMarketingWriteoff_'), /quantity>threeDpNumber\(metrics\.availability\)/);
  assert.match(dashboard, /row\.className='line-item writeoff-line'/);
  assert.match(dashboard, /aria-label="Видалити фурнітуру/);
  assert.match(dashboard, /id="threeDpMarketingMessage"[^>]*aria-live="polite"/);
  assert.match(dashboard, /@media \(min-width: 801px\) and \(max-width: 1200px\)/);
  assert.match(dashboard, /@media \(max-width: 800px\)[\s\S]*\.line-item-grid,[\s\S]*grid-template-columns:1fr/);
});
