# Codex Handoff — ST-2b.1: premature draft order on payment-method select (defer confirm.confirm to place-order click)

Date: 2026-06-13. Parent: ST-2 / 2b (planned). HIGH-RISK: checkout / payment / Hutko / fiscalization / order status → `bs-checkout-smoke` mandatory. Client JS + confirm wiring.

## 1. Task ID
ST-2b.1 — selecting a payment method (especially Hutko) creates an order row in the DB BEFORE the user clicks «Оформити замовлення». Known anomaly from the ST-2 audit; st2a.4 only masked the noise (void status «Чернетка (системний)» + confirm-gating). This is the full fix planned for 2b.

## 2. Context — confirmed in code
`catalog/view/template/checkout/checkout.twig`:
```js
window.bsCheckoutRefreshConfirmIfPaymentReady = function() {
  if ($('#input-payment-code').val()) {
    $('#checkout-confirm').load('index.php?route=checkout/confirm.confirm&language=...');
    return true;
  }
  $('#checkout-confirm').html('');
  return false;
};
```
In OpenCart 4, `checkout/confirm.confirm` **writes the order** to `oc_order` (incomplete/“missing orders” status 0) as a side effect of rendering the confirm panel. Because this is called as soon as a payment method is selected, every payment selection (and re-selection) spawns a draft order; Hutko additionally prepares payment-side state. st2a.4 added a synthetic void status to filter these in admin/CRM, but the root (order created pre-submit) remains and is a cutover blocker.

## 3. Goal
No order (not even a draft) is created until the user explicitly clicks «Оформити замовлення». Until then the confirm area shows a client-side placeholder/summary. On place-order click, the real confirm + order creation runs exactly once and proceeds to payment (Hutko redirect / COD) as today. Hutko amount, Checkbox fiscalization, totals, coupons unaffected.

## 4. What to change
- Replace the auto `$('#checkout-confirm').load('checkout/confirm.confirm')` on payment-ready with a **client-side placeholder/summary** (totals already known client-side; no server confirm call).
- Move the real `checkout/confirm.confirm` call into the **«Оформити замовлення» click handler**: on click → load/run confirm.confirm once → then continue the existing place-order/payment flow (Hutko redirect etc.).
- Ensure the place-order button still works end-to-end if it currently relies on the pre-loaded `#checkout-confirm` content — rewire to “load confirm, then submit/redirect”.
- Keep st2a.4 void-status handling as a safety net (do not remove yet).
- Guard against double-submit (in-flight flag) so place-order can’t create two orders.

## 5. Do not touch
Hutko `buildRequest`/amount logic, `payment_hutko_shipping_include`, Checkbox/fiscalization, getQuote cost, coupon/First15 totals, CRM payload field shape, NP model, DB schema, order_status config. This task only changes WHEN confirm.confirm is called, not payment/fiscal math.

## 6. Likely files
`catalog/view/template/checkout/checkout.twig` — `bsCheckoutRefreshConfirmIfPaymentReady` (~620), the «Оформити замовлення» button handler, `#checkout-confirm` panel, payment-method select wiring. Possibly the confirm template/controller if a placeholder render is added server-side. Verify against live; clear template cache after.

## 7. Acceptance criteria (measurable)
1. Selecting any payment method (incl. Hutko) → **no new row in `oc_order`** (verify admin order count / CRM `action=orders` before vs after selection).
2. `#checkout-confirm` shows a placeholder/summary after payment select; no `checkout/confirm.confirm` network call on select.
3. Click «Оформити замовлення» → `confirm.confirm` fires **once** → order created once → proceeds to Hutko redirect / COD success.
4. Hutko amount = total − shipping (shipping_include=0) with coupon applied; Checkbox receipt correct (sandbox).
5. Browsing the checkout and switching payment methods several times without placing → zero draft orders created.
6. Logged-in + guest both correct; no double orders on double-click.

## 8. QA / smoke test
`bs-checkout-smoke` full 11-step. Matrix: {guest, logged-in} × {відділення, поштомат} × {Hutko, COD}. Check `oc_order` row count before/after payment select (must not grow); place one real test order each payment → admin shows exactly one; Hutko sandbox amount + Checkbox receipt; CRM readback. Confirm st2a.4 void filter still sane.

## 9. Rollback
Backup edited files to `_patch_backups/st2b1-*`; restore + clear template cache. HIGH-RISK — deploy in a quiet window; keep st2a.4 void-status filter as fallback so any stray drafts stay filtered.

## 10. Status after
Closes the draft-order anomaly → unblocks cutover (per st2a.4 notes). Re-verify order counts. Update Notion R-13.5. Related: ST-2 plan (confirm section), handoff_ST-2a4_order-void-noise.
