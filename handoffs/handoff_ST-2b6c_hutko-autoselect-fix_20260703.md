# Codex Handoff — ST-2b.6 Phase 1: remove silent auto-select/auto-save of Hutko as payment method

Date: 2026-07-03. Parent: ST-2b.6 (Phase 1 — first actual behavior fix; follows Phase 0 and Phase 0b diagnostics, see `handoff_ST-2b6_hutko-phantom-order-tab-restore_20260703.md` and `handoff_ST-2b6b_hutko-payment-silent-reset_20260703.md`). **HIGH-RISK** (checkout / payment / Hutko / fiscalization) → `bs-checkout-smoke` mandatory, full 11-step, before this ships. Also reference `bs-seo-risk-gate` is not applicable (no SEO/indexing surface touched); this is purely a checkout/payment behavior change.

## 1. Task ID

ST-2b.6 Phase 1 — stop the checkout from silently re-selecting and auto-saving Hutko as the payment method whenever the payment selection gets reset (most commonly after an address change), on both the new (stock) checkout and the old (SimpleCheckout) module. This directly targets the root cause confirmed in Phase 0b diagnostics: customers who explicitly chose a different payment method (e.g. COD) can have that choice silently overwritten back to Hutko without any click, which matches owner-reported real customer orders showing Hutko charged with no payment made.

## 2. Context — confirmed by Phase 0b diagnostic logs (live evidence, not assumption)

**New checkout (`catalog/view/template/checkout/checkout.twig`):**
- `window.bsCheckoutResetMethodState` (around line ~2994-3010) clears `paymentCode` to empty whenever it runs with `reason: "address"` — this reset itself is triggered by an address-field-change refresh (see owner's note in §2b below — this trigger must be preserved).
- Immediately after, `renderPaymentMethods` (around line ~2108-2148) finds a `pendingChoice` value of `"hutko"` already present, matches it against the available payment option codes (e.g. `["pinta_nova_poshta_cod.pinta_nova_poshta_cod", "hutko.hutko", "bank_transfer.bank_transfer", "cod.cod"]`), selects `hutko.hutko`, and calls `savePayment(...)` with `isAuto: true` — with no user click anywhere in this chain. Captured log sequence (Phase 0b export, 2026-07-03 09:45:06): `default-payment-candidate` → `auto-save-payment` → `save-payment:entry` → `payment-code-changed` (`changeSource: "savePayment.success"`, `isAuto: true`, `oldPaymentCode: ""`, `newPaymentCode: "hutko.hutko"`). This repeated identically after a second address-triggered reset in the same session (sequences 41-51 in the export).
- `pendingChoice: "hutko"` is present even at initial page load, before any user interaction (`phase0b:new:payment-module-ready`, sequence 11) — confirming this is a hardcoded/default preference baked into the new checkout's payment module init, not something derived from user action.

**Old checkout (SimpleCheckout module, `?route=extension/SimpleCheckout/module/pinta_simple_checkout`):**
- A function literally named `selectPreferredPaymentMethod(preferredPrefix)` (around line ~1808) is called from `refreshCheckoutUi` (~1851), which itself runs on **every** `ajaxComplete` event on the page (~2325). Captured log (sequences 63, 65): `preferredPrefix: "hutko"`, `candidateCode: "hutko"` — i.e. every AJAX refresh on the old checkout re-applies a hardcoded Hutko preference the same way.

**Owner-provided constraint — do not break this while fixing the above:** address fields on the new checkout trigger a shipping-method refresh not only on Enter but also on blur (clicking/tabbing away to any other part of the page). This blur-triggered refresh is intentional, existing behavior (a separate "crutch") and must keep working exactly as it does today. Only the *tail end* of what happens after that refresh — the silent Hutko re-selection and auto-save — is in scope for removal. Do not touch, disable, or alter the trigger that fires `bsCheckoutResetMethodState` on address blur/change itself.

## 3. Goal

1. Address change (via Enter, blur/click-away, or dropdown reselect) still correctly triggers a shipping-method refresh, exactly as today — **unchanged**.
2. After that refresh resets the payment selection, the payment code stays empty. **No automatic re-selection of any payment method, no automatic `savePayment` call.** The customer must explicitly click a payment radio again.
3. While payment is empty, the confirm panel shows the existing ST-2b.4 intermediate-state UI (order summary + «Оберіть спосіб оплати» hint, no place-order button) — this mechanism already exists for exactly this state; reuse it, do not rebuild it.
4. Same fix on the old (SimpleCheckout) checkout: `selectPreferredPaymentMethod(preferredPrefix='hutko')` no longer fires automatically from `refreshCheckoutUi`'s `ajaxComplete` handler.
5. No change to Hutko/COD/bank_transfer functional logic, totals, fiscalization, or coupon logic. No change to the zero-draft-order guarantees from ST-2b.1/ST-2b.4.

## 4. What to change

**New checkout (`checkout.twig`):**
- In the `renderPaymentMethods` success path (~2108-2148): remove the `default-payment-candidate` → `auto-save-payment` → `savePayment(isAuto: true)` chain entirely. When no payment method is currently selected (including right after a reset), do not pick one automatically and do not call `savePayment`. Leave the payment radios unchecked and `paymentCode` empty until the customer clicks one.
- Verify `bsCheckoutRefreshConfirmIfPaymentReady` (already gates on `$('#input-payment-code').val()` being non-empty per ST-2b.1/2b.3) correctly falls through to the existing "no payment selected yet" intermediate-summary state built in ST-2b.4 — confirm this still renders correctly with the auto-select removed (it should, since that mechanism was built for exactly this empty-payment case, but verify against live).
- Confirm whether `pendingChoice: "hutko"` is set anywhere else (e.g. server-rendered initial HTML/JS variable) beyond this one auto-save path — if so, that source should also stop influencing an automatic save, though it's fine if a radio is visually pre-checked as a *suggestion* as long as nothing gets saved without a real click. **Clarify with owner if a visual pre-check (no auto-save) is acceptable, or if the payment section should render with nothing checked at all until first click.** Default to "nothing checked, nothing saved" unless told otherwise, since that's the simpler, safer behavior and matches the ST-2b.4 empty-state UI already built.

**Old checkout (SimpleCheckout module):**
- In `refreshCheckoutUi` (~1851), remove or gate out the call to `selectPreferredPaymentMethod(preferredPrefix='hutko')` so it no longer runs automatically on every `ajaxComplete`. If this function is also relied on for some other legitimate purpose (e.g., rendering the initial radio list), keep that part — only remove the auto-select/auto-check-to-hutko behavior.
- Verify whether SimpleCheckout auto-saves the payment selection anywhere (similar to `savePayment(isAuto:true)` on the new checkout) — if so, remove that auto-save call too, using the same principle: real click only.

## 5. Do not touch

- **The address blur/change → shipping-method refresh trigger itself** (the mechanism that calls `bsCheckoutResetMethodState` / re-runs `refreshCheckoutUi` on address field blur, not just Enter). This is explicitly required, existing behavior — do not disable, debounce, or alter when/how often it fires. Only remove what happens *after* the reset (the Hutko auto-pick).
- Hutko `buildRequest`/amount/sign/api, `payment_hutko_shipping_include`, Checkbox/fiscalization, `getQuote` cost, coupon/First15 totals.
- ST-2a.4 void-status net, ST-2b.1 deferred-confirm click-gating logic (note: Phase 0a found no `isTrusted`/pointer gate currently exists there — that is a **separate, still-open issue**, not in scope for this handoff; do not attempt to add a click-trust gate here, it needs its own scoped task).
- ST-2b.2/2b.3 success-page and summary-capture logic — reuse the existing intermediate-state rendering, don't rewrite it.
- CRM payload field shape, NP model, DB schema, order_status config.
- `sitemap.xml`, `robots.txt`, canonical, redirects, `.htaccess`, Merchant feed, schema/JSON-LD.
- Remove the Phase 0/0b diagnostic instrumentation only if the owner confirms it's no longer needed for follow-up verification — otherwise leave it in place alongside this fix so the fix's effect is directly observable in the same logs.

## 6. Likely files / areas (verify against LIVE; clear template cache after)

- `catalog/view/template/checkout/checkout.twig` — `bsCheckoutResetMethodState` (~2994-3010), `renderPaymentMethods` success handler (~2108-2148), `bsCheckoutRefreshConfirmIfPaymentReady` / `bsCheckoutDeferredConfirmHtml` (verify empty-state rendering, ~3255-3282).
- SimpleCheckout module template — `refreshCheckoutUi` (~1851), `selectPreferredPaymentMethod` (~1808), `ajaxComplete` binding (~2325). Route: `extension/SimpleCheckout/module/pinta_simple_checkout` — exact file path unconfirmed, Codex must locate.
- Codex must verify all line numbers against the actual live files (these are taken from Phase 0b diagnostic stack traces, which reference the live deployed code, but re-grep before editing — do not blind-edit by line number alone).

## 7. Acceptance criteria (measurable)

1. On the new checkout: select COD (or any non-Hutko method), then trigger an address refresh (via blur/click-away to elsewhere on the page, and separately via re-selecting an address from the dropdown) → payment code becomes empty and **stays empty** — confirm via `window.bsSt2b6ReadDiagnostics()` / `phase0b:new:payment-code-changed` log showing no `isAuto: true` auto-save event following the reset.
2. Confirm panel shows the existing "items + Сума + Разом + «Оберіть спосіб оплати»" intermediate state, no place-order button, while payment is empty.
3. Customer must click a payment radio again before the place-order button/flow becomes available — verify this matches ST-2b.1/2b.4's existing gating.
4. Address blur (clicking elsewhere, not just Enter) still correctly triggers the shipping-method refresh exactly as before — no regression to this crutch.
5. Same verification on the old (SimpleCheckout) checkout: repeated ajax refreshes no longer silently re-check/re-save Hutko over a previously chosen method.
6. Zero new draft orders introduced by this change — re-verify the ST-2b.1/2b.4 zero-draft-order guarantee still holds (`oc_order` count steady while switching methods and triggering address refreshes without clicking place-order).
7. Hutko amount/shipping math, Checkbox fiscalization, coupon/First15 totals unaffected when Hutko *is* explicitly chosen by the customer.

## 8. QA / smoke test

`bs-checkout-smoke`, full 11-step, sandbox/staging only. Additional targeted scenario for this fix, run on **both** checkouts:
1. Enter address → select COD → re-select the same or a different address from the dropdown (triggers refresh) → confirm payment resets to empty and does *not* silently become Hutko → manually re-select COD → place order → verify order saved with COD, not Hutko.
2. Repeat, but trigger the address refresh via clicking/tabbing away from an address input field (not the dropdown, not Enter) → same expected result.
3. Repeat once with Hutko explicitly chosen (not as an auto-default) to confirm real Hutko flow, amount, and fiscalization are unaffected.
4. Confirm `oc_order` row count does not increase from any of the above steps until an explicit place-order click.

## 9. Rollback

Backup both edited files (`checkout.twig` and the SimpleCheckout module template) to `_patch_backups/ST-2b6c-*` before write; restore + clear OpenCart `template/`+`cache.*` on any regression. HIGH-RISK — deploy in a quiet window, keep the ST-2a.4 void-status net as a fallback safety net regardless.

## 10. Recommended status after execution

If QA passes (owner manual check: COD stays COD through an address refresh, Hutko still works when explicitly chosen, zero new drafts): this closes the core finding from ST-2b.6 Phase 0/0b. Move ST-2b.6 to `Готово` only after owner's manual QA confirms — not before. Re-verify the zero-draft-order guarantee ST-2c depends on. Update `booster-dashboard.html` ROADMAP_FLOW and Notion status for ST-2b.6 in the same session per `ROADMAP_SOP.md §3-4`. Note as a separate, still-open follow-up: Phase 0a found no `isTrusted`/pointer-activation gate on the deferred-confirm button — that remains unresolved and is not addressed by this handoff; flag to owner whether it needs its own scoped task before ST-2c cutover.
