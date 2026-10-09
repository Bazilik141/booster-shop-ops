// Homepage mockup — round 2.
// Round-1 feedback applied:
//   - Removed hero text card + featured booster card + trust strip.
//   - Category tiles keep brand colour AND restore the logo image
//     (placeholder card with brand label, like the live screenshots).
// Kept (per feedback "ок"): Recommended, Promo banner, FAQ teaser.

function CategoryTile({ accent, accentSoft, label, brand, text, count }) {
  return (
    <a href="#" style={{
      background: '#fff', border: '1px solid var(--bs-line)',
      borderRadius: 'var(--bs-r-lg)', overflow: 'hidden',
      color: 'var(--bs-ink)', display: 'flex', flexDirection: 'column',
      transition: 'border-color .15s, transform .15s',
    }}>
      {/* Coloured top with logo card inside — same affordance as the live site */}
      <div style={{
        background: accent,
        position: 'relative', overflow: 'hidden',
        padding: 24,
        display: 'flex', alignItems: 'center', gap: 24,
        minHeight: 170,
      }}>
        {/* Brand label, top-left */}
        <span style={{
          position: 'absolute', top: 18, left: 22,
          fontSize: 18, fontWeight: 800, color: '#fff',
          letterSpacing: '-0.02em',
        }}>{label}</span>

        {/* White card holding the official logo (placeholder here) */}
        <div style={{
          marginLeft: 'auto',
          background: '#fff',
          borderRadius: 10,
          padding: '14px 20px',
          minWidth: 220,
          aspectRatio: '16/6',
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          fontWeight: 800, fontSize: 22, letterSpacing: '-0.02em',
          color: accent,
          textTransform: 'uppercase',
          fontFamily: '"Manrope", system-ui, sans-serif',
          // subtle stripes hint that this is a placeholder, not a real logo
          backgroundImage: `repeating-linear-gradient(45deg, ${accentSoft} 0 12px, transparent 12px 24px)`,
        }}>
          {brand} logo
        </div>
      </div>

      <div style={{
        padding: '16px 20px',
        display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 16,
      }}>
        <div>
          <div style={{ fontSize: 13.5, color: 'var(--bs-ink-2)', lineHeight: 1.4 }}>{text}</div>
          <div style={{ marginTop: 6, fontSize: 12, color: 'var(--bs-ink-3)' }}>{count}</div>
        </div>
        <I.Chevron width="14" height="14" style={{
          color: 'var(--bs-ink-3)', transform: 'rotate(-90deg)', flex: '0 0 auto',
        }} />
      </div>
    </a>
  );
}

function HomeMock() {
  return (
    <div className="bs-mock" style={{ background: 'var(--bs-bg)' }}>
      <HeaderV1 />

      <main style={{ maxWidth: 1240, margin: '0 auto', padding: '28px 32px 56px' }}>
        {/* Category tiles — variant D (Quiet retailer index), approved round-3. */}
        <section style={{ marginBottom: 40 }}>
          <TileVariantD />
        </section>

        {/* Recommended */}
        <section style={{ marginBottom: 40 }}>
          <header style={{ marginBottom: 16, display: 'flex', alignItems: 'baseline', justifyContent: 'space-between' }}>
            <h2>Рекомендовані товари</h2>
            <a href="#" style={{ fontSize: 13.5, fontWeight: 600 }}>Усі товари →</a>
          </header>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 16 }}>
            {PRODUCTS.slice(0, 4).map(p => (
              <ProductCard key={p.id} product={p} />
            ))}
          </div>
        </section>

        {/* Promo strip */}
        <section style={{ marginBottom: 40 }}>
          <a href="#" style={{
            display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 20,
            background: '#fff', border: '1px solid var(--bs-line)',
            borderRadius: 'var(--bs-r-lg)',
            padding: '22px 28px',
            color: 'var(--bs-ink)',
          }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 18 }}>
              <span style={{
                width: 44, height: 44, borderRadius: 'var(--bs-r-sm)',
                background: 'var(--bs-warning-bg)', color: 'var(--bs-warning-fg)',
                display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontWeight: 800,
                fontSize: 18,
              }}>%</span>
              <div>
                <div style={{ fontSize: 16, fontWeight: 700 }}>Акції цього тижня</div>
                <div style={{ fontSize: 13, color: 'var(--bs-ink-3)' }}>Sealed-бустери до −25%, Low Pull від ₴80</div>
              </div>
            </div>
            <span className="bs-btn bs-btn-secondary">Подивитись акції →</span>
          </a>
        </section>

        {/* FAQ teaser */}
        <section>
          <div style={{
            background: '#fff', border: '1px solid var(--bs-line)',
            borderRadius: 'var(--bs-r-lg)', padding: 28,
            display: 'grid', gridTemplateColumns: '1fr auto', gap: 24, alignItems: 'center',
          }}>
            <div>
              <h2 style={{ fontSize: 20 }}>Питання про бустери, sealed та Low Pull?</h2>
              <p style={{ marginTop: 8, color: 'var(--bs-ink-3)', fontSize: 14, lineHeight: 1.6 }}>
                У FAQ зібрано пояснення термінів, чим відрізняються японські та корейські бустери, і як ми пакуємо замовлення.
              </p>
            </div>
            <a href="FAQ редизайн.html" className="bs-btn bs-btn-secondary" style={{ padding: '12px 18px' }}>
              Відкрити FAQ →
            </a>
          </div>
        </section>
      </main>
    </div>
  );
}

Object.assign(window, { HomeMock, CategoryTile });
