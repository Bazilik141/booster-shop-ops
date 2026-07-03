# Codex Handoff — ST-2b.6: phantom Hutko order after tab close/reopen (Phase 0 — diagnostics only)

Date: 2026-07-03. Parent: ST-2 / 2b. **HIGH-RISK** (checkout / order creation / Hutko / fiscalization) → `bs-checkout-smoke` mandatory before any Phase 1 fix ships. **Diagnostic-first**: do not harden behavior until Phase 0 identifies the trigger. Blocks ST-2c (cutover cannot proceed while the zero-draft-order guarantee has an open hole).

> Built on the LIVE checkout after `st2b1`–`st2b5` patches. Codex must verify/anchor against the live files, not backups — this repo has no local copy of `checkout.twig` (owner-server-only).

## 1. Task ID

ST-2b.6 — leaving the checkout tab open, closing the browser, then reopening it produces a phantom order in `oc_order` with Hutko as payment method, with no payment made. Reproduced example: order #186. First surfaced on dashboard 2026-06-25, no handoff/diagnostics filed until now, not yet tracked in Notion.

## 2. Context — confirmed vs. unconfirmed

**Confirmed (from ST-2b.1/ST-2b.4 work, same codebase):**
- The only `checkout/confirm.confirm` loader in the live tree is `catalog/view/template/checkout/checkout.twig`, wired through `bsCheckoutLoadConfirmAndSubmit`, invoked by the deferred handler `click.bsSt2b1DeferredConfirm` on `#checkout-confirm [data-bs-deferred-confirm]` (ST-2b.1), later gated to require a trusted pointer-click activation (ST-2b.4 Phase 1).
- `confirm.confirm` writes the order row in OC4 (status 0, caught by the ST-2a.4 void-status net as «Чернетка (системний)») as a side effect of rendering the confirm panel — this is the only known order-creation path in the stock checkout.
- ST-2b.4 already closed the "draft on payment switch" path by requiring `event.isTrusted` + pointer activation on the deferred button. That fix is live and unrelated triggers (mouse/keyboard payment switching) are confirmed closed as of ST-2b.4/2b.5 QA.

**Unconfirmed — this is what Phase 0 must establish:**
- Whether order #186's trigger is bfcache/tab-restore replaying a previously-queued or previously-trusted click/submit (e.g., an in-flight XHR at tab-close resuming or retrying on reopen).
- Whether `pageshow` with `event.persisted === true` (back-forward cache restore) re-runs any initialization code that ends up calling `bsCheckoutLoadConfirmAndSubmit` or hitting `confirm.confirm` directly.
- Whether Hutko being auto-selected as default payment method interacts with the restore — e.g. a `change` event synthesized by the browser restoring form state fires a handler that (incorrectly) reads as trusted.
- Whether the existing double-submit / in-flight guard (`bsCheckoutConfirmSubmitting`-equivalent from ST-2b.1) itself is stuck in a restored state and something in its reset path fires the load.
- The dashboard lists 3 unverified suspects (do not treat as confirmed): (1) tab close/reopen behavior generally, (2) auto-selected Hutko firing a call on page load/restore, (3) the shipping-address micro-update refresh interacting with a restored tab.

Do not assume any of the above is the actual cause — this handoff exists to capture evidence, not to fix blind.

## 3. Goal

Identify the exact trigger that creates order #186-class phantom Hutko orders on tab close/reopen, with evidence (log entries + stack trace) captured from a real repro. No behavior change to order creation in this phase. Phase 1 (the actual fix) is a separate handoff written after Phase 0 evidence comes back.

## 4. What to change (Phase 0 only)

- Instrument the known `confirm.confirm` call site(s) in `checkout.twig` (and the same fallback call sites ST-2b.4 Phase 0 already found in `payment_method.twig`, `shipping_address.twig`, `register.twig`, `payment_address.twig` — re-grep live to confirm they're still present in that form after ST-2b.4/2b.5 landed) with a logger that captures: triggering `event.type`, `event.isTrusted`, `document.activeElement`, event target/currentTarget, `document.visibilityState`, and a stack trace. Reuse/extend the ST-2b.4 Phase 0 pattern (`bsSt2b4LogConfirm`-style helper) rather than inventing a new one, if it's still present in the live file — check first.
- Additionally instrument `window.addEventListener('pageshow', ...)`, `pagehide`, and `visibilitychange` at the checkout page level: log `event.persisted`, timestamp, current payment/shipping selection state, and whether any in-flight confirm/submit flag is set at that moment.
- Do NOT remove or alter the ST-2b.4 trusted-pointer-click gate. Do NOT change order-creation logic, Hutko `buildRequest`/amount, or the ST-2a.4 void-status net in this phase.
- Provide a repro script for the owner: open checkout, fill address, select Hutko, leave tab open X minutes, close browser fully, reopen, navigate back to the tab (or reopen via history) — capture whatever fires, if anything, along with `oc_order` row count before/after.
- Deliver captured log output (or documented absence of any trigger after N repro attempts) before any Phase 1 fix is proposed.

## 5. Do not touch

Hutko `buildRequest`/amount/sign/api logic, `payment_hutko_shipping_include`, Checkbox/fiscalization, `getQuote` cost, coupon/First15 totals, ST-2a.4 void-status net (keep it as-is — it is the current safety fallback), ST-2b.1 deferred-confirm trusted-click gate (keep it, do not weaken), ST-2b.2/2b.3 success-page and summary-capture logic, CRM payload field shape, NP model, DB schema, order_status config, `sitemap.xml`, `robots.txt`, canonical, redirects, `.htaccess`, Merchant feed, schema/JSON-LD. This task only adds observability — it changes nothing about when or how an order is actually created.

## 6. Likely files / areas (verify against LIVE; clear template cache after)

- `catalog/view/template/checkout/checkout.twig` — `bsCheckoutLoadConfirmAndSubmit`, `click.bsSt2b1DeferredConfirm` handler, any existing ST-2b.4 diagnostic helper (check if still present or already stripped post-2b.4).
- `catalog/view/template/checkout/payment_method.twig`, `shipping_address.twig`, `register.twig`, `payment_address.twig` — legacy `confirm.confirm` fallback call sites ST-2b.4 Phase 0 found; re-grep to confirm current state.
- New: page-level `pageshow`/`pagehide`/`visibilitychange` listeners — likely added fresh in `checkout.twig` (there is no evidence of existing bfcache handling in the confirmed context above).
- Codex should verify against actual project files; re-grep the live tree (catalog + theme + active extensions) for any `confirm.confirm` caller before assuming the list above is complete.

## 7. Acceptance criteria (measurable)

1. A captured diagnostic log (or documented failed-repro evidence) that identifies what fires on the tab close/reopen repro — event type, `isTrusted`, `persisted` flag, stack trace.
2. No change in `oc_order` row-creation behavior during Phase 0 (instrumentation only) — confirm via CRM `action=orders` count before/after applying this patch on a clean session with no repro attempted.
3. Existing ST-2b.1/ST-2b.4 guarantees unaffected: switching payment methods (mouse/keyboard) still creates zero draft orders; explicit place-order click still creates exactly one order.
4. Diagnostic code is clearly marked/flagged for removal before final deploy (same convention as `st2b4_phase0_confirm_trigger_diagnostics`).

## 8. QA / smoke test

Run `bs-checkout-smoke` baseline (11-step) once after applying Phase 0 instrumentation to confirm no regression from the added logging alone — this is observability-only, so the smoke test here is a regression check, not a validation of a fix. Additionally: perform the tab close/reopen repro at least 3 times (varying wait time before reopen, varying whether Hutko or COD is pre-selected) and capture the console/log output each time, including cases where nothing reproduces.

## 9. Rollback

Backup edited file(s) to `_patch_backups/st2b6-*` before write; restore + clear OpenCart `template/`+`cache.*` if anything regresses. Since this phase is instrumentation-only (no order-creation logic touched), rollback risk is low, but treat as HIGH-RISK deploy window anyway because it touches the same files as the live checkout guarantee.

## 10. Recommended status after execution

Phase 0 done + evidence delivered → ST-2b.6 status: `На діагностиці` → write Phase 1 fix handoff based on findings (or close as "not reproducible after N attempts, monitoring" if no trigger is captured — owner decision). Unblocks ST-2c only once Phase 1 (if needed) ships and the zero-draft-order guarantee is re-verified. Update `booster-dashboard.html` ROADMAP_FLOW status for ST-2b.6 in the same session per `ROADMAP_SOP.md §3-4`. Not yet in Notion — owner decision needed on whether to add it there now or after Phase 0 evidence is in hand.
