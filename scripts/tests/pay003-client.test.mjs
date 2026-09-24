import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source=fs.readFileSync('work/pay003/candidate/catalog/view/javascript/pay003-credit.js','utf8');
let checks=0;
function harness(){
 let now=0,seq=0;const timers=new Map(),calls=[],redirects=[];
 const elements=Object.fromEntries(['pay003-credit','pay003-refresh','pay003-network','pay003-title','pay003-message'].map(id=>[id,{dataset:{},textContent:'',disabled:false,handlers:{},addEventListener(name,fn){this.handlers[name]=fn;}}]));
 elements['pay003-credit'].dataset={stateUrl:'/state',completeUrl:'/complete',csrf:'synthetic',poll:'1',confirmed:'0'};
 const doc={hidden:false,handlers:{},getElementById:id=>elements[id],addEventListener(name,fn){this.handlers[name]=fn;}};
 const waiting={title:'Очікування',message:'Підтвердьте заявку',poll:true,confirmed:false,offline:false,redirect:null};
 let response={status:200,data:waiting};
 class Clock extends Date{static now(){return now;}}
 const context={document:doc,window:{location:{href:'https://shop.invalid/credit',origin:'https://shop.invalid',assign:url=>redirects.push(url)},addEventListener(){}},URL,URLSearchParams,AbortController,Date:Clock,setTimeout(fn,ms){timers.set(++seq,{fn,at:now+ms});return seq;},clearTimeout:id=>timers.delete(id),fetch:async(url,options)=>{calls.push({url,...options});return{ok:response.status===200,status:response.status,json:async()=>response.data};}};
 vm.runInNewContext(source,context);
 const flush=async()=>{for(let i=0;i<15;i++)await Promise.resolve();};
 return{elements,calls,redirects,timers,doc,set(r){response=r;},async click(){elements['pay003-refresh'].handlers.click();await flush();},async advance(ms){const end=now+ms;while(true){const entries=[...timers].filter(([,v])=>v.at<=end).sort((a,b)=>a[1].at-b[1].at);if(!entries.length)break;const[id,t]=entries[0];timers.delete(id);now=t.at;t.fn();await flush();}now=end;await flush();},waiting};
}
function check(ok,name){assert.ok(ok,name);checks++;}
let h=harness();check(h.calls.length===0,'no immediate bank request');await h.advance(5000);check(h.calls.length===1&&!h.calls[0].method,'initial DB-only GET');await h.advance(26000);check(h.calls.filter(c=>c.method==='POST').length===1,'bank fallback at 30s');await h.click();check(h.calls.filter(c=>c.method==='POST').length===1,'manual early read DB-only');
h.set({status:503,data:{}});await h.click();check(h.elements['pay003-network'].textContent.includes('Не вдалося'),'network error visible');check(!h.elements['pay003-refresh'].disabled,'retry button released');
h=harness();h.set({status:200,data:{title:'Відмовлено',message:'Зверніться до підтримки',poll:false,confirmed:false}});await h.click();const n=h.calls.length;await h.advance(100000);check(h.calls.length===n,'terminal stops polling');
h=harness();h.set({status:403,data:{}});await h.click();await h.advance(100000);check(h.calls.length===1&&h.elements['pay003-network'].textContent.includes('Доступ'),'auth failure stops');
h=harness();h.set({status:200,data:{...h.waiting,confirmed:true,poll:false,redirect:'/complete'}});await h.click();check(h.redirects[0]==='https://shop.invalid/complete','same-origin confirmed redirect');
h=harness();h.set({status:200,data:{...h.waiting,confirmed:true,poll:false,redirect:'https://evil.invalid/'}});await h.click();check(h.redirects.length===0,'foreign redirect refused');
h=harness();await h.advance(1000000);check(h.calls.length===24,'automatic checks capped');await h.click();check(h.calls.length===25,'manual check after cap');
h=harness();h.doc.hidden=true;h.doc.handlers.visibilitychange();await h.advance(100000);check(h.calls.length===0,'background polling paused');h.doc.hidden=false;h.doc.handlers.visibilitychange();await h.advance(1000);check(h.calls.length===1,'visible resumes');
console.log(JSON.stringify({checks,result:'ok',network:'synthetic',timers:'virtual'}));
