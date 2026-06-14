# Codex Handoff — ST-2b.2: checkout/success — Hutko-return resilience + fiscal-text match + bottom spacing

Date: 2026-06-14. Parent: ST-2 / 2b. **HIGH-RISK** (checkout/success + Hutko return + fiscalization-adjacent copy) → `bs-checkout-smoke` mandatory; risk-gate via `bs-seo-risk-gate` not needed (no SEO/index zone). Surfaced during ST-2b.1 smoke. Pre-existing, NOT caused by ST-2b.1 (that patch did not touch success page).

## 1. Task ID
ST-2b.2 — three independent success-page defects found on live during ST-2b.1 testing (orders #166–169):
- **(a)** Hutko: after payment return, accepting the cookie banner (page reload) drops the redesigned success page into its generic fallback («Ваше замовлення прийнято!» without order details — perceived as "old design").
- **(c)** Hutko orders show the wrong fiscal-receipt line («…буде відправлено… при відправці замовлення.») instead of the Hutko line («Фіскальний чек відправлено…»).
- **(d)** Large empty whitespace block below the success card.
- (b) shipping-cost display on success is **out of scope here → ST-2c** (depends on real shipping cost in totals).

## 2. Context — confirmed in code (backup 6.14.2026_10-00-15)
`catalog/controller/checkout/success.php`:
- Captures `$order_id = $this->session->data['order_id']`, then on first view **clears** `order_id` (+ payment/shipping/comment/coupon/agree session keys) and `$this->cart->clear()`.
- Builds `order_data['payment_code'] = strtolower(trim($order_info['payment_code'] ?? ''))`.
- Skips zero-value `shipping`/`pinta_nova_poshta` totals (`R-11-FIX`) — why shipping is hidden today (real value lands in ST-2c).

`catalog/view/template/checkout/success.twig`:
- Full redesign renders only `{% if order_data.order_id %}`; otherwise `{% else %}` → `bs-success-fallback` with generic `text_message`. **This else-branch is the "old design" the owner sees.**
- Fiscal line: `{% if payment_code == 'hutko' %}` → «відправлено»; `cod`/`післяплат` → «в день отримання»; `{% else %}` → «при відправці». For Hutko order #169 the page rendered the **else** text → the stored `payment_code` is **not** literally `hutko` (OpenCart 4 extension code is almost certainly `hutko.hutko`), so the exact match misses.
- Has `<style>/* r11-spacing-fix */ .bs-success-hero{margin-top:1.5rem}</style>` — spacing already partially patched here.

`extension/hutko/catalog/controller/payment/hutko.php`:
- `response()` → `restoreReturnSession()` (signed return cookie, 1800s, carries `order_id`+`customer_id`+`customer_token`) → `redirect(... index.php?route=checkout/success)`.
- Root of (a): success clears `order_id` on first render and has **no resilience to a reload**; the cookie-consent accept reloads the page after the session was cleared → fallback branch. (For COD/bank the user stays on-site, banner already accepted, no reload, so they keep the full design.)

## 3. Goal
On Hutko return, the redesigned success page survives a reload (cookie-accept or F5) and keeps showing «Замовлення #N прийнято» with order details for the order owner; the Hutko fiscal line is correct; no large empty block below the card. Hutko amount, fiscalization, order creation, totals unchanged.

## 4. What to change

**Phase 0 — diagnostics on live (Codex, read-only first):**
1. `SELECT order_id, payment_code, payment_method FROM oc_order WHERE order_id IN (166,167,168,169);` — record the exact `payment_code` for Hutko / COD / bank-transfer. Drives (c).
2. Locate the cookie-consent banner («Ми використовуємо файли cookie… Так, я згоден»): not present in `catalog/view/template/common/*.twig` — likely an external/module script. Find the file and confirm whether "Accept" does `location.reload()` / full navigation. Drives (a).

**(a) Success-page reload resilience:**
- Make `checkout/success` re-render the full read-only order summary on reload **for the order owner**, instead of falling into the generic branch. Suggested approach (Codex to confirm against code):
  - Logged-in: if `session.order_id` is absent, fall back to the customer's most recent order **only when `order.customer_id == current customer**`.
  - Guest: reuse the existing signed Hutko **return cookie** (already carries `order_id`+`customer_id`, HMAC-validated, 1800s) to re-fetch read-only order_data — **read the cookie, do not change its security/secret**.
  - Keep `$this->cart->clear()` and the session-key cleanup; only add a safe re-render path. Never display an order that fails ownership validation.
- AND make the cookie-consent "Accept" **not do a destructive full reload** on the success route (hide banner client-side without `location.reload()`), once its script is located in Phase 0.
- Defensive: ensure the `{% else %}` fallback still looks consistent (it is acceptable only for a truly orderless visit).

**(c) Fiscal-text detection:** stop matching the brittle literal `'hutko'`. In the controller, derive booleans from the confirmed code, e.g. `is_hutko = str_starts_with($payment_code, 'hutko')` and an explicit COD check, and pass `is_hutko`/`is_cod` to the template; branch on those flags instead of string compare. Use the exact value(s) found in Phase 0.

**(d) Spacing:** remove the large empty area below the success card (likely `min-height`/footer-gap on `#checkout-success.bs-cp-page`). CSS only.

## 5. Do not touch
Hutko `buildRequest`/amount/`sign`/`api`/`response` redirect target, return-cookie HMAC secret/mechanism (READ only for ownership check — no security changes), `getQuote`, `payment_hutko_shipping_include`, order creation/`confirm.confirm`, `order_status` config, Checkbox/fiscalization payload, coupon/First15 totals, the cart-clear on success, CRM payload, NP model, DB schema, `sitemap.xml`/`robots.txt`/canonical/redirects/`.htaccess`. Do **not** add shipping display here (that is ST-2c). This task changes only: success reload-resilience + render-ownership, fiscal-text detection, and success-page spacing.

## 6. Likely files / areas (verify against live; clear template cache after)
- `catalog/controller/checkout/success.php`
- `catalog/view/template/checkout/success.twig`
- cookie-consent script — **TBD in Phase 0** (external/module JS)
- success-page CSS for (d) — locate actual rule in theme/bs-cp-page CSS.
Codex should verify against actual project files before editing.

## 7. Acceptance criteria (measurable)
1. Hutko, after payment return **and** after clicking «Так, я згоден» (reload): `index.php?route=checkout/success` shows «Замовлення #N прийнято» + Доставка/Оплата + items — **not** the generic fallback.
2. F5 on the success page within the same session shows the same full order design (not fallback).
3. Hutko order success line = «Фіскальний чек відправлено на ваш номер або на E-mail.»; COD = «…в день отримання замовлення.»; bank/other = «…при відправці замовлення.»
4. Ownership enforced: a session with no matching order (or a different customer) never sees another order's data — graceful fallback only.
5. No large empty whitespace block below the success card (visual).
6. No JS console errors; Hutko amount, fiscal receipt (`status=done`), order count unchanged.

## 8. QA / smoke test
`bs-checkout-smoke` focused run. Matrix: {guest, logged-in} × {Hutko, COD, bank-transfer}. Per Hutko cell: pay (sandbox) → land on success (full design) → accept cookie **and** F5 → still full design with correct order_id + Hutko fiscal line. COD/bank: correct fiscal line, full design. Verify `oc_order` count unchanged by these views; fiscal receipt still `done`; no other-customer leakage (try reloading after logout / fresh session → fallback, no data leak).

## 9. Rollback
Backup edited files to `_patch_backups/st2b2-*`; restore + clear OpenCart `template/`+`cache.*`. Cookie-script edit reversible (single revert). HIGH-RISK — deploy in a quiet window. If resilience change misbehaves, reverting restores current behavior (full design on first view, fallback on reload).

## 10. Recommended status after execution
Success-page parity fixed → confident customer-facing UX for cutover. Re-verify via smoke; mark `На перевірці` → `Готово` only after owner QA. Note in R-13.5: shipping-cost display (b) lands with ST-2c. Related: handoff_ST-2b1 (defer draft orders), ST-2 plan (confirm/success), ST-2c (real shipping cost + cutover).
