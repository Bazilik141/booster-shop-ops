import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync(new URL('../Code.gs', import.meta.url), 'utf8');
const dashboard = fs.readFileSync(new URL('../../../dashboard/booster-dashboard.html', import.meta.url), 'utf8');

function functionSource(name) {
  const marker = `function ${name}`;
  const start = source.indexOf(marker);
  assert.notEqual(start, -1, `${name} declaration is missing`);
  const open = source.indexOf('{', start);
  let depth = 0;
  for (let index = open; index < source.length; index += 1) {
    if (source[index] === '{') depth += 1;
    if (source[index] === '}') {
      depth -= 1;
      if (depth === 0) return source.slice(start, index + 1);
    }
  }
  throw new Error(`${name} declaration is incomplete`);
}

function financeFactory() {
  return new Function(`
    function round2_(value){return Math.round((Number(value)+Number.EPSILON)*100)/100;}
    function dateSortValue_(value){return value instanceof Date?value.getTime():new Date(value).getTime();}
    ${functionSource('crm011FinanceNumber_')}
    ${functionSource('crm011FinanceWriteoffsForPeriod_')}
    ${functionSource('crm011FinanceOperatingExpenses_')}
    ${functionSource('crm011FinancePeriod_')}
    return {crm011FinancePeriod_};
  `)();
}

const bounds = { start: new Date('2026-09-01T00:00:00Z'), end: new Date('2026-10-01T00:00:00Z') };
const baseLines = [
  {row_number:3,order_id:'A-1',date_ms:new Date('2026-09-03T00:00:00Z').getTime(),units:1,revenue:100,cogs:40,packaging:5,acquiring:2,nova_pay:1,marketplace_fee:0,delivery:2,sheet_net:50},
  {row_number:4,order_id:'A-2',date_ms:new Date('2026-09-07T00:00:00Z').getTime(),units:1,revenue:200,cogs:80,packaging:5,acquiring:3,nova_pay:0,marketplace_fee:2,delivery:10,sheet_net:100},
  {row_number:5,order_id:'NO-ID',date_ms:new Date('2026-09-08T00:00:00Z').getTime(),units:1,revenue:50,cogs:20,packaging:2,acquiring:1,nova_pay:0,marketplace_fee:0,delivery:2,sheet_net:25},
];
const sales = {
  lines: baseLines,
  orders: [
    {order_id:'A-1',customer_group:'new',identity_conflict:false},
    {order_id:'A-2',customer_group:'repeat',identity_conflict:false},
    {order_id:'NO-ID',customer_group:'unidentified',identity_conflict:false},
  ],
};
const expenses = {
  columns:{date:0,amount:1,operating:2},
  rows:[[new Date('2026-09-05T00:00:00Z'),30,'Так']],
};
const writeoffs = {
  columns:{id:0,date:1,qty:2,total:3,reason:4,note:5},
  rows:[
    ['WRT-1',new Date('2026-09-06T00:00:00Z'),1,10,'Пошкодження',''],
    ['WRT-2',new Date('2026-09-06T00:00:00Z'),1,'','Пошкодження',''],
    ['WRT-3',new Date('2026-09-06T00:00:00Z'),1,5,'для комплектації замовлення','Продаж A-2'],
  ],
};

test('Pass B removed only the three CRM-011 shadow declarations', () => {
  for (const name of ['crmGetOrders_','apiLtvReport_','apiQualifiedClientsReport_']) {
    assert.equal((source.match(new RegExp(`^function ${name}\\(`, 'gm')) || []).length, 1, name);
  }
  const names = [...source.matchAll(/^function\s+([A-Za-z0-9_]+)\s*\(/gm)].map(match => match[1]);
  const duplicates = [...new Set(names.filter((name,index) => names.indexOf(name) !== index))].sort();
  assert.deepEqual(duplicates, ['apiAddSale_','getDirectOrderExpense_']);
});

test('header resolver follows names after columns are reordered', () => {
  const factory = new Function(`
    function apiNormalizeHeader_(value){return String(value||'').trim().toLowerCase();}
    ${functionSource('crm011FinanceColumn_')}
    return crm011FinanceColumn_;
  `)();
  assert.equal(factory(['Чистий прибуток','Нова Пей','Сума продажу'],'Сума продажу'),2);
  assert.equal(factory(['Чистий прибуток','Нова Пей','Сума продажу'],['Missing','Нова Пей']),1);
  assert.throws(() => factory(['A'],'Сума продажу'), /CRM011_FINANCE_COLUMN_NOT_FOUND/);
});

test('first and second order in one period classify as New then Repeat', () => {
  const headers = ['Номер замовлення / операції','Дата продажу','Телефон клієнта','ПІБ клієнта','Кількість','Сума продажу','Управлінська собівартість продажу','Пакування','Еквайринг','Нова Пей','Комісія маркетплейсу','Доставка за рахунок магазину','Чистий прибуток','Статус оплати','Статус замовлення','Тип оплати','Дата оплати','Дата отримання','Дата фіксації собівартості','Метод собівартості'];
  const makeRow = values => headers.map(header => values[header] ?? '');
  const rows = [
    makeRow({'Номер замовлення / операції':'FIRST','Дата продажу':new Date('2026-09-03'),'Телефон клієнта':'+380501112233','Кількість':1,'Сума продажу':100,'Управлінська собівартість продажу':40,'Пакування':5,'Еквайринг':2,'Нова Пей':1,'Комісія маркетплейсу':0,'Доставка за рахунок магазину':2,'Чистий прибуток':50,'Статус оплати':'Оплачено','Статус замовлення':'Отримано'}),
    makeRow({'Номер замовлення / операції':'SECOND','Дата продажу':new Date('2026-09-07'),'Телефон клієнта':'+380501112233','Кількість':1,'Сума продажу':200,'Управлінська собівартість продажу':80,'Пакування':5,'Еквайринг':3,'Нова Пей':0,'Комісія маркетплейсу':2,'Доставка за рахунок магазину':10,'Чистий прибуток':100,'Статус оплати':'Оплачено','Статус замовлення':'Отримано'}),
  ];
  const sheet = {getLastColumn:()=>headers.length,getLastRow:()=>rows.length+2,getRange:(row)=>({getDisplayValues:()=>[headers],getValues:()=>rows})};
  const factory = new Function('sheet',`
    function apiNormalizeHeader_(value){return String(value||'').trim().toLowerCase();}
    function onlyDigits_(value){return String(value||'').replace(/\\D/g,'');}
    function num_(value){const number=Number(value);return Number.isFinite(number)?number:0;}
    function dateSortValue_(value){return value instanceof Date?value.getTime():new Date(value).getTime();}
    function isUnfinalizedPreorderCostMethod_(value){return String(value||'').indexOf('Прогноз передзамовлення')!==-1;}
    ${functionSource('isPhysicalStockReservationSale_')}
    ${functionSource('crm012WasPreorderRow_')}
    ${functionSource('crm012RecognitionDateForSalesRow_')}
    ${functionSource('crm011FinanceTable_')}
    ${functionSource('crm011FinanceColumn_')}
    ${functionSource('crm011FinanceNumber_')}
    ${functionSource('crm011FinanceClientKey_')}
    ${functionSource('crm011FinanceSalesModel_')}
    return crm011FinanceSalesModel_({getSheetByName:()=>sheet});
  `)(sheet);
  assert.deepEqual(factory.orders.map(order => [order.order_id,order.customer_group]),[['FIRST','new'],['SECOND','repeat']]);
});

test('P&L reconciles direct deductions, operating expenses and valued writeoffs', () => {
  const result = financeFactory().crm011FinancePeriod_(sales, expenses, writeoffs, bounds);
  assert.deepEqual(result.totals, {orders:3,units:3,revenue:350,net_profit:135,margin_pct:38.57,avg_order:116.67});
  assert.deepEqual(result.pnl, {revenue:350,cogs:140,gross_profit:210,packaging:12,delivery:14,payment_fees:9,operating_expenses:30,writeoffs:10,net_profit:135,margin_pct:38.57});
  assert.deepEqual(result.customer_mix, {
    new:{orders:1,revenue:100,avg_order:100,margin_pct:50},
    repeat:{orders:1,revenue:200,avg_order:200,margin_pct:50},
    unidentified_orders:1,
  });
  assert.equal(result.data_quality.writeoff_missing_value_rows,1);
  assert.equal(result.data_quality.linked_order_writeoffs_excluded,1);
  assert.equal(result.data_quality.reconciliation_sample.length,3);
  assert.ok(result.data_quality.reconciliation_sample.every(row => row.difference === 0));
});

test('P&L stops instead of hiding a mismatch in sheet net profit', () => {
  const badSales = {...sales,lines:baseLines.map((line,index) => index === 1 ? {...line,sheet_net:99} : line)};
  assert.throws(() => financeFactory().crm011FinancePeriod_(badSales,expenses,writeoffs,bounds), /CRM011_PNL_RECONCILIATION_FAILED/);
});

test('finance report reuses one header-resolved sales model', () => {
  const report = functionSource('apiFinanceReport_');
  assert.match(report,/crm011FinanceSalesModel_\(ss\)/);
  assert.doesNotMatch(report,/_getCrmSalesRows\(\)|_getCrmSalesRowEntries\(\)/);
  assert.match(report,/crm011FinanceInventoryAssets_\(sales\)/);
  assert.doesNotMatch(report,/apiSkuList_\(\{\}\)/);
  assert.match(source,/action === 'finance_report'\) return 'bscrm_v2_' \+ version \+ '_' \+ action \+ '_v4_/);
  assert.match(report,/comparison = \{ period: compareBounds, totals: compared\.totals, pnl: compared\.pnl, cashflow: cashflowFor\(compareBounds\)/);
});

test('missing paid revenue stays unavailable in cashflow instead of becoming zero', () => {
  const cashIn = new Function(`
    function round2_(value){return Math.round((Number(value)+Number.EPSILON)*100)/100;}
    ${functionSource('crm011FinanceCashIn_')}
    return crm011FinanceCashIn_;
  `)();
  const result = cashIn({lines:[{payment_status:'Оплачено',payment_date_ms:new Date('2026-09-05').getTime(),date_ms:new Date('2026-09-05').getTime(),payment_type:'Картка',revenue:null}]},bounds);
  assert.equal(result.amount,null);
  assert.equal(result.missing_value_rows,1);
});

test('dashboard renders the expanded Pass B contract', () => {
  for (const label of ['Чистий прибуток','Чиста маржа','Середній чек','Собівартість товарів','Комісії оплат','Списання']) assert.match(dashboard,new RegExp(label));
  assert.match(dashboard,/financeCustomerMix_/);
  assert.match(dashboard,/unidentified_orders/);
  assert.match(dashboard,/d\.comparison&&d\.comparison\.pnl/);
  assert.match(dashboard,/d\.comparison&&d\.comparison\.cashflow/);
  assert.doesNotMatch(dashboard,/comparison&&d\.comparison\.inventory_assets/);
});
