// The two phone mockups demonstrating the redesign.

const { useState: vS, useRef: vR, useEffect: vE } = React;

/* ---------- phone shell ------------------------------------------------- */
function Phone({ children, label }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 14 }}>
      <div style={{
        width: 372, height: 760, background: '#fff',
        border: '9px solid #15181d', borderRadius: 42,
        boxShadow: '0 24px 60px rgba(17,24,39,0.22)',
        overflow: 'hidden', position: 'relative',
        display: 'flex', flexDirection: 'column',
        fontFamily: '"Manrope", system-ui, sans-serif',
      }}>
        <div style={{ flex: '0 0 30px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 24px', fontSize: 12.5, fontWeight: 700, color: 'var(--bs-ink)' }}>
          <span>9:41</span><span style={{ fontSize: 9, letterSpacing: 2 }}>● ● ●</span>
        </div>
        {children}
      </div>
      <div style={{ fontSize: 12.5, color: 'var(--bs-ink-3)', fontWeight: 600 }}>{label}</div>
    </div>
  );
}

/* faux page content behind the search, so behaviour reads in context */
function PageBehind() {
  return (
    <div style={{ padding: '14px 14px 30px' }}>
      <div style={{ display: 'flex', gap: 8, marginBottom: 14, overflowX: 'hidden' }}>
        {['Бустери', 'Бустер-бокси', 'Набори', 'Акції'].map(c => (
          <span key={c} className="bs-chip" style={{ padding: '7px 12px', fontSize: 12.5, whiteSpace: 'nowrap' }}>{c}</span>
        ))}
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
        {CATALOG.slice(0, 4).map(p => (
          <div key={p.id} className="bs-card" style={{ padding: 10 }}>
            <div className="bs-img-ph" style={{ aspectRatio: '1/1', borderRadius: 'var(--bs-r-sm)', marginBottom: 8 }} data-label="" />
            <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--bs-ink)', lineHeight: 1.35, height: 32, overflow: 'hidden' }}>{p.title}</div>
            <div style={{ fontSize: 13.5, fontWeight: 800, color: 'var(--bs-ink)', marginTop: 6 }}>₴{p.price}</div>
          </div>
        ))}
      </div>
    </div>
  );
}

function MiniLogo() {
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
      <span style={{ fontSize: 14, fontWeight: 900, letterSpacing: '-0.04em', color: 'var(--bs-pokemon)', lineHeight: 0.95, textTransform: 'uppercase' }}>Booster<br/>Shop</span>
      <span aria-hidden style={{ display: 'inline-block', width: 11, height: 17, background: 'var(--bs-blue)', clipPath: 'polygon(60% 0,100% 0,40% 50%,90% 50%,15% 100%,55% 55%,0 55%)' }} />
    </div>
  );
}
const iconBtn = {
  width: 38, height: 38, borderRadius: 'var(--bs-r-sm)', background: 'var(--bs-bg)',
  border: 0, color: 'var(--bs-ink-2)', cursor: 'pointer',
  display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 auto',
};

/* ======================================================================= *
   NAV DRAWER — slides from the left when the burger is tapped.
   Catalog (with expandable TCG lines) · Info · Account.
 * ======================================================================= */
// Data-driven — майбутні категорії (Аксесуари, Інші TCG…) вставляються
// одним рядком МІЖ One Piece та Акціями. Акції завжди лишаються останніми.
const CATALOG_NAV = [
  { label: 'Pokémon TCG', accent: 'var(--bs-pokemon)', subs: ['Бустери', 'Бустер-бокси', 'Набори'] },
  { label: 'One Piece Card Game', accent: 'var(--bs-onepiece)', subs: ['Бустери', 'Набори', 'Mystery Box'] },
  { label: 'Акції', accent: 'var(--bs-danger)', sale: true },
];
const INFO_NAV = [
  { label: 'Доставка та оплата', icon: 'Truck' },
  { label: 'Гарантія оригіналу', icon: 'Shield' },
  { label: 'Про магазин', icon: 'Bag' },
];

/* horizontal brand lockup for the drawer — fills the header row, no dead gap */
function DrawerBrand() {
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 7 }}>
      <span style={{ fontSize: 18, fontWeight: 900, letterSpacing: '-0.035em', color: 'var(--bs-pokemon)', textTransform: 'uppercase', lineHeight: 1 }}>Booster&nbsp;Shop</span>
      <span aria-hidden style={{ display: 'inline-block', width: 12, height: 18, background: 'var(--bs-blue)', clipPath: 'polygon(60% 0,100% 0,40% 50%,90% 50%,15% 100%,55% 55%,0 55%)' }} />
    </div>
  );
}

/* Shared menu body — account header + catalog + info + footer.
   Used by BOTH the mobile drawer and the web drawer so behaviour/content
   stay in lockstep. Parent <aside> controls size/position/animation. */
function MenuPanel({ onClose, auth = false }) {
  const [exp, setExp] = vS(null);
  return (
    <>
      {/* account header — guest: single login CTA · authorized: Account + orders */}
      <div style={{ padding: '14px 14px 14px', borderBottom: '1px solid var(--bs-line)', background: 'var(--bs-bg)', flex: '0 0 auto' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', height: 38 }}>
          <DrawerBrand />
          <button onClick={onClose} aria-label="Закрити" style={{ ...iconBtn, width: 34, height: 34, background: '#fff', border: '1px solid var(--bs-line)' }}><Ic.Close width="13" height="13" /></button>
        </div>
        {!auth ? (
          <button style={{
            marginTop: 12, width: '100%', height: 46, display: 'flex', alignItems: 'center', gap: 11,
            padding: '0 14px', background: '#fff', border: '1px solid var(--bs-line)',
            borderRadius: 'var(--bs-r-sm)', cursor: 'pointer', fontFamily: 'inherit',
          }}>
            <span style={{ width: 28, height: 28, borderRadius: '50%', background: 'var(--bs-blue-soft)', color: 'var(--bs-blue)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 auto' }}><Ic.User width="16" height="16" /></span>
            <span style={{ flex: 1, textAlign: 'left', fontSize: 14, fontWeight: 700, color: 'var(--bs-ink)' }}>Увійти або зареєструватися</span>
            <Ic.Chevron width="13" height="13" style={{ color: 'var(--bs-ink-4)', flex: '0 0 auto' }} />
          </button>
        ) : (
          <div style={{ marginTop: 12, display: 'flex', flexDirection: 'column', gap: 6 }}>
            <button style={{
              width: '100%', height: 46, display: 'flex', alignItems: 'center', gap: 11,
              padding: '0 14px', background: '#fff', border: '1px solid var(--bs-line)',
              borderRadius: 'var(--bs-r-sm)', cursor: 'pointer', fontFamily: 'inherit',
            }}>
              <span style={{ width: 28, height: 28, borderRadius: '50%', background: 'var(--bs-blue-soft)', color: 'var(--bs-blue)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 auto' }}><Ic.User width="16" height="16" /></span>
              <span style={{ flex: 1, textAlign: 'left', fontSize: 14, fontWeight: 700, color: 'var(--bs-ink)' }}>Акаунт</span>
              <Ic.Chevron width="13" height="13" style={{ color: 'var(--bs-ink-4)', flex: '0 0 auto' }} />
            </button>
            <a href="#" style={{ display: 'flex', alignItems: 'center', gap: 11, height: 40, padding: '0 14px', textDecoration: 'none', borderRadius: 'var(--bs-r-sm)' }}>
              <span style={{ width: 28, display: 'inline-flex', justifyContent: 'center', flex: '0 0 auto', color: 'var(--bs-ink-3)' }}><Ic.Orders width="16" height="16" /></span>
              <span style={{ flex: 1, fontSize: 13.5, fontWeight: 600, color: 'var(--bs-ink-2)' }}>Мої замовлення</span>
              <Ic.Chevron width="12" height="12" style={{ color: 'var(--bs-ink-4)', flex: '0 0 auto' }} />
            </a>
          </div>
        )}
      </div>

      <div style={{ flex: 1, overflowY: 'auto' }}>
        {/* catalog */}
        <SectionLabel>Каталог</SectionLabel>
        {CATALOG_NAV.map((c) => (
          <div key={c.label}>
            <button onClick={() => c.subs && setExp(exp === c.label ? null : c.label)} style={{
              display: 'flex', alignItems: 'center', gap: 12, width: '100%', height: 48,
              padding: '0 18px', background: 'transparent', border: 0, cursor: 'pointer',
              textAlign: 'left', fontFamily: 'inherit',
            }}>
              <span style={{ width: 9, height: 9, borderRadius: 3, background: c.accent, flex: '0 0 auto' }} />
              <span style={{ flex: 1, fontSize: 14.5, fontWeight: c.sale ? 800 : 600, color: c.sale ? 'var(--bs-danger)' : 'var(--bs-ink)' }}>{c.label}</span>
              {c.subs && <Ic.Chevron width="13" height="13" style={{ color: 'var(--bs-ink-4)', transform: exp === c.label ? 'rotate(90deg)' : 'none', transition: 'transform .2s' }} />}
              {!c.subs && <Ic.Chevron width="13" height="13" style={{ color: 'var(--bs-ink-4)' }} />}
            </button>
            {c.subs && exp === c.label && (
              <div style={{ paddingBottom: 6 }}>
                {c.subs.map(s => (
                  <a key={s} href="#" style={{ display: 'block', padding: '9px 18px 9px 39px', fontSize: 13.5, color: 'var(--bs-ink-2)', textDecoration: 'none' }}>{s}</a>
                ))}
              </div>
            )}
          </div>
        ))}

        {/* info */}
        <SectionLabel style={{ marginTop: 6, borderTop: '1px solid var(--bs-line-2)', paddingTop: 16 }}>Інформація</SectionLabel>
        {INFO_NAV.map((it) => {
          const Icon = Ic[it.icon];
          return (
            <a key={it.label} href="#" style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '0 18px', height: 46, textDecoration: 'none' }}>
              <Icon width="17" height="17" style={{ color: 'var(--bs-ink-3)', flex: '0 0 auto' }} />
              <span style={{ flex: 1, fontSize: 14, fontWeight: 500, color: 'var(--bs-ink-2)' }}>{it.label}</span>
            </a>
          );
        })}
      </div>

      {/* footer — telegram CTA */}
      <div style={{ padding: 14, borderTop: '1px solid var(--bs-line)', flex: '0 0 auto' }}>
        <a href="#" style={{
          display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, height: 44,
          background: 'var(--bs-blue-soft)', color: 'var(--bs-blue)', borderRadius: 'var(--bs-r-sm)',
          fontSize: 13.5, fontWeight: 700, textDecoration: 'none',
        }}>
          <Ic.Tg width="17" height="17" /> Наш Telegram-канал
        </a>
      </div>
    </>
  );
}

function NavDrawer({ open, onClose }) {
  return (
    <>
      <div onClick={onClose} style={{
        position: 'absolute', inset: 0, background: 'rgba(17,24,39,0.40)', zIndex: 45,
        opacity: open ? 1 : 0, pointerEvents: open ? 'auto' : 'none', transition: 'opacity .22s',
      }} />
      <aside style={{
        position: 'absolute', top: 0, bottom: 0, left: 0, width: '86%', maxWidth: 320, zIndex: 46,
        background: '#fff', display: 'flex', flexDirection: 'column',
        boxShadow: '8px 0 40px rgba(17,24,39,0.18)',
        transform: open ? 'translateX(0)' : 'translateX(-104%)',
        transition: 'transform .28s cubic-bezier(.32,.72,0,1)',
      }}>
        <MenuPanel onClose={onClose} />
      </aside>
    </>
  );
}

/* ======================================================================= *
   VARIANT A — Inline-expand search bar
   The header carries a real full-width search field (big tap target, ~44px).
   Tapping it does NOT navigate — it focuses in place and a results panel
   drops under the header with a dimming scrim. Closest to current layout.
 * ======================================================================= */
function VariantA() {
  const [open, setOpen] = vS(false);
  const [menu, setMenu] = vS(false);
  const [q, setQ] = vS('');
  const inp = vR(null);

  vE(() => { if (open && inp.current) inp.current.focus(); }, [open]);

  const close = () => { setOpen(false); setQ(''); };

  return (
    <Phone label="A · Inline-expand — поле розкривається на місці">
      <div style={{ flex: 1, position: 'relative', overflow: 'hidden', background: 'var(--bs-bg)' }}>
        {/* header */}
        <header style={{
          background: '#fff', borderBottom: '1px solid var(--bs-line)',
          padding: '10px 12px', display: 'flex', alignItems: 'center', gap: 10,
          position: 'relative', zIndex: 30,
        }}>
          {!open && <button style={iconBtn} onClick={() => setMenu(true)}><Ic.Menu width="18" height="18" /></button>}
          {!open && <MiniLogo />}
          {/* the search field — flexes to fill the row */}
          <div style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 8 }}>
            {open && (
              <button style={{ ...iconBtn, background: 'transparent', width: 30 }} onClick={close}><Ic.Back width="18" height="18" /></button>
            )}
            <div onClick={() => !open && setOpen(true)} style={{
              flex: 1, display: 'flex', alignItems: 'center', gap: 8, height: 42,
              background: open ? '#fff' : 'var(--bs-bg)',
              border: `1.5px solid ${open ? 'var(--bs-blue)' : 'var(--bs-line)'}`,
              boxShadow: open ? '0 0 0 3px rgba(30,58,138,0.10)' : 'none',
              borderRadius: 'var(--bs-r-sm)', padding: '0 12px', cursor: 'text',
              transition: 'border-color .15s, box-shadow .15s, background .15s',
            }}>
              <Ic.Search width="17" height="17" style={{ color: open ? 'var(--bs-blue)' : 'var(--bs-ink-3)', flex: '0 0 auto' }} />
              <input ref={inp} value={q} onChange={e => setQ(e.target.value)} placeholder="Пошук бустерів, сетів…"
                readOnly={!open}
                style={{ flex: 1, border: 0, background: 'transparent', outline: 'none', fontSize: 14.5, color: 'var(--bs-ink)', fontFamily: 'inherit', width: '100%', minWidth: 0, cursor: open ? 'text' : 'pointer' }} />
              {open && q && <button onMouseDown={e => e.preventDefault()} onClick={() => { setQ(''); inp.current && inp.current.focus(); }} style={{ ...iconBtn, width: 24, height: 24, background: 'var(--bs-line-2)', borderRadius: '50%' }}><Ic.Close width="11" height="11" /></button>}
            </div>
          </div>
          {!open && <button style={{ ...iconBtn, background: 'var(--bs-green)', color: '#fff', width: 'auto', padding: '0 11px', gap: 5 }}><Ic.Cart width="16" height="16" /><span style={{ fontSize: 12.5, fontWeight: 800 }}>2</span></button>}
        </header>

        {/* page content */}
        <div style={{ position: 'absolute', inset: '63px 0 0', overflowY: 'auto' }}>
          <PageBehind />
        </div>

        {/* scrim */}
        <div onClick={close} style={{
          position: 'absolute', inset: '63px 0 0', background: 'rgba(17,24,39,0.32)',
          opacity: open ? 1 : 0, pointerEvents: open ? 'auto' : 'none',
          transition: 'opacity .2s', zIndex: 20,
        }} />

        {/* results panel drops under header */}
        <div style={{
          position: 'absolute', top: 63, left: 0, right: 0, zIndex: 25,
          background: '#fff', borderBottom: '1px solid var(--bs-line)',
          boxShadow: 'var(--bs-sh-pop)', maxHeight: 'calc(100% - 63px)', overflowY: 'auto',
          transform: open ? 'translateY(0)' : 'translateY(-8px)',
          opacity: open ? 1 : 0, pointerEvents: open ? 'auto' : 'none',
          transition: 'opacity .18s, transform .18s',
        }}>
          <SearchResults q={q} onPick={() => {}} onTerm={(t) => setQ(t)} />
        </div>

        {/* nav drawer */}
        <NavDrawer open={menu} onClose={() => setMenu(false)} />
      </div>
    </Phone>
  );
}

/* ======================================================================= *
   VARIANT B — Full-screen search sheet
   Header stays compact (logo + icon). Tapping the search icon slides a full
   sheet OVER the page with a large input + Cancel, auto-focused, full-height
   results. Best when the header row is crowded.
 * ======================================================================= */
function VariantB() {
  const [open, setOpen] = vS(false);
  const [q, setQ] = vS('');
  const inp = vR(null);
  vE(() => { if (open && inp.current) setTimeout(() => inp.current && inp.current.focus(), 60); }, [open]);
  const close = () => { setOpen(false); setQ(''); };

  return (
    <Phone label="B · Full-screen sheet — пошук на весь екран">
      <div style={{ flex: 1, position: 'relative', overflow: 'hidden', background: 'var(--bs-bg)' }}>
        {/* compact header */}
        <header style={{
          background: '#fff', borderBottom: '1px solid var(--bs-line)',
          padding: '10px 12px', display: 'flex', alignItems: 'center', gap: 10,
        }}>
          <button style={iconBtn}><Ic.Menu width="18" height="18" /></button>
          <MiniLogo />
          <span style={{ flex: 1 }} />
          <button style={iconBtn} onClick={() => setOpen(true)}><Ic.Search width="18" height="18" /></button>
          <button style={{ ...iconBtn, background: 'var(--bs-green)', color: '#fff', width: 'auto', padding: '0 11px', gap: 5 }}><Ic.Cart width="16" height="16" /><span style={{ fontSize: 12.5, fontWeight: 800 }}>2</span></button>
        </header>

        <div style={{ position: 'absolute', inset: '63px 0 0', overflowY: 'auto' }}><PageBehind /></div>

        {/* full sheet */}
        <div style={{
          position: 'absolute', inset: 0, background: '#fff', zIndex: 40,
          display: 'flex', flexDirection: 'column',
          transform: open ? 'translateY(0)' : 'translateY(100%)',
          transition: 'transform .26s cubic-bezier(.32,.72,0,1)',
        }}>
          <div style={{ flex: '0 0 30px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 24px', fontSize: 12.5, fontWeight: 700, color: 'var(--bs-ink)' }}>
            <span>9:41</span><span style={{ fontSize: 9, letterSpacing: 2 }}>● ● ●</span>
          </div>
          <div style={{ padding: '8px 12px 12px', display: 'flex', alignItems: 'center', gap: 10, borderBottom: '1px solid var(--bs-line)' }}>
            <div style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 8, height: 42, background: 'var(--bs-bg)', border: '1.5px solid var(--bs-line)', borderRadius: 'var(--bs-r-sm)', padding: '0 12px' }}>
              <Ic.Search width="17" height="17" style={{ color: 'var(--bs-ink-3)', flex: '0 0 auto' }} />
              <input ref={inp} value={q} onChange={e => setQ(e.target.value)} placeholder="Пошук бустерів, сетів…"
                style={{ flex: 1, border: 0, background: 'transparent', outline: 'none', fontSize: 15, color: 'var(--bs-ink)', fontFamily: 'inherit', width: '100%', minWidth: 0 }} />
              {q && <button onMouseDown={e => e.preventDefault()} onClick={() => { setQ(''); inp.current && inp.current.focus(); }} style={{ ...iconBtn, width: 24, height: 24, background: 'var(--bs-line-2)', borderRadius: '50%' }}><Ic.Close width="11" height="11" /></button>}
            </div>
            <button onClick={close} style={{ background: 'transparent', border: 0, color: 'var(--bs-blue)', fontSize: 14.5, fontWeight: 700, cursor: 'pointer', padding: '0 2px', fontFamily: 'inherit' }}>Скасувати</button>
          </div>
          <div style={{ flex: 1, overflowY: 'auto' }}>
            <SearchResults q={q} onPick={() => {}} onTerm={(t) => setQ(t)} />
          </div>
        </div>
      </div>
    </Phone>
  );
}

Object.assign(window, { Phone, VariantA, VariantB });
