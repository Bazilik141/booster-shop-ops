# Claude review — RD-25 WP2-FIX gallery frame ratio

Date: 2026-10-12 · Runner `patches/RD-25-WP2-FIX_gallery-frame-ratio_20261012.php` (sha256 `5f29571f…5129`) · Report `…_report_20261012.md` · All four target diffs read; runner logic below `$targets = $spec` byte-identical to the reviewed WP2 runner (only task/marker/token constants differ). Screenshots inspected: #190 at 390 and 1440, #144 at 1440.

**Verdict: Deploy OK.**

- Implements the owner decision: frame ratio = primary served fit, clamped 0.8–1.0, set once via `--bs-gallery-ratio`; JS untouched, so thumb selection cannot change it. Photo/legacy centred; cut keeps inset + bottom (selector specificity checked: cut rules 0,2,2 beat the general 0,2,1).
- N1 fixed: additional-image main fallback = popup at `config_image_popup_*`.
- Gates: fresh 5/5 hashes, GA4 anchors equal for both branches, JSON-LD 8/8 identical, rollback returns to WP2+WP3 without touching WP3.

## Notes (non-blocking)

- Wide photos (> 1:1, e.g. Album 400 yellow 1.5:1) sit centred in a square frame with empty bands above and below — expected by the clamp; fixed properly only by a new photo (RD-27).
- If the primary fit is refused on a cold request, that request's ratio comes from the legacy thumb (square); a later warm view uses the fit ratio.
