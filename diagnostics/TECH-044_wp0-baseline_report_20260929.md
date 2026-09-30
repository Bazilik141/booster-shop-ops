# TECH-044 WP0 — baseline, resizer consumers, WebP quality, card dimensions

Date: 2026-09-29 · Executor: Claude Code (Opus, high) · Read-only: nothing on production was changed.
Handoff: `handoffs/handoff_TECH-044_webp-resizer_20260929.md` §4 WP0.

Inputs: `backup-9.24.2026_16-35-03_boosters.tar.gz` (files + `mysql/boosters_ocart49.sql`, extracted
outside the repo), live site 2026-09-29 13:00–13:20 UTC (curl with a mobile UA, the built-in browser,
pagespeed.web.dev = Lighthouse 13.5.0). Converted images, crops and harness runs stayed in the session
scratchpad; they are not in the repository.

## Verdict

WP0 does not contradict the handoff; WP1 was built as specified. Facts the handoff did not have:

1. **`Image::save()` does not pass `$quality` to WebP.** `system/library/image.php` calls
   `imagewebp($this->image, $file)`; PHP's default for that call is 80. The measured choice (§3) is
   also 80, so WP1 passes 80 explicitly and the library stays untouched. Any other value would need a
   library change — the handoff's stop condition.
2. **Production already writes WebP through this exact path.** Live `-250x250.webp` / `-350x350.webp`
   caches of WebP sources (MS1, scarlet-and-violet-black-bolt, OP-15) equal local encodes of the same
   sources to within 26 bytes, two of six byte-identical. So web-SAPI GD can write WebP, and local byte
   predictions (§5) transfer to production.
3. **Consumer missing from handoff §2: the image sitemap.** `extension/ps_google_sitemap` puts
   `resize(…, popup 700×700)` into `<image:loc>`. The static `sitemap_index.xml` changes at the next
   nightly regen (04:15). 29 of its 122 entries are already `.webp`; Google image sitemaps accept WebP.
   The first request after deploy generates ~119 new 700 px files — 17 s locally (19.5 s for today's
   PNGs). On shared hosting that may exceed `sitemap-regen.sh`'s `curl --max-time 30` once; the script
   then logs `FAIL` and keeps the previous file. Owner warm-up step is in the WP1 QA list.
4. **Every PHP warning reaches the customer.** `catalog/controller/startup/error.php` echoes warnings and
   notices into the page (`config_error_display = 1` in the DB) and ignores `@`. The WebP path must emit
   none; the WP1 runner's self-test fails on any. Showing PHP messages with server paths to visitors is
   itself an issue outside TECH-044 — flagged for triage, not changed.
5. **Home cards are 240×240**, from the Featured module (`opencart.featured.6`, home layout,
   `content_bottom`), not `config_image_product` 250.
6. **Stale `.webp` cache names exist.** 8 files in `image/cache/catalog/Other/` (stems `Yu-Gi-Oh OCG Duel
   Monsters QUARTER CENTURY ART COLLECTION 25th BOX`, `arkush-na-9-kyshenok-game-7-days-9-pocket-page[.]`)
   were made from WebP sources later replaced by PNGs. Both PNG sources are newer than those files, so
   the mtime trigger regenerates them — no wrong picture. No folder holds two sources that differ only by
   extension (0 of 284); WP1 still keeps today's naming if that ever happens.

Image settings in the 09-24 dump equal the handoff's list. No `ocp5_event` row targets
`model/tool/image`; no OCMOD override of the file exists. There are no GIF sources in `image/`.

## 1. Consumers of `model_tool_image->resize()`

| Caller (09-24 backup) | Size | URL lands in | WebP |
|---|---|---|---|
| `product/category.php:289`, `search.php:220`, `special.php:128`, `manufacturer.php:201` | product 250 | card `<img>` | ok |
| `product/category.php:152` | category 200 | category header image | ok |
| `product/product.php:349-350` | popup 700 / thumb 500 | gallery; **Product JSON-LD `image`** (`product.twig:987-989`) | ok — WebP is a supported structured-data image format; acceptance 3 verifies |
| `product/product.php:363-364` | popup 700 / additional 100 | gallery thumbs + JSON-LD `image[]` | ok |
| `product/product.php:469` | fixed 50 | option-value images | ok |
| `product/related.php:61` | related 350 | related cards | ok |
| `checkout/cart.php:163`, `common/cart.php:76` | cart 120 | cart page, mini-cart | ok |
| `account/wishlist.php:111`, `product/compare.php:86` | 55 / 90 | account pages | ok |
| `cms/blog.php:149,182,400`, `information/contact.php:39,62` | 1140×380 / 268 | blog, contact | ok |
| `smart_filter/smart_filter.php:113,174,176` | theme image keys | AJAX filter results | ok |
| `extension/opencart` modules featured (home), bestseller, latest, special, banner, blog | module setting | home cards | ok |
| `extension/ps_live_search` `:147,177,206` | module setting | live-search dropdown (JSON → `<img>`) | ok |
| `extension/ps_google_sitemap` `:98,108,141,236` | popup 700 | sitemap `<image:loc>` | ok (Verdict 3) |
| `extension/SimpleCheckout` `:2573` | related 350 | legacy checkout, out of the request path | n/a |

Not fed by the resizer: `og:image`/`twitter:image` (`common/header.php:80-108` uses `image/<original>`),
Merchant feed `image_link` (`~/merchant-feed-build.php:86-88,373` → `<base>image/<p.image>`), order
e-mails (`mail/order.php` has no resize call), admin (own model). No template or script rewrites a
cache URL by its extension.

## 2. Baseline, 2026-09-29

### 2.1 Resizer images per page (curl, every `<img>` incl. lazy)

| Page | Resizer images | Formats | Bytes | Non-lazy resizer image |
|---|---|---|---|---|
| Home | 4 of 7 | 2 png · 1 webp · 1 JPG | 151.9 KiB | none (LCP is the `category-tile` WebP, not resizer) |
| `/catalog/Pokemon` | 15 of 16 | 10 png · 2 jpg · 3 webp | 907.4 KiB | first card PNG 83.8 KiB (eager) |
| `/product/One-Piece-Boosters-OP-11` | 8 of 12 | 7 png · 1 webp | 854.9 KiB | main image `-500x500.png` 267.8 KiB (LCP) |

Heaviest single files: related cards `-350x350.png` 130–145 KiB, category cards `-250x250.png`
71–122 KiB. Built-in browser at 360 px (DPR 2) loads 5 category cards on first paint.

### 2.2 PSI (Lighthouse 13.5.0) — median [runs], runs 13:07–13:19 UTC

| Page · device | Perf | FCP ms | LCP ms | TBT ms | CLS | SI ms | Image transfer | "Improve image delivery" |
|---|---|---|---|---|---|---|---|---|
| Home · mobile | 71 [67 71 73] | 3151 | 5402 [6002 5326 5402] | 101 [174 101 0] | 0 | 4936 [5081 4936 3520] | 290.9 KiB, 8 req | 217 KiB |
| Home · desktop | 99 [98 100 99] | 441 [401 441 441] | 843 [1081 593 843] | 22 | 0.013 [0 0.013 0.013] | 704 | 336.3 KiB, 8 req | 257 KiB |
| Category · mobile | 73 [69 73 73] | 3151 | 5401 [6152 5401 5401] | 0 [20 0 0] | 0 | 3540 [4936 3540 3524] | 409.9 KiB, 10 req | 322 KiB |
| Category · desktop | 83 [77 83 83] | 428 [401 428 448] | 1461 [1461 1441 1461] | 17 [7 17 42] | 0.254 [0.394 0.254 0.253] | 750 [805 719 750] | 933.2 KiB, 17 req | 803 KiB |
| Product · mobile | 66 [66 66 66] | 3751 | 6601 [6751 6601 6601] | 0 | 0 | 4842 [4842 4838 4905] | 914.6 KiB, 12 req | 838 KiB |
| Product · desktop | 98 [85 98 100] | 440 [427 447 440] | 781 [781 1061 721] | 23 [29 9 23] | 0.011 [0.293 0.004 0.011] | 592 [589 592 641] | 914.6 KiB, 12 req | 844 KiB |

LCP element: home = `section.bs-cattiles … picture > img` (tile), category = first `.bs-pcard__media img`,
product = `.bs-product__main-img img`. Home desktop run 2 was served from PSI's cache and replaced by a
fourth run; home mobile run 2 (69) is not in the medians.

## 3. Offline conversion test → WebP quality 80

Method: the live `system/library/image.php`, same `new Image()` → `resize(W, H)` → encode path as the
storefront; 14 real sources (12 PNGs, all with transparency, incl. the heaviest card PNGs and the
flat-gradient 3D print; two JPEGs incl. upper-case `.JPG`); 250 and 500 px; PNG as today and WebP q70/75/80/85/90. SSIM on luminance
(8×8 windows) against the lossless PNG of the same resize; visual check on 4–5× nearest-neighbour crops
of fine Japanese text and line art, on white and on a checkerboard.

| Source (250 px unless noted) | Today | q75 | **q80** | q85 | SSIM q75 / **q80** / q85 |
|---|---|---|---|---|---|
| Inferno X box (Photoroom, alpha) | 122.3 KiB | 13.0 | **15.0** | 17.7 | 0.981 / **0.986** / 0.991 |
| Chaos Rising Boosters (alpha) | 83.8 | 12.5 | **14.4** | 16.3 | 0.993 / **0.996** / 0.997 |
| pokemon_tcg_mega_gallade_ex (alpha) | 86.5 | 11.9 | **13.6** | 15.7 | 0.993 / **0.996** / 0.997 |
| Duel Masters DM24-RP3 box (alpha) | 112.7 | 16.8 | **19.0** | 21.5 | 0.991 / **0.994** / 0.996 |
| OP-11 booster, 500 px (product LCP) | 267.9 | 26.3 | **30.7** | 36.2 | 0.992 / **0.994** / 0.996 |
| Flat Luffy picture, 500 px (worst SSIM) | 251.5 | 11.0 | **13.2** | 16.9 | 0.976 / **0.979** / 0.983 |
| Mystery Mix XL (JPEG; today = JPEG q90) | 17.3 | 7.7 | **9.0** | 10.4 | 0.988 / **0.990** / 0.993 |
| **All 14 at 250 px** | 1042.0 | 143.8 | **165.6 (16%)** | 190.0 | — |
| **All 14 at 500 px** | 3634.2 | 410.1 | **477.5 (13%)** | 556.0 | — |

Visual: q75 visibly smears red line art and the small `ランダム5枚入り` text at 4–5×; q80 differs from
PNG only in faint gradient smoothing at 5×, nothing at 1–2×; q85 adds 15–17% bytes for no visible
change. Cards are painted at up to 2.1× their pixel size on phones (§4), so the 4–5× view is the
relevant stress case. Alpha is bit-exact at every quality (libwebp encodes alpha losslessly).
**Chosen: q80.**

## 4. WP2 input — rendered card size (report only; nothing changed)

Built-in browser, `object-fit: contain`, square images → painted size = shorter side of the box.
Widths 768 and 1440 were emulated at DPR 1; the DPR 2 column is the pixel need on a 2× screen.

| Image (served px) | 360 | 390 | 768 | 1440 | Need at DPR 2 (max) |
|---|---|---|---|---|---|
| Category card (250) | 243.6 | 265.4 | 243.6 | 258.8 | **531** |
| Home featured card (240) | 243.6 | 265.4 | 176.2 | 221.9 | 531 |
| Related card (350) | 215.4 | 235.4 | 435.4 | 221.9 | 871 (768 px, single column) |
| Product main (500) | 336 | 340 | 332 | 460 | 920 |
| Product additional thumb (100) | 70 | 70 | 70 | 70 | 140 |

Every card is upscaled on a 2× phone (250 px source painted at 487–531 px); the category card is
slightly upscaled even at DPR 1 on 1440 (259 > 250). `thumb.twig` declares `width="240" height="240"`.
Weight reference for the WP2 decision (q80, §3 booster/box PNG sources): a 500 px card is 26–57 KiB
versus 8–19 KiB at 250 px.

## 5. Expected effect of WP1

Exact local encodes of the images on each page (local WebP matches live within 0.2%, Verdict 2):

| Page | Resizer images today | After WP1 | Non-lazy resizer image |
|---|---|---|---|
| Home | 151.9 KiB | 27.8 KiB | — |
| `/catalog/Pokemon` | 907.4 KiB | 177.6 KiB (20%) | 83.8 → 14.4 KiB |
| Product OP-11 | 854.9 KiB | 126.9 KiB (15%) | 267.8 → 30.7 KiB (LCP) |

Whole catalogue (284 sources × 8 storefront sizes): WebP output is 16–33% of today's bytes per size;
no WebP file is larger than today's file; alpha matches the PNG output exactly for all 143 PNG sources
(131 with transparency).
