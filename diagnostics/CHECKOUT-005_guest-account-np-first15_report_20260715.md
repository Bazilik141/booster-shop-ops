# CHECKOUT-005 — guest-account Nova Poshta + First15

## Scope

Fix only the guest checkout path where `checkout/payment_method.createAccount` creates an account just before order confirmation. No database schema or data migration is included.

## Files touched

- `catalog/controller/checkout/payment_method.php`
  - validates the current selected Nova Poshta refs using the same Pinta module lookups as `checkout/shipping_address.save`;
  - writes `custom_field['bs_np_v1']` into the in-session payment and shipping address before `addAddress()` creates the account address;
  - applies pending `First15` after login and before the next confirm/Hutko request, only when no coupon is already active.
- `catalog/view/template/checkout/checkout.twig`
  - submits the selected NP ref fields with `payment_method.createAccount`.

## Risk and rollback

- Risk: checkout/account creation and address book; a stale NP ref makes account creation fail open to the existing guest order flow rather than saving a legacy address.
- DB: no schema or direct SQL changes. OpenCart's existing customer/address writes remain unchanged.
- Rollback: the patch writes originals under `_patch_backups/CHECKOUT-005_guest-account-np-first15_20260715-<timestamp>/` before each edit.

## Validation

- Dry-run anchor contract: one anchor per replacement.
- PHP syntax: `php -l catalog/controller/checkout/payment_method.php` after write; restores both files on failure.
- Repeat run: reports `already_applied=yes` without rewriting files.

## Run

```bash
cd ~/public_html || exit
php CHECKOUT-005_guest-account-np-first15_20260715.php
php -r 'require "config.php"; foreach (glob(DIR_CACHE . "*") as $f) { if (is_file($f)) @unlink($f); }'
```

## Post-deploy QA

1. As a guest, select a Nova Poshta branch, parcel locker, and courier address in separate checks; enter a new email and tick account creation.
2. Finish order: `First15` must be shown in totals / sent to Hutko before payment.
3. Return to checkout/account with the new customer: the selected address must open without the “repeat NP point” warning.
4. Confirm a guest order without account creation remains unchanged.
5. Confirm an account with a manually entered coupon does not replace it with First15.
