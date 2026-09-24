# Codex Report — CAT-004: mobile buy bar below mini-cart

Date: 2026-09-24

## Scope

The owner's final correction: the fixed quantity/buy bar on mobile product pages covers the open mini-cart. This runner changes only the source `z-index` rule for that product-page bar. It does not alter cart contents, price logic, checkout, or shared CSS.

## Root cause and override history

The live EB-03 product page at a 390 × 720 viewport computed `.bs-sticky-atc` at `z-index: 1050`, while `.bs-mini-cart__overlay` and `.bs-mini-cart__panel` were `400` (`--bs-z-modal`). With the drawer open, `document.elementFromPoint()` on its lower area returned `.bs-sticky-atc__cta` rather than a mini-cart child. The user screenshot shows the same occlusion over the checkout button.

`product.twig` contains the R-04 inline CSS source rule at approximately line 603. `boostershop-ds.css` defines `--bs-z-sticky: 200` and `--bs-z-modal: 400`; RD-12 uses the modal token for the drawer. Repository search found no later patch changing `.bs-sticky-atc`. The fix replaces the source rule with `z-index: var(--bs-z-sticky, 200)`; it adds no override or `!important`.

## Files touched

```text
patches/CAT-004_mobile-sticky-atc-minicart-layer_20260924.php
```

The runner changes `catalog/view/template/product/product.twig` on the owner's host.

## Local validation

```text
php -l patches/CAT-004_mobile-sticky-atc-minicart-layer_20260924.php
No syntax errors detected in patches/CAT-004_mobile-sticky-atc-minicart-layer_20260924.php

fixture apply: changed=catalog/view/template/product/product.twig; done=ok
repeat: already_applied=yes; done=ok; file SHA-256 unchanged
source drift fixture: anchor_count_product_sticky_rule=0,expected=1; done=failed; file SHA-256 unchanged
```

The source diff contains only one comment and the `z-index` replacement. No Twig expression or JS changed. Product CSS media condition remains `max-width: 768px`; drawer layer remains 400 at the mobile and desktop media rules. At report preparation, the runner had not been uploaded or executed by the owner. The local browser fixture could not be opened by the available browser tooling, so 320/390/768 px and desktop visual checks remained for owner QA.

Owner follow-up on 2026-09-24: the overlap is fixed after deployment. This is owner-reported production QA; Codex did not independently rerun the post-deploy browser checks.

## Idempotency and rollback

Re-running returns `already_applied=yes` without changing the target. The runner backs up the original Twig file to `_patch_backups/CAT-004_mobile-sticky-atc-minicart-layer_20260924-<timestamp>-<pid>/product.twig` before writing and self-deletes after success. To roll back, restore the logged `product.twig` backup to `catalog/view/template/product/product.twig`, then clear OpenCart cache.

## Run command (owner, after Claude review)

Upload the PHP file to `~/public_html`, then run this single block:

```bash
cd ~/public_html || exit
php CAT-004_mobile-sticky-atc-minicart-layer_20260924.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA

- [ ] At 320 and 390 px, scroll until the quantity/buy bar is visible, open a mini-cart with an item and a long product title, and confirm the checkout button and bottom of the drawer are fully visible and clickable.
- [ ] Close the mini-cart and confirm the quantity/buy bar returns and its controls remain usable.
- [ ] At 768 px, confirm the drawer and bar do not overlap. At desktop width, confirm the mobile bar remains hidden.
- [ ] Repeat on an in-stock and a pre-order product. Confirm the shop's normal Tier 1 smoke URLs if this patch is deployed.

## Risk

Low and limited to the product-page mobile bar stacking order. The owner confirmed the reported overlap is fixed; full Tier 1 and breakpoint checks are not independently verified by Codex.
