import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, '../Code.gs'), 'utf8');
assert.match(code, /addItem\('Заповнити собівартість передзамовлень', 'initializePreorderCostsMenu'\)/,
  'existing preorder rows have an owner-visible one-time initialization path');
assert.match(code, /function initializeMissingPreorderCosts_\(ss\)[\s\S]*plans\.forEach/,
  'initialization preflights every missing line before the first cost write');
const context = vm.createContext({
  JSON, Math, Number, String, Boolean, Array, Object, RegExp, Date, Error, Set, isFinite,
  Logger: { log() {} }, SpreadsheetApp: {}, Utilities: {}, Session: {}, console
});
vm.runInContext(code + '\nglobalThis.__test={calculatePreorderForecastCost_,apiDecoratePreorderStock_,apiCrmStockProjectionMetrics_,isActualSaleForCost_,isPhysicalStockReservationSale_,apiRecentSalesForUpdate_,normalizeOpenCartSku_,mapOpenCartOrderStatus_,_getCrmSalesRows,orderNeedsPreorderCostRecovery_,resetMemo_};', context, { filename: 'Code.gs' });

const incomingRows = [];
function purchase({ lot, sku = 'SKU-MIX', qty, prro = 0, mgmt = 0, status }) {
  const row = Array(18).fill('');
  row[0] = lot; row[4] = sku; row[7] = qty; row[12] = prro; row[15] = mgmt; row[16] = status;
  return row;
}
incomingRows.push(purchase({ lot: 'LOT-IN-1', qty: 2, prro: 200, mgmt: 220, status: 'В дорозі' }));
const purchases = {
  getLastRow: () => incomingRows.length + 2,
  getRange: (row, column, rows, columns) => ({
    getValues: () => incomingRows.slice(row - 3, row - 3 + rows).map((source) => source.slice(column - 1, column - 1 + columns))
  })
};
const ss = { getSheetByName: (name) => name === 'Закупки' ? purchases : null };

context.getFifoCostBatches_ = () => [{ row: 3, lotId: 'LOT-UA-1', qty: 1, prroUnit: 100, mgmtUnit: 120 }];
context.getConsumedQtyBeforeSale_ = () => 0;
context.getWriteOffQtyBeforeSale_ = () => 0;
context.inventoryMigrationOutQtyBeforeSale_ = () => 0;
context.apiSkuStockMetrics_ = () => ({ 'SKU-MIX': { max_buy_price: 300 } });
context.apiReadRrcMap_ = () => ({ 'SKU-RRP': { rrc: 400 } });

const mixed = context.__test.calculatePreorderForecastCost_(ss, 'SKU-MIX', 4, 10, new Date('2026-08-27'), {});
assert.deepEqual(JSON.parse(JSON.stringify(mixed)), {
  prroUnit: 200,
  mgmtUnit: 215,
  method: 'Прогноз передзамовлення',
  audit: 'reserved_before=0; FIFO LOT-UA-1: 1 x 100/120; їде LOT-IN-1: 2 x 200/220; гранична закупка: 1 x 300',
  actual_qty: 1,
  incoming_qty: 2,
  fallback_qty: 1,
  forecast_qty: 3
}, 'mixed preorder combines landed FIFO, incoming FIFO, and max-buy fallback');

incomingRows.length = 0;
context.getFifoCostBatches_ = () => [];
context.apiSkuStockMetrics_ = () => ({});
const rrpFallback = context.__test.calculatePreorderForecastCost_(ss, 'SKU-RRP', 2, 11, new Date('2026-08-27'), {});
assert.equal(rrpFallback.prroUnit, 300);
assert.equal(rrpFallback.mgmtUnit, 300);
assert.equal(rrpFallback.fallback_qty, 2);
assert.match(rrpFallback.audit, /75% РРЦ 400: 2 x 300/);

context.getFifoCostBatches_ = () => [{ row: 4, lotId: 'LOT-UA-2', qty: 2, prroUnit: 90, mgmtUnit: 110 }];
context.apiReadRrcMap_ = () => ({});
const landedOnly = context.__test.calculatePreorderForecastCost_(ss, 'SKU-NO-RRP', 2, 12, new Date('2026-08-27'), {});
assert.equal(landedOnly.method, 'FIFO (резерв передзамовлення)');
assert.equal(landedOnly.prroUnit, 90, 'fully landed stock does not require a fallback price');
assert.equal(landedOnly.forecast_qty, 0);

function saleRow({ order = '', sku, qty, status, payment = 'Оплачено', method = '' }) {
  const row = Array(32).fill('');
  row[0] = order; row[5] = sku; row[7] = qty; row[22] = payment; row[23] = status; row[29] = method;
  return { values: row };
}
context._getCrmSalesRowEntries = () => [
  saleRow({ sku: 'SKU-STOCK', qty: 3, status: 'Передзамовлення' }),
  saleRow({ sku: 'SKU-STOCK', qty: 2, status: 'Нове' }),
  saleRow({ sku: 'SKU-STOCK', qty: 7, status: 'Відправлено' }),
  saleRow({ sku: 'SKU-STOCK', qty: 277, status: 'Отримано' }),
  saleRow({ sku: 'SKU-STOCK', qty: 11, status: '' })
];
const decorated = [{ sku: 'SKU-STOCK', stock: -2, expected: 4, incoming_stock: 4 }];
context.__test.apiDecoratePreorderStock_(decorated);
assert.deepEqual(JSON.parse(JSON.stringify(decorated[0])), {
  sku: 'SKU-STOCK', stock: 0, expected: 4, incoming_stock: 4, stock_raw: -2,
  reserved_total: 5, regular_reserved: 2, preorder_reserved: 3, physical_stock: 3,
  current_shortfall: 2, projected_available_raw: 2, projected_stock: 2,
  projected_deficit: 0, preorder_deficit: 2
}, 'negative free stock is separated from incoming stock, physical stock, and the post-arrival projection');
const eb03 = [{ sku: 'OP-JP-EB03-BST', stock: -11, expected: 12, incoming_stock: 12 }];
context._getCrmSalesRowEntries = () => [saleRow({ sku: 'OP-JP-EB03-BST', qty: 14, status: 'Нове' })];
context.__test.apiDecoratePreorderStock_(eb03);
assert.deepEqual(JSON.parse(JSON.stringify(eb03[0])), {
  sku: 'OP-JP-EB03-BST', stock: 0, expected: 12, incoming_stock: 12, stock_raw: -11,
  reserved_total: 14, regular_reserved: 14, preorder_reserved: 0, physical_stock: 3,
  current_shortfall: 11, projected_available_raw: 1, projected_stock: 1,
  projected_deficit: 0, preorder_deficit: 11
}, 'EB03-like data reports three physical units and no deficit after the incoming shipment');
const stockProjectionRow = Array(20).fill('');
stockProjectionRow[0] = 'OP-JP-EB03-BST';
stockProjectionRow[7] = -11;
stockProjectionRow[16] = 12;
stockProjectionRow[18] = 14;
stockProjectionRow[19] = -2;
context._getCrmSs = () => ({
  getSheetByName: (name) => name === 'Склад' ? {
    getLastRow: () => 3,
    getRange: () => ({ getValues: () => [stockProjectionRow] })
  } : null
});
assert.deepEqual(JSON.parse(JSON.stringify(context.__test.apiCrmStockProjectionMetrics_()['OP-JP-EB03-BST'])), {
  stock: -11, expected: 12, incoming_stock: 12, preorder_reserved_sheet: 14,
  incoming_after_preorder: -2, projected_available: 1
}, 'the API reads raw incoming Q and derives the post-arrival projection instead of relabeling T as incoming');
assert.equal(context.__test.isPhysicalStockReservationSale_(saleRow({ sku: 'SKU-STOCK', qty: 1, status: 'В обробці' }).values), true,
  'a processing order remains on the shelf and is a physical-stock reservation');
assert.equal(context.__test.isPhysicalStockReservationSale_(saleRow({ sku: 'SKU-STOCK', qty: 1, status: 'Відправлено' }).values), false,
  'a shipped order never returns units to physical stock');
assert.equal(context.__test.isPhysicalStockReservationSale_(saleRow({ sku: 'SKU-STOCK', qty: 1, status: 'Отримано' }).values), false,
  'a completed historic sale never returns units to physical stock');
assert.equal(context.__test.isPhysicalStockReservationSale_(saleRow({ sku: 'SKU-STOCK', qty: 1, status: '' }).values), false,
  'payment without an active fulfilment status is not a physical-stock reservation');

assert.equal(context.__test.isActualSaleForCost_(saleRow({ sku: 'SKU-STOCK', qty: 1, status: 'Відправлено', method: 'Прогноз передзамовлення' }).values), false,
  'forecasted cost remains outside actual reporting until FIFO reconciliation');
assert.equal(context.__test.isActualSaleForCost_(saleRow({ sku: 'SKU-STOCK', qty: 1, status: 'Відправлено', method: 'FIFO (передзамовлення, звірено)' }).values), true,
  'reconciled preorder enters actual reporting');

context._getCrmSalesRowEntries = () => [
  saleRow({ order: 'MIXED-PRE', sku: 'SKU-IN-STOCK', qty: 1, status: 'Нове', method: 'FIFO (резерв передзамовлення)' }),
  saleRow({ order: 'MIXED-PRE', sku: 'SKU-INCOMING', qty: 1, status: 'Передзамовлення', method: 'Прогноз передзамовлення' }),
  saleRow({ order: 'REGULAR', sku: 'SKU-REGULAR', qty: 1, status: 'Нове', method: 'FIFO' })
];
context.__test.resetMemo_();
const actualRows = context.__test._getCrmSalesRows();
assert.equal(actualRows.length, 1, 'one preorder line excludes every line of the mixed order from actual reporting');
assert.equal(actualRows[0][0], 'REGULAR');
assert.equal(context.__test.normalizeOpenCartSku_('PKM-JP-ABYSS-BST'), 'PKM-JP-ABYE-BST');
assert.equal(context.__test.normalizeOpenCartSku_('PKM-JP-ABYSS-BBX'), 'PKM-JP-ABYE-BBX');
assert.equal(context.__test.mapOpenCartOrderStatus_('Очікування товару'), 'Передзамовлення',
  'the OpenCart waiting-for-stock status remains a preorder in CRM');

assert.equal(context.__test.orderNeedsPreorderCostRecovery_([{ values: saleRow({ sku: 'OP-JP-MBX-ST', qty: 1, status: 'Відправлено', method: 'FIFO' }).values }], 'Відправлено'), false,
  'a TTN-only Mystery Box edit never starts global preorder recovery');
assert.equal(context.__test.orderNeedsPreorderCostRecovery_([{ values: saleRow({ sku: 'SKU-PRE', qty: 1, status: 'Передзамовлення', method: 'Прогноз передзамовлення' }).values }], 'Відправлено'), true,
  'a transitioned preorder remains eligible for its bounded cost recovery');
assert.equal(context.__test.orderNeedsPreorderCostRecovery_([{ values: saleRow({ sku: 'SKU-NEW', qty: 1, status: 'Нове', method: 'FIFO' }).values }], 'Передзамовлення'), true,
  'a newly marked preorder keeps the cost-recovery path');

const recentHeaders = [
  'Номер замовлення / операції', 'Дата продажу', 'Сума продажу', 'Управлінська собівартість продажу',
  'Пакування', 'Доставка за рахунок магазину', 'Статус оплати', 'Статус замовлення', 'ТТН', 'Пошта',
  'Примітка', 'Тип оплати', 'Паковання', 'Метод собівартості'
];
const recentRow = (values) => recentHeaders.map((header) => values[header] ?? '');
context._getCrmSs = () => ({ getSheetByName: () => ({}) });
context.apiRecentTable_ = () => ({
  headerRow: 2,
  headers: recentHeaders,
  rows: [
    recentRow({ 'Номер замовлення / операції': 'PRE-OLD', 'Дата продажу': new Date('2026-08-26'), 'Сума продажу': 1000,
      'Управлінська собівартість продажу': 600, 'Статус оплати': 'Не оплачено', 'Статус замовлення': 'Передзамовлення',
      'Метод собівартості': 'Прогноз передзамовлення' }),
    recentRow({ 'Номер замовлення / операції': 'REG-NEW', 'Дата продажу': new Date('2026-08-27'), 'Сума продажу': 500,
      'Управлінська собівартість продажу': 200, 'Статус оплати': 'Оплачено', 'Статус замовлення': 'Нове',
      'Метод собівартості': 'FIFO' })
  ]
});
context.apiRecentCol_ = (headers, name) => headers.indexOf(name);
context.apiDate_ = (value) => value.toISOString().slice(0, 10);
const preorderTab = JSON.parse(JSON.stringify(context.__test.apiRecentSalesForUpdate_({ kind: 'preorder', limit: 1 })));
assert.equal(preorderTab.rows.length, 1);
assert.equal(preorderTab.rows[0].order_id, 'PRE-OLD', 'order-kind filtering happens before the recent-row limit');
assert.equal(preorderTab.rows[0].cost_state, 'forecast');
const regularTab = JSON.parse(JSON.stringify(context.__test.apiRecentSalesForUpdate_({ kind: 'regular', limit: 1 })));
assert.equal(regularTab.rows[0].order_id, 'REG-NEW');
assert.equal(regularTab.rows[0].cost_state, 'actual');

console.log('Preorder forecast cascade, reporting gate, and stock deficit tests passed');
