import fs from 'node:fs';import path from 'node:path';import {createRequire} from 'node:module';import {fileURLToPath} from 'node:url';
const require=createRequire('C:/Users/14bez/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/entry.js');const {chromium}=require('playwright');
const w=path.dirname(fileURLToPath(import.meta.url)),repo=path.resolve(w,'../..'),e=path.join(repo,'diagnostics/RD-25-WP2-WP3_phase0_evidence_20261012'),source=path.join(w,'source'),fixture=path.join(w,'fixture'),old=path.join(repo,'work/rd25-wp1-20261009/fixture');
const manifest=JSON.parse(fs.readFileSync(path.join(e,'visual-manifest.json'),'utf8'));const output=[],errors=[];
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});const page=await browser.newPage();
page.on('pageerror',err=>errors.push(String(err)));
await page.route('**/*',async route=>{
 const u=new URL(route.request().url());if(u.origin!=='http://rd25.local')return route.abort();
 let rel=decodeURIComponent(u.pathname),base=e;
 if(rel.startsWith('/image/')){base=path.join(fixture,'image');rel=rel.slice(6);}
 else if(rel==='/css/fixture-manrope.css'){base=path.join(old,'catalog/view/stylesheet');rel='/fixture-manrope.css';}
 else if(rel.startsWith('/css/')){base=path.join(source,'catalog/view/stylesheet');rel=rel.slice(4);}
 else if(rel.startsWith('/catalog/view/stylesheet/fonts/')){base=old;}
 else if(rel.startsWith('/catalog/')){base=source;}
 const f=path.resolve(base,'.'+rel);if(!f.startsWith(base+path.sep)||!fs.existsSync(f))return route.fulfill({status:404,body:'diagnostic asset missing'});
 const types={'.html':'text/html; charset=utf-8','.css':'text/css','.js':'application/javascript','.webp':'image/webp','.png':'image/png','.jpg':'image/jpeg','.jpeg':'image/jpeg','.ttf':'font/ttf','.woff2':'font/woff2'};
 return route.fulfill({status:200,contentType:types[path.extname(f).toLowerCase()]||'application/octet-stream',body:fs.readFileSync(f)});
});
if(process.argv.includes('--sheet')){
 try{
  await page.setViewportSize({width:1440,height:1000});await page.goto('http://rd25.local/contact-sheet.html');
  await page.locator('.pair').first().locator('img').evaluateAll(xs=>Promise.all(xs.map(x=>x.decode())));
  if(await page.locator('.pair').count()!==18)throw Error('Contact sheet pair count');
  await page.screenshot({path:path.join(e,'contact-sheet-preview.png')});
  await page.locator('#width').selectOption('390');await page.locator('#surface').selectOption('cart');
  if(await page.locator('.pair:visible').count()!==1)throw Error('Contact sheet filters');
  if(errors.length)throw Error(JSON.stringify(errors));
  console.log(JSON.stringify({contact_sheet_pairs:18,filters:true,js_errors:0}));
 }finally{await browser.close();}process.exit(0);
}
try{
 for(const width of [1440,390]){
  await page.setViewportSize({width,height:1000});
  for(const item of manifest.pages){
   await page.goto('http://rd25.local/'+item.page);await page.evaluate(()=>document.fonts.ready);await page.locator('img').evaluateAll(xs=>Promise.all(xs.map(x=>{x.loading='eager';return x.decode().catch(()=>{});}))); 
   const metrics=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth-innerWidth,images:[...document.images].map(x=>({src:x.src,loaded:x.complete&&x.naturalWidth>0,natural:[x.naturalWidth,x.naturalHeight],box:[x.getBoundingClientRect().width,x.getBoundingClientRect().height],fit:getComputedStyle(x).objectFit,position:getComputedStyle(x).objectPosition})),main:(()=>{let x=document.querySelector('.bs-product__main-img');return x?[x.getBoundingClientRect().width,x.getBoundingClientRect().height]:null})(),thumbs:[...document.querySelectorAll('.bs-product__thumbs>a')].map(x=>({box:[x.getBoundingClientRect().width,x.getBoundingClientRect().height],label:x.getAttribute('aria-label')})),directMiniImages:document.querySelectorAll('.bs-mini-cart__row>a>img').length}));
   const shot=item.page.replace('.html','')+'-'+width+'.png';await page.screenshot({path:path.join(e,shot),fullPage:true});
   output.push({...item,width,screenshot:shot,metrics});
   if(metrics.images.some(x=>!x.loaded))throw Error('Missing image in '+item.page);
   if(item.state==='after'){
    if(metrics.overflow>1)throw Error('Prototype horizontal overflow '+item.page+' @'+width);
    if(metrics.main&&Math.abs(metrics.main[0]/metrics.main[1]-.8)>.005)throw Error('Main ratio '+item.page);
    if(metrics.thumbs.some(x=>Math.abs(x.box[0]/x.box[1]-.8)>.005||!x.label))throw Error('Thumb ratio/label '+item.page);
    if(item.surface!=='product'&&metrics.images.some(x=>x.fit!=='contain'||x.position!=='50% 100%'))throw Error('Small-image framing '+item.page);
    if(item.surface==='mini-cart'&&await page.locator('#cart .bs-mini-cart__row>a>img').count()!==7)throw Error('Checkout DOM compatibility '+item.page);
   }
  }
 }
 for(const width of [1440,390]){
  await page.setViewportSize({width,height:1000});await page.goto('http://rd25.local/product-201-after.html');
  const a=page.locator('[data-gallery-thumb]').nth(5),target=await a.getAttribute('data-main'),popup=await a.getAttribute('href');
  await a.focus();await page.keyboard.press('Enter');
  if(await page.locator('[data-gallery-main]>img').getAttribute('src')!==target)throw Error('Gallery keyboard switch failed');
  if(await page.locator('[data-gallery-main]').getAttribute('href')!==popup)throw Error('Popup not following selected thumb');
  await page.locator('[data-gallery-main]').click();await page.locator('.mfp-img').waitFor({state:'visible'});
  const actual=await page.locator('.mfp-img').getAttribute('src');if(actual!==popup)throw Error('700 popup changed');
  const previous=await page.locator('[data-gallery-thumb]').nth(4).getAttribute('href');
  await page.keyboard.press('ArrowLeft');await page.waitForFunction(expected=>document.querySelector('.mfp-img')?.getAttribute('src')===expected,previous);
  await page.keyboard.press('ArrowRight');await page.waitForFunction(expected=>document.querySelector('.mfp-img')?.getAttribute('src')===expected,popup);
  await page.keyboard.press('Escape');
  await page.locator('.mfp-wrap').waitFor({state:'detached'});
  output.push({surface:'gallery-interaction',width,keyboard_switch:true,popup:actual,lightbox:true,previous_next:true,escape_close:true});
 }
}finally{await browser.close();fs.writeFileSync(path.join(e,'visual-metrics.json'),JSON.stringify({records:output,js_errors:errors},null,2));}
if(errors.length)throw Error(JSON.stringify(errors));console.log(JSON.stringify({screenshots:output.filter(x=>x.screenshot).length,gallery_interactions:output.filter(x=>x.surface==='gallery-interaction').length,js_errors:errors.length}));
