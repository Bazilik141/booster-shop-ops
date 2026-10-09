// Compose every mockup into the design canvas. Round 2: header V1 chosen,
// home stripped, product card simplified to one card with state-driven
// behaviour, and new sections for product page / cart / checkout / account /
// mobile views.

function ProductCardStates() {
  // Sample products covering all the cases we care about.
  const samples = [
    { product: PRODUCTS[0], label: 'Default — sealed (без бейджа)' },
    { product: PRODUCTS[9], label: 'Зі знижкою — sealed + −27%' },
    { product: PRODUCTS[5], label: 'Low Pull + знижка −47%' },
    { product: PRODUCTS[7], label: 'Передзамовлення' },
    { product: PRODUCTS[8], label: 'Немає в наявності' },
  ];
  return (
    <div className="bs-mock" style={{ padding: 28, background: 'var(--bs-bg)', minHeight: '100%' }}>
      <div style={{ marginBottom: 18, maxWidth: 720 }}>
        <h3 style={{ marginBottom: 6 }}>Картки товарів — стани</h3>
        <p style={{ fontSize: 13.5, color: 'var(--bs-ink-3)' }}>
          Один шаблон картки, поведінка змінюється від <code style={{ background: 'var(--bs-paper)', padding: '1px 6px', borderRadius: 4, border: '1px solid var(--bs-line)' }}>product.state</code>:
          sealed — без бейджа, бо це дефолт. Бейджі є тільки на винятках (Low Pull, передзамовлення, відсутність).
        </p>
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 14 }}>
        {samples.map((s, i) => (
          <div key={i} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <ProductCard product={s.product} />
            <div style={{
              fontFamily: '"JetBrains Mono", ui-monospace, monospace',
              fontSize: 10.5, color: 'var(--bs-ink-3)', letterSpacing: '.04em',
              textTransform: 'uppercase',
            }}>{s.label}</div>
          </div>
        ))}
      </div>

      <div style={{
        marginTop: 28, padding: 20, background: '#fff',
        border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r)',
        display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 24,
      }}>
        {[
          { h: 'Дефолт без бейджа', t: 'Sealed = асортимент. Не пиліться шумом, який нічого не повідомляє.' },
          { h: 'Бейджі-винятки', t: 'Low Pull · Передзамовлення · Немає в наявності — пліткають лише про реальні відхилення.' },
          { h: 'Sale-ієрархія', t: 'Нова ціна — червона, велика. Стара ціна — менша, ink-4 grey, перекреслена. Бейдж −% у TR куті чорний.' },
        ].map(n => (
          <div key={n.h}>
            <div style={{
              fontSize: 11, fontWeight: 700, letterSpacing: '.1em',
              color: 'var(--bs-ink-3)', textTransform: 'uppercase', marginBottom: 4,
            }}>{n.h}</div>
            <div style={{ fontSize: 13, color: 'var(--bs-ink-2)', lineHeight: 1.55 }}>{n.t}</div>
          </div>
        ))}
      </div>
    </div>
  );
}

function FoundationsArtboard() {
  return (
    <div style={{
      padding: 28, background: '#fff', height: '100%', boxSizing: 'border-box',
      fontFamily: '"Manrope", system-ui, sans-serif',
    }}>
      <div style={{ marginBottom: 20 }}>
        <div style={{
          fontFamily: '"JetBrains Mono", ui-monospace, monospace',
          fontSize: 11, letterSpacing: '.14em', color: 'var(--bs-blue)',
          textTransform: 'uppercase', marginBottom: 6,
        }}>Foundations · v2</div>
        <h2 style={{ marginBottom: 4 }}>Booster Shop — design tokens</h2>
        <p style={{ fontSize: 13, color: 'var(--bs-ink-3)', maxWidth: 680, lineHeight: 1.6 }}>
          Шпаргалка з токенів. Повна специфікація — у <code style={{ background: 'var(--bs-bg)', padding: '2px 5px', borderRadius: 4 }}>tokens.css</code> + <code style={{ background: 'var(--bs-bg)', padding: '2px 5px', borderRadius: 4 }}>audit.md</code>.
        </p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24 }}>
        <div>
          <h4 style={{ fontSize: 13, marginBottom: 10, color: 'var(--bs-ink)' }}>Кольори</h4>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10 }}>
            {[
              { name: 'bg',       hex: '#F7F7F5' },
              { name: 'paper',    hex: '#FFFFFF' },
              { name: 'line',     hex: '#E5E7EB' },
              { name: 'ink',      hex: '#111827' },
              { name: 'ink-2',    hex: '#1F2937' },
              { name: 'ink-3',    hex: '#6B7280' },
              { name: 'blue',     hex: '#1E3A8A' },
              { name: 'gold',     hex: '#D4A017' },
              { name: 'green',    hex: '#16A34A' },
              { name: 'pokemon',  hex: '#C68A00' },
              { name: 'onepiece', hex: '#1E40AF' },
              { name: 'danger',   hex: '#B91C1C' },
            ].map(c => (
              <div key={c.name}>
                <div style={{
                  height: 42, background: c.hex, borderRadius: 6,
                  border: ['paper'].includes(c.name) ? '1px solid var(--bs-line)' : 'none',
                }} />
                <div style={{ fontSize: 11, color: 'var(--bs-ink-2)', marginTop: 4, fontWeight: 600 }}>{c.name}</div>
                <div style={{ fontSize: 10, color: 'var(--bs-ink-3)', fontFamily: 'monospace' }}>{c.hex}</div>
              </div>
            ))}
          </div>
        </div>

        <div>
          <h4 style={{ fontSize: 13, marginBottom: 10, color: 'var(--bs-ink)' }}>Типографіка</h4>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <div><span style={{ fontSize: 32, fontWeight: 800, letterSpacing: '-0.02em' }}>H1 — 32/800</span></div>
            <div><span style={{ fontSize: 24, fontWeight: 700, letterSpacing: '-0.015em' }}>H2 — 24/700</span></div>
            <div><span style={{ fontSize: 18, fontWeight: 700 }}>H3 — 18/700</span></div>
            <div><span style={{ fontSize: 14.5 }}>Body — 14.5/400</span></div>
            <div style={{ color: 'var(--bs-ink-3)' }}><span style={{ fontSize: 13 }}>Secondary — 13/400</span></div>
          </div>

          <h4 style={{ fontSize: 13, marginTop: 18, marginBottom: 10, color: 'var(--bs-ink)' }}>Кнопки</h4>
          <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
            <button className="bs-btn bs-btn-primary">Купити</button>
            <button className="bs-btn bs-btn-secondary">До каталогу</button>
            <button className="bs-btn bs-btn-ghost">Відмінити</button>
          </div>

          <h4 style={{ fontSize: 13, marginTop: 18, marginBottom: 10, color: 'var(--bs-ink)' }}>Бейджі</h4>
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <StatePill state="low-pull" />
            <StatePill state="preorder" />
            <StatePill state="out" />
            <DiscountPill percent={25} />
          </div>
        </div>
      </div>
    </div>
  );
}

function App() {
  return (
    <DesignCanvas
      title="Booster Shop — UX/UI Audit"
      subtitle="Round 4 — approved. Головна: D · Quiet retailer. Категорія: redesigned header card. Preorder: lighter blue. Наступний крок — Twig handoff."
    >
      <DCSection
        id="foundations"
        title="0 · Foundations"
        subtitle="Палітра, типографіка, базові компоненти — джерело істини для всіх макетів."
      >
        <DCArtboard id="tokens" label="Tokens & primitives" width={1100} height={580}>
          <FoundationsArtboard />
        </DCArtboard>
      </DCSection>

      <DCSection
        id="header"
        title="1 · Шапка — V1 Minimal white (затверджено)"
        subtitle="Білий фон, gold-foam немає. Логотип залишається кольоровим."
      >
        <DCArtboard id="header-v1" label="V1 · Minimal white" width={1240} height={100}>
          <div className="bs-mock"><HeaderV1 /></div>
        </DCArtboard>
        <DCArtboard id="header-v2" label="V2 (alt) — utility + nav" width={1240} height={200}>
          <div className="bs-mock"><HeaderV2 /></div>
        </DCArtboard>
        <DCArtboard id="header-v3" label="V3 (alt) — quiet band" width={1240} height={180}>
          <div className="bs-mock"><HeaderV3 /></div>
        </DCArtboard>
      </DCSection>

      <DCSection
        id="home"
        title="2 · Головна сторінка — затверджено Variant D"
        subtitle="Тайли категорій — D · Quiet retailer index. Резервні варіанти A/B/C залишаються в артборді для референсу."
      >
        <DCArtboard id="home" label="Home — proposed v3 (D)" width={1240} height={1500}>
          <HomeMock />
        </DCArtboard>
        <DCArtboard id="tile-variants" label="Тайли — 4 варіанти (референс)" width={1100} height={1700}>
          <HomeTilesShowcase />
        </DCArtboard>
      </DCSection>

      <DCSection
        id="category"
        title="3 · Сторінка категорії — редизайн шапки"
        subtitle="Інтегрований header-card з 4px брендовою смугою. Hero-row — title+sort, toolbar-row — chips+active filters."
      >
        <DCArtboard id="category-sidebar" label="З правим сайдбаром" width={1240} height={1100}>
          <CategoryMock filters="sidebar" />
        </DCArtboard>
        <DCArtboard id="category-topbar" label="З topbar-фільтрами" width={1240} height={1100}>
          <CategoryMock filters="topbar" />
        </DCArtboard>
      </DCSection>

      <DCSection
        id="product"
        title="4 · Сторінка товару"
        subtitle="Галерея + summary + qty +/-. Trust facts тиха лінія. FAQ-lite + рекомендовані."
      >
        <DCArtboard id="product" label="Product page — desktop" width={1240} height={2200}>
          <ProductPageMock />
        </DCArtboard>
      </DCSection>

      <DCSection
        id="product-card"
        title="5 · Картки товарів — стани"
        subtitle="Default / Sale / Low Pull / Preorder / Out-of-stock. Без бейджів sealed/unweighed."
      >
        <DCArtboard id="cards" label="5 станів картки" width={1240} height={500}>
          <ProductCardStates />
        </DCArtboard>
      </DCSection>

      <DCSection
        id="cart"
        title="6 · Кошик"
        subtitle="Картки-рядки замість таблиці, прогрес безкоштовної доставки до ₴1500."
      >
        <DCArtboard id="cart-page" label="Cart page" width={1240} height={780}>
          <CartPageMock />
        </DCArtboard>
        <DCArtboard id="minicart" label="Mini-cart drawer" width={1240} height={720}>
          <MiniCartMock />
        </DCArtboard>
      </DCSection>

      <DCSection
        id="checkout"
        title="7 · Чекаут"
        subtitle="3 колонки: дані + доставка/оплата + замовлення. Тиха blue-soft auth-смужка зверху."
      >
        <DCArtboard id="checkout" label="Checkout — desktop" width={1240} height={1500}>
          <CheckoutPageMock />
        </DCArtboard>
      </DCSection>

      <DCSection
        id="account"
        title="8 · Особистий кабінет"
        subtitle="Картки замість OpenCart-таблиць. Спокійний нейтральний сайдбар, лівий «синій header» прибрано."
      >
        <DCArtboard id="login" label="Авторизація / Реєстрація" width={1240} height={520}>
          <LoginRegisterMock />
        </DCArtboard>
        <DCArtboard id="orders" label="Список замовлень" width={1240} height={680}>
          <OrderListMock />
        </DCArtboard>
        <DCArtboard id="order-detail" label="Деталь замовлення" width={1240} height={900}>
          <OrderDetailMock />
        </DCArtboard>
        <DCArtboard id="addresses" label="Адреси" width={1240} height={560}>
          <AddressListMock />
        </DCArtboard>
        <DCArtboard id="info" label="Особисті дані" width={1240} height={540}>
          <AccountInfoMock />
        </DCArtboard>
      </DCSection>

      <DCSection
        id="mobile"
        title="9 · Мобільні в'юхи"
        subtitle="Критичні мобільні екрани: категорія з фільтр-sheet, картка товару зі sticky add-to-cart, mini-cart."
      >
        <DCArtboard id="m-category" label="Категорія" width={420} height={770}>
          <Phone><MobileCategoryPage /></Phone>
        </DCArtboard>
        <DCArtboard id="m-product"  label="Товар + sticky CTA" width={420} height={770}>
          <Phone><MobileProductPage /></Phone>
        </DCArtboard>
        <DCArtboard id="m-cart" label="Mini-cart" width={420} height={770}>
          <Phone><MobileMiniCart /></Phone>
        </DCArtboard>
      </DCSection>

      <DCSection
        id="footer"
        title="10 · Footer"
        subtitle="Каталог · Інформація · Покупцю · Контакти. Розподіл лінків узгоджений."
      >
        <DCArtboard id="footer" label="Footer — proposed" width={1240} height={400}>
          <div className="bs-mock"><FooterMock /></div>
        </DCArtboard>
      </DCSection>

      <DCSection
        id="next"
        title="11 · Що далі"
        subtitle="Що уточнюємо після цього раунду, перш ніж переходити до twig-шаблонів."
      >
        <DCArtboard id="next" label="To-do list" width={760} height={520}>
          <NextSteps />
        </DCArtboard>
      </DCSection>
    </DesignCanvas>
  );
}

function NextSteps() {
  const todos = [
    { t: 'UX-004 — Sub-category chips + category header card (blue-soft active)', ref: 'IMPL #1' },
    { t: 'UX-037 — Empty states (cart / search 0 / category 0)', ref: 'IMPL #2 · NEW' },
    { t: 'UX-036 — /special: FAQ нижче товарів', ref: 'IMPL #3 · NEW' },
    { t: 'UX-038 — Sticky add-to-cart (mobile)', ref: 'IMPL #4a · NEW' },
    { t: 'UX-017 — Transactional emails у DS-палітрі', ref: 'IMPL #4b' },
    { t: 'TMPL — Twig-шаблони під OpenCart 4', ref: 'IMPL #5' },
    { t: 'TECH-018 — Microsoft Clarity heatmap + scroll-depth', ref: 'IMPL #6 · post-launch' },
  ];
  return (
    <div style={{
      padding: 28, background: '#fff', height: '100%', boxSizing: 'border-box',
      fontFamily: '"Manrope", system-ui, sans-serif',
    }}>
      <h3 style={{ marginBottom: 6 }}>Handoff порядок — Codex / Twig</h3>
      <p style={{ fontSize: 13, color: 'var(--bs-ink-3)', marginBottom: 16 }}>
        Зафіксовано в audit.md · Розділ «Round-4 Approved Decisions».
      </p>
      <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 10 }}>
        {todos.map((t, i) => (
          <li key={i} style={{
            display: 'flex', gap: 12,
            paddingBottom: 10, borderBottom: '1px solid var(--bs-line-2)',
          }}>
            <span style={{
              flex: '0 0 22px', width: 22, height: 22, borderRadius: '50%',
              background: 'var(--bs-blue-soft)', color: 'var(--bs-blue)',
              display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
              fontSize: 11, fontWeight: 800,
            }}>{i + 1}</span>
            <div>
              <div style={{ fontSize: 13.5, color: 'var(--bs-ink)', fontWeight: 500 }}>{t.t}</div>
              <div style={{
                fontFamily: '"JetBrains Mono", ui-monospace, monospace',
                fontSize: 11, color: 'var(--bs-ink-3)', marginTop: 2,
              }}>{t.ref}</div>
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}

ReactDOM.createRoot(document.body.appendChild(document.createElement('div')))
  .render(<App />);
