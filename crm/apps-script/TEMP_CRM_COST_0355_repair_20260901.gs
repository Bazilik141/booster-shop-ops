/*
 * TEMPORARY ONE-TIME CRM REPAIR — delete from the live Apps Script project
 * after the owner has captured the successful apply + repeat/read-back output.
 *
 * Scope: exactly OC-FOP-0355 / PKM-JP-BBLT-BST in Продажі.
 * Writes on apply: only L:M and AD:AF of the one resolved sale row.
 * Dependencies: the current main Code.gs in the same bound Apps Script project.
 */

const CRM_COST_0355_REPAIR_ = Object.freeze({
  order: 'OC-FOP-0355',
  sku: 'PKM-JP-BBLT-BST',
  date: '2026-09-01',
  qty: 3,
  price: 350,
  bad_lot: 'LOT-0073',
  bad_lot_sku: 'PKM-JP-MZERO-BBX',
  bad_prro_unit: 2886.34,
  bad_mgmt_unit: 3074.02,
  expected_prro_unit: 217.84,
  expected_mgmt_unit: 230.91,
  marker: 'crm_cost_0355_refreeze=2026-09-01'
});

function previewCrmCost0355Repair() {
  const result = crmCost0355RepairAction_(true);
  Logger.log(JSON.stringify(result));
  return result;
}

function repairCrmCost0355() {
  const lock = LockService.getDocumentLock();
  if (!lock.tryLock(30000)) throw new Error('CRM зараз змінюється іншою операцією; повтори пізніше.');
  try {
    const result = crmCost0355RepairAction_(false);
    Logger.log(JSON.stringify(result));
    return result;
  } finally {
    lock.releaseLock();
  }
}

function crmCost0355RepairAction_(dryRun) {
  resetMemoForMutation_();
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const plan = crmCost0355Plan_(ss);
  if (dryRun || plan.already_applied) {
    return {
      ok: true,
      action: 'crm_cost_0355_repair',
      dry_run: !!dryRun,
      already_applied: plan.already_applied,
      rows_written: 0,
      target: plan.target,
      before: plan.before,
      planned: plan.planned,
      bad_lot: plan.bad_lot
    };
  }

  const sales = plan.sales;
  const originalCosts = sales.getRange(plan.row, 12, 1, 2).getValues();
  const originalAudit = sales.getRange(plan.row, 30, 1, 3).getValues();
  const integrityBefore = apiIntegrityCheck_();
  if (!integrityBefore.clean) throw new Error('CRM integrity check до repair не чистий; запис зупинено.');

  try {
    sales.getRange(plan.row, 12, 1, 2).setValues([[
      plan.planned.prro_unit,
      plan.planned.mgmt_unit
    ]]);
    sales.getRange(plan.row, 30, 1, 3).setValues([[
      'FIFO (CRM-COST-0355)',
      trimCostAudit_([plan.planned.audit, CRM_COST_0355_REPAIR_.marker].filter(Boolean).join('; ')),
      new Date()
    ]]);
    SpreadsheetApp.flush();

    const readBack = sales.getRange(plan.row, 1, 1, 32).getValues()[0];
    if (String(readBack[0] || '').trim() !== CRM_COST_0355_REPAIR_.order ||
        String(readBack[5] || '').trim() !== CRM_COST_0355_REPAIR_.sku ||
        Math.abs(num_(readBack[11]) - plan.planned.prro_unit) > 0.009 ||
        Math.abs(num_(readBack[12]) - plan.planned.mgmt_unit) > 0.009 ||
        String(readBack[30] || '').indexOf(CRM_COST_0355_REPAIR_.marker) === -1) {
      throw new Error('Read-back ремонту собівартості не збігся з планом.');
    }

    const integrityAfter = apiIntegrityCheck_();
    const integrity = crmAssertCapacityIntegrity_(integrityBefore, integrityAfter);
    invalidateDoGetCache_();
    return {
      ok: true,
      action: 'crm_cost_0355_repair',
      dry_run: false,
      already_applied: false,
      rows_written: 1,
      target: plan.target,
      before: plan.before,
      after: {
        prro_unit: round2_(num_(readBack[11])),
        mgmt_unit: round2_(num_(readBack[12])),
        method: String(readBack[29] || '').trim(),
        audit: String(readBack[30] || '').trim()
      },
      integrity: integrity
    };
  } catch (error) {
    sales.getRange(plan.row, 12, 1, 2).setValues(originalCosts);
    sales.getRange(plan.row, 30, 1, 3).setValues(originalAudit);
    SpreadsheetApp.flush();
    invalidateDoGetCache_();
    throw error;
  }
}

function crmCost0355Plan_(ss) {
  const config = CRM_COST_0355_REPAIR_;
  const sales = ss.getSheetByName('Продажі');
  const purchases = ss.getSheetByName('Закупки');
  if (!sales || !purchases) throw new Error('Не знайдено вкладку Продажі або Закупки.');

  const matches = [];
  sales.getRange(3, 1, Math.max(sales.getLastRow() - 2, 1), 32).getValues().forEach(function(values, index) {
    if (String(values[0] || '').trim() === config.order && String(values[5] || '').trim() === config.sku) {
      matches.push({ row: index + 3, values: values });
    }
  });
  if (matches.length !== 1) throw new Error('Очікувався рівно один рядок ' + config.order + ' / ' + config.sku + ', знайдено: ' + matches.length + '.');

  const target = matches[0], values = target.values;
  if (apiDate_(values[2]) !== config.date || Math.abs(num_(values[7]) - config.qty) > 0.000001 || Math.abs(num_(values[8]) - config.price) > 0.009) {
    throw new Error('Цільовий рядок змінив дату, кількість або ціну; repair зупинено.');
  }
  if (!isActualSaleForCost_(values) || isMysteryBoxSale_(config.sku, values[6]) || is3dpPackagingSku_(config.sku)) {
    throw new Error('Цільовий рядок більше не є звичайним фактичним FIFO-продажем.');
  }

  const lotMatches = [];
  purchases.getRange(3, 1, Math.max(purchases.getLastRow() - 2, 1), 18).getValues().forEach(function(row, index) {
    if (String(row[0] || '').trim() === config.bad_lot) lotMatches.push({ row: index + 3, values: row });
  });
  if (lotMatches.length !== 1 || String(lotMatches[0].values[4] || '').trim() !== config.bad_lot_sku) {
    throw new Error(config.bad_lot + ' більше не має підтверджений чужий SKU ' + config.bad_lot_sku + '.');
  }

  const currentPrro = round2_(num_(values[11]));
  const currentMgmt = round2_(num_(values[12]));
  const currentMethod = String(values[29] || '').trim();
  const currentAudit = String(values[30] || '').trim();
  const alreadyApplied = currentAudit.indexOf(config.marker) !== -1 &&
    Math.abs(currentPrro - config.expected_prro_unit) <= 0.009 &&
    Math.abs(currentMgmt - config.expected_mgmt_unit) <= 0.009;
  if (!alreadyApplied && (Math.abs(currentPrro - config.bad_prro_unit) > 0.009 ||
      Math.abs(currentMgmt - config.bad_mgmt_unit) > 0.009 || currentAudit.indexOf(config.bad_lot) === -1)) {
    throw new Error('Поточна помилкова собівартість або аудит уже змінилися; repair нічого не записав.');
  }

  const fifo = calculateFifoSaleCost_(ss, config.sku, config.qty, target.row, values[2], {});
  const fifoPrro = round2_(num_(fifo.prroUnit));
  const fifoMgmt = round2_(num_(fifo.mgmtUnit));
  if (Math.abs(fifoPrro - config.expected_prro_unit) > 0.009 || Math.abs(fifoMgmt - config.expected_mgmt_unit) > 0.009) {
    throw new Error('Поточний FIFO дає ' + fifoPrro + ' / ' + fifoMgmt + ' замість підтверджених ' + config.expected_prro_unit + ' / ' + config.expected_mgmt_unit + '; repair зупинено.');
  }

  return {
    sales: sales,
    row: target.row,
    already_applied: alreadyApplied,
    target: { sheet: 'Продажі', row: target.row, order: config.order, sku: config.sku, qty: config.qty },
    before: { prro_unit: currentPrro, mgmt_unit: currentMgmt, method: currentMethod, audit: currentAudit },
    planned: { prro_unit: fifoPrro, mgmt_unit: fifoMgmt, method: String(fifo.method || 'FIFO'), audit: String(fifo.audit || '') },
    bad_lot: { id: config.bad_lot, row: lotMatches[0].row, sku: String(lotMatches[0].values[4] || '').trim() }
  };
}
