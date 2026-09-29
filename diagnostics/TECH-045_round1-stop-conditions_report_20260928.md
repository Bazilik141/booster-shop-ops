# TECH-045 round 1 — stop conditions triggered before any patch

> Resolved by owner decisions 1a/2a/3a (2026-09-28). Patches and results: `diagnostics/TECH-045_round1-patches_report_20260928.md`.

Date: 2026-09-28 · Executor: Claude Code · Handoff: `handoffs/handoff_TECH-045_render-blocking-fonts-icons_20260925.md`
Live source: `diagnostics/tech045-live-20260928.tar.gz` (the handoff says repo root; it is in `diagnostics/`),
extracted to `live-snapshots/20260928_tech045-render-blocking/`.

**Verdict: no patch written.** Handoff stop conditions fire in WP-A, WP-C and WP-D. WP-B depends on
the WP-A outcome for its last step. Owner decisions are needed (§5). The baseline PSI (3 runs ×
mobile/desktop × 3 URLs) was not taken yet. It will be taken right before the patches are written,
so that before/after runs stay close together in time.

## 1. Anchors — live vs backup

`header.twig` live lines match the backup-based anchors exactly: `:54` icons, `:55`/`:56`
preconnects, `:57` Manrope, `:62` JetBrains Mono, `:63` IBM Plex, `:48-51` → live `:50-51`
(`bs_wp1_defer`). DS token live: `?v=cat004-rd11-trust-cart-finish-20260924`.
`boostershop-ds.css` `:2678`, `:3225`, `:3240`, `:108`/`:109` match. `cart.twig:11` matches.

## 2. WP-A — STOP: unlisted JetBrains Mono consumer

`catalog/view/stylesheet/content-pages.css:466`

```css
.bs-kv--copy .bs-kv__value{…font-family:'JetBrains Mono',ui-monospace,monospace;font-size:12.5px;font-weight:600;word-break:break-all}
```

This rule styles the copyable key/value fields (`.bs-copy-btn` next to them) in the PAY-003
payment-methods block of `content-pages.css`, i.e. the bank requisites on the payment information
page. The handoff lists only `ds.css:2678/3225/3240`. Removing `header.twig:62` would change this
page's requisites typeface too. No other `JetBrains`/`IBM Plex` reference exists in the archived
files. Note that the archive does not contain product, category or information templates, and
those templates may still reference either font inline.

## 3. WP-C — STOP: FontAwesome icon above the fold on the product page

`catalog/view/javascript/booster-product-polish.js:48` rewrites `#button-cart` to
`<i class="fa-solid fa-cart-shopping"></i><span>У кошик</span>`.

Measured live, `/product/Pokemon-boosters-Mega-Symphonia`, first viewport, scroll 0:

| Viewport | Icon top–bottom | Above the fold |
|---|---|---|
| desktop 1440×900 | 453–469 px | **yes** |
| mobile 390×844 | 900 px | no (just below) |

Home and `/catalog/Pokemon` at 390×844: no visible FA icon in the first viewport. `fa-home` and
`fa-filter` exist on the category page but have zero width, and `#back-to-top` (`fa-chevron-up`)
is `visibility:hidden; opacity:0` until the visitor scrolls. With a non-blocking `all.min.css`,
the add-to-cart button on desktop would paint without its icon first. The icon would then appear
and push the label sideways (a `<i>` has no width until FA CSS applies).

## 4. WP-D — STOP on `.bs-btn-primary`; headings skipped; `aside` clean

**`.bs-btn-primary` consumers** (archive-wide):

| Where | Label / action | Purchase? |
|---|---|---|
| `header.twig:323` + `cart.twig:10` | mini-cart trigger | yes |
| `cart.twig:12` | «Оформити замовлення» | yes |
| `cart.twig:12`, empty-cart state | **«До каталогу»** (`data-bs-mini-cart-close`) | **no** |
| `common.js:89` toast | «Переглянути кошик» / «Оформити замовлення» / «Відкрити кошик» (error) | yes (cart) |

The empty-cart «До каталогу» is a navigation action styled as purchase-green, so the handoff's stop
condition applies. Desktop toast (`ds.css` RD-12 block, ≥768 px) overrides the primary button to
white/ink, so it is unaffected by a background change. The archive has no product, category or
checkout templates, so consumers there are unverified.

**Heading order: skip, per handoff.** The styles target the elements, not classes:
`ds.css:792` and `:2246` `.bs-footer__col h4`; `ds.css:1299–1364`
`#content > .row.mb-3 + h3 + .row …` (the featured-products row layout keyed on the `<h3>`);
`ds.css:89/94` `.bs h3`. Changing the tag levels would restyle or break these rules.

**`cart.twig:11` aside → div: clear.** There is no `aside` element selector for the mini-cart in
any archived CSS or JS; the only `aside` selectors are `.bs-co-aside` (class). The swipe handlers
bind to `.bs-mini-cart__panel` (class).
Recorded, out of scope: `header.twig:331` `<aside class="bs-menu__panel" role="dialog">` has the
same role defect. It is `hidden` until the burger opens, so PSI/axe does not flag it today.

**Footer / cookie contrast:** not measured yet; it waits until WP-D is unblocked.

## 5. Owner decisions needed

| # | Decision | Options | Recommendation |
|---|---|---|---|
| 1 | Payment-page requisites after JetBrains Mono is dropped | (a) system monospace `ui-monospace, SFMono-Regular, Menlo, Consolas, monospace`: stays monospace, no download; (b) Manrope, like the menu labels; (c) keep JetBrains Mono, then WP-A is dropped | (a) |
| 2 | Add-to-cart icon vs non-blocking FA | (a) extend scope: replace the FA `<i>` in `booster-product-polish.js:48` with an inline SVG, the same pattern as the header icons, then FA can go non-blocking everywhere except checkout; (b) keep FA blocking on `product/product` too, so the gain applies to home and category only; (c) accept the icon popping in on desktop product | (a) |
| 3 | «До каталогу» in the empty mini-cart | (a) move it to `bs-btn-secondary` in the same `cart.twig` edit, then darken `.bs-btn-primary`; (b) darken anyway and leave «До каталогу» green as it is today | (a) |

WP-B proceeds after decision 1. If 1 = (c), JetBrains Mono stays on Google Fonts, so the two
Google preconnects stay and WP-B only self-hosts Manrope.
