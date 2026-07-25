# Codex Report — PAY-001: Phase 2c defects follow-up

Date: 2026-07-25

## Scope

The handoff defines four independent defects. D2, D3, and D4 are implemented as separate patches. D1 remains evidence-gated: the observed browser `.fail()` message proves that `mono_chast.confirm` returned a non-2xx response or invalid JSON, but the 2026-07-25 PHP/OpenCart error log is not present in the local backup. No Mono API/signature/payload change was guessed.

No DB, settings, SimpleCheckout, order-write, fiscalization, Hutko/COD/IBAN, NCRM, SEO, chip-order, cart-contract, preorder-gate, PUMB, or warning-token changes are included.

## Files touched

```text
patches/PAY-001_phase2c_d1_runtime_diagnostic_20260725.php
  read-only controller/config/cURL/DB-audit/log evidence collector

patches/PAY-001_phase2c_d2_coupon_totals_refresh_20260725.php
  catalog/view/javascript/checkout-state.js
  catalog/view/javascript/checkout-reskin.js

patches/PAY-001_phase2c_d3_credit_term_refresh_20260725.php
  catalog/controller/checkout/checkout.php

patches/PAY-001_phase2c_d4_credit_unavailable_row_20260725.php
  catalog/view/template/checkout/payment_method.twig
```

## Diagnosis and minimal fixes

- D2: `checkout/coupon` already returns authoritative `summary_html`, but the client ignored it and issued a separate `checkout/confirm` GET. The patch passes that response into `checkout-state`, updates the cached/visible summary immediately, and still reloads payment methods so the credit gate is recomputed. The fallback confirm GET remains only when a caller does not provide summary HTML.
- D3: a new valid `mono_chast_parts` redirect updated the preference but retained an earlier `session.payment_method.code = mono_chast.*`. The patch clears only that earlier Mono credit selection; the existing modal preference then selects and saves the newest term.
- D4: the client synthesized the disabled credit row only when prior credit intent existed. The patch synthesizes it whenever Mono is configured and the server gate has a blocking reason, and puts the existing dynamic gate text directly in the disabled row. Confirm blocking remains limited to an actually selected credit method.
- D1: `mono_chast.php` normally returns JSON for API rejections, including cURL HTTP `0`; therefore the generic AJAX `.fail()` text points to an uncaught PHP/DB error, route-level HTTP error, or invalid output. Fresh logs are required before changing the bridge.

## Dry-run result

```text
D2: changed_file=checkout-state.js; changed_file=checkout-reskin.js; php_l=ok; done=ok
D3: changed_file=checkout.php; php_l=ok; done=ok
D4: changed_file=payment_method.twig; php_l=ok; done=ok
```

## Syntax result

```text
All three patch PHP files: php -l OK
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

Do not deploy D2-D4 until the review of this partial follow-up is accepted. D1 still blocks PAY-001 sandbox sign-off.

```bash
cd ~/public_html || exit
php PAY-001_phase2c_d2_coupon_totals_refresh_20260725.php
php PAY-001_phase2c_d3_credit_term_refresh_20260725.php
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
- [ ] After the D1 root-cause patch, run the complete 15-step smoke plan and reproduce successful Mono create/poll twice.

## D1 evidence still required

Collect the current Mono controller plus the 2026-07-25 PHP/OpenCart error logs. The failed order should not be retried merely to gather this archive.

Run the read-only diagnostic and return its terminal output:

```bash
cd ~/public_html || exit
php PAY-001_phase2c_d1_runtime_diagnostic_20260725.php
```

## Side effects / risks

Checkout/payment remains a high-risk area. D2-D4 are narrow session/UI-state changes and do not alter order creation or totals calculations. Live browser QA is still required. PAY-001 remains in progress and Mono stays disabled until D1 is fixed and the complete sandbox smoke passes.
