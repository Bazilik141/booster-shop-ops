# ST-2c.2 — checkout shipping-summary race fix

Date: 2026-07-19  
Scope: checkout sidebar must refresh to the newly saved paid Nova Poshta shipping quote after changing the delivery address or NP delivery mode.

## Evidence and diagnosis

Live archive `booster-debug-ST2c2-address-flow.tar.gz` confirms the sequence:

1. `shipping_address.php` clears `shipping_method` and `shipping_methods` whenever the address changes.
2. `checkout.twig` reacts to successful `register.save` by immediately calling `bsCheckoutRefreshPromoCouponSummary()`, then waits 250 ms to reload/auto-select shipping methods.
3. `shipping_method.twig` obtains quotes through `checkout/shipping_method.quote`, then saves the selected quote through `checkout/shipping_method.save`.
4. ST-2c.1 correctly calls the same quiet summary refresh after that save. However, the promo-summary client has a `busy` guard: when the earlier address-triggered summary is still in flight, this later refresh is discarded rather than deferred.

This explains the observed result: Ctrl+R sees an already persisted quote, while an address change can leave the sidebar at `—` if the first summary response wins and the newer refresh is dropped.

## Change

`patches/ST-2c.2_checkout-shipping-summary-race-fix_20260719.php` changes only `catalog/view/javascript/checkout-reskin.js`.

It changes the existing promo-summary client so a summary request requested while `busy` is set is coalesced into one quiet follow-up request after the active request completes. Coupon apply/remove actions retain their existing single-flight behavior. No database, Nova Poshta tariff calculation, Hutko, CRM, shipping controller/template, or order-confirm endpoint is changed.

## Local verification

- Latest live archive verified to include the ST-2c.1 listener and the exact `busy` guard.
- State and request anchors are each required exactly once before writing.
- Patch PHP lint is a success gate; a failure restores the file backup.
- The patch is idempotent: repeat runs print `already_applied=yes`.
- The changed JavaScript is checked with `node --check` in a fixture based on the supplied live `checkout-reskin.js`.

## Rollback

Restore `catalog/view/javascript/checkout-reskin.js` from the timestamped `_patch_backups/ST-2c.2_checkout-shipping-summary-race-fix_20260719_<timestamp>/` directory printed by the patch.

## Run command

```bash
cd ~/public_html || exit
php ST-2c.2_checkout-shipping-summary-race-fix_20260719.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA

1. Guest checkout below ₴2,000: choose a full NP warehouse address; sidebar must show the paid quote and goods plus shipping.
2. Change the NP address, wait for the automatic method selection; the sidebar must replace `—` with the new paid quote without Ctrl+R.
3. Switch warehouse, poshtomat, and courier; each save must refresh the sidebar/total.
4. Cross the ₴2,000 pre-discount threshold; sidebar shipping must become ₴0.
5. Place one test order and confirm checkout and success-page totals agree.
6. Confirm no order is created before the explicit confirm action.
