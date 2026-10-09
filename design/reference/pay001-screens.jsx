// PAY-001-UI — компоновка екранів прототипу з готових DS-блоків
// (shared-ui.jsx + header-variants.jsx + rd13-checkout.jsxexports + pay001-*).

const PayIconLocal = (p) => (
  <svg {...p} viewBox="0 0 20 16" fill="none">
    <rect x="1" y="1" width="18" height="14" rx="2.5" stroke="currentColor" strokeWidth="1.5" />
    <path d="M1 6h18" stroke="currentColor" strokeWidth="1.5" />
  </svg>
);
const ReceiptIconLocal = (p) => (
  <svg {...p} viewBox="0 0 16 20" fill="none">
    <path d="M2 1h12v17l-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5-2 1.5V1z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round" />
    <path d="M5 6h6M5 9.5h6M5 13h4" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round" />
  </svg>
);

// PAY-001-UI2 Q2: a real qty stepper (not just a tweak) so "клієнт збільшив
// кількість" is an actual interaction — CreditTeaser/CTA recompute off
// price*qty live, no reload.
function QtyStepper({ qty, onChange }) {
  return (
    <div style={{ display: 'inline-flex', alignItems: 'center', border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r-sm)', overflow: 'hidden', flex: '0 0 auto' }}>
      <button type="button" onClick={() => onChange(Math.max(1, qty - 1))} style={{ width: 40, height: 48, border: 0, background: '#fff', fontSize: 17, cursor: 'pointer', color: 'var(--bs-ink-2)' }}>−</button>
      <span style={{ minWidth: 32, textAlign: 'center', fontSize: 14.5, fontWeight: 700, color: 'var(--bs-ink)' }}>{qty}</span>
      <button type="button" onClick={() => onChange(Math.min(9, qty + 1))} style={{ width: 40, height: 48, border: 0, background: '#fff', fontSize: 17, cursor: 'pointer', color: 'var(--bs-ink-2)' }}>+</button>
    </div>
  );
}

function ProductScreen({ price, device, qty, onQtyChange, preorder, onOpenModal, onGoCheckout }) {
  const mobile = device === 'mobile';
  const financeAmount = price * qty;
  return (
    <div className="bs-mock">
      <HeaderV1 />
      <main style={{ maxWidth: mobile ? '100%' : 1080, margin: '0 auto', padding: mobile ? '16px' : '24px 32px 56px', boxSizing: 'border-box' }}>
        <nav style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12.5, color: 'var(--bs-ink-3)', marginBottom: 16 }}>
          <I.Home width="12" height="12" /><span>›</span><span>Pokémon TCG</span><span>›</span><span style={{ color: 'var(--bs-ink)' }}>Товар</span>
        </nav>

        <div style={{ display: 'grid', gridTemplateColumns: mobile ? '1fr' : '1fr 1fr', gap: mobile ? 20 : 36 }}>
          <ImagePh label="Фото товару" ratio="1/1" />

          <div style={{ display: 'flex', flexDirection: 'column', gap: 18, minWidth: 0 }}>
            <div>
              <div style={{ fontFamily: '"JetBrains Mono", ui-monospace, monospace', fontSize: 11, letterSpacing: '.14em', color: 'var(--bs-ink-3)', textTransform: 'uppercase', marginBottom: 8 }}>
                Pokémon TCG · Japanese Edition
              </div>
              <h1 style={{ fontSize: mobile ? 22 : 28, lineHeight: 1.2 }}>
                Бустер Pokémon TCG: Mega Symphonia (Японське видання)
              </h1>
            </div>

            <div style={{ padding: '14px 0', borderBlock: '1px solid var(--bs-line)' }}>
              <PriceRow price={price} size="lg" />
            </div>

            <div style={{ display: 'flex', gap: 10 }}>
              <QtyStepper qty={qty} onChange={onQtyChange} />
              <button className="bs-btn bs-btn-primary" style={{ flex: 1, padding: '14px 18px', fontSize: 15 }}>
                <I.Cart width="16" height="16" /> Додати в кошик
              </button>
            </div>

            <InstallmentsCTA price={financeAmount} preorder={preorder} onOpen={onOpenModal} />

            <CreditTeaser price={financeAmount} preorder={preorder} />

            {!mobile && <TrustStrip />}
          </div>
        </div>

        <div style={{ marginTop: 28, display: 'flex', justifyContent: 'center' }}>
          <button className="bs-btn bs-btn-secondary" onClick={onGoCheckout}>Перейти в чекаут без ПЧ (порівняти) →</button>
        </div>
      </main>
    </div>
  );
}

function CheckoutScreen({ device, price, qty, discountPct, payment, setPayment, paymentsCount, setPaymentsCount, selectedBank, setSelectedBank, onSubmit, onBack, preorder }) {
  const mobile = device === 'mobile';
  const items = [{ id: 'demo', title: 'Бустер Pokémon TCG: Mega Symphonia (Японське видання)', price, qty }];
  // PAY-001-UI2 Q4: the credit block reads the amount AFTER discount/coupon
  // (payable), same field OrderTotals already shows — never the raw subtotal.
  const payable = Math.round(price * qty * (1 - discountPct / 100));

  const paymentBlock = (
    <CreditCoCard icon={<PayIconLocal width="17" height="14" />} title="Оплата">
      <PaymentSectionCredit value={payment} onSelect={setPayment} price={payable} paymentsCount={paymentsCount} setPaymentsCount={setPaymentsCount} selectedBank={selectedBank} setSelectedBank={setSelectedBank} hasPreorderItem={preorder} />
    </CreditCoCard>
  );
  const summary = (
    <CreditCoCard icon={<ReceiptIconLocal width="14" height="17" />} title="Замовлення · 1 товар">
      <OrderItems items={items} />
      <OrderTotals items={items} discountPct={discountPct} />
      <CreditOrderTail payment={payment} price={payable} onSubmit={onSubmit} hasPreorderItem={preorder} />
    </CreditCoCard>
  );

  return (
    <div className="bs-mock">
      <HeaderV1 />
      <main style={{ maxWidth: mobile ? '100%' : 1240, margin: '0 auto', padding: mobile ? '16px' : '20px 32px 56px', boxSizing: 'border-box' }}>
        <nav style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12.5, color: 'var(--bs-ink-3)', marginBottom: 14 }}>
          <button onClick={onBack} style={{ background: 'none', border: 0, padding: 0, font: 'inherit', color: 'var(--bs-blue)', cursor: 'pointer', fontWeight: 600 }}>← Товар</button>
        </nav>
        <h1 style={{ marginBottom: 18 }}>Оформити замовлення</h1>

        {mobile ? (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            <MobileInfoRow icon={<I.User width="14" height="14" />} title="Отримувач" value="Євгеній Л. · 099 111 22 33" />
            <MobileInfoRow icon={<I.Truck width="14" height="14" />} title="Доставка" value="Нова пошта · відділення №31, Дніпро" />
            {paymentBlock}
            {summary}
          </div>
        ) : (
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 380px', gap: 20, alignItems: 'flex-start' }}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
              <ReceiverCard errors={false} />
              <DeliveryCard guest={false} />
              {paymentBlock}
            </div>
            <aside style={{ position: 'sticky', top: 16 }}>{summary}</aside>
          </div>
        )}
      </main>
    </div>
  );
}

function CheckoutShell({ device, children }) {
  const mobile = device === 'mobile';
  return (
    <div className="bs-mock">
      <HeaderV1 />
      <main style={{ maxWidth: mobile ? '100%' : 760, margin: '0 auto', padding: mobile ? '16px' : '40px 32px 80px', boxSizing: 'border-box' }}>
        {children}
      </main>
    </div>
  );
}

Object.assign(window, { ProductScreen, CheckoutScreen, CheckoutShell });
