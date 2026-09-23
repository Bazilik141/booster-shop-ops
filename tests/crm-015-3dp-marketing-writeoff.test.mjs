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

test('one-time maintenance routes are absent from the final 3D sources', () => {
  assert.doesNotMatch(api, /3dp_sales_formula_repair|salesFormulaRepairPlan3dp_|salesFormulaRepairAction3dp_/);
  assert.doesNotMatch(finalPaste, /3dp_sales_formula_repair|salesFormulaRepairPlan3dp_|salesFormulaRepairAction3dp_/);
  assert.doesNotMatch(api, /3dp_crm015_reclassify|HtmlService\.createTemplateFromFile\('CRM-015'\)/);
  assert.doesNotMatch(finalPaste, /3dp_crm015_reclassify|HtmlService\.createTemplateFromFile\('CRM-015'\)/);
  assert.equal(fs.existsSync(path.join(root, '3d-print/apps-script-3dp-api/CRM-015.html')), false);
  assert.equal(fs.existsSync(path.join(root, 'work/CRM-015_3dp_Code_from_V34.gs')), false);
});

test('owner paste file keeps CRM-015 functions but excludes separate batch-draft changes', () => {
  for (const name of ['marketingPayoutFormula3dp_', 'assertMarketingPayoutSchema3dp_',
    'createPayoutAction3dp_', 'salesChannelValidationPlan3dp_', 'salesChannelValidationSync3dp_']) {
    assert.equal(functionSource(finalPaste, name), functionSource(api, name), name);
  }
  assert.doesNotMatch(finalPaste, /function batchDraftQuantity3dp_/);
  assert.doesNotMatch(finalPaste, /batchDraftStorageKey3dp_\(sku, actor, serhiyDraft, quantity\)/);
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

test('3D API routes permanent marketing FIFO to the gift ledger, not sales', () => {
  assert.match(api, /case '3dp_marketing_writeoff':\s*return fifo3dpMarketingWriteoffAction_/);
  const source = functionSource(fifo, 'fifo3dpMarketingWriteoffAction_');
  assert.match(source, /'marketing_gift'/);
  assert.match(source, /SHEETS_3DP\.plyushky/);
  assert.match(source, /purchaseCell\.setFormula\(purchaseFormula\)/);
  assert.doesNotMatch(source, /copyFormulaCells3dp_\(sales|sales\.getRange\(saleRow/);
  assert.match(fifo, /FIFO_MARKETING_WRITEOFF_COMMITTED/);
  assert.match(fifo, /marketing_expense: fifo3dpRound_\(quantity \* \(buyout \+ ownerFixturePerUnit \+ serhiyFixturePerUnit\), 2\)/);
  assert.match(fifo, /serhiy_accrual: fifo3dpRound_\(quantity \* \(buyout \+ serhiyFixturePerUnit\), 2\)/);
  assert.match(crm, /gift_row_3dp: remote\.gift_row/);
  assert.doesNotMatch(crm, /sale_row_3dp: remote\.sale_row/);
});

test('marketing replay rejects a deleted legacy sale instead of silently paying zero', () => {
  const source = functionSource(fifo, 'fifo3dpMarketingWriteoffAction_');
  assert.match(source, /LEGACY_MARKETING_PROJECTION_MISSING/);
  assert.match(source, /MARKETING_PROJECTION_MISMATCH/);
  assert.match(functionSource(fifo, 'fifo3dpReverseAction_'), /MARKETING_REVERSAL_REQUIRES_CRM/);
});

test('payout formula includes sales, gift buyouts and Serhiy-paid fixtures for the selected month', () => {
  const context = vm.createContext({});
  vm.runInContext(functionSource(api, 'marketingPayoutFormula3dp_') +
    '\nglobalThis.formula=marketingPayoutFormula3dp_(2);', context);
  assert.match(context.formula, /SUMIFS\('Продажі'!\$K:\$K;'Продажі'!\$S:\$S;A2\)/);
  assert.match(context.formula, /SUMIFS\('Маркетингові_плюшки'!\$E:\$E;/);
  assert.match(context.formula, /SUMIFS\('Маркетингові_плюшки'!\$I:\$I;/);
  assert.match(context.formula, /EDATE\(DATE\(VALUE\(LEFT\(A2;4\)\);VALUE\(RIGHT\(A2;2\)\);1\);1\)/);
  assert.match(functionSource(api, 'createPayoutAction3dp_'), /setFormula\(marketingPayoutFormula3dp_\(result\.row\)\)/);
  assert.match(fifo, /I: fifo3dpRound_\(quantity \* serhiyFixturePerUnit, 2\)/);
});

test('one-unit marketing commit separates buyout and Serhiy fixture accrual without a sale', () => {
  const cells = new Map();
  const key = (row, column) => `${row}:${column}`;
  const cell = (row, column) => ({
    getValue: () => cells.get(key(row, column))?.value ?? '',
    getFormula: () => cells.get(key(row, column))?.formula ?? '',
    setValue: value => { cells.set(key(row, column), { value }); },
    setFormula: formula => { cells.set(key(row, column), { formula }); },
    setNumberFormat: () => {},
  });
  const gifts = { getRange: (row, column) => cell(row, column) };
  const nomenclature = { getRange: (row, column) => ({ getValue: () => column === 18 ? 25 : '' }) };
  let batchCounters = [0, 2];
  const batches = { getRange: () => ({
    getValues: () => [batchCounters.slice()],
    setValues: rows => { batchCounters = rows[0].slice(); },
  }) };
  let allocationRow;
  const allocationSheet = { getLastRow: () => 1, getRange: () => ({ setValues: rows => { allocationRow = rows[0]; } }) };
  const context = vm.createContext({
    API_3DP: { timezone: 'Europe/Kiev' },
    SHEETS_3DP: { nomenclature: 'Номенклатура', plyushky: 'Маркетингові_плюшки', sales: 'Продажі' },
    CATALOG_FIFO_3DP: { allocationsSheet: 'allocations', batchesSheet: 'batches', allocationHeaders: Array(16) },
    Utilities: { formatDate: () => '2026-09-23T12:00:00+03:00' },
    assertOwner3dp_: () => {}, assertManualValue3dp_: () => {}, requiredSku3dp_: value => value,
    assertMarketingPayoutSchema3dp_: () => {},
    inventoryWholeNumber3dp_: value => Number(value), fifo3dpRequiredNumber_: value => Number(value),
    fifo3dpFingerprint_: () => 'fingerprint', fifo3dpFindOperation_: () => null,
    fifo3dpEnsureHiddenSheet_: () => ({ sheet: allocationSheet }),
    fifo3dpReconcileAction_: () => ({ clean: true }),
    getSheet3dp_: (_, name) => {
      if (name === 'Продажі') throw new Error('marketing touched sales');
      return name === 'Номенклатура' ? nomenclature : gifts;
    },
    resolveTargetRow3dp_: () => 2, assertNomenclatureActiveForOperation3dp_: () => {},
    columnToNumber3dp_: column => ({ R: 18, A: 1, B: 2, C: 3, D: 4, F: 6, H: 8, I: 9 })[column],
    isBlank3dp_: value => value === '' || value == null, number3dp_: Number,
    fifo3dpPlanAllocation_: () => ({ allocations: [{ row: 2, quantity: 1, batch_id: 'B1', cost_uah: 24.52 }],
      total_cost_uah: 24.52, unit_cost_uah: 24.52 }),
    fifo3dpBatchRows_: () => [], findFirstBusinessEmptyRow3dp_: () => 3,
    snapshotRange3dp_: () => ({}), canonicalFormula3dp_: value => value,
    copyFormulaCells3dp_: () => {}, appendAudit3dp_: () => {},
    apiError3dp_: code => Object.assign(new Error(code), { code }),
  });
  const ss = { getSheetByName: name => name === 'batches' ? batches : null };
  vm.runInContext(functionSource(fifo, 'fifo3dpRound_') + '\n' +
    functionSource(fifo, 'fifo3dpMarketingWriteoffAction_') +
    '\nglobalThis.commit=fifo3dpMarketingWriteoffAction_;', context);
  const result = context.commit(ss, { operation_id: 'marketing:CRM015-EXAMPLE1', sku: 'ACC-3D-PKM-110',
    quantity: 1, date: '2026-09-23', note: 'Подарунок до замовлення',
    owner_fixture_per_unit: 3, serhiy_fixture_per_unit: 2 }, { role: 'owner' });
  assert.equal(result.gift_row, 3);
  assert.equal(result.buyout_unit, 25);
  assert.equal(result.serhiy_accrual, 27);
  assert.equal(result.marketing_expense, 30);
  assert.equal(cells.get(key(3, 2)).value, 'ACC-3D-PKM-110');
  assert.equal(new Date(cells.get(key(3, 1)).value).toISOString(), '2026-09-23T12:00:00.000Z');
  assert.equal(cells.get(key(3, 3)).value, 1);
  assert.equal(cells.get(key(3, 4)).value, 25);
  assert.equal(cells.get(key(3, 6)).value, 1);
  assert.equal(cells.get(key(3, 9)).value, 2);
  assert.match(cells.get(key(3, 5)).formula, /C3\*D3/);
  assert.deepEqual(Array.from(batchCounters), [1, 1]);
  assert.equal(allocationRow[3], 'marketing_gift');
  assert.equal(allocationRow[14], 3);
});

test('sales channel dropdown preview is read-only and apply is fingerprint-gated', () => {
  const start = api.indexOf('const SALES_CHANNELS_CANONICAL_3DP');
  const end = api.indexOf('function salesDerivedFormulaMap3dp_', start);
  assert.ok(start > 0 && end > start);
  const oldOptions = ['Сайт', 'Директ', 'Instagram', 'Telegram', 'OLX'];
  const rule = options => ({
    getCriteriaType: () => 'VALUE_IN_LIST', getCriteriaValues: () => [options, true],
    getAllowInvalid: () => false, getHelpText: () => '',
  });
  let validations = [[rule(oldOptions)], [rule(oldOptions)], [rule(oldOptions)]];
  let values = [['OpenCart'], [''], ['']];
  let writes = 0;
  const range = {
    getDataValidations: () => validations,
    getDisplayValues: () => values,
    setDataValidation: next => { writes += 1; validations = validations.map(() => [next]); },
    setDataValidations: next => { validations = next; },
  };
  const sheet = {
    getMaxRows: () => 4,
    getRange: (...args) => args[0] === 'M1' ? { getDisplayValue: () => 'Канал' } : range,
  };
  const context = vm.createContext({
    SHEETS_3DP: { sales: 'Продажі' }, getSheet3dp_: () => sheet,
    SpreadsheetApp: {
      DataValidationCriteria: { VALUE_IN_LIST: 'VALUE_IN_LIST' },
      newDataValidation: () => {
        let selected = [];
        return {
          requireValueInList(next) { selected = next; return this; },
          setAllowInvalid() { return this; }, setHelpText() { return this; },
          build() { return rule(selected); },
        };
      },
    },
    assertOwner3dp_: () => {}, fifo3dpFingerprint_: value => JSON.stringify(value),
    appendAudit3dp_: () => {}, apiError3dp_: code => Object.assign(new Error(code), { code }),
  });
  vm.runInContext(api.slice(start, end) + '\nglobalThis.sync=salesChannelValidationSync3dp_;', context);
  const preview = context.sync({}, { apply: false }, {});
  assert.equal(writes, 0);
  assert.deepEqual(JSON.parse(JSON.stringify(preview.options.slice(0, 6))),
    ['OpenCart', 'Telegram', 'OLX', 'Monobazar', 'Вручну', 'Інше']);
  assert.match(dashboard, /const SALE_CHANNELS = \['OpenCart','Telegram','OLX','Monobazar','Вручну','Інше'\]/);
  assert.throws(() => context.sync({}, { apply: true, expected_fingerprint: 'stale' }, {}),
    error => error.code === 'STALE_SALES_CHANNEL_VALIDATION');
  assert.equal(writes, 0);
  const applied = context.sync({}, { apply: true, expected_fingerprint: preview.fingerprint }, {});
  assert.equal(applied.apply, true);
  assert.equal(writes, 1);
  assert.equal(context.sync({}, { apply: false }, {}).already_applied, true);
  values = [['Unexpected'], [''], ['']];
  const blocked = context.sync({}, { apply: false }, {});
  assert.equal(blocked.blocker_count, 1);
  assert.throws(() => context.sync({}, { apply: true, expected_fingerprint: blocked.fingerprint }, {}),
    error => error.code === 'SALES_CHANNEL_VALIDATION_BLOCKED');
  assert.equal(writes, 1);
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
