# CAT-004 / RD-11 mobile trust, credit cards, and cart alert follow-up

Date: 2026-09-24
Executor: Codex
Review gate: Claude; deployment and final browser QA: owner.

## Outcome

One independently runnable PHP patch implements the four owner screenshot corrections:
`patches/CAT-004_RD-11_mobile-trust-cart-alert_20260924.php`. It changes
`catalog/view/stylesheet/boostershop-ds.css`,
`catalog/view/template/checkout/cart_list.twig`, and
`catalog/view/template/common/header.twig` (CSS cache token only).

The patch has not been deployed, committed, or pushed. It does not edit stock
predicates, checkout eligibility, payment eligibility, price calculations, or
the shared homepage trust strip.

## Source and root causes

- Source shape: owner's `BS_cart_product_ui_20260924.tar.gz` export plus the
  locally composed prior seven runners. The latest screenshots show the later
  rendered UI; no fresh post-deployment source archive was supplied. Exact
  anchors stop the runner before writing if the server differs.
- `boostershop-ds.css` product mobile rule currently sets
  `.bs-product__info .bs-trust-strip__item { align-items: flex-start; }`.
  This puts the icon at the first text line when labels wrap. The later
  preorder-only rule also sets `text-align:center`, making its three-line item
  inconsistent with the other two items. The patch centers icons vertically
  for all product trust items and left-aligns the preorder label. Both rules
  stay inside `.bs-product__info`, avoiding the homepage strip also touched by
  `cat002_5c_mobile_visual_breadcrumb_20260630.php`.
- `CAT-004_preorder-price-credit-layout_20260924.php` created a two-column
  provider grid and a 480px rule that compresses both cards. The patch edits
  this mobile source rule to use one column below 768px; desktop remains two.
  The second card's dividing line moves from left to top. One-provider markup
  still spans the only column.
- `cart_list.twig` has a PAY-002 stock hint above the heading and an RD-11
  stock notice below it, which creates the duplicate shown in the screenshot.
  The runner removes the PAY-002 top hint and the fallback stock-only alert.
  The RD-11 notice, unrelated error/success/preorder/minimum messages, and
  both checkout button guards remain. In the supplied cart controller,
  `error_stock` is only populated when `$has_stock_error` is true, while
  `pay002_cart_stock_warning` equals that same `$has_stock_error`; the retained
  RD-11 notice therefore covers the removed stock-specific top alerts.

## Override history and review points

- Product trust source rule: `CAT-004_info-column-reflow_20260923.php`, then
  `CAT-004_trust-credit-copy_20260924.php`.
- Provider source rule: `PAY-001_phase2_credit_ui_20260721.php`, PAY-002 PUMB
  Twig/JS, then `CAT-004_preorder-price-credit-layout_20260924.php`. The local
  fixture has a slightly different 480px selector spelling from the final
  runner; this patch accepts either exact known spelling and no other drift.
- Cart top hint source: `PAY-002_cart-stock-hint-correction_20260831.php`;
  RD-11 added the lower redesigned notice. Inspect removal of both top
  stock-only blocks and retention of the lower notice and checkout guards.
- The responsive declarations live inside the existing source breakpoint
  section. No new `!important`, timeout, absolute/fixed positioning, or JS was
  added. The 767.98px breakpoint matches the storefront mobile breakpoint.

## Local validation

- Runner `php -l`: passed (local PHP 8.3.30; syntax is compatible with
  production PHP 8.0).
- Ran the runner on copies of both known September 24 CSS spellings (the
  composed fixture and the final predecessor runner form): each returned
  `done=ok`, logged three backups, changed three files, and self-deleted.
  Re-copy and repeat on the composed fixture returned `already_applied=yes`.
- Full revised `cart_list.twig` parsed using local Twig 3.28.0. The existing
  focused rendering matrix still passed for product stock/preorder status and
  all three cart line labels.
- A localhost component fixture using the patched real stylesheet was checked
  in the in-app browser at 320, 390, 768, and 1280px. At 320/390, the two
  provider rows occupied successive full-width rows; at 768/1280, they shared
  one row. For both ordinary and preorder three-item trust lines, SVG and text
  center Y coordinates matched to within 0.01px; no horizontal overflow was
  observed. The isolated fixture did not include the bank image assets, so
  these are geometry checks, not final full-page or production visual QA.
- Hover/focus behavior was not changed: the edited provider and trust items
  are informational. Cart controls were left untouched.

## Run and rollback

Run this **after** the seven previous runners in the final Claude review
packet, or by itself if those seven have already succeeded on the server.
Upload the patch to `~/public_html`, then the owner may run:

```bash
cd ~/public_html || exit
php CAT-004_RD-11_mobile-trust-cart-alert_20260924.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

On `done=failed`, stop; inspect the anchor error and obtain the current source
instead of forcing an edit. For rollback, restore the three logged files from
`_patch_backups/CAT-004_RD-11_mobile-trust-cart-alert_20260924-*/` and clear
the OpenCart template cache.

Owner QA after deployment: inspect normal and preorder product trust lines at
320/390px; Mono/PUMB rows at mobile and desktop; cart at both widths with a
stock warning (one redesigned notice, no top duplicate); verify blocked
checkout button and unrelated cart messages still work. Run normal Tier 1
smoke URLs. No production claim is made from local fixture QA.
