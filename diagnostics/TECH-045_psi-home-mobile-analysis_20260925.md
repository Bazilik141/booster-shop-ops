# PSI analysis — Home, 2026-09-25 22:21 (mobile 68 / desktop 97)

Author: Claude (Cowork) · Read-only diagnosis, no code changed.
Roadmap: `TECH-045` (Stage 2 render-blocking, pre-existing row, Not started). Images belong to
`TECH-044` (owner chose resizer→WebP, 2026-08-06). Handoff: `handoffs/handoff_TECH-045_render-blocking-fonts-icons_20260925.md`.
Inputs: `PageSpeed Insights_files/PageSpeed Insights.html` (owner save, both form factors
parsed separately), `backup-9.24.2026_16-35-03_boosters.tar.gz` (header.twig, home.twig,
footer.twig, CSS, .htaccess), `handoffs/handoff_TECH-013_mobile-cwv-stage1_20260804.md`.

**Evidence limits.** One PSI run, one URL (home). Per the TECH-013 measurement rule, no
metric movement may be claimed from a single run. The backup predates the scan by ~30 h:
live `boostershop-ds.css` is `?v=cat004-rd11-trust-cart-finish-20260924`, backup has
`?v=rd-pp-meta-20260924`. Head structure below is from the backup and must be re-checked on
live before a patch. CrUX has **no field data** for this page on either form factor — the
score is a lab simulation (Moto G Power, slow 4G 150 ms RTT / 1.6 Mbps, CPU 1.2x).

## 1. Numbers

| Metric | Mobile 25.09 | Desktop 25.09 | Mobile baseline 04.08 (handoff §2.0) |
|---|---|---|---|
| Performance | **68** | 97 | 62 (16.07) |
| FCP | **3.9 s** | 0.8 s | 4.1 s |
| LCP | **5.5 s** | 1.1 s | 8.8 s |
| Speed Index | 5.5 s | 0.9 s | 6.2 s |
| TBT | 0 ms | 10 ms | 10 ms |
| CLS | 0 | 0.003 | 0 |
| Render-blocking savings (LH estimate) | 2,560 ms | 830 ms | 3,080 ms |
| A11y / BP / SEO | 95 / 100 / 100 | 95 / 100 / 100 | 94 / 100 / 100 |

TECH-013 Stage 1 goal (mobile LCP ≤ 4.0 s, FCP ≤ 2.5 s) is **not met** on home. LCP moved a
lot, FCP barely: Stage 1 fixed images and cache, not the render-blocking chain.

## 2. Root cause — the page cannot paint until 13 head requests finish

Mobile LCP element = `category-tile-pokemon-540.webp` (eager, `fetchpriority=high`, found in
initial HTML — already correct). Observed LCP breakdown: TTFB 0 · load delay 380 ms · load
320 ms · **render delay 1,820 ms**. The image arrives early and then waits for CSS/fonts.
Server is not the problem (document response 2 ms, compression on, no redirects).

Render-blocking set on mobile (transfer / LH duration):

| # | Resource | KiB | ms | Needed for first paint of home? |
|---|---|---|---|---|
| 1 | `boostershop-ds.css` (180 KB raw, grows every release) | 35.1 | 1,980 | yes, but 28 KiB of it unused on home |
| 2 | Google Fonts CSS ×3 (Manrope, JetBrains Mono, IBM Plex Sans Condensed) | 4.3 | 2,250 | only Manrope |
| 3 | `bootstrap.css` (full BS5, 270 KB raw) | 29.9 | 1,390 | ~1 KiB of 29.5 used on home |
| 4 | `jquery-3.7.1.min.js` (sync by design, header.twig:41-45) | 33.5 | 1,390 | no, kept sync for live-search inline init + `chain` global |
| 5 | FontAwesome `all.min.css` | 25.9 | 1,780 | no — home uses one icon (`fa-chevron-up`, footer.twig:64) |
| 6 | `stylesheet.css`, `booster-typography.css`, `bs-faq.css`, `content-pages.css`, `ps_live_search.css` | 17.5 | 200–790 | partly; faq/content-pages/live-search CSS load on every route |

Findings by lever:

**F1 — Two decorative Google Fonts block every page.** `header.twig:62` JetBrains Mono is
used only by `.bs-menu__label` (ds.css:2678, 10.5 px labels inside the closed burger menu —
see `TECH-013_wp1-jetbrains-mono-check_20260805.md`). `header.twig:63` IBM Plex Sans
Condensed is used only by `.bs-h1-badge__eyebrow/__sub` (ds.css:3225/3240), rendered only in
`home.twig:34-37` (10 px, `aria-hidden`). IBM Plex was added after TECH-013. Each is a
cross-origin render-blocking stylesheet on every route for ~10 px text.

**F2 — Manrope comes via a two-origin chain** (fonts.googleapis.com CSS → fonts.gstatic.com
woff2, 3 files ~55 KiB). Self-hosting removes two cross-origin connection setups from the
critical path on slow 4G.

**F3 — FontAwesome costs ~181 KiB on every page** (`all.min.css` 26 KiB blocking +
`fa-solid-900.woff2` 155 KiB, the single largest transfer on home = 21% of 729 KiB). Home,
header and product thumb templates use one icon. FA is used widely in stock account /
checkout / SimpleCheckout / NovaPoshta templates (52 distinct `fa-*` tokens across ~50
templates), so removal is not an option — subset or non-blocking load.

**F4 — `bootstrap.css` and `boostershop-ds.css` ship whole on every route.** 82 KiB unused
CSS on home. Purging/critical-CSS is the biggest remaining lever but touches every page
including checkout (risky zone). Stage-2 scale; needs its own diagnosis.

**F5 — jQuery is synchronous on all routes.** Documented dependency (live-search inline
initialiser + `chain` global for checkout). Deferring on non-checkout routes needs the
initialiser moved behind `DOMContentLoaded`. Medium risk, Stage 2.

## 3. Secondary findings

- **Product thumbnails are PNG.** `image/cache/.../pokemon-storm-emeralda-M6-booster-pack-240x240.png`
  70.8 KiB, `OP-16 ...-Photoroom-240x240.png` 65.2 KiB — ~60 KiB savings each as WebP. Home
  has 4 thumbs; category pages have many, so the category benchmark URL is likely hit harder
  (not measured in this report). OpenCart keeps the source extension in `image/cache`.
- **Logo** `BS Big logo.png` 20.7 KiB shown at 103×32 (mobile) / 135×42 (desktop).
- **Tile `width`/`height` attributes are 1080×1080** while the served files are 540×540 and,
  on desktop, 800×450 (16:9). CLS is 0 because CSS sizes the box; harmless today, wrong
  intrinsic ratio if that CSS ever changes.
- **Unused preconnects** to `www.google.com` and `www.gstatic.com` (head child #45). Source
  not in any template or extension file in the backup — likely a DB-stored module output.
  Not located; low impact.
- **Cache TTL 7 d on images** is the deliberate TECH-013 WP3 policy (unversioned image
  paths) — not a defect. CSS/JS/fonts get 30 d.
- **Clarity**: 26 KiB, one 65 ms task at 2.9 s. Not material (TBT 0).
- `www.youtube.com/.../log_event` (4,009 ms) in the mobile dependency tree is not requested by
  the site — no YouTube reference in any template. PSI harness noise; ignore.

## 4. Accessibility 95 (not a performance factor)

- **Buy button contrast.** `.bs-btn-primary` = white on `--bs-green #16A34A` → 3.3:1, fails
  AA 4.5:1 for normal text. `--bs-green-d #15803D` gives 5.0:1 and stays green (purchase-colour
  rule preserved). Same audit also flags footer copy/hours/brand text and the cookie-consent
  buttons (colour values not in the saved report; measure on live).
- Heading order: `Рекомендовані товари` is `<h3>`, footer `КАТАЛОГ` is `<h4>` without the
  intermediate level.
- `aside.bs-mini-cart__panel role="dialog"` — `dialog` is not an allowed role on `<aside>`
  (also the only failure in the experimental "Agentic browsing" category). Use a `<div>`.

## 5. Work order

Owner decision 2026-09-25: JetBrains Mono and IBM Plex Sans Condensed are replaced by
Manrope; steps A–D go to a TECH-045 handoff. E is `TECH-044`. F stays in TECH-045 but needs
its own diagnosis first.

| Step | Change | Risk | Where |
|---|---|---|---|
| A | Drop JetBrains Mono + IBM Plex; labels use Manrope (F1) | Low | TECH-045 WP-A |
| B | Self-host Manrope woff2 + one preload (F2) | Low–Med | TECH-045 WP-B |
| C | FA `all.min.css` non-blocking off-checkout (F3) | Med | TECH-045 WP-C |
| D | Buy-button contrast, aside role, heading levels (§4) | Low | TECH-045 WP-D |
| E | Product thumbs → WebP | Med | TECH-044 |
| F | Critical CSS / Bootstrap purge; jQuery defer off-checkout (F4, F5) | High | TECH-045, later round |

Measurement gate before and after any step: 3 PSI runs × mobile + desktop × the three
TECH-013 benchmark URLs (handoff §2.1). Quote medians, label every number by page and device.
