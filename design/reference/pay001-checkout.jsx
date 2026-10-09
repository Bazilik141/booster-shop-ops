// PAY-001-UI — чекаут: пункт оплати «Оплатити частинами» у списку способів
// оплати (стан c), екран очікування підтвердження в застосунку (d), і
// результат — успіх/відмова людською мовою (e). Візуальна мова RadioRow/
// CoCard скопійована з rd13-checkout.jsx (той самий паттерн, окремий файл —
// Babel-скрипти не діляться скоупом і ця подача незалежна від RD-13).
//
// Payment-method value is the bank-agnostic "installments" (not "monobank") —
// WHICH bank fulfills it is a separate axis (`selectedBank`, 'monobank'|'pumb'),
// same as on the product-page modal. Логіка ~90% спільна (той самий
// creditMonthly/поріг), різниця — момент списання 1-го платежу: monobank
// сьогодні, ПУМБ лише за місяць (paymentsRemaining() рахує це один раз, у
// pay001-credit.jsx).
//
// PAY-001-UI2 Q4: «price» тут — вже сума ПІСЛЯ купона/знижки (payable), не
// сирий прайс товару — рахувати поріг 500 ₴ від неї, не від items subtotal.

const ErrorDotIcon = () => (
  <svg width="12" height="12" viewBox="0 0 12 12" fill="none" style={{ flex: '0 0 auto' }}>
    <circle cx="6" cy="6" r="5" stroke="currentColor" strokeWidth="1.3" />
    <path d="M6 3.3v3.2M6 8.4v.1" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" />
  </svg>
);
const PhoneIcon = (p) => (
  <svg {...p} viewBox="0 0 16 22" fill="none">
    <rect x="1" y="1" width="14" height="20" rx="2.5" stroke="currentColor" strokeWidth="1.5" />
    <path d="M6 17h4" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
  </svg>
);
const WarnIcon = (p) => (
  <svg {...p} viewBox="0 0 22 22" fill="none">
    <circle cx="11" cy="11" r="9" stroke="currentColor" strokeWidth="1.6" />
    <path d="M11 6.5v6M11 15.5v.1" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
  </svg>
);

function CreditRadioRow({ name, value, current, onSelect, label, sublabel, trailing, squareBottom }) {
  const isActive = value === current;
  return (
    <label style={{
      display: 'flex', alignItems: 'center', gap: 12, padding: '13px 15px',
      border: `1.5px solid ${isActive ? 'var(--bs-blue)' : 'var(--bs-line)'}`,
      borderBottom: squareBottom ? 'none' : undefined,
      background: isActive ? 'var(--bs-blue-soft)' : '#fff',
      borderRadius: squareBottom ? 'var(--bs-r-sm) var(--bs-r-sm) 0 0' : 'var(--bs-r-sm)',
      cursor: 'pointer', transition: 'background .15s, border-color .15s',
    }}>
      <input type="radio" name={name} value={value} checked={isActive} onChange={() => onSelect(value)}
        style={{ accentColor: 'var(--bs-blue)', flex: '0 0 auto' }} />
      <div style={{ flex: 1 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
          <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--bs-ink)' }}>{label}</span>
          {trailing}
        </div>
        {sublabel && <div style={{ fontSize: 12, color: 'var(--bs-ink-3)', marginTop: 2 }}>{sublabel}</div>}
      </div>
    </label>
  );
}

function PaymentSectionCredit({ value, onSelect, price, paymentsCount, setPaymentsCount, selectedBank, setSelectedBank, hasPreorderItem }) {
  const open = value === 'installments';
  return (
    <>
      <CreditRadioRow name="pay" value="card" current={value} onSelect={onSelect}
        label="Картка, Google Pay / Apple Pay" sublabel="Безпечно через еквайринг" />

      <div>
        {/* QA round 2: dropped the "Розстрочка від підключених банків" sublabel — owner call. */}
        <CreditRadioRow name="pay" value="installments" current={value} onSelect={onSelect}
          label="Оплатити частинами" squareBottom={open} />
        {open && (
          <InstallmentBankList price={price} paymentsCount={paymentsCount} setPaymentsCount={setPaymentsCount}
            selectedBank={selectedBank} setSelectedBank={setSelectedBank} hasPreorderItem={hasPreorderItem} />
        )}
      </div>

      <CreditRadioRow name="pay" value="cod" current={value} onSelect={onSelect}
        label="Оплата при отриманні (накладений платіж)" />
      <CreditRadioRow name="pay" value="iban" current={value} onSelect={onSelect}
        label="За реквізитами на IBAN" />
    </>
  );
}

// One bank's own bordered card inside the drawer — a nested radio choice
// (same idiom as the outer payment-method list: collapsed header row, expands
// only when selected). Both banks share price/paymentsCount/threshold; they
// differ only in accent color and the "Без першого платежу" ПУМБ note.
function BankRadioCard({ bank, selected, onSelect, price, paymentsCount, setPaymentsCount, belowThreshold }) {
  const isMono = bank === 'monobank';
  const accent = isMono ? '#111' : 'var(--bs-pumb-red)';
  const monthly = creditMonthly(price, paymentsCount);
  const left = paymentsRemaining(bank, paymentsCount);
  return (
    <div style={{ border: `1.5px solid ${selected ? accent : 'var(--bs-line)'}`, borderRadius: 'var(--bs-r-sm)', background: '#fff', overflow: 'hidden', transition: 'border-color .15s' }}>
      <label style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '12px 14px', cursor: 'pointer' }} onClick={() => onSelect(bank)}>
        <input type="radio" name="pay001-co-bank" checked={selected} onChange={() => onSelect(bank)} style={{ accentColor: accent, flex: '0 0 auto' }} />
        {isMono ? <MonoStickerBadge height={30} /> : <PumbStickerBadge height={30} />}
        <span style={{ flex: 1, fontSize: 12.5, color: 'var(--bs-ink-3)' }}>{isMono ? 'Покупка частинами' : 'Без першого платежу'}</span>
      </label>

      {selected && (
        <div style={{ padding: '0 14px 14px', display: 'flex', flexDirection: 'column', gap: 12 }}>
          <PaymentsPicker value={paymentsCount} onChange={setPaymentsCount} disabled={belowThreshold} accent={accent} />

          {belowThreshold ? (
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: 8, padding: '10px 12px', background: 'var(--bs-warning-bg)', border: '1px solid var(--bs-warning-line)', borderRadius: 'var(--bs-r-sm)' }}>
              <WarnIcon width="16" height="16" style={{ color: 'var(--bs-warning-fg)', flex: '0 0 auto', marginTop: 1 }} />
              <span style={{ fontSize: 12.5, color: 'var(--bs-warning-fg)', lineHeight: 1.5 }}>
                Сума замовлення нижче ₴{CREDIT_MIN_AMOUNT} — оберіть інший спосіб оплати або додайте товар у кошик.
              </span>
            </div>
          ) : (
            <>
              <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap', paddingTop: 10, borderTop: '1px solid var(--bs-line-2)' }}>
                <InfoStat label="Сума в кредит" value={`₴${price}`} />
                <InfoStat label="Щомісячний платіж" value={`₴${monthly}`} />
                <InfoStat label="Платежів до завершення" value={left} />
              </div>
              <div style={{ fontSize: 11.5, color: 'var(--bs-ink-3)' }}>
                Кредит буде оформлено на номер телефону: <strong style={{ color: 'var(--bs-ink-2)' }}>+38 099 111 22 33</strong>
              </div>
            </>
          )}
        </div>
      )}
    </div>
  );
}

// Selecting "Оплатити частинами" reveals a drawer with each connected bank as
// its OWN bordered card (not nested inside one another) — both live now, a
// nested radio choice between them. The drawer's top border is the row's own
// missing bottom edge (squareBottom above), so the frame reads as one
// continuous piece.
//
// PAY-001-UI2 Q4: if `price` (payable, post-coupon) is below the 500 ₴ floor,
// the numeric breakdown is replaced by a soft warning and the 3/4/5 picker
// disables for whichever bank is selected — the drawer stays open (so the
// reason is visible) but nothing in it can be chosen until the amount recovers.
//
// QA round 2 (2026-07-24): new higher-priority gate — `hasPreorderItem` (any
// cart line with zero real stock) blocks the whole method regardless of
// amount, so it's checked FIRST and replaces the bank cards entirely with one
// notice (no point choosing a bank when the method itself is blocked). Mirrors
// the product-page precedence rule (preorder beats the amount threshold).
function InstallmentBankList({ price, paymentsCount, setPaymentsCount, selectedBank, setSelectedBank, hasPreorderItem }) {
  const belowThreshold = price < CREDIT_MIN_AMOUNT;
  return (
    <div style={{
      padding: 12, background: 'var(--bs-blue-soft)',
      border: '1.5px solid var(--bs-blue)', borderTop: '1px solid var(--bs-blue)',
      borderRadius: '0 0 var(--bs-r-sm) var(--bs-r-sm)',
      display: 'flex', flexDirection: 'column', gap: 10,
    }}>
      {hasPreorderItem ? (
        <div style={{ display: 'flex', alignItems: 'flex-start', gap: 8, padding: '10px 12px', background: 'var(--bs-warning-bg)', border: '1px solid var(--bs-warning-line)', borderRadius: 'var(--bs-r-sm)' }}>
          <WarnIcon width="16" height="16" style={{ color: 'var(--bs-warning-fg)', flex: '0 0 auto', marginTop: 1 }} />
          <span style={{ fontSize: 12.5, color: 'var(--bs-warning-fg)', lineHeight: 1.5 }}>
            Оплата частинами доступна лише для товарів у наявності — вилучіть товар на передзамовленні з кошика, щоб скористатися розстрочкою.
          </span>
        </div>
      ) : (
        <>
          <BankRadioCard bank="monobank" selected={selectedBank === 'monobank'} onSelect={setSelectedBank}
            price={price} paymentsCount={paymentsCount} setPaymentsCount={setPaymentsCount} belowThreshold={belowThreshold} />
          <BankRadioCard bank="pumb" selected={selectedBank === 'pumb'} onSelect={setSelectedBank}
            price={price} paymentsCount={paymentsCount} setPaymentsCount={setPaymentsCount} belowThreshold={belowThreshold} />
        </>
      )}
    </div>
  );
}

function CreditCoCard({ icon, title, children }) {
  return (
    <section className="bs-card" style={{ padding: 22 }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 18 }}>
        <span style={{
          width: 30, height: 30, borderRadius: 'var(--bs-r-sm)', background: 'var(--bs-bg)', color: 'var(--bs-ink-2)',
          display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 auto',
        }}>{icon}</span>
        <h3 style={{ fontSize: 16, margin: 0, flex: 1 }}>{title}</h3>
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>{children}</div>
    </section>
  );
}

function MobileInfoRow({ icon, title, value }) {
  return (
    <section className="bs-card" style={{ padding: '13px 16px', display: 'flex', alignItems: 'center', gap: 12 }}>
      <span style={{
        width: 28, height: 28, borderRadius: 'var(--bs-r-sm)', background: 'var(--bs-bg)', color: 'var(--bs-ink-2)',
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 auto',
      }}>{icon}</span>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 13, fontWeight: 700, color: 'var(--bs-ink)' }}>{title}</div>
        <div style={{ fontSize: 12, color: 'var(--bs-ink-3)', marginTop: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{value}</div>
      </div>
    </section>
  );
}

// Prototype-only control cluster — dashed shell so it never reads as production UI.
function PrototypeControls({ children }) {
  return (
    <div style={{
      padding: '10px 14px', border: '1px dashed var(--bs-ink-4)', borderRadius: 'var(--bs-r-sm)',
      display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap', justifyContent: 'center',
    }}>
      <span style={{
        fontFamily: '"JetBrains Mono", monospace', fontSize: 9.5, letterSpacing: '.07em',
        textTransform: 'uppercase', color: 'var(--bs-ink-4)', marginRight: 4,
      }}>Демо, не в макеті:</span>
      {children}
    </div>
  );
}

// PAY-001-UI2 Q4: `price` (payable, post-coupon) below 500 ₴ while "Оплатити
// частинами" is selected blocks submit — grey button + compact inline warning
// right above it (visible without scrolling back up to the payment section).
function CreditOrderTail({ payment, price, onSubmit, hasPreorderItem }) {
  const [agree, setAgree] = React.useState(false);
  const [showErr, setShowErr] = React.useState(false);
  const isInstallments = payment === 'installments';
  // Preorder gate outranks the amount gate — see InstallmentBankList above.
  const blockedPreorder = isInstallments && hasPreorderItem;
  const blockedAmount = isInstallments && !hasPreorderItem && price < CREDIT_MIN_AMOUNT;
  const blocked = blockedPreorder || blockedAmount;
  const dimmed = !agree || blocked;
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      <label style={{ display: 'flex', gap: 10, alignItems: 'flex-start', cursor: 'pointer', fontSize: 12, color: 'var(--bs-ink-2)', lineHeight: 1.55 }}>
        <input type="checkbox" checked={agree} onChange={(e) => { setAgree(e.target.checked); setShowErr(false); }}
          style={{ marginTop: 2, accentColor: 'var(--bs-blue)', flex: '0 0 auto' }} />
        <span>Погоджуюсь з умовами <a href="#" onClick={(e) => e.preventDefault()} style={{ color: 'var(--bs-blue)' }}>Публічної оферти</a>, включно з положеннями про обробку персональних даних.</span>
      </label>
      {showErr && (
        <div style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 12, color: 'var(--bs-danger)', fontWeight: 500 }}>
          <ErrorDotIcon /> Погодьтесь з умовами, щоб продовжити
        </div>
      )}
      {payment !== 'installments' && (
        <div style={{ fontSize: 11.5, color: 'var(--bs-ink-4)', lineHeight: 1.5 }}>
          У цьому прототипі змодельована лише гілка «Оплатити частинами» — оберіть її вище, щоб побачити стани очікування/результату.
        </div>
      )}
      {blocked && (
        <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: 'var(--bs-warning-fg)', fontWeight: 500 }}>
          <WarnIcon width="13" height="13" />
          {blockedPreorder
            ? 'У замовленні є товар на передзамовленні — оплата частинами недоступна, доки він у кошику.'
            : `Сума нижче ₴${CREDIT_MIN_AMOUNT} для оплати частинами — змініть спосіб оплати або додайте товар.`}
        </div>
      )}
      <button className="bs-btn bs-btn-primary" style={{ padding: '15px', fontSize: 15, opacity: dimmed ? 0.5 : 1, cursor: dimmed ? 'not-allowed' : 'pointer' }}
        onClick={() => { if (blocked) return; if (!agree) { setShowErr(true); return; } onSubmit(payment); }}>
        Підтвердити замовлення →
      </button>
    </div>
  );
}

const BANK_LABEL = { monobank: 'monobank', pumb: 'ПУМБ' };

function WaitingScreen({ bank = 'monobank', onDemoSuccess, onDemoDecline }) {
  const isMono = bank === 'monobank';
  return (
    <section className="bs-card" style={{ padding: '44px 36px', textAlign: 'center', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 18 }}>
      <div style={{ width: 72, height: 72, borderRadius: '50%', background: 'var(--bs-line-2)', color: 'var(--bs-ink)', display: 'flex', alignItems: 'center', justifyContent: 'center', position: 'relative' }}>
        <span className="pay001-pulse" style={!isMono ? { borderColor: 'var(--bs-pumb-red)' } : undefined} />
        <PhoneIcon width="28" height="28" />
      </div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        {isMono ? (<><MonoPaw size={18} /> <MonoMark size={13} /></>) : (<><img src={PUMB_LOGO} alt="" style={{ width: 18, height: 18, borderRadius: 5 }} /> <PumbMark size={13} /></>)}
      </div>
      <div>
        <h2 style={{ fontSize: 20, margin: '0 0 10px', color: 'var(--bs-ink)' }}>Підтвердіть покупку в застосунку {BANK_LABEL[bank]}</h2>
        <p style={{ fontSize: 14.5, color: 'var(--bs-ink-2)', lineHeight: 1.6, maxWidth: 440, margin: '0 auto' }}>
          Ми надіслали запит у застосунок {BANK_LABEL[bank]} на вашому телефоні. Відкрийте push-сповіщення
          та підтвердьте покупку частинами — зазвичай це займає кілька хвилин, іноді до ~15.
        </p>
      </div>
      <div style={{ fontSize: 13, color: 'var(--bs-ink-3)' }}>Не закривайте цю сторінку — вона оновиться сама.</div>

      <PrototypeControls>
        <button className="bs-btn bs-btn-secondary bs-btn-sm" onClick={onDemoSuccess}>Підтверджено</button>
        <button className="bs-btn bs-btn-secondary bs-btn-sm" onClick={onDemoDecline}>Відмовлено</button>
      </PrototypeControls>
    </section>
  );
}

// Decline copy references "застосунок {bank}" — the reason text is otherwise
// bank-agnostic (funds/rejection/timeout read fine for either bank).
function declineCopy(bank, reason) {
  const b = BANK_LABEL[bank] || BANK_LABEL.monobank;
  const table = {
    not_found: { title: 'Не вдалося підтвердити покупку', body: `У застосунку ${b} не знайшли ваш профіль за номером телефону з замовлення. Перевірте номер телефону або оберіть інший спосіб оплати.` },
    insufficient_funds: { title: 'Недостатньо коштів для розстрочки', body: `${b} не може підтвердити цю покупку частинами зараз. Спробуйте ще раз пізніше або оберіть інший спосіб оплати.` },
    rejected: { title: 'Підтвердження відхилено', body: `Покупку частинами скасовано в застосунку ${b}. Замовлення не оформлено — спробуйте ще раз або оберіть інший спосіб оплати.` },
    timeout: { title: 'Час очікування вичерпано', body: `Ви не встигли підтвердити покупку в застосунку ${b}. Спробуйте ще раз або оберіть інший спосіб оплати.` },
  };
  return table[reason] || table.not_found;
}
// Kept for compatibility with older call sites expecting a flat lookup.
const DECLINE_COPY = { not_found: declineCopy('monobank', 'not_found'), insufficient_funds: declineCopy('monobank', 'insufficient_funds'), rejected: declineCopy('monobank', 'rejected'), timeout: declineCopy('monobank', 'timeout') };

// Success copy differs by bank in one factual way: monobank charged the 1st
// installment today; ПУМБ hasn't charged anything yet (1st payment in a month).
function successCopy(bank) {
  return bank === 'pumb'
    ? 'Оплату частинами ПУМБ підтверджено. Перший платіж спишеться через місяць, наступні — щомісяця автоматично.'
    : 'Оплату частинами monobank підтверджено. Перший платіж списано зараз, наступні — щомісяця автоматично.';
}

function ResultScreen({ outcome, reason, bank = 'monobank', orderId, onRetry, onSwitchMethod }) {
  const ok = outcome === 'success';
  const d = !ok ? declineCopy(bank, reason) : null;
  return (
    <section style={{ display: 'grid', gridTemplateColumns: '4px 1fr', overflow: 'hidden', background: '#fff', border: '1px solid var(--bs-line)', borderRadius: 'var(--bs-r)' }}>
      <div style={{ background: ok ? 'var(--bs-green)' : 'var(--bs-danger)' }} />
      <div style={{ padding: '32px 30px', display: 'flex', alignItems: 'flex-start', gap: 22, minWidth: 0 }}>
        <div style={{
          width: 60, height: 60, borderRadius: '50%', flex: '0 0 auto',
          background: ok ? '#DCFCE7' : '#FDECEC', color: ok ? 'var(--bs-green)' : 'var(--bs-danger)',
          display: 'flex', alignItems: 'center', justifyContent: 'center',
        }}>{ok ? <I.Check width="26" height="26" /> : <WarnIcon width="24" height="24" />}</div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontFamily: '"JetBrains Mono", monospace', fontSize: 11, letterSpacing: '.08em', textTransform: 'uppercase', color: 'var(--bs-ink-3)', marginBottom: 8 }}>
            Замовлення №{orderId}
          </div>
          <h2 style={{ fontSize: 24, fontWeight: 800, margin: '0 0 10px', color: 'var(--bs-ink)' }}>{ok ? 'Дякуємо, замовлення в грі.' : d.title}</h2>
          <p style={{ fontSize: 14.5, color: 'var(--bs-ink-2)', lineHeight: 1.6, margin: 0, maxWidth: 460 }}>
            {ok ? successCopy(bank) : d.body}
          </p>
          <div style={{ display: 'flex', gap: 10, marginTop: 20, flexWrap: 'wrap' }}>
            {ok ? (
              <a className="bs-btn bs-btn-primary" href="#" onClick={(e) => e.preventDefault()}>Продовжити покупки →</a>
            ) : (
              <>
                <button className="bs-btn bs-btn-primary" onClick={onRetry}>Спробувати ще раз</button>
                <button className="bs-btn bs-btn-secondary" onClick={onSwitchMethod}>Обрати інший спосіб оплати</button>
              </>
            )}
          </div>
        </div>
      </div>
    </section>
  );
}

Object.assign(window, {
  PaymentSectionCredit, CreditCoCard, CreditOrderTail, MobileInfoRow,
  WaitingScreen, ResultScreen, PrototypeControls, DECLINE_COPY, declineCopy, successCopy, BANK_LABEL,
});
