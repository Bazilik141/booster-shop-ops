// Home-page category tile — 4 alternative directions to choose from.
// All four share the brand-color rule: Pokémon gold + One Piece blue stay
// scoped to the tiles themselves, never bleed into surrounding UI.

// V-A — current direction (horizontal, brand band + logo card inside)
function TileVariantA() {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
      {[
        { accent: 'var(--bs-pokemon)', label: 'Pokémon TCG', brand: 'Pokémon', count: '24 товари' },
        { accent: 'var(--bs-onepiece)', label: 'One Piece Card Game', brand: 'One Piece', count: '12 товарів' },
      ].map(c => (
        <a key={c.label} href="#" style={tileBase}>
          <div style={{
            background: c.accent, padding: 24, minHeight: 170,
            display: 'flex', alignItems: 'center', gap: 16, position: 'relative',
          }}>
            <span style={{ ...labelInline, fontSize: 18 }}>{c.label}</span>
            <div style={logoCard(c.accent)}>{c.brand} logo</div>
          </div>
          <div style={tileFooter}>
            <span>{c.count}</span>
            <I.Chevron width="12" height="12" style={{ transform: 'rotate(-90deg)', color: 'var(--bs-ink-3)' }} />
          </div>
        </a>
      ))}
    </div>
  );
}

// V-B — Image-on-top vertical stack. Big brand panel with logo centered, text
// block below. Feels closest to a clean Shopify collection card.
function TileVariantB() {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
      {[
        { accent: 'var(--bs-pokemon)', label: 'Pokémon TCG', brand: 'Pokémon',
          tag: 'Японські та корейські sealed-бустери', count: '24 товари' },
        { accent: 'var(--bs-onepiece)', label: 'One Piece Card Game', brand: 'One Piece',
          tag: 'Sealed packs · Heroines · Mystery Box', count: '12 товарів' },
      ].map(c => (
        <a key={c.label} href="#" style={tileBase}>
          <div style={{
            background: c.accent, height: 200,
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            position: 'relative',
          }}>
            <div style={logoCard(c.accent, { width: 180, aspectRatio: '5/2' })}>{c.brand} logo</div>
            <span style={{
              position: 'absolute', top: 14, left: 18,
              fontSize: 11, fontWeight: 700, letterSpacing: '.1em',
              color: 'rgba(255,255,255,0.7)', textTransform: 'uppercase',
            }}>Категорія</span>
          </div>
          <div style={{ padding: '16px 20px', display: 'flex', flexDirection: 'column', gap: 6 }}>
            <h3 style={{ fontSize: 18, margin: 0 }}>{c.label}</h3>
            <div style={{ fontSize: 13, color: 'var(--bs-ink-3)' }}>{c.tag}</div>
            <div style={{
              marginTop: 6, display: 'flex', justifyContent: 'space-between', alignItems: 'center',
            }}>
              <span style={{ fontSize: 12, color: 'var(--bs-ink-3)' }}>{c.count}</span>
              <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--bs-blue)' }}>Переглянути →</span>
            </div>
          </div>
        </a>
      ))}
    </div>
  );
}

// V-C — Asymmetric editorial. Pokémon as 2-col wide hero with strong visual,
// One Piece as 1-col secondary. Useful if Pokémon is your bigger SKU base.
function TileVariantC() {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 16, minHeight: 280 }}>
      {/* Pokémon — wide hero */}
      <a href="#" style={{ ...tileBase, gridRow: 'span 2' }}>
        <div style={{
          background: 'var(--bs-pokemon)', flex: 1, padding: 28,
          display: 'flex', flexDirection: 'column', justifyContent: 'space-between',
          position: 'relative', overflow: 'hidden',
        }}>
          <div style={{
            position: 'absolute', right: -40, top: -40, width: 280, height: 280,
            borderRadius: '50%',
            background: 'radial-gradient(circle at 30% 30%, rgba(255,255,255,0.25), transparent 70%)',
          }} />
          <div style={{ position: 'relative' }}>
            <span style={{
              fontSize: 11, fontWeight: 700, letterSpacing: '.12em',
              color: 'rgba(255,255,255,0.75)', textTransform: 'uppercase',
            }}>Категорія</span>
            <h2 style={{ color: '#fff', fontSize: 32, marginTop: 6 }}>Pokémon TCG</h2>
            <p style={{ marginTop: 8, fontSize: 14, color: 'rgba(255,255,255,0.85)', maxWidth: 360, lineHeight: 1.55 }}>
              Японські та корейські sealed-бустери, бустер-бокси, набори.
            </p>
          </div>
          <div style={{ ...logoCard('var(--bs-pokemon)', { width: 220, aspectRatio: '16/6' }), alignSelf: 'flex-end' }}>Pokémon logo</div>
        </div>
        <div style={tileFooter}>
          <span>24 товари</span>
          <I.Chevron width="12" height="12" style={{ transform: 'rotate(-90deg)', color: 'var(--bs-ink-3)' }} />
        </div>
      </a>

      {/* One Piece + sub-stacked secondary tile */}
      <a href="#" style={tileBase}>
        <div style={{
          background: 'var(--bs-onepiece)', padding: 20, minHeight: 130,
          display: 'flex', flexDirection: 'column', justifyContent: 'space-between',
        }}>
          <span style={{ ...labelInline, fontSize: 15 }}>One Piece Card Game</span>
          <div style={{ ...logoCard('var(--bs-onepiece)', { width: 140, aspectRatio: '5/2' }), alignSelf: 'flex-end' }}>One Piece</div>
        </div>
        <div style={tileFooter}>
          <span>12 товарів</span>
          <I.Chevron width="12" height="12" style={{ transform: 'rotate(-90deg)', color: 'var(--bs-ink-3)' }} />
        </div>
      </a>
      <a href="#" style={tileBase}>
        <div style={{
          padding: '20px 22px', minHeight: 130,
          display: 'flex', flexDirection: 'column', justifyContent: 'space-between',
          background: 'var(--bs-bg)',
        }}>
          <span style={{ fontSize: 11, fontWeight: 700, letterSpacing: '.12em', color: 'var(--bs-ink-3)', textTransform: 'uppercase' }}>
            Підбірка
          </span>
          <h3 style={{ fontSize: 17, color: 'var(--bs-ink)' }}>Mystery Box · Low Pull</h3>
        </div>
        <div style={tileFooter}>
          <span>8 товарів</span>
          <I.Chevron width="12" height="12" style={{ transform: 'rotate(-90deg)', color: 'var(--bs-ink-3)' }} />
        </div>
      </a>
    </div>
  );
}

// V-D — Brand strip + content card. Brand colour is a thin top strip, body of
// the card is neutral white with logo placeholder front-and-centre. Quietest
// option; reads as a curated retailer index.
function TileVariantD() {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
      {[
        { accent: 'var(--bs-pokemon)', label: 'Pokémon TCG', brand: 'Pokémon', tag: 'JP · KR sealed бустери, boxes', count: '24 товари' },
        { accent: 'var(--bs-onepiece)', label: 'One Piece Card Game', brand: 'One Piece', tag: 'Sealed packs · Heroines · Mystery Box', count: '12 товарів' },
      ].map(c => (
        <a key={c.label} href="#" style={tileBase}>
          {/* Thin brand strip */}
          <div style={{ background: c.accent, height: 6 }} />
          <div style={{ padding: '24px 24px 20px', display: 'flex', alignItems: 'center', gap: 18 }}>
            <div style={{
              ...logoCard(c.accent, { width: 110, aspectRatio: '1/1' }),
              backgroundImage: `repeating-linear-gradient(45deg, ${c.accent}1F 0 12px, transparent 12px 24px)`,
            }}>{c.brand}</div>
            <div style={{ flex: 1 }}>
              <h3 style={{ fontSize: 18, margin: 0 }}>{c.label}</h3>
              <div style={{ fontSize: 13, color: 'var(--bs-ink-3)', marginTop: 6, lineHeight: 1.5 }}>{c.tag}</div>
              <div style={{ marginTop: 10, display: 'flex', alignItems: 'center', gap: 14 }}>
                <span style={{ fontSize: 12, color: 'var(--bs-ink-3)' }}>{c.count}</span>
                <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--bs-blue)' }}>Переглянути →</span>
              </div>
            </div>
          </div>
        </a>
      ))}
    </div>
  );
}

// Shared styles -------------------------------------------------------------
const tileBase = {
  background: '#fff', border: '1px solid var(--bs-line)',
  borderRadius: 'var(--bs-r-lg)', overflow: 'hidden',
  color: 'var(--bs-ink)', display: 'flex', flexDirection: 'column',
  textDecoration: 'none',
};
const tileFooter = {
  padding: '12px 20px', display: 'flex', alignItems: 'center',
  justifyContent: 'space-between',
  fontSize: 12, color: 'var(--bs-ink-3)',
  borderTop: '1px solid var(--bs-line-2)',
};
const labelInline = {
  position: 'absolute', top: 18, left: 22,
  fontWeight: 800, color: '#fff', letterSpacing: '-0.015em',
};
function logoCard(accent, extra = {}) {
  return {
    background: '#fff', borderRadius: 10, padding: '14px 22px',
    minWidth: 200, aspectRatio: '16/6',
    display: 'flex', alignItems: 'center', justifyContent: 'center',
    fontWeight: 800, fontSize: 18, letterSpacing: '-0.02em',
    color: accent, textTransform: 'uppercase',
    marginLeft: 'auto',
    backgroundImage: `repeating-linear-gradient(45deg, rgba(255,255,255,0.6) 0 12px, transparent 12px 24px)`,
    ...extra,
  };
}

function HomeTilesShowcase() {
  const variants = [
    { id: 'A', name: 'A · Horizontal band (поточний)', comp: <TileVariantA /> },
    { id: 'B', name: 'B · Image-top vertical card', comp: <TileVariantB /> },
    { id: 'C', name: 'C · Asymmetric editorial', comp: <TileVariantC /> },
    { id: 'D', name: 'D · Quiet retailer index', comp: <TileVariantD /> },
  ];
  return (
    <div className="bs-mock" style={{
      padding: 28, background: 'var(--bs-bg)', minHeight: '100%',
    }}>
      <div style={{ marginBottom: 18, maxWidth: 760 }}>
        <h3 style={{ marginBottom: 6 }}>Тайли категорій — 4 напрями</h3>
        <p style={{ fontSize: 13.5, color: 'var(--bs-ink-3)' }}>
          Усі чотири дотримуються правила: бренд-кольори Pokémon (gold) та One Piece (blue) живуть лише
          всередині тайла, ніколи не виходять в навколишній UI. Logo-карта — placeholder для реального
          бренд-візуалу.
        </p>
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 32 }}>
        {variants.map(v => (
          <div key={v.id}>
            <div style={{
              fontFamily: '"JetBrains Mono", ui-monospace, monospace',
              fontSize: 11, color: 'var(--bs-ink-3)', letterSpacing: '.06em',
              textTransform: 'uppercase', marginBottom: 10,
            }}>{v.name}</div>
            {v.comp}
          </div>
        ))}
      </div>
    </div>
  );
}

Object.assign(window, {
  TileVariantA, TileVariantB, TileVariantC, TileVariantD, HomeTilesShowcase,
});
