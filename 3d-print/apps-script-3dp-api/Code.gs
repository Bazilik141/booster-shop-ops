/**
 * Booster Shop 3D-P API (bound Apps Script web app).
 *
 * Security invariants:
 * - token values live only in Script Properties;
 * - caller identity is derived from the matched property, never trusted from input;
 * - formula cells and non-whitelisted columns are never writable;
 * - all writes use a script-wide lock and append an audit record;
 * - print-log deletion is a reversible archive state, never a physical row delete.
 */

const API_3DP = Object.freeze({
  spreadsheetId: '1yp15H3YJGkqI4Rx89G4QZHkD9m67gnWh58TsTTi-jjo',
  timezone: 'Europe/Kyiv',
  ownerTokenProperty: 'BOOSTER_3DP_TOKEN',
  serhiyTokenProperty: 'BOOSTER_3DP_SERHIY_TOKEN',
  auditSheet: '_Аудит_API',
  settingsSheet: 'Налаштування',
  fixturesSheet: 'Фурнітура_довідник',
  printLogSheet: 'Друк-лог',
  printLogStatusColumn: 'J',
  printLogHistoryColumn: 'K',
  nomenclatureStatusColumn: 'O',
  nomenclatureHistoryColumn: 'P',
  activeStatus: 'Активний',
  archivedStatus: 'Архів',
  maxRangeCells: 500,
  maxReadRows: 500,
  maxStockAdjustmentReasonChars: 250,
  salesCrmRowHeader: 'CRM row number',
});

const SHEETS_3DP = Object.freeze({
  legend: 'Легенда',
  nomenclature: 'Номенклатура',
  printLog: 'Друк-лог',
  sales: 'Продажі',
  payouts: 'Виплати',
  plyushky: 'Маркетингові_плюшки',
  availability: 'Наявність',
  analytics: 'Аналітика',
  settings: 'Налаштування',
  fixtures: 'Фурнітура_довідник',
  drafts: '_Чернетки_партій',
  stockAdjustments: '_Коригування_наявності',
});

const APPENDABLE_SHEETS_3DP = Object.freeze([
  SHEETS_3DP.nomenclature,
  SHEETS_3DP.printLog,
  SHEETS_3DP.sales,
  SHEETS_3DP.payouts,
  SHEETS_3DP.plyushky,
]);

const BATCH_DRAFT_FIELDS_3DP = Object.freeze([
  Object.freeze({ key: 'quantity', header: 'Кількість у партії, шт', integer: true }),
  Object.freeze({ key: 'total_weight_g', header: 'Сумарна вага партії, г' }),
  Object.freeze({ key: 'total_print_time_h', header: 'Сумарний час партії, год' }),
  Object.freeze({ key: 'spool_weight_g', header: 'Вага котушки, г' }),
  Object.freeze({ key: 'spool_price_uah', header: 'Ціна котушки, грн' }),
]);

const STOCK_ADJUSTMENT_HEADERS_3DP = Object.freeze([
  'SKU',
  'Зміна наявності, шт',
  'Причина',
  'Дата коригування (Київ)',
]);

const TECHNICAL_APPEND_COLUMNS_3DP = Object.freeze({
  'Продажі': Object.freeze(['T']),
});

// Grounded from the live formulas, the workbook Legend, and the owner-approved scope.
// K in Номенклатура and G in Друк-лог are deliberately absent because they are formulas.
const OWNER_MANUAL_COLUMNS_3DP = Object.freeze({
  'Номенклатура': Object.freeze(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'L', 'M', 'N']),
  'Друк-лог': Object.freeze(['A', 'B', 'C', 'D', 'E', 'F', 'H', 'I']),
  'Продажі': Object.freeze(['A', 'B', 'D', 'E', 'G', 'H', 'M', 'N', 'O', 'P', 'Q', 'R']),
  'Виплати': Object.freeze(['A', 'D', 'E', 'F']),
  'Маркетингові_плюшки': Object.freeze(['A', 'B', 'C', 'D', 'F', 'G', 'H']),
  'Налаштування': Object.freeze(['B']),
});

const SERHIY_MANUAL_COLUMNS_3DP = Object.freeze({
  'Номенклатура': Object.freeze(['G', 'H', 'I', 'J', 'L', 'M', 'N']),
  'Друк-лог': Object.freeze(['A', 'B', 'C', 'D', 'E', 'F', 'H', 'I']),
});

const FORMULA_COLUMNS_3DP = Object.freeze({
  'Номенклатура': Object.freeze(['K']),
  'Друк-лог': Object.freeze(['G']),
  'Продажі': Object.freeze(['C', 'F', 'I', 'J', 'K', 'L', 'S']),
  'Виплати': Object.freeze(['B', 'C']),
  'Маркетингові_плюшки': Object.freeze(['E']),
});

const READABLE_SHEETS_3DP = Object.freeze([
  'Легенда',
  'Номенклатура',
  'Друк-лог',
  'Продажі',
  'Виплати',
  'Маркетингові_плюшки',
  'Наявність',
  'Аналітика',
  'Налаштування',
  'Фурнітура_довідник',
]);

function doGet(e) {
  return respond3dp_(function () {
    const params = (e && e.parameter) || {};
    const actor = authenticate3dp_(params.token);
    return handleGet3dp_(params, actor);
  });
}

function doPost(e) {
  return respond3dp_(function () {
    const raw = e && e.postData && e.postData.contents;
    if (!raw) throw apiError3dp_('EMPTY_BODY', 'Request body is required.');

    let body;
    try {
      body = JSON.parse(raw);
    } catch (error) {
      throw apiError3dp_('INVALID_JSON', 'Request body must be valid JSON.');
    }

    const actor = authenticate3dp_(body.token);
    return withScriptLock3dp_(function () {
      return handlePost3dp_(body, actor);
    });
  });
}

function handleGet3dp_(params, actor) {
  const action = String(params.action || '').trim();
  const spreadsheet = getSpreadsheet3dp_();

  switch (action) {
    case '3dp_get_row':
      return getRowAction3dp_(spreadsheet, params);
    case '3dp_get_range':
      return getRangeAction3dp_(spreadsheet, params, actor);
    case '3dp_overview':
      return overviewAction3dp_(spreadsheet);
    case '3dp_skus':
      return skusAction3dp_(spreadsheet, params);
    case '3dp_sales':
      return tableAction3dp_(spreadsheet, SHEETS_3DP.sales, { requireHeader: 'SKU' });
    case '3dp_plyushky':
      return tableAction3dp_(spreadsheet, SHEETS_3DP.plyushky, { requireHeader: 'SKU' });
    case '3dp_payouts':
      return tableAction3dp_(spreadsheet, SHEETS_3DP.payouts, { requireHeader: 'Період (РРРР-ММ)' });
    case '3dp_print_log':
      return printLogAction3dp_(spreadsheet, params);
    case '3dp_fixtures':
      return tableAction3dp_(spreadsheet, SHEETS_3DP.fixtures, { requireHeader: 'Назва фурнітури' });
    case '3dp_batch_draft':
      return getBatchDraftAction3dp_(spreadsheet, params, actor);
    case '3dp_stock_adjustments':
      return stockAdjustmentsAction3dp_(spreadsheet, params, actor);
    default:
      throw apiError3dp_('UNKNOWN_ACTION', 'Unknown read action.');
  }
}

function handlePost3dp_(body, actor) {
  const action = String(body.action || '').trim();
  const spreadsheet = getSpreadsheet3dp_();

  switch (action) {
    case '3dp_write':
      return writeAction3dp_(spreadsheet, body, actor);
    case '3dp_append_row':
      return appendRowAction3dp_(spreadsheet, body, actor);
    case '3dp_print_log_update':
      return updatePrintLogAction3dp_(spreadsheet, body, actor);
    case '3dp_print_log_archive':
      return setPrintLogArchiveAction3dp_(spreadsheet, body, actor, true);
    case '3dp_print_log_restore':
      return setPrintLogArchiveAction3dp_(spreadsheet, body, actor, false);
    case '3dp_batch_draft_save':
      return saveBatchDraftAction3dp_(spreadsheet, body, actor);
    case '3dp_nomenclature_archive':
      return setNomenclatureArchiveAction3dp_(spreadsheet, body, actor, true);
    case '3dp_nomenclature_restore':
      return setNomenclatureArchiveAction3dp_(spreadsheet, body, actor, false);
    case '3dp_adjust_stock':
      return adjustStockAction3dp_(spreadsheet, body, actor);
    case '3dp_setup_3dp010':
      return setup3dp010Action3dp_(spreadsheet, actor);
    case '3dp_setup_addendum2':
      return setupAddendum2Action3dp_(spreadsheet, actor);
    default:
      throw apiError3dp_('UNKNOWN_ACTION', 'Unknown write action.');
  }
}

function authenticate3dp_(providedToken) {
  const token = String(providedToken || '');
  if (!token) throw apiError3dp_('UNAUTHORIZED', 'Missing token.');

  const properties = PropertiesService.getScriptProperties();
  const ownerToken = properties.getProperty(API_3DP.ownerTokenProperty) || '';
  const serhiyToken = properties.getProperty(API_3DP.serhiyTokenProperty) || '';

  if (ownerToken && constantTimeEqual3dp_(token, ownerToken)) {
    return { role: 'owner', identity: 'dashboard' };
  }
  if (serhiyToken && constantTimeEqual3dp_(token, serhiyToken)) {
    return { role: 'serhiy', identity: 'serhiy' };
  }
  throw apiError3dp_('UNAUTHORIZED', 'Invalid token.');
}

function constantTimeEqual3dp_(left, right) {
  const a = String(left);
  const b = String(right);
  let mismatch = a.length ^ b.length;
  const maxLength = Math.max(a.length, b.length);
  for (let index = 0; index < maxLength; index += 1) {
    mismatch |= (a.charCodeAt(index % Math.max(a.length, 1)) || 0) ^
      (b.charCodeAt(index % Math.max(b.length, 1)) || 0);
  }
  return mismatch === 0;
}

function getSpreadsheet3dp_() {
  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  if (!spreadsheet || spreadsheet.getId() !== API_3DP.spreadsheetId) {
    throw apiError3dp_('WRONG_SPREADSHEET', 'This script must be bound to the approved 3D-P spreadsheet.');
  }
  return spreadsheet;
}

function getSheet3dp_(spreadsheet, sheetName) {
  const name = String(sheetName || '');
  if (READABLE_SHEETS_3DP.indexOf(name) === -1 && name !== API_3DP.auditSheet) {
    throw apiError3dp_('SHEET_NOT_ALLOWED', 'Sheet is not allowed.');
  }
  const sheet = spreadsheet.getSheetByName(name);
  if (!sheet) throw apiError3dp_('SHEET_NOT_FOUND', 'Required sheet not found: ' + name);
  return sheet;
}

function getRowAction3dp_(spreadsheet, params) {
  const sheetName = String(params.sheet || '');
  if ([SHEETS_3DP.nomenclature, SHEETS_3DP.availability].indexOf(sheetName) === -1) {
    throw apiError3dp_('SHEET_NOT_ALLOWED', '3dp_get_row supports only SKU-keyed sheets.');
  }
  const sku = String(params.sku || '').trim();
  if (!sku) throw apiError3dp_('SKU_REQUIRED', 'sku is required.');
  if (isPlaceholderSku3dp_(sku)) throw apiError3dp_('ROW_FILTERED', 'Illustrative or placeholder rows are not returned.');

  const sheet = getSheet3dp_(spreadsheet, sheetName);
  const lastRow = Math.min(sheet.getLastRow(), API_3DP.maxReadRows);
  const lastColumn = sheet.getLastColumn();
  const headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0];
  if (lastRow < 2) throw apiError3dp_('ROW_NOT_FOUND', 'SKU not found.');
  const values = sheet.getRange(2, 1, lastRow - 1, lastColumn).getValues();

  for (let index = 0; index < values.length; index += 1) {
    if (String(values[index][0] || '').trim() === sku) {
      const row = rowObject3dp_(headers, values[index], index + 2);
      if (isExampleRow3dp_(row)) throw apiError3dp_('ROW_FILTERED', 'Illustrative rows are not returned.');
      return { action: '3dp_get_row', sheet: sheetName, row: row };
    }
  }
  throw apiError3dp_('ROW_NOT_FOUND', 'SKU not found.');
}

function getRangeAction3dp_(spreadsheet, params, actor) {
  const sheetName = String(params.sheet || '');
  if (sheetName === API_3DP.auditSheet || READABLE_SHEETS_3DP.indexOf(sheetName) === -1) {
    throw apiError3dp_('SHEET_NOT_ALLOWED', 'Sheet is not readable through this action.');
  }

  const parsed = parseBoundedRange3dp_(params.range);
  if (sheetName === SHEETS_3DP.legend) {
    if (parsed.startColumn !== 1 || parsed.endColumn > 2 || parsed.startRow < 32 || parsed.endRow > 60) {
      throw apiError3dp_('RANGE_NOT_ALLOWED', 'Only the bounded open-questions block is exposed from Легенда.');
    }
  }
  if (sheetName === SHEETS_3DP.analytics) {
    if (parsed.startColumn < 1 || parsed.endColumn > 14 || parsed.startRow < 3 || parsed.endRow > 17) {
      throw apiError3dp_('RANGE_NOT_ALLOWED', 'Only the calculator table is exposed from Аналітика.');
    }
  }

  const sheet = getSheet3dp_(spreadsheet, sheetName);
  if (parsed.endRow > sheet.getMaxRows() || parsed.endColumn > sheet.getMaxColumns()) {
    throw apiError3dp_('RANGE_OUT_OF_BOUNDS', 'Requested range is outside the sheet grid.');
  }

  const range = sheet.getRange(parsed.a1);
  return {
    action: '3dp_get_range',
    sheet: sheetName,
    range: parsed.a1,
    values: normalizeMatrix3dp_(range.getValues()),
    formulas: range.getFormulas(),
  };
}

function overviewAction3dp_(spreadsheet) {
  const nomenclature = readTable3dp_(spreadsheet, SHEETS_3DP.nomenclature, { requireHeader: 'SKU' });
  const availability = readTable3dp_(spreadsheet, SHEETS_3DP.availability, { requireHeader: 'SKU' });
  const sales = readTable3dp_(spreadsheet, SHEETS_3DP.sales, { requireHeader: 'SKU' });
  const month = Utilities.formatDate(new Date(), API_3DP.timezone, 'yyyy-MM');

  const activeSkus = activeNomenclatureRows3dp_(nomenclature.rows);
  const activeSkuIndex = activeSkus.reduce(function (result, row) {
    result[String(row.SKU || '')] = true;
    return result;
  }, {});
  const totals = availability.rows
    .filter(function (row) { return Boolean(activeSkuIndex[String(row.SKU || '')]); })
    .reduce(function (result, row) {
    result.printed += number3dp_(row['Надруковано всього, шт']);
    result.defects += number3dp_(row['Брак всього, шт']);
    result.sold += number3dp_(row['Продано на сайті, шт']);
    result.given += number3dp_(row['Видано як плюшка, шт']);
    result.available += number3dp_(row['Наявно зараз, шт']);
    return result;
  }, { printed: 0, defects: 0, sold: 0, given: 0, available: 0 });
  const accrued = sales.rows.reduce(function (sum, row) {
    return String(row['Період (авто, РРРР-ММ)'] || '') === month
      ? sum + number3dp_(row['Нараховано Сергію, грн'])
      : sum;
  }, 0);

  return {
    action: '3dp_overview',
    month: month,
    summary: {
      sku_count: activeSkus.length,
      printed: totals.printed,
      defects: totals.defects,
      sold: totals.sold,
      given: totals.given,
      available: totals.available,
      accrued_serhiy_current_month: accrued,
    },
  };
}

function skusAction3dp_(spreadsheet, params) {
  const includeArchived = String((params && params.include_archived) || '').toLowerCase() === 'true';
  const nomenclature = readTable3dp_(spreadsheet, SHEETS_3DP.nomenclature, { requireHeader: 'SKU' });
  const availability = readTable3dp_(spreadsheet, SHEETS_3DP.availability, { requireHeader: 'SKU' });
  const bySku = {};
  availability.rows.forEach(function (row) {
    bySku[String(row.SKU || '')] = row;
  });
  const rows = nomenclature.rows
    .filter(function (row) { return !isPlaceholderSku3dp_(row.SKU); })
    .filter(function (row) { return includeArchived || !isArchivedNomenclatureRow3dp_(row); })
    .map(function (row) {
      return Object.assign({}, row, { availability: bySku[String(row.SKU || '')] || null });
    });
  return { action: '3dp_skus', rows: rows, count: rows.length, include_archived: includeArchived };
}

function activeNomenclatureRows3dp_(rows) {
  return rows.filter(function (row) {
    return !isPlaceholderSku3dp_(row.SKU) && !isArchivedNomenclatureRow3dp_(row);
  });
}

function isArchivedNomenclatureRow3dp_(row) {
  return String(row['API_статус_запису'] || '') === API_3DP.archivedStatus;
}

function tableAction3dp_(spreadsheet, sheetName, options) {
  const table = readTable3dp_(spreadsheet, sheetName, options || {});
  return { action: actionNameForSheet3dp_(sheetName), sheet: sheetName, rows: table.rows, count: table.rows.length };
}

function printLogAction3dp_(spreadsheet, params) {
  const includeArchived = String(params.include_archived || '').toLowerCase() === 'true';
  const table = readTable3dp_(spreadsheet, SHEETS_3DP.printLog, { requireHeader: 'SKU', includeArchived: includeArchived });
  return { action: '3dp_print_log', rows: table.rows, count: table.rows.length, include_archived: includeArchived };
}

function readTable3dp_(spreadsheet, sheetName, options) {
  const sheet = getSheet3dp_(spreadsheet, sheetName);
  const lastRow = Math.min(sheet.getLastRow(), API_3DP.maxReadRows);
  const lastColumn = sheet.getLastColumn();
  if (lastColumn < 1) return { headers: [], rows: [] };
  const headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0];
  if (lastRow < 2) return { headers: headers, rows: [] };
  const values = sheet.getRange(2, 1, lastRow - 1, lastColumn).getValues();
  const rows = [];

  values.forEach(function (valuesRow, index) {
    const row = rowObject3dp_(headers, valuesRow, index + 2);
    if (options && options.requireHeader && isBlank3dp_(row[options.requireHeader])) return;
    if (isExampleRow3dp_(row)) return;
    if (!(options && options.includeArchived) && isArchivedPrintLogRow3dp_(sheetName, row)) return;
    rows.push(row);
  });
  return { headers: headers, rows: rows };
}

function rowObject3dp_(headers, values, rowNumber) {
  const row = { row_number: rowNumber };
  headers.forEach(function (header, index) {
    if (header) row[String(header)] = normalizeCellValue3dp_(values[index]);
  });
  return row;
}

function isExampleRow3dp_(row) {
  const sku = String(row.SKU || '').toLowerCase();
  const status = String(row['Статус'] || '').toLowerCase();
  const notes = String(row['Примітки'] || '').toLowerCase();
  return sku === 'приклад-001' || status.indexOf('приклад') !== -1 || status.indexOf('видалити') !== -1 ||
    notes.indexOf('приклад') !== -1 && notes.indexOf('видалити') !== -1;
}

function isPlaceholderSku3dp_(value) {
  const sku = String(value || '').trim().toLowerCase();
  return !sku || sku === 'приклад-001' || sku.indexOf('призначити sku') !== -1;
}

function isArchivedPrintLogRow3dp_(sheetName, row) {
  return sheetName === SHEETS_3DP.printLog && String(row['API_статус_запису'] || '') === API_3DP.archivedStatus;
}

function writeAction3dp_(spreadsheet, body, actor) {
  const sheetName = String(body.sheet || '');
  if (sheetName === SHEETS_3DP.printLog) {
    throw apiError3dp_('SPECIALIZED_ACTION_REQUIRED', 'Use 3dp_print_log_update so row history is preserved.');
  }
  const sheet = getSheet3dp_(spreadsheet, sheetName);
  const column = resolveColumn3dp_(sheet, body.column);
  const row = resolveTargetRow3dp_(sheet, body.sku_or_row);
  if (row < 2) throw apiError3dp_('ROW_NOT_ALLOWED', 'Header row cannot be changed.');

  const range = sheet.getRange(row, columnToNumber3dp_(column));
  if (range.getFormula()) throw apiError3dp_('FORMULA_CELL', 'Formula cells cannot be changed.');
  assertCellWriteAllowed3dp_(sheetName, column, actor);
  assertWriteTargetAllowed3dp_(sheetName, column, row);
  const oldRawValue = range.getValue();
  const oldValue = normalizeCellValue3dp_(oldRawValue);
  if (Object.prototype.hasOwnProperty.call(body, 'expected_current') &&
      !equalCellValue3dp_(oldValue, body.expected_current)) {
    throw apiError3dp_('STALE_WRITE', 'The cell changed after it was read. Refresh and retry.');
  }

  assertManualValue3dp_(body.value);
  setCellValue3dp_(range, body.value);
  try {
    appendAudit3dp_(spreadsheet, actor, 'WRITE', sheetName, column + row, oldValue, body.value, '');
  } catch (error) {
    setCellValue3dp_(range, oldRawValue);
    throw error;
  }
  return { action: '3dp_write', sheet: sheetName, cell: column + row, old_value: oldValue, new_value: normalizeCellValue3dp_(body.value) };
}

function appendRowAction3dp_(spreadsheet, body, actor) {
  const sheetName = String(body.sheet || '');
  if (APPENDABLE_SHEETS_3DP.indexOf(sheetName) === -1) {
    throw apiError3dp_('SHEET_NOT_WRITABLE', 'Rows cannot be appended to this sheet.');
  }
  const sheet = getSheet3dp_(spreadsheet, sheetName);
  if (sheetName === SHEETS_3DP.nomenclature) assertNomenclatureArchiveSystemReady3dp_(sheet);
  const values = body.values;
  if (!values || typeof values !== 'object' || Array.isArray(values)) {
    throw apiError3dp_('VALUES_REQUIRED', 'values must be an object keyed by column or header.');
  }

  const normalized = {};
  Object.keys(values).forEach(function (key) {
    const column = resolveColumn3dp_(sheet, key);
    if (sheetName === SHEETS_3DP.sales && column === 'T') {
      assertTechnicalSaleAppendAllowed3dp_(sheet, column, values[key], actor);
    } else {
      assertCellWriteAllowed3dp_(sheetName, column, actor);
    }
    if (Object.prototype.hasOwnProperty.call(normalized, column)) {
      throw apiError3dp_('DUPLICATE_COLUMN', 'The same target column was supplied more than once.');
    }
    assertManualValue3dp_(values[key]);
    normalized[column] = values[key];
  });
  if (!Object.keys(normalized).length) throw apiError3dp_('VALUES_REQUIRED', 'At least one value is required.');
  if (sheetName === SHEETS_3DP.sales && !Object.prototype.hasOwnProperty.call(normalized, 'T')) {
    throw apiError3dp_('CRM_ROW_REQUIRED', 'Продажі rows require the technical CRM row number in column T.');
  }

  const row = findFirstBusinessEmptyRow3dp_(sheet, sheetName, actor);
  copyFormulaCells3dp_(sheet, sheetName, row);
  const applied = [];
  try {
    Object.keys(normalized).forEach(function (column) {
      const range = sheet.getRange(row, columnToNumber3dp_(column));
      if (range.getFormula()) throw apiError3dp_('FORMULA_CELL', 'Formula cells cannot be changed.');
      const oldValue = normalizeCellValue3dp_(range.getValue());
      setCellValue3dp_(range, normalized[column]);
      applied.push({ column: column, range: range, oldValue: oldValue, newValue: normalized[column] });
    });

    if (sheetName === SHEETS_3DP.nomenclature) {
      const statusRange = sheet.getRange(row, columnToNumber3dp_(API_3DP.nomenclatureStatusColumn));
      const historyRange = sheet.getRange(row, columnToNumber3dp_(API_3DP.nomenclatureHistoryColumn));
      if (statusRange.getFormula() || historyRange.getFormula()) {
        throw apiError3dp_('FORMULA_CELL', 'SKU archive system fields must remain manual cells.');
      }
      const oldStatus = normalizeCellValue3dp_(statusRange.getValue());
      const oldHistory = normalizeCellValue3dp_(historyRange.getValue());
      const newHistory = historyLine3dp_(actor, 'Створено новий SKU');
      setCellValue3dp_(statusRange, API_3DP.activeStatus);
      setCellValue3dp_(historyRange, newHistory);
      applied.push({ column: API_3DP.nomenclatureStatusColumn, range: statusRange, oldValue: oldStatus, newValue: API_3DP.activeStatus });
      applied.push({ column: API_3DP.nomenclatureHistoryColumn, range: historyRange, oldValue: oldHistory, newValue: newHistory });
    }

    if (sheetName === SHEETS_3DP.printLog) {
      const statusRange = sheet.getRange(row, columnToNumber3dp_(API_3DP.printLogStatusColumn));
      const historyRange = sheet.getRange(row, columnToNumber3dp_(API_3DP.printLogHistoryColumn));
      statusRange.setValue(API_3DP.activeStatus);
      historyRange.setValue(historyLine3dp_(actor, 'Створено новий запис'));
      applied.push({ column: API_3DP.printLogStatusColumn, range: statusRange, oldValue: '', newValue: API_3DP.activeStatus });
      applied.push({ column: API_3DP.printLogHistoryColumn, range: historyRange, oldValue: '', newValue: historyRange.getValue() });
    }

    appendAudit3dp_(
      spreadsheet,
      actor,
      'APPEND',
      sheetName,
      'row:' + row,
      {},
      applied.reduce(function (result, change) {
        result[change.column] = normalizeCellValue3dp_(change.newValue);
        return result;
      }, {}),
      'row=' + row
    );
  } catch (error) {
    applied.reverse().forEach(function (change) { setCellValue3dp_(change.range, change.oldValue); });
    throw error;
  }

  return { action: '3dp_append_row', sheet: sheetName, row: row };
}

function updatePrintLogAction3dp_(spreadsheet, body, actor) {
  assertPrintLogRole3dp_(actor);
  const sheet = getSheet3dp_(spreadsheet, SHEETS_3DP.printLog);
  const row = positiveRowNumber3dp_(body.row);
  assertRealPrintLogRow3dp_(sheet, row);

  const currentStatus = String(sheet.getRange(row, columnToNumber3dp_(API_3DP.printLogStatusColumn)).getValue() || API_3DP.activeStatus);
  if (currentStatus === API_3DP.archivedStatus) {
    throw apiError3dp_('ROW_ARCHIVED', 'Restore the row before editing it.');
  }

  const changes = body.changes;
  const expected = body.expected_current;
  if (!changes || typeof changes !== 'object' || Array.isArray(changes) || !Object.keys(changes).length) {
    throw apiError3dp_('CHANGES_REQUIRED', 'changes must contain at least one field.');
  }
  if (!expected || typeof expected !== 'object' || Array.isArray(expected)) {
    throw apiError3dp_('EXPECTED_REQUIRED', 'expected_current is required for print-log edits.');
  }

  const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getDisplayValues()[0];
  const prepared = [];
  const seenColumns = {};
  Object.keys(changes).forEach(function (key) {
    const column = resolveColumn3dp_(sheet, key);
    if (seenColumns[column]) throw apiError3dp_('DUPLICATE_COLUMN', 'The same target column was supplied more than once.');
    seenColumns[column] = true;
    assertCellWriteAllowed3dp_(SHEETS_3DP.printLog, column, actor);
    assertManualValue3dp_(changes[key]);
    const range = sheet.getRange(row, columnToNumber3dp_(column));
    if (range.getFormula()) throw apiError3dp_('FORMULA_CELL', 'Formula cells cannot be changed.');
    const oldRawValue = range.getValue();
    const oldValue = normalizeCellValue3dp_(oldRawValue);
    const expectedKey = Object.prototype.hasOwnProperty.call(expected, key) ? key : column;
    if (!Object.prototype.hasOwnProperty.call(expected, expectedKey)) {
      throw apiError3dp_('EXPECTED_REQUIRED', 'expected_current is missing for column ' + column + '.');
    }
    if (!equalCellValue3dp_(oldValue, expected[expectedKey])) {
      throw apiError3dp_('STALE_WRITE', 'Print-log row changed after it was read. Refresh and retry.');
    }
    if (!equalCellValue3dp_(oldValue, changes[key])) {
      prepared.push({
        column: column,
        range: range,
        oldRawValue: oldRawValue,
        oldValue: oldValue,
        newValue: changes[key],
        label: headers[columnToNumber3dp_(column) - 1] || column,
      });
    }
  });
  if (!prepared.length) return { action: '3dp_print_log_update', row: row, already_applied: true };

  const historyRange = sheet.getRange(row, columnToNumber3dp_(API_3DP.printLogHistoryColumn));
  const oldHistory = String(historyRange.getValue() || '');
  const summary = prepared.map(function (change) {
    return change.label + ': ' + displayAuditValue3dp_(change.oldValue) + ' → ' + displayAuditValue3dp_(change.newValue);
  }).join('; ');
  const newHistory = appendHistory3dp_(oldHistory, historyLine3dp_(actor, summary));

  try {
    prepared.forEach(function (change) { setCellValue3dp_(change.range, change.newValue); });
    historyRange.setValue(newHistory);
    appendAudit3dp_(
      spreadsheet,
      actor,
      'PRINT_LOG_EDIT',
      SHEETS_3DP.printLog,
      'row:' + row,
      prepared.reduce(function (result, change) {
        result[change.column] = change.oldValue;
        return result;
      }, {}),
      prepared.reduce(function (result, change) {
        result[change.column] = normalizeCellValue3dp_(change.newValue);
        return result;
      }, {}),
      summary
    );
  } catch (error) {
    prepared.forEach(function (change) { setCellValue3dp_(change.range, change.oldRawValue); });
    historyRange.setValue(oldHistory);
    throw error;
  }
  return { action: '3dp_print_log_update', row: row, changes: prepared.length, history: newHistory };
}

function setPrintLogArchiveAction3dp_(spreadsheet, body, actor, archive) {
  assertPrintLogRole3dp_(actor);
  const sheet = getSheet3dp_(spreadsheet, SHEETS_3DP.printLog);
  const row = positiveRowNumber3dp_(body.row);
  assertRealPrintLogRow3dp_(sheet, row);

  const statusRange = sheet.getRange(row, columnToNumber3dp_(API_3DP.printLogStatusColumn));
  const historyRange = sheet.getRange(row, columnToNumber3dp_(API_3DP.printLogHistoryColumn));
  const oldStatus = String(statusRange.getValue() || API_3DP.activeStatus);
  const newStatus = archive ? API_3DP.archivedStatus : API_3DP.activeStatus;
  if (oldStatus === newStatus) {
    return { action: archive ? '3dp_print_log_archive' : '3dp_print_log_restore', row: row, already_applied: true };
  }
  if (Object.prototype.hasOwnProperty.call(body, 'expected_status') && String(body.expected_status) !== oldStatus) {
    throw apiError3dp_('STALE_WRITE', 'Print-log status changed after it was read. Refresh and retry.');
  }

  const oldHistory = String(historyRange.getValue() || '');
  const reason = String(body.reason || '').trim();
  const summary = 'Статус: ' + oldStatus + ' → ' + newStatus + (reason ? '; причина: ' + reason : '');
  const newHistory = appendHistory3dp_(oldHistory, historyLine3dp_(actor, summary));
  try {
    statusRange.setValue(newStatus);
    historyRange.setValue(newHistory);
    appendAudit3dp_(spreadsheet, actor, archive ? 'PRINT_LOG_ARCHIVE' : 'PRINT_LOG_RESTORE', SHEETS_3DP.printLog, API_3DP.printLogStatusColumn + row, oldStatus, newStatus, summary);
  } catch (error) {
    statusRange.setValue(oldStatus);
    historyRange.setValue(oldHistory);
    throw error;
  }
  return { action: archive ? '3dp_print_log_archive' : '3dp_print_log_restore', row: row, status: newStatus };
}

function getBatchDraftAction3dp_(spreadsheet, params, actor) {
  assertBatchDraftRole3dp_(actor);
  const sku = requiredSku3dp_(params.sku);
  const nomenclature = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
  const nomenclatureRow = resolveTargetRow3dp_(nomenclature, sku);
  assertRealNomenclatureRow3dp_(nomenclature, nomenclatureRow);
  const drafts = getInternalSheet3dp_(spreadsheet, SHEETS_3DP.drafts);
  const draftRow = findBatchDraftRow3dp_(drafts, sku);
  return {
    action: '3dp_batch_draft',
    sku: sku,
    found: draftRow > 0,
    values: batchDraftValues3dp_(drafts, draftRow),
  };
}

function saveBatchDraftAction3dp_(spreadsheet, body, actor) {
  assertBatchDraftRole3dp_(actor);
  const sku = requiredSku3dp_(body.sku);
  const nomenclature = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
  const nomenclatureRow = resolveTargetRow3dp_(nomenclature, sku);
  assertRealNomenclatureRow3dp_(nomenclature, nomenclatureRow);
  if (nomenclatureStatusAtRow3dp_(nomenclature, nomenclatureRow) === API_3DP.archivedStatus) {
    throw apiError3dp_('SKU_ARCHIVED', 'Restore the SKU before saving a new batch draft.');
  }

  const values = body.values;
  const expected = body.expected_current;
  if (!values || typeof values !== 'object' || Array.isArray(values) || !Object.keys(values).length) {
    throw apiError3dp_('VALUES_REQUIRED', 'values must contain one or more supported batch-draft fields.');
  }
  if (!expected || typeof expected !== 'object' || Array.isArray(expected)) {
    throw apiError3dp_('EXPECTED_REQUIRED', 'expected_current is required for every saved batch-draft field.');
  }

  const drafts = getInternalSheet3dp_(spreadsheet, SHEETS_3DP.drafts);
  const existingRow = findBatchDraftRow3dp_(drafts, sku);
  const oldValues = batchDraftValues3dp_(drafts, existingRow);
  const nextValues = Object.assign({}, oldValues);
  const supplied = [];

  Object.keys(values).forEach(function (key) {
    const field = batchDraftField3dp_(key);
    if (!Object.prototype.hasOwnProperty.call(expected, key)) {
      throw apiError3dp_('EXPECTED_REQUIRED', 'expected_current is missing for batch-draft field ' + key + '.');
    }
    const nextValue = normalizeBatchDraftValue3dp_(values[key], field);
    if (!equalCellValue3dp_(oldValues[key], expected[key])) {
      throw apiError3dp_('STALE_WRITE', 'Batch draft changed after it was read. Refresh and retry.');
    }
    nextValues[key] = nextValue;
    supplied.push(field);
  });

  const changed = supplied.filter(function (field) {
    return !equalCellValue3dp_(oldValues[field.key], nextValues[field.key]);
  });
  if (!changed.length) {
    return {
      action: '3dp_batch_draft_save',
      sku: sku,
      row: existingRow || null,
      values: oldValues,
      already_applied: true,
    };
  }

  const row = existingRow || nextInternalRow3dp_(drafts);
  const applied = [];
  try {
    if (!existingRow) {
      const skuRange = drafts.getRange(row, 1);
      if (skuRange.getFormula()) throw apiError3dp_('FORMULA_CELL', 'Batch-draft SKU key must remain a manual cell.');
      const oldSku = normalizeCellValue3dp_(skuRange.getValue());
      setCellValue3dp_(skuRange, sku);
      applied.push({ range: skuRange, oldRawValue: oldSku });
    }
    changed.forEach(function (field) {
      const range = drafts.getRange(row, BATCH_DRAFT_FIELDS_3DP.indexOf(field) + 2);
      if (range.getFormula()) throw apiError3dp_('FORMULA_CELL', 'Batch-draft fields must remain manual cells.');
      const oldRawValue = range.getValue();
      setCellValue3dp_(range, nextValues[field.key]);
      applied.push({ range: range, oldRawValue: oldRawValue });
    });
    appendAudit3dp_(
      spreadsheet,
      actor,
      'BATCH_DRAFT_SAVE',
      SHEETS_3DP.drafts,
      'sku:' + sku,
      oldValues,
      nextValues,
      'row=' + row
    );
  } catch (error) {
    applied.reverse().forEach(function (change) { setCellValue3dp_(change.range, change.oldRawValue); });
    throw error;
  }
  return { action: '3dp_batch_draft_save', sku: sku, row: row, values: nextValues };
}

function setNomenclatureArchiveAction3dp_(spreadsheet, body, actor, archive) {
  assertOwner3dp_(actor, 'Caller may not change SKU status.');
  const sheet = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
  const row = positiveRowNumber3dp_(body.row);
  assertRealNomenclatureRow3dp_(sheet, row);

  const statusRange = sheet.getRange(row, columnToNumber3dp_(API_3DP.nomenclatureStatusColumn));
  const historyRange = sheet.getRange(row, columnToNumber3dp_(API_3DP.nomenclatureHistoryColumn));
  if (statusRange.getFormula() || historyRange.getFormula()) {
    throw apiError3dp_('FORMULA_CELL', 'SKU archive system fields must remain manual cells.');
  }
  const oldStatus = nomenclatureStatusAtRow3dp_(sheet, row);
  const newStatus = archive ? API_3DP.archivedStatus : API_3DP.activeStatus;
  if (oldStatus === newStatus) {
    return { action: archive ? '3dp_nomenclature_archive' : '3dp_nomenclature_restore', row: row, already_applied: true };
  }
  if (Object.prototype.hasOwnProperty.call(body, 'expected_status') && String(body.expected_status) !== oldStatus) {
    throw apiError3dp_('STALE_WRITE', 'SKU status changed after it was read. Refresh and retry.');
  }

  const oldHistory = String(historyRange.getValue() || '');
  const reason = optionalReason3dp_(body.reason);
  const summary = 'Статус: ' + oldStatus + ' → ' + newStatus + (reason ? '; причина: ' + reason : '');
  const newHistory = appendHistory3dp_(oldHistory, historyLine3dp_(actor, summary));
  try {
    statusRange.setValue(newStatus);
    historyRange.setValue(newHistory);
    appendAudit3dp_(
      spreadsheet,
      actor,
      archive ? 'NOMENCLATURE_ARCHIVE' : 'NOMENCLATURE_RESTORE',
      SHEETS_3DP.nomenclature,
      API_3DP.nomenclatureStatusColumn + row,
      oldStatus,
      newStatus,
      summary
    );
  } catch (error) {
    statusRange.setValue(oldStatus);
    historyRange.setValue(oldHistory);
    throw error;
  }
  return { action: archive ? '3dp_nomenclature_archive' : '3dp_nomenclature_restore', row: row, status: newStatus, history: newHistory };
}

function stockAdjustmentsAction3dp_(spreadsheet, params, actor) {
  assertOwner3dp_(actor, 'Caller may not read stock adjustments.');
  const sku = String(params.sku || '').trim();
  if (sku) {
    const nomenclature = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
    assertRealNomenclatureRow3dp_(nomenclature, resolveTargetRow3dp_(nomenclature, sku));
  }
  const limit = boundedLimit3dp_(params.limit, 50, 100);
  const sheet = getInternalSheet3dp_(spreadsheet, SHEETS_3DP.stockAdjustments);
  const lastRow = Math.min(sheet.getLastRow(), API_3DP.maxReadRows);
  if (lastRow < 2) return { action: '3dp_stock_adjustments', rows: [], count: 0 };
  const headers = sheet.getRange(1, 1, 1, STOCK_ADJUSTMENT_HEADERS_3DP.length).getDisplayValues()[0];
  const values = sheet.getRange(2, 1, lastRow - 1, STOCK_ADJUSTMENT_HEADERS_3DP.length).getValues();
  const reason = String(params.reason || '').trim();
  const rows = values.map(function (row, index) {
    return rowObject3dp_(headers, row, index + 2);
  }).filter(function (row) {
    return !isBlank3dp_(row.SKU) && (!sku || String(row.SKU) === sku) &&
        (!reason || stockReasonMatches3dp_(row['Причина'], reason));
  }).reverse().slice(0, limit);
  return { action: '3dp_stock_adjustments', rows: rows, count: rows.length, sku: sku || null };
}

function stockReasonMatches3dp_(stored, requested) {
  const actual = String(stored || '').trim();
  const expected = String(requested || '').trim();
  return actual === expected || actual === expected + '; WARNING: insufficient stock';
}

function isAutomaticSaleAdjustmentReason3dp_(reason) {
  return /^auto:\s*CRM order\s+\S+/i.test(String(reason || '').trim());
}

function findExistingStockAdjustment3dp_(ledger, sku, reason) {
  const lastRow = Math.min(ledger.getLastRow(), API_3DP.maxReadRows);
  if (lastRow < 2) return 0;
  const values = ledger.getRange(2, 1, lastRow - 1, STOCK_ADJUSTMENT_HEADERS_3DP.length).getValues();
  for (let index = values.length - 1; index >= 0; index -= 1) {
    if (String(values[index][0] || '').trim() === sku && stockReasonMatches3dp_(values[index][2], reason)) {
      return index + 2;
    }
  }
  return 0;
}

function adjustStockAction3dp_(spreadsheet, body, actor) {
  assertOwner3dp_(actor, 'Caller may not adjust stock.');
  const sku = requiredSku3dp_(body.sku);
  const nomenclature = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
  const nomenclatureRow = resolveTargetRow3dp_(nomenclature, sku);
  assertRealNomenclatureRow3dp_(nomenclature, nomenclatureRow);
  if (nomenclatureStatusAtRow3dp_(nomenclature, nomenclatureRow) === API_3DP.archivedStatus) {
    throw apiError3dp_('SKU_ARCHIVED', 'Restore the SKU before adjusting its stock.');
  }
  if (!Object.prototype.hasOwnProperty.call(body, 'expected_current')) {
    throw apiError3dp_('EXPECTED_REQUIRED', 'expected_current is required for a stock adjustment.');
  }
  const reason = requiredReason3dp_(body.reason);
  const automaticSale = isAutomaticSaleAdjustmentReason3dp_(reason);
  const availability = getSheet3dp_(spreadsheet, SHEETS_3DP.availability);
  const availabilityRow = findAvailabilityRow3dp_(availability, sku);
  const stockRange = availability.getRange(availabilityRow, 7);
  const formula = stockRange.getFormula();
  if (!formula || canonicalFormula3dp_(formula).indexOf(SHEETS_3DP.stockAdjustments) === -1) {
    throw apiError3dp_('STOCK_FORMULA_NOT_READY', 'Run setup3dpApiAddendum2 before adjusting stock.');
  }

  const ledger = getInternalSheet3dp_(spreadsheet, SHEETS_3DP.stockAdjustments);
  const existingLedgerRow = findExistingStockAdjustment3dp_(ledger, sku, reason);
  if (existingLedgerRow) {
    return { action: '3dp_adjust_stock', sku: sku, already_applied: true, ledger_row: existingLedgerRow, reason: reason };
  }

  const oldValue = automaticSale
    ? signedWholeNumber3dp_(stockRange.getValue(), 'Current stock must be a whole number.')
    : inventoryWholeNumber3dp_(stockRange.getValue(), 'Current stock must be a non-negative whole number.');
  if (!equalCellValue3dp_(oldValue, body.expected_current)) {
    throw apiError3dp_('STALE_WRITE', 'Stock changed after it was read. Refresh and retry.');
  }

  const hasDelta = Object.prototype.hasOwnProperty.call(body, 'delta');
  const hasNewValue = Object.prototype.hasOwnProperty.call(body, 'new_value');
  if (hasDelta === hasNewValue) {
    throw apiError3dp_('STOCK_CHANGE_REQUIRED', 'Provide exactly one of delta or new_value.');
  }
  const delta = hasDelta
    ? signedWholeNumber3dp_(body.delta, 'delta must be a whole number.')
    : inventoryWholeNumber3dp_(body.new_value, 'new_value must be a non-negative whole number.') - oldValue;
  const newValue = oldValue + delta;
  if (newValue < 0 && !automaticSale) throw apiError3dp_('NEGATIVE_STOCK', 'Stock cannot become negative.');
  if (delta === 0) {
    return { action: '3dp_adjust_stock', sku: sku, old_value: oldValue, new_value: newValue, already_applied: true };
  }

  const ledgerRow = nextInternalRow3dp_(ledger);
  const ranges = [ledger.getRange(ledgerRow, 1), ledger.getRange(ledgerRow, 2), ledger.getRange(ledgerRow, 3), ledger.getRange(ledgerRow, 4)];
  ranges.forEach(function (range) {
    if (range.getFormula()) throw apiError3dp_('FORMULA_CELL', 'Stock-adjustment ledger must remain manual cells.');
  });
  const oldRawValues = ranges.map(function (range) { return range.getValue(); });
  const warning = newValue < 0 ? 'insufficient_stock' : '';
  const ledgerReason = warning ? reason + '; WARNING: insufficient stock' : reason;
  const ledgerValues = [sku, delta, ledgerReason, now3dp_()];
  try {
    ranges.forEach(function (range, index) { setCellValue3dp_(range, ledgerValues[index]); });
    appendAudit3dp_(spreadsheet, actor, 'STOCK_ADJUSTMENT', SHEETS_3DP.availability, 'G' + availabilityRow, oldValue, newValue,
      'sku=' + sku + '; delta=' + delta + '; reason=' + ledgerReason + '; ledger_row=' + ledgerRow);
  } catch (error) {
    ranges.forEach(function (range, index) { setCellValue3dp_(range, oldRawValues[index]); });
    throw error;
  }
  return { action: '3dp_adjust_stock', sku: sku, row: availabilityRow, old_value: oldValue, new_value: newValue, delta: delta,
    ledger_row: ledgerRow, warning: warning || null };
}
/**
 * The Web App has already acquired the script lock in doPost(). Keep this
 * wrapper lock-free so the owner-only setup route cannot deadlock itself.
 */
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

function setupAddendum2Action3dp_(spreadsheet, actor) {
  assertOwner3dp_(actor, 'Caller may not run Addendum #2 setup.');
  return setup3dpApiAddendum2Unlocked3dp_(spreadsheet);
}

function assertPrintLogRole3dp_(actor) {
  if (!actor || ['owner', 'serhiy'].indexOf(actor.role) === -1) {
    throw apiError3dp_('FORBIDDEN', 'Caller may not change print-log rows.');
  }
}

function assertRealPrintLogRow3dp_(sheet, row) {
  if (row < 2 || row > sheet.getLastRow()) throw apiError3dp_('ROW_NOT_FOUND', 'Print-log row not found.');
  const sku = String(sheet.getRange(row, 2).getValue() || '').trim();
  if (!sku || isPlaceholderSku3dp_(sku)) throw apiError3dp_('ROW_NOT_ALLOWED', 'Illustrative or empty rows cannot be edited through this action.');
}

function assertTechnicalSaleAppendAllowed3dp_(sheet, column, value, actor) {
  if (!actor || actor.role !== 'owner' || column !== 'T') {
    throw apiError3dp_('COLUMN_NOT_ALLOWED', 'Technical sale columns are reserved for the CRM order hook.');
  }
  if (sheet.getRange('T1').getDisplayValue() !== API_3DP.salesCrmRowHeader) {
    throw apiError3dp_('SCHEMA_NOT_READY', 'Run 3D-P-010 setup before appending Продажі rows.');
  }
  if (typeof value !== 'number' || !Number.isInteger(value) || value < 3) {
    throw apiError3dp_('CRM_ROW_INVALID', 'CRM row number must be a whole number >= 3.');
  }
}

function assertCellWriteAllowed3dp_(sheetName, column, actor) {
  const byRole = actor.role === 'serhiy' ? SERHIY_MANUAL_COLUMNS_3DP : OWNER_MANUAL_COLUMNS_3DP;
  const allowed = byRole[sheetName] || [];
  if (allowed.indexOf(column) === -1) {
    throw apiError3dp_('COLUMN_NOT_ALLOWED', 'Column is not a whitelisted manual-input field for this caller.');
  }
}

function assertWriteTargetAllowed3dp_(sheetName, column, row) {
  if (sheetName === SHEETS_3DP.settings && (column !== 'B' || row < 2 || row > 4)) {
    throw apiError3dp_('ROW_NOT_ALLOWED', 'Only Налаштування!B2:B4 can be changed through the API.');
  }
}

function assertOwner3dp_(actor, message) {
  if (!actor || actor.role !== 'owner') throw apiError3dp_('FORBIDDEN', message || 'Owner access is required.');
}

function assertBatchDraftRole3dp_(actor) {
  if (!actor || ['owner', 'serhiy'].indexOf(actor.role) === -1) {
    throw apiError3dp_('FORBIDDEN', 'Caller may not read or save batch drafts.');
  }
}

function requiredSku3dp_(value) {
  const sku = String(value || '').trim();
  if (!sku) throw apiError3dp_('SKU_REQUIRED', 'sku is required.');
  if (isPlaceholderSku3dp_(sku)) throw apiError3dp_('ROW_FILTERED', 'Illustrative or placeholder rows are not available.');
  return sku;
}

function getInternalSheet3dp_(spreadsheet, sheetName) {
  if ([SHEETS_3DP.drafts, SHEETS_3DP.stockAdjustments].indexOf(sheetName) === -1) {
    throw apiError3dp_('SHEET_NOT_ALLOWED', 'Internal sheet is not allowed.');
  }
  const sheet = spreadsheet.getSheetByName(sheetName);
  if (!sheet) throw apiError3dp_('SHEET_NOT_FOUND', 'Run setup3dpApiAddendum2 before using ' + sheetName + '.');
  return sheet;
}

function batchDraftField3dp_(key) {
  const field = BATCH_DRAFT_FIELDS_3DP.filter(function (candidate) { return candidate.key === key; })[0];
  if (!field) throw apiError3dp_('FIELD_NOT_ALLOWED', 'Unsupported batch-draft field.');
  return field;
}

function batchDraftValues3dp_(sheet, row) {
  return BATCH_DRAFT_FIELDS_3DP.reduce(function (result, field, index) {
    result[field.key] = row ? normalizeCellValue3dp_(sheet.getRange(row, index + 2).getValue()) : '';
    return result;
  }, {});
}

function normalizeBatchDraftValue3dp_(value, field) {
  assertManualValue3dp_(value);
  if (isBlank3dp_(value)) return '';
  const parsed = Number(value);
  if (!isFinite(parsed) || parsed < 0 || (field.integer && !Number.isInteger(parsed))) {
    throw apiError3dp_('INVALID_DRAFT_VALUE', field.key + ' must be a non-negative ' + (field.integer ? 'whole number.' : 'number.'));
  }
  return parsed;
}

function findBatchDraftRow3dp_(sheet, sku) {
  const lastRow = sheet.getLastRow();
  if (lastRow < 2) return 0;
  const values = sheet.getRange(2, 1, lastRow - 1, 1).getValues();
  let found = 0;
  values.forEach(function (row, index) {
    if (String(row[0] || '').trim() === sku) {
      if (found) throw apiError3dp_('DUPLICATE_KEY', 'Batch-draft storage has duplicate SKU keys.');
      found = index + 2;
    }
  });
  return found;
}

function nextInternalRow3dp_(sheet) {
  const row = Math.max(sheet.getLastRow() + 1, 2);
  if (row > sheet.getMaxRows()) throw apiError3dp_('NO_EMPTY_ROW', 'No prepared row is available in the internal ledger.');
  return row;
}

function assertRealNomenclatureRow3dp_(sheet, row) {
  if (row < 2 || row > sheet.getLastRow()) throw apiError3dp_('ROW_NOT_FOUND', 'SKU row not found.');
  const sku = String(sheet.getRange(row, 1).getValue() || '').trim();
  if (!sku || isPlaceholderSku3dp_(sku)) throw apiError3dp_('ROW_NOT_ALLOWED', 'Illustrative or empty SKU rows cannot be changed.');
  return sku;
}

function assertNomenclatureArchiveSystemReady3dp_(sheet) {
  const headers = sheet.getRange('O1:P1').getDisplayValues()[0];
  if (headers[0] !== 'API_статус_запису' || headers[1] !== 'API_історія_змін') {
    throw apiError3dp_('ARCHIVE_SYSTEM_NOT_READY', 'Run setup3dpApiAddendum2 before changing SKU archive state.');
  }
}

function nomenclatureStatusAtRow3dp_(sheet, row) {
  assertNomenclatureArchiveSystemReady3dp_(sheet);
  const status = String(sheet.getRange(row, columnToNumber3dp_(API_3DP.nomenclatureStatusColumn)).getValue() || API_3DP.activeStatus);
  if ([API_3DP.activeStatus, API_3DP.archivedStatus].indexOf(status) === -1) {
    throw apiError3dp_('INVALID_STATUS', 'SKU status must be Активний or Архів.');
  }
  return status;
}

function optionalReason3dp_(value) {
  const reason = String(value || '').trim();
  if (!reason) return '';
  assertManualValue3dp_(reason);
  if (reason.length > API_3DP.maxStockAdjustmentReasonChars) {
    throw apiError3dp_('REASON_TOO_LONG', 'Reason exceeds the allowed length.');
  }
  return reason;
}

function requiredReason3dp_(value) {
  if (typeof value !== 'string') throw apiError3dp_('REASON_REQUIRED', 'A short reason is required.');
  const reason = optionalReason3dp_(value);
  if (reason.length < 3) throw apiError3dp_('REASON_REQUIRED', 'A short reason of at least 3 characters is required.');
  return reason;
}

function inventoryWholeNumber3dp_(value, message) {
  if (isBlank3dp_(value)) throw apiError3dp_('INVALID_STOCK_VALUE', message);
  const parsed = Number(value);
  if (!isFinite(parsed) || !Number.isInteger(parsed) || parsed < 0) {
    throw apiError3dp_('INVALID_STOCK_VALUE', message);
  }
  return parsed;
}

function signedWholeNumber3dp_(value, message) {
  if (isBlank3dp_(value)) throw apiError3dp_('INVALID_STOCK_VALUE', message);
  const parsed = Number(value);
  if (!isFinite(parsed) || !Number.isInteger(parsed)) throw apiError3dp_('INVALID_STOCK_VALUE', message);
  return parsed;
}

function boundedLimit3dp_(value, defaultValue, maxValue) {
  if (isBlank3dp_(value)) return defaultValue;
  const parsed = Number(value);
  if (!Number.isInteger(parsed) || parsed < 1 || parsed > maxValue) {
    throw apiError3dp_('INVALID_LIMIT', 'limit must be a whole number from 1 to ' + maxValue + '.');
  }
  return parsed;
}

function findAvailabilityRow3dp_(sheet, sku) {
  const lastRow = Math.min(sheet.getLastRow(), API_3DP.maxReadRows);
  if (lastRow < 2) throw apiError3dp_('ROW_NOT_FOUND', 'Availability row not found.');
  const values = sheet.getRange(2, 1, lastRow - 1, 1).getValues();
  for (let index = 0; index < values.length; index += 1) {
    if (String(values[index][0] || '').trim() === sku) return index + 2;
  }
  throw apiError3dp_('ROW_NOT_FOUND', 'Availability row not found for SKU.');
}
function resolveTargetRow3dp_(sheet, skuOrRow) {
  if (typeof skuOrRow === 'number' || /^\d+$/.test(String(skuOrRow || ''))) {
    const row = positiveRowNumber3dp_(skuOrRow);
    if (row > sheet.getMaxRows()) throw apiError3dp_('ROW_NOT_FOUND', 'Row is outside the sheet.');
    return row;
  }
  const sku = String(skuOrRow || '').trim();
  if (!sku) throw apiError3dp_('ROW_REQUIRED', 'sku_or_row is required.');
  const lastRow = sheet.getLastRow();
  if (lastRow < 2) throw apiError3dp_('ROW_NOT_FOUND', 'SKU not found.');
  const values = sheet.getRange(2, 1, lastRow - 1, 1).getValues();
  for (let index = 0; index < values.length; index += 1) {
    if (String(values[index][0] || '').trim() === sku) return index + 2;
  }
  throw apiError3dp_('ROW_NOT_FOUND', 'SKU not found.');
}

function findFirstBusinessEmptyRow3dp_(sheet, sheetName, actor) {
  const ownerColumns = OWNER_MANUAL_COLUMNS_3DP[sheetName];
  if (!ownerColumns) throw apiError3dp_('SHEET_NOT_WRITABLE', 'Rows cannot be appended to this sheet.');
  const columns = actor.role === 'serhiy' ? (SERHIY_MANUAL_COLUMNS_3DP[sheetName] || []) : ownerColumns;
  if (!columns.length) throw apiError3dp_('SHEET_NOT_WRITABLE', 'Caller cannot append rows to this sheet.');
  const maxRow = Math.min(Math.max(sheet.getLastRow() + 1, 2), sheet.getMaxRows());
  const occupiedColumns = ownerColumns.concat(TECHNICAL_APPEND_COLUMNS_3DP[sheetName] || []);
  for (let row = 2; row <= maxRow; row += 1) {
    const empty = occupiedColumns.every(function (column) {
      return isBlank3dp_(sheet.getRange(row, columnToNumber3dp_(column)).getValue());
    });
    const unusedStatus = sheetName === SHEETS_3DP.nomenclature &&
      !isBlank3dp_(sheet.getRange(row, columnToNumber3dp_(API_3DP.nomenclatureStatusColumn)).getValue());
    if (empty && !unusedStatus) return row;
  }
  throw apiError3dp_('NO_EMPTY_ROW', 'No empty prepared row is available.');
}

function copyFormulaCells3dp_(sheet, sheetName, row) {
  const formulaColumns = FORMULA_COLUMNS_3DP[sheetName] || [];
  if (row <= 2) return;
  formulaColumns.forEach(function (column) {
    const source = sheet.getRange(row - 1, columnToNumber3dp_(column));
    const target = sheet.getRange(row, columnToNumber3dp_(column));
    if (!target.getFormula() && source.getFormula()) {
      source.copyTo(target, SpreadsheetApp.CopyPasteType.PASTE_FORMULA, false);
    }
  });
}

function resolveColumn3dp_(sheet, input) {
  const value = String(input || '').trim();
  if (/^[A-Z]{1,2}$/i.test(value)) return value.toUpperCase();
  const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getDisplayValues()[0];
  const index = headers.indexOf(value);
  if (index === -1) throw apiError3dp_('COLUMN_NOT_FOUND', 'Column header not found.');
  return numberToColumn3dp_(index + 1);
}

function appendAudit3dp_(spreadsheet, actor, operation, sheetName, target, oldValue, newValue, details) {
  let audit = spreadsheet.getSheetByName(API_3DP.auditSheet);
  if (!audit) throw apiError3dp_('AUDIT_NOT_INITIALIZED', 'Run setup3dpApi before enabling writes.');
  audit.appendRow([
    now3dp_(),
    actor.identity,
    operation,
    sheetName,
    target,
    displayAuditValue3dp_(oldValue),
    displayAuditValue3dp_(newValue),
    String(details || ''),
  ]);
}

function historyLine3dp_(actor, description) {
  return now3dp_() + ' [' + actor.identity + '] ' + String(description || '');
}

function appendHistory3dp_(current, line) {
  return current ? current + '\n' + line : line;
}

function now3dp_() {
  return Utilities.formatDate(new Date(), API_3DP.timezone, 'yyyy-MM-dd HH:mm:ss');
}

function displayAuditValue3dp_(value) {
  if (value === null || typeof value === 'undefined' || value === '') return '∅';
  if (value instanceof Date) return Utilities.formatDate(value, API_3DP.timezone, 'yyyy-MM-dd HH:mm:ss');
  if (typeof value === 'object') return JSON.stringify(value);
  return String(value);
}

function assertManualValue3dp_(value) {
  if (typeof value === 'string' && value.trim().charAt(0) === '=') {
    throw apiError3dp_('FORMULA_VALUE_NOT_ALLOWED', 'Manual-input values cannot start with =.');
  }
}

function setCellValue3dp_(range, value) {
  if (value === null || typeof value === 'undefined' || value === '') range.clearContent();
  else range.setValue(value);
}

function equalCellValue3dp_(left, right) {
  const normalize = function (value) {
    if (value instanceof Date) return value.toISOString();
    if (value === null || typeof value === 'undefined' || value === '') return '';
    if (typeof value === 'number') return String(value);
    if (typeof value === 'boolean') return value ? 'true' : 'false';
    return String(value);
  };
  return normalize(left) === normalize(right);
}

function normalizeCellValue3dp_(value) {
  if (value instanceof Date) return Utilities.formatDate(value, API_3DP.timezone, 'yyyy-MM-dd HH:mm:ss');
  if (typeof value === 'undefined') return null;
  return value;
}

function normalizeMatrix3dp_(matrix) {
  return matrix.map(function (row) { return row.map(normalizeCellValue3dp_); });
}

function number3dp_(value) {
  if (typeof value === 'number' && isFinite(value)) return value;
  const parsed = Number(String(value || '').replace(/\s/g, '').replace(',', '.').replace(/[^0-9.\-]/g, ''));
  return isFinite(parsed) ? parsed : 0;
}

function isBlank3dp_(value) {
  return value === null || typeof value === 'undefined' || String(value).trim() === '';
}

function positiveRowNumber3dp_(value) {
  const row = Number(value);
  if (!Number.isInteger(row) || row < 2) throw apiError3dp_('INVALID_ROW', 'A data row number (2 or greater) is required.');
  return row;
}

function parseBoundedRange3dp_(input) {
  const raw = String(input || '').trim().toUpperCase();
  const match = raw.match(/^([A-Z]{1,2})([1-9]\d*)(?::([A-Z]{1,2})([1-9]\d*))?$/);
  if (!match) throw apiError3dp_('INVALID_RANGE', 'Use a bounded A1 range such as A1:F20.');
  const startColumn = columnToNumber3dp_(match[1]);
  const startRow = Number(match[2]);
  const endColumn = columnToNumber3dp_(match[3] || match[1]);
  const endRow = Number(match[4] || match[2]);
  if (endColumn < startColumn || endRow < startRow) throw apiError3dp_('INVALID_RANGE', 'Range end must follow range start.');
  const cells = (endColumn - startColumn + 1) * (endRow - startRow + 1);
  if (cells > API_3DP.maxRangeCells) throw apiError3dp_('RANGE_TOO_LARGE', 'Requested range exceeds the bounded-read limit.');
  return { a1: raw, startColumn: startColumn, startRow: startRow, endColumn: endColumn, endRow: endRow, cells: cells };
}

function columnToNumber3dp_(column) {
  const letters = String(column || '').toUpperCase();
  let result = 0;
  for (let index = 0; index < letters.length; index += 1) {
    result = result * 26 + letters.charCodeAt(index) - 64;
  }
  if (!result) throw apiError3dp_('INVALID_COLUMN', 'Invalid column.');
  return result;
}

function numberToColumn3dp_(number) {
  let value = Number(number);
  let result = '';
  while (value > 0) {
    value -= 1;
    result = String.fromCharCode(65 + value % 26) + result;
    value = Math.floor(value / 26);
  }
  return result;
}

function actionNameForSheet3dp_(sheetName) {
  if (sheetName === SHEETS_3DP.sales) return '3dp_sales';
  if (sheetName === SHEETS_3DP.payouts) return '3dp_payouts';
  if (sheetName === SHEETS_3DP.plyushky) return '3dp_plyushky';
  if (sheetName === SHEETS_3DP.fixtures) return '3dp_fixtures';
  return '3dp_table';
}

function withScriptLock3dp_(callback) {
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(15000)) throw apiError3dp_('BUSY', 'Another write is in progress. Retry after refreshing.');
  try {
    return callback();
  } finally {
    lock.releaseLock();
  }
}

function respond3dp_(callback) {
  let payload;
  try {
    payload = Object.assign({ ok: true }, callback());
  } catch (error) {
    payload = { ok: false, error: error && error.message ? error.message : 'Unexpected API error.', code: error && error.code ? error.code : 'INTERNAL_ERROR' };
  }
  return ContentService.createTextOutput(JSON.stringify(payload)).setMimeType(ContentService.MimeType.JSON);
}

function apiError3dp_(code, message) {
  const error = new Error(message);
  error.code = code;
  return error;
}

/**
 * Read-only preflight for Addendum #2. It must pass before the owner runs
 * setup3dpApiAddendum2() in the bound 3D-P spreadsheet.
 */
function preview3dpApiAddendum2() {
  const spreadsheet = getSpreadsheet3dp_();
  validateSetupAnchors3dp_(spreadsheet);
  validateAddendum2Prerequisites3dp_(spreadsheet);
  validateNomenclatureArchiveState3dp_(spreadsheet);
  return {
    ok: true,
    spreadsheet_id: spreadsheet.getId(),
    planned_changes: [
      'Налаштування!B2:B4 becomes owner-writable only through guarded 3dp_write',
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
/**
 * Read-only preflight for the one-time schema setup. Safe to run repeatedly.
 */
function preview3dpApiSetup() {
  const spreadsheet = getSpreadsheet3dp_();
  validateSetupAnchors3dp_(spreadsheet);
  return {
    ok: true,
    spreadsheet_id: spreadsheet.getId(),
    planned_changes: [
      'Номенклатура: delete legacy O combined-amortization column, replace plastic and price/kg inputs with per-unit weight plus spool weight/price, and clear legacy H:J values',
      'Налаштування: create editable global constants for printer power, electricity price, and printer amortization',
      'Фурнітура_довідник: create the name/price reference list for calculator dropdowns',
      'Номенклатура: make K use the final spool-based material, electricity, amortization, and fixture formula',
      'Друк-лог: rename the existing editable defect field to Брак, шт; existing edit-with-history path remains in use',
      'Друк-лог: add J status and K per-row change history; no row deletion',
      'Наявність: exclude Друк-лог rows whose API status is Архів',
      'Normalize approved manual-input columns to blue font on prepared non-example rows',
      'Create and hide _Аудит_API',
    ],
  };
}

/**
 * Owner-run, idempotent setup. This changes only the approved 3D-P Sheet schema.
 */
function setup3dpApi() {
  return withScriptLock3dp_(function () {
    const spreadsheet = getSpreadsheet3dp_();
    validateSetupAnchors3dp_(spreadsheet);
    const changes = [];

    setupGlobalSettings3dp_(spreadsheet, changes);
    setupNomenclatureFinalCostSchema3dp_(spreadsheet, changes);
    setupFixturesReference3dp_(spreadsheet, changes);
    setupPrintLogSystemColumns3dp_(spreadsheet, changes);
    setupAvailabilityArchiveAwareFormulas3dp_(spreadsheet, changes);
    normalizeManualInputColors3dp_(spreadsheet, changes);
    setupAuditSheet3dp_(spreadsheet, changes);

    return { ok: true, already_applied: changes.length === 0, changes: changes };
  });
}

/**
 * Owner-run targeted repair for archive-aware availability formulas.
 * Safe to repeat; touches only Наявність!C:D formula cells.
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

function validateSetupAnchors3dp_(spreadsheet) {
  const expected = {};
  expected[SHEETS_3DP.nomenclature] = [
    'SKU', 'Назва виробу', 'Франшиза', 'Тип', 'Трек', 'Статус', ['Час друку, год', 'Час друку за од., год'],
    ['Матеріал (пластик)', 'Вага виробу за од., г'], ['Витрата матеріалу, г', 'Вага котушки, г'],
    ['Ціна матеріалу, грн/кг', 'Ціна котушки, грн'], [
      'Собівартість Сергія (матеріал+фурнітура), грн',
      'Собівартість Сергія (матеріал+фурнітура+амортизація), грн',
      'Собівартість Сергія (виробнича), грн',
    ],
    'Дата оновлення', 'Примітки', 'Фурнітура (ланцюжок/карабін), грн/шт',
  ];
  expected[SHEETS_3DP.printLog] = [
    'Дата', 'SKU', 'Надруковано, шт', 'Час друку факт, год', ['Брак/відходи, шт', 'Брак, шт'],
    'Витрачено матеріалу, г (факт)', 'Собівартість партії, грн', 'Хто друкував', 'Примітки',
  ];
  expected[SHEETS_3DP.availability] = [
    'SKU', 'Назва', 'Надруковано всього, шт', 'Брак всього, шт', 'Продано на сайті, шт',
    'Видано як плюшка, шт', 'Наявно зараз, шт',
  ];

  Object.keys(expected).forEach(function (sheetName) {
    const sheet = getSheet3dp_(spreadsheet, sheetName);
    const headers = sheet.getRange(1, 1, 1, expected[sheetName].length).getDisplayValues()[0];
    expected[sheetName].forEach(function (header, index) {
      const allowed = Array.isArray(header) ? header : [header];
      if (allowed.indexOf(headers[index]) === -1) {
        throw apiError3dp_('SETUP_ANCHOR_MISMATCH', sheetName + '!' + numberToColumn3dp_(index + 1) + '1 header mismatch.');
      }
    });
  });
}

function validateAddendum2Prerequisites3dp_(spreadsheet) {
  const nomenclature = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
  const finalHeaders = ['Час друку за од., год', 'Вага виробу за од., г', 'Вага котушки, г', 'Ціна котушки, грн'];
  if (JSON.stringify(nomenclature.getRange('G1:J1').getDisplayValues()[0]) !== JSON.stringify(finalHeaders) ||
      nomenclature.getRange('K1').getDisplayValue() !== 'Собівартість Сергія (виробнича), грн') {
    throw apiError3dp_('ADDENDUM_1_REQUIRED', 'Run and live-verify the 2026-08-02 final-cost schema correction before Addendum #2.');
  }
  const settings = spreadsheet.getSheetByName(API_3DP.settingsSheet);
  if (!settings || settings.getRange('A2:A4').getDisplayValues().map(function (row) { return row[0]; }).join('|') !==
      'Потужність принтера, кВт|Ціна електроенергії, грн/кВт·год|Амортизація принтера, грн/год') {
    throw apiError3dp_('ADDENDUM_1_REQUIRED', 'Approved Налаштування!B2:B4 is required before Addendum #2.');
  }
  const printLog = getSheet3dp_(spreadsheet, SHEETS_3DP.printLog);
  if (printLog.getRange('J1:K1').getDisplayValues()[0].join('|') !== 'API_статус_запису|API_історія_змін') {
    throw apiError3dp_('ADDENDUM_1_REQUIRED', 'Approved Друк-лог archive/history columns are required before Addendum #2.');
  }
}
function validateNomenclatureArchiveState3dp_(spreadsheet) {
  const sheet = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
  const expected = [
    { column: API_3DP.nomenclatureStatusColumn, header: 'API_статус_запису' },
    { column: API_3DP.nomenclatureHistoryColumn, header: 'API_історія_змін' },
  ];
  expected.forEach(function (item) {
    const current = String(sheet.getRange(item.column + '1').getDisplayValue() || '');
    if (current && current !== item.header) {
      throw apiError3dp_('SETUP_ANCHOR_MISMATCH', SHEETS_3DP.nomenclature + '!' + item.column + '1 is occupied by an unexpected column.');
    }
  });

  const lastRow = sheet.getLastRow();
  if (lastRow < 2) return [];
  const statuses = sheet.getRange(2, columnToNumber3dp_(API_3DP.nomenclatureStatusColumn), lastRow - 1, 1).getValues();
  const blankStatusRows = [];
  statuses.forEach(function (values, index) {
    const status = String(values[0] || '').trim();
    if (!status) {
      blankStatusRows.push(index + 2);
      return;
    }
    if ([API_3DP.activeStatus, API_3DP.archivedStatus].indexOf(status) === -1) {
      throw apiError3dp_('SETUP_ANCHOR_MISMATCH', SHEETS_3DP.nomenclature + '!' + API_3DP.nomenclatureStatusColumn + (index + 2) + ' has an unsupported archive state.');
    }
  });
  return blankStatusRows;
}

function setupNomenclatureArchiveSystem3dp_(spreadsheet, changes) {
  const sheet = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
  const expected = [
    { column: API_3DP.nomenclatureStatusColumn, header: 'API_статус_запису' },
    { column: API_3DP.nomenclatureHistoryColumn, header: 'API_історія_змін' },
  ];
  expected.forEach(function (item) {
    if (!sheet.getRange(item.column + '1').getDisplayValue()) {
      sheet.getRange('N1').copyTo(sheet.getRange(item.column + '1'), SpreadsheetApp.CopyPasteType.PASTE_FORMAT, false);
      sheet.getRange(item.column + '1').setValue(item.header);
      changes.push(SHEETS_3DP.nomenclature + '!' + item.column + '1 added');
    }
  });
  const blankStatusRows = validateNomenclatureArchiveState3dp_(spreadsheet);
  blankStatusRows.forEach(function (row) {
    const sku = String(sheet.getRange(row, 1).getValue() || '').trim();
    if (sku && !isPlaceholderSku3dp_(sku)) {
      sheet.getRange(row, columnToNumber3dp_(API_3DP.nomenclatureStatusColumn)).setValue(API_3DP.activeStatus);
    }
  });
  const realCount = blankStatusRows.filter(function (row) {
    const sku = String(sheet.getRange(row, 1).getValue() || '').trim();
    return sku && !isPlaceholderSku3dp_(sku);
  }).length;
  if (realCount) {
    changes.push('Номенклатура!' + API_3DP.nomenclatureStatusColumn + ' initialized Активний for ' + realCount + ' real SKU row(s)');
  }
}

function setupBatchDraftStorage3dp_(spreadsheet, changes) {
  const headers = ['SKU'].concat(BATCH_DRAFT_FIELDS_3DP.map(function (field) { return field.header; }));
  let sheet = spreadsheet.getSheetByName(SHEETS_3DP.drafts);
  if (!sheet) {
    sheet = spreadsheet.insertSheet(SHEETS_3DP.drafts);
    sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
    sheet.setFrozenRows(1);
    sheet.hideSheet();
    changes.push(SHEETS_3DP.drafts + ' created and hidden for keyed raw batch drafts');
    return;
  }
  const currentHeaders = sheet.getRange(1, 1, 1, headers.length).getDisplayValues()[0];
  if (JSON.stringify(currentHeaders) !== JSON.stringify(headers)) {
    throw apiError3dp_('SETUP_ANCHOR_MISMATCH', SHEETS_3DP.drafts + '!A1:F1 headers do not match the approved batch-draft schema.');
  }
  if (!sheet.isSheetHidden()) {
    sheet.hideSheet();
    changes.push(SHEETS_3DP.drafts + ' hidden');
  }
}

function setupStockAdjustmentLedger3dp_(spreadsheet, changes) {
  let sheet = spreadsheet.getSheetByName(SHEETS_3DP.stockAdjustments);
  if (!sheet) {
    sheet = spreadsheet.insertSheet(SHEETS_3DP.stockAdjustments);
    sheet.getRange(1, 1, 1, STOCK_ADJUSTMENT_HEADERS_3DP.length).setValues([STOCK_ADJUSTMENT_HEADERS_3DP]);
    sheet.setFrozenRows(1);
    sheet.hideSheet();
    changes.push(SHEETS_3DP.stockAdjustments + ' created and hidden as append-only stock ledger');
    return;
  }
  const currentHeaders = sheet.getRange(1, 1, 1, STOCK_ADJUSTMENT_HEADERS_3DP.length).getDisplayValues()[0];
  if (JSON.stringify(currentHeaders) !== JSON.stringify(STOCK_ADJUSTMENT_HEADERS_3DP)) {
    throw apiError3dp_('SETUP_ANCHOR_MISMATCH', SHEETS_3DP.stockAdjustments + '!A1:D1 headers do not match the approved stock-ledger schema.');
  }
  if (!sheet.isSheetHidden()) {
    sheet.hideSheet();
    changes.push(SHEETS_3DP.stockAdjustments + ' hidden');
  }
}

function setupAvailabilityStockAdjustmentFormula3dp_(spreadsheet, changes) {
  const sheet = getSheet3dp_(spreadsheet, SHEETS_3DP.availability);
  const lastRow = Math.max(findLastFormulaRow3dp_(sheet, 'G'), 2);
  let formulaChanged = false;
  for (let row = 2; row <= lastRow; row += 1) {
    const expected = '=IF(A' + row + '="";"";C' + row + '-D' + row + '-E' + row + '-F' + row + '+SUMIF(\'' + SHEETS_3DP.stockAdjustments + '\'!$A:$A;A' + row + ';\'' + SHEETS_3DP.stockAdjustments + '\'!$B:$B))';
    const range = sheet.getRange(row, 7);
    if (canonicalFormula3dp_(range.getFormula()) !== canonicalFormula3dp_(expected)) {
      range.setFormula(expected);
      formulaChanged = true;
    }
  }
  if (formulaChanged) changes.push('Наявність!G2:G' + lastRow + ' now includes stock-adjustment ledger');
}
function setupNomenclatureFinalCostSchema3dp_(spreadsheet, changes) {
  const sheet = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
  const currentO = String(sheet.getRange('O1').getDisplayValue() || '');
  const legacyO = 'Комбінована амортизація, грн/год';
  if (currentO && currentO !== legacyO) throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Номенклатура!O1 is occupied by an unexpected column.');

  const legacyH = String(sheet.getRange('H1').getDisplayValue() || '') === 'Матеріал (пластик)';
  const legacyI = String(sheet.getRange('I1').getDisplayValue() || '') === 'Витрата матеріалу, г';
  const legacyJ = String(sheet.getRange('J1').getDisplayValue() || '') === 'Ціна матеріалу, грн/кг';
  const requiresLegacyClear = legacyH || legacyI || legacyJ;
  if (requiresLegacyClear && !(legacyH && legacyI && legacyJ)) {
    throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Номенклатура H:J is a partial legacy schema; stop before clearing values.');
  }
  if (requiresLegacyClear) {
    const lastRow = Math.max(sheet.getLastRow(), 2);
    sheet.getRange(2, 8, lastRow - 1, 3).clearContent();
    changes.push('Номенклатура!H2:J' + lastRow + ' legacy plastic/price-per-kg values cleared by owner-approved migration');
  }
  if (currentO) {
    sheet.deleteColumn(15);
    changes.push('Номенклатура!O legacy combined-amortization column removed');
  }

  const expectedHeaders = ['Час друку за од., год', 'Вага виробу за од., г', 'Вага котушки, г', 'Ціна котушки, грн'];
  const currentHeaders = sheet.getRange(1, 7, 1, 4).getDisplayValues()[0];
  if (JSON.stringify(currentHeaders) !== JSON.stringify(expectedHeaders)) {
    sheet.getRange(1, 7, 1, 4).setValues([expectedHeaders]);
    changes.push('Номенклатура!G1:J1 renamed for per-unit/spool inputs');
  }

  const expectedK = 'Собівартість Сергія (виробнича), грн';
  if (sheet.getRange('K1').getDisplayValue() !== expectedK) {
    sheet.getRange('K1').setValue(expectedK);
    changes.push('Номенклатура!K1 updated');
  }

  const lastFormulaRow = Math.max(findLastFormulaRow3dp_(sheet, 'K'), 2);
  let formulaChanged = false;
  for (let row = 2; row <= lastFormulaRow; row += 1) {
    const range = sheet.getRange(row, 11);
    const expectedFormula = '=IF(A' + row + '="";"";IFERROR(H' + row + '/I' + row + '*J' + row + '+G' + row + '*\'' + API_3DP.settingsSheet + '\'!$B$2*\'' + API_3DP.settingsSheet + '\'!$B$3+G' + row + '*\'' + API_3DP.settingsSheet + '\'!$B$4+N' + row + ';""))';
    if (canonicalFormula3dp_(range.getFormula()) !== canonicalFormula3dp_(expectedFormula)) {
      range.setFormula(expectedFormula);
      formulaChanged = true;
    }
  }
  if (formulaChanged) changes.push('Номенклатура!K2:K' + lastFormulaRow + ' formulas normalized');
}

function setupGlobalSettings3dp_(spreadsheet, changes) {
  let sheet = spreadsheet.getSheetByName(API_3DP.settingsSheet);
  const expected = [
    ['Глобальні константи 3D-друку', '', ''],
    ['Потужність принтера, кВт', 0.17, 'кВт'],
    ['Ціна електроенергії, грн/кВт·год', 4.32, 'грн/кВт·год'],
    ['Амортизація принтера, грн/год', 12, 'грн/год'],
  ];
  if (!sheet) {
    sheet = spreadsheet.insertSheet(API_3DP.settingsSheet);
    sheet.getRange(1, 1, 4, 3).setValues(expected);
    sheet.getRange('B2:B4').setFontColor('#0000ff');
    changes.push(API_3DP.settingsSheet + ' created with editable B2:B4 constants');
    return;
  }
  const current = sheet.getRange(1, 1, 4, 3).getDisplayValues();
  expected.forEach(function (row, rowIndex) {
    [0, 2].forEach(function (columnIndex) {
      if (String(current[rowIndex][columnIndex] || '') !== String(row[columnIndex] || '')) {
        throw apiError3dp_('SETUP_ANCHOR_MISMATCH', API_3DP.settingsSheet + ' structure differs from the approved settings block.');
      }
    });
  });
  const valuesRange = sheet.getRange('B2:B4');
  const colors = valuesRange.getFontColors();
  const alreadyBlue = colors.every(function (row) { return String(row[0] || '').toLowerCase() === '#0000ff'; });
  if (!alreadyBlue) {
    valuesRange.setFontColor('#0000ff');
    changes.push(API_3DP.settingsSheet + '!B2:B4 marked as editable settings');
  }
}

function setupFixturesReference3dp_(spreadsheet, changes) {
  let sheet = spreadsheet.getSheetByName(API_3DP.fixturesSheet);
  if (!sheet) {
    sheet = spreadsheet.insertSheet(API_3DP.fixturesSheet);
    sheet.getRange('A1:B1').setValues([['Назва фурнітури', 'Ціна, грн/шт']]);
    changes.push(API_3DP.fixturesSheet + ' created with name/price headers');
    return;
  }
  const headers = sheet.getRange('A1:B1').getDisplayValues()[0];
  if (headers[0] !== 'Назва фурнітури' || headers[1] !== 'Ціна, грн/шт') {
    throw apiError3dp_('SETUP_ANCHOR_MISMATCH', API_3DP.fixturesSheet + '!A1:B1 headers do not match the approved reference-list schema.');
  }
}
function setupPrintLogSystemColumns3dp_(spreadsheet, changes) {
  const sheet = getSheet3dp_(spreadsheet, SHEETS_3DP.printLog);
  const defectHeader = String(sheet.getRange('E1').getDisplayValue() || '');
  if (defectHeader !== 'Брак, шт') {
    if (defectHeader !== 'Брак/відходи, шт') throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Друк-лог!E1 is not the approved defect column.');
    sheet.getRange('E1').setValue('Брак, шт');
    changes.push('Друк-лог!E1 renamed to Брак, шт');
  }
  const headers = [
    { column: 'J', value: 'API_статус_запису' },
    { column: 'K', value: 'API_історія_змін' },
  ];
  headers.forEach(function (item) {
    const current = String(sheet.getRange(item.column + '1').getDisplayValue() || '');
    if (current && current !== item.value) throw apiError3dp_('SETUP_ANCHOR_MISMATCH', SHEETS_3DP.printLog + '!' + item.column + '1 is occupied.');
    if (!current) {
      sheet.getRange('I1').copyTo(sheet.getRange(item.column + '1'), SpreadsheetApp.CopyPasteType.PASTE_FORMAT, false);
      sheet.getRange(item.column + '1').setValue(item.value);
      changes.push(SHEETS_3DP.printLog + '!' + item.column + '1 added');
    }
  });

  const lastRow = sheet.getLastRow();
  let statusChanged = false;
  for (let row = 2; row <= lastRow; row += 1) {
    const sku = String(sheet.getRange(row, 2).getValue() || '').trim();
    const status = sheet.getRange(row, 10);
    if (sku && !status.getValue()) {
      status.setValue(API_3DP.activeStatus);
      statusChanged = true;
    }
  }
  if (statusChanged) changes.push(SHEETS_3DP.printLog + ' existing rows marked active');
}

function setupAvailabilityArchiveAwareFormulas3dp_(spreadsheet, changes) {
  const sheet = getSheet3dp_(spreadsheet, SHEETS_3DP.availability);
  const lastRow = Math.max(findLastFormulaRow3dp_(sheet, 'C'), findLastFormulaRow3dp_(sheet, 'D'), 2);
  let formulaChanged = false;
  for (let row = 2; row <= lastRow; row += 1) {
    const expectedC = '=IF(A' + row + '="";"";SUMIFS(\'Друк-лог\'!$C:$C;\'Друк-лог\'!$B:$B;A' + row + ';\'Друк-лог\'!$J:$J;"<>Архів"))';
    const expectedD = '=IF(A' + row + '="";"";SUMIFS(\'Друк-лог\'!$E:$E;\'Друк-лог\'!$B:$B;A' + row + ';\'Друк-лог\'!$J:$J;"<>Архів"))';
    const rangeC = sheet.getRange(row, 3);
    const rangeD = sheet.getRange(row, 4);
    if (canonicalFormula3dp_(rangeC.getFormula()) !== canonicalFormula3dp_(expectedC)) {
      rangeC.setFormula(expectedC);
      formulaChanged = true;
    }
    if (canonicalFormula3dp_(rangeD.getFormula()) !== canonicalFormula3dp_(expectedD)) {
      rangeD.setFormula(expectedD);
      formulaChanged = true;
    }
  }
  if (formulaChanged) changes.push('Наявність!C2:D' + lastRow + ' made archive-aware');
}

function normalizeManualInputColors3dp_(spreadsheet, changes) {
  const starts = {
    'Номенклатура': 3,
    'Друк-лог': 3,
    'Продажі': 3,
    'Виплати': 3,
    'Маркетингові_плюшки': 4,
  };
  Object.keys(OWNER_MANUAL_COLUMNS_3DP).forEach(function (sheetName) {
    const sheet = getSheet3dp_(spreadsheet, sheetName);
    const startRow = starts[sheetName] || 2;
    const endRow = Math.max(sheet.getLastRow(), startRow);
    OWNER_MANUAL_COLUMNS_3DP[sheetName].forEach(function (column) {
      const range = sheet.getRange(column + startRow + ':' + column + endRow);
      const colors = range.getFontColors();
      const alreadyBlue = colors.every(function (row) {
        return row.every(function (color) { return String(color || '').toLowerCase() === '#0000ff'; });
      });
      if (!alreadyBlue) {
        range.setFontColor('#0000ff');
        changes.push(sheetName + '!' + column + startRow + ':' + column + endRow + ' manual-input color normalized');
      }
    });
  });
}

function setupAuditSheet3dp_(spreadsheet, changes) {
  let sheet = spreadsheet.getSheetByName(API_3DP.auditSheet);
  if (!sheet) {
    sheet = spreadsheet.insertSheet(API_3DP.auditSheet);
    sheet.getRange(1, 1, 1, 8).setValues([[
      'timestamp_kyiv', 'identity', 'operation', 'sheet', 'target', 'old_value', 'new_value', 'details',
    ]]);
    sheet.setFrozenRows(1);
    changes.push(API_3DP.auditSheet + ' created');
  }
  if (!sheet.isSheetHidden()) {
    sheet.hideSheet();
    changes.push(API_3DP.auditSheet + ' hidden');
  }
}

function findLastFormulaRow3dp_(sheet, column) {
  const lastRow = Math.max(sheet.getLastRow(), 2);
  const formulas = sheet.getRange(2, columnToNumber3dp_(column), lastRow - 1, 1).getFormulas();
  for (let index = formulas.length - 1; index >= 0; index -= 1) {
    if (formulas[index][0]) return index + 2;
  }
  return 0;
}

function canonicalFormula3dp_(formula) {
  return String(formula || '').replace(/\s+/g, '');
}
