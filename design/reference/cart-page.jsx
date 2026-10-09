// Full cart page mockup (desktop). Replaces the live table layout with a
// card-based item list on the left and an order summary panel on the right.
// Empty state shown as a tweak inside the canvas; this artboard shows the
// happy path.

function CartLine({ item, onQty, onRemove }) {
  return (
    <div className="bs-card" style={{
      display: 'grid', gridTemplateColumns: '92px 1fr auto auto', gap: 18,
      alignItems: 'center',
      padding: 14,
    }}>
      <div style={{
        width: 92, height: 92, borderRadius: 'var(--bs-r-sm)',
        background: '#fff', border: '1px solid var(--bs-line)', overflow: 'hidden',
      }}>
        <ImagePh ratio="1/1" radius="var(--bs-r-sm)" label="" />
      </div>

      <div style={{ display: 'flex', flexDirection: 'column', gap: 6, minWidth: 0 }}>
        <a href="#" style={{
          fontSize: 14.5, fontWeight: 600, color: 'var(--bs-ink)',
          lineHeight: 1.4, textDecoration: 'none',
        }}>{item.title}</a>
        <div style={{ fontSize: 12, color: 'var(--bs-ink-3)' }}>
          Артикул: {item.sku} · Японське видання · Sealed
        </div>
        <div style={{ marginTop: 4 }}>
          <button onClick={onRemove} style={{
            background: 'transparent', border: 0, padding: 0,
            color: 'var(--bs-ink-3)', cursor: 'pointer', fontSize: 12,
            textDecoration: 'underline', textUnderlineOffset: 3,
          }}>Видалити</button>
        </div>
      </div>

      <QtyInput value={item.qty} onChange={onQty} />

      <div style={{ textAlign: 'right', minWidth: 90 }}>
        <div style={{ fontSize: 16, fontWeight: 800, color: 'var(--bs-ink)' }}>
          ₴{item.price * item.qty}
        </div>
        <div style={{ fontSize: 12, color: 'var(--bs-ink-3)', marginTop: 2 }}>
          ₴{item.price} × {item.qty}
        </div>
      </div>
    </div>
  );
}

function CartPageMock() {
  const [items, setItems] = React.useState([
    { id: 'sym',  title: 'Бустер Pokémon TCG: Mega Symphonia (Японське видання)', sku: 'PKM-MS-JP-001', price: 150, qty: 2 },
    { id: 'op11', title: 'Бустер One Piece Card Game OP-11 (Японське видання)',    sku: 'OP-OP11-JP',   price: 200, qty: 2 },
  ]);
  const [promo, setPromo] = React.useState('');

  const sub = items.reduce((s, it) => s + it.price * it.qty, 0);
  const freeFrom = 1500;
  const toFree = Math.max(0, freeFrom - sub);
  const freeProgress = Math.min(100, (sub / freeFrom) * 100);

  return (
    <div className="bs-mock">
      <HeaderV1 />

      <main style={{ maxWidth: 1240, margin: '0 auto', padding: '20px 32px 56px' }}>
        {/* Breadcrumbs */}
        <nav style={{
          display: 'flex', alignItems: 'center', gap: 8,
          fontSize: 12.5, color: 'var(--bs-ink-3)', marginBottom: 14,
        }}>
          <a href="#" style={{ color: 'var(--bs-ink-3)', display: 'inline-flex' }}><I.Home width="12" height="12" /></a>
          <span>›</span>
          <span style={{ color: 'var(--bs-ink)' }}>Мій кошик</span>
        </nav>

        <header style={{ marginBottom: 22, display: 'flex', alignItems: 'baseline', gap: 12 }}>
          <h1>Мій кошик</h1>
          <span style={{ fontSize: 14, color: 'var(--bs-ink-3)', fontWeight: 500 }}>
            {items.length} товари · {items.reduce((s, it) => s + it.qty, 0)} шт.
          </span>
        </header>

        <div style={{ display: 'grid', gridTemplateColumns: '1.6fr 1fr', gap: 24, alignItems: 'flex-start' }}>
          <section style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            {items.map(it => (
              <CartLine
                key={it.id} item={it}
                onQty={(v) => setItems(items.map(x => x.id === it.id ? { ...x, qty: v } : x))}
                onRemove={() => setItems(items.filter(x => x.id !== it.id))}
              />
            ))}

            <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 8 }}>
              <a href="#" className="bs-btn bs-btn-ghost" style={{ padding: '10px 14px' }}>
                ← Продовжити покупки
              </a>
              <button className="bs-btn bs-btn-ghost" style={{ color: 'var(--bs-ink-3)' }}>
                Очистити кошик
              </button>
            </div>
          </section>

          {/* Order summary */}
          <aside className="bs-card" style={{ padding: 22, position: 'sticky', top: 16, alignSelf: 'flex-start' }}>
            <h3 style={{ marginBottom: 14, fontSize: 16 }}>Підсумок замовлення</h3>

            {/* Free-shipping progress */}
            <div style={{
              padding: '12px 14px', borderRadius: 'var(--bs-r-sm)',
              background: 'var(--bs-blue-soft)',
              marginBottom: 16,
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 8 }}>
                <I.Truck width="14" height="14" style={{ color: 'var(--bs-blue)' }} />
                <span style={{ fontSize: 12.5, color: 'var(--bs-blue)', fontWeight: 600 }}>
                  {toFree === 0
                    ? 'Безкоштовна доставка застосована'
                    : `До безкоштовної доставки лишилось ₴${toFree}`}
                </span>
              </div>
              <div style={{ height: 4, background: '#fff', borderRadius: 999 }}>
                <div style={{
                  width: `${freeProgress}%`, height: '100%',
                  background: 'var(--bs-blue)', borderRadius: 999,
                  transition: 'width .25s',
                }} />
              </div>
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 13.5 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--bs-ink-2)' }}>
                <span>Сума товарів</span>
                <span>₴{sub}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--bs-ink-2)' }}>
                <span>Доставка</span>
                <span style={{ color: sub >= 1500 ? 'var(--bs-green)' : 'var(--bs-ink-3)', fontWeight: sub >= 1500 ? 700 : 500 }}>
                  {sub >= 1500 ? 'За наш кошт' : 'За тарифами Нової Пошти'}
                </span>
              </div>
            </div>

            <div style={{
              borderTop: '1px solid var(--bs-line)', marginTop: 16, paddingTop: 16,
              display: 'flex', justifyContent: 'space-between',
              fontSize: 18, fontWeight: 800, color: 'var(--bs-ink)',
            }}>
              <span>До сплати</span>
              <span>₴{sub}</span>
            </div>

            {/* Promo */}
            <div style={{ marginTop: 16 }}>
              <label style={{
                fontSize: 12, fontWeight: 600, color: 'var(--bs-ink-3)',
                textTransform: 'uppercase', letterSpacing: '.06em',
                marginBottom: 8, display: 'block',
              }}>Промокод</label>
              <div style={{ display: 'flex', gap: 8 }}>
                <input
                  value={promo} onChange={e => setPromo(e.target.value)}
                  placeholder="Введіть промокод" style={{
                    flex: 1, padding: '10px 12px', borderRadius: 'var(--bs-r-sm)',
                    border: '1px solid var(--bs-line)', background: '#fff',
                    fontSize: 13, color: 'var(--bs-ink)', font: 'inherit', outline: 'none',
                  }}
                />
                <button className="bs-btn bs-btn-secondary" style={{ borderColor: 'var(--bs-blue)', color: 'var(--bs-blue)' }}>
                  Застосувати
                </button>
              </div>
            </div>

            <button className="bs-btn bs-btn-primary" style={{
              width: '100%', padding: '14px', fontSize: 15, marginTop: 18,
            }}>
              Оформити замовлення →
            </button>
            <div style={{ fontSize: 11.5, color: 'var(--bs-ink-3)', marginTop: 10, textAlign: 'center', lineHeight: 1.5 }}>
              Натискаючи кнопку, ви погоджуєтесь з <a href="#" style={{ color: 'var(--bs-blue)' }}>Публічною офертою</a>.
            </div>
          </aside>
        </div>
      </main>
    </div>
  );
}

Object.assign(window, { CartPageMock, CartLine, QtyInput });
