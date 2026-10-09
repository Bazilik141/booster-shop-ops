// Shared scaffolding for the six content-page mockups (checkout success,
// гарантія, про нас, оплата і доставка, обмін і повернення, публічна оферта).
//
// Visual system follows boostershop-ds.css:
//   • Page sits on F7F7F5 with white cards inside.
//   • Page Hero = white card, eyebrow "BOOSTER SHOP" + h1 + lede.
//   • Body = two-column on desktop: sticky TOC (260px) + content (rest).
//   • Section pattern = small mono eyebrow + h2 + body. No SVG illustrations;
//     we use simple icon glyphs from the shared I icon set sparingly.
//   • Callouts: blue-soft pull-quote, neutral fact card, gold accent for trust.
//   • Single green CTA per page (Telegram or "Продовжити").

const C = {
  Mail: (p) => (<svg {...p} viewBox="0 0 18 18" fill="none"><rect x="2" y="4" width="14" height="10" rx="1.6" stroke="currentColor" strokeWidth="1.4"/><path d="M2 5l7 5 7-5" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/></svg>),
  Card: (p) => (<svg {...p} viewBox="0 0 18 18" fill="none"><rect x="2" y="4" width="14" height="10" rx="1.6" stroke="currentColor" strokeWidth="1.4"/><path d="M2 8h14" stroke="currentColor" strokeWidth="1.4"/></svg>),
  Box:  (p) => (<svg {...p} viewBox="0 0 18 18" fill="none"><path d="M2 6l7-3 7 3v6l-7 3-7-3V6z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/><path d="M2 6l7 3 7-3M9 9v6" stroke="currentColor" strokeWidth="1.4"/></svg>),
  Clock:(p) => (<svg {...p} viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="6.5" stroke="currentColor" strokeWidth="1.4"/><path d="M9 5v4l3 1.5" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round"/></svg>),
  Phone:(p) => (<svg {...p} viewBox="0 0 18 18" fill="none"><path d="M5 2.5h3l1 3-2 1c.7 2 2 3.3 4 4l1-2 3 1v3a1 1 0 01-1 1A12 12 0 014 4a1 1 0 011-1.5z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/></svg>),
  Chat: (p) => (<svg {...p} viewBox="0 0 18 18" fill="none"><path d="M3 4h12v8H7l-3 3v-3H3V4z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/></svg>),
  Doc:  (p) => (<svg {...p} viewBox="0 0 18 18" fill="none"><path d="M4 2h7l3 3v11H4V2z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round"/><path d="M11 2v3h3M6 9h6M6 12h6M6 6h2" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round"/></svg>),
  Return:(p) => (<svg {...p} viewBox="0 0 18 18" fill="none"><path d="M4 9a5 5 0 015-5h2l-2-2m2 2l-2 2" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round"/><path d="M14 9a5 5 0 01-5 5H7l2 2m-2-2l2-2" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  Yen:  (p) => (<svg {...p} viewBox="0 0 18 18" fill="none"><path d="M4 3l5 7 5-7M5 10h8M5 13h8M9 10v5" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round"/></svg>),
  Sparkle:(p)=>(<svg {...p} viewBox="0 0 18 18" fill="none"><path d="M9 2v4M9 12v4M2 9h4M12 9h4M5 5l2.5 2.5M10.5 10.5L13 13M13 5l-2.5 2.5M7.5 10.5L5 13" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round"/></svg>),
};

// Eyebrow — small mono label above titles. Adds editorial rhythm without
// shouting. Default colour is ink-3 unless tone='gold'.
function Eyebrow({ children, tone }) {
  const colour = tone === 'gold' ? 'var(--bs-gold)'
                : tone === 'blue' ? 'var(--bs-blue)'
                : 'var(--bs-ink-3)';
  return (
    <div style={{
      fontFamily: '"JetBrains Mono", ui-monospace, monospace',
      fontSize: 11, fontWeight: 500, letterSpacing: '0.14em',
      color: colour, textTransform: 'uppercase',
      display: 'inline-flex', alignItems: 'center', gap: 10,
    }}>
      <span style={{ width: 18, height: 1, background: 'currentColor', opacity: 0.55 }} />
      {children}
    </div>
  );
}

// Breadcrumb (DS style, single line).
function Crumbs({ trail }) {
  return (
    <nav style={{
      display: 'flex', alignItems: 'center', gap: 8,
      fontSize: 13, color: 'var(--bs-ink-3)', marginBottom: 18, flexWrap: 'wrap',
    }}>
      <a href="#" style={{ color: 'var(--bs-ink-3)', display: 'inline-flex', alignItems: 'center' }}>
        <I.Home width="13" height="13" />
      </a>
      {trail.map((label, i) => (
        <React.Fragment key={i}>
          <span style={{ opacity: 0.55 }}>›</span>
          {i === trail.length - 1
            ? <span style={{ color: 'var(--bs-ink)' }}>{label}</span>
            : <a href="#" style={{ color: 'var(--bs-blue)' }}>{label}</a>}
        </React.Fragment>
      ))}
    </nav>
  );
}

// Hero card — eyebrow + h1 + lede inside a white card with a gold (or blue,
// or green) accent stripe on the left edge. Replaces the current "boxed" hero
// from the existing pages with something that has more rhythm.
function Hero({ eyebrow, title, lede, accent = 'gold', icon }) {
  const stripe = accent === 'blue' ? 'var(--bs-blue)'
               : accent === 'green' ? 'var(--bs-green)'
               : 'var(--bs-gold)';
  return (
    <section className="bs-card" style={{
      padding: 0, overflow: 'hidden',
      display: 'grid', gridTemplateColumns: '4px 1fr',
      marginBottom: 32,
    }}>
      <div style={{ background: stripe }} />
      <div style={{ padding: '28px 32px', display: 'flex', alignItems: 'flex-start', gap: 24 }}>
        <div style={{ flex: 1 }}>
          <Eyebrow tone={accent === 'gold' ? 'gold' : 'blue'}>Booster Shop</Eyebrow>
          <h1 style={{
            fontSize: 38, fontWeight: 800, letterSpacing: '-0.02em',
            margin: '12px 0 10px', color: 'var(--bs-ink)', lineHeight: 1.1,
          }}>{title}</h1>
          {lede && (
            <p style={{
              fontSize: 16, lineHeight: 1.55, color: 'var(--bs-ink-2)',
              margin: 0, maxWidth: 680,
            }}>{lede}</p>
          )}
        </div>
        {icon && (
          <div style={{
            width: 72, height: 72, flex: '0 0 auto',
            borderRadius: 'var(--bs-r)',
            background: accent === 'green' ? '#DCFCE7'
                     : accent === 'blue' ? 'var(--bs-blue-soft)'
                     : 'var(--bs-gold-soft)',
            color: accent === 'green' ? 'var(--bs-green)'
                 : accent === 'blue' ? 'var(--bs-blue)'
                 : 'var(--bs-gold)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
          }}>
            {React.cloneElement(icon, { width: 32, height: 32 })}
          </div>
        )}
      </div>
    </section>
  );
}

// Section heading inside body. Optional eyebrow.
function H2({ eyebrow, children, id }) {
  return (
    <header style={{ marginBottom: 14 }} id={id}>
      {eyebrow && <Eyebrow>{eyebrow}</Eyebrow>}
      <h2 style={{
        fontSize: 22, fontWeight: 700, color: 'var(--bs-ink)',
        margin: eyebrow ? '8px 0 0' : 0, letterSpacing: '-0.015em',
      }}>{children}</h2>
    </header>
  );
}

// Standard paragraph text used inside Section.
function P({ children, muted }) {
  return (
    <p style={{
      fontSize: 15, lineHeight: 1.7,
      color: muted ? 'var(--bs-ink-3)' : 'var(--bs-ink-2)',
      margin: '0 0 12px',
    }}>{children}</p>
  );
}

// Definition block — keyword + definition. Used for "Sealed = ..." style copy.
function Def({ term, children }) {
  return (
    <p style={{
      fontSize: 15, lineHeight: 1.7, color: 'var(--bs-ink-2)',
      margin: '0 0 12px',
    }}>
      <strong style={{ color: 'var(--bs-ink)', fontWeight: 700 }}>{term}</strong>{' — '}
      {children}
    </p>
  );
}

// Section wrapper — big content block with optional id for TOC.
function Section({ id, children, padTop = true }) {
  return (
    <section id={id} style={{ paddingTop: padTop ? 36 : 0 }}>
      {children}
    </section>
  );
}

// Blue-soft pull callout — for key facts or "what this means for you" notes.
function Callout({ icon, title, children, tone = 'blue' }) {
  const palette = {
    blue:  { bg: 'var(--bs-blue-soft)', border: '#c7d2fe', fg: 'var(--bs-blue)' },
    gold:  { bg: 'var(--bs-gold-soft)', border: '#E8C766', fg: '#8a5a00' },
    green: { bg: '#DCFCE7',             border: '#86EFAC', fg: '#15803D' },
    grey:  { bg: 'var(--bs-bg)',        border: 'var(--bs-line)', fg: 'var(--bs-ink-2)' },
  }[tone];
  return (
    <aside style={{
      background: palette.bg, border: `1px solid ${palette.border}`,
      borderRadius: 'var(--bs-r)', padding: '16px 18px',
      margin: '8px 0 18px',
      display: 'grid', gridTemplateColumns: icon ? '28px 1fr' : '1fr', gap: 14,
    }}>
      {icon && (
        <span style={{
          color: palette.fg, display: 'inline-flex',
          alignItems: 'flex-start', paddingTop: 2,
        }}>
          {React.cloneElement(icon, { width: 20, height: 20 })}
        </span>
      )}
      <div>
        {title && (
          <div style={{
            fontSize: 14, fontWeight: 700, color: palette.fg,
            marginBottom: children ? 4 : 0,
          }}>{title}</div>
        )}
        {children && (
          <div style={{ fontSize: 14, lineHeight: 1.6, color: 'var(--bs-ink-2)' }}>
            {children}
          </div>
        )}
      </div>
    </aside>
  );
}

// Sticky table of contents (desktop only — collapses below 900px).
function TOC({ items, current, onNav }) {
  return (
    <nav style={{
      position: 'sticky', top: 24, alignSelf: 'flex-start',
      borderLeft: '1px solid var(--bs-line)',
      paddingLeft: 18, paddingTop: 2,
    }} aria-label="Зміст сторінки">
      <div style={{
        fontFamily: '"JetBrains Mono", ui-monospace, monospace',
        fontSize: 11, fontWeight: 500, letterSpacing: '0.14em',
        color: 'var(--bs-ink-3)', textTransform: 'uppercase', marginBottom: 14,
      }}>На цій сторінці</div>
      <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 10 }}>
        {items.map((it) => (
          <li key={it.id}>
            <a href={`#${it.id}`} onClick={(e) => { if (onNav) { e.preventDefault(); onNav(it.id); } }}
              style={{
                fontSize: 13.5,
                color: current === it.id ? 'var(--bs-ink)' : 'var(--bs-ink-3)',
                fontWeight: current === it.id ? 700 : 500,
                textDecoration: 'none', lineHeight: 1.45,
                display: 'block', borderLeft: current === it.id ? '2px solid var(--bs-blue)' : '2px solid transparent',
                paddingLeft: 12, marginLeft: -20,
            }}>{it.label}</a>
          </li>
        ))}
      </ul>
    </nav>
  );
}

// Two-column layout: content + TOC.
function ContentLayout({ children, toc }) {
  return (
    <div style={{
      display: 'grid', gridTemplateColumns: '1fr 220px', gap: 56,
      alignItems: 'flex-start',
    }}>
      <div style={{ minWidth: 0 }}>{children}</div>
      {toc && <div>{toc}</div>}
    </div>
  );
}

// Stat row — compact list of bold facts (used on Про нас).
function StatRow({ stats }) {
  return (
    <div style={{
      display: 'grid', gridTemplateColumns: `repeat(${stats.length}, 1fr)`, gap: 0,
      background: '#fff', border: '1px solid var(--bs-line)',
      borderRadius: 'var(--bs-r)', overflow: 'hidden',
      margin: '20px 0 28px',
    }}>
      {stats.map((s, i) => (
        <div key={i} style={{
          padding: '20px 22px',
          borderLeft: i > 0 ? '1px solid var(--bs-line-2)' : 'none',
        }}>
          <div style={{
            fontSize: 28, fontWeight: 800, color: 'var(--bs-ink)',
            letterSpacing: '-0.02em', lineHeight: 1,
          }}>{s.value}</div>
          <div style={{
            fontSize: 12.5, color: 'var(--bs-ink-3)', marginTop: 8, lineHeight: 1.45,
          }}>{s.label}</div>
        </div>
      ))}
    </div>
  );
}

// Telegram CTA card — the universal "ще питання?" footer used on every page.
function TelegramCard() {
  return (
    <section className="bs-card" style={{
      padding: '24px 28px', marginTop: 40,
      background: 'var(--bs-blue-soft)', border: '1px solid #c7d2fe',
      display: 'grid', gridTemplateColumns: '40px 1fr auto', gap: 16, alignItems: 'center',
    }}>
      <div style={{
        width: 40, height: 40, background: '#229ED9', color: '#fff',
        borderRadius: 'var(--bs-r-sm)', display: 'flex',
        alignItems: 'center', justifyContent: 'center',
      }}>
        <I.Tg width="20" height="20" />
      </div>
      <div>
        <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--bs-ink)' }}>
          Залишилось питання?
        </div>
        <div style={{ fontSize: 13.5, color: 'var(--bs-ink-2)', marginTop: 2 }}>
          Найшвидше відповідаємо в Telegram: <strong>@boostershop_tcg</strong>
        </div>
      </div>
      <a className="bs-btn bs-btn-blue-outline" href="#" style={{ fontSize: 13.5 }}>
        Відкрити Telegram →
      </a>
    </section>
  );
}

// Sub-page tab nav for the mockup app — lets the user switch between the 6
// pages without scrolling. Sticky top, scrolls horizontally on mobile.
function PageTabs({ pages, active, onSelect }) {
  return (
    <nav style={{
      position: 'sticky', top: 0, zIndex: 5,
      background: 'rgba(247,247,245,0.92)',
      backdropFilter: 'blur(6px)',
      borderBottom: '1px solid var(--bs-line)',
      padding: '12px 32px',
      display: 'flex', alignItems: 'center', gap: 6, overflowX: 'auto',
    }}>
      <span style={{
        fontFamily: '"JetBrains Mono", ui-monospace, monospace',
        fontSize: 11, color: 'var(--bs-ink-3)', textTransform: 'uppercase',
        letterSpacing: '0.14em', marginRight: 12, flex: '0 0 auto',
      }}>Контент</span>
      {pages.map((p) => (
        <button key={p.id} onClick={() => onSelect(p.id)}
          style={{
            flex: '0 0 auto',
            border: 0, background: active === p.id ? 'var(--bs-ink)' : 'transparent',
            color: active === p.id ? '#fff' : 'var(--bs-ink-2)',
            padding: '7px 14px', borderRadius: 'var(--bs-r-pill)',
            fontSize: 13, fontWeight: 600, cursor: 'pointer', fontFamily: 'inherit',
            whiteSpace: 'nowrap',
          }}>
          {p.label}
        </button>
      ))}
    </nav>
  );
}

// Page frame — recreates a slim browser chrome around each page so the user
// reads them as discrete pages (not one long doc). Header from header-variants
// + breadcrumb inside main + content.
function PageShell({ children }) {
  return (
    <div className="bs-mock" style={{ background: 'var(--bs-bg)' }}>
      <HeaderV1 />
      <main style={{
        maxWidth: 1180, margin: '0 auto', padding: '28px 32px 64px',
      }}>
        {children}
      </main>
    </div>
  );
}

Object.assign(window, {
  C, Eyebrow, Crumbs, Hero, H2, P, Def, Section, Callout, TOC,
  ContentLayout, StatRow, TelegramCard, PageTabs, PageShell,
});
