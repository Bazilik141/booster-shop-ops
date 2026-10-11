# RD-25 WP1-FIX — r2 / G1 report

Date: 2026-10-11
Status: Ready for independent review; no commit, push or deploy.

## Revision boundary

Review: `RD-25-WP1-FIX_media-trim-classification_claude-review_20261011.md`, G1 items1–4. r2 replaces the undeployed returned r1 runner and applies directly to the same Phase0 three-source gate; it is not a follow-up runner on top of r1. Library cropFit and Thumb candidates are byte-identical to r1. Changes are confined to the model cold path, runner target payload/hash and revision filename/backup path. All classification/trim thresholds, legacy resizer/crop, CSS/Twig, commerce, JSON-LD/lightbox, source bytes, cache versions and prior artifacts remain unchanged. Accepted r1 scope/evidence is in the previous report.

| Target | Before SHA256 | After SHA256 |
|---|---|---|
| `catalog/model/tool/image.php` | `9d6aaec56eaa44be072e67957e43b7365ca8f115283988d0ab7a3227db885285` | `85cf9489244c48fe915bc25f370cdfdb2b39c9c6d6a42219cec7f84a9e375e71` |
| `system/library/image.php` | `b032345fa128dc867a55dbe4bf49f4070a34a2efa952525a69006fdb706875d9` | `7f81dcbb8b5d2d032005ae28ce01cea5ba7542f7334fd9909e538f6952adeff9` |
| `catalog/controller/product/thumb.php` | `47e56e45e134359074aecdd3a1a50a068e8b19766b40589cb8688e077df5d2eb` | `cf4f8e354b18a4318af159c34bf3c083d0991007b42d03e2c57aa4963c1ed3b1` |

Runner: `patches/RD-25-WP1-FIX_media-trim-classification-r2_20261011.php`; 47079 bytes; SHA256 `c01231fa36e8fbb65758b8df863f5831d93f53568bf652db856c4a269a682921`. [Model-only r1→r2 diff](RD-25-WP1-FIX_media-trim-classification-r2_evidence_20261011/revision.diff). Reviewed runner body is unchanged apart from revision name/backup path and target payload/hash. The model uses one exact full-file nowdoc replacement; the other two transformations remain the accepted r1 transformations.

## G1.1–2 — fast path and integer scan

Four source corners classify all **120 inside-band photos** directly with full crop; no interior scan or row/column buffers allocated. The remaining **166 sources** are scanned for crop/cover-safety measurements. Full pixel work falls from 554,124,538 to 160,312,729 source pixels (71.1% skipped).

Ensure truecolor immediately before the scan. Read original corners first: GD palette conversion zeroes hidden RGB at fully transparent pixels in #189/#206. Preserving their original corner RGB keeps the background median and all diagnostics identical. Each scan pixel uses imagecolorat integer bits (alpha>>24 &0x7F; RGB channel shifts); imagecolorsforindex is used only for the four corners. Thresholds/order, content counts, safety padding and strict fallback are unchanged.

**Parity:** all286 operational fields match r1; all166 production-scanned sources match every r1 measurement field, including all bboxes and cover checks. Forced full-diagnostic bit scans additionally match every measurement field for all286. Same source/stat/version/type fields remain unchanged. Exact per-asset comparison and enumerated diagnostic differences: [parity.json](RD-25-WP1-FIX_media-trim-classification-r2_evidence_20261011/parity.json). Class totals remain165 cut /121 photo; no additional class/crop changes.

Owner explicitly approved only the fast-photo diagnostic exception: alpha_bbox_16, background_bbox_12 and cover_check are null for120 fast photos. No bbox is invented from corners. rd25ReadMedia is byte-identical to r1, already accepts these nullable diagnostics and validates only operational crop bounds/dimensions. No code reads cached diagnostic fields for a decision. Tests accept null on cached metadata and then generate a fit successfully. Warm pre-existing full r1/v4 diagnostics remain valid.

## G1.3 — request admission budget and locks

Budget: **1.25 seconds cumulative per request**, measured with monotonic hrtime around source getimagesize, GD decode, pixel measurement and metadata publication. It leaves0.25s below the reviewer’s proposed1.5s limit as admission headroom. Warm validated metadata is returned before this check. Once cumulative time reaches the limit, each further uncached source returns[] without decoding, scanning or writing JSON. The unchanged Thumb controller then uses its caller-provided legacy URL and existing Twig framing. A new request resets the counter and continues warming.

This is an **admission budget, not a hard whole-request deadline**: an already admitted source finishes, even if it crosses1.25s. This avoids repeatedly abandoning the same large cut and never warming it. It can overrun by one source. Source stat/cache lookups, caller legacy resize, Twig work, fit decode/resample/encode and other page/controller work are outside the measured budget; whole render time is measured separately below. Native hosting timings remain unknown.

Both metadata measurement and fit-generation locks use LOCK_EX|LOCK_NB. Contention returns[] / empty fit URL immediately, leaving the legacy thumb visible; no queued multi-second wait. A warm metadata/fit cache is usable after budget exhaustion. Fixture checks force cumulative exhaustion across different sources and hold the real file locks; no metadata write or pixel scan occurs on budget refusal, both locks return in<0.1s, and unrelated site warnings remain visible.

## G1.4 — measured cold cost

Native local PHP8.3.30/GD, five largest originals by area, no source downsampling. All five use four corners and zero interior pixels:

| Asset | Source pixels | GD decode seconds | Corner measurement seconds | Total seconds |
|---|---:|---:|---:|
| #079 `catalog/3D print/Функціональні/Ditto-pidstavka-dlia- telefony.JPG` | 22,011,192 | 0.08583 | 0.00006 | 0.08590 |
| #078 `catalog/3D print/Функціональні/Ditto-pidstavka-dlia- telefony-01.JPG` | 19,616,436 | 0.08171 | 0.00006 | 0.08177 |
| #117 `catalog/One Piece/IMG_20260605_125627_236.jpg` | 18,365,508 | 0.08157 | 0.00004 | 0.08161 |
| #225 `catalog/Pokemon/IMG_7255.jpg` | 17,504,424 | 0.08245 | 0.00004 | 0.08248 |
| #058 `catalog/3D print/Фігурки/Mew-pockeball.JPG` | 12,102,048 | 0.05742 | 0.00007 | 0.05748 |

[All source timings](RD-25-WP1-FIX_media-trim-classification-r2_evidence_20261011/source-performance.json) also measure #097/#131/#143/#186 after the change. These are local decode/measurement timings, not host or HTTP timings.

The category fixture executes the real current model and unchanged Thumb controller, rendering the unchanged full Thumb Twig template and extracted category product-grid Twig. Synthetic names/prices; DB queries mocked read-only. GD sources are real original bytes linked into a separate fixture; originals are never written. Sources are ordered by descending area for a pessimistic admission order. Metadata v4 and fit-v2 caches start empty; legacy thumbs are warm, matching the deployment state of WP1/r3. Header/navigation, actual DB queries, network and unrelated full-page work are excluded.

| Set / request | Cards | Fit cards | Legacy fallback | Wall seconds | Warnings |
|---|---:|---:|---:|---:|---:|
| archive-superset / cold | 127 | 18 | 109 | 9.527313 | 0 |
| archive-superset / second-request | 127 | 20 | 107 | 2.736689 | 0 |
| archive-superset / third-request | 127 | 27 | 100 | 4.296255 | 0 |
| real-category / cold | 20 | 5 | 15 | 2.925105 | 0 |
| real-category / second-request | 20 | 20 | 0 | 2.898142 | 0 |
| real-category / third-request | 20 | 20 | 0 | 0.079819 | 0 |

Actual category input: owner read-only DB export at 2026-10-11T15:58:18+00:00, root category70, 20 enabled store0 products in acsesuary and descendants, primary image per product only. [Owner export](RD-25-WP1-FIX_media-trim-classification-r2_evidence_20261011/category-owner-lookup.json). All nonempty source paths were verified against the immutable286-source archive manifest before use. Zero DB/file writes from the lookup. The entire exported set is rendered in one request, without applying a pagination limit. Provenance is the owner-pasted public read-only lookup output; the executor did not connect to the hosting database.

## Checks and owner QA

- Runner **52/52**: exact gate, candidate/target lint, dry-run, verified backups, repeat, drift/partial/missing/anchor refusal, invalid candidate lint failure, restoration on simulated post-write failure, rollback, reapply and F6 composition both orders; real Featured/Thumb payload9 cases.
- G1 **19/19**: truecolor/palette parity and semi-alpha, exact4-pixel fast path including internal transparency, nullable diagnostics, cumulative budget, warm access after exhaustion, metadata and fit LOCK_NB, no warning output.
- All286 warmed metadata/fit/Thumb dimensions unchanged; unchanged actual Twig attributes equal served files. cropFit PNG/WebP semi-alpha63 retained after crop/save; zero printing-handler warnings. Small cold PNG/JPEG/WebP handler fixtures use dimensions that complete within the real budget; no budget constant is bypassed.
- Chrome **64/64** at1440/1024/390/360px across five grids, actual fits/intrinsic dimensions, contain/cover/slot ratios, overflow and hover/focus/active; no JS exceptions. Local approved CSS/Twig fixtures, not live storefront QA.
- PHP8.0 compatible additions; local native runtime is8.3.30. Runner logs native PHP_VERSION, refuses<8.0 and runs PHP_BINARY -l on all3 candidates before writes and all3 written targets after writes. Host8.0 proof is still owner-run evidence.

Optional CLI pre-warm was not added: it is not needed for this scoped revision and no OpenCart/DB bootstrap is introduced. Gradual warm-up is deliberate. First cold visits can show legacy padded thumbs, especially on a full large listing; hard refresh again before judging the new framing. Remaining cold sources continue warming on later requests; second visit is not a promise that every full-set asset is already warm. Check no warning/fatal output, preserved cart actions, and final framing of the r1 reference products. Do not delete old caches.

## Owner review/deploy boundary

Only the r2 runner should be uploaded after independent review. Existing three-target source gate, backup/lint/readback, restoration, exact applied-hash idempotency and rollback checks remain. No manual cache clear required (v4/v2 retained). No commit, push, deploy, database writes, status/canon changes or original image edits.

```bash
cd ~/public_html && php RD-25-WP1-FIX_media-trim-classification-r2_20261011.php
```

Rollback: re-upload the same r2 runner, then `php RD-25-WP1-FIX_media-trim-classification-r2_20261011.php --rollback=_patch_backups/<printed-r2-directory>` from ~/public_html; it refuses newer target modifications.
