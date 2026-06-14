# Codex Handoff — ST-2b.3: restore order summary in deferred confirm panel + compact success «На головну»

Date: 2026-06-14. Parent: ST-2 / 2b. **(A) is HIGH-RISK** (checkout confirm panel — must NOT reintroduce draft orders) → `bs-checkout-smoke` mandatory. (B) is low-risk CSS/layout. Both surfaced during ST-2b.2 QA.

> Built on backup `6.14.2026_10-00-15` (pre-ST-2b.1) + the deployed patches `st2b1_defer_confirm_draft_orders_20260614.php` and `st2b2_success_hutko_reload_fiscal_spacing_20260614.php`. **Codex must anchor against the LIVE post-2b.1/2b.2 files**, not the backup.

## 1. Task ID
ST-2b.3 — two UX defects found after ST-2b.1/2b.2:
- **(A)** In stock checkout, after the user selects address → shipping → payment, the order summary (items + Сума + Разом) disappears and only «Замовлення ще не створено» remains. Affects **all users** (guest and logged-in, same session).
- **(B)** On `checkout/success`, the «На головну» button sits at the bottom and adds extra page depth / empty space on all three payment methods.

## 2. Context — confirmed in code
- `catalog/view/template/checkout/checkout.twig:30`: `<div id="checkout-confirm" …>{{ confirm }}</div>` — at page load this holds the server-rendered summary.
- `catalog/view/template/checkout/confirm.twig`: the `{{ confirm }}` output = `.table-responsive > table` with `tbody` (products: `qty x name`, model, options) + `tfoot` (totals: `total.title` / `total.text` — Сума, Разом, etc.) + `#checkout-payment` (payment button area).
- **ST-2b.1 regression:** `bsCheckoutRefreshConfirmIfPaymentReady` now does `$('#checkout-confirm').html(bsCheckoutDeferredConfirmHtml())`, and `bsCheckoutDeferredConfirmHtml()` renders only «Підтвердження замовлення / Замовлення ще не створено» + Доставка/Оплата labels + the place-order button — **no items, no totals**. The reset path also does `$('#checkout-confirm').html('')`. So the summary table from `{{ confirm }}` is wiped when payment becomes ready.
- The summary data is already on the page at load (the initial `{{ confirm }}` table) — it can be captured client-side; **no server call is needed** to re-display it.
- `catalog/view/template/checkout/success.twig`: `<nav class="bs-success-actions">` (На головну + optional history) sits after `.bs-success-footer-msg`; ST-2b.2 added `st2b2-success-spacing` CSS (padding-bottom). The button placement, not just padding, creates the perceived empty depth.

## 3. Goal
The deferred confirm panel keeps showing the order summary (items + Сума + Разом) for all users from the moment it appears until «Оформити замовлення», **without creating any order and without calling `checkout/confirm.confirm`**. On success, «На головну» sits compactly with no large empty block. ST-2b.1 no-draft-order guarantee and ST-2b.2 reload/fiscal behavior remain intact.

## 4. What to change
**(A) checkout.twig — order summary in the deferred placeholder (client-side only):**
- On page load, capture the initial `{{ confirm }}` summary markup (the products `tbody` rows + totals `tfoot` rows, i.e. the `.table-responsive` table) into a client-side variable **once**, before any swap.
- Rebuild `bsCheckoutDeferredConfirmHtml()` so the panel shows: the captured order summary (items + Сума + Разом) **+** the «Замовлення ще не створено…» notice **+** Доставка/Оплата labels **+** the existing place-order button (`[data-bs-deferred-confirm]`).
- Ensure the reset/clear path (`$('#checkout-confirm').html('')`) and the refresh path do not permanently lose the summary — re-render from the cached markup.
- **Hard constraint:** do NOT call `checkout/confirm.confirm` to obtain the summary (that recreates draft orders — the exact bug ST-2b.1 fixed). Use only client-side cached markup. The authoritative final total still comes from `confirm.confirm` that runs once on the place-order click (unchanged).
- Note on totals: at this stage shipping is the Hutko crutch (cost 0); the client-side summary shows items + current totals. Real shipping-cost reconciliation in totals is ST-2c — do not add it here.

**(B) success.twig — compact «На головну»:**
- Reposition `bs-success-actions` higher / reduce the vertical gap above it so it fills the available space compactly instead of adding page depth. Adjust the `st2b2-success-spacing` block as needed. CSS/markup only; keep both buttons (На головну, and «Переглянути замовлення» when logged-in) and their links.

## 5. Do not touch
The place-order click handler and the single `confirm.confirm` call on click (ST-2b.1), `bsCheckoutLoadConfirmAndSubmit`, the double-submit guard, ST-2b.2 success reload-resilience / ownership logic / fiscal `is_hutko`/`is_cod` flags, Hutko `buildRequest`/amount/sign/api, `payment_hutko_shipping_include`, Checkbox/fiscalization, `getQuote`, coupon/First15 totals, CRM payload, NP model, DB schema, order_status, `sitemap.xml`/`robots.txt`/canonical/redirects/`.htaccess`. Do not add real shipping cost to totals (ST-2c). This task only re-displays an already-available summary client-side and adjusts success-page layout.

## 6. Likely files / areas (verify against LIVE post-2b.1/2b.2; clear template cache after)
- `catalog/view/template/checkout/checkout.twig` — `bsCheckoutDeferredConfirmHtml`, `bsCheckoutRefreshConfirmIfPaymentReady`, the reset/clear path, `#checkout-confirm`.
- `catalog/view/template/checkout/success.twig` — `bs-success-actions`, `st2b2-success-spacing`.
Codex should verify against actual project files before editing.

## 7. Acceptance criteria (measurable)
1. After selecting address → shipping → payment, **guest AND logged-in**: `#checkout-confirm` shows order items + Сума + Разом + «Замовлення ще не створено» notice + «Оформити замовлення» button.
2. No `checkout/confirm.confirm` network call on payment select/re-select; `oc_order` row count **unchanged** on select and on switching payment methods several times.
3. Click «Оформити замовлення» → `confirm.confirm` fires once → exactly one order → proceeds to Hutko/COD as today (unchanged from ST-2b.1).
4. Switching shipping/payment back and forth keeps the summary visible (re-rendered from cache), never a blank panel.
5. `checkout/success`: «На головну» is positioned compactly; no large empty whitespace block below — verified on Hutko, COD, bank-transfer and mobile (≤480px).
6. No JS console errors; ST-2b.2 reload-resilience + fiscal text still correct.

## 8. QA / smoke test
`bs-checkout-smoke` focused. Matrix {guest, logged-in} × {відділення, поштомат} × {Hutko, COD, bank}. Per cell: select address/shipping/payment → confirm panel shows items + totals + place-order (no `confirm.confirm` call, `oc_order` count steady) → switch payment a few times (summary stays) → place one real test order (Hutko sandbox / COD): exactly +1 order, payment proceeds, fiscal text correct. Then on success page check «На головну» placement + no empty block on all three methods + mobile.

## 9. Rollback
Backup edited files (`.bak` next to file or `_patch_backups/st2b3-*`); restore + clear OpenCart `template/`+`cache.*`. (A) is HIGH-RISK — deploy in a quiet window; if the summary re-render misbehaves, reverting restores ST-2b.1 placeholder behavior (no draft orders preserved either way).

## 10. Recommended status after execution
Checkout confirm UX restored (summary visible) + success layout tightened → cleaner pre-cutover UX. Re-verify via smoke; `На перевірці` → `Готово` only after owner QA. Next gates unchanged: rest of 2b (coupon/First15 + agree + GA4 dedupe), then 2c (real shipping cost + cutover `url.php`); shipping display on success (b) lands with ST-2c.
