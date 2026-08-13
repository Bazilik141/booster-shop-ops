import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import vm from "node:vm";
import { fileURLToPath } from "node:url";

const here=path.dirname(fileURLToPath(import.meta.url));
const code=fs.readFileSync(path.resolve(here,"../Code.gs"),"utf8");
const row=(order,date,phone,name,revenue,profit,channel="Вручну")=>{const values=Array(32).fill("");values[0]=order;values[1]=channel;values[2]=new Date(date);values[3]=phone;values[4]=name;values[7]=1;values[10]=revenue;values[21]=profit;return values;};
const rows=[
  row("A-1","2026-08-01","380000000001","Repeat",100,20),row("A-2","2026-08-02","380000000001","Repeat",100,20),
  row("B-1","2026-08-03","380000000002","Spend",1601,300),
  row("C-1","2025-01-01","380000000003","Margin",100,50),
  row("D-1","2026-08-04","380000000004","Ignored",100,20),
];
const context=vm.createContext({JSON,Math,Number,String,Boolean,Array,Object,RegExp,Date,Error,isFinite,console,Logger:{log(){}},SpreadsheetApp:{getActive:()=>null,openById:()=>null},PropertiesService:{getScriptProperties:()=>({getProperty:()=>""})},Utilities:{formatDate:()=>"2026-08-12",sleep(){}},Session:{getScriptTimeZone:()=>"Europe/Kyiv"},ContentService:{MimeType:{JSON:"JSON"},createTextOutput:()=>({setMimeType(){return this;}})}});
vm.runInContext(code+"\napiReadCrmSalesRows_=function(){return globalThis.__rows;};globalThis.__test={apiQualifiedClientsReport_};",context,{filename:"Code.gs"});
context.__rows=rows;
const result=context.__test.apiQualifiedClientsReport_({limit:500});
assert.equal(result.ok,true);
assert.equal(result.total_matching,3);
assert.deepEqual(JSON.parse(JSON.stringify(result.clients.map(client=>client.name))),["Spend","Repeat","Margin"],"default sort is spend_60d descending");
assert.deepEqual(JSON.parse(JSON.stringify(result.clients[0].qualification)),["spend_60d"]);
assert.deepEqual(JSON.parse(JSON.stringify(result.clients[1].qualification)),["repeat"]);
assert.deepEqual(JSON.parse(JSON.stringify(result.clients[2].qualification)),["margin"]);
console.log("Qualified clients report tests passed");
