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
  printLogSheet: 'Друк-лог',
  printLogStatusColumn: 'J',
  printLogHistoryColumn: 'K',
  activeStatus: 'Активний',
  archivedStatus: 'Архів',
  maxRangeCells: 500,
  maxReadRows: 500,
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
});

// Grounded from the live formulas, the workbook Legend, and the owner-approved scope.
// K in Номенклатура and G in Друк-лог are deliberately absent because they are formulas.
const OWNER_MANUAL_COLUMNS_3DP = Object.freeze({
  'Номенклатура': Object.freeze(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'L', 'M', 'N', 'O']),
  'Друк-лог': Object.freeze(['A', 'B', 'C', 'D', 'E', 'F', 'H', 'I']),
  'Продажі': Object.freeze(['A', 'B', 'D', 'E', 'G', 'H', 'M', 'N', 'O', 'P', 'Q', 'R']),
  'Виплати': Object.freeze(['A', 'D', 'E', 'F']),
  'Маркетингові_плюшки': Object.freeze(['A', 'B', 'C', 'D', 'F', 'G', 'H']),
});

const SERHIY_MANUAL_COLUMNS_3DP = Object.freeze({
  'Номенклатура': Object.freeze(['G', 'H', 'I', 'J', 'L', 'M', 'N', 'O']),
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
      return skusAction3dp_(spreadsheet);
    case '3dp_sales':
      return tableAction3dp_(spreadsheet, SHEETS_3DP.sales, { requireHeader: 'SKU' });
    case '3dp_plyushky':
      return tableAction3dp_(spreadsheet, SHEETS_3DP.plyushky, { requireHeader: 'SKU' });
    case '3dp_payouts':
      return tableAction3dp_(spreadsheet, SHEETS_3DP.payouts, { requireHeader: 'Період (РРРР-ММ)' });
    case '3dp_print_log':
      return printLogAction3dp_(spreadsheet, params);
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

  const realSkus = nomenclature.rows.filter(function (row) {
    return !isPlaceholderSku3dp_(row.SKU);
  });
  const totals = availability.rows.reduce(function (result, row) {
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
      sku_count: realSkus.length,
      printed: totals.printed,
      defects: totals.defects,
      sold: totals.sold,
      given: totals.given,
      available: totals.available,
      accrued_serhiy_current_month: accrued,
    },
  };
}

function skusAction3dp_(spreadsheet) {
  const nomenclature = readTable3dp_(spreadsheet, SHEETS_3DP.nomenclature, { requireHeader: 'SKU' });
  const availability = readTable3dp_(spreadsheet, SHEETS_3DP.availability, { requireHeader: 'SKU' });
  const bySku = {};
  availability.rows.forEach(function (row) {
    bySku[String(row.SKU || '')] = row;
  });
  const rows = nomenclature.rows
    .filter(function (row) { return !isPlaceholderSku3dp_(row.SKU); })
    .map(function (row) {
      return Object.assign({}, row, { availability: bySku[String(row.SKU || '')] || null });
    });
  return { action: '3dp_skus', rows: rows, count: rows.length };
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
  const sheet = getSheet3dp_(spreadsheet, sheetName);
  const values = body.values;
  if (!values || typeof values !== 'object' || Array.isArray(values)) {
    throw apiError3dp_('VALUES_REQUIRED', 'values must be an object keyed by column or header.');
  }

  const normalized = {};
  Object.keys(values).forEach(function (key) {
    const column = resolveColumn3dp_(sheet, key);
    assertCellWriteAllowed3dp_(sheetName, column, actor);
    if (Object.prototype.hasOwnProperty.call(normalized, column)) {
      throw apiError3dp_('DUPLICATE_COLUMN', 'The same target column was supplied more than once.');
    }
    assertManualValue3dp_(values[key]);
    normalized[column] = values[key];
  });
  if (!Object.keys(normalized).length) throw apiError3dp_('VALUES_REQUIRED', 'At least one value is required.');

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

function assertCellWriteAllowed3dp_(sheetName, column, actor) {
  const byRole = actor.role === 'serhiy' ? SERHIY_MANUAL_COLUMNS_3DP : OWNER_MANUAL_COLUMNS_3DP;
  const allowed = byRole[sheetName] || [];
  if (allowed.indexOf(column) === -1) {
    throw apiError3dp_('COLUMN_NOT_ALLOWED', 'Column is not a whitelisted manual-input field for this caller.');
  }
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
  for (let row = 2; row <= maxRow; row += 1) {
    const empty = ownerColumns.every(function (column) {
      return isBlank3dp_(sheet.getRange(row, columnToNumber3dp_(column)).getValue());
    });
    if (empty) return row;
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
 * Read-only preflight for the one-time schema setup. Safe to run repeatedly.
 */
function preview3dpApiSetup() {
  const spreadsheet = getSpreadsheet3dp_();
  validateSetupAnchors3dp_(spreadsheet);
  return {
    ok: true,
    spreadsheet_id: spreadsheet.getId(),
    planned_changes: [
      'Номенклатура: add O header for combined amortization and include O*G in K formulas',
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

    setupNomenclatureAmortization3dp_(spreadsheet, changes);
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
    'SKU', 'Назва виробу', 'Франшиза', 'Тип', 'Трек', 'Статус', 'Час друку, год', 'Матеріал (пластик)',
    'Витрата матеріалу, г', 'Ціна матеріалу, грн/кг', [
      'Собівартість Сергія (матеріал+фурнітура), грн',
      'Собівартість Сергія (матеріал+фурнітура+амортизація), грн',
    ],
    'Дата оновлення', 'Примітки', 'Фурнітура (ланцюжок/карабін), грн/шт',
  ];
  expected[SHEETS_3DP.printLog] = [
    'Дата', 'SKU', 'Надруковано, шт', 'Час друку факт, год', 'Брак/відходи, шт',
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

function setupNomenclatureAmortization3dp_(spreadsheet, changes) {
  const sheet = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
  const currentO = String(sheet.getRange('O1').getDisplayValue() || '');
  const expectedO = 'Комбінована амортизація, грн/год';
  if (currentO && currentO !== expectedO) throw apiError3dp_('SETUP_ANCHOR_MISMATCH', 'Номенклатура!O1 is occupied by another column.');
  if (!currentO) {
    sheet.getRange('N1').copyTo(sheet.getRange('O1'), SpreadsheetApp.CopyPasteType.PASTE_FORMAT, false);
    sheet.getRange('O1').setValue(expectedO);
    changes.push('Номенклатура!O1 added');
  }

  const expectedK = 'Собівартість Сергія (матеріал+фурнітура+амортизація), грн';
  if (sheet.getRange('K1').getDisplayValue() !== expectedK) {
    sheet.getRange('K1').setValue(expectedK);
    changes.push('Номенклатура!K1 updated');
  }

  const lastFormulaRow = Math.max(findLastFormulaRow3dp_(sheet, 'K'), 2);
  let formulaChanged = false;
  for (let row = 2; row <= lastFormulaRow; row += 1) {
    const range = sheet.getRange(row, 11);
    const expectedFormula = '=I' + row + '*J' + row + '/1000+N' + row + '+O' + row + '*G' + row;
    if (canonicalFormula3dp_(range.getFormula()) !== canonicalFormula3dp_(expectedFormula)) {
      range.setFormula(expectedFormula);
      formulaChanged = true;
    }
  }
  if (formulaChanged) changes.push('Номенклатура!K2:K' + lastFormulaRow + ' formulas normalized');
}

function setupPrintLogSystemColumns3dp_(spreadsheet, changes) {
  const sheet = getSheet3dp_(spreadsheet, SHEETS_3DP.printLog);
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
