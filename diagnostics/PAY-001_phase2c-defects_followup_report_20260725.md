# Codex Report — PAY-001: Phase 2c defects follow-up

Date: 2026-07-25

## Scope

The handoff defines four independent defects. D1, D2, D3, and D4 are implemented as separate patches. The D1 change is based on the live 2026-07-25 error line, not an API/signature guess.

No DB, settings, SimpleCheckout, order-write, fiscalization, Hutko/COD/IBAN, NCRM, SEO, chip-order, cart-contract, preorder-gate, PUMB, or warning-token changes are included.

## Files touched

```text
patches/PAY-001_phase2c_d1_runtime_diagnostic_20260725.php
  read-only controller/config/cURL/DB-audit/log evidence collector

patches/PAY-001_phase2c_d1_oc4_payment_method_fix_20260725.php
  extension/mono_chast/catalog/controller/payment/mono_chast.php

patches/PAY-001_phase2c_d2_coupon_totals_refresh_20260725.php
  catalog/view/javascript/checkout-state.js
  catalog/view/javascript/checkout-reskin.js
  catalog/view/template/checkout/checkout.twig

patches/PAY-001_phase2c_d3_credit_term_refresh_20260725.php
  catalog/controller/checkout/checkout.php

patches/PAY-001_phase2c_d4_credit_unavailable_row_20260725.php
  catalog/view/template/checkout/payment_method.twig
```

## Diagnosis and minimal fixes

- D1: the live log records `Array to string conversion` at `mono_chast.php:42` during the failed attempt. OpenCart 4 `model_checkout_order->getOrder()` already decodes `payment_method` to an array, while the bridge cast that array to `"Array"` and called `json_decode()`. OpenCart's warning handler then echoed or redirected before valid JSON, which made the browser enter the generic `.fail()` branch. The patch accepts the OC4 array directly and retains a safe JSON-string fallback for legacy rows. It does not change the Mono request, signature, payload, retry, callback, poll, or order-write boundary.
- D2: `checkout/coupon` already returns authoritative `summary_html`, but the client ignored it and issued a separate `checkout/confirm` GET. The patch passes that response into `checkout-state`, updates the cached/visible summary immediately, and still reloads payment methods so the credit gate is recomputed. The fallback confirm GET remains only when a caller does not provide summary HTML. Both changed checkout JS assets receive a new cache-busting version in `checkout.twig`.
- D3: a new valid `mono_chast_parts` redirect updated the preference but retained an earlier `session.payment_method.code = mono_chast.*`. The patch clears only that earlier Mono credit selection; the existing modal preference then selects and saves the newest term.
- D4: the client synthesized the disabled credit row only when prior credit intent existed. The patch synthesizes it whenever Mono is configured and the server gate has a blocking reason, and puts the existing dynamic gate text directly in the disabled row. Confirm blocking remains limited to an actually selected credit method.

## Dry-run result

```text
D1: changed_file=mono_chast.php; php_l=ok; done=ok
D2: changed_file=checkout-state.js; changed_file=checkout-reskin.js; changed_file=checkout.twig; php_l=ok; done=ok
D3: changed_file=checkout.php; php_l=ok; done=ok
D4: changed_file=payment_method.twig; php_l=ok; done=ok
```

## Syntax result

```text
All four fix patch PHP files: php -l OK
D1 target mono_chast.php: php -l OK
D1 payment-method normalization: OC4 array, legacy JSON string, and malformed fallback OK
D3 target checkout.php: php -l OK
D2 checkout-state.js: node --check OK
D2 checkout-reskin.js: node --check OK
D4 embedded payment-method JavaScript: parsed by Node Function constructor OK
```

## Idempotency

Re-uploading and re-running each patch after a successful dry-run returns:

```text
already_applied=yes
```

## Rollback

Each patch prints its own backup path:

```text
_patch_backups/<patch-name>-<timestamp>/
```

Restore only the files listed under that patch. No SQL rollback is required.

## Run commands (owner)

```bash
cd ~/public_html || exit
php PAY-001_phase2c_d1_oc4_payment_method_fix_20260725.php && \
php PAY-001_phase2c_d2_coupon_totals_refresh_20260725.php && \
php PAY-001_phase2c_d3_credit_term_refresh_20260725.php && \
php PAY-001_phase2c_d4_credit_unavailable_row_20260725.php
```

After deployment, clear the OpenCart cache with the existing safe cache command used for Phase 2c.

## Post-deploy QA checklist

- [ ] Apply/remove a coupon with credit selected: visible subtotal, discount, and payable total update without F5; credit gate updates too.
- [ ] Repeat coupon apply/remove with Hutko or COD selected.
- [ ] Product A → 3 payments → checkout; remove A; product B → 4 payments → checkout; 4 remains selected.
- [ ] Direct below-threshold checkout with no prior credit selection shows a muted disabled credit row and threshold hint.
- [ ] Preorder cart with no prior credit selection shows a muted disabled credit row and preorder hint.
- [ ] Disabled row cannot be selected and does not block confirmation of a non-credit method.
- [ ] Hutko/COD/IBAN, SimpleCheckout isolation, cart-add contract, 3/4/5 order, PUMB card, and existing warning colors remain unchanged.
- [ ] Run the complete 15-step smoke plan and reproduce successful Mono create/poll twice.

## D1 root-cause evidence

```text
2026-07-25 07:31:07 - PHP Warning: Array to string conversion
file: extension/mono_chast/catalog/controller/payment/mono_chast.php
line: 42
```

## Side effects / risks

Checkout/payment remains a high-risk area. D1-D4 are narrow normalization/session/UI-state changes and do not alter order creation or totals calculations. Live browser QA is still required. PAY-001 remains in progress and Mono stays disabled outside the controlled sandbox test until the complete smoke passes.
