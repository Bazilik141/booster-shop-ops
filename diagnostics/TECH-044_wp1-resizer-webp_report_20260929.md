# Report — TECH-044 WP1: the storefront image resizer writes WebP

Date: 2026-09-29 · Executor: Claude Code (Opus, high) · Status: patch ready, **not deployed**; needs
Claude (chat) review, then owner deploy and QA.
Baseline, consumer inventory and quality choice: `diagnostics/TECH-044_wp0-baseline_report_20260929.md`.

## Scope

Handoff §4 WP1, one file: `catalog/model/tool/image.php`. `resize()` first calls a new private
`resizeWebp()`; whatever that declines returns `''` and the original `resize()` body runs unchanged
(it is kept byte for byte). WebP at quality 80, passed explicitly to `Image::save()` — which does not
forward it to `imagewebp()`, whose default is 80 (WP0 Verdict 1). WP2 and every file in handoff §5
are untouched.

Beyond the handoff's wording, all fail-safe (they fall back to today's output, never to a new behaviour):

| Guard | Why |
|---|---|
| WebP is decided by the file's **content** on generation (`getimagesize`), not only its extension | a GIF saved as `.png`/`.jpg` keeps today's path, so animation is never lost and no palette image reaches `imagewebp()` |
| `X.png` beside `X.webp`/`X.jpg` (any case) → today's naming for that image | both would share one `.webp` cache name and show each other's picture; 0 such pairs today. Not covered: `X.png` + `X.PNG` in one folder |
| Encode produced no file or an empty one → empty file removed, today's path | a 0-byte `.webp` would otherwise be served from cache forever |
| Runner refuses unless the target's sha1 equals the audited 09-24 file | any later edit to this core file must be reviewed, not overwritten |
| Runner lints and **self-tests the new class on the host's PHP + GD before writing** | the storefront shows every PHP warning to customers (WP0 Verdict 4); the self-test fails on any warning |

## Files touched

```
patches/TECH-044_wp1-resizer-webp_20260929.php              — runner (edits catalog/model/tool/image.php)
diagnostics/TECH-044_wp0-baseline_report_20260929.md        — WP0
diagnostics/TECH-044_wp1-resizer-webp_report_20260929.md    — this report
```

`ROADMAP_FLOW` not changed: WP1 is not deployed and TECH-044 stays In progress (handoff §10).

## Local verification (PHP 8.3.30, the live files from the 09-24 backup)

Runner, fake webroot holding the live `image.php` and system files:

```
target_sha1=d3acd6ea7c40731444308d0342c3ff1d0eb9f720
php_l:new_file=ok
selftest:webp_supported=yes
selftest: png_url, png_is_webp, png_size, png_transparency_kept, png_opaque_centre_kept,
          cache_hit_same_url, jpeg_equal_size_url, jpeg_equal_size_reencoded, uppercase_jpg_url,
          webp_equal_size_url, webp_equal_size_copied, twin_name_keeps_old_path, gif_unchanged,
          missing_file_empty  = ok
backup=_patch_backups/TECH-044_wp1-resizer-webp_20260929-<ts>/catalog/model/tool/image.php
php_l:catalog/model/tool/image.php=ok
changed=catalog/model/tool/image.php sha1=52cba0fcf85fcad7f81ba5fe5a4a0c4b276e7f55
done=ok                                   (runner self-deleted)
```

Modified target (one appended line) → `error=target_differs_from_audited_version`, `done=failed`, target
and runner left untouched.

Model against all 284 real sources in `image/catalog/`, same harness for every variant, any PHP
warning counted:

| Check | Result |
|---|---|
| Acceptance 6 — capability forced off vs original model, 284 sources × 250/100 px + each source at its own size (the `copy()` branch) | 852 calls: identical URLs, byte-identical cache trees |
| WebP on, 284 sources × 250/240/350/500/700/100/120/50 + own size | 2,556 calls, 0 warnings, every file RIFF/WEBP |
| Transparency, all 143 PNG sources at 250 px (131 transparent) | alpha channel identical to today's PNG output |
| Size | no WebP file larger than today's; 16–33% of today's bytes per size |
| WebP sources | same URL and bytes as today |

`php scripts/check-php-host-compat.php` on the runner and on the produced `image.php`: clean (nothing
newer than PHP 8.0). The real 8.0 gate is `php -l` on the host — in the run command below.

## Idempotency

Marker `TECH-044-WEBP` (4 occurrences in the patched file) → `already_applied=yes`, nothing written.

## Run command (owner, after Claude review)

```bash
cd ~/public_html && php -l TECH-044_wp1-resizer-webp_20260929.php && php TECH-044_wp1-resizer-webp_20260929.php
```

Expected: every `selftest:` line `=ok`, `done=ok`, file gone. If it prints `selftest:webp_supported=no`,
the site's output is unchanged — report it; WP0 shows the web server does write WebP, so the CLI would
differ from it.

## Post-deploy QA checklist

- [ ] Open each Tier 1 URL (AGENTS.md) twice; the first open generates the `.webp` files and is slower.
- [ ] Category card image: right-click → open in new tab → ends `-250x250.webp`; then
      `curl -sI "https://boostershop.website/image/cache/catalog/Pokemon/Abyss%20Eye%20Booster-250x250.webp"` → `200`, `content-type: image/webp`.
- [ ] Phone and desktop: cards, product gallery, related, mini-cart, cart, checkout thumbnails — no
      black box on transparent packs (OP-11 main image is one), no blur, no missing image. Checkout: look only.
- [ ] Safari on iPhone: `/catalog/Pokemon` and the OP-11 product render images.
- [ ] Rich Results Test on `https://boostershop.website/product/One-Piece-Boosters-OP-11`: Product valid,
      no new errors or warnings; `image` URLs end `.webp`.
- [ ] Open `https://boostershop.website/uk-ua/sitemap.xml` once and let it finish (first time up to about a
      minute) — pre-generates the 700 px files so the 04:15 regen fits its 30 s limit. `<image:loc>` now ends `.webp`.
- [ ] Next morning: `~/logs/sitemap-regen.log` shows `OK locs=…`.
- [ ] OpenCart error log (`/home2/boosters/ocartdata/storage/logs/`): no new entries after the Tier 1 opens.
- [ ] Acceptance 5, after-numbers: 3 PSI runs per Tier 1 page, mobile and desktop, against the WP0 table.

## Rollback

```bash
cd ~/public_html && cp "$(ls -d _patch_backups/TECH-044_wp1-resizer-webp_20260929-* | tail -1)/catalog/model/tool/image.php" catalog/model/tool/image.php
```

Immediate: `resize()` returns the old `.png`/`.jpg` cache files again (never deleted). Generated `.webp`
files become inert and may stay. No DB rollback. Trigger: any missing or broken product image, a black
background on a transparent image, a new Rich Results error on Product, or a PHP warning on a page.

## Side effects / risks

- Every image URL from the resizer changes, so first visits after deploy regenerate each image once
  (source decode dominates; same cost as today's cache fill). Visitors hitting a page before its
  `.webp` exists wait for generation; the sitemap warm-up above covers the one bulk request.
- The image sitemap switches PNG/JPEG entries to `.webp` at the next regen (WP0 Verdict 3). Old URLs
  keep answering `200` because the old cache files stay.
- `image/cache` grows by the new files (≈ 16–33% of the size of the files they replace).
- If card URLs still end `.png` a few minutes after `done=ok`, the web server's OPcache has not
  re-read `image.php` yet; it takes effect when the PHP workers recycle. Nothing to roll back in that case.
