import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, '../Code.gs'), 'utf8');
const dashboard = fs.readFileSync(path.resolve(here, '../../../dashboard/booster-dashboard.html'), 'utf8');
const headers = [
  'ID партії','ZenMarket Order №','Трек-номер','Дата доставки в Україну','SKU','Назва товару','Формат',
  'Кількість одиниць','Вартість лоту, грн','Доставка / комісії по Японії, грн','Доставка UA, грн',
  'Собівартість закупки партії / ПРРО','Собівартість 1 од. / ПРРО','Кредитне обслуговування',
  'Управлінська собівартість партії','Управлінська собівартість 1 од.','Статус','Примітка',
  'ZenMarket URL','Постачальник','Дата створення','Дата відправки в Україну'
];
const rows = [
  ['LOT-0001','','','', 'SKU-A','Назва A','',2,'','','','','','','',12.34,'Замовлено','','','','2026-09-01',''],
  ['LOT-0002','','LX123','', 'SKU-A','Назва A','',1,'','','','','','','',15.67,'На складі UA','','','','2026-09-02','2026-09-03'],
  ['LOT-0003','','','', 'SKU-B','Назва B','',3,'','','','','','','','','Продано','','','','','']
];
const values = [['Закупки'], headers, ...rows];
const sheet = {
  getLastRow: () => values.length,
  getLastColumn: () => headers.length,
  getRange: (row, col, height, width) => ({
    getValues: () => values.slice(row - 1, row - 1 + height).map((line) => line.slice(col - 1, col - 1 + width)),
    getDisplayValues: () => values.slice(row - 1, row - 1 + height).map((line) => line.slice(col - 1, col - 1 + width).map(String)),
    getValue: () => values[row - 1][col - 1],
    setValue: (value) => { values[row - 1][col - 1] = value; }
  })
};
const spreadsheet = { getSheetByName: (name) => name === 'Закупки' ? sheet : null, getSpreadsheetTimeZone: () => 'Europe/Moscow' };
const context = vm.createContext({ JSON, Math, Number, String, Boolean, Array, Object, RegExp, Date, Error, Set, isFinite, console,
  Utilities: { formatDate: (value, timeZone) => new Intl.DateTimeFormat('sv-SE', { timeZone, year:'numeric', month:'2-digit', day:'2-digit' }).format(value) },
  Session: { getScriptTimeZone: () => 'Europe/Kyiv' }
});
vm.runInContext(code + '\nglobalThis.__crm016 = { apiPurchaseRegister_, apiSetPurchaseShipmentDate_, apiAddPurchase_, apiUpdatePurchase_ };', context, { filename: 'Code.gs' });
context._getCrmSs = () => spreadsheet;
context.invalidateDoGetCache_ = () => {};

let result = context.__crm016.apiPurchaseRegister_();
assert.equal(result.rows.length, 3, 'archive and active lots are both returned');
assert.equal(result.rows.filter((row) => row.sku === 'SKU-A').length, 2, 'the same SKU in two lots stays on two rows');
assert.equal(result.rows[0].unit_cost, 12.34, 'formula-derived unit cost is used');
assert.equal(result.rows[2].unit_cost, null, 'missing cost is not shown as zero');
assert.equal(result.rows[1].shipped_date, '2026-09-03');
rows[0][1] = 'yskh374'; rows[1][1] = 'yskh374';
rows[0][14] = 812.66; rows[1][14] = 812.66;
result = context.__crm016.apiPurchaseRegister_();
assert.equal(result.rows[0].order_ref, 'yskh374');
assert.equal(result.rows[0].total_cost, 812.66);
const groupSource = dashboard.match(/function purchaseGroups_\(items\) \{[\s\S]*?(?=\nfunction togglePurchaseGroup_)/)?.[0];
assert.ok(groupSource, 'purchase grouping is present in dashboard');
const grouped = new Function('items', groupSource + '\nreturn purchaseGroups_(items);')(result.rows);
assert.equal(grouped.length, 2, 'two adjacent technical lots with one reference form one visible purchase');
assert.equal(grouped[0].total_cost, 1625.32);
assert.equal(grouped[0].items.length, 2);
assert.equal(grouped[0].active, true, 'mixed ordered/received purchase remains active');
assert.match(code, /maximum 10 SKU positions/, 'purchase API rejects overflow instead of truncating');
assert.match(code, /maximum 25 SKU positions/, 'mass status update accepts up to 25 positions');
assert.match(dashboard, /const PURCHASE_BATCH_LIMIT = 25;/);
assert.match(dashboard, /const PURCHASE_CREATE_ITEM_LIMIT = 10;/);
rows[0][20] = new Date('2026-01-01T21:00:00Z'); // Jan 2 in Sheet timezone, Jan 1 in script timezone.
assert.equal(context.__crm016.apiPurchaseRegister_().rows[0].purchase_date, '2026-01-02');
rows[0][20] = '2026-09-01';

assert.throws(() => context.__crm016.apiSetPurchaseShipmentDate_(spreadsheet, {
  lot_id:'LOT-0001', shipped_date:'2026-02-31', expected_shipped_date:''
}), /invalid/);
assert.throws(() => context.__crm016.apiSetPurchaseShipmentDate_(spreadsheet, {
  lot_id:'LOT-0001', shipped_date:'2026-09-04', expected_shipped_date:'2026-09-02'
}), /changed/);
result = context.__crm016.apiSetPurchaseShipmentDate_(spreadsheet, {
  lot_id:'LOT-0001', shipped_date:'2026-09-04', expected_shipped_date:''
});
assert.equal(result.ok, true);
assert.equal(context.__crm016.apiPurchaseRegister_().rows[0].shipped_date, '2026-09-04');
headers.pop();
assert.equal(context.__crm016.apiPurchaseRegister_().shipment_date_available, false);
assert.throws(() => context.__crm016.apiSetPurchaseShipmentDate_(spreadsheet, {
  lot_id:'LOT-0001', shipped_date:'2026-09-05', expected_shipped_date:'2026-09-04'
}), /Додайте колонку/);

context.resetMemoForMutation_ = () => {};
context.invalidateDoGetCache_ = () => {};
context.crmNextAppendRow_ = () => 3;
context.generateLotIds_ = count => Array.from({length:count}, (_, index) => 'LOT-' + String(index + 1).padStart(4,'0'));
context.parseSku_ = value => String(value || '').trim();
context.getCurrencyRate_ = () => 4;
const writes = [];
const batchSheet = {
  getLastRow: () => 27,
  getRange: (row,col,height,width) => ({
    setValues: values => writes.push({row,col,height,width,values}),
    setValue: value => writes.push({row,col,value}),
    getValues: () => Array.from({length:25}, (_, index) => {
      const line = Array(18).fill(''); line[0] = 'LOT-' + String(index + 1).padStart(4,'0'); return line;
    })
  })
};
const batchSs = { getSheetByName: name => name === 'Закупки' ? batchSheet : null };
const items = Array.from({length:10}, (_, index) => ({sku:'SKU-'+index,qty:1}));
assert.match(context.__crm016.apiAddPurchase_(batchSs,{order_ref:'yskh-test',total_cost:100,items}).error || '', /^$/);
assert.equal(writes.length,4,'ten SKU positions use four bounded batch writes');
assert.deepEqual(writes.map(write => write.height),[10,10,10,10]);
assert.match(context.__crm016.apiAddPurchase_(batchSs,{order_ref:'yskh-test',total_cost:100,items:[...items,items[0]]}).error,/maximum 10/);
assert.equal(writes.length,4,'overflow does not write a partial purchase');
writes.length = 0;
const lots = Array.from({length:25}, (_, index) => ({lot_id:'LOT-' + String(index + 1).padStart(4,'0')}));
assert.equal(context.__crm016.apiUpdatePurchase_(batchSs,{lots,status:'В дорозі'}).rows_updated,25);
assert.equal(writes.length,25);
writes.length = 0;
assert.match(context.__crm016.apiUpdatePurchase_(batchSs,{lots:[...lots,lots[0]],status:'В дорозі'}).error,/maximum 25/);
assert.equal(writes.length,0,'overflow does not update any status');
console.log('CRM-016 purchase register and shipment date guards passed');
