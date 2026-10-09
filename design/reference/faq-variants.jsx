// Three FAQ accordion variants for Booster Shop.
// All three honour the same brief: light, airy, brand-consistent, smooth
// expand animation, multi-open allowed. They differ in *visual structure*
// (no chrome / soft cards / editorial numbered list).

const { useState, useRef, useEffect } = React;

// ---------------------------------------------------------------------------
// Shared animated panel: measures content height for a smooth grid-rows hack
// fallback when CSS interpolate-size isn't available. Multi-open supported.
// ---------------------------------------------------------------------------
function Collapse({ open, children, duration = 280 }) {
  const ref = useRef(null);
  const [h, setH] = useState(0);
  useEffect(() => {
    if (!ref.current) return;
    const measure = () => setH(ref.current.scrollHeight);
    measure();
    const ro = new ResizeObserver(measure);
    ro.observe(ref.current);
    return () => ro.disconnect();
  }, [children]);
  return (
    <div
      style={{
        overflow: 'hidden',
        height: open ? h : 0,
        transition: `height ${duration}ms cubic-bezier(.2,.7,.2,1)`,
      }}
    >
      <div ref={ref}>{children}</div>
    </div>
  );
}

// Hook: which items are open (Set of indexes). Multi-open by default.
function useOpenSet(initial = []) {
  const [open, setOpen] = useState(() => new Set(initial));
  const toggle = (i) => {
    setOpen((prev) => {
      const next = new Set(prev);
      if (next.has(i)) next.delete(i); else next.add(i);
      return next;
    });
  };
  return [open, toggle];
}

// ---------------------------------------------------------------------------
// VARIANT A — "Hairline" — quietest. No card chrome. Hairline rules between
// rows, generous vertical padding, animated chevron. Reads like an editorial
// FAQ, doesn't fight the rest of the page.
// ---------------------------------------------------------------------------
function FaqVariantA({ items }) {
  const [open, toggle] = useOpenSet([0]);
  return (
    <section style={{ marginTop: 28 }}>
      <header style={{ marginBottom: 14 }}>
        <div style={{
          fontFamily: "'JetBrains Mono', ui-monospace, monospace",
          fontSize: 11, fontWeight: 500, letterSpacing: '.14em',
          color: 'var(--bs-gold)', textTransform: 'uppercase',
          marginBottom: 6,
        }}>FAQ · Pokémon TCG</div>
        <h3 style={{
          margin: 0, fontSize: 26, fontWeight: 700, letterSpacing: '-0.01em',
          color: 'var(--bs-ink)',
        }}>
          Часті питання про бустери
        </h3>
      </header>

      <div style={{ borderTop: '1px solid var(--bs-line)' }}>
        {items.map((item, i) => {
          const isOpen = open.has(i);
          return (
            <div key={i} style={{ borderBottom: '1px solid var(--bs-line)' }}>
              <button
                onClick={() => toggle(i)}
                style={{
                  width: '100%', background: 'transparent', border: 0,
                  padding: '20px 4px', cursor: 'pointer',
                  display: 'flex', alignItems: 'center', gap: 16,
                  textAlign: 'left', font: 'inherit',
                  color: 'var(--bs-ink)',
                }}
              >
                <span style={{
                  flex: 1, fontSize: 16, fontWeight: 600, lineHeight: 1.4,
                  letterSpacing: '-0.005em',
                }}>
                  {item.q}
                </span>
                <span style={{
                  flex: '0 0 auto', width: 28, height: 28,
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
              <Collapse open={isOpen}>
                <div style={{
                  padding: '0 56px 22px 4px',
                  fontSize: 14.5, lineHeight: 1.65,
                  color: 'var(--bs-ink-2)',
                }}>
                  {item.a}
                </div>
              </Collapse>
            </div>
          );
        })}
      </div>
    </section>
  );
}

// ---------------------------------------------------------------------------
// VARIANT B — "Soft cards" — each Q/A is its own rounded card. Restrained:
// white stays white when open, only a thin BLUE border signals "open". The
// + → × icon picks up brand blue on open. No left rail (per feedback — the
// previous gold bar looked heavy when the row was active). Per hybrid color
// strategy, FAQ uses system blue accent, not category gold.
// ---------------------------------------------------------------------------
function FaqVariantB({ items }) {
  const [open, toggle] = useOpenSet([0]);
  return (
    <section style={{ marginTop: 28 }}>
      <header style={{
        display: 'flex', alignItems: 'baseline', gap: 14,
        marginBottom: 16,
      }}>
        <h3 style={{
          margin: 0, fontSize: 26, fontWeight: 700, letterSpacing: '-0.01em',
          color: 'var(--bs-ink)',
        }}>
          Часті питання
        </h3>
        <span style={{
          fontSize: 13, color: 'var(--bs-ink-3)', fontWeight: 500,
        }}>
          {items.length} відповідей про бустери Pokémon TCG
        </span>
      </header>

      <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
        {items.map((item, i) => {
          const isOpen = open.has(i);
          return (
            <div
              key={i}
              style={{
                position: 'relative',
                background: '#fff',
                border: `1px solid ${isOpen ? 'var(--bs-blue)' : 'var(--bs-line)'}`,
                borderRadius: 12,
                transition: 'border-color .2s, box-shadow .2s',
                boxShadow: isOpen
                  ? '0 1px 0 rgba(30,58,138,.06)'
                  : '0 1px 0 rgba(17,24,39,0.02)',
                overflow: 'hidden',
              }}
            >
              <button
                onClick={() => toggle(i)}
                style={{
                  width: '100%', background: 'transparent', border: 0,
                  padding: '18px 20px', cursor: 'pointer',
                  display: 'flex', alignItems: 'center', gap: 16,
                  textAlign: 'left', font: 'inherit',
                  color: 'var(--bs-ink)',
                }}
              >
                <span style={{
                  flex: 1, fontSize: 15.5, fontWeight: 600, lineHeight: 1.45,
                  letterSpacing: '-0.005em',
                }}>
                  {item.q}
                </span>
                <span style={{
                  flex: '0 0 auto', width: 30, height: 30,
                  display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                  color: isOpen ? 'var(--bs-blue)' : 'var(--bs-ink-3)',
                  transition: 'color .2s, transform .3s',
                  transform: `rotate(${isOpen ? 180 : 0}deg)`,
                }}>
                  <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M3 5l4 4 4-4" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"/>
                  </svg>
                </span>
              </button>
              <Collapse open={isOpen}>
                <div style={{
                  padding: '0 64px 20px 20px',
                  fontSize: 14.5, lineHeight: 1.65, color: 'var(--bs-ink-2)',
                  borderTop: '1px solid var(--bs-line-2)',
                  marginTop: 0, paddingTop: 16,
                }}>
                  {item.a}
                </div>
              </Collapse>
            </div>
          );
        })}
      </div>
    </section>
  );
}

// ---------------------------------------------------------------------------
// VARIANT C — "Boxed hairlines" — hybrid of A and B. A single rounded card
// (B's containment, used here as a quiet trust-frame) holds A's hairline
// rows. Section header sits inside the card. No gold floods, just one
// chevron-coloured-on-open accent — fits Muji-style trust UI for sealed
// product retailers.
// ---------------------------------------------------------------------------
function FaqVariantC({ items }) {
  const [open, toggle] = useOpenSet([0]);
  return (
    <section style={{ marginTop: 28 }}>
      <div style={{
        background: '#fff',
        border: '1px solid var(--bs-line)',
        borderRadius: 14,
        overflow: 'hidden',
        boxShadow: '0 1px 0 rgba(17,24,39,0.02)',
      }}>
        <header style={{
          padding: '20px 24px 18px',
          borderBottom: '1px solid var(--bs-line)',
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          gap: 16,
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <span style={{
              width: 6, height: 6, borderRadius: '50%',
              background: 'var(--bs-gold)', flex: '0 0 auto',
            }} />
            <h3 style={{
              margin: 0, fontSize: 17, fontWeight: 700,
              letterSpacing: '-0.005em', color: 'var(--bs-ink)',
            }}>
              Часті питання — Pokémon TCG
            </h3>
          </div>
          <span style={{
            fontFamily: "'JetBrains Mono', ui-monospace, monospace",
            fontSize: 11, color: 'var(--bs-ink-3)',
            letterSpacing: '.06em',
          }}>
            {String(items.length).padStart(2, '0')} ПИТАНЬ
          </span>
        </header>

        <div>
          {items.map((item, i) => {
            const isOpen = open.has(i);
            return (
              <div
                key={i}
                style={{
                  borderTop: i === 0 ? 'none' : '1px solid var(--bs-line)',
                  background: isOpen ? 'var(--bs-bg)' : '#fff',
                  transition: 'background .2s',
                }}
              >
                <button
                  onClick={() => toggle(i)}
                  style={{
                    width: '100%', background: 'transparent', border: 0,
                    padding: '18px 24px', cursor: 'pointer',
                    display: 'flex', alignItems: 'center', gap: 16,
                    textAlign: 'left', font: 'inherit',
                    color: 'var(--bs-ink)',
                  }}
                >
                  <span style={{
                    flex: 1, fontSize: 15.5, fontWeight: 600, lineHeight: 1.45,
                    letterSpacing: '-0.005em',
                  }}>
                    {item.q}
                  </span>
                  <span style={{
                    flex: '0 0 auto', width: 28, height: 28,
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
                <Collapse open={isOpen}>
                  <div style={{
                    padding: '0 64px 22px 24px',
                    fontSize: 14.5, lineHeight: 1.65,
                    color: 'var(--bs-ink-2)',
                  }}>
                    {item.a}
                  </div>
                </Collapse>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}

Object.assign(window, { FaqVariantA, FaqVariantB, FaqVariantC, Collapse });
