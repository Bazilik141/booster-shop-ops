// Main composition for "FAQ редизайн v2.html" — a design canvas exploring:
//   1) Why current FAQs render inconsistently across product pages (and the
//      shape of the fix — a small normalizer that runs in the browser).
//   2) Four fresh visual directions for the accordion, all driven by the
//      same normalized data so the chosen one becomes the single source of
//      truth across every product description.

const PageMock = ({ children, width = 720, mobile = false }) => (
  <div style={{
    width, background: '#fff',
    padding: mobile ? '16px 16px 20px' : '24px 28px 28px',
    color: 'var(--bs-ink)',
    fontFamily: 'Manrope, system-ui, sans-serif',
  }}>
    {/* a tiny breadcrumb-ish header so it visually reads as "product page" */}
    <div style={{
      display: 'flex', alignItems: 'center', gap: 8,
      fontSize: mobile ? 11 : 12, color: 'var(--bs-ink-3)',
      marginBottom: mobile ? 10 : 14,
    }}>
      <span>Pokémon TCG</span>
      <span style={{ opacity: .35 }}>/</span>
      <span>Бустер White Flare</span>
    </div>

    {/* a slice of product description above the FAQ so the spacing-into-FAQ
        is actually visible (this is where the "ugly top padding" problem
        manifested on the live site) */}
    <div style={{
      fontSize: mobile ? 13.5 : 14, lineHeight: 1.6, color: 'var(--bs-ink-2)',
      marginBottom: mobile ? 18 : 24,
    }}>
      Кожен <strong>sealed-бустер містить 7 карт</strong>. White Flare особливо цінують
      за кілька ексклюзивних позицій, яких немає в Black Bolt. Бустери закуплені з
      повноцінних коробок і продаються <strong>без зважування</strong>.
    </div>

    {children}
  </div>
);

function Heading({ kicker, title, body, accent }) {
  return (
    <div style={{ marginBottom: 18, maxWidth: 720 }}>
      <div style={{
        fontFamily: "'JetBrains Mono', ui-monospace, monospace",
        fontSize: 11, fontWeight: 500, letterSpacing: '.16em',
        color: accent || 'var(--bs-gold)', textTransform: 'uppercase',
        marginBottom: 6,
      }}>{kicker}</div>
      <div style={{
        fontSize: 24, fontWeight: 700, letterSpacing: '-0.015em',
        color: 'var(--bs-ink)', marginBottom: 8,
      }}>{title}</div>
      {body && (
        <div style={{
          fontSize: 14, lineHeight: 1.6, color: 'var(--bs-ink-2)',
          maxWidth: 640,
        }}>{body}</div>
      )}
    </div>
  );
}

// Shared kicker used inside each variant artboard
function VariantHeader({ tag, name, note }) {
  return (
    <div style={{ marginBottom: 6 }}>
      <div style={{
        display: 'flex', alignItems: 'baseline', gap: 10, marginBottom: 4,
      }}>
        <span style={{
          fontFamily: "'JetBrains Mono', ui-monospace, monospace",
          fontSize: 10, fontWeight: 600, letterSpacing: '.14em',
          color: 'var(--bs-gold)', textTransform: 'uppercase',
        }}>{tag}</span>
        <span style={{
          fontSize: 16, fontWeight: 700, color: 'var(--bs-ink)',
          letterSpacing: '-0.005em',
        }}>{name}</span>
      </div>
      <div style={{
        fontSize: 12.5, color: 'var(--bs-ink-3)', lineHeight: 1.5,
        maxWidth: 640,
      }}>{note}</div>
    </div>
  );
}

function App() {
  const items = window.FAQ_V2_ITEMS;

  return (
    <window.DesignCanvas>

      {/* ───────── SECTION 0 — діагноз ─────────────────────────────────── */}
      <window.DCSection
        id="diagnosis"
        title="Діагноз і рішення"
        subtitle="Чому одна сторінка виглядає інакше за іншу — і як це зробити неможливим."
      >
        <window.DCArtboard id="problem" label="Проблема — три формати з одного й того ж промпта" width={1280} height={760}>
          <div style={{
            padding: '28px 32px',
            background: '#fff', color: 'var(--bs-ink)',
            fontFamily: 'Manrope, system-ui, sans-serif',
            display: 'flex', flexDirection: 'column', gap: 16,
          }}>
            <Heading
              kicker="Корінь проблеми"
              title="ШІ пише FAQ як хоче. CSS чекає на одну структуру."
              body={
                <span>
                  Затверджений шаблон передбачає <code style={{ background: 'var(--bs-bg)', padding: '1px 5px', borderRadius: 4, fontSize: 12.5 }}>{`<div class="bs-faq-accordion">`}</code> з
                  елементами <code style={{ background: 'var(--bs-bg)', padding: '1px 5px', borderRadius: 4, fontSize: 12.5 }}>{`.bs-faq-item`}</code>.
                  Але кожна нова сторінка приходить з власним смаком: то <code style={{ background: 'var(--bs-bg)', padding: '1px 5px', borderRadius: 4, fontSize: 12.5 }}>{`<h4>`}</code> + <code style={{ background: 'var(--bs-bg)', padding: '1px 5px', borderRadius: 4, fontSize: 12.5 }}>{`<p>`}</code>,
                  то <code style={{ background: 'var(--bs-bg)', padding: '1px 5px', borderRadius: 4, fontSize: 12.5 }}>{`<p><strong>?</strong></p>`}</code>,
                  то заголовки без відповідей. Жоден з трьох не збігається з тим, що очікує стиль.
                </span>
              }
            />
            <div style={{
              padding: 14,
              background: 'var(--bs-warning-bg)',
              border: '1px solid var(--bs-warning-line)',
              borderRadius: 8,
              color: 'var(--bs-warning-fg)',
              fontSize: 13, lineHeight: 1.55,
            }}>
              <strong>Рішення:</strong> один JS-нормалізатор на фронті <em>(parseFaq)</em> сканує
              опис, знаходить заголовок «FAQ» / «Часті питання», й автоматично перетворює
              будь-який формат у єдиний акордеон. Що б ШІ не написав — користувач бачить
              однаковий результат. Промпти теж варто закрутити, але код перестає від них залежати.
            </div>

            <window.NormalizerDemo />
          </div>
        </window.DCArtboard>
      </window.DCSection>

      {/* ───────── SECTION 1 — 4 свіжі варіанти візуалу ────────────────── */}
      <window.DCSection
        id="variants"
        title="4 нові варіанти акордеону"
        subtitle="Усі побудовані на одних і тих самих даних з parseFaq(). Обрати треба ОДИН — він стане єдиним джерелом істини для всіх товарів."
      >
        <window.DCArtboard id="variant-a" label="A · Quiet hairlines (виправлений оригінал)" width={780} height={620}>
          <PageMock>
            <VariantHeader
              tag="ВАРІАНТ A"
              name="Quiet hairlines"
              note="Очищений оригінал: заголовок «Часті питання» — звичайний текст на білому, без beige-блока з padding 16px. Тонкі лінії, gold-chevron на відкритті. Найменше шуму, ідеально для скрол-важких сторінок з описами."
            />
            <div style={{ marginTop: 14 }}>
              <window.FaqV2_A items={items} />
            </div>
          </PageMock>
        </window.DCArtboard>

        <window.DCArtboard id="variant-b" label="B · Numbered editorial" width={780} height={620}>
          <PageMock>
            <VariantHeader
              tag="ВАРІАНТ B"
              name="Numbered editorial"
              note="Моноширинні номери 01, 02… ліворуч від питання — як індекс у редакційному матеріалі. Читається кураторсько: «це не випадковий список, а підібраний набір». Працює тим самим тригером, плюс «+/−» замість шеврона."
            />
            <div style={{ marginTop: 14 }}>
              <window.FaqV2_B items={items} />
            </div>
          </PageMock>
        </window.DCArtboard>

        <window.DCArtboard id="variant-c" label="C · Q · A prefixes" width={780} height={620}>
          <PageMock>
            <VariantHeader
              tag="ВАРІАНТ C"
              name="Q · A prefixes (knowledge base)"
              note="Чорна «Q»-плашка біля питання, золота «A»-плашка біля відповіді. Активний рядок підсвічується сірим фоном. Найбільш «шопівський» — фактологічний, як FAQ у premium reference doc. Має fallback для випадків, коли ШІ не дописав відповідь."
            />
            <div style={{ marginTop: 14 }}>
              <window.FaqV2_C items={items} />
            </div>
          </PageMock>
        </window.DCArtboard>

        <window.DCArtboard id="variant-d" label="D · Stacked soft cards" width={780} height={620}>
          <PageMock>
            <VariantHeader
              tag="ВАРІАНТ D"
              name="Stacked soft cards"
              note="Кожне Q/A — окрема м'яка карточка з тонкою лінією. При відкритті: легка тінь, золота вертикальна риска ліворуч, «+» обертається на «×» у золотій плашці. Об'єктно й сучасно, без жорсткого блока."
            />
            <div style={{ marginTop: 14 }}>
              <window.FaqV2_D items={items} />
            </div>
          </PageMock>
        </window.DCArtboard>
      </window.DCSection>

      {/* ───────── SECTION 2 — мобільна A (затверджений варіант) ────────── */}
      <window.DCSection
        id="mobile"
        title="Mobile · обраний варіант A"
        subtitle="Той самий компонент при ширині продуктової сторінки на телефоні (~360–390px). Зменшені відступи, чітабельний tap-target ≥44px, заголовок не злипається з описом вище."
      >
        <window.DCArtboard id="mobile-collapsed" label="A · Mobile · 390px · усе закрите" width={420} height={820}>
          <div style={{
            background: '#f3f1ec', padding: 15,
            display: 'flex', justifyContent: 'center',
          }}>
            <div style={{
              width: 390, background: '#fff',
              borderRadius: 18,
              boxShadow: '0 1px 0 rgba(0,0,0,.03)',
              overflow: 'hidden',
            }}>
              <PageMock width={390} mobile>
                <window.FaqV2_A items={items} />
              </PageMock>
            </div>
          </div>
        </window.DCArtboard>

        <window.DCArtboard id="mobile-open" label="A · Mobile · 390px · з відкритими питаннями" width={420} height={820}>
          <div style={{
            background: '#f3f1ec', padding: 15,
            display: 'flex', justifyContent: 'center',
          }}>
            <div style={{
              width: 390, background: '#fff',
              borderRadius: 18,
              boxShadow: '0 1px 0 rgba(0,0,0,.03)',
              overflow: 'hidden',
            }}>
              <PageMock width={390} mobile>
                {/* second instance opens 0 and 2 by default via a small wrapper */}
                <window.FaqV2_A_MobileOpenDemo items={items} />
              </PageMock>
            </div>
          </div>
        </window.DCArtboard>
      </window.DCSection>

    </window.DesignCanvas>
  );
}

ReactDOM.createRoot(document.getElementById('app')).render(<App />);
