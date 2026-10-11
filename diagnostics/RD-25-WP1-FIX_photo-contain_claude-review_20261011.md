# Claude review — RD-25 WP1-FIX photo contain

Date: 2026-10-11 · Patch `patches/RD-25-WP1-FIX_photo-contain_20261011.php` (247 lines, sha256 `1a01abb7…fcd1`, read in full) · Report `…_photo-contain_report_20261011.md` · `source.diff` read.

**Verdict: Deploy OK.** Implements the owner decision of 2026-10-11 (whole product visible, empty space acceptable), which supersedes the RD-25 "photo = cover" framing.

- Change: `.bs-pcard__media--photo img` cover → `contain; object-position: center bottom`, comment updated, marker added; header token replaced wholesale via the single validated `boostershop-ds.css?v=` occurrence (convention 8). No new selector, override or `!important`.
- Runner: C1–C7 as in the reviewed RD-25 runners; four r2 context files hash-gated read-only; repeat keyed on the CSS marker so a later header token is never overwritten; rollback receipt verified.
- Context gate confirms r2 (model `85cf9489…`, library `7f81dcbb…`, thumb `cf4f8e35…`) is what is installed.

## Visible consequences the owner accepts with this change

- Album 400 yellow (wide 1.5:1 photo) returns to a full-width grey photo band at the bottom of the tile with empty space above — the r2 "safe cover" exception no longer has a visual effect.
- Square photos (Mystery Box, square 3D shots) on mobile 4:5 get ≈ 20 % empty space on top.
- `border-radius` sits on the full-size `<img>` box: a contained photo that does not touch the slot corners shows square corners. Cosmetic; fixing it needs a wrapper sized to the image — out of scope, track if it bothers.

## Deploy note

The report gives no concrete cache command; use the one from WP1 (clears data cache and compiled Twig only, keeps `image/cache/rd25-fit` and `rd25-media-*`).

## Canon

At RD-25 closure DECISIONS must record: cut = contain bottom in 84 % × 94 %; photo = contain bottom, full tile (owner 2026-10-11), replacing "photo cover".
