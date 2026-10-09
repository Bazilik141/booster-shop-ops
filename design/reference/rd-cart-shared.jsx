// RD-11 / RD-12 / toast — shared primitives. DS tokens only (tokens.css == boostershop-ds.css :root).
const RDC = {
  threshold: 2000, // {{ shipping_pinta_nova_poshta_free_from }} — admin setting, NOT hardcoded copy
};
RDC.nearGap = () => Math.round(RDC.threshold * 0.3); // progress appears within 30% of the threshold, computed at render

const money = (n) => '₴' + String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

const Ic = {
  minus: (p) => <svg viewBox="0 0 24 24" width={p.s || 16} height={p.s || 16} fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M5 12h14"/></svg>,
  plus: (p) => <svg viewBox="0 0 24 24" width={p.s || 16} height={p.s || 16} fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>,
  x: (p) => <svg viewBox="0 0 24 24" width={p.s || 16} height={p.s || 16} fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>,
  check: (p) => <svg viewBox="0 0 24 24" width={p.s || 18} height={p.s || 18} fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12.4l2.6 2.6L16 9.6"/></svg>,
  warn: (p) => <svg viewBox="0 0 24 24" width={p.s || 18} height={p.s || 18} fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.2M12 16.3v.2"/></svg>,
  cart: (p) => <svg viewBox="0 0 24 24" width={p.s || 20} height={p.s || 20} fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6Z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0" strokeLinecap="round"/></svg>,
  truck: (p) => <svg viewBox="0 0 24 24" width={p.s || 18} height={p.s || 18} fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/></svg>,
  trash: (p) => <svg viewBox="0 0 24 24" width={p.s || 16} height={p.s || 16} fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg>,
  search: (p) => <svg viewBox="0 0 24 24" width={p.s || 18} height={p.s || 18} fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4 4"/></svg>,
  bag: (p) => <svg viewBox="0 0 24 24" width={p.s || 26} height={p.s || 26} fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"><path d="M6 7h12l-1 12H7L6 7Z"/><path d="M9 7a3 3 0 0 1 6 0" strokeLinecap="round"/><path d="M9.5 13h5" strokeLinecap="round"/></svg>,
};

function ImgPh({ w, h, label, r }) {
  return <div className="bs-img-ph" data-label={label || ''} style={{ width: w, height: h, flex: '0 0 auto', borderRadius: r || 'var(--bs-r-sm)', border: '1px solid var(--bs-line)' }}></div>;
}

// 44×44 tap targets (UX-026). Minus turns into a remove affordance at qty 1.
function Stepper({ qty, onChange, compact }) {
  const b = { width: 44, height: 44, border: 0, background: 'transparent', color: 'var(--bs-ink-2)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', borderRadius: 'inherit' };
  return (
    <div style={{ display: 'inline-flex', alignItems: 'center', border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r-sm)', background: '#fff', height: 44 }}>
      <button style={{ ...b, color: qty <= 1 ? 'var(--bs-ink-4)' : 'var(--bs-ink-2)' }} onClick={() => onChange(-1)} aria-label="Менше"><Ic.minus s={16}/></button>
      <span style={{ minWidth: compact ? 28 : 34, textAlign: 'center', fontSize: 14.5, fontWeight: 700, color: 'var(--bs-ink)' }}>{qty}</span>
      <button style={b} onClick={() => onChange(1)} aria-label="Більше"><Ic.plus s={16}/></button>
    </div>
  );
}

function LinkBtn({ children, onClick, danger }) {
  return <button onClick={onClick} style={{ background: 'transparent', border: 0, padding: 0, color: danger ? 'var(--bs-ink-3)' : 'var(--bs-blue)', fontSize: 13, fontWeight: 600, display: 'inline-flex', alignItems: 'center', gap: 6 }}>{children}</button>;
}

// Rule line always; progress bar only when the remainder is small (nearGap).
function ShippingInfo({ subtotal, dense }) {
  const left = RDC.threshold - subtotal;
  const free = left <= 0;
  const near = !free && left <= RDC.nearGap();
  return (
    <div style={{ border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r)', background: free ? 'var(--bs-green-soft)' : 'var(--bs-bg)', padding: dense ? '10px 12px' : '12px 14px', display: 'grid', gap: 8 }}>
      <div style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
        <span style={{ color: free ? 'var(--bs-green)' : 'var(--bs-ink-3)', marginTop: 1 }}><Ic.truck s={18}/></span>
        <div style={{ fontSize: 13, lineHeight: 1.45, color: 'var(--bs-ink-2)' }}>
          {free
            ? <span style={{ color: 'var(--bs-green-hover)', fontWeight: 700 }}>Доставка Новою поштою — безкоштовно</span>
            : <>Безкоштовна доставка Новою поштою від <strong style={{ color: 'var(--bs-ink)' }}>{money(RDC.threshold)}</strong></>}
        </div>
      </div>
      {near && (
        <div style={{ display: 'grid', gap: 6 }}>
          <div style={{ height: 6, borderRadius: 999, background: 'var(--bs-line)', overflow: 'hidden' }}>
            <div style={{ width: Math.round((subtotal / RDC.threshold) * 100) + '%', height: '100%', background: 'var(--bs-green)' }}></div>
          </div>
          <div style={{ fontSize: 12.5, color: 'var(--bs-ink-3)' }}>Ще {money(left)} — і доставка безкоштовна</div>
        </div>
      )}
    </div>
  );
}

function Notice({ tone, title, text }) {
  const map = {
    warn: { bg: 'var(--bs-warning-bg)', bd: 'var(--bs-warning-line)', fg: 'var(--bs-warning-fg)' },
    info: { bg: 'var(--bs-blue-soft)', bd: '#c7d2fe', fg: 'var(--bs-blue)' },
  }[tone || 'warn'];
  return (
    <div role="alert" style={{ display: 'flex', gap: 10, background: map.bg, border: '1px solid ' + map.bd, borderRadius: 'var(--bs-r)', padding: '12px 14px', color: map.fg }}>
      <span style={{ marginTop: 1 }}><Ic.warn s={18}/></span>
      <div style={{ fontSize: 13, lineHeight: 1.5 }}>{title && <strong style={{ display: 'block' }}>{title}</strong>}{text}</div>
    </div>
  );
}

// Full-width strip, sits directly under the header. Success = DS green, error = DS danger (never green).
function Toast({ toast, onClose, onOpenCart }) {
  if (!toast) return null;
  const ok = toast.type === 'success';
  return (
    <div role="status" aria-live="polite" style={{
      pointerEvents: 'auto', background: ok ? 'var(--bs-green)' : 'var(--bs-danger)', color: '#fff',
      boxShadow: 'var(--bs-sh-pop)', animation: 'rdSlideDown .22s ease-out',
    }}>
      <div style={{ maxWidth: 1180, margin: '0 auto', padding: '12px 16px', display: 'flex', alignItems: 'center', gap: 12 }}>
        <span style={{ flex: '0 0 auto', display: 'inline-flex' }}>{ok ? <Ic.check s={20}/> : <Ic.warn s={20}/>}</span>
        <div style={{ flex: '1 1 auto', fontSize: 14, fontWeight: 600, lineHeight: 1.35 }}>{toast.message}</div>
        {ok && onOpenCart && (
          <button onClick={onOpenCart} style={{ flex: '0 0 auto', background: 'rgba(255,255,255,.16)', border: '1px solid rgba(255,255,255,.55)', color: '#fff', fontSize: 13, fontWeight: 700, padding: '8px 12px', borderRadius: 'var(--bs-r-sm)' }}>Переглянути кошик</button>
        )}
        <button onClick={onClose} aria-label="Закрити" style={{ flex: '0 0 auto', width: 32, height: 32, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', background: 'transparent', border: 0, color: '#fff', opacity: .85 }}><Ic.x s={16}/></button>
      </div>
    </div>
  );
}

function EmptyState({ title, text, cta, onCta }) {
  return (
    <div style={{ maxWidth: 420, margin: '32px auto', textAlign: 'center', display: 'grid', justifyItems: 'center', padding: '24px 16px' }}>
      <div style={{ width: 48, height: 48, borderRadius: 999, background: 'var(--bs-line-2)', color: 'var(--bs-ink-3)', display: 'grid', placeItems: 'center' }}><Ic.bag s={26}/></div>
      <h3 style={{ margin: '14px 0 0', fontSize: 18, fontWeight: 700, color: 'var(--bs-ink)' }}>{title}</h3>
      <p style={{ margin: '8px 0 0', fontSize: 14, color: 'var(--bs-ink-3)', lineHeight: 1.55 }}>{text}</p>
      {cta && <button className="bs-btn bs-btn-primary" style={{ marginTop: 18, height: 44, padding: '0 20px' }} onClick={onCta}>{cta}</button>}
    </div>
  );
}

const CATALOG = [
  { id: 'mega', title: 'Бустер Pokémon TCG: Mega Symphonia (Японське видання)', price: 150, stock: true },
  { id: 'op11', title: 'Бустер One Piece Card Game OP-11 (Японське видання)', price: 165, stock: true },
  { id: 'ninja', title: 'Бустер Pokémon TCG: Ninja Spinner', price: 150, stock: true },
  { id: 'sleeves', title: 'Протектори Dragon Shield Matte 100 шт.', price: 340, stock: true },
  { id: 'box', title: 'Бустер-бокс Pokémon TCG: Prismatic Evolutions', price: 4200, stock: false },
  { id: 'binder', title: 'Альбом-біндер на 480 карт, чорний', price: 890, stock: true },
];

Object.assign(window, { RDC, money, Ic, ImgPh, Stepper, LinkBtn, ShippingInfo, Notice, Toast, EmptyState, CATALOG });
