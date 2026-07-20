# Codex Report — CHECKOUT-004: promo code in the new checkout

Date: 2026-07-15

## Scope

The fresh live archives showed that the `checkout/coupon.summary` / `.apply` endpoint described in the handoff was absent. This patch restores the ST-2b.5 runtime coupon/First15 endpoint and replaces only the RD13 promo stub in the new checkout. It does not change the ST-2c cutover, old SimpleCheckout, free-shipping RD13-STUB, shipping, payment, fiscalization, or order-write gates.

## Files touched

```
patches/CHECKOUT-004_promo-code-new-checkout_20260715.php
catalog/controller/checkout/register.php              — queues First15 after a new checkout account
catalog/controller/checkout/confirm.php               — enables coupon total for read-only preview
catalog/view/template/checkout/checkout.twig          — response-only summary bridge, First15 refresh, JS cache version
catalog/view/javascript/checkout-reskin.js            — replaces promo stub with summary/apply/remove UI
catalog/model/checkout/booster_coupon.php             — new runtime coupon / First15 helper
catalog/controller/checkout/coupon.php                — new JSON summary/apply/remove endpoint
```

DB changes: none. Runtime only reads `order` and `order_total` to block reuse of First15 after an order with `order_status_id > 0`.

## Dry-run result

The patch was executed in an isolated fixture assembled from the owner-provided 2026-07-15 live archives.

```
changed_files=6
done=ok
promo stub names=0
free-shipping RD13-STUB markers=2
promo-side checkout/confirm.confirm calls=0
```

## php -l result

```
No syntax errors detected in catalog/controller/checkout/register.php
No syntax errors detected in catalog/controller/checkout/confirm.php
No syntax errors detected in catalog/model/checkout/booster_coupon.php
No syntax errors detected in catalog/controller/checkout/coupon.php
```

## Idempotency

Re-running the patch in the same fixture returned:

```
already_applied=yes
changed_files=0
done=ok
```

## Rollback

The patch backs up every existing changed target before writing:

```
_patch_backups/CHECKOUT-004_promo-code-new-checkout_20260715-<timestamp>/
```

Restore the backed-up files from that directory and remove the two new files only if they were introduced by this patch:

```
catalog/model/checkout/booster_coupon.php
catalog/controller/checkout/coupon.php
```

Then clear `DIR_CACHE` cache and template files using the command below.

## Run command (owner)

```bash
cd ~/public_html || exit
php CHECKOUT-004_promo-code-new-checkout_20260715.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] New checkout, guest and logged-in: valid coupon → discount row and total change; `oc_order` count and status-0 draft count remain unchanged.
- [ ] Remove coupon → original total returns and promo UI returns to the empty state.
- [ ] New-account registration: First15 appears after `register.save`; account/email with a prior completed order gets the existing inline reuse error.
- [ ] Invalid coupon shows one inline error; Network has only `checkout/coupon.summary|apply|remove` for promo actions, never `checkout/confirm.confirm`.
- [ ] `rd13_stub_coupon` and `data-co-promo-stub` are absent; the free-shipping progress block remains present.
- [ ] Complete the 11-step `bs-checkout-smoke` on the new checkout before marking Done.

## Side effects / risks

Checkout/order-total risk is high, but limited to coupon session state and a read-only totals summary. No schema/data write, payment-signature, shipping-quote, or explicit order-write path is changed. Browser JS cache version is intentionally bumped to `checkout004-20260715`.
