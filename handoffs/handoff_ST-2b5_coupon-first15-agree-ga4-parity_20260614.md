# Codex Handoff — ST-2b.5: stock-checkout parity — coupon/First15 + agree/oferta + GA4 dedupe

Date: 2026-06-14. Parent: ST-2 / 2b (final parity before cutover). **HIGH-RISK** (Part A touches order totals/discounts; checkout/payment) → `bs-checkout-smoke` mandatory (incl. First15 steps 2–3). Combined handoff by owner request; implement and review **per part** (A → B → C), each behind its own acceptance/QA.

> Port **logic, not code verbatim**, from SimpleCheckout into the stock OC4 checkout. Built on the LIVE checkout after st2b1/2b2/2b3/2b4. **Codex must verify against live files** and port-source `extension/SimpleCheckout/catalog/controller/module/pinta_simple_checkout.php`.

## 1. Task ID
ST-2b.5 — bring the stock checkout to SimpleCheckout feature parity so cutover (2c) is safe:
- **A. Coupon / First15** (coupon UI is disabled on stock checkout today).
- **B. Agree / oferta** (single agree flow + required gate).
- **C. GA4 dedupe** (begin_checkout + purchase, fired once).

## 2. Context — confirmed in code (port source)
SimpleCheckout controller (`…/module/pinta_simple_checkout.php`):
- `applySimpleCheckoutCouponCode()` (≈3766): enables coupon total (`total_coupon_status=1`, `coupon_status=1`, default `total_coupon_sort_order=4`); empty→error «Введіть промокод»; `model_marketing_coupon->getCoupon()` invalid→error; **First15 special:** if code==`First15` AND `hasSimpleCheckoutCouponOrderUsage()` true → error «Промокод First15 вже був використаний для цього акаунта»; else `session.coupon=code`.
- `hasSimpleCheckoutCouponOrderUsage()` (≈3800): counts `oc_order_total` rows `code='coupon'` + `title LIKE '%(First15)%'` joined to `oc_order` with `order_status_id>0`, filtered by `customer_id` OR `LOWER(email)` (email from logged-in or `request.post['email']`). This is the duplicate-use guard.
- `coupon()` AJAX (≈2839): POST `remove`→unset `session.coupon` + «Промокод прибрано»; else `applySimpleCheckoutCouponCode`; preserves payment method from post; returns JSON.
- `applyPendingWelcomeCoupon()` (≈3724): if `session.welcome_coupon_pending` set + cart has products + no existing `session.coupon` → apply it (First15 for new registrations); sets `welcome_coupon_applied`/`welcome_coupon_error`; unsets pending.
- Cleanup: clears `welcome_coupon_pending/applied/error` + `coupon`/`reward` before payment redirect (≈3584) and after order creation. **Stock `checkout/success.php` already unsets `coupon`,`reward`,`welcome_coupon_*`** (verified) — so success-side cleanup exists.
- GA4: `buildSimpleCheckoutGa4BeginCheckoutPayload()` (≈3865) + `getSimpleCheckoutGa4Items()`/`Currency()`; twig fires `ps_dataLayer.pushEventData('begin_checkout', …)`; cart kept until success so a `purchase` payload can be built before success clears it.
- agree: controller sets `text_agree` (sprintf with `information/information/agree` link, `config_checkout_id`) and reads `session.agree`.

Stock side:
- Coupon UI disabled on stock cart/checkout (ST-0). Stock has `extension/opencart/total/coupon` total + a coupon controller — confirm whether to reuse its AJAX or add a thin custom endpoint.
- `checkout/success.php` clears coupon/reward/welcome_* (good for cleanup parity).
- GA4 on stock = `ps_enhanced_measurement` (ST-2a.10 added a gtag guard). 
- agree: ST-2b.4 payment script already posts `agree` to `checkout/payment_method.comment`; stock markup has `input[name="agree"]`.
- Confirm panel is client-side deferred (ST-2b.1/2b.3/2b.4): summary is a cached snapshot; real total comes from `confirm.confirm` on explicit click.

## 3. Goal (per part)
- **A:** Customer can apply/remove a coupon on the stock checkout; total updates correctly; First15 auto-applies for eligible new registrations and is blocked on reuse (per customer/email); discounts cleared on success. **No draft order created by applying a coupon.**
- **B:** A single agree/oferta checkbox with the correct oferta link; order cannot be placed unless agreed.
- **C:** `begin_checkout` fires once on checkout load and `purchase` fires once on success, with correct items/value, no duplicates.

## 4. What to change (per part)

### Part A — coupon / First15 (HIGH-RISK, totals)
- Enable coupon total on the stock checkout path (port `prepareSimpleCheckoutCouponTotal`: `total_coupon_status`/`coupon_status`/sort order). Add a coupon input + apply/remove control on the confirm/cart area.
- Coupon apply/remove endpoint: reuse stock coupon AJAX if shape fits, else a thin controller porting `applySimpleCheckoutCouponCode` + `coupon()` (incl. First15 reuse guard via `hasSimpleCheckoutCouponOrderUsage` — port the SQL exactly, by `customer_id` OR posted/customer email).
- Port `applyPendingWelcomeCoupon` so First15 set as `welcome_coupon_pending` at registration auto-applies once cart has products and no manual coupon present.
- **Integration with deferred panel (critical):** applying/removing a coupon changes totals, but the deferred confirm panel shows the **ST-2b.3 cached snapshot**. After a coupon apply/remove, **re-capture/refresh** the client-side summary so the shown total is correct; **do not call `checkout/confirm.confirm`** to do this (would create a draft — violates ST-2b.1/2b.4). Source the updated total from the coupon AJAX response (return totals) or a totals-only endpoint, not confirm.confirm.
- Keep success-side cleanup (already in `success.php`); ensure pre-payment cleanup parity if needed.

### Part B — agree / oferta
- Render a single agree checkbox with the oferta link (port `text_agree`: `information/information/agree` + `config_checkout_id`). Avoid duplicate agree controls.
- Gate placement: order cannot be confirmed unless agreed. Integrate with ST-2b.4 `bsCheckoutCanConfirm()` — add an agree requirement to the confirm-button enable condition (and/or server-side validation on `confirm.confirm`). Owner note: button gate today = shipping+payment; adding agree here is expected.
- Persist agree via the existing `payment_method.comment` post (ST-2b.4) / `session.agree`.

### Part C — GA4 dedupe
- `begin_checkout`: fire once on stock checkout load with correct items/currency (port `buildSimpleCheckoutGa4BeginCheckoutPayload`/items/currency logic onto the stock controller, or wire into `ps_enhanced_measurement`). Guard against double-fire (e.g., once per page load).
- `purchase`: fire once on `checkout/success` with the order's items/value/transaction_id=order_id; **dedupe** so a reload of success (ST-2b.2 made success reload-resilient!) does NOT re-fire purchase — use a one-shot guard (e.g., session flag keyed by order_id, cleared appropriately). This dedupe is essential given ST-2b.2 re-render on reload.
- Avoid double GA4 (stock `ps_enhanced_measurement` vs ported payload): ensure only one source fires each event.

## 5. Do not touch
Hutko `buildRequest`/amount/sign/api, `payment_hutko_shipping_include`, Checkbox/fiscalization, `getQuote`/NP cost, NP model, the `confirm.confirm` single-caller guarantee (ST-2b.4 — coupon refresh must NOT add a confirm.confirm call), ST-2b.2 success reload/ownership logic, st2a.4 void net, order_status config, DB schema, CRM payload shape, Merchant feed / Product schema (no structured-data change), `sitemap.xml`/`robots.txt`/canonical/redirects/`.htaccess`, shipping availability/logic (ST-2b.4 scope). Real shipping cost in totals remains **ST-2c**, not here.

## 6. Likely files / areas (verify against live; clear template cache after)
- Stock checkout: `catalog/view/template/checkout/checkout.twig`, `confirm.twig`, `payment_method.twig`; a coupon controller (reuse `extension/opencart/total/coupon` or thin custom under `catalog/controller/checkout/…`); `catalog/controller/checkout/success.php` (GA4 purchase hook only — cleanup already present); registration path for `welcome_coupon_pending` (port from SimpleCheckout / existing First15 wiring in `account/register.php`).
- GA4: stock `ps_enhanced_measurement` integration vs ported payload.
- Codex should verify against actual files and port logic, not copy SimpleCheckout code verbatim.

## 7. Acceptance criteria (measurable, per part)
**A (coupon/First15):**
1. Apply valid coupon on stock checkout → total reduces by the coupon; remove → total restored. **`oc_order` count unchanged** during apply/remove (no draft).
2. Deferred confirm panel shows the **updated** total after apply/remove (not the stale snapshot).
3. `First15` auto-applies for an eligible new registration (welcome_coupon_pending) once; appears in totals.
4. Reusing `First15` for the same customer/email (prior order with status>0) → blocked with «…вже був використаний…»; checkout still usable.
5. Invalid coupon → clear single error, checkout not broken.
6. On success, coupon/reward/welcome_* cleared (no leftover discount on next cart).

**B (agree):**
7. Single agree checkbox with working oferta link; «Оформити» cannot complete unless agreed (button disabled and/or server rejects), and agreeing enables it.

**C (GA4):**
8. `begin_checkout` fires exactly once on checkout load (correct items/value) — verify in dataLayer/GA debug.
9. `purchase` fires exactly once on success with order_id as transaction_id; **reloading the success page does NOT re-fire** purchase.
10. No duplicate GA4 events from two sources.

## 8. QA / smoke test
`bs-checkout-smoke` full, with First15 steps 2–3 run together, plus: apply/remove coupon (total correct, `oc_order` steady), First15 auto + reuse-block, invalid coupon, agree-gate (cannot place without agree), GA4 begin_checkout once + purchase once + no re-fire on success reload. Matrix {guest, logged-in} × {Hutko, COD, bank}. Verify Hutko amount and fiscal receipt unaffected by coupon (amount = discounted total − shipping). Confirm no draft orders at any step.

## 9. Rollback
Per-part backups to `_patch_backups/st2b5-*` (or `.bak`); restore + clear template/cache. Parts are independent — a faulty part can be reverted without the others. HIGH-RISK (Part A totals) — deploy in a quiet window; verify totals + Hutko amount + fiscal before/after. st2a.4 void net stays as fallback.

## 10. Recommended status after execution
Stock checkout reaches SimpleCheckout parity (coupon/First15, agree, GA4) → unblocks **2c (real shipping cost in totals + cutover `url.php`)**, the last gate. `На перевірці` → `Готово` only after owner QA per part. Related: ST-2 plan, handoff_ST-2b1/2b2/2b3/2b4, ST-2a.10 (gtag guard), success.php (cleanup).
