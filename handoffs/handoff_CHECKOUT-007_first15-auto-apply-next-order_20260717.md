# Codex Handoff — CHECKOUT-007: First15 auto-applies on the customer's actual next order (no manual code entry)

Date: 2026-07-17. Parent: CHECKOUT-006 (First15 next-order message, deployed). **HIGH-RISK zone** (checkout, order totals, coupon) → `bs-checkout-smoke` mandatory before Done.

> Live-file task: repo has no local copy of `payment_method.php` / `coupon.php` / `booster_coupon.php` (owner-server-only). Codex must anchor against LIVE files (post-CHECKOUT-006 state).

**Decision context (owner-confirmed 2026-07-17):** CHECKOUT-006 stopped applying First15 to the order the guest is placing while registering, and added a success-page message. Owner tested: current order total is now correct. But the owner does **not** want the customer to have to manually type "First15" on their next order — the discount must apply automatically, silently, the next time this customer actually checks out. This task builds that.

## 1. Task ID

CHECKOUT-007 — for a customer whose account was created via the guest-during-checkout flow (CHECKOUT-001/005/006 — the "tick save my data" shortcut in `payment_method.createAccount()`), automatically apply First15 the next time they start a genuinely separate checkout session, with no code entry required. Must not affect the order they were placing when they registered (CHECKOUT-006 already fixed that — do not regress it).

## 2. Context — confirmed in code

- There is no existing mechanism in this codebase for a coupon to auto-apply across a **new, later session**. Every existing First15 auto-apply path (`register.php`'s hook from CHECKOUT-004, the removed CHECKOUT-005 block) works by setting `session.data['welcome_coupon_pending']` and consuming it **within the same checkout session**, via `booster_coupon.php`'s `applyPendingWelcomeCoupon()`. A session flag does not survive to a later day/visit — this is why "auto-apply on the actual next order" needs a new, durable-per-customer signal, not a session flag.
- `hasCouponOrderUsage()` (in `catalog/model/checkout/booster_coupon.php`, CHECKOUT-004) already answers "has this customer ever completed an order using First15" — by `customer_id` OR email, against `oc_order_total`/`oc_order`. This is the correct eligibility check to reuse; do not duplicate its logic.
- `catalog/controller/checkout/payment_method.php`'s `createAccount()` already passes a `custom_field` array into `model_account_customer->addCustomer()` (confirmed live, used today to preserve any pre-existing guest custom fields). This is the established extension point to store a durable per-customer flag — same pattern ACC-002 already uses for `bs_np_v1` on addresses, just on the customer record instead.
- `catalog/controller/checkout/coupon.php`'s `summary()` action (CHECKOUT-004) already runs on every new-checkout page load (`ensurePromoCoupon()` calls `bsCheckoutRefreshPromoCouponSummary({quiet:true})` on init) and already calls `applyPendingWelcomeCoupon()`. This is the natural place to add the new, session-independent auto-apply check — no new page hook needed.
- Scope boundary (owner-implied, keep narrow): this task auto-applies First15 only for customers created via the guest-during-checkout flow (CHECKOUT-005/006). It does **not** change behavior for customers who register through the standalone `checkout/register` step (CHECKOUT-004 — already applies/shows correctly in the same session) or through `account/register` outside checkout (not currently wired to First15 at all — out of scope; flag to owner as a possible future gap, do not fix here without a separate decision).

## 3. Goal

A customer who registered via the guest-during-checkout shortcut, and has never used First15, gets it applied automatically — no typing, no visible promo-code step — the next time they load a checkout page with items in the cart and no coupon already set. It applies exactly once (their first real order after registering), then behaves like any customer who has used First15: blocked from reapplying via the existing reuse guard. The order they were placing at registration time is unaffected (already correct per CHECKOUT-006).

## 4. What to change

- **`catalog/controller/checkout/payment_method.php`** — in `createAccount()`, where `custom_field` is assembled for `addCustomer()`, add `'bs_first15_pending' => 1` into that array (merge with whatever guest custom fields already exist there — do not overwrite them). This replaces or sits alongside the CHECKOUT-006 session-only `checkout001_first15_offer_pending` flag (that flag still drives the one-time success-page message; keep it as-is unless Codex finds a cleaner shared source — confirm with owner before removing it, since the message copy may need to change from "введіть промокод" wording to "застосуємо автоматично" wording either way — see §4 twig note below).
- **`catalog/model/checkout/booster_coupon.php`** — add a new method, e.g. `applyPendingFirst15ForCustomer(int $customer_id): array`, that: reads the customer's `custom_field.bs_first15_pending`; if not set, returns `[]`; if set, checks `hasCouponOrderUsage('First15', <customer's email>)` — if already used (should not normally happen, but do not double-apply), clear the flag and return `[]`; if genuinely eligible and `session.coupon` is empty, apply via the existing `applyCouponCode('First15', $email)` path, and on success clear `custom_field.bs_first15_pending` on the customer record (load `account/customer`, update custom_field, do not touch other customer fields). On failure, leave the flag set (retry on the next page load) and do not surface a hard error to the page — this is a silent convenience feature, not a user-facing action.
- **`catalog/controller/checkout/coupon.php`** — in `summary()`, after the existing `applyPendingWelcomeCoupon()` call, if the customer `isLogged()` and no coupon got applied by that call, call the new `applyPendingFirst15ForCustomer((int)$this->customer->getId())` and merge its result into the response the same way `applyPendingWelcomeCoupon`'s result already is.
- **`catalog/view/template/checkout/success.twig`** (CHECKOUT-006 block) — update the copy to reflect that no code entry is needed, e.g. "Дякуємо за реєстрацію! На ваше наступне замовлення ми автоматично застосуємо знижку 15%." Confirm with owner if exact wording matters before shipping (content rule: no fake guarantees, natural wording — keep it factual).
- Codex should verify current anchors against live post-CHECKOUT-006 files; the exact `custom_field` assembly line in `createAccount()` and the exact `summary()` body in `coupon.php` may not match earlier patch source verbatim after CHECKOUT-006's edits.

## 5. Do not touch

- The order that was placed when the customer registered (CHECKOUT-006's fix) — this task must never re-introduce a coupon change to that order. The new auto-apply logic must only run on a **later** `checkout/coupon.summary` call, i.e., a fresh checkout page load with a cart that is not the just-completed order.
- `catalog/controller/checkout/register.php` and the standalone `checkout/register` First15 flow (CHECKOUT-004) — already correct, out of scope.
- The NP-address validation from CHECKOUT-005 — unrelated, must remain exactly as deployed.
- `catalog/controller/checkout/success.php`'s reload-resilience / Hutko-return logic (ST-2b.2) and the `checkout001_first15_offer_pending` session-flag consumption added by CHECKOUT-006 — copy-only change to the twig text, no logic change there.
- Order-creation gates (ST-2b6e, ST-2b6d, RD-13.1J), `confirm.confirm`, Hutko `buildRequest`/amount/sign, Checkbox/fiscalization, NP shipping cost/quote (ST-2c), order statuses, DB schema.
- Do not extend this to `account/register` (standalone, outside checkout) or to any customer who did not go through the guest-during-checkout flow — flag as a possible future ask, do not implement here.
- `sitemap.xml`, `robots.txt`, canonical, redirects, `.htaccess`, Merchant feed, schema/JSON-LD.

## 6. Likely files / areas (verify against LIVE, post-CHECKOUT-006)

- `catalog/controller/checkout/payment_method.php` — persist `bs_first15_pending` on the new customer.
- `catalog/model/checkout/booster_coupon.php` — new `applyPendingFirst15ForCustomer()` method.
- `catalog/controller/checkout/coupon.php` — call the new method from `summary()`.
- `catalog/view/template/checkout/success.twig` — copy update only.
- `catalog/model/account/customer.php` — verify the exact method to read/update a customer's `custom_field` (e.g., `editCustomField()` or equivalent) before assuming its name/signature; do not guess.

## 7. Acceptance criteria (measurable)

1. Guest ticks "save my data", registers during checkout, completes the order: order total is full price, unchanged from CHECKOUT-006 (no regression).
2. That customer's account record carries `custom_field.bs_first15_pending = 1` immediately after registration (verify directly, e.g., via admin customer edit or a read-only check — do not assume from code alone).
3. Same customer, in a **new** session/day, logs in (or is already logged in), adds a product, opens checkout: without typing anything, the confirm panel shows `Купон (First15) −15%` and the reduced total — sourced from the existing `coupon.summary` auto-refresh on page load.
4. After that second order completes with First15 applied, the customer's `custom_field.bs_first15_pending` is cleared; a third order does not get an automatic discount and manually typing `First15` is blocked by the existing reuse guard (unchanged behavior).
5. A customer who already has a manually-entered different coupon active when `coupon.summary` runs does not get First15 silently swapped in.
6. Guest checkout without account creation, and standalone `checkout/register`-flow customers: unaffected, no `bs_first15_pending` ever set for them by this task.
7. No `confirm.confirm` calls added by this change; no Hutko amount/signature path touched.
8. If the auto-apply fails for any reason (coupon disabled, edge case), checkout still functions normally at full price — never a hard error blocking checkout.

## 8. QA / smoke test

Full 11-step `bs-checkout-smoke`, plus: register via guest-during-checkout (COD and Hutko) → confirm order 1 total is full price → start a **second**, separate checkout session as that customer → confirm First15 auto-applies and displays before confirm, and the Hutko amount (if used) matches the discounted total → complete order 2 → confirm `bs_first15_pending` cleared and a manual `First15` entry is now blocked on a hypothetical order 3. Record max order ID + status-0 draft count before/after.

## 9. Rollback note

Back up every changed file to `_patch_backups/CHECKOUT-007_<...>-<timestamp>/` before writing; restore those files and clear `cache.*` + `template/*` via `DIR_CACHE`. Any customer left with a stray `custom_field.bs_first15_pending = 1` after rollback is harmless — stock code ignores unknown custom-field keys, and no order-total logic depends on it once this patch is reverted.

## 10. Recommended status after execution

Patch → `На перевірці` → owner QA (incl. `bs-checkout-smoke` + the two-session §8 scenario, both COD and Hutko) → `Готово`.
