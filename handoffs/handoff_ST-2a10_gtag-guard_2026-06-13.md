# Codex Handoff — ST-2a.10: `gtag is not defined` breaks add-to-cart + checkout success handlers (ROOT CAUSE of both open bugs)

Date: 2026-06-13. Parent: ST-2 / cookie-hang + guest-blocker. Likely the UNIFYING root cause behind the cold-session add-to-cart "hang" AND part of the guest checkout shipping-not-loading. Client/inline JS from a GA4 extension.

## 1. Task ID
ST-2a.10 — `Uncaught ReferenceError: gtag is not defined` thrown inside GA4 `ps_dataLayer.pushEventData`, which is wired into the add-to-cart and checkout AJAX success handlers. The exception aborts the success/complete chain → add-to-cart button never resets (perceived hang) and checkout shipping/payment can fail to load. Reproduced in InPrivate on a product page before cookie consent (console: `gtag is not defined` at `Object.pushEventData`, called from `Object.success` of cart.add, and at page-load `view_item`).

## 2. Context — root cause (confirmed, owner console + code)
- File: `extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php` — builds the inline `ps_dataLayer` object injected in the page head.
- `pushEventData` (~lines 87-93) calls `gtag('event', eventName, ...)` **unguarded**:
  ```js
  pushEventData: function(eventName, data) {
      ...
      gtag('event', eventName, data.ecommerce);   // ~91
      ...
      gtag('event', eventName, data);             // ~93
  }
  ```
  Other unguarded gtag calls in the same object: `gtag('set', 'user_data', ...)` (~107), `gtag('event', 'conversion', ...)` (~129).
- `pushEventData` is injected into success handlers:
  - `~488`: `ps_dataLayer.pushEventData('add_to_cart', json['ps_add_to_cart']);` (cart.add success) → throws → button stuck.
  - `~621` add_shipping_info, `~602` add_payment_info, `~638` begin_checkout (checkout success handlers) → can break shipping/payment load on checkout.
  - `~500` view_item, `~159/237/...` view_item_list etc. (page load).
- Why `gtag` is undefined: the GTM/gtag bootstrap is consent-gated (GTM consent mode) / not defined before the user accepts the cookie banner, so on a cold/no-consent page `gtag` does not exist when `pushEventData` runs → ReferenceError → the surrounding success/complete JS aborts.

This explains: cold-session add-to-cart "hang" (button never resets because the success handler threw, even though cart.add returned 200 and the item was added); the product-page console errors; and likely why ST-2a.8/2a.8b autosave fixes didn't fully resolve guest shipping (the checkout success handler can throw on add_shipping_info before shipping renders).

## 3. Goal
GA4 tracking must NEVER throw when `gtag` is not yet defined. Success/complete handlers (add-to-cart, checkout shipping/payment) must always run to completion regardless of analytics/consent state. Tracking should resume normally once `gtag` exists (post-consent).

## 4. What to change
File: `extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php` (the inline `ps_dataLayer` JS). Verify exact lines against current/live.
**Primary fix — guard every gtag call:** at the top of `pushEventData` (and `pushUserData`/conversion paths that call gtag at ~107/~129) add:
```js
if (typeof gtag !== 'function') { return; }
```
so the function no-ops instead of throwing when gtag is absent.
**Recommended additional (resilience):** ensure a gtag stub exists early so events queue instead of being lost — near the top of the injected block (before any pushEventData), define once:
```js
window.dataLayer = window.dataLayer || [];
window.gtag = window.gtag || function(){ window.dataLayer.push(arguments); };
```
(Consent mode still governs whether tags actually fire; the stub only prevents ReferenceError and preserves queued events.) Codex to choose guard-only vs guard+stub; guard is mandatory, stub is preferred.

Do NOT change what events are tracked, the consent-mode config, GTM container id, or the data payloads.

## 5. Do not touch
GTM container id / consent-mode settings, cookie banner (`common/cookie`), cart/checkout business logic, ST-2a.7/2a.8/2a.8b code, Hutko/Checkbox, NP model, DB. This is a JS-resilience guard only.

## 6. Likely files
`extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php` (inline `ps_dataLayer.pushEventData` ~87-93 + gtag sites ~107/~129; optional stub near the object/init). Vendor extension — local patch with backup; clear template cache after (inline is built server-side, but page/template cache may hold output).

## 7. Acceptance criteria
1. InPrivate, product page, BEFORE accepting cookies: console has NO `gtag is not defined`; `pushEventData('view_item')` no-ops cleanly.
2. Cold session add-to-cart (no consent): button shows progress then RESETS, success alert + mini-cart update appear, no stuck "Додаємо у кошик…", item added. (Combined with ST-2a.9 B1, fully graceful.)
3. Guest checkout (cold/no-consent): after address+contacts, shipping + payment load (re-test ST-2a.8/2a.8b once gtag no longer throws).
4. After accepting cookies (gtag defined): GA4 events fire as before (add_to_cart, view_item, begin_checkout) — no tracking regression.
5. No JS errors in console on home/product/checkout in both consent states.

## 8. QA / smoke
bs-checkout-smoke + cold-session add-to-cart (InPrivate). Check console clean in both consent states; verify GA4 still records events after consent (DebugView or console-log mode).

## 9. Rollback
Backup the model file to `_patch_backups/st2a10-*`; restore on issue; clear cache. Low risk (adds a guard / stub; no behavior change when gtag is present).

## 10. Status after
If this closes the cart hang and (with 2a.8/2a.8b) the guest shipping load → cookie-hang + guest blocker both resolved. Re-verify both flows. Update Notion R-13.5. The ST-2a.9 B2 server timing probe becomes secondary (the "hang" was the gtag throw, not pure server latency) — keep probe output for reference only.
