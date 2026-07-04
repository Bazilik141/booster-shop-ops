# Codex Report — ST-2b.6: Hutko phantom order tab-restore diagnostics

Date: 2026-07-03

## Scope

Phase 0 only, 1:1 with the handoff:

- added temporary browser lifecycle and `checkout/confirm.confirm` trigger logging;
- preserved checkout/order/Hutko/fiscalization behavior;
- made diagnostic history survive a full browser close through bounded `localStorage`;
- did not change the database, payment calculation, order status, CRM payload, or trusted-click behavior.

Live evidence was collected from `booster-debug-ST-2b6-live-20260703.tar.gz`.

Important live finding: the current deferred-confirm handler does **not** contain an
`event.isTrusted` or pointer-activation gate, although the handoff described that
gate as already live. Phase 0 does not add the missing gate; it records the real
event and stack so Phase 1 can be evidence-based.

## Live caller map

`st2b6-confirm-callers.txt` shows one active request site:

```text
catalog/view/template/checkout/checkout.twig
  window.bsCheckoutLoadConfirmAndSubmit
  checkout/confirm.confirm
  click.bsSt2b1DeferredConfirm
```

No `checkout/confirm.confirm` call remains in:

```text
catalog/view/template/checkout/payment_method.twig
catalog/view/template/checkout/shipping_address.twig
catalog/view/template/checkout/register.twig
catalog/view/template/checkout/payment_address.twig
```

Therefore the patch changes only `checkout.twig`.

## Files touched

```text
patches/ST-2b6_hutko-tab-restore-phase0-diagnostics_20260703.php
diagnostics/ST-2b6_hutko-tab-restore-phase0_report_20260703.md
```

Server-side target:

```text
catalog/view/template/checkout/checkout.twig
```

## Diagnostic data captured

Each entry includes:

- timestamp and source;
- `event.type`, `event.isTrusted`, `event.persisted`;
- active element, target/current target, trigger;
- `document.visibilityState`, hidden/focus/discarded state;
- navigation type;
- current shipping/payment code and label;
- `bsCheckoutConfirmSubmitting` state;
- JavaScript stack trace.

Listeners cover `pageshow`, `pagehide`, `visibilitychange`, the deferred button
click, `bsCheckoutLoadConfirmAndSubmit`, the actual `.load()` call, its callback,
and global jQuery `ajaxSend` observation of `checkout/confirm.confirm`.

No recipient name, address, phone, token, or payment credential is logged.

Read the persistent buffer in DevTools:

```javascript
window.bsSt2b6ReadDiagnostics()
```

Copy it:

```javascript
copy(JSON.stringify(window.bsSt2b6ReadDiagnostics(), null, 2))
```

Clear it before a new test series:

```javascript
window.bsSt2b6ClearDiagnostics()
```

## Dry-run result

Applied against the exact live archive shape:

```text
scope=Phase 0 browser diagnostics only
db_schema_changes=none
db_data_changes=none
php_l_patch=ok
changed=catalog/view/template/checkout/checkout.twig
target_php_lint=not_applicable:twig_only
diagnostic_storage=localStorage:bs.st2b6.diag.v1
done=ok
```

Second run:

```text
already_applied=yes
changed_files=none
done=ok
```

The first portability test intentionally reached a Windows replace failure;
`restore_on_fail=ok` restored the original SHA-256 before the write path was
made cross-platform.

The first and second runs both self-deleted the temporary patch file in a
non-synced local test directory.

## Syntax checks

```text
No syntax errors detected in ST-2b6_hutko-tab-restore-phase0-diagnostics_20260703.php
Node.js syntax check of the patched checkout script: exit 0
```

The target is Twig/JavaScript, so there is no target PHP file to lint. The patch
runs `php -l` on itself before any server write and fails closed if lint is
unavailable or unsuccessful.

## Idempotency

Re-running returns:

```text
already_applied=yes
done=ok
```

The patch is pinned to live SHA-256:

```text
d1f83b562f7ff39e7ad04a52d15e492f666ebaa40b13080035ef21d2c8c83bd8
```

If `checkout.twig` changes before deployment, the patch stops before writing.

## Backup and rollback

Backup is created before the write:

```text
_patch_backups/ST-2b6_hutko-tab-restore-phase0-diagnostics_20260703-<timestamp>/catalog/view/template/checkout/checkout.twig
```

The patch prints the exact path. Restore that file to:

```text
catalog/view/template/checkout/checkout.twig
```

Then clear `cache.*` and `template/*` using `DIR_CACHE` from `config.php`.
The patch restores automatically if a post-write check fails.

## Run command (owner)

```bash
cd ~/public_html || exit
php ST-2b6_hutko-tab-restore-phase0-diagnostics_20260703.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] Record the CRM/admin `oc_order` baseline before deployment.
- [ ] Run the existing 11-step `bs-checkout-smoke` once.
- [ ] Confirm payment switching by mouse and keyboard creates zero orders.
- [ ] Confirm one explicit place-order click creates exactly one order.
- [ ] Confirm double-click still creates no duplicate.
- [ ] Open checkout, run `window.bsSt2b6ClearDiagnostics()`, fill the address, select Hutko, and do not click place-order.
- [ ] Attempt 1: wait about 2 minutes, fully close the browser, reopen/restore the tab, export diagnostics, compare order count.
- [ ] Attempt 2: repeat with Hutko after a longer wait, reopen through browser history, export diagnostics, compare order count.
- [ ] Attempt 3: repeat with COD as the control method, export diagnostics, compare order count.
- [ ] Preserve logs even when no order appears; absence after three attempts is evidence.
- [ ] Send all three JSON exports and before/after order counts for Phase 1 analysis.

## Side effects / risks

- Checkout order behavior is unchanged; instrumentation only.
- Browser logging is bounded to the latest 200 entries.
- Data persists in same-origin `localStorage` until explicitly cleared.
- This is temporary code and must be removed in Phase 1 or after closing the investigation.
- If the log is absent after cache clear, check the active `${DB_PREFIX}theme`
  override for `checkout/checkout` before changing Twig again.
- `bs-checkout-smoke` and owner manual QA remain mandatory because this touches
  the live checkout template.

## Roadmap status

No dashboard/Notion status was changed. The handoff says to move ST-2b.6 to
`На діагностиці` after Phase 0 execution/evidence; Notion creation is still an
owner decision.
