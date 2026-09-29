# Handoff — TECH-045 Stage 2, round 1: render-blocking fonts and icons

Date: 2026-09-25 · Parent: TECH-013 (Done 2026-08-06) · Notion: `3b46bf20-bdb4-81a7-aae0-fc4b4a828977`
Executor: Claude Code · model=Opus · effort=high — **owner decision 2026-09-28.**
Why: needs live-file discovery (the backup is older than live `boostershop-ds.css`), downloading
and verifying font files, and PSI/DevTools measurement; Claude Code executed all four TECH-013
packages in the same files.

Evidence: `diagnostics/TECH-045_psi-home-mobile-analysis_20260925.md` (read §2–§4 first).

## 1. Task ID
`TECH-045` — work packages WP-A, WP-B, WP-C, WP-D of this handoff. Four separate patch files.

## 2. Context
Home, mobile PSI 2026-09-25: score 68, FCP 3.9 s, LCP 5.5 s, TBT 0, CLS 0. Desktop 97. The LCP
image arrives by ~0.7 s and then waits 1.8 s for render-blocking CSS and fonts. The head blocks
on 13 requests, including three cross-origin Google Fonts stylesheets (two of them for 10 px
decorative text) and the full FontAwesome stylesheet (home uses one icon).

Owner decision 2026-09-25: JetBrains Mono and IBM Plex Sans Condensed are dropped; their text
renders in Manrope.

⚠ **Live is newer than the backup.** Live `boostershop-ds.css` is
`?v=cat004-rd11-trust-cart-finish-20260924`; `backup-9.24.2026_16-35-03` has
`?v=rd-pp-meta-20260924`. Every anchor below is from the backup. Before writing a patch,
obtain the live `header.twig`, `boostershop-ds.css`, `footer.twig`, `cart.twig` from the owner
(cPanel File Manager → Download) and re-verify each anchor and count.

**Live source for this round (owner, 2026-09-28):** `tech045-live-20260928.tar.gz` in the repo
root (gitignored). Paths are relative to `public_html`. Extract to
`live-snapshots/20260928_tech045-render-blocking/` — never into the repo tree it mirrors.
Contents: `common/{header,footer,home,cart,cookie}.twig`, `controller/common/header.php`,
`catalog/view/stylesheet/*.css`, `catalog/view/javascript/*.js`, featured-module twig. Anchors
in this handoff are backup-based; the archive wins on any difference. If a needed file is
missing, stop and ask the owner for it.

## 3. Goal
Remove fonts and icon CSS from the render-blocking path without visual change beyond the
approved font swap. Target inherited from TECH-013 (mobile FCP ≤ 2.5 s, LCP ≤ 4.0 s on the
three benchmark URLs); this round is not expected to reach it alone — report measured medians,
do not promise the target.

## 4. What to change

### WP-A — drop JetBrains Mono and IBM Plex Sans Condensed (LOW)
- `catalog/view/template/common/header.twig:62` and `:63` (backup lines) — remove both Google
  Fonts `<link>` tags. Anchor count exactly 1 each.
- `catalog/view/stylesheet/boostershop-ds.css:2678` `.bs-menu__label` and `:3225`, `:3240`
  `.bs-h1-badge__eyebrow/__sub` — change the family to the DS Manrope stack
  (`'Manrope', system-ui, sans-serif`). Edit the source rules; no new overrides, no `!important`.
  Anchor count must match live; grep live for any other `JetBrains`/`IBM Plex` reference and
  stop if one exists that is not listed here.
- Cache-bust `boostershop-ds.css` per AGENTS.md convention 8 (read the token, replace wholesale).

### WP-B — self-host Manrope (LOW–MEDIUM)
- Add Manrope woff2 files under `catalog/view/stylesheet/fonts/manrope/` (new directory). Use the
  same files Google serves for the current request (weights 400–800 are all used in DS CSS;
  Google serves a variable font split by unicode range). Keep cyrillic and latin subsets; include
  latin-ext only if live pages use it — executor verifies.
- `@font-face` rules with `font-display: swap`, matching `unicode-range` and a weight range.
  Placement (top of `boostershop-ds.css` or the existing inline `<style>` in `header.twig`) is the
  executor's choice — state why in the patch description.
- One `<link rel="preload" as="font" type="font/woff2" crossorigin>` for the cyrillic subset only.
- Remove `header.twig:57` Manrope Google link and the two Google preconnects `:55`, `:56` —
  only once WP-A and WP-B leave no other Google Fonts request. Verify on live.
- Font URLs must carry a version query (`?v=`): `.htaccess` WP3 gives woff2 a 30-day TTL.
- Licence: Manrope is SIL OFL — keep its licence text next to the files.

### WP-C0 — product cart-button icon to inline SVG (LOW) — scope extension added 2026-09-28 (Claude recommendation; applies once the owner confirms it to the executor)
Executor finding: `booster-product-polish.js:48` injects `<i class="fa-solid fa-cart-shopping">` into
`#button-cart`; on desktop it is above the fold, so WP-C alone would make the label jump when FA
CSS arrives. This icon also depends today on the 155 KiB `fa-solid-900.woff2`.
- `catalog/view/javascript/booster-product-polish.js:48` — replace the `<i>` with an inline
  `<svg width="16" height="16" … fill="currentColor" aria-hidden="true" focusable="false">` cart
  glyph (same pattern as the header SVGs, `header.twig:251+`). Fixed width/height so the button
  never reflows.
- `booster-typography.css:209` `#product-info #button-cart i` — add the equivalent sizing rule for
  the SVG; keep the `i` rule (harmless).
- Separate patch file, deployed **before** WP-C. The JS file has no `?v=` today — add a
  cache-bust to its `<script>` in `header.twig:67` per convention 8.
- ⚠ Same line, unverified on live: the script overwrites `#button-cart` unconditionally, while
  the backup `product.twig:371` renders preorder products with the label `Передзамовити`. If a live
  preorder product shows «У кошик», do **not** fix it in this patch — report it; the owner decides.

### WP-C — FontAwesome off the critical path, non-checkout routes only (MEDIUM)
- `header.twig:54` `<link href="{{ icons }}" …>` — on routes where `bs_wp1_defer` is `' defer'`
  (already defined at `header.twig:48-51`), load the stylesheet non-blocking (preload/print-media
  swap pattern) with a `<noscript>` fallback. Checkout routes keep today's blocking `<link>`.
- Do not change `catalog/controller/common/header.php:45` or the FA files.
- Any icon visible above the fold on home/category/product must be listed in the patch
  description with a before/after screenshot; if one exists, stop and report instead of shipping.
- Subsetting `fa-solid-900.woff2` is **out of scope** for this round (next round, after measuring
  WP-C).

### WP-D — accessibility defects from the same report (LOW)
- ⚠ The product-page buy button is NOT styled by `.bs-btn-primary`: `booster-typography.css:179-192`
  `#product-info #button-cart` hardcodes `background/border-color: #16a34a` (hover `#15803d`).
  Fix both rules, or the product page keeps 3.3:1.
- `boostershop-ds.css:108` `.bs-btn-primary` — background to `var(--bs-green-d)` (#15803D, 5.0:1
  with white; today #16A34A = 3.3:1). Hover needs a darker green than `--bs-green-d`; propose the
  value in the patch description. Green stays green — purchase-colour rule preserved. Check every
  other consumer of `.bs-btn-primary` before changing; if non-purchase actions use it, stop and
  report.
- `catalog/view/template/common/cart.twig:11` — `<aside class="bs-mini-cart__panel" role="dialog">`
  → `<div …>`. First grep CSS/JS for `aside.bs-mini-cart` / element selectors; if any exist, report.
- Footer text and cookie-consent buttons also fail contrast; the saved report has no colour
  values. Measure on live (DevTools / axe), fix only the failing elements, list each with
  before/after ratio.
- Heading order (`Рекомендовані товари` `<h3>`, footer `<h4>` at `footer.twig:13,20,27,34`):
  fix only if the styles target classes, not the element. Otherwise report and skip.
- Cache-bust per convention 8.

## 5. Do not touch
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess` (including the TECH-013 WP3 block)
- checkout, payment, Hutko, Checkbox/fiscalization, Nova Poshta, SimpleCheckout templates
- Merchant feed, schema / JSON-LD
- jQuery loading and `common.js` / `chain` global (header.twig:41-45 comment) — later round
- `bootstrap.css` and any CSS rule removal or purge — later round, own diagnosis
- product/category images and the image resizer — `TECH-044`
- the Clarity snippet and the `{% for analytic in analytics %}` block
- the unused `www.google.com` / `www.gstatic.com` preconnects — source not located; record only

## 6. Likely files / areas (verify against live)
`catalog/view/template/common/header.twig`, `catalog/view/stylesheet/boostershop-ds.css`,
`catalog/view/stylesheet/fonts/manrope/*` (new), `catalog/view/template/common/cart.twig`,
`catalog/view/template/common/footer.twig`, `catalog/view/template/common/cookie.twig`,
featured-module template (executor locates).

## 7. Acceptance criteria
- A: no request to `fonts.googleapis.com` for JetBrains Mono or IBM Plex on `/`, `/catalog/Pokemon`,
  product benchmark URL; `.bs-menu__label` (menu open) and `.bs-h1-badge__eyebrow` compute to Manrope.
- B: no request to `fonts.googleapis.com` or `fonts.gstatic.com` on the three URLs; Manrope
  `status: "loaded"` from own origin; Cyrillic text renders in Manrope (screenshot).
- C: on the three URLs `all.min.css` is absent from PSI mobile "render-blocking requests"; on
  `/index.php?route=checkout/checkout` it is still a blocking `<link>` (view-source).
- D: PSI Accessibility on home shows no "insufficient contrast" and no "ARIA role" failure;
  `.bs-btn-primary` white-text contrast ≥ 4.5:1.
- All WPs: CLS stays ≤ 0.01 mobile and desktop on all three URLs.
- Measurement: 3 PSI runs × mobile + desktop × three URLs, before and after, medians in the
  report, every number labelled by page and device.

## 8. QA / smoke test (owner, after each deploy)
- Hard refresh (Ctrl+F5) home, category, product, cart, checkout, account/login on mobile 390 px,
  tablet 768 px, desktop 1440 px; compare with before-screenshots.
- Open the burger menu: section labels visible and readable.
- Home: badge «Твій TCG магазин» renders.
- WP-C and WP-D touch `header.twig` / `cart.twig`, which render on checkout → run
  `bs-checkout-smoke` once after the last of them is deployed. Mini-cart: open, close, swipe,
  remove item.
- OpenCart theme cache refresh after any Twig change; if a Twig change is not visible, check for
  a theme DB override before patching again.

## 9. Rollback
Each patch follows AGENTS.md conventions 1–8: backup to `_patch_backups/<patch>-<ts>/`, `php -l`
gate, `already_applied` marker, self-delete. Rollback = restore that WP's files from its backup
folder, refresh the theme cache, Ctrl+F5. WP-B additionally: the new `fonts/manrope/` directory
may stay on disk — it is inert once the `@font-face`/preload are reverted. No DB changes.

Delivery: `patches/TECH-045_<wp-slug>_<YYYYMMDD>.php`, one per WP, order A → B → C0 → C → D. Owner
uploads to `~/public_html` and runs `php <patch>.php`. The executor never commits, pushes or
deploys. Report: `diagnostics/TECH-045_<slug>_report_<YYYYMMDD>.md`.

## 10. Recommended status after execution
Dashboard mirror (handed to the executor, Notion already updated 2026-09-28): in
`dashboard/booster-dashboard.html` `ROADMAP_TASKS`, row `TECH-045` → `status: 'active'`,
`tool: 'Claude Code'`, `lastUpdated: '2026-09-28'`. Only this row; check the file's latest diff
first.

Patches ready, not deployed → Notion stays `In progress`. Deployed, awaiting QA → `In progress`.
Closure only on owner authorization. Notion is written by Claude (chat); the executor updates
`ROADMAP_FLOW` only as part of an authorized roadmap-affecting patch.

---

# Round 2 — purchase-button colour and sizes (owner decisions 2026-09-29)

Round 1 (WP-A…D) deployed 2026-09-28/29, owner QA passed. Owner feedback on WP-D: `#15803D` on
purchase buttons reads too heavy. One CSS-only work package, **WP-E**, one patch file.
Executor: Claude Code (same as round 1).

**Before writing WP-E:** take the round-1 "after" PSI set (3 runs × mobile + desktop × three
benchmark URLs, medians, labelled) and add it to the round-1 report, so round 2 does not blur
the round-1 result. Live source for round 2: `tech045-live3-20260929.tar.gz` (owner, repo root
or `diagnostics/`), extracted to `live-snapshots/20260929_tech045-round2/`. Round-1 snapshots are
stale for every file WP-A…D touched.

## WP-E scope
1. **Purchase green.** Base `#12883E` (4.55:1 with white — the lightest same-hue green that passes
   AA), hover/focus/open state `#15803D` (the current base). Apply to every purchase/cart
   control: `.bs-btn-primary`, header mini-cart trigger (base and `:hover/:focus/.show`),
   `#product-info #button-cart` (+ `:hover/:focus`), `.mini-cart-checkout-btn`
   (`boostershop-ds.css` ~2230), checkout confirm button (`#checkout-checkout.bs-co
   #checkout-confirm …`, ds.css ~4872, currently still `--bs-green`). Add a token (name is the
   executor's choice) instead of repeating the hex. Do not change `--bs-green` itself (it also
   colours text, borders and badges). Remove `--bs-green-dd` if nothing uses it afterwards.
2. **Checkout confirm font:** 15px → 17px (same rule). Label must stay on one line at 390 px.
3. **Card «Купити»** (`.bs-pcard__buy-btn`, thumb.twig): 14.5px/600 → 16px/700 on desktop. Check
   the mobile rule (ds.css ~500, 13px) and choose a mobile size that stays on one line in the
   2-column grid at 360 and 390 px; state the value and why. Product-page «У кошик» unchanged.
4. **`checkout/success.twig:135` «Переглянути замовлення»** — text is dark navy on green today.
   Find the rule that overrides `.bs-btn-primary { color:#fff }` there (name it, file:line) and
   fix at the source so the label is white in all states. No `!important` unless the patch header
   justifies it.

Out of scope, unchanged: preorder label/colour, mobile toast, `.bs-footer__legal`, heading order,
`bs-menu__panel` aside role, anything non-CSS.

## Acceptance
- White-on-base contrast ≥ 4.5:1 for every control in item 1 (list each with its ratio).
- PSI Accessibility on home ≥ 98, no contrast failure.
- Checkout confirm 17px, one line at 390 px; card «Купити» one line at 360/390/768/1440 px.
- Success page button label white (normal, hover, focus).
- CLS no worse than the round-1 after-set.

## QA / rollback
Owner: home, category, product, mini-cart (full and empty), checkout, success page at
390 / 768 / 1440 px, then **bs-checkout-smoke** (checkout CSS changed). Rollback = restore WP-E's
files from `_patch_backups/<patch>-<ts>/`, theme cache refresh, Ctrl+F5.
Delivery: `patches/TECH-045_wpe-purchase-green-sizes_<YYYYMMDD>.php`, conventions 1–8.
