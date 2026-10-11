# Codex Report — RD-25 WP1: media fit and catalog grids

Date: 2026-10-10 · Revision 2 (same pre-deployment patch filename)
Status: **F1 corrected; revision prepared for re-review. Not deployed; owner QA remains open.** F2: current featured-controller evidence is still missing; see the caller table.

## Scope and authority

Owner authorization: RD-25 WP1 returned for changes on 2026-10-10: fix F1, document F2 per caller, accept F3 without source-alpha trimming, re-pin hashes and rerun runner checks. WP1 only; no commit, push, deploy, roadmap/status changes, WP2 or WP3. Review: `diagnostics/RD-25-WP1_media-fit-catalog_claude-review_20261010.md`.

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

Main patch: `patches/RD-25-WP1_media-fit-catalog_20261009.php` (31,945 bytes).

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
- Classification: `DIR_CACHE/rd25-media-v2-<sha256(canonical-source-path)>.json`, invalidated on version, source mtime or size changes. `RD25_MEDIA_VERSION` is **2**; old v1 metadata cannot supply the previous class. The fit-image algorithm/key remains v1 because classification does not change fit pixels. Warm requests use file metadata and cached JSON, with zero GD decodes. Locks and temporary files prevent competing first requests from publishing partial files; generated public images are chmod 0644.
- Unavailable storage, invalid/outside source, unsupported formats and missing images retain the original thumbnail/placeholder. Blank or placeholder thumbs are not newly decoded.

### Cut / photo rule and counts — F1 corrected

1. PNG/WebP with all four normalized corner alpha values ≤16/255 → `cut` (existing transparent rule).
2. Otherwise, all four corners fully opaque (alpha 255) **and every R/G/B channel ≥240** → `cut`.
3. Otherwise → `photo`.

The near-white threshold is the executor's original `all_near_white_corners` audit test, explicitly requested by the owner. No new filename/product/game exception was guessed. Opaque near-white JPEG, PNG and WebP are all covered. The new metadata records each corner RGB, opacity, white-corner result and classification reason. Dark and other non-white-corner photos remain `photo`.

**Counts are 286 archive assets, not active product-ID rows or the handoff's 32 primary listing samples.** Source bytes are unchanged; gallery/logo/old assets are included.

| Folder | Cut R1 → R2 | Photo R1 → R2 | Total |
|---|---:|---:|---:|
| Pokemon | 60 → 66 | 12 → 6 | 72 |
| One Piece | 48 → 55 | 14 → 7 | 62 |
| 3D print | 0 → 0 | 97 → 97 | 97 |
| Other | 26 → 29 | 12 → 9 | 38 |
| Accsesuary | 3 → 4 | 14 → 13 | 17 |
| **Total** | **137 → 154** | **149 → 132** | **286** |

#### Every reclassified asset — 17 `photo` → `cut`

- `catalog/Accsesuary/albom-dlia-kartok-pokemon-na-400-slotiv.png`
- `catalog/One Piece/Bandai.jpg`
- `catalog/One Piece/BS Big logo.png`
- `catalog/One Piece/BS Big logo1.jpg`
- `catalog/One Piece/One Piece OP-05 Awakening of the New Era Booster (Японське видання).jpg`
- `catalog/One Piece/One Piece OP-05 Awakening of the New Era display (Японське видання).jpg`
- `catalog/One Piece/One-Piece-Boosters-EB-02 card.jpg`
- `catalog/One Piece/OP JP V7-PROMO.png`
- `catalog/Other/35pt-180pt-magnitni-ramki-dlya-kartok_6l74fhlsgq9cbn63n.jpg`
- `catalog/Other/G7D.jpg`
- `catalog/Other/Yu-Gi-Oh! OCG Blazing Dominion Booster.webp`
- `catalog/Pokemon/MS1.webp`
- `catalog/Pokemon/MS2.jpg`
- `catalog/Pokemon/MSB1.jpg`
- `catalog/Pokemon/Pokemon Mega Dream Ex.webp`
- `catalog/Pokemon/Pokemon TCG Mega Evolution 05 - Pitch Black - Checklane Blister.webp`
- `catalog/Pokemon/Scarlet_Violet_Expansion_Pack_White_Flare.png`

All seventeen meet the four-opaque-corner RGB test. Logos are included because this is an asset classifier, not a product-ID exception list. OP-05 Booster, Blazing Dominion, MS1, White Flare and Pitch Black blister now use `contain` and preserve the entire source rectangle. Their white source background is retained, not erased or trimmed. The five previously cited sources no longer lose roughly half their source height in a square cover slot.

[Exact R1/R2 class comparison and RGB values](RD-25-WP1_media-fit-catalog_evidence_20261009/reclassified-assets.json); [current complete classification](RD-25-WP1_media-fit-catalog_evidence_20261009/classification.json); [reviewed R1 baseline](RD-25-WP1_media-fit-catalog_evidence_20261009/round1/classification.json). Metadata mtime fields describe extracted local files, not verified production timestamps.

All 137 original transparent cuts retain their class. Sixteen have near-transparent raw 8-bit edges; the original ≤16 alpha tolerance is retained. [Original raw-alpha audit](RD-25-WP1_media-fit-catalog_evidence_20261009/alpha-edge-audit.json).

#### Unchanged non-white photos / remaining crop-sensitive artwork

All **97** 3D-print photos remain photo. The four Mystery Box JPGs (Pokémon Standard/XL, One Piece Standard/XL) remain photo; `Pokemon/Outlet MIX.png` remains a transparent cut. The OP-07 mixed-alpha pack (GD 149/38/2/0), Leafeon inside composition (0/0/253/0), and partially transparent 3D Zoro photograph (255/126/255/126) remain photo because their corners fail both cut conditions.

Opaque non-white pack artwork such as OP-15 Booster, Duel Masters card/pack art, G7D sleeves, QCAC Booster and Beyond the Brave retains `photo` **per the owner's explicit instruction**. No automatic exception or source edit is introduced. The original R1 crop-sensitive list is preserved under [round1 photo audit](RD-25-WP1_media-fit-catalog_evidence_20261009/round1/photo-audit-index.json). These files still merit crop QA if used as primary images, but F1 only authorizes the white-corner change.

## Per-caller original image / getProduct cost — F2

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

[Per-caller SHA256, line references and exact construction excerpts](RD-25-WP1_media-fit-catalog_evidence_20261009/caller-image-audit.json). The featured controller is the only remaining source gap; a one-file current export was requested asynchronously while F1 and all available checks continued. The featured Twig file alone cannot establish its PHP caller payload.

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
| [Runner checks](RD-25-WP1_media-fit-catalog_evidence_20261009/runner-checks.json) | **24/24**: full preflight, no-write dry run, eleven exact outputs, backups before writes, self-delete, repeat, dynamic cache token, source drift refusal, partial marker refusal, exact rollback, newer-source rollback refusal, forced second-write failure with complete restoration |
| [Functional checks](RD-25-WP1_media-fit-catalog_evidence_20261009/functional-checks.json) | **36/36**: PNG/JPEG/WebP, corner alpha, non-padding dimensions, alpha preservation, no enlargement, zero decodes on 20 warm views, same-stem isolation, stat invalidation, corrupt metadata recovery, legacy cache unchanged, native-format fallback, thumb source and third-party lookup, placeholder and read-only controller queries |
| [Twig fixture](RD-25-WP1_media-fit-catalog_evidence_20261009/twig-fixture.json) | seven complete target templates compile; seven isolated layout pages rendered from real thumb/template grid markup |
| [UI checks](RD-25-WP1_media-fit-catalog_evidence_20261009/ui-checks.json) | **129/129** across five ordinary grid routes × 1440/1024/390/360px; framing, typography, gaps, badges, no overflow/broken images, Manrope loaded, class rewrite + append, hover/focus/active, preserved submit payload; vertical featured and related rail retained |
| [F1 white-pack UI](RD-25-WP1_media-fit-catalog_evidence_20261009/white-pack-ui-checks.json) | **68/68**: all 17 reclassified assets × 1440/1024/390/360; every image uses cut/contain and fits its complete source rectangle |

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
2. **F1 resolved:** the 17 original near-white photo assets now contain their full rectangle. Non-white/mixed-corner photos retain photo per the owner's instruction. Primary product-ID attribution remains bounded by the supplied assets; the review specifically confirms OP-05 Rare Pack and Blazing Dominion as live primary cases. Featured PHP payload remains unverified until its missing current file is supplied.
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

- [ ] Independent re-review of F1/classification v2, the 17 reclassified paths, re-pinned hashes and caller evidence. F3 is accepted without trimming.
- [ ] At 1440/1024/390/360: **OP-05 Rare Pack and Blazing Dominion** show complete packs; MS1, White Flare and Pitch Black blister remain uncropped; Pokémon, One Piece and 3D-print categories; long names, sale/preorder/rare/out/no-photo states; photo crop and badge readability; physical booster size/shelf, horizontal overflow and keyboard focus.
- [ ] Search, specials, manufacturer, horizontal featured; confirm any other active home module. Vertical widgets and related swipe rail retain their intended layout.
- [ ] Compare real JSON-LD image and every lightbox href against before-deploy values; both keep the original 700×700 path. Product gallery/mini-cart/cart still use their previous WP2/WP3 geometry.
- [ ] “Показати ще”, filters, sorting, back/forward and no-JS fallback; no new console/server errors.
- [ ] Add to cart from a listing, toast and cart/mini-cart; no real order needed. Second visit has normal cache behavior.
- [ ] Baseline site smoke routes from AGENTS.md, including checkout entry without placing an order. If markup appears stale after clearing, inspect current theme override before editing more Twig.

**WP2 and WP3 remain blocked on the owner's WP1 deployment and QA. No statuses or “approved/not live” design decisions were changed.**

## Revision 2 evidence and hash pinning

Reviewed R1 patch/report, hashes, classification and checks are preserved in `RD-25-WP1_media-fit-catalog_evidence_20261009/round1/`. The eleven original before-hashes remain pinned to the same current October 9 inputs; all candidate after-hashes and the runner checksum were regenerated. The model candidate changed for v2/white corners; header token is now `rd25-wp1-media-fit-r2-20261010`. The image-fit implementation and source assets are unchanged. This revision replaces the reviewed pre-deployment artifact; it does not silently overwrite an already installed different revision.

F1 screenshots: [1440](RD-25-WP1_media-fit-catalog_evidence_20261009/white-packs-1440.png), [1024](RD-25-WP1_media-fit-catalog_evidence_20261009/white-packs-1024.png), [390](RD-25-WP1_media-fit-catalog_evidence_20261009/white-packs-390.png), [360](RD-25-WP1_media-fit-catalog_evidence_20261009/white-packs-360.png).
