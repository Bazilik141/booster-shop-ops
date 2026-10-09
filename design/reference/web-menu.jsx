// Web header demo — burger-menu left of the logo, opening the SAME menu
// (MenuPanel) as mobile, as a left slide-in panel with scrim. Esc / scrim /
// X all close. Shown inside a faux desktop browser frame.

const { useState: wS, useEffect: wE } = React;

function WebBurger({ onClick }) {
  return (
    <button onClick={onClick} aria-label="Меню" className="web-burger">
      <Ic.Menu width="20" height="20" />
    </button>
  );
}

/* secondary ghost-link (login/account, telegram) — brand blue, no fill */
function WebGhost({ icon, children, onClick }) {
  const Icon = Ic[icon];
  return (
    <button className="web-ghost" onClick={onClick}>
      <Icon width="16" height="16" />{children}
    </button>
  );
}

/* The web slide-in drawer — fixed to the browser frame (absolute within it),
   ~380px, scrim behind. Reuses MenuPanel verbatim. */
function WebMenuDrawer({ open, onClose, auth }) {
  wE(() => {
    if (!open) return;
    const h = (e) => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', h);
    return () => window.removeEventListener('keydown', h);
  }, [open, onClose]);

  return (
    <>
      <div onClick={onClose} style={{
        position: 'absolute', inset: 0, background: 'rgba(17,24,39,0.42)', zIndex: 80,
        opacity: open ? 1 : 0, pointerEvents: open ? 'auto' : 'none', transition: 'opacity .22s',
      }} />
      <aside style={{
        position: 'absolute', top: 0, bottom: 0, left: 0, width: 380, zIndex: 81,
        background: '#fff', display: 'flex', flexDirection: 'column',
        boxShadow: '10px 0 50px rgba(17,24,39,0.20)',
        transform: open ? 'translateX(0)' : 'translateX(-104%)',
        transition: 'transform .3s cubic-bezier(.32,.72,0,1)',
      }}>
        <MenuPanel onClose={onClose} auth={auth} />
      </aside>
    </>
  );
}

/* faux storefront body behind the header so the overlay reads in context */
function WebBody() {
  return (
    <div style={{ padding: '26px 32px 40px', maxWidth: 1240, margin: '0 auto' }}>
      <div style={{ display: 'flex', gap: 10, marginBottom: 22 }}>
        {['Усі товари', 'Новинки', 'Pokémon TCG', 'One Piece', 'Бустер-бокси'].map((c, i) => (
          <span key={c} className="bs-chip" style={{ padding: '8px 14px', fontSize: 13, background: i === 0 ? 'var(--bs-ink)' : undefined, color: i === 0 ? '#fff' : undefined, borderColor: i === 0 ? 'var(--bs-ink)' : undefined }}>{c}</span>
        ))}
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 18 }}>
        {CATALOG.slice(0, 8).map(p => (
          <div key={p.id} className="bs-card" style={{ padding: 14 }}>
            <div className="bs-img-ph" style={{ aspectRatio: '1/1', borderRadius: 'var(--bs-r-sm)', marginBottom: 12 }} />
            <div style={{ fontSize: 13.5, fontWeight: 600, color: 'var(--bs-ink)', lineHeight: 1.4, height: 38, overflow: 'hidden' }}>{p.title}</div>
            <div style={{ fontSize: 15, fontWeight: 800, color: 'var(--bs-ink)', marginTop: 8 }}>₴{p.price}</div>
          </div>
        ))}
      </div>
    </div>
  );
}

function WebHeaderDemo() {
  const [menu, setMenu] = wS(false);
  const [auth, setAuth] = wS(false);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 16, width: '100%' }}>
      {/* guest / authorized state toggle (demo control, not part of the header) */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
        <span style={{ fontSize: 12, color: 'var(--bs-ink-3)', fontWeight: 600 }}>Стан користувача:</span>
        <div className="seg">
          <button className={!auth ? 'on' : ''} onClick={() => setAuth(false)}>Гість</button>
          <button className={auth ? 'on' : ''} onClick={() => setAuth(true)}>Авторизований</button>
        </div>
      </div>

      {/* browser frame */}
      <div style={{
        width: '100%', maxWidth: 1080, borderRadius: 14, overflow: 'hidden',
        border: '1px solid var(--bs-line)', background: '#fff',
        boxShadow: '0 30px 70px rgba(17,24,39,0.16)',
      }}>
        {/* chrome */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '11px 16px', background: '#EDEDEA', borderBottom: '1px solid var(--bs-line)' }}>
          <span style={{ width: 11, height: 11, borderRadius: '50%', background: '#E5564B' }} />
          <span style={{ width: 11, height: 11, borderRadius: '50%', background: '#E6A93A' }} />
          <span style={{ width: 11, height: 11, borderRadius: '50%', background: '#54B65A' }} />
          <div style={{ flex: 1, margin: '0 12px', height: 26, borderRadius: 7, background: '#fff', border: '1px solid var(--bs-line)', display: 'flex', alignItems: 'center', padding: '0 12px', fontSize: 12, color: 'var(--bs-ink-3)' }}>boostershop.website</div>
        </div>

        {/* the actual page — position:relative so the drawer overlays only it */}
        <div style={{ position: 'relative', height: 540, overflow: 'hidden', background: '#fff' }}>
          <div style={{ position: 'absolute', inset: 0, overflowY: 'auto' }}>
            {/* HEADER — main row + category nav (utility strip removed) */}
            <header style={{ background: '#fff', borderBottom: '1px solid var(--bs-line)', position: 'sticky', top: 0, zIndex: 10 }}>
              {/* main row: BURGER · logo · search · login/account · telegram · cart */}
              <div style={{ padding: '14px 32px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 16, maxWidth: 1240, margin: '0 auto' }}>
                  <WebBurger onClick={() => setMenu(true)} />
                  <div style={{ display: 'flex', alignItems: 'center', gap: 7, flex: '0 0 auto' }}>
                    <span style={{ fontSize: 19, fontWeight: 900, letterSpacing: '-0.04em', color: 'var(--bs-pokemon)', textTransform: 'uppercase', lineHeight: 0.95 }}>Booster<br/>Shop</span>
                    <span aria-hidden style={{ display: 'inline-block', width: 13, height: 20, background: 'var(--bs-blue)', clipPath: 'polygon(60% 0,100% 0,40% 50%,90% 50%,15% 100%,55% 55%,0 55%)' }} />
                  </div>
                  <div style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 10, background: 'var(--bs-bg)', border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r-sm)', padding: '10px 14px' }}>
                    <Ic.Search width="16" height="16" style={{ color: 'var(--bs-ink-3)', flex: '0 0 auto' }} />
                    <input placeholder="Пошук бустерів, сетів, виробників…" style={{ flex: 1, border: 0, background: 'transparent', outline: 'none', fontSize: 14, color: 'var(--bs-ink)', fontFamily: 'inherit', width: '100%' }} />
                  </div>
                  {/* secondary ghost-links */}
                  <WebGhost icon="User">{auth ? 'Акаунт' : 'Увійти'}</WebGhost>
                  <WebGhost icon="Tg">Telegram</WebGhost>
                  {/* the ONLY green CTA */}
                  <button style={{
                    display: 'inline-flex', alignItems: 'center', gap: 8, flex: '0 0 auto',
                    padding: '10px 16px', borderRadius: 'var(--bs-r-sm)', border: 0, cursor: 'pointer',
                    background: 'var(--bs-green)', color: '#fff', fontFamily: 'inherit',
                  }}
                    onMouseEnter={e => e.currentTarget.style.background = 'var(--bs-green-hover)'}
                    onMouseLeave={e => e.currentTarget.style.background = 'var(--bs-green)'}>
                    <Ic.Cart width="16" height="16" /><span style={{ fontSize: 13.5, fontWeight: 700 }}>Кошик</span>
                    <span style={{ fontSize: 12.5, fontWeight: 500, opacity: 0.9 }}>· ₴700</span>
                  </button>
                </div>
              </div>
              {/* category nav — kept */}
              <div style={{ borderTop: '1px solid var(--bs-line-2)', padding: '10px 32px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 22, maxWidth: 1240, margin: '0 auto', fontSize: 13.5, fontWeight: 600, color: 'var(--bs-ink-2)' }}>
                  <a href="#" style={{ color: 'var(--bs-ink)', position: 'relative' }}>Pokémon<span style={{ position: 'absolute', left: 0, right: 0, bottom: -11, height: 2, background: 'var(--bs-pokemon)' }} /></a>
                  <a href="#">One Piece</a>
                  <a href="#" style={{ color: 'var(--bs-danger)' }}>Акції</a>
                  <a href="#" style={{ marginLeft: 'auto', color: 'var(--bs-ink-3)', fontWeight: 500 }}>Доставка</a>
                  <a href="#" style={{ color: 'var(--bs-ink-3)', fontWeight: 500 }}>Контакти</a>
                </div>
              </div>
            </header>

            <WebBody />
          </div>

          {/* drawer overlays the page region only */}
          <WebMenuDrawer open={menu} onClose={() => setMenu(false)} auth={auth} />
        </div>
      </div>
      <div style={{ fontSize: 12.5, color: 'var(--bs-ink-3)', fontWeight: 600, textAlign: 'center', maxWidth: 720 }}>Бургер зліва від лого відкриває те саме меню панеллю зліва. «Увійти/Акаунт» та «Telegram» — вторинні ghost-лінки (сині), кошик — єдина зелена CTA. Перемкніть стан угорі, щоб побачити меню гостя vs авторизованого.</div>
    </div>
  );
}

Object.assign(window, { WebHeaderDemo, WebMenuDrawer, WebBurger });
