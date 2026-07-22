# Codex Report — PAY-001: Phase 2 credit UI + sandbox-ready confirm bridge

Date: 2026-07-21

## Scope

Implements the selected architecture from the RESET handoff:

- keeps the deployed SimpleCheckout isolation in place;
- adds a virtual mono_chast.mono_chast_{3|4|5} method only inside stock checkout/checkout;
- adds the product chooser, modal, checkout drawer, inactive official PUMB offer, supplied payment assets, and the stock-checkout Mono pending bridge.

No DB schema/settings, checkout/confirm.php, Hutko, COD, IBAN, NCRM or system/library/url.php changes are made.

The credit method is auto-selected only when checkout/checkout receives a valid mono_chast_parts=3|4|5 from the product modal. A direct or malformed checkout URL clears that session flag and leaves payment unselected.

## Files touched by the uploadable patch

```text
catalog/controller/product/product.php
catalog/view/template/product/product.twig
catalog/controller/checkout/checkout.php
catalog/controller/checkout/payment_method.php
catalog/view/template/checkout/payment_method.twig
catalog/view/stylesheet/boostershop-ds.css
catalog/view/template/common/header.twig
extension/mono_chast/catalog/controller/payment/mono_chast.php
catalog/view/image/payment/pay001-mono-label.png
catalog/view/image/payment/pay001-pumb.svg
```

### Exact checkout changes

- checkout.php records the valid modal selection in session and clears it for direct visits.
- payment_method.php injects the virtual Mono payment-map group in getBoosterCheckoutPaymentMethods(), after the existing Hutko/COD/IBAN filter has completed; it does not call the isolated Mono model or alter that filter.
- payment_method.twig preserves the current booster_category normalisation and checkout state-revision protocol, then renders the credit drawer and treats mono_chast separately so Hutko remains its own card-payment route.
- mono_chast.php now makes the existing OpenCart confirm boundary usable: checkout/confirm creates the order, then Mono index() renders the required #button-confirm, calls create, displays the pending instruction, and polls the existing server-side state route.
- mono_chast.php accepts documented HTTP 409 duplicate-create recovery and changes products[].sum from line total to unit price.
- product.php/product.twig gate and render the product modal; CSS and header.twig add the scoped presentation rules and cache-bust.

### Asset provenance

- pay001-mono-label.png is embedded from the supplied repository asset handoffs/assets/PAY-001-UI/monobank-label/black/flat/Label_black_400_px.png.
- pay001-pumb.svg is embedded verbatim from the owner-supplied archive Інтеграція ПУМБ Сплачуй частинами.zip, nested archive Логотипи по Сплачуйте частинами та ПУМБ.zip, file SVG Logo SCH/SVG/PUMB_SCH_logo.svg. It is only an inactive “СКОРО БУДЕ” card; no PUMB API or payment method is enabled.

## Local dry-run

Tested on a controlled copy of the owner-supplied current-source archive:

```text
booster-debug-PAY001-phase2-anchor.tar.gz
```

```text
backup=.../_patch_backups/PAY-001_phase2_credit_ui_20260721-20260721-135904
changed_file=<10 files>
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
No syntax errors detected in extension/mono_chast/catalog/controller/payment/mono_chast.php
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
- [ ] After sandbox credentials and point_id are saved, temporarily enable only for sandbox: product chooser and stock checkout/checkout show Mono 3/4/5; SimpleCheckout stays without it.
- [ ] Directly open checkout/checkout without mono_chast_parts (and with an invalid value): no payment is preselected. Enter from the product modal: only then Mono is preselected.
- [ ] Choose 3, 4 and 5 from a product: the matching virtual code is saved and reaches the existing Mono confirm() controller after the normal order-write boundary.
- [ ] On place-order, verify the pending instruction appears, a 201 and a 409 both retain the same Mono order_id, and polling handles WAITING_FOR_CLIENT, FAIL and WAITING_FOR_STORE_CONFIRM.
- [ ] Verify the exact sandbox payload: unit-price products[].sum, total_sum reconciliation with delivery/discounts, invoice/point_id, HTTPS callback route, and callback signature.
- [ ] Verify the PUMB card is inactive and reads Сплачуйте частинами ПУМБ / СКОРО БУДЕ.
- [ ] Run the 11-step bs-checkout-smoke in both entry contexts, including mandatory Hutko, COD and IBAN regression.
- [ ] After sandbox QA restore payment_mono_chast_status=0; leave SimpleCheckout isolation deployed.

## Risk

High-risk checkout/payment surface. The patch fails before writing if the installed Mono extension or its SimpleCheckout-isolation marker is missing, or if any current source anchor no longer matches. It remains disabled while payment_mono_chast_status=0. Local validation is not deployment, API, sandbox, callback, or live checkout proof; total_sum reconciliation and the public callback route remain mandatory sandbox checks.
