// Side-by-side comparison of the Акції page order (before/after).
// LEFT  = current order:  Intro → FAQ → Products → Footer
// RIGHT = proposed order:  Intro → Products → FAQ → Footer
// Mini layout — shows section sequence, not the FAQ design itself.

function StackBlock({ label, kind, height, dim }) {
  const palette = {
    intro: { bg: '#fff', text: 'var(--bs-ink)', accent: 'var(--bs-ink-3)' },
    faq:   { bg: 'var(--bs-gold-soft)', text: 'var(--bs-ink)', accent: 'var(--bs-gold)' },
    products: { bg: '#fff', text: 'var(--bs-ink)', accent: 'var(--bs-green)' },
    footer: { bg: '#0f1115', text: '#9aa3ad', accent: '#9aa3ad' },
  }[kind];
  return (
    <div style={{
      background: palette.bg,
      border: kind === 'footer' ? 'none' : '1px solid var(--bs-line)',
      borderRadius: 10,
      padding: '14px 16px',
      minHeight: height,
      display: 'flex', flexDirection: 'column', justifyContent: 'space-between',
      gap: 10,
      opacity: dim ? 0.55 : 1,
      transition: 'opacity .2s',
    }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <span style={{
          fontFamily: "'JetBrains Mono', ui-monospace, monospace",
          fontSize: 10, letterSpacing: '.14em',
          color: palette.accent, textTransform: 'uppercase', fontWeight: 500,
        }}>{label}</span>
      </div>
      {/* Visual content sketch — different for each kind */}
      {kind === 'intro' && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 5 }}>
          <div style={{ height: 8, background: 'var(--bs-line)', borderRadius: 3, width: '85%' }} />
          <div style={{ height: 8, background: 'var(--bs-line)', borderRadius: 3, width: '92%' }} />
          <div style={{ height: 8, background: 'var(--bs-line)', borderRadius: 3, width: '70%' }} />
        </div>
      )}
      {kind === 'faq' && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
          {[0,1,2].map(i => (
            <div key={i} style={{
              display: 'flex', alignItems: 'center', gap: 8,
              background: '#fff', borderRadius: 6,
              border: '1px solid var(--bs-gold-line)',
              padding: '6px 10px',
            }}>
              <div style={{ flex: 1, height: 6, background: 'var(--bs-line-2)', borderRadius: 2 }} />
              <div style={{
                width: 14, height: 14, borderRadius: 4,
                background: 'var(--bs-gold)', color: '#fff',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                fontSize: 10, fontWeight: 700, lineHeight: 1,
              }}>+</div>
            </div>
          ))}
        </div>
      )}
      {kind === 'products' && (
        <div style={{
          display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 8,
        }}>
          {[0,1,2].map(i => (
            <div key={i} style={{
              border: '1px solid var(--bs-line)', borderRadius: 6,
              padding: 6, display: 'flex', flexDirection: 'column', gap: 4,
            }}>
              <div style={{
                aspectRatio: '1/1', borderRadius: 4,
                background: 'repeating-linear-gradient(45deg, #f0eee9 0 4px, #e8e5dc 4px 8px)',
                position: 'relative',
              }}>
                <span style={{
                  position: 'absolute', top: 3, left: 3,
                  background: '#e11d2d', color: '#fff',
                  fontSize: 7, fontWeight: 700, padding: '1px 3px',
                  borderRadius: 2, letterSpacing: '.04em',
                }}>−25%</span>
              </div>
              <div style={{ height: 5, background: 'var(--bs-line)', borderRadius: 2, width: '80%' }} />
              <div style={{
                background: 'var(--bs-green)', height: 12, borderRadius: 3,
                marginTop: 2,
              }} />
            </div>
          ))}
        </div>
      )}
      {kind === 'footer' && (
        <div style={{
          display: 'flex', justifyContent: 'space-between', alignItems: 'center',
          color: '#6c7480', fontSize: 10,
          fontFamily: "'JetBrains Mono', ui-monospace, monospace",
        }}>
          <span>BOOSTER SHOP © 2026</span>
          <span>Telegram · Кабінет</span>
        </div>
      )}
    </div>
  );
}

function Arrow({ direction = 'down', highlight }) {
  return (
    <div style={{
      display: 'flex', justifyContent: 'center', padding: '6px 0',
      color: highlight ? 'var(--bs-gold)' : 'var(--bs-ink-3)',
    }}>
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
        <path d="M8 3v10M4 9l4 4 4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
      </svg>
    </div>
  );
}

function PromoStack({ title, subtitle, blocks, accent }) {
  return (
    <div style={{
      flex: 1,
      background: '#fff',
      border: `1px solid ${accent ? 'var(--bs-gold-line)' : 'var(--bs-line)'}`,
      borderRadius: 14,
      padding: 18,
      display: 'flex', flexDirection: 'column', gap: 0,
      position: 'relative',
    }}>
      <div style={{
        display: 'flex', alignItems: 'center', gap: 8,
        marginBottom: 4,
      }}>
        <div style={{
          fontSize: 11, fontWeight: 700, textTransform: 'uppercase',
          letterSpacing: '.1em',
          color: accent ? 'var(--bs-gold)' : 'var(--bs-ink-3)',
        }}>{title}</div>
        {accent && (
          <span style={{
            background: 'var(--bs-gold)', color: '#fff',
            fontSize: 9, fontWeight: 700, padding: '2px 6px',
            borderRadius: 3, letterSpacing: '.06em',
          }}>NEW</span>
        )}
      </div>
      <div style={{
        fontSize: 16, fontWeight: 700, color: 'var(--bs-ink)',
        marginBottom: 12, letterSpacing: '-0.01em',
      }}>
        {subtitle}
      </div>
      <div style={{ display: 'flex', flexDirection: 'column' }}>
        {blocks.map((b, i) => (
          <React.Fragment key={i}>
            <StackBlock {...b} />
            {i < blocks.length - 1 && <Arrow highlight={b.highlightArrow} />}
          </React.Fragment>
        ))}
      </div>
    </div>
  );
}

function PromoReorder() {
  return (
    <div style={{
      padding: '28px 28px',
      background: '#fafaf8',
      fontFamily: "'Manrope', system-ui, sans-serif",
      height: '100%',
      boxSizing: 'border-box',
    }}>
      <div style={{ marginBottom: 18 }}>
        <div style={{
          fontFamily: "'JetBrains Mono', ui-monospace, monospace",
          fontSize: 11, fontWeight: 500, letterSpacing: '.14em',
          color: 'var(--bs-gold)', textTransform: 'uppercase',
          marginBottom: 6,
        }}>Сторінка «Акції»</div>
        <h3 style={{
          margin: 0, fontSize: 24, fontWeight: 800, letterSpacing: '-0.015em',
          color: 'var(--bs-ink)',
        }}>
          Порядок секцій: FAQ після товарів
        </h3>
        <p style={{
          margin: '8px 0 0', fontSize: 13.5, lineHeight: 1.55,
          color: 'var(--bs-ink-2)', maxWidth: 600,
        }}>
          На сторінках акцій користувач прийшов <strong>за товарами зі знижкою</strong> —
          довідкові питання не повинні стояти між ним і пропозицією. Переставляємо FAQ
          нижче, щоб не блокувати воронку.
        </p>
      </div>

      <div style={{ display: 'flex', gap: 18, alignItems: 'stretch' }}>
        <PromoStack
          title="Зараз"
          subtitle="Intro → FAQ → Товари"
          blocks={[
            { label: '01 · Заголовок та опис', kind: 'intro', height: 60 },
            { label: '02 · FAQ', kind: 'faq', height: 110, dim: false },
            { label: '03 · Товари акції', kind: 'products', height: 130 },
            { label: '04 · Футер', kind: 'footer', height: 36 },
          ]}
        />
        <PromoStack
          title="Стане"
          subtitle="Intro → Товари → FAQ"
          accent
          blocks={[
            { label: '01 · Заголовок та опис', kind: 'intro', height: 60 },
            { label: '02 · Товари акції', kind: 'products', height: 130, highlightArrow: true },
            { label: '03 · FAQ', kind: 'faq', height: 110 },
            { label: '04 · Футер', kind: 'footer', height: 36 },
          ]}
        />
      </div>

      <div style={{
        marginTop: 18,
        background: '#fff',
        border: '1px solid var(--bs-line)', borderRadius: 10,
        padding: '14px 18px',
        display: 'flex', gap: 18, alignItems: 'flex-start',
      }}>
        <div style={{
          width: 32, height: 32, borderRadius: 8,
          background: 'var(--bs-gold-soft)',
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          flex: '0 0 auto',
        }}>
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M8 1.5L9.5 6h4.5l-3.7 2.7L11.7 13 8 10.3 4.3 13l1.4-4.3L2 6h4.5L8 1.5z" stroke="var(--bs-gold)" strokeWidth="1.3" strokeLinejoin="round"/>
          </svg>
        </div>
        <div style={{ fontSize: 13, lineHeight: 1.55, color: 'var(--bs-ink-2)' }}>
          <strong style={{ color: 'var(--bs-ink)' }}>Чому це працює:</strong> на сторінці акцій FAQ не несе SEO-функції першого екрану — він важить як «трастовий хвіст» (повертає сумнівних, допомагає Schema). А промо-картки мають побачитися одразу під hero.
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { PromoReorder });
