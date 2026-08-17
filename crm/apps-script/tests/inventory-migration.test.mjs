import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here=path.dirname(fileURLToPath(import.meta.url));
const code=fs.readFileSync(path.resolve(here,"../Code.gs"),"utf8");

class Range {
  constructor(sheet,row,column,rows=1,columns=1){this.sheet=sheet;this.row=row;this.column=column;this.rows=rows;this.columns=columns;}
  cells(fn){return Array.from({length:this.rows},(_,r)=>Array.from({length:this.columns},(_,c)=>fn(this.sheet.cell(this.row+r,this.column+c))));}
  getValues(){return this.cells(cell=>cell.value);}
  getDisplayValues(){return this.cells(cell=>String(cell.value??""));}
  getFormulas(){return this.cells(cell=>cell.formula);}
  getValue(){return this.sheet.cell(this.row,this.column).value;}
  getFormula(){return this.sheet.cell(this.row,this.column).formula;}
  setValues(values){values.forEach((row,r)=>row.forEach((value,c)=>{const cell=this.sheet.cell(this.row+r,this.column+c);cell.value=value;cell.formula="";}));return this;}
  setValue(value){return this.setValues([[value]]);}
  setFormula(formula){const cell=this.sheet.cell(this.row,this.column);cell.formula=formula;return this;}
  clearContent(){this.cells(cell=>{cell.value="";cell.formula="";return null;});return this;}
}

class Sheet {
  constructor(name,maxRows=200){this.name=name;this.maxRows=maxRows;this.data=new Map();}
  key(row,column){return row+":"+column;}
  cell(row,column){const key=this.key(row,column);if(!this.data.has(key))this.data.set(key,{value:"",formula:""});return this.data.get(key);}
  getRange(row,column,rows=1,columns=1){return new Range(this,row,column,rows,columns);}
  getLastRow(){let last=0;for(const [key,cell] of this.data){if(cell.value!==""||cell.formula)last=Math.max(last,Number(key.split(":")[0]));}return last;}
  getMaxRows(){return this.maxRows;}
  insertRowsAfter(row,count){assert.equal(row,this.maxRows);this.maxRows+=count;return this;}
  setFrozenRows(){return this;}
}

class Spreadsheet {
  constructor(sheets){this.sheets=new Map(sheets.map(sheet=>[sheet.name,sheet]));}
  getSheetByName(name){return this.sheets.get(name)||null;}
  insertSheet(name){const sheet=new Sheet(name);this.sheets.set(name,sheet);return sheet;}
}

function makeEnvironment(){
  const products=new Sheet("Товари"),stock=new Sheet("Склад"),purchases=new Sheet("Закупки"),sales=new Sheet("Продажі"),writeoffs=new Sheet("Списання");
  products.getRange(3,1,3,12).setValues([
    ["BOX-001","","Test box","","","","Booster Box","","","","","Так"],
    ["PACK-001","","Test pack","","","","Booster","","","","","Так"],
    ["PKM-JP-OUTL-BST","","Outlet Mix","","","","Booster","","","","","Так"]
  ]);
  stock.getRange(3,1,3,10).setValues([
    ["BOX-001","","","","","","",2,100,110],
    ["PACK-001","","","","","","",0,0,0],
    ["PKM-JP-OUTL-BST","","","","","","",0,0,0]
  ]);
  [3,4,5].forEach(row=>stock.getRange(row,8).setFormula('=IF($A'+row+'="";"";$E'+row+'-$F'+row+'-$G'+row+')'));
  const lot=Array(18).fill("");lot[0]="LOT-0001";lot[3]=new Date("2026-08-01");lot[4]="BOX-001";lot[7]=2;lot[12]=100;lot[15]=110;lot[16]="На складі";
  purchases.getRange(3,1,1,18).setValues([lot]);
  const ss=new Spreadsheet([products,stock,purchases,sales,writeoffs]);
  const context=vm.createContext({
    JSON,Math,Number,String,Boolean,Array,Object,RegExp,Date,Error,isFinite,
    Logger:{log(){}},SpreadsheetApp:{flush(){}},Utilities:{},Session:{},
    PropertiesService:{getScriptProperties:()=>({getProperty:()=>"",setProperty(){}})}
  });
  vm.runInContext(code+`\napiIntegrityCheck_=function(){return {clean:true,problems:[]};};resetMemoForMutation_=function(){};updateSkuCurrentCost_=function(){};invalidateDoGetCache_=function(){};_getCrmSs=function(){return globalThis.__crm;};inventoryMigrationVerify_=function(ss,request,before,plans,rows){const after=inventoryMigrationStockSnapshot_(ss).available;if(Math.abs(after[request.sourceSku]-(before[request.sourceSku]-request.sourceQty))>0.000001)throw new Error('source snapshot mismatch');if(Math.abs(after[request.targetSku]-((before[request.targetSku]||0)+request.targetQty))>0.000001)throw new Error('target snapshot mismatch');return {source_available:after[request.sourceSku],target_available:after[request.targetSku],management_transferred:rows.reduce(function(sum,row){return sum+Number(row[11]||0);},0)};};globalThis.__test={apiInventoryMigration_,apiInventoryMigrationContext_,inventoryMigrationStockSnapshot_,getFifoCostBatches_};`,context,{filename:"Code.gs"});
  context.__crm=ss;
  return {ss,stock,context};
}

const {ss,stock,context}=makeEnvironment();
const first=context.__test.apiInventoryMigration_(ss,{action:"inventory_migration",type:"box_to_packs",source_sku:"BOX-001",target_sku:"PACK-001",target_qty:36,expected_source_available:2,request_id:"migration_box_to_pack_001"});
assert.equal(first.ok,true);
assert.equal(first.operation_id,"MIG-0001");
assert.equal(first.source_qty,1);
assert.equal(first.target_qty,36);
const ledger=ss.getSheetByName("Міграції_Складу");
assert.equal(ledger.getRange(1,1).getValue(),"ID міграції");
assert.equal(ledger.getRange(2,1).getValue(),"MIG-0001");
assert.equal(ledger.getRange(2,4).getValue(),"BOX-001");
assert.equal(ledger.getRange(2,5).getValue(),"PACK-001");
assert.equal(ledger.getRange(2,6).getValue(),"LOT-0001");
assert.equal(ledger.getRange(2,7).getValue(),1);
assert.equal(ledger.getRange(2,8).getValue(),36);
assert.equal(ledger.getRange(2,11).getValue(),100,"full box cost is carried once, not duplicated");
assert.equal(ledger.getRange(2,12).getValue(),110);
assert.match(stock.getRange(3,8).getFormula(),/Міграції_Складу/);
assert.match(stock.getRange(4,8).getFormula(),/Міграції_Складу/);
let snapshot=context.__test.inventoryMigrationStockSnapshot_(ss).available;
assert.equal(snapshot["BOX-001"],1);
assert.equal(snapshot["PACK-001"],36);
const packBatches=context.__test.getFifoCostBatches_(ss,"PACK-001",null);
assert.equal(packBatches.length,1);
assert.equal(packBatches[0].lotId,"MIG-0001/LOT-0001");
assert.equal(packBatches[0].qty,36);
assert.ok(Math.abs(packBatches[0].prroUnit-(100/36))<0.000001);
const repeated=context.__test.apiInventoryMigration_(ss,{action:"inventory_migration",type:"box_to_packs",source_sku:"BOX-001",target_sku:"PACK-001",target_qty:36,expected_source_available:1,request_id:"migration_box_to_pack_001"});
assert.equal(repeated.ok,true);
assert.equal(repeated.already_applied,true);
assert.equal(ledger.getLastRow(),2,"a repeated request does not append a second movement");
const outlet=context.__test.apiInventoryMigration_(ss,{action:"inventory_migration",type:"packs_to_outlet",source_sku:"PACK-001",source_qty:5,expected_source_available:36,request_id:"migration_pack_to_outlet_001"});
assert.equal(outlet.ok,true);
assert.equal(outlet.operation_id,"MIG-0002");
assert.equal(ledger.getRange(3,4).getValue(),"PACK-001");
assert.equal(ledger.getRange(3,5).getValue(),"PKM-JP-OUTL-BST");
assert.equal(ledger.getRange(3,7).getValue(),5);
assert.equal(ledger.getRange(3,8).getValue(),5);
snapshot=context.__test.inventoryMigrationStockSnapshot_(ss).available;
assert.equal(snapshot["BOX-001"],1);
assert.equal(snapshot["PACK-001"],31);
assert.equal(snapshot["PKM-JP-OUTL-BST"],5);
const outletBatches=context.__test.getFifoCostBatches_(ss,"PKM-JP-OUTL-BST",null);
assert.equal(outletBatches.length,1);
assert.ok(Math.abs(outletBatches[0].prroUnit-(100/36))<0.000001,"outlet receives the exact transferred pack cost");
const contextResult=context.__test.apiInventoryMigrationContext_();
assert.equal(contextResult.outlet.sku,"PKM-JP-OUTL-BST");
assert.equal(contextResult.boxes[0].available,1);
assert.equal(contextResult.pack_sources[0].available,31);
console.log("Inventory migration FIFO and idempotency tests passed");
