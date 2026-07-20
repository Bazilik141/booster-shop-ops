# Codex Report — ST-2c.3a: payment code lookup fix

Date: 2026-07-20

## Scope

Production regression reported after ST-2c.3: every rendered payment option can be selected visually, but `payment_method.save` returns `error_payment_method`, leaves `#input-payment-code` empty, and the sidebar continues to say that payment is not selected.

Root cause in the exact post-ST-2c.3 controller:

- `getMethods()` renders the canonical filtered option using its real `option['code']`;
- `save()` still splits that code on `.` and assumes the resulting parts equal the internal payment group/option array keys;
- the canonical filter preserves the real extension array keys, which are not required to match the code parts;
- therefore a valid rendered option can fail the legacy key-shape lookup.

Fix: one shared server lookup now scans the canonical session list by the option's own `option['code']`. The exact option returned by that lookup becomes `session.payment_method`. No UI hiding, refresh, DB, order creation, payment gateway, or Nova Poshta changes.

## Files touched

```text
patches/ST-2c.3a_payment-code-lookup-fix_20260720.php
catalog/controller/checkout/payment_method.php   (live target)
```

## Dry-run result

Applied to an isolated copy of the exact ST-2c.3 output:

```text
changed=catalog/controller/checkout/payment_method.php
php_l=ok
done=ok
```

Generated target SHA256: `6A96DC5C5CEDEB1074B278BD1A46B3C561907EF93E635E4855A338ECC43728FD`. The generated file matched the reviewed work file byte-for-byte.

## Contract test

A fixture intentionally used internal keys that differ from `option.code`. This reproduces the contract the legacy `explode('.')` lookup rejects.

```text
payment_code_lookup=ok
```

The same lookup also rejected an unavailable code.

## php -l result

```text
No syntax errors detected in payment_method.php
No syntax errors detected in ST-2c.3a_payment-code-lookup-fix_20260720.php
```

## Idempotency

Re-running returned:

```text
already_applied=yes
```

The repeat gate checks both the ST-2c.3a marker and the absence of the old `explode('.')` validator.

## Rollback

Backup is created before the write at:

```text
_patch_backups/ST-2c.3a_payment-code-lookup-fix_20260720_<UTC timestamp>/catalog/controller/checkout/payment_method.php
```

Copy that file back to `catalog/controller/checkout/payment_method.php`. A failed PHP lint restores it automatically.

## Run command (owner)

```bash
cd ~/public_html || exit
php ST-2c.3a_payment-code-lookup-fix_20260720.php
```

Expected terminal tail: `php_l=ok`, `done=ok`.

## Post-deploy QA checklist

- [ ] Open checkout with delivery ready and no payment selected; no red payment error should be present.
- [ ] Select Hutko; its radio remains selected, the red error is absent, and the sidebar becomes payment-ready.
- [ ] Select post-payment; the same state must persist without a reload.
- [ ] Select IBAN; the same state must persist without a reload.
- [ ] Switch between all three methods several times; each newest selection must replace the previous session method.
- [ ] Confirm the fourth stock method remains absent.
- [ ] Complete one test order and verify the selected payment method on success/admin.

## Side effects / risks

Risk: medium-high because the checkout payment session write boundary changes. The patch is limited to one controller, accepts only a code already present in the server-generated canonical session list, preserves the exact selected option payload, performs no DB changes, and has an exact SHA256 source gate plus automatic rollback on lint failure.
