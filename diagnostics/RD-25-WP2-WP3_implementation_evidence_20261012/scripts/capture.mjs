import fs from 'node:fs';import path from 'node:path';import {createRequire} from 'node:module';import {fileURLToPath} from 'node:url';
const require=createRequire('C:/Users/14bez/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/entry.js');const {chromium}=require('playwright');
const w=path.dirname(fileURLToPath(import.meta.url)),repo=path.resolve(w,'../..'),v=path.join(w,'visual'),source=path.join(repo,'work/rd25-wp2-wp3-phase0-20261012/source'),fixture=path.join(repo,'work/rd25-wp2-wp3-phase0-20261012/fixture'),fonts=path.join(repo,'work/rd25-wp1-20261009/fixture');
const manifest=JSON.parse(fs.readFileSync(path.join(v,'manifest.json'),'utf8'));const rendered=JSON.parse(fs.readFileSync(path.join(w,'rendered.json'),'utf8'));
const records=[],errors=[],requests=[];let quantities={},removed=new Set();
function assert(ok,message){if(!ok)throw Error(message);}
function fragment(mini){let html=fs.readFileSync(path.join(v,mini?'mini-fragment.html':'cart-fragment.html'),'utf8');return html.replace(/<article class="bs-(?:mini-cart__row|cart-row)"[^>]*>.*?<\/article>/gs,article=>{const id=mini?article.match(/data-key="(\d+)"/)?.[1]:article.match(/name="key" value="(\d+)"/)?.[1];if(removed.has(id))return '';return article.replace(/name="quantity" value="\d+"/g,'name="quantity" value="'+(quantities[id]||1)+'"');});}
const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});const page=await browser.newPage();
page.on('pageerror',e=>errors.push(String(e)));
await page.route('**/*',async route=>{
 const request=route.request(),u=new URL(request.url());if(u.origin!=='http://rd25.local')return route.abort();
 if(u.pathname==='/index.php'){
  const action=u.searchParams.get('route');requests.push({action,method:request.method(),body:request.postData()});
  if(action==='checkout/cart.edit'){const p=new URLSearchParams(request.postData());quantities[p.get('key')]=Number(p.get('quantity'));return route.fulfill({contentType:'application/json',body:JSON.stringify({success:'Fixture quantity updated'})});}
  if(action==='common/cart.remove'||action==='checkout/cart.remove'){const p=new URLSearchParams(request.postData());removed.add(p.get('key')||u.searchParams.get('key'));return route.fulfill({contentType:'application/json',body:JSON.stringify({success:'Fixture item removed'})});}
  if(action==='common/cart.info'||action==='checkout/cart.list')return route.fulfill({contentType:'text/html; charset=utf-8',body:fragment(action==='common/cart.info')});
  return route.fulfill({status:400,body:'Unexpected fixture action'});
 }
 let rel=decodeURIComponent(u.pathname),base=v;
 if(rel.startsWith('/image/')){base=path.join(fixture,'image');rel=rel.slice(6);}
 else if(rel==='/css/fixture-manrope.css'){base=path.join(fonts,'catalog/view/stylesheet');rel='/fixture-manrope.css';}
 else if(rel.startsWith('/css/')){base=path.join(source,'catalog/view/stylesheet');rel=rel.slice(4);if(rel==='/boostershop-ds.css')base=path.join(w,'after-wp3/catalog/view/stylesheet');}
 else if(rel.startsWith('/catalog/view/stylesheet/fonts/'))base=fonts;
 else if(rel.startsWith('/catalog/'))base=source;
 const f=path.resolve(base,'.'+rel);if(!f.startsWith(base+path.sep)||!fs.existsSync(f))return route.fulfill({status:404,body:'Fixture missing'});
 const types={'.html':'text/html; charset=utf-8','.css':'text/css','.js':'application/javascript','.webp':'image/webp','.png':'image/png','.jpg':'image/jpeg','.jpeg':'image/jpeg','.ttf':'font/ttf','.woff2':'font/woff2'};
 return route.fulfill({contentType:types[path.extname(f).toLowerCase()]||'application/octet-stream',body:fs.readFileSync(f)});
});
async function load(file,width){quantities={};removed=new Set();await page.setViewportSize({width,height:1000});await page.goto('http://rd25.local/'+file);await page.evaluate(()=>document.fonts.ready);await page.locator('img').evaluateAll(xs=>Promise.all(xs.map(x=>{x.loading='eager';return x.decode();})));}
try{
 for(const width of [1440,1024,390,360]){
  for(const item of manifest){
   await load(item.page,width);
   const metrics=await page.evaluate(()=>{let main=document.querySelector('.bs-product__main-img'),rail=document.querySelector('.bs-product__thumbs');const box=x=>x?[x.getBoundingClientRect().x,x.getBoundingClientRect().y,x.getBoundingClientRect().width,x.getBoundingClientRect().height]:null;return {overflow:document.documentElement.scrollWidth-innerWidth,main:box(main),rail:box(rail),railScroll:rail?[rail.clientHeight,rail.scrollHeight,rail.clientWidth,rail.scrollWidth]:null,background:main?getComputedStyle(main).backgroundColor:null,thumbs:[...document.querySelectorAll('[data-bs-gallery-thumb]')].map(box),images:[...document.images].filter(x=>x.getBoundingClientRect().width>0).map(x=>({loaded:x.complete&&x.naturalWidth>0,fit:getComputedStyle(x).objectFit,position:getComputedStyle(x).objectPosition,box:box(x)})),small:[...document.querySelectorAll('.bs-mini-cart__row>a,.bs-cart-row__thumb')].filter(x=>x.getBoundingClientRect().width>0).map(box)};});
   assert(metrics.overflow<=1,'Page overflow '+item.page+' @'+width);
   assert(metrics.images.every(x=>x.loaded&&x.fit==='contain'&&x.position==='50% 100%'),'Loaded contain/bottom '+item.page);
   if(item.surface==='product'){
    assert(Math.abs(metrics.main[2]/metrics.main[3]-.8)<.005,'4:5 main '+item.page);
    assert(metrics.background==='rgba(0, 0, 0, 0)','Grey backing '+item.page);
    const count=rendered.products[item.product_id].wp3.payload.bs_gallery.length;
    assert(metrics.thumbs.length===(count>1?count:0),'Primary rail/single case '+item.page);
    assert(metrics.thumbs.every(b=>Math.abs(b[2]/b[3]-.8)<.005&&b[2]>=44&&b[3]>=44),'Rail thumb dimensions');
    if(metrics.rail){
     if(width>=992){assert(metrics.rail[0]<metrics.main[0]&&Math.abs(metrics.rail[1]-metrics.main[1])<1,'Rail left');assert(metrics.rail[3]<=metrics.main[3]+1,'Rail bounded by main image');}
     else assert(metrics.rail[1]>=metrics.main[1]+metrics.main[3]-1,'Mobile rail below');
    }
   }else assert(metrics.small.every(b=>b[2]===52&&b[3]===65),'Small slot 52x65 '+item.page);
   const screenshot=item.page.replace('.html','')+'-'+width+'.png';await page.screenshot({path:path.join(v,screenshot),fullPage:true});records.push({...item,width,screenshot,metrics});
  }
  await load('product-201.html',width);
  const thumb=page.locator('[data-bs-gallery-thumb]').nth(5),popup=await thumb.getAttribute('href'),main=await thumb.getAttribute('data-bs-gallery-src');
  await page.keyboard.press('Tab');await thumb.focus();
  assert(await thumb.evaluate(x=>getComputedStyle(x).outlineStyle)==='solid','Thumb keyboard focus');
  const normalColor=await thumb.evaluate(x=>getComputedStyle(x).borderTopColor);await thumb.hover();
  assert(await thumb.evaluate(x=>getComputedStyle(x).borderTopColor)!==normalColor,'Inactive thumbnail hover border');await page.keyboard.press('Enter');
  assert(await page.locator('[data-bs-gallery-main]').getAttribute('href')===popup,'Literal popup preserved');
  assert(await page.locator('[data-bs-gallery-main] img').getAttribute('src')===main,'Keyboard switches main');
  assert(await thumb.getAttribute('aria-current')==='true'&&await page.locator('[aria-current="true"]').count()===1,'One selected thumb');
  assert(await thumb.evaluate(x=>getComputedStyle(x).borderTopWidth)==='2px','Active border');
  await page.locator('[data-bs-gallery-main]').click();await page.locator('.mfp-img').waitFor({state:'visible'});
  assert(await page.locator('.mfp-img').getAttribute('src')===popup,'Selected 700 URL');
  assert(await page.locator('.mfp-counter').textContent()==='6 of 6','Lightbox six entries no duplicate');
  const previous=await page.locator('[data-bs-gallery-thumb]').nth(4).getAttribute('href');await page.keyboard.press('ArrowLeft');await page.waitForFunction(x=>document.querySelector('.mfp-img')?.getAttribute('src')===x,previous);
  await page.keyboard.press('ArrowRight');await page.waitForFunction(x=>document.querySelector('.mfp-img')?.getAttribute('src')===x,popup);await page.keyboard.press('Escape');await page.locator('.mfp-wrap').waitFor({state:'detached'});
  await load('product-96.html',width);const single=await page.locator('[data-bs-gallery-main]').getAttribute('href');await page.keyboard.press('Tab');await page.locator('[data-bs-gallery-main]').focus();assert(await page.locator('[data-bs-gallery-main]').evaluate(x=>getComputedStyle(x).outlineStyle)==='solid','Main keyboard focus');await page.keyboard.press('Enter');await page.locator('.mfp-img').waitFor({state:'visible'});assert(await page.locator('.mfp-img').getAttribute('src')===single,'Single image popup');assert(await page.locator('.mfp-counter:visible').count()===0,'No visible single-image lightbox counter');await page.keyboard.press('Escape');
  records.push({surface:'gallery-interaction',width,keyboard_focus:true,hover_active:true,six_items:true,single_item:true,popup_byte_identical:true,previous_next_escape:true});
  await load('reload.html',width);let start=requests.length;
  await page.locator('#cart .bs-mini-cart-qty[data-delta="1"]').first().click();
  await page.waitForFunction(()=>document.querySelector('#shopping-cart input[name="quantity"]')?.value==='2'&&document.querySelector('#cart .mini-cart-quantity-input')?.value==='2');
  assert(await page.locator('#cart .bs-mini-cart__row>a>img').count()===8,'Mini DOM shape after reload');
  assert((await page.locator('#cart .bs-mini-cart__row>a>img').first().getAttribute('src')).includes('/rd25-fit/'),'Fit after mini reload');
  await page.locator('#shopping-cart .bs-cart-qty-btn[data-delta="1"]').first().click();
  await page.waitForFunction(()=>document.querySelector('#shopping-cart input[name="quantity"]')?.value==='3'&&document.querySelector('#cart .mini-cart-quantity-input')?.value==='3');
  assert((await page.locator('#shopping-cart .bs-cart-row__thumb img').first().getAttribute('src')).includes('/rd25-fit/'),'Fit after cart reload');
  const actual=requests.slice(start);assert(actual.filter(x=>x.action==='checkout/cart.edit').length===2,'Exactly one edit per quantity change');assert(actual.filter(x=>x.action==='common/cart.info').length>=2&&actual.filter(x=>x.action==='checkout/cart.list').length>=2,'Both actual fragment reload paths');
  await page.locator('#cart .mini-cart-remove-btn').first().click();await page.waitForFunction(()=>document.querySelectorAll('#cart .bs-mini-cart__row').length===7&&document.querySelectorAll('#shopping-cart .bs-cart-row').length===7);
  await page.locator('#shopping-cart .bs-cart-remove').first().click();await page.waitForFunction(()=>document.querySelectorAll('#cart .bs-mini-cart__row').length===6&&document.querySelectorAll('#shopping-cart .bs-cart-row').length===6);
  records.push({surface:'cart-fragment-interaction',width,mini_qty_reload:true,cart_qty_reload:true,mini_remove:true,cart_remove:true,unchanged_post_fields:true,requests:requests.slice(start)});
 }
 assert(errors.length===0,'JS errors '+JSON.stringify(errors));
}catch(error){console.log(JSON.stringify({diagnostic_requests:requests,quantities,dom:await page.evaluate(()=>({cart:[...document.querySelectorAll('#shopping-cart input[name="quantity"]')].map(x=>x.value),mini:[...document.querySelectorAll('#cart .mini-cart-quantity-input')].map(x=>x.value)}))}));throw error;}
finally{await browser.close();fs.writeFileSync(path.join(w,'ui-checks.json'),JSON.stringify({records,js_errors:errors,scope:'Actual patched Twig gallery JS and unmodified cart interaction JS, local mocked cart HTTP only; no production request'},null,2));}
console.log(JSON.stringify({screenshots:records.filter(x=>x.screenshot).length,interaction_groups:records.filter(x=>!x.screenshot).length,js_errors:errors.length}));
