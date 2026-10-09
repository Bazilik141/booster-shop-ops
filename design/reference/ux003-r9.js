const SUB=[['Бустери',15],['Бустер бокси',13],['Набори',13],['Фігурки та декор',21]];
const GROUPS=[['type','Тип товару',['Блістер бокс','Бустер','Бустер бокс','Набори','Аксесуар']],['country','Країна',['Корея','США/Європа','Україна','Японія']]];
const PRODUCTS=[
['Бустер Pokémon TCG: Mega Evolution — Chaos Rising (Англійське видання)',330,'Бустер','США/Європа'],
['Містері бокс Pokémon TCG: Mystery Mix Standard (Японське видання)',750,'Набори','Японія'],
['Містері бокс Pokémon TCG: Mystery Mix XL (Японське видання)',1100,'Набори','Японія'],
['Набір Pokémon TCG: Special Card Set MEGA Gallade EX (Японське видання)',1600,'Набори','Японія'],
['Бустер Pokémon TCG: Inferno X (Японське видання)',220,'Бустер','Японія'],
['Бустер Pokémon TCG: Black Bolt (Японське видання)',350,'Бустер','Японія'],
['Бустер Pokémon TCG: Abyss Eye (Японське видання)',210,'Бустер','Японія']];
const SORTS=['За замовчуванням','Назва (А - Я)','Ціна (низька > висока)','Ціна (висока > низька)'];
const TOTAL=48;
function pl(n){const a=n%10,b=n%100;return n+' '+(a===1&&b!==11?'товар':a>=2&&a<=4&&(b<12||b>14)?'товари':'товарів');}
const IC={menu:'<path d="M4 7h16M4 12h16M4 17h16"/>',search:'<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4-4"/>',cart:'<path d="M5.5 8h13l-1.2 12H6.7L5.5 8Z"/><path d="M9 8V7a3 3 0 0 1 6 0v1"/>',user:'<circle cx="12" cy="8" r="3.5"/><path d="M5 20c1-4 4-5.5 7-5.5s6 1.5 7 5.5"/>',tg:'<path d="m21 4-18 7 6 2 2 6 3-4 5 4 2-15Z"/><path d="m9 13 9-6"/>',sliders:'<path d="M4 7h9M17 7h3M4 17h3M11 17h9"/><circle cx="15" cy="7" r="2"/><circle cx="9" cy="17" r="2"/>',chev:'<path d="m6 9 6 6 6-6"/>',x:'<path d="M6 6l12 12M18 6 6 18"/>',check:'<path d="m5 12 5 5 9-10"/>',home:'<path d="M5 11 12 5l7 6v8H5z"/>'};
function ic(n,s=18,sw=1.8,c=''){return `<svg class="ic ${c}" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="${sw}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${IC[n]}</svg>`;}
function results(sel,sort){
  let r=sel.length?PRODUCTS.filter(p=>{const t=sel.filter(v=>GROUPS[0][2].includes(v)),c=sel.filter(v=>GROUPS[1][2].includes(v));return(!t.length||t.includes(p[2]))&&(!c.length||c.includes(p[3]));}):PRODUCTS.slice();
  if(sort===1)r.sort((a,b)=>a[0].localeCompare(b[0],'uk'));
  if(sort===2)r.sort((a,b)=>a[1]-b[1]);
  if(sort===3)r.sort((a,b)=>b[1]-a[1]);
  return r;
}
function total(sel){return sel.length?results(sel,0).length:TOTAL;}
function badgeHtml(v,n){if(!v||!n)return '';return `<span class="cb cb-${v}" aria-hidden="true">${n>9?'9+':n}</span>`;}
function header(w,o={}){
  const mob=w<576,desk=w>=992,v=o.badge,n=o.cart||0;
  const aria=n?`Кошик: ${pl(n)}`:'Кошик порожній';
  const cart=mob?`<button class="hcart sq" aria-label="${aria}">${ic('cart',22)}${badgeHtml(v,n)}</button>`:desk?`<button class="hcart">${ic('cart',20)}Мій кошик - ₴200.00</button>`:`<button class="hcart" aria-label="${aria}, ₴200.00"><span class="hci">${ic('cart',20)}${badgeHtml(v,n)}</span>₴200.00</button>`;
  return `<header class="hd"><div class="hd-in">${desk?`<button class="hbtn">${ic('menu',20)}Каталог</button>`:`<button class="hbtn sq" aria-label="Меню">${ic('menu',22)}</button>`}<img class="logo" src="bs-logo-crop.png" alt="Booster Shop"><div class="hsearch">${ic('search',17)}<span>${mob?'Пошук':'Пошук бустерів, сетів, виробників…'}</span></div>${mob?'':desk?`<span class="hlink">${ic('user',17)}Акаунт</span><span class="hlink">${ic('tg',17)}Telegram</span>`:`<span class="hlink">${ic('user',20)}</span><span class="hlink">${ic('tg',20)}</span>`}${cart}</div></header>`;
}
function render(st,w){
  const mob=w<576,desk=w>=992,f=k=>st.kf===k?' kf':'';
  const sel=st.sel,n=sel.length,cnt=st.loading?total(st.applied):total(sel);
  const fbtn=`<button type="button" class="fbtn${st.open?' is-on':''}${f('fbtn')}" data-act="panel" data-fk="fbtn" aria-expanded="${st.open}" aria-controls="fpanel">${ic('sliders',18)}<span>Фільтр</span>${n?`<span class="cnt" aria-hidden="true">${n}</span>`:''}${ic('chev',16,2,st.open?'up':'')}</button>`;
  const sort=`<label class="sort"><span class="vh">Сортування</span><select data-act="sort">${SORTS.map((s,i)=>`<option value="${i}"${i===st.sort?' selected':''}>${s}</option>`).join('')}</select>${ic('chev',16,2)}</label>`;
  const pills=SUB.map(([t,c])=>`<a href="#" class="pill" onclick="return false">${t} <i>${c}</i></a>`).join('');
  const head=mob?`<p class="ch-sm"><b>Pokémon</b> · <span data-cnt>${pl(cnt)}</span></p>`:`<h1 class="ch-h1">Pokémon <small>${pl(cnt)}</small></h1>`;
  const row=desk?`<div class="ch-row"><div class="seg">${pills}</div><div class="tools">${fbtn}${sort}</div></div>`:`<div class="pills">${pills}</div><div class="tools2">${fbtn}${sort}</div>`;
  let panel='';
  if(st.open){
    panel=`<div class="panel" id="fpanel"><div class="groups">${GROUPS.map(([k,t,vals])=>{const go=st.g[k]!==false,gn=vals.filter(v=>sel.includes(v)).length;return `<div class="grp"><button type="button" class="gt${f('g:'+k)}" data-act="grp" data-k="${k}" data-fk="g:${k}" aria-expanded="${go}">${t}${gn?`<span class="cnt" aria-label="вибрано ${gn}">${gn}</span>`:''}${ic('chev',16,2,go?'up':'')}</button>${go?`<div class="cks">${vals.map(v=>`<label class="ck${f('cb:'+v)}"><input type="checkbox" data-v="${v}" data-fk="cb:${v}"${sel.includes(v)?' checked':''}><span class="box">${ic('check',12,3)}</span>${v}</label>`).join('')}</div>`:''}</div>`;}).join('')}</div>${desk?`<div class="pfoot"><span class="pres">${st.loading?'<span class="spin"></span>Оновлюємо…':cnt?'Знайдено '+pl(cnt):'Нічого не знайдено'}</span><button type="button" class="btn-s${f('close')}" data-act="close" data-fk="close">Згорнути ${ic('chev',14,2,'up')}</button></div>`:''}</div>`;
  }
  const chips=n?`<div class="fchips"><span class="fl">Фільтр:</span>${sel.map(v=>`<button type="button" class="fchip${f('chip:'+v)}" data-act="rm" data-v="${v}" data-fk="chip:${v}" aria-label="Прибрати фільтр «${v}»">${v}${ic('x',14,2.4)}</button>`).join('')}${n>=2?`<button type="button" class="reset${f('reset')}" data-act="resetall" data-fk="reset">Скинути все</button>`:''}</div>`:'';
  const list=st.loading?results(st.applied,st.prevSort??st.sort):results(sel,st.sort);
  const empty=!st.loading&&list.length===0;
  const cards=list.map(p=>`<div class="pc"><div class="pc-img"><span>фото товару</span></div><a href="#" class="pc-n" onclick="return false">${p[0]}</a><b class="pc-p">₴${p[1]}.00</b><button class="buy">Купити</button></div>`).join('');
  const emptyHtml=`<div class="empty"><h2>Нічого не знайдено з цими фільтрами</h2><p>Приберіть один із фільтрів або скиньте всі.</p><button type="button" class="btn-s blue${f('emptyreset')}" data-act="resetall" data-fk="emptyreset">Скинути фільтри</button></div>`;
  const pag=st.pag&&!empty?`<nav class="pag" aria-label="Сторінки"><a href="#" class="pg is-on" aria-current="page">1</a><a href="#" class="pg">2</a><a href="#" class="pg" aria-label="Наступна сторінка">›</a></nav>`:'';
  let sticky='';
  if(st.open&&!desk){
    sticky=empty?`<div class="sticky two"><span>0 товарів з цими фільтрами</span><button type="button" class="btn-s" data-act="resetall" data-fk="reset">Скинути</button></div>`:`<div class="sticky"><button type="button" class="btn-p${f('show')}" data-act="show" data-fk="show"${st.loading?' aria-disabled="true"':''}>${st.loading?'<span class="spin w"></span>Оновлюємо…':'Показати '+pl(cnt)}</button></div>`;
  }
  return `${header(w)}<main class="ct"><nav class="crumbs"><span class="home">${ic('home',13,2)}</span>${ic('chev',12,2,'rt')}<span class="crumb">Pokémon</span></nav><section class="ch">${head}${row}${panel}${chips}</section><div class="res${st.loading?' is-loading':''}${f('res')}" tabindex="-1" data-fk="res" aria-label="Товари" aria-busy="${!!st.loading}"><div class="bar"></div>${empty?emptyHtml:`<div class="grid">${cards}</div>`}${pag}</div><div class="vh" role="status" aria-live="polite">${st.say||''}</div></main>${sticky}`;
}
function mount(el){
  const w=+el.dataset.w,h=+el.dataset.h||(w<576?780:w<992?900:860);
  const st=Object.assign({sel:[],applied:[],open:false,g:{},sort:0,loading:false,say:''},JSON.parse(el.dataset.st||'{}'));
  if(!el.dataset.st||!JSON.parse(el.dataset.st).applied)st.applied=st.applied.length?st.applied:st.sel.slice();
  if(w<576&&st.g.country===undefined)st.g.country=false;
  const lab=el.dataset.label||'';
  el.innerHTML=`<figcaption><b>${w}</b> px${lab?' · '+lab:''}</figcaption><div class="zw"><div class="vp" style="width:${w}px;height:${h}px"></div></div><div class="live"><span>aria-live</span><em></em></div>`;
  const vp=el.querySelector('.vp'),live=el.querySelector('.live em');
  let timer;
  function draw(fk){
    const y=vp.scrollTop;vp.innerHTML=render(st,w);vp.scrollTop=y;
    live.textContent=st.say?'«'+st.say+'»':'—';
    if(fk){const t=vp.querySelector(`[data-fk="${CSS.escape(fk)}"]`);if(t)t.focus({preventScroll:true});}
  }
  function load(fk,prefix){
    st.kf=null;st.loading=true;st.say='';draw(fk);clearTimeout(timer);
    timer=setTimeout(()=>{st.loading=false;st.applied=st.sel.slice();st.prevSort=st.sort;const c=total(st.sel);st.say=(prefix||'')+(c?'Знайдено '+pl(c)+'.':'Нічого не знайдено з цими фільтрами.');draw(fk);},900);
  }
  vp.addEventListener('change',e=>{
    const t=e.target;
    if(t.matches('input[type=checkbox]')){const v=t.dataset.v;st.sel=t.checked?[...st.sel,v]:st.sel.filter(x=>x!==v);load('cb:'+v);}
    if(t.matches('select')){st.prevSort=st.sort;st.sort=+t.value;load(null,'Відсортовано: '+SORTS[st.sort]+'. ');vp.querySelector('select')?.focus({preventScroll:true});}
  });
  vp.addEventListener('click',e=>{
    const b=e.target.closest('[data-act]');if(!b||b.tagName==='SELECT')return;const a=b.dataset.act;
    if(a==='panel'){st.open=!st.open;st.kf=null;draw('fbtn');}
    if(a==='close'){st.open=false;draw('fbtn');}
    if(a==='grp'){const k=b.dataset.k;st.g[k]=st.g[k]===false;draw('g:'+k);}
    if(a==='rm'){const v=b.dataset.v,i=st.sel.indexOf(v);st.sel=st.sel.filter(x=>x!==v);const nx=st.sel[i]||st.sel[i-1];load(nx?'chip:'+nx:'fbtn','Фільтр «'+v+'» прибрано. ');}
    if(a==='resetall'){st.sel=[];load('fbtn','Фільтри скинуто. ');}
    if(a==='show'){if(st.loading)return;st.open=false;draw();const r=vp.querySelector('.res');vp.scrollTo({top:r.offsetTop-12,behavior:'smooth'});r.focus({preventScroll:true});}
    if(b.classList.contains('pg')){e.preventDefault();}
  });
  draw();
  if(st.scroll==='bottom')vp.scrollTop=vp.scrollHeight;
  if(st.scroll==='res')vp.scrollTop=vp.querySelector('.res').offsetTop-12;
}
function fit(){
  document.querySelectorAll('.fr').forEach(fr=>{const w=+fr.dataset.w,av=fr.parentElement.clientWidth;const z=Math.min(1,av/w);fr.querySelector('.zw').style.zoom=z;fr.querySelector('figcaption').dataset.z=z<1?Math.round(z*100)+'%':'';});
  document.querySelectorAll('.hz').forEach(hz=>{const w=+hz.dataset.w,av=hz.parentElement.clientWidth;hz.style.zoom=Math.min(1,av/(w+12));hz.style.padding='8px 6px 0';});
}
function mountBadges(){
  document.querySelectorAll('[data-hdr]').forEach(el=>{const w=+el.dataset.w;const o=JSON.parse(el.dataset.hdr);el.innerHTML=`<div class="hz" data-w="${w}"><div class="vp hdr" style="width:${w}px">${header(w,o)}</div></div><p class="hcap">${o.cart?'aria-label: «Кошик: '+pl(o.cart)+'»':'aria-label: «Кошик порожній» · без бейджа'}</p>`;});
}
document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('.fr').forEach(mount);mountBadges();fit();addEventListener('resize',fit);});
