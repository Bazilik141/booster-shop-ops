# Codex Report — CHECKOUT-001 Phase 1.3: guest-only oferta and persistent loader

Date: 2026-07-05

## Scope

Implemented the owner’s two checkout corrections on top of Phase 1.2:

- show and require the Public Offer agreement only when checkout starts as guest;
- hide and do not validate that agreement for an already authorized customer;
- replace the disappearing button-only status with a fixed checkout overlay that remains visible while `#checkout-confirm` is replaced by AJAX.

The registered-guest flow remains sequential: account pre-step → confirm → payment confirm. This patch makes the wait continuously visible but does not merge those requests or change order/account creation.

Database, customer schema and payment extensions are not changed.

## Files touched

```text
patches/CHECKOUT-001_phase1-3_guest-oferta-loader-fix_20260705.php

Runtime targets:
catalog/view/template/checkout/checkout.twig
catalog/controller/checkout/payment_method.php
catalog/controller/checkout/confirm.php
```

## Dry-run result

```text
source_sha256=checkout.twig:ec580a506d546b4500fc97d105e52ab02335f99abc31cd1633e2e0e494eead61
source_sha256=payment_method.php:b7c08ee8940dedeb5e05eecbba6069afd2c203624c188fdc035755908d5ac95d
source_sha256=confirm.php:7d9f4bafb348983afc5b1794ce5d8933f8b1f12ebbd9fc68ed6d5b58b30390df
changed_files=3
guest_checkout_oferta=visible_and_required
authorized_checkout_oferta=hidden_and_not_required
guest_preconfirm_login=agreement_requirement_preserved_by_session_flag
checkout_loader=persistent_overlay_visible_before_first_request
done=ok
```

Focused checks:

```text
checkout_js_syntax=ok
persistent_loader_contract=ok
guest_oferta=visible_and_flagged
authorized_oferta=hidden_and_flag_cleared
confirm_guest_only_gate=ok
```

## php -l result

```text
No syntax errors detected in CHECKOUT-001_phase1-3_guest-oferta-loader-fix_20260705.php
No syntax errors detected in catalog/controller/checkout/payment_method.php
No syntax errors detected in catalog/controller/checkout/confirm.php
```

## Idempotency

Re-running against the fully patched state returns:

```text
already_applied=yes
changed_files=0
done=ok
```

## Rollback

The patch backs up all three runtime targets under:

```text
_patch_backups/CHECKOUT-001_phase1-3_guest-oferta-loader-fix_20260705-<timestamp>-<random>/
```

Restore the three files from that directory to their original paths, then clear OpenCart template cache.

## Run command (owner)

Upload both named patch files to `~/public_html`, then run:

```bash
cd ~/public_html || exit
php CHECKOUT-001_phase1-2_checkout-ux-loader_20260705.php && php CHECKOUT-001_phase1-3_guest-oferta-loader-fix_20260705.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

If Phase 1.2 is already deployed, its runner returns `already_applied=yes`; the chain then applies Phase 1.3.

## Post-deploy QA checklist

- [ ] Guest sees the Public Offer block and cannot submit while it is unchecked.
- [ ] Guest without account opt-in sees the overlay immediately and completes one order.
- [ ] Guest with account opt-in sees the overlay immediately, gets one account and one order, then redirects to success.
- [ ] Authorized customer does not see the Public Offer block and can complete one order.
- [ ] Overlay title is `Оформлюємо замовлення...` and remains visible while the confirm block changes.
- [ ] Double-click does not create a second order.
- [ ] Network tab shows no `createAccount` request when account opt-in is unchecked or absent.

## Side effects / risks

- Checkout is a high-risk path; rollback is the patch backup.
- The overlay intentionally blocks repeat interaction while sequential checkout requests run.
- Actual registered-guest processing time is not reduced; eliminating one of its sequential requests requires a separate server-flow redesign and broader regression testing.
