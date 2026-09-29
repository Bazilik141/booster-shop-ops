import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import crypto from 'node:crypto';
import test from 'node:test';

const read = (relative) => fs.readFileSync(new URL(relative, import.meta.url), 'utf8');
const release = read('../../../patches/3D-P-007_snorlax-stock_20260928/Code_final.gs');
const fifo = read('../../../patches/3D-P-007_snorlax-stock_20260928/CatalogFifo.gs');
const repair = read('../../../patches/3D-P-007_snorlax-stock_20260928/3D-P-007.html');
const fixtureSource = read('./role-read-projections.test.mjs');
// Reuse only the existing Sheet test double, not its unrelated baseline assertions.
const harness = fixtureSource.slice(fixtureSource.indexOf('function columnNumber('), fixtureSource.indexOf('const owner ='));
const { loadApi, Sheet } = vm.runInNewContext(harness + '\n({loadApi,Sheet})', { vm, Buffer, console, deployedCode: release + '\n' + fifo });
const originalSetValue = Sheet.prototype.setValueAt;
Sheet.prototype.setValueAt = function(row,col,value) { this.setFormulaAt(row,col,'');return originalSetValue.call(this,row,col,value); };
const actor = { role: 'owner', identity: 'test' };
const plain = (x) => JSON.parse(JSON.stringify(x));

function setup(sourceCode = release + '\n' + fifo) {
  const api = loadApi(sourceCode);
  const { context: c, workbook: ss } = api;
  const source = ss.getSheetByName('Номенклатура');
  const availability = ss.getSheetByName('Наявність');
  source.getRange(76, 1, 1, 19).setValues([['FIG-SNRLX-500','Snorlax','','Фігурка','','',46205,'50.99',1000,900,'','','','','Активний','',150,110,'']]);
  const headers = vm.runInContext('CATALOG_FIFO_3DP.batchHeaders', c);
  const batch = ['3DP-B-20260928231333-47e11cde','fixture-request',36,'2026-09-28','2026-09-28','FIG-SNRLX-500',1,0,1,50.99,2.1166666667,1000,900,.1,4.32,12,0,72.29684,0,1,'active','serhiy','fingerprint',72.29684,72.29684];
  const batches = new Sheet('_Партії_FIFO_3DP',[plain(headers),batch]);
  ss.sheets.set('_Партії_FIFO_3DP', batches);
  ss.getSheetByName('Друк-лог').getRange(36, 2, 1, 9).setValues([['FIG-SNRLX-500',1,2.1166666667,0,50.99,72.29684,'Сергій','[fifo_batch:3DP-B-20260928231333-47e11cde]','Активний']]);
  const originalValueAt = availability.valueAt.bind(availability);
  availability.balance = 1;
  availability.valueAt = function(row,col) {
    const formula = this.formulaAt(row,col);
    if (col === 1 && formula.startsWith('=IF(\'Номенклатура\'!O')) return source.valueAt(row,15) === 'Активний' ? source.valueAt(row,1) : '';
    if (col === 7 && formula) return this.balance;
    return originalValueAt(row,col);
  };
  const props = new Map();
  c.PropertiesService = { getScriptProperties: () => ({getProperty: k => props.get(k) || null, setProperty: (k,v) => props.set(k,v), deleteProperty: k => props.delete(k)}) };
  c.Utilities.computeDigest = (_,text) => [...crypto.createHash('sha256').update(String(text)).digest()];
  const run = (mode) => vm.runInContext(repair,c)(mode);
  return {...api,source,availability,batches,props,run};
}

test('release and supplied FIFO parse; no temporary route in final or mirror', () => {
  new vm.Script(release + '\n' + fifo);
  new vm.Script(read('../../../patches/3D-P-007_snorlax-stock_20260928/Code_with_repair.gs'));
  new vm.Script(repair);
  assert.doesNotMatch(release, /preview3dp007Repair|apply3dp007Repair/);
  assert.doesNotMatch(read('../Code.gs'), /preview3dp007Repair|apply3dp007Repair/);
  assert.doesNotMatch(read('../Code.gs'), /TASK_3D_P_007_PREVIEW|TASK_3D_P_007_BACKUP/);
});

test('draft normalizer produces numeric hours and material inputs; invalid values fail', () => {
  const {context:c,source} = setup();
  for (const [v,h] of [['2:07',2.1166666667],['1,5',1.5],['1 год 30 хв',1.5],[0,0],['','']]) {
    const result=c.normalizeNomenclatureDraftValues3dp_(source,{B:'Test',D:'Фігурка',G:v,H:'50.99',I:'1000',J:'900',N:'1,5'});
    assert.equal(result.G,h);assert.equal(result.H,50.99);assert.equal(result.N,1.5);
  }
  for (const v of ['2:75','-1','text']) assert.throws(()=>c.normalizeNomenclatureDraftValues3dp_(source,{B:'Test',D:'Фігурка',G:v}),{code:'INVALID_PRINT_TIME'});
  assert.throws(()=>c.normalizeNomenclatureDraftValues3dp_(source,{B:'Test',D:'Фігурка',H:'-2'}),{code:'INVALID_DRAFT_NUMBER'});
});

test('missing availability is provisioned once and row formula semantics match live baseline', () => {
  const {context:c,workbook:ss,availability} = setup();
  assert.equal(c.ensureNomenclatureAvailability3dp_(ss,76).initialized,true);
  const actual=availability.getRange(76,1,1,7).getFormulas()[0];
  assert.match(actual[0], /O76="Активний"/);
  const live=JSON.parse(read('./fixtures/availability-row73-formulas.json'));
  for(let i=1;i<7;i++) {
    if(i===2 || i===3) {
      assert.match(actual[i], /Номенклатура'!\$O:\$O/);
      assert.match(actual[i], /Активний/);
      assert.match(actual[i], new RegExp(i===2 ? "Друк-лог'!\\$C:\\$C" : "Друк-лог'!\\$E:\\$E"));
    } else assert.equal(actual[i],live[i].replaceAll('73','76'));
  }
  assert.equal(c.ensureNomenclatureAvailability3dp_(ss,76).initialized,false);
});

test('partial projection fills only blanks and manual/formula/duplicate conflicts fail before writes', () => {
  for (const variant of ['manual','formula','duplicate','draft','archived']) {
    const {context:c,workbook:ss,availability,source}=setup();
    if(variant==='manual')availability.setValueAt(76,7,99);
    if(variant==='formula')availability.setFormulaAt(76,3,'=999');
    if(variant==='duplicate')availability.setValueAt(77,1,'FIG-SNRLX-500');
    if(variant==='draft')source.setValueAt(76,15,'Чернетка');
    if(variant==='archived')source.setValueAt(76,15,'Архів');
    const before=JSON.stringify(availability.formulas);
    assert.throws(()=>c.ensureNomenclatureAvailability3dp_(ss,76),error => String(error.code).startsWith('AVAILABILITY_'));
    assert.equal(JSON.stringify(availability.formulas),before);
  }
  const {context:c,workbook:ss,availability}=setup();
  availability.setFormulaAt(76,1,"='Номенклатура'!A76");
  c.ensureNomenclatureAvailability3dp_(ss,76);
  assert.equal(availability.formulaAt(76,1),"='Номенклатура'!A76");
  assert.ok(availability.formulaAt(76,7));
});

test('owner creation and draft promotion provision availability, including rollback on conflict', () => {
  for (const conflict of [false,true]) {
    const {context:c,workbook:ss,source,availability}=setup();
    source.setValueAt(76,1,'DRAFT-TEST');source.setValueAt(76,15,'Чернетка');
    // Test the actual activation transaction, stubbing only unrelated history/analytics.
    c.nomenclatureKeyHistory3dp_=()=>({blocking_locations:[]});c.syncActiveNomenclatureAnalytics3dp_=()=>({initialized_skus:[]});
    if(conflict)availability.setValueAt(76,7,99);
    const action=()=>c.assignNomenclatureSkuAction3dp_(ss,{draft_sku:'DRAFT-TEST',sku:'FIG-SNRLX-500'},actor);
    if(conflict){assert.throws(action,{code:'AVAILABILITY_VALUE_CONFLICT'});assert.equal(source.valueAt(76,1),'DRAFT-TEST');assert.equal(source.valueAt(76,15),'Чернетка');assert.equal(availability.valueAt(76,7),99);}
    else {action();assert.ok(availability.formulaAt(76,7));}
  }
  const {context:c,workbook:ss,availability}=setup();
  c.syncActiveNomenclatureAnalytics3dp_=()=>({initialized_skus:['FIG-NEW-100']});
  const created=c.createNomenclatureOwnerAction3dp_(ss,{sku:'FIG-NEW-100',values:{B:'New',D:'Фігурка',Q:100}},actor);
  assert.ok(availability.formulaAt(created.row,7));
});

test('repair preview is sheet-read-only; apply is exact, backed up and idempotent', () => {
  const {run,source,availability,batches,props}=setup();
  const batchBefore=JSON.stringify(batches.rows);
  const p=run('preview');assert.equal(p.changed_cells.length,9);assert.equal(source.valueAt(76,7),46205);assert.equal(availability.formulaAt(76,1),'');
  const applied=run('apply');assert.equal(applied.available,1);assert.equal(source.valueAt(76,7),2.1166666667);assert.equal(source.valueAt(76,8),50.99);
  assert.ok(props.get('TASK_3D_P_007_BACKUP'));assert.equal(JSON.stringify(batches.rows),batchBefore);
  assert.equal(run('apply').already_applied,true);
});

test('temporary Apps Script editor wrappers load the exact HTML and run preview/apply', () => {
  const temp = read('../../../patches/3D-P-007_snorlax-stock_20260928/Code_with_repair.gs');
  const a = setup(temp + '\n' + fifo);
  a.context.HtmlService = { createHtmlOutputFromFile: name => {
    assert.equal(name, '3D-P-007');
    return { getContent: () => repair };
  } };
  assert.equal(a.context.preview3dp007Repair().changed_cells.length, 9);
  assert.equal(a.context.apply3dp007Repair().available, 1);
});

test('repair rejects missing/stale preview and changed evidence; failure restores inputs and formulas', () => {
  assert.throws(()=>setup().run('apply'),/Preview missing/);
  { const a=setup();a.run('preview');a.batches.setValueAt(2,19,.5);assert.throws(()=>a.run('apply'),/stale/);assert.equal(a.source.valueAt(76,7),46205); }
  { const a=setup();a.source.setValueAt(76,7,3);assert.throws(()=>a.run('preview'),/G76 differs/); }
  { const a=setup();a.run('preview');a.availability.balance=0;assert.throws(()=>a.run('apply'),/differs from/);assert.equal(a.source.valueAt(76,7),46205);assert.equal(a.source.valueAt(76,8),'50.99');assert.equal(a.availability.formulaAt(76,1),''); }
});
