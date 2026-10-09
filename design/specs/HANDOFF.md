# Booster Shop — Implementation Handoff
_Designed: 21.05.2026 · Recipient: Codex / dev. Built from `Booster Shop UX Audit.html` + `audit.md`._

---

## 0. How to use this document

This is the **single source of truth** for porting the approved audit into your OpenCart 4 store. It's organized into self-contained **phases**, each with:

1. **Goal** — what the phase changes for users.
2. **Files** — which OpenCart template / CSS / JS file(s) to touch.
3. **Code** — exact HTML/CSS/JS to add. Class names are stable; rename templates as your theme requires.
4. **Notes** — gotchas, accessibility, mobile.
5. **QA checklist** — manual smoke-test before merging.

You can pick phases in **any order**, but the **recommended order** is:

> Phase 1 (UX-004) → Phase 2 (UX-037) → Phase 3 (UX-036) → Phase 4a (UX-038) → Phase 4b (UX-017) → Phase 5 (TMPL) → Phase 6 (TECH-018)

**Path conventions used here:**

- `<THEME>` = your active theme key, e.g. `default`, `journal3`, etc. Real OpenCart 4 path is `catalog/view/template/<THEME>/...` for stock themes or `extension/<theme>/catalog/view/template/...` for installed themes. Adjust as needed.
- `<JS>` = `catalog/view/javascript/<THEME>/` or your theme's JS folder.
- `<CSS>` = `catalog/view/stylesheet/<THEME>/` or your theme's CSS folder.

**Master CSS file:** drop in `handoff/boostershop-ds.css` once → it powers every phase. Don't duplicate tokens or component CSS into individual templates.

**Reference mockups:** open `Booster Shop UX Audit.html` for the visual ground truth. Section numbers match this doc.

---

## Phase 0 — Setup

### 0.1 Load the design-system CSS + Manrope font

Add to your main layout (`<THEME>/common/header.twig`) inside `<head>`, **before** other site styles so component classes can be overridden if needed:

```twig
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="catalog/view/stylesheet/<THEME>/boostershop-ds.css">
```

If you'd rather inline @import — `boostershop-ds.css` already imports Manrope itself, so you only need the `<link>` to the stylesheet.

### 0.2 Add the `bs` base class to `<body>`

This scopes typography + box-sizing reset. Edit `<THEME>/common/header.twig`:

```twig
<body class="bs {{ class }}">
```

(Keep any existing `{{ class }}` so OpenCart's per-route body class still applies.)

### 0.3 Sanity-check

After cache flush:
- Fonts load (Manrope visible in DevTools → Network).
- `:root` tokens (`--bs-blue`, `--bs-green`, …) resolve in computed styles on `<body>`.

---

## Phase 1 — UX-004 · Sub-category chips + category header card

### 1.1 Goal

Replace OpenCart's default `<h1>` + sub-category icon-tiles with one **integrated header card**: brand-coloured top strip → title + count + tagline + sort → toolbar row with chips + active filter chips.

### 1.2 Files

- `<THEME>/product/category.twig` — main markup.
- `<CSS>/boostershop-ds.css` — already contains `.bs-cat-header*` classes.

### 1.3 Markup

Replace the existing category header block (everything from `<h1>` through the sort+limit row + sub-category buttons + active filter row) with:

```twig
{# data sources: $heading_title (h1), $product_total or $products|length, $description (intro paragraph) #}
{# $sub_categories is your subcategory loop; ensure each item exposes name + href + active #}

<div class="bs-cat-header{% if category.code == 'one-piece' %} bs-cat-header--onepiece{% endif %}">
  <div class="bs-cat-header__strip"></div>

  <div class="bs-cat-header__hero">
    <div>
      <div class="bs-cat-header__title">
        <h1>{{ heading_title }}</h1>
        <span class="bs-count">{{ product_total }} {{ products_total_label }}</span>
      </div>
      {% if tagline %}
        <div class="bs-cat-header__tagline">{{ tagline }}</div>
      {% endif %}
    </div>

    <div style="flex:1"></div>

    <label class="visually-hidden" for="bs-sort">{{ text_sort }}</label>
    <select id="bs-sort" class="bs-select" onchange="location = this.value;">
      {% for sort in sorts %}
        <option value="{{ sort.href }}"{% if sort.value == current_sort %} selected{% endif %}>{{ sort.text }}</option>
      {% endfor %}
    </select>
  </div>

  <div class="bs-cat-header__toolbar">
    {# Sub-category chips — none active when arriving from home/breadcrumb #}
    <nav class="bs-chip-row" aria-label="{{ text_subcategories }}">
      {% for sub in sub_categories %}
        <a href="{{ sub.href }}" class="bs-chip{% if sub.active %} bs-chip--active{% endif %}">
          {{ sub.name }}
        </a>
      {% endfor %}
    </nav>

    {# Active filter chips (only renders when at least one filter is on) #}
    {% if active_filters|length %}
      <div class="bs-filter-chips" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <span style="font-size:12.5px; color:var(--bs-ink-3);">{{ text_active }}:</span>
        {% for f in active_filters %}
          <span class="bs-filter-chip">
            {{ f.label }}
            <button class="bs-filter-chip__remove"
                    type="button"
                    aria-label="{{ text_remove }} {{ f.label }}"
                    onclick="location='{{ f.remove_url }}'">×</button>
          </span>
        {% endfor %}
        <button class="bs-filter-reset" onclick="location='{{ reset_url }}'">{{ text_reset_all }}</button>
      </div>
    {% endif %}
  </div>
</div>
```

### 1.4 OpenCart wiring (controller)

In `catalog/controller/product/category.php` (or your custom controller):
- Pass `tagline` from category `description` (strip HTML, first 1–2 sentences).
- Expose `sub_categories` as array of `{name, href, active}` — `active` is true only if the current URL contains the subcategory slug.
- Build `active_filters` from the current `$_GET['filter']` map.
- Compute `reset_url` as the category URL **without** any filter or sub-category params.

### 1.5 CSS

Already in `boostershop-ds.css`. Brand strip colour:
- Pokémon = gold (`var(--bs-pokemon)`).
- One Piece = blue (`.bs-cat-header--onepiece` override sets `var(--bs-onepiece)`).
- Generic categories (Набори, Акції) → gold by default; add a `data-accent` strategy if you add more brands later.

### 1.6 QA checklist

- [ ] Empty subcategory list → toolbar still renders sort dropdown right-aligned without break.
- [ ] No active filters → second row of toolbar is hidden.
- [ ] Mobile (≤640px) → hero stacks vertically, toolbar scrolls horizontally without clipping the sort.
- [ ] Click a chip → server re-renders with that chip `--active`.
- [ ] Reset all → all filter params removed but sort param kept (or per your behaviour spec).

---

## Phase 2 — UX-037 · Empty states

### 2.1 Goal

A single `EmptyState` partial reused in three places: empty cart, empty search results, category with 0 products.

### 2.2 File

Create `<THEME>/common/empty_state.twig`:

```twig
{# Reusable empty state.
   Inputs:
     - icon  : 'cart' | 'search' | 'box'  (svg sprite key)
     - title : string
     - desc  : string (optional, 1 short line)
     - cta_href, cta_label : optional primary action
     - hint_href, hint_label : optional secondary text link
#}

<div class="bs-empty">
  <div class="bs-empty__icon">
    {% if icon == 'cart' %}
      <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
        <path d="M8 10h5l5 26h22l5-18H14" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
        <circle cx="20" cy="42" r="2.5" fill="currentColor"/>
        <circle cx="36" cy="42" r="2.5" fill="currentColor"/>
      </svg>
    {% elseif icon == 'search' %}
      <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
        <circle cx="22" cy="22" r="14" stroke="currentColor" stroke-width="2"/>
        <path d="M33 33l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    {% else %}
      <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
        <path d="M6 16l18-8 18 8v18l-18 8-18-8V16z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
        <path d="M6 16l18 8 18-8M24 24v18" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
      </svg>
    {% endif %}
  </div>
  <h2 class="bs-empty__title">{{ title }}</h2>
  {% if desc %}<p class="bs-empty__desc">{{ desc }}</p>{% endif %}
  <div class="bs-empty__actions">
    {% if cta_href %}<a class="bs-btn bs-btn-primary" href="{{ cta_href }}">{{ cta_label }}</a>{% endif %}
    {% if hint_href %}<a class="bs-btn bs-btn-ghost" href="{{ hint_href }}">{{ hint_label }}</a>{% endif %}
  </div>
</div>
```

### 2.3 CSS

Add to `boostershop-ds.css` (append):

```css
.bs-empty {
  text-align: center;
  padding: 56px 24px;
  max-width: 480px;
  margin: 0 auto;
}
.bs-empty__icon {
  width: 72px; height: 72px;
  display: inline-flex; align-items: center; justify-content: center;
  background: var(--bs-bg); border-radius: 50%;
  color: var(--bs-ink-3);
  margin-bottom: 18px;
}
.bs-empty__title { font-size: 20px; font-weight: 700; color: var(--bs-ink); margin: 0 0 8px; }
.bs-empty__desc  { font-size: 14px; color: var(--bs-ink-3); line-height: 1.55; margin: 0 0 22px; }
.bs-empty__actions { display: inline-flex; gap: 10px; flex-wrap: wrap; justify-content: center; }
```

### 2.4 Usage points

**Cart empty (`<THEME>/checkout/cart.twig`):** wrap the existing empty branch:

```twig
{% if products|length == 0 %}
  {% include 'common/empty_state.twig' with {
    icon: 'cart',
    title: 'Кошик поки порожній',
    desc: 'Додайте кілька бустерів до кошика — почати можна з рекомендованих товарів на головній.',
    cta_href: catalog_url, cta_label: 'До каталогу',
    hint_href: faq_url,     hint_label: 'Як ми пакуємо замовлення →'
  } %}
{% else %}
  …existing cart markup…
{% endif %}
```

**Search 0 results (`<THEME>/product/search.twig`):**

```twig
{% if products|length == 0 %}
  {% include 'common/empty_state.twig' with {
    icon: 'search',
    title: ('За запитом «' ~ search ~ '» нічого не знайдено'),
    desc: 'Спробуйте уточнити написання або скоротити запит — наприклад, лише назву сету.',
    cta_href: catalog_url, cta_label: 'Перейти до каталогу'
  } %}
{% else %}
  …existing search results…
{% endif %}
```

**Category 0 products (`<THEME>/product/category.twig`):**

```twig
{% if products|length == 0 %}
  {% include 'common/empty_state.twig' with {
    icon: 'box',
    title: 'У цій категорії поки немає товарів',
    desc: 'Ми оновлюємо асортимент регулярно. Спробуйте сусідню категорію або підпишіться на Telegram-канал.',
    cta_href: telegram_url, cta_label: 'Підписатись у Telegram',
    hint_href: catalog_url, hint_label: 'Інші категорії'
  } %}
{% else %}
  …existing product loop…
{% endif %}
```

### 2.5 QA checklist

- [ ] Empty cart shows; cart-table is hidden.
- [ ] Search for `xyz123` shows search empty state.
- [ ] Disable all products in one category (admin) → category empty state shows.
- [ ] Buttons render in design-system colours.

---

## Phase 3 — UX-036 · /special: FAQ нижче товарів

### 3.1 Goal

Reorder the `/special` template so the discounted products block renders **before** the FAQ accordion. Right now the FAQ sits between the intro paragraph and the products, which blocks the funnel.

### 3.2 File

`<THEME>/product/special.twig`

### 3.3 Change

Find the existing layout. It looks roughly like:

```twig
{# OLD #}
<div class="page">
  <h1>{{ heading_title }}</h1>
  <p>{{ description }}</p>

  {% include 'common/faq.twig' %}     {# ← FAQ here #}

  <div class="product-grid">…</div>   {# ← products below #}
</div>
```

Swap to:

```twig
{# NEW — products immediately under intro, FAQ after #}
<div class="page">
  <h1>{{ heading_title }}</h1>
  <p>{{ description }}</p>

  <div class="product-grid">…</div>   {# ← products first #}

  <hr class="bs-divider" style="margin: 40px 0; border: 0; border-top: 1px solid var(--bs-line);">

  {% include 'common/faq.twig' %}     {# ← FAQ moves down #}
</div>
```

If your FAQ partial reads its category context from URL, no controller change needed. If FAQ data is injected via controller, also confirm the data still loads.

### 3.4 QA checklist

- [ ] /special: products appear before "Часті питання…" heading.
- [ ] FAQ still expands/collapses.
- [ ] No layout shift on mobile.

---

## Phase 4a — UX-038 · Sticky add-to-cart (mobile)

### 4a.1 Goal

On mobile (≤768px), the product page must surface a sticky bottom bar with `qty` + `Додати в кошик`, so the user never has to scroll back up.

### 4a.2 File

- `<THEME>/product/product.twig` — append the sticky bar at the end of `<main>`.
- `<JS>/sticky-add-to-cart.js` — small IntersectionObserver to hide the bar when the main CTA is visible.
- CSS lives in `boostershop-ds.css` (see 4a.5).

### 4a.3 Markup (append before `</main>`)

```twig
<div class="bs-sticky-atc" hidden data-product-id="{{ product_id }}">
  <div class="bs-sticky-atc__inner">
    <div class="bs-qty">
      <button type="button" class="bs-qty__btn" data-act="dec" aria-label="Зменшити">−</button>
      <input type="number" class="bs-qty__input" value="1" min="1">
      <button type="button" class="bs-qty__btn" data-act="inc" aria-label="Збільшити">+</button>
    </div>
    <button type="button" class="bs-btn bs-btn-primary bs-sticky-atc__cta" data-add-to-cart>
      Додати — ₴<span data-price>{{ price_amount }}</span>
    </button>
  </div>
</div>
```

### 4a.4 JS (`sticky-add-to-cart.js`)

```js
(function() {
  const bar = document.querySelector('.bs-sticky-atc');
  const mainCta = document.querySelector('[data-main-add-to-cart]');
  if (!bar || !mainCta) return;

  // Only show on mobile widths.
  const mql = window.matchMedia('(max-width: 768px)');
  function update(entries) {
    const visible = entries[0].isIntersecting;
    bar.hidden = visible || !mql.matches;
  }
  const io = new IntersectionObserver(update, { rootMargin: '0px 0px -50px 0px' });
  io.observe(mainCta);
  mql.addEventListener('change', () => bar.hidden = !mql.matches);

  // Qty +/-
  bar.addEventListener('click', (e) => {
    const t = e.target.closest('[data-act]'); if (!t) return;
    const input = bar.querySelector('.bs-qty__input');
    let v = parseInt(input.value, 10) || 1;
    v = t.dataset.act === 'inc' ? v + 1 : Math.max(1, v - 1);
    input.value = v;
  });

  // Add-to-cart triggers the same handler as the main CTA — wire to your existing AJAX.
  bar.querySelector('[data-add-to-cart]').addEventListener('click', () => {
    const qty = bar.querySelector('.bs-qty__input').value;
    // Re-use existing cart.add or whatever fn the main CTA calls:
    if (typeof cart !== 'undefined' && cart.add) {
      cart.add(bar.dataset.productId, qty);
    } else {
      mainCta.click();
    }
  });
})();
```

Also mark your main CTA with `data-main-add-to-cart` so the IntersectionObserver can find it. The pattern uses your existing AJAX, not a custom request — so checkout/payment stays untouched.

### 4a.5 CSS (append to `boostershop-ds.css`)

```css
.bs-sticky-atc {
  position: fixed; inset: auto 8px 8px; z-index: 50;
  background: #fff;
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r);
  box-shadow: var(--bs-sh-pop);
  padding: 10px;
}
.bs-sticky-atc__inner { display: flex; gap: 10px; align-items: center; }
.bs-sticky-atc__cta { flex: 1; }

.bs-qty {
  display: inline-flex; align-items: center;
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r-sm);
}
.bs-qty__btn {
  width: 38px; height: 44px;
  border: 0; background: transparent;
  color: var(--bs-ink-2); cursor: pointer;
  font-size: 16px; font-weight: 600;
}
.bs-qty__input {
  width: 38px; height: 44px;
  border: 0; background: transparent;
  text-align: center; font-weight: 700; font-size: 14px;
  color: var(--bs-ink); outline: none;
  -moz-appearance: textfield;
}
.bs-qty__input::-webkit-outer-spin-button,
.bs-qty__input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
```

### 4a.6 QA checklist

- [ ] Open product page on phone → see sticky bar at bottom.
- [ ] Scroll up to main CTA → sticky bar disappears.
- [ ] Tap + or − → quantity updates.
- [ ] Tap "Додати" → product appears in cart (uses existing AJAX path).
- [ ] Desktop → sticky bar never shows.

---

## Phase 4b — UX-017 · Transactional emails

### 4b.1 Goal

Replace OpenCart's default transactional email layout (yellow header / blue links) with the DS palette: white background, `--bs-ink` headings, blue links, **green CTA reserved for purchase confirmations only**.

### 4b.2 Files

- `<THEME>/mail/order_alert.twig` (admin notice — restyle only.)
- `<THEME>/mail/order.twig` (customer order confirmation — full re-skin.)
- Any other `mail/*.twig` that contains visual blocks.

### 4b.3 Boilerplate

Top-level email shell (use the same structure across all mails):

```html
<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{ subject }}</title>
  <style>
    body { margin: 0; background: #F7F7F5; font-family: 'Manrope', system-ui, sans-serif; color: #1F2937; }
    .wrap { max-width: 600px; margin: 0 auto; padding: 24px 16px; }
    .card { background: #fff; border: 1px solid #E5E7EB; border-radius: 14px; padding: 28px; }
    h1, h2, h3 { color: #111827; margin: 0 0 12px; }
    h1 { font-size: 22px; font-weight: 800; letter-spacing: -0.015em; }
    a  { color: #1E3A8A; }
    .btn { display: inline-block; background: #16A34A; color: #fff !important;
           padding: 12px 18px; border-radius: 8px; font-weight: 700; text-decoration: none; }
    .muted { color: #6B7280; font-size: 13px; }
    table { width: 100%; border-collapse: collapse; }
    table td { padding: 10px 0; border-bottom: 1px solid #EEF0F2; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Замовлення #{{ order_id }} прийнято</h1>
      <p>Дякуємо! Ми вже почали збирати ваше замовлення.</p>
      …items table…
      <a class="btn" href="{{ order_url }}">Переглянути замовлення →</a>
    </div>
    <p class="muted" style="text-align:center; margin-top:16px;">
      Booster Shop · <a href="{{ telegram_url }}">Telegram</a> · <a href="{{ store_url }}">{{ store_name }}</a>
    </p>
  </div>
</body>
</html>
```

### 4b.4 Rules

- Single green CTA per email (the one taking user to their order or to checkout/repeat).
- All other links → blue text-only.
- Status changes (shipped, delivered) → use blue-soft alert card, not green.
- Don't inline gold accent for status; gold is reserved for hero/category in retail.

### 4b.5 QA checklist

- [ ] Customer order confirmation renders correctly in Gmail web + iOS Mail.
- [ ] Buttons clickable (no fancy `display:flex` — emails hate it).
- [ ] No external CSS — everything inline `<style>` or attribute.
- [ ] Cyrillic renders (charset declared).

---

## Phase 5 — TMPL · Twig templates

This is the big one. Each subsection has the markup + class mappings. Class behaviour is already in `boostershop-ds.css`.

> Reference: open `Booster Shop UX Audit.html` to see exact intended look. Phase 5 mirrors the artboards 1:1.

### 5.1 Header (V1 Minimal white)

`<THEME>/common/header.twig` — replace the entire visible header section.

```twig
<header class="bs-header">
  <div class="bs-header__inner">
    <a href="{{ home }}" class="bs-header__logo">
      {{ logo_svg|raw }}  {# inline brand SVG #}
    </a>

    <form class="bs-search" action="{{ search_action }}" method="get">
      <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="color:#6B7280;flex:0 0 auto;">
        <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.6"/>
        <path d="M14 14l4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
      </svg>
      <input class="bs-search__input" type="search" name="search"
             placeholder="Пошук бустерів, сетів, виробників…"
             value="{{ search_query }}">
    </form>

    <a href="{{ account_url }}" class="bs-btn bs-btn-ghost bs-btn-sm">
      <svg width="16" height="16" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="7" r="3.2" stroke="currentColor" stroke-width="1.6"/><path d="M3.5 17c.7-3.3 3.4-5 6.5-5s5.8 1.7 6.5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      Кабінет
    </a>
    <a href="{{ telegram_url }}" class="bs-btn bs-btn-ghost bs-btn-sm">Telegram</a>

    <a href="{{ cart_url }}" class="bs-btn bs-btn-primary bs-btn-sm">
      <svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M3 4h2l2 10h9l2-7H6" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"/><circle cx="8" cy="17" r="1.2" fill="currentColor"/><circle cx="15" cy="17" r="1.2" fill="currentColor"/></svg>
      <span style="font-weight:700;">{{ cart_count }}</span>
      <span style="font-weight:500; opacity:0.88;">· ₴{{ cart_total }}</span>
    </a>
  </div>
</header>
```

CSS (append to `boostershop-ds.css`):

```css
.bs-header { background: #fff; border-bottom: 1px solid var(--bs-line); padding: 14px 32px; }
.bs-header__inner {
  display: flex; align-items: center; gap: 24px;
  max-width: 1240px; margin: 0 auto;
}
.bs-header__logo { display: inline-flex; flex: 0 0 auto; }
.bs-search {
  flex: 1;
  display: flex; align-items: center; gap: 10px;
  background: var(--bs-bg); border: 1px solid var(--bs-line);
  border-radius: var(--bs-r-sm);
  padding: 8px 12px 8px 14px;
}
.bs-search__input {
  flex: 1; border: 0; background: transparent; outline: none;
  font: inherit; font-size: 14px; color: var(--bs-ink);
}
@media (max-width: 768px) {
  .bs-header { padding: 10px 14px; }
  .bs-header__inner { gap: 10px; }
  .bs-header .bs-btn-ghost { display: none; }   /* Кабінет/Telegram fold into menu */
}
```

**Note:** the search bar `flex:1` (no max-width) — this is the round-5 fix that removes the empty gap between search and cart.

### 5.2 Footer

`<THEME>/common/footer.twig`:

```twig
<footer class="bs-footer">
  <div class="bs-footer__inner">
    <div class="bs-footer__brand">
      {{ logo_white_svg|raw }}
      <p>Оригінальні sealed-бустери Pokémon TCG та One Piece Card Game з Японії та Кореї. Без зважування й сортування.</p>
      <p class="bs-footer__legal">ФОП Леусенко Євгеній Андрійович</p>
    </div>

    <div class="bs-footer__col">
      <h4>Каталог</h4>
      <a href="{{ url_pokemon }}">Pokémon TCG</a>
      <a href="{{ url_onepiece }}">One Piece Card Game</a>
      <a href="{{ url_special }}">Акції</a>
    </div>
    <div class="bs-footer__col">
      <h4>Інформація</h4>
      <a href="{{ url_shipping }}">Оплата і доставка</a>
      <a href="{{ url_return }}">Обмін і повернення</a>
      <a href="{{ url_offer }}">Публічна оферта</a>
    </div>
    <div class="bs-footer__col">
      <h4>Покупцю</h4>
      <a href="{{ url_guarantee }}">Гарантія оригінальності</a>
      <a href="{{ url_about }}">Про магазин</a>
      <a href="{{ url_telegram }}">Telegram-канал</a>
    </div>
    <div class="bs-footer__col">
      <h4>Контакти</h4>
      <a href="{{ telegram_support_url }}">@boostershop_support</a>
      <a href="mailto:{{ email }}">{{ email }}</a>
      <a href="tel:{{ phone }}">{{ phone }}</a>
    </div>
  </div>

  <div class="bs-footer__bottom">
    <span>© 2026 Booster Shop. Усі права захищено.</span>
    <span>Графік підтримки: 10:00–20:00 (UA)</span>
  </div>
</footer>
```

CSS (append):

```css
.bs-footer {
  background: #0F1115; color: #9CA3AF;
  padding: 40px 32px 24px;
}
.bs-footer__inner {
  max-width: 1240px; margin: 0 auto;
  display: grid;
  grid-template-columns: 1.4fr 1fr 1fr 1fr 1.1fr;
  gap: 32px;
  margin-bottom: 32px;
}
.bs-footer__brand p { font-size: 13px; line-height: 1.6; max-width: 280px; }
.bs-footer__legal  { color: #6B7280; font-size: 12.5px; }
.bs-footer__col h4 {
  font-size: 12px; font-weight: 700; color: #F3F4F6;
  text-transform: uppercase; letter-spacing: .1em; margin: 0 0 14px;
}
.bs-footer__col a {
  display: block; color: #9CA3AF; font-size: 13px; text-decoration: none;
  margin-bottom: 10px;
}
.bs-footer__col a:hover { color: #fff; }
.bs-footer__bottom {
  max-width: 1240px; margin: 0 auto;
  border-top: 1px solid #1F2937; padding-top: 18px;
  display: flex; justify-content: space-between; font-size: 12px; color: #6B7280;
}
@media (max-width: 768px) {
  .bs-footer__inner { grid-template-columns: 1fr 1fr; }
  .bs-footer__brand { grid-column: 1 / -1; }
}
```

### 5.3 Product card (used in category / home / related / search)

`<THEME>/common/product_card.twig` (new):

```twig
{# Inputs: product = { id, name, href, image, price, old_price?, state? } #}
{# state in: '', 'low-pull', 'preorder', 'out' #}

{% set is_out = product.state == 'out' %}
{% set is_pre = product.state == 'preorder' %}
{% set discount = product.old_price and product.old_price > product.price
   ? ((1 - product.price / product.old_price) * 100)|round
   : 0 %}

<article class="bs-pcard{% if is_out %} bs-pcard--out{% endif %}">
  <a class="bs-pcard__media" href="{{ product.href }}">
    <img src="{{ product.image }}" alt="{{ product.name }}" loading="lazy">

    {% if discount > 0 and not is_out %}
      <span class="bs-pcard__badge-tr">
        <span class="bs-badge bs-badge--discount">−{{ discount }}%</span>
      </span>
    {% endif %}

    {% if product.state == 'low-pull' %}
      <span class="bs-pcard__badge-tl">
        <span class="bs-badge bs-badge--lowpull">Low Pull</span>
      </span>
    {% elseif product.state == 'preorder' %}
      <span class="bs-pcard__badge-tl">
        <span class="bs-badge bs-badge--preorder">Передзамовлення</span>
      </span>
    {% elseif product.state == 'out' %}
      <span class="bs-pcard__badge-tl">
        <span class="bs-badge bs-badge--out">Немає в наявності</span>
      </span>
    {% endif %}
  </a>

  <div class="bs-pcard__body">
    <h4 class="bs-pcard__title"><a href="{{ product.href }}">{{ product.name }}</a></h4>

    <div class="bs-pcard__price-row{% if product.old_price %} bs-pcard__price-row--sale{% endif %}">
      <span class="bs-pcard__price-new">₴{{ product.price|number_format(0, '.', '') }}</span>
      {% if product.old_price %}
        <span class="bs-pcard__price-old">₴{{ product.old_price|number_format(0, '.', '') }}</span>
      {% endif %}
    </div>

    {% if is_out %}
      <button type="button" class="bs-btn bs-btn-secondary" data-notify="{{ product.id }}">
        Очікую надходження
      </button>
    {% elseif is_pre %}
      <button type="button" class="bs-btn bs-btn-preorder" data-add="{{ product.id }}">
        Передзамовити
      </button>
    {% else %}
      <button type="button" class="bs-btn bs-btn-primary" data-add="{{ product.id }}">
        Купити
      </button>
    {% endif %}
  </div>
</article>
```

Include from category/home/search: `{% include 'common/product_card.twig' with { product: p } %}` inside the existing product loop.

**Replacing per state**: ensure your controller sets `product.state` based on:
- Stock 0 → `'out'`
- Stock < 0 / status flag → `'preorder'`
- Manual tag → `'low-pull'`
- Else → empty string (sealed = default, no badge).

### 5.4 Product page

Full structure in `Booster Shop UX Audit.html` section 4. Key blocks to add (around existing OpenCart product.twig):

```twig
{# Breadcrumb already exists — keep it #}

<div class="bs-pp">
  <div class="bs-pp__gallery">…OpenCart's gallery, with new thumbnail strip…</div>

  <div class="bs-pp__summary">
    <div class="bs-pp__meta">{{ category_path }} · Japanese Edition</div>
    <h1>{{ heading_title }}</h1>

    <div class="bs-pp__reviews-line">
      <div class="bs-stars" aria-label="Рейтинг 4.8 з 5">
        {# 5 gold stars #}
      </div>
      <span>4.8 · <a href="#reviews">відгуки в Telegram / OLX</a></span>
    </div>

    <div class="bs-pp__stock">
      <span class="bs-dot"></span> В наявності · {{ stock_qty }} шт.
    </div>

    <div class="bs-pp__price">
      <span class="bs-pp__price-new">₴{{ price }}</span>
      {% if old_price %}<span class="bs-pp__price-old">₴{{ old_price }}</span>{% endif %}
    </div>

    <div class="bs-pp__atc">
      <div class="bs-qty">…+/− input…</div>
      <button type="button" class="bs-btn bs-btn-primary" data-main-add-to-cart>
        Додати в кошик
      </button>
    </div>

    {# PUMB instalments placeholder (UX future) #}
    <button type="button" class="bs-pumb">
      <span class="bs-pumb__chip">ПУМБ</span>
      <span class="bs-pumb__text">
        <strong>Оплата частинами</strong> — від ₴50/міс · до 4 платежів без відсотків
      </span>
      <span class="bs-pumb__chev">›</span>
    </button>

    <ul class="bs-pp__trust">
      <li>Sealed з box/case</li>
      <li>Не зважуємо</li>
      <li>~3 дні Новою поштою</li>
    </ul>
  </div>
</div>

{# Tabs: Опис / Характеристики / Відгуки #}
<div class="bs-tabs" data-tabs>
  <nav class="bs-tabs__nav">
    <button class="bs-tabs__tab is-active" data-tab="desc">Опис</button>
    <button class="bs-tabs__tab" data-tab="specs">Характеристики</button>
    <button class="bs-tabs__tab" data-tab="reviews" id="reviews">Відгуки</button>
  </nav>
  <div class="bs-tabs__panel is-active" data-panel="desc">{{ description }}</div>
  <div class="bs-tabs__panel" data-panel="specs">…attribute table…</div>
  <div class="bs-tabs__panel" data-panel="reviews">
    {% include 'common/reviews_external.twig' %}
  </div>
</div>

{% include 'common/faq.twig' %}

{# Related products grid — uses common/product_card.twig partial #}
```

CSS (append):

```css
.bs-pp { display: grid; grid-template-columns: 1.1fr 1fr; gap: 32px; margin-bottom: 32px; }
.bs-pp__meta { font: 11px/1 'JetBrains Mono', ui-monospace, monospace;
               letter-spacing: .14em; color: var(--bs-ink-3);
               text-transform: uppercase; margin-bottom: 8px; }
.bs-pp__reviews-line { display: flex; align-items: center; gap: 12px;
                       margin-top: 10px; font-size: 13px; color: var(--bs-ink-3); }
.bs-pp__stock { display: inline-flex; align-items: center; gap: 8px;
                font-size: 13px; color: var(--bs-green); font-weight: 600; margin: 18px 0; }
.bs-pp__stock .bs-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--bs-green); }

.bs-pp__price { padding: 14px 0; border-block: 1px solid var(--bs-line); margin-bottom: 18px; }
.bs-pp__price-new { font-size: 24px; font-weight: 800; color: var(--bs-ink); letter-spacing: -0.01em; }
.bs-pp__price-old { margin-left: 12px; font-size: 14px; color: var(--bs-ink-4); text-decoration: line-through; }

.bs-pp__atc { display: flex; align-items: center; gap: 12px; }
.bs-pp__atc .bs-btn { flex: 1; height: 52px; font-size: 15px; }

.bs-pumb {
  display: flex; align-items: center; gap: 12px;
  width: 100%; padding: 12px 14px;
  margin-top: 14px;
  background: #fff;
  border: 1px dashed var(--bs-line);
  border-radius: var(--bs-r-sm);
  cursor: pointer; text-align: left;
  font: inherit; color: var(--bs-ink-2);
}
.bs-pumb__chip {
  background: #7C3AED; color: #fff;
  padding: 4px 8px; border-radius: 4px;
  font-weight: 900; font-size: 13px; letter-spacing: -0.04em;
}
.bs-pumb__text { flex: 1; font-size: 13px; line-height: 1.4; }
.bs-pumb__text strong { color: var(--bs-ink); }
.bs-pumb__chev { color: var(--bs-ink-3); }

.bs-pp__trust {
  list-style: none; padding: 14px 18px; margin: 18px 0 0;
  background: var(--bs-bg); border-radius: var(--bs-r);
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
  font-size: 12px; color: var(--bs-ink-2); font-weight: 500;
}

/* Tabs */
.bs-tabs { background: #fff; border: 1px solid var(--bs-line); border-radius: var(--bs-r); }
.bs-tabs__nav { display: flex; border-bottom: 1px solid var(--bs-line); padding: 0 24px; }
.bs-tabs__tab {
  padding: 16px 20px 14px; border: 0; background: transparent;
  color: var(--bs-ink-3); font: inherit; font-size: 14px; font-weight: 700; cursor: pointer;
  border-bottom: 2px solid transparent; margin-bottom: -1px;
}
.bs-tabs__tab.is-active { color: var(--bs-ink); border-bottom-color: var(--bs-blue); }
.bs-tabs__panel { display: none; padding: 24px; line-height: 1.65; }
.bs-tabs__panel.is-active { display: block; }

@media (max-width: 768px) {
  .bs-pp { grid-template-columns: 1fr; }
  .bs-pp__atc { flex-wrap: wrap; }
  .bs-pp__trust { grid-template-columns: 1fr; }
}
```

Tab JS (`<JS>/tabs.js`):

```js
document.querySelectorAll('[data-tabs]').forEach(root => {
  root.querySelectorAll('[data-tab]').forEach(tab => {
    tab.addEventListener('click', () => {
      const id = tab.dataset.tab;
      root.querySelectorAll('[data-tab]').forEach(t => t.classList.toggle('is-active', t === tab));
      root.querySelectorAll('[data-panel]').forEach(p => p.classList.toggle('is-active', p.dataset.panel === id));
    });
  });
});
```

External reviews partial (`<THEME>/common/reviews_external.twig`):

```twig
<p class="bs-pp__intro">
  Відгуки про Booster Shop клієнти залишають у наших зовнішніх каналах, де вже склалася жива спільнота.
</p>
<div class="bs-reviews-cards">
  <a class="bs-reviews-card" href="{{ telegram_reviews_url }}">
    <span class="bs-reviews-card__icon" style="background:#229ED9">📨</span>
    <div>
      <div class="bs-reviews-card__title">Telegram-група відгуків</div>
      <div class="bs-reviews-card__sub">@boostershop_reviews</div>
    </div>
    <span class="bs-reviews-card__cta">Відкрити Telegram →</span>
  </a>
  <a class="bs-reviews-card" href="{{ olx_profile_url }}">
    <span class="bs-reviews-card__icon" style="background:#002F34;color:#A1FF54;font-weight:800;">OLX</span>
    <div>
      <div class="bs-reviews-card__title">Профіль на OLX</div>
      <div class="bs-reviews-card__sub">рейтинг 4.9 · 84 відгуки</div>
    </div>
    <span class="bs-reviews-card__cta">Відкрити OLX →</span>
  </a>
</div>
```

```css
.bs-reviews-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.bs-reviews-card  {
  display: grid; grid-template-columns: 38px 1fr auto; gap: 12px;
  padding: 18px; border-radius: var(--bs-r);
  border: 1px solid var(--bs-line); background: #fff;
  color: var(--bs-ink); text-decoration: none; align-items: center;
}
.bs-reviews-card:hover { border-color: var(--bs-blue); }
.bs-reviews-card__icon {
  width: 38px; height: 38px; border-radius: 10px;
  display: inline-flex; align-items: center; justify-content: center; color: #fff;
}
.bs-reviews-card__title { font-size: 14px; font-weight: 700; color: var(--bs-ink); }
.bs-reviews-card__sub   { font-size: 12px; color: var(--bs-ink-3); }
.bs-reviews-card__cta   { font-size: 12.5px; font-weight: 600; color: var(--bs-blue); }
@media (max-width: 640px) { .bs-reviews-cards { grid-template-columns: 1fr; } }
```

### 5.5 Cart page

`<THEME>/checkout/cart.twig`. See `Booster Shop UX Audit.html` section 6 for visual reference. Replace the existing `<table>` with card-rows + summary aside:

```twig
<div class="bs-cart">
  <section class="bs-cart__list">
    {% for item in products %}
      <article class="bs-card bs-cart__row">
        <a href="{{ item.href }}" class="bs-cart__thumb">
          <img src="{{ item.thumb }}" alt="">
        </a>
        <div class="bs-cart__info">
          <a href="{{ item.href }}" class="bs-cart__name">{{ item.name }}</a>
          <div class="bs-cart__meta">Артикул: {{ item.sku }} · Japanese · Sealed</div>
          <button type="button" class="bs-cart__remove" data-remove="{{ item.cart_id }}">Видалити</button>
        </div>
        <div class="bs-qty"><!-- +/- like sticky-atc --></div>
        <div class="bs-cart__price">
          <div class="bs-cart__total">₴{{ item.total }}</div>
          <div class="bs-cart__unit">₴{{ item.price }} × {{ item.qty }}</div>
        </div>
      </article>
    {% endfor %}
  </section>

  <aside class="bs-card bs-cart__summary">
    <h3>Підсумок замовлення</h3>

    <div class="bs-cart__progress" data-progress="{{ progress_pct }}">
      <div class="bs-cart__progress-text">
        {% if subtotal >= 1500 %}Безкоштовна доставка застосована
        {% else %}До безкоштовної доставки лишилось ₴{{ 1500 - subtotal }}
        {% endif %}
      </div>
      <div class="bs-cart__progress-bar"><i style="width:{{ progress_pct }}%"></i></div>
    </div>

    <div class="bs-cart__line"><span>Сума товарів</span><span>₴{{ subtotal }}</span></div>
    <div class="bs-cart__line">
      <span>Доставка</span>
      <span class="{% if subtotal >= 1500 %}bs-ok{% endif %}">
        {% if subtotal >= 1500 %}За наш кошт{% else %}За тарифами Нової Пошти{% endif %}
      </span>
    </div>

    <div class="bs-cart__total-line">
      <span>До сплати</span><span>₴{{ subtotal }}</span>
    </div>

    <div class="bs-cart__promo">
      <label class="bs-field-label">Промокод</label>
      <div style="display:flex; gap:8px;">
        <input class="bs-input" type="text" name="coupon">
        <button class="bs-btn bs-btn-blue-outline">Застосувати</button>
      </div>
    </div>

    <a class="bs-btn bs-btn-primary" href="{{ checkout }}" style="width:100%; height:48px;">
      Оформити замовлення →
    </a>
    <p class="bs-cart__legal">
      Натискаючи кнопку, ви погоджуєтесь з <a href="{{ offer }}">Публічною офертою</a>.
    </p>
  </aside>
</div>
```

CSS:

```css
.bs-cart { display: grid; grid-template-columns: 1.6fr 1fr; gap: 24px; align-items: flex-start; }
.bs-cart__list { display: flex; flex-direction: column; gap: 12px; }
.bs-cart__row {
  display: grid; grid-template-columns: 92px 1fr auto auto; gap: 18px;
  align-items: center; padding: 14px;
}
.bs-cart__thumb { display: block; width: 92px; height: 92px;
                  border: 1px solid var(--bs-line); border-radius: var(--bs-r-sm); overflow: hidden; }
.bs-cart__thumb img { width: 100%; height: 100%; object-fit: contain; }
.bs-cart__info { min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.bs-cart__name { font-size: 14.5px; font-weight: 600; color: var(--bs-ink); }
.bs-cart__meta { font-size: 12px; color: var(--bs-ink-3); }
.bs-cart__remove { background: 0; border: 0; padding: 0;
                   color: var(--bs-ink-3); cursor: pointer; font-size: 12px;
                   text-decoration: underline; text-underline-offset: 3px; width: fit-content; }
.bs-cart__price { text-align: right; }
.bs-cart__total { font-size: 16px; font-weight: 800; color: var(--bs-ink); }
.bs-cart__unit  { font-size: 12px; color: var(--bs-ink-3); margin-top: 2px; }

.bs-cart__summary { padding: 22px; position: sticky; top: 16px; }
.bs-cart__progress { padding: 12px 14px; border-radius: var(--bs-r-sm);
                     background: var(--bs-blue-soft); margin-bottom: 16px; }
.bs-cart__progress-text { font-size: 12.5px; color: var(--bs-blue); font-weight: 600; margin-bottom: 8px; }
.bs-cart__progress-bar  { height: 4px; background: #fff; border-radius: 999px; overflow: hidden; }
.bs-cart__progress-bar i { display: block; height: 100%; background: var(--bs-blue); border-radius: 999px; }
.bs-cart__line { display: flex; justify-content: space-between; font-size: 13.5px; padding: 4px 0; }
.bs-cart__line .bs-ok { color: var(--bs-green); font-weight: 700; }
.bs-cart__total-line {
  border-top: 1px solid var(--bs-line); margin-top: 16px; padding-top: 16px;
  display: flex; justify-content: space-between;
  font-size: 18px; font-weight: 800; color: var(--bs-ink);
}
.bs-cart__promo { margin-top: 16px; }
.bs-cart__legal { font-size: 11.5px; color: var(--bs-ink-3); margin-top: 10px; text-align: center; line-height: 1.5; }
.bs-cart__legal a { color: var(--bs-blue); }
@media (max-width: 768px) {
  .bs-cart { grid-template-columns: 1fr; }
  .bs-cart__row { grid-template-columns: 56px 1fr; row-gap: 10px; }
  .bs-cart__row .bs-qty, .bs-cart__row .bs-cart__price { grid-column: 1 / -1; }
}
```

### 5.6 Mini-cart

Update your existing mini-cart trigger AJAX to render this drawer markup. See section 6 in canvas.

(Detailed markup mirrors `minicart.jsx` in the audit project — same class names as `.bs-cart__*` apply. Use a right-side `position:fixed; right:0; top:0; bottom:0; width: 400px;` drawer with overlay. Empty state: include `common/empty_state.twig` with `icon:'cart'`.)

### 5.7 Checkout

**Warning:** custom checkout + Hutko + Checkbox + НП module — don't touch payment flow. Just re-skin sections to match `Booster Shop UX Audit.html` section 7. Specifically:

- Replace the red "Маєш акаунт?" banner with `.bs-auth-nudge` (blue-soft, see CSS below).
- Apply `.bs-card` wrapper to each section (Отримувач / Адреса / Метод доставки / Спосіб оплати / Коментар / Замовлення / Промокод).
- Replace shipping/payment radios with `.bs-radio-row` (blue-soft active).
- Apply `.bs-input` / `.bs-select` / `.bs-textarea` to all form fields.
- Disclaimer + checkbox inside summary aside.

```css
.bs-auth-nudge {
  display: flex; align-items: center; gap: 14px;
  padding: 12px 16px;
  background: var(--bs-blue-soft);
  border: 1px solid #c7d2fe;
  border-radius: var(--bs-r-sm);
  font-size: 13.5px; color: var(--bs-ink-2);
}

.bs-radio-row {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 16px;
  border: 1.5px solid var(--bs-line);
  background: #fff;
  border-radius: var(--bs-r-sm); cursor: pointer;
  transition: background .15s, border-color .15s;
}
.bs-radio-row:hover { border-color: var(--bs-ink-3); }
.bs-radio-row.is-active {
  border-color: var(--bs-blue);
  background: var(--bs-blue-soft);
}
.bs-radio-row input[type="radio"] { accent-color: var(--bs-blue); }
```

### 5.8 Account pages

Markup follows `account-pages.jsx` 1:1. Key class names:
- `.bs-account-shell` — 260px sidebar + content grid.
- `.bs-account-nav` — sidebar nav (use `.bs-chip--active` style for current item).
- `.bs-order-card` — order list card (status pill + ТТН line + Details button).
- `.bs-order-status--{processing|shipped|delivered|cancelled}` — pill colour.
- `.bs-address-card` — address card with `Default` pill + edit/delete actions.
- Use `.bs-card`, `.bs-input`, `.bs-btn-*` for inner forms (info, password).

Skip the OpenCart default `<h2>Account Menu</h2>` heading; replace with the small "Особистий кабінет / Євгеній Леусенко / email" stack as in mockup.

### 5.9 FAQ accordion (Variant B + chevron)

`<THEME>/common/faq.twig`:

```twig
<section class="bs-faq" itemscope itemtype="https://schema.org/FAQPage">
  <header class="bs-faq__header">
    <h3>Часті питання</h3>
    <span class="bs-faq__count">{{ faq|length }} відповідей</span>
  </header>

  <div class="bs-faq__list">
    {% for q in faq %}
      <article class="bs-faq__item" itemprop="mainEntity" itemscope itemtype="https://schema.org/Question">
        <button type="button" class="bs-faq__q" aria-expanded="false" aria-controls="faq-a-{{ loop.index }}">
          <span itemprop="name">{{ q.question }}</span>
          <svg class="bs-faq__chev" width="14" height="14" viewBox="0 0 14 14" fill="none">
            <path d="M3 5l4 4 4-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <div class="bs-faq__a" id="faq-a-{{ loop.index }}" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer" hidden>
          <div itemprop="text">{{ q.answer|raw }}</div>
        </div>
      </article>
    {% endfor %}
  </div>
</section>
```

CSS:

```css
.bs-faq__header { display: flex; align-items: baseline; gap: 14px; margin-bottom: 16px; }
.bs-faq__count  { font-size: 13px; color: var(--bs-ink-3); }
.bs-faq__list   { display: flex; flex-direction: column; gap: 8px; }
.bs-faq__item   {
  background: #fff; border: 1px solid var(--bs-line);
  border-radius: 12px; transition: border-color .2s;
}
.bs-faq__item.is-open { border-color: var(--bs-blue); }
.bs-faq__q {
  width: 100%; padding: 18px 20px; cursor: pointer;
  display: flex; align-items: center; gap: 16px;
  background: 0; border: 0; text-align: left; font: inherit;
  color: var(--bs-ink); font-size: 15.5px; font-weight: 600;
  line-height: 1.45;
}
.bs-faq__chev {
  flex: 0 0 auto;
  color: var(--bs-ink-3);
  transition: transform .3s, color .2s;
}
.bs-faq__item.is-open .bs-faq__chev {
  transform: rotate(180deg);
  color: var(--bs-blue);
}
.bs-faq__a { padding: 0 64px 20px 20px;
             font-size: 14.5px; line-height: 1.65; color: var(--bs-ink-2);
             border-top: 1px solid var(--bs-line-2); padding-top: 16px; }
```

JS:

```js
document.querySelectorAll('.bs-faq__q').forEach(btn => {
  btn.addEventListener('click', () => {
    const item = btn.closest('.bs-faq__item');
    const answer = item.querySelector('.bs-faq__a');
    const isOpen = item.classList.toggle('is-open');
    btn.setAttribute('aria-expanded', String(isOpen));
    answer.hidden = !isOpen;
  });
});
```

**Schema.org:** `itemprop` markup above is structured-data-friendly (FAQPage). Per audit constraints, **do not auto-add FAQPage JSON-LD** without a separate ticket; itemprop attributes don't auto-promote to rich results unless validated.

### 5.10 Home category tiles (Variant D)

`<THEME>/common/home_tiles.twig`:

```twig
<section class="bs-home-tiles">
  {% for c in home_categories %}
    <a class="bs-home-tile{% if c.code == 'one-piece' %} bs-home-tile--onepiece{% endif %}" href="{{ c.href }}">
      <div class="bs-home-tile__strip"></div>
      <div class="bs-home-tile__body">
        <div class="bs-home-tile__logo" style="background-image:url('{{ c.logo }}');"></div>
        <div class="bs-home-tile__info">
          <h3>{{ c.name }}</h3>
          <p>{{ c.tagline }}</p>
          <div class="bs-home-tile__cta">
            <span class="bs-home-tile__count">{{ c.count }} {{ c.count_label }}</span>
            <span class="bs-home-tile__arrow">Переглянути →</span>
          </div>
        </div>
      </div>
    </a>
  {% endfor %}
</section>
```

CSS:

```css
.bs-home-tiles { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 40px; }
.bs-home-tile  {
  background: #fff; border: 1px solid var(--bs-line);
  border-radius: var(--bs-r-lg); overflow: hidden;
  color: var(--bs-ink); text-decoration: none;
  display: flex; flex-direction: column;
  transition: border-color .15s;
}
.bs-home-tile:hover { border-color: var(--bs-ink-3); }
.bs-home-tile__strip { height: 6px; background: var(--bs-pokemon); }
.bs-home-tile--onepiece .bs-home-tile__strip { background: var(--bs-onepiece); }
.bs-home-tile__body { padding: 24px; display: flex; align-items: center; gap: 18px; }
.bs-home-tile__logo {
  width: 110px; height: 110px;
  border-radius: var(--bs-r);
  background-size: contain; background-repeat: no-repeat; background-position: center;
  background-color: var(--bs-bg);
  flex: 0 0 auto;
}
.bs-home-tile__info { flex: 1; }
.bs-home-tile__info h3 { font-size: 18px; margin: 0; }
.bs-home-tile__info p { font-size: 13px; color: var(--bs-ink-3); margin: 6px 0 10px; line-height: 1.5; }
.bs-home-tile__cta { display: flex; align-items: center; gap: 14px; }
.bs-home-tile__count { font-size: 12px; color: var(--bs-ink-3); }
.bs-home-tile__arrow { font-size: 13px; font-weight: 600; color: var(--bs-blue); }
@media (max-width: 640px) {
  .bs-home-tiles { grid-template-columns: 1fr; }
  .bs-home-tile__body { gap: 14px; padding: 18px; }
  .bs-home-tile__logo { width: 80px; height: 80px; }
}
```

Controller wiring: pass `home_categories` array with `{name, href, code, logo, tagline, count, count_label}`. Use the real brand-logo image you've already uploaded (per TECH-027, logo lives in `/image/brand/`, not in a category folder).

---

## Phase 6 — TECH-018 · Microsoft Clarity

Clarity is already installed. After Phase 5 deploys, **check these heatmaps + recordings** within 2 weeks:

1. **Header cart pill** — confirm new cart button click-through doesn't drop after width change (5.1 widened search).
2. **Category header card** — heatmap clicks on chips vs. sort dropdown.
3. **Sticky add-to-cart (mobile)** — scroll-depth at which it appears, click-through.
4. **PUMB block on product** — clicks (signal: real demand before module integration).
5. **External reviews block** — outbound clicks to Telegram + OLX.
6. **/special FAQ position** — scroll depth after Phase 3 reorder; users should now reach products faster.
7. **Mini-cart drawer** — task completion (added → opened drawer → went to checkout).

Build a Notion table with these 7 cards, screenshot Clarity findings into it, decide Phase 7 from data.

---

## QA — global checklist

Run before each deploy:

- [ ] CSS file loaded; no FOUC.
- [ ] No console errors on home / category / product / cart / checkout / account.
- [ ] Mobile (iPhone SE 375 width) — every page is usable; no horizontal scroll.
- [ ] Cart total → checkout total match exactly. (Don't touch the payment flow!)
- [ ] OpenCart admin order email = the new template (Phase 4b).
- [ ] Microsoft Clarity firing on every page (Network → `clarity.ms`).

---

## Constraints recap

- ❌ Don't reintroduce gold-foam header.
- ❌ Don't use green outside purchase actions.
- ❌ Don't add FAQPage JSON-LD (separate ticket).
- ❌ Don't change checkout/payment/fiscalization markup. Restyle only.
- ❌ Don't add red number-only sale prices (use discount pill + grey strikethrough old).
- ✅ Sealed = default (no badge). Badges for exceptions only.
- ✅ Mobile-first. Sticky CTA on product. Drawer (not dropdown) for mini-cart.
- ✅ Performance: lazy-load images, no heavy carousels, single CSS file.

---

## Appendix A — Reference files

| File in this project        | Purpose                                                   |
|---|---|
| `Booster Shop UX Audit.html`| Canvas with every artboard. Open this side-by-side with `HANDOFF.md` during dev. |
| `audit.md`                  | Full audit narrative + roadmap. Read once before starting. |
| `tokens.css`                | Tokens lifted out of mocks. Mirrors `:root` in `boostershop-ds.css`. |
| `shared-ui.jsx`             | React reference for badges + cards. Source of truth for component behaviour. |
| `category-mock.jsx`         | Category header card + filter sidebar — reference for Phase 1. |
| `product-page.jsx`          | Product page layout — reference for Phase 5.4. |
| `cart-page.jsx`             | Cart page — reference for Phase 5.5. |
| `checkout-page.jsx`         | Checkout — reference for Phase 5.7. |
| `account-pages.jsx`         | Account flows — reference for Phase 5.8. |
| `faq-variants.jsx`          | FAQ Variant B — reference for Phase 5.9. |
| `mobile-views.jsx`          | Mobile critical flows — reference for Phase 4a + every mobile breakpoint. |
| `handoff/boostershop-ds.css`| **Drop in.** Single CSS shipped with this handoff. |
