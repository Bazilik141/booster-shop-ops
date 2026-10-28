# Codex Report — ACC-006 + RD-25: mobile header and canonical media centering

Date: 2026-10-14
Executor: Codex, continuing the owner's assignment.
Revision: 4 (report-only post-review correction; patch byte-identical to revision 1).
Boundary: independent Claude CLI source review completed — **Deploy OK, no blockers**. Next gate: owner production dry-run, then separately owner apply and QA. ACC-008/009 remain queued.

Historical transport: three direct-API requests were rejected for insufficient API credit; none produced a review. Their logs remain preserved in the evidence folder. The owner clarified that "API" meant automated Claude CLI authenticated through the existing Claude.ai subscription. Current channel: installed Claude Code 2.1.299 binary from the VS Code extension, verified saved subscription login, no API key/helper/provider fallback. Existing account credentials are used only by the native CLI; no credential value, private account identifier or `.env.review` is read into the review adapter. No browser re-login was needed.

Current source review is saved separately as `ACC-006-RD-25_header-media-centering_claude-cli-review_20261014.md` only on a complete successful CLI response; transport evidence is `claude-cli-review-run.json`. Claude receives the verified packet through stdin with safe mode, no tools, no MCP/custom skills/hooks, no session persistence, and no Git/deployment/Notion/dashboard authority. A native CLI failure or subscription limit is not a source verdict, and does not trigger a direct API fallback or purchase. No reviewer acceptance is fabricated.

Completed native CLI evidence: one turn, Claude Opus 5.5/high, subscription auth verified, `api_key_source_initialized=none`, no tools/MCP calls, and `isUsingOverage=false`. CLI token-price estimates are not a subscription charge. All reviewed packet inputs were hash-identical throughout the request. The reviewed report snapshot is preserved as `report_reviewed_20261014.md` in the evidence folder. Revision 4 corrects the DB wording and records the accepted source verdict/remaining QA; it does not change the patch or its decoded payload.

Review context correction: the review text's sentence that ACC-007 remains blocked does not match the owner's earlier successful deployment/QA acceptance. Treat that sentence as a reviewer context error, not a task-status transition; no task/roadmap status was changed. Likewise, the reviewer mentions a numerical browser cache max-age that is not established by this packet; future RD-27 must verify actual hosting/browser/CDN headers rather than assume that value.

## Result and authorized scope

One combined PHP runner changes two existing production files. It hides the header wishlist control at the existing inclusive 768px mobile boundary, and centers contained catalogue/related-card and gallery images by editing their canonical component rules directly. The owner explicitly combined these changes on October 14 and requested the proper base mechanism. That decision supersedes the former RD-25 bottom-alignment policy.

The accepted ACC-006 wishlist runner and all three ACC-007 runners/reports are byte-identical to their locked versions. No Git command, deployment, Notion/dashboard write, customer/DB mutation or next account package was performed. The code dependencies and original photos are read-only inputs. No production image, resize/classification/cache implementation, cart/checkout/analytics script or product template is rewritten.

## Fresh source evidence

Owner drop: `header-media-current_20261014.tar.gz`, 23,410,170 bytes.

Archive SHA-256: `3619ee5857b9b468ae55e0f97bf02bf2d567b6aa21008b42d25d2c7fd44787d0`.

All 21 unique regular members passed safe-path, duplicate and hash checks: 14 code files plus seven original PNGs. Full manifest: `ACC-006-RD-25_header-media-centering_evidence_20261014/source-intake.json`. There is no config, DB dump, private customer payload or live-session token in the archive or review packet.

| Production target | Fresh raw SHA-256 | Expected installed raw SHA-256 with this token and LF |
|---|---|---|
| `catalog/view/stylesheet/boostershop-ds.css` | `e396793de22fdfa588267755a22c13a5c9b260ad086b97af942b3efe6af6b357` | `c5af44b72b362a8090b2f5699d41f06d0e2d1663ca9b82173c66d9d67413af0d` |
| `catalog/view/template/common/header.twig` | `ff9f59cb2164e6921e6e5153c002a0684f35114c7cc85f3cfa534c4a1d815d70` | `3d27b142faf99dbb0417d6cf4d736556e735e42e5718e0999c8d18177a70e01d` |

The runner normalizes line endings for source gates and separately normalizes the validated DS asset token in the header. It preserves actual target line endings. Twelve exact code dependency hashes fail closed before backup/write; full decoded entries and dependency list are in `payload.json`.

## Root cause and override history

The public acrylic-stand gallery and Album 400 catalogue card reproduced the owner's screenshots before implementation. Loaded public DS CSS matched the fresh archive hash. The blue area in the screenshot was the DevTools element highlight.

In the fresh DS CSS, catalogue `cut` at lines 273–274 has `top:6%; height:94%` and `object-position:center bottom`. Its bottom safe inset is zero. Catalogue `photo` at lines 276–279 is also explicitly bottom aligned. Gallery main/rail `cut` at lines 349–352 has vertical tracks `4% 96%` and bottom alignment. Gallery `photo`/legacy default at line 354 is already centered.

Prior selector writers were checked: `RD-25-WP1-FIX_photo-contain_20261011.php`, `RD-25-WP2_product-gallery_20261012.php`, and `RD-25-WP2-FIX_gallery-frame-ratio_20261012.php`. The defect follows their earlier framing choice. This runner edits those source declarations in place; it does not stack a second media override.

## Exact mechanism and expected appearance

| Component | Direct source change | Visible result |
|---|---|---|
| Catalogue/related `photo` | `center bottom` → `center center` | The entire proportional photo occupies the middle of the existing slot. |
| Catalogue/related `cut` | Height 94% → 88%; retain top 6% and existing horizontal geometry; center the image | Equal 6% top/bottom safe area, with spare contain space shared equally. |
| Gallery main and rail `cut` | Rows `4% 96%` → `4% 92% 4%`; center the image | Equal 4% top/bottom safe area; the same rule applies to every gallery item. |
| Gallery `photo`/legacy | Preserve its existing centered rule | Correct existing framing remains. |
| Header wishlist | Add one dedicated class and hide it in the existing `max-width:768px` header block | The mobile header loses the heart/count control; desktop keeps it. |

The new 88% and 92% values are derived from the existing 6% and 4% top insets mirrored at the bottom. There are no product-ID cases, negative margins, translate/transform adjustments, optical-centroid crops, timers, extra positioned layers or `!important`. The existing absolute image rule for cards is retained as the component's base layout, not newly introduced. No unexplained pixel value is added.

This centers the served bitmap. Space already drawn inside an original photo remains part of that photo. Images retain `object-fit:contain`, proportions and full edges; originals and fit-cache bytes are not edited. A tall cut-out can render slightly smaller where the newly symmetric safe area limits its height: the available catalogue height changes by about 6.4%, gallery height by about 4.2%. Landscape examples primarily move, with the same painted width/height.

The accepted gallery frame still follows the primary served ratio clamped to 0.8–1 and the existing maximum width. Thumbnail selection changes the bitmap, not the frame dimensions or adjacent panel position. Popup and JSON-LD URLs remain on their previous paths.

Measured native-template local examples at 1024px:

- Two-stand gallery image: same 372×465 frame and approximately 327.36×251.52 painted bitmap. Spare top/bottom goes from 213.46/0.02px to 106.73/106.75px.
- Album 400 card: same 222×222 slot and 222×147.79 painted bitmap. Spare top/bottom goes from 74.21/0px to 37.11/37.11px.

The hidden header link stays mounted with unchanged wishlist endpoint/token/count attributes. The existing wishlist JS obtains its settings by DOM selector, without a visibility condition. Card/product buttons, POST token handling and back/forward state refresh therefore retain their configuration. No real session token was used in the local fixtures.

## Local verification and limitations

Evidence folder: `ACC-006-RD-25_header-media-centering_evidence_20261014/`.

- `runner-tests.json`: 31 checks passed, including source/dependency/anchor/theme gates, no-write dry-run, exactly two target writes, self-delete, repeat/no second backup, arbitrary asset token, later-token preservation, exact rollback, drift refusal, tampered backup/traversal rejection, CRLF preservation and automatic restore after an injected write fault.
- Native runner `php -l`: clean on local PHP 8.3.30. Production remains PHP 8.0.30; runner syntax/APIs stay PHP 8.0 compatible. No production PHP target is changed, so no changed-target PHP lint line is expected.
- `fit-tests.json`: seven fresh originals processed by the unmodified native image model/library, 21 fits at main/rail/listing sizes plus existing legacy popups. Native dimensions match exact crop/scale geometry; no added padded fit canvas. Source/copied originals and source model/library remain unchanged. Zero warnings, stable warm URLs.
- Fit-fixture limitation: Windows sandbox permission-stat calls falsely refused paths that native file I/O could read/write. A test-only namespaced shim admits only the isolated cache root after an exclusive actual write/read/delete probe, and only cache files after an actual read probe. Production model/library bytes are untouched. Separate PHP requests preserve the native per-request admission budget. These results are local geometry/cache evidence, not hosting performance or permission proof.
- `render-tests.json`: full current header/product/thumb Twig compiled and rendered through native Twig, zero PHP/Twig warnings; before/after rendered product JSON-LD is byte-identical for the two fresh-photo examples.
- `ui-tests.json`: 1,054 assertions passed in offline headless Chrome. Widths 390/1024/1440 plus 768/769 boundary; all five stand images and both Album 400 images; long titles, out-of-stock/preorder cards, image containment and symmetric space, stable gallery dimensions, hover/focus/selected thumbnail, native popup/Escape, header visibility, card/product wishlist/count updates and persisted-pageshow POST state refresh. No document overflow or browser runtime errors.
- Additional narrow booster, booster box, square album and six-image product cases use retained October 12 fit payloads/cache images with current native Twig/CSS. They are regression fixtures, not fresh production inventory evidence. jQuery/Magnific Popup/Bootstrap JS are likewise retained diagnostic libraries. The comparison uses the native text-logo fallback/system font and a synthetic cart button; real logo, menu/search and stock mini-cart still require owner QA.
- `scope-tests.json`: both complete embedded entries decoded and checked; twelve readonly dependencies verified; seven locked prior artifacts hash-identical. No newly added `!important`, `setTimeout`, absolute/fixed positioned layers, `innerHTML` or `eval`. Native GA4 model replacement anchor counts are unchanged in both version branches. Pre-existing unmatched stock hooks remain unmatched; GA4 DebugView is not verified.
- `cache-tests.json`: the owner cache command removes nested compiled Twig PHP only and preserves RD25 metadata and other cache entries; missing cache fails. Linked entries are skipped and a linked/outside template root is refused.
- `future-photo-tests.json`: a synthetic local source was replaced at the same PNG path from a whole studio `photo` to a real transparent `cut`. The unmodified native metadata reclassified it, all three fit URLs regenerated without cache deletion, repeated warm fits stayed byte/URL-stable, previous fits were preserved, and the existing popup regenerated at its same URL for a newer original. The same disclosed Windows fixture permission shim was used; no production/original/archive file was changed.

Inspect the actual visual artifact `comparison.png`. It is a local render of real source templates/images, not an AI mockup or production acceptance screenshot. Reproducible task tooling is in `work/header-media-centering-20261014/`; it makes no production connection.

## Future media transition — owner clarification

Read-only context: `handoffs/handoff_RD-27-RD-28_media-standard-and-logo_20261012.md`. The owner now confirms that product photos will be refreshed gradually and every product will eventually have a transparent-background primary image. This resolves the old RD-27 target-style alternative for primary photos and supersedes the handoff's historical bottom-alignment description. Additional images can still be photographs. It is context for this package, not authorization to execute RD-27/RD-28 or change that handoff/roadmap.

Owner sequencing: execute the future media/brand handoff only after WP1/WP2/WP3 of the current account-data task, with its own later gates. This turn reads context and verifies forward compatibility only. ACC-008/009 and any future image batch, Pixelcut pilot, source replacement, DB path mapping, logo/favicon or canon/status edits are not started.

The current native controllers derive each image's `kind` and dimensions from `mediaInfo`, not a product-specific CSS list. Metadata is keyed by canonical path/mtime/size; fit URLs use the same source signature. A replacement can therefore change from `photo` to `cut` automatically; the centered `cut` rules apply without another centering patch. Gallery primary ratio is recalculated from the served primary for each new page render and remains stable while switching thumbnails. This does not freeze old-photo dimensions or assume every additional image becomes transparent.

Future RD-27 replacement requirements remain outside this runner: a replacement must change the stat signature, with source mtime newer than legacy cache files. Same mtime plus same size is not a content hash and does not guarantee refresh. Verify legacy URL/browser/CDN caching during the future deployment. Format/path changes such as JPEG to PNG/WebP need that package's separate mapping/rollback and Merchant/schema QA. The future native replacement fixture proves the valid changed-signature case, not an executed batch or universal hosting-cache acceptance.

## Runner, dry-run and safety gates

Patch: `patches/ACC-006-RD-25_header-media-centering_20261014.php`.

SHA-256: `34f2f2d9075e6bb929ea7f9078a69fd1cdfd46dacdfb021afb58c39f6b56e0a7`.

First pending gate is independent Claude source review. After an accepted review, upload this one file to `~/public_html` and run the owner dry-run gate:

```bash
cd ~/public_html && php ACC-006-RD-25_header-media-centering_20261014.php --dry-run
```

Expected successful lines:

```text
source_gate=2_targets_ok
db_preflight=read_only_theme_ok
db_mutations=none
dry_run=ok
done=ok
```

The only DB operation is a static read-only SELECT against active theme overrides for header/product/thumb. The runner validates DB_PREFIX with `^[A-Za-z0-9_]+$`, uses integer DB_PORT, and calls `mysqli::query()` plus result `fetch_assoc()`; no user input enters this SQL. These APIs do not require mysqlnd. It does not use a prepared statement or `bind_result`, and never calls mysqlnd-only `get_result`. An override, missing file, incompatible source/dependency, malformed/duplicate asset reference or partial marker stops before writes. No DB dump is required for this no-DB-write package. Generic errors are redacted.

C1–C8: existence/paths and exact anchors; backup receipt before write; atomic source updates and candidate/installed PHP lint where applicable; automatic restore on failure; marker/hash repeat gate; no DB writes; self-delete after success/repeat; prefix-based token read/validation/replacement. The new DS token is `acc006-rd25-media-center-20261014`, never appended to the old token. A repeat preserves a later valid token.

## Apply and cache refresh — only after the dry-run owner gate

The owner performs deployment. Save the exact printed backup directory. Exactly two `changed=` lines are expected, then `done=ok` and `cache_cleared=ok`.

```bash
cd ~/public_html && php ACC-006-RD-25_header-media-centering_20261014.php && php -r 'require "config.php"; $base=realpath(DIR_CACHE); if ($base===false) {fwrite(STDERR,"cache_path_missing\n");exit(1);} $tpl=realpath($base."/template"); if ($tpl!==false) {if (strpos($tpl.DIRECTORY_SEPARATOR,$base.DIRECTORY_SEPARATOR)!==0 || is_link($base."/template")) {fwrite(STDERR,"unsafe_template_cache\n");exit(1);} $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tpl,FilesystemIterator::SKIP_DOTS)); foreach ($it as $f) {if ($f->isFile() && !$f->isLink() && substr($f->getFilename(),-4)===".php" && !unlink($f->getPathname())) {fwrite(STDERR,"template_cache_clear_failed\n");exit(1);}}} echo "cache_cleared=ok\n";'
```

This refreshes compiled Twig only. The versioned DS reference refreshes browser CSS. Preserve image-cache fits and RD25 classification metadata; there is no image processing deployment or need to regenerate them. Reload the page after deployment.

## Rollback

Backup: `_patch_backups/ACC-006-RD-25_header-media-centering_20261014-<UTC>-<random>/`, printed by the runner. It contains the exact original CSS/header and a checked receipt. The successful runner self-deletes; re-upload the same runner to roll back.

```bash
cd ~/public_html && php ACC-006-RD-25_header-media-centering_20261014.php --rollback=_patch_backups/<exact-printed-directory> && php -r 'require "config.php"; $base=realpath(DIR_CACHE); if ($base===false) {fwrite(STDERR,"cache_path_missing\n");exit(1);} $tpl=realpath($base."/template"); if ($tpl!==false) {if (strpos($tpl.DIRECTORY_SEPARATOR,$base.DIRECTORY_SEPARATOR)!==0 || is_link($base."/template")) {fwrite(STDERR,"unsafe_template_cache\n");exit(1);} $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tpl,FilesystemIterator::SKIP_DOTS)); foreach ($it as $f) {if ($f->isFile() && !$f->isLink() && substr($f->getFilename(),-4)===".php" && !unlink($f->getPathname())) {fwrite(STDERR,"template_cache_clear_failed\n");exit(1);}}} echo "cache_cleared=ok\n";'
```

Rollback restores both original byte streams and the original asset token. A later header token/content or CSS change refuses rollback before either file is rewritten; obtain the current files for a new reviewed restoration rather than blindly overwriting subsequent work.

Rollback is recoverable but not a transaction across the two files. If an I/O failure interrupts it after restoring one target, do not run apply over the mixed state. Re-run the same rollback command after resolving the I/O issue; the receipt gate accepts the original/candidate states and rejects unrelated later changes. Preserve the runner/backup until rollback completes.

## Owner production QA

1. At 390/1024/1440px, open `/product/akrylova-pidstavka-dlya-kart`; select all five photos. Especially check the two-stand shot, balanced space, full edges, no distortion/frame jump, rail focus/selected border and enlarged popup/Escape.
2. Open `/catalog/acsesuary` and `/product/albom-dlia-kartok-na-400-slotiv`. Check catalogue and related-card alignment, both gallery images, long names and out-of-stock/preorder controls. Also check a narrow sealed pack, box, square photo and mixed gallery from the actual catalogue.
3. Mobile header: no heart/count at 390px and 768px; visible at 769px and desktop. Check logo, search/menu, account navigation, cart opening and wishlist access inside the account. Add from a card and product on mobile, verify list/count on desktop and browser Back.
4. Tier 1: home, `/catalog/Pokemon`, `/catalog/Pokemon/Pokemon-booster-box`, `/product/One-Piece-Boosters-OP-11`, a currently valid 3D product opened from the catalogue, `/information/oplata-i-dostavka`, cart and checkout entry. The owner-retired FIG-CHARM-001 is not a required control URL. Do not submit/pay for a test order.
5. Confirm wishlist GA4 `add_to_wishlist` in DebugView if analytics acceptance is required; local POST/UI evidence does not prove external event delivery. Check for new Twig/header/product errors in `/home2/boosters/ocartdata/storage/logs/` without sharing private log contents.

## Risk and next boundary

Business-data risk is low: no PHP business logic, DB write, stock, price or order status mutation. Shared stylesheet/header reach is broad, so the source/dependency/theme gates and all Tier 1 checks remain necessary. All `cut` card/gallery images adopt symmetric safe insets, and tall ones may shrink slightly as described; this is an intentional component-level result. Centering a photo does not change its internal composition.

Non-blocking review notes retained without changing the accepted runner: one old CSS documentation line still says 94% above the new 88% rule; the new explanatory comment inside a selector list is legal CSS but can be tidied at a later fresh-source touch; the runner requires `exec` despite no changed PHP target. Theme preflight is conservative across stores and owner Theme Editor/QA still matters. The owner must visually accept the approximately 6.4% height reduction for height-limited packs/cards. Full independent limitations and notes are in the immutable review artifact.

Stop at independent review, then the owner dry-run output and owner apply/QA gates. This package does not close roadmap statuses or grant ACC-008/009 implementation.
