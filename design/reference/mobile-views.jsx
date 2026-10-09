// Mobile views — selected critical flows shown inside a simple phone frame.
// Just structural mockups (390-wide), not a full ios bezel — we're showing
// layout/UX, not chrome.

function Phone({ children, height = 720 }) {
  return (
    <div style={{
      width: 390, height,
      background: '#fff',
      border: '8px solid #1a1d22',
      borderRadius: 38,
      boxShadow: '0 18px 48px rgba(17,24,39,0.18)',
      overflow: 'hidden',
      margin: '0 auto',
      display: 'flex', flexDirection: 'column',
      fontFamily: '"Manrope", system-ui, sans-serif',
    }}>
      {/* status bar */}
      <div style={{
        flex: '0 0 28px',
        background: '#fff',
        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
        padding: '0 22px',
        fontSize: 12, fontWeight: 700, color: 'var(--bs-ink)',
      }}>
        <span>9:41</span>
        <span style={{ fontSize: 10 }}>● ● ●</span>
      </div>
      <div style={{ flex: 1, overflow: 'auto', background: 'var(--bs-bg)' }}>{children}</div>
    </div>
  );
}

// Mobile header — compact, no gold band, hamburger left, search expandable.
function MobileHeader({ small }) {
  return (
    <header style={{
      background: '#fff', borderBottom: '1px solid var(--bs-line)',
      padding: '10px 14px',
      display: 'flex', alignItems: 'center', gap: 10,
      position: 'sticky', top: 0, zIndex: 5,
    }}>
      <button style={menuBtn}><I.Filter width="16" height="16" /></button>
      <Logo size={16} />
      <span style={{ flex: 1 }} />
      <button style={menuBtn}><I.Search width="16" height="16" /></button>
      <button style={{
        ...menuBtn,
        background: 'var(--bs-green)', color: '#fff',
        width: 'auto', padding: '0 12px', gap: 6,
      }}>
        <I.Cart width="14" height="14" />
        <span style={{ fontSize: 12, fontWeight: 700 }}>2</span>
      </button>
    </header>
  );
}
const menuBtn = {
  width: 34, height: 34, borderRadius: 'var(--bs-r-sm)',
  background: 'var(--bs-bg)', border: 'none',
  color: 'var(--bs-ink-2)', cursor: 'pointer',
  display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
};

// Mobile category — subcategory chips wrap, filter sheet trigger.
function MobileCategoryPage() {
  return (
    <>
      <MobileHeader />
      <nav style={{
        display: 'flex', alignItems: 'center', gap: 6,
        padding: '10px 14px', fontSize: 12, color: 'var(--bs-ink-3)',
      }}>
        <I.Home width="11" height="11" />
        <span>›</span>
        <span style={{ color: 'var(--bs-ink)' }}>Pokémon TCG</span>
      </nav>
      <div style={{ padding: '0 14px 24px' }}>
        <h1 style={{ fontSize: 22, marginBottom: 4 }}>Pokémon TCG</h1>
        <div style={{ fontSize: 12.5, color: 'var(--bs-ink-3)', marginBottom: 14 }}>24 товари</div>

        {/* Chips */}
        <div style={{ display: 'flex', gap: 6, marginBottom: 14, overflowX: 'auto', paddingBottom: 4 }}>
          {['Бустери', 'Бустер-бокси', 'Набори'].map((c, i) => (
            <a key={c} href="#" style={{
              display: 'inline-flex', padding: '7px 12px',
              background: '#fff', border: '1px solid var(--bs-line)',
              borderRadius: 999, fontSize: 12.5, fontWeight: 600,
              color: 'var(--bs-ink-2)', whiteSpace: 'nowrap',
            }}>{c}</a>
          ))}
        </div>

        {/* Filter + sort */}
        <div style={{ display: 'flex', gap: 8, marginBottom: 14 }}>
          <button style={{
            flex: 1, padding: '10px 12px',
            background: '#fff', border: '1px solid var(--bs-line)',
            borderRadius: 'var(--bs-r-sm)',
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
            gap: 8, fontSize: 13, fontWeight: 600, color: 'var(--bs-ink)',
          }}>
            <I.Filter width="14" height="14" /> Фільтри
            <span style={{
              background: 'var(--bs-blue)', color: '#fff',
              fontSize: 10, fontWeight: 700, padding: '1px 6px', borderRadius: 999, marginLeft: 4,
            }}>1</span>
          </button>
          <button style={{
            flex: 1, padding: '10px 12px',
            background: '#fff', border: '1px solid var(--bs-line)',
            borderRadius: 'var(--bs-r-sm)',
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
            gap: 8, fontSize: 13, fontWeight: 600, color: 'var(--bs-ink)',
          }}>
            Сортувати <I.Chevron width="10" height="10" />
          </button>
        </div>

        {/* Product grid 2-col */}
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
          {PRODUCTS.slice(0, 6).map(p => (
            <ProductCard key={p.id} product={p} />
          ))}
        </div>
      </div>
    </>
  );
}

// Mobile product — gallery, title, qty, sticky add-to-cart.
function MobileProductPage() {
  return (
    <>
      <MobileHeader />
      <div style={{ paddingBottom: 84 /* room for sticky CTA */ }}>
        <div style={{ padding: 14 }}>
          <div className="bs-card" style={{ padding: 12 }}>
            <ImagePh ratio="1/1" radius="var(--bs-r-sm)" label="Pokémon TCG photo" />
          </div>
          <div style={{ display: 'flex', gap: 8, marginTop: 10, overflowX: 'auto' }}>
            {[0,1,2,3].map(i => (
              <div key={i} style={{
                width: 56, height: 56, flex: '0 0 56px',
                border: `1.5px solid ${i === 0 ? 'var(--bs-blue)' : 'var(--bs-line)'}`,
                borderRadius: 'var(--bs-r-sm)', padding: 3, background: '#fff',
              }}>
                <ImagePh ratio="1/1" radius="4px" label="" />
              </div>
            ))}
          </div>
        </div>

        <div style={{ padding: '0 14px' }}>
          <div style={{
            fontFamily: '"JetBrains Mono", ui-monospace, monospace',
            fontSize: 10, letterSpacing: '.14em', color: 'var(--bs-ink-3)',
            textTransform: 'uppercase', marginBottom: 6,
          }}>Pokémon TCG · JP</div>
          <h2 style={{ fontSize: 18, lineHeight: 1.25, marginBottom: 8 }}>
            Бустер Pokémon TCG: Mega Brave (Японське видання)
          </h2>
          <div style={{
            display: 'inline-flex', alignItems: 'center', gap: 6,
            fontSize: 12, color: 'var(--bs-green)', fontWeight: 600,
            marginBottom: 14,
          }}>
            <span style={{ width: 6, height: 6, borderRadius: '50%', background: 'var(--bs-green)' }} />
            В наявності · 6 шт.
          </div>

          <div style={{ padding: '14px 0', borderBlock: '1px solid var(--bs-line)' }}>
            <PriceRow price={150} size="lg" />
          </div>

          {/* Trust line */}
          <div style={{
            display: 'flex', gap: 8, padding: '12px 0', flexWrap: 'wrap',
            fontSize: 11.5, color: 'var(--bs-ink-2)',
          }}>
            {[
              { ic: <I.Pack width="12" height="12" />, t: 'Sealed з box/case' },
              { ic: <I.Shield width="12" height="12" />, t: 'Не зважуємо' },
              { ic: <I.Truck width="12" height="12" />, t: '~3 дні НП' },
            ].map((x, i) => (
              <span key={i} style={{
                display: 'inline-flex', alignItems: 'center', gap: 5,
                padding: '5px 9px', background: 'var(--bs-bg)',
                borderRadius: 999,
              }}>
                <span style={{ color: 'var(--bs-blue)' }}>{x.ic}</span>{x.t}
              </span>
            ))}
          </div>

          {/* Tabs (collapsed view — just headers) */}
          <div style={{
            display: 'flex', gap: 14, padding: '10px 0',
            borderBottom: '1px solid var(--bs-line)', marginTop: 8,
          }}>
            <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--bs-ink)', borderBottom: '2px solid var(--bs-blue)', paddingBottom: 6 }}>Опис</span>
            <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--bs-ink-3)' }}>Характеристики</span>
            <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--bs-ink-3)' }}>Відгуки</span>
          </div>
          <p style={{ fontSize: 13.5, lineHeight: 1.6, color: 'var(--bs-ink-2)', padding: '14px 0' }}>
            <strong>Mega Brave</strong> — сучасний японський сет Pokémon TCG із лінійки Mega Evolution.
            Sealed-бустер містить 5 карт, формат Japanese Edition…
          </p>
        </div>
      </div>

      {/* Sticky add-to-cart */}
      <div style={{
        position: 'absolute', left: 8, right: 8, bottom: 8,
        background: '#fff', border: '1px solid var(--bs-line)',
        borderRadius: 'var(--bs-r)',
        padding: 10, display: 'flex', gap: 10, alignItems: 'center',
        boxShadow: 'var(--bs-sh-pop)',
      }}>
        <div style={{
          display: 'inline-flex', alignItems: 'center',
          border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r-sm)',
        }}>
          <button style={qb}><I.Minus width="10" height="10" /></button>
          <span style={{ minWidth: 24, textAlign: 'center', fontWeight: 700, fontSize: 14 }}>1</span>
          <button style={qb}><I.Plus width="10" height="10" /></button>
        </div>
        <button className="bs-btn bs-btn-primary" style={{ flex: 1, padding: '12px', fontSize: 14 }}>
          Додати — ₴150
        </button>
      </div>
    </>
  );
}
const qb = {
  width: 38, height: 38, border: 0, background: 'transparent',
  color: 'var(--bs-ink-2)', cursor: 'pointer',
  display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
};

// Mobile mini-cart drawer — full-height sheet.
function MobileMiniCart() {
  const items = [
    { id: 'sym',  title: 'Бустер Pokémon TCG: Mega Symphonia', price: 150, qty: 2 },
    { id: 'op11', title: 'Бустер One Piece OP-11',              price: 200, qty: 2 },
  ];
  const sub = items.reduce((s, it) => s + it.price * it.qty, 0);
  return (
    <div style={{ height: '100%', display: 'flex', flexDirection: 'column', background: '#fff' }}>
      <header style={{
        padding: '14px 16px',
        borderBottom: '1px solid var(--bs-line)',
        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
      }}>
        <div>
          <div style={{ fontSize: 16, fontWeight: 700, color: 'var(--bs-ink)' }}>Кошик</div>
          <div style={{ fontSize: 11.5, color: 'var(--bs-ink-3)', marginTop: 2 }}>2 товари · 4 шт.</div>
        </div>
        <button style={menuBtn}><I.Close width="12" height="12" /></button>
      </header>
      <div style={{ flex: 1, overflowY: 'auto', padding: '0 16px' }}>
        {items.map(it => (
          <div key={it.id} style={{
            display: 'grid', gridTemplateColumns: '56px 1fr auto', gap: 10,
            padding: '14px 0', borderBottom: '1px solid var(--bs-line-2)',
            alignItems: 'flex-start',
          }}>
            <div style={{
              width: 56, height: 56, borderRadius: 'var(--bs-r-sm)',
              background: '#fff', border: '1px solid var(--bs-line)', overflow: 'hidden',
            }}>
              <ImagePh ratio="1/1" radius="var(--bs-r-sm)" label="" />
            </div>
            <div>
              <div style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--bs-ink)', lineHeight: 1.4 }}>{it.title}</div>
              <div style={{
                display: 'inline-flex', alignItems: 'center', marginTop: 8,
                border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r-sm)',
              }}>
                <button style={qb}><I.Minus width="10" height="10" /></button>
                <span style={{ minWidth: 22, textAlign: 'center', fontWeight: 600, fontSize: 12 }}>{it.qty}</span>
                <button style={qb}><I.Plus width="10" height="10" /></button>
              </div>
            </div>
            <div style={{ textAlign: 'right', fontSize: 13.5, fontWeight: 700, color: 'var(--bs-ink)' }}>
              ₴{it.price * it.qty}
            </div>
          </div>
        ))}
        <div style={{
          marginTop: 12, padding: '10px 12px',
          background: 'var(--bs-blue-soft)', borderRadius: 'var(--bs-r-sm)',
          display: 'flex', alignItems: 'center', gap: 8,
        }}>
          <I.Truck width="14" height="14" style={{ color: 'var(--bs-blue)' }} />
          <span style={{ fontSize: 12, color: 'var(--bs-blue)', fontWeight: 600 }}>
            До безкоштовної доставки лишилось ₴{Math.max(0, 1500 - sub)}
          </span>
        </div>
      </div>
      <footer style={{
        padding: '14px 16px',
        borderTop: '1px solid var(--bs-line)',
        display: 'flex', flexDirection: 'column', gap: 10,
      }}>
        <div style={{
          display: 'flex', justifyContent: 'space-between',
          fontSize: 16, fontWeight: 800, color: 'var(--bs-ink)',
        }}>
          <span>До сплати</span><span>₴{sub}</span>
        </div>
        <button className="bs-btn bs-btn-primary" style={{ padding: '12px', fontSize: 14 }}>
          Оформити замовлення →
        </button>
      </footer>
    </div>
  );
}

Object.assign(window, { Phone, MobileHeader, MobileCategoryPage, MobileProductPage, MobileMiniCart });
