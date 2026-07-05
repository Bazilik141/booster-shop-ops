# Codex Report — CHECKOUT-001 Phase 1.2: oferta session persistence hotfix

Date: 2026-07-05

## Scope

Fixes the deployed checkout failure:

```text
Не знайдено кнопку підтвердження оплати після створення замовлення.
```

The issue reproduced with account opt-in both enabled and disabled.

## Root cause

`catalog/view/template/checkout/payment_method.twig` sends both `comment` and
`agree` to:

```text
checkout/payment_method.comment
```

The corresponding controller method persisted only `session.comment`.
`session.agree` remained empty even when the checkbox was visually checked.

Phase 1.1 correctly made agreement mandatory in `confirm.confirm`. The missing
session value therefore set confirm status to false, omitted the payment extension
partial and its real `#button-confirm`, and triggered the visible fallback error.

This also explains why disabling account creation did not change the result.

## Files touched

```text
patches/CHECKOUT-001_phase1-2_agree-session-hotfix_20260705.php
```

Runtime target:

```text
catalog/controller/checkout/payment_method.php
```

No Twig, payment extension, account creation, database, Hutko, fiscalization, CRM,
order status, or SimpleCheckout code is changed.

## Fresh-source preflight

Exact SHA256 of the reproduced current deployed Phase 1 + Phase 1.1 controller:

```text
b1b9be5e7fd45d37dba38fc5af7a74cb3df99475354e0f41efe262605525a251
```

The patch fails before writing on a missing file, hash mismatch, or anchor mismatch.

## Dry-run result

```text
already_applied=no
changed_files=1
agree_checked=session.agree=1
agree_unchecked=session.agree=unset
done=ok
```

Focused runtime:

```text
agree_session_runtime=ok
comment_persistence=unchanged
```

The runtime mock proved that checking the oferta persists `session.agree=1`,
unchecking removes it, and comment persistence remains unchanged.

## php -l result

```text
No syntax errors detected in CHECKOUT-001_phase1-2_agree-session-hotfix_20260705.php
No syntax errors detected in catalog/controller/checkout/payment_method.php
```

Local PHP: 8.3.30.

## Idempotency

```text
already_applied=yes
changed_files=0
done=ok
```

## Database behavior

No schema or row changes.

The failed opt-in test created its account before `confirm.confirm`, so that test
account remains without an order. Remove it through OpenCart admin if it is test
data, or use a new incognito guest/email for the next clean test.

## Rollback

The patch backs up the controller to:

```text
_patch_backups/CHECKOUT-001_phase1-2_agree-session-hotfix_20260705-<timestamp>-<suffix>/
```

Restore that file and clear OpenCart cache/template files.

## Run command

```bash
cd ~/public_html || exit
php CHECKOUT-001_phase1-2_agree-session-hotfix_20260705.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

Expected terminal end:

```text
done=ok
```

## Post-deploy QA checklist

- [ ] Open a new incognito session with a fresh guest email.
- [ ] Fill contact/address data and select shipping/payment.
- [ ] Leave oferta unchecked: place-order remains blocked.
- [ ] Check oferta: place-order unlocks.
- [ ] Network shows successful `payment_method.comment` before
  `confirm.confirm`.
- [ ] `confirm.confirm` response contains the payment extension
  `#button-confirm`.
- [ ] Opt-in disabled: one order completes as guest.
- [ ] Opt-in enabled with a new email: one account and one order are created.
- [ ] Exactly one customer-facing set-password email is sent.
- [ ] Exactly one `confirm.confirm` request occurs per trusted click.
- [ ] Verify Hutko, Checkbox fiscalization, and CRM readback before closing.

## Status

Keep CHECKOUT-001 `In progress` until owner QA passes.
