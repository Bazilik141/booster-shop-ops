/*
 * One-off CRM migrations and repairs archived 2026-08-13.
 * NOT DEPLOYED: this repository file must never be pasted into the live Apps Script project.
 * Extracted from owner-reported CRM Web App version V114 (2026-08-13).
 * Restore any function only after a fresh schema and live-state review; the original targets may have moved.
 */

const CRM_MAN_FOP_0005_USAGE_REPAIR_ = Object.freeze({
  order: 'MAN-FOP-0005', component: 'ACC-002', desired_component_qty: 1,
  fixture: 'FUR-BR-COLOR-MIX', payer: 'власник', desired_fixture_qty: 1,
  target_row: 268, target_sku: 'BR-CHARM-100', marker: '[repair:MAN-FOP-0005-usage-duplicates-v1]'
});

function previewManFop0005UsageDuplicateRepair() { return manFop0005UsageDuplicateRepairAction_(true); }
function repairManFop0005UsageDuplicates() {
  const lock = LockService.getScriptLock(); if (!lock.tryLock(30000)) throw new Error('CRM busy; retry later.');
  try { return manFop0005UsageDuplicateRepairAction_(false); } finally { lock.releaseLock(); }
}

function manFop0005UsageDuplicateRepairAction_(dryRun) {
  const ss = _getCrmSs(), config = CRM_MAN_FOP_0005_USAGE_REPAIR_;
  const sales = ss.getSheetByName('Продажі');
  const componentLedger = ensureOrderComponentUsageLedger_(ss, false);
  const fixtureLedger = ensure3dp019FixtureUsageLedger_(ss);
  if (!sales) throw new Error('Продажі sheet missing.');
  const orderRows = [];
  const salesValues = sales.getRange(3, 1, Math.max(sales.getLastRow() - 2, 1), 32).getValues();
  salesValues.forEach(function(row, index) { if (String(row[0] || '').trim() === config.order) orderRows.push({ row: index + 3, values: row }); });
  if (!orderRows.length || !orderRows.some(function(item) { return item.row === config.target_row && String(item.values[5] || '').trim() === config.target_sku; })) {
    throw new Error('MAN-FOP-0005 target rows no longer match the verified order.');
  }
  let componentQty = 0, componentPrroUnit = 0, componentMgmtUnit = 0;
  const componentValues = componentLedger.getRange(2, 1, Math.max(componentLedger.getLastRow() - 1, 1), 15).getValues();
  componentValues.forEach(function(row) {
    if (String(row[2] || '').trim() !== config.order || String(row[3] || '').trim() !== 'SKU' || String(row[4] || '').trim() !== config.component) return;
    componentQty = round2_(componentQty + num_(row[5]));
    if (num_(row[5]) > 0) { componentPrroUnit = num_(row[6]); componentMgmtUnit = num_(row[7]); }
  });
  let fixtureQty = 0, fixtureUnit = 0;
  const fixtureValues = fixtureLedger.getRange(2, 1, Math.max(fixtureLedger.getLastRow() - 1, 1), 14).getValues();
  fixtureValues.forEach(function(row) {
    if (String(row[3] || '').trim() !== config.order || String(row[4] || '').trim() !== config.fixture || String(row[5] || '').trim() !== config.payer ||
        Math.floor(num_(row[12])) !== config.target_row || String(row[13] || '').trim() !== config.target_sku) return;
    fixtureQty = round2_(fixtureQty + num_(row[6]));
    if (num_(row[6]) > 0) fixtureUnit = num_(row[7]);
  });
  if (componentQty < config.desired_component_qty || fixtureQty < config.desired_fixture_qty) throw new Error('Repair refuses to invent missing usage.');
  const componentAdjustment = round2_(config.desired_component_qty - componentQty);
  const fixtureAdjustment = round2_(config.desired_fixture_qty - fixtureQty);
  const noteChanges = orderRows.map(function(item) {
    const current = String(item.values[26] || '').trim();
    const parts = current.split(';').map(function(value) { return value.trim(); }).filter(Boolean);
    const normalized = parts.length > 1 && parts.every(function(value) { return value === parts[0]; }) ? parts[0] : current;
    return { row: item.row, before: current, after: normalized, would_change: normalized !== current };
  }).filter(function(item) { return item.would_change; });
  const wouldChange = !!componentAdjustment || !!fixtureAdjustment || noteChanges.length > 0;
  let componentWrite = null, fixtureWrite = null, syncResult = null, componentCost = null;
  if (!dryRun && wouldChange) {
    const date = orderRows[0].values[2] || new Date();
    if (componentAdjustment) {
      componentWrite = appendOrderComponents_(ss, { entries: [{ kind: 'SKU', code: config.component, name: config.component, qty: componentAdjustment, prroUnit: componentPrroUnit, mgmtUnit: componentMgmtUnit, note: config.marker, targetRow: 0, targetSku: '' }], prro_total: round2_(componentAdjustment * componentPrroUnit), mgmt_total: round2_(componentAdjustment * componentMgmtUnit) }, date, config.order, config.marker);
    }
    if (fixtureAdjustment) {
      fixtureWrite = append3dp019FixtureUsage_(ss, { entries: [{ name: config.fixture, payer: config.payer, qty: fixtureAdjustment, unitCost: fixtureUnit, targetRow: config.target_row, targetSku: config.target_sku }], payer: config.payer, total: round2_(fixtureAdjustment * fixtureUnit) }, date, 'Коригування дубля', config.order, config.marker);
    }
    noteChanges.forEach(function(item) { sales.getRange(item.row, 27).setValue(item.after); });
    SpreadsheetApp.flush();
    const rowNumbers = orderRows.map(function(item) { return item.row; });
    syncResult = sync3dpPackagingCost_(sales, config.order, rowNumbers, 'repairManFop0005UsageDuplicates', { request_id: 'repair-MAN-FOP-0005-v1' });
    SpreadsheetApp.flush();
    componentCost = applyOrderComponentCost_(ss, config.order, rowNumbers);
    if (componentAdjustment) updateSkuCurrentCost_(ss);
    invalidateDoGetCache_();
  }
  const report = { ok: true, action: 'man_fop_0005_usage_duplicate_repair', dry_run: !!dryRun,
    assumption: 'Keep the older blind-packet gift; retain exactly one ACC-002 and one owner-paid FUR-BR-COLOR-MIX on CRM row 268.',
    before: { component: config.component, component_qty: componentQty, fixture: config.fixture, fixture_qty: fixtureQty },
    desired: { component_qty: config.desired_component_qty, fixture_qty: config.desired_fixture_qty },
    adjustments: { component_qty: componentAdjustment, fixture_qty: fixtureAdjustment, sales_notes_to_normalize: noteChanges.length },
    component_write: componentWrite, fixture_write: fixtureWrite, sync: syncResult, component_cost: componentCost,
    would_change: wouldChange, already_applied: !wouldChange };
  Logger.log(JSON.stringify(report)); return report;
}

function setup3dp019FixturePayerPhaseA() {
const sheetName = 'Розхідники';
const headerRow = 3;
const firstDataRow = 4;
const legacyLastColumn = 14; // A:N. N is the existing sale-form dropdown formula seed.
const payerColumn = 15; // O. It must be appended after N, never inserted before an existing column.
const legacyHeaders = [
  'Тип розхідника', 'Категорія', 'Собівартість 1 шт', 'Початково на складі', 'Початково їде',
  'Надійшло через витрати', 'Їде через витрати', 'Використано в продажах', 'Залишок на складі',
  'Очікується', 'Вартість залишку', 'Примітка', '', 'Dropdown для форми продажу'
];
const fixtureNames = ['FUR-BR-COLOR-MIX', 'FUR-BR-CARB'];
const legacyCategory = '3D-друк';
const fixtureCategory = 'Фурнітура';
const ownerPayer = 'власник';
const text = function(value) { return String(value == null ? '' : value).trim(); };

const sheet = SpreadsheetApp.getActive().getSheetByName(sheetName);
if (!sheet) throw new Error('3D-P-019 Phase A stopped: sheet ' + sheetName + ' was not found.');

const lastColumn = sheet.getLastColumn();
if (lastColumn < legacyLastColumn) {
  throw new Error('3D-P-019 Phase A stopped: ' + sheetName + ' has only ' + lastColumn + ' used columns; expected A:N.');
}
if (lastColumn > payerColumn) {
  throw new Error('3D-P-019 Phase A stopped: ' + sheetName + ' has data after column O; append safety cannot be proven.');
}

const headers = sheet.getRange(headerRow, 1, 1, lastColumn).getDisplayValues()[0].map(text);
for (let index = 0; index < legacyHeaders.length; index++) {
  if (headers[index] !== legacyHeaders[index]) {
    throw new Error('3D-P-019 Phase A stopped: ' + sheetName + ' header ' + String.fromCharCode(65 + index)
      + ' differs from the verified A:N schema.');
  }
}
const hasPayerColumn = lastColumn === payerColumn;
if (hasPayerColumn && headers[payerColumn - 1] !== 'Платник') {
  throw new Error('3D-P-019 Phase A stopped: column O is not the expected Платник header.');
}

const lastRow = sheet.getLastRow();
if (lastRow < firstDataRow) throw new Error('3D-P-019 Phase A stopped: no consumable rows were found.');
const rowWidth = hasPayerColumn ? payerColumn : legacyLastColumn;
const rows = sheet.getRange(firstDataRow, 1, lastRow - firstDataRow + 1, rowWidth).getValues()
  .map(function(values, index) { return { row: firstDataRow + index, values: values }; });
const fixtureRows = rows.filter(function(item) {
  const category = text(item.values[1]);
  return category === legacyCategory || category === fixtureCategory;
});

if (fixtureRows.length !== fixtureNames.length) {
  throw new Error('3D-P-019 Phase A stopped: expected exactly two fixture-category rows, found ' + fixtureRows.length + '.');
}
const seenFixtures = {};
fixtureRows.forEach(function(item) {
  const name = text(item.values[0]);
  const category = text(item.values[1]);
  const payer = hasPayerColumn ? text(item.values[payerColumn - 1]) : '';
  if (fixtureNames.indexOf(name) === -1 || seenFixtures[name]) {
    throw new Error('3D-P-019 Phase A stopped: unexpected or duplicate fixture row ' + name + '.');
  }
  if (category !== legacyCategory && category !== fixtureCategory) {
    throw new Error('3D-P-019 Phase A stopped: fixture ' + name + ' has an unsupported category.');
  }
  if (payer && payer !== ownerPayer) {
    throw new Error('3D-P-019 Phase A stopped: fixture ' + name + ' already has an unapproved payer value.');
  }
  seenFixtures[name] = true;
});
fixtureNames.forEach(function(name) {
  if (!seenFixtures[name]) throw new Error('3D-P-019 Phase A stopped: required fixture ' + name + ' was not found.');
});

let headerAdded = false;
if (!hasPayerColumn) {
  sheet.insertColumnAfter(legacyLastColumn);
  sheet.getRange(headerRow, payerColumn).setValue('Платник');
  headerAdded = true;
}

let categoriesRenamed = 0;
let payersBackfilled = 0;
fixtureRows.forEach(function(item) {
  if (text(item.values[1]) === legacyCategory) {
    sheet.getRange(item.row, 2).setValue(fixtureCategory);
    categoriesRenamed++;
  }
  const payer = hasPayerColumn ? text(item.values[payerColumn - 1]) : '';
  if (!payer) {
    sheet.getRange(item.row, payerColumn).setValue(ownerPayer);
    payersBackfilled++;
  }
});
SpreadsheetApp.flush();

const result = {
  ok: true,
  action: '3dp019_phase_a_fixture_payer_setup',
  header_added: headerAdded,
  category_rows_renamed: categoriesRenamed,
  payer_rows_backfilled: payersBackfilled,
  already_applied: !headerAdded && categoriesRenamed === 0 && payersBackfilled === 0
};
Logger.log(JSON.stringify(result));
return result;
}

const CRM_CONSUMABLE_ARRIVAL_REPAIR_NAMES_ = {
  'Стікер лого+QR': true,
  'Аніме-брелок поліестер': true,
  'Брошки TCG енергії': true,
  'Брелок солом\'яний капелюх': true,
  'Фоторамка One Piece': true,
  'Фоторамка Pokémon': true,
  'Наліпка One Piece': true,
  'Нашивка': true,
  'Фігурка краба': true,
  'Піни One Piece': true,
  'Фігурка Pokémon': true,
  'FUR-BR-COLOR-MIX': true,
  'FUR-BR-CARB': true,
};

function previewConsumableArrivalStatusRepair() { return consumableArrivalStatusRepairAction_(true); }
function repairConsumableArrivalStatus() {
const lock = LockService.getScriptLock(); if (!lock.tryLock(30000)) throw new Error('CRM busy; retry later.');
try { return consumableArrivalStatusRepairAction_(false); } finally { lock.releaseLock(); }
}
function consumableArrivalStatusRepairAction_(dryRun) {
const ss = _getCrmSs(), expenses = ss.getSheetByName('Витрати');
if (!expenses) throw new Error('Витрати sheet missing.');
const lastRow = Math.max(expenses.getLastRow(), 3);
const values = expenses.getRange(3, 1, lastRow - 2, 11).getValues();
const rows = [];
values.forEach(function(row, index) {
  const name = String(row[7] || '').trim(), status = String(row[9] || '').trim();
  if (!CRM_CONSUMABLE_ARRIVAL_REPAIR_NAMES_[name] || status !== 'Їде') return;
  rows.push({ row_index: index + 3, name: name, qty: round2_(num_(row[8])), before_status: status, after_status: 'На складі' });
});
if (!dryRun) rows.forEach(function(item) { expenses.getRange(item.row_index, 10).setValue('На складі'); });
if (!dryRun && rows.length) { SpreadsheetApp.flush(); invalidateDoGetCache_(); }
const report = { ok: true, action: 'consumable_arrival_status_repair', dry_run: !!dryRun, rows: rows, rows_to_update: rows.length, rows_updated: dryRun ? 0 : rows.length, already_applied: rows.length === 0 };
Logger.log(JSON.stringify(report)); return report;
}

function apiLtvReportLegacy_(params) {
params = params || {};
const limit = Math.max(1, Math.min(apiNum_(params.limit) || 10, 50));
const rows = apiReadCrmSalesRows_();
const clients = {};
rows.forEach(function(row) {
const key = apiCustomerKey_(row);
if (!key) return;
if (!clients[key]) clients[key] = { display: apiCustomerDisplay_(row), orders: {}, units: 0, revenue: 0 };
clients[key].orders[String(row[0] || '')] = true;
clients[key].units = round2_(clients[key].units + num_(row[7]));
clients[key].revenue = round2_(clients[key].revenue + num_(row[10]));
});
const result = Object.keys(clients).map(function(key) { const c = clients[key]; return { identifier: c.display, display: c.display, orders: Object.keys(c.orders).length, units: c.units, revenue: round2_(c.revenue), ltv: round2_(c.revenue) }; });
result.sort(function(a, b) { return b.ltv - a.ltv; });
return { ok: true, limit: limit, clients: result.slice(0, limit) };
}

const CRM_MAN_FOP_0006_ALLOCATION_REPAIR_ = Object.freeze({ order: 'MAN-FOP-0006', rows: [272, 273, 274], skus: ['ACC-3D-DITTO-410', 'PKM-JP-MBX-XL', 'OP-JP-MBX-ST'], discount: 100, packaging: 80, delivery: 120 });

function previewManFop0006AllocationRepair() { return manFop0006AllocationRepairAction_(true); }
function repairManFop0006Allocations() {
  const lock = LockService.getScriptLock(); if (!lock.tryLock(30000)) throw new Error('CRM busy; retry later.');
  try { return manFop0006AllocationRepairAction_(false); } finally { lock.releaseLock(); }
}

function manFop0006AllocationRepairAction_(dryRun) {
  const ss = _getCrmSs(), sales = ss.getSheetByName('Продажі'), config = CRM_MAN_FOP_0006_ALLOCATION_REPAIR_;
  const rows = config.rows.map(function(row, index) {
    const values = sales.getRange(row, 1, 1, 29).getValues()[0];
    if (String(values[0] || '').trim() !== config.order || String(values[5] || '').trim() !== config.skus[index]) throw new Error('MAN-FOP-0006 rows no longer match the verified order.');
    return { row: row, values: values, gross: num_(values[7]) * num_(values[8]) };
  });
  const discount = allocateAmount_(config.discount, rows.map(function(item) { return item.gross; }));
  const packaging = allocateAmount_(config.packaging, rows.map(function(item) { return item.gross; }));
  const delivery = allocateAmount_(config.delivery, rows.map(function(item) { return item.gross; }));
  const changes = rows.map(function(item, index) { return { row: item.row, before: [num_(item.values[9]), num_(item.values[15]), num_(item.values[19])], after: [discount[index], packaging[index], delivery[index]] }; });
  const wouldChange = changes.some(function(item) { return JSON.stringify(item.before) !== JSON.stringify(item.after); });
  if (!dryRun && wouldChange) changes.forEach(function(item) { sales.getRange(item.row, 10).setValue(item.after[0]); sales.getRange(item.row, 16).setValue(item.after[1]); sales.getRange(item.row, 20).setValue(item.after[2]); });
  if (!dryRun && wouldChange) { SpreadsheetApp.flush(); invalidateDoGetCache_(); }
  const report = { ok: true, action: 'man_fop_0006_allocation_repair', dry_run: !!dryRun, order: config.order, changes: changes,
    totals: { discount: round2_(discount.reduce(function(sum, value) { return sum + value; }, 0)), packaging: round2_(packaging.reduce(function(sum, value) { return sum + value; }, 0)), delivery: round2_(delivery.reduce(function(sum, value) { return sum + value; }, 0)) }, already_applied: !wouldChange };
  Logger.log(JSON.stringify(report)); return report;
}

const CRM_MYSTERY_COST_REPAIR_ORDERS_ = ['OC-FOP-0309', 'OC-FOP-0312', 'OLX-FOP-0050'];
function previewMysteryBoxCostRegressionRepair() { return mysteryBoxCostRegressionRepairAction_(true); }
function repairMysteryBoxCostRegression() {
const lock = LockService.getScriptLock(); if (!lock.tryLock(30000)) throw new Error('CRM busy; retry later.');
try { return mysteryBoxCostRegressionRepairAction_(false); } finally { lock.releaseLock(); }
}
function mysteryBoxCostRegressionRepairAction_(dryRun) {
const ss = _getCrmSs();
const results = CRM_MYSTERY_COST_REPAIR_ORDERS_.map(function(order) { return recalculateMysteryBoxOrderCost_(ss, order, { dry_run: dryRun }); });
const missing = CRM_MYSTERY_COST_REPAIR_ORDERS_.filter(function(_, index) { return !results[index]; });
if (missing.length) throw new Error('Mystery Box rows or linked writeoffs missing: ' + missing.join(', '));
if (!dryRun) { SpreadsheetApp.flush(); updateSkuCurrentCost_(ss); invalidateDoGetCache_(); }
const changed = results.filter(function(item) { return item.would_change; }).length;
const report = { ok: true, action: 'mystery_box_cost_regression_repair', dry_run: !!dryRun, orders: results, orders_changed: changed, already_applied: changed === 0 };
Logger.log(JSON.stringify(report)); return report;
}

function plan3dp019HistoricalFixtureFrozenCleanup_(salesRows, ledgerRows) {
  const coveredOrders = {};
  (ledgerRows || []).forEach(function(row) {
    const source = String(row[2] || '').trim();
    const reference = String(row[3] || '').trim();
    if ((source === 'Продаж' || source === 'Коригування') && reference) coveredOrders[reference] = true;
  });
  const candidates = [];
  let skippedCovered = 0;
  (salesRows || []).forEach(function(row) {
    const order = String(row[CRM_3DP_ORDER_HEADER_] || '').trim();
    const payer = String(row[CRM_3DP_FIXTURE_PAYER_HEADER_] || '').trim();
    const rawCost = row[CRM_3DP_FIXTURE_COST_HEADER_];
    const cost = crm3dpFiniteNonNegative_(rawCost);
    const hasValue = payer !== '' || (cost === null ? String(rawCost == null ? '' : rawCost).trim() !== '' : Math.abs(cost) >= 0.005);
    if (!hasValue) return;
    if (coveredOrders[order]) { skippedCovered++; return; }
    candidates.push({ row: row, payer: payer, cost: rawCost == null ? '' : rawCost });
  });
  return {
    rows_inspected: (salesRows || []).length,
    rows_skipped_ledger_covered: skippedCovered,
    candidates: candidates,
  };
}

function preview3dp019HistoricalFixtureFrozenValues() {
  return run3dp019HistoricalFixtureFrozenCleanup_(true);
}

function clear3dp019HistoricalFixtureFrozenValues() {
  return run3dp019HistoricalFixtureFrozenCleanup_(false);
}

function run3dp019HistoricalFixtureFrozenCleanup_(dryRun) {
  const ss = SpreadsheetApp.getActive();
  const ledger = ss.getSheetByName(CRM_3DP019_FIXTURE_USAGE_SHEET_);
  if (!ledger) throw new Error('3D-P-019 cleanup stopped: run setup3dp019FixtureUsagePhaseB() first so ledger coverage can be checked.');
  const ledgerRows = ledger.getRange(2, 1, Math.max(ledger.getLastRow() - 1, 1), 11).getValues();
  const config = crm3dpConfig_();
  if (!config) throw new Error('3D-P-019 cleanup stopped: 3D-P sync properties are not configured.');
  const salesRows = crm3dpSaleRows_(config);
  const cleanupPlan = plan3dp019HistoricalFixtureFrozenCleanup_(salesRows, ledgerRows);
  const candidates = cleanupPlan.candidates;
  const result = {
    ok: true,
    action: dryRun ? '3dp019_historical_fixture_frozen_preview' : '3dp019_historical_fixture_frozen_clear',
    dry_run: !!dryRun,
    rows_inspected: cleanupPlan.rows_inspected,
    rows_skipped_ledger_covered: cleanupPlan.rows_skipped_ledger_covered,
    rows_to_clear: candidates.length,
    rows_cleared: 0,
    already_applied: candidates.length === 0,
    failures: []
  };
  if (dryRun || !candidates.length) { Logger.log(JSON.stringify(result)); return result; }
  candidates.forEach(function(candidate) {
    try {
      crm3dpFetchJson_(config.url, {
        method: 'post', contentType: 'text/plain;charset=utf-8', payload: JSON.stringify({
          action: '3dp_write', token: config.token, sheet: CRM_3DP_SALES_SHEET_, sku_or_row: candidate.row.row_number,
          column: 'V', value: 0, expected_current: candidate.cost,
        }),
      });
      crm3dpFetchJson_(config.url, {
        method: 'post', contentType: 'text/plain;charset=utf-8', payload: JSON.stringify({
          action: '3dp_write', token: config.token, sheet: CRM_3DP_SALES_SHEET_, sku_or_row: candidate.row.row_number,
          column: 'W', value: '', expected_current: candidate.payer,
        }),
      });
      result.rows_cleared++;
    } catch (error) {
      result.ok = false;
      result.failures.push({ row: candidate.row.row_number, detail: String(error && error.message ? error.message : error) });
    }
  });
  Logger.log(JSON.stringify(result));
  return result;
}
