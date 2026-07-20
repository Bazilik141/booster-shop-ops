# CHECKOUT-007A — shared success First15 message fallback

Date: 2026-07-18

## Scope

Fixes the verified live gap where the shared `success.twig` already contains the First15 message but `show_first15_offer` is false in a non-Hutko success render. No coupon, payment, Hutko, Nova Poshta, or order-total logic changes.

## File touched

```text
catalog/controller/checkout/success.php
```

## Behavior

The existing one-time session flag remains first priority. Only when it is absent, the controller reads the durable `bs_first15_pending` customer custom field and displays the message if all three conditions hold:

1. `checkout001_account_processed` is `created` in this checkout session;
2. the displayed order belongs to that shortcut-created customer;
3. the durable First15 pending key still exists.

This covers COD, bank transfer/requisites, and Hutko through the same shared controller, without showing the message to normal guest, standalone registration, or later discounted orders.

## Dry-run result

```text
changed_files=1
php_lint=ok file=catalog/controller/checkout/success.php
done=ok
```

## Idempotency and rollback

- Re-run prints `already_applied=yes`.
- Backup: `_patch_backups/CHECKOUT-007A_first15-success-message-fallback_20260718-<timestamp>/catalog/controller/checkout/success.php`.
- Restore that file and clear OpenCart cache/template cache to roll back.

## Run command (owner)

```bash
cd ~/public_html || exit
php CHECKOUT-007A_first15-success-message-fallback_20260718.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA

- [ ] Guest + save-data + COD: first success page shows the automatic-15%-next-order message.
- [ ] Repeat with payment by requisites and Hutko; the same message appears once.
- [ ] Plain guest checkout and standalone registration show no message.
- [ ] Later order with First15 auto-applied does not show this registration message again.
- [ ] Confirm order total and Hutko amount paths remain untouched; run `bs-checkout-smoke`.
