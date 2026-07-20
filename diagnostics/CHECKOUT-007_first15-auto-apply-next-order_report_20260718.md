# CHECKOUT-007 — First15 auto-apply on the actual next order

Date: 2026-07-18

## Scope

Implements the handoff only for accounts created through the guest-during-checkout shortcut. The current registration order retains its full pre-confirm total; the first later checkout auto-applies First15 through the existing coupon summary endpoint.

## Files touched

```text
catalog/controller/checkout/payment_method.php
catalog/controller/checkout/coupon.php
catalog/model/checkout/booster_coupon.php
catalog/view/template/checkout/success.twig
```

## Runtime data effect

No schema migration or bulk query. A newly created shortcut account gets `customer.custom_field.bs_first15_pending = 1`. The new coupon model method removes only that JSON key after a successful First15 application, or if the existing order-usage guard says First15 was already used. All other custom fields are preserved.

## Dry-run result

```text
changed_files=4
php_lint=ok file=catalog/controller/checkout/payment_method.php
php_lint=ok file=catalog/controller/checkout/coupon.php
php_lint=ok file=catalog/model/checkout/booster_coupon.php
done=ok
```

## Idempotency and rollback

- A repeat run returns `already_applied=yes` with no rewrite.
- The runner backs up all four files to `_patch_backups/CHECKOUT-007_first15-auto-apply-next-order_20260718-<timestamp>/` before edits.
- Restoring those files disables auto-apply. Any remaining pending custom-field key is harmless because stock code ignores it.

## Run command (owner)

```bash
cd ~/public_html || exit
php CHECKOUT-007_first15-auto-apply-next-order_20260718.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] Guest + save-data + COD/Hutko: order 1 keeps the full confirm-panel total; the offer text promises automatic application next time.
- [ ] In a fresh later checkout with products, First15 appears in the promo summary before confirmation without entering a code.
- [ ] Complete that discounted order; verify the customer custom field no longer has `bs_first15_pending` and a third checkout receives no automatic discount.
- [ ] A manually entered non-First15 coupon is never replaced.
- [ ] Guest checkout without account creation and standalone `checkout/register` remain unchanged.
- [ ] Run `bs-checkout-smoke`; record max order ID and status-0 draft count before/after.

## Risk

Checkout/coupon behavior plus a per-customer JSON custom-field update. No `confirm.confirm`, Hutko amount/signature, NP validation, order status, or schema logic is changed. If the coupon is disabled or a cleanup update fails, checkout remains usable; the automatic offer is retried later rather than surfacing a blocking error.
