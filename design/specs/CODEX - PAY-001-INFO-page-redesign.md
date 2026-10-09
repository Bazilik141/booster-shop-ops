# CODEX HANDOFF — PAY-001-INFO · Редизайн сторінки «Оплата і доставка» (information_id=4)

Date: 2026-07-26 · Status: Ready for implementation · Не пов'язано з чекаут/товарною логікою `CODEX - PAY-001-ADDENDUM-2.md` (та лишається чинною окремо) — це виключно статична інформаційна сторінка.

## 1. Task ID
`PAY-001-INFO-1`

## 2. Контекст

Сторінка **«Оплата і доставка»** — `https://boostershop.website/information/oplata-i-dostavka`, OpenCart route `information/information&information_id=4` (підтверджено в шапці/меню й footer на кількох живих знімках сторінок). Обгортка — стандартний `information.twig`: хедер, `breadcrumbs` і `$heading_title` рендерить темплейт; **усе тіло статті (hero, TOC, усі H2/H3-секції) приходить з поля «Опис» в адмінці** в режимі HTML — власник керує текстом без правок коду.

⚠️ **Розбіжність у наявних доках:** пакет `handoff/content-pages/templates/information.twig` (старіший хендоф-пакет у цьому дизайн-проєкті) описує обгортку з класами `.bs-cp-shell`/`.bs-cp-crumbs` і без TOC. **Це не відповідає поточному живому сайту** — живий DOM (звірено на 2 незалежних знімках, включно зі свіжим сканом від 2026-07-26) використовує `.bs-cp-wrap` / `.bs-cp-breadcrumb` / `.bs-cp-layout` / `.bs-cp-toc` з JS, що будує TOC на льоту зі списку `<h2>` в `.bs-cp-main`. **Довіряти живому сайту й поточному `content-pages.css`, не старому доку.** Кодекс має звірити, який саме файл темплейту зараз задіяний у темі, перш ніж редагувати.

**Скарга власника:** забагато суцільного тексту, навіть заголовки (H2/H3) губляться в масиві абзаців; бейдж-«лапка» monobank (кругла чорна наклейка з написом по колу) виглядає як спам; лого ПУМБ поруч зі «Сплачуйте частинами» відсутнє взагалі.

**Дизайн погоджено власником** у окремому дизайн-проєкті. Джерело істини для цього хендофу:
- `PAY-001 Оплата і доставка - сторінка.html` — фінальна затверджена сторінка (реальні класи `.bs-cp-*`, реальні токени з `tokens.css`/`content-pages.css`).
- `PAY-001 Оплата і доставка - редизайн.html` — канвас порівняння «зараз / десктоп / мобайл» (той самий файл через iframe у 2 в'юпортах).

## 3. Мета

Замінити верстку й текст розділу «Способи оплати» (і додати іконки до інших H2) на затверджений варіант нижче — **без зміни змісту жодного розкриття банку** (ліцензії, ставки, суми, назви продуктів лишаються фактично тими самими чи прямо вказаними власником нижче). Increases scannability, not information — жоден пункт розкриття не видалено, тільки перегруповано.

## 4. Що змінити

### 4.1 CSS — додати в `content-pages.css` (той, що фактично підключений на проді; поточний query-suffix у знімках — `?v=r09copy-recovery-20260526`, **підняти версію** при деплої за тим самим конвеншеном, що й інші файли теми, напр. `?v=pay001-info-20260726`)

```css
.bs-cp-main h2{display:flex;align-items:center;gap:12px;margin:44px 0 18px;font-size:22px}
.bs-cp-main h2:first-child{margin-top:0}
.bs-cp-toc__link{display:flex;align-items:center;gap:8px}
.bs-cp-toc__link svg{flex:0 0 auto;opacity:.6}
.bs-cp-toc__link.is-active svg,.bs-cp-toc__link:hover svg{opacity:1}
.bs-cp-h2-icon{display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:9px;background:var(--bs-blue-soft);color:var(--bs-blue);flex:0 0 auto}
.bs-pm-quickcard{border:1px solid var(--bs-line);border-radius:var(--bs-r);background:#fff;box-shadow:var(--bs-sh-sm);margin-bottom:8px}
.bs-pm-row{display:flex;gap:14px;padding:18px 20px;align-items:flex-start}
.bs-pm-row+.bs-pm-row{border-top:1px solid var(--bs-line-2)}
.bs-pm-row__icon{width:34px;height:34px;border-radius:9px;background:var(--bs-line-2);color:var(--bs-ink-2);display:flex;align-items:center;justify-content:center;flex:0 0 auto}
.bs-pm-row__body{min-width:0;flex:1}
.bs-pm-row__body h3{margin:0 0 4px !important;font-size:15.5px !important}
.bs-pm-row__body p{margin:0 !important;font-size:14px}
.bs-pm-eyebrow{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--bs-ink-3);margin:32px 0 12px}
.bs-pm-card{border:1.5px solid var(--bs-line);border-radius:var(--bs-r);background:#fff;box-shadow:var(--bs-sh-sm);padding:22px 24px;margin-bottom:16px}
.bs-pm-card__head{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.bs-pm-card__head h3{margin:0 !important;font-size:18px !important;flex:1;min-width:0}
.bs-pm-logo{width:28px;height:28px;flex:0 0 auto;display:block}
.bs-pm-pill{font-size:11.5px;font-weight:800;padding:5px 11px;border-radius:999px;letter-spacing:-.01em;flex:0 0 auto;white-space:nowrap}
.bs-pm-pill--mono{background:#111;color:#fff}
.bs-pm-pill--pumb{background:var(--bs-pumb-red);color:#fff}
.bs-cp-finePrint{font-size:13px !important;color:var(--bs-ink-3) !important;line-height:1.6}
.bs-pm-kv-title{font-size:13.5px;font-weight:700;color:var(--bs-ink);margin:14px 0 -2px}
.bs-pm-note{font-size:13px;color:var(--bs-ink-3);margin:6px 0 0}
.bs-kv-grid{display:grid;grid-template-columns:1fr 1fr;column-gap:28px;row-gap:14px;margin:16px 0;padding:16px 0;border-top:1px solid var(--bs-line-2);border-bottom:1px solid var(--bs-line-2)}
.bs-kv__label{display:block;font-size:11.5px;color:var(--bs-ink-3);margin-bottom:3px}
.bs-kv__value{display:block;font-size:14px;color:var(--bs-ink);font-weight:700;line-height:1.4}
.bs-kv--copy .bs-kv__value{display:flex;align-items:center;gap:8px;font-family:'JetBrains Mono',ui-monospace,monospace;font-size:12.5px;font-weight:600;word-break:break-all}
.bs-copy-btn{position:relative;border:1px solid var(--bs-line);background:#fff;border-radius:6px;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;color:var(--bs-ink-3);cursor:pointer;flex:0 0 auto}
.bs-copy-btn:hover{color:var(--bs-blue);border-color:var(--bs-blue)}
.bs-copy-btn__tip{position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);background:#111;color:#fff;font-size:10.5px;font-weight:600;padding:3px 7px;border-radius:5px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .15s}
.bs-copy-btn.is-copied{color:#fff;background:var(--bs-green);border-color:var(--bs-green)}
.bs-copy-btn.is-copied .bs-copy-btn__tip{opacity:1}
@media (max-width:640px){.bs-kv-grid{grid-template-columns:1fr}}
```

Ці класи нові (`bs-pm-*`, `bs-kv-*`, `bs-cp-h2-icon`, `bs-copy-btn`) — жодних колізій з існуючими іменами в `content-pages.css`/`boostershop-ds.css`. Два винятки чіпають існуючі селектори: `.bs-cp-main h2` (додає flex+іконку-слот; безпечно навіть без іконки всередині — просто flex на одному текстовому вузлі) і `.bs-cp-toc__link` (додає flex+gap для іконки в TOC). **Обидва селектори спільні для всіх 5 інформаційних сторінок** (Гарантія, Про нас, Оплата і доставка, Обмін і повернення, Оферта) — перевірити, що інші 4 не ламаються (H2 в них просто не матиме `<span class="bs-cp-h2-icon">`, це нормально й очікувано, TOC-посилання так само без іконки-svg всередині — flex на порожньому вмісті не шкодить).

### 4.2 Розмітка — нове тіло «Опис» для information_id=4

Повний, готовий до вставки HTML нижче замінює вміст блоку `.bs-cp-main` (усі 4 H2-секції) і додає іконки в `<h2>` та в TOC-посилання. Джерело — файл проєкту `PAY-001 Оплата і доставка - сторінка.html` (скопійовано 1:1, лише без `<html>/<head>/<body>` обвʼязки прототипу).

**Що НЕ міняється змістовно:** ліцензії/номери банків, суми, «Надавач послуг», IBAN-реквізити, телефон, назви продуктів («Покупка частинами», «Сплачуйте частинами» — обидві залишені дослівно, це офіційні назви за банківськими гайдлайнами, не скорочувати інакше).

**Що змінилось текстово (звірити символ-в-символ):**
- monobank, вступний рядок → «Без відсотків і переплат з Покупкою частинами monobank | Universal Bank.»
- ПУМБ, вступний рядок → «Без першого платежу та переплат від ПУМБ.»
- Обидві картки отримали видимий заголовок «Характеристики продукту:» над сіткою характеристик.
- monobank: рядок «Перший платіж» тепер один із пунктів сітки (значення «у момент оформлення покупки»), не окремий абзац.
- ПУМБ: **«Строк кредитування» тепер 3 – 24 міс.** (було «від 2 до 24 місяців» — власник свідомо змінив мінімум 2→3).
- ПУМБ: порядок пунктів «Процентна ставка» і «Строк кредитування» поміняно місцями (ставка тепер перед строком).
- ПУМБ: **видалено** пункти «Щомісячна комісія за обслуговування кредитної заборгованості», «Разова комісія», «Реальна річна процентна ставка», «Погашення» — цих 4 пунктів більше нема в сітці.
- ПУМБ: **додано** пункт «Доступна кількість платежів» → «3 – 5, за ставкою 0,00001% річних», і пункт «Збільшення терміну погашення чи кількості платежів» → «за пропозицією в застосунку при оформленні, за додаткову плату» (в тому ж рядку сітки, за аналогією з monobank).
- ПУМБ: під IBAN-реквізитами додано зноску «*Платіж на IBAN за тарифами вашого банку.»
- ПУМБ: великий абзац «Ця інформація містить загальні умови…споживчого кредиту.» **замінено повністю** на «Істотні характеристики продукту та попередження — на сайті pumb.ua/mahazyny-partnery-rozstrochky.» (лінк).
- Еybrow над двома банківськими картками: «Оплата частинами — оберіть банк» → **«Сплатити частинами»**.
- Лапка monobank (кругла чорна наклейка, inline PNG ~35 КБ у base64) **видалена повністю**, замінена inline SVG-лапою (без завантаження додаткового файлу — прибирає ~35 КБ на кожен рендер сторінки).

```html
<nav class="bs-cp-breadcrumb"><a href="{{ home url }}"><i class="fas fa-home"></i></a><span class="bs-cp-breadcrumb__sep">/</span><span aria-current="page">Оплата і доставка</span></nav>

<div class="bs-cp-content">
<article class="bs-cp-article">
<header class="bs-cp-hero">
<div>
<div class="bs-cp-kicker">Booster Shop</div>
<h1>Оплата і доставка</h1>
<p class="bs-cp-intro">Відправляємо замовлення по всій Україні через Нову Пошту. Пакуємо акуратно, щоб бустери, бокси й аксесуари доїхали в тому самому стані, у якому ви їх чекали.</p>
</div>
</header>

<div class="bs-cp-layout">
<aside class="bs-cp-toc">
<div class="bs-cp-toc__title">Зміст</div>
<ol class="bs-cp-toc__list">
<li><a href="#cp-0" class="bs-cp-toc__link is-active"><svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M2 5l6-2.6L14 5v6l-6 2.6L2 11V5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M2 5l6 2.6L14 5M8 7.6V13.6" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>Доставка Новою Поштою</a></li>
<li><a href="#cp-1" class="bs-cp-toc__link"><svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.2" stroke="currentColor" stroke-width="1.4"/><path d="M8 4.6V8.2l2.6 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>Передзамовлення</a></li>
<li><a href="#cp-2" class="bs-cp-toc__link"><svg width="14" height="14" viewBox="0 0 20 20" fill="none"><rect x="2" y="4.5" width="16" height="11" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M2 8.2h16" stroke="currentColor" stroke-width="1.4"/></svg>Способи оплати</a></li>
<li><a href="#cp-3" class="bs-cp-toc__link"><svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M3 9.5L17 4l-2 13-4-2-2 3-1-4 8-7-9 5-4-1.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>Є питання?</a></li>
</ol>
</aside>

<div class="bs-cp-main">

<h2 id="cp-0"><span class="bs-cp-h2-icon"><svg width="17" height="17" viewBox="0 0 16 16" fill="none"><path d="M2 5l6-2.6L14 5v6l-6 2.6L2 11V5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M2 5l6 2.6L14 5M8 7.6V13.6" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></span>Доставка Новою Поштою</h2>
<p>У відділення, поштомат або кур'єром за адресою — обирайте, як зручніше.</p>
<p>Підтверджені замовлення зазвичай відправляємо в той самий день. Якщо товар є в наявності, максимальний строк відправки — наступний робочий день після оформлення.</p>
<p>Для замовлень від <strong>2000 грн</strong> доставка — <strong>за наш кошт</strong>.</p>
<p class="bs-cp-finePrint">* Якщо обираєте післяплату, комісія NovaPay за переказ коштів оплачується окремо згідно з тарифами Нової Пошти.</p>

<h2 id="cp-1"><span class="bs-cp-h2-icon"><svg width="17" height="17" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.2" stroke="currentColor" stroke-width="1.4"/><path d="M8 4.6V8.2l2.6 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Передзамовлення</h2>
<p>Для товарів зі статусом <strong>Передзамовлення</strong> строки доставки узгоджуємо окремо після оформлення. Зв'язуємося не пізніше 2 робочих днів і повідомляємо орієнтовну дату виконання замовлення.</p>
<p>Скасувати передзамовлення та повернути кошти можна в будь-який момент до фактичного викупу товару у постачальника. Після викупу — за домовленістю або відповідно до чинного законодавства України.</p>

<h2 id="cp-2"><span class="bs-cp-h2-icon"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><rect x="2" y="4.5" width="16" height="11" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M2 8.2h16" stroke="currentColor" stroke-width="1.4"/></svg></span>Способи оплати</h2>

<div class="bs-pm-quickcard">
<div class="bs-pm-row">
<span class="bs-pm-row__icon"><svg width="16" height="16" viewBox="0 0 20 20" fill="none"><rect x="2" y="4.5" width="16" height="11" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M2 8.2h16" stroke="currentColor" stroke-width="1.4"/></svg></span>
<div class="bs-pm-row__body"><h3>Картка онлайн</h3><p>Безпечна оплата через <strong>Hutko</strong>: Visa, Mastercard, Apple Pay та Google Pay.</p></div>
</div>
<div class="bs-pm-row">
<span class="bs-pm-row__icon"><svg width="16" height="16" viewBox="0 0 20 20" fill="none"><rect x="2" y="5.5" width="16" height="9" rx="1.6" stroke="currentColor" stroke-width="1.4"/><circle cx="10" cy="10" r="2.1" stroke="currentColor" stroke-width="1.4"/></svg></span>
<div class="bs-pm-row__body"><h3>Післяплата</h3><p>Оплата після огляду у відділенні або поштоматі Нової Пошти. Комісія NovaPay сплачується окремо.</p></div>
</div>
<div class="bs-pm-row">
<span class="bs-pm-row__icon"><svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M3 8l7-4.5L17 8M4 8v7h12V8M2 15h16" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" stroke-linecap="round"/></svg></span>
<div class="bs-pm-row__body">
<h3>Оплата на IBAN</h3>
<p>Переказ на рахунок ФОП. Реквізити також доступні під час оформлення замовлення.</p>
<div class="bs-kv-grid">
<div class="bs-kv"><span class="bs-kv__label">Отримувач</span><span class="bs-kv__value">ФОП Леусенко Євгеній Андрійович</span></div>
<div class="bs-kv"><span class="bs-kv__label">ЄДРПОУ</span><span class="bs-kv__value">3485903435</span></div>
<div class="bs-kv bs-kv--copy"><span class="bs-kv__label">IBAN</span><span class="bs-kv__value">UA063348510000000026003285008<button class="bs-copy-btn" data-copy="UA063348510000000026003285008" aria-label="Скопіювати IBAN"><svg width="12" height="12" viewBox="0 0 16 16" fill="none"><rect x="6" y="6" width="8" height="8" rx="1.3" stroke="currentColor" stroke-width="1.3"/><path d="M4.5 10H3.6a1 1 0 01-1-1V3.6a1 1 0 011-1H10a1 1 0 011 1v1" stroke="currentColor" stroke-width="1.3"/></svg><span class="bs-copy-btn__tip">Скопійовано</span></button></span></div>
<div class="bs-kv"><span class="bs-kv__label">МФО</span><span class="bs-kv__value">334851</span></div>
<div class="bs-kv"><span class="bs-kv__label">Банк</span><span class="bs-kv__value">АТ «ПУМБ»</span></div>
<div class="bs-kv"><span class="bs-kv__label">Призначення платежу</span><span class="bs-kv__value">оплата за товар</span></div>
</div>
<p class="bs-pm-note">*Платіж на IBAN за тарифами вашого банку.</p>
</div>
</div>
</div>

<div class="bs-pm-eyebrow">Сплатити частинами</div>

<div class="bs-pm-card">
<div class="bs-pm-card__head">
<span class="bs-pm-paw"><svg width="26" height="26" viewBox="0 0 32 32" style="display:block;filter:drop-shadow(0 1px 1.5px rgba(0,0,0,.4))"><g fill="#111" stroke="#fff" stroke-width="1.6" stroke-linejoin="round" paint-order="stroke"><ellipse cx="16" cy="21" rx="8.2" ry="7"/><ellipse cx="5.5" cy="11.5" rx="3.3" ry="4.3" transform="rotate(-20 5.5 11.5)"/><ellipse cx="12.2" cy="6.5" rx="3.4" ry="4.5" transform="rotate(-7 12.2 6.5)"/><ellipse cx="19.8" cy="6.5" rx="3.4" ry="4.5" transform="rotate(7 19.8 6.5)"/><ellipse cx="26.5" cy="11.5" rx="3.3" ry="4.3" transform="rotate(20 26.5 11.5)"/></g></svg></span>
<h3>Покупка частинами</h3>
<span class="bs-pm-pill bs-pm-pill--mono">monobank</span>
</div>
<p>Без відсотків і переплат з Покупкою частинами monobank | Universal Bank.</p>
<p class="bs-cp-finePrint"><strong>Надавач послуг:</strong> АТ «УНІВЕРСАЛ БАНК» ліцензія НБУ №92 від 20.01.1994, номер у держреєстрі банків №226.</p>
<div class="bs-pm-kv-title">Характеристики продукту:</div>
<div class="bs-kv-grid">
<div class="bs-kv"><span class="bs-kv__label">Мінімальна сума розстрочки</span><span class="bs-kv__value">1 грн</span></div>
<div class="bs-kv"><span class="bs-kv__label">Максимальна сума розстрочки</span><span class="bs-kv__value">400 000 грн</span></div>
<div class="bs-kv"><span class="bs-kv__label">Реальна річна процентна ставка</span><span class="bs-kv__value">0,000001%</span></div>
<div class="bs-kv"><span class="bs-kv__label">Доступний строк, платежів</span><span class="bs-kv__value">3 – 25</span></div>
<div class="bs-kv"><span class="bs-kv__label">Порядок погашення</span><span class="bs-kv__value">щомісячні платежі рівними частинами</span></div>
<div class="bs-kv"><span class="bs-kv__label">Перший платіж</span><span class="bs-kv__value">у момент оформлення покупки</span></div>
</div>
<p class="bs-pm-note">Істотні характеристики продукту та попередження — на сайті <a href="https://chast.monobank.ua" target="_blank" rel="noopener">chast.monobank.ua</a>.</p>
</div>

<div class="bs-pm-card">
<div class="bs-pm-card__head">
<img class="bs-pm-logo" src="{{ ШЛЯХ_ДО_ЛОГО_ПУМБ }}" alt="ПУМБ">
<h3>Сплачуйте частинами</h3>
<span class="bs-pm-pill bs-pm-pill--pumb">ПУМБ</span>
</div>
<p>Без першого платежу та переплат від ПУМБ.</p>
<p class="bs-cp-finePrint"><strong>Надавач послуг:</strong> АКЦІОНЕРНЕ ТОВАРИСТВО «ПЕРШИЙ УКРАЇНСЬКИЙ МІЖНАРОДНИЙ БАНК», банківська ліцензія НБУ №8 від 06.10.2011.</p>
<div class="bs-pm-kv-title">Характеристики продукту:</div>
<div class="bs-kv-grid">
<div class="bs-kv"><span class="bs-kv__label">Мінімальна сума кредиту</span><span class="bs-kv__value">500 грн</span></div>
<div class="bs-kv"><span class="bs-kv__label">Максимальна сума кредиту</span><span class="bs-kv__value">500 000 грн</span></div>
<div class="bs-kv"><span class="bs-kv__label">Процентна ставка</span><span class="bs-kv__value">0,00001% річних</span></div>
<div class="bs-kv"><span class="bs-kv__label">Строк кредитування</span><span class="bs-kv__value">3 – 24 міс.</span></div>
<div class="bs-kv"><span class="bs-kv__label">Доступна кількість платежів</span><span class="bs-kv__value">3 – 5, за ставкою 0,00001% річних</span></div>
<div class="bs-kv"><span class="bs-kv__label">Збільшення терміну погашення чи кількості платежів</span><span class="bs-kv__value">за пропозицією в застосунку при оформленні, за додаткову плату</span></div>
</div>
<p class="bs-pm-note">Істотні характеристики продукту та попередження — на сайті <a href="https://pumb.ua/mahazyny-partnery-rozstrochky" target="_blank" rel="noopener">pumb.ua/mahazyny-partnery-rozstrochky</a>.</p>
</div>

<h2 id="cp-3"><span class="bs-cp-h2-icon"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><path d="M3 9.5L17 4l-2 13-4-2-2 3-1-4 8-7-9 5-4-1.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></span>Є питання?</h2>
<p>Напишіть нам у Telegram — відповідаємо швидко і з задоволенням.</p>
<p style="margin-top:12px"><a class="bs-info-cta" href="https://telegram.me/boostershop_tcg" rel="noopener" target="_blank">Написати в Telegram</a></p>
<p>Телефон: <strong>+38 063 674 32 52</strong>.</p>

</div>
</div>
</article>
</div>
```

**Примітки до вставки:**
- Перший рядок (`<nav class="bs-cp-breadcrumb">…`) наведено для довідки/звірки з тим, що вже рендерить `{{ breadcrumbs }}` у темплейті — **це НЕ частина «Опис»**, не вставляти дублікат. Якщо жива сторінка вже рендерить breadcrumb з темплейту (найімовірніше) — почати вставку з `<div class="bs-cp-content">`.
- **Опис-поле в адмінці, найімовірніше, містить лише вміст від `<article class="bs-cp-article">` до його закриття** (без обгорток `.bs-cp-page`/`.bs-cp-wrap`/`.bs-cp-row`, які, судячи з `row`/`col` Bootstrap-класів, належать темплейту). **Кодекс має звірити точну межу поля «Опис» у БД/адмінці перед вставкою** — не припускати. Незалежно від точної межі, нова розмітка сумісна: використовує ті самі id (`cp-0…cp-3`), ті самі контейнерні класи (`.bs-cp-hero`, `.bs-cp-layout`, `.bs-cp-toc`, `.bs-cp-main`), тож існуючий TOC-скрипт і JSON-LD (якщо вони темплейт-рівня, поза «Опис») продовжать працювати без змін.
- Заглушка `{{ ШЛЯХ_ДО_ЛОГО_ПУМБ }}` — замінити на реальний шлях після завантаження логотипу (п. 4.3). Це не Twig-змінна в буквальному сенсі (Опис — статичний HTML, не темплейт) — просто плейсхолдер-нагадування, підставити абсолютний чи відносний шлях до файлу як звичайний рядок.

### 4.3 Новий асет — лого ПУМБ

Власник надав офіційний файл: у дизайн-проєкті лежить як `pumb-logo.jpg` (оригінал `PUMB_SCH_.jpg`, кругла червона марка на білому тлі, без прозорості — це нормально, білий фон збігається з фоном картки). Завантажити в медіатеку/каталог зображень за існуючим конвеншеном (поруч із рештою `image/catalog/...` асетів), прописати реальний шлях у `src` (замінює плейсхолдер із п. 4.2). Розмір відображення — 28×28 CSS px (клас `.bs-pm-logo`), тож завантажувати можна в помірному дозволі (напр. 128×128 чи 256×256), не оригінальний друкований розмір.

### 4.4 JS — кнопка копіювання IBAN

Малий vanilla-скрипт, додати поруч із рештою скриптів сторінки (чи інлайном в кінці «Опис», за існуючим конвеншеном цієї ж сторінки — TOC-скрипт уже вбудований інлайном):

```html
<script>
document.querySelectorAll('.bs-copy-btn').forEach(function(btn){
btn.addEventListener('click',function(){
var text=btn.getAttribute('data-copy');
if(navigator.clipboard){navigator.clipboard.writeText(text).then(function(){
btn.classList.add('is-copied');
setTimeout(function(){btn.classList.remove('is-copied')},1300);
}).catch(function(){});}
});
});
</script>
```

Обробляє `.catch()` мовчки — на браузерах/контекстах без Clipboard API кнопка просто не покаже «Скопійовано», без помилки в консолі.

## 5. Не чіпати

- **`sitemap.xml`, `robots.txt`, редіректи, canonical, `.htaccess`** — не зачіпаються цим завданням, залишити як є.
- **TOC-скрипт** (будує `<li>` в `.bs-cp-toc__list` зі списку `<h2>`) і **JSON-LD BreadcrumbList** — не редагувати; нова розмітка навмисно сумісна з ними (ті самі id/класи).
- **Інші 4 інформаційні сторінки** (Гарантія оригінальності, Про нас, Обмін і повернення, Публічна оферта) — спільний `content-pages.css`, не міняти їхній HTML; тільки перевірити, що нові CSS-правила (п. 4.1) не ламають їхній вигляд.
- **Чекаут/оплата/фіскалізація** — ця сторінка суто інформаційна (без форм, без транзакцій). Не займатись логікою PAY-001 credit/checkout (`pay001-*.jsx`, `CODEX - PAY-001-ADDENDUM-2.md`) в межах цього таску — окрема робота.
- **Merchant feed / Product schema** — не використовується на цій сторінці, не зачіпається.
- Точні числа/ліцензії/реквізити банків — **не змінювати понад те, що прямо вказано в п. 4.2** (звірити символ-в-символ).

## 6. Ймовірні файли / зони

- `catalog/view/stylesheet/content-pages.css` (чи еквівалент у поточній темі — підтвердити точний шлях)
- Адмінка → Дизайн → Інформація → «Оплата і доставка» (`information_id=4`) → поле «Опис»; або напряму таблиця БД (`oc_information_description` чи еквівалент конкретної версії OpenCart — **підтвердити назву таблиці/поля в реальному коді**, не припускати)
- Темплейт-обгортка інформаційних сторінок — **звірити, який файл справді підключений** (наявний у цьому дизайн-проєкті `handoff/content-pages/templates/information.twig` виглядає застарілим, див. п. 2)
- Медіатека/каталог зображень — новий файл логотипу ПУМБ

## 7. Критерії приймання

- [ ] `/information/oplata-i-dostavka` — кожен H2 («Доставка Новою Поштою», «Передзамовлення», «Способи оплати», «Є питання?») має іконку зліва від тексту.
- [ ] «Способи оплати» показує ОДНУ картку з 3 рядками (Картка онлайн / Післяплата / Оплата на IBAN), IBAN-реквізити — сіткою мітка/значення, з кнопкою копіювання IBAN (клік → реальний textContent з `data-copy` потрапляє в буфер обміну, без помилок у консолі) і зноскою «*Платіж на IBAN за тарифами вашого банку.» під нею.
- [ ] Eyebrow «Сплатити частинами» над двома картками банків.
- [ ] Картка monobank: маленька inline SVG-лапа (не base64-наклейка), пігулка «monobank», текст і сітка характеристик — точно як у п. 4.2 (6 пунктів, включно з «Перший платіж»).
- [ ] Картка ПУМБ: реальне лого (не зламане зображення), пігулка «ПУМБ» (червона), текст і сітка характеристик — точно як у п. 4.2 (6 пунктів: мін./макс. сума, ставка, строк 3–24 міс., доступна кількість платежів, збільшення терміну — саме в цьому порядку; жодного зі старих 4 прибраних пунктів нема).
- [ ] Фінальний абзац ПУМБ — лінк на `pumb.ua/mahazyny-partnery-rozstrochky`, старого абзацу «Ця інформація містить загальні умови…» більше нема.
- [ ] TOC («Зміст») як і раніше показує 4 пункти, скрол-спай підсвічує активний розділ.
- [ ] Консоль без помилок; на інших 4 інформаційних сторінках — візуальних регресій нема (вибірково перевірити хоча б одну, напр. «Про нас»).
- [ ] Мобайл (≤768px): картки й сітки не ламаються, сітка характеристик переходить в 1 колонку на ≤640px. Кнопка копіювання IBAN — 22×22px (менше загального мінімуму 44px для тач-цілей): прийнятний виняток, це другорядна дія поруч із уже видимим текстом IBAN (сам текст лишається виділюваним/копійованим і руками), не основний CTA.

## 8. QA / смоук-тест

Не чекаут-флоу (форм і транзакцій немає) — повний `bs-checkout-smoke` не потрібен. Ручна перевірка:
1. Завантажити сторінку десктоп + мобайл (390px) — звірити з `PAY-001 Оплата і доставка - редизайн.html` (канвас-порівняння).
2. Клікнути кожне посилання TOC — переконатись у коректному скролі й підсвітці.
3. Натиснути кнопку копіювання IBAN, вставити кудись — має бути точно `UA063348510000000026003285008`.
4. Перевірити, що лого ПУМБ і всі SVG-іконки рендеряться (немає битих зображень / порожніх іконок).
5. Відкрити ще одну інформаційну сторінку (напр. «Про нас») — переконатись, що спільний CSS не зламав її.

**Важливо (не блокер для деплою, але не закривати задачу без цього):** текст банківських розкриттів (ставки, суми, назви продуктів) — регуляторний контент. Рекомендую власнику звірити фінальний текст ПУМБ/monobank-карток із банківськими PDF (`Ключова інформація про банківський продукт та правила її розміщення Сплачуйте частинами ПУМБ.pdf`, `monobank | ПЧ Guideline.pdf`) і, за раніше зафіксованим ризиком проєкту, узгодити зміну лого/тексту ПУМБ із банком перед публікацією (див. `handoff_PAY-001-UI2_design-questions_20260722.md`, §8 «ПУМБ-брендинг»).

## 9. Rollback note

- Перед перезаписом поля «Опис» — **скопіювати поточний HTML як є** в окремий файл-бекап (напр. `handoff/rollback/information-4-pre-20260726.html`), оскільки контент живе в БД, не в git — відкату «однією командою» нема, тільки ручна вставка збереженої копії назад.
- CSS: зберегти копію поточного `content-pages.css` (напр. `content-pages.css.bak-pre-pay001-info`) до перезапису; відкат — повернути файл і попередній `?v=` в лінку.
- Новий асет лого ПУМБ — адитивний (новий файл), відкату не потребує; просто повернути `src` на попередній стан («Опис»-бекап уже містить старий варіант без лого).

## 10. Рекомендований статус після виконання

**Ready for owner content QA** (не «Done») — потрібне фінальне звірення текстів банківських розкриттів власником/банком перед тим, як вважати задачу повністю закритою (п. 8).
