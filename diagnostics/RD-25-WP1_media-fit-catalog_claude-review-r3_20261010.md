# Claude review r3 — RD-25 WP1 media fit + catalog grids

Date: 2026-10-10 · Patch revision 3 (diff against `round2/` read in full: aspect guard, metadata v3, header token r3 only) · Report revision 3.

**Verdict: Deploy OK; non-blocking notes.**

- F5 fixed as specified: inclusive 0.70–1.45 cover band via integer cross-products; outside → `cut` with `aspect_outside_cover_band`; v3 metadata rejects v2. 10 class changes, all photo → cut, none cut → photo. OP-15, QCAC, Beyond the Brave boosters now uncropped; the live DM24-RP3 booster thumb comes from the 1100×1100 transparent PNG, already `cut`.
- Side effect accepted by the rule: two tall 3D photos (Geodude 682×1024, Ditto pen cup 1024×1535) become `cut` — the whole dark photo rectangle is shown, bottom-aligned, uncropped. Owner QA: confirm acceptable; long-term fix is reshooting these at 3:4.
- F1 retained; F3 accepted; F4 tracked separately; F6 (featured payload) open, non-blocking.
- C1–C7 unchanged from r1; source hashes still pinned to the 2026-10-09 archives — if any of the 11 files changed on production since, the runner stops before writing (`source_sha256_mismatch`).

Owner QA additions: OP-05 Rare Pack, Blazing Dominion, OP-15, Beyond the Brave, QCAC tiles show the whole pack; a 3D category shows Geodude and the Ditto cup uncropped.
