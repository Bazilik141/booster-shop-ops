/**
 * CRM-002 — support "На складі UA" in FIFO and lot auto-status.
 *
 * Deployment:
 * 1. In the bound Booster Shop CRM Apps Script project, replace the existing
 *    getFifoCostBatches_() and updateLotStatuses() functions with these
 *    versions.
 * 2. Save the project.
 * 3. Run updateLotStatuses() once and review its result.
 *
 * The live sheet formulas and Apps_Script_код source-copy were already updated
 * on 2026-06-27. This file is the reviewable bound-script replacement.
 */

function getFifoCostBatches_(ss, sku, saleDate) {
  const purchases = ss.getSheetByName('Закупки');
  if (!purchases) return [];
  const saleSort = dateSortValue_(saleDate);
  const allowed = {
    'На складі UA': true,
    'На складі': true,
    'Частково продано': true,
    'Продано': true
  };
  const lastRow = Math.max(purchases.getLastRow(), 3);
  const values = purchases.getRange(3, 1, lastRow - 2, 18).getValues();
  const batches = [];
  values.forEach(function(row, index) {
    if (String(row[4] || '').trim() !== sku) return;
    if (!allowed[String(row[16] || '').trim()]) return;
    const batchSort = dateSortValue_(row[3]);
    if (saleSort && batchSort && batchSort > saleSort) return;
    if (!batchSort) {
      Logger.log(
        'FIFO warning: lot ' + row[0] + ' (' + row[4] +
        ') has no delivery date — included as earliest'
      );
    }
    const qty = num_(row[7]);
    if (qty <= 0) return;
    const prroUnit = num_(row[12]) || (qty ? num_(row[11]) / qty : 0);
    const mgmtUnit = num_(row[15]) || (qty ? num_(row[14]) / qty : prroUnit);
    batches.push({
      row: index + 3,
      lotId: String(row[0] || ('row' + (index + 3))),
      qty: qty,
      prroUnit: prroUnit,
      mgmtUnit: mgmtUnit || prroUnit,
      sort: batchSort || index + 1
    });
  });
  batches.sort(function(a, b) {
    return a.sort - b.sort || a.row - b.row;
  });
  return batches;
}

function updateLotStatuses() {
  const ss = _getCrmSs();
  const purchases = ss.getSheetByName('Закупки');
  if (!purchases) throw new Error('Не знайдено вкладку Закупки.');
  const updatable = {
    'На складі UA': true,
    'На складі': true,
    'Частково продано': true
  };
  const allowedForFifo = {
    'На складі UA': true,
    'На складі': true,
    'Частково продано': true,
    'Продано': true
  };
  const lastRow = Math.max(purchases.getLastRow(), 3);
  const values = purchases.getRange(3, 1, lastRow - 2, 17).getValues();
  const soldBySku = getSoldQtyBySkuForLotStatuses_(ss);
  const lotsBySku = {};
  values.forEach(function(row, index) {
    const sku = String(row[4] || '').trim();
    const status = String(row[16] || '').trim();
    if (!sku || !allowedForFifo[status]) return;
    const qty = num_(row[7]);
    if (qty <= 0) return;
    if (!lotsBySku[sku]) lotsBySku[sku] = [];
    lotsBySku[sku].push({
      rowNumber: index + 3,
      qty: qty,
      status: status,
      sort: dateSortValue_(row[3]) || 9000000000000 + index
    });
  });
  const changes = [];
  Object.keys(lotsBySku).forEach(function(sku) {
    const lots = lotsBySku[sku].sort(function(a, b) {
      return a.sort - b.sort || a.rowNumber - b.rowNumber;
    });
    let remainingSold = num_(soldBySku[sku]);
    lots.forEach(function(lot) {
      const soldFromLot = Math.min(Math.max(remainingSold, 0), lot.qty);
      remainingSold = round2_(remainingSold - soldFromLot);
      let nextStatus =
        lot.status === 'На складі UA' ? 'На складі UA' : 'На складі';
      if (soldFromLot >= lot.qty) nextStatus = 'Продано';
      else if (soldFromLot > 0) nextStatus = 'Частково продано';
      if (updatable[lot.status] && lot.status !== nextStatus) {
        changes.push({ row: lot.rowNumber, status: nextStatus });
      }
    });
  });
  changes.forEach(function(change) {
    purchases.getRange(change.row, 17).setValue(change.status);
  });
  updateSkuCurrentCost_(ss);
  const message = 'Статуси лотів оновлено: ' + changes.length + ' змін.';
  try {
    SpreadsheetApp.getActive().toast(message, 'Booster CRM', 5);
  } catch (e) {
    Logger.log(message);
  }
  return {
    checkedSku: Object.keys(lotsBySku).length,
    changed: changes.length
  };
}
