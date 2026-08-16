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
  fixtureReferenceHeader: 'Фурнітура (ціна-довідка), грн/шт',
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
  settingsJournal: '_Журнал_налаштувань_3DP',
});

const PRINT_TIME_ENTRY_3DP = Object.freeze({
  minHours: 0.02,
  maxHours: 100,
  numberFormat: '0.##########',
  nomenclature: Object.freeze({ sheet: SHEETS_3DP.nomenclature, column: 7, header: 'Час друку за од., год' }),
  printLog: Object.freeze({ sheet: SHEETS_3DP.printLog, column: 4, header: 'Час друку факт, год' }),
  headerNote: 'Вводьте десяткові години: 1,5 = 1 год 30 хв. Також можна ввести 1:30 або 1 год 30 хв; значення буде збережене як десяткові години.',
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
  'Продажі': Object.freeze(['F', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA']),
});

const ORDER_LINE_ACCOUNTING_COLUMNS_3DP = Object.freeze({
  X: 'Режим CRM',
  Y: 'Фурнітура власника за од., грн (заморожена)',
  Z: 'Фурнітура Сергія за од., грн (заморожена)',
  AA: 'Ціна викупу за од., грн (заморожена)',
});

const PRICE_MODEL_COLUMNS_3DP = Object.freeze({
  nomenclature: Object.freeze({
    Q: 'РРЦ фактична, грн',
    R: 'Ціна під викуп, грн',
    S: 'Посилання на модель',
  }),
  sales: Object.freeze({
    U: 'РРЦ на момент продажу, грн',
    V: 'Вартість фурнітури за од., грн (заморожена)',
    W: 'Платник фурнітури',
  }),
  analytics: Object.freeze([
    'SKU',
    'Назва',
    'Собівартість Сергія, грн',
    'Витрати BoosterShop (фурнітура), грн',
    'Час друку, год',
    '% прибутку Сергію',
    'РРЦ фактична',
    'РРЦ рекомендована',
    'Маржа BoosterShop, грн',
    'Маржа BoosterShop, %',
    'Нараховано Сергію, грн',
    'Прибуток Сергію/год друку, грн',
    '',
    '',
  ]),
});

const SALES_FROZEN_COLUMNS_3DP = Object.freeze(['F', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA']);
const SALES_CORRECTABLE_FROZEN_COLUMNS_3DP = Object.freeze(['V', 'W', 'X', 'Y', 'Z', 'AA']);
const SALES_3DP015_REQUIRED_COLUMNS_3DP = Object.freeze(['F', 'T', 'U', 'V', 'W']);
const SALES_ORDER_LINE_REQUIRED_COLUMNS_3DP = Object.freeze(['F', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA']);
const LEGACY_PRE_3DP015_SALES_FORMULA_COLUMNS_3DP = Object.freeze(['F']);

// Grounded from the live formulas, the workbook Legend, and the owner-approved scope.
// K in Номенклатура and G in Друк-лог are deliberately absent because they are formulas.
const OWNER_MANUAL_COLUMNS_3DP = Object.freeze({
  'Номенклатура': Object.freeze(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'L', 'M', 'N', 'Q', 'R', 'S']),
  'Друк-лог': Object.freeze(['A', 'B', 'C', 'D', 'E', 'F', 'H', 'I']),
  'Продажі': Object.freeze(['A', 'B', 'D', 'E', 'G', 'H', 'M', 'N', 'O', 'P', 'Q', 'R']),
  'Виплати': Object.freeze(['A', 'D', 'E', 'F']),
  'Маркетингові_плюшки': Object.freeze(['A', 'B', 'C', 'D', 'F', 'G', 'H']),
  'Налаштування': Object.freeze(['B']),
});

const SERHIY_MANUAL_COLUMNS_3DP = Object.freeze({
  'Номенклатура': Object.freeze(['G', 'H', 'I', 'J', 'L', 'M', 'N']),
  'Друк-лог': Object.freeze(['A', 'B', 'C', 'D', 'E', 'F', 'H', 'I']),
  'Налаштування': Object.freeze(['B']),
});

// Owner-controlled disclosure switch. Keep this as the sole full-economics switch.
const SERHIY_FULL_ECONOMICS_VISIBLE_3DP = false;

// Header-name projections are intentional: column letters change as the 3D-P
// accounting model evolves, while a renamed/missing approved header must stop
// the read rather than silently disclose or omit another column.
const SERHIY_READ_PROJECTION_3DP = Object.freeze({
  'Номенклатура': Object.freeze([
    'SKU', 'Назва виробу', 'Час друку за од., год', 'Вага виробу за од., г',
    'Вага котушки, г', 'Ціна котушки, грн', 'Собівартість Сергія (виробнича), грн',
    'РРЦ фактична, грн',
  ]),
  'Друк-лог': Object.freeze([
    'Дата', 'SKU', 'Надруковано, шт', 'Час друку факт, год', 'Брак, шт',
    'Витрачено матеріалу, г (факт)', 'Собівартість партії, грн', 'Хто друкував',
    'Примітки', 'API_статус_запису', 'API_історія_змін',
  ]),
  'Наявність': Object.freeze([
    'SKU', 'Назва', 'Надруковано всього, шт', 'Брак всього, шт',
    'Продано на сайті, шт', 'Видано як плюшка, шт', 'Наявно зараз, шт',
  ]),
  'Продажі': Object.freeze([
    'Дата', 'SKU', 'Кількість', '% прибутку Сергію', 'Нараховано Сергію, грн',
    'РРЦ на момент продажу, грн', 'Платник фурнітури', 'Режим CRM',
    'Фурнітура Сергія за од., грн (заморожена)', 'Ціна викупу за од., грн (заморожена)',
  ]),
  'Виплати': Object.freeze([
    'Період (РРРР-ММ)', 'Нараховано Сергію за період, грн', 'Термін перевірки Сергієм',
    'Дата фактичної виплати', 'Статус', 'Примітки',
  ]),
  'Маркетингові_плюшки': Object.freeze(['Дата', 'SKU', 'Видано як бонус, шт']),
  'Фурнітура_довідник': Object.freeze(['Назва фурнітури', 'Ціна, грн/шт']),
  'Аналітика': Object.freeze([
    'SKU', 'Назва', 'Собівартість Сергія, грн', 'Час друку, год', '% прибутку Сергію',
    'РРЦ фактична', 'Нараховано Сергію, грн', 'Прибуток Сергію/год друку, грн',
  ]),
});

const SERHIY_READ_ACTIONS_3DP = Object.freeze([
  '3dp_get_row', '3dp_get_range', '3dp_overview', '3dp_bootstrap',
  '3dp_information_bootstrap', '3dp_skus', '3dp_sales', '3dp_plyushky',
  '3dp_payouts', '3dp_print_log', '3dp_fixtures', '3dp_batch_draft',
  '3dp_settings_journal',
]);

const SETTINGS_VALUE_BOUNDS_3DP = Object.freeze({
  2: Object.freeze({ label: 'Потужність принтера, кВт', min: 0.01, max: 5 }),
  3: Object.freeze({ label: 'Ціна електроенергії, грн/кВт·год', min: 0.01, max: 100 }),
  4: Object.freeze({ label: 'Амортизація принтера, грн/год', min: 0, max: 1000 }),
  5: Object.freeze({ label: 'Планований брак, частка', min: 0, max: 0.5 }),
});

const SETTINGS_JOURNAL_HEADERS_3DP = Object.freeze([
  'Час (Київ)', 'Роль', 'Параметр', 'Було', 'Стало',
]);
const BATCH_DRAFT_ACTOR_HEADER_3DP = 'Роль автора чернетки';

const FORMULA_COLUMNS_3DP = Object.freeze({
  'Номенклатура': Object.freeze(['K']),
  'Друк-лог': Object.freeze(['G']),
  'Продажі': Object.freeze(['C', 'I', 'J', 'K', 'L', 'S']),
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

/**
 * Bound-sheet simple trigger. It only normalizes direct user edits in the two
 * print-time columns; script writes do not re-trigger a simple onEdit.
 */
function onEdit(e) {
  try {
    if (!e || !e.range) return;
    const range = e.range;
    const sheet = range.getSheet();
    const firstColumn = range.getColumn();
    const lastColumn = firstColumn + range.getNumColumns() - 1;
    if (![PRINT_TIME_ENTRY_3DP.nomenclature, PRINT_TIME_ENTRY_3DP.printLog].some(function (target) {
      return target.sheet === sheet.getName() && target.column >= firstColumn && target.column <= lastColumn;
    })) return;
    for (let rowOffset = 0; rowOffset < range.getNumRows(); rowOffset += 1) {
      for (let columnOffset = 0; columnOffset < range.getNumColumns(); columnOffset += 1) {
        const cell = range.getCell(rowOffset + 1, columnOffset + 1);
        const cellTarget = printTimeEntryTarget3dp_(sheet.getName(), cell.getColumn());
        if (!cellTarget || cell.getRow() < 2) continue;
        normalizePrintTimeEdit3dp_(cell, range.getNumRows() === 1 && range.getNumColumns() === 1 ? e.value : '');
      }
    }
  } catch (error) {
    console.warn('3D-P print-time onEdit normalization skipped: ' + (error && error.message ? error.message : error));
  }
}

function printTimeEntryTarget3dp_(sheetName, column) {
  const targets = [PRINT_TIME_ENTRY_3DP.nomenclature, PRINT_TIME_ENTRY_3DP.printLog];
  return targets.filter(function (target) { return target.sheet === sheetName && target.column === column; })[0] || null;
}

function parsePrintTime3dp_(value) {
  if (value === null || typeof value === 'undefined') return { ok: true, blank: true, kind: 'blank', hours: null };
  const raw = String(value).replace(/\u00a0/g, ' ').trim();
  if (!raw) return { ok: true, blank: true, kind: 'blank', hours: null };
  const text = raw.toLowerCase().replace(/\s+/g, ' ');
  const clock = /^(\d+):([0-5]?\d)(?::([0-5]?\d))?$/.exec(text);
  if (clock) return printTimeResult3dp_(Number(clock[1]) + Number(clock[2]) / 60 + Number(clock[3] || 0) / 3600, 'clock');
  const units = /^(?:(\d+(?:[.,]\d+)?)\s*(?:год(?:ина|ини|ин)?|г|h))?\s*(?:(\d+(?:[.,]\d+)?)\s*(?:хв(?:илин(?:а|и)?)?|m))?$/.exec(text);
  if (units && (units[1] || units[2])) {
    const hours = Number(String(units[1] || '0').replace(',', '.'));
    const minutes = Number(String(units[2] || '0').replace(',', '.'));
    if (minutes >= 60) return invalidPrintTime3dp_();
    return printTimeResult3dp_(hours + minutes / 60, 'words');
  }
  if (/^\d*(?:[.,]\d+)?$/.test(text) && /\d/.test(text)) return printTimeResult3dp_(Number(text.replace(',', '.')), 'decimal');
  return invalidPrintTime3dp_();
}

function printTimeResult3dp_(hours, kind) {
  if (!isFinite(hours) || hours < 0) return invalidPrintTime3dp_();
  return { ok: true, blank: false, kind: kind, hours: Number(Number(hours).toFixed(10)) };
}

function invalidPrintTime3dp_() {
  return { ok: false, blank: false, kind: 'invalid', error: 'Введіть десяткові години (1,65), 1:39 або 1 год 39 хв.' };
}

function isTimeNumberFormat3dp_(numberFormat) {
  const format = String(numberFormat || '');
  return /\[[hH]\]|[hH]:|:[mM]/.test(format);
}

function normalizePrintTimeEdit3dp_(range, eventValue) {
  const current = range.getValue();
  const eventParsed = parsePrintTime3dp_(eventValue);
  const displayParsed = parsePrintTime3dp_(range.getDisplayValue());
  let parsed = eventParsed;
  if (eventParsed.blank && isTimeNumberFormat3dp_(range.getNumberFormat()) && displayParsed.ok && displayParsed.kind === 'clock') {
    parsed = displayParsed;
  } else if (eventParsed.blank) {
    parsed = parsePrintTime3dp_(current);
  }
  if (parsed.blank) return;
  if (!parsed.ok) {
    range.setNote(PRINT_TIME_ENTRY_3DP.headerNote + '\n⚠ ' + parsed.error);
    return;
  }
  if (typeof current !== 'number' || current !== parsed.hours) range.setValue(parsed.hours);
  range.setNumberFormat(PRINT_TIME_ENTRY_3DP.numberFormat);
  const warning = printTimeWarning3dp_(parsed.hours);
  range.setNote(warning ? PRINT_TIME_ENTRY_3DP.headerNote + '\n' + warning : '');
}

function printTimeWarning3dp_(hours) {
  if (hours < PRINT_TIME_ENTRY_3DP.minHours || hours > PRINT_TIME_ENTRY_3DP.maxHours) {
    return '⚠ Незвичний час: перевірте значення (очікуваний діапазон ' + PRINT_TIME_ENTRY_3DP.minHours + '–' + PRINT_TIME_ENTRY_3DP.maxHours + ' год).';
  }
  return '';
}

function handleGet3dp_(params, actor) {
  const action = String(params.action || '').trim();
  const spreadsheet = getSpreadsheet3dp_();
  assertReadActionAllowed3dp_(action, actor);

  switch (action) {
    case '3dp_get_row':
      return getRowAction3dp_(spreadsheet, params, actor);
    case '3dp_get_range':
      return getRangeAction3dp_(spreadsheet, params, actor);
    case '3dp_overview':
      return overviewAction3dp_(spreadsheet, actor);
    case '3dp_bootstrap':
      return bootstrapAction3dp_(spreadsheet, params, actor);
    case '3dp_information_bootstrap':
      return informationBootstrapAction3dp_(spreadsheet, actor);
    case '3dp_skus':
      return skusAction3dp_(spreadsheet, params, actor);
    case '3dp_sales':
      return tableAction3dp_(spreadsheet, SHEETS_3DP.sales, { requireHeader: 'SKU' }, actor);
    case '3dp_plyushky':
      return tableAction3dp_(spreadsheet, SHEETS_3DP.plyushky, { requireHeader: 'SKU' }, actor);
    case '3dp_payouts':
      return tableAction3dp_(spreadsheet, SHEETS_3DP.payouts, { requireHeader: 'Період (РРРР-ММ)' }, actor);
    case '3dp_print_log':
      return printLogAction3dp_(spreadsheet, params, actor);
    case '3dp_fixtures':
      return tableAction3dp_(spreadsheet, SHEETS_3DP.fixtures, { requireHeader: 'Назва фурнітури' }, actor);
    case '3dp_batch_draft':
      return getBatchDraftAction3dp_(spreadsheet, params, actor);
    case '3dp_stock_adjustments':
      return stockAdjustmentsAction3dp_(spreadsheet, params, actor);
    case '3dp_settings_journal':
      return settingsJournalAction3dp_(spreadsheet, params, actor);
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
    case '3dp_manufacture_batch':
      return manufactureBatchAction3dp_(spreadsheet, body, actor);
    case '3dp_print_log_update':
      return updatePrintLogAction3dp_(spreadsheet, body, actor);
    case '3dp_print_log_archive':
      return setPrintLogArchiveAction3dp_(spreadsheet, body, actor, true);
    case '3dp_print_log_restore':
      return setPrintLogArchiveAction3dp_(spreadsheet, body, actor, false);
    case '3dp_batch_draft_save':
      return saveBatchDraftAction3dp_(spreadsheet, body, actor);
    case '3dp_payout_create':
      return createPayoutAction3dp_(spreadsheet, body, actor);
    case '3dp_payout_mark_paid':
      return markPayoutPaidAction3dp_(spreadsheet, body, actor);
    case '3dp_order_gifts_append':
      return appendOrderGiftsAction3dp_(spreadsheet, body, actor);
    case '3dp_test_order_cleanup':
      return testOrderCleanupAction3dp_(spreadsheet, body, actor);
    case '3dp_nomenclature_archive':
      return setNomenclatureArchiveAction3dp_(spreadsheet, body, actor, true);
    case '3dp_nomenclature_restore':
      return setNomenclatureArchiveAction3dp_(spreadsheet, body, actor, false);
    case '3dp_adjust_stock':
      return adjustStockAction3dp_(spreadsheet, body, actor);
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

function getRowAction3dp_(spreadsheet, params, actor) {
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
      if (sheetName === SHEETS_3DP.nomenclature) row['% прибутку Сергію'] = profitShareForSku3dp_(spreadsheet, sku);
      if (isSerhiyProjectionActive3dp_(actor) && sheetName === SHEETS_3DP.nomenclature && isArchivedNomenclatureRow3dp_(row)) {
        throw apiError3dp_('ROW_FILTERED', 'Archived SKU is not available to this caller.');
      }
      if (isExampleRow3dp_(row)) throw apiError3dp_('ROW_FILTERED', 'Illustrative rows are not returned.');
      return { action: '3dp_get_row', sheet: sheetName, row: projectRow3dp_(sheetName, headers, row, actor) };
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
  assertSerhiyRangeReadAllowed3dp_(sheetName, sheet, parsed, actor);

  const range = sheet.getRange(parsed.a1);
  return {
    action: '3dp_get_range',
    sheet: sheetName,
    range: parsed.a1,
    values: normalizeMatrix3dp_(range.getValues()),
    formulas: range.getFormulas(),
  };
}

function overviewAction3dp_(spreadsheet, actor) {
  const nomenclature = readTable3dp_(spreadsheet, SHEETS_3DP.nomenclature, { requireHeader: 'SKU' });
  const availability = readTable3dp_(spreadsheet, SHEETS_3DP.availability, { requireHeader: 'SKU' });
  const sales = readTable3dp_(spreadsheet, SHEETS_3DP.sales, { requireHeader: 'SKU' });
  return overviewFromTables3dp_(nomenclature, availability, sales);
}

function overviewFromTables3dp_(nomenclature, availability, sales) {
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
  const accrued = sales ? sales.rows.reduce(function (sum, row) {
    return String(row['Період (авто, РРРР-ММ)'] || '') === month
      ? sum + number3dp_(row['Нараховано Сергію, грн'])
      : sum;
  }, 0) : null;

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

function bootstrapAction3dp_(spreadsheet, params, actor) {
  if (isSerhiyProjectionActive3dp_(actor)) {
    return {
      ok: true,
      action: '3dp_bootstrap',
      overview: overviewAction3dp_(spreadsheet, actor),
      skus: skusAction3dp_(spreadsheet, { include_archived: 'false' }, actor),
      settings: getRangeAction3dp_(spreadsheet, { sheet: SHEETS_3DP.settings, range: 'B2:B5' }, actor),
      analytics: projectedRangeAction3dp_(spreadsheet, SHEETS_3DP.analytics, 3, 17, 3, actor),
    };
  }
  const includeArchived = String((params && params.include_archived) || 'true').toLowerCase() === 'true';
  const nomenclature = readTable3dp_(spreadsheet, SHEETS_3DP.nomenclature, { requireHeader: 'SKU' });
  const availability = readTable3dp_(spreadsheet, SHEETS_3DP.availability, { requireHeader: 'SKU' });
  return {
    ok: true,
    action: '3dp_bootstrap',
    overview: overviewFromTables3dp_(nomenclature, availability, null),
    skus: skusFromTables3dp_(nomenclature, availability, includeArchived),
    settings: getRangeAction3dp_(spreadsheet, { sheet: SHEETS_3DP.settings, range: 'A1:C5' }, actor),
    analytics: getRangeAction3dp_(spreadsheet, { sheet: SHEETS_3DP.analytics, range: 'A3:N17' }, actor),
  };
}

function informationBootstrapAction3dp_(spreadsheet, actor) {
  return {
    ok: true,
    action: '3dp_information_bootstrap',
    sales: tableAction3dp_(spreadsheet, SHEETS_3DP.sales, { requireHeader: 'SKU' }, actor),
    plyushky: tableAction3dp_(spreadsheet, SHEETS_3DP.plyushky, { requireHeader: 'SKU' }, actor),
    payouts: tableAction3dp_(spreadsheet, SHEETS_3DP.payouts, { requireHeader: 'Період (РРРР-ММ)' }, actor),
    fixtures: tableAction3dp_(spreadsheet, SHEETS_3DP.fixtures, { requireHeader: 'Назва фурнітури' }, actor),
  };
}

function skusAction3dp_(spreadsheet, params, actor) {
  const includeArchived = String((params && params.include_archived) || '').toLowerCase() === 'true';
  const nomenclature = readTable3dp_(spreadsheet, SHEETS_3DP.nomenclature, { requireHeader: 'SKU' });
  const availability = readTable3dp_(spreadsheet, SHEETS_3DP.availability, { requireHeader: 'SKU' });
  const result = skusFromTables3dp_(nomenclature, availability, isSerhiyProjectionActive3dp_(actor) ? false : includeArchived);
  if (!isSerhiyProjectionActive3dp_(actor)) return result;
  return Object.assign({}, result, {
    rows: result.rows.map(function (row) {
      const projected = projectRow3dp_(SHEETS_3DP.nomenclature, nomenclature.headers, row, actor);
      projected.availability = row.availability
        ? projectRow3dp_(SHEETS_3DP.availability, availability.headers, row.availability, actor)
        : null;
      return projected;
    }),
  });
}

function skusFromTables3dp_(nomenclature, availability, includeArchived) {
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

function tableAction3dp_(spreadsheet, sheetName, options, actor) {
  const table = readTable3dp_(spreadsheet, sheetName, options || {});
  return {
    action: actionNameForSheet3dp_(sheetName),
    sheet: sheetName,
    rows: projectRows3dp_(sheetName, table.headers, table.rows, actor),
    count: table.rows.length,
  };
}

function isSerhiyProjectionActive3dp_(actor) {
  return Boolean(actor && actor.role === 'serhiy' && !SERHIY_FULL_ECONOMICS_VISIBLE_3DP);
}

function isSerhiyFullEconomics3dp_(actor) {
  return Boolean(actor && actor.role === 'serhiy' && SERHIY_FULL_ECONOMICS_VISIBLE_3DP);
}

function assertReadActionAllowed3dp_(action, actor) {
  if (!isSerhiyProjectionActive3dp_(actor)) return;
  if (SERHIY_READ_ACTIONS_3DP.indexOf(action) === -1) {
    throw apiError3dp_('READ_PROJECTION_FORBIDDEN', 'This read action has no approved Serhiy projection.');
  }
}

function projectionHeadersForSheet3dp_(sheetName) {
  const headers = SERHIY_READ_PROJECTION_3DP[sheetName];
  if (!headers) throw apiError3dp_('READ_PROJECTION_FORBIDDEN', 'No Serhiy read projection is configured for sheet ' + sheetName + '.');
  return headers;
}

function assertProjectionHeaders3dp_(sheetName, headers) {
  const allowed = projectionHeadersForSheet3dp_(sheetName);
  const missing = allowed.filter(function (header) { return headers.indexOf(header) === -1; });
  if (missing.length) {
    throw apiError3dp_(
      'READ_PROJECTION_HEADER_MISSING',
      'Serhiy projection header missing in ' + sheetName + ': ' + missing.join(', ') + '.'
    );
  }
  return allowed;
}

function projectRow3dp_(sheetName, headers, row, actor) {
  if (!isSerhiyProjectionActive3dp_(actor)) return row;
  const allowed = assertProjectionHeaders3dp_(sheetName, headers);
  const projected = {};
  if (Object.prototype.hasOwnProperty.call(row, 'row_number')) projected.row_number = row.row_number;
  allowed.forEach(function (header) {
    if (Object.prototype.hasOwnProperty.call(row, header)) projected[header] = row[header];
  });
  return projected;
}

function projectRows3dp_(sheetName, headers, rows, actor) {
  if (!isSerhiyProjectionActive3dp_(actor)) return rows;
  assertProjectionHeaders3dp_(sheetName, headers);
  return rows.map(function (row) { return projectRow3dp_(sheetName, headers, row, actor); });
}

function projectionHeaderRow3dp_(sheetName) {
  return sheetName === SHEETS_3DP.analytics ? 3 : 1;
}

function assertSerhiyRangeReadAllowed3dp_(sheetName, sheet, parsed, actor) {
  if (!isSerhiyProjectionActive3dp_(actor)) return;
  if (sheetName === SHEETS_3DP.settings) {
    if (parsed.startColumn !== 2 || parsed.endColumn !== 2 || parsed.startRow < 2 || parsed.endRow > 5) {
      throw apiError3dp_('RANGE_NOT_PROJECTED', 'Serhiy may read only Налаштування!B2:B5.');
    }
    return;
  }
  const headerRow = projectionHeaderRow3dp_(sheetName);
  const headers = sheet.getRange(headerRow, 1, 1, sheet.getLastColumn()).getDisplayValues()[0];
  const allowed = assertProjectionHeaders3dp_(sheetName, headers);
  for (let column = parsed.startColumn; column <= parsed.endColumn; column += 1) {
    const header = headers[column - 1];
    if (allowed.indexOf(header) === -1) {
      throw apiError3dp_('RANGE_NOT_PROJECTED', 'Serhiy may not read ' + sheetName + ' column ' + numberToColumn3dp_(column) + '.');
    }
  }
}

function projectedRangeAction3dp_(spreadsheet, sheetName, startRow, endRow, headerRow, actor) {
  const sheet = getSheet3dp_(spreadsheet, sheetName);
  const lastColumn = sheet.getLastColumn();
  const headers = sheet.getRange(headerRow, 1, 1, lastColumn).getDisplayValues()[0];
  const allowed = assertProjectionHeaders3dp_(sheetName, headers);
  const indexes = allowed.map(function (header) { return headers.indexOf(header); });
  const range = sheet.getRange(startRow, 1, endRow - startRow + 1, lastColumn);
  const values = range.getValues().map(function (row) {
    return indexes.map(function (index) { return row[index]; });
  });
  const formulas = range.getFormulas().map(function (row) {
    return indexes.map(function (index) { return row[index]; });
  });
  return {
    action: '3dp_get_range',
    sheet: sheetName,
    range: 'projected:' + sheetName + '!' + startRow + ':' + endRow,
    values: normalizeMatrix3dp_(values),
    formulas: formulas,
  };
}

function profitShareForSku3dp_(spreadsheet, sku) {
  const analytics = getSheet3dp_(spreadsheet, SHEETS_3DP.analytics);
  const lastRow = Math.min(Math.max(analytics.getLastRow(), 4), 100);
  const rows = analytics.getRange(4, 1, lastRow - 3, 6).getValues();
  for (let index = 0; index < rows.length; index += 1) {
    if (String(rows[index][0] || '').trim() !== String(sku || '').trim()) continue;
    if (isBlank3dp_(rows[index][5])) throw apiError3dp_('PROFIT_SHARE_NOT_FOUND', 'Serhiy profit share is blank in Analytics for SKU ' + sku + '.');
    const share = number3dp_(rows[index][5]);
    if (share >= 0 && share <= 1) return share;
    throw apiError3dp_('INVALID_PROFIT_SHARE', 'Serhiy profit share must stay between 0 and 1 for SKU ' + sku + '.');
  }
  throw apiError3dp_('PROFIT_SHARE_NOT_FOUND', 'Serhiy profit share is not configured in Analytics for SKU ' + sku + '.');
}

function createPayoutAction3dp_(spreadsheet, body, actor) {
  assertOwner3dp_(actor, 'Caller may not create payout periods.');
  const period = String(body.period || '').trim();
  if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(period)) throw apiError3dp_('INVALID_PERIOD', 'period must use YYYY-MM.');
  const sheet = getSheet3dp_(spreadsheet, SHEETS_3DP.payouts);
  const existing = readTable3dp_(spreadsheet, SHEETS_3DP.payouts, { requireHeader: 'Період (РРРР-ММ)' }).rows.filter(function (row) {
    return String(row['Період (РРРР-ММ)'] || '').trim() === period;
  });
  if (existing.length) return { action: '3dp_payout_create', row: existing[0].row_number, period: period, already_applied: true };
  const result = appendRowAction3dp_(spreadsheet, {
    sheet: SHEETS_3DP.payouts,
    values: { A: period, E: 'Очікує перевірки', F: String(body.note || '').trim() },
  }, actor);
  SpreadsheetApp.flush();
  return { action: '3dp_payout_create', row: result.row, period: period, already_applied: false };
}

function markPayoutPaidAction3dp_(spreadsheet, body, actor) {
  assertOwner3dp_(actor, 'Caller may not mark payouts as paid.');
  const sheet = getSheet3dp_(spreadsheet, SHEETS_3DP.payouts);
  const row = positiveRowNumber3dp_(body.row_number);
  if (row > sheet.getLastRow()) throw apiError3dp_('ROW_NOT_FOUND', 'Payout row was not found.');
  const period = String(sheet.getRange(row, 1).getDisplayValue() || '').trim();
  if (!period || period !== String(body.expected_period || '').trim()) throw apiError3dp_('STALE_WRITE', 'Payout period changed after it was read. Refresh and retry.');
  const paidDate = String(body.paid_date || '').trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(paidDate)) throw apiError3dp_('INVALID_DATE', 'paid_date must use YYYY-MM-DD.');
  const range = sheet.getRange(row, 4, 1, 3);
  const before = range.getValues()[0];
  const currentDate = normalizeCellValue3dp_(before[0]);
  const currentStatus = String(before[1] || '').trim();
  if (currentStatus === 'Виплачено' && String(currentDate || '').slice(0, 10) === paidDate) {
    return { action: '3dp_payout_mark_paid', row: row, period: period, paid_date: paidDate, already_applied: true };
  }
  if (currentStatus === 'Виплачено') throw apiError3dp_('STALE_WRITE', 'Payout is already marked paid with another date. Refresh before changing it.');
  const note = [String(before[2] || '').trim(), String(body.note || '').trim()].filter(Boolean).join('; ');
  try {
    range.setValues([[paidDate, 'Виплачено', note]]);
    appendAudit3dp_(spreadsheet, actor, 'PAYOUT_PAID', SHEETS_3DP.payouts, 'D' + row + ':F' + row, before, [paidDate, 'Виплачено', note], 'period=' + period);
  } catch (error) {
    range.setValues([before]);
    throw error;
  }
  return { action: '3dp_payout_mark_paid', row: row, period: period, paid_date: paidDate, already_applied: false };
}

function testOrderCleanupPlan3dp_(spreadsheet, order) {
  const sales = getSheet3dp_(spreadsheet, SHEETS_3DP.sales);
  const adjustments = spreadsheet.getSheetByName(SHEETS_3DP.stockAdjustments);
  const gifts = getSheet3dp_(spreadsheet, SHEETS_3DP.plyushky);
  if (!adjustments) throw apiError3dp_('SHEET_NOT_FOUND', 'Required stock-adjustment ledger is missing.');
  const salesRows = [];
  const salesValues = sales.getRange(2, 1, Math.max(sales.getLastRow() - 1, 1), 27).getValues();
  salesValues.forEach(function (values, index) { if (String(values[13] || '').trim() === order) salesRows.push(index + 2); });
  const adjustmentRows = [];
  const adjustmentValues = adjustments.getRange(2, 1, Math.max(adjustments.getLastRow() - 1, 1), 4).getValues();
  adjustmentValues.forEach(function (values, index) {
    if (String(values[2] || '').indexOf('auto: CRM order ' + order + ' ') === 0) adjustmentRows.push(index + 2);
  });
  const giftRows = [];
  let giftRequestMarkers = 0;
  const giftValues = gifts.getRange(4, 1, Math.max(gifts.getLastRow() - 3, 1), 8).getValues();
  giftValues.forEach(function (values, index) {
    if (String(values[6] || '').trim() !== order) return;
    giftRows.push(index + 4);
    giftRequestMarkers += (String(values[7] || '').match(/\[crm_component:[^\]]+\]/g) || []).length;
  });
  return {
    sales: sales,
    adjustments: adjustments,
    gifts: gifts,
    sales_rows: salesRows,
    adjustment_rows: adjustmentRows,
    gift_rows: giftRows,
    gift_request_marker_count: giftRequestMarkers,
  };
}

function testOrderCleanupAction3dp_(spreadsheet, body, actor) {
  assertOwner3dp_(actor, 'Caller may not clean up test-order rows.');
  const order = String(body.order || '').trim();
  if (!order) throw apiError3dp_('INVALID_ORDER', 'Order is required for test-order cleanup.');
  const dryRun = body.dry_run === true || String(body.dry_run || '').toLowerCase() === 'true';
  if (!dryRun && String(body.confirm || '') !== 'CLEAN TEST ORDER ' + order) throw apiError3dp_('CONFIRMATION_REQUIRED', 'Exact cleanup confirmation is required.');
  const plan = testOrderCleanupPlan3dp_(spreadsheet, order);
  const report = {
    action: '3dp_test_order_cleanup',
    order: order,
    dry_run: dryRun,
    sales_rows: plan.sales_rows,
    stock_adjustment_rows: plan.adjustment_rows,
    marketing_gift_rows: plan.gift_rows,
    gift_request_marker_count: plan.gift_request_marker_count,
    tables: {
      'Продажі': plan.sales_rows.length,
      '_Коригування_наявності': plan.adjustment_rows.length,
      'Маркетингові_плюшки': plan.gift_rows.length,
    },
    rows_to_clear: plan.sales_rows.length + plan.adjustment_rows.length + plan.gift_rows.length,
    rows_cleared: 0,
    already_applied: plan.sales_rows.length === 0 && plan.adjustment_rows.length === 0 && plan.gift_rows.length === 0,
  };
  if (dryRun || report.already_applied) return report;
  const snapshots = plan.sales_rows.map(function (row) { return snapshotRange3dp_(plan.sales, 'A' + row + ':AA' + row); })
    .concat(plan.adjustment_rows.map(function (row) { return snapshotRange3dp_(plan.adjustments, 'A' + row + ':D' + row); }))
    .concat(plan.gift_rows.map(function (row) { return snapshotRange3dp_(plan.gifts, 'A' + row + ':H' + row); }));
  const manualSalesColumns = ['A','B','D','E','F','G','H','M','N','O','P','Q','R','T','U','V','W','X','Y','Z','AA'];
  const manualGiftColumns = ['A','B','F','G','H'];
  try {
    plan.sales_rows.forEach(function (row) { manualSalesColumns.forEach(function (column) { plan.sales.getRange(column + row).clearContent(); }); });
    plan.adjustment_rows.forEach(function (row) { plan.adjustments.getRange(row, 1, 1, 4).clearContent(); });
    plan.gift_rows.forEach(function (row) { manualGiftColumns.forEach(function (column) { plan.gifts.getRange(column + row).clearContent(); }); });
    SpreadsheetApp.flush();
    appendAudit3dp_(spreadsheet, actor, 'CLEANUP_TEST_ORDER', 'multiple', order, {
      sales_rows: plan.sales_rows,
      stock_adjustment_rows: plan.adjustment_rows,
      marketing_gift_rows: plan.gift_rows,
      gift_request_marker_count: plan.gift_request_marker_count,
    }, {}, 'Dashboard test-order cleanup; formulas, request markers, and audit history handled as designed.');
    report.rows_cleared = report.rows_to_clear;
  } catch (error) {
    snapshots.reverse().forEach(restoreRange3dp_);
    throw error;
  }
  return report;
}

function printLogAction3dp_(spreadsheet, params, actor) {
  const includeArchived = String(params.include_archived || '').toLowerCase() === 'true';
  const table = readTable3dp_(spreadsheet, SHEETS_3DP.printLog, { requireHeader: 'SKU', includeArchived: includeArchived });
  return {
    action: '3dp_print_log',
    rows: projectRows3dp_(SHEETS_3DP.printLog, table.headers, table.rows, actor),
    count: table.rows.length,
    include_archived: includeArchived,
  };
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
  if (sheetName === SHEETS_3DP.sales && SALES_CORRECTABLE_FROZEN_COLUMNS_3DP.indexOf(column) !== -1) {
    assertTechnicalSaleFixtureCorrectionWriteAllowed3dp_(sheet, column, row, body.value, actor);
  } else {
    assertCellWriteAllowed3dp_(sheetName, column, actor);
  }
  assertWriteTargetAllowed3dp_(sheetName, column, row);
  const oldRawValue = range.getValue();
  const oldNumberFormat = range.getNumberFormat();
  const printTimeTarget = printTimeEntryTarget3dp_(sheetName, columnToNumber3dp_(column));
  const oldValue = printTimeTarget
    ? normalizeStoredPrintTime3dp_(oldRawValue, range.getDisplayValue(), oldNumberFormat)
    : normalizeCellValue3dp_(oldRawValue);
  if (Object.prototype.hasOwnProperty.call(body, 'expected_current') &&
      !equalCellValue3dp_(oldValue, body.expected_current)) {
    throw apiError3dp_('STALE_WRITE', 'The cell changed after it was read. Refresh and retry.');
  }

  assertManualValue3dp_(body.value);
  const isSettingsWrite = sheetName === SHEETS_3DP.settings && column === 'B';
  const isMaterialPriceWrite = sheetName === SHEETS_3DP.nomenclature && column === 'J';
  const newValue = isSettingsWrite ? normalizedSettingsValue3dp_(row, body.value) : body.value;
  let materialHistoryRange = null;
  let oldMaterialHistory = null;
  if (isMaterialPriceWrite) {
    materialHistoryRange = sheet.getRange(row, columnToNumber3dp_(API_3DP.nomenclatureHistoryColumn));
    if (materialHistoryRange.getFormula()) {
      throw apiError3dp_('FORMULA_CELL', 'Номенклатура history must remain a manual system field.');
    }
    oldMaterialHistory = String(materialHistoryRange.getValue() || '');
  }

  setCellValue3dp_(range, newValue);
  if (printTimeTarget) range.setNumberFormat(PRINT_TIME_ENTRY_3DP.numberFormat);
  try {
    if (materialHistoryRange) {
      materialHistoryRange.setValue(appendHistory3dp_(
        oldMaterialHistory,
        historyLine3dp_(actor, 'Ціна котушки: ' + displayAuditValue3dp_(oldValue) + ' → ' + displayAuditValue3dp_(newValue))
      ));
    }
    if (isSettingsWrite) appendSettingsJournal3dp_(spreadsheet, actor, row, oldValue, newValue);
    appendAudit3dp_(spreadsheet, actor, 'WRITE', sheetName, column + row, oldValue, newValue, '');
  } catch (error) {
    setCellValue3dp_(range, oldRawValue);
    range.setNumberFormat(oldNumberFormat);
    if (materialHistoryRange) materialHistoryRange.setValue(oldMaterialHistory);
    throw error;
  }
  return { action: '3dp_write', sheet: sheetName, cell: column + row, old_value: oldValue, new_value: normalizeCellValue3dp_(newValue) };
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
    if (sheetName === SHEETS_3DP.sales && SALES_FROZEN_COLUMNS_3DP.indexOf(column) !== -1) {
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
  if (sheetName === SHEETS_3DP.sales) {
    const required = is3dpOrderLineAccountingSchemaReady3dp_(sheet)
      ? SALES_ORDER_LINE_REQUIRED_COLUMNS_3DP
      : (is3dp015SalesSchemaReady3dp_(sheet) ? SALES_3DP015_REQUIRED_COLUMNS_3DP : ['T']);
    const missing = required.filter(function (column) { return !Object.prototype.hasOwnProperty.call(normalized, column); });
    if (missing.length) {
      if (missing.length === 1 && missing[0] === 'T') {
        throw apiError3dp_('CRM_ROW_REQUIRED', 'Продажі rows require the technical CRM row number in column T.');
      }
      throw apiError3dp_('FROZEN_VALUES_REQUIRED', 'Продажі rows require technical columns: ' + missing.join(', ') + '.');
    }
  }

  const row = findFirstBusinessEmptyRow3dp_(sheet, sheetName, actor);
  copyFormulaCells3dp_(sheet, sheetName, row);
  const applied = [];
  try {
    Object.keys(normalized).forEach(function (column) {
      const range = sheet.getRange(row, columnToNumber3dp_(column));
      const existingFormula = range.getFormula();
      const approvedFrozenCostReplacement = sheetName === SHEETS_3DP.sales && column === 'F' &&
        canonicalFormula3dp_(existingFormula) === canonicalFormula3dp_(legacySalesProductionCostFormula3dp_(row));
      if (existingFormula && !approvedFrozenCostReplacement) {
        throw apiError3dp_('FORMULA_CELL', 'Formula cells cannot be changed.');
      }
      const oldRawValue = range.getValue();
      const oldNumberFormat = range.getNumberFormat();
      const printTimeTarget = printTimeEntryTarget3dp_(sheetName, columnToNumber3dp_(column));
      const oldValue = printTimeTarget
        ? normalizeStoredPrintTime3dp_(oldRawValue, range.getDisplayValue(), oldNumberFormat)
        : normalizeCellValue3dp_(oldRawValue);
      setCellValue3dp_(range, normalized[column]);
      if (printTimeTarget) range.setNumberFormat(PRINT_TIME_ENTRY_3DP.numberFormat);
      applied.push({ column: column, range: range, oldValue: oldValue, oldRawValue: oldRawValue, oldNumberFormat: oldNumberFormat, oldFormula: existingFormula, newValue: normalized[column] });
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
    applied.reverse().forEach(function (change) {
      if (change.oldFormula) change.range.setFormula(change.oldFormula);
      else setCellValue3dp_(change.range, Object.prototype.hasOwnProperty.call(change, 'oldRawValue') ? change.oldRawValue : change.oldValue);
      if (change.oldNumberFormat) change.range.setNumberFormat(change.oldNumberFormat);
    });
    throw error;
  }

  return { action: '3dp_append_row', sheet: sheetName, row: row };
}

function appendOrderGiftsAction3dp_(spreadsheet, body, actor) {
  assertOwner3dp_(actor, 'Caller may not append CRM order gifts.');
  const requestId = String(body.request_id || '').trim();
  if (!/^[A-Za-z0-9_-]{8,80}$/.test(requestId)) {
    throw apiError3dp_('REQUEST_ID_REQUIRED', 'A stable CRM request_id is required.');
  }
  const order = String(body.order || '').trim();
  if (!order) throw apiError3dp_('ORDER_REQUIRED', 'CRM order number is required.');
  const date = String(body.date || '').trim().slice(0, 10);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) throw apiError3dp_('DATE_REQUIRED', 'Gift date must use YYYY-MM-DD.');
  const rawItems = Array.isArray(body.items) ? body.items.slice(0, 10) : [];
  if (!rawItems.length) throw apiError3dp_('ITEMS_REQUIRED', 'At least one 3D gift is required.');

  const nomenclature = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
  const availability = getSheet3dp_(spreadsheet, SHEETS_3DP.availability);
  const gifts = getSheet3dp_(spreadsheet, SHEETS_3DP.plyushky);
  const items = rawItems.map(function (item, index) {
    const sku = requiredSku3dp_(item && item.sku);
    const qty = inventoryWholeNumber3dp_(item && item.qty, 'Gift quantity must be a non-negative whole number.');
    if (qty < 1) throw apiError3dp_('INVALID_QUANTITY', 'Gift quantity must be a positive whole number.');
    const nomenclatureRow = resolveTargetRow3dp_(nomenclature, sku);
    assertRealNomenclatureRow3dp_(nomenclature, nomenclatureRow);
    if (nomenclatureStatusAtRow3dp_(nomenclature, nomenclatureRow) === API_3DP.archivedStatus) {
      throw apiError3dp_('SKU_ARCHIVED', 'Archived SKU cannot be issued as a gift: ' + sku + '.');
    }
    const buyoutRaw = nomenclature.getRange(nomenclatureRow, columnToNumber3dp_('R')).getValue();
    if (isBlank3dp_(buyoutRaw)) throw apiError3dp_('BUYOUT_NOT_FOUND', 'Booster Shop buyout price is blank for SKU ' + sku + '.');
    const buyout = number3dp_(buyoutRaw);
    if (buyout < 0) throw apiError3dp_('INVALID_BUYOUT', 'Booster Shop buyout price cannot be negative for SKU ' + sku + '.');
    return { sku: sku, qty: qty, buyout: buyout, note: String(item && item.note || '').trim(), marker: '[crm_component:' + requestId + ':' + index + ']' };
  });

  const lastRow = Math.min(gifts.getLastRow(), API_3DP.maxReadRows);
  const existingNotes = lastRow >= 4 ? gifts.getRange(4, 8, lastRow - 3, 1).getDisplayValues().flat().map(String) : [];
  const missing = items.filter(function (item) { return !existingNotes.some(function (note) { return note.indexOf(item.marker) !== -1; }); });
  if (!missing.length) return { action: '3dp_order_gifts_append', order: order, rows_added: 0, already_applied: true };

  const requestedBySku = {};
  missing.forEach(function (item) { requestedBySku[item.sku] = (requestedBySku[item.sku] || 0) + item.qty; });
  Object.keys(requestedBySku).forEach(function (sku) {
    const availabilityRow = findAvailabilityRow3dp_(availability, sku);
    const stock = signedWholeNumber3dp_(availability.getRange(availabilityRow, 7).getValue(), 'Current stock must be a whole number.');
    if (requestedBySku[sku] > stock) {
      throw apiError3dp_('INSUFFICIENT_STOCK', 'Not enough 3D stock for ' + sku + ': requested ' + requestedBySku[sku] + ', available ' + stock + '.');
    }
  });

  const applied = [];
  try {
    missing.forEach(function (item) {
      const row = findFirstBusinessEmptyRow3dp_(gifts, SHEETS_3DP.plyushky, actor);
      const ranges = Array.from({ length: 8 }, function (_, index) { return gifts.getRange(row, index + 1); });
      const previous = ranges.map(function (range) { return { value: range.getValue(), formula: range.getFormula(), format: range.getNumberFormat() }; });
      copyFormulaCells3dp_(gifts, SHEETS_3DP.plyushky, row);
      gifts.getRange(row, 1).setValue(date);
      gifts.getRange(row, 2).setValue(item.sku);
      gifts.getRange(row, 6).setValue(item.qty);
      gifts.getRange(row, 7).setValue(order);
      gifts.getRange(row, 8).setValue([item.marker, item.note].filter(Boolean).join(' '));
      applied.push({ row: row, ranges: ranges, previous: previous, item: item });
    });
    appendAudit3dp_(spreadsheet, actor, 'ORDER_GIFTS_APPEND', SHEETS_3DP.plyushky,
      applied.map(function (entry) { return entry.row; }).join(','), {},
      applied.map(function (entry) { return { sku: entry.item.sku, qty: entry.item.qty, order: order }; }),
      'request_id=' + requestId);
  } catch (error) {
    applied.reverse().forEach(function (entry) {
      entry.ranges.forEach(function (range, index) {
        const old = entry.previous[index];
        if (old.formula) range.setFormula(old.formula); else setCellValue3dp_(range, old.value);
        range.setNumberFormat(old.format);
      });
    });
    throw error;
  }
  return { action: '3dp_order_gifts_append', order: order, rows_added: applied.length, already_applied: false,
    items: applied.map(function (entry) { return { sku: entry.item.sku, qty: entry.item.qty, buyout_unit: entry.item.buyout, row: entry.row }; }) };
}

// Prepared Продажі rows carry the historic live lookup in F. A CRM append may replace only this
// exact row-local formula with a frozen production cost; all other formulas remain protected.
function legacySalesProductionCostFormula3dp_(row) {
  return '=IF(B' + row + '="";"";IFERROR(INDEX(\'Номенклатура\'!$K:$K;MATCH(B' + row + ';\'Номенклатура\'!$A:$A;0));0))';
}

function manufactureBatchAction3dp_(spreadsheet, body, actor) {
  assertPrintLogRole3dp_(actor);
  const sku = requiredSku3dp_(body.sku);
  const nomenclature = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
  const nomenclatureRow = resolveTargetRow3dp_(nomenclature, sku);
  if (nomenclatureStatusAtRow3dp_(nomenclature, nomenclatureRow) === API_3DP.archivedStatus) {
    throw apiError3dp_('ROW_ARCHIVED', 'Archived SKU cannot receive a manufactured batch.');
  }
  const quantity = inventoryWholeNumber3dp_(body.quantity, 'quantity must be a non-negative whole number.');
  if (quantity < 1) throw apiError3dp_('INVALID_QUANTITY', 'quantity must be a positive whole number.');
  const defects = inventoryWholeNumber3dp_(body.defects == null ? 0 : body.defects, 'defects must be a non-negative whole number.');
  if (defects > quantity) throw apiError3dp_('INVALID_DEFECTS', 'defects cannot exceed the printed quantity.');
  const printTime = parsePrintTime3dp_(body.total_print_time_h);
  if (!printTime.ok || printTime.blank) throw apiError3dp_('INVALID_PRINT_TIME', printTime.error || 'total_print_time_h is required.');
  const material = Number(body.total_weight_g);
  if (!Number.isFinite(material) || material < 0) throw apiError3dp_('INVALID_MATERIAL', 'total_weight_g must be a non-negative number.');
  const printedBy = String(body.printed_by || (actor.role === 'serhiy' ? 'Сергій' : 'власник')).trim();
  if (['Сергій', 'власник'].indexOf(printedBy) === -1) throw apiError3dp_('INVALID_PRINTER', 'printed_by must be Сергій or власник.');
  const requestId = String(body.request_id || '').trim();
  if (!/^[A-Za-z0-9_-]{8,80}$/.test(requestId)) throw apiError3dp_('REQUEST_ID_REQUIRED', 'A stable request_id is required.');
  const note = String(body.note || '').trim();
  assertManualValue3dp_(note);
  if (note.length > 220) throw apiError3dp_('NOTE_TOO_LONG', 'Manufacturing note exceeds 220 characters.');

  const printLog = getSheet3dp_(spreadsheet, SHEETS_3DP.printLog);
  const marker = '[dashboard_request:' + requestId + ']';
  const lastRow = printLog.getLastRow();
  if (lastRow >= 2) {
    const notes = printLog.getRange(2, 9, lastRow - 1, 1).getValues();
    for (let index = 0; index < notes.length; index += 1) {
      if (String(notes[index][0] || '').indexOf(marker) !== -1) {
        return { action: '3dp_manufacture_batch', row: index + 2, already_applied: true, request_id: requestId };
      }
    }
  }
  const appended = appendRowAction3dp_(spreadsheet, {
    sheet: SHEETS_3DP.printLog,
    values: {
      A: Utilities.formatDate(new Date(), API_3DP.timezone, 'yyyy-MM-dd'),
      B: sku,
      C: quantity,
      D: printTime.hours,
      E: defects,
      F: material,
      H: printedBy,
      I: [note, marker].filter(Boolean).join(' '),
    },
  }, actor);
  return {
    action: '3dp_manufacture_batch', row: appended.row, already_applied: false,
    request_id: requestId, printed: quantity, defects: defects, stock_added: quantity - defects,
  };
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
  const draftRow = findBatchDraftRow3dp_(drafts, sku, actor, isSerhiyProjectionActive3dp_(actor));
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
  const serhiyDraft = actor && actor.role === 'serhiy';
  if (serhiyDraft) ensureBatchDraftActorColumn3dp_(drafts);
  const existingRow = findBatchDraftRow3dp_(drafts, sku, actor, serhiyDraft);
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
      setCellValue3dp_(skuRange, batchDraftStorageKey3dp_(sku, actor, serhiyDraft));
      applied.push({ range: skuRange, oldRawValue: oldSku });
      if (serhiyDraft) {
        const actorRange = drafts.getRange(row, batchDraftActorColumn3dp_());
        if (actorRange.getFormula()) throw apiError3dp_('FORMULA_CELL', 'Batch-draft actor role must remain a manual system field.');
        const oldActor = normalizeCellValue3dp_(actorRange.getValue());
        actorRange.setValue(actor.role);
        applied.push({ range: actorRange, oldRawValue: oldActor });
      }
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
  if (!actor || (actor.role !== 'owner' && !isSerhiyFullEconomics3dp_(actor))) {
    throw apiError3dp_('FORBIDDEN', 'Caller may not read stock adjustments.');
  }
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
    throw apiError3dp_('STOCK_FORMULA_NOT_READY', 'The required 3D-P stock ledger/formula is not configured.');
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







function nomenclatureFinalCostFormula3dp_(row, includeFixture, includeDefectRate) {
  const fixture = includeFixture ? '+N' + row : '';
  const base = 'H' + row + '/I' + row + '*J' + row + '+G' + row + '*\'' + API_3DP.settingsSheet + '\'!$B$2*\'' + API_3DP.settingsSheet + '\'!$B$3+G' + row + '*\'' + API_3DP.settingsSheet + '\'!$B$4' + fixture;
  const cost = includeDefectRate === false ? base : '(' + base + ')*(1+\'' + API_3DP.settingsSheet + '\'!$B$5)';
  return '=IF(A' + row + '="";"";IFERROR(' + cost + ';""))';
}


function salesOrderLineMarginFormula3dp_(row) {
  return '=IF(OR(B' + row + '="";E' + row + '="");"";IF(X' + row + '="Маркетинг";0;E' + row + '-F' + row + '-G' + row + '-Y' + row + '-Z' + row + '))';
}

function salesOrderLineSerhiyAccrualFormula3dp_(row) {
  return '=IF(OR(B' + row + '="";E' + row + '="");"";IF(X' + row + '="Маркетинг";D' + row + '*(AA' + row + '+Z' + row + ');D' + row + '*(F' + row + '+H' + row + '*I' + row + '+Z' + row + ')))';
}

function salesOrderLineBoosterIncomeFormula3dp_(row) {
  return '=IF(OR(B' + row + '="";E' + row + '="");"";IF(X' + row + '="Маркетинг";0;D' + row + '*I' + row + '*(1-H' + row + ')))';
}


function snapshotRange3dp_(sheet, a1) {
  const range = sheet.getRange(a1);
  return { range: range, values: range.getValues(), formulas: range.getFormulas(), notes: range.getNotes(), fontColors: range.getFontColors(), numberFormats: range.getNumberFormats() };
}

function restoreRange3dp_(snapshot) {
  snapshot.range.setValues(snapshot.values);
  snapshot.formulas.forEach(function (formulas, rowIndex) {
    formulas.forEach(function (formula, columnIndex) {
      if (formula) snapshot.range.getCell(rowIndex + 1, columnIndex + 1).setFormula(formula);
    });
  });
  snapshot.range.setNotes(snapshot.notes);
  snapshot.range.setFontColors(snapshot.fontColors);
  snapshot.range.setNumberFormats(snapshot.numberFormats);
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
  if (!actor || actor.role !== 'owner' || SALES_FROZEN_COLUMNS_3DP.indexOf(column) === -1) {
    throw apiError3dp_('COLUMN_NOT_ALLOWED', 'Technical sale columns are reserved for the CRM order hook.');
  }
  if (sheet.getRange('T1').getDisplayValue() !== API_3DP.salesCrmRowHeader) {
    throw apiError3dp_('SCHEMA_NOT_READY', 'Run 3D-P-010 setup before appending Продажі rows.');
  }
  if (column === 'T' && (typeof value !== 'number' || !Number.isInteger(value) || value < 3)) {
    throw apiError3dp_('CRM_ROW_INVALID', 'CRM row number must be a whole number >= 3.');
  }
  if (column === 'W') {
    if (value !== '' && value !== 'власник' && value !== 'Сергій') {
      throw apiError3dp_('FIXTURE_PAYER_INVALID', 'Fixture payer must be blank, власник, or Сергій.');
    }
    return;
  }
  if (column === 'X') {
    if (value !== 'Продаж' && value !== 'Маркетинг') {
      throw apiError3dp_('SALE_MODE_INVALID', 'CRM sale mode must be Продаж or Маркетинг.');
    }
    return;
  }
  if (column !== 'T' && (typeof value !== 'number' || !Number.isFinite(value) || value < 0)) {
    throw apiError3dp_('FROZEN_VALUE_INVALID', 'Frozen cost and price values must be non-negative numbers.');
  }
  if (column !== 'T' && !is3dp015SalesSchemaReady3dp_(sheet)) {
    throw apiError3dp_('SCHEMA_NOT_READY', 'Run 3D-P-015 setup before appending frozen sale values.');
  }
}

function assertTechnicalSaleFixtureCorrectionWriteAllowed3dp_(sheet, column, row, value, actor) {
  if (!actor || actor.role !== 'owner' || SALES_FROZEN_COLUMNS_3DP.indexOf(column) === -1) {
    throw apiError3dp_('COLUMN_NOT_ALLOWED', 'Only the CRM order hook may correct frozen sale values.');
  }
  if (sheet.getRange('T1').getDisplayValue() !== API_3DP.salesCrmRowHeader || !is3dp015SalesSchemaReady3dp_(sheet)) {
    throw apiError3dp_('SCHEMA_NOT_READY', 'Run 3D-P-010 and 3D-P-015 setup before correcting frozen fixture values.');
  }
  const crmRow = Number(sheet.getRange(row, columnToNumber3dp_('T')).getValue());
  if (!Number.isInteger(crmRow) || crmRow < 3) {
    throw apiError3dp_('ROW_NOT_ALLOWED', 'Frozen fixture values may be corrected only on a CRM-linked sale row.');
  }
  if (column === 'W') {
    if (value !== '' && value !== 'власник' && value !== 'Сергій') {
      throw apiError3dp_('FIXTURE_PAYER_INVALID', 'Fixture payer must be blank, власник, or Сергій.');
    }
    return;
  }
  if (column === 'X') {
    if (value !== 'Продаж' && value !== 'Маркетинг') {
      throw apiError3dp_('SALE_MODE_INVALID', 'CRM sale mode must be Продаж or Маркетинг.');
    }
    return;
  }
  if (typeof value !== 'number' || !Number.isFinite(value) || value < 0) {
    throw apiError3dp_('FROZEN_VALUE_INVALID', 'Frozen fixture cost must be a non-negative number.');
  }
}

function is3dp015SalesSchemaReady3dp_(sheet) {
  return Object.keys(PRICE_MODEL_COLUMNS_3DP.sales).every(function (column) {
    return sheet.getRange(column + '1').getDisplayValue() === PRICE_MODEL_COLUMNS_3DP.sales[column];
  });
}

function is3dpOrderLineAccountingSchemaReady3dp_(sheet) {
  return Object.keys(ORDER_LINE_ACCOUNTING_COLUMNS_3DP).every(function (column) {
    return sheet.getRange(column + '1').getDisplayValue() === ORDER_LINE_ACCOUNTING_COLUMNS_3DP[column];
  });
}

function assertCellWriteAllowed3dp_(sheetName, column, actor) {
  const byRole = actor.role === 'serhiy' ? SERHIY_MANUAL_COLUMNS_3DP : OWNER_MANUAL_COLUMNS_3DP;
  const allowed = byRole[sheetName] || [];
  if (allowed.indexOf(column) === -1) {
    throw apiError3dp_('COLUMN_NOT_ALLOWED', 'Column is not a whitelisted manual-input field for this caller.');
  }
}

function assertWriteTargetAllowed3dp_(sheetName, column, row) {
  if (sheetName === SHEETS_3DP.settings && (column !== 'B' || row < 2 || row > 5)) {
    throw apiError3dp_('ROW_NOT_ALLOWED', 'Only Налаштування!B2:B5 can be changed through the API.');
  }
}

function normalizedSettingsValue3dp_(row, value) {
  const rule = SETTINGS_VALUE_BOUNDS_3DP[row];
  if (!rule) throw apiError3dp_('ROW_NOT_ALLOWED', 'Only approved settings rows may be changed.');
  const raw = typeof value === 'string' ? value.trim().replace(',', '.') : value;
  if (raw === '' || raw === null || typeof raw === 'undefined' ||
      (typeof raw !== 'number' && typeof raw !== 'string')) {
    throw apiError3dp_('SETTINGS_VALUE_INVALID', rule.label + ' must be a number.');
  }
  if (typeof raw === 'string' && !/^(?:\d+(?:\.\d+)?|\.\d+)$/.test(raw)) {
    throw apiError3dp_('SETTINGS_VALUE_INVALID', rule.label + ' must be a decimal number.');
  }
  const numeric = typeof raw === 'number' ? raw : Number(raw);
  if (!Number.isFinite(numeric)) throw apiError3dp_('SETTINGS_VALUE_INVALID', rule.label + ' must be a finite number.');
  if (numeric < rule.min || numeric > rule.max) {
    throw apiError3dp_(
      'SETTINGS_VALUE_OUT_OF_BOUNDS',
      rule.label + ' must be between ' + rule.min + ' and ' + rule.max + '.'
    );
  }
  return numeric;
}

function getOrCreateSettingsJournal3dp_(spreadsheet) {
  let sheet = spreadsheet.getSheetByName(SHEETS_3DP.settingsJournal);
  if (!sheet) {
    sheet = spreadsheet.insertSheet(SHEETS_3DP.settingsJournal);
    sheet.getRange(1, 1, 1, SETTINGS_JOURNAL_HEADERS_3DP.length).setValues([SETTINGS_JOURNAL_HEADERS_3DP]);
    sheet.setFrozenRows(1);
    sheet.hideSheet();
    return sheet;
  }
  const headers = sheet.getRange(1, 1, 1, SETTINGS_JOURNAL_HEADERS_3DP.length).getDisplayValues()[0];
  if (JSON.stringify(headers) !== JSON.stringify(SETTINGS_JOURNAL_HEADERS_3DP)) {
    throw apiError3dp_('SETTINGS_JOURNAL_SCHEMA_MISMATCH', 'Settings journal headers do not match the approved schema.');
  }
  return sheet;
}

function appendSettingsJournal3dp_(spreadsheet, actor, row, oldValue, newValue) {
  const rule = SETTINGS_VALUE_BOUNDS_3DP[row];
  if (!rule) throw apiError3dp_('ROW_NOT_ALLOWED', 'Only approved settings rows may be journaled.');
  const journal = getOrCreateSettingsJournal3dp_(spreadsheet);
  journal.appendRow([
    now3dp_(),
    actor.role,
    rule.label,
    displayAuditValue3dp_(oldValue),
    displayAuditValue3dp_(newValue),
  ]);
}

function settingsJournalAction3dp_(spreadsheet, params, actor) {
  const journal = spreadsheet.getSheetByName(SHEETS_3DP.settingsJournal);
  if (!journal) return { action: '3dp_settings_journal', rows: [], count: 0 };
  const headers = journal.getRange(1, 1, 1, SETTINGS_JOURNAL_HEADERS_3DP.length).getDisplayValues()[0];
  if (JSON.stringify(headers) !== JSON.stringify(SETTINGS_JOURNAL_HEADERS_3DP)) {
    throw apiError3dp_('SETTINGS_JOURNAL_SCHEMA_MISMATCH', 'Settings journal headers do not match the approved schema.');
  }
  const limit = boundedLimit3dp_(params.limit, 50, 100);
  const lastRow = Math.min(journal.getLastRow(), API_3DP.maxReadRows);
  if (lastRow < 2) return { action: '3dp_settings_journal', rows: [], count: 0 };
  const values = journal.getRange(2, 1, lastRow - 1, SETTINGS_JOURNAL_HEADERS_3DP.length).getValues();
  const rows = values.map(function (row, index) {
    return rowObject3dp_(headers, row, index + 2);
  }).filter(function (row) {
    return !isSerhiyProjectionActive3dp_(actor) || row['Роль'] === 'serhiy';
  }).reverse().slice(0, limit);
  return { action: '3dp_settings_journal', rows: rows, count: rows.length };
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
  if (!sheet) throw apiError3dp_('SHEET_NOT_FOUND', 'Required 3D-P extension sheet is not configured: ' + sheetName + '.');
  return sheet;
}

function batchDraftField3dp_(key) {
  const field = BATCH_DRAFT_FIELDS_3DP.filter(function (candidate) { return candidate.key === key; })[0];
  if (!field) throw apiError3dp_('FIELD_NOT_ALLOWED', 'Unsupported batch-draft field.');
  return field;
}

function batchDraftValues3dp_(sheet, row) {
  const values = row ? sheet.getRange(row, 2, 1, BATCH_DRAFT_FIELDS_3DP.length).getValues()[0] : [];
  return BATCH_DRAFT_FIELDS_3DP.reduce(function (result, field, index) {
    result[field.key] = row ? normalizeCellValue3dp_(values[index]) : '';
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

function batchDraftActorColumn3dp_() {
  return BATCH_DRAFT_FIELDS_3DP.length + 2;
}

function batchDraftActorColumnReady3dp_(sheet) {
  return String(sheet.getRange(1, batchDraftActorColumn3dp_()).getDisplayValue() || '') === BATCH_DRAFT_ACTOR_HEADER_3DP;
}

function ensureBatchDraftActorColumn3dp_(sheet) {
  const range = sheet.getRange(1, batchDraftActorColumn3dp_());
  const current = String(range.getDisplayValue() || '');
  if (!current) {
    range.setValue(BATCH_DRAFT_ACTOR_HEADER_3DP);
    return;
  }
  if (current !== BATCH_DRAFT_ACTOR_HEADER_3DP) {
    throw apiError3dp_('BATCH_DRAFT_SCHEMA_MISMATCH', 'Batch-draft actor column does not match the approved schema.');
  }
}

function batchDraftStorageKey3dp_(sku, actor, scopeToActor) {
  return scopeToActor ? String((actor && actor.role) || '') + '::' + sku : sku;
}

function findBatchDraftRow3dp_(sheet, sku, actor, scopeToActor) {
  const lastRow = sheet.getLastRow();
  if (lastRow < 2) return 0;
  const hasActorColumn = batchDraftActorColumnReady3dp_(sheet);
  if (scopeToActor && !hasActorColumn) return 0;
  const values = sheet.getRange(2, 1, lastRow - 1, hasActorColumn ? batchDraftActorColumn3dp_() : 1).getValues();
  const storageKey = batchDraftStorageKey3dp_(sku, actor, scopeToActor);
  let found = 0;
  values.forEach(function (row, index) {
    if (String(row[0] || '').trim() !== storageKey) return;
    const actorRole = hasActorColumn ? String(row[batchDraftActorColumn3dp_() - 1] || '').trim() : '';
    const matchesRole = scopeToActor
      ? actorRole === String((actor && actor.role) || '')
      : (!actorRole || actorRole === 'owner');
    if (matchesRole) {
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
    throw apiError3dp_('ARCHIVE_SYSTEM_NOT_READY', 'The required 3D-P archive-state columns are not configured.');
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
  let formulaColumns = FORMULA_COLUMNS_3DP[sheetName] || [];
  if (sheetName === SHEETS_3DP.sales && !is3dp015SalesSchemaReady3dp_(sheet)) {
    formulaColumns = formulaColumns.concat(LEGACY_PRE_3DP015_SALES_FORMULA_COLUMNS_3DP);
  }
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

function normalizeStoredPrintTime3dp_(value, displayValue, numberFormat) {
  if (!(value instanceof Date) || !isTimeNumberFormat3dp_(numberFormat)) return normalizeCellValue3dp_(value);
  const match = /^(\d+):([0-5]?\d)(?::([0-5]?\d))?$/.exec(String(displayValue || '').trim());
  if (!match) return normalizeCellValue3dp_(value);
  return Number(((Number(match[1]) + Number(match[2]) / 60 + Number(match[3] || 0) / 3600) / 24).toFixed(10));
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
 * the archived Addendum #2 migration in the bound 3D-P spreadsheet.
 */
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
      'Номенклатура: make K use the final spool-based material, electricity, and amortization formula; fixture remains a separate reference price',
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
    'Дата оновлення', 'Примітки', ['Фурнітура (ланцюжок/карабін), грн/шт', API_3DP.fixtureReferenceHeader],
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
    sheet.getRange(1, 1, 1, headers.length + 1).setValues([headers.concat([BATCH_DRAFT_ACTOR_HEADER_3DP])]);
    sheet.setFrozenRows(1);
    sheet.hideSheet();
    changes.push(SHEETS_3DP.drafts + ' created and hidden for keyed raw batch drafts');
    return;
  }
  const currentHeaders = sheet.getRange(1, 1, 1, headers.length).getDisplayValues()[0];
  if (JSON.stringify(currentHeaders) !== JSON.stringify(headers)) {
    throw apiError3dp_('SETUP_ANCHOR_MISMATCH', SHEETS_3DP.drafts + '!A1:F1 headers do not match the approved batch-draft schema.');
  }
  const actorHeader = String(sheet.getRange(1, batchDraftActorColumn3dp_()).getDisplayValue() || '');
  if (!actorHeader) {
    sheet.getRange(1, batchDraftActorColumn3dp_()).setValue(BATCH_DRAFT_ACTOR_HEADER_3DP);
    changes.push(SHEETS_3DP.drafts + ' actor-role column added for private Serhiy drafts');
  } else if (actorHeader !== BATCH_DRAFT_ACTOR_HEADER_3DP) {
    throw apiError3dp_('SETUP_ANCHOR_MISMATCH', SHEETS_3DP.drafts + '!G1 does not match the approved actor-role schema.');
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
    const expectedFormula = nomenclatureFinalCostFormula3dp_(row, false);
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
    ['Планований брак, частка', 0.1, 'частка (0.1 = 10%)'],
  ];
  if (!sheet) {
    sheet = spreadsheet.insertSheet(API_3DP.settingsSheet);
    sheet.getRange(1, 1, 5, 3).setValues(expected);
    sheet.getRange('B2:B5').setFontColor('#0000ff');
    changes.push(API_3DP.settingsSheet + ' created with editable B2:B5 constants');
    return;
  }
  const current = sheet.getRange(1, 1, 5, 3).getDisplayValues();
  expected.forEach(function (row, rowIndex) {
    [0, 2].forEach(function (columnIndex) {
      const actual = String(current[rowIndex][columnIndex] || '');
      const required = String(row[columnIndex] || '');
      if (rowIndex === 4 && !actual) return;
      if (actual !== required) {
        throw apiError3dp_('SETUP_ANCHOR_MISMATCH', API_3DP.settingsSheet + ' structure differs from the approved settings block.');
      }
    });
  });
  let row5Changed = false;
  if (!String(current[4][0] || '')) {
    sheet.getRange('A5').setValue(expected[4][0]);
    row5Changed = true;
  }
  if (isBlank3dp_(sheet.getRange('B5').getValue())) {
    sheet.getRange('B5').setValue(expected[4][1]);
    row5Changed = true;
  }
  if (!String(current[4][2] || '')) {
    sheet.getRange('C5').setValue(expected[4][2]);
    row5Changed = true;
  }
  if (row5Changed) changes.push(API_3DP.settingsSheet + '!A5:C5 initialized with the owner-editable planned defect rate');
  const valuesRange = sheet.getRange('B2:B5');
  const colors = valuesRange.getFontColors();
  const alreadyBlue = colors.every(function (row) { return String(row[0] || '').toLowerCase() === '#0000ff'; });
  if (!alreadyBlue) {
    valuesRange.setFontColor('#0000ff');
    changes.push(API_3DP.settingsSheet + '!B2:B5 marked as editable settings');
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
  return String(formula || '')
    .replace(/\s+/g, '')
    // Google Sheets may add single quotes around a sheet name when persisting the formula.
    // Quoted and unquoted names are semantically identical for the approved sheet names.
    .replace(/'([^']+)'!/g, '$1!');
}
