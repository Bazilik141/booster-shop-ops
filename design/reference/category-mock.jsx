// Category page mockup — Pokémon TCG section.
// Includes filter exploration: variant 'sidebar' (current behavior, refined)
// or 'topbar' (filters compress into a horizontal row, sidebar gone).

function SubCategoryChips({ active = null }) {
  // Chips reflect path-segment, not category landing default. When user arrives
  // from home page, no chip is active — all products show.
  const items = ['Бустери', 'Бустер-бокси', 'Набори'];
  return (
    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
      {items.map(label => {
        const isActive = label === active;
        return (
          <a key={label} href="#" style={{
            display: 'inline-flex', alignItems: 'center', gap: 8,
            padding: '8px 14px',
            background: isActive ? 'var(--bs-blue-soft)' : '#fff',
            color: isActive ? 'var(--bs-blue)' : 'var(--bs-ink-2)',
            border: `1px solid ${isActive ? 'var(--bs-blue)' : 'var(--bs-line)'}`,
            borderRadius: 'var(--bs-r-pill)',
            fontSize: 13, fontWeight: 600,
            transition: 'background .15s, border-color .15s, color .15s',
          }}>
            {label}
          </a>
        );
      })}
    </div>
  );
}

function ActiveFilterChips({ filters, onRemove }) {
  if (!filters.length) return null;
  return (
    <div style={{
      display: 'flex', gap: 8, flexWrap: 'wrap',
      paddingTop: 4, paddingBottom: 4,
    }}>
      <span style={{ fontSize: 12.5, color: 'var(--bs-ink-3)', alignSelf: 'center', marginRight: 4 }}>
        Активні:
      </span>
      {filters.map((f, i) => (
        <span key={i} style={{
          display: 'inline-flex', alignItems: 'center', gap: 6,
          background: 'var(--bs-blue-soft)', color: 'var(--bs-blue)',
          padding: '4px 10px', borderRadius: 'var(--bs-r-pill)',
          fontSize: 12, fontWeight: 600,
        }}>
          {f}
          <button onClick={() => onRemove(i)} style={{
            background: 'transparent', border: 0, padding: 0,
            color: 'var(--bs-blue)', cursor: 'pointer',
            display: 'inline-flex', alignItems: 'center',
          }}>
            <I.Close width="10" height="10" />
          </button>
        </span>
      ))}
      <button style={{
        fontSize: 12, color: 'var(--bs-ink-3)', background: 'transparent',
        border: 0, cursor: 'pointer', fontWeight: 500,
        textDecoration: 'underline', textUnderlineOffset: 3,
      }}>
        Скинути все
      </button>
    </div>
  );
}

// Sidebar filter — refined version. Quiet header, hairline groups, checkboxes.
function FilterSidebar() {
  return (
    <aside className="bs-card" style={{ padding: 0, position: 'sticky', top: 16, alignSelf: 'flex-start' }}>
      <header style={{
        padding: '14px 16px',
        borderBottom: '1px solid var(--bs-line)',
        display: 'flex', alignItems: 'center', gap: 8,
      }}>
        <I.Filter width="14" height="14" style={{ color: 'var(--bs-ink-3)' }} />
        <span style={{ fontSize: 13.5, fontWeight: 700, color: 'var(--bs-ink)' }}>Фільтр</span>
      </header>

      <FilterGroup label="Тип товару" defaultOpen>
        {['Бустер', 'Бустер-бокс', 'Набір'].map(o => (
          <FilterCheckbox key={o} label={o} />
        ))}
      </FilterGroup>
      <FilterGroup label="Країна">
        {['Японія', 'Корея'].map(o => <FilterCheckbox key={o} label={o} />)}
      </FilterGroup>
      <FilterGroup label="Стан">
        {['Sealed', 'Unweighed', 'Low Pull'].map(o => <FilterCheckbox key={o} label={o} />)}
      </FilterGroup>
      <FilterGroup label="Ціна">
        <PriceRange />
      </FilterGroup>

      <div style={{ padding: 14, borderTop: '1px solid var(--bs-line)' }}>
        <button className="bs-btn bs-btn-secondary" style={{ width: '100%', justifyContent: 'center', borderColor: 'var(--bs-blue)', color: 'var(--bs-blue)' }}>
          Застосувати
        </button>
      </div>
    </aside>
  );
}

function FilterGroup({ label, children, defaultOpen = true }) {
  const [open, setOpen] = React.useState(defaultOpen);
  return (
    <div style={{ borderBottom: '1px solid var(--bs-line)' }}>
      <button onClick={() => setOpen(!open)} style={{
        width: '100%', padding: '12px 16px', background: 'transparent', border: 0,
        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
        color: 'var(--bs-ink)', fontSize: 13.5, fontWeight: 600, cursor: 'pointer',
      }}>
        {label}
        <I.Chevron width="12" height="12" style={{
          color: 'var(--bs-ink-3)',
          transform: `rotate(${open ? 180 : 0}deg)`,
          transition: 'transform .2s',
        }} />
      </button>
      {open && (
        <div style={{ padding: '0 16px 14px', display: 'flex', flexDirection: 'column', gap: 8 }}>
          {children}
        </div>
      )}
    </div>
  );
}

function FilterCheckbox({ label }) {
  const [checked, setChecked] = React.useState(false);
  return (
    <label style={{
      display: 'flex', alignItems: 'center', gap: 10,
      cursor: 'pointer', fontSize: 13.5, color: 'var(--bs-ink-2)',
    }}>
      <span style={{
        width: 16, height: 16, borderRadius: 4,
        border: `1px solid ${checked ? 'var(--bs-blue)' : 'var(--bs-line)'}`,
        background: checked ? 'var(--bs-blue)' : '#fff',
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
        color: '#fff',
        transition: 'background .15s, border-color .15s',
      }} onClick={() => setChecked(!checked)}>
        {checked && <I.Check width="10" height="10" />}
      </span>
      {label}
    </label>
  );
}

function PriceRange() {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
      <div style={{ display: 'flex', gap: 6 }}>
        <input placeholder="від ₴80" style={inputStyle} />
        <input placeholder="до ₴800" style={inputStyle} />
      </div>
      <div style={{
        height: 4, background: 'var(--bs-line-2)', borderRadius: 999, position: 'relative',
      }}>
        <div style={{
          position: 'absolute', left: '15%', right: '40%', top: 0, bottom: 0,
          background: 'var(--bs-blue)', borderRadius: 999,
        }} />
      </div>
    </div>
  );
}
const inputStyle = {
  flex: 1, padding: '6px 10px', borderRadius: 6,
  border: '1px solid var(--bs-line)', background: '#fff', font: 'inherit',
  fontSize: 12.5, color: 'var(--bs-ink)', outline: 'none', width: '100%',
};

// Topbar variant — horizontal filter row, no sidebar
function FilterTopbar() {
  return (
    <div className="bs-card" style={{
      padding: '10px 12px',
      display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap',
    }}>
      {['Тип товару', 'Країна', 'Стан', 'Ціна'].map(label => (
        <button key={label} style={{
          display: 'inline-flex', alignItems: 'center', gap: 6,
          padding: '8px 12px',
          background: '#fff', border: '1px solid var(--bs-line)',
          borderRadius: 'var(--bs-r-sm)',
          fontSize: 13, fontWeight: 600, color: 'var(--bs-ink-2)',
        }}>
          {label} <I.Chevron width="10" height="10" />
        </button>
      ))}
      <span style={{ flex: 1 }} />
      <button style={{
        padding: '8px 12px', background: 'transparent', border: 0,
        color: 'var(--bs-blue)', fontSize: 13, fontWeight: 600, cursor: 'pointer',
      }}>
        Усі фільтри
      </button>
    </div>
  );
}

// Page composition --------------------------------------------------------
function CategoryMock({ filters: filterVariant = 'sidebar' }) {
  const [activeFilters, setActiveFilters] = React.useState(['Японія']);
  const removeFilter = (i) => setActiveFilters(activeFilters.filter((_, idx) => idx !== i));

  const products = PRODUCTS.filter(p => p.brand === 'pokemon' || p.brand === 'onepiece');

  return (
    <div className="bs-mock">
      <HeaderV1 />

      <main style={{ maxWidth: 1240, margin: '0 auto', padding: '20px 32px 56px' }}>
        {/* Breadcrumbs */}
        <nav style={{
          display: 'flex', alignItems: 'center', gap: 8,
          fontSize: 12.5, color: 'var(--bs-ink-3)', marginBottom: 12,
        }}>
          <a href="#" style={{ color: 'var(--bs-ink-3)', display: 'inline-flex' }}>
            <I.Home width="12" height="12" />
          </a>
          <span>›</span>
          <a href="#" style={{ color: 'var(--bs-ink-3)' }}>Каталог</a>
          <span>›</span>
          <span style={{ color: 'var(--bs-ink)' }}>Pokémon TCG</span>
        </nav>

        {/* Integrated category header card.
            - 4px brand-colour strip on top gives this category a tiny visual
              identity (mirrors the D-tile pattern on the home page).
            - Hero row: title + count + tagline · sort dropdown right.
            - Toolbar row: sub-cat chips · filter trigger · active filter chips
              · reset — all on a single line, divider above.
            This replaces the four-row stack between header and grid. */}
        <div className="bs-card" style={{ overflow: 'hidden', marginBottom: 18 }}>
          <div style={{ height: 4, background: 'var(--bs-pokemon)' }} />

          <div style={{
            padding: '18px 22px',
            display: 'flex', alignItems: 'center', gap: 16,
          }}>
            <div style={{ minWidth: 0 }}>
              <div style={{ display: 'flex', alignItems: 'baseline', gap: 10 }}>
                <h1 style={{ fontSize: 26 }}>Pokémon TCG</h1>
                <span style={{ fontSize: 13, color: 'var(--bs-ink-3)', fontWeight: 500 }}>24 товари</span>
              </div>
              <div style={{ fontSize: 13, color: 'var(--bs-ink-3)', marginTop: 4 }}>
                Японські та корейські sealed-бустери, бустер-бокси, набори
              </div>
            </div>
            <span style={{ flex: 1 }} />
            <select style={{
              padding: '8px 30px 8px 12px', background: '#fff',
              border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r-sm)',
              fontSize: 13, color: 'var(--bs-ink-2)', appearance: 'none', font: 'inherit',
              backgroundImage: "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 10 10'><path d='M2 4l3 3 3-3' stroke='%236B7280' stroke-width='1.5' fill='none'/></svg>\")",
              backgroundRepeat: 'no-repeat', backgroundPosition: 'right 10px center',
              flex: '0 0 auto',
            }}>
              <option>За замовчуванням</option>
              <option>Спочатку дешевші</option>
              <option>Спочатку дорожчі</option>
              <option>Новинки</option>
            </select>
          </div>

          {/* Toolbar row — single line, divider above. Filter trigger lives at the
              very right when topbar variant is chosen. */}
          <div style={{
            padding: '12px 22px',
            borderTop: '1px solid var(--bs-line-2)',
            background: 'var(--bs-bg)',
            display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap',
          }}>
            <SubCategoryChips active={null} />
            {filterVariant === 'topbar' && (
              <button style={{
                display: 'inline-flex', alignItems: 'center', gap: 6,
                padding: '8px 12px',
                background: '#fff', border: '1px solid var(--bs-line)',
                borderRadius: 'var(--bs-r-sm)',
                fontSize: 13, fontWeight: 600, color: 'var(--bs-ink-2)',
                cursor: 'pointer',
              }}>
                <I.Filter width="13" height="13" /> Усі фільтри
              </button>
            )}
            <span style={{ flex: 1 }} />
            <ActiveFilterChips filters={activeFilters} onRemove={removeFilter} />
          </div>
        </div>

        {/* Body grid */}
        <div style={{
          display: 'grid',
          gridTemplateColumns: filterVariant === 'sidebar' ? '240px 1fr' : '1fr',
          gap: 24,
        }}>
          {filterVariant === 'sidebar' && <FilterSidebar />}

          <section style={{
            display: 'grid',
            gridTemplateColumns: filterVariant === 'sidebar'
              ? 'repeat(3, 1fr)' : 'repeat(4, 1fr)',
            gap: 14,
          }}>
            {products.map(p => (
              <ProductCard key={p.id} product={p} />
            ))}
          </section>
        </div>
      </main>
    </div>
  );
}

Object.assign(window, { CategoryMock, SubCategoryChips, FilterSidebar, FilterTopbar });
