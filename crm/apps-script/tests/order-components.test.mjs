import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here=path.dirname(fileURLToPath(import.meta.url));
const code=fs.readFileSync(path.resolve(here,"../Code.gs"),"utf8");

class Range{
  constructor(sheet,row,col,rows=1,cols=1){this.sheet=sheet;this.row=row;this.col=col;this.rows=rows;this.cols=cols;}
  cells(){return Array.from({length:this.rows},(_,r)=>Array.from({length:this.cols},(_,c)=>this.sheet.cell(this.row+r,this.col+c)));}
  getValues(){return this.cells().map(row=>row.map(cell=>cell.value??""));}
  getDisplayValues(){return this.cells().map(row=>row.map(cell=>String(cell.value??"")));}
  getDisplayValue(){return String(this.getValue()??"");}
  getValue(){return this.sheet.cell(this.row,this.col).value??"";}
  getFormula(){return this.sheet.cell(this.row,this.col).formula||"";}
  setValue(value){const cell=this.sheet.cell(this.row,this.col);cell.value=value;cell.formula="";return this;}
  setValues(values){values.forEach((row,r)=>row.forEach((value,c)=>{const cell=this.sheet.cell(this.row+r,this.col+c);cell.value=value;cell.formula="";}));return this;}
  setFormula(formula){this.sheet.cell(this.row,this.col).formula=formula;return this;}
  clearContent(){this.cells().flat().forEach(cell=>{cell.value="";cell.formula="";});return this;}
}
class Sheet{
  constructor(name){this.name=name;this.map=new Map();}
  key(r,c){return `${r}:${c}`;}
  cell(r,c){const key=this.key(r,c);if(!this.map.has(key))this.map.set(key,{value:"",formula:""});return this.map.get(key);}
  getRange(r,c,rows=1,cols=1){if(typeof r==="string"){const match=/^([A-Z]+)(\d+)(?::([A-Z]+)(\d+))?$/.exec(r);if(!match)throw new Error("bad A1: "+r);const col=s=>[...s].reduce((n,ch)=>n*26+ch.charCodeAt(0)-64,0),startCol=col(match[1]),startRow=Number(match[2]),endCol=col(match[3]||match[1]),endRow=Number(match[4]||match[2]);return new Range(this,startRow,startCol,endRow-startRow+1,endCol-startCol+1);}return new Range(this,r,c,rows,cols);}
  getLastRow(){let last=0;for(const [key,cell] of this.map)if(cell.value!==""||cell.formula)last=Math.max(last,Number(key.split(":")[0]));return last;}
  getName(){return this.name;}
  setFrozenRows(){return this;}
}
class Spreadsheet{
  constructor(sheets){this.sheets=new Map(sheets.map(sheet=>[sheet.name,sheet]));}
  getSheetByName(name){return this.sheets.get(name)||null;}
  insertSheet(name){const sheet=new Sheet(name);this.sheets.set(name,sheet);return sheet;}
}

const stock=new Sheet("Склад"),consumables=new Sheet("Розхідники");
stock.getRange(3,1,3,10).setValues([
  ["PKM-TEST","Test booster","Pokémon","Booster",10,1,0,9,40,45],
  ["EMPTY","Empty","Pokémon","Booster",1,1,0,0,20,22],
  ["BR-CHARM-001","Stale CRM 3D name","3D","3D",1,1,0,99,1,1],
]);
consumables.getRange(4,1,3,15).setValues([
  ["Пакет","Упаковка",3,10,0,0,0,2,8,0,24,"","","",""],
  ["FUR-TEST","Фурнітура",2,0,0,0,0,0,0,10,0,"","","","власник"],
  ["Немає","Маркетинг",5,0,0,0,0,0,0,0,0,"","","",""],
]);
const crm=new Spreadsheet([stock,consumables]);
const scriptProperties={};
const context=vm.createContext({JSON,Math,Number,String,Boolean,Array,Object,RegExp,Date,Error,isFinite,console,
  Logger:{log(){}},SpreadsheetApp:{openById:()=>crm,getActive:()=>crm,flush(){}},PropertiesService:{getScriptProperties:()=>({getProperty:key=>scriptProperties[key]||"",setProperty(key,value){scriptProperties[key]=value;}})},
  Utilities:{formatDate:()=>"2026-08-12"},Session:{getScriptTimeZone:()=>"Europe/Kyiv"},ContentService:{MimeType:{JSON:"JSON"},createTextOutput:()=>({setMimeType(){return this;}})}
});
vm.runInContext(code+"\nglobalThis.__test={apiOrderComponentCatalog_,buildOrderComponentPlan_,replaceOrderComponentAudit_,normalizeRepeatedExactNote_,setupOrderComponentUsage,orderUpdateRequestState_,orderComponentMarketingByOrder_,crm3dpAccountingSnapshot_,repair3dpExpenseProjectionFormulas_,expenseProjectionFormulas3dp_,apiAddConsumablePurchase_,apiUpdateConsumablePurchase_,allocateAmount_};",context,{filename:"Code.gs"});

const catalog=context.__test.apiOrderComponentCatalog_();
assert.equal(catalog.ok,true);
assert.deepEqual(JSON.parse(JSON.stringify(catalog.components.map(item=>item.id))),["consumable:Пакет","sku:PKM-TEST"]);
assert.equal(catalog.fixtures[0].selection,"FUR-TEST | власник");
assert.equal(catalog.fixtures[0].stock,0,"fixture shortage remains selectable under F6");
assert.match(catalog.three_dp_error,/не налаштовано/);
assert.equal(catalog.components.some(item=>item.code==="BR-CHARM-001"),false,"stale CRM stock never masquerades as 3D source truth");
scriptProperties.BOOSTER_3DP_URL="https://example.test/exec";
scriptProperties.BOOSTER_3DP_SYNC_TOKEN="test-token";
const originalGet=context.crm3dpGet_;
context.crm3dpGet_=()=>({rows:[{SKU:"BR-CHARM-001","Назва виробу":"Брелок Чармандер","Ціна під викуп, грн":20,API_статус_запису:"Активний",availability:{"Наявно зараз, шт":36}}]});
const remoteCatalog=context.__test.apiOrderComponentCatalog_();
const remote3d=remoteCatalog.components.find(item=>item.id==="3dp:BR-CHARM-001");
assert.equal(remote3d.kind,"3D-P");
assert.equal(remote3d.name,"Брелок Чармандер");
assert.equal(remote3d.stock,36);
assert.equal(remote3d.mgmt_unit,20);
context.crm3dpGet_=originalGet;
delete scriptProperties.BOOSTER_3DP_URL;delete scriptProperties.BOOSTER_3DP_SYNC_TOKEN;
assert.match(code,/kind: '3D-P'/,"component catalog has a direct 3D-P source-truth path");
assert.match(code,/append3dpOrderGifts_\(componentPlan, current\[2\], order, requestId\)/,"3D gifts are written remotely before the local component ledger");
assert.match(code,/action: '3dp_order_gifts_append'/,"CRM uses the idempotent specialized 3D gift action");
assert.match(code,/retry_action: changed \? 'resubmit_order_update'/,"a partial writer failure resumes the stable order update instead of offering a sync-only retry");

const plan=context.__test.buildOrderComponentPlan_(crm,[{id:"sku:PKM-TEST",qty:2},{id:"consumable:Пакет",qty:1}]);
assert.equal(plan.ok,true);
assert.equal(plan.prro_total,80);
assert.equal(plan.mgmt_total,93);
assert.equal(plan.entries[1].targetRow,0,"an order-level consumable does not require a target sale row");
assert.equal(plan.entries[1].targetSku,"");
const targeted=context.__test.buildOrderComponentPlan_(crm,[{id:"consumable:Пакет",qty:1,target_row:268,target_sku:"BR-CHARM-100"}]);
assert.equal(targeted.entries[0].targetRow,268);
assert.equal(targeted.entries[0].targetSku,"BR-CHARM-100");
const shortage=context.__test.buildOrderComponentPlan_(crm,[{id:"sku:PKM-TEST",qty:10}]);
assert.equal(shortage.ok,false);
assert.match(shortage.error,/Недостатньо на складі/);

consumables.getRange(4,8).setFormula("=7");
consumables.getRange(6,8).setValue(4);
const setup=context.__test.setupOrderComponentUsage();
assert.equal(setup.ok,true);
assert.equal(setup.consumable_formulas_updated,2);
assert.match(consumables.getRange(4,8).getFormula(),/\(7\)\+IFNA\(SUMIFS\(Використання_компонентів/);
assert.equal(consumables.getRange(5,8).getFormula(),"","fixture formula is owned by the separate fixture ledger");
assert.match(consumables.getRange(6,8).getFormula(),/^=4\+IFNA/);
assert.equal(context.__test.setupOrderComponentUsage().already_applied,true);
const expenses=crm.insertSheet("Витрати"),expenseFormulas=context.__test.expenseProjectionFormulas3dp_();
expenses.getRange(3,1,3,13).setValues([
  [new Date(),"Реклама","",1,"Ні","","","","","","","#REF!","#REF!"],
  [new Date(),"Пакування","",1,"Ні","","","","","","","Ні","Розхідник: не в операційці"],
  [new Date(),"Реклама","",1,"Ні","","","","","","","Так","Рахується в операційці"],
]);
expenses.getRange("L3").setFormula(expenseFormulas.L);expenses.getRange("L3").setValue("#REF!");expenses.getRange("L3").setFormula(expenseFormulas.L);expenses.cell(3,12).value="#REF!";
expenses.getRange("M3").setFormula(expenseFormulas.M);expenses.cell(3,13).value="#REF!";
assert.equal(context.__test.repair3dpExpenseProjectionFormulas_(crm),2);
assert.equal(expenses.getRange("L3").getFormula(),expenseFormulas.L);
assert.equal(expenses.getRange("M3").getFormula(),expenseFormulas.M);
assert.equal(context.__test.repair3dpExpenseProjectionFormulas_(crm),0,"healthy ARRAYFORMULA spill is not treated as 52 manual blockers");
const consumablePurchase=context.__test.apiAddConsumablePurchase_(crm,{name:"Пакет",date:"2026-08-12",qty:20,total_cost:70,status:"Замовлено",reference:"TEST-CONS-1",description:"Тестова закупка"});
assert.equal(consumablePurchase.ok,true);
assert.equal(consumablePurchase.catalog_created,false);
assert.equal(expenses.getRange(consumablePurchase.row_index,8).getValue(),"Пакет");
assert.equal(expenses.getRange(consumablePurchase.row_index,10).getValue(),"Замовлено");
assert.match(consumables.getRange(4,7).getFormula(),/"Замовлено"/,"ordered stock is included in incoming quantity");
const received=context.__test.apiUpdateConsumablePurchase_(crm,{row_index:consumablePurchase.row_index,status:"На складі",expected_status:"Замовлено"});
assert.equal(received.ok,true);
assert.equal(expenses.getRange(consumablePurchase.row_index,10).getValue(),"На складі");
const componentLedger=crm.getSheetByName("Використання_компонентів");
assert.deepEqual(componentLedger.getRange(1,14,1,2).getValues()[0],["CRM row number","SKU цілі"]);
componentLedger.getRange(2,1,1,11).setValues([["CMP-USE-00001",new Date(),"MAN-FOP-0005","SKU","PKM-TEST",1,40,45,40,45,"[dashboard_request:dashboard-12345678]"]]);
componentLedger.getRange(3,1,1,15).setValues([["CMP-USE-00002",new Date(),"MAN-FOP-0005","Розхідник","Пакет",1,3,3,3,3,"linked mystery-box component","","",3,"BR-CHARM-100"]]);
const sales=crm.insertSheet("Продажі");
sales.getRange(3,1,2,11).setValues([
  ["MAN-FOP-0005","Вручну",new Date(),"","","BR-CHARM-100","Брелок",1,25,0,25],
  ["MAN-FOP-0005","Вручну",new Date(),"","","PKM-TEST","Бустер",1,148,0,148],
]);
const marketing=context.__test.orderComponentMarketingByOrder_(crm);
assert.equal(marketing.byOrder["MAN-FOP-0005"],45,"component management cost is exposed as order Marketing");
assert.equal(marketing.byRow[3]+marketing.byRow[4],45,"order-level gifts are allocated across sale rows exactly once");
assert.equal(marketing.byOrder["MAN-FOP-0005"],45,"a line-targeted component is excluded from order Marketing");
const requestState=context.__test.orderUpdateRequestState_(crm,"MAN-FOP-0005","dashboard-12345678");
assert.equal(requestState.component,true);
assert.equal(requestState.fixture,false);

const audit=context.__test.replaceOrderComponentAudit_("FIFO; order_components_prro=10,mgmt=12",15,20);
assert.equal(audit,"FIFO; order_components_prro=15,mgmt=20");
assert.equal(context.__test.normalizeRepeatedExactNote_("Паковання: Інше; Паковання: Інше; Паковання: Інше"),"Паковання: Інше");
assert.equal(context.__test.normalizeRepeatedExactNote_("Паковання: Інше; крихке"),"Паковання: Інше; крихке","mixed notes are never collapsed");
const saleSnapshot=context.__test.crm3dpAccountingSnapshot_({row:268,values:["MAN-FOP-0005","Вручну",new Date(),"","","BR-CHARM-100","Брелок",1,25,0,25,"","","","",0.29]},"MAN-FOP-0005",{production_cost:4.89,buyout:20,profit_share:0.5},{owner_total:2.96,serhiy_total:0,owner_per_unit:2.96,serhiy_per_unit:0},"Продаж","dashboard-test");
assert.equal(saleSnapshot.serhiy_payout,13.32);
assert.equal(saleSnapshot.mgmt_cost,16.28);
assert.equal(saleSnapshot.marketing,0);
const serhiyFixtureSale=context.__test.crm3dpAccountingSnapshot_({row:269,values:["MAN-FOP-0006","Вручну",new Date(),"","","BR-CHARM-100","Брелок",1,25,0,25,"","","","",0.29]},"MAN-FOP-0006",{production_cost:4.89,buyout:20,profit_share:0.5},{owner_total:0,serhiy_total:3,owner_per_unit:0,serhiy_per_unit:3},"Продаж","dashboard-test-serhiy");
assert.equal(serhiyFixtureSale.serhiy_payout,16.3,"Serhiy fixture is reimbursed in full after reducing the 50/50 margin base");
assert.equal(serhiyFixtureSale.mgmt_cost,16.3);
const marketingSnapshot=context.__test.crm3dpAccountingSnapshot_({row:268,values:["MAN-FOP-0005","Вручну",new Date(),"","","BR-CHARM-100","Брелок",1,0,0,0,"","","","",0]},"MAN-FOP-0005",{production_cost:4.89,buyout:20,profit_share:0.5},{owner_total:2.96,serhiy_total:0,owner_per_unit:2.96,serhiy_per_unit:0},"Маркетинг","dashboard-test-2");
assert.equal(marketingSnapshot.serhiy_payout,20);
assert.equal(marketingSnapshot.mgmt_cost,22.96);
assert.equal(marketingSnapshot.marketing,22.96);
assert.deepEqual(JSON.parse(JSON.stringify(context.__test.allocateAmount_(100,[100,1000,700]))),[5.56,55.56,38.88]);
assert.deepEqual(JSON.parse(JSON.stringify(context.__test.allocateAmount_(80,[100,1000,700]))),[4.44,44.44,31.12]);
assert.deepEqual(JSON.parse(JSON.stringify(context.__test.allocateAmount_(120,[100,1000,700]))),[6.67,66.67,46.66]);
assert.match(code,/update_sale'\) return boosterCrmJson_\(apiUpdateSaleWithComponents_/);
assert.match(code,/if \(!item\.targetRow && !item\.targetSku\) return;/,"blank component target is accepted as order-level allocation");
assert.match(code,/recalculateMysteryBoxOrderCost_\(ss, orderKey\)/,"later order edits restore Mystery Box cost from linked writeoffs");
assert.match(code,/\? applyOrderComponentCost_\(ss, order, rows\)[\s\S]*if \(componentCost\.rows_updated\)/,"existing order components are reapplied after every base-cost refresh");
assert.match(code,/desiredPackaging = crm3dpRound2_\(entryQuantity > 0 \? crm3dpNumber_\(entry\.values\[15\]\) \/ entryQuantity : 0\)/,"3D-P G remains a per-unit packaging value");
assert.match(code,/add_consumable_purchase'\) return boosterCrmJson_\(apiAddConsumablePurchase_/);
assert.match(code,/update_consumable_purchase'\) return boosterCrmJson_\(apiUpdateConsumablePurchase_/);
assert.match(code,/retry_3dp_sync'\) return boosterCrmJson_\(apiRetry3dpOrderSync_/);
assert.match(code,/apiRetry3dpOrderSync_[\s\S]*sync3dpPackagingCost_\(sales, order, rows, 'apiRetry3dpOrderSync_'/,"retry-only path calls 3D-P sync without component or fixture writers");
assert.match(code,/current_cost_refresh: 'deferred_to_nightly_inventory_maintenance'/,"new manual sales do not block on a full catalog-cost rebuild");
assert.match(code,/const CRM_TEST_ORDER_CLEANUP_ = Object\.freeze/);
assert.match(code,/function apiTestOrderCleanup_\(ss, payload\)/);
assert.doesNotMatch(code,/CRM_MYSTERY_COST_REPAIR_ORDERS_/);
assert.doesNotMatch(code,/function previewTestOrderPurge\(\)/);
assert.match(code,/CRM_3DP_PROFIT_SHARE_HEADER_/);
console.log("CRM order component tests passed");
