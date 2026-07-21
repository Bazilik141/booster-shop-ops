# PAY-001 — Legacy Simple Checkout isolation

## Scope

Prevent `mono_chast` from being exposed by the existing OpenCart payment-method enumerator, which the legacy Simple Checkout consumes. The extension's admin configuration and sandbox integration remain intact.

## File touched

`extension/mono_chast/catalog/model/payment/mono_chast.php`

The patch replaces only `MonoChast::getMethods()` with an explicit empty result, marked `PAY-001-SIMPLE-CHECKOUT-ISOLATION`.

## Database

No database reads or writes. Existing payment-extension registration and settings are not altered.

## Validation

- Target-file and `config.php` existence checks before changes.
- Exact legacy `getMethods()` body must occur once; otherwise the patch aborts without editing.
- Backs up the target file under `_patch_backups/` before writing.
- Runs `php -l`; restores the backup if syntax validation fails.
- Repeat execution reports `already_applied=yes`.

## Risk and rollback

Low and reversible. The current Simple Checkout no longer receives any `mono_chast` option, even when the admin setting is enabled. Restore the saved backup file to reverse.

## Run command

```bash
cd ~/public_html || exit
php PAY-001_simple_checkout_isolation_20260721.php
```

## Post-deploy QA

1. Refresh the current Simple Checkout in a new private browser window.
2. Verify no `Покупка Частинами monobank` option appears at any cart total or UAH currency.
3. In Admin → Extensions → Payments, open the mono_chast configuration and confirm it still saves normally.
4. Do not enable public usage in the legacy checkout. The redesigned checkout requires a separate Phase 2 integration point.
