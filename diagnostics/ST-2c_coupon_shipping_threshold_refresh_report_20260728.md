# ST-2c — coupon shipping threshold refresh

Date: 2026-07-28  
Risk: checkout / Nova Poshta; owner deployment and browser QA required.

## Confirmed root cause

- `catalog/controller/checkout/coupon.php` returned `free_shipping_subtotal` from `cart->getSubTotal()`, so the reskin continued to use the pre-coupon amount (for example 2050 UAH) after a coupon reduced the payable cart to 1845 UAH.
- `extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php` used the same pre-discount subtotal for its `За наш кошт` decision.
- After a quote refresh, `catalog/view/template/checkout/shipping_method.twig` retained the selected method but did not re-save its refreshed quote to the checkout session.

## Patch scope

- `catalog/controller/checkout/coupon.php`: exposes the payable, post-discount total to the checkout reskin.
- `extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php`: calculates free-shipping eligibility from the configured checkout totals after the active coupon; Pinta remains display-only (`cost = 0`).
- `catalog/view/javascript/checkout-state.js` and `catalog/view/template/checkout/shipping_method.twig`: after coupon apply/remove, retain the selected delivery option, re-quote it, and save the fresh display tariff back to session.

No DB, payment provider, Hutko, order-write, SimpleCheckout, fiscalization, CRM, or routing changes are included.

## Local validation

- PHP syntax: patch, `checkout/coupon.php`, and Pinta shipping model passed.
- JavaScript syntax: patched `checkout-state.js` passed `node --check`.
- Isolated replay against the supplied live snapshots: dry-run passed; application created backups and completed with `done=ok`; second run returned `already_applied=yes`.

## Owner QA after deployment

1. Cart just above 2000 UAH, selected NP warehouse: apply a coupon below 2000. The card must stop saying `За наш кошт`, show the refreshed NP tariff/fallback, and keep warehouse selected.
2. Remove the coupon: free-delivery status must return without a page reload.
3. Repeat for poshtomat and courier, plus one logged-in checkout.
4. Complete one non-payment test order only if the owner can safely do so; ensure the chosen NP method and displayed tariff match the order summary.

## Rollback

Restore the four changed files from the patch-created `_patch_backups/ST-2c_coupon_shipping_threshold_refresh_validated_20260728-<timestamp>/` directory. No database rollback is needed.
