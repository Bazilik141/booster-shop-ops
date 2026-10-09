// Four fresh FAQ accordion variants. All share the same Collapse primitive
// and the same content; the difference is purely structural / visual. They
// all explicitly DO NOT use a beige header block (which is what caused the
// large top margin on the previously-approved version).
//
// Sizing notes: each variant is built to live inside the product description
// column on a real product page (~720px wide). Question text caps at ~16px
// and never goes below 14.5px body, per the design system type ramp.

const { useState: useStateV2, useRef: useRefV2, useEffect: useEffectV2 } = React;

// ---------------------------------------------------------------------------
// Shared Collapse — smooth height transition, measured via ResizeObserver.
// ---------------------------------------------------------------------------
function CollapseV2({ open, children, duration = 260 }) {
  const ref = useRefV2(null);
  const [h, setH] = useStateV2(0);
  useEffectV2(() => {
    if (!ref.current) return;
    const m = () => setH(ref.current.scrollHeight);
    m();
    const ro = new ResizeObserver(m);
    ro.observe(ref.current);
    return () => ro.disconnect();
  }, [children]);
  return (
    <div style={{
      overflow: 'hidden',
      height: open ? h : 0,
      transition: `height ${duration}ms cubic-bezier(.2,.7,.2,1)`,
    }}>
      <div ref={ref}>{children}</div>
    </div>
  );
}

function useOpenSetV2(initial = []) {
  const [open, setOpen] = useStateV2(() => new Set(initial));
  const toggle = (i) => setOpen((p) => {
    const n = new Set(p); n.has(i) ? n.delete(i) : n.add(i); return n;
  });
  return [open, toggle];
}

// ---------------------------------------------------------------------------
// VARIANT A — "Quiet hairlines" (refined original)
// Same DNA as the approved one, but the FAQ title is plain text on the page
// background — no beige container. That kills the "ugly large top padding"
// the user pointed out. Hairline rows, animated chevron, gold-on-open.
// ---------------------------------------------------------------------------
function FaqV2_A({ items, initialOpen = [0] }) {
  const [open, toggle] = useOpenSetV2(initialOpen);
  return (
    <section style={{ background: '#fff' }}>
      <h3 style={{
        margin: '0 0 8px',
        fontSize: 22, fontWeight: 700, letterSpacing: '-0.01em',
        color: 'var(--bs-ink)',
      }}>
        Часті питання
      </h3>
      <div style={{ borderTop: '1px solid var(--bs-line)' }}>
        {items.map((it, i) => {
          const isOpen = open.has(i);
          return (
            <div key={i} style={{ borderBottom: '1px solid var(--bs-line)' }}>
              <button
                onClick={() => toggle(i)}
                aria-expanded={isOpen}
                style={{
                  width: '100%', background: 'transparent', border: 0,
                  padding: '18px 4px', cursor: 'pointer',
                  display: 'flex', alignItems: 'center', gap: 16,
                  textAlign: 'left', font: 'inherit', color: 'var(--bs-ink)',
                }}
              >
                <span style={{
                  flex: 1, fontSize: 15.5, fontWeight: 600, lineHeight: 1.4,
                }}>{it.q}</span>
                <span style={{
                  width: 24, height: 24,
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  color: isOpen ? 'var(--bs-gold)' : 'var(--bs-ink-3)',
                  transform: `rotate(${isOpen ? 180 : 0}deg)`,
                  transition: 'transform .28s cubic-bezier(.2,.7,.2,1), color .2s',
                }}>
                  <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M3 5l4 4 4-4" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"/>
                  </svg>
                </span>
              </button>
              <CollapseV2 open={isOpen}>
                <div style={{
                  padding: '0 56px 20px 4px',
                  fontSize: 14.5, lineHeight: 1.65, color: 'var(--bs-ink-2)',
                }}>{it.a}</div>
              </CollapseV2>
            </div>
          );
        })}
      </div>
    </section>
  );
}

// ---------------------------------------------------------------------------
// VARIANT B — "Numbered editorial"
// Monospace counter (01, 02…) anchors each row like an editorial index.
// Communicates "this is a curated list, not a wall of bold text". Reads
// premium without using a single gradient.
// ---------------------------------------------------------------------------
function FaqV2_B({ items }) {
  const [open, toggle] = useOpenSetV2([0]);
  return (
    <section style={{ background: '#fff' }}>
      <div style={{
        display: 'flex', alignItems: 'baseline', gap: 12,
        marginBottom: 12,
      }}>
        <span style={{
          fontFamily: "'JetBrains Mono', ui-monospace, monospace",
          fontSize: 10.5, fontWeight: 500, letterSpacing: '.16em',
          color: 'var(--bs-ink-3)', textTransform: 'uppercase',
        }}>
          FAQ — {String(items.length).padStart(2, '0')} ПИТАНЬ
        </span>
        <span style={{ flex: 1, height: 1, background: 'var(--bs-line)' }} />
      </div>

      <div>
        {items.map((it, i) => {
          const isOpen = open.has(i);
          return (
            <div key={i} style={{ borderBottom: '1px solid var(--bs-line-2)' }}>
              <button
                onClick={() => toggle(i)}
                aria-expanded={isOpen}
                style={{
                  width: '100%', background: 'transparent', border: 0,
                  padding: '20px 0', cursor: 'pointer',
                  display: 'grid',
                  gridTemplateColumns: '44px 1fr auto',
                  alignItems: 'baseline', gap: 16,
                  textAlign: 'left', font: 'inherit', color: 'var(--bs-ink)',
                }}
              >
                <span style={{
                  fontFamily: "'JetBrains Mono', ui-monospace, monospace",
                  fontSize: 12, fontWeight: 500, letterSpacing: '.06em',
                  color: isOpen ? 'var(--bs-gold)' : 'var(--bs-ink-4)',
                  transition: 'color .2s',
                }}>{String(i + 1).padStart(2, '0')}</span>

                <span style={{
                  fontSize: 16, fontWeight: 600, lineHeight: 1.4,
                  letterSpacing: '-0.005em',
                }}>{it.q}</span>

                <span style={{
                  width: 28, height: 28, alignSelf: 'center',
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  color: isOpen ? 'var(--bs-ink)' : 'var(--bs-ink-4)',
                  transition: 'color .2s',
                  fontFamily: "'JetBrains Mono', ui-monospace, monospace",
                  fontSize: 18, fontWeight: 400,
                }}>{isOpen ? '−' : '+'}</span>
              </button>
              <CollapseV2 open={isOpen}>
                <div style={{
                  padding: '0 44px 22px 60px',
                  fontSize: 14.5, lineHeight: 1.7, color: 'var(--bs-ink-2)',
                }}>{it.a}</div>
              </CollapseV2>
            </div>
          );
        })}
      </div>
    </section>
  );
}

// ---------------------------------------------------------------------------
// VARIANT C — "Q · A prefixes" (knowledge-base style)
// Each question is prefixed with a small "Q" pill in brand ink; the answer
// gets an "A" pill in gold. No card chrome at all — feels like a clean
// reference doc. Works equally well on Q-only state (handles the broken
// Mega Dream case gracefully — empty answer renders as "відповідь готується").
// ---------------------------------------------------------------------------
function FaqV2_C({ items }) {
  const [open, toggle] = useOpenSetV2([0]);
  const pill = (label, color, bg) => (
    <span style={{
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      width: 22, height: 22, borderRadius: 6,
      background: bg, color,
      fontFamily: "'JetBrains Mono', ui-monospace, monospace",
      fontSize: 11, fontWeight: 700, letterSpacing: 0,
      flex: '0 0 auto',
    }}>{label}</span>
  );
  return (
    <section style={{ background: '#fff' }}>
      <h3 style={{
        margin: '0 0 14px',
        fontSize: 22, fontWeight: 700, letterSpacing: '-0.01em',
        color: 'var(--bs-ink)',
      }}>
        Часті питання
      </h3>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
        {items.map((it, i) => {
          const isOpen = open.has(i);
          return (
            <div key={i} style={{
              borderRadius: 10,
              background: isOpen ? 'var(--bs-bg)' : 'transparent',
              transition: 'background .2s',
            }}>
              <button
                onClick={() => toggle(i)}
                aria-expanded={isOpen}
                style={{
                  width: '100%', background: 'transparent', border: 0,
                  padding: '14px 14px 14px 12px', cursor: 'pointer',
                  display: 'flex', alignItems: 'center', gap: 12,
                  textAlign: 'left', font: 'inherit', color: 'var(--bs-ink)',
                }}
              >
                {pill('Q', '#fff', 'var(--bs-ink)')}
                <span style={{
                  flex: 1, fontSize: 15.5, fontWeight: 600, lineHeight: 1.4,
                }}>{it.q}</span>
                <span style={{
                  width: 20, height: 20,
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  color: 'var(--bs-ink-3)',
                  transform: `rotate(${isOpen ? 180 : 0}deg)`,
                  transition: 'transform .28s cubic-bezier(.2,.7,.2,1)',
                }}>
                  <svg width="12" height="12" viewBox="0 0 14 14" fill="none">
                    <path d="M3 5l4 4 4-4" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"/>
                  </svg>
                </span>
              </button>
              <CollapseV2 open={isOpen}>
                <div style={{
                  padding: '0 14px 16px 12px',
                  display: 'flex', alignItems: 'flex-start', gap: 12,
                }}>
                  {pill('A', '#fff', 'var(--bs-gold)')}
                  <div style={{
                    flex: 1, paddingTop: 1,
                    fontSize: 14.5, lineHeight: 1.65, color: 'var(--bs-ink-2)',
                  }}>
                    {it.a || (
                      <span style={{ color: 'var(--bs-ink-3)', fontStyle: 'italic' }}>
                        Відповідь у підготовці.
                      </span>
                    )}
                  </div>
                </div>
              </CollapseV2>
            </div>
          );
        })}
      </div>
    </section>
  );
}

// ---------------------------------------------------------------------------
// VARIANT D — "Stacked soft cards"
// Each Q/A is its own white card with a soft hairline. Open state lifts the
// card with a subtle shadow and adds a thin gold rail on the LEFT (replacing
// the previously-removed gold flood). Feels object-y, more "modern e-commerce
// premium". Good middle ground between A (flat) and the previous v1 boxed.
// ---------------------------------------------------------------------------
function FaqV2_D({ items }) {
  const [open, toggle] = useOpenSetV2([0]);
  return (
    <section style={{ background: '#fff' }}>
      <h3 style={{
        margin: '0 0 14px',
        fontSize: 22, fontWeight: 700, letterSpacing: '-0.01em',
        color: 'var(--bs-ink)',
      }}>
        Часті питання
      </h3>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
        {items.map((it, i) => {
          const isOpen = open.has(i);
          return (
            <div
              key={i}
              style={{
                position: 'relative',
                background: '#fff',
                border: `1px solid ${isOpen ? '#dfd6c0' : 'var(--bs-line)'}`,
                borderRadius: 10,
                boxShadow: isOpen
                  ? '0 2px 4px rgba(17,24,39,.04), 0 1px 0 rgba(17,24,39,.02)'
                  : '0 1px 0 rgba(17,24,39,.02)',
                transition: 'border-color .2s, box-shadow .2s',
                overflow: 'hidden',
              }}
            >
              <div style={{
                position: 'absolute', left: 0, top: 0, bottom: 0,
                width: 3, background: 'var(--bs-gold)',
                opacity: isOpen ? 1 : 0,
                transition: 'opacity .2s',
              }} />
              <button
                onClick={() => toggle(i)}
                aria-expanded={isOpen}
                style={{
                  width: '100%', background: 'transparent', border: 0,
                  padding: '16px 18px', cursor: 'pointer',
                  display: 'flex', alignItems: 'center', gap: 14,
                  textAlign: 'left', font: 'inherit', color: 'var(--bs-ink)',
                }}
              >
                <span style={{
                  flex: 1, fontSize: 15.5, fontWeight: 600, lineHeight: 1.4,
                }}>{it.q}</span>
                <span style={{
                  width: 26, height: 26, borderRadius: 999,
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  color: isOpen ? 'var(--bs-ink)' : 'var(--bs-ink-3)',
                  background: isOpen ? 'var(--bs-gold-soft)' : 'transparent',
                  transition: 'background .2s, color .2s',
                }}>
                  <svg width="12" height="12" viewBox="0 0 14 14" fill="none"
                    style={{ transform: `rotate(${isOpen ? 45 : 0}deg)`, transition: 'transform .28s cubic-bezier(.2,.7,.2,1)' }}>
                    <path d="M7 3v8M3 7h8" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round"/>
                  </svg>
                </span>
              </button>
              <CollapseV2 open={isOpen}>
                <div style={{
                  padding: '0 56px 18px 18px',
                  fontSize: 14.5, lineHeight: 1.65, color: 'var(--bs-ink-2)',
                }}>{it.a}</div>
              </CollapseV2>
            </div>
          );
        })}
      </div>
    </section>
  );
}

Object.assign(window, { FaqV2_A, FaqV2_B, FaqV2_C, FaqV2_D, CollapseV2, useOpenSetV2 });

// Small wrapper used by the mobile artboard so the second board shows the
// "everything open" state without us cloning the variant.
function FaqV2_A_MobileOpenDemo({ items }) {
  return <FaqV2_A items={items} initialOpen={[0, 2]} />;
}
Object.assign(window, { FaqV2_A_MobileOpenDemo });
