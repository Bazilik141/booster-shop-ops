# Codex Report — ST-2c: mini-cart shipping re-quote

## Scope

Fix the stock checkout state transition after a successful mini-cart quantity update or item removal. The selected delivery method remains selected, but its fresh quote must be saved back to the checkout session.

## Root cause

`common/cart.twig` emits `bs:cart-updated` after its mini-cart fragment reload. `checkout-state.js` receives the event, but `cartChanged()` previously called `addressSaved()`. That path quotes delivery with `autoSelect: true`; when the same method is already selected, `shipping_method.twig` does not call `saveShipping()`. The stale `booster_display_text` therefore remains in the checkout session.

## Files touched

- `catalog/view/javascript/checkout-state.js`

## Change

`cartChanged()` now follows the already established coupon behavior: it increments the revision, clears only the transient shipping display/payment state, then calls `bsCheckoutLoadShippingMethods()` with `autoSelect: false` and `resaveCurrent: true`. The current quote is consequently persisted through the existing `checkout/shipping_method.save` path and returns an authoritative fresh checkout summary.

## Guard and rollback

The runner accepts only SHA-256 `C291F81EE26E354CE51C34BA7C8694FA4F0CACCE46263B79509A989DA7EDFA6A`, checks one exact `cartChanged()` anchor, and backs up the target beneath `_patch_backups/` before writing. Restore that backed-up file to roll back.

## Local validation

Used the fresh cPanel snapshot from `booster-minicart-shipping-refresh-debug.tar.gz`.

- runner `--dry-run`: passed;
- runner apply in an isolated fixture: passed, backup created, one file changed;
- `node --check catalog/view/javascript/checkout-state.js`: passed;
- repeat runner: `already_applied=yes` and self-deleted;
- static assertion: the patched `cartChanged()` calls the existing shipping loader with `resaveCurrent: true`.

## Owner QA

1. Select each Nova Poshta mode, start above the free-shipping threshold, then reduce mini-cart quantity below it.
2. Confirm the delivery box changes from `За наш кошт` to the applicable tariff text without a page reload.
3. Increase back above the threshold and confirm `За наш кошт` returns.
4. Repeat with an active coupon, rapid consecutive quantity edits, and mini-cart removal.
5. Confirm payment options and final order total remain correct; do not place a production test order unless intended.
