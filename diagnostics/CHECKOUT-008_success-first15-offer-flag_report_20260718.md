# CHECKOUT-008 — shared success First15 offer flag

Date: 2026-07-18  
Risk: checkout/success display only; no payment, coupon, order-write or database change.

## Root cause

The newest post-CHECKOUT-007 archive contains the conditional First15 block in `catalog/view/template/checkout/success.twig` and the account-creation flag in `catalog/controller/checkout/payment_method.php`. The last captured `catalog/controller/checkout/success.php` does not consume `checkout001_first15_offer_pending` or add `show_first15_offer` to `order_data`. The Twig condition therefore remains false for COD, bank-details payment and Hutko alike.

## Patch scope

`patches/CHECKOUT-008_success-first15-offer-flag_20260718.php` changes only `catalog/controller/checkout/success.php`:

- reads and clears the one-time guest-registration First15 flag before success-session cleanup;
- passes `show_first15_offer` to the existing shared success Twig template;
- leaves the existing Hutko reload, fiscal, payment and order-data paths unchanged.

## Validation

- Target anchors were verified against `booster-debug-CHECKOUT006-post-live.tar.gz` (2026-07-17), the latest archive containing `success.php`.
- The newer `booster-debug-CHECKOUT007-post-live.tar.gz` confirms that the Twig message and the `payment_method.php` session flag already exist.
- Runner: strict one-anchor checks, backup, PHP lint with automatic restore, idempotent marker and self-delete.

## Rollback

Restore `catalog/controller/checkout/success.php` from `_patch_backups/CHECKOUT-008_success-first15-offer-flag_20260718-<timestamp>/`, then clear OpenCart cache/template cache.

## Post-deploy QA

1. Guest selects account creation, then completes COD: first success view contains the First15 next-order message.
2. Repeat the same guest-registration flow with bank-details payment and Hutko: same message appears on their first success render.
3. Plain guest checkout and existing logged-in customer: no message.
4. Reload success: message may disappear by design; the order summary and Hutko reload-resilience stay intact.
