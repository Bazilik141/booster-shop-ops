# Хендоф для Codex — Сторінка товару: RD-10 фікс + breadcrumb редизайн

**Дата:** 2026-06-11
**Гілка:** `fix/product-page-rd10`
**Стек:** OpenCart 4 · Twig · `boostershop-ds.css`
**Скоуп:** тільки `product/product.twig` + CSS. Кошик, оплата, фіскалізація — **не чіпати**.

Затверджено власником на дошці: `Сторінка товару - фінал.html`
(4 артборди: Desktop in-stock · Desktop preorder · Mobile in-stock · Mobile preorder)

---

## 0. TL;DR — що робимо

| # | Проблема (після RD-10) | Що зробити |
|---|---|---|
| 1 | Breadcrumb — потворний box-контейнер з рамкою | Замінити на pill-chip nav + колір активного чіпу за категорією |
| 2 | Зображення розтягується на всю висоту колонки | Обмежити `max-height` контейнеру галереї |
| 3 | Зник напис «Виробник:», цифра наявності без контексту | Відновити підпис виробника + «В наявності: XX шт» |
| 4 | Зник блок знижки при купівлі від 5 шт | Відновити `.bs-bulk-discount` з даних `product_discounts` |
| 5 | Блок «Оплата частинами ПУМБ» видно, а сервіс не підключено | Сховати тимчасово через CSS |
| 6 | «0 відгуків» без сенсу для нового товару | Якщо відгуків 0 → посилання «Відгуки про нас →» на OLX |
| 7 | Траст-рядок не відповідає референсу з головної | Нові 3 пункти з іконками; різні для in-stock і preorder |

**Файли:**
| Файл | Зміна |
|---|---|
| `catalog/view/template/product/product.twig` | Едіти 1–7 |
| `catalog/view/stylesheet/boostershop-ds.css` | Нові CSS-блоки (описано в кожному едіті) |
| `catalog/controller/product/product.php` | Передати `category_code`, `review_count`, `discounts` (едіт 1, 4, 6) |

---

## 1. Breadcrumb: pill-chips + колір активного чіпу за категорією

**Поточний вигляд:** повноширинний рядок з `border-top/border-bottom`, схожий на хедер — виглядає зайвим елементом.

**Новий вигляд:** маленькі pill-chips без контейнера. Активний чіп (назва товару) фарбується в колір бренду.

### 1.1 Контролер — передати code категорії

У `catalog/controller/product/product.php`, де збирається `$data['breadcrumbs']`, додати:

```php
// після збору breadcrumbs
// Отримати перший рівень категорії (parent_id = 0 → top-level)
$category_id  = $this->request->get['path'] ?? 0;
$category_info = $this->model_catalog_category->getCategory($category_id);
$data['category_code'] = $category_info['keyword'] ?? ''; // slug: 'pokemon', 'one-piece' тощо
```

> Якщо `keyword` не заповнений для категорії — заповніть в Admin → Catalog → Categories → SEO keyword. Без нього колір фолбечить на «pokémon gold» (безпечно).

### 1.2 Шаблон

Знайти існуючий breadcrumb-блок у `product.twig` та **повністю замінити**:

```twig
{# ── Breadcrumb category colour map ───────────────────────────────── #}
{% set _cat_colors = {
  'pokemon':   { bg: '#FBF4DC', bd: '#D4A017', tx: '#6B3A00' },
  'one-piece': { bg: '#EEF2FF', bd: '#C7D2FE', tx: '#1E3A8A' },
  'mtg':       { bg: '#FEF2F2', bd: '#FECACA', tx: '#991B1B' },
  'yugioh':    { bg: '#F5F3FF', bd: '#DDD6FE', tx: '#5B21B6' },
  'acc':       { bg: '#F3F4F6', bd: '#D1D5DB', tx: '#374151' },
} %}
{% set _cc = _cat_colors[category_code] ?? _cat_colors['pokemon'] %}

<nav class="bs-crumb" aria-label="Breadcrumb">
  <ol class="bs-crumb__list">
    {% for crumb in breadcrumbs %}
      {% if not loop.last %}
        <li class="bs-crumb__item">
          <a href="{{ crumb.href }}" class="bs-crumb__link {% if loop.first %}bs-crumb__link--home{% endif %}"
             aria-label="{{ loop.first ? text_home : crumb.text }}">
            {% if loop.first %}
              <svg width="10" height="10" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                <path d="M8 2l6 5v7h-4v-4H6v4H2V7l6-5z"/>
              </svg>
            {% else %}
              {{ crumb.text }}
            {% endif %}
          </a>
          <span class="bs-crumb__sep" aria-hidden="true">
            <svg width="7" height="7" viewBox="0 0 8 8" fill="none">
              <path d="M2 1.5l4 2.5-4 2.5" stroke="currentColor" stroke-width="1.4"
                    stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </li>
      {% else %}
        {# Поточний товар — кольоровий чіп категорії #}
        <li class="bs-crumb__item">
          <span class="bs-crumb__current"
            style="background:{{ _cc.bg }};border-color:{{ _cc.bd }};color:{{ _cc.tx }};">
            {{ crumb.text }}
          </span>
        </li>
      {% endif %}
    {% endfor %}
  </ol>
</nav>
```

### 1.3 CSS (append to `boostershop-ds.css`)

```css
/* -- Breadcrumb pill-chips ---------------------------------------------- */
.bs-crumb { padding: 10px 0 4px; }
.bs-crumb__list {
  list-style: none; margin: 0; padding: 0;
  display: flex; align-items: center; gap: 5px; flex-wrap: wrap;
}
.bs-crumb__item { display: flex; align-items: center; gap: 5px; }
.bs-crumb__sep  { color: var(--bs-ink-4); display: inline-flex; }

.bs-crumb__link {
  display: inline-flex; align-items: center; justify-content: center;
  padding: 4px 10px; border-radius: 999px;
  background: var(--bs-bg); border: 1px solid var(--bs-line);
  font-size: 12px; color: var(--bs-ink-3); text-decoration: none; font-weight: 500;
  white-space: nowrap; transition: border-color .15s, color .15s;
}
.bs-crumb__link:hover { border-color: var(--bs-ink-3); color: var(--bs-ink-2); }
.bs-crumb__link--home { width: 26px; height: 26px; padding: 0; }

.bs-crumb__current {
  display: inline-flex; align-items: center;
  padding: 4px 12px; border-radius: 999px;
  font-size: 12px; font-weight: 600;
  border: 1px solid;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 380px;
}

@media (max-width: 768px) {
  .bs-crumb__link { font-size: 11px; padding: 3px 9px; }
  .bs-crumb__current { font-size: 11px; max-width: 155px; }
}
```

---

## 2. Галерея: обмежити висоту зображення

**Проблема:** після RD-10 контейнер зображення розтягується на всю висоту grid-колонки — для портретних бустер-паків (~2:3 ratio) картинка стає непропорційно великою і розмитою.

**Фікс:** обмежити main-image CSS-ом. Адаптувати селектор до реального класу/id контейнера.

```css
/* -- Product page gallery: max-height constraint ----------------------- */
/* Підставити реальний селектор main-image контейнера */
.product-image-container,
#image,
.bs-pp__gallery .main-image,
.thumbnails-main {          /* ← OpenCart default, перевір */
  max-height: 460px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.product-image-container img,
#image img,
.bs-pp__gallery .main-image img {
  max-height: 460px;
  width: auto;
  max-width: 100%;
  object-fit: contain;
  display: block;
  margin: 0 auto;
}

@media (max-width: 768px) {
  .product-image-container,
  #image { max-height: 340px; }
  .product-image-container img,
  #image img { max-height: 340px; }
}
```

> Якщо тема зберігає aspect-ratio через `padding-bottom` трюк — видали padding з контейнера, замість нього постав `height: auto; max-height: 460px` на `<img>` напряму.

---

## 3. Метадані товару: Виробник + Наявність

**До (зламано):** виробник — голе посилання без підпису; наявність — число в зеленому бейджі без контексту.

**Після:** два рядки з підписами в нейтральній табличній панелі.

### 3.1 Шаблон

Знайти блок з виробником та кількістю і замінити:

```twig
{# ── Product meta panel ─────────────────────────────────────────── #}
<div class="bs-pp-meta">

  <div class="bs-pp-meta__row">
    <span class="bs-pp-meta__label">Виробник</span>
    {% if manufacturer %}
      <a href="{{ manufacturer }}" class="bs-pp-meta__link">{{ manufacturer_name }}</a>
    {% else %}
      <span class="bs-pp-meta__val">—</span>
    {% endif %}
  </div>

  <div class="bs-pp-meta__row">
    {% if preorder %}
      <span class="bs-pp-meta__label">Статус</span>
      <span class="bs-badge bs-badge--preorder" style="font-size:12px;">● Передзамовлення</span>
    {% else %}
      <span class="bs-pp-meta__label">В наявності</span>
      <span class="bs-pp-meta__val" style="color:var(--bs-green);font-weight:700;">
        {{ stock_quantity }} шт
      </span>
    {% endif %}
  </div>

</div>
```

> `manufacturer` — href (як у стандартному OpenCart product.twig, де `{{ manufacturer }}`). `manufacturer_name` — текст. `stock_quantity` — число (ціле). `preorder` — boolean з контролера (якщо ще немає — дивись логіку з `CODEX - product-card-states-preorder.md`).

### 3.2 CSS

```css
/* -- Product page meta panel ------------------------------------------- */
.bs-pp-meta {
  border: 1px solid var(--bs-line-2);
  border-radius: 8px; overflow: hidden;
}
.bs-pp-meta__row {
  display: flex; align-items: center; gap: 8px;
  padding: 9px 14px; font-size: 13px;
}
.bs-pp-meta__row:first-child { background: #fff; border-bottom: 1px solid var(--bs-line-2); }
.bs-pp-meta__row:last-child  { background: var(--bs-bg); }
.bs-pp-meta__label { width: 96px; flex-shrink: 0; color: var(--bs-ink-4); }
.bs-pp-meta__val   { color: var(--bs-ink-2); font-weight: 500; }
.bs-pp-meta__link  { color: var(--bs-blue); text-decoration: none; font-weight: 600; }
.bs-pp-meta__link:hover { text-decoration: underline; }
```

---

## 4. Відгуки: «Відгуки про нас» коли рейтингу немає

**До:** «0 відгуків / Написати відгук» — для нового товару виглядає як пустий показник.

**Після:** якщо відгуків 0 — одна пряма ссилка на OLX-профіль. Якщо є відгуки — стандартний рядок зірок.

### 4.1 Контролер — передати URL

```php
$data['olx_profile_url'] = 'https://www.olx.ua/uk/shops/<ВАШ_ID_МАГАЗИНУ>/';
// і передати кількість відгуків числом:
$data['review_count'] = $review_total; // вже є в stock OpenCart product.php
```

### 4.2 Шаблон

```twig
{# ── Reviews row ─────────────────────────────────────────────────── #}
{% if review_count > 0 %}
  <div class="bs-pp-reviews">
    <div class="bs-stars" aria-label="{{ text_rating }}">
      {# existing stars markup — лишити як є #}
    </div>
    <a href="#tab-reviews" class="bs-pp-reviews__count">
      {{ review_count }} {{ text_reviews }}
    </a>
    <span class="bs-pp-reviews__sep">/</span>
    <a href="#tab-reviews" class="bs-pp-reviews__write">{{ text_write_review }}</a>
  </div>
{% else %}
  <a href="{{ olx_profile_url }}" target="_blank" rel="noopener" class="bs-pp-reviews__olx">
    <svg width="12" height="12" viewBox="0 0 14 14" fill="#F59E0B" aria-hidden="true">
      <path d="M7 1.5l1.7 3.6L12.5 6 9.7 8.5l.8 4L7 10.5 3.5 12.5l.8-4L1.5 6l3.8-.9L7 1.5z"/>
    </svg>
    Відгуки про нас →
  </a>
{% endif %}
```

### 4.3 CSS

```css
/* -- Product page reviews row ------------------------------------------ */
.bs-pp-reviews { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.bs-pp-reviews__count,
.bs-pp-reviews__write { font-size: 13px; color: var(--bs-blue); text-decoration: none; font-weight: 500; }
.bs-pp-reviews__write:hover,
.bs-pp-reviews__count:hover { text-decoration: underline; }
.bs-pp-reviews__sep { color: var(--bs-ink-4); font-size: 13px; }

.bs-pp-reviews__olx {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 13px; color: var(--bs-blue); text-decoration: none; font-weight: 500;
}
.bs-pp-reviews__olx:hover { text-decoration: underline; }
```

---

## 5. Знижка при купівлі від 5 шт: відновити

Цей блок існував до RD-10 і був видалений. Поставити назад між ціною і рядком «Кількість + Кошик».

### 5.1 Контролер — передати перший тир знижки

OpenCart уже передає `$data['discounts']` (масив знижок за кількістю). Якщо він є — дістати перший рівень:

```php
$data['bulk_qty']   = !empty($data['discounts']) ? (int)$data['discounts'][0]['quantity'] : 0;
$data['bulk_price'] = !empty($data['discounts']) ? $data['discounts'][0]['price']         : '';
// $data['discounts'][0]['price'] — вже відформатований рядок з валютою, як price/special
```

### 5.2 Шаблон

Вставити після блоку ціни:

```twig
{% if bulk_qty and bulk_price %}
  <div class="bs-bulk-discount">
    <svg width="12" height="12" viewBox="0 0 16 16" fill="none"
         stroke="#D4A017" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
         aria-hidden="true">
      <path d="M9.5 1.5H5L1.5 5v4.5l9 9L17 12z"/>
      <circle cx="5.5" cy="5.5" r="1" fill="#D4A017" stroke="none"/>
    </svg>
    {{ bulk_qty }} або більше: {{ bulk_price }}
  </div>
{% endif %}
```

### 5.3 CSS

```css
/* -- Bulk discount badge ----------------------------------------------- */
.bs-bulk-discount {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 7px 14px; border-radius: 6px;
  border: 1px solid #D4A017; background: #FBF4DC;
  color: #6B3A00; font-size: 13.5px; font-weight: 600;
}
```

---

## 6. Приховати блок «Оплата частинами ПУМБ»

Підключення модуля ПУМБ заплановано пізніше. Поки що блок приховати CSS-ом — **не видаляти markup**, щоб увімкнення в майбутньому було однорядковою зміною.

Знайти клас/id блоку ПУМБ у product.twig (шукати `pumb`, `instalments`, «Оплата частинами»). Додати в `boostershop-ds.css`:

```css
/* -- ПУМБ: тимчасово прихований до підключення модуля ----------------- */
/* Коли модуль буде готовий — прибрати цей блок цілком */
.pumb-instalments,
.bs-pumb,
[data-module="pumb"],
.payment-instalments { display: none !important; }
```

> Якщо модуль виводиться через hook/extension position — можна відключити його в Admin → Extensions → Modules замість CSS-override. CSS-правило — страховка.

---

## 7. Траст-рядок: 3 пункти, різні для preorder

**Референс:** скріншот траст-рядка з головної сторінки (золоті іконки, 3 пункти, вертикальні роздільники між ними).

**In-stock:** Гарантія оригінальності · Швидка відправка · Telegram підтримка
**Preorder:** Гарантія оригінальності · Привеземо під замовлення · Telegram підтримка

Знайти існуючий `.bs-pp__trust` (або аналогічний блок) і замінити:

```twig
{# ── Trust strip ─────────────────────────────────────────────────── #}
{% if preorder %}
  {% set _trust = [
    { icon: 'shield', label: 'Гарантія оригінальності' },
    { icon: 'truck',  label: 'Привеземо під замовлення' },
    { icon: 'send',   label: 'Telegram підтримка' },
  ] %}
{% else %}
  {% set _trust = [
    { icon: 'shield', label: 'Гарантія оригінальності' },
    { icon: 'zap',    label: 'Швидка відправка' },
    { icon: 'send',   label: 'Telegram підтримка' },
  ] %}
{% endif %}

<div class="bs-trust-strip" role="list">
  {% for item in _trust %}
    {% if not loop.first %}<div class="bs-trust-strip__div" aria-hidden="true"></div>{% endif %}
    <div class="bs-trust-strip__item" role="listitem">
      {# SVG іконки нижче — вставити inline #}
      {% if item.icon == 'shield' %}
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          <polyline points="9,12 11,14 15,10"/>
        </svg>
      {% elseif item.icon == 'zap' %}
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <polygon points="13,2 3,14 12,14 11,22 21,10 12,10 13,2"/>
        </svg>
      {% elseif item.icon == 'send' %}
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="22" y1="2" x2="11" y2="13"/>
          <polygon points="22,2 15,22 11,13 2,9 22,2"/>
        </svg>
      {% elseif item.icon == 'truck' %}
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="1" y="3" width="15" height="13" rx="1"/>
          <path d="M16 8h4l3 3v5h-7V8z"/>
          <circle cx="5.5" cy="18.5" r="2.5"/>
          <circle cx="18.5" cy="18.5" r="2.5"/>
        </svg>
      {% endif %}
      <span>{{ item.label }}</span>
    </div>
  {% endfor %}
</div>
```

### 7.1 CSS

```css
/* -- Product page trust strip ------------------------------------------ */
.bs-trust-strip {
  display: flex; align-items: stretch;
  background: #fff; border: 1px solid var(--bs-line);
  border-radius: 8px; padding: 11px 14px;
}
.bs-trust-strip__div {
  width: 1px; background: var(--bs-line);
  align-self: stretch; flex-shrink: 0; margin: 0 12px;
}
.bs-trust-strip__item {
  flex: 1; display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 5px; text-align: center;
  color: var(--bs-gold);      /* іконки золоті */
}
.bs-trust-strip__item span {
  font-size: 12.5px; font-weight: 600; color: var(--bs-ink-2);
  line-height: 1.3; text-wrap: balance;
}

@media (max-width: 768px) {
  .bs-trust-strip { padding: 9px 8px; }
  .bs-trust-strip__div { margin: 0 7px; }
  .bs-trust-strip__item svg { width: 14px; height: 14px; }
  .bs-trust-strip__item span { font-size: 10.5px; }
}
```

---

## 8. Нові токени (додати в `:root` boostershop-ds.css)

Якщо цих токенів ще немає:

```css
/* -- Additional brand tokens ------------------------------------------- */
--bs-mtg:    #991B1B;  /* Magic: The Gathering — deep red  */
--bs-yugioh: #5B21B6;  /* Yu-Gi-Oh! — violet               */
--bs-gold:   #C68A00;  /* alias до --bs-pokemon, для траст-рядка */
```

---

## 9. Кнопка «Передзамовити» (preorder CTA)

Якщо `.bs-btn-preorder` ще не доданий з попереднього патчу (`CODEX - product-card-states-preorder.md`):

```css
/* вже має бути в DS після попереднього патчу — перевір */
.bs-btn-preorder { background: var(--bs-blue-light); color: #fff; }
.bs-btn-preorder:hover { background: #2563EB; }
```

На сторінці товару також поміняти текст і колір кнопки «У кошик» для preorder:

```twig
{% if preorder %}
  <button type="button" class="bs-btn bs-btn-preorder" id="button-cart" ...>
    <svg ...cart icon...></svg> Передзамовити
  </button>
{% else %}
  <button type="button" class="bs-btn bs-btn-primary" id="button-cart" ...>
    <svg ...cart icon...></svg> У кошик
  </button>
{% endif %}
```

---

## QA-чеклист

**Breadcrumb:**
- [ ] Сторінка товару Pokémon → активний чіп **золотий** (`#FBF4DC` / `#D4A017`).
- [ ] Сторінка товару One Piece → активний чіп **синій** (`#EEF2FF` / `#C7D2FE`).
- [ ] Мобайл (390px) → назва товару обрізається через `text-overflow: ellipsis`, не переносить на 2 рядки.
- [ ] Немає старого border-box контейнера.

**Галерея:**
- [ ] Портретне зображення бустера: висота не перевищує 460px на десктопі / 340px на мобайлі.
- [ ] Зображення не розтягнуте, `object-fit: contain` зберігає пропорції.

**Метадані:**
- [ ] «Виробник: The Pokémon Company» — обидва рядки є.
- [ ] «В наявності: 18 шт» — слово «наявність» з'являється рівно один раз.
- [ ] Preorder-товар: «Статус: ● Передзамовлення» замість «В наявності».

**Відгуки:**
- [ ] Товар без відгуків → «Відгуки про нас →» + посилання відкривається в новій вкладці на OLX.
- [ ] Товар з відгуками → зірки + «N відгуків / Написати відгук».

**Знижка:**
- [ ] Товар з quantity discount → «5 або більше: ₴190.00» між ціною і кнопкою.
- [ ] Товар без знижки → блок прихований (не рендерується).

**ПУМБ:**
- [ ] Блок «Оплата частинами ПУМБ» повністю невидимий на всіх сторінках товарів.

**Траст-рядок:**
- [ ] Звичайний товар: Гарантія оригінальності · **Швидка відправка** · Telegram підтримка.
- [ ] Preorder-товар: Гарантія оригінальності · **Привеземо під замовлення** · Telegram підтримка.
- [ ] Іконки **золоті** (`--bs-gold`), текст темно-сірий.
- [ ] Мобайл: усі 3 пункти видно, текст читаємо (10.5px).

**Загальне:**
- [ ] Кнопка «У кошик» (зелена) → звичайний товар; «Передзамовити» (синя) → preorder.
- [ ] Консоль: нуль JS-помилок на `/product/<slug>`.
- [ ] Кошик / оплата / фіскалізація — не зачеплені (перевір Network: запити на `checkout/` не змінились).

---

## Не робити (out of scope)

- ❌ Не вводити ПУМБ-інтеграцію — тільки приховати.
- ❌ Не додавати нові поля в product_description або product_attribute.
- ❌ Не чіпати `checkout/`, `payment/`, фіскалізацію.
- ❌ Не змінювати логіку `cart.add()` — тільки клас і текст кнопки.
