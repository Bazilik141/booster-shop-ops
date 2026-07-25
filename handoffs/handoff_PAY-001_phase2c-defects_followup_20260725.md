# Codex Handoff — PAY-001 Phase 2c: 4 defects from owner smoke test

Date: 2026-07-25 | Parent: `diagnostics/PAY-001_phase2c_checkout_smoke_plan_20260725.md` (findings section) + `handoffs/handoff_PAY-001_phase2c_cart-contract-gates_20260722.md`

## 1. Task ID

PAY-001 Phase 2c — defect follow-up (patch `PAY-001_phase2c_cart_contract_gates_20260725.php` already deployed; these are fixes/investigation on top, not a rebase).

## 2. Context

Owner ran a live manual smoke test on `payment_mono_chast_status=1` (test window) 2026-07-25. Result: **not passing**, `payment_mono_chast_status` returned to `0`. Four defects found, one of them (D1) confirmed by owner as a real failed order attempt, not a false alarm — no retry succeeded.

## 3. Goal

Fix D1 (blocking — real sandbox order creation fails) and D2/D3 (functional bugs). Get an explicit owner/Claude decision recorded for D4 before touching UI for it.

## 4. What to change

**D1 — [P0, BLOCKING] Mono sandbox `order/create` failed: "Не вдалося зв'язатися з monobank. Спробуйте ще раз."**
Owner confirmed: this credit attempt (3 payments, 700 грн) did not succeed, no retry worked. This is outside Phase 2c's own file set — it lives in the confirm/pending bridge added earlier (`extension/mono_chast/catalog/controller/payment/mono_chast.php`, per `diagnostics/PAY-001_phase2_credit_ui_report_20260721.md`: "mono_chast.php now makes the existing OpenCart confirm boundary usable ... calls create, displays the pending instruction"). Needed:
- Pull a fresh debug archive of that controller + relevant PHP error log lines around the failure timestamp (owner to provide, or Codex requests via the usual tar workflow).
- Confirm actual HTTP outcome of the `/api/order/create` call: timeout, DNS/TLS failure reaching `https://u2-demo-ext.mono.st4g3.com`, wrong store-id/secret read, malformed payload, or signature mismatch.
- Rule out interference from Phase 2c: Phase 2c does not touch this file, but confirm the new session keys (`pay001_mono_chast_from_modal`, `pay001_mono_chast_parts`) aren't being read by this controller in a way that breaks payload construction.
- This blocks steps 12–13 of the smoke plan entirely; nothing about real order creation can be signed off until this is root-caused.

**D2 — Coupon doesn't refresh the visible "Сума товарів / До сплати" total in checkout.**
Reproducible for ANY payment method, not just credit — apply a coupon via AJAX, total display stays stale until F5. Actual charged amount is correct (confirmed: order #275, 700 − 10% = 630 matched). Display-only.
- Confirm whether this predates Phase 2c or was introduced by it. Suspect: `checkout-state.js`'s new hook (`if (typeof window.bsCheckoutLoadPaymentMethods === 'function') { window.bsCheckoutLoadPaymentMethods(...) }` added on cart/coupon change) may be running instead of, not alongside, whatever existing totals-refresh call normally follows coupon apply.
- Fix so both the payment-method gate AND the totals summary refresh on coupon apply, without duplicating requests.

**D3 — Selected credit term "sticks" to the previous product after cart changes.**
Repro: add product A via modal with 3 payments → remove A, add product B via modal with 4 payments → checkout still shows 3 selected, not 4.
- Hypothesis (verify against actual code, not assumed): the first arrival auto-saves `session.payment_method.code = 'mono_chast.mono_chast_3'` (existing auto-select-on-arrival behavior in `payment_method.twig`, `if (!current && selected && selected.pay001Credit) savePayment(...)`). The second arrival correctly writes `session.pay001_mono_chast_parts = 4`, but the payment-method list render prioritizes the already-saved `session.payment_method.code` (still "3") over the fresh `preferred`, because `current` is non-empty.
- Likely fix: when `checkout.php` captures a *new* valid `mono_chast_parts` from a modal redirect, and the currently saved `session.payment_method.code` is itself a `mono_chast.*` code, update/clear that saved code too so the new preferred term wins on next render — not just the preference hint.

**D4 — Checkout payment list omits credit entirely (no muted/disabled row) when it's not yet selected and the gate is unavailable; the same-state hint only appears if credit was already selected before the coupon dropped total below threshold.**
Root cause (confirmed in code): `pay001MonoChastMethod()` returns `[]` when `gate['available']` is false, so the whole option is absent from `payment_methods` unless it's the currently-selected method (soft-blocker path). The product page always shows a muted row with a hint; checkout does not, for the "not yet selected" case.
- This was never explicitly specified for the checkout list in ADDENDUM-2 (which covered the product page) or in the phase2c handoff's soft-blocker section (which only covered "already selected, became ineligible").
- **Claude's recommendation:** show the same muted/disabled row with the dynamic hint on checkout too, for parity with the product page, regardless of whether it's currently selected. Proceed on this default unless owner overrides when reviewing this handoff.

## 5. Do not touch

- SimpleCheckout isolation marker (`PAY-001-SIMPLE-CHECKOUT-ISOLATION`), `system/library/url.php`.
- Order-write boundary / `checkout/confirm.php`'s create-on-explicit-click gate — D1 investigation may need to READ this file for context but must not alter when/how `addOrder`/`editOrder` fires.
- Fiscalization/Checkbox integration.
- Hutko / COD / IBAN logic and normalization.
- NCRM order-sync.
- DB schema/settings (no new columns/rows for these 4 fixes).
- sitemap.xml, robots.txt, redirects, canonical, .htaccess, Merchant feed, schema.
- Chip order/labels, cart-add contract, preorder gate, PUMB card, warning color tokens — already correct, do not touch while fixing D1–D4.

## 6. Likely files / areas (likely, not confirmed — verify against actual project files)

- D1: `extension/mono_chast/catalog/controller/payment/mono_chast.php`, its API client/signature code, PHP error log.
- D2: `checkout-state.js` (the new hook), whatever JS currently owns the coupon-apply success handler and totals summary re-render (not yet identified by file name — Codex to locate).
- D3: `catalog/controller/checkout/checkout.php` (session write on `mono_chast_parts`), `catalog/view/template/checkout/payment_method.twig` (`current`/`preferred` resolution, auto-save-on-arrival logic).
- D4: `catalog/controller/checkout/payment_method.php` (`pay001MonoChastMethod()` return-empty branch), `payment_method.twig` render loop.

## 7. Acceptance criteria

- D1: a real sandbox `order/create` for an eligible cart succeeds end-to-end (201, callback or poll reaches `WAITING_FOR_STORE_CONFIRM`), reproduced at least twice.
- D2: apply a coupon via AJAX with no page reload — "Сума товарів" and "До сплати" update immediately to the discounted amount, for credit and for at least one other payment method.
- D3: repro sequence above (swap product, different term) — checkout shows the term from the most recent modal selection, not the earlier one.
- D4: with credit not selected and cart below threshold (or containing a preorder item), the payment list shows the same muted/disabled row + dynamic hint as the product page, consistent regardless of prior selection order.
- No regression on Hutko/COD/IBAN, SimpleCheckout isolation, chip order/labels, cart-add contract, or the preorder/threshold gates already passing.

## 8. QA / smoke test

HIGH-RISK zone: checkout/payment. Re-run `diagnostics/PAY-001_phase2c_checkout_smoke_plan_20260725.md` in full (all 15 steps, not just the 4 defect repros) before recommending `payment_mono_chast_status=1` again, even for testing. `bs-checkout-smoke` protocol applies. Keep the test window short; return to `status=0` immediately after each session regardless of pass/fail.

## 9. Rollback note

Each fix should ship as its own small patch with the existing project pattern (source-anchor/SHA check, backup to `_patch_backups/`, `php -l`, restore-on-failure, idempotent). D1 in particular: if root cause requires changing signature/payload construction, back up the exact pre-change file and confirm `php -l` plus a dry sandbox call before considering it fixed.

## 10. Recommended status after execution

PAY-001 stays In progress, blocked on D1 specifically for any production step. After D1–D4 fixed and the full 15-step smoke test passes, dashboard subtask "Phase 2 — UI" can move to done and PAY-001 proceeds to the production/partner-registration track.
