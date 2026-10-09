// PAY-001-UI — «Оплата частинами»: товарна сторінка (CTA + інформ. блок + модалка
// вибору кредитної пропозиції). Два банки, обидва живі: monobank (чорний/білий,
// лапка) і ПУМБ (офіційний червоний #E60C2A, «Сплачуйте частинами» — назва за
// банківським гайдом, не скорочувати інакше). Логіка спільна на ~90% (той самий
// creditMonthly/поріг/пікер 3-4-5) — розходиться лише в моменті списання першого
// платежу: monobank списує його одразу при покупці (тому ніде не пишемо «без
// першого платежу» для monobank), ПУМБ — навпаки, це його реальна й банком
// заявлена перевага (перший платіж лише через місяць), тож підпис показуємо
// лише в картках ПУМБ.
//
// PAY-001-UI2: тизер і CTA завжди в DOM — при сумі нижче порогу або
// передзамовленні не зникають, а притлумлюються (opacity) з підказкою; те саме
// притлумлення тепер стосується ОБОХ банківських рядків одночасно (умова —
// властивість товару, не банку).

const CREDIT_MIN_AMOUNT = 500;
const CREDIT_MAX_PAYMENTS = 5;
const CREDIT_PAYMENT_OPTIONS = [3, 4, 5];
const PUMB_LOGO = 'uploads/pasted-1784456162944-0.png';

function creditMonthly(price, count = CREDIT_MAX_PAYMENTS) {
  return Math.ceil(price / count);
}
function paymentsWord(n) {
  return n >= 2 && n <= 4 ? 'платежі' : 'платежів';
}
// monobank charges the 1st installment today (N-1 remain after checkout); ПУМБ
// defers the 1st a month (all N remain — nothing has been charged yet).
function paymentsRemaining(bank, count) {
  return bank === 'pumb' ? count : count - 1;
}

// monobank paw — per guideline: black fill, white outline, soft drop shadow.
// Never rotate, recolor, or drop the shadow (muting uses container opacity only).
function MonoPaw({ size = 22 }) {
  return (
    <span style={{ display: 'inline-flex', flex: '0 0 auto', filter: 'drop-shadow(0 1px 1.5px rgba(0,0,0,.4))' }}>
      <svg width={size} height={size} viewBox="0 0 32 32" style={{ display: 'block' }}>
        <g fill="#111" stroke="#fff" strokeWidth="1.6" strokeLinejoin="round" paintOrder="stroke">
          <ellipse cx="16" cy="21" rx="8.2" ry="7" />
          <ellipse cx="5.5" cy="11.5" rx="3.3" ry="4.3" transform="rotate(-20 5.5 11.5)" />
          <ellipse cx="12.2" cy="6.5" rx="3.4" ry="4.5" transform="rotate(-7 12.2 6.5)" />
          <ellipse cx="19.8" cy="6.5" rx="3.4" ry="4.5" transform="rotate(7 19.8 6.5)" />
          <ellipse cx="26.5" cy="11.5" rx="3.3" ry="4.3" transform="rotate(20 26.5 11.5)" />
        </g>
      </svg>
    </span>
  );
}

function MonoMark({ size = 14, sub = false }) {
  return (
    <span style={{ display: 'inline-flex', alignItems: 'baseline', gap: 6, minWidth: 0 }}>
      <span style={{ fontWeight: 800, fontSize: size, letterSpacing: '-0.03em', color: '#111' }}>monobank</span>
      {sub && <span style={{ fontWeight: 500, fontSize: size * 0.62, color: 'var(--bs-ink-3)' }}>Universal Bank</span>}
    </span>
  );
}

// Hollow variant of the paw for use on dark surfaces (guideline's "black" sticker style).
function MonoPawOutline({ size = 18 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 32 32" style={{ display: 'block', flex: '0 0 auto' }}>
      <g fill="none" stroke="#fff" strokeWidth="2.1" strokeLinejoin="round">
        <ellipse cx="16" cy="21" rx="8.2" ry="7" />
        <ellipse cx="5.5" cy="11.5" rx="3.3" ry="4.3" transform="rotate(-20 5.5 11.5)" />
        <ellipse cx="12.2" cy="6.5" rx="3.4" ry="4.5" transform="rotate(-7 12.2 6.5)" />
        <ellipse cx="19.8" cy="6.5" rx="3.4" ry="4.5" transform="rotate(7 19.8 6.5)" />
        <ellipse cx="26.5" cy="11.5" rx="3.3" ry="4.3" transform="rotate(20 26.5 11.5)" />
      </g>
    </svg>
  );
}

// Compact black "sticker" lockup — a livelier mono brand mark for the checkout
// bank line, per guideline's ready black-card asset style.
function MonoStickerBadge({ height = 36 }) {
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 8, height, padding: '0 13px',
      background: '#111', borderRadius: height / 2, flex: '0 0 auto', boxShadow: '0 2px 6px rgba(0,0,0,.18)',
    }}>
      <MonoPawOutline size={height - 16} />
      <span style={{ color: '#fff', fontWeight: 800, fontSize: 13.5, letterSpacing: '-0.02em' }}>monobank</span>
    </span>
  );
}

// ПУМБ wordmark — styled text (not a vector trace of their custom condensed
// logotype from the brand pack); red on light surfaces, white on the sticker.
function PumbMark({ size = 14, on = 'light' }) {
  return (
    <span style={{
      fontWeight: 800, fontSize: size, letterSpacing: '-0.01em', textTransform: 'uppercase',
      color: on === 'light' ? 'var(--bs-pumb-red)' : '#fff',
    }}>ПУМБ</span>
  );
}

// Compact red "sticker" lockup for ПУМБ — same shape language as MonoStickerBadge
// so the two read as peers in the checkout drawer.
function PumbStickerBadge({ height = 36 }) {
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 8, height, padding: '0 13px',
      background: 'var(--bs-pumb-red)', borderRadius: height / 2, flex: '0 0 auto', boxShadow: '0 2px 6px rgba(230,12,42,.25)',
    }}>
      <img src={PUMB_LOGO} alt="" style={{ width: height - 16, height: height - 16, borderRadius: 5, display: 'block', flex: '0 0 auto' }} />
      <PumbMark size={13.5} on="dark" />
    </span>
  );
}

function SoonTag() {
  return (
    <span style={{
      fontSize: 9.5, fontWeight: 700, padding: '3px 7px', borderRadius: 999,
      background: 'var(--bs-line-2)', color: 'var(--bs-ink-3)', textTransform: 'uppercase', letterSpacing: '.03em', flex: '0 0 auto',
    }}>Скоро буде</span>
  );
}

// Small circled-i used next to the muted-state hint copy (threshold / preorder).
function HintIcon() {
  return (
    <svg width="13" height="13" viewBox="0 0 16 16" fill="none" style={{ flex: '0 0 auto' }}>
      <circle cx="8" cy="8" r="6.5" stroke="currentColor" strokeWidth="1.3" />
      <path d="M8 7.3v4M8 5.1v.1" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" />
    </svg>
  );
}

function PaymentsPicker({ value, onChange, options = CREDIT_PAYMENT_OPTIONS, disabled = false, accent = '#111' }) {
  return (
    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
      {options.map((n) => {
        const active = n === value && !disabled;
        return (
          <button key={n} type="button" disabled={disabled} onClick={() => onChange(n)}
            className={!disabled && n !== value ? 'pay001-chip' : ''}
            style={{
              padding: '7px 12px', borderRadius: 'var(--bs-r-pill)', fontSize: 12.5, fontWeight: 700,
              border: `1.5px solid ${active ? accent : 'var(--bs-line)'}`,
              background: active ? accent : '#fff', color: disabled ? 'var(--bs-ink-4)' : (active ? '#fff' : 'var(--bs-ink-2)'),
              cursor: disabled ? 'not-allowed' : 'pointer', font: 'inherit', whiteSpace: 'nowrap',
              opacity: disabled ? .6 : 1, transition: 'background .15s, border-color .15s, opacity .2s',
            }}>{n} {paymentsWord(n)}</button>
        );
      })}
    </div>
  );
}

function InfoStat({ label, value }) {
  return (
    <div>
      <div style={{ fontSize: 11, color: 'var(--bs-ink-3)' }}>{label}</div>
      <div style={{ fontSize: 14.5, fontWeight: 800, color: 'var(--bs-ink)' }}>{value}</div>
    </div>
  );
}

// Non-interactive info block on the product page — bank name, max payments,
// "Покупка частинами" label, logo. Does not open anything; the modal opens
// from the separate "Оплатити частинами" CTA next to "Додати в кошик".
// Always rendered (never returns null): below the 500 ₴ threshold or on
// preorder items it stays in the DOM at reduced opacity with a hint instead
// of disappearing, so it can "wake up" live when qty/stock change. Both bank
// rows mute together — the condition belongs to the product, not the bank.
function CreditTeaser({ price, preorder = false }) {
  const below = price < CREDIT_MIN_AMOUNT;
  const state = preorder ? 'preorder' : below ? 'threshold' : 'active';
  const muted = state !== 'active';
  const remaining = Math.max(0, CREDIT_MIN_AMOUNT - price);
  const hint = state === 'preorder'
    ? 'Оплата частинами буде доступна після надходження товару.'
    : state === 'threshold'
      ? `Оплата частинами доступна від ₴${CREDIT_MIN_AMOUNT} — додайте ще ₴${remaining} у кошик.`
      : '';

  const rowStyle = { display: 'flex', alignItems: 'center', gap: 12, padding: '10px 12px', border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r-sm)', opacity: muted ? .5 : 1, transition: 'opacity .25s ease' };

  return (
    <div className="bs-card" style={{ padding: 14, display: 'flex', flexDirection: 'column', gap: 10 }}>
      <div style={{
        fontFamily: '"JetBrains Mono", ui-monospace, monospace', fontSize: 10.5,
        letterSpacing: '.1em', textTransform: 'uppercase', color: 'var(--bs-ink-3)',
      }}>Оплата частинами</div>

      <div style={rowStyle}>
        <MonoPaw size={26} />
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 13.5, fontWeight: 700, color: 'var(--bs-ink)' }}>Покупка частинами <MonoMark size={13} /></div>
          <div style={{ fontSize: 12, color: 'var(--bs-ink-3)', marginTop: 2 }}>До {CREDIT_MAX_PAYMENTS} платежів</div>
        </div>
      </div>

      <div style={rowStyle}>
        <img src={PUMB_LOGO} alt="ПУМБ" style={{ width: 26, height: 26, borderRadius: 6, flex: '0 0 auto', display: 'block' }} />
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 13.5, fontWeight: 700, color: 'var(--bs-ink)' }}>Сплачуйте частинами <PumbMark size={13} /></div>
          <div style={{ fontSize: 12, color: 'var(--bs-ink-3)', marginTop: 2 }}>До {CREDIT_MAX_PAYMENTS} платежів · Без першого платежу</div>
        </div>
      </div>

      {/* Collapsible hint row — grid-rows trick animates height+fade on state change, no reload. */}
      <div style={{ display: 'grid', gridTemplateRows: hint ? '1fr' : '0fr', transition: 'grid-template-rows .25s ease' }}>
        <div style={{ overflow: 'hidden', display: 'flex', alignItems: 'center', gap: 6, color: 'var(--bs-ink-3)', fontSize: 12, lineHeight: 1.4, opacity: hint ? 1 : 0, transition: 'opacity .2s ease' }}>
          <HintIcon /><span>{hint}</span>
        </div>
      </div>
    </div>
  );
}

// The actual clickable CTA on the product page — secondary to "Додати в кошик"
// but clearly visible. Opens CreditModal. Mirrors CreditTeaser's muted state
// (disabled, not hidden) so the two never disagree about availability.
function InstallmentsCTA({ price, preorder = false, onOpen }) {
  const muted = preorder || price < CREDIT_MIN_AMOUNT;
  return (
    <button onClick={muted ? undefined : onOpen} disabled={muted} className="bs-btn" style={{
      padding: '11px 16px', fontSize: 14, fontWeight: 700,
      background: '#fff', color: muted ? 'var(--bs-ink-4)' : '#111',
      border: `1.5px solid ${muted ? 'var(--bs-line)' : '#111'}`,
      cursor: muted ? 'not-allowed' : 'pointer', opacity: muted ? .65 : 1,
      transition: 'opacity .25s ease, color .25s ease, border-color .25s ease',
    }}>
      Оплатити частинами
    </button>
  );
}

// Selectable bank card used inside the modal — both banks are live and pickable;
// clicking anywhere on a card (including its own picker) selects it. Selected
// card gets the bank's accent border + tint; the other stays neutral but stays
// fully interactive (its picker still works and switches selection to it).
function BankModalCard({ bank, selected, onSelect, price, paymentsCount, setPaymentsCount }) {
  const isMono = bank === 'monobank';
  const accent = isMono ? '#111' : 'var(--bs-pumb-red)';
  const tint = isMono ? '#fafafa' : 'var(--bs-pumb-red-soft)';
  const monthly = creditMonthly(price, paymentsCount);
  return (
    <div style={{
      display: 'flex', flexDirection: 'column', gap: 12, padding: 16,
      border: `1.5px solid ${selected ? accent : 'var(--bs-line)'}`, borderRadius: 'var(--bs-r)',
      background: selected ? tint : '#fff', transition: 'border-color .15s, background .15s',
    }}>
      <label style={{ display: 'flex', alignItems: 'center', gap: 10, cursor: 'pointer' }} onClick={() => onSelect(bank)}>
        <input type="radio" name="pay001-bank" checked={selected} onChange={() => onSelect(bank)} style={{ accentColor: accent, flex: '0 0 auto' }} />
        {isMono ? <MonoPaw size={30} /> : <img src={PUMB_LOGO} alt="" style={{ width: 30, height: 30, borderRadius: 7, flex: '0 0 auto', display: 'block' }} />}
        <div style={{ flex: 1, minWidth: 0 }}>
          {isMono ? <MonoMark size={15} /> : <PumbMark size={15} />}
          <div style={{ fontSize: 12, color: 'var(--bs-ink-3)', marginTop: 1 }}>{isMono ? 'Покупка частинами' : 'Сплачуйте частинами · Без першого платежу'}</div>
        </div>
      </label>

      <PaymentsPicker value={paymentsCount} onChange={(n) => { onSelect(bank); setPaymentsCount(n); }} accent={selected ? accent : 'var(--bs-ink-4)'} />

      <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap', borderTop: '1px solid var(--bs-line-2)', paddingTop: 10 }}>
        <InfoStat label="Щомісячний платіж" value={`₴${monthly}`} />
        <InfoStat label="Платежів" value={paymentsCount} />
        <InfoStat label="Вартість товару" value={`₴${price}`} />
      </div>
    </div>
  );
}

// device='mobile' → bottom sheet; otherwise centered dialog.
// PAY-001-UI2 Q1 (revised — QA round 2, 2026-07-24): footer now has TWO real
// actions, not one committed + one no-op. Primary "Купити частинами" adds to
// cart AND proceeds to checkout with the chosen bank/count. Secondary
// "Продовжити покупки" ALSO adds to cart (same bank/count) but stays put —
// this is what was missing before (picking a bank/count and closing added
// nothing to the cart). The header's × stays a true no-op close for anyone
// who wants to back out with no side effects. Both footer actions show their
// own loading state while "adding".
function CreditModal({ open, device, price, paymentsCount, setPaymentsCount, selectedBank, setSelectedBank, onClose, onChoose, onContinueShopping }) {
  const [adding, setAdding] = React.useState(null); // null | 'checkout' | 'continue'
  React.useEffect(() => { if (open) setAdding(null); }, [open]);
  if (!open) return null;
  const sheet = device === 'mobile';
  // The app fakes a phone viewport with a 390px-wide centered column (not a real
  // iframe), so a plain `inset:0` fixed layer would span the whole real browser
  // window instead of that column. Pin the overlay to the same column when
  // sheet===true; desktop keeps the normal full-viewport centered dialog.
  const overlayRect = sheet
    ? { position: 'fixed', top: 0, bottom: 0, left: '50%', width: 390, transform: 'translateX(-50%)' }
    : { position: 'fixed', inset: 0 };

  const handlePrimary = () => {
    if (adding) return;
    setAdding('checkout');
    // Prototype-only stand-in for the real add-to-cart request; on the live
    // site this is the same AJAX cart-add the main "Додати в кошик" button
    // uses, then a redirect to checkout with the chosen bank + payments count.
    setTimeout(() => { onChoose(selectedBank); }, 600);
  };
  // QA round 2: was a plain "Закрити" no-op — the reported bug (bank/count
  // chosen but nothing landed in the cart unless the shopper also went
  // through checkout) means this button must ALSO commit the cart-add, just
  // without the redirect. Same AJAX in real impl, same success notification
  // the site already shows for the main "Додати в кошик" button — no new
  // toast component needed on the production side.
  const handleSecondary = () => {
    if (adding) return;
    setAdding('continue');
    setTimeout(() => { onContinueShopping(selectedBank); }, 600);
  };

  return (
    <div onClick={adding ? undefined : onClose} style={{
      ...overlayRect, zIndex: 200,
      background: 'linear-gradient(to bottom, rgba(17,24,39,0.45), rgba(17,24,39,0.6))',
      backdropFilter: 'blur(2px)',
      display: 'flex', alignItems: sheet ? 'flex-end' : 'center', justifyContent: 'center',
    }}>
      <div onClick={(e) => e.stopPropagation()} style={{
        background: 'var(--bs-paper)', width: sheet ? '100%' : 440,
        maxWidth: '100%', boxSizing: 'border-box', maxHeight: '90vh', overflowY: 'auto',
        borderRadius: sheet ? 'var(--bs-r-lg) var(--bs-r-lg) 0 0' : 'var(--bs-r-lg)',
        boxShadow: 'var(--bs-sh-pop)', padding: sheet ? '10px 18px 26px' : '24px',
        display: 'flex', flexDirection: 'column', gap: 16,
      }}>
        {sheet && <div style={{ width: 36, height: 4, borderRadius: 2, background: 'var(--bs-line)', alignSelf: 'center' }} />}

        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <h3 style={{ fontSize: 18, margin: 0, flex: 1 }}>Виберіть кредитну пропозицію</h3>
          <button onClick={onClose} disabled={adding} aria-label="Закрити" style={{
            background: 'var(--bs-bg)', border: 0, width: 30, height: 30, borderRadius: '50%',
            display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: adding ? 'default' : 'pointer',
            color: 'var(--bs-ink-2)', opacity: adding ? .5 : 1,
          }}><I.Close width="13" height="13" /></button>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          <BankModalCard bank="monobank" selected={selectedBank === 'monobank'} onSelect={setSelectedBank}
            price={price} paymentsCount={paymentsCount} setPaymentsCount={setPaymentsCount} />
          <BankModalCard bank="pumb" selected={selectedBank === 'pumb'} onSelect={setSelectedBank}
            price={price} paymentsCount={paymentsCount} setPaymentsCount={setPaymentsCount} />
        </div>

        <div style={{ display: 'flex', flexDirection: sheet ? 'column' : 'row', gap: 10 }}>
          <button className="bs-btn" onClick={handlePrimary} disabled={!!adding} style={{
            flex: 1, padding: '13px 16px', fontSize: 14.5, fontWeight: 700,
            border: '1.5px solid #111', background: '#111', color: '#fff',
            opacity: adding === 'checkout' ? .75 : adding ? .5 : 1, cursor: adding ? 'default' : 'pointer',
          }}>
            {adding === 'checkout' && <span className="pay001-spin" />}
            {adding === 'checkout' ? 'Додаємо…' : 'Купити частинами'}
          </button>
          <button className="bs-btn" onClick={handleSecondary} disabled={!!adding} style={{
            flex: 1, padding: '13px 16px', fontSize: 14.5, fontWeight: 700,
            border: '1.5px solid var(--bs-line)', background: '#fff', color: 'var(--bs-ink-2)',
            opacity: adding === 'continue' ? .75 : adding ? .5 : 1, cursor: adding ? 'default' : 'pointer',
          }}>
            {adding === 'continue' && <span className="pay001-spin pay001-spin-dark" />}
            {adding === 'continue' ? 'Додаємо…' : 'Продовжити покупки'}
          </button>
        </div>

        <p style={{ fontSize: 11.5, color: 'var(--bs-ink-3)', margin: 0, lineHeight: 1.5 }}>
          Без комісії для вас — умови кредитування визначає банк.
        </p>
      </div>
    </div>
  );
}

Object.assign(window, {
  CREDIT_MIN_AMOUNT, CREDIT_MAX_PAYMENTS, CREDIT_PAYMENT_OPTIONS, creditMonthly, paymentsWord, paymentsRemaining, PUMB_LOGO,
  MonoPaw, MonoPawOutline, MonoMark, MonoStickerBadge, PumbMark, PumbStickerBadge, SoonTag, PaymentsPicker, InfoStat,
  CreditTeaser, InstallmentsCTA, CreditModal,
});
