// Checkout page mockup (desktop). Three-column layout: Отримувач + Адреса /
// Метод доставки + Спосіб оплати + Коментар / Кошик + Промокод + Підтвердження.
// Replaces the current loud red auth banner with a quiet blue-soft strip;
// inputs unified to DS style. UX-014 / UX-025 friendly.

function FormField({ label, required, hint, error, children, type, ...input }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
      <label style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--bs-ink-2)' }}>
        {label} {required && <span style={{ color: 'var(--bs-danger)' }}>*</span>}
      </label>
      {children || (
        <input type={type || 'text'} {...input} style={{
          padding: '10px 12px', borderRadius: 'var(--bs-r-sm)',
          border: `1px solid ${error ? 'var(--bs-danger)' : 'var(--bs-line)'}`,
          background: '#fff',
          fontSize: 14, color: 'var(--bs-ink)', font: 'inherit', outline: 'none',
          width: '100%', boxSizing: 'border-box',
        }} />
      )}
      {hint && <div style={{ fontSize: 11.5, color: 'var(--bs-ink-3)' }}>{hint}</div>}
      {error && <div style={{ fontSize: 12, color: 'var(--bs-danger)' }}>{error}</div>}
    </div>
  );
}

function Section({ title, children }) {
  return (
    <section className="bs-card" style={{ padding: 22 }}>
      <h3 style={{ fontSize: 16, marginBottom: 16 }}>{title}</h3>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
        {children}
      </div>
    </section>
  );
}

function RadioRow({ name, value, current, onSelect, label, sublabel }) {
  const isActive = value === current;
  return (
    <label style={{
      display: 'flex', alignItems: 'center', gap: 12,
      padding: '14px 16px',
      border: `1.5px solid ${isActive ? 'var(--bs-blue)' : 'var(--bs-line)'}`,
      background: isActive ? 'var(--bs-blue-soft)' : '#fff',
      borderRadius: 'var(--bs-r-sm)', cursor: 'pointer',
      transition: 'background .15s, border-color .15s',
    }}>
      <input type="radio" name={name} value={value} checked={isActive} onChange={() => onSelect(value)}
        style={{ accentColor: 'var(--bs-blue)' }} />
      <div style={{ flex: 1 }}>
        <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--bs-ink)' }}>{label}</div>
        {sublabel && <div style={{ fontSize: 12, color: 'var(--bs-ink-3)', marginTop: 2 }}>{sublabel}</div>}
      </div>
    </label>
  );
}

function CheckoutPageMock() {
  const [shipping, setShipping] = React.useState('np-branch');
  const [payment, setPayment] = React.useState('card');

  const items = [
    { id: 'sym',  title: 'Бустер Pokémon TCG: Mega Symphonia (Японське видання)', price: 150, qty: 2 },
    { id: 'op11', title: 'Бустер One Piece Card Game OP-11 (Японське видання)',   price: 200, qty: 2 },
  ];
  const sub = items.reduce((s, it) => s + it.price * it.qty, 0);

  return (
    <div className="bs-mock">
      <HeaderV1 />

      <main style={{ maxWidth: 1240, margin: '0 auto', padding: '20px 32px 56px' }}>
        <nav style={{
          display: 'flex', alignItems: 'center', gap: 8,
          fontSize: 12.5, color: 'var(--bs-ink-3)', marginBottom: 14,
        }}>
          <a href="#" style={{ color: 'var(--bs-ink-3)', display: 'inline-flex' }}><I.Home width="12" height="12" /></a>
          <span>›</span>
          <a href="#" style={{ color: 'var(--bs-ink-3)' }}>Мій кошик</a>
          <span>›</span>
          <span style={{ color: 'var(--bs-ink)' }}>Оформити замовлення</span>
        </nav>

        <h1 style={{ marginBottom: 18 }}>Оформити замовлення</h1>

        {/* Auth nudge — quiet blue-soft, not the loud red box */}
        <div style={{
          display: 'flex', alignItems: 'center', gap: 14,
          padding: '12px 16px', marginBottom: 20,
          background: 'var(--bs-blue-soft)',
          border: '1px solid #c7d2fe',
          borderRadius: 'var(--bs-r-sm)',
        }}>
          <I.User width="16" height="16" style={{ color: 'var(--bs-blue)' }} />
          <div style={{ flex: 1, fontSize: 13.5, color: 'var(--bs-ink-2)' }}>
            Маєш акаунт? <strong>Авторизуйся</strong>, щоб не вводити дані заново.
          </div>
          <button className="bs-btn bs-btn-ghost" style={{
            color: 'var(--bs-blue)', padding: '6px 10px', fontSize: 13,
          }}>Увійти в акаунт →</button>
        </div>

        {/* 3-column form */}
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1.05fr', gap: 18, alignItems: 'flex-start' }}>
          {/* Col 1 */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
            <Section title="Отримувач">
              <FormField label="Ім'я" required defaultValue="Євгеній" />
              <FormField label="Прізвище" required defaultValue="Леусенко" />
              <FormField label="Електронна пошта" required type="email" defaultValue="evgenij.leusenko@gmail.com" />
              <FormField label="Телефон" required defaultValue="0991119279" />
            </Section>

            <Section title="Адреса доставки">
              <label style={{ display: 'flex', alignItems: 'center', gap: 10, fontSize: 14, color: 'var(--bs-ink)' }}>
                <input type="radio" defaultChecked style={{ accentColor: 'var(--bs-blue)' }} />
                Використати існуючу адресу
              </label>
              <FormField label="Адреса">
                <select style={{
                  padding: '10px 30px 10px 12px', borderRadius: 'var(--bs-r-sm)',
                  border: '1px solid var(--bs-line)', background: '#fff',
                  fontSize: 14, color: 'var(--bs-ink)', font: 'inherit', outline: 'none',
                  width: '100%', appearance: 'none',
                  backgroundImage: "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 10 10'><path d='M2 4l3 3 3-3' stroke='%236B7280' stroke-width='1.5' fill='none'/></svg>\")",
                  backgroundRepeat: 'no-repeat', backgroundPosition: 'right 12px center',
                }}>
                  <option>вул. Савкіна, Будинок 6, Дніпро</option>
                </select>
              </FormField>
              <div style={{ fontSize: 12, color: 'var(--bs-ink-3)' }}>
                Щоб додати нову адресу, перейдіть у розділ <a href="#" style={{ color: 'var(--bs-blue)' }}>«Мої адреси»</a>.
              </div>
            </Section>
          </div>

          {/* Col 2 */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
            <Section title="Метод доставки">
              <RadioRow name="ship" value="np-branch" current={shipping} onSelect={setShipping}
                label="Нова пошта — у відділення"
                sublabel="За тарифами Нової пошти · ~3 дні" />
              <RadioRow name="ship" value="np-address" current={shipping} onSelect={setShipping}
                label="Нова пошта — адресна або поштомат"
                sublabel="Для поштомата: вкажіть номер у коментарі" />
            </Section>

            <Section title="Спосіб оплати">
              <RadioRow name="pay" value="card" current={payment} onSelect={setPayment}
                label="Оплата карткою, Google / Apple Pay"
                sublabel="Безпечно через еквайринг" />
              <RadioRow name="pay" value="cod" current={payment} onSelect={setPayment}
                label="Оплата при доставці (післяплата)" />
              <RadioRow name="pay" value="iban" current={payment} onSelect={setPayment}
                label="Платіж за реквізитами на IBAN" />
            </Section>

            <Section title="Коментар до замовлення">
              <textarea placeholder="Наприклад: бустери для подарунка, упакуйте, будь ласка." rows={3}
                style={{
                  padding: '10px 12px', borderRadius: 'var(--bs-r-sm)',
                  border: '1px solid var(--bs-line)', background: '#fff',
                  fontSize: 14, color: 'var(--bs-ink)', font: 'inherit', outline: 'none',
                  resize: 'vertical', boxSizing: 'border-box', width: '100%',
                }} />
            </Section>
          </div>

          {/* Col 3 — summary */}
          <aside style={{ display: 'flex', flexDirection: 'column', gap: 18, position: 'sticky', top: 16 }}>
            <Section title="Ваше замовлення">
              <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                {items.map(it => (
                  <div key={it.id} style={{
                    display: 'grid', gridTemplateColumns: '52px 1fr auto', gap: 12, alignItems: 'center',
                  }}>
                    <div style={{
                      width: 52, height: 52, borderRadius: 'var(--bs-r-sm)',
                      background: '#fff', border: '1px solid var(--bs-line)', overflow: 'hidden',
                    }}>
                      <ImagePh ratio="1/1" radius="var(--bs-r-sm)" label="" />
                    </div>
                    <div style={{ minWidth: 0 }}>
                      <div style={{
                        fontSize: 13, fontWeight: 600, color: 'var(--bs-ink)', lineHeight: 1.4,
                        display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical',
                        overflow: 'hidden',
                      }}>{it.title}</div>
                      <div style={{ fontSize: 11, color: 'var(--bs-ink-3)', marginTop: 2 }}>× {it.qty}</div>
                    </div>
                    <div style={{ fontSize: 13.5, fontWeight: 700, color: 'var(--bs-ink)' }}>₴{it.price * it.qty}</div>
                  </div>
                ))}
              </div>

              <div style={{ borderTop: '1px solid var(--bs-line)', paddingTop: 14, marginTop: 14, display: 'flex', flexDirection: 'column', gap: 6, fontSize: 13.5 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--bs-ink-2)' }}>
                  <span>Сума</span><span>₴{sub}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--bs-ink-2)' }}>
                  <span>Доставка</span>
                  <span style={{ color: sub >= 1500 ? 'var(--bs-green)' : 'var(--bs-ink-3)', fontWeight: sub >= 1500 ? 700 : 500 }}>
                    {sub >= 1500 ? 'За наш кошт' : 'За тарифами Нової Пошти'}
                  </span>
                </div>
              </div>

              <div style={{
                borderTop: '1px solid var(--bs-line)', marginTop: 12, paddingTop: 12,
                display: 'flex', justifyContent: 'space-between',
                fontSize: 18, fontWeight: 800, color: 'var(--bs-ink)',
              }}>
                <span>До сплати</span><span>₴{sub}</span>
              </div>
            </Section>

            <Section title="Промокод">
              <div style={{ display: 'flex', gap: 8 }}>
                <input placeholder="Введіть промокод" style={{
                  flex: 1, padding: '10px 12px', borderRadius: 'var(--bs-r-sm)',
                  border: '1px solid var(--bs-line)', background: '#fff',
                  fontSize: 13, color: 'var(--bs-ink)', font: 'inherit', outline: 'none',
                }} />
                <button className="bs-btn bs-btn-secondary" style={{ borderColor: 'var(--bs-blue)', color: 'var(--bs-blue)' }}>
                  Застосувати
                </button>
              </div>
            </Section>

            <div style={{
              padding: 14,
              background: 'var(--bs-bg)', borderRadius: 'var(--bs-r-sm)',
              fontSize: 12, color: 'var(--bs-ink-2)', lineHeight: 1.55,
            }}>
              <label style={{ display: 'flex', alignItems: 'flex-start', gap: 10, cursor: 'pointer' }}>
                <input type="checkbox" defaultChecked style={{ accentColor: 'var(--bs-blue)', marginTop: 2 }} />
                <span>
                  Я погоджуюсь з умовами <a href="#" style={{ color: 'var(--bs-blue)' }}>Публічної оферти</a>,
                  включно з положеннями про обробку персональних даних.
                </span>
              </label>
              <p style={{ marginTop: 10, color: 'var(--bs-ink-3)', fontSize: 11.5 }}>
                Вміст бустерів є випадковим і залежить від розподілу шансів, закладеного виробником.
              </p>
            </div>

            <button className="bs-btn bs-btn-primary" style={{ padding: '14px', fontSize: 15 }}>
              Підтвердити замовлення →
            </button>
          </aside>
        </div>
      </main>
    </div>
  );
}

Object.assign(window, { CheckoutPageMock, FormField, Section, RadioRow });
