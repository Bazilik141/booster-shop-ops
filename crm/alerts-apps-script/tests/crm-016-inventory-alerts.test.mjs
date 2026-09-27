import assert from 'node:assert/strict';
import fs from 'node:fs';

const source = fs.readFileSync(new URL('../Code.gs', import.meta.url), 'utf8');
const start = source.indexOf('function collect3dpInventoryIssues_(');
const end = source.indexOf('function installDailyTroubleAlertTrigger()', start);
assert.ok(start >= 0 && end > start);
const code = source.slice(start, end);
function run(snapshot) {
  const fn = new Function('crm016AlertInventorySnapshot_', 'hashText_', code + '\nreturn collect3dpInventoryIssues_;')(
    () => { if (snapshot instanceof Error) throw snapshot; return snapshot; }, value => value);
  return fn();
}

const issues = run({ source_status:'ready', skus:[
  { sku:'ACC-3D-PKM-110', is_3dp:true, name:'stand', stock_raw:2, issues:[] },
  { sku:'BR-DITTO-400', is_3dp:true, name:'ditto', stock_raw:0, issues:['3dp_sale_sync_missing'] },
  { sku:'FIG-ONIX-500', is_3dp:true, name:'onix', stock_raw:-1, issues:[] },
  { sku:'ACC-005', is_3dp:false, stock_raw:-8, issues:[] },
], exceptions:[{ sku:'ACC-3D-TCG-600', code:'3dp_active_missing_from_crm_active_catalog' }] });
assert.deepEqual(issues.map(issue => issue.kind), ['3dp_sale_sync_missing', '3dp_print_needed', '3dp_catalog_gap']);
assert.equal(issues.some(issue => issue.sku === 'ACC-3D-PKM-110'), false);
assert.equal(issues.some(issue => issue.sku === 'ACC-005'), false);
const proxyIssues = run({ source_status:'ready', skus:[
  { sku:'BR-DITTO-400', is_3dp:true, name:'ditto', stock_raw:null,
    stock_error:'Fulfilled CRM sale is missing', issues:['3dp_sale_sync_missing','3dp_physical_unverified'] }
], exceptions:[{ sku:'BR-DITTO-400', code:'3dp_sale_sync_missing', missing_fulfilled:1,
  missing_fulfilled_sources:[{ crm_row:387, order:'OC-FOP-0382', quantity:1 }] }] });
assert.deepEqual(proxyIssues.map(issue => issue.kind), ['3dp_sale_sync_missing'],
  'unverified proxy sale keeps the sync alert but does not claim a print deficit');
assert.match(proxyIssues[0].action, /не повторювати продаж/);
assert.equal(proxyIssues[0].signature, '3dp_sale_sync_missing|BR-DITTO-400|387:OC-FOP-0382:1');
const futureProxyIssues = run({ source_status:'ready', skus:[
  { sku:'BR-DITTO-400', is_3dp:true, name:'ditto', stock_raw:null,
    stock_error:'Fulfilled CRM sale is missing', issues:['3dp_sale_sync_missing','3dp_physical_unverified'] }
], exceptions:[{ sku:'BR-DITTO-400', code:'3dp_sale_sync_missing', missing_fulfilled:2,
  missing_fulfilled_sources:[{ crm_row:387, order:'OC-FOP-0382', quantity:1 },
    { crm_row:400, order:'OC-FOP-0400', quantity:1 }] }] });
assert.notEqual(futureProxyIssues[0].id, proxyIssues[0].id, 'a future unsynced sale must reappear after this one is dismissed');
assert.deepEqual(run(new Error('timeout')).map(issue => issue.kind), ['3dp_source']);
assert.deepEqual(run({ source_status:'unavailable', skus:[], exceptions:[] }).map(issue => issue.kind), ['3dp_source']);
assert.ok(source.includes('if (/^(?:BR|FIG|ACC-3D)-[A-Z0-9]/i.test(sku)) continue;'));

const negativeStart = source.indexOf('function collectNegativeStockIssues_(');
const negativeEnd = source.indexOf('function collectStockQueueIssues_()', negativeStart);
const negativeFactory = new Function('SpreadsheetApp','alertHeaderRow_','normalizeText_','findColumnLoose_','cell_','alertDisplayNumber_','hashText_','trimText_',
  source.slice(negativeStart, negativeEnd) + '\nreturn collectNegativeStockIssues_;');
const values = [
  ['SKU','Назва','Залишок','Очікується'],
  ['OP-JP-EB03-BST','EB-03','-5','8'],
  ['ACC-005','ACC-005','-8','0'],
  ['BR-CHARM-100','Charm','-1','0'],
];
const sheet = { getLastRow:() => values.length, getLastColumn:() => values[0].length,
  getRange:() => ({ getDisplayValues:() => values }) };
const negative = negativeFactory({ getActive:() => ({ getSheetByName:() => sheet }) }, () => 0,
  value => String(value||'').trim().toLowerCase(),
  (headers,names) => headers.findIndex(header => names.includes(header)),
  (row,index) => index >= 0 ? String(row[index]||'') : '',
  value => Number(value), value => value, (value,length) => String(value).slice(0,length));
const remaining = negative({ skus:[
  { sku:'OP-JP-EB03-BST', stock_raw:-5, physical_stock:1 },
  { sku:'ACC-005', stock_raw:-8, physical_stock:null },
] });
assert.deepEqual(remaining.map(issue => issue.sku), ['ACC-005'], 'open preorder and 3D formula negatives are not physical shortage alerts');
assert.match(remaining[0].details, /закуплено, ще не на складі UA: 0 шт/);
console.log('CRM-016 alert selection: 3D source, sync and print exceptions OK');
