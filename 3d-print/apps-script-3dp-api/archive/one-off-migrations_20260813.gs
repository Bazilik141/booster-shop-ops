/*
 * One-off 3D-P migrations and repairs archived 2026-08-13.
 * NOT DEPLOYED: this repository file must never be pasted into the live Apps Script project.
 * Extracted from owner-reported 3D-P Web App version V22 (2026-08-13).
 * Restore any function only after a fresh schema and live-state review; the original targets may have moved.
 */

function repair3dpAvailabilityFormulas() {
  return withScriptLock3dp_(function () {
    const spreadsheet = getSpreadsheet3dp_();
    validateSetupAnchors3dp_(spreadsheet);
    const changes = [];
    setupAvailabilityArchiveAwareFormulas3dp_(spreadsheet, changes);
    return { ok: true, already_applied: changes.length === 0, changes: changes };
  });
}

function preview3dpApiAddendum2() {
  const spreadsheet = getSpreadsheet3dp_();
  validateSetupAnchors3dp_(spreadsheet);
  validateAddendum2Prerequisites3dp_(spreadsheet);
  validateNomenclatureArchiveState3dp_(spreadsheet);
  return {
    ok: true,
    spreadsheet_id: spreadsheet.getId(),
    planned_changes: [
      'Налаштування!B2:B5 becomes owner-writable only through guarded 3dp_write',
      'Номенклатура: preserve legacy F business status; add O:P technical Активний/Архів archive state/history with audit',
      '_Чернетки_партій: create and hide keyed raw batch-draft storage for five calculator inputs',
      '_Коригування_наявності: create and hide append-only stock-adjustment ledger with reason and Kyiv timestamp',
      'Наявність!G: add the stock-adjustment ledger to the existing formula; no formula cell is overwritten by the API',
    ],
  };
}

/**
 * Owner-run, idempotent Addendum #2 setup. It changes only the approved
 * 3D-P Sheet extension schema after the read-only preview passes.
 */
function setup3dpApiAddendum2() {
  return withScriptLock3dp_(function () {
    return setup3dpApiAddendum2Unlocked3dp_(getSpreadsheet3dp_());
  });
}

/**
 * Setup body shared by the bound-editor entry point and the owner API route.
 * Its caller must already hold the script lock.
 */
function setup3dpApiAddendum2Unlocked3dp_(spreadsheet) {
  validateSetupAnchors3dp_(spreadsheet);
  validateAddendum2Prerequisites3dp_(spreadsheet);
  validateNomenclatureArchiveState3dp_(spreadsheet);
  const changes = [];
  setupNomenclatureArchiveSystem3dp_(spreadsheet, changes);
  setupBatchDraftStorage3dp_(spreadsheet, changes);
  setupStockAdjustmentLedger3dp_(spreadsheet, changes);
  setupAvailabilityStockAdjustmentFormula3dp_(spreadsheet, changes);
  return { ok: true, already_applied: changes.length === 0, changes: changes };
}

function setupAddendum2Action3dp_(spreadsheet, actor) {
  assertOwner3dp_(actor, 'Caller may not run Addendum #2 setup.');
  return setup3dpApiAddendum2Unlocked3dp_(spreadsheet);
}

function normalizeNomenclatureFinalCostFormula3dp_(sheet, lastRow, changes) {
  let changed = false;
  for (let row = 2; row <= lastRow; row += 1) {
    const range = sheet.getRange(row, 11);
    const expected = nomenclatureFinalCostFormula3dp_(row, false);
    if (canonicalFormula3dp_(range.getFormula()) !== canonicalFormula3dp_(expected)) {
      range.setFormula(expected);
      changed = true;
    }
  }
  if (changed) changes.push('Номенклатура!K2:K' + lastRow + ' normalized to the current production-cost formula with planned defect-rate uplift; fixture N remains a separate reference price');
}

function salesMarginFormula3dp_(row, includeOwnerFixture) {
  const fixture = includeOwnerFixture ? '-IF(W' + row + '="власник";V' + row + ';0)' : '';
  return '=IF(OR(B' + row + '="";E' + row + '="");"";E' + row + '-F' + row + '-G' + row + fixture + ')';
}

function salesSerhiyAccrualFormula3dp_(row, includeSerhiyFixture) {
  const fixture = includeSerhiyFixture ? '+IF(W' + row + '="Сергій";V' + row + ';0)' : '';
  return '=IF(OR(I' + row + '="";I' + row + '<0);"див. Статус";D' + row + '*(F' + row + '+H' + row + '*I' + row + fixture + '))';
}

function salesBoosterIncomeFormula3dp_(row, includeSerhiyFixture) {
  const body = includeSerhiyFixture
    ? 'D' + row + '*(I' + row + '*(1-H' + row + ')-IF(W' + row + '="Сергій";V' + row + ';0))'
    : 'D' + row + '*I' + row + '*(1-H' + row + ')';
  return '=IF(OR(I' + row + '="";I' + row + '<0);"див. Статус";' + body + ')';
}

function rebuild3dp015Analytics3dp_(sheet, nomenclature, nomenclatureRows, changes) {
  const headerRange = sheet.getRange('A3:N3');
  const headersChanged = JSON.stringify(headerRange.getDisplayValues()[0]) !== JSON.stringify(PRICE_MODEL_COLUMNS_3DP.analytics);
  const ownerSplitBySku = headersChanged ? {} : analyticsOwnerSplitBySku3dp_(sheet, nomenclature);
  let changed = false;
  if (headersChanged) {
    headerRange.setValues([PRICE_MODEL_COLUMNS_3DP.analytics]);
    changed = true;
  }

  nomenclatureRows.forEach(function (sourceRow, index) {
    const row = index + 4;
    const sourceSku = String(nomenclature.getRange(sourceRow, 1).getDisplayValue() || '').trim();
    if (analytics3dp015RowMatches3dp_(sheet, row, sourceRow)) return;
    const target = sheet.getRange(row, 1, 1, 14);
    target.clearContent();
    analytics3dp015FormulaEntries3dp_(row, sourceRow).forEach(function (entry) {
      sheet.getRange(row, entry.column).setFormula(entry.formula);
    });
    const split = Object.prototype.hasOwnProperty.call(ownerSplitBySku, sourceSku) ? ownerSplitBySku[sourceSku] : 0.5;
    sheet.getRange(row, 6).setValue(split);
    changed = true;
  });

  for (let row = nomenclatureRows.length + 4; row <= 17; row += 1) {
    if (!analytics3dp015RowHasContent3dp_(sheet, row)) continue;
    sheet.getRange(row, 1, 1, 14).clearContent();
    changed = true;
  }
  if (changed) changes.push('Аналітика!A3:N17 synchronized to current Номенклатура SKU rows; stale rows and legacy #REF!/three-scenario content removed');
}

function analytics3dp015FormulaEntries3dp_(row, sourceRow) {
  return [
    { column: 1, formula: '=\'Номенклатура\'!A' + sourceRow },
    { column: 2, formula: '=IF(A' + row + '="";"";\'Номенклатура\'!B' + sourceRow + ')' },
    { column: 3, formula: '=IF(A' + row + '="";"";\'Номенклатура\'!K' + sourceRow + ')' },
    { column: 4, formula: '=IF(A' + row + '="";"";N(\'Номенклатура\'!N' + sourceRow + '))' },
    { column: 5, formula: '=IF(A' + row + '="";"";\'Номенклатура\'!G' + sourceRow + ')' },
    { column: 7, formula: '=IF(A' + row + '="";"";\'Номенклатура\'!Q' + sourceRow + ')' },
    { column: 8, formula: '=IF(A' + row + '="";"";"pending")' },
    { column: 9, formula: '=IF(OR(A' + row + '="";NOT(ISNUMBER(C' + row + '));NOT(ISNUMBER(G' + row + ')));"";IF(G' + row + '-C' + row + '-N(D' + row + ')<0;"збиток";(G' + row + '-C' + row + '-N(D' + row + '))*(1-F' + row + ')))' },
    { column: 10, formula: '=IF(OR(NOT(ISNUMBER(I' + row + '));NOT(ISNUMBER(G' + row + '));G' + row + '=0);"";I' + row + '/G' + row + ')' },
    { column: 11, formula: '=IF(OR(A' + row + '="";NOT(ISNUMBER(C' + row + '));NOT(ISNUMBER(G' + row + ')));"";IF(G' + row + '-C' + row + '-N(D' + row + ')<0;"збиток";C' + row + '+F' + row + '*(G' + row + '-C' + row + '-N(D' + row + '))))' },
    { column: 12, formula: '=IF(OR(E' + row + '="";E' + row + '=0;K' + row + '="");"";IF(ISNUMBER(K' + row + ');K' + row + '/E' + row + ';"збиток"))' },
  ];
}

function analytics3dp015RowMatches3dp_(sheet, row, sourceRow) {
  return analytics3dp015FormulaEntries3dp_(row, sourceRow).every(function (entry) {
    return canonicalFormula3dp_(sheet.getRange(row, entry.column).getFormula()) === canonicalFormula3dp_(entry.formula);
  });
}

function analytics3dp015RowHasContent3dp_(sheet, row) {
  const range = sheet.getRange(row, 1, 1, 14);
  return range.getValues()[0].some(function (value) { return !isBlank3dp_(value); }) ||
    range.getFormulas()[0].some(function (formula) { return Boolean(formula); });
}

function analyticsOwnerSplitBySku3dp_(sheet, nomenclature) {
  const result = {};
  for (let row = 4; row <= 17; row += 1) {
    const source = sheet.getRange(row, 1);
    let sku = String(source.getDisplayValue() || '').trim();
    const reference = /^='Номенклатура'!A(\d+)$/.exec(String(source.getFormula() || ''));
    if ((!sku || sku === '#REF!') && reference) {
      sku = String(nomenclature.getRange(Number(reference[1]), 1).getDisplayValue() || '').trim();
    }
    if (sku && sku !== '#REF!') result[sku] = sheet.getRange(row, 6).getValue();
  }
  return result;
}

function ensure3dp015AnalyticsFixtureNote3dp_(sheet, changes) {
  const note = 'Планування: Номенклатура!N у цій колонці припускає, що фурнітуру оплатив власник. Платник зберігається лише в Продажі!W, тому цей блок не моделює відшкодування Сергію за фурнітуру.';
  const range = sheet.getRange('D3');
  if (range.getNote() === note) return;
  range.setNote(note);
  changes.push('Аналітика!D3 documents the owner-paid fixture planning assumption; sale-row payer remains Продажі!W');
}

function setup3dp015NomenclatureHeaders3dp_(sheet, changes) {
  if (sheet.getRange('N1').getDisplayValue() !== API_3DP.fixtureReferenceHeader) {
    sheet.getRange('N1').setValue(API_3DP.fixtureReferenceHeader);
    changes.push('Номенклатура!N1 relabeled as a fixture reference price');
  }
  Object.keys(PRICE_MODEL_COLUMNS_3DP.nomenclature).forEach(function (column) {
    const range = sheet.getRange(column + '1');
    if (range.getDisplayValue() !== PRICE_MODEL_COLUMNS_3DP.nomenclature[column]) {
      sheet.getRange('P1').copyTo(range, SpreadsheetApp.CopyPasteType.PASTE_FORMAT, false);
      range.setValue(PRICE_MODEL_COLUMNS_3DP.nomenclature[column]);
      changes.push('Номенклатура!' + column + '1 added');
    }
  });
}

function setup3dp015SalesHeaders3dp_(sheet, lastRow, changes) {
  Object.keys(PRICE_MODEL_COLUMNS_3DP.sales).forEach(function (column) {
    const range = sheet.getRange(column + '1');
    if (range.getDisplayValue() !== PRICE_MODEL_COLUMNS_3DP.sales[column]) {
      sheet.getRange('T1').copyTo(range, SpreadsheetApp.CopyPasteType.PASTE_FORMAT, false);
      range.setValue(PRICE_MODEL_COLUMNS_3DP.sales[column]);
      changes.push('Продажі!' + column + '1 added');
    }
  });
  if (lastRow < 2) throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Продажі requires a prepared data row.');
}

function normalize3dp015SalesFinancialFormulas3dp_(sheet, lastRow, changes) {
  const changed = [];
  for (let row = 2; row <= lastRow; row += 1) {
    [
      { column: 9, label: 'I', formula: is3dpOrderLineAccountingSchemaReady3dp_(sheet) ? salesOrderLineMarginFormula3dp_(row) : salesMarginFormula3dp_(row, true) },
      { column: 11, label: 'K', formula: is3dpOrderLineAccountingSchemaReady3dp_(sheet) ? salesOrderLineSerhiyAccrualFormula3dp_(row) : salesSerhiyAccrualFormula3dp_(row, true) },
      { column: 12, label: 'L', formula: is3dpOrderLineAccountingSchemaReady3dp_(sheet) ? salesOrderLineBoosterIncomeFormula3dp_(row) : salesBoosterIncomeFormula3dp_(row, true) },
    ].forEach(function (expected) {
      const range = sheet.getRange(row, expected.column);
      if (canonicalFormula3dp_(range.getFormula()) !== canonicalFormula3dp_(expected.formula)) {
        range.setFormula(expected.formula);
        changed.push(expected.label);
      }
    });
  }
  if (changed.length) {
    changes.push('Продажі!I/K/L now keep production cost F separate, subtract owner-paid fixture V once, and reimburse Serhiy-paid V as a separate accrual');
  }
}

function validate3dp015Schema3dp_(spreadsheet) {
  const settings = getSheet3dp_(spreadsheet, SHEETS_3DP.settings);
  const nomenclature = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
  const printLog = getSheet3dp_(spreadsheet, SHEETS_3DP.printLog);
  const sales = getSheet3dp_(spreadsheet, SHEETS_3DP.sales);
  const analytics = getSheet3dp_(spreadsheet, SHEETS_3DP.analytics);
  const legacyFixtureHeader = 'Фурнітура (ланцюжок/карабін), грн/шт';
  const currentFixtureHeader = String(nomenclature.getRange('N1').getDisplayValue() || '');
  if ([legacyFixtureHeader, API_3DP.fixtureReferenceHeader].indexOf(currentFixtureHeader) === -1) {
    throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Номенклатура!N1 is not the approved fixture reference header.');
  }
  Object.keys(PRICE_MODEL_COLUMNS_3DP.nomenclature).forEach(function (column) {
    const current = String(nomenclature.getRange(column + '1').getDisplayValue() || '');
    if (current && current !== PRICE_MODEL_COLUMNS_3DP.nomenclature[column]) {
      throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Номенклатура!' + column + '1 is occupied by an unexpected header.');
    }
  });

  const nomenclatureFormulaLastRow = Math.max(findLastFormulaRow3dp_(nomenclature, 'K'), 2);
  for (let row = 2; row <= nomenclatureFormulaLastRow; row += 1) {
    const range = nomenclature.getRange(row, 11);
    const current = canonicalFormula3dp_(range.getFormula());
    if (!current && isBlank3dp_(range.getValue())) continue;
    const legacy = canonicalFormula3dp_(nomenclatureFinalCostFormula3dp_(row, true, false));
    const preFix2 = canonicalFormula3dp_(nomenclatureFinalCostFormula3dp_(row, false, false));
    const approved = canonicalFormula3dp_(nomenclatureFinalCostFormula3dp_(row, false, true));
    if (current !== legacy && current !== preFix2 && current !== approved) {
      throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Номенклатура!K' + row + ' differs from the approved cost formula.');
    }
  }

  if (sales.getRange('T1').getDisplayValue() !== API_3DP.salesCrmRowHeader) {
    throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Продажі!T1 must remain CRM row number.');
  }
  const salesLastRow = Math.max(sales.getLastRow(), 2);
  Object.keys(PRICE_MODEL_COLUMNS_3DP.sales).forEach(function (column) {
    const header = String(sales.getRange(column + '1').getDisplayValue() || '');
    if (header && header !== PRICE_MODEL_COLUMNS_3DP.sales[column]) {
      throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Продажі!' + column + '1 is occupied by an unexpected header.');
    }
    if (!header) {
      const range = sales.getRange(2, columnToNumber3dp_(column), salesLastRow - 1, 1);
      const occupied = range.getValues().some(function (values) { return !isBlank3dp_(values[0]); }) ||
        range.getFormulas().some(function (formulas) { return Boolean(formulas[0]); });
      if (occupied) throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Продажі!' + column + ' has data before its approved header.');
    }
  });
  const salesMarginLastRow = Math.max(findLastFormulaRow3dp_(sales, 'I'), 2);
  for (let row = 2; row <= salesMarginLastRow; row += 1) {
    const formulas = [
      { column: 9, label: 'I', legacy: salesMarginFormula3dp_(row, false), approved: salesMarginFormula3dp_(row, true), line: salesOrderLineMarginFormula3dp_(row) },
      { column: 11, label: 'K', legacy: salesSerhiyAccrualFormula3dp_(row, false), approved: salesSerhiyAccrualFormula3dp_(row, true), line: salesOrderLineSerhiyAccrualFormula3dp_(row) },
      { column: 12, label: 'L', legacy: salesBoosterIncomeFormula3dp_(row, false), approved: salesBoosterIncomeFormula3dp_(row, true), line: salesOrderLineBoosterIncomeFormula3dp_(row) },
    ];
    formulas.forEach(function (formula) {
      const range = sales.getRange(row, formula.column);
      const current = canonicalFormula3dp_(range.getFormula());
      if (!current && isBlank3dp_(range.getValue())) return;
      const legacy = canonicalFormula3dp_(formula.legacy);
      const approved = canonicalFormula3dp_(formula.approved);
      const line = canonicalFormula3dp_(formula.line);
      if (current !== legacy && current !== approved && current !== line) {
        throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Продажі!' + formula.label + row + ' differs from the approved financial formula.');
      }
    });
  }

  const legacyAnalyticsHeaders = [
    'SKU', 'Назва', 'Собівартість Сергія, грн', 'Витрати BoosterShop, грн', 'Час друку, год', '% прибутку Сергію',
    'Ціна Консервативна', 'Ціна Середня', 'Ціна Оптимістична', 'Маржа BoosterShop Консерв, грн',
    'Маржа BoosterShop Середня, грн', 'Маржа BoosterShop Оптим, грн', 'Нараховано Сергію (Середня), грн',
    'Прибуток Сергію/год друку (Середня), грн',
  ];
  const analyticsHeaders = analytics.getRange('A3:N3').getDisplayValues()[0];
  if (JSON.stringify(analyticsHeaders) !== JSON.stringify(legacyAnalyticsHeaders) &&
      JSON.stringify(analyticsHeaders) !== JSON.stringify(PRICE_MODEL_COLUMNS_3DP.analytics)) {
    throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Аналітика!A3:N3 does not match the approved legacy or 3D-P-015 calculator.');
  }

  const nomenclatureRows = [];
  const rows = nomenclature.getRange(2, 1, Math.max(nomenclature.getLastRow() - 1, 1), 1).getValues();
  rows.forEach(function (values, index) {
    const sku = String(values[0] || '').trim();
    if (sku && !isPlaceholderSku3dp_(sku)) nomenclatureRows.push(index + 2);
  });
  if (nomenclatureRows.length > 14) {
    throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Аналітика A4:A17 has room for 14 SKU rows; expand it explicitly before migration.');
  }
  return {
    settings: settings,
    nomenclature: nomenclature,
    printLog: printLog,
    sales: sales,
    analytics: analytics,
    nomenclatureFormulaLastRow: nomenclatureFormulaLastRow,
    salesLastRow: salesLastRow,
    salesMarginLastRow: salesMarginLastRow,
    nomenclatureRows: nomenclatureRows,
  };
}

function setup3dp015Action3dp_(spreadsheet, actor) {
  assertOwner3dp_(actor, 'Caller may not run 3D-P-015 setup.');
  const plan = validate3dp015Schema3dp_(spreadsheet);
  const snapshots = [
    snapshotRange3dp_(plan.settings, 'A1:C5'),
    snapshotRange3dp_(plan.nomenclature, 'N1'),
    snapshotRange3dp_(plan.nomenclature, 'Q1:S1'),
    snapshotRange3dp_(plan.nomenclature, 'K2:K' + plan.nomenclatureFormulaLastRow),
    snapshotRange3dp_(plan.sales, 'U1:W' + plan.salesLastRow),
    snapshotRange3dp_(plan.sales, 'I2:L' + plan.salesMarginLastRow),
    snapshotRange3dp_(plan.analytics, 'A3:N17'),
  ];
  const changes = [];

  try {
    setupGlobalSettings3dp_(spreadsheet, changes);
    setup3dp015NomenclatureHeaders3dp_(plan.nomenclature, changes);
    normalizeNomenclatureFinalCostFormula3dp_(plan.nomenclature, plan.nomenclatureFormulaLastRow, changes);
    setup3dp015SalesHeaders3dp_(plan.sales, plan.salesLastRow, changes);
    normalize3dp015SalesFinancialFormulas3dp_(plan.sales, plan.salesMarginLastRow, changes);
    rebuild3dp015Analytics3dp_(plan.analytics, plan.nomenclature, plan.nomenclatureRows, changes);
    ensure3dp015AnalyticsFixtureNote3dp_(plan.analytics, changes);
    if (changes.length) {
      appendAudit3dp_(spreadsheet, actor, 'SETUP_3DP015', 'schema', 'price-model', {}, {
        nomenclature_columns: ['Q', 'R', 'S'],
        sales_columns: ['U', 'V', 'W'],
        changes: changes,
      }, '3D-P-015 price-model migration; live preflight captured before execution.');
    }
  } catch (error) {
    snapshots.reverse().forEach(restoreRange3dp_);
    throw error;
  }

  return { action: '3dp_setup_3dp015', ok: true, already_applied: changes.length === 0, changes: changes };
}

function setup3dp024Action3dp_(spreadsheet, actor) {
  assertOwner3dp_(actor, 'Caller may not run 3D-P-024 setup.');
  const plan = validate3dp015Schema3dp_(spreadsheet);
  const snapshots = [
    snapshotRange3dp_(plan.nomenclature, 'G1:G' + Math.max(plan.nomenclature.getLastRow(), 2)),
    snapshotRange3dp_(plan.nomenclature, 'K2:K' + plan.nomenclatureFormulaLastRow),
    snapshotRange3dp_(plan.printLog, 'D1:D' + Math.max(plan.printLog.getLastRow(), 2)),
    snapshotRange3dp_(plan.sales, 'I2:L' + plan.salesMarginLastRow),
    snapshotRange3dp_(plan.analytics, 'A1'),
  ];
  const changes = [];
  try {
    setup3dp024PrintTimeEntry3dp_(plan.nomenclature, plan.printLog, changes);
    normalizeNomenclatureFinalCostFormula3dp_(plan.nomenclature, plan.nomenclatureFormulaLastRow, changes);
    normalize3dp015SalesFinancialFormulas3dp_(plan.sales, plan.salesMarginLastRow, changes);
    update3dp024AnalyticsTitle3dp_(plan.analytics, changes);
    if (changes.length) {
      appendAudit3dp_(spreadsheet, actor, 'SETUP_3DP024', 'schema', 'print-time-entry', {}, { changes: changes },
        '3D-P-024 print-time entry safety setup; warnings are fail-open.');
    }
  } catch (error) {
    snapshots.reverse().forEach(restoreRange3dp_);
    throw error;
  }
  return { action: '3dp_setup_3dp024', ok: true, already_applied: changes.length === 0, changes: changes };
}

function setup3dp024PrintTimeEntry3dp_(nomenclature, printLog, changes) {
  [
    { sheet: nomenclature, target: PRINT_TIME_ENTRY_3DP.nomenclature, a1: 'G1' },
    { sheet: printLog, target: PRINT_TIME_ENTRY_3DP.printLog, a1: 'D1' },
  ].forEach(function (entry) {
    const range = entry.sheet.getRange(entry.a1);
    if (String(range.getDisplayValue() || '') !== entry.target.header) {
      throw apiError3dp_('SETUP_ANCHOR_MISMATCH', entry.target.sheet + '!' + entry.a1 + ' must remain ' + entry.target.header + '.');
    }
    if (range.getNote() !== PRINT_TIME_ENTRY_3DP.headerNote) {
      range.setNote(PRINT_TIME_ENTRY_3DP.headerNote);
      changes.push(entry.target.sheet + '!' + entry.a1 + ' documents decimal-hour entry and accepted human formats');
    }
    const lastRow = Math.max(entry.sheet.getLastRow(), 2);
    const dataRange = entry.sheet.getRange(2, entry.target.column, lastRow - 1, 1);
    const formats = dataRange.getNumberFormats();
    if (formats.some(function (row) { return String(row[0] || '') !== PRINT_TIME_ENTRY_3DP.numberFormat; })) {
      dataRange.setNumberFormat(PRINT_TIME_ENTRY_3DP.numberFormat);
      const columnLetter = String(entry.a1 || '').replace(/[0-9]+$/, '');
      changes.push(entry.target.sheet + '!' + columnLetter + '2:' + columnLetter + lastRow + ' normalized to decimal-hour number format');
    }
  });
}

function update3dp024AnalyticsTitle3dp_(sheet, changes) {
  const legacy = 'Маржа-калькулятор по SKU (цінові сценарії, формула 50/50 після повернення собівартості)';
  const approved = 'Маржа-калькулятор по SKU (фактична РРЦ, формула 50/50 після повернення собівартості)';
  const range = sheet.getRange('A1');
  const current = String(range.getDisplayValue() || '');
  if (current === approved) return;
  if (current !== legacy) throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Аналітика!A1 is not the approved stale three-scenario title.');
  range.setValue(approved);
  changes.push('Аналітика!A1 relabeled for the actual-RRP model; row 18 market references are untouched');
}

function validate3dpOrderLineAccountingSchema3dp_(sales) {
  if (!is3dp015SalesSchemaReady3dp_(sales)) {
    throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Run 3D-P-015 setup before order-line accounting setup.');
  }
  const lastRow = Math.max(sales.getLastRow(), 2);
  const maxColumns = sales.getMaxColumns();
  Object.keys(ORDER_LINE_ACCOUNTING_COLUMNS_3DP).forEach(function (column) {
    const columnNumber = columnToNumber3dp_(column);
    if (columnNumber > maxColumns) return;
    const header = String(sales.getRange(column + '1').getDisplayValue() || '');
    if (header && header !== ORDER_LINE_ACCOUNTING_COLUMNS_3DP[column]) {
      throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Продажі!' + column + '1 is occupied by an unexpected header.');
    }
    if (!header) {
      const range = sales.getRange(2, columnToNumber3dp_(column), lastRow - 1, 1);
      const occupied = range.getValues().some(function (row) { return !isBlank3dp_(row[0]); }) ||
        range.getFormulas().some(function (row) { return Boolean(row[0]); });
      if (occupied) throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Продажі!' + column + ' has data before its approved header.');
    }
  });
  return { lastRow: lastRow };
}

function setup3dpOrderLineAccountingAction3dp_(spreadsheet, actor) {
  assertOwner3dp_(actor, 'Caller may not run order-line accounting setup.');
  const sales = getSheet3dp_(spreadsheet, SHEETS_3DP.sales);
  const plan = validate3dpOrderLineAccountingSchema3dp_(sales);
  const availability = getSheet3dp_(spreadsheet, SHEETS_3DP.availability);
  const availabilityLastRow = Math.max(findLastFormulaRow3dp_(availability, 'E'), findLastFormulaRow3dp_(availability, 'F'), 2);
  const availabilitySnapshot = snapshotRange3dp_(availability, 'E1:F' + availabilityLastRow);
  const originalMaxColumns = sales.getMaxColumns();
  const requiredMaxColumns = columnToNumber3dp_('AA');
  let columnsAdded = 0;
  let salesSnapshot = null;
  const changes = [];
  try {
    if (originalMaxColumns < requiredMaxColumns) {
      columnsAdded = requiredMaxColumns - originalMaxColumns;
      sales.insertColumnsAfter(originalMaxColumns, columnsAdded);
      changes.push('Продажі grid expanded by ' + columnsAdded + ' column(s) through AA');
    }
    salesSnapshot = snapshotRange3dp_(sales, 'I1:AA' + plan.lastRow);
    Object.keys(ORDER_LINE_ACCOUNTING_COLUMNS_3DP).forEach(function (column) {
      const range = sales.getRange(column + '1');
      if (range.getDisplayValue() !== ORDER_LINE_ACCOUNTING_COLUMNS_3DP[column]) {
        sales.getRange('W1').copyTo(range, SpreadsheetApp.CopyPasteType.PASTE_FORMAT, false);
        range.setValue(ORDER_LINE_ACCOUNTING_COLUMNS_3DP[column]);
        changes.push('Продажі!' + column + '1 added');
      }
    });
    let migrated = 0;
    for (let row = 2; row <= plan.lastRow; row += 1) {
      const crmRow = Number(sales.getRange(row, columnToNumber3dp_('T')).getValue());
      const sku = String(sales.getRange(row, 2).getValue() || '').trim();
      if (!sku && !crmRow) continue;
      const modeCell = sales.getRange(row, columnToNumber3dp_('X'));
      const ownerCell = sales.getRange(row, columnToNumber3dp_('Y'));
      const serhiyCell = sales.getRange(row, columnToNumber3dp_('Z'));
      const buyoutCell = sales.getRange(row, columnToNumber3dp_('AA'));
      let rowChanged = false;
      if (isBlank3dp_(modeCell.getValue())) { modeCell.setValue('Продаж'); rowChanged = true; }
      const legacyFixture = Math.max(0, Number(sales.getRange(row, columnToNumber3dp_('V')).getValue()) || 0);
      const legacyPayer = String(sales.getRange(row, columnToNumber3dp_('W')).getValue() || '').trim();
      if (isBlank3dp_(ownerCell.getValue())) { ownerCell.setValue(legacyPayer === 'власник' ? legacyFixture : 0); rowChanged = true; }
      if (isBlank3dp_(serhiyCell.getValue())) { serhiyCell.setValue(legacyPayer === 'Сергій' ? legacyFixture : 0); rowChanged = true; }
      if (isBlank3dp_(buyoutCell.getValue())) { buyoutCell.setValue(0); rowChanged = true; }
      [
        { column: 9, formula: salesOrderLineMarginFormula3dp_(row) },
        { column: 11, formula: salesOrderLineSerhiyAccrualFormula3dp_(row) },
        { column: 12, formula: salesOrderLineBoosterIncomeFormula3dp_(row) },
      ].forEach(function (entry) {
        const cell = sales.getRange(row, entry.column);
        if (canonicalFormula3dp_(cell.getFormula()) !== canonicalFormula3dp_(entry.formula)) {
          cell.setFormula(entry.formula);
          rowChanged = true;
        }
      });
      if (rowChanged) migrated++;
    }
    if (migrated) changes.push('Продажі existing rows migrated to explicit per-line accounting: ' + migrated);
    let availabilityChanged = false;
    for (let row = 2; row <= availabilityLastRow; row += 1) {
      const expectedE = '=IF(A' + row + '="";"";SUMIFS(Продажі!$D:$D;Продажі!$B:$B;A' + row + ';Продажі!$X:$X;"<>Маркетинг"))';
      const expectedF = '=IF(A' + row + '="";"";SUMIF(Маркетингові_плюшки!$B:$B;A' + row + ';Маркетингові_плюшки!$F:$F)+SUMIFS(Продажі!$D:$D;Продажі!$B:$B;A' + row + ';Продажі!$X:$X;"Маркетинг"))';
      const cellE = availability.getRange(row, 5);
      const cellF = availability.getRange(row, 6);
      if (canonicalFormula3dp_(cellE.getFormula()) !== canonicalFormula3dp_(expectedE)) {
        cellE.setFormula(expectedE);
        availabilityChanged = true;
      }
      if (canonicalFormula3dp_(cellF.getFormula()) !== canonicalFormula3dp_(expectedF)) {
        cellF.setFormula(expectedF);
        availabilityChanged = true;
      }
    }
    if (availabilityChanged) changes.push('Наявність!E:F split CRM rows between Продаж and Маркетинг without changing total stock');
    if (changes.length) appendAudit3dp_(spreadsheet, actor, 'SETUP_ORDER_LINE_ACCOUNTING', 'schema', 'Продажі!X:AA', {}, { changes: changes }, 'Per-line CRM sale/marketing accounting setup.');
  } catch (error) {
    restoreRange3dp_(availabilitySnapshot);
    if (salesSnapshot) restoreRange3dp_(salesSnapshot);
    if (columnsAdded) sales.deleteColumns(originalMaxColumns + 1, columnsAdded);
    throw error;
  }
  return { action: '3dp_setup_order_line_accounting', ok: true, already_applied: changes.length === 0, columns_added: columnsAdded, changes: changes };
}

function setup3dp010Action3dp_(spreadsheet, actor) {
  assertOwner3dp_(actor, 'Caller may not run 3D-P-010 setup.');
  const sheet = getSheet3dp_(spreadsheet, SHEETS_3DP.sales);
  const headerRange = sheet.getRange('T1');
  const currentHeader = String(headerRange.getDisplayValue() || '').trim();
  if (currentHeader && currentHeader !== API_3DP.salesCrmRowHeader) {
    throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Продажі!T1 is occupied by an unexpected header.');
  }
  const lastRow = Math.max(sheet.getLastRow(), 2);
  const values = sheet.getRange(2, 20, lastRow - 1, 1).getValues();
  const formulas = sheet.getRange(2, 20, lastRow - 1, 1).getFormulas();
  for (let index = 0; index < values.length; index += 1) {
    if (!isBlank3dp_(values[index][0]) || formulas[index][0]) {
      throw apiError3dp_('T_NOT_EMPTY', 'Продажі!T must be empty before 3D-P-010 setup.');
    }
  }
  if (!currentHeader) headerRange.setValue(API_3DP.salesCrmRowHeader);
  return { action: '3dp_setup_3dp010', ok: true, already_applied: Boolean(currentHeader), sheet: SHEETS_3DP.sales,
    column: 'T', header: API_3DP.salesCrmRowHeader, rows_checked: lastRow - 1 };
}

function setup3dp010() {
  return withScriptLock3dp_(function () {
    return setup3dp010Action3dp_(getSpreadsheet3dp_(), { role: 'owner', identity: 'dashboard' });
  });
}

function preview3dp015() {
  const spreadsheet = getSpreadsheet3dp_();
  validate3dp015Schema3dp_(spreadsheet);
  return {
    ok: true,
    spreadsheet_id: spreadsheet.getId(),
    planned_changes: [
      'Номенклатура: add owner-only Q:R:S for фактична РРЦ, buyout price, and durable model link',
      'Номенклатура: remove fixture N from the Serhiy production-cost K formula and relabel N as a reference price',
      'Продажі: add frozen U:W fields after technical T and keep historical F formulas untouched',
      'Продажі: subtract only owner-paid frozen fixture V from margin I, separately from packaging G',
      'Аналітика: replace the broken three-scenario calculator with фактична РРЦ, pending recommended РРЦ, and one actual-price margin model',
    ],
  };
}

function setup3dp015() {
  return withScriptLock3dp_(function () {
    return setup3dp015Action3dp_(getSpreadsheet3dp_(), { role: 'owner', identity: 'dashboard' });
  });
}

function preview3dp024() {
  const spreadsheet = getSpreadsheet3dp_();
  validate3dp015Schema3dp_(spreadsheet);
  return {
    ok: true,
    spreadsheet_id: spreadsheet.getId(),
    planned_changes: [
      'Номенклатура!G and Друк-лог!D: add decimal-hour entry instructions and non-blocking plausibility warnings',
      'Номенклатура!K and Продажі!I/K/L: fill a blank approved formula cell instead of rejecting the setup',
      'Аналітика!A1: replace the stale three-scenario title without touching row 18 market references',
    ],
  };
}

function setup3dp024() {
  return withScriptLock3dp_(function () {
    return setup3dp024Action3dp_(getSpreadsheet3dp_(), { role: 'owner', identity: 'dashboard' });
  });
}

function preview3dpOrderLineAccounting() {
  const spreadsheet = getSpreadsheet3dp_();
  const sales = getSheet3dp_(spreadsheet, SHEETS_3DP.sales);
  const plan = validate3dpOrderLineAccountingSchema3dp_(sales);
  const result = {
    ok: true,
    spreadsheet_id: spreadsheet.getId(),
    current_columns: sales.getMaxColumns(),
    required_columns: columnToNumber3dp_('AA'),
    columns_to_add: Math.max(columnToNumber3dp_('AA') - sales.getMaxColumns(), 0),
    rows_to_migrate: plan.lastRow - 1,
    planned_changes: [
      'Продажі: add frozen X:AA mode, owner fixture, Serhiy fixture, and buyout fields',
      'Продажі: default existing CRM-linked rows to Продаж and split legacy V/W without inventing mixed-payer data',
      'Продажі: make I/K/L line-aware; Маркетинг bypasses 50/50 and uses frozen buyout while Продаж keeps the split',
      'Наявність: exclude Продажі rows marked Маркетинг because those rows are counted by Маркетингові_плюшки',
    ],
  };
  Logger.log(JSON.stringify(result));
  return result;
}

function setup3dpOrderLineAccounting() {
  const result = withScriptLock3dp_(function () {
    return setup3dpOrderLineAccountingAction3dp_(getSpreadsheet3dp_(), { role: 'owner', identity: 'dashboard' });
  });
  Logger.log(JSON.stringify(result));
  return result;
}

function preview3dpSalesProfitShareBackfill() {
  const result = salesProfitShareBackfill3dp_(getSpreadsheet3dp_(), { role: 'owner', identity: 'dashboard' }, true);
  Logger.log(JSON.stringify(result));
  return result;
}

function setup3dpSalesProfitShareBackfill() {
  const result = withScriptLock3dp_(function () {
    return salesProfitShareBackfill3dp_(getSpreadsheet3dp_(), { role: 'owner', identity: 'dashboard' }, false);
  });
  Logger.log(JSON.stringify(result));
  return result;
}

function salesProfitShareBackfill3dp_(spreadsheet, actor, dryRun) {
  assertOwner3dp_(actor, 'Caller may not backfill frozen sales profit shares.');
  const sales = getSheet3dp_(spreadsheet, SHEETS_3DP.sales);
  if (String(sales.getRange('B1').getDisplayValue() || '').trim() !== 'SKU' ||
      String(sales.getRange('H1').getDisplayValue() || '').trim() !== '% прибутку Сергію') {
    throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Продажі!B1/H1 headers do not match the approved sales schema.');
  }
  const lastRow = Math.max(sales.getLastRow(), 2);
  const values = sales.getRange(2, 2, lastRow - 1, 7).getValues();
  const rows = [];
  values.forEach(function (row, index) {
    const sku = String(row[0] || '').trim();
    if (!sku || !isBlank3dp_(row[6])) return;
    rows.push({ row_number: index + 2, sku: sku, profit_share: profitShareForSku3dp_(spreadsheet, sku) });
  });
  const result = { ok: true, action: '3dp_sales_profit_share_backfill', dry_run: Boolean(dryRun), rows: rows,
    rows_to_update: rows.length, rows_updated: 0, already_applied: rows.length === 0 };
  if (dryRun || result.already_applied) return result;
  const snapshot = snapshotRange3dp_(sales, 'H2:H' + lastRow);
  try {
    rows.forEach(function (row) { sales.getRange(row.row_number, 8).setValue(row.profit_share); });
    SpreadsheetApp.flush();
    appendAudit3dp_(spreadsheet, actor, 'BACKFILL_SALES_PROFIT_SHARE', SHEETS_3DP.sales,
      rows.map(function (row) { return 'H' + row.row_number; }).join(','), '', rows, 'Frozen per-sale share from Analytics; existing nonblank values preserved.');
    result.rows_updated = rows.length;
  } catch (error) {
    restoreRange3dp_(snapshot);
    throw error;
  }
  return result;
}
