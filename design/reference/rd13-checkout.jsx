// RD-13 · Checkout reskin (HIGH-RISK) — markup/CSS mockup only. v2, per review:
// - "Контакт" renamed → "Отримувач" (+ По батькові, necessary field)
// - Доставка: saved-address select OR "+ Інша адреса" manual entry (Область→
//   Місто→Тип доставки→Відділення cascade); guest adds Captcha + newsletter
// - Замовлення: shipping price now a real number (API-driven in prod, not just
//   a label), free-shipping progress bar (₴2000 threshold), item list caps at
//   ~3 rows then scrolls internally so the card doesn't grow unbounded,
//   "вміст бустерів випадковий…" disclaimer removed, agreement checkbox is
//   OFF by default (matches live behaviour — no pre-checked legal consent)
// - Guest checkout adds "Зберегти дані для наступного разу" (auto-account)
// - Mobile: Отримувач + Доставка are collapsible like Замовлення — collapsed
//   showing "Ім'я Прізвище · телефон" / "Нова пошта · відділення №" for a
//   returning customer with saved data, expanded by default otherwise.
//   "Маєш акаунт?" now sits ABOVE the order-summary block.
// Payment/fiscalization logic still NOT represented — visual shell only.

const FREE_SHIP_THRESHOLD = 2000;

const coInputBase = {
  width: '100%', boxSizing: 'border-box',
  padding: '10px 12px', borderRadius: 'var(--bs-r-sm)',
  border: '1.5px solid var(--bs-line)', background: '#fff',
  fontSize: 14, color: 'var(--bs-ink)', font: 'inherit', outline: 'none',
};
const coInputDisabled = { ...coInputBase, color: 'var(--bs-ink-4)', background: 'var(--bs-bg)', cursor: 'not-allowed' };
const coSelectBase = {
  ...coInputBase,
  appearance: 'none',
  padding: '10px 30px 10px 12px',
  backgroundImage: "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 10 10'><path d='M2 4l3 3 3-3' stroke='%236B7280' stroke-width='1.5' fill='none'/></svg>\")",
  backgroundRepeat: 'no-repeat', backgroundPosition: 'right 12px center',
};
const coLinkBtnStyle = {
  alignSelf: 'flex-start', background: 'transparent', border: 0, padding: 0,
  color: 'var(--bs-blue)', fontSize: 13, fontWeight: 600, cursor: 'pointer', font: 'inherit',
};

// ---------- small local icons (payment card / receipt) ----------------------
const PayIcon = (p) => (
  <svg {...p} viewBox="0 0 20 16" fill="none">
    <rect x="1" y="1" width="18" height="14" rx="2.5" stroke="currentColor" strokeWidth="1.5" />
    <path d="M1 6h18" stroke="currentColor" strokeWidth="1.5" />
  </svg>
);
const ReceiptIcon = (p) => (
  <svg {...p} viewBox="0 0 16 20" fill="none">
    <path d="M2 1h12v17l-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5-2 1.5V1z" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round" />
    <path d="M5 6h6M5 9.5h6M5 13h4" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round" />
  </svg>
);

// ---------- form primitives ---------------------------------------------------
function FieldLabel({ children, required }) {
  return (
    <label style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--bs-ink-2)', display: 'block', marginBottom: 6 }}>
      {children}{required && <span style={{ color: 'var(--bs-danger)' }}> *</span>}
    </label>
  );
}

function TextField({ label, required, error, hint, ...props }) {
  return (
    <div>
      <FieldLabel required={required}>{label}</FieldLabel>
      <input {...props} style={{
        ...coInputBase,
        borderColor: error ? 'var(--bs-danger)' : 'var(--bs-line)',
        background: error ? '#FEF4F3' : '#fff',
      }} />
      {error ? (
        <div style={{ display: 'flex', alignItems: 'center', gap: 5, marginTop: 6, fontSize: 12, color: 'var(--bs-danger)', fontWeight: 500 }}>
          <ErrorDot /> {error}
        </div>
      ) : hint ? (
        <div style={{ marginTop: 6, fontSize: 11.5, color: 'var(--bs-ink-3)' }}>{hint}</div>
      ) : null}
    </div>
  );
}

function ErrorDot() {
  return (
    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" style={{ flex: '0 0 auto' }}>
      <circle cx="6" cy="6" r="5" stroke="currentColor" strokeWidth="1.3" />
      <path d="M6 3.3v3.2M6 8.4v.1" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" />
    </svg>
  );
}

function FieldGrid({ cols = 2, compact, children }) {
  return <div style={{ display: 'grid', gridTemplateColumns: compact ? '1fr' : `repeat(${cols}, 1fr)`, gap: 14 }}>{children}</div>;
}

function RadioRow({ name, value, current, onSelect, label, sublabel }) {
  const isActive = value === current;
  return (
    <label style={{
      display: 'flex', alignItems: 'center', gap: 12,
      padding: '13px 15px',
      border: `1.5px solid ${isActive ? 'var(--bs-blue)' : 'var(--bs-line)'}`,
      background: isActive ? 'var(--bs-blue-soft)' : '#fff',
      borderRadius: 'var(--bs-r-sm)', cursor: 'pointer',
      transition: 'background .15s, border-color .15s',
    }}>
      <input type="radio" name={name} value={value} checked={isActive} onChange={() => onSelect(value)}
        style={{ accentColor: 'var(--bs-blue)', flex: '0 0 auto' }} />
      <div style={{ flex: 1 }}>
        <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--bs-ink)' }}>{label}</div>
        {sublabel && <div style={{ fontSize: 12, color: 'var(--bs-ink-3)', marginTop: 2 }}>{sublabel}</div>}
      </div>
    </label>
  );
}

// Toggle switch — used for optional preference-style choices (newsletter,
// save-account). Agreement to the offer stays a checkbox (unchanged pattern).
function Toggle({ checked, onChange }) {
  return (
    <button type="button" role="switch" aria-checked={checked} onClick={() => onChange(!checked)} style={{
      width: 38, height: 21, borderRadius: 11, border: 'none', padding: 2,
      background: checked ? 'var(--bs-blue)' : 'var(--bs-line)', cursor: 'pointer',
      display: 'inline-flex', alignItems: 'center', flex: '0 0 auto',
      justifyContent: checked ? 'flex-end' : 'flex-start', transition: 'background .15s',
    }}>
      <span style={{ width: 17, height: 17, borderRadius: '50%', background: '#fff', display: 'block', boxShadow: '0 1px 2px rgba(0,0,0,.25)' }} />
    </button>
  );
}

function ToggleRow({ checked, onChange, label, hint }) {
  return (
    <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
      <div style={{ marginTop: 1 }}><Toggle checked={checked} onChange={onChange} /></div>
      <div style={{ flex: 1 }}>
        <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--bs-ink)' }}>{label}</div>
        {hint && <div style={{ fontSize: 11.5, color: 'var(--bs-ink-3)', marginTop: 3, lineHeight: 1.5 }}>{hint}</div>}
      </div>
    </div>
  );
}

// Generic reCAPTCHA-style placeholder — never redraw a third-party brand mark;
// a labeled placeholder communicates "captcha widget here" without it.
function CaptchaBlock() {
  return (
    <div>
      <FieldLabel>Перевірка безпеки</FieldLabel>
      <div style={{
        display: 'flex', alignItems: 'center', gap: 12,
        maxWidth: 304, height: 74, padding: '0 16px', boxSizing: 'border-box',
        border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r-sm)', background: '#fff',
      }}>
        <div style={{ width: 24, height: 24, border: '1.5px solid var(--bs-line)', borderRadius: 4, flex: '0 0 auto' }} />
        <span style={{ fontSize: 13, color: 'var(--bs-ink-2)', flex: 1 }}>Я не робот</span>
        <div className="bs-img-ph" data-label="CAPTCHA" style={{ width: 46, height: 46, borderRadius: 6, fontSize: 6.5, flex: '0 0 auto' }} />
      </div>
    </div>
  );
}

// Card shell shared by all 4 sections on desktop — icon chip + title.
function CoCard({ icon, title, children }) {
  return (
    <section className="bs-card" style={{ padding: 22 }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 18 }}>
        <span style={{
          width: 30, height: 30, borderRadius: 'var(--bs-r-sm)',
          background: 'var(--bs-bg)', color: 'var(--bs-ink-2)',
          display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 auto',
        }}>{icon}</span>
        <h3 style={{ fontSize: 16, margin: 0, flex: 1 }}>{title}</h3>
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>{children}</div>
    </section>
  );
}

// Mobile equivalent — collapsible: header row always visible (icon, title,
// one-line summary when collapsed, chevron); body only when open.
function MobileCollapsible({ icon, title, summary, defaultOpen, children }) {
  const [open, setOpen] = React.useState(defaultOpen);
  return (
    <section className="bs-card" style={{ padding: 0, overflow: 'hidden' }}>
      <button onClick={() => setOpen(o => !o)} style={{
        width: '100%', display: 'flex', alignItems: 'center', gap: 10,
        padding: '14px 16px', background: 'transparent', border: 0, cursor: 'pointer', font: 'inherit', textAlign: 'left',
      }}>
        <span style={{
          width: 28, height: 28, borderRadius: 'var(--bs-r-sm)', background: 'var(--bs-bg)',
          color: 'var(--bs-ink-2)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 auto',
        }}>{icon}</span>
        <span style={{ flex: 1, minWidth: 0 }}>
          <span style={{ display: 'block', fontSize: 14, fontWeight: 700, color: 'var(--bs-ink)' }}>{title}</span>
          {!open && summary && (
            <span style={{ display: 'block', fontSize: 12, color: 'var(--bs-ink-3)', marginTop: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{summary}</span>
          )}
        </span>
        <I.Chevron width="13" height="13" style={{
          color: 'var(--bs-ink-3)', transform: open ? 'rotate(180deg)' : 'none', transition: 'transform .2s', flex: '0 0 auto',
        }} />
      </button>
      {open && (
        <div style={{ padding: '2px 16px 20px', borderTop: '1px solid var(--bs-line)' }}>
          <div style={{ paddingTop: 16, display: 'flex', flexDirection: 'column', gap: 14 }}>{children}</div>
        </div>
      )}
    </section>
  );
}

// ---------- 1. Отримувач ------------------------------------------------------
function ReceiverFields({ errors, compact }) {
  return (
    <>
      <FieldGrid compact={compact}>
        <TextField label="Ім'я" required defaultValue="Євгеній" />
        <TextField label="Прізвище" required error={errors ? 'Це поле обов’язкове' : null} />
      </FieldGrid>
      <FieldGrid compact={compact}>
        <TextField label="По батькові" hint="Необов'язково" />
        <TextField label="Телефон" required defaultValue="099111"
          error={errors ? 'Введіть номер у форматі 0XXXXXXXXX' : null} />
      </FieldGrid>
      <TextField label="Електронна пошта" required type="email" defaultValue="evgenij.leusenko@gmail.com" />
    </>
  );
}
function ReceiverCard({ errors }) {
  return <CoCard icon={<I.User width="16" height="16" />} title="Отримувач"><ReceiverFields errors={errors} /></CoCard>;
}

// ---------- 2. Доставка -------------------------------------------------------
function DeliveryFields({ compact, guest }) {
  const [ship, setShip] = React.useState('branch');
  const [mode, setMode] = React.useState(guest ? 'manual' : 'saved');
  const [subscribe, setSubscribe] = React.useState(false);

  return (
    <>
      <RadioRow name="ship" value="branch" current={ship} onSelect={setShip}
        label="Нова пошта — у відділення" sublabel="За тарифами Нової пошти · ~2–3 дні" />
      <RadioRow name="ship" value="courier" current={ship} onSelect={setShip}
        label="Нова пошта — кур'єром" sublabel="Адресна доставка" />
      <RadioRow name="ship" value="postomat" current={ship} onSelect={setShip}
        label="Нова пошта — поштомат" />

      {mode === 'saved' ? (
        <>
          <div>
            <FieldLabel>Відділення</FieldLabel>
            <select style={coSelectBase}><option>№22, вул. Савкіна, Будинок 6, Дніпро</option></select>
          </div>
          <button type="button" onClick={() => setMode('manual')} style={coLinkBtnStyle}>+ Інша адреса</button>
        </>
      ) : (
        <>
          <FieldGrid compact={compact}>
            <div><FieldLabel required>Область</FieldLabel><input placeholder="Почніть вводити область" style={coInputBase} /></div>
            <div><FieldLabel required>Місто</FieldLabel><input placeholder="Спочатку оберіть область" disabled style={coInputDisabled} /></div>
          </FieldGrid>
          <FieldGrid compact={compact}>
            <div>
              <FieldLabel required>Тип доставки</FieldLabel>
              <select style={coSelectBase}><option>Відділення</option><option>Поштомат</option><option>Кур'єр</option></select>
            </div>
            <div><FieldLabel required>Відділення або поштомат</FieldLabel><input placeholder="Спочатку оберіть місто" disabled style={coInputDisabled} /></div>
          </FieldGrid>

          {!guest && (
            <button type="button" onClick={() => setMode('saved')} style={coLinkBtnStyle}>← Використати збережену адресу</button>
          )}
          {guest && (
            <>
              <CaptchaBlock />
              <ToggleRow checked={subscribe} onChange={setSubscribe} label="Хочу отримувати новини Booster Shop" />
            </>
          )}
        </>
      )}
    </>
  );
}
function DeliveryCard({ guest }) {
  return <CoCard icon={<I.Truck width="16" height="16" />} title="Доставка"><DeliveryFields guest={guest} /></CoCard>;
}

// ---------- 3. Оплата ---------------------------------------------------------
function PaymentFields() {
  const [pay, setPay] = React.useState('card');
  return (
    <>
      <RadioRow name="pay" value="card" current={pay} onSelect={setPay}
        label="Картка, Google Pay / Apple Pay" sublabel="Безпечно через еквайринг" />
      <RadioRow name="pay" value="cod" current={pay} onSelect={setPay}
        label="Оплата при отриманні (накладений платіж)" />
      <RadioRow name="pay" value="iban" current={pay} onSelect={setPay}
        label="За реквізитами на IBAN" />
    </>
  );
}
function PaymentCard() {
  return <CoCard icon={<PayIcon width="17" height="14" />} title="Оплата"><PaymentFields /></CoCard>;
}

// ---------- 4. Замовлення — progress / items / totals / tail -----------------
const CO_ITEMS_DEFAULT = [
  { id: 'sym', title: 'Бустер Pokémon TCG: Mega Symphonia (Японське видання)', price: 150, qty: 2 },
  { id: 'op11', title: 'Бустер One Piece Card Game OP-11 (Японське видання)', price: 200, qty: 2 },
];
const CO_ITEMS_MANY = [
  { id: 'sym', title: 'Бустер Pokémon TCG: Mega Symphonia (Японське видання)', price: 150, qty: 2 },
  { id: 'eb03', title: 'Бустер One Piece Card Game EB-03 (Японське видання)', price: 180, qty: 1 },
  { id: 'ninja', title: 'Бустер Pokémon TCG: Ninja Spinner (Японське видання)', price: 150, qty: 3 },
  { id: 'brave', title: 'Бустер Pokémon TCG: Mega Brave (Японське видання)', price: 150, qty: 1 },
  { id: 'heroines', title: 'Бустер One Piece Card Game: Heroines Edition', price: 220, qty: 2 },
];
const CO_ITEMS_FREESHIP = [
  { id: 'sym', title: 'Бустер Pokémon TCG: Mega Symphonia (Японське видання)', price: 150, qty: 6 },
  { id: 'op11', title: 'Бустер One Piece Card Game OP-11 (Японське видання)', price: 200, qty: 6 },
];

function useCoTotals(items, discountPct = 0) {
  const sub = items.reduce((s, it) => s + it.price * it.qty, 0);
  const qty = items.reduce((s, it) => s + it.qty, 0);
  const discount = discountPct ? Math.round(sub * discountPct / 100) : 0;
  const payable = sub - discount;
  return { sub, qty, discount, payable, freeShip: payable >= FREE_SHIP_THRESHOLD };
}

// Shipping cost + free-shipping incentive merged into ONE block — the price
// currently due (API-driven in prod) sits right next to the progress toward
// free shipping, instead of a plain totals-row plus a separate callout.
function ShippingBlock({ payable }) {
  const apiShipPrice = 65; // sample — real number comes from the carrier API, already wired
  const remaining = Math.max(0, FREE_SHIP_THRESHOLD - payable);
  const pct = Math.min(100, Math.round((payable / FREE_SHIP_THRESHOLD) * 100));
  const done = remaining <= 0;
  return (
    <div style={{ padding: '12px 14px', borderRadius: 'var(--bs-r-sm)', background: done ? '#EAF7EE' : 'var(--bs-blue-soft)' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 9 }}>
        <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--bs-ink-2)' }}>Доставка</span>
        <span style={{ fontSize: 14.5, fontWeight: 800, color: done ? 'var(--bs-green)' : 'var(--bs-ink)' }}>
          {done ? 'Безкоштовно' : `₴${apiShipPrice}`}
        </span>
      </div>
      <div style={{ fontSize: 12, fontWeight: 600, color: done ? 'var(--bs-green)' : 'var(--bs-blue)', marginBottom: 8 }}>
        {done ? 'Безкоштовна доставка застосована ✓' : `До безкоштовної доставки лишилось ₴${remaining}`}
      </div>
      <div style={{ height: 5, background: '#fff', borderRadius: 999, overflow: 'hidden' }}>
        <div style={{ width: pct + '%', height: '100%', background: done ? 'var(--bs-green)' : 'var(--bs-blue)', borderRadius: 999 }} />
      </div>
    </div>
  );
}

// Caps at ~3 visible rows then scrolls internally — the card's own height no
// longer depends on how many distinct line items are in the cart.
function OrderItems({ items }) {
  const many = items.length > 3;
  return (
    <div style={{
      display: 'flex', flexDirection: 'column', gap: 12,
      maxHeight: many ? 268 : 'none', overflowY: many ? 'auto' : 'visible', paddingRight: many ? 4 : 0,
    }}>
      {items.map(it => (
        <div key={it.id} style={{ display: 'grid', gridTemplateColumns: '48px 1fr auto', gap: 12, alignItems: 'center', flex: '0 0 auto' }}>
          <div style={{ width: 48, height: 48, borderRadius: 'var(--bs-r-sm)', overflow: 'hidden', border: '1px solid var(--bs-line)' }}>
            <ImagePh ratio="1/1" radius="0" label="" />
          </div>
          <div style={{ minWidth: 0 }}>
            <div style={{
              fontSize: 13, fontWeight: 600, color: 'var(--bs-ink)', lineHeight: 1.35,
              display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden',
            }}>{it.title}</div>
            <div style={{ fontSize: 11, color: 'var(--bs-ink-3)', marginTop: 2 }}>× {it.qty}</div>
          </div>
          <div style={{ fontSize: 13.5, fontWeight: 700, color: 'var(--bs-ink)' }}>₴{it.price * it.qty}</div>
        </div>
      ))}
    </div>
  );
}

// "До сплати" is the ITEMS total only (after any promo discount) — shipping
// is shown/tracked separately in ShippingBlock, never folded into this number.
function OrderTotals({ items, discountPct = 0, promoCode }) {
  const { sub, discount, payable } = useCoTotals(items, discountPct);
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6, fontSize: 13.5 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--bs-ink-2)' }}>
        <span>Сума товарів</span><span>₴{sub}</span>
      </div>
      {discount > 0 && (
        <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--bs-green)', fontWeight: 600 }}>
          <span>Знижка ({promoCode} −{discountPct}%)</span><span>−₴{discount}</span>
        </div>
      )}
      <div style={{
        display: 'flex', justifyContent: 'space-between',
        fontSize: 18, fontWeight: 800, color: 'var(--bs-ink)',
        borderTop: '1px solid var(--bs-line)', marginTop: 6, paddingTop: 10,
      }}>
        <span>До сплати</span><span>₴{payable}</span>
      </div>
    </div>
  );
}

// Promo box — plain input+button when nothing's applied; a settled chip row
// (code, %, remove) once it is. FIRST15 demo below shows the applied state.
function PromoField({ promo }) {
  if (promo) {
    return (
      <div>
        <FieldLabel>Промокод</FieldLabel>
        <div style={{
          display: 'flex', alignItems: 'center', gap: 10,
          padding: '10px 12px', borderRadius: 'var(--bs-r-sm)',
          background: '#EAF7EE', border: '1px solid #BBE8CB',
        }}>
          <span style={{
            display: 'inline-flex', alignItems: 'center', gap: 5,
            fontSize: 12.5, fontWeight: 700, color: 'var(--bs-green)',
            background: '#fff', border: '1px solid #BBE8CB', borderRadius: 999, padding: '4px 10px',
          }}>{promo.code} · −{promo.pct}%</span>
          <span style={{ flex: 1, fontSize: 12.5, color: 'var(--bs-ink-2)' }}>Промокод застосовано</span>
          <button type="button" style={{ ...coLinkBtnStyle, color: 'var(--bs-ink-3)' }}>Прибрати</button>
        </div>
      </div>
    );
  }
  return (
    <div>
      <FieldLabel>Промокод</FieldLabel>
      <div style={{ display: 'flex', gap: 8 }}>
        <input placeholder="Введіть промокод" style={{ ...coInputBase, flex: 1 }} />
        <button className="bs-btn bs-btn-secondary" style={{ borderColor: 'var(--bs-blue)', color: 'var(--bs-blue)' }}>Застосувати</button>
      </div>
    </div>
  );
}

// Promo + comment + agreement + (guest-only) save-account toggle + the single
// primary CTA. Agreement defaults UNCHECKED (matches production — no
// pre-ticked legal consent); the CTA reads as dimmed until checked, which is
// the correct/expected state, not an error. The red inline message only
// appears once a submit was attempted (the `errors` demo prop).
function OrderTail({ errors, guest, promo }) {
  const [agree, setAgree] = React.useState(false);
  const [saveAccount, setSaveAccount] = React.useState(true);
  const showAgreeError = errors && !agree;
  const dimmed = !agree;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16, marginTop: 4 }}>
      <PromoField promo={promo} />

      <div>
        <FieldLabel>Коментар до замовлення</FieldLabel>
        <textarea rows={2} placeholder="Наприклад: бустери для подарунка, упакуйте, будь ласка."
          style={{ ...coInputBase, resize: 'vertical' }} />
      </div>

      <label style={{ display: 'flex', gap: 10, alignItems: 'flex-start', cursor: 'pointer', fontSize: 12, color: 'var(--bs-ink-2)', lineHeight: 1.55 }}>
        <input type="checkbox" checked={agree} onChange={e => setAgree(e.target.checked)}
          style={{ marginTop: 2, accentColor: 'var(--bs-blue)', flex: '0 0 auto' }} />
        <span>
          Погоджуюсь з умовами <a href="#" style={{ color: 'var(--bs-blue)' }}>Публічної оферти</a>,
          включно з положеннями про обробку персональних даних.
        </span>
      </label>

      {guest && (
        <ToggleRow checked={saveAccount} onChange={setSaveAccount}
          label="Зберегти дані та зареєструватись"
          hint="Створимо обліковий запис і надішлемо одноразове посилання для встановлення пароля." />
      )}

      {showAgreeError && (
        <div style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 12, color: 'var(--bs-danger)', fontWeight: 500 }}>
          <ErrorDot /> Погодьтесь з умовами, щоб продовжити
        </div>
      )}

      <button className="bs-btn bs-btn-primary" style={{ padding: '15px', fontSize: 15, opacity: dimmed ? 0.5 : 1, cursor: dimmed ? 'not-allowed' : 'pointer' }}>
        Підтвердити замовлення →
      </button>
    </div>
  );
}

function AuthNudge({ compact }) {
  return (
    <div style={{
      display: 'flex', alignItems: 'center', gap: compact ? 10 : 14,
      padding: compact ? '11px 14px' : '12px 16px',
      background: 'var(--bs-blue-soft)', border: '1px solid #c7d2fe', borderRadius: 'var(--bs-r-sm)',
    }}>
      <I.User width={compact ? '15' : '16'} height={compact ? '15' : '16'} style={{ color: 'var(--bs-blue)', flex: '0 0 auto' }} />
      <div style={{ flex: 1, fontSize: compact ? 13 : 13.5, color: 'var(--bs-ink-2)' }}>
        {compact ? 'Маєш акаунт?' : <>Маєш акаунт? <strong>Авторизуйся</strong>, щоб не вводити дані заново.</>}
      </div>
      <button className="bs-btn bs-btn-ghost" style={{ color: 'var(--bs-blue)', padding: compact ? '4px 8px' : '6px 10px', fontSize: compact ? 12.5 : 13 }}>
        Увійти{!compact && ' в акаунт'} →
      </button>
    </div>
  );
}

function CoBreadcrumb() {
  return (
    <nav style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12.5, color: 'var(--bs-ink-3)', marginBottom: 14 }}>
      <span style={{ display: 'inline-flex' }}><I.Home width="12" height="12" /></span>
      <span>›</span><span>Мій кошик</span><span>›</span>
      <span style={{ color: 'var(--bs-ink)' }}>Оформити замовлення</span>
    </nav>
  );
}

// ---------- Desktop composition ----------------------------------------------
function CheckoutDesktop({ errors = false, guest = false, items = CO_ITEMS_DEFAULT, promo = null }) {
  const { qty, payable } = useCoTotals(items, promo ? promo.pct : 0);
  return (
    <div className="bs-mock" style={{ minHeight: '100%' }}>
      <HeaderV1 />
      <main style={{ maxWidth: 1240, margin: '0 auto', padding: '20px 32px 56px' }}>
        <CoBreadcrumb />
        <h1 style={{ marginBottom: 18 }}>Оформити замовлення</h1>
        {guest && <div style={{ marginBottom: 20 }}><AuthNudge /></div>}

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 380px', gap: 20, alignItems: 'flex-start' }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
            <ReceiverCard errors={errors} />
            <DeliveryCard guest={guest} />
            <PaymentCard />
          </div>

          <aside style={{ position: 'sticky', top: 16 }}>
            <CoCard icon={<ReceiptIcon width="14" height="17" />} title={`Замовлення · ${qty} товари`}>
              <OrderItems items={items} />
              <ShippingBlock payable={payable} />
              <OrderTotals items={items} discountPct={promo ? promo.pct : 0} promoCode={promo ? promo.code : ''} />
              <OrderTail errors={errors} guest={guest} promo={promo} />
            </CoCard>
          </aside>
        </div>
      </main>
    </div>
  );
}

// ---------- Mobile composition ------------------------------------------------
function OrderSummaryMobile({ defaultOpen = false, items, promo = null }) {
  const [open, setOpen] = React.useState(defaultOpen);
  const { qty, payable } = useCoTotals(items, promo ? promo.pct : 0);
  return (
    <section className="bs-card" style={{ padding: 0, overflow: 'hidden' }}>
      <button onClick={() => setOpen(o => !o)} style={{
        width: '100%', display: 'flex', alignItems: 'center', gap: 10,
        padding: '15px 16px', background: 'transparent', border: 0, cursor: 'pointer', font: 'inherit', textAlign: 'left',
      }}>
        <ReceiptIcon width="13" height="16" style={{ color: 'var(--bs-ink-2)', flex: '0 0 auto' }} />
        <span style={{ flex: 1, fontSize: 14, fontWeight: 700, color: 'var(--bs-ink)' }}>Ваше замовлення · {qty} товари</span>
        <span style={{ fontSize: 15, fontWeight: 800, color: 'var(--bs-ink)' }}>₴{payable}</span>
        <I.Chevron width="13" height="13" style={{ color: 'var(--bs-ink-3)', transform: open ? 'rotate(180deg)' : 'none', transition: 'transform .2s', flex: '0 0 auto' }} />
      </button>
      {open && (
        <div style={{ padding: '2px 16px 18px', borderTop: '1px solid var(--bs-line)', display: 'flex', flexDirection: 'column', gap: 14 }}>
          <OrderItems items={items} />
          <ShippingBlock payable={payable} />
          <OrderTotals items={items} discountPct={promo ? promo.pct : 0} promoCode={promo ? promo.code : ''} />
        </div>
      )}
    </section>
  );
}

// filled: returning customer already has Отримувач/Доставка data saved →
// those two collapse by default. Guest (or no saved data) → expanded.
// forceOpen lets one card override the default (demoing the tap-to-edit
// interaction while its sibling stays collapsed).
function CheckoutMobile({ errors = false, guest = false, filled = true, items = CO_ITEMS_DEFAULT, forceOpen = null, promo = null }) {
  const collapseDefault = !guest && filled;
  const receiverOpen = forceOpen === 'receiver' ? true : !collapseDefault;
  const deliveryOpen = forceOpen === 'delivery' ? true : !collapseDefault;

  return (
    <div className="bs-mock" style={{ minHeight: '100%' }}>
      <div style={{ padding: '14px 16px', borderBottom: '1px solid var(--bs-line)', background: '#fff', display: 'flex', alignItems: 'center', gap: 10 }}>
        <div style={{ fontWeight: 800, fontSize: 15, color: 'var(--bs-ink)' }}>Booster Shop</div>
        <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 5, fontSize: 11, color: 'var(--bs-ink-3)' }}>
          <I.Shield width="12" height="12" /> Безпечне оформлення
        </div>
      </div>

      <div style={{ padding: '16px 14px 40px', display: 'flex', flexDirection: 'column', gap: 14 }}>
        <h1 style={{ fontSize: 21 }}>Оформити замовлення</h1>

        {guest && <AuthNudge compact />}

        <OrderSummaryMobile defaultOpen={false} items={items} promo={promo} />

        <MobileCollapsible icon={<I.User width="15" height="15" />} title="Отримувач"
          summary="Євгеній Леусенко · 099 111 92 79" defaultOpen={receiverOpen}>
          <ReceiverFields errors={errors} compact />
        </MobileCollapsible>

        <MobileCollapsible icon={<I.Truck width="15" height="15" />} title="Доставка"
          summary="Нова пошта · відділення №22" defaultOpen={deliveryOpen}>
          <DeliveryFields compact guest={guest} />
        </MobileCollapsible>

        <CoCard icon={<PayIcon width="17" height="14" />} title="Оплата"><PaymentFields /></CoCard>

        <section className="bs-card" style={{ padding: 18 }}>
          <OrderTail errors={errors} guest={guest} promo={promo} />
        </section>
      </div>
    </div>
  );
}

const PROMO_FIRST15 = { code: 'FIRST15', pct: 15 };

Object.assign(window, {
  CheckoutDesktop, CheckoutMobile,
  ReceiverCard, DeliveryCard, PaymentCard, OrderItems, OrderTotals, OrderTail,
  PROMO_FIRST15,
});
