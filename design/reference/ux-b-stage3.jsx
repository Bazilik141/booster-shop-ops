function BrandHead({ v }) {
  let left;
  if (v === 'cur') left = <span className="ub-brand">Booster&nbsp;Shop <svg width="12" height="18" viewBox="0 0 12 18" aria-hidden="true"><polygon points="7.2,0 12,0 4.8,9 10.8,9 1.8,18 6.6,9.9 0,9.9" fill="var(--bs-blue)" /></svg></span>;
  if (v === 'a') left = <a href="#" className="s3-blogo" aria-label="Booster Shop — на головну"><img src="assets/bs-logo-crop.png" alt="Booster Shop" width="110" height="38" /></a>;
  if (v === 'b') left = <span className="s3-bmenu">Меню</span>;
  if (v === 'c') left = <span className="s3-bink">Booster&nbsp;Shop <svg width="12" height="18" viewBox="0 0 12 18" aria-hidden="true"><polygon points="7.2,0 12,0 4.8,9 10.8,9 1.8,18 6.6,9.9 0,9.9" fill="var(--bs-pokemon)" /></svg></span>;
  return <div className="ub-brandrow">{left}<span className="ub-mclose"><U n="close" s={14} sw={2} /></span></div>;
}
function BrandPanel({ v }) {
  return (
    <div className="s3-bp">
      <div className="ub-mh">
        <BrandHead v={v} />
        <span className="ub-acct"><span className="ub-acct-ic"><U n="user" s={16} /></span><span className="ub-acct-l">Акаунт</span><U n="chevR" s={14} /></span>
        <span className="ub-orders"><U n="orders" s={16} sw={1.6} /> Мої замовлення</span>
      </div>
      <div className="ub-mlabel">Каталог</div>
      {UB_CATS.slice(0, 3).map(c => <span key={c.k} className="ub-mrow"><i className="ub-dot" style={{ background: c.dot }}></i><span className="ub-mname">{c.name}</span><U n="chevR" s={14} /></span>)}
    </div>
  );
}

function GridDemo({ w, v }) {
  const mob = w <= 768;
  const bsCont = w >= 1200 ? 1140 : w >= 992 ? 960 : w >= 768 ? 720 : w >= 576 ? 540 : w;
  let cw = bsCont, pad = 12;
  if (v === 'a' && w < 768) { cw = w; pad = 10; }
  if (v === 'b' && w < 992) { cw = w; pad = mob ? 10 : 32; }
  return (
    <div className={'ub-vp s3-gv s3-gv-' + v} style={{ width: w, '--cw': bsCont + 'px' }}>
      <Header w={w} cat="a" />
      <div className="s3-gc" style={{ maxWidth: cw, paddingLeft: pad, paddingRight: pad }}>
        <Crumbs items={['Pokémon']} />
        <section className="ub-cathead"><h1>Pokémon <small>48 товарів</small></h1><div className="ub-chips s3-chips2">{[['Бустери', 15], ['Бустер бокси', 13], ['Набори', 13], ['Фігурки та декор', 21]].map(([t, n]) => <span key={t} className="ub-chip">{t} <i>{n}</i></span>)}</div></section>
        <div className="s3-act"><span className="ub-btn ub-btn-sec"><U n="sliders" s={16} /> Фільтр</span><span className="ub-input ub-select">За замовчуванням <U n="chevD" s={16} /></span></div>
        <div className="s3-g2">{UB_PRODUCTS.slice(0, 2).map((p, i) => <div key={i} className="ub-pc"><div className="ub-pc-img s3-short"><span>фото</span></div><a href="#" className="ub-pc-n">{p[0]}</a><b className="ub-pc-p">{p[1]}</b><span className="ub-buy">Купити</span></div>)}</div>
      </div>
      <div className="s3-edge s3-edge-l" style={{ left: 'calc(50% - ' + (cw / 2 - pad) + 'px)' }}></div>
      <div className="s3-edge s3-edge-r" style={{ right: 'calc(50% - ' + (cw / 2 - pad) + 'px)' }}></div>
    </div>
  );
}

const S3_TYPES = [['Блістер бокс'], ['Бустер', true], ['Бустер бокс'], ['Набори'], ['Аксесуар']];
const S3_COUNTRY = [['Корея'], ['США/Європа'], ['Україна'], ['Японія']];
function Checks({ items }) {
  return <div className="s3-checks">{items.map(([t, on]) => <label key={t} className="s3-ck"><span className={'s3-box' + (on ? ' is-on' : '')}>{on && <U n="check" s={12} sw={3} />}</span>{t}</label>)}</div>;
}
function FilterAside({ v }) {
  if (v === 'cur') return (
    <aside className="s3-fa-cur">
      <div className="s3-fc-h"><span className="s3-fa-ic">▼</span> Фільтр</div>
      <div className="s3-fc-g">Тип товару</div>
      <div className="s3-fc-b">{S3_TYPES.map(([t, on]) => <label key={t}><span className={'s3-fc-box' + (on ? ' is-on' : '')}></span>{t}</label>)}</div>
      <div className="s3-fc-g">Країна</div>
      <div className="s3-fc-b">{S3_COUNTRY.map(([t]) => <label key={t}><span className="s3-fc-box"></span>{t}</label>)}</div>
      <div className="s3-fc-f"><span className="s3-fc-btn">▼ Пошук</span></div>
    </aside>
  );
  if (v === 'a') return (
    <aside className="s3-fa">
      <div className="s3-fa-h"><U n="sliders" s={16} /> Фільтр</div>
      <div className="s3-fa-g"><div className="s3-fa-t">Тип товару</div><Checks items={S3_TYPES} /></div>
      <div className="s3-fa-g"><div className="s3-fa-t">Країна</div><Checks items={S3_COUNTRY} /></div>
    </aside>
  );
  return (
    <aside className="s3-fa s3-fb">
      <div className="s3-fa-h"><U n="sliders" s={16} /> Фільтр <span className="s3-reset">Скинути</span></div>
      <div className="s3-fb-g is-open"><div className="s3-fb-t">Тип товару <span className="s3-cnt">1</span><U n="chevD" s={16} cls="is-up" /></div><Checks items={S3_TYPES} /></div>
      <div className="s3-fb-g"><div className="s3-fb-t">Країна <U n="chevD" s={16} /></div></div>
    </aside>
  );
}
function FilterDemo({ v }) {
  const w = 1440;
  const side = v !== 'c';
  return (
    <div className="ub-vp s3-fv" style={{ width: w }}>
      <Header w={w} cat="a" />
      <div className="ub-c">
        <Crumbs items={['Pokémon']} />
        <div className={side ? 's3-flay' : ''}>
          <div>
            <section className="ub-cathead s3-ch">
              <h1>Pokémon <small>48 товарів</small></h1>
              <div className="s3-chrow">
                <div className="ub-chips">{[['Бустери', 15], ['Бустер бокси', 13], ['Набори', 13], ['Фігурки та декор', 21]].map(([t, n]) => <span key={t} className="ub-chip">{t} <i>{n}</i></span>)}</div>
                <span className="ub-input ub-select s3-sort">За замовчуванням <U n="chevD" s={16} /></span>
              </div>
              {v === 'c' && <div className="s3-fbar">
                <span className="s3-fbtn is-on">Тип товару <span className="s3-cnt">1</span><U n="chevD" s={16} cls="is-up" />
                  <div className="s3-pop"><Checks items={S3_TYPES} /></div>
                </span>
                <span className="s3-fbtn">Країна <U n="chevD" s={16} /></span>
              </div>}
              {v !== 'cur' && <div className="s3-chips-act"><span className="s3-fl">Фільтр:</span><span className="s3-fchip">Бустер <U n="close" s={12} sw={2.4} /></span><span className="s3-reset">Скинути</span></div>}
            </section>
            <div className="s3-fgrid" style={{ gridTemplateColumns: side ? 'repeat(3,minmax(0,1fr))' : 'repeat(4,minmax(0,1fr))' }}>
              {UB_PRODUCTS.concat(UB_PRODUCTS).slice(0, side ? 3 : 4).map((p, i) => <div key={i} className="ub-pc"><div className="ub-pc-img"><span>фото</span></div><a href="#" className="ub-pc-n">{p[0]}</a><b className="ub-pc-p">{p[1]}</b><span className="ub-buy">Купити</span></div>)}
            </div>
          </div>
          {side && <FilterAside v={v} />}
        </div>
      </div>
    </div>
  );
}
const FF_GROUPS = [['type', 'Тип товару', S3_TYPES], ['country', 'Країна', S3_COUNTRY]];
function SortIc() { return <svg className="ub-ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M7 4v16m0 0-3-3m3 3 3-3M17 20V4m0 0-3 3m3-3 3 3" /></svg>; }
function FilterFinal({ w, initOpen }) {
  const mob = w < 576, desk = w >= 1024;
  const [open, setOpen] = React.useState(initOpen);
  const [gs, setGs] = React.useState({ type: true, country: !mob });
  const sel = 1;
  return (
    <div className="ub-vp ff-vp" style={{ width: w }}>
      <Header w={w} cat="a" />
      <div className="ub-c" style={!desk ? { maxWidth: 'none', paddingLeft: mob ? 10 : 32, paddingRight: mob ? 10 : 32 } : null}>
        <Crumbs items={['Pokémon']} />
        <section className="ub-cathead ff-ch">
          <h1>Pokémon <small>48 товарів</small></h1>
          <div className="ff-row">
            <div className="ff-chips" role="list">{[['Бустери', 15], ['Бустер бокси', 13], ['Набори', 13], ['Фігурки та декор', 21]].map(([t, n]) => <a key={t} href="#" role="listitem" className="ff-chip">{t} <i>{n}</i></a>)}</div>
            <div className="ff-tools">
              <button type="button" className={'ff-fbtn' + (open ? ' is-on' : '')} aria-expanded={open} aria-controls="ff-panel" aria-label={mob ? 'Фільтр' : undefined} onClick={() => setOpen(!open)}>
                <U n="sliders" s={17} />{!mob && <span>Фільтр</span>}{sel > 0 && <span className="s3-cnt">{sel}</span>}{!mob && <U n="chevD" s={16} cls={open ? 'is-up' : ''} />}
              </button>
              {mob ? <label className="ff-sortic" aria-label="Сортування"><SortIc /></label> : <span className="ub-input ub-select ff-sort">За замовчуванням <U n="chevD" s={16} /></span>}
            </div>
          </div>
          {open && <div className="ff-panel" id="ff-panel">
            <div className="ff-groups">{FF_GROUPS.map(([k, t, items]) => {
              const gOpen = gs[k];
              const n = items.filter(x => x[1]).length;
              return (
                <div key={k} className={'ff-g' + (gOpen ? ' is-open' : '')}>
                  <button type="button" className="ff-gt" aria-expanded={gOpen} onClick={() => setGs({ ...gs, [k]: !gOpen })}>{t}{n > 0 && <span className="s3-cnt">{n}</span>}<U n="chevD" s={16} cls={gOpen ? 'is-up' : ''} /></button>
                  {gOpen && <Checks items={items} />}
                </div>
              );
            })}</div>
            <div className="ff-foot"><span className="s3-fl">Фільтр:</span><span className="s3-fchip">Бустер <U n="close" s={12} sw={2.4} /></span><span className="s3-reset">Скинути</span><button type="button" className="ff-close" onClick={() => setOpen(false)}>Згорнути <U n="chevD" s={14} cls="is-up" /></button></div>
          </div>}
          {!open && <div className="s3-chips-act"><span className="s3-fl">Фільтр:</span><span className="s3-fchip">Бустер <U n="close" s={12} sw={2.4} /></span><span className="s3-reset">Скинути</span></div>}
        </section>
        <div className="ub-grid">{UB_PRODUCTS.concat(UB_PRODUCTS).slice(0, desk ? 4 : 2).map((p, i) => <div key={i} className="ub-pc"><div className="ub-pc-img"><span>фото</span></div><a href="#" className="ub-pc-n">{p[0]}</a><b className="ub-pc-p">{p[1]}</b><span className="ub-buy">Купити</span></div>)}</div>
      </div>
    </div>
  );
}
Object.assign(window, { BrandPanel, GridDemo, FilterDemo, FilterFinal });
