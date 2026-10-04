const [O, port] = process.argv.slice(2); const P = port || 8791; const pre = port === '8790' ? 'before' : 'after';
const H = `(()=>{const h=document.querySelector('.bs-header').getBoundingClientRect();return {hdrTop:Math.round(h.top),hdrH:Math.round(h.height),scrolled:document.querySelector('.bs-header').classList.contains('is-scrolled'),shadow:getComputedStyle(document.querySelector('.bs-header')).boxShadow!=='none',var:getComputedStyle(document.documentElement).getPropertyValue('--bs-header-sticky-h'),ph:document.getElementById('ps-live-search-input').placeholder,burgerW:Math.round(document.getElementById('bs-menu-open').getBoundingClientRect().width),label:(document.querySelector('.bs-burger__label')||{}).offsetWidth||0,hscroll:document.documentElement.scrollWidth>innerWidth}})()`;
const jobs = [];
for (const w of [390, 768, 1440]) {
  for (const pg of ['category', 'search&search=pokemon', 'info', 'stack', 'checkout', 'success&st=d']) {
    jobs.push({ url: `http://127.0.0.1:${P}/?page=${pg}`, w, h: w === 390 ? 844 : 900, out: `${O}/${pre}_scroll_${pg.split('&')[0]}_${w}.png`, pre: ['window.scrollTo(0,700)'], wait: 500, evalOut: `JSON.stringify(${H})` });
  }
  jobs.push({ url: `http://127.0.0.1:${P}/?page=category`, w, h: w === 390 ? 844 : 900, out: `${O}/${pre}_top_category_${w}.png`, evalOut: `JSON.stringify(${H})` });
}
if (pre === 'after') {
  // burger open, Pokémon expanded
  for (const w of [390, 1440]) jobs.push({ url: `http://127.0.0.1:${P}/?page=category`, w, h: w === 390 ? 844 : 900, out: `${O}/after_burger_${w}.png`, pre: ['window.scrollTo(0,500)', "document.getElementById('bs-menu-open').click()", "document.querySelector('[data-bs-accordion]').click()"], wait: 600,
    evalOut: "JSON.stringify({links:[...document.querySelectorAll('.bs-menu__sub')].map(a=>a.getAttribute('href')).filter(h=>/figurky/.test(h)),brandImg:!!document.querySelector('.bs-menu__brand img'),brandW:Math.round(document.querySelector('.bs-menu__brand img').getBoundingClientRect().width),brandHref:document.querySelector('.bs-menu__brand').getAttribute('href'),menuOnTop:document.elementFromPoint(50,30).closest('#bs-menu')!==null})" });
  // mobile search overlay after scroll
  jobs.push({ url: `http://127.0.0.1:${P}/?page=category`, w: 390, h: 844, out: `${O}/after_msearch_scrolled_390.png`, pre: ['window.scrollTo(0,900)', "document.getElementById('ps-live-search-input').focus()", "(()=>{const i=document.getElementById('ps-live-search-input');i.value='pokemon';i.dispatchEvent(new Event('input',{bubbles:true}))})()"], wait: 1200,
    evalOut: "JSON.stringify({open:document.getElementById('bs-msearch').classList.contains('is-open'),dropTop:Math.round(document.getElementById('ps-live-search').getBoundingClientRect().top),hdrVar:getComputedStyle(document.documentElement).getPropertyValue('--bs-header-h'),clear:(()=>{const c=document.querySelector('[data-bs-search-clear]');const r=c.getBoundingClientRect();return {hidden:c.hidden,w:r.width,h:r.height}})(),fieldTop:Math.round(document.querySelector('.bs-msearch__field').getBoundingClientRect().top)})" });
  // mini-cart over buy bar
  jobs.push({ url: `http://127.0.0.1:${P}/?page=stack`, w: 390, h: 844, out: `${O}/after_minicart_stack_390.png`, pre: ['window.scrollTo(0,600)', "document.querySelector('[data-bs-mini-cart-open]').click()"], wait: 700,
    evalOut: "JSON.stringify((()=>{const b=document.querySelector('.bs-mini-cart__foot a.bs-btn-primary');const r=b.getBoundingClientRect();const t=document.elementFromPoint(r.left+r.width/2,r.top+r.height/2);return {btnBottom:Math.round(r.bottom),hitIsBtn:b.contains(t)||t===b,hit:t.className}})())" });
  // checkout aside offset
  jobs.push({ url: `http://127.0.0.1:${P}/?page=checkout`, w: 1440, h: 900, out: `${O}/after_checkout_aside_1440.png`, pre: ['window.scrollTo(0,800)'], wait: 500,
    evalOut: "JSON.stringify({asideTop:Math.round(document.querySelector('.bs-co-aside').getBoundingClientRect().top),hdrBottom:Math.round(document.querySelector('.bs-header').getBoundingClientRect().bottom)})" });
  // content TOC anchor
  jobs.push({ url: `http://127.0.0.1:${P}/?page=info`, w: 1440, h: 900, out: `${O}/after_info_anchor_1440.png`, pre: ["document.querySelectorAll('.bs-cp-toc__link')[2].click()"], wait: 900,
    evalOut: "JSON.stringify({h2Top:Math.round(document.querySelectorAll('.bs-cp-main h2')[2].getBoundingClientRect().top),hdrBottom:Math.round(document.querySelector('.bs-header').getBoundingClientRect().bottom),tocTop:Math.round(document.querySelector('.bs-cp-toc').getBoundingClientRect().top)})" });
  jobs.push({ url: `http://127.0.0.1:${P}/?page=stack`, w: 390, h: 844, out: `${O}/after_review_anchor_390.png`, pre: ["document.querySelector('.nav-tabs').scrollIntoView({block:'start'})"], wait: 600,
    evalOut: "JSON.stringify({tabsTop:Math.round(document.querySelector('.nav-tabs').getBoundingClientRect().top),hdrBottom:Math.round(document.querySelector('.bs-header').getBoundingClientRect().bottom)})" });
}
process.stdout.write(JSON.stringify(jobs));
