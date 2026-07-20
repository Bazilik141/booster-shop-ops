# CHECKOUT-006 — First15 offer on the next order

Date: 2026-07-17

## Scope

Implements the handoff exactly: the guest account creation path no longer applies First15 to the order currently being confirmed. It stores only a one-time success-page flag. The existing CHECKOUT-005 Nova Poshta validation is preserved unchanged.

## Files touched

```text
catalog/controller/checkout/payment_method.php
catalog/controller/checkout/success.php
catalog/view/template/checkout/success.twig
```

## Dry-run result

```text
changed_files=3
php_lint=ok file=catalog/controller/checkout/payment_method.php
php_lint=ok file=catalog/controller/checkout/success.php
done=ok
```

## PHP syntax and idempotency

- `php -l` runs for both changed controllers and restores every changed file on failure.
- A repeat run returns `already_applied=yes` without rewriting files.

## Rollback

The runner creates `_patch_backups/CHECKOUT-006_first15-next-order-message_20260717-<timestamp>/` before editing. Restore all three saved files, then clear `DIR_CACHE` cache/template entries.

## Run command (owner)

```bash
cd ~/public_html || exit
php CHECKOUT-006_first15-next-order-message_20260717.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] Guest + “save my data” + COD: confirm-panel and final order totals match; no First15 row in current order.
- [ ] Same flow through Hutko: amount sent to Hutko equals the displayed/full order total.
- [ ] First success render shows the next-order First15 message once; plain guest checkout does not.
- [ ] Later, manually enter First15 for that newly created account: it applies successfully.
- [ ] Standalone registration remains unchanged: First15 is still applied/displayed before confirmation.
- [ ] Run `bs-checkout-smoke`; record max order ID and status-0 draft count before/after.

## Side effects / risks

Checkout and success-page only; no DB schema/data changes. The courtesy message is deliberately one-time and does not persist through Hutko reload recovery. Coupon backend, order-write gate, Hutko amount logic, and Nova Poshta validation are untouched.
