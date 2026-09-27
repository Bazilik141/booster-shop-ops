import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const source=fs.readFileSync(path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../Code.gs'),'utf8');
function letters(column){let text='';for(;column;column=Math.floor((column-1)/26))text=String.fromCharCode(65+(column-1)%26)+text;return text;}
function a1Parts(text){const match=/^([A-Z]+)(\d+)$/.exec(text);assert.ok(match);return {column:[...match[1]].reduce((n,c)=>n*26+c.charCodeAt(0)-64,0),row:Number(match[2])};}
class Range {
  constructor(sheet,row,column,rows=1,columns=1){Object.assign(this,{sheet,row,column,rows,columns});}
  getSheet(){return this.sheet;}
  getRow(){return this.row;}
  getColumn(){return this.column;}
  getA1Notation(){return letters(this.column)+this.row;}
  getValue(){return this.sheet.cell(this.row,this.column).value;}
  getDisplayValue(){return String(this.getValue()??'');}
  getFormula(){return this.sheet.cell(this.row,this.column).formula;}
  getValues(){return Array.from({length:this.rows},(_,r)=>Array.from({length:this.columns},(_,c)=>this.sheet.cell(this.row+r,this.column+c).value));}
  getDisplayValues(){return this.getValues().map(row=>row.map(value=>String(value??'')));}
  setValue(value){this.sheet.cell(this.row,this.column).value=value;this.sheet.cell(this.row,this.column).formula='';return this;}
  setFormula(formula){this.sheet.cell(this.row,this.column).formula=formula;return this;}
  setValues(values){values.forEach((row,r)=>row.forEach((value,c)=>this.sheet.getRange(this.row+r,this.column+c).setValue(value)));return this;}
}
class Sheet {
  constructor(name){this.name=name;this.cells=new Map();}
  getName(){return this.name;}
  cell(row,column){const key=row+':'+column;if(!this.cells.has(key))this.cells.set(key,{value:'',formula:''});return this.cells.get(key);}
  getRange(row,column,rows=1,columns=1){if(typeof row==='string'){const pos=a1Parts(row);return new Range(this,pos.row,pos.column);}return new Range(this,row,column,rows,columns);}
  getRangeList(addresses){return {setValue:(value)=>{addresses.forEach(address=>this.getRange(address).setValue(value));}};}
  getLastRow(){let max=0;for(const [key,cell] of this.cells)if(cell.value!==''||cell.formula)max=Math.max(max,Number(key.split(':')[0]));return max;}
}
class Spreadsheet {
  constructor(sheets){this.sheets=new Map(sheets.map(sheet=>[sheet.name,sheet]));}
  getSheetByName(name){return this.sheets.get(name)||null;}
  insertSheet(name){const sheet=new Sheet(name);this.sheets.set(name,sheet);return sheet;}
  createTextFinder(text){
    let entire=false,formulas=false;
    return {matchEntireCell(value){entire=value;return this;},matchFormulaText(value){formulas=value;return this;},findAll:()=>{
      const found=[];
      for(const sheet of this.sheets.values())for(const [key,cell] of sheet.cells){
        const haystack=String(formulas&&cell.formula?cell.formula:cell.value??'');
        if((entire?haystack.toUpperCase()===text.toUpperCase():haystack.toUpperCase().includes(text.toUpperCase()))){const [row,column]=key.split(':').map(Number);found.push(sheet.getRange(row,column));}
      }
      return found;
    }};
  }
}
function fixture(){
  const names=['Товари','РРЦ','Склад','Продажі','Закупки','Списання','Міграції_Складу'];
  const crm=new Spreadsheet(names.map(name=>new Sheet(name)));
  const auto=new Spreadsheet([new Sheet('Майстер_Товарів')]);
  const old='PKM-JP-OLD-BST',next='PKM-JP-NEW-BST';
  const products=crm.getSheetByName('Товари'),rrc=crm.getSheetByName('РРЦ'),stock=crm.getSheetByName('Склад');
  products.getRange(3,1).setValue(old);products.getRange(3,3).setValue('Old full name');
  products.getRange(3,6).setValue('Old set');products.getRange(3,7).setValue('Бустер');
  products.cell(3,2).formula='=IF(OR($D3="";$F3="";$E3="";$G3="");"";$D3&" — "&$F3&" — "&$E3&" — "&$G3)';products.cell(3,2).value='Old accounting name';products.cell(3,10).formula='=E3';
  rrc.cell(3,1).formula='=Товари!A3';rrc.cell(3,1).value=old;rrc.cell(3,8).formula='=E3';rrc.getRange(3,5).setValue(100);
  stock.cell(3,1).formula='=Товари!A3';stock.cell(3,1).value=old;
  auto.getSheetByName('Майстер_Товарів').cell(2,1).formula='=IMPORTRANGE("id","Товари!A3")';
  auto.getSheetByName('Майстер_Товарів').cell(2,1).value=old;
  crm.getSheetByName('Продажі').getRange(3,6).setValue(old);
  crm.getSheetByName('Продажі').cell(3,7).formula='=IF($F3="";"";INDEX(Товари!B:B;MATCH($F3;Товари!A:A;0)))';
  crm.getSheetByName('Продажі').cell(3,7).value='Old accounting name';
  crm.getSheetByName('Продажі').getRange(3,8).setValue(2);
  crm.getSheetByName('Продажі').getRange(3,12).setValue(45.67);
  crm.getSheetByName('Закупки').getRange(3,5).setValue(old);
  crm.getSheetByName('Закупки').getRange(3,8).setValue(10);
  crm.getSheetByName('Списання').getRange(3,4).setValue(old);
  crm.getSheetByName('Міграції_Складу').getRange(2,4).setValue(old);
  crm.getSheetByName('Міграції_Складу').getRange(2,5).setValue(old);
  const properties={};
  const context=vm.createContext({JSON,Math,Number,String,Boolean,Array,Object,RegExp,Date,Error,isFinite,
    SpreadsheetApp:{flush(){for(const row of [3,4]){const b=products.cell(row,2);if(b.formula.includes('$C'+row))b.value=products.cell(row,3).value;else if(b.formula.includes('$D'+row))b.value='Old accounting name';rrc.cell(row,1).value=products.cell(row,1).value;stock.cell(row,1).value=products.cell(row,1).value;}auto.getSheetByName('Майстер_Товарів').cell(2,1).value=products.cell(3,1).value;const sales=crm.getSheetByName('Продажі');for(const row of [3,4]){const g=sales.cell(row,7);if(g.formula){const sku=sales.cell(row,6).value;const productRow=[3,4].find(n=>products.cell(n,1).value===sku);g.value=productRow?products.cell(productRow,2).value:'';}}}},
    PropertiesService:{getScriptProperties:()=>({getProperty:key=>properties[key]||'',setProperty:(key,value)=>{properties[key]=value;}})},
    Logger:{log(){}}
  });
  vm.runInContext(source+'\ncrmCatalogLastWritableRow_=function(){return 201;};_getCrmSs=function(){return globalThis.__crm;};_getAutoSs=function(){return globalThis.__auto;};apiIntegrityCheck_=function(){globalThis.__integrityCalls++;const clean=globalThis.__integrityClean && globalThis.__integrityCalls!==globalThis.__integrityFailAt;return {clean:clean,problems:clean?[]:[{code:"broken"}]};};globalThis.__api={apiCatalogIdentityContext_,apiCatalogIdentityPreview_,apiCatalogIdentityApply_,normalizeOpenCartSku_,crmCatalogIdentityManualShortNames_,crmIntegrityCheckRowFormulas_,resetMemo_};',context,{filename:'Code.gs'});
  context.__crm=crm;context.__auto=auto;context.__integrityClean=true;context.__integrityCalls=0;context.__integrityFailAt=-1;context.__api.resetMemo_();
  return {crm,auto,context,old,next,products,setIntegrity(value){context.__integrityClean=value;},failPostcheck(){context.__integrityFailAt=2;}};
}
function request(f){return {old_sku:f.old,new_sku:f.next,new_name:'New full name',expected_name:'Old full name'};}

{
  const f=fixture(),payload=request(f),preview=f.context.__api.apiCatalogIdentityPreview_(f.crm,payload);
  const context=f.context.__api.apiCatalogIdentityContext_(f.crm,{sku:f.old});
  assert.equal(context.full_name,'Old full name','editor reads canonical Товари name');
  assert.equal(context.accounting_name,'Old accounting name');
  assert.equal(context.name_sync_supported,true,'dashboard can refuse an older Web App before editing');
  assert.equal(preview.ok,true,preview.error);assert.equal(preview.references,6);assert.equal(preview.sale_names_updated,1);
  assert.equal(f.products.getRange(3,1).getValue(),f.old,'preview is read-only');
  const applied=f.context.__api.apiCatalogIdentityApply_(f.crm,{...payload,preview_signature:preview.preview_signature});
  assert.equal(applied.ok,true,applied.error);assert.equal(applied.integrity_clean,true);
  for(const [name,row,column] of [['Товари',3,1],['Продажі',3,6],['Закупки',3,5],['Списання',3,4],['Міграції_Складу',2,4],['Міграції_Складу',2,5]])assert.equal(f.crm.getSheetByName(name).getRange(row,column).getValue(),f.next);
  assert.equal(f.crm.getSheetByName('Продажі').getRange(3,12).getValue(),45.67,'frozen cost is unchanged');
  assert.equal(f.products.getRange(3,10).getFormula(),'=E3','price formula is untouched');
  assert.equal(f.crm.getSheetByName('РРЦ').getRange(3,8).getFormula(),'=E3','RRP formula is untouched');
  assert.equal(f.crm.getSheetByName('РРЦ').getRange(3,1).getValue(),f.next);
  assert.equal(f.products.getRange(3,2).getValue(),'New full name','accounting name follows edited full name');
  assert.equal(f.crm.getSheetByName('Продажі').getRange(3,7).getValue(),'New full name','historical sale formula displays edited name');
  assert.equal(f.context.__api.normalizeOpenCartSku_(f.old),f.next,'future OpenCart imports use the journal alias');
  assert.equal(f.crm.getSheetByName('Зміни_SKU').getRange(2,6).getValue(),'APPLIED');
  f.crm.getSheetByName('Зміни_SKU').getRange(2,6).setValue('RECOVERY_REQUIRED');
  f.context.__api.resetMemo_();
  assert.throws(()=>f.context.__api.normalizeOpenCartSku_(f.old),/unfinished/,'an interrupted migration blocks future imports');
}
{
  const f=fixture(),payload={...request(f),new_sku:f.old},preview=f.context.__api.apiCatalogIdentityPreview_(f.crm,payload);
  assert.equal(preview.ok,true,preview.error);assert.equal(preview.references,0);assert.equal(preview.sale_names_updated,1);
  const applied=f.context.__api.apiCatalogIdentityApply_(f.crm,{...payload,preview_signature:preview.preview_signature});
  assert.equal(applied.ok,true,applied.error);assert.equal(f.products.getRange(3,3).getValue(),'New full name');
  assert.equal(f.products.getRange(3,2).getValue(),'New full name');
  assert.equal(f.crm.getSheetByName('Продажі').getRange(3,7).getValue(),'New full name','name-only edit updates historical sale display through existing formula');
  assert.equal(f.crm.getSheetByName('Продажі').getRange(3,6).getValue(),f.old);
}
{
  const f=fixture();f.products.cell(3,2).formula='=IF($A3="";"";$C3)';f.products.cell(3,2).value='Old full name';
  f.crm.getSheetByName('Продажі').cell(3,7).value='Old full name';
  const payload={...request(f),new_sku:f.old},preview=f.context.__api.apiCatalogIdentityPreview_(f.crm,payload);
  assert.equal(preview.ok,true,preview.error);
  const applied=f.context.__api.apiCatalogIdentityApply_(f.crm,{...payload,preview_signature:preview.preview_signature});
  assert.equal(applied.ok,true,applied.error);
  assert.equal(f.products.getRange(3,2).getFormula(),'=IF($A3="";"";$C3)','an already linked accounting-name formula stays intact');
  assert.equal(f.crm.getSheetByName('Продажі').getRange(3,7).getValue(),'New full name');
}
{
  const f=fixture();f.crm.insertSheet('Невідомий_звіт').getRange(3,2).setValue(f.old);
  const blocked=f.context.__api.apiCatalogIdentityPreview_(f.crm,request(f));
  assert.equal(blocked.ok,false);assert.match(blocked.error,/Unknown SKU dependency/);
  assert.equal(f.products.getRange(3,1).getValue(),f.old);
}
{
  const f=fixture();f.crm.getSheetByName('Продажі').cell(9,10).formula='="'+f.old+'"';
  assert.match(f.context.__api.apiCatalogIdentityPreview_(f.crm,request(f)).error,/hardcoded in formula/);
}
{
  const f=fixture(),payload=request(f),preview=f.context.__api.apiCatalogIdentityPreview_(f.crm,payload);
  f.crm.getSheetByName('Продажі').getRange(4,6).setValue(f.old);
  f.crm.getSheetByName('Продажі').cell(4,7).formula='=IF($F4="";"";INDEX(Товари!B:B;MATCH($F4;Товари!A:A;0)))';
  f.crm.getSheetByName('Продажі').cell(4,7).value='Old accounting name';
  assert.match(f.context.__api.apiCatalogIdentityApply_(f.crm,{...payload,preview_signature:preview.preview_signature}).error,/preview again/);
  assert.equal(f.products.getRange(3,1).getValue(),f.old);
}
{
  const f=fixture(),payload=request(f),preview=f.context.__api.apiCatalogIdentityPreview_(f.crm,payload);
  f.setIntegrity(false);
  assert.match(f.context.__api.apiCatalogIdentityApply_(f.crm,{...payload,preview_signature:preview.preview_signature}).error,/precheck/);
  assert.equal(f.crm.getSheetByName('Зміни_SKU'),null,'dirty precheck creates no journal');
}
{
  const f=fixture(),payload=request(f),preview=f.context.__api.apiCatalogIdentityPreview_(f.crm,payload);
  f.failPostcheck();
  const failed=f.context.__api.apiCatalogIdentityApply_(f.crm,{...payload,preview_signature:preview.preview_signature});
  assert.equal(failed.ok,false);assert.match(failed.error,/rolled back/);
  assert.equal(f.products.getRange(3,1).getValue(),f.old);
  assert.equal(f.products.getRange(3,3).getValue(),'Old full name');
  assert.equal(f.products.getRange(3,2).getValue(),'Old accounting name');
  assert.match(f.products.getRange(3,2).getFormula(),/\$D3/,'rollback restores the original short-name formula');
  assert.equal(f.crm.getSheetByName('Продажі').getRange(3,6).getValue(),f.old);
  assert.equal(f.crm.getSheetByName('Продажі').getRange(3,7).getValue(),'Old accounting name','sale display follows the restored formula');
  assert.equal(f.crm.getSheetByName('Зміни_SKU').getRange(2,6).getValue(),'ROLLED_BACK');
  assert.equal(f.context.__api.normalizeOpenCartSku_(f.old),f.old);
}
{
  const f=fixture(),payload=request(f),preview=f.context.__api.apiCatalogIdentityPreview_(f.crm,payload);
  f.context.apiIntegrityCheck_=function(){f.context.__integrityCalls++;const clean=f.context.__integrityCalls===1;return {clean,problems:clean?[]:[{code:'broken'}]};};
  const failed=f.context.__api.apiCatalogIdentityApply_(f.crm,{...payload,preview_signature:preview.preview_signature});
  assert.equal(failed.ok,false);assert.match(failed.error,/manual recovery/);
  assert.equal(f.crm.getSheetByName('Зміни_SKU').getRange(2,6).getValue(),'RECOVERY_REQUIRED','unverified rollback must never be recorded as complete');
}
{
  const f=fixture();f.products.getRange(3,6).setValue('3D-друк');
  assert.match(f.context.__api.apiCatalogIdentityPreview_(f.crm,request(f)).error,/3D-P products/);
}
{
  const f=fixture();f.products.getRange(3,2).setValue('Curated accounting name');
  assert.match(f.context.__api.apiCatalogIdentityPreview_(f.crm,request(f)).error,/manual or custom rule/,'manual short names are never overwritten by the editor');
}
{
  const f=fixture();f.crm.getSheetByName('Продажі').getRange(3,7).setValue('Frozen sale name');
  assert.match(f.context.__api.apiCatalogIdentityPreview_(f.crm,request(f)).error,/not linked to the catalogue/,'a literal historical sale name cannot be promised to update');
}
{
  const f=fixture(),rrc=f.crm.getSheetByName('РРЦ'),stock=f.crm.getSheetByName('Склад'),master=f.auto.getSheetByName('Майстер_Товарів');
  f.products.getRange(3,1).setValue('');f.products.getRange(4,1).setValue(f.old);f.products.getRange(4,3).setValue('Old full name');
  f.products.cell(4,2).formula='=IF(OR($D4="";$F4="";$E4="";$G4="");"";$D4&" — "&$F4&" — "&$E4&" — "&$G4)';f.products.cell(4,2).value='Old accounting name';
  f.products.getRange(4,6).setValue('Old set');f.products.getRange(4,7).setValue('Бустер');f.products.cell(4,10).formula='=E4';
  rrc.cell(3,1).value='';stock.cell(3,1).value='';master.cell(2,1).value='';
  rrc.getRange(4,1).setValue(f.old);rrc.cell(4,8).formula='=E4';stock.getRange(4,1).setValue(f.old);
  master.getRange(3,1).setValue(f.old);
  const preview=f.context.__api.apiCatalogIdentityPreview_(f.crm,request(f));
  assert.equal(preview.ok,true,preview.error);
  assert.equal(preview.product_row,4,'ARRAYFORMULA spill outputs are accepted as projections');
  assert.equal(preview.references,6,'only manual identity keys enter the write plan');
}
{
  const f=fixture();
  assert.match(f.context.__api.apiCatalogIdentityPreview_(f.crm,{...request(f),new_sku:'ACC-001-BPJP'}).error,/legacy OpenCart alias/);
}
{
  const f=fixture(),journal=f.crm.insertSheet('Зміни_SKU');
  journal.getRange(2,2).setValue('ACC-001-BPEN');journal.getRange(2,3).setValue('ACC-001-NEW');journal.getRange(2,6).setValue('APPLIED');
  f.context.__api.resetMemo_();
  assert.equal(f.context.__api.normalizeOpenCartSku_('ACC-001-BPJP'),'ACC-001-NEW','historical hardcoded alias chains into a later journal rename');
}
{
  const f=fixture(),journal=f.crm.insertSheet('Зміни_SKU');
  journal.getRange(2,2).setValue('ACC-001');journal.getRange(2,3).setValue('ACC-001-NEW');journal.getRange(2,6).setValue('APPLIED');
  journal.getRange(3,2).setValue('ACC-001-NEW');journal.getRange(3,3).setValue('ACC-001-FINAL');journal.getRange(3,6).setValue('PENDING');
  const allowed=f.context.__api.crmCatalogIdentityManualShortNames_();
  assert.equal(allowed['ACC-001-FINAL'],true,'manual short-name integrity exception follows a renamed SKU, including the pending postcheck');
  const report={problems:[],truncated:{}};
  f.context.__api.crmIntegrityCheckRowFormulas_(report,{sheet:f.products,title:'Товари',headerIndex:{SKU:0,'Коротка назва':1},dataStartRow:3,values:[['ACC-001-FINAL','Manual label']],formulas:[['','']]},['Коротка назва']);
  assert.equal(report.problems.length,0,'integrity checker accepts the renamed legacy manual short-name cell');
}

console.log('Catalog identity preview, migration, aliases and guards passed');
