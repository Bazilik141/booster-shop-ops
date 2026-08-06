# TECH-013 — Orientation report (Stage 1, pre-WP1)

Date: 2026-08-05 · Executor: Claude Code (Opus, thinking=high) · Owner: Raccoon
Handoff: `handoffs/handoff_TECH-013_mobile-cwv-stage1_20260804.md` (Rev. 2026-08-05, canonical)
Evidence source: `backup-8.5.2026_10-49-27_boosters.tar.gz` (cPanel, 2026-08-05 10:49 GMT+3)

**No patch code written. No file in `public_html` touched. Nothing committed.**
This document is read-only evidence for WP1–WP4.

---

## Method

The archive was indexed once (`tar -tzf`, 22,794 entries, 9,035 under `public_html`), then
only the named files were extracted to a temp directory outside the repository. The archive
was never unpacked into the repo. Two bounded reads were made against
`mysql/boosters_ocart49.sql` (streamed, `config_theme` / `config_logo` rows only).

---

## a) Active theme and real file paths

`config_theme` = **`basic`** (from `oc_setting`: `(10096,0,'config','config_theme','basic',0)`).

OpenCart 4.1 resolves theme `basic` to the base template root, **not** to
`catalog/view/theme/<name>/template/`. Real live paths:

| Asset | Real path (relative to `~/public_html`) | Bytes |
|---|---|---|
| Head template | `catalog/view/template/common/header.twig` | 22,231 |
| Footer template | `catalog/view/template/common/footer.twig` | 5,329 |
| Head controller | `catalog/controller/common/header.php` | — |
| Home | `catalog/view/template/common/home.twig` | 7,213 |
| Category | `catalog/view/template/product/category.twig` | 34,560 |
| Product | `catalog/view/template/product/product.twig` | 58,619 |
| Product card | `catalog/view/template/product/thumb.twig` | — |
| Theme CSS root | `catalog/view/stylesheet/` | — |

`catalog/view/theme/` exists but contains only
`default/template/smart_filter/{smart_category,smart_filter}.twig` — two filter-module
overrides, unrelated to this task. There is **no** `catalog/view/theme/basic/` directory.

Head asset variables resolved in `catalog/controller/common/header.php`:

```
:44  $data['bootstrap']  = 'catalog/view/stylesheet/bootstrap.css';
:45  $data['icons']      = 'catalog/view/stylesheet/fonts/fontawesome/css/all.min.css';
:46  $data['stylesheet'] = 'catalog/view/stylesheet/stylesheet.css';
:49  $data['jquery']     = 'catalog/view/javascript/jquery/jquery-3.7.1.min.js';
:52  $data['styles']     = $this->document->getStyles();
:53  $data['scripts']    = $this->document->getScripts('header');
:65  $data['logo']       = $this->config->get('config_url') . 'image/' . $this->config->get('config_logo');
```

---

## b) Render-blocking inventory — current vs handoff §2A (2026-07-16)

Verbatim from `catalog/view/template/common/header.twig`, in document order.

| # | Line | Asset | Blocking? | vs §2A |
|---|---|---|---|---|
| 1 | 41 | `jquery-3.7.1.min.js` (`{{ jquery }}`) | **JS, sync** | unchanged |
| 2 | 43 | `bootstrap.css` (`{{ bootstrap }}`) — 270,584 B raw | CSS | unchanged |
| 3 | 44 | `all.min.css` FontAwesome (`{{ icons }}`) — 104,502 B raw | CSS | unchanged |
| 4 | 45 | `preconnect` fonts.googleapis.com | hint | **duplicate** (see 8) |
| 5 | 46 | `preconnect` fonts.gstatic.com (crossorigin) | hint | **duplicate** (see 9) |
| 6 | 47 | `boostershop-ds.css?v=toc003-pay001-phase2c-20260725` — 158,573 B raw | CSS | unchanged |
| 7 | 48 | `bs-faq.css?v=rd05b-faq-20260604` — 5,264 B | CSS | unchanged |
| 8 | 49 | `content-pages.css?v=pay001-info-20260726` — 11,052 B | CSS | unchanged |
| 9 | 50 | `stylesheet.css?v=rd10-…-20260611e` — 39,611 B | CSS | unchanged |
| 10 | 51 | `preconnect` fonts.googleapis.com | hint | **exact duplicate of line 45** |
| 11 | 52 | `preconnect` fonts.gstatic.com | hint | **exact duplicate of line 46** |
| 12 | 53 | Google Fonts `Inter:wght@400;500;600;700&display=swap` | CSS | unchanged |
| 13 | 54 | Google Fonts `JetBrains+Mono:wght@400;500;600&display=swap` | CSS | unchanged |
| 14 | 55 | Google Fonts `IBM+Plex+Sans+Condensed:…&display=swap` | CSS | unchanged |
| 15 | 56 | `booster-typography.css?v=ux022-…` — 17,374 B | CSS | unchanged |
| 16 | 58 | `common.js?v=cartjslogs-20260526` — 14,961 B | **JS, sync** | unchanged |
| 17 | 59 | `booster-product-polish.js` — 2,778 B (no `?v=`) | **JS, sync** | unchanged |
| 18 | 60 | `bs-faq.js?v=rd05b-…` — 11,367 B | JS, **already `defer`** | **CHANGED — already deferred** |
| 19 | 65–67 | `{% for style in styles %}` — module CSS (incl. `ps_live_search.css`, 2,776 B) | CSS | unchanged |
| 20 | 68–70 | `{% for script in scripts %}` — `getScripts('header')`, incl. `ps_live_search.js` (13,169 B), `ps-enhanced-measurement.js` (1,915 B) | **JS, sync** | unchanged |
| 21 | 79–85 | inline `ps_dataLayer.tracking_delay` shim | inline | new since §2A, harmless |
| 22 | 87–93 | inline Microsoft Clarity loader (`clarity.ms`, async injected) | inline | present — TECH-018 discrepancy confirmed still live |
| 23 | 94–233 | 140-line inline `<style>` block | inline | not in §2A |
| 24 | 234 | `patch-mobile-search-menu-redesign.js?v=rd010203b-20260531` — 5,611 B | JS, **already `defer`** | **CHANGED — already deferred** |

Footer (`footer.twig`):

| # | Line | Asset | Notes |
|---|---|---|---|
| 25 | 116 | `{{ cookie }}` | cookie banner — July's LCP element |
| 26 | 117 | `{{ bootstrap }}` → `bootstrap.bundle.min.js` | end of body, non-blocking |
| 27 | 118–120 | `{% for script in scripts %}` → `getScripts('footer')` | end of body |
| 28 | 67–114 | inline `<style>` for `.back-to-top` | inline |
| 29 | 121–147 | inline `DOMContentLoaded` back-to-top script | inline |

### Changes vs §2A — flagged

1. **`bs-faq.js` and `patch-mobile-search-menu-redesign.js` already carry `defer`.**
   §2A lists neither as deferred. Two of the JS items are already done; the remaining
   synchronous head JS is `jquery`, `common.js`, `booster-product-polish.js`, and whatever
   `getScripts('header')` emits.

2. **NEW AND NOT IN §2A — the fourth Google Fonts request is a CSS `@import`.**
   `catalog/view/stylesheet/boostershop-ds.css:13`:
   ```css
   @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');
   ```
   §2A says "plus 4 Google Fonts requests (~2,700 ms)" but attributes all of them to the
   head. Only **three** are `<link>` tags (lines 53–55). The fourth is this `@import`,
   which is the worst of the four: the browser cannot discover it until
   `boostershop-ds.css` (158 KB raw) has downloaded **and** parsed, so it creates a
   serialized three-hop critical chain —
   `header.twig` → `boostershop-ds.css` → `fonts.googleapis.com/css2` → `fonts.gstatic.com/…woff2`.
   This is a strong candidate for a large share of the ~3,080 ms and is a named root cause
   for WP1 (per AGENTS.md UI/CSS discipline: root cause = `boostershop-ds.css:13`).

3. **`Manrope` is the DS body font and is loaded only by that `@import`.**
   `boostershop-ds.css:79` → `font-family: 'Manrope', system-ui, -apple-system, sans-serif`.
   So the font on the critical rendering path is delivered by the slowest of the four
   requests. Meanwhile `Inter` (line 53) is loaded from the head — I found no
   `font-family: 'Inter'` rule in `boostershop-ds.css`. **Inter may be an unused 
   render-blocking request.** Needs a live computed-style check before removal; not assumed.

4. **Preconnect hints are duplicated verbatim.** Lines 45/46 and 51/52 are identical pairs.
   Four hints where two suffice.

5. **Handoff §2A/§4 says to remove the `gstatic.com` preconnect. Doing that as written
   would be a regression.** `fonts.gstatic.com` is where all four Google Fonts `.woff2`
   files actually come from — it *is* requested, just late, because the `@import` delays
   discovery. The July PSI warning is explained by the late request, not by an unused
   origin. Correct action: **de-duplicate** the hints (drop lines 51–52), keep one
   `gstatic` preconnect. See §f contradiction 2.

6. **§2A byte figures are transfer (gzipped) sizes, not file sizes.** `all.min.css` is
   26.2 KiB over the wire but 104,502 B on disk; `boostershop-ds.css` is 28 KiB over the
   wire but 158,573 B; `bootstrap.css` is 30.1 KiB over the wire but 270,584 B. Not an
   error in the handoff — recorded so acceptance measurements compare like with like.

---

## c) CDN / minifier extension, and font origins

### CDN or minifier: **none installed.**

Complete list of `public_html/extension/*`:

```
PintaNovaPoshtaCod   SimpleCheckout      ciaccountsidebar   dv_dialogify
dv_opencart_patch    dv_simple_html_dom  hutko              mono_chast
ocmod                opencart            ps_enhanced_measurement
ps_google_recaptcha  ps_google_sitemap   ps_live_search
ps_popup_dialog_box  ps_product_category_filter             pumb_credit
telegram_notify      ukrainian           ventocart
```

No minifier, no combiner, no CDN, no image-optimizer, no lazy-load module. Every
optimization in Stage 1 has to be done by hand in the templates, the assets, or `.htaccess`.

A **LiteSpeed** layer is present at the server level (see §d) — a leftover WordPress
LSCache block. It is currently doing nothing for OpenCart pages except `CacheLookup on`.
Not in scope; noted because it may explain the selective 7-day TTLs §2A observed.

### Fonts — four origins, three mechanisms

| Font | Loaded from | How | `display` |
|---|---|---|---|
| Inter 400/500/600/700 | `fonts.googleapis.com` → `fonts.gstatic.com` | `<link>` header.twig:53 | `swap` ✅ |
| JetBrains Mono 400/500/600 | `fonts.googleapis.com` → `fonts.gstatic.com` | `<link>` header.twig:54 | `swap` ✅ |
| IBM Plex Sans Condensed 400–700 | `fonts.googleapis.com` → `fonts.gstatic.com` | `<link>` header.twig:55 | `swap` ✅ |
| **Manrope** 400–800 | `fonts.googleapis.com` → `fonts.gstatic.com` | **`@import` boostershop-ds.css:13** | `swap` ✅ |
| **FontAwesome 6** | **self-hosted** `catalog/view/stylesheet/fonts/fontawesome/webfonts/` | `all.min.css` (`{{ icons }}`) | **`block` ✗ ×10** |
| OpenCart legacy | self-hosted `catalog/view/stylesheet/fonts/opencart.{eot,svg,ttf,woff}` | legacy | — |

`fa-solid-900.woff2` = **158,224 B**, matching §2A's "155 KB".
`all.min.css` contains **10** `@font-face` blocks, **all** with `font-display:block` and
**zero** with `swap`.

**WP4 is smaller than the handoff assumes.** All four Google Fonts URLs already carry
`&display=swap`. The only remaining `font-display` work is the ten `font-display:block`
declarations in the self-hosted FontAwesome CSS.

---

## d) `.htaccess` — current state, verbatim

**Path:** `~/public_html/.htaccess` — the only `.htaccess` under `public_html`. 83 lines,
2,862 bytes.

### Existing cache / compression directives: **there are none.**

Grep for `Expires|deflate|gzip|brotli|Cache-Control|mod_headers|ExpiresByType|SetOutputFilter|FilterDeclare`
returns exactly one line, and it is not a cache policy:

```apache
28:RewriteRule .* - [E=Cache-Control:no-autoflush]
```

— a LiteSpeed internal flag inside the WordPress LSCache block, not an HTTP cache header.

There is **no `mod_expires` block, no `mod_deflate` block, and no static-asset
`Cache-Control` anywhere in `.htaccess`.** The selective 7-day TTLs §2A observed on some
CSS and images therefore come from server-level config (LiteSpeed / cPanel defaults), not
from a file we control. WP3 is appending to a file with **zero** existing cache policy —
there is nothing to conflict with, but there is also nothing here we can edit to change
the current partial behaviour.

### `# BEGIN sitemap-no-compression` block: **it does not exist. It has been removed.**

This is the single biggest divergence from the handoff. Grep for `no-compression` in
`.htaccess` → no match. What survives is the `<FilesMatch>` block at the very top,
verbatim, bytes 1–160 (line 1 is empty; lines 6–15 are ten empty lines where the removed
block used to sit):

```apache
                                              ← line 1, empty
<FilesMatch "sitemap.*\.xml$">                ← line 2
    ForceType application/xml
    Header set Content-Type "application/xml; charset=UTF-8"
</FilesMatch>                                 ← line 5
                                              ← lines 6-15, ten empty lines
# BEGIN LSCACHE                               ← line 16
```

**Why it is gone.** `diagnostics/TECH-005-DEEP_handson-diagnostic-runbook_2026-06-05.md`
Block G, step 1, instructs the owner to *"comment out (or delete) the whole block from
`# BEGIN sitemap-no-compression` to `# END sitemap-no-compression`"*. The ten empty lines
are the footprint of that deletion. The runbook's own instruction also covered the
`<FilesMatch>` blocks at the top, but the `ForceType`/`Header set Content-Type` block was
kept.

**Consequence for WP3.** The handoff §2.2 and §5 instruction *"do not reorder, merge with,
or reformat the existing `# BEGIN sitemap-no-compression` block"* is now moot — there is no
such block. The protected artifact WP3 must not disturb is the
**`<FilesMatch "sitemap.*\.xml$">` block at lines 2–5**, plus the LSCache
`CacheDisable public /sitemap*.xml` lines at 25–27. I will treat those as the frozen zone
and re-word the constraint accordingly rather than silently dropping it. The `*.xml`
exclusion rule stands unchanged.

### Full current structure (for the append target)

```
L1        (blank)
L2–5      <FilesMatch "sitemap.*\.xml$"> … </FilesMatch>          ← FROZEN
L6–15     (ten blank lines — footprint of the deleted sitemap block)
L16–45    # BEGIN LSCACHE … # END LSCACHE   (WordPress LiteSpeed leftover;
          contains CacheDisable public /sitemap-gsc.xml|/sitemap.xml|/sitemap.txt)  ← FROZEN
L46–49    # BEGIN NON_LSCACHE … # END NON_LSCACHE  (empty)
L50–53    Options +FollowSymlinks / Options -Indexes / RewriteEngine On / RewriteBase /
L57–59    HTTPS + non-www 301 canonical redirect                  ← FROZEN (§5 redirects)
L61–72    # BEGIN legacy-404-301 20260702 … # END  (10 product 301s) ← FROZEN (§5 redirects)
L74       RewriteRule ^uk-ua/?$ …
L76       RewriteRule ^uk-ua/sitemap.xml$ …                       ← FROZEN
L77       (commented-out sitemap.xml rewrite)                     ← FROZEN
L80–82    OpenCart SEO front-controller rewrite                   ← FROZEN
L83       </blank / EOF>
```

Safe append point for `# BEGIN BS-SPEED-1 cache`: **end of file, after line 82.** A
`<FilesMatch>`/`<IfModule mod_expires>` block at EOF cannot affect any `RewriteRule`
above it, and cannot reach `*.xml` if the extension list excludes it.

---

## e) TECH-003 — `width`/`height` verification

**Verified present. Do not redo.** One correctness concern, one dead-code false alarm.

| Template | Line | Element | `width`/`height` | Verdict |
|---|---|---|---|---|
| `common/header.twig` | 250 | header logo | `width="1498" height="465"` | ✅ present, matches intrinsic 1498×465 |
| `product/thumb.twig` | 23 | product card | `width="240" height="240"` + `loading="lazy"` | ✅ present |
| `product/product.twig` | 61 | main product image | `width="400" height="400"` + `decoding="async"` | ✅ present |
| `product/product.twig` | 69 | gallery thumb | `width="72" height="72"` + `loading="lazy"` | ✅ present |
| `common/home.twig` | 43 | Pokémon tile | `width="168" height="168"` | ⚠️ see below |
| `common/home.twig` | 53 | One Piece tile | `width="168" height="168"` | ⚠️ see below |
| `product/category.twig` | 171 | category thumb | none | ✅ **false alarm — inside a `{# … #}` Twig comment, never rendered** |
| `product/product.twig` | 214, 235 | option-value swatches | none | option images, below fold, negligible |

CLS = 0 is consistent with this: every rendered above-the-fold `<img>` carries both
attributes.

**⚠️ Declared aspect ratio does not match intrinsic on both home tiles.**
`home.twig:43` declares `168×168` (1:1) for `PokemonC.png`, whose intrinsic size is
**1500×585 (2.56:1)**. `home.twig:53` declares `168×168` for `One Piece-Photoroom.png`,
intrinsic **463×111 (4.17:1)**. CSS in `.bs-catcard__media` is currently absorbing the
mismatch (CLS measured 0), but this is a live constraint on WP2: **re-exporting these two
files to a different aspect ratio will change layout unless the declared attributes and the
card CSS are changed in the same patch.** §2A/§4's "re-export `PokemonC.png` at 368×144"
assumes a 184×72 display box; the template declares a square. The rendered box must be
measured in a real browser before the re-export size is chosen. Recorded, not acted on.

**Note for WP2:** `thumb.twig:23` applies `loading="lazy"` to **every** product card,
including the first row above the fold on `/catalog/Pokemon`. Handoff §4 WP2 says "Never
lazy-load the LCP element." Once the LCP element is re-identified after WP1, if it lands
on a first-row product card, this line is the thing to fix (`fetchpriority="high"` + drop
`lazy` for the first N cards).

---

## f) Proposed work-package order, and contradictions found

### Proposed order: **WP1 → WP4 → WP2 → WP3**

| Order | WP | Risk | One-line risk note |
|---|---|---|---|
| 1 | **WP1** render-blocking | MEDIUM | Global head template on every page including checkout — defer order must keep jQuery synchronous ahead of `common.js`, `ps_live_search.js` and the inline live-search initializer at `header.twig:499`, which calls `window.jQuery` directly. |
| 2 | **WP4** fonts | LOW | Ten `font-display:block` → `swap` in a vendor file (`all.min.css`); risk is a brief FOUT on FontAwesome icons, and the anchor count must be exactly 10 or the patch aborts. |
| 3 | **WP2** images | LOW–MEDIUM | Re-export changes intrinsic aspect ratio on both home tiles, whose declared `168×168` already disagrees with the source — CLS can move from 0 if the declared attributes and `.bs-catcard__media` CSS are not updated in the same patch. |
| 4 | **WP3** `.htaccess` | HIGH | Risky zone, separate owner approval; must never match `*.xml`, must append at EOF below the SEO front-controller rewrite, and must not give un-versioned image paths a long immutable TTL. |

**Why WP4 second, not last.** WP1 and WP4 touch the same font critical path. Doing the
FontAwesome `swap` immediately after WP1 means one PSI re-scan covers the whole font
story, and WP4 is a ten-occurrence string replacement in one vendor file — the cheapest
possible patch to verify while the site is still fresh from WP1. This differs from the
canonical handoff §4 numbering (WP1→WP2→WP3→WP4) and matches the ordering already written
in `handoffs/TECH-013_claude-code-kickoff_20260805.md` line 76 ("WP1, WP2, WP4, then WP3
last"), except that I put WP4 ahead of WP2 rather than after it. **Owner's call.**

**Why WP3 must be last — a hard technical reason, not a preference.**
CSS and JS in `header.twig` are cache-busted by `?v=` query strings, so a long TTL on them
is safe. **The images are not.** `home.twig:43` and `:53` reference
`image/catalog/Pokemon/PokemonC.png` and `image/catalog/One Piece/One Piece-Photoroom.png`
with **no version parameter**, and the header logo is emitted by
`header.php:65` as a bare `image/` + `config_logo` path — also unversioned. If WP3 ships
`max-age=31536000, immutable` before WP2 re-exports those files, every visitor who has
loaded the site keeps the **old 394 KB logo and 196 KB tile for up to a year**, and the
entire 773 KiB acceptance criterion becomes unmeasurable for returning users. WP3 last,
and even then images should get a shorter TTL (30 days) than the `?v=`-versioned CSS/JS,
or the re-exported assets need new filenames.

### Contradictions between the handoff and the actual code

1. **§6 template paths are wrong.** The handoff says
   `catalog/view/theme/<active-theme>/template/common/header.twig` and
   `catalog/view/theme/<active-theme>/stylesheet/`. Neither exists. Theme is `basic`;
   real paths are `catalog/view/template/common/header.twig` and
   `catalog/view/stylesheet/`. §6 does say "verify against actual files, do not assume" —
   so this is the handoff working as designed, but the paths must be corrected before WP1.

2. **§2A and §4 say to remove the `gstatic.com` preconnect. As written this is a
   regression.** All four Google Fonts families serve their `.woff2` from
   `fonts.gstatic.com`; the origin *is* used. The real defect is that lines 45/46 and
   51/52 are **exact duplicates**, giving four hints where two are needed, which is what
   trips the PSI ">4 preconnect" warning. Recommended correction: delete the duplicate
   pair (lines 51–52), keep one `googleapis` + one `gstatic` hint. I will not remove the
   `gstatic` preconnect entirely without owner sign-off.

3. **§2.2 and §5 protect a `.htaccess` block that no longer exists.** The
   `# BEGIN sitemap-no-compression` block was deleted, per TECH-005-DEEP runbook Block G.
   The frozen zone should be restated as the `<FilesMatch "sitemap.*\.xml$">` block
   (lines 2–5) and the LSCache `CacheDisable public /sitemap*.xml` lines (25–27). The
   "never match `*.xml`" rule and the `curl -sI` byte-identical baseline requirement are
   unaffected and still stand.

4. **§2A implies the render-blocking JS is all synchronous. Two items already carry
   `defer`** (`bs-faq.js`, `patch-mobile-search-menu-redesign.js`). Expected WP1 savings
   should be recalculated from the remaining synchronous set, not from the full §2A list.

5. **§2A undercounts the cost of the fourth Google Fonts request.** It is a CSS `@import`
   at `boostershop-ds.css:13`, not a head `<link>`, and therefore sits behind a 158 KB
   stylesheet download in a serialized chain. This is the largest single lever in WP1 and
   the handoff does not name it.

6. **§4 WP4 is mostly already done.** All four Google Fonts URLs already carry
   `&display=swap`. Only the self-hosted FontAwesome `font-display:block` ×10 remains.

7. **§4 WP2's "re-export `PokemonC.png` at 368×144" conflicts with the template.**
   `home.twig:43` declares `width="168" height="168"`. The July "displayed 184×72" figure
   is a painted-box measurement, not the declared attribute. The correct export size
   cannot be chosen from the handoff alone — it needs one live browser measurement of
   `.bs-catcard__media`.

8. **§2A calls the FontAwesome/DS/bootstrap sizes 26.2 / 28 / 30.1 KiB.** Those are
   gzipped transfer sizes; on disk they are 104,502 / 158,573 / 270,584 bytes. Acceptance
   measurements must state which is being compared.

9. **Kickoff doc vs canonical handoff on WP order.**
   `handoffs/TECH-013_claude-code-kickoff_20260805.md:76` says "WP1, WP2, WP4, then WP3
   last"; canonical §4 says "Execute in this order" WP1→WP2→WP3→WP4. The canonical
   handoff is the governing document, but its order puts the HIGH-risk `.htaccess` patch
   before the LOW-risk font patch, and — per the unversioned-image argument above —
   before the image re-export it would freeze. Recommending WP3 last.

### Not a contradiction, but recorded

- **TECH-018 / Microsoft Clarity is live** (`header.twig:87–93`, tag `wufz7dj4ug`),
  confirming §2A's side finding. Not touched. Must still fire after WP1's reorder.
- **`Inter` may be an unused render-blocking font.** `boostershop-ds.css:79` sets the DS
  body font to `Manrope`; I found no `font-family: 'Inter'` rule in the DS. Removing an
  in-use font would be a visible regression, so this needs a live computed-style check
  before any action. Flagged for WP1 investigation, not assumed.
- **`bootstrap-icons.css` (73,298 B)** exists in `catalog/view/stylesheet/` but is not
  referenced from `header.twig`. May be injected by a module via `getStyles()`. To be
  confirmed against a live page source during WP1.
- **No local copy of any file was modified.** Extraction target was a temp directory
  outside the repository; the archive was not unpacked into the repo.

---

## Blocking questions for the owner

1. **Approve the reordering** WP1 → WP4 → WP2 → WP3 (canonical §4 says WP3 before WP4)?
2. **Preconnect:** de-duplicate lines 51–52 and keep one `gstatic` hint, rather than
   removing `gstatic` as §2A/§4 instruct?
3. **`.htaccess` frozen zone restated** as `<FilesMatch "sitemap.*\.xml$">` (lines 2–5) +
   LSCache `CacheDisable` lines (25–27), since `# BEGIN sitemap-no-compression` is gone —
   confirm?
4. WP1 needs **one live-page check** (computed `font-family`, whether `Inter` and
   `bootstrap-icons.css` are actually used, and the painted size of `.bs-catcard__media`).
   Should I fetch the live pages, or will you supply a saved page source / DevTools export?

---

_No patch written. No production file touched. Nothing committed or pushed._
_References: `AGENTS.md` (patch conventions, UI/CSS patch discipline, risky zones),
`CLAUDE.md`, `handoffs/handoff_TECH-013_mobile-cwv-stage1_20260804.md` (canonical),
`diagnostics/TECH-005-DEEP_handson-diagnostic-runbook_2026-06-05.md` (Block G — explains
the removed `.htaccess` block)._
