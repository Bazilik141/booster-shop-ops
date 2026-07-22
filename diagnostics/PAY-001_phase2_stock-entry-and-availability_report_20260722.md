# Codex Report — PAY-001: Phase 2 stock entry and availability guard

Date: 2026-07-22

## Scope

Implements the three observed Phase 2 corrections without widening checkout scope:

- Product-modal selection now builds a direct `index.php?route=checkout/checkout` URL, so the current `URL::link()` redirect to legacy SimpleCheckout is not used.
- A direct selection of “Оплатити частинами” in stock checkout immediately reveals the 3/4/5 Mono drawer and still saves a valid virtual Mono option.
- The full product credit UI is rendered only when the product quantity is greater than zero; the provider block is placed under the credit button and above the existing trust line.

No database rows, settings, `system/library/url.php`, SimpleCheckout isolation, Hutko, COD, IBAN, NCRM, order-write code, or Mono API code are changed.

## Files touched

```text
patches/PAY-001_phase2_stock-entry-and-availability_20260722.php — uploadable patch
catalog/controller/product/product.php
catalog/view/template/product/product.twig
catalog/view/template/checkout/payment_method.twig
```

## Dry-run result

Tested on the controlled copy of `booster-debug-PAY001-phase2-anchor.tar.gz` after applying the already-deployed Phase 2 patch.

```text
backup=.../_patch_backups/PAY-001_phase2_stock-entry-and-availability_20260722-20260722-062433
changed_file=catalog/controller/product/product.php
changed_file=catalog/view/template/product/product.twig
changed_file=catalog/view/template/checkout/payment_method.twig
php_l=ok
done=ok
```

## php -l result

```text
No syntax errors detected in PAY-001_phase2_stock-entry-and-availability_20260722.php
No syntax errors detected in catalog/controller/product/product.php
```

## Idempotency

Re-running on the patched controlled copy returned `already_applied=yes` and made no write.

## Rollback

The patch creates its own backup before every write:

```text
_patch_backups/PAY-001_phase2_stock-entry-and-availability_20260722-<timestamp>/files/
```

Restore the three files from that directory and clear OpenCart cache. No DB rollback is required.

## Run command (owner)

```bash
cd ~/public_html || exit
php PAY-001_phase2_stock-entry-and-availability_20260722.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] With sandbox Mono enabled, choose 3, 4, then 5 on an in-stock product; every choice opens `index.php?route=checkout/checkout` and preselects the same part count.
- [ ] Open stock checkout directly, choose “Оплатити частинами”, and verify the Mono 3/4/5 drawer appears immediately; choose each count and verify the selected chip and monthly amount change.
- [ ] Confirm that the provider block is below the “Оплатити частинами” button and above the trust line on an in-stock product.
- [ ] On products with quantity `0` / “Передзамовлення” / “Закінчився”, confirm that neither the credit button nor the provider block/modal is present.
- [ ] Recheck SimpleCheckout: no Mono method is exposed. Recheck Hutko, COD and IBAN in stock checkout.
- [ ] After any sandbox work, return `payment_mono_chast_status` to `0`.

## Side effects / risks

Checkout/product surface, medium risk. The direct URL intentionally bypasses only the known generated-link redirect and does not alter that redirect globally. The availability rule uses server-side product quantity, but normal cart and Mono backend validation stay unchanged; it is an offer/UI guard, not a replacement for final order validation.
