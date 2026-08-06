# Handoff — TECH-013 Site Speed, Stage 1 (Quick Wins)

**Rev. 2026-08-05 — CANONICAL.** Executor changed from Codex to **Claude Code**.
Supersedes `handoff_TECH-013_mobile-core-web-vitals_20260716.md`, whose file-level
diagnostics are folded into §2 and §2A below. The July file is retained as the
baseline record only — do not execute from it.

`Executor: Claude Code · model=Opus · thinking=high`
Risky zone (`.htaccess`), multi-file, global templates, and the work needs live-file
discovery in a theme that has no local copy plus local image processing and measurement —
all of which favour a repo-resident executor. Codex weekly quota is also constrained.
**Do not run this on a small model.**

Owner decision 2026-08-05: patch authorship is now shared between Codex and Claude Code;
`AGENTS.md` amended accordingly (authority rules, role table, flow, executor section).
The earlier role-authority blocker is resolved. Claude Code still never commits, pushes,
or deploys.

Preflight: bs-seo-risk-gate classification at the end of this document.
Owner approval required before deploy (High-risk zone: `.htaccess` is touched for cache headers).

## 1. Task ID
TECH-013 — Core Web Vitals technical pass, Stage 1. Consolidates TECH-004 (render-blocking),
TECH-002 (static cache policy) and the image-delivery part. **TECH-003 (image width/height)
is a subtask of TECH-013 as of 2026-08-05 (owner instruction) — verify its work is intact,
do not redo it.** Working label in audit docs: BS-SPEED-1.

Notion page: `3a06bf20-bdb4-810c-b914-e518ca5f7188`. Status is written by Claude only.

## 2. Context
boostershop.website, OpenCart 4.1.0.3 storefront (custom/modified theme, Twig templates).

### 2.0 Measured baseline and trend (mobile, PageSpeed Insights)

| Metric | 2026-07-16 | 2026-08-04 | Trend |
|---|---|---|---|
| Performance score | **62 / 100** | not recorded | — |
| FCP | 4.1 s | 4.1 s | flat |
| **LCP** | 8.3 s | **8.8 s** | **regressed ~0.5 s in 3 weeks** |
| Speed Index | 6.2 s | 6.2 s | flat |
| TBT | 0 ms | 10 ms | both pass |
| CLS | 0 | 0 | pass — protect this |
| Render-blocking savings | ≈3,060 ms | ≈3,080 ms | flat |
| Image delivery savings | 773 KiB | 773 KiB | flat |
| Cache TTL savings | 327 KiB | 327 KiB | flat |
| font-display savings | 50 ms | 40 ms | flat |

Accessibility 94 / Best Practices 100 / SEO 100 (2026-07-16).
July scan conditions: emulated Moto G Power, Lighthouse 13.4.0, throttled slow 4G, single-page
session, captured by owner 2026-07-16 21:45 GMT+3.

**Reading of the trend:** nothing was fixed between the two scans and LCP still degraded.
The regression is not caused by this task's scope; treat 8.8 s as the live baseline and
re-measure immediately before starting, since it may have drifted again.

**Unresolved contradiction — do not paper over.** The July scan reported TTFB in the LCP
breakdown as 0 ms (concluding the backend is fine), while Google Search Console 90-day crawl
stats report average server response 621 ms with peaks to 1,031 ms. Both cannot describe the
same reality. Lab TTFB is a single warm request; crawl stats are aggregate and cold. Do not
resolve this inside Stage 1 — it is the core question for Stage 2. Record, do not act.

### 2.1 Owner-designated benchmark URLs (authoritative, 2026-08-05)

| Type | URL | PSI analysis |
|---|---|---|
| Home | `https://boostershop.website/` | `h22q4aqi8f` (mobile + desktop) |
| Category | `https://boostershop.website/catalog/Pokemon` | `9797wc5sh8` (mobile + desktop) |
| Product | `https://boostershop.website/product/Pokemon-boosters-Mega-Symphonia` | `f96lnjqlbg` (mobile + desktop) |

**Changed from previous revisions.** Earlier drafts used
`/product/Pokemon-boosters-Mega-Dream-EX` as the product URL. The owner has designated
**Mega-Symphonia**. Both URLs exist and return 200; use Mega-Symphonia for all before/after
measurement so the comparison is against the owner's own PSI runs. Measure both mobile and
desktop — desktop is now part of the record.

## 2A. File-level diagnostics (carried over from the 2026-07-16 scan)

This is the concrete inventory. **Verify every item against the current live files before
acting — these observations are three weeks old.**

**Render-blocking chain (~3,060–3,080 ms).** Blocking assets observed:
`all.min.css` (FontAwesome, 26.2 KiB / 1550 ms), `bootstrap.css` (30.1 KiB / 1350 ms),
`boostershop-ds.css` (28 KiB / 1550 ms), `stylesheet.css`, `bs-faq.css`, `content-pages.css`,
`booster-typography.css`, `jquery-3.7.1.min.js` (33.6 KiB / 1160 ms), `ps_live_search.js` + its
CSS, `common.js`, `booster-product-polish.js`, `ps-enhanced-measurement.js`, plus 4 Google Fonts
requests (~2,700 ms).

**Image weight (773 KiB).**
- `BS Big logo.png` — served 1498×465 (394 KB), displayed 180×56. ~392 KB recoverable by resize
  alone. Served from `image/catalog/One Piece/BS Big logo.png`, i.e. bypassing the OpenCart
  `image/cache/` resizer.
- `PokemonC.png` — served 1500×585 (196 KB), displayed 184×72. ~193 KB recoverable.
- Product-card images not in WebP/AVIF (`mega_gallade_ex`, OP-15 box, `One-Piece-Photoroom`,
  Mystery box) — ~180 KB.

**Cache TTL (327 KiB).** Served with `Cache-Control: None`: `jquery-3.7.1.min.js`,
`bootstrap.bundle.min.js`, `common.js`, `ps_live_search.js`, `booster-product-polish.js`,
`patch-mobile-search-menu-redesign.js`, and **`fa-solid-900.woff2` (155 KB)**. Some CSS and
images already carry a 7-day TTL — the existing policy is selective, not global. The new block
must not fight the existing partial policy; inspect what is already set before adding.

**LCP element.** The July scan identified the LCP element as the **cookie-banner text**
("Ми використовуємо файли cookie…"), with element render delay 2,520 ms. This is a symptom of
slow first paint, not a fault of the banner. After render-blocking is fixed, the LCP element
will most likely move to real content (hero/logo). **Re-identify the LCP element after each
work package** — optimizing for the wrong element wastes the whole pass.

**Preconnect.** `gstatic.com` preconnect is present but the origin is not requested early —
remove it. PSI warns above 4 preconnect hints.

**Stage 2 candidates — out of scope here.** Unused CSS ~78 KiB (`bootstrap.css` ~28 KB unused of
30 KB, `all.min.css` ~25 KB unused, `boostershop-ds.css` ~21 KB unused), unused JS ~23 KiB
(jQuery ~23 KB unused of 33 KB), JS minify ~11 KiB, CSS minify ~13 KiB, server TTFB.
**Do not attempt aggressive CSS purging in Stage 1.**

**Side finding, not this task.** `clarity.js` (Microsoft Clarity) is already live while roadmap
TECH-018 shows it as planned. Discrepancy logged for separate reconciliation — do not touch it
here, but verify it still functions after any script reorder.

### 2.2 Sequencing gate — LIFTED 2026-08-05 (owner decision), scoping rule stays

**Owner decision 2026-08-05:** TECH-005-DEEP is no longer a blocker for this task. Google has
not started reading the sitemap regardless; other search engines read it fine; `robots.txt` is
read normally. The owner accepts the current sitemap state and does not want WP3 held for it.

**WP3 is therefore unblocked. The technical scoping rule is NOT lifted:**

- The new cache block must **never match `*.xml`**. Scope it to css/js/image/font extensions
  only. The sitemap compression behaviour is unresolved, not fixed — do not perturb it.
- Do not reorder, merge with, or reformat the existing `# BEGIN sitemap-no-compression` block.
- Capture `curl -sI` header baselines for `/sitemap-full.xml` and `/robots.txt` **before** WP3
  and compare after. Byte-identical is the acceptance bar. If they differ, revert WP3
  immediately — that would mean the block leaked outside its intended scope.

WP3 still requires explicit owner approval before deploy because `.htaccess` is a risky zone;
that is an authority gate, not a sequencing one.

Roadmap note from TECH-004 still applies: inline scripts depend on jQuery — keep jQuery
synchronous, or defer the whole chain in dependency order.

## 3. Goal
Reduce mobile LCP from 8.8 s to **≤4.0 s** and FCP from 4.1 s to **≤2.5 s** on all three
benchmark URLs, with zero visual or functional regressions. Stretch: Performance ≥80.

## 4. What to change — work packages

Execute in this order. **One patch file per work package.** Do not bundle: bundling destroys
independent rollback, which is the only safety net in a patch-based deploy model.

### WP1 — Render-blocking CSS/JS in `<head>` (MEDIUM risk)
- Inventory all `<link rel="stylesheet">` and `<script>` in the head template(s).
- Defer non-critical JS (`defer`), preserving execution order where dependencies exist
  (jQuery before dependent plugins).
- Load non-critical CSS asynchronously (`media="print"` + `onload` pattern, or split
  critical/non-critical). Inline only a minimal critical CSS block if needed above the fold.
- Remove the unused `gstatic.com` preconnect; keep `preconnect` only for origins actually
  requested early.
- Scope defer changes to catalog/home/product rendering paths. **Do not defer anything the
  checkout flow loads synchronously** without first proving the dependency.

### WP2 — Images (LOW–MEDIUM risk)
- Re-export `BS Big logo.png` at display size ×2 (360×112) instead of 1498×465.
- Re-export `PokemonC.png` at 368×144 instead of 1500×585.
- Convert heavy product-card PNGs to WebP (or AVIF + WebP fallback) where quality allows.
  WebP must be **additive** — keep the PNG fallback until QA confirms.
- Check which template calls bypass `image/cache/` (the header logo does) and route them
  through the resizer where practical.
- Preserve explicit `width`/`height` on all `<img>` (this is the TECH-003 subtask surface —
  verify, do not redo). CLS must stay 0.
- Add `loading="lazy"` below the fold. **Never lazy-load the LCP element.** Consider
  `fetchpriority="high"` on the real LCP image once it has been re-identified.

### WP3 — Cache TTL in `.htaccess` (HIGH risk — unblocked 2026-08-05, see §2.2)
- New isolated block, delimited `# BEGIN BS-SPEED-1 cache` / `# END BS-SPEED-1 cache`.
- `Cache-Control: public, max-age=31536000, immutable` where filenames are versioned by
  OpenCart; otherwise a pragmatic `max-age` ≥30 days.
- Leave `no-cache` for HTML, cart, checkout and session responses.
- Extensions only: css, js, images, fonts. Never `*.xml`.

### WP4 — Fonts (LOW risk)
- `font-display: swap` on `@font-face` declarations, notably `fa-solid-900.woff2`, and/or
  `display=swap` on Google Fonts URLs.

## 5. Do not touch
- `robots.txt`
- sitemap / `sitemap_index.xml` and its generation
- canonical tags, meta robots, hreflang
- any redirect rules; existing `.htaccess` content outside the new cache block (append-only,
  clearly commented); the `# BEGIN sitemap-no-compression` block specifically
- checkout and payment: SimpleCheckout module (`route=extension/SimpleCheckout/...`),
  Hutko/Checkbox integration, fiscalization, Nova Poshta
- JSON-LD / schema markup blocks in templates
- Merchant Center feed and its generation
- URL structure, slugs, pagination parameters
- product/category content, titles, meta descriptions
- jQuery as a library — do not remove; live search and probably other widgets depend on it

## 5A. CORRECTIONS from orientation, 2026-08-05 — these override the sections above

Source: `diagnostics/TECH-013_orientation_report_20260805.md`. Claims below were
independently re-verified by Claude (chat) against the backup: `.htaccess` byte/line
counts, the `@import`, the duplicate preconnect pairs, and the unversioned image paths
all match exactly.

1. **§6 paths were wrong.** `config_theme` = `basic`; OpenCart 4.1 resolves this to the
   base template root. Real paths are `catalog/view/template/common/header.twig` and
   `footer.twig`, CSS root `catalog/view/stylesheet/`. There is **no**
   `catalog/view/theme/basic/`. Use §5B below, not §6.
2. **The `# BEGIN sitemap-no-compression` block no longer exists** — it was deleted per
   the TECH-005-DEEP runbook (Block G step 1). Ten blank lines at `.htaccess:6–15` are its
   footprint. The frozen zone is restated in §5B. The "never `*.xml`" rule is unchanged.
3. **`.htaccess` contains no cache or compression policy at all.** The selective 7-day
   TTLs seen in July come from server-level LiteSpeed/cPanel config, not from a file we
   control. WP3 appends to a file with zero existing policy — nothing to conflict with,
   but also confirm with `curl -sI` that the appended block actually takes effect.
4. **Do NOT remove the `gstatic` preconnect.** All four Google Fonts serve `.woff2` from
   `fonts.gstatic.com` — the origin is used, just discovered late. The real defect is that
   lines 45/46 are repeated verbatim at 51/52. **De-duplicate; keep one hint each.**
5. **The fourth Google Fonts request is a CSS `@import`, not a `<link>`** —
   `boostershop-ds.css:13` imports Manrope, the DS body font. It is undiscoverable until a
   158,573-byte stylesheet downloads *and* parses, creating a serialized 3-hop chain. This
   is the largest named root cause for WP1 and §2A did not identify it.
6. **WP4 is smaller than assumed.** All four Google Fonts URLs already carry
   `&display=swap`. The only remaining work is 10 `font-display:block` declarations in
   self-hosted `all.min.css`. Anchor count must be exactly 10 or abort.
7. **Two JS files are already deferred** (`bs-faq.js`,
   `patch-mobile-search-menu-redesign.js`). Remaining sync head JS: jQuery, `common.js`,
   `booster-product-polish.js`, plus whatever `getScripts('header')` emits.
8. **§2A byte figures are gzipped transfer sizes.** On disk: `bootstrap.css` 270,584 B,
   `all.min.css` 104,502 B, `boostershop-ds.css` 158,573 B. Acceptance criteria must state
   which measure is used.
9. **`category.twig:171` is a false alarm** — the attribute-less `<img>` sits inside a
   `{# … #}` Twig comment and never renders. TECH-003 is intact; verify, do not redo.

### Work-package order — REVISED (owner approved 2026-08-05)

`WP1 → WP4 → WP2 → WP3`. WP3 moves from third to last for a hard reason:

**Images are not cache-busted.** `home.twig:43`/`:53` and `header.php:65` emit
unversioned paths (`{{ base }}image/catalog/...`), while CSS/JS carry `?v=`. Shipping
`max-age=31536000, immutable` before WP2 would pin the old 394 KB logo and 196 KB tile in
returning visitors' browsers for a year and make the 773 KiB acceptance criterion
unmeasurable.

**Durable consequence — must be solved inside WP3, not deferred.** Re-exporting the images
in WP2 does not fix the underlying fragility: any *future* image change would be pinned
for a year too. WP3 must therefore do one of:

- (a) apply the long `immutable` TTL **only** to `?v=`-versioned assets, and a short TTL
  (≤7 days) to unversioned paths; or
- (b) add cache-busting to image URLs first, then apply the long TTL uniformly.

Choose one explicitly in the patch description and state why. Do not ship a blanket
one-year `immutable` rule over unversioned image paths.

### §5C — live computed-style measurements, owner-supplied 2026-08-05

Captured on `https://boostershop.website/` via DevTools console.
**Caveat: desktop viewport 1411×911, dpr 1 — device emulation was not active.**
Font usage below is viewport-independent and therefore valid. Painted box sizes are
desktop-only and must be re-captured at 390 px before WP2 sets export dimensions.

**CONFIRMED — `Inter` is unused. Remove it in WP1.**
`Inter` appears in **zero** computed `font-family` values and in **zero** entries of
`document.fonts` loaded set. The `<link>` at `header.twig:53` is a fully unused
render-blocking request to `fonts.googleapis.com`. Deleting it removes one blocking
request and one origin round-trip with no visual effect.

Family usage (element counts): `Manrope` 344 · `JetBrains Mono` 2 ·
`IBM Plex Sans Condensed` 2 · `Font Awesome 6 Free` 1.

Loaded fonts: Manrope 400/500/600/700/800, IBM Plex Sans Condensed 400/600,
Font Awesome 6 Free 900.

**`JetBrains Mono` — probable second removal, verify first.** Two elements compute to it,
but it does **not** appear in the loaded-font set, meaning it is never actually painted.
Identify those two elements before deleting `header.twig:54`. Do not remove on this
evidence alone.

**`IBM Plex Sans Condensed` — keep.** Used and loaded.

**`bootstrap-icons.css` — confirmed not referenced.** Absent from
`document.styleSheets`. Dead file on disk only; no WP1 action, do not delete in this task.

**Manrope loads five weights (400–800).** Weight trimming is a real further saving but is
Stage 2 — it requires auditing every weight actually rendered. Out of scope here.

Painted sizes (desktop, dpr 1 — indicative only):
`logo` 135×42, intrinsic 1498×465 · `.bs-catcard__media` 240×168 with `img` painted
226×154, intrinsic 1500×585.

### §5D — WP2 export targets, RESOLVED 2026-08-05

Mobile capture completed: viewport 390×824, **dpr 2**. Combined with the desktop capture,
export targets are now fixed. No further measurement needed for WP2.

**`object-fit: contain`, `object-position: 50% 50%` on `.bs-catcard__media img`.**
This resolves the aspect-ratio concern raised in the orientation report. `contain`
letterboxes the image inside an explicitly CSS-sized box without cropping, so re-exporting
**at the original aspect ratio** cannot move layout. The declared `width="168" height="168"`
attributes do not drive layout here — the CSS box does — which is why CLS is 0 despite the
mismatch. Keep the aspect ratio; do not "fix" the attributes to 1:1 dimensions.

Painted CSS sizes (element box vs actual rendered content under `contain`):

| Asset | Mobile 390 / dpr2 | Desktop 1411 / dpr1 | Intrinsic | **Export target** |
|---|---|---|---|---|
| Header logo | 103×32 | 135×42 | 1498×465 (3.222:1) | **270×84** |
| `PokemonC.png` | box 116×88 → content 116×45 | box 226×154 → content 226×88 | 1500×585 (2.564:1) | **452×176** |
| `One Piece-Photoroom.png` | same box, content ≈116×28 | content ≈226×54 | 463×111 (4.17:1) | **452×108** |

Targets are sized for **desktop retina (dpr 2)**, the largest requirement across
breakpoints — not for mobile alone. Mobile-only sizing would under-serve desktop retina
and produce visible blur.

**Correction to §2A's image accounting.** `One Piece-Photoroom.png` is intrinsically
463×111 against a 452×108 requirement — it is **already correctly sized** and yields
effectively no saving. The 773 KiB opportunity is dominated by the logo (394 KB → expect
well under the 40 KiB acceptance bar) and `PokemonC.png` (196 KB). Do not spend WP2 effort
re-exporting the One Piece tile; convert format only if it is not already WebP.

### §5E — `JetBrains Mono` resolved: removable, with one visual check

The two elements are `DIV.bs-menu__label` and `DIV.bs-menu__label.bs-menu__label--sep`.
The font is **not** in the loaded set on either desktop or mobile, so those labels already
render in fallback today. Removing `header.twig:54` is therefore visually neutral in the
current state.

Residual risk: the labels may be hidden at capture time and could trigger the load when the
menu opens. **Before deleting, open the mobile menu and screenshot `.bs-menu__label`;
repeat after.** If identical, the removal stands. If not, keep the link and record why.

With Inter (§5C) and JetBrains Mono removed, WP1 eliminates **two** of the three head
Google Fonts `<link>` requests outright, on top of hoisting the Manrope `@import`.

### §5B — restated `.htaccess` frozen zone (replaces the §2.2/§5 wording)

Do not modify, reorder or reformat:

- `.htaccess:2–5` — `<FilesMatch "sitemap.*\.xml$">` (`ForceType`, `Header set Content-Type`)
- `.htaccess:16–45` — `# BEGIN LSCACHE … # END LSCACHE`, including
  `CacheDisable public /sitemap*.xml`
- `.htaccess:57–59` — HTTPS + non-www 301 canonical redirect
- `.htaccess:61–72` — `# BEGIN legacy-404-301 20260702` block (10 product 301s)
- `.htaccess:74`, `:76`, `:77` — `uk-ua` rewrites and the commented sitemap rewrite
- `.htaccess:80–82` — OpenCart SEO front-controller rewrite
- `.htaccess:6–15` — the ten blank lines. Leave them. Cosmetic cleanup here is pointless
  risk in a risky zone.

Append point for `# BEGIN BS-SPEED-1 cache`: **end of file, after line 82.**

## 6. Likely files / areas — SUPERSEDED by §5A.1, kept for history
- `catalog/view/theme/<active-theme>/template/common/header.twig` (head, CSS/JS includes, logo)
- `catalog/view/theme/<active-theme>/template/common/footer.twig` (scripts before `</body>`)
- Theme stylesheets under `catalog/view/theme/<active-theme>/stylesheet/`
- Product/category grid templates for `<img>` attributes (`product/product.twig`,
  `product/category.twig`, featured-product module templates)
- `.htaccess` in web root — cache block only, WP3 only
- OpenCart image cache settings (admin: image dimensions) — read-only check

**There is no local copy of the live theme in this repo.** Live state comes from the owner's
cPanel backup drop — use the newest backup (currently
`backup-8.5.2026_10-49-27_boosters.tar.gz`). If a needed file is missing from the backup, ask
the owner to export it. Do not reconstruct file contents from `.htaccess` fragments quoted in
past handoffs.

Verify before writing: active theme name, whether a CDN/minifier extension is installed, and
where fonts are loaded from.

## 7. Acceptance criteria (measurable)
- PSI mobile, all three URLs: **LCP ≤4.0 s AND FCP ≤2.5 s** (lab).
- PSI "Render-blocking requests" estimated savings **≤800 ms** (from ≈3,080 ms).
- PSI "Improve image delivery" estimated savings **≤200 KiB** (from 773 KiB).
- `BS Big logo.png` + `PokemonC.png` combined weight drop **≥500 KB**; visibly sharp at
  3 breakpoints.
- Header logo served as an optimized asset **≤40 KiB** with explicit dimensions.
- **CLS remains 0** (±0.02) and **TBT ≤200 ms** on all three URLs. CLS regression = revert.
- `curl -sI` on `jquery-3.7.1.min.js`, `bootstrap.css`, `fa-solid-900.woff2` →
  `Cache-Control: max-age=2592000` or longer (WP3 only).
- `curl -sI /sitemap-full.xml` and `/robots.txt` — headers **byte-identical** to the
  pre-patch baseline. Capture that baseline before touching anything.
- All three URLs return HTTP 200 and are visually identical to production (above-the-fold
  screenshot comparison at mobile 390×844 and desktop 1920×1080).
- No new console errors (Chrome DevTools, all three URLs).
- `bs-checkout-smoke` full 11-step run clean (SimpleCheckout, First15, Hutko, Nova Poshta).

## 8. QA / smoke test
- Before/after PSI runs, **mobile and desktop**, on all three benchmark URLs; attach both
  reports to the Notion task.
- `header.twig`/`footer.twig` are global, so checkout rendering is affected: run
  `bs-checkout-smoke` after deploy.
- Verify `robots.txt`, `sitemap_index.xml`, one canonical tag and one product JSON-LD block are
  byte-identical before/after.
- Verify cart add/remove and the login modal still work (JS defer order).
- Verify Microsoft Clarity (`clarity.js`) still fires after any reorder/defer.
- Re-identify the LCP element after each work package.

## 9. Delivery format — patch runner (this is how the change reaches production)

The owner deploys by uploading one file to `~/public_html` and running one command. There is no
git-based deploy and no staging environment. Therefore each work package ships as a **PHP patch
runner** per the AGENTS.md patch conventions:

1. **File-exists check** — fail with a clear error if the target is not found; never blind-edit.
2. **Anchor pre-check** — fail if anchor count != expected.
3. **Backup** to `_patch_backups/<patch>-<ts>/` before any write.
4. **`php -l` gate** — restore-on-fail; no silent failures.
5. **Idempotent marker** — `already_applied=yes` on a repeat run.
6. **No DB changes** in this task.
7. **Self-delete** after success.

Naming: `patches/TECH-013_<slug>_20260805.php` — one per work package.
Drop to: `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\patches\`

Binary assets (re-exported images, WebP) cannot travel inside a PHP runner. Deliver them as
files in the repo with an explicit upload path per file, and state it in the response.

After each patch is ready, respond with: what it does (1–2 sentences), the local file path, the
run command (`php <filename>` in `~/public_html`), and one terminal block.

**Claude Code must not commit, push, or deploy.** Prepare the owner-run PowerShell commit block
per `CLAUDE.md` §Commit-block requirements; the owner runs it.

## 10. Rollback
- Before any edit: the runner's own backup to `_patch_backups/` is the primary mechanism.
  Additionally capture `curl -sI` header baselines for `/sitemap-full.xml` and `/robots.txt`.
- Images: originals copied to `_patch_backups/` before overwrite. WebP additive — PNG fallback
  stays until QA confirms.
- `.htaccess` (WP3): `cp .htaccess .htaccess.bak-tech013-20260805` before editing. Rollback =
  delete the named block, or restore the backup file. Do not hand-reconstruct.
- No DB changes are made, so file restore is a complete rollback.
- If a rollback is executed, record what failed in the Notion task before retrying.

## 11. Recommended status after execution
- Patches prepared, reviewed, not deployed → Status stays **In progress**, Stage →
  `Patches ready, awaiting owner deploy`.
- After owner deploy + PSI re-scan + clean checkout smoke → Stage →
  `Deployed, awaiting QA sign-off`. Status stays **In progress**.
- **Never set Status = Done.** Only the owner closes tasks, after acceptance criteria are met
  with numbers and checkout smoke is clean.
- Notion status is written by Claude. Claude Code updates `ROADMAP_FLOW` in
  `dashboard/booster-dashboard.html` only if the implementation requires it — never both.
- Re-check the GSC Core Web Vitals section 28 days after production deploy.

---

## Appendix: bs-seo-risk-gate classification (preflight)

1. **Risk: High** — touches `.htaccess` (protected zone); global templates affect all page
   types including checkout.
2. **Affected assets:** theme header/footer templates, theme CSS, template `<img>` markup,
   `.htaccess` (additive cache block), web fonts, image assets. Page types affected: all.
3. **Do not touch:** robots.txt, sitemap, canonicals, redirects, schema, Merchant feed,
   checkout/payment/fiscalization/Nova Poshta code, URL structure.
4. **Safest next action:** all four work packages as separate patch runners with backup +
   rollback. Capture protected-file header baselines before the first patch.
   **Confirmed by owner 2026-08-05: there is no staging environment — every patch lands
   directly on production.** This is the single largest risk factor in this task. It is why
   one-work-package-per-patch, the runner's automatic backup, and deploying one patch at a
   time (verify, then proceed) are mandatory rather than advisory. Do not let the owner run
   two patches back-to-back without checking the site in between.
5. **QA checklist:** PSI before/after (3 URLs × mobile/desktop) · visual parity screenshots ·
   console errors · protected-zone byte-diff · `bs-checkout-smoke` after deploy.
6. **Owner approval required: yes** — before every production deploy, and specifically to
   unlock WP3.
7. **Related smoke checks:** `bs-checkout-smoke` (global templates touch checkout rendering).
   `bs-merchant-schema-qa` not required unless schema blocks are modified — prohibited by §5.

---

_References: `AGENTS.md` (risky zones, patch conventions, UI/CSS patch discipline, authority
rules), `CLAUDE.md` (commit-block requirements), `ROADMAP_SOP.md` (roadmap governance),
`bs-seo-risk-gate`, `bs-checkout-smoke`. TECH-005-DEEP — sitemap/`.htaccess` sitemap block
frozen, do not touch._
