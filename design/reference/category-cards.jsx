// Category promo cards — same layout as the live site, but the logo image
// becomes a large FULL-BLEED left panel (object-fit: cover) so it reads big
// and is cropped vertically instead of growing the tile height.
// Logos are <image-slot> targets — drop the real Pokémon / One Piece logos in.

const { useState: cS } = React;

const CATS = [
  {
    id: 'pokemon', accent: 'var(--bs-pokemon)', title: 'Pokémon TCG',
    desc: 'Оригінальні бустери, бокси та набори Pokémon TCG. Японські, корейські й англійські видання, sealed, без зважування.',
    ph: 'Лого Pokémon',
  },
  {
    id: 'onepiece', accent: 'var(--bs-onepiece)', title: 'One Piece Card Game',
    desc: 'Оригінальні бустери та бокси One Piece Card Game від Bandai. Sealed із боксів, без сортування.',
    ph: 'Лого One Piece',
  },
];

/* one card. `h` = fixed card height (image crops to it); `imgW` = logo panel width */
function CatCard({ cat, suffix, h = 168, imgW = 168, clamp = 3 }) {
  return (
    <div style={{
      position: 'relative', display: 'flex', height: h, background: '#fff',
      border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r)',
      boxShadow: 'var(--bs-sh-sm)', overflow: 'hidden',
    }}>
      {/* top accent bar */}
      <div style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 4, background: cat.accent, zIndex: 2 }} />
      {/* enlarged full-bleed logo panel — cropped vertically, never grows the tile */}
      <image-slot
        id={`cat-${cat.id}${suffix}`}
        style={{ width: imgW, height: h, flex: `0 0 ${imgW}px`, display: 'block', background: 'var(--bs-bg)', borderRight: '1px solid var(--bs-line)' }}
        shape="rect"
        fit="cover"
        placeholder={cat.ph}
      ></image-slot>
      {/* text */}
      <div style={{ flex: 1, minWidth: 0, padding: '18px 20px', display: 'flex', flexDirection: 'column' }}>
        <div style={{ fontSize: 18, fontWeight: 800, color: 'var(--bs-ink)', letterSpacing: '-0.01em' }}>{cat.title}</div>
        <div style={{ fontSize: 13.5, lineHeight: 1.5, color: 'var(--bs-ink-3)', marginTop: 6, textWrap: 'pretty', display: '-webkit-box', WebkitLineClamp: clamp, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>{cat.desc}</div>
        <a href="#" style={{ marginTop: 'auto', paddingTop: 10, fontSize: 13.5, fontWeight: 700, color: 'var(--bs-blue)', textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: 6 }}>
          Переглянути <Ic.Arrow width="15" height="15" />
        </a>
      </div>
    </div>
  );
}

function CategoryCardsDemo() {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 30, width: '100%' }}>

      {/* DESKTOP — two across, like now */}
      <div style={{ width: '100%', maxWidth: 1080 }}>
        <div style={{ fontSize: 12.5, color: 'var(--bs-ink-3)', fontWeight: 600, marginBottom: 12 }}>Desktop · дві картки в ряд</div>
        <div style={{ background: '#fff', border: '1px solid var(--bs-line)', borderRadius: 14, padding: '26px 28px', boxShadow: '0 18px 50px rgba(17,24,39,0.10)' }}>
          <h3 style={{ fontSize: 21, fontWeight: 800, color: 'var(--bs-ink)', letterSpacing: '-0.02em', margin: '0 0 18px' }}>Оригінальні бустери та бокси Pokémon, One Piece та інших TCG</h3>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18 }}>
            {CATS.map(c => <CatCard key={c.id} cat={c} suffix="-d" />)}
          </div>
        </div>
      </div>

      {/* MOBILE — stacked full width */}
      <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 12 }}>
        <div style={{ fontSize: 12.5, color: 'var(--bs-ink-3)', fontWeight: 600 }}>Mobile · картки одна під одною</div>
        <div style={{ width: 360, background: '#fff', border: '9px solid #15181d', borderRadius: 36, padding: 14, boxShadow: '0 20px 50px rgba(17,24,39,0.18)' }}>
          <h3 style={{ fontSize: 16, fontWeight: 800, color: 'var(--bs-ink)', letterSpacing: '-0.01em', margin: '4px 2px 14px', lineHeight: 1.3 }}>Оригінальні бустери та бокси Pokémon, One Piece та інших TCG</h3>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            {CATS.map(c => <CatCard key={c.id} cat={c} suffix="-m" h={132} imgW={124} clamp={2} />)}
          </div>
        </div>
      </div>

      <div style={{ fontSize: 12.5, color: 'var(--bs-ink-3)', fontWeight: 600, textAlign: 'center', maxWidth: 720 }}>
        Лого тепер займає всю висоту картки (full-bleed, <code>object-fit: cover</code>) — масштабується вгору й обрізається по вертикалі, тож висота плитки не змінюється. Перетягніть свій логотип у будь-яку плитку; подвійний клік — перекадрувати.
      </div>
    </div>
  );
}

Object.assign(window, { CategoryCardsDemo, CatCard });
