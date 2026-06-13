# HANDOFF TO CODEX — RD-01 + RD-02 + RD-03 (Combined Patch)
_Date: 2026-05-30 · Prepared by: Claude · Recipient: Codex_

---

## 1. Task ID

**RD-01-02-03-SHELL-DS** (combined single patch)

Notion tasks:
- RD-01 — Design System reconciliation (tokens, radius, green, gold-foam cleanup)
- RD-02 — Header + utility bar visual parity
- RD-03 — Footer visual parity

---

## 2. Context

**What's already done (do not redo from scratch):**
- `boostershop-ds.css` is deployed and connected in `header.twig` via `<link>` tag.
- `<body class="bs">` is set. Manrope font is active.
- R-05 (header + footer) was executed: header has ~20 `.bs-*` classes, footer has ~9 `.bs-*` classes. Structure partially exists.
- Backup available: `backup-5.30.2026_19-02-08_boosters.tar.gz` (2026-05-30, ~170 MB).

**Why these three tasks are combined:**
- RD-01 modifies `boostershop-ds.css` and requires a `?v=` cache-bust bump in `header.twig`.
- RD-02 also modifies `header.twig` for visual parity.
- RD-03 modifies `footer.twig`.
- All three are "shell" components — no product logic, no checkout, no price logic.
- Combining avoids two separate `header.twig` patches.

**Design reference source of truth:**
- `HANDOFF (redesign).md` Phase 5.1 (header) and Phase 5.2 (footer) in the project folder.
- `tokens.css` for token reference values.
- `audit_2026-05-19.md` for DS rules.

---

## 3. Goal

Bring `boostershop-ds.css`, `common/header.twig`, and `common/footer.twig` to full DS parity with the Claude Design reference (2026-05-21):

1. **RD-01:** Clean up CSS token layer — radius scale, shadows, z-index, green purity, remove gold-foam decorative uses.
2. **RD-02:** Header visual parity — compact cart-pill, neutral breadcrumbs, DS search field, no gold-foam, no stray green.
3. **RD-03:** Footer visual parity — 4-column layout (Каталог / Інформація / Покупцю / Контакти), DS typography, correct Telegram links.

Scope: **CSS and template markup only. No business logic. No price logic. No checkout logic.**

---

## 4. What to Change

### RD-01 — `boostershop-ds.css`

#### 4.1 Radius scale (reconcile to 3 values only)
```css
:root {
  --bs-r-sm: 6px;   /* inputs, small chips, badges */
  --bs-r:    10px;  /* product cards, dropdowns, modal */
  --bs-r-lg: 14px;  /* hero blocks, category tiles, footer */
}
```
Buttons specifically use `8px` (override at component level, not a token):
```css
.bs-btn { border-radius: 8px; }
```

#### 4.2 Shadow tokens (add if missing)
```css
:root {
  --bs-sh-sm:  0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.05);
  --bs-sh-md:  0 4px 12px rgba(0,0,0,.10), 0 2px 4px rgba(0,0,0,.06);
  --bs-sh-pop: 0 8px 24px rgba(0,0,0,.12), 0 2px 6px rgba(0,0,0,.07);
}
```

#### 4.3 Z-index tokens (add if missing)
```css
:root {
  --bs-z-dropdown: 100;
  --bs-z-sticky:   200;
  --bs-z-overlay:  300;
  --bs-z-modal:    400;
  --bs-z-toast:    500;
}
```

#### 4.4 ⚠️ Green purity — CRITICAL RULE
Replace ALL occurrences of old green values with DS tokens.

Find and replace globally in `boostershop-ds.css`:
- `#1fa247` → `#16A34A` (or `var(--bs-green)`)
- `#18853a` → `#15803D` (or `var(--bs-green-d)`)
- Any other non-`#16A34A`/`#15803D` green shades used outside purchase-action context → audit and remove.

Ensure `--bs-green` and `--bs-green-d` tokens exist in `:root`:
```css
:root {
  --bs-green:   #16A34A;
  --bs-green-d: #15803D;
}
```

Green is ONLY allowed on:
- `.bs-btn-primary` (Купити / Оформити / Додати в кошик)
- Mini-cart checkout CTA
- Success state indicators
- `.bs-cart__line .bs-ok` (free shipping confirmation)
- `.bs-pp__stock` (in-stock dot) — green text/dot acceptable here

Green must NOT appear on:
- Navigation, menu, chips
- Category headers or tiles
- Informational buttons
- Breadcrumbs, badges (except success)
- Footer, header (except cart CTA button)

#### 4.5 Gold-foam cleanup
Remove gold/foam as decorative background from:
- Any header background, header chip backgrounds using `--bs-pokemon` / `--bs-gold` / `#D4A017` or similar.
- Any chip `.bs-chip` default background that uses gold/amber tones as decoration.

Gold (`--bs-pokemon: #C68A00`, `--bs-gold: #D4A017`) is ONLY allowed for:
- Pokémon category brand strip (the 4px top border stripe on category tiles/header card)
- Explicit Pokémon branding contexts

Header and chips use neutral backgrounds: `#F7F7F5` (bg) and `#E5E7EB` (border).

#### 4.6 Cache-bust bump — MANDATORY after any CSS change
In `common/header.twig`, find the `<link>` to `boostershop-ds.css` and bump its `?v=` version string:
```twig
{# Find something like: #}
<link rel="stylesheet" href="...boostershop-ds.css?v=XXXXXXXX">
{# Change to: #}
<link rel="stylesheet" href="...boostershop-ds.css?v=rd010203-20260530">
```

---

### RD-02 — `common/header.twig`

**State before:** R-05 was executed, structure partially exists. This is a parity pass, not a full rewrite. Codex must read the current file before making changes.

#### 4.7 Cart pill — compact
Current issue: cart button may be too prominent / using wrong green shade.

Required state:
- Cart icon + count as primary (bold)
- Total price as secondary (smaller, lower opacity ~0.88)
- Green background stays (`.bs-btn-primary`) but must be `#16A34A` / hover `#15803D`
- Remove any `#1fa247` / `#18853a` from cart button

Reference markup (from `HANDOFF (redesign).md` Phase 5.1):
```twig
<a href="{{ cart_url }}" class="bs-btn bs-btn-primary bs-btn-sm">
  {# cart SVG icon #}
  <span style="font-weight:700;">{{ cart_quantity }}</span>
  <span style="font-weight:500; opacity:0.88;">· ₴{{ cart_total }}</span>
</a>
```

#### 4.8 Breadcrumbs — neutral colors
Find breadcrumb markup in header.twig (or wherever rendered).

Required:
- All ancestor links: color `var(--bs-ink-3)` = `#6B7280` (grey)
- Current/last item: color `var(--bs-ink)` = `#111827` (dark, no underline)
- NO bright blue (`#1E3A8A`) on breadcrumb links
- Separator: `›` or `/` in `#9CA3AF`

CSS to verify/add:
```css
.bs-breadcrumb a           { color: var(--bs-ink-3); text-decoration: none; }
.bs-breadcrumb a:hover     { color: var(--bs-ink-2); }
.bs-breadcrumb__current    { color: var(--bs-ink); font-weight: 500; }
.bs-breadcrumb__sep        { color: #9CA3AF; margin: 0 6px; }
```

#### 4.9 Search field — DS style
Search input must use DS classes:
```twig
<form class="bs-search" action="{{ search_action }}" method="get">
  {# search SVG icon, color #6B7280 #}
  <input class="bs-search__input" type="search" name="search"
         placeholder="Пошук бустерів, сетів…"
         value="{{ search_query|default('') }}">
</form>
```
Background: `var(--bs-bg)` = `#F7F7F5`. Border: `1px solid var(--bs-line)` = `#E5E7EB`. Radius: `var(--bs-r-sm)` = 6px.

**Note:** Search dropdown visual handled here. Search functionality logic (autocomplete, AJAX) is a separate task UX-009 — DO NOT touch search PHP controller or JS logic.

#### 4.10 Gold-foam removal from header
Verify no gold/amber background anywhere in header markup or header-specific CSS rules. Header background must be `#fff`. Border-bottom: `1px solid var(--bs-line)`.

---

### RD-03 — `common/footer.twig`

**State before:** R-05 was executed, footer has ~9 bs-classes. This is a parity pass.

#### 4.11 Footer column structure
Footer must have exactly 4 navigation columns after the brand block:

| Column | Links |
|--------|-------|
| **Каталог** | Pokémon TCG, One Piece Card Game, Акції |
| **Інформація** | Оплата і доставка, Обмін і повернення, Публічна оферта |
| **Покупцю** | Гарантія оригінальності, Про магазин, Telegram-канал |
| **Контакти** | @BoosterShop_Support_bot (Telegram), email, phone |

Reference markup structure (from `HANDOFF (redesign).md` Phase 5.2):
- Brand block: logo, short description (оригінальні sealed-бустери), legal name
- 4 `.bs-footer__col` blocks with `<h4>` headings
- Bottom bar: copyright + support hours

#### 4.12 Telegram links (specific, do not invent)
- Channel: `https://t.me/boostershop_tcg`
- Support bot: `https://t.me/BoosterShop_Support_bot` (or `@BoosterShop_Support_bot`)

#### 4.13 DS typography
- Column headings: `12px, font-weight 700, text-transform uppercase, letter-spacing 0.1em, color #F3F4F6`
- Column links: `13px, color #9CA3AF, display block, margin-bottom 10px`
- Column links hover: `color #fff`
- Footer background: `#0F1115`
- Bottom bar: `font-size 12px, color #6B7280`, with border-top `1px solid #1F2937`
- Mobile (≤768px): `grid-template-columns: 1fr 1fr`, brand block `grid-column: 1 / -1`

---

## 5. Do Not Touch

| Zone | Why |
|------|-----|
| `sitemap.xml` | GSC re-indexing in progress |
| `robots.txt` | Active, do not modify |
| `.htaccess` | Brotli/compression settings are stable |
| Redirects / canonical | Pending separate task |
| `checkout/checkout.twig` | HIGH-RISK — Hutko/Checkbox/payment |
| `checkout/cart.twig` | Separate task RD-11 |
| `common/cart.twig` (mini-cart) | Separate task RD-12 |
| `product/thumb.twig` | Separate task RD-04 |
| `product/product.twig` | Separate task RD-10 |
| Price logic / discount calculations | Never touch |
| Checkout/payment/fiscalization logic | Never touch |
| Merchant feed (`merchant-feed.tsv`) | Separate task |
| Schema / JSON-LD | Separate task |
| Product/category PHP controllers | Out of scope |
| First15 coupon logic | Active, tested, do not touch |
| Nova Poshta module | Do not touch |
| Account registration logic | Separate RD-17/20 |
| Search PHP controller / AJAX logic | Separate UX-009 |
| CRM / Google Sheets | Out of scope |

---

## 6. Likely Files / Areas

| File | Task | Notes |
|------|------|-------|
| `catalog/view/stylesheet/<THEME>/boostershop-ds.css` | RD-01 | **Verify path from server.** Main DS file, ~48KB as of 29.05. |
| `catalog/view/template/<THEME>/common/header.twig` | RD-01 (cache-bust), RD-02 | Contains `<link>` to boostershop-ds.css + header markup |
| `catalog/view/template/<THEME>/common/footer.twig` | RD-03 | Footer markup |

⚠️ **OC4 DB override risk:** Before patching `.twig` files, Codex MUST check table `ocp5_theme` for rows where `filename` matches `common/header.twig` or `common/footer.twig`. If a DB override row exists, the file edit will be silently ignored. In that case: either update the DB row, or delete the override and patch the file.

Query to check:
```sql
SELECT * FROM ocp5_theme WHERE filename IN ('common/header.twig', 'common/footer.twig');
```
If rows exist → update `code` column with new content, OR delete row and edit file.

---

## 7. Acceptance Criteria

All criteria must pass before marking Done.

### RD-01
- [ ] `:root` in `boostershop-ds.css` contains `--bs-r-sm: 6px`, `--bs-r: 10px`, `--bs-r-lg: 14px`
- [ ] `:root` contains `--bs-green: #16A34A`, `--bs-green-d: #15803D`
- [ ] `:root` contains `--bs-sh-sm`, `--bs-sh-md`, `--bs-sh-pop`
- [ ] `:root` contains z-index tokens
- [ ] Zero occurrences of `#1fa247` in `boostershop-ds.css` (grep to verify)
- [ ] Zero occurrences of `#18853a` in `boostershop-ds.css` (grep to verify)
- [ ] No gold/amber decorative backgrounds in header or chip default state
- [ ] `?v=` string in `header.twig` `<link>` updated to new value (e.g., `rd010203-20260530`)
- [ ] DevTools → Computed → `:root` on `<body>` shows correct token values

### RD-02
- [ ] Header background is `#ffffff`, border-bottom `1px solid #E5E7EB`
- [ ] Cart button uses green `#16A34A`, hover `#15803D`
- [ ] Cart button: count bold, price secondary (smaller / lower opacity)
- [ ] Breadcrumb ancestor links are grey `#6B7280`, not blue
- [ ] Breadcrumb current item is `#111827`
- [ ] Search field has `background: #F7F7F5`, `border: 1px solid #E5E7EB`, `border-radius: 6px`
- [ ] No gold-foam in header area (DevTools → Computed)
- [ ] Mobile (375px): header is compact, no horizontal overflow

### RD-03
- [ ] Footer has exactly 4 columns: Каталог / Інформація / Покупцю / Контакти
- [ ] Telegram channel link is `https://t.me/boostershop_tcg`
- [ ] Support bot link is `https://t.me/BoosterShop_Support_bot`
- [ ] Footer background is `#0F1115`
- [ ] Column headings are uppercase, `#F3F4F6`
- [ ] Column links are `#9CA3AF`, hover `#fff`
- [ ] Mobile (375px): 2-column grid, brand block full-width

---

## 8. QA / Smoke Test

After patch, run in order:

1. **Cache flush** — OpenCart admin → Dashboard → "Clear" cache (or via admin toolbar). Hard-refresh browser (Ctrl+Shift+R / Cmd+Shift+R).

2. **Home page load** (`https://boostershop.website/`):
   - DevTools → Network → verify `boostershop-ds.css` loads with new `?v=` string (not from cache)
   - DevTools → Console → zero errors
   - DevTools → Computed on `body` → `--bs-green` = `#16A34A`, `--bs-r` = `10px`

3. **Header visual check** (all pages: home, any category, any product):
   - White header, no gold tint
   - Cart pill: icon + count + secondary price
   - Breadcrumbs grey (not blue) on category/product pages
   - Search field is grey-background DS style

4. **Footer visual check** (home page bottom):
   - 4 columns visible on desktop
   - Telegram links present and correct (`t.me/boostershop_tcg`, `t.me/BoosterShop_Support_bot`)
   - Background dark `#0F1115`

5. **Mobile check** (375px via DevTools responsive):
   - Header: compact, no horizontal scroll
   - Footer: 2-column grid, brand block full-width

6. **Checkout smoke** — quickly verify checkout page still loads and cart button works:
   - Add any product to cart
   - Open cart → verify green CTA button still present and functional
   - Do NOT proceed to payment

7. **grep verification** (run on server or locally in CSS file):
   ```bash
   grep -in "#1fa247\|#18853a" boostershop-ds.css
   # Expected: no results
   ```

---

## 9. Rollback Note

**Backup available:** `backup-5.30.2026_19-02-08_boosters.tar.gz` (2026-05-30 19:02, ~170MB)

**Minimal rollback (CSS only):**
- Restore previous `boostershop-ds.css` from backup archive
- Revert `?v=` string in `header.twig` to previous value
- Flush OpenCart cache

**Twig rollback:**
- If `header.twig` or `footer.twig` break layout: restore from backup archive or from `ocp5_theme` table backup
- If DB override was modified: restore the original `code` value in `ocp5_theme`

**Risk level: LOW** — this patch is CSS tokens + shell component markup only. No logic, no price, no checkout.

---

## 10. Recommended Status After Execution

After Codex completes and owner verifies live:
- **RD-01** → Status: `Done`, Last Updated: date of completion
- **RD-02** → Status: `Done`, Last Updated: date of completion
- **RD-03** → Status: `Done`, Last Updated: date of completion

Owner manual verification checklist (what only the owner can confirm):
- [ ] Header looks correct on a real phone (iOS Safari / Android Chrome)
- [ ] Footer Telegram links open correctly in Telegram app
- [ ] No visual regressions noticed while browsing the store
- [ ] Cache fully cleared (not just admin cache — also CDN/browser if applicable)

Next task to unlock: **RD-04 — Product card system** (separate patch, separate QA).

---
_Handoff prepared: 2026-05-30 · Claude · booster-shop-ops/handoffs/_
