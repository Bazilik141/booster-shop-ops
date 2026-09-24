# CAT-004 / RD-11 trust text and cart CTA correction

Date: 2026-09-24
Executor: Codex
Review gate: Claude; deployment and final QA: owner.

## Outcome

`patches/CAT-004_RD-11_trust-cart-finish_20260924.php` implements the owner's
four screenshot findings. Product trust labels are centered within their own
text areas at desktop and mobile widths. Cart checkout links have white text,
the space between the two summary buttons is reduced from 30px to 14px, and
both desktop and sticky-mobile links say `Оформити замовлення`.

Targets: product DS CSS, `checkout/cart.twig`, `checkout/cart_list.twig`, and
`common/header.twig` (CSS cache token). No stock, checkout, payment, price, DB,
or homepage trust behavior changes. No commit, push, or deployment occurred.

## Root causes verified on the live site

- On the live EB-03 product page, trust item spans computed to
  `text-align:left`. Previous work centered the SVGs vertically, but it did
  not center wrapped text lines. The new rule is scoped to
  `.bs-product__info .bs-trust-strip__item span`, so the homepage trust strip
  is unaffected. Existing icon-left markup and vertical centering remain.
- With one temporary EB-03 item in an isolated browser cart session, the cart
  checkout link computed to `rgb(30, 58, 138)` even though its inline source
  rule says white. `boostershop-ds.css` has `.bs a {color:var(--bs-blue)}`
  (specificity 0-1-1), which outranks `.bs-cart-checkout` (0-1-0). The runner
  edits the cart source rule to `#checkout-cart .bs-cart-checkout`, including
  hover and disabled selectors. It does not add `!important`.
- The live cart measured a 30px button gap: `14px` from the summary grid and
  `16px` from `.bs-cart-continue` top margin. The runner sets the summary link
  margin to zero, leaving the 14px grid gap, and retains the original 16px
  margin for the separate continue link below the cart line items.
- `cart_list.twig` contains two active `>Оформити</a>` links: summary and
  mobile sticky bar. Both are renamed. The disabled checkout labels and guard
  expressions remain untouched. The temporary browser cart item was removed;
  that session ended empty.

## Source and override history

- Owner screenshots plus live read-only DOM/CSS measurements on
  `boostershop.website/product/One-Piece-Boosters-EB-03` and the cart.
- Local source: the owner's `BS_cart_product_ui_20260924.tar.gz` and composed
  prior runners. The September 24 export did not include the current
  `checkout/cart.twig`; its exact RD-11 style block was reconstructed from
  `RD-11_cart-page_20260921.php` and matched against the live CSSOM. The new
  runner uses exact anchor counts and aborts before writing on drift.
- Trust selector history: `CAT-004_info-column-reflow_20260923.php`,
  `CAT-004_trust-credit-copy_20260924.php`, then
  `CAT-004_RD-11_mobile-trust-cart-alert_20260924.php`. Run this new patch
  after all three; it checks the most recent marker. The shared homepage
  `.bs-trust-strip` is outside `.bs-product__info`.
- Cart source: `RD-11_cart-page_20260921.php` created the shell style and
  both cart links. This runner edits those source rules. No unexplained
  `!important`, JS timer, or absolute/fixed positioning was added.

## Local validation

- PHP 8.0-compatible runner passed local `php -l` on PHP 8.3.30.
- On a copied composed fixture: four target backups, `done=ok`, self-delete;
  repeat returned `already_applied=yes`. Both edited cart Twig templates
  parsed with local Twig 3.28.0.
- A localhost fixture loaded the patched real DS stylesheet plus the patched
  cart shell style. At 320, 390, 768, and 1280px, all six trust spans
  computed to `text-align:center`; no text or page horizontal overflow. At
  390px, the two visual line fragments of each preorder label shared the
  exact horizontal center of its span. The cart CTA computed white with a
  14px gap and the new label. At 320/390px, the sticky-mobile CTA kept a 48px
  height with no text overflow. After clicking the local CTA, its hover and
  focus states remained white.
- The localhost page is a component fixture; its bank image assets were not
  included. These checks do not prove the full live page after deployment.

## Deployment boundary and rollback

Claude should review this patch together with the prior mobile-trust/cart-alert
patch. Only the owner uploads and runs it. Run it **after**
`CAT-004_RD-11_mobile-trust-cart-alert_20260924.php` has succeeded:

```bash
cd ~/public_html || exit
php CAT-004_RD-11_trust-cart-finish_20260924.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

The runner logs four backups under `_patch_backups/<patch>-<timestamp>/` and
self-deletes on success. If it reports `done=failed`, stop and obtain the
current target source. To roll back, restore those four files and clear the
template cache. Owner QA: inspect preorder and in-stock trust strips at
desktop/mobile, white CTA text at normal/hover/focus states, both checkout
labels, the new button gap, and the normal Tier 1 smoke URLs.
