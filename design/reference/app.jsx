// Compose all variants into a single design canvas.

const Crumbs = () => (
  <div className="crumbs">
    <svg viewBox="0 0 16 16" fill="currentColor"><path d="M8 2l6 5v7h-4v-4H6v4H2V7l6-5z"/></svg>
    <a href="#">Pokémon</a>
    <span style={{ color: '#c8ccd1' }}>›</span>
    <span>Бустери</span>
  </div>
);

const CategoryHeader = () => (
  <>
    <Crumbs />
    <h1 className="h-page">Бустери Pokémon TCG</h1>
    {window.CATEGORY_HEADER.paragraphs.map((p, i) => (
      <p key={i} className="lede">{p}</p>
    ))}
  </>
);

// Wraps a variant inside the page-context chrome (breadcrumbs + h1 + intro
// copy), so each artboard reads as a real category page section, not a
// floating component.
function PageMock({ variantTag, children }) {
  return (
    <div className="page">
      <CategoryHeader />
      <div className="variant-tag">{variantTag}</div>
      {children}
    </div>
  );
}

function App() {
  const items = window.FAQ_ITEMS;
  return (
    <DesignCanvas
      title="Booster Shop — FAQ редизайн"
      subtitle="3 варіанти акордеона + порядок секцій на сторінці «Акції»"
    >
      <DCSection
        id="faq"
        title="FAQ — три напрямки"
        subtitle="Один і той самий контент із сторінки категорії, три візуальні рішення. Кожне підтримує множинне відкриття, плавну анімацію та мобільну версію."
      >
        <DCArtboard
          id="variant-a"
          label="A · Hairline"
          width={780}
          height={1200}
        >
          <PageMock variantTag="Варіант A — Тиха межа">
            <FaqVariantA items={items} />
          </PageMock>
        </DCArtboard>

        <DCArtboard
          id="variant-b"
          label="B · Soft cards"
          width={780}
          height={1200}
        >
          <PageMock variantTag="Варіант B — М'які картки">
            <FaqVariantB items={items} />
          </PageMock>
        </DCArtboard>

        <DCArtboard
          id="variant-c"
          label="C · Boxed hairlines"
          width={780}
          height={1200}
        >
          <PageMock variantTag="Варіант C — Обведена картка з hairline-рядками">
            <FaqVariantC items={items} />
          </PageMock>
        </DCArtboard>
      </DCSection>

      <DCSection
        id="promo"
        title="Сторінка «Акції» — порядок секцій"
        subtitle="Окрема задача: на сторінках акцій FAQ має йти ПІСЛЯ блоку з товарами, а не перед ним."
      >
        <DCArtboard
          id="promo-order"
          label="Порядок: до / після"
          width={920}
          height={780}
        >
          <PromoReorder />
        </DCArtboard>
      </DCSection>
    </DesignCanvas>
  );
}

ReactDOM.createRoot(document.body.appendChild(document.createElement('div')))
  .render(<App />);
