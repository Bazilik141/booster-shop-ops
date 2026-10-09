// RD-12 — mini-cart. Desktop: right drawer 380px. Mobile: bottom sheet.
function MiniRow({ item, onQty, onRemove }) {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: '56px 1fr', gap: 12, padding: '14px 0', borderBottom: '1px solid var(--bs-line-2)', alignItems: 'start' }}>
      <ImgPh w={56} h={56} label="" />
      <div style={{ minWidth: 0, display: 'grid', gap: 8 }}>
        <div style={{ display: 'flex', gap: 10, alignItems: 'start' }}>
          <a href="#" style={{ flex: '1 1 auto', fontSize: 13.5, fontWeight: 600, color: 'var(--bs-ink)', lineHeight: 1.4, display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>{item.title}</a>
          <button onClick={onRemove} aria-label="Видалити" style={{ flex: '0 0 auto', width: 32, height: 32, display: 'grid', placeItems: 'center', background: 'transparent', border: 0, color: 'var(--bs-ink-4)' }}><Ic.x s={15}/></button>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
          <Stepper qty={item.qty} onChange={onQty} compact />
          <span style={{ fontSize: 15, fontWeight: 700, color: 'var(--bs-ink)', whiteSpace: 'nowrap' }}>{money(item.price * item.qty)}</span>
        </div>
      </div>
    </div>
  );
}

function MiniCart({ open, mobile, items, onClose, onQty, onRemove, onCheckout, onCartPage }) {
  const subtotal = items.reduce((s, i) => s + i.price * i.qty, 0);
  const count = items.reduce((s, i) => s + i.qty, 0);
  const panel = mobile
    ? { position: 'absolute', left: 0, right: 0, bottom: 0, maxHeight: '86%', borderRadius: 'var(--bs-r-lg) var(--bs-r-lg) 0 0', transform: open ? 'translateY(0)' : 'translateY(100%)' }
    : { position: 'absolute', top: 0, bottom: 0, right: 0, width: 380, transform: open ? 'translateX(0)' : 'translateX(100%)' };
  return (
    <div style={{ position: 'absolute', inset: 0, pointerEvents: open ? 'auto' : 'none', zIndex: 30 }}>
      <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgba(17,24,39,.45)', opacity: open ? 1 : 0, transition: 'opacity .22s ease' }}></div>
      <div role="dialog" aria-label="Кошик" style={{ ...panel, background: 'var(--bs-paper)', boxShadow: 'var(--bs-sh-pop)', transition: 'transform .26s cubic-bezier(.22,.7,.3,1)', display: 'flex', flexDirection: 'column' }}>
        {mobile && <div style={{ display: 'grid', placeItems: 'center', paddingTop: 8 }}><div style={{ width: 40, height: 4, borderRadius: 999, background: 'var(--bs-line)' }}></div></div>}
        <header style={{ display: 'flex', alignItems: 'center', gap: 10, padding: mobile ? '10px 16px 12px' : '16px 16px 14px', borderBottom: '1px solid var(--bs-line)' }}>
          <h3 style={{ flex: '1 1 auto', fontSize: 16, fontWeight: 700, color: 'var(--bs-ink)' }}>Кошик{count > 0 && <span style={{ color: 'var(--bs-ink-3)', fontWeight: 600 }}> · {count} {count === 1 ? 'товар' : count < 5 ? 'товари' : 'товарів'}</span>}</h3>
          <button onClick={onClose} aria-label="Закрити" style={{ width: 44, height: 44, display: 'grid', placeItems: 'center', background: 'transparent', border: 0, color: 'var(--bs-ink-2)', marginRight: -10 }}><Ic.x s={18}/></button>
        </header>
        {items.length ? (
          <React.Fragment>
            <div style={{ flex: '1 1 auto', overflowY: 'auto', padding: '0 16px', minHeight: mobile ? 120 : 0 }}>
              {items.map(it => <MiniRow key={it.id} item={it} onQty={(d) => onQty(it.id, d)} onRemove={() => onRemove(it.id)} />)}
            </div>
            <footer style={{ flex: '0 0 auto', borderTop: '1px solid var(--bs-line)', padding: '14px 16px 16px', display: 'grid', gap: 12, background: 'var(--bs-paper)' }}>
              <ShippingInfo subtotal={subtotal} dense />
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
                <span style={{ fontSize: 14, fontWeight: 700, color: 'var(--bs-ink)' }}>Сума</span>
                <span style={{ fontSize: 22, fontWeight: 800, color: 'var(--bs-ink)', letterSpacing: '-0.02em' }}>{money(subtotal)}</span>
              </div>
              <button className="bs-btn bs-btn-primary" onClick={onCheckout} style={{ height: 48, width: '100%', fontSize: 15 }}>Оформити замовлення</button>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10 }}>
                <LinkBtn onClick={onClose}>← Продовжити покупки</LinkBtn>
                <LinkBtn onClick={onCartPage}>Відкрити кошик</LinkBtn>
              </div>
            </footer>
          </React.Fragment>
        ) : (
          <div style={{ flex: '1 1 auto', display: 'grid', alignContent: 'center', paddingBottom: mobile ? 24 : 0 }}>
            <EmptyState title="Кошик порожній" text="Тут з’являться товари, які ви додасте." cta="До каталогу" onCta={onClose} />
          </div>
        )}
      </div>
    </div>
  );
}

function ShopHeader({ mobile, count, onCart }) {
  return (
    <header style={{ position: 'sticky', top: 0, zIndex: 10, background: 'var(--bs-paper)', borderBottom: '1px solid var(--bs-line)' }}>
      <div style={{ maxWidth: 1180, margin: '0 auto', padding: mobile ? '10px 16px' : '12px 24px', display: 'flex', alignItems: 'center', gap: mobile ? 10 : 20 }}>
        <div style={{ fontSize: mobile ? 14 : 16, fontWeight: 800, letterSpacing: '-0.02em', color: 'var(--bs-ink)', whiteSpace: 'nowrap' }}>BOOSTER<span style={{ color: 'var(--bs-blue)' }}>SHOP</span></div>
        {!mobile && (
          <div style={{ flex: '1 1 auto', maxWidth: 520, display: 'flex', alignItems: 'center', gap: 8, height: 40, padding: '0 12px', border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r-sm)', color: 'var(--bs-ink-4)', fontSize: 13.5 }}><Ic.search s={16}/> Пошук товарів</div>
        )}
        <div style={{ flex: '1 1 auto' }}></div>
        <button onClick={onCart} className="bs-btn bs-btn-primary" style={{ height: 44, padding: mobile ? '0 12px' : '0 16px', position: 'relative', gap: 8 }}>
          <Ic.cart s={20}/>{!mobile && <span>Кошик</span>}
          {count > 0 && <span style={{ position: 'absolute', top: -6, right: -6, minWidth: 20, height: 20, padding: '0 5px', borderRadius: 999, background: 'var(--bs-ink)', color: '#fff', fontSize: 11.5, fontWeight: 700, display: 'grid', placeItems: 'center', border: '2px solid #fff' }}>{count}</span>}
        </button>
      </div>
    </header>
  );
}

Object.assign(window, { MiniCart, MiniRow, ShopHeader });
