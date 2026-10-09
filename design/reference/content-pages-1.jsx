// Pages 1–3: Order Success, Гарантія оригінальності, Про нас.
// All use the shared PageShell + Hero + Section pattern. Each page is a top-
// level component that mounts into PageShell.

// ─────────────────────────────────────────────────────────────────────────────
// 1. ORDER SUCCESS — "Замовлення прийнято"
// Replaces the current bare-text page with a confirmation hero + summary card
// + a 3-step "what happens next" timeline + a quiet trust note. Single green
// CTA = "Продовжити покупки" (back to catalog).
// ─────────────────────────────────────────────────────────────────────────────
function OrderSuccessPage() {
  const order = {
    id: 'BS-2026-04812',
    total: 700,
    items: [
      { title: 'Бустер Pokémon TCG: Mega Symphonia (Японське видання)', qty: 2, sum: 300 },
      { title: 'Бустер One Piece Card Game OP-11 (Японське видання)',  qty: 2, sum: 400 },
    ],
    shipping: 'Нова пошта — у відділення №31, Дніпро',
    payment: 'Оплата карткою',
  };

  const steps = [
    { n: 1, title: 'Збираємо ваш лут' },
    { n: 2, title: 'Пакуємо та відправляємо' },
    { n: 3, title: 'Надсилаємо фіскальний чек' },
  ];

  return (
    <PageShell>
      <Crumbs trail={['Кошик', 'Оформити замовлення', 'Замовлення прийнято']} />

      {/* Confirmation hero — green stripe + check icon */}
      <section style={{
        display: 'grid', gridTemplateColumns: '4px 1fr', overflow: 'hidden',
        background: '#fff', border: '1px solid var(--bs-line)',
        borderRadius: 'var(--bs-r)', marginBottom: 28,
      }}>
        <div style={{ background: 'var(--bs-green)' }} />
        <div style={{ padding: '32px 36px', display: 'flex', alignItems: 'center', gap: 26 }}>
          <div style={{
            width: 76, height: 76, borderRadius: '50%',
            background: '#DCFCE7', color: 'var(--bs-green)',
            display: 'flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 auto',
          }}>
            <I.Check width="36" height="36" />
          </div>
          <div style={{ flex: 1 }}>
            <Eyebrow tone="gold">Замовлення №{order.id}</Eyebrow>
            <h1 style={{
              fontSize: 36, fontWeight: 800, letterSpacing: '-0.02em',
              margin: '10px 0 8px', color: 'var(--bs-ink)', lineHeight: 1.1,
            }}>
              Дякуємо, замовлення в грі.
            </h1>
            <p style={{ fontSize: 15.5, color: 'var(--bs-ink-2)', margin: 0, lineHeight: 1.55 }}>
              Уже збираємо ваш лут. Статус замовлення можна перевірити в{' '}
              <a href="#" style={{ color: 'var(--bs-blue)', fontWeight: 600 }}>історії замовлень</a>.
            </p>
          </div>
        </div>
      </section>

      <div style={{ display: 'grid', gridTemplateColumns: '1.5fr 1fr', gap: 24, alignItems: 'flex-start' }}>
        {/* Left: timeline */}
        <div>
          <Eyebrow>Що далі</Eyebrow>
          <h2 style={{ fontSize: 22, fontWeight: 700, margin: '8px 0 22px', color: 'var(--bs-ink)' }}>
            Кроки, які ми робимо для вас
          </h2>

          <ol style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 10 }}>
            {steps.map((s, i) => (
              <li key={s.n} style={{
                display: 'grid', gridTemplateColumns: '40px 1fr', gap: 18,
                background: '#fff', border: '1px solid var(--bs-line)',
                borderRadius: 'var(--bs-r)', padding: '14px 18px',
                alignItems: 'center',
              }}>
                <div style={{
                  width: 40, height: 40, borderRadius: '50%',
                  background: i === 0 ? 'var(--bs-ink)' : 'var(--bs-bg)',
                  color: i === 0 ? '#fff' : 'var(--bs-ink-3)',
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  fontSize: 14, fontWeight: 800, letterSpacing: '-0.01em',
                  border: i === 0 ? '0' : '1px solid var(--bs-line)',
                }}>{s.n}</div>
                <div style={{ fontSize: 15, fontWeight: 600, color: 'var(--bs-ink)' }}>
                  {s.title}
                </div>
              </li>
            ))}
          </ol>

          <Callout icon={<C.Phone />} tone="grey" title="Без зайвих дзвінків">
            Ми не телефонуємо й не пишемо без потреби. Якщо в замовленні все заповнено коректно —
            просто тихо й швидко відправляємо.
          </Callout>

          <div style={{ display: 'flex', gap: 10, marginTop: 24, flexWrap: 'wrap' }}>
            <a className="bs-btn bs-btn-primary" href="#" style={{ minWidth: 200 }}>
              Продовжити покупки →
            </a>
            <a className="bs-btn bs-btn-secondary" href="#">
              Перейти в кабінет
            </a>
          </div>
        </div>

        {/* Right: order summary card */}
        <aside className="bs-card" style={{ padding: 22, position: 'sticky', top: 16 }}>
          <Eyebrow>Замовлення</Eyebrow>
          <h3 style={{ fontSize: 17, fontWeight: 700, margin: '8px 0 18px', color: 'var(--bs-ink)' }}>
            №{order.id}
          </h3>

          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            {order.items.map((it, i) => (
              <div key={i} style={{
                display: 'grid', gridTemplateColumns: '1fr auto', gap: 12,
                paddingBottom: 12,
                borderBottom: i < order.items.length - 1 ? '1px solid var(--bs-line-2)' : 'none',
              }}>
                <div style={{ minWidth: 0 }}>
                  <div style={{
                    fontSize: 13, fontWeight: 600, color: 'var(--bs-ink)',
                    lineHeight: 1.4,
                    display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical',
                    overflow: 'hidden',
                  }}>{it.title}</div>
                  <div style={{ fontSize: 11.5, color: 'var(--bs-ink-3)', marginTop: 2 }}>× {it.qty}</div>
                </div>
                <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--bs-ink)' }}>₴{it.sum}</div>
              </div>
            ))}
          </div>

          <div style={{
            marginTop: 14, paddingTop: 14, borderTop: '1px solid var(--bs-line)',
            display: 'flex', justifyContent: 'space-between',
            fontSize: 17, fontWeight: 800, color: 'var(--bs-ink)',
          }}>
            <span>Сплачено</span><span>₴{order.total}</span>
          </div>

          <div style={{
            marginTop: 16, paddingTop: 16, borderTop: '1px solid var(--bs-line-2)',
            display: 'flex', flexDirection: 'column', gap: 10,
            fontSize: 12.5, color: 'var(--bs-ink-2)', lineHeight: 1.5,
          }}>
            <div style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
              <span style={{ color: 'var(--bs-ink-3)', flex: '0 0 70px' }}>Доставка</span>
              <span>{order.shipping}</span>
            </div>
            <div style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
              <span style={{ color: 'var(--bs-ink-3)', flex: '0 0 70px' }}>Оплата</span>
              <span>{order.payment}</span>
            </div>
          </div>
        </aside>
      </div>

      <TelegramCard />
    </PageShell>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. ГАРАНТІЯ ОРИГІНАЛЬНОСТІ
// Long-form trust page. Sticky TOC + sectioned body + key-fact callouts.
// ─────────────────────────────────────────────────────────────────────────────
function GuaranteePage() {
  const sections = [
    { id: 'source', label: 'Звідки товар' },
    { id: 'sealed', label: 'Що означає sealed' },
    { id: 'unweighed', label: 'Що означає unweighed' },
    { id: 'lowpull', label: 'Low pull і чому він існує' },
    { id: 'boxes', label: 'Бокси та запечатані набори' },
    { id: 'chances', label: 'Про шанси' },
    { id: 'trust', label: 'Довіра' },
  ];

  return (
    <PageShell>
      <Crumbs trail={['Гарантія оригінальності']} />

      <Hero
        accent="gold"
        icon={<I.Shield />}
        title="Гарантія оригінальності"
        lede="В TCG довіра — це головне. Нижче — факти про те, звідки приїжджає товар, як читати маркування в назвах та які саме гарантії ви отримуєте."
      />

      <ContentLayout toc={<TOC items={sections} />}>
        <Section id="source" padTop={false}>
          <H2 eyebrow="01">Звідки товар</H2>
          <P>Поставки формуються з перевірених джерел у Японії та Кореї: локальних магазинів, маркетплейсів, аукціонів і посередників, які працюють з оригінальною TCG-продукцією.</P>
          <P muted>Ми не працюємо із сумнівними джерелами, де неможливо перевірити походження товару.</P>
        </Section>

        <Section id="sealed">
          <H2 eyebrow="02">Що означає sealed</H2>
          <Def term="Sealed">це заводське пакування. Товар не розкривався й не змінювався. Ви отримуєте його в тому вигляді, в якому він був випущений виробником.</Def>
          <Callout icon={<C.Box />} title="Sealed — це базовий стандарт">
            У нашому асортименті sealed-стан є замовчуванням для всіх бустерів і боксів. Це не «опція» — це норма.
          </Callout>
        </Section>

        <Section id="unweighed">
          <H2 eyebrow="03">Що означає unweighed</H2>
          <Def term="Unweighed">бустер не зважувався й не відбирався вручну. Поштучні бустери продаються без перевідбору — ви отримуєте випадковий пак із недоторканого пулу зі збереженням заводських шансів на рідкісні карти.</Def>
        </Section>

        <Section id="lowpull">
          <H2 eyebrow="04">Low pull і чому він існує</H2>
          <Def term="Low pull">бустери, які закуповуються окремо, без гарантії високої рідкісності. Вони дешевші в закупці, тому продаються за нижчою ціною.</Def>
          <P>Такий формат підходить, якщо хочеться просто відкрити бустери, зібрати базові карти або взяти товар дешевше — без очікування дорогих «хітів».</P>
          <Callout icon={<C.Sparkle />} tone="gold" title="Завжди позначається в назві">
            Low pull завжди прямо вказується в назві та описі товару. Він не продається під виглядом звичайних бустерів.
          </Callout>
        </Section>

        <Section id="boxes">
          <H2 eyebrow="05">Бокси та запечатані набори</H2>
          <P>Запечатані бокси та набори продаються в заводській плівці без втручання. Ви отримуєте їх у первозданному вигляді — без розкриття й без змін.</P>
        </Section>

        <Section id="chances">
          <H2 eyebrow="06">Про шанси</H2>
          <P>Сам виробник не закладає у бустерах гарантії конкретної карти. Результат відкриття залежить від розподілу, закладеного виробником.</P>
          <P>У запечатаних боксах виробник зазвичай закладає гарантований розподіл кількості рідкісних карт у межах боксу — але це не означає гарантію конкретної карти.</P>
          <Callout icon={<C.Yen />} tone="grey">
            Шанси — це частина гри. Ми чесно про них пишемо, але не обіцяємо «гарантований hit».
          </Callout>
        </Section>

        <Section id="trust">
          <H2 eyebrow="07">Довіра</H2>
          <P>За потреби можемо підтвердити стан і вигляд товару перед відправкою фото або відео. Також у нас є історія продажів і відгуки на зовнішніх платформах.</P>
          <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginTop: 8 }}>
            <a className="bs-btn bs-btn-blue-outline" href="#">Переглянути відгуки в Telegram</a>
            <a className="bs-btn bs-btn-secondary" href="#">Профіль на OLX</a>
          </div>
        </Section>

        <TelegramCard />
      </ContentLayout>
    </PageShell>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. ПРО НАС
// Editorial layout: hero + 3-stat row + sectioned story + pillar cards + contact card.
// ─────────────────────────────────────────────────────────────────────────────
function AboutPage() {
  const pillars = [
    { icon: <C.Box />,    title: 'Оригінал, не repack', desc: 'Поставки з перевірених джерел. Без перепакування й без «random» товару без зрозумілого походження.' },
    { icon: <C.Doc />,    title: 'Чесний опис',        desc: 'Sealed, unweighed, low pull, promo — все це прямо позначається в назві та описі. Ніяких прихованих умов.' },
    { icon: <C.Clock />,  title: 'Швидка відправка',   desc: 'У більшості випадків — той самий день. Ми розуміємо, що чекати бустери важче, ніж відкривати їх.' },
  ];

  const sections = [
    { id: 'sell',  label: 'Що ми продаємо' },
    { id: 'how',   label: 'Як ми працюємо' },
    { id: 'who',   label: 'Для кого Booster Shop' },
    { id: 'contact', label: 'Зв\u2019язок' },
  ];

  return (
    <PageShell>
      <Crumbs trail={['Про нас']} />

      <Hero
        accent="gold"
        icon={<I.Star />}
        title="Від колекціонера для колекціонерів"
        lede="Booster Shop — магазин оригінальних бустерів Pokémon TCG, One Piece Card Game та інших колекційних карткових ігор для тих, хто цінує справжній sealed-продукт, чесний опис товару й задоволення від процесу анпакінгу."
      />

      <StatRow stats={[
        { value: '100%', label: 'Sealed-продукт з перевірених джерел' },
        { value: 'Той самий день', label: 'Більшість замовлень відправляється Новою Поштою' },
        { value: 'Без repack', label: 'Не торгуємо підробками і перепакованою продукцією' },
      ]} />

      <ContentLayout toc={<TOC items={sections} />}>
        <Section id="sell" padTop={false}>
          <H2 eyebrow="01">Що ми продаємо</H2>
          <P>
            Основний фокус магазину — <strong>Pokémon TCG</strong> і <strong>One Piece Card Game</strong>:
            японські бустери, booster boxes, promo packs, корейські релізи, окремі sealed-продукти
            та колекційні позиції.
          </P>
          <P>
            Надалі Booster Shop буде розширювати асортимент: нові ККГ, інші мовні видання, аксесуари
            для колекціонерів і гри, а також додаткові формати для тих, хто хоче розвивати свою
            колекцію або комфортно заходити в хобі з нуля.
          </P>
          <Callout icon={<C.Box />} tone="grey">
            Ми працюємо з оригінальною продукцією й не продаємо підробки, repack або сумнівні «рандомні» товари без зрозумілого походження.
          </Callout>
        </Section>

        <Section id="how">
          <H2 eyebrow="02">Як ми працюємо</H2>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 12, margin: '14px 0 20px' }}>
            {pillars.map((p, i) => (
              <article key={i} style={{
                background: '#fff', border: '1px solid var(--bs-line)',
                borderRadius: 'var(--bs-r)', padding: '18px 18px 20px',
              }}>
                <div style={{
                  width: 36, height: 36, borderRadius: 'var(--bs-r-sm)',
                  background: 'var(--bs-gold-soft)', color: 'var(--bs-gold)',
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  marginBottom: 12,
                }}>{React.cloneElement(p.icon, { width: 18, height: 18 })}</div>
                <div style={{ fontSize: 14.5, fontWeight: 700, color: 'var(--bs-ink)', marginBottom: 6 }}>
                  {p.title}
                </div>
                <div style={{ fontSize: 13, color: 'var(--bs-ink-2)', lineHeight: 1.55 }}>
                  {p.desc}
                </div>
              </article>
            ))}
          </div>
          <P>
            На карточках товару завжди вказується формат продукту, мова видання, стан, тип пакування
            та інші важливі характеристики. Якщо бустер продається без зважування — ми прямо пишемо
            <strong> Unweighed</strong>. Якщо це low pull, promo pack або інший особливий формат —
            це також зазначається в описі.
          </P>
          <P muted>Ми не обіцяємо «гарантований hit» і не створюємо завищених очікувань там, де їх не повинно бути — ваші шанси завжди залежать лише від випадковості, закладеної виробником.</P>
        </Section>

        <Section id="who">
          <H2 eyebrow="03">Для кого Booster Shop</H2>
          <P>Для колекціонерів. Для тих, хто любить opening sessions. Для фанатів Pokémon і One Piece. Для подарунків. Для sealed-колекцій. А також для новачків, які тільки хочуть зайти у світ TCG, відкривши свій перший бустер.</P>
        </Section>

        <Section id="contact">
          <H2 eyebrow="04">Зв'язок</H2>
          <div style={{
            display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12,
            background: '#fff', border: '1px solid var(--bs-line)',
            borderRadius: 'var(--bs-r)', overflow: 'hidden',
          }}>
            <a href="#" style={{
              padding: '20px 22px', display: 'grid', gridTemplateColumns: '36px 1fr',
              gap: 14, alignItems: 'center', textDecoration: 'none', color: 'inherit',
              borderRight: '1px solid var(--bs-line-2)',
            }}>
              <span style={{
                width: 36, height: 36, borderRadius: 'var(--bs-r-sm)',
                background: '#229ED9', color: '#fff',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
              }}><I.Tg width="18" height="18" /></span>
              <div>
                <div style={{ fontSize: 12, color: 'var(--bs-ink-3)', textTransform: 'uppercase', letterSpacing: '.05em' }}>Telegram</div>
                <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--bs-ink)', marginTop: 2 }}>@boostershop_tcg</div>
              </div>
            </a>
            <a href="tel:+380636743252" style={{
              padding: '20px 22px', display: 'grid', gridTemplateColumns: '36px 1fr',
              gap: 14, alignItems: 'center', textDecoration: 'none', color: 'inherit',
            }}>
              <span style={{
                width: 36, height: 36, borderRadius: 'var(--bs-r-sm)',
                background: 'var(--bs-bg)', color: 'var(--bs-ink-2)',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
              }}><C.Phone width="18" height="18" /></span>
              <div>
                <div style={{ fontSize: 12, color: 'var(--bs-ink-3)', textTransform: 'uppercase', letterSpacing: '.05em' }}>Телефон</div>
                <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--bs-ink)', marginTop: 2 }}>+38 063 674 32 52</div>
              </div>
            </a>
          </div>
          <p style={{ fontSize: 13, color: 'var(--bs-ink-3)', marginTop: 14 }}>
            Booster Shop — від колекціонера для колекціонерів.
          </p>
        </Section>
      </ContentLayout>
    </PageShell>
  );
}

Object.assign(window, { OrderSuccessPage, GuaranteePage, AboutPage });
