/**
 * Owner-run copy rehearsal for actual manufactured-batch FIFO.
 * Requires the reviewed Code.gs + CatalogFifo.gs candidate in the bound 3D-P project.
 * The live spreadsheet is fingerprinted before/after and never passed to a mutation action.
 */

function catalogFifo3dpRehearsal() {
  const live = getSpreadsheet3dp_();
  const before = catalogFifo3dpBusinessFingerprint_(live);
  const stamp = Utilities.formatDate(new Date(), API_3DP.timezone, 'yyyyMMdd-HHmmss');
  const copyFile = DriveApp.getFileById(live.getId()).makeCopy(live.getName() + ' DRYRUN-FIFO-' + stamp);
  const copy = SpreadsheetApp.openById(copyFile.getId());
  const actor = { role: 'owner', identity: 'fifo-copy-rehearsal' };
  try {
    setup3dpCatalogFifoSchemaFor_(copy);
    const sku = catalogFifo3dpRehearsalSku_(copy);
    const requestBase = 'fifoqa-' + stamp;
    const first = fifo3dpManufactureBatchAction_(copy, {
      sku: sku, quantity: 2, defects: 0, total_print_time_h: 2,
      total_weight_g: 100, spool_weight_g: 1000, spool_price_uah: 800,
      serhiy_consumables_uah: 0, request_id: requestBase + '-b1', printed_by: 'власник', note: 'FIFO copy rehearsal layer 1',
    }, actor);
    const second = fifo3dpManufactureBatchAction_(copy, {
      sku: sku, quantity: 3, defects: 0, total_print_time_h: 3,
      total_weight_g: 300, spool_weight_g: 1000, spool_price_uah: 800,
      serhiy_consumables_uah: 0, request_id: requestBase + '-b2', printed_by: 'власник', note: 'FIFO copy rehearsal layer 2',
    }, actor);
    const saleOperation = 'crm_sale:DRYRUN-' + stamp + ':999999';
    const sale = fifo3dpCrmSaleCommitAction_(copy, {
      operation_id: saleOperation, order: 'DRYRUN-' + stamp, crm_row: 999999, sku: sku, quantity: 3,
      sale_date: Utilities.formatDate(new Date(), API_3DP.timezone, 'yyyy-MM-dd'), sale_unit_price: 200,
      packaging_unit: 0, profit_share: 0.5, actual_rrp: 200, fixture_cost: 0, fixture_payer: '',
      owner_fixture_per_unit: 0, serhiy_fixture_per_unit: 0, buyout: 100, mode: 'Продаж', channel: 'FIFO rehearsal',
    }, actor);
    if (sale.allocations.length !== 2 || sale.allocations[0].batch_id !== first.batch_id || sale.allocations[1].batch_id !== second.batch_id) throw new Error('FIFO_REHEARSAL_ORDER_FAILED');
    const replay = fifo3dpCrmSaleCommitAction_(copy, {
      operation_id: saleOperation, order: 'DRYRUN-' + stamp, crm_row: 999999, sku: sku, quantity: 3,
      sale_date: Utilities.formatDate(new Date(), API_3DP.timezone, 'yyyy-MM-dd'), sale_unit_price: 200,
      packaging_unit: 0, profit_share: 0.5, actual_rrp: 200, fixture_cost: 0, fixture_payer: '',
      owner_fixture_per_unit: 0, serhiy_fixture_per_unit: 0, buyout: 100, mode: 'Продаж', channel: 'FIFO rehearsal',
    }, actor);
    if (!replay.already_applied || replay.allocation_id !== sale.allocation_id) throw new Error('FIFO_REHEARSAL_REPLAY_FAILED');
    const reversal = fifo3dpReverseAction_(copy, {
      operation_id: 'crm_reverse:DRYRUN-' + stamp + ':999999', original_operation_id: saleOperation,
      date: Utilities.formatDate(new Date(), API_3DP.timezone, 'yyyy-MM-dd'), reason: 'FIFO copy rehearsal return',
    }, actor);
    const reversalReplay = fifo3dpReverseAction_(copy, {
      operation_id: 'crm_reverse:DRYRUN-' + stamp + ':999999', original_operation_id: saleOperation,
      date: Utilities.formatDate(new Date(), API_3DP.timezone, 'yyyy-MM-dd'), reason: 'FIFO copy rehearsal return',
    }, actor);
    if (!reversalReplay.already_applied || reversalReplay.reversal_id !== reversal.reversal_id) throw new Error('FIFO_REHEARSAL_REVERSAL_REPLAY_FAILED');
    let reactivationBlocked = false;
    try {
      fifo3dpCrmSaleCommitAction_(copy, {
        operation_id: saleOperation, order: 'DRYRUN-' + stamp, crm_row: 999999, sku: sku, quantity: 3,
        sale_date: Utilities.formatDate(new Date(), API_3DP.timezone, 'yyyy-MM-dd'), sale_unit_price: 200,
        packaging_unit: 0, profit_share: 0.5, actual_rrp: 200, fixture_cost: 0, fixture_payer: '',
        owner_fixture_per_unit: 0, serhiy_fixture_per_unit: 0, buyout: 100, mode: 'Продаж', channel: 'FIFO rehearsal',
      }, actor);
    } catch (error) { reactivationBlocked = String(error && error.code || error).indexOf('FIFO_ALLOCATION_REVERSED') !== -1; }
    if (!reactivationBlocked) throw new Error('FIFO_REHEARSAL_REACTIVATION_NOT_BLOCKED');
    const cleanBeforeDrift = fifo3dpReconcileAction_(copy, actor);
    if (!cleanBeforeDrift.clean) throw new Error('FIFO_REHEARSAL_NOT_CLEAN_AFTER_REVERSAL');
    const batches = copy.getSheetByName(CATALOG_FIFO_3DP.batchesSheet);
    batches.getRange(2, 19, 1, 2).setValues([[1, 1]]);
    const dirty = fifo3dpReconcileAction_(copy, actor);
    if (dirty.clean || !dirty.repairable_batches.length) throw new Error('FIFO_REHEARSAL_DRIFT_NOT_DETECTED');
    const repaired = fifo3dpRepairAction_(copy, {
      request_id: requestBase + '-repair', expected_fingerprint: dirty.fingerprint, reason: 'FIFO copy rehearsal counter repair',
    }, actor);
    if (repaired.already_applied || !repaired.reconciliation.clean) throw new Error('FIFO_REHEARSAL_REPAIR_FAILED');
    const finalCheck = fifo3dpReconcileAction_(copy, actor);
    const liveUnchanged = catalogFifo3dpBusinessFingerprint_(live) === before;
    if (!finalCheck.clean || !liveUnchanged) throw new Error('FIFO_REHEARSAL_FINAL_VERIFICATION_FAILED');
    copyFile.setTrashed(true);
    const result = { ok: true, action: 'catalog_fifo_3dp_rehearsal', state: 'rehearsal_passed', live_unchanged: true,
      rehearsal_copy_trashed: true, sku: sku, batches: 2, sale_layers: sale.allocations.length,
      sale_replay: replay.already_applied, reversal_replay: reversalReplay.already_applied,
      reactivation_blocked: true, repair_changed_batches: repaired.changed_batches, reconciliation_clean: true };
    console.log('CATALOG_FIFO_3DP ' + JSON.stringify(result));
    return result;
  } catch (error) {
    const result = { ok: false, action: 'catalog_fifo_3dp_rehearsal', state: 'rehearsal_failed', live_unchanged: catalogFifo3dpBusinessFingerprint_(live) === before,
      rehearsal_file_id: copyFile.getId(), error: String(error && error.message || error) };
    console.log('CATALOG_FIFO_3DP ' + JSON.stringify(result));
    return result;
  }
}

function setup3dpCatalogFifoSchemaFor_(spreadsheet) {
  fifo3dpEnsureAnalyticsSheet_(spreadsheet);
  fifo3dpEnsureHiddenSheet_(spreadsheet, CATALOG_FIFO_3DP.batchesSheet, CATALOG_FIFO_3DP.batchHeaders);
  fifo3dpEnsureHiddenSheet_(spreadsheet, CATALOG_FIFO_3DP.allocationsSheet, CATALOG_FIFO_3DP.allocationHeaders);
  syncActiveNomenclatureAnalytics3dp_(spreadsheet);
}

function catalogFifo3dpRehearsalSku_(spreadsheet) {
  const sheet = getSheet3dp_(spreadsheet, SHEETS_3DP.nomenclature);
  const rows = readTable3dp_(spreadsheet, SHEETS_3DP.nomenclature, { includeArchived: true, requireHeader: 'SKU' }).rows;
  const row = rows.filter(function (entry) {
    return isActiveNomenclatureRow3dp_(entry) && !isBlank3dp_(entry['РРЦ фактична, грн']) && !isBlank3dp_(entry['Ціна під викуп, грн']);
  })[0];
  if (!row) throw new Error('FIFO_REHEARSAL_ACTIVE_PRICED_SKU_NOT_FOUND');
  resolveTargetRow3dp_(sheet, row.SKU);
  return String(row.SKU);
}

function catalogFifo3dpBusinessFingerprint_(spreadsheet) {
  const names = [SHEETS_3DP.nomenclature, SHEETS_3DP.printLog, SHEETS_3DP.sales, SHEETS_3DP.plyushky, SHEETS_3DP.payouts,
    SHEETS_3DP.availability, CATALOG_FIFO_3DP.batchesSheet, CATALOG_FIFO_3DP.allocationsSheet];
  const payload = names.map(function (name) {
    const sheet = spreadsheet.getSheetByName(name);
    if (!sheet) return { name: name, missing: true };
    const rows = Math.max(sheet.getLastRow(), 1), columns = Math.max(sheet.getLastColumn(), 1);
    const range = sheet.getRange(1, 1, rows, columns);
    return { name: name, values: range.getValues(), formulas: range.getFormulas(), formats: range.getNumberFormats() };
  });
  return fifo3dpFingerprint_(payload);
}
