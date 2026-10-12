# Handoff addendum — RD-25 WP2 (product gallery) and WP3 (small thumbnails)

Date: 2026-10-11
Executor: Codex · model=Sol · effort=high — Codex delivered all WP1 patches this round (same-executor rule); the gallery touches product-page template/JS and the lightbox path, so a large model is warranted.
Owner decision on executor: pending.

Base handoff: `handoffs/handoff_RD-25_product-media-slot_20261009.md` (in master) §4 WP2/WP3, §5–§9. This addendum **overrides** it where they differ. **Owner decision 2026-10-11: WP2 and WP3 run as one stage** — one Phase 0, one review round, one deploy session. They stay **two patch files** (`RD-25-WP2_product-gallery_<date>.php`, `RD-25-WP3_small-thumbs_<date>.php`) so each has its own backup and rollback; WP3 must apply cleanly after WP2 and both must refuse partial states.

## What is live now (WP1 complete, owner QA OK 2026-10-11)

WP1 r3 → F6 featured payload → cache-miss repair → WP1-FIX r2 (v4 classification, `cropFit()`, `rd25-fit-v2`, 1.25 s budget, `LOCK_NB`) → WP1-FIX photo-contain (CSS token `rd25-wp1-photo-contain-20261011`). Reviews in `diagnostics/RD-25-WP1*`.

## Overrides to the base handoff

1. **Framing rule (owner 2026-10-11):** whole product always visible, empty space acceptable. `cut` = contain, bottom-aligned, inside the inset box; `photo` = **contain, center bottom** (not cover). Applies to WP2 main image, WP2 thumbnails and WP3.
2. **Reuse, do not re-implement:** `model_tool_image->mediaInfo()` / `resizeFit()` (v4 metadata, crop for `cut`, fit-v2 key, budget, locks, cache-miss guard). New box sizes only; no new classifier, no new resizer, no metadata/fit version bump unless Phase 0 proves it necessary.
3. **Cold path:** new fit sizes are generated on first view. Product page renders one main + N thumbs; WP3 renders a few rows. Measure cold cost in Phase 0; keep within the existing budget model (fallback to the legacy image when refused).
4. **Do-not-touch additions:** r2 model/library/thumb controller behaviour for listings; `.bs-pcard__media*` rules; RD-26 preorder rules; the 700×700 popup URL and JSON-LD/og:image (unchanged from base §5).

## WP2 + WP3 — mandatory joint Phase 0 (read-only, then stop)

0.1 Ask the owner for a fresh export and verify hashes (expected model `85cf9489…`, library `7f81dcbb…`, thumb `cf4f8e35…`, CSS = photo-contain after-hash `c140e25d…`; header token `rd25-wp1-photo-contain-20261011`). Starting command, extend it in your request if Phase 0 needs more files:

```bash
cd ~/public_html && tar -czf ~/RD-25-WP2-check-$(date +%Y%m%d).tar.gz catalog/controller/product/product.php catalog/view/template/product/product.twig catalog/model/tool/image.php system/library/image.php catalog/view/stylesheet/boostershop-ds.css catalog/view/template/common/header.twig catalog/controller/common/cart.php catalog/view/template/common/cart.twig catalog/controller/checkout catalog/view/template/checkout
```

0.2 Map the current product-page gallery: which template/partial renders the main image and thumbs, which JS switches images and opens magnific-popup, where `#F8FAFC` comes from, and whether a DB theme override exists for `product/product` (check `ocp5_theme` in the newest backup; do not guess the prefix).
0.3 Inventory: per live product, number of images and their classes (from v4 metadata rules); list products with 1 image (no rail) and with > 5 images (rail overflow).
0.3b WP3: map mini-cart, cart page and (only if they share the partial) checkout summary thumbnails; report the live-search thumbnail admin setting.
0.4 Before/after contact sheet at 1440 and 390 (product page, mini-cart, cart page) for: a sealed booster, a booster box, a Mystery Box, a dark 3D photo, Album 400 yellow, Games 7 Days 63.5×88, a product with ≥ 5 images.
0.5 Measure cold cost of one product page (main + thumbs) and of the cart page with empty fit cache.
0.6 Deliver `diagnostics/RD-25-WP2-WP3_phase0_report_<date>.md` + evidence; stop until the owner replies "go WP2-WP3".

## WP2 acceptance (adds to base §7)

Main image 4:5 slot, contain per the framing rule, no grey backing; thumbnails 4:5 with the same rule; clicking a thumb switches the main image; the lightbox `href` and JSON-LD `image` byte-identical to before (diff in report); keyboard focus visible on thumbs; no horizontal scroll at 360; repeat → `already_applied=yes`.

## WP3 acceptance

Base §7 WP3 with the 2026-10-11 framing rule. If any checkout template is touched → `bs-checkout-smoke` steps 1–4 in owner QA. Live search stays vendor-untouched (report its admin setting only).

## Closure

After WP3 QA: RD-25 Done in Notion + dashboard mirror; `design/DECISIONS.md` RD-25 entry restored and moved to live with the 2026-10-11 framing rule.
