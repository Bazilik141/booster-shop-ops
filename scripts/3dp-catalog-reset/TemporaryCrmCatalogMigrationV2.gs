/**
 * TEMPORARY V2 rehearsal for the CRM 3D catalogue migration.
 * Requires TemporaryCrmCatalogMigration.gs in the same Apps Script project.
 * This file does not mutate the live CRM. It rehearses the catalogue import in
 * a Drive copy and verifies that the live catalogue fingerprint is unchanged.
 */

function catalogMigrationCrmV2Log_(result) {
  console.log('CATALOG_MIGRATION_CRM_V2 ' + JSON.stringify(result));
  return result;
}

function catalogMigrationCrmV2Hash_(value) {
  const bytes = Utilities.computeDigest(
    Utilities.DigestAlgorithm.SHA_256,
    JSON.stringify(value),
    Utilities.Charset.UTF_8
  );
  return bytes.map(function (byte) {
    const value = byte < 0 ? byte + 256 : byte;
    return ('0' + value.toString(16)).slice(-2);
  }).join('');
}

function catalogMigrationCrmV2RangeState_(range) {
  return {
    values: range.getValues(),
    formulas: range.getFormulasR1C1(),
  };
}

function catalogMigrationCrmV2Fingerprint_(ss) {
  const products = ss.getSheetByName('Товари');
  const rrp = ss.getSheetByName('РРЦ');
  const stock = ss.getSheetByName('Склад');
  const settings = ss.getSheetByName('Налаштування');
  if (!products || !rrp || !stock || !settings) throw new Error('CRM catalogue sheet is missing.');
  const last = crmCatalogLastWritableRow_(products, rrp, stock);
  const optionState = CRM_CATALOG_OPTION_KEYS_.map(function (key) {
    const config = crmCatalogOptionConfig_(key);
    return { key: key, values: crmCatalogOptionValues_(settings, config) };
  });
  return catalogMigrationCrmV2Hash_({
    products: catalogMigrationCrmV2RangeState_(products.getRange(3, 1, last - 2, 15)),
    rrp: catalogMigrationCrmV2RangeState_(rrp.getRange(3, 1, last - 2, 8)),
    options: optionState,
  });
}

function catalogMigrationCrmV2OptionRequests_() {
  const seen = {};
  const requests = [];
  CATALOG_MIGRATION_CRM_ROWS_.forEach(function (data) {
    [
      { key: 'brand', value: data.D },
      { key: 'language', value: data.E },
      { key: 'set', value: data.F },
      { key: 'format', value: data.G },
    ].forEach(function (request) {
      const value = String(request.value || '').trim();
      const identity = request.key + '\u0001' + value;
      if (!value || seen[identity]) return;
      seen[identity] = true;
      requests.push({ key: request.key, value: value });
    });
  });
  return requests;
}

function catalogMigrationCrmV2TargetRows_(ss, oldRows) {
  const products = ss.getSheetByName('Товари');
  const rrp = ss.getSheetByName('РРЦ');
  const stock = ss.getSheetByName('Склад');
  const last = crmCatalogLastWritableRow_(products, rrp, stock);
  const productValues = products.getRange(3, 1, last - 2, 15).getValues();
  const rrpValues = rrp.getRange(3, 5, last - 2, 3).getValues();
  const old = {};
  oldRows.forEach(function (item) { old[item.row] = true; });
  const manualIndexes = [0, 2, 3, 4, 5, 6, 7, 8, 10, 11, 12, 13, 14];
  const rows = [];
  productValues.forEach(function (values, index) {
    const row = index + 3;
    const productBlank = manualIndexes.every(function (column) {
      return String(values[column] == null ? '' : values[column]).trim() === '';
    });
    const rrpBlank = rrpValues[index].every(function (value) {
      return String(value == null ? '' : value).trim() === '';
    });
    if (old[row] || (productBlank && rrpBlank)) rows.push(row);
  });
  if (rows.length < CATALOG_MIGRATION_CRM_ROWS_.length) {
    throw new Error('Only ' + rows.length + ' empty catalogue rows are available; 72 are required.');
  }
  return rows.slice(0, CATALOG_MIGRATION_CRM_ROWS_.length);
}

function catalogMigrationCrmV2Groups_(rows) {
  const groups = [];
  rows.forEach(function (row, index) {
    const previous = groups.length ? groups[groups.length - 1] : null;
    if (!previous || row !== previous.last + 1) {
      groups.push({ first: row, last: row, indexes: [index] });
    } else {
      previous.last = row;
      previous.indexes.push(index);
    }
  });
  return groups;
}

function catalogMigrationCrmV2InstallOptions_(ss) {
  const settings = ss.getSheetByName('Налаштування');
  if (!settings) throw new Error('Налаштування sheet is missing.');
  const requests = catalogMigrationCrmV2OptionRequests_();
  const capacity = crmEnsureCatalogOptionCapacity_(ss, requests, true);
  const added = [];
  requests.forEach(function (request) {
    const plan = apiCatalogOptionPlan_(settings, request.key, request.value, true);
    if (!plan) return;
    plan.cell.setValue(plan.value);
    SpreadsheetApp.flush();
    added.push({ key: request.key, value: request.value, cell: plan.cell.getA1Notation() });
  });
  return { requests: requests, added: added, capacity: capacity };
}

function catalogMigrationCrmV2WriteCatalogue_(ss) {
  const products = ss.getSheetByName('Товари');
  const rrp = ss.getSheetByName('РРЦ');
  const stock = ss.getSheetByName('Склад');
  if (!products || !rrp || !stock) throw new Error('CRM catalogue sheet is missing.');
  const last = crmCatalogLastWritableRow_(products, rrp, stock);
  const oldRows = catalogMigrationCrm3dRows_(products, last);
  const actualOld = oldRows.map(function (item) { return item.sku; }).sort();
  if (JSON.stringify(actualOld) !== JSON.stringify(CATALOG_MIGRATION_CRM_EXPECTED_OLD_)) {
    throw new Error('Rehearsal stopped: old 3D catalogue differs from the approved preflight.');
  }
  const rows = catalogMigrationCrmV2TargetRows_(ss, oldRows);
  rows.forEach(function (row) {
    if (!products.getRange(row, 10).getFormula()) throw new Error('Товари!J' + row + ' formula is missing.');
    if (!rrp.getRange(row, 8).getFormula()) throw new Error('РРЦ!H' + row + ' formula is missing.');
  });
  const options = catalogMigrationCrmV2InstallOptions_(ss);
  oldRows.forEach(function (item) {
    products.getRange(item.row, 1, 1, 9).clearContent();
    products.getRange(item.row, 11, 1, 5).clearContent();
    rrp.getRange(item.row, 5, 1, 3).clearContent();
  });

  catalogMigrationCrmV2Groups_(rows).forEach(function (group) {
    const dataRows = group.indexes.map(function (index) { return CATALOG_MIGRATION_CRM_ROWS_[index]; });
    products.getRange(group.first, 1, dataRows.length, 1).setValues(dataRows.map(function (data) { return [data.A]; }));
    products.getRange(group.first, 2, dataRows.length, 1).setFormulas(dataRows.map(function (_, offset) {
      const row = group.first + offset;
      return ['=IF($A' + row + '="";"";$C' + row + ')'];
    }));
    products.getRange(group.first, 3, dataRows.length, 7).setValues(dataRows.map(function (data) {
      return [data.C || '', data.D || '', data.E || '', data.F || '', data.G || '', data.H == null ? '' : data.H, data.I == null ? '' : data.I];
    }));
    products.getRange(group.first, 11, dataRows.length, 5).setValues(dataRows.map(function (data) {
      return [data.K == null ? '' : data.K, data.L || '', data.M == null ? '' : data.M, data.N || '', data.O == null ? '' : data.O];
    }));
  });
  SpreadsheetApp.flush();
  Utilities.sleep(2000);
  SpreadsheetApp.flush();

  catalogMigrationCrmV2Groups_(rows).forEach(function (group) {
    const dataRows = group.indexes.map(function (index) { return CATALOG_MIGRATION_CRM_ROWS_[index]; });
    const projectedSkus = rrp.getRange(group.first, 1, dataRows.length, 1).getDisplayValues();
    dataRows.forEach(function (data, offset) {
      const row = group.first + offset;
      const projectedSku = String(projectedSkus[offset][0] || '').trim();
      if (projectedSku !== data.A) throw new Error('РРЦ projection mismatch at A' + row + ': expected ' + data.A + ', got ' + projectedSku + '.');
    });
    rrp.getRange(group.first, 5, dataRows.length, 3).setValues(dataRows.map(function (data) {
      return [data.rrp_E == null ? '' : data.rrp_E, new Date(), data.rrp_G || ''];
    }));
  });
  SpreadsheetApp.flush();
  Utilities.sleep(2000);
  SpreadsheetApp.flush();

  const problems = [];
  const seen = {};
  catalogMigrationCrmV2Groups_(rows).forEach(function (group) {
    const dataRows = group.indexes.map(function (index) { return CATALOG_MIGRATION_CRM_ROWS_[index]; });
    const productDisplays = products.getRange(group.first, 1, dataRows.length, 2).getDisplayValues();
    const shortNameFormulas = products.getRange(group.first, 2, dataRows.length, 1).getFormulas();
    const currentPriceFormulas = products.getRange(group.first, 10, dataRows.length, 1).getFormulas();
    const rrpDisplays = rrp.getRange(group.first, 1, dataRows.length, 2).getDisplayValues();
    const rrpValues = rrp.getRange(group.first, 5, dataRows.length, 1).getValues();
    const dynamicRrpFormulas = rrp.getRange(group.first, 8, dataRows.length, 1).getFormulas();
    dataRows.forEach(function (data, offset) {
      const row = group.first + offset;
      const sku = String(productDisplays[offset][0] || '').trim();
      const productName = String(productDisplays[offset][1] || '').trim();
      const rrpSku = String(rrpDisplays[offset][0] || '').trim();
      const rrpName = String(rrpDisplays[offset][1] || '').trim();
      if (sku !== data.A || seen[sku]) problems.push({ row: row, sku: data.A, code: 'sku_write_or_duplicate' });
      seen[sku] = true;
      if (!shortNameFormulas[offset][0]) problems.push({ row: row, sku: data.A, code: 'short_name_formula_missing' });
      if (!currentPriceFormulas[offset][0]) problems.push({ row: row, sku: data.A, code: 'current_price_formula_missing' });
      if (!dynamicRrpFormulas[offset][0]) problems.push({ row: row, sku: data.A, code: 'dynamic_rrp_formula_missing' });
      if (!productName || /^#/.test(productName) || rrpSku !== data.A || rrpName !== productName) {
        problems.push({ row: row, sku: data.A, code: 'rrp_projection_mismatch' });
      }
      if (!catalogMigrationCrmValueMatches_(rrpValues[offset][0], data.rrp_E)) {
        problems.push({ row: row, sku: data.A, code: 'rrp_value_mismatch' });
      }
    });
  });
  const seedDisplays = rrp.getRange('A3:D3').getDisplayValues()[0];
  if (seedDisplays.some(function (value) { return /^#/.test(String(value || '')); })) {
    problems.push({ row: 3, code: 'rrp_arrayformula_error', values: seedDisplays });
  }
  if (problems.length) throw new Error('Catalogue rehearsal verification failed: ' + JSON.stringify(problems.slice(0, 20)));
  if (!catalogMigrationCrmRowsMatchTarget_(products, rrp, last)) throw new Error('Catalogue target verification failed.');
  return {
    rows: rows,
    row_groups: catalogMigrationCrmV2Groups_(rows).map(function (group) { return group.first + ':' + group.last; }),
    options: options,
    target_count: CATALOG_MIGRATION_CRM_ROWS_.length,
    active: CATALOG_MIGRATION_CRM_ROWS_.filter(function (data) { return data.L === 'Так'; }).length,
    inactive: CATALOG_MIGRATION_CRM_ROWS_.filter(function (data) { return data.L === 'Ні'; }).length,
  };
}

function catalogMigrationCrmRehearsalV2() {
  const live = _getCrmSs();
  const preview = catalogMigrationCrmPreview();
  if (!preview.ok || preview.state !== 'ready') {
    return catalogMigrationCrmV2Log_({
      ok: false,
      action: 'catalog_migration_crm_rehearsal_v2',
      state: 'blocked_live_preflight',
      preview_state: preview.state,
      integrity: preview.integrity,
    });
  }
  const beforeFingerprint = catalogMigrationCrmV2Fingerprint_(live);
  const stamp = Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyyMMdd-HHmmss');
  const copyFile = DriveApp.getFileById(live.getId()).makeCopy(live.getName() + ' DRYRUN-3DP-CATALOG-V2-' + stamp);
  try {
    const rehearsal = SpreadsheetApp.openById(copyFile.getId());
    const result = catalogMigrationCrmV2WriteCatalogue_(rehearsal);
    const afterFingerprint = catalogMigrationCrmV2Fingerprint_(live);
    if (afterFingerprint !== beforeFingerprint) throw new Error('Live CRM fingerprint changed during copy rehearsal.');
    copyFile.setTrashed(true);
    return catalogMigrationCrmV2Log_({
      ok: true,
      action: 'catalog_migration_crm_rehearsal_v2',
      state: 'catalogue_rehearsal_passed',
      live_unchanged: true,
      live_fingerprint: beforeFingerprint,
      rehearsal_copy_trashed: true,
      result: result,
    });
  } catch (error) {
    return catalogMigrationCrmV2Log_({
      ok: false,
      action: 'catalog_migration_crm_rehearsal_v2',
      state: 'catalogue_rehearsal_failed',
      live_unchanged: catalogMigrationCrmV2Fingerprint_(live) === beforeFingerprint,
      live_fingerprint: beforeFingerprint,
      rehearsal_file_id: copyFile.getId(),
      error: String(error && error.message ? error.message : error),
    });
  }
}
