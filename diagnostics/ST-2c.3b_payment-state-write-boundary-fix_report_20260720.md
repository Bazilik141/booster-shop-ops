# Codex Report — ST-2c.3b: payment state write-boundary fix

Date: 2026-07-20

## Scope

Investigated the owner-provided live bundle `booster-debug-ST2c3b-payment-live.tar.gz` (SHA-256 `F062D90B4160F7362B16A4F23677CF1C9BF94178798D67F99847A968E235D90F`). It contains the current source controller, payment/checkout Twig templates, and checkout state coordinator; no `system/storage/modification` shadow files were present in the bundle.

The live controller hash confirms ST-2c.3a is installed. Two remaining defects are visible in the exact live code:

1. `checkout-state.js::bootstrap()` returns through the shipping bootstrap path before `renderPaymentPreview()`, so the initial payment block stays empty.
2. `payment_method.save` accepts a posted code only when it exists in `session.payment_methods`, even though that map is merely a side effect of a separate asynchronous `payment_method.getMethods` request. The coordinator also starts totals and payment-method reads in the same shipping transition. This makes the payment write boundary depend on transient inter-request session state and produces `error_payment_method` even for a rendered option.

The patch moves method resolution into one server helper shared by `getMethods()` and `save()`. On save, the server rebuilds the currently allowed canonical list from the current checkout address, filters it to Hutko / preferred post-payment / bank, and validates the posted exact option code there. It also renders the three preview choices immediately and removes the address-dependent helper text.

DB changes: none. Payment gateway execution, order creation, Nova Poshta selection, shipping totals, and SimpleCheckout are not changed.

## Files touched

```text
patches/ST-2c.3b_payment-state-write-boundary-fix_20260720.php
diagnostics/ST-2c.3b_payment-state-write-boundary-fix_report_20260720.md

Live targets changed by the patch:
catalog/controller/checkout/payment_method.php
catalog/view/javascript/checkout-state.js
catalog/view/template/checkout/payment_method.twig
catalog/view/template/checkout/checkout.twig
```

## Fresh live hashes

```text
6A96DC5C5CEDEB1074B278BD1A46B3C561907EF93E635E4855A338ECC43728FD  catalog/controller/checkout/payment_method.php
6840D6BD9AB19FC7D70329E35007871A8616D61490FF892C419B2676020C092E  catalog/view/javascript/checkout-state.js
2DC69B60ECFB22B0AA466E168DEF15927BEE0097923117FE9B8E3868DBDA0430  catalog/view/template/checkout/payment_method.twig
628C4D71272B3BB75F89B0625E8D96938BE0DC012E40770D6F132441F782F7CA  catalog/view/template/checkout/checkout.twig
```

The patch refuses to edit if any target hash or exact anchor differs.

## Dry-run result

Applied to an isolated copy of the exact supplied live files:

```text
backup=.../_patch_backups/ST-2c.3b_payment-state-write-boundary-fix_20260720-20260720_132928
changed=catalog/controller/checkout/payment_method.php
changed=catalog/view/javascript/checkout-state.js
changed=catalog/view/template/checkout/payment_method.twig
changed=catalog/view/template/checkout/checkout.twig
php_l=ok
done=ok
self_deleted=True
```

Static contract verification:

```text
static_contract=ok
preview_methods=hutko,cod,bank
legacy_session_gate=absent
address_dependent_hint=absent
payment_method.twig embedded_js=ok
```

## php -l result

```text
No syntax errors detected in ST-2c.3b_payment-state-write-boundary-fix_20260720.php
No syntax errors detected in catalog/controller/checkout/payment_method.php
checkout-state.js node --check = ok
payment_method.twig embedded JavaScript = ok
```

## Idempotency

Re-uploading and re-running the patch against the patched fixture returns:

```text
already_applied=yes
```

Partial application is rejected. On first application, all four exact source files are backed up before any write. A write or PHP lint failure restores all four backups.

## Rollback

Backup directory:

```text
_patch_backups/ST-2c.3b_payment-state-write-boundary-fix_20260720-<UTC timestamp>/
```

Copy the four files from that directory back to their original paths, then clear OpenCart data/template cache. No SQL rollback is needed.

## Run command (owner)

```bash
cd ~/public_html || exit
php ST-2c.3b_payment-state-write-boundary-fix_20260720.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] On a fresh checkout with empty recipient/address fields, all three payment choices are visible immediately and no address-dependent payment hint is shown.
- [ ] Selecting a preview choice before address entry highlights it without a red payment error; it is not persisted until shipping is ready.
- [ ] Fill recipient and each of the three Nova Poshta flows; after shipping becomes ready, choose Hutko, post-payment, and bank in separate sessions. Each selection stays checked, the red `Потрібен спосіб оплати!` error is absent, and the sidebar no longer asks for a payment method.
- [ ] Edit email and delivery address after a valid selection, then reselect payment. The same behavior remains correct without Ctrl+R.
- [ ] The generic fourth OpenCart COD method never appears.
- [ ] Paid shipping amount and checkout total still update after address/shipping changes.
- [ ] Complete one owner-approved test order and verify the selected payment code on success/admin. Do not test Hutko with a real charge unless intended.

## Side effects / risks

Payment selection now performs one server-side read of currently available payment methods at the save boundary. This is intentional validation against current state and removes dependence on an AJAX-populated session snapshot. Risk is medium because stock checkout/payment is affected; rollback is the four-file backup. No DB, gateway API, fiscalization, or order-status writes are added.
