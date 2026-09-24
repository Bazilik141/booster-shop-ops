// Written spec accompanying the prototype — values the executor needs.
function SpecRow({ k, v }) {
  return <div style={{ display: 'grid', gridTemplateColumns: '180px minmax(0,1fr)', gap: 12, padding: '7px 0', borderBottom: '1px solid var(--bs-line-2)', fontSize: 13, lineHeight: 1.5 }}><span style={{ color: 'var(--bs-ink-3)' }}>{k}</span><span style={{ color: 'var(--bs-ink-2)', minWidth: 0, overflowWrap: 'break-word', wordBreak: 'break-word' }}>{v}</span></div>;
}

function SpecBlock({ title, sub, rows }) {
  return (
    <section className="bs-card" style={{ padding: 18, display: 'grid', gap: 10, alignContent: 'start', minWidth: 0 }}>
      <div>
        <h3 style={{ fontSize: 15, fontWeight: 700, color: 'var(--bs-ink)' }}>{title}</h3>
        {sub && <p style={{ fontSize: 12.5, color: 'var(--bs-ink-3)', marginTop: 4, lineHeight: 1.5 }}>{sub}</p>}
      </div>
      <div>{rows.map((r, i) => <SpecRow key={i} k={r[0]} v={r[1]} />)}</div>
    </section>
  );
}

function SpecNotes() {
  return (
    <div style={{ display: 'grid', gap: 14 }}>
      <h2 style={{ fontSize: 18, fontWeight: 800, color: 'var(--bs-ink)', letterSpacing: '-0.015em', marginTop: 8 }}>Специфікація</h2>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(320px,1fr))', gap: 14 }}>
        <SpecBlock title="RD-11 · Сторінка кошика" sub="checkout/cart.twig + cart_list.twig. Тільки розмітка й CSS — логіку цін і оформлення не чіпати." rows={[
          ['Десктоп', 'Дві колонки: список minmax(0,1fr) + підсумок 340px, gap 24px. Підсумок position:sticky; top:16px'],
          ['Мобайл', 'Одна колонка; картка підсумку під списком + липка панель знизу (сума + «Оформити», h48)'],
          ['Рядок товару', 'Мініатюра 96×96 (моб. 72×72), назва до 2 рядків, ціна за шт., степер, «Видалити». Роздільник 1px --bs-line-2'],
          ['Кількість', 'Степер − / значення / +, кнопки 44×44, рамка --bs-line, радіус --bs-r-sm. Колонку «Модель» прибрати з розмітки, не ховати скриптом'],
          ['Доставка', 'Правило «Безкоштовна доставка Новою поштою від {сума}» показуємо завжди; прогрес-бар — лише коли залишок ≤ 30% від порога, рахуємо при рендері (без нового налаштування в адмінці)'],
          ['Поріг', '{{ shipping_pinta_nova_poshta_free_from }} — змінна з адмінки, у копірайті не хардкодити'],
          ['Фон банера', 'Новий токен --bs-green-soft: #F3FBF6 (поруч із --bs-blue-soft / --bs-gold-soft у boostershop-ds.css) — власник підтвердив 21.09'],
          ['CTA', '«Оформити» — --bs-green, h48, 100% ширини. «Продовжити покупки» — біла з рамкою --bs-blue (замість #1f95d1)'],
          ['Помилка складу', 'Плашка --bs-warning-bg / --bs-warning-line зверху списку; CTA — сірий disabled (--bs-line-2 / --bs-ink-2), не напівпрозорий зелений; текст «Виправте кількість товарів»'],
          ['Порожній кошик', 'Патерн .bs-empty (RD-06): іконка 48px, заголовок, текст, зелена CTA «Перейти до каталогу»'],
          ['Рекомендації', '«Часто беруть разом» — вживається чинний модуль bestseller (extension/opencart/catalog/{model,controller,view/template}/module/bestseller.php|twig) як є, без додаткового полірування — концепція рекомендацій зміниться в окремій задачі. Спочатку перевірити, чи модуль вже увімкнений десь на бойовому'],
        ]} />
        <SpecBlock title="RD-12 · Міні-кошик" sub="common/cart.twig — Bootstrap-dropdown замінюється на шухляду. Інлайновий <style> з хардкодом кольорів видалити." rows={[
          ['Десктоп', 'Шухляда справа, ширина 380px, на всю висоту; підкладка rgba(17,24,39,.45); анімація 260ms'],
          ['Мобайл', 'Нижній аркуш: max-height 86%, радіус --bs-r-lg зверху, «ручка» 40×4px'],
          ['Шапка', '«Кошик · N товарів» + хрестик 44×44'],
          ['Рядок', 'Мініатюра 56×56, назва до 2 рядків, степер 44px, сума позиції, хрестик видалення'],
          ['Низ', 'Липкий блок: рядок доставки, «Сума», зелена CTA «Оформити замовлення» h48, нижче «Продовжити покупки» і «Відкрити кошик»'],
          ['Тригер у шапці', '--bs-green / hover --bs-green-d (зараз #1fa247 / #18853a — це дрейф кольору, виправити в цьому ж патчі). Лічильник — темний кружечок --bs-ink з білою рамкою 2px'],
          ['Колізії', 'Шухляда z-index --bs-z-modal (400) над cookie-банером; кнопку «нагору» ховати, поки шухляда відкрита'],
          ['Відкриття', 'Тільки вручну — після «Додати в кошик» шухляда НЕ відкривається (рішення власника 21.09)'],
        ]} />
        <SpecBlock title="Тост «Товар додано»" sub="#alert у header.twig + common.js. Мобайл — одна смужка; десктоп — три варіанти на вибір (див. прототип, перемикач «Варіант тосту»)." rows={[
          ['Мобайл', 'Смужка на всю ширину, прикріплена одразу під шапкою'],
          ['Десктоп · A — Куточка (обрано)', 'Картка 300px під кнопкою кошика в правому верхньому куті (top 74px, впритул до шапки), без кнопки-хрестика — закриття через «Продовжити» або клік поза карткою'],
          ['Десктоп · B — смужка по контенту', 'Тонована смужка, ширина = контейнер сторінки, не на всю ширину екрана'],
          ['Десктоп · C — під кнопкою кошика', 'Картка з «хвостиком» під кнопкою кошика в шапці — найкоротший маршрут оку, але залежить від позиції кнопки'],
          ['Заміна для всіх', 'Скасувати position:fixed; top:30%; left:50% + margin-left з stylesheet.css (рядки 37–90) — саме він перекриває контент'],
          ['Спільне', 'Успіх — --bs-green/--bs-green-soft/--bs-green-hover, галочка; Помилка — --bs-danger, «!», зелений не вживається. Авто-приховання 4с лише для успіху'],
          ['Доступність', 'role="status" aria-live="polite" для успіху; для помилки role="alert"'],
        ]} />
      </div>
      <div className="bs-card" style={{ padding: 18, display: 'grid', gap: 8 }}>
        <h3 style={{ fontSize: 15, fontWeight: 700, color: 'var(--bs-ink)' }}>Відкриті питання</h3>
        <ul style={{ margin: 0, paddingLeft: 18, fontSize: 13, lineHeight: 1.65, color: 'var(--bs-ink-2)' }}>
          <li>Десктопний тост: обрати варіант A/B/C з контролера в прототипі.</li>
          <li>Рекомендації: підтвердити, де саме модуль bestseller увімкнений зараз, або увімкнути через цей патч.</li>
          <li>Бекап, з якого взято розмітку, від 09.07 — перед патчем звірити файли зі свіжим станом продакшену.</li>
        </ul>
      </div>
    </div>
  );
}

Object.assign(window, { SpecNotes, SpecBlock, SpecRow });
