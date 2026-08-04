# Codex Handoff — Site Speed, Stage 1 (Quick Wins)

Preflight: bs-seo-risk-gate classification included at the end of this document. Owner approval required before deploy (High-risk zone: .htaccess is touched for cache headers).

## 1. Task ID
TECH-013 — Core Web Vitals technical pass, Stage 1 (roadmap sheet UX_UI Tasks). This stage consolidates: TECH-004 (render-blocking, was Беклог), TECH-002 (static cache policy, was Заплановано) and the image-delivery part. TECH-003 (image width/height) is already "На перевірці" — verify, do not redo. Working label in audit docs: BS-SPEED-1.

## 2. Context
boostershop.website, OpenCart storefront (custom/modified theme, Twig templates). SEO audit 2026-08-04 (v1.1) findings, mobile lab data from PageSpeed Insights snapshot (analysis id s6sfd7alic, form_factor=mobile):

- LCP 8.8 s (target ≤2.5 s), FCP 4.1 s, Speed Index 6.2 s.
- TBT 10 ms and CLS 0 — JS execution and layout are healthy; the problem is resource delivery only.
- Render-blocking requests: estimated savings ≈3,080 ms (largest single lever).
- Image delivery: estimated savings 773 KiB (oversized/unoptimized images; e.g. header logo `image/catalog/One Piece/BS Big logo.png` is a large PNG served on every page).
- Inefficient cache TTL on static assets: 327 KiB re-downloaded per repeat view.
- Font display: 40 ms savings (font-display not set to swap).
- Google crawl stats (90 days): average server response 621 ms with peaks to 1,031 ms — server-side TTFB is slow but is OUT OF SCOPE for Stage 1 (Stage 2 candidate: hosting/PHP/OPcache/DB review).
- Minor: minify JS (~11 KiB), unused CSS ~78 KiB, unused JS ~23 KiB — Stage 2 candidates; do not attempt aggressive CSS purging in Stage 1.

Stage map (owner-approved scope: Stage 1 only in this handoff):
- Stage 1 (this handoff): render-blocking, images, cache TTL, font-display.
- Stage 2 (separate handoff later): server TTFB, unused CSS/JS removal, JS minification pipeline.

**SEQUENCING GATE (roadmap dependency).** TECH-005-DEEP is still open: LiteSpeed serves sitemap XML as compressed/binary to third-party clients (reproduced again 2026-08-04: `/sitemap.xml` and `/sitemap_index.xml` return unparseable binary under Content-Type application/xml). The `.htaccess` compression/cache zone is frozen by that task. Therefore:
- Do steps 1 (render-blocking), 2 (images) and 4 (fonts) now — templates/CSS only, no `.htaccess`.
- Step 3 (cache TTL in `.htaccess`) only after the owner confirms TECH-005-DEEP status allows touching the compression/cache layer, and the added block must not alter Content-Encoding behavior for XML (scope it to css/js/images/fonts extensions only, never `*.xml`).

Roadmap notes for TECH-004 also warn: inline scripts depend on jQuery — keep jQuery synchronous or defer the whole chain in order.

## 3. Goal
Reduce mobile lab LCP from 8.8 s to ≤4.0 s and FCP from 4.1 s to ≤2.5 s on the home page, a category page (/catalog/Pokemon) and a product page (/product/Pokemon-boosters-Mega-Dream-EX), with zero visual/functional regressions.

## 4. What to change
1. Render-blocking CSS/JS in `<head>`:
   - Inventory all `<link rel="stylesheet">` and `<script>` in the head template(s).
   - Defer non-critical JS (`defer` attribute; keep execution order where dependencies exist, e.g. jQuery before dependent plugins).
   - Load non-critical CSS asynchronously (media="print" onload pattern or split critical/non-critical); inline only a minimal critical CSS block if needed for above-the-fold.
   - Add `preconnect` only for origins actually used early (verify — PSI flagged unused preconnect hints; remove hints to origins that are not requested).
2. Images:
   - Convert heavy PNG catalog/branding images to WebP (or AVIF with WebP fallback) where quality allows; the OpenCart `image/cache/` resizer already produces sized variants — verify which template calls bypass it (the header logo is served from `image/catalog/...` unresized).
   - Ensure `width`/`height` (or aspect-ratio CSS) on template `<img>` tags to preserve CLS 0.
   - Add `loading="lazy"` to below-the-fold images (product grids, footer); DO NOT lazy-load the LCP element (hero/first product image/logo).
   - Ensure the LCP image is discoverable early (no background-image-only LCP; consider `fetchpriority="high"` on it).
3. Cache TTL:
   - Add long-lived cache headers for static assets (css, js, images, fonts): `Cache-Control: public, max-age=31536000, immutable` where filenames are versioned by OpenCart; otherwise a pragmatic max-age (≥30 days) — additive block in `.htaccess` (mod_expires/mod_headers) or server config.
4. Fonts:
   - Set `font-display: swap` for web fonts (in @font-face or Google Fonts URL parameter `display=swap`).

## 5. Do not touch
- robots.txt
- sitemap / sitemap_index.xml and its generation
- canonical tags, meta robots, hreflang
- any redirect rules; existing `.htaccess` content outside the newly added cache-headers block (append-only, clearly commented)
- checkout and payment: SimpleCheckout module (`route=extension/SimpleCheckout/...`), Hutko/Checkbox integration, fiscalization
- JSON-LD / schema markup blocks in templates
- Merchant Center feed and its generation
- URL structure, slugs, pagination parameters
- product/category content, titles, meta descriptions

## 6. Likely files / areas (verify against actual project files — do not assume)
- `catalog/view/theme/<active-theme>/template/common/header.twig` (head, CSS/JS includes, logo)
- `catalog/view/theme/<active-theme>/template/common/footer.twig` (scripts before </body>)
- Theme stylesheet(s) under `catalog/view/theme/<active-theme>/stylesheet/`
- Product/category grid templates for `<img>` attributes (`product/product.twig`, `product/category.twig`, module templates for featured products)
- `.htaccess` in web root (cache headers block only)
- OpenCart image cache settings (admin: image dimensions) — read-only check, change only if a template bypasses `image/cache/`
- Codex should verify against actual project files: active theme name, whether a CDN/minifier extension is already installed, and where fonts are loaded from.

## 7. Acceptance criteria (measurable)
- PSI mobile, home page: LCP ≤4.0 s AND FCP ≤2.5 s (lab).
- PSI "Render-blocking requests" estimated savings ≤800 ms (from ≈3,080 ms).
- PSI "Improve image delivery" estimated savings ≤200 KiB (from 773 KiB).
- CLS remains 0 (±0.02) and TBT ≤200 ms on all three test URLs.
- All three test URLs return HTTP 200 and are visually identical to production (above-the-fold screenshot comparison, mobile 390×844 and desktop 1920×1080).
- Header logo served as optimized asset (≤40 KiB) with explicit dimensions.
- No console errors introduced (Chrome DevTools, all three URLs).

## 8. QA / smoke test
- Before/after PSI runs (mobile + desktop) on staging for: `/`, `/catalog/Pokemon`, `/product/Pokemon-boosters-Mega-Dream-EX`; attach both reports to the task.
- Because `header.twig`/`footer.twig` are global, checkout pages are affected: run the manual `bs-checkout-smoke` 11-step plan after deploy to staging and again after production deploy.
- Verify robots.txt, sitemap_index.xml, one canonical tag and one product JSON-LD block are byte-identical before/after (protected zones untouched).
- Verify cart add/remove and login modal still work (JS defer order).

## 9. Rollback note
- Before any edit: file-level backup of every touched template, stylesheet and `.htaccess` (copy with `.bak-2026-08-XX` suffix stored outside webroot or in the project backup location).
- Rollback = restore backed-up files; no DB changes are made in this task, so file restore is complete rollback.
- The `.htaccess` cache block must be delimited with `# BEGIN BS-SPEED-1 cache` / `# END BS-SPEED-1 cache` comments so it can be removed atomically.

## 10. Recommended status after execution
"Staging — awaiting owner review": deploy to staging, attach before/after PSI + screenshots + smoke-test results, then owner approves production deploy. After production: re-run PSI, mark task "Done — monitoring", re-check GSC Core Web Vitals section after 28 days.

---

## Appendix: bs-seo-risk-gate classification (preflight)

1. **Risk: High** (touches `.htaccess` — protected zone; global templates affect all page types including checkout).
2. **Affected assets:** theme header/footer templates, theme CSS, template `<img>` markup, `.htaccess` (additive cache block), web fonts. Page types affected: all (global templates).
3. **Do not touch:** robots.txt, sitemap, canonicals, redirects, schema, Merchant feed, checkout/payment/fiscalization code, URL structure.
4. **Safest next action:** implement on staging only; before/after fetch of protected files (robots.txt, sitemap, one canonical, one JSON-LD block) to prove they are unchanged; owner reviews PSI + screenshots before production.
5. **QA checklist:** PSI before/after (3 URLs × mobile/desktop) · visual parity screenshots · console errors · protected-zones byte-diff · bs-checkout-smoke on staging and production.
6. **Owner approval required: yes** (before production deploy).
7. **Related smoke checks:** bs-checkout-smoke (global templates touch checkout rendering). bs-merchant-schema-qa not required unless schema blocks are accidentally modified — which is prohibited by section 5.
