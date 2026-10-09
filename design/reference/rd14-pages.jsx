const RP_I = {
  check: <path d="M20 6 9 17l-5-5" />,
  cards: <><rect x="8" y="3" width="12" height="16" rx="2" /><path d="M5 7v12a2 2 0 0 0 2 2h9" /></>,
  smile: <><circle cx="12" cy="12" r="9" /><path d="M8.5 14s1.3 2 3.5 2 3.5-2 3.5-2" /><path d="M9 9.5h.01M15 9.5h.01" /></>,
  gift: <><rect x="3" y="8" width="18" height="4" rx="1" /><path d="M12 8v13M19 12v9H5v-9" /><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5" /></>,
  alert: <><circle cx="12" cy="12" r="9" /><path d="M12 7.5v5.5M12 16.5h.01" /></>,
  copy: <><rect x="9" y="9" width="12" height="12" rx="2" /><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1" /></>,
  receipt: <><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2Z" /><path d="M9 8h6M9 12h6" /></>,
  phone: <><path d="M4 5c0 8 7 15 15 15l1-4-4-2-2 2c-2-1-4-3-5-5l2-2-2-4Z" /><path d="m3 3 18 18" /></>,
  bank: <><path d="M3 10h18L12 4Z" /><path d="M5 10v8M10 10v8M14 10v8M19 10v8M3 20h18" /></>,
  tag: <><path d="M3 12V4h8l10 10-8 8Z" /><path d="M7.5 7.5h.01" /></>
};
function Ic({ n, cls }) {
  return <svg className={'rp-ic ' + (cls || '')} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{RP_I[n]}</svg>;
}
function TgIc() {
  return <svg className="rp-ic rp-ic-tg" viewBox="0 0 24 24" aria-hidden="true"><path fill="#229ED9" d="M21.2 4.3 2.9 11.4c-1 .4-1 1.7 0 2l4.5 1.5 1.7 5.3c.2.7 1.1.9 1.6.4l2.6-2.4 4.7 3.4c.6.4 1.4.1 1.6-.6l3-14.6c.2-1-.6-1.5-1.4-1.1Zm-3.4 3.5-7.9 7.1-.3 3-1.2-3.9 9-6.4c.3-.2.6.1.4.2Z" /></svg>;
}

const RP_TG = 'https://telegram.me/boostershop_tcg';
const RP_ORDERS = {
  a: { id: 10482, logged: false, first15: false, pay: 'cod', ship: 'Нова Пошта — у відділення', shipText: 'Київ, Відділення №27', payMethod: 'Оплата при отриманні (накладений платіж)',
    items: [[1, 'Pokémon TCG: Scarlet & Violet — Prismatic Evolutions Elite Trainer Box', '₴3450.00'], [2, 'One Piece Card Game OP-09 Booster Pack', '₴420.00']],
    totals: [['Сума', '₴3870.00'], ['Нова Пошта — у відділення', '₴0.00'], ['Всього', '₴3870.00']] },
  b: { id: 10483, logged: true, first15: true, pay: 'hutko', ship: 'Нова Пошта — кур’єр', shipText: 'Львів, вул. Городоцька, 15', payMethod: 'Оплата карткою онлайн (Hutko)',
    items: [[1, 'Pokémon TCG: Surging Sparks Booster Display (36 бустерів)', '₴6900.00']],
    totals: [['Сума', '₴6900.00'], ['Нова Пошта — кур’єр', '₴0.00'], ['Всього', '₴6900.00']] },
  c: { id: 10484, logged: false, first15: false, pay: 'iban', ship: 'Нова Пошта — у поштомат', shipText: 'Одеса, Поштомат №4410', payMethod: 'Оплата на рахунок (IBAN)',
    items: [[1, 'Disney Lorcana: Azurite Sea Booster Box', '₴5600.00'], [3, 'Ultra Pro Deck Protector Sleeves — Matte Black (100)', '₴1050.00']],
    totals: [['Сума', '₴6650.00'], ['Нова Пошта — у поштомат', '₴0.00'], ['Всього', '₴6650.00']] },
  d: { id: 10485, logged: true, first15: false, pay: 'cod', ship: 'Нова Пошта — у відділення', shipText: 'Харків, Відділення №112 (до 30 кг на одне місце)', payMethod: 'Оплата при отриманні (накладений платіж)',
    items: [
      [1, 'Pokémon TCG: Scarlet & Violet — Prismatic Evolutions Super-Premium Collection (англійською мовою, запечатаний)', '₴4990.00'],
      [2, 'Pokémon TCG: Scarlet & Violet 151 Ultra-Premium Collection — Mew (англійською мовою)', '₴11800.00'],
      [1, 'One Piece Card Game OP-10 Royal Blood Booster Box (24 бустери, японською мовою)', '₴3200.00'],
      [4, 'Magic: The Gathering — Duskmourn: House of Horror Play Booster (англійською мовою)', '₴1040.00'],
      [1, 'Ultra Pro Premium PRO-Binder 12-Pocket Zippered — Pokémon Charizard, Pikachu, Eevee', '₴1450.00'],
      [2, 'Dragon Shield Matte Sleeves Standard Size 100 шт. — Jet Black / Crimson / Petrol', '₴980.00']],
    totals: [['Сума', '₴23460.00'], ['Нова Пошта — у відділення', '₴0.00'], ['Всього', '₴23460.00']] }
};

function Hero({ o, d }) {
  return (
    <section className="rp-hero">
      <div className="rp-hero-ic"><Ic n="check" /></div>
      {o ? <>
        <h1>Замовлення #{o.id} прийнято</h1>
        <p className="rp-sub">Замовлення в грі — ми вже збираємо ваш лут <Ic n="cards" cls="rp-ic-inl" /></p>
      </> : <h1>Ваше замовлення прийнято!</h1>}
    </section>
  );
}
function First15() {
  return (
    <section className="rp-card rp-f15" role="status">
      <span className="rp-f15-ic"><Ic n="tag" /></span>
      <div><strong>Дякуємо за реєстрацію!</strong><p>На ваше наступне замовлення ми автоматично застосуємо знижку 15%.</p></div>
    </section>
  );
}
function MetaRows({ o }) {
  return (
    <div className="rp-meta">
      <div className="rp-meta-row"><span className="rp-lbl">Доставка</span><span>{o.ship} · {o.shipText}</span></div>
      <div className="rp-meta-row"><span className="rp-lbl">Оплата</span><span>{o.payMethod}</span></div>
    </div>
  );
}
const RP_REQ = [['Отримувач', 'ФОП Леусенко Євгеній Андрійович'], ['ЄДРПОУ', '3485903435'], ['IBAN', 'UA063348510000000026003285008'], ['МФО', '334851'], ['Банк', 'АТ «ПУМБ»'], ['Призначення платежу', 'оплата за товар']];
function Iban({ status, onCopy, bare }) {
  return (
    <section className={bare ? 'rp-iban rp-iban-bare' : 'rp-card rp-iban'} aria-labelledby="rp-iban-t">
      {!bare && <h2 id="rp-iban-t" className="rp-h2"><Ic n="bank" /> Реквізити для оплати</h2>}
      <dl className="rp-req">{RP_REQ.map(([k, v]) => <div key={k} className={k === 'IBAN' ? 'rp-req-row rp-req-iban' : 'rp-req-row'}><dt>{k}</dt><dd>{v}</dd></div>)}</dl>
      <div className="rp-iban-act">
        <button type="button" className="rp-btn rp-btn-sec" data-checkout008-copy-requisites="" onClick={onCopy}><Ic n="copy" /> Скопіювати реквізити</button>
        <span className="rp-copy-st" data-checkout008-copy-status="" role="status" aria-live="polite" hidden={!status}><Ic n="check" /> {status}</span>
      </div>
    </section>
  );
}
function Items({ o, withMeta, title }) {
  return (
    <section className="rp-card rp-items">
      <h2 className="rp-h2">{title || 'Ваше замовлення'}</h2>
      {withMeta && <MetaRows o={o} />}
      <table className="rp-table">
        <tbody>{o.items.map((it, i) => <tr key={i}><td className="rp-iname"><span className="rp-qty">{it[0]}×</span> {it[1]}</td><td className="rp-iprice">{it[2]}</td></tr>)}</tbody>
        <tfoot>{o.totals.map((t, i) => <tr key={i} className={i === o.totals.length - 1 ? 'rp-grand' : ''}><td>{t[0]}</td><td className="rp-iprice">{t[1]}</td></tr>)}</tfoot>
      </table>
    </section>
  );
}
function Actions({ o }) {
  return (
    <nav className="rp-actions" aria-label="Дії після замовлення">
      <a href="#" className="rp-btn rp-btn-sec" onClick={e => e.preventDefault()}>На головну</a>
      {o.logged && <a href="#" className="rp-btn rp-btn-sec" onClick={e => e.preventDefault()}>Переглянути замовлення</a>}
    </nav>
  );
}
function fiscal(o) {
  if (o.pay === 'hutko') return 'Фіскальний чек відправлено на ваш номер або на E-mail.';
  if (o.pay === 'cod') return 'Фіскальний чек буде відправлено на ваш номер або на E-mail в день отримання замовлення.';
  return 'Фіскальний чек буде відправлено на ваш номер або на E-mail при відправці замовлення.';
}
const RP_NOCALL = 'Ми не телефонуємо і не пишемо для підтвердження без потреби. Якщо всі дані заповнені коректно — просто тихо і швидко відправляємо';
function TgLink({ btn }) {
  return <a href={RP_TG} target="_blank" rel="noopener" className={btn ? 'rp-btn rp-btn-sec rp-tg-btn' : 'rp-tg-link'}><TgIc /> напишіть у Telegram</a>;
}
function FooterMsg({ o, card }) {
  return (
    <section className={card ? 'rp-card rp-foot rp-foot-card' : 'rp-foot'}>
      {card && <h2 className="rp-h2">Що далі</h2>}
      <p>{fiscal(o)}</p>
      <p>{RP_NOCALL} <Ic n="smile" cls="rp-ic-inl" /></p>
      <p>Якщо є питання — <TgLink />.</p>
      <p className="rp-bye"><strong>Вдалого анпакінгу <Ic n="gift" cls="rp-ic-inl" /></strong></p>
    </section>
  );
}
function Steps({ o, iban }) {
  const s = [];
  if (o.pay === 'iban') s.push(['bank', 'Оплата за реквізитами', null, iban]);
  s.push(['receipt', 'Фіскальний чек', fiscal(o)]);
  s.push(['phone', 'Без зайвих дзвінків', <>{RP_NOCALL} <Ic n="smile" cls="rp-ic-inl" /></>]);
  return (
    <section className="rp-card rp-steps">
      <h2 className="rp-h2">Що далі <span className="rp-prop">нові підзаголовки — пропозиція</span></h2>
      <ol>{s.map((x, i) => <li key={i}><span className="rp-step-n"><Ic n={x[0]} /></span><div className="rp-step-b"><strong>{x[1]}</strong>{x[2] && <p>{x[2]}</p>}{x[3]}</div></li>)}</ol>
    </section>
  );
}
function Fallback() {
  return (
    <section className="rp-card rp-fallback">
      <div className="rp-msg"><p>Ваше замовлення успішно оформлене!</p><p>Якщо у вас виникли питання, будь ласка, <a href="#">зв’яжіться з нами</a>.</p><p>Дякуємо за покупку!</p></div>
      <div className="rp-actions"><a href="#" className="rp-btn rp-btn-sec">На головну</a></div>
    </section>
  );
}

function SuccessPage({ d, st, copyStatus, onCopy }) {
  if (st === 'e') return <div className={'rp-w rp-w-' + d}><Hero d={d} /><Fallback /><p className="rp-approx">text_message і heading_title — приблизний текст мовного файлу</p></div>;
  const o = RP_ORDERS[st];
  const iban = o.pay === 'iban';
  if (d === 1) return (
    <div className="rp-w rp-w-1">
      <Hero o={o} />
      {o.first15 && <First15 />}
      {iban && <Iban status={copyStatus} onCopy={onCopy} />}
      <Items o={o} withMeta />
      <Actions o={o} />
      <FooterMsg o={o} />
    </div>
  );
  if (d === 2) return (
    <div className="rp-w rp-w-2">
      <Hero o={o} />
      <div className="rp-split">
        <div className="rp-col-main">
          {o.first15 && <First15 />}
          {iban && <Iban status={copyStatus} onCopy={onCopy} />}
          <FooterMsg o={o} card />
          <Actions o={o} />
        </div>
        <aside className="rp-col-side"><Items o={o} withMeta /></aside>
      </div>
    </div>
  );
  return (
    <div className="rp-w rp-w-3">
      <Hero o={o} />
      {o.first15 && <First15 />}
      <Steps o={o} iban={iban && <Iban bare status={copyStatus} onCopy={onCopy} />} />
      <Items o={o} withMeta />
      <Actions o={o} />
      <section className="rp-foot rp-foot-3">
        <p className="rp-bye"><strong>Вдалого анпакінгу <Ic n="gift" cls="rp-ic-inl" /></strong></p>
        <p>Якщо є питання — <TgLink />.</p>
      </section>
    </div>
  );
}

function FailurePage({ d, proposed }) {
  return (
    <div className={'rp-w rp-w-f rp-wf-' + d}>
      <section className="rp-card rp-fail">
        <div className="rp-fail-ic"><Ic n="alert" /></div>
        <div className="rp-fail-b">
          <h1>{proposed ? 'Оплата не пройшла' : 'Помилка оплати!'}</h1>
          {proposed ? (
            <div className="rp-msg">
              <p>Платіж не було завершено. Так буває, якщо банк відхилив операцію, на картці не вистачило коштів або сторінку оплати закрили до кінця.</p>
              <p>Кошик після переходу до оплати очищується, тож щоб оформити замовлення знову, додайте товари ще раз.</p>
              <p>Якщо кошти списались або ви не впевнені, чи створено замовлення, напишіть нам у Telegram або через <a href="#">форму зв’язку</a>.</p>
            </div>
          ) : (
            <div className="rp-msg">
              <p>У процесі оплати виникла помилка. Через помилку не вдалося завершити оформлення замовлення</p>
              <p>Можливі причини:</p>
              <ul><li>Недостатньо коштів</li><li>Збій перевірки</li></ul>
              <p>Будь ласка, повторіть спробу, вибравши інший спосіб оплати.</p>
              <p>Якщо помилка повторилася, будь ласка <a href="#">зв'яжіться з нами</a> та повідомте подробиці замовлення.</p>
            </div>
          )}
          <div className="rp-actions rp-fail-act">
            <a href="#" className="rp-btn rp-btn-sec">На головну</a>
            <TgLink btn />
          </div>
        </div>
      </section>
      
    </div>
  );
}

function ShellHeader() {
  return <div className="rp-shell-h"><span className="rp-sh-b"></span><span className="rp-sh-logo">BOOSTER SHOP</span><span className="rp-sh-s">шапка без змін — пакет B</span><span className="rp-sh-b"></span></div>;
}
function Crumbs({ fail }) {
  return <ul className="rp-crumbs"><li><a href="#" aria-label="Головна"><svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" aria-hidden="true"><path d="M12 3 3 10.5V21h6.5v-6h5v6H21V10.5Z" /></svg></a></li><li><a href="#">Кошик покупок</a></li><li><a href="#">Оформлення замовлення</a></li><li><span className="rp-cr-cur">{fail ? 'Помилка оплати!' : 'Замовлення прийнято'}</span></li></ul>;
}
function Viewport({ w, children, fail }) {
  return (
    <div className="rp-vp" style={{ width: w }}>
      <ShellHeader />
      <div className="rp-container"><Crumbs fail={fail} />{children}</div>
      <div className="rp-shell-f">футер без змін</div>
    </div>
  );
}
Object.assign(window, { SuccessPage, FailurePage, Viewport });
