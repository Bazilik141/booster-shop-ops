const LS_PRODUCTS = [
  ['Бустер Pokémon TCG: Mega Evolution — Chaos Rising (Англійське видання)', '₴330.00'],
  ['Pokémon TCG: Scarlet & Violet — Prismatic Evolutions Elite Trainer Box (Англійське видання)', '₴3450.00', '₴3900.00'],
  ['Містері бокс Pokémon TCG: Mystery Mix Standard (Японське видання)', '₴750.00'],
  ['Містері бокс Pokémon TCG: Mystery Mix XL (Японське видання)', '₴1100.00']
];
const LS_CATS = ['Pokémon', 'Бустери Pokémon', 'Бустер бокси Pokémon'];
function LiveList({ st, q, mob }) {
  if (st === 'empty') return null;
  return (
    <ul className={'ub-ls' + (mob ? ' is-mob' : '')} id="ps-live-search">
      {st === 'loading' && <li className="ub-ls-load"><span className="ub-spin" aria-hidden="true"></span><span className="ub-sr">Завантаження</span></li>}
      {st === 'results' && <>
        <li className="ub-ls-sub">Результати за: <output>{q}</output></li>
        <li><span className="ub-ls-h">Товари</span></li>
        {LS_PRODUCTS.map((p, i) => (
          <li key={i}><a href="#" className="ub-ls-item"><span className="ub-ls-th"></span><span className="ub-ls-info"><strong className="ub-ls-name">{p[0]}</strong><span className="ub-ls-prices"><span className={'ub-ls-new' + (p[2] ? ' is-sale' : '')}>{p[1]}</span>{p[2] && <s className="ub-ls-old">{p[2]}</s>}</span></span></a></li>
        ))}
        <li><span className="ub-ls-h">Категорії</span></li>
        {LS_CATS.map(c => <li key={c}><a href="#" className="ub-ls-cat">{c}<U n="chevR" s={14} /></a></li>)}
        <li><a href="#" className="ub-ls-more">Усі результати <U n="arrowR" s={16} sw={2} /></a></li>
      </>}
      {st === 'none' && <li className="ub-ls-none"><span className="ub-ls-none-ic"><U n="search" s={20} /></span><strong>Нічого не знайдено за «{q}»</strong><p>Перевірте написання або спробуйте коротший запит. <Ann>копія — пропозиція</Ann></p></li>}
      {st === 'error' && <li className="ub-ls-err" role="alert"><span className="ub-ls-none-ic is-err"><U n="alert" s={20} /></span><strong>Не вдалося завантажити підказки</strong><span className="ub-btn ub-btn-sec">Шукати на сторінці результатів <U n="arrowR" s={16} sw={2} /></span><Ann>копія — пропозиція · Booster JS в init</Ann></li>}
    </ul>
  );
}
function MobileOverlay({ st, q }) {
  return (
    <>
      <div className="ub-mo-bar">
        <span className="ub-mo-back" aria-label="Назад"><U n="back" s={20} sw={2} /></span>
        <div className="ub-sf is-focus ub-mo-field"><U n="search" s={16} />{q ? <span className="ub-sf-val">{q}<i className="ub-caret"></i></span> : <span className="ub-sf-ph">Пошук<i className="ub-caret"></i></span>}
        {q && <span className="ub-mo-clear" aria-label="Очистити"><span><U n="close" s={12} sw={2.4} /></span></span>}</div>
      </div>
      <div className="ub-mo-scrim"></div>
      {st !== 'empty' && <div className="ub-mo-drop"><LiveList st={st} q={q} mob /></div>}
    </>
  );
}
function SearchForm({ q }) {
  return (
    <div className="ub-sform">
      <div className="ub-sform-g">
        <label className="ub-flabel" htmlFor="input-search">Пошук</label>
        <span className="ub-input" id="input-search">{q}</span>
        <label className="ub-check"><span className="ub-box"></span> Шукати в описі товарів</label>
      </div>
      <div className="ub-sform-g">
        <label className="ub-flabel" htmlFor="input-category">Категорія</label>
        <span className="ub-input ub-select" id="input-category">Всі категорії <U n="chevD" s={16} /></span>
        <label className="ub-check is-dis"><span className="ub-box"></span> Пошук у підкатегоріях</label>
      </div>
      <div className="ub-sform-act"><span className="ub-btn ub-btn-pri" id="button-search">Пошук</span></div>
    </div>
  );
}
function SearchPage({ w, st }) {
  const none = st === 'none';
  const q = none ? 'zzz' : 'pokemon';
  const open = st === 'open' || none;
  const mob = w <= 768;
  return (
    <div className="ub-c">
      <Crumbs items={['Пошук']} />
      <div className="ub-sp-head">
        <h1>Пошук - {q}</h1>
        <details className="ub-refine" open={open}>
          <summary className="ub-btn ub-btn-sec ub-refine-btn"><U n="sliders" s={16} /> Змінити пошук <U n="chevD" s={16} cls={open ? 'is-up' : ''} /><Ann>копія — пропозиція</Ann></summary>
          <SearchForm q={q} />
        </details>
      </div>
      <h2 className="ub-sr">Результати пошуку</h2>
      {!none && <>
        <div className="ub-dc" id="display-control">
          {!mob && <div className="ub-view"><span className="ub-vbtn is-on" id="button-list" aria-label="Список"><U n="list" s={18} /></span><span className="ub-vbtn" id="button-grid" aria-label="Сітка"><U n="grid" s={18} /></span></div>}
          <label className="ub-sel"><span>Сортування</span><span className="ub-input ub-select" id="input-sort">За замовчуванням <U n="chevD" s={16} /></span></label>
          <label className="ub-sel ub-sel-lim"><span>Показати</span><span className="ub-input ub-select" id="input-limit">15 <U n="chevD" s={16} /></span></label>
        </div>
        <Cards n={3} />
        <p className="ub-stub">картки RD-04 без змін · пагінація і «Показано з 1 по 15…» — без змін</p>
      </>}
      {none && <>
        <div className="ub-empty" role="status">
          <span className="ub-empty-ic"><U n="search" s={24} /></span>
          <p className="ub-empty-t">Немає товарів, які відповідають критеріям пошуку.</p>
        </div>
        <section className="ub-next">
          <h3>Подивіться розділи <Ann>копія — пропозиція</Ann></h3>
          <div className="ub-next-l">{UB_CATS.slice(0, 4).map(c => <a key={c.k} href="#" className="ub-next-a"><i className="ub-dot" style={{ background: c.dot }}></i>{c.name}<U n="chevR" s={14} /></a>)}</div>
          <div className="ub-next-tg">
            <p>Не знайшли? Напишіть у Telegram — привеземо під замовлення <Ann>копія — пропозиція</Ann></p>
            <a href="https://telegram.me/boostershop_tcg" className="ub-btn ub-btn-sec"><svg width="17" height="17" viewBox="0 0 24 24" aria-hidden="true"><path fill="#229ED9" d="M21.2 4.3 2.9 11.4c-1 .4-1 1.7 0 2l4.5 1.5 1.7 5.3c.2.7 1.1.9 1.6.4l2.6-2.4 4.7 3.4c.6.4 1.4.1 1.6-.6l3-14.6c.2-1-.6-1.5-1.4-1.1Zm-3.4 3.5-7.9 7.1-.3 3-1.2-3.9 9-6.4c.3-.2.6.1.4.2Z" /></svg> Написати в Telegram</a>
          </div>
        </section>
      </>}
    </div>
  );
}
Object.assign(window, { LiveList, MobileOverlay, SearchPage });
