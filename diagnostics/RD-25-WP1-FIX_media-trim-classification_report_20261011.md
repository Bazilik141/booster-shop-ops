# RD-25 WP1-FIX — Phase 1 implementation report

Date: 2026-10-11
Status: Ready for independent review; no commit, push or deployment.

## Scope and source gate

One self-contained runner: `patches/RD-25-WP1-FIX_media-trim-classification_20261011.php`. Three PHP targets only. Fresh export is `RD-25-WP1-FIX-check-20261011.tar.gz`, SHA-256 `d90a2ce776b282fd68d5063acce5d24832860fe5b86fe4c16733641ba5c131a1`. Owner confirms photos unchanged since the October 9 followup export. The September 24 backup was not used. Phase 0 accepted measurements and its sealed evidence remain unchanged.

Root causes: old mediaInfo uses original-canvas corners and the v3 aspect rule; resizeFit keeps empty source margins; Thumb derives emitted dimensions from untrimmed dimensions. Existing crop flattens alpha. The new cropFit helper preserves alpha and does not change resize(), crop() or legacy resizeFit().

| Target | Before SHA-256 | After SHA-256 |
|---|---|---|
| `catalog/model/tool/image.php` | `9d6aaec56eaa44be072e67957e43b7365ca8f115283988d0ab7a3227db885285` | `ea17a200ecd251954ccf907586fad234ee10e796a8b642abd394339b3bb8ace3` |
| `system/library/image.php` | `b032345fa128dc867a55dbe4bf49f4070a34a2efa952525a69006fdb706875d9` | `7f81dcbb8b5d2d032005ae28ce01cea5ba7542f7334fd9909e538f6952adeff9` |
| `catalog/controller/product/thumb.php` | `47e56e45e134359074aecdd3a1a50a068e8b19766b40589cb8688e077df5d2eb` | `cf4f8e354b18a4318af159c34bf3c083d0991007b42d03e2c57aa4963c1ed3b1` |

Patch SHA-256: `894c0e75e7a1bcea13f8ff5d59fe849d1de47576996931d2daf848fd0ae4297a`; 22024 bytes. Readable transformations: [source.diff](RD-25-WP1-FIX_media-trim-classification_evidence_20261011/source.diff).

## Review conditions

### 1 — asset #222 usage

Owner hosting lookup, 2026-10-11T06:39:54+00:00: all 12 allowlisted public fields checked; zero matches in product.image and product_image.image and zero matches in category, manufacturer, banner, option_value, setting, module, theme and public descriptions. It has no current reference in these checked fields; there is no evidence that it is a listing/product image, so the stop condition is not met. It appears to be an unused uploaded asset. This is not an exhaustive search of arbitrary unexported custom code. [Owner evidence](RD-25-WP1-FIX_media-trim-classification_evidence_20261011/asset222-owner-lookup.json). The MYSQL_OPT_RECONNECT driver deprecation printed before JSON is recorded separately; it does not indicate an image-cache warning. Zero DB/file writes.

### 2 — exact Phase 0 rules and PHP/GD decisions

Opacity >16; minimum 2 content pixels per row/column; 2 source-pixel pad; page-white all RGB >=250 with opacity255; maximum corner span <=8; channel-wise median background (including fractional .5); max-channel distance >12; inclusive .70..1.45 cover band; uniform fully opaque non-white exception only if expanded strict content bbox fits both slot cover windows inset 2% per axis. Mixed white/transparent corners classify cut. For cut: alpha trim if any opacity<=16; otherwise only eligible uniform background-distance trim; mixed nonuniform corners retain full canvas. If filtered+pad excludes any above-threshold pixel, expand to strict bbox (#233). Original pixels are never rewritten.

**#097 Album 400 yellow:** GD background median [241,241.5,241.5], span4, strict distance bbox [378,90,1140,952], padded [376,88,1142,954]. Mobile safe window [374.784,20.48,1161.216,1003.52]: left clearance **1.216 px**. Desktop and mobile pass; `photo`, `uniform_non_white_safe_cover_both_slots`; full canvas1536x1024, fit700x466.

**#186 G7D sleeves:** GD corners [255,255,255,255], [255,255,255,255], [36,184,111,255], [232,235,232,255], span219. Mixed page-white corners -> `cut`, `mixed_page_white_or_transparent_corners`; trim none, bbox [0,0,362,500], fit362x500. Exact GD metadata/bboxes for both are saved as gd-required-97.json / gd-required-186.json.

All 286 GD class/reason, alpha/background bbox, crop, trim method, background median and fit dimensions match Phase 0 exactly. Final classes: **165 cut / 121 photo**, 140 changed crops. All class changes:

| Asset | Source | Change | Reason |
|---|---|---|
| #097 | `catalog/Accsesuary/albom-dlia-kartok-OnePiece-na-400-slotiv.png` | cut → photo | `uniform_non_white_safe_cover_both_slots` |
| #186 | `catalog/Other/G7D sleeves premium plus (MTG).webp` | photo → cut | `mixed_page_white_or_transparent_corners` |
| #222 | `catalog/Pokemon/Gemini_Generated_Image_c8wi9fc8wi9fc8wi.png` | photo → cut | `mixed_page_white_or_transparent_corners` |

137 transparent-corner cuts remain cut; four Mystery Box assets and all 95 existing photo-class 3D assets remain photo. Geodude/Ditto dark rectangles and Glory of Team Rocket source frame remain. Wide products remain width-limited under the existing 84% box.

### 3 — verification evidence

- Local PHP **8.3.30**, GD: all286 generated fits; every actual Thumb data width/height equals served file dimensions, and the unchanged real Twig template renders matching attributes for cut and photo (286 assets). Synthetic product data; no DB writes.
- cropFit PNG and WebP: semitransparent edge alpha63 matches the source after crop and after save; transparent pixel alpha127 preserved. Full numeric evidence in functional.json. Invalid bounds throw before mutation.
- Runner checks **52/52**: dry run without backup/write; candidate and post-write lint; exact backup, apply, repeat/self-delete, rollback, drift/partial/missing/anchor refusals; actual invalid candidate lint failure; simulated post-write failure restores original bytes; reapply; F6 composition both orders. Actual Featured controller+Thumb payload mocks cover9 cases; normal24 cards add zero Thumb getProduct() calls.
- Printing and throwing site handlers: cold PNG/JPEG/WebP and warm paths have zero warnings/output. Warm paths decode zero images. Deletion race, unreadable cache, directory-not-file, corrupt/stale metadata and source stat change handled; original handler/error_reporting preserved and unrelated warnings still reach it. New v4 crop validation also rejects non-integer, out-of-bounds and inconsistent crop metadata.
- Local Chrome **64/64** checks across1440/1024/390/360px and category/search/special/manufacturer_info/featured grids: actual generated source pixels, intrinsic dimensions, slot ratios, contain-bottom vs cover, no horizontal overflow; hover/focus/active and no JS errors. Screenshots category-*.png. CSS is the previously approved October9 fixture, not a new live CSS export; these are local UI checks, not production QA.
- Native **PHP8.0 was not available locally**. Added code uses PHP8.0-compatible syntax; the runner logs PHP_VERSION, refuses PHP<8.0 and runs **PHP_BINARY -l on all3 candidates before writes and all3 target files after writes**. On the stated PHP8.0 host those checks execute with that native binary. Local PHP8.3 lint is not misrepresented as already executed host8.0 proof.
- Additional source/parity checks **305/305**: all source hashes, all286 original image hashes, Phase0 evidence/report seal, prior delivered patch hashes and protected legacy APIs unchanged.

### 4 — exact scope, cache names and preserved paths

Only model, image library and Thumb controller are patch targets. Twig, CSS and header tokens untouched. Commerce/state/attribute logic and caller payload handling untouched. Legacy resize(), its WebP path, JSON-LD image and the 700x700 lightbox URLs are unchanged. WP2/WP3 excluded. Metadata **v4**, fit cache key prefix **rd25-fit-v2:** prevents reuse of old full-canvas fit pixels. Old metadata/fit files retained unused; no cache deletion or manual clear required. New crop metadata validates exclusive integer bounds and derived dimensions before warm reuse.

## Runner, review and owner gate

All source hashes and anchor counts are checked before backup/write. Verified originals and candidates are saved under `_patch_backups/RD-25-WP1-FIX_media-trim-classification_20261011-<UTC>-<random>/`. Candidates lint before any target write. Writes use same-directory temp+rename, then lint/readback; errors restore written files. Repeat run checks exact candidate hashes before already_applied=yes. Rollback refuses subsequent changes and verifies original hashes. Success apply/repeat/rollback self-deletes the runner.

After independent review, owner uploads runner and executes:

```bash
cd ~/public_html && php RD-25-WP1-FIX_media-trim-classification_20261011.php
```

Optional source-gate preview: same command with `--dry-run`. Rollback: re-upload the same runner, then `php RD-25-WP1-FIX_media-trim-classification_20261011.php --rollback=_patch_backups/<directory printed at apply>` from ~/public_html. No server commands executed by the executor.

Owner QA after deployment: first visits on home/category/search/special/manufacturer/related listings produce no warnings; inspect OP13/15/17, album400 yellow, G7D sleeves, translucent edges, mystery boxes and unchanged dark3D examples. Capture native host PHP_VERSION and lint lines from runner output. First v4 visits scan source pixels and generate fits once; warm paths are cached. Native host8.0 runtime and production visual acceptance remain owner gates.

No commit, push, deploy, external status updates or design canon changes. Canon restoration stays at RD25 closure as review directs.

## Visual and timing companion evidence

[Actual GD before/after contact sheets](RD-25-WP1-FIX_media-trim-classification_evidence_20261011/contact-sheet/index.html): 33 required/reference assets across six pages, visually inspected. Phase0 before tiles are compared against actual generated GD fit files. #233 retains the full source canvas after strict fallback, so both sides use its identical full-canvas fit geometry. Contact tiles approximate the unchanged slot CSS; actual browser screenshots and checks are separate.

Local uncached source decode + pixel scan for #097/#131/#143/#186 measured 0.8389/0.2541/0.0488/0.0991 seconds respectively (PHP8.3, excludes encoding/network/hosting load). This is one-time work per source stat/version; warm metadata and fits perform no GD decode. First v4 requests can take longer; native hosting timing has not been measured. [Timing evidence](RD-25-WP1-FIX_media-trim-classification_evidence_20261011/cold-performance.json).
