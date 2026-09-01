/*
 * TEMPORARY ONE-TIME CRM REPAIR V2 — delete from the live Apps Script project
 * after a successful apply and repeat preview.
 *
 * Scope: exactly the four Продажі rows of OC-FOP-0355.
 * Rebuilds base cost through the current canonical FIFO functions, then
 * reapplies the existing order-component ledger (including marketing items).
 */

const CRM_COST_0355_V2_ = Object.freeze({
  order: 'OC-FOP-0355',
  date: '2026-09-01',
  marker: 'crm_cost_0355_v2_refreeze=2026-09-01',
  lines: Object.freeze([
    Object.freeze({ sku: 'PKM-JP-MBRV-BST', qty: 6 }),
    Object.freeze({ sku: 'PKM-JP-BBLT-BST', qty: 3 }),
    Object.freeze({ sku: 'PKM-JP-SVEX-BLR', qty: 1 }),
    Object.freeze({ sku: 'MTG-JP-AFRS-BST', qty: 2 })
  ])
});

function previewCrmCost0355OrderRepairV2() {
  const result = crmCost0355OrderRepairV2Action_(true);
  Logger.log(JSON.stringify(result));
  return result;
}

function repairCrmCost0355OrderV2() {
  const lock = LockService.getDocumentLock();
  if (!lock.tryLock(30000)) throw new Error('CRM зараз змінюється іншою операцією; повтори пізніше.');
  try {
    const result = crmCost0355OrderRepairV2Action_(false);
    Logger.log(JSON.stringify(result));
    return result;
  } finally {
    lock.releaseLock();
  }
}

function crmCost0355OrderRepairV2Action_(dryRun) {
  resetMemoForMutation_();
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const plan = crmCost0355OrderRepairV2Plan_(ss);
  if (dryRun || plan.already_applied) {
    return {
      ok: true,
      action: 'crm_cost_0355_order_repair_v2',
      dry_run: !!dryRun,
      already_applied: plan.already_applied,
      rows_written: 0,
      order: CRM_COST_0355_V2_.order,
      component_totals_preserved: plan.component_totals,
      lines: plan.lines
    };
  }

  const sales = plan.sales;
  const firstRow = plan.rows[0];
  const rowCount = plan.rows.length;
  const originalCosts = sales.getRange(firstRow, 12, rowCount, 2).getValues();
  const originalAudit = sales.getRange(firstRow, 30, rowCount, 3).getValues();
  const integrityBefore = apiIntegrityCheck_();
  if (!integrityBefore.clean) throw new Error('CRM integrity check до V2 repair не чистий; запис зупинено.');

  try {
    resetOrderComponentCostProjectionBeforeBaseRefresh_(ss, plan.rows);
    const runState = {};
    plan.rows.forEach(function(row) {
      fixSaleCostForRow_(ss, row, runState, { clearPending: true, forceRecalculate: true });
    });
    const reapplied = reapplyOrderComponentCostAfterBaseRefresh_(ss, CRM_COST_0355_V2_.order, plan.rows);
    if (Math.abs(num_(reapplied.prro_total) - plan.component_totals.prro) > 0.009 ||
        Math.abs(num_(reapplied.mgmt_total) - plan.component_totals.mgmt) > 0.009) {
      throw new Error('Після FIFO змінилися підсумки компонентів замовлення; виконано rollback.');
    }

    plan.rows.forEach(function(row) {
      const auditCell = sales.getRange(row, 31);
      auditCell.setValue(trimCostAudit_([String(auditCell.getValue() || ''), CRM_COST_0355_V2_.marker].filter(Boolean).join('; ')));
      sales.getRange(row, 32).setValue(new Date());
    });
    SpreadsheetApp.flush();

    const after = crmCost0355OrderRepairV2Readback_(sales, plan.rows);
    after.forEach(function(line, index) {
      const expected = plan.lines[index];
      if (line.sku !== expected.sku ||
          Math.abs(line.prro_unit - expected.planned_prro_unit) > 0.009 ||
          Math.abs(line.mgmt_unit - expected.planned_mgmt_unit) > 0.009 ||
          line.audit.indexOf(CRM_COST_0355_V2_.marker) === -1) {
        throw new Error('Read-back V2 не збігся з preview для ' + expected.sku + '; виконано rollback.');
      }
    });

    const integrityAfter = apiIntegrityCheck_();
    const integrity = crmAssertCapacityIntegrity_(integrityBefore, integrityAfter);
    invalidateDoGetCache_();
    return {
      ok: true,
      action: 'crm_cost_0355_order_repair_v2',
      dry_run: false,
      already_applied: false,
      rows_written: rowCount,
      order: CRM_COST_0355_V2_.order,
      component_totals_preserved: plan.component_totals,
      after: after,
      integrity: integrity
    };
  } catch (error) {
    sales.getRange(firstRow, 12, rowCount, 2).setValues(originalCosts);
    sales.getRange(firstRow, 30, rowCount, 3).setValues(originalAudit);
    SpreadsheetApp.flush();
    invalidateDoGetCache_();
    throw error;
  }
}

function crmCost0355OrderRepairV2Plan_(ss) {
  const sales = ss.getSheetByName('Продажі');
  if (!sales) throw new Error('Не знайдено вкладку Продажі.');
  const matches = [];
  sales.getRange(3, 1, Math.max(sales.getLastRow() - 2, 1), 32).getValues().forEach(function(values, index) {
    if (String(values[0] || '').trim() === CRM_COST_0355_V2_.order) matches.push({ row: index + 3, values: values });
  });
  if (matches.length !== CRM_COST_0355_V2_.lines.length) {
    throw new Error('Очікувалося 4 рядки OC-FOP-0355, знайдено: ' + matches.length + '.');
  }
  matches.sort(function(a, b) { return a.row - b.row; });
  const rows = matches.map(function(item) { return item.row; });
  if (rows.some(function(row, index) { return index > 0 && row !== rows[0] + index; })) {
    throw new Error('Рядки OC-FOP-0355 більше не утворюють один безперервний блок; V2 зупинено.');
  }

  matches.forEach(function(item, index) {
    const expected = CRM_COST_0355_V2_.lines[index];
    const values = item.values;
    if (String(values[5] || '').trim() !== expected.sku || Math.abs(num_(values[7]) - expected.qty) > 0.000001 || apiDate_(values[2]) !== CRM_COST_0355_V2_.date) {
      throw new Error('Склад замовлення змінився біля рядка ' + item.row + '; V2 нічого не записав.');
    }
    if (!isActualSaleForCost_(values)) throw new Error('Рядок ' + item.row + ' не є фактичним продажем; V2 зупинено.');
  });

  const componentTotals = orderComponentTotals_(ss, CRM_COST_0355_V2_.order);
  const weights = orderRowWeights_(sales, rows);
  const prroAllocations = allocateAmount_(componentTotals.unassigned.prro, weights);
  const mgmtAllocations = allocateAmount_(componentTotals.unassigned.mgmt, weights);
  const runState = {};
  const lines = matches.map(function(item, index) {
    const values = item.values;
    const sku = String(values[5] || '').trim();
    const qty = num_(values[7]);
    const fifo = calculateFifoSaleCost_(ss, sku, qty, item.row, values[2], runState);
    const auto = calculateAutoConsumableLineCost_(ss, values, item.row, runState);
    const targeted = componentTotals.byRow[item.row] || { prro: 0, mgmt: 0 };
    const rowPrro = round2_(num_(prroAllocations[index]) + num_(targeted.prro));
    const rowMgmt = round2_(num_(mgmtAllocations[index]) + num_(targeted.mgmt));
    const basePrro = round2_(num_(fifo.prroUnit) * qty);
    const baseMgmt = round2_(num_(fifo.mgmtUnit) * qty + num_(auto.cost));
    runState[sku] = num_(runState[sku]) + qty;
    return {
      row: item.row,
      sku: sku,
      qty: qty,
      before_prro_unit: round2_(num_(values[11])),
      before_mgmt_unit: round2_(num_(values[12])),
      planned_prro_unit: round2_((basePrro + rowPrro) / qty),
      planned_mgmt_unit: round2_((baseMgmt + rowMgmt) / qty),
      fifo_audit: String(fifo.audit || ''),
      auto_consumables: String(auto.audit || ''),
      order_components_prro: rowPrro,
      order_components_mgmt: rowMgmt
    };
  });
  const alreadyApplied = matches.every(function(item) {
    return String(item.values[30] || '').indexOf(CRM_COST_0355_V2_.marker) !== -1;
  });
  return {
    sales: sales,
    rows: rows,
    lines: lines,
    already_applied: alreadyApplied,
    component_totals: { prro: round2_(num_(componentTotals.prro)), mgmt: round2_(num_(componentTotals.mgmt)) }
  };
}

function crmCost0355OrderRepairV2Readback_(sales, rows) {
  return rows.map(function(row) {
    const values = sales.getRange(row, 1, 1, 32).getValues()[0];
    return {
      row: row,
      sku: String(values[5] || '').trim(),
      prro_unit: round2_(num_(values[11])),
      mgmt_unit: round2_(num_(values[12])),
      method: String(values[29] || '').trim(),
      audit: String(values[30] || '').trim()
    };
  });
}
