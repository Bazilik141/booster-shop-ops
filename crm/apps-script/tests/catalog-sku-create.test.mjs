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
  getValue() { return this.sheet.cell(this.row, this.column).value ?? ""; }
  getDisplayValue() { return String(this.getValue()); }
  getFormula() { return this.sheet.cell(this.row, this.column).formula || ""; }
  getCell(row, column) { return new MockRange(this.sheet, this.row + row - 1, this.column + column - 1); }
  setValue(value) { const cell = this.sheet.cell(this.row, this.column);cell.value=value;cell.formula="";return this; }
  setFormula(formula) { const cell = this.sheet.cell(this.row, this.column);cell.formula=formula;cell.value="";return this; }
  setValues(values) { values.forEach((row, r) => row.forEach((value, c) => { const cell=this.sheet.cell(this.row+r,this.column+c);cell.value=value;cell.formula=""; }));return this; }
  clearContent() { this.matrix((cell) => { cell.value="";cell.formula="";return null; });return this; }
}

class MockSheet {
  constructor(name) { this.name=name;this.cells=new Map(); }
  key(row,column){return row+":"+column;}
  cell(row,column){const key=this.key(row,column);if(!this.cells.has(key))this.cells.set(key,{value:"",formula:""});return this.cells.get(key);}
  getRange(...args){if(typeof args[0]==="string"){const x=parseA1(args[0]);return new MockRange(this,x.row,x.column,x.rows,x.columns);}return new MockRange(this,args[0],args[1],args[2]||1,args[3]||1);}
  getLastRow(){let last=0;for(const [key,cell] of this.cells){if(cell.value!=="" || cell.formula)last=Math.max(last,Number(key.split(":")[0]));}return last;}
  getName(){return this.name;}
}

class MockSpreadsheet {
  constructor(sheets){this.sheets=new Map(sheets.map((sheet)=>[sheet.name,sheet]));}
  getSheetByName(name){return this.sheets.get(name)||null;}
}

function makeEnvironment({ missingProductPriceFormula = false } = {}) {
  const products=new MockSheet("Товари"),rrc=new MockSheet("РРЦ"),settings=new MockSheet("Налаштування"),master=new MockSheet("Майстер_Товарів");
  products.getRange(3,1,1,15).setValues([["EXISTING-001","","Existing","Booster Shop","UA","Pokemon","Бустер","","","",0,"Так","","",""]]);
  products.getRange(3,10).setFormula("=price");
  rrc.getRange(3,1,1,8).setValues([["EXISTING-001","Existing","Booster Shop","3D аксесуар",10,new Date("2026-08-12"),"",""]]);
  rrc.getRange(3,8).setFormula("=dynamic");
  if(!missingProductPriceFormula)products.getRange(4,10).setFormula("=price");
  rrc.getRange(4,8).setFormula("=dynamic");
  settings.getRange(4,4).setValue("Booster Shop");
  settings.getRange(4,7).setValue("UA");
  settings.getRange(4,10).setValue("3D аксесуар");
  settings.getRange(4,30).setValue("3D-друк");
  const crm=new MockSpreadsheet([products,rrc,settings]);
  const automation=new MockSpreadsheet([master]);
  const properties={};
  const context=vm.createContext({
    JSON,Math,Number,String,Boolean,Array,Object,RegExp,Date,Error,isFinite,
    Logger:{log(){}},Session:{getScriptTimeZone:()=>"Europe/Kyiv"},Utilities:{formatDate:()=>"2026-08-12"},
    PropertiesService:{getScriptProperties:()=>({getProperty:(key)=>properties[key]||"",setProperty:(key,value)=>{properties[key]=value;}})},
    SpreadsheetApp:{openById:(id)=>String(id).includes("1PvlSlg3")?crm:automation,flush:()=>{for(let row=3;row<=201;row++){const sku=products.getRange(row,1).getValue();rrc.getRange(row,1).setValue(sku);rrc.getRange(row,2).setValue(products.getRange(row,3).getValue());rrc.getRange(row,3).setValue(products.getRange(row,4).getValue());rrc.getRange(row,4).setValue(products.getRange(row,7).getValue());}}},
    ContentService:{MimeType:{JSON:"JSON"},createTextOutput:(text)=>({text,setMimeType(){return this;}})},
  });
  vm.runInContext(code+'\nglobalThis.__test={apiAddSku_,apiUpdateRrpBatch_};',context,{filename:"Code.gs"});
  return { apiAddSku:context.__test.apiAddSku_,apiUpdateRrpBatch:context.__test.apiUpdateRrpBatch_,crm,products,rrc,settings };
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
  const rrpCell=env.rrc.cell(3,5);rrpCell.formula='=5*2';rrpCell.value=10;
  const updated=env.apiUpdateRrpBatch(env.crm,{changes:[{sku:"EXISTING-001",rrp:15,expected_rrp:10}]});
  assert.equal(updated.ok,true);
  assert.equal(env.rrc.getRange(3,5).getValue(),15);
  assert.equal(env.rrc.getRange(3,5).getFormula(),"");
  assert.equal(env.rrc.getRange(3,8).getFormula(),"=dynamic");
}

console.log("CRM catalog SKU create tests passed");
