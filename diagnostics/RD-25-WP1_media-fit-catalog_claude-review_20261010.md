# Claude review — RD-25 WP1 media fit + catalog grids

Date: 2026-10-10 · Reviewer: Claude (chat) · Patch: `patches/RD-25-WP1_media-fit-catalog_20261009.php` (read in full, 780 lines) · Report: `diagnostics/RD-25-WP1_media-fit-catalog_report_20261009.md`

**Verdict: Return for changes** — one blocking defect (F1). Everything else is non-blocking.

## Conventions and scope

C1–C7 present: file/writability checks; exact anchor counts plus before/after SHA-256 pinning; backup and candidate lint before any target write; lint + readback after write with restore on failure; all-or-nothing marker in all 11 targets (`already_applied=yes`); no DB writes; self-delete. Scope matches handoff §4 WP1; nothing from §5 is written (product page, popup/JSON-LD path, cart, vendor modules, DB). The existing `resize()` and its callers are untouched.

## Findings

| ID | Severity | Where | Issue | Canon |
|---|---|---|---|---|
| F1 | **Blocking** | `catalog/model/tool/image.php` candidate, `mediaInfo()` (patch l.188–198) | Opaque sealed product art on a white background is classified `photo` and rendered `object-fit: cover`. For portrait packs in a 1:1 tile, cover keeps only the middle ≈50 % of the height: the top and bottom of the booster are cut off. Affected primary images per the executor's own `photo-audit-index.json` (`all_near_white_corners: true`): OP-05 Booster 510×1000 (live Rare Pack listing), Yu-Gi-Oh Blazing Dominion Booster 726×1500 (live product 101), MS1 665×1301, Pitch Black Checklane Blister 343×630, White Flare 573×1183, OP-05 display 515×868, EB-02 card 844×1200, MS2, MSB1, others. | Handoff §2 / DECISIONS RD-25: cover is for opaque **photos with a background** (3D shots). A white background on a white page is visually a cut-out; cropping a sealed pack defeats the task's goal. |
| F2 | Non-blocking | thumb controller (patch l.380–393) | When the caller does not pass `image`, the controller calls `getProduct()` once per card. If category/search/special/manufacturer/featured do not pass `image`, a listing page adds one full `getProduct()` per tile (24+ queries). The report says the original image comes "from the product row" but does not state per caller whether `image` is present. | Performance; state it per caller in the report. |
| F3 | Non-blocking (decision) | report §Verification limits 1 | Visible content of some cut-outs is ≈89 % of the slot height (source transparent margins kept). Acceptable: the 90 % figure in handoff §7 was an approximation; no source trimming. | — |
| F4 | Non-blocking | report §Verification limits 3 | Global `outline: none !important` in `stylesheet.css` hides focus on the buy button; pre-existing, out of WP1 scope. Track separately. | RULES: focus visible. |

## Required change (F1)

Extend the classification so that an opaque source whose four corners are all near-white (the executor's `all_near_white_corners` test, e.g. each RGB ≥ 245 and roughly uniform) is `cut`, not `photo`. Keep `photo` for opaque sources with non-white corners (the dark 3D shots). Bump `RD25_MEDIA_VERSION` so cached metadata is recomputed. Re-run the classification and list the reclassified assets; light-background 3D photos that become `cut` are acceptable (they render as today, uncropped).

## Before running (after the fix)

- Expect `changed_files=11`, one backup directory `_patch_backups/RD-25-WP1_media-fit-catalog_20261009-<UTC>-<random>/`, `self_delete=ok`. A repeat run needs a re-upload.
- Source hashes are pinned to the 2026-10-09 archives: any later change to one of the 11 files makes the runner stop before writing.

## Rollback

Re-upload the runner and run `--rollback=_patch_backups/<printed directory>`, then clear the theme cache (command in the report). New `image/cache/rd25-fit/` files and `rd25-media-*` metadata stay unused. Roll back WP2/WP3 first if deployed.

## Smoke after deploy

`bs-deploy-verify` plus the report's owner QA list; add: an OP-05 Rare Pack tile and the Blazing Dominion tile show the whole pack.
