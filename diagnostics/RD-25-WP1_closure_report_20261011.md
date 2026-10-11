# RD-25 WP1 — closure record

Date: 2026-10-11
Status: WP1 closed; parent RD-25 remains In progress.

## Owner authority and acceptance

The owner reported **"Deploy done, QA ok"** and explicitly authorized closure, commit/push and updates in this session. When the original three-package scope was reconciled against the canonical task, the owner explicitly selected **"Закрити WP1; RD-25 активна"**. This is the narrow owner reassignment permitting Codex to update this task's Notion record for closure. No standing Notion or Git permission is created.

Deployment and production QA are owner-reported acceptance. Previous source, runner and browser checks are local evidence; this record does not claim independent server access or a fresh production smoke run. No implementation or test rerun is part of this closure.

## Accepted scope and deployment sequence

1. `patches/RD-25-WP1_media-fit-catalog_20261009.php`: catalog fit URLs, media classification, desktop 1:1 / mobile two-column 4:5 listing slots; reviewed final r3 payload under the original filename.
2. `patches/RD-25-WP1_featured-image-payload_20261010.php`: featured caller image payload; closes the earlier F6 uncertainty.
3. `patches/RD-25-WP1_media-cache-miss_20261010.php`: cold JSON-cache miss handling without visible missing-file warnings.
4. `patches/RD-25-WP1-FIX_media-trim-classification-r2_20261011.php`: measured trim/classification, cropFit helper and Thumb integration; cold-path fast corner classification, integer scan, request admission budget and nonblocking lock fallback. SHA256: `c01231fa36e8fbb65758b8df863f5831d93f53568bf652db856c4a269a682921`.
5. `patches/RD-25-WP1-FIX_photo-contain_20261011.php`: owner-approved whole-product framing for photos; CSS photo rule becomes contain + bottom, header asset token refreshed. SHA256: `1a01abb7ccd98903a962ede7918410ec1920e696f92067ca22038a60fe52fcd1`.

The returned `RD-25-WP1-FIX_media-trim-classification_20261011.php` r1 is retained as historical review evidence only. It was superseded by r2, which applies directly to the Phase 0 source gate. These are historical, hash-pinned runners, not a new deployment bundle or an instruction to replay them on the live shop.

## Current design decision

Owner decision 2026-10-11: **the whole product must be visible; empty space is acceptable**. This supersedes the original photo-cover requirement.

- Catalog cut images: contain, bottom alignment, 84% x 94% inner box.
- Catalog photos: contain, bottom alignment, full tile.
- Desktop slot: 1:1. Mobile: two columns, 4:5.
- Source files are preserved. Product JSON-LD image URLs, legacy resize callers and 700x700 lightbox URLs remain unchanged by this WP1 work.
- Metadata version 4 and fit cache version 2 remain current for the accepted trim/classification revision.

`design/DECISIONS.md` records the live catalog decision and explicitly separates the pending gallery/small-thumbnail scope.

## Evidence and accepted limitations

- Independent Claude verdicts: `diagnostics/RD-25-WP1-FIX_media-trim-classification-r2_claude-review_20261011.md` and `diagnostics/RD-25-WP1-FIX_photo-contain_claude-review_20261011.md`: **Deploy OK**.
- r2 report: 286/286 operational metadata parity; all 166 scanned assets match every r1 measurement field. The owner-approved exception is null diagnostic bbox/cover fields on 120 corner-only photos; those fields are not decision inputs.
- r2 previous local checks: 52 runner checks, 19 G1 checks, 64 browser checks. Real accessories category fixture: 20 owner-exported primary images, cold 2.925105s with 5 fit / 15 fallback, second 2.898142s with 20 fit, third 0.079819s with 20 fit; zero warnings. These exclude live DB/network/header work and are not host timings.
- Final photo-contain previous local checks: 18 runner checks and 64 browser checks across 1440/1024/390/360; 286 Twig dimension records unchanged. The final r2 and photo-contain seals are preserved and checked during artifact preparation; prior reports are not rewritten.
- The 1.25s classification budget controls admission, not a hard page deadline. One already admitted scan can overrun; fit encoding and other controller work are outside it. Lock contention/budget exhaustion uses the legacy thumb until later visits warm the cache.
- Empty space above square/wide photos is accepted. A contained photo that does not reach the image-box corners may have visually square corners; that cosmetic wrapper change is outside WP1.
- Local PHP 8.3 syntax/runtime evidence is not a hosting PHP 8.0 execution claim.

## Task synchronization and Git boundary

Canonical Notion card: https://app.notion.com/p/3f46bf20bdb4814d91a7c9cc06088eea.

Notion was updated and read back: Status **In progress**; Stage **WP1 closed: owner deploy + QA OK; WP2/WP3 not started**; Last Updated **2026-10-11**. The dashboard mirrors the active parent status and the accepted WP1 milestone. `context-index.md` adds routing pointers only; the existing Notion page-ID registry already contains RD-25 and needs no change.

The original checkout is on `codex/3dp-payout-approval-status` with unrelated tracked and untracked changes. RD-25 closure is prepared in an isolated managed worktree based on freshly fetched `origin/master`; only RD-25 artifacts and its three canonical/routing mirror files enter the commit. Unrelated branch history and owner changes are excluded. The manifest `diagnostics/RD-25-WP1_closure_manifest_20261011.json` inventories copied task artifacts. Raw live archives, working fixtures and secrets are excluded.

## Remaining scope

**WP2 product gallery and WP3 small thumbnails are not implemented, deployed or accepted.** RD-25 stays active by explicit owner decision. A new owner instruction is required to begin either package. WP1 completion does not imply completion of the original three-package task.
