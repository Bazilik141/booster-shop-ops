# Codex Handoff — ST-2b.4: eliminate residual draft-order on payment switch + show summary in intermediate state

Date: 2026-06-14. Parent: ST-2 / 2b. **HIGH-RISK** (checkout / order creation / Hutko) → `bs-checkout-smoke` mandatory. **Diagnostic-first**: confirm the trigger before hardening. Surfaced during ST-2b.3 QA.

> Built on the LIVE checkout after `st2b1`, `st2b2`, `st2b3` patches. **Codex must verify/anchor against the live files**, not backups.

## 1. Task ID
ST-2b.4 — two items found in ST-2b.3 QA:
- **(M) Residual draft-order on payment switch.** Reproduced **once, intermittently, in a logged-in session**: an order was created when **switching the payment method** (not on «Оформити замовлення»). It landed as «Чернетка (системний)» (st2a.4 void status caught it ✓), then «Оформити» promoted it to «В обробці». So a path still triggers `checkout/confirm.confirm` outside the intended explicit click.
- **(b3-gap) Empty summary in the intermediate state.** After the address is entered (a micro-refresh reveals shipping/payment options) and **before** a payment method is chosen, `#checkout-confirm` is blank; the cached summary only returns once payment is selected.

## 2. Context — confirmed
- **Single `confirm.confirm` caller.** Grep of the codebase (catalog + extension, excluding the inactive SimpleCheckout module) shows the only `checkout/confirm.confirm` loader is in `catalog/view/template/checkout/checkout.twig` — the line ST-2b.1 converted into the explicit place-order path (`bsCheckoutLoadConfirmAndSubmit`, invoked by the delegated handler `click.bsSt2b1DeferredConfirm` on `#checkout-confirm [data-bs-deferred-confirm]`). ST-2b.3 re-asserted exactly **1** such loader. No stock-JS auto-confirm-on-payment-change exists in the catalog theme.
- **Implication:** because there is only one caller (the deferred button handler), the draft-on-switch almost certainly means **the deferred button was activated unintentionally** — e.g. keyboard Enter while focus landed on it after switching radios, a synthetic/programmatic click, or an event reaching the handler — rather than a hidden second caller. Codex must still re-grep the live tree (all `.js`/`.twig` in catalog, theme, and active extensions) to rule out a second caller definitively.
- In OC4, `confirm.confirm` writes the order in status 0 (= «Чернетка (системний)» per st2a.4), and the payment step promotes it. So the draft appears exactly at the moment `confirm.confirm` runs.
- **b3-gap:** the deferred placeholder renders only when payment is ready (`bsCheckoutRefreshConfirmIfPaymentReady`); the reset/clear path does `$('#checkout-confirm').html('')`, emptying the panel between address-refresh and payment selection. The summary is cached client-side (ST-2b.3 `bsCheckoutInitialSummaryHtml`) but not injected in this window.

## 3. Goal
No order — not even a draft — is created until an explicit, trusted pointer click on «Оформити замовлення». Switching shipping/payment any number of times (mouse or keyboard) creates zero orders. The order summary stays visible in the intermediate state. st2a.4 void-status net stays as a safety fallback. Hutko amount/fiscalization/totals unchanged.

## 4. What to change
**Phase 0 — diagnostics (do NOT change order-creation behavior yet):**
- Temporarily instrument every invocation of `bsCheckoutLoadConfirmAndSubmit` / the `confirm.confirm` load: log to console (and optionally a lightweight same-origin beacon) the triggering `event.type`, `event.isTrusted`, `document.activeElement`, the event target, and a `new Error().stack`.
- Re-grep the live tree (catalog + theme + active extensions, all `.js`/`.twig`) for any other `confirm.confirm` loader or auto-submit on payment/shipping change; report findings.
- Provide a repro script: logged-in session, switch payment via mouse clicks AND via keyboard (Tab to radios, arrow/Enter), rapid switching; watch `oc_order` and the diagnostic log.
- **Deliver the captured trigger (or proof that only a trusted pointer click fires it) before Phase 1.**

**Phase 1 — harden + fix gap (after Phase 0 confirms the trigger):**
- Gate the deferred-button action so it runs only on a genuine user pointer click of «Оформити» (e.g. require `event.isTrusted` and pointer-type activation; ignore activation that originates from payment/shipping radio interaction or stray Enter). Keep the existing double-submit guard (`bsCheckoutConfirmSubmitting`).
- Guarantee that selecting/switching payment never calls `bsCheckoutLoadConfirmAndSubmit` / `confirm.confirm`.
- **b3-gap:** in the intermediate state (address done, payment not yet chosen, panel otherwise empty), render the cached summary (items + Сума + Разом) **without** the place-order button, plus a short hint «Оберіть спосіб оплати». Strictly client-side; **no `confirm.confirm`**.

## 5. Do not touch
What `checkout/confirm.confirm` does internally (order creation, totals, Hutko `buildRequest`/amount/sign/api, Checkbox/fiscalization, coupon/First15), the st2a.4 void-status net (**keep it**), ST-2b.2 success reload/ownership/fiscal logic, ST-2b.3 summary-capture mechanism (extend, don't rewrite), NP model, DB schema, order_status config, CRM payload, `sitemap.xml`/`robots.txt`/canonical/redirects/`.htaccess`. This task changes only WHO/WHEN triggers `confirm.confirm` and the intermediate display.

## 6. Likely files / areas (verify against LIVE; clear template cache after)
- `catalog/view/template/checkout/checkout.twig` — `click.bsSt2b1DeferredConfirm` handler, `bsCheckoutLoadConfirmAndSubmit`, `bsCheckoutRefreshConfirmIfPaymentReady`, the reset/clear path, `bsCheckoutDeferredConfirmHtml` / `bsCheckoutCachedSummaryHtml`.
- Remove Phase-0 diagnostics before final deploy (or guard behind a flag).
Codex should verify against actual project files; re-grep to confirm the single-caller assumption on live.

## 7. Acceptance criteria (measurable)
1. Phase 0: a captured diagnostic log that identifies the trigger of `confirm.confirm` in the repro (or evidence it only fires on a trusted pointer click).
2. Switching payment methods (mouse AND keyboard), rapidly, any number of times → **0 new `oc_order` rows** (no new «Чернетка (системний)»). Verify via CRM `action=orders` / admin count before vs after.
3. `confirm.confirm` fires **only** on explicit pointer click of «Оформити замовлення» → exactly **+1** order → proceeds to Hutko/COD as today.
4. Intermediate state (address entered, payment not chosen): `#checkout-confirm` shows items + Сума + Разом + «Оберіть спосіб оплати» hint, **no** place-order button, **no** `confirm.confirm` network call.
5. st2a.4 void-status net still present and functioning.
6. No double order on double-click; correct for guest and logged-in.

## 8. QA / smoke test
`bs-checkout-smoke`, with extra payment-switch stress. Matrix {guest, logged-in} × {відділення, поштомат} × {Hutko, COD, bank}. Per cell: enter address → confirm intermediate summary visible (no button, no `confirm.confirm`, `oc_order` steady) → switch payment several times via mouse and keyboard (`oc_order` stays flat, no Чернетка) → click «Оформити» once → exactly +1 order, payment proceeds, fiscal text correct. Confirm st2a.4 filter sane.

## 9. Rollback
Backup edited file(s) to `_patch_backups/st2b4-*` or `.bak`; restore + clear OpenCart `template/`+`cache.*`. Remove Phase-0 diagnostics after use. st2a.4 void-status net remains as the fallback so any stray draft stays filtered. HIGH-RISK — deploy in a quiet window.

## 10. Recommended status after execution
Closes the residual draft-order path (the core 2b/cutover theme) and the intermediate-summary gap → green to proceed with rest of 2b (coupon/First15 + agree + GA4 dedupe) and then 2c (real shipping cost + cutover `url.php`). `На перевірці` → `Готово` only after owner QA. Related: handoff_ST-2b1 (defer confirm), handoff_ST-2a4 (void noise), handoff_ST-2b3.
