// Account flows mockups. One file with the account-shell layout + 4 inner
// pages (order list, order detail, address list, info edit). Login/Register
// is a separate mock since it's pre-auth. Replaces the OpenCart-admin look
// with card-based content and a calm neutral sidebar (no big blue header).

function AccountSidebar({ active }) {
  const items = [
    { id: 'orders',    label: 'Мої замовлення',     icon: <I.Pack width="14" height="14" /> },
    { id: 'info',      label: 'Особисті дані',      icon: <I.User width="14" height="14" /> },
    { id: 'addresses', label: 'Адреси доставки',    icon: <I.Truck width="14" height="14" /> },
    { id: 'password',  label: 'Пароль',             icon: <I.Shield width="14" height="14" /> },
    { id: 'logout',    label: 'Вийти',              icon: <I.Close width="14" height="14" />, ghost: true },
  ];
  return (
    <aside className="bs-card" style={{ padding: 12 }}>
      <div style={{
        padding: '6px 10px 12px',
        borderBottom: '1px solid var(--bs-line-2)',
        marginBottom: 8,
      }}>
        <div style={{ fontSize: 12, color: 'var(--bs-ink-3)' }}>Особистий кабінет</div>
        <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--bs-ink)', marginTop: 2 }}>
          Євгеній Леусенко
        </div>
        <div style={{ fontSize: 11.5, color: 'var(--bs-ink-3)', marginTop: 2 }}>
          evgenij.leusenko@gmail.com
        </div>
      </div>
      <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 2 }}>
        {items.map(i => {
          const isActive = i.id === active;
          return (
            <li key={i.id}>
              <a href="#" style={{
                display: 'flex', alignItems: 'center', gap: 10,
                padding: '10px 12px', borderRadius: 'var(--bs-r-sm)',
                background: isActive ? 'var(--bs-blue-soft)' : 'transparent',
                color: i.ghost ? 'var(--bs-ink-3)' : (isActive ? 'var(--bs-blue)' : 'var(--bs-ink-2)'),
                fontSize: 13.5, fontWeight: isActive ? 700 : 500,
                textDecoration: 'none',
                marginTop: i.ghost ? 8 : 0,
                borderTop: i.ghost ? '1px solid var(--bs-line-2)' : 'none',
                paddingTop: i.ghost ? 14 : '10px',
              }}>
                <span style={{ color: 'currentColor', flex: '0 0 auto' }}>{i.icon}</span>
                {i.label}
              </a>
            </li>
          );
        })}
      </ul>
    </aside>
  );
}

function AccountShell({ active, title, children }) {
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
          <a href="#" style={{ color: 'var(--bs-ink-3)' }}>Особистий кабінет</a>
          <span>›</span>
          <span style={{ color: 'var(--bs-ink)' }}>{title}</span>
        </nav>
        <h1 style={{ marginBottom: 18 }}>{title}</h1>
        <div style={{ display: 'grid', gridTemplateColumns: '260px 1fr', gap: 24, alignItems: 'flex-start' }}>
          <AccountSidebar active={active} />
          <section style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            {children}
          </section>
        </div>
      </main>
    </div>
  );
}

// ----- Order list ----------------------------------------------------------
function OrderStatus({ status }) {
  const map = {
    'processing': { label: 'В обробці',  bg: 'var(--bs-warning-bg)', fg: 'var(--bs-warning-fg)' },
    'shipped':    { label: 'Відправлено', bg: 'var(--bs-blue-soft)', fg: 'var(--bs-blue)' },
    'delivered':  { label: 'Доставлено',  bg: '#dcfce7', fg: '#166534' },
    'cancelled':  { label: 'Відмінений',  bg: 'var(--bs-line-2)', fg: 'var(--bs-ink-3)' },
  }[status];
  return (
    <span style={{
      display: 'inline-block', padding: '4px 10px', borderRadius: 999,
      fontSize: 12, fontWeight: 600, background: map.bg, color: map.fg,
    }}>{map.label}</span>
  );
}

function OrderListMock() {
  const orders = [
    { no: '#103', date: '21.05.2026', total: 700, items: 4, status: 'shipped',    ttn: '20451004123456' },
    { no: '#101', date: '18.05.2026', total: 450, items: 2, status: 'delivered',  ttn: '20451004113322' },
    { no: '#98',  date: '09.05.2026', total: 1,   items: 1, status: 'cancelled' },
  ];
  return (
    <AccountShell active="orders" title="Мої замовлення">
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        {orders.map(o => (
          <div key={o.no} className="bs-card" style={{ padding: 18 }}>
            <div style={{
              display: 'grid', gridTemplateColumns: '110px 1fr 1fr 1fr auto', gap: 18,
              alignItems: 'center',
            }}>
              <div>
                <div style={{ fontSize: 12, color: 'var(--bs-ink-3)' }}>Замовлення</div>
                <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--bs-ink)', marginTop: 2 }}>{o.no}</div>
              </div>
              <div>
                <div style={{ fontSize: 12, color: 'var(--bs-ink-3)' }}>Дата</div>
                <div style={{ fontSize: 14, color: 'var(--bs-ink)', marginTop: 2 }}>{o.date}</div>
              </div>
              <div>
                <div style={{ fontSize: 12, color: 'var(--bs-ink-3)' }}>Сума</div>
                <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--bs-ink)', marginTop: 2 }}>
                  ₴{o.total} <span style={{ color: 'var(--bs-ink-3)', fontWeight: 500, fontSize: 12 }}>· {o.items} шт.</span>
                </div>
              </div>
              <div>
                <OrderStatus status={o.status} />
                {o.ttn && (
                  <div style={{
                    fontSize: 11, color: 'var(--bs-ink-3)',
                    fontFamily: '"JetBrains Mono", ui-monospace, monospace',
                    marginTop: 4,
                  }}>ТТН {o.ttn}</div>
                )}
              </div>
              <button className="bs-btn bs-btn-secondary" style={{ padding: '8px 12px' }}>
                Деталі →
              </button>
            </div>
          </div>
        ))}
      </div>
    </AccountShell>
  );
}

// ----- Order detail --------------------------------------------------------
function OrderDetailMock() {
  return (
    <AccountShell active="orders" title="Замовлення #103">
      <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
        {/* Status banner */}
        <div className="bs-card" style={{
          padding: 18,
          display: 'flex', alignItems: 'center', gap: 16,
          background: 'var(--bs-blue-soft)', borderColor: '#c7d2fe',
        }}>
          <span style={{
            width: 38, height: 38, borderRadius: '50%',
            background: 'var(--bs-blue)', color: '#fff',
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
          }}><I.Truck width="18" height="18" /></span>
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--bs-ink)' }}>Замовлення відправлене</div>
            <div style={{ fontSize: 13, color: 'var(--bs-ink-2)', marginTop: 2 }}>
              ТТН Нової пошти: <strong>20451004123456</strong> · Очікувана дата: <strong>24.05.2026</strong>
            </div>
          </div>
          <button className="bs-btn bs-btn-secondary" style={{ borderColor: 'var(--bs-blue)', color: 'var(--bs-blue)' }}>
            Відстежити →
          </button>
        </div>

        {/* Meta grid */}
        <div className="bs-card" style={{ padding: 22 }}>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 18 }}>
            {[
              { l: 'Дата',          v: '21.05.2026' },
              { l: 'Доставка',      v: 'Нова пошта · відділення' },
              { l: 'Оплата',        v: 'Карткою (Google Pay)' },
              { l: 'Адреса',        v: 'м. Дніпро, вул. Савкіна, Будинок 6' },
            ].map(x => (
              <div key={x.l}>
                <div style={{ fontSize: 11.5, color: 'var(--bs-ink-3)', textTransform: 'uppercase', letterSpacing: '.06em' }}>{x.l}</div>
                <div style={{ fontSize: 13.5, color: 'var(--bs-ink)', marginTop: 4, fontWeight: 500 }}>{x.v}</div>
              </div>
            ))}
          </div>
        </div>

        {/* Items */}
        <div className="bs-card" style={{ padding: 0 }}>
          <header style={{ padding: '16px 22px', borderBottom: '1px solid var(--bs-line)' }}>
            <h3 style={{ fontSize: 16 }}>Склад замовлення</h3>
          </header>
          <div>
            {[
              { title: 'Бустер Pokémon TCG: Mega Symphonia', sku: 'PKM-MS-JP-001', qty: 2, price: 150 },
              { title: 'Бустер One Piece Card Game OP-11',    sku: 'OP-OP11-JP',    qty: 2, price: 200 },
            ].map(it => (
              <div key={it.sku} style={{
                display: 'grid', gridTemplateColumns: '64px 1fr auto auto', gap: 16,
                padding: '14px 22px',
                borderBottom: '1px solid var(--bs-line-2)',
                alignItems: 'center',
              }}>
                <div style={{
                  width: 64, height: 64, borderRadius: 'var(--bs-r-sm)',
                  background: '#fff', border: '1px solid var(--bs-line)', overflow: 'hidden',
                }}>
                  <ImagePh ratio="1/1" radius="var(--bs-r-sm)" label="" />
                </div>
                <div>
                  <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--bs-ink)' }}>{it.title}</div>
                  <div style={{ fontSize: 12, color: 'var(--bs-ink-3)', marginTop: 2 }}>Артикул: {it.sku}</div>
                </div>
                <div style={{ fontSize: 13, color: 'var(--bs-ink-2)' }}>{it.qty} × ₴{it.price}</div>
                <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--bs-ink)' }}>₴{it.qty * it.price}</div>
              </div>
            ))}
          </div>
          <footer style={{
            padding: '14px 22px', display: 'flex', justifyContent: 'space-between',
            background: 'var(--bs-bg)',
          }}>
            <div style={{ fontSize: 13, color: 'var(--bs-ink-3)' }}>Доставка: Безкоштовно</div>
            <div style={{ fontSize: 17, fontWeight: 800, color: 'var(--bs-ink)' }}>До сплати: ₴700</div>
          </footer>
        </div>
      </div>
    </AccountShell>
  );
}

// ----- Address list --------------------------------------------------------
function AddressListMock() {
  const addresses = [
    { id: 1, label: 'Default', name: 'Євгеній Леусенко', addr: 'м. Дніпро, вул. Савкіна, Будинок 6', np: 'НП відд. №12' },
    { id: 2, label: null,      name: 'Євгеній Леусенко', addr: 'м. Київ, поштомат №501',            np: 'НП поштомат' },
  ];
  return (
    <AccountShell active="addresses" title="Адреси доставки">
      <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
        <button className="bs-btn bs-btn-primary">
          <I.Plus width="12" height="12" /> Нова адреса
        </button>
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 14 }}>
        {addresses.map(a => (
          <div key={a.id} className="bs-card" style={{ padding: 18, position: 'relative' }}>
            {a.label && (
              <span style={{
                position: 'absolute', top: 14, right: 14,
                padding: '2px 8px', borderRadius: 999,
                background: 'var(--bs-blue-soft)', color: 'var(--bs-blue)',
                fontSize: 11, fontWeight: 700, letterSpacing: '.06em',
              }}>{a.label}</span>
            )}
            <div style={{ fontSize: 11.5, color: 'var(--bs-ink-3)', textTransform: 'uppercase', letterSpacing: '.08em' }}>
              {a.np}
            </div>
            <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--bs-ink)', marginTop: 6 }}>{a.name}</div>
            <div style={{ fontSize: 13.5, color: 'var(--bs-ink-2)', marginTop: 6, lineHeight: 1.5 }}>{a.addr}</div>
            <div style={{ marginTop: 14, display: 'flex', gap: 8 }}>
              <button className="bs-btn bs-btn-secondary" style={{ padding: '8px 12px', fontSize: 13 }}>Редагувати</button>
              <button className="bs-btn bs-btn-ghost" style={{ color: 'var(--bs-danger)', padding: '8px 12px', fontSize: 13 }}>
                Видалити
              </button>
            </div>
          </div>
        ))}
      </div>
    </AccountShell>
  );
}

// ----- Account info edit ---------------------------------------------------
function AccountInfoMock() {
  return (
    <AccountShell active="info" title="Особисті дані">
      <div className="bs-card" style={{ padding: 22 }}>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
          <FormField label="Ім'я" required defaultValue="Євгеній" />
          <FormField label="Прізвище" required defaultValue="Леусенко" />
          <FormField label="E-Mail" required type="email" defaultValue="evgenij.leusenko@gmail.com" />
          <FormField label="Телефон" required defaultValue="+380 99 111 92 79" />
        </div>
        <div style={{
          marginTop: 22, paddingTop: 18, borderTop: '1px solid var(--bs-line)',
          display: 'flex', justifyContent: 'space-between', alignItems: 'center',
        }}>
          <a href="#" style={{ fontSize: 13.5, color: 'var(--bs-blue)' }}>← Повернутись</a>
          <button className="bs-btn bs-btn-primary">Зберегти зміни</button>
        </div>
      </div>
    </AccountShell>
  );
}

// ----- Login / Register ----------------------------------------------------
function LoginRegisterMock() {
  return (
    <div className="bs-mock">
      <HeaderV1 />
      <main style={{ maxWidth: 1080, margin: '0 auto', padding: '24px 32px 56px' }}>
        <nav style={{
          display: 'flex', alignItems: 'center', gap: 8,
          fontSize: 12.5, color: 'var(--bs-ink-3)', marginBottom: 14,
        }}>
          <a href="#" style={{ color: 'var(--bs-ink-3)', display: 'inline-flex' }}><I.Home width="12" height="12" /></a>
          <span>›</span>
          <span style={{ color: 'var(--bs-ink)' }}>Авторизація</span>
        </nav>

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18, alignItems: 'flex-start' }}>
          <div className="bs-card" style={{ padding: 26 }}>
            <h2 style={{ fontSize: 22 }}>Зареєстрований клієнт</h2>
            <p style={{ color: 'var(--bs-ink-3)', fontSize: 13.5, marginTop: 6, lineHeight: 1.55 }}>
              Увійдіть, щоб бачити історію замовлень, зберегти адреси та користуватись акційними цінами.
            </p>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14, marginTop: 18 }}>
              <FormField label="E-Mail" required type="email" placeholder="ваш@email" />
              <FormField label="Пароль" required type="password" placeholder="Пароль" />
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, color: 'var(--bs-ink-2)' }}>
                  <input type="checkbox" style={{ accentColor: 'var(--bs-blue)' }} /> Запам'ятати
                </label>
                <a href="#" style={{ fontSize: 13, color: 'var(--bs-blue)' }}>Забули пароль?</a>
              </div>
              <button className="bs-btn bs-btn-primary" style={{ padding: '12px', fontSize: 14 }}>
                Увійти
              </button>
              <div style={{
                fontSize: 11, color: 'var(--bs-ink-3)', textAlign: 'center', marginTop: 4,
              }}>захищено reCAPTCHA</div>
            </div>
          </div>

          <div className="bs-card" style={{ padding: 26 }}>
            <h2 style={{ fontSize: 22 }}>Новий клієнт</h2>
            <p style={{ color: 'var(--bs-ink-3)', fontSize: 13.5, marginTop: 6, lineHeight: 1.55 }}>
              Створіть акаунт, щоб купувати в один клік і не вводити дані щоразу.
              Сума від ₴1500 — безкоштовна доставка.
            </p>
            <ul style={{ listStyle: 'none', padding: 0, margin: '18px 0 22px', display: 'flex', flexDirection: 'column', gap: 8 }}>
              {[
                'Швидке оформлення замовлень',
                'Історія покупок і статуси посилок',
                'Збереження адрес і знижок',
              ].map(b => (
                <li key={b} style={{
                  display: 'flex', alignItems: 'center', gap: 10,
                  fontSize: 13.5, color: 'var(--bs-ink-2)',
                }}>
                  <span style={{
                    width: 18, height: 18, borderRadius: '50%',
                    background: 'var(--bs-blue-soft)', color: 'var(--bs-blue)',
                    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                    flex: '0 0 auto',
                  }}><I.Check width="10" height="10" /></span>
                  {b}
                </li>
              ))}
            </ul>
            <button className="bs-btn bs-btn-secondary" style={{
              padding: '12px', fontSize: 14, width: '100%',
              borderColor: 'var(--bs-blue)', color: 'var(--bs-blue)',
            }}>
              Зареєструватись →
            </button>
          </div>
        </div>
      </main>
    </div>
  );
}

Object.assign(window, {
  AccountSidebar, AccountShell,
  OrderListMock, OrderDetailMock, AddressListMock, AccountInfoMock,
  LoginRegisterMock,
});
