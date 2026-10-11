# RD-25 WP1-FIX — complete product photographs

Date: 2026-10-11
Status: ready for independent review; no commit, push or deploy.

## Scope and owner decision

Owner supplied nine post-deploy screenshots, clarified that the whole product must be visible and empty space is acceptable, and supplied the requested fresh six-file export. This correction follows that current instruction. It changes the earlier `photo = cover` requirement documented in the WP1-FIX handoff; all PHP classification/trim/cache/budget work stays intact. WP2/WP3 are not started.

Patch: `patches/RD-25-WP1-FIX_photo-contain_20261011.php`, 12,964 bytes, SHA256 `1a01abb7ccd98903a962ede7918410ec1920e696f92067ca22038a60fe52fcd1`.

## Cause and correction

Fresh source `boostershop-ds.css:276` sets `.bs-pcard__media--photo img` to `object-fit: cover` inside square desktop / 4:5 mobile slots. The r2 fit file retains the full photograph; browser cover geometry then crops it. Confirmed screenshots show missing outer sleeves (#192), slab sides (#091), and stand bases (#082/#084). Warming does not change this rule. The previous diagnosis contains the original-image comparisons.

Edit the existing rule directly to `object-fit: contain; object-position: center bottom`. Keep its existing element dimensions and radius. Update the adjacent geometry comment and add the correction's CSS marker. Header receives only a replacement stylesheet version token, read and validated at runtime by the CSS path prefix. No new selector, override layer, `!important`, positioning, timeout or JavaScript is added.

History checked: the photo/cut source selectors were introduced by `RD-25-WP1_media-fit-catalog_20261009.php`; no other runner defines those selectors. The fresh CSS contains one photo selector and the RD-26 amber preorder block. RD-26 and all unrelated rules remain byte-identical.

## Fresh input and target gates

Input: owner `RD-25-WP1-FIX-photo-check-20261011.tar.gz`, SHA256 `3039792077f051aec0ba0be08b86831d434788acf603f3019959418466a8843c`. Exactly six regular files; extracted into a separate local work source directory. [Source gate](RD-25-WP1-FIX_photo-contain_evidence_20261011/source-gate.json).

| Written file | Fresh before SHA256 | Expected after SHA256 |
|---|---|---|
| `catalog/view/stylesheet/boostershop-ds.css` | `edc5913817da258374c26aa03cb172eca07c2ee11e76a25402e46e7472440a53` | `c140e25de36e40b89830972cbc270b35cdda017cac0cd64118df935312eaf0f9` |
| `catalog/view/template/common/header.twig` | `05a195529958ad22f2cdf29229f36d2e47d65394328f6cbe0bba38c9378bb752` | `bf0328cd9da4095282069584150ca7a396fc4a09426b0e90b00ab852ffaa70d2` |

Header gate normalizes the single valid current token. Its normalized before/after hash is `eec16fbe81c72332d44ab2ecc7ee9f8a613a39206a21ab40ee04d7e2b7007cee`. A different valid token alone is accepted; other header drift fails before writes. Token read from the export: `rd25-wp1-media-fit-r3-20261010`; new token: `rd25-wp1-photo-contain-20261011`. The old token is evidence, not a runner anchor.

Read-only context gates validate the fresh r2 model/library/Thumb controller and unchanged Thumb Twig before first apply. Their fresh hashes match the accepted r2 candidates. The runner never writes them. [Manifest](RD-25-WP1-FIX_photo-contain_evidence_20261011/manifest.json), [exact diff](RD-25-WP1-FIX_photo-contain_evidence_20261011/source.diff).

## Local evidence

- `php -l`: no syntax errors in the runner, native local PHP 8.3.30. The runner requires PHP >=8.0 and self-lints via PHP_BINARY before writes. Both output files are CSS/Twig, so PHP lint is not represented as CSS/Twig syntax proof. Existing Thumb Twig and listing templates render with the existing Twig runtime.
- Dry-run: both target preflights, four context gates, `target_writes=0`, `done=ok`; no backup created.
- Local apply: verified original backups, exact two expected target files, `changed_files=2`, `done=ok`, `self_delete=ok`.
- Runner lifecycle and refusal checks: **18/18**. Includes exact backups, repeat, rollback, independent valid header token, repeat preserving a later token and unrelated later CSS, CSS/header/context drift, missing target, invalid/duplicate token, and restoration after simulated post-write failure. Prior r1/r2 report/patch/evidence seals are unchanged.
- Adapted existing requested UI checks: **64/64**, Chrome at 1440/1024/390/360px across category/search/special/manufacturer/featured fixtures. Actual fit images and intrinsic attributes, preserved slot ratios, contain + bottom for both classes, no horizontal overflow, hover/focus/active, no JavaScript exceptions. The fixture contains 36 actual assets including all four reported photograph defects, packs/cutouts, albums and Mystery Box artwork. Asset #222 remains a diagnostic-only control; the owner lookup previously found no production references.
- Existing actual Thumb Twig dimension comparison: **286/286** fit dimensions unchanged. No new classification scan or GD generation is required for this CSS correction.

[Runner checks](RD-25-WP1-FIX_photo-contain_evidence_20261011/runner-checks.json), [UI checks](RD-25-WP1-FIX_photo-contain_evidence_20261011/ui-checks.json), [desktop preview](RD-25-WP1-FIX_photo-contain_evidence_20261011/category-1440.png), [mobile preview](RD-25-WP1-FIX_photo-contain_evidence_20261011/category-390.png).

## Visible consequences and limits

All product-card photos use whole-source contain, including Mystery Box art and albums. At mismatched aspect ratios they gain empty space. Dark rectangular backgrounds remain in the original photographs. Cutout box geometry remains 84% x 94% with bottom alignment, so broad boxes remain shorter than narrow packs. Source compositions containing overlapping packs are not reconstructed.

Local fixtures prove the change with fresh CSS/Thumb and existing grid/theme resources; they do not prove current live whole-page behavior, database theme overrides, hosting PHP 8.0 execution or owner visual acceptance. Photos were previously confirmed unchanged by the owner. No automatic production requests or deployment were performed in this revision.

## Deploy, cache and owner QA

After review, upload only the new runner to `~/public_html` and execute it there. Clear compiled Twig cache after successful apply so header serves the new stylesheet token, then Ctrl+F5. Use the project's config-derived template cache directory; keep image/cache/rd25-fit and rd25-media metadata intact. This correction does not require another media warm-up or a metadata/fit version bump.

Verify sleeves' outer edges and the three stand photographs' complete bases/sides on desktop and mobile; check Mystery Box artwork, unchanged narrow packs, preorder badges and one listing add-to-cart action. Expected runner finish: `changed_files=2`, `done=ok`, `self_delete=ok`.

```bash
cd ~/public_html && php RD-25-WP1-FIX_photo-contain_20261011.php
```

Repeat uses only this correction's CSS marker/content and exits `already_applied=yes` without changing a later stylesheet token. First apply refuses target/context drift. Backups are under `_patch_backups/RD-25-WP1-FIX_photo-contain_20261011-<UTC>-<random>/original/`.

Rollback: re-upload the same runner and run `php RD-25-WP1-FIX_photo-contain_20261011.php --rollback=_patch_backups/<printed-directory>`, followed by compiled Twig cache refresh and Ctrl+F5. The exact receipt protects against rolling back newer CSS/header modifications. Rollback returns the previous photo cover behavior; media caches remain usable.

No commit, push, deploy, DB write, source-image edit, PHP model/controller/library change, gallery/lightbox/JSON-LD/resize URL change, status update, or canonical design-document edit.
