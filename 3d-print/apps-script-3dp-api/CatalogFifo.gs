/**
 * Actual manufactured-batch FIFO for the 3D-P workbook.
 *
 * This file deliberately keeps immutable cost inputs outside formula columns.
 * Setup is owner-run; API mutations are routed through Code.gs and its lock.
 */

const CATALOG_FIFO_3DP = Object.freeze({
  analyticsSheet: 'Аналітика_SKU',
  batchesSheet: '_Партії_FIFO_3DP',
  allocationsSheet: '_Розподіл_FIFO_3DP',
  defaultProfitShare: 0.5,
  batchHeaders: Object.freeze([
    'batch_id', 'request_id', 'print_log_row', 'created_at_kyiv', 'manufactured_date', 'SKU',
    'printed_qty', 'defects_qty', 'good_qty', 'actual_weight_g', 'actual_time_h',
    'spool_weight_g', 'spool_price_uah', 'printer_power_kw', 'electricity_uah_kwh',
    'depreciation_uah_h', 'serhiy_consumables_uah', 'actual_total_cost_uah',
    'allocated_qty', 'available_qty', 'status', 'actor', 'payload_fingerprint',
    'unit_cost_uah', 'remaining_cost_uah',
  ]),
  allocationHeaders: Object.freeze([
    'allocation_id', 'operation_id', 'created_at_kyiv', 'source_type', 'source_ref', 'SKU',
    'quantity', 'total_cost_uah', 'batch_breakdown_json', 'direction', 'actor',
    'payload_fingerprint', 'status', 'reversal_of', 'sale_row', 'crm_row',
  ]),
});

function fifo3dpRound_(value, digits) {
  const multiplier = Math.pow(10, digits == null ? 6 : digits);
  return Math.round((Number(value) + Number.EPSILON) * multiplier) / multiplier;
}

function fifo3dpStableJson_(value) {
  if (value == null || typeof value !== 'object') return JSON.stringify(value);
  if (Array.isArray(value)) return '[' + value.map(fifo3dpStableJson_).join(',') + ']';
  return '{' + Object.keys(value).sort().map(function (key) {
    return JSON.stringify(key) + ':' + fifo3dpStableJson_(value[key]);
  }).join(',') + '}';
}

function fifo3dpFingerprint_(value) {
  const bytes = Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, fifo3dpStableJson_(value), Utilities.Charset.UTF_8);
  return bytes.map(function (item) { return ('0' + ((item + 256) % 256).toString(16)).slice(-2); }).join('');
}

function fifo3dpCalculateBatchCost_(input) {
  const weight = Number(input.actual_weight_g);
  const time = Number(input.actual_time_h);
  const spoolWeight = Number(input.spool_weight_g);
  const spoolPrice = Number(input.spool_price_uah);
  const power = Number(input.printer_power_kw);
  const electricity = Number(input.electricity_uah_kwh);
  const depreciation = Number(input.depreciation_uah_h);
  const extra = Number(input.serhiy_consumables_uah || 0);
  [weight, time, spoolWeight, spoolPrice, power, electricity, depreciation, extra].forEach(function (value) {
    if (!Number.isFinite(value) || value < 0) throw new Error('FIFO_BATCH_COST_INVALID_INPUT');
  });
  if (!(spoolWeight > 0) || !(spoolPrice > 0) || !(time > 0)) throw new Error('FIFO_BATCH_COST_REQUIRED_INPUT');
  const material = weight / spoolWeight * spoolPrice;
  const energy = time * power * electricity;
  const amortization = time * depreciation;
  return {
    material_uah: fifo3dpRound_(material, 6),
    electricity_uah: fifo3dpRound_(energy, 6),
    depreciation_uah: fifo3dpRound_(amortization, 6),
    serhiy_consumables_uah: fifo3dpRound_(extra, 6),
    total_uah: fifo3dpRound_(material + energy + amortization + extra, 6),
  };
}

function fifo3dpPlanAllocation_(layers, quantity) {
  const requested = Number(quantity);
  if (!Number.isInteger(requested) || requested <= 0) throw new Error('FIFO_INVALID_QUANTITY');
  const ordered = layers.slice().filter(function (layer) { return Number(layer.available_qty) > 0; }).sort(function (left, right) {
    const dateOrder = String(left.manufactured_at || '').localeCompare(String(right.manufactured_at || ''));
    return dateOrder || Number(left.row || 0) - Number(right.row || 0) || String(left.batch_id).localeCompare(String(right.batch_id));
  });
  let remaining = requested;
  let total = 0;
  const allocations = [];
  ordered.forEach(function (layer) {
    if (!remaining) return;
    const available = Number(layer.available_qty);
    const take = Math.min(available, remaining);
    const unitCost = Number(layer.unit_cost_uah);
    if (!Number.isFinite(unitCost) || unitCost < 0) throw new Error('FIFO_INVALID_LAYER_COST');
    const cost = fifo3dpRound_(take * unitCost, 6);
    allocations.push({ batch_id: layer.batch_id, row: layer.row, quantity: take, cost_uah: cost, unit_cost_uah: unitCost });
    total = fifo3dpRound_(total + cost, 6);
    remaining -= take;
  });
  if (remaining) {
    const error = new Error('FIFO_INSUFFICIENT_COSTED_STOCK');
    error.required = requested;
    error.available = requested - remaining;
    throw error;
  }
  return { quantity: requested, total_cost_uah: total, unit_cost_uah: fifo3dpRound_(total / requested, 6), allocations: allocations };
}

function fifo3dpAssertHeaders_(sheet, expected, code) {
  const actual = sheet.getRange(1, 1, 1, expected.length).getDisplayValues()[0];
  if (JSON.stringify(actual) !== JSON.stringify(expected)) throw apiError3dp_(code, 'FIFO sheet headers do not match the approved schema.');
}

function fifo3dpEnsureHiddenSheet_(spreadsheet, name, headers) {
  let sheet = spreadsheet.getSheetByName(name);
  let created = false;
  if (!sheet) {
    sheet = spreadsheet.insertSheet(name);
    sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
    sheet.setFrozenRows(1);
    created = true;
  }
  fifo3dpAssertHeaders_(sheet, headers, 'FIFO_SCHEMA_MISMATCH');
  if (!sheet.isSheetHidden()) sheet.hideSheet();
  return { sheet: sheet, created: created };
}

function fifo3dpAnalyticsHeaders_() {
  return PRICE_MODEL_COLUMNS_3DP.analytics;
}

function fifo3dpEnsureAnalyticsSheet_(spreadsheet) {
  let sheet = spreadsheet.getSheetByName(CATALOG_FIFO_3DP.analyticsSheet);
  let created = false;
  if (!sheet) {
    sheet = spreadsheet.insertSheet(CATALOG_FIFO_3DP.analyticsSheet);
    sheet.getRange(1, 1, 1, fifo3dpAnalyticsHeaders_().length).setValues([fifo3dpAnalyticsHeaders_()]);
    sheet.setFrozenRows(1);
    created = true;
  }
  fifo3dpAssertHeaders_(sheet, fifo3dpAnalyticsHeaders_(), 'FIFO_ANALYTICS_SCHEMA_MISMATCH');
  return { sheet: sheet, created: created };
}

function preview3dpCatalogFifoSchema() {
  const spreadsheet = getSpreadsheet3dp_();
  return {
    ok: true,
    spreadsheet_id: spreadsheet.getId(),
    analytics_sheet_exists: Boolean(spreadsheet.getSheetByName(CATALOG_FIFO_3DP.analyticsSheet)),
    batches_sheet_exists: Boolean(spreadsheet.getSheetByName(CATALOG_FIFO_3DP.batchesSheet)),
    allocations_sheet_exists: Boolean(spreadsheet.getSheetByName(CATALOG_FIFO_3DP.allocationsSheet)),
    planned_changes: [CATALOG_FIFO_3DP.analyticsSheet, CATALOG_FIFO_3DP.batchesSheet, CATALOG_FIFO_3DP.allocationsSheet],
  };
}

function setup3dpCatalogFifoSchema() {
  return withScriptLock3dp_(function () {
    const spreadsheet = getSpreadsheet3dp_();
    const analytics = fifo3dpEnsureAnalyticsSheet_(spreadsheet);
    const batches = fifo3dpEnsureHiddenSheet_(spreadsheet, CATALOG_FIFO_3DP.batchesSheet, CATALOG_FIFO_3DP.batchHeaders);
    const allocations = fifo3dpEnsureHiddenSheet_(spreadsheet, CATALOG_FIFO_3DP.allocationsSheet, CATALOG_FIFO_3DP.allocationHeaders);
    const sync = syncActiveNomenclatureAnalytics3dp_(spreadsheet);
    return {
      ok: true,
      action: 'setup3dp_catalog_fifo_schema',
      already_applied: !analytics.created && !batches.created && !allocations.created && !sync.changed,
      analytics_rows: sync.active_sku_count,
      initialized_profit_shares: sync.initialized_skus,
    };
  });
}

function fifo3dpBatchRows_(spreadsheet, sku) {
  const sheet = spreadsheet.getSheetByName(CATALOG_FIFO_3DP.batchesSheet);
  if (!sheet) throw apiError3dp_('FIFO_SCHEMA_NOT_READY', 'Run setup3dpCatalogFifoSchema first.');
  fifo3dpAssertHeaders_(sheet, CATALOG_FIFO_3DP.batchHeaders, 'FIFO_SCHEMA_MISMATCH');
  if (sheet.getLastRow() < 2) return [];
  return sheet.getRange(2, 1, sheet.getLastRow() - 1, CATALOG_FIFO_3DP.batchHeaders.length).getValues().map(function (row, index) {
    return {
      row: index + 2,
      batch_id: String(row[0] || ''),
      manufactured_at: String(row[3] || ''),
      sku: String(row[5] || ''),
      good_qty: Number(row[8] || 0),
      total_cost_uah: Number(row[17] || 0),
      allocated_qty: Number(row[18] || 0),
      available_qty: Number(row[19] || 0),
      status: String(row[20] || ''),
      fingerprint: String(row[22] || ''),
      unit_cost_uah: Number(row[23] || (Number(row[8]) ? Number(row[17]) / Number(row[8]) : 0)),
    };
  }).filter(function (row) { return row.sku === sku && row.status === 'active'; });
}

function fifo3dpFindOperation_(sheet, operationId) {
  if (!sheet || sheet.getLastRow() < 2) return null;
  const values = sheet.getRange(2, 1, sheet.getLastRow() - 1, CATALOG_FIFO_3DP.allocationHeaders.length).getValues();
  for (let index = values.length - 1; index >= 0; index -= 1) {
    if (String(values[index][1] || '') === operationId && String(values[index][12] || '') === 'committed') {
      return { row: index + 2, values: values[index] };
    }
  }
  return null;
}

function fifo3dpReplayResult_(existing, fingerprint) {
  if (String(existing.values[11] || '') !== fingerprint) throw apiError3dp_('IDEMPOTENCY_CONFLICT', 'operation_id was already used with a different payload.');
  const breakdown = JSON.parse(String(existing.values[8] || '[]'));
  const sourceType = String(existing.values[3] || '');
  const projectionRow = Number(existing.values[14]);
  return {
    action: (sourceType === 'marketing_writeoff' || sourceType === 'marketing_gift') ? '3dp_marketing_writeoff' : '3dp_crm_sale_commit',
    already_applied: true, operation_id: String(existing.values[1]),
    allocation_id: String(existing.values[0]), quantity: Number(existing.values[6]),
    total_cost_uah: Number(existing.values[7]), unit_cost_uah: fifo3dpRound_(Number(existing.values[7]) / Number(existing.values[6]), 6),
    allocations: breakdown, sale_row: sourceType === 'marketing_gift' ? 0 : projectionRow,
    gift_row: sourceType === 'marketing_gift' ? projectionRow : 0, crm_row: Number(existing.values[15]),
  };
}

function fifo3dpPlanReversal_(breakdown, batches) {
  const byId = {};
  (batches || []).forEach(function (batch) { byId[String(batch.batch_id || '')] = batch; });
  let quantity = 0;
  let totalCost = 0;
  const restores = (Array.isArray(breakdown) ? breakdown : []).map(function (entry) {
    const batchId = String(entry.batch_id || '');
    const batch = byId[batchId];
    const restoreQty = Number(entry.quantity);
    const restoreCost = Number(entry.cost_uah);
    if (!batch || Number(batch.row) !== Number(entry.row)) throw new Error('FIFO_REVERSAL_BATCH_NOT_FOUND');
    if (!Number.isInteger(restoreQty) || restoreQty <= 0 || !Number.isFinite(restoreCost) || restoreCost < 0) throw new Error('FIFO_REVERSAL_BREAKDOWN_INVALID');
    if (Number(batch.allocated_qty) < restoreQty || Number(batch.available_qty) + restoreQty > Number(batch.good_qty)) throw new Error('FIFO_REVERSAL_BATCH_STATE_CONFLICT');
    quantity += restoreQty;
    totalCost = fifo3dpRound_(totalCost + restoreCost, 6);
    return { batch_id: batchId, row: Number(batch.row), quantity: restoreQty, cost_uah: restoreCost };
  });
  if (!restores.length) throw new Error('FIFO_REVERSAL_BREAKDOWN_EMPTY');
  return { quantity: quantity, total_cost_uah: totalCost, restores: restores };
}

function fifo3dpFindReversal_(sheet, allocationId) {
  if (!sheet || sheet.getLastRow() < 2) return null;
  const values = sheet.getRange(2, 1, sheet.getLastRow() - 1, CATALOG_FIFO_3DP.allocationHeaders.length).getValues();
  for (let index = values.length - 1; index >= 0; index -= 1) {
    if (String(values[index][13] || '') === allocationId && String(values[index][12] || '') === 'committed') return { row: index + 2, values: values[index] };
  }
  return null;
}

function fifo3dpAppendNegativeProjection_(spreadsheet, original, reversalId, returnDate, reason, actor) {
  const sourceType = String(original.values[3] || '');
  const originalRow = Number(original.values[14]);
  const sheetName = (sourceType === 'crm_sale' || sourceType === 'marketing_writeoff') ? SHEETS_3DP.sales : sourceType === 'crm_gift' ? SHEETS_3DP.plyushky : '';
  if (!sheetName || !(originalRow >= 2)) throw apiError3dp_('FIFO_REVERSAL_SOURCE_UNSUPPORTED', 'Only a committed sale, marketing writeoff, or gift allocation can be reversed.');
  const sheet = getSheet3dp_(spreadsheet, sheetName);
  const row = findFirstBusinessEmptyRow3dp_(sheet, sheetName, actor);
  const snapshot = snapshotRange3dp_(sheet, 'A' + row + ':' + numberToColumn3dp_(sheet.getLastColumn()) + row);
  const source = sheet.getRange(originalRow, 1, 1, sheet.getLastColumn()).getValues()[0];
  copyFormulaCells3dp_(sheet, sheetName, row);
  if (sourceType === 'crm_sale' || sourceType === 'marketing_writeoff') {
    ['A','B','E','F','G','H','M','N','T','U','V','W','X','Y','Z','AA'].forEach(function (column) {
      sheet.getRange(row, columnToNumber3dp_(column)).setValue(source[columnToNumber3dp_(column) - 1]);
    });
    sheet.getRange(row, 1).setValue(returnDate);
    sheet.getRange(row, 4).setValue(-Math.abs(Number(original.values[6])));
  } else {
    ['A','B','G'].forEach(function (column) { sheet.getRange(row, columnToNumber3dp_(column)).setValue(source[columnToNumber3dp_(column) - 1]); });
    sheet.getRange(row, 1).setValue(returnDate);
    sheet.getRange(row, 6).setValue(-Math.abs(Number(original.values[6])));
    sheet.getRange(row, 8).setValue(['[fifo_reversal:' + reversalId + ']', reason].filter(Boolean).join(' '));
  }
  return { sheet: sheet, row: row, snapshot: snapshot, source_type: sourceType };
}

function fifo3dpReverseAction_(spreadsheet, body, actor) {
  assertOwner3dp_(actor, 'Only the owner/CRM token may reverse FIFO allocations.');
  const operationId = String(body.operation_id || '').trim();
  const originalOperationId = String(body.original_operation_id || '').trim();
  const returnDate = String(body.date || '').trim().slice(0, 10);
  const reason = String(body.reason || '').trim();
  if (!/^[A-Za-z0-9:_-]{8,120}$/.test(operationId)) throw apiError3dp_('OPERATION_ID_REQUIRED', 'A stable reversal operation_id is required.');
  if (!/^[A-Za-z0-9:_-]{8,120}$/.test(originalOperationId) || operationId === originalOperationId) throw apiError3dp_('ORIGINAL_OPERATION_REQUIRED', 'A different original_operation_id is required.');
  if (!/^\d{4}-\d{2}-\d{2}$/.test(returnDate)) throw apiError3dp_('DATE_REQUIRED', 'Reversal date must use YYYY-MM-DD.');
  if (!reason || reason.length > 220) throw apiError3dp_('REVERSAL_REASON_REQUIRED', 'A reversal reason up to 220 characters is required.');
  assertManualValue3dp_(reason);

  const allocations = fifo3dpEnsureHiddenSheet_(spreadsheet, CATALOG_FIFO_3DP.allocationsSheet, CATALOG_FIFO_3DP.allocationHeaders).sheet;
  const original = fifo3dpFindOperation_(allocations, originalOperationId);
  if (!original || String(original.values[9] || '') !== 'consume') throw apiError3dp_('FIFO_ORIGINAL_NOT_FOUND', 'Committed consume allocation was not found.');
  if (['marketing_writeoff', 'marketing_gift'].indexOf(String(original.values[3] || '')) !== -1) {
    throw apiError3dp_('MARKETING_REVERSAL_REQUIRES_CRM', 'Marketing reversal must also compensate the CRM expense and payout; this FIFO-only route cannot do that.');
  }
  const canonical = { operation_id: operationId, original_operation_id: originalOperationId, original_allocation_id: String(original.values[0]), date: returnDate, reason: reason };
  const fingerprint = fifo3dpFingerprint_(canonical);
  const replay = fifo3dpFindOperation_(allocations, operationId);
  if (replay) {
    if (String(replay.values[11] || '') !== fingerprint) throw apiError3dp_('IDEMPOTENCY_CONFLICT', 'operation_id was already used with a different reversal payload.');
    return { action: '3dp_fifo_reverse', already_applied: true, operation_id: operationId, reversal_id: String(replay.values[0]), reversal_of: String(replay.values[13]), restored_quantity: Math.abs(Number(replay.values[6])), restored_cost_uah: Math.abs(Number(replay.values[7])), projection_row: Number(replay.values[14]) };
  }
  if (fifo3dpFindReversal_(allocations, String(original.values[0]))) throw apiError3dp_('FIFO_ALREADY_REVERSED', 'The original allocation already has a committed reversal.');

  const batchSheet = spreadsheet.getSheetByName(CATALOG_FIFO_3DP.batchesSheet);
  if (!batchSheet) throw apiError3dp_('FIFO_SCHEMA_NOT_READY', 'Run setup3dpCatalogFifoSchema first.');
  const batchRows = fifo3dpBatchRows_(spreadsheet, String(original.values[5]));
  const breakdown = JSON.parse(String(original.values[8] || '[]'));
  let plan;
  try { plan = fifo3dpPlanReversal_(breakdown, batchRows); }
  catch (error) { throw apiError3dp_(String(error && error.message || 'FIFO_REVERSAL_INVALID'), 'Original FIFO layers no longer match their committed state.'); }
  if (plan.quantity !== Number(original.values[6]) || Math.abs(plan.total_cost_uah - Number(original.values[7])) > 0.000001) throw apiError3dp_('FIFO_REVERSAL_TOTAL_MISMATCH', 'Original allocation totals do not match its batch breakdown.');

  const reversalId = '3DP-R-' + operationId.replace(/[^A-Za-z0-9_-]/g, '-').slice(0, 70);
  const allocationRow = allocations.getLastRow() + 1;
  const snapshots = plan.restores.map(function (item) { return { row: item.row, values: batchSheet.getRange(item.row, 19, 1, 2).getValues()[0] }; });
  let projection = null;
  try {
    plan.restores.forEach(function (item) {
      const range = batchSheet.getRange(item.row, 19, 1, 2);
      const before = range.getValues()[0];
      range.setValues([[Number(before[0]) - item.quantity, Number(before[1]) + item.quantity]]);
    });
    projection = fifo3dpAppendNegativeProjection_(spreadsheet, original, reversalId, returnDate, reason, actor);
    const negativeBreakdown = plan.restores.map(function (item) { return { batch_id: item.batch_id, row: item.row, quantity: -item.quantity, cost_uah: -item.cost_uah }; });
    allocations.getRange(allocationRow, 1, 1, CATALOG_FIFO_3DP.allocationHeaders.length).setValues([[
      reversalId, operationId, Utilities.formatDate(new Date(), API_3DP.timezone, "yyyy-MM-dd'T'HH:mm:ssXXX"),
      'fifo_reversal', String(original.values[4] || ''), String(original.values[5] || ''), -plan.quantity,
      -plan.total_cost_uah, JSON.stringify(negativeBreakdown), 'restore', actor.role, fingerprint, 'committed',
      String(original.values[0]), projection.row, Number(original.values[15] || 0),
    ]]);
    appendAudit3dp_(spreadsheet, actor, 'FIFO_ALLOCATION_REVERSED', CATALOG_FIFO_3DP.allocationsSheet,
      'A' + allocationRow + ':P' + allocationRow, {}, { reversal_id: reversalId, reversal_of: String(original.values[0]), restored_quantity: plan.quantity, restored_cost_uah: plan.total_cost_uah }, 'operation_id=' + operationId + '; reason=' + reason);
  } catch (error) {
    snapshots.forEach(function (snapshot) { batchSheet.getRange(snapshot.row, 19, 1, 2).setValues([snapshot.values]); });
    allocations.getRange(allocationRow, 1, 1, CATALOG_FIFO_3DP.allocationHeaders.length).clearContent();
    if (projection) restoreRange3dp_(projection.snapshot);
    throw error;
  }
  return { action: '3dp_fifo_reverse', already_applied: false, operation_id: operationId, reversal_id: reversalId,
    reversal_of: String(original.values[0]), restored_quantity: plan.quantity, restored_cost_uah: plan.total_cost_uah,
    projection_sheet: projection.sheet.getName(), projection_row: projection.row };
}

function fifo3dpReconcileLedger_(batches, allocations, projectionQuantity) {
  const problems = [];
  const batchById = {};
  const allocationById = {};
  const expectedAllocated = {};
  (batches || []).forEach(function (batch) { batchById[String(batch.batch_id || '')] = batch; });
  (allocations || []).forEach(function (allocation) { allocationById[String(allocation.allocation_id || '')] = allocation; });
  (allocations || []).filter(function (allocation) { return allocation.status === 'committed'; }).forEach(function (allocation) {
    let breakdown;
    try { breakdown = JSON.parse(String(allocation.breakdown_json || '[]')); }
    catch (error) { problems.push({ code: 'FIFO_BREAKDOWN_INVALID_JSON', allocation_id: allocation.allocation_id }); return; }
    if (!Array.isArray(breakdown) || !breakdown.length) { problems.push({ code: 'FIFO_BREAKDOWN_EMPTY', allocation_id: allocation.allocation_id }); return; }
    let quantity = 0;
    let cost = 0;
    breakdown.forEach(function (entry) {
      const batch = batchById[String(entry.batch_id || '')];
      if (!batch || Number(batch.row) !== Number(entry.row)) { problems.push({ code: 'FIFO_BATCH_REFERENCE_MISSING', allocation_id: allocation.allocation_id, batch_id: String(entry.batch_id || '') }); return; }
      const entryQuantity = Number(entry.quantity);
      const entryCost = Number(entry.cost_uah);
      if (!Number.isFinite(entryQuantity) || !Number.isFinite(entryCost)) { problems.push({ code: 'FIFO_BREAKDOWN_VALUE_INVALID', allocation_id: allocation.allocation_id }); return; }
      quantity += entryQuantity;
      cost = fifo3dpRound_(cost + entryCost, 6);
      expectedAllocated[batch.batch_id] = fifo3dpRound_(Number(expectedAllocated[batch.batch_id] || 0) + entryQuantity, 6);
    });
    if (Math.abs(quantity - Number(allocation.quantity)) > 0.000001 || Math.abs(cost - Number(allocation.total_cost_uah)) > 0.000001) problems.push({ code: 'FIFO_ALLOCATION_TOTAL_MISMATCH', allocation_id: allocation.allocation_id });
    let sourceType = allocation.source_type;
    if (sourceType === 'fifo_reversal') {
      const original = allocationById[String(allocation.reversal_of || '')];
      sourceType = original && original.source_type;
    }
    const projected = projectionQuantity(sourceType, Number(allocation.projection_row), allocation);
    if (projected == null) problems.push({ code: 'FIFO_PROJECTION_MISSING', allocation_id: allocation.allocation_id, expected: Number(allocation.quantity), actual: null });
    else if (Math.abs(Number(projected) - Number(allocation.quantity)) > 0.000001) problems.push({ code: 'FIFO_PROJECTION_QUANTITY_MISMATCH', allocation_id: allocation.allocation_id, expected: Number(allocation.quantity), actual: Number(projected) });
  });
  const batchRepairs = [];
  (batches || []).forEach(function (batch) {
    const expected = Number(expectedAllocated[batch.batch_id] || 0);
    const expectedAvailable = Number(batch.good_qty) - expected;
    if (Math.abs(Number(batch.allocated_qty) - expected) > 0.000001) problems.push({ code: 'FIFO_BATCH_ALLOCATED_MISMATCH', batch_id: batch.batch_id, expected: expected, actual: Number(batch.allocated_qty) });
    if (Math.abs(Number(batch.available_qty) - expectedAvailable) > 0.000001) problems.push({ code: 'FIFO_BATCH_AVAILABLE_MISMATCH', batch_id: batch.batch_id, expected: expectedAvailable, actual: Number(batch.available_qty) });
    if ((Math.abs(Number(batch.allocated_qty) - expected) > 0.000001 || Math.abs(Number(batch.available_qty) - expectedAvailable) > 0.000001) && expected >= 0 && expectedAvailable >= 0) batchRepairs.push({ row: batch.row, batch_id: batch.batch_id, allocated_qty: expected, available_qty: expectedAvailable });
  });
  return { clean: problems.length === 0, problems: problems.slice(0, 50), problem_count: problems.length, batch_repairs: batchRepairs };
}

function fifo3dpReconcileAction_(spreadsheet, actor) {
  assertOwner3dp_(actor, 'Only the owner may reconcile the FIFO ledger.');
  const batchSheet = spreadsheet.getSheetByName(CATALOG_FIFO_3DP.batchesSheet);
  const allocationSheet = spreadsheet.getSheetByName(CATALOG_FIFO_3DP.allocationsSheet);
  if (!batchSheet || !allocationSheet) throw apiError3dp_('FIFO_SCHEMA_NOT_READY', 'Run setup3dpCatalogFifoSchema first.');
  fifo3dpAssertHeaders_(batchSheet, CATALOG_FIFO_3DP.batchHeaders, 'FIFO_SCHEMA_MISMATCH');
  fifo3dpAssertHeaders_(allocationSheet, CATALOG_FIFO_3DP.allocationHeaders, 'FIFO_SCHEMA_MISMATCH');
  const batchValues = batchSheet.getLastRow() < 2 ? [] : batchSheet.getRange(2, 1, batchSheet.getLastRow() - 1, CATALOG_FIFO_3DP.batchHeaders.length).getValues();
  const allocationValues = allocationSheet.getLastRow() < 2 ? [] : allocationSheet.getRange(2, 1, allocationSheet.getLastRow() - 1, CATALOG_FIFO_3DP.allocationHeaders.length).getValues();
  const batches = batchValues.map(function (row, index) { return { row: index + 2, batch_id: String(row[0] || ''), good_qty: Number(row[8]), allocated_qty: Number(row[18]), available_qty: Number(row[19]) }; }).filter(function (row) { return row.batch_id; });
  const allocations = allocationValues.filter(function (row) { return String(row[0] || ''); }).map(function (row) { return {
    allocation_id: String(row[0]), operation_id: String(row[1]), source_type: String(row[3]), sku: String(row[5]), quantity: Number(row[6]),
    total_cost_uah: Number(row[7]), breakdown_json: String(row[8]), status: String(row[12]), reversal_of: String(row[13]), projection_row: Number(row[14]),
  }; });
  const sales = getSheet3dp_(spreadsheet, SHEETS_3DP.sales);
  const gifts = getSheet3dp_(spreadsheet, SHEETS_3DP.plyushky);
  const result = fifo3dpReconcileLedger_(batches, allocations, function (sourceType, row, allocation) {
    if (!(row >= 2)) return null;
    if (sourceType === 'crm_sale' || sourceType === 'marketing_writeoff') return sales.getRange(row, 4).getValue();
    if (sourceType === 'marketing_gift') {
      const projection = gifts.getRange(row, 2, 1, 7).getValues()[0];
      if (String(projection[0] || '') !== allocation.sku ||
          String(projection[6] || '').indexOf('[fifo_allocation:' + allocation.allocation_id + ']') === -1) return null;
      return projection[4];
    }
    if (sourceType === 'crm_gift') return gifts.getRange(row, 6).getValue();
    return null;
  });
  const fingerprint = fifo3dpFingerprint_({ batches: batches, allocations: allocations, problems: result.problems });
  return { action: '3dp_fifo_reconcile', clean: result.clean, problems: result.problems, problem_count: result.problem_count,
    repairable_batches: result.batch_repairs, fingerprint: fingerprint, batches_checked: batches.length,
    allocations_checked: allocations.filter(function (row) { return row.status === 'committed'; }).length };
}

function fifo3dpRepairAction_(spreadsheet, body, actor) {
  assertOwner3dp_(actor, 'Only the owner may repair FIFO batch counters.');
  const requestId = String(body.request_id || '').trim();
  const expectedFingerprint = String(body.expected_fingerprint || '').trim();
  const reason = String(body.reason || '').trim();
  if (!/^[A-Za-z0-9_-]{8,80}$/.test(requestId)) throw apiError3dp_('REQUEST_ID_REQUIRED', 'A stable repair request_id is required.');
  if (!/^[a-f0-9]{64}$/.test(expectedFingerprint)) throw apiError3dp_('EXPECTED_FINGERPRINT_REQUIRED', 'Use the fingerprint returned by 3dp_fifo_reconcile.');
  if (!reason || reason.length > 220) throw apiError3dp_('REPAIR_REASON_REQUIRED', 'A repair reason up to 220 characters is required.');
  assertManualValue3dp_(reason);
  const before = fifo3dpReconcileAction_(spreadsheet, actor);
  if (before.clean) return { action: '3dp_fifo_repair', already_applied: true, request_id: requestId, changed_batches: 0, reconciliation: before };
  if (before.fingerprint !== expectedFingerprint) throw apiError3dp_('STALE_RECONCILIATION', 'FIFO state changed after preview; run 3dp_fifo_reconcile again.');
  const repairableCodes = { FIFO_BATCH_ALLOCATED_MISMATCH: true, FIFO_BATCH_AVAILABLE_MISMATCH: true };
  const blockers = before.problems.filter(function (problem) { return !repairableCodes[problem.code]; });
  if (blockers.length) throw apiError3dp_('FIFO_REPAIR_BLOCKED', 'Missing or inconsistent allocations/business rows require scoped recovery; batch counters were not changed.');
  if (!before.repairable_batches.length) throw apiError3dp_('FIFO_REPAIR_NOT_SAFE', 'No safe batch-counter repair is available.');
  const batches = spreadsheet.getSheetByName(CATALOG_FIFO_3DP.batchesSheet);
  const snapshots = before.repairable_batches.map(function (repair) { return { row: repair.row, values: batches.getRange(repair.row, 19, 1, 2).getValues()[0] }; });
  try {
    before.repairable_batches.forEach(function (repair) { batches.getRange(repair.row, 19, 1, 2).setValues([[repair.allocated_qty, repair.available_qty]]); });
    const after = fifo3dpReconcileAction_(spreadsheet, actor);
    if (!after.clean) throw apiError3dp_('FIFO_REPAIR_VERIFICATION_FAILED', 'FIFO reconciliation is still dirty after batch-counter repair.');
    appendAudit3dp_(spreadsheet, actor, 'FIFO_BATCH_COUNTERS_REPAIRED', CATALOG_FIFO_3DP.batchesSheet,
      snapshots.map(function (snapshot) { return 'S' + snapshot.row + ':T' + snapshot.row; }).join(','),
      snapshots.map(function (snapshot) { return { row: snapshot.row, allocated_qty: snapshot.values[0], available_qty: snapshot.values[1] }; }),
      before.repairable_batches, 'request_id=' + requestId + '; reason=' + reason);
    return { action: '3dp_fifo_repair', already_applied: false, request_id: requestId, changed_batches: before.repairable_batches.length, reconciliation: after };
  } catch (error) {
    snapshots.forEach(function (snapshot) { batches.getRange(snapshot.row, 19, 1, 2).setValues([snapshot.values]); });
    throw error;
  }
}

function fifo3dpRequiredNumber_(value, code, allowZero) {
  const number = Number(value);
  if (!Number.isFinite(number) || (allowZero ? number < 0 : number <= 0)) throw apiError3dp_(code, code);
  return number;
}

function fifo3dpManufactureBatchAction_(spreadsheet, body, actor) {
  assertPrintLogRole3dp_(actor);
  const sku = requiredSku3dp_(body.sku);
  const nomenclature = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
  const nomenclatureRow = resolveTargetRow3dp_(nomenclature, sku);
  assertNomenclatureActiveForOperation3dp_(nomenclature, nomenclatureRow, 'ROW_ARCHIVED', 'Archived SKU cannot receive a manufactured batch.', 'Only active SKU can receive a manufactured batch.');
  const quantity = inventoryWholeNumber3dp_(body.quantity, 'quantity must be a non-negative whole number.');
  const defects = inventoryWholeNumber3dp_(body.defects == null ? 0 : body.defects, 'defects must be a non-negative whole number.');
  if (quantity < 1 || defects > quantity) throw apiError3dp_('INVALID_QUANTITY', 'Printed quantity and defects are invalid.');
  const printTime = parsePrintTime3dp_(body.total_print_time_h);
  if (!printTime.ok || printTime.blank) throw apiError3dp_('INVALID_PRINT_TIME', printTime.error || 'total_print_time_h is required.');
  const requestId = String(body.request_id || '').trim();
  if (!/^[A-Za-z0-9_-]{8,80}$/.test(requestId)) throw apiError3dp_('REQUEST_ID_REQUIRED', 'A stable request_id is required.');
  const printedBy = String(body.printed_by || (actor.role === 'serhiy' ? 'Сергій' : 'власник')).trim();
  if (['Сергій', 'власник'].indexOf(printedBy) === -1) throw apiError3dp_('INVALID_PRINTER', 'printed_by must be Сергій or власник.');
  const note = String(body.note || '').trim();
  assertManualValue3dp_(note);
  if (note.length > 220) throw apiError3dp_('NOTE_TOO_LONG', 'Manufacturing note exceeds 220 characters.');

  const settings = getSheet3dp_(spreadsheet, SHEETS_3DP.settings).getRange('B2:B4').getValues().map(function (row) { return Number(row[0]); });
  const costInput = {
    actual_weight_g: fifo3dpRequiredNumber_(body.total_weight_g, 'INVALID_MATERIAL', true),
    actual_time_h: printTime.hours,
    spool_weight_g: fifo3dpRequiredNumber_(body.spool_weight_g, 'SPOOL_WEIGHT_REQUIRED', false),
    spool_price_uah: fifo3dpRequiredNumber_(body.spool_price_uah, 'SPOOL_PRICE_REQUIRED', false),
    printer_power_kw: settings[0], electricity_uah_kwh: settings[1], depreciation_uah_h: settings[2],
    serhiy_consumables_uah: fifo3dpRequiredNumber_(body.serhiy_consumables_uah == null ? 0 : body.serhiy_consumables_uah, 'INVALID_SERHIY_CONSUMABLES', true),
  };
  const cost = fifo3dpCalculateBatchCost_(costInput);
  const fingerprintPayload = { sku: sku, quantity: quantity, defects: defects, cost_input: costInput, printed_by: printedBy, note: note };
  const fingerprint = fifo3dpFingerprint_(fingerprintPayload);
  const batchesState = fifo3dpEnsureHiddenSheet_(spreadsheet, CATALOG_FIFO_3DP.batchesSheet, CATALOG_FIFO_3DP.batchHeaders);
  const batches = batchesState.sheet;
  if (batches.getLastRow() >= 2) {
    const rows = batches.getRange(2, 1, batches.getLastRow() - 1, CATALOG_FIFO_3DP.batchHeaders.length).getValues();
    for (let index = rows.length - 1; index >= 0; index -= 1) {
      if (String(rows[index][1] || '') !== requestId) continue;
      if (String(rows[index][22] || '') !== fingerprint) throw apiError3dp_('IDEMPOTENCY_CONFLICT', 'request_id was already used with a different batch payload.');
      return { action: '3dp_manufacture_batch', row: Number(rows[index][2]), batch_id: String(rows[index][0]), already_applied: true, request_id: requestId, actual_total_cost_uah: Number(rows[index][17]) };
    }
  }

  const printLog = getSheet3dp_(spreadsheet, SHEETS_3DP.printLog);
  const printRow = printLog.getLastRow() + 1;
  const batchRow = batches.getLastRow() + 1;
  const now = new Date();
  const date = Utilities.formatDate(now, API_3DP.timezone, 'yyyy-MM-dd');
  const timestamp = Utilities.formatDate(now, API_3DP.timezone, "yyyy-MM-dd'T'HH:mm:ssXXX");
  const batchId = '3DP-B-' + Utilities.formatDate(now, API_3DP.timezone, 'yyyyMMddHHmmss') + '-' + requestId.slice(-8);
  const good = quantity - defects;
  const batchValues = [batchId, requestId, printRow, timestamp, date, sku, quantity, defects, good,
    costInput.actual_weight_g, costInput.actual_time_h, costInput.spool_weight_g, costInput.spool_price_uah,
    costInput.printer_power_kw, costInput.electricity_uah_kwh, costInput.depreciation_uah_h,
    costInput.serhiy_consumables_uah, cost.total_uah, 0, good, good ? 'active' : 'loss', actor.role, fingerprint,
    '', ''];
  const batchRange = batches.getRange(batchRow, 1, 1, batchValues.length);
  try {
    batchRange.setValues([batchValues]);
    batches.getRange(batchRow, 24).setFormula('=IF(I' + batchRow + '=0;0;R' + batchRow + '/I' + batchRow + ')');
    batches.getRange(batchRow, 25).setFormula('=X' + batchRow + '*T' + batchRow);
    const appended = appendRowAction3dp_(spreadsheet, { sheet: SHEETS_3DP.printLog, values: {
      A: date, B: sku, C: quantity, D: printTime.hours, E: defects, F: costInput.actual_weight_g,
      H: printedBy, I: [note, '[fifo_batch:' + batchId + ']', '[dashboard_request:' + requestId + ']'].filter(Boolean).join(' '),
    } }, actor);
    if (appended.row !== printRow) throw apiError3dp_('PRINT_LOG_ROW_CHANGED', 'Print-log target row changed during the locked operation.');
    printLog.getRange(printRow, 7).setFormula('=IF(B' + printRow + '="";"";IFERROR(INDEX(\'' + CATALOG_FIFO_3DP.batchesSheet + '\'!$R:$R;MATCH(ROW();\'' + CATALOG_FIFO_3DP.batchesSheet + '\'!$C:$C;0));0))');
    appendAudit3dp_(spreadsheet, actor, 'FIFO_BATCH_CREATED', CATALOG_FIFO_3DP.batchesSheet, 'A' + batchRow + ':Y' + batchRow, {}, { batch_id: batchId, sku: sku, good_qty: good, total_cost_uah: cost.total_uah }, 'request_id=' + requestId);
  } catch (error) {
    batchRange.clearContent();
    if (printLog.getLastRow() >= printRow) printLog.getRange(printRow, 1, 1, printLog.getLastColumn()).clearContent();
    throw error;
  }
  return { action: '3dp_manufacture_batch', row: printRow, batch_id: batchId, already_applied: false, request_id: requestId,
    printed: quantity, defects: defects, stock_added: good, actual_total_cost_uah: cost.total_uah, cost_components: cost };
}

function fifo3dpCrmSaleCommitAction_(spreadsheet, body, actor) {
  assertOwner3dp_(actor, 'Only the owner/CRM token may commit a FIFO sale.');
  const operationId = String(body.operation_id || '').trim();
  if (!/^[A-Za-z0-9:_-]{8,120}$/.test(operationId)) throw apiError3dp_('OPERATION_ID_REQUIRED', 'A stable operation_id is required.');
  const sku = requiredSku3dp_(body.sku);
  const quantity = inventoryWholeNumber3dp_(body.quantity, 'quantity must be a positive whole number.');
  if (quantity < 1) throw apiError3dp_('INVALID_QUANTITY', 'quantity must be a positive whole number.');
  const canonical = {
    operation_id: operationId, sku: sku, quantity: quantity, source_ref: String(body.order || ''), crm_row: Number(body.crm_row),
    mode: String(body.mode || 'Продаж'), sale_date: String(body.sale_date || ''), sale_unit_price: Number(body.sale_unit_price || 0),
    packaging_unit: Number(body.packaging_unit || 0), profit_share: Number(body.profit_share), actual_rrp: Number(body.actual_rrp),
    fixture_cost: Number(body.fixture_cost || 0), fixture_payer: String(body.fixture_payer || ''),
    owner_fixture_per_unit: Number(body.owner_fixture_per_unit || 0), serhiy_fixture_per_unit: Number(body.serhiy_fixture_per_unit || 0),
    buyout: Number(body.buyout || 0), channel: String(body.channel || ''), note: String(body.note || ''),
  };
  const fingerprint = fifo3dpFingerprint_(canonical);
  const allocationState = fifo3dpEnsureHiddenSheet_(spreadsheet, CATALOG_FIFO_3DP.allocationsSheet, CATALOG_FIFO_3DP.allocationHeaders);
  const allocationsSheet = allocationState.sheet;
  const existing = fifo3dpFindOperation_(allocationsSheet, operationId);
  if (existing) {
    if (fifo3dpFindReversal_(allocationsSheet, String(existing.values[0]))) throw apiError3dp_('FIFO_ALLOCATION_REVERSED', 'This sale allocation was reversed and cannot be reactivated; create a new sale operation.');
    return fifo3dpReplayResult_(existing, fingerprint);
  }
  const plan = fifo3dpPlanAllocation_(fifo3dpBatchRows_(spreadsheet, sku), quantity);
  const sales = getSheet3dp_(spreadsheet, SHEETS_3DP.sales);
  const saleRow = sales.getLastRow() + 1;
  const allocationRow = allocationsSheet.getLastRow() + 1;
  const now = Utilities.formatDate(new Date(), API_3DP.timezone, "yyyy-MM-dd'T'HH:mm:ssXXX");
  const allocationId = '3DP-A-' + operationId.replace(/[^A-Za-z0-9_-]/g, '-').slice(0, 70);
  const batchSheet = spreadsheet.getSheetByName(CATALOG_FIFO_3DP.batchesSheet);
  const batchSnapshots = plan.allocations.map(function (item) {
    return { row: item.row, values: batchSheet.getRange(item.row, 19, 1, 2).getValues()[0] };
  });
  let saleAppended = false;
  try {
    plan.allocations.forEach(function (item) {
      const range = batchSheet.getRange(item.row, 19, 1, 2);
      const before = range.getValues()[0];
      range.setValues([[Number(before[0] || 0) + item.quantity, Number(before[1] || 0) - item.quantity]]);
    });
    const appended = appendRowAction3dp_(spreadsheet, { sheet: SHEETS_3DP.sales, internal_fifo_marker: INTERNAL_FIFO_APPEND_MARKER_3DP, values: {
      A: canonical.sale_date, B: sku, D: quantity, E: canonical.sale_unit_price, F: plan.unit_cost_uah,
      G: canonical.packaging_unit, H: canonical.profit_share, M: canonical.channel, N: canonical.source_ref,
      T: canonical.crm_row, U: canonical.actual_rrp, V: canonical.fixture_cost, W: canonical.fixture_payer,
      X: canonical.mode, Y: canonical.owner_fixture_per_unit, Z: canonical.serhiy_fixture_per_unit, AA: canonical.buyout,
    } }, actor);
    saleAppended = true;
    if (appended.row !== saleRow) throw apiError3dp_('SALE_ROW_CHANGED', 'Sales target row changed during the locked operation.');
    allocationsSheet.getRange(allocationRow, 1, 1, CATALOG_FIFO_3DP.allocationHeaders.length).setValues([[
      allocationId, operationId, now, 'crm_sale', canonical.source_ref, sku, quantity, plan.total_cost_uah,
      JSON.stringify(plan.allocations), 'consume', actor.role, fingerprint, 'committed', '', saleRow, canonical.crm_row,
    ]]);
    appendAudit3dp_(spreadsheet, actor, 'FIFO_SALE_COMMITTED', CATALOG_FIFO_3DP.allocationsSheet,
      'A' + allocationRow + ':P' + allocationRow, {}, { allocation_id: allocationId, sku: sku, qty: quantity, total_cost_uah: plan.total_cost_uah }, 'operation_id=' + operationId);
  } catch (error) {
    batchSnapshots.forEach(function (snapshot) { batchSheet.getRange(snapshot.row, 19, 1, 2).setValues([snapshot.values]); });
    allocationsSheet.getRange(allocationRow, 1, 1, CATALOG_FIFO_3DP.allocationHeaders.length).clearContent();
    if (saleAppended) sales.getRange(saleRow, 1, 1, sales.getLastColumn()).clearContent();
    throw error;
  }
  return { action: '3dp_crm_sale_commit', already_applied: false, operation_id: operationId, allocation_id: allocationId,
    quantity: quantity, total_cost_uah: plan.total_cost_uah, unit_cost_uah: plan.unit_cost_uah,
    allocations: plan.allocations, sale_row: saleRow, crm_row: canonical.crm_row };
}

function fifo3dpMarketingWriteoffAction_(spreadsheet, body, actor) {
  assertOwner3dp_(actor, 'Only the owner/CRM token may commit a marketing writeoff.');
  const operationId = String(body.operation_id || '').trim();
  if (!/^[A-Za-z0-9:_-]{8,120}$/.test(operationId)) throw apiError3dp_('OPERATION_ID_REQUIRED', 'A stable operation_id is required.');
  const sku = requiredSku3dp_(body.sku);
  const quantity = inventoryWholeNumber3dp_(body.quantity, 'quantity must be a positive whole number.');
  if (quantity < 1) throw apiError3dp_('INVALID_QUANTITY', 'quantity must be a positive whole number.');
  const date = String(body.date || '').trim().slice(0, 10);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) throw apiError3dp_('DATE_REQUIRED', 'Marketing date must use YYYY-MM-DD.');
  const note = String(body.note || '').trim();
  if (!note || note.length > 220) throw apiError3dp_('NOTE_REQUIRED', 'A marketing reason up to 220 characters is required.');
  assertManualValue3dp_(note);
  const ownerFixturePerUnit = fifo3dpRequiredNumber_(body.owner_fixture_per_unit == null ? 0 : body.owner_fixture_per_unit, 'INVALID_OWNER_FIXTURE', true);
  const serhiyFixturePerUnit = fifo3dpRequiredNumber_(body.serhiy_fixture_per_unit == null ? 0 : body.serhiy_fixture_per_unit, 'INVALID_SERHIY_FIXTURE', true);
  assertMarketingPayoutSchema3dp_(spreadsheet);
  const canonical = { operation_id: operationId, sku: sku, quantity: quantity, date: date, note: note,
    owner_fixture_per_unit: ownerFixturePerUnit, serhiy_fixture_per_unit: serhiyFixturePerUnit };
  const fingerprint = fifo3dpFingerprint_(canonical);
  const allocationState = fifo3dpEnsureHiddenSheet_(spreadsheet, CATALOG_FIFO_3DP.allocationsSheet, CATALOG_FIFO_3DP.allocationHeaders);
  const allocationsSheet = allocationState.sheet;
  const existing = fifo3dpFindOperation_(allocationsSheet, operationId);
  if (existing) {
    if (fifo3dpFindReversal_(allocationsSheet, String(existing.values[0]))) throw apiError3dp_('FIFO_ALLOCATION_REVERSED', 'This marketing allocation was reversed; create a new operation.');
    const replay = fifo3dpReplayResult_(existing, fingerprint);
    const sourceType = String(existing.values[3] || '');
    let buyoutReplay;
    if (sourceType === 'marketing_gift') {
      const giftsReplay = getSheet3dp_(spreadsheet, SHEETS_3DP.plyushky);
      const projection = giftsReplay.getRange(replay.gift_row, 1, 1, 9).getValues()[0];
      if (String(projection[1] || '') !== sku || Number(projection[2]) !== quantity ||
          Number(projection[5]) !== quantity || isBlank3dp_(projection[3]) ||
          !Number.isFinite(Number(projection[3])) || Number(projection[3]) < 0 ||
          String(projection[7] || '').indexOf('[fifo_allocation:' + replay.allocation_id + ']') === -1 ||
          Number(projection[8] || 0) !== fifo3dpRound_(quantity * serhiyFixturePerUnit, 2)) {
        throw apiError3dp_('MARKETING_PROJECTION_MISMATCH', 'Committed marketing gift projection changed; do not retry with a new ID.');
      }
      buyoutReplay = Number(projection[3]);
    } else if (sourceType === 'marketing_writeoff') {
      const salesReplay = getSheet3dp_(spreadsheet, SHEETS_3DP.sales);
      const projection = salesReplay.getRange(replay.sale_row, 1, 1, 27).getValues()[0];
      if (String(projection[1] || '') !== sku || Number(projection[3]) !== quantity ||
          String(projection[23] || '') !== 'Маркетинг') {
        throw apiError3dp_('LEGACY_MARKETING_PROJECTION_MISSING', 'Legacy marketing sale was removed; reclassify its FIFO allocation before retrying.');
      }
      buyoutReplay = Number(projection[26]);
    } else {
      throw apiError3dp_('MARKETING_OPERATION_TYPE_MISMATCH', 'operation_id belongs to a different FIFO source.');
    }
    replay.buyout_unit = buyoutReplay;
    replay.serhiy_accrual = fifo3dpRound_(quantity * (buyoutReplay + serhiyFixturePerUnit), 2);
    replay.marketing_expense = fifo3dpRound_(quantity * (buyoutReplay + ownerFixturePerUnit + serhiyFixturePerUnit), 2);
    return replay;
  }

  const fifoIntegrity = fifo3dpReconcileAction_(spreadsheet, actor);
  if (!fifoIntegrity.clean) {
    throw apiError3dp_('FIFO_RECONCILIATION_REQUIRED', 'Repair the existing FIFO projection before another marketing writeoff.');
  }

  const nomenclature = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
  const nomenclatureRow = resolveTargetRow3dp_(nomenclature, sku);
  assertNomenclatureActiveForOperation3dp_(nomenclature, nomenclatureRow, 'SKU_ARCHIVED', 'Archived SKU cannot be written off.', 'Only active SKU can be written off.');
  const buyoutRaw = nomenclature.getRange(nomenclatureRow, columnToNumber3dp_('R')).getValue();
  if (isBlank3dp_(buyoutRaw) || number3dp_(buyoutRaw) < 0) throw apiError3dp_('BUYOUT_NOT_FOUND', 'Valid buyout price is required for SKU ' + sku + '.');
  const buyout = number3dp_(buyoutRaw);
  const plan = fifo3dpPlanAllocation_(fifo3dpBatchRows_(spreadsheet, sku), quantity);
  const gifts = getSheet3dp_(spreadsheet, SHEETS_3DP.plyushky);
  const giftRow = findFirstBusinessEmptyRow3dp_(gifts, SHEETS_3DP.plyushky, actor);
  const giftSnapshot = snapshotRange3dp_(gifts, 'A' + giftRow + ':I' + giftRow);
  if (!isBlank3dp_(gifts.getRange(giftRow, 9).getValue()) || gifts.getRange(giftRow, 9).getFormula()) {
    throw apiError3dp_('GIFT_FIXTURE_CELL_CONFLICT', 'The target gift fixture-accrual cell is occupied.');
  }
  const purchaseFormula = '=IF(OR(C' + giftRow + '="";D' + giftRow + '="");"";C' + giftRow + '*D' + giftRow + ')';
  const purchaseCell = gifts.getRange(giftRow, 5);
  if ((!purchaseCell.getFormula() && !isBlank3dp_(purchaseCell.getValue())) ||
      (purchaseCell.getFormula() && canonicalFormula3dp_(purchaseCell.getFormula()) !== canonicalFormula3dp_(purchaseFormula))) {
    throw apiError3dp_('GIFT_PURCHASE_FORMULA_CONFLICT', 'The target gift purchase-sum cell is not a blank or the expected row formula.');
  }
  const allocationRow = allocationsSheet.getLastRow() + 1;
  const now = Utilities.formatDate(new Date(), API_3DP.timezone, "yyyy-MM-dd'T'HH:mm:ssXXX");
  const allocationId = '3DP-A-' + operationId.replace(/[^A-Za-z0-9_-]/g, '-').slice(0, 70);
  const batchSheet = spreadsheet.getSheetByName(CATALOG_FIFO_3DP.batchesSheet);
  const batchSnapshots = plan.allocations.map(function(item) { return { row: item.row, values: batchSheet.getRange(item.row, 19, 1, 2).getValues()[0] }; });
  try {
    plan.allocations.forEach(function(item) {
      const range = batchSheet.getRange(item.row, 19, 1, 2);
      const before = range.getValues()[0];
      range.setValues([[Number(before[0] || 0) + item.quantity, Number(before[1] || 0) - item.quantity]]);
    });
    copyFormulaCells3dp_(gifts, SHEETS_3DP.plyushky, giftRow);
    purchaseCell.setFormula(purchaseFormula);
    const values = {
      A: new Date(date + 'T12:00:00Z'), B: sku, C: quantity, D: buyout, F: quantity,
      H: ['[crm015_marketing:' + operationId + ']', note, '[fifo_allocation:' + allocationId + ']'].join(' '),
      I: fifo3dpRound_(quantity * serhiyFixturePerUnit, 2),
    };
    Object.keys(values).forEach(function(column) { gifts.getRange(giftRow, columnToNumber3dp_(column)).setValue(values[column]); });
    gifts.getRange(giftRow, 1).setNumberFormat('yyyy-mm-dd');
    allocationsSheet.getRange(allocationRow, 1, 1, CATALOG_FIFO_3DP.allocationHeaders.length).setValues([[
      allocationId, operationId, now, 'marketing_gift', 'CRM-015/' + operationId, sku, quantity, plan.total_cost_uah,
      JSON.stringify(plan.allocations), 'consume', actor.role, fingerprint, 'committed', '', giftRow, '',
    ]]);
    appendAudit3dp_(spreadsheet, actor, 'FIFO_MARKETING_WRITEOFF_COMMITTED', CATALOG_FIFO_3DP.allocationsSheet,
      'A' + allocationRow + ':P' + allocationRow, {}, { allocation_id: allocationId, sku: sku, qty: quantity,
        fifo_cost_uah: plan.total_cost_uah, buyout_uah: buyout * quantity,
        owner_fixture_uah: ownerFixturePerUnit * quantity, serhiy_fixture_uah: serhiyFixturePerUnit * quantity },
      'operation_id=' + operationId);
  } catch (error) {
    batchSnapshots.forEach(function(snapshot) { batchSheet.getRange(snapshot.row, 19, 1, 2).setValues([snapshot.values]); });
    allocationsSheet.getRange(allocationRow, 1, 1, CATALOG_FIFO_3DP.allocationHeaders.length).clearContent();
    restoreRange3dp_(giftSnapshot);
    throw error;
  }
  return { action: '3dp_marketing_writeoff', already_applied: false, operation_id: operationId, allocation_id: allocationId,
    quantity: quantity, total_cost_uah: plan.total_cost_uah, unit_cost_uah: plan.unit_cost_uah, allocations: plan.allocations,
    gift_row: giftRow, buyout_unit: buyout,
    serhiy_accrual: fifo3dpRound_(quantity * (buyout + serhiyFixturePerUnit), 2),
    marketing_expense: fifo3dpRound_(quantity * (buyout + ownerFixturePerUnit + serhiyFixturePerUnit), 2) };
}

function fifo3dpOrderGiftsAppendAction_(spreadsheet, body, actor) {
  assertOwner3dp_(actor, 'Caller may not append CRM order gifts.');
  const requestId = String(body.request_id || '').trim();
  if (!/^[A-Za-z0-9_-]{8,80}$/.test(requestId)) throw apiError3dp_('REQUEST_ID_REQUIRED', 'A stable CRM request_id is required.');
  const order = String(body.order || '').trim();
  const date = String(body.date || '').trim().slice(0, 10);
  if (!order) throw apiError3dp_('ORDER_REQUIRED', 'CRM order number is required.');
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) throw apiError3dp_('DATE_REQUIRED', 'Gift date must use YYYY-MM-DD.');
  const rawItems = Array.isArray(body.items) ? body.items.slice(0, 10) : [];
  if (!rawItems.length) throw apiError3dp_('ITEMS_REQUIRED', 'At least one 3D gift is required.');
  const nomenclature = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
  const allocationSheet = fifo3dpEnsureHiddenSheet_(spreadsheet, CATALOG_FIFO_3DP.allocationsSheet, CATALOG_FIFO_3DP.allocationHeaders).sheet;
  const items = rawItems.map(function (raw, index) {
    const sku = requiredSku3dp_(raw && raw.sku);
    const qty = inventoryWholeNumber3dp_(raw && raw.qty, 'Gift quantity must be a positive whole number.');
    if (qty < 1) throw apiError3dp_('INVALID_QUANTITY', 'Gift quantity must be a positive whole number.');
    const nomenclatureRow = resolveTargetRow3dp_(nomenclature, sku);
    assertNomenclatureActiveForOperation3dp_(nomenclature, nomenclatureRow, 'SKU_ARCHIVED', 'Archived SKU cannot be issued as a gift: ' + sku + '.', 'Only active SKU can be issued as a gift: ' + sku + '.');
    const buyoutRaw = nomenclature.getRange(nomenclatureRow, columnToNumber3dp_('R')).getValue();
    if (isBlank3dp_(buyoutRaw) || number3dp_(buyoutRaw) < 0) throw apiError3dp_('BUYOUT_NOT_FOUND', 'Valid buyout price is required for SKU ' + sku + '.');
    const note = String(raw && raw.note || '').trim();
    const operationId = 'gift:' + requestId + ':' + index;
    const fingerprint = fifo3dpFingerprint_({ operation_id: operationId, sku: sku, qty: qty, order: order, date: date, note: note });
    return { sku: sku, qty: qty, buyout: number3dp_(buyoutRaw), note: note, operation_id: operationId, fingerprint: fingerprint, marker: '[crm_component:' + requestId + ':' + index + ']' };
  });
  const existing = items.map(function (item) { return fifo3dpFindOperation_(allocationSheet, item.operation_id); });
  if (existing.every(Boolean)) {
    existing.forEach(function (entry, index) { fifo3dpReplayResult_(entry, items[index].fingerprint); });
    return { action: '3dp_order_gifts_append', order: order, rows_added: 0, already_applied: true };
  }
  if (existing.some(Boolean)) throw apiError3dp_('PARTIAL_IDEMPOTENT_STATE', 'Only part of this gift request is present; reconcile it before retrying.');

  const virtualBySku = {};
  const plans = items.map(function (item) {
    const layers = virtualBySku[item.sku] || (virtualBySku[item.sku] = fifo3dpBatchRows_(spreadsheet, item.sku).map(function (layer) { return Object.assign({}, layer); }));
    const plan = fifo3dpPlanAllocation_(layers, item.qty);
    plan.allocations.forEach(function (allocation) {
      const layer = layers.filter(function (candidate) { return candidate.row === allocation.row; })[0];
      layer.available_qty -= allocation.quantity;
    });
    return plan;
  });
  const gifts = getSheet3dp_(spreadsheet, SHEETS_3DP.plyushky);
  const batches = spreadsheet.getSheetByName(CATALOG_FIFO_3DP.batchesSheet);
  const batchDeltas = {};
  plans.forEach(function (plan) { plan.allocations.forEach(function (entry) { batchDeltas[entry.row] = (batchDeltas[entry.row] || 0) + entry.quantity; }); });
  const batchSnapshots = Object.keys(batchDeltas).map(function (row) { return { row: Number(row), values: batches.getRange(Number(row), 19, 1, 2).getValues()[0] }; });
  const giftRows = [];
  const allocationStart = allocationSheet.getLastRow() + 1;
  try {
    batchSnapshots.forEach(function (snapshot) {
      const delta = batchDeltas[snapshot.row];
      batches.getRange(snapshot.row, 19, 1, 2).setValues([[Number(snapshot.values[0] || 0) + delta, Number(snapshot.values[1] || 0) - delta]]);
    });
    items.forEach(function (item, index) {
      const row = findFirstBusinessEmptyRow3dp_(gifts, SHEETS_3DP.plyushky, actor);
      const range = gifts.getRange(row, 1, 1, 8);
      const previous = { values: range.getValues()[0], formulas: range.getFormulas()[0], formats: range.getNumberFormats()[0] };
      copyFormulaCells3dp_(gifts, SHEETS_3DP.plyushky, row);
      gifts.getRange(row, 1).setValue(date); gifts.getRange(row, 2).setValue(item.sku);
      gifts.getRange(row, 6).setValue(item.qty); gifts.getRange(row, 7).setValue(order);
      gifts.getRange(row, 8).setValue([item.marker, item.note, '[fifo_allocation:3DP-A-' + item.operation_id.replace(/[^A-Za-z0-9_-]/g, '-') + ']'].filter(Boolean).join(' '));
      giftRows.push({ row: row, range: range, previous: previous });
      allocationSheet.getRange(allocationStart + index, 1, 1, CATALOG_FIFO_3DP.allocationHeaders.length).setValues([[
        '3DP-A-' + item.operation_id.replace(/[^A-Za-z0-9_-]/g, '-'), item.operation_id,
        Utilities.formatDate(new Date(), API_3DP.timezone, "yyyy-MM-dd'T'HH:mm:ssXXX"), 'crm_gift', order, item.sku,
        item.qty, plans[index].total_cost_uah, JSON.stringify(plans[index].allocations), 'consume', actor.role,
        item.fingerprint, 'committed', '', giftRows[index].row, '',
      ]]);
    });
    appendAudit3dp_(spreadsheet, actor, 'FIFO_ORDER_GIFTS_COMMITTED', CATALOG_FIFO_3DP.allocationsSheet,
      'A' + allocationStart + ':P' + (allocationStart + items.length - 1), {}, items.map(function (item, index) { return { sku: item.sku, qty: item.qty, cost: plans[index].total_cost_uah }; }), 'request_id=' + requestId);
  } catch (error) {
    batchSnapshots.forEach(function (snapshot) { batches.getRange(snapshot.row, 19, 1, 2).setValues([snapshot.values]); });
    giftRows.reverse().forEach(function (entry) {
      entry.range.clearContent(); entry.range.setValues([entry.previous.values]);
      entry.previous.formulas.forEach(function (formula, index) { if (formula) entry.range.getCell(1, index + 1).setFormula(formula); });
      entry.range.setNumberFormats([entry.previous.formats]);
    });
    allocationSheet.getRange(allocationStart, 1, items.length, CATALOG_FIFO_3DP.allocationHeaders.length).clearContent();
    throw error;
  }
  return { action: '3dp_order_gifts_append', order: order, rows_added: items.length, already_applied: false,
    items: items.map(function (item, index) { return { sku: item.sku, qty: item.qty, buyout_unit: item.buyout, row: giftRows[index].row, fifo_cost: plans[index].total_cost_uah }; }) };
}

function fifo3dpRejectLegacyStockAdjustment_(spreadsheet, body, actor) {
  assertStockAdjustmentRole3dp_(actor, 'Caller may not adjust stock.');
  throw apiError3dp_('FIFO_RECONCILIATION_REQUIRED', 'Direct stock adjustments are disabled under actual-batch FIFO. Record a manufactured batch or use an explicit FIFO reversal/reconciliation action.');
}

function fifo3dpAssertPrintLogMutable_(spreadsheet, printLogRow) {
  const batches = spreadsheet.getSheetByName(CATALOG_FIFO_3DP.batchesSheet);
  if (!batches || batches.getLastRow() < 2) return;
  const values = batches.getRange(2, 1, batches.getLastRow() - 1, 21).getValues();
  const batch = values.filter(function (row) { return Number(row[2]) === Number(printLogRow); })[0];
  if (batch) throw apiError3dp_('FIFO_BATCH_IMMUTABLE', 'A costed manufactured batch cannot be edited or archived; use an explicit reversal/reconciliation operation.');
}
