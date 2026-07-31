# Claude Review Handoff — ST-2c coupon shipping-threshold refresh

Date: 2026-07-28  
Codex config: model=Sol · effort=xhigh

## Task

Review the proposed uploadable host patch before owner deployment:

`patches/ST-2c_coupon_shipping_threshold_refresh_validated_20260728.php`

The owner reported that a coupon can reduce the checkout payable amount below the free-shipping threshold, yet the checkout still displays `За наш кошт`. A browser reload did not correct it.

## Current live evidence

The owner supplied targeted live-source archives on 2026-07-28. The relevant source facts are verified from those archives:

1. `catalog/controller/checkout/coupon.php`
   - The coupon response returned `free_shipping_subtotal` from `cart->getSubTotal()`.
   - This is the pre-discount subtotal. In the reported reproduction, it remained 2050 UAH after a 10% coupon reduced the payable cart to 1845 UAH.
2. `catalog/view/javascript/checkout-reskin.js`
   - The summary card uses the server-supplied `free_shipping_subtotal` to decide whether to render `За наш кошт` / `Безкоштовна доставка застосована`.
3. `extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php`
   - `getBoosterCartTotalUah()` also used `cart->getSubTotal()` before discounts for the same free-shipping condition.
   - Pinta is intentionally display-only in the current architecture: quote `cost` is `0.0`; the actual carrier tariff is carried through `booster_display_text`.
4. `catalog/view/javascript/checkout-state.js` plus `catalog/view/template/checkout/shipping_method.twig`
   - Coupon updates previously refreshed summary/payment state only.
   - A refreshed quote for an already-selected shipping code was not saved back to the session, so the old display-only tariff could persist.

## Proposed patch behavior

The patch is limited to these four host files:

1. `catalog/controller/checkout/coupon.php`
   - Sends the checkout total calculated after coupon/discount as the free-shipping eligibility amount.
2. `extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php`
   - Calculates its threshold from the existing checkout totals after an active coupon/discount.
   - Keeps Pinta `cost = 0.0`; it does not add delivery to payable order totals.
3. `catalog/view/javascript/checkout-state.js`
   - On coupon apply/remove, preserves the selected shipping code, re-quotes it, and clears payment state for its normal re-evaluation.
   - Does not call `checkout/confirm.confirm` or another order-write endpoint.
4. `catalog/view/template/checkout/shipping_method.twig`
   - After quote refresh, re-saves the same selected quote, updating the checkout session and `booster_display_text`.

Excluded from scope: DB/schema, Hutko, Mono, fiscalization, order creation, CRM/NCRM, SimpleCheckout, `url.php`, and payment provider calculations.

## Patch safety contract

- Exact SHA-256 guards against the supplied live file versions; it refuses a changed target.
- Checks target presence and anchor count.
- Creates per-file backups in `_patch_backups/` before writes.
- Restores all changed files if either PHP syntax check fails.
- Uses `already_applied=yes` on repeat execution.
- Self-deletes after successful host execution.
- No database write.

## Local validation evidence

Performed only against an isolated temporary copy of the owner-supplied live files:

- Patch PHP syntax: passed.
- `--dry-run`: `done=ok`.
- Applied replay: four target files backed up and changed; both PHP target files passed `php -l`; `done=ok`.
- Patched `checkout-state.js`: `node --check` passed.
- Repeat replay: `already_applied=yes`.

This is not production proof. No host upload, cache clear, browser, API, payment, or live order test has occurred.

## Review request for Claude

Please inspect the patch diff and answer explicitly:

1. Does the post-discount total basis match the owner’s explicit rule: a coupon dropping the payable cart below the threshold removes free shipping?
2. Is calling existing `checkout/cart->getTotals()` inside Pinta quote evaluation safe in this OpenCart build, including the display-only `shipping_method.cost = 0.0` architecture? Flag any recursion or total-order concern.
3. Does the `resaveCurrent` branch preserve all three NP modes (warehouse, poshtomat, courier) and avoid changing the customer’s selected mode?
4. Confirm no hidden order-write call, Hutko amount change, payment method regression, or stale async-response race was introduced.
5. Review the runner contract: SHA guards, anchors, backups, rollback-on-lint, idempotency, self-delete, and cache-clear instruction.

## Owner QA required after a positive review and deployment

1. Start above 2000 UAH, select an NP mode, then apply a valid coupon below 2000 UAH. The card must stop saying `За наш кошт`, show the refreshed NP tariff/fallback, and retain the selected mode.
2. Remove the coupon. Free shipping must return without a full page reload.
3. Repeat for warehouse, poshtomat, and courier; run at least one logged-in flow.
4. Verify payment methods remain available and no order is created before the normal final action.

## Deployment status

Prepared and locally validated only. Awaiting Claude review, owner upload, cache clear, and owner QA. Do not mark ST-2c or a related roadmap task as done from this handoff.
