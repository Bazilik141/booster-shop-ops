# Claude review r2 — RD-25 WP1 media fit + catalog grids

Date: 2026-10-10 · Patch revision 2 (791 lines; diff against `round1/` read in full: F1 classification, metadata v2, header token r2 only) · Report revision 2.

**Verdict: Return for changes** — one blocking defect (F5). F1 is fixed as specified. F2 is answered for every caller except featured (non-blocking, see F6).

## Findings

| ID | Severity | Where | Issue | Canon |
|---|---|---|---|---|
| F5 | **Blocking** | `catalog/model/tool/image.php` candidate, `mediaInfo()` classification | Opaque, edge-to-edge **pack art** (no background at all, the pack rectangle is the whole image) has non-white corners, so it stays `photo` and is rendered `cover`. In a 1:1 tile a 1:2 pack keeps only its middle half. Confirmed live primary images on 2026-10-10 (listing thumbs are 500×500 with the pack occupying ≈ 240–248 × 478–498 px, i.e. source ≈ 0.5): `OP-15 Booster` (Бустер One Piece OP-15), `Duel Masters — DM24-RP3 — JP — Booster`, `Yu-Gi-Oh! OCG Duel Monsters BEYOND THE BRAVE Booster`, and per the executor's own list `QCAC-BoosterJP`. My r1 instruction ("keep `photo` for non-white corners") caused this; the intent of the owner decision is that `cover` never crops a product. | Handoff §2: cover is for opaque photos **with a background** (3D shots, ≈ 3:4). DECISIONS RD-25. |
| F6 | Non-blocking | featured controller | Payload unverified (file not in the archives). Worst case adds one `getProduct()` per featured card on the home page, cached per request. Accept; confirm when the file is available. | — |

## Required change (F5)

Add an aspect guard to the class decision: `cover` (`photo`) only when the source aspect ratio w/h is within **0.70 – 1.45**; any source outside that range is `cut` (contain + bottom) regardless of corners. Rationale: inside that band `cover` in a 1:1 or 4:5 slot crops at most ≈ 30 % of one axis (3D photos at 3:4, the 4:3 Ditto bowl, square mystery boxes); outside it, cover would cut a pack roughly in half. Record the reason in the metadata (`class_reason: aspect_outside_cover_band`), bump `RD25_MEDIA_VERSION` to 3, re-run the classification and list every asset whose class changes. Keep F1 (near-white corners → cut) and everything else unchanged.

## Everything else

C1–C7 as in r1; scope unchanged; protected media paths unchanged; F3 accepted; F4 tracked separately.

## Before running / rollback / smoke

As in `diagnostics/RD-25-WP1_media-fit-catalog_claude-review_20261010.md`. Add to owner QA: OP-15 Booster, Duel Masters DM24-RP3 Booster, Beyond the Brave Booster and QCAC Booster tiles show the whole pack.
