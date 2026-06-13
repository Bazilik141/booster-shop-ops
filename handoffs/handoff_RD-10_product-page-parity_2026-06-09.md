# HANDOFF TO CODEX — RD-10 (Product page parity + sticky ATC)
_Date: 2026-06-09 · Prepared by: Claude · Recipient: Codex + Owner_
_Source backup: `backup-6.3.2026_21-17-59_boosters.tar.gz` (= live as of 2026-06-03)_
_Recommended model: Sonnet · standard thinking_

---

## SEO RISK GATE (preflight)

**Risk: MEDIUM.**

- JSON-LD Product schema in `product.twig` is protected — do NOT touch.
- BreadcrumbList, Organization, WebSite JSON-LD scripts — do NOT touch.
- Product attributes/descriptions/SEO texts — scope-guarded (Notion: "не чіпати атрибути/описи/SEO-тексти").
- `checkout/*`, payment, sitemap, robots, canonical, Merchant feed — out of scope.

**No checkout smoke test needed.** Only visual/structural changes to `product.twig` + additions to `boostershop-ds.css`.

---

## 0. What is already done — verify, do not re-implement

### ✅ Sticky Add-to-Cart (R-04) — LIVE in template

The `{# ===== R-04: Sticky Add-to-Cart (mobile only) ===== #}` block is **already fully implemented** in `product.twig` (from R-04 patch). It includes:
- `.bs-sticky-atc` fixed bar with CSS (media query `max-width: 768px`)
- `.bs-qty` stepper (−/+ buttons + `<input type="number">`)
- `.bs-btn.bs-btn-primary.bs-sticky-atc__cta` button with `display_price`
- IntersectionObserver watching `[data-main-add-to-cart]`
- Qty sync between main form and sticky bar

**Codex: keep this block intact. Only verify the button class is `bs-btn bs-btn-primary` (DS-class). No re-implementation.**

### ✅ Related carousel progress (R07MOB10) — LIVE

The `// R07MOB10 related carousel progress` `<script>` block is already in `product.twig`. Keep as-is.

### ✅ Product JSON-LD schema — LIVE, protected

All five `<script type="application/ld+json">` blocks (Product + shippingDetails, Organization/shipping, BreadcrumbList, Organization/sameAs, WebSite) are present and correct. **Do not touch.**

---

## 1. Task summary

**File:** `catalog/view/template/product/product.twig`
**Also:** `catalog/view/stylesheet/boostershop-ds.css` (additive CSS only)
**Also:** remove old FAQ inline styles/scripts (replaced by RD-05 files)

| Component | Current state | Required change |
|---|---|---|
| Gallery | Raw `img-thumbnail`, no DS wrapper | Add `.bs-product__gallery` DS wrapper + thumbnail strip layout |
| Title block | Plain `<h1>` + Bootstrap `<ul>` | DS class, hide article/model, manufacturer as small link |
| Stock | Plain text in `<ul>` | `.bs-badge` with variant (preorder / out / instock) |
| Price block | Bootstrap `price-old/price-new` in `<ul>` | `.bs-price-block` DS component |
| Installment | Missing | Static placeholder `.bs-installment-hint` (ПУМБ) |
| Add-to-cart form | Functional, Bootstrap `.btn-primary` | Keep logic, wrap in DS classes |
| Sticky ATC | **Already done (R-04)** | Verify `.bs-btn.bs-btn-primary` class, no re-implementation |
| FAQ | Inline CSS + old JS in template | Remove inline FAQ block → RD-05 files already handle this |
| Spec tab | Bootstrap `table-bordered` | `.bs-spec-table` DS class |
| Reviews tab | Native OC4 review widget | Replace content with Telegram + OLX redirect cards |

---

## 2. Step 0 — DB check (always first)

```sql
SELECT * FROM ocp5_theme WHERE filename IN (
  'product/product.twig',
  'common/header.twig'
);
```

- If `product/product.twig` row exists → edit the DB `code` column, not the file.
- If `common/header.twig` row exists → check if `bs-faq.css` + `bs-faq.js` are already linked there.

---

## 3. Step 1 — CSS additions to `boostershop-ds.css`

**Insert the entire block BEFORE the existing `/* ==== RD-04f` marker.**
Marker to find:
```css
/* == RD-04: product card CTA + minor fixes 20260601 == */
```
Insert before that line:

```css
/* ==== RD-10: product page — gallery, info, price, badge, reviews 20260609 ==== */

/* --- Layout wrapper --- */
.bs-product__layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 32px;
  margin-bottom: 32px;
}
@media (max-width: 767px) {
  .bs-product__layout { grid-template-columns: 1fr; gap: 20px; }
}

/* --- Gallery --- */
.bs-product__gallery {}
.bs-product__gallery .bs-product__main-img {
  display: block;
  width: 100%;
  border-radius: var(--bs-radius-md, 10px);
  overflow: hidden;
  background: var(--bs-surface, #f8fafc);
  margin-bottom: 10px;
}
.bs-product__gallery .bs-product__main-img img {
  width: 100%;
  height: auto;
  aspect-ratio: 1;
  object-fit: contain;
  display: block;
}
.bs-product__thumbs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.bs-product__thumbs a {
  display: block;
  width: 72px;
  height: 72px;
  border-radius: var(--bs-radius-sm, 6px);
  overflow: hidden;
  border: 1px solid var(--bs-line, #D8DEE8);
  flex: 0 0 72px;
}
.bs-product__thumbs a:hover { border-color: var(--bs-gold, #C68A00); }
.bs-product__thumbs img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

/* --- Info panel --- */
.bs-product__info { display: flex; flex-direction: column; gap: 14px; }

/* --- Title --- */
.bs-product__title {
  font-size: clamp(20px, 3vw, 26px);
  font-weight: 700;
  line-height: 1.25;
  color: var(--bs-ink, #111827);
  margin: 0;
}

/* --- Brand link --- */
.bs-product__brand {
  font-size: 13px;
  color: var(--bs-ink-3, #6B7280);
  text-decoration: none;
}
.bs-product__brand:hover { color: var(--bs-gold, #C68A00); }

/* --- Stock badge (add instock variant to existing .bs-badge system) --- */
.bs-badge--instock { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }

/* --- Price block --- */
.bs-price-block { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
.bs-price-block__new {
  font-size: 28px;
  font-weight: 800;
  color: var(--bs-ink, #111827);
  line-height: 1;
}
.bs-price-block__old {
  font-size: 16px;
  color: var(--bs-ink-3, #6B7280);
  text-decoration: line-through;
  line-height: 1;
}

/* --- Installment placeholder (ПУМБ) --- */
.bs-installment-hint {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: var(--bs-radius-sm, 6px);
  background: var(--bs-blue-soft, #eff6ff);
  border: 1px solid #c7d2fe;
  font-size: 13px;
  color: var(--bs-blue, #1E3A8A);
}
.bs-installment-hint__label { font-weight: 600; }
.bs-installment-hint__sub { color: var(--bs-ink-3, #6B7280); }

/* --- Specification table --- */
.bs-spec-table { width: 100%; border-collapse: collapse; }
.bs-spec-table thead td {
  padding: 10px 14px;
  background: var(--bs-surface, #f8fafc);
  font-weight: 700;
  font-size: 13px;
  color: var(--bs-ink-2, #374151);
  border-bottom: 2px solid var(--bs-line, #D8DEE8);
}
.bs-spec-table tbody tr { border-bottom: 1px solid var(--bs-line, #D8DEE8); }
.bs-spec-table tbody tr:last-child { border-bottom: none; }
.bs-spec-table tbody td {
  padding: 9px 14px;
  font-size: 14px;
  color: var(--bs-ink, #111827);
  vertical-align: top;
}
.bs-spec-table tbody td:first-child {
  width: 45%;
  color: var(--bs-ink-3, #6B7280);
  font-weight: 500;
}

/* --- Reviews redirect cards --- */
.bs-review-cards {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  padding: 8px 0;
}
@media (max-width: 540px) {
  .bs-review-cards { grid-template-columns: 1fr; }
}
.bs-review-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px;
  border-radius: var(--bs-radius-md, 10px);
  border: 1px solid var(--bs-line, #D8DEE8);
  text-decoration: none;
  color: var(--bs-ink, #111827);
  transition: border-color 0.18s, box-shadow 0.18s;
}
.bs-review-card:hover {
  border-color: var(--bs-gold, #C68A00);
  box-shadow: 0 4px 16px rgba(17,24,39,0.08);
  color: var(--bs-ink, #111827);
}
.bs-review-card__icon {
  flex: 0 0 40px;
  width: 40px;
  height: 40px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.bs-review-card--tg .bs-review-card__icon { background: #e8f4fd; }
.bs-review-card--olx .bs-review-card__icon { background: #fff3e0; }
.bs-review-card__body { flex: 1; min-width: 0; }
.bs-review-card__title { font-weight: 700; font-size: 14px; line-height: 1.3; }
.bs-review-card__sub { font-size: 12px; color: var(--bs-ink-3, #6B7280); margin-top: 2px; }
.bs-review-card__arrow { font-size: 18px; color: var(--bs-ink-3, #6B7280); flex: 0 0 auto; }

/* ==== /RD-10 ==== */
```

---

## 4. Step 2 — Rewrite `product.twig` structure

Below is the complete revised structure. Replace the current `product.twig` content **from `{{ header }}` through `{{ footer }}`**, preserving all JSON-LD `<script>` blocks, R-04 sticky ATC block, and R07MOB10 script exactly.

### 4a. Top — header + breadcrumb (unchanged)
```twig
{{ header }}
<div id="product-info" class="container">
  <ul class="breadcrumb">
    {% for breadcrumb in breadcrumbs %}
      <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
    {% endfor %}
  </ul>
  <div class="row">{{ column_left }}
    <div id="content" class="col">
      {{ content_top }}
```

### 4b. Main product layout (REPLACE current `<div class="row mb-3">` block)

```twig
      <div class="bs-product__layout">

        {# === GALLERY === #}
        {% if thumb or images %}
        <div class="bs-product__gallery magnific-popup">
          {% if thumb %}
          <div class="bs-product__main-img">
            <a href="{{ popup }}" title="{{ heading_title }}">
              <img src="{{ thumb }}" title="{{ heading_title }}" alt="{{ heading_title }}" width="400" height="400" decoding="async"/>
            </a>
          </div>
          {% endif %}
          {% if images %}
          <div class="bs-product__thumbs">
            {% for image in images %}
              <a href="{{ image.popup }}" title="{{ heading_title }}">
                <img src="{{ image.thumb }}" title="{{ heading_title }}" alt="{{ heading_title }}" width="72" height="72" loading="lazy" decoding="async"/>
              </a>
            {% endfor %}
          </div>
          {% endif %}
        </div>
        {% endif %}

        {# === INFO PANEL === #}
        <div class="bs-product__info">

          {# Title #}
          <h1 class="bs-product__title">{{ heading_title }}</h1>

          {# Manufacturer #}
          {% if manufacturer %}
          <div>
            <a href="{{ manufacturers }}" class="bs-product__brand">{{ manufacturer }}</a>
          </div>
          {% endif %}

          {# Stock badge #}
          {% set stock_text = stock|striptags|trim %}
          {% if stock_text == 'Закінчився' or stock_text == 'Out Of Stock' %}
            {% set stock_mod = 'bs-badge--out' %}
          {% elseif stock_text == 'Передзамовлення' or stock_text == 'Pre-Order' %}
            {% set stock_mod = 'bs-badge--preorder' %}
          {% else %}
            {% set stock_mod = 'bs-badge--instock' %}
          {% endif %}
          <div><span class="bs-badge {{ stock_mod }}">{{ stock }}</span></div>

          {# Price block #}
          {% if price %}
          <div class="bs-price-block">
            {% if special %}
              <span class="bs-price-block__old">{{ price }}</span>
              <span class="bs-price-block__new">{{ special }}</span>
            {% else %}
              <span class="bs-price-block__new">{{ price }}</span>
            {% endif %}
          </div>

          {# ПУМБ installment placeholder — static, will be replaced with real integration #}
          <div class="bs-installment-hint">
            <span class="bs-installment-hint__label">Оплата частинами ПУМБ</span>
            <span class="bs-installment-hint__sub">Деталі при оформленні замовлення</span>
          </div>
          {% endif %}

          {# Add-to-cart form — keep logic intact, update button class #}
          <div id="product">
            <form id="form-product">
              {% if options %}
                <hr>
                <h3>{{ text_option }}</h3>
                <div>
                  {# — keep all existing option type blocks (select/radio/checkbox/text/textarea/file/date/time/datetime) exactly as-is — #}
                </div>
              {% endif %}

              {% if subscription_plans %}
                <hr/>
                <h3>{{ text_subscription }}</h3>
                <div class="mb-3 required">
                  <select name="subscription_plan_id" id="input-subscription" class="form-select">
                    <option value="">{{ text_select }}</option>
                    {% for subscription_plan in subscription_plans %}
                      <option value="{{ subscription_plan.subscription_plan_id }}">{{ subscription_plan.name }}</option>
                    {% endfor %}
                  </select>
                  {% for subscription_plan in subscription_plans %}
                    <div id="subscription-description-{{ subscription_plan.subscription_plan_id }}" class="form-text subscription d-none">{{ subscription_plan.description }}</div>
                  {% endfor %}
                  <div id="error-subscription" class="invalid-feedback"></div>
                </div>
              {% endif %}

              <div class="mb-3">
                <div class="input-group">
                  <div class="input-group-text">{{ entry_qty }}</div>
                  <input type="text" name="quantity" value="{{ minimum }}" size="2" id="input-quantity" class="form-control"/>
                  <button type="submit" id="button-cart" class="btn btn-primary btn-lg btn-block" data-main-add-to-cart>{{ button_cart }}</button>
                </div>
                <input type="hidden" name="product_id" value="{{ product_id }}" id="input-product-id"/>
                <div id="error-quantity" class="form-text"></div>
              </div>
              {% if minimum > 1 %}
                <div class="alert alert-warning"><i class="fa-solid fa-circle-info"></i> {{ text_minimum }}</div>
              {% endif %}
            </form>
          </div>

          {# Reward points (keep if used) #}
          {% if reward %}
            <div class="mt-1" style="font-size:13px;color:var(--bs-ink-3)">{{ text_reward }} {{ reward }}</div>
          {% endif %}

        </div>{# /bs-product__info #}

      </div>{# /bs-product__layout #}
```

> **Important:** In the options block above, keep ALL the existing `{% if option.type == 'select' %}`, `{% if option.type == 'radio' %}`, etc. blocks EXACTLY as they are in the current template. They are not shown here to save space. Do not simplify or remove them.

### 4c. Tabs (REPLACE current `<ul class="nav nav-tabs">` block)

```twig
      <ul class="nav nav-tabs">
        <li class="nav-item"><a href="#tab-description" data-bs-toggle="tab" class="nav-link active">{{ tab_description }}</a></li>
        {% if attribute_groups %}
          <li class="nav-item"><a href="#tab-specification" data-bs-toggle="tab" class="nav-link">{{ tab_attribute }}</a></li>
        {% endif %}
        <li class="nav-item"><a href="#tab-review" data-bs-toggle="tab" class="nav-link">Відгуки</a></li>
      </ul>

      <div class="tab-content">

        {# Description tab — unchanged #}
        <div id="tab-description" class="tab-pane fade show active mb-4">
          {{ description }}
          {% if tags %}
            <p>{{ text_tags }}
              {% for tag in tags %}
                <a href="{{ tag.href }}">{{ tag.tag }}</a>{% if not loop.last %},{% endif %}
              {% endfor %}
            </p>
          {% endif %}
        </div>

        {# Specification tab — DS-styled table #}
        {% if attribute_groups %}
          <div id="tab-specification" class="tab-pane fade">
            <div class="table-responsive">
              <table class="bs-spec-table">
                {% for attribute_group in attribute_groups %}
                  <thead>
                    <tr><td colspan="2">{{ attribute_group.name }}</td></tr>
                  </thead>
                  <tbody>
                    {% for attribute in attribute_group.attribute %}
                      <tr>
                        <td>{{ attribute.name }}</td>
                        <td>{{ attribute.text }}</td>
                      </tr>
                    {% endfor %}
                  </tbody>
                {% endfor %}
              </table>
            </div>
          </div>
        {% endif %}

        {# Reviews tab — Telegram + OLX redirect cards (NOT native review widget) #}
        <div id="tab-review" class="tab-pane fade mb-4">
          <div class="bs-review-cards">

            <a href="https://t.me/boostershop_tcg" target="_blank" rel="noopener noreferrer" class="bs-review-card bs-review-card--tg">
              <div class="bs-review-card__icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="12" cy="12" r="12" fill="#29B6F6"/>
                  <path d="M5 11.8l2.8 1.05 1.08 3.47c.07.22.34.3.52.16l1.56-1.27a.37.37 0 0 1 .45-.01l2.82 2.05c.2.15.49.04.55-.2l2.18-9.57c.07-.28-.19-.52-.46-.42L5 10.97a.38.38 0 0 0 0 .73z" fill="#fff"/>
                </svg>
              </div>
              <div class="bs-review-card__body">
                <div class="bs-review-card__title">Відгуки в Telegram</div>
                <div class="bs-review-card__sub">Реальні відгуки покупців у каналі</div>
              </div>
              <div class="bs-review-card__arrow">›</div>
            </a>

            <a href="[OLX_URL_NEEDED]" target="_blank" rel="noopener noreferrer" class="bs-review-card bs-review-card--olx">
              <div class="bs-review-card__icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <rect width="24" height="24" rx="6" fill="#FF6B00"/>
                  <text x="4" y="17" font-family="Arial" font-size="11" font-weight="bold" fill="#fff">OLX</text>
                </svg>
              </div>
              <div class="bs-review-card__body">
                <div class="bs-review-card__title">Відгуки на OLX</div>
                <div class="bs-review-card__sub">Оцінки та коментарі покупців</div>
              </div>
              <div class="bs-review-card__arrow">›</div>
            </a>

          </div>
        </div>

      </div>{# /tab-content #}
```

### 4d. After tabs: related + sticky ATC + scripts (keep as-is)

```twig
      {{ related }}

      {# ===== R-04: Sticky Add-to-Cart (mobile only) ===== #}
      {# KEEP ENTIRE R-04 BLOCK EXACTLY AS-IS — do not modify #}
      ...
      {# ===== /R-04 ===== #}

      {# R07MOB10 script — KEEP AS-IS #}
      ...

      {{ content_bottom }}
    </div>
    {{ column_right }}
  </div>
</div>
```

### 4e. Scripts + JSON-LD (keep as-is)

Keep ALL of the following **exactly unchanged** in this order:
1. `<script type="text/javascript">` (subscription toggle + form-product submit + magnificPopup init)
2. `<script type="application/ld+json">` Product schema
3. `<script type="application/ld+json">` Organization/shipping
4. `<script type="application/ld+json">` BreadcrumbList
5. `<script type="application/ld+json">` Organization/sameAs
6. `<script type="application/ld+json">` WebSite

### 4f. OLD FAQ block — REMOVE entirely

Find and remove the entire inline `<style>` block that contains `.bs-faq-accordion`, `.bs-faq-title`, `.bs-faq-item`, `.bs-faq-toggle`, `.bs-faq-icon`, `.bs-faq-panel`, `.bs-special-seo` (~100 lines).

Find and remove the entire inline `<script>` block with `window.bsFaqAccordionReady` (~35 lines).

These are replaced by the `bs-faq.css` + `bs-faq.js` files from RD-05.

### 4g. RD-05 FAQ files — link check

Before removing the old FAQ inline block, check `common/header.twig` (or its DB equivalent):
```
grep "bs-faq" in header.twig DB/file
```

- **If `bs-faq.css` + `bs-faq.js` are already linked in `header.twig`** → just remove the inline block, files are already active.
- **If NOT yet linked** → add to `header.twig` after the DS stylesheet link:
  ```twig
  <link rel="stylesheet" href="{{ catalog }}view/stylesheet/bs-faq.css?v=rd05-faq-20260603">
  <script src="{{ catalog }}view/javascript/bs-faq.js" defer></script>
  ```
  And verify files exist at those paths on the server.

---

## 5. Owner action required before Codex proceeds

**OLX URL:** Replace `[OLX_URL_NEEDED]` in the reviews tab with the actual Booster Shop OLX store URL. Paste it in the handoff reply or add it to the Notion task comment.

---

## 6. Do NOT touch

- All `<script type="application/ld+json">` blocks — product schema, breadcrumbs, org, website.
- `{# ===== R-04: Sticky Add-to-Cart #}` block — already done, zero changes.
- `// R07MOB10` script block — already done, zero changes.
- The `#form-product` submit handler jQuery script — keep intact.
- `$('#input-subscription').on('change', ...)` script — keep intact.
- Product `description`, attribute texts, SEO keywords — zero scope.
- `boostershop-ds.css` existing blocks — additive only, no edits to existing rules.
- `robots.txt`, `sitemap-gsc.xml`, `.htaccess`, checkout, payment — out of scope.

---

## 7. Acceptance criteria

- Home → click any product → product page loads with DS-styled 2-column layout (gallery left, info right).
- Main product image fills the left column at natural aspect ratio; thumbnails appear as 72px squares below.
- Stock status renders as `.bs-badge` chip: green for "В наявності", amber for "Передзамовлення", grey for "Закінчився".
- Price renders large (28px, bold) in `.bs-price-block`; if special price, old price is struck-through.
- ПУМБ installment hint visible as blue-tinted strip below price.
- "Відгуки" tab visible always; clicking it shows 2 redirect cards (Telegram + OLX), no native review form.
- Telegram card → opens `https://t.me/boostershop_tcg` in new tab.
- Spec tab renders with `.bs-spec-table` (no `table-bordered` Bootstrap class).
- FAQ accordion renders via RD-05 normalizer (`.bs-faq` class structure), NOT the old `.bs-faq-accordion` style.
- Sticky ATC still appears on mobile when the main "Купити" button scrolls out of view.
- `document.querySelector('#button-cart')` exists with `data-main-add-to-cart` attribute (sticky sync).
- No `console.error` on product page load.
- All 5 JSON-LD scripts present and unchanged (validate with Google Rich Results Test).

---

## 8. QA checklist

- [ ] Desktop (1280px): 2-column layout renders, no horizontal scroll.
- [ ] Mobile (390px): single-column layout; gallery → info stacked vertically.
- [ ] Stock badge: open "Pokemon-boosters-White-Flare" → green "В наявності" badge.
- [ ] Stock badge: open a product with "Передзамовлення" → amber badge.
- [ ] Price block: product with special price → old price struck-through, new price large.
- [ ] ПУМБ hint: visible as blue strip below price.
- [ ] "Відгуки" tab: no native review form; only 2 redirect cards.
- [ ] Telegram card: click → opens Telegram channel in new tab.
- [ ] Spec tab: attributes table renders with `.bs-spec-table`, no Bootstrap `table-bordered`.
- [ ] FAQ: product with FAQ content (OP-15, White-Flare) → RD-05 accordion renders with gold chevron.
- [ ] FAQ: no old `.bs-faq-accordion` visible; no duplicate FAQ rendering.
- [ ] Sticky ATC mobile: scroll past "Купити" button → sticky bar appears; qty sync works.
- [ ] Related carousel: still renders below tabs, progress bar updates on swipe.
- [ ] Add-to-cart: add item → success alert, cart counter updates.
- [ ] Google Rich Results Test on one product URL → Product schema valid, no errors.
- [ ] `view-source:` on product URL → no `<script type="application/ld+json">` blocks modified.

---

## 9. Risks

| Risk | Impact | Mitigation |
|---|---|---|
| Overwriting or modifying JSON-LD blocks | Product loses Rich Results + Merchant feed | Explicit scope guard: do not touch any `<script type="application/ld+json">` |
| Removing `data-main-add-to-cart` from button | Sticky ATC stops working (no IntersectionObserver target) | Keep `data-main-add-to-cart` on `#button-cart` |
| Options block (radio/select/checkbox/file/date/etc.) partially removed or simplified | Some products break at add-to-cart | Instruction: copy-paste all `{% if option.type == ... %}` blocks exactly as-is |
| RD-05 files not deployed before removing inline FAQ CSS | FAQ renders unstyled | Step 4f includes explicit check — do not remove inline block until files confirmed present |
| OLX URL left as `[OLX_URL_NEEDED]` placeholder | Broken link on reviews tab | Owner must provide URL before Codex merges |
| `bs-badge--instock` not in DS → badge renders unstyled | "В наявності" shows without styling | Included in the new CSS block (Step 1) |

---

## 10. Rollback

- Restore `product/product.twig` from `ocp5_theme` row backup (if DB-stored) or from `backup-6.3.2026_21-17-59_boosters.tar.gz` → `homedir/public_html/catalog/view/template/product/product.twig`.
- Remove the `/* ==== RD-10 ==== */` CSS block from `boostershop-ds.css` (it's clearly delimited by `/* ==== RD-10 ... ==== */` and `/* ==== /RD-10 ==== */` markers).
- No DB data changed, no schema modified → rollback = file restore only.

---

## 11. Cache bust

After applying changes, update `header.twig` stylesheet version string:
```
# Find current: ?v=tech010-noindex-20260609 (or whatever is current)
# Change to:    ?v=rd10-product-parity-20260609
```

---

_Dependencies confirmed: RD-01 ✓ RD-04 ✓ RD-05 ✓ — all done. Sticky ATC (R-04) already live in template. OLX URL needed from owner before Codex can finalize reviews tab._
