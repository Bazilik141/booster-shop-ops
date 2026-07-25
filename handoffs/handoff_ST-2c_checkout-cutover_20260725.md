# Codex Handoff — ST-2c: cutover to the new checkout (Mono credit stays disabled)

Date: 2026-07-25 | Parent: dashboard ST-2c, `plans/PAY_decomposition_mono-pumb-preorder_20260721.md`

## 1. Task ID

ST-2c subtask 3 — remove the SimpleCheckout redirect so the stock `checkout/checkout` becomes the default for all customers.

## 2. Context

ST-2c has been intentionally blocked on PAY-001 so the new checkout could ship with Monobank credit already built in, instead of doing checkout surgery twice. PAY-001 Phase 1/2/2c/2d are now complete: R-13.5 (shipping display fallback, mini-cart refresh), QA2 (phone display, term flattening), and the preorder-gate/product-threshold fixes are all owner-QA'd clean. Owner has decided to proceed with the cutover now, explicitly keeping `payment_mono_chast_status=0` — enabling Mono for real traffic is a separate, later decision gated on Monobank's own test purchase and activation.

## 3. Goal

Make the stock checkout the checkout every customer reaches through normal site navigation, replacing SimpleCheckout as the default, without enabling Mono credit for real traffic.

## 4. What to change

Remove/disable the SimpleCheckout redirect in `system/library/url.php` (the override, previously documented around lines 62-66, that rewrites `checkout/checkout` link generation to SimpleCheckout's route). After this change, normal "Оформити"/checkout links and buttons across the site should resolve to the stock checkout instead of SimpleCheckout. Confirm the exact current lines against live code before patching — do not rely on the remembered line numbers.

## 5. Do not touch

- `payment_mono_chast_status` — must stay `0`. This task is routing only, not credit enablement.
- Do not uninstall or delete the SimpleCheckout extension/files. It stays installed as a fallback until ST-6 (separate, not-started task).
- Order-write boundary, Mono API client, fiscalization/Checkbox, Hutko/COD/IBAN provider logic, NCRM, and the just-verified R-13.5/QA2/preorder-threshold fixes — unrelated, do not re-touch.
- DB/settings/schema — no changes expected.

## 6. Likely files / areas

`system/library/url.php` only, per the existing documented isolation mechanism — confirm against live code.

## 7. Acceptance criteria

- A fresh/incognito visitor's normal path (cart → proceed to checkout, any "Оформити" link/button sitewide) lands on the stock checkout, not SimpleCheckout.
- Guest and authorized checkout both complete end to end with Hutko, COD, and IBAN (credit stays hidden/disabled, `status=0`).
- Nova Poshta shipping display (including the "За тарифами Нової пошти" fallback) renders correctly for normal traffic, not just direct-URL testers.
- A preorder-item cart completes normally through a non-credit payment method on the now-default stock checkout.
- SimpleCheckout remains installed and functional for rollback.
- No change in Mono/credit visibility behavior beyond what already exists today (still fully gated by `payment_mono_chast_status`).

## 8. QA / smoke test

Highest-risk deploy in this task so far — this is the first time all real customers hit the new checkout at once. Full `bs-checkout-smoke` 15-step re-run required against the **now-default** checkout path (not the direct-URL test path used until now), plus: guest and authorized flows, First15/coupon, all three non-credit payment methods, Nova Poshta office/address/courier delivery, and at least one preorder-item order end to end. Recommend the owner do one more full manual pass immediately after deploy, then watch the first live orders closely.

## 9. Rollback note

Restore `url.php` from `_patch_backups/` (single file) — this immediately routes all traffic back to SimpleCheckout. No DB/order changes are part of this task, so rollback is clean and fast.

## 10. Recommended status after execution

Once deployed and the full smoke passes on live traffic, ST-2c → Done. ST-6 (remove the old checkout) becomes unblocked but should stay a separate, lower-urgency task — keep SimpleCheckout as a safety net for now. PAY-001 stays In progress, blocked on Monobank's own test/activation — unaffected by this task since Mono stays off.
