# Codex Report — RD-12: checkout thumbnail compatibility

Date: 2026-09-22

## Scope

Restore checkout summary product thumbnails after RD-12 replaced the header
mini-cart markup. The patch changes only the DOM selector in
`checkout-reskin.js` and refreshes that asset's existing cache token in the
checkout template. It does not alter cart data, prices, totals, order creation,
payment, or shipping.

## Root cause and evidence

- The 2026-09-07 live snapshot has legacy product markup:
  `.mini-cart-thumb > img.img-thumbnail` in `common/cart.twig`.
- `checkout-reskin.js` searches only
  `#cart .mini-cart-thumb img, #cart img.img-thumbnail` and emits an image only
  when that query finds a matching product.
- The current RD-12 live source uses
  `.bs-mini-cart__row > a > img`, without either legacy class. Therefore the
  lookup returns no thumbnail and the checkout summary renders no image.

## Files touched by the runner

```
catalog/view/javascript/checkout-reskin.js
catalog/view/template/checkout/checkout.twig
```

## Safety

- Both target files and exact anchors must be present exactly once.
- The runner reads, validates, and replaces the existing checkout-reskin cache
  token wholesale; it never assumes its prior value.
- Backup is created under `_patch_backups/<patch>-<timestamp>/` before writes.
- Any post-write verification failure restores written targets.
- A complete repeat returns `already_applied=yes` and removes the uploaded runner.

## Local validation

- `php -l patches/RD-12_checkout-thumbnail-compat_20260922.php`
- Exact selector and cache-reference anchors checked against the local checkout
  source evidence.

## Owner deployment and QA

Run from `~/public_html`, then clear OpenCart caches.

- [ ] Open checkout with at least two different cart products: each order-summary row shows its own image.
- [ ] Change quantity or remove an item: image, name, quantity and price remain aligned after checkout summary refresh.
- [ ] Open cart and checkout entry URLs; verify no Twig/PHP error page.
- [ ] Complete only normal manual checkout smoke until the final confirmation step; do not create a test order solely for this visual change.

## Rollback

Restore the two files from the runner's printed `_patch_backups/...` directory,
then clear OpenCart caches.
