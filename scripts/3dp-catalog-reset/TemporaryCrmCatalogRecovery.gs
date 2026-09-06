/**
 * TEMPORARY owner-run recovery for the interrupted 3D catalogue migration.
 * Restores only the two ranges touched before the failed rollback.
 * Delete this file from Apps Script after verified recovery.
 */

const CRM_CATALOG_RECOVERY_ = Object.freeze({
  backupMarker: ' PRE-3DP-CATALOG-',
  maxBackupAgeMs: 4 * 60 * 60 * 1000,
  specs: Object.freeze([
    Object.freeze({ sheet: 'Товари', range: 'A3:O220', formulaOnlyColumns: Object.freeze([2, 10]) }),
    Object.freeze({ sheet: 'РРЦ', range: 'A3:H220', formulaOnlyColumns: Object.freeze([1, 2, 3, 4, 8]) }),
  ]),
});

const CRM_CATALOG_RECOVERY_MANUAL_SHORT_NAMES_ = Object.freeze([
  Object.freeze({ row: 52, sku: 'ACC-001' }),
  Object.freeze({ row: 53, sku: 'ACC-002' }),
  Object.freeze({ row: 54, sku: 'ACC-003' }),
  Object.freeze({ row: 55, sku: 'ACC-004' }),
  Object.freeze({ row: 56, sku: 'ACC-005' }),
  Object.freeze({ row: 57, sku: 'ACC-006' }),
  Object.freeze({ row: 58, sku: 'ACC-007-360' }),
  Object.freeze({ row: 59, sku: 'ACC-008' }),
  Object.freeze({ row: 60, sku: 'ACC-009' }),
  Object.freeze({ row: 71, sku: 'PKM-JP-MBX-XL' }),
  Object.freeze({ row: 72, sku: 'OP-JP-MBX-XL' }),
  Object.freeze({ row: 73, sku: 'PKM-JP-MBX-ST' }),
  Object.freeze({ row: 74, sku: 'OP-JP-MBX-ST' }),
  Object.freeze({ row: 75, sku: 'ACC-3D-DITTO-410' }),
  Object.freeze({ row: 76, sku: 'PKM-EN-PBLK-BLR-SLP' }),
]);

function catalogMigrationCrmRecoveryLog_(result) {
  console.log('CATALOG_MIGRATION_CRM_RECOVERY ' + JSON.stringify(result));
  return result;
}

function catalogMigrationCrmRecoveryBackup_() {
  const current = _getCrmSs();
  const expectedPrefix = current.getName() + CRM_CATALOG_RECOVERY_.backupMarker;
  const files = DriveApp.searchFiles("title contains 'PRE-3DP-CATALOG-' and trashed = false");
  const candidates = [];
  while (files.hasNext()) {
    const file = files.next();
    if (file.getId() === current.getId() || file.getName().indexOf(expectedPrefix) !== 0) continue;
    candidates.push({ file: file, created: file.getDateCreated() });
  }
  candidates.sort(function (a, b) { return b.created.getTime() - a.created.getTime(); });
  if (!candidates.length) throw new Error('PRE-3DP-CATALOG backup was not found.');
  const selected = candidates[0];
  const ageMs = new Date().getTime() - selected.created.getTime();
  if (ageMs < 0 || ageMs > CRM_CATALOG_RECOVERY_.maxBackupAgeMs) {
    throw new Error('Newest PRE-3DP-CATALOG backup is outside the four-hour recovery window.');
  }
  return {
    file: selected.file,
    spreadsheet: SpreadsheetApp.openById(selected.file.getId()),
    created: selected.created,
    age_ms: ageMs,
  };
}

function catalogMigrationCrmRecoveryMatrix_(sheet, rangeA1, formulaOnlyColumns) {
  const range = sheet.getRange(rangeA1);
  const values = range.getValues();
  const formulas = range.getFormulas();
  const formulaOnly = {};
  formulaOnlyColumns.forEach(function (column) { formulaOnly[column] = true; });
  return values.map(function (row, rowIndex) {
    return row.map(function (value, columnIndex) {
      const formula = formulas[rowIndex][columnIndex];
      if (formula) return formula;
      return formulaOnly[columnIndex + 1] ? '' : value;
    });
  });
}

function catalogMigrationCrmRecoveryHash_(matrix) {
  const bytes = Utilities.computeDigest(
    Utilities.DigestAlgorithm.SHA_256,
    JSON.stringify(matrix),
    Utilities.Charset.UTF_8
  );
  return bytes.map(function (byte) {
    const value = byte < 0 ? byte + 256 : byte;
    return ('0' + value.toString(16)).slice(-2);
  }).join('');
}

function catalogMigrationCrmRecoverySheetState_(current, backup, spec) {
  const currentSheet = current.getSheetByName(spec.sheet);
  const backupSheet = backup.getSheetByName(spec.sheet);
  if (!currentSheet || !backupSheet) throw new Error('Recovery sheet is missing: ' + spec.sheet);
  const currentMatrix = catalogMigrationCrmRecoveryMatrix_(currentSheet, spec.range, spec.formulaOnlyColumns);
  const backupMatrix = catalogMigrationCrmRecoveryMatrix_(backupSheet, spec.range, spec.formulaOnlyColumns);
  return {
    sheet: spec.sheet,
    range: spec.range,
    current_sha256: catalogMigrationCrmRecoveryHash_(currentMatrix),
    backup_sha256: catalogMigrationCrmRecoveryHash_(backupMatrix),
    matches_backup: JSON.stringify(currentMatrix) === JSON.stringify(backupMatrix),
  };
}

function catalogMigrationCrmRecoveryCellValue_(value) {
  return value instanceof Date ? value.toISOString() : value;
}

function catalogMigrationCrmRecoveryDiagnoseRrp() {
  const current = _getCrmSs();
  const backup = catalogMigrationCrmRecoveryBackup_();
  const spec = CRM_CATALOG_RECOVERY_.specs.filter(function (item) {
    return item.sheet === 'РРЦ';
  })[0];
  const currentRange = current.getSheetByName(spec.sheet).getRange(spec.range);
  const backupRange = backup.spreadsheet.getSheetByName(spec.sheet).getRange(spec.range);
  const currentValues = currentRange.getValues();
  const backupValues = backupRange.getValues();
  const currentFormulas = currentRange.getFormulas();
  const backupFormulas = backupRange.getFormulas();
  const differences = [];
  const differenceCounts = {};
  for (let rowIndex = 0; rowIndex < currentValues.length; rowIndex++) {
    for (let columnIndex = 0; columnIndex < currentValues[0].length; columnIndex++) {
      const currentComparable = currentFormulas[rowIndex][columnIndex] ||
        catalogMigrationCrmRecoveryCellValue_(currentValues[rowIndex][columnIndex]);
      const backupComparable = backupFormulas[rowIndex][columnIndex] ||
        catalogMigrationCrmRecoveryCellValue_(backupValues[rowIndex][columnIndex]);
      if (JSON.stringify(currentComparable) === JSON.stringify(backupComparable)) continue;
      const column = String.fromCharCode(65 + columnIndex);
      differenceCounts[column] = (differenceCounts[column] || 0) + 1;
      if (differences.length < 60) {
        differences.push({
          cell: column + (rowIndex + 3),
          current: currentComparable,
          backup: backupComparable,
          current_is_formula: !!currentFormulas[rowIndex][columnIndex],
          backup_is_formula: !!backupFormulas[rowIndex][columnIndex],
        });
      }
    }
  }
  return catalogMigrationCrmRecoveryLog_({
    ok: true,
    action: 'catalog_migration_crm_recovery_diagnose_rrp',
    difference_counts: differenceCounts,
    differences: differences,
    differences_capped: differences.length === 60,
  });
}

function catalogMigrationCrmRecoveryProductNameState_(sheet, row) {
  const range = sheet.getRange(row, 1, 1, 15);
  const values = range.getValues()[0];
  const displays = range.getDisplayValues()[0];
  const formulas = range.getFormulas()[0];
  return {
    sku_A: catalogMigrationCrmRecoveryCellValue_(values[0]),
    short_name_B: catalogMigrationCrmRecoveryCellValue_(values[1]),
    short_name_B_display: displays[1],
    short_name_B_formula: formulas[1],
    full_name_C: catalogMigrationCrmRecoveryCellValue_(values[2]),
    full_name_C_display: displays[2],
    full_name_C_formula: formulas[2],
    formula_columns: formulas.map(function (formula, index) {
      return formula ? String.fromCharCode(65 + index) : '';
    }).filter(Boolean),
  };
}

function catalogMigrationCrmRecoveryDiagnoseProductNames() {
  const current = _getCrmSs();
  const backup = catalogMigrationCrmRecoveryBackup_();
  const currentProducts = current.getSheetByName('Товари');
  const backupProducts = backup.spreadsheet.getSheetByName('Товари');
  const currentRrp = current.getSheetByName('РРЦ');
  const backupRrp = backup.spreadsheet.getSheetByName('РРЦ');
  if (!currentProducts || !backupProducts || !currentRrp || !backupRrp) {
    throw new Error('Required recovery sheet is missing.');
  }
  const rows = [52, 53, 54, 55, 56, 57, 58, 59, 60, 71, 72, 73, 74, 75, 76];
  const details = rows.map(function (row) {
    const currentRrpValues = currentRrp.getRange(row, 1, 1, 2).getDisplayValues()[0];
    const backupRrpValues = backupRrp.getRange(row, 1, 1, 2).getDisplayValues()[0];
    return {
      row: row,
      current_product: catalogMigrationCrmRecoveryProductNameState_(currentProducts, row),
      backup_product: catalogMigrationCrmRecoveryProductNameState_(backupProducts, row),
      current_rrp_A_B: currentRrpValues,
      backup_rrp_A_B: backupRrpValues,
    };
  });
  return catalogMigrationCrmRecoveryLog_({
    ok: true,
    action: 'catalog_migration_crm_recovery_diagnose_product_names',
    rows: details,
  });
}

function catalogMigrationCrmRecoveryRestoreSheet_(targetSheet, sourceSheet, spec) {
  const target = targetSheet.getRange(spec.range);
  const source = sourceSheet.getRange(spec.range);
  const validations = target.getDataValidations();
  const numberFormats = target.getNumberFormats();
  const values = source.getValues();
  const formulas = source.getFormulas();
  const formulaOnly = {};
  spec.formulaOnlyColumns.forEach(function (column) { formulaOnly[column] = true; });
  const manualValues = values.map(function (row, rowIndex) {
    return row.map(function (value, columnIndex) {
      return formulas[rowIndex][columnIndex] || formulaOnly[columnIndex + 1] ? '' : value;
    });
  });
  const writeFormats = numberFormats.map(function (row, rowIndex) {
    return row.map(function (format, columnIndex) {
      const isManual = !formulas[rowIndex][columnIndex] && !formulaOnly[columnIndex + 1];
      return isManual && typeof values[rowIndex][columnIndex] === 'string' ? '@' : format;
    });
  });
  target.clearDataValidations();
  target.clearContent();
  target.setNumberFormats(writeFormats);
  target.setValues(manualValues);
  target.setNumberFormats(numberFormats);
  for (let columnIndex = 0; columnIndex < formulas[0].length; columnIndex++) {
    let start = -1;
    for (let rowIndex = 0; rowIndex <= formulas.length; rowIndex++) {
      const formula = rowIndex < formulas.length ? formulas[rowIndex][columnIndex] : '';
      if (formula && start < 0) start = rowIndex;
      if ((!formula || rowIndex === formulas.length) && start >= 0) {
        const end = rowIndex;
        const run = formulas.slice(start, end).map(function (row) { return [row[columnIndex]]; });
        target.getCell(start + 1, columnIndex + 1).offset(0, 0, run.length, 1).setFormulas(run);
        start = -1;
      }
    }
  }
  target.setDataValidations(validations);
}

function catalogMigrationCrmRecoveryCopyExactCells_(current, sourceSheet, targetSheet, cells) {
  const localSourceSheet = sourceSheet.copyTo(current);
  try {
    cells.forEach(function (a1) {
      const sourceCell = localSourceSheet.getRange(a1);
      if (sourceCell.getFormula()) throw new Error('Expected a literal backup value at РРЦ!' + a1);
      sourceCell.copyTo(
        targetSheet.getRange(a1),
        SpreadsheetApp.CopyPasteType.PASTE_NORMAL,
        false
      );
    });
    SpreadsheetApp.flush();
  } finally {
    current.deleteSheet(localSourceSheet);
    SpreadsheetApp.flush();
  }
}

function catalogMigrationCrmRecoveryRowSnapshot_(sheet, rangeA1) {
  const range = sheet.getRange(rangeA1);
  const values = range.getValues()[0];
  const displays = range.getDisplayValues()[0];
  const formulas = range.getFormulas()[0];
  return values.map(function (value, index) {
    return {
      cell: String.fromCharCode(65 + index) + range.getRow(),
      value: catalogMigrationCrmRecoveryCellValue_(value),
      display: displays[index],
      formula: formulas[index],
    };
  });
}

function catalogMigrationCrmRecoveryPreview() {
  const current = _getCrmSs();
  const backup = catalogMigrationCrmRecoveryBackup_();
  const sheets = CRM_CATALOG_RECOVERY_.specs.map(function (spec) {
    return catalogMigrationCrmRecoverySheetState_(current, backup.spreadsheet, spec);
  });
  return catalogMigrationCrmRecoveryLog_({
    ok: true,
    action: 'catalog_migration_crm_recovery_preview',
    state: sheets.every(function (item) { return item.matches_backup; }) ? 'already_restored' : 'ready',
    source_backup: {
      id: backup.file.getId(),
      name: backup.file.getName(),
      created: backup.created,
      age_ms: backup.age_ms,
    },
    sheets: sheets,
    restore_scope: ['Товари!A3:O220', 'РРЦ!A3:H220'],
  });
}

function catalogMigrationCrmRecoveryApply() {
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(30000)) throw new Error('CRM is busy; retry recovery later.');
  try {
    const current = _getCrmSs();
    const backup = catalogMigrationCrmRecoveryBackup_();
    const before = CRM_CATALOG_RECOVERY_.specs.map(function (spec) {
      return catalogMigrationCrmRecoverySheetState_(current, backup.spreadsheet, spec);
    });
    if (before.every(function (item) { return item.matches_backup; })) {
      return catalogMigrationCrmRecoveryLog_({
        ok: true,
        action: 'catalog_migration_crm_recovery_apply',
        already_restored: true,
        source_backup_id: backup.file.getId(),
      });
    }
    const stamp = Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyyMMdd-HHmmss');
    const damagedBackup = DriveApp.getFileById(current.getId()).makeCopy(current.getName() + ' DAMAGED-PRE-RECOVERY-' + stamp);
    const mismatched = {};
    before.forEach(function (item) { if (!item.matches_backup) mismatched[item.sheet] = true; });
    CRM_CATALOG_RECOVERY_.specs.filter(function (spec) { return !!mismatched[spec.sheet]; }).forEach(function (spec) {
      const targetSheet = current.getSheetByName(spec.sheet);
      const sourceSheet = backup.spreadsheet.getSheetByName(spec.sheet);
      if (!targetSheet || !sourceSheet) throw new Error('Recovery sheet is missing: ' + spec.sheet);
      catalogMigrationCrmRecoveryRestoreSheet_(targetSheet, sourceSheet, spec);
    });
    SpreadsheetApp.flush();
    const after = CRM_CATALOG_RECOVERY_.specs.map(function (spec) {
      return catalogMigrationCrmRecoverySheetState_(current, backup.spreadsheet, spec);
    });
    const integrity = apiIntegrityCheck_();
    const verified = after.every(function (item) { return item.matches_backup; }) &&
      integrity.clean === true && (!integrity.problems || integrity.problems.length === 0);
    if (!verified) throw new Error('Recovery verification failed: ' + JSON.stringify({ sheets: after, integrity: integrity }));
    invalidateDoGetCache_();
    return catalogMigrationCrmRecoveryLog_({
      ok: true,
      action: 'catalog_migration_crm_recovery_apply',
      already_restored: false,
      source_backup_id: backup.file.getId(),
      damaged_backup_id: damagedBackup.getId(),
      sheets: after,
      integrity: integrity,
    });
  } finally {
    lock.releaseLock();
  }
}

function catalogMigrationCrmRecoveryFinish() {
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(30000)) throw new Error('CRM is busy; retry recovery later.');
  try {
    const current = _getCrmSs();
    const backup = catalogMigrationCrmRecoveryBackup_();
    const targetSheet = current.getSheetByName('РРЦ');
    const sourceSheet = backup.spreadsheet.getSheetByName('РРЦ');
    if (!targetSheet || !sourceSheet) throw new Error('Recovery sheet is missing: РРЦ');
    const cells = ['F41', 'F42', 'F45'];
    const expected = {
      F41: '2026-05-23',
      F42: '2026-05-22',
      F45: '2026-05-28',
    };
    cells.forEach(function (a1) {
      const sourceCell = sourceSheet.getRange(a1);
      if (sourceCell.getValue() !== expected[a1]) {
        throw new Error('Backup value changed at РРЦ!' + a1 + '; recovery stopped.');
      }
    });
    const targetSeedFormula = targetSheet.getRange('B3').getFormula();
    const sourceSeedFormula = sourceSheet.getRange('B3').getFormula();
    if (!targetSeedFormula || targetSeedFormula !== sourceSeedFormula) {
      throw new Error('РРЦ!B3 ARRAYFORMULA seed does not match the backup; recovery stopped.');
    }
    const datesNeedCopy = cells.some(function (a1) {
      return targetSheet.getRange(a1).getValue() !== sourceSheet.getRange(a1).getValue();
    });
    const stamp = Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyyMMdd-HHmmss');
    const damagedBackup = DriveApp.getFileById(current.getId()).makeCopy(current.getName() + ' DAMAGED-PRE-RECOVERY-' + stamp);
    if (datesNeedCopy) catalogMigrationCrmRecoveryCopyExactCells_(current, sourceSheet, targetSheet, cells);
    targetSheet.getRange('B4:B220').clearContent();
    SpreadsheetApp.flush();
    Utilities.sleep(2000);
    SpreadsheetApp.flush();
    const after = CRM_CATALOG_RECOVERY_.specs.map(function (spec) {
      return catalogMigrationCrmRecoverySheetState_(current, backup.spreadsheet, spec);
    });
    const integrity = apiIntegrityCheck_();
    const verified = after.every(function (item) { return item.matches_backup; }) &&
      integrity.clean === true && (!integrity.problems || integrity.problems.length === 0);
    if (!verified) {
      throw new Error('Recovery finish verification failed: ' + JSON.stringify({
        sheets: after,
        integrity: integrity,
        current_rrp_row_3: catalogMigrationCrmRecoveryRowSnapshot_(targetSheet, 'A3:H3'),
        backup_rrp_row_3: catalogMigrationCrmRecoveryRowSnapshot_(sourceSheet, 'A3:H3'),
      }));
    }
    invalidateDoGetCache_();
    return catalogMigrationCrmRecoveryLog_({
      ok: true,
      action: 'catalog_migration_crm_recovery_finish',
      source_backup_id: backup.file.getId(),
      damaged_backup_id: damagedBackup.getId(),
      restored_cells: datesNeedCopy ? cells.map(function (a1) { return 'РРЦ!' + a1; }) : [],
      cleared_arrayformula_blockers: 'РРЦ!B4:B220',
      sheets: after,
      integrity: integrity,
    });
  } finally {
    lock.releaseLock();
  }
}

function catalogMigrationCrmRecoveryRestoreManualShortNames() {
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(30000)) throw new Error('CRM is busy; retry recovery later.');
  try {
    const current = _getCrmSs();
    const backup = catalogMigrationCrmRecoveryBackup_();
    const targetProducts = current.getSheetByName('Товари');
    const sourceProducts = backup.spreadsheet.getSheetByName('Товари');
    const targetRrp = current.getSheetByName('РРЦ');
    if (!targetProducts || !sourceProducts || !targetRrp) throw new Error('Required recovery sheet is missing.');

    CRM_CATALOG_RECOVERY_MANUAL_SHORT_NAMES_.forEach(function (item) {
      const targetSku = String(targetProducts.getRange(item.row, 1).getValue() || '').trim();
      const sourceSku = String(sourceProducts.getRange(item.row, 1).getValue() || '').trim();
      const targetCell = targetProducts.getRange(item.row, 2);
      const sourceCell = sourceProducts.getRange(item.row, 2);
      const targetValue = String(targetCell.getValue() || '');
      const sourceValue = String(sourceCell.getValue() || '');
      if (targetSku !== item.sku || sourceSku !== item.sku) {
        throw new Error('Manual short-name SKU/row guard failed at Товари!' + item.row + ': expected ' + item.sku + '.');
      }
      if (!sourceValue || sourceCell.getFormula()) {
        throw new Error('Backup manual short name is not a literal at Товари!B' + item.row + '.');
      }
      if (targetCell.getFormula() || (targetValue && targetValue !== sourceValue)) {
        throw new Error('Unexpected current value at Товари!B' + item.row + '; recovery stopped.');
      }
    });

    const stamp = Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyyMMdd-HHmmss');
    const damagedBackup = DriveApp.getFileById(current.getId()).makeCopy(current.getName() + ' DAMAGED-PRE-RECOVERY-' + stamp);
    catalogMigrationCrmRecoveryCopyExactCells_(
      current,
      sourceProducts,
      targetProducts,
      CRM_CATALOG_RECOVERY_MANUAL_SHORT_NAMES_.map(function (item) { return 'B' + item.row; })
    );
    SpreadsheetApp.flush();
    Utilities.sleep(2000);
    SpreadsheetApp.flush();

    const manualNames = CRM_CATALOG_RECOVERY_MANUAL_SHORT_NAMES_.map(function (item) {
      const targetCell = targetProducts.getRange(item.row, 2);
      const sourceCell = sourceProducts.getRange(item.row, 2);
      const targetValue = String(targetCell.getValue() || '');
      const sourceValue = String(sourceCell.getValue() || '');
      const rrpName = String(targetRrp.getRange(item.row, 2).getValue() || '');
      return {
        row: item.row,
        sku: item.sku,
        product_matches_backup: !targetCell.getFormula() && targetValue === sourceValue,
        rrp_matches_product: rrpName === targetValue,
      };
    });
    const after = CRM_CATALOG_RECOVERY_.specs.map(function (spec) {
      return catalogMigrationCrmRecoverySheetState_(current, backup.spreadsheet, spec);
    });
    const integrity = apiIntegrityCheck_();
    const verified = manualNames.every(function (item) {
      return item.product_matches_backup && item.rrp_matches_product;
    }) && after.every(function (item) { return item.matches_backup; }) &&
      integrity.clean === true && (!integrity.problems || integrity.problems.length === 0);
    if (!verified) {
      throw new Error('Manual short-name recovery verification failed: ' + JSON.stringify({
        manual_names: manualNames,
        sheets: after,
        integrity: integrity,
      }));
    }
    invalidateDoGetCache_();
    return catalogMigrationCrmRecoveryLog_({
      ok: true,
      action: 'catalog_migration_crm_recovery_restore_manual_short_names',
      source_backup_id: backup.file.getId(),
      damaged_backup_id: damagedBackup.getId(),
      restored_cells: CRM_CATALOG_RECOVERY_MANUAL_SHORT_NAMES_.map(function (item) {
        return 'Товари!B' + item.row;
      }),
      manual_names: manualNames,
      sheets: after,
      integrity: integrity,
    });
  } finally {
    lock.releaseLock();
  }
}
