// Footer mockup — round 2 (post-feedback).
// Round-1 feedback applied:
//   - Каталог: dropped "Набори" and "/special" (per request).
//   - Інформація: Оплата і доставка / Обмін і повернення / Публічна оферта.
//   - Покупцю:   Гарантія оригінальності / Про магазин / Telegram-канал.
//   - Контакти:  телефон / email / Telegram-чат підтримки.

function FooterMock() {
  const cols = [
    {
      title: 'Каталог',
      links: ['Pokémon TCG', 'One Piece Card Game', 'Акції'],
    },
    {
      title: 'Інформація',
      links: ['Оплата і доставка', 'Обмін і повернення', 'Публічна оферта'],
    },
    {
      title: 'Покупцю',
      links: ['Гарантія оригінальності', 'Про магазин', 'Telegram-канал'],
    },
    {
      title: 'Контакти',
      // Mix link+icon so the contact rows look more functional than plain anchors
      raw: [
        { icon: <I.Tg width="13" height="13" />, label: '@boostershop_support' },
        { icon: <span style={{ fontSize: 11 }}>✉</span>, label: 'hello@boostershop.website' },
        { icon: <span style={{ fontSize: 12 }}>☎</span>, label: '+380 XX XXX XX XX' },
      ],
    },
  ];

  return (
    <footer style={{
      background: '#0F1115',
      color: '#9CA3AF',
      fontFamily: '"Manrope", system-ui, sans-serif',
      padding: '40px 32px 24px',
    }}>
      <div style={{ maxWidth: 1240, margin: '0 auto' }}>
        <div style={{
          display: 'grid', gridTemplateColumns: '1.4fr 1fr 1fr 1fr 1.1fr', gap: 32,
          marginBottom: 32,
        }}>
          {/* Brand col */}
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <span style={{
                fontSize: 18, fontWeight: 900, letterSpacing: '-0.04em',
                color: '#fff', lineHeight: 1, textTransform: 'uppercase',
              }}>Booster<br/>Shop</span>
              <span aria-hidden style={{
                display: 'inline-block', width: 16, height: 22,
                background: '#3B82F6',
                clipPath: 'polygon(60% 0,100% 0,40% 50%,90% 50%,15% 100%,55% 55%,0 55%)',
              }} />
            </div>
            <p style={{ marginTop: 14, fontSize: 13, lineHeight: 1.6, maxWidth: 280 }}>
              Оригінальні sealed-бустери Pokémon TCG та One Piece Card Game з Японії та Кореї.
              Без зважування й сортування.
            </p>
            <p style={{ marginTop: 14, fontSize: 12.5, color: '#6B7280', lineHeight: 1.6 }}>
              ФОП Леусенко Євгеній Андрійович
            </p>
          </div>

          {cols.map(col => (
            <div key={col.title}>
              <div style={{
                fontSize: 12, fontWeight: 700, color: '#F3F4F6',
                textTransform: 'uppercase', letterSpacing: '.1em', marginBottom: 14,
              }}>{col.title}</div>
              <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 10 }}>
                {col.links && col.links.map(l => (
                  <li key={l}>
                    <a href="#" style={{ color: '#9CA3AF', fontSize: 13, textDecoration: 'none' }}>{l}</a>
                  </li>
                ))}
                {col.raw && col.raw.map((r, i) => (
                  <li key={i} style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <span style={{
                      width: 22, height: 22, borderRadius: 6,
                      background: 'rgba(255,255,255,0.06)', color: '#fff',
                      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                      flex: '0 0 auto',
                    }}>{r.icon}</span>
                    <a href="#" style={{ color: '#E5E7EB', fontSize: 13, textDecoration: 'none' }}>{r.label}</a>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        <div style={{
          borderTop: '1px solid #1F2937',
          paddingTop: 18,
          display: 'flex', justifyContent: 'space-between', alignItems: 'center',
          fontSize: 12, color: '#6B7280',
        }}>
          <span>© 2026 Booster Shop. Усі права захищено.</span>
          <span>Графік підтримки: 10:00–20:00 (UA)</span>
        </div>
      </div>
    </footer>
  );
}

Object.assign(window, { FooterMock });
