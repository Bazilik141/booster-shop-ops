# Codex Report — PAY-001 Phase 2d: Mono lifecycle fail-safe

Date: 2026-07-25

## Scope

Implemented the owner-reported follow-up after order #276:

- treat `point_id` as a required merchant credential;
- normalize Ukrainian local phone numbers only for the Mono request;
- do not treat `CREATE_FAILED` as an idempotent success;
- allow a failed transaction row to be repaired by a later successful retry;
- redirect a successfully created Mono application to `checkout/success`;
- prevent IBAN/COD/another payment controller from completing the same Mono order;
- expose a bounded bank validation message instead of a false pending state.

No credential is generated or written. Monobank has not issued the merchant
`point_id`, so end-to-end API acceptance remains externally blocked.

## Evidence

Order #276:

- OpenCart payment code: `mono_chast.mono_chast_3`;
- Mono transaction: `CREATE_FAILED`;
- Mono HTTP status: `400`;
- bank response: `point_id` was empty;
- a later non-Mono controller added IBAN instructions/status history to the same
  order without changing its stored Mono payment method.

## Files touched

```text
patches/PAY-001_phase2d_mono_lifecycle_fail_safe_20260725.php

Runtime files replaced by the patch:
catalog/controller/product/product.php
catalog/controller/checkout/payment_method.php
extension/mono_chast/catalog/controller/payment/mono_chast.php
```

## Dry-run result

```text
changed_file=catalog/controller/product/product.php
changed_file=catalog/controller/checkout/payment_method.php
changed_file=extension/mono_chast/catalog/controller/payment/mono_chast.php
php_l=ok
done=ok
```

## Contract tests

```text
private_contracts=ok
```

Covered:

- `067 123 45 67` → `+380671234567`;
- already-international Ukrainian phone stays canonical;
- invalid short phone is rejected;
- `CREATE_FAILED` is not reusable as success;
- `IN_PROCESS` with a real Mono order ID is reusable;
- API validation message is surfaced as an error.

## php -l result

```text
No syntax errors detected in catalog/controller/product/product.php
No syntax errors detected in catalog/controller/checkout/payment_method.php
No syntax errors detected in extension/mono_chast/catalog/controller/payment/mono_chast.php
No syntax errors detected in PAY-001_phase2d_mono_lifecycle_fail_safe_20260725.php
```

## Idempotency

Re-running against the patched fixture returns:

```text
already_applied=yes
```

## Rollback

Backup:

```text
_patch_backups/PAY-001_phase2d_mono_lifecycle_fail_safe_20260725-<timestamp>/
```

Restore the three printed runtime paths from that directory and clear OpenCart
cache/template cache.

## Run command (owner)

```bash
cd ~/public_html || exit
php PAY-001_phase2d_mono_lifecycle_fail_safe_20260725.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] Keep `payment_mono_chast_status=0`.
- [ ] Confirm COD, IBAN and Hutko remain selectable and create fresh orders.
- [ ] Confirm order #276 is not silently modified by the patch.
- [ ] After Monobank issues `store_id`, secret and `point_id`, enter all three in admin.
- [ ] In a controlled test window, confirm a local-format Ukrainian phone is accepted.
- [ ] Confirm HTTP/API rejection remains on checkout as an error and never becomes pending.
- [ ] Confirm switching away from a failed Mono attempt creates a new OpenCart order.
- [ ] Confirm a successful Mono create redirects to `checkout/success` and clears the cart.
- [ ] Confirm callback updates the waiting order status.

## Side effects / risks

- Checkout/payment is high-risk; full live success cannot be signed off before
  Monobank merchant onboarding and a real `point_id`.
- Existing order #276 is preserved unchanged and requires an owner-side admin
  decision (cancel/retain) outside this patch.
- No DB schema/settings changes and no SimpleCheckout, Hutko, fiscalization,
  NCRM, or routing files are touched.
