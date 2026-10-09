// Header variants — three concepts to replace the current gold-foam header.
// All comply with UX-034 (clean retail, no decorative band) and UX-003 (calm structural nav).

// Tiny reusable bits ---------------------------------------------------------
function Logo({ size = 28 }) {
  // Wordmark in brand gold + small electric-bolt mark — not a real recreation
  // of the live logo, just a stand-in so layouts read correctly. Real SVG
  // logo gets dropped in by you at implementation time.
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
      <span style={{
        fontFamily: '"Manrope", system-ui, sans-serif',
        fontSize: size, fontWeight: 900, letterSpacing: '-0.04em',
        color: 'var(--bs-pokemon)',
        lineHeight: 1, textTransform: 'uppercase',
      }}>
        Booster<br/>Shop
      </span>
      <span aria-hidden style={{
        display: 'inline-block', width: size*0.7, height: size*1.1,
        background: 'var(--bs-blue)',
        clipPath: 'polygon(60% 0,100% 0,40% 50%,90% 50%,15% 100%,55% 55%,0 55%)',
      }} />
    </div>
  );
}

function SearchBar({ compact }) {
  return (
    <div style={{
      flex: 1, display: 'flex', alignItems: 'center',
      background: 'var(--bs-bg)',
      border: '1px solid var(--bs-line)',
      borderRadius: 'var(--bs-r-sm)',
      padding: compact ? '6px 10px 6px 12px' : '10px 12px 10px 14px',
      gap: 10,
    }}>
      <I.Search width="16" height="16" style={{ color: 'var(--bs-ink-3)', flex: '0 0 auto' }}/>
      <input
        placeholder="Пошук бустерів, сетів, виробників…"
        style={{
          flex: 1, border: 0, background: 'transparent', outline: 'none',
          fontSize: compact ? 13.5 : 14, color: 'var(--bs-ink)',
          fontFamily: 'inherit', width: '100%',
        }}
      />
    </div>
  );
}

function CartPill({ count = 2, total = '700.00', compact }) {
  return (
    <button className="bs-btn bs-btn-primary" style={{
      padding: compact ? '8px 12px' : '10px 14px',
      borderRadius: 'var(--bs-r-sm)',
      gap: 8,
    }}>
      <I.Cart width="16" height="16" />
      <span style={{ fontSize: 13.5, fontWeight: 700 }}>
        {count}
      </span>
      <span style={{ fontSize: 12, fontWeight: 500, opacity: 0.88 }}>
        · ₴{total}
      </span>
    </button>
  );
}

// V1 — "Minimal white". Most restrained. Single row. Logo · Search · Cart.
function HeaderV1() {
  return (
    <header style={{
      background: '#fff',
      borderBottom: '1px solid var(--bs-line)',
      padding: '14px 32px',
    }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 24, maxWidth: 1240, margin: '0 auto' }}>
        <Logo size={20} />
        <SearchBar />
        <div style={{ display: 'flex', alignItems: 'center', gap: 6, color: 'var(--bs-ink-3)', fontSize: 13 }}>
          <button className="bs-btn bs-btn-ghost" style={{ padding: '8px 10px', fontSize: 13 }}>
            <I.User width="16" height="16" /> Кабінет
          </button>
          <button className="bs-btn bs-btn-ghost" style={{ padding: '8px 10px', fontSize: 13 }}>
            <I.Tg width="16" height="16" /> Telegram
          </button>
        </div>
        <CartPill compact />
      </div>
    </header>
  );
}

// V2 — "Utility bar + nav". Thin grey utility strip on top for secondary
// links, then a white main row, then a quiet category nav row.
function HeaderV2() {
  return (
    <header style={{ background: '#fff', borderBottom: '1px solid var(--bs-line)' }}>
      {/* utility bar */}
      <div style={{
        background: 'var(--bs-bg)',
        borderBottom: '1px solid var(--bs-line)',
        padding: '6px 32px',
        fontSize: 12, color: 'var(--bs-ink-3)',
      }}>
        <div style={{
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          maxWidth: 1240, margin: '0 auto',
        }}>
          <span>Оригінальні sealed-бустери з Японії та Кореї · Доставка ~3 дні</span>
          <div style={{ display: 'flex', gap: 18, alignItems: 'center' }}>
            <a href="#" style={{ color: 'var(--bs-ink-3)', display: 'flex', alignItems: 'center', gap: 4 }}>
              <I.User width="12" height="12" /> Кабінет
            </a>
            <a href="#" style={{ color: 'var(--bs-ink-3)', display: 'flex', alignItems: 'center', gap: 4 }}>
              <I.Tg width="12" height="12" /> Telegram
            </a>
            <a href="#" style={{ color: 'var(--bs-ink-3)' }}>Оформлення замовлення</a>
          </div>
        </div>
      </div>
      {/* main bar */}
      <div style={{ padding: '16px 32px' }}>
        <div style={{
          display: 'flex', alignItems: 'center', gap: 28,
          maxWidth: 1240, margin: '0 auto',
        }}>
          <Logo size={22} />
          <SearchBar />
          <CartPill />
        </div>
      </div>
      {/* category nav */}
      <div style={{ borderTop: '1px solid var(--bs-line-2)', padding: '10px 32px' }}>
        <div style={{
          display: 'flex', alignItems: 'center', gap: 22,
          maxWidth: 1240, margin: '0 auto',
          fontSize: 13.5, fontWeight: 600, color: 'var(--bs-ink-2)',
        }}>
          <a href="#" style={{ color: 'var(--bs-ink)', position: 'relative' }}>
            Pokémon
            <span style={{ position: 'absolute', left: 0, right: 0, bottom: -11, height: 2, background: 'var(--bs-pokemon)' }} />
          </a>
          <a href="#">One Piece</a>
          <a href="#">Набори</a>
          <a href="#">
            <span style={{ color: 'var(--bs-danger)' }}>Акції</span>
          </a>
          <a href="#" style={{ marginLeft: 'auto', color: 'var(--bs-ink-3)', fontWeight: 500 }}>Доставка</a>
          <a href="#" style={{ color: 'var(--bs-ink-3)', fontWeight: 500 }}>Контакти</a>
        </div>
      </div>
    </header>
  );
}

// V3 — "Quiet brand band". Keeps a subtle band feel (closer to current site)
// but without the loud gold-foam — uses a very pale neutral and small accent.
function HeaderV3() {
  return (
    <header style={{
      background: '#fff',
      borderBottom: '1px solid var(--bs-line)',
    }}>
      <div style={{
        background: 'var(--bs-bg)',
        borderBottom: '1px solid var(--bs-line)',
      }}>
        <div style={{
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          maxWidth: 1240, margin: '0 auto',
          padding: '20px 32px',
          gap: 24,
        }}>
          <Logo size={26} />
          <SearchBar />
          <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
            <button className="bs-btn bs-btn-ghost" style={{ padding: '8px 10px', fontSize: 13 }}>
              <I.User width="16" height="16" />
            </button>
            <CartPill />
          </div>
        </div>
      </div>
      {/* nav strip on white */}
      <div style={{ padding: '10px 32px' }}>
        <div style={{
          display: 'flex', alignItems: 'center', gap: 24,
          maxWidth: 1240, margin: '0 auto',
          fontSize: 13.5, fontWeight: 600, color: 'var(--bs-ink-2)',
        }}>
          <a href="#" style={{ color: 'var(--bs-ink)' }}>Pokémon</a>
          <a href="#">One Piece</a>
          <a href="#">Набори</a>
          <a href="#" style={{ color: 'var(--bs-danger)' }}>Акції</a>
          <a href="#" style={{ marginLeft: 'auto', color: 'var(--bs-ink-3)', fontWeight: 500 }}>Доставка</a>
          <a href="#" style={{ color: 'var(--bs-ink-3)', fontWeight: 500 }}>FAQ</a>
        </div>
      </div>
    </header>
  );
}

Object.assign(window, { HeaderV1, HeaderV2, HeaderV3, Logo, SearchBar, CartPill });
