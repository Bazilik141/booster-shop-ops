# Codex Report — ST-2b.6d: trusted deferred-confirm activation

Date: 2026-07-03

## Scope

Adds one gate to the existing delegated «Оформити замовлення» click handler.
No database, payment selection, order implementation, Hutko, fiscalization,
totals, address refresh, or ST-2b6c behavior is changed.

## Files touched

```text
patches/ST-2b6d_deferred-confirm-trusted-click-gate_20260703.php
catalog/view/template/checkout/checkout.twig
```

## Guard

The handler proceeds only when:

- the native event has `isTrusted === true`;
- the delegated trigger is the deferred-confirm button;
- both event target and jQuery current target are that button.

Browser-generated click events from mouse/touch and Enter/Space on a focused
button satisfy these checks. Programmatic `.trigger('click')` and
`dispatchEvent(new Event('click'))` do not.

The existing event parameter, double-submit guard, `confirm.confirm` call,
and Phase 0/0b diagnostics remain unchanged. Rejected and accepted activations
are logged.

## Dry-run / syntax

```text
php_l_patch=ok
keyboard_activation=preserved:trusted native click on focused button
diagnostics=preserved
done=ok
```

Patched checkout JavaScript syntax passed. Second run returned:

```text
already_applied=yes
changed_files=none
done=ok
```

## Backup / rollback

```text
_patch_backups/ST-2b6d_deferred-confirm-trusted-click-gate_20260703-<timestamp>/catalog/view/template/checkout/checkout.twig
```

Restore the printed backup and clear `cache.*` plus `template/*` via
`DIR_CACHE`.

## Run command

```bash
cd ~/public_html || exit
php ST-2b6d_deferred-confirm-trusted-click-gate_20260703.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA

- [ ] Run full 11-step `bs-checkout-smoke`.
- [ ] `.trigger('click')` and synthetic `dispatchEvent` create no request/order.
- [ ] Real mouse click creates exactly one order.
- [ ] Tab to the button and use Enter; verify exactly one order.
- [ ] Repeat keyboard check with Space.
- [ ] Enter while focused on payment/shipping radio creates no order.
- [ ] Rapid double-click still creates only one order.
- [ ] Export diagnostics and verify rejected/accepted entries.

## Status

Do not mark Done before owner mouse and keyboard QA. Reload click-through timing
remains a separate issue if it reproduces.
