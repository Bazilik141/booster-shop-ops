# Claude review — RD-25 WP2 (gallery) + WP3 (small thumbs)

Date: 2026-10-12 · Runners `RD-25-WP2_product-gallery_20261012.php` (sha256 `2d0dbf97…f607`), `RD-25-WP3_small-thumbs_20261012.php` (`b5ad8231…f776`) · Report `RD-25-WP2-WP3_implementation_report_20261012.md` · All 11 target diffs in the evidence folder read; WP2 runner logic read in full (WP3 shares the skeleton).

**Verdict: Deploy OK (both, WP2 then WP3).** Non-blocking notes below.

- Scope matches Phase 0 §9 and the review conditions. Legacy `popup`/`thumb`/`images`, resize calls and JSON-LD untouched (separate `bs_gallery`); lightbox opens the original 700×700 list at the selected index; thumbnails are native anchors with labels and `aria-current`.
- Old gallery height caps (RD-10, RD-10D2) and cover rules (RD-11 cart, RD-12 mini-cart) edited at source; no overrides, no `!important`.
- C1 met: every GA4 injector anchor count identical before/WP2/WP3 for both model VERSION branches; `data-ps-track-*`, add-to-cart fields, cart key/qty/remove markup unchanged; anchors re-checked by the runners against candidates before writing. Mini-cart keeps `row > a > img` for the GA4 anchor and the checkout-summary DOM copy.
- Runner: CLI + PHP ≥ 8.0, self-lint, context hash gates (WP1 model/library/thumb, GA4 model), header token normalised and replaced wholesale, mixed-marker refusal, verified backups, atomic writes with lint/readback and restore, rollback receipt; WP2 rollback refused while WP3 is applied.

## Non-blocking notes

- N1 — Gallery fallback: if the fit is refused on a cold request (budget/lock), an **additional** image's main fallback is its 100×100 legacy thumb, so selecting it shows a blurred enlarged image until the next view warms the fit. Transient; if seen in QA, follow-up: use the item's popup URL as the main fallback.
- N2 — Cart page desktop thumbnail shrinks 96 → 52×65 (approved spec). Judge in QA.
- N3 — Mobile product page: main image is now full-width 4:5 (≈ 440 px tall at 390) instead of the 340 px cap, so the price/buy block starts lower. Approved geometry; judge in QA.
- N4 — Pre-existing bug found by Codex: Enter in the cart quantity input submits twice. Out of scope; candidate for a BUG task.
- N5 — The report's cache command contains line breaks inside strings; owner gets a one-line equivalent.
