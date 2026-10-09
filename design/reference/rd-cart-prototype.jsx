// Prototype shell: device frame, scenario controls, catalog page, routing between surfaces.
function CatalogPage({ mobile, onAdd }) {
  return (
    <div style={{ padding: mobile ? 16 : 24, paddingBottom: 40 }}>
      <div style={{ fontSize: 12.5, color: 'var(--bs-ink-3)', marginBottom: 10 }}>Головна › Pokémon TCG</div>
      <h1 style={{ fontSize: mobile ? 22 : 28, fontWeight: 800, color: 'var(--bs-ink)', letterSpacing: '-0.02em', marginBottom: 4 }}>Pokémon TCG</h1>
      <p style={{ fontSize: 13.5, color: 'var(--bs-ink-3)', marginBottom: 18 }}>Сторінка-контекст. Натисніть «Додати в кошик» — щоб побачити тост.</p>
      <div style={{ display: 'grid', gridTemplateColumns: mobile ? 'repeat(2,minmax(0,1fr))' : 'repeat(3,minmax(0,1fr))', gap: mobile ? 12 : 16 }}>
        {CATALOG.map(p => (
          <div key={p.id} className="bs-card" style={{ padding: 12, display: 'grid', gap: 10, alignContent: 'start' }}>
            <ImgPh w="100%" h={mobile ? 120 : 170} label="product shot" />
            <div style={{ fontSize: 13.5, fontWeight: 600, color: 'var(--bs-ink)', lineHeight: 1.4, display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden', minHeight: 38 }}>{p.title}</div>
            <div style={{ fontSize: 16, fontWeight: 800, color: 'var(--bs-ink)' }}>{money(p.price)}</div>
            <button className="bs-btn bs-btn-primary" onClick={() => onAdd(p)} style={{ minHeight: 44, height: 'auto', width: '100%', fontSize: 13.5, whiteSpace: 'normal', textAlign: 'center', padding: '8px 10px' }}>{p.stock ? 'Додати в кошик' : 'Немає в наявності'}</button>
          </div>
        ))}
      </div>
    </div>
  );
}

function Stage({ mobile, children }) {
  const wrapRef = React.useRef(null);
  const [scale, setScale] = React.useState(1);
  const W = mobile ? 390 : 1180, H = mobile ? 780 : 760;
  React.useEffect(() => {
    const el = wrapRef.current; if (!el) return;
    const fit = () => { const w = el.clientWidth; if (w > 0) setScale(Math.min(1, w / W)); };
    fit();
    const raf = requestAnimationFrame(fit);
    const ro = new ResizeObserver(fit); ro.observe(el);
    return () => { cancelAnimationFrame(raf); ro.disconnect(); };
  }, [W]);
  return (
    <div ref={wrapRef} style={{ width: '100%', minWidth: 0, overflow: 'hidden', height: H * (scale || 1), display: 'flex', justifyContent: 'center' }}>
      <div style={{ width: W, height: H, transform: 'scale(' + scale + ')', transformOrigin: 'top center', position: 'relative', borderRadius: mobile ? 22 : 12, overflow: 'hidden', border: '1px solid var(--bs-line)', boxShadow: 'var(--bs-sh-pop)', background: 'var(--bs-bg)' }}>
        {children}
      </div>
    </div>
  );
}

const SCENES = {
  empty: [],
  one: [{ id: 'mega', title: CATALOG[0].title, price: 150, qty: 1, stock: true }],
  many: [
    { id: 'mega', title: CATALOG[0].title, price: 150, qty: 2, stock: true },
    { id: 'op11', title: CATALOG[1].title, price: 165, qty: 1, stock: true },
    { id: 'sleeves', title: CATALOG[3].title, price: 340, qty: 1, stock: true },
  ],
  near: [
    { id: 'box', title: CATALOG[5].title, price: 890, qty: 1, stock: true },
    { id: 'sleeves', title: CATALOG[3].title, price: 340, qty: 2, stock: true },
  ],
  full: [{ id: 'box', title: CATALOG[4].title, price: 4200, qty: 1, stock: true }],
};

function Ctl({ active, onClick, children }) {
  return <button onClick={onClick} style={{ height: 34, padding: '0 12px', borderRadius: 'var(--bs-r-sm)', fontSize: 12.5, fontWeight: 600, border: '1px solid ' + (active ? 'var(--bs-ink)' : 'var(--bs-line)'), background: active ? 'var(--bs-ink)' : '#fff', color: active ? '#fff' : 'var(--bs-ink-2)' }}>{children}</button>;
}

function CtlGroup({ label, children }) {
  return (
    <div style={{ display: 'grid', gap: 6 }}>
      <span style={{ fontSize: 10.5, fontWeight: 700, letterSpacing: '.08em', textTransform: 'uppercase', color: 'var(--bs-ink-4)' }}>{label}</span>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>{children}</div>
    </div>
  );
}

function App() {
  const [mobile, setMobile] = React.useState(true);
  const [route, setRoute] = React.useState('catalog');
  const [scene, setScene] = React.useState('many');
  const [items, setItems] = React.useState(SCENES.many);
  const [mini, setMini] = React.useState(false);
  const [toast, setToast] = React.useState(null);
  const [toastVariant, setToastVariant] = React.useState('strip');
  const [stockError, setStockError] = React.useState(false);
  const timer = React.useRef(null);

  const fire = (type, message) => {
    clearTimeout(timer.current);
    setToast({ type, message, k: Date.now() });
    if (type === 'success') timer.current = setTimeout(() => setToast(null), 4000);
  };
  const loadScene = (k) => { setScene(k); setItems(SCENES[k]); setStockError(false); setToast(null); };

  const add = (p) => {
    if (!p.stock) { fire('error', 'Товару «' + p.title + '» немає в наявності в потрібній кількості.'); return; }
    setItems(prev => prev.some(i => i.id === p.id)
      ? prev.map(i => i.id === p.id ? { ...i, qty: i.qty + 1 } : i)
      : [...prev, { id: p.id, title: p.title, price: p.price, qty: 1, stock: true }]);
    fire('success', 'Товар додано в кошик');
  };
  const setQty = (id, d) => setItems(prev => prev.map(i => i.id === id ? { ...i, qty: Math.max(1, i.qty + d) } : i));
  const remove = (id) => setItems(prev => prev.filter(i => i.id !== id));
  const count = items.reduce((s, i) => s + i.qty, 0);

  return (
    <div style={{ maxWidth: 1320, margin: '0 auto', padding: '24px 20px 56px', display: 'grid', gridTemplateColumns: 'minmax(0,1fr)', gap: 18 }}>
      <header style={{ display: 'grid', gap: 6 }}>
        <div style={{ fontSize: 11, fontWeight: 700, letterSpacing: '.1em', textTransform: 'uppercase', color: 'var(--bs-ink-4)' }}>RD-11 · RD-12 · Add-to-cart toast</div>
        <h1 style={{ fontSize: 26, fontWeight: 800, letterSpacing: '-0.02em', color: 'var(--bs-ink)' }}>Кошик, міні-кошик і повідомлення про додавання</h1>
        <p style={{ fontSize: 14, color: 'var(--bs-ink-3)', maxWidth: 760, lineHeight: 1.55 }}>Клікабельний прототип. Тільки токени <code>boostershop-ds.css</code>. Додайте товар у каталозі → зверху з’явиться смужка-тост; кошик у шапці відкриває шухляду (десктоп) або нижній аркуш (мобайл).</p>
      </header>

      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 20, padding: 14, background: '#fff', border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r)' }}>
        <CtlGroup label="Пристрій">
          <Ctl active={mobile} onClick={() => setMobile(true)}>Мобайл 390</Ctl>
          <Ctl active={!mobile} onClick={() => setMobile(false)}>Десктоп 1180</Ctl>
        </CtlGroup>
        <CtlGroup label="Екран">
          <Ctl active={route === 'catalog'} onClick={() => setRoute('catalog')}>Каталог</Ctl>
          <Ctl active={route === 'cart'} onClick={() => setRoute('cart')}>Сторінка кошика</Ctl>
        </CtlGroup>
        <CtlGroup label="Вміст кошика">
          <Ctl active={scene === 'empty'} onClick={() => loadScene('empty')}>Порожній</Ctl>
          <Ctl active={scene === 'one'} onClick={() => loadScene('one')}>1 товар</Ctl>
          <Ctl active={scene === 'many'} onClick={() => loadScene('many')}>3 товари</Ctl>
          <Ctl active={scene === 'near'} onClick={() => loadScene('near')}>Близько до порога</Ctl>
          <Ctl active={scene === 'full'} onClick={() => loadScene('full')}>Понад поріг</Ctl>
        </CtlGroup>
        <CtlGroup label="Стани">
          <Ctl onClick={() => setMini(true)}>Відкрити міні-кошик</Ctl>
          <Ctl onClick={() => fire('success', 'Товар додано в кошик')}>Тост: успіх</Ctl>
          <Ctl onClick={() => fire('error', 'Товару немає в наявності в потрібній кількості.')}>Тост: помилка</Ctl>
          <Ctl active={stockError} onClick={() => { setStockError(!stockError); setRoute('cart'); }}>Помилка складу в кошику</Ctl>
        </CtlGroup>
        {!mobile && (
          <CtlGroup label="Варіант тосту (десктоп)">
            <Ctl active={toastVariant === 'strip'} onClick={() => setToastVariant('strip')}>Смужка зверху</Ctl>
            <Ctl active={toastVariant === 'corner'} onClick={() => setToastVariant('corner')}>A · Куточка</Ctl>
            <Ctl active={toastVariant === 'inline'} onClick={() => setToastVariant('inline')}>B · Смужка по ширині контенту</Ctl>
            <Ctl active={toastVariant === 'anchored'} onClick={() => setToastVariant('anchored')}>C · Під кнопкою кошика</Ctl>
          </CtlGroup>
        )}
      </div>

      <Stage mobile={mobile}>
        <div className="bs-mock" style={{ position: 'absolute', inset: 0, overflowY: 'auto' }}>
          <ShopHeader mobile={mobile} count={count} onCart={() => setMini(true)} />
          {route === 'catalog'
            ? <CatalogPage mobile={mobile} onAdd={add} />
            : <CartPage mobile={mobile} items={items} stockError={stockError} onQty={setQty} onRemove={remove} onContinue={() => setRoute('catalog')} onCheckout={() => fire('success', 'Далі — оформлення замовлення (RD-13, поза скоупом)')} onAdd={add} />}
        </div>
        <div className="bs-mock" style={{ position: 'absolute', inset: 0, pointerEvents: 'none', zIndex: 40, background: 'transparent' }}>
          <div style={{ position: 'absolute', top: mobile ? 65 : 69, left: 0, right: 0, pointerEvents: 'none' }}>
            {(mobile || toastVariant === 'strip')
              ? <Toast key={toast && toast.k} toast={toast} onClose={() => setToast(null)} onOpenCart={() => { setToast(null); setMini(true); }} />
              : <DesktopToast key={toast && toast.k} toast={toast} variant={toastVariant} onClose={() => setToast(null)} onOpenCart={() => { setToast(null); setMini(true); }} />}
          </div>
          {route === 'cart' && mobile && <CartStickyBar items={items} blocked={stockError} onCheckout={() => fire('success', 'Далі — оформлення замовлення (RD-13, поза скоупом)')} />}
        </div>
        <div className="bs-mock" style={{ position: 'absolute', inset: 0, pointerEvents: mini ? 'auto' : 'none', zIndex: 50, background: 'transparent' }}>
          <MiniCart open={mini} mobile={mobile} items={items} onClose={() => setMini(false)} onQty={setQty} onRemove={remove}
            onCheckout={() => { setMini(false); fire('success', 'Далі — оформлення замовлення (RD-13, поза скоупом)'); }}
            onCartPage={() => { setMini(false); setRoute('cart'); }} />
        </div>
      </Stage>

      <SpecNotes />
    </div>
  );
}

Object.assign(window, { App, CatalogPage, Stage, SCENES });
