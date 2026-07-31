# Codex Report — ST-2c: mini-cart shipping threshold alignment

## Scope

Repair the two remaining discrepancies after a mini-cart cart change: Pinta's authoritative free-shipping quote and the checkout summary progress text.

## Root cause

The supplied live `catalog/model/checkout/cart.php` states that OpenCart's magic model proxy cannot pass referenced parameters through a direct method call; `getTotals()` must be invoked as its callable property. Pinta had been changed to a direct call, so its referenced `$total` was not reliably populated and the quote could remain paid above the threshold.

Separately, `checkout-reskin.js` always preferred `window.bsCheckoutFreeShippingRule.subtotal`, which comes from an earlier coupon-summary response. After mini-cart change, `shipping_method.save` supplied a fresh server-rendered summary, but the progress widget continued to display the old subtotal.

## Files touched

- `extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php`
- `catalog/view/javascript/checkout-reskin.js`

## Change

Pinta now invokes `($this->model_checkout_cart->getTotals)($totals, $taxes, $total)` to preserve pass-by-reference semantics. The summary widget now prefers the freshly rendered checkout grand total, whose shipping cost is display-only zero, and only falls back to the prior coupon-summary subtotal when a fresh total is unavailable.

## Guard and rollback

Both files have exact current SHA-256 guards and one required anchor. Before writing, the runner copies them to `_patch_backups/`. Restore those copies to roll back.

## Local validation

Used the supplied 2026-07-29 cPanel snapshot.

- runner `--dry-run`: passed;
- isolated apply: passed, two backups created and two files changed;
- `php -l` on Pinta: passed;
- `node --check` on `checkout-reskin.js`: passed;
- static assertions: callable `getTotals` and fresh rendered payable-total branch present;
- repeat runner: `already_applied=yes` and self-deleted;
- empirical cPanel Loader/Proxy harness: direct `$proxy->getTotals(...)` yielded total `0` with no referenced outputs; callable `($proxy->getTotals)(...)` yielded total `2100`, one total row and one tax entry.

## JavaScript scope proof

In the patched isolated fixture, uildSummaryView() starts at line 1169 and closes at line 1323. ar grand = null; is declared at line 1218; the new enderedPayableTotal = grand ? ... use is at line 1267. A Node scope-check verified the declaration and use are both inside that same function, and 
ode --check passed.

## Owner QA

1. For each Nova Poshta mode, change mini-cart quantity from below ₴2000 to above it and back.
2. Confirm the delivery text and progress message switch together without page reload.
3. Repeat with a coupon that brings the payable amount below ₴2000.
4. Verify a rapid quantity change or removal does not restore stale delivery text.
5. Confirm the final order total remains product total minus discount; Pinta remains display-only.
