# Claude review — RD-25 WP2+WP3 joint Phase 0

Date: 2026-10-12 · Report `diagnostics/RD-25-WP2-WP3_phase0_report_20261012.md` · Inspected: product contact sheets 1440/390, mini-cart and cart after (390), export cleanup script.

**Verdict: Phase 0 accepted — go WP2-WP3, with conditions C1–C3.**

- Source gate 6/6 matches the live WP1 end state; DB theme override checked (zero rows); host PHP 8.0.30 confirmed.
- Gallery root cause and plan are sound: separate gallery-view array (JSON-LD `image` and popup URLs untouched, 7/7 byte-identical), primary included in the rail, no rail for single-image products (37), main-image lightbox at the selected index, attribute-preserving URL transfer, source CSS rules edited instead of new overrides.
- WP3 map is correct: checkout summary reuses the mini-cart `img.src` by DOM copy, so it stays out of scope and inherits the fit URL in its 48×48 contain box.
- Visuals: rail left at 1440, row below at 390, whole product visible; mini-cart/cart 52×65 thumbs readable. Album 400 is small in the cart thumb — expected under the contain rule.
- Cold cost acceptable: worst product page ≈ 2.8 s first view, cart with 20 largest items ≈ 3.7 s, then ≈ 0.05 s.
- Cleanup script is scoped to this export chain and keeps the accepted archive; safe for the owner to run.

## Conditions for Phase 1

- **C1 — GA4 injector anchors (not covered in Phase 0).** `ps_enhanced_measurement` injects its scripts by literal `str_replace` against stock fragments of `product/product.twig`, `common/cart.twig`, `checkout/cart*.twig` and `common/header.twig`; a removed anchor silently kills the event. Request the module's catalog model file from the owner (read-only), list every anchor bound to the touched templates, and prove each anchor's occurrence count is identical before and after WP2 and after WP3. Also prove unchanged: `data-ps-track-*` attributes, add-to-cart form fields (`product_id`, `quantity`), cart quantity/remove controls.
- **C2 — Checkout smoke.** WP3 touches `checkout/cart.twig` and `checkout/cart_list.twig` → owner QA includes `bs-checkout-smoke` steps 1–4 (add, mini-cart, cart page qty/remove, proceed to checkout). No order submission needed.
- **C3 — Composition and rollback** exactly as in report §9 (WP1→WP2→WP3, WP3 rollback → after-WP2, WP2 rollback → WP1), plus 1440/1024/390/360 UI checks, keyboard focus on thumbs, and a fragment reload of mini-cart and cart list after qty change.
