# Codex Report — ST-2b.6 Phase 1: remove Hutko auto-select

Date: 2026-07-03

## Scope

Requires a real payment-method click on both checkouts. Address blur/change,
shipping refresh, Hutko/COD/bank logic, totals, fiscalization, coupons, order
creation, database, and existing Phase 0/0b diagnostics remain unchanged.

## Files touched

```text
patches/ST-2b6c_remove-hutko-autoselect_20260703.php
catalog/view/template/checkout/payment_method.twig
extension/SimpleCheckout/catalog/view/template/module/checkout.twig
extension/SimpleCheckout/catalog/view/template/module/payment_method.twig
extension/SimpleCheckout/catalog/controller/module/pinta_simple_checkout.php
```

Four runtime files are required because SimpleCheckout had three independent
fallbacks: client auto-check, Twig first-radio pre-check, and server-side
`empty → hutko.hutko` resolution. A template-only change would not satisfy the
explicit-click guarantee.

## Changes

- Stock checkout starts with no pending Hutko preference.
- Stock payment list no longer selects or saves any method automatically.
- SimpleCheckout preferred-method function is now a no-op.
- SimpleCheckout request building no longer forces a payment radio.
- Initial and AJAX-loaded SimpleCheckout payment templates leave all radios
  unchecked when the session has no payment code.
- Empty SimpleCheckout payment stays invalid server-side instead of becoming
  Hutko or the first method.
- Phase 0/0b logs remain available for verification.

## Dry-run and syntax

```text
php_l_patch=ok
php_l_simple_controller=ok
address_refresh_logic=unchanged
diagnostics=preserved
done=ok
```

Stock and SimpleCheckout JavaScript syntax checks passed. Second run returned:

```text
already_applied=yes
changed_files=none
done=ok
```

The unchanged live `checkout.twig` and `shipping_method.twig` hashes confirm the
address blur/change and shipping-refresh implementation was not edited.

## Backup / rollback

Backups are created under:

```text
_patch_backups/ST-2b6c_remove-hutko-autoselect_20260703-<timestamp>/<original path>
```

The patch prints every exact path, validates the changed PHP controller with
`php -l`, and restores all files on failure. For manual rollback, restore all
four backups and clear `cache.*` plus `template/*` via `DIR_CACHE`.

## Run command

```bash
cd ~/public_html || exit
php ST-2b6c_remove-hutko-autoselect_20260703.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA

- [ ] Run full 11-step `bs-checkout-smoke` in a quiet window.
- [ ] New checkout: choose COD, trigger address refresh by blur, confirm payment stays empty.
- [ ] Repeat with address dropdown re-selection.
- [ ] Confirm intermediate summary shows «Оберіть спосіб оплати» and no active place-order button.
- [ ] Explicitly select COD and verify the order remains COD.
- [ ] Repeat on SimpleCheckout; AJAX refresh must not check Hutko or any first method.
- [ ] Explicitly choose Hutko and verify amount/fiscalization unchanged.
- [ ] Confirm `oc_order` count stays flat until explicit place-order click.
- [ ] Export `window.bsSt2b6ReadDiagnostics()` and confirm no `isAuto: true` event after reset.

## Status

Do not mark Done before owner QA. The separate missing trusted-pointer gate
remains open and is not changed by this patch.
