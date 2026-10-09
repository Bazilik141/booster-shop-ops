// Shared UI primitives used across every mockup in the audit canvas.
// One file = one place to enforce design system. Components attach to window
// so other Babel script blocks can use them without import boilerplate.

const { useState: usR, useRef: urR, useEffect: ueR } = React;

// ---------- Icons (inline SVG, currentColor) ---------------------------------
const I = {
  Search: (p) => (<svg {...p} viewBox="0 0 20 20" fill="none"><circle cx="9" cy="9" r="6" stroke="currentColor" strokeWidth="1.6"/><path d="M14 14l4 4" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"/></svg>),
  Cart: (p) => (<svg {...p} viewBox="0 0 20 20" fill="none"><path d="M3 4h2l2 10h9l2-7H6" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" strokeLinecap="round"/><circle cx="8" cy="17" r="1.2" fill="currentColor"/><circle cx="15" cy="17" r="1.2" fill="currentColor"/></svg>),
  User: (p) => (<svg {...p} viewBox="0 0 20 20" fill="none"><circle cx="10" cy="7" r="3.2" stroke="currentColor" strokeWidth="1.6"/><path d="M3.5 17c.7-3.3 3.4-5 6.5-5s5.8 1.7 6.5 5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"/></svg>),
  Tg: (p) => (<svg {...p} viewBox="0 0 20 20" fill="none"><path d="M3 9.5L17 4l-2 13-4-2-2 3-1-4 8-7-9 5-4-1.5z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/></svg>),
  Chevron: (p) => (<svg {...p} viewBox="0 0 14 14" fill="none"><path d="M3 5l4 4 4-4" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  Plus: (p) => (<svg {...p} viewBox="0 0 12 12" fill="none"><path d="M6 1.5v9M1.5 6h9" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round"/></svg>),
  Minus: (p) => (<svg {...p} viewBox="0 0 12 12" fill="none"><path d="M1.5 6h9" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round"/></svg>),
  Close: (p) => (<svg {...p} viewBox="0 0 14 14" fill="none"><path d="M3 3l8 8M11 3l-8 8" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round"/></svg>),
  Home: (p) => (<svg {...p} viewBox="0 0 16 16" fill="currentColor"><path d="M8 2l6 5v7h-4v-4H6v4H2V7l6-5z"/></svg>),
  Check: (p) => (<svg {...p} viewBox="0 0 14 14" fill="none"><path d="M3 7l3 3 5-6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  Shield: (p) => (<svg {...p} viewBox="0 0 16 16" fill="none"><path d="M8 1.5l5.5 2v4c0 4-2.4 6.4-5.5 7-3.1-.6-5.5-3-5.5-7v-4L8 1.5z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/><path d="M5.5 7.7l2 2 3-3.4" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  Truck: (p) => (<svg {...p} viewBox="0 0 18 16" fill="none"><path d="M1 3h10v8H1zM11 6h4l2 3v2h-6z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/><circle cx="5" cy="13" r="1.5" stroke="currentColor" strokeWidth="1.4"/><circle cx="13" cy="13" r="1.5" stroke="currentColor" strokeWidth="1.4"/></svg>),
  Pack: (p) => (<svg {...p} viewBox="0 0 16 16" fill="none"><path d="M2 5l6-3 6 3v6l-6 3-6-3V5z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/><path d="M2 5l6 3 6-3M8 8v6" stroke="currentColor" strokeWidth="1.4"/></svg>),
  Filter: (p) => (<svg {...p} viewBox="0 0 16 16" fill="none"><path d="M2 4h12M4 8h8M6 12h4" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round"/></svg>),
  Star: (p) => (<svg {...p} viewBox="0 0 14 14" fill="currentColor"><path d="M7 1.5l1.7 3.6L12.5 6 9.7 8.5l.8 4L7 10.5 3.5 12.5l.8-4L1.5 6l3.8-.9L7 1.5z"/></svg>),
  Zap: (p) => (<svg {...p} viewBox="0 0 16 16" fill="none"><path d="M9 1L3 9h4l-1 6 6-8H8l1-6z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round" strokeLinecap="round"/></svg>),
};

// ---------- Image placeholder ------------------------------------------------
function ImagePh({ label = 'Фото товару', ratio = '1/1', radius, style, fallback }) {
  return (
    <div
      className="bs-img-ph"
      data-label={fallback ? '' : label}
      style={{
        aspectRatio: ratio,
        borderRadius: radius ?? 'var(--bs-r)',
        width: '100%',
        ...style,
      }}
    >
      {fallback}
    </div>
  );
}

// ---------- Sample product data ---------------------------------------------
const PRODUCTS = [
  { id: 'mega-symphonia', title: 'Бустер Pokémon TCG: Mega Symphonia (Японське видання)', price: 150, brand: 'pokemon', state: 'sealed' },
  { id: 'op-eb03',        title: 'Бустер One Piece Card Game EB-03 (Японське видання)',  price: 180, brand: 'onepiece', state: 'sealed' },
  { id: 'op-op11',        title: 'Бустер One Piece Card Game OP-11 (Японське видання)',  price: 165, brand: 'onepiece', state: 'sealed' },
  { id: 'ninja-spinner',  title: 'Бустер Pokémon TCG: Ninja Spinner (Японське видання)', price: 150, brand: 'pokemon', state: 'sealed' },
  { id: 'mega-brave',     title: 'Бустер Pokémon TCG: Mega Brave (Японське видання)',    price: 150, brand: 'pokemon', state: 'sealed' },
  { id: 'mix-low',        title: 'Бустер Pokémon TCG: MIX set low pull',                  price: 80,  oldPrice: 150, brand: 'pokemon', state: 'low-pull' },
  { id: 'op-heroines',    title: 'Бустер One Piece Card Game: Heroines Edition',          price: 220, brand: 'onepiece', state: 'sealed' },
  { id: 'op-mystery',     title: 'One Piece Mystery Box (Японське видання)',              price: 450, brand: 'onepiece', state: 'preorder' },
  { id: 'pkm-out',        title: 'Бустер Pokémon TCG: Wild Force (Японське видання)',     price: 170, brand: 'pokemon', state: 'out' },
  { id: 'sale-default',   title: 'Бустер Pokémon TCG: Mega Evolution (Розпродаж)',        price: 110, oldPrice: 150, brand: 'pokemon', state: 'sealed' },
];

// ---------- Product card (3 variants) ---------------------------------------
// State pill — only badges that actually flag something the buyer cares about.
// Sealed/unweighed are the DEFAULT for the catalog, so we no longer pill them
// (per feedback: "sealed це основа асортименту").
function StatePill({ state }) {
  const map = {
    'low-pull': { label: 'Low Pull', tone: { background: 'var(--bs-warning-bg)', color: 'var(--bs-warning-fg)', border: '1px solid var(--bs-warning-line)' }},
    'out':      { label: 'Немає в наявності', tone: { background: 'var(--bs-line-2)', color: 'var(--bs-ink-3)', border: '1px solid var(--bs-line)' }},
    'preorder': { label: 'Передзамовлення', tone: { background: 'var(--bs-blue-soft)', color: 'var(--bs-blue)', border: '1px solid #c7d2fe' }},
  }[state];
  if (!map) return null;
  return <span className="bs-badge" style={map.tone}>{map.label}</span>;
}

function DiscountPill({ percent }) {
  return (
    <span className="bs-badge bs-badge-discount" style={{ background: 'var(--bs-ink)', color: '#fff' }}>
      −{percent}%
    </span>
  );
}

// Price row — sale state amplifies new price (red, slightly larger), keeps old
// price quieter (smaller grey strikethrough) so it never competes. Plain price
// stays in ink (no red unless oldPrice present).
function PriceRow({ price, oldPrice, size = 'md' }) {
  const styles = {
    md: { p: 16, op: 12.5 },
    lg: { p: 24, op: 14 },
  }[size];
  const isSale = !!oldPrice;
  return (
    <div style={{ display: 'flex', alignItems: 'baseline', gap: 10 }}>
      <span style={{
        fontSize: isSale ? styles.p + 1 : styles.p,
        fontWeight: 800,
        color: isSale ? 'var(--bs-danger)' : 'var(--bs-ink)',
        letterSpacing: '-0.01em',
      }}>
        ₴{price}
      </span>
      {isSale && (
        <span style={{
          fontSize: styles.op, color: 'var(--bs-ink-4)',
          textDecoration: 'line-through', fontWeight: 500,
        }}>
          ₴{oldPrice}
        </span>
      )}
    </div>
  );
}

// Single product card — handles every state derived from product.state.
// State drives badges and CTA, not a `variant` switch. Sealed/unweighed are
// never pilled (they're the catalog default). Discount badge appears whenever
// oldPrice exists. Out-of-stock dims the image and swaps the CTA to a quiet
// outline button. Preorder keeps a green CTA but relabels it.
function ProductCard({ product, onAdd, compact }) {
  const { title, price, oldPrice, brand, state } = product;
  const discount = oldPrice ? Math.round((1 - price / oldPrice) * 100) : 0;
  const isOut = state === 'out';
  const isPreorder = state === 'preorder';

  let cta;
  if (isOut) {
    cta = (
      <button className="bs-btn" style={{
        width: '100%',
        background: '#fff', color: 'var(--bs-ink)',
        border: '1px solid var(--bs-line)',
        height: 44,
      }}>
        Очікую надходження
      </button>
    );
  } else if (isPreorder) {
    // Distinct from green «Купити» — softer blue (`--bs-blue-light`), not the
    // deep brand blue. Reader sees this is a purchase action, but not
    // «standard buy» — lighter, less saturated reads as «delayed/optional».
    cta = (
      <button className="bs-btn" style={{
        width: '100%', height: 44,
        background: 'var(--bs-blue-light)', color: '#fff',
        fontSize: 14.5, fontWeight: 700,
      }} onClick={onAdd}>
        <I.Cart width="16" height="16" /> Передзамовити
      </button>
    );
  } else {
    cta = (
      <button className="bs-btn bs-btn-primary" style={{
        width: '100%', height: 44,
        fontSize: 14.5, fontWeight: 700,
      }} onClick={onAdd}>
        <I.Cart width="16" height="16" /> Купити
      </button>
    );
  }

  return (
    <div className="bs-card" style={{ overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
      <div style={{ position: 'relative', padding: 12 }}>
        <ImagePh
          label={brand === 'pokemon' ? 'Pokémon TCG photo' : 'One Piece TCG photo'}
          radius="var(--bs-r-sm)"
          style={isOut ? { opacity: 0.45 } : null}
        />
        {discount > 0 && !isOut && (
          <div style={{ position: 'absolute', top: 18, right: 18 }}>
            <DiscountPill percent={discount} />
          </div>
        )}
        {state && state !== 'sealed' && state !== 'unweighed' && (
          <div style={{ position: 'absolute', top: 18, left: 18 }}>
            <StatePill state={state} />
          </div>
        )}
      </div>

      <div style={{ padding: '4px 14px 14px', display: 'flex', flexDirection: 'column', gap: 10 }}>
        <h4 style={{
          fontSize: 14, fontWeight: 600, lineHeight: 1.4,
          color: isOut ? 'var(--bs-ink-3)' : 'var(--bs-ink)',
          display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical',
          overflow: 'hidden', minHeight: 40,
          letterSpacing: '-0.005em',
        }}>{title}</h4>

        <PriceRow price={price} oldPrice={oldPrice} />
        {cta}
      </div>
    </div>
  );
}

// Trust strip — used on home and product page. Matches production reference:
// 3 equal bordered tiles, icon over label, centered.
function TrustStrip() {
  const items = [
    { icon: <I.Shield width="16" height="16" />, text: 'Гарантія оригінальності' },
    { icon: <I.Zap width="16" height="16" />,    text: 'Швидка відправка' },
    { icon: <I.Tg width="16" height="16" />,     text: 'Telegram підтримка' },
  ];
  return (
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10 }}>
      {items.map((it, i) => (
        <div key={i} style={{
          display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
          gap: 6, textAlign: 'center', padding: '13px 8px',
          background: '#fff', border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r)',
        }}>
          <span style={{
            display: 'inline-flex', width: 28, height: 28, borderRadius: 'var(--bs-r-sm)',
            background: 'var(--bs-bg)', alignItems: 'center', justifyContent: 'center',
            color: 'var(--bs-ink-2)', flex: '0 0 auto',
          }}>{it.icon}</span>
          <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--bs-ink-2)', lineHeight: 1.3, textWrap: 'balance' }}>{it.text}</span>
        </div>
      ))}
    </div>
  );
}

Object.assign(window, { I, ImagePh, ProductCard, PRODUCTS, StatePill, DiscountPill, PriceRow, TrustStrip });
