# Codex Report — PAY-001: Phase 2 dedicated credit UI

Date: 2026-07-21

## Scope

Implements the selected architecture from the RESET handoff:

- keeps the deployed SimpleCheckout isolation in place;
- adds a virtual mono_chast.mono_chast_{3|4|5} method only inside stock checkout/checkout;
- adds the product chooser, modal, checkout drawer, inactive official PUMB offer and supplied payment assets.

No DB schema/settings, confirm.php, Hutko, COD, IBAN, NCRM or system/library/url.php changes are made.

## Files touched by the uploadable patch

```text
catalog/controller/product/product.php
catalog/view/template/product/product.twig
catalog/controller/checkout/checkout.php
catalog/controller/checkout/payment_method.php
catalog/view/template/checkout/payment_method.twig
catalog/view/stylesheet/boostershop-ds.css
catalog/view/template/common/header.twig
catalog/view/image/payment/pay001-mono-label.png
catalog/view/image/payment/pay001-pumb.svg
```

## Local dry-run

Tested on a controlled copy of the newest applicable source evidence:

```text
backup-7.19.2026_09-58-50_boosters.tar.gz
booster-debug-checkout-state-refactor.tar.gz
booster-debug-ST2c5-display-only-shipping.tar.gz
pay001-postdeploy-files.tar.gz
```

```text
backup=.../_patch_backups/PAY-001_phase2_credit_ui_20260721-20260721-122750
changed_file=<9 files>
php_l=ok
done=ok
second run: already_applied=yes
```

## PHP checks

```text
No syntax errors detected in PAY-001_phase2_credit_ui_20260721.php
No syntax errors detected in catalog/controller/product/product.php
No syntax errors detected in catalog/controller/checkout/checkout.php
No syntax errors detected in catalog/controller/checkout/payment_method.php
```

## Rollback

The patch creates a timestamped backup before any write:

```text
_patch_backups/PAY-001_phase2_credit_ui_20260721-<timestamp>/files/
```

Restore the affected files from that directory, then clear OpenCart cache. No DB rollback is required.

## Owner run

```bash
cd ~/public_html || exit
php PAY-001_phase2_credit_ui_20260721.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared";'
```

## Post-deploy QA

- [ ] Keep payment_mono_chast_status=0: neither checkout exposes Mono.
- [ ] Temporarily enable only for sandbox: product chooser and stock checkout/checkout show Mono 3/4/5; SimpleCheckout stays without it.
- [ ] Choose 3, 4 and 5 from a product: the matching virtual code is saved and reaches the existing Mono confirm() controller after the normal order-write boundary.
- [ ] Verify the PUMB card is inactive and reads Сплачуйте частинами ПУМБ / СКОРО БУДЕ.
- [ ] Run the 11-step bs-checkout-smoke, including Hutko, COD and IBAN.
- [ ] After sandbox QA restore payment_mono_chast_status=0; leave SimpleCheckout isolation deployed.

## Risk

High-risk checkout/payment surface. The patch fails before writing if the installed Mono extension or its SimpleCheckout-isolation marker is missing, or if any current source anchor no longer matches. Local validation is not deployment or live checkout proof.
