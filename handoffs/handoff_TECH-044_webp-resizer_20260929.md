# Handoff — TECH-044: WebP output from the OpenCart image resizer

Date: 2026-09-29 | Notion: `3b46bf20-bdb4-81b9-9169-fa9657c0cab0` | Follow-up in queue: TECH-047 (starts after this task)
Executor: Claude Code · model=Opus · thinking=high — owner-assigned 2026-09-29; the recommendation matches: WP0 needs local image processing and byte measurement, and WP1 edits a shared core file whose output reaches every storefront image and the Product JSON-LD `image`.

## 1. Task ID

`TECH-044` — Product card images: WebP output + resizer dimensions. Owner decision 2026-08-06: **variant A** — patch the resizer to emit WebP regardless of source format. No DB writes, no per-product admin work. Variants B (re-upload in admin) and C (`UPDATE oc_product.image`) are rejected.

## 2. Context

- After TECH-013 (2026-08-06) mobile image weight was 252 KiB home / 511 KiB category / 830 KiB product against a 200 KiB target. Those numbers are **seven weeks old** — re-measure in WP0; do not quote them as current.
- Live resizer (`backup-9.24.2026_16-35-03_boosters.tar.gz`), `catalog/model/tool/image.php::resize()`:
  - output name = `cache/<name>-<W>x<H>.<source extension>` → PNG sources stay PNG;
  - when source dimensions already equal the target it **`copy()`s the original** instead of re-encoding;
  - regeneration trigger is `!is_file($image_new) || filemtime(source) > filemtime(cache)`.
- `system/library/image.php::save()` picks the encoder from the **output file extension** (`jpg|jpeg` → `imagejpeg`, `png` → `imagepng`, `gif`, `webp` → `imagewebp`). `resize()` there already calls `imagesavealpha` for PNG and WebP. So changing the output extension in the model is the whole mechanism — no library change is expected.
- Image settings in the DB (2026-09-12 dump): product 250×250, thumb 500×500, popup 700×700, related 350×350, additional 100×100, cart 120×120, category 200×200. `thumb.twig` renders `<img … width="240" height="240" loading="lazy">`.
- **Merchant feed is not fed by the resizer.** `~/merchant-feed-build.php` (hosting home, outside `public_html`) builds `image_link` as `<base>image/<p.image>` — the original file. TECH-044 does not change the feed.
- **Product JSON-LD is fed by the resizer.** `product.twig` ~line 987: `"image": [ {{ popup|json_encode }} … ]` → after WP1 the schema image URLs end in `.webp`.
- `catalog/controller/mail/order.php` does not call `resize()` — order emails are not affected (verified on the 09-24 backup).
- `.htaccess` cache tier already matches `webp` (`FilesMatch "\.(png|jpe?g|gif|webp|avif|svg|ico)$"`).
- Notion records GD with WebP support on the server as verified during TECH-013 WP2. Treat that as a claim; the patch must still gate on it at runtime (see WP1).

## 3. Goal

Every storefront image produced by `model_tool_image->resize()` is served as WebP, with lower total image bytes on Tier 1 pages, no visual regression (transparency, sharpness), and no change to the Merchant feed.

## 4. What to change — two work packages, one patch

### WP0 — read-only baseline and measurement (no patch; report only)

1. From the newest backup, list every caller of `model_tool_image->resize(` / `resize(` under `public_html/catalog/` and `public_html/extension/*/catalog/`. For each: route, config key used, where the URL ends up (HTML `<img>`, JSON-LD, JS, email, OG tag). Flag any consumer that may not accept WebP.
2. Baseline: for home, `/catalog/Pokemon`, one product page — total image bytes and per-image format/size on mobile (Lighthouse/PSI or a headless browser at 360 px). PSI: at least 3 runs, mobile and desktop kept separate.
3. Offline conversion test: extract 10–15 real source images from `public_html/image/catalog/` in the backup (include at least one transparent PNG and the heaviest product-card PNGs), resize them locally with the same GD calls to 250×250 and 500×500, save as PNG and WebP at 2–3 quality levels. Report bytes and a visual judgement. Pick the quality value for WP1 from this data, not from a default.
4. Dimension finding for WP2: measure the rendered CSS size of the product card image at 360 / 390 / 768 / 1440 px widths and state the pixel size needed at DPR 2. Report only — do not change settings.

Output: `diagnostics/TECH-044_wp0-baseline_report_20260929.md` (or the actual date).

### WP1 — patch `catalog/model/tool/image.php` → WebP output

Patch file: `patches/TECH-044_wp1-resizer-webp_<YYYYMMDD>.php`. One file edited.

- Output extension becomes `webp` for PNG / JPEG / WebP sources when the runtime supports it: `function_exists('imagewebp')` **and** `imagetypes() & IMG_WEBP`. If not supported → keep today's behaviour exactly (source extension). No fatal, no warning.
- **GIF sources keep their current behaviour** (animation would be lost).
- When the output format differs from the source, always re-encode through `\Opencart\System\Library\Image` — never `copy()` a PNG into a `.webp` name. The equal-dimensions branch is where this bug would appear.
- Quality: pass the value chosen in WP0 explicitly via `save($file, $quality)`; the executor must verify how `Image::save()` forwards quality to `imagewebp()` in the live file.
- Transparency must survive (the library's `imagesavealpha` path must be reached for PNG sources).
- The returned URL keeps the current shape: `config_url . 'image/' . cache/<name>-WxH.webp` (space → `%20` handling unchanged).
- No cache purge. New `.webp` cache files are generated on first request; existing `.png` cache files stay on disk untouched (this is what makes rollback instant).
- Idempotent marker comment in `image.php` (e.g. `// TECH-044-WEBP`), per C5.
- PHP 8.0-compatible.

### WP2 — card image dimensions (NOT in this patch)

Deferred until WP1 is live and TECH-045 is closed. WP0 supplies the numbers; changing `config_image_product_*` is an owner setting (Admin → Settings → Image) or a separately approved DB write — owner decides after WP0. `thumb.twig` `width`/`height` attributes are part of WP2, not WP1.

## 5. Do not touch

- `system/library/image.php` — unless WP0 proves a defect that blocks WP1; then stop and report, do not widen scope silently.
- `admin/` — including `admin/model/tool/image.php` and the admin image manager.
- `thumb.twig`, `header.twig`, `boostershop-ds.css` — pending/active TECH-045 patches (`wpd`, `wpe`) edit them. No markup, `<picture>` or `srcset` work in this task.
- `product.twig` JSON-LD — schema changes only through the URL the resizer returns; no edits to the template.
- `~/merchant-feed-build.php` and `merchant-feed.tsv`.
- Source images in `image/catalog/` — never overwritten, converted or deleted.
- `image/cache/` — no deletion, no purge.
- Database — no reads-for-write, no settings changes, no `oc_product.image` changes.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`.
- Checkout, payment, Hutko, Checkbox/fiscalization, Nova Poshta, order status, email templates.

## 6. Likely files / areas

- `catalog/model/tool/image.php` — **confirmed** as the only write target (09-24 backup).
- `system/library/image.php` — read only; confirmed encoder-by-extension.
- Callers (read only, inventory in WP0): `catalog/controller/product/product.php` (popup/thumb/additional, 50×50 variant thumbs), `catalog/controller/product/category.php` (product + category image), `common/home.php`, module controllers under `extension/*/catalog/controller/module/`, cart/mini-cart. The executor must verify the complete list against the actual backup.

## 7. Acceptance criteria

1. `curl -sI <a category card image URL>` → URL ends `-250x250.webp`, `Content-Type: image/webp`, `200`.
2. Home, `/catalog/Pokemon`, one product page: every `<img>` coming from `image/cache/` is `.webp` (except GIF sources, if any).
3. Product page JSON-LD `image` URLs end in `.webp` and return `200 image/webp`; Rich Results Test on that URL shows Product valid with **no new errors or warnings** vs. before (per `bs-merchant-schema-qa`).
4. A transparent-PNG product keeps a transparent background in the card and on the product page (no black or white box).
5. Total mobile image bytes on `/catalog/Pokemon` and the product page are lower than the WP0 baseline; the report gives before/after numbers (3+ PSI runs, mobile/desktop separate). The 200 KiB target is a goal, not an acceptance gate.
6. With WebP unsupported (simulated locally by forcing the capability check false), output is byte-for-byte today's behaviour.
7. `merchant-feed-build.php` untouched; its `image_link` logic re-read and confirmed resizer-independent in the report.
8. No new PHP warnings/notices in the error log after rendering the Tier 1 URLs.

## 8. QA / smoke test (owner, after deploy)

1. Upload the patch to `~/public_html`, run `php TECH-044_wp1-resizer-webp_<date>.php`; expect a success line and self-delete.
2. Open the Tier 1 smoke URLs (`AGENTS.md` → Tier 1). The first open of each page is slower while WebP files are generated — open each twice.
3. Hard refresh (Ctrl+F5); right-click a card image → Open in new tab → address ends in `.webp`.
4. Visual check on phone and desktop: cards, product gallery, related products, mini-cart, cart, checkout thumbnails. Look for black backgrounds, blur, missing images. Checkout: visual only, no test order needed — no checkout code changes.
5. Safari on iPhone: one category and one product page render images.
6. Rich Results Test on one product URL (see acceptance 3).
7. Merchant Center: nothing to do; the feed is unchanged.

## 9. Rollback

- Restore `catalog/model/tool/image.php` from `_patch_backups/TECH-044_wp1-resizer-webp_<date>-<ts>/`.
- Effect is immediate: the old `.png` cache files were never removed, so the resizer returns them again.
- The generated `.webp` files in `image/cache/` are inert after rollback and may stay. Deleting them is optional, owner-run, and not required.
- No DB rollback — no DB change.
- **Rollback trigger:** any missing/broken product image, black backgrounds on transparent images, or a new Rich Results error on Product.

## 10. Recommended status after execution

- Handoff written, executor assigned → Notion `In progress` (set 2026-09-29).
- After WP1 deploy + owner QA pass → WP1 closed in the Notion note; the task stays `In progress` until WP2 is either delivered or explicitly dropped by the owner.
- Delivery: patch file into `patches/`, owner uploads to `~/public_html` and runs `php <patch>.php`. The executor never commits, pushes or deploys, and never writes Notion.
- Report: `diagnostics/TECH-044_wp1-resizer-webp_report_<date>.md` per `templates/codex-report-template.md`. Review by Claude (chat) before deploy.
