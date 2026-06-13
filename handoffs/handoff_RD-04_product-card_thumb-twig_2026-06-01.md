# HANDOFF TO CODEX — RD-04: Product Card System (`thumb.twig`)
_Date: 2026-06-01 · Prepared by: Claude · Recipient: Codex_

---

## 1. Task ID

**RD-04 — Product card system: всі стани**

Notion: https://www.notion.so/3706bf20bdb481d28f83d77c17262526  
Priority: **High** · Blocks: RD-07, RD-08, RD-09, RD-10  
Depends on: RD-01 (DS CSS — already applied via patches a→e).

---

## 2. Context

**What already exists (DO NOT redo from scratch):**

- `boostershop-ds.css` has a complete `bs-pcard` CSS section (marker: `PRODUCT-THUMB-COMPACT-20260523`), including:
  - `.bs-pcard`, `.bs-pcard__media` (1:1 aspect-ratio img), `.bs-pcard__body`
  - `.bs-pcard__title` — 14px/600, min-height 40px, 2-line clamp ✅
  - `.bs-pcard__price-row` + `--sale` modifier, `.bs-pcard__price-new`, `.bs-pcard__price-old` ✅
  - `.bs-pcard__badge-tl/tr` ✅
  - `.bs-badge--discount/lowpull/preorder/out` ✅
  - `.bs-btn-preorder` (`var(--bs-blue-light)` = `#3B82F6`) ✅
  - `--bs-danger: #B91C1C` token ✅

- `thumb.twig` current state: old OC4 `product-thumb` wrapper with R-08 overlay badges only — **needs full rewrite**.

- RD-01–03 patches (a→e) already applied on server. `boostershop-ds.css?v=rd010203e-20260601` is live.

**Backup:** `backup-5.30.2026_19-02-08_boosters.tar.gz` (30.05.2026 19:02) — rollback source if needed.

---

## 3. Goal

Rewrite `catalog/view/template/product/thumb.twig` to the full DS card (`bs-pcard`), covering all 6 states:

| State | Trigger | TL Badge | TR Badge | CTA Button |
|-------|---------|----------|----------|------------|
| **default** (sealed) | quantity > 0, no special, not pre/low | — | — | green «Купити» |
| **sale** | `special` is not empty, quantity > 0 | — | `−NN% dark` | green «Купити» |
| **out-of-stock** | quantity ≤ 0 | «Немає в наявності» grey | — | static text (no form) |
| **preorder** | stock_status contains "передзамов" | «Передзамовлення» blue-soft | — | blue «Передзамовити» |
| **low-pull** | stock_status contains "low pull" | «Low Pull» amber | — | green «Купити» |
| **no-photo** | `thumb` is empty/placeholder | — | — | as per state |

`thumb.twig` is already included in: `category.twig`, `home.twig`, `special.twig`, `related.twig`, `search.twig`. Rewriting it propagates everywhere.

---

## 4. What to Change

### 4.1 ⚠️ DB Override Check — MANDATORY FIRST STEP

Before touching any twig file, check `ocp5_theme` for a DB override row:

```sql
SELECT * FROM ocp5_theme WHERE filename = 'product/thumb.twig';
```

- **If row exists:** update `code` column with new content AND edit the file. Or delete the row and edit only the file.
- **If no row:** edit the file directly.

Same check for the CSS file if you add CSS:
```sql
SELECT * FROM ocp5_theme WHERE filename LIKE '%boostershop-ds%';
```

---

### 4.2 Controller — Add `price_value` and `special_value` (required for discount %)

**File:** `catalog/controller/product/category.php` (and same for `special.php`, `search.php`, `home.php` if they use thumb).

Find the block where each product's data array is built (look for where `price` is formatted via `$this->currency->format()`). Add two raw numeric fields alongside:

```php
// After the existing lines that set $product_data['price'] and $product_data['special']:
$product_data['price_value']    = (float)$product_info['price'];
$product_data['special_value']  = $product_info['special'] ? (float)$product_info['special'] : 0.0;
```

> ⚠️ Do NOT touch any price calculation logic, discount logic, tax logic, or currency formatting. This is read-only exposure of existing values.

**Also expose stock_status text for preorder/low-pull detection:**

```php
$product_data['stock'] = $product_info['stock_status'] ?? '';
// stock_status is already available in OC4 product_info — just pass it through
```

> If `stock_status` or `stock_status_id` is not in `$product_info`, skip this line and set `$product_data['stock'] = ''`. Low-pull and preorder badges will simply not show until this is wired.

---

### 4.3 `thumb.twig` — Full Rewrite

**File:** `catalog/view/template/product/thumb.twig`

Replace the entire file content with:

```twig
{# RD-04 — Product card system: всі стани
   OC4 variables: href, thumb, name, price (formatted), special (formatted, '' if none),
   quantity, button_cart, cart_add, cart, product_id, minimum
   Added by controller (step 4.2): price_value (float), special_value (float), stock (text)
#}

{% set bs_is_out  = quantity is defined and quantity <= 0 %}
{% set bs_is_sale = special is not empty and not bs_is_out %}
{% set bs_is_pre  = not bs_is_out and stock is defined and
                    ('передзамов' in stock|lower or 'preorder' in stock|lower) %}
{% set bs_is_low  = not bs_is_out and not bs_is_pre and stock is defined and
                    ('low pull' in stock|lower or 'low-pull' in stock|lower) %}
{% set bs_no_img  = not thumb or 'no_image' in thumb %}

{# Discount % — only when numeric values are exposed by controller #}
{% set discount = 0 %}
{% if bs_is_sale and price_value is defined and special_value is defined and price_value > 0 %}
  {% set discount = ((1 - special_value / price_value) * 100)|round %}
{% endif %}

<article class="bs-pcard{% if bs_is_out %} bs-pcard--out{% endif %}{% if bs_no_img %} bs-pcard--nophoto{% endif %}">

  {# — MEDIA — #}
  <a class="bs-pcard__media" href="{{ href }}" tabindex="-1" aria-hidden="true">
    {% if bs_no_img %}
      <span class="bs-pcard__img-placeholder" aria-hidden="true"></span>
    {% else %}
      <img src="{{ thumb }}" alt="{{ name }}" loading="lazy" width="240" height="240">
    {% endif %}

    {# TR: discount pill — sale only, never on out-of-stock #}
    {% if discount > 0 %}
      <span class="bs-pcard__badge-tr">
        <span class="bs-badge bs-badge--discount">−{{ discount }}%</span>
      </span>
    {% endif %}

    {# TL: exception state badge — sealed products get NO badge #}
    {% if bs_is_out %}
      <span class="bs-pcard__badge-tl">
        <span class="bs-badge bs-badge--out">Немає в наявності</span>
      </span>
    {% elseif bs_is_pre %}
      <span class="bs-pcard__badge-tl">
        <span class="bs-badge bs-badge--preorder">Передзамовлення</span>
      </span>
    {% elseif bs_is_low %}
      <span class="bs-pcard__badge-tl">
        <span class="bs-badge bs-badge--lowpull">Low Pull</span>
      </span>
    {% endif %}
  </a>

  {# — BODY — #}
  <div class="bs-pcard__body">

    <h4 class="bs-pcard__title">
      <a href="{{ href }}">{{ name }}</a>
    </h4>

    {# Price row #}
    {% if price %}
      <div class="bs-pcard__price-row{% if bs_is_sale %} bs-pcard__price-row--sale{% endif %}">
        <span class="bs-pcard__price-new">{{ bs_is_sale ? special : price }}</span>
        {% if bs_is_sale %}
          <span class="bs-pcard__price-old">{{ price }}</span>
        {% endif %}
      </div>
    {% endif %}

    {# CTA #}
    <div class="bs-pcard__cta">
      {% if bs_is_out %}
        <span class="bs-pcard__unavail">Немає в наявності</span>
      {% else %}
        <form method="post"
              data-oc-toggle="ajax"
              data-oc-load="{{ cart }}"
              data-oc-target="#cart"
              class="bs-pcard__form">
          <button type="submit"
                  formaction="{{ cart_add }}"
                  class="bs-btn {% if bs_is_pre %}bs-btn-preorder{% else %}bs-btn-primary{% endif %} bs-pcard__buy-btn">
            {{ bs_is_pre ? 'Передзамовити' : button_cart }}
          </button>
          <input type="hidden" name="product_id" value="{{ product_id }}">
          <input type="hidden" name="quantity"   value="{{ minimum }}">
        </form>
      {% endif %}
    </div>

  </div>
</article>
```

---

### 4.4 `boostershop-ds.css` — Additions Only

Add at the end of the `bs-pcard` section (after line ~275 in the PRODUCT-THUMB-COMPACT block), before the next section marker:

```css
/* == RD-04: product card CTA + minor fixes 20260601 == */

/* Full-width CTA wrapper */
.bs-pcard__cta        { margin-top: auto; }
.bs-pcard__form       { width: 100%; }
.bs-pcard__buy-btn    { width: 100%; font-weight: 600; text-transform: none; }
.bs-pcard__unavail    { font-size: 13px; color: var(--bs-ink-3); display: block;
                         padding: 10px 0 2px; }

/* Fix: spec = 16px bold in sale (was 17px) */
.bs-pcard__price-row--sale .bs-pcard__price-new { font-size: 16px; font-weight: 800; }

/* No-photo placeholder */
.bs-pcard__img-placeholder {
  display: block; width: 100%; aspect-ratio: 1/1;
  background: var(--bs-bg); border-radius: var(--bs-r-sm);
}

/* Ensure body flex fills card height uniformly in grid */
.bs-pcard { height: 100%; }
```

**After any CSS change — bump `?v=` in `header.twig`:**
```twig
{# Change to: #}
boostershop-ds.css?v=rd04-20260601
```

---

### 4.5 Remove Old R-08 Override Rules (optional cleanup)

In `boostershop-ds.css`, find and remove the legacy R-08 block (lines ~1328–1370):
```css
/* == R08-BADGES-20260525: product card state badges ===================== */
```
These `.product-thumb .bs-pcard-badge` rules are no longer needed since `thumb.twig` is fully rewritten. Removing them reduces CSS dead weight (~35 lines). This is **optional** — leaving them causes no harm since `product-thumb` class no longer exists in the new markup.

---

## 5. Do Not Touch

| Zone | Reason |
|------|--------|
| Price calculation logic in controller | Never |
| `special` / tax / discount calculation PHP | Never |
| Cart AJAX handler (`cart` URL / `data-oc-*` attrs) | Keep exactly as-is |
| `minimum` quantity input | Keep — OC4 requires it |
| `product/product.twig` | Separate task RD-10 |
| `product/category.twig` grid layout | Separate task RD-07 |
| `common/cart.twig` (mini-cart) | Separate task RD-12 |
| `sitemap.xml`, `robots.txt` | Do not touch |
| Merchant feed / Schema JSON-LD | Do not touch |
| Checkout/payment logic | Never |
| First15 coupon logic | Never |

---

## 6. Likely Files

| File | Change |
|------|--------|
| `catalog/view/template/product/thumb.twig` | **Full rewrite** |
| `catalog/view/stylesheet/boostershop-ds.css` | Add ~10 lines + cache-bust |
| `catalog/view/template/common/header.twig` | Cache-bust `?v=` only |
| `catalog/controller/product/category.php` | Add `price_value`, `special_value`, `stock` |
| `catalog/controller/product/special.php` | Same additions if renders thumbs |
| `catalog/controller/product/search.php` | Same additions if renders thumbs |
| `catalog/controller/common/home.php` | Same additions if renders thumbs |

> Check each controller — some may already expose `price_value`. Only add what's missing.

---

## 7. Acceptance Criteria

All must pass before marking Done.

### Markup
- [ ] `thumb.twig` uses `bs-pcard` — zero legacy `product-thumb` / `.image` / `.content` classes
- [ ] `article.bs-pcard` renders for every product in category page
- [ ] Image has `aspect-ratio: 1/1` + `object-fit: contain` (DevTools)
- [ ] No-image products show placeholder `background: var(--bs-bg)` instead of broken image
- [ ] `h4.bs-pcard__title` has `min-height: 40px`, `-webkit-line-clamp: 2` (DevTools)

### States
- [ ] **Default (sealed):** No TL badge, no TR badge, green «Купити» full-width
- [ ] **Sale:** TR badge `−NN%` dark (`#111827` bg, white text); price-new red `#B91C1C`; price-old strikethrough grey; green «Купити»
- [ ] **Sale with discount=0** (special present but no numeric values yet): No % badge, red price, old strikethrough still shows
- [ ] **Out of stock:** TL grey «Немає в наявності»; image opacity 0.45; NO form/button, static text only
- [ ] **Preorder:** TL blue-soft «Передзамовлення»; blue «Передзамовити» CTA; add-to-cart form present
- [ ] **Low Pull:** TL amber «Low Pull»; green «Купити» CTA

### Price
- [ ] Sale: `bs-pcard__price-new` = 16px/800 weight, color `#B91C1C`
- [ ] Sale: `bs-pcard__price-old` = strikethrough, 12.5px, `var(--bs-ink-4)`
- [ ] No-sale: `bs-pcard__price-new` = 16px, `var(--bs-ink)` (dark)
- [ ] Price hierarchy: new price visually dominant, old clearly secondary

### CTA
- [ ] «Купити» / «Передзамовити» button is full-width within the card
- [ ] `font-weight: 600`, `text-transform: none` (no uppercase)
- [ ] Preorder button uses blue `#3B82F6` (`.bs-btn-preorder`)
- [ ] OC4 AJAX add-to-cart still works (cart updates without page reload)

### Responsive
- [ ] Mobile (375px): cards in grid render correctly, no overflow
- [ ] Cards in grid have uniform height (flex-column layout with `margin-top: auto` on CTA)

---

## 8. QA Checklist (Owner manual steps after patch)

1. **Cache flush** — OpenCart admin → Dashboard → Clear cache. Hard-refresh (Ctrl+Shift+R).
2. **Category page:** open any category (e.g., `/boostershop.website/pokemon`). Verify:
   - Cards render with new layout
   - Sealed product: no badge, green button
   - Sale product (якщо є): red price + strikethrough + discount badge
3. **Home page:** verify product tiles use new card layout
4. **Out-of-stock product:** verify opacity 0.45 on image, no CTA form
5. **Add to cart:** click «Купити» on any in-stock card → verify cart count updates (OC4 AJAX)
6. **DevTools → Network:** `boostershop-ds.css` loads with new `?v=rd04-20260601`
7. **Mobile check (375px):** category page — no horizontal scroll, cards stack correctly
8. **Related products block** on any product page — also uses `thumb.twig` → verify same layout

> ⚠️ If preorder/low-pull badges don't appear yet — that's expected if `stock` variable is not yet exposed by the controller. Mark as partial until controller is wired.

---

## 9. Risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| DB override on `thumb.twig` silently ignores file edit | HIGH | Run SQL check FIRST (step 4.1) |
| `price_value`/`special_value` not in some controllers → discount=0 | LOW | Acceptable — badge simply won't show; not a blocker |
| `stock` text varies per product (custom statuses) → badges won't trigger | MEDIUM | Owner to verify stock_status text matches "передзамов" / "low pull" |
| OC4 AJAX form attributes must stay exactly as current | HIGH | Keep `data-oc-toggle="ajax"`, `data-oc-load`, `data-oc-target` attrs |
| Grid height uniformity depends on parent grid CSS | MEDIUM | Category grid uses `.bs-product-grid` (verify in category.twig) |
| Old R-08 CSS removed too early → if old markup lingers | LOW | Only remove R-08 block AFTER confirming thumb.twig rewrite is live |

---

## 10. Rollback

**Backup:** `backup-5.30.2026_19-02-08_boosters.tar.gz` (2026-05-30 19:02)

Minimal rollback:
1. Restore `thumb.twig` from backup archive
2. Revert `?v=` in `header.twig`
3. Revert controller changes (remove `price_value`/`special_value`/`stock` lines)
4. Flush OpenCart cache

**Risk level: LOW** — thumb.twig is markup only, no business logic.

---

## 11. Status After Execution

After Codex completes + owner verifies live:
- **RD-04** → Status: `Done`, Last Updated: completion date

Next tasks unlocked: **RD-07** (category page parity), **RD-08** (subcategory/leaf), **RD-09** (home parity), **RD-10** (product page parity) — all depend on RD-04 card.

---
_Handoff prepared: 2026-06-01 · Claude · booster-shop-ops/handoffs/_
