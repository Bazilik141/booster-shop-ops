import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, "../Code.gs"), "utf8");

function columnNumber(text) {
  return [...text].reduce((value, char) => value * 26 + char.charCodeAt(0) - 64, 0);
}

function parseA1(a1) {
  const match = /^([A-Z]+)(\d+):([A-Z]+)(\d+)$/.exec(String(a1).toUpperCase());
  if (!match) throw new Error("Unsupported A1 range: " + a1);
  return { row: Number(match[2]), column: columnNumber(match[1]), rows: Number(match[4]) - Number(match[2]) + 1, columns: columnNumber(match[3]) - columnNumber(match[1]) + 1 };
}

class MockRange {
  constructor(sheet, row, column, rows = 1, columns = 1) { this.sheet = sheet; this.row = row; this.column = column; this.rows = rows; this.columns = columns; }
  matrix(getter) { return Array.from({ length: this.rows }, (_, r) => Array.from({ length: this.columns }, (_, c) => getter(this.sheet.cell(this.row + r, this.column + c)))); }
  getValues() { return this.matrix((cell) => cell.value ?? ""); }
  getDisplayValues() { return this.matrix((cell) => String(cell.value ?? "")); }
  getFormulas() { return this.matrix((cell) => cell.formula || ""); }
  getDataValidations() { return this.matrix((cell) => cell.validation || null); }
  getValue() { return this.sheet.cell(this.row, this.column).value ?? ""; }
  getDisplayValue() { return String(this.getValue()); }
  getFormula() { return this.sheet.cell(this.row, this.column).formula || ""; }
  getDataValidation() { return this.sheet.cell(this.row, this.column).validation || null; }
  getCell(row, column) { return new MockRange(this.sheet, this.row + row - 1, this.column + column - 1); }
  getSheet() { return this.sheet; }
  getRow() { return this.row; }
  getColumn() { return this.column; }
  getNumRows() { return this.rows; }
  getNumColumns() { return this.columns; }
  setValue(value) { const cell = this.sheet.cell(this.row, this.column);cell.value=value;cell.formula="";return this; }
  setFormula(formula) { const cell = this.sheet.cell(this.row, this.column);cell.formula=formula;cell.value="";return this; }
  setValues(values) { values.forEach((row, r) => row.forEach((value, c) => { const cell=this.sheet.cell(this.row+r,this.column+c);cell.value=value;cell.formula=""; }));return this; }
  setDataValidation(rule) { this.matrix((cell) => { cell.validation=rule;return null; });return this; }
  clearContent() { this.matrix((cell) => { cell.value="";cell.formula="";return null; });return this; }
  copyTo(destination) { return destination; }
}

class MockSheet {
  constructor(name, maxRows = 201) { this.name=name;this.cells=new Map();this.maxRows=maxRows; }
  key(row,column){return row+":"+column;}
  cell(row,column){const key=this.key(row,column);if(!this.cells.has(key))this.cells.set(key,{value:"",formula:"",validation:null});return this.cells.get(key);}
  getRange(...args){if(typeof args[0]==="string"){const x=parseA1(args[0]);return new MockRange(this,x.row,x.column,x.rows,x.columns);}return new MockRange(this,args[0],args[1],args[2]||1,args[3]||1);}
  getLastRow(){let last=0;for(const [key,cell] of this.cells){if(cell.value!=="" || cell.formula)last=Math.max(last,Number(key.split(":")[0]));}return last;}
  getMaxRows(){return this.maxRows;}
  getMaxColumns(){return 32;}
  getSheetId(){return this.name;}
  insertRowsAfter(row,count){assert.equal(row,this.maxRows,`${this.name}: rows are appended only at the grid end`);this.maxRows+=count;return this;}
  getName(){return this.name;}
}

class MockSpreadsheet {
  constructor(sheets){this.sheets=new Map(sheets.map((sheet)=>[sheet.name,sheet]));this.toasts=[];}
  getSheetByName(name){return this.sheets.get(name)||null;}
  toast(message,title,timeoutSeconds){this.toasts.push({message,title,timeoutSeconds});}
}

function makeEnvironment({ missingProductPriceFormula = false, settingsRows = 201 } = {}) {
  const products=new MockSheet("Товари"),rrc=new MockSheet("РРЦ"),stock=new MockSheet("Склад"),settings=new MockSheet("Налаштування",settingsRows),master=new MockSheet("Майстер_Товарів");
  products.getRange(3,1,1,15).setValues([["EXISTING-001","","Existing","Booster Shop","UA","Pokemon","Бустер","","","",0,"Так","","",""]]);
  products.getRange(3,10).setFormula("=price");
  rrc.getRange(3,1,1,8).setValues([["EXISTING-001","Existing","Booster Shop","3D аксесуар",10,new Date("2026-08-12"),"",""]]);
  rrc.getRange(3,8).setFormula("=dynamic");
  stock.getRange(3,1).setValue("EXISTING-001");
  if(!missingProductPriceFormula)products.getRange(4,10).setFormula("=price");
  rrc.getRange(4,8).setFormula("=dynamic");
  settings.getRange(4,4).setValue("Booster Shop");
  settings.getRange(4,7).setValue("UA");
  settings.getRange(4,10).setValue("3D аксесуар");
  settings.getRange(4,30).setValue("3D-друк");
  const crm=new MockSpreadsheet([products,rrc,stock,settings]);
  const automation=new MockSpreadsheet([master]);
  const properties={};
  class RuleBuilder {
    requireValueInRange(range) { this.range=range;return this; }
    setAllowInvalid(value) { this.allowInvalid=value;return this; }
    build() { const range=this.range,allowInvalid=Boolean(this.allowInvalid);return {getCriteriaType:()=>"VALUE_IN_RANGE",getCriteriaValues:()=>[range],getAllowInvalid:()=>allowInvalid}; }
  }
  const context=vm.createContext({
    JSON,Math,Number,String,Boolean,Array,Object,RegExp,Date,Error,isFinite,
    Logger:{log(){}},Session:{getScriptTimeZone:()=>"Europe/Kyiv"},Utilities:{formatDate:()=>"2026-08-12"},
    PropertiesService:{getScriptProperties:()=>({getProperty:(key)=>properties[key]||"",setProperty:(key,value)=>{properties[key]=value;}})},
    SpreadsheetApp:{openById:(id)=>String(id).includes("1PvlSlg3")?crm:automation,getActive:()=>crm,getUi:()=>({alert(){}}),CopyPasteType:{PASTE_FORMAT:"format",PASTE_DATA_VALIDATION:"validation"},DataValidationCriteria:{VALUE_IN_RANGE:"VALUE_IN_RANGE"},newDataValidation:()=>new RuleBuilder(),flush:()=>{for(let row=3;row<=201;row++){const sku=products.getRange(row,1).getValue();rrc.getRange(row,1).setValue(sku);rrc.getRange(row,2).setValue(products.getRange(row,3).getValue());rrc.getRange(row,3).setValue(products.getRange(row,4).getValue());rrc.getRange(row,4).setValue(products.getRange(row,7).getValue());}}},
    ContentService:{MimeType:{JSON:"JSON"},createTextOutput:(text)=>({text,setMimeType(){return this;}})},
  });
  vm.runInContext(code+'\napiIntegrityCheck_=function(){return {clean:true,problems:[]};};globalThis.__test={apiAddSku_,apiUpdateRrpBatch_,apiSync3dpCatalogRrp_,setupCrmCatalogOptionInfrastructure};',context,{filename:"Code.gs"});
  return { apiAddSku:context.__test.apiAddSku_,apiUpdateRrpBatch:context.__test.apiUpdateRrpBatch_,apiSync3dpCatalogRrp:context.__test.apiSync3dpCatalogRrp_,setupCatalogOptions:context.__test.setupCrmCatalogOptionInfrastructure,crm,products,rrc,settings };
}

const payload={sku:"BR-CHARM-100",full_name:"Брелок Чармандер (Pokémon) — 3D-друк",brand:"Booster Shop",language:"UA",set:"3D-друк",format:"3D аксесуар",rrp:25,active:true,source:"3d",short_name_mode:"full_name",allow_new_options:true};
{
  const env=makeEnvironment();
  const created=env.apiAddSku(env.crm,payload);
  assert.equal(created.ok,true);
  assert.equal(created.product_row,4);
  assert.equal(env.products.getRange(4,1).getValue(),"BR-CHARM-100");
  assert.match(env.products.getRange(4,2).getFormula(),/\$C4/);
  assert.equal(env.products.getRange(4,10).getFormula(),"=price");
  assert.equal(env.rrc.getRange(4,5).getValue(),25);
  assert.equal(env.rrc.getRange(4,8).getFormula(),"=dynamic");
  const repeated=env.apiAddSku(env.crm,payload);
  assert.equal(repeated.ok,true);
  assert.equal(repeated.already_applied,true);
  const conflict=env.apiAddSku(env.crm,{...payload,rrp:30});
  assert.equal(conflict.ok,false);
  assert.match(conflict.error,/different CRM fields or RRP/);
}

{
  const env=makeEnvironment();
  const accessory={sku:"ACC-ALBUM-001",full_name:"Альбом для карток 9 кишень",brand:"Ultra PRO",language:"",set:"",format:"Альбом",rrp:280,active:true,catalog_kind:"accessory",allow_new_options:true};
  const created=env.apiAddSku(env.crm,accessory);
  assert.equal(created.ok,true);
  assert.equal(created.catalog_kind,"accessory");
  assert.equal(env.products.getRange(4,5).getValue(),"");
  assert.equal(env.products.getRange(4,6).getValue(),"");
  assert.equal(env.products.getRange(4,7).getValue(),"Альбом");
  assert.equal(env.products.getRange(4,2).getFormula(),'=IF($A4="";"";$C4)');
  assert.deepEqual(JSON.parse(JSON.stringify(created.options_added)),["Ultra PRO","Альбом"],"an accessory never writes blank language or set options");
  const repeated=env.apiAddSku(env.crm,accessory);
  assert.equal(repeated.ok,true);
  assert.equal(repeated.already_applied,true);
  const tcgMissingFields=env.apiAddSku(env.crm,{...accessory,sku:"PKM-JP-TEST-001",catalog_kind:"tcg"});
  assert.equal(tcgMissingFields.ok,false);
  assert.match(tcgMissingFields.error,/brand, language, set and format required/);
}

{
  const env=makeEnvironment({missingProductPriceFormula:true});
  const rejected=env.apiAddSku(env.crm,payload);
  assert.equal(rejected.ok,false);
  assert.match(rejected.error,/price formula is missing/);
  assert.equal(env.products.getRange(4,1).getValue(),"");
}

{
  const env=makeEnvironment();
  const updated=env.apiUpdateRrpBatch(env.crm,{changes:[{sku:"EXISTING-001",rrp:15.5,expected_rrp:10}]});
  assert.equal(updated.ok,true);
  assert.equal(updated.rows_updated,1);
  assert.equal(env.rrc.getRange(3,5).getValue(),15.5);
  assert.equal(env.rrc.getRange(3,8).getFormula(),"=dynamic");
  assert.match(String(env.rrc.getRange(3,7).getValue()),/owner dashboard/);
  const stale=env.apiUpdateRrpBatch(env.crm,{changes:[{sku:"EXISTING-001",rrp:16,expected_rrp:10}]});
  assert.equal(stale.ok,false);
  assert.match(stale.error,/refresh the SKU list/);
  assert.equal(env.rrc.getRange(3,5).getValue(),15.5);
  const created=env.apiAddSku(env.crm,payload);
  assert.equal(created.ok,true);
  const threeDp=env.apiUpdateRrpBatch(env.crm,{changes:[{sku:"BR-CHARM-100",rrp:30,expected_rrp:25}]});
  assert.equal(threeDp.ok,false);
  assert.match(threeDp.error,/3D SKU must be edited/);
  assert.equal(env.rrc.getRange(4,5).getValue(),25);
  const legacyThreeDp=makeEnvironment();
  legacyThreeDp.products.getRange(3,6,1,2).setValues([["3D-друк","3D аксесуар"]]);
  const legacyBlocked=legacyThreeDp.apiUpdateRrpBatch(legacyThreeDp.crm,{changes:[{sku:"EXISTING-001",rrp:15,expected_rrp:10}]});
  assert.equal(legacyBlocked.ok,false);
  assert.match(legacyBlocked.error,/3D SKU must be edited/);
}

{
  const env=makeEnvironment();
  const created=env.apiAddSku(env.crm,payload);
  assert.equal(created.ok,true);
  const synced=env.apiSync3dpCatalogRrp(env.crm,{sku:"BR-CHARM-100",rrp:30,expected_rrp:25});
  assert.equal(synced.ok,true);
  assert.equal(synced.action,"sync_3dp_catalog_rrp");
  assert.equal(synced.previous_rrp,25);
  assert.equal(synced.rrp,30);
  assert.equal(synced.integrity_check_required,true);
  assert.equal(env.rrc.getRange(4,5).getValue(),30);
  assert.match(String(env.rrc.getRange(4,7).getValue()),/РРЦ 3D синхронізовано/);
  assert.equal(env.rrc.getRange(4,8).getFormula(),"=dynamic");
  assert.equal(env.products.getRange(4,3).getValue(),payload.full_name,"3D RRP sync must not alter product data");
  const repeated=env.apiSync3dpCatalogRrp(env.crm,{sku:"BR-CHARM-100",rrp:30,expected_rrp:30});
  assert.equal(repeated.ok,true);
  assert.equal(repeated.already_applied,true);
  const stale=env.apiSync3dpCatalogRrp(env.crm,{sku:"BR-CHARM-100",rrp:35,expected_rrp:25});
  assert.equal(stale.ok,false);
  assert.match(stale.error,/refresh and retry/);
  assert.equal(env.rrc.getRange(4,5).getValue(),30);
  const non3d=env.apiSync3dpCatalogRrp(env.crm,{sku:"EXISTING-001",rrp:15,expected_rrp:10});
  assert.equal(non3d.ok,false);
  assert.match(non3d.error,/canonical 3D SKU required/);
}

assert.match(code,/action === 'sync_3dp_catalog_rrp'/,"the owner-only 3D RRP action must be routed by doPost");

{
  const env=makeEnvironment();
  const rrpCell=env.rrc.cell(3,5);rrpCell.formula='=5*2';rrpCell.value=10;
  const updated=env.apiUpdateRrpBatch(env.crm,{changes:[{sku:"EXISTING-001",rrp:15,expected_rrp:10}]});
  assert.equal(updated.ok,true);
  assert.equal(env.rrc.getRange(3,5).getValue(),15);
  assert.equal(env.rrc.getRange(3,5).getFormula(),"");
  assert.equal(env.rrc.getRange(3,8).getFormula(),"=dynamic");
}

{
  const env=makeEnvironment({settingsRows:60});
  env.settings.getRange(4,30,41,1).setValues(Array.from({length:41},(_,index)=>[index===0?"3D-друк":"SET-"+index]));
  const created=env.apiAddSku(env.crm,{...payload,sku:"YGO-JP-BETB-BST",full_name:"Yu-Gi-Oh! — BEYOND THE BRAVE — JP — Booster",set:"BEYOND THE BRAVE",cards_per_booster:5});
  assert.equal(created.ok,true,"a new set uses existing Settings grid capacity beyond the legacy AD44 boundary");
  assert.equal(env.settings.getRange(45,30).getValue(),"BEYOND THE BRAVE");
  assert.equal(env.settings.getMaxRows(),60,"unused Settings rows are used before the grid grows");
  const setRule=env.products.getRange(3,6).getDataValidation();
  const setSource=setRule.getCriteriaValues()[0];
  assert.equal(setRule.getAllowInvalid(),false,"Set keeps strict validation");
  assert.equal(setSource.getRow(),4);
  assert.equal(setSource.getColumn(),30);
  assert.equal(setSource.getNumRows(),57,"Set validation covers the current Settings grid, not AD4:AD44");
}

{
  const env=makeEnvironment({settingsRows:60});
  const setup=env.setupCatalogOptions();
  assert.equal(setup.ok,true);
  assert.deepEqual(JSON.parse(JSON.stringify(setup.validation_fields)),["brand","language","set","format"]);
  assert.equal(setup.integrity_before_clean,true);
  assert.equal(setup.integrity_after_clean,true);
  assert.deepEqual(JSON.parse(JSON.stringify(env.crm.toasts)),[{message:"Довідники SKU перевірено. Валідації оновлено: brand, language, set, format",title:"Booster CRM",timeoutSeconds:10}],"the menu reports completion without blocking the execution");
  assert.equal(env.setupCatalogOptions().already_applied,true,"the public one-time validation migration is idempotent");
}

[
  { field:"brand", settingsColumn:4, legacyRows:7, value:"New Brand", sku:"TST-BRAND-001", payload:{brand:"New Brand"} },
  { field:"language", settingsColumn:7, legacyRows:5, value:"XX", sku:"TST-LANGUAGE-001", payload:{language:"XX"} },
  { field:"format", settingsColumn:10, legacyRows:12, value:"New Format", sku:"TST-FORMAT-001", payload:{format:"New Format"} }
].forEach(({field,settingsColumn,legacyRows,value,sku,payload:overrides}) => {
  const env=makeEnvironment({settingsRows:60});
  env.settings.getRange(4,settingsColumn,legacyRows,1).setValues(Array.from({length:legacyRows},(_,index)=>[index===0?(field==="brand"?"Booster Shop":field==="language"?"UA":"3D аксесуар"):field+"-"+index]));
  const created=env.apiAddSku(env.crm,{...payload,...overrides,sku,full_name:"Reference option capacity test — "+field});
  assert.equal(created.ok,true,"a full legacy "+field+" list accepts one new value");
  assert.equal(env.settings.getRange(4+legacyRows,settingsColumn).getValue(),value);
  assert.ok(created.options_added.includes(value));
});

{
  const env=makeEnvironment({settingsRows:44});
  env.settings.getRange(4,30,41,1).setValues(Array.from({length:41},(_,index)=>[index===0?"3D-друк":"SET-"+index]));
  const created=env.apiAddSku(env.crm,{...payload,sku:"YGO-JP-BETB-BST",full_name:"Yu-Gi-Oh! — BEYOND THE BRAVE — JP — Booster",set:"BEYOND THE BRAVE",cards_per_booster:5});
  assert.equal(created.ok,true,"a full Settings grid grows before the new option is written");
  assert.equal(created.option_capacity.settings_rows_added,50);
  assert.equal(env.settings.getMaxRows(),94);
  assert.equal(env.settings.getRange(45,30).getValue(),"BEYOND THE BRAVE");
  const setSource=env.products.getRange(3,6).getDataValidation().getCriteriaValues()[0];
  assert.equal(setSource.getNumRows(),91,"validation is rebuilt after Settings growth");
}

console.log("CRM catalog SKU create tests passed");
