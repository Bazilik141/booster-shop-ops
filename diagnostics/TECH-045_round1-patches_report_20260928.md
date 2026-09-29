# TECH-045 round 1 — five patches, render-blocking fonts/icons + home a11y

Date: 2026-09-28 · Executor: Claude Code (Opus, high) · Handoff: `handoffs/handoff_TECH-045_render-blocking-fonts-icons_20260925.md`
Owner decisions applied (2026-09-28, on `TECH-045_round1-stop-conditions_report_20260928.md`): 1(a) requisites → system
monospace; 2(a) WP-C0 inline-SVG cart icon; 3(a) «До каталогу» → `bs-btn-secondary`, then darken purchase green.
Live source: `diagnostics/tech045-live-20260928.tar.gz` + `tech045-live2-20260928.tar.gz` (repo root), extracted to
`live-snapshots/20260928_tech045-render-blocking/`. Every anchor below was counted on those files.

## 1. Patches — deploy in this order, one at a time, QA between

| # | File | Touches | Precondition checked by the runner |
|---|---|---|---|
| A | `patches/TECH-045_wpa-drop-decorative-fonts_20260928.php` | header.twig, boostershop-ds.css, content-pages.css | — |
| B | `patches/TECH-045_wpb-self-host-manrope_20260928.php` | header.twig; creates `catalog/view/stylesheet/fonts/manrope/` (3 woff2 + OFL.txt, embedded, SHA-256 checked) | WP-A marker |
| C0 | `patches/TECH-045_wpc0-cart-icon-svg_20260928.php` | booster-product-polish.js, booster-typography.css, header.twig | — |
| C | `patches/TECH-045_wpc-fontawesome-nonblocking_20260928.php` | header.twig | WP-C0 applied (no `fa-cart-shopping` left in the JS) |
| D | `patches/TECH-045_wpd-a11y-contrast-roles_20260928.php` | boostershop-ds.css, booster-typography.css, cart.twig, cookie.twig, header.twig | — |

Each patch header carries its root cause, override history, measurements and rollback. All follow conventions 1–8;
cache tokens are read and replaced wholesale (A, C0, D). No DB change, no `.htaccess`/sitemap/robots/schema/feed/
checkout-logic/jQuery/bootstrap.css change. No `!important` added (D edits the values of two pre-existing
`!important` mini-cart-trigger declarations; it replaces bootstrap's `!important` `bg-primary` utility on the cookie
button with a local class instead of overriding it). No `setTimeout`, no new `position`, no magic pixel values beyond
the 18 px SVG box.

**Local verification.** Full chain A→B→C0→C→D run on a copy of the live files, both LF and CRLF line endings (output
identical after EOL normalisation; endings preserved). Twig gate used the site's Twig 3.18 source from
`backup-9.24.2026`. Every runner re-run returns `already_applied=yes`; B refuses to run before A, C before C0; B aborts
without writing when a different file already sits at a font path. Rendered `header.twig` after the chain: `/` and
product → FontAwesome `media="print"` + `<noscript>`, checkout/checkout and checkout/cart → the original blocking tag;
0 `fonts.g*` references, 1 preload, 3 `@font-face` on every route. `scripts/check-php-host-compat.php`: clean;
`php -l` (8.3) clean. The real PHP 8.0 gate is the owner's `php -l` below.

## 2. Baseline PSI — before any patch

2026-09-28 17:42–17:53 GMT+3, pagespeed.web.dev, Lighthouse 13.5.0, 3 runs each. Medians; runs in brackets.
Benchmark URLs per TECH-013 handoff §2.1.

| Page · device | Perf | FCP s | LCP s | TBT ms | CLS | SI s | Render-blocking est. ms | A11y |
|---|---|---|---|---|---|---|---|---|
| Home · mobile | 66 [63 67 66] | 4.0 [4.1 4.0 4.0] | 5.9 [5.9 5.9 5.9] | 40 [50 10 40] | 0 | 5.5 [8.2 5.2 5.5] | 2,690 [2690 2680 2690] | 95 |
| Home · desktop | 97 [99 97 97] | 0.7 [0.7 0.8 0.7] | 1.1 [0.9 1.1 1.2] | 20 [20 20 10] | 0.001 [0.001 0.003 0] | 0.9 [1.0 0.9 0.8] | 490 [490 850 430] | 95 |
| Category · mobile | 68 [68 68 74] | 4.0 [4.0 4.0 3.0] | 5.5 [5.5 5.5 3.8] | 10 [20 0 10] | 0 [0 0 0.183] | 5.3 [5.4 5.3 3.0] | 2,510 [2510 2680 2050] | 92 |
| Category · desktop | 78 [80 75 78] | 0.7 [0.8 0.7 0.7] | 1.4 [1.4 1.4 1.3] | 10 [10 0 40] | **0.372** [0.283 0.417 0.372] | 1.0 [1.0 1.5 1.0] | 510 [740 490 510] | 92 |
| Product · mobile | 67 [66 67 67] | 4.4 [4.5 4.4 4.4] | 5.5 [5.8 5.5 5.5] | 0 | 0 | 5.2 [5.3 5.2 5.2] | 1,740 [3640 1740 1540] | 93 |
| Product · desktop | 99 [99 99 99] | 0.7 | 0.8 [0.8 0.8 0.9] | 10 [10 10 20] | 0.004 [0.018 0.004 0.004] | 0.9 [0.9 0.8 0.9] | 480 [480 480 490] | 93 |

Report IDs (mobile/desktop share one): home `wsobrv0l9q` `3ucvyk7olg` `1q7sc3ulq3`; category `t18in742nf`
`9t4go9ufww` `v9a172177k`; product `mckse5jg38` `k3cbl2k9m9` `0qkx9nvj5p`.

⚠ The CLS acceptance line (≤ 0.01 everywhere) is already failed **before** this round on category desktop (median
0.372, the known `TECH-043` defect) and once on category mobile (run 3, 0.183). For those two, the after-check is
"no worse than baseline", not ≤ 0.01.

### 2a. After round 1 (WP-A…D deployed, owner QA passed)

2026-09-29 11:59–12:13 GMT+3, same tool and method. Medians; runs in brackets; Δ = median vs baseline median.

| Page · device | Perf | FCP s | LCP s | TBT ms | CLS | SI s | Render-blocking est. ms | A11y |
|---|---|---|---|---|---|---|---|---|
| Home · mobile | 73 [73 73 95] Δ+7 | 3.2 [3.2 3.2 1.8] Δ−0.8 | 5.4 [5.4 5.5 2.7] Δ−0.5 | 10 [0 10 10] | 0 | 3.6 [4.0 3.6 1.9] Δ−1.9 | 580 [580 580 1340] Δ−2,110 | 98 Δ+3 |
| Home · desktop | 100 [100 99 100] Δ+3 | 0.4 Δ−0.3 | 0.8 [0.8 0.9 0.6] Δ−0.3 | 20 [20 40 20] | 0 | 0.8 [0.7 0.8 0.8] | 120 [100 120 230] Δ−370 | 98 |
| Category · mobile | 73 [96 73 73] Δ+5 | 3.0 [1.7 3.0 3.2] Δ−1.0 | 5.4 [2.6 5.6 5.4] Δ−0.1 | 20 [10 90 20] | 0 [0.049 0 0] | 3.6 [2.6 3.6 3.6] Δ−1.7 | 900 [1330 900 580] Δ−1,610 | 92 |
| Category · desktop | 82 [84 82 78] Δ+4 | 0.4 Δ−0.3 | 1.3 [1.3 0.7 1.4] Δ−0.1 | 10 [20 0 10] | **0.356** [0.252 0.371 0.356] | 0.7 [0.8 0.7 0.7] | 100 [190 100 100] Δ−410 | 92 |
| Product · mobile | 65 [65 69 65] Δ−2 | 3.8 [3.8 3.8 3.8] Δ−0.6 | **7.6** [7.6 5.4 7.6] **Δ+2.1** | 0 | 0 | 5.0 [5.0 5.1 5.0] Δ−0.2 | 1,740 [1740 580 1740] Δ0 | 93 |
| Product · desktop | 100 [100 100 100] Δ+1 | 0.4 Δ−0.3 | 0.6 [0.7 0.6 0.6] Δ−0.2 | 0 [20 0 0] | 0.004 [0.004 0.004 0.005] | 0.6 [1.0 0.6 0.6] | 100 [110 100 100] Δ−380 | 93 |

Report IDs: home `v8ry9kgrbh` `b4bsiu9i6h` `nyqiflnu89`; category `pxfa811r70` `rmr1ez2z5y` `vz67rlemz0`; product
`zd9aesq8os` `x27twzafcr` `fnjm8931uz`.

Acceptance:
- A/B/C met on all three URLs: the PSI mobile render-blocking list (home `b4bsiu9i6h`, category `rmr1ez2z5y`, product
  `fnjm8931uz`) contains no `fonts.googleapis.com`, `fonts.gstatic.com` or `all.min.css`. What remains is the
  first-party CSS set plus jQuery (the later round).
- D: home Accessibility 95 → 98.
- CLS: unchanged on every URL. Category desktop is still the pre-existing `TECH-043` level (0.356 vs 0.372 baseline).
- Target FCP ≤ 2.5 s / LCP ≤ 4.0 s: not reached on mobile medians (home 3.2 / 5.4, category 3.0 / 5.4, product 3.8 /
  7.6), as the handoff expected.
- ⚠ **Product mobile LCP median got worse, 5.5 → 7.6 s (2 of 3 runs).** The LCP element is the same product image
  (`MS1-500x500.webp`). In run `fnjm8931uz` its render delay was 1,920 ms, and the blocking list held only the
  first-party CSS and jQuery (boostershop-ds.css 1,540 ms, bootstrap.css and jQuery 1,160 ms each). None of the
  round-1 assets are on that list. The cause is not established. Candidates to test are the Manrope preload
  competing with the LCP image on this route, or run-to-run variance. Recommend a re-measure and a trace before the
  next round touches this page.

## 3. Findings recorded, not changed (owner decides whether to open tasks)

- **Preorder label.** `booster-product-polish.js` overwrites `#button-cart` unconditionally; live
  `/product/Duel-Masters-Boosters-DM24-RP3` (preorder) shows «У кошик» instead of the template's «Передзамовити».
  The `#product-info #button-cart` rule also paints the preorder button green regardless of `bs-btn-preorder`.
  WP-C0 keeps both behaviours as they are.
- **Non-purchase buttons still green (darker after WP-D)**: `checkout/cart_list.twig:43` and `product/category.twig:171`
  empty-state «continue», plus `checkout/success.twig:135` «Переглянути замовлення» (found in the second archive; same
  class of case as the owner's decision, left unchanged).
- **Heading order skipped** per handoff: styles are element-bound (`#content > .row.mb-3 + h3 + .row`,
  `.bs h3`, `.bs-footer__col h4`). PSI will keep flagging it on home.
- **Same role defect, not flagged today**: `header.twig:331` `<aside class="bs-menu__panel" role="dialog">` (hidden
  until the burger opens).
- **Contrast outside the audited page**: mobile toast (white on `--bs-green`, ds.css:5856) and `.bs-footer__legal`
  (#6B7280, ds.css:788).
- **Cookie decline button is invisible on live today**: white text on #F8F9FA (1.05:1). WP-D fixes it.

## 4. Owner run and QA

Per patch: upload to `~/public_html`, then

```bash
cd ~/public_html && php -l TECH-045_wpa-drop-decorative-fonts_20260928.php && php TECH-045_wpa-drop-decorative-fonts_20260928.php
```

(same form for B, C0, C, D). Expect `done=ok`; anything else is `done=failed` with nothing written or `restore=ok`.
After each: OpenCart theme cache refresh, Ctrl+F5; if a Twig change is not visible, check for a theme DB override
before patching again.

| After | Check |
|---|---|
| A | Burger menu open (390 px): «Каталог» / «Інформація» labels readable in Manrope. Desktop home badge «Твій TCG магазин» renders. Payment page requisites (copy fields) monospace. DevTools Network: no `JetBrains`/`IBM+Plex`. |
| B | DevTools Network on `/`: no `fonts.googleapis.com` / `fonts.gstatic.com`; `manrope-cyrillic.woff2?v=manrope-v20` from own domain, status 200, `font/woff2`. Ukrainian text and «₴» look as before. |
| C0 | Product page, desktop and mobile: «У кошик» shows the new cart icon, label in the same place; add to cart once — icon comes back after the loading state. |
| C | View source on `/`: FontAwesome link has `media="print"`; on `/index.php?route=checkout/checkout` it is the plain `<link>`. Account/login pages: icons may appear a moment after text on a cold load — expected. |
| D | Home: header «Мій кошик», card «Купити», footer bottom line, cookie bar both buttons readable. Mini-cart: open, close, swipe, remove item; empty cart shows «До каталогу» as a white button. Product «У кошик» darker green. Then **bs-checkout-smoke** (header/cart/cookie render on checkout). |

All: 390 / 768 / 1440 px on home, category, product, cart, checkout, account/login.

Rollback (any WP): copy that WP's files back from `_patch_backups/<patch>-<timestamp>/`, refresh the theme cache,
Ctrl+F5. WP-B: the `fonts/manrope/` directory may stay; it is inert once header.twig is restored.

## 5. Status

Patches ready, not deployed → Notion stays `In progress`. Dashboard `ROADMAP_TASKS` row TECH-045 set to
`active` / `Claude Code` / `2026-09-28` (the only dashboard change). Review: Claude (chat).
