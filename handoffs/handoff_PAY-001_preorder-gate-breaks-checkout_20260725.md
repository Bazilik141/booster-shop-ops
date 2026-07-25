# Codex Handoff — PAY-001 Phase 2c: preorder gate breaks checkout entry + payment list; product-page threshold ignores cart total

Date: 2026-07-25 | Parent: owner live QA, post R-13.5 + QA2 deploy (both confirmed clean, unrelated to this handoff)

## 1. Task ID

PAY-001 Phase 2c — 2 new defects in the preorder/threshold gates, found outside the 15-step checklist.

## 2. Context

R-13.5 (shipping-display-fallback, minicart-checkout-refresh) and PAY-001 QA2 (phone display, term flattening) both passed owner QA clean — not affected by this handoff. During the same QA pass the owner found two separate, previously-undetected defects in the Phase 2c preorder/threshold gate logic.

## 3. Goal

Restore the originally specified behavior: a preorder item in the cart must only affect the Mono/credit option (muted row + hint), never checkout entry or the rest of the payment method list. Product-page threshold display must reflect the real current cart total, matching checkout's already-correct behavior.

## 4. What to change

**Defect A — [P0, BLOCKING] Preorder cart breaks checkout entry and empties the ENTIRE payment method list, including non-credit methods.**

Repro (owner-confirmed):
1. Add a "передзамовлення" (preorder, qty=0) product to cart.
2. Navigate directly to `?route=checkout/checkout` (stock checkout) — unexpectedly redirected to `?route=checkout/cart`.
3. From the cart page, use the normal "Продовжити" button to proceed — reach a checkout view where the Payment section shows OpenCart's standard `Немає доступних способів оплати для цієї доставки`. This happens with `payment_mono_chast_status=0` (module fully disabled) — meaning Hutko/COD/IBAN are also missing from the list, not just credit.

Expected: preorder in cart must never block checkout entry and must never remove other (non-credit) payment methods from the list. Only the Mono/credit option should be affected — shown as a muted/disabled row with a preorder hint, matching the product-page pattern and the already-shipped D4 fix for the below-threshold case (`payment_method.twig`), exactly as originally specified in the Phase 2c contract (smoke plan step 8, ADDENDUM-2).

Owner's hypothesis, unconfirmed — verify against live code before fixing: this looks like an early/incomplete implementation, as if something short-circuits checkout entry or the whole method list when a preorder item is detected, instead of only gating the credit option additively. Please root-cause against live code first:
- trace the actual source of the redirect in step 2 (which controller/condition sends checkout → cart);
- trace why the payment method list comes back fully empty in step 3, not just missing Mono;
- confirm whether "preorder in cart" is really the trigger, or something else coincidental in the test cart, before writing a fix.

This is the same rigor D1 got (diagnose against live evidence, don't guess-patch).

**Defect B — [lower severity, display-only] Product-page threshold hint ignores the existing cart total.**

Repro (owner screenshot): product priced ₴170 (below the ₴500 threshold on its own), cart already contains other items totaling ₴5211 (well above threshold). The product page still shows the credit option muted with `Оплата частинами доступна від 500 ₴ — додайте ще 330 ₴` — calculated only from this product's own price, ignoring the real current cart total. Checkout, with the same cart, correctly shows credit as available.

Expected: the product-page threshold display should use the real current cart total (existing cart + this product), matching checkout's already-correct logic, not just this product's standalone price.

## 5. Do not touch

- Order-write boundary / `confirm.php` create-on-click gate.
- Mono API client, signature, payload, callback, poll.
- SimpleCheckout isolation marker and `system/library/url.php`'s redirect-to-SimpleCheckout logic — unrelated to this bug, do not modify.
- Hutko/COD/IBAN provider logic itself (their disappearance from the list is the bug; the providers themselves are fine).
- R-13.5 (shipping-display-fallback, minicart-checkout-refresh) and QA2 (phone/term) fixes — just verified clean, do not re-touch.
- NCRM, DB/settings.

## 6. Likely files / areas (unconfirmed — verify against live code)

- Defect A: `catalog/controller/checkout/checkout.php` (entry/redirect condition), `catalog/controller/checkout/payment_method.php` (`getMethods()` / `pay001MonoChastGate()` / wherever preorder is detected).
- Defect B: `catalog/view/template/product/product.twig` or its companion JS (threshold display logic) — wherever the product-page credit gate computes its own total instead of fetching the current cart total.

## 7. Acceptance criteria

- Direct URL to `checkout/checkout` with a preorder item in cart opens checkout normally — no redirect to cart.
- In that state, Hutko/COD/IBAN appear normally in the payment list; only Mono/credit is muted with a preorder hint.
- Confirming an order with a non-credit method succeeds normally with a preorder item in cart.
- Product-page threshold hint reflects the real current cart total (cart + this product), verified by adding a below-threshold product to an already-above-threshold cart.
- No regression on the already-passing 15-step smoke, R-13.5, or QA2 fixes.

## 8. QA / smoke test

HIGH-RISK zone: checkout entry and the full payment method list are confirmed broken for a real path (currently low-traffic since stock checkout isn't the default route yet, but this directly blocks ST-2c cutover — after cutover, every customer with a preorder item would hit this). `bs-checkout-smoke` protocol applies. Full 15-step re-run (`diagnostics/PAY-001_phase2c_checkout_smoke_plan_20260725.md`) plus explicit pass/fail on both defects above, before PAY-001 is considered smoke-clean.

## 9. Rollback note

Standard project pattern: source-anchor/SHA check, backup to `_patch_backups/`, lint, restore-on-failure, idempotent no-op on re-run.

## 10. Recommended status after execution

PAY-001 stays In progress. Do not tell Monobank "ready to activate" and do not enable `payment_mono_chast_status` live until both defects here are fixed and re-verified. R-13.5 and QA2 stay Done — unaffected by this handoff.
