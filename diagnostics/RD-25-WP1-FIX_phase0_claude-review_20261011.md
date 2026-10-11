# Claude review — RD-25 WP1-FIX Phase 0

Date: 2026-10-11 · Report: `diagnostics/RD-25-WP1-FIX_phase0_report_20261011.md` · Contact sheets `required-01..04.png` inspected visually.

**Verdict: Phase 0 accepted — go Phase 1, with the conditions below.**

- Source gate: 4/4 hashes match the installed r3 + cache-miss state.
- Root cause confirmed in code; existing `Image::crop()` measured unusable for alpha (opaque black background) → new `cropFit()` helper is justified; `resize()`/`crop()` untouched.
- Thresholds are measured, not guessed; page-white ≥ 250 sits in an empty 240..251 gap.
- Visual result: OP-13/15/17 packs within 0.5 % height; OP-17 box, EB-02, Hot Wind Arena, Gallade, Mega Dream EX, acrylic stand fixed; Album 400 yellow now a photo like Album 360; G7D 63.5×88 shows the whole package; Mystery Boxes and the 95 dark 3D photos unchanged.
- Accepted limits: wide products (Outlet Mix, Toploader 1 pc, 4-album Pokemon set) stay width-limited by the approved 84 % box; Geodude/Ditto keep the dark rectangle (reshoot); Glory of Team Rocket frame is in the source.

## Conditions for Phase 1

1. **#222 `catalog/Pokemon/Gemini_Generated_Image_c8wi9fc8wi9fc8wi.png`** becomes `cut` and renders as a grey box. Not referenced anywhere in the 2026-09-24 DB dump (`boosters_ocart49.sql`, grep by filename: 0 hits) and not seen on the live Pokemon listing. Phase 1 report must state where it is used; if it is a listing/product image, stop and ask.
2. Album 400 passes the cover-safety test with ≈ 1.2 px clearance. The decision must be computed in PHP/GD from source pixels exactly as in Phase 0; report the GD-computed decision and bbox for #097 and #186.
3. Phase 1 evidence must include: `cropFit()` alpha preservation (PNG and WebP, semi-transparent edge pixel kept), served fit dimensions = emitted `<img>` width/height for cut and photo, cold/warm metadata path with zero warnings under a printing handler (cache-miss guard kept), composition with F6, idempotency and rollback, host `php -l` on PHP 8.0 in the runner.
4. One patch file, three targets as proposed (model, library, thumb controller), hash-pinned to the Phase 0 gate; Twig and CSS untouched.

## Canon note

Codex reports the RD-25 entry is missing from `design/DECISIONS.md` on master and in the working tree. Restore it at RD-25 closure together with the "live" update (text exists in the Claude canon copy).
