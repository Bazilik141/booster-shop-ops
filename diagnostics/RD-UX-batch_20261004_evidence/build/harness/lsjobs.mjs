// node lsjobs.mjs <outDir> <port> <prefix>
const [O, port, pre, mode] = process.argv.slice(2);
const type = q => `(()=>{const i=document.getElementById('ps-live-search-input');i.focus();i.value=${JSON.stringify(q)};i.dispatchEvent(new Event('input',{bubbles:true}));return true})()`;
const EV = `JSON.stringify((()=>{const l=document.getElementById('ps-live-search');const r=l.getBoundingClientRect();const items=[...l.querySelectorAll('.ps-live-search-item')];
 const prods=items.filter(a=>a.querySelector('strong.name'));const vis=prods.filter(a=>{const b=a.getBoundingClientRect();return b.bottom<=innerHeight&&b.top>=0}).length;
 const nm=prods[0]&&prods[0].querySelector('.name');const lh=nm?parseFloat(getComputedStyle(nm).lineHeight):0;
 return {show:l.classList.contains('show'),top:Math.round(r.top),h:Math.round(r.height),prods:prods.length,prodsVisible:vis,desc:prods[0]?getComputedStyle(prods[0].querySelector('.description')||document.body).display:null,
 nameLines:nm?Math.round(nm.getBoundingClientRect().height/lh):0,rowH:prods[0]?Math.round(prods[0].getBoundingClientRect().height):0,
 state:(l.querySelector('.bs-ls-state')||{}).innerText||null,role:(l.querySelector('.bs-ls-state')||{getAttribute:()=>null}).getAttribute('role'),
 more:(l.querySelector('a.ps-live-search-more')||{}).href||null,moreSpan:!!l.querySelector('span.ps-live-search-more'),noRes:l.querySelectorAll('.ps-live-search-item-text').length,
 faVisible:[...l.querySelectorAll('i')].filter(i=>getComputedStyle(i).display!=='none').length,spinner:(()=>{const s=l.querySelector('.ps-live-search-item-loading');return s?getComputedStyle(s,'::before').borderTopColor:null})(),
 saleNew:(l.querySelector('.price-old + .price-new')?getComputedStyle(l.querySelector('.price-old + .price-new')).color:null)}})())`;
const jobs = [];
for (const w of [390, 768, 1440]) {
  const h = w === 390 ? 844 : 900;
  const J = (name, steps, wait, extra = {}) => jobs.push({ url: `http://127.0.0.1:${port}/?page=category`, w, h, out: `${O}/${pre}_ls_${name}_${w}.png`, pre: steps, wait, evalOut: EV, ...extra });
  if (mode !== 'timeout') {
  J('focus', ["document.getElementById('ps-live-search-input').focus()"], 300);
  J('loading', [type('pok')], 300, { stepWait: 250 });
  J('results', [type('pokemon')], 900);
  J('none', [type('zzz')], 900);
  }
  if (pre === 'after' && mode !== 'timeout') {
    J('error', [type('err')], 900);
    // Deterministic order tests: $.ajax is stubbed so replies can be delivered out of order.
    const stub = "window.__calls=[];$.ajax=function(o){window.__calls.push(o);return {abort:function(){}}};true";
    const json = q => `(await (await fetch('index.php?route=extension/ps_live_search/module/ps_live_search.autocomplete&search=${q}')).json())`;
    J('stale_error', [stub, type('hang'), type('pokemon'), `(async()=>{window.__calls[1].success(${json('pokemon')});window.__calls[0].error({},'timeout');return window.__calls.length})()`], 300, { logPre: true });
    J('stale_success', [stub, type('hang'), type('zzz'), `(async()=>{window.__calls[1].success(${json('zzz')});window.__calls[0].success(${json('pokemon')});return window.__calls.length})()`], 300, { logPre: true });
    J('abort', [stub, type('pokemon'), "(()=>{window.__calls[0].error({},'abort');return window.__calls.length})()"], 300, { logPre: true });
  }
  if (pre === 'after' && mode === 'timeout') J('timeout', [type('hang')], 8800);
}
process.stdout.write(JSON.stringify(jobs));
