import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, '../Code.gs'), 'utf8');
const makeSale = (order, sku, date = '2026-09-20', status = 'Нове') => {
  const row = Array(34).fill('');
  row[0] = order; row[1] = 'Вручну'; row[2] = date; row[5] = sku;
  row[6] = sku; row[7] = 1; row[8] = 100; row[9] = 10;
  row[15] = 4; row[19] = 6; row[22] = 'Не оплачено'; row[23] = status;
  return row;
};
function makeRange(sheet, row, col, height = 1, width = 1) {
  const index = offset => row + offset - sheet.firstRow;
  const getValues = () => Array.from({length:height}, (_, r) => Array.from({length:width}, (_, c) => (sheet.rows[index(r)] || [])[col - 1 + c] ?? ''));
  return {
    getValues, getValue: () => getValues()[0][0],
    getFormulas: () => Array.from({length:height}, (_, r) => Array.from({length:width}, (_, c) => {
      const absoluteColumn = col + c;
      return sheet.name === 'Продажі' && ((absoluteColumn >= 17 && absoluteColumn <= 19) || (sheet.manualFormulaColumn === absoluteColumn && row + r === (sheet.manualFormulaRow || 4))) ? '=formula' : '';
    })),
    setValues(values) { values.forEach((line, r) => { const at=index(r); while(sheet.rows.length<=at) sheet.rows.push([]); line.forEach((value,c) => { sheet.rows[at][col - 1 + c] = value; }); }); },
    setValue(value) { this.setValues([[value]]); },
    clearContent() { this.setValues(Array.from({length:height}, () => Array(width).fill(''))); },
    getCell(r,c) { return makeRange(sheet,row+r-1,col+c-1); }
  };
}
const sales = { name:'Продажі', firstRow:3, rows:[makeSale('ORDER-1','SKU-A'), makeSale('ORDER-1','SKU-B')],
  getLastRow() { return this.rows.length + 2; },
  getRange(row, col, height, width) { return makeRange(this,row,col,height,width); }
};
const lot = Array(17).fill(''); lot[3] = '2026-09-01'; lot[4] = 'SKU-A'; lot[7] = 1; lot[16] = 'Продано';
const purchases = { name:'Закупки', firstRow:3, rows:[lot],
  getLastRow() { return this.rows.length + 2; },
  getRange(row, col, height, width) { return makeRange(this,row,col,height,width); }
};
const sheets = { Продажі:sales, Закупки:purchases };
const spreadsheet = { getSheetByName: name => sheets[name] || null,
  insertSheet(name) { const sheet={name,firstRow:1,rows:[],getLastRow() { return this.rows.length; },getRange(row,col,height,width) { return makeRange(this,row,col,height,width); },setFrozenRows() {}}; sheets[name]=sheet; return sheet; }
};
const context = vm.createContext({ JSON, Math, Number, String, Boolean, Array, Object, RegExp, Date, Error, Set, isFinite, console,
  Utilities: { DigestAlgorithm:{SHA_256:'SHA_256'}, Charset:{UTF_8:'UTF_8'}, computeDigest: (_algorithm, value) => Array.from(value).slice(0, 32).map(char => char.charCodeAt(0)) },
  SpreadsheetApp: { flush() {} }
});
vm.runInContext(code + '\nglobalThis.__crm016 = { crm016OrderLinePlan_, apiOrderLineRemovePreview_, apiRemoveOrderLine_, crm016RemovedOrderSkus_, normalizeOpenCartSku_ };', context, { filename:'Code.gs' });
context._getCrmSs = () => spreadsheet;
context.crmRowsMatching_ = (sheet, start, _width, predicate) => sheet.rows.map((values, index) => ({ row:start + index, values })).filter(item => predicate(item.values));
context.getSoldQtyBySkuForLotStatuses_ = () => Object.fromEntries(['SKU-A','SKU-B'].map(sku => [sku, sales.rows.reduce((sum, row) => sum + (row[5] === sku && context.isStockReservationSale_(row) ? Number(row[7]) : 0), 0)]));

const preview = () => context.__crm016.apiOrderLineRemovePreview_({order_id:'ORDER-1',crm_row:3});
let result = preview();
assert.equal(result.eligible, true, 'ordinary open order is eligible');
assert.equal(result.sku, 'SKU-A');
assert.equal(result.crm_row, 3);
assert.equal(context.__crm016.normalizeOpenCartSku_('ACC-001-BPJP'), 'ACC-001-BPEN', 'old OpenCart article resolves to new CRM SKU');

sales.rows[1][23] = 'В обробці';
assert.match(preview().blockers.join(' '), /різні статуси/);
sales.rows[1][23] = 'Нове';

sales.rows[0][23] = 'Отримано';
assert.match(preview().blockers.join(' '), /Статус/);
sales.rows[0][23] = 'Нове';

sales.rows[0][22] = 'Скасовано';
assert.match(preview().blockers.join(' '), /резерві складу/);
sales.rows[0][22] = 'Не оплачено';

sales.rows[1][33] = 'fiscal-receipt';
assert.match(preview().blockers.join(' '), /фіскальний/);
sales.rows[1][33] = '';

sales.manualFormulaColumn = 16;
assert.match(preview().blockers.join(' '), /містить формулу/);
delete sales.manualFormulaColumn;
sales.manualFormulaColumn = 10; sales.manualFormulaRow = 3;
assert.match(preview().blockers.join(' '), /містить формулу/, 'target manual formula blocks correction');
delete sales.manualFormulaColumn; delete sales.manualFormulaRow;

sales.rows.push(makeSale('ORDER-2','SKU-A','2026-09-21'));
assert.match(preview().blockers.join(' '), /пізніші продажі/);
sales.rows[0][23] = 'Передзамовлення'; sales.rows[1][23] = 'Передзамовлення';
sales.rows[0][29] = 'FIFO (резерв передзамовлення)';
sales.rows[0][30] = 'reserved_before=1; LOT-0117: 1 x 100/100';
sales.rows[2][30] = 'before=2; LOT-0117: 1 x 100/100';
lot[0] = 'LOT-0117'; lot[7] = 3;
assert.equal(preview().eligible, true, 'a preorder can release a reservation when later sales use the same sole arrived lot: ' + JSON.stringify(preview().blockers));
const secondLot = lot.slice(); secondLot[0] = 'LOT-0118'; purchases.rows.push(secondLot);
assert.match(preview().blockers.join(' '), /пізніші продажі/, 'multiple arrived lots still require FIFO recalculation');
purchases.rows.pop();
sales.rows[2][30] = '';
assert.match(preview().blockers.join(' '), /пізніші продажі/, 'a later sale without a lot audit remains blocked');
sales.rows[0][23] = 'Нове'; sales.rows[1][23] = 'Нове';
sales.rows[0][29] = ''; sales.rows[0][30] = ''; lot[0] = ''; lot[7] = 1;
sales.rows.pop();

sheets['Використання_компонентів'] = { rows:[['','','ORDER-1']] };
assert.match(preview().blockers.join(' '), /Використання_компонентів/);
delete sheets['Використання_компонентів'];

result = preview();
assert.equal(result.eligible, true);
assert.throws(() => context.__crm016.apiRemoveOrderLine_(spreadsheet, {
  order_id:'ORDER-1', crm_row:3, request_id:'crm016-test-request', reason:'Помилка обліку', expected_fingerprint:'stale'
}), /Order changed/, 'stale preview is rejected before writing');
assert.equal(sales.rows[0][0], 'ORDER-1');
context.apiIntegrityCheck_ = () => ({clean:true});
context.resetMemoForMutation_ = () => {};
context.updateSkuCurrentCost_ = () => {};
context.clearOrdersCache_ = () => {};
context.invalidateDoGetCache_ = () => {};
result = context.__crm016.apiRemoveOrderLine_(spreadsheet, {
  order_id:'ORDER-1', crm_row:3, request_id:'crm016-test-request', reason:'Помилка обліку', expected_fingerprint:result.expected_fingerprint
});
assert.equal(result.ok, true);
assert.equal(sales.rows[0][0], '', 'the deleted line is cleared in place');
assert.equal(sales.rows[1][9], 20, 'the order discount is retained on the surviving line');
assert.equal(sales.rows[1][15], 8, 'packaging cost is retained on the surviving line');
assert.equal(sales.rows[1][19], 12, 'shop delivery is retained on the surviving line');
assert.equal(purchases.rows[0][16], 'На складі', 'a fully sold lot reopens after the sale is removed');
assert.equal(result.lot_statuses_changed, 1);
assert.equal(sheets['Коригування_Рядків_Замовлень'].rows.length, 2, 'a durable audit row is written');
assert.equal(sheets['Коригування_Рядків_Замовлень'].rows[1][11], 'APPLIED', 'audit is complete only after verification');
assert.equal(context.__crm016.crm016RemovedOrderSkus_(spreadsheet,'ORDER-1')['SKU-A'], true, 'OpenCart sync exclusion is recorded');
assert.equal(context.__crm016.apiRemoveOrderLine_(spreadsheet, {request_id:'crm016-test-request',order_id:'ORDER-1',crm_row:3}).already_applied, true);
sheets['Коригування_Рядків_Замовлень'].rows[1][11] = 'PENDING';
assert.equal(context.__crm016.crm016RemovedOrderSkus_(spreadsheet,'ORDER-1')['SKU-A'], undefined, 'incomplete audit does not suppress OpenCart lines');
assert.throws(() => context.__crm016.apiRemoveOrderLine_(spreadsheet, {request_id:'crm016-test-request',order_id:'ORDER-1',crm_row:3}), /incomplete/, 'incomplete retry requests manual reconciliation');
console.log('CRM-016 order-line guard matrix passed');
