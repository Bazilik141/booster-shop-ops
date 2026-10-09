// Mini-cart drawer mock — UX-013. Right-side drawer (380px), sticky subtotal,
// qty +/- controls (44×44 for mobile compliance per UX-026), green CTA at bottom.
// Renders inside an artboard with a faded category background to show context.

function CartRow({ item, onQty, onRemove }) {
  return (
    <div style={{
      display: 'grid', gridTemplateColumns: '64px 1fr auto', gap: 12,
      padding: '14px 0', borderBottom: '1px solid var(--bs-line-2)',
      alignItems: 'flex-start',
    }}>
      <div style={{
        width: 64, height: 64, borderRadius: 'var(--bs-r-sm)', overflow: 'hidden',
        background: '#fff', border: '1px solid var(--bs-line)',
      }}>
        <ImagePh ratio="1/1" radius="var(--bs-r-sm)" label="" />
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8, minWidth: 0 }}>
        <div style={{
          fontSize: 13.5, fontWeight: 600, color: 'var(--bs-ink)',
          lineHeight: 1.4,
          display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical',
          overflow: 'hidden',
        }}>{item.title}</div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <div style={{
            display: 'inline-flex', alignItems: 'center',
            border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r-sm)',
          }}>
            <button onClick={() => onQty(-1)} style={qtyBtn}><I.Minus width="10" height="10" /></button>
            <span style={{
              minWidth: 28, textAlign: 'center', fontSize: 13, fontWeight: 600, color: 'var(--bs-ink)',
            }}>{item.qty}</span>
            <button onClick={() => onQty(1)} style={qtyBtn}><I.Plus width="10" height="10" /></button>
          </div>
          <button onClick={onRemove} style={{
            background: 'transparent', border: 0, color: 'var(--bs-ink-3)',
            fontSize: 12, cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: 4,
            textDecoration: 'underline', textUnderlineOffset: 3,
          }}>Видалити</button>
        </div>
      </div>
      <div style={{ textAlign: 'right', fontSize: 14, fontWeight: 700, color: 'var(--bs-ink)' }}>
        ₴{item.price * item.qty}
      </div>
    </div>
  );
}

const qtyBtn = {
  width: 28, height: 28, border: 0, background: 'transparent',
  color: 'var(--bs-ink-2)', cursor: 'pointer',
  display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
};

function MiniCartMock() {
  const [items, setItems] = React.useState([
    { id: 'mega', title: 'Бустер Pokémon TCG: Mega Symphonia (Японське видання)', price: 150, qty: 2 },
    { id: 'op11', title: 'Бустер One Piece Card Game OP-11 (Японське видання)',  price: 165, qty: 1 },
    { id: 'ninja', title: 'Бустер Pokémon TCG: Ninja Spinner',                   price: 150, qty: 1 },
  ]);
  const sub = items.reduce((s, it) => s + it.price * it.qty, 0);

  const setQty = (id, d) => setItems(items.map(it =>
    it.id === id ? { ...it, qty: Math.max(1, it.qty + d) } : it
  ));
  const remove = (id) => setItems(items.filter(it => it.id !== id));

  return (
    <div className="bs-mock" style={{
      height: '100%', position: 'relative',
      background: 'linear-gradient(to right, rgba(17,24,39,0.45), rgba(17,24,39,0.65))',
      backdropFilter: 'blur(2px)',
    }}>
      {/* faked page behind to show drawer context */}
      <div style={{
        position: 'absolute', inset: 0, zIndex: 0,
        backgroundImage: 'linear-gradient(rgba(247,247,245,.85), rgba(247,247,245,.85)), repeating-linear-gradient(0deg, #fff 0 80px, #f0eee9 80px 81px)',
      }} />

      {/* drawer */}
      <aside style={{
        position: 'absolute', right: 0, top: 0, bottom: 0,
        width: 400, background: '#fff',
        boxShadow: 'var(--bs-sh-pop)',
        display: 'flex', flexDirection: 'column',
        zIndex: 1,
      }}>
        <header style={{
          padding: '18px 22px',
          borderBottom: '1px solid var(--bs-line)',
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
        }}>
          <div>
            <div style={{ fontSize: 16, fontWeight: 700, color: 'var(--bs-ink)' }}>Кошик</div>
            <div style={{ fontSize: 12, color: 'var(--bs-ink-3)', marginTop: 2 }}>
              {items.length} товари · {items.reduce((s, it) => s + it.qty, 0)} шт.
            </div>
          </div>
          <button style={{
            width: 32, height: 32, borderRadius: 'var(--bs-r-sm)',
            background: 'var(--bs-bg)', border: 0,
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
            color: 'var(--bs-ink-2)', cursor: 'pointer',
          }}>
            <I.Close width="12" height="12" />
          </button>
        </header>

        <div style={{ flex: 1, overflowY: 'auto', padding: '4px 22px' }}>
          {items.map(it => (
            <CartRow key={it.id} item={it}
              onQty={(d) => setQty(it.id, d)} onRemove={() => remove(it.id)} />
          ))}
          {/* upsell row */}
          <div style={{
            marginTop: 18, padding: '12px 14px',
            background: 'var(--bs-blue-soft)', borderRadius: 'var(--bs-r-sm)',
            display: 'flex', alignItems: 'center', gap: 10,
          }}>
            <I.Truck width="16" height="16" style={{ color: 'var(--bs-blue)' }} />
            <span style={{ fontSize: 12.5, color: 'var(--bs-blue)', fontWeight: 600 }}>
              {sub >= 1500
                ? 'Безкоштовна доставка застосована'
                : `Безкоштовна доставка від ₴1500 — лишилось ₴${1500 - sub}`}
            </span>
          </div>
        </div>

        <footer style={{
          padding: '16px 22px',
          borderTop: '1px solid var(--bs-line)',
          background: '#fff',
          display: 'flex', flexDirection: 'column', gap: 10,
        }}>
          <div style={{
            display: 'flex', justifyContent: 'space-between',
            fontSize: 16, fontWeight: 700, color: 'var(--bs-ink)',
          }}>
            <span>До сплати</span><span>₴{sub}</span>
          </div>
          <button className="bs-btn bs-btn-primary" style={{ width: '100%', padding: '14px', fontSize: 14 }}>
            Оформити замовлення →
          </button>
          <button className="bs-btn bs-btn-ghost" style={{ width: '100%' }}>
            Продовжити покупки
          </button>
        </footer>
      </aside>
    </div>
  );
}

Object.assign(window, { MiniCartMock });
