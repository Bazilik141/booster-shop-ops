# PAY-002 — completed work and urgent context handoff request to Claude

Date: 2026-08-31
Project root: `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops`
Owner decision: Codex remains the executor through completion. Claude is asked
to transfer the remaining bank/support context, not take over implementation.

## Short completion report

- PUMB product-page card and modal are implemented; the selected provider and
  payment count survive “Add and checkout”. Owner confirmed both PUMB and Mono
  selection work after the provider-selection correction.
- Cart warning is now based on canonical `Cart::hasStock()` / `hasMinimum()`.
  Previously `stock` (numeric available quantity) was incorrectly treated as
  availability for the requested quantity. The first two warning patches did
  not fix that; the canonical-stock patch supersedes them. Cart feedback, CTA,
  and checkout entry now agree with shipping/payment validation. Preorders and
  genuine minimum-order rules are preserved.
- Checkout delay was traced to `shipping_method.quote`: owner measured 4.18 s
  waiting for the server. Payment loading waits downstream for shipping.
  Pinta synchronously requested a tariff repeatedly, including for free shipping.
  The new patch skips free-shipping tariff calls and caches exact successful
  pricing payloads within the session for 300 seconds, maximum eight entries.
  Failed prices are not cached. Cold paid quotes still depend on carrier speed.
  Safe Server-Timing headers distinguish total quote time and API hit/miss/free.
- Owner deployed `PAY-002_np-quote-cache_20260831.php` at 12:56:59 UTC and
  `PAY-002_cart-canonical-stock_20260831.php` at 13:00:24 UTC. Both report matching
  candidate/after SHA, PHP lint, `done=ok`, `self_delete=ok`, and cache cleared.
  Owner then confirmed: “Топ! Все працює!”; speed had separately been praised.
- Local checks used actual archived controllers/models with isolated services:
  cart regression/recovery, stock/minimum/preorders, tariff output preservation,
  cache invalidation/errors, exact source hashes, repeat runs, and rollback on
  injected syntax failure. No real bank call was made by Codex.

Latest patches touch only these five live files:
`catalog/controller/checkout/{cart,checkout,shipping_method}.php`,
`catalog/view/template/checkout/cart_list.twig`, and
`extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php`.
No deployment DB changes, public PUMB switch, credential changes, commit/push,
Notion properties, or roadmap status updates were performed by Codex.

Full hashes, scope, tests and rollback:

- `diagnostics/PAY-002_cart-canonical-stock-and-checkout-timing_report_20260831.md`
- `diagnostics/PAY-002_np-quote-cache_report_20260831.md`

Server backups:

- `_patch_backups/PAY-002_np-quote-cache_20260831-20260831-125659-b6c887/`
- `_patch_backups/PAY-002_cart-canonical-stock_20260831-20260831-130024-0c366d/`

This is owner acceptance of the UI/cart/speed fixes, NOT proof of a new
UI-originated bank lifecycle or authorization to enable public production PUMB.

## Task for Claude — preserve the remaining context now

Your conversation contains PUMB support decisions that Codex and the owner must
not reconstruct from memory or guess. Before your context/token budget expires,
produce ONE self-contained, actionable handoff for Codex + owner:

`handoffs/handoff_PAY-002_final-test-and-cutover_20260831.md`

Keep the historical recap short. Spend detail on exact operational facts and
the shortest safe route to completion. Use your support conversations and
existing evidence, not a new broad audit. Do not author another patch, contact
the bank, change settings/statuses, or assign a parallel executor in this step.

Include all of the following:

1. **Environment/value matrix.** Exact known test vs production API/OAuth and
   callback URLs, configuration keys, enable/public/preview state and how to
   verify it. Mark each current value as verified, last-known with date, or
   unknown. Separate desired test settings from observed deployed settings.
2. **Bank-issued test data.** Exact synthetic/test phone numbers supplied by
   PUMB, scenario/expected result for each, required number format, who signs
   and how, required test-app/login/OTP procedure, and reset/reuse limitations.
   Do not substitute customer phone numbers or invent test identities.
3. **Support decisions and traps.** Supported terms by environment (especially
   test 4 vs production 5), amount units/rounding/minimums, application validity,
   callback authentication/headers/retries, state meanings, guarantee-letter
   interpretation, goods-shipped timing and refund restrictions. Include every
   relevant non-obvious rule learned with support, its source/date, and whether
   a later answer superseded it. Preserve unresolved questions explicitly.
4. **Exact end-to-end test runbook.** Preconditions, clean test cart, preview,
   product modal → PUMB term 4 → checkout → one authorized test application;
   then verification of payload term/amount, stored `requested_term`, callback,
   signing, `WAITING_STORE_CONFIRM`, guarantee letter `NEW_4`, goods-shipped,
   `FUNDED`, refund and final cleanup, where applicable to the confirmed contour.
   For every step give: executor (owner/Codex), exact tool/file/command or UI
   action, inputs, expected output, evidence to retain, and stop/rollback rule.
   Include the no-preview negative case without accidentally creating an order.
5. **Remaining release gates.** Reconcile PAY-004, PAY-005, order-status §8a,
   NCRM-14, bank brand/layout approval, production cutover and PAY-001-SMOKE.
   For each: what is already proven, what is missing, concrete files/settings,
   dependencies, minimum acceptance evidence, and whether it blocks public
   launch. Do not turn previously proven diagnostic-script tests into UI proof.
6. **Ready-to-use references.** Existing diagnostic runners and their correct
   invocation, relevant reports/support messages, known-good test evidence,
   log locations and safe read-only queries. Distinguish script-created
   applications from customer-created ones; avoid unnecessary repeat cycles.
7. **First next action.** End with one exact step Codex and owner can take as
   soon as the handoff is read, plus a short list of genuinely missing inputs.
   No vague “test the integration” instructions or unnecessary planning rounds.

For secrets (passwords, OAuth/API keys, preview tokens, callback credentials),
name the setting and owner-held retrieval location; never paste plaintext into
the repository or report. Required personal/payment identifiers stay in the
owner-controlled evidence location. Safe bank-issued synthetic test values can
be included explicitly. Missing values must be labeled missing, not inferred.

Start from these existing documents, then add knowledge only present in your chat:

- `diagnostics/PAY-002_verification-ledger-and-next-bank-test_20260831.md`
- `handoffs/handoff_PAY-002_session-continuation_20260826.md`
- `handoffs/handoff_PAY-002_pumb-product-page-card_20260831.md`

Acceptance: Codex and the owner can execute the remaining authorized steps
without access to Claude's chat and without asking again for a fact already
settled with PUMB support. Bank orders, signatures, refunds, credentials and
public cutover remain explicit owner gates; this handoff does not authorize them.
