import {spawn} from 'node:child_process';
import {writeFileSync,mkdirSync} from 'node:fs';
import {dirname,join} from 'node:path';
const base='http://127.0.0.1:8789/index.php?route=product/category&language=uk-ua&path=59';
const port=9537;
const chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new',`--remote-debugging-port=${port}`,`--user-data-dir=${join(import.meta.dirname,'chrome-fixture-profile')}`,'--no-first-run','--disable-gpu','--force-device-scale-factor=1','about:blank'],{stdio:'ignore',windowsHide:true});
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
let tabs;
for(var i=0;i<50;i++){try{tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();if(tabs.length)break;}catch{}await sleep(200);}
if(!tabs)throw Error('Headless Chrome did not start');
const ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
await new Promise(r=>ws.addEventListener('open',r,{once:true}));
let id=0;const pending=new Map(),errors=[],results=[];
ws.addEventListener('message',ev=>{const m=JSON.parse(ev.data);if(m.id&&pending.has(m.id)){pending.get(m.id)(m);pending.delete(m.id);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);});
const send=(method,params={})=>new Promise(r=>{const n=++id;pending.set(n,r);ws.send(JSON.stringify({id:n,method,params}));});
const js=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.result?.exceptionDetails)throw Error(r.result.exceptionDetails.exception?.description||r.result.exceptionDetails.text);return r.result?.result?.value;};
const ok=(value,message)=>{if(!value)throw Error(message);};
async function ready(){for(var i=0;i<70;i++){if(await js("document.readyState==='complete' && !!window.bsCategoryNavigate"))return;await sleep(100);}throw Error('Fixture not ready');}
async function load(width,url=base){await send('Emulation.setDeviceMetricsOverride',{width,height:844,deviceScaleFactor:1,mobile:false});await send('Page.navigate',{url});await ready();await sleep(180);}
async function done(){for(var i=0;i<60;i++){if(await js("document.getElementById('bs-category-results')?.getAttribute('aria-busy')==='false'"))return;await sleep(100);}throw Error('Request did not settle');}
async function change(value,on=true){await js(`(()=>{var i=document.querySelector('input[name="filter[]"][value="${value}"]');i.checked=${on};i.focus();i.dispatchEvent(new Event('change',{bubbles:true}));})()`);await sleep(330);await done();}
async function test(name,fn){if(process.env.BS_CASE&&process.env.BS_CASE!==name)return;errors.length=0;try{const detail=await fn();ok(!errors.length,'New JS exceptions: '+errors.join('\n'));results.push({name,pass:true,detail});console.log('PASS',name);}catch(e){results.push({name,pass:false,error:String(e),exceptions:[...errors]});console.log('FAIL',name,String(e));throw e;}}
await send('Page.enable');await send('Runtime.enable');await send('Network.enable');await send('Emulation.setFocusEmulationEnabled',{enabled:true});
mkdirSync(join(import.meta.dirname,'screenshots'),{recursive:true});
try{
for(const w of [390,575,576,768,991,992,1000,1440]){
 await test('responsive-'+w,async()=>{
  await load(w,base+'&qty=3');
  const v=await js(`(()=>{let b=document.querySelector('[data-bs-cart-qty]'),badge=b.querySelector('.bs-cart-badge'),label=b.querySelector('.bs-btn-label'),r=b.getBoundingClientRect();return {aria:b.getAttribute('aria-label'),badge:getComputedStyle(badge).display,label:getComputedStyle(label).display,height:r.height,width:r.width,overflow:document.documentElement.scrollWidth>innerWidth,green:getComputedStyle(b).backgroundColor};})()`);
  ok(v.height===44,'Cart must be 44px tall');ok((v.badge!=='none')===(w<992),'Badge breakpoint');
  ok((v.label!=='none')===(w>=576),'Amount breakpoint');ok(!v.overflow,'Horizontal overflow');
  ok(w>=992?v.aria==='Мій кошик - 999999.00₴':v.aria===(w>=576?'Кошик: 3 товари, 999999.00₴':'Кошик: 3 товари'),'Cart accessible label');
  if([390,768,1000,1440].includes(w)){
    await js("document.getElementById('bs-ff-toggle').click()");
    const shot=await send('Page.captureScreenshot',{format:'png'});writeFileSync(join(import.meta.dirname,'screenshots',`category-${w}.png`),Buffer.from(shot.result.data,'base64'));
    const grid=await js("getComputedStyle(document.querySelector('.bs-ff-groups')).gridTemplateColumns.split(' ').length");
    ok(grid===(w<576?1:w<992?2:4),'Filter group columns');
  }
  return v;
 });
}
for(const n of [0,1,12,21])await test('quantity-'+n,async()=>{
 await load(390,base+'&qty='+n);
 const v=await js("(()=>{let b=document.querySelector('[data-bs-cart-qty]'),d=b.querySelector('.bs-cart-badge');return{aria:b.getAttribute('aria-label'),hidden:getComputedStyle(d).display==='none',text:d.textContent};})()");
 ok(v.hidden===(n===0),'Empty badge visibility');ok(n===0||v.text===(n>=10?'9+':String(n)),'Badge text');
 ok(v.aria===(n===0?'Кошик порожній':`Кошик: ${n} ${n===1||n===21?'товар':'товарів'}`),'Plural');return v;
});
await test('filters-sort-history-metadata-and-focus',async()=>{
 await load(390);await js("window.__identity='survives';document.getElementById('bs-ff-toggle').click()");
 await change('12');
 ok(await js("window.__identity==='survives' && !document.getElementById('bs-ff-panel').hidden && document.activeElement.value==='12'"),'Navigation/panel/focus regression');
 ok(await js("document.getElementById('bs-category-results').dataset.total==='24' && document.querySelector('meta[name=robots]').content==='noindex,follow'"),'Filtered total/robots');
 await js("var s=document.querySelector('.bs-ff-sort select');s.selectedIndex=2;s.dispatchEvent(new Event('change',{bubbles:true}))");await sleep(100);await done();
 ok(await js("new URL(location.href).searchParams.get('filter')==='12' && new URL(location.href).searchParams.get('order')==='DESC'"),'Sort lost filter');
 await js('history.back()');await sleep(180);await done();
 ok(await js("document.querySelector('.bs-ff-sort select').selectedIndex===0 && document.querySelector('input#input-filter-12').checked"),'Back restoration');
 await js('history.back()');await sleep(180);await done();
 ok(await js("!document.querySelector('input#input-filter-12').checked && document.querySelector('meta[name=robots]').content==='index,follow'"),'Clean history metadata');
 await js('history.forward()');await sleep(180);await done();ok(await js("document.querySelector('input#input-filter-12').checked"),'Forward');
 await change('13');await change('24');
 await js("document.querySelector('[data-remove-filter=\"12\"]').click()");await sleep(100);await done();
 ok(await js("document.activeElement.dataset.removeFilter==='13' && document.querySelector('[data-bs-category-status]').textContent.includes('прибрано')"),'Chip focus/announcement');
 await js("document.querySelector('[data-bs-active-filters] [data-bs-r9-reset]').click()");await sleep(100);await done();
 ok(await js("document.activeElement.id==='bs-ff-toggle' && !new URL(location.href).searchParams.has('filter') && document.querySelector('[data-bs-category-status]').textContent.startsWith('Фільтри скинуто.')"),'Reset focus/URL/announcement');
 return await js("({count:document.querySelector('.bs-count').textContent,robots:document.querySelector('meta[name=robots]').content,identity:window.__identity})");
});
await test('load-more-filter-rebind-and-empty-state',async()=>{
 await load(768);await js("document.getElementById('bs-load-more-btn').click()");await sleep(150);await done();
 ok(await js("document.querySelectorAll('#product-list > .col').length===16"),'Append');
 ok(await js("!document.querySelector('link[rel=prev]') && !new URL(location.href).searchParams.has('page')"),'Load-more changed current-page metadata/address');
 await change('12');ok(await js("document.querySelectorAll('#product-list > .col').length===8"),'Grid did not reset');
 await js("document.getElementById('bs-load-more-btn').click()");await sleep(150);await done();
 ok(await js("document.querySelectorAll('#product-list > .col').length===16 && document.querySelector('.bs-lm-total').textContent==='24'"),'Old grid retained by load more');
 await change('12',false);await change('15');await change('24');
 ok(await js("document.querySelector('.bs-r9-empty') && document.getElementById('bs-category-results').dataset.total==='0' && !document.querySelector('[data-bs-r9-zero]').hidden"),'Zero result');
 await js("document.querySelector('.bs-r9-empty [data-bs-r9-reset]').click()");await sleep(150);await done();
 ok(await js("document.querySelectorAll('#product-list > .col').length===8 && document.activeElement.id==='bs-ff-toggle'"),'Empty reset');
 return 'append/reset/zero-result passed';
});
await test('latest-request-wins-with-an-uncancellable-old-response',async()=>{
 await load(768);await js("window.__fetch=fetch;window.fetch=async function(url,options){let r=await window.__fetch(url,{...options,signal:undefined});let f=new URL(url).searchParams.get('filter');await new Promise(ok=>setTimeout(ok,f==='12'?900:20));return r;}");
 await js("var i=document.querySelector('input#input-filter-12');i.checked=true;i.dispatchEvent(new Event('change',{bubbles:true}))");await sleep(320);
 await js("var i=document.querySelector('input#input-filter-13');i.checked=true;i.dispatchEvent(new Event('change',{bubbles:true}))");await sleep(320);
 await js("var i=document.querySelector('input#input-filter-24');i.checked=true;i.dispatchEvent(new Event('change',{bubbles:true}))");await sleep(1200);await done();
 ok(await js("document.getElementById('bs-category-results').dataset.total==='6' && new URL(location.href).searchParams.get('filter')==='12,13,24'"),'Stale response applied');
 await js('window.fetch=window.__fetch');return 'Latest result remains 6';
});
await test('fallback-on-request-failure',async()=>{
 await load(390);await js("window.__identity='old';window.fetch=function(){return Promise.reject(new Error('synthetic offline'));}");
 await js("var i=document.querySelector('input#input-filter-12');i.checked=true;i.dispatchEvent(new Event('change',{bubbles:true}))");await sleep(700);await ready();
 ok(await js("window.__identity===undefined && document.querySelector('input#input-filter-12').checked && document.querySelector('meta[name=robots]').content==='noindex,follow'"),'Fallback did not navigate to server-rendered state');return 'Normal navigation confirmed';
});
await test('no-filter-and-no-subcategories',async()=>{
 await load(768,base+'&nofilter=1&nosubs=1');ok(await js("!document.getElementById('bs-ff-toggle')"),'Unexpected filter');
 await js("var s=document.querySelector('.bs-ff-sort select');s.selectedIndex=1;s.dispatchEvent(new Event('change',{bubbles:true}))");await sleep(120);await done();
 ok(await js("new URL(location.href).searchParams.get('sort')==='p.price'"),'Sort without filter failed');return 'No module safe';
});
await test('cart-fragment-refresh-and-drawer-preservation',async()=>{
 await load(768,base+'&qty=3');await js("document.querySelector('[data-bs-mini-cart-open]').click();submitMiniCartQuantity(jQuery('.mini-cart-quantity-input').val(12))");await sleep(700);
 ok(await js("document.querySelector('[data-bs-cart-qty]').dataset.bsCartQty==='12' && document.querySelector('[data-bs-mini-cart]').classList.contains('is-open') && document.querySelector('[data-bs-cart-qty]').getAttribute('aria-label')==='Кошик: 12 товарів, 999999.00₴'"),'Cart fragment/count/drawer');
 await js("document.querySelector('.mini-cart-remove-btn').click()");await sleep(700);
 ok(await js("getComputedStyle(document.querySelector('.bs-cart-badge')).display==='none' && document.querySelector('[data-bs-cart-qty]').getAttribute('aria-label')==='Кошик порожній'"),'Remove badge');return 'Quantity and remove refresh passed';
});
await test('desktop-trigger-exact-source-style-parity',async()=>{
 const read="(()=>{let b=document.querySelector('.mini-cart-trigger'),l=b.querySelector('.bs-btn-label'),i=b.querySelector('.bs-cart-icon'),s=getComputedStyle(b),ls=getComputedStyle(l),r=b.getBoundingClientRect();return {w:r.width,h:r.height,bg:s.backgroundColor,border:s.borderColor,radius:s.borderRadius,padding:s.padding,gap:s.gap,font:ls.fontSize,weight:ls.fontWeight,icon:getComputedStyle(i).width,text:l.textContent};})()";
 await send('Emulation.setDeviceMetricsOverride',{width:1440,height:844,deviceScaleFactor:1,mobile:false});
 await send('Page.navigate',{url:base+'&baseline=1&qty=3'});await sleep(600);
 const before=await js(read);
 await load(1440,base+'&qty=3');const after=await js(read);
 ok(JSON.stringify(before)===JSON.stringify(after),'Desktop style regression: '+JSON.stringify({before,after}));
 return after;
});
await test('long-content-keyboard-sticky-and-single-fetch',async()=>{
 await load(390);await js("document.getElementById('bs-ff-toggle').click();window.__calls=[];window.__savedFetch=fetch;window.fetch=function(url,options){window.__calls.push(url);return window.__savedFetch(url,options)}");
 const geometry=await js("(()=>{let g=document.querySelector('[data-bs-ff-group]'),r=g.getBoundingClientRect(),s=g.querySelector('.bs-ff-ck:last-child span').getBoundingClientRect();return {r:r.right,s:s.right,left:s.left};})()");
 ok(geometry.s<=geometry.r+1,'Long filter label escapes column');
 await js("document.querySelector('input#input-filter-12').focus()");
 await send('Input.dispatchKeyEvent',{type:'keyDown',key:' ',code:'Space',windowsVirtualKeyCode:32});
 await send('Input.dispatchKeyEvent',{type:'keyUp',key:' ',code:'Space',windowsVirtualKeyCode:32});
 await sleep(350);await done();
 ok(await js("window.__calls.length===1 && document.querySelector('input#input-filter-12').checked"),'Keyboard filter must make one fetch');
 await js("document.querySelector('[data-bs-r9-show]').click()");
 ok(await js("document.getElementById('bs-ff-panel').hidden && document.activeElement.id==='product-list'"),'Show CTA close/focus');
 await js("window.fetch=window.__savedFetch;document.getElementById('bs-ff-toggle').click();let c=document.querySelector('.bs-ff-checks');for(var i=0;i<30;i++)c.appendChild(c.firstElementChild.cloneNode(true))");
 const sticky=await js("(()=>{let b=document.querySelector('[data-bs-r9-show]'),r=b.getBoundingClientRect(),a=[];for(let p=b.parentElement;p;p=p.parentElement){let s=getComputedStyle(p);a.push({node:p.id||p.className||p.tagName,x:s.overflowX,y:s.overflowY,position:s.position})}return {top:r.top,bottom:r.bottom,height:r.height,scroll:scrollY,ancestors:a};})()");
 ok(sticky.bottom<=844&&sticky.top>=0,'Sticky CTA lost below tall panel: '+JSON.stringify(sticky));
 return {geometry,sticky};
});
await test('category-card-add-after-grid-swap',async()=>{
 await load(768);await change('12');
 const old=await js("Number(document.querySelector('[data-bs-cart-qty]').dataset.bsCartQty)");
 await js("document.querySelector('#product-list form button').click()");await sleep(800);
 const now=await js("Number(document.querySelector('[data-bs-cart-qty]').dataset.bsCartQty)");
 ok(now===old+1,'AJAX card add failed after grid replacement');
 ok(await js("!!document.querySelector('.bs-toast') && document.querySelector('.bs-cart-badge').textContent===String(Number(document.querySelector('[data-bs-cart-qty]').dataset.bsCartQty))"),'Toast/badge after add');
 return {old,now};
});
for(const failure of ['500','missing-wrapper','wrong-canonical'])await test('fallback-'+failure,async()=>{
 await load(390);await js(`window.__identity='old';window.__savedFetch=fetch;window.fetch=async function(url,options){let r=await window.__savedFetch(url,options);let h=await r.text();${failure==='missing-wrapper'?`h=h.replace('id="bs-category-results"','id="missing-result"');`:failure==='wrong-canonical'?`h=h.replace('rel="canonical"','rel="changed"');`:''}return {status:${failure==='500'?500:200},url:r.url,text:async()=>h};}`);
 await js("var i=document.querySelector('input#input-filter-12');i.checked=true;i.dispatchEvent(new Event('change',{bubbles:true}))");await sleep(600);await ready();
 ok(await js("window.__identity===undefined && document.querySelector('input#input-filter-12').checked && document.querySelector('meta[name=robots]').content==='noindex,follow'"),'Failure fallback');return 'Normal server navigation';
});
await test('interaction-styles-and-loading-state',async()=>{
 await load(768,base+'&filter=12');
 await send('DOM.enable');await send('CSS.enable');
 const doc=await send('DOM.getDocument');const chip=await send('DOM.querySelector',{nodeId:doc.result.root.nodeId,selector:'.bs-r9-chip'});
 await send('CSS.forcePseudoState',{nodeId:chip.result.nodeId,forcedPseudoClasses:['hover','active','focus-visible']});
 const state=await js("(()=>{let s=getComputedStyle(document.querySelector('.bs-r9-chip'));return {outline:s.outlineWidth,style:s.outlineStyle,color:s.outlineColor,cursor:s.cursor};})()");
 ok(state.outline==='2px'&&state.style==='solid'&&state.cursor==='pointer','Chip interaction styling');
 await js("document.getElementById('bs-ff-toggle').click();window.__savedFetch=fetch;window.fetch=async function(url,options){let r=await window.__savedFetch(url,options);await new Promise(done=>setTimeout(done,500));return r;};window.__panelTop=document.getElementById('bs-ff-panel').getBoundingClientRect().top;var i=document.querySelector('input#input-filter-13');i.checked=true;i.dispatchEvent(new Event('change',{bubbles:true}))");
 const loading=await js("(()=>{let r=document.getElementById('bs-category-results'),g=document.getElementById('product-list'),b=document.querySelector('[data-bs-r9-show]');return{busy:r.getAttribute('aria-busy'),grid:g.getAttribute('aria-busy'),min:r.style.minHeight,opacity:getComputedStyle(g).opacity,pointer:getComputedStyle(g).pointerEvents,disabled:b.disabled,aria:b.getAttribute('aria-disabled'),top:document.getElementById('bs-ff-panel').getBoundingClientRect().top===window.__panelTop};})()");
 ok(loading.busy==='true'&&loading.grid==='true'&&loading.min&&loading.opacity==='0.45'&&loading.pointer==='none'&&loading.disabled&&loading.aria==='true'&&loading.top,'Loading or stable panel state');
 await sleep(950);await done();ok(await js("document.getElementById('bs-category-results').style.minHeight===''"),'Height lock was not released');return{state,loading};
});
} finally {
 writeFileSync(join(import.meta.dirname,process.env.BS_CASE?'browser-results-'+process.env.BS_CASE+'.json':'browser-results.json'),JSON.stringify(results,null,2));ws.close();chrome.kill();
}
console.log('TOTAL',results.length,'PASS',results.filter(r=>r.pass).length);
