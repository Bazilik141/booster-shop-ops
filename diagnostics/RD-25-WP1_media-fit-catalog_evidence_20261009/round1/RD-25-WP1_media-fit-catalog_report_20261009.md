# Codex Report — RD-25 WP1: media fit and catalog grids

Date: 2026-10-09
Status: **Patch prepared for independent review. Not deployed; owner QA remains open.**

## Scope and authority

Owner go: “роби, не зупиняйся на кожному кроці. Чекаю патч на рев'ю.” WP1 only. No commit, push, deploy, roadmap/status changes, WP2 or WP3.

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

Main patch: `patches/RD-25-WP1_media-fit-catalog_20261009.php` (31,133 bytes).

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
- Classification: `DIR_CACHE/rd25-media-v1-<sha256(canonical-source-path)>.json`, invalidated on version, source mtime or size changes. Warm requests use file metadata and cached JSON, with zero GD decodes. Locks and temporary files prevent competing first requests from publishing partial files; generated public images are chmod 0644.
- Unavailable storage, invalid/outside source, unsupported formats and missing images retain the original thumbnail/placeholder. Blank or placeholder thumbs are not newly decoded.

### Cut / photo rule and counts

PNG/WebP with **all four corner alpha values ≤16/255** → `cut`; everything else → `photo`. Here 0 is transparent, 255 opaque. The small tolerance handles nearly transparent edge pixels in the actual archive. JPEG is always `photo`. GD's 7-bit alpha is normalized to this scale.

**These are 286 archive assets in product-direction folders, not 286 active products and not the handoff's 32 sampled primary listing images.** Gallery images, logos and old assets are included. There is no product-ID → primary-image export in these inputs, so exact active-product counts/misclassification attribution cannot be asserted.

| Folder | Cut | Photo | Total |
|---|---:|---:|---:|
| Pokemon | 60 | 12 | 72 |
| One Piece | 48 | 14 | 62 |
| 3D print | 0 | 97 | 97 |
| Other | 26 | 12 | 38 |
| Accsesuary | 3 | 14 | 17 |
| **Total** | **137** | **149** | **286** |

[Complete per-file classification](RD-25-WP1_media-fit-catalog_evidence_20261009/classification.json). Its mtime fields describe the extracted local fixture, not independently verified production timestamps. Raw 8-bit inspection finds 16 near-transparent cuts; GD quantizes 14 of them to zero and leaves two with nonzero corner alpha. Strict raw alpha=0 would yield 121/165; strict GD alpha=0 yields 135/151. The implemented tolerance yields the same 137/149 with both decoders. [Edge audit](RD-25-WP1_media-fit-catalog_evidence_20261009/alpha-edge-audit.json) lists every one of the 16 paths and exact corner values.

### Suspected classifications / crop-sensitive assets

The four actual Mystery Box JPGs (Pokémon Standard/XL, One Piece Standard/XL) are `photo`, as intended. `Pokemon/Outlet MIX.png` is a transparent composition and becomes `cut`; it is not assumed to be one of those opaque box photos.

Opaque white-background product artwork becomes `photo` by the proposed rule. **Cover can crop packaging or preserve white already inside the original**, so these 13 product-looking assets need primary-image mapping and visual owner QA:
- `catalog/Accsesuary/albom-dlia-kartok-pokemon-na-400-slotiv.png`
- `catalog/One Piece/One Piece OP-05 Awakening of the New Era Booster (Японське видання).jpg`
- `catalog/One Piece/One Piece OP-05 Awakening of the New Era display (Японське видання).jpg`
- `catalog/One Piece/One-Piece-Boosters-EB-02 card.jpg`
- `catalog/One Piece/OP JP V7-PROMO.png`
- `catalog/Other/35pt-180pt-magnitni-ramki-dlya-kartok_6l74fhlsgq9cbn63n.jpg`
- `catalog/Other/Yu-Gi-Oh! OCG Blazing Dominion Booster.webp`
- `catalog/Pokemon/MS1.webp`
- `catalog/Pokemon/MS2.jpg`
- `catalog/Pokemon/MSB1.jpg`
- `catalog/Pokemon/Pokemon Mega Dream Ex.webp`
- `catalog/Pokemon/Pokemon TCG Mega Evolution 05 - Pitch Black - Checklane Blister.webp`
- `catalog/Pokemon/Scarlet_Violet_Expansion_Pack_White_Flare.png`

Six additional opaque portrait pack/artwork assets are crop-sensitive:
- `catalog/One Piece/OP-15 Booster.webp`
- `catalog/Other/Duel Masters — DM24-RP3 — JP card.jpg`
- `catalog/Other/Duel Masters — DM24-RP3 — JP.jpg`
- `catalog/Other/G7D sleeves premium plus (MTG).webp`
- `catalog/Other/QCAC-BoosterJP.png`
- `catalog/Other/Yu-Gi-Oh! OCG Duel Monsters BEYOND THE BRAVE Booster.webp`

Mixed-alpha candidates:

- `One Piece/Бустер One Piece Card Game OP-07 (Японське видання).png`: GD corners 149/38/2/0, therefore `photo`. Full pack artwork may be cropped by cover.
- `Pokemon/Pokémon — Special Card Set Grass Leafeon VSTAR — JP — Collection Set inside.png`: 0/0/253/0, therefore `photo`. Looks like a gallery composition; primary use is unknown.
- `3D print/Фігурки/kartyna-zoro-one-piece-3d-druk1.webp`: 255/126/255/126, remains `photo`; visual inspection shows an actual photo on a light background. Partial alpha alone is not a reason to call it a cutout.

No inferred per-product overrides were introduced. [All candidates and Mystery Box paths](RD-25-WP1_media-fit-catalog_evidence_20261009/classification-candidates.json). The 149 photo assets were visually inspected in four contact sheets; [photo audit index](RD-25-WP1_media-fit-catalog_evidence_20261009/photo-audit-index.json) records IDs, source paths, corner colors and white-background detection. These are conservative **asset review candidates**, not confirmed wrong live product rows.

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
| [Runner checks](RD-25-WP1_media-fit-catalog_evidence_20261009/runner-checks.json) | **22/22**: full preflight, no-write dry run, eleven exact outputs, backups before writes, self-delete, repeat, dynamic cache token, source drift refusal, partial marker refusal, exact rollback, newer-source rollback refusal, forced second-write failure with complete restoration |
| [Functional checks](RD-25-WP1_media-fit-catalog_evidence_20261009/functional-checks.json) | **25/25**: PNG/JPEG/WebP, corner alpha, non-padding dimensions, alpha preservation, no enlargement, zero decodes on 20 warm views, same-stem isolation, stat invalidation, corrupt metadata recovery, legacy cache unchanged, native-format fallback, thumb source and third-party lookup, placeholder and read-only controller queries |
| [Twig fixture](RD-25-WP1_media-fit-catalog_evidence_20261009/twig-fixture.json) | seven complete target templates compile; seven isolated layout pages rendered from real thumb/template grid markup |
| [UI checks](RD-25-WP1_media-fit-catalog_evidence_20261009/ui-checks.json) | **129/129** across five ordinary grid routes × 1440/1024/390/360px; framing, typography, gaps, badges, no overflow/broken images, Manrope loaded, class rewrite + append, hover/focus/active, preserved submit payload; vertical featured and related rail retained |

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

1. Existing transparent margins **inside source assets** are retained; only resizer-added canvas padding is removed. The prescribed CSS image box reaches the bottom and is 94% tall, but that does not guarantee the visible object is ≥90% or reaches the shelf. Example OP-16 source has a visible alpha>16 box 951/1000 high: approximately **89.4% visible slot height and 2.35% slot bottom gap** when height constrained. **That strict visible-content acceptance point remains open**, rather than being claimed passed. Source-alpha trimming or approved exceptions require a reviewed scope decision. [Measured source margins](RD-25-WP1_media-fit-catalog_evidence_20261009/source-margin-observations.json).
2. The proposed alpha rule cannot distinguish a white-background cutout from a photograph; 19 opaque assets plus two mixed-alpha compositions are explicitly flagged above. Actual affected primary products cannot be identified without a product-image mapping. No classification exception is guessed.
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

- [ ] Independent review of source diff, classification/crop candidates and source-margin acceptance point before deployment.
- [ ] At 1440/1024/390/360: Pokémon, One Piece and 3D-print categories; long names, sale/preorder/rare/out/no-photo states; photo crop and badge readability; physical booster size/shelf, horizontal overflow and keyboard focus.
- [ ] Search, specials, manufacturer, horizontal featured; confirm any other active home module. Vertical widgets and related swipe rail retain their intended layout.
- [ ] Compare real JSON-LD image and every lightbox href against before-deploy values; both keep the original 700×700 path. Product gallery/mini-cart/cart still use their previous WP2/WP3 geometry.
- [ ] “Показати ще”, filters, sorting, back/forward and no-JS fallback; no new console/server errors.
- [ ] Add to cart from a listing, toast and cart/mini-cart; no real order needed. Second visit has normal cache behavior.
- [ ] Baseline site smoke routes from AGENTS.md, including checkout entry without placing an order. If markup appears stale after clearing, inspect current theme override before editing more Twig.

**WP2 and WP3 remain blocked on the owner's WP1 deployment and QA. No statuses or “approved/not live” design decisions were changed.**
