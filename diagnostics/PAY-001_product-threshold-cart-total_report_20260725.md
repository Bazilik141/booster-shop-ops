# Codex Report — PAY-001: product credit threshold includes cart

Date: 2026-07-25

## Scope

Implemented Defect B from `handoff_PAY-001_preorder-gate-breaks-checkout_20260725.md`.

Root cause: the product-page script calculated advisory credit eligibility as `unit price × selected quantity` and had no cart subtotal in its data contract.

The controller now exposes the current cart subtotal. The product-page eligibility and “add more” hint use:

```text
current cart subtotal + current product unit price × selected quantity
```

The modal's item total and monthly installment remain based only on the quantity being added. Checkout remains the authoritative gate.

## Files touched

```text
patches/PAY-001_product-threshold-cart-total_20260725.php — uploadable patch
catalog/controller/product/product.php                  — live target
catalog/view/template/product/product.twig              — live target
```

No DB, settings, order-write, Mono API, payment-provider, SimpleCheckout, R-13.5, or QA2 changes.

## Dry-run result

```text
changed_file=catalog/controller/product/product.php
changed_file=catalog/view/template/product/product.twig
php_l=ok
done=ok
```

Focused eligibility cases:

```text
existing_cart={"total":5381,"available":true,"remaining":0}
below={"total":370,"available":false,"remaining":130}
quantity_reaches={"total":540,"available":true,"remaining":0}
```

## php -l result

```text
No syntax errors detected in PAY-001_product-threshold-cart-total_20260725.php
No syntax errors detected in catalog/controller/product/product.php
```

## Idempotency

Re-running against the patched fixture returns:

```text
already_applied=yes
```

The patch validates exact source SHA256 values and exact one-count anchors before writing.

## Rollback

Backups are created at:

```text
_patch_backups/PAY-001_product-threshold-cart-total_20260725-<timestamp>/catalog/controller/product/product.php
_patch_backups/PAY-001_product-threshold-cart-total_20260725-<timestamp>/catalog/view/template/product/product.twig
```

Restore both files and clear OpenCart template/cache files.

## Run command (owner)

Run together with the separate preorder stock-gate patch after review.

## Post-deploy QA checklist

- [ ] With an empty cart, a ₴170 product remains muted at quantity 1 and shows the correct remaining amount.
- [ ] Increasing product quantity until the combined amount reaches the threshold activates the button.
- [ ] With an existing cart already above the threshold, the same ₴170 in-stock product is active at quantity 1.
- [ ] A zero-stock/preorder product remains inactive regardless of cart total.
- [ ] Modal item total and monthly installment do not include unrelated cart products.
- [ ] Checkout recalculates the authoritative payable amount and credit gate normally.
- [ ] Do not mark the full smoke complete and do not tell Monobank “ready” until all handoff QA passes.

## Side effects / risks

Risk: low-medium. The product-page state is advisory and uses the server-rendered cart subtotal at page-load time; checkout continues to validate the actual current cart.
