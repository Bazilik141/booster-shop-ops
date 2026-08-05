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

## 6. Likely files / areas — verify against actual files, do not assume
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
