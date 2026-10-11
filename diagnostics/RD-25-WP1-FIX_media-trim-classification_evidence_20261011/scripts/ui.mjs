import {createRequire} from 'node:module'; import fs from 'node:fs'; import path from 'node:path'; import {fileURLToPath} from 'node:url';
const require=createRequire('C:/Users/14bez/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/entry.js'); const {chromium}=require('playwright');
const w=path.dirname(fileURLToPath(import.meta.url)), repo=path.resolve(w,'../..'), e=path.join(repo,'diagnostics/RD-25-WP1-FIX_media-trim-classification_evidence_20261011');
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true}); const checks=[]; const check=(name,pass,detail)=>{checks.push({name,pass:!!pass,detail});};
const page=await browser.newPage(); const errors=[]; page.on('pageerror',x=>errors.push(String(x)));
await page.route('**/*',async route=>{
 const u=new URL(route.request().url()); if(u.origin!=='http://127.0.0.1:18726')return route.abort();
 let rel=decodeURIComponent(u.pathname), base=e;
 if(rel.startsWith('/image/')){base=path.join(repo,'work/rd25-wp1-fix-phase0-20261011/images');rel=rel.slice(6);}
 else if(rel.startsWith('/css/')){base=path.join(repo,'work/rd25-wp1-20261009/fixture/catalog/view/stylesheet');rel=rel.slice(4);}
 else if(rel.startsWith('/catalog/view/stylesheet/')){base=path.join(repo,'work/rd25-wp1-20261009/fixture');}
 const f=path.resolve(base,'.'+rel); if(!f.startsWith(base+path.sep)||!fs.existsSync(f))return route.fulfill({status:404,body:'missing'});
 const types={'.html':'text/html; charset=utf-8','.css':'text/css','.ttf':'font/ttf','.webp':'image/webp','.png':'image/png','.jpg':'image/jpeg'};
 return route.fulfill({status:200,contentType:types[path.extname(f)]||'application/octet-stream',body:fs.readFileSync(f)});
});
try{
 for(const width of [1440,1024,390,360]){
  await page.setViewportSize({width,height:1000});
  for(const grid of ['category','search','special','manufacturer_info','featured-horizontal']){
   await page.goto('http://127.0.0.1:18726/'+grid+'.html'); await page.evaluate(()=>document.fonts.ready);
   await page.locator('img').evaluateAll(xs=>Promise.all(xs.map(x=>{x.loading='eager';return x.decode().catch(()=>{});}))); const tag=grid+'@'+width;
   const data=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth-innerWidth,images:[...document.images].map(i=>{let s=getComputedStyle(i),p=i.parentElement.getBoundingClientRect();return {w:i.width,h:i.height,nw:i.naturalWidth,nh:i.naturalHeight,aw:Number(i.getAttribute('width')),ah:Number(i.getAttribute('height')),kind:i.parentElement.classList.contains('bs-pcard__media--cut')?'cut':'photo',fit:s.objectFit,pos:s.objectPosition,ratio:p.width/p.height};})}));
   check(tag+' actual generated images and exact intrinsic attributes',data.images.length===33 && data.images.every(i=>i.nw===i.aw&&i.nh===i.ah),data.images.length);
   check(tag+' approved slot ratio and contain/cover framing',data.images.every(i=>Math.abs(i.ratio-(width<768?.8:1))<.003&&i.fit===(i.kind==='cut'?'contain':'cover')&&(i.kind!=='cut'||i.pos==='50% 100%')));
   check(tag+' no horizontal overflow',data.overflow<=1,data.overflow);
   if(grid==='category')await page.screenshot({path:path.join(e,'category-'+width+'.png'),fullPage:true});
  }
 }
 const btn=page.locator('.bs-pcard__buy-btn').first(); await btn.hover(); check('hover state preserved',await btn.evaluate(x=>x.matches(':hover'))); await btn.focus();check('keyboard focus preserved',await btn.evaluate(x=>document.activeElement===x));await btn.hover();await page.mouse.down();check('active state preserved',await btn.evaluate(x=>x.matches(':active')));await page.mouse.up();check('no JavaScript errors',errors.length===0,errors);
}finally{await browser.close();fs.writeFileSync(path.join(e,'ui-checks.json'),JSON.stringify({checks,passed:checks.filter(x=>x.pass).length,total:checks.length},null,2));}
console.log(JSON.stringify({passed:checks.filter(x=>x.pass).length,total:checks.length,failed:checks.filter(x=>!x.pass)}));if(checks.some(x=>!x.pass))process.exitCode=1;
