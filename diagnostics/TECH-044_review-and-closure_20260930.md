# TECH-044 — pre-deploy review, post-deploy QA and closure

Date: 2026-09-30 | Reviewer: Claude (chat) | Executor: Claude Code | Status: closed `Done` on owner authorization

## Pre-deploy review (2026-09-29) — `Deploy OK; non-blocking notes`

- Patch `patches/TECH-044_wp1-resizer-webp_20260929.php` (sha1 `9d74082b5b7d1700905cc381310acdf5f9982e9a`) read in full, 424 lines. C1–C7 present; single target `catalog/model/tool/image.php`; no DB.
- Target in `backup-9.24.2026_16-35-03_boosters.tar.gz` has sha1 `d3acd6ea7c40731444308d0342c3ff1d0eb9f720` = the runner's `BASE_SHA1`.
- `php -l` (PHP 8.4, cloud sandbox) clean on the runner and on the model file produced by applying the runner's transform to the stock `resize()` body. Manual scan: no PHP 8.1+ syntax. Server-side lint + self-test in the runner remain the production gate.
- Non-blocking notes: self-test runs on CLI PHP (web GD proven by existing `.webp` cache files); image sitemap `<image:loc>` switches to `.webp`; storefront shows PHP errors to visitors (`config_error_display = 1`) — separate issue, not in scope.

## Post-deploy QA (2026-09-30)

| Check | Result | Source |
|---|---|---|
| `/catalog/Pokemon` cards | 15/15 `-250x250.webp`, no PHP text | Claude, WebFetch |
| `/catalog/Pokemon/Pokemon-booster-box` | 13/13 resizer images `.webp` (1 non-resizer logo) | Claude, WebFetch |
| Product `Pokemon-booster-box-Inferno-X` | main `-500x500.webp`, popup `-700x700.webp`, thumbs `-100x100.webp` | Claude, WebFetch |
| Home featured cards | first fetch returned `.png`/`.JPG`; re-fetch after owner traffic: all `.webp` | Claude, WebFetch |
| Rich Results Test, Product | valid, no new errors | owner |
| Image sitemap (`uk-ua/sitemap.xml`) | regenerated, all `<image:loc>` `-700x700.webp` | owner-supplied file |
| Transparent images, cart, mini-cart, iPhone Safari | OK. Grey product-gallery background / cart thumb border confirmed pre-existing by owner | owner |
| Cache warm-up over all sitemap URLs | done, second pass fast | owner |

Owner observation: first view of each image-bearing page after deploy was slow — server-side generation of the new `.webp` cache files, once per image/size, not browser cache. Mitigation for new products: rerun the sitemap warm-up loop after adding products.

## WP2 — card dimensions (done by owner, admin settings, no code)

Trigger: owner reported category/home cards visibly softer than before on phones. Cause (WP0 §4): 250 px cards painted at ~490–530 px on 2× screens; lossless PNG masked the upscale, lossy WebP q80 does not. Fix applied by the owner 2026-09-30:

- System → Settings → Image → product list size 250×250 → **500×500**;
- Extensions → Modules → «Рекомендовані» (Featured) → module «Головна» width/height 240 → **500** (and «Популярні товари» if it was 240/250 — owner-reported).

WP0 weight reference: a 500 px card at q80 is 26–57 KiB vs. 84–122 KiB for the old 250 px PNG. Owner confirmed cards sharp after warm-up. `thumb.twig` `width="240" height="240"` left unchanged — square ratio, CSS sizes the box.

## Open / follow-ups

- Tier 3: server error log check scheduled for 2026-10-01 12:00 Kyiv (reminder in session). Watch-only; does not reopen the task unless entries appear.
- Not part of TECH-044: `config_error_display = 1` on production (PHP errors shown to visitors) — candidate separate task, owner decision pending.
