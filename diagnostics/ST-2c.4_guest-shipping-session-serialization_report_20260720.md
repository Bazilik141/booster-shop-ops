# Codex Report — ST-2c.4: guest paid-shipping summary continuity

Date: 2026-07-20

## Scope

Implemented against the fresh owner archive
`booster-debug-ST2c4-guest-shipping.tar.gz` (SHA-256
`53FA007190B5DDEF2023B241E207511C77C7723A465063C7DBF259DC2E245FF9`).

The patch fixes two reported checkout regressions:

1. Guest coupon/register session traffic is serialized with shipping writes, and
   the paid-shipping summary is returned by the same request that stores the
   selected shipping quote. The normal save path no longer needs a second totals
   request. Coupon queue steps resolve after both AJAX success and failure so the
   global `.done()`-driven chain cannot be frozen by a failed coupon request.
2. The duplicated delivery-address line is removed from the Receiver card; the
   delivery address remains in the Delivery card.

No Nova Poshta tariff, free-shipping threshold, payment-method, order-write, or
database logic is changed.

## Root cause evidence

- `checkout-reskin.js` sent `checkout/coupon.*` outside the existing checkout
  `chain`, while guest `checkout/register.save` and shipping quote/save used that
  chain. The guest-only coupon refresh could therefore overlap the shipping
  session write.
- `catalog/view/javascript/common.js` from the newest full cPanel backup available
  locally defines `Chain.attach()` as FIFO append and starts execution only when
  `start === false`; attaching inside an active request therefore does not recurse.
  Its executor advances only from `jqxhr.done()`, so ST-2c.4 returns an
  always-resolved Deferred wrapper for coupon queue steps. This prevents a coupon
  HTTP failure from stalling the following shipping/payment work without changing
  the global theme queue.
- The newest full cPanel backup available locally
  (`backup-7.19.2026_09-58-50_boosters.tar.gz`) confirms why that overlap loses
  data: `system/library/session.php` reads the full session at request start and
  writes it only from a shutdown handler; `system/library/session/db.php` then
  uses `REPLACE INTO` for the entire JSON blob, without locking or field-level
  merge. The last finishing request therefore overwrites the other request's
  newer `shipping_method` value.
- `shipping_method.save` stored `session.shipping_method`, then the coordinator
  separately requested `checkout/confirm`. A later guest session response could
  leave that confirm request without `session.shipping_method`, producing the
  visible dash even though the client radio remained selected.
- The logged-in flow does not run the guest register/coupon sequence, matching
  the reported guest-only symptom.
- The duplicated Receiver address was emitted directly by
  `checkout-reskin.js` as `data-co-receiver-address` and then populated from the
  selected delivery address.

## Files touched

The runner changes these live files:

```text
catalog/controller/checkout/shipping_method.php
catalog/view/javascript/checkout-state.js
catalog/view/javascript/checkout-reskin.js
catalog/view/template/checkout/shipping_method.twig
catalog/view/template/checkout/checkout.twig
```

Deliverables:

```text
patches/ST-2c.4_guest-shipping-session-serialization_20260720.php
diagnostics/ST-2c.4_guest-shipping-session-serialization_report_20260720.md
```

## Dry-run result

Executed on an exact extracted copy of the supplied archive:

```text
changed=catalog/controller/checkout/shipping_method.php
changed=catalog/view/javascript/checkout-state.js
changed=catalog/view/javascript/checkout-reskin.js
changed=catalog/view/template/checkout/shipping_method.twig
changed=catalog/view/template/checkout/checkout.twig
php_l=ok
done=ok
```

Contract checks also confirmed:

```text
JS syntax: checkout-state.js = ok
JS syntax: checkout-reskin.js = ok
shipping summary handoff: controller -> Twig -> coordinator = ok
summary is rendered after session.shipping_method is written = ok
data-co-receiver-address removed = ok
chain contract: FIFO append, active attach is non-recursive, done-only advance = confirmed
coupon queue failure settlement = ok
```

Hash-mismatch fixture:

```text
error=sha256_mismatch:catalog/view/javascript/checkout-state.js
exit_code=1
safe_fail_no_writes=ok
```

## php -l result

```text
No syntax errors detected in ST-2c.4_guest-shipping-session-serialization_20260720.php
No syntax errors detected in catalog/controller/checkout/shipping_method.php
```

## Idempotency

Re-running against the patched fixture returns:

```text
already_applied=yes
```

The production runner self-deletes only after `done=ok`; the local fixture used
a fresh copy of the runner for the idempotency check.

## Rollback

The runner backs up every target before the first write to:

```text
_patch_backups/ST-2c.4_guest-shipping-session-serialization_20260720-<ts>/
```

Restore the five matching relative paths from that directory, then clear the
OpenCart cache. No DB rollback is required.

## Run command (owner)

```bash
cd ~/public_html || exit
php ST-2c.4_guest-shipping-session-serialization_20260720.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] Guest, cart below 2000 UAH: fill recipient and each of the three NP address
      flows; after shipping selection the summary shows the paid amount, not a dash.
- [ ] Guest: edit email, recipient name/phone, region/city/warehouse or courier
      address; the summary follows the final selected shipping method without a
      page reload.
- [ ] Guest: apply and remove a coupon after shipping is selected; shipping price
      and grand total stay present and recalculate once.
- [ ] Logged-in checkout: all three NP delivery modes still show their correct
      paid/free price and payment choices remain functional.
- [ ] Receiver card contains only receiver fields/status; no delivery-address
      duplicate is rendered below them.
- [ ] Complete one test order and confirm checkout success, admin order total,
      and CRM readback contain the same shipping cost.

## Side effects / risks

- Checkout/NP is a high-risk area; owner production smoke testing is required
  before marking ST-2c Done.
- `shipping_method.save` response becomes larger because it includes the existing
  read-only confirm HTML. It removes the normal follow-up totals request, so the
  total request count does not increase.
- Compatibility fallback remains: if an older/custom server response lacks
  `summary_html`, the coordinator performs the existing read-only totals refresh.
- The global `Chain` itself remains unchanged. Existing non-coupon queue callbacks
  still inherit its historical done-only error behavior; ST-2c.4 ensures the newly
  queued coupon callback always resolves after `complete`, including AJAX errors.
- No `setTimeout`, CSS override, `!important`, DB write, or order-write endpoint
  was added.
