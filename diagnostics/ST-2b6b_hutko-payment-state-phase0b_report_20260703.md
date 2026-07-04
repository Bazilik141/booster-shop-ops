# Codex Report — ST-2b.6 Phase 0b: Hutko payment-state diagnostics

Date: 2026-07-03

## Scope

Diagnostic-only instrumentation for silent payment changes and old/new checkout
state divergence. No payment selection, order creation, Hutko, fiscalization,
database, status, or CRM behavior was changed.

## Files touched

```text
patches/ST-2b6b_hutko-payment-state-phase0b-diagnostics_20260703.php
catalog/view/template/checkout/checkout.twig
catalog/view/template/checkout/payment_method.twig
catalog/view/template/checkout/shipping_method.twig
extension/SimpleCheckout/catalog/view/template/module/checkout.twig
```

## Live candidates found

Candidates only; none is a confirmed cause until a matching runtime log exists.

1. Stock `payment_method.twig` initializes `bsPaymentPendingChoice = 'hutko'`.
2. If the hidden payment code is empty, stock checkout falls back to pending
   choice, then explicit Hutko, then the first option, and auto-calls
   `payment_method.save`.
3. Stock shipping save and checkout state reset clear the hidden payment code;
   the subsequent payment reload can therefore enter the Hutko fallback.
4. SimpleCheckout `selectPreferredPaymentMethod()` selects a Hutko-prefixed radio
   when none is checked.
5. SimpleCheckout `buildCheckoutRequestData()` calls
   `ensureCheckoutMethodSelection('payment_method', 'hutko')`.
6. SimpleCheckout controller `resolveSimpleCheckoutPaymentMethod()` returns
   `hutko.hutko` when the posted selection is empty and Hutko is available.
7. SimpleCheckout payment template checks the first method when its server-side
   `code` is empty.

## Instrumentation

- New checkout logs default selection, auto-save, radio events, payment-code
  writes/resets, stack traces, and whether each save was automatic.
- Confirm rendering logs displayed shipping/payment labels, hidden codes, live
  checked radios, preview radios, and divergence flags at the same timestamp.
- SimpleCheckout logging was added because the old checkout was the Phase 0a
  blind spot. It records radio events, preferred/default selection, request
  serialization, AJAX refresh state, lifecycle events, and stack traces.
- Both checkouts use the same bounded `localStorage` key:
  `bs.st2b6.diag.v1`.
- No customer address, phone, email, token, or payment credential is logged.

The SimpleCheckout controller was inspected but not modified. Client-side
instrumentation captures the selected/rendered code and request-building path
without touching the live rollback checkout's order logic.

## Dry-run result

```text
scope=Phase 0b diagnostics only
db_schema_changes=none
db_data_changes=none
php_l_patch=ok
changed_files=catalog/view/template/checkout/checkout.twig,catalog/view/template/checkout/payment_method.twig,catalog/view/template/checkout/shipping_method.twig,extension/SimpleCheckout/catalog/view/template/module/checkout.twig
simplecheckout_instrumentation=added
done=ok
```

Second run:

```text
already_applied=yes
changed_files=none
done=ok
```

## Syntax checks

```text
PHP patch: No syntax errors detected
New checkout JavaScript: syntax ok
Payment-method JavaScript: syntax ok
Shipping-method JavaScript: syntax ok
SimpleCheckout JavaScript blocks: syntax ok
```

## Idempotency and preflight

The patch pins all four live SHA-256 hashes and verifies exact anchors before
writing. Re-running returns `already_applied=yes`. Any changed live file or
partial installation fails before writing.

## Backup and rollback

Backups:

```text
_patch_backups/ST-2b6b_hutko-payment-state-phase0b-diagnostics_20260703-<timestamp>/<original path>
```

The patch prints every exact backup path and restores all changed files if a
post-write check fails. For manual rollback, restore all four printed backups,
then clear `cache.*` and `template/*` via `DIR_CACHE`.

## Run command

```bash
cd ~/public_html || exit
php ST-2b6b_hutko-payment-state-phase0b-diagnostics_20260703.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Evidence export

Clear before each test:

```javascript
window.bsSt2b6ClearDiagnostics()
```

Export on either old or new checkout:

```javascript
copy(JSON.stringify(window.bsSt2b6ReadDiagnostics(), null, 2))
```

## Post-deploy QA

- [ ] Record order count before deployment and confirm no count change from deployment alone.
- [ ] Run the 11-step `bs-checkout-smoke` baseline.
- [ ] Old checkout: select COD + a distinct shipping method; screenshot checked radios.
- [ ] Navigate to new checkout without touching fields; immediately screenshot radios and confirm-panel text.
- [ ] Export diagnostics and compare `display*`, `hidden*`, `live*`, and `*Diverged` fields.
- [ ] Repeat with Hutko and bank payment plus 2–3 shipping combinations.
- [ ] Preserve clean/matching runs as evidence too.
- [ ] One deliberate confirm click must still create exactly one order.

## Status

Remains `На діагностиці`. No dashboard/Notion status change was made.
