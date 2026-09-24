# Codex Report — PAY-002: canonical cart stock feedback and checkout timing intake

Date: 2026-08-31

## Scope

Owner requests correction of the cart warning/checkout redirect and diagnosis
and correction of the 2–4 second payment/delivery UI delay. The cart correction
is implemented and locally verified. The delay is not yet attributed; no
performance mutation is shipped without timing evidence.

## Evidence and root cause

Input: `booster-debug-cart-checkout-20260831.tar.gz` (owner export after correction),
SHA-256 `3f2c0874d7a1e2119996e1e445d904d510727539094709d18a56403f997707ff`.

- `system/library/cart/cart.php:92,282`: `stock` is a numeric available quantity.
- `system/library/cart/cart.php:246-247,283`: `stock_status` is false if the cart
  requires more stock than available. Options also affect it (lines 138,171).
- `system/library/cart/cart.php:745`: existing `hasStock()` reads `stock_status`
  and preserves the explicit preorder exception (`stock_status_id=8`).
- `system/library/cart/cart.php:774`: `hasMinimum()` returns false for a failed
  product minimum. `minimum_status=true` means valid, not an error.
- `catalog/controller/checkout/cart.php:82` and `checkout.php:22` erroneously
  read numeric `stock` as a Boolean. A positive available quantity of 5 cannot
  flag a requested quantity of 8. The previous two hint runners preserved that
  faulty input, which explains why their syntax/assertion passes did not prove
  correct user behavior.
- `shipping_method.php:49,98` and `payment_method.php:72,302` use the correct
  `hasStock()` and redirect invalid carts. Thus the entry controller and cart
  hint disagreed with later checkout requests. `confirm.php:42` uses the same
  canonical availability gate.
- `cart.twig:93-100,153-155`: the existing cart update reloads `cart.list`; no
  additional timer or client flag is needed to clear the warning after correction.

## Files touched

```text
patches/PAY-002_cart-canonical-stock_20260831.php
scripts/tests/pay002-cart-canonical-stock.test.php
scripts/pay002-checkout-timing.js
diagnostics/PAY-002_cart-canonical-stock-and-checkout-timing_report_20260831.md
```

Production targets (only three):

1. `catalog/controller/checkout/cart.php`: use `!Cart::hasStock()` and
   `!Cart::hasMinimum()`; correct existing row minimum-error polarity.
2. `catalog/controller/checkout/checkout.php`: use the same canonical stock
   gate before entering checkout. Payment modal session logic is unchanged.
3. `catalog/view/template/checkout/cart_list.twig`: correct stock/option/zero
   availability wording; show real minimum failures separately; disable CTA
   only for actual checkout blockers; avoid duplicate generic stock alerts.

No canonical library edits, stock policy changes, database writes, CSS edits,
timers, payment selection changes, bank calls, or order creation.

## Exact source guards

| Target | Before SHA-256 | After SHA-256 |
|---|---|---|
| cart.php | `9d5900eab3ed4590aeb13f2819ba4712be1eb55bc9dd8aac8dbb572a2da0c667` | `9d0bd9b2ffe271ce774dac2797783c80c15091ff0291a0e53d5794cf069e84d5` |
| checkout.php | `96ef34ad3c0c2b3c980d6ffe54953da3f66e2cd1320f9f549305f5c3f98289c9` | `dbf4b8b98c5be25286c7e44254484bb78830179092c2340c50f3a81b2949c3a2` |
| cart_list.twig | `0bd7fcfd597742bb2838cf9211deb4b3f732a638cdf582c48e8f72533bbcb3ab` | `0c264b8676e8d7e8f97e59cab9d02b204005d1b1cbf4d3c8659a1df1d160fbec` |

The runner additionally SHA-checks `system/library/cart/cart.php` read-only.
Every replacement anchor and every candidate hash is checked before writes.
It refuses mixed/partial source states. Replacement specifications are encoded
in the self-contained runner to preserve exact source bytes.

## Local fixture result

The unchanged shipping runner was executed on a copy of the fresh owner archive.
The regression harness executes the actual cart/checkout controllers and the
actual Cart::hasStock()/hasMinimum() methods; services and cart rows are stubbed,
so no database or bank is accessed. It fails against the original deployed
controller on `exceeds-positive-stock: stock warning`, then passes after patch.

```text
PASS clean archive + exact after SHA + self delete + repeat
PASS source drift refused before backup/write
PASS injected PHP syntax fault restores ALL three original files
PASS regression harness detects original deployed error
PASS exceeds-positive-stock
PASS corrected-to-available
PASS zero-stock
PASS option-shortage
PASS stock-checkout-allowed
PASS preorder-preserved
PASS genuine-minimum
PASS stock-and-minimum
PASS mixed cart
PASS fragment recovery
```

Rollback fault injection changed only the test copy's transformation payload;
the shipping artifact does not include that fault. Test driver/build artifacts
and original sources remain isolated under `work/pay002-cart-perf/`, not staged.

## Syntax results

PHP runner and both generated PHP targets pass local PHP 8.3 lint. Runner syntax
uses PHP 8.0-compatible features; production PHP 8.0 lint is enforced during
owner execution. The timing collector passes `node --check`.

## Idempotency and rollback

Exact three-file post-image returns `already_applied=yes` and self-deletes.
Backup: `_patch_backups/PAY-002_cart-canonical-stock_20260831-<timestamp>-<suffix>/`.
Restore all three target files from that directory and clear template cache.
No database rollback is needed. Note: rollback reintroduces the known hint bug.

## Checkout latency — confirmed vs unknown

### Owner timing update, 2026-08-31 12:34 UTC

This new evidence supersedes the earlier timing gap below. The owner identified
`checkout/shipping_method.quote`: starts about 983 ms after navigation, waits
4.18 seconds for the server response, total about 4.19 seconds. Queueing is
1.83 ms, stalled 2.02 ms, download under 1 ms. Initiator is the expected
`checkout-state.bootstrap -> bsCheckoutLoadShippingMethods -> chain -> AJAX`.
Thus this observed delay is in the shipping request's server-response path,
not a multi-second client queue. Payment method fetching depends on shipping
quote/save and is delayed downstream. No cookie/header values are retained here.

`catalog/controller/checkout/shipping_method.php:73` delegates to
`model_checkout_shipping_method->getMethods(address)`.
`extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php:46,79`
calls `getDocumentPrice()` synchronously. At line 430 it can call Nova Poshta's
`InternetDocument/getDocumentPrice` when API pricing is enabled. Local city/
warehouse lookups and totals/coupon calculations also execute in that path.
The screenshot alone cannot assign all 4.18 seconds to the external API.

The code also computes the tariff before replacing its display with
`За наш кошт` for qualifying orders. Skipping that unnecessary calculation is
a potential bounded optimization, but would not fix an uncached paid-shipping
request by itself. Do not ship it as a complete latency fix.

Required remaining source: `catalog/model/checkout/` and Pinta's
`system/library/` plus `catalog/model/module/`. These were not included in the
first archive command. Inspect timeout/retry/cache and lookup behavior before
choosing the fix; add bounded timing instrumentation if source does not identify
the elapsed-time owner. The previous browser timing collector is no longer
required for locating this observed slow request.

The screenshots show visible shop XHR durations around 35–43 ms; selected
Clarity analytics request is about 380 ms. Names are truncated and the
navigation-relative start/waterfall is absent. This does not establish a bank,
shipping API, or backend bottleneck and does not rule out an earlier slow request.

Current client flow:

```text
document-ready -> checkout-state.bootstrap
  saved shipping -> confirm read + payment methods
  no shipping    -> shipping quote -> shipping save -> payment methods
```

`checkout-state.js:400` defers bootstrap to jQuery document-ready. The script
tags precede `{{ footer }}` in `checkout.twig`. Parsing/blocking scripts later
in the document, the shared request queue, or DOM render work are candidates,
not confirmed causes. The queue does not introduce a fixed 2–4 second timer.
`checkout-reskin.js:1594` also documents an existing mutation-observer stream;
changing it without a measured trace would risk unrelated checkout behavior.

Prepared `scripts/pay002-checkout-timing.js` is a read-only DevTools collector.
It returns navigation timing, allowlisted route names, script timing, and a
payment input count. It omits query parameters, request/response bodies,
customer data, cookies, tokens, and payment codes. The owner should run it
after reloading a valid checkout and send the JSON output. It is NOT uploaded
to public_html and does not change the page or send data anywhere.

## Run command (owner)

Upload the PHP runner only, then:

```bash
cd ~/public_html || exit
php PAY-002_cart-canonical-stock_20260831.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA and remaining gates

- [ ] Quantity 8 with available 5: stock warning, unavailable CTA under current
  stock-checkout-disabled policy; no inaccurate minimum message.
- [ ] Quantity corrected to 5: after the existing fragment refresh the warning
  clears and the normal checkout link works.
- [ ] Valid Mono and PUMB modal selections retain their selected term.
- [ ] Cart at 375/768/1440 widths, long product names and warning text; enabled
  CTA hover/focus and blocked CTA non-navigation. No browser-layout proof is
  claimed by this local controller test.
- [ ] Normal Tier 1 site smoke; do not submit a bank order.
- [ ] Capture timing JSON to finish latency diagnosis before choosing a fix.

Owner deployment/QA remains pending. No commit, push, deployment, or task-status
mutation was performed. The overall two-problem request remains incomplete
until latency is attributed and corrected.
