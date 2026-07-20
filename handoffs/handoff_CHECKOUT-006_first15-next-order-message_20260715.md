# Codex Handoff — CHECKOUT-006: First15 becomes a next-order offer for the guest-register-during-checkout flow

Date: 2026-07-15. Parent: CHECKOUT-005 (guest-account NP + First15, deployed 2026-07-15). **HIGH-RISK zone** (checkout, payment amount, success page) → `bs-checkout-smoke` mandatory before Done.

> Live-file task: repo has no local copy of `checkout.twig` / `success.twig` / `payment_method.php` (owner-server-only). Codex must anchor against LIVE files (post-CHECKOUT-005 state).

**Decision context (owner-confirmed 2026-07-15):** CHECKOUT-005 shipped First15 auto-applying to the customer's **current** order the moment they register during checkout. Owner-tested order #241 confirms the discount total is calculated correctly in the actual order — but the confirm step (before clicking «Оформити») still shows the full price, so the customer commits to one price and is charged another. Owner chose **not** to fix this by making the discount visible pre-confirm (that requires synchronizing account creation with the Hutko payment-amount calculation — bigger, riskier). Instead: **stop applying First15 to the current order** in this flow, and tell the customer on the success page that 15% is waiting for their *next* order.

## 1. Task ID

CHECKOUT-006 — remove the immediate First15 auto-apply added by CHECKOUT-005 inside `checkout/payment_method.createAccount()` (guest ticks "save my data" during checkout → account created just before order confirmation). Replace it with a success-page message: "Дякуємо за реєстрацію — 15% знижка чекає на наступне замовлення (промокод First15)". The current order goes through at full price, same as if no coupon existed.

## 2. Context — confirmed in code and live testing

- CHECKOUT-005 (`patches/CHECKOUT-005_guest-account-np-first15_20260715.php`) added two things inside `payment_method.php`'s `createAccount()`: (a) Nova Poshta address re-validation before `addAddress()` — **confirmed fixed by owner, keep exactly as-is**; (b) immediately after `checkout001_account_customer_id` is set, if `session.coupon` is empty: sets `welcome_coupon_pending = 'First15'` and calls `model_checkout_booster_coupon->applyPendingWelcomeCoupon($email)` right there, applying the coupon to the order that is about to be confirmed.
- Owner-tested result (order #241, COD): total correctly shows `Купон (First15) −0.15`, final `0.85`. So the order-total math is correct. The problem is purely sequencing: the confirm-panel preview the customer sees **before** clicking «Оформити» was captured earlier in the flow and still shows the full price — the coupon lands between that preview and the actual order write. For COD this is a checkout software issue, no payment leaves the customer's hands until delivery; for Hutko (online prepayment), **it is not yet verified whether the amount actually sent to Hutko reflects the discount** — this task removes the discrepancy at the source instead of chasing that verification.
- This only affects the **guest-registers-during-checkout** path. The **standalone registration** path (register via `checkout/register`, then check out separately, logged in) is unaffected by CHECKOUT-005 and unaffected by this task: CHECKOUT-004's `register.php` hook queues `welcome_coupon_pending`, and the promo widget's own `coupon.summary` call (fired on the new-checkout page load / after `register.save`, per CHECKOUT-004) consumes and displays it **before** the customer reaches the confirm step. Owner confirmed this path has no bugs — do not touch it.
- Reuse guard: `hasCouponOrderUsage()` (in `catalog/model/checkout/booster_coupon.php`, CHECKOUT-004) blocks reapplying First15 only once an order with `code='coupon'` + title matching `(First15)` and `order_status_id > 0` exists for that customer/email. If CHECKOUT-006 stops applying the coupon to the current (first) order, that row never gets created — so First15 remains legitimately usable, unprompted, the next time this customer manually types it into the promo box. **No new "queue for later" mechanism is needed** — removing the auto-apply is sufficient by itself.
- `catalog/controller/checkout/success.php` currently clears `order_id`, payment/shipping/comment/coupon/agree session keys on first render (confirmed in `handoff_ST-2b2_success-page-hutko-fiscal-spacing_20260614.md`); it does **not** already clear `checkout001_*` keys as of that patch, but CHECKOUT-001/005 added new ones since — Codex must re-verify the current live cleanup list before reading/relying on any `checkout001_*` session flag in `success.php`.
- `success.php`/`success.twig` are a **HIGH-RISK, previously-hardened zone** (ST-2b.2 fixed Hutko-return reload-resilience, fiscal-text detection, and spacing there). This task must be strictly additive — one new conditional block — and must not touch that existing logic.

## 3. Goal

A guest who ticks "save my data" during checkout and registers: their order is placed at the same full price they saw on the confirm screen (no last-second discount, no mismatch). On the success page, they see a short thank-you message telling them First15 (15% off) is available for their next order. The standalone registration flow, and guest checkout without account creation, are both unchanged.

## 4. What to change

- **`catalog/controller/checkout/payment_method.php`** — inside `createAccount()`, remove the CHECKOUT-005 block that sets `welcome_coupon_pending` and calls `applyPendingWelcomeCoupon()` (and the `$json['first15_applied']` field it set). Do not touch the NP-address-validation code CHECKOUT-005 added right before it — that stays. In its place, set a lightweight session flag only (no coupon/session.coupon mutation at all), e.g. `checkout001_first15_offer_pending = 1`, used solely to drive the success-page message.
- **`catalog/controller/checkout/success.php`** — on the same render pass where other session keys are currently read (before they get cleared), read the new flag (or `checkout001_account_processed === 'created'` + `checkout001_account_customer_id`, whichever is more reliable against current live code — Codex to confirm) and pass a boolean, e.g. `order_data.show_first15_offer`, to the template. Clear the flag the same way the existing keys are cleared on that same render.
- **`catalog/view/template/checkout/success.twig`** — add one conditional block, shown only when `order_data.show_first15_offer` is true: a short message ("Дякуємо за реєстрацію! Даруємо вам знижку 15% на наступне замовлення — промокод First15.") placed near the order-accepted header, matching existing card styling. It is acceptable for this message to not survive a forced reload (e.g., Hutko cookie-consent reload per ST-2b.2) — it's a one-time courtesy message, not order-critical data; do not extend the ST-2b.2 reload-resilience mechanism to cover it unless trivial.
- Codex should verify current anchors against the live post-CHECKOUT-005 files — do not assume line numbers from the June/July patches above still match exactly.

## 5. Do not touch

- `catalog/controller/checkout/register.php` and the standalone-registration First15 flow (CHECKOUT-004) — already correct, out of scope.
- The NP-address-validation part of CHECKOUT-005 (`checkout005PrepareNpAddress`, `checkout005ApplyNpAddressToCheckoutSession`) — confirmed fixed, must remain exactly as deployed.
- `catalog/model/checkout/booster_coupon.php`, `catalog/controller/checkout/coupon.php` — the coupon/First15 backend itself is not being changed, only stopping one caller from invoking it.
- Success-page Hutko reload-resilience, return-cookie mechanism, fiscal-text branching, and spacing fix from ST-2b.2 — additive change only, no refactor.
- Order-creation gates: ST-2b6e read-only render + explicit `confirm::index($allow_order_write)`, ST-2b6d trusted-click gate, RD-13.1J CAPTCHA payload, ST-2b.1 deferred-confirm flow.
- Hutko `buildRequest`/amount/sign/api, Checkbox/fiscalization, NP shipping cost/quote logic (ST-2c scope), order statuses, cart/order totals calculation.
- `sitemap.xml`, `robots.txt`, canonical, redirects, `.htaccess`, Merchant feed, schema/JSON-LD, DB schema.

## 6. Likely files / areas (verify against LIVE, post-CHECKOUT-005)

- `catalog/controller/checkout/payment_method.php` — remove auto-apply block inside `createAccount()`.
- `catalog/controller/checkout/success.php` — read flag, pass to template, clear flag.
- `catalog/view/template/checkout/success.twig` — new conditional message block.
- Codex should verify against actual project files; re-check the exact current CHECKOUT-005 anchor text before removing it (it may differ slightly from the original patch source if further edits landed since deploy).

## 7. Acceptance criteria (measurable)

1. Guest ticks "save my data", registers during checkout, completes an order (COD): order total = full price, matches exactly what the confirm screen showed before «Оформити» — no coupon line, no last-second change.
2. Same scenario with Hutko: amount sent to Hutko and the order total both equal the full (undiscounted) price — no ambiguity left to verify separately, since no discount is applied here at all.
3. `payment_method.createAccount` response no longer mutates `session.coupon` / `welcome_coupon_pending` for this flow.
4. Success page (first view) shows the First15-next-order message for this scenario only.
5. That same customer, on a **separate, later** order, manually types `First15` in the promo box → applies successfully (−15%), proving the reuse-guard does not block their legitimate first use.
6. Standalone registration (register via `checkout/register`, then check out separately) is unaffected: First15 still auto-applies and is visible in the confirm panel before the customer clicks «Оформити», exactly as today.
7. Guest checkout without the "save data" tick: unaffected, no message, no session flag set.
8. A guest who manually entered a different coupon before registering keeps that coupon (no override) — matches the existing `empty(session.coupon)` guard pattern.
9. No new `confirm.confirm` calls; order-creation gate, Hutko amount path, and NP-address fix untouched.

## 8. QA / smoke test

Full 11-step `bs-checkout-smoke`, plus this matrix: {guest+register (COD), guest+register (Hutko), standalone register → separate checkout, plain guest no-register} — verify order total always matches the confirm-panel preview exactly, success message appears only in the guest+register case, and First15 is usable manually on the affected customer's next order. Record max order ID + status-0 draft count before/after; confirm Hutko amount matches order total for the Hutko cell specifically.

## 9. Rollback note

Back up every changed file to `_patch_backups/CHECKOUT-006_<...>-<timestamp>/` before writing; restore those files and clear `cache.*` + `template/*` via `DIR_CACHE` to roll back. Rollback returns to CHECKOUT-005's current behavior (auto-apply to current order, no success message) — not to a coupon-less state.

## 10. Recommended status after execution

Patch → `На перевірці` → owner QA (incl. `bs-checkout-smoke` + §7 checks, both COD and Hutko) → `Готово`.
