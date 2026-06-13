# Хендоф для Codex — FAQ-акордеон: єдиний нормалізатор + Variant A (Quiet hairlines)

**Дата:** 2026-06-03
**Гілка:** `feat/faq-normalizer`
**Стек:** OpenCart 4 · тема `catalog/view/template/...` · `boostershop-ds.css` (DS) · vanilla JS (без залежностей)
**Скоуп:** product page (опис товару, секція FAQ). Жодних змін у кошику, оформленні, фіскалізації.

Затверджено власником у `FAQ редизайн v2.html` → артборд **«A · Quiet hairlines»** + мобільна версія в секції *Mobile*.

---

## 0. TL;DR — що робимо

1. **Корінь проблеми:** ШІ пише FAQ кожного разу різним HTML — то `<h4>+<p>`, то `<p><strong>?</strong></p>`, то заголовки без відповідей. CSS `.bs-faq-accordion` чекає **одну** структуру → товари рендеряться по-різному (OP-15, Mega Dream EX). Промпти латати марно, ШІ все одно гулятиме.
2. **Рішення:** додаємо невеликий vanilla-JS нормалізатор `bs-faq.js`, який на product page:
   - знаходить заголовок «FAQ» або «Часті питання» в описі;
   - парсить будь-який з 4-х відомих форматів у пари Q/A;
   - будує канонічний DOM `.bs-faq` з a11y і Schema.org FAQPage розміткою;
   - видаляє з опису старий месив (заголовок + slice).
3. **Новий CSS** `bs-faq.css` — варіант A «Quiet hairlines»: простий текстовий заголовок (БЕЗ beige-блока з `padding:16px`, який давав той «негарний відступ зверху»), тонкі лінії між питаннями, gold-chevron на відкритті, плавна анімація через CSS `grid-template-rows`.
4. **Старі стилі `.bs-faq-accordion` / `.bs-faq-title` / `.bs-faq-item` зачищаються** в новому CSS (`all: revert` + `display:none`) — на випадок, якщо тема десь їх ще використовує.

**Файли в патчі (всі лежать у `handoff/faq-accordion/`):**

| Файл | Куди ставити | Що робить |
|---|---|---|
| `bs-faq.css` | `catalog/view/.../stylesheet/bs-faq.css` (поряд з `boostershop-ds.css`) | Стилі акордеону — Variant A |
| `bs-faq.js`  | `catalog/view/.../javascript/bs-faq.js` | Нормалізатор + поведінка |
| цей файл     | — | Інструкція для тебе |

**Підключення в темі** (один раз, у `common/header.twig` або `product/product.twig` — як зручніше):

```twig
{# після boostershop-ds.css #}
<link rel="stylesheet" href="catalog/view/.../stylesheet/bs-faq.css">

{# в кінці body (або defer у head) — тільки на product page #}
<script src="catalog/view/.../javascript/bs-faq.js" defer></script>
```

> ℹ️ Скрипт ідемпотентний: повторний запуск нічого не ламає. Автостарт на `DOMContentLoaded`, є публічний API `window.BoosterShopFaq.normalizeFaq(rootEl)` — якщо опис довантажується аяксом, виклич руками після вставки.

---

## 1. Що саме виправляємо в дизайні

### Було (затверджений раніше варіант)

```
[ beige background, padding:16px, margin:22px ]
  FAQ
[ /beige ]
─────────────────────────────────────
  Питання?                         [+]
─────────────────────────────────────
```

**Проблеми:**
- beige-блок з `padding 16px + margin 22px` згори даває порожній «брусок» між описом і питаннями. Замовник: «виглядає просто негарно».
- Заголовок «FAQ» з усіх caps — нагадує bot-template, не сторінку магазину.
- На сторінках, де ШІ написав FAQ як `<h4>+<p>` або `<strong>+<p>`, акордеон не зчитувався — CSS чекав на `.bs-faq-accordion`, який ШІ не написав.

### Стало (Variant A)

```
Часті питання
─────────────────────────────────────
Що таке Black & White Rare (BWR)?  ⌄
─────────────────────────────────────
BWR — нова рідкість сетів...
─────────────────────────────────────
Чим White Flare відрізняється…?    ⌄
─────────────────────────────────────
```

- Заголовок — просто `<h3>Часті питання</h3>` на білому. Жодних блоків з фоном.
- Тонкі лінії 1px між питаннями (`var(--bs-line)`).
- Chevron підсвічується `var(--bs-gold)` при відкритті, плавно обертається.
- Хіт-зона ≥ 44px (mobile-friendly).
- Анімація через `grid-template-rows: 0fr → 1fr` — без JS-математики висоти, без стрибків.

Дизайн відповідає секції «A · Quiet hairlines» у `FAQ редизайн v2.html`. Mobile-варіант — секція «Mobile · обраний варіант A» там же.

---

## 2. Як працює нормалізатор (для розуміння)

`bs-faq.js` робить наступне на кожній сторінці товару:

```
1. Шукає опис: #tab-description / .tab-description / .product-description
2. У ньому шукає заголовок: h2/h3/h4, що матчить /faq|часті\s*питан|часто\s*задаван/i
3. Збирає сиблінги після заголовка, поки не зустріне h<=faqLevel
4. Визначає "question node" за правилами:
     - <h4>, <h5>, <h6>          → це питання
     - <dt>                       → це питання
     - <p><strong>?</strong></p> де <strong> покриває >=85% тексту
                                  і закінчується на «?» → це питання
   Все інше між питаннями        → це HTML відповіді
5. Якщо в описі вже є <div class="bs-faq-accordion"> — читає його напряму.
6. Будує новий <section class="bs-faq"> із schema.org розміткою
   і вставляє ПЕРЕД старим заголовком. Старі ноди видаляються.
7. Вішає click + ARIA. Анімацію робить CSS.
```

**Що буде, якщо ШІ дав питання без відповіді** (Mega Dream EX case): рендериться рядок «Відповідь у підготовці.» (клас `.bs-faq__a-pending`, курсивом, приглушеним кольором). Це краще ніж сирий `<h4>` без розкриття.

**Що буде, якщо ШІ зовсім не написав FAQ:** скрипт мовчки виходить, нічого не псує.

---

## 3. CSS — `bs-faq.css`

Файл цілком готовий, лежить у `handoff/faq-accordion/bs-faq.css`. Звертається до існуючих токенів DS (`--bs-ink`, `--bs-ink-2`, `--bs-ink-3`, `--bs-line`, `--bs-gold`, `--bs-blue`). Якщо токенів немає в момент завантаження — у кожного правила є фолбек-хексу.

Ключові точки:

```css
.bs-faq          { margin: 24px 0 0; background: transparent; }  /* більше жодних beige-фонів */
.bs-faq__title   { font-size: 22px; font-weight: 700; }
.bs-faq__list    { border-top: 1px solid var(--bs-line); }
.bs-faq__item    { border-bottom: 1px solid var(--bs-line); }
.bs-faq__q       { width: 100%; padding: 18px 4px; min-height: 44px; }
.bs-faq__a       { display: grid; grid-template-rows: 0fr;
                   transition: grid-template-rows .28s cubic-bezier(.2,.7,.2,1); }
.bs-faq__item[data-open="true"] .bs-faq__a { grid-template-rows: 1fr; }
.bs-faq__a-inner { overflow: hidden; padding: 0 56px 0 4px; }
.bs-faq__item[data-open="true"] .bs-faq__a-inner { padding-bottom: 20px; }
```

Mobile (`@media (max-width: 640px)`): дрібніший заголовок (19px), коротший правий padding (32px), щільніший gap. `prefers-reduced-motion` вимикає переходи.

В кінці файлу — захист від спадщини:

```css
.bs-faq-accordion,
.bs-faq-accordion .bs-faq-title,
.bs-faq-accordion .bs-faq-item { all: revert; }
.bs-faq-accordion { display: none !important; }
```

Це для тих сторінок, де canonical-розмітка лишається в DOM до нормалізатора. Він її ховає (а скрипт замінить новим акордеоном).

---

## 4. JS — `bs-faq.js`

Файл цілком готовий, лежить у `handoff/faq-accordion/bs-faq.js`. Vanilla JS, IIFE, без залежностей. Підтримує IE11+ за духом (без сучасних літералів) — але можеш сміливо вважати, що ним користуються тільки сучасні браузери.

Публічний API (на `window.BoosterShopFaq`):

```js
BoosterShopFaq.normalizeFaq(rootEl)   // основна функція
BoosterShopFaq.parseFaq(rootEl)       // лише парсинг → { items: [{q, aHtml}] }
BoosterShopFaq.buildAccordion(parsed) // лише рендер DOM
BoosterShopFaq.init()                 // авто-обхід всіх описів на сторінці
```

Селектори опису, в яких шукати FAQ:

```js
var DESCRIPTION_SELECTORS = [
  '#tab-description',
  '.tab-description',
  '.product-description',
  '.bs-product-description',
];
```

Якщо назва контейнера в темі інша — додай туди ще один селектор.

---

## 5. Що НЕ робити в шаблоні Twig

Не треба міняти `product/product.twig` під «правильну розмітку FAQ». Сенс нормалізатора саме в тому, щоб не залежати від того, як ШІ напише `description` товару. Опис як було — текст в БД, як приходить — так приходить.

Якщо колись ми зробимо окреме поле «FAQ» в адмінці і будемо віддавати його з контролера як масив питань — тоді робимо `<section class="bs-faq">…</section>` прямо в Twig, і скрипт не запускається (бо `data-bs-faq-done="1"` можна виставити з шаблону).

---

## 6. Тест-кейси — обов'язково перевірити

1. **OP-15 (https://boostershop.website/product/OnePiece-booster-box-OP15)** — там FAQ написаний `<strong>?</strong>+<p>`. Має зрендеритися 3 акордеон-айтеми.
2. **Mega Dream EX (https://boostershop.website/product/Pokemon-boosters-Mega-Dream-EX)** — там `<h4>` без відповідей. Має зрендеритися 4 айтеми зі станом «Відповідь у підготовці.».
3. **White Flare (https://boostershop.website/product/Pokemon-boosters-White-Flare)** — там canonical `<div class="bs-faq-accordion">`. Скрипт має прочитати його напряму, замінити на нову розмітку, beige-заголовок зникнути.
4. **Сторінка товару без FAQ** — скрипт не повинен нічого зробити (мовчазний exit).
5. **Mobile (DevTools, 390×844)** — питання не обрізаються, tap-зона ≥44px, chevron на місці.
6. **Клавіатура** — Tab фокусить кнопку, Enter/Space відкриває, focus-ring видимий.
7. **Schema.org** — у консолі: `document.querySelector('.bs-faq').itemType === 'https://schema.org/FAQPage'`. Має повернути true. Перевірити в Google Rich Results Test (опційно).

---

## 7. Бекап і ідемпотентність

- Файли — нові, нічого не перезаписують. Бекапи не потрібні.
- Скрипт ідемпотентний: ставить `data-bs-faq-done="1"` на root → другий виклик skip.
- Якщо треба відкотитись — прибрати `<link>` і `<script>` з шаблону, повернутися до старого CSS.

---

## 8. Подальші кроки (не цей патч)

1. **Промпт для ШІ при написанні описів** — окремо погоджуємо коротку інструкцію («FAQ пиши як список питань H4 + параграф відповіді»). Це другий рівень захисту, а не єдиний; код тепер не залежить від цього.
2. **Адмінка** — окреме поле «FAQ» (повторюваний блок Q/A). Тоді скрипт можна буде не запускати, а рендерити прямо з контролера. Пропозиція на майбутній спринт.
3. **Аналітика** — додати `data-faq-q` на кнопку і трекати, які питання відкривають. Робимо коли буде GA готовий.

---

## 9. Файли

```
handoff/faq-accordion/
├── bs-faq.css     ← кладемо в catalog/view/.../stylesheet/
├── bs-faq.js      ← кладемо в catalog/view/.../javascript/
└── CODEX - FAQ accordion normalizer.md ← цей файл
```

Готово до інтеграції.
