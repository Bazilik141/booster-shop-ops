# HANDOFF TO CODEX — RD-07 (Category page parity) + RD-06 (Empty-state)
_Date: 2026-06-04 · Prepared by: Claude · Recipient: Codex_
_Source backup used for inspection: `backup-6.3.2026_21-17-59_boosters.tar.gz` (CSS cache-bust `rd04f-preorder-mobile-20260601` — confirmed identical to live `header.twig` link, so backup == live)._

> ⚠️ READ FIRST. The two previous breadcrumb patches (`rd010203g_desktop_breadcrumb_fix`, `rd010203h_breadcrumb_mobile_parity`) failed because they **stacked new `!important` overrides instead of removing the conflicting layer**. The fix below is **subtractive**, not additive. Do not add a new breadcrumb block. Remove the two experimental ones.

---

# PART A — RD-07: Category page parity (PRIMARY: breadcrumb ghost-divider fix)

## A1. Task ID
**RD-07 — Category page parity** · `product/category.twig` (+ `boostershop-ds.css`)
Roadmap: replaces R-03, UX-004. Depends on RD-01 (DS), RD-04 (card — done).
Priority: High. Blocking visual defect: **desktop breadcrumb "ghost divider".**

## A2. Context (grounded — live DOM + CSS cascade inspection done)

**Markup (live, `category.twig` lines 3-7):**
```twig
<div id="product-category" class="container">
  <ul class="breadcrumb">
    {% for breadcrumb in breadcrumbs %}
      <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
    {% endfor %}
  </ul>
```
`<body class="bs">`. On a category page the breadcrumb sits inside `#product-category.container`. Live example: `https://boostershop.website/catalog/Pokemon` renders 2 items (Головна · Pokémon).

**Root cause of the ghost divider — exact cascade result (desktop ≥769px, category page):**

There are SIX layered breadcrumb rule-sets in `boostershop-ds.css`, fighting each other:

| # | Marker / location | What it does | Specificity | Wins? |
|---|---|---|---|---|
| 1 | Bootstrap `.breadcrumb-item + .breadcrumb-item::before` | divider = `var(--bs-breadcrumb-divider)` | (0,2,1) | no |
| 2 | base `.bs .breadcrumb …` (~L1977-2009) | neutral text, `#9CA3AF` "·", DS-correct | (0,4,1) | no (desktop) |
| 3 | Fix 7b `.breadcrumb` (~L2719) | divider "›" | (0,1,0) | superseded |
| 4 | Fix 4 `.breadcrumb` (~L2796) | divider **"·"** | (0,1,0) | sets var only |
| 5 | **RD-G** `body.bs .breadcrumb > … + …::before` (~L2870-2952, `@media min-width:769px`, `!important`) | tries flat "·" separators | (0,4,2) !imp | **overridden by #6** |
| 6 | **RD-H** `body.bs #product-category.container > .breadcrumb > … + …::before` (~L2954-3090, `@media min-width:769px`, `!important`) | **green segmented tabs + skewed `::before`** | **(1,5,2) !imp** | **WINS** |

Because RD-H carries the `#product-category` **ID** (specificity (1,5,2) `!important`), it beats RD-G (0,4,2) and everything else on category pages. **RD-G is dead code on category pages.**

**The ghost divider = the RD-H separator pseudo-element.** Resolved computed style of `li.breadcrumb-item + li.breadcrumb-item::before` on desktop category page:
```
content: "";                 /* not "·" — empty */
position: absolute; z-index: 1;
left: -9px; top: -1px;
width: 18px; height: 24px;
background: #EEF7E8;                       /* pale green */
border-top: 1px solid #CFE6C6;
border-right: 1px solid #CFE6C6;           /* pale-green edge */
transform: skewX(-35deg); transform-origin: 100% 0;
```
That skewed pale-green parallelogram, absolutely positioned over the segment seam and clipped by the `<ul>`'s `overflow:hidden; height:24px`, is what reads as a faint diagonal "ghost" sliver. RD-H also turns each `<li>` into a green tab (`background:#EEF7E8; border:1px solid #CFE6C6`) and the first `<li>` into a 42px grey "home" cell (`#F8FAFC`) — i.e. the whole desktop breadcrumb is rendered as connected green chevron-tabs.

**This contradicts the Design System.** RD-02 / RD-07 spec = breadcrumbs **neutral text** (`#6B7280` / current `#111827`) with a subtle `·` separator — NOT green tabs. The green segmented look (RD-H) was an off-design experiment.

**Scope of the defect:**
- **Desktop (≥769px):** broken (green tabs + ghost). RD-H only matches `#product-category` and `main` → category pages (and any page whose breadcrumb is inside `main`).
- **Mobile (<769px):** there is **no** `max-width` segmented rule → breadcrumb already falls back to base + Fix4 = clean neutral "·" text. Mobile is fine. (Note: RD-H is mislabelled "mobile parity" but its rules are `min-width:769px` only.)

**JSON-LD `BreadcrumbList`** (`category.twig` L760-779) is independent of the `<ul>` CSS. The fix is CSS-only → schema/SEO untouched.

## A3. Goal
Desktop breadcrumb renders as **neutral text** matching mobile and the DS: `Головна · Pokémon`, separators `·` in `#9CA3AF`, last item darker (`var(--bs-ink)`, weight 500), no boxes, no green, **no ghost sliver**. Single source of truth — no competing `!important` layers.

## A4. What to change

**File:** `catalog/view/stylesheet/boostershop-ds.css` (static asset — edits apply directly; CSS is NOT in `ocp5_theme` overrides).

**Step 0 — MANDATORY pre-edit confirmation (do not skip — this is why prior patches were blind).**
Open `https://boostershop.website/catalog/Pokemon` at viewport ≥769px → DevTools → select the 2nd `li.breadcrumb-item` → Computed → expand `::before`. Confirm: `content: ""`, `position: absolute`, `background-color: rgb(238, 247, 232)` (#EEF7E8), `transform: matrix(...) skewX`. Confirm the `<li>` itself has `background:#EEF7E8; border:1px solid #CFE6C6`. This proves RD-H is the culprit **before** touching code.

**Step 1 — Remove the two experimental desktop blocks (delete by MARKER, not raw line numbers — numbers drift):**
- Delete the whole block starting at marker `/* ==== RD-01-02-03G desktop breadcrumb fix 20260601 ==== */` up to and including the closing `}` of its `@media (min-width: 769px){…}` (≈ lines 2870-2952).
- Delete the whole block starting at marker `/* ==== RD-01-02-03H breadcrumb mobile parity 20260601 ==== */` up to and including the closing `}` of its `@media (min-width: 769px){…}` (≈ lines 2954-3090).
- Stop **before** `/* ==== RD-04f mobile search tap/focus fix 20260601 ==== */` (do not touch RD-04f).

**Step 2 — Keep as the single source of truth (do NOT delete):**
- base `.bs .breadcrumb` / `.bs-breadcrumb` block (~L1977-2009)
- Fix 4 `.breadcrumb { --bs-breadcrumb-divider:"·" }` + `.breadcrumb-item + .breadcrumb-item::before { opacity:.45 }` (~L2796-2805)

  After removing G+H, the separator falls back to Bootstrap `content: var(--bs-breadcrumb-divider)` = **"·"**, styled by base (`#9CA3AF`, padding) + Fix4. That is the DS target.

**Step 3 — (Optional cleanup, low value, only if trivial):** Fix 7b (~L2719-2729) is superseded by Fix 4 (same selectors, later wins). Leaving it is harmless. Consolidating Fix 7b + Fix 4 into one block further reduces layering but is **not required** for the fix. If unsure, leave both.

**Step 4 — Cache-bust.** In `catalog/view/template/common/header.twig` change the breadcrumb/ds stylesheet version:
```
catalog/view/stylesheet/boostershop-ds.css?v=rd07-breadcrumb-20260604
```
⚠️ `header.twig` IS a twig → run the DB-override check first:
```sql
SELECT * FROM ocp5_theme WHERE filename = 'common/header.twig';
```
If a row exists, update its `code` too (or delete the row and edit the file). Otherwise edit the file directly.

**Step 5 — Post-edit confirmation (DevTools, ≥769px, /catalog/Pokemon):**
- `li.breadcrumb-item + li.breadcrumb-item::before` → computed `content` is `"·"`, no `transform`, no `background`.
- `li.breadcrumb-item` → no `background`, no `border`.
- Breadcrumb is neutral text, `·` separators; last item darker. Same look as mobile.

## A5. Do not touch
- JSON-LD `BreadcrumbList` script in `category.twig` (L~760-779) — leave exactly as is.
- `sitemap.xml`, `robots.txt`, `.htaccess`, redirects, canonical, hreflang.
- Merchant feed, Product schema / any other JSON-LD.
- Checkout, payment, Hutko/Checkbox, fiscalization, First15.
- RD-04f mobile-search block and any non-breadcrumb CSS.
- The breadcrumb **markup** in `category.twig` (`<ul class="breadcrumb">…`) — fix is CSS-only.
- Product grid, toolbar chips, sort `<select>`, sidebar filter markup (already DS — see A7 parity checks; verify only).

## A6. Likely files / areas
| File | Change | Confidence |
|---|---|---|
| `catalog/view/stylesheet/boostershop-ds.css` | Remove RD-G + RD-H breadcrumb blocks | confirmed (markers present) |
| `catalog/view/template/common/header.twig` | cache-bust `?v=` only | confirmed |
| `ocp5_theme` (DB) | check override on `common/header.twig` | verify |

## A7. Acceptance criteria (measurable)
- [ ] Desktop `/catalog/Pokemon` (≥769px): breadcrumb is plain text `Головна · Pokémon` — no green fill, no boxes, no skewed sliver.
- [ ] DevTools: `li.breadcrumb-item + li.breadcrumb-item::before` computed `content` == `"·"`; no `transform`; no `background-color`.
- [ ] DevTools: `li.breadcrumb-item` computed has no `background-color` and no `border`.
- [ ] Separator colour `#9CA3AF` (rgb(156,163,175)); last-item colour = `var(--bs-ink)`, font-weight 500.
- [ ] Mobile (<769px) breadcrumb unchanged (still neutral "·" text) — no regression.
- [ ] Same neutral breadcrumb on a product page and a subcategory page (consistency: RD-H no longer overrides only category).
- [ ] `boostershop-ds.css` loads with new `?v=rd07-breadcrumb-20260604` (Network tab).
- [ ] `grep -c "skewX" boostershop-ds.css` == 0; `grep -c "RD-01-02-03H" boostershop-ds.css` == 0; `grep -c "RD-01-02-03G" boostershop-ds.css` == 0.
- [ ] Category header parity intact (verify, should already pass): 4px brand strip (`.bs-cat-header__strip`), neutral subcategory chips, DS `.bs-select` sort, sidebar filter without gold header.
- [ ] View-source: `BreadcrumbList` JSON-LD unchanged; Rich Results test still valid.

## A8. QA / smoke test (owner, after patch)
1. OpenCart admin → clear cache. Hard refresh (Ctrl+Shift+R).
2. Desktop `/catalog/Pokemon`: breadcrumb = neutral text, no ghost, no green tabs.
3. Repeat on `/catalog/One-Piece` and a leaf subcategory (e.g. `…/Pokemon-boosters`) and one product page → all breadcrumbs consistent.
4. Mobile width (375px): breadcrumb still clean.
5. DevTools confirm the computed `::before` checks in A7.
6. `https://search.google.com/test/rich-results` on the category URL → BreadcrumbList still detected, no new errors.
7. No SEO action needed (CSS-only) — but confirm canonical/robots unchanged out of caution.

## A9. Rollback note
Single-file CSS change. Rollback = restore `catalog/view/stylesheet/boostershop-ds.css` and `common/header.twig` from `backup-6.3.2026_21-17-59_boosters.tar.gz` (paths: `homedir/public_html/catalog/view/stylesheet/boostershop-ds.css`, `…/template/common/header.twig`), revert `ocp5_theme` header row if edited, clear cache. Risk: **LOW** (visual, no logic).

## A10. Recommended status after execution
RD-07 → `Done` once A7 passes live. Note in Notion: "Breadcrumb de-layered — removed RD-G/RD-H experimental blocks, single DS source of truth (neutral · text)."
SEO risk: **LOW** (per `bs-seo-risk-gate`: CSS-only, no sitemap/robots/canonical/schema). No `bs-checkout-smoke` / `bs-merchant-schema-qa` needed.

---

# PART B — RD-06: Empty-state component (`bs-empty`)

## B1. Task ID
**RD-06 — Empty-state component** (shared). Roadmap: new (UX-037). Reusable across cart, search, empty category.

## B2. Context (grounded)
There is **no shared empty-state component** today. Current states are unstyled / text-only:
- **Empty category** — `category.twig` (L119-122): `{% if not categories and not products %} <p>{{ text_no_results }}</p> <div class="text-end"><a href="{{ continue }}" class="btn btn-primary">{{ button_continue }}</a></div>` → plain Bootstrap, off-DS.
- **Search no-results** — `product/search.twig`: renders `text_empty` plainly.
- **Cart empty (page)** — `checkout/cart.twig`: OC4 `text_empty`.
- **Mini-cart empty** — `common/cart.twig`: dropdown shows "Кошик порожній!" (controller/AJAX-rendered — verify render path before editing).

## B3. Goal
One reusable `.bs-empty` block (icon + title + subtext + optional CTA), applied to the four spots above, DS-styled, mobile-safe.

## B4. What to change
**CSS — add to `boostershop-ds.css`** (new section, before RD-04f marker):
```
.bs-empty { … centered column, max-width 420px, margin auto, padding 40px 16px }
.bs-empty__icon  { 48px, color var(--bs-ink-4) }
.bs-empty__title { 18px/600, var(--bs-ink) }
.bs-empty__text  { 14px, var(--bs-ink-3) }
.bs-empty__cta   { reuse .bs-btn.bs-btn-primary }
```
**Markup — replace per file (text variables stay; only wrapper/visual changes):**
- `category.twig` empty branch → `bs-empty` with `{{ text_no_results }}` + CTA `{{ continue }}` / `{{ button_continue }}`.
- `search.twig` → wrap `{{ text_empty }}` in `bs-empty` (CTA → continue shopping / catalog).
- `checkout/cart.twig` → wrap empty `{{ text_empty }}` in `bs-empty` (CTA → continue).
- `common/cart.twig` mini-cart → **verify render path first** (if empty HTML is built in controller/JS, the change may belong there or in a JS template, not the twig). Flag if so; do not break the AJAX dropdown.

Run the `ocp5_theme` DB-override check for each twig you edit (same as A4 Step 4).

## B5. Do not touch
- Cart AJAX (`data-oc-*`), qty logic, coupon/First15, checkout/payment/fiscalization.
- Controller text-variable values (use existing `text_empty` / `text_no_results` / `button_continue`).
- `sitemap.xml`, `robots.txt`, canonical, schema, Merchant feed.
- Add-to-cart form on product cards.

## B6. Likely files / areas
`boostershop-ds.css` (add `.bs-empty`), `product/category.twig`, `product/search.twig`, `checkout/cart.twig`, `common/cart.twig` (verify), `ocp5_theme` (override checks), `header.twig` (cache-bust if CSS changed — reuse the RD-07 `?v=`).

## B7. Acceptance criteria
- [ ] Empty category page shows `bs-empty` (icon + title + subtext + CTA), centered, DS typography — no bare Bootstrap `<p>`/`btn`.
- [ ] Search with no results shows the same component.
- [ ] Empty cart page shows the same component; "continue shopping" CTA works.
- [ ] Mini-cart empty state still renders correctly via its existing path (no broken dropdown).
- [ ] Mobile (375px): centered, no overflow.
- [ ] No change to cart count / add-to-cart behaviour.

## B8. QA / smoke test
1. Clear cache, hard refresh.
2. Trigger empty category (filter to 0 results) → `bs-empty` shows.
3. Search nonsense query → `bs-empty` shows.
4. Empty the cart → cart page + mini-cart empty states OK.
5. Add a product → cart updates normally (AJAX intact).
6. Mobile check.

## B9. Rollback note
Restore the edited twig(s) + `boostershop-ds.css` from `backup-6.3.2026_21-17-59_boosters.tar.gz`, revert `ocp5_theme` rows if edited, clear cache. Risk: **LOW** (visual). Cart files are near checkout but **no logic touched** — a basic add-to-cart + empty-cart visual check is sufficient; full `bs-checkout-smoke` not required.

## B10. Recommended status after execution
RD-06 → `Done` once B7 passes. If mini-cart empty must be wired in controller/JS, split that into a follow-up sub-task and mark RD-06 `Partial` until done.

---

## Cross-task notes
- RD-07 (breadcrumb) is **CSS-only** and independent; do it first — it unblocks the visible defect fastest and carries the lowest risk.
- RD-06 touches `category.twig` (same file as RD-07's host page but a different region — empty branch vs breadcrumb `<ul>`). No conflict; can be the same deploy or separate.
- Both share one cache-bust bump if CSS changes are deployed together.
- If anything in the actual code differs from the line ranges above (line drift, extra overrides), **Codex should verify against actual project files and the MARKER comments, not the numbers.**

_Prepared 2026-06-04 · Claude · booster-shop-ops/handoffs/_
