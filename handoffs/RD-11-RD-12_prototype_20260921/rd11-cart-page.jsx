// RD-11 — cart page. Card rows + sticky summary column (desktop), cards + sticky CTA bar (mobile).
function CartRow({ item, mobile, onQty, onRemove }) {
  const total = item.price * item.qty;
  return (
    <div style={{ display: 'grid', gridTemplateColumns: mobile ? '72px 1fr' : '96px 1fr auto', gap: mobile ? 12 : 16, padding: mobile ? '14px 0' : '18px 0', borderBottom: '1px solid var(--bs-line-2)', alignItems: 'start' }}>
      <ImgPh w={mobile ? 72 : 96} h={mobile ? 72 : 96} label="" />
      <div style={{ minWidth: 0, display: 'grid', gap: 10 }}>
        <div style={{ display: 'flex', gap: 12, alignItems: 'start' }}>
          <a href="#" style={{ flex: '1 1 auto', fontSize: mobile ? 14 : 15, fontWeight: 600, color: 'var(--bs-ink)', lineHeight: 1.4, display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>{item.title}</a>
          {mobile && <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--bs-ink)', whiteSpace: 'nowrap' }}>{money(total)}</div>}
        </div>
        <div style={{ fontSize: 12.5, color: 'var(--bs-ink-3)' }}>{money(item.price)} / шт.{!item.stock && <span style={{ color: 'var(--bs-danger)', fontWeight: 600 }}> · немає в наявності</span>}</div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 14, flexWrap: 'wrap' }}>
          <Stepper qty={item.qty} onChange={onQty} />
          <LinkBtn danger onClick={onRemove}><Ic.trash s={15}/> Видалити</LinkBtn>
        </div>
      </div>
      {!mobile && <div style={{ textAlign: 'right', fontSize: 17, fontWeight: 700, color: 'var(--bs-ink)', whiteSpace: 'nowrap', paddingTop: 2 }}>{money(total)}</div>}
    </div>
  );
}

function SummaryCard({ items, subtotal, blocked, mobile, onCheckout, onContinue }) {
  const count = items.reduce((s, i) => s + i.qty, 0);
  return (
    <div className="bs-card" style={{ padding: mobile ? 16 : 20, display: 'grid', gap: 14 }}>
      <h3 style={{ fontSize: 16, fontWeight: 700, color: 'var(--bs-ink)' }}>Разом</h3>
      <div style={{ display: 'grid', gap: 8, fontSize: 14 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--bs-ink-2)' }}><span>Товари ({count})</span><span>{money(subtotal)}</span></div>
        <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--bs-ink-3)' }}><span>Доставка</span><span>за тарифами перевізника</span></div>
      </div>
      <div style={{ borderTop: '1px solid var(--bs-line)', paddingTop: 12, display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
        <span style={{ fontSize: 14, fontWeight: 700, color: 'var(--bs-ink)' }}>До сплати</span>
        <span style={{ fontSize: 24, fontWeight: 800, color: 'var(--bs-ink)', letterSpacing: '-0.02em' }}>{money(subtotal)}</span>
      </div>
      <ShippingInfo subtotal={subtotal} dense />
      <button className={blocked ? 'bs-btn' : 'bs-btn bs-btn-primary'} disabled={blocked} onClick={onCheckout} style={{ height: 48, width: '100%', fontSize: 15, cursor: blocked ? 'not-allowed' : 'pointer', ...(blocked ? { background: 'var(--bs-line-2)', color: 'var(--bs-ink-2)', border: '1px solid var(--bs-line)' } : null) }}>{blocked ? 'Виправте кількість товарів' : 'Оформити'}</button>
      <button className="bs-btn" onClick={onContinue} style={{ height: 44, width: '100%', background: '#fff', color: 'var(--bs-blue)', border: '1px solid var(--bs-blue)' }}>Продовжити покупки</button>
    </div>
  );
}

function RecoStrip({ mobile, onAdd }) {
  const list = CATALOG.filter(p => p.stock).slice(0, 4);
  return (
    <section style={{ marginTop: mobile ? 28 : 40 }}>
      <h3 style={{ fontSize: mobile ? 16 : 18, fontWeight: 700, color: 'var(--bs-ink)', marginBottom: 12 }}>Часто беруть разом</h3>
      <div style={{ display: mobile ? 'flex' : 'grid', gridTemplateColumns: 'repeat(4,minmax(0,1fr))', gap: 12, overflowX: mobile ? 'auto' : 'visible', paddingBottom: mobile ? 4 : 0 }}>
        {list.map(p => (
          <div key={p.id} className="bs-card" style={{ padding: 10, display: 'grid', gap: 8, minWidth: mobile ? 150 : 0, alignContent: 'start' }}>
            <ImgPh w="100%" h={mobile ? 110 : 130} label="product" />
            <div style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--bs-ink)', lineHeight: 1.35, display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden', minHeight: 34 }}>{p.title}</div>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
              <span style={{ fontSize: 14, fontWeight: 700, color: 'var(--bs-ink)' }}>{money(p.price)}</span>
              <button className="bs-btn bs-btn-primary" style={{ minHeight: 36, height: 'auto', padding: '6px 10px', fontSize: 12.5, whiteSpace: 'normal' }} onClick={() => onAdd(p)}>+ У кошик</button>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}

function CartPage({ mobile, items, stockError, onQty, onRemove, onContinue, onCheckout, onAdd }) {
  const subtotal = items.reduce((s, i) => s + i.price * i.qty, 0);
  const blocked = stockError;
  const pad = mobile ? 16 : 28;
  if (!items.length) {
    return (
      <div style={{ padding: pad, paddingBottom: 40 }}>
        <div style={{ fontSize: 12.5, color: 'var(--bs-ink-3)', marginBottom: 14 }}>Головна › Кошик</div>
        <div className="bs-card"><EmptyState title="Кошик порожній" text="Додайте бустери, бокси чи аксесуари — вони з’являться тут." cta="Перейти до каталогу" onCta={onContinue} /></div>
        <RecoStrip mobile={mobile} onAdd={onAdd} />
      </div>
    );
  }
  return (
    <div style={{ padding: pad, paddingBottom: mobile ? 110 : 48 }}>
      <div style={{ fontSize: 12.5, color: 'var(--bs-ink-3)', marginBottom: 10 }}>Головна › Кошик</div>
      <h1 style={{ fontSize: mobile ? 22 : 28, fontWeight: 800, color: 'var(--bs-ink)', letterSpacing: '-0.02em', marginBottom: mobile ? 14 : 20 }}>Кошик</h1>
      {stockError && <div style={{ marginBottom: 16 }}><Notice tone="warn" title="Перевірте кількість товарів у кошику." text="Одного або кількох товарів немає в наявності в обраній кількості — зменште кількість або приберіть позицію." /></div>}
      <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : 'minmax(0,1fr) 340px', gap: mobile ? 16 : 24, alignItems: 'start' }}>
        <div className="bs-card" style={{ padding: mobile ? '2px 14px 14px' : '4px 20px 20px' }}>
          {items.map(it => <CartRow key={it.id} item={it} mobile={mobile} onQty={(d) => onQty(it.id, d)} onRemove={() => onRemove(it.id)} />)}
          {!mobile && (
            <div style={{ paddingTop: 16 }}>
              <button className="bs-btn" onClick={onContinue} style={{ height: 44, background: '#fff', color: 'var(--bs-blue)', border: '1px solid var(--bs-blue)' }}>← Продовжити покупки</button>
            </div>
          )}
        </div>
        {mobile
          ? <SummaryCard items={items} subtotal={subtotal} blocked={blocked} mobile onCheckout={onCheckout} onContinue={onContinue} />
          : <div style={{ position: 'sticky', top: 16 }}><SummaryCard items={items} subtotal={subtotal} blocked={blocked} onCheckout={onCheckout} onContinue={onContinue} /></div>}
      </div>
      <RecoStrip mobile={mobile} onAdd={onAdd} />
    </div>
  );
}

// Mobile: total + CTA pinned above the fold-line so «Оформити» is always one tap away.
function CartStickyBar({ items, blocked, onCheckout }) {
  const subtotal = items.reduce((s, i) => s + i.price * i.qty, 0);
  if (!items.length) return null;
  return (
    <div style={{ position: 'absolute', left: 0, right: 0, bottom: 0, background: 'var(--bs-paper)', borderTop: '1px solid var(--bs-line)', boxShadow: '0 -6px 20px rgba(17,24,39,.07)', padding: '10px 16px 14px', display: 'flex', alignItems: 'center', gap: 12, pointerEvents: 'auto' }}>
      <div style={{ display: 'grid' }}>
        <span style={{ fontSize: 11.5, color: 'var(--bs-ink-3)' }}>До сплати</span>
        <span style={{ fontSize: 19, fontWeight: 800, color: 'var(--bs-ink)' }}>{money(subtotal)}</span>
      </div>
      <button className={blocked ? 'bs-btn' : 'bs-btn bs-btn-primary'} disabled={blocked} onClick={onCheckout} style={{ flex: '1 1 auto', height: 48, fontSize: 15, cursor: blocked ? 'not-allowed' : 'pointer', ...(blocked ? { background: 'var(--bs-line-2)', color: 'var(--bs-ink-2)', border: '1px solid var(--bs-line)' } : null) }}>{blocked ? 'Виправте кількість' : 'Оформити'}</button>
    </div>
  );
}

Object.assign(window, { CartPage, CartStickyBar, SummaryCard, CartRow, RecoStrip });
