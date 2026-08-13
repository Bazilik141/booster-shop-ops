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
  getValue(){return this.sheet.cell(this.row,this.col).value??"";}
  getFormulas(){return this.cells().map(row=>row.map(cell=>cell.formula||""));}
  setValues(values){values.forEach((row,r)=>row.forEach((value,c)=>{this.sheet.cell(this.row+r,this.col+c).value=value;}));return this;}
  setValue(value){this.sheet.cell(this.row,this.col).value=value;return this;}
  clearContent(){this.cells().flat().forEach(cell=>{cell.value="";cell.formula="";});return this;}
  clearDataValidations(){return this;}
}
class Sheet{
  constructor(name){this.name=name;this.map=new Map();this.maxRows=500;this.maxColumns=32;}
  key(r,c){return `${r}:${c}`;}
  cell(r,c){const key=this.key(r,c);if(!this.map.has(key))this.map.set(key,{value:"",formula:""});return this.map.get(key);}
  getRange(r,c,rows=1,cols=1){return new Range(this,r,c,rows,cols);}
  getLastRow(){let last=0;for(const [key,cell] of this.map)if(cell.value!==""||cell.formula)last=Math.max(last,Number(key.split(":")[0]));return last;}
  getLastColumn(){let last=0;for(const [key,cell] of this.map)if(cell.value!==""||cell.formula)last=Math.max(last,Number(key.split(":")[1]));return last;}
  getMaxRows(){return this.maxRows;}
  getMaxColumns(){return this.maxColumns;}
  insertColumnsAfter(_,count){this.maxColumns+=count;}
}
class Spreadsheet{constructor(sheets){this.sheets=new Map(sheets.map(sheet=>[sheet.name,sheet]));}getSheetByName(name){return this.sheets.get(name)||null;}}

const sales=new Sheet("Продажі"),writeoffs=new Sheet("Списання"),consumables=new Sheet("Розхідники");
sales.getRange(2,30,1,3).setValues([["Метод собівартості","Аудит собівартості","Дата фіксації собівартості"]]);
const sale=Array(32).fill("");
sale[0]="OC-FOP-0309";sale[2]=new Date("2026-08-10T00:00:00Z");sale[5]="PKM-JP-MBX-XL";sale[6]="Містері бокс Pokémon TCG";sale[7]=1;sale[11]=0;sale[12]=3.26;sale[22]="Оплачено";sale[23]="Отримано";
sales.getRange(3,1,1,32).setValues([sale]);
const writeoffTotals=[[90.50,95.93],[158.12,167.60],[172.22,182.56],[185.44,196.56],[41.07,43.53]];
writeoffs.getRange(3,1,writeoffTotals.length,12).setValues(writeoffTotals.map((cost,index)=>["WRT-X"+index,new Date(),"Інше","SKU-"+index,"",1,"","",cost[0],cost[1],"","Замовлення OC-FOP-0309"]));
consumables.getRange(4,1,3,9).setValues([
  ["Стікер лого+QR","",1.17,100,"",0,"",0,100],
  ["Блайнд-пакет для картки","",1.32,100,"",0,"",0,100],
  ["Наліпка Mystery Box","",0.77,100,"",0,"",0,100],
]);
const spreadsheet=new Spreadsheet([sales,writeoffs,consumables]);
const context=vm.createContext({JSON,Math,Number,String,Boolean,Array,Object,RegExp,Date,Error,isFinite,console,Logger:{log(){}},SpreadsheetApp:{getActive:()=>spreadsheet,openById:()=>spreadsheet},PropertiesService:{getScriptProperties:()=>({getProperty:()=>""})},Utilities:{formatDate:()=>"2026-08-12",sleep(){}},Session:{getScriptTimeZone:()=>"Europe/Kyiv"},ContentService:{MimeType:{JSON:"JSON"},createTextOutput:()=>({setMimeType(){return this;}})}});
vm.runInContext(code+"\nglobalThis.__test={recalculateMysteryBoxOrderCost_,fixSaleCostForRow_};",context,{filename:"Code.gs"});

const preview=context.__test.recalculateMysteryBoxOrderCost_(spreadsheet,"OC-FOP-0309",{dry_run:true});
assert.equal(preview.prro_components,647.35);
assert.equal(preview.mgmt_components,686.18);
assert.equal(preview.consumables,3.26);
assert.equal(preview.prro_unit,647.35);
assert.equal(preview.mgmt_unit,689.44);
assert.equal(sales.getRange(3,12).getValue(),0,"dry-run must not mutate cost");

context.__test.fixSaleCostForRow_(spreadsheet,3,{},{});
assert.equal(sales.getRange(3,12).getValue(),647.35,"later edits restore linked writeoff COGS instead of FIFO zero");
assert.equal(sales.getRange(3,13).getValue(),689.44);
assert.equal(sales.getRange(3,30).getValue(),"MBX фактична комплектація");
console.log("Mystery Box cost repair tests passed");
