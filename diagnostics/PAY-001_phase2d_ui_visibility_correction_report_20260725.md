# Codex Report — PAY-001 Phase 2d: UI visibility correction

Date: 2026-07-25

## Scope

Corrects an over-strict gate introduced by
`PAY-001_phase2d_mono_lifecycle_fail_safe_20260725.php`.

Credit UI now remains visible for owner UI-QA when the extension, API base,
store ID and store secret are configured, even before Monobank issues the
merchant `point_id`.

The server-side Mono confirm controller is unchanged and still rejects the
application before any bank API call when `point_id` is missing.

## Files touched

```text
patches/PAY-001_phase2d_ui_visibility_correction_20260725.php

catalog/controller/product/product.php
catalog/controller/checkout/payment_method.php
```

## Dry-run result

```text
changed_file=catalog/controller/product/product.php
changed_file=catalog/controller/checkout/payment_method.php
php_l=ok
done=ok
```

## php -l result

Both changed PHP controllers and the patch pass `php -l`.

## Idempotency

Re-running returns:

```text
already_applied=yes
```

## Rollback

Restore both files from:

```text
_patch_backups/PAY-001_phase2d_ui_visibility_correction_20260725-<timestamp>/
```

## Run command (owner)

```bash
cd ~/public_html || exit
php PAY-001_phase2d_ui_visibility_correction_20260725.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] With the extension enabled, product credit cards/buttons are visible.
- [ ] Checkout renders the credit method and 3/4/5 term UI when cart gates pass.
- [ ] Without `point_id`, final Mono confirmation shows the onboarding error and does not call the bank API.
- [ ] Switching to COD/IBAN after that error creates a fresh OpenCart order.

## Side effects / risks

- No DB/settings changes.
- No SimpleCheckout, Hutko, fiscalization, NCRM, success, or routing files changed.
- Real Mono order creation remains blocked until the bank issues `point_id`.
