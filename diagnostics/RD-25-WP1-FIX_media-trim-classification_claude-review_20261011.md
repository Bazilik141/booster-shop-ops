# Claude review — RD-25 WP1-FIX Phase 1 patch

Date: 2026-10-11 · Patch `patches/RD-25-WP1-FIX_media-trim-classification_20261011.php` (429 lines, sha256 `894c0e75…297a`) · Report `diagnostics/RD-25-WP1-FIX_media-trim-classification_report_20261011.md` · `source.diff` read in full.

**Verdict: Return for changes — one blocking defect (G1).** Everything else is accepted: rules match Phase 0 exactly (286/286 GD parity), `cropFit()` keeps alpha, `resize()`/`crop()` untouched, thumb emits crop-based dimensions, v4 metadata + `rd25-fit-v2` key, crop metadata validated, runner identical to the reviewed cache-miss runner plus a PHP ≥ 8.0 gate and an applied-hash check; review conditions 1–4 met (#222 unused).

## G1 — Blocking: cold-path cost on the storefront request

`rd25Measure()` visits every source pixel in PHP with `imagecolorsforindex(imagecolorat())`, inside the page request, for every source (photos included). The bump to v4 makes all metadata cold at once.

- Executor's own timing: 0.84 s for #097 (1.57 Mpx, local PHP 8.3) ≈ 0.53 µs/px, before encoding and on a faster machine than shared hosting.
- Corpus has primaries/secondaries up to 22.0 Mpx (Ditto phone stand JPGs), 18.4 Mpx (One Piece IMG_20260605), 17.5 Mpx (Pokemon IMG_7255), several 11–12 Mpx 3D JPGs → ≈ 6–12 s per source locally, plausibly 2× on hosting. Corpus total 554 Mpx ≈ 5 min of scanning.
- A cold category render with several such sources can exceed `max_execution_time` (fatal mid-render, broken page for a buyer). `mediaInfo()` takes a blocking `LOCK_EX`, so concurrent visitors of the same product queue behind a multi-second scan.

## Required change (G1)

1. **Skip the scan when the answer does not depend on it.** Read the four corners first. If the source is inside the 0.70–1.45 band and no corner is page-white or transparent, it is `photo` with full bounds — no pixel scan. Scan only when a crop box or the outside-band cover-safety bbox is actually needed. (This removes the large 3D JPEG photos from the cold path.)
2. **Faster scan, identical result.** Ensure truecolor (`imagepalettetotruecolor` on a palette source) and read `imagecolorat()` as an integer with bit operations (alpha = `($v >> 24) & 0x7F`, R/G/B shifts) instead of `imagecolorsforindex()` per pixel. Prove 286/286 identical metadata to the current Phase 1 output.
3. **Per-request time budget.** Keep a request-static cumulative measuring time; once it exceeds a bounded budget (propose ≤ 1.5 s, justify the value), `mediaInfo()` returns `[]` for further uncached sources in that request without writing metadata — the card falls back to the existing legacy thumb, and the next requests continue warming. Use a non-blocking lock (`LOCK_NB`): if another request is measuring the same source, return `[]` instead of waiting.
4. **Measure and report** the worst-case cold request: wall time for a cold render of the largest real category set (all 3D/acsesuary primaries) with the budget on, plus per-source scan time for the five largest sources after changes 1–2.
5. Optional, only if safe without DB writes or OpenCart bootstrapping risk: a separate CLI pre-warm mode. Otherwise document gradual warm-up in the owner QA.

Keep everything else byte-for-byte as in this revision.

## Owner QA additions (for the next revision)

First visit after deploy may show a few old-style (padded) thumbs on a cold page; they switch to the new framing on later visits. Hard refresh a second time before judging framing.
