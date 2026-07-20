# Codex Report — ST-2c.5: display-only Nova Poshta tariff

Date: 2026-07-20

## Scope

Restores the canonical shipping contract after ST-2c made the real Nova Poshta tariff a positive OpenCart quote `cost`:

- customer payable total contains products/discounts only;
- Nova Poshta tariff remains visible as informational text;
- Hutko and fiscal product lists never receive shipping as a payable amount;
- guest and logged-in checkout use the same path;
- an already-open checkout session with the old positive quote is normalized on read/write.

No DB schema or data changes. Existing ST-2c.3/ST-2c.4 checkout sequencing and guest-session serialization remain intact.

Fresh input bundle: `booster-debug-ST2c5-display-only-shipping.tar.gz`  
SHA256: `8C179C0A147CAFFE082F9635A7508063C8F7BE82BDA6F2BAE5A5BB7BC2B68054`

## Root cause and fix boundary

Root cause: `PintaNovaPoshta::getQuote()` put the API tariff in the standard quote `cost`. OpenCart's stock shipping-total extension then added that value to `$total`; the resulting order/Hutko amount was therefore products plus delivery.

Fix:

1. Pinta quote payable `cost` is always `0.0`.
2. The formatted tariff is stored separately as `booster_display_text`.
3. `shipping_method` normalizes both new quotes and stale pre-patch session quotes at the server write boundary.
4. Checkout sidebar reads only `booster_display_text`, never the shipping total row.
5. The same informational text is stored inside the order's existing `shipping_method` JSON and shown on success without creating an `order_total` charge.

## Files touched

The host runner changes:

```text
extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php
catalog/controller/checkout/shipping_method.php
catalog/view/template/checkout/shipping_method.twig
catalog/view/javascript/checkout-state.js
catalog/view/javascript/checkout-reskin.js
catalog/view/template/checkout/checkout.twig
catalog/controller/checkout/success.php
catalog/view/template/checkout/success.twig
```

Deliverables:

```text
patches/ST-2c.5_display-only-shipping-contract_20260720.php
diagnostics/ST-2c.5_display-only-shipping-contract_report_20260720.md
```

## Dry-run result

Executed against a clean copy of the fresh ST-2c.5 bundle:

```text
php_l=ok file=extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php
php_l=ok file=catalog/controller/checkout/shipping_method.php
php_l=ok file=catalog/controller/checkout/success.php
payable_shipping_cost=0
display_field=booster_display_text
cache_clear=required
done=ok
```

Static post-run checks:

```text
Pinta quote cost occurrences: exactly 2, both cost => 0.0
old getBoosterShippingCost helper: absent
booster_display_text quote occurrences: exactly 2
checkout sidebar positive shipping-total fallback: absent
node --check checkout-state.js: ok
node --check checkout-reskin.js: ok
```

The current stock checkout order model was also checked: it JSON-encodes the full `shipping_method` array, so `booster_display_text` survives into the existing order field without a DB migration.

Review follow-up: the fresh Pinta model calculates `$shipping_cost_session_currency` in both quote branches before use:

```text
warehouse: API result line 46; session-currency assignment lines 51/55; quote starts line 60
doors:     API result line 78; session-currency assignment lines 83/86; quote starts line 91
```

Therefore the display helper receives an in-scope value for warehouse/parcel-locker and address delivery. `php -l` alone would not prove this, but the fresh source does.

## php -l result

```text
No syntax errors detected in patches/ST-2c.5_display-only-shipping-contract_20260720.php
No syntax errors detected in extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php
No syntax errors detected in catalog/controller/checkout/shipping_method.php
No syntax errors detected in catalog/controller/checkout/success.php
```

## Idempotency

Second run against the patched test tree:

```text
already_applied=yes
```

Live runner also SHA256-gates every target. A drifted or partially applied target stops before backup/write.

## Rollback

Automatic backup:

```text
_patch_backups/ST-2c.5_display-only-shipping-contract_20260720-<ts>/
```

Restore the eight affected files from that directory to their original relative paths, then clear theme/modification caches.

## Run command (owner)

```bash
cd ~/public_html || exit
php ST-2c.5_display-only-shipping-contract_20260720.php
rm -rf system/storage/cache/*
rm -rf system/storage/modification/*
```

Expected terminal tail:

```text
payable_shipping_cost=0
display_field=booster_display_text
cache_clear=required
done=ok
```

## Post-deploy QA checklist

- [ ] Guest, cart below ₴2000: checkout shows the real NP tariff, while `До сплати` equals products/discounts only.
- [ ] Logged-in customer, cart below ₴2000: same result.
- [ ] Switch all three delivery variants/address values: tariff updates, payable total remains product-only.
- [ ] Reload an already-open pre-patch checkout: stale positive shipping cost is removed from payable total and retained as display text.
- [ ] Cart at/above free-shipping threshold: `За наш кошт`; payable total still product-only.
- [ ] Hutko checkout: charged amount and product list exclude delivery.
- [ ] COD/IBAN: created order total excludes delivery; success page shows delivery method plus informational tariff.
- [ ] Payment selection, guest address flow, coupon, and final confirmation still work after each shipping change.

The task remains high-risk until the owner completes the live money smoke above. Local archive validation cannot prove the Hutko acquiring amount or fiscal receipt produced by production services.

## Side effects / risks

- Checkout/payment is high risk, but the monetary fix is at the source and session boundary rather than a visual subtraction.
- Existing order rows are not rewritten. Only orders created after deployment carry `booster_display_text` in their shipping-method JSON.
- If the NP API returns no usable tariff below the threshold, the display stays `—`; the payable total still safely excludes shipping.
- UX/content follow-up: consider explicitly stating near the tariff that delivery is paid to Nova Poshta upon receipt, so customers do not read it as part of `До сплати`.
- Merchant-feed delivery pricing must be audited separately against the display-only checkout contract; it is outside ST-2c.5.
