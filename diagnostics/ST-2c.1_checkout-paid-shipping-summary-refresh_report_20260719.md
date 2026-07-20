# ST-2c.1 — checkout paid-shipping summary refresh

Date: 2026-07-19  
Scope: paid Nova Poshta shipping is calculated correctly but displayed as `—` / omitted in the checkout sidebar until the success page.

## Evidence and diagnosis

- Current live runtime archive: `booster-debug-ST2c1-runtime.tar.gz`.
- The current Pinta Nova Poshta model sets each NP quote's `cost` to `getBoosterShippingCost($shipping_cost_default)` and returns the non-zero Nova Poshta API tariff below the configured threshold.
- The successful order shown by the owner contains an NP line of **₴93.50** and a total of **₴793.50** for a ₴700 product. This proves the selected quote reaches the final order correctly.
- `checkout-reskin.js` renders the sidebar from the existing cached/deferred confirm summary. It shows `—` when that table has no current shipping row.
- The same file already uses `checkout/coupon.summary` to refresh that cached summary without calling `checkout/confirm.confirm`.
- OpenCart's `checkout/shipping_method.save` stores the selected quote in the session and returns `success`; the reskin previously did not refresh its cached summary after that success response.

## Change

`patches/ST-2c.1_checkout-paid-shipping-summary-refresh_20260719.php` changes only:

- `catalog/view/javascript/checkout-reskin.js`

It adds a namespaced `ajaxSuccess` listener. Only after a successful `checkout/shipping_method.save`, it schedules the existing quiet `checkout/coupon.summary` refresh. That updates the cached checkout total and sidebar. It does not call `checkout/confirm.confirm`, create an order, modify coupons, change Nova Poshta pricing, Hutko, DB, or CRM.

## Local verification

- Fresh target shape verified from the supplied runtime archive.
- Exact insertion anchor count: 1.
- Patch `php -l`: required before success; the patch restores its source backup if this gate fails.
- Idempotency: a second run reports `already_applied=yes` and writes nothing.

## Rollback

Restore `catalog/view/javascript/checkout-reskin.js` from the timestamped directory printed as `backup=...` under `_patch_backups/`.

## Run command

```bash
cd ~/public_html || exit
php ST-2c.1_checkout-paid-shipping-summary-refresh_20260719.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA

1. In a guest checkout below ₴2,000, complete the NP address fields and choose a warehouse / poshtomat / courier method.
2. Wait for the method selection to complete; the sidebar must show the actual paid shipping amount instead of `—` and total must equal goods plus shipping.
3. Change between NP methods; the displayed shipping/total must update each time.
4. At or above ₴2,000 pre-discount subtotal, shipping must display ₴0.
5. Place one test order and verify success page equals the checkout sidebar total.
6. Confirm no extra draft/order appears before the explicit “Підтвердити замовлення” action.
