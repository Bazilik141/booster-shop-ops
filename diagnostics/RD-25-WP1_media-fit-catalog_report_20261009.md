# Codex Report — RD-25 WP1: media fit and catalog grids

Date: 2026-10-10 · Revision 3 (same pre-deployment patch filename)
Status: **F5 corrected; F1 retained; revision 3 prepared for independent re-review. Not deployed; owner QA remains open.** F6 (featured payload) remains open and non-blocking by explicit owner decision.

## Scope and authority

Owner authorization: RD-25 WP1 r2 returned for changes on 2026-10-10: implement F5 aspect guard, metadata version 3, rerun classification and all runner/UI checks, list every changed class and re-pin hashes. F1 is accepted as implemented; F3 remains accepted without source-alpha trimming. F6 remains open, non-blocking. WP1 only; no commit, push, deploy, roadmap/status changes, WP2 or WP3. Review: `diagnostics/RD-25-WP1_media-fit-catalog_claude-review-r2_20261010.md`.

Canonical handoff and `design/DECISIONS.md` / “Product media slot, RD-25” were read from **master**, without switching the shared dirty checkout. Current implementation was prepared in Codex desktop. This is implementation evidence, not Claude review or production acceptance. Unrelated repository changes were preserved.

### Current source and RD-26 gate

Only the two owner-provided October 9 archives supplied patch source. The September 24 backup was not used. `RD-25-live-20261009.tar.gz` contains the deployed `RD-26 20261009` single amber preorder badge and header token `rd26-preorder-badge-20261009`; the stop condition about pre-RD-26 source does not apply. The followup supplies `featured.twig` and source images. Archive checksums: [delivery manifest](RD-25-WP1_media-fit-catalog_evidence_20261009/delivery-manifest.json).

The runner binds all eleven target files to the supplied source hashes and exact single anchors; it refuses drift before any write. Header-token changes alone are normalized, shape-checked and replaced wholesale according to convention 8. RD-26 badge styling remains intact.

## Diagnosis and source history

- `system/library/image.php::resize()` builds a padded canvas of exactly WxH; `catalog/model/tool/image.php` already contains TECH-044 WebP behavior. Both existing methods and callers are retained byte for byte.
- Supplied `boostershop-ds.css:263–269` has `331/240` media geometry and centered `contain`. UI-PCARD-D established these source rules; RD-25 edits them in place.
- UI-PCARD-BADGE keeps badges at top 4px / side 18px. CAT-004 SD-7 adds the badge column. TECH-045-WPE sets existing 16px CTA and sale typography. These rules were inspected before editing.
- Stock `common.js` can replace the entire `#product-list` class string; the existing DS source documents that behavior. The new **data attribute** survives it. Bootstrap `mb-3` would add a second row spacing beside the requested 24px gap, so actual grid children replace it with a scoped class.
- `related.twig` uses `row-cols-1`, but its existing mobile layout is a swipe rail with progress UI. It stays a rail; WP1 fit/framing reaches its cards through the shared partial.

## Files delivered and production targets

Main patch: `patches/RD-25-WP1_media-fit-catalog_20261009.php` (32,270 bytes).

Production targets:
- `system/library/image.php`
- `catalog/model/tool/image.php`
- `catalog/controller/product/thumb.php`
- `catalog/view/template/product/thumb.twig`
- `catalog/view/stylesheet/boostershop-ds.css`
- `catalog/view/template/product/category.twig`
- `catalog/view/template/product/search.twig`
- `catalog/view/template/product/special.twig`
- `catalog/view/template/product/manufacturer_info.twig`
- `extension/opencart/catalog/view/template/module/featured.twig`
- `catalog/view/template/common/header.twig`

Review the readable [source diff](RD-25-WP1_media-fit-catalog_evidence_20261009/source.diff) and [before/after normalized hashes](RD-25-WP1_media-fit-catalog_evidence_20261009/targets.json). Product-page controller/template, related template, cart/checkout, vendor search, source images and database are outside the runner's write set.

## Fit plan and cache names

- New library `Image::resizeFit()` scales within **700×875**, saves actual scaled dimensions and preserves alpha. It never adds a padding canvas or enlarges a small original. This capacity supports the tested listing slots at 2× where source pixels suffice; undersized sources cannot become true 2× assets.
- Model API: `resizeFit(filename,width,height)` and `mediaInfo(filename)`. The thumb controller uses the original `image` supplied with the product row. Product-ID-only callers read their original once per request; no original path is reconstructed from a cache filename.
- Image cache: `image/cache/rd25-fit/<sha256(version:path:mtime:size)>-700x875-fit.webp`. The canonical source path includes its extension; same-stem PNG/JPEG cannot collide. Without WebP output, `.png` / `.jpg` / `.webp` native output is used. No legacy cache is overwritten or deleted.
- Classification: `DIR_CACHE/rd25-media-v3-<sha256(canonical-source-path)>.json`, invalidated on version, source mtime or size changes. `RD25_MEDIA_VERSION` is **3**; old v1/v2 metadata cannot supply the previous class. Old metadata files remain untouched. Corrupt or stale-version metadata in the new namespace is regenerated once. The fit-image algorithm/key remains v1 because classification does not change fit pixels. Warm requests use file metadata and cached JSON, with zero GD decodes. Locks and temporary files prevent competing first requests from publishing partial files; generated public images are chmod 0644.
- Unavailable storage, invalid/outside source, unsupported formats and missing images retain the original thumbnail/placeholder. Blank or placeholder thumbs are not newly decoded.

### Cut / photo rule and counts — F5 corrected, F1 retained

Decision order:

1. **Source w/h <0.70 or >1.45 → `cut`, regardless of corners.** Reason: `aspect_outside_cover_band`.
2. Within the inclusive **0.70–1.45** band, PNG/WebP with all four normalized corner alpha values ≤16/255 → `cut`, reason `transparent_corners`.
3. Within that band, all four corners fully opaque (alpha 255) and every R/G/B channel ≥240 → `cut`, reason `opaque_near_white_corners` (accepted F1 unchanged).
4. Otherwise → `photo`, reason `non_white_or_mixed_corners`.

The guard uses integer cross-products (`width*100 < height*70` or `width*100 > height*145`), so exactly 0.70 and 1.45 are eligible for `photo`. Aspect reason takes precedence for every outside-band asset, including an already-transparent or already-white cut. Classification is based on the original source dimensions, before fit resizing. No filename, product, game or background exception is added. Source pixels, alpha margins, fit algorithm and CSS geometry are unchanged.

**Counts are 286 archive assets, not active product-ID rows or the handoff's 32 primary samples.** Gallery, logo and historic assets are included.

| Folder | Cut R2 → R3 | Photo R2 → R3 | Total |
|---|---:|---:|---:|
| Pokemon | 66 → 67 | 6 → 5 | 72 |
| One Piece | 55 → 57 | 7 → 5 | 62 |
| 3D print | 0 → 2 | 97 → 95 | 97 |
| Other | 29 → 33 | 9 → 5 | 38 |
| Accsesuary | 4 → 5 | 13 → 12 | 17 |
| **Total** | **154 → 164** | **132 → 122** | **286** |

#### Every class change — 10 `photo` → `cut`

| Asset path | Source dimensions | w/h | New reason |
|---|---:|---:|---|
| `catalog/3D print/Фігурки/Геодуд фігурка 3д.jpg` | 682×1024 | 0.666016 | `aspect_outside_cover_band` |
| `catalog/3D print/Функціональні/stakan-dlia-ruchok-ditto-pokemon-3d-druk.webp` | 1024×1535 | 0.667101 | `aspect_outside_cover_band` |
| `catalog/Accsesuary/albom-dlia-kartok-OnePiece-na-400-slotiv.png` | 1536×1024 | 1.500000 | `aspect_outside_cover_band` |
| `catalog/One Piece/OP-15 Booster.webp` | 209×418 | 0.500000 | `aspect_outside_cover_band` |
| `catalog/One Piece/Бустер One Piece Card Game OP-07 (Японське видання).png` | 745×1500 | 0.496667 | `aspect_outside_cover_band` |
| `catalog/Other/Duel Masters — DM24-RP3 — JP.jpg` | 508×730 | 0.695890 | `aspect_outside_cover_band` |
| `catalog/Other/QCAC-BoosterJP.png` | 928×1930 | 0.480829 | `aspect_outside_cover_band` |
| `catalog/Other/Yu-Gi-Oh OCG Duel Monsters QUARTER CENTURY ART COLLECTION 25th cards.webp` | 1583×449 | 3.525612 | `aspect_outside_cover_band` |
| `catalog/Other/Yu-Gi-Oh! OCG Duel Monsters BEYOND THE BRAVE Booster.webp` | 580×1200 | 0.483333 | `aspect_outside_cover_band` |
| `catalog/Pokemon/Pokémon — Special Card Set Grass Leafeon VSTAR — JP — Collection Set inside.png` | 1200×540 | 2.222222 | `aspect_outside_cover_band` |

There are no `cut` → `photo` changes. All **17 accepted F1 reclassifications** and every previous cut retain `cut`. Two 3D photos now become cuts because their ratio is about 2:3; the owner-approved aspect rule applies to photographs too. The OP-07 mixed-alpha pack and wide Leafeon composition also become cuts. The partially transparent Zoro photo remains photo within the band. All four Mystery Box JPGs remain photo; Outlet MIX remains cut.

**81 additional assets retain their class but change only the reason** to `aspect_outside_cover_band`; they were already cuts. [Exact class changes, ratios and corners](RD-25-WP1_media-fit-catalog_evidence_20261009/reclassified-assets.json), [reason-only changes](RD-25-WP1_media-fit-catalog_evidence_20261009/reason-only-changes.json), [all current metadata](RD-25-WP1_media-fit-catalog_evidence_20261009/classification.json), [reviewed R2 baseline](RD-25-WP1_media-fit-catalog_evidence_20261009/round2/classification.json). The original raw-alpha tolerance remains unchanged; [raw-alpha audit](RD-25-WP1_media-fit-catalog_evidence_20261009/alpha-edge-audit.json). Extracted fixture mtimes are not production timestamps.

#### Four review-named primary cases and source attribution

- `OP-15 Booster.webp` (209×418, ratio 0.5), `QCAC-BoosterJP.png` (928×1930, ratio 0.480829), and `BEYOND THE BRAVE Booster.webp` (580×1200, ratio 0.483333) change from photo to aspect cut. Local real-source UI checks show their complete source rectangles.
- The supplied `Duel Masters — DM24-RP3 — JP — Booster.png` is **1100×1100 with transparent corners and was already cut in R2**; it stays cut. The supplied `Duel Masters — DM24-RP3 — JP.jpg` (508×730, ratio 0.695890) newly becomes an aspect cut. The separate `... JP card.jpg` (500×700, ratio 0.714286) remains photo, as the approved band requires.

The review's attribution of a narrow DM24-RP3 live primary is reviewer evidence; this archive does not establish that live product's exact image mapping. No claim is made that the square transparent Booster source itself was reclassified. The generic guard covers any non-white opaque narrow primary supplied by production. Owner QA must verify all four named live tiles after deployment.

## Per-caller original image / getProduct cost — F2 / F6

| Caller | Current source evidence | `image` passed? | Extra `getProduct()` calls introduced by WP1 |
|---|---|---|---|
| category | `catalog/controller/product/category.php:287`; product_data array union + $result; no image override on left | Yes, original row field | **0** for N cards with a nonempty image; **M** for M distinct absent/empty-image product IDs |
| search | `catalog/controller/product/search.php:219`; product_data array union + $result; no image override on left | Yes, original row field | **0** for N cards with a nonempty image; **M** for M distinct absent/empty-image product IDs |
| special | `catalog/controller/product/special.php:127`; product_data array union + $result; no image override on left | Yes, original row field | **0** for N cards with a nonempty image; **M** for M distinct absent/empty-image product IDs |
| manufacturer_info | `catalog/controller/product/manufacturer.php:200`; product_data array union + $result; no image override on left | Yes, original row field | **0** for N cards with a nonempty image; **M** for M distinct absent/empty-image product IDs |
| featured | `extension/opencart/catalog/controller/module/featured.php` absent from both supplied archives | **Unverified; no stock-code assumption** | Actual current case unknown. If image supplied: 0; if absent/empty: N distinct positive product IDs → N extra calls (24 cards → 24) |
| related | `catalog/controller/product/related.php:60`; product_data array union + $result; no image override on left | Yes, original row field | **0** for N cards with a nonempty image; **M** for M distinct absent/empty-image product IDs |

`+ $result` does not replace the original image with the resized `thumb`; the left array does not define `image`. The manufacturer-info route is served by `catalog/controller/product/manufacturer.php::info`, not a separate manufacturer_info controller. Related has the same row union.

The source-less fallback is cached once per positive product ID within the request, across repeated cards/modules. Empty-string `image` also triggers it; a nonempty but missing filesystem path does not trigger `getProduct()` (fit falls back to the old thumb). Therefore a normal 24-card listing with valid original images adds zero calls; a 24-card listing of distinct source-less products adds exactly 24 calls. These are **getProduct API-call counts**, not asserted individual SQL-query counts. Local probes verified the 0/24 distinction and zero further calls on repeats.

[Per-caller SHA256, line references and exact construction excerpts](RD-25-WP1_media-fit-catalog_evidence_20261009/caller-image-audit.json). **F6 is accepted as open, non-blocking by the owner.** The featured controller is absent from both current archives; confirm its payload when that file is available. The featured Twig alone cannot establish its PHP payload. Worst case is one extra `getProduct()` per distinct featured card, cached per request.

## Product grid inventory

Every `row-cols-1` template with a product loop in the **supplied current template set**:

| Template | WP1 treatment |
|---|---|
| `catalog/view/template/product/category.twig` | two mobile columns; data hook survives class rewrite; first image retains eager/high priority |
| `catalog/view/template/product/search.twig` | two mobile columns |
| `catalog/view/template/product/special.twig` | two mobile columns |
| `catalog/view/template/product/manufacturer_info.twig` | two mobile columns |
| `extension/opencart/catalog/view/template/module/featured.twig` | horizontal axis: two mobile columns; vertical axis unchanged |
| `catalog/view/template/product/related.twig` | existing mobile swipe rail retained; cards receive fit/framing |

The followup contains only featured among extension module templates. This inventory does not claim an audit of any other unprovided/active extension template. [Inventory](RD-25-WP1_media-fit-catalog_evidence_20261009/grid-inventory.json).

CSS implements transparent 1:1 media ≥768px / 4:5 below, cut 8%/6%/84%/94% bottom contain, photo full cover and 10px radius. Actual mobile grids below 576px have 24px row / 14px column gap. Titles 13px/600/1.35 with two-line clamp and minimum 35px; prices 15px/800; purchase buttons 44px/15px/700. Source component media queries are adjacent to the edited rules. No new `!important`, delayed JS, end-of-file override layer, backdrop, shadow or card container was added. New geometry and the 8px mobile CTA padding are documented. [Source audit](RD-25-WP1_media-fit-catalog_evidence_20261009/source-audit.json).

## Protected URLs and behavior

**JSON-LD `image` and 700×700 lightbox URL construction are unchanged.** `product.php` still calls the original `resize()` with popup settings; `product.twig` still uses `popup` / `image.popup` in both lightbox anchors and JSON-LD. Product-page files are outside the target set. The existing image library/model code is byte-identical after removing the new method blocks. The fifteen existing resize expressions in nine supplied callers are byte-identical.

[Protected media evidence](RD-25-WP1_media-fit-catalog_evidence_20261009/protected-media.json) records file/block hashes and original expressions; [protected-media.diff](RD-25-WP1_media-fit-catalog_evidence_20261009/protected-media.diff) is intentionally empty. This is source/composition proof, not a post-deploy capture of real production URLs.

All targeted Twig inline scripts, runner-9 fetch/history code and the complete thumb add-to-cart form are byte-identical. Product ID, quantity, actions, AJAX hooks and existing GA4 attributes remain intact. The model introduces no DB writes. Related scrolling, thumbnail-gallery switching, cart/payment/shipping logic and Merchant/email/OG resize paths were not rewritten.

## Local verification

Runtime: **PHP 8.3.30 + GD**, Chrome headless and local Twig parser. PHP code uses PHP 8.0-compatible syntax/APIs; native PHP 8.0 was not available locally. The runner performs candidate `php -l` with the hosting PHP **before writes**, then lint/readback after writes, and restores on failure.

| Evidence | Result |
|---|---|
| [Runner checks](RD-25-WP1_media-fit-catalog_evidence_20261009/runner-checks.json) | **26/26**: full preflight, no-write dry run, eleven exact outputs, backups before writes, self-delete, repeat, dynamic cache token, source drift refusal, partial marker refusal, exact rollback, newer-source rollback refusal, forced second-write failure with complete restoration |
| [Functional checks](RD-25-WP1_media-fit-catalog_evidence_20261009/functional-checks.json) | **79/79**: PNG/JPEG/WebP, corner alpha, non-padding dimensions, alpha preservation, no enlargement, zero decodes on 20 warm views, same-stem isolation, stat invalidation, corrupt metadata recovery, legacy cache unchanged, native-format fallback, thumb source and third-party lookup, placeholder and read-only controller queries |
| [Twig fixture](RD-25-WP1_media-fit-catalog_evidence_20261009/twig-fixture.json) | seven complete target templates compile; seven isolated layout pages rendered from real thumb/template grid markup |
| [UI checks](RD-25-WP1_media-fit-catalog_evidence_20261009/ui-checks.json) | **129/129** across five ordinary grid routes × 1440/1024/390/360px; framing, typography, gaps, badges, no overflow/broken images, Manrope loaded, class rewrite + append, hover/focus/active, preserved submit payload; vertical featured and related rail retained |
| [F1 white-pack UI](RD-25-WP1_media-fit-catalog_evidence_20261009/white-pack-ui-checks.json) | **68/68**: all 17 accepted F1 assets × 1440/1024/390/360; every image uses cut/contain and fits its complete source rectangle |
| [F5 aspect UI](RD-25-WP1_media-fit-catalog_evidence_20261009/aspect-pack-ui-checks.json) | **40/40**: all 10 R3 class changes × 1440/1024/390/360; complete source rectangles, contain + bottom |
| [Four review-named source fixtures](RD-25-WP1_media-fit-catalog_evidence_20261009/review-primary-pack-ui-checks.json) | **16/16**: OP-15, archive DM24-RP3 Booster PNG, Beyond the Brave and QCAC × four widths; all contained |

Runner syntax:

```text
No syntax errors detected in patches/RD-25-WP1_media-fit-catalog_20261009.php
```

Dry run:

```text
preflight_file=<each of 11 targets>
dry_run=yes
target_writes=0
done=ok
```

Repeat: `already_applied=yes`, no content or token change, self-delete. Logs: [apply](RD-25-WP1_media-fit-catalog_evidence_20261009/apply.txt), [dry run](RD-25-WP1_media-fit-catalog_evidence_20261009/dry-run.txt), [repeat](RD-25-WP1_media-fit-catalog_evidence_20261009/repeat.txt), [rollback](RD-25-WP1_media-fit-catalog_evidence_20261009/rollback.txt), [forced failure restoration](RD-25-WP1_media-fit-catalog_evidence_20261009/failure-restoration.txt).

Screenshots were visually inspected: [1440](RD-25-WP1_media-fit-catalog_evidence_20261009/category-1440.png), [1024](RD-25-WP1_media-fit-catalog_evidence_20261009/category-1024.png), [390](RD-25-WP1_media-fit-catalog_evidence_20261009/category-390.png), [360](RD-25-WP1_media-fit-catalog_evidence_20261009/category-360.png). Names/prices/badge combinations are **synthetic stress fixtures**, not an export of production product state.

### Verification limits and review points

1. **F3 accepted by owner / Claude review:** source-alpha margins remain untouched. The OP-16 visible object can be approximately 89.4% tall rather than the handoff's approximate 90%; this is accepted, not a pending trim request. CSS stays the prescribed 94% image box, bottom aligned. [Source-margin observation](RD-25-WP1_media-fit-catalog_evidence_20261009/source-margin-observations.json).
2. **F1 retained / F5 corrected:** all 17 white-background corrections remain cuts; all 10 additional outside-band assets become cuts. Source classification and UI proof use current supplied archives. Exact live DM24-RP3 primary mapping is unverified in these archives; see the source-attribution note above. F6 remains open, non-blocking.
3. Existing `stylesheet.css` globally resets outlines with `!important`. Local purchase focus remains observable through the preserved green state and card lift; title focus keeps its 2px ring. A new button outline was not silently added. Keyboard focus must be reviewed on deployed pages.
4. Bootstrap, `stylesheet.css` and typography CSS for the layout fixture came from the October 4 local dependency snapshot; they are not patch inputs. Production Manrope WOFF2 fetching was reset by the remote endpoint; local checks used the same **Manrope v20** family/weights from Google TTF files. Deployed-font byte identity was not proven. [Dependency provenance](RD-25-WP1_media-fit-catalog_evidence_20261009/ui-dependencies.json).
5. The browser used file-backed local HTTP responses and DOM append/class-rewrite simulations. It did not submit a production cart, run live AJAX/GA4, validate DB theme overrides, filter navigation or owner deployment. Runner-9/GA4/cart preservation is supported by unchanged source and local submit capture, not external integration proof.
6. Cold listing requests can create a new fit image **and** the legacy thumbnail because all existing caller `resize()` expressions remain intact. Warm visits do not decode again. Small originals remain limited by their native pixels. Source replacements with identical mtime and size retain their cache key.

## Rollback and owner run command

After independent review, upload only the patch to `~/public_html`. The patch creates `_patch_backups/RD-25-WP1_media-fit-catalog_20261009-<UTC>-<random>/`, verifies originals and candidate syntax, writes atomically, verifies outputs and self-deletes. The receipt records actual before/after hashes. A failed write restores only successfully written targets, then lint/readback verifies restoration.

```bash
cd ~/public_html
php RD-25-WP1_media-fit-catalog_20261009.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Rollback: re-upload the same runner, then use the **exact backup directory printed by that deployment**, replacing the placeholder below. It refuses newer content instead of overwriting another patch. Roll back WP2/WP3 first if deployed. New fit images/metadata can remain unused; rollback deletes neither originals nor old caches.

```bash
cd ~/public_html
php RD-25-WP1_media-fit-catalog_20261009.php --rollback=_patch_backups/<exact-directory-printed-by-the-patch> && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy owner QA — still pending

- [ ] Independent re-review of F5/classification v3, inclusive boundaries, every 10 class changes, accepted F1 regressions and re-pinned hashes. F3 stays accepted; F6 stays open, non-blocking.
- [ ] At 1440/1024/390/360: **OP-15 Booster, Duel Masters DM24-RP3 Booster, Beyond the Brave Booster and QCAC Booster** show the whole pack; confirm **OP-05 Rare Pack, Blazing Dominion, MS1, White Flare and Pitch Black blister** remain uncropped; Pokémon, One Piece and 3D-print categories; long names, sale/preorder/rare/out/no-photo states; photo crop and badge readability; physical booster size/shelf, horizontal overflow and keyboard focus.
- [ ] Search, specials, manufacturer, horizontal featured; confirm any other active home module. Vertical widgets and related swipe rail retain their intended layout.
- [ ] Compare real JSON-LD image and every lightbox href against before-deploy values; both keep the original 700×700 path. Product gallery/mini-cart/cart still use their previous WP2/WP3 geometry.
- [ ] “Показати ще”, filters, sorting, back/forward and no-JS fallback; no new console/server errors.
- [ ] Add to cart from a listing, toast and cart/mini-cart; no real order needed. Second visit has normal cache behavior.
- [ ] Baseline site smoke routes from AGENTS.md, including checkout entry without placing an order. If markup appears stale after clearing, inspect current theme override before editing more Twig.

**WP2 and WP3 remain blocked on the owner's WP1 deployment and QA. No statuses or “approved/not live” design decisions were changed.**

## Revision 3 evidence and hash pinning

Reviewed R1 and R2 patch/report, manifests, targets, classification and check results remain in `RD-25-WP1_media-fit-catalog_evidence_20261009/round1/` and `RD-25-WP1_media-fit-catalog_evidence_20261009/round2/`. All eleven original before-hashes still bind the same October 9 inputs. Candidate after-hashes and the runner SHA256 were regenerated in [targets](RD-25-WP1_media-fit-catalog_evidence_20261009/targets.json) and [delivery manifest](RD-25-WP1_media-fit-catalog_evidence_20261009/delivery-manifest.json). Header token is `rd25-wp1-media-fit-r3-20261010`; metadata version is 3. The fit cache key remains `rd25-fit-v1` because fit pixels are unchanged.

This replaces the pre-deployment artifact. Fixtures verify that R3 refuses already-installed R1 or R2 content without target/token writes; it is not an in-place upgrade runner for a previously deployed revision. Runner checks additionally prove safe apply, repeat, rollback and restoration after forced write failure. Functional checks cover inclusive 0.70/1.45 boundaries, just-outside 0.699/1.451, PNG/JPEG/WebP in both outside directions, aspect-reason precedence for white/transparent corners, stale-v2 metadata rejection, warm-cache behavior and every accepted F1 case.

Local UI evidence: **129 general + 68 F1 + 40 F5 + 16 review-named source fixtures = 253 passed checks** at 1440/1024/390/360. F5 screenshot inspection shows full narrow packs, the whole wide card strip and Leafeon arrangement, and uncut tall Geodude/Ditto photos. The separate four-source UI fixture also checks the unchanged square DM Booster PNG; this is archive-source rendering proof, not a live product-image mapping. All page prices/names are synthetic fixture data.

F5 screenshots: [1440](RD-25-WP1_media-fit-catalog_evidence_20261009/aspect-packs-1440.png), [1024](RD-25-WP1_media-fit-catalog_evidence_20261009/aspect-packs-1024.png), [390](RD-25-WP1_media-fit-catalog_evidence_20261009/aspect-packs-390.png), [360](RD-25-WP1_media-fit-catalog_evidence_20261009/aspect-packs-360.png).
Accepted F1 regression screenshots: [1440](RD-25-WP1_media-fit-catalog_evidence_20261009/white-packs-1440.png), [1024](RD-25-WP1_media-fit-catalog_evidence_20261009/white-packs-1024.png), [390](RD-25-WP1_media-fit-catalog_evidence_20261009/white-packs-390.png), [360](RD-25-WP1_media-fit-catalog_evidence_20261009/white-packs-360.png).
Four review-named source screenshots: [1440](RD-25-WP1_media-fit-catalog_evidence_20261009/review-primary-packs-1440.png), [1024](RD-25-WP1_media-fit-catalog_evidence_20261009/review-primary-packs-1024.png), [390](RD-25-WP1_media-fit-catalog_evidence_20261009/review-primary-packs-390.png), [360](RD-25-WP1_media-fit-catalog_evidence_20261009/review-primary-packs-360.png).
