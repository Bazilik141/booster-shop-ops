// Pages 4–6: Оплата і доставка, Обмін і повернення, Публічна оферта.
//
// Copy is drafted to match Booster Shop voice (sealed, чесний опис, Telegram-
// first, без зайвих дзвінків). The dev/founder should edit text directly in
// the source — the design system is the deliverable here, not the legal copy.

// ─────────────────────────────────────────────────────────────────────────────
// 4. ОПЛАТА І ДОСТАВКА
// Two parallel "blocks": Оплата (4 method cards) + Доставка (3 НП options).
// Plus: free-shipping threshold strip, processing time fact card, short FAQ.
// ─────────────────────────────────────────────────────────────────────────────
function MethodCard({ icon, title, lede, tags, fee }) {
  return (
    <article style={{
      background: '#fff', border: '1px solid var(--bs-line)',
      borderRadius: 'var(--bs-r)', padding: '18px 20px',
      display: 'flex', flexDirection: 'column', gap: 10,
      position: 'relative',
    }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
        <span style={{
          width: 36, height: 36, borderRadius: 'var(--bs-r-sm)',
          background: 'var(--bs-bg)', color: 'var(--bs-ink-2)',
          display: 'flex', alignItems: 'center', justifyContent: 'center',
        }}>{React.cloneElement(icon, { width: 18, height: 18 })}</span>
        <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--bs-ink)' }}>{title}</div>
        {fee && (
          <span style={{
            marginLeft: 'auto', fontSize: 12, color: 'var(--bs-ink-3)',
            fontFamily: '"JetBrains Mono", ui-monospace, monospace',
          }}>{fee}</span>
        )}
      </div>
      <p style={{ fontSize: 13.5, color: 'var(--bs-ink-2)', lineHeight: 1.55, margin: 0 }}>
        {lede}
      </p>
      {tags && (
        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 2 }}>
          {tags.map((t) => (
            <span key={t} style={{
              fontSize: 11, fontWeight: 600, color: 'var(--bs-ink-3)',
              background: 'var(--bs-bg)', padding: '3px 8px',
              borderRadius: 'var(--bs-r-pill)',
            }}>{t}</span>
          ))}
        </div>
      )}
    </article>
  );
}

function DeliveryPage() {
  const sections = [
    { id: 'pay',     label: 'Оплата' },
    { id: 'ship',    label: 'Доставка' },
    { id: 'time',    label: 'Терміни обробки' },
    { id: 'free',    label: 'Безкоштовна доставка' },
  ];

  return (
    <PageShell>
      <Crumbs trail={['Оплата і доставка']} />

      <Hero
        accent="blue"
        icon={<C.Card />}
        title="Оплата і доставка"
        lede="Як ви можете заплатити, як ми відправляємо замовлення, і скільки часу це займає. Нічого прихованого — комісій і додаткових платежів за обробку замовлення немає."
      />

      <ContentLayout toc={<TOC items={sections} />}>
        <Section id="pay" padTop={false}>
          <H2 eyebrow="01">Способи оплати</H2>
          <div style={{
            display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginTop: 16,
          }}>
            <MethodCard
              icon={<C.Card />}
              title="Карткою онлайн"
              lede="Visa, Mastercard, Google Pay, Apple Pay. Безпечно через еквайринг — реквізити картки магазину не передаються."
            />
            <MethodCard
              icon={<C.Box />}
              title="Післяплата Нової пошти"
              lede="Оплачуєте при отриманні у відділенні. Комісія Нової пошти за післяплату оплачується окремо за тарифами перевізника."
            />
            <MethodCard
              icon={<C.Yen />}
              title="IBAN — реквізити"
              lede="Платіж за реквізитами на IBAN. Підходить для юридичних осіб і великих замовлень. Реквізити надсилаємо після підтвердження."
            />
            <MethodCard
              icon={<C.Sparkle />}
              title="ПУМБ — оплата частинами"
              lede="До 4 платежів без відсотків. Доступно для замовлень від ₴500. Підключення — в процесі."
              tags={['Скоро']}
            />
          </div>
        </Section>

        <Section id="ship">
          <H2 eyebrow="02">Доставка</H2>
          <P>Ми відправляємо посилки переважно <strong>Новою Поштою</strong>. Однак заради любих клієнтів можемо розглянути інші поштові служби за вашим зверненням в <a href="#" style={{ color: 'var(--bs-blue)', fontWeight: 600 }}>підтримку</a>.</P>
          <P>Відправка замовлень іншими службами доставки можлива для замовлень від 1500 грн з оплатою доставки за наш кошт.</P>

          <div style={{ display: 'flex', flexDirection: 'column', gap: 10, marginTop: 14 }}>
            {[
              { title: 'У відділення НП', lede: 'Найдешевший варіант. Зазвичай ~2–4 дні. Підходить, якщо у вашому місті є зручне відділення.', tag: 'Найпопулярніше' },
              { title: 'Поштомат НП', lede: 'Цілодобовий самовивіз без черг і взаємодії з оператором. Вкажіть номер поштомата в коментарі до замовлення.', tag: null },
              { title: 'Адресна доставка НП', lede: 'Кур’єр Нової Пошти привезе замовлення на вказану адресу. Час прибуття — за домовленістю з кур’єром.', tag: null },
            ].map((d, i) => (
              <div key={i} style={{
                background: '#fff', border: '1px solid var(--bs-line)',
                borderRadius: 'var(--bs-r)', padding: '14px 18px',
                display: 'grid', gridTemplateColumns: '40px 1fr auto', gap: 16, alignItems: 'center',
              }}>
                <span style={{
                  width: 40, height: 40, borderRadius: 'var(--bs-r-sm)',
                  background: 'var(--bs-blue-soft)', color: 'var(--bs-blue)',
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                }}><I.Truck width="18" height="18" /></span>
                <div>
                  <div style={{ fontSize: 14.5, fontWeight: 700, color: 'var(--bs-ink)' }}>{d.title}</div>
                  <div style={{ fontSize: 13, color: 'var(--bs-ink-2)', lineHeight: 1.5, marginTop: 2 }}>{d.lede}</div>
                </div>
                {d.tag && (
                  <span className="bs-badge bs-badge--preorder" style={{ alignSelf: 'flex-start' }}>{d.tag}</span>
                )}
              </div>
            ))}
          </div>

          <Callout icon={<C.Box />} tone="grey">
            Замовлення пакуємо так, щоб бустери приїхали до вас у тому ж стані, в якому ми їх взяли. Sealed-плівка не пошкоджується.
          </Callout>
        </Section>

        <Section id="time">
          <H2 eyebrow="03">Терміни обробки</H2>
          <div style={{
            display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, margin: '14px 0',
          }}>
            <div style={{
              background: '#fff', border: '1px solid var(--bs-line)',
              borderRadius: 'var(--bs-r)', padding: '20px 22px',
            }}>
              <div style={{ fontSize: 11, fontFamily: '"JetBrains Mono", ui-monospace, monospace', letterSpacing: '.14em', color: 'var(--bs-ink-3)', textTransform: 'uppercase' }}>
                До 14:00
              </div>
              <div style={{ fontSize: 22, fontWeight: 800, color: 'var(--bs-ink)', margin: '8px 0 6px', letterSpacing: '-0.015em' }}>
                Відправляємо в той самий день
              </div>
              <div style={{ fontSize: 13, color: 'var(--bs-ink-2)', lineHeight: 1.55 }}>
                Якщо замовлення підтверджене до 14:00 робочого дня — їде в НП того ж дня.
              </div>
            </div>
            <div style={{
              background: '#fff', border: '1px solid var(--bs-line)',
              borderRadius: 'var(--bs-r)', padding: '20px 22px',
            }}>
              <div style={{ fontSize: 11, fontFamily: '"JetBrains Mono", ui-monospace, monospace', letterSpacing: '.14em', color: 'var(--bs-ink-3)', textTransform: 'uppercase' }}>
                Після 14:00
              </div>
              <div style={{ fontSize: 22, fontWeight: 800, color: 'var(--bs-ink)', margin: '8px 0 6px', letterSpacing: '-0.015em' }}>
                Наступний робочий день
              </div>
              <div style={{ fontSize: 13, color: 'var(--bs-ink-2)', lineHeight: 1.55 }}>
                У вихідні та свята обробку переносимо на найближчий робочий день — ТТН прийде SMS.
              </div>
            </div>
          </div>
          <P muted>Передзамовлення відправляємо одразу після прибуття партії — про дату ми завжди пишемо в Telegram-каналі та в описі товару.</P>
        </Section>

        <Section id="free">
          <H2 eyebrow="04">Безкоштовна доставка</H2>
          <Callout icon={<C.Sparkle />} tone="green" title="Безкоштовна доставка від ₴1500">
            Якщо сума товарів у кошику перевищує ₴1500, ми оплачуємо доставку Новою Поштою у відділення або поштомат. Для безкоштовної адресної доставки сума замовлення має перевищувати ₴2000.
          </Callout>
        </Section>

        <TelegramCard />
      </ContentLayout>
    </PageShell>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. ОБМІН І ПОВЕРНЕННЯ
// 3-step process strip + "що приймаємо / що не приймаємо" pair + contact card.
// ─────────────────────────────────────────────────────────────────────────────
function ReturnsPage() {
  const sections = [
    { id: 'rules',  label: '14 днів на повернення' },
    { id: 'how',    label: 'Як повернути' },
    { id: 'what',   label: 'Що приймаємо / не приймаємо' },
    { id: 'damage', label: 'Якщо товар пошкоджено' },
    { id: 'refund', label: 'Повернення коштів' },
  ];

  return (
    <PageShell>
      <Crumbs trail={['Обмін і повернення']} />

      <Hero
        accent="gold"
        icon={<C.Return />}
        title="Обмін і повернення"
        lede="Sealed-товар — це особливий випадок. Нижче — як саме ми приймаємо повернення й чому розкриті бустери повернути не можна. Без сюрпризів."
      />

      <ContentLayout toc={<TOC items={sections} />}>
        <Section id="rules" padTop={false}>
          <H2 eyebrow="01">14 днів на повернення</H2>
          <P>Згідно з законом «Про захист прав споживачів», ви маєте право повернути товар належної якості протягом <strong>14 днів</strong> з моменту отримання — за умови, що він не був у використанні, зберіг товарний вигляд і заводське пакування.</P>
          <Callout icon={<C.Doc />} tone="blue" title="Що важливо для TCG-продукції">
            Sealed-бустер є придатним до повернення лише доки він залишається запечатаним. Розкритий бустер — це вже «використаний» товар, незалежно від того, які карти випали.
          </Callout>
        </Section>

        <Section id="how">
          <H2 eyebrow="02">Як повернути — три кроки</H2>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10, marginTop: 14 }}>
            {[
              { n: '01', title: 'Напишіть у Telegram', desc: 'Повідомте номер замовлення й коротко — що повертаєте. Узгодимо умови за 1 годину в робочий час.' },
              { n: '02', title: 'Надішліть назад НП', desc: 'Запакуйте товар так, щоб sealed-плівка не пошкодилася в дорозі. Відправка — за рахунок покупця.' },
              { n: '03', title: 'Отримайте кошти', desc: 'Повертаємо повну суму товару тим же способом, яким ви платили. До 3 робочих днів після отримання.' },
            ].map((s) => (
              <div key={s.n} style={{
                background: '#fff', border: '1px solid var(--bs-line)',
                borderRadius: 'var(--bs-r)', padding: '20px 20px 22px',
              }}>
                <div style={{
                  fontFamily: '"JetBrains Mono", ui-monospace, monospace',
                  fontSize: 12, color: 'var(--bs-gold)', fontWeight: 600, letterSpacing: '.08em',
                  marginBottom: 10,
                }}>{s.n}</div>
                <div style={{ fontSize: 15, fontWeight: 700, color: 'var(--bs-ink)', marginBottom: 6 }}>
                  {s.title}
                </div>
                <div style={{ fontSize: 13, color: 'var(--bs-ink-2)', lineHeight: 1.55 }}>
                  {s.desc}
                </div>
              </div>
            ))}
          </div>
        </Section>

        <Section id="what">
          <H2 eyebrow="03">Що ми приймаємо</H2>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginTop: 12 }}>
            <div style={{
              background: '#fff', border: '1px solid #BBF7D0',
              borderRadius: 'var(--bs-r)', padding: 18,
            }}>
              <div style={{
                display: 'inline-flex', alignItems: 'center', gap: 8,
                color: 'var(--bs-green)', fontSize: 13, fontWeight: 700, marginBottom: 12,
              }}>
                <I.Check width="16" height="16" /> Приймаємо
              </div>
              <ul style={{ paddingLeft: 18, margin: 0, fontSize: 14, color: 'var(--bs-ink-2)', lineHeight: 1.7 }}>
                <li>Sealed-бустери в заводській плівці</li>
                <li>Запечатані бокси й набори без втручання</li>
                <li>Товар з пошкодженням з нашої вини</li>
                <li>Невідповідність опису (мова, формат, видання)</li>
              </ul>
            </div>
            <div style={{
              background: '#fff', border: '1px solid #FECACA',
              borderRadius: 'var(--bs-r)', padding: 18,
            }}>
              <div style={{
                display: 'inline-flex', alignItems: 'center', gap: 8,
                color: 'var(--bs-danger)', fontSize: 13, fontWeight: 700, marginBottom: 12,
              }}>
                <I.Close width="14" height="14" /> Не приймаємо
              </div>
              <ul style={{ paddingLeft: 18, margin: 0, fontSize: 14, color: 'var(--bs-ink-2)', lineHeight: 1.7 }}>
                <li>Розкриті бустери (вміст випадковий — це не дефект)</li>
                <li>Товар без оригінального пакування</li>
                <li>Товар з механічними пошкодженнями після отримання</li>
                <li>Промо-набори зі статусом «без повернення» в описі</li>
              </ul>
            </div>
          </div>
          <Callout icon={<C.Sparkle />} tone="grey">
            «Випало не те, що хотілося» — не є підставою для повернення. Шанси закладені виробником, і ми не контролюємо, що саме всередині конкретного паку.
          </Callout>
        </Section>

        <Section id="damage">
          <H2 eyebrow="04">Якщо товар пошкоджено</H2>
          <P>Перед відправкою кожне замовлення пакується вручну. Якщо ж посилка приїхала з пошкодженням — складіть акт у відділенні НП і пришліть нам фото в Telegram <strong>протягом 24 годин</strong>. Розглянемо, замінимо або повернемо кошти.</P>
        </Section>

        <Section id="refund">
          <H2 eyebrow="05">Повернення коштів</H2>
          <P>Кошти повертаємо тим же способом, яким ви платили. Картка — на ту ж картку, IBAN — на ваш рахунок. Зазвичай <strong>1–3 робочих дні</strong> після того, як ми отримали товар і перевірили його стан.</P>
          <P muted>Якщо доставка була оплачена окремо (адресна, поштомат, післяплата) — вартість доставки не повертається. Це тариф перевізника, на нього ми не впливаємо.</P>
        </Section>

        <TelegramCard />
      </ContentLayout>
    </PageShell>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. ПУБЛІЧНА ОФЕРТА
// Legal/long-form. Same TOC scaffold + dense sections + "last updated" badge.
// The copy is a placeholder skeleton — final wording should be reviewed by
// the founder / legal before publish.
// ─────────────────────────────────────────────────────────────────────────────
function OfferPage() {
  const sections = [
    { id: 'general',   label: '1. Загальні положення' },
    { id: 'subject',   label: '2. Предмет договору' },
    { id: 'price',     label: '3. Ціна та оплата' },
    { id: 'delivery',  label: '4. Доставка' },
    { id: 'returns',   label: '5. Обмін і повернення' },
    { id: 'liability', label: '6. Відповідальність сторін' },
    { id: 'forcemajeure', label: '7. Форс-мажор' },
    { id: 'data',      label: '8. Персональні дані' },
    { id: 'details',   label: '9. Реквізити продавця' },
  ];

  const updated = '15 травня 2026';

  return (
    <PageShell>
      <Crumbs trail={['Публічна оферта']} />

      <section className="bs-card" style={{
        padding: 0, overflow: 'hidden', marginBottom: 32,
        display: 'grid', gridTemplateColumns: '4px 1fr',
      }}>
        <div style={{ background: 'var(--bs-ink)' }} />
        <div style={{ padding: '28px 32px' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12 }}>
            <Eyebrow>Документ</Eyebrow>
            <span style={{
              fontSize: 11.5, color: 'var(--bs-ink-3)',
              fontFamily: '"JetBrains Mono", ui-monospace, monospace',
            }}>Оновлено {updated}</span>
          </div>
          <h1 style={{
            fontSize: 36, fontWeight: 800, letterSpacing: '-0.02em',
            margin: '0 0 10px', color: 'var(--bs-ink)', lineHeight: 1.1,
          }}>Публічна оферта</h1>
          <p style={{ fontSize: 15, color: 'var(--bs-ink-2)', lineHeight: 1.55, margin: 0, maxWidth: 680 }}>
            Цей документ є офіційною пропозицією (офертою) ФОП Леусенко Євгеній Андрійович —
            власника інтернет-магазину Booster Shop — укласти договір купівлі-продажу на умовах,
            викладених нижче. Оформлюючи замовлення на сайті, ви приймаєте умови цієї оферти повністю.
          </p>
        </div>
      </section>

      <ContentLayout toc={<TOC items={sections} />}>
        <Section id="general" padTop={false}>
          <H2 eyebrow="1">Загальні положення</H2>
          <P><strong>1.1.</strong> Продавець — ФОП Леусенко Євгеній Андрійович, який здійснює продаж колекційних карткових товарів через інтернет-магазин boostershop.com.ua.</P>
          <P><strong>1.2.</strong> Покупець — фізична або юридична особа, яка оформила замовлення на сайті й тим самим прийняла умови цієї оферти.</P>
          <P><strong>1.3.</strong> Оферта вважається прийнятою з моменту оформлення замовлення та натискання кнопки «Підтвердити замовлення».</P>
        </Section>

        <Section id="subject">
          <H2 eyebrow="2">Предмет договору</H2>
          <P><strong>2.1.</strong> Продавець зобов'язується передати у власність Покупця товар, представлений на сайті, а Покупець — прийняти цей товар і сплатити його вартість.</P>
          <P><strong>2.2.</strong> Найменування, кількість, асортимент, ціна та інші суттєві умови визначаються в кошику Покупця на момент оформлення замовлення.</P>
        </Section>

        <Section id="price">
          <H2 eyebrow="3">Ціна та оплата</H2>
          <P><strong>3.1.</strong> Ціни на товари вказані на сайті в гривнях і включають усі необхідні податки. Продавець залишає за собою право змінювати ціни; ціна для конкретного замовлення фіксується в момент його оформлення.</P>
          <P><strong>3.2.</strong> Способи оплати: банківською карткою онлайн, післяплатою при отриманні в Новій Пошті, або за реквізитами на IBAN. Детальніше — у розділі «Оплата і доставка».</P>
        </Section>

        <Section id="delivery">
          <H2 eyebrow="4">Доставка</H2>
          <P><strong>4.1.</strong> Доставка здійснюється Новою Поштою по території України — у відділення, на адресу або в поштомат, за вибором Покупця.</P>
          <P><strong>4.2.</strong> Терміни доставки залежать від тарифів Нової Пошти. Орієнтовний час — 2–4 робочих дні після передачі замовлення перевізнику.</P>
          <P><strong>4.3.</strong> Ризик випадкової загибелі або пошкодження товару переходить до Покупця з моменту отримання замовлення у перевізника.</P>
        </Section>

        <Section id="returns">
          <H2 eyebrow="5">Обмін і повернення</H2>
          <P><strong>5.1.</strong> Покупець має право повернути товар належної якості протягом 14 днів з моменту отримання — за умови збереження товарного вигляду й заводського пакування.</P>
          <P><strong>5.2.</strong> Розкриті sealed-бустери поверненню не підлягають, оскільки після розкриття товар вважається використаним.</P>
          <P><strong>5.3.</strong> Деталізована процедура — у розділі «Обмін і повернення».</P>
        </Section>

        <Section id="liability">
          <H2 eyebrow="6">Відповідальність сторін</H2>
          <P><strong>6.1.</strong> Сторони несуть відповідальність за невиконання або неналежне виконання зобов'язань відповідно до чинного законодавства України.</P>
          <P><strong>6.2.</strong> Продавець не несе відповідальності за зміст рідкісних карт у бустерах — їх розподіл закладається виробником і не контролюється Продавцем.</P>
        </Section>

        <Section id="forcemajeure">
          <H2 eyebrow="7">Форс-мажор</H2>
          <P>Сторони звільняються від відповідальності за повне або часткове невиконання зобов'язань, якщо це є наслідком обставин непереборної сили: воєнних дій, стихійних лих, актів державної влади, що унеможливлюють виконання договору.</P>
        </Section>

        <Section id="data">
          <H2 eyebrow="8">Персональні дані</H2>
          <P>Оформлюючи замовлення, Покупець дає згоду на обробку своїх персональних даних — імені, телефону, адреси доставки — виключно з метою виконання замовлення. Дані не передаються третім особам, окрім перевізника Нової Пошти, банку-еквайру й Державної податкової служби України.</P>
        </Section>

        <Section id="details">
          <H2 eyebrow="9">Реквізити продавця</H2>
          <div className="bs-card" style={{ padding: 22 }}>
            <div style={{
              display: 'grid', gridTemplateColumns: '160px 1fr', gap: '10px 24px',
              fontSize: 14, color: 'var(--bs-ink-2)',
            }}>
              <span style={{ color: 'var(--bs-ink-3)' }}>Назва</span>
              <span style={{ color: 'var(--bs-ink)' }}>ФОП Леусенко Євгеній Андрійович</span>
              <span style={{ color: 'var(--bs-ink-3)' }}>ЄДРПОУ / РНОКПП</span>
              <span style={{ color: 'var(--bs-ink)', fontFamily: '"JetBrains Mono", ui-monospace, monospace' }}>0000000000</span>
              <span style={{ color: 'var(--bs-ink-3)' }}>IBAN</span>
              <span style={{ color: 'var(--bs-ink)', fontFamily: '"JetBrains Mono", ui-monospace, monospace' }}>UA00 000000 00000000000000000</span>
              <span style={{ color: 'var(--bs-ink-3)' }}>Телефон</span>
              <span style={{ color: 'var(--bs-ink)' }}>+38 063 674 32 52</span>
              <span style={{ color: 'var(--bs-ink-3)' }}>Telegram</span>
              <span style={{ color: 'var(--bs-ink)' }}>@boostershop_tcg</span>
            </div>
          </div>
          <p style={{ fontSize: 12.5, color: 'var(--bs-ink-3)', marginTop: 16, lineHeight: 1.6 }}>
            Реквізити вище — шаблон для верстки. Перед публікацією замініть на актуальні дані ФОП.
          </p>
        </Section>

        <TelegramCard />
      </ContentLayout>
    </PageShell>
  );
}

Object.assign(window, { DeliveryPage, ReturnsPage, OfferPage });
