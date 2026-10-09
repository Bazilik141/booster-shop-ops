const UI = {
  burger: <path d="M3 6h18M3 12h18M3 18h18" />,
  search: <><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></>,
  user: <><circle cx="12" cy="8.5" r="3.8" /><path d="M4.5 20c.8-3.8 3.9-5.8 7.5-5.8s6.7 2 7.5 5.8" /></>,
  tg: <path d="M3.5 11.5 20 5l-2.4 15-4.6-2.4-2.4 3.4-1.2-4.6 9.4-8.2-10.6 6-4.7-1.7Z" />,
  cart: <><path d="M4 7h16l-1.2 12.5a1.5 1.5 0 0 1-1.5 1.5H6.7a1.5 1.5 0 0 1-1.5-1.5Z" /><path d="M8.5 10a3.5 3.5 0 0 0 7 0" /></>,
  back: <path d="m14.5 5-7 7 7 7" />,
  close: <path d="M6 6l12 12M18 6 6 18" />,
  chevR: <path d="m9 5 7 7-7 7" />,
  chevD: <path d="m6 9 6 6 6-6" />,
  home: <path d="M4 11 12 4l8 7v9h-5.5v-6h-5v6H4Z" />,
  list: <><path d="M9 6h11M9 12h11M9 18h11" /><path d="M4 6h.01M4 12h.01M4 18h.01" /></>,
  grid: <><rect x="4" y="4" width="6.5" height="6.5" rx="1" /><rect x="13.5" y="4" width="6.5" height="6.5" rx="1" /><rect x="4" y="13.5" width="6.5" height="6.5" rx="1" /><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1" /></>,
  arrowR: <path d="M5 12h14m-6-6 6 6-6 6" />,
  alert: <><circle cx="12" cy="12" r="9" /><path d="M12 7.5v5.5M12 16.5h.01" /></>,
  sliders: <path d="M4 7h10M18 7h2M4 17h4M12 17h8M14 4v6M8 14v6" />,
  orders: <><path d="M3.5 7.5 12 4l8.5 3.5v9L12 20l-8.5-3.5Z" /><path d="m3.5 7.5 8.5 3.5 8.5-3.5M12 11v9" /></>,
  truck: <><path d="M2 5h12v10H2zM14 9h4.5l3 3.5V15H14z" /><circle cx="6" cy="17.5" r="1.8" /><circle cx="17" cy="17.5" r="1.8" /></>,
  shield: <><path d="M12 3 19 5.5v5c0 5-3 8-7 9-4-1-7-4-7-9v-5Z" /><path d="m8.8 11.8 2.3 2.3 4-4.3" /></>,
  bag: <><path d="M5 8h14l-1 12H6Z" /><path d="M9 8V7a3 3 0 0 1 6 0v1" /></>,
  check: <path d="M20 6 9 17l-5-5" />
};
function U({ n, s, sw, cls }) {
  return <svg className={'ub-ic ' + (cls || '')} width={s || 18} height={s || 18} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={sw || 1.8} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{UI[n]}</svg>;
}
function Ann({ children }) { return <span className="ub-ann">{children}</span>; }

const UB_CATS = [
  { k: 'pokemon', name: 'Pokémon TCG', dot: 'var(--bs-pokemon)', subs: [['Усі Pokémon'], ['Бустери Pokémon'], ['Бустер бокси Pokémon'], ['Набори'], ['Фігурки та декор', '/catalog/Pokemon/figurky-ta-dekor-pokemon', true]] },
  { k: 'onepiece', name: 'One Piece Card Game', dot: 'var(--bs-onepiece)', subs: [['Усі One Piece'], ['Бустери One Piece'], ['Фігурки та декор', '/catalog/One-Piece/figurky-ta-dekor-one-piece', true]] },
  { k: 'other', name: 'Інші TCG', dot: 'var(--bs-other-tcg)', subs: [['Усі Інші TCG'], ['Yu-Gi-Oh! OCG', null, false, 'var(--bs-yugioh)'], ['Magic: The Gathering', null, false, 'var(--bs-mtg)']] },
  { k: 'acc', name: 'Аксесуари', dot: 'var(--bs-accessories)' },
  { k: 'sale', name: 'Акції', dot: 'var(--bs-danger)', sale: true }
];

function Logo({ mob }) {
  return <a href="#" className="ub-logo" aria-label="Booster Shop — на головну"><img src="assets/bs-logo-crop.png" alt="Booster Shop" width={mob ? 92 : 128} height={mob ? 32 : 44} /></a>;
}
function CartBtn({ mob }) {
  if (mob) return <span className="ub-cart ub-cart-m" aria-label="Мій кошик"><U n="cart" s={20} sw={2} /><span className="ub-qty">2</span></span>;
  return <span className="ub-cart"><U n="cart" s={18} sw={2} /> Мій кошик - ₴230.00</span>;
}
function SearchField({ mob, value, focus }) {
  return (
    <div className={'ub-sf' + (focus ? ' is-focus' : '')}>
      <U n="search" s={16} />
      {value ? <span className="ub-sf-val">{value}<i className="ub-caret"></i></span> : <span className="ub-sf-ph">{mob ? 'Пошук' : 'Пошук бустерів, сетів, виробників...'}{focus && <i className="ub-caret"></i>}</span>}
    </div>
  );
}
function Header({ w, cat, scrolled, search, focus, children }) {
  const mob = w <= 768, desk = w >= 1024;
  return (
    <>
      <header className={'ub-h' + (scrolled ? ' is-scrolled' : '')}>
        <div className="ub-h-in">
          <span className={'ub-burger' + (desk && cat === 'a' ? ' has-label' : '')}><U n="burger" s={20} />{desk && cat === 'a' && <span>Каталог</span>}</span>
          <Logo mob={mob} />
          <div className="ub-h-search"><SearchField mob={mob} value={search} focus={focus} />{children}</div>
          {!mob && <><span className="ub-ghost"><U n="user" s={16} /> Акаунт</span><span className="ub-ghost"><U n="tg" s={16} /> Telegram</span></>}
          <CartBtn mob={mob} />
        </div>
        {scrolled && <span className="ub-sticky-tag">sticky · тінь --bs-sh-sm</span>}
      </header>
      {desk && cat === 'b' && !scrolled && (
        <nav className="ub-catrow" aria-label="Категорії">
          <div className="ub-catrow-in">{UB_CATS.map(c => <a key={c.k} href="#" className={c.sale ? 'is-sale' : ''}>{c.name}</a>)}</div>
        </nav>
      )}
    </>
  );
}
function Crumbs({ items }) {
  return <ul className="ub-crumbs"><li><a href="#" aria-label="Головна" className="ub-cr-home"><U n="home" s={12} sw={2.2} /></a></li>{items.map((t, i) => <li key={i}><span className={'ub-cr' + (i === items.length - 1 ? ' is-cur' : '')}>{t}</span></li>)}</ul>;
}
const UB_PRODUCTS = [
  ['Бустер Pokémon TCG: Mega Evolution — Chaos Rising (Англійське видання)', '₴330.00'],
  ['Містері бокс Pokémon TCG: Mystery Mix Standard (Японське видання)', '₴750.00'],
  ['Містері бокс Pokémon TCG: Mystery Mix XL (Японське видання)', '₴1100.00']
];
function Card({ p }) {
  return <div className="ub-pc"><div className="ub-pc-img"><span>фото</span></div><a href="#" className="ub-pc-n">{p[0]}</a><b className="ub-pc-p">{p[1]}</b><span className="ub-buy">Купити</span></div>;
}
function Cards({ n }) { return <div className="ub-grid">{UB_PRODUCTS.concat(UB_PRODUCTS).slice(0, n || 3).map((p, i) => <Card key={i} p={p} />)}</div>; }
function CategoryStub({ scrolled }) {
  return (
    <div className="ub-c">
      {!scrolled && <><Crumbs items={['Pokémon']} />
        <section className="ub-cathead"><h1>Pokémon <small>48 товарів</small></h1><div className="ub-chips">{[['Бустери', 15], ['Бустер бокси', 13], ['Набори', 13], ['Фігурки та декор', 21]].map(([t, n]) => <span key={t} className="ub-chip">{t} <i>{n}</i></span>)}</div></section></>}
      <Cards n={scrolled ? 6 : 3} />
      <p className="ub-stub">сторінка категорії — без змін</p>
    </div>
  );
}
function Burger({ open }) {
  return (
    <div className="ub-menu">
      <div className="ub-scrim"></div>
      <aside className="ub-panel" aria-label="Меню">
        <div className="ub-mh">
          <div className="ub-brandrow"><span className="ub-brand">Booster&nbsp;Shop <svg width="12" height="18" viewBox="0 0 12 18" aria-hidden="true"><polygon points="7.2,0 12,0 4.8,9 10.8,9 1.8,18 6.6,9.9 0,9.9" fill="var(--bs-blue)" /></svg></span><span className="ub-mclose"><U n="close" s={14} sw={2} /></span></div>
          <span className="ub-acct"><span className="ub-acct-ic"><U n="user" s={16} /></span><span className="ub-acct-l">Акаунт</span><U n="chevR" s={14} /></span>
          <span className="ub-orders"><U n="orders" s={16} sw={1.6} /> Мої замовлення</span>
        </div>
        <div className="ub-mb">
          <div className="ub-mlabel">Каталог</div>
          {UB_CATS.map(c => {
            const isOpen = c.k === open;
            return (
              <div key={c.k} className={'ub-mcat' + (isOpen ? ' is-open' : '')}>
                <span className={'ub-mrow' + (c.sale ? ' is-sale' : '')}><i className="ub-dot" style={{ background: c.dot }}></i><span className="ub-mname">{c.name}</span><U n={c.subs && isOpen ? 'chevD' : 'chevR'} s={14} /></span>
                {c.subs && isOpen && <div className="ub-subs">{c.subs.map((s, i) => <a key={i} href={s[1] || '#'} className={'ub-sub' + (s[2] ? ' is-new' : '')}>{s[3] && <i className="ub-dot" style={{ background: s[3] }}></i>}{s[0]}{s[2] && <Ann>нове посилання</Ann>}</a>)}</div>}
              </div>
            );
          })}
          <div className="ub-mlabel ub-mlabel-sep">Інформація</div>
          <span className="ub-minfo"><U n="truck" s={17} sw={1.5} /> Оплата і доставка</span>
          <span className="ub-minfo"><U n="shield" s={17} sw={1.5} /> Гарантія оригінальності</span>
          <span className="ub-minfo"><U n="bag" s={17} sw={1.5} /> Про магазин</span>
        </div>
        <div className="ub-mf"><span className="ub-mtg"><U n="tg" s={17} sw={1.5} /> Наш Telegram-канал</span></div>
      </aside>
    </div>
  );
}
const UB_VH = { 390: 844, 768: 1024, 1440: 900 };
function Viewport({ w, fixed, fold, children }) {
  return (
    <div className={'ub-vp' + (fixed ? ' is-fixed' : '')} style={{ width: w, height: fixed ? UB_VH[w] : undefined }}>
      {children}
      {fold && <div className="ub-fold" style={{ top: UB_VH[w] }}><span>межа першого екрана · {UB_VH[w]} px</span></div>}
      {!fixed && <div className="ub-foot">футер без змін</div>}
    </div>
  );
}
Object.assign(window, { U, Ann, Header, Crumbs, Cards, CategoryStub, Burger, Viewport, UB_CATS, UB_PRODUCTS });
