// Product page mockup (desktop). Mirrors the live structure:
// Breadcrumbs · Gallery + Summary · Tabs (Опис / Характеристики / Відгуки) ·
// FAQ accordion · Related products. Trust facts surface as a quiet line
// in the summary, not as a heavy SEO block — per UX-008.

function ImageGallery() {
  const [active, setActive] = React.useState(0);
  const thumbs = ['Фронт', 'Зворот', 'Бокс', 'Картки'];
  return (
    <div style={{ display: 'grid', gridTemplateColumns: '80px 1fr', gap: 14 }}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
        {thumbs.map((t, i) => (
          <button key={i} onClick={() => setActive(i)} style={{
            border: `1.5px solid ${i === active ? 'var(--bs-blue)' : 'var(--bs-line)'}`,
            background: '#fff', borderRadius: 'var(--bs-r-sm)',
            padding: 4, cursor: 'pointer',
            transition: 'border-color .15s',
          }}>
            <ImagePh ratio="1/1" radius="4px" label={t} style={{ fontSize: 9 }} />
          </button>
        ))}
      </div>
      <div className="bs-card" style={{ padding: 12 }}>
        <ImagePh ratio="1/1" radius="var(--bs-r-sm)" label={`Фото · ${thumbs[active]}`} />
      </div>
    </div>
  );
}

function QtyInput({ value, onChange }) {
  return (
    <div style={{
      display: 'inline-flex', alignItems: 'center',
      border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r-sm)',
      background: '#fff',
    }}>
      <button onClick={() => onChange(Math.max(1, value - 1))} style={{
        width: 44, height: 44, border: 0, background: 'transparent',
        color: 'var(--bs-ink-2)', cursor: 'pointer',
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      }}><I.Minus width="12" height="12" /></button>
      <input value={value} onChange={(e) => onChange(Math.max(1, +e.target.value || 1))} style={{
        width: 48, height: 44, border: 0, background: 'transparent',
        textAlign: 'center', font: 'inherit', fontSize: 15, fontWeight: 600,
        color: 'var(--bs-ink)', outline: 'none',
      }} />
      <button onClick={() => onChange(value + 1)} style={{
        width: 44, height: 44, border: 0, background: 'transparent',
        color: 'var(--bs-ink-2)', cursor: 'pointer',
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      }}><I.Plus width="12" height="12" /></button>
    </div>
  );
}

function ProductTabs() {
  const [tab, setTab] = React.useState('desc');
  const tabs = [
    { id: 'desc', label: 'Опис' },
    { id: 'specs', label: 'Характеристики' },
    { id: 'reviews', label: 'Відгуки (12)' },
  ];
  return (
    <div className="bs-card" style={{ padding: 0 }}>
      <div style={{
        display: 'flex', borderBottom: '1px solid var(--bs-line)',
        padding: '0 24px',
      }}>
        {tabs.map(t => (
          <button key={t.id} onClick={() => setTab(t.id)} style={{
            padding: '16px 20px 14px', border: 0, background: 'transparent',
            color: tab === t.id ? 'var(--bs-ink)' : 'var(--bs-ink-3)',
            fontSize: 14, fontWeight: 700, cursor: 'pointer',
            borderBottom: `2px solid ${tab === t.id ? 'var(--bs-blue)' : 'transparent'}`,
            marginBottom: -1,
            letterSpacing: '-0.005em',
          }}>{t.label}</button>
        ))}
      </div>
      <div style={{ padding: 24 }}>
        {tab === 'desc' && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 14, fontSize: 14.5, lineHeight: 1.65, color: 'var(--bs-ink-2)' }}>
            <h3 style={{ marginBottom: 0, fontSize: 18 }}>Оригінальний японський sealed-бустер Mega Brave</h3>
            <p>
              <strong>Mega Brave</strong> — сучасний японський сет <strong>Pokémon TCG</strong> із лінійки Mega Evolution.
              Цінують за повернення Mega-покемонів, колекційний потенціал та рідкісні chase-карти,
              які цікаво шукати під час відкриття.
            </p>
            <p>
              Оригінальний японський <strong>sealed-бустер</strong> містить <strong>5 карт</strong>, формат
              <strong> Japanese Edition</strong>, походить із повноцінних коробок/кейсів — не з випадкових
              розсипних партій.
            </p>
            <p>
              Усі бустери продаються <strong>без зважування</strong>. Ми не сортуємо та не вибираємо «гарні»
              позиції — отримуєте бустер у стані з фабрики виробника.
            </p>
          </div>
        )}
        {tab === 'specs' && (
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13.5 }}>
            <tbody>
              {[
                ['Бренд', 'Pokémon TCG'],
                ['Видання', 'Японське (JP)'],
                ['Сет', 'Mega Brave'],
                ['Кількість карт', '5'],
                ['Стан', 'Sealed · Unweighed'],
                ['Походження', 'Booster box / case'],
                ['Виробник', 'The Pokémon Company'],
              ].map(([k, v]) => (
                <tr key={k} style={{ borderBottom: '1px solid var(--bs-line-2)' }}>
                  <td style={{ padding: '10px 0', color: 'var(--bs-ink-3)', width: 180 }}>{k}</td>
                  <td style={{ padding: '10px 0', color: 'var(--bs-ink)', fontWeight: 500 }}>{v}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
        {tab === 'reviews' && (
          // Native reviews are sparse — redirect to where users *actually* write
          // about us. Two cards: Telegram група відгуків + OLX-профіль.
          <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            <p style={{ fontSize: 13, color: 'var(--bs-ink-3)', lineHeight: 1.6 }}>
              Відгуки про Booster Shop клієнти залишають у наших зовнішніх каналах, де вже
              склалася жива спільнота. Гортайте реальні думки в одному з місць нижче — або
              додайте свій після покупки.
            </p>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              {/* Telegram */}
              <a href="#" style={{
                display: 'flex', flexDirection: 'column', gap: 10,
                padding: 18, borderRadius: 'var(--bs-r)',
                background: '#fff', border: '1px solid var(--bs-line)',
                color: 'var(--bs-ink)', textDecoration: 'none',
                transition: 'border-color .15s',
              }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                  <span style={{
                    width: 38, height: 38, borderRadius: 10,
                    background: '#229ED9', color: '#fff',
                    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                  }}><I.Tg width="18" height="18" /></span>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontSize: 14, fontWeight: 700 }}>Telegram-група відгуків</div>
                    <div style={{ fontSize: 12, color: 'var(--bs-ink-3)' }}>@boostershop_reviews</div>
                  </div>
                  <div style={{ display: 'flex', gap: 1, color: 'var(--bs-gold)' }}>
                    {[1,2,3,4,5].map(i => <I.Star key={i} width="12" height="12" />)}
                  </div>
                </div>
                <p style={{ fontSize: 12.5, color: 'var(--bs-ink-2)', lineHeight: 1.55, margin: 0 }}>
                  Жива гілка, де клієнти викладають відео/фото відкритих бустерів та враження.
                </p>
                <span style={{
                  fontSize: 12.5, fontWeight: 600, color: 'var(--bs-blue)',
                  display: 'inline-flex', alignItems: 'center', gap: 4, marginTop: 4,
                }}>Відкрити Telegram →</span>
              </a>

              {/* OLX */}
              <a href="#" style={{
                display: 'flex', flexDirection: 'column', gap: 10,
                padding: 18, borderRadius: 'var(--bs-r)',
                background: '#fff', border: '1px solid var(--bs-line)',
                color: 'var(--bs-ink)', textDecoration: 'none',
              }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                  <span style={{
                    width: 38, height: 38, borderRadius: 10,
                    background: '#002F34', color: '#A1FF54',
                    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                    fontWeight: 800, fontSize: 14, letterSpacing: '-0.02em',
                  }}>OLX</span>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontSize: 14, fontWeight: 700 }}>Профіль на OLX</div>
                    <div style={{ fontSize: 12, color: 'var(--bs-ink-3)' }}>рейтинг 4.9 · 84 відгуки</div>
                  </div>
                  <div style={{ display: 'flex', gap: 1, color: 'var(--bs-gold)' }}>
                    {[1,2,3,4,5].map(i => <I.Star key={i} width="12" height="12" />)}
                  </div>
                </div>
                <p style={{ fontSize: 12.5, color: 'var(--bs-ink-2)', lineHeight: 1.55, margin: 0 }}>
                  Верифіковані відгуки покупців після реальної угоди через OLX-доставку.
                </p>
                <span style={{
                  fontSize: 12.5, fontWeight: 600, color: 'var(--bs-blue)',
                  display: 'inline-flex', alignItems: 'center', gap: 4, marginTop: 4,
                }}>Відкрити OLX →</span>
              </a>
            </div>

            <div style={{
              padding: '14px 18px',
              background: 'var(--bs-bg)', borderRadius: 'var(--bs-r-sm)',
              display: 'flex', alignItems: 'center', gap: 14,
            }}>
              <I.Tg width="16" height="16" style={{ color: 'var(--bs-blue)', flex: '0 0 auto' }} />
              <span style={{ flex: 1, fontSize: 13, color: 'var(--bs-ink-2)' }}>
                Купили бустер у нас? Напишіть відгук у Telegram-групі або на OLX — подякуємо бонусом.
              </span>
              <button className="bs-btn bs-btn-secondary" style={{ borderColor: 'var(--bs-blue)', color: 'var(--bs-blue)' }}>
                Як залишити відгук
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

function ProductFaqLite() {
  const items = [
    { q: 'Що означає sealed?', a: 'Sealed — оригінальний запакований бустер у заводській плівці, який не відкривався та не зважувався.' },
    { q: 'Що означає unweighed?', a: 'Unweighed означає, що бустер не проходив ручний перевідбір за вагою. Покупець отримує товар саме в тому стані, в якому він прийшов від постачальника.' },
    { q: 'Чи бустери закуповуються з box/case?', a: 'Так. Ми не торгуємо розсипом — усі sealed-бустери походять з повноцінних коробок/кейсів.' },
    { q: 'Чим Mega Brave відрізняється від Mega Symphonia?', a: 'Це два різні сети з лінійки Mega Evolution із відмінним пулом карт та chase-позиціями.' },
  ];
  const [open, setOpen] = React.useState(new Set([0]));
  const toggle = (i) => {
    const next = new Set(open);
    next.has(i) ? next.delete(i) : next.add(i);
    setOpen(next);
  };
  return (
    <section>
      <header style={{ display: 'flex', alignItems: 'baseline', gap: 12, marginBottom: 14 }}>
        <h3 style={{ margin: 0 }}>FAQ — про цей бустер</h3>
        <span style={{ fontSize: 12, color: 'var(--bs-ink-3)' }}>{items.length} питання</span>
      </header>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
        {items.map((it, i) => {
          const isOpen = open.has(i);
          return (
            <div key={i} className="bs-card" style={{
              borderColor: isOpen ? 'var(--bs-blue)' : 'var(--bs-line)',
              transition: 'border-color .15s',
            }}>
              <button onClick={() => toggle(i)} style={{
                width: '100%', background: 'transparent', border: 0, cursor: 'pointer',
                padding: '16px 20px',
                display: 'flex', alignItems: 'center', gap: 16,
                textAlign: 'left', font: 'inherit', color: 'var(--bs-ink)',
              }}>
                <span style={{ flex: 1, fontSize: 15, fontWeight: 600 }}>{it.q}</span>
                <span style={{
                  width: 26, height: 26,
                  display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                  color: isOpen ? 'var(--bs-blue)' : 'var(--bs-ink-3)',
                  transform: `rotate(${isOpen ? 180 : 0}deg)`,
                  transition: 'transform .25s, color .2s',
                }}>
                  <svg width="13" height="13" viewBox="0 0 14 14" fill="none">
                    <path d="M3 5l4 4 4-4" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"/>
                  </svg>
                </span>
              </button>
              {isOpen && (
                <div style={{
                  padding: '0 20px 18px', fontSize: 13.5, lineHeight: 1.65,
                  color: 'var(--bs-ink-2)',
                  borderTop: '1px solid var(--bs-line-2)', paddingTop: 14,
                }}>{it.a}</div>
              )}
            </div>
          );
        })}
      </div>
    </section>
  );
}

function ProductPageMock() {
  const [qty, setQty] = React.useState(1);
  return (
    <div className="bs-mock">
      <HeaderV1 />

      <main style={{ maxWidth: 1240, margin: '0 auto', padding: '20px 32px 56px' }}>
        {/* Breadcrumbs */}
        <nav style={{
          display: 'flex', alignItems: 'center', gap: 8,
          fontSize: 12.5, color: 'var(--bs-ink-3)', marginBottom: 18,
        }}>
          <a href="#" style={{ color: 'var(--bs-ink-3)', display: 'inline-flex' }}><I.Home width="12" height="12" /></a>
          <span>›</span>
          <a href="#" style={{ color: 'var(--bs-ink-3)' }}>Pokémon TCG</a>
          <span>›</span>
          <a href="#" style={{ color: 'var(--bs-ink-3)' }}>Бустери</a>
          <span>›</span>
          <span style={{ color: 'var(--bs-ink)' }}>Mega Brave</span>
        </nav>

        {/* Hero: gallery + summary */}
        <section style={{
          display: 'grid', gridTemplateColumns: '1.1fr 1fr', gap: 32,
          marginBottom: 32,
        }}>
          <ImageGallery />

          <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
            <div>
              <div style={{
                fontFamily: '"JetBrains Mono", ui-monospace, monospace',
                fontSize: 11, letterSpacing: '.14em', color: 'var(--bs-ink-3)',
                textTransform: 'uppercase', marginBottom: 8,
              }}>Pokémon TCG · Japanese Edition</div>
              <h1 style={{ fontSize: 28, lineHeight: 1.2 }}>
                Бустер Pokémon TCG: Mega Brave (Японське видання)
              </h1>
              <div style={{
                display: 'flex', alignItems: 'center', gap: 12,
                marginTop: 10, fontSize: 13, color: 'var(--bs-ink-3)',
              }}>
                <div style={{ display: 'flex', gap: 2, color: 'var(--bs-gold)' }}>
                  {[1,2,3,4,5].map(i => <I.Star key={i} width="13" height="13" />)}
                </div>
                <span>4.8 · відгуки в Telegram / OLX</span>
              </div>
            </div>

            {/* Stock signal */}
            <div style={{
              display: 'inline-flex', alignItems: 'center', gap: 8,
              fontSize: 13, color: 'var(--bs-green)', fontWeight: 600,
            }}>
              <span style={{ width: 8, height: 8, borderRadius: '50%', background: 'var(--bs-green)' }} />
              В наявності · 6 шт.
            </div>

            {/* Price */}
            <div style={{ padding: '14px 0', borderBlock: '1px solid var(--bs-line)' }}>
              <PriceRow price={150} size="lg" />
              <div style={{ fontSize: 12, color: 'var(--bs-ink-3)', marginTop: 6 }}>
                Знижка при покупці від 5 шт. — деталі у характеристиках
              </div>
            </div>

            {/* Add to cart row */}
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
              <QtyInput value={qty} onChange={setQty} />
              <button className="bs-btn bs-btn-primary" style={{
                flex: 1, padding: '14px 18px', fontSize: 15,
              }}>
                <I.Cart width="16" height="16" /> Додати в кошик
              </button>
            </div>

            {/* ПУМБ «Оплата частинами» — placeholder під майбутню інтеграцію. Коли
                модуль підключимо, блок покаже розплановку та відкриватиме банківський модал. */}
            <button style={{
              display: 'flex', alignItems: 'center', gap: 12,
              width: '100%', padding: '12px 14px',
              background: '#fff',
              border: '1px dashed var(--bs-line)',
              borderRadius: 'var(--bs-r-sm)', cursor: 'pointer', textAlign: 'left',
              font: 'inherit', color: 'var(--bs-ink-2)',
            }}>
              <span style={{
                fontFamily: '"Manrope", system-ui, sans-serif',
                fontWeight: 900, letterSpacing: '-0.04em',
                fontSize: 13, color: '#fff',
                background: '#7C3AED',
                padding: '4px 8px', borderRadius: 4,
                flex: '0 0 auto',
              }}>ПУМБ</span>
              <span style={{ flex: 1, fontSize: 13, lineHeight: 1.4 }}>
                <strong style={{ color: 'var(--bs-ink)' }}>Оплата частинами</strong> — від ₴50/міс · до 4 платежів без відсотків
              </span>
              <I.Chevron width="12" height="12" style={{ color: 'var(--bs-ink-3)', transform: 'rotate(-90deg)' }} />
            </button>

            {/* Trust line — quiet, no big block (UX-008) */}
            <div style={{
              display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10,
              padding: '14px 18px', background: 'var(--bs-bg)',
              borderRadius: 'var(--bs-r)',
            }}>
              {[
                { ic: <I.Pack width="14" height="14" />, t: 'Sealed з box/case' },
                { ic: <I.Shield width="14" height="14" />, t: 'Не зважуємо' },
                { ic: <I.Truck width="14" height="14" />, t: '~3 дні Новою поштою' },
              ].map((x, i) => (
                <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12, color: 'var(--bs-ink-2)', fontWeight: 500 }}>
                  <span style={{ color: 'var(--bs-blue)' }}>{x.ic}</span>{x.t}
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* Tabs */}
        <section style={{ marginBottom: 32 }}>
          <ProductTabs />
        </section>

        {/* FAQ */}
        <section style={{ marginBottom: 32 }}>
          <ProductFaqLite />
        </section>

        {/* Related */}
        <section style={{ marginBottom: 16 }}>
          <header style={{ marginBottom: 16, display: 'flex', alignItems: 'baseline', justifyContent: 'space-between' }}>
            <h2 style={{ fontSize: 22 }}>Можливо, вас зацікавить</h2>
            <a href="#" style={{ fontSize: 13.5, fontWeight: 600 }}>Усі бустери Pokémon →</a>
          </header>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 16 }}>
            {PRODUCTS.filter(p => p.brand === 'pokemon').slice(0, 4).map(p => (
              <ProductCard key={p.id} product={p} />
            ))}
          </div>
        </section>
      </main>
    </div>
  );
}

Object.assign(window, { ProductPageMock });
