# HANDOFF TO CODEX — RD-10D2 (Breadcrumb → match Clod Design mockup)

_Date: 2026-06-11 · Prepared by: Claude · Recipient: Codex + Owner_
_Branch: `fix/product-page-rd10` · Stack: OpenCart 4 · Twig · CSS_
_Folds into existing patch `rd10_product_cosmetic_fix_20260611d.php`. Keep all non-breadcrumb logic of `d` as-is._

---

## 1. Task ID
RD-10D2 — breadcrumb cosmetic correction (product + category/subcategory), aligned to approved mockup `Сторінка товару - фінал.html`.

## 2. Context
- DB check done by owner: `override_rows=0` for `product/product` and `common/header` → **pure file path**, no `ocp5_theme` overrides. (DB-override branch in `d` will simply find nothing — fine.)
- Patch `d` was reviewed: buy-row split, reviews-above-meta, trust-below-buy, mobile grid, controller (`review_count` + `bs_is_preorder`) — **all correct, keep them**.
- Only `d`'s breadcrumb handling is wrong vs the approved mockup. `d` removes all chevrons and renders the literal word “Головна”. Owner wants chevrons kept, a clean home **icon**, and the stray artifact gone.

### Root causes (verified against current live files)
1. **Chevrons hidden** — `catalog/view/stylesheet/boostershop-ds.css:3476`: `.bs-crumb__sep { display: none !important; }` (added by a prior patch). Mockup HAS chevrons `›` between chips.
2. **“Лапки на будиночку” (product)** — current home icon is a stroked 3-path house (`viewBox 0 0 24 24`, `stroke-width 1.9`) rendered at 13px → messy strokes. Mockup uses a single **filled** house: `viewBox 0 0 16 16`, path `M8 2l6 5v7h-4v-4H6v4H2V7l6-5z`.
3. **“Зайвий шеврон / через очко” (category & subcategory)** — listing pages render OpenCart default `<ul class="breadcrumb">` (home cell = `<i class="fas fa-home"></i>`), and theme `catalog/view/stylesheet/stylesheet.css` adds a rotated-square arrow via `.breadcrumb > li.breadcrumb-item:after { content:""; ... transform:rotate(-45deg) }` plus `:first-child { margin-left:-18px }`. That arrow next to the broken/clipped FA home is the artifact. `d` killed it but dropped chevrons and used text “Головна”.

## 3. Goal
Product, category and subcategory breadcrumbs render **exactly like the mockup**:
`[home pill · filled home icon] › [category pill] › [colored current chip]`
- chevron `›` (INK4 `#9CA3AF`) **between every chip**;
- home = clean filled-house icon in a 26px pill (no `">`, no rotated-square arrow, no FA glyph dependency);
- current chip = category colour (Pokémon default `#FBF4DC` / `#D4A017` / `#6B3A00`);
- pills: bg `#F7F7F5`, border `#E5E7EB`, text `#6B7280`, 12px, radius 999;
- one horizontal scroll line on mobile (no wrap to 2 lines).

## 4. What to change
Three edits, folded into `d` (or shipped as `rd10_breadcrumb_mockup_20260611e.php`). **Do not change `d`'s buy-row / reviews / trust / controller / header cache-bust logic.**

### 4A. `product.twig` — product breadcrumb (function `product_breadcrumb_block()` in `d`)
Keep the existing `bs-crumb` structure **including the `bs-crumb__sep` chevron** between items. Only swap the home icon to the mockup filled house. Net result block:

```twig
  {# RD-10D2: breadcrumb pill chips — mockup-aligned, filled home icon, chevrons kept #}
  {% set _crumb_blob = breadcrumbs|json_encode|lower %}
  {% set _cat_key = category_code|default('') %}
  {% if not _cat_key %}
    {% if 'one-piece' in _crumb_blob or 'one piece' in _crumb_blob %}{% set _cat_key = 'one-piece' %}
    {% elseif 'mtg' in _crumb_blob or 'magic' in _crumb_blob %}{% set _cat_key = 'mtg' %}
    {% elseif 'yugioh' in _crumb_blob or 'yu-gi-oh' in _crumb_blob %}{% set _cat_key = 'yugioh' %}
    {% elseif 'acc' in _crumb_blob or 'аксес' in _crumb_blob %}{% set _cat_key = 'acc' %}
    {% else %}{% set _cat_key = 'pokemon' %}{% endif %}
  {% endif %}
  {% set _cat_colors = {
    'pokemon':   { bg: '#FBF4DC', bd: '#D4A017', tx: '#6B3A00' },
    'one-piece': { bg: '#EEF2FF', bd: '#C7D2FE', tx: '#1E3A8A' },
    'mtg':       { bg: '#FEF2F2', bd: '#FECACA', tx: '#991B1B' },
    'yugioh':    { bg: '#F5F3FF', bd: '#DDD6FE', tx: '#5B21B6' },
    'acc':       { bg: '#F3F4F6', bd: '#D1D5DB', tx: '#374151' }
  } %}
  {% set _cc = _cat_colors[_cat_key]|default(_cat_colors['pokemon']) %}

  <nav class="bs-crumb" aria-label="Breadcrumb">
    <ol class="bs-crumb__list">
      {% for breadcrumb in breadcrumbs %}
        {% if not loop.last %}
          <li class="bs-crumb__item">
            <a href="{{ breadcrumb.href }}" class="bs-crumb__link{% if loop.first %} bs-crumb__link--home{% endif %}" aria-label="{% if loop.first %}Головна{% else %}{{ breadcrumb.text|striptags }}{% endif %}">
              {% if loop.first %}
                <svg width="11" height="11" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 2l6 5v7h-4v-4H6v4H2V7l6-5z"/></svg>
              {% else %}{{ breadcrumb.text }}{% endif %}
            </a>
            <span class="bs-crumb__sep" aria-hidden="true">
              <svg width="7" height="7" viewBox="0 0 8 8" fill="none"><path d="M2 1.5l4 2.5-4 2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
          </li>
        {% else %}
          <li class="bs-crumb__item">
            <span class="bs-crumb__current" style="background:{{ _cc.bg }};border-color:{{ _cc.bd }};color:{{ _cc.tx }};">{{ breadcrumb.text }}</span>
          </li>
        {% endif %}
      {% endfor %}
    </ol>
  </nav>
```

### 4B. `boostershop-ds.css` — show chevrons again, no wrap (append inside `d`'s `transform_ds_css` block)
Overrides the `display:none` at line 3476 (later in cascade + `!important` wins):

```css
/* RD-10D2: restore breadcrumb chevrons + single-line scroll */
.bs-crumb__list { flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; }
.bs-crumb__list::-webkit-scrollbar { display: none; }
.bs-crumb__item { flex: 0 0 auto; }
.bs-crumb__sep {
  display: inline-flex !important;
  align-items: center;
  color: var(--bs-ink-4, #9CA3AF);
  flex: 0 0 auto;
}
.bs-crumb__link--home { width: 26px; height: 26px; padding: 0; }
.bs-crumb__link--home svg { display: block; }
```

### 4C. `stylesheet.css` — global listing breadcrumb to mockup (REPLACE `d`'s global block)
Replace the `RD-10D global breadcrumb` block in `d`'s `transform_stylesheet_css()` with this (keeps chevrons + draws a home icon, no “Головна” text):

```css
/* ==== RD-10D2: global breadcrumb to match mockup 20260611 ==== */
.breadcrumb, body.bs .breadcrumb, ul.breadcrumb, body.bs ul.breadcrumb {
  list-style: none !important; display: flex !important; align-items: center !important;
  flex-wrap: nowrap !important; gap: 5px !important; margin: 0 0 14px !important;
  padding: 10px 0 8px !important; border: 0 !important; border-radius: 0 !important;
  background: transparent !important; overflow-x: auto !important; overflow-y: hidden !important; scrollbar-width: none;
}
.breadcrumb::-webkit-scrollbar, body.bs .breadcrumb::-webkit-scrollbar { display: none; }
.breadcrumb > li.breadcrumb-item, body.bs .breadcrumb > li.breadcrumb-item {
  display: inline-flex !important; align-items: center !important; gap: 5px !important;
  flex: 0 0 auto !important; margin: 0 !important; padding: 0 !important;
  position: static !important; text-shadow: none !important; white-space: nowrap !important;
}
/* kill the theme rotated-square arrow */
.breadcrumb > li.breadcrumb-item:after, body.bs .breadcrumb > li.breadcrumb-item:after,
.breadcrumb > li.breadcrumb-item::after, body.bs .breadcrumb > li.breadcrumb-item::after {
  content: none !important; display: none !important;
}
/* chevron BETWEEN chips */
.breadcrumb > li.breadcrumb-item + li.breadcrumb-item::before,
body.bs .breadcrumb > li.breadcrumb-item + li.breadcrumb-item::before {
  content: "\203A" !important; display: inline-flex !important; align-items: center !important;
  color: #9CA3AF !important; font-size: 14px !important; line-height: 1 !important; margin: 0 1px 0 0 !important; padding: 0 !important;
}
/* chip pills */
.breadcrumb .breadcrumb-item > a, body.bs .breadcrumb .breadcrumb-item > a {
  display: inline-flex !important; align-items: center !important; justify-content: center !important;
  min-height: 26px !important; padding: 4px 10px !important; border-radius: 999px !important;
  border: 1px solid #E5E7EB !important; background: #F7F7F5 !important; color: #6B7280 !important;
  font-size: 12px !important; font-weight: 500 !important; line-height: 1.2 !important; text-decoration: none !important;
  overflow: hidden !important; text-overflow: ellipsis !important; white-space: nowrap !important;
}
/* home = icon pill: hide FA glyph, draw filled home as background */
.breadcrumb .breadcrumb-item:first-child > a, body.bs .breadcrumb .breadcrumb-item:first-child > a {
  width: 26px !important; padding: 0 !important; color: transparent !important; font-size: 0 !important;
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 16 16' fill='%236B7280'><path d='M8 2l6 5v7h-4v-4H6v4H2V7l6-5z'/></svg>") !important;
  background-repeat: no-repeat !important; background-position: center !important;
}
.breadcrumb .breadcrumb-item:first-child i, body.bs .breadcrumb .breadcrumb-item:first-child i { display: none !important; }
/* current (last) chip — Pokémon gold default */
.breadcrumb .breadcrumb-item:last-child > a, body.bs .breadcrumb .breadcrumb-item:last-child > a,
.breadcrumb .breadcrumb-item.active, body.bs .breadcrumb .breadcrumb-item.active {
  background: #FBF4DC !important; border-color: #D4A017 !important; color: #6B3A00 !important;
  font-weight: 600 !important; max-width: min(54vw, 420px) !important;
}
@media (max-width: 767.98px) {
  .breadcrumb .breadcrumb-item > a, body.bs .breadcrumb .breadcrumb-item > a { min-height: 25px !important; padding: 4px 9px !important; font-size: 11px !important; }
  .breadcrumb .breadcrumb-item:first-child > a, body.bs .breadcrumb .breadcrumb-item:first-child > a { width: 24px !important; padding: 0 !important; }
  .breadcrumb .breadcrumb-item:last-child > a, body.bs .breadcrumb .breadcrumb-item:last-child > a,
  .breadcrumb .breadcrumb-item.active, body.bs .breadcrumb .breadcrumb-item.active { max-width: min(58vw, 260px) !important; }
}
/* ==== /RD-10D2 ==== */
```

> Note: the current/active chip colour is fixed to Pokémon gold for **all** listing pages (global CSS can’t read the category). Acceptable per mockup default. Per-category colour on listing breadcrumbs would need a body/category class from a controller — out of scope here.

### 4D. Cache-bust
Bump both versions in `header.twig` (as `d` already does): `boostershop-ds.css?v=` and `{{ stylesheet }}?v=` → new `rd10-breadcrumb-mockup-20260611e` (or keep `d`'s values if shipped inside `d`).

## 5. Do not touch
- `d`'s buy-row split, reviews-above-meta, trust-below-buy, mobile grid, `bs-installment-hint{display:none}` (ПУМБ stays hidden).
- `product.php` controller edits from `d` (`review_count`, `bs_is_preorder`) — keep.
- All five `<script type="application/ld+json">` blocks; R-04 sticky ATC; R07MOB10; `#form-product` submit; `data-main-qty` stepper JS; `data-main-add-to-cart`.
- `checkout/`, `payment/`, fiscalization, `cart.add()`, `sitemap*`, `robots.txt`, canonical, `.htaccess`, Merchant feed.
- BreadcrumbList JSON-LD in `category.twig` / `product.twig` (only visual CSS + the product `<nav>` markup change).

## 6. Likely files / areas
- `catalog/view/template/product/product.twig` — product `<nav class="bs-crumb">` (home icon swap; chevrons already in markup).
- `catalog/view/stylesheet/boostershop-ds.css` — 4B append.
- `catalog/view/stylesheet/stylesheet.css` — 4C replace global block.
- `catalog/view/template/common/header.twig` — cache-bust.
- **Not edited:** `category.twig`, `search.twig`, `special.twig`, `manufacturer_info.twig` — global CSS (4C) covers all listing breadcrumbs. Codex to verify these still use `<ul class="breadcrumb"><li class="breadcrumb-item">`.

## 7. Acceptance criteria (measurable)
- **Product** page `/product/*`: breadcrumb = home-pill (filled house icon, no `">`) → `›` → category pill → `›` → gold current chip. No rotated-square arrow.
- **Category** `/catalog/Pokemon` and **subcategory** `/catalog/Pokemon/...`: same pill+chevron look; home is the filled-house icon pill; **no FA glyph, no `">`, no box border around the whole row**.
- Chevron `›` colour `#9CA3AF`, visible between every pair of chips on both page types.
- Pills: bg `#F7F7F5`, border `#E5E7EB`, text `#6B7280`, 12px; current chip `#FBF4DC`/`#D4A017`/`#6B3A00`.
- Mobile 390/360px: one horizontal line, long names ellipsis, no 2-line wrap.
- `view-source` on a product URL: exactly five `<script type="application/ld+json">` blocks, unchanged.
- Console: zero JS errors on product + category.

## 8. QA / smoke test
No checkout/payment touched → **no checkout smoke test required.** Visual QA:
- Desktop 1280 + mobile 390/360 on: product (Mega Dream EX), category (Pokémon), subcategory (Бустер бокси Pokémon).
- Confirm `›` chevrons present, home icon clean, no `">`/arrow/box.
- Confirm `d`'s other fixes intact (qty stepper separated, reviews above manufacturer, trust below buy row, preorder blue CTA).
- Network: no new/changed requests to `checkout/`.

## 9. Rollback note
Pure file changes (override_rows=0). Patch writes timestamped backups under `system/storage/backup/<patch>_<ts>/files/...`. Rollback = restore `product.twig`, `boostershop-ds.css`, `stylesheet.css`, `header.twig` from that backup, or remove the `RD-10D2` CSS blocks (clearly delimited) and revert the home `<svg>` swap. No DB rows touched.

## 10. Recommended status after execution
`На перевірці` → owner visual QA on product + category + subcategory (desktop + mobile) → `Готово`. Do not mark `Готово` before owner QA.

---

### Integration summary for Codex
1. Take working `rd10_product_cosmetic_fix_20260611d.php`.
2. Replace `product_breadcrumb_block()` output with **4A** (keeps `bs-crumb__sep`, filled home).
3. In `transform_ds_css()` appended block, add **4B** rules.
4. In `transform_stylesheet_css()`, replace the global breadcrumb `$block` with **4C**.
5. Keep everything else in `d` unchanged. Bump cache-bust (**4D**).
6. Re-run validators; `php -l` on `product.php`; confirm `db_override` log line; confirm 5×JSON-LD unchanged.
