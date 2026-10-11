# Claude review r2 — RD-25 WP1-FIX

Date: 2026-10-11 · Patch `patches/RD-25-WP1-FIX_media-trim-classification-r2_20261011.php` (sha256 `c01231fa…2921`) · Report `…-r2_report_20261011.md` · `revision.diff` (r1→r2 model) read in full.

**Verdict: Deploy OK.** G1 fixed.

- Independent recompute: the model `before` nowdoc equals the full 9d6aaec5 file (count 1, same length); replacement yields the pinned after-hash `85cf9489…`. Runner body identical to r1 except the r2 backup prefix. Library and thumb transformations unchanged from r1.
- Fast path: inside-band sources with no page-white/transparent corner return `photo` with full crop from four corners — matches the r1 rule order exactly (that branch never depended on the scan). 120/286 sources skip the scan; scanned pixels 554 → 160 Mpx. The five largest sources (12–22 Mpx JPEG) now cost ≈ 0.06–0.09 s (decode only).
- Integer bit-op scan with truecolor conversion after reading original corners; parity 286/286 operational, 166/166 scanned all fields. Null diagnostics only on fast-path photos, as approved.
- Budget 1.25 s cumulative per request (admission), `LOCK_NB` on both metadata and fit locks, no metadata write on refusal → legacy thumb fallback.

## Non-blocking notes

- Fit generation (decode/resample/encode of the v2 fit) is outside the budget. Real acsesuary category (20 products) cold ≈ 2.9 s, second ≈ 2.9 s, third 0.08 s locally; hosting likely slower. Owner should warm the main categories right after deploy (open each 2–3 times).
- Largest scanned source (≈ 5.75 Mpx PNG) can overrun the budget once by ≈ 1.7 s locally.
- PHP 8.0 lint/runtime proof comes from the owner run (runner logs `php_version` and lints all targets).

## Owner QA

Run, then open each category (One-Piece, Pokemon, more-tcg, acsesuary), home and search 2–3 times; judge framing only after the cards stop changing. Check: OP-13/15/17 equal height, OP-17 box, EB-02, Hot Wind Arena booster, Gallade, Album 400 yellow (photo like Album 360), Games 7 Days 63.5×88 whole package, acrylic stand, Mystery Boxes and dark 3D photos unchanged; no warning text; add to cart from a listing works.
